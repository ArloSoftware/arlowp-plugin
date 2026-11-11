<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- This established importer file follows the arlo-* loader convention; platform response fields retain their external schema names.

namespace ArloTraining\Importer;

use ArloTraining\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieves and decodes snapshot parts through the WordPress HTTP transport.
 */
class RemoteHttpSnapshotPartProvider implements SnapshotPartProvider {
	const RESPONSE_SIZE_BUFFER_BYTES = 1048576;
	const MAX_RESPONSE_BYTES         = ImportRequest::FRAGMENT_MAX_BYTE_SIZE + self::RESPONSE_SIZE_BUFFER_BYTES;

	/** @var SnapshotHttpTransport */
	private $transport;

	/**
	 * @param SnapshotHttpTransport|null $transport Snapshot HTTP transport.
	 */
	public function __construct( ?SnapshotHttpTransport $transport = null ) {
		$this->transport = $transport ?? new RemoteSnapshotHttpTransport();
	}

	/**
	 * @param SnapshotPartRequest $request Snapshot part retrieval request.
	 * @return string Decoded JSON text.
	 */
	public function get_snapshot_part_content( SnapshotPartRequest $request ): string {
		$encrypted_contents = $this->transport->get_remote_content( $request->uri, $request->context->platform_hostname, self::MAX_RESPONSE_BYTES, $request->context->import_id );
		$key                = $request->context->encryption_key;
		$method             = $request->context->encryption_method;
		$plain_contents     = \ArloTraining\Crypto::decrypt_gzip( $encrypted_contents, $key, $method, ImportRequest::FRAGMENT_MAX_BYTE_SIZE + 1 );
		$iteration          = SnapshotPartRequest::PART_TYPE_FRAGMENT === $request->part_type ? $request->fragment_index + 1 : null;
		Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot data decrypted [part=' . $request->part_type . ( is_null( $iteration ) ? '' : '; iteration=' . $iteration ) . '; bytes=' . strlen( $plain_contents ) . ']', $request->context->import_id );

		return $plain_contents;
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
