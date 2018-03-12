<?php

namespace Arlo;

class TimeZoneManager {

	private $dbl;	
	private $plugin;

	public $timezones;
	public $indexed_timezones;

	public function __construct($plugin, $dbl) {
		$this->plugin = $plugin;
		$this->dbl = &$dbl; 		
	}

	public function get_timezones() {
		if (!$this->timezones) {
			$this->timezones = $this->query_timezones();
		}

		return $this->timezones;
	}

	public function get_indexed_timezones($timezone_id = 0) {
		if (!$this->indexed_timezones) {
			$this->timezones = $this->get_timezones();

			$this->indexed_timezones = [];
			foreach ($this->timezones as $timezone) {
				$this->indexed_timezones[$timezone['id']] = $timezone;
			}
		}

		if (intval($timezone_id) > 0) {
			return !empty($this->indexed_timezones[$timezone_id]) ? $this->indexed_timezones[$timezone_id] : null;
		} 

		return $this->indexed_timezones;
	}	

	private function query_timezones($timezone_id = 0) {
		$table = $this->dbl->prefix . "arlo_timezones";
		$import_id = $this->plugin->get_importer()->get_current_import_id();
		$timezone_id = intval($timezone_id);

		$sql = "
		SELECT
			id,
			name,
			windows_tz_id
		FROM
			{$table}
		WHERE
			import_id = " . $import_id . "
		ORDER BY name
		";

		$items = $this->dbl->get_results($sql, ARRAY_A);

		//until we introduce BaseUtcOffset to use in order by
		uasort($items, 'self::sort_timezone_by_offset');

		return $items;
	}

	private static function sort_timezone_by_offset($a, $b) {
		//trick to guess timezone order from timezone like:
		//(UTC+11:00) Solomon Is., New Caledonia
		$ka = substr($a['name'], 4, 3) . substr($a['name'], 8, 2);
		$kb = substr($b['name'], 4, 3) . substr($b['name'], 8, 2);
		if (!is_numeric($ka) || !is_numeric($kb) || !strcmp($ka, $kb)) {
			return strcmp($a['name'], $b['name']);
		}
		return $ka > $kb;
	}

}