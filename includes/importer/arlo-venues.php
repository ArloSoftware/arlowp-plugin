<?php

namespace Arlo\Importer;

class Venues extends BaseEntity {

	public function __construct($plugin, $importer, $data, $iterator = 0) {
		parent::__construct($plugin, $importer, $data, $iterator);

		$this->table_name = $this->wpdb->prefix . 'arlo_venues';
	}

	protected function save_entity($item) {
		$slug = sanitize_title($item->VenueID . ' ' . $item->Name);
		$query = $this->wpdb->query( $this->wpdb->prepare( 
			"INSERT INTO " . $this->table_name . " 
			(v_arlo_id, v_name, v_geodatapointlatitude, v_geodatapointlongitude, v_physicaladdressline1, v_physicaladdressline2, v_physicaladdressline3, v_physicaladdressline4, v_physicaladdresssuburb, v_physicaladdresscity, v_physicaladdressstate, v_physicaladdresspostcode, v_physicaladdresscountry, v_viewuri, v_facilityinfodirections, v_facilityinfoparking, v_post_name, import_id) 
			VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )
			", 
			$item->VenueID,
			$item->Name,
			@$item->GeoData->PointLatitude,
			@$item->GeoData->PointLongitude,
			@$item->PhysicalAddress->StreetLine1,
			@$item->PhysicalAddress->StreetLine2,
			@$item->PhysicalAddress->StreetLine3,
			@$item->PhysicalAddress->StreetLine4,
			@$item->PhysicalAddress->Suburb,
			@$item->PhysicalAddress->City,
			@$item->PhysicalAddress->State,
			@$item->PhysicalAddress->PostCode,
			@$item->PhysicalAddress->Country,
			@$item->ViewUri,
			@$item->FacilityInfo->Directions->Text,
			@$item->FacilityInfo->Parking->Text,
			$slug,
			$this->import_id
		) );
						
		if ($query === false) {
			\Arlo\Logger::log_error('SQL error: ' . $this->wpdb->last_error . ' ' .$this->wpdb->last_query, $this->import_id);
		}
						
		// create associated custom post, if it dosen't exist
		// should be arlo_venues
		if(!arlo_get_post_by_name($slug, 'arlo_venue')) {
			wp_insert_post(array(
				'post_title'    => $item->Name,
				'post_content'  => '',
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_type'		=> 'arlo_venue',
				'post_name'		=> $slug
			));
		}
	}
}