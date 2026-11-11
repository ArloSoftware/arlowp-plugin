<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieves raw manifest bytes through the approved snapshot HTTP policy.
 */
class RemoteHttpSnapshotManifestProvider implements SnapshotManifestProvider {
	/** Bounds worker memory consumed by the buffered manifest HTTP response. */
	const MAX_MANIFEST_BYTES = 5242880;

	/** Reserves enough headroom for the buffered response and JSON decoding peak. */
	const MANIFEST_MEMORY_PEAK_USAGE_FACTOR = 12;

	/** @var SnapshotHttpTransport */
	private $transport;

	/**
	 * @param SnapshotHttpTransport|null $transport Snapshot HTTP transport.
	 */
	public function __construct( ?SnapshotHttpTransport $transport = null ) {
		$this->transport = $transport ?? new RemoteSnapshotHttpTransport();
	}

	/**
	 * Retrieves a package-bound manifest after enforcing size and memory admission.
	 *
	 * @param SnapshotManifestRequest $request Admitted package advertisement.
	 * @return string Raw authenticated manifest bytes.
	 */
	public function get_snapshot_manifest_content( SnapshotManifestRequest $request ): string {
		if ( $request->manifest_expected_bytes < 1 || $request->manifest_expected_bytes > self::MAX_MANIFEST_BYTES || ! preg_match( '/^[a-f0-9]{64}$/D', $request->manifest_expected_sha256 ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest advertisement is invalid' );
		}
		$request_memory_limit      = $this->get_request_memory_limit();
		$available_memory          = null === $request_memory_limit ? null : max( 0, $request_memory_limit - memory_get_usage( true ) );
		$estimated_memory_required = $request->manifest_expected_bytes * self::MANIFEST_MEMORY_PEAK_USAGE_FACTOR;
		if ( null !== $available_memory && $available_memory < $estimated_memory_required ) {
			throw new \ArloTraining\SnapshotManifestMemoryException( $request_memory_limit, $request->manifest_expected_bytes, $estimated_memory_required ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The typed exception is caught by the import pipeline; its integer diagnostics are logged separately and it is never rendered directly.
		}

		$bytes = $this->transport->get_remote_content( $request->manifest_uri, $request->platform_hostname, $request->manifest_expected_bytes, $request->import_id );
		if ( strlen( $bytes ) !== $request->manifest_expected_bytes || ! hash_equals( hex2bin( $request->manifest_expected_sha256 ), hash( 'sha256', $bytes, true ) ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest package binding is invalid' );
		}

		return $bytes;
	}

	/**
	 * Reads the configured PHP memory limit for the current request.
	 *
	 * @return int|null Configured PHP memory limit in bytes, or null when no reliable finite limit is configured.
	 */
	protected function get_request_memory_limit(): ?int {
		return $this->parse_request_memory_limit( ini_get( 'memory_limit' ) );
	}

	/**
	 * Converts a PHP memory_limit setting to finite byte count when possible.
	 *
	 * @param mixed $memory_limit PHP memory_limit setting.
	 * @return int|null Configured PHP memory limit in bytes, or null when no reliable finite limit is configured.
	 */
	protected function parse_request_memory_limit( $memory_limit ): ?int {
		if ( ! is_string( $memory_limit ) ) {
			return null;
		}

		$memory_limit = strtolower( trim( $memory_limit ) );
		if ( '-1' === $memory_limit || ! preg_match( '/^(\d+)([kmg])?$/D', $memory_limit, $matches ) ) {
			return null;
		}

		$multiplier = [
			''  => 1,
			'k' => 1024,
			'm' => 1024 * 1024,
			'g' => 1024 * 1024 * 1024,
		];
		$limit      = (int) $matches[1] * $multiplier[ $matches[2] ?? '' ];

		return $limit;
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
