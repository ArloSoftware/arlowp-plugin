<?php

namespace Arlo\Provisioning;

use Arlo\Logger;

class SchemaManager {

	const DB_SCHEMA_HASH = 'bd3433fc50d0f33591947e829b901f6483007777';
	const DB_SCHEMA_VERSION = '4.1.0';

	/* database layer */
	private $dbl;

	private $message_handler;
	private $plugin;

	public function __construct($dbl, $message_handler, $plugin) {
		$this->dbl = &$dbl;
		$this->message_handler = $message_handler;
		$this->plugin = $plugin;
	}

	public function create_db_schema_hash( ) {
		$scheme = [];

		$tables = $this->dbl->get_results("SHOW TABLES like '%arlo%'", ARRAY_N);

		foreach ($tables as $table) {
			$field_defs = $this->dbl->get_results("SHOW COLUMNS FROM " . $table[0], ARRAY_A);
			$fields = [];
			foreach ($field_defs as $fd) {

				if (strpos($fd['Type'], 'enum') !== false) {
					preg_match_all("/'(.*)'/sU", $fd['Type'], $matches);
					if (is_array($matches[1])) {
						sort($matches[1]);

						$fd['Type'] = "enum('" . implode("','", $matches[1]) . ")";
					}
				}

				$fields[$fd['Field']] = [
					'Type' => $fd['Type'], 
					'Key' => $fd['Key'],
				];
			}
			ksort($fields);
			$scheme[$table[0]] = $fields;
		}
		ksort($scheme);

		return hash('sha1', json_encode($scheme));
	}

	public function check_db_schema() {
		if ($this->create_db_schema_hash() !== self::DB_SCHEMA_HASH) {

			//delete tables and re-create them
			$this->delete_tables();
			$this->install_schema();

			$message = [
				'<p>' . __('Arlo for WordPress has detected that there may be a problem with the structure of event information in your database. Event information is being repaired with a new copy.', 'arlo-for-wordpress' ) . '</p>',
				'<p>' . __('This repair may take a few minutes, and during this time information about your events will be temporarily unavailable for visitors on your site.', 'arlo-for-wordpress' ) . '</p>',
				'<p>' . sprintf(__('You can monitor the progress of the new import at the <a href="%s">Arlo Settings</a> page.', 'arlo-for-wordpress'), admin_url( 'admin.php?page=' . $this->plugin->plugin_slug)) . '</p>'
			 ];
			 
			$this->message_handler->set_message('error', __('Plugin upgrade warning', 'arlo-for-wordpress' ), implode('', $message), true);
			
			//kick off an import
			if (get_option('arlo_import_disabled', '0') != '1')
				$this->plugin->get_scheduler()->set_task("import", -1);	

			Logger::log("The current database shema could be wrong");
		 }
	}	

	public function check_db_version($current_version) {
		return version_compare($current_version, self::DB_SCHEMA_VERSION);
	}

	public function install_schema() {
		$this->dbl->suppress_errors(false);

		$this->dbl->query('START TRANSACTION');

		$this->install_table_arlo_async_tasks();
		$this->install_table_arlo_eventtemplate();
		$this->install_table_arlo_contentfields();
		$this->install_table_arlo_tags();
		$this->install_table_arlo_events();
		$this->install_table_arlo_onlineactivities();
		$this->install_table_arlo_venues();
		$this->install_table_arlo_presenters();
		$this->install_table_arlo_offers();
		$this->install_table_arlo_eventtemplates_presenters();
		$this->install_table_arlo_events_presenters();
		$this->install_table_arlo_log();
		$this->install_table_arlo_import();
		$this->install_table_arlo_import_parts();
		$this->install_table_arlo_import_lock();
		$this->install_table_arlo_categories();
		$this->install_table_arlo_eventtemplates_categories();
		$this->install_table_arlo_timezones();
		$this->install_table_arlo_messages();

		$this->dbl->query('COMMIT');

		return;
	}

	private function install_table_arlo_async_tasks() {	
		$table_name = $this->dbl->prefix . "arlo_async_tasks";

		$sql = "CREATE TABLE " . $table_name . " (
		task_id int(11) NOT NULL AUTO_INCREMENT,
		task_priority tinyint(4) NOT NULL DEFAULT '0',
		task_task varchar(255) DEFAULT NULL,
		task_status tinyint(4) NOT NULL DEFAULT '0' COMMENT '0:scheduled, 1:paused, 2:in_progress, 3: failed, 4: completed',
		task_status_text varchar(255) DEFAULT NULL,
		task_created timestamp NULL DEFAULT NULL COMMENT 'Dates are in UTC',
		task_modified timestamp NULL DEFAULT NULL COMMENT 'Dates are in UTC',
		PRIMARY KEY  (task_id),
		KEY task_status (task_status),
		KEY task_priority (task_priority)
		) " . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
		
		$sql = "
		CREATE TABLE " . $this->dbl->prefix . "arlo_async_task_data (
		data_task_id int(11) NOT NULL,
		data_text text NOT NULL,
		PRIMARY KEY  (data_task_id)
		) " . $this->dbl->charset_collate . "";
		
		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_eventtemplate() {	
		$table_name = $this->dbl->prefix . "arlo_eventtemplates";

		$sql = "CREATE TABLE " . $table_name . " (
			et_id int(11) NOT NULL AUTO_INCREMENT,
			et_arlo_id int(11) NOT NULL,
			et_code varchar(255) NULL,
			et_name varchar(255) NULL,
			et_descriptionsummary text NULL,
			et_post_name varchar(255) NULL,
			et_post_id int(10) unsigned DEFAULT NULL, 
			et_advertised_duration varchar(255) NULL,
			import_id int(10) unsigned DEFAULT NULL,
			et_registerinteresturi text NULL,
			et_registerprivateinteresturi text NULL,
			et_credits varchar(255) NULL,
			et_viewuri text NULL,
			et_hero_image text NULL,
			et_list_image text NULL,
			et_region varchar(5) NULL,
			PRIMARY KEY  (et_id),
			KEY et_post_id (et_post_id), 
			KEY et_arlo_id (et_arlo_id),
			KEY et_region (et_region))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_contentfields() {	
		$table_name = $this->dbl->prefix . "arlo_contentfields";

		$sql = "CREATE TABLE " . $table_name . " (
			cf_id int(11) NOT NULL AUTO_INCREMENT,
			et_id int(11) NOT NULL,
			cf_fieldname varchar(255) NULL,
			cf_text text NULL,
			cf_order int(11) NULL,
			e_contenttype varchar(255) NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (cf_id),
			KEY cf_order (cf_order),
			KEY et_id (et_id))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_events() {	
		$table_name = $this->dbl->prefix . "arlo_events";

		$sql = "CREATE TABLE " . $table_name . " (
			e_id int(11) NOT NULL AUTO_INCREMENT,
			e_arlo_id int(11) NOT NULL,
			et_arlo_id int(11) NULL,
			e_code varchar(255) NULL,
			e_name varchar(255) NULL,
			e_startdatetime DATETIME NOT NULL,
			e_finishdatetime DATETIME NULL,
			e_startdatetimeoffset varchar(6) NOT NULL,
			e_finishdatetimeoffset varchar(6) NULL,
			e_starttimezoneabbr varchar(7) NOT NULL,
			e_finishtimezoneabbr varchar(7) NULL,
			e_timezone_id int(11) NULL,
			v_id int(11) NULL,
			e_locationname varchar(255) NULL,
			e_locationroomname varchar(255) NULL,
			e_locationvisible tinyint(1) NOT NULL DEFAULT '0',
			e_isfull tinyint(1) NOT NULL DEFAULT FALSE,
			e_placesremaining int(11) NULL,
			e_sessiondescription varchar(255) NULL,
			e_summary text NULL,
			e_notice text NULL,
			e_credits varchar(255) NULL,
			e_viewuri varchar(255) NULL,
			e_registermessage varchar(255) NULL,
			e_registeruri varchar(255) NULL,
			e_providerorganisation varchar(255) NULL,
			e_providerwebsite varchar(255) NULL,
			e_isonline tinyint(1) NOT NULL DEFAULT FALSE,
			e_is_taxexempt tinyint(1) NOT NULL DEFAULT FALSE,
			e_parent_arlo_id int(11) NOT NULL,
			e_region varchar(5) NOT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (e_id),
			KEY et_arlo_id (et_arlo_id),
			KEY e_arlo_id (e_arlo_id),
			KEY e_region (e_region),
			KEY e_is_taxexempt (e_is_taxexempt),
			KEY v_id (v_id))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_onlineactivities() {	
		$table_name = $this->dbl->prefix . "arlo_onlineactivities";

		$sql = "CREATE TABLE " . $table_name . " (
			oa_id int(11) NOT NULL AUTO_INCREMENT,
			oat_arlo_id int(11) NULL,
			oa_arlo_id varchar(64) NOT NULL,
			oa_code varchar(255) NULL,
			oa_name varchar(255) NULL,
			oa_delivery_description varchar(255) NULL,
			oa_viewuri varchar(255) NULL,
			oa_reference_terms varchar(255) NULL,
			oa_credits varchar(255) NULL,		
			oa_registermessage varchar(255) NULL,
			oa_registeruri varchar(255) NULL,
			oa_region varchar(5) NOT NULL,		
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (oa_id),
			KEY oat_arlo_id (oat_arlo_id),
			KEY oa_region (oa_region))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_venues() {	
		$table_name = $this->dbl->prefix . "arlo_venues";

		$sql = "CREATE TABLE " . $table_name . " (
			v_id int(11) NOT NULL AUTO_INCREMENT,
			v_arlo_id int(11) NOT NULL,
			v_name varchar(255) NULL,
			v_locationname varchar(255) NULL,
			v_geodatapointlatitude DECIMAL(10,6) NULL,
			v_geodatapointlongitude DECIMAL(10,6) NULL,
			v_physicaladdressline1 varchar(255) NULL,
			v_physicaladdressline2 varchar(255) NULL,
			v_physicaladdressline3 varchar(255) NULL,
			v_physicaladdressline4 varchar(255) NULL,
			v_physicaladdresssuburb varchar(255) NULL,
			v_physicaladdresscity varchar(255) NULL,
			v_physicaladdressstate varchar(255) NULL,
			v_physicaladdresspostcode varchar(255) NULL,
			v_physicaladdresscountry varchar(255) NULL,
			v_viewuri varchar(255) NULL,
			v_facilityinfodirections text NULL,
			v_facilityinfoparking text NULL,
			v_post_name varchar(255) NULL,
			v_post_id int(10) unsigned DEFAULT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (v_id),
			KEY v_arlo_id (v_arlo_id),
			KEY v_post_id (v_post_id))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_presenters() {	
		$table_name = $this->dbl->prefix . "arlo_presenters";

		$sql = "CREATE TABLE " . $table_name . " (
			p_id int(11) NOT NULL AUTO_INCREMENT,
			p_arlo_id int(11) NOT NULL,
			p_firstname varchar(64) NULL,
			p_lastname varchar(64) NULL,
			p_viewuri varchar(255) NULL,
			p_profile text NULL,
			p_qualifications text NULL,
			p_interests text NULL,
			p_twitterid varchar(255) NULL,
			p_facebookid varchar(255) NULL,
			p_linkedinid varchar(255) NULL,
			p_post_name varchar(255) NULL,
			p_post_id int(10) unsigned DEFAULT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (p_id),
			KEY p_arlo_id (p_arlo_id),
			KEY p_post_id (p_post_id))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_offers() {	
		$table_name = $this->dbl->prefix . "arlo_offers";

		$sql = "CREATE TABLE " . $table_name . " (
			o_id int(11) NOT NULL AUTO_INCREMENT,
			o_arlo_id INT,
			et_id INT,
			e_id INT,
			oa_id INT,
			o_label varchar(255) NULL,
			o_isdiscountoffer tinyint(1) NOT NULL DEFAULT FALSE,
			o_currencycode varchar(255) NULL,
			o_offeramounttaxexclusive DECIMAL(15,2) NULL,
			o_offeramounttaxinclusive DECIMAL(15,2) NULL,
			o_formattedamounttaxexclusive varchar(255) NULL,
			o_formattedamounttaxinclusive varchar(255) NULL,
			o_taxrateshortcode varchar(255) NULL,
			o_taxratename varchar(255) NULL,
			o_taxratepercentage DECIMAL(3,2) NULL,
			o_message text NULL,
			o_order int(11) NULL,
			o_replaces int(11) NULL,
			o_region varchar(5) NOT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (o_id),
			KEY o_arlo_id (o_arlo_id),
			KEY et_id (et_id),
			KEY e_id (e_id),
			KEY oa_id (oa_id),
			KEY o_region (o_region),
			KEY o_order (o_order))
			" . $this->dbl->charset_collate . "";
		
		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_eventtemplates_presenters() {	
		$table_name = $this->dbl->prefix . "arlo_eventtemplates_presenters";
		
		$sql = "CREATE TABLE " . $table_name . " (
			et_id int(11) NOT NULL,
			p_arlo_id int(11) NOT NULL,
			p_order int(11) NULL COMMENT 'Order of the presenters for the event template.',
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (et_id, p_arlo_id, import_id),
			KEY cf_order (p_order),
			KEY fk_et_id_idx (et_id ASC),
			KEY fk_p_id_idx (p_arlo_id ASC))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_tags() {	
		$sql = "CREATE TABLE " . $this->dbl->prefix . "arlo_tags (
			id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			tag varchar(255) NOT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (id)) " . $this->dbl->charset_collate . "";
			
		$this->dbl->sync_schema($sql);
		
		$sql = "CREATE TABLE " . $this->dbl->prefix . "arlo_events_tags (
			e_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (e_id, tag_id, import_id)) " . $this->dbl->charset_collate . "";
			
		$this->dbl->sync_schema($sql);  	
		
		$sql = "CREATE TABLE " . $this->dbl->prefix . "arlo_onlineactivities_tags (
			oa_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (oa_id, tag_id, import_id)) " . $this->dbl->charset_collate . "";
			
		$this->dbl->sync_schema($sql);	
		
		$sql = "CREATE TABLE " . $this->dbl->prefix . "arlo_eventtemplates_tags (
			et_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (et_id, tag_id, import_id)) " . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_events_presenters() {	
		$table_name = $this->dbl->prefix . "arlo_events_presenters";
		
		$sql = "CREATE TABLE " . $table_name . " (
			e_id int(11) NOT NULL,
			p_arlo_id int(11) NOT NULL,
			p_order int(11) NULL COMMENT 'Order of the presenters for the event.',
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (e_id, p_arlo_id, import_id),		
			KEY fk_e_id_idx (e_id ASC),
			KEY fk_p_id_idx (p_arlo_id ASC))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_categories() {	
		$table_name = $this->dbl->prefix . "arlo_categories";
		
		$sql = "CREATE TABLE " . $table_name . " (
			c_id int(11) NOT NULL AUTO_INCREMENT,
			c_arlo_id int(11) NOT NULL,
			c_name varchar(255) NOT NULL DEFAULT '',
			c_slug varchar(255) NOT NULL DEFAULT '',
			c_header TEXT,
			c_footer TEXT,
			c_template_num SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			c_order BIGINT(20) DEFAULT NULL,
			c_depth_level tinyint(3) unsigned NOT NULL DEFAULT '0',
			c_parent_id int(11) DEFAULT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (c_id, import_id),
			UNIQUE KEY c_arlo_id_key (c_arlo_id,import_id),
			KEY c_parent_id (c_parent_id))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_eventtemplates_categories() {	
		$table_name = $this->dbl->prefix . "arlo_eventtemplates_categories";

		$sql = "CREATE TABLE " . $table_name . " (
			et_arlo_id int(11) NOT NULL,
			c_arlo_id int(11) NOT NULL,
			et_order SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (et_arlo_id, c_arlo_id, import_id),
			KEY fk_et_id_idx (et_arlo_id ASC),
			KEY fk_c_id_idx (c_arlo_id ASC))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_timezones() {	
		$table_name = $this->dbl->prefix . "arlo_timezones";

		$sql = "
			CREATE TABLE " . $table_name . " (
			id int(11) NOT NULL,
			name varchar(256) NOT NULL,
			windows_tz_id varchar(256) NOT NULL,
			utc_offset int(11) NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (id, import_id)) " . $this->dbl->charset_collate . ";";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_log() {	
		$table_name = $this->dbl->prefix . "arlo_log";

		$sql = "CREATE TABLE $table_name (
			id int(11) unsigned NOT NULL AUTO_INCREMENT,
			import_id int(11) unsigned NULL,
			message TEXT,
			created DATETIME DEFAULT NULL COMMENT 'in UTC',
			successful tinyint(1) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY import_id (import_id)) 
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_import_lock() {	
		$table_name = $this->dbl->prefix . "arlo_import_lock";
			
		$sql = "CREATE TABLE $table_name (
			import_id int(10) unsigned NOT NULL,
			lock_acquired DATETIME NOT NULL,
			lock_expired DATETIME NOT NULL
			) " . $this->dbl->charset_collate . "";
		
		$this->dbl->sync_schema($sql);
	}

	private function install_table_arlo_import() {	
		$table_name = $this->dbl->prefix . "arlo_import";
			
		$sql = "CREATE TABLE $table_name (
			  	id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
				request_id varchar(63) NOT NULL,  
				import_id int(10) unsigned NOT NULL,
				fragmented bit(1) NOT NULL DEFAULT b'1', 
				response_json text NULL DEFAULT NULL,
				callback_json text NULL DEFAULT NULL,
				nonce varchar(63) NOT NULL,
				type enum('full') NOT NULL DEFAULT 'full',
				created datetime NOT NULL COMMENT 'in UTC',
				modified datetime DEFAULT NULL COMMENT 'in UTC',
				expired datetime NOT NULL COMMENT 'in UTC',
				PRIMARY KEY  (id)
			) " . $this->dbl->charset_collate . "";
		
		$this->dbl->sync_schema($sql);        
	}

	private function install_table_arlo_import_parts() {	
		$table_name = $this->dbl->prefix . "arlo_import_parts";
			
		$sql = "CREATE TABLE $table_name (
			  	id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				import_id INT(10) UNSIGNED NOT NULL,
				part ENUM('image', 'fragment') NOT NULL,
				iteration SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
				import_text LONGTEXT NULL DEFAULT NULL,
				created datetime NOT NULL,
				modified datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id)
			) " . $this->dbl->charset_collate . "";
		
		$this->dbl->sync_schema($sql);        
	}

	private function install_table_arlo_messages() {	
		$table_name = $this->dbl->prefix . "arlo_messages";

		$sql = "CREATE TABLE $table_name (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			type enum('import_error', 'information', 'error', 'review') DEFAULT NULL,
			title varchar(255) DEFAULT NULL,
			message text NOT NULL,
			global tinyint(1) DEFAULT 0,
			dismissed timestamp NULL DEFAULT NULL,
			dismissed_by int(10) unsigned NULL DEFAULT NULL,
			created timestamp NULL DEFAULT NULL,
			PRIMARY KEY (id),
			KEY type (type))
			" . $this->dbl->charset_collate . "";

		$this->dbl->sync_schema($sql);
	}

	public function delete_tables() {
		//should be used in the uninstall.php
		$sql="
			DROP TABLE IF EXISTS " .
				$this->dbl->prefix . "arlo_async_tasks," .
				$this->dbl->prefix . "arlo_async_task_data," . 
				$this->dbl->prefix . "arlo_categories," . 
				$this->dbl->prefix . "arlo_contentfields, " . 
				$this->dbl->prefix . "arlo_events, " . 		
				$this->dbl->prefix . "arlo_events_presenters, " . 
				$this->dbl->prefix . "arlo_eventtemplates," . 
				$this->dbl->prefix . "arlo_eventtemplates_categories," . 		
				$this->dbl->prefix . "arlo_eventtemplates_presenters, " .
				$this->dbl->prefix . "arlo_onlineactivities, " . 
				$this->dbl->prefix . "arlo_onlineactivities_tags, " .
				$this->dbl->prefix . "arlo_offers, " . 		
				$this->dbl->prefix . "arlo_presenters, " . 
				$this->dbl->prefix . "arlo_venues, " . 
				$this->dbl->prefix . "arlo_events_tags, " . 
				$this->dbl->prefix . "arlo_eventtemplates_tags,  " . 
				$this->dbl->prefix . "arlo_tags,  " . 
				$this->dbl->prefix . "arlo_timezones,  " . 
				$this->dbl->prefix . "arlo_messages, " .
				$this->dbl->prefix . "arlo_log," .
				$this->dbl->prefix . "arlo_import," .
				$this->dbl->prefix . "arlo_import_parts," .
				$this->dbl->prefix . "arlo_import_lock";

		$this->dbl->query($sql);
	}
}