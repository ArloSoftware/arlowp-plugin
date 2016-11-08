<?php

namespace Arlo\Importer;

class OnlineActivities extends Importer {

	private $oa_id;

	public function __construct() {	}

	public function import() {
		$table_name = parent::$wpdb->prefix . 'arlo_onlineactivities';

		if (!empty(parent::$data_json->OnlineActivities) && is_array(parent::$data_json->OnlineActivities)) { 
			foreach(parent::$data_json->OnlineActivities as $item) {	
				if (!empty($item->OnlineActivityID)) {
					
					$query = parent::$wpdb->query(
						parent::$wpdb->prepare( 
							"INSERT INTO $table_name 
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
							parent::$import_id
						)
					);
									
					if ($query === false) {					
						self::add_log('SQL error: ' . parent::$wpdb->last_error . ' ' .parent::$wpdb->last_query, parent::$import_id);
						throw new Exception('Database insert failed: ' . $table_name);
					}	
					
					$this->oa_id = parent::$wpdb->insert_id;	
					
					if (isset($item->Tags) && !empty($item->Tags)) {
						$this->save_tags($item->Tags, $this->oa_id, 'oa', parent::$import_id);
					}
					
					if(isset($item->AdvertisedOffers) && !empty($item->AdvertisedOffers)) {
						$this->save_advertised_offer($item->AdvertisedOffers, $item->Region, null, null, $this->oa_id);
					}
				}
			}		
		}
	}
}