<?php

namespace Arlo\Importer;

class Venues extends Importer {

	public function __construct() {	}

	public function import() {
		$table_name = parent::$wpdb->prefix . 'arlo_venues'; 
	
		if (!empty(parent::$data_json->Venues) && is_array(parent::$data_json->Venues)) {
			foreach(parent::$data_json->Venues as $item) {

				$slug = sanitize_title($item->VenueID . ' ' . $item->Name);
				$query = parent::$wpdb->query( parent::$wpdb->prepare( 
					"INSERT INTO $table_name 
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
					parent::$import_id
				) );
                                
                if ($query === false) {
                	parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' .parent::$wpdb->last_query, parent::$import_id);
                    throw new Exception('Database insert failed: ' . $table_name);
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
	}
}