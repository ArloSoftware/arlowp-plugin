<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Timezones extends BaseImporter {
	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);

		$this->table_name = $this->dbl->prefix . 'arlo_timezones';
	}

	protected function save_entity($item) {
		$query = $this->dbl->insert(
			$this->table_name,
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
			throw new \Exception('SQL error: ' . $this->dbl->last_error );
		} 		
	}
}