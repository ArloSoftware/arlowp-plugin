<?php

namespace Arlo;

use Arlo\Utilities;

class VersionHandler {
	const VERSION = '3.0';

	private $dbl;
	private $message_handler;
	private $plugin;

	public function __construct($dbl, $message_handler, $plugin) {
		$this->dbl = &$dbl; 	

		$this->message_handler = $message_handler;	
		$this->plugin = $plugin;
	}	

	public function get_current_installed_version () {
		return get_option('arlo_plugin_version');
	}

	public function set_installed_version() {
		update_option('arlo_plugin_version', self::VERSION);
				
		$now = Utilities::get_now_utc();
		update_option('arlo_updated', $now->format("Y-m-d H:i:s"));
	}

	public function run_update($from_version) {
		$this->update(self::VERSION, $from_version);
		$this->set_installed_version();
	}

	private function update($new_version, $old_version) {
		//pre datamodell update need to be done before
		if (version_compare($old_version, '2.4') < 0) {
			$this->run_pre_data_update('2.4');
		}

		if (version_compare($old_version, '2.4.1.1') < 0) {
			$this->run_pre_data_update('2.4.1.1');
		}	

		if (version_compare($old_version, '3.0') < 0) {
			$this->run_pre_data_update('3.0');
		}			
		
		arlo_add_datamodel();	
	
		if (version_compare($old_version, '2.2.1') < 0) {
			$this->do_update('2.2.1');
		}	
		
		if (version_compare($old_version, '2.3') < 0) {
			$this->do_update('2.3');
		}

		if (version_compare($old_version, '2.3.5') < 0) {
			$this->do_update('2.3.5');
		}
		
		if (version_compare($old_version, '2.4') < 0) {
			$this->do_update('2.4');
		}

		if (version_compare($old_version, '3.0') < 0) {
			$this->do_update('3.0');
		}		
	}
	
	private function run_pre_data_update($version) {
		
		switch($version) {
			case '2.4':
				$exists = $this->dbl->get_var("SHOW TABLES LIKE '" . $this->dbl->prefix . "arlo_log'", 0, 0);
				if (is_null($exists)) {
					$this->dbl->query("RENAME TABLE " . $this->dbl->prefix . "arlo_import_log TO " . $this->dbl->prefix . "arlo_log");
				}
				
				$exists = $this->dbl->get_var("SHOW TABLES LIKE '" . $this->dbl->prefix . "arlo_async_tasks'", 0, 0);
				if (!is_null($exists)) {
					$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_async_tasks CHANGE task_modified task_modified TIMESTAMP NULL DEFAULT NULL COMMENT 'Dates are in UTC';");
					$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_async_tasks CHANGE task_created task_created TIMESTAMP NULL DEFAULT NULL COMMENT 'Dates are in UTC';");
				}				
				
				$exists = $this->dbl->get_var("SHOW COLUMNS FROM " . $this->dbl->prefix . "arlo_eventtemplates_presenters LIKE 'et_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates_presenters CHANGE  et_arlo_id  et_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}
				
				$exists = $this->dbl->get_var("SHOW COLUMNS FROM " . $this->dbl->prefix . "arlo_events_tags LIKE 'e_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events_tags CHANGE  e_arlo_id  e_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}

				$exists = $this->dbl->get_var("SHOW COLUMNS FROM " . $this->dbl->prefix . "arlo_eventtemplates_tags LIKE 'et_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates_tags CHANGE  et_arlo_id  et_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}


				$exists = $this->dbl->get_var("SHOW COLUMNS FROM " . $this->dbl->prefix . "arlo_events_presenters LIKE 'e_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events_presenters CHANGE  e_arlo_id  e_id int( 11 ) NOT NULL DEFAULT  '0'");
						
				}				

				$exists = $this->dbl->get_var("SHOW KEYS FROM " . $this->dbl->prefix . "arlo_categories WHERE key_name = 'c_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_categories DROP KEY c_arlo_id ");	
				}

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_timezones DROP PRIMARY KEY, ADD PRIMARY KEY (id,active)");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events_presenters DROP PRIMARY KEY, ADD PRIMARY KEY (e_id,p_arlo_id,active)");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events_tags DROP PRIMARY KEY, ADD PRIMARY KEY (e_id,tag_id,active)");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates_tags DROP PRIMARY KEY, ADD PRIMARY KEY (et_id,tag_id,active)");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_categories DROP PRIMARY KEY, ADD PRIMARY KEY (c_id, active)");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates_categories DROP PRIMARY KEY, ADD PRIMARY KEY (et_arlo_id,c_arlo_id,active)");				
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_timezones_olson DROP PRIMARY KEY, ADD PRIMARY KEY (timezone_id,olson_name,active)");				
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates_presenters DROP PRIMARY KEY, ADD PRIMARY KEY (et_id,p_arlo_id,active)");
															
			break;

			case '2.4.1.1':
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_categories CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_contentfields CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates_categories CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_offers CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_onlineactivities CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_onlineactivities_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");				
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_timezones CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_timezones_olson CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_venues CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
			break;

			case '3.0':
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_async_tasks 
				CHANGE task_task task_task VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE task_status_text task_status_text VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_async_task_data 
				CHANGE data_text data_text TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_eventtemplates 
				CHANGE et_code et_code VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE et_post_name et_post_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE et_advertised_duration et_advertised_duration VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE et_region et_region VARCHAR(5) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE et_descriptionsummary et_descriptionsummary TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");
				
				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_contentfields 
				CHANGE cf_fieldname cf_fieldname VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_contenttype e_contenttype VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE cf_text cf_text TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events 
				CHANGE e_code e_code VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_name e_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_datetimeoffset e_datetimeoffset VARCHAR(6) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_timezone e_timezone VARCHAR(10) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_locationname e_locationname VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_locationroomname e_locationroomname VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_sessiondescription e_sessiondescription VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_credits e_credits VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_viewuri e_viewuri VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_registermessage e_registermessage VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_registeruri e_registeruri VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_providerorganisation e_providerorganisation VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_providerwebsite e_providerwebsite VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_region e_region VARCHAR(5) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE e_notice e_notice TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_events DROP e_summary;");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_onlineactivities 
				CHANGE oa_arlo_id oa_arlo_id VARCHAR(64) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_code oa_code VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_name oa_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_delivery_description oa_delivery_description VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_viewuri oa_viewuri VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_reference_terms oa_reference_terms VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_credits oa_credits VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_registermessage oa_registermessage VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_registeruri oa_registeruri VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE oa_region oa_region VARCHAR(5) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_venues 
				CHANGE v_name v_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdressline1 v_physicaladdressline1 VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdressline2 v_physicaladdressline2 VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdressline3 v_physicaladdressline3 VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdressline4 v_physicaladdressline4 VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdresssuburb v_physicaladdresssuburb VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdresscity v_physicaladdresscity VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdressstate v_physicaladdressstate VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdresspostcode v_physicaladdresspostcode VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_physicaladdresscountry v_physicaladdresscountry VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_viewuri v_viewuri VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_post_name v_post_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE v_facilityinfodirections v_facilityinfodirections TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ",
				CHANGE v_facilityinfoparking v_facilityinfoparking TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ",
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_presenters 
				CHANGE p_firstname p_firstname VARCHAR(64) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE p_lastname p_lastname VARCHAR(64) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE p_viewuri p_viewuri VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE p_twitterid p_twitterid VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE p_facebookid p_facebookid VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE p_linkedinid p_linkedinid VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE p_post_name p_post_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE p_profile p_profile TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ",
				CHANGE p_qualifications p_qualifications TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ",
				CHANGE p_interests p_interests TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ",
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");			

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_categories 
				CHANGE c_name c_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL DEFAULT '',
				CHANGE c_slug c_slug VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL DEFAULT '',
				CHANGE c_header c_header TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ",
				CHANGE c_footer c_footer TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ",
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_offers
				CHANGE o_label o_label VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE o_currencycode o_currencycode VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE o_formattedamounttaxexclusive o_formattedamounttaxexclusive VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE o_formattedamounttaxinclusive o_formattedamounttaxinclusive VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE o_taxrateshortcode o_taxrateshortcode VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE o_taxratename o_taxratename VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE o_region o_region VARCHAR(5) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE o_message o_message TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_tags 
				CHANGE tag tag VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");	

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_categories 
				CHANGE c_name c_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL DEFAULT '',
				CHANGE c_slug c_slug VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL DEFAULT '',
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");		

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_timezones 
				CHANGE name name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_timezones_olson 
				CHANGE olson_name olson_name VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_log 
				CHANGE message message TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ",
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");

				$this->dbl->query("ALTER TABLE " . $this->dbl->prefix . "arlo_messages 
				CHANGE title title VARCHAR(255) CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NULL DEFAULT NULL,
				CHANGE message message TEXT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . " NOT NULL,
				DEFAULT CHARACTER SET " . $this->dbl->charset  . " COLLATE " . $this->dbl->collate . ";");	
			break;					
		}
	}	
	
	private function do_update($version) {
		switch($version) {
			case '2.2.1': 
				//Add [arlo_no_event_text] shortcode to the templates
				$update_templates = ['eventsearch', 'events'];
				$saved_templates = arlo_get_option('templates');
				
				foreach ($update_templates as $id) {
					if (!empty($saved_templates[$id]['html'])) {
						$content = $saved_templates[$id]['html'];
						
						if (strpos($content, "arlo_no_event_text") === false) {
							$shortcode = "\n[arlo_no_event_text]\n";
							$append_after = "[arlo_category_footer]";						
						
							//try to find the [arlo_category_footer], and append before
							$pos = strpos($content, $append_after);
							if ($pos !== false) {
								$pos += strlen($append_after);
							} else {
								$pos = strlen($content);
							}
							
							$saved_templates[$id]['html'] = substr_replace($content, $shortcode, $pos, 0);
						}
					}
				}
				
				arlo_set_option('templates', $saved_templates);
			break;
			case '2.3': 
				$saved_templates = arlo_get_option('templates');

				//Add [arlo_template_region_selector] shortcode to the event template
				if (!empty($saved_templates['event']['html']) && strpos($saved_templates['event']['html'], "[arlo_template_region_selector]") === false) {
					$saved_templates['event']['html'] = "[arlo_template_region_selector]\n" . $saved_templates['event']['html'];
				}
				
				//Add [arlo_template_region_selector] shortcode to the catalogue template
				if (!empty($saved_templates['events']['html']) && strpos($saved_templates['event']['html'], "[arlo_template_region_selector]") === false) {
					$saved_templates['events']['html'] = "[arlo_template_region_selector]\n" . $saved_templates['events']['html'];
				}
								
				//Add [arlo_template_search_region_selector] shortcode to the event search template
				if (!empty($saved_templates['eventsearch']['html']) && strpos($saved_templates['event']['html'], "[arlo_template_search_region_selector]") === false) {
					$saved_templates['eventsearch']['html'] = "[arlo_template_search_region_selector]\n" . $saved_templates['eventsearch']['html'];
				}				

				//Add [arlo_upcoming_region_selector] shortcode to the upcoming events list template
				if (!empty($saved_templates['upcoming']['html']) && strpos($saved_templates['event']['html'], "[arlo_upcoming_region_selector]") === false) {
					$saved_templates['upcoming']['html'] = "[arlo_upcoming_region_selector]\n" . $saved_templates['upcoming']['html'];
				}
				
				arlo_set_option('templates', $saved_templates);
			break;
			
			case '2.3.5':
				wp_clear_scheduled_hook( 'arlo_import' );
				
				if ( ! wp_next_scheduled('arlo_scheduler')) {
					wp_schedule_event( time(), 'minutes_5', 'arlo_scheduler' );
				}

			break;
			
			case '2.4': 
				
				//Add [event_template_register_interest] shortcode to the event template
				$update_templates = ['event'];
				$saved_templates = arlo_get_option('templates');
				
				foreach ($update_templates as $id) {
					if (!empty($saved_templates[$id]['html'])) {
						$content = $saved_templates[$id]['html'];
						
						if (strpos($content, "[arlo_event_template_register_interest]") === false) {
							$shortcode = "\n[arlo_event_template_register_interest]\n";
							$append_before = [
								"[arlo_suggest_datelocation",
								"[arlo_content_field_item",
								"<h3>Similar courses",
							];
							foreach ($append_before as $target) {
								//try to find the given shortcode, and append before
								$pos = strpos($content, $target);
								if ($pos !== false) {
									break;
								}
							}
							
							if ($pos === false) {
								$pos = strlen($content);
							}
							
							$saved_templates[$id]['html'] = substr_replace($content, $shortcode, $pos, 0);
						}
					}
				}
				
				wp_clear_scheduled_hook( 'arlo_scheduler' );
				wp_schedule_event( time(), 'minutes_5', 'arlo_scheduler' );
				
				arlo_set_option('templates', $saved_templates);

				$this->plugin->change_setting('arlo_send_data', 1);

				if ($this->message_handler->get_message_by_type_count('information') == 0) {
					
					$message = [
					'<p>' . __('Arlo for WordPress will automatically send technical data to Arlo if problems are encountered when synchronising your event information. The data is sent securely and will help our team when providing support for this plugin. You can turn this off anytime in the', 'arlo-for-wordpress' ) . ' <a href="?page=arlo-for-wordpress#misc" class="arlo-settings-link" id="settings_misc">' . __('setting', 'arlo-for-wordpress' ) . '</a>.</p>',
					'<p><a target="_blank" class="button button-primary" id="arlo_turn_off_send_data">' . __('Turn off', 'arlo-for-wordpress' ) . '</a></p>'
					];
					
					$this->message_handler->set_message('information', __('Send error data to Arlo', 'arlo-for-wordpress' ), implode('', $message), false);
				}

				if ( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ) {
					$message = [
						'<p>' . __('Arlo for WordPress requires that the Cron feature in WordPress is enabled, or replaced with an external trigger.', 'arlo-for-wordpress' ) .' ' . sprintf(__('<a target="_blank" href="%s">View documentation</a> for more information.', 'arlo-for-wordpress' ), 'http://developer.arlo.co/doc/wordpress/import#import-wordpress-cron') . '</p>',
						'<p>' . __('You may safely dismiss this warning if your system administrator has installed an external Cron solution.', 'arlo-for-wordpress' ) . '</p>'
					];
			
					$this->message_handler->set_message('error', __('WordPress Cron is disabled', 'arlo-for-wordpress' ), implode('', $message), false);
				}
				
			break;	
		}	
	}	
}
