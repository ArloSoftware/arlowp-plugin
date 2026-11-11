<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Parses a complete supported target manifest without retaining input bytes.
 */
class SnapshotManifestParser {
	const MANIFEST_ENTITY_KIND_EVENT = 'Event';

	/** @var array<string,array<int,string>> */
	private const KEY_SCHEMAS = [
		'TimeZone'                       => [ 'integer' ],
		'Presenter'                      => [ 'integer' ],
		'Venue'                          => [ 'integer' ],
		'Template'                       => [ 'integer' ],
		self::MANIFEST_ENTITY_KIND_EVENT => [ 'integer' ],
		'OnlineActivity'                 => [ 'string' ],
		'Category'                       => [ 'integer' ],
		'CategoryItem'                   => [ 'integer', 'integer' ],
	];

	/**
	 * @param string $bytes Authenticated raw manifest bytes.
	 * @return SnapshotManifest
	 */
	public function parse( string $bytes ): SnapshotManifest {
		$manifest = json_decode( $bytes, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! $this->is_object_shape( $manifest, [ 'SchemaLevel', 'Complete', 'CensusTag', 'Entries' ] ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest has an unsupported shape' );
		}
		if ( ! in_array( $manifest['SchemaLevel'], [ SnapshotManifest::SCHEMA_LEVEL_DEFAULT, SnapshotManifest::SCHEMA_LEVEL_DELTA ], true ) || true !== $manifest['Complete'] || ! $this->is_list( $manifest['Entries'] ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest is incomplete or uses an unsupported schema level' );
		}

		$entries          = [];
		$entry_identities = [];
		foreach ( $manifest['Entries'] as $entry ) {
			$parsed_entry = $this->parse_entry( $entry );
			$identity     = implode( "\0", array_merge( [ $parsed_entry->kind, $parsed_entry->region ], $parsed_entry->key ) );
			if ( isset( $entry_identities[ $identity ] ) ) {
				throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest contains a duplicate physical key' );
			}
			$entry_identities[ $identity ] = true;
			$entries[]                     = $parsed_entry;
		}

		return new SnapshotManifest( $manifest['SchemaLevel'], $this->decode_tag( $manifest['CensusTag'] ), $entries );
	}

	/**
	 * Validates and normalizes one manifest entity entry.
	 *
	 * @param mixed $entry Manifest entry.
	 * @return SnapshotManifestEntry
	 */
	private function parse_entry( $entry ): SnapshotManifestEntry {
		if ( ! is_array( $entry ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest entry is invalid: expected an object.' );
		}
		$identity = $this->format_entry_identity( $entry );
		foreach ( [ 'Kind', 'Key', 'StructuralTag' ] as $required_field ) {
			if ( ! array_key_exists( $required_field, $entry ) ) {
				throw $this->create_entry_field_exception( $identity, 'is missing field', $required_field ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
		}
		if ( ! is_string( $entry['Kind'] ) || ! array_key_exists( $entry['Kind'], self::KEY_SCHEMAS ) ) {
			throw $this->create_entry_field_exception( $identity, 'has an invalid field', 'Kind' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
		$is_event = self::MANIFEST_ENTITY_KIND_EVENT === $entry['Kind'];

		$key                    = $this->parse_key( $entry['Key'], self::KEY_SCHEMAS[ $entry['Kind'] ], $identity );
		$region                 = $this->parse_region( $entry['Region'] ?? '', $identity );
		$fragment_element_index = $this->parse_fragment_element_index( $entry['Kind'], $entry, $identity );

		return new SnapshotManifestEntry(
			$entry['Kind'],
			$key,
			$region,
			$this->decode_entry_tag( $entry['StructuralTag'], $identity, 'StructuralTag' ),
			$is_event && array_key_exists( 'AvailabilityTag', $entry ) && null !== $entry['AvailabilityTag'] ? $this->decode_entry_tag( $entry['AvailabilityTag'], $identity, 'AvailabilityTag' ) : null,
			$fragment_element_index
		);
	}

	/**
	 * Validates key components against the entity kind's schema.
	 *
	 * @param mixed             $key Key components.
	 * @param array<int,string> $expected_types Schema-declared key types.
	 * @param string            $identity Entry identity for diagnostics.
	 * @return array<int,string>
	 */
	private function parse_key( $key, array $expected_types, string $identity ): array {
		if ( ! $this->is_list( $key ) || count( $key ) !== count( $expected_types ) ) {
			throw $this->create_entry_field_exception( $identity, 'has an invalid field', 'Key' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		$normalized = [];
		foreach ( $key as $index => $component ) {
			if ( 'integer' === $expected_types[ $index ] && is_int( $component ) && $component >= 0 ) {
				$normalized[] = (string) $component;
				continue;
			}
			if ( 'string' === $expected_types[ $index ] && is_string( $component ) && strlen( $component ) >= 1 && strlen( $component ) <= 64 && preg_match( '/^[\x20-\x7E]+$/D', $component ) ) {
				$normalized[] = $component;
				continue;
			}
			throw $this->create_entry_field_exception( $identity, 'has an invalid field', 'Key' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		return $normalized;
	}

	/**
	 * Validates an optional manifest region.
	 *
	 * @param mixed  $region Region value.
	 * @param string $identity Entry identity for diagnostics.
	 * @return string
	 */
	private function parse_region( $region, string $identity ): string {
		if ( ! is_string( $region ) || ( '' !== $region && ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,15}$/D', $region ) ) ) {
			throw $this->create_entry_field_exception( $identity, 'has an invalid field', 'Region' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		return $region;
	}

	/**
	 * Reads the required fragment locator for Event entries.
	 *
	 * @param string              $kind Manifest kind.
	 * @param array<string,mixed> $entry Manifest entry.
	 * @param string              $identity Entry identity for diagnostics.
	 * @return int|null
	 */
	private function parse_fragment_element_index( string $kind, array $entry, string $identity ): ?int {
		if ( self::MANIFEST_ENTITY_KIND_EVENT !== $kind ) {
			return null;
		}
		if ( ! array_key_exists( 'FragmentElementIndex', $entry ) ) {
			throw $this->create_entry_field_exception( $identity, 'is missing field', 'FragmentElementIndex' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
		if ( ! is_int( $entry['FragmentElementIndex'] ) || $entry['FragmentElementIndex'] < 0 ) {
			throw $this->create_entry_field_exception( $identity, 'has an invalid field', 'FragmentElementIndex' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		return $entry['FragmentElementIndex'];
	}

	/**
	 * Adds entry context to an invalid tag error.
	 *
	 * @param mixed  $tag Tag value.
	 * @param string $identity Entry identity for diagnostics.
	 * @param string $field Field name.
	 * @return string
	 */
	private function decode_entry_tag( $tag, string $identity, string $field ): string {
		try {
			return $this->decode_tag( $tag );
		} catch ( \ArloTraining\SnapshotProcessException $exception ) {
			throw $this->create_entry_field_exception( $identity, 'has an invalid field', $field ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	/**
	 * Formats available entry values for validation diagnostics.
	 *
	 * @param array<string,mixed> $entry Manifest entry.
	 * @return string
	 */
	private function format_entry_identity( array $entry ): string {
		$identity = [ $entry['Kind'] ?? null, $entry['Key'] ?? null, $entry['Region'] ?? '' ];
		$json     = wp_json_encode( $identity, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( ! is_string( $json ) || '[' !== substr( $json, 0, 1 ) || ']' !== substr( $json, -1 ) ) {
			return '{ null, null, "" }';
		}

		return '{ ' . substr( $json, 1, -1 ) . ' }';
	}

	/**
	 * Creates a validation exception scoped to one manifest entry field.
	 *
	 * @param string $identity Entry identity.
	 * @param string $reason Error reason.
	 * @param string $field Field name.
	 * @return \ArloTraining\SnapshotProcessException
	 */
	private function create_entry_field_exception( string $identity, string $reason, string $field ): \ArloTraining\SnapshotProcessException {
		return new \ArloTraining\SnapshotProcessException( "Snapshot manifest entry {$identity} {$reason} '{$field}'" );
	}

	/**
	 * Decodes one canonical base64 SHA-256 tag.
	 *
	 * @param mixed $tag Canonical base64 SHA-256 tag.
	 * @return string Raw 32-byte tag.
	 */
	private function decode_tag( $tag ): string {
		if ( ! is_string( $tag ) || ! preg_match( '/^[A-Za-z0-9+\/]{43}=$/D', $tag ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest tag is invalid' );
		}
		$decoded = base64_decode( $tag, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Validates the documented base64 SHA-256 transport encoding.
		if ( false === $decoded || 32 !== strlen( $decoded ) || $tag !== base64_encode( $decoded ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Re-encodes the decoded SHA-256 bytes to enforce canonical base64 encoding.
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest tag is invalid' );
		}

		return $decoded;
	}

	/**
	 * Confirms a decoded JSON object has exactly the required fields.
	 *
	 * @param mixed             $value Candidate object decoded as an associative array.
	 * @param array<int,string> $fields Required fields.
	 * @return bool
	 */
	private function is_object_shape( $value, array $fields ): bool {
		return is_array( $value ) && $this->has_only_fields( $value, $fields ) && count( $value ) === count( $fields );
	}

	/**
	 * Checks that an object contains no unsupported fields.
	 *
	 * @param array<mixed>      $value Candidate field map.
	 * @param array<int,string> $allowed_fields Fields permitted by the schema.
	 * @return bool
	 */
	private function has_only_fields( array $value, array $allowed_fields ): bool {
		foreach ( array_keys( $value ) as $field ) {
			if ( ! is_string( $field ) || ! in_array( $field, $allowed_fields, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Confirms a decoded JSON value is an indexed list.
	 *
	 * @param mixed $value Candidate JSON array.
	 * @return bool
	 */
	private function is_list( $value ): bool {
		return is_array( $value ) && ( [] === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
