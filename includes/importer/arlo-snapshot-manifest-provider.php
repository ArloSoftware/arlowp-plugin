<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieves raw manifest bytes for one admitted immutable snapshot package.
 */
interface SnapshotManifestProvider {
	/**
	 * @param SnapshotManifestRequest $request Admitted package advertisement.
	 * @return string Raw manifest bytes.
	 */
	public function get_snapshot_manifest_content( SnapshotManifestRequest $request ): string;
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
