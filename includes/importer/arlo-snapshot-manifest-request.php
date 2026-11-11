<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Request value object for manifest metadata from one admitted snapshot package.
 */
class SnapshotManifestRequest {
	/**
	 * Platform snapshot identity supplied during callback admission.
	 *
	 * @var string
	 */
	public $snapshot_id;

	/**
	 * Snapshot image URI supplied during callback admission.
	 *
	 * @var string
	 */
	public $snapshot_uri;

	/**
	 * Remote URI from which the package-bound manifest bytes are retrieved.
	 *
	 * @var string
	 */
	public $manifest_uri;

	/**
	 * Expected lowercase hexadecimal SHA-256 digest advertised for the manifest bytes.
	 *
	 * @var string
	 */
	public $manifest_expected_sha256;

	/**
	 * Expected manifest byte count used as the remote response limit.
	 *
	 * @var int
	 */
	public $manifest_expected_bytes;

	/**
	 * Platform hostname used by the approved remote-host policy.
	 *
	 * @var string
	 */
	public $platform_hostname;

	/**
	 * Import identifier used only to attach transport diagnostics to this import.
	 *
	 * @var int
	 */
	public $import_id;

	/**
	 * Creates a package-bound manifest request.
	 *
	 * @param string $snapshot_id Platform snapshot identity.
	 * @param string $snapshot_uri Snapshot image URI.
	 * @param string $manifest_uri Remote manifest URI.
	 * @param string $manifest_expected_sha256 Expected lowercase hexadecimal manifest digest.
	 * @param int    $manifest_expected_bytes Expected advertised manifest byte count.
	 * @param string $platform_hostname Admitted platform hostname.
	 * @param int    $import_id Import identifier for diagnostics.
	 */
	public function __construct( string $snapshot_id, string $snapshot_uri, string $manifest_uri, string $manifest_expected_sha256, int $manifest_expected_bytes, string $platform_hostname, int $import_id ) {
		$this->snapshot_id              = $snapshot_id;
		$this->snapshot_uri             = $snapshot_uri;
		$this->manifest_uri             = $manifest_uri;
		$this->manifest_expected_sha256 = $manifest_expected_sha256;
		$this->manifest_expected_bytes  = $manifest_expected_bytes;
		$this->platform_hostname        = $platform_hostname;
		$this->import_id                = $import_id;
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
