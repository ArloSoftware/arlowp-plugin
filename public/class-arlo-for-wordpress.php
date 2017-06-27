<?php

use Arlo\Database\WPDatabaseLayer;
use Arlo\Provisioning\SchemaManager;
use Arlo\Logger;
use Arlo\VersionHandler;
use Arlo\Scheduler;
use Arlo\Importer\Importer;
use Arlo\Importer\ImportRequest;
use Arlo\MessageHandler;
use Arlo\NoticeHandler;
use ArloAPI\Transports\Wordpress;
use ArloAPI\Client;
use Arlo\Utilities;
use Arlo\Environment;
use Arlo\ThemeManager;
use Arlo\TimeZoneManager;
use Arlo\SystemRequirements;

/**
 * Arlo for WordPress.
 * Text Domain: arlo-for-wordpress
 *
 * @package   Arlo_For_Wordpress
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      http://arlo.co
 * @copyright 2015 Arlo
 * 
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to 'class-arlo-for-wordpress-admin.php'
 *
 *
 * @package Arlo_For_Wordpress
 * @author  Adam Fentosi <adam.fentosi@arlo.co>
 */
class Arlo_For_Wordpress {

	/**
	 * Minimum required PHP version
	 *
	 * @since   2.0.6
	 *
	 * @var     string
	 */
	const MIN_PHP_VERSION = '5.4.0';

	/**
	 * The default loaded theme
	 *
	 * @since   3.0
	 *
	 * @var     string
	 */
	const DEFAULT_THEME = 'jazz';

	/**
	 * The default platform name
	 *
	 * @since   3.0
	 *
	 * @var     string
	 */
	const DEFAULT_PLATFORM = 'websitetestdata';

	/**
	 * Google maps API key for the default platform
	 *
	 * @since   3.0
	 *
	 * @var     string
	 */
	const GOOGLE_MAPS_API_KEY = 'AIzaSyCJO87A9heNXZUThrJudaxiu0X4mqy3cvw';

	/**
	 * @TODO - Rename "arlo-for-wordpress" to the name your your plugin
	 *
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	public $plugin_slug = 'arlo-for-wordpress';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Location for overloaded data.
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
    protected $data = array();
    
	/**
	 * $post_types: used to set default settings & create posts types for import
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
    public static $post_types = array(
		'upcoming' => array(
			'slug' => 'upcomingevents',
			'name' => 'Upcoming events',
			'singular_name' => 'Upcoming event list',
			'regionalized' => true
		),
		'event' => array(
			'slug' => 'event',
			'name' => 'Events',
			'singular_name' => 'Catalogue',
			'regionalized' => true
		),
		'venue' => array(
			'slug' => 'venue',
			'name' => 'Venues',
			'singular_name' => 'Venue list'
		),		
		'presenter' => array(
			'slug' => 'presenter',
			'name' => 'Presenters',
			'singular_name' => 'Presenter list'
		),		
		'eventsearch' => array(
			'slug' => 'eventsearch',
			'name' => 'Event search',
			'singular_name' => 'Event search',
			'regionalized' => true
		),
		'oa' => array(
			'slug' => 'onlineactivities',
			'name' => 'Online activities',
			'singular_name' => 'Online activity',
			'regionalized' => true
		)
    );
    
	/**
	 * $pages: used to set the necessary pages
	 *
	 * @since    2.2.0
	 *
	 * @var      array
	 */
	 
    public static $pages = array(

			array(
				'name'				=> 'events',
				'title'				=> 'Events',
				'content' 			=> '[arlo_event_template_list]',
				'child_post_type'	=> 'event'
			),
			array(
				'name'				=> 'eventsearch',
				'title'				=> 'Event search',
				'content' 			=> '[arlo_event_template_search_list]',
				'child_post_type'	=> 'event'
			),			
			array(
				'name'				=> 'upcoming',
				'title'				=> 'Upcoming Events',
				'content' 			=> '[arlo_upcoming_list]'
			),
			array(
				'name'				=> 'presenters',
				'title'				=> 'Presenters',
				'content' 			=> '[arlo_presenter_list]',
				'child_post_type'	=> 'presenter'
			),
			array(
				'name'				=> 'venues',
				'title'				=> 'Venues',
				'content' 			=> '[arlo_venue_list]',
				'child_post_type'	=> 'venue'
			),
			array(
				'name'				=> 'oa',
				'title'				=> 'Online Activities',
				'content' 			=> '[arlo_onlineactivites_list]',
				'child_post_type'	=> 'event'
			),
		);  

    
	/**
	 * $price_settings: used to set the price showing on the site
	 *
	 * @since    2.1.0
	 *
	 * @var      array
	 */
    public static $price_settings = array(
        'exclgst' => 'Exclude GST.',
        'inclgst' => 'Include GST.',
    ); 
    
	/**
	 * $dismissible_notices: valid dismissible notices
	 *
	 * @since    2.1.5
	 *
	 * @var      array
	 */
    public static $dismissible_notices = array(
    	'welcome' => 'arlo-welcome-admin-notice',
    	'developer' => 'arlo-developer-admin-notice',
    	'webinar' => 'arlo-webinar-admin-notice',
    	'newpages' => 'arlo-newpages-admin-notice',
		'wp_video' => 'arlo-wp-video',
    );     
    
	/**
	 * $delivery_labels: used to show the different delivery types
	 *
	 * @since    2.0.6
	 *
	 * @var      array
	 */
    public static $delivery_labels = array(
        0 => 'Workshop',
        1 => 'Online',
    );
    
	/**
	 * $templates: defines the available templates for the plugin
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
    public static $templates = array(
		'event' => array(
			'id' => 'event',
			'name' => 'Event',
		),
		'events' => array(
			'id' => 'events',
			'shortcode' => '[arlo_event_template_list]',
			'name' => 'Catalogue'
		),
		'eventsearch' => array(
			'id' => 'eventsearch',
			'shortcode' => '[arlo_event_template_search_list]',
			'name' => 'Event search list'
		),
		'upcoming' => array(
			'id' => 'upcoming',
			'shortcode' => '[arlo_upcoming_list]',
			'name' => 'Upcoming event list',
		),
		'oa' => array(
			'id' => 'oa',
			'shortcode' => '[arlo_onlineactivites_list]',
			'name' => 'Online activity list'
		),
		'presenter' => array(
			'id' => 'presenter',
			'name' => 'Presenter'
		),
		'presenters' => array(
			'id' => 'presenters',
			'shortcode' => '[arlo_presenter_list]',
			'name' => 'Presenter list'
		),
		'venue' => array(
			'id' => 'venue',
			'name' => 'Venue'
		),
		'venues' => array(
			'id' => 'venues',
			'shortcode' => '[arlo_venue_list]',
			'name' => 'Venue list'
		)
    );

	/**
	 * $available_filters: defines the available filters for the plugin
	 *
	 * @since    3.2.0
	 *
	 * @var      array
	 */

	public static  $available_filters = array(
		'event' => array(
			'name' => 'Event',
			'filters' => array(
				'location' => 'Location'
			)
		),
		'upcoming' => array(
			'name' => 'Upcoming',
			'filters' => array(
				'category' => 'Category', 
				'month' => 'Month', 
				'location' => 'Location', 
				'delivery' => 'Delivery', 
				'eventtag' => 'Event tag', 
				'templatetag' => 'Template tag', 
				'presenter' => 'Presenter'
			)
		),
		'oa' => array(
			'name' => 'Online activities',
			'filters' => array(
				'oatag' => 'Online activity tag', 
				'templatetag' => 'Template tag',
				'category' => 'Category'
			)
		),
		'template' => array(
			'name' => 'Catalogue',
			'filters' => array(
				'category' => 'Category', 
				'delivery' => 'Delivery', 
				'location' => 'Location', 
				'templatetag' => 'Tag'
			)
		)
	);


	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {
		// check for a proxy redirect request
		add_action( 'wp', array( $this, 'redirect_proxy' ) );

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Register custom post types
		add_action( 'init', 'arlo_register_custom_post_types');

		// Load public-facing style sheet and JavaScript.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		
		// cron actions
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) ); 
		add_action( 'arlo_scheduler', array( $this, 'cron_scheduler' ) );
		
		add_action( 'arlo_set_import', array( $this, 'cron_set_import' ) );
		
		//load custom css
		add_action( 'wp_head', array( $this, 'load_custom_css' ) );
		
		//add canonical urls for the filtered lists
		add_action( 'wp_head', array( $this, 'add_canonical_urls' ) );
		
		//add meta description
		add_action( 'wp_head', array( $this, 'add_meta_description' ) );
		
		// GP: Check if the scheduled task is entered. If it does not exist set it. (This ensures it is in as long as the plugin is activated.  
		if ( ! wp_next_scheduled('arlo_set_import')) {
			wp_schedule_event( time(), 'minutes_30', 'arlo_set_import' );
		}
		

		// content and excerpt filters to hijack arlo registered post types
		add_filter('the_content', 'arlo_the_content');
	
	
		add_action( 'wp_ajax_arlo_dismissible_notice', array($this, 'dismissible_notice_callback'));

		add_action( 'wp_ajax_arlo_turn_off_send_data', array($this, 'turn_off_send_data_callback'));
		
		add_action( 'wp_ajax_arlo_dismiss_message', array($this, 'dismiss_message_callback'));
		
		add_action( 'wp_ajax_arlo_start_scheduler', array($this, 'start_scheduler_callback'));
		
		add_action( 'wp_ajax_arlo_get_task_info', array($this, 'arlo_get_task_info_callback'));
		
		add_action( 'wp_ajax_arlo_terminate_task', array($this, 'arlo_terminate_task_callback'));
		
		add_action( 'wp_ajax_arlo_get_last_import_log', array($this, 'arlo_get_last_import_log_callback'));

		//load scheduler tasks
		add_action( 'wp_ajax_arlo_run_scheduler', array( $this, 'run_scheduler' ) );
		add_action( 'wp_ajax_nopriv_arlo_run_scheduler', array( $this, 'run_scheduler' ) );

		add_action( 'wp_ajax_arlo_import_callback', array( $this, 'import_callback' ) );
		add_action( 'wp_ajax_nopriv_arlo_import_callback', array( $this, 'import_callback' ) );
		
		// the_post action - allows us to inject Arlo-specific data as required
		// consider this later
		//add_action( 'the_posts', array( $this, 'the_posts_action' ) );
		
		add_action( 'init', 'set_search_redirect');
		
		add_action( 'wp', 'set_region_redirect');

		\Arlo\Shortcodes\Shortcodes::init(); 
	}

	/**
	 * Import callback
	 *
	 * @since     2.5
	 *
	 * @return    null
	 */	

	public function import_callback() {
		if (get_option('arlo_import_disabled', '0') == '1') return;
		$this->get_importer()->callback();
	}	

	/**
	 * Run the scheduler action
	 *
	 * @since     2.4.1
	 *
	 * @return    null
	 */
	public function run_scheduler() {
		session_write_close();
		check_ajax_referer( 'arlo_import', 'nonce' );

		//avoid too many sql connections because of the async tasks
		$settings = get_option('arlo_settings');
		if (!empty($settings['sleep_between_import_tasks']) && is_numeric($settings['sleep_between_import_tasks'])) 
			sleep($settings['sleep_between_import_tasks']); 
		
		$this->cron_scheduler();
		wp_die();
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
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
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		$plugin = Arlo_For_Wordpress::get_instance();

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = $plugin->get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					$plugin->single_activate();
				}

				restore_current_blog();

			} else {
				$plugin->single_activate();
			}

		} else {
			$plugin->single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {
		$plugin = Arlo_For_Wordpress::get_instance();

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = $plugin->get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					$plugin->single_deactivate();

				}

				restore_current_blog();

			} else {
				$plugin->single_deactivate();
			}

		} else {
			$plugin->single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		$this->single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );
	}
	
	
	/**
	 * Send log to Arlo
	 *
	 * @since     2.4
	 *
	 * @return    null
	 */	
	

	public function send_log_to_arlo($message = '') {	
		$client = $this->get_api_client();		
		$last_import = $this->get_importer()->get_last_import_date();
		
		$log = Logger::create_log_csv(1000);
		
		try {
			$response = $client->WPLogError()->sendLog($message, $last_import, $log);
		} catch (\Exception $e) {
			Logger::log($e->getMessage());
		}
	}		

	
	/**
	 * Check the plugin version on bulk update
	 *
	 * @since     2.4
	 *
	 * @return    null
	 */	
	
	public static function bulk_plugin_updater( $upgrader_object, $data ) {
		if ($data['action'] == 'update' && $data['type'] == 'plugin' ) {
			foreach($data['plugins'] as $each_plugin){
				if (basename($each_plugin) == 'arlo-for-wordpress.php'){
					Arlo_For_Wordpress::check_plugin_version();
				}
			}
		}
	}


	/**
	 * Check the version of the db schema
	 *
	 * @since     2.4
	 *
	 * @return    null
	 */
	public function check_db_schema() { 		
		$this->get_schema_manager()->check_db_schema();
	}	
	
	/**
	 * Check the version of the plugin
	 *
	 * @since     2.4
	 *
	 * @return    null
	 */
	public static function check_plugin_version() {
		$plugin = Arlo_For_Wordpress::get_instance();

		$plugin_version = $plugin->get_version_handler()->get_current_installed_version();
		
		if (!empty($plugin_version)) {
            $import_id  = get_option('arlo_import_id',"");
            $last_import = $plugin->get_importer()->get_last_import_date();

			//check system requirements and disable the import
			if (!SystemRequirements::overall_check()) {
				update_option( 'arlo_import_disabled', 1 );
			} else {
				update_option( 'arlo_import_disabled', 0 );
				update_option( 'arlo_plugin_disabled', 0 );
			}			
            
            if (empty($import_id)) {
                if (empty($last_import)) {
                    $last_import = date("Y");
                }
                $plugin->get_importer()->set_import_id(date("Y", strtotime($last_import)));
            }
                        
			if ($plugin_version != VersionHandler::VERSION) {
				$plugin->get_version_handler()->run_update($plugin_version);
				
				$plugin->get_schema_manager()->check_db_schema();
			}
		} else {
			arlo_add_datamodel();

			$plugin->get_version_handler()->set_installed_version();

			//check system requirements and disable the plugin/import
			if (!SystemRequirements::overall_check()) {
				update_option( 'arlo_plugin_disabled', 1 );
				update_option( 'arlo_import_disabled', 1 );
			} 
		}

		//force the plugin to use new url structure
		update_option('arlo_new_url_structure', 1);	
	}
	

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private function single_activate() {
		//check plugin version and forca data modell update
		$this->check_plugin_version();
		arlo_add_datamodel();

		// flush permalinks upon plugin deactivation
		flush_rewrite_rules();

		//set default themes
		$this->set_default_theme();

		// must happen before adding pages
		$this->set_default_options();
		
		// run import every 15 minutes
		Logger::log("Plugin activated");

		// now add pages
		self::add_pages();

		update_option('arlo_plugin_version', VersionHandler::VERSION);

		if (!SystemRequirements::overall_check()) {
			update_option( 'arlo_plugin_disabled', 1 );
			update_option( 'arlo_import_disabled', 1 );
		} 		

		//load demo data
		$settings = get_option('arlo_settings');
		if (empty($settings['platform_name'])) {
			$plugin = self::get_instance();
			$plugin->load_demo();
			do_action('arlo_scheduler');
		}
	}

	/**
	 * Set the default theme
	 *
	 * @since    3.0.0
	 *
	 */	
	public function set_default_theme() {
		$theme_id = get_option('arlo_theme', '');

		//set default theme
		if (empty($theme_id)) {
			$theme_manager = $this->get_theme_manager();	
			$theme_settings = $theme_manager->get_themes_settings();

			$theme_id = Arlo_For_Wordpress::DEFAULT_THEME;

			$stored_themes_settings[$theme_id] = $theme_settings[$theme_id];
			$stored_themes_settings[$theme_id]->templates = $theme_manager->load_default_templates($theme_id);

			update_option('arlo_themes_settings', $stored_themes_settings, 1);
			update_option('arlo_theme', $theme_id, 1);
		}
	}

	/**
	 * Set the default values for arlo wp_options table option
	 *
	 * @since    1.0.0
	 *
	 */
	private function set_default_options() {
		$settings = get_option('arlo_settings');
		
		if (is_array($settings) && count($settings)) {
			//add new templates			
			foreach($this::$templates as $id => $template) {
				if (empty($settings['templates'][$id]['html'])) {
					$settings['templates'][$id] = array(
						'html' => arlo_get_template($id)
					);
				}
			}
			
			update_option('arlo_settings', $settings);
			
		} else {
			$default_settings = array(
				'platform_name' => '',
				'post_types' => self::$post_types,
				'templates' => array()
			);
			
			foreach($this::$templates as $id => $template) {
				$default_settings['templates'][$id] = array(
					'html' => arlo_get_template($id)
				);
			}		
			
			add_option('arlo_settings', $default_settings);			
		}
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private function single_deactivate() {
		// flush permalinks upon plugin deactivation
		flush_rewrite_rules();
		
		wp_clear_scheduled_hook( 'arlo_scheduler' );
		wp_clear_scheduled_hook( 'arlo_set_import' );
		wp_clear_scheduled_hook( 'arlo_import' );
		
		$this->delete_running_tasks();
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		
		load_plugin_textdomain( $domain, false, plugin_basename( dirname( __FILE__ ) ) . '/../languages' );
		
	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css?20170424', __FILE__ ), array(), VersionHandler::VERSION );
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles-darktooltip', plugins_url( 'assets/css/libs/darktooltip.min.css', __FILE__ ), array(), VersionHandler::VERSION );
		wp_enqueue_style( $this->plugin_slug .'-arlo-icons8', plugins_url( '../admin/assets/fonts/arlo-icons8/Arlo-WP.css', __FILE__ ), array(), VersionHandler::VERSION );

		//enqueue theme related styles
		$stored_themes_settings = get_option( 'arlo_themes_settings', [] );
		$theme_id = get_option( 'arlo_theme');
		
		if (isset($stored_themes_settings[$theme_id])) {
			//internal resources
			if (isset($stored_themes_settings[$theme_id]->internalResources->stylesheets) && is_array($stored_themes_settings[$theme_id]->internalResources->stylesheets)) {
				foreach ($stored_themes_settings[$theme_id]->internalResources->stylesheets as $key => $stylesheet) {
					wp_enqueue_style( $this->plugin_slug . '-theme-internal-stylesheet-' . $key, $stylesheet, [], $stored_themes_settings[$theme_id]->version );
				}
			} 

			//external resources
			if (isset($stored_themes_settings[$theme_id]->externalResources->stylesheets) && is_array($stored_themes_settings[$theme_id]->externalResources->stylesheets)) {
				foreach ($stored_themes_settings[$theme_id]->externalResources->stylesheets as $key => $stylesheet) {
					wp_enqueue_style( $this->plugin_slug . '-theme-external-stylesheet-' . $key, $stylesheet, [], VersionHandler::VERSION );
				}
			} 			
		}

		$customcss_load_type = get_option('arlo_customcss');
		if ($customcss_load_type == 'file' && file_exists(plugin_dir_path( __FILE__ ) . 'assets/css/custom.css')) {
			$customcss_timestamp = get_option('arlo_customcss_timestamp');
			wp_enqueue_style( $this->plugin_slug .'-custom-styles', plugins_url( 'assets/css/custom.css', __FILE__ ), array(), $customcss_timestamp );		
		}			
	}
	
	/**
	 * Add canonical urls for the filtered lists (upcoming, category).
	 * SEO compatibility
	 *
	 * @since    2.2.0
	 */
	public function add_canonical_urls() {
		$settings = get_option('arlo_settings');
		$page_id = get_query_var('page_id', '');
		$obj = get_queried_object();
		
		$page_id = (empty($obj->ID) ? $page_id : $obj->ID);	
		
		$filter_enabled_page_ids = [];
						
		foreach($this::$available_filters as $page => $filters) {
			if (!empty($settings['post_types'][$page]['posts_page'])) {
				$filter_enabled_page_ids[] = intval($settings['post_types'][$page]['posts_page']);
			}			
		}
				
		if (in_array($page_id, $filter_enabled_page_ids)) {
			$url = get_home_url() . '/' .$obj->post_name;

			//has to be the same order as in public.js to construct the same order
			if (!empty($_GET['arlo-category'])) {
				$url .= '/cat-' . wp_unslash($_GET['arlo-category']);
			}
			
			if (!empty($_GET['arlo-month'])) {
				$url .= '/month-' . wp_unslash($_GET['arlo-month']);
			}
			
			if (!empty($_GET['arlo-location'])) {
				$url .= '/location-' . wp_unslash($_GET['arlo-location']);
			}

			if (isset($_GET['arlo-delivery']) && is_numeric($_GET['arlo-delivery'])) {
				$url .= '/delivery-' . intval($_GET['arlo-delivery']);
			}

			if (!empty($_GET['arlo-presenter'])) {
				$url .= '/presenter-' . wp_unslash($_GET['arlo-presenter']);
			}

			if (!empty($_GET['arlo-eventtag'])) {
				if (is_numeric($_GET['arlo-eventtag'])) {
					$tag = self::get_tag_by_id($_GET['arlo-eventtag']);
					if (!empty($tag['tag'])) {
						$_GET['arlo-eventtag'] = $tag['tag'];
					}
				}
				$url .= '/eventtag-' . wp_unslash($_GET['arlo-eventtag']);
			}
			
			if (!empty($_GET['arlo-oatag'])) {
				if (is_numeric($_GET['arlo-oatag'])) {
					$tag = self::get_tag_by_id($_GET['arlo-oatag']);
					if (!empty($tag['tag'])) {
						$_GET['arlo-oatag'] = $tag['tag'];
					}
				}
				$url .= '/oatag-' . wp_unslash($_GET['arlo-oatag']);
			}

			if (!empty($_GET['arlo-templatetag'])) {
				if (is_numeric($_GET['arlo-templatetag'])) {
					$tag = self::get_tag_by_id($_GET['arlo-templatetag']);
					if (!empty($tag['tag'])) {
						$_GET['arlo-templatetag'] = $tag['tag'];
					}					
				}
			
				$url .= '/templatetag-' . wp_unslash($_GET['arlo-templatetag']);
			}
			
			echo '<link rel="canonical" href="' . esc_url($url) . '/" />';
		}
	}	
	
	/**
	 * Add meta descriptions for the template
	 * SEO compatibility
	 *
	 * @since    2.2.0
	 */
	public function add_meta_description() {
		$settings = get_option('arlo_settings');
		$page_id = get_query_var('page_id', '');
		$obj = get_queried_object();
		
		$page_id = (empty($obj->ID) ? $page_id : $obj->ID);
		
		if (!empty($obj->post_type) && $obj->post_type == 'arlo_event' && !empty($obj->post_content)) {
			$ellipsis = '';
			$desc = strip_tags($obj->post_content);
			if (strlen($desc) >= 150) {
				$end_pos = strpos($desc, " ", 140);
				$ellipsis = '...';
			} else {
				$end_pos = strlen($desc);
			}
			$desc = substr($desc, 0, $end_pos) . $ellipsis;
			
			echo '<meta description="' . htmlspecialchars($desc, ENT_COMPAT, 'UTF-8') . '">';
		}
	}	
	
	
	
	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    2.2.0
	 */
	public function load_custom_css() {
		$customcss_load_type = get_option('arlo_customcss');
				
		if ($customcss_load_type !== 'file' || !file_exists(plugin_dir_path( __FILE__ ) . 'assets/css/custom.css')) {
			$settings = get_option('arlo_settings');
			
			if (!empty($settings['customcss'])) {
				echo "\n<style type=\"text/css\">\n" . preg_replace('/<\/style>/i', '', $settings['customcss']) . "\n</style>\n";
			}
		}
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js?20170424', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION );
		wp_enqueue_script( $this->plugin_slug . '-plugin-script-darktooltip', plugins_url( 'assets/js/libs/jquery.darktooltip.min.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION );
		wp_enqueue_script( $this->plugin_slug . '-plugin-script-cookie', plugins_url( 'assets/js/libs/jquery.cookie.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION );
		wp_localize_script( $this->plugin_slug . '-plugin-script', 'objectL10n', array(
			'showmoredates' => __( 'Show me more dates', 'arlo-for-wordpress' ),
		) );
		wp_localize_script( $this->plugin_slug . '-plugin-script', 'WPUrls', array(
			'home_url' => get_home_url(),
		) );

		//enqueue theme related scripts
		$stored_themes_settings = get_option( 'arlo_themes_settings', [] );
		$theme_id = get_option( 'arlo_theme');

		if (isset($stored_themes_settings[$theme_id])) {
			//internal resources
			if (isset($stored_themes_settings[$theme_id]->internalResources->javascripts) && is_array($stored_themes_settings[$theme_id]->internalResources->javascripts)) {
				foreach ($stored_themes_settings[$theme_id]->internalResources->javascripts as $key => $script) {
					wp_enqueue_script( $this->plugin_slug . '-theme-internal-script-' . $key, $script, array( 'jquery' ), $stored_themes_settings[$theme_id]->version );
				}
			} 

			//external resources
			if (isset($stored_themes_settings[$theme_id]->externalResources->javascripts) && is_array($stored_themes_settings[$theme_id]->externalResources->javascripts)) {
				foreach ($stored_themes_settings[$theme_id]->externalResources->javascripts as $key => $script) {
					wp_enqueue_script( $this->plugin_slug . '-theme-external-script-' . $key, $script, [], VersionHandler::VERSION );
				}
			} 			
		}
	}
	
	/**  Local Setter  */
	public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }
    
    /**  Local Getter  */
    public function __get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        return null;
    }         
	
	public function get_scheduler() {
		if($scheduler = $this->__get('scheduler')) {
			return $scheduler;
		}
		
		$scheduler = new Scheduler($this, $this->get_dbl());
		
		$this->__set('scheduler', $scheduler);
		
		return $scheduler;
	}

	public function get_theme_manager() {
		if($theme_manager = $this->__get('theme_manager')) {
			return $theme_manager;
		}
		
		$theme_manager = new ThemeManager($this, $this->get_dbl());
		
		$this->__set('theme_manager', $theme_manager);
		
		return $theme_manager;
	}	

	public function get_importer() {
		if($importer = $this->__get('importer')) {
			return $importer;
		}

		$settings = get_option('arlo_settings');
		
		$importer = new Importer($this->get_environment(), $this->get_dbl(), $this->get_message_handler(), $this->get_api_client(), $this->get_scheduler());

		if (!empty($settings['import_fragment_size'])) {
			$importer->fragment_size = $settings['import_fragment_size'];
		}
		
		$this->__set('importer', $importer);
		
		return $importer;
	}

	public function get_environment() {
		if($get_environment = $this->__get('get_environment')) {
			return $get_environment;
		}
		
		$get_environment = new Environment();
		
		$this->__set('get_environment', $get_environment);
		
		return $get_environment;
	}	

	public function get_notice_handler() {
		if($notice_handler = $this->__get('notice_handler')) {
			return $notice_handler;
		}
		
		$notice_handler = new NoticeHandler($this->get_message_handler(), $this->get_importer(), $this->get_dbl());
		
		$this->__set('notice_handler', $notice_handler);
		
		return $notice_handler;
	}		
	
	public function get_message_handler() {
		if($message_handler = $this->__get('message_handler')) {
			return $message_handler;
		}
		
		$message_handler = new MessageHandler($this->get_dbl());
		
		$this->__set('message_handler', $message_handler);
		
		return $message_handler;
	}	

	public function get_dbl() {
		if($dbl = $this->__get('dbl')) {
			return $dbl;
		}
		
		$dbl = new WPDatabaseLayer();
		
		$this->__set('dbl', $dbl);
		
		return $dbl;
	}	

	public function get_timezone_manager() {
		if($timezone_manager = $this->__get('timezone_manager')) {
			return $timezone_manager;
		}
		
		$timezone_manager = new TimeZoneManager($this, $this->get_dbl());
		
		$this->__set('timezone_manager', $timezone_manager);
		
		return $timezone_manager;
	}	

	public function get_schema_manager() {
		if($schema_manager = $this->__get('schema_manager')) {
			return $schema_manager;
		}
		
		$schema_manager = new SchemaManager($this->get_dbl(), $this->get_message_handler(), $this);
		
		$this->__set('schema_manager', $schema_manager);
		
		return $schema_manager;
	}	

	public function get_version_handler() {
		if($version_handler = $this->__get('version_handler')) {
			return $version_handler;
		}
		
		$version_handler = new VersionHandler($this->get_dbl(), $this->get_message_handler(), $this, $this->get_theme_manager());
		
		$this->__set('version_handler', $version_handler);
		
		return $version_handler;
	}		
	
	public function get_api_client() {
		if(get_option('arlo_test_api')) {
			define('ARLO_TEST_API', true);
		}
	
		$platform_name = arlo_get_option('platform_name');
		
		if(!$platform_name) return false;
	
		if($client = $this->__get('api_client')) {
			return $client;
		}
	
		$transport = new Wordpress();
		$transport->setRequestTimeout(30);
		$transport->setUseNewUrlStructure(get_option('arlo_new_url_structure') == 1);
		
		$client = new Client($platform_name, $transport, VersionHandler::VERSION);
		
		$this->__set('api_client', $client);
		
		return $client;
	}
	
	public function cron_set_import() {
		$scheduler = $this->get_scheduler();
		$scheduler->set_task("import");
		$settings = get_option('arlo_settings');
		
		//check last import date
		$type = 'import_error';
		$last_import = $this->get_importer()->get_last_import_date();
		$last_import_ts = strtotime($last_import);
		$no_import = false;
		
		if (!empty($settings['platform_name'])) {
			if (!(!empty($last_import) && $last_import_ts !== false)) {
				$last_import = get_option('arlo_updated');
				$last_import_ts = strtotime($last_import);
				$no_import = true;
			}
			
			if (!empty($last_import) && $last_import_ts !== false) {
				$now = \Arlo\Utilities::get_now_utc();
				
				//older than 6 hours
				if (intval($now->format("U")) - $last_import_ts > 60 * 60 * 6) {
					$message_handler = $this->get_message_handler();
					
					//create an error message, if there isn't 
					if ($message_handler->get_message_by_type_count($type) == 0) {	
						
						$message = [
						'<p>'. __('Arlo for WordPress encountered problems when synchronising your event information. Information about your events may be out of date.', 'arlo-for-wordpress' ) . ' ' . (!$no_import ? sprintf(__('The last successful synchronisation was %s UTC', 'arlo-for-wordpress' ), $last_import)  : '') . '</p>',
						'<p><a href="' . get_admin_url() . 'admin.php?page=arlo-for-wordpress-logs" target="blank">'. __('View diagnostic logs', 'arlo-for-wordpress' ) . '</a> '. __('for more information.', 'arlo-for-wordpress' ) . '</p>'
						];
						
						if ($message_handler->set_message($type, __('Event synchronisation error', 'arlo-for-wordpress' ), implode('', $message), true) === false) {
							Logger::log("Couldn't create Arlo 6 hours import error message");
						}
						
						if (isset($settings['arlo_send_data']) && $settings['arlo_send_data'] == "1") {
							$this->send_log_to_arlo(strip_tags($message[0]));
						}
					}
				}			
			}	
		}

		//kick off Scheduler
		$this->cron_scheduler();
	}
	
	public function cron_scheduler() {
		session_write_close();
		try{
			$this->clean_up_tasks();
			$this->run_task_scheduler();
		}catch(\Exception $e){
			var_dump($e);
		}
	}
	
	public function clean_up_tasks() {
		$scheduler = $this->get_scheduler();
		
		$paused_running_tasks = array_merge($scheduler->get_paused_tasks(), $scheduler->get_running_tasks());
				
		foreach ($paused_running_tasks as $task) {
			$ts = strtotime($task->task_modified);
			$now = time() - date('Z');
			if ($now - $ts > 4*60) {
				$scheduler->update_task($task->task_id, 3, "Import doesn't respond within 4 minutes, stopped by the scheduler");
				$scheduler->clear_cron();
			}
		}
	}
	

	private function delete_running_tasks() {		
		$this->get_scheduler()->delete_running_tasks();
		$this->get_scheduler()->delete_paused_tasks();
	}

	public function run_task_scheduler() {	
		$this->get_scheduler()->run_task();		
	}
	
	public function load_demo() {
		$settings = get_option('arlo_settings');
		$notice_id = self::$dismissible_notices['newpages'];
		$user = wp_get_current_user();
		update_user_meta($user->ID, $notice_id, 1);
		
		if (empty($settings['platform_name'])) {
			$settings['platform_name'] = Arlo_For_Wordpress::DEFAULT_PLATFORM;
		}
		
		$error = [];
		
		foreach (self::$post_types as $id => $page) {
			//try to find and publish the page
			$args = array(
  				'name' => $id,
  				'post_type' => 'page',
  				'post_status' => array('publish','draft'),
  				'numberposts' => 1
			);
			
			$posts = get_posts($args);
			
			if (!(is_array($posts) && count($posts) == 1)) {
				$args = array(
	  				'post_type' => 'page',
	  				'post_status' => array('publish','draft'),
	  				'numberposts' => 1
				);
				
				$posts = get_posts($args);					
			}
							
			if (is_array($posts) && count($posts) == 1) {
				if ($posts[0]->post_status == 'draft') {
					wp_publish_post($posts[0]->ID);
				}
				
				$settings['post_types'][$id]['posts_page'] = $posts[0]->ID;
			} else {
				$error[] = $page['name'];
			} 
		}
		
		update_option('arlo_settings', $settings);
		
		$_SESSION['arlo-demo'] = $error;
		
		$scheduler = $this->get_scheduler();
		$scheduler->set_task("import", -1);
	}       
        	
	public function import($force = false, $task_id = 0) {
		if (get_option('arlo_import_disabled', '0') == '1') return;
		$importer = $this->get_importer();

		//track warnings during the import
		$old_track_settings = ini_set('track_errors', '1');
		if ($importer->run($force, $task_id) !== false) {
			if ($importer->is_finished) {
				// flush the rewrite rules
				flush_rewrite_rules(true);	
      			wp_cache_flush();
			}

			ini_set('track_errors', $old_track_settings);

			return true;
		}

		ini_set('track_errors', $old_track_settings);

		return false;
	}

	public static function delete_custom_posts($table, $column, $post_type) {		
		global $wpdb;

		$table = $wpdb->prefix . 'arlo_' . $table;
		$items = $wpdb->get_results("SELECT $column FROM $table", ARRAY_A);

		$post_names = array();
		foreach($items as $item) {
			$post_names[] = $item[$column];
		}

		$args = array(
			'post_type' => 'arlo_' . $post_type,
			'posts_per_page' => -1
		);

		$posts = get_posts($args);

		if(!empty($posts)) {
			foreach($posts as $post) {
				if(!in_array($post->post_name, $post_names)) {
					wp_delete_post( $post->ID, true );
				}
			}
		}
	}
	
	public function add_cron_schedules($schedules) {
		$schedules = [
			'minutes_5' => [
				'interval' => 300,
				'display' => __('Once every 5 minutes')
				],
			'minutes_15' => [
				'interval' => 900,
				'display' => __('Once every 15 minutes')
				],				
			'hourly' => [
				'interval' => 3600,
				'display' => __('Once every hour')
				],
			'minutes_30' => [
				'interval' => 1800,
				'display' => __('Every 30 minutes')
				]
			];
		return $schedules;
	}
	
	public function redirect_proxy() {
		$settings = get_option('arlo_settings');
		$import_id = $this->get_importer()->get_current_import_id();
		
		if(!isset($_GET['object_post_type']) || !isset($_GET['arlo_id'])) return;
		
		switch($_GET['object_post_type']) {
			case 'event':
				//check if it's a private event				
				if (!empty($_GET['e']) || !empty($_GET['t']) && !empty($settings['platform_name'])) {
					$platform_url = strpos($settings['platform_name'], '.') !== false ? $settings['platform_name'] : $settings['platform_name'] . '.arlo.co';

					$url = 'http://' . $platform_url . '/events/' . intval($_GET['arlo_id']) . '-fake-redirect-url?';
					$url .= (!empty($_GET['e']) ? 'e=' . $_GET['e'] : '');
					$url .= (!empty($_GET['t']) ? 't=' . $_GET['t'] : '');
					
					$location = $url;
				} else {
					$event = \Arlo\Entities\Templates::get(array('id' => intval($_GET['arlo_id'])), array(), 1, $import_id);
					
					if(!$event) return;
					
					$post = arlo_get_post_by_name($event->et_post_name, 'arlo_event');
					
					if(!$post) return;
					
					$location = get_permalink($post->ID);					
				}
			break;
			
			case 'venue':
				$venue = \Arlo\Entities\Venues::get(array('id' => intval($_GET['arlo_id'])), array(), 1, $import_id);
				
				if(!$venue) return;
				
				$post = arlo_get_post_by_name($venue->v_post_name, 'arlo_venue');
				
				if(!$post) return;
				
				$location = get_permalink($post->ID);
			break;
			
			case 'presenter':
				$presenter = \Arlo\Entities\Presenters::get(array('id' => intval($_GET['arlo_id'])), array(), 1, $import_id);
				
				if(!$presenter) return;
				
				$post = arlo_get_post_by_name($presenter->p_post_name, 'arlo_presenter');
				
				if(!$post) return;
				
				$location = get_permalink($post->ID);
			break;
			
			default:
				return;
			break;
		}
		
		wp_redirect( $location, 301 );
		exit;
	}		
	
	public function start_scheduler_callback() {		
		do_action("arlo_scheduler");
		
		wp_die();
	}
	
	public function arlo_get_last_import_log_callback() {
		global $wpdb;
		$successful = isset($_POST['successful']) ? true : false;	
		
		$log = Logger::get_log($successful, 1);
		
		if (count($log)) {
			if (strpos($log[0]['message'], "Error code 404") !== false ) {
				$log[0]['message'] = __('The provided platform name does not exist.', 'arlo-for-wordpress' );
			}
				
			$log[0]['last_import'] = $this->get_importer()->get_last_import_date();
			
			echo wp_json_encode($log[0]);
		}
		
		wp_die();
	}
	
	
	public function arlo_terminate_task_callback() {
		$task_id = intval($_POST['taskID']);
		if ($task_id > 0) {
			
			//need to terminate all the upcoming immediate tasks
			$this->get_scheduler()->terminate_all_immediate_task($task_id);
			
			$this->get_importer()->clear_import_lock();
			
			echo $task_id;
		}
		
		wp_die();
	}
	
	
	public function arlo_get_task_info_callback() {
		$task_id = intval($_POST['taskID']);
		
		if ($task_id > 0) {
			$task = $this->get_scheduler()->get_tasks(null, null, $task_id);
			
			echo wp_json_encode($task);
		}
		
		wp_die();
	}
	
	public function dismiss_message_callback() {
		$id = intval($_POST['id']);
		
		if ($id > 0) {			
			$this->get_message_handler()->dismiss_message($id);
		}		
		
		echo $id;
		wp_die();
	}	

	public function turn_off_send_data_callback() {		
		$this->change_setting('arlo_send_data', 0);

		echo 0;
		wp_die();
	}		
	
	
	public function dismissible_notice_callback() {		
		if (!empty($_POST['id'])) {
			$this->get_notice_handler()->dismiss_user_notice($_POST['id']);
		}		
		
		echo $_POST['id'];
		wp_die();
	}	

	public function change_setting($setting_name, $value) {
		$settings = get_option('arlo_settings');

		$settings[$setting_name] = $value;

		update_option('arlo_settings', $settings);
	}
	
	public function get_tag_by_id($tag_id) {
		global $wpdb;		
		
		$tag = $wpdb->get_row($wpdb->prepare(
		"SELECT 
			id, 
			tag
		FROM 
			" . $wpdb->prefix . "arlo_tags
		WHERE 
			id = %d", [$tag_id]), ARRAY_A);

		return $tag;
	}	
	
	/**
	 * determine_url_structure function.
	 *
	 * Determines if the platform is available via the new url structure
	 * 
	 * @access public
	 * @return void
	 */
	 
	public function determine_url_structure($platform_name = '') {
		$client = $this->get_api_client();
		
		$new_url = $client->transport->getRemoteURL($platform_name, true, true);

		if (extension_loaded('curl')) {
			$ch = curl_init($new_url);

			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$settings = get_option('arlo_settings');
			if (!empty($settings['disable_ssl_verification']) && $settings['disable_ssl_verification'] == 1) {
				error_log(__FUNCTION__ . 'disable ssl');
				curl_setopt($c, CURLOPT_SSL_VERIFYHOST,false);
				curl_setopt($c, CURLOPT_SSL_VERIFYPEER,false);
			}
			
			curl_exec($ch);
			
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
		}

		// update settings
		update_option('arlo_new_url_structure', $httpcode == 500 ? 1 : 0);
	}	
		

	/**
	 * add_pages function.
	 * 
	 * @access public
	 * @return associated array page_ids
	 */
	public function add_pages($page_name = '') {
		$page_ids = [];
		
		foreach($this::$pages as $page) {
			if (!empty($page_name) && $page['name'] != $page_name) continue;

			$current_page = get_page_by_title($page['title']);
		
			if(is_null($current_page)) {
				$post_id = wp_insert_post(array(
					'post_type'		=> 'page',
					'post_status'	=> 'draft',
					'post_name' 	=> $page['name'],
					'post_title'	=> $page['title'],
					'post_content' 	=> $page['content']
				));

				if (!empty($post_id)) {
					$page_ids[$page['name']] = $post_id;
				}
			}
		}
		return $page_ids;
	}
}

