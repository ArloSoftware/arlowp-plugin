<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Immutable ordered list of fragment subtasks selected for one snapshot fragment.
 *
 * ProcessFragment persists this list in checkpoint state and uses next() on later
 * wakes to skip fragment steps that have no payload.
 */
final class FragmentStepPlan {
	/**
	 * Ordered subtask keys.
	 *
	 * @var string[]
	 */
	private $steps;

	/**
	 * Creates a plan from caller-validated subtask keys.
	 *
	 * @param string[] $steps Ordered subtask keys.
	 */
	public function __construct( array $steps ) {
		$this->steps = array_values( array_unique( array_filter( $steps, 'is_string' ) ) );
	}

	/**
	 * Returns the first planned subtask key.
	 *
	 * @return string|null First subtask key, or null when the plan is empty.
	 */
	public function first(): ?string {
		return $this->steps[0] ?? null;
	}

	/**
	 * Returns whether the plan contains the supplied subtask key.
	 *
	 * @param string $step Subtask key to locate.
	 * @return bool True when the subtask is present in the plan.
	 */
	public function contains( string $step ): bool {
		return in_array( $step, $this->steps, true );
	}

	/**
	 * Returns the planned subtask after the supplied key.
	 *
	 * @param string $current Current subtask key.
	 * @return string|null Next subtask key, or null when current is absent or last.
	 */
	public function next( string $current ): ?string {
		$index = array_search( $current, $this->steps, true );

		return $index === false ? null : ( $this->steps[ $index + 1 ] ?? null );
	}

	/**
	 * Returns the ordered subtask keys.
	 *
	 * @return string[] Ordered subtask keys.
	 */
	public function to_array(): array {
		return $this->steps;
	}
}
