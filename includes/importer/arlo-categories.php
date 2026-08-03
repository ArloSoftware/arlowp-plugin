<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;

class Categories extends BaseImporter {

	public function __construct($importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);
	}

	protected function save_entity($item) {
		global $wpdb;
		$slug = sanitize_title($item->CategoryID . ' ' . $item->Name);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,  -- Direct database query is required, do not need cache for data import process. Cache will be reset after the import process done.
		$query = $wpdb->query( $wpdb->prepare("INSERT INTO {$wpdb->prefix}arlo_categories 
			(c_arlo_id, c_name, c_slug, c_header, c_footer, c_order, c_parent_id, import_id) 
			VALUES ( %d, %s, %s, %s, %s, %d, %d, %s ) 
			", 
			$item->CategoryID,
			$item->Name,
			$slug,
			!empty($item->Description) && !empty($item->Description->Text) ? $item->Description->Text : null,
			!empty($item->Footer) && !empty($item->Footer->Text) ? $item->Footer->Text : null,
			!empty($item->SequenceIndex) ? $item->SequenceIndex : null,
			!empty($item->ParentCategoryID) ? $item->ParentCategoryID : null,
			$this->import_id
		) );

		if ($query === false) {
			throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}
}