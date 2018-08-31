<?php

namespace Arlo\Importer;

use Arlo\Logger;
use Arlo\Utilities;

class ProcessFragment extends BaseImporter {

	protected $data_json;
	
	//the keys in this array have to match with the keys in the JSON 
	//except irregular_tasks
	public $import_tasks = [	
				'Download' => 'Download fragment file',		
				'TimeZones' => 'Importing time zones',
				'Presenters' => 'Importing presenters',
				'Venues' => 'Importing venues',
				'Templates' => 'Importing event templates',
				'Events' => 'Importing events',
				'OnlineActivities' => 'Importing online activities',
				'Categories' => 'Importing categories',
				'CategoryItems' => 'Updating templates order in category',
			];	

	public $current_task;
	public $current_task_class;
	public $current_task_num;
	public $current_task_desc = '';
	public $current_task_iteration = 0;
	public $current_task_retry = 0;

	private $irregular_tasks = [
				'Download',
			];

	public $uri;


	protected function save_entity($item) {}

	public function set_state($state) {
		$task_keys = array_keys($this->import_tasks);
		if (!empty($state)) {
			if (!empty($state->current_subtask)) {
				$this->set_current_task($state->current_subtask);
				$this->current_task_iteration = (isset($state->iteration) && is_numeric($state->iteration) ? $state->iteration + 1 : 0);
			} else if (!empty($state->finished_subtask)) {
				//figure out the next task;
				$k = array_search($state->finished_subtask, $task_keys);
				if ($k !== false && isset($task_keys[++$k])) {
					$this->set_current_task($task_keys[$k]);	
					$state->current_subtask_retry = 0;		
				} else {
					$this->iteration_finished = true;
				}
			} else {
				$this->set_current_task($task_keys[0]);	
			}
		} else {
			$this->set_current_task($task_keys[0]);
		}

		$this->current_task_retry = (isset($state->current_subtask_retry) ? $state->current_subtask_retry + 1 : $this->current_task_retry + 1);
		$this->update_task_data_for_retry($this->get_state());
	}

	public function get_state() {
		$state = [
			'finished_subtask' => null,
			'current_subtask' => null,
			'current_subtask_desc' => isset($this->import_tasks[$this->current_task]) ? $this->import_tasks[$this->current_task] : '',
			'current_subtask_num' => array_search($this->current_task, array_keys($this->import_tasks)),
			'iteration' => null,
			'iteration_finished' => ($this->iteration_finished ? 1 : 0),
			'current_subtask_retry' => $this->current_task_retry,
		];
	
		if (isset($this->current_task_class) && $this->current_task_class->is_finished) {
			$state['finished_subtask'] = $this->current_task;
		} else {
			$state['current_subtask'] = $this->current_task;
			$state['iteration'] = isset($this->current_task_class->iteration) ? $this->current_task_class->iteration : 0;
		}

		return $state;
	}

	private function update_task_data_for_retry($state) {
		$data['subtask_state'] = $state;

		$this->scheduler->update_task_data($this->task_id, $data);
	}

	public function set_current_task($task_step) {
		$this->current_task = $task_step;
		$this->current_task_num = array_search($this->current_task, array_keys($this->import_tasks));
		$this->current_task_desc = $this->import_tasks[$this->current_task];
	}

	private function get_data_json() {
		$import_iteration = $this->iteration + 1;
		$item = $this->importing_parts->get_import_part("fragment", $import_iteration, $this->import_id);

		if (empty($item)) {
			throw new \Exception("Import Error: the import \"fragment\" part cannot be found");
		}
		if (empty($item->import_text)) {
			throw new \Exception("Import Error: the content of the fragment import part is empty");
		}

		$this->data_json = json_decode($item->import_text);

		if (is_null($this->data_json)) {
			throw new \Exception("JSON error: " . json_last_error_msg());
		}
	}
	public function run() {
		$import_task = $this->current_task;

		$this->data_json = null;
		if ($this->current_task_num > 0) {
			$this->get_data_json();
		}

		$class_name = "Arlo\Importer\\" . $import_task;

		$this->current_task_class = new $class_name($this->importer, $this->dbl, $this->message_handler, (!empty($this->data_json->$import_task) ? $this->data_json->$import_task : null), $this->current_task_iteration, $this->api_client, $this->scheduler, $this->importing_parts);		
		
		if (!empty($this->data_json->$import_task) || in_array($import_task, $this->irregular_tasks)) {			
			//we need to do some special setup for different tasks
			switch($this->current_task) {
				case 'Download':
					$import = $this->importer->get_import_entry($this->import_id, null, 1);
					if (!is_null($import)) {
						$this->current_task_class->uri = $this->uri;
						$this->current_task_class->import_part = "fragment";
						$this->current_task_class->import_iteration = $this->iteration + 1;
						$this->current_task_class->response_json = json_decode($import->response_json);			 
					} else {
						throw new \Exception('Couldn\'t retrive the import from database');
					}
				break;
			}
			try {
				$this->current_task_class->run();
				$this->current_task_retry--;
			} catch (\Exception $e) {
				throw new \Exception($e->getMessage());
			}
			
		} else {
			$this->current_task_class->is_finished = true;
		}
	}
}