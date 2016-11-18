<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Timezones extends BaseImporter {
	public function __construct($plugin, $importer, $data, $iterator = 0) {
		parent::__construct($plugin, $importer, $data, $iterator);

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
			Logger::log_error('SQL error: ' . $this->dbl->last_error . ' ' . $this->dbl->last_query, $this->import_id);
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