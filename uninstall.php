<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo Software <support@arlo.co>
 * @license   GPL-2.0-or-later
 * @link      https://arlo.co
 * @copyright 2015-2026 Arlo Software
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Run uninstall cleanup for plugin-owned data and scheduled work.
 *
 * Uninstall must be self-contained even when WordPress does not run a clean
 * deactivation pass first. Clear scheduled hooks before deleting the tables and
 * options those callbacks would read if a stale cron event fired later.
 *
 * @since 1.0
 * @return void
 */
function arlo_uninstall() {

	arlo_clear_scheduled_hooks();

	arlo_delete_tables();

	arlo_delete_custom_posts();

	arlo_delete_options();

	arlo_delete_transients();

	arlo_delete_cookies();

	// Nuke all settings if "Keep settings..." is unchecked
	$settings = get_option( 'arlo_settings', [] );
	if ( empty( $settings['keep_settings'] ) ) {

		arlo_delete_important_options();

		arlo_delete_user_meta();

	}
}

/**
 * Clear plugin-owned scheduled hooks during uninstall.
 *
 * Deactivation normally clears these hooks first, but uninstall should also be
 * self-contained so recurring or one-shot scheduler events cannot survive a
 * missed deactivation cleanup.
 *
 * @return void
 */
function arlo_clear_scheduled_hooks() {
	wp_clear_scheduled_hook( 'arlo_scheduler' );
	wp_clear_scheduled_hook( 'arlo_scheduler', array( 1 ) );
	wp_clear_scheduled_hook( 'arlo_scheduler', array( 2 ) );
	wp_clear_scheduled_hook( 'arlo_scheduler', array( 3 ) );
	wp_clear_scheduled_hook( 'arlo_set_import' );
	wp_clear_scheduled_hook( 'arlo_import' );
}

/**
 * Return the exact plugin-owned custom table names for the current prefix.
 *
 * SchemaManager owns the allowlist, so a table added there is dropped without
 * editing this file. The literal fallback keeps uninstall working if that file
 * cannot be loaded; a unit test keeps it identical to the SchemaManager list.
 *
 * @since 5.2.0
 * @param string $prefix The WordPress table prefix.
 * @return string[] Fully qualified plugin-owned table names.
 */
function arlo_get_owned_table_names( $prefix ) {
	$schema_manager_file = __DIR__ . '/includes/provisioning/arlo-schema-manager.php';

	if ( is_readable( $schema_manager_file ) ) {
		require_once $schema_manager_file;
	}

	if ( class_exists( '\\ArloTraining\\Provisioning\\SchemaManager', false ) && method_exists( '\\ArloTraining\\Provisioning\\SchemaManager', 'get_owned_table_names' ) ) {
		return \ArloTraining\Provisioning\SchemaManager::get_owned_table_names( $prefix );
	}

	$suffixes = [
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

	return array_map(
		static function ( $suffix ) use ( $prefix ) {
			return $prefix . $suffix;
		},
		$suffixes
	);
}

/**
 * Drop plugin-owned custom database tables.
 *
 * The table list is the exact plugin-owned allowlist; no WordPress core or
 * third-party tables, including unrelated {$prefix}arlo_* tables, are selected
 * dynamically.
 *
 * @since 1.0
 * @return void
 */
function arlo_delete_tables() {
	global $wpdb;

	$table_names = arlo_get_owned_table_names( $wpdb->prefix );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct database query is required for uninstall; table names come from the fixed plugin-owned allowlist and the WordPress prefix.
	$wpdb->query( 'DROP TABLE IF EXISTS ' . implode( ', ', $table_names ) );
}

/**
 * Delete plugin-owned custom post records.
 *
 * The query is scoped to Arlo post types only, including legacy and current
 * post type names used by imported event, presenter, and venue records.
 *
 * @since 1.0
 * @return void
 */
function arlo_delete_custom_posts() {

	global $wpdb;

	$sql = "DELETE FROM $wpdb->posts WHERE post_type IN ('arlo_events', 'arlo_presenters', 'arlo_venues', 'arlo_event', 'arlo_presenter', 'arlo_venue')";

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for uninstall; the table name is provided by WordPress and post types are fixed literals.
	$wpdb->query( $sql );
}

/**
 * Delete runtime plugin options while preserving admin-managed settings.
 *
 * Important settings are kept unless the uninstall setting explicitly allows
 * arlo_delete_important_options() to remove them.
 *
 * @since 1.0
 * @return void
 */
function arlo_delete_options() {

	$options = [
		'arlo_import_id',
		'arlo_customcss',
		'arlo_customcss_timestamp',
		'arlo_last_import',
		'arlo_import_cleanup_pending',
		'arlo_new_url_structure',
		'arlo_plugin_version',
		'arlo_import_disabled',
		'arlo_plugin_disabled',
		'arlo_updated',
		'arlo_platform_access_failure_count',
		'arlo_platform_access_first_failure_at',
		'arlo_platform_access_last_failure_at',
		'arlo_import_connection_health_disabled',
		'arlo_import_disabled_message',
		'arlo_import_disabled_since',
		'arlo_rejected_callback_log_state',
		'arlo_rejected_matched_callback_log_state',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Delete plugin-owned transients, including their expiry rows.
 *
 * These are cached results and one-time messages, so they are removed whether
 * or not settings are kept. Only keys beginning with arlo_ are matched.
 *
 * @since 5.2.0
 * @return void
 */
function arlo_delete_transients() {

	global $wpdb;

	$prefixes = [
		'_transient_arlo_',
		'_transient_timeout_arlo_',
		'_site_transient_arlo_',
		'_site_transient_timeout_arlo_',
	];

	foreach ( $prefixes as $prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for uninstall; the prefixes are fixed literals scoped to plugin-owned transient keys.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
	}
}

/**
 * Delete the per-user admin notice state the plugin stores.
 *
 * This runs only when the uninstall setting does not request keeping settings,
 * because dismissed notices are stored preferences rather than cached data.
 *
 * @since 5.2.0
 * @return void
 */
function arlo_delete_user_meta() {

	$meta_keys = [
		'arlo-welcome-admin-notice',
		'arlo-newpages-admin-notice',
		'arlo-wp-video',
		'arlo-page-setup-admin-notice',
		'arlo-page-setup-admin-notice-broken-state',
	];

	foreach ( $meta_keys as $meta_key ) {
		delete_metadata( 'user', 0, $meta_key, '', true );
	}
}

/**
 * Delete stored plugin settings and widget options.
 *
 * This runs only when the uninstall setting does not request keeping settings.
 *
 * @since 4.0
 * @return void
 */
function arlo_delete_important_options() {

	$options = [
		'arlo_schema_version',
		'arlo_settings',
		'arlo_regions',
		'arlo_theme',
		'arlo_themes_settings',
		'arlo_filter_settings',
		'arlo_page_filter_settings',
		'arlo_review_notice_date',
		'arlo_powered_by_upgrade_notice',
		'widget_arlo-for-wordpress-upcoming-widget',
		'widget_arlo-for-wordpress-categories-widget',
		'widget_arlo-for-wordpress-search-widget',
		'widget_arlo-for-wordpress-region-selector',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Expire plugin-owned admin UI cookies.
 *
 * @return void
 */
function arlo_delete_cookies() {
	setcookie( 'arlo-vertical-tab', '', -1, '/' );
	setcookie( 'arlo-nav-tab', '', -1, '/' );
}

arlo_uninstall();
