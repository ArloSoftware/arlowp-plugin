<?php

namespace Arlo\Importer;

use Arlo\Singleton;
use Arlo\Logger;
use Arlo\Utilities;

class Importer extends Singleton {
	public static $filename;
	public static $is_finished = false;

	protected static $dir;
	protected static $plugin;
	protected static $data_json;

	public $import_id;
	
	private $current_import_id;
	private $last_import_date;

	private $environment;
	private $message_handler;
	private $dbl;

	
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

	public function __construct($environment, $dbl, $message_handler) {
		self::$dir = trailingslashit(plugin_dir_path( __FILE__ )).'../../import/';
		self::$filename = 'data'; //TODO: Change it

		$this->environment = $environment;
		$this->dbl = $dbl;
		$this->message_handler = $message_handler;
	}

	public function set_import_id($import_id) {
		$this->import_id = $import_id;
	}

	public function set_current_import_id($import_id) {
		update_option('arlo_import_id', $import_id);
                               
		$this->current_import_id = $import_id;		
	}

	public function get_current_import_id() {
        //need to access the db directly, get_option('arlo_import_id'); can return a cached (old) value
        $table_name = $this->dbl->prefix . "options";
        
        $sql = "SELECT option_value
			FROM $table_name 
            WHERE option_name = 'arlo_import_id'";
	               
		$this->current_import_id = $this->dbl->get_var($sql);
                
		return $this->current_import_id;		
	}

	public function set_last_import_date() {
		$now = Utilities::get_now_utc();
       	$timestamp = $now->format("Y-m-d H:i:s");	
	
		update_option('arlo_last_import', $timestamp);
		$this->last_import_date = $timestamp;
	}	

	public function get_last_import_date() {
		if(!is_null($this->last_import_date)) {
			return $this->last_import_date;
		}
		
		$this->last_import_date = get_option('arlo_last_import');
		
		return $this->last_import_date;
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

	public function should_importer_run($force = false) {
		if(!$force) {
			Logger::log('Synchronization Started', $this->import_id);
			Logger::log('Synchronization identified as automatic synchronization.', $this->import_id);
			if(!empty($last)) {
				Logger::log('Previous successful synchronization found.', $this->import_id);
				if(strtotime('-1 hour') > strtotime($this->get_last_import_date())) {
					Logger::log('Synchronization more than an hour old. Synchronization required.', $this->import_id);
				}
				else {
					Logger::log('Synchronization less than an hour old (' . date("Y-m-d H:i:s", strtotime($this->get_last_import_date())) . '). Synchronization stopped.', $this->import_id);
					return false;
				}
			}
		}

		return true;
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
			Logger::log_error('The file doesn\'t exist: ' . self::$filename, $this->import_id);
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
				Logger::log_error('Couldn\'t decrypt the file: ' . $e->getMessage(), $this->import_id);				
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

   public function clear_import_lock() {
        $table_name = $this->dbl->prefix . "arlo_import_lock";
      
        $query = $this->dbl->query('DELETE FROM ' . $table_name);
    }     
    
    public function get_import_lock_entries_number() {
        $table_name = $this->dbl->prefix ."arlo_import_lock";
        
        $sql = '
            SELECT 
                lock_acquired
            FROM
                ' . $table_name . '
            WHERE
                lock_expired > NOW()
            ';
	               
        $this->dbl->get_results($sql);
        
        return $this->dbl->num_rows;
    }
    
    private function cleanup_import_lock() {
        $table_name = $this->dbl->prefix ."arlo_import_lock";
      
        $this->dbl->query(
            'DELETE FROM  
                ' . $table_name . '
            WHERE 
                lock_expired < NOW()
            '
        );
    }
    
    private function add_import_lock() {
        
        $table_lock = $this->dbl->prefix . "arlo_import_lock";
        $table_log = $this->dbl->prefix . "arlo_log";
        
        $query = $this->dbl->query(
                'INSERT INTO ' . $table_lock . ' (import_id, lock_acquired, lock_expired)
                SELECT ' . $this->import_id . ', NOW(), ADDTIME(NOW(), "00:05:00.00") FROM ' . $table_log . ' WHERE (SELECT count(1) FROM ' . $table_lock . ') = 0 LIMIT 1');
                    
        return $query !== false && $query == 1;
    }
    
    public function acquire_import_lock() {
    	$lock_entries_num = $this->get_import_lock_entries_number();
        if ($lock_entries_num == 0) {
            $this->cleanup_import_lock();
            if ($this->add_import_lock($this->import_id)) {
                return true;
            }
        } else if ($lock_entries_num == 1) {
        	return $this->check_import_lock($this->import_id);
        }
        
        return false;
    }
    
    public function check_import_lock() {
    	$table_name = "{$this->dbl->prefix}arlo_import_lock";
        
        $sql = '
            SELECT 
                lock_acquired
            FROM
                ' . $table_name . '
            WHERE
                import_id = ' . $this->import_id . '
            AND    
                lock_expired > NOW()';
               
        $this->dbl->get_results($sql);
        
        if ($this->dbl->num_rows == 1) {
            return true;
        }
    
        return false;
    }	

	private function run_import_task($import_task) {
		$this->get_data_json(); 
		
		if (!empty(self::$data_json->$import_task) || in_array($import_task, $this->irregular_tasks)) {
			$this->environment->start_time = time(); // Set start time of current process.
			
			Logger::log('Import subtask started: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc, $this->import_id);

			$class_name = "Arlo\Importer\\" . $import_task;
			
			$this->current_task_class = new $class_name($this, $this->dbl, $this->message_handler, (!empty(self::$data_json->$import_task) ? self::$data_json->$import_task : null), $this->current_task_iterator);
			$this->current_task_class->import();
			
			Logger::log('Import subtask ended: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc, $this->import_id);			
		}
	}
}