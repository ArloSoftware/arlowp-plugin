<?php
namespace ArloTraining;

use ArloTraining\Importer\ImportMutex;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Drives the fragmented, self-kicking import pipeline: selects the next
 * `arlo_async_tasks` row to work on, guards against a second import starting
 * concurrently, runs one subtask, and re-triggers itself until the import
 * finishes or fails.
 *
 * Remarks:
 * - See the `task_status` / `task_priority` legend below for the integer
 *   literals used throughout this class.
 * - Concurrency is guarded in layers: `set_task_unique()` uses a short
 *   options-table mutex to prevent duplicate automatic import rows during
 *   enqueue; `get_next_scheduled_tasks()` / `get_next_immediate_tasks()` /
 *   `should_defer_new_task_start()` stop a second import from starting while
 *   one is in-flight (status 1 paused or 2 running); the network-wide process
 *   lock (`lock_process()`) stops two requests from executing a subtask's
 *   synchronous code at the same instant; the importer's DB mutex token gates
 *   checkpoint writes while that subtask runs.
 * - Subtask-to-subtask progression is driven by `kick_off_scheduler()`
 *   (healthy path) or `kick_off_scheduler_with_backoff()` (failure/retry
 *   path), each a non-blocking request this site sends to its own
 *   `admin-ajax.php`.
 */
#[\AllowDynamicProperties]
class Scheduler {

	/**
	 * Maximum pacing delay, in seconds, between processing successive async
	 * tasks. Caps the admin-configurable `sleep_between_import_tasks`
	 * setting — a compatibility delay, not a storm-control mechanism.
	 *
	 * @since   5.2.0
	 */
	const MAX_SLEEP_BETWEEN_TASKS_SECONDS = 2;

	/**
	 * Legacy alias for the maximum scheduler pacing delay.
	 *
	 * @since   2.4.1
	 * @deprecated 5.2.0 Use `MAX_SLEEP_BETWEEN_TASKS_SECONDS` instead.
	 */
	const MAX_SLEEP_BETWEEN_TASKS = self::MAX_SLEEP_BETWEEN_TASKS_SECONDS;

	/**
	 * Escalating delay, in seconds, applied before re-triggering the scheduler
	 * after a failed import subtask. Indexed by consecutive retry attempt
	 * (attempt 1 uses the first entry). The final entry is reused for any
	 * attempt beyond the length of the ladder.
	 *
	 * @since   5.2.0
	 */
	const RETRY_BACKOFF_SECONDS = array( 5, 15, 30, 60, 120 );

	/**
	 * Distinguishing arguments for the single-event retry backoff wakeup.
	 *
	 * Deliberately a plain positional array (no string keys) — `call_user_func_array()`
	 * treats string-keyed array entries as named arguments as of PHP 8.1. The
	 * `arlo_scheduler` hook is registered with accepted_args=0, so these values
	 * distinguish cron events without being forwarded to `run_scheduler_queue()`.
	 *
	 * @since   5.2.0
	 */
	const RETRY_BACKOFF_CRON_ARGS = array( 1 );

	/**
	 * Distinguishing arguments for the short safety-net after an immediate self-kick.
	 *
	 * The normal loopback request is non-blocking and can be lost before the next
	 * scheduler request reaches WordPress. This single event bounds that gap without
	 * waiting for the coarse recurring five-minute scheduler event.
	 *
	 * @since   5.2.0
	 */
	const IMMEDIATE_FALLBACK_CRON_ARGS = array( 2 );

	/**
	 * Distinguishing arguments for the process-lock-contention retry wakeup.
	 *
	 * Must differ from the recurring safety net, failure-retry backoff, and
	 * immediate fallback events so each scheduler wakeup type is keyed separately.
	 *
	 * @since   5.2.0
	 */
	const LOCK_BUSY_RETRY_CRON_ARGS = array( 3 );

	/** Delay, in seconds, before the immediate self-kick fallback may resume a paused task. */
	const IMMEDIATE_FALLBACK_DELAY_SECONDS = 30;

	/** Minimum jittered delay, in seconds, before retrying after a busy process lock. */
	const LOCK_BUSY_RETRY_MIN_SECONDS = 3;

	/** Maximum jittered delay, in seconds, before retrying after a busy process lock. */
	const LOCK_BUSY_RETRY_MAX_SECONDS = 8;

	/**
	 * Maximum age, in seconds, a held process lock may reach before the
	 * stale-lock safety net treats it as abandoned and forcibly releases it.
	 *
	 * Set to 28 minutes to match the stalled-task window enforced by
	 * `Arlo_For_Wordpress::fail_stalled_or_overlong_import_tasks()`, so the two backstops agree on when
	 * an in-flight import is considered stalled rather than introducing a second,
	 * differently-tuned threshold.
	 *
	 * Remarks:
	 * - Deliberately far longer than the lock's own 180s transient TTL (see
	 *   `lock_process()`): the lock is only ever held for a single subtask, so
	 *   the TTL always expires first and this net can never engage on the happy
	 *   path — it exists only to reclaim a lock left behind by a request that
	 *   crashed before reaching `unlock_process()`.
	 * - Widening this same lock to span a full import lifecycle (which would
	 *   have made this net load-bearing on a much wider window) was considered
	 *   and deliberately not taken; the lifecycle guard actually used instead is
	 *   `should_defer_new_task_start()` / `get_next_scheduled_tasks()`. This net
	 *   remains the narrow, rarely-engaged backstop described above.
	 *
	 * @since   5.2.0
	 */
	const PROCESS_LOCK_MAX_AGE_SECONDS = 28 * MINUTE_IN_SECONDS;

	/**
	 * Maximum age, in seconds, for a short task-enqueue mutex.
	 *
	 * The lock is held only around the outstanding-task check and INSERT. The TTL
	 * is a recovery path if PHP dies between acquiring and releasing it.
	 *
	 * @since   5.2.0
	 */
	const TASK_ENQUEUE_LOCK_TTL_SECONDS = 60;

	/*
	 * Async task state model (arlo_async_tasks table). These literals appear as
	 * bare integers throughout this class (e.g. update_task($id, 3)); this is the
	 * single legend for them.
	 *
	 * task_status:   0 = scheduled  (queued, not yet started)
	 *                1 = paused     (import in progress, waiting for the next self-kick)
	 *                2 = running    (a subtask is executing in this request right now)
	 *                3 = failed     (errored, or cancelled/terminated as a failure)
	 *                4 = completed  (finished successfully, skipped, or terminated)
	 *
	 * task_priority: 0 = normal scheduled sync
	 *               -1 = immediate/forced sync, jumps the queue ahead of normal scheduled
	 *                    tasks — used not just for the admin's manual "sync now" action, but
	 *                    also a platform connection change, a schema rebuild, and version-
	 *                    upgrade migrations that force a fresh import
	 */

	// Only one import task may execute at a time; the whole pipeline assumes serial execution.
	private $max_simultaneous_task = 1;
	private $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Counts tasks actively executing right now.
	 *
	 * Status 2 represents the narrow request window where a subtask is running.
	 * It does not include paused in-flight imports waiting for the next wakeup.
	 *
	 * @return  int Number of running tasks.
	 */
	private function get_running_tasks_count() {
		global $wpdb;
		// Counts only actively-executing tasks (status 2) — one mid-subtask this instant.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is static and safe. No caching needed for import process , low-frequency operations with high-frequency data updates. Direct database query is required for custom table.
		$result = $wpdb->get_results(
			"
		SELECT
			COUNT(1) AS num
		FROM
			{$wpdb->prefix}arlo_async_tasks
		WHERE
			task_status = 2
		"
		);

		return $result[0]->num;
	}

	/**
	 * Counts every task currently occupying the in-flight slot.
	 *
	 * Status 1 is paused between self-kicks and status 2 is executing in the
	 * current request. New task selection gates on both states; queued status 0
	 * rows are non-terminal but have not yet entered the in-flight slot.
	 *
	 * @return  int Number of paused or running tasks.
	 */
	private function get_running_paused_tasks_count() {
		global $wpdb;
		// Counts every in-flight task (status 1 paused + 2 running) — i.e. a task that
		// has started but not finished, whether or not work is executing right now.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is static and safe. No caching needed for data import process, low-frequency operations with high-frequency data updates. Direct database query is required for custom table.
		$result = $wpdb->get_results(
			"
		SELECT
			COUNT(1) AS num
		FROM
			{$wpdb->prefix}arlo_async_tasks
		WHERE
			task_status IN (1,2)
		"
		);

		return $result[0]->num;
	}

	/**
	 * Queues a task row without checking for an existing active task.
	 *
	 * This is the low-level insert helper. Scheduled automatic imports should use
	 * `set_task_unique()` so concurrent cron ticks cannot create duplicate import
	 * rows; forced/admin paths may still call this when they intentionally queue a
	 * separate task.
	 *
	 * @param   string  $task     Task key to queue.
	 * @param   int     $priority Task priority.
	 * @return  int|false New task id on insert; false when the task is empty or insert fails.
	 */
	public function set_task( $task = '', $priority = 0 ) {
		global $wpdb;
		if ( empty( $task ) ) {
			return false;
		}
		$utc_date         = gmdate( "Y-m-d H:i:s" );
		$task_status_text = $task === 'import' ? 'Import is queued' : 'Task is queued';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Import related queries do not need caching. Direct database query is required for custom table.
		$query = $wpdb->query(
			$wpdb->prepare(
				"
		INSERT INTO
			{$wpdb->prefix}arlo_async_tasks (task_priority, task_task, task_status_text, task_created)
		VALUES
			(%d, %s, %s, %s)
		",
				$priority,
				$task,
				$task_status_text,
				$utc_date
			)
		);

		if ( $query ) {
			return $wpdb->insert_id;
		} else {
			return false;
		}
	}

	/**
	 * Queues a task only when no existing task with the given active statuses exists.
	 *
	 * Uses a short DB-backed mutex before checking and inserting so two concurrent
	 * enqueue attempts cannot both observe an empty queue and create duplicate rows.
	 *
	 * @since   5.2.0
	 * @param   string  $task            Task key to queue.
	 * @param   int     $priority        Task priority.
	 * @param   array   $active_statuses Status values that make an existing task active.
	 * @return  int|false New task id on insert; false when the task already exists or cannot be queued.
	 */
	public function set_task_unique( $task = '', $priority = 0, array $active_statuses = array( 0, 1, 2 ) ) {
		if ( empty( $task ) ) {
			return false;
		}

		$lock_value = $this->acquire_task_enqueue_lock( $task );
		if ( $lock_value === false ) {
			return false;
		}

		try {
			if ( $this->has_task_with_statuses( $task, $active_statuses ) ) {
				return false;
			}

			return $this->set_task( $task, $priority );
		} finally {
			$this->release_task_enqueue_lock( $task, $lock_value );
		}
	}

	/**
	 * Whether a task has any queued, paused, or running row.
	 *
	 * @since   5.2.0
	 * @param   string  $task Task key to check.
	 * @return  bool True when a non-terminal row exists.
	 */
	public function has_non_terminal_task( $task = '' ): bool {
		return $this->has_task_with_statuses( $task, array( 0, 1, 2 ) );
	}

	/**
	 * Updates a scheduler task row regardless of its current terminal state.
	 *
	 * This is the low-level status update helper. Importer-owned progress writes
	 * should use `update_task_if_active()` so stale requests cannot revive a task
	 * already failed or completed by another path.
	 *
	 * @param   int       $task_id          Task id to update.
	 * @param   int|null  $task_status      New task status, or null to leave SQL expression unchanged.
	 * @param   string    $task_status_text Optional status text.
	 * @return  void
	 */
	public function update_task( $task_id = 0, $task_status = null, $task_status_text = '' ) {
		global $wpdb;
		$task_status      = ( is_null( $task_status ) ? 'task_status' : intval( $task_status ) ); //actually task_stauts will never be null.
		$no_status_text   = empty( $task_status_text );
		$task_status_text = ( $no_status_text ? 'task_status_text' : $task_status_text );
		$utc_date         = gmdate( "Y-m-d H:i:s" );

		$parameter = [ $task_status ];

		$task_status_text_sql = $no_status_text ? '' : 'task_status_text = %s,';
		if ( ! $no_status_text ) {
			$parameter[] = $task_status_text;
		}
		$parameter[] = $utc_date;
		$parameter[] = intval( $task_id );

		$sql = "
		UPDATE
			{$wpdb->prefix}arlo_async_tasks
		SET
			task_status = %d,
			$task_status_text_sql
			task_modified = %s
		WHERE
			task_id = %d
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The SQL statement is dynamically constructed and parameters are prepared here. Import related queries do not need caching. Direct database query is required for custom table. No caching needed for data import process
		$query = $wpdb->query( Utilities::prepare_sql( $sql, $parameter ) );
	}

	/**
	 * Updates a task only while it is still non-terminal.
	 *
	 * Used by the importer after ownership checks so a stale request cannot revive a
	 * task that an admin cancellation or another request has already failed/completed.
	 *
	 * @param int         $task_id          Task id to update.
	 * @param int         $task_status      New task status.
	 * @param string      $task_status_text Optional status text.
	 * @return bool True when the task row was updated or was already in the requested active state.
	 */
	public function update_task_if_active( $task_id, int $task_status, $task_status_text = '' ): bool {
		global $wpdb;
		$task_status    = intval( $task_status );
		$no_status_text = empty( $task_status_text );
		$utc_date       = gmdate( "Y-m-d H:i:s" );

		$parameter = [ $task_status ];

		$task_status_text_sql = $no_status_text ? '' : 'task_status_text = %s,';
		if ( ! $no_status_text ) {
			$parameter[] = $task_status_text;
		}
		$parameter[] = $utc_date;
		$parameter[] = intval( $task_id );

		$sql = "
		UPDATE
			{$wpdb->prefix}arlo_async_tasks
		SET
			task_status = %d,
			$task_status_text_sql
			task_modified = %s
		WHERE
			task_id = %d
		AND
			task_status NOT IN (3, 4)
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The SQL statement is dynamically constructed and parameters are prepared here. Import related queries do not need caching. Direct database query is required for custom table. No caching needed for data import process.
		$query = $wpdb->query( Utilities::prepare_sql( $sql, $parameter ) );

		if ( $query === false ) {
			return false;
		}

		if ( intval( $query ) > 0 ) {
			return true;
		}

		// MySQL reports 0 affected rows when a paused task is marked paused again within
		// the same second and no column value changes. That is still a valid active task;
		// only terminal tasks must reject the caller so a stale request cannot revive them.
		return ! $this->is_task_terminal( $task_id );
	}
	/**
	 * Writes arbitrary JSON task data for a scheduler task.
	 *
	 * This is the low-level data write helper. Import checkpoint writes that depend
	 * on live mutex ownership should use `update_task_data_if_mutex_owned()` instead.
	 *
	 * @param   int    $task_id        Task id whose data should be written.
	 * @param   array  $data           Data fragment to write or merge.
	 * @param   bool   $overwrite_data Whether to replace existing data instead of merging it.
	 * @return  void
	 */
	public function update_task_data( $task_id, $data = array(), $overwrite_data = false ) {
		global $wpdb;
		if ( ! $overwrite_data ) {
			$task = $this->get_task_data( $task_id );

			$task_data = ( ! empty( $task[0]->task_data_text ) ? json_decode( $task[0]->task_data_text, true ) : [] );
			$task_data = is_array( $task_data ) ? $task_data : [];
			$data      = array_replace_recursive( $task_data, $data );
		}
		$data = wp_json_encode( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed for data import process, low-frequency operations with high-frequency data updates. Direct database query is required for custom table.
		$query = $wpdb->query(
			$wpdb->prepare(
				"
		INSERT INTO
			{$wpdb->prefix}arlo_async_task_data
		SET
			data_task_id = %d,
			data_text = %s
		ON DUPLICATE KEY UPDATE
			data_text = %s
		",
				$task_id,
				$data,
				$data
			)
		);
	}

	/**
	 * Clears only the short immediate fallback event for the current scheduler wakeup.
	 *
	 * The importer calls this after acquiring the DB mutex, before it can schedule the
	 * next fallback for successful progress. DB-mutex losers must leave this event armed.
	 *
	 * @return void
	 */
	public function clear_immediate_fallback_cron(): void {
		wp_clear_scheduled_hook( 'arlo_scheduler', self::IMMEDIATE_FALLBACK_CRON_ARGS );
	}

	/**
	 * Writes checkpoint data only if the caller still owns the import mutex token.
	 *
	 * The ownership predicate is inside the INSERT...SELECT so the checkpoint and
	 * token check are one database decision, not a PHP check followed by a stale write.
	 *
	 * @param int         $task_id Task id whose checkpoint should be written.
	 * @param array       $data    Checkpoint fragment to merge with existing task data.
	 * @param ImportMutex $mutex   Mutex instance owned by the current importer request.
	 * @return bool True when the write succeeded or ownership was still confirmed after a no-op.
	 */
	public function update_task_data_if_mutex_owned( $task_id, $data, ImportMutex $mutex ): bool {
		global $wpdb;

		$task = $this->get_task_data( $task_id );
		if ( empty( $task ) ) {
			return false;
		}

		$task_data = ( ! empty( $task[0]->task_data_text ) ? json_decode( $task[0]->task_data_text, true ) : [] );
		$task_data = is_array( $task_data ) ? $task_data : [];
		$data      = array_replace_recursive( $task_data, $data );
		$data      = wp_json_encode( $data );

		$sql = "
		INSERT INTO
			{$wpdb->prefix}arlo_async_task_data (data_task_id, data_text)
		SELECT
			incoming.data_task_id,
			incoming.data_text
		FROM
			(SELECT %d AS data_task_id, %s AS data_text) AS incoming
		WHERE EXISTS (
			SELECT 1
			FROM {$wpdb->prefix}arlo_import_lock
			WHERE lock_row_id = %d
			AND lock_token = %s
			AND lock_expired > UTC_TIMESTAMP()
		)
		ON DUPLICATE KEY UPDATE
			data_text = incoming.data_text
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Token-conditional cross-table checkpoint write must run as a single direct SQL statement; table names are plugin-owned and parameters are prepared here.
		$query = $wpdb->query( Utilities::prepare_sql( $sql, [ intval( $task_id ), $data, ImportMutex::LOCK_ROW_ID, $mutex->get_token() ] ) );

		if ( $query === false ) {
			return false;
		}

		if ( intval( $query ) === 0 ) {
			// A duplicate update can be a no-op when JSON is unchanged; confirm before treating it as loss.
			return $mutex->confirm_ownership();
		}

		return true;
	}

	/**
	 * Checks whether a task is already failed or completed.
	 *
	 * @param int $task_id Task id to inspect.
	 * @return bool True when task_status is 3 or 4, or the task row no longer exists.
	 */
	public function is_task_terminal( $task_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct task status read is required for import ownership checkpoints; no caching applies.
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT task_status FROM {$wpdb->prefix}arlo_async_tasks WHERE task_id = %d",
				intval( $task_id )
			)
		);

		if ( is_null( $status ) ) {
			return true;
		}

		return in_array( intval( $status ), [ 3, 4 ], true );
	}

	/**
	 * Whether the scheduler has capacity to start or resume a task.
	 *
	 * @since    5.2.0
	 *
	 * @return   bool True when the number of running tasks is below the scheduler's
	 *                simultaneous task limit.
	 */
	public function has_available_task_slot() {
		return $this->max_simultaneous_task > $this->get_running_tasks_count();
	}

	/**
	 * Legacy alias for `has_available_task_slot()`.
	 *
	 * @since      2.4.1
	 * @deprecated 5.2.0 Use `has_available_task_slot()` instead.
	 *
	 * @return     bool True when the scheduler has task capacity available.
	 */
	public function check_empty_slot_for_task() {
		return $this->has_available_task_slot();
	}

	/**
	 * Whether there is a scheduler task waiting to be processed.
	 *
	 * @since    5.2.0
	 *
	 * @return   bool True when `get_next_task()` can return work for the scheduler.
	 */
	public function has_waiting_task() {
		return ! empty( $this->get_next_task() );
	}

	/**
	 * Selects the next scheduler task to process, preferring to resume an
	 * in-flight import over starting a new one.
	 *
	 * Selection order:
	 * 1. Resume a paused (in-flight) import, if one exists.
	 * 2. Otherwise start a forced/immediate "sync now" task — but only when no
	 *    import is currently in-flight.
	 * 3. Otherwise start the next ordinary scheduled task — again only when no
	 *    import is currently in-flight.
	 *
	 * Steps 2 and 3 are both gated on there being no in-flight import at the
	 * moment of selection, so a second trigger (a cron tick, a manual sync, or a
	 * race between self-kicks) cannot pick a new task while one is already
	 * running or paused between self-kicks. Resuming the already-in-flight task
	 * (step 1) is deliberately never gated — it is the one import allowed to
	 * make progress.
	 *
	 * Remarks:
	 * - Delegates to `get_next_paused_tasks()`, `get_next_immediate_tasks()`,
	 *   and `get_next_scheduled_tasks()`; the latter two share the
	 *   `get_running_paused_tasks_count()` in-flight gate (tasks at status 1
	 *   paused or 2 running).
	 * - This gate is necessary but not sufficient on its own: the task row it
	 *   returns is a snapshot, and under real concurrency another request can
	 *   change the in-flight state before `process_task()` acts on it.
	 *   `process_task()`'s own `should_defer_new_task_start()` re-checks
	 *   immediately before starting a selected new task, closing that window.
	 *
	 * @since    2.4.1
	 *
	 * @return   array  The selected single-element task row set, or an empty
	 *                  array when there is no eligible task to process.
	 */
	public function get_next_task() {
		// Priority order: resume an in-progress import before starting anything new —
		// 1) a paused (in-flight) task, then 2) a forced/immediate "sync now", then
		// 3) the next normal scheduled task.
		$task = $this->get_next_paused_tasks();

		if ( empty( $task ) ) {
			$task = $this->get_next_immediate_tasks();
			if ( empty( $task ) ) {
				$task = $this->get_next_scheduled_tasks();
			}
		}

		return $task;
	}

	/**
	 * Returns a scheduler task by id, including its associated JSON data row.
	 *
	 * @param   int|null  $task_id Task id to fetch.
	 * @return  array Task rows matching the id, or an empty array.
	 */
	public function get_task_data( $task_id = null ) {
		return $this->get_tasks( null, null, $task_id, 1 );
	}

	/**
	 * Returns tasks currently executing in this request window.
	 *
	 * @return  array Running task rows.
	 */
	public function get_running_tasks() {
		return $this->get_tasks( 2 );
	}

	/**
	 * Returns paused in-flight tasks waiting for a scheduler wakeup.
	 *
	 * @return  array Paused task rows.
	 */
	public function get_paused_tasks() {
		return $this->get_tasks( 1 );
	}

	public function has_failed_scheduled_import_since( $utc_date ): bool {
		global $wpdb;

		$utc_date = is_scalar( $utc_date ) ? trim( (string) $utc_date ) : '';
		if ( $utc_date === '' ) {
			return false;
		}

		$sql = "
		SELECT
			COUNT(1)
		FROM
			{$wpdb->prefix}arlo_async_tasks
		WHERE
			task_task = %s
		AND
			task_priority = %d
		AND
			task_status = %d
		AND
			COALESCE(task_modified, task_created) > %s
		";

		$result = $wpdb->get_var( $wpdb->prepare( $sql, 'import', 0, 3, $utc_date ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is a static parameterised string; import task state must be read directly from the async task table.

		return intval( $result ) > 0;
	}


	/**
	 * Returns the next forced/immediate ("sync now") import task, but only when
	 * no import is currently in-flight.
	 *
	 * A forced task (priority -1) jumps ahead of ordinary scheduled tasks, but
	 * does not jump ahead of an import that is already running or paused
	 * between self-kicks — it defers until the in-flight import reaches a
	 * terminal state. A forced sync therefore still bypasses the import-interval
	 * throttle and the soft health/user gates, but never starts a second
	 * concurrent import.
	 *
	 * Remarks:
	 * - Gated on `get_running_paused_tasks_count()` (tasks at status 1 paused or
	 *   2 running) being zero.
	 *
	 * @return   array  The next immediate task row set (single element), or an
	 *                  empty array when an import is already in-flight or no
	 *                  immediate task is waiting.
	 */
	public function get_next_immediate_tasks() {
		if ( $this->get_running_paused_tasks_count() == 0 ) {
			return $this->get_tasks( 0, -1, null, 1 );
		}
		return [];
	}

	/**
	 * Returns the next ordinary scheduled import task, but only when no import
	 * is currently in-flight.
	 *
	 * A scheduled task (priority 0) defers, exactly like a forced task, until
	 * any in-flight import reaches a terminal state.
	 *
	 * Remarks:
	 * - Mirrors `get_next_immediate_tasks()`: both begin a brand-new import and
	 *   are gated on `get_running_paused_tasks_count()` (tasks at status 1
	 *   paused or 2 running) being zero.
	 * - Scheduled starts use the same status 1/2 in-flight gate as forced starts,
	 *   so a new automatic task cannot be selected while another import is paused
	 *   between self-kicks or executing a subtask.
	 *
	 * @since    5.2.0
	 *
	 * @return   array  The next scheduled task row set (single element), or an
	 *                  empty array when an import is already in-flight or no
	 *                  scheduled task is waiting.
	 */
	public function get_next_scheduled_tasks() {
		if ( $this->get_running_paused_tasks_count() == 0 ) {
			return $this->get_tasks( 0, 0, null, 1 );
		}
		return [];
	}

	/**
	 * Returns the paused (in-flight) import task waiting to resume, if any.
	 *
	 * This is the one task-selection path that is intentionally never gated by
	 * the in-flight count: a paused task (status 1) is the import currently in
	 * progress between self-kicks, so resuming it is exactly what must be
	 * allowed to happen. Gating it would deadlock the fragmented import loop.
	 *
	 * @return   array  The paused task row set (single element), or an empty
	 *                  array when no import is paused.
	 */
	public function get_next_paused_tasks() {
		return $this->get_tasks( 1, null, null, 1 );
	}

	/**
	 * Queries scheduler tasks with optional status, priority, id, limit, and data filters.
	 *
	 * This is the central task-read helper used by task selection, cleanup, and
	 * import callback recovery. Returned rows include joined task data as
	 * `task_data_text` when present.
	 *
	 * @param   int|array|null  $status         Task status or statuses to match.
	 * @param   int|null        $priority       Task priority to match.
	 * @param   int|null        $task_id        Task id to match.
	 * @param   int|null        $limit          Maximum number of rows to return.
	 * @param   string          $task_data_text Optional SQL LIKE fragment for task data.
	 * @return  array Matching task rows.
	 */
	public function get_tasks( $status = null, $priority = null, $task_id = null, $limit = null, $task_data_text = '' ) {
		global $wpdb;
		$has_status_filter = is_array( $status );
		$task_id           = ( isset( $task_id ) && is_numeric( $task_id ) ? $task_id : null );
		$status            = ( isset( $status ) && is_numeric( $status ) ? [ $status ] : ( is_array( $status ) ? array_filter(
			$status,
			function ( $status_value ) {
				return is_numeric( $status_value );
			}
		) : null ) );
		$priority          = ( isset( $priority ) && is_numeric( $priority ) ? $priority : null );
		$limit             = ( isset( $limit ) && is_numeric( $limit ) ? $limit : null );
		$task_data_text    = ( ! empty( $task_data_text ) ? $task_data_text : null );

		if ( $has_status_filter && empty( $status ) ) {
			return array();
		}

		$sql = "
		SELECT
			task_id,
			task_task,
			task_status,
			task_priority,
			task_status_text,
			task_created,
			task_modified,
			data_text AS task_data_text
		FROM
			{$wpdb->prefix}arlo_async_tasks
		LEFT JOIN
			{$wpdb->prefix}arlo_async_task_data
		ON
			data_task_id = task_id
		WHERE
			1
			" . ( ! is_null( $status ) ? "AND task_status IN (" . implode( ',', array_fill( 0, count( $status ), '%d' ) ) . ")" : "" ) . "
			" . ( ! is_null( $priority ) ? "AND task_priority = %d" : "" ) . "
			" . ( ! is_null( $task_id ) ? "AND task_id = %d" : "" ) . "
			" . ( ! is_null( $task_data_text ) ? "AND data_text like %s" : "" ) . "
		ORDER BY
			task_priority,
			task_created
		" . ( ! is_null( $limit ) ? "LIMIT %d" : "" );

		$parameter = [];
		if ( ! is_null( $status ) ) {
			$parameter = array_merge( $parameter, $status );
		}
		if ( ! is_null( $priority ) ) {
			$parameter[] = $priority;
		}
		if ( ! is_null( $task_id ) ) {
			$parameter[] = $task_id;
		}
		if ( ! is_null( $task_data_text ) ) {
			$parameter[] = '%' . $task_data_text . '%';
		}
		if ( ! is_null( $limit ) ) {
			$parameter[] = $limit;
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is dynamically constructed and parameters are prepared here. Import related queries do not need caching. Direct database query is required for custom table.
		$result = $wpdb->get_results( Utilities::prepare_sql( $sql, $parameter ) );

		return $result;
	}

	/**
	 * Finds an active import task by exactly matching the decoded task-state import id.
	 *
	 * get_tasks() can only filter task data with SQL LIKE, which is unsafe for import ids
	 * because they are numeric substrings inside JSON. Callback error handling needs an
	 * exact state match so a late/stale callback cannot fail another active import whose
	 * task data merely contains the same digits elsewhere.
	 *
	 * @param int|string $import_id Import id from the callback's import entry.
	 * @return array Single matching task row, or an empty array when no exact active match exists.
	 */
	public function get_active_task_by_import_id( $import_id ): array {
		if ( ! is_scalar( $import_id ) || $import_id === '' ) {
			return array();
		}

		$tasks = $this->get_tasks( array( 1, 2 ) );
		foreach ( $tasks as $task ) {
			if ( empty( $task->task_data_text ) ) {
				continue;
			}

			$state = json_decode( $task->task_data_text );
			if ( ! is_object( $state ) || ! isset( $state->import_id ) || ! is_scalar( $state->import_id ) ) {
				continue;
			}

			if ( (string) $state->import_id === (string) $import_id ) {
				return array( $task );
			}
		}

		return array();
	}

	public function delete_running_tasks() {
		return $this->delete_tasks( 2 );
	}

	public function delete_paused_tasks() {
		return $this->delete_tasks( 1 );
	}

	private function delete_tasks( $status = null, $priority = null, $task_id = null, $limit = null ) {
		global $wpdb;
		$has_status_filter = is_array( $status );
		$task_id           = ( isset( $task_id ) && is_numeric( $task_id ) ? $task_id : null );
		$status            = ( isset( $status ) && is_numeric( $status ) ? [ $status ] : ( is_array( $status ) ? array_filter( $status, 'is_numeric' ) : null ) );
		$priority          = ( isset( $priority ) && is_numeric( $priority ) ? $priority : null );
		$limit             = ( isset( $limit ) && is_numeric( $limit ) ? $limit : null );

		if ( $has_status_filter && empty( $status ) ) {
			return false;
		}

		$sql = "
		DELETE tasks, tasks_data FROM
			{$wpdb->prefix}arlo_async_tasks AS tasks
		LEFT JOIN
			{$wpdb->prefix}arlo_async_task_data AS tasks_data
		ON
			data_task_id = task_id
		WHERE
			1
			" . ( ! is_null( $status ) ? "AND task_status IN (" . implode( ',', array_fill( 0, count( $status ), '%d' ) ) . ")" : "" ) . "
			" . ( ! is_null( $priority ) ? "AND task_priority = %d" : "" ) . "
			" . ( ! is_null( $task_id ) ? "AND task_id = %d" : "" ) . "
		" . ( ! is_null( $limit ) ? "LIMIT %d" : "" );

		$parameter = [];
		if ( ! is_null( $status ) ) {
			$parameter = array_merge( $parameter, $status );
		}
		if ( ! is_null( $priority ) ) {
			$parameter[] = $priority;
		}
		if ( ! is_null( $task_id ) ) {
			$parameter[] = $task_id;
		}
		if ( ! is_null( $limit ) ) {
			$parameter[] = $limit;
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is dynamically constructed and parameters are prepared here. Import related queries do not need caching. Direct database query is required for custom table
		return $wpdb->get_results( Utilities::prepare_sql( $sql, $parameter ) );
	}

	/**
	 * Runs either the selected task id or the next eligible scheduler task.
	 *
	 * @param int|null $task_id Optional task id to process directly.
	 * @param string   $source  Scheduler wake source for support diagnostics.
	 * @return void
	 */
	public function run_task( $task_id = null, $source = 'unknown' ) {
		$task_id = ( ! empty( $task_id ) && is_numeric( $task_id ) ? $task_id : null );
		if ( $this->has_available_task_slot() ) {

			$task = ! is_null( $task_id ) ? $this->get_task_data( $task_id ) : $this->get_next_task();
			$this->process_task( $task, $source );
		}
	}

	/**
	 * Marks the selected active import task and later active import tasks as terminal.
	 *
	 * This does not force-release the DB import mutex. A running request observes the
	 * terminal task row and stops cooperatively without another request stealing its lock.
	 *
	 * @param int $task_id First active import task id to terminate, inclusive.
	 * @return int|bool Number of affected rows, false when no valid task id is supplied.
	 */
	public function terminate_active_import_tasks_from( $task_id ) {
		global $wpdb;
		$task_id = ( isset( $task_id ) && is_numeric( $task_id ) ? $task_id : null );

		if ( $task_id > 0 ) {
			$task = $this->get_task_data( $task_id );
			if ( empty( $task[0] ) || ( $task[0]->task_task ?? '' ) !== 'import' || ! in_array( intval( $task[0]->task_status ?? -1 ), [ 0, 1, 2 ], true ) ) {
				return false;
			}

			// Terminating one import also drains later queued import rows, otherwise the
			// next queued import can restart immediately. Keep this broader termination
			// scoped to active import rows so future scheduler task types are not cancelled.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is static and safe. No caching needed for import process , low-frequency operations with high-frequency data updates. Direct database query is required for custom table
			$query = $wpdb->query(
				$wpdb->prepare(
					"
			UPDATE
				{$wpdb->prefix}arlo_async_tasks AS tasks
			SET
				task_status = 4,
				task_status_text = 'Import task terminated'
			WHERE
				task_id >= %d
			AND
				task_task = %s
			AND
				task_status IN (0, 1, 2)
			",
					$task_id,
					'import'
				)
			);

			// Only the process lock is cleared here; the token mutex belongs to any live request.
			$this->unlock_process( "import" );
			if ( $query !== false && (int) $query > 0 ) {
				$this->log_terminated_import_task( $task[0] ?? null );
			}

			return $query;
		}

		return false;
	}

	/**
	 * Marks every active import task as terminal, whatever its position in the queue.
	 *
	 * Used when stored import checkpoints can no longer be resumed, such as after a release that changes the
	 * importer's persisted state. Like the single-task termination, this is cooperative: the DB import mutex is
	 * left to any live request, which observes the terminal row and stops. Unlike it, this does not release the
	 * network-wide process lock, which may belong to another site on a multisite network.
	 *
	 * @return int|bool Number of affected rows, false when the update failed.
	 */
	public function terminate_all_active_import_tasks() {
		global $wpdb;

		$tasks        = $this->get_tasks( [ 0, 1, 2 ] );
		$active_tasks = array_filter(
			is_array( $tasks ) ? $tasks : [],
			static function ( $task ) {
				return 'import' === ( $task->task_task ?? '' );
			}
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is static and safe. No caching needed for import process , low-frequency operations with high-frequency data updates. Direct database query is required for custom table
		$query = $wpdb->query(
			$wpdb->prepare(
				"
			UPDATE
				{$wpdb->prefix}arlo_async_tasks AS tasks
			SET
				task_status = 4,
				task_status_text = 'Import task terminated'
			WHERE
				task_task = %s
			AND
				task_status IN (0, 1, 2)
			",
				'import'
			)
		);

		// The process lock is network-wide and expires on its own, so another site's running subtask keeps it.
		if ( false !== $query && (int) $query > 0 ) {
			foreach ( $active_tasks as $task ) {
				$this->log_terminated_import_task( $task );
			}
		}

		return $query;
	}

	/**
	 * Processes a single scheduler task: arms the recurring safety net, acquires
	 * the process lock when available, defers fresh starts if another import has
	 * become in-flight, runs one import subtask, and releases the lock.
	 *
	 * If another request already holds the process lock, this schedules a short
	 * lock-busy retry instead of treating the contention as an import failure.
	 *
	 * Remarks:
	 * - `is_process_running()`'s own stale-lock safety net clears any lock left
	 *   behind by a genuinely abandoned/crashed task before this method's guard
	 *   check relies on it, so a stalled lock cannot permanently block new work.
	 *   On the happy path no such lock exists — the lock's 180s TTL always
	 *   expires long before the max-age threshold — so this is a no-op and
	 *   changes nothing about normal processing.
	 * - Delegates to `is_process_running()`, `lock_process()`/
	 *   `unlock_process()`, `should_defer_new_task_start()`,
	 *   `schedule_lock_busy_retry()`, and `Arlo_For_Wordpress::import()`.
	 * - A failed import marks the task failed (status 3) and clears the cron
	 *   safety-net event; an empty/invalid task just clears the cron.
	 *
	 * @param    array   $task    The task row set from `get_next_task()` /
	 *                            `get_task_data()`; element 0 carries `task_task`,
	 *                            `task_id`, `task_priority`, and `task_status`.
	 * @param    string  $source  Scheduler wake source for support diagnostics.
	 * @return   void
	 */
	public function process_task( $task = array(), $source = 'unknown' ) {
		$task_task = $task[0]->task_task ?? null;
		if ( ! empty( $task_task ) ) {
			$this->schedule_cron();
			$is_process_running = $this->is_process_running( $task_task );
			if ( ! $is_process_running ) {
				$this->lock_process( $task_task );
				// A selected status-0 task holds no lock, so another import can have gone in-flight
				// since this task was selected — re-check before starting a new import.
				if ( $this->should_defer_new_task_start( $task[0] ) ) {
					$this->unlock_process( $task_task );
					return;
				}
				$worker_started_at = microtime( true );
				$worker_outcome    = 'completed';
				try {
					$this->log_scheduler_worker_start( $task[0], $source );
					switch ( $task_task ) {
						case 'import':
							if ( ! $this->plugin->import( $task[0]->task_priority == -1, $task[0]->task_id ) ) {
								$worker_outcome = 'failed';
								if ( $this->update_task_if_active( $task[0]->task_id, 3, "Import task failed" ) ) {
									$this->clear_cron();
								}
							}
							break;
					}
				} catch ( \Throwable $e ) {
					$worker_outcome = 'exception';
					throw $e;
				} finally {
					// Always bookend the worker and release the process lock, even if the subtask
					// throws, so failure-case tracing stays complete and the lock is not leaked.
					// $worker_started_at is captured before the start-log write so elapsed_ms spans
					// the same window as the paired start/stop lines.
					$this->log_scheduler_worker_stop( $task[0], $source, $worker_started_at, $worker_outcome );
					$this->unlock_process( $task_task );
				}
			} else {
				$this->schedule_lock_busy_retry();
			}
		} else {
			$this->clear_cron();
		}
	}

	/**
	 * Whether a selected task that has not started yet should defer instead of
	 * running, because another import is already in-flight.
	 *
	 * Only ever true for a task at status 0 (not yet started); a task already
	 * resumed from a paused state (status 1) is never deferred by this check —
	 * deferring a resume would deadlock the self-kick loop that advances it.
	 *
	 * Remarks:
	 * - Exists because `process_task()`'s own `is_process_running()` guard only
	 *   detects a subtask synchronously executing this instant — a paused task
	 *   (status 1) holds no lock, so that guard cannot see it. `get_next_task()`
	 *   gates new-task selection against exactly that paused/running state, but
	 *   its result is a snapshot that can go stale under real concurrency
	 *   before `process_task()` acts on it.
	 * - Called by `process_task()` only after it has already acquired the
	 *   process lock, so no other concurrent request can pass that guard while
	 *   this check runs — closing the time-of-check/time-of-use window between
	 *   selection and this re-check.
	 *
	 * @param    object  $task  Selected task row.
	 * @return   bool    True when this task has not started yet and an import
	 *                   is now in-flight.
	 */
	private function should_defer_new_task_start( $task ) {
		// task_status is always present on a real DB row (get_tasks() always selects
		// it); isset() only guards a hand-built object that omits the field entirely.
		return isset( $task->task_status )
			&& intval( $task->task_status ) === 0
			&& $this->get_running_paused_tasks_count() > 0;
	}

	/**
	 * Arms the recurring scheduler safety-net event when it is not already scheduled.
	 *
	 * The fast path is the immediate self-kick chain. This recurring event is the
	 * slower fallback that resumes paused work if a loopback request is lost.
	 *
	 * @return  void
	 */
	protected function schedule_cron() {
		// Recurring safety-net: if a self-kick loopback is ever lost (missed shutdown
		// hook, blocked loopback request, or a backed-off retry whose single event did
		// not fire), this 5-minute cron still advances the paused task. It is the coarse
		// ceiling behind the fast self-kick chain, not the primary driver.
		if ( ! wp_next_scheduled( 'arlo_scheduler' ) ) {
			wp_schedule_event( time() + ( 60 * 5 ), 'minutes_5', 'arlo_scheduler' );
		}
	}

	/**
	 * Schedules a short, jittered retry after the process lock was busy.
	 *
	 * A process-lock-busy defer has not reached the importer and is not an import
	 * subtask failure. This wakeup only gives the scheduler another near-term chance
	 * to acquire the network-wide process lock while the recurring five-minute cron
	 * remains the coarse recovery bound.
	 *
	 * @return void
	 */
	private function schedule_lock_busy_retry() {
		if ( wp_next_scheduled( 'arlo_scheduler', self::LOCK_BUSY_RETRY_CRON_ARGS ) ) {
			return;
		}

		$delay = wp_rand( self::LOCK_BUSY_RETRY_MIN_SECONDS, self::LOCK_BUSY_RETRY_MAX_SECONDS );
		wp_schedule_single_event( time() + $delay, 'arlo_scheduler', self::LOCK_BUSY_RETRY_CRON_ARGS );
	}

	/**
	 * Clears scheduler cron events for the recurring safety-net schedule and
	 * scheduler-owned single-event wakeups.
	 *
	 * Remarks:
	 * - `wp_clear_scheduled_hook()` is argument-sensitive. Scheduler single-event
	 *   wakeups carry distinct argument arrays, so clearing only the empty-args
	 *   hook would leave stale one-shot wakeups behind after an import finishes
	 *   or fails terminally before a delayed retry fires.
	 *
	 * @return   void
	 */
	public function clear_cron() {
		$timestamp = wp_next_scheduled( 'arlo_scheduler' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'arlo_scheduler' );
		}

		wp_clear_scheduled_hook( 'arlo_scheduler' );
		wp_clear_scheduled_hook( 'arlo_scheduler', self::RETRY_BACKOFF_CRON_ARGS );
		wp_clear_scheduled_hook( 'arlo_scheduler', self::IMMEDIATE_FALLBACK_CRON_ARGS );
		wp_clear_scheduled_hook( 'arlo_scheduler', self::LOCK_BUSY_RETRY_CRON_ARGS );
	}

	/**
	 * Whether the named task's process lock is currently held.
	 *
	 * Reads the lock's site transient once. If it is absent, the process is not
	 * running. If it is present but its recorded acquisition time is older than
	 * `PROCESS_LOCK_MAX_AGE_SECONDS`, it is treated as abandoned: the stale-lock
	 * safety net force-releases it here and reports "not running", so a lock
	 * value that survives beyond its intended TTL for any reason cannot block
	 * the scheduler forever. On the happy path the TTL always expires the lock
	 * long before this max age, so this never engages.
	 *
	 * Remarks:
	 * - Delegates the staleness judgement to `is_lock_value_stale()`, sharing
	 *   logic with `get_process_lock_age()` / `is_process_lock_stale()` without
	 *   requiring a second transient read on this method's hot path (called on
	 *   every `process_task()` invocation, including every self-kick).
	 * - The lock's transient name (`<task>_process_lock`) is deliberately kept
	 *   as stored by earlier versions so a lock held across an upgrade is still
	 *   recognised.
	 *
	 * @param    string  $task  The lock's task key (e.g. `import`).
	 * @return   bool    True when the lock is held and not stale.
	 */
	private function is_process_running( $task ) {
		$lock_value = get_site_transient( $task . '_process_lock' );

		if ( ! $lock_value ) {
			return false;
		}

		if ( $this->is_lock_value_stale( $lock_value ) ) {
			$this->unlock_process( $task );

			return false;
		}

		return true;
	}

	/**
	 * Whether the import task's process lock is currently held.
	 *
	 * Remarks:
	 * - A lock whose recorded acquisition time is older than
	 *   `PROCESS_LOCK_MAX_AGE_SECONDS` is treated as abandoned: it is released as
	 *   a side effect of this check (see `is_process_running()`) and reported as
	 *   not held, rather than as still running.
	 *
	 * @since    5.2.0
	 *
	 * @return   bool True if a subtask is currently mid-execution under the
	 *                network-wide `import_process_lock` site transient, and
	 *                that lock is not stale.
	 */
	public function is_import_running() {
		return $this->is_process_running( 'import' );
	}

	/**
	 * Acquires the network-wide import process lock.
	 *
	 * The lock is a site transient (backed by `wp_sitemeta` on multisite) so the
	 * fragmented self-kick chain is serialised across sub-sites sharing one
	 * server, not just within a single site. The 180s TTL auto-releases the lock
	 * if a request dies before reaching `unlock_process()`, so a crash cannot
	 * wedge the pipeline. The stored payload is the lock's acquisition time
	 * (unix seconds).
	 *
	 * Remarks:
	 * - The stale-lock safety net (`get_process_lock_age()` /
	 *   `release_stale_process_lock()`) reads this same payload to measure the
	 *   lock's age. The previous payload (`microtime()`) was never read by
	 *   anything; this only gives that slot a meaning.
	 *
	 * @param    string  $task  The task key (e.g. `import`) the lock guards.
	 * @return   void
	 */
	private function lock_process( $task ) {
		set_site_transient( $task . '_process_lock', time(), 180 );
	}

	/**
	 * Releases the process lock for a task.
	 *
	 * @param   string  $task The task key (e.g. `import`) whose process lock should be released.
	 * @return  self
	 */
	public function unlock_process( $task ) {
		delete_site_transient( $task . '_process_lock' );
		return $this;
	}

	/**
	 * Age, in seconds, of a process lock's recorded acquisition time.
	 *
	 * A non-numeric or non-positive payload (e.g. the pre-5.2.0 `microtime()`
	 * format, from a lock acquired by not-yet-upgraded code during a rolling
	 * deploy) cannot be aged and returns null rather than being mis-parsed.
	 *
	 * @param    mixed     $lock_value  The raw value read back from a process
	 *                                   lock's site transient.
	 * @return   int|null  Seconds since the lock was first acquired, or null
	 *                     when the value cannot be aged.
	 */
	private function lock_value_age( $lock_value ) {
		if ( ! is_numeric( $lock_value ) || (int) $lock_value <= 0 ) {
			return null;
		}

		return max( 0, time() - (int) $lock_value );
	}

	/**
	 * Whether a process lock's recorded acquisition time is beyond
	 * `PROCESS_LOCK_MAX_AGE_SECONDS` and should be treated as abandoned.
	 *
	 * @param    mixed  $lock_value  The raw value read back from a process
	 *                                lock's site transient.
	 * @return   bool   True when the value can be aged and exceeds the max age.
	 */
	private function is_lock_value_stale( $lock_value ) {
		$age = $this->lock_value_age( $lock_value );

		return $age !== null && $age > self::PROCESS_LOCK_MAX_AGE_SECONDS;
	}

	/**
	 * Age, in seconds, of the currently-held process lock.
	 *
	 * @since    5.2.0
	 *
	 * @param    string    $task  The task key (e.g. `import`) whose lock to inspect.
	 * @return   int|null  Seconds since the lock was first acquired, or null when
	 *                     no lock is held (or its payload predates the timestamp
	 *                     format and cannot be aged).
	 */
	public function get_process_lock_age( $task ) {
		return $this->lock_value_age( get_site_transient( $task . '_process_lock' ) );
	}

	/**
	 * Whether the held process lock has aged past `PROCESS_LOCK_MAX_AGE_SECONDS`
	 * and should be treated as abandoned.
	 *
	 * Returns false when no lock is held, when its age cannot be determined, and
	 * — on the happy path — always, because the lock's 180s TTL expires long
	 * before the max-age threshold is reached. It can only return true once the
	 * lock outlives that TTL, which today it never does.
	 *
	 * @since    5.2.0
	 *
	 * @param    string  $task  The task key (e.g. `import`) whose lock to inspect.
	 * @return   bool    True only when a lock is held and older than the max age.
	 */
	public function is_process_lock_stale( $task ) {
		return $this->is_lock_value_stale( get_site_transient( $task . '_process_lock' ) );
	}

	/**
	 * Releases the process lock only if it is stale (older than
	 * `PROCESS_LOCK_MAX_AGE_SECONDS`), reclaiming a lock left behind by a
	 * genuinely abandoned or crashed task.
	 *
	 * This is a safety net, not part of normal lock handling: on the happy path
	 * the lock is short-lived and its TTL always expires first, so this never
	 * fires and normal processing is unaffected.
	 *
	 * Remarks:
	 * - Mirrors the intent of `Arlo_For_Wordpress::fail_stalled_or_overlong_import_tasks()`'s
	 *   28-minute stalled-task detector, applied to the lock itself.
	 *
	 * @since    5.2.0
	 *
	 * @param    string  $task  The task key (e.g. `import`) whose lock to reclaim.
	 * @return   bool    True if a stale lock was found and released; false otherwise.
	 */
	public function release_stale_process_lock( $task ) {
		if ( $this->is_process_lock_stale( $task ) ) {
			$this->unlock_process( $task );

			return true;
		}

		return false;
	}

	/**
	 * Arms the immediate, self-perpetuating scheduler loopback that advances a
	 * multi-step import one subtask per request.
	 *
	 * The import pipeline is deliberately fragmented: each request processes a
	 * single subtask and then re-triggers the scheduler, so no individual
	 * request risks a shared-hosting execution-time limit. This method
	 * registers a `shutdown` hook that fires that re-trigger once the current
	 * request ends, and guards against registering the hook more than once per
	 * request. This is the healthy-progression path: an immediate, non-blocking
	 * loopback.
	 *
	 * Remarks:
	 * - The actual request is sent by `kick_off_scheduler_on_shutdown()`.
	 * - The failure/retry path uses `kick_off_scheduler_with_backoff()` instead,
	 *   which spaces re-triggers out so a persistently failing subtask cannot
	 *   re-fire in a tight loop.
	 *
	 * @return   void
	 */
	public function kick_off_scheduler() {
		$has_shutdown_action = has_action( 'shutdown', array( $this, 'kick_off_scheduler_on_shutdown' ) );
		if ( ! $has_shutdown_action ) {
			add_action( 'shutdown', array( $this, 'kick_off_scheduler_on_shutdown' ) );
		}

		$next_fallback = wp_next_scheduled( 'arlo_scheduler', self::IMMEDIATE_FALLBACK_CRON_ARGS );
		if ( ! $next_fallback ) {
			wp_schedule_single_event( time() + self::IMMEDIATE_FALLBACK_DELAY_SECONDS, 'arlo_scheduler', self::IMMEDIATE_FALLBACK_CRON_ARGS );
		}
	}

	/**
	 * Resolves the backoff delay, in seconds, for a given consecutive retry
	 * attempt from the `RETRY_BACKOFF_SECONDS` ladder.
	 *
	 * A non-positive attempt (i.e. normal progression, not a retry) returns
	 * zero, meaning "no delay". Attempt 1 maps to the first ladder entry;
	 * attempts beyond the ladder length are capped at the final entry.
	 *
	 * @since    5.2.0
	 *
	 * @param    int  $retry_attempt  The consecutive retry attempt number
	 *                                (1-based). Values below 1 yield no delay.
	 * @return   int  The delay in seconds before the scheduler should be
	 *                re-triggered; 0 when no backoff applies.
	 */
	public static function get_retry_backoff_seconds( $retry_attempt ) {
		$retry_attempt = (int) $retry_attempt;

		if ( $retry_attempt < 1 ) {
			return 0;
		}

		$ladder = self::RETRY_BACKOFF_SECONDS;
		$index  = min( $retry_attempt, count( $ladder ) ) - 1;

		return (int) $ladder[ $index ];
	}

	/**
	 * Re-triggers the scheduler after a failed import subtask, spacing the
	 * next run according to the retry backoff ladder so a persistently
	 * failing import cannot re-fire itself in a tight, backoff-free loop.
	 *
	 * When no backoff applies (attempt below 1), this behaves exactly like
	 * `kick_off_scheduler()` — an immediate, non-blocking loopback self-kick.
	 * When a backoff applies, the resume is deferred instead: no PHP worker
	 * is held for the delay, and no immediate loopback request is fired.
	 *
	 * Remarks:
	 * - The deferred resume is scheduled as a single `arlo_scheduler` WP-Cron
	 *   event at `now + backoff`; that hook already routes to
	 *   `Arlo_For_Wordpress::run_scheduler_queue()`, which resumes the paused
	 *   (status 1) task. The recurring `minutes_5` `arlo_scheduler` event
	 *   remains as a coarser safety net.
	 * - The single event carries a distinguishing positional argument (see
	 *   `RETRY_BACKOFF_CRON_ARGS`) so WordPress's near-duplicate suppression does
	 *   not mistake it for the recurring (empty-argument) `arlo_scheduler` event
	 *   and silently drop it.
	 * - Delegates to `get_retry_backoff_seconds()` for the delay and to
	 *   `kick_off_scheduler()` for the immediate (no-backoff) path.
	 *
	 * @since    5.2.0
	 *
	 * @param    int  $retry_attempt  The consecutive retry attempt number
	 *                                (1-based) driving the backoff.
	 * @return   void
	 */
	public function kick_off_scheduler_with_backoff( $retry_attempt ) {
		$backoff_seconds = self::get_retry_backoff_seconds( $retry_attempt );

		if ( $backoff_seconds <= 0 ) {
			// Normal progression (not a retry): keep the immediate loopback so a
			// healthy import advances without any added latency between subtasks.
			$this->kick_off_scheduler();
			return;
		}

		// Tag the event so WordPress's near-duplicate check keys it separately
		// from the recurring empty-argument 'arlo_scheduler' event. Without a
		// distinct argument, a recurring occurrence within ~10 minutes of this
		// timestamp would be treated as a duplicate and this backed-off retry
		// would silently never be scheduled.
		$backoff_args = self::RETRY_BACKOFF_CRON_ARGS;

		// Only one backed-off retry needs to be pending at a time; don't stack a
		// second event if a prior retry in this chain is already scheduled.
		$next_backoff = wp_next_scheduled( 'arlo_scheduler', $backoff_args );
		if ( ! $next_backoff ) {
			wp_schedule_single_event( time() + $backoff_seconds, 'arlo_scheduler', $backoff_args );
		}
	}

	/**
	 * Fires the scheduler loopback request as the current request shuts down.
	 *
	 * Sends a non-blocking `wp_remote_post` to this site's own
	 * `admin-ajax.php?action=arlo_run_scheduler` endpoint (with a fresh
	 * `arlo_import` nonce), which picks up and advances the next import
	 * subtask. The request is non-blocking (`timeout` 0.01s, `blocking` false)
	 * so the finishing request is not held open waiting for the next step.
	 *
	 * Registered as a `shutdown` action by `kick_off_scheduler()`; not intended
	 * to be called directly.
	 *
	 * @return   void
	 */
	public function kick_off_scheduler_on_shutdown() {
		$url  = add_query_arg( $this->get_query_args(), $this->get_query_url() );
		$args = $this->get_post_args();

		$response = wp_remote_post( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'Scheduler self-kick failed: ' . implode( ', ', $response->get_error_messages() ) );
		}
	}

	private function try_kick_off_scheduler( $url ) {
		$limit         = 10;
		$tries         = 0;
		$sleep_seconds = 3;

		$args = $this->get_post_args();

		do {
			$success  = false;
			$response = wp_remote_post( esc_url_raw( $url ), $args );

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_messages();
				sleep( $sleep_seconds );
			} elseif ( substr( $response['response']['code'], 0, 1 ) != 2 ) {
				$error_message = 'Unknown error';
				if ( ! empty( $response['response']['code'] ) ) {
					$error_message = $response['response']['message'];
				}

				sleep( $sleep_seconds );
			} else {
				$success = true;
			}
		} while ( ! $success && ++$tries < $limit );

		if ( ! $success && isset( $error_message ) && $tries >= $limit ) {
			throw new \ArloTraining\SchedulerException( 'Kick off scheduler error: ' . ( is_array( $error_message ) ? implode( ', ', $error_message ) : $error_message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
	}

	/**
	 * Record which PHP worker started processing a scheduler-selected task.
	 *
	 * @param object $task   Selected scheduler task row.
	 * @param string $source Scheduler wake source.
	 * @return void
	 */
	private function log_scheduler_worker_start( $task, $source ): void {
		Logger::log_diagnostic(
			Logger::DIAGNOSTIC_VERBOSITY_TRACE,
			'Starting scheduler worker [worker_id=' . $this->get_scheduler_worker_id() . '; task_id=' . intval( $task->task_id ?? 0 ) . '; source=' . $this->format_scheduler_wake_source( $source ) . '; task=' . (string) ( $task->task_task ?? 'unknown' ) . '; status=' . ( isset( $task->task_status ) ? intval( $task->task_status ) : 'unknown' ) . '; priority=' . ( isset( $task->task_priority ) ? intval( $task->task_priority ) : 'unknown' ) . ']'
		);
	}

	/**
	 * Record that a PHP worker finished processing a scheduler-selected task.
	 *
	 * Bookends log_scheduler_worker_start() so a support timeline can show which
	 * requests actually did work, how long they took, and how they ended. Joined
	 * to the matching start line by the request correlation id.
	 *
	 * @param object $task       Selected scheduler task row.
	 * @param string $source     Scheduler wake source.
	 * @param float  $started_at microtime(true) captured when the worker started.
	 * @param string $outcome    Coarse worker outcome label.
	 * @return void
	 */
	private function log_scheduler_worker_stop( $task, $source, $started_at, $outcome ): void {
		$elapsed_ms = (int) round( ( microtime( true ) - $started_at ) * 1000 );
		Logger::log_diagnostic(
			Logger::DIAGNOSTIC_VERBOSITY_TRACE,
			'Stopping scheduler worker [worker_id=' . $this->get_scheduler_worker_id() . '; task_id=' . intval( $task->task_id ?? 0 ) . '; source=' . $this->format_scheduler_wake_source( $source ) . '; outcome=' . $outcome . '; elapsed_ms=' . $elapsed_ms . ']'
		);
	}

	/**
	 * Return the current PHP worker process id for diagnostics.
	 *
	 * @return string Worker process id, or `unknown` when unavailable.
	 */
	private function get_scheduler_worker_id(): string {
		$worker_id = function_exists( 'getmypid' ) ? getmypid() : false;

		return $worker_id === false ? 'unknown' : (string) intval( $worker_id );
	}

	/**
	 * Format a scheduler wake source for compact log output.
	 *
	 * @param string $source Scheduler wake source.
	 * @return string Safe source label.
	 */
	private function format_scheduler_wake_source( $source ): string {
		$source = preg_replace( '/[^a-z0-9_-]+/i', '-', (string) $source );
		$source = trim( (string) $source, '-' );

		return $source === '' ? 'unknown' : $source;
	}

	private function get_query_args() {
		if ( property_exists( $this, 'query_args' ) ) {
			return $this->query_args;
		}

		return array(
			'action' => 'arlo_run_scheduler',
		);
	}

	private function get_query_url() {
		if ( property_exists( $this, 'query_url' ) ) {
			return $this->query_url;
		}

		return admin_url( 'admin-ajax.php' );
	}

	private function get_post_args() {
		if ( property_exists( $this, 'post_args' ) ) {
			return $this->post_args;
		}

		return array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'body'      => array( 'nonce' => wp_create_nonce( 'arlo_import' ) ),
			'cookies'   => $_COOKIE,
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- https_local_ssl_verify is a WordPress core filter; this loopback request intentionally mirrors core's own cron loopback SSL setting.
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);
	}

	/**
	 * Whether a task has at least one row in the supplied statuses.
	 *
	 * @param   string  $task     Task key to check.
	 * @param   array   $statuses Status values to match.
	 * @return  bool True when a matching row exists.
	 */
	private function has_task_with_statuses( $task, array $statuses ): bool {
		global $wpdb;

		if ( empty( $task ) ) {
			return false;
		}

		$statuses = array_values(
			array_filter(
				$statuses,
				function ( $status ) {
					return is_numeric( $status );
				}
			)
		);
		if ( empty( $statuses ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%d' ) );
		$sql          = "
		SELECT
			COUNT(1)
		FROM
			{$wpdb->prefix}arlo_async_tasks
		WHERE
			task_task = %s
		AND
			task_status IN ($placeholders)
		";

		$parameters = array_merge( array( $task ), array_map( 'intval', $statuses ) );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The SQL statement is dynamically constructed and parameters are prepared here. Import task state must be read directly from the async task table.
		$result = $wpdb->get_var( Utilities::prepare_sql( $sql, $parameters ) );

		return intval( $result ) > 0;
	}

	/**
	 * Acquires the short mutex used around unique task enqueue.
	 *
	 * The WordPress options table provides the required atomic unique insert via
	 * its `option_name` key. Expired stale rows are removed before acquisition.
	 *
	 * @param   string  $task Task key being queued.
	 * @return  string|false Lock value on success; false when another caller holds it.
	 */
	private function acquire_task_enqueue_lock( $task ) {
		global $wpdb;

		$option_name = $this->get_task_enqueue_lock_option_name( $task );
		$now         = time();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock state is read directly from the options table so acquisition is not affected by object-cache state.
		$existing_value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name ) );
		if ( ! empty( $existing_value ) && $this->is_task_enqueue_lock_expired( $existing_value, $now ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stale lock cleanup is token-scoped by exact option_value match and prepared here.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $option_name, $existing_value ) );
		}

		$lock_value           = $this->create_task_enqueue_lock_value( $now );
		$previous_suppression = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The options table has a unique option_name key; this INSERT is the enqueue mutex's atomic acquire.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", $option_name, $lock_value, 'no' ) );
		$wpdb->suppress_errors( $previous_suppression );

		return $inserted ? $lock_value : false;
	}

	/**
	 * Releases the unique-enqueue mutex if this caller still owns it.
	 *
	 * @param   string  $task       Task key that was queued.
	 * @param   string  $lock_value Lock value returned by acquire_task_enqueue_lock().
	 * @return  void
	 */
	private function release_task_enqueue_lock( $task, $lock_value ): void {
		global $wpdb;

		$option_name = $this->get_task_enqueue_lock_option_name( $task );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock release is token-scoped by exact option_value match and prepared here.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $option_name, $lock_value ) );
	}

	/**
	 * Returns the options-table key for a task enqueue mutex.
	 *
	 * @param   string  $task Task key being queued.
	 * @return  string Option name for the mutex row.
	 */
	private function get_task_enqueue_lock_option_name( $task ): string {
		return 'arlo_task_enqueue_lock_' . substr( sha1( (string) $task ), 0, 16 );
	}

	/**
	 * Creates the token payload stored in the enqueue mutex row.
	 *
	 * @param   int  $now Current Unix timestamp.
	 * @return  string JSON encoded lock payload.
	 */
	private function create_task_enqueue_lock_value( $now ): string {
		return wp_json_encode(
			array(
				'token'   => $this->create_task_enqueue_lock_token( $now ),
				'expires' => $now + self::TASK_ENQUEUE_LOCK_TTL_SECONDS,
			)
		);
	}

	/**
	 * Creates a best-effort owner token for the short enqueue mutex.
	 *
	 * @param   int  $now Current Unix timestamp.
	 * @return  string Token stored in the enqueue mutex row.
	 */
	private function create_task_enqueue_lock_token( $now ): string {
		try {
			return bin2hex( $this->get_random_bytes( 16 ) );
		} catch ( \Throwable $e ) {
			// This token only scopes release of a short-lived options-table enqueue mutex.
			// It is not an authentication secret and does not own importer checkpoints.
			return sha1( $now . '|' . uniqid( '', true ) . '|' . wp_rand() );
		}
	}

	/**
	 * Returns random bytes for enqueue lock token generation.
	 *
	 * @param   int  $length Number of bytes to return.
	 * @return  string Random bytes.
	 */
	protected function get_random_bytes( $length ): string {
		return random_bytes( $length );
	}

	/**
	 * Whether an enqueue mutex row has expired and can be reclaimed.
	 *
	 * @param   string  $lock_value Raw lock payload from the options table.
	 * @param   int     $now        Current Unix timestamp.
	 * @return  bool True when the lock is expired or malformed.
	 */
	private function is_task_enqueue_lock_expired( $lock_value, $now ): bool {
		$decoded = json_decode( (string) $lock_value, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['expires'] ) || ! is_numeric( $decoded['expires'] ) ) {
			return true;
		}

		return intval( $decoded['expires'] ) <= $now;
	}

	/**
	 * Log a termination for the selected import task.
	 *
	 * @param object|null $task Scheduler task row selected by the termination caller.
	 * @return void
	 */
	private function log_terminated_import_task( $task ): void {
		$task_data = json_decode( ! empty( $task->task_data_text ) ? $task->task_data_text : '{}' );
		$task_data = is_object( $task_data ) ? $task_data : (object) array();
		$import_id = ! empty( $task_data->import_id ) && is_scalar( $task_data->import_id ) && is_numeric( $task_data->import_id ) ? intval( $task_data->import_id ) : 0;

		Logger::log( 'Import task terminated', $import_id );
	}
}
