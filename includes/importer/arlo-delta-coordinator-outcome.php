<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Immutable route result returned by the no-write delta coordinator.
 */
class DeltaCoordinatorOutcome {
	const CONTINUE_FULL_SHADOW = 'continue_full_shadow';
	const TERMINAL_SUCCESS     = 'terminal_delta_success';

	/** @var string */
	private $route;

	/** @var string */
	private $completion_detail = '';

	/**
	 * @param string $route Named outer-importer route.
	 * @param string $completion_detail Human-readable successful completion detail.
	 */
	public function __construct( string $route, string $completion_detail = '' ) {
		if ( ! in_array( $route, [ self::CONTINUE_FULL_SHADOW, self::TERMINAL_SUCCESS ], true ) ) {
			throw new \InvalidArgumentException( 'Delta coordinator route is invalid.' );
		}

		$this->route             = $route;
		$this->completion_detail = $completion_detail;
	}

	/**
	 * @return string Named outer-importer route.
	 */
	public function get_route(): string {
		return $this->route;
	}

	/**
	 * Returns the optional human-readable detail for a terminal delta success.
	 *
	 * @return string Human-readable successful completion detail.
	 */
	public function get_completion_detail(): string {
		return $this->completion_detail;
	}
}
