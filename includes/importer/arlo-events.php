<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Events extends BaseImporter {

	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $file_handler = null, $scheduler = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $file_handler, $scheduler);

		$this->table_name = $this->dbl->prefix . 'arlo_events';
	}

	protected function save_entity($item) {
		if (!empty($item->EventID) && is_numeric($item->EventID) && $item->EventID > 0) {
			$entity_id = $this->save_event_data($item, 0);

			//only save for events, not for sessions
			if (isset($item->Tags) && !empty($item->Tags)) {
				$this->save_tags($item->Tags, $entity_id, 'event');
			}
		}
	}

	private function save_event_data($item = [], $parent_id = 0, $region = '') {	
		$query = $this->dbl->query(
			$this->dbl->prepare( 
				"INSERT INTO " . $this->table_name ." 
				(e_arlo_id, et_arlo_id, e_parent_arlo_id, e_code, e_name, e_startdatetime, e_finishdatetime, e_datetimeoffset, e_timezone, e_timezone_id, v_id, e_locationname, e_locationroomname, e_locationvisible , e_isfull, e_placesremaining, e_sessiondescription, e_notice, e_viewuri, e_registermessage, e_registeruri, e_providerorganisation, e_providerwebsite, e_isonline, e_credits, e_region, import_id) 
				VALUES ( %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) 
				", 
			    $item->EventID,
				@$item->EventTemplateID,
				$parent_id,
				@$item->Code,
				$item->Name,
				substr(@$item->StartDateTime,0,26),
				substr(@$item->EndDateTime,0,26),
				substr(@$item->StartDateTime,27,6),
				@$item->TimeZone,
				@$item->TimeZoneID,
				@$item->Location->VenueID,
				@$item->Location->Name,
				@$item->Location->VenueRoomName,
				(!empty($item->Location->ViewUri) ? 1 : 0 ),
				@$item->IsFull,
				@$item->PlacesRemaining,
				@$item->SessionsDescription,
				@$item->Notice,
				@$item->ViewUri,
				@$item->RegistrationInfo->RegisterMessage,
				@$item->RegistrationInfo->RegisterUri,
				@$item->Provider->Name,
				@$item->Provider->WebsiteUri,
				@$item->Location->IsOnline,
				(!empty($item->Credits) ? json_encode($item->Credits) : ''),
				(!empty($item->Region) ? $item->Region : $region),
				$this->import_id
			)
		);
                        
		if ($query === false) {					
			throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
		}	
		
		$entity_id = $this->dbl->insert_id;
		
		//advertised offers
		if(!empty($item->AdvertisedOffers) && is_array($item->AdvertisedOffers)) {
			$this->save_advertised_offer($item->AdvertisedOffers, (!empty($item->Region) ? $item->Region : $region), null, $entity_id);
		}
		
		// prsenters
		if(!empty($item->Presenters) && is_array($item->Presenters)) {
			$this->save_presenters($item->Presenters, $entity_id);
		}
		
		//Save session information
		if ($parent_id == 0 && isset($item->Sessions) && is_array($item->Sessions) && !empty($item->Sessions[0]->EventID) && $item->Sessions[0]->EventID != $item->EventID ) {
			foreach ($item->Sessions as $session) {
				$this->save_event_data($session, $item->EventID, $item->Region);
			}
		}

		return $entity_id;
	}

	private function save_presenters($presenters = [], $event_id) {
		if (empty($event_id)) throw new \Exception('No eventID given: ' . __CLASS__ . '::' . __FUNCTION__);

		if(!empty($presenters) && is_array($presenters)) {
			foreach($presenters as $index => $presenter) {
				$query = $this->dbl->query( $this->dbl->prepare( 
					"INSERT INTO " . $this->dbl->prefix . "arlo_events_presenters 
					(e_id, p_arlo_id, p_order, import_id) 
					VALUES ( %d, %d, %d, %s ) 
					", 
				    $event_id,
				    $presenter->PresenterID,
				    $index,
				    $this->import_id
				) );
				
				if ($query === false) {
					throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
				}
			}
		}		
	}
}