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
function arlo_delete_tables()
{
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
		$wpdb->prefix . "arlo_offers, " . 		
		$wpdb->prefix . "arlo_presenters, " . 
		$wpdb->prefix . "arlo_venues, " . 
		$wpdb->prefix . "arlo_events_tags, " . 
		$wpdb->prefix . "arlo_eventtemplates_tags,  " . 
		$wpdb->prefix . "arlo_tags,  " . 
		$wpdb->prefix . "arlo_timezones,  " . 
		$wpdb->prefix . "arlo_timezones_olson, " . 
		$wpdb->prefix . "arlo_import_log," .
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

	delete_option('arlo_settings');

	delete_option('arlo_last_import');

}

arlo_uninstall();

?>
