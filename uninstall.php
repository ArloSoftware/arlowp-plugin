<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      http://arlo.co
 * @copyright 2015 Arlo
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
	$sql="
	DROP TABLE IF EXISTS " .
		$wpdb->prefix . "arlo_async_tasks," .
		$wpdb->prefix . "arlo_async_task_data," . 
		$wpdb->prefix . "arlo_categories," . 
		$wpdb->prefix . "arlo_contentfields, " . 
		$wpdb->prefix . "arlo_events, " . 		
		$wpdb->prefix . "arlo_events_presenters, " . 
		$wpdb->prefix . "arlo_eventtemplates," . 
		$wpdb->prefix . "arlo_eventtemplates_categories," . 		
		$wpdb->prefix . "arlo_eventtemplates_presenters, " .
		$wpdb->prefix . "arlo_onlineactivities, " . 
		$wpdb->prefix . "arlo_onlineactivities_tags, " .
		$wpdb->prefix . "arlo_offers, " . 		
		$wpdb->prefix . "arlo_presenters, " . 
		$wpdb->prefix . "arlo_venues, " . 
		$wpdb->prefix . "arlo_events_tags, " . 
		$wpdb->prefix . "arlo_eventtemplates_tags,  " . 
		$wpdb->prefix . "arlo_tags,  " . 
		$wpdb->prefix . "arlo_timezones,  " . 
		$wpdb->prefix . "arlo_messages, " .
		$wpdb->prefix . "arlo_log," .
		$wpdb->prefix . "arlo_import," .
		$wpdb->prefix . "arlo_import_lock";

	$wpdb->query($sql);

}

/**
 * Delete Arlo created custom posts 
 * @since: 1.0
 * @param:
 * @return:
 */
function arlo_delete_custom_posts() {

	global $wpdb;

	$sql = "DELETE FROM $wpdb->posts WHERE post_type IN ('arlo_events', 'arlo_presenters', 'arlo_venues')";

	$wpdb->query($sql);

}

/**
 * Delete Arlo wp-options records 
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
		'arlo_theme',
		'arlo_import_disabled',
		'arlo_plugin_disabled',
		'arlo_updated',
	];
	
	foreach ($options as $option) {
		delete_option($option);
	}
}

arlo_uninstall();

