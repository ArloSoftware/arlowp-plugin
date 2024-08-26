<?php
/**
 * Arlo For Wordpress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      https://arlo.co
 * @copyright 2018 Arlo
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * If you're interested in introducing public-facing
 * functionality, then refer to `class-arlo-for-wordpress.php`
 *
 * @TODO: Rename this class to a proper name for your plugin.
 *
 * @package Arlo_For_Wordpress_Admin
 * @author  Adam Fentosi <adam.fentosi@arlo.co>, Gabriel Oheix
 */

use Arlo\VersionHandler;
use Arlo\Importer\ImportRequest;
use Arlo\ThemeManager;
#[\AllowDynamicProperties]
class Arlo_For_Wordpress_Admin {

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
	protected $plugin_screen_hook_suffix = null;
	protected $plugin_venues_screen_hook_suffix = null;
	protected $plugin_presenters_screen_hook_suffix = null;
	protected $plugin_templates_screen_hook_suffix = null;
	protected $plugin_events_screen_hook_suffix = null;
	protected $plugin_oa_screen_hook_suffix = null;
	protected $plugin_loglist_screen_hook_suffix = null;
	protected $plugin_sessions_screen_hook_suffix = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		/*
		 * @TODO :
		 *
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		/* if( ! is_super_admin() ) {
			return;
		} */

		/*
		 * Call $plugin_slug from public plugin class.
		 *
		 * @TODO:
		 *
		 * - Rename "Arlo_For_Wordpress" to the name of your initial plugin class
		 *
		 */
		$plugin = Arlo_For_Wordpress::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();

		add_action( 'admin_init', array( $this, 'arlo_register_settings'));

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pointers' ) );

		// Add the admin page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		add_filter ( 'user_can_richedit' , array( $this, 'disable_visual_editor') , 50 );

		add_filter( 'pre_update_option_arlo_settings', array($this, 'settings_pre_saved'), 10, 2 );
		
		add_action( 'update_option_arlo_settings', array($this, 'settings_saved') );
		
		
		add_action( 'admin_init', array($this, 'check_plugin_version') );
	}
	
	/**
	 * Check the version of the plugin
	 *
	 * @since     2.1.6
	 *
	 * @return    null
	 */
	 
	public function check_plugin_version($plugin) {
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

		/*
		 * @TODO :
		 *
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		/* if( ! is_super_admin() ) {
			return;
		} */

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @TODO:
	 *
	 * - Rename "Arlo_For_Wordpress" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) || !isset($this->plugin_venues_screen_hook_suffix) || !isset($this->plugin_presenters_screen_hook_suffix) || !isset($this->plugin_templates_screen_hook_suffix) || !isset($this->plugin_events_screen_hook_suffix) || !isset($this->plugin_sessions_screen_hook_suffix) || !isset($this->plugin_loglist_screen_hook_suffix)  || !isset($this->plugin_oa_screen_hook_suffix)) {
			return;
		}
		
		wp_enqueue_style( $this->plugin_slug .'-admin-public-styles', plugins_url( 'assets/css/admin_public.css?20170424', __FILE__ ), array(), VersionHandler::VERSION );		
		wp_enqueue_style( $this->plugin_slug .'-icons8', plugins_url( 'assets/fonts/icons8/Arlo-WP.css', __FILE__ ), array(), VersionHandler::VERSION );
		
		$screen = get_current_screen();	
		
		if ( in_array($screen->id, [$this->plugin_screen_hook_suffix, $this->plugin_venues_screen_hook_suffix, $this->plugin_oa_screen_hook_suffix, $this->plugin_loglist_screen_hook_suffix, $this->plugin_presenters_screen_hook_suffix, $this->plugin_templates_screen_hook_suffix, $this->plugin_events_screen_hook_suffix, $this->plugin_sessions_screen_hook_suffix])) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css?20170424', __FILE__ ), array(), VersionHandler::VERSION );
			
			if ($screen->id == $this->plugin_screen_hook_suffix) {
				wp_enqueue_style( $this->plugin_slug .'-codemirror', plugins_url( 'assets/css/libs/codemirror.css', __FILE__ ), array(), VersionHandler::VERSION );
				wp_enqueue_style( $this->plugin_slug . '-fancybox', plugins_url( '../public/custom-assets/fancybox/jquery.fancybox.min.css', __FILE__), array(), '3.0.47' );
			}
		}
	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @TODO:
	 *
	 * - Rename "Arlo_For_Wordpress" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		wp_enqueue_script( $this->plugin_slug . '-admin-global-script', plugins_url( 'assets/js/admin_public.js?20170424', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, true );

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-lsapiclient', plugins_url( 'assets/js/lib/ls-apiclient-1.2.0.min.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, true );
			wp_enqueue_script( $this->plugin_slug . '-codemirror', plugins_url( 'assets/js/lib/codemirror.js', __FILE__ ), array(), VersionHandler::VERSION, true );
			wp_enqueue_script( $this->plugin_slug . '-codemirror-css', plugins_url( 'assets/js/lib/codemirror-css.js', __FILE__ ), array(), VersionHandler::VERSION, true );
			wp_enqueue_script( $this->plugin_slug . '-plugin-script-cookie', plugins_url( '../public/assets/js/libs/js.cookie.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION );
			wp_enqueue_script( $this->plugin_slug . '-arlo-for-wordpress-script', plugins_url( 'assets/js/arlo_for_wordpress.js?20170424', __FILE__ ), array( 'jquery', 'jquery-ui-core', $this->plugin_slug . '-plugin-script-cookie' ), VersionHandler::VERSION, true );
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js?20170424', __FILE__ ), array( 'jquery'), VersionHandler::VERSION, true );
			wp_enqueue_script( $this->plugin_slug . '-plugin-script-tingle', plugins_url( '../public/custom-assets/fancybox/jquery.fancybox.min.js' , __FILE__), array('jquery'), '3.3.7', true );
		}
	}

	/**
	 * Add Wordpress Feature Pointers
	 *
	 *
	 * @since     1.0.0
	 *
	 * @return    null
	 */
	public function enqueue_pointers() {

		/*
		 * $pointer = new Feature_Pointer($pointerID, $pointerTarget, $pointerTitle, $pointerContent, $pointerEdge, $pointerAlign)
		 *
		 * Parameters for Feature_Pointer:
		 * $pointerID: unique identifier for the pointer. Required
		 * $pointerTarget: The ID of the element that the pointer will point too. Required
		 * $pointerTitle: The title text of the pointer. Required
		 * $pointerContent: The main content of the pointer. Required
		 * $pointerEdge: To which edge of the pointer the target element will sit. Optional, defaults to 'left'
		 * $pointerAlign: How the pointer is aligned to the target element. Optional, defaults to 'center'
		 */

		$pointer = new Feature_Pointer('arlo-1st-pointer', '#toplevel_page_arlo-for-wordpress', __('Arlo for WordPress', 'arlo-for-wordpress' ), __('Arlo is almost ready. Just enter your details and you&apos;re good to go.', 'arlo-for-wordpress' ), 'left', 'center');

	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Add a settings page for this plugin to the Settings menu.
		 *
		 * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
		 *
		 *        Administration Menus: http://codex.wordpress.org/Administration_Menus
		 *
		 * @TODO:
		 *
		 * - Change 'Page Title' to the title of your plugin admin page
		 * - Change 'Menu Text' to the text for menu item for the plugin settings page
		 * - Change 'manage_options' to the capability you see fit
		 *   For reference: http://codex.wordpress.org/Roles_and_Capabilities
		 */
		 
		 /*
		$this->plugin_screen_hook_suffix = add_options_page(
			ARLO_PLUGIN_NAME . ' ' . __( 'Settings', 'arlo-for-wordpress' ),
			ARLO_PLUGIN_NAME,
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);
		*/
		
		$this->plugin_screen_hook_suffix = add_menu_page( 'Arlo settings page', 'Arlo settings', 'manage_options', $this->plugin_slug, array( $this, 'display_plugin_admin_page' ), 'none', '10.4837219128727371208127' );
		$this->plugin_events_screen_hook_suffix = add_submenu_page($this->plugin_slug, __( 'Events', 'arlo-for-wordpress' ), __( 'Events', 'arlo-for-wordpress' ) , 'manage_options' , $this->plugin_slug . '-events' , array( $this, 'display_events_admin_page'));		
		$this->plugin_oa_screen_hook_suffix = add_submenu_page($this->plugin_slug, __( 'Online Activities', 'arlo-for-wordpress' ), __( 'Online Activities', 'arlo-for-wordpress' ) , 'manage_options' , $this->plugin_slug . '-onlineactivities' , array( $this, 'display_oa_admin_page'));
		$this->plugin_templates_screen_hook_suffix = add_submenu_page($this->plugin_slug, __( 'Templates', 'arlo-for-wordpress' ), __( 'Templates', 'arlo-for-wordpress' ) , 'manage_options' , $this->plugin_slug . '-templates' , array( $this, 'display_templates_admin_page'));		
		$this->plugin_sessions_screen_hook_suffix = add_submenu_page($this->plugin_slug, __( 'Sessions', 'arlo-for-wordpress' ), __( 'Sessions', 'arlo-for-wordpress' ) , 'manage_options' , $this->plugin_slug . '-sessions' , array( $this, 'display_sessions_admin_page'));		
		$this->plugin_presenters_screen_hook_suffix = add_submenu_page($this->plugin_slug, __( 'Presenters', 'arlo-for-wordpress' ), __( 'Presenters', 'arlo-for-wordpress' ) , 'manage_options' , $this->plugin_slug . '-presenters' , array( $this, 'display_presenters_admin_page'));
		$this->plugin_venues_screen_hook_suffix = add_submenu_page($this->plugin_slug, __( 'Venues', 'arlo-for-wordpress' ), __( 'Venues', 'arlo-for-wordpress' ) , 'manage_options' , $this->plugin_slug . '-venues' , array( $this, 'display_venues_admin_page'));
		$this->plugin_loglist_screen_hook_suffix = add_submenu_page($this->plugin_slug, __( 'Logs', 'arlo-for-wordpress' ), __( 'Logs', 'arlo-for-wordpress' ) , 'manage_options' , $this->plugin_slug . '-logs' , array( $this, 'display_loglist_admin_page'));

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {	
		include_once( 'views/admin.php' );
	}
	
	/**
	 * Render the lists page for this plugin.
	 *
	 * @since    2.2.0
	 */
	public function display_venues_admin_page() {
	 	require_once 'includes/class-arlo-for-wordpress-venues.php';
 
 		$list = new Arlo_For_Wordpress_Venues();
	
		include_once( 'views/list.php' );
	}	
	
	public function display_presenters_admin_page() {
	 	require_once 'includes/class-arlo-for-wordpress-presenters.php';
 
 		$list = new Arlo_For_Wordpress_Presenters();
	
		include_once( 'views/list.php' );
	}	
	
	public function display_templates_admin_page() {
	 	require_once 'includes/class-arlo-for-wordpress-templates.php';
 
 		$list = new Arlo_For_Wordpress_Templates();
	
		include_once( 'views/list.php' );
	}	
	
	public function display_events_admin_page() {
	 	require_once 'includes/class-arlo-for-wordpress-events.php';
 
 		$list = new Arlo_For_Wordpress_Events();
	
		include_once( 'views/list.php' );
	}
	
	public function display_sessions_admin_page() {
	 	require_once 'includes/class-arlo-for-wordpress-sessions.php';
 
 		$list = new Arlo_For_Wordpress_Sessions();
	
		include_once( 'views/list.php' );
	}		

	public function display_oa_admin_page() {
	 	require_once 'includes/class-arlo-for-wordpress-onlineactivities.php';
 
 		$list = new Arlo_For_Wordpress_OnlineActivities();
	
		include_once( 'views/list.php' );
	}		

	public function display_loglist_admin_page() {
	 	require_once 'includes/class-arlo-for-wordpress-loglist.php';
 
 		$list = new Arlo_For_Wordpress_LogList();
	
		include_once( 'views/list.php' );
	}		
	
	

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', 'arlo-for-wordpress' ) . '</a>'
			),
			$links
		);

	}

	/**
	 * NOTE:     Actions are points in the execution of a page or process
	 *           lifecycle that WordPress fires.
	 *
	 *           Actions:    http://codex.wordpress.org/Plugin_API#Actions
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Action_Reference
	 *
	 * @since    1.0.0
	 */
	public function action_method_name() {
		// @TODO: Define your action hook callback here
	}

	/**
	 * NOTE:     Filters are points of execution in which WordPress modifies data
	 *           before saving it or sending it to the browser.
	 *
	 *           Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    1.0.0
	 */
	public function filter_method_name() {
		// @TODO: Define your filter hook callback here
	}

	/**
	 * NOTE:     Actions are points in the execution of a page or process
	 *           lifecycle that WordPress fires.
	 *
	 *           Actions:    http://codex.wordpress.org/Plugin_API#Actions
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Action_Reference
	 *
	 * @since    1.0.0
	 */
	public function arlo_register_settings() {
		
		require_once('includes/class-arlo-for-wordpress-settings.php');

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
		
		if ( ! isset( $wp_settings_sections[$page] ) )
			return;

		foreach ( (array) $wp_settings_sections[$page] as $section ) {
			echo '<div class="'.$section['id'].' arlo-section cf">';
			if ( $section['title'] )
				echo "<h3>{$section['title']}</h3>\n";

			if ( $section['callback'] )
				call_user_func( $section['callback'], $section );

			if ( ! isset( $wp_settings_fields ) || !isset( $wp_settings_fields[$page] ) || !isset( $wp_settings_fields[$page][$section['id']] ) )
				continue;
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
	function do_settings_fields($page, $section) {
		global $wp_settings_fields;

		if ( ! isset( $wp_settings_fields[$page][$section] ) )
			return;

		foreach ( (array) $wp_settings_fields[$page][$section] as $field ) {
			$field['args']['label_for'] = !empty($field['args']['label_for']) ? $field['args']['label_for'] : "";
			echo '<div class="' . ARLO_PLUGIN_PREFIX.'-field-wrap cf ' . ARLO_PLUGIN_PREFIX . '-' . strtolower(esc_attr($field['args']['label_for'])) . '" id="' . ARLO_PLUGIN_PREFIX . '-' . strtolower(esc_attr($field['args']['label_for'])) . '">';
				
			if($field['callback'][1] == 'arlo_template_callback') {
			
				echo '
					<table class="'.ARLO_PLUGIN_PREFIX.'-template-table">
						<tr>
							<td>
								<h2 class="nav-tab-wrapper vertical-nav-tab-wrapper">';
								    foreach(Arlo_For_Wordpress::$templates as $id => $template) {
								    	$name = __($template['name'], 'arlo-for-wordpress' );
										echo '<a href="#pages/'.$id.'" class="nav-tab vertical-nav-tab ' . $this->plugin_slug . '-pages-' . $id . '" id="' . $this->plugin_slug . '-pages-' . $id . '">'.$name.'</a>';
								    }
								echo '</h2>
							</td>
							
							<td>
								<div class="' . ARLO_PLUGIN_PREFIX . '-field ' . ARLO_PLUGIN_PREFIX . '-template-field">';
									call_user_func($field['callback'], $field['args']);

									$type = isset($field["args"]["type"]) ? $field["args"]["type"] : $field["id"];

									$path = ARLO_PLUGIN_DIR . 'admin/includes/codes/' . $type . '.php';
									if(file_exists($path)) {
										echo '<div class="' . ARLO_PLUGIN_PREFIX . '-shortcodes">
											<h3>' . __( 'Recommended shortcodes', 'arlo-for-wordpress' ) . '</h3>
											<a href="http://developer.arlo.co/doc/wordpress/shortcodes/" target="_blank">' . __( 'More about shortcodes', 'arlo-for-wordpress' ) . '</a>';
										
										include($path);
										echo '</div>';
									}
									
								echo '</div>
							</td>
						</tr>
					</table>
				';
			} else {
				echo '
					<div class="' . ARLO_PLUGIN_PREFIX . '-label"><label>' . $field['title'] . '</label></div>
					<div class="' . ARLO_PLUGIN_PREFIX . '-field">';
					call_user_func($field['callback'], $field['args']);
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

	public function disable_visual_editor($default) {

		if(isset($_GET['page']) && $_GET['page'] == 'arlo-for-wordpress') {

			return false;

		}

		return $default;
	}

	public function settings_pre_saved($new, $old) {
		$urlparts = parse_url(site_url());
		$domain = $urlparts['host'];

		if (empty($new['import_fragment_size']) || !is_numeric($new['import_fragment_size'])) {
			$new['import_fragment_size'] = ImportRequest::FRAGMENT_DEFAULT_BYTE_SIZE;
		} else if ($new['import_fragment_size'] > ImportRequest::FRAGMENT_MAX_BYTE_SIZE) {
			$new['import_fragment_size'] = ImportRequest::FRAGMENT_MAX_BYTE_SIZE;
		}

		if (empty($new['sleep_between_import_tasks']) || !is_numeric($new['sleep_between_import_tasks'])) {
			$new['sleep_between_import_tasks'] = 0;
		} else if ($new['sleep_between_import_tasks'] > \Arlo\Scheduler::MAX_SLEEP_BETWEEN_TASKS) {
			$new['sleep_between_import_tasks'] = \Arlo\Scheduler::MAX_SLEEP_BETWEEN_TASKS;
		}

		if (!empty($old["custom_shortcodes"])) {
			$new["custom_shortcodes"] = $old["custom_shortcodes"];
		} else {
			$new["custom_shortcodes"] = array();
		}

		// Custom shortcodes
		if (!empty($new["new_custom_shortcode"]) && !empty($new["new_custom_shortcode_type"]) && !array_key_exists( $new["new_custom_shortcode"], $old['custom_shortcodes'] ) && !shortcode_exists("arlo_" . $new["new_custom_shortcode"] . "") && preg_match('/^[\w]+$/',$new["new_custom_shortcode"]) === 1 ) {

			$shortcode_name = substr( sanitize_text_field(strtolower( str_replace( array("&","/","<",">","[","]","="),'',str_replace(' ','_',$new["new_custom_shortcode"]) ) )), 0, 15 ); // WP limits post name lengths

			setcookie("arlo-new-custom-shortcode", $shortcode_name, time()+60*60*24*30, '/', $domain);	

			$shortcode_type = $new["new_custom_shortcode_type"];

			if (empty($new['custom_shortcodes'])) {
				$new['custom_shortcodes'] = array();
			}

			$new['custom_shortcodes'][$shortcode_name] = $shortcode_type;

			$default_template = \Arlo_For_Wordpress::arlo_template_source()[ 'arlo-' . $new["new_custom_shortcode_type"] ];

			if ( !empty($default_template) ) {
				$new['templates'][ $new["new_custom_shortcode"] ]['html'] = $default_template;
			}
		}

		if (!empty($new["delete_shortcode"])) {
			unset( $new['custom_shortcodes'][$new["delete_shortcode"]] );
		}

		unset($new["new_custom_shortcode"]);
		unset($new["new_custom_shortcode_type"]);
		unset($new["delete_shortcode"]);

		return $new;
	}

	private function normalize_filter_options($settings_name, $setting_array) {
		//normalize filters options
		$filters = array();

		if (isset($setting_array[$settings_name]) && is_array($setting_array[$settings_name]) && count($setting_array[$settings_name])) {
			foreach($setting_array[$settings_name] as $filter_group_name => $filter_group) {
				foreach ($filter_group as $filter_name => $filter_settings) {
					foreach ($filter_settings as $filter_setting_id => $filter_setting) {
						$old_value = (isset($filter_setting['filteroldvalue']) ? esc_html($filter_setting['filteroldvalue']) : '');
						$new_value = (isset($filter_setting['filternewvalue']) ? esc_html($filter_setting['filternewvalue']) : '');
						if (strlen($new_value) > 64) {
							$new_value = substr($new_value, 0, 64);
						}
						
						if (isset($filter_setting["filteraction"]) && $filter_setting["filteraction"] == "rename" && isset($old_value) && !empty($new_value)) {
							$filters[$filter_group_name][$filter_name][$old_value] = $new_value;
						}

						if (isset($filter_setting["filteraction"]) && $filter_setting["filteraction"] == "exclude" && isset($old_value) && $old_value != '') {
							if (!isset($filters['hiddenfilters'][$filter_group_name][$filter_name])) {
								$filters['hiddenfilters'][$filter_group_name][$filter_name] = array();
							}

							array_push($filters['hiddenfilters'][$filter_group_name][$filter_name], htmlspecialchars_decode($old_value, ENT_QUOTES));
						} else if (isset($filter_setting["filteraction"]) && $filter_setting["filteraction"] == "showonly"  && isset($old_value) && $old_value != '') {
							if (!isset($filters['showonlyfilters'][$filter_group_name][$filter_name])) {
								$filters['showonlyfilters'][$filter_group_name][$filter_name] = array();
							}

							array_push($filters['showonlyfilters'][$filter_group_name][$filter_name], htmlspecialchars_decode($old_value, ENT_QUOTES));
						}
						else {
							if (!empty($filters['hiddenfilters'][$filter_group_name][$filter_name])) {
								$old_value_index = array_search($old_value, $filters['hiddenfilters'][$filter_group_name][$filter_name]);

								if ($old_value_index) {
									unset( $filters['hiddenfilters'][$filter_group_name][$filter_name][$old_value_index] );
								}
							}
						}
					}
				}
			}
		}

		update_option($settings_name, $filters);
	}
		
	public function settings_saved($old) {
		$new = get_option('arlo_settings', array());
		$import_id = get_option('arlo_import_id');
		$old_regions = get_option('arlo_regions', array());
		$plugin = Arlo_For_Wordpress::get_instance();

		//save theme changes
		$theme_id = get_option('arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME);
		$stored_themes_settings = get_option( 'arlo_themes_settings', [] );
		$stored_themes_settings[$theme_id]->templates = $new['templates'];
		update_option('arlo_themes_settings', $stored_themes_settings, 1);
			
		if($old['platform_name'] != $new['platform_name'] && !empty($new['platform_name'])) {
			$plugin->determine_url_structure($new['platform_name']);
			
			$scheduler = $plugin->get_scheduler();
			$scheduler->set_task("import", -1);
		} else if (empty($new['platform_name'])) {
			$notice_id = Arlo_For_Wordpress::$dismissible_notices['welcome'];
			$user = wp_get_current_user();
			update_user_meta($user->ID, $notice_id, 1);			
		}
		
		update_option('arlo_customcss', 'inline');
		
		$access_type = get_filesystem_method();
		if($access_type === 'direct') {

			$creds = request_filesystem_credentials(site_url() . '/wp-admin/', '', false, false, array());

			if (WP_Filesystem($creds)) {
				global $wp_filesystem;
				$custom_css = (isset($new['customcss']) ? $new['customcss'] : '');
				$new['customcss'] = preg_replace('/<\/style>/i', '', $custom_css);
				
				$filename = trailingslashit(plugin_dir_path( __FILE__ )).'../public/assets/css/custom.css';
				if ($wp_filesystem->put_contents( $filename, $new['customcss'], FS_CHMOD_FILE)) {
					update_option('arlo_customcss', 'file');
					update_option('arlo_customcss_timestamp', time());
				} 
			}	
		}

		//check if the tax exempt tag has changed
		if (!empty($import_id) && (
			(isset($new['taxexempt_tag']) && isset($old['taxexempt_tag']) && $new['taxexempt_tag'] != $old['taxexempt_tag']) ||
			!isset($new['taxexempt_tag']) || !isset($old['taxexempt_tag'])
			)) {
			$plugin->get_importer()->set_tax_exempt_events($import_id);
		}

		//normalize regions
		$regions = array();
		if (is_array($new['regionid']) && count($new['regionid'])) {
			foreach($new['regionid'] as $key => $regionid) {
				if (!empty($regionid) && !empty($new['regionname'][$key])) {
					$regions[$regionid] = $new['regionname'][$key];
				}
			}
		}
		update_option('arlo_regions', $regions);

		//normalize filter options and save them
		$this->normalize_filter_options('arlo_filter_settings', $new);
		$this->normalize_filter_options('arlo_page_filter_settings', $new);

		// need to check for posts-page change here
		// loop through each post type and check if the posts-page has changed
		foreach($new['post_types'] as $id => $post_type) {
			if(isset($old['post_types'][$id]['posts_page']) && $old['post_types'][$id]['posts_page'] != $new['post_types'][$id]['posts_page']) {
				$posts = get_posts(array(
					'posts_per_page'	=> -1,
					'post_type'			=> 'arlo_' . $id,
					'post_status'		=> 'publish' // only there to ensure we don't create a loop if a user has tampered
				));
				
				$posts = array_merge($posts, $regions);
			
				// update all posts of this type to have this parent id
				foreach($posts as $post) {
					wp_update_post(array(
						'ID'			=> $post->ID,
						'post_parent'	=> $new['post_types'][$id]['posts_page']
					));
				}
			}
		}
	}

}