<?php

namespace Arlo\Importer;

class Templates extends Importer {

	private $slug;
	private $template_id;

	public function __construct() {	}

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

	public function import() {
		$table_name = parent::$wpdb->prefix . 'arlo_eventtemplates'; 
	
		if (!empty(parent::$data_json->Templates) && is_array(parent::$data_json->Templates)) {
			foreach(parent::$data_json->Templates as $item) {
				$this->slug = sanitize_title($item->TemplateID . ' ' . $item->Name);
				$query = parent::$wpdb->query(
					parent::$wpdb->prepare( 
						"INSERT INTO $table_name 
						(et_arlo_id, et_code, et_name, et_descriptionsummary, et_advertised_duration, et_post_name, active, et_registerinteresturi, et_viewuri, et_region) 
						VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s) 
						", 
						$item->TemplateID,
						@$item->Code,
						$item->Name,
						@$item->Description->Summary,
						@$item->AdvertisedDuration,
						$this->slug,
						parent::$import_id,
						!empty($item->RegisterInterestUri) ? $item->RegisterInterestUri : '',
						!empty($item->ViewUri) ? $item->ViewUri : '',
						(!empty($region) ? $region : '')
					)
				);
								
				if ($query === false) {
					parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' .parent::$wpdb->last_query, parent::$import_id);
					throw new Exception('Database insert failed: ' . $table_name);
				}
				
				$this->template_id = parent::$wpdb->insert_id;
				
				//TODO: Test without a summary/description
				$this->save_update_wp_post($item->Name, @$item->Description->Summary);

				//tags
				if (isset($item->Tags) && !empty($item->Tags)) {
					$this->save_tags($item->Tags, $this->template_id, 'template');
				}				
									
				// advertised offers
				if(!empty($item->BestAdvertisedOffers) && is_array($item->BestAdvertisedOffers)) {
					$this->save_advertised_offer($item->BestAdvertisedOffers, '', $this->template_id);
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
		}
	}

	private function save_categories($categories) {
		if (empty($this->template_id)) throw new Exception('No templateID given: ' . __CLASS__ . '::' . __FUNCTION__);

		if(!empty($categories) && is_array($categories)) {
			foreach($categories as $index => $category) {
				$query = parent::$wpdb->query( parent::$wpdb->prepare( 
					"REPLACE INTO " . parent::$wpdb->prefix . "arlo_eventtemplates_categories 
					(et_arlo_id, c_arlo_id, active) 
					VALUES ( %d, %d, %s ) 
					", 
					$this->template_id,
					$category->CategoryID,
					parent::$import_id
				) );
												
				if ($query === false) {
					parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' . parent::$wpdb->last_query, parent::$import_id);
					throw new Exception('Database insert failed: ' . $table_name);
				}
			}
		}
	}

	private function save_advertised_presenters($advertised_presenters = []) {
		if (empty($this->template_id)) throw new Exception('No templateID given: ' . __CLASS__ . '::' . __FUNCTION__);

		if(!empty($advertised_presenters) && is_array($advertised_presenters)) {
			foreach($advertised_presenters as $index => $presenter) {
				$query = parent::$wpdb->query( parent::$wpdb->prepare( 
					"INSERT INTO " . parent::$wpdb->prefix . "arlo_eventtemplates_presenters 
					(et_id, p_arlo_id, p_order, active) 
					VALUES ( %d, %d, %d, %s ) 
					", 
					$this->template_id,
					$presenter->PresenterID,
					$index,
					parent::$import_id
				) );
												
				if ($query === false) {
					parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' . parent::$wpdb->last_query, parent::$import_id);
					throw new Exception('Database insert failed: ' . $table_name);
				}
			}
		}		
	}

	private function save_content_fields($content_fields = []) {
		if (empty($this->template_id)) throw new Exception('No templateID given: ' . __CLASS__ . '::' . __FUNCTION__);

		if (!empty($content_fields) && is_array($content_fields)) {
			foreach($content_fields as $index => $content) {
				$query = parent::$wpdb->query( parent::$wpdb->prepare( 
					"INSERT INTO " . parent::$wpdb->prefix . "arlo_contentfields 
					(et_id, cf_fieldname, cf_text, cf_order, e_contenttype, active) 
					VALUES ( %d, %s, %s, %s, %s, %s ) 
					", 
					$this->template_id,
					@$content->FieldName,
					@$content->Content->Text,
					$index,
					@$content->Content->ContentType,
					parent::$import_id
				));
				
				if ($query === false) {
					parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' . parent::$wpdb->last_query, parent::$import_id);
					throw new Exception('Database insert failed: ' . $table_name);
				}
			}		
		}
	}	
}