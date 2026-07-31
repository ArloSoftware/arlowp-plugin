<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;

class Venues extends BaseImporter {

	public function __construct($importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);
	}

	protected function save_entity($item) {
		global $wpdb;
		$slug = sanitize_title($item->VenueID . ' ' . $item->Name);

		// create associated custom post, if it dosen't exist
		// should be arlo_venues
		$post_config_array = array(
				'post_title'    => $item->Name,
				'post_content'  => '',
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_type'		=> 'arlo_venue',
				'post_name'		=> $slug
			);

		$post = arlo_get_post_by_name($slug, 'arlo_venue');
		if(is_null($post) || false === $post) {
			$post_id = wp_insert_post($post_config_array);
		} else {
			$post_config_array['ID'] = $post->ID;
			$post_id = $post->ID;
			$wpdb->update($wpdb->prefix .'posts', $post_config_array, array('id' => $post_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Import updates the associated post record directly and cache is cleared after import.
		}

		if (is_numeric($post_id) && $post_id > 0) {
			// we'll clear cache after the import process finishes
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching  -- Direct database query is required. No cache needed for the insert operation in data import process. Cache will be reset after the import process done.
            $query = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}arlo_venues 
                (v_arlo_id, v_name, v_locationname, v_geodatapointlatitude, v_geodatapointlongitude, v_physicaladdressline1, v_physicaladdressline2, v_physicaladdressline3, v_physicaladdressline4, v_physicaladdresssuburb, v_physicaladdresscity, v_physicaladdressstate, v_physicaladdresspostcode, v_physicaladdresscountry, v_viewuri, v_facilityinfodirections, v_facilityinfoparking, v_post_name, v_post_id, import_id) 
                VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s )
                ", 
				$item->VenueID,
				$item->Name,
				$item->LocationName,
				!empty($item->GeoData) && !empty($item->GeoData->PointLatitude) ? $item->GeoData->PointLatitude : null,
				!empty($item->GeoData) && !empty($item->GeoData->PointLongitude) ? $item->GeoData->PointLongitude : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->StreetLine1) ? $item->PhysicalAddress->StreetLine1 : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->StreetLine2) ? $item->PhysicalAddress->StreetLine2 : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->StreetLine3) ? $item->PhysicalAddress->StreetLine3 : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->StreetLine4) ? $item->PhysicalAddress->StreetLine4 : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->Suburb) ? $item->PhysicalAddress->Suburb : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->City) ? $item->PhysicalAddress->City : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->State) ? $item->PhysicalAddress->State : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->PostCode) ? $item->PhysicalAddress->PostCode : null,
				!empty($item->PhysicalAddress) && !empty($item->PhysicalAddress->Country) ? $item->PhysicalAddress->Country : null,
				!empty($item->ViewUri) ? $item->ViewUri : null,
				!empty($item->FacilityInfo) && !empty($item->FacilityInfo->Directions) && !empty($item->FacilityInfo->Directions->Text) ? $item->FacilityInfo->Directions->Text : null,
				!empty($item->FacilityInfo) && !empty($item->FacilityInfo->Parking) && !empty($item->FacilityInfo->Parking->Text) ? $item->FacilityInfo->Parking->Text : null,
				$slug,
				$post_id,
				$this->import_id
			) );
			
			if ($query === false) {
				throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
		} else {
			throw new \Exception('Venue post creation error: ' . $slug); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}
}