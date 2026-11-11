<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports event payload items, including sessions and related child records.
 *
 * For each top-level event this task writes the event row, any child session
 * rows, and the associated offers, presenters, and tags that hang off that
 * event cluster for the current import.
 */
class Events extends AbstractImportStep {

	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
	}

	protected function import_entity( $entity_payload ) {
		if ( ! empty( $entity_payload->EventID ) && is_numeric( $entity_payload->EventID ) && $entity_payload->EventID > 0 ) {
			$this->import_event_cluster( $entity_payload, 0 );
		}
	}

	/**
	 * Imports one event or session payload and its dependent child rows.
	 *
	 * Cleanup is scoped to the current import id and resolved region so replaying
	 * an AU event cluster, for example, does not delete the NZ rows for the same
	 * Arlo event ids.
	 *
	 * @param object $event_payload Decoded event or session payload from the snapshot fragment.
	 * @param int    $parent_id     Parent event Arlo id for session rows, or 0 for top-level events.
	 * @param string $region        Fallback region inherited from the parent event.
	 * @return int Inserted local event row id.
	 */
	private function import_event_cluster( $event_payload, $parent_id = 0, $region = '' ) {
		global $wpdb;
		$event_region = $this->normalise_region( ! empty( $event_payload->Region ) ? $event_payload->Region : $region );
		// Share the delta overlay mapping so full-shadow imports preserve zero, null, and absent availability identically.
		$availability = EventAvailabilityExtractor::extract_root_db_row_values( $event_payload );

		// Delete any prior cluster for this business identity + import to enable safe replay
		// if a previous save was interrupted before the checkpoint advanced.
		// For top-level events, this also covers all sessions whose e_parent_arlo_id points at the
		// parent event row's e_arlo_id value.
		// For session calls, there are no sub-sessions to match by parent id,
		// so the delete is scoped to the one session row and its children.
		$old_e_ids = $this->get_prepared_column_values(
			"SELECT e_id FROM {$wpdb->prefix}arlo_events WHERE (e_arlo_id = %d OR e_parent_arlo_id = %d) AND e_region = %s AND import_id = %d",
			[ $event_payload->EventID, $event_payload->EventID, $event_region, (int) $this->import_id ]
		);
		if ( ! empty( $old_e_ids ) ) {
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_offers', 'e_id', $old_e_ids );
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_events_presenters', 'e_id', $old_e_ids );
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_events_tags', 'e_id', $old_e_ids );
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_events', 'e_id', $old_e_ids );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required,  Do not need cache for insert operation in data import process.
		$query = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}arlo_events
				(e_arlo_id, et_arlo_id, e_parent_arlo_id, e_code, e_name, e_startdatetime, e_finishdatetime, e_startdatetimeoffset, e_finishdatetimeoffset, e_starttimezoneabbr, e_finishtimezoneabbr, e_timezone_id, v_id, e_locationname, e_locationroomname, e_locationvisible , e_isfull, e_placesremaining, e_sessiondescription, e_summary, e_notice, e_viewuri, e_registermessage, e_registeruri, e_providerorganisation, e_providerwebsite, e_isonline, e_credits, e_region, import_id)
				VALUES ( %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d )
				",
				$event_payload->EventID,
				! empty( $event_payload->EventTemplateID ) ? $event_payload->EventTemplateID : null,
				$parent_id,
				! empty( $event_payload->Code ) ? $event_payload->Code : null,
				$event_payload->Name,
				! empty( $event_payload->StartDateTime ) ? substr( $event_payload->StartDateTime, 0, 26 ) : '',
				! empty( $event_payload->EndDateTime ) ? substr( $event_payload->EndDateTime, 0, 26 ) : '',
				! empty( $event_payload->StartDateTime ) ? substr( $event_payload->StartDateTime, 27, 6 ) : '',
				! empty( $event_payload->EndDateTime ) ? substr( $event_payload->EndDateTime, 27, 6 ) : '',
				! empty( $event_payload->StartTimeZoneAbbr ) ? $event_payload->StartTimeZoneAbbr : null,
				! empty( $event_payload->EndTimeZoneAbbr ) ? $event_payload->EndTimeZoneAbbr : null,
				! empty( $event_payload->TimeZoneID ) ? $event_payload->TimeZoneID : null,
				! empty( $event_payload->Location ) && ! empty( $event_payload->Location->VenueID ) ? $event_payload->Location->VenueID : null,
				! empty( $event_payload->Location ) && ! empty( $event_payload->Location->Name ) ? $event_payload->Location->Name : null,
				! empty( $event_payload->Location ) && ! empty( $event_payload->Location->VenueRoomName ) ? $event_payload->Location->VenueRoomName : null,
				( ! empty( $event_payload->Location->ViewUri ) ? 1 : 0 ),
				$availability['e_isfull'],
				$availability['e_placesremaining'],
				! empty( $event_payload->SessionsDescription ) ? $event_payload->SessionsDescription : null,
				! empty( $event_payload->Summary ) ? $event_payload->Summary : null,
				! empty( $event_payload->Notice ) ? $event_payload->Notice : null,
				! empty( $event_payload->ViewUri ) ? $event_payload->ViewUri : null,
				$availability['e_registermessage'],
				$availability['e_registeruri'],
				! empty( $event_payload->Provider ) && ! empty( $event_payload->Provider->Name ) ? $event_payload->Provider->Name : null,
				! empty( $event_payload->Provider ) && ! empty( $event_payload->Provider->WebsiteUri ) ? $event_payload->Provider->WebsiteUri : null,
				! empty( $event_payload->Location ) && ! empty( $event_payload->Location->IsOnline ) ? $event_payload->Location->IsOnline : null,
				( ! empty( $event_payload->Credits ) ? wp_json_encode( $event_payload->Credits ) : '' ),
				$event_region,
				(int) $this->import_id
			)
		);

		if ( $query === false ) {
			throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}

		$entity_id = $wpdb->insert_id;

		//advertised offers
		if ( ! empty( $event_payload->AdvertisedOffers ) && is_array( $event_payload->AdvertisedOffers ) ) {
			$this->save_advertised_offer( $event_payload->AdvertisedOffers, $event_region, null, $entity_id );
		}

		//presenters
		if ( ! empty( $event_payload->Presenters ) && is_array( $event_payload->Presenters ) ) {
			$this->save_event_presenters( $event_payload->Presenters, $entity_id );
		}

		//event tags or session tags
		if ( ! empty( $event_payload->Tags ) && is_array( $event_payload->Tags ) ) {
			$this->save_tags( $event_payload->Tags, $entity_id, 'event' );
		}

		//Save session information
		if ( $parent_id == 0 && isset( $event_payload->Sessions ) && is_array( $event_payload->Sessions ) && ! empty( $event_payload->Sessions[0]->EventID ) && $event_payload->Sessions[0]->EventID != $event_payload->EventID ) {
			foreach ( $event_payload->Sessions as $session ) {
				$this->import_event_cluster( $session, $event_payload->EventID, $this->normalise_region( $event_payload->Region ) );
			}
		}

		return $entity_id;
	}

	private function save_event_presenters( $presenters, $event_id ) {
		global $wpdb;
		if ( empty( $event_id ) ) {
			throw new \Exception( 'No eventID given: ' . __CLASS__ . '::' . __FUNCTION__ ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}

		if ( ! empty( $presenters ) && is_array( $presenters ) ) {
			foreach ( $presenters as $index => $presenter ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required,  Do not need cache for insert operation in data import process. Cache will be reset after the import process done.
				$query = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO " . $wpdb->prefix . "arlo_events_presenters
					(e_id, p_arlo_id, p_order, import_id)
					VALUES ( %d, %d, %d, %d )
					",
						$event_id,
						$presenter->PresenterID,
						$index,
						(int) $this->import_id
					)
				);

				if ( $query === false ) {
					throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
				}
			}
		}
	}
}
