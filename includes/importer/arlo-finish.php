<?php

namespace Arlo\Importer;

class Finish extends Importer {

	public function __construct() {	}

	public function import() {
		if (parent::$plugin->get_import_lock_entries_number() == 1 && parent::$plugin->check_import_lock(parent::$import_id)) {
            //clean up the old entries
			$this->cleanup_import(parent::$import_id);
        
            // update logs
            parent::$plugin->add_log('Synchronization successful', parent::$import_id, null, true);            
			
	        //set import id
	        parent::$plugin->set_import_id(parent::$import_id);
	        
	        parent::$plugin->set_last_import();
	        
	        $message_handler = parent::$plugin->get_message_handler();
	        $message_handler->dismiss_by_type('import_error');

			parent::$is_finished = true;	        
        } else {
            parent::$plugin->add_log('Synchronization died because of a database LOCK, please wait 5 minutes and try again.', parent::$import_id);
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
			$table = parent::$wpdb->prefix . 'arlo_' . $table;
			parent::$wpdb->query(parent::$wpdb->prepare("DELETE FROM $table WHERE import_id <> %s", parent::$import_id));
		}   

		parent::$plugin->add_log('Database cleanup', parent::$import_id);
        
        // delete unneeded custom posts
        parent::$plugin->delete_custom_posts('eventtemplates','et_post_name','event');

        parent::$plugin->delete_custom_posts('presenters','p_post_name','presenter');

        parent::$plugin->delete_custom_posts('venues','v_post_name','venue');           
        
		parent::$plugin->add_log('Posts cleanup ', parent::$import_id);
	}
}