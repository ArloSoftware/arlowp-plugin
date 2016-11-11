<?php

namespace Arlo\Importer;

error_reporting(E_ALL);
ini_set('display_error', 1);

use Arlo\Singleton;

class Importer extends Singleton {
	public static $filename;
	public static $is_finished = false;

	protected static $dir;
	protected static $plugin;
	protected static $data_json;
	protected static $wpdb;

	protected $environment;
	public $import_id;

	private $import_timezones;
	private $import_presenters;
	private $import_venues;
	private $import_event_templates;
	private $import_events;
	private $import_onlineactivities;
	private $import_categories;
	private $import_finish;
	
	//the keys in this array have to match with the keys in the JSON 
	//except the last two
	public $import_tasks = [
				'TimeZones' => "Importing time zones",
				'Presenters' => "Importing presenters",
				'Venues' => "Importing venues",
				'Templates' => "Importing event templates",
				'Events' => "Importing events",
				'OnlineActivities' => "Importing online activities",
				'Categories' => "Importing categories",
				'CategoryItems' => 'Updating templates order in category',
				'CategoryDepth' => 'Updating category depth',			
				'Finish' => 'Finalize the import',
			];	

	public $current_task;
	public $current_task_class;
	public $current_task_num;
	public $current_task_desc = '';

	private $current_task_iterator = 0;
	private $irregular_tasks = [
				'CategoryDepth',
				'Finish',
			];

	public function __construct($plugin) {
		global $wpdb;
		
		self::$wpdb = &$wpdb; 
		self::$dir = trailingslashit(plugin_dir_path( __FILE__ )).'../../import/';
		self::$filename = 'data'; //TODO: Change it
		self::$plugin = $plugin;

		$this->environment = new \Arlo\Environment();
	}

	public function set_import_id($import_id) {
		$this->import_id = $import_id;
	}

	public function set_state($state) {
		$task_keys = array_keys($this->import_tasks);

		if (!empty($state)) {
			if (!empty($state->current_subtask)) {
				$this->set_current_task($state->current_subtask);
				$this->current_task_iterator = (!empty($state->iterator) && is_numeric($state->iterator) ? $state->iterator + 1 : 0);
			} else if (!empty($state->finished_subtask)) {
				//figure out the next task;
				$k = array_search($state->finished_subtask, $task_keys);
				if ($k !== false && isset($task_keys[++$k])) {
					$this->set_current_task($task_keys[$k]);			
				} else {
					self::$is_finished = true;
				}
			}
		} else {
			$this->set_current_task($task_keys[0]);
		}
	}

	public function get_state() {
		$state = [
			'finished_subtask' => null,
			'current_subtask' => null ,
			'iterator' => null
		];
	
		if ($this->current_task_class->is_finished) {
			$state['finished_subtask'] = $this->current_task;
		} else {
			$state['current_subtask'] = $this->current_task;
			$state['iterator'] = $this->current_task_class->iterator;
		}

		return $state;
	}

    public function check_viable_execution_environment() { 
        return $this->environment->check_viable_execution_environment();
    }

	public function set_current_task($task_step) {
		$this->current_task = $task_step;
		$this->current_task_num = array_search($this->current_task, array_keys($this->import_tasks));
		$this->current_task_desc = $this->import_tasks[$this->current_task];
	}

	private function get_data_json() {
		$filename = self::$dir . self::$filename . '.json';
		
		if (is_null(self::$data_json)) {
			self::$data_json = json_decode(mb_strcut(utf8_encode($this->read_file($filename)), 6));
		}
	}

	protected function read_file($filename) {
		if (!empty($filename) && file_exists($filename)) {
			$fp = fopen($filename, 'r');
			$content = fread($fp, filesize($filename));
			fclose($fp);

			return $content;
		} else {
			\Arlo\Logger::log_error('The file doesn\'t exist: ' . self::$filename, $this->import_id);
		}
	}

	protected function write_file($filename, $data) {
		$fp = fopen($file, 'w+');

		$success = fwrite($fp, $data);

		fclose($fp);

		return $success;
	}

	public function decrypt() {
		$filename = self::$dir . self::$filename;
		$json = json_decode(utf8_encode($this->read_file($filename)));

		if (isset($json->__encrypted__)) {
			try {
				self::$data_json = Crypto::decrypt($json->__encrypted__);
				
				$this->write_file($filename . '.json', self::$data_json);
			} catch (\Exception $e) {
				\Arlo\Logger::log_error('Couldn\'t decrypt the file: ' . $e->getMessage(), $this->import_id);				
			}
		}

		return false;

		unset($json);
	}

	public function run() {
		if (!self::$is_finished && isset($this->import_tasks[$this->current_task])) {
			$this->run_import_task($this->current_task);
		}
	}

	private function run_import_task($import_task) {
		$this->get_data_json(); 
		
		if (!empty(self::$data_json->$import_task) || in_array($import_task, $this->irregular_tasks)) {
			$this->environment->start_time = time(); // Set start time of current process.
			
			\Arlo\Logger::log('Import subtask started: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc, $this->import_id);

			$class_name = "Arlo\Importer\\" . $import_task;
			
			$this->current_task_class = new $class_name(self::$plugin, $this, (!empty(self::$data_json->$import_task) ? self::$data_json->$import_task : null), $this->current_task_iterator);
			$this->current_task_class->import();
			
			\Arlo\Logger::log('Import subtask ended: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc, $this->import_id);			
		}
	}
}