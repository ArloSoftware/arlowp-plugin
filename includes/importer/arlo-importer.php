<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;
use ArloTraining\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Runs one subtask of the fragmented, multi-step Arlo import pipeline per
 * invocation, persisting progress via the scheduler's task data so the next
 * self-kicked request can resume exactly where this one left off.
 *
 * Remarks:
 * - Subtask order is `$import_tasks`: ImportRequest, Download, ProcessFragment,
 *   CategoryDepth, Finish.
 * - Guarded by two independent locks: `Scheduler`'s network-wide process lock
 *   (one subtask executing at a time across the whole pipeline) and this
 *   class's own token-scoped `arlo_import_lock` DB-table mutex.
 * - A completed non-terminal subtask re-triggers immediately via
 *   `kick_off_scheduler()`. `ImportRequest` instead waits for the platform
 *   callback, while a terminal completion does not re-trigger. A retryable
 *   failure defers via `Scheduler::kick_off_scheduler_with_backoff()` (see
 *   `run_locked_import()`).
 */
class Importer {
	/**
	 * Maximum number of consecutive attempts allowed for a single subtask
	 * iteration — whether counted at the top level or within a subtask's own
	 * nested state (e.g. fragment processing) — before that attempt is
	 * abandoned as unrecoverable.
	 */
	const MAX_RETRY_ATTEMPT = 5;

	/**
	 * PHP execution time limit, in seconds, applied for the duration of a
	 * single import-pipeline request (a scheduler-triggered subtask run, or
	 * the platform's asynchronous snapshot callback). The original limit is
	 * restored once the request finishes, so this only widens the ceiling
	 * for the work this class itself performs.
	 */
	const IMPORT_TIMEOUT_SECONDS = 90;

	/**
	 * Minimum number of seconds that must elapse after a successful import
	 * before another automatic import may start; bypassed entirely for a
	 * forced manual sync. Independent of, and shorter than, the
	 * deployment-mode cadence throttle in `Arlo_For_Wordpress::get_sync_interval()`.
	 */
	const IMPORT_INTERVAL_MIN_SECONDS = 600; // 10 minutes

	/**
	 * Number of platform-access failures (`arlo_platform_access_failure_count`)
	 * required, together with `IMPORT_FAILURE_MIN_DURATION_HOURS`, to trigger
	 * automatically disabling imports.
	 */
	const IMPORT_FAILURE_MAX_COUNT = 10;

	/**
	 * Minimum number of hours the current failure streak must span, together
	 * with `IMPORT_FAILURE_MAX_COUNT`, to trigger automatically disabling
	 * imports. Prevents a short burst of rapid failures from tripping the
	 * disable on count alone.
	 */
	const IMPORT_FAILURE_MIN_DURATION_HOURS = 3;

	/**
	 * Escalating minimum interval, in seconds, between full import attempts
	 * while consecutive platform-access failures (`arlo_platform_access_failure_count`)
	 * are accumulating. Indexed by consecutive failure count (a count of 1 uses the
	 * first entry). The final entry is reused for any count beyond the length of
	 * the ladder. Reset to "no extra interval" the moment a full attempt succeeds,
	 * since the counter itself is reset on success.
	 *
	 * This is a separate, additional gate to the last-success-relative throttles
	 * (`IMPORT_INTERVAL_MIN_SECONDS` here, and the deployment-mode throttle in
	 * `Arlo_For_Wordpress::get_sync_interval()`), which continue to serve their
	 * existing purpose of rate-limiting a healthy site's re-sync frequency.
	 *
	 * @since   5.2.0
	 */
	const IMPORT_FAILURE_BACKOFF_SECONDS = array(
		30 * MINUTE_IN_SECONDS,
		30 * MINUTE_IN_SECONDS,
		90 * MINUTE_IN_SECONDS,
		2 * HOUR_IN_SECONDS,
		12 * HOUR_IN_SECONDS,
	);

	protected $data_json;

	private $environment;
	private $message_handler;
	private $api_client;
	private $scheduler;
	private $importing_parts;
	private $mutex;
	private $snapshot_part_provider;
	private $snapshot_manifest_provider;
	private $delta_test_fault_selector;
	private $delta_run_parameters;

	private $current_import_id;
	private $last_import_date;
	private $import_completion_detail;

	private $current_task_iteration = 0;
	private $state;
	private $task_id;
	private $task_deferred = false;

	/**
	 * Ordered top-level import pipeline and its task descriptions.
	 *
	 * Keys determine checkpoint progression and must match `IMPORT_TASK_WEIGHTS`.
	 * `DeltaRunCoordinator` is an internal routing step, not a snapshot JSON key.
	 *
	 * @var array<string,string>
	 */
	public $import_tasks = [
		'ImportRequest'       => "Request a database snapshot",
		'Download'            => "Download snapshot file",
		'DeltaRunCoordinator' => 'Checking snapshot changes',
		'ProcessFragment'     => "Process fragments",
		'CategoryDepth'       => 'Updating category depth',
		'Finish'              => 'Finalize the import',
	];

	/**
	 * Fixed weight (percentage points out of 100) allocated to each top-level
	 * import subtask, used to compute an overall progress percentage. Weights
	 * reflect each subtask's typical share of total import time, not its
	 * position in the sequence — `ProcessFragment` dominates because it is
	 * where the bulk of the actual catalogue data is imported; the others
	 * are comparatively quick, fixed-cost steps regardless of catalogue size.
	 *
	 * Keys must match `$import_tasks`; values must sum to 100.
	 *
	 * @since   5.2.0
	 */
	const IMPORT_TASK_WEIGHTS = [
		'ImportRequest'       => 5,
		'Download'            => 5,
		'DeltaRunCoordinator' => 0,
		'ProcessFragment'     => 80,
		'CategoryDepth'       => 5,
		'Finish'              => 5,
	];

	public $current_task;
	public $current_task_class;
	public $current_task_num;
	public $current_task_desc  = '';
	public $current_task_retry = 0;

	public $fragment_size;
	private $incremental_run_id;

	public $import_id;
	public $nonce;
	public $is_finished = false;

	public function __construct( $environment, $message_handler, $api_client, $scheduler, $importing_parts ) {
		$this->environment            = $environment;
		$this->message_handler        = $message_handler;
		$this->api_client             = $api_client;
		$this->scheduler              = $scheduler;
		$this->importing_parts        = $importing_parts;
		$this->snapshot_part_provider = new RemoteHttpSnapshotPartProvider();
	}

	public function generate_import_id() {
		return \ArloTraining\Utilities::get_random_int();
	}

	public function set_import_id( $import_id ) {
		$this->import_id = $import_id;
	}

	/**
	 * Sets the provider used to retrieve decoded snapshot fragments for this importer request.
	 *
	 * @param SnapshotPartProvider $provider Provider to apply.
	 * @return void
	 */
	public function set_snapshot_part_provider( SnapshotPartProvider $provider ): void {
		$this->snapshot_part_provider = $provider;
	}

	/**
	 * Returns the configured decoded snapshot provider.
	 *
	 * ProcessFragment and DeltaRunCoordinator use this when they retrieve snapshot fragments.
	 *
	 * @return SnapshotPartProvider
	 */
	public function get_snapshot_part_provider(): SnapshotPartProvider {
		return $this->snapshot_part_provider;
	}

	/**
	 * Returns the decoded admitted snapshot image retained by the importer.
	 *
	 * @return string Image JSON for the current admitted import.
	 * @throws \ArloTraining\SnapshotProcessException When the admitted image is unavailable.
	 */
	public function get_admitted_snapshot_image_content(): string {
		$item = $this->importing_parts->get_import_part( 'image', null, $this->import_id );
		if ( ! is_object( $item ) || ! isset( $item->import_text ) || ! is_string( $item->import_text ) || '' === $item->import_text ) {
			throw new \ArloTraining\SnapshotProcessException( 'Admitted snapshot image is unavailable.' );
		}

		return $item->import_text;
	}

	/**
	 * Gets metadata for the admitted snapshot, including details of the source
	 * platform and transport encryption.
	 *
	 * @return SnapshotContext Admitted snapshot transport and decryption context.
	 * @throws \ArloTraining\SnapshotProcessException When admitted callback metadata is invalid.
	 */
	public function get_admitted_snapshot_context(): SnapshotContext {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are authenticated external snapshot response field names.
		$import = $this->get_import_entry( $this->import_id, null, 1 );
		if ( ! is_object( $import ) || empty( $import->callback_json ) || empty( $import->response_json ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Admitted snapshot transport metadata is unavailable.' );
		}
		$callback = json_decode( $import->callback_json );
		$response = json_decode( $import->response_json );
		if ( ! is_object( $callback ) || ! is_object( $response )
			|| ! isset( $response->ArloMetadata->PlatformHostname, $response->Result->EncryptedResponse->key->k, $response->Result->EncryptedResponse->enc )
			|| ! is_scalar( $response->ArloMetadata->PlatformHostname ) || ! is_scalar( $response->Result->EncryptedResponse->key->k ) || ! is_scalar( $response->Result->EncryptedResponse->enc ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Admitted snapshot transport metadata is invalid.' );
		}

		return new SnapshotContext( (string) $response->ArloMetadata->PlatformHostname, (string) $response->Result->EncryptedResponse->key->k, (string) $response->Result->EncryptedResponse->enc, (int) $this->import_id );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Sets the process-local provider used to retrieve an admitted snapshot manifest.
	 *
	 * @param SnapshotManifestProvider|null $provider Provider to apply, or null to retain the default.
	 * @return void
	 */
	public function set_snapshot_manifest_provider( ?SnapshotManifestProvider $provider ): void {
		$this->snapshot_manifest_provider = $provider;
	}

	/**
	 * Returns the optional process-local manifest provider.
	 *
	 * @return SnapshotManifestProvider|null
	 */
	public function get_snapshot_manifest_provider(): ?SnapshotManifestProvider {
		return $this->snapshot_manifest_provider;
	}

	/**
	 * Configures one fixed delta fault for the current test request only.
	 *
	 * @param string|null $selector Fixed DeltaRunCoordinator test selector, or null to disable it.
	 * @return void
	 */
	public function set_delta_test_fault_selector( ?string $selector ): void {
		if ( null !== $selector && ! in_array( $selector, [ DeltaRunCoordinator::TEST_FAULT_OVERLAY_PRE_COMMIT, DeltaRunCoordinator::TEST_FAULT_AFTER_FIRST_COMMITTED_ROOT_ABORT, DeltaRunCoordinator::TEST_FAULT_BEFORE_BASELINE_PROMOTION_MUTEX_LOSS ], true ) ) {
			throw new \InvalidArgumentException( 'Delta test fault selector is invalid.' );
		}

		$this->delta_test_fault_selector = $selector;
	}

	/**
	 * Sets request-local limits for the next DeltaRunCoordinator in this importer request.
	 *
	 * @param DeltaRunParameters $parameters Delta coordinator parameters.
	 * @return void
	 */
	public function set_delta_run_parameters( DeltaRunParameters $parameters ): void {
		$this->delta_run_parameters = $parameters;
	}

	public function set_current_import_id( $import_id ) {
		update_option( 'arlo_import_id', $import_id );

		$this->current_import_id = $import_id;
	}

	/**
	 * Returns the current live import id from the options table.
	 *
	 * @param bool $force_refresh Whether to bypass the importer's per-request cached value.
	 * @param bool $lock_for_update Whether to lock the durable option row in an active transaction.
	 * @return string|int|null Current live import id, or null when unset.
	 */
	public function get_current_import_id( $force_refresh = false, $lock_for_update = false ) {
		if ( ! $force_refresh && $this->current_import_id !== null ) {
			return $this->current_import_id;
		}

		global $wpdb;
		// Bypass WP's options cache (get_option may return a stale value during import).
		// Result is cached in $this->current_import_id for the remainder of this request.
		if ( $lock_for_update ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query bypasses WP options cache; per-request caching is handled by the property.
			$this->current_import_id = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s FOR UPDATE', $wpdb->prefix . 'options', 'arlo_import_id' ) );
			if ( '' !== (string) $wpdb->last_error ) {
				// The locking read runs inside a transaction that compares this id with its own, so a null from a failed read must not look like "no live import".
				$this->current_import_id = null;
				throw new \RuntimeException( 'Could not lock the live import id: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by the surrounding transaction, which rolls back, and logged by the import pipeline; never rendered.
			}
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query bypasses WP options cache; per-request caching is handled by the property.
			$this->current_import_id = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->prefix . 'options', 'arlo_import_id' ) );
		}

		return $this->current_import_id;
	}

	public function set_last_import_date() {
		$now       = \ArloTraining\Utilities::get_now_utc();
		$timestamp = $now->format( "Y-m-d H:i:s" );

		update_option( 'arlo_last_import', $timestamp );
		$this->last_import_date = $timestamp;
	}

	public function set_tax_exempt_events( $import_id ) {
		global $wpdb;
		$settings = get_option( 'arlo_settings' );

		if ( ! empty( $settings['taxexempt_tag'] ) ) {
			$sql = $wpdb->prepare(
				"
			UPDATE
				{$wpdb->prefix}arlo_events AS e,
				{$wpdb->prefix}arlo_events_tags AS et,
				{$wpdb->prefix}arlo_tags AS t
			SET
				e_is_taxexempt = 1
			WHERE
				t.tag = %s
			AND
				t.id = et.tag_id
			AND
				et.e_id = e.e_id
			AND
				et.import_id = %d
			AND
				t.import_id = %d
			AND
				e.import_id = %d
			",
				[ trim( $settings['taxexempt_tag'] ), $import_id, $import_id, $import_id ]
			);
		} else {
			$sql = $wpdb->prepare(
				"
			UPDATE
				{$wpdb->prefix}arlo_events AS e
			SET
				e_is_taxexempt = 0
			WHERE
				e.import_id = %d
			",
				[ $import_id ]
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared --  Direct database query is required for custom table. Do not need cache for import process. SQL is prepared above
		$query = $wpdb->query( $sql );

		if ( $query === false ) {
			throw new \Exception( 'SQL error at set_tax_exempt_events: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	public function get_last_import_date() {
		if ( ! is_null( $this->last_import_date ) ) {
			return $this->last_import_date;
		}

		$this->last_import_date = get_option( 'arlo_last_import' );

		return $this->last_import_date;
	}

	public function set_state( $state ) {
		$this->state = $state;
		$task_keys   = array_keys( $this->import_tasks );
		$moved_to    = null;

		if ( ! empty( $state ) ) {
			if ( ! empty( $state->current_subtask ) ) {
				$this->set_current_task( $state->current_subtask );
				// Advance the iteration only once the previous one is confirmed finished (or on a
				// fresh subtask with no prior iteration state) — otherwise this is a retry of the
				// SAME iteration, so it must be replayed, not skipped forward.
				$this->current_task_iteration = ( isset( $state->iteration ) && is_numeric( $state->iteration ) ? ( ( isset( $state->subtask_state ) && $state->subtask_state->iteration_finished == 1 ) || ! isset( $state->subtask_state ) ? $state->iteration + 1 : $state->iteration ) : 0 );
			} elseif ( ! empty( $state->finished_subtask ) ) {
				//figure out the next task;
				if ( 'Download' === $state->finished_subtask ) {
					$this->set_current_task( 'ProcessFragment' );
					$state->current_subtask_retry = 0;
					$moved_to                     = 'ProcessFragment';
				} else {
					$k = array_search( $state->finished_subtask, $task_keys, true );
					if ( $k !== false && isset( $task_keys[ ++$k ] ) ) {
						$this->set_current_task( $task_keys[ $k ] );
						$state->current_subtask_retry = 0;
						$moved_to                     = $task_keys[ $k ];
					} else {
						$this->is_finished = true;
					}
				}
			} else {
				$this->set_current_task( $task_keys[0] );
			}
		} else {
			$this->set_current_task( $task_keys[0] );
		}

		// The retry counter this feeds (should_retry(), and the scheduler backoff via
		// get_current_retry_attempt()) is provisionally incremented on EVERY call, before
		// we know whether this attempt will succeed or fail. run_locked_import()'s success
		// path decrements it right back afterward so it only nets upward across genuine
		// failures, never across healthy progress.
		$this->current_task_retry = ( isset( $state->current_subtask_retry ) ? $state->current_subtask_retry + 1 : $this->current_task_retry + 1 );
		// Persist only the incremented retry counter — not the full state. At this point
		// current_task_class is always null (it is assigned inside run_import_task(), which
		// runs after this call), so get_state() would return iteration = null and
		// subtask_state = null. The legacy full-state write passed those nulls through
		// the task-data merge and overwrote valid position fields in the stored checkpoint,
		// resetting a concurrent reader to fragment 1. The full mutex-owned state write
		// that captures the post-subtask position already occurs in run_locked_import()'s
		// success path after run_import_task() returns.
		$early_write = [ 'current_subtask_retry' => $this->current_task_retry ];
		if ( ! is_null( $moved_to ) ) {
			// Moving past a finished subtask must be recorded with the counter. Otherwise the stored
			// checkpoint still reads "previous subtask finished", the next wake takes this branch
			// again and resets the counter, so a subtask that fails every time is never capped.
			$early_write['finished_subtask'] = null;
			$early_write['current_subtask']  = $moved_to;
		}

		return $this->scheduler->update_task_data_if_mutex_owned( $this->task_id, $early_write, $this->get_mutex() );
	}

	/**
	 * Merges task data only while this importer request still owns the mutex token.
	 *
	 * @param int   $task_id Task id whose data should be updated.
	 * @param array $data    Partial task data to merge into the stored checkpoint.
	 * @return bool True when the token-owned write succeeded.
	 */
	public function update_task_data_if_mutex_owned( $task_id, $data ): bool {
		return $this->scheduler->update_task_data_if_mutex_owned( $task_id, $data, $this->get_mutex() );
	}

	/**
	 * Seals the configured mode into one immutable new-session admission result.
	 *
	 * @param string $mode Metadata-free configured importer mode.
	 * @return string Admitted importer mode for the current session.
	 */
	public function admit_importer_mode( string $mode ): string {
		if ( \Arlo_For_Wordpress::IMPORTER_MODE_FULL_DELTA !== $mode ) {
			return \Arlo_For_Wordpress::IMPORTER_MODE_FULL;
		}

		return $this->supports_delta_infrastructure()
			? \Arlo_For_Wordpress::IMPORTER_MODE_FULL_DELTA
			: \Arlo_For_Wordpress::IMPORTER_MODE_FULL;
	}

	public function get_state() {
		if ( ! empty( $this->incremental_run_id ) ) {
			return [
				'finished_subtask'      => null,
				'current_subtask'       => 'DeltaRunCoordinator',
				'incremental_run_id'    => $this->incremental_run_id,
				'current_subtask_retry' => $this->current_task_retry,
				'iteration'             => null,
				'subtask_state'         => null,
			];
		}

		$state = [
			'finished_subtask'      => null,
			'current_subtask'       => null,
			'current_subtask_retry' => $this->current_task_retry,
			'iteration'             => null,
			'subtask_state'         => null,
		];

		if ( ! is_null( $this->current_task_class ) ) {
			$state['subtask_state'] = $this->current_task_class->get_state();

			if ( $this->current_task_class->is_finished ) {
				$state['finished_subtask'] = $this->current_task;
			} else {
				$state['current_subtask'] = $this->current_task;
				$state['iteration']       = $this->current_task_class->iteration;
			}
		} else {
			$state['current_subtask'] = $this->current_task;
		}

		return $state;
	}

	/**
	 * Decides whether a brand-new import attempt is allowed to start right now.
	 *
	 * Only applies to scheduled/automatic starts — a forced manual sync
	 * (`$force = true`) always bypasses every check performed here.
	 *
	 * Remarks:
	 * - Two independent gates, both additive to the WP-Cron tick cadence itself:
	 *   the `IMPORT_INTERVAL_MIN_SECONDS` guard (keyed to time since the last
	 *   successful import) and the `IMPORT_FAILURE_BACKOFF_SECONDS` escalation
	 *   (keyed to time since the last platform-access failure and how many
	 *   consecutive failures have accumulated). Either can independently stop
	 *   this tick from starting a new attempt.
	 * - The escalation gate is a no-op once `arlo_platform_access_failure_count`
	 *   is back to 0 — it only engages once a genuine chronic streak exists.
	 * - This method has no reset logic of its own for that counter; it simply
	 *   reads the current health-counter state, so the gate stands down
	 *   automatically whenever the counter is reset after a successful import or
	 *   reachable-platform outcome.
	 *
	 * @param bool $force Whether this is a manually forced sync.
	 * @param bool $log   Whether to write diagnostic log entries while evaluating the gate.
	 * @return bool True if a new import attempt may start now.
	 */
	public function should_importer_run( $force = false, $log = true ) {
		if ( ! $force ) {
			$elapsed = $this->get_seconds_since_last_import();
			if ( $elapsed !== false && $elapsed < self::IMPORT_INTERVAL_MIN_SECONDS ) {
				if ( $log ) {
					Logger::log( 'Last import is less than ' . self::IMPORT_INTERVAL_MIN_SECONDS . ' seconds old. Import task skipped.', $this->import_id );
				}
				return false;
			}

			$failure_wait = $this->get_seconds_until_next_failure_retry();
			if ( $failure_wait > 0 ) {
				if ( $log ) {
					$count           = intval( get_option( 'arlo_platform_access_failure_count', 0 ) );
					$last_failure_at = get_option( 'arlo_platform_access_last_failure_at', '' );
					Logger::log( 'Import task is backing off after ' . $count . ' consecutive platform-access ' . ( $count === 1 ? 'failure' : 'failures' ) . '. Last failure at ' . $last_failure_at . ' UTC; next attempt allowed in ' . $failure_wait . ' seconds.', $this->import_id );
				}
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns the number of seconds still remaining before a new import attempt
	 * is allowed under the chronic-failure escalation, or 0 if it is already
	 * allowed (including when there is no active failure streak at all, or when
	 * connection health checks are disabled, or when the stored failure timestamp
	 * is unusable).
	 *
	 * @return int
	 */
	private function get_seconds_until_next_failure_retry() {
		$arlo_settings = get_option( 'arlo_settings', [] );
		if ( ( $arlo_settings['import_connection_healthchecks_enabled'] ?? '1' ) !== '1' ) {
			return 0;
		}

		$count = intval( get_option( 'arlo_platform_access_failure_count', 0 ) );
		if ( $count <= 0 ) {
			return 0;
		}

		$last_failure_at = get_option( 'arlo_platform_access_last_failure_at', '' );
		if ( empty( $last_failure_at ) ) {
			return 0;
		}

		try {
			$last_failure_ts = ( new \DateTime( $last_failure_at, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}

		$now_ts = time();
		if ( $last_failure_ts > $now_ts ) {
			// Future timestamp (corrupt data) — fail open rather than blocking until it elapses.
			return 0;
		}

		$ladder   = self::IMPORT_FAILURE_BACKOFF_SECONDS;
		$required = $ladder[ min( $count - 1, count( $ladder ) - 1 ) ]; // $count is 1-indexed; -1 converts to the array's 0-indexed rung.
		$elapsed  = $now_ts - $last_failure_ts;

		return max( 0, $required - $elapsed );
	}

	/**
	 * Returns the number of seconds since the last successful import,
	 * or false if no valid timestamp is available.
	 * Negative values (future timestamps from corrupt data) are treated as zero.
	 *
	 * @return int|false
	 */
	private function get_seconds_since_last_import() {
		$last_import_date = $this->get_last_import_date();
		if ( empty( $last_import_date ) ) {
			return false;
		}

		try {
			$last_utc_ts = ( new \DateTime( $last_import_date, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return false;
		}

		return max( 0, time() - $last_utc_ts );
	}

	public function check_viable_execution_environment() {
		return $this->environment->check_viable_execution_environment();
	}

	public function set_current_task( $task_step ) {
		$this->current_task      = $task_step;
		$this->current_task_num  = array_search( $this->current_task, array_keys( $this->import_tasks ), true );
		$this->current_task_desc = $this->import_tasks[ $this->current_task ];
	}

	private function get_data_json() {
		$item = $this->importing_parts->get_import_part( "image", null, $this->import_id );

		if ( empty( $item ) ) {
			throw new \Exception( "Import Error: the import \"image\" part cannot be found" );
		}
		if ( empty( $item->import_text ) ) {
			throw new \Exception( "Import Error: the content of the import part is empty" );
		}

		$this->data_json = json_decode( $item->import_text );

		if ( is_null( $this->data_json ) ) {
			throw new \Exception( "JSON Error: " . json_last_error_msg() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	/**
	 * Resolves the task state for an import run.
	 *
	 * For a resumed import, returns the decoded task state object.
	 * For a new import, initialises the task and returns null.
	 * Returns false if the run should be aborted (throttled or invalid state).
	 * When a new task is intentionally deferred by a throttle, the task is marked
	 * finished/skipped and `$task_deferred` is set so the scheduler does not
	 * classify the skip as an import failure.
	 *
	 * @param bool       $force Whether to bypass the import interval throttle.
	 * @param array|null $rows  Optional task row set already read after acquiring the mutex.
	 * @return object|null|false
	 */
	private function resolve_task_state( $force, $rows = null ) {
		if ( is_null( $rows ) ) {
			$rows = $this->scheduler->get_task_data( $this->task_id );
		}
		if ( empty( $rows ) ) {
			return false;
		}
		$task = $rows[0];

		// Resuming an in-progress import
		if ( ! empty( $task->task_data_text ) ) {
			$state = json_decode( $task->task_data_text );
			if ( ! is_object( $state ) || empty( $state->import_id ) ) {
				return false;
			}
			$this->set_import_id( $state->import_id );
			return $state;
		}

		// Starting a new import - check throttle.
		if ( ! $this->should_importer_run( $force ) ) {
			$this->task_deferred = true;
			if ( $this->scheduler->update_task_if_active( $this->task_id, 4, 'Import task skipped: import is currently deferred' ) ) {
				// Run after the terminal skip so chronic backoff still gives log maintenance a chance.
				Logger::execute_log_maintenance();
			}
			return false;
		}

		$start_message = 'Import started';
		if ( $force ) {
			$start_message .= ' (manual)';
		} else {
			$failure_count = intval( get_option( 'arlo_platform_access_failure_count', 0 ) );
			if ( $failure_count > 0 ) {
				$start_message .= ' (attempt ' . ( $failure_count + 1 ) . ')';
			}
		}

		Logger::log( $start_message, $this->import_id );
		if ( ! $this->scheduler->update_task_data_if_mutex_owned(
			$this->task_id,
			[ 'import_id' => $this->import_id ],
			$this->get_mutex()
		) ) {
			$this->task_deferred = true;
			Logger::log( 'Import setup stopped because another process took over. Another request may continue it.', $this->import_id );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import setup state write was rejected [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; reason=mutex_ownership_lost]', $this->import_id );
			return false;
		}
		// Run maintenance only after the initial state write is accepted; rejected stale workers must stay cheap.
		Logger::execute_log_maintenance();
		// A started import or retry attempt gives rejected callbacks a fresh logging budget.
		Logger::reset_rejected_callback_throttle();

		return null;
	}

	/**
	 * Runs one scheduler-selected import step under the request time-limit guard.
	 *
	 * @param bool $force   Whether this is a manually forced import.
	 * @param int  $task_id Async task id selected by the scheduler.
	 * @return bool True when the step completed or deferred; false on setup failure.
	 */
	public function run( $force = false, $task_id = 0 ) {
		$retval              = false;
		$original_time_limit = $this->environment->arlo_set_time_limit( self::IMPORT_TIMEOUT_SECONDS );
		try {
			$retval = $this->execute_import( $force, $task_id );
		} catch ( \Exception $e ) {
			Logger::log( $e->getMessage() );
		} finally {
			$this->environment->arlo_set_time_limit( $original_time_limit );
		}

		return $retval;
	}

	/**
	 * Acquires the import mutex, resolves fresh task state, and runs one locked subtask.
	 *
	 * Task state is deliberately read only after the DB mutex is acquired so competing
	 * scheduler requests cannot both resume from the same checkpoint snapshot.
	 *
	 * @param bool $force   Whether this is a manually forced import.
	 * @param int  $task_id Async task id selected by the scheduler.
	 * @return bool True for completed/deferred work, false for terminal setup failure.
	 */
	private function execute_import( $force, $task_id ) {
		$this->task_id       = intval( $task_id );
		$this->task_deferred = false;
		$mutex_acquired      = false;

		$this->set_import_id( \ArloTraining\Utilities::get_random_int() );

		try {
			$lock_result = $this->get_mutex()->acquire();
			if ( $lock_result !== ImportMutex::ACQUIRED ) {
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Import mutex not acquired [result=' . $lock_result . '; task_id=' . $this->task_id . '; force=' . ( $force ? 'true' : 'false' ) . ']', $this->import_id );
				if ( $lock_result === ImportMutex::ALREADY_HELD ) {
					// Another request is actively processing this site; leave the task queued for its owner to hand off after release.
					$this->task_deferred = true;
					Logger::log( 'Another import is already running. This import will continue later.' );
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import lock is already held by another worker [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; result=' . $lock_result . '; force=' . ( $force ? 'true' : 'false' ) . ']', $this->import_id );
					return $this->task_deferred;
				}

				if ( $lock_result === ImportMutex::DB_RETRYABLE ) {
					$this->task_deferred = true;
					Logger::log( 'Import paused because the database was temporarily unavailable. It will retry shortly.' );
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import lock acquire hit a retryable database error [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; result=' . $lock_result . '; force=' . ( $force ? 'true' : 'false' ) . ']', $this->import_id );
					return $this->task_deferred;
				}

				return $this->handle_lock_failure();
			}
			$mutex_acquired = true;

			$this->scheduler->clear_immediate_fallback_cron();

			if ( $this->task_id > 0 ) {
				// Re-read after acquiring the mutex; the scheduler's selected row can be stale by now.
				$task_rows = $this->scheduler->get_task_data( $this->task_id );
				if ( empty( $task_rows ) ) {
					return false;
				}

				$status = isset( $task_rows[0]->task_status ) ? intval( $task_rows[0]->task_status ) : null;
				if ( $status === 3 || $status === 4 ) {
					// Cooperative termination wins over a stale self-kick that was already in flight.
					$this->release_import_mutex();
					$this->task_deferred = true;
					Logger::log( 'Import task has already ended. This worker will stop.' );
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Scheduler task was terminal when the worker re-read it [task_id=' . $this->task_id . '; status=' . ( is_null( $status ) ? 'null' : $status ) . ']', $this->import_id );
					return $this->task_deferred;
				}

				$task_state = $this->resolve_task_state( $force, $task_rows );
				Logger::log_diagnostic(
					Logger::DIAGNOSTIC_VERBOSITY_TRACE,
					function () use ( $task_state, $status ) {
						return 'Resolved scheduler task [task_id=' . $this->task_id . ( is_null( $status ) ? '' : '; status=' . $status ) . '; state=[' . $this->format_task_state_for_diagnostic_log( $task_state ) . ']]';
					},
					$this->import_id
				);
				if ( $task_state === false ) {
					return $this->task_deferred;
				}

				$this->get_mutex()->set_import_id( (int) $this->import_id );
			}

			// A resumed task replaces the provisional request ID while resolving its state.
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Import mutex acquired [task_id=' . $this->task_id . '; force=' . ( $force ? 'true' : 'false' ) . ']', $this->import_id );

			return $this->run_locked_import( isset( $task_state ) ? $task_state : null, $force );
		} finally {
			$this->release_import_mutex();
			$this->scheduler->unlock_process( 'import' );
			if ( $mutex_acquired ) {
				// A competing worker can defer after observing this import mutex as held.
				// Wake the scheduler after the terminal owner releases it rather than waiting
				// for the recurring cron safety net.
				$this->maybe_kick_off_next_queued_task();
			}
		}
	}

	/**
	 * Starts the scheduler immediately when another task is waiting in the queue.
	 *
	 * @return void
	 */
	private function maybe_kick_off_next_queued_task(): void {
		if ( $this->task_id > 0
			&& $this->scheduler->is_task_terminal( $this->task_id )
			&& $this->scheduler->has_waiting_task() ) {
			$this->scheduler->kick_off_scheduler();
		}
	}

	/**
	 * Returns the per-request import mutex, creating it lazily for test injection.
	 *
	 * @return ImportMutex Mutex used by this importer request.
	 */
	protected function get_mutex(): ImportMutex {
		if ( is_null( $this->mutex ) ) {
			$this->mutex = new ImportMutex( $GLOBALS['wpdb'] );
		}

		return $this->mutex;
	}

	/**
	 * Releases this request's import mutex if it currently owns one.
	 *
	 * @return void
	 */
	private function release_import_mutex(): void {
		if ( $this->get_mutex()->release() ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Import mutex released [task_id=' . $this->task_id . ']', $this->import_id );
		}
	}

	/**
	 * Confirms that this importer request still owns the import mutex.
	 *
	 * @return bool True when the token still owns the lock row.
	 */
	public function confirm_import_mutex_ownership(): bool {
		return $this->get_mutex()->confirm_ownership();
	}

	/**
	 * Extends the import mutex lease for long irreversible phases.
	 *
	 * @param int $ttl_seconds Number of seconds to extend from database time.
	 * @return bool True when ownership is retained.
	 */
	public function extend_import_mutex_ownership( $ttl_seconds = ImportMutex::TTL_SECONDS ): bool {
		return $this->get_mutex()->extend_ownership( $ttl_seconds );
	}

	/**
	 * Handles missing or stale lock-table schema by invoking the schema repair fallback.
	 *
	 * Normal 5.1.0 to 5.2.0 upgrades should reach this table shape via VersionHandler's
	 * ALTER migration; this path is the last-resort salvage route when the live table is unusable.
	 *
	 * @return bool Always false so the scheduler marks the selected run as failed/deferred by caller rules.
	 */
	private function handle_lock_failure() {
		Logger::log( "Synchronization LOCK table missing or stale, triggering db schema check" );
		\Arlo_For_Wordpress::get_instance()->ensure_consistent_db_schema();

		return false;
	}

	/**
	 * Custom PHP error handler used during imports. Converts plugin-originating
	 * PHP errors into exceptions so they can be caught and logged by the import pipeline.
	 *
	 * @param int         $num     Error level.
	 * @param string      $str     Error message.
	 * @param string      $file    File where the error occurred.
	 * @param int         $line    Line number.
	 * @param mixed|null  $context Error context (deprecated in PHP 8).
	 */
	public function handle_import_error( $num, $str, $file, $line, $context = null ) {
		error_log( $str . ' in ' . $file . ' on line ' . $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging errors for debugging purposes.

		//pretty nasty, but need to know if our plugin throws the error or something else (like a cache plugin)
		//arlo- is in case $file would not include the path; just 'arlo' would catch all errors for hosted servers like vanguard.wpdemo.arlo.co where domain is used in the plugin file path
		if ( strpos( $file, 'arlo-' ) !== false || strpos( $file, 'arlowp' ) !== false ) {

			// specific error for file permission
			if ( strpos( $str, 'fopen(' ) === 0 ) {
				if ( strpos( $str, 'ermission denied' ) > 0 ) {
					Logger::log( "Missing write permission" . ( strpos( $str, "/import/" ) > 0 ? " on 'import' directory" : "" ), $this->import_id );
				}
			}

			throw new \Exception( $str ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	/**
	 * Runs one checkpointed import subtask under the import mutex.
	 *
	 * A successful subtask persists the next checkpoint, then either waits for
	 * the snapshot callback, self-kicks the scheduler, or completes a terminal
	 * delta no-op. Retryable exceptions are paused and deferred with backoff;
	 * exhausted retries, SchedulerException, PlatformAccessHttpException,
	 * SnapshotProcessException, and DeltaFatalException fail terminally. Post-switch Finish failures are
	 * recorded as cleanup debt rather than failed imports.
	 *
	 * @param object|null $task_state Decoded task state for a resumed import, or null to start fresh.
	 * @param bool        $force      Whether this is a manually forced sync.
	 * @return bool True on success or a deferred retry; false on terminal failure.
	 */
	private function run_locked_import( $task_state, $force = false ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Logging errors for debugging purposes.
		set_error_handler( [ $this, 'handle_import_error' ], E_ALL & ~E_USER_NOTICE & ~E_NOTICE & ~E_DEPRECATED );

		try {
			// Preflight: a worker that lost ownership must not read or advance the checkpoint.
			if ( ! $this->confirm_import_mutex_ownership() ) {
				Logger::log( 'Import paused because another process took over before progress could be saved. Another request may continue it.', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import mutex ownership was lost before task state was written [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; step=' . $this->current_task . ']', $this->import_id );
				return true;
			}

			if ( $this->scheduler->is_task_terminal( $this->task_id ) ) {
				return true;
			}

			// Resolve the resumed checkpoint into the top-level subtask to run now.
			if ( $this->set_state( $task_state ) === false ) {
				Logger::log( 'Import retry paused because another process took over. Another request may continue it.', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Retry state write was rejected [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; step=' . $this->current_task . '; reason=mutex_ownership_lost]', $this->import_id );
				return true;
			}

			// Complete a pipeline that had already finished when its checkpoint was resumed.
			if ( $this->is_finished ) {
				if ( ! $this->confirm_import_mutex_ownership() ) {
					Logger::log( 'Import paused because another process took over. Another request may continue it.', $this->import_id );
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import mutex ownership was lost before finish handling [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; step=' . $this->current_task . ']', $this->import_id );
					return true;
				}

				if ( $this->scheduler->is_task_terminal( $this->task_id ) ) {
					return true;
				}

				$this->importing_parts->delete_all_import_parts();

				if ( ! $this->scheduler->update_task_if_active( $this->task_id, 4, $this->get_terminal_success_task_status_text() ) ) {
					return true;
				}
				$this->log_terminal_success();

				$this->run_post_success_analysis_cleanup();

				// Run after the terminal transition so stale workers cannot trigger cleanup for lost work.
				Logger::execute_log_maintenance();
				$this->scheduler->clear_cron();

				return true;
			}

			// Execute one top-level subtask for this scheduler request.
			if ( isset( $this->import_tasks[ $this->current_task ] ) ) {
				Logger::log_diagnostic(
					Logger::DIAGNOSTIC_VERBOSITY_TRACE,
					function () {
						return 'Running import subtask; task=' . $this->current_task . '; task_num=' . ( $this->current_task_num + 1 ) . '/' . count( $this->import_tasks ) . '; iteration=' . $this->current_task_iteration . '; retry=' . $this->current_task_retry . '.';
					},
					$this->import_id
				);
				$this->run_import_task( $this->current_task );
			}

			// Undoes set_state()'s provisional increment above — this attempt succeeded, so it
			// must not count toward should_retry()'s ceiling or inflate the next backoff delay.
			--$this->current_task_retry;

			if ( ! $this->confirm_import_mutex_ownership() ) {
				Logger::log( 'Import paused because another process took over. Another request may continue it.', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import mutex ownership was lost after the step finished [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; step=' . $this->current_task . '; retry=' . $this->current_task_retry . ']', $this->import_id );
				return true;
			}

			if ( $this->scheduler->is_task_terminal( $this->task_id ) ) {
				return true;
			}

			// A matching delta manifest finishes without entering the full-shadow pipeline.
			if ( $this->is_terminal_delta_success() ) {
				$this->importing_parts->delete_all_import_parts();

				if ( ! $this->scheduler->update_task_if_active( $this->task_id, 4, $this->get_terminal_success_task_status_text() ) ) {
					return true;
				}
				$this->log_terminal_success();

				$this->run_post_success_analysis_cleanup();

				Logger::execute_log_maintenance();
				$this->scheduler->clear_cron();

				return true;
			}

			// Persist the next durable checkpoint before making the task eligible for another kick.
			$checkpoint_state = $this->get_state();
			if ( ! $this->scheduler->update_task_data_if_mutex_owned( $this->task_id, $checkpoint_state, $this->get_mutex() ) ) {
				Logger::log( 'Import paused because progress could not be saved. Another request may continue it.', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Checkpoint write was rejected [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; step=' . $this->current_task . '; reason=mutex_ownership_lost]', $this->import_id );
				return true;
			}

			// Pause the task only after its checkpoint is durable.
			if ( ! $this->scheduler->update_task_if_active( $this->task_id, 1, $this->get_paused_task_status_text() ) ) {
				return true;
			}

			// ImportRequest waits for the platform callback; later subtasks advance immediately.
			if ( $this->current_task_num > 0 ) {
				// Subtask 0 (ImportRequest) is excluded: it marks itself finished once the
				// platform acknowledges the snapshot request (returns a RequestID), without
				// waiting for the actual snapshot data — progression to the next subtask
				// (Download) is driven by the platform's async callback into
				// SnapshotHandler::process(), not by this self-kick loop. Self-kicking here
				// would just waste a request racing a callback that hasn't arrived yet.
				$this->kick_off_scheduler();
			}

			return true;
		} catch ( \Exception $e ) {
			// Classify a subtask failure as retryable, post-switch cleanup debt, or terminal.
			if ( ! $this->confirm_import_mutex_ownership() ) {
				Logger::log( 'Import stopped handling an error because another process took over. Another request may report the final result.', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import mutex ownership was lost while handling an exception [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; step=' . $this->current_task . '; exception=' . get_class( $e ) . ']', $this->import_id );
				return true;
			}

			if ( $this->scheduler->is_task_terminal( $this->task_id ) ) {
				return true;
			}

			$state              = $this->get_state();
			$post_switch_finish = $this->is_post_switch_finish_state( $state );
			Logger::log_diagnostic(
				Logger::DIAGNOSTIC_VERBOSITY_TRACE,
				function () use ( $e, $state ) {
					return 'Import subtask exception [exception=' . get_class( $e ) . '; task=' . $this->current_task . '; retry_attempt=' . $this->get_current_retry_attempt( $state ) . '; state=[' . $this->format_task_state_for_diagnostic_log( $state ) . ']; message=' . $e->getMessage() . ']';
				},
				$this->import_id
			);
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import subtask exception [task=' . $this->current_task . '; retry_attempt=' . $this->get_current_retry_attempt( $state ) . '; exception=' . get_class( $e ) . '; message=' . $e->getMessage() . ']', $this->import_id );
			$callback_wait_expired = null;
			if ( $e instanceof \ArloTraining\CallbackTimeoutException ) {
				// A missing callback is bounded by time since the request, not by the retry
				// ceiling. Fall back to the ceiling only when the request time is unknown.
				$callback_wait_expired = $this->is_snapshot_callback_wait_expired();
				$can_retry             = null === $callback_wait_expired ? $this->should_retry( $state ) : ! $callback_wait_expired;
			} else {
				$can_retry = $this->should_retry( $state );
			}
			if ( $can_retry
				&& ! ( $e instanceof \ArloTraining\SchedulerException )
				&& ! ( $e instanceof \ArloTraining\PlatformAccessHttpException )
				&& ! ( $e instanceof \ArloTraining\SnapshotProcessException )
				&& ! ( $e instanceof \ArloTraining\DeltaFatalException ) ) {
				// pause the task and defer the retry with an escalating backoff so a
				// persistently failing subtask cannot re-fire in a tight loop. For a
				// missing platform callback, each wake is only a re-check inside the
				// callback wait limit; the callback itself also wakes the importer.
				if ( ! $this->scheduler->update_task_if_active( $this->task_id, 1 ) ) {
					return true;
				}
				$retry_attempt = $this->get_current_retry_attempt( $state );
				if ( $e instanceof \ArloTraining\CallbackTimeoutException && null !== $callback_wait_expired ) {
					$retry_attempt = $this->record_callback_wait_attempt();
					if ( null === $retry_attempt ) {
						Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Callback wait write was rejected [task_id=' . $this->task_id . '; import_id=' . $this->import_id . '; reason=mutex_ownership_lost]', $this->import_id );
						return true;
					}
				}
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Scheduling import step retry after a delay [step=' . $this->current_task . '; attempt=' . $retry_attempt . ']', $this->import_id );
				if ( $e instanceof \ArloTraining\CallbackTimeoutException && 1 === $retry_attempt ) {
					// One permanent row when the wait begins; later attempts stay in the diagnostic log above.
					Logger::log( $e->getMessage() . '; waiting and retrying', $this->import_id );
				}
				$this->scheduler->kick_off_scheduler_with_backoff( $retry_attempt );
				if ( ! ( $e instanceof \ArloTraining\CallbackTimeoutException ) ) {
					// Non-platform failure in retry path: platform was reachable, reset health counter.
					update_option( 'arlo_platform_access_failure_count', 0 );
					delete_option( 'arlo_platform_access_first_failure_at' );
					delete_option( 'arlo_platform_access_last_failure_at' );
				}
				// A missing callback is left uncounted here but its accumulated health state is
				// preserved: this attempt has not concluded yet, so an intermediate retry must
				// not erase failure state built up by earlier failed attempts.
				return true;
			} elseif ( $post_switch_finish ) {
				if ( ! $this->scheduler->update_task_if_active( $this->task_id, 4, 'Import processing complete; cleanup pending' ) ) {
					return true;
				}

				$this->run_post_success_analysis_cleanup();

				// The live switch already happened, so terminal cleanup debt must not become a failed import.
				Logger::log( $e->getMessage(), $this->import_id );
				Logger::log( 'Import processing complete; cleanup pending', $this->import_id, null, true );
				update_option( 'arlo_import_cleanup_pending', $this->get_current_import_id( true ) );

				Logger::execute_log_maintenance();
				$this->scheduler->clear_cron();
				return true;
			} else {
				Logger::log( $e->getMessage(), $this->import_id );
				Logger::log( 'Import task failed', $this->import_id );
				if ( ! $this->scheduler->update_task_if_active( $this->task_id, 3, 'Import task failed' ) ) {
					return true;
				}
				// Run after the terminal transition so stale workers cannot trigger cleanup for lost work.
				Logger::execute_log_maintenance();
				$this->scheduler->clear_cron();
				if ( $e instanceof \ArloTraining\PlatformAccessHttpException
					|| $e instanceof \ArloTraining\CallbackTimeoutException ) {
					if ( ! $force ) {
						// Scheduled sync platform failure (unreachable platform or a callback that
						// never arrived across every retry): count toward the auto-disable threshold.
						$this->handle_platform_access_failure( $e );
					}
					// Forced sync platform failure: leave health counters intact so accumulated
					// failure state from scheduled syncs is not erased by an admin retry.
				} elseif ( ! ( $e instanceof \ArloTraining\DeltaFatalException ) ) {
					// Non-platform failure: platform was reachable, reset health counter.
					update_option( 'arlo_platform_access_failure_count', 0 );
					delete_option( 'arlo_platform_access_first_failure_at' );
					delete_option( 'arlo_platform_access_last_failure_at' );
				}
				return true;
			}
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Returns whether the current failure belongs to Finish after the live id switched.
	 *
	 * @param array $state Importer checkpoint state for the current attempt.
	 * @return bool True when the public live id has moved to this import.
	 */
	private function is_post_switch_finish_state( $state ): bool {
		if ( $this->current_task !== 'Finish' ) {
			return false;
		}

		if ( ! empty( $state['live_switch_done'] ) ) {
			return true;
		}

		if ( ! empty( $state['subtask_state'] ) && is_array( $state['subtask_state'] ) && ! empty( $state['subtask_state']['live_switch_done'] ) ) {
			return true;
		}

		return (string) $this->get_current_import_id( true ) === (string) $this->import_id;
	}

	private function should_retry( $state ) {
		if ( ( is_null( $state['subtask_state'] ) && $state['current_subtask_retry'] >= self::MAX_RETRY_ATTEMPT ) || ( ! is_null( $state['subtask_state'] ) && $state['subtask_state']['current_subtask_retry'] >= self::MAX_RETRY_ATTEMPT ) ) {
			$subtask_desc = $this->get_subtask_state_desc();
			Logger::log( "Maximum retry attempt reached for '" . $this->current_task_desc . $subtask_desc . "'", $this->import_id );
			return false;
		}

		return true;
	}

	/**
	 * Returns the consecutive retry attempt count for the current failure,
	 * selecting the same counter that `should_retry()` gates on: the nested
	 * subtask counter when a subtask maintains its own state (e.g. fragment
	 * processing), otherwise the top-level import counter.
	 *
	 * Used to drive the scheduler's retry backoff so the spacing between
	 * retries matches the attempt that `should_retry()` is actually counting.
	 *
	 * @param array $state Import state array as produced by `get_state()`.
	 * @return int The 1-based retry attempt number for the current failure, or 0 if no
	 *             retry count is present in `$state` (e.g. called outside a retry context).
	 */
	private function get_current_retry_attempt( $state ) {
		if ( ! is_null( $state['subtask_state'] ) && isset( $state['subtask_state']['current_subtask_retry'] ) ) {
			return (int) $state['subtask_state']['current_subtask_retry'];
		}

		return (int) ( $state['current_subtask_retry'] ?? 0 );
	}

	/**
	 * Record a platform access failure and disable imports if both sustained-failure
	 * thresholds are exceeded.
	 *
	 * Increments the failure counter and, once IMPORT_FAILURE_MAX_COUNT failures have
	 * occurred within a window of at least IMPORT_FAILURE_MIN_DURATION_HOURS, sets
	 * arlo_import_connection_health_disabled and stores a normalised human-readable
	 * reason in arlo_import_disabled_message. Is a no-op when
	 * import_connection_healthchecks_enabled is off in settings.
	 *
	 * Also records arlo_platform_access_last_failure_at on every call — the
	 * timestamp used to escalate the minimum interval before another import
	 * attempt is allowed while this failure streak continues.
	 *
	 * Accepts any exception representing a failed attempt to reach or complete a
	 * sync against the platform — an unreachable-platform HTTP error, or a full
	 * attempt that exhausted its retries because the platform's snapshot callback
	 * never arrived. Only the exception message is read.
	 *
	 * Public to allow direct invocation in unit tests without reflection.
	 *
	 * @param \Exception $e The caught exception whose message is normalised and
	 *        stored as the disable reason.
	 *
	 * @since 5.1.0
	 */
	public function handle_platform_access_failure( \Exception $e ) {
		$arlo_settings = get_option( 'arlo_settings', [] );
		if ( ( $arlo_settings['import_connection_healthchecks_enabled'] ?? '1' ) !== '1' ) {
			return;
		}

		$now = \ArloTraining\Utilities::get_now_utc();

		// Set first failure timestamp if not already set
		if ( empty( get_option( 'arlo_platform_access_first_failure_at', '' ) ) ) {
			update_option( 'arlo_platform_access_first_failure_at', $now->format( 'Y-m-d H:i:s' ) );
		}

		// Record this failure's timestamp — read by should_importer_run() to escalate
		// the minimum interval before the next attempt is allowed to start.
		update_option( 'arlo_platform_access_last_failure_at', $now->format( 'Y-m-d H:i:s' ) );

		// Increment failure count
		$count = intval( get_option( 'arlo_platform_access_failure_count', 0 ) ) + 1;
		update_option( 'arlo_platform_access_failure_count', $count );

		// Check if both thresholds are exceeded
		if ( $count >= self::IMPORT_FAILURE_MAX_COUNT ) {
			$first_failure_at = get_option( 'arlo_platform_access_first_failure_at', '' );
			if ( ! empty( $first_failure_at ) ) {
				try {
					$first = new \DateTime( $first_failure_at, new \DateTimeZone( 'UTC' ) );
				} catch ( \Exception $date_ex ) {
					// Stored timestamp is corrupt — reset counter and start a clean window from the current failure
					update_option( 'arlo_platform_access_failure_count', 1 );
					update_option( 'arlo_platform_access_first_failure_at', $now->format( 'Y-m-d H:i:s' ) );
					return;
				}
				$hours_elapsed = ( $now->getTimestamp() - $first->getTimestamp() ) / 3600;

				if ( $hours_elapsed >= self::IMPORT_FAILURE_MIN_DURATION_HOURS ) {
					$platform_host = ! empty( $arlo_settings['platform_name'] ) ? $arlo_settings['platform_name'] . '.arlo.co' : 'the Arlo platform';

					$raw_message = $e->getMessage();

					// Strip a leading "hostname: " prefix if present (e.g. messages thrown by
					// Download::get_remote_data() are prefixed with the CDN hostname).
					// This lets the patterns below match regardless of where the exception originated.
					$normalised = preg_replace( '/^[^\s:]+:\s+/', '', $raw_message );

					if ( preg_match( '/^.+ returned \d+ .+$/', $normalised ) ) {
						// HTTP error — hostname + status code already embedded; use as-is.
						$stored_message = $normalised;
					} elseif ( preg_match( '/^cURL error 28:/i', $normalised ) ) {
						// cURL timeout — omit the "after X milliseconds" suffix.
						$stored_message = $platform_host . ': Connection timed out';
					} elseif ( preg_match( '/^cURL error \d+: (.+)$/i', $normalised, $m ) ) {
						// Other cURL errors (SSL, DNS, etc.) — use the human-readable description.
						$stored_message = $platform_host . ': ' . $m[1];
					} else {
						// Use the normalised form to avoid double-prefixing if the message
						// originated from Download and carried a CDN hostname prefix.
						$stored_message = $platform_host . ': ' . $normalised;
					}

					update_option( 'arlo_import_connection_health_disabled', '1' );
					update_option( 'arlo_import_disabled_message', sanitize_text_field( $stored_message ) );
					update_option( 'arlo_import_disabled_since', $now->format( 'Y-m-d H:i:s' ) );
					update_option( 'arlo_platform_access_failure_count', 0 );
					delete_option( 'arlo_platform_access_first_failure_at' );
					delete_option( 'arlo_platform_access_last_failure_at' );

					$minutes_elapsed = max( 1, (int) round( $hours_elapsed * 60 ) );
					Logger::log(
						'Automatic synchronization was disabled (' . $count . ' ' . ( $count === 1 ? 'failure' : 'failures' ) . ' over ' . $minutes_elapsed . ' ' . ( $minutes_elapsed === 1 ? 'minute' : 'minutes' ) . '). Reason: ' . $stored_message,
						$this->import_id
					);
				}
			}
		}
	}

	private function run_import_task( $import_task ) {
		global $wpdb;
		$this->data_json = null;
		if ( 'ProcessFragment' === $this->current_task ) {
			$this->get_data_json();
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot image manifest decoded [fragments=' . $this->get_total_fragments() . ']', $this->import_id );
		}

		$this->environment->start_time = time(); // Set start time of current process.

		$class_name = "ArloTraining\Importer\\" . $import_task;

		$this->current_task_class          = new $class_name( $this, $this->message_handler, ( ! empty( $this->data_json->$import_task ) ? $this->data_json->$import_task : null ), $this->current_task_iteration, $this->api_client, $this->scheduler, $this->importing_parts );
		$this->current_task_class->task_id = $this->task_id;
		$this->current_task_class->set_legacy_region_id_handling( $this->is_legacy_region_id_handling_session() );
		if ( 'Download' === $this->current_task && $this->snapshot_part_provider instanceof SnapshotPartProvider ) {
			$this->current_task_class->set_snapshot_part_provider( $this->snapshot_part_provider );
		}

		//we need to do some special setup for different tasks
		switch ( $this->current_task ) {
			case 'ImportRequest':
				$this->current_task_class->fragment_size = $this->fragment_size;
				break;
			case 'Download':
				// This is a point-in-time check, not a wait: whatever the platform has posted
				// back by the moment this subtask happens to run is all that's considered —
				// there is no polling or blocking here for the callback to arrive.
				$import = $this->get_import_entry( $this->import_id, null, 1 );

				if ( ! is_null( $import ) ) {
					if ( ! empty( $import->callback_json ) ) {
						$callback_json = json_decode( $import->callback_json );
						Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot callback received [' . $this->format_snapshot_callback_diagnostic_fields( $callback_json ) . ']', $this->import_id );

						if ( json_last_error() != JSON_ERROR_NONE ) {
							error_log( "JSON Decode error: " . json_last_error_msg() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging errors for debugging purposes.
							Logger::log_error( "JSON Decode error: " . json_last_error_msg(), $this->import_id );
						}

						if ( ! empty( $callback_json->SnapshotUri ) ) {
							$this->current_task_class->uri              = $callback_json->SnapshotUri;
							$this->current_task_class->import_part      = "image";
							$this->current_task_class->import_iteration = null;
							$this->current_task_class->response_json    = json_decode( $import->response_json );
							$this->configure_snapshot_manifest_admission( $callback_json, $this->current_task_class->response_json );
							Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot image download prepared from Arlo callback [snapshot_uri=' . $callback_json->SnapshotUri . ']', $this->import_id );
						} elseif ( ! empty( $callback_json->Error ) ) {
							$error_code = ( isset( $callback_json->Error->Code ) && is_scalar( $callback_json->Error->Code ) ) ? (string) $callback_json->Error->Code : 'unknown';
							Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Arlo snapshot callback returned an error [code=' . $error_code . ']', $this->import_id );
							Logger::log_error( $callback_json->Error->Code . ': ' . $callback_json->Error->Message, $this->import_id );
						} elseif ( ! empty( $callback_json->__jwe__ ) ) {
							Logger::log_error( 'Snapshot callback payload is not ready for Download; callback_json contains encrypted callback data but no SnapshotUri or Error.', $this->import_id, null, false, \ArloTraining\SnapshotProcessException::class );
						} else {
							Logger::log_error( 'Snapshot callback payload is missing SnapshotUri and Error.', $this->import_id, null, false, \ArloTraining\SnapshotProcessException::class );
						}
					} else {
						// A callback that hasn't arrived by this check is treated as still in
						// flight until IMPORT_CALLBACK_WAIT_TIMEOUT_SECONDS has passed since the
						// request; run_locked_import() re-checks on the retry backoff until then and
						// fails the import after it. A callback arriving later is ignored by callback().
						// Nothing is logged here: run_locked_import() writes one row when the wait
						// begins and one at the terminal failure, so the permanent log does not grow
						// with every re-check.
						throw new \ArloTraining\CallbackTimeoutException( $this->get_missing_callback_message( $import ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by run_locked_import() and written to the Arlo log table via Logger; it is escaped with esc_html() at admin render time.
					}
				} else {
					Logger::log_error( 'Couldn\'t retrieve the import from database', $this->import_id );
				}

				break;
			case 'ProcessFragment':
				$this->current_task_class->set_state( $this->state->subtask_state );

				if ( ! empty( $this->data_json->FullImageFragments->Elements[ $this->current_task_iteration ] ) ) {
					$this->current_task_desc      .= ' ' . ( $this->current_task_iteration + 1 ) . '/' . count( $this->data_json->FullImageFragments->Elements );
					$this->current_task_class->uri = $this->data_json->FullImageFragments->Elements[ $this->current_task_iteration ]->Uri;
				} else {
					$this->current_task_class->is_finished = true;
				}
				break;
			case 'DeltaRunCoordinator':
				$this->current_task_class->set_incremental_run_id( isset( $this->state->incremental_run_id ) ? (int) $this->state->incremental_run_id : 0 );
				$this->current_task_class->set_test_fault_selector( $this->delta_test_fault_selector );
				if ( $this->delta_run_parameters instanceof DeltaRunParameters ) {
					$this->current_task_class->set_parameters( $this->delta_run_parameters );
				}
				break;
			case 'Finish':
				$this->current_task_class->set_incremental_baseline_enabled( $this->supports_delta_infrastructure() );
				$this->current_task_class->set_incremental_baseline_configuration_signature( $this->compute_current_baseline_configuration_signature() );
				$this->current_task_class->set_state( $this->state->subtask_state );
				break;
		}

		if ( ! $this->current_task_class->is_finished && ! $this->current_task_class->iteration_finished ) {
			$task_desc = $this->get_progress_task_desc();

			// A percentage is only meaningful while processing a multi-fragment full
			// snapshot. Atomic steps and one-fragment imports otherwise jump straight
			// to completion.
			if ( $this->current_task === 'ImportRequest' ) {
				$progress_desc = $this->get_import_request_status_text();
			} elseif ( $this->current_task === 'DeltaRunCoordinator' ) {
				$progress_desc = 'Import is running: Checking snapshot for changes.';
			} elseif ( $this->should_display_import_progress_percent() ) {
				$progress_desc = 'Import is running (' . $this->get_import_progress_percent() . '% complete): ' . $task_desc;
			} else {
				$progress_desc = 'Import is running: ' . $task_desc;
			}
			if ( ! $this->scheduler->update_task_if_active( $this->task_id, 2, $progress_desc ) ) {
				return;
			}

			// Only the start is logged, not a matching "ended" line: successful completion of
			// this subtask is already implied by whatever log line comes next (the next
			// subtask's "started" line, or one of the terminal "Import task finished"/"Import task
			// failed" messages). A "started" line that is never followed by anything else pinpoints
			// exactly which subtask was executing if the request crashes, times out, or is killed
			// mid-run — that forensic value is lost if this is replaced by (or paired with) an
			// "ended" line instead, since a crash produces no "ended" line either way.
			Logger::log( $progress_desc, $this->import_id );
			$this->current_task_class->run();
			if ( 'Download' === $this->current_task && $this->current_task_class->is_finished ) {
				$this->start_admitted_delta_run( $this->current_task_class );
			}
		}
	}

	/**
	 * Creates the durable coordinator checkpoint only for a clean compatible baseline proven by Download.
	 *
	 * @param Download $download Completed top-level image download.
	 * @return void
	 */
	private function start_admitted_delta_run( Download $download ): void {
		if ( ! $this->is_delta_schema_session() ) {
			return;
		}
		// The sealed session mode is the one readiness admission; later external DDL is an ordinary fatal database failure.

		$context = $download->consume_validated_delta_context();
		if ( ! is_array( $context ) || ! isset( $context['manifest'], $context['manifest_request'], $context['image_content'], $context['configuration_signature'] ) || ! $context['manifest'] instanceof SnapshotManifest || ! $context['manifest_request'] instanceof SnapshotManifestRequest || ! is_string( $context['image_content'] ) || ! is_string( $context['configuration_signature'] ) ) {
			return;
		}

		$repository = $this->get_incremental_run_repository();
		try {
			$baseline = $repository->get_current_clean_baseline( $context['manifest']->schema_level, $context['configuration_signature'] );
		} catch ( \Throwable $exception ) {
			Logger::log( 'Unable to apply changes. Processing full snapshot.', $this->import_id );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check could not read the current baseline; processing the full snapshot [stage=baseline_lookup; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
			$download->stage_full_shadow_candidate( $context );
			return;
		}
		$active_id = $this->get_current_import_id( true );
		if ( ! is_object( $baseline ) || empty( $active_id ) ) {
			Logger::log( 'Processing full snapshot.', $this->import_id );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check found no compatible current baseline; processing the full snapshot [baseline_found=' . ( is_object( $baseline ) ? 'true' : 'false' ) . '; active_import_found=' . ( empty( $active_id ) ? 'false' : 'true' ) . ']', $this->import_id );
			$download->stage_full_shadow_candidate( $context );
			return;
		}

		// Matching CensusTag and count need only a durable run; the coordinator rechecks them under lock without candidate or manifest staging.
		$census_tag_matches        = is_string( $baseline->census_tag ?? null ) && hash_equals( $baseline->census_tag, $context['manifest']->census_tag );
		$manifest_matches_baseline = (int) $baseline->projection_count === count( $context['manifest']->entries ) && $census_tag_matches;
		if ( ! $manifest_matches_baseline && ! $download->stage_full_shadow_candidate( $context ) ) {
			Logger::log( 'Unable to apply changes. Processing full snapshot.', $this->import_id );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot changes could not stage a full-shadow candidate; bypassing the change check [stage=full_shadow_candidate; result=false]', $this->import_id );
			return;
		}

		try {
			$run_id = $repository->create_run( (int) $this->import_id, (int) $active_id, $context['manifest_request'], $context['manifest'], $context['configuration_signature'], $context['image_content'], (int) $baseline->current_baseline_generation_id );
		} catch ( \Throwable $exception ) {
			Logger::log( 'Unable to apply changes. Processing full snapshot.', $this->import_id );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check could not create its durable run; processing the full snapshot [stage=create_run; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
			return;
		}

		if ( ! $manifest_matches_baseline ) {
			try {
				$repository->stage_target_manifest( $run_id, $context['manifest'] );
			} catch ( \Throwable $exception ) {
				Logger::log( 'Unable to apply changes. Processing full snapshot.', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest could not be prepared for comparison; processing the full snapshot [stage=stage_target_manifest; baseline_entries=' . (int) $baseline->projection_count . '; snapshot_entries=' . count( $context['manifest']->entries ) . '; census_tag_matches=' . ( $census_tag_matches ? 'true' : 'false' ) . '; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
				try {
					$repository->delete_staged_target_manifest( $run_id );
					$repository->mark_run_full_shadow_with_reason( $run_id, IncrementalRunRepository::REASON_MANIFEST_STAGING_INCOMPLETE );
				} catch ( \Throwable $fallback_exception ) {
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Full-snapshot fallback could not persist its recovery state [stage=persist_recovery; exception=' . get_class( $fallback_exception ) . '; message=' . $fallback_exception->getMessage() . ']', (int) $this->import_id );
				}
				return;
			}

			Logger::log( 'Import is running: Checking snapshot for changes', $this->import_id );
			Logger::log( 'Snapshot changes detected.', $this->import_id );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check found manifest differences; processing the full snapshot [baseline_entries=' . (int) $baseline->projection_count . '; snapshot_entries=' . count( $context['manifest']->entries ) . '; census_tag_matches=' . ( $census_tag_matches ? 'true' : 'false' ) . '; baseline_census_tag=' . $this->format_census_tag_for_diagnostic( $baseline->census_tag ?? null ) . '; snapshot_census_tag=' . $this->format_census_tag_for_diagnostic( $context['manifest']->census_tag ) . ']', $this->import_id );
			Logger::log( 'Processing full snapshot.', $this->import_id );
		}

		$this->incremental_run_id = $run_id;
		Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, ( $manifest_matches_baseline ? 'Matching snapshot admitted for terminal no-op checking' : 'Snapshot manifest admitted for change checking' ) . ' [run_id=' . $run_id . '; schema_level=' . (int) $context['manifest']->schema_level . '; entries=' . count( $context['manifest']->entries ) . ']', $this->import_id );
	}

	/**
	 * Encodes a stored raw CensusTag for text-only diagnostic output.
	 *
	 * @param mixed $tag Raw 32-byte tag, or null when no baseline tag exists.
	 * @return string Canonical Base64 tag or the null sentinel.
	 */
	private function format_census_tag_for_diagnostic( $tag ): string {
		if ( ! is_string( $tag ) ) {
			return 'null';
		}

		return base64_encode( $tag ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Converts a fixed-length binary CensusTag to its canonical diagnostic representation.
	}

	/**
	 * Returns whether the live schema supports the delta writer transaction contract.
	 *
	 * @return bool True only when every delta write and baseline-promotion table is transaction-capable.
	 */
	protected function supports_delta_infrastructure(): bool {
		return \Arlo_For_Wordpress::get_instance()->get_schema_manager()->supports_delta_infrastructure();
	}

	/**
	 * Computes the baseline configuration signature from the current plugin settings.
	 *
	 * @return string|null Raw SHA-256 signature for the current settings.
	 */
	private function compute_current_baseline_configuration_signature(): ?string {
		$settings      = get_option( 'arlo_settings', [] );
		$platform_name = is_array( $settings ) && isset( $settings['platform_name'] ) && is_scalar( $settings['platform_name'] ) ? strtolower( trim( (string) $settings['platform_name'] ) ) : '';
		if ( '' === $platform_name || ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $platform_name ) || strlen( $platform_name ) > 63 ) {
			return null;
		}

		try {
			return ImportRequest::compute_baseline_configuration_signature_for_platform( $platform_name . \ArloTraining\API\Transports\Transport::PLATFORM_DOMAIN );
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Could not compute baseline configuration signature for current settings [exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
			return null;
		}
	}

	/**
	 * @return bool Whether the current step completed a terminal delta no-op.
	 */
	private function is_terminal_delta_success(): bool {
		return $this->current_task_class instanceof DeltaRunCoordinator
			&& $this->current_task_class->is_finished
			&& $this->current_task_class->get_outcome() instanceof DeltaCoordinatorOutcome
			&& DeltaCoordinatorOutcome::TERMINAL_SUCCESS === $this->current_task_class->get_outcome()->get_route();
	}

	/**
	 * Returns the completion detail for the completed full snapshot.
	 *
	 * @return string Human-readable full-import entity counts.
	 */
	public function get_import_completion_detail(): string {
		if ( is_string( $this->import_completion_detail ) ) {
			return $this->import_completion_detail;
		}

		global $wpdb;
		$entity_tables = [
			'templates'         => 'arlo_eventtemplates',
			'events'            => 'arlo_events',
			'online activities' => 'arlo_onlineactivities',
			'presenters'        => 'arlo_presenters',
			'venues'            => 'arlo_venues',
		];
		$counts        = [];
		foreach ( $entity_tables as $label => $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Completion counts read the just-written plugin-owned dataset once, before it becomes live.
			$counts[] = number_format( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE import_id = %d', $wpdb->prefix . $table, $this->import_id ) ) ) . ' ' . $label;
		}

		$this->import_completion_detail = implode( ', ', $counts );
		return $this->import_completion_detail;
	}

	/**
	 * Builds the terminal scheduler status from the delta outcome or full-import counts.
	 *
	 * @return string Terminal scheduler status text for a successful import.
	 */
	private function get_terminal_success_task_status_text(): string {
		if ( $this->current_task_class instanceof DeltaRunCoordinator ) {
			$outcome = $this->current_task_class->get_outcome();
			if ( $outcome instanceof DeltaCoordinatorOutcome && $outcome->get_completion_detail() !== '' ) {
				return 'Import task finished (' . $outcome->get_completion_detail() . ')';
			}
		}

		if ( $this->current_task_class instanceof Finish || ( is_object( $this->state ) && isset( $this->state->finished_subtask ) && 'Finish' === $this->state->finished_subtask ) ) {
			return 'Import task finished (' . $this->get_import_completion_detail() . ')';
		}

		return 'Import task finished';
	}

	/**
	 * Logs the user-visible completion message after the scheduler accepted the terminal state.
	 *
	 * @return void
	 */
	private function log_terminal_success(): void {
		Logger::log( str_replace( 'Import task finished', 'Import complete', $this->get_terminal_success_task_status_text() ), $this->import_id, null, true );
	}

	/**
	 * Configures stateless manifest validation for an admitted full-delta session.
	 *
	 * @param object $callback_json Decrypted callback response.
	 * @param mixed  $response_json Original snapshot request response.
	 * @return void
	 */
	private function configure_snapshot_manifest_admission( object $callback_json, $response_json ): void {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are authenticated external platform callback field names.
		if ( ! $this->is_delta_schema_session() ) {
			return;
		}

		if ( ! isset( $callback_json->ManifestUri ) || ( is_scalar( $callback_json->ManifestUri ) && '' === trim( (string) $callback_json->ManifestUri ) ) ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Snapshot manifest was not offered; continuing ordinary full-shadow import', $this->import_id );
			return;
		}

		try {
			$this->assert_snapshot_manifest_advertisement_shape( $callback_json, $response_json );
			$request  = new SnapshotManifestRequest(
				(string) $callback_json->SnapshotID,
				(string) $callback_json->SnapshotUri,
				(string) $callback_json->ManifestUri,
				(string) $callback_json->ManifestSha256,
				$callback_json->ManifestBytes,
				(string) $response_json->ArloMetadata->PlatformHostname,
				(int) $this->import_id
			);
			$provider = $this->snapshot_manifest_provider ?? new RemoteHttpSnapshotManifestProvider();
			$this->current_task_class->set_snapshot_manifest_admission( $provider, $request );
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest advertisement rejected; continuing ordinary full-shadow import [stage=advertisement_shape; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Whether this session froze the legacy five-character region ID handling when it started.
	 *
	 * Only the persisted task state counts; a session that predates the setting reads as off.
	 *
	 * @return bool
	 */
	public function is_legacy_region_id_handling_session(): bool {
		return is_object( $this->state ) && ! empty( $this->state->legacy_region_id_handling );
	}

	/**
	 * @return bool Whether this import originally requested the delta-capable schema.
	 */
	private function is_delta_schema_session(): bool {
		return is_object( $this->state )
			&& \Arlo_For_Wordpress::IMPORTER_MODE_FULL_DELTA === ( $this->state->importer_mode ?? null )
			&& SnapshotManifest::SCHEMA_LEVEL_DELTA === (int) ( $this->state->schema_level ?? 0 );
	}

	/**
	 * @return IncrementalRunRepository Delta admission and coordinator persistence boundary.
	 */
	protected function get_incremental_run_repository(): IncrementalRunRepository {
		return new IncrementalRunRepository();
	}

	/**
	 * Best-effort disposal of terminal delta-analysis workspace after a successful task transition.
	 *
	 * @return void
	 */
	private function run_post_success_analysis_cleanup(): void {
		if ( ! $this->extend_import_mutex_ownership() ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Incremental analysis cleanup skipped [reason=mutex_ownership_unavailable]', $this->import_id );
			return;
		}

		if ( ! $this->supports_delta_infrastructure() ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Incremental analysis cleanup skipped [reason=nontransactional_infrastructure]', $this->import_id );
			return;
		}

		try {
			$deleted = $this->get_incremental_run_repository()->purge_all_analysis( $this->get_mutex()->get_token() );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Incremental analysis cleanup completed [manifest_rows=' . (int) $deleted['manifest_rows'] . '; run_rows=' . (int) $deleted['run_rows'] . ']', $this->import_id );
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Incremental analysis cleanup failed [exception=' . get_class( $exception ) . ']', $this->import_id );
		}
	}

	/**
	 * Short task description used in the "Import is running" status/log line.
	 *
	 * For `ProcessFragment`, this reports which fragment is currently being
	 * worked on (`current_task_iteration`) rather than the generic top-level
	 * "Process fragments" description — the fragment number is what's
	 * actually informative here. The internal step order (Download,
	 * TimeZones, Presenters, Venues, ...) is not numbered, since 1-9 doesn't
	 * mean anything to a reader without also knowing the full step list.
	 */
	private function get_progress_task_desc() {
		if ( $this->current_task === 'ProcessFragment' && ! is_null( $this->current_task_class ) ) {
			$subtask_state = $this->current_task_class->get_state();

			if ( is_array( $subtask_state ) ) {
				$fragment_num         = $this->current_task_iteration + 1;
				$current_subtask      = $subtask_state['current_subtask'] ?? '';
				$current_subtask_desc = $subtask_state['current_subtask_desc'] ?? '';

				if ( $current_subtask === 'Download' ) {
					return 'Downloading fragment #' . $fragment_num . ' (of ' . $this->get_total_fragments() . ')';
				}

				return 'Processing fragment #' . $fragment_num . ( $current_subtask_desc !== '' ? ': ' . $current_subtask_desc : '' );
			}
		}

		return $this->current_task_desc;
	}

	/**
	 * Total number of fragments in the current import, per the fetched
	 * snapshot manifest. Falls back to 1 if that data isn't available yet
	 * (e.g. before `Download` has populated `data_json`).
	 */
	private function get_total_fragments() {
		if ( empty( $this->data_json )
			|| empty( $this->data_json->FullImageFragments )
			|| empty( $this->data_json->FullImageFragments->Elements )
			|| ! is_array( $this->data_json->FullImageFragments->Elements ) ) {
			return 1;
		}

		return count( $this->data_json->FullImageFragments->Elements );
	}

	/**
	 * @return bool Whether the current task has enough durable work for an overall percentage to be useful.
	 */
	private function should_display_import_progress_percent(): bool {
		return 'ProcessFragment' === $this->current_task && $this->get_total_fragments() > 1;
	}

	private function get_subtask_state_desc() {
		$subtask_desc = '';
		if ( ! is_null( $this->current_task_class ) ) {
			$subtask_state = $this->current_task_class->get_state();

			if ( ! is_null( $subtask_state ) ) {
				$subtask_desc = ': ' . ( $subtask_state['current_subtask_num'] + 1 ) . '/' . count( $this->current_task_class->import_tasks ) . ' ' . $subtask_state['current_subtask_desc'];
			}
		}

		return $subtask_desc;
	}

	/**
	 * Overall import progress as a whole-number percentage (0-100).
	 *
	 * Computed from `IMPORT_TASK_WEIGHTS`: every top-level subtask before the
	 * current one contributes its full weight; the current subtask contributes
	 * its weight multiplied by how far through its own iteration it has
	 * reached (see `get_current_task_percent()`). This is a single overall
	 * figure across the whole import, not the separate per-subtask/per-fragment
	 * fraction counters shown alongside it in the log/status text.
	 *
	 * @since   5.2.0
	 *
	 * @return  int  0-100.
	 */
	public function get_import_progress_percent() {
		$completed_weight = 0;
		foreach ( array_keys( $this->import_tasks ) as $task_key ) {
			if ( $task_key === $this->current_task ) {
				break;
			}
			$completed_weight += self::IMPORT_TASK_WEIGHTS[ $task_key ] ?? 0;
		}

		$current_weight = self::IMPORT_TASK_WEIGHTS[ $this->current_task ] ?? 0;

		return (int) min( 100, round( $completed_weight + ( $current_weight * $this->get_current_task_percent() / 100.0 ) ) );
	}

	/**
	 * Percentage (0-100) of the *current* top-level subtask that has been
	 * completed so far, used by `get_import_progress_percent()` to weight the
	 * in-progress subtask rather than counting it as either 0% or 100%.
	 *
	 * Only `ProcessFragment` currently exposes enough nested state (which
	 * fragment out of how many, and which of its own fixed sub-steps within
	 * that fragment) to compute a real percentage; every other subtask is a
	 * single, comparatively quick step with no meaningful internal progress
	 * to report, so it is treated as 0% until it completes and control moves
	 * to the next subtask.
	 *
	 * @return  int  0-100.
	 */
	private function get_current_task_percent() {
		if ( $this->current_task !== 'ProcessFragment' || is_null( $this->current_task_class ) ) {
			return 0;
		}

		$total_fragments = $this->get_total_fragments();
		if ( $total_fragments <= 0 ) {
			return 0;
		}

		$substep_fraction = 0.0;
		$subtask_state    = $this->current_task_class->get_state();
		if ( is_array( $subtask_state ) && ! empty( $this->current_task_class->import_tasks ) ) {
			$substep_total       = count( $this->current_task_class->import_tasks );
			$current_subtask_num = isset( $subtask_state['current_subtask_num'] ) && is_numeric( $subtask_state['current_subtask_num'] )
				? (int) $subtask_state['current_subtask_num']
				: -1;

			$substep_fraction = ( $substep_total > 0 && $current_subtask_num >= 0 ) ? ( $current_subtask_num + 1 ) / $substep_total : 0.0;
		}

		return (int) min( 100, round( ( ( $this->current_task_iteration + $substep_fraction ) / $total_fragments ) * 100 ) );
	}

	/**
	 * Look up an import entry by request ID and verify the nonce matches.
	 *
	 * On a successful match the nonce is consumed (blanked in the DB) so the
	 * same callback cannot be replayed.
	 *
	 * @param string $nonce     The nonce from the callback POST body.
	 * @param string $request_id The RequestID from the callback POST body.
	 * @return object The matching import entry row.
	 * @throws \ArloTraining\ImportCallbackRejectedException If no matching entry is found.
	 * @throws \ArloTraining\ImportCallbackNonceRejectedException If an entry matches the request ID but
	 *                                                              the nonce is empty/consumed or does
	 *                                                              not match.
	 */
	protected function get_and_validate_import_entry( string $nonce, string $request_id ) {
		$import = $this->get_import_entry( null, $request_id, 1 );

		if ( is_null( $import ) ) {
			throw new \ArloTraining\ImportCallbackRejectedException( 'Import callback rejected: no valid import entry for request ID' );
		}

		if ( empty( $nonce ) || ! hash_equals( (string) $import->nonce, $nonce ) ) {
			throw new \ArloTraining\ImportCallbackNonceRejectedException( 'Import callback rejected: nonce mismatch or empty nonce', (int) $import->import_id );
		}

		if ( ! $this->consume_import_nonce( $import->import_id, $nonce ) ) {
			throw new \ArloTraining\ImportCallbackNonceRejectedException( 'Import callback rejected: nonce already consumed (possible replay)', (int) $import->import_id );
		}

		return $import;
	}

	/**
	 * Releases the scheduler process lock and re-triggers the scheduler for the
	 * next subtask via an immediate, non-blocking loopback.
	 *
	 * The DB import mutex remains owned by the current request until execute_import()'s
	 * finally block. Only the outer process lock is released here so the self-kicked
	 * request can wait on the DB mutex rather than being blocked by the transient lock.
	 *
	 * Remarks:
	 * - Used by the healthy-progression paths: a completed subtask, and the
	 *   snapshot-request callback in `SnapshotHandler`.
	 * - The failure/retry path in `run_locked_import()` defers its re-trigger
	 *   through `Scheduler::kick_off_scheduler_with_backoff()` instead.
	 *
	 * @return   void
	 */
	public function kick_off_scheduler() {
		$this->scheduler->unlock_process( 'import' );
		$this->scheduler->kick_off_scheduler();
	}

	/**
	 * Entry point for the Arlo platform's asynchronous snapshot-ready callback.
	 *
	 * Validates the callback (nonce and request ID, consuming the nonce so the
	 * same callback cannot be replayed), decrypts the JWE-wrapped snapshot
	 * payload, and hands it to `SnapshotHandler::process()`, which persists the
	 * decoded payload and advances the import to the next subtask on success.
	 * A rejected or invalid callback is logged and otherwise ignored; it never
	 * advances the import.
	 *
	 * Remarks:
	 * - Registered (unauthenticated) via `Arlo_For_Wordpress::import_callback()`
	 *   on both `wp_ajax_arlo_import_callback` and
	 *   `wp_ajax_nopriv_arlo_import_callback` — this is the externally-reachable
	 *   HTTP endpoint the Arlo platform posts the snapshot callback to, not
	 *   something called from within this plugin's own request flow.
	 * - `\ArloTraining\ImportCallbackRejectedException` (a malformed or oversized
	 *   body, or no matching entry) comes from a caller that has not matched a
	 *   pending import. It is passed to `Logger::log_rejected_callback()`, which
	 *   writes a debug-level, throttled row, and is otherwise swallowed with no
	 *   other side effect. `ImportCallbackNonceRejectedException` (a bad nonce, or
	 *   a replayed/already-consumed nonce for a request ID that matched a real
	 *   import) goes to `Logger::log_rejected_matched_callback()`, an info-level
	 *   row in a separate throttle bucket. A valid callback for an import with no active task is likewise ignored
	 *   without consuming its nonce. Every other `\Exception` is logged as a
	 *   synchronization failure and, when the import entry was already
	 *   resolved, marks the matching in-flight task (status 1 paused or 2
	 *   running, found by exact decoded `import_id`) as failed (status 3) so
	 *   the scheduler is not left waiting on a callback that already errored.
	 * - Bumps the execution time limit to `IMPORT_TIMEOUT_SECONDS` for the
	 *   duration of this method, restoring the original limit afterward.
	 *
	 * @return   void
	 */
	public function callback() {
		$original_time_limit = $this->environment->arlo_set_time_limit( self::IMPORT_TIMEOUT_SECONDS );
		$import              = null;
		try {
			$handler = new SnapshotHandler( $this );

			// 1. Read the raw POST body and parse it
			$raw_body = SnapshotHandler::read_bounded_callback_body();
			if ( $raw_body === false ) {
				throw new \Exception( 'Failed to read snapshot callback request body' );
			}
			$snapshot_callback_response = $handler->parse_snapshot_callback_response( $raw_body );

			// 2. Match the callback to its original import request via nonce and request ID
			$pending_import = $this->get_import_entry( null, $snapshot_callback_response->RequestID, 1 );
			// A platform callback can arrive after its task is terminal, such as when a
			// user terminates an import while awaiting the callback, or after the callback
			// wait timed out. Check before get_and_validate_import_entry() consumes the nonce
			// or stores stale callback data.
			$matches_pending_import = is_object( $pending_import )
				&& ! empty( $pending_import->nonce )
				&& hash_equals( $pending_import->nonce, $snapshot_callback_response->Nonce );
			if ( $matches_pending_import && empty( $this->scheduler->get_active_task_by_import_id( $pending_import->import_id ) ) ) {
				Logger::log( 'Import callback arrived too late: its import task is no longer active (the callback wait timed out or the import was stopped).', $pending_import->import_id );
				return;
			}
			// The limit applies to the callback itself so the outcome does not depend on
			// whether a late WP-Cron wake has failed the task yet. The task is left for that
			// wake, or the stalled-import sweep, to fail.
			if ( $matches_pending_import && empty( $pending_import->callback_json ) && true === $this->is_snapshot_callback_wait_expired( $pending_import ) ) {
				Logger::log( 'Import callback arrived too late: ' . self::format_elapsed_time( (int) $this->get_snapshot_request_age_seconds( $pending_import ) ) . ' after the request; the limit is ' . self::format_elapsed_time( \Arlo_For_Wordpress::IMPORT_CALLBACK_WAIT_TIMEOUT_SECONDS ) . '.', $pending_import->import_id );
				return;
			}
			$import = $this->get_and_validate_import_entry( $snapshot_callback_response->Nonce, $snapshot_callback_response->RequestID );
			// Written only after the import matches: an earlier write would bypass the rejected-callback throttle.
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Snapshot callback body received [bytes=' . strlen( $raw_body ) . ']', $import->import_id );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Snapshot callback matched import entry [import_id=' . $import->import_id . '; request_id=' . $snapshot_callback_response->RequestID . ']', $import->import_id );

			// 3. Initialise the import
			$this->set_import_id( $import->import_id );
			// Update the saved import entry with the callback response data
			$this->update_import_entry( [ 'callback_json' => wp_json_encode( $snapshot_callback_response ) ] );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Snapshot callback stored [import_id=' . $import->import_id . ']', $import->import_id );

			// 4. Decrypt the JWE payload
			$key      = $this->get_import_entry_encryption_key( $import );
			$snapshot = $handler->decrypt( $snapshot_callback_response->__jwe__, $key );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Snapshot callback decrypted [response=' . ( ! empty( $snapshot->SnapshotUri ) ? 'snapshot_uri' : ( ! empty( $snapshot->Error ) ? 'error' : 'unknown' ) ) . ']', $import->import_id );

			// 5. Start the import process with the decrypted snapshot data
			$handler->process( $snapshot );
		} catch ( \ArloTraining\ImportCallbackNonceRejectedException $e ) {
			// The request ID matched a real import, so this stays visible at info level, in its own throttle bucket.
			Logger::log_rejected_matched_callback( $e->getMessage(), $e->import_id );
		} catch ( \ArloTraining\ImportCallbackRejectedException $e ) {
			// The caller has not matched a pending import, so this is untrusted noise: debug-only and throttled.
			Logger::log_rejected_callback( $e->getMessage() );
		} catch ( \Exception $e ) {
			$log_import_id = ( ! empty( $import->import_id ) ) ? $import->import_id : null;
			Logger::log( $e->getMessage(), $log_import_id );

			if ( ! empty( $import->import_id ) ) {
				Logger::log( 'Import task failed', $import->import_id );

				$task = $this->scheduler->get_active_task_by_import_id( $import->import_id );

				if ( ! empty( $task[0]->task_id ) ) {
					if ( $this->scheduler->update_task_if_active( $task[0]->task_id, 3, 'Import task failed' ) ) {
						// Run after the terminal transition so stale callbacks cannot trigger cleanup for lost work.
						Logger::execute_log_maintenance();
					}
				}
			} else {
				Logger::log( 'Import callback failed' );
			}
		} finally {
			$this->environment->arlo_set_time_limit( $original_time_limit );
		}
	}

	protected function get_import_entry_encryption_key( object $import_entry ): string {
		if ( empty( $import_entry->response_json ) ) {
			throw new \Exception( 'Encryption key missing from import entry response' );
		}
		$original_response = json_decode( $import_entry->response_json );
		if ( ! is_object( $original_response ) || ! isset( $original_response->Callback->EncryptedResponse->key->k ) ) {
			throw new \Exception( 'Encryption key missing from import entry response' );
		}
		return (string) $original_response->Callback->EncryptedResponse->key->k;
	}

	/**
	 * Returns whether an import's snapshot request is still waiting for its platform callback.
	 *
	 * An import entry that no longer exists is reported as not waiting, so callers only act on
	 * proof that no callback was stored. Expired entries still count: the entry expires an hour
	 * after the request, and a sweep on a quiet site can run well after that.
	 *
	 * @param int $import_id Import identifier from the task checkpoint.
	 * @return bool True when the stored import entry has no callback payload.
	 */
	public function is_awaiting_snapshot_callback( $import_id ): bool {
		$import = $this->get_import_entry_including_expired( $import_id );

		return is_object( $import ) && empty( $import->callback_json );
	}

	public function get_import_entry( $import_id = null, $request_id = null, $limit = null ) {
		return $this->query_import_entry( $import_id, $request_id, $limit, false );
	}

	/**
	 * Returns one import entry by import id even after it has expired.
	 *
	 * @param int $import_id Import identifier.
	 * @return object|null Stored import entry, or null when none exists.
	 */
	public function get_import_entry_including_expired( $import_id ) {
		return $this->query_import_entry( $import_id, null, 1, true );
	}

	/**
	 * Reads import entries, optionally including those past their expiry.
	 *
	 * @param int|null    $import_id       Import identifier filter.
	 * @param string|null $request_id      Platform request identifier filter.
	 * @param int|null    $limit           Maximum rows; a limit of 1 returns a single row or null.
	 * @param bool        $include_expired Whether expired entries may be returned.
	 * @return mixed Matching row, rows, or null.
	 */
	private function query_import_entry( $import_id, $request_id, $limit, $include_expired ) {
		global $wpdb;
		$utc_date = gmdate( "Y-m-d H:i:s" );

		$import_id  = ( ! empty( $import_id ) && is_numeric( $import_id ) ? $import_id : null );
		$limit      = ( ! empty( $limit ) && is_numeric( $limit ) ? $limit : null );
		$request_id = ( ! empty( $request_id ) ? $request_id : null );

		if ( is_null( $request_id ) && is_null( $import_id ) ) {
			return null;
		}

		$sql = "
		SELECT
			import_id,
			request_id,
			nonce,
			callback_json,
			response_json,
			created,
			modified,
			expired
		FROM
			{$wpdb->prefix}arlo_import
		WHERE
			1
			" . ( ! is_null( $import_id ) ? ' AND import_id = %d' : '' ) . "
			" . ( ! is_null( $request_id ) ? ' AND request_id = %s' : '' ) . "
			" . ( $include_expired ? '' : ' AND expired >= %s' ) . "
		" . ( ! is_null( $limit ) ? ' LIMIT %d' : '' ) . "
		";

		$parameter = [];
		if ( ! is_null( $import_id ) ) {
			$parameter[] = $import_id;
		}
		if ( ! is_null( $request_id ) ) {
			$parameter[] = $request_id;
		}
		if ( ! $include_expired ) {
			$parameter[] = $utc_date;
		}
		if ( ! is_null( $limit ) ) {
			$parameter[] = $limit;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database query is required. The SQL statement is constructed from static strings and parameters are prepared here. Do not need cache for import process.
		$import = $wpdb->get_results( $wpdb->prepare( $sql, $parameter ) );
		if ( is_null( $import ) ) {
			Logger::log_error( 'Couldn\'t find valid import' );
		} elseif ( $limit == 1 ) {
			$import = ! empty( $import[0] ) ? $import[0] : null;
		}

		return $import;
	}

	public function update_import_entry( $data = array() ) {
		global $wpdb;
		$utc_date = gmdate( "Y-m-d H:i:s" );

		$available_fields_for_update = [
			'request_id',
			'callback_json',
			'response_json',
		];

		$update_fields = [];

		$parameter = [];

		foreach ( $available_fields_for_update as $field ) {
			if ( ! empty( $data[ $field ] ) ) {
				$update_fields[] = $field . '=%s';
				$parameter[]     = $data[ $field ];
			}
		}

		$sql         = "
		UPDATE
			{$wpdb->prefix}arlo_import
		SET
			" . ( count( $update_fields ) ? implode( ', ', $update_fields ) . ', ' : '' ) . "
			modified = %s
		WHERE
			import_id = %d
		";
		$parameter[] = $utc_date;
		$parameter[] = $this->import_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct database query is required. The SQL statement is constructed from static strings and parameters are prepared here. Do not need cache for import process.
		if ( $wpdb->query( $wpdb->prepare( $sql, $parameter ) ) === false ) {
			throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	/**
	 * Insert a new import entry for the current import.
	 *
	 * @param string|null $nonce Nonce to store with the entry.
	 * @return int|string The import ID.
	 * @throws \Exception If the database insert fails.
	 */
	public function set_import_entry( $nonce = null ) {
		global $wpdb;
		$utc_date        = gmdate( "Y-m-d H:i:s" );
		$utc_plusonehour = gmdate( "Y-m-d H:i:s", time() + ( 60 * 60 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database query is required.Do not need cache for insert operation in data import process.
		$query = $wpdb->query(
			$wpdb->prepare(
				"
		INSERT INTO
			{$wpdb->prefix}arlo_import
			(import_id, nonce, created, expired)
		VALUES
			(%d, %s, %s, %s)
		",
				(int) $this->import_id,
				$nonce,
				$utc_date,
				$utc_plusonehour
			)
		);

		if ( ! $query ) {
			throw new \Exception( 'SQL error creating import entry: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		return $this->import_id;
	}

	/**
	 * Delete the import entry for the given import ID.
	 *
	 * @param int|string $import_id The import ID whose entry should be deleted.
	 * @throws \Exception If the database delete fails.
	 */
	public function delete_import_entry( $import_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct delete required; no cache to invalidate for a row that never completed setup.
		$result = $wpdb->delete(
			"{$wpdb->prefix}arlo_import",
			[ 'import_id' => (int) $import_id ],
			[ '%d' ]
		);

		if ( $result === false ) {
			throw new \Exception( 'SQL error deleting import entry: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	/**
	 * Atomically consume the nonce on an import entry so the same callback
	 * cannot be replayed.
	 *
	 * Called immediately after a successful nonce match in get_and_validate_import_entry().
	 * The UPDATE includes the nonce in its WHERE clause so that only the first
	 * concurrent request succeeds — if a second request races past the PHP-side
	 * comparison, the DB update returns 0 rows and this method returns false,
	 * causing the caller to reject the duplicate.
	 *
	 * @param int|string $import_id The import entry to consume the nonce for.
	 * @param string     $nonce     The nonce value to match atomically.
	 * @return bool True if the nonce was consumed, false if already consumed or row missing.
	 * @throws \Exception If the DB update fails.
	 */
	private function consume_import_nonce( $import_id, string $nonce ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time nonce consumption; caching not applicable.
		$result = $wpdb->update(
			"{$wpdb->prefix}arlo_import",
			[ 'nonce' => '' ],
			[
				'import_id' => (int) $import_id,
				'nonce'     => $nonce,
			],
			[ '%s' ],
			[ '%d', '%s' ]
		);
		if ( $result === false ) {
			throw new \Exception( 'Import callback rejected: failed to consume nonce due to DB error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
		return $result === 1;
	}

	/**
	 * Build the status text shown while the snapshot request is being sent.
	 *
	 * The import row does not contain the persisted request metadata until
	 * ImportRequest::run() succeeds, so this uses the current API client session.
	 *
	 * @return string Status text for the running ImportRequest step.
	 */
	private function get_import_request_status_text() {
		$message = 'Requesting import snapshot';
		$host    = '';

		if ( $this->api_client && is_scalar( $this->api_client->platform_name ) ) {
			$platform_name = strtolower( trim( (string) $this->api_client->platform_name ) );
			if ( $platform_name !== '' && preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $platform_name ) && strlen( $platform_name ) <= 63 ) {
				$host = $platform_name . \ArloTraining\API\Transports\Transport::PLATFORM_DOMAIN;
			}
		}

		if ( $host !== '' ) {
			$message .= ' from ' . $host;
		}

		return $message;
	}

	/**
	 * Build the paused task text after the snapshot request has been accepted.
	 *
	 * @return string Empty for ordinary paused subtasks; callback-wait text for ImportRequest.
	 */
	private function get_paused_task_status_text() {
		if ( $this->current_task !== 'ImportRequest' ) {
			return '';
		}

		$message = 'Waiting on import snapshot callback';
		$import  = $this->get_import_entry( $this->import_id, null, 1 );
		$host    = $this->get_import_platform_hostname( $import );

		if ( $host !== '' ) {
			$message .= ' from ' . $host;
		}

		return $message;
	}

	/**
	 * Build the log message written when a snapshot callback has not arrived.
	 *
	 * @param object|null $import Import row containing request metadata and creation time.
	 * @return string Missing-callback message with host and elapsed time when available.
	 */
	private function get_missing_callback_message( $import ) {
		$message = 'No snapshot callback received';
		$host    = $this->get_import_platform_hostname( $import );

		if ( $host !== '' ) {
			$message .= ' from ' . $host;
		}

		$elapsed_seconds = $this->get_snapshot_request_age_seconds( $import );
		if ( null === $elapsed_seconds ) {
			return $message;
		}

		return $message . ' after ' . self::format_elapsed_time( $elapsed_seconds );
	}

	/**
	 * Seconds since the snapshot request was made, from the import entry's creation time.
	 *
	 * @param object|null $import Import row containing the creation time.
	 * @return int|null Age in seconds, or null when the row or its time is missing or unparseable.
	 */
	private function get_snapshot_request_age_seconds( $import ): ?int {
		if ( ! is_object( $import ) || empty( $import->created ) ) {
			return null;
		}

		try {
			$created_ts = ( new \DateTime( (string) $import->created, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return null;
		}

		return max( 0, \ArloTraining\Utilities::get_now_utc()->getTimestamp() - $created_ts );
	}

	/**
	 * Counts one re-check of the snapshot callback without spending the Download retry budget.
	 *
	 * The wake provisionally incremented current_subtask_retry in set_state(); waiting is not a
	 * failure of the step, so that counter is put back and the waits are tracked on their own
	 * checkpoint key, which only spaces the next re-check.
	 *
	 * @return int|null The 1-based wait attempt number, used for the backoff spacing, or null when
	 *                  another process owns the import and the checkpoint write was rejected.
	 */
	private function record_callback_wait_attempt(): ?int {
		$attempt = ( is_object( $this->state ) ? (int) ( $this->state->callback_wait_attempts ?? 0 ) : 0 ) + 1;

		$written = $this->update_task_data_if_mutex_owned(
			$this->task_id,
			[
				'current_subtask_retry'  => 0,
				'callback_wait_attempts' => $attempt,
			]
		);

		return $written ? $attempt : null;
	}

	/**
	 * Whether the current import has waited at least the callback wait limit for its snapshot callback.
	 *
	 * Compares the request timestamp rather than counting wakes, so a late WP-Cron wake still
	 * reaches the same answer. A stored callback ends the wait whatever the age: one accepted just
	 * before the limit can be stored after the check that found none.
	 *
	 * @param object|null $import Import row, or null to read the current import's entry.
	 * @return bool|null True when the limit has passed with no callback, false while inside it or
	 *                   once the callback is stored, null when the request time is unknown.
	 */
	private function is_snapshot_callback_wait_expired( $import = null ): ?bool {
		if ( null === $import ) {
			$import = $this->get_import_entry_including_expired( $this->import_id );
		}

		if ( is_object( $import ) && ! empty( $import->callback_json ) ) {
			return false;
		}

		$age = $this->get_snapshot_request_age_seconds( $import );

		return null === $age ? null : $age >= \Arlo_For_Wordpress::IMPORT_CALLBACK_WAIT_TIMEOUT_SECONDS;
	}

	/**
	 * Read the original platform hostname stored with a snapshot request response.
	 *
	 * @param object|null $import Import row whose response_json may contain ArloMetadata.
	 * @return string Validated platform host, or an empty string when unavailable.
	 */
	private function get_import_platform_hostname( $import ) {
		if ( ! is_object( $import ) ) {
			return '';
		}

		if ( empty( $import->response_json ) ) {
			return '';
		}

		$response_json = json_decode( $import->response_json );
		if ( ! is_object( $response_json ) || ! is_object( $response_json->ArloMetadata ?? null ) || empty( $response_json->ArloMetadata->PlatformHostname ) || ! is_scalar( $response_json->ArloMetadata->PlatformHostname ) ) {
			return '';
		}

		$host            = rtrim( strtolower( trim( trim( (string) $response_json->ArloMetadata->PlatformHostname ), '[]' ) ), '.' );
		$platform_domain = preg_quote( ltrim( \ArloTraining\API\Transports\Transport::PLATFORM_DOMAIN, '.' ), '/' );
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?\.' . $platform_domain . '$/i', $host ) ) {
			return '';
		}

		return $host;
	}

	/**
	 * Format elapsed seconds for repeated callback-timeout log messages.
	 *
	 * @param int $elapsed_seconds Elapsed duration in seconds.
	 * @return string Elapsed duration in total seconds.
	 */
	private static function format_elapsed_time( $elapsed_seconds ) {
		$elapsed_seconds = max( 0, (int) $elapsed_seconds );

		return $elapsed_seconds . ' ' . ( $elapsed_seconds === 1 ? 'second' : 'seconds' );
	}

	/**
	 * Compact import task state for support diagnostic log entries.
	 *
	 * @param mixed $state Import checkpoint state.
	 * @return string Human-readable state summary.
	 */
	private function format_task_state_for_diagnostic_log( $state ) {
		if ( $state === false ) {
			return 'false';
		}

		if ( is_null( $state ) ) {
			return 'new';
		}

		if ( is_object( $state ) ) {
			$state = get_object_vars( $state );
		}

		if ( ! is_array( $state ) ) {
			return 'unknown';
		}

		$parts = [];
		foreach ( [ 'current_subtask', 'finished_subtask', 'current_subtask_retry', 'iteration' ] as $key ) {
			if ( array_key_exists( $key, $state ) && ! is_null( $state[ $key ] ) && $state[ $key ] !== '' ) {
				$parts[] = $key . '=' . $this->format_diagnostic_log_value( $state[ $key ] );
			}
		}

		if ( ! empty( $state['subtask_state'] ) ) {
			$subtask_state = is_object( $state['subtask_state'] ) ? get_object_vars( $state['subtask_state'] ) : $state['subtask_state'];
			if ( is_array( $subtask_state ) ) {
				$subtask_parts = [];
				foreach ( [ 'current_subtask', 'finished_subtask', 'current_subtask_desc', 'current_subtask_retry', 'iteration', 'table_cleanup_index', 'custom_post_cleanup_index' ] as $key ) {
					if ( array_key_exists( $key, $subtask_state ) && ! is_null( $subtask_state[ $key ] ) && $subtask_state[ $key ] !== '' ) {
						$subtask_parts[] = $key . '=' . $this->format_diagnostic_log_value( $subtask_state[ $key ] );
					}
				}
				if ( ! empty( $subtask_parts ) ) {
					$parts[] = 'subtask_state=(' . implode( ', ', $subtask_parts ) . ')';
				}
			}
		}

		return empty( $parts ) ? 'empty' : implode( '; ', $parts );
	}

	/**
	 * Format an arbitrary checkpoint value without invoking PHP string conversion warnings.
	 *
	 * @param mixed $value Checkpoint value.
	 * @return string Compact value description for diagnostic logs.
	 */
	private function format_diagnostic_log_value( $value ): string {
		if ( is_null( $value ) ) {
			return 'null';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( is_array( $value ) ) {
			return 'array(' . count( $value ) . ')';
		}

		if ( is_object( $value ) ) {
			return 'object(' . get_class( $value ) . ')';
		}

		return gettype( $value );
	}

	/**
	 * Summarizes the admitted callback without requiring optional delta fields.
	 *
	 * @param mixed $callback_json Decoded callback response.
	 * @return string Structured callback fields for diagnostic logging.
	 */
	private function format_snapshot_callback_diagnostic_fields( $callback_json ): string {
		$fields = [];
		if ( is_object( $this->state ) && isset( $this->state->schema_level ) && is_scalar( $this->state->schema_level ) ) {
			$fields[] = 'schema_level=' . $this->state->schema_level;
		}

		if ( is_object( $callback_json ) ) {
			foreach ( [
				'SnapshotID'     => 'snapshot_id',
				'SnapshotUri'    => 'snapshot_uri',
				'ManifestUri'    => 'manifest_uri',
				'ManifestSha256' => 'manifest_sha256',
				'ManifestBytes'  => 'manifest_bytes',
			] as $property => $field ) {
				if ( isset( $callback_json->$property ) && is_scalar( $callback_json->$property ) && '' !== (string) $callback_json->$property ) {
					$fields[] = $field . '=' . $callback_json->$property;
				}
			}
		}

		return empty( $fields ) ? 'details=unavailable' : implode( '; ', $fields );
	}

	/**
	 * Asserts that a callback manifest advertisement and its source response are usable.
	 *
	 * @param object $callback_json Decrypted callback response.
	 * @param mixed  $response_json Original snapshot request response.
	 * @return void
	 * @throws \ArloTraining\SnapshotProcessException When either external payload is malformed.
	 */
	private function assert_snapshot_manifest_advertisement_shape( object $callback_json, $response_json ): void {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are authenticated external platform callback field names.
		if ( ! is_scalar( $callback_json->SnapshotID ?? null )
			|| '' === trim( (string) $callback_json->SnapshotID )
			|| ! is_scalar( $callback_json->SnapshotUri ?? null )
			|| '' === trim( (string) $callback_json->SnapshotUri )
			|| ! is_scalar( $callback_json->ManifestUri ?? null )
			|| ! is_scalar( $callback_json->ManifestSha256 ?? null )
			|| ! is_int( $callback_json->ManifestBytes ?? null ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest advertisement is invalid' );
		}

		if ( ! is_object( $response_json ) || ! isset( $response_json->ArloMetadata ) || ! is_object( $response_json->ArloMetadata ) || ! isset( $response_json->ArloMetadata->PlatformHostname ) || ! is_scalar( $response_json->ArloMetadata->PlatformHostname ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest advertisement is invalid' );
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
}
