<?php

namespace Arlo;
#[\AllowDynamicProperties]
class Scheduler {

	const MAX_SLEEP_BETWEEN_TASKS = 15;	
	
	private $max_simultaneous_task = 1;
	private $table = '';
	private $table_data = '';
	private $plugin;
	private $dbl;
	
	public function __construct($plugin, $dbl) {
		$this->dbl = &$dbl; 		
		$this->table = $this->dbl->prefix . 'arlo_async_tasks';
		$this->tabledata = $this->dbl->prefix . 'arlo_async_task_data';
		$this->plugin = $plugin;
	}
	
	private function get_running_tasks_count() {		
		$sql = "
		SELECT 
			COUNT(1) AS num
		FROM
			{$this->table}
		WHERE
			task_status = 2
		";
		
		$result = $this->dbl->get_results($sql); 
				
		return $result[0]->num;
	}
	
	private function get_running_paused_tasks_count() {		
		$sql = "
		SELECT 
			COUNT(1) AS num
		FROM
			{$this->table}
		WHERE
			task_status IN (1,2)
		";
		
		$result = $this->dbl->get_results($sql); 
				
		return $result[0]->num;
	}
	
	
	public function set_task($task = '', $priority = 0) {
		if (empty($task)) return false;
		$utc_date = gmdate("Y-m-d H:i:s");
	
		$sql = "
		INSERT INTO
			{$this->table} (task_priority, task_task, task_created)
		VALUES
			(%d, %s, %s)
		";
		
		$query = $this->dbl->query($this->dbl->prepare($sql, $priority, $task, $utc_date));
		
		if ($query) {
			return $this->dbl->insert_id;
		} else {
			return false;
		}
	}	
	
	public function update_task($task_id = 0, $task_status = null, $task_status_text = '') {
		$task_status = (is_null($task_status) ? 'task_status' : intval($task_status));
		$task_status_text = (empty($task_status_text) ? 'task_status_text' : "'" . $this->dbl->_real_escape($task_status_text) . "'");
		$utc_date = gmdate("Y-m-d H:i:s"); 
	
		$sql = "
		UPDATE 	
			{$this->table}
		SET
			task_status = {$task_status},
			task_status_text = {$task_status_text},
			task_modified = '{$utc_date}'
		WHERE
			task_id = " . (intval($task_id)) . "
		";

		$query = $this->dbl->query($sql);		
	}
	
	public function update_task_data($task_id, $data = array(), $overwrite_data = false) {	
		if (!$overwrite_data) {
			$task = $this->get_task_data($task_id);
			
			$task_data = (!empty($task[0]->task_data_text) ? json_decode($task[0]->task_data_text, true) : [] ) ;
			$data = array_replace_recursive($task_data, $data);
		}		
		$data = json_encode($data);

		$sql = "
		INSERT INTO
			{$this->tabledata}
		SET
			data_task_id = %d,
			data_text = '%s'
		ON DUPLICATE KEY UPDATE 
			data_text = '%s'
		";
		$query = $this->dbl->query($this->dbl->prepare($sql, $task_id, $data, $data));		
	}

	public function check_empty_slot_for_task() {
		return $this->max_simultaneous_task > $this->get_running_tasks_count();
	}
	
	public function get_next_task() {
		$task = $this->get_next_paused_tasks();
		
		if (empty($task)) {
			$task = $this->get_next_immediate_tasks();
			if (empty($task)) {
				$task = $this->get_tasks(0, 0, null, 1);
			}
		}
		
		return $task;
	}
	
	public function get_task_data($task_id = null) {
		return $this->get_tasks(null, null, $task_id, 1);
	}
	
	public function get_running_tasks() {
		return $this->get_tasks(2);
	}
	
	public function get_paused_tasks() {
		return $this->get_tasks(1);
	}

	
	public function get_next_immediate_tasks() {
		if ($this->get_running_paused_tasks_count() == 0) {
			return $this->get_tasks(0, -1, null, 1);
		}
		return [];
	}
	
	public function get_next_paused_tasks() {	
		return $this->get_tasks(1, null, null, 1);
	}
	
	public function get_tasks($status = null, $priority = null, $task_id = null, $limit = null, $task_data_text = '') {
		$task_id = (isset($task_id) && is_numeric($task_id) ? $task_id : null);
		$status = (isset($status) && is_numeric($status) ? [$status] : (is_array($status) ? array_filter($status, function($numeric) { return is_numeric($numeric);} ) : null));
		$priority = (isset($priority) && is_numeric($priority) ? $priority : null);
		$limit = (isset($limit) && is_numeric($limit) ? $limit : null);
		$task_data_text = (!empty($task_data_text) ? $this->dbl->_real_escape($task_data_text) : null);
		
		$sql = "
		SELECT
			task_id,
			task_task,
			task_status,
			task_priority,
			task_status_text,
			task_modified,
			data_text AS task_data_text
		FROM
			{$this->table}
		LEFT JOIN 
			{$this->tabledata}
		ON
			data_task_id = task_id
		WHERE 	
			1
			".(!is_null($status) ? "AND task_status IN (" . implode(",", $status) . ")" : "") . "
			".(!is_null($priority) ? "AND task_priority = " . $priority : "") . "
			".(!is_null($task_id) ? "AND task_id = " . $task_id : "") . "
			".(!is_null($task_data_text) ? "AND data_text like '%" . $task_data_text . "%'" : "") . "
		ORDER BY
			task_priority,
			task_created
		" . (!is_null($limit) ? "LIMIT " . $limit : "");

		return $this->dbl->get_results($sql);
	}
		
	public function delete_running_tasks() {
		return $this->delete_tasks(2);
	}
	
	public function delete_paused_tasks() {
		return $this->delete_tasks(1);
	}
	
	private function delete_tasks($status = null, $priority = null, $task_id = null, $limit = null) {
		$task_id = (isset($task_id) && is_numeric($task_id) ? $task_id : null);
		$status = (isset($status) && is_numeric($status) ? [$status] : (is_array($status) ? $status : null));
		$priority = (isset($priority) && is_numeric($priority) ? $priority : null);
		$limit = (isset($limit) && is_numeric($limit) ? $limit : null);
		
		$sql = "
		DELETE tasks, tasks_data FROM
			{$this->table} AS tasks
		LEFT JOIN 
			{$this->tabledata} AS tasks_data
		ON
			data_task_id = task_id
		WHERE 	
			1
			".(!is_null($status) ? "AND task_status IN (" . implode(",", $status) . ")" : "") . "
			".(!is_null($priority) ? "AND task_priority = " . $priority : "") . "
			".(!is_null($task_id) ? "AND task_id = " . $task_id : "") . "
		" . (!is_null($limit) ? "LIMIT " . $limit : "");
				
		return $this->dbl->get_results($sql);
	}
	
	public function run_task($task_id = null) {
		$task_id = (!empty($task_id) && is_numeric($task_id) ? $task_id : null);
		if ($this->check_empty_slot_for_task()) {

			$task = !is_null($task_id) ? $this->get_task_data($task_id) : $this->get_next_task();
			$this->process_task($task);
		}
	}
	
	public function terminate_all_immediate_task($task_id) {
		$task_id = (isset($task_id) && is_numeric($task_id) ? $task_id : null);
		
		if ($task_id > 0) {
			$sql = "
			UPDATE
				{$this->table} AS tasks
			SET
				task_status = 4, 
				task_status_text = 'Import is terminated by the user'
			WHERE 
				task_id >= {$task_id}
			";

			//TODO: we should query this from the database, but now we have only import as an async task
			$this->unlock_process("import");
			
			return $this->dbl->query($sql);
		}
		
		return false;

	}
	
	public function process_task($task = array()) {
		if (isset($task[0]) && !empty($task_task = $task[0]->task_task)) {
			$this->schedule_cron();
			if (!$this->is_process_running($task_task)) {
				$this->lock_process($task_task);
				switch ($task_task) {
					case 'import':
						if (!$this->plugin->import($task[0]->task_priority == -1, $task[0]->task_id)) {
							$this->update_task($task[0]->task_id, 3, "Import failed");
							$this->clear_cron();
						}
					break;
				}
				$this->unlock_process($task_task);
			}	
		} else {
			$this->clear_cron();
		}
	}

	protected function schedule_cron() {
		if ( ! wp_next_scheduled('arlo_scheduler')) {
			wp_schedule_event( time() + (60*5), 'minutes_5', 'arlo_scheduler' );
		}
	}

	public function clear_cron() {
		$timestamp = wp_next_scheduled('arlo_scheduler');

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'arlo_scheduler');
		}

		wp_clear_scheduled_hook( 'arlo_scheduler');
	}	

	private function is_process_running($task) {
		if ( get_site_transient( $task . '_process_lock' ) ) {
			// Process already running.
			return true;
		}

		return false;
	}

	private function lock_process($task) {
		set_site_transient( $task . '_process_lock', microtime(), 180 );
	}

	public function unlock_process($task) {
		delete_site_transient( $task . '_process_lock' );
		return $this;
	}	

	public function kick_off_scheduler() {
		if ( ! has_action( 'shutdown', array( $this, 'kick_off_scheduler_on_shutdown' ) ) ) {
			add_action( 'shutdown', array( $this, 'kick_off_scheduler_on_shutdown' ) );
		}		
	}

	public function kick_off_scheduler_on_shutdown() {
		$url = add_query_arg( $this->get_query_args(), $this->get_query_url() );
		$args = $this->get_post_args();	
	
		wp_remote_post( esc_url_raw( $url ), $args );
	}

	private function try_kick_off_scheduler($url) {
		$limit = 10;
		$tries = 0;
		$sleep_seconds = 3;

		$args = $this->get_post_args();

		do {
			$success = false;
			$response = wp_remote_post( esc_url_raw( $url ), $args );

			if (is_wp_error($response)) {
				$error_message = $response->get_error_messages();
				sleep($sleep_seconds);
			} else if ( substr($response['response']['code'], 0, 1) != 2 ) {
				$error_message = 'Unknown error';
				if (!empty($response['response']['code'])) {
					$error_message = $response['response']['message'];
				} 

				sleep($sleep_seconds);
			} else {
				$success = true;
			}
		} while(!$success && ++$tries < $limit);

		if (!$success && isset($error_message) && $tries >= $limit) {
			throw new \Arlo\SchedulerException('Kick off scheduler error: ' . (is_array($error_message) ? implode(', ', $error_message) : $error_message));
		}
	}

	private function get_query_args() {
		if ( property_exists( $this, 'query_args' ) ) {
			return $this->query_args;
		}

		return array(
			'action' => 'arlo_run_scheduler',
			'nonce'  => wp_create_nonce( 'arlo_import' ),
		);
	}

	private function get_query_url() {
		if ( property_exists( $this, 'query_url' ) ) {
			return $this->query_url;
		}

		return admin_url( 'admin-ajax.php' );
	}

	private function get_post_args() {
		if ( property_exists( $this, 'post_args' ) ) {
			return $this->post_args;
		}

		return array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'cookies'   => $_COOKIE,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);
	}

}
