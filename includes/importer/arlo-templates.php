<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Templates extends BaseImporter {

	private $slug;

	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $file_handler = null, $scheduler = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $file_handler, $scheduler);

		$this->table_name = $this->dbl->prefix . 'arlo_eventtemplates';
	}

	protected function save_entity($item) {
		$this->slug = sanitize_title($item->TemplateID . ' ' . $item->Name);
		$query = $this->dbl->query(
			$this->dbl->prepare( 
				"INSERT INTO " . $this->table_name ." 
				(et_arlo_id, et_code, et_name, et_descriptionsummary, et_advertised_duration, et_post_name, import_id, et_registerinteresturi, et_viewuri, et_region) 
				VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s) 
				", 
				$item->TemplateID,
				@$item->Code,
				$item->Name,
				@$item->Description->Summary,
				@$item->AdvertisedDuration,
				$this->slug,
				$this->import_id,
				!empty($item->RegisterInterestUri) ? $item->RegisterInterestUri : '',
				!empty($item->ViewUri) ? $item->ViewUri : '',
				(!empty($item->Region) ? $item->Region : '')
			)
		);
						
		if ($query === false) {
			throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
		}
		
		$this->id = $this->dbl->insert_id;
		
		//TODO: Test without a summary/description
		$this->save_update_wp_post($item->Name, @$item->Description->Summary);

		//tags
		if (isset($item->Tags) && !empty($item->Tags)) {
			$this->save_tags($item->Tags, $this->id, 'template');
		}
							
		// advertised offers
		if(!empty($item->BestAdvertisedOffers) && is_array($item->BestAdvertisedOffers)) {
			$this->save_advertised_offer($item->BestAdvertisedOffers, $item->Region, $this->id);
		}
		
		// content fields
		if(!empty($item->Description->ContentFields) && is_array($item->Description->ContentFields)) {
			$this->save_content_fields($item->Description->ContentFields);
		}
	
		// prsenters
		if(!empty($item->AdvertisedPresenters) && is_array($item->AdvertisedPresenters)) {
			$this->save_advertised_presenters($item->AdvertisedPresenters);
		}
		
		// categories
		if(!empty($item->Categories) && is_array($item->Categories)) {
			$this->save_categories($item->Categories);
		}
	}

	private function save_update_wp_post($title, $content = '') {
		
		// create associated custom post, if it dosen't exist
		$post_config_array = array(
			'post_title'    => $title,
			'post_content'  => $content,
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_type'		=> 'arlo_event',
			'post_name'		=> $this->slug
		);					
		
		$post = arlo_get_post_by_name($this->slug, 'arlo_event');
		
		if(!$post) {					
			wp_insert_post($post_config_array, true);						
		} else {
			$post_config_array['ID'] = $post->ID;
			wp_update_post($post_config_array);				
		}
	} 	

	private function save_categories($categories) {
		if (empty($this->id)) throw new \Exception('No templateID given: ' . __CLASS__ . '::' . __FUNCTION__);

		if(!empty($categories) && is_array($categories)) {
			foreach($categories as $index => $category) {
				$query = $this->dbl->query( $this->dbl->prepare( 
					"REPLACE INTO " . $this->dbl->prefix . "arlo_eventtemplates_categories 
					(et_arlo_id, c_arlo_id, import_id) 
					VALUES ( %d, %d, %s ) 
					", 
					$this->id,
					$category->CategoryID,
					$this->import_id
				) );
												
				if ($query === false) {
					Logger::log('SQL error: ' . $this->dbl->last_error . ' ' . $this->dbl->last_query, $this->import_id);
				}
			}
		}
	}

	private function save_advertised_presenters($advertised_presenters = []) {
		if (empty($this->id)) throw new \Exception('No templateID given: ' . __CLASS__ . '::' . __FUNCTION__);

		if(!empty($advertised_presenters) && is_array($advertised_presenters)) {
			foreach($advertised_presenters as $index => $presenter) {
				$query = $this->dbl->query( $this->dbl->prepare( 
					"INSERT INTO " . $this->dbl->prefix . "arlo_eventtemplates_presenters 
					(et_id, p_arlo_id, p_order, import_id) 
					VALUES ( %d, %d, %d, %s ) 
					", 
					$this->id,
					$presenter->PresenterID,
					$index,
					$this->import_id
				) );
												
				if ($query === false) {
					throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' . $this->dbl->last_query);
				}
			}
		}		
	}

	private function save_content_fields($content_fields = []) {
		if (empty($this->id)) throw new \Exception('No templateID given: ' . __CLASS__ . '::' . __FUNCTION__);

		if (!empty($content_fields) && is_array($content_fields)) {
			foreach($content_fields as $index => $content) {
				$query = $this->dbl->query( $this->dbl->prepare( 
					"INSERT INTO " . $this->dbl->prefix . "arlo_contentfields 
					(et_id, cf_fieldname, cf_text, cf_order, e_contenttype, import_id) 
					VALUES ( %d, %s, %s, %s, %s, %s ) 
					", 
					$this->id,
					@$content->FieldName,
					@$content->Content->Text,
					$index,
					@$content->Content->ContentType,
					$this->import_id
				));
				
				if ($query === false) {
					throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' . $this->dbl->last_query);
				}
			}		
		}
	}	
}