<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;

class Timezones extends BaseImporter {
	public function __construct($importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);
	}

	protected function save_entity($item) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. No cache needed for the insert operation in data import process. Cache will be reset after the import process done.
		$query = $wpdb->insert(
			$wpdb->prefix . 'arlo_timezones',
			array(
				'id' => $item->TimeZoneID,
				'name' => $item->Name,
				'windows_tz_id' => $item->WindowsTzID,
				'utc_offset' => $item->UtcOffset,
				'import_id' => $this->import_id
			),
			array(
				'%d', '%s', '%s', '%d', '%s'
			)
		);

		if ($query === false) {
			throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		} 		
	}
}