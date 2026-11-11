<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Reusable transport and decryption metadata for parts of one platform snapshot.
 *
 * This context does not identify a downloadable part. A concrete URI, part type,
 * and optional fragment index are supplied when creating a SnapshotPartRequest.
 */
class SnapshotContext {
	/** @var string */
	public $platform_hostname;

	/** @var string */
	public $encryption_key;

	/** @var string */
	public $encryption_method;

	/** @var int */
	public $import_id;

	/**
	 * @param string $platform_hostname Platform hostname from the snapshot response.
	 * @param string $encryption_key Snapshot encryption key.
	 * @param string $encryption_method Snapshot encryption method.
	 * @param int    $import_id Import identifier for diagnostics.
	 */
	public function __construct( string $platform_hostname, string $encryption_key, string $encryption_method, int $import_id ) {
		$this->platform_hostname = $platform_hostname;
		$this->encryption_key    = $encryption_key;
		$this->encryption_method = $encryption_method;
		$this->import_id         = $import_id;
	}

	/**
	 * @param string   $uri URI for the requested root snapshot or element fragment JSON document.
	 * @param string   $part_type Part type: `SnapshotPartRequest::PART_TYPE_SNAPSHOT` or `SnapshotPartRequest::PART_TYPE_FRAGMENT`.
	 * @param int|null $fragment_index Element-fragment index, or null for the root snapshot.
	 * @return SnapshotPartRequest Request for one concrete snapshot part.
	 */
	public function create_part_request( string $uri, string $part_type, ?int $fragment_index ): SnapshotPartRequest {
		return new SnapshotPartRequest( $this, $uri, $part_type, $fragment_index );
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName