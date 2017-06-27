<?php
/**
 * The WordPress Plugin Boilerplate.
 *
 * A foundation off of which to build well-documented WordPress plugins that
 * also follow WordPress Coding Standards and PHP best practices.
 *
 * @package   Arlo_For_Wordpress
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      http://arlo.co
 * @copyright 2015 Arlo
 *
 * @wordpress-plugin
 * Plugin Name:       Arlo
 * Description:       Connect your WordPress to Arlo
 * Version:           3.2
 * Author:            Arlo
 * Author URI:       http://arlo.co
 * Text Domain:       arlo-for-wordpress
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/ArloSoftware/arlowp-plugin
 */

/*----------------------------------------------------------------------------*
 * Constants
 * https://github.com/Preferizi/lea-plugin.learningsource.dev
 *----------------------------------------------------------------------------*/

// mostly used for adding css class prefixes, if this is changed, the prefixes in the css will need to be changed too.
define('ARLO_PLUGIN_PREFIX', 'arlo'); 
define('ARLO_PLUGIN_NAME', 'Arlo');
define('ARLO_PLUGIN_DIR', plugin_dir_path( __FILE__ ));

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

/*
 * @TODO:
 *
 * - replace `class-arlo-for-wordpress.php` with the name of the plugin's class file
 *
 */
 
// load API files
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-api/Client.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-api/Transports/Wordpress.php' );

//include database classes
require_once( plugin_dir_path( __FILE__ ) . 'includes/database/arlo-database-layer.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/database/arlo-wp-database-layer.php');

//include provisioning classes
require_once( plugin_dir_path( __FILE__ ) . 'includes/provisioning/arlo-schema-manager.php');

//include exceptions
require_once( plugin_dir_path( __FILE__ ) . 'includes/exceptions/arlo-exceptions.php');

//include extra classes
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-arrays.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-utilities.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-date-formatter.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-scheduler.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-crypto.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-environment.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-notice-handler.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-message-handler.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-version-handler.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-file-handler.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-logger.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-system-requirements.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-theme-manager.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-timezone-manager.php');
	
//include shortcodes
require_once( plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-shortcodes.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-categories.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-online-activities.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-templates.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-events.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-presenters.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-venues.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-upcoming-events.php');

// Include Arlo entities
require_once( plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-templates.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-events.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-online-activities.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-categories.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-offers.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-venues.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-presenters.php');

//include importer
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-importer.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-base-importer.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-import-request.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-download.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-process-fragment.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-timezones.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-presenters.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-venues.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-templates.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-events.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-online-activities.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-categories.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-category-items.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-category-depth.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-finish.php');

// start the public plugin class
require_once( plugin_dir_path( __FILE__ ) . 'public/class-arlo-for-wordpress.php' );
require_once( plugin_dir_path( __FILE__ ) . 'public/bootstrap.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 *
 * @TODO:
 *
 * - replace Arlo_For_Wordpress with the name of the class defined in
 *   `class-arlo-for-wordpress.php`
 */
register_activation_hook( __FILE__, array( 'Arlo_For_Wordpress', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Arlo_For_Wordpress', 'deactivate' ) );

/*
 * @TODO:
 *
 * - replace Arlo_For_Wordpress with the name of the class defined in
 *   `class-arlo-for-wordpress.php`
 */
add_action( 'plugins_loaded', array( 'Arlo_For_Wordpress', 'get_instance' ) );
add_action( 'init', array( 'Arlo_For_Wordpress', 'check_plugin_version' ) );
add_action( 'upgrader_process_complete', array( 'Arlo_For_Wordpress', 'bulk_plugin_updater' ), 10, 2 );

/*
 *
 * Load Widgets
 *
 */
require_once( plugin_dir_path( __FILE__ ) . '/widgets/upcoming-widget/class-arlo-for-wordpress-upcoming-widget.php' );
add_action( 'plugins_loaded', array( 'Arlo_For_Wordpress_Upcoming_Widget', 'get_instance' ) );
require_once( plugin_dir_path( __FILE__ ) . '/widgets/categories-widget/class-arlo-for-wordpress-categories-widget.php' );
add_action( 'plugins_loaded', array( 'Arlo_For_Wordpress_Categories_Widget', 'get_instance' ) );
require_once( plugin_dir_path( __FILE__ ) . '/widgets/search-widget/class-arlo-for-wordpress-search-widget.php' );
add_action( 'plugins_loaded', array( 'arlo_for_wordpress_search_widget', 'get_instance' ) );


/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

/*
 * @TODO:
 *
 * - replace `class-arlo-for-wordpress-admin.php` with the name of the plugin's admin file
 * - replace Arlo_For_Wordpress_Admin with the name of the class defined in
 *   `class-arlo-for-wordpress-admin.php`
 *
 * If you want to include Ajax within the dashboard, change the following
 * conditional to:
 *
 * if ( is_admin() ) {
 *   ...
 * }
 *
 * The code below is intended to to give the lightest footprint possible.
 */
if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {

	require_once( plugin_dir_path( __FILE__ ) . 'admin/class-arlo-for-wordpress-admin.php' );
	add_action( 'plugins_loaded', array( 'Arlo_For_Wordpress_Admin', 'get_instance' ) );

	require_once( plugin_dir_path( __FILE__ ) . 'admin/includes/class-feature-pointer.php' );

}
