<?php

namespace Arlo\Importer;

use Arlo\Logger;

class OnlineActivities extends BaseEntity {

	public function __construct($plugin, $importer, $data, $iterator = 0) {
		parent::__construct($plugin, $importer, $data, $iterator);

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
					@$item->Code,
					$item->Name,
					@$item->DeliveryDescription,
					$item->ViewUri,
					json_encode($item->ReferenceTerms),
					(!empty($item->Credits) ? json_encode($item->Credits) : ''),
					@$item->RegistrationInfo->RegisterMessage,
					@$item->RegistrationInfo->RegisterUri,
					(!empty($item->Region) ? $item->Region : ''),
					$this->import_id
				)
			);
							
			if ($query === false) {					
				Logger::log_error('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query, $this->import_id);
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