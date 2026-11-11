<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Persists immutable delta-analysis inputs and complete target comparison staging.
 */
class IncrementalRunRepository {
	const MODE_FULL  = 'full';
	const MODE_DELTA = 'delta';

	const RESULT_PENDING     = 'pending';
	const RESULT_NO_OP       = 'no_op';
	const RESULT_DELTA       = 'delta';
	const RESULT_FULL_SHADOW = 'full_shadow';

	const COMPARE_RESULT_CENSUS_TAG_MISMATCH         = 'census_tag_mismatch';
	const COMPARE_RESULT_MANIFEST_STAGING_INCOMPLETE = 'manifest_staging_incomplete';
	const COMPARE_RESULT_TARGET_HAS_CHANGED_KEYS     = 'target_has_changed_keys';
	const COMPARE_RESULT_TARGET_HAS_MISSING_KEYS     = 'target_has_missing_keys';
	const COMPARE_RESULT_TARGET_HAS_NEW_KEYS         = 'target_has_new_keys';

	const REASON_ANALYSIS_FAILED             = 'analysis_failed';
	const REASON_BASELINE_CHANGED            = 'baseline_changed';
	const REASON_NO_OP_PROOF_LOST            = 'no_op_proof_lost';
	const REASON_MANIFEST_STAGING_INCOMPLETE = self::COMPARE_RESULT_MANIFEST_STAGING_INCOMPLETE;

	/** @var \wpdb */
	private $wpdb;

	/**
	 * Uses the supplied WordPress database connection, or the global connection when omitted.
	 *
	 * @param \wpdb|null $wpdb WordPress database connection.
	 */
	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb !== null ? $wpdb : $GLOBALS['wpdb'];
	}

	/**
	 * Returns the current compatible baseline only while it is clean and live.
	 *
	 * @param int    $schema_level Manifest schema level.
	 * @param string $configuration_signature Raw configuration signature.
	 * @return object|null Compatible clean baseline state, or null when none exists.
	 * @throws \RuntimeException When the baseline read fails.
	 */
	public function get_current_clean_baseline( int $schema_level, string $configuration_signature ) {
		$baseline = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT baseline_state_id, current_baseline_generation_id, projection_count, census_tag FROM %i WHERE baseline_state_id = %d AND baseline_status = %s AND schema_level = %d AND configuration_signature = %s AND current_baseline_generation_id IS NOT NULL', $this->get_baseline_state_table(), 1, IncrementalBaselineRepository::STATUS_CLEAN, $schema_level, $configuration_signature ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delta admission reads the plugin-owned singleton baseline state before creating a run.
		$this->throw_if_database_error();

		return $baseline;
	}

	/**
	 * Returns the admitted run identified by the coordinator checkpoint.
	 *
	 * @param int $run_id Incremental run id.
	 * @return object|null Persisted run row, or null when the checkpoint is absent.
	 * @throws \RuntimeException When the run read fails.
	 */
	public function get_run( int $run_id ) {
		$run = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE incremental_run_id = %d', $this->get_run_table(), $run_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The coordinator reads one plugin-owned immutable run by its durable checkpoint id.
		$this->throw_if_database_error();

		return $run;
	}

	/**
	 * Creates one pending run that immutably records an admitted snapshot boundary.
	 *
	 * @param int                     $admitted_import_id Owning importer session id.
	 * @param int                     $expected_active_import_id Live import id expected by later delta work.
	 * @param SnapshotManifestRequest $request Validated admitted manifest request.
	 * @param SnapshotManifest        $manifest Complete validated target manifest.
	 * @param string                  $configuration_signature Raw configuration signature.
	 * @param string                  $image_content Decoded admitted image content.
	 * @param int                     $expected_baseline_generation_id Compatible current baseline generation.
	 * @return int Positive incremental run id.
	 * @throws \RuntimeException When the immutable run cannot be persisted.
	 */
	public function create_run( int $admitted_import_id, int $expected_active_import_id, SnapshotManifestRequest $request, SnapshotManifest $manifest, string $configuration_signature, string $image_content, int $expected_baseline_generation_id ): int {
		$now      = gmdate( 'Y-m-d H:i:s' );
		$inserted = $this->wpdb->insert(
			$this->get_run_table(),
			[
				'admitted_import_id'              => $admitted_import_id,
				'expected_active_import_id'       => $expected_active_import_id,
				'schema_level'                    => $manifest->schema_level,
				'configuration_signature'         => $configuration_signature,
				'source_snapshot_id'              => $request->snapshot_id,
				'snapshot_package_digest'         => hash( 'sha256', $image_content, true ),
				'census_tag'                      => $manifest->census_tag,
				'snapshot_manifest_count'         => count( $manifest->entries ),
				'expected_baseline_generation_id' => $expected_baseline_generation_id,
				'created'                         => $now,
				'modified'                        => $now,
			]
		);

		if ( false === $inserted || (int) $this->wpdb->insert_id <= 0 ) {
			$this->throw_database_error( 'Could not create incremental run.' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Stages every admitted target-manifest entry for indexed baseline comparison.
	 *
	 * @param int              $run_id Incremental run id.
	 * @param SnapshotManifest $manifest Complete validated target manifest.
	 * @return void
	 * @throws \RuntimeException When target intent cannot be persisted.
	 */
	public function stage_target_manifest( int $run_id, SnapshotManifest $manifest ): void {
		foreach ( $manifest->entries as $entry ) {
			if ( false === $this->wpdb->insert(
				$this->get_snapshot_manifest_table(),
				[
					'analysis_run_id'               => $run_id,
					'entity_kind'                   => $entry->kind,
					'entity_key_1'                  => $entry->key[0],
					'entity_key_2'                  => $entry->key[1] ?? '',
					'entity_region_code'            => $entry->region,
					'structural_tag'                => $entry->structural_tag,
					'availability_tag'              => $entry->availability_tag,
					'source_fragment_element_index' => $entry->fragment_element_index,
				]
			) ) {
				$this->throw_database_error( 'Could not stage incremental target manifest entry.' );
			}
		}
	}

	/**
	 * Removes all comparison staging for a run before a conservative full-shadow fallback.
	 *
	 * @param int $run_id Incremental run id.
	 * @return void
	 * @throws \RuntimeException When target staging cannot be deleted.
	 */
	public function delete_staged_target_manifest( int $run_id ): void {
		if ( false === $this->wpdb->delete(
			$this->get_snapshot_manifest_table(),
			[ 'analysis_run_id' => $run_id ],
			[ '%d' ]
		) ) {
			$this->throw_database_error( 'Could not delete staged incremental target manifest.' );
		}
	}

	/**
	 * Compares the complete staged target with its expected baseline using indexed set operations.
	 *
	 * @param int $run_id Incremental run id.
	 * @param int $baseline_generation_id Expected compatible baseline generation.
	 * @param int $expected_count Manifest envelope row count.
	 * @return string One of the COMPARE_RESULT_* values describing why delta cannot be a no-op.
	 * @throws \RuntimeException When a comparison query fails.
	 */
	public function compare_run_result_to_baseline( int $run_id, int $baseline_generation_id, int $expected_count ): string {
		$manifest_table = $this->get_snapshot_manifest_table();
		$baseline_table = $this->get_baseline_table();
		$staged_count   = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE analysis_run_id = %d', $manifest_table, $run_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The coordinator validates its plugin-owned complete target staging before comparison.
		$this->throw_if_database_error();
		if ( $staged_count !== $expected_count ) {
			return self::COMPARE_RESULT_MANIFEST_STAGING_INCOMPLETE;
		}

		$changed = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT 1 FROM %i AS target INNER JOIN %i AS baseline ON baseline.baseline_generation_id = %d AND baseline.entity_kind = target.entity_kind AND baseline.entity_key_1 = target.entity_key_1 AND baseline.entity_key_2 = target.entity_key_2 AND baseline.entity_region_code = target.entity_region_code WHERE target.analysis_run_id = %d AND (NOT ( baseline.structural_tag <=> target.structural_tag ) OR NOT ( baseline.availability_tag <=> target.availability_tag )) LIMIT 1', $manifest_table, $baseline_table, $baseline_generation_id, $run_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed inner join compares compact plugin-owned manifest and baseline projection tags.
		$this->throw_if_database_error();
		$new_keys = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT 1 FROM %i AS target LEFT JOIN %i AS baseline ON baseline.baseline_generation_id = %d AND baseline.entity_kind = target.entity_kind AND baseline.entity_key_1 = target.entity_key_1 AND baseline.entity_key_2 = target.entity_key_2 AND baseline.entity_region_code = target.entity_region_code WHERE target.analysis_run_id = %d AND baseline.baseline_row_id IS NULL LIMIT 1', $manifest_table, $baseline_table, $baseline_generation_id, $run_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed target-to-baseline anti-join detects unsupported additions without loading a census into PHP.
		$this->throw_if_database_error();
		$missing = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT 1 FROM %i AS baseline LEFT JOIN %i AS target ON target.analysis_run_id = %d AND target.entity_kind = baseline.entity_kind AND target.entity_key_1 = baseline.entity_key_1 AND target.entity_key_2 = baseline.entity_key_2 AND target.entity_region_code = baseline.entity_region_code WHERE baseline.baseline_generation_id = %d AND target.snapshot_manifest_row_id IS NULL LIMIT 1', $baseline_table, $manifest_table, $run_id, $baseline_generation_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Indexed baseline-to-target anti-join detects unsupported omissions without loading a census into PHP.
		$this->throw_if_database_error();

		if ( $new_keys > 0 ) {
			return self::COMPARE_RESULT_TARGET_HAS_NEW_KEYS;
		}
		if ( $missing > 0 ) {
			return self::COMPARE_RESULT_TARGET_HAS_MISSING_KEYS;
		}
		if ( $changed > 0 ) {
			return self::COMPARE_RESULT_TARGET_HAS_CHANGED_KEYS;
		}

		return self::COMPARE_RESULT_CENSUS_TAG_MISMATCH;
	}

	/**
	 * Rechecks the current admitted boundary and resolves a pending run as full shadow.
	 *
	 * Derives and persists the fallback reason under the same transaction as the result transition.
	 *
	 * @param int         $run_id                   Incremental run id.
	 * @param int         $current_active_import_id Current live import id for the boundary recheck.
	 * @param string|null $forced_reason            Optional sealed reason for a known candidate-less pre-write fallback.
	 * @return void
	 * @throws \InvalidArgumentException When a forced reason is unsupported.
	 * @throws \RuntimeException When the run is not pending or transaction, query, or state persistence fails.
	 */
	public function resolve_run_as_full_shadow( int $run_id, int $current_active_import_id, ?string $forced_reason = null ): void {
		if ( null !== $forced_reason && self::REASON_NO_OP_PROOF_LOST !== $forced_reason ) {
			throw new \InvalidArgumentException( 'Incremental full-shadow forced reason is unsupported.' );
		}

		if ( false === $this->wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The run decision must lock its plugin-owned run and baseline state together.
			$this->throw_database_error( 'Could not start incremental run analysis.' );
		}

		try {
			$run      = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE incremental_run_id = %d FOR UPDATE', $this->get_run_table(), $run_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The coordinator locks its one plugin-owned admitted run before sealing it.
			$baseline = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE baseline_state_id = %d FOR UPDATE', $this->get_baseline_state_table(), 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The coordinator locks the plugin-owned singleton comparison boundary before sealing.
			$this->throw_if_database_error();
			if ( ! is_object( $run ) || self::RESULT_PENDING !== $run->terminal_result ) {
				throw new \RuntimeException( 'Incremental run is unavailable for analysis.' );
			}

			$reason = self::REASON_BASELINE_CHANGED;
			if ( self::REASON_NO_OP_PROOF_LOST === $forced_reason ) {
				$reason = $forced_reason;
			} elseif ( is_object( $baseline )
				&& IncrementalBaselineRepository::STATUS_CLEAN === $baseline->baseline_status
				&& $current_active_import_id === (int) $run->expected_active_import_id
				&& (int) $baseline->current_baseline_generation_id === (int) $run->expected_baseline_generation_id
				&& (int) $baseline->schema_level === (int) $run->schema_level
				&& hash_equals( $baseline->configuration_signature, $run->configuration_signature ) ) {
				$reason = $this->compare_run_result_to_baseline( $run_id, (int) $run->expected_baseline_generation_id, (int) $run->snapshot_manifest_count );
			}

			$updated = $this->wpdb->update(
				$this->get_run_table(),
				[
					'sealed_mode'        => self::MODE_FULL,
					'terminal_result'    => self::RESULT_FULL_SHADOW,
					'fallback_reason'    => $reason,
					'analysis_sealed_at' => gmdate( 'Y-m-d H:i:s' ),
					'modified'           => gmdate( 'Y-m-d H:i:s' ),
				],
				[
					'incremental_run_id' => $run_id,
					'terminal_result'    => self::RESULT_PENDING,
				]
			);
			if ( false === $updated ) {
				$this->throw_database_error( 'Could not seal incremental run analysis.' );
			}
			if ( 0 === $updated ) {
				throw new \RuntimeException( 'Incremental full-shadow run did not remain pending during finalization.' );
			}

			if ( false === $this->wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The full-shadow seal is valid only when its locked analysis commits.
				$this->throw_database_error( 'Could not commit incremental run analysis.' );
			}

			return;
		} catch ( \Throwable $exception ) {
			$this->rollback_analysis();
			throw $exception;
		}
	}

	/**
	 * Tries to mark an unchanged pending run successful only while its complete admitted boundary remains current.
	 *
	 * @param int $run_id Incremental run id.
	 * @return bool True when the no-op proof remained current and was sealed; false when any locked run, live-import, or baseline predicate no longer matches.
	 * @throws \RuntimeException When transaction or state persistence fails.
	 */
	public function try_mark_no_op_run_success_if_current( int $run_id ): bool {
		if ( false === $this->wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The no-op decision must lock its plugin-owned run and baseline with the live import option.
			$this->throw_database_error( 'Could not start incremental no-op sealing.' );
		}

		try {
			$active_import_id = $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s FOR UPDATE', $this->wpdb->options, 'arlo_import_id' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The live import option is locked with the pending run and baseline before recording terminal no-op success.
			$run              = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE incremental_run_id = %d FOR UPDATE', $this->get_run_table(), $run_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The coordinator locks its one plugin-owned admitted run before sealing it.
			$baseline         = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE baseline_state_id = %d FOR UPDATE', $this->get_baseline_state_table(), 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The singleton baseline state remains locked through no-op proof and result sealing.
			$this->throw_if_database_error();

			$matches = is_object( $run )
				&& is_object( $baseline )
				&& self::RESULT_PENDING === $run->terminal_result
				&& IncrementalBaselineRepository::STATUS_CLEAN === $baseline->baseline_status
				&& (int) $active_import_id === (int) $run->expected_active_import_id
				&& (int) $baseline->current_baseline_generation_id === (int) $run->expected_baseline_generation_id
				&& (int) $baseline->schema_level === (int) $run->schema_level
				&& is_string( $baseline->configuration_signature ?? null )
				&& hash_equals( $baseline->configuration_signature, $run->configuration_signature )
				&& (int) $baseline->projection_count === (int) $run->snapshot_manifest_count
				&& is_string( $baseline->census_tag ?? null )
				&& hash_equals( $baseline->census_tag, $run->census_tag );
			if ( ! $matches ) {
				// A stale proof is an expected declined compare-and-set; no live write exists, so full shadow can safely converge.
				$this->rollback_analysis();
				return false;
			}

			$updated = $this->wpdb->update(
				$this->get_run_table(),
				[
					'sealed_mode'        => self::MODE_DELTA,
					'terminal_result'    => self::RESULT_NO_OP,
					'analysis_sealed_at' => gmdate( 'Y-m-d H:i:s' ),
					'modified'           => gmdate( 'Y-m-d H:i:s' ),
				],
				[
					'incremental_run_id' => $run_id,
					'terminal_result'    => self::RESULT_PENDING,
				]
			);
			if ( 1 !== $updated ) {
				if ( false === $updated ) {
					$this->throw_database_error( 'Could not seal incremental no-op.' );
				}
				throw new \RuntimeException( 'Incremental no-op run did not remain pending during finalization.' );
			}

			if ( false === $this->wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The terminal no-op result is valid only when its locked proof commits.
				$this->throw_database_error( 'Could not commit incremental no-op sealing.' );
			}

			return true;
		} catch ( \Throwable $exception ) {
			$this->rollback_analysis();
			throw $exception;
		}
	}

	/**
	 * Diagnoses whether a bounded set of availability-only Event roots is eligible for the overlay.
	 *
	 * This diagnostic read does not seal or otherwise mutate the run. The writer calls
	 * seal_first_event_availability_write() inside its bundle transaction so a
	 * pre-write rejection remains eligible for same-import full shadow.
	 *
	 * @param int $run_id Incremental run id.
	 * @param int $baseline_generation_id Expected clean baseline generation.
	 * @param int $expected_count Complete target-manifest row count.
	 * @param int $maximum_selected_roots Code-owned selected-root limit.
	 * @return array<int,object>|null Selected immutable root intents, or null when full shadow is required.
	 * @throws \RuntimeException When selection queries cannot complete.
	 */
	public function try_select_bounded_event_availability_delta( int $run_id, int $baseline_generation_id, int $expected_count, int $maximum_selected_roots ) {
		if ( $maximum_selected_roots < 1 ) {
			throw new \InvalidArgumentException( 'Selected Event root limit is invalid.' );
		}
		$manifest_table = $this->get_snapshot_manifest_table();
		$baseline_table = $this->get_baseline_table();
		$staged_count   = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE analysis_run_id = %d', $manifest_table, $run_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delta selection verifies complete plugin-owned target intent before sealing one bounded Event root.
		$this->throw_if_database_error();
		if ( $staged_count !== $expected_count ) {
			return null;
		}

		// Find existing Event records whose structure is unchanged but whose availability differs from the baseline.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The narrow Event overlay selects one indexed existing root by immutable target/baseline identity and tags.
		$event_records_with_changed_availability = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT
					target.entity_key_1,
					target.entity_region_code,
					target.source_fragment_element_index
				FROM %i AS target
				INNER JOIN %i AS baseline ON
					baseline.baseline_generation_id = %d
					AND baseline.entity_kind = target.entity_kind
					AND baseline.entity_key_1 = target.entity_key_1
					AND baseline.entity_key_2 = target.entity_key_2
					AND baseline.entity_region_code = target.entity_region_code
				WHERE target.analysis_run_id = %d
					AND target.entity_kind = %s
					AND baseline.structural_tag <=> target.structural_tag
					AND NOT ( baseline.availability_tag <=> target.availability_tag )
				ORDER BY target.entity_key_1, target.entity_region_code',
				$manifest_table,
				$baseline_table,
				$baseline_generation_id,
				$run_id,
				SnapshotManifestParser::MANIFEST_ENTITY_KIND_EVENT
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The narrow Event overlay selects one indexed existing root by immutable target/baseline identity and tags.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$this->throw_if_database_error();

		// Require a bounded changed Event set with valid references into source fragments.
		if ( ! is_array( $event_records_with_changed_availability )
			|| [] === $event_records_with_changed_availability
			|| count( $event_records_with_changed_availability ) > $maximum_selected_roots ) {
			return null;
		}
		foreach ( $event_records_with_changed_availability as $event_record ) {
			if ( ! is_object( $event_record )
				|| ! is_numeric( $event_record->entity_key_1 )
				|| (int) $event_record->entity_key_1 <= 0
				|| ! is_string( $event_record->entity_region_code )
				|| '' === $event_record->entity_region_code
				|| ! is_numeric( $event_record->source_fragment_element_index )
				|| (int) $event_record->source_fragment_element_index < 0 ) {
				return null;
			}
		}

		// Count every changed target record, regardless of entity kind or whether its structural or availability tag changed.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Every changed target key must be the single selected Event root before delta may be sealed.
		$changed_record_count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*)
				FROM %i AS target
				INNER JOIN %i AS baseline ON
					baseline.baseline_generation_id = %d
					AND baseline.entity_kind = target.entity_kind
					AND baseline.entity_key_1 = target.entity_key_1
					AND baseline.entity_key_2 = target.entity_key_2
					AND baseline.entity_region_code = target.entity_region_code
				WHERE target.analysis_run_id = %d
					AND (
						NOT ( baseline.structural_tag <=> target.structural_tag )
						OR NOT ( baseline.availability_tag <=> target.availability_tag )
					)',
				$manifest_table,
				$baseline_table,
				$baseline_generation_id,
				$run_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Every changed target key must be the single selected Event root before delta may be sealed.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$this->throw_if_database_error();

		// Reject the overlay unless every changed record is selected and no keys are missing or new.
		if ( count( $event_records_with_changed_availability ) !== $changed_record_count || self::COMPARE_RESULT_TARGET_HAS_CHANGED_KEYS !== $this->compare_run_result_to_baseline( $run_id, $baseline_generation_id, $expected_count ) ) {
			return null;
		}

		return $event_records_with_changed_availability;
	}

	/**
	 * Performs the first Event overlay write compare-and-set inside the caller's transaction.
	 *
	 * @param int $run_id Incremental run identifier.
	 * @param int $current_active_import_id Current live import identifier.
	 * @param int $selected_root_count Count of selected Event roots in the bounded write window.
	 * @return void
	 * @throws \RuntimeException When the expected clean baseline cannot be atomically tainted and sealed.
	 */
	public function seal_first_event_availability_write( int $run_id, int $current_active_import_id, int $selected_root_count ): void {
		if ( $selected_root_count < 1 ) {
			throw new \InvalidArgumentException( 'Selected Event root count is invalid.' );
		}
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The selected run and plugin-owned singleton baseline row are locked before the first live Event write.
		$run      = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE incremental_run_id = %d FOR UPDATE', $this->get_run_table(), $run_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The selected run is locked with its baseline before the first live Event write.
		$baseline = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE baseline_state_id = %d FOR UPDATE', $this->get_baseline_state_table(), 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin-owned singleton baseline row must be locked before it is tainted.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$this->throw_if_database_error();

		if ( ! is_object( $run ) || ! is_object( $baseline )
			|| self::RESULT_PENDING !== $run->terminal_result
			|| IncrementalBaselineRepository::STATUS_CLEAN !== $baseline->baseline_status
			|| $current_active_import_id !== (int) $run->expected_active_import_id
			|| (int) $baseline->current_baseline_generation_id !== (int) $run->expected_baseline_generation_id
			|| (int) $baseline->schema_level !== (int) $run->schema_level
			|| ! hash_equals( $baseline->configuration_signature, $run->configuration_signature ) ) {
			throw new \RuntimeException( 'Incremental Event availability write no longer has its expected clean baseline.' );
		}

		$tainted = $this->wpdb->update(
			$this->get_baseline_state_table(),
			[
				'baseline_status' => IncrementalBaselineRepository::STATUS_FULL_REQUIRED,
				'modified'        => gmdate( 'Y-m-d H:i:s' ),
			],
			[
				'baseline_state_id'              => 1,
				'baseline_status'                => IncrementalBaselineRepository::STATUS_CLEAN,
				'current_baseline_generation_id' => (int) $run->expected_baseline_generation_id,
			]
		);
		if ( 1 !== $tainted ) {
			if ( false === $tainted ) {
				$this->throw_database_error( 'Could not mark incremental baseline full-required.' );
			}
			throw new \RuntimeException( 'Incremental Event availability baseline changed during first-write sealing.' );
		}

		$sealed = $this->wpdb->update(
			$this->get_run_table(),
			[
				'sealed_mode'         => self::MODE_DELTA,
				'terminal_result'     => self::RESULT_DELTA,
				'selected_root_count' => $selected_root_count,
				'analysis_sealed_at'  => gmdate( 'Y-m-d H:i:s' ),
				'modified'            => gmdate( 'Y-m-d H:i:s' ),
			],
			[
				'incremental_run_id' => $run_id,
				'terminal_result'    => self::RESULT_PENDING,
			]
		);
		if ( 1 !== $sealed ) {
			if ( false === $sealed ) {
				$this->throw_database_error( 'Could not seal Event availability delta.' );
			}
			throw new \RuntimeException( 'Incremental Event availability run did not remain pending during first-write sealing.' );
		}
	}

	/**
	 * Marks a pending run for full shadow with a caller-supplied value-free reason.
	 *
	 * @param int    $run_id Incremental run id.
	 * @param string $reason Value-free fallback reason.
	 * @return bool True when the pending result was marked; false when it was already terminal or unavailable.
	 * @throws \RuntimeException When the result cannot be persisted.
	 */
	public function mark_run_full_shadow_with_reason( int $run_id, string $reason ): bool {
		$updated = $this->wpdb->update(
			$this->get_run_table(),
			[
				'sealed_mode'        => self::MODE_FULL,
				'terminal_result'    => self::RESULT_FULL_SHADOW,
				'fallback_reason'    => $reason,
				'analysis_sealed_at' => gmdate( 'Y-m-d H:i:s' ),
				'modified'           => gmdate( 'Y-m-d H:i:s' ),
			],
			[
				'incremental_run_id' => $run_id,
				'terminal_result'    => self::RESULT_PENDING,
			]
		);
		if ( false === $updated ) {
			$this->throw_database_error( 'Could not seal incremental full-shadow fallback.' );
		}

		return 1 === $updated;
	}

	/**
	 * Asserts that a sealed full-shadow run retains its exact admitted candidate proof.
	 *
	 * @param int $run_id Incremental run id.
	 * @return void
	 * @throws \RuntimeException When the sealed run or its candidate is unavailable or inconsistent.
	 */
	public function assert_full_shadow_run_integrity( int $run_id ): void {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The coordinator verifies one plugin-owned sealed run and its admitted candidate proof before full shadow.
		$run = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE incremental_run_id = %d AND terminal_result = %s', $this->get_run_table(), $run_id, self::RESULT_FULL_SHADOW ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The coordinator verifies its one plugin-owned sealed run before handing it to ordinary full shadow.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$this->throw_if_database_error();
		if ( ! is_object( $run ) ) {
			throw new \RuntimeException( 'Sealed incremental full-shadow run is unavailable.' );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Candidate proof is plugin-owned state keyed by the admitted full-shadow import.
		$candidate = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE import_id = %d', $this->get_candidate_table(), (int) $run->admitted_import_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Candidate proof is plugin-owned state keyed by the admitted full-shadow import.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$this->throw_if_database_error();

		if ( ! is_object( $candidate )
			|| (int) $candidate->schema_level !== (int) $run->schema_level
			|| ! hash_equals( $candidate->configuration_signature, $run->configuration_signature )
			|| ! hash_equals( $candidate->source_snapshot_id, $run->source_snapshot_id )
			|| ! hash_equals( $candidate->snapshot_package_digest, $run->snapshot_package_digest )
			|| (int) $candidate->manifest_count !== (int) $run->snapshot_manifest_count
			|| ! hash_equals( $candidate->census_tag, $run->census_tag ) ) {
			throw new \RuntimeException( 'Incremental full-shadow candidate does not match its sealed run.' );
		}
	}

	/**
	 * Attempts to claim the no-op run's shared finalization exactly once.
	 *
	 * @param int $run_id Incremental run id.
	 * @return bool True only for the caller that stored the claim; false when the run is not eligible or was claimed already.
	 * @throws \RuntimeException When the claim cannot be persisted.
	 */
	public function try_claim_no_op_run_finalization( int $run_id ): bool {
		return $this->try_claim_run_finalization( $run_id, self::RESULT_NO_OP );
	}

	/**
	 * Claims shared finalization after a promoted availability overlay.
	 *
	 * @param int $run_id Incremental run id.
	 * @return bool True only for the caller that stored the claim.
	 */
	public function try_claim_delta_run_finalization( int $run_id ): bool {
		return $this->try_claim_run_finalization( $run_id, self::RESULT_DELTA );
	}

	/**
	 * Atomically claims shared success finalization for one eligible terminal run.
	 *
	 * @param int    $run_id Incremental run id.
	 * @param string $terminal_result Required terminal result for the claim.
	 * @return bool True only for the caller that recorded the claim.
	 * @throws \RuntimeException When the claim cannot be persisted.
	 */
	private function try_claim_run_finalization( int $run_id, string $terminal_result ): bool {
		$claimed = $this->wpdb->update(
			$this->get_run_table(),
			[
				'success_finalization_claimed_at' => gmdate( 'Y-m-d H:i:s' ),
				'modified'                        => gmdate( 'Y-m-d H:i:s' ),
			],
			[
				'incremental_run_id'              => $run_id,
				'terminal_result'                 => $terminal_result,
				'success_finalization_claimed_at' => null,
			]
		);
		if ( false === $claimed ) {
			$this->throw_database_error( 'Could not claim incremental no-op finalization.' );
		}

		return 1 === $claimed;
	}

	/**
	 * Deletes the disposable incremental analysis workspace while the supplied mutex token remains locked.
	 *
	 * @param string $lock_token Current import mutex token.
	 * @return array{manifest_rows: int, run_rows: int} Deleted analysis row counts.
	 * @throws \RuntimeException When ownership cannot be verified or the reset cannot commit.
	 */
	public function purge_all_analysis( string $lock_token ): array {
		if ( false === $this->wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The disposable analysis workspace must be deleted atomically.
			$this->throw_database_error( 'Could not start incremental analysis cleanup.' );
		}

		try {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The fixed plugin-owned analysis and mutex tables are accessed only inside this atomic cleanup transaction.
			$lock_row_id = $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT lock_row_id FROM %i WHERE lock_row_id = %d AND lock_token = %s AND lock_expired > UTC_TIMESTAMP() FOR UPDATE', $this->get_import_lock_table(), ImportMutex::LOCK_ROW_ID, $lock_token ) );
			$this->throw_if_database_error();
			if ( (int) $lock_row_id !== ImportMutex::LOCK_ROW_ID ) {
				throw new \RuntimeException( 'Import mutex ownership is unavailable for incremental analysis cleanup.' );
			}

			$manifest_rows = $this->wpdb->query( $this->wpdb->prepare( 'DELETE FROM %i', $this->get_snapshot_manifest_table() ) );
			if ( false === $manifest_rows ) {
				$this->throw_database_error( 'Could not delete incremental analysis manifests.' );
			}

			$run_rows = $this->wpdb->query( $this->wpdb->prepare( 'DELETE FROM %i', $this->get_run_table() ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( false === $run_rows ) {
				$this->throw_database_error( 'Could not delete incremental analysis runs.' );
			}

			if ( false === $this->wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup is complete only when both fixed-table deletes commit together.
				$this->throw_database_error( 'Could not commit incremental analysis cleanup.' );
			}

			return [
				'manifest_rows' => (int) $manifest_rows,
				'run_rows'      => (int) $run_rows,
			];
		} catch ( \Throwable $exception ) {
			$this->rollback_analysis();
			throw $exception;
		}
	}

	/**
	 * Returns bounded changed-tag data for support diagnostics.
	 *
	 * @param int $run_id Incremental run id.
	 * @param int $baseline_generation_id Expected compatible baseline generation.
	 * @param int $limit Maximum rows to return.
	 * @return array<int,object> Changed entries with baseline and target tag values.
	 * @throws \InvalidArgumentException When the requested diagnostic limit is invalid.
	 * @throws \RuntimeException When the comparison query fails.
	 */
	public function get_changed_tag_entries_data( int $run_id, int $baseline_generation_id, int $limit ): array {
		if ( $limit < 1 ) {
			throw new \InvalidArgumentException( 'Changed tag diagnostic limit is invalid.' );
		}

		$manifest_table = $this->get_snapshot_manifest_table();
		$baseline_table = $this->get_baseline_table();
		$query          = $this->wpdb->prepare(
			'SELECT
					target.entity_kind,
					target.entity_key_1,
					target.entity_key_2,
					target.entity_region_code,
					target.source_fragment_element_index,
					baseline.structural_tag AS baseline_structural_tag,
					target.structural_tag AS snapshot_structural_tag,
					baseline.availability_tag AS baseline_availability_tag,
					target.availability_tag AS snapshot_availability_tag
				FROM %i AS target
				INNER JOIN %i AS baseline ON
					baseline.baseline_generation_id = %d
					AND baseline.entity_kind = target.entity_kind
					AND baseline.entity_key_1 = target.entity_key_1
					AND baseline.entity_key_2 = target.entity_key_2
					AND baseline.entity_region_code = target.entity_region_code
				WHERE target.analysis_run_id = %d
					AND (
						NOT ( baseline.structural_tag <=> target.structural_tag )
						OR NOT ( baseline.availability_tag <=> target.availability_tag )
					)
				ORDER BY target.entity_kind, target.entity_key_1, target.entity_key_2, target.entity_region_code
				LIMIT %d',
			$manifest_table,
			$baseline_table,
			$baseline_generation_id,
			$run_id,
			$limit
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table identifiers and scalar values are prepared with fixed SQL placeholders.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $query is prepared above; support diagnostics read a bounded, indexed comparison over plugin-owned staged target and baseline tables.
		$entries = $this->wpdb->get_results( $query );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->throw_if_database_error();
		if ( ! is_array( $entries ) ) {
			throw new \RuntimeException( 'Changed tag diagnostic entries are unavailable.' );
		}

		return $entries;
	}

	/**
	 * Returns the plugin-owned singleton baseline-state table name for repository queries.
	 *
	 * @return string Fully qualified plugin-owned baseline state table.
	 */
	private function get_baseline_state_table(): string {
		return $this->wpdb->prefix . 'arlo_incremental_baseline_state';
	}

	/**
	 * Returns the plugin-owned incremental-run table name for repository queries.
	 *
	 * @return string Fully qualified plugin-owned run table.
	 */
	private function get_run_table(): string {
		return $this->wpdb->prefix . 'arlo_incremental_runs';
	}

	/**
	 * Returns the plugin-owned target-manifest staging table name for repository queries.
	 *
	 * @return string Fully qualified plugin-owned target manifest table.
	 */
	private function get_snapshot_manifest_table(): string {
		return $this->wpdb->prefix . 'arlo_snapshot_manifest';
	}

	/**
	 * Returns the fixed import-mutex table name for transaction ownership checks.
	 *
	 * @return string Fully qualified plugin-owned import lock table.
	 */
	private function get_import_lock_table(): string {
		return $this->wpdb->prefix . 'arlo_import_lock';
	}

	/**
	 * Returns the plugin-owned baseline-projection table name for repository queries.
	 *
	 * @return string Fully qualified plugin-owned baseline projection table.
	 */
	private function get_baseline_table(): string {
		return $this->wpdb->prefix . 'arlo_incremental_baseline';
	}

	/**
	 * Returns the plugin-owned full-shadow candidate-proof table name for repository queries.
	 *
	 * @return string Fully qualified plugin-owned full-shadow candidate table.
	 */
	private function get_candidate_table(): string {
		return $this->wpdb->prefix . 'arlo_incremental_full_baseline_candidates';
	}

	/**
	 * Raises a database exception when WordPress retained an error from the preceding query.
	 *
	 * @return void
	 * @throws \RuntimeException When WordPress reported a database error.
	 */
	private function throw_if_database_error(): void {
		if ( isset( $this->wpdb->last_error ) && '' !== $this->wpdb->last_error ) {
			$this->throw_database_error( 'Incremental run database operation failed.' );
		}
	}

	/**
	 * Throws a cause-bearing exception for an unsuccessful repository database operation.
	 *
	 * @param string $operation Failed database operation.
	 *
	 * @return void
	 * @throws \RuntimeException Always, with the WordPress database error when available.
	 */
	private function throw_database_error( string $operation ): void {
		$error   = isset( $this->wpdb->last_error ) ? (string) $this->wpdb->last_error : '';
		$message = '' === $error ? $operation : $operation . ' ' . $error;

		throw new \RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
	}

	/**
	 * @return void
	 * @throws \RuntimeException When the analysis transaction cannot be rolled back.
	 */
	private function rollback_analysis(): void {
		if ( false === $this->wpdb->query( 'ROLLBACK' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Roll back only the coordinator's plugin-owned analysis transaction after an incomplete decision.
			$this->throw_database_error( 'Could not roll back incremental run analysis.' );
		}
	}
}
