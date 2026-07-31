<?php
namespace ArloTraining;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
#[\AllowDynamicProperties]
class Scheduler {

	const MAX_SLEEP_BETWEEN_TASKS = 15;	
	
	private $max_simultaneous_task = 1;
	private $plugin;
	
	public function __construct($plugin) {
		$this->plugin = $plugin;
	}
	
	private function get_running_tasks_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is static and safe. No caching needed for import process , low-frequency operations with high-frequency data updates. Direct database query is required for custom table.
		$result = $wpdb->get_results("
		SELECT 
			COUNT(1) AS num
		FROM
			{$wpdb->prefix}arlo_async_tasks
		WHERE
			task_status = 2
		"); 
				
		return $result[0]->num;
	}
	
	private function get_running_paused_tasks_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is static and safe. No caching needed for data import process, low-frequency operations with high-frequency data updates. Direct database query is required for custom table.
		$result = $wpdb->get_results("
		SELECT 
			COUNT(1) AS num
		FROM
			{$wpdb->prefix}arlo_async_tasks
		WHERE
			task_status IN (1,2)
		"); 
				
		return $result[0]->num;
	}
	
	
	public function set_task($task = '', $priority = 0) {
		global $wpdb;
		if (empty($task)) return false;
		$utc_date = gmdate("Y-m-d H:i:s");
	
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Import related queries do not need caching. Direct database query is required for custom table.
		$query = $wpdb->query($wpdb->prepare("
		INSERT INTO
			{$wpdb->prefix}arlo_async_tasks (task_priority, task_task, task_created)
		VALUES
			(%d, %s, %s)
		", $priority, $task, $utc_date));
		
		if ($query) {
			return $wpdb->insert_id;
		} else {
			return false;
		}
	}	
	
	public function update_task($task_id = 0, $task_status = null, $task_status_text = '') {
		global $wpdb;
		$task_status = (is_null($task_status) ? 'task_status' : intval($task_status)); //actually task_stauts will never be null.
		$no_status_text = empty($task_status_text);
		$task_status_text = ($no_status_text ? 'task_status_text' :  $task_status_text);
		$utc_date = gmdate("Y-m-d H:i:s"); 
		
		$parameter = [$task_status];

		$task_status_text_sql = $no_status_text ? '' : 'task_status_text = %s,';
		if(!$no_status_text) {
			$parameter[] = $task_status_text;
		}
		$parameter[] = $utc_date;
		$parameter[] = intval($task_id);
		
		$sql = "
		UPDATE 	
			{$wpdb->prefix}arlo_async_tasks
		SET
			task_status = %d,
			$task_status_text_sql
			task_modified = %s
		WHERE
			task_id = %d
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The SQL statement is dynamically constructed and parameters are prepared here. Import related queries do not need caching. Direct database query is required for custom table. No caching needed for data import process
		$query = $wpdb->query(Utilities::prepare_sql($sql, $parameter));
	}
	
	public function update_task_data($task_id, $data = array(), $overwrite_data = false) {
		global $wpdb;
		if (!$overwrite_data) {
			$task = $this->get_task_data($task_id);
			
			$task_data = (!empty($task[0]->task_data_text) ? json_decode($task[0]->task_data_text, true) : [] ) ;
			$data = array_replace_recursive($task_data, $data);
		}		
		$data = json_encode($data);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No caching needed for data import process, low-frequency operations with high-frequency data updates. Direct database query is required for custom table.
		$query = $wpdb->query($wpdb->prepare("
		INSERT INTO
			{$wpdb->prefix}arlo_async_task_data
		SET
			data_task_id = %d,
			data_text = %s
		ON DUPLICATE KEY UPDATE 
			data_text = %s
		", $task_id, $data, $data));		
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

	public function has_failed_scheduled_import_since($utc_date): bool {
		global $wpdb;

		$utc_date = is_scalar($utc_date) ? trim((string) $utc_date) : '';
		if ($utc_date === '') {
			return false;
		}

		$sql = "
		SELECT
			COUNT(1)
		FROM
			{$wpdb->prefix}arlo_async_tasks
		WHERE
			task_task = %s
		AND
			task_priority = %d
		AND
			task_status = %d
		AND
			COALESCE(task_modified, task_created) > %s
		";

		$result = $wpdb->get_var($wpdb->prepare($sql, 'import', 0, 3, $utc_date)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is a static parameterised string; import task state must be read directly from the async task table.

		return intval($result) > 0;
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
		global $wpdb;
		$task_id = (isset($task_id) && is_numeric($task_id) ? $task_id : null);
		$status = (isset($status) && is_numeric($status) ? [$status] : (is_array($status) ? array_filter($status, function($numeric) { return is_numeric($numeric);} ) : null));
		$priority = (isset($priority) && is_numeric($priority) ? $priority : null);
		$limit = (isset($limit) && is_numeric($limit) ? $limit : null);
		$task_data_text = (!empty($task_data_text) ? $task_data_text : null);
		
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
			{$wpdb->prefix}arlo_async_tasks
		LEFT JOIN 
			{$wpdb->prefix}arlo_async_task_data
		ON
			data_task_id = task_id
		WHERE 	
			1
			".(!is_null($status) ? "AND task_status IN (" . implode(',', array_map(function() {return "%d";}, $status)) . ")" : "") . "
			".(!is_null($priority) ? "AND task_priority = %d" : "") . "
			".(!is_null($task_id) ? "AND task_id = %d" : "") . "
			".(!is_null($task_data_text) ? "AND data_text like %s" : "") . "
		ORDER BY
			task_priority,
			task_created
		" . (!is_null($limit) ? "LIMIT %d" : "");

		$parameter = [];
		if(!is_null($status)) {
			$parameter = array_merge($parameter, $status);
		}
		if(!is_null($priority)) {
			$parameter[] = $priority;
		}
		if(!is_null($task_id)) {
			$parameter[] = $task_id;
		}
		if(!is_null($task_data_text)) {
			$parameter[] = '%' . $task_data_text . '%';
		}
		if(!is_null($limit)) {
			$parameter[] = $limit;
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is dynamically constructed and parameters are prepared here. Import related queries do not need caching. Direct database query is required for custom table.
		$result = $wpdb->get_results(Utilities::prepare_sql($sql, $parameter));
		
		return $result;
	}
		
	public function delete_running_tasks() {
		return $this->delete_tasks(2);
	}
	
	public function delete_paused_tasks() {
		return $this->delete_tasks(1);
	}
	
	private function delete_tasks($status = null, $priority = null, $task_id = null, $limit = null) {
		global $wpdb;
		$task_id = (isset($task_id) && is_numeric($task_id) ? $task_id : null);
		$status = (isset($status) && is_numeric($status) ? [$status] : (is_array($status) ? $status : null));
		$priority = (isset($priority) && is_numeric($priority) ? $priority : null);
		$limit = (isset($limit) && is_numeric($limit) ? $limit : null);
		
		$sql = "
		DELETE tasks, tasks_data FROM
			{$wpdb->prefix}arlo_async_tasks AS tasks
		LEFT JOIN 
			{$wpdb->prefix}arlo_async_task_data AS tasks_data
		ON
			data_task_id = task_id
		WHERE 	
			1
			".(!is_null($status) ? "AND task_status IN (" . implode(',', array_map(function() {return "%d";}, $status)) . ")" : "") . "
			".(!is_null($priority) ? "AND task_priority = %d" : "") . "
			".(!is_null($task_id) ? "AND task_id = %d" : "") . "
		" . (!is_null($limit) ? "LIMIT %d" : "");
		
		$parameter = [];
		if(!is_null($status)) {
			$parameter = array_merge($parameter, $status);
		}
		if(!is_null($priority)) {
			$parameter[] = $priority;
		}
		if(!is_null($task_id)) {
			$parameter[] = $task_id;
		}
		if(!is_null($limit)) {
			$parameter[] = $limit;
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is dynamically constructed and parameters are prepared here. Import related queries do not need caching. Direct database query is required for custom table
		return $wpdb->get_results(Utilities::prepare_sql($sql, $parameter));
	}
	
	public function run_task($task_id = null) {
		$task_id = (!empty($task_id) && is_numeric($task_id) ? $task_id : null);
		if ($this->check_empty_slot_for_task()) {

			$task = !is_null($task_id) ? $this->get_task_data($task_id) : $this->get_next_task();
			$this->process_task($task);
		}
	}
	
	public function terminate_all_immediate_task($task_id) {
		global $wpdb;
		$task_id = (isset($task_id) && is_numeric($task_id) ? $task_id : null);
		
		if ($task_id > 0) {
			//TODO: we should query this from the database, but now we have only import as an async task
			$this->unlock_process("import");
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is static and safe. No caching needed for import process , low-frequency operations with high-frequency data updates. Direct database query is required for custom table
			return $wpdb->query($wpdb->prepare("
			UPDATE
				{$wpdb->prefix}arlo_async_tasks AS tasks
			SET
				task_status = 4, 
				task_status_text = 'Import is terminated by the user'
			WHERE 
				task_id >= %d
			", $task_id));
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
			throw new \ArloTraining\SchedulerException(esc_html('Kick off scheduler error: ' . (is_array($error_message) ? implode(', ', $error_message) : $error_message)));
		}
	}

	private function get_query_args() {
		if ( property_exists( $this, 'query_args' ) ) {
			return $this->query_args;
		}

		return array(
			'action' => 'arlo_run_scheduler',
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
			'body'      => array( 'nonce' => wp_create_nonce( 'arlo_import' ) ),
			'cookies'   => $_COOKIE,
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);
	}

}
