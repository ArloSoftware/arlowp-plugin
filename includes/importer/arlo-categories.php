<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports category records from the snapshot payload into arlo_categories.
 *
 * Each payload item becomes one category row for the current import, including
 * its parent relationship, display order, slug, and rich-text header/footer
 * content used by category views.
 */
class Categories extends AbstractImportStep {

	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
	}

	protected function import_entity( $entity_payload ) {
		global $wpdb;
		$slug = sanitize_title( $entity_payload->CategoryID . ' ' . $entity_payload->Name );
		// Delete any prior row for this business identity + import to enable safe replay
		// if a previous save was interrupted before the checkpoint advanced.
		$this->execute_prepared_query(
			"DELETE FROM {$wpdb->prefix}arlo_categories WHERE c_arlo_id = %d AND import_id = %d",
			[ $entity_payload->CategoryID, (int) $this->import_id ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required, do not need cache for data import process. Cache will be reset after the import process done.
		$query = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}arlo_categories
			(c_arlo_id, c_name, c_slug, c_header, c_footer, c_order, c_parent_id, import_id)
			VALUES ( %d, %s, %s, %s, %s, %d, %d, %d )
			",
				$entity_payload->CategoryID,
				$entity_payload->Name,
				$slug,
				! empty( $entity_payload->Description ) && ! empty( $entity_payload->Description->Text ) ? $entity_payload->Description->Text : null,
				! empty( $entity_payload->Footer ) && ! empty( $entity_payload->Footer->Text ) ? $entity_payload->Footer->Text : null,
				! empty( $entity_payload->SequenceIndex ) ? $entity_payload->SequenceIndex : null,
				! empty( $entity_payload->ParentCategoryID ) ? $entity_payload->ParentCategoryID : null,
				(int) $this->import_id
			)
		);

		if ( $query === false ) {
			throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
	}
}
