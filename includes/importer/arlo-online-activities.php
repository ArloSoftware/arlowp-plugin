<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports online activity payload items and their related child data.
 *
 * Each payload item becomes one online-activity row for the current import,
 * along with any attached tags and advertised offers.
 */
class OnlineActivities extends AbstractImportStep {

	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
	}

	protected function import_entity( $entity_payload ) {
		global $wpdb;
		if ( ! empty( $entity_payload->OnlineActivityID ) ) {
			// Delete any prior cluster for this business identity + import to enable safe replay
			// if a previous save was interrupted before the checkpoint advanced.
			$oa_region  = ! empty( $entity_payload->Region ) ? $this->normalise_region( $entity_payload->Region ) : '';
			$old_oa_ids = $this->get_prepared_column_values(
				"SELECT oa_id FROM {$wpdb->prefix}arlo_onlineactivities WHERE oa_arlo_id = %s AND oa_region = %s AND import_id = %d",
				[ $entity_payload->OnlineActivityID, $oa_region, (int) $this->import_id ]
			);
			if ( ! empty( $old_oa_ids ) ) {
				$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_onlineactivities_tags', 'oa_id', $old_oa_ids );
				$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_offers', 'oa_id', $old_oa_ids );
				$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_onlineactivities', 'oa_id', $old_oa_ids );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required,  Do not need cache for insert operation in data import process. Cache will be reset after the import process done.
			$query = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}arlo_onlineactivities
					(oa_arlo_id, oat_arlo_id, oa_code, oa_name, oa_delivery_description, oa_viewuri, oa_reference_terms, oa_credits, oa_registermessage, oa_registeruri, oa_region, import_id)
					VALUES ( %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d)
					",
					$entity_payload->OnlineActivityID,
					$entity_payload->TemplateID,
					! empty( $entity_payload->Code ) ? $entity_payload->Code : null,
					$entity_payload->Name,
					! empty( $entity_payload->DeliveryDescription ) ? $entity_payload->DeliveryDescription : null,
					$entity_payload->ViewUri,
					wp_json_encode( $entity_payload->ReferenceTerms ),
					( ! empty( $entity_payload->Credits ) ? wp_json_encode( $entity_payload->Credits ) : '' ),
					! empty( $entity_payload->RegistrationInfo ) && ! empty( $entity_payload->RegistrationInfo->RegisterMessage ) ? $entity_payload->RegistrationInfo->RegisterMessage : null,
					! empty( $entity_payload->RegistrationInfo ) && ! empty( $entity_payload->RegistrationInfo->RegisterUri ) ? $entity_payload->RegistrationInfo->RegisterUri : null,
					$oa_region,
					(int) $this->import_id
				)
			);

			if ( $query === false ) {
				throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
			}

			$this->id = $wpdb->insert_id;

			if ( isset( $entity_payload->Tags ) && ! empty( $entity_payload->Tags ) ) {
				$this->save_tags( $entity_payload->Tags, $this->id, 'oa' );
			}

			if ( isset( $entity_payload->AdvertisedOffers ) && ! empty( $entity_payload->AdvertisedOffers ) ) {
				$this->save_advertised_offer( $entity_payload->AdvertisedOffers, $entity_payload->Region, null, null, $this->id );
			}
		}
	}
}
