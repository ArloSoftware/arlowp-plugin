<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports venue payload items and keeps their companion WP posts in sync.
 *
 * Each payload item updates or creates the venue custom post, then writes the
 * corresponding venue row with address, geodata, directions, and parking
 * information for the current import.
 */
class Venues extends AbstractImportStep {

	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
	}

	protected function import_entity( $entity_payload ) {
		global $wpdb;
		$slug = sanitize_title( $entity_payload->VenueID . ' ' . $entity_payload->Name );

		// Create associated venue custom post if it doesn't exist.
		$post_config_array = array(
			'post_title'   => $entity_payload->Name,
			// Always empty: arlo_the_content_venue() replaces the page body with the venue template at render time.
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_type'    => 'arlo_venue',
			'post_name'    => $slug,
		);

		$post = arlo_get_post_by_name( $slug, 'arlo_venue' );
		if ( is_null( $post ) || false === $post ) {
			// Forced sanitising: kses is skipped for users with unfiltered_html, but imported data is shared by all users and must be stored the same whoever runs the import.
			$post_id = $this->insert_post_sanitised( $post_config_array );
		} else {
			// Update path only, as the insert above already sanitises and the filters are not idempotent. post_name is untouched so URLs are stable.
			$post_config_array['ID'] = $post->ID;
			$post_id                 = $post->ID;
			$post_config_array       = $this->sanitise_post_text( $post_config_array, $post_id );
			$updated                 = $wpdb->update( $wpdb->prefix . 'posts', $post_config_array, array( 'id' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update of the plugin-owned Arlo post: importer updates deliberately avoid wp_update_post() so third-party post hooks do not fire for every imported entity. The title and content are sanitised first, as wp_insert_post() does on create, and a changed row is cleared from the core post cache below. Arlo's own caches are invalidated through CacheControl when the import finishes.
			// $wpdb->update() returns the number of changed rows, so an unchanged post skips the cache clear.
			if ( $updated ) {
				clean_post_cache( $post_id );
			}
		}

		if ( is_numeric( $post_id ) && $post_id > 0 ) {
			// Delete any prior row for this business identity + import to enable safe replay
			// if a previous save was interrupted before the checkpoint advanced.
			$this->execute_prepared_query(
				"DELETE FROM {$wpdb->prefix}arlo_venues WHERE v_arlo_id = %d AND import_id = %d",
				[ $entity_payload->VenueID, (int) $this->import_id ]
			);

			// we'll clear cache after the import process finishes
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching  -- Direct database query is required. No cache needed for the insert operation in data import process. Cache will be reset after the import process done.
			$query = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}arlo_venues
                (v_arlo_id, v_name, v_locationname, v_geodatapointlatitude, v_geodatapointlongitude, v_physicaladdressline1, v_physicaladdressline2, v_physicaladdressline3, v_physicaladdressline4, v_physicaladdresssuburb, v_physicaladdresscity, v_physicaladdressstate, v_physicaladdresspostcode, v_physicaladdresscountry, v_viewuri, v_facilityinfodirections, v_facilityinfoparking, v_post_name, v_post_id, import_id)
                VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d )
                ",
					$entity_payload->VenueID,
					$entity_payload->Name,
					$entity_payload->LocationName,
					! empty( $entity_payload->GeoData ) && ! empty( $entity_payload->GeoData->PointLatitude ) ? $entity_payload->GeoData->PointLatitude : null,
					! empty( $entity_payload->GeoData ) && ! empty( $entity_payload->GeoData->PointLongitude ) ? $entity_payload->GeoData->PointLongitude : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->StreetLine1 ) ? $entity_payload->PhysicalAddress->StreetLine1 : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->StreetLine2 ) ? $entity_payload->PhysicalAddress->StreetLine2 : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->StreetLine3 ) ? $entity_payload->PhysicalAddress->StreetLine3 : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->StreetLine4 ) ? $entity_payload->PhysicalAddress->StreetLine4 : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->Suburb ) ? $entity_payload->PhysicalAddress->Suburb : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->City ) ? $entity_payload->PhysicalAddress->City : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->State ) ? $entity_payload->PhysicalAddress->State : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->PostCode ) ? $entity_payload->PhysicalAddress->PostCode : null,
					! empty( $entity_payload->PhysicalAddress ) && ! empty( $entity_payload->PhysicalAddress->Country ) ? $entity_payload->PhysicalAddress->Country : null,
					! empty( $entity_payload->ViewUri ) ? $entity_payload->ViewUri : null,
					! empty( $entity_payload->FacilityInfo ) && ! empty( $entity_payload->FacilityInfo->Directions ) && ! empty( $entity_payload->FacilityInfo->Directions->Text ) ? $entity_payload->FacilityInfo->Directions->Text : null,
					! empty( $entity_payload->FacilityInfo ) && ! empty( $entity_payload->FacilityInfo->Parking ) && ! empty( $entity_payload->FacilityInfo->Parking->Text ) ? $entity_payload->FacilityInfo->Parking->Text : null,
					$slug,
					$post_id,
					(int) $this->import_id
				)
			);

			if ( $query === false ) {
				throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
			}
		} else {
			throw new \Exception( 'Venue post creation error: ' . $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
	}
}
