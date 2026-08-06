<?php

namespace ArloTraining;

class TimeZoneManager {
	
	private $plugin;

	public $timezones;
	public $indexed_timezones;

	public function __construct($plugin) {
		$this->plugin = $plugin;
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
		global $wpdb;
		$import_id = $this->plugin->get_importer()->get_current_import_id();
		$timezone_id = intval($timezone_id);

		return CacheControl::fetch_results($wpdb->prepare("
		SELECT
			id,
			name,
			windows_tz_id
		FROM
			{$wpdb->prefix}arlo_timezones
		WHERE
			import_id = %d
		ORDER BY utc_offset, name
		", $import_id), ARRAY_A);
	}

}