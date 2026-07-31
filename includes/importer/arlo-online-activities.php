<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;

class OnlineActivities extends BaseImporter {

	public function __construct($importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);
	}

	protected function save_entity($item) {
		global $wpdb;
		if (!empty($item->OnlineActivityID)) {					
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required,  Do not need cache for insert operation in data import process. Cache will be reset after the import process done.
			$query = $wpdb->query($wpdb->prepare( "INSERT INTO {$wpdb->prefix}arlo_onlineactivities 
					(oa_arlo_id, oat_arlo_id, oa_code, oa_name, oa_delivery_description, oa_viewuri, oa_reference_terms, oa_credits, oa_registermessage, oa_registeruri, oa_region, import_id) 
					VALUES ( %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s) 
					", 
					$item->OnlineActivityID,
					$item->TemplateID,
					!empty($item->Code) ? $item->Code : null,
					$item->Name,
					!empty($item->DeliveryDescription) ? $item->DeliveryDescription : null,
					$item->ViewUri,
					json_encode($item->ReferenceTerms),
					(!empty($item->Credits) ? json_encode($item->Credits) : ''),
					!empty($item->RegistrationInfo) && !empty($item->RegistrationInfo->RegisterMessage) ? $item->RegistrationInfo->RegisterMessage : null,
					!empty($item->RegistrationInfo) && !empty($item->RegistrationInfo->RegisterUri) ? $item->RegistrationInfo->RegisterUri : null,
					(!empty($item->Region) ? $item->Region : ''),
					$this->import_id
				)
			);
			
			if ($query === false) {					
				throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}	
			
			$this->id = $wpdb->insert_id;	
			
			if (isset($item->Tags) && !empty($item->Tags)) {
				$this->save_tags($item->Tags, $this->id, 'oa');
			}
			
			if(isset($item->AdvertisedOffers) && !empty($item->AdvertisedOffers)) {
				$this->save_advertised_offer($item->AdvertisedOffers, $item->Region, null, null, $this->id);
			}
		}
	}
}