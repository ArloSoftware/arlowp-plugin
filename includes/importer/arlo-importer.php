<?php

namespace Arlo\Importer;

use Arlo\Singleton;
use Arlo\Logger;
use Arlo\Utilities;
use Arlo\FileHandler;

class Importer extends Singleton {
	
	public static $is_finished = false;

	protected static $plugin;
	protected static $data_json;

	public static $filename;
	protected static $dir;			

	public $import_id;
	public $nonce;
	
	private $current_import_id;
	private $last_import_date;

	private $environment;
	private $message_handler;
	private $dbl;
	private $api_client;
	private $scheduler;
	private $file_handler;

	
	//the keys in this array have to match with the keys in the JSON 
	//except irregular_tasks
	public $import_tasks = [
				'ImportRequest' => "Request a database snapshot",
				'Download' => "Download file",
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
				'ImportRequest',
				'Download',
				'CategoryDepth',
				'Finish',
			];

	public function __construct($environment, $dbl, $message_handler, $api_client, $scheduler) {
		self::$dir = trailingslashit(plugin_dir_path( __FILE__ )).'../../import/';

		$this->environment = $environment;
		$this->dbl = $dbl;
		$this->message_handler = $message_handler;
		$this->api_client = $api_client;
		$this->scheduler = $scheduler;
	}

	public function generate_import_id() {
		return Utilities::get_random_int();
	}

	public function set_import_id($import_id) {
		$this->import_id = $import_id;

		//pretty weird place for it, but the file name depends on the import_id
		$this->file_handler = new FileHandler(self::$dir, $import_id, $import_id);
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
		$filename = self::$dir . $this->import_id . '.dec.json';
	
		if (is_null(self::$data_json)) {
			self::$data_json = $this->file_handler->read_file_as_json($filename)->FullImage;
		}
	}

	public function run($force = false, $task_id = 0) {
		$task_id = intval($task_id);
		$retval = true;

		$this->set_import_id(Utilities::get_random_int());

		if ($task_id > 0) {
			$task = $this->scheduler->get_task_data($task_id);
			if (count($task)) {
				$task = $task[0];
			};

			if (empty($task->task_data_text) && $this->should_importer_run($force)) {
				Logger::log('Synchronization Started', $this->import_id);
		        $this->scheduler->update_task_data($task_id, ['import_id' => $this->import_id]);
			} else {
				$task->task_data_text = json_decode($task->task_data_text);
				if (empty($task->task_data_text->import_id)) {
					return false;
				} else {
					$this->set_import_id($task->task_data_text->import_id);
				}
			}
		}

		//if an import is already running, exit
        if ($this->acquire_import_lock()) {
			try {
				$this->set_state($task->task_data_text);

				if (!self::$is_finished) {
					$this->scheduler->update_task($task_id, 2, "Import is running: task " . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc);

					if (!self::$is_finished && isset($this->import_tasks[$this->current_task])) {
						$this->run_import_task($this->current_task);
					}

					$this->scheduler->update_task_data($task_id, $this->get_state());
					
					$this->scheduler->update_task($task_id, 1);
					$this->scheduler->unlock_process('import');
				}

				if (self::$is_finished) {
					//finish task
					$this->scheduler->update_task($task_id, 4, "Import finished");
					$this->scheduler->clear_cron();

					$this->file_handler->delete_file(self::$dir . $this->import_id . '.dec.json');

				} else if ($this->current_task_num > 0) {
					$this->scheduler->kick_off_scheduler();
				}
			} catch(\Exception $e) {
				Logger::log('Synchronization failed, please check the <a href="?page=arlo-for-wordpress-logs&s='.$this->import_id.'">Log</a> ', $this->import_id);
				$this->scheduler->update_task($task_id, 3);
				
				$retval = false;
			}
		} else {
            Logger::log('Synchronization LOCK found, please wait 5 minutes and try again', $this->import_id);
            $retval = false;
        }

		$this->clear_import_lock();

		return $retval;
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
		if ($this->current_task_num > 1) {
			$this->get_data_json();
		}
		
		if (!empty(self::$data_json->$import_task) || in_array($import_task, $this->irregular_tasks)) {
			$this->environment->start_time = time(); // Set start time of current process.
			
			Logger::log('Import subtask started: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc, $this->import_id);

			$class_name = "Arlo\Importer\\" . $import_task;
			
			$this->current_task_class = new $class_name($this, $this->dbl, $this->message_handler, (!empty(self::$data_json->$import_task) ? self::$data_json->$import_task : null), $this->current_task_iterator, $this->api_client, $this->file_handler);
			$this->current_task_class->run();
			
			Logger::log('Import subtask ended: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc, $this->import_id);			
		} else {
			Logger::log_error('Error with the subtask', $this->import_id);
		}
	}

	private function validate_import_entry($nonce = null, $request_id = null) {
		$import = $this->get_import_entry(null, $request_id, 1);
		
		if (!is_null($import)) {
			if ($nonce == $import->nonce) {
				return $import;
			}
		}

		return false;
	}

	public function callback() {
		$callback_json = json_decode(file_get_contents("php://input"));

		if (!empty($callback_json->Nonce) && ($import = $this->validate_import_entry($callback_json->Nonce, $callback_json->RequestID)) !== false) {
			$this->set_import_id($import->import_id);
			$this->update_import_entry(['callback_json' => json_encode($callback_json) ]);
			$this->scheduler->kick_off_scheduler();
		} 
	}

	public function get_import_entry($import_id = null, $request_id = null, $limit = null) {
		$utc_date = gmdate("Y-m-d H:i:s"); 

		$import_id = (!empty($import_id) && is_numeric($import_id) ? $import_id : null);
		$nonce = (!empty($nonce) ? $nonce : null);
		$limit = (!empty($limit) && is_numeric($limit) ? $limit : null);

		$table_name = $this->dbl->prefix . "arlo_import";

		$sql = '
		SELECT
			import_id,
			request_id,
			nonce,
			callback_json,
			response_json,
			created,
			modified,
			expired 
		FROM 
			' . $table_name . '
		WHERE
			1
			' . (!is_null($import_id) ? ' AND import_id = ' . $import_id : '' ) . '
			' . (!is_null($request_id) ? ' AND request_id = "' . $request_id . '"' : '' ) . '
		AND
			expired >= "' . $utc_date . '"
		' . (!is_null($limit) ? ' LIMIT ' . $limit : '' ) . '
		';

		if (is_null($import = $this->dbl->get_results($sql))) {
			Logger::log_error('Couldn\'t find valid import');
		} else if ($limit == 1) {
			$import = $import[0];
		}
		
		return $import;
	}

	public function update_import_entry($data = array()) {
		$utc_date = gmdate("Y-m-d H:i:s"); 
		$table_name = $this->dbl->prefix . "arlo_import";

		$available_fields_for_update = [
			'request_id',
			'callback_json',
			'response_json'
		];

		$update_fields = [];

		foreach ($available_fields_for_update as $field) {
			if (!empty($data[$field])) {
				$update_fields[] = $field . '="' . $this->dbl->_real_escape($data[$field]) . '"';
			}
		}

		$sql = '
		UPDATE 
			' . $table_name . '
		SET
			' . (count($update_fields) ? implode(', ', $update_fields) . ', ' : '' )  . '
			modified = "' . $utc_date . '"
		WHERE 
			import_id = ' . $this->import_id . '
		';

		if ($this->dbl->query($sql) === false) {
			Logger::log_error('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query, $this->import_id);
		}
	}

	public function set_import_entry($nonce = null) {
		$utc_date = gmdate("Y-m-d H:i:s");
		$utc_plusonehour =  gmdate("Y-m-d H:i:s", time() + (60 * 60));
		$table_name = $this->dbl->prefix . "arlo_import";

		$sql = '
		INSERT INTO
			' . $table_name . ' 
			(import_id, nonce, created, expired)
		VALUES
			(%s, %s, %s, %s)
		';
		
		$query = $this->dbl->query($this->dbl->prepare($sql, $this->import_id, $nonce, $utc_date, $utc_plusonehour));
		
		if ($query) {
			return $this->dbl->insert_id;
		} else {
			return false;
		}
	} 
}