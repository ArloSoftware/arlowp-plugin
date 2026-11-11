<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;
use ArloTraining\CacheControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Final import step that makes a completed import live and resumes post-switch cleanup.
 */
class Finish extends AbstractImportStep {

	public $import_tasks = array(
		'PrepareNewLiveDataset'    => 'Prepare new live dataset',
		'SwitchLive'               => 'Switch live import id',
		'SuccessFinalization'      => 'Finalize successful import',
		'BuildIncrementalBaseline' => 'Build incremental baseline',
		'CleanupTables'            => 'Cleaning up import records',
		'CleanupPosts'             => 'Cleaning up posts',
	);

	public $current_task;
	public $current_task_num;
	public $current_task_desc  = '';
	public $current_task_retry = 0;

	private $live_switch_done             = false;
	private $success_finalization_done    = false;
	private $incremental_baseline_done    = false;
	private $incremental_baseline_enabled = false;
	private $baseline_configuration_signature;
	private $table_cleanup_index        = 0;
	private $custom_post_cleanup_index  = 0;
	private $table_cleanup_rows_deleted = 0;
	private $custom_posts_deleted       = 0;

	/** @var IncrementalBaselineRepository|null */
	private $baseline_repository;

	protected function import_entity( $entity_payload ) {}

	/**
	 * Restores resumable Finish phase state and records this retry attempt.
	 *
	 * @param array<string, int|string|null>|object|null $state Stored nested Finish state from scheduler task data.
	 * @return void
	 * @throws \Exception When the retry checkpoint cannot be written by the mutex owner.
	 */
	public function set_state( $state ) {
		if ( ! empty( $state ) ) {
			$state                            = (object) $state;
			$this->live_switch_done           = ! empty( $state->live_switch_done );
			$this->success_finalization_done  = ! empty( $state->success_finalization_done );
			$this->incremental_baseline_done  = ! empty( $state->incremental_baseline_done );
			$this->table_cleanup_index        = isset( $state->table_cleanup_index ) ? intval( $state->table_cleanup_index ) : 0;
			$this->custom_post_cleanup_index  = isset( $state->custom_post_cleanup_index ) ? intval( $state->custom_post_cleanup_index ) : 0;
			$this->table_cleanup_rows_deleted = isset( $state->table_cleanup_rows_deleted ) ? intval( $state->table_cleanup_rows_deleted ) : 0;
			$this->custom_posts_deleted       = isset( $state->custom_posts_deleted ) ? intval( $state->custom_posts_deleted ) : 0;

			if ( ! empty( $state->current_subtask ) && isset( $this->import_tasks[ $state->current_subtask ] ) ) {
				$this->set_current_task( $state->current_subtask );
			}
		}

		$this->reconcile_live_switch_done();
		$this->select_current_task();

		$this->current_task_retry = ( isset( $state->current_subtask_retry ) ? intval( $state->current_subtask_retry ) + 1 : $this->current_task_retry + 1 );

		if ( ! $this->persist_retry_state( array( 'current_subtask_retry' => $this->current_task_retry ) ) ) {
			throw new \Exception( 'Retry state write rejected - mutex ownership lost.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the surrounding import pipeline and never rendered in a browser context.
		}
	}

	/**
	 * Returns the nested Finish checkpoint state stored in scheduler task data.
	 *
	 * The returned array contains the standard import-step checkpoint keys plus
	 * Finish-specific flags: live_switch_done, success_finalization_done, incremental_baseline_done,
	 * table_cleanup_index, custom_post_cleanup_index, table_cleanup_rows_deleted,
	 * and custom_posts_deleted.
	 *
	 * @return array<string, int|string|null> Current phase, retry, and cleanup cursor state.
	 */
	public function get_state() {
		$this->select_current_task();

		return array(
			'finished_subtask'           => ( $this->is_finished ? $this->current_task : null ),
			'current_subtask'            => ( $this->is_finished ? null : $this->current_task ),
			'current_subtask_desc'       => isset( $this->import_tasks[ $this->current_task ] ) ? $this->import_tasks[ $this->current_task ] : '',
			'current_subtask_num'        => array_search( $this->current_task, array_keys( $this->import_tasks ), true ),
			'iteration'                  => 0,
			'iteration_finished'         => 0,
			'current_subtask_retry'      => $this->current_task_retry,
			'live_switch_done'           => ( $this->live_switch_done ? 1 : 0 ),
			'success_finalization_done'  => ( $this->success_finalization_done ? 1 : 0 ),
			'incremental_baseline_done'  => ( $this->incremental_baseline_done ? 1 : 0 ),
			'table_cleanup_index'        => $this->table_cleanup_index,
			'custom_post_cleanup_index'  => $this->custom_post_cleanup_index,
			'table_cleanup_rows_deleted' => $this->table_cleanup_rows_deleted,
			'custom_posts_deleted'       => $this->custom_posts_deleted,
		);
	}

	/**
	 * Advances finalization until complete, paused, or failed by exception.
	 *
	 * @return void
	 */
	public function run() {
		$this->reconcile_live_switch_done();
		$this->select_current_task();

		if ( ! $this->try_continue_with_import_mutex() ) {
			$this->clear_provisional_retry_charge();
			return;
		}

		if ( ! $this->live_switch_done ) {
			$this->set_current_task( 'PrepareNewLiveDataset' );
			$this->prepare_new_live_dataset();

			if ( ! $this->try_continue_with_import_mutex() ) {
				$this->clear_provisional_retry_charge();
				return;
			}

			$this->set_current_task( 'SwitchLive' );
			if ( ! $this->switch_live_import_id() ) {
				$this->clear_provisional_retry_charge();
				return;
			}
		}

		if ( ! $this->success_finalization_done ) {
			$this->set_current_task( 'SuccessFinalization' );
			if ( ! $this->run_success_finalization() ) {
				$this->clear_provisional_retry_charge();
				return;
			}
		}

		if ( $this->incremental_baseline_enabled && ! $this->incremental_baseline_done ) {
			$this->set_current_task( 'BuildIncrementalBaseline' );
			if ( ! $this->try_finalize_incremental_baseline() ) {
				// The new dataset remains live, but baseline finalization is
				// not durable; pause before cleanup so a later Finish kick
				// can retry.
				$this->clear_provisional_retry_charge();
				return;
			}
		}

		if ( ! $this->cleanup_previous_imports() ) {
			$this->clear_provisional_retry_charge();
			return;
		}

		delete_option( 'arlo_import_cleanup_pending' );
		$this->is_finished = true;
		$this->clear_provisional_retry_charge();
	}

	/**
	 * Prepares imported rows that must be correct before the live id changes.
	 *
	 * @return void
	 */
	private function prepare_new_live_dataset() {
		$this->importer->set_tax_exempt_events( $this->import_id );
	}

	/**
	 * Makes this import id live and records cleanup debt durably.
	 *
	 * @return bool True when the live-switch checkpoint was accepted by the mutex owner.
	 */
	private function switch_live_import_id() {
		$this->importer->set_current_import_id( $this->import_id );
		// Last-ditch guard: never checkpoint live_switch_done unless the live id really changed in durable storage.
		$current_import_id = $this->importer->get_current_import_id( true );
		if ( (string) $current_import_id !== (string) $this->import_id ) {
			Logger::log( sprintf( 'Live import id mismatch after switchover attempt; expected %s, found %s. Finish paused before cleanup.', $this->import_id, ( $current_import_id === null ? 'null' : $current_import_id ) ), $this->import_id );
			return false;
		}

		$this->live_switch_done = true;
		// From this point on, failures must preserve the new live dataset and report cleanup debt.
		update_option( 'arlo_import_cleanup_pending', $this->import_id );
		CacheControl::cache_delete( CacheControl::GROUP_PRIMARY_OBJECTS );
		CacheControl::invalidate_upcoming_widget_cache();
		CacheControl::Clear();

		return $this->persist_phase_state( array( 'live_switch_done' => 1 ) );
	}

	/**
	 * Records successful import side effects once for a live import.
	 *
	 * @return bool True when finalization completed or was already safely claimed; false when the task should pause.
	 */
	private function run_success_finalization() {
		if ( ! $this->try_continue_with_import_mutex() ) {
			return false;
		}

		$this->success_finalization_done = true;
		// Claim the one-time success path before firing third-party hooks; duplicates are worse than a skipped hook after a fatal.
		if ( ! $this->persist_phase_state( array( 'success_finalization_done' => 1 ) ) ) {
			return false;
		}

		( new ImportSuccessFinalizer() )->finalize_import( $this->importer, $this->message_handler, (int) $this->import_id );

		return true;
	}

	/**
	 * Finalizes a full-shadow candidate by promoting it or invalidating the baseline.
	 *
	 * @return bool True when the durable lifecycle decision and Finish checkpoint were persisted.
	 */
	private function try_finalize_incremental_baseline() {
		if ( ! $this->try_continue_with_import_mutex() ) {
			return false;
		}

		$repository = $this->get_baseline_repository();
		// Reading the current live import id and promoting or invalidating its baseline must be coordinated in one transaction.
		$completed = $repository->run_in_transaction(
			function ( IncrementalBaselineRepository $repository ) {
				$current_import_id = $this->get_current_live_import_id( true );
				$candidate         = $repository->get_locked_candidate_for_import( (int) $this->import_id );

				if ( (string) $current_import_id !== (string) $this->import_id ) {
					// A newer import is already live, so do not change its current baseline.
					return ! is_object( $candidate ) || $repository->remove_candidate_and_generation( (int) $candidate->candidate_id, (int) $candidate->baseline_generation_id );
				}

				if ( ! is_object( $candidate ) ) {
					// Promotion may have committed before its Finish checkpoint was written.
					if ( $repository->is_current_clean_baseline_for_import( (int) $this->import_id ) ) {
						return true;
					}

					// This completed shadow import has no candidate generation to prove as the next reusable baseline.
					return $repository->mark_baseline_invalid();
				}

				// Candidate identity comes from the admitted snapshot; compare it to current settings here so a mid-import configuration change cannot promote an incompatible baseline.
				if ( ! $this->candidate_matches_current_configuration( $candidate ) || $repository->get_baseline_generation_entry_count( (int) $candidate->baseline_generation_id ) !== (int) $candidate->manifest_count ) {
					// A full-shadow import admitted before relevant plugin configuration changed may become live, but cannot become a reusable baseline for the new configuration.
					// This live import has no complete baseline proof.
					// Remove any incomplete candidate and mark baseline invalid to require full shadow next time.
					if ( ! $repository->remove_candidate_and_generation( (int) $candidate->candidate_id, (int) $candidate->baseline_generation_id ) ) {
						return false;
					}
					return $repository->mark_baseline_invalid();
				}

				// The still-live import has complete proof; make its generation the current baseline.
				return $repository->promote_candidate_as_baseline( $candidate );
			},
			// The callback reads before it promotes or invalidates, and an admin platform change invalidates from another request.
			true
		);
		if ( ! $completed ) {
			return false;
		}

		$this->incremental_baseline_done = true;
		return $this->persist_phase_state( array( 'incremental_baseline_done' => 1 ) );
	}

	/**
	 * Sets the persistence boundary used for baseline build and promotion.
	 *
	 * @param IncrementalBaselineRepository $repository Baseline repository.
	 * @return void
	 */
	public function set_incremental_baseline_repository( IncrementalBaselineRepository $repository ) {
		$this->baseline_repository = $repository;
	}

	/**
	 * Enables baseline lifecycle finalization for this Finish instance.
	 *
	 * @param bool $enabled Whether to enable baseline lifecycle finalization.
	 * @return void
	 */
	public function set_incremental_baseline_enabled( bool $enabled ): void {
		$this->incremental_baseline_enabled = $enabled;
		$this->select_current_task();
	}

	/**
	 * Sets the current configuration signature used to validate a candidate baseline.
	 *
	 * @param string|null $signature Raw SHA-256 signature for current settings.
	 * @return void
	 */
	public function set_incremental_baseline_configuration_signature( ?string $signature ): void {
		$this->baseline_configuration_signature = $signature;
	}

	/**
	 * Checks whether a staged candidate matches the current configuration.
	 *
	 * @param object $candidate Staged candidate baseline.
	 * @return bool Whether the candidate is compatible with current settings.
	 */
	private function candidate_matches_current_configuration( $candidate ): bool {
		return is_string( $this->baseline_configuration_signature )
			&& isset( $candidate->configuration_signature )
			&& is_string( $candidate->configuration_signature )
			&& hash_equals( $this->baseline_configuration_signature, $candidate->configuration_signature );
	}

	/**
	 * Returns the baseline persistence boundary.
	 *
	 * @return IncrementalBaselineRepository Baseline persistence boundary.
	 */
	private function get_baseline_repository(): IncrementalBaselineRepository {
		if ( null === $this->baseline_repository ) {
			$this->baseline_repository = new IncrementalBaselineRepository();
		}

		return $this->baseline_repository;
	}

	/**
	 * Removes database rows and custom posts that do not belong to the current live import id.
	 *
	 * @return bool True when cleanup completed; false when cleanup checkpointing or viability requires a pause.
	 */
	private function cleanup_previous_imports() {
		global $wpdb;
		$tables      = $this->get_cleanup_tables();
		$table_count = count( $tables );

		for ( $i = $this->table_cleanup_index; $i < $table_count; $i++ ) {
			if ( ! $this->try_continue_with_import_mutex() ) {
				return false;
			}

			// Cleanup always follows the durable live pointer, not the import that originally scheduled this work.
			$current_live_import_id = $this->get_current_live_import_id();
			if ( ! $this->is_valid_import_id( $current_live_import_id ) ) {
				Logger::log( 'Live import id unavailable during table cleanup; Finish paused before cleanup.', $this->import_id );
				return false;
			}

			$table = $wpdb->prefix . 'arlo_' . $tables[ $i ];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for delete operation.
			$rows_deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE import_id <> %d', $table, $current_live_import_id ) );
			if ( $rows_deleted === false ) {
				Logger::log( sprintf( 'Database cleanup failed for %s: %s', $table, $wpdb->last_error ), $this->import_id );
				return false;
			}
			$this->table_cleanup_rows_deleted += intval( $rows_deleted );

			$this->table_cleanup_index = $i + 1;
			$this->set_current_task( 'CleanupTables' );
			if ( ! $this->persist_phase_state(
				array(
					'table_cleanup_index'        => $this->table_cleanup_index,
					'table_cleanup_rows_deleted' => $this->table_cleanup_rows_deleted,
				)
			) ) {
				return false;
			}

			if ( $this->table_cleanup_index === $table_count ) {
				Logger::log( 'Cleaned up import records', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import record cleanup completed [tables=' . $table_count . '; rows_deleted=' . $this->table_cleanup_rows_deleted . '; live_import_id=' . $current_live_import_id . ']', $this->import_id );
			}

			if ( ! $this->importer->check_viable_execution_environment() ) {
				return false;
			}
		}

		$post_cleanup_batches = $this->get_post_cleanup_batches();
		$post_cleanup_count   = count( $post_cleanup_batches );

		for ( $i = $this->custom_post_cleanup_index; $i < $post_cleanup_count; $i++ ) {
			if ( ! $this->try_continue_with_import_mutex() ) {
				return false;
			}

			// Retained post slugs must be scoped to the same current-live id as table cleanup.
			$current_live_import_id = $this->get_current_live_import_id();
			if ( ! $this->is_valid_import_id( $current_live_import_id ) ) {
				Logger::log( 'Live import id unavailable during post cleanup; Finish paused before cleanup.', $this->import_id );
				return false;
			}

			$post_cleanup_batch = $post_cleanup_batches[ $i ];
			$cleanup_result     = \Arlo_For_Wordpress::delete_custom_posts( $post_cleanup_batch[0], $post_cleanup_batch[1], $post_cleanup_batch[2], $current_live_import_id );
			if ( empty( $cleanup_result['success'] ) ) {
				return false;
			}
			$this->custom_posts_deleted += intval( $cleanup_result['deleted_count'] );

			$this->custom_post_cleanup_index = $i + 1;
			$this->set_current_task( 'CleanupPosts' );
			if ( ! $this->persist_phase_state(
				array(
					'custom_post_cleanup_index' => $this->custom_post_cleanup_index,
					'custom_posts_deleted'      => $this->custom_posts_deleted,
				)
			) ) {
				return false;
			}

			if ( $this->custom_post_cleanup_index === $post_cleanup_count ) {
				Logger::log( 'Cleaned up posts', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Post cleanup completed [post_types=' . $post_cleanup_count . '; posts_deleted=' . $this->custom_posts_deleted . '; live_import_id=' . $current_live_import_id . ']', $this->import_id );
			}

			if ( ! $this->importer->check_viable_execution_environment() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns imported table suffixes eligible for old-row cleanup.
	 *
	 * @return list<string> Table suffixes without the WordPress prefix or arlo_ prefix.
	 */
	private function get_cleanup_tables() {
		return array(
			'eventtemplates',
			'contentfields',
			'offers',
			'events',
			'events_tags',
			'tags',
			'presenters',
			'venues',
			'categories',
			'onlineactivities',
			'onlineactivities_tags',
			'events_presenters',
			'eventtemplates_categories',
			'eventtemplates_presenters',
			'eventtemplates_tags',
			'timezones',
		);
	}

	/**
	 * Returns custom-post cleanup mappings for imported entity post types.
	 *
	 * @return list<array{0:string,1:string,2:string}> Table, slug-column, and post-type suffix mappings.
	 */
	private function get_post_cleanup_batches() {
		return array(
			array( 'eventtemplates', 'et_post_name', 'event' ),
			array( 'presenters', 'p_post_name', 'presenter' ),
			array( 'venues', 'v_post_name', 'venue' ),
		);
	}

	/**
	 * Sets the active Finish phase metadata used for checkpoints and status text.
	 *
	 * @param string $task_step Finish phase key from $import_tasks.
	 * @return void
	 */
	public function set_current_task( $task_step ) {
		$this->current_task      = $task_step;
		$this->current_task_num  = array_search( $this->current_task, array_keys( $this->import_tasks ), true );
		$this->current_task_desc = isset( $this->import_tasks[ $this->current_task ] ) ? $this->import_tasks[ $this->current_task ] : '';
	}

	/**
	 * Attempts to keep this request as the active Finish owner.
	 *
	 * @return bool True when the import mutex is held and the scheduler task is not terminal.
	 */
	private function try_continue_with_import_mutex() {
		if ( ! $this->importer->extend_import_mutex_ownership() ) {
			Logger::log( $this->live_switch_done ? 'Import cleanup stopped because import mutex ownership was lost.' : 'Import stopped because import mutex ownership was lost.', $this->import_id );
			return false;
		}

		if ( ! empty( $this->task_id ) && $this->scheduler->is_task_terminal( $this->task_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Derives the current Finish phase from durable phase and cleanup state.
	 *
	 * @return void
	 */
	private function select_current_task() {
		if ( ! $this->live_switch_done ) {
			$this->set_current_task( 'PrepareNewLiveDataset' );
			return;
		}

		if ( ! $this->success_finalization_done ) {
			$this->set_current_task( 'SuccessFinalization' );
			return;
		}

		if ( $this->incremental_baseline_enabled && ! $this->incremental_baseline_done ) {
			$this->set_current_task( 'BuildIncrementalBaseline' );
			return;
		}

		if ( $this->table_cleanup_index < count( $this->get_cleanup_tables() ) ) {
			$this->set_current_task( 'CleanupTables' );
			return;
		}

		$this->set_current_task( 'CleanupPosts' );
	}

	/**
	 * Rebuilds live-switch state from the stored live import id when checkpoints lag behind.
	 *
	 * @return void
	 */
	private function reconcile_live_switch_done() {
		if ( $this->live_switch_done ) {
			return;
		}

		$current_import_id = $this->importer->get_current_import_id( true );
		if ( (string) $current_import_id === (string) $this->import_id ) {
			$this->live_switch_done = true;
			update_option( 'arlo_import_cleanup_pending', $current_import_id );
		}
	}

	/**
	 * Returns the current live import id from durable option storage.
	 *
	 * @return string|int|null Current live import id, or null when unset.
	 */
	private function get_current_live_import_id( $lock_for_update = false ) {
		return $this->importer->get_current_import_id( true, $lock_for_update );
	}

	/**
	 * Returns whether an import id candidate is safe for cleanup queries.
	 *
	 * @param string|int|null $import_id Import id candidate from durable storage.
	 * @return bool True when cleanup can use the value as a positive integer import id.
	 */
	private function is_valid_import_id( $import_id ) {
		if ( is_int( $import_id ) ) {
			return $import_id > 0;
		}

		if ( ! is_string( $import_id ) || $import_id === '' ) {
			return false;
		}

		return ctype_digit( $import_id ) && intval( $import_id ) > 0;
	}

	/**
	 * Merges a Finish phase-state fragment into scheduler task data.
	 *
	 * @param array<string, int|string|null> $state Nested Finish phase-state fragment.
	 * @return bool True when the mutex-owned task-data write succeeded.
	 */
	private function persist_phase_state( $state ) {
		$data = array(
			'subtask_state' => $state,
		);

		if ( isset( $state['live_switch_done'] ) ) {
			$data['live_switch_done'] = $state['live_switch_done'];
		}

		return $this->importer->update_task_data_if_mutex_owned( $this->task_id, $data );
	}

	/**
	 * Persists the provisional nested retry counter for this Finish attempt.
	 *
	 * @param array<string, int> $state Nested retry-state fragment.
	 * @return bool True when the mutex-owned task-data write succeeded.
	 */
	private function persist_retry_state( $state ) {
		$data                  = array();
		$data['subtask_state'] = $state;

		return $this->importer->update_task_data_if_mutex_owned( $this->task_id, $data );
	}

	/**
	 * Clears the provisional retry charge for a non-throwing Finish attempt.
	 *
	 * @return void
	 */
	private function clear_provisional_retry_charge() {
		if ( $this->current_task_retry > 0 ) {
			// set_state() charged this kick provisionally; successful deferrals must not consume retry budget.
			--$this->current_task_retry;
		}
	}
}
