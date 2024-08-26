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
		ORDER BY utc_offset, name
		";

		return $this->dbl->get_results($sql, ARRAY_A);
	}

}