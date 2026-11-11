<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Applies category ordering metadata to imported template/category links.
 *
 * This task updates the bridge table rows created during template import so
 * each template/category association carries the sequence index supplied by the
 * snapshot payload.
 */
class CategoryItems extends AbstractImportStep {

	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
	}

	protected function import_entity( $entity_payload ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for UPDATE operation in data import process. Cache will be reset after the import process done.
		$query = $wpdb->query(
			$wpdb->prepare(
				"
		UPDATE
			{$wpdb->prefix}arlo_eventtemplates_categories
		SET
			et_order = %d
		WHERE
			et_arlo_id = %d
		AND
			c_arlo_id = %d
		AND
			import_id = %d
		",
				! empty( $entity_payload->SequenceIndex ) ? $entity_payload->SequenceIndex : 0,
				$entity_payload->EventTemplateID,
				$entity_payload->CategoryID,
				$this->import_id
			)
		);

		if ( $query === false ) {
			throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
	}
}
