<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports event-template payload items and their related child records.
 *
 * Each payload item updates or creates the template's WP post, writes the
 * template row for the current import, and then persists categories,
 * presenters, content fields, tags, and advertised offers linked to it.
 */
class Templates extends AbstractImportStep {

	private $slug;

	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
	}

	protected function import_entity( $entity_payload ) {
		global $wpdb;
		$this->slug = sanitize_title( $entity_payload->TemplateID . ' ' . $entity_payload->Name );

		// Delete any prior cluster for this business identity + import to enable safe replay
		// if a previous save was interrupted before the checkpoint advanced.
		$et_region  = ! empty( $entity_payload->Region ) ? $this->normalise_region( $entity_payload->Region ) : '';
		$old_et_ids = $this->get_prepared_column_values(
			"SELECT et_id FROM {$wpdb->prefix}arlo_eventtemplates WHERE et_arlo_id = %d AND et_region = %s AND import_id = %d",
			[ $entity_payload->TemplateID, $et_region, (int) $this->import_id ]
		);
		if ( ! empty( $old_et_ids ) ) {
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_eventtemplates_tags', 'et_id', $old_et_ids );
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_offers', 'et_id', $old_et_ids );
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_contentfields', 'et_id', $old_et_ids );
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_eventtemplates_presenters', 'et_id', $old_et_ids );
			$this->delete_rows_by_ids( $wpdb->prefix . 'arlo_eventtemplates', 'et_id', $old_et_ids );
		}
		// Delete category links keyed by et_arlo_id (not et_id) to avoid stale associations.
		$this->execute_prepared_query(
			"DELETE FROM {$wpdb->prefix}arlo_eventtemplates_categories WHERE et_arlo_id = %d AND import_id = %d",
			[ $entity_payload->TemplateID, (int) $this->import_id ]
		);

		$description_summary = ! empty( $entity_payload->Description ) && ! empty( $entity_payload->Description->Summary ) ? $entity_payload->Description->Summary : null;
		$post_id             = $this->save_update_wp_post( $entity_payload->Name, $description_summary );

		if ( $post_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching  -- Direct database query is required. No cache needed for the insert operation in data import process. Cache will be reset after the import process done.
			$query = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}arlo_eventtemplates
					(et_arlo_id, et_code, et_name, et_descriptionsummary, et_advertised_duration, et_post_name, et_post_id, import_id, et_registerinteresturi, et_registerprivateinteresturi, et_credits, et_viewuri, et_hero_image, et_list_image, et_region)
					VALUES ( %d, %s, %s, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s)
					",
					$entity_payload->TemplateID,
					! empty( $entity_payload->Code ) ? $entity_payload->Code : null,
					$entity_payload->Name,
					$description_summary,
					! empty( $entity_payload->AdvertisedDuration ) ? $entity_payload->AdvertisedDuration : null,
					$this->slug,
					$post_id,
					(int) $this->import_id,
					! empty( $entity_payload->RegisterInterestUri ) ? $entity_payload->RegisterInterestUri : '',
					! empty( $entity_payload->RegisterPrivateInterestUri ) ? $entity_payload->RegisterPrivateInterestUri : '',
					! empty( $entity_payload->Credits ) ? wp_json_encode( $entity_payload->Credits ) : '',
					! empty( $entity_payload->ViewUri ) ? $entity_payload->ViewUri : '',
					! empty( $entity_payload->Media->{'Template.HeroImage'}->Uri ) ? $entity_payload->Media->{'Template.HeroImage'}->Uri : '',
					! empty( $entity_payload->Media->{'Template.ListImage'}->Uri ) ? $entity_payload->Media->{'Template.ListImage'}->Uri : '',
					$et_region
				)
			);

			if ( $query === false ) {
				throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
			}
		} else {
			throw new \Exception( 'WP Post creation error ' . $this->slug ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}

		$this->id = $wpdb->insert_id;

		//tags
		if ( isset( $entity_payload->Tags ) && ! empty( $entity_payload->Tags ) ) {
			$this->save_tags( $entity_payload->Tags, $this->id, 'template' );
		}

		// advertised offers
		if ( ! empty( $entity_payload->BestAdvertisedOffers ) && is_array( $entity_payload->BestAdvertisedOffers ) ) {
			$this->save_advertised_offer( $entity_payload->BestAdvertisedOffers, $entity_payload->Region, $this->id );
		}

		// content fields
		if ( ! empty( $entity_payload->Description->ContentFields ) && is_array( $entity_payload->Description->ContentFields ) ) {
			$this->save_content_fields( $entity_payload->Description->ContentFields );
		}

		// presenters
		if ( ! empty( $entity_payload->AdvertisedPresenters ) && is_array( $entity_payload->AdvertisedPresenters ) ) {
			$this->save_advertised_presenters( $entity_payload->AdvertisedPresenters );
		}

		// categories
		if ( ! empty( $entity_payload->Categories ) && is_array( $entity_payload->Categories ) ) {
			$this->save_categories( $entity_payload->Categories, $entity_payload->TemplateID );
		}
	}

	private function save_update_wp_post( $title, $content = '' ) {
		global $wpdb;
		// create associated custom post, if it dosen't exist
		$post_config_array = array(
			'post_title'   => $title,
			'post_content' => (string) $content,
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_type'    => 'arlo_event',
			'post_name'    => $this->slug,
		);

		$post = arlo_get_post_by_name( $this->slug, 'arlo_event' );

		if ( ! $post ) {
			// Forced sanitising: kses is skipped for users with unfiltered_html, but imported data is shared by all users and must be stored the same whoever runs the import.
			$post_id = $this->insert_post_sanitised( $post_config_array, true );
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

		return $post_id;
	}

	private function save_categories( $categories, $template_id ) {
		global $wpdb;
		if ( empty( $template_id ) || ! is_numeric( $template_id ) ) {
			throw new \Exception( 'No templateID given: ' . __CLASS__ . '::' . __FUNCTION__ ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}

		if ( ! empty( $categories ) && is_array( $categories ) ) {
			foreach ( $categories as $index => $category ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No cache needed for the insert operation in data import process. Direct database query is required for custom table.
				$query = $wpdb->query(
					$wpdb->prepare(
						"REPLACE INTO {$wpdb->prefix}arlo_eventtemplates_categories
                    (et_arlo_id, c_arlo_id, import_id)
					VALUES ( %d, %d, %d )
                    ",
						$template_id,
						$category->CategoryID,
						(int) $this->import_id
					)
				);

				if ( $query === false ) {
					throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
				}
			}
		}
	}

	private function save_advertised_presenters( $advertised_presenters = [] ) {
		global $wpdb;
		if ( empty( $this->id ) ) {
			throw new \Exception( 'No templateID given: ' . __CLASS__ . '::' . __FUNCTION__ ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
		if ( ! empty( $advertised_presenters ) && is_array( $advertised_presenters ) ) {
			foreach ( $advertised_presenters as $index => $presenter ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No cache needed for the insert operation in data import process. Direct database query is required for custom table.
				$query = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}arlo_eventtemplates_presenters
                    (et_id, p_arlo_id, p_order, import_id)
					VALUES ( %d, %d, %d, %d )
                    ",
						$this->id,
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

	private function save_content_fields( $content_fields = [] ) {
		global $wpdb;
		if ( empty( $this->id ) ) {
			throw new \Exception( 'No templateID given: ' . __CLASS__ . '::' . __FUNCTION__ ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
		if ( ! empty( $content_fields ) && is_array( $content_fields ) ) {
			foreach ( $content_fields as $index => $content ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. No cache needed for the insert operation in data import process.
				$query = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}arlo_contentfields
					(et_id, cf_fieldname, cf_text, cf_order, e_contenttype, import_id)
					VALUES ( %d, %s, %s, %s, %s, %d )
					",
						$this->id,
						! empty( $content->FieldName ) ? $content->FieldName : null,
						! empty( $content->Content ) && ! empty( $content->Content->Text ) ? $content->Content->Text : null,
						$index,
						! empty( $content->Content ) && ! empty( $content->Content->ContentType ) ? $content->Content->ContentType : null,
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
