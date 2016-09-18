<?php

namespace Arlo;

class Scheduler {
	
	private $max_simultaneous_task = 1;
	private $table = '';
	private $table_data = '';
	private $wpdb;
	
	public function __construct($plugin) {
		global $wpdb;
		
		$this->wpdb = &$wpdb; 		
		$this->table = $this->wpdb->prefix . 'arlo_async_tasks';
		$this->tabledata = $this->wpdb->prefix . 'arlo_async_task_data';
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
		
		$result = $this->wpdb->get_results($sql); 
				
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
		
		$result = $this->wpdb->get_results($sql); 
				
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
		
		$query = $this->wpdb->query($this->wpdb->prepare($sql, $priority, $task, $utc_date));
		
		if ($query) {
			return $this->wpdb->insert_id;
		} else {
			return false;
		}
	}	
	
	public function update_task($task_id = 0, $task_status = null, $task_status_text = '') {
		$task_status = (is_null($task_status) ? 'task_status' : intval($task_status));
		$task_status_text = (empty($task_status_text) ? 'task_status_text' : "'" . $this->wpdb->_real_escape($task_status_text) . "'");
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
		
		$query = $this->wpdb->query($sql);		
	}	
	
	public function update_task_data($task_id, $data = array(), $overwrite_data = false) {	
		if (!$overwrite_data) {
			$task = $this->get_task_data($task_id);
			
			$task_data = (!empty($task[0]->task_data_text) ? json_decode($task[0]->task_data_text, true) : [] ) ;
			$data = array_merge($task_data, $data);
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
		$query = $this->wpdb->query($this->wpdb->prepare($sql, $task_id, $data, $data));		
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
	
	public function get_tasks($status = null, $priority = null, $task_id = null, $limit = null) {
		$task_id = (isset($task_id) && is_numeric($task_id) ? $task_id : null);
		$status = (isset($status) && is_numeric($status) ? [$status] : (is_array($status) ? $status : null));
		$priority = (isset($priority) && is_numeric($priority) ? $priority : null);
		$limit = (isset($limit) && is_numeric($limit) ? $limit : null);
		
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
		ORDER BY
			task_priority,
			task_created
		" . (!is_null($limit) ? "LIMIT " . $limit : "");
				
		return $this->wpdb->get_results($sql);
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
				
		return $this->wpdb->get_results($sql);
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
			
			return $this->wpdb->query($sql);
		}
		
		return false;

	}
	
	public function process_task($task = array()) {
		if (!empty($task[0]->task_task)) {

			switch ($task[0]->task_task) {
				case 'import':
					if (!$this->plugin->import($task[0]->task_priority == -1, $task[0]->task_id)) {
						$this->update_task($task[0]->task_id, 3, "Import failed");
					}
					
				break;
			}
		}
	}
}

?>