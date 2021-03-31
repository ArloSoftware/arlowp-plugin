<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Venues extends BaseImporter {

	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);

		$this->table_name = $this->dbl->prefix . 'arlo_venues';
	}

	protected function save_entity($item) {
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
			$post_id = wp_update_post($post_config_array);
		}

		if (is_numeric($post_id) && $post_id > 0) {
			$query = $this->dbl->query( $this->dbl->prepare( 
				"INSERT INTO " . $this->table_name . " 
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
				throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
			}
		} else {
			throw new \Exception('Venue post creation error: ' . $slug);
		}
	}
}