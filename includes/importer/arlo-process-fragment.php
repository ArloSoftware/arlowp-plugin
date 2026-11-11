<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;
use ArloTraining\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Orchestrates the subtask pipeline for one imported snapshot fragment.
 *
 * This task loads the stored fragment payload, advances through the ordered
 * entity subtasks, and persists fragment-level checkpoint state so retries can
 * resume at the correct subtask and iteration.
 */
class ProcessFragment extends AbstractImportStep {

	protected $data_json;

	/**
	 * Valid step plan for the current fragment, when one was stored or computed.
	 *
	 * @var FragmentStepPlan|null
	 */
	private $fragment_step_plan = null;

	/**
	 * JSON scalar persisted in checkpoint state for the current fragment's plan.
	 *
	 * @var string|null
	 */
	private $fragment_step_plan_json = null;

	//the keys in this array have to match with the keys in the JSON
	//except irregular_tasks
	public $import_tasks = [
		'Download'         => 'Download fragment file',
		'TimeZones'        => 'Time zones',
		'Presenters'       => 'Presenters',
		'Venues'           => 'Venues',
		'Templates'        => 'Event templates',
		'Events'           => 'Events',
		'OnlineActivities' => 'Online activities',
		'Categories'       => 'Categories',
		'CategoryItems'    => 'Category order',
	];

	public $current_task;
	public $current_task_class;
	public $current_task_num;
	public $current_task_desc      = '';
	public $current_task_iteration = 0;
	public $current_task_retry     = 0;

	private $irregular_tasks = [
		'Download',
	];

	public $uri;


	protected function import_entity( $entity_payload ) {}

	public function set_state( $state ) {
		$task_keys                = array_keys( $this->import_tasks );
		$retry_state              = [];
		$clear_fragment_step_plan = false;
		$moved_to                 = null;
		if ( ! empty( $state ) ) {
			if ( ! empty( $state->current_subtask ) ) {
				$this->hydrate_fragment_step_plan( $state, $state->current_subtask );
				$this->set_current_task( $state->current_subtask );
				$this->current_task_iteration = ( isset( $state->iteration ) && is_numeric( $state->iteration ) ? $state->iteration + 1 : 0 );
			} elseif ( ! empty( $state->finished_subtask ) ) {
				// A valid fragment plan jumps directly to the next payload-bearing step, skipping empty entity steps.
				$fragment_step_plan = $this->hydrate_fragment_step_plan( $state, $state->finished_subtask );
				if ( ! is_null( $fragment_step_plan ) ) {
					$next_task = $fragment_step_plan->next( $state->finished_subtask );
					if ( ! is_null( $next_task ) ) {
						$this->set_current_task( $next_task );
						$state->current_subtask_retry = 0;
						$moved_to                     = $next_task;
					} else {
						// The finished step was the last planned step, so this fragment is done.
						$this->iteration_finished = true;
					}
				} else {
					// No valid plan was persisted; fall back to the original full subtask order so no work is skipped.
					$k = array_search( $state->finished_subtask, $task_keys, true );
					if ( $k !== false && isset( $task_keys[ ++$k ] ) ) {
						$this->set_current_task( $task_keys[ $k ] );
						$state->current_subtask_retry = 0;
						$moved_to                     = $task_keys[ $k ];
						// The rejected plan must not survive the move, or a failed attempt would validate it against the new step.
						$clear_fragment_step_plan = true;
					} else {
						// The finished step was the last step in the fallback order, so this fragment is done.
						$this->iteration_finished = true;
					}
				}
			} else {
				// Fresh fragment state starts at Download and clears any prior fragment plan.
				$this->set_current_task( $task_keys[0] );
				$clear_fragment_step_plan = true;
			}
		} else {
			// Brand-new fragment state starts at Download and clears any prior fragment plan.
			$this->set_current_task( $task_keys[0] );
			$clear_fragment_step_plan = true;
		}

		if ( $this->iteration_finished ) {
			// Boundary wakes do not run a fragment subtask, so they should not consume nested retry budget.
			$this->current_task_retry = 0;
		} else {
			$this->current_task_retry = ( isset( $state->current_subtask_retry ) ? $state->current_subtask_retry + 1 : $this->current_task_retry + 1 );
		}
		$retry_state['current_subtask_retry'] = $this->current_task_retry;
		if ( $clear_fragment_step_plan ) {
			$retry_state['fragment_step_plan'] = null;
		}
		if ( ! is_null( $moved_to ) ) {
			// Record the move with the counter, otherwise the stored state still reads "previous step
			// finished" and the next wake resets the counter, so a failing step is never capped.
			$retry_state['finished_subtask'] = null;
			$retry_state['current_subtask']  = $moved_to;
		}
		// Persist only the incremented nested retry counter. Passing get_state() here
		// would write iteration = 0 and other defaults (current_task_class is null at
		// this point), overwriting the real fragment-subtask position stored in
		// subtask_state by the previous successful kick.
		if ( ! $this->update_task_data_for_retry( $retry_state ) ) {
			throw new \Exception( 'Retry state write rejected - mutex ownership lost.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the surrounding import pipeline and never rendered in a browser context.
		}
	}

	public function get_state() {
		$state = [
			'finished_subtask'      => null,
			'current_subtask'       => null,
			'current_subtask_desc'  => ! is_null( $this->current_task ) && isset( $this->import_tasks[ $this->current_task ] ) ? $this->import_tasks[ $this->current_task ] : '',
			'current_subtask_num'   => array_search( $this->current_task, array_keys( $this->import_tasks ), true ),
			'iteration'             => null,
			'iteration_finished'    => ( $this->iteration_finished ? 1 : 0 ),
			'current_subtask_retry' => $this->current_task_retry,
			'fragment_step_plan'    => $this->fragment_step_plan_json,
		];

		if ( isset( $this->current_task_class ) && $this->current_task_class->is_finished ) {
			$state['finished_subtask'] = $this->current_task;
		} else {
			$state['current_subtask'] = $this->current_task;
			$state['iteration']       = isset( $this->current_task_class->iteration ) ? $this->current_task_class->iteration : 0;
		}

		return $state;
	}

	private function update_task_data_for_retry( $state ) {
		$data['subtask_state'] = $state;

		return $this->importer->update_task_data_if_mutex_owned( $this->task_id, $data );
	}

	public function set_current_task( $task_step ) {
		$this->current_task      = $task_step;
		$this->current_task_num  = array_search( $this->current_task, array_keys( $this->import_tasks ), true );
		$this->current_task_desc = $this->import_tasks[ $this->current_task ];
	}

	private function get_data_json() {
		$import_iteration = $this->iteration + 1;
		$item             = $this->importing_parts->get_import_part( "fragment", $import_iteration, $this->import_id );

		if ( empty( $item ) ) {
			throw new \Exception( "Import Error: the import \"fragment\" part cannot be found" );
		}
		if ( empty( $item->import_text ) ) {
			throw new \Exception( "Import Error: the content of the fragment import part is empty" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		$this->data_json = json_decode( $item->import_text );

		if ( is_null( $this->data_json ) ) {
			throw new \Exception( "JSON error: " . json_last_error_msg() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		Logger::log_diagnostic(
			Logger::DIAGNOSTIC_VERBOSITY_DEBUG,
			function () use ( $import_iteration ) {
				return 'Snapshot fragment decoded [fragment=' . $import_iteration . '; items=' . $this->summarize_fragment_payload_counts() . ']';
			},
			$this->import_id
		);
	}
	public function run() {
		global $wpdb;
		$import_task = $this->current_task;

		$this->data_json = null;
		if ( $this->current_task_num > 0 ) {
			$this->get_data_json();
		}

		$class_name = "ArloTraining\Importer\\" . $import_task;

		$this->current_task_class = new $class_name( $this->importer, $this->message_handler, $this->get_payload_for_task( $import_task ), $this->current_task_iteration, $this->api_client, $this->scheduler, $this->importing_parts );
		$this->current_task_class->set_legacy_region_id_handling( $this->legacy_region_id_handling );
		if ( 'Download' === $import_task && $this->importer->get_snapshot_part_provider() instanceof SnapshotPartProvider ) {
			$this->current_task_class->set_snapshot_part_provider( $this->importer->get_snapshot_part_provider() );
		}
		Logger::log_diagnostic(
			Logger::DIAGNOSTIC_VERBOSITY_DEBUG,
			function () use ( $import_task ) {
				return 'Processing snapshot fragment step [fragment=' . ( $this->iteration + 1 ) . '; step=' . $import_task . '; iteration=' . $this->current_task_iteration . '; items=' . $this->get_payload_item_count( $import_task ) . '; retry=' . $this->current_task_retry . ']';
			},
			$this->import_id
		);

		if ( ! empty( $this->data_json->$import_task ) || in_array( $import_task, $this->irregular_tasks, true ) ) {
			//we need to do some special setup for different tasks
			switch ( $this->current_task ) {
				case 'Download':
					$import = $this->importer->get_import_entry( $this->import_id, null, 1 );
					if ( ! is_null( $import ) ) {
						$this->current_task_class->uri              = $this->uri;
						$this->current_task_class->import_part      = "fragment";
						$this->current_task_class->import_iteration = $this->iteration + 1;
						$this->current_task_class->response_json    = json_decode( $import->response_json );
					} else {
						throw new \Exception( 'Couldn\'t retrieve the import from database' );
					}
					break;
			}
			try {
				$this->current_task_class->run();
				if ( $import_task === 'Download' && $this->current_task_class->is_finished ) {
					// Download just stored the fragment; build the per-fragment plan from its contents for later wakes.
					$this->get_data_json();
					$this->build_fragment_step_plan();
				}
				--$this->current_task_retry;
			} catch ( \Exception $e ) {
				throw new \Exception( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
		} else {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Skipping empty snapshot fragment step [fragment=' . ( $this->iteration + 1 ) . '; step=' . $import_task . ']', $this->import_id );
			$this->current_task_class->is_finished = true;
		}
	}

	/**
	 * Summarize decoded fragment payload counts for support diagnostics.
	 *
	 * @return string Comma-separated task=count pairs for the current fragment.
	 */
	private function summarize_fragment_payload_counts() {
		$counts = [];
		foreach ( $this->import_tasks as $task => $description ) {
			if ( $task === 'Download' ) {
				continue;
			}

			$count = $this->get_payload_item_count( $task );
			if ( $count > 0 ) {
				$counts[] = $task . '=' . $count;
			}
		}

		return empty( $counts ) ? 'none' : implode( ', ', $counts );
	}

	/**
	 * Count the decoded payload items for a fragment subtask.
	 *
	 * @param string $task Fragment subtask key.
	 * @return int Number of decoded items for the task.
	 */
	private function get_payload_item_count( $task ) {
		if ( empty( $this->data_json ) || empty( $this->data_json->$task ) ) {
			return 0;
		}

		return is_array( $this->data_json->$task ) ? count( $this->data_json->$task ) : 1;
	}

	/**
	 * Returns the decoded payload slice for a fragment subtask.
	 *
	 * @param string $task Fragment subtask key.
	 * @return mixed|null Payload slice for the task, or null when absent or empty.
	 */
	private function get_payload_for_task( $task ) {
		return ! empty( $this->data_json->$task ) ? $this->data_json->$task : null;
	}

	/**
	 * Hydrates a stored fragment step plan when the checkpoint value is fully valid.
	 *
	 * @param object|null $state         Stored fragment subtask state.
	 * @param string|null $required_step Step that must be present in the stored plan.
	 * @return FragmentStepPlan|null Valid fragment step plan, or null to fall back.
	 */
	private function hydrate_fragment_step_plan( $state, $required_step = null ): ?FragmentStepPlan {
		$this->fragment_step_plan      = null;
		$this->fragment_step_plan_json = null;

		if ( ! is_object( $state ) || ! property_exists( $state, 'fragment_step_plan' ) || ! is_string( $state->fragment_step_plan ) ) {
			return null;
		}

		$steps = json_decode( $state->fragment_step_plan, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! $this->is_valid_fragment_step_plan_steps( $steps, $required_step ) ) {
			return null;
		}

		$this->fragment_step_plan      = new FragmentStepPlan( $steps );
		$this->fragment_step_plan_json = $state->fragment_step_plan;

		return $this->fragment_step_plan;
	}

	/**
	 * Computes and stores the fragment step plan for the decoded fragment payload.
	 *
	 * @return void
	 */
	private function build_fragment_step_plan(): void {
		$steps = [ 'Download' ];

		foreach ( $this->import_tasks as $task => $description ) {
			if ( $task === 'Download' ) {
				continue;
			}

			$class_name = "ArloTraining\Importer\\" . $task;
			$payload    = $this->get_payload_for_task( $task );
			$step       = new $class_name( $this->importer, $this->message_handler, $payload, 0, $this->api_client, $this->scheduler, $this->importing_parts );

			if ( $step->should_process( $payload ) ) {
				$steps[] = $task;
			}
		}

		$this->fragment_step_plan      = new FragmentStepPlan( $steps );
		$this->fragment_step_plan_json = wp_json_encode( $this->fragment_step_plan->to_array() );
		if ( false === $this->fragment_step_plan_json ) {
			throw new \Exception( 'Fragment step plan could not be encoded' );
		}

		Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Fragment step plan computed [fragment=' . ( $this->iteration + 1 ) . '; steps=' . implode( ', ', $steps ) . ']', $this->import_id );
	}

	/**
	 * Returns whether a decoded step list is safe to use for fragment advancement.
	 *
	 * @param mixed       $steps         Decoded plan value.
	 * @param string|null $required_step Step that must be present in the plan.
	 * @return bool True when the decoded value is a complete valid step list.
	 */
	private function is_valid_fragment_step_plan_steps( $steps, $required_step = null ): bool {
		if ( empty( $steps ) || ! is_array( $steps ) || array_values( $steps ) !== $steps || $steps[0] !== 'Download' ) {
			return false;
		}

		$allowed_tasks = array_keys( $this->import_tasks );
		$seen          = [];
		foreach ( $steps as $step ) {
			if ( ! is_string( $step ) || ! in_array( $step, $allowed_tasks, true ) || isset( $seen[ $step ] ) ) {
				return false;
			}

			$seen[ $step ] = true;
		}

		return is_null( $required_step ) || isset( $seen[ $required_step ] );
	}
}
