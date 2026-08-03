<?php

namespace ArloTraining;

use ArloTraining\Utilities;
#[\AllowDynamicProperties]
class VersionHandler {
	const VERSION = '5.1.0';

	private $message_handler;
	private $plugin;

	public function __construct($message_handler, $plugin, $theme_manager) {
		$this->message_handler = $message_handler;	
		$this->plugin = $plugin;
		$this->theme_manager = $theme_manager;
	}	

	public function get_current_installed_version () {
		return get_option('arlo_plugin_version');
	}

	public function set_installed_version() {
		$schema_manager = $this->plugin->get_schema_manager();
		update_option('arlo_plugin_version', self::VERSION);
				
		$now = \ArloTraining\Utilities::get_now_utc();
		update_option('arlo_updated', $now->format("Y-m-d H:i:s"));
		
		update_option('arlo_schema_version', $schema_manager::DB_SCHEMA_VERSION);
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
			$this->run_pre_data_update('2.4.1.1');
			$this->run_pre_data_update('3.0');
		}			
		
		if (version_compare($old_version, '3.6') < 0) {
			$this->run_pre_data_update('3.6');
		}		
		
		if (version_compare($old_version, '3.6.1') < 0) {
			$this->run_pre_data_update('3.6.1');
		}
		
		if (version_compare($old_version, '3.8') < 0) {
			$this->run_pre_data_update('3.8');
		}
		
		if (version_compare($old_version, '3.8.1') < 0) {
			$this->run_pre_data_update('3.8.1');
		}
		
		if (version_compare($old_version, '3.9') < 0) {
			$this->run_pre_data_update('3.9');
		}
		
		if (version_compare($old_version, '4.0') < 0) {
			$this->run_pre_data_update('4.0');
		}

		if (version_compare($old_version, '4.1') < 0) {
			$this->run_pre_data_update('4.1');
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

		if (version_compare($old_version, '3.1') < 0) {
			$this->do_update('3.1');
		}

		if (version_compare($old_version, '3.1.3') < 0) {
			$this->do_update('3.1.3');
		}

		if (version_compare($old_version, '3.2') < 0) {
			$this->do_update('3.2');
		}

		if (version_compare($old_version, '3.3') < 0) {
			$this->do_update('3.3');
		}
		
		if (version_compare($old_version, '3.5') < 0) {
			$this->do_update('3.5');
		}

		if (version_compare($old_version, '3.5.1') < 0) {
			$this->do_update('3.5.1');
		}

		if (version_compare($old_version, '3.6') < 0) {
			$this->do_update('3.6');
		}

		if (version_compare($old_version, '4.0') < 0) {
			$this->do_update('4.0');
		}

		if (version_compare($old_version, '5.1.0') < 0) {
			$this->do_update('5.1.0');
		}
	}
	
	private function run_pre_data_update($version) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Trusted Schema update. Direct database query is required for custom table. Do not need cache for schema update
		switch($version) {
			case '2.4':
				$exists = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "arlo_log'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("RENAME TABLE " . $wpdb->prefix . "arlo_import_log TO " . $wpdb->prefix . "arlo_log");
				}
				
				$exists = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "arlo_async_tasks'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks CHANGE task_modified task_modified TIMESTAMP NULL DEFAULT NULL COMMENT 'Dates are in UTC';");
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks CHANGE task_created task_created TIMESTAMP NULL DEFAULT NULL COMMENT 'Dates are in UTC';");
				}				
				
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates_presenters LIKE 'et_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters CHANGE  et_arlo_id  et_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}
				
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events_tags LIKE 'e_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_tags CHANGE  e_arlo_id  e_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}

				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates_tags LIKE 'et_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags CHANGE  et_arlo_id  et_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}


				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events_presenters LIKE 'e_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters CHANGE  e_arlo_id  e_id int( 11 ) NOT NULL DEFAULT  '0'");
						
				}

				$exists = $wpdb->get_var("SHOW KEYS FROM " . $wpdb->prefix . "arlo_categories WHERE key_name = 'c_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_categories DROP KEY c_arlo_id ");	
				}

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones DROP PRIMARY KEY, ADD PRIMARY KEY (id,active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters DROP PRIMARY KEY, ADD PRIMARY KEY (e_id,p_arlo_id,active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_tags DROP PRIMARY KEY, ADD PRIMARY KEY (e_id,tag_id,active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags DROP PRIMARY KEY, ADD PRIMARY KEY (et_id,tag_id,active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_categories DROP PRIMARY KEY, ADD PRIMARY KEY (c_id, active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_categories DROP PRIMARY KEY, ADD PRIMARY KEY (et_arlo_id,c_arlo_id,active)");				
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones_olson DROP PRIMARY KEY, ADD PRIMARY KEY (timezone_id,olson_name,active)");				
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters DROP PRIMARY KEY, ADD PRIMARY KEY (et_id,p_arlo_id,active)");
															
			break;

			case '2.4.1.1':
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_categories CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_contentfields CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_categories CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_offers CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_onlineactivities CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_onlineactivities_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");				
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones_olson CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_venues CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
			break;

			case '3.0':
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events DROP e_summary;");
				$wpdb->query("DROP TABLE " . $wpdb->prefix . "arlo_timezones_olson;");
			break;	

			case '3.6':
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_is_taxexempt'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_is_taxexempt TINYINT(1) NOT NULL DEFAULT '0' AFTER e_isonline");
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events ADD INDEX (e_is_taxexempt(1))");
				}
			break;

			case '3.6.1':
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events DROP e_timezone_id;");
				$wpdb->query("DROP TABLE " . $wpdb->prefix . "arlo_timezones;");
				$wpdb->query("CREATE TABLE " . $wpdb->prefix . "arlo_timezones (
					id int(11) NOT NULL,
					name varchar(256) NOT NULL,
					windows_tz_id varchar(256) NOT NULL,
					import_id int(10) unsigned NOT NULL,
					PRIMARY KEY  (id, import_id))
					" . $wpdb->get_charset_collate() . ";");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_timezone_id INT(11) NULL AFTER e_timezone");
			break;

			case '3.8':
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_summary'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_summary TEXT NULL AFTER e_sessiondescription");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates LIKE 'et_registerprivateinteresturi'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates ADD et_registerprivateinteresturi TEXT NULL AFTER et_registerinteresturi");
				}
			break;

			case '3.8.1':
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_async_tasks LIKE 'task_hostname'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks ADD task_hostname varchar(255) DEFAULT NULL AFTER task_status_text");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_async_tasks LIKE 'task_lb_count'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks ADD task_lb_count tinyint(4) NOT NULL DEFAULT '0' AFTER task_hostname");
				}
			break;

			case '3.9':
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_async_tasks LIKE 'task_lb_count'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks DROP task_lb_count");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_async_tasks LIKE 'task_hostname'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks DROP task_hostname");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates LIKE 'et_credits'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates ADD et_credits varchar(255) NULL AFTER et_registerprivateinteresturi");
				}
				$exists = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "arlo_import_parts'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("DROP TABLE " . $wpdb->prefix . "arlo_import_parts;");
				}
				$wpdb->query("CREATE TABLE " . $wpdb->prefix . "arlo_import_parts (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					import_id INT(10) UNSIGNED NOT NULL,
					part ENUM('image', 'fragment') NOT NULL,
					iteration SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
					import_text LONGTEXT NULL DEFAULT NULL,
					created datetime NOT NULL,
					modified datetime NULL DEFAULT NULL,
					PRIMARY KEY  (id))
					" . $wpdb->get_charset_collate() . ";");
			break;

			case '4.0':
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates LIKE 'et_hero_image'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates ADD et_hero_image text NULL AFTER et_viewuri");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates LIKE 'et_list_image'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates ADD et_list_image text NULL AFTER et_hero_image");
				}
			break;


			case '4.1':
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_timezones LIKE 'utc_offset'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones ADD utc_offset INT(11) NOT NULL AFTER windows_tz_id");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_finishtimezoneabbr'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_finishtimezoneabbr varchar(7) NULL AFTER e_finishdatetime");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_starttimezoneabbr'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_starttimezoneabbr varchar(7) NOT NULL AFTER e_finishdatetime");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_finishdatetimeoffset'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_finishdatetimeoffset varchar(6) NOT NULL AFTER e_finishdatetime");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_startdatetimeoffset'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_startdatetimeoffset varchar(6) NOT NULL AFTER e_finishdatetime");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_datetimeoffset'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events DROP e_datetimeoffset;");
				}
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_timezone'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events DROP e_timezone;");
				}

				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_venues LIKE 'v_locationname'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_venues ADD v_locationname text NULL AFTER v_name");
				}
			break;
		}
		// phpcs:enable
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
					'<p>' . esc_html__('Arlo for WordPress will automatically send technical data to Arlo if problems are encountered when synchronising your event information. The data is sent securely and will help our team when providing support for this plugin. You can turn this off anytime in the', 'arlo-training-and-event-management-system' ) . ' <a href="?page=arlo-for-wordpress#misc" class="arlo-settings-link" id="settings_misc">' . esc_html__('setting', 'arlo-training-and-event-management-system' ) . '</a>.</p>',
					'<p><a target="_blank" class="button button-primary" id="arlo_turn_off_send_data">' . esc_html__('Turn off', 'arlo-training-and-event-management-system' ) . '</a></p>'
					];
					
					$this->message_handler->set_message('information', esc_html__('Send error data to Arlo', 'arlo-training-and-event-management-system' ), implode('', $message), false);
				}

				if ( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ) {
					$message = [
						/* translators: %s: arlo for wordpress document link */
						'<p>' . esc_html__('Arlo for WordPress requires that the Cron feature in WordPress is enabled, or replaced with an external trigger.', 'arlo-training-and-event-management-system' ) .' ' . wp_kses(sprintf(__('<a target="_blank" href="%s">View documentation</a> for more information.', 'arlo-training-and-event-management-system' ), 'https://developer.arlo.co/doc/wordpress/import#import-wordpress-cron'), ['a'=>['href'=>[],'target'=>[]]]) . '</p>',
						'<p>' . esc_html__('You may safely dismiss this warning if your system administrator has installed an external Cron solution.', 'arlo-training-and-event-management-system' ) . '</p>'
					];
			
					$this->message_handler->set_message('error', esc_html__('WordPress Cron is disabled', 'arlo-training-and-event-management-system' ), implode('', $message), false);
				}
				
			break;	

			case '3.0':
				global $wpdb;
				$import_id = get_option('arlo_import_id','');
				if (!empty($import_id)) {
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Data migration. Direct database query is required for custom table. Do not need cache for one time data migration
					//update post_id in the templates table
					$items = $wpdb->get_results($wpdb->prepare("
					SELECT
						et_id,
						et_post_name
					FROM 
						{$wpdb->prefix}arlo_eventtemplates
					WHERE
						import_id = %d
					AND
						(et_post_id IS NULL OR et_post_id = 0)
					", $import_id));
					if (is_array($items) && count($items)) {
						foreach($items as $key => $item) {
							$post = arlo_get_post_by_name($item->et_post_name, 'arlo_event');
							if (!is_null($post) && !empty($post->ID) && $post->ID > 0) {
								
								$wpdb->update($wpdb->prefix . 'arlo_eventtemplates', array( 'et_post_id' => $post->ID), array( 'et_id' => $item->et_id ));
							}
						}
					}

					//update post_id in the presenters table
					$items = $wpdb->get_results($wpdb->prepare("
					SELECT
						p_id,
						p_post_name
					FROM 
						{$wpdb->prefix}arlo_presenters
					WHERE
						import_id = %d
					AND
						(p_post_id IS NULL OR p_post_id = 0)
					", $import_id));
					if (is_array($items) && count($items)) {
						foreach($items as $key => $item) {
							$post = arlo_get_post_by_name($item->p_post_name, 'arlo_presenter');
							if (!is_null($post) && !empty($post->ID) && $post->ID > 0) {
								
								$wpdb->update($wpdb->prefix . 'arlo_presenters', array( 'p_post_id' => $post->ID), array( 'p_id' => $item->p_id ));
							}
						}
					}

					//update post_id in the venues table
					$items = $wpdb->get_results($wpdb->prepare("
					SELECT
						v_id,
						v_post_name
					FROM 
						{$wpdb->prefix}arlo_venues
					WHERE
						import_id = %d
					AND
						(v_post_id IS NULL OR v_post_id = 0)
					", $import_id));
					if (is_array($items) && count($items)) {
						foreach($items as $key => $item) {
							$post = arlo_get_post_by_name($item->v_post_name, 'arlo_venue');
							if (!is_null($post) && !empty($post->ID) && $post->ID > 0) {
								
								$wpdb->update($wpdb->prefix . 'arlo_venues', array( 'v_post_id' => $post->ID), array( 'v_id' => $item->v_id ));
							}
						}
					}
					// phpcs:enable
				}

				$theme_id = 'custom';
				update_option('arlo_theme', $theme_id, 1);

				$settings = get_option('arlo_settings');

				$theme_settings = $this->theme_manager->get_themes_settings();

				$stored_themes_settings[$theme_id] = $theme_settings[$theme_id];
				$stored_themes_settings[$theme_id]->templates = $this->theme_manager->load_default_templates($theme_id);

				foreach ($settings['templates'] as $page => $template) {
					$stored_themes_settings[$theme_id]->templates[$page]['html'] = $settings['templates'][$page]['html'];
				}

				update_option('arlo_themes_settings', $stored_themes_settings, 1);

				//Add [arlo_powered_by] shortcode to the event template
				$saved_templates = arlo_get_option('templates');
				
				foreach ($saved_templates as $id => $content) {
					if (!empty($content['html'])) {
						
						if (strpos($content['html'], "[arlo_powered_by]") === false) {
							$shortcode = "\n[arlo_powered_by]\n";

							$saved_templates[$id]['html'] = $content['html'] . $shortcode;
						}
					}
				}

				arlo_set_option('templates', $saved_templates);	

				//kick off an import
				if (get_option('arlo_import_disabled', '0') != '1')
					$this->plugin->get_scheduler()->set_task("import", -1);					
			break;

			case '3.1':
				delete_metadata('user', 0 , 'arlo-developer-admin-notice', '0', true);
			break;

			case '3.1.3':
				//kick off an import
				if (get_option('arlo_import_disabled', '0') != '1')
					$this->plugin->get_scheduler()->set_task("import", -1);	
			break;
			case '3.2':
				//try to add OA page (don't publish)
				$page_name = 'oa';

				$page_ids = Arlo_For_Wordpress::add_pages($page_name);
			break;
			case '3.3':
				$theme_settings = get_option( 'arlo_themes_settings', [] );
				$settings = get_option( 'arlo_settings', [] );
				$regions = get_option('arlo_regions');

				//Add Schedule template and OA template
				$page_name = 'schedule';
				$page_ids = Arlo_For_Wordpress::add_pages($page_name);	

				$selected_theme_id = get_option( 'arlo_theme' );

				foreach ($theme_settings as $theme_name => $theme_setting) {					
					if ($selected_theme_id !== 'custom') {
						$oa_template = file_get_contents($theme_settings[$theme_name]->dir . '/templates/oa.tpl');
						
						if (!empty($oa_template)) {
							if (!array_key_exists('oa',$theme_setting->templates)) {
								$theme_settings[$theme_name]->templates['oa'] = array( 'html' => $oa_template );
							}

							if ($theme_name == $selected_theme_id) {
								if (!array_key_exists('oa',$settings['templates'])) {
									$settings['templates']['oa'] = array( 'html' => $oa_template );
								}
							}
						}

						$schedule_template = file_get_contents($theme_settings[$theme_name]->dir . '/templates/schedule.tpl');
						
						if (!empty($schedule_template)) {
							if (!array_key_exists('schedule',$theme_setting->templates)) {
								$theme_settings[$theme_name]->templates['schedule'] = array( 'html' => $schedule_template );
							}

							if ($theme_name == $selected_theme_id) {
								if (!array_key_exists('schedule',$settings['templates'])) {
									$settings['templates']['schedule'] = array( 'html' => $schedule_template );
								}
							}
						}
					}
				}

				$settings['regionid'] = array_map('strtoupper',$settings['regionid']);
				$regions = array_change_key_case($regions, CASE_UPPER);

				//Add rich snippet shortcodes to templates
				$settings = $this->update_template($settings, 'event',false,"[arlo_event_template_rich_snippet]");
				$settings = $this->update_template($settings, 'event','[/arlo_event_list_item]',"[arlo_event_rich_snippet]");
				$settings = $this->update_template($settings, 'event','[/arlo_oa_list_item]',"[arlo_oa_rich_snippet]");

				$settings = $this->update_template($settings, 'presenter',false,"[arlo_presenter_rich_snippet]");

				$settings = $this->update_template($settings, 'presenters','[/arlo_presenter_list_item]',"[arlo_presenter_rich_snippet]");

				$settings = $this->update_template($settings, 'venue',false,"[arlo_venue_rich_snippet]");

				$settings = $this->update_template($settings, 'venues','[/arlo_venue_list_item]',"[arlo_venue_rich_snippet]");

				$settings = $this->update_template($settings, 'events','[/arlo_event_template_list_item]',"[arlo_event_template_rich_snippet]");

				$settings = $this->update_template($settings, 'eventsearch','[/arlo_event_template_list_item]',"[arlo_event_template_rich_snippet]");

				$settings = $this->update_template($settings, 'upcoming','[/arlo_upcoming_list_item]',"[arlo_event_rich_snippet]");

				$settings = $this->update_template($settings, 'oa','[/arlo_onlineactivites_list_item]',"[arlo_oa_rich_snippet]");


				update_option('arlo_themes_settings', $theme_settings);
				update_option('arlo_settings', $settings);
				update_option('arlo_regions', $regions);
			break;

			case '3.5':
				//update filter settings, if there is
				$filter_settings = get_option('arlo_filter_settings', []);

				if (isset($filter_settings['template'])) {
					$filter_settings['events'] = $filter_settings['template'];
					unset($filter_settings['template']);					
				}

				if (isset($filter_settings['arlohiddenfilters']) && isset($filter_settings['arlohiddenfilters']['template'])) {
					$filter_settings['arlohiddenfilters']['events'] = $filter_settings['arlohiddenfilters']['template'];
					unset($filter_settings['arlohiddenfilters']['template']);					
				}

				update_option('arlo_filter_settings', $filter_settings);

				$this->plugin->set_review_notice_date('now');
			break;

			case '3.5.1':
				//delete cookies
				$urlparts = wp_parse_url(site_url());
				$domain = $urlparts['host'];

				unset( $_COOKIE['arlo-nav-tab'] );
				setcookie('arlo-nav-tab', '', -1, '/', $domain);

				unset( $_COOKIE['arlo-vertical-tab'] );
				setcookie('arlo-vertical-tab', '', -1, '/', $domain);
				
			break;			

			case '3.6':
				//update filter settings, if there is
				$filter_settings = get_option('arlo_filter_settings', []);

				//use numeric key for filters, etc
				$new_settings_array = [];
				foreach ($filter_settings as $page => $filter_options) {
					if ($page == 'arlohiddenfilters') {
						continue;
					}
		
					if (!isset($new_settings_array[$page])) {
						$new_settings_array[$page] = [];
					}
		
					foreach ($filter_options as $group => $filters) {
						if (!isset($new_settings_array[$page][$group])) {
							$new_settings_array[$page][$group] = [];
						}
		
						switch ($group) {
							case 'category':
							break;
		
							case 'delivery':
								foreach ($filters as $old_value => $new_value) {
									$delivery_key = array_search($old_value, \Arlo_For_Wordpress::$delivery_labels);
									if ($delivery_key !== false && !empty($new_value))
										$new_settings_array[$page][$group][$delivery_key] = $new_value;
								}
							break;
							default: 
								foreach ($filters as $old_value => $new_value) {
									if (!empty($new_value))
										$new_settings_array[$page][$group][$old_value] = $new_value;
									
								}
							break;
						}
					}
				}
				if (isset($filter_settings['arlohiddenfilters']) && is_array($filter_settings['arlohiddenfilters'])) {
					foreach ($filter_settings['arlohiddenfilters'] as $page => $filter_options) {		
						if (!isset($new_settings_array['hiddenfilters'][$page])) {
							$new_settings_array['hiddenfilters'][$page] = [];
						}
			
						foreach ($filter_options as $group => $filters) {
							if (!isset($new_settings_array['hiddenfilters'][$page][$group])) {
								$new_settings_array['hiddenfilters'][$page][$group] = [];
							}
			
							switch ($group) {
								case 'category':
								break;
			
								case 'delivery':
									foreach ($filters as $filter) {
										$delivery_key = array_search($filter, \Arlo_For_Wordpress::$delivery_labels);
										if ($delivery_key !== false)
											$new_settings_array['hiddenfilters'][$page][$group][] = $delivery_key;
									}
								break;
								default: 
									foreach ($filters as $filter) {
										if (!empty($filter))
											$new_settings_array['hiddenfilters'][$page][$group][] = $filter;
										
									}
								break;
							}
						}
					}
				}
				

				//now remove by-page filters and only keep hidden and delivery
				$filter_settings = $new_settings_array;
				$is_notice_required = false;
				$filter_parts = ['showonlyfilters', 'hiddenfilters', 'generic'];

				//create new generic section
				foreach ($filter_settings as $page => $filter_options) {
					if (!in_array($page, $filter_parts)) {
						foreach ($filter_options as $group => $filters) {
							//copy first delivery filters
							if ($group == 'delivery' && empty($filter_settings['generic'])) {
								$filter_settings['generic'] = array('delivery' => $filters);
								break;
							}
						}
					}
				}

				//remove obsolete filter settings
				foreach ($filter_settings as $page => $filter_options) {
					if (!in_array($page, $filter_parts)) {
						//remove the whole page
						unset($filter_settings[$page]);
						$is_notice_required = true;
					} else {
						foreach ($filter_options as $group => $filters) {
							//remove all non-delivery groups
							if ($group != 'delivery') {
								unset($filter_settings[$page][$group]);
							}
						}
					}
				}

				if ($is_notice_required) {
					$message = [
						'<p>'. esc_html__('The Filters tab has been removed and previous filter settings are no longer available. The delivery filter settings are now configured from the General Settings tab and apply to all Arlo for WordPress pages. Locations, tags and categories should be managed from the Arlo management platform.', 'arlo-training-and-event-management-system' ) . '</p>'
					];
					if (!$this->message_handler->set_message('import_error', esc_html__('Filter settings deleted', 'arlo-training-and-event-management-system' ), implode('', $message), true)) {
						Logger::log("Couldn't create Arlo 3.6 filters settings lost notice message");
					}
				}

				update_option('arlo_filter_settings', $filter_settings);
			break;

			case '4.0':
				$settings = get_option('arlo_settings');
				$settings['keep_settings'] = "1";
				update_option('arlo_settings', $settings);
			break;

			case '5.1.0':
				// Backfill deployment_mode introduced in 5.1.0.
				$settings = get_option('arlo_settings');
				if (is_array($settings) && !isset($settings['deployment_mode'])) {
					$settings['deployment_mode'] = \Arlo_For_Wordpress::get_deployment_mode_default($settings['platform_name'] ?? '');
					update_option('arlo_settings', $settings);
				}
			break;

		}	
	}

	private function update_template($templates, $page, $insert_before, $shortcode) {
		if (!empty($templates['templates'][$page]['html']) && strpos($templates['templates'][$page]['html'], $shortcode) === false) {
			$shortcode = "\n".$shortcode."\n";

			if ($insert_before) {
				$pos = strpos($templates['templates'][$page]['html'],$insert_before);
				$templates['templates'][$page]['html'] = substr_replace($templates['templates'][$page]['html'], $shortcode, $pos, 0);
			} else {
				$templates['templates'][$page]['html'] = $templates['templates'][$page]['html'] . $shortcode;
			}
		}

		return $templates;
	}
}
