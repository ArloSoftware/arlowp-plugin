<?php

namespace Arlo\Importer;

class Timezones extends Importer {

	public function __construct() {	}

	public function import() {
		$table_name = parent::$wpdb->prefix . 'arlo_timezones'; 

		if (!empty(parent::$data_json->TimeZones) && is_array(parent::$data_json->TimeZones)) {
			foreach(parent::$data_json->TimeZones as $item) {
				$query = parent::$wpdb->insert(
					$table_name,
					array(
						'id' => $item->TimeZoneID,
						'name' => $item->Name,
						'import_id' => parent::$import_id
					),
					array(
						'%d', '%s', '%s'
					)
				);				
				if ($query === false) {
					parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' .parent::$wpdb->last_query, parent::$import_id);
					throw new Exception('Database insert failed: ' . $table_name);
				} else {
					if (is_array($item->TzNames)) {
						foreach ($item->TzNames as $TzName) {
							$query = parent::$wpdb->insert(
								$table_name . '_olson',
								array(
									'timezone_id' => $item->TimeZoneID,
									'olson_name' => $TzName,
									'import_id' => parent::$import_id
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
	}
}