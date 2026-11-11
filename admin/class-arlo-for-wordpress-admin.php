<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Arlo For WordPress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo Software <support@arlo.co>
 * @license   GPL-2.0-or-later
 * @link      https://arlo.co
 * @copyright 2015-2026 Arlo Software
 */

/**
 * Admin-side functionality: settings, menu pages and admin assets.
 *
 * @package Arlo_For_Wordpress_Admin
 */

use ArloTraining\CacheControl;
use ArloTraining\VersionHandler;
use ArloTraining\CustomCss;
use ArloTraining\SettingsPersistence;
use ArloTraining\CustomCssMigration;
use ArloTraining\Importer\ImportRequest;
use ArloTraining\Importer\IncrementalBaselineRepository;
use ArloTraining\Logger;
use ArloTraining\TemplateAccess;
use ArloTraining\ThemeManager;
use ArloTraining\Utilities;
#[\AllowDynamicProperties]

class Arlo_For_Wordpress_Admin {

	/**
	 * Largest Custom CSS request body the migration commands read, in bytes.
	 *
	 * Twice the scanner's 2 MiB bound, because slashes added to request data can at most double the text.
	 */
	const CUSTOM_CSS_MAX_REQUEST_BYTES = 4194304;

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix            = null;
	protected $plugin_venues_screen_hook_suffix     = null;
	protected $plugin_presenters_screen_hook_suffix = null;
	protected $plugin_templates_screen_hook_suffix  = null;
	protected $plugin_events_screen_hook_suffix     = null;
	protected $plugin_oa_screen_hook_suffix         = null;
	protected $plugin_loglist_screen_hook_suffix    = null;
	protected $plugin_sessions_screen_hook_suffix   = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		/*
		 * Call $plugin_slug from public plugin class.
		 */
		$plugin            = Arlo_For_Wordpress::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();

		add_action( 'admin_init', array( $this, 'arlo_register_settings' ) );

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pointers' ) );

		// Add the admin page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		add_filter( 'user_can_richedit', array( $this, 'disable_visual_editor' ), 50 );

		add_filter( 'pre_update_option_arlo_settings', array( $this, 'settings_pre_saved' ), 10, 2 );

		add_action( 'update_option_arlo_settings', array( $this, 'settings_saved' ) );
		add_action( 'admin_post_arlo_custom_css_migrate', [ $this, 'migrate_custom_css' ] );
		add_action( 'admin_post_arlo_custom_css_revert', [ $this, 'revert_custom_css' ] );
		add_action( 'admin_post_arlo_custom_css_discard', [ $this, 'discard_custom_css' ] );

		add_action( 'admin_init', array( $this, 'check_plugin_version' ) );
	}

	/**
	 * Check the version of the plugin
	 *
	 * @since     2.1.6
	 *
	 * @return    null
	 */

	public function check_plugin_version( $plugin ) {
		global $wp_rewrite;
		$plugin = Arlo_For_Wordpress::get_instance();
		$plugin->check_plugin_version();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register and enqueue admin-specific style sheets.
	 *
	 * The full admin stylesheet and the icon font load only on Arlo screens. The small
	 * notice stylesheet also loads on any screen where an Arlo notice will render.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) || ! isset( $this->plugin_venues_screen_hook_suffix ) || ! isset( $this->plugin_presenters_screen_hook_suffix ) || ! isset( $this->plugin_templates_screen_hook_suffix ) || ! isset( $this->plugin_events_screen_hook_suffix ) || ! isset( $this->plugin_sessions_screen_hook_suffix ) || ! isset( $this->plugin_loglist_screen_hook_suffix ) || ! isset( $this->plugin_oa_screen_hook_suffix ) ) {
			return;
		}

		$screen         = get_current_screen();
		$is_arlo_screen = $this->is_arlo_screen( $screen );

		if ( $is_arlo_screen || $this->has_pending_global_notices() ) {
			wp_enqueue_style( $this->plugin_slug . '-admin-notices', plugins_url( 'assets/css/admin-notices.css', __FILE__ ), array(), VersionHandler::VERSION );
		}

		if ( ! $is_arlo_screen ) {
			return;
		}

		wp_enqueue_style( $this->plugin_slug . '-icons8', plugins_url( 'assets/css/arlo-icons.css', __FILE__ ), array(), VersionHandler::VERSION );
		wp_enqueue_style( $this->plugin_slug . '-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array( 'wp-pointer' ), VersionHandler::VERSION );

		if ( $screen->id == $this->plugin_screen_hook_suffix ) {
			wp_enqueue_style( 'wp-codemirror' );
		}
	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * The notice script (dismissal handlers) loads on Arlo screens and on any screen
	 * where an Arlo notice will render. The settings-page scripts load only there.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen         = get_current_screen();
		$is_arlo_screen = $this->is_arlo_screen( $screen );

		if ( ! $is_arlo_screen && ! $this->has_pending_global_notices() ) {
			return;
		}

		wp_enqueue_script( $this->plugin_slug . '-admin-global-script', plugins_url( 'assets/js/admin_public.js', __FILE__ ), array( 'jquery', 'common' ), VersionHandler::VERSION, array( 'in_footer' => true ) );
		wp_add_inline_script(
			$this->plugin_slug . '-admin-global-script',
			'var arloAdmin = ' . wp_json_encode(
				array(
					'nonce'         => wp_create_nonce( $this->plugin_slug . '-arlo-for-wordpress-script' ),
					// Used by JS to build correct preview URLs on sites where the WP core
					// files live in a subdirectory (siteurl != home). wp_parse_url() returns
					// null when there is no path component (root install), and false when
					// the URL is malformed. Use ?: (not ??) so both null and false normalise
					// to '/' for JS: origin + home_url_path + '?page_id=...'
					'home_url_path' => rtrim( wp_parse_url( get_home_url(), PHP_URL_PATH ) ?: '', '/' ) . '/',
				),
				JSON_HEX_TAG
			) . ';',
			'before'
		);

		if ( ! $is_arlo_screen || $this->plugin_screen_hook_suffix !== $screen->id ) {
			return;
		}

		// The CSS editor only exists while there is legacy CSS (or an orphaned migration) to review, and only for users who may migrate it.
		$needs_css_editor = false;
		if ( CustomCss::current_user_can_edit() ) {
			$css_settings     = get_option( 'arlo_settings', [] );
			$needs_css_editor = CustomCssMigration::needs_editor( is_array( $css_settings ) ? $css_settings : [] );
		}
		if ( $needs_css_editor ) {
			wp_enqueue_script( 'wp-codemirror' );
			$settings = wp_enqueue_code_editor( [ 'type' => 'text/css' ] );
			wp_add_inline_script(
				'wp-codemirror',
				"(function () {
var settings = " . wp_json_encode( $settings, JSON_HEX_TAG ) . ";
document.addEventListener('DOMContentLoaded', function () {
	var textarea = document.getElementById('arlo_customcss');
	if (textarea && wp.codeEditor && settings) {
		if (settings.codemirror) {
			settings.codemirror.autoRefresh = true;
		}
		wp.codeEditor.initialize(textarea, settings);
	}
});
})();"
			);
		}
		wp_enqueue_script( $this->plugin_slug . '-plugin-script-cookie', plugins_url( '../public/assets/js/libs/js.cookie.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, false );
		// Depends on the global script so the arloAdmin data it reads is printed first.
		wp_enqueue_script( $this->plugin_slug . '-arlo-for-wordpress-script', plugins_url( 'assets/js/arlo_for_wordpress.js', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'wp-pointer', $this->plugin_slug . '-plugin-script-cookie', $this->plugin_slug . '-admin-global-script' ), VersionHandler::VERSION, false );
		wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, false );
		wp_enqueue_script( $this->plugin_slug . '-custom-css-migration', plugins_url( 'assets/js/custom-css-migration.js', __FILE__ ), [ 'wp-i18n' ], VersionHandler::VERSION, true );
		wp_localize_script(
			$this->plugin_slug . '-custom-css-migration',
			'arloCssMigration',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'arlo_custom_css_migration' ),
				'failed'       => __( 'The check could not be completed. Please try again. Your CSS and settings have not changed.', 'arlo-training-and-event-management-system' ),
				'checking'     => __( 'Checking your CSS...', 'arlo-training-and-event-management-system' ),
				'clear'        => __( 'No issues were found.', 'arlo-training-and-event-management-system' ),
				'clearNotes'   => __( 'No problems were found. The notes above are for information only.', 'arlo-training-and-event-management-system' ),
				'warning'      => __( 'These findings do not block migration. If they are acceptable, you can continue. Check your live pages after migrating.', 'arlo-training-and-event-management-system' ),
				'unknown'      => __( 'The check could not verify all of this CSS. These findings do not block migration. Review the listed items and test your live pages after migrating.', 'arlo-training-and-event-management-system' ),
				'blocked'      => __( 'This CSS cannot be migrated until the items marked "Must fix" are resolved. Edit it in the editor above and check again.', 'arlo-training-and-event-management-system' ),
				'empty'        => __( 'There is no CSS in the editor to migrate. Put your CSS back in the editor to migrate it, or use Discard this CSS below if you no longer need it.', 'arlo-training-and-event-management-system' ),
				'mustFix'      => __( 'Must fix', 'arlo-training-and-event-management-system' ),
				'warnings'     => __( 'Findings', 'arlo-training-and-event-management-system' ),
				'checkFailed'  => __( 'The check failed:', 'arlo-training-and-event-management-system' ),
				'edited'       => __( 'Your CSS changed while it was being checked, so nothing was migrated. Check it again, or migrate when you are ready.', 'arlo-training-and-event-management-system' ),
				'newTab'       => __( '(opens in a new tab)', 'arlo-training-and-event-management-system' ),
				'unsaved'      => __( 'Your changes in this editor have not been migrated and will be lost if you leave this page.', 'arlo-training-and-event-management-system' ),
				/* translators: 1: CSS line number, 2: finding description. */
				'line'         => __( 'Line %1$d: %2$s', 'arlo-training-and-event-management-system' ),
				/* translators: 1: CSS line number, 2: position on that line, 3: finding description. */
				'linePosition' => __( 'Line %1$d, pos %2$d: %3$s', 'arlo-training-and-event-management-system' ),
			]
		);
	}

	/**
	 * Add WordPress Feature Pointers
	 *
	 * The first-run pointer points users at the Arlo admin menu entry, so it is only offered
	 * to users who can see that menu, and only on the screens they land on after activation
	 * (Dashboard, Plugins). It is not shown on Arlo's own screens, where they have already arrived.
	 *
	 * @since     1.0.0
	 *
	 * @return    null
	 */
	public function enqueue_pointers() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}

		/*
		 * $pointer = new Arlo_Feature_Pointer($pointerID, $pointerTarget, $pointerTitle, $pointerContent, $pointerEdge, $pointerAlign)
		 *
		 * Parameters for Arlo_Feature_Pointer:
		 * $pointerID: unique identifier for the pointer. Required
		 * $pointerTarget: The ID of the element that the pointer will point too. Required
		 * $pointerTitle: The title text of the pointer. Required
		 * $pointerContent: The main content of the pointer. Required
		 * $pointerEdge: To which edge of the pointer the target element will sit. Optional, defaults to 'left'
		 * $pointerAlign: How the pointer is aligned to the target element. Optional, defaults to 'center'
		 */

		$pointer = new Arlo_Feature_Pointer( 'arlo-1st-pointer', '#toplevel_page_arlo-for-wordpress', esc_html__( 'Arlo for WordPress', 'arlo-training-and-event-management-system' ), esc_html__( "Arlo is almost ready. Just enter your details and you're good to go.", 'arlo-training-and-event-management-system' ), 'left', 'center' );
	}

	/**
	 * Whether the given screen is one of the plugin's own admin screens.
	 *
	 * @param \WP_Screen|null $screen Current screen.
	 * @return bool
	 */
	protected function is_arlo_screen( $screen ) {
		if ( ! is_object( $screen ) || empty( $screen->id ) ) {
			return false;
		}

		$arlo_screens = array_filter(
			array(
				$this->plugin_screen_hook_suffix,
				$this->plugin_venues_screen_hook_suffix,
				$this->plugin_oa_screen_hook_suffix,
				$this->plugin_loglist_screen_hook_suffix,
				$this->plugin_presenters_screen_hook_suffix,
				$this->plugin_templates_screen_hook_suffix,
				$this->plugin_events_screen_hook_suffix,
				$this->plugin_sessions_screen_hook_suffix,
			)
		);

		return in_array( $screen->id, $arlo_screens, true );
	}

	/**
	 * Whether an Arlo notice registered for every admin screen will render on this request.
	 *
	 * @return bool
	 */
	protected function has_pending_global_notices() {
		$notice_handler = Arlo_For_Wordpress::get_instance()->get_notice_handler();

		return is_object( $notice_handler ) && method_exists( $notice_handler, 'has_global_notices' ) && $notice_handler->has_global_notices();
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		$svg_path    = plugin_dir_path( __FILE__ ) . 'assets/img/arlo-menu-icon.svg';
		$svg_content = @file_get_contents( $svg_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- local file read for SVG data URI; @ suppresses the warning emitted when the file is missing so WP_DEBUG logs stay clean
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes a local SVG asset into a data URI for the WordPress admin menu icon; not code obfuscation.
		$menu_icon                       = is_string( $svg_content ) ? 'data:image/svg+xml;base64,' . base64_encode( $svg_content ) : 'none';
		$this->plugin_screen_hook_suffix = add_menu_page( esc_html__( 'Arlo Settings', 'arlo-training-and-event-management-system' ), esc_html__( 'Arlo', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug, array( $this, 'display_plugin_admin_page' ), $menu_icon, 81 );
		add_submenu_page( $this->plugin_slug, esc_html__( 'Arlo Settings', 'arlo-training-and-event-management-system' ), esc_html__( 'Settings', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug, array( $this, 'display_plugin_admin_page' ) );
		$this->plugin_events_screen_hook_suffix     = add_submenu_page( $this->plugin_slug, esc_html__( 'Events', 'arlo-training-and-event-management-system' ), esc_html__( 'Events', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug . '-events', array( $this, 'display_events_admin_page' ) );
		$this->plugin_oa_screen_hook_suffix         = add_submenu_page( $this->plugin_slug, esc_html__( 'Online Activities', 'arlo-training-and-event-management-system' ), esc_html__( 'Online Activities', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug . '-onlineactivities', array( $this, 'display_oa_admin_page' ) );
		$this->plugin_templates_screen_hook_suffix  = add_submenu_page( $this->plugin_slug, esc_html__( 'Templates', 'arlo-training-and-event-management-system' ), esc_html__( 'Templates', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug . '-templates', array( $this, 'display_templates_admin_page' ) );
		$this->plugin_sessions_screen_hook_suffix   = add_submenu_page( $this->plugin_slug, esc_html__( 'Sessions', 'arlo-training-and-event-management-system' ), esc_html__( 'Sessions', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug . '-sessions', array( $this, 'display_sessions_admin_page' ) );
		$this->plugin_presenters_screen_hook_suffix = add_submenu_page( $this->plugin_slug, esc_html__( 'Presenters', 'arlo-training-and-event-management-system' ), esc_html__( 'Presenters', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug . '-presenters', array( $this, 'display_presenters_admin_page' ) );
		$this->plugin_venues_screen_hook_suffix     = add_submenu_page( $this->plugin_slug, esc_html__( 'Venues', 'arlo-training-and-event-management-system' ), esc_html__( 'Venues', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug . '-venues', array( $this, 'display_venues_admin_page' ) );
		$this->plugin_loglist_screen_hook_suffix    = add_submenu_page( $this->plugin_slug, esc_html__( 'Logs', 'arlo-training-and-event-management-system' ), esc_html__( 'Logs', 'arlo-training-and-event-management-system' ), 'manage_options', $this->plugin_slug . '-logs', array( $this, 'display_loglist_admin_page' ) );
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		include_once 'views/admin.php';
	}

	/**
	 * Render the lists page for this plugin.
	 *
	 * @since    2.2.0
	 */
	public function display_venues_admin_page() {
		require_once 'includes/class-arlo-for-wordpress-venues.php';

		$list = new Arlo_For_Wordpress_Venues();

		include_once 'views/list.php';
	}

	public function display_presenters_admin_page() {
		require_once 'includes/class-arlo-for-wordpress-presenters.php';

		$list = new Arlo_For_Wordpress_Presenters();

		include_once 'views/list.php';
	}

	public function display_templates_admin_page() {
		require_once 'includes/class-arlo-for-wordpress-templates.php';

		$list = new Arlo_For_Wordpress_Templates();

		include_once 'views/list.php';
	}

	public function display_events_admin_page() {
		require_once 'includes/class-arlo-for-wordpress-events.php';

		$list = new Arlo_For_Wordpress_Events();

		include_once 'views/list.php';
	}

	public function display_sessions_admin_page() {
		require_once 'includes/class-arlo-for-wordpress-sessions.php';

		$list = new Arlo_For_Wordpress_Sessions();

		include_once 'views/list.php';
	}

	public function display_oa_admin_page() {
		require_once 'includes/class-arlo-for-wordpress-onlineactivities.php';

		$list = new Arlo_For_Wordpress_OnlineActivities();

		include_once 'views/list.php';
	}

	public function display_loglist_admin_page() {
		require_once 'includes/class-arlo-for-wordpress-loglist.php';

		$list = new Arlo_For_Wordpress_LogList();

		include_once 'views/list.php';
	}



	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug ) ) . '">' . esc_html__( 'Settings', 'arlo-training-and-event-management-system' ) . '</a>',
			),
			$links
		);
	}

	/**
	 * Registers the plugin's settings.
	 *
	 * @since    1.0.0
	 */
	public function arlo_register_settings() {
		require_once 'includes/class-arlo-for-wordpress-settings.php';
		$settings = new Arlo_For_Wordpress_Settings();
	}

	/**
	 * Prints out all settings sections added to a particular settings page
	 *
	 * Part of the Settings API. Use this in a settings page callback function
	 * to output all the sections and fields that were added to that $page with
	 * add_settings_section() and add_settings_field()
	 *
	 * @global $wp_settings_sections Storage array of all settings sections added to admin pages
	 * @global $wp_settings_fields Storage array of settings fields and info about their pages/sections
	 * @since 2.7.0
	 *
	 * @param string $page The slug name of the page whos settings sections you want to output
	 */
	function do_settings_sections( $page ) {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections[ $page ] ) ) {
			return;
		}

		foreach ( (array) $wp_settings_sections[ $page ] as $section ) {
			echo '<div class="' . esc_attr( $section['id'] ) . ' arlo-section cf">';
			if ( $section['title'] ) {
				echo "<h3>" . esc_html( $section['title'] ) . "</h3>\n";
			}

			if ( $section['callback'] ) {
				call_user_func( $section['callback'], $section );
			}

			if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $page ] ) || ! isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
				continue;
			}
			//echo '<table class="form-table">';
			$this->do_settings_fields( $page, $section['id'] );
			//echo '</table>';
			echo '</div>';
		}
	}

	/**
	 * Print out the settings fields for a particular settings section
	 *
	 * Part of the Settings API. Use this in a settings page to output
	 * a specific section. Should normally be called by do_settings_sections()
	 * rather than directly.
	 *
	 * @global $wp_settings_fields Storage array of settings fields and their pages/sections
	 *
	 * @since 2.7.0
	 *
	 * @param string $page Slug title of the admin page who's settings fields you want to show.
	 * @param section $section Slug title of the settings section who's fields you want to show.
	 */
	function do_settings_fields( $page, $section ) {
		global $wp_settings_fields;

		if ( ! isset( $wp_settings_fields[ $page ][ $section ] ) ) {
			return;
		}

		foreach ( (array) $wp_settings_fields[ $page ][ $section ] as $field ) {
			$field['args']['label_for'] = ! empty( $field['args']['label_for'] ) ? $field['args']['label_for'] : "";
			echo '<div class="' . esc_attr( ARLO_PLUGIN_PREFIX . '-field-wrap cf ' . ARLO_PLUGIN_PREFIX . '-' . strtolower( $field['args']['label_for'] ) ) . '" id="' . esc_attr( ARLO_PLUGIN_PREFIX . '-' . strtolower( $field['args']['label_for'] ) ) . '">';

			if ( $field['callback'][1] == 'arlo_template_callback' ) {

				echo '
					<table class="' . esc_attr( ARLO_PLUGIN_PREFIX . '-template-table' ) . '">
						<tr>
							<td>
								<h2 class="nav-tab-wrapper vertical-nav-tab-wrapper">';
				foreach ( Arlo_For_Wordpress::get_templates() as $id => $template ) {
					$name = $template['name'];
					echo '<a href="' . esc_url( '#pages/' . $id ) . '" class="' . esc_attr( "nav-tab vertical-nav-tab {$this->plugin_slug}-pages-{$id}" ) . '" id="' . esc_attr( "{$this->plugin_slug}-pages-{$id}" ) . '">' . esc_html( $name ) . '</a>';
				}
								echo '</h2>
							</td>

							<td>
								<div class="' . esc_attr( ARLO_PLUGIN_PREFIX . '-field ' . ARLO_PLUGIN_PREFIX . '-template-field' ) . '">';
									call_user_func( $field['callback'], $field['args'] );

									$type = isset( $field["args"]["type"] ) ? $field["args"]["type"] : $field["id"];

									$path = ARLO_PLUGIN_DIR . 'admin/includes/codes/' . $type . '.php';
				if ( file_exists( $path ) ) {
					echo '<div class="' . esc_attr( ARLO_PLUGIN_PREFIX . '-shortcodes' ) . '">
											<h3>' . esc_html__( 'Recommended shortcodes', 'arlo-training-and-event-management-system' ) . '</h3>
											<a href="https://developer.arlo.co/doc/wordpress/shortcodes/" target="_blank">' . esc_html__( 'More about shortcodes', 'arlo-training-and-event-management-system' ) . '</a>';

					include $path;
					echo '</div>';
				}

								echo '</div>
							</td>
						</tr>
					</table>
				';
			} else {
				echo '
					<div class="' . esc_attr( ARLO_PLUGIN_PREFIX . '-label' ) . '">' . $field['title'] . '</div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin field title HTML output. $field['title'] is trusted field-title markup supplied by add_settings_field().
					. '<div class="' . esc_attr( ARLO_PLUGIN_PREFIX . '-field' ) . '">';
					call_user_func( $field['callback'], $field['args'] );
				echo '</div>
				';
			}
			echo '</div>';
		}
	}

	/**
	 * Test if the current page is the Alro settings page and disables the visual editor if it is
	 *
	 * @since 1.0.0
	 *
	 * @param $default
	 */

	public function disable_visual_editor( $default ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Context implies safe execution. $_GET['page'] is menu slug, not a form submission. /wp-admin/admin.php?page=arlo-for-wordpress
		if ( isset( $_GET['page'] ) && sanitize_key( $_GET['page'] ) == 'arlo-for-wordpress' ) {
			return false;
		}
		return $default;
	}

	public function settings_pre_saved( $new, $old ) {
		if ( SettingsPersistence::is_system_write() ) {
			return $new;
		}
		if ( ! is_array( $new ) ) {
			// Treat malformed full-option writes as no-ops so stored settings are not wiped.
			return is_array( $old ) ? $old : [];
		}
		$old = is_array( $old ) ? $old : [];

		// A restricted user's form must neither change nor drop the stored templates. This runs before a new custom shortcode's default template is added below.
		$new = TemplateAccess::maybe_revert_template_changes( $new, $old );

		if ( ! empty( $new['platform_name'] ) && ( empty( $old['platform_name'] ) || $old['platform_name'] !== $new['platform_name'] ) ) {
			$new['user_import_enabled'] = '1';
		}

		if ( empty( $new['import_fragment_size'] ) || ! is_numeric( $new['import_fragment_size'] ) ) {
			$new['import_fragment_size'] = ImportRequest::FRAGMENT_DEFAULT_BYTE_SIZE;
		} elseif ( $new['import_fragment_size'] > ImportRequest::FRAGMENT_MAX_BYTE_SIZE ) {
			$new['import_fragment_size'] = ImportRequest::FRAGMENT_MAX_BYTE_SIZE;
		}

		if ( empty( $new['sleep_between_import_tasks'] ) || ! is_numeric( $new['sleep_between_import_tasks'] ) ) {
			$new['sleep_between_import_tasks'] = 0;
		} elseif ( $new['sleep_between_import_tasks'] < 0 ) {
			$new['sleep_between_import_tasks'] = 0;
		} elseif ( $new['sleep_between_import_tasks'] > \ArloTraining\Scheduler::MAX_SLEEP_BETWEEN_TASKS_SECONDS ) {
			$new['sleep_between_import_tasks'] = \ArloTraining\Scheduler::MAX_SLEEP_BETWEEN_TASKS_SECONDS;
		}

		$new = \Arlo_For_Wordpress::normalize_importer_mode_setting( $new, $old );

		$old_has_retention_max_rows = array_key_exists( 'log_retention_max_rows', $old );
		$new_has_retention_max_rows = array_key_exists( 'log_retention_max_rows', $new );
		$old_retention_max_rows     = Logger::normalize_retention_max_rows( $old['log_retention_max_rows'] ?? null );
		$new_retention_is_supported = $new_has_retention_max_rows && Logger::is_supported_retention_max_rows( $new['log_retention_max_rows'] );
		if ( $new_retention_is_supported ) {
			$new['log_retention_max_rows'] = Logger::normalize_retention_max_rows( $new['log_retention_max_rows'] );
		} elseif ( $old_has_retention_max_rows ) {
			$new['log_retention_max_rows'] = $old_retention_max_rows;
		} else {
			$new['log_retention_max_rows'] = Logger::LOG_RETENTION_DEFAULT_MAX_ROWS;
		}
		if ( $new_retention_is_supported && $new['log_retention_max_rows'] !== $old_retention_max_rows ) {
			$this->add_log_retention_settings_message( Logger::set_retention_max_rows( $new['log_retention_max_rows'] ) );
		}

		$old_has_diagnostic_level = array_key_exists( Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY, $old );
		$new_has_diagnostic_level = array_key_exists( Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY, $new );
		if ( $new_has_diagnostic_level && Logger::is_supported_diagnostic_level( $new[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] ) ) {
			$new[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] = Logger::normalize_diagnostic_level( $new[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] );
		} elseif ( $old_has_diagnostic_level ) {
			$new[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] = Logger::normalize_diagnostic_level( $old[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] );
		} else {
			$new[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] = Logger::DIAGNOSTIC_VERBOSITY_OFF;
		}

		// Normalise a submitted choice and keep a stored one through writes that omit it; undecided stays absent.
		if ( array_key_exists( Arlo_For_Wordpress::POWERED_BY_SETTING, $new ) ) {
			$new[ Arlo_For_Wordpress::POWERED_BY_SETTING ] = '1' === $new[ Arlo_For_Wordpress::POWERED_BY_SETTING ] ? '1' : '0';
		} elseif ( array_key_exists( Arlo_For_Wordpress::POWERED_BY_SETTING, $old ) ) {
			$new[ Arlo_For_Wordpress::POWERED_BY_SETTING ] = '1' === $old[ Arlo_For_Wordpress::POWERED_BY_SETTING ] ? '1' : '0';
		}

		// Normalise a submitted choice and keep a stored one through writes that omit it; the setting stays absent (off) until used.
		if ( array_key_exists( Arlo_For_Wordpress::LEGACY_REGION_ID_HANDLING_SETTING, $new ) ) {
			$new[ Arlo_For_Wordpress::LEGACY_REGION_ID_HANDLING_SETTING ] = '1' === $new[ Arlo_For_Wordpress::LEGACY_REGION_ID_HANDLING_SETTING ] ? '1' : '0';
		} elseif ( array_key_exists( Arlo_For_Wordpress::LEGACY_REGION_ID_HANDLING_SETTING, $old ) ) {
			$new[ Arlo_For_Wordpress::LEGACY_REGION_ID_HANDLING_SETTING ] = '1' === $old[ Arlo_For_Wordpress::LEGACY_REGION_ID_HANDLING_SETTING ] ? '1' : '0';
		}

		$old_custom_shortcodes = isset( $old['custom_shortcodes'] ) && is_array( $old['custom_shortcodes'] ) ? $old['custom_shortcodes'] : [];
		if ( ! empty( $old_custom_shortcodes ) ) {
			$new["custom_shortcodes"] = $old_custom_shortcodes;
		} else {
			$new["custom_shortcodes"] = array();
		}

		// Preserve post_types page assignments when a partial or malformed settings
		// update omits them; a non-array value is treated the same as missing.
		if ( ! isset( $new['post_types'] ) || ! is_array( $new['post_types'] ) ) {
			$new['post_types'] = ( is_array( $old ) && isset( $old['post_types'] ) && is_array( $old['post_types'] ) ) ? $old['post_types'] : [];
		}

		$new_custom_shortcode        = isset( $new["new_custom_shortcode"] ) && is_scalar( $new["new_custom_shortcode"] ) ? (string) $new["new_custom_shortcode"] : '';
		$new_custom_shortcode_type   = isset( $new["new_custom_shortcode_type"] ) && is_scalar( $new["new_custom_shortcode_type"] ) ? (string) $new["new_custom_shortcode_type"] : '';
		$delete_shortcode            = isset( $new["delete_shortcode"] ) && is_scalar( $new["delete_shortcode"] ) ? (string) $new["delete_shortcode"] : '';
		$shortcode_name              = substr( sanitize_text_field( strtolower( str_replace( array( "&", "/", "<", ">", "[", "]", "=" ), '', str_replace( ' ', '_', $new_custom_shortcode ) ) ) ), 0, 15 ); // WP limits post name lengths
		$shortcode_type_is_supported = array_key_exists( $new_custom_shortcode_type, \Arlo_For_Wordpress::$shortcode_types );

		// Custom shortcodes
		if ( ! empty( $shortcode_name ) && $shortcode_type_is_supported && ! array_key_exists( $shortcode_name, $old_custom_shortcodes ) && ! shortcode_exists( "arlo_" . $shortcode_name . "" ) && preg_match( '/^[\w]+$/', $new_custom_shortcode ) === 1 ) {
			\ArloTraining\UserFlash::set( \ArloTraining\UserFlash::NEW_CUSTOM_SHORTCODE, $shortcode_name );

			$shortcode_type = $new_custom_shortcode_type;

			if ( empty( $new['custom_shortcodes'] ) ) {
				$new['custom_shortcodes'] = array();
			}

			$new['custom_shortcodes'][ $shortcode_name ] = $shortcode_type;

			$default_template = $this->get_template_source()[ 'arlo-' . $new_custom_shortcode_type ] ?? '';

			if ( ! empty( $default_template ) ) {
				$new['templates'][ $shortcode_name ]['html'] = $default_template;
			}
		}

		if ( ! empty( $delete_shortcode ) ) {
			unset( $new['custom_shortcodes'][ $delete_shortcode ] );
		}

		unset( $new["new_custom_shortcode"] );
		unset( $new["new_custom_shortcode_type"] );
		unset( $new["delete_shortcode"] );

		return CustomCss::prepare_settings_for_save( $new, $old );
	}

	/**
	 * Read-only advisory endpoint. Editor contents never enter the settings save pipeline.
	 *
	 * @return void
	 */
	public function check_custom_css_migration(): void {
		if ( ! $this->custom_css_migration_authorized( false ) ) {
			wp_send_json_error( [ 'code' => 'forbidden' ], 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The shared authorization guard above verifies this POST's scalar nonce and manage_options capability.
		if ( ! isset( $_POST['css'] ) || ! is_string( $_POST['css'] ) ) {
			wp_send_json_error( [ 'code' => 'invalid_css' ], 400 );
		}
		// Shape and size precede unslashing; slashes can at most double the analysis byte bound.
		$submitted_css = $_POST['css']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shape checked above; size is bounded before unslashing, and advisory analysis must receive unmodified CSS, never render it.
		$css           = strlen( $submitted_css ) > self::CUSTOM_CSS_MAX_REQUEST_BYTES ? $submitted_css : wp_unslash( $submitted_css );
		wp_send_json_success( CustomCssMigration::preflight( $css ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Authenticates the Custom CSS entry points without accepting array-shaped nonce values.
	 *
	 * The check only needs manage_options, so it can explain a missing edit_css permission; migrating and reverting write
	 * into WordPress's Additional CSS and also need edit_css.
	 *
	 * @param bool $require_edit_css Whether the user must be able to edit Additional CSS.
	 * @return bool
	 */
	private function custom_css_migration_authorized( bool $require_edit_css ): bool {
		return current_user_can( 'manage_options' )
			&& ( ! $require_edit_css || ( get_current_user_id() > 0 && CustomCss::current_user_can_edit() ) )
			&& isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] )
			&& (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'arlo_custom_css_migration' );
	}

	/**
	 * Explicit confirmed migration of the CSS in the editor into WordPress's Additional CSS.
	 *
	 * @return void
	 */
	public function migrate_custom_css(): void {
		if ( ! $this->custom_css_migration_authorized( true ) ) {
			wp_die( esc_html__( 'You are not allowed to migrate Custom CSS.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 403 ] );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The shared authorization guard above verifies this POST's scalar nonce, manage_options and edit_css.
		if ( ! isset( $_POST['css'], $_POST['confirmed'] ) || ! is_string( $_POST['css'] ) || ! is_string( $_POST['confirmed'] ) ) {
			wp_die( esc_html__( 'Confirm the Custom CSS migration.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		$submitted_css = $_POST['css']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shape checked above; size is bounded before unslashing, and the CSS is validated by the migration, never rendered here.
		$confirmed     = wp_unslash( $_POST['confirmed'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the literal confirmation value "1" is accepted below.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '1' !== $confirmed ) {
			wp_die( esc_html__( 'Confirm the Custom CSS migration.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		$settings = get_option( 'arlo_settings', [] );
		if ( ! is_array( $settings ) ) {
			wp_die( esc_html__( 'The saved Custom CSS settings could not be read.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		if ( strlen( $submitted_css ) > self::CUSTOM_CSS_MAX_REQUEST_BYTES ) {
			$this->finish_custom_css_command( new \WP_Error( 'too_large' ) );
		}
		$this->finish_custom_css_command( CustomCssMigration::migrate( wp_unslash( $submitted_css ), $settings, get_current_user_id() ), 'migrated' );
	}

	/**
	 * Explicit confirmed return of migrated CSS from Additional CSS to the plugin.
	 *
	 * @return void
	 */
	public function revert_custom_css(): void {
		if ( ! $this->custom_css_migration_authorized( true ) ) {
			wp_die( esc_html__( 'You are not allowed to revert Custom CSS.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 403 ] );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The shared authorization guard above verifies this POST's scalar nonce, manage_options and edit_css.
		if ( ! isset( $_POST['confirmed'] ) || ! is_string( $_POST['confirmed'] ) ) {
			wp_die( esc_html__( 'Confirm the Custom CSS revert.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		$confirmed = wp_unslash( $_POST['confirmed'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the literal confirmation value "1" is accepted below.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '1' !== $confirmed ) {
			wp_die( esc_html__( 'Confirm the Custom CSS revert.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		$settings = get_option( 'arlo_settings', [] );
		if ( ! is_array( $settings ) ) {
			wp_die( esc_html__( 'The saved Custom CSS settings could not be read.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		$this->finish_custom_css_command( CustomCssMigration::revert( $settings, get_current_user_id() ), 'reverted' );
	}

	/**
	 * Explicit confirmed removal of the CSS the plugin still holds, for a site that does not want to migrate it.
	 *
	 * It writes nothing to Additional CSS, so it needs manage_options but not edit_css.
	 *
	 * @return void
	 */
	public function discard_custom_css(): void {
		if ( ! $this->custom_css_migration_authorized( false ) ) {
			wp_die( esc_html__( 'You are not allowed to discard Custom CSS.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 403 ] );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The shared authorization guard above verifies this POST's scalar nonce and manage_options capability.
		if ( ! isset( $_POST['confirmed'] ) || ! is_string( $_POST['confirmed'] ) ) {
			wp_die( esc_html__( 'Confirm discarding the Custom CSS.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		$confirmed = wp_unslash( $_POST['confirmed'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the literal confirmation value "1" is accepted below.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '1' !== $confirmed ) {
			wp_die( esc_html__( 'Confirm discarding the Custom CSS.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		$settings = get_option( 'arlo_settings', [] );
		if ( ! is_array( $settings ) ) {
			wp_die( esc_html__( 'The saved Custom CSS settings could not be read.', 'arlo-training-and-event-management-system' ), '', [ 'response' => 400 ] );
		}
		$this->finish_custom_css_command( CustomCssMigration::discard( $settings, get_current_user_id() ), 'discarded' );
	}

	/**
	 * Records the outcome of a migrate or revert command for the settings screen and returns there.
	 *
	 * @param array|\WP_Error $result  Result from CustomCssMigration.
	 * @param string          $success Notice code to show when the command succeeded.
	 * @return void
	 */
	private function finish_custom_css_command( $result, string $success = 'failed' ): void {
		if ( is_wp_error( $result ) ) {
			$notice = $result->get_error_code();
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Custom CSS command failed [' . $notice . '].' );
		} else {
			$notice = $success;
			\ArloTraining\CacheControl::Clear();
		}
		\ArloTraining\UserFlash::set( \ArloTraining\UserFlash::CUSTOM_CSS_MIGRATION, $notice );
		wp_safe_redirect( admin_url( 'admin.php?page=arlo-for-wordpress' ) . '#customcss' );
		exit;
	}

	/**
	 * Return the default template HTML keyed by shortcode type.
	 *
	 * @return array Template source map from Arlo shortcode key to default HTML.
	 */
	protected function get_template_source(): array {
		return \Arlo_For_Wordpress::arlo_template_source();
	}

	/**
	 * Add an admin notice describing explicit log-retention maintenance results.
	 *
	 * @param array $status Structured log-maintenance status.
	 * @return void
	 */
	private function add_log_retention_settings_message( array $status ): void {
		if ( $status['status'] === 'truncated' ) {
			add_settings_error(
				'arlo_settings',
				'arlo_log_retention_truncated',
				esc_html__( 'The diagnostic log was too large and has been cleared.', 'arlo-training-and-event-management-system' ),
				'warning'
			);
			return;
		}

		if ( $status['status'] === 'cleaned' && $status['rows_deleted'] > 0 ) {
			add_settings_error(
				'arlo_settings',
				'arlo_log_retention_cleaned',
				esc_html__( 'Old log rows were deleted.', 'arlo-training-and-event-management-system' ),
				'updated'
			);
			return;
		}

		if ( $status['status'] !== 'cleaned' ) {
			add_settings_error(
				'arlo_settings',
				'arlo_log_retention_partial',
				esc_html__( 'The log retention setting was saved, but log maintenance could not finish. Maintenance will continue during synchronization.', 'arlo-training-and-event-management-system' ),
				'warning'
			);
		}
	}

	private function normalize_filter_options( $settings_name, $setting_array ) {
		//normalize filters options
		$filters = array();

		if ( isset( $setting_array[ $settings_name ] ) && is_array( $setting_array[ $settings_name ] ) && count( $setting_array[ $settings_name ] ) ) {
			foreach ( $setting_array[ $settings_name ] as $filter_group_name => $filter_group ) {
				foreach ( $filter_group as $filter_name => $filter_settings ) {
					foreach ( $filter_settings as $filter_setting_id => $filter_setting ) {
						$old_value         = ( isset( $filter_setting['filteroldvalue'] ) ? esc_html( $filter_setting['filteroldvalue'] ) : '' );
						$old_value_decoded = htmlspecialchars_decode( $old_value, ENT_QUOTES );
						$new_value         = ( isset( $filter_setting['filternewvalue'] ) ? esc_html( $filter_setting['filternewvalue'] ) : '' );
						if ( strlen( $new_value ) > 64 ) {
							$new_value = substr( $new_value, 0, 64 );
						}

						if ( isset( $filter_setting["filteraction"] ) && $filter_setting["filteraction"] == "rename" && isset( $old_value ) && ! empty( $new_value ) ) {
							$filters[ $filter_group_name ][ $filter_name ][ $old_value ] = $new_value;
						}

						if ( isset( $filter_setting["filteraction"] ) && $filter_setting["filteraction"] == "exclude" && isset( $old_value ) && $old_value != '' ) {
							if ( ! isset( $filters['hiddenfilters'][ $filter_group_name ][ $filter_name ] ) ) {
								$filters['hiddenfilters'][ $filter_group_name ][ $filter_name ] = array();
							}

							array_push( $filters['hiddenfilters'][ $filter_group_name ][ $filter_name ], $old_value_decoded );
						} elseif ( isset( $filter_setting["filteraction"] ) && $filter_setting["filteraction"] == "showonly" && isset( $old_value ) && $old_value != '' ) {
							if ( ! isset( $filters['showonlyfilters'][ $filter_group_name ][ $filter_name ] ) ) {
								$filters['showonlyfilters'][ $filter_group_name ][ $filter_name ] = array();
							}

							array_push( $filters['showonlyfilters'][ $filter_group_name ][ $filter_name ], $old_value_decoded );
						} elseif ( ! empty( $filters['hiddenfilters'][ $filter_group_name ][ $filter_name ] ) ) {
								$old_value_index = array_search( $old_value_decoded, $filters['hiddenfilters'][ $filter_group_name ][ $filter_name ], true );

							if ( false !== $old_value_index ) {
								unset( $filters['hiddenfilters'][ $filter_group_name ][ $filter_name ][ $old_value_index ] );
							}
						}
					}
				}
			}
		}

		update_option( $settings_name, $filters );
	}

	public function settings_saved( $old ) {
		if ( SettingsPersistence::is_system_write() ) {
			return;
		}
		$new              = get_option( 'arlo_settings', array() );
		$import_id        = get_option( 'arlo_import_id' );
		$old_regions      = get_option( 'arlo_regions', array() );
		$plugin           = Arlo_For_Wordpress::get_instance();
		$old_platform     = is_array( $old ) && isset( $old['platform_name'] ) && is_scalar( $old['platform_name'] ) ? Utilities::normalize_platform_name( $old['platform_name'] ) : '';
		$new_platform     = is_array( $new ) && isset( $new['platform_name'] ) && is_scalar( $new['platform_name'] ) ? Utilities::normalize_platform_name( $new['platform_name'] ) : '';
		$platform_changed = 0 !== strcasecmp( $old_platform, $new_platform );

		// The banner wording marker is only needed while the credit choice is undecided.
		if ( is_array( $new ) && array_key_exists( Arlo_For_Wordpress::POWERED_BY_SETTING, $new ) ) {
			delete_option( Arlo_For_Wordpress::POWERED_BY_UPGRADE_NOTICE_OPTION );
		}

		// Cached widget markup may contain the credit shortcode output, so it must not outlive a change of choice.
		$old_powered_by = is_array( $old ) && isset( $old[ Arlo_For_Wordpress::POWERED_BY_SETTING ] ) ? $old[ Arlo_For_Wordpress::POWERED_BY_SETTING ] : null;
		$new_powered_by = is_array( $new ) && isset( $new[ Arlo_For_Wordpress::POWERED_BY_SETTING ] ) ? $new[ Arlo_For_Wordpress::POWERED_BY_SETTING ] : null;
		if ( $old_powered_by !== $new_powered_by ) {
			CacheControl::invalidate_upcoming_widget_cache();
		}

		//save theme changes
		$this->save_theme_templates( isset( $new['templates'] ) ? $new['templates'] : [] );

		if ( $platform_changed && $plugin->get_schema_manager()->supports_delta_infrastructure() ) {
			// A manifest baseline belongs to one platform only; retire it before the new platform can be imported.
			try {
				( new IncrementalBaselineRepository() )->mark_baseline_invalid();
			} catch ( \Throwable $exception ) {
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Could not invalidate incremental baseline after platform change [exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']' );
			}
		}

		if ( $platform_changed && ! empty( $new_platform ) ) {
			\Arlo_For_Wordpress::reset_connection_health();

			$scheduler = $plugin->get_scheduler();
			$scheduler->set_task( "import", -1 );
		} elseif ( empty( $new_platform ) ) {
			$notice_id = Arlo_For_Wordpress::$dismissible_notices['welcome'];
			$user      = wp_get_current_user();
			update_user_meta( $user->ID, $notice_id, 1 );
		}

		//check if the tax exempt tag has changed
		if ( ! empty( $import_id ) && (
			( isset( $new['taxexempt_tag'] ) && isset( $old['taxexempt_tag'] ) && $new['taxexempt_tag'] != $old['taxexempt_tag'] ) ||
			! isset( $new['taxexempt_tag'] ) || ! isset( $old['taxexempt_tag'] )
			) ) {
			$plugin->get_importer()->set_tax_exempt_events( $import_id );
		}

		//normalize regions
		$regions = array();
		if ( is_array( $new['regionid'] ) && count( $new['regionid'] ) ) {
			foreach ( $new['regionid'] as $key => $regionid ) {
				if ( ! empty( $regionid ) && ! empty( $new['regionname'][ $key ] ) ) {
					$regions[ $regionid ] = $new['regionname'][ $key ];
				}
			}
		}
		update_option( 'arlo_regions', $regions );

		//normalize filter options and save them
		$this->normalize_filter_options( 'arlo_filter_settings', $new );
		$this->normalize_filter_options( 'arlo_page_filter_settings', $new );

		// need to check for posts-page change here
		// loop through each post type and check if the posts-page has changed
		foreach ( $new['post_types'] as $id => $post_type ) {
			if ( isset( $old['post_types'][ $id ]['posts_page'] ) && $old['post_types'][ $id ]['posts_page'] != $new['post_types'][ $id ]['posts_page'] ) {
				$posts = get_posts(
					array(
						'posts_per_page' => -1,
						'post_type'      => 'arlo_' . $id,
						'post_status'    => 'publish', // only there to ensure we don't create a loop if a user has tampered
					)
				);

				// update all posts of this type to have this parent id
				foreach ( $posts as $post ) {
					wp_update_post(
						array(
							'ID'          => $post->ID,
							'post_parent' => $new['post_types'][ $id ]['posts_page'],
						)
					);
				}
			}
		}
	}

	protected function save_theme_templates( $templates ) {
		$templates              = is_array( $templates ) ? $templates : [];
		$theme_id               = get_option( 'arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME );
		$stored_themes_settings = get_option( 'arlo_themes_settings', [] );
		if ( ! is_array( $stored_themes_settings ) ) {
			$stored_themes_settings = [];
		}
		if ( ! isset( $stored_themes_settings[ $theme_id ] ) || ! is_object( $stored_themes_settings[ $theme_id ] ) ) {
			$stored_themes_settings[ $theme_id ] = new \stdClass();
		}
		$stored_themes_settings[ $theme_id ]->templates = $templates;
		update_option( 'arlo_themes_settings', $stored_themes_settings, 1 );
	}
}
