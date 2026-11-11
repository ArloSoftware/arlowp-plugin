<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieves decoded snapshot content for one image or fragment.
 */
interface SnapshotPartProvider {
	/**
	 * @param SnapshotPartRequest $request Snapshot part retrieval request.
	 * @return string Decoded JSON text.
	 */
	public function get_snapshot_part_content( SnapshotPartRequest $request ): string;
}
