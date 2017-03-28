<?php

namespace Arlo\Importer;

use Arlo\Logger;

class GenerateStaticArrays extends BaseImporter {

	protected function save_entity($item) {}

	public function run() {
		$content = sprintf('<?php
namespace Arlo;

class GeneratedStaticArrays {
	public static $arlo_timezones = %s;
}', $this->get_arlo_timezones());

		if (file_put_contents(sprintf(trailingslashit(plugin_dir_path( __FILE__ )) . '../arlo-generated-static-arrays-%s.php', $this->import_id), $content) === false) {
			Logger::log_error('Couldn\'t create static array file', $this->import_id);
        } 

		$this->is_finished = true;
	}

	private function get_arlo_timezones() {
		$arlo_timezones = [];
		
		$timezones = $this->dbl->get_results("
		SELECT
			id,
			name,
			windows_tz_id
		FROM 
			" . $this->dbl->prefix . "arlo_timezones
		WHERE
			import_id = " . $this->import_id . "
		ORDER BY
			name
		");

		foreach ($timezones as $tz) {
			$arlo_timezones[$tz->id] = [
				'name' => $tz->name,
				'windows_tz_id' => $tz->windows_tz_id
			];
		}

		return var_export($arlo_timezones, true);
	}
}