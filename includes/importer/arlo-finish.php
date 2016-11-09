<?php

namespace Arlo\Importer;

class Finish extends BaseEntity {

	protected function save_entity($item) {}

	public function import() {
		if ($this->plugin->get_import_lock_entries_number() == 1 && $this->plugin->check_import_lock($this->import_id)) {
            //clean up the old entries
			$this->cleanup_import($this->import_id);
        
            // update logs
            Logger::log('Synchronization successful', $this->import_id, null, true);            
			
	        //set import id
	        $this->plugin->set_import_id($this->import_id);
	        
	        $this->plugin->set_last_import();
	        
	        $message_handler = $this->plugin->get_message_handler();
	        $message_handler->dismiss_by_type('import_error');

			$this->is_finished = true;
        } else {
            Logger::log('Synchronization died because of a database LOCK, please wait 5 minutes and try again.', $this->import_id);
        }
	}

	private function cleanup_import() {
		$tables = array(
			'eventtemplates',
			'contentfields',
			'offers',
			'events',
			'events_tags',
			'tags',
			'presenters',
			'venues',
			'categories',
			'onlineactivities',
			'onlineactivities_tags',
            'events_presenters',
            'eventtemplates_categories',
            'eventtemplates_presenters',
            'eventtemplates_tags',
            'timezones',
            'timezones_olson'
		);
                		
		foreach($tables as $table) {
			$table = $this->wpdb->prefix . 'arlo_' . $table;
			$this->wpdb->query($this->wpdb->prepare("DELETE FROM $table WHERE import_id <> %s", $this->import_id));
		}   

		Logger::log('Database cleanup', $this->import_id);
        
        // delete unneeded custom posts
        $this->plugin->delete_custom_posts('eventtemplates','et_post_name','event');

        $this->plugin->delete_custom_posts('presenters','p_post_name','presenter');

        $this->plugin->delete_custom_posts('venues','v_post_name','venue');           
        
		Logger::log('Posts cleanup ', $this->import_id);
	}
}