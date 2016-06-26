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
 * Version:           2.2.1
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
 
//load extra functions
require_once( plugin_dir_path( __FILE__ ) . 'includes/functions.php' ); 
 
// load API files
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-api/Client.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-api/Transports/Wordpress.php' );

// Include Arlo classes
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-event-templates.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-events.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-categories.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-offers.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-venues.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-presenters.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/arlo-shortcodes.php');

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
