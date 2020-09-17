<?php

namespace Arlo\Importer;

use Arlo\Logger;
use Arlo\Utilities;
use Arlo\Crypto;

class Importer {
	const MAX_RETRY_ATTEMPT = 5;

	protected $data_json;			
	
	private $environment;
	private $message_handler;
	private $dbl;
	private $api_client;
	private $scheduler;
	private $importing_parts;

	private $current_import_id;
	private $last_import_date;

	private $current_task_iteration = 0;
	private $state;		
	private $task_id;

	//the keys in this array have to match with the keys in the JSON 
	//except irregular_tasks
	public $import_tasks = [
				'ImportRequest' => "Request a database snapshot",
				'Download' => "Download snapshot file",
				'ProcessFragment' => "Process fragments",
				'CategoryDepth' => 'Updating category depth',
				'Finish' => 'Finalize the import',
			];	

	public $current_task;
	public $current_task_class;
	public $current_task_num;
	public $current_task_desc = '';
	public $current_task_retry = 0;

	public $fragment_size;

	public $import_id;
	public $nonce;
	public $is_finished = false;

	public function __construct($environment, $dbl, $message_handler, $api_client, $scheduler, $importing_parts) {
		$this->environment = $environment;
		$this->dbl = $dbl;
		$this->message_handler = $message_handler;
		$this->api_client = $api_client;
		$this->scheduler = $scheduler;
		$this->importing_parts = $importing_parts;
	}

	public function generate_import_id() {
		return \Arlo\Utilities::get_random_int();
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
		$now = \Arlo\Utilities::get_now_utc();
       	$timestamp = $now->format("Y-m-d H:i:s");	
	
		update_option('arlo_last_import', $timestamp);
		$this->last_import_date = $timestamp;
	}	

	public function set_tax_exempt_events($import_id) {
		$settings = get_option('arlo_settings');

		if (!empty($settings['taxexempt_tag'])) {
			$sql = $this->dbl->prepare("
			UPDATE 
				{$this->dbl->prefix}arlo_events AS e, 
				{$this->dbl->prefix}arlo_events_tags AS et, 
				{$this->dbl->prefix}arlo_tags AS t 
			SET 
				e_is_taxexempt = 1
			WHERE 
				t.tag = %s
			AND 
				t.id = et.tag_id
			AND
				et.e_id = e.e_id
			AND 
				et.import_id = %d
			AND 
				t.import_id = %d
			AND
				e.import_id = %d
			", [trim($settings['taxexempt_tag']), $import_id, $import_id, $import_id]);			
		} else {
			$sql = $this->dbl->prepare("
			UPDATE 
				{$this->dbl->prefix}arlo_events AS e
			SET 
				e_is_taxexempt = 0
			WHERE 
				e.import_id = %d
			", [$import_id]);
		}
		
		$query = $this->dbl->query($sql);

		if ($query === false) {					
			throw new \Exception('SQL error at set_tax_exempt_events: ' . $this->dbl->last_error);
		}	
	}

	public function get_last_import_date() {
		if(!is_null($this->last_import_date)) {
			return $this->last_import_date;
		}
		
		$this->last_import_date = get_option('arlo_last_import');
		
		return $this->last_import_date;
	}	

	public function set_state($state) {
		$this->state = $state;		
		$task_keys = array_keys($this->import_tasks);

		if (!empty($state)) {
			if (!empty($state->current_subtask)) {
				$this->set_current_task($state->current_subtask);
				$this->current_task_iteration = (isset($state->iteration) && is_numeric($state->iteration) ? ((isset($state->subtask_state) && $state->subtask_state->iteration_finished == 1) || !isset($state->subtask_state) ? $state->iteration + 1 : $state->iteration )  : 0);
			} else if (!empty($state->finished_subtask)) {
				//figure out the next task;
				$k = array_search($state->finished_subtask, $task_keys);
				if ($k !== false && isset($task_keys[++$k])) {
					$this->set_current_task($task_keys[$k]);	
					$state->current_subtask_retry = 0;		
				} else {
					$this->is_finished = true;
				}
			} else {
				$this->set_current_task($task_keys[0]);	
			}
		} else {
			$this->set_current_task($task_keys[0]);
		}

		$this->current_task_retry = (isset($state->current_subtask_retry) ? $state->current_subtask_retry + 1 : $this->current_task_retry + 1);
		$this->scheduler->update_task_data($this->task_id, $this->get_state());
	}

	public function get_state() {
		$state = [
			'finished_subtask' => null,
			'current_subtask' => null,
			'current_subtask_retry' => $this->current_task_retry,
			'iteration' => null,
			'subtask_state' =>  null,
		];

		if (!is_null($this->current_task_class)) {
			$state['subtask_state'] = $this->current_task_class->get_state();

			if ($this->current_task_class->is_finished) {
				$state['finished_subtask'] = $this->current_task;
			} else {
				$state['current_subtask'] = $this->current_task;
				$state['iteration'] = $this->current_task_class->iteration;
			}
		} else {
			$state['current_subtask'] = $this->current_task;
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
		$item = $this->importing_parts->get_import_part("image", null, $this->import_id);

		if (empty($item)) {
			throw new \Exception("Import Error: the import \"image\" part cannot be found");
		}
		if (empty($item->import_text)) {
			throw new \Exception("Import Error: the content of the import part is empty");
		}

		$this->data_json = json_decode($item->import_text);

		if (is_null($this->data_json)) {
			throw new \Exception("JSON Error: " . json_last_error_msg());
		}
	}

	public function run($force = false, $task_id = 0) {
		$this->task_id = intval($task_id);
		$retval = true;

		$this->set_import_id(\Arlo\Utilities::get_random_int());

		if ($this->task_id > 0) {
			$task = $this->scheduler->get_task_data($this->task_id);
			if (count($task)) {
				$task = $task[0];
			};

			if (empty($task->task_data_text) && $this->should_importer_run($force)) {
				Logger::log('Synchronization Started', $this->import_id);
		        $this->scheduler->update_task_data($this->task_id, ['import_id' => $this->import_id]);
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

			set_error_handler ( function($num, $str, $file, $line, $context = null) {
				error_log($str . ' in ' . $file . ' on line ' . $line);

				//pretty nasty, but need to know if our plugin throws the error or something else (like a cache plugin)
				//arlo- is in case $file would not include the path; just 'arlo' would catch all errors for hosted servers like vanguard.wpdemo.arlo.co where domain is used in the plugin file path
				if (strpos($file, 'arlo-') !== false || strpos($file, 'arlowp')) {

					// specific error for file permission
					if (strpos($str, 'fopen(') === 0) {
						if (strpos($str, 'ermission denied') > 0) {
							Logger::log("Missing write permission" . (strpos($str, "/import/") > 0 ? " on 'import' directory" : ""), $this->import_id);
						}
					}

					throw new \Exception($str);
				}
			}, E_ALL & ~E_USER_NOTICE & ~E_NOTICE  & ~E_DEPRECATED);
			
			try {
				$this->set_state($task->task_data_text);

				if (!$this->is_finished) {

					if (!$this->is_finished && isset($this->import_tasks[$this->current_task])) {
						$this->run_import_task($this->current_task);
					}

					//means that wasn't any error/warning during the task
					$this->current_task_retry--;
					$this->scheduler->update_task_data($this->task_id, $this->get_state());
					$this->scheduler->update_task($this->task_id, 1);
				}

				if ($this->is_finished) {
					//finish task
					$this->scheduler->update_task($this->task_id, 4, "Import finished");
					$this->scheduler->clear_cron();

					$this->importing_parts->delete_all_import_parts();
				} else if ($this->current_task_num > 0) {
					$this->kick_off_scheduler();
				}
			} catch(\Exception $e) {
				if ($this->should_retry($this->get_state()) && !($e instanceof \Arlo\SchedulerException)) {
					//pause the task 
					$this->scheduler->update_task($this->task_id, 1);
					$this->kick_off_scheduler();
				} else {
					Logger::log($e->getMessage(), $this->import_id);
					Logger::log('Synchronization failed, please check the <a href="?page=arlo-for-wordpress-logs&s='.$this->import_id.'">Log</a> ', $this->import_id);
					//cancel the task
					$this->scheduler->update_task($this->task_id, 3);
					$retval = false;
				}
			}

			restore_error_handler();
		} else {
			$retval = false;
			/**
			 * For some reason there have been multiple cases of our import lock table going missing. 
			 * To resolve this, we will check for the table missing error and trigger DB rebuild.
			 * It is likely another plugin, but its not unwise to simply handle it and move on.
			 */
			if ($this->check_import_lock_error()){
				Logger::log("Synchronization LOCK table missing, triggering db schema check", $this->import_id);
				\Arlo_For_Wordpress::get_instance()->check_db_schema();
			} else {
				Logger::log('Synchronization LOCK found, please wait 5 minutes and try again', $this->import_id);
			}
        }

		$this->clear_import_lock();
		$this->scheduler->unlock_process('import');

		return $retval;
	}

	private function update_task_data_for_retry($state) {
		$data = ['current_subtask_retry' => $state['current_subtask_retry']];
		if (!is_null($state['subtask_state'])) {
			$data['subtask_state']['current_subtask_retry'] = $state['subtask_state']['current_subtask_retry'];
		}

		$this->scheduler->update_task_data($this->task_id, $data);
	}

	private function should_retry($state) {
		if ((is_null($state['subtask_state']) && $state['current_subtask_retry'] >= self::MAX_RETRY_ATTEMPT) || (!is_null($state['subtask_state']) && $state['subtask_state']['current_subtask_retry'] >= self::MAX_RETRY_ATTEMPT)) {
			$subtask_desc = $this->get_subtask_state_desc();
			Logger::log("Maximum retry attempt reached for '" . $this->current_task_desc . $subtask_desc . "'", $this->import_id);
			return false;
		}

		return true; 
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
	
	private function check_import_lock_error(){
		$tableMissingErr = "Table '{$this->dbl->wpdb->dbname}.{$this->dbl->prefix}arlo_import_lock' doesn't exist";
		if ($this->dbl->last_error == $tableMissingErr){
			return true;
		} else { return false; }
	}

	private function run_import_task($import_task) {
		$this->data_json = null;
		if ($this->current_task_num == 2) {
			$this->get_data_json();
		}
		
		$this->environment->start_time = time(); // Set start time of current process.
		
		$class_name = "Arlo\Importer\\" . $import_task;

		$this->current_task_class = new $class_name($this, $this->dbl, $this->message_handler, (!empty($this->data_json->$import_task) ? $this->data_json->$import_task : null), $this->current_task_iteration, $this->api_client, $this->scheduler, $this->importing_parts);
		$this->current_task_class->task_id = $this->task_id;

		//we need to do some special setup for different tasks
		switch($this->current_task) {
			case 'ImportRequest':
				$this->current_task_class->fragment_size = $this->fragment_size;
				break;
			case 'Download':
				$import = $this->get_import_entry($this->import_id, null, 1);

				if (!is_null($import)) {
					if (!empty($import->callback_json)) {
						$callback_json = json_decode($import->callback_json);

						if (json_last_error() != JSON_ERROR_NONE) {
							error_log("JSON Decode error: " . json_last_error_msg());
							Logger::log_error("JSON Decode error: " . json_last_error_msg(), $this->import_id);
						}

						if (!empty($callback_json->SnapshotUri)) {
							$this->current_task_class->uri = $callback_json->SnapshotUri;
							$this->current_task_class->import_part = "image";
							$this->current_task_class->import_iteration = null;
							$this->current_task_class->response_json = json_decode($import->response_json);
						} elseif (!empty($callback_json->Error)) {
							Logger::log_error($callback_json->Error->Code . ': ' . $callback_json->Error->Message, $this->import_id);
						}
					} else {
						Logger::log_error('The import callback did not happen', $this->import_id);
					}
				} else {
					Logger::log_error('Couldn\'t retrive the import from database', $this->import_id);
				}

			break;
			case 'ProcessFragment':
				$this->current_task_class->set_state($this->state->subtask_state);

				if (!empty($this->data_json->FullImageFragments->Elements[$this->current_task_iteration])) {
					$this->current_task_desc .= ' ' . ($this->current_task_iteration+1) . '/' . count($this->data_json->FullImageFragments->Elements);
					$this->current_task_class->uri = $this->data_json->FullImageFragments->Elements[$this->current_task_iteration]->Uri;
				} else {
					$this->current_task_class->is_finished = true;
				}
			break;			
		}

		if (!$this->current_task_class->is_finished && !$this->current_task_class->iteration_finished) {
			$subtask_desc = $this->get_subtask_state_desc();
			$this->scheduler->update_task($this->task_id, 2, "Import is running: task " . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc . $subtask_desc);

			Logger::log('Import subtask started: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc . $subtask_desc, $this->import_id);		
			$this->current_task_class->run();
			Logger::log('Import subtask ended: ' . ($this->current_task_num + 1) . "/" . count($this->import_tasks) . ": " . $this->current_task_desc . $subtask_desc, $this->import_id);
		}
	}

	private function get_subtask_state_desc() {
		$subtask_desc = '';
		if (!is_null($this->current_task_class)) {
			$subtask_state = $this->current_task_class->get_state();
			
			if (!is_null($subtask_state)) {
				$subtask_desc = ': ' . ($subtask_state['current_subtask_num'] + 1) . '/' . count($this->current_task_class->import_tasks) . ' ' . $subtask_state['current_subtask_desc'];
			}
		}

		return $subtask_desc;		
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

	private function kick_off_scheduler() {
		$this->scheduler->unlock_process('import');
		$this->scheduler->kick_off_scheduler();
	}

	public function callback() {
		try {
			$callback_json = json_decode(utf8_encode(file_get_contents("php://input")));

			if (!is_null($callback_json) && !empty($callback_json->Nonce) && 
				($import = $this->validate_import_entry($callback_json->Nonce, $callback_json->RequestID)) !== false && !empty($callback_json->__jwe__)) {

				$this->set_import_id($import->import_id);
				$this->update_import_entry(['callback_json' => json_encode($callback_json) ]);
				$response_json = json_decode($import->response_json);

				//JWE decode
				$decoded = preg_replace('/[\x00-\x1F\x7F]/', '', utf8_decode(Crypto::jwe_decrypt($callback_json->__jwe__, $response_json->Callback->EncryptedResponse->key->k)));
				$decoded_json = json_decode($decoded);
				if (!empty($decoded_json->SnapshotUri)) {
					$this->update_import_entry(['callback_json' => $decoded]);
					$this->kick_off_scheduler();
				} else {
					if (!empty($decoded_json->Error)) {
						throw new \Exception($decoded_json->Error->Code . ': ' . $decoded_json->Error->Message);
					} else {
						throw new \Exception('Error in the response for the snapshot request');
					}
				}	
			} else {
				throw new \Exception('no Nonce or the requested import is not valid');
			}
		} catch(\Exception $e) {
			Logger::log($e->getMessagE(), (!empty($import->import_id)) ? $import->import_id : null);
			Logger::log('Synchronization failed, please check the <a href="?page=arlo-for-wordpress-logs&s='.$this->import_id.'">Log</a> ', $this->import_id);

			if (!empty($import->import_id)) {
				$task = $this->scheduler->get_tasks([1,2], null, null, 1, $import->import_id);

				if (!empty($task[0]->task_id)) {
					$this->scheduler->update_task($task[0]->task_id, 3);
				}
			}
		}
	}

	public function get_import_entry($import_id = null, $request_id = null, $limit = null) {
		$utc_date = gmdate("Y-m-d H:i:s"); 

		$import_id = (!empty($import_id) && is_numeric($import_id) ? $import_id : null);
		$limit = (!empty($limit) && is_numeric($limit) ? $limit : null);
		$request_id = (!empty($request_id) ? $request_id : null);

		if (is_null($request_id) && is_null($import_id)) 
			return null; 

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
			' . (!is_null($request_id) ? ' AND request_id = "' . esc_sql($request_id) . '"' : '' ) . '
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
			throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
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