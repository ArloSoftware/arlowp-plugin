<?php
/**
 * Arlo For Wordpress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      http://arlo.co
 * @copyright 2015 Arlo
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
 * @author  Your Name <email@example.com>
 */
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

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		add_filter ( 'user_can_richedit' , array( $this, 'disable_visual_editor') , 50 );

		/*
		 * Define custom functionality.
		 *
		 * Read more about actions and filters:
		 * http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		add_action( '@TODO', array( $this, 'action_method_name' ) );
		add_filter( '@TODO', array( $this, 'filter_method_name' ) );
		
		add_action( 'update_option_arlo_settings', array($this, 'settings_saved') );
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

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), Arlo_For_Wordpress::VERSION );
			wp_enqueue_style( $this->plugin_slug .'-tooltip-styles', plugins_url( 'assets/css/lib/darktooltip.min.css', __FILE__ ), array(), Arlo_For_Wordpress::VERSION );
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

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-tooltip-script', plugins_url( 'assets/js/lib/jquery.darktooltip.min.js', __FILE__ ), array( 'jquery' ), Arlo_For_Wordpress::VERSION, true );
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), Arlo_For_Wordpress::VERSION, true );
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

		$pointer = new Feature_Pointer('arlo-1st-pointer', '#menu-settings', __('Arlo',$this->plugin_slug), __('Arlo is almost ready. Just enter your details and you&apos;re good to go.',$this->plugin_slug), 'left', 'center');

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
		$this->plugin_screen_hook_suffix = add_options_page(
			PLUGIN_NAME . ' ' . __( 'Settings', $this->plugin_slug ),
			PLUGIN_NAME,
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

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
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
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
			
		if ($section == 'arlo_template_section') {
			echo "<h5>Available shortcodes</h5>";
		}

		foreach ( (array) $wp_settings_fields[$page][$section] as $field ) {
			echo '<div class="'.PLUGIN_PREFIX.'-field-wrap cf '.PLUGIN_PREFIX.'-'. strtolower(esc_attr($field['args']['label_for'])).'">';
			if ( !empty($field['args']['label_for']) )
				echo '<div class="'.PLUGIN_PREFIX.'-label"><label for="' . esc_attr( $field['args']['label_for'] ) . '">' . $field['title'] . '</label>';
			else
				echo '<div class="'.PLUGIN_PREFIX.'-label"><label>' . $field['title'] . '</label>';
			if($field['callback'][1] == 'arlo_template_callback') {
				$path = PLUGIN_DIR.'admin/includes/codes/'.$field['id'].'.php';
				if(file_exists($path)) include($path);
			}
			echo '</div>';
			echo '<div class="'.PLUGIN_PREFIX.'-field">';
			call_user_func($field['callback'], $field['args']);
			echo '</div>';
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
	
	public function settings_saved($old) {
		$new = get_option('arlo_settings', array());
	
		if($old['platform_name'] != $new['platform_name']) {
			$plugin = Arlo_For_Wordpress::get_instance();
			$plugin->import();
		}
		
		// need to check for posts-page change here
		// loop through each post type and check if the posts-page has changed
		foreach($new['post_types'] as $id => $post_type) {
			if($old['post_types'][$id]['posts_page'] != $new['post_types'][$id]['posts_page']) {
				$posts = get_posts(array(
					'posts_per_page'	=> -1,
					'post_type'			=> 'arlo_' . $id,
					'post_status'		=> 'publish' // only there to ensure we don't create a loop if a user has tampered
				));
			
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
