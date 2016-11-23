<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Timezones extends BaseImporter {
	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $file_handler = null, $scheduler = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $file_handler, $scheduler);

		$this->table_name = $this->dbl->prefix . 'arlo_timezones';
	}

	protected function save_entity($item) {
		$query = $this->dbl->insert(
			$this->table_name,
			array(
				'id' => $item->TimeZoneID,
				'name' => $item->Name,
				'import_id' => $this->import_id
			),
			array(
				'%d', '%s', '%s'
			)
		);				

		if ($query === false) {
			throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' . $this->dbl->last_query);
		} else {
			if (is_array($item->TzNames)) {
				foreach ($item->TzNames as $TzName) {
					$query = $this->dbl->insert(
						$this->table_name . '_olson',
						array(
							'timezone_id' => $item->TimeZoneID,
							'olson_name' => $TzName,
							'import_id' => $this->import_id
						),
						array(
							'%d', '%s', '%s'
						)
					);
				} 
			}
		}		
	}
}