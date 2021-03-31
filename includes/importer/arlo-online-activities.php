<?php

namespace Arlo\Importer;

use Arlo\Logger;

class OnlineActivities extends BaseImporter {

	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);

		$this->table_name = $this->dbl->prefix . 'arlo_onlineactivities';
	}

	protected function save_entity($item) {
		if (!empty($item->OnlineActivityID)) {					
			$query = $this->dbl->query(
				$this->dbl->prepare( 
					"INSERT INTO " . $this->table_name . " 
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
				throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
			}	
			
			$this->id = $this->dbl->insert_id;	
			
			if (isset($item->Tags) && !empty($item->Tags)) {
				$this->save_tags($item->Tags, $this->id, 'oa', $this->import_id);
			}
			
			if(isset($item->AdvertisedOffers) && !empty($item->AdvertisedOffers)) {
				$this->save_advertised_offer($item->AdvertisedOffers, $item->Region, null, null, $this->id);
			}
		}
	}
}