<?php

namespace Arlo;

class Scheduler {
	
	private $max_simultaneous_task = 1;
	private $table = '';
	private $wpdb;
	
	public function __construct($plugin) {
		global $wpdb;
		
		$this->wpdb = &$wpdb; 		
		$this->table = $this->wpdb->prefix . 'arlo_async_tasks';
		$this->plugin = $plugin;
	}

	
	private function get_running_tasks_count() {
		global $wpdb;
		
		$sql = "
		SELECT 
			1
		FROM
			{$this->table}
		WHERE
			task_status = 2
		";
		
		 $result = $this->wpdb->get_results($sql); 
		
		return $this->wpdb->num_rows($sql);
	}
	
	public function set_task($task = '', $priority = 0) {
		if (empty($task)) return false;
	
		$sql = "
		INSERT INTO
			{$this->table} (task_priority, task_task, task_created)
		VALUES
			(%d, %s, NOW())
		";
		
		$query = $this->wpdb->query($this->wpdb->prepare($sql, $priority, $task));
		
		if ($query) {
			return $this->wpdb->insert_id;
		} else {
			return false;
		}
	}	
	
	public function update_task($task_id = 0, $task_status = 0, $task_status_text = '') {
		$sql = "
		UPDATE 	
			{$this->table}
		SET
			task_status = %d,
			task_status_text = '%s'
		WHERE
			task_id = %d
		";

		$query = $this->wpdb->query($this->wpdb->prepare($sql, $task_status, $task_status_text, $task_id));
	}	
	
	public function check_empty_slot_for_task() {
		return $this->max_simultaneous_task > $this->get_running_tasks_count;
	}
	
	public function get_next_task() {		
		return $this->get_task_data(); 
	}
	
	public function get_task_data($task_id = null) {
		return $this->get_tasks(0, null, $task_id, 1);
	}
	
	public function get_running_tasks() {
		return $this->get_tasks(1);
	}
	
	public function get_next_immediate_tasks() {	
		return $this->get_tasks(0, -1, null, 1);
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
			task_status_text
		FROM
			{$this->table}
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
	
	public function run_task($task_id = null) {
		$task_id = (!empty($task_id) && is_numeric($task_id) ? $task_id : null);
		
		if ($this->check_empty_slot_for_task()) {
			$task = !is_null($task_id) ? $this->get_task_data($task_id) : $this->get_next_task();

			$this->process_task($task);
		}
	}
	
	public function process_task($task = array()) {
		if (!empty($task[0]->task_task)) {

			switch ($task[0]->task_task) {
				case 'import':
					if ($this->plugin->import($task[0]->task_priority == -1, $task[0]->task_id)) {
						$this->update_task($task[0]->task_id, 3, "Import finished");
					} else {
						$this->update_task($task[0]->task_id, 2, "Import failed");
					}
					
				break;
			}
		}
	}
}

?>