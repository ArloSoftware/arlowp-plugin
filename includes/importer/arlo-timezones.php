<?php

namespace Arlo\Importer;

class Timezones extends BaseEntity {
	public function __construct($plugin, $importer, $data, $iterator = 0) {
		parent::__construct($plugin, $importer, $data, $iterator);

		$this->table_name = $this->wpdb->prefix . 'arlo_timezones';
	}

	protected function save_entity($item) {
		$query = $this->wpdb->insert(
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
			Logger::log('SQL error: ' . $this->wpdb->last_error . ' ' . $this->wpdb->last_query, $this->import_id, null, false , true);
		} else {
			if (is_array($item->TzNames)) {
				foreach ($item->TzNames as $TzName) {
					$query = $this->wpdb->insert(
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