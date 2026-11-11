<?php

namespace ArloTraining;

use ArloTraining\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Runs plugin-version upgrades and records the installed plugin/schema versions.
 *
 * `VERSION` is the plugin release gate used by `arlo_plugin_version`. Schema shape is
 * recorded separately through `SchemaManager::DB_SCHEMA_VERSION` after migrations run.
 */
#[\AllowDynamicProperties]
class VersionHandler {
	const VERSION                                     = '5.2.0';
	private const PRE_VERSION_5_2_0_REGION_MAX_LENGTH = 5;

	private $message_handler;
	private $plugin;
	private $refresh_import_after_update = false;

	/**
	 * Creates the version handler used by the plugin singleton.
	 *
	 * @param mixed $message_handler Message handler dependency used by legacy migrations.
	 * @param mixed $plugin          Plugin singleton exposing schema/settings helpers.
	 * @param mixed $theme_manager   Theme manager dependency used by legacy template migrations.
	 */
	public function __construct( $message_handler, $plugin, $theme_manager ) {
		$this->message_handler = $message_handler;
		$this->plugin          = $plugin;
		$this->theme_manager   = $theme_manager;
	}

	/**
	 * Returns the installed plugin release version.
	 *
	 * @return string|false Stored `arlo_plugin_version` option, or false when absent.
	 */
	public function get_current_installed_version() {
		return get_option( 'arlo_plugin_version' );
	}

	/**
	 * Records the current plugin release version and schema model version.
	 *
	 * @return void
	 */
	public function set_installed_version() {
		CustomCss::initialize();
		$schema_manager = $this->plugin->get_schema_manager();
		update_option( 'arlo_plugin_version', self::VERSION );

		$now = \ArloTraining\Utilities::get_now_utc();
		update_option( 'arlo_updated', $now->format( "Y-m-d H:i:s" ) );

		update_option( 'arlo_schema_version', $schema_manager::DB_SCHEMA_VERSION );
	}

	/**
	 * Runs all migrations needed between the installed plugin version and this build.
	 *
	 * @param string $from_version Previously installed plugin release version.
	 * @return void
	 */
	public function run_update( $from_version ) {
		$this->update( self::VERSION, $from_version );
		$this->set_installed_version();
	}

	private function update( $new_version, $old_version ) {
		$this->refresh_import_after_update = false;

		//pre datamodell update need to be done before
		if ( version_compare( $old_version, '2.4' ) < 0 ) {
			$this->run_pre_data_update( '2.4' );
		}

		if ( version_compare( $old_version, '2.4.1.1' ) < 0 ) {
			$this->run_pre_data_update( '2.4.1.1' );
		}

		if ( version_compare( $old_version, '3.0' ) < 0 ) {
			$this->run_pre_data_update( '2.4.1.1' );
			$this->run_pre_data_update( '3.0' );
		}

		if ( version_compare( $old_version, '3.6' ) < 0 ) {
			$this->run_pre_data_update( '3.6' );
		}

		if ( version_compare( $old_version, '3.6.1' ) < 0 ) {
			$this->run_pre_data_update( '3.6.1' );
		}

		if ( version_compare( $old_version, '3.8' ) < 0 ) {
			$this->run_pre_data_update( '3.8' );
		}

		if ( version_compare( $old_version, '3.8.1' ) < 0 ) {
			$this->run_pre_data_update( '3.8.1' );
		}

		if ( version_compare( $old_version, '3.9' ) < 0 ) {
			$this->run_pre_data_update( '3.9' );
		}

		if ( version_compare( $old_version, '4.0' ) < 0 ) {
			$this->run_pre_data_update( '4.0' );
		}

		if ( version_compare( $old_version, '4.1' ) < 0 ) {
			$this->run_pre_data_update( '4.1' );
		}

		// Schema ALTERs must run before arlo_add_datamodel() can trigger hash repair.
		if ( version_compare( $old_version, '5.2.0' ) < 0 ) {
			$this->run_pre_data_update( '5.2.0' );
		}

		arlo_add_datamodel();

		if ( version_compare( $old_version, '2.2.1' ) < 0 ) {
			$this->do_update( '2.2.1' );
		}

		if ( version_compare( $old_version, '2.3' ) < 0 ) {
			$this->do_update( '2.3' );
		}

		if ( version_compare( $old_version, '2.3.5' ) < 0 ) {
			$this->do_update( '2.3.5' );
		}

		if ( version_compare( $old_version, '2.4' ) < 0 ) {
			$this->do_update( '2.4' );
		}

		if ( version_compare( $old_version, '3.0' ) < 0 ) {
			$this->do_update( '3.0' );
		}

		if ( version_compare( $old_version, '3.1' ) < 0 ) {
			$this->do_update( '3.1' );
		}

		if ( version_compare( $old_version, '3.1.3' ) < 0 ) {
			$this->do_update( '3.1.3' );
		}

		if ( version_compare( $old_version, '3.2' ) < 0 ) {
			$this->do_update( '3.2' );
		}

		if ( version_compare( $old_version, '3.3' ) < 0 ) {
			$this->do_update( '3.3' );
		}

		if ( version_compare( $old_version, '3.5' ) < 0 ) {
			$this->do_update( '3.5' );
		}

		if ( version_compare( $old_version, '3.5.1' ) < 0 ) {
			$this->do_update( '3.5.1' );
		}

		if ( version_compare( $old_version, '3.6' ) < 0 ) {
			$this->do_update( '3.6' );
		}

		if ( version_compare( $old_version, '4.0' ) < 0 ) {
			$this->do_update( '4.0' );
		}

		if ( version_compare( $old_version, '5.1.0' ) < 0 ) {
			$this->do_update( '5.1.0' );
		}

		if ( version_compare( $old_version, '5.2.0' ) < 0 ) {
			$this->do_update( '5.2.0' );
		}

		// Queue refresh imports only after every version-specific update has run,
		// so stacked upgrades cannot import against a partially migrated schema.
		if ( $this->refresh_import_after_update && \Arlo_For_Wordpress::is_import_enabled() ) {
			$this->plugin->get_scheduler()->set_task( "import", -1 );
		}
	}

	private function run_pre_data_update( $version ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Trusted Schema update. Direct database query is required for custom table. Do not need cache for schema update
		switch ( $version ) {
			case '2.4':
				$exists = $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "arlo_log'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "RENAME TABLE " . $wpdb->prefix . "arlo_import_log TO " . $wpdb->prefix . "arlo_log" );
				}

				$exists = $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "arlo_async_tasks'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks CHANGE task_modified task_modified TIMESTAMP NULL DEFAULT NULL COMMENT 'Dates are in UTC';" );
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks CHANGE task_created task_created TIMESTAMP NULL DEFAULT NULL COMMENT 'Dates are in UTC';" );
				}

				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates_presenters LIKE 'et_arlo_id'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters CHANGE  et_arlo_id  et_id int( 11 ) NOT NULL DEFAULT  '0'" );
				}

				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events_tags LIKE 'e_arlo_id'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events_tags CHANGE  e_arlo_id  e_id int( 11 ) NOT NULL DEFAULT  '0'" );
				}

				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates_tags LIKE 'et_arlo_id'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags CHANGE  et_arlo_id  et_id int( 11 ) NOT NULL DEFAULT  '0'" );
				}

				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events_presenters LIKE 'e_arlo_id'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters CHANGE  e_arlo_id  e_id int( 11 ) NOT NULL DEFAULT  '0'" );

				}

				$exists = $wpdb->get_var( "SHOW KEYS FROM " . $wpdb->prefix . "arlo_categories WHERE key_name = 'c_arlo_id'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_categories DROP KEY c_arlo_id " );
				}

				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_timezones DROP PRIMARY KEY, ADD PRIMARY KEY (id,active)" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters DROP PRIMARY KEY, ADD PRIMARY KEY (e_id,p_arlo_id,active)" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events_tags DROP PRIMARY KEY, ADD PRIMARY KEY (e_id,tag_id,active)" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags DROP PRIMARY KEY, ADD PRIMARY KEY (et_id,tag_id,active)" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_categories DROP PRIMARY KEY, ADD PRIMARY KEY (c_id, active)" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_categories DROP PRIMARY KEY, ADD PRIMARY KEY (et_arlo_id,c_arlo_id,active)" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_timezones_olson DROP PRIMARY KEY, ADD PRIMARY KEY (timezone_id,olson_name,active)" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters DROP PRIMARY KEY, ADD PRIMARY KEY (et_id,p_arlo_id,active)" );

				break;

			case '2.4.1.1':
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_categories CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_contentfields CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_categories CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_offers CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_onlineactivities CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_onlineactivities_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_timezones CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_timezones_olson CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_venues CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'" );
				break;

			case '3.0':
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events DROP e_summary;" );
				$wpdb->query( "DROP TABLE " . $wpdb->prefix . "arlo_timezones_olson;" );
				break;

			case '3.6':
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_is_taxexempt'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_is_taxexempt TINYINT(1) NOT NULL DEFAULT '0' AFTER e_isonline" );
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events ADD INDEX (e_is_taxexempt(1))" );
				}
				break;

			case '3.6.1':
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events DROP e_timezone_id;" );
				$wpdb->query( "DROP TABLE " . $wpdb->prefix . "arlo_timezones;" );
				$wpdb->query(
					"CREATE TABLE " . $wpdb->prefix . "arlo_timezones (
					id int(11) NOT NULL,
					name varchar(256) NOT NULL,
					windows_tz_id varchar(256) NOT NULL,
					import_id int(10) unsigned NOT NULL,
					PRIMARY KEY  (id, import_id))
					" . $wpdb->get_charset_collate() . ";"
				);
				$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_timezone_id INT(11) NULL AFTER e_timezone" );
				break;

			case '3.8':
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_summary'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_summary TEXT NULL AFTER e_sessiondescription" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates LIKE 'et_registerprivateinteresturi'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates ADD et_registerprivateinteresturi TEXT NULL AFTER et_registerinteresturi" );
				}
				break;

			case '3.8.1':
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_async_tasks LIKE 'task_hostname'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks ADD task_hostname varchar(255) DEFAULT NULL AFTER task_status_text" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_async_tasks LIKE 'task_lb_count'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks ADD task_lb_count tinyint(4) NOT NULL DEFAULT '0' AFTER task_hostname" );
				}
				break;

			case '3.9':
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_async_tasks LIKE 'task_lb_count'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks DROP task_lb_count" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_async_tasks LIKE 'task_hostname'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks DROP task_hostname" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates LIKE 'et_credits'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates ADD et_credits varchar(255) NULL AFTER et_registerprivateinteresturi" );
				}
				$exists = $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "arlo_import_parts'", 0, 0 );
				if ( ! is_null( $exists ) ) {
					$wpdb->query( "DROP TABLE " . $wpdb->prefix . "arlo_import_parts;" );
				}
				$wpdb->query(
					"CREATE TABLE " . $wpdb->prefix . "arlo_import_parts (
					id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					import_id INT(10) UNSIGNED NOT NULL,
					part ENUM('image', 'fragment') NOT NULL,
					iteration SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
					import_text LONGTEXT NULL DEFAULT NULL,
					created datetime NOT NULL,
					modified datetime NULL DEFAULT NULL,
					PRIMARY KEY  (id))
					" . $wpdb->get_charset_collate() . ";"
				);
				break;

			case '4.0':
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates LIKE 'et_hero_image'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates ADD et_hero_image text NULL AFTER et_viewuri" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates LIKE 'et_list_image'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates ADD et_list_image text NULL AFTER et_hero_image" );
				}
				break;

			case '4.1':
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_timezones LIKE 'utc_offset'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_timezones ADD utc_offset INT(11) NOT NULL AFTER windows_tz_id" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_finishtimezoneabbr'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_finishtimezoneabbr varchar(7) NULL AFTER e_finishdatetime" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_starttimezoneabbr'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_starttimezoneabbr varchar(7) NOT NULL AFTER e_finishdatetime" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_finishdatetimeoffset'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_finishdatetimeoffset varchar(6) NOT NULL AFTER e_finishdatetime" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_startdatetimeoffset'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events ADD e_startdatetimeoffset varchar(6) NOT NULL AFTER e_finishdatetime" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_datetimeoffset'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events DROP e_datetimeoffset;" );
				}
				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events LIKE 'e_timezone'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_events DROP e_timezone;" );
				}

				$exists = $wpdb->get_var( "SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_venues LIKE 'v_locationname'", 0, 0 );
				if ( is_null( $exists ) ) {
					$wpdb->query( "ALTER TABLE " . $wpdb->prefix . "arlo_venues ADD v_locationname text NULL AFTER v_name" );
				}
				break;

			case '5.2.0':
				$table_name = $wpdb->prefix . 'arlo_import_lock';
				$table_like = $wpdb->esc_like( $table_name );
				$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ), 0, 0 );

				if ( ! is_null( $exists ) ) {
					$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table_name ) );

					$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, $wpdb->esc_like( 'lock_row_id' ) ), 0, 0 );
					if ( is_null( $exists ) ) {
						$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD lock_row_id int(10) unsigned NOT NULL DEFAULT 1 FIRST', $table_name ) );
					}

					$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, $wpdb->esc_like( 'lock_token' ) ), 0, 0 );
					if ( is_null( $exists ) ) {
						$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD lock_token varchar(64) NOT NULL AFTER lock_row_id', $table_name ) );
					}

					$wpdb->query( $wpdb->prepare( "ALTER TABLE %i MODIFY import_id int(10) unsigned NOT NULL DEFAULT '0'", $table_name ) );

					$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW KEYS FROM %i WHERE Key_name = %s', $table_name, 'PRIMARY' ), 0, 0 );
					if ( is_null( $exists ) ) {
						$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD PRIMARY KEY (lock_row_id)', $table_name ) );
					}
				}

				$table_name = $wpdb->prefix . 'arlo_log';
				$table_like = $wpdb->esc_like( $table_name );
				$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ), 0, 0 );

				if ( ! is_null( $exists ) ) {
					$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, $wpdb->esc_like( 'diagnostic_level' ) ), 0, 0 );

					if ( is_null( $exists ) ) {
						$exceeds_truncate_threshold = $wpdb->get_var(
							$wpdb->prepare(
								'SELECT id FROM %i ORDER BY id DESC LIMIT 1 OFFSET %d',
								$table_name,
								Logger::LOG_RETENTION_TRUNCATE_THRESHOLD_ROWS
							),
							0,
							0
						);

						if ( ! is_null( $exceeds_truncate_threshold ) ) {
							$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );
							CacheControl::cache_delete( CacheControl::GROUP_LOG );
						}

						$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD diagnostic_level tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER successful', $table_name ) );

						if ( ! is_null( $exceeds_truncate_threshold ) ) {
							Logger::log( __( 'The Arlo log was cleared during the 5.2.0 upgrade because it exceeded the diagnostic-level migration safety threshold.', 'arlo-training-and-event-management-system' ), null, null, true );

							if ( is_object( $this->message_handler ) && method_exists( $this->message_handler, 'set_message' ) ) {
								$this->message_handler->set_message(
									'information',
									__( 'Arlo log cleared during upgrade', 'arlo-training-and-event-management-system' ),
									__( 'The Arlo log was cleared during the 5.2.0 upgrade because it exceeded the diagnostic-level migration safety threshold. New log entries will continue to be recorded.', 'arlo-training-and-event-management-system' ),
									false
								);
							}
						}
					}
				}

				// Keep region codes as text so existing case-insensitive event/template comparisons remain intact.
				$region_columns = [
					'arlo_eventtemplates'   => [ 'et_region', 'varchar(16) NULL' ],
					'arlo_events'           => [ 'e_region', 'varchar(16) NOT NULL' ],
					'arlo_onlineactivities' => [ 'oa_region', 'varchar(16) NOT NULL' ],
					'arlo_offers'           => [ 'o_region', 'varchar(16) NOT NULL' ],
				];

				foreach ( $region_columns as $table_suffix => $definition ) {
					$table_name = $wpdb->prefix . $table_suffix;
					$table_like = $wpdb->esc_like( $table_name );
					$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ), 0, 0 );

					if ( ! is_null( $exists ) ) {
						$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, $wpdb->esc_like( $definition[0] ) ), 0, 0 );
					}

					if ( ! is_null( $exists ) ) {
						$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i MODIFY %i ' . $definition[1], $table_name, $definition[0] ) );
					}
				}

				$import_indexes = [
					'arlo_events'           => [
						'e_import_region_arlo_id'        => '(import_id, e_region, e_arlo_id)',
						'e_import_region_parent_arlo_id' => '(import_id, e_region, e_parent_arlo_id)',
					],
					'arlo_onlineactivities' => [
						'oa_arlo_id' => '(oa_arlo_id)',
					],
				];

				foreach ( $import_indexes as $table_suffix => $indexes ) {
					$table_name = $wpdb->prefix . $table_suffix;
					$table_like = $wpdb->esc_like( $table_name );
					$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ), 0, 0 );

					if ( is_null( $exists ) ) {
						continue;
					}

					foreach ( $indexes as $index_name => $columns ) {
						$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW KEYS FROM %i WHERE Key_name = %s', $table_name, $index_name ), 0, 0 );

						if ( is_null( $exists ) ) {
							$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY %i ' . $columns, $table_name, $index_name ) );
						}
					}
				}

				$this->plugin->get_schema_manager()->install_incremental_schema();
				break;

		}
		// phpcs:enable
	}

	private function do_update( $version ) {
		switch ( $version ) {
			case '2.2.1':
				//Add [arlo_no_event_text] shortcode to the templates
				$update_templates = [ 'eventsearch', 'events' ];
				$saved_templates  = arlo_get_option( 'templates' );

				foreach ( $update_templates as $id ) {
					if ( ! empty( $saved_templates[ $id ]['html'] ) ) {
						$content = $saved_templates[ $id ]['html'];

						if ( strpos( $content, "arlo_no_event_text" ) === false ) {
							$shortcode    = "\n[arlo_no_event_text]\n";
							$append_after = "[arlo_category_footer]";

							//try to find the [arlo_category_footer], and append before
							$pos = strpos( $content, $append_after );
							if ( $pos !== false ) {
								$pos += strlen( $append_after );
							} else {
								$pos = strlen( $content );
							}

							$saved_templates[ $id ]['html'] = substr_replace( $content, $shortcode, $pos, 0 );
						}
					}
				}

				arlo_set_option( 'templates', $saved_templates );
				break;
			case '2.3':
				$saved_templates = arlo_get_option( 'templates' );

				//Add [arlo_template_region_selector] shortcode to the event template
				if ( ! empty( $saved_templates['event']['html'] ) && strpos( $saved_templates['event']['html'], "[arlo_template_region_selector]" ) === false ) {
					$saved_templates['event']['html'] = "[arlo_template_region_selector]\n" . $saved_templates['event']['html'];
				}

				//Add [arlo_template_region_selector] shortcode to the catalogue template
				if ( ! empty( $saved_templates['events']['html'] ) && strpos( $saved_templates['event']['html'], "[arlo_template_region_selector]" ) === false ) {
					$saved_templates['events']['html'] = "[arlo_template_region_selector]\n" . $saved_templates['events']['html'];
				}

				//Add [arlo_template_search_region_selector] shortcode to the event search template
				if ( ! empty( $saved_templates['eventsearch']['html'] ) && strpos( $saved_templates['event']['html'], "[arlo_template_search_region_selector]" ) === false ) {
					$saved_templates['eventsearch']['html'] = "[arlo_template_search_region_selector]\n" . $saved_templates['eventsearch']['html'];
				}

				//Add [arlo_upcoming_region_selector] shortcode to the upcoming events list template
				if ( ! empty( $saved_templates['upcoming']['html'] ) && strpos( $saved_templates['event']['html'], "[arlo_upcoming_region_selector]" ) === false ) {
					$saved_templates['upcoming']['html'] = "[arlo_upcoming_region_selector]\n" . $saved_templates['upcoming']['html'];
				}

				arlo_set_option( 'templates', $saved_templates );
				break;

			case '2.3.5':
				wp_clear_scheduled_hook( 'arlo_import' );

				if ( ! wp_next_scheduled( 'arlo_scheduler' ) ) {
					wp_schedule_event( time(), 'minutes_5', 'arlo_scheduler' );
				}

				break;

			case '2.4':
				//Add [event_template_register_interest] shortcode to the event template
				$update_templates = [ 'event' ];
				$saved_templates  = arlo_get_option( 'templates' );

				foreach ( $update_templates as $id ) {
					if ( ! empty( $saved_templates[ $id ]['html'] ) ) {
						$content = $saved_templates[ $id ]['html'];

						if ( strpos( $content, "[arlo_event_template_register_interest]" ) === false ) {
							$shortcode     = "\n[arlo_event_template_register_interest]\n";
							$append_before = [
								"[arlo_suggest_datelocation",
								"[arlo_content_field_item",
								"<h3>Similar courses",
							];
							foreach ( $append_before as $target ) {
								//try to find the given shortcode, and append before
								$pos = strpos( $content, $target );
								if ( $pos !== false ) {
									break;
								}
							}

							if ( $pos === false ) {
								$pos = strlen( $content );
							}

							$saved_templates[ $id ]['html'] = substr_replace( $content, $shortcode, $pos, 0 );
						}
					}
				}

				wp_clear_scheduled_hook( 'arlo_scheduler' );
				wp_schedule_event( time(), 'minutes_5', 'arlo_scheduler' );

				arlo_set_option( 'templates', $saved_templates );

				if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
					$message = [
						'<p>' . esc_html__( 'Arlo for WordPress requires that the Cron feature in WordPress is enabled, or replaced with an external trigger.', 'arlo-training-and-event-management-system' ) . ' ' . wp_kses(
							/* translators: %s: arlo for wordpress document link */
							sprintf( __( '<a target="_blank" href="%s">View documentation</a> for more information.', 'arlo-training-and-event-management-system' ), 'https://developer.arlo.co/doc/wordpress/import#import-wordpress-cron' ),
							[
								'a' => [
									'href'   => [],
									'target' => [],
								],
							]
						) . '</p>',
						'<p>' . esc_html__( 'You may safely dismiss this warning if your system administrator has installed an external Cron solution.', 'arlo-training-and-event-management-system' ) . '</p>',
					];

					$this->message_handler->set_message( 'error', esc_html__( 'WordPress Cron is disabled', 'arlo-training-and-event-management-system' ), implode( '', $message ), false );
				}

				break;

			case '3.0':
				global $wpdb;
				$import_id = get_option( 'arlo_import_id', '' );
				if ( ! empty( $import_id ) ) {
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Data migration. Direct database query is required for custom table. Do not need cache for one time data migration
					//update post_id in the templates table
					$items = $wpdb->get_results(
						$wpdb->prepare(
							"
					SELECT
						et_id,
						et_post_name
					FROM
						{$wpdb->prefix}arlo_eventtemplates
					WHERE
						import_id = %d
					AND
						(et_post_id IS NULL OR et_post_id = 0)
					",
							$import_id
						)
					);
					if ( is_array( $items ) && count( $items ) ) {
						foreach ( $items as $key => $item ) {
							$post = arlo_get_post_by_name( $item->et_post_name, 'arlo_event' );
							if ( ! is_null( $post ) && ! empty( $post->ID ) && $post->ID > 0 ) {

								$wpdb->update( $wpdb->prefix . 'arlo_eventtemplates', array( 'et_post_id' => $post->ID ), array( 'et_id' => $item->et_id ) );
							}
						}
					}

					//update post_id in the presenters table
					$items = $wpdb->get_results(
						$wpdb->prepare(
							"
					SELECT
						p_id,
						p_post_name
					FROM
						{$wpdb->prefix}arlo_presenters
					WHERE
						import_id = %d
					AND
						(p_post_id IS NULL OR p_post_id = 0)
					",
							$import_id
						)
					);
					if ( is_array( $items ) && count( $items ) ) {
						foreach ( $items as $key => $item ) {
							$post = arlo_get_post_by_name( $item->p_post_name, 'arlo_presenter' );
							if ( ! is_null( $post ) && ! empty( $post->ID ) && $post->ID > 0 ) {

								$wpdb->update( $wpdb->prefix . 'arlo_presenters', array( 'p_post_id' => $post->ID ), array( 'p_id' => $item->p_id ) );
							}
						}
					}

					//update post_id in the venues table
					$items = $wpdb->get_results(
						$wpdb->prepare(
							"
					SELECT
						v_id,
						v_post_name
					FROM
						{$wpdb->prefix}arlo_venues
					WHERE
						import_id = %d
					AND
						(v_post_id IS NULL OR v_post_id = 0)
					",
							$import_id
						)
					);
					if ( is_array( $items ) && count( $items ) ) {
						foreach ( $items as $key => $item ) {
							$post = arlo_get_post_by_name( $item->v_post_name, 'arlo_venue' );
							if ( ! is_null( $post ) && ! empty( $post->ID ) && $post->ID > 0 ) {

								$wpdb->update( $wpdb->prefix . 'arlo_venues', array( 'v_post_id' => $post->ID ), array( 'v_id' => $item->v_id ) );
							}
						}
					}
					// phpcs:enable
				}

				$theme_id = 'custom';
				update_option( 'arlo_theme', $theme_id, 1 );

				$settings = get_option( 'arlo_settings' );

				$theme_settings = $this->theme_manager->get_themes_settings();

				$stored_themes_settings[ $theme_id ]            = $theme_settings[ $theme_id ];
				$stored_themes_settings[ $theme_id ]->templates = $this->theme_manager->load_default_templates( $theme_id );

				foreach ( $settings['templates'] as $page => $template ) {
					$stored_themes_settings[ $theme_id ]->templates[ $page ]['html'] = $settings['templates'][ $page ]['html'];
				}

				update_option( 'arlo_themes_settings', $stored_themes_settings, 1 );

				//kick off an import
				if ( get_option( 'arlo_import_disabled', '0' ) != '1' ) {
					$this->plugin->get_scheduler()->set_task( "import", -1 );
				}
				break;

			case '3.1':
				delete_metadata( 'user', 0, 'arlo-developer-admin-notice', '0', true );
				break;

			case '3.1.3':
				//kick off an import
				if ( get_option( 'arlo_import_disabled', '0' ) != '1' ) {
					$this->plugin->get_scheduler()->set_task( "import", -1 );
				}
				break;
			case '3.2':
				//try to add OA page (don't publish)
				$page_name = 'oa';

				$page_ids = Arlo_For_Wordpress::add_pages( $page_name );
				break;
			case '3.3':
				$theme_settings = get_option( 'arlo_themes_settings', [] );
				$settings       = get_option( 'arlo_settings', [] );
				$regions        = get_option( 'arlo_regions' );

				//Add Schedule template and OA template
				$page_name = 'schedule';
				$page_ids  = Arlo_For_Wordpress::add_pages( $page_name );

				$selected_theme_id = get_option( 'arlo_theme' );

				foreach ( $theme_settings as $theme_name => $theme_setting ) {
					if ( $selected_theme_id !== 'custom' ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads bundled local theme template files during a version migration; wp_remote_get() is for remote URLs.
						$oa_template = file_get_contents( $theme_settings[ $theme_name ]->dir . '/templates/oa.tpl' );

						if ( ! empty( $oa_template ) ) {
							if ( ! array_key_exists( 'oa', $theme_setting->templates ) ) {
								$theme_settings[ $theme_name ]->templates['oa'] = array( 'html' => $oa_template );
							}

							if ( $theme_name == $selected_theme_id ) {
								if ( ! array_key_exists( 'oa', $settings['templates'] ) ) {
									$settings['templates']['oa'] = array( 'html' => $oa_template );
								}
							}
						}

						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads bundled local theme template files during a version migration; wp_remote_get() is for remote URLs.
						$schedule_template = file_get_contents( $theme_settings[ $theme_name ]->dir . '/templates/schedule.tpl' );

						if ( ! empty( $schedule_template ) ) {
							if ( ! array_key_exists( 'schedule', $theme_setting->templates ) ) {
								$theme_settings[ $theme_name ]->templates['schedule'] = array( 'html' => $schedule_template );
							}

							if ( $theme_name == $selected_theme_id ) {
								if ( ! array_key_exists( 'schedule', $settings['templates'] ) ) {
									$settings['templates']['schedule'] = array( 'html' => $schedule_template );
								}
							}
						}
					}
				}

				$settings['regionid'] = array_map( 'strtoupper', $settings['regionid'] );
				$regions              = array_change_key_case( $regions, CASE_UPPER );

				//Add rich snippet shortcodes to templates
				$settings = $this->update_template( $settings, 'event', false, "[arlo_event_template_rich_snippet]" );
				$settings = $this->update_template( $settings, 'event', '[/arlo_event_list_item]', "[arlo_event_rich_snippet]" );
				$settings = $this->update_template( $settings, 'event', '[/arlo_oa_list_item]', "[arlo_oa_rich_snippet]" );

				$settings = $this->update_template( $settings, 'presenter', false, "[arlo_presenter_rich_snippet]" );

				$settings = $this->update_template( $settings, 'presenters', '[/arlo_presenter_list_item]', "[arlo_presenter_rich_snippet]" );

				$settings = $this->update_template( $settings, 'venue', false, "[arlo_venue_rich_snippet]" );

				$settings = $this->update_template( $settings, 'venues', '[/arlo_venue_list_item]', "[arlo_venue_rich_snippet]" );

				$settings = $this->update_template( $settings, 'events', '[/arlo_event_template_list_item]', "[arlo_event_template_rich_snippet]" );

				$settings = $this->update_template( $settings, 'eventsearch', '[/arlo_event_template_list_item]', "[arlo_event_template_rich_snippet]" );

				$settings = $this->update_template( $settings, 'upcoming', '[/arlo_upcoming_list_item]', "[arlo_event_rich_snippet]" );

				$settings = $this->update_template( $settings, 'oa', '[/arlo_onlineactivites_list_item]', "[arlo_oa_rich_snippet]" );

				update_option( 'arlo_themes_settings', $theme_settings );
				update_option( 'arlo_settings', $settings );
				update_option( 'arlo_regions', $regions );
				break;

			case '3.5':
				//update filter settings, if there is
				$filter_settings = get_option( 'arlo_filter_settings', [] );

				if ( isset( $filter_settings['template'] ) ) {
					$filter_settings['events'] = $filter_settings['template'];
					unset( $filter_settings['template'] );
				}

				if ( isset( $filter_settings['arlohiddenfilters'] ) && isset( $filter_settings['arlohiddenfilters']['template'] ) ) {
					$filter_settings['arlohiddenfilters']['events'] = $filter_settings['arlohiddenfilters']['template'];
					unset( $filter_settings['arlohiddenfilters']['template'] );
				}

				update_option( 'arlo_filter_settings', $filter_settings );
				break;

			case '3.5.1':
				//delete cookies
				$urlparts = wp_parse_url( site_url() );
				$domain   = $urlparts['host'];

				unset( $_COOKIE['arlo-nav-tab'] );
				setcookie( 'arlo-nav-tab', '', -1, '/', $domain );

				unset( $_COOKIE['arlo-vertical-tab'] );
				setcookie( 'arlo-vertical-tab', '', -1, '/', $domain );

				break;

			case '3.6':
				//update filter settings, if there is
				$filter_settings = get_option( 'arlo_filter_settings', [] );

				//use numeric key for filters, etc
				$new_settings_array = [];
				foreach ( $filter_settings as $page => $filter_options ) {
					if ( $page == 'arlohiddenfilters' ) {
						continue;
					}

					if ( ! isset( $new_settings_array[ $page ] ) ) {
						$new_settings_array[ $page ] = [];
					}

					foreach ( $filter_options as $group => $filters ) {
						if ( ! isset( $new_settings_array[ $page ][ $group ] ) ) {
							$new_settings_array[ $page ][ $group ] = [];
						}

						switch ( $group ) {
							case 'category':
								break;

							case 'delivery':
								foreach ( $filters as $old_value => $new_value ) {
									$delivery_key = array_search( $old_value, \Arlo_For_Wordpress::$delivery_labels, true );
									if ( $delivery_key !== false && ! empty( $new_value ) ) {
										$new_settings_array[ $page ][ $group ][ $delivery_key ] = $new_value;
									}
								}
								break;
							default:
								foreach ( $filters as $old_value => $new_value ) {
									if ( ! empty( $new_value ) ) {
										$new_settings_array[ $page ][ $group ][ $old_value ] = $new_value;
									}
								}
								break;
						}
					}
				}
				if ( isset( $filter_settings['arlohiddenfilters'] ) && is_array( $filter_settings['arlohiddenfilters'] ) ) {
					foreach ( $filter_settings['arlohiddenfilters'] as $page => $filter_options ) {
						if ( ! isset( $new_settings_array['hiddenfilters'][ $page ] ) ) {
							$new_settings_array['hiddenfilters'][ $page ] = [];
						}

						foreach ( $filter_options as $group => $filters ) {
							if ( ! isset( $new_settings_array['hiddenfilters'][ $page ][ $group ] ) ) {
								$new_settings_array['hiddenfilters'][ $page ][ $group ] = [];
							}

							switch ( $group ) {
								case 'category':
									break;

								case 'delivery':
									foreach ( $filters as $filter ) {
										$delivery_key = array_search( $filter, \Arlo_For_Wordpress::$delivery_labels, true );
										if ( $delivery_key !== false ) {
											$new_settings_array['hiddenfilters'][ $page ][ $group ][] = $delivery_key;
										}
									}
									break;
								default:
									foreach ( $filters as $filter ) {
										if ( ! empty( $filter ) ) {
											$new_settings_array['hiddenfilters'][ $page ][ $group ][] = $filter;
										}
									}
									break;
							}
						}
					}
				}

				//now remove by-page filters and only keep hidden and delivery
				$filter_settings    = $new_settings_array;
				$is_notice_required = false;
				$filter_parts       = [ 'showonlyfilters', 'hiddenfilters', 'generic' ];

				//create new generic section
				foreach ( $filter_settings as $page => $filter_options ) {
					if ( ! in_array( $page, $filter_parts, true ) ) {
						foreach ( $filter_options as $group => $filters ) {
							//copy first delivery filters
							if ( $group == 'delivery' && empty( $filter_settings['generic'] ) ) {
								$filter_settings['generic'] = array( 'delivery' => $filters );
								break;
							}
						}
					}
				}

				//remove obsolete filter settings
				foreach ( $filter_settings as $page => $filter_options ) {
					if ( ! in_array( $page, $filter_parts, true ) ) {
						//remove the whole page
						unset( $filter_settings[ $page ] );
						$is_notice_required = true;
					} else {
						foreach ( $filter_options as $group => $filters ) {
							//remove all non-delivery groups
							if ( $group != 'delivery' ) {
								unset( $filter_settings[ $page ][ $group ] );
							}
						}
					}
				}

				if ( $is_notice_required ) {
					$message = [
						'<p>' . esc_html__( 'The Filters tab has been removed and previous filter settings are no longer available. The delivery filter settings are now configured from the General Settings tab and apply to all Arlo for WordPress pages. Locations, tags and categories should be managed from the Arlo management platform.', 'arlo-training-and-event-management-system' ) . '</p>',
					];
					if ( ! $this->message_handler->set_message( 'import_error', esc_html__( 'Filter settings deleted', 'arlo-training-and-event-management-system' ), implode( '', $message ), true ) ) {
						Logger::log( "Couldn't create Arlo 3.6 filters settings lost notice message" );
					}
				}

				update_option( 'arlo_filter_settings', $filter_settings );
				break;

			case '4.0':
				$settings                  = get_option( 'arlo_settings' );
				$settings['keep_settings'] = "1";
				update_option( 'arlo_settings', $settings );
				break;

			case '5.1.0':
				// Backfill deployment_mode introduced in 5.1.0.
				$settings = get_option( 'arlo_settings' );
				if ( is_array( $settings ) && ! isset( $settings['deployment_mode'] ) ) {
					$settings['deployment_mode'] = \Arlo_For_Wordpress::get_deployment_mode_default( $settings['platform_name'] ?? '' );
					// A plain update_option() would run the settings form sanitizer, which turns a stored '0' checkbox value into '1'.
					SettingsPersistence::write_as_system( $settings );
				}
				break;

			case '5.2.0':
				$this->terminate_inflight_import();
				$this->maybe_mark_powered_by_upgrade_notice();
				$this->remove_retired_send_data_setting();
				$this->remove_retired_review_prompt();

				if ( $this->should_refresh_after_region_column_widening() ) {
					$this->refresh_import_after_update = true;
				}

				break;

		}
	}

	/**
	 * Ends any import that an earlier release left in flight, and drops its downloaded parts.
	 *
	 * Earlier releases persisted checkpoints and platform responses in a shape this release cannot resume, so a
	 * resumed import fails on missing snapshot metadata and retries until it gives up. The abandoned dataset is
	 * not live; the next completed import removes its rows.
	 *
	 * This is best effort, to reduce friction during upgrades. An upgrade step cannot fail, and if the cleanup does not
	 * happen the resumed import is still likely to fail gracefully on its own.
	 *
	 * @return void
	 */
	private function terminate_inflight_import(): void {
		global $wpdb;

		// Guards against overlapping upgrade requests: a delayed one must not end the repair import the first one queued
		// after stamping the version. The stored value is read directly because the options cache can still be stale.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bypasses the options cache on purpose; the cached arlo_plugin_version can be stale while another request upgrades.
		$stored_version = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, 'arlo_plugin_version' ) );
		if ( is_string( $stored_version ) && version_compare( $stored_version, self::VERSION, '>=' ) ) {
			return;
		}

		$this->plugin->get_scheduler()->terminate_all_active_import_tasks();
		$this->plugin->get_importing_parts()->delete_all_import_parts();
	}

	/**
	 * Drops the stored value of the retired "Allow to send data to Arlo" setting.
	 *
	 * The plugin no longer sends logs to Arlo, so the opt-in has no remaining effect. Other settings are untouched.
	 *
	 * @return void
	 */
	private function remove_retired_send_data_setting(): void {
		$settings = get_option( 'arlo_settings', [] );

		if ( is_array( $settings ) && array_key_exists( 'arlo_send_data', $settings ) ) {
			unset( $settings['arlo_send_data'] );
			// A plain update_option() would run the settings form sanitizer, which turns a stored '0' checkbox value into '1'.
			SettingsPersistence::write_as_system( $settings );
		}
	}

	/**
	 * Removes the stored state of the retired review prompt: its schedule option and any pending or dismissed prompt rows.
	 *
	 * @return void
	 */
	private function remove_retired_review_prompt(): void {
		delete_option( 'arlo_review_notice_date' );
		$this->message_handler->delete_messages( 'review' );
	}

	/**
	 * Selects the "now disabled by default" opt-in banner wording for a site that upgrades while undecided.
	 *
	 * The credit used to be on by default, so a site that has already recorded a choice is left alone.
	 *
	 * @return void
	 */
	private function maybe_mark_powered_by_upgrade_notice(): void {
		$settings = get_option( 'arlo_settings', [] );
		if ( is_array( $settings ) && ! array_key_exists( \Arlo_For_Wordpress::POWERED_BY_SETTING, $settings ) ) {
			update_option( \Arlo_For_Wordpress::POWERED_BY_UPGRADE_NOTICE_OPTION, '1' );
			// Widget output cached while the credit was on by default must not keep showing it.
			CacheControl::invalidate_upcoming_widget_cache();
		}
	}

	private function should_refresh_after_region_column_widening(): bool {
		global $wpdb;
		$regions = get_option( 'arlo_regions', [] );

		if ( ! is_array( $regions ) || count( $regions ) === 0 ) {
			return false;
		}

		foreach ( array_keys( $regions ) as $region_id ) {
			if ( strlen( (string) $region_id ) > self::PRE_VERSION_5_2_0_REGION_MAX_LENGTH ) {
				return true;
			}
		}

		$region_columns    = [
			'arlo_eventtemplates'   => 'et_region',
			'arlo_events'           => 'e_region',
			'arlo_onlineactivities' => 'oa_region',
			'arlo_offers'           => 'o_region',
		];
		$current_import_id = get_option( 'arlo_import_id', '' );
		$import_id         = ( is_numeric( $current_import_id ) && (int) $current_import_id > 0 ) ? (int) $current_import_id : null;

		// Existing varchar(5) rows cannot tell us what was truncated. A stored value
		// at the old maximum is the only durable local evidence that region IDs may
		// have hit the column limit, so only then do we request a fresh shadow import.
		// Prefer the current live import's rows when available; stale or abandoned
		// imports should not decide whether the upgraded site needs a refresh.
		foreach ( $region_columns as $table_suffix => $column ) {
			$table_name = $wpdb->prefix . $table_suffix;
			$query      = ( $import_id !== null )
				? $wpdb->prepare(
					'SELECT 1 FROM %i WHERE CHAR_LENGTH(%i) = %d AND import_id = %d LIMIT 1',
					$table_name,
					$column,
					self::PRE_VERSION_5_2_0_REGION_MAX_LENGTH,
					$import_id
				)
				: $wpdb->prepare(
					'SELECT 1 FROM %i WHERE CHAR_LENGTH(%i) = %d LIMIT 1',
					$table_name,
					$column,
					self::PRE_VERSION_5_2_0_REGION_MAX_LENGTH
				);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- One-time upgrade check using a prepared query against trusted plugin table/column names to decide whether a data refresh is needed after widening region columns.
			$has_old_limit_value = $wpdb->get_var( $query );
			if ( ! empty( $has_old_limit_value ) ) {
				return true;
			}
		}

		return false;
	}

	private function update_template( $templates, $page, $insert_before, $shortcode ) {
		if ( ! empty( $templates['templates'][ $page ]['html'] ) && strpos( $templates['templates'][ $page ]['html'], $shortcode ) === false ) {
			$shortcode = "\n" . $shortcode . "\n";

			if ( $insert_before ) {
				$pos                                     = strpos( $templates['templates'][ $page ]['html'], $insert_before );
				$templates['templates'][ $page ]['html'] = substr_replace( $templates['templates'][ $page ]['html'], $shortcode, $pos, 0 );
			} else {
				$templates['templates'][ $page ]['html'] = $templates['templates'][ $page ]['html'] . $shortcode;
			}
		}

		return $templates;
	}
}
