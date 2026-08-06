<?php

namespace ArloTraining\Provisioning;

use ArloTraining\Logger;

class SchemaManager {

	const DB_SCHEMA_HASH = '6dd7d709f19e44104c3a2f98c95884c7315c86b8';
	const DB_SCHEMA_VERSION = '4.1.0';

	private $message_handler;
	private $plugin;

	public function __construct($message_handler, $plugin) {
		$this->message_handler = $message_handler;
		$this->plugin = $plugin;
	}

	public static function get_schema_upgrade_warning_title() {
		return __( 'Plugin upgrade warning', 'arlo-training-and-event-management-system' );
	}

	/**
	 * Returns the exact custom table suffixes owned by the plugin.
	 *
	 * This allowlist is the canonical source of truth for schema discovery and
	 * deletion so unrelated third-party tables such as {$prefix}arlo_backup do
	 * not participate in schema hashing or cleanup.
	 *
	 * @return list<string> Plugin-owned table suffixes without the WP prefix.
	 */
	protected static function get_plugin_table_suffixes(): array {
		return [
			'arlo_async_tasks',
			'arlo_async_task_data',
			'arlo_categories',
			'arlo_contentfields',
			'arlo_events',
			'arlo_events_presenters',
			'arlo_eventtemplates',
			'arlo_eventtemplates_categories',
			'arlo_eventtemplates_presenters',
			'arlo_onlineactivities',
			'arlo_onlineactivities_tags',
			'arlo_offers',
			'arlo_presenters',
			'arlo_venues',
			'arlo_events_tags',
			'arlo_eventtemplates_tags',
			'arlo_tags',
			'arlo_timezones',
			'arlo_messages',
			'arlo_log',
			'arlo_import',
			'arlo_import_parts',
			'arlo_import_lock',
		];
	}

	/**
	 * Returns the full plugin-owned table names for a given WP prefix.
	 *
	 * @param string $prefix The WP table prefix.
	 * @return list<string> Fully qualified plugin-owned table names.
	 */
	protected static function get_plugin_table_names( string $prefix ): array {
		return array_map(
			static function ( string $suffix ) use ( $prefix ): string {
				return $prefix . $suffix;
			},
			self::get_plugin_table_suffixes()
		);
	}

	/**
	 * Discovers all existing arlo_ tables under the given prefix.
	 *
	 * The wildcard query is intentionally broad, but callers must still filter
	 * the results against get_plugin_table_names() before treating a table as
	 * plugin-owned.
	 *
	 * @param string $prefix The $wpdb->prefix value to scope the query.
	 * @return array<string, true> Existing arlo_-prefixed tables keyed by full table name.
	 */
	protected function discover_arlo_tables( string $prefix ): array {
		global $wpdb;
		$discovered_tables = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema discovery. Direct database query is required for custom tables.
		$tables = $wpdb->get_results( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $prefix . 'arlo_' ) . '%' ), ARRAY_N );

		foreach ( $tables as $table ) {
			$discovered_tables[ $table[0] ] = true;
		}

		return $discovered_tables;
	}

	/**
	 * Queries the database and returns raw SHOW COLUMNS output for all plugin-owned
	 * tables under the given prefix, keyed by full table name.
	 *
	 * Discovery starts with all {$prefix}arlo_% tables, then filters the results
	 * against the plugin's exact owned-table allowlist so unrelated third-party
	 * tables with the same naming convention do not affect the schema hash.
	 *
	 * @param string $prefix The $wpdb->prefix value to scope the query.
	 * @return array<string, list<array{Field: string, Type: string, Key: string}>>
	 *         Full table name => list of raw column rows from SHOW COLUMNS.
	 */
	protected function fetch_table_columns( string $prefix ): array {
		global $wpdb;
		$raw = [];
		$discovered_tables = $this->discover_arlo_tables( $prefix );

		foreach ( self::get_plugin_table_names( $prefix ) as $table_name ) {
			if ( ! isset( $discovered_tables[ $table_name ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct DB queries; table name is from the plugin-owned allowlist, verified present in SHOW TABLES results.
			$raw[ $table_name ] = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name ), ARRAY_A );
		}

		return $raw;
	}

	/**
	 * Computes a SHA1 hash from raw table column metadata.
	 *
	 * Strips $prefix from every table name so the result is identical regardless
	 * of the configured $wpdb->prefix (e.g. 'wp_', 'MyCustomSchemaPrefix_', any custom prefix).
	 * DB_SCHEMA_HASH must be the value this method produces for the current schema.
	 *
	 * @param array<string, list<array{Field: string, Type: string, Key: string}>> $raw_tables
	 *        Full table name => list of raw column rows, as returned by fetch_table_columns().
	 * @param string $prefix The WP table prefix to strip from table names before hashing.
	 * @return string SHA1 hash of the normalised, prefix-stripped table/column structure.
	 */
	public static function compute_schema_hash( array $raw_tables, string $prefix ): string {
		$scheme = [];

		foreach ( $raw_tables as $table_name => $column_rows ) {
			if ( '' !== $prefix && 0 !== strpos( $table_name, $prefix ) ) {
				throw new \InvalidArgumentException( 'Schema table name does not start with the provided prefix. Table: ' . $table_name . ' Prefix: ' . $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
			}

			$fields = [];
			foreach ( $column_rows as $fd ) {
				if ( strpos( $fd['Type'], 'enum' ) !== false ) {
					preg_match_all( "/'(.*)'/sU", $fd['Type'], $matches );
					if ( is_array( $matches[1] ) ) {
						sort( $matches[1] );
						$fd['Type'] = "enum('" . implode( "','", $matches[1] ) . "')";
					}
				}

				$fields[ $fd['Field'] ] = [
					'Type' => $fd['Type'],
					'Key'  => $fd['Key'],
				];
			}
			ksort( $fields );
			// Strip the WP prefix so the hash is identical for any $wpdb->prefix value.
			$scheme[ substr( $table_name, strlen( $prefix ) ) ] = $fields;
		}
		ksort( $scheme );

		return hash( 'sha1', json_encode( $scheme ) );
	}

	/**
	 * Computes a SHA1 hash of the live database schema for all plugin-owned tables.
	 *
	 * @return string SHA1 hash of the sorted table/column structure.
	 */
	public function get_plugin_db_schema_hash() {
		global $wpdb;
		return self::compute_schema_hash( $this->fetch_table_columns( $wpdb->prefix ), $wpdb->prefix );
	}

	/**
	 * Checks the live database schema against DB_SCHEMA_HASH and repairs it if necessary.
	 *
	 * If the hash does not match, all plugin-owned tables are dropped and reinstalled,
	 * an admin notice is posted, and a fresh import is scheduled (when import is enabled).
	 *
	 * @return void
	 */
	public function ensure_consistent_db_schema() {
		if ( $this->get_plugin_db_schema_hash() !== self::DB_SCHEMA_HASH ) {

			//delete tables and re-create them
			$this->delete_tables();
			$this->install_schema();

			$hard_import_disabled = get_option('arlo_import_disabled', '0') == '1';
			$import_enabled = \Arlo_For_Wordpress::is_import_enabled();

			if ( $import_enabled ) {
				$message = [
					'<p>' . esc_html__( 'Arlo for WordPress has detected that there may be a problem with the structure of event information in your database. Event information is being repaired with a new copy.', 'arlo-training-and-event-management-system' ) . '</p>',
					'<p>' . esc_html__( 'This repair may take a few minutes, and during this time information about your events will be temporarily unavailable for visitors on your site.', 'arlo-training-and-event-management-system' ) . '</p>',
				];
				/* translators: %s: arlo setting page link */
				$message[] = '<p>' . wp_kses( sprintf( __( 'You can monitor the progress of the new import at the <a href="%s">Arlo Settings</a> page.', 'arlo-training-and-event-management-system' ), esc_url( admin_url( 'admin.php?page=' . $this->plugin->plugin_slug ) ) ), array( 'a' => array( 'href' => array() ) ) ) . '</p>';
			} elseif ( $hard_import_disabled ) {
				$message = [
					'<p>' . esc_html__( 'Arlo for WordPress has detected that there may be a problem with the structure of event information in your database.', 'arlo-training-and-event-management-system' ) . '</p>',
				];
				/* translators: %s: arlo setting page link */
				$message[] = '<p>' . wp_kses( sprintf( __( 'Arlo for WordPress cannot repair your event data automatically because one or more system requirements are not met. Please visit the <a href="%s">Arlo Settings</a> page to review the System requirements section before restoring your event data.', 'arlo-training-and-event-management-system' ), esc_url( admin_url( 'admin.php?page=' . $this->plugin->plugin_slug ) ) ), array( 'a' => array( 'href' => array() ) ) ) . '</p>';
			} else {
				$message = [
					'<p>' . esc_html__( 'Arlo for WordPress has detected that there may be a problem with the structure of event information in your database.', 'arlo-training-and-event-management-system' ) . '</p>',
				];
				/* translators: %s: arlo setting page link */
				$message[] = '<p>' . wp_kses( sprintf( __( 'Automatic sync is currently disabled. Please visit the <a href="%s">Arlo Settings</a> page and click &ldquo;Synchronize now&rdquo; to restore your event data.', 'arlo-training-and-event-management-system' ), esc_url( admin_url( 'admin.php?page=' . $this->plugin->plugin_slug ) ) ), array( 'a' => array( 'href' => array() ) ) ) . '</p>';
			}
			 
			$this->message_handler->set_message('error', self::get_schema_upgrade_warning_title(), implode('', $message), true);
			
			//kick off an import
			if ( $import_enabled )
				$this->plugin->get_scheduler()->set_task("import", -1);	

			Logger::log("The current database shema could be wrong");
		 }
	}	

	public function check_db_version($current_version) {
		return version_compare($current_version, self::DB_SCHEMA_VERSION);
	}

	public function install_schema() {
		global $wpdb;
		$wpdb->suppress_errors(false);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction start. Schema update. Direct database query is required for custom table.
		$wpdb->query('START TRANSACTION');

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction commit. Schema update. Direct database query is required for custom table.
		$wpdb->query('COMMIT');

		return;
	}

	private function sync_schema($sql) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	private function install_table_arlo_async_tasks() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_async_tasks";

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
		) " . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
		$sql = "
		CREATE TABLE " . $wpdb->prefix . "arlo_async_task_data (
		data_task_id int(11) NOT NULL,
		data_text text NOT NULL,
		PRIMARY KEY  (data_task_id)
		) " . $wpdb->get_charset_collate() . "";
		
		$this->sync_schema($sql);
	}

	private function install_table_arlo_eventtemplate() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_eventtemplates";

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
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_contentfields() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_contentfields";

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
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_events() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_events";

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
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_onlineactivities() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_onlineactivities";

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
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_venues() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_venues";

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
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_presenters() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_presenters";

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
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_offers() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_offers";

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
			" . $wpdb->get_charset_collate() . "";
		
		$this->sync_schema($sql);
	}

	private function install_table_arlo_eventtemplates_presenters() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_eventtemplates_presenters";
		
		$sql = "CREATE TABLE " . $table_name . " (
			et_id int(11) NOT NULL,
			p_arlo_id int(11) NOT NULL,
			p_order int(11) NULL COMMENT 'Order of the presenters for the event template.',
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (et_id, p_arlo_id, import_id),
			KEY cf_order (p_order),
			KEY fk_et_id_idx (et_id ASC),
			KEY fk_p_id_idx (p_arlo_id ASC))
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_tags() {
		global $wpdb;
		$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_tags (
			id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			tag varchar(255) NOT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (id)) " . $wpdb->get_charset_collate() . "";
			
		$this->sync_schema($sql);
		
		$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_events_tags (
			e_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (e_id, tag_id, import_id)) " . $wpdb->get_charset_collate() . "";
			
		$this->sync_schema($sql);  	
		
		$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_onlineactivities_tags (
			oa_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (oa_id, tag_id, import_id)) " . $wpdb->get_charset_collate() . "";
			
		$this->sync_schema($sql);	
		
		$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags (
			et_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (et_id, tag_id, import_id)) " . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_events_presenters() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_events_presenters";
		
		$sql = "CREATE TABLE " . $table_name . " (
			e_id int(11) NOT NULL,
			p_arlo_id int(11) NOT NULL,
			p_order int(11) NULL COMMENT 'Order of the presenters for the event.',
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (e_id, p_arlo_id, import_id),		
			KEY fk_e_id_idx (e_id ASC),
			KEY fk_p_id_idx (p_arlo_id ASC))
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_categories() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_categories";
		
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
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_eventtemplates_categories() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_eventtemplates_categories";

		$sql = "CREATE TABLE " . $table_name . " (
			et_arlo_id int(11) NOT NULL,
			c_arlo_id int(11) NOT NULL,
			et_order SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (et_arlo_id, c_arlo_id, import_id),
			KEY fk_et_id_idx (et_arlo_id ASC),
			KEY fk_c_id_idx (c_arlo_id ASC))
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_timezones() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_timezones";

		$sql = "
			CREATE TABLE " . $table_name . " (
			id int(11) NOT NULL,
			name varchar(256) NOT NULL,
			windows_tz_id varchar(256) NOT NULL,
			utc_offset int(11) NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (id, import_id)) " . $wpdb->get_charset_collate() . ";";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_log() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_log";

		$sql = "CREATE TABLE $table_name (
			id int(11) unsigned NOT NULL AUTO_INCREMENT,
			import_id int(11) unsigned NULL,
			message TEXT,
			created DATETIME DEFAULT NULL COMMENT 'in UTC',
			successful tinyint(1) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY import_id (import_id)) 
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	private function install_table_arlo_import_lock() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_import_lock";
			
		$sql = "CREATE TABLE $table_name (
			import_id int(10) unsigned NOT NULL,
			lock_acquired DATETIME NOT NULL,
			lock_expired DATETIME NOT NULL
			) " . $wpdb->get_charset_collate() . "";
		
		$this->sync_schema($sql);
	}

	private function install_table_arlo_import() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_import";
			
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
			) " . $wpdb->get_charset_collate() . "";
		
		$this->sync_schema($sql);        
	}

	private function install_table_arlo_import_parts() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_import_parts";
			
		$sql = "CREATE TABLE $table_name (
			  	id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				import_id INT(10) UNSIGNED NOT NULL,
				part ENUM('image', 'fragment') NOT NULL,
				iteration SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
				import_text LONGTEXT NULL DEFAULT NULL,
				created datetime NOT NULL,
				modified datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id)
			) " . $wpdb->get_charset_collate() . "";
		
		$this->sync_schema($sql);        
	}

	private function install_table_arlo_messages() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_messages";

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
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema($sql);
	}

	/**
	 * Drops all plugin-owned custom tables for the current WP prefix.
	 *
	 * Uses the canonical owned-table allowlist so only tables managed by this
	 * plugin are removed during schema repair or uninstall.
	 *
	 * @return void
	 */
	public function delete_tables() {
		global $wpdb;
		$table_names = self::get_plugin_table_names( $wpdb->prefix );
		//should be used in the uninstall.php
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are from the hardcoded plugin-owned allowlist prepended with the WP prefix (alphanumeric+underscore only). No user input. Dropping tables for schema reset/uninstall requires a direct query.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . implode( ',', $table_names ) );
	}
}
