<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports presenter payload items and keeps their companion WP posts in sync.
 *
 * Each payload item updates or creates the presenter custom post, then writes
 * the corresponding presenter row used by Arlo catalogue views.
 */
class Presenters extends AbstractImportStep {

	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
	}

	protected function import_entity( $entity_payload ) {
		global $wpdb;
		$name = $entity_payload->FirstName . ' ' . $entity_payload->LastName;

		$slug = sanitize_title( $entity_payload->PresenterID . ' ' . $name );

		$post_config_array = array(
			'post_title'   => $name,
			// Always empty: arlo_the_content_presenter() replaces the page body with the presenter template at render time.
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_type'    => 'arlo_presenter',
			'post_name'    => $slug,
		);

		$post = arlo_get_post_by_name( $slug, 'arlo_presenter' );
		if ( is_null( $post ) || false === $post ) {
			// Forced sanitising: kses is skipped for users with unfiltered_html, but imported data is shared by all users and must be stored the same whoever runs the import.
			$post_id = $this->insert_post_sanitised( $post_config_array );
		} else {
			// Update path only, as the insert above already sanitises and the filters are not idempotent. post_name is untouched so URLs are stable.
			$post_config_array['ID'] = $post->ID;
			$post_id                 = $post->ID;
			$post_config_array       = $this->sanitise_post_text( $post_config_array, $post_id );
			$updated                 = $wpdb->update( $wpdb->prefix . 'posts', $post_config_array, array( 'id' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update of the plugin-owned Arlo post: importer updates deliberately avoid wp_update_post() so third-party post hooks do not fire for every imported entity. The title and content are sanitised first, as wp_insert_post() does on create, and a changed row is cleared from the core post cache below. Arlo's own caches are invalidated through CacheControl when the import finishes.
			// $wpdb->update() returns the number of changed rows, so an unchanged post skips the cache clear.
			if ( $updated ) {
				clean_post_cache( $post_id );
			}
		}

		if ( is_numeric( $post_id ) && $post_id > 0 ) {
			// Delete any prior row for this business identity + import to enable safe replay
			// if a previous save was interrupted before the checkpoint advanced.
			$this->execute_prepared_query(
				"DELETE FROM {$wpdb->prefix}arlo_presenters WHERE p_arlo_id = %d AND import_id = %d",
				[ $entity_payload->PresenterID, (int) $this->import_id ]
			);

			// we'll refresh cache after the entire import process finishes.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required,  Do not need cache for insert operation in data import process. Cache will be reset after the import process done.
			$query = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}arlo_presenters
                    (p_arlo_id, p_firstname, p_lastname, p_viewuri, p_profile, p_qualifications, p_interests, p_twitterid, p_facebookid, p_linkedinid, p_post_name, p_post_id, import_id)
                    VALUES
					( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d )
                ",
					$entity_payload->PresenterID,
					$entity_payload->FirstName,
					$entity_payload->LastName,
					! empty( $entity_payload->ViewUri ) ? $entity_payload->ViewUri : null,
					! empty( $entity_payload->Profile ) && ! empty( $entity_payload->Profile->ProfessionalProfile->Text ) ? $entity_payload->Profile->ProfessionalProfile->Text : null,
					! empty( $entity_payload->Profile ) && ! empty( $entity_payload->Profile->Qualifications->Text ) ? $entity_payload->Profile->Qualifications->Text : null,
					! empty( $entity_payload->Profile ) && ! empty( $entity_payload->Profile->Interests->Text ) ? $entity_payload->Profile->Interests->Text : null,
					! empty( $entity_payload->SocialNetworkInfo ) && ! empty( $entity_payload->SocialNetworkInfo->TwitterID ) ? $entity_payload->SocialNetworkInfo->TwitterID : null,
					! empty( $entity_payload->SocialNetworkInfo ) && ! empty( $entity_payload->SocialNetworkInfo->FacebookID ) ? $entity_payload->SocialNetworkInfo->FacebookID : null,
					! empty( $entity_payload->SocialNetworkInfo ) && ! empty( $entity_payload->SocialNetworkInfo->LinkedInID ) ? $entity_payload->SocialNetworkInfo->LinkedInID : null,
					$slug,
					$post_id,
					(int) $this->import_id
				)
			);

			if ( $query === false ) {
				throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
			}
		} else {
			throw new \Exception( 'Presenter post creation error: ' . $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}

		// create associated custom post, if it dosen't exist
		if ( ! arlo_get_post_by_name( $slug, 'arlo_presenter' ) ) {

		}
	}
}
