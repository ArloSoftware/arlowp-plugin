<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieves bounded bytes through the approved snapshot HTTP policy.
 */
interface SnapshotHttpTransport {
	/**
	 * @param string $uri Snapshot resource URI.
	 * @param string $platform_hostname Platform hostname from the snapshot response.
	 * @param int    $max_response_bytes Maximum allowed response body bytes.
	 * @param int    $import_id Import identifier for diagnostics.
	 * @return string Response body.
	 */
	public function get_remote_content( string $uri, string $platform_hostname, int $max_response_bytes, int $import_id ): string;
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
