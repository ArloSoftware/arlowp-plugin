<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      https://arlo.co
 * @copyright 2018 Arlo
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * The uninstall process 
 * @since: 1.0
 * @param:
 * @return:
 */
function arlo_uninstall() {

	arlo_delete_tables();

	arlo_delete_custom_posts();

	arlo_delete_options();

	arlo_delete_cookies();

	// Nuke all settings if "Keep settings..." is unchecked
	$settings = get_option('arlo_settings', []);
	if (empty($settings['keep_settings'])) {

		arlo_delete_important_options();

	}
}

/**
 * Drop all tables of plugin 
 * @since: 1.0
 * @param:
 * @return:
 */
function arlo_delete_tables() {
	//should use the SchemaManager->delete_tables
	
    global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Direct database query is required for uninstall process, using fixed table names. Do not need to cache result here. Schema change is trusted and only applied to arlo tables
    $wpdb->query("DROP TABLE IF EXISTS 
		{$wpdb->prefix}arlo_async_tasks,
		{$wpdb->prefix}arlo_async_task_data, 
		{$wpdb->prefix}arlo_categories, 
		{$wpdb->prefix}arlo_contentfields, 
		{$wpdb->prefix}arlo_events, 		
		{$wpdb->prefix}arlo_events_presenters, 
		{$wpdb->prefix}arlo_eventtemplates, 
		{$wpdb->prefix}arlo_eventtemplates_categories, 		
		{$wpdb->prefix}arlo_eventtemplates_presenters, 
		{$wpdb->prefix}arlo_onlineactivities, 
		{$wpdb->prefix}arlo_onlineactivities_tags, 
		{$wpdb->prefix}arlo_offers, 		
		{$wpdb->prefix}arlo_presenters, 
		{$wpdb->prefix}arlo_venues, 
		{$wpdb->prefix}arlo_events_tags, 
		{$wpdb->prefix}arlo_eventtemplates_tags,  
		{$wpdb->prefix}arlo_tags,  
		{$wpdb->prefix}arlo_timezones,  
		{$wpdb->prefix}arlo_messages, 
		{$wpdb->prefix}arlo_log,
		{$wpdb->prefix}arlo_import,
		{$wpdb->prefix}arlo_import_parts,
		{$wpdb->prefix}arlo_import_lock");

}

/**
 * Delete Arlo created custom posts 
 * @since: 1.0
 * @param:
 * @return:
 */
function arlo_delete_custom_posts() {

	global $wpdb;

	$sql = "DELETE FROM $wpdb->posts WHERE post_type IN ('arlo_events', 'arlo_presenters', 'arlo_venues', 'arlo_event', 'arlo_presenter', 'arlo_venue')";

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for uninstall process, input is fixed SQL string.
    $wpdb->query($sql);

}

/**
 * Delete Arlo wp-options records but keep important settings
 * @since: 1.0
 * @param:
 * @return:
 */
function arlo_delete_options() {

	$options = [
		'arlo_import_id',
		'arlo_customcss',
		'arlo_customcss_timestamp',
		'arlo_last_import',
		'arlo_new_url_structure',
		'arlo_plugin_version',
		'arlo_import_disabled',
		'arlo_plugin_disabled',
		'arlo_updated',
		'arlo_platform_access_failure_count',
		'arlo_platform_access_first_failure_at',
		'arlo_import_connection_health_disabled',
		'arlo_import_disabled_message',
		'arlo_import_disabled_since',
	];
	
	foreach ($options as $option) {
		delete_option($option);
	}
}

/**
 * Delete all Arlo wp-options records considered as important settings
 * @since: 4.0
 * @param:
 * @return:
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
		'widget_arlo-for-wordpress-upcoming-widget',
		'widget_arlo-for-wordpress-categories-widget',
		'widget_arlo-for-wordpress-search-widget',
		'widget_arlo-for-wordpress-region-selector',
	];
	
	foreach ($options as $option) {
		delete_option($option);
	}
}

function arlo_delete_cookies() {
	setcookie('arlo-vertical-tab', '', -1, '/');
	setcookie('arlo-nav-tab', '', -1, '/');
}

arlo_uninstall();

