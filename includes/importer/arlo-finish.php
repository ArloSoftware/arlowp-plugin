<?php

namespace Arlo\Importer;

use Arlo\Logger;
use Arlo\CacheControl;

class Finish extends BaseImporter {

	protected function save_entity($item) {}

	public function run() {
		if ($this->importer->get_import_lock_entries_number() == 1 && $this->importer->check_import_lock($this->import_id)) {

            //clean up the old entries
			$this->cleanup_import($this->import_id);
       
            // update logs
            Logger::log('Synchronization successful', $this->import_id, null, true);            
			
	        //set import id
	        $this->importer->set_current_import_id($this->import_id);
	        
			$this->importer->set_last_import_date();
			
			$this->importer->set_tax_exempt_events($this->import_id);
	        
	        $this->message_handler->dismiss_by_type('import_error');

			$this->is_finished = true;

			do_action('arlo_import_finished');

			CacheControl::Clear();
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
            'timezones'
		);
                		
		foreach($tables as $table) {
			$table = $this->dbl->prefix . 'arlo_' . $table;
			$this->dbl->query($this->dbl->prepare("DELETE FROM $table WHERE import_id <> %s", $this->import_id));
		}   

		Logger::log('Database cleanup', $this->import_id);
        
        // delete unneeded custom posts
        \Arlo_For_Wordpress::delete_custom_posts('eventtemplates','et_post_name','event');

        \Arlo_For_Wordpress::delete_custom_posts('presenters','p_post_name','presenter');

        \Arlo_For_Wordpress::delete_custom_posts('venues','v_post_name','venue');           
        
		Logger::log('Posts cleanup ', $this->import_id);
	}
}