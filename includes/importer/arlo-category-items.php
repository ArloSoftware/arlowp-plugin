<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;

class CategoryItems extends BaseImporter {

	public function __construct($importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);
	}

	protected function save_entity($item) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for UPDATE operation in data import process. Cache will be reset after the import process done.
		$query = $wpdb->query( $wpdb->prepare("
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
		", !empty($item->SequenceIndex) ? $item->SequenceIndex : 0, $item->EventTemplateID, $item->CategoryID, $this->import_id) );
		
		if ($query === false) {
			throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}
}