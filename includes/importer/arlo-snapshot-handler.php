<?php

namespace ArloTraining\Importer;

use ArloTraining\Crypto;

/**
 * Handles the snapshot callback data pipeline: nonce generation, parsing the
 * raw callback body, decrypting the JWE payload, and processing the decoded
 * response.
 *
 * Separated from Importer so that each step can be tested independently
 * of the I/O and state-management concerns that live on Importer.
 */
class SnapshotHandler {

	private $importer;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Generate a nonce for a new import request.
	 *
	 * The format — 32 hex chars, no hyphens — is the canonical form
	 * that validate_callback_fields() enforces on inbound callbacks. Both sides
	 * of the contract live in this class so a format change only needs one edit.
	 *
	 * @return string 32-character hex nonce.
	 */
	public static function generate_nonce(): string {
		return \ArloTraining\Utilities::GUIDv4( true, true );
	}

	/**
	 * Parse and validate the raw callback body string.
	 *
	 * Rejects oversized bodies before decoding, then delegates field-level
	 * format validation to validate_callback_fields().
	 *
	 * @param string $raw_body The raw POST body (JSON string).
	 * @return object Decoded JSON with Nonce, RequestID, and __jwe__ fields.
	 * @throws \Exception If the body is missing, oversized, or structurally invalid.
	 */
	public function parse_snapshot_callback_response( string $raw_body ): object {
		// Reject oversized bodies before any parsing work. The real callback
		// contains a 32-char nonce, a ≤63-char RequestID, and a compact JWE
		// token; the full payload is well under 4 KB on any legitimate request.
		if ( strlen( $raw_body ) > 8192 ) {
			throw new \Exception( 'Snapshot callback body exceeds maximum allowed size' );
		}

		$callback = json_decode( $raw_body );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( 'Failed to parse snapshot callback body: ' . json_last_error_msg() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		if ( ! is_object( $callback ) ) {
			throw new \Exception( 'Snapshot callback body must be a JSON object' );
		}

		if ( empty( $callback->Nonce ) || empty( $callback->__jwe__ ) || empty( $callback->RequestID ) ) {
			throw new \Exception( 'Snapshot callback body missing required fields (Nonce, RequestID, __jwe__)' );
		}

		$this->validate_callback_fields( $callback );

		return $callback;
	}

	/**
	 * Validate the format of the decoded callback fields.
	 *
	 * Called by parse_snapshot_callback_response() after presence checks pass.
	 * Rejects values that can never match a stored import entry, so the DB is
	 * not queried for payloads that are trivially invalid.
	 *
	 * - Nonce: 32 hex chars, no hyphens (produced by generate_nonce()). Case-insensitive
	 *   to accommodate platforms where the underlying GUID generator returns uppercase hex.
	 * - RequestID: ≤63 chars (matches the varchar(63) DB column); no format
	 *   assumption is made about the platform-generated value.
	 * - __jwe__: compact JWE serialization — exactly 5 base64url segments
	 *   separated by dots.
	 *
	 * Fields are checked with is_scalar() before use. Non-scalar values (arrays,
	 * objects) are rejected with a clean \Exception — an explicit check avoids
	 * the "Array to string conversion" PHP notice that (string) casting would
	 * emit on a public AJAX endpoint, and prevents log noise from malformed
	 * requests.
	 *
	 * @param object $callback The decoded callback object.
	 * @throws \Exception If any field fails its format check.
	 */
	private function validate_callback_fields( object $callback ): void {
		if ( ! is_scalar( $callback->Nonce ) || ! is_scalar( $callback->RequestID ) || ! is_scalar( $callback->__jwe__ ) ) {
			throw new \Exception( 'Snapshot callback fields must be scalar values' );
		}

		$nonce      = (string) $callback->Nonce;
		$request_id = (string) $callback->RequestID;
		$jwe        = (string) $callback->__jwe__;

		if ( ! preg_match( '/^[0-9a-f]{32}$/i', $nonce ) ) {
			throw new \Exception( 'Snapshot callback Nonce has invalid format' );
		}

		if ( strlen( $request_id ) > 63 ) {
			throw new \Exception( 'Snapshot callback RequestID exceeds maximum allowed length' );
		}

		if ( substr_count( $jwe, '.' ) !== 4 ) {
			throw new \Exception( 'Snapshot callback __jwe__ has invalid JWE structure' );
		}
	}

	/**
	 * Decrypt the JWE payload.
	 *
	 * @param string $jwe The encrypted JWE string from the callback body.
	 * @param string $key The symmetric key for decryption.
	 * @return object Decoded snapshot response.
	 * @throws \Exception If decryption or JSON decoding fails.
	 */
	public function decrypt( string $jwe, string $key ): object {
		$payload_string = Crypto::jwe_decrypt( $jwe, $key );
		// Defensively strip any stray ASCII control characters that may survive AES-CBC block padding.
		$payload_string = preg_replace( '/[\x00-\x1F\x7F]/', '', $payload_string );

		$response = json_decode( $payload_string );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( 'Failed to decode snapshot response: ' . json_last_error_msg() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
		if ( ! is_object( $response ) ) {
			throw new \Exception( 'Snapshot response payload must be a JSON object' );
		}

		return $response;
	}

	/**
	 * Process a decoded snapshot response.
	 *
	 * On success (SnapshotUri present): persists the decoded payload and kicks
	 * off the scheduler for the next import step.
	 * On error: throws an exception with sanitized error fields.
	 *
	 * @param object $snapshot_response The decoded snapshot response object.
	 * @throws \Exception On error responses or missing content.
	 */
	public function process( object $snapshot_response ): void {
		if ( ! empty( $snapshot_response->SnapshotUri ) ) {
			$this->importer->update_import_entry( [
				'callback_json' => wp_json_encode( $snapshot_response ),
			] );
			$this->importer->kick_off_scheduler();
		} elseif ( ! empty( $snapshot_response->Error ) ) {
			$code    = sanitize_text_field( (string) ( $snapshot_response->Error->Code    ?? '' ) );
			$message = sanitize_text_field( (string) ( $snapshot_response->Error->Message ?? '' ) );
			$parts   = array_filter( [ $code, $message ] );
			throw new \Exception(
				! empty( $parts ) ? implode( ': ', $parts ) : 'Error in the response for the snapshot request' // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			);
		} else {
			throw new \Exception( 'Error in the response for the snapshot request' );
		}
	}
}
