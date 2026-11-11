<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

use ArloTraining\CacheControl;
use ArloTraining\DeltaFragmentLimitException;
use ArloTraining\Logger;
use ArloTraining\DeltaFatalException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Import step that attempts to perform a delta run, conditionally falling back
 * to ordinary full shadow.
 *
 * Exposes the resulting terminal-success or full-shadow route through
 * DeltaCoordinatorOutcome.
 */
class DeltaRunCoordinator extends AbstractImportStep {
	public const TEST_FAULT_OVERLAY_PRE_COMMIT                   = 'overlay_pre_commit';
	public const TEST_FAULT_AFTER_FIRST_COMMITTED_ROOT_ABORT     = 'after_first_committed_root_abort';
	public const TEST_FAULT_BEFORE_BASELINE_PROMOTION_MUTEX_LOSS = 'before_baseline_promotion_mutex_loss';

	/** @var IncrementalRunRepository|null */
	private $run_repository;

	/** @var int */
	private $incremental_run_id = 0;

	/** @var DeltaCoordinatorOutcome|null */
	private $outcome;

	/** @var ImportSuccessFinalizer|null */
	private $success_finalization;

	/** @var EventAvailabilityOverlayWriter|null */
	private $availability_writer;

	/** @var IncrementalBaselineRepository|null */
	private $baseline_repository;

	/** @var float */
	private $request_started_at_milliseconds = 0.0;

	/** @var string|null */
	private $test_fault_selector;

	/** @var DeltaRunParameters|null */
	private $parameters;

	/**
	 * Attempts to complete an admitted delta run without data writes, conditionally
	 * falling back to ordinary full shadow when analysis cannot establish a no-op.
	 * Writes the resulting route to get_outcome().
	 *
	 * @return void
	 */
	public function run() {
		$this->request_started_at_milliseconds = $this->get_prewrite_clock_milliseconds();
		$repository                            = $this->get_run_repository();
		$no_op_sealed                          = false;
		Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check started for the admitted manifest [run_id=' . $this->incremental_run_id . ']', $this->import_id );
		try {
			try {
				$run = $repository->get_run( $this->incremental_run_id );
			} catch ( \Throwable $exception ) {
				$this->handle_prewrite_failure( $repository, $exception, 'load_run' );
				$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::CONTINUE_FULL_SHADOW );
				return;
			}
			// An absent or mismatched durable run must fail closed to ordinary full shadow.
			if ( ! is_object( $run ) || (int) $run->admitted_import_id !== (int) $this->import_id ) {
				$this->log_full_snapshot_fallback( 'Snapshot change check could not use its admitted run; processing the full snapshot [run_found=' . ( is_object( $run ) ? 'true' : 'false' ) . '; expected_import_id=' . (int) $this->import_id . '; actual_import_id=' . ( is_object( $run ) && isset( $run->admitted_import_id ) ? (int) $run->admitted_import_id : 'none' ) . ']' );
				$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::CONTINUE_FULL_SHADOW );
				return;
			}
			$terminal_result = $run->terminal_result ?? IncrementalRunRepository::RESULT_PENDING;
			// Reuse a prior sealed decision instead of repeating comparison or staging work.
			if ( IncrementalRunRepository::RESULT_FULL_SHADOW === $terminal_result ) {
				if ( IncrementalRunRepository::REASON_NO_OP_PROOF_LOST === ( $run->fallback_reason ?? null ) ) {
					// Equal-census admission intentionally stages no candidate. A later lost no-op
					// proof is still pre-write, so same-import full shadow can safely proceed.
					Logger::log( 'Snapshot changes detected.', $this->import_id );
					Logger::log( 'Processing full snapshot.', $this->import_id );
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check reused a candidate-less no-op fallback; processing the full snapshot [run_id=' . $this->incremental_run_id . ']', $this->import_id );
					$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::CONTINUE_FULL_SHADOW );
					return;
				}
				try {
					$repository->assert_full_shadow_run_integrity( $this->incremental_run_id );
				} catch ( \Throwable $exception ) {
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Sealed full-snapshot decision failed integrity verification [run_id=' . $this->incremental_run_id . '; stage=assert_full_shadow_integrity; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
					throw $exception;
				}
				Logger::log( 'Snapshot changes detected.', $this->import_id );
				Logger::log( 'Processing full snapshot.', $this->import_id );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check reused a previously sealed full-snapshot decision [run_id=' . $this->incremental_run_id . ']', $this->import_id );
				$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::CONTINUE_FULL_SHADOW );
				return;
			}
			if ( IncrementalRunRepository::RESULT_NO_OP === $terminal_result ) {
				$this->finalize_no_op( $repository );
				return;
			}

			try {
				$baseline         = $repository->get_current_clean_baseline( (int) $run->schema_level, $run->configuration_signature );
				$active_import_id = (int) $this->importer->get_current_import_id( true );
				$is_unchanged     = is_object( $baseline )
					&& $active_import_id === (int) $run->expected_active_import_id
					&& (int) $baseline->current_baseline_generation_id === (int) $run->expected_baseline_generation_id
					&& (int) $baseline->projection_count === (int) $run->snapshot_manifest_count
					&& is_string( $baseline->census_tag ?? null )
					&& hash_equals( $baseline->census_tag, $run->census_tag );

				// Equality is safe only while the exact expected clean baseline remains live.
				if ( $is_unchanged && $repository->try_mark_no_op_run_success_if_current( $this->incremental_run_id ) ) {
					$no_op_sealed = true;
				}

				if ( $is_unchanged && ! $no_op_sealed ) {
					// A declined no-op compare-and-set has no live write, so same-import full shadow remains the simple recovery path.
					$active_import_id = (int) $this->importer->get_current_import_id( true );
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'No-op compare-and-set was declined because its locked proof no longer matched; processing full snapshot [run_id=' . $this->incremental_run_id . '; stage=no_op_compare_and_set_declined; expected_active_import_id=' . (int) $run->expected_active_import_id . '; observed_active_import_id=' . $active_import_id . '; expected_baseline_generation_id=' . (int) $run->expected_baseline_generation_id . '; prelock_baseline_generation_id=' . ( is_object( $baseline ) ? (int) $baseline->current_baseline_generation_id : 'none' ) . '; prelock_entries=' . ( is_object( $baseline ) ? (int) $baseline->projection_count : 'none' ) . ']', $this->import_id );
					$repository->resolve_run_as_full_shadow( $this->incremental_run_id, $active_import_id, IncrementalRunRepository::REASON_NO_OP_PROOF_LOST );
					Logger::log( 'Snapshot changes detected.', $this->import_id );
					Logger::log( 'Processing full snapshot.', $this->import_id );
					$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::CONTINUE_FULL_SHADOW );
					return;
				} elseif ( ! $is_unchanged ) {
					// This keeps the selection attempt within the admitted live-import and baseline boundary.
					$can_attempt_availability_selection = is_object( $baseline )
						&& $active_import_id === (int) $run->expected_active_import_id
						&& (int) $baseline->current_baseline_generation_id === (int) $run->expected_baseline_generation_id
						&& (int) $baseline->projection_count === (int) $run->snapshot_manifest_count;
					if ( $can_attempt_availability_selection ) {
						if ( is_string( $baseline->census_tag ?? null ) && ! hash_equals( $baseline->census_tag, $run->census_tag ) ) {
							$this->log_changed_manifest_tags( $repository, $run );
						}
						$selections = $repository->try_select_bounded_event_availability_delta( $this->incremental_run_id, (int) $run->expected_baseline_generation_id, (int) $run->snapshot_manifest_count, $this->get_parameters()->max_selected_event_roots );
						if ( is_array( $selections ) ) {
							$this->apply_selected_event_availability( $repository, $run, $selections );
							return;
						}
					}
					$repository->resolve_run_as_full_shadow( $this->incremental_run_id, $active_import_id );
				}
			} catch ( DeltaFatalException $exception ) {
				throw $exception;
			} catch ( \Throwable $exception ) {
				$this->handle_prewrite_failure( $repository, $exception, 'compare_or_seal' );
				$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::CONTINUE_FULL_SHADOW );
				return;
			}

			if ( $no_op_sealed ) {
				$this->finalize_no_op( $repository );
				return;
			}

			// A sealed decision and its candidate proof must remain intact before continuation.
			try {
				$repository->assert_full_shadow_run_integrity( $this->incremental_run_id );
			} catch ( \Throwable $exception ) {
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Sealed full-snapshot decision failed integrity verification [run_id=' . $this->incremental_run_id . '; stage=assert_full_shadow_integrity; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
				throw $exception;
			}
			Logger::log( 'Snapshot changes detected.', $this->import_id );
			Logger::log( 'Processing full snapshot.', $this->import_id );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check selected full-snapshot continuation; processing the full snapshot [run_id=' . $this->incremental_run_id . '; expected_active_import_id=' . (int) $run->expected_active_import_id . '; active_import_id=' . $active_import_id . '; expected_baseline_generation_id=' . (int) $run->expected_baseline_generation_id . '; baseline_generation_id=' . ( is_object( $baseline ) ? (int) $baseline->current_baseline_generation_id : 'none' ) . '; expected_entries=' . (int) $run->snapshot_manifest_count . '; baseline_entries=' . ( is_object( $baseline ) ? (int) $baseline->projection_count : 'none' ) . '; census_tag_matches=' . ( is_object( $baseline ) && is_string( $baseline->census_tag ?? null ) && hash_equals( $baseline->census_tag, $run->census_tag ) ? 'true' : 'false' ) . ']', $this->import_id );
			$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::CONTINUE_FULL_SHADOW );
		} finally {
			$this->is_finished = true;
		}
	}

	/**
	 * Ignores entity payloads because this step routes a run rather than imports entities.
	 *
	 * @param mixed $entity_payload Unused coordinator payload item.
	 * @return void
	 */
	protected function import_entity( $entity_payload ) {}

	/**
	 * Applies the bounded selected roots then promotes their complete staged baseline.
	 *
	 * @param IncrementalRunRepository $repository Run persistence boundary.
	 * @param object                   $run Immutable admitted run.
	 * @param array<int,object>        $selections Selected Event manifest intents.
	 * @return void
	 */
	private function apply_selected_event_availability( IncrementalRunRepository $repository, object $run, array $selections ): void {
		$has_committed_bundle = false;
		try {
			$fragment_indexes = [];
			foreach ( $selections as $selection ) {
				if ( ! is_object( $selection ) || ! isset( $selection->source_fragment_element_index ) || ! is_numeric( $selection->source_fragment_element_index ) ) {
					throw new \RuntimeException( 'Selected Event availability work is invalid.' );
				}
				$fragment_indexes[ (int) $selection->source_fragment_element_index ] = true;
			}
			$parameters = $this->get_parameters();
			if ( count( $fragment_indexes ) > $parameters->max_selected_fragment_count ) {
				throw new DeltaFragmentLimitException( 'Selected Event availability requires too many fragments [required_fragments=' . count( $fragment_indexes ) . '; fragment_limit=' . $parameters->max_selected_fragment_count . '].' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by this coordinator and logged as an internal diagnostic; it is never rendered.
			}
			if ( [] === $selections || count( $selections ) > $parameters->max_selected_event_roots || ! $this->has_prewrite_budget() || ! $this->importer->confirm_import_mutex_ownership() || ! $this->importer->extend_import_mutex_ownership() ) {
				throw new \RuntimeException( 'Selected Event availability work has no active importer mutex.' );
			}
			$fragment_numbers = array_map(
				static function ( int $fragment_index ): int {
					return $fragment_index + 1;
				},
				array_keys( $fragment_indexes )
			);
			sort( $fragment_numbers, SORT_NUMERIC );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Selected Event availability fragments for pre-write retrieval [run_id=' . $this->incremental_run_id . '; selected_roots=' . count( $selections ) . '; fragments=' . implode( ',', $fragment_numbers ) . ']', $this->import_id );
			$provider = $this->importer->get_snapshot_part_provider();
			if ( ! $provider instanceof SnapshotPartProvider ) {
				throw new \RuntimeException( 'Selected Event availability work has no fragment provider.' );
			}
			$snapshot_image    = SnapshotImageSpec::from_content( $this->importer->get_admitted_snapshot_image_content() );
			$fragment_provider = new SnapshotFragmentContentProvider( $snapshot_image, $provider, $this->importer->get_admitted_snapshot_context() );
			if ( null !== $parameters->before_fragment_fetch_hook ) {
				if ( ! is_callable( $parameters->before_fragment_fetch_hook ) ) {
					throw new \RuntimeException( 'Selected Event availability fragment fetch hook is invalid.' );
				}
				$fragment_provider->set_before_fragment_fetch_hook( $parameters->before_fragment_fetch_hook );
			}
			$events = [];
			$loader = new SnapshotImageEventLoader();
			foreach ( $selections as $selection ) {
				$events[] = $loader->load( $selection, $fragment_provider );
			}
			if ( $fragment_provider->get_unique_fragment_count() > $parameters->max_selected_fragment_count || $fragment_provider->get_decoded_bytes() > $parameters->max_selected_decoded_bytes || ! $this->has_prewrite_budget() ) {
				throw new \RuntimeException( 'Selected Event availability work exceeded its pre-write budget.' );
			}
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Selected Event availability work completed pre-write retrieval [run_id=' . $this->incremental_run_id . '; selected_roots=' . count( $selections ) . '; distinct_fragments=' . $fragment_provider->get_unique_fragment_count() . '; decoded_bytes=' . $fragment_provider->get_decoded_bytes() . '; elapsed_ms=' . (int) round( $this->get_prewrite_clock_milliseconds() - $this->request_started_at_milliseconds ) . ']', $this->import_id );
			$writer = $this->get_event_availability_writer();
			if ( self::TEST_FAULT_OVERLAY_PRE_COMMIT === $this->test_fault_selector ) {
				$writer->set_before_commit_hook(
					static function (): void {
						throw new \RuntimeException( 'Selected Event availability test fault before commit.' );
					}
				);
			}
			foreach ( $events as $event_index => $event ) {
				if ( ! $this->importer->confirm_import_mutex_ownership() || ! $this->importer->extend_import_mutex_ownership() ) {
					throw new \RuntimeException( 'Import mutex was lost before Event availability write.' );
				}
				$writer->apply(
					(int) $run->expected_active_import_id,
					$event,
					0 === $event_index ? function () use ( $repository, $selections ): void {
						if ( ! $this->importer->confirm_import_mutex_ownership() || ! $this->importer->extend_import_mutex_ownership() ) {
							throw new \RuntimeException( 'Import mutex was lost before Event availability write.' );
						}
						$repository->seal_first_event_availability_write( $this->incremental_run_id, (int) $this->importer->get_current_import_id( true, true ), count( $selections ) );
					} : null,
					$parameters->event_overlay_transaction_scope
				);
				$has_committed_bundle = true;
				CacheControl::cache_delete( CacheControl::GROUP_PRIMARY_OBJECTS );
				CacheControl::invalidate_upcoming_widget_cache();
				if ( 0 === $event_index && self::TEST_FAULT_AFTER_FIRST_COMMITTED_ROOT_ABORT === $this->test_fault_selector ) {
					throw new \RuntimeException( 'Selected Event availability test fault after first committed root.' );
				}
			}
		} catch ( \Throwable $exception ) {
			if ( $exception instanceof DeltaFatalException ) {
				// Preserve an already-terminal reason such as an unacknowledged commit outcome.
				throw $exception;
			}
			if ( $has_committed_bundle || ( isset( $writer ) && $writer->has_committed_bundle() ) ) {
				throw new DeltaFatalException( 'Event availability delta failed after its first committed bundle.', 0, $exception ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The chained exception is propagated to the importer and logged; it is not rendered.
			}
			$this->handle_prewrite_failure( $repository, $exception, 'selected_event_availability' );
			$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::CONTINUE_FULL_SHADOW );
			return;
		}

		try {
			if ( self::TEST_FAULT_BEFORE_BASELINE_PROMOTION_MUTEX_LOSS === $this->test_fault_selector ) {
				throw new \RuntimeException( 'Import mutex was lost before baseline promotion.' );
			}
			if ( ! $this->importer->confirm_import_mutex_ownership() || ! $this->importer->extend_import_mutex_ownership() ) {
				throw new \RuntimeException( 'Import mutex was lost before baseline promotion.' );
			}
			$this->promote_selected_baseline( $run );
		} catch ( \Throwable $exception ) {
			throw new DeltaFatalException( 'Event availability delta failed after its first committed bundle.', 0, $exception ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The chained exception is propagated to the importer and logged; it is not rendered.
		}

		$completion_detail = $this->format_event_update_completion_detail( count( $selections ) );
		try {
			if ( $repository->try_claim_delta_run_finalization( $this->incremental_run_id ) ) {
				try {
					$this->get_success_finalization()->finalize_import( $this->importer, $this->message_handler, (int) $this->import_id );
				} catch ( \Throwable $exception ) {
					Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Event availability delta finalization failed after promotion [run_id=' . $this->incremental_run_id . '; exception=' . get_class( $exception ) . ']', $this->import_id );
				}
			}
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Event availability delta finalization claim failed after promotion [run_id=' . $this->incremental_run_id . '; exception=' . get_class( $exception ) . ']', $this->import_id );
		}
		$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::TERMINAL_SUCCESS, $completion_detail );
	}

	private function promote_selected_baseline( object $run ): void {
		$repository = $this->get_baseline_repository();
		$completed  = $repository->run_in_transaction(
			function ( IncrementalBaselineRepository $baseline_repository ) use ( $run ): bool {
				$candidate = $baseline_repository->get_locked_candidate_for_import( (int) $run->admitted_import_id );
				return (int) $this->importer->get_current_import_id( true, true ) === (int) $run->expected_active_import_id
					&& $baseline_repository->lock_and_verify_full_required_baseline_generation( (int) $run->expected_baseline_generation_id )
					&& is_object( $candidate )
					&& (int) $candidate->manifest_count === (int) $run->snapshot_manifest_count
					&& $baseline_repository->get_baseline_generation_entry_count( (int) $candidate->baseline_generation_id ) === (int) $candidate->manifest_count
					&& $baseline_repository->promote_candidate_as_baseline( $candidate );
			}
		);
		if ( ! $completed ) {
			throw new \RuntimeException( 'Selected Event availability baseline promotion was declined.' );
		}
	}

	/**
	 * @return bool Whether the bounded write window remains available.
	 */
	private function has_prewrite_budget(): bool {
		return $this->get_prewrite_clock_milliseconds() - $this->request_started_at_milliseconds < $this->get_parameters()->prewrite_budget_milliseconds;
	}

	/**
	 * Returns the request-local pre-write clock in milliseconds.
	 *
	 * @return float Milliseconds from a monotonic request-local clock.
	 */
	private function get_prewrite_clock_milliseconds(): float {
		$clock = $this->get_parameters()->prewrite_budget_clock;
		if ( null !== $clock ) {
			if ( ! is_callable( $clock ) ) {
				throw new \RuntimeException( 'Selected Event availability pre-write clock is invalid.' );
			}
			$milliseconds = call_user_func( $clock );
			if ( ! is_numeric( $milliseconds ) ) {
				throw new \RuntimeException( 'Selected Event availability pre-write clock returned an invalid value.' );
			}

			return (float) $milliseconds;
		}

		return microtime( true ) * 1000;
	}

	/**
	 * Returns the request-local parameters, creating production defaults when absent.
	 *
	 * @return DeltaRunParameters Request-local delta limits.
	 */
	private function get_parameters(): DeltaRunParameters {
		if ( null === $this->parameters ) {
			$this->parameters = new DeltaRunParameters();
		}

		return $this->parameters;
	}

	/**
	 * Returns the injected repository, creating the standard repository only when absent.
	 *
	 * @return IncrementalRunRepository Persisted delta-run boundary.
	 */
	private function get_run_repository(): IncrementalRunRepository {
		if ( null === $this->run_repository ) {
			$this->run_repository = new IncrementalRunRepository();
		}

		return $this->run_repository;
	}

	/**
	 * Returns the injected finalization helper, creating the standard helper only when absent.
	 *
	 * @return ImportSuccessFinalizer Shared finalization helper.
	 */
	private function get_success_finalization(): ImportSuccessFinalizer {
		if ( null === $this->success_finalization ) {
			$this->success_finalization = new ImportSuccessFinalizer();
		}

		return $this->success_finalization;
	}

	/**
	 * Injects the repository that persists and reads durable delta-run state.
	 *
	 * @param IncrementalRunRepository $repository Persisted delta-run boundary.
	 * @return void
	 */
	public function set_incremental_run_repository( IncrementalRunRepository $repository ): void {
		$this->run_repository = $repository;
	}

	/**
	 * Stores the admitted run checkpoint that this coordinator may route.
	 *
	 * @param int $incremental_run_id Admitted run checkpoint identifier.
	 * @return void
	 */
	public function set_incremental_run_id( int $incremental_run_id ): void {
		$this->incremental_run_id = $incremental_run_id;
	}

	/**
	 * Injects the helper responsible for shared importer success side effects.
	 *
	 * @param ImportSuccessFinalizer $success_finalization Shared finalization helper.
	 * @return void
	 */
	public function set_success_finalization( ImportSuccessFinalizer $success_finalization ): void {
		$this->success_finalization = $success_finalization;
	}

	public function set_event_availability_writer( EventAvailabilityOverlayWriter $writer ): void {
		$this->availability_writer = $writer;
	}

	public function set_incremental_baseline_repository( IncrementalBaselineRepository $repository ): void {
		$this->baseline_repository = $repository;
	}

	/**
	 * Sets request-local limits used by this coordinator run.
	 *
	 * @param DeltaRunParameters $parameters Delta coordinator parameters.
	 * @return void
	 */
	public function set_parameters( DeltaRunParameters $parameters ): void {
		$this->parameters = $parameters;
	}

	/**
	 * Installs one fixed test-only fault for the current coordinator request.
	 *
	 * @param string|null $selector Fixed fault selector, or null to disable it.
	 * @return void
	 */
	public function set_test_fault_selector( ?string $selector ): void {
		if ( null !== $selector && ! in_array( $selector, [ self::TEST_FAULT_OVERLAY_PRE_COMMIT, self::TEST_FAULT_AFTER_FIRST_COMMITTED_ROOT_ABORT, self::TEST_FAULT_BEFORE_BASELINE_PROMOTION_MUTEX_LOSS ], true ) ) {
			throw new \InvalidArgumentException( 'Delta test fault selector is invalid.' );
		}

		$this->test_fault_selector = $selector;
	}

	/**
	 * Returns the route selected by the most recent run, if any.
	 *
	 * @return DeltaCoordinatorOutcome|null Completed route decision.
	 */
	public function get_outcome(): ?DeltaCoordinatorOutcome {
		return $this->outcome;
	}

	/**
	 * Returns the injected Event availability writer, creating the standard writer when absent.
	 *
	 * @return EventAvailabilityOverlayWriter Event availability overlay writer.
	 */
	private function get_event_availability_writer(): EventAvailabilityOverlayWriter {
		if ( null === $this->availability_writer ) {
			$this->availability_writer = new EventAvailabilityOverlayWriter();
		}

		return $this->availability_writer;
	}

	/**
	 * Returns the injected baseline repository, creating the standard repository when absent.
	 *
	 * @return IncrementalBaselineRepository Incremental baseline persistence boundary.
	 */
	private function get_baseline_repository(): IncrementalBaselineRepository {
		if ( null === $this->baseline_repository ) {
			$this->baseline_repository = new IncrementalBaselineRepository();
		}

		return $this->baseline_repository;
	}


	/**
	 * Performs one-time shared side effects after a durable no-op decision.
	 *
	 * @param IncrementalRunRepository $repository Persisted delta-run boundary.
	 * @return void
	 */
	private function finalize_no_op( IncrementalRunRepository $repository ): void {
		try {
			$claimed = $repository->try_claim_no_op_run_finalization( $this->incremental_run_id );
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'No-op finalization could not claim its admitted run [run_id=' . $this->incremental_run_id . '; stage=claim_no_op_finalization; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
			throw $exception;
		}

		if ( $claimed ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot change check matched the current clean baseline; completing without processing snapshot data [run_id=' . $this->incremental_run_id . ']', $this->import_id );
			Logger::log( 'No changes to import', $this->import_id );
			try {
				$this->get_success_finalization()->finalize_import( $this->importer, $this->message_handler, (int) $this->import_id );
			} catch ( \Throwable $exception ) {
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'No-op finalization failed after the admitted run was claimed [run_id=' . $this->incremental_run_id . '; stage=success_finalization; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
				throw $exception;
			}
		} else {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'No-op finalization was already claimed by another worker [run_id=' . $this->incremental_run_id . '; stage=claim_no_op_finalization; result=already_claimed]', $this->import_id );
		}

		$this->outcome = new DeltaCoordinatorOutcome( DeltaCoordinatorOutcome::TERMINAL_SUCCESS, 'already up to date' );
	}

	/**
	 * Handles a pre-write failure by attempting to mark the pending run for full shadow.
	 *
	 * @param IncrementalRunRepository $repository Persisted delta-run boundary.
	 * @param \Throwable                $reason Failure that prevented a delta decision.
	 * @param string                    $stage Operation that failed before a decision was sealed.
	 * @return void
	 */
	private function handle_prewrite_failure( IncrementalRunRepository $repository, \Throwable $reason, string $stage ): void {
		$this->log_full_snapshot_fallback( 'Snapshot change check failed before a decision; processing the full snapshot [run_id=' . $this->incremental_run_id . '; stage=' . $stage . '; exception=' . get_class( $reason ) . '; message=' . $reason->getMessage() . ']' );
		try {
			$repository->delete_staged_target_manifest( $this->incremental_run_id );
			$repository->mark_run_full_shadow_with_reason( $this->incremental_run_id, IncrementalRunRepository::REASON_ANALYSIS_FAILED );
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Full-snapshot fallback could not persist its recovery state [run_id=' . $this->incremental_run_id . '; stage=persist_recovery; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', (int) $this->import_id );
		}
	}

	/**
	 * Logs an administrator-facing fallback message with its support diagnostic cause.
	 *
	 * @param string $diagnostic_message Support diagnostic describing the failed route.
	 * @return void
	 */
	private function log_full_snapshot_fallback( string $diagnostic_message ): void {
		Logger::log( 'Unable to apply changes. Processing full snapshot.', $this->import_id );
		Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, $diagnostic_message, $this->import_id );
	}

	/**
	 * Logs a bounded diagnostic comparison of changed tags for a census mismatch.
	 *
	 * @param IncrementalRunRepository $repository Staged-target repository.
	 * @param object                   $run Admitted immutable run.
	 * @return void
	 */
	private function log_changed_manifest_tags( IncrementalRunRepository $repository, object $run ): void {
		Logger::log_diagnostic(
			Logger::DIAGNOSTIC_VERBOSITY_DEBUG,
			function () use ( $repository, $run ): string {
				try {
					$limit          = 50;
					$entries        = $repository->get_changed_tag_entries_data( $this->incremental_run_id, (int) $run->expected_baseline_generation_id, $limit + 1 );
					$snapshot_image = SnapshotImageSpec::from_content( $this->importer->get_admitted_snapshot_image_content() );
					$fragment_count = count( $snapshot_image->get_fragment_elements() );
					$details        = [];
					foreach ( array_slice( $entries, 0, $limit ) as $entry ) {
						if ( ! is_object( $entry ) ) {
							continue;
						}
						$fragment_number = is_numeric( $entry->source_fragment_element_index ?? null ) && (int) $entry->source_fragment_element_index >= 0 ? (int) $entry->source_fragment_element_index + 1 : null;
						$details[]       = [
							'kind'                      => $entry->entity_kind ?? null,
							'key_1'                     => $entry->entity_key_1 ?? null,
							'key_2'                     => $entry->entity_key_2 ?? null,
							'region'                    => $entry->entity_region_code ?? null,
							'source_fragment_number'    => $fragment_number,
							'baseline_structural_tag'   => $this->format_tag_for_diagnostic( $entry->baseline_structural_tag ?? null ),
							'snapshot_structural_tag'   => $this->format_tag_for_diagnostic( $entry->snapshot_structural_tag ?? null ),
							'baseline_availability_tag' => $this->format_tag_for_diagnostic( $entry->baseline_availability_tag ?? null ),
							'snapshot_availability_tag' => $this->format_tag_for_diagnostic( $entry->snapshot_availability_tag ?? null ),
						];
					}

					return 'Snapshot manifest tag differences [run_id=' . $this->incremental_run_id . '; changed_entries=' . count( $entries ) . '; truncated=' . ( count( $entries ) > $limit ? 'true' : 'false' ) . '; listed_fragments=' . $fragment_count . '; entries=' . wp_json_encode( $details, JSON_HEX_TAG ) . ']';
				} catch ( \Throwable $exception ) {
					return 'Snapshot manifest tag differences are unavailable [run_id=' . $this->incremental_run_id . '; reason=' . get_class( $exception ) . ']';
				}
			},
			$this->import_id
		);
	}

	/**
	 * @param mixed $tag Raw 32-byte tag, or null.
	 * @return string Canonical base64 tag or the null sentinel.
	 */
	private function format_tag_for_diagnostic( $tag ): string {
		return is_string( $tag ) ? base64_encode( $tag ) : 'null'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Converts fixed-length binary manifest tags to canonical support-diagnostic values.
	}

	/**
	 * Formats the selected Event-root count for terminal delta status and logging.
	 *
	 * @param int $updated_event_count Number of selected Event roots updated.
	 * @return string Human-readable delta completion detail.
	 */
	private function format_event_update_completion_detail( int $updated_event_count ): string {
		return $updated_event_count . ' event' . ( 1 === $updated_event_count ? '' : 's' ) . ' updated';
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
