<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;
use ArloTraining\Utilities;

class Importer {
	const MAX_RETRY_ATTEMPT = 5;
	const IMPORT_TIMEOUT_SECONDS = 90;
	const IMPORT_INTERVAL_MIN_SECONDS = 600; // 10 minutes
	const IMPORT_FAILURE_MAX_COUNT = 10;
	const IMPORT_FAILURE_MIN_DURATION_HOURS = 3;

	protected $data_json;			
	
	private $environment;
	private $message_handler;
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

	public function __construct($environment, $message_handler, $api_client, $scheduler, $importing_parts) {
		$this->environment = $environment;
		$this->message_handler = $message_handler;
		$this->api_client = $api_client;
		$this->scheduler = $scheduler;
		$this->importing_parts = $importing_parts;
	}

	public function generate_import_id() {
		return \ArloTraining\Utilities::get_random_int();
	}

	public function set_import_id($import_id) {
		$this->import_id = $import_id;
	}

	public function set_current_import_id($import_id) {
		update_option('arlo_import_id', $import_id);
                               
		$this->current_import_id = $import_id;		
	}

	public function get_current_import_id() {
		if ( $this->current_import_id !== null ) {
			return $this->current_import_id;
		}

		global $wpdb;
		// Bypass WP's options cache (get_option may return a stale value during import).
		// Result is cached in $this->current_import_id for the remainder of this request.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query bypasses WP options cache; per-request caching is handled by the property.
		$this->current_import_id = $wpdb->get_var("SELECT option_value
			FROM {$wpdb->prefix}options
			WHERE option_name = 'arlo_import_id'");

		return $this->current_import_id;
	}

	public function set_last_import_date() {
		$now = \ArloTraining\Utilities::get_now_utc();
       	$timestamp = $now->format("Y-m-d H:i:s");	
	
		update_option('arlo_last_import', $timestamp);
		$this->last_import_date = $timestamp;
	}	

	public function set_tax_exempt_events($import_id) {
		global $wpdb;
		$settings = get_option('arlo_settings');

		if (!empty($settings['taxexempt_tag'])) {
			$sql = $wpdb->prepare("
			UPDATE 
				{$wpdb->prefix}arlo_events AS e, 
				{$wpdb->prefix}arlo_events_tags AS et, 
				{$wpdb->prefix}arlo_tags AS t 
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
			$sql = $wpdb->prepare("
			UPDATE 
				{$wpdb->prefix}arlo_events AS e
			SET 
				e_is_taxexempt = 0
			WHERE 
				e.import_id = %d
			", [$import_id]);
		}
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared --  Direct database query is required for custom table. Do not need cache for import process. SQL is prepared above
		$query = $wpdb->query($sql);

		if ($query === false) {					
			throw new \Exception('SQL error at set_tax_exempt_events: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
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
		if (!$force) {
			Logger::log('Synchronization identified as automatic synchronization.', $this->import_id);

			$elapsed = $this->get_seconds_since_last_import();
			if ($elapsed !== false && $elapsed < self::IMPORT_INTERVAL_MIN_SECONDS) {
				Logger::log('Last synchronization is less than ' . self::IMPORT_INTERVAL_MIN_SECONDS . ' seconds old. Synchronization stopped.', $this->import_id);
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns the number of seconds since the last successful import,
	 * or false if no valid timestamp is available.
	 * Negative values (future timestamps from corrupt data) are treated as zero.
	 *
	 * @return int|false
	 */
	private function get_seconds_since_last_import() {
		$last_import_date = $this->get_last_import_date();
		if (empty($last_import_date)) {
			return false;
		}

		try {
			$last_utc_ts = (new \DateTime($last_import_date, new \DateTimeZone('UTC')))->getTimestamp();
		} catch (\Exception $e) {
			return false;
		}

		return max(0, time() - $last_utc_ts);
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
			throw new \Exception("JSON Error: " . json_last_error_msg()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	/**
	 * Resolves the task state for an import run.
	 *
	 * For a resumed import, returns the decoded task state object.
	 * For a new import, initialises the task and returns null.
	 * Returns false if the run should be aborted (throttled or invalid state).
	 *
	 * @param bool $force Whether to bypass the import interval throttle.
	 * @return object|null|false
	 */
	private function resolve_task_state($force) {
		$rows = $this->scheduler->get_task_data($this->task_id);
		if (empty($rows)) {
			return false;
		}
		$task = $rows[0];

		// Resuming an in-progress import
		if (!empty($task->task_data_text)) {
			$state = json_decode($task->task_data_text);
			if (!is_object($state) || empty($state->import_id)) {
				return false;
			}
			$this->set_import_id($state->import_id);
			return $state;
		}

		// Starting a new import — check throttle
		if (!$this->should_importer_run($force)) {
			return false;
		}

		Logger::log('Synchronization Started', $this->import_id);
		$this->scheduler->update_task_data($this->task_id, ['import_id' => $this->import_id]);
		return null;
	}

	public function run($force = false, $task_id = 0) {
		$retval = false;
		$original_time_limit = $this->environment->arlo_set_time_limit(self::IMPORT_TIMEOUT_SECONDS);
		try {
			$retval = $this->execute_import($force, $task_id);
		} catch(\Exception $e) {
			Logger::log($e->getMessage());
		} finally {
			$this->environment->arlo_set_time_limit($original_time_limit);
		}

		return $retval;
	}

	private function execute_import($force, $task_id) {
		$this->task_id = intval($task_id);

		$this->set_import_id(\ArloTraining\Utilities::get_random_int());

		if ($this->task_id > 0) {
			$task_state = $this->resolve_task_state($force);
			if ($task_state === false) {
				return false;
			}
		}

		if (!$this->acquire_import_lock()) {
			return $this->handle_lock_failure();
		}

		try {
			return $this->run_locked_import(isset($task_state) ? $task_state : null, $force);
		} finally {
			$this->clear_import_lock();
			$this->scheduler->unlock_process('import');
		}
	}

	private function handle_lock_failure() {
		/**
		 * For some reason there have been multiple cases of our import lock table going missing.
		 * To resolve this, we will check for the table missing error and trigger DB rebuild.
		 * It is likely another plugin, but its not unwise to simply handle it and move on.
		 */
		if ($this->check_import_lock_error()) {
			Logger::log("Synchronization LOCK table missing, triggering db schema check", $this->import_id);
			\Arlo_For_Wordpress::get_instance()->ensure_consistent_db_schema();
		} else {
			Logger::log('Synchronization LOCK found, please wait 5 minutes and try again', $this->import_id);
		}

		return false;
	}

	/**
	 * Custom PHP error handler used during imports. Converts plugin-originating
	 * PHP errors into exceptions so they can be caught and logged by the import pipeline.
	 *
	 * @param int         $num     Error level.
	 * @param string      $str     Error message.
	 * @param string      $file    File where the error occurred.
	 * @param int         $line    Line number.
	 * @param mixed|null  $context Error context (deprecated in PHP 8).
	 */
	public function handle_import_error($num, $str, $file, $line, $context = null) {
		error_log($str . ' in ' . $file . ' on line ' . $line); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging errors for debugging purposes.

		//pretty nasty, but need to know if our plugin throws the error or something else (like a cache plugin)
		//arlo- is in case $file would not include the path; just 'arlo' would catch all errors for hosted servers like vanguard.wpdemo.arlo.co where domain is used in the plugin file path
		if (strpos($file, 'arlo-') !== false || strpos($file, 'arlowp') !== false) {

			// specific error for file permission
			if (strpos($str, 'fopen(') === 0) {
				if (strpos($str, 'ermission denied') > 0) {
					Logger::log("Missing write permission" . (strpos($str, "/import/") > 0 ? " on 'import' directory" : ""), $this->import_id);
				}
			}

			throw new \Exception($str); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	private function run_locked_import($task_state, $force = false) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Logging errors for debugging purposes.
		set_error_handler([$this, 'handle_import_error'], E_ALL & ~E_USER_NOTICE & ~E_NOTICE  & ~E_DEPRECATED);

		try {
			$this->set_state($task_state);

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

			return true;
		} catch(\Exception $e) {
			if ($this->should_retry($this->get_state())
				&& !($e instanceof \ArloTraining\SchedulerException)
				&& !($e instanceof \ArloTraining\PlatformAccessHttpException)
				&& !($e instanceof \ArloTraining\SnapshotProcessException)) {
				//pause the task
				$this->scheduler->update_task($this->task_id, 1);
				$this->kick_off_scheduler();
				// Non-platform failure in retry path: platform was reachable, reset health counter
				update_option('arlo_platform_access_failure_count', 0);
				delete_option('arlo_platform_access_first_failure_at');
				return true;
			} else {
				Logger::log($e->getMessage(), $this->import_id);
				Logger::log('Synchronization failed', $this->import_id);
				//cancel the task
				$this->scheduler->update_task($this->task_id, 3);
				if ($e instanceof \ArloTraining\PlatformAccessHttpException) {
					if (!$force) {
						// Scheduled sync platform failure: count toward auto-disable threshold.
						$this->handle_platform_access_failure($e);
					}
					// Forced sync platform failure: leave health counters intact so accumulated
					// failure state from scheduled syncs is not erased by an admin retry.
				} else {
					// Non-platform failure: platform was reachable, reset health counter.
					update_option('arlo_platform_access_failure_count', 0);
					delete_option('arlo_platform_access_first_failure_at');
				}
				return false;
			}
		} finally {
			restore_error_handler();
		}
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
        global $wpdb;
      
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for import process.
        $query = $wpdb->query("DELETE FROM {$wpdb->prefix}arlo_import_lock");
    }     
    
    public function get_import_lock_entries_number() {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process.
        $wpdb->get_results("
            SELECT 
                lock_acquired
            FROM
                {$wpdb->prefix}arlo_import_lock
            WHERE
                lock_expired > NOW()
            ");
        
        return $wpdb->num_rows;
    }
    
    private function cleanup_import_lock() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database query is required. Do not need to cache the result.
        $wpdb->query("DELETE FROM {$wpdb->prefix}arlo_import_lock WHERE lock_expired < NOW()");
    }
    
    private function add_import_lock() {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for import process.
        $query = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}arlo_import_lock (import_id, lock_acquired, lock_expired)
                SELECT %d, NOW(), ADDTIME(NOW(), '00:05:00.00') FROM {$wpdb->prefix}arlo_log WHERE (SELECT count(1) FROM {$wpdb->prefix}arlo_import_lock) = 0 LIMIT 1",
                $this->import_id
            )
        );
                    
        return $query !== false && $query == 1;
    }
    
    public function acquire_import_lock() {
    	$lock_entries_num = $this->get_import_lock_entries_number();
        if ($lock_entries_num == 0) {
            $this->cleanup_import_lock();
            if ($this->add_import_lock()) {
                return true;
            }
        } else if ($lock_entries_num == 1) {
        	return $this->check_import_lock();
        }
        
        return false;
    }
    
    public function check_import_lock() {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database query is required. Do not need cache for import process.
        $wpdb->get_results($wpdb->prepare("
            SELECT 
                lock_acquired
            FROM
                {$wpdb->prefix}arlo_import_lock
            WHERE
                import_id = %d
            AND    
                lock_expired > NOW()", $this->import_id));
        
        if ($wpdb->num_rows == 1) {
            return true;
        }
    
        return false;
	}	
	
	/**
	 * Record a platform access failure and disable imports if both sustained-failure
	 * thresholds are exceeded.
	 *
	 * Increments the failure counter and, once IMPORT_FAILURE_MAX_COUNT failures have
	 * occurred within a window of at least IMPORT_FAILURE_MIN_DURATION_HOURS, sets
	 * arlo_import_connection_health_disabled and stores a normalised human-readable
	 * reason in arlo_import_disabled_message. Is a no-op when
	 * import_connection_healthchecks_enabled is off in settings.
	 *
	 * Public to allow direct invocation in unit tests without reflection.
	 *
	 * @param \ArloTraining\PlatformAccessHttpException $e The caught exception whose
	 *        message is normalised and stored as the disable reason.
	 *
	 * @since 5.1.0
	 */
	public function handle_platform_access_failure(\ArloTraining\PlatformAccessHttpException $e) {
		$arlo_settings = get_option('arlo_settings', []);
		if (($arlo_settings['import_connection_healthchecks_enabled'] ?? '1') !== '1') return;

		$now = \ArloTraining\Utilities::get_now_utc();

		// Set first failure timestamp if not already set
		if (empty(get_option('arlo_platform_access_first_failure_at', ''))) {
			update_option('arlo_platform_access_first_failure_at', $now->format('Y-m-d H:i:s'));
		}

		// Increment failure count
		$count = intval(get_option('arlo_platform_access_failure_count', 0)) + 1;
		update_option('arlo_platform_access_failure_count', $count);

		// Check if both thresholds are exceeded
		if ($count >= self::IMPORT_FAILURE_MAX_COUNT) {
			$first_failure_at = get_option('arlo_platform_access_first_failure_at', '');
			if (!empty($first_failure_at)) {
				try {
					$first = new \DateTime($first_failure_at, new \DateTimeZone('UTC'));
				} catch (\Exception $date_ex) {
					// Stored timestamp is corrupt — reset counter and start a clean window from the current failure
					update_option('arlo_platform_access_failure_count', 1);
					update_option('arlo_platform_access_first_failure_at', $now->format('Y-m-d H:i:s'));
					return;
				}
				$hours_elapsed = ($now->getTimestamp() - $first->getTimestamp()) / 3600;

				if ($hours_elapsed >= self::IMPORT_FAILURE_MIN_DURATION_HOURS) {
					$platform_host = !empty($arlo_settings['platform_name']) ? $arlo_settings['platform_name'] . '.arlo.co' : 'the Arlo platform';

					$raw_message = $e->getMessage();

					// Strip a leading "hostname: " prefix if present (e.g. messages thrown by
					// Download::get_remote_data() are prefixed with the CDN hostname).
					// This lets the patterns below match regardless of where the exception originated.
					$normalised = preg_replace('/^[^\s:]+:\s+/', '', $raw_message);

					if (preg_match('/^.+ returned \d+ .+$/', $normalised)) {
						// HTTP error — hostname + status code already embedded; use as-is.
						$stored_message = $normalised;
					} elseif (preg_match('/^cURL error 28:/i', $normalised)) {
						// cURL timeout — omit the "after X milliseconds" suffix.
						$stored_message = $platform_host . ': Connection timed out';
					} elseif (preg_match('/^cURL error \d+: (.+)$/i', $normalised, $m)) {
						// Other cURL errors (SSL, DNS, etc.) — use the human-readable description.
						$stored_message = $platform_host . ': ' . $m[1];
					} else {
						// Use the normalised form to avoid double-prefixing if the message
						// originated from Download and carried a CDN hostname prefix.
						$stored_message = $platform_host . ': ' . $normalised;
					}

					update_option('arlo_import_connection_health_disabled', '1');
					update_option('arlo_import_disabled_message', sanitize_text_field($stored_message));
					update_option('arlo_import_disabled_since', $now->format('Y-m-d H:i:s'));
					update_option('arlo_platform_access_failure_count', 0);
					delete_option('arlo_platform_access_first_failure_at');

					$minutes_elapsed = max(1, (int) round($hours_elapsed * 60));
					Logger::log(
						'Automatic synchronization was disabled (' . $count . ' ' . ($count === 1 ? 'failure' : 'failures') . ' over ' . $minutes_elapsed . ' ' . ($minutes_elapsed === 1 ? 'minute' : 'minutes') . '). Reason: ' . $stored_message,
						$this->import_id
					);
				}
			}
		}
	}

	private function check_import_lock_error(){
        global $wpdb;
		$tableMissingErr = "Table '{$wpdb->dbname}.{$wpdb->prefix}arlo_import_lock' doesn't exist";
		if ($wpdb->last_error == $tableMissingErr){
			return true;
		} else { return false; }
	}

	private function run_import_task($import_task) {
		global $wpdb;
		$this->data_json = null;
		if ($this->current_task_num == 2) {
			$this->get_data_json();
		}
		
		$this->environment->start_time = time(); // Set start time of current process.
		
		$class_name = "ArloTraining\Importer\\" . $import_task;
		
		$this->current_task_class = new $class_name($this, $this->message_handler, (!empty($this->data_json->$import_task) ? $this->data_json->$import_task : null), $this->current_task_iteration, $this->api_client, $this->scheduler, $this->importing_parts);
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
							error_log("JSON Decode error: " . json_last_error_msg()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging errors for debugging purposes.
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

	/**
	 * Look up an import entry by request ID and verify the nonce matches.
	 *
	 * On a successful match the nonce is consumed (blanked in the DB) so the
	 * same callback cannot be replayed.
	 *
	 * @param string $nonce     The nonce from the callback POST body.
	 * @param string $request_id The RequestID from the callback POST body.
	 * @return object The matching import entry row.
	 * @throws \ArloTraining\ImportCallbackRejectedException If no matching entry is found,
	 *                                                        the nonce is empty/consumed, or
	 *                                                        the nonce does not match.
	 */
	protected function get_and_validate_import_entry(string $nonce, string $request_id) {
		$import = $this->get_import_entry(null, $request_id, 1);

		if (is_null($import)) {
			throw new \ArloTraining\ImportCallbackRejectedException('Import callback rejected: no valid import entry for request ID');
		}

		if (empty($nonce) || $nonce !== $import->nonce) {
			throw new \ArloTraining\ImportCallbackRejectedException('Import callback rejected: nonce mismatch or empty nonce');
		}

		if (!$this->consume_import_nonce($import->import_id, $nonce)) {
			throw new \ArloTraining\ImportCallbackRejectedException('Import callback rejected: nonce already consumed (possible replay)');
		}

		return $import;
	}

	public function kick_off_scheduler() {
		$this->scheduler->unlock_process('import');
		$this->scheduler->kick_off_scheduler();
	}

	public function callback() {
		$original_time_limit = $this->environment->arlo_set_time_limit(self::IMPORT_TIMEOUT_SECONDS);
		$import = null;
		try {
			$handler = new SnapshotHandler($this);

			// 1. Read the raw POST body and parse it
			$raw_body = file_get_contents('php://input');
			if ($raw_body === false) {
				throw new \Exception('Failed to read snapshot callback request body');
			}
			$snapshot_callback_response = $handler->parse_snapshot_callback_response($raw_body);

			// 2. Match the callback to its original import request via nonce and request ID
			$import = $this->get_and_validate_import_entry($snapshot_callback_response->Nonce, $snapshot_callback_response->RequestID);

			// 3. Initialise the import
			$this->set_import_id($import->import_id);
			// Update the saved import entry with the callback response data
			$this->update_import_entry(['callback_json' => wp_json_encode($snapshot_callback_response)]);

			// 4. Decrypt the JWE payload
			$key = $this->get_import_entry_encryption_key($import);
			$snapshot = $handler->decrypt($snapshot_callback_response->__jwe__, $key);

			// 5. Start the import process with the decrypted snapshot data
			$handler->process($snapshot);
		} catch(\ArloTraining\ImportCallbackRejectedException $e) {
			Logger::log($e->getMessage());
		} catch(\Exception $e) {
			$log_import_id = (!empty($import->import_id)) ? $import->import_id : null;
			Logger::log($e->getMessage(), $log_import_id);

			if (!empty($import->import_id)) {
				Logger::log('Synchronization failed', $import->import_id);

				$task = $this->scheduler->get_tasks([1,2], null, null, 1, $import->import_id);

				if (!empty($task[0]->task_id)) {
					$this->scheduler->update_task($task[0]->task_id, 3);
				}
			} else {
				Logger::log('Import callback failed');
			}
		} finally {
			$this->environment->arlo_set_time_limit($original_time_limit);
		}
	}

	protected function get_import_entry_encryption_key(object $import_entry): string {
		if (empty($import_entry->response_json)) {
			throw new \Exception('Encryption key missing from import entry response');
		}
		$original_response = json_decode($import_entry->response_json);
		if (!is_object($original_response) || !isset($original_response->Callback->EncryptedResponse->key->k)) {
			throw new \Exception('Encryption key missing from import entry response');
		}
		return (string) $original_response->Callback->EncryptedResponse->key->k;
	}

	public function get_import_entry($import_id = null, $request_id = null, $limit = null) {
		global $wpdb;
		$utc_date = gmdate("Y-m-d H:i:s"); 

		$import_id = (!empty($import_id) && is_numeric($import_id) ? $import_id : null);
		$limit = (!empty($limit) && is_numeric($limit) ? $limit : null);
		$request_id = (!empty($request_id) ? $request_id : null);

		if (is_null($request_id) && is_null($import_id)) 
			return null; 

		$sql = "
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
			{$wpdb->prefix}arlo_import
		WHERE
			1
			" . (!is_null($import_id) ? ' AND import_id = %d' : '' ) . "
			" . (!is_null($request_id) ? ' AND request_id = %s' : '' ) . "
		AND
			expired >= %s
		" . (!is_null($limit) ? ' LIMIT %d' : '' ) . "
		";

		$parameter = [];
		if(!is_null($import_id) ) {
			$parameter[] = $import_id;
		}
		if(!is_null($request_id) ) {
			$parameter[] = $request_id;
		}
		$parameter[] = $utc_date;
		if(!is_null($limit) ) {
			$parameter[] = $limit;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database query is required. The SQL statement is constructed from static strings and parameters are prepared here. Do not need cache for import process.
		if (is_null($import = $wpdb->get_results($wpdb->prepare($sql, $parameter)))) {
			Logger::log_error('Couldn\'t find valid import');
		} else if ($limit == 1) {
			$import = !empty($import[0]) ? $import[0] : null;
		}
		
		return $import;
	}

	public function update_import_entry($data = array()) {
		global $wpdb;
		$utc_date = gmdate("Y-m-d H:i:s"); 

		$available_fields_for_update = [
			'request_id',
			'callback_json',
			'response_json'
		];

		$update_fields = [];

		$parameter = [];
		
		foreach ($available_fields_for_update as $field) {
			if (!empty($data[$field])) {
				$update_fields[] = $field . '=%s';
				$parameter[] = $data[$field];
			}
		}

		$sql = "
		UPDATE 
			{$wpdb->prefix}arlo_import
		SET
			" . (count($update_fields) ? implode(', ', $update_fields) . ', ' : '' )  . "
			modified = %s
		WHERE 
			import_id = %d
		";
		$parameter[] = $utc_date;
		$parameter[] = $this->import_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct database query is required. The SQL statement is constructed from static strings and parameters are prepared here. Do not need cache for import process.
		if ($wpdb->query($wpdb->prepare($sql, $parameter)) === false) {
			throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	/**
	 * Insert a new import entry for the current import.
	 *
	 * @param string|null $nonce Nonce to store with the entry.
	 * @return int|string The import ID.
	 * @throws \Exception If the database insert fails.
	 */
	public function set_import_entry($nonce = null) {
		global $wpdb;
		$utc_date = gmdate("Y-m-d H:i:s");
		$utc_plusonehour =  gmdate("Y-m-d H:i:s", time() + (60 * 60));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database query is required.Do not need cache for insert operation in data import process.
		$query = $wpdb->query($wpdb->prepare("
		INSERT INTO
			{$wpdb->prefix}arlo_import 
			(import_id, nonce, created, expired)
		VALUES
			(%d, %s, %s, %s)
		", (int) $this->import_id, $nonce, $utc_date, $utc_plusonehour));

		if ( ! $query ) {
			throw new \Exception('SQL error creating import entry: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		return $this->import_id;
	}

	/**
	 * Delete the import entry for the given import ID.
	 *
	 * @param int|string $import_id The import ID whose entry should be deleted.
	 * @throws \Exception If the database delete fails.
	 */
	public function delete_import_entry( $import_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct delete required; no cache to invalidate for a row that never completed setup.
		$result = $wpdb->delete(
			"{$wpdb->prefix}arlo_import",
			['import_id' => (int) $import_id],
			['%d']
		);

		if ($result === false) {
			throw new \Exception('SQL error deleting import entry: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	/**
	 * Atomically consume the nonce on an import entry so the same callback
	 * cannot be replayed.
	 *
	 * Called immediately after a successful nonce match in get_and_validate_import_entry().
	 * The UPDATE includes the nonce in its WHERE clause so that only the first
	 * concurrent request succeeds — if a second request races past the PHP-side
	 * comparison, the DB update returns 0 rows and this method returns false,
	 * causing the caller to reject the duplicate.
	 *
	 * @param int|string $import_id The import entry to consume the nonce for.
	 * @param string     $nonce     The nonce value to match atomically.
	 * @return bool True if the nonce was consumed, false if already consumed or row missing.
	 * @throws \Exception If the DB update fails.
	 */
	private function consume_import_nonce($import_id, string $nonce): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time nonce consumption; caching not applicable.
		$result = $wpdb->update(
			"{$wpdb->prefix}arlo_import",
			['nonce' => ''],
			['import_id' => (int) $import_id, 'nonce' => $nonce],
			['%s'],
			['%d', '%s']
		);
		if ($result === false) {
			throw new \Exception('Import callback rejected: failed to consume nonce due to DB error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
		return $result === 1;
	}
}