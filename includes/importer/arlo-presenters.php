<?php

namespace Arlo\Importer;

class Presenters extends Importer {

	public function __construct() {	}

	public function import() {
		$table_name = parent::$wpdb->prefix . 'arlo_presenters'; 
	
		if (!empty(parent::$data_json->Presenters) && is_array(parent::$data_json->Presenters)) {
			foreach(parent::$data_json->Presenters as $item) {

				$slug = sanitize_title($item->PresenterID . ' ' . $item->FirstName . ' ' . $item->LastName);
				$query = parent::$wpdb->query( parent::$wpdb->prepare( 
					"INSERT INTO $table_name 
					(p_arlo_id, p_firstname, p_lastname, p_viewuri, p_profile, p_qualifications, p_interests, p_twitterid, p_facebookid, p_linkedinid, p_post_name, import_id) 
					VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) 
					", 
				    $item->PresenterID,
					$item->FirstName,
					$item->LastName,
					@$item->ViewUri,
					@$item->Profile->ProfessionalProfile->Text,
					@$item->Profile->Qualifications->Text,
					@$item->Profile->Interests->Text,
					@$item->SocialNetworkInfo->TwitterID,
					@$item->SocialNetworkInfo->FacebookID,
					@$item->SocialNetworkInfo->LinkedInID,
					$slug,
					parent::$import_id
				) );
                                
                if ($query === false) {
                	parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' . parent::$wpdb->last_query, parent::$import_id);
                    throw new Exception('Database insert failed: ' . $table_name);
                }
				
				$name = $item->FirstName . ' ' . $item->LastName;
				
				// create associated custom post, if it dosen't exist
				if(!arlo_get_post_by_name($slug, 'arlo_presenter')) {
					wp_insert_post(array(
						'post_title'    => $name,
						'post_content'  => '',
						'post_status'   => 'publish',
						'post_author'   => 1,
						'post_type'		=> 'arlo_presenter',
						'post_name'		=> $slug
					));
				}
			}
		}
	}
}