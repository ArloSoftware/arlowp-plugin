<?php
/**
 * Arlo Training Management Software.
 *
 * Imports published training data from Arlo and displays it on your WordPress site.
 *
 * @package   Arlo_For_Wordpress
 * @author    Arlo Software <support@arlo.co>
 * @license   GPL-2.0-or-later
 * @link      https://arlo.co
 * @copyright 2015-2026 Arlo Software
 *
 * @wordpress-plugin
 * Plugin Name:       Arlo Training Management Software
 * Plugin URI:        https://www.arlo.co/apps/wordpress-events-plugin
 * Description:       Connect your WordPress to Arlo
 * Version:           5.2.1
 * Requires at least: 7.0
 * Author:            Arlo Software
 * Author URI:        https://www.arlo.co
 * Text Domain:       arlo-training-and-event-management-system
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Constants
 *----------------------------------------------------------------------------*/

// mostly used for adding css class prefixes, if this is changed, the prefixes in the css will need to be changed too.
define( 'ARLO_PLUGIN_PREFIX', 'arlo' );
define( 'ARLO_PLUGIN_NAME', 'Arlo' );
define( 'ARLO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARLO_PLUGIN_ROOT_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'ARLO_FEATURE_DELTA_IMPORT' ) ) {
	define( 'ARLO_FEATURE_DELTA_IMPORT', 'delta_import' );
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

// load API files
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-api/Client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-api/Transports/Wordpress.php';

//include provisioning classes
require_once plugin_dir_path( __FILE__ ) . 'includes/provisioning/arlo-schema-manager.php';

//include exceptions
require_once plugin_dir_path( __FILE__ ) . 'includes/exceptions/arlo-exceptions.php';

// Cache Controller
require_once plugin_dir_path( __FILE__ ) . '/includes/arlo-cache-control.php';

//include extra classes
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-arrays.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-utilities.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-settingspersistence.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-customcss.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-customcsscompatibility.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-customcssmigration.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-templateaccess.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-featureflagmanager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-date-formatter.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-scheduler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-crypto.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-environment.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-userflash.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-notice-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-message-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-version-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-system-requirements.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-theme-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-timezone-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/arlo-redirect.php';

//include shortcodes
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-filters.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-categories.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-online-activities.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-templates.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-events.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-presenters.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-venues.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes/arlo-upcoming-events.php';

// Include Arlo entities
require_once plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-templates.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-events.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-online-activities.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-categories.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-offers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-venues.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-presenters.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/entities/arlo-tags.php';

//include importer
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/class-arlo-import-mutex.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-context.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-part-request.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-part-provider.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-http-transport.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-remote-snapshot-http-transport.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-manifest-request.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-manifest-provider.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-manifest.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-manifest-entry.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-manifest-parser.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-image-spec.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-fragment-content-provider.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-delta-coordinator-outcome.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-incremental-baseline-repository.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-incremental-run-repository.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-import-success-finalizer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-delta-run-parameters.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-event-availability-extractor.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-image-event-loader.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-event-availability-overlay-writer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-importer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-importing-parts.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-abstract-import-step.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-delta-run-coordinator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-fragment-step-plan.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-import-request.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-remote-http-snapshot-part-provider.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-remote-http-snapshot-manifest-provider.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-download.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-snapshot-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-process-fragment.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-timezones.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-presenters.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-venues.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-templates.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-events.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-online-activities.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-categories.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-category-items.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-category-depth.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/importer/arlo-finish.php';



// start the public plugin class
require_once plugin_dir_path( __FILE__ ) . 'public/class-arlo-for-wordpress.php';
require_once plugin_dir_path( __FILE__ ) . 'public/bootstrap.php';

// Activation and deactivation hooks. Deletion is handled by uninstall.php.
register_activation_hook( __FILE__, array( 'Arlo_For_Wordpress', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Arlo_For_Wordpress', 'deactivate' ) );

add_action( 'init', array( 'Arlo_For_Wordpress', 'init' ), 1 );
add_action( 'init', array( 'Arlo_For_Wordpress', 'get_instance' ), 2 );
add_action( 'init', array( 'Arlo_For_Wordpress', 'check_plugin_version' ) );
add_action( 'upgrader_process_complete', array( 'Arlo_For_Wordpress', 'bulk_plugin_updater' ), 10, 2 );
add_action(
	'wp_ajax_arlo_check_custom_css_migration',
	static function () {
		require_once __DIR__ . '/admin/class-arlo-for-wordpress-admin.php';
		Arlo_For_Wordpress_Admin::get_instance()->check_custom_css_migration();
	}
);

// Load widgets
require_once plugin_dir_path( __FILE__ ) . '/widgets/upcoming-widget/class-arlo-for-wordpress-upcoming-widget.php';
require_once plugin_dir_path( __FILE__ ) . '/widgets/categories-widget/class-arlo-for-wordpress-categories-widget.php';
require_once plugin_dir_path( __FILE__ ) . '/widgets/search-widget/class-arlo-for-wordpress-search-widget.php';
require_once plugin_dir_path( __FILE__ ) . '/widgets/region-selector/class-arlo-for-wordpress-region-selector.php';


/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

// The admin UI is not loaded for admin-ajax.php requests, which register their own handlers.
if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {

	require_once plugin_dir_path( __FILE__ ) . 'admin/class-arlo-for-wordpress-admin.php';
	add_action( 'init', array( 'Arlo_For_Wordpress_Admin', 'get_instance' ), 2 );

	require_once plugin_dir_path( __FILE__ ) . 'admin/includes/class-arlo-feature-pointer.php';

}
