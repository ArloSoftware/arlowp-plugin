<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

use ArloTraining\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieves JSON content for fragments declared by one admitted snapshot image.
 */
class SnapshotFragmentContentProvider {
	/** @var SnapshotImageSpec */
	private $snapshot_image;

	/** @var SnapshotPartProvider */
	private $part_provider;

	/** @var SnapshotContext */
	private $snapshot_context;

	/** @var array<int,string> */
	private $fragment_contents = [];

	/** @var int */
	private $decoded_bytes = 0;

	/** @var callable|null */
	private $before_fragment_fetch_hook;

	/**
	 * @param SnapshotImageSpec     $snapshot_image Validated admitted snapshot image fragment manifest.
	 * @param SnapshotPartProvider   $part_provider Request-local snapshot part provider.
	 * @param SnapshotContext        $snapshot_context Admitted transport and decryption context.
	 */
	public function __construct( SnapshotImageSpec $snapshot_image, SnapshotPartProvider $part_provider, SnapshotContext $snapshot_context ) {
		$this->snapshot_image   = $snapshot_image;
		$this->part_provider    = $part_provider;
		$this->snapshot_context = $snapshot_context;
	}

	/**
	 * Retrieves the JSON content for one fragment declared by the snapshot image.
	 *
	 * @param int $fragment_element_index Zero-based snapshot-image fragment index.
	 * @return string Decrypted fragment JSON content.
	 * @throws \ArloTraining\SnapshotProcessException When the requested fragment is not declared by the snapshot image.
	 */
	public function get_fragment_content( int $fragment_element_index ): string {
		if ( isset( $this->fragment_contents[ $fragment_element_index ] ) ) {
			return $this->fragment_contents[ $fragment_element_index ];
		}

		$fragment_uri = $this->snapshot_image->get_fragment_uri( $fragment_element_index );
		if ( null !== $this->before_fragment_fetch_hook ) {
			call_user_func( $this->before_fragment_fetch_hook, $fragment_element_index );
		}
		$fragment_number = $fragment_element_index + 1;
		Logger::log( 'Import is running: Downloading and processing fragment #' . $fragment_number, $this->snapshot_context->import_id );
		$content = $this->part_provider->get_snapshot_part_content( $this->snapshot_context->create_part_request( $fragment_uri, SnapshotPartRequest::PART_TYPE_FRAGMENT, $fragment_element_index ) );
		$this->log_fragment_decoded( $content, $fragment_number );
		$this->fragment_contents[ $fragment_element_index ] = $content;
		$this->decoded_bytes                               += strlen( $content );

		return $content;
	}

	/**
	 * @return int Number of distinct fragment indexes fetched in this request.
	 */
	public function get_unique_fragment_count(): int {
		return count( $this->fragment_contents );
	}

	/**
	 * @return int Aggregate decoded JSON bytes fetched in this request.
	 */
	public function get_decoded_bytes(): int {
		return $this->decoded_bytes;
	}

	/**
	 * Sets an optional request-local callback invoked before each uncached fragment fetch.
	 *
	 * @param callable|null $hook Callback receiving the fragment element index.
	 * @return void
	 */
	public function set_before_fragment_fetch_hook( ?callable $hook ): void {
		$this->before_fragment_fetch_hook = $hook;
	}

	/**
	 * Logs the decoded entity counts using the same fragment diagnostic shape as full imports.
	 *
	 * @param string $content Decrypted fragment JSON.
	 * @param int    $fragment_number One-based fragment number.
	 * @return void
	 */
	private function log_fragment_decoded( string $content, int $fragment_number ): void {
		Logger::log_diagnostic(
			Logger::DIAGNOSTIC_VERBOSITY_DEBUG,
			function () use ( $content, $fragment_number ) {
				$payload = json_decode( $content );
				if ( ! is_object( $payload ) ) {
					return 'Snapshot fragment decoded [fragment=' . $fragment_number . '; items=unavailable]';
				}

				$counts = [];
				foreach ( [ 'TimeZones', 'Presenters', 'Venues', 'Templates', 'Events', 'OnlineActivities', 'Categories', 'CategoryItems' ] as $part ) {
					if ( empty( $payload->$part ) ) {
						continue;
					}
					$counts[] = $part . '=' . ( is_array( $payload->$part ) ? count( $payload->$part ) : 1 );
				}

				return 'Snapshot fragment decoded [fragment=' . $fragment_number . '; items=' . ( empty( $counts ) ? 'none' : implode( ', ', $counts ) ) . ']';
			},
			$this->snapshot_context->import_id
		);
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName