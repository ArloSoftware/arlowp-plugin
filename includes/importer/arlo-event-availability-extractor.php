<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- This established importer file follows the arlo-* loader convention and reads external snapshot field names.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Extracts Event availability values shaped for the known arlo_events table schema.
 *
 * This is the shared availability mapping for ordinary full-shadow imports and
 * delta overlays. Keep both paths using these methods so zero, null, and absent
 * platform values retain the same database representation.
 */
class EventAvailabilityExtractor {
	/**
	 * Returns top-level Event availability values shaped for the arlo_events table schema.
	 *
	 * @param object $event Event payload from a validated selected fragment.
	 * @return array<string,mixed> Database row values keyed by owned arlo_events columns.
	 */
	public static function extract_root_db_row_values( object $event ): array {
		return [
			'e_isfull'          => self::extract_is_full( $event ),
			'e_placesremaining' => self::extract_places_remaining( $event ),
			'e_registermessage' => ! empty( $event->RegistrationInfo ) && is_object( $event->RegistrationInfo ) && ! empty( $event->RegistrationInfo->RegisterMessage ) ? $event->RegistrationInfo->RegisterMessage : null,
			'e_registeruri'     => ! empty( $event->RegistrationInfo ) && is_object( $event->RegistrationInfo ) && ! empty( $event->RegistrationInfo->RegisterUri ) ? $event->RegistrationInfo->RegisterUri : null,
		];
	}

	/**
	 * Returns Event session availability values shaped for the arlo_events table schema.
	 *
	 * Sessions intentionally do not use RegistrationInfo.
	 *
	 * @param object $session Session payload from a validated selected fragment.
	 * @return array<string,mixed> Database row values keyed by owned arlo_events columns.
	 */
	public static function extract_session_db_row_values( object $session ): array {
		return [
			'e_isfull'          => self::extract_is_full( $session ),
			'e_placesremaining' => self::extract_places_remaining( $session ),
		];
	}

	/**
	 * Keeps valid numeric zero distinct from omitted, null, and invalid values.
	 *
	 * @param object $event Event or session payload.
	 * @return int|float|string|null Database-compatible places-remaining value.
	 */
	private static function extract_places_remaining( object $event ) {
		if ( ! property_exists( $event, 'PlacesRemaining' ) || ! is_numeric( $event->PlacesRemaining ) ) {
			return null;
		}

		return $event->PlacesRemaining;
	}

	/**
	 * Keeps an explicitly false availability value distinct from an omitted field.
	 *
	 * @param object $event Event or session payload.
	 * @return int|bool|null Database-compatible fullness value.
	 */
	private static function extract_is_full( object $event ) {
		if ( ! property_exists( $event, 'IsFull' ) || null === $event->IsFull ) {
			return null;
		}

		return false === $event->IsFull ? 0 : $event->IsFull;
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase