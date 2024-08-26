<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Presenters extends BaseImporter {
	
	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);

		$this->table_name = $this->dbl->prefix . 'arlo_presenters';
	}

	protected function save_entity($item) { 
		$name = $item->FirstName . ' ' . $item->LastName;

		$slug = sanitize_title($item->PresenterID . ' ' . $name);
		
		$post_config_array = array(
				'post_title'    => $name,
				'post_content'  => '',
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_type'		=> 'arlo_presenter',
				'post_name'		=> $slug
			);

		$post = arlo_get_post_by_name($slug, 'arlo_presenter');
		if (is_null($post) || false === $post) {
			$post_id = wp_insert_post($post_config_array);
		} else {
			$post_config_array['ID'] = $post->ID;
			$post_id = wp_update_post($post_config_array);
		}

		if (is_numeric($post_id) && $post_id > 0) {
			$query = $this->dbl->query( $this->dbl->prepare( 
				"INSERT INTO 
					" . $this->table_name ." 
					(p_arlo_id, p_firstname, p_lastname, p_viewuri, p_profile, p_qualifications, p_interests, p_twitterid, p_facebookid, p_linkedinid, p_post_name, p_post_id, import_id) 
					VALUES 
					( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s ) 
				", 
				$item->PresenterID,
				$item->FirstName,
				$item->LastName,
				!empty($item->ViewUri) ? $item->ViewUri : null,
				!empty($item->Profile) && !empty($item->Profile->ProfessionalProfile->Text) ? $item->Profile->ProfessionalProfile->Text : null,
				!empty($item->Profile) && !empty($item->Profile->Qualifications->Text) ? $item->Profile->Qualifications->Text : null,
				!empty($item->Profile) && !empty($item->Profile->Interests->Text) ? $item->Profile->Interests->Text : null,
				!empty($item->SocialNetworkInfo) && !empty($item->SocialNetworkInfo->TwitterID) ? $item->SocialNetworkInfo->TwitterID : null,
				!empty($item->SocialNetworkInfo) && !empty($item->SocialNetworkInfo->FacebookID) ? $item->SocialNetworkInfo->FacebookID : null,
				!empty($item->SocialNetworkInfo) && !empty($item->SocialNetworkInfo->LinkedInID) ? $item->SocialNetworkInfo->LinkedInID : null,
				$slug,
				$post_id,
				$this->import_id
			) );

			if ($query === false) {
				throw new \Exception('SQL error: ' . $this->dbl->last_error );
			}
		} else {
			throw new \Exception('Presenter post creation error: ' . $slug);
		}
		
		// create associated custom post, if it dosen't exist
		if(!arlo_get_post_by_name($slug, 'arlo_presenter')) {
			
		}
	}
}