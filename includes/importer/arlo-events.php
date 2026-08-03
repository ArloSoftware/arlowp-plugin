<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;

class Events extends BaseImporter {

	public function __construct($importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);
	}

	protected function save_entity($item) {
		if (!empty($item->EventID) && is_numeric($item->EventID) && $item->EventID > 0) {
			$entity_id = $this->save_event_data($item, 0);
		}
	}

	private function save_event_data($item = [], $parent_id = 0, $region = '') {	
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required,  Do not need cache for insert operation in data import process.
		$query = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}arlo_events 
				(e_arlo_id, et_arlo_id, e_parent_arlo_id, e_code, e_name, e_startdatetime, e_finishdatetime, e_startdatetimeoffset, e_finishdatetimeoffset, e_starttimezoneabbr, e_finishtimezoneabbr, e_timezone_id, v_id, e_locationname, e_locationroomname, e_locationvisible , e_isfull, e_placesremaining, e_sessiondescription, e_summary, e_notice, e_viewuri, e_registermessage, e_registeruri, e_providerorganisation, e_providerwebsite, e_isonline, e_credits, e_region, import_id) 
				VALUES ( %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) 
				", 
			    $item->EventID,
				!empty($item->EventTemplateID) ? $item->EventTemplateID : null,
				$parent_id,
				!empty($item->Code) ? $item->Code : null,
				$item->Name,
				!empty($item->StartDateTime) ? substr($item->StartDateTime,0,26) : '',
				!empty($item->EndDateTime) ? substr($item->EndDateTime,0,26) : '',
				!empty($item->StartDateTime) ? substr($item->StartDateTime,27,6) : '',
				!empty($item->EndDateTime) ? substr($item->EndDateTime,27,6) : '',
				!empty($item->StartTimeZoneAbbr) ? $item->StartTimeZoneAbbr : null,
				!empty($item->EndTimeZoneAbbr) ? $item->EndTimeZoneAbbr : null,
				!empty($item->TimeZoneID) ? $item->TimeZoneID : null,
				!empty($item->Location) && !empty($item->Location->VenueID) ? $item->Location->VenueID : null,
				!empty($item->Location) && !empty($item->Location->Name) ? $item->Location->Name : null,
				!empty($item->Location) && !empty($item->Location->VenueRoomName) ? $item->Location->VenueRoomName : null,
				(!empty($item->Location->ViewUri) ? 1 : 0 ),
				!empty($item->IsFull) ? $item->IsFull : null,
				!empty($item->PlacesRemaining) ? $item->PlacesRemaining : null,
				!empty($item->SessionsDescription) ? $item->SessionsDescription : null,
				!empty($item->Summary) ? $item->Summary : null,
				!empty($item->Notice) ? $item->Notice : null,
				!empty($item->ViewUri) ? $item->ViewUri : null,
				!empty($item->RegistrationInfo) && !empty($item->RegistrationInfo->RegisterMessage) ? $item->RegistrationInfo->RegisterMessage : null,
				!empty($item->RegistrationInfo) && !empty($item->RegistrationInfo->RegisterUri) ? $item->RegistrationInfo->RegisterUri : null,
				!empty($item->Provider) && !empty($item->Provider->Name) ? $item->Provider->Name : null,
				!empty($item->Provider) && !empty($item->Provider->WebsiteUri) ? $item->Provider->WebsiteUri : null,
				!empty($item->Location) && !empty($item->Location->IsOnline) ? $item->Location->IsOnline : null,
				(!empty($item->Credits) ? json_encode($item->Credits) : ''),
				(!empty($item->Region) ? $item->Region : $region),
				$this->import_id
			)
		);
                        
		if ($query === false) {					
			throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}	
		
		$entity_id = $wpdb->insert_id;
		
		//advertised offers
		if(!empty($item->AdvertisedOffers) && is_array($item->AdvertisedOffers)) {
			$this->save_advertised_offer($item->AdvertisedOffers, (!empty($item->Region) ? $item->Region : $region), null, $entity_id);
		}
		
		//presenters
		if(!empty($item->Presenters) && is_array($item->Presenters)) {
			$this->save_presenters($item->Presenters, $entity_id);
		}
		
		//event tags or session tags
		if(!empty($item->Tags) && is_array($item->Tags)) {
			$this->save_tags($item->Tags, $entity_id, 'event');
		}
		
		//Save session information
		if ($parent_id == 0 && isset($item->Sessions) && is_array($item->Sessions) && !empty($item->Sessions[0]->EventID) && $item->Sessions[0]->EventID != $item->EventID ) {
			foreach ($item->Sessions as $session) {
				$this->save_event_data($session, $item->EventID, $item->Region);
			}
		}

		return $entity_id;
	}

	private function save_presenters($presenters, $event_id) {
		global $wpdb;
		if (empty($event_id)) throw new \Exception('No eventID given: ' . __CLASS__ . '::' . __FUNCTION__);

		if(!empty($presenters) && is_array($presenters)) {
			foreach($presenters as $index => $presenter) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required,  Do not need cache for insert operation in data import process. Cache will be reset after the import process done.
				$query = $wpdb->query( $wpdb->prepare( 
					"INSERT INTO " . $wpdb->prefix . "arlo_events_presenters 
					(e_id, p_arlo_id, p_order, import_id) 
					VALUES ( %d, %d, %d, %s ) 
					", 
				    $event_id,
				    $presenter->PresenterID,
				    $index,
				    $this->import_id
				) );
				
				if ($query === false) {
					throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
				}
			}
		}		
	}
}