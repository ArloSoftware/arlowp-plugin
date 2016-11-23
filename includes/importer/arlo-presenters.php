<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Presenters extends BaseImporter {
	
	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $file_handler = null, $scheduler = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $file_handler, $scheduler);

		$this->table_name = $this->dbl->prefix . 'arlo_presenters';
	}

	protected function save_entity($item) { 
		$slug = sanitize_title($item->PresenterID . ' ' . $item->FirstName . ' ' . $item->LastName);
		$query = $this->dbl->query( $this->dbl->prepare( 
			"INSERT INTO 
				" . $this->table_name ." 
				(p_arlo_id, p_firstname, p_lastname, p_viewuri, p_profile, p_qualifications, p_interests, p_twitterid, p_facebookid, p_linkedinid, p_post_name, import_id) 
				VALUES 
				( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) 
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
			$this->import_id
		) );
						
		if ($query === false) {
			throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' . $this->dbl->last_query);
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