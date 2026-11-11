<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- This established importer file follows the arlo-* loader convention and reads external snapshot field names.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Loads one selected Event root from its declared snapshot fragment.
 */
class SnapshotImageEventLoader {
	/**
	 * Loads the selected Event from its declared snapshot fragment.
	 *
	 * Retrieves the fragment identified by the selected Event intent, then
	 * returns the unique raw Event record parsed from that fragment's JSON
	 * payload.
	 *
	 * @param object                          $selected_event_intent Sealed selected Event identity and fragment location.
	 * @param SnapshotFragmentContentProvider $fragment_provider Provider for JSON content of declared snapshot fragments.
	 * @return object Exact Event root with its nested sessions.
	 * @throws \ArloTraining\SnapshotProcessException When the image, fragment, or root membership is invalid.
	 */
	public function load( object $selected_event_intent, SnapshotFragmentContentProvider $fragment_provider ): object {
		if ( ! isset( $selected_event_intent->entity_key_1, $selected_event_intent->entity_region_code, $selected_event_intent->source_fragment_element_index ) || ! is_numeric( $selected_event_intent->entity_key_1 ) || (int) $selected_event_intent->entity_key_1 <= 0 || ! is_string( $selected_event_intent->entity_region_code ) || '' === $selected_event_intent->entity_region_code || ! is_numeric( $selected_event_intent->source_fragment_element_index ) || (int) $selected_event_intent->source_fragment_element_index < 0 ) {
			throw new \ArloTraining\SnapshotProcessException( 'Selected Event availability intent is invalid.' );
		}

		$index   = (int) $selected_event_intent->source_fragment_element_index;
		$content = $fragment_provider->get_fragment_content( $index );
		$payload = json_decode( $content );
		$events  = is_object( $payload ) && isset( $payload->Events ) && is_array( $payload->Events ) ? $payload->Events : null;
		if ( ! is_array( $events ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Selected Event availability fragment has no Event roots.' );
		}

		$matches = [];
		foreach ( $events as $event ) {
			if ( is_object( $event ) && isset( $event->EventID, $event->Region ) && is_numeric( $event->EventID ) && is_scalar( $event->Region ) && (int) $event->EventID === (int) $selected_event_intent->entity_key_1 && (string) $event->Region === $selected_event_intent->entity_region_code ) {
				$matches[] = $event;
			}
		}
		if ( 1 !== count( $matches ) || ! isset( $matches[0]->Sessions ) || ! is_array( $matches[0]->Sessions ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Selected Event availability root membership is invalid.' );
		}

		return $matches[0];
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
