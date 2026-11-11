<?php

namespace ArloTraining\Provisioning;

use ArloTraining\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/arlo-schema-inspection.php';

/**
 * Owns the plugin custom-table schema definition, hashing, and repair path.
 */
class SchemaManager {

	const DB_SCHEMA_HASH    = '0f23ed13c3029484d1a5aca957cf019d0bc51163';
	const DB_SCHEMA_VERSION = '5.2.0';

	private $message_handler;
	private $plugin;

	/**
	 * Creates the schema manager for the current plugin/site context.
	 *
	 * @param mixed $message_handler Message handler used for schema-repair warnings.
	 * @param mixed $plugin          Plugin singleton used to schedule repair imports.
	 */
	public function __construct( $message_handler, $plugin ) {
		$this->message_handler = $message_handler;
		$this->plugin          = $plugin;
	}

	/**
	 * Returns the user-facing schema repair warning title.
	 *
	 * @return string Translated admin warning title.
	 */
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
			'arlo_incremental_baseline',
			'arlo_incremental_baseline_state',
			'arlo_incremental_full_baseline_candidates',
			'arlo_incremental_runs',
			'arlo_snapshot_manifest',
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
	 * Returns the full plugin-owned table names for a given WP prefix.
	 *
	 * Public so uninstall.php can read the exact allowlist without bootstrapping
	 * the plugin or instantiating the schema manager.
	 *
	 * @param string $prefix The WP table prefix.
	 * @return list<string> Fully qualified plugin-owned table names.
	 */
	public static function get_owned_table_names( string $prefix ): array {
		return self::get_plugin_table_names( $prefix );
	}

	/**
	 * Discovers all existing arlo_ tables under the given prefix.
	 *
	 * The wildcard query is intentionally broad, but callers must still filter
	 * the results against get_plugin_table_names() before treating a table as
	 * plugin-owned.
	 *
	 * @param string $prefix The $wpdb->prefix value to scope the query.
	 * @return array<string, true>|null Existing arlo_-prefixed tables keyed by full table name, or null when the query failed.
	 */
	protected function discover_arlo_tables( string $prefix ): ?array {
		global $wpdb;
		$discovered_tables = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema discovery. Direct database query is required for custom tables.
		$tables = $wpdb->get_results( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $prefix . 'arlo_' ) . '%' ), ARRAY_N );

		if ( ! is_array( $tables ) || ! empty( $wpdb->last_error ) ) {
			return null;
		}

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
	 * @return array<string, list<array{Field: string, Type: string, Key: string}>>|null
	 *         Full table name => list of raw column rows from SHOW COLUMNS, or null when any read failed.
	 */
	protected function fetch_table_columns( string $prefix ): ?array {
		global $wpdb;
		$raw               = [];
		$discovered_tables = $this->discover_arlo_tables( $prefix );

		if ( null === $discovered_tables ) {
			return null;
		}

		foreach ( self::get_plugin_table_names( $prefix ) as $table_name ) {
			if ( ! isset( $discovered_tables[ $table_name ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check requires direct DB queries; table name is from the plugin-owned allowlist, verified present in SHOW TABLES results.
			$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name ), ARRAY_A );

			if ( ! is_array( $columns ) || ! empty( $wpdb->last_error ) ) {
				return null;
			}

			$raw[ $table_name ] = $columns;
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
				$fd['Type'] = self::normalise_column_type( $fd['Type'] );

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

		return hash( 'sha1', wp_json_encode( $scheme ) );
	}

	/**
	 * Normalises a raw SHOW COLUMNS type description for schema hashing.
	 *
	 * The normalised type preserves every schema characteristic that affects
	 * storage or values. It only removes documented representation differences:
	 * type-keyword case/spacing, semantic aliases, integer display widths, and
	 * the equivalent YEAR(4) display width. MySQL 8 omits these widths while
	 * MySQL 5.7 and MariaDB report them.
	 *
	 * Examples: int(11) => int; INTEGER(11) UNSIGNED => int unsigned;
	 * NUMERIC(10, 6) => decimal(10,6).
	 *
	 * @param string $column_type Raw SHOW COLUMNS Type value.
	 * @return string Canonical column type used by compute_schema_hash().
	 */
	private static function normalise_column_type( string $column_type ): string {
		$normalised_type = '';
		$in_string       = false;
		$pending_space   = false;
		$suppress_space  = false;

		for ( $position = 0, $length = strlen( $column_type ); $position < $length; ++$position ) {
			$character = $column_type[ $position ];

			if ( "'" === $character ) {
				if ( $in_string && $position + 1 < $length && "'" === $column_type[ $position + 1 ] ) {
					$normalised_type .= "''";
					++$position;
					continue;
				}

				$normalised_type .= $character;
				$in_string        = ! $in_string;
				$pending_space    = false;
				$suppress_space   = false;
				continue;
			}

			if ( $in_string ) {
				$normalised_type .= $character;
				continue;
			}

			if ( ctype_space( $character ) ) {
				if ( ! $suppress_space ) {
					$pending_space = true;
				}
				continue;
			}

			if ( '(' === $character || ',' === $character || ')' === $character ) {
				$normalised_type  = rtrim( $normalised_type );
				$normalised_type .= $character;
				$pending_space    = false;
				$suppress_space   = '(' === $character || ',' === $character;
				continue;
			}

			if ( $pending_space && '' !== $normalised_type ) {
				$normalised_type .= ' ';
			}

			$normalised_type .= strtolower( $character );
			$pending_space    = false;
			$suppress_space   = false;
		}

		$normalised_type = trim( $normalised_type );
		$normalised_type = preg_replace( '/^integer\b/', 'int', $normalised_type );
		$normalised_type = preg_replace( '/^(?:bool|boolean)\b/', 'tinyint', $normalised_type );
		$normalised_type = preg_replace( '/^(?:dec|numeric|fixed)\b/', 'decimal', $normalised_type );
		$normalised_type = preg_replace( '/^double precision\b/', 'double', $normalised_type );
		$normalised_type = preg_replace( '/^year\(4\)(?=\s|$)/', 'year', $normalised_type );

		return preg_replace( '/^(tinyint|smallint|mediumint|bigint|int)\(\d+\)(?=\s|$)/', '$1', $normalised_type );
	}

	/**
	 * Reads the live plugin-owned tables and compares them with the canonical schema.
	 *
	 * A failed read is reported as unreadable rather than as an empty schema, so callers
	 * never mistake a transient database error for structural drift.
	 *
	 * @return SchemaInspection Match, mismatch, or unreadable outcome.
	 */
	public function inspect_schema(): SchemaInspection {
		global $wpdb;
		$prefix      = $wpdb->prefix;
		$raw_columns = $this->fetch_table_columns( $prefix );

		if ( null === $raw_columns ) {
			$error = ! empty( $wpdb->last_error ) ? (string) $wpdb->last_error : 'unexpected database response';
			// The failed statement shows whether table discovery or a column read failed.
			$statement = isset( $wpdb->last_query ) && is_string( $wpdb->last_query ) && '' !== $wpdb->last_query ? ' [query: ' . $wpdb->last_query . ']' : '';

			return SchemaInspection::unreadable( 'Could not read plugin table structure: ' . $error . $statement );
		}

		$missing_tables = array_values( array_diff( self::get_plugin_table_names( $prefix ), array_keys( $raw_columns ) ) );

		return SchemaInspection::from_hash( self::compute_schema_hash( $raw_columns, $prefix ), self::DB_SCHEMA_HASH, $missing_tables );
	}

	/**
	 * Computes a SHA1 hash of the live database schema for all plugin-owned tables.
	 *
	 * @return string|null SHA1 hash of the sorted table/column structure, or null when the schema could not be read.
	 */
	public function get_plugin_db_schema_hash(): ?string {
		return $this->inspect_schema()->get_hash();
	}

	/**
	 * Checks whether the database supports delta-import infrastructure.
	 *
	 * @return bool True when every table a delta transaction writes or locks uses InnoDB. That includes the WordPress
	 *              options table, whose live import id row the transactions lock with SELECT ... FOR UPDATE.
	 */
	public function supports_delta_infrastructure(): bool {
		global $wpdb;
		$required_transaction_table_suffixes = [
			'arlo_events',
			'arlo_incremental_runs',
			'arlo_snapshot_manifest',
			'arlo_incremental_baseline_state',
			'arlo_import_lock',
			'arlo_incremental_baseline',
			'arlo_incremental_full_baseline_candidates',
		];

		$table_names = [ $wpdb->options ];
		foreach ( $required_transaction_table_suffixes as $table_suffix ) {
			$table_names[] = $wpdb->prefix . $table_suffix;
		}

		foreach ( $table_names as $table_name ) {
			$table   = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table_name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only engine inspection for each table participating in the first Event delta write transaction.
			$details = is_object( $table ) ? get_object_vars( $table ) : [];
			$engine  = $details['Engine'] ?? null;

			if ( ! is_string( $engine ) || 0 !== strcasecmp( $engine, 'InnoDB' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks the live database schema against DB_SCHEMA_HASH and repairs it if necessary.
	 *
	 * If the live schema was read and the hash does not match, all plugin-owned tables are
	 * dropped and reinstalled, an admin notice is posted, and a fresh import is scheduled
	 * (when a configured, enabled platform exists). If the schema cannot be read, nothing is
	 * changed: a failed read is not evidence of drift. The check is not retried on its own; a
	 * later upgrade or an importer lock failure runs it again.
	 *
	 * @return void
	 */
	public function ensure_consistent_db_schema() {
		$inspection = $this->inspect_schema();

		if ( ! $inspection->is_readable() ) {
			$this->report_schema_problem( 'Database schema check skipped; existing tables were left untouched. ' . $inspection->get_reason() );
			return;
		}

		if ( $inspection->needs_repair() ) {

			//delete tables and re-create them
			$this->delete_tables();
			$this->install_schema();
			$this->log_repair_outcome( $this->inspect_schema() );

			$hard_import_disabled = get_option( 'arlo_import_disabled', '0' ) == '1';
			$arlo_settings        = get_option( 'arlo_settings', [] );

			// Only attempt a repair import for a previously configured platform.
			// is_import_enabled() rejects sites whose requirements are unmet, whose sync is switched off,
			// or whose platform access has been auto-disabled after sustained connection failures.
			$platform_configured   = is_array( $arlo_settings ) && \Arlo_For_Wordpress::has_configured_platform( $arlo_settings );
			$enqueue_repair_import = $platform_configured && \Arlo_For_Wordpress::is_import_enabled();

			if ( $enqueue_repair_import ) {
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
			} elseif ( ! $platform_configured ) {
				$message = [
					'<p>' . esc_html__( 'Arlo for WordPress has detected that there may be a problem with the structure of event information in your database.', 'arlo-training-and-event-management-system' ) . '</p>',
				];
				/* translators: %s: arlo setting page link */
				$message[] = '<p>' . wp_kses( sprintf( __( 'No Arlo platform is currently connected, so your event data cannot be restored automatically. Please visit the <a href="%s">Arlo Settings</a> page and enter your platform name to reconnect and restore it.', 'arlo-training-and-event-management-system' ), esc_url( admin_url( 'admin.php?page=' . $this->plugin->plugin_slug ) ) ), array( 'a' => array( 'href' => array() ) ) ) . '</p>';
			} else {
				$message = [
					'<p>' . esc_html__( 'Arlo for WordPress has detected that there may be a problem with the structure of event information in your database.', 'arlo-training-and-event-management-system' ) . '</p>',
				];
				/* translators: %s: arlo setting page link */
				$message[] = '<p>' . wp_kses( sprintf( __( 'Automatic sync is currently disabled. Please visit the <a href="%s">Arlo Settings</a> page and click &ldquo;Synchronize now&rdquo; to restore your event data.', 'arlo-training-and-event-management-system' ), esc_url( admin_url( 'admin.php?page=' . $this->plugin->plugin_slug ) ) ), array( 'a' => array( 'href' => array() ) ) ) . '</p>';
			}

			$this->message_handler->set_message( 'error', self::get_schema_upgrade_warning_title(), implode( '', $message ), true );

			// kick off an import
			if ( $enqueue_repair_import ) {
				// Plain set_task(): the repair above dropped the task tables, so set_task_unique() has no existing row to deduplicate against.
				$this->plugin->get_scheduler()->set_task( "import", -1 );
			}

			Logger::log( "The current database schema could be wrong" );
		}
	}

	/**
	 * Compares a stored schema model version with the current schema model version.
	 *
	 * This is separate from the plugin release version; normal upgrades are gated by
	 * `arlo_plugin_version`, while this value records which schema model was stamped.
	 *
	 * @param string $current_version Stored schema version to compare.
	 * @return int Same return contract as version_compare().
	 */
	public function check_db_version( $current_version ) {
		return version_compare( $current_version, self::DB_SCHEMA_VERSION );
	}

	/**
	 * Installs or repairs all plugin-owned custom tables from the canonical schema.
	 *
	 * Used on fresh install and as the destructive fallback when schema hash repair is needed.
	 * Expected versioned upgrades should run targeted ALTER migrations before reaching this path.
	 *
	 * @return void
	 */
	public function install_schema() {
		global $wpdb;
		$wpdb->suppress_errors( false );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction start. Schema update. Direct database query is required for custom table.
		$wpdb->query( 'START TRANSACTION' );

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
		$this->install_table_arlo_incremental_baseline_state();
		$this->install_table_arlo_incremental_baseline();
		$this->install_table_arlo_incremental_full_baseline_candidates();
		$this->install_table_arlo_incremental_runs();
		$this->install_table_arlo_snapshot_manifest();
		$this->install_table_arlo_categories();
		$this->install_table_arlo_eventtemplates_categories();
		$this->install_table_arlo_timezones();
		$this->install_table_arlo_messages();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction commit. Schema update. Direct database query is required for custom table.
		$wpdb->query( 'COMMIT' );
	}

	/**
	 * Installs only the incremental-import tables during a preservation-aware upgrade.
	 *
	 * @return void
	 */
	public function install_incremental_schema() {
		$this->install_table_arlo_incremental_baseline_state();
		$this->install_table_arlo_incremental_baseline();
		$this->install_table_arlo_incremental_full_baseline_candidates();
		$this->install_table_arlo_incremental_runs();
		$this->install_table_arlo_snapshot_manifest();
	}

	private function sync_schema( $sql ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
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

		$this->sync_schema( $sql );
		$sql = "
		CREATE TABLE " . $wpdb->prefix . "arlo_async_task_data (
		data_task_id int(11) NOT NULL,
		data_text text NOT NULL,
		PRIMARY KEY  (data_task_id)
		) " . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );
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
			et_region varchar(16) NULL,
			PRIMARY KEY  (et_id),
			KEY et_post_id (et_post_id),
			KEY et_arlo_id (et_arlo_id),
			KEY et_region (et_region))
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
	}

	private function install_table_arlo_events() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_events";

		// Region codes use text storage for case-insensitive event/template comparisons.
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
			e_region varchar(16) NOT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (e_id),
			KEY et_arlo_id (et_arlo_id),
			KEY e_arlo_id (e_arlo_id),
			KEY e_region (e_region),
			KEY e_import_region_arlo_id (import_id, e_region, e_arlo_id),
			KEY e_import_region_parent_arlo_id (import_id, e_region, e_parent_arlo_id),
			KEY e_is_taxexempt (e_is_taxexempt),
			KEY v_id (v_id))
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );
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
			oa_region varchar(16) NOT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (oa_id),
			KEY oat_arlo_id (oat_arlo_id),
			KEY oa_arlo_id (oa_arlo_id),
			KEY oa_region (oa_region))
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
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
			o_region varchar(16) NOT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (o_id),
			KEY o_arlo_id (o_arlo_id),
			KEY et_id (et_id),
			KEY e_id (e_id),
			KEY oa_id (oa_id),
			KEY o_region (o_region),
			KEY o_order (o_order))
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
	}

	private function install_table_arlo_tags() {
		global $wpdb;
		$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_tags (
			id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			tag varchar(255) NOT NULL,
			import_id int(10) unsigned DEFAULT NULL,
			PRIMARY KEY  (id)) " . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );

		$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_events_tags (
			e_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (e_id, tag_id, import_id)) " . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );

		$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_onlineactivities_tags (
			oa_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (oa_id, tag_id, import_id)) " . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );

		$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags (
			et_id int(11) NOT NULL,
			tag_id mediumint(8) unsigned NOT NULL,
			import_id int(10) unsigned NOT NULL,
			PRIMARY KEY  (et_id, tag_id, import_id)) " . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
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
			diagnostic_level tinyint(3) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY import_id (import_id))
			" . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );
	}

	private function install_table_arlo_import_lock() {
		global $wpdb;
		$table_name = $wpdb->prefix . "arlo_import_lock";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema migration check requires a direct table lookup; no caching applies.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_exists === $table_name ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existing lock rows must be removed before adding a fixed primary key; table name is plugin-owned.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table_name ) );
		}

		$sql = "CREATE TABLE $table_name (
			lock_row_id int(10) unsigned NOT NULL DEFAULT 1,
			lock_token varchar(64) NOT NULL,
			import_id int(10) unsigned NOT NULL DEFAULT 0,
			lock_acquired DATETIME NOT NULL,
			lock_expired DATETIME NOT NULL,
			PRIMARY KEY  (lock_row_id)
			) " . $wpdb->get_charset_collate() . "";

		$this->sync_schema( $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema verification requires a direct index lookup; table name is plugin-owned.
		$primary_key = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'PRIMARY' AND Column_name = 'lock_row_id'", $table_name ) );
		if ( empty( $primary_key ) ) {
			throw new \RuntimeException( 'Import lock table schema repair failed: primary key on lock_row_id was not created.' );
		}
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

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
	}

	private function install_table_arlo_incremental_baseline_state() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'arlo_incremental_baseline_state';

		$sql = "CREATE TABLE $table_name (
			baseline_state_id tinyint(3) unsigned NOT NULL DEFAULT 1,
			current_baseline_generation_id bigint(20) unsigned NULL,
			schema_level smallint(5) unsigned NULL,
			configuration_signature binary(32) NULL,
			projection_count bigint(20) unsigned NOT NULL DEFAULT 0,
			census_tag binary(32) NULL,
			baseline_status enum('clean','full_required','invalid') NOT NULL DEFAULT 'invalid',
			modified datetime NOT NULL COMMENT 'in UTC',
			PRIMARY KEY  (baseline_state_id)) " . $wpdb->get_charset_collate();

		$this->sync_schema( $sql );
	}

	private function install_table_arlo_incremental_baseline() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'arlo_incremental_baseline';

		$sql = "CREATE TABLE $table_name (
			baseline_row_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			baseline_generation_id bigint(20) unsigned NOT NULL,
			baseline_owner_kind enum('delta_run','full_shadow_import') NOT NULL,
			baseline_owner_id bigint(20) unsigned NOT NULL,
			entity_kind varbinary(32) NOT NULL,
			entity_key_1 varbinary(64) NOT NULL,
			entity_key_2 varbinary(64) NOT NULL DEFAULT '',
			entity_region_code varbinary(16) NOT NULL DEFAULT '',
			structural_tag binary(32) NOT NULL,
			availability_tag binary(32) NULL,
			source_snapshot_id varbinary(255) NOT NULL,
			PRIMARY KEY  (baseline_row_id),
			UNIQUE KEY baseline_entity (baseline_generation_id, entity_kind, entity_key_1, entity_key_2, entity_region_code),
			KEY baseline_kind_region (baseline_generation_id, entity_kind, entity_region_code)) " . $wpdb->get_charset_collate();

		$this->sync_schema( $sql );
	}

	private function install_table_arlo_incremental_full_baseline_candidates() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'arlo_incremental_full_baseline_candidates';

		$sql = "CREATE TABLE $table_name (
			candidate_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_id bigint(20) unsigned NOT NULL,
			baseline_generation_id bigint(20) unsigned NOT NULL,
			schema_level smallint(5) unsigned NOT NULL,
			configuration_signature binary(32) NOT NULL,
			source_snapshot_id varbinary(255) NOT NULL,
			snapshot_package_digest binary(32) NOT NULL,
			manifest_count bigint(20) unsigned NOT NULL,
			census_tag binary(32) NOT NULL,
			PRIMARY KEY  (candidate_id),
			UNIQUE KEY import_id (import_id),
			KEY candidate_generation (baseline_generation_id)) " . $wpdb->get_charset_collate();

		$this->sync_schema( $sql );
	}

	private function install_table_arlo_incremental_runs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'arlo_incremental_runs';

		$sql = "CREATE TABLE $table_name (
			incremental_run_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			admitted_import_id bigint(20) unsigned NOT NULL,
			expected_active_import_id bigint(20) unsigned NOT NULL,
			schema_level smallint(5) unsigned NOT NULL,
			configuration_signature binary(32) NOT NULL,
			source_snapshot_id varbinary(255) NOT NULL,
			snapshot_package_digest binary(32) NOT NULL,
			census_tag binary(32) NOT NULL,
			snapshot_manifest_count bigint(20) unsigned NOT NULL,
			expected_baseline_generation_id bigint(20) unsigned NOT NULL,
			sealed_mode enum('full','delta') NULL,
			terminal_result enum('pending','no_op','delta','full_shadow') NOT NULL DEFAULT 'pending',
			fallback_reason varchar(64) NULL,
			analysis_sealed_at datetime NULL,
			selected_root_count bigint(20) unsigned NOT NULL DEFAULT 0,
			success_finalization_claimed_at datetime NULL,
			created datetime NOT NULL COMMENT 'in UTC',
			modified datetime NOT NULL COMMENT 'in UTC',
			PRIMARY KEY  (incremental_run_id),
			UNIQUE KEY admitted_import_id (admitted_import_id),
			KEY run_mode (sealed_mode, terminal_result)) " . $wpdb->get_charset_collate();

		$this->sync_schema( $sql );
	}

	private function install_table_arlo_snapshot_manifest() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'arlo_snapshot_manifest';

		$sql = "CREATE TABLE $table_name (
			snapshot_manifest_row_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			analysis_run_id bigint(20) unsigned NOT NULL,
			entity_kind varbinary(32) NOT NULL,
			entity_key_1 varbinary(64) NOT NULL,
			entity_key_2 varbinary(64) NOT NULL DEFAULT '',
			entity_region_code varbinary(16) NOT NULL DEFAULT '',
			structural_tag binary(32) NOT NULL,
			availability_tag binary(32) NULL,
			source_fragment_element_index bigint(20) unsigned NULL,
			PRIMARY KEY  (snapshot_manifest_row_id),
			UNIQUE KEY manifest_entity (analysis_run_id, entity_kind, entity_key_1, entity_key_2, entity_region_code),
			KEY manifest_fragment (analysis_run_id, source_fragment_element_index)) " . $wpdb->get_charset_collate();

		$this->sync_schema( $sql );
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

		$this->sync_schema( $sql );
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are from the hardcoded plugin-owned allowlist prepended with the WP prefix (alphanumeric+underscore only). No user input. Dropping tables for schema reset/uninstall requires a direct query.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . implode( ',', $table_names ) );
	}

	/**
	 * Records whether a repair produced the canonical schema.
	 *
	 * Diagnostic only: the tables exist again, so the fresh import is still the right next step.
	 *
	 * @param SchemaInspection $inspection Inspection taken immediately after install_schema().
	 * @return void
	 */
	private function log_repair_outcome( SchemaInspection $inspection ): void {
		if ( SchemaInspection::STATUS_MATCHES === $inspection->get_status() ) {
			Logger::log( 'Database schema repair completed; the structure matches the expected schema.' );
			return;
		}

		$missing_tables = $inspection->get_missing_tables();
		$details        = SchemaInspection::STATUS_UNREADABLE === $inspection->get_status()
			? $inspection->get_reason()
			: 'live hash ' . $inspection->get_hash() . '; missing tables: ' . ( array() === $missing_tables ? 'none' : implode( ', ', $missing_tables ) );

		$this->report_schema_problem( 'Database schema still differs from the expected structure after repair [status=' . $inspection->get_status() . '; ' . $details . ']' );
	}

	/**
	 * Reports a schema problem to the Arlo log and the PHP error log.
	 *
	 * The Arlo log is itself a plugin table that a failed repair or a failing database may not have
	 * available, so the same text is also written where it cannot depend on those tables.
	 *
	 * @param string $message Diagnostic text without personal or site data.
	 * @return void
	 */
	private function report_schema_problem( string $message ): void {
		Logger::log( $message );
		error_log( 'Arlo for WordPress: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The Arlo log table may be the table that is missing or unreadable.
	}
}
