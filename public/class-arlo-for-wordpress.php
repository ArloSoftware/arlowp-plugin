<?php

use \Arlo\Database;
use \Arlo\Provisioning;

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
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */

	const VERSION = '2.4.1.1';

	/**
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
		);  

	/**
	 * $message_notice_types: used to map arlo message types to WP notices 
	 *
	 * @since    2.4
	 *
	 * @var      array
	 */
    public static $message_notice_types = array(
        'inport_error' => 'error',
        'information' => 'notice-warning',
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
			'sub' => array(
				'' => 'List',
				'grid' => 'Grid'
			)
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
			'sub' => array(
				'' => 'List',
				'grid' => 'Grid'
			)
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
		),
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
		
		
		// the_post action - allows us to inject Arlo-specific data as required
		// consider this later
		//add_action( 'the_posts', array( $this, 'the_posts_action' ) );
		
		add_action( 'init', 'set_search_redirect');
		
		add_action( 'wp', 'set_region_redirect');
	}

	/**
	 * Run the scheduler action
	 *
	 * @since     2.4.1
	 *
	 * @return    null
	 */
	public static function run_scheduler() {
		session_write_close();
		do_action('arlo_scheduler');
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
		
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
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

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
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
		self::single_activate();
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
	private static function get_blog_ids() {

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
	
	public static function send_log_to_arlo($message = '') {
		$plugin = self::get_instance();
		
		$client = $plugin->get_api_client();		
		$last_import = $plugin->get_last_import();
		
		$log = self::create_log_csv(1000);
				
		$response = $client->WPLogError()->sendLog($message, $last_import, $log);
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
					self::check_plugin_version();
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
	public static function check_db_schema() { 		
		$plugin = self::get_instance();
		$plugin->get_schema_manager()->check_db_schema();
	}	
	
	/**
	 * Check the version of the plugin
	 *
	 * @since     2.4
	 *
	 * @return    null
	 */
	public static function check_plugin_version() {
 		$plugin = self::get_instance();
		$plugin_version = get_option('arlo_plugin_version');
		
		if (!empty($plugin_version)) {
            $import_id  = get_option('arlo_import_id',"");
            $last_import = $plugin->get_last_import();
            
            if (empty($import_id)) {
                if (empty($last_import)) {
                    $last_import = date("Y");
                }
                $plugin->set_import_id(date("Y", strtotime($last_import)));
            }
                        
			if ($plugin_version != $plugin::VERSION) {
				$plugin::update($plugin::VERSION, $plugin_version);
				update_option('arlo_plugin_version', $plugin::VERSION);
				
				$now = \Arlo\Utilities::get_now_utc();
				update_option('arlo_updated', $now->format("Y-m-d H:i:s"));
				self::check_db_schema();
			}
		} else {
			arlo_add_datamodel();
			update_option('arlo_plugin_version', $plugin::VERSION);
			
			$now = \Arlo\Utilities::get_now_utc();
			update_option('arlo_updated', $now->format("Y-m-d H:i:s"));			
		}
	}
	
	/**
	 * Run update scripts according to the version of the plugin
	 *
	 * @since    2.2.1
	 *
	 * @return   null
	 */
	public static function update($new_version, $old_version) {
		//pre datamodell update need to be done before
		if (version_compare($old_version, '2.4') < 0) {
			self::run_pre_data_update('2.4');
		}

		if (version_compare($old_version, '2.4.1.1') < 0) {
			self::run_pre_data_update('2.4.1.1');
		}	

		if (version_compare($old_version, '2.5') < 0) {
			self::run_pre_data_update('2.5');
		}			
		
		arlo_add_datamodel();	
	
		if (version_compare($old_version, '2.2.1') < 0) {
			self::run_update('2.2.1');
		}	
		
		if (version_compare($old_version, '2.3') < 0) {
			self::run_update('2.3');
		}

		if (version_compare($old_version, '2.3.5') < 0) {
			self::run_update('2.3.5');
		}
		
		if (version_compare($old_version, '2.4') < 0) {
			self::run_update('2.4');
		}

		if (version_compare($old_version, '2.5') < 0) {
			self::run_update('2.5');
		}		
	}
	
	private static function run_pre_data_update($version) {
		global $wpdb;	
		
		switch($version) {
			case '2.4':
				$exists = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "arlo_log'", 0, 0);
				if (is_null($exists)) {
					$wpdb->query("RENAME TABLE " . $wpdb->prefix . "arlo_import_log TO " . $wpdb->prefix . "arlo_log");
				}
				
				$exists = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "arlo_async_tasks'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks CHANGE task_modified task_modified TIMESTAMP NULL DEFAULT NULL COMMENT 'Dates are in UTC';");
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks CHANGE task_created task_created TIMESTAMP NULL DEFAULT NULL COMMENT 'Dates are in UTC';");
				}				
				
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates_presenters LIKE 'et_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters CHANGE  et_arlo_id  et_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}
				
				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events_tags LIKE 'e_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_tags CHANGE  e_arlo_id  e_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}

				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_eventtemplates_tags LIKE 'et_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags CHANGE  et_arlo_id  et_id int( 11 ) NOT NULL DEFAULT  '0'");	
				}


				$exists = $wpdb->get_var("SHOW COLUMNS FROM " . $wpdb->prefix . "arlo_events_presenters LIKE 'e_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters CHANGE  e_arlo_id  e_id int( 11 ) NOT NULL DEFAULT  '0'");
						
				}				

				$exists = $wpdb->get_var("SHOW KEYS FROM " . $wpdb->prefix . "arlo_categories WHERE key_name = 'c_arlo_id'", 0, 0);
				if (!is_null($exists)) {
					$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_categories DROP KEY c_arlo_id ");	
				}

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones DROP PRIMARY KEY, ADD PRIMARY KEY (id,active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters DROP PRIMARY KEY, ADD PRIMARY KEY (e_id,p_arlo_id,active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_tags DROP PRIMARY KEY, ADD PRIMARY KEY (e_id,tag_id,active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags DROP PRIMARY KEY, ADD PRIMARY KEY (et_id,tag_id,active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_categories DROP PRIMARY KEY, ADD PRIMARY KEY (c_id, active)");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_categories DROP PRIMARY KEY, ADD PRIMARY KEY (et_arlo_id,c_arlo_id,active)");				
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones_olson DROP PRIMARY KEY, ADD PRIMARY KEY (timezone_id,olson_name,active)");				
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters DROP PRIMARY KEY, ADD PRIMARY KEY (et_id,p_arlo_id,active)");
															
			break;

			case '2.4.1.1':
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_categories CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_contentfields CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_categories CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_offers CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_onlineactivities CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_onlineactivities_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_presenters CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");				
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_tags CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones_olson CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_venues CHANGE active import_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
			break;

			case '2.5':
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_tasks 
				CHANGE task_task task_task VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE task_status_text task_status_text VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_async_task_data 
				CHANGE data_text data_text TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_eventtemplates 
				CHANGE et_code et_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE et_post_name et_post_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE et_advertised_duration et_advertised_duration VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE et_region et_region VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE et_descriptionsummary et_descriptionsummary TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");
				
				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_contentfields 
				CHANGE cf_fieldname cf_fieldname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_contenttype e_contenttype VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE cf_text cf_text TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_events 
				CHANGE e_code e_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_name e_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_datetimeoffset e_datetimeoffset VARCHAR(6) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_timezone e_timezone VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_locationname e_locationname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_locationroomname e_locationroomname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_summary e_summary VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_sessiondescription e_sessiondescription VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_credits e_credits VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_viewuri e_viewuri VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_registermessage e_registermessage VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_registeruri e_registeruri VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_providerorganisation e_providerorganisation VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_providerwebsite e_providerwebsite VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_region e_region VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE e_notice e_notice TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_onlineactivities 
				CHANGE oa_arlo_id oa_arlo_id VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_code oa_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_name oa_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_delivery_description oa_delivery_description VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_viewuri oa_viewuri VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_reference_terms oa_reference_terms VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_credits oa_credits VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_registermessage oa_registermessage VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_registeruri oa_registeruri VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE oa_region oa_region VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_venues 
				CHANGE v_name v_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdressline1 v_physicaladdressline1 VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdressline2 v_physicaladdressline2 VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdressline3 v_physicaladdressline3 VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdressline4 v_physicaladdressline4 VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdresssuburb v_physicaladdresssuburb VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdresscity v_physicaladdresscity VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdressstate v_physicaladdressstate VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdresspostcode v_physicaladdresspostcode VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_physicaladdresscountry v_physicaladdresscountry VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_viewuri v_viewuri VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_post_name v_post_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE v_facilityinfodirections v_facilityinfodirections TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
				CHANGE v_facilityinfoparking v_facilityinfoparking TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_presenters 
				CHANGE p_firstname p_firstname VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE p_lastname p_lastname VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE p_viewuri p_viewuri VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE p_twitterid p_twitterid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE p_facebookid p_facebookid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE p_linkedinid p_linkedinid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE p_post_name p_post_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE p_profile p_profile TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
				CHANGE p_qualifications p_qualifications TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
				CHANGE p_interests p_interests TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");			

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_categories 
				CHANGE c_name c_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
				CHANGE c_slug c_slug VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
				CHANGE c_header c_header TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
				CHANGE c_footer c_footer TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_offers
				CHANGE o_label o_label VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE o_currencycode o_currencycode VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE o_formattedamounttaxexclusive o_formattedamounttaxexclusive VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE o_formattedamounttaxinclusive o_formattedamounttaxinclusive VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE o_taxrateshortcode o_taxrateshortcode VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE o_taxratename o_taxratename VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE o_region o_region VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE o_message o_message TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_tags 
				CHANGE tag tag VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");	

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_categories 
				CHANGE c_name c_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
				CHANGE c_slug c_slug VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");		

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones 
				CHANGE name name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_timezones_olson 
				CHANGE olson_name olson_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_log 
				CHANGE message message TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");

				$wpdb->query("ALTER TABLE " . $wpdb->prefix . "arlo_messages 
				CHANGE title title VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
				CHANGE message message TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");	
			break;					
		}
	}	
	
	private static function run_update($version) {
		switch($version) {
			case '2.2.1': 
				//Add [arlo_no_event_text] shortcode to the templates
				$update_templates = ['eventsearch', 'events'];
				$saved_templates = arlo_get_option('templates');
				
				foreach ($update_templates as $id) {
					if (!empty($saved_templates[$id]['html'])) {
						$content = $saved_templates[$id]['html'];
						
						if (strpos($content, "arlo_no_event_text") === false) {
							$shortcode = "\n[arlo_no_event_text]\n";
							$append_after = "[arlo_category_footer]";						
						
							//try to find the [arlo_category_footer], and append before
							$pos = strpos($content, $append_after);
							if ($pos !== false) {
								$pos += strlen($append_after);
							} else {
								$pos = strlen($content);
							}
							
							$saved_templates[$id]['html'] = substr_replace($content, $shortcode, $pos, 0);
						}
					}
				}
				
				arlo_set_option('templates', $saved_templates);
			break;
			case '2.3': 
				$saved_templates = arlo_get_option('templates');

				//Add [arlo_template_region_selector] shortcode to the event template
				if (!empty($saved_templates['event']['html']) && strpos($saved_templates['event']['html'], "[arlo_template_region_selector]") === false) {
					$saved_templates['event']['html'] = "[arlo_template_region_selector]\n" . $saved_templates['event']['html'];
				}
				
				//Add [arlo_template_region_selector] shortcode to the catalogue template
				if (!empty($saved_templates['events']['html']) && strpos($saved_templates['event']['html'], "[arlo_template_region_selector]") === false) {
					$saved_templates['events']['html'] = "[arlo_template_region_selector]\n" . $saved_templates['events']['html'];
				}
								
				//Add [arlo_template_search_region_selector] shortcode to the event search template
				if (!empty($saved_templates['eventsearch']['html']) && strpos($saved_templates['event']['html'], "[arlo_template_search_region_selector]") === false) {
					$saved_templates['eventsearch']['html'] = "[arlo_template_search_region_selector]\n" . $saved_templates['eventsearch']['html'];
				}				

				//Add [arlo_upcoming_region_selector] shortcode to the upcoming events list template
				if (!empty($saved_templates['upcoming']['html']) && strpos($saved_templates['event']['html'], "[arlo_upcoming_region_selector]") === false) {
					$saved_templates['upcoming']['html'] = "[arlo_upcoming_region_selector]\n" . $saved_templates['upcoming']['html'];
				}
				
				arlo_set_option('templates', $saved_templates);
			break;
			
			case '2.3.5':
				wp_clear_scheduled_hook( 'arlo_import' );
				
				if ( ! wp_next_scheduled('arlo_scheduler')) {
					wp_schedule_event( time(), 'minutes_5', 'arlo_scheduler' );
				}

			break;
			
			case '2.4': 
				
				//Add [event_template_register_interest] shortcode to the event template
				$update_templates = ['event'];
				$saved_templates = arlo_get_option('templates');
				
				foreach ($update_templates as $id) {
					if (!empty($saved_templates[$id]['html'])) {
						$content = $saved_templates[$id]['html'];
						
						if (strpos($content, "[arlo_event_template_register_interest]") === false) {
							$shortcode = "\n[arlo_event_template_register_interest]\n";
							$append_before = [
								"[arlo_suggest_datelocation",
								"[arlo_content_field_item",
								"<h3>Similar courses",
							];
							foreach ($append_before as $target) {
								//try to find the given shortcode, and append before
								$pos = strpos($content, $target);
								if ($pos !== false) {
									break;
								}
							}
							
							if ($pos === false) {
								$pos = strlen($content);
							}
							
							$saved_templates[$id]['html'] = substr_replace($content, $shortcode, $pos, 0);
						}
					}
				}
				
				wp_clear_scheduled_hook( 'arlo_scheduler' );
				wp_schedule_event( time(), 'minutes_5', 'arlo_scheduler' );
				
				arlo_set_option('templates', $saved_templates);

				$plugin = self::get_instance();
				$message_handler = $plugin->get_message_handler();

				$plugin::change_setting('arlo_send_data', 1);

				if ($message_handler->get_message_by_type_count('information') == 0) {
					
					$message = [
					'<p>' . __('Arlo for WordPress will automatically send technical data to Arlo if problems are encountered when synchronising your event information. The data is sent securely and will help our team when providing support for this plugin. You can turn this off anytime in the', 'arlo-for-wordpress' ) . ' <a href="?page=arlo-for-wordpress#misc" class="arlo-settings-link" id="settings_misc">' . __('setting', 'arlo-for-wordpress' ) . '</a>.</p>',
					'<p><a target="_blank" class="button button-primary" id="arlo_turn_off_send_data">' . __('Turn off', 'arlo-for-wordpress' ) . '</a></p>'
					];
					
					$message_handler->set_message('information', __('Send error data to Arlo', 'arlo-for-wordpress' ), implode('', $message), false);
				}

				if ( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ) {
					$message = [
						'<p>' . __('Arlo for WordPress requires that the Cron feature in WordPress is enabled, or replaced with an external trigger.', 'arlo-for-wordpress' ) .' ' . sprintf(__('<a target="_blank" href="%s">View documentation</a> for more information.', 'arlo-for-wordpress' ), 'http://developer.arlo.co/doc/wordpress/import#import-wordpress-cron') . '</p>',
						'<p>' . __('You may safely dismiss this warning if your system administrator has installed an external Cron solution.', 'arlo-for-wordpress' ) . '</p>'
					];
			
					$message_handler->set_message('error', __('WordPress Cron is disabled', 'arlo-for-wordpress' ), implode('', $message), false);
				}
				
			break;	
		}	
	}
	

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		//check plugin version and forca data modell update
		self::check_plugin_version();
		arlo_add_datamodel();

		// flush permalinks upon plugin deactivation
		flush_rewrite_rules();

		// must happen before adding pages
		self::set_default_options();
		
		// run import every 15 minutes
		\Arlo\Logger::log("Plugin activated");

		// now add pages
		self::add_pages();
		
		update_option('arlo_plugin_version', self::VERSION);
	}

	/**
	 * Set the default values for arlo wp_options table option
	 *
	 * @since    1.0.0
	 *
	 */
	private static function set_default_options() {
		$settings = get_option('arlo_settings');
		
		if (is_array($settings) && count($settings)) {
			//add new templates			
			foreach(self::$templates as $id => $template) {
				if (empty($settings['templates'][$id]['html'])) {
					$settings['templates'][$id] = array(
						'html' => arlo_get_blueprint($id)
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
			
			foreach(self::$templates as $id => $template) {
				$default_settings['templates'][$id] = array(
					'html' => arlo_get_blueprint($id)
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
	private static function single_deactivate() {
		// flush permalinks upon plugin deactivation
		flush_rewrite_rules();
		
		wp_clear_scheduled_hook( 'arlo_scheduler' );
		wp_clear_scheduled_hook( 'arlo_set_import' );
		wp_clear_scheduled_hook( 'arlo_import' );
		
		self::delete_running_tasks();
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
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css?20161031', __FILE__ ), array(), self::VERSION );
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles-darktooltip', plugins_url( 'assets/css/libs/darktooltip.min.css', __FILE__ ), array(), self::VERSION );
		
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
		
		$filter_enabled_arlo_pages = ['upcoming', 'event'];
				
		foreach($filter_enabled_arlo_pages as $page) {
			if (!empty($settings['post_types'][$page]['posts_page'])) {
				$filter_enabled_page_ids[] = intval($settings['post_types'][$page]['posts_page']);
			}			
		}
				
		if (in_array($page_id, $filter_enabled_page_ids)) {
			$url = get_home_url() . '/' .$obj->post_name;

			//has to be the same order as in public.js to construct the same order
			if (!empty($_GET['arlo-category'])) {
				$url .= '/cat-' . urlencode($_GET['arlo-category']);
			}
			
			if (!empty($_GET['arlo-month'])) {
				$url .= '/month-' . urlencode($_GET['arlo-month']);
			}
			
			if (!empty($_GET['arlo-location'])) {
				$url .= '/location-' . urlencode($_GET['arlo-location']);
			}

			if (isset($_GET['arlo-delivery']) && is_numeric($_GET['arlo-delivery'])) {
				$url .= '/delivery-' . urlencode($_GET['arlo-delivery']);
			}

			if (!empty($_GET['arlo-eventtag'])) {
				if (is_numeric($_GET['arlo-eventtag'])) {
					$tag = self::get_tag_by_id($_GET['arlo-eventtag']);
					if (!empty($tag['tag'])) {
						$_GET['arlo-eventtag'] = $tag['tag'];
					}
				}
				$url .= '/eventtag-' . urlencode($_GET['arlo-eventtag']);
			}
			
			if (!empty($_GET['arlo-templatetag'])) {
				if (is_numeric($_GET['arlo-templatetag'])) {
					$tag = self::get_tag_by_id($_GET['arlo-templatetag']);
					if (!empty($tag['tag'])) {
						$_GET['arlo-templatetag'] = $tag['tag'];
					}					
				}
			
				$url .= '/templatetag-' . urlencode($_GET['arlo-templatetag']);
			}
			
			echo '<link rel="canonical" href="' . $url . '/" />';
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
				echo "\n<style>\n" . $settings['customcss'] . "\n</style>\n";
			}
		}
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js?20161031', __FILE__ ), array( 'jquery' ), self::VERSION );
		wp_enqueue_script( $this->plugin_slug . '-plugin-script-darktooltip', plugins_url( 'assets/js/libs/jquery.darktooltip.min.js', __FILE__ ), array( 'jquery' ), self::VERSION );
		wp_enqueue_script( $this->plugin_slug . '-plugin-script-cookie', plugins_url( 'assets/js/libs/jquery.cookie.js', __FILE__ ), array( 'jquery' ), self::VERSION );
		wp_localize_script( $this->plugin_slug . '-plugin-script', 'objectL10n', array(
			'showmoredates' => __( 'Show me more dates', 'arlo-for-wordpress' ),
		) );
		wp_localize_script( $this->plugin_slug . '-plugin-script', 'WPUrls', array(
			'home_url' => get_home_url(),
		) );
		
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

	/* API & import functionality */
    
	public function set_import_id($import_id) {
		update_option('arlo_import_id', $import_id);
                               
		$this->import_id = $import_id;
	}    
        
	public function get_import_id() {	
        global $wpdb;
        
        //need to access the db directly, get_option('arlo_import_id'); can return a cached (old) value
        $table_name = "{$wpdb->prefix}options";
        
        $sql = "SELECT option_value
			FROM $table_name 
            WHERE option_name = 'arlo_import_id'";
	    
	    $import_id = $wpdb->get_var($sql);
            
		$this->import_id = $import_id;
                
		return $this->import_id;
	}            
	
	public function get_log($limit=1) {
		global $wpdb;
		
		$table_name = "{$wpdb->prefix}arlo_log";
		
		$items = $wpdb->get_results(
			"SELECT log.* 
			FROM $table_name log 
			ORDER BY log.id DESC
			LIMIT $limit"
		);
		
		return $items;
	}
	
	public function get_last_successful_import_log() {
		global $wpdb;
		
		$table_name = "{$wpdb->prefix}arlo_log";
		
		$item = $wpdb->get_row(
			"SELECT log.* 
			FROM $table_name log 
			WHERE log.successful = 1 
			ORDER BY log.created DESC
			LIMIT 1"
		);
		
		if($item) return $item;
		
		return false;
	}
	
	// should only be used when successful
	public function set_last_import() {
		$now = \Arlo\Utilities::get_now_utc();
       	$timestamp = $now->format("Y-m-d H:i:s");	
	
		update_option('arlo_last_import', $timestamp);
		$this->last_imported = $timestamp;
	}
	
	public function get_last_import() {
		if(!is_null($this->last_imported)) {
			return $this->last_imported;
		}
		
		$this->last_imported = get_option('arlo_last_import');
		
		return $this->last_imported;
	}
	
	public function get_scheduler() {
		if($scheduler = $this->__get('scheduler')) {
			return $scheduler;
		}
		
		$scheduler = new \Arlo\Scheduler($this);
		
		$this->__set('scheduler', $scheduler);
		
		return $scheduler;
	}

	public function get_importer() {
		if($importer = $this->__get('importer')) {
			return $importer;
		}
		
		$importer = new \Arlo\Importer\Importer($this);
		
		$this->__set('importer', $importer);
		
		return $importer;
	}
	
	public function get_message_handler() {
		if($message_handler = $this->__get('message_handler')) {
			return $message_handler;
		}
		
		$message_handler = new \Arlo\MessageHandler();
		
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

	public function get_schema_manager() {
		if($schema_manager = $this->__get('schema_manager')) {
			return $schema_manager;
		}
		
		$schema_manager = SchemaManager($this->get_dbl(), $this->get_message_handler());
		
		$this->__set('schema_manager', $schema_manager);
		
		return $schema_manager;
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
	
		$transport = new \ArloAPI\Transports\Wordpress();
		$transport->setRequestTimeout(30);
		$transport->setUseNewUrlStructure(get_option('arlo_new_url_structure') == 1);
		
		$client = new \ArloAPI\Client($platform_name, $transport, self::VERSION);
		
		$this->__set('api_client', $client);
		
		return $client;
	}
	
	public function cron_set_import() {
		$scheduler = $this->get_scheduler();
		$scheduler->set_task("import");
		$settings = get_option('arlo_settings');
		
		//check last import date
		$type = 'import_error';
		$last_import = $this->get_last_import();
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
							\Arlo\Logger::log("Couldn't create Arlo 6 hours import error message");
						}
						
						if (isset($settings['arlo_send_data']) && $settings['arlo_send_data'] == "1") {
							self::send_log_to_arlo(strip_tags($message[0]));
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
			if ($now - $ts > 10*60) {
				$scheduler->update_task($task->task_id, 3, "Import doesn't respond within 10 minutes, stopped by the scheduler");
				$scheduler->clear_cron();
			}
		}
	}
	
	private static function delete_running_tasks() {
		$plugin = self::get_instance();
		$scheduler = $plugin->get_scheduler();
		
		$scheduler->delete_running_tasks();
		$scheduler->delete_paused_tasks();
	}

	public function run_task_scheduler() {
		$scheduler = $this->get_scheduler();
		
		$scheduler->run_task();		
	}
	
	public function create_log_csv($limit = 1000) {
		global $wpdb;
		$limit = intval($limit);
		
        $table_name = "{$wpdb->prefix}arlo_import_lock";
        
		$fp = fopen('php://temp/maxmemory:2097125', 'w');
		if($fp === FALSE) {
			\Arlo\Logger::log('Couldn\'t create log CSV', $import_id);
		    return false;
		}
        
        $sql = '
            SELECT 
                id,
                message,
                created, 
                successful
            FROM
                ' . $wpdb->prefix . 'arlo_log
            ORDER BY
            	id DESC
            ' . 
            ($limit !== 0 ? 'LIMIT ' . $limit : '');
            
	               
        $entries = $wpdb->get_results($sql, 'ARRAY_N');
	
		if (is_array($entries) && count($entries)) {
			fputcsv($fp, array('ID', 'Log', 'CreatedDateTime', 'Successful'));
			
			foreach ($entries as $entry) {
				fputcsv($fp, $entry);
	        } 
		}
		
		rewind($fp);
		$csv = stream_get_contents($fp);
		fclose($fp);		
		
		return $csv;
	
	}
	
	public function download_synclog() {        
		$csv = self::create_log_csv(0);
	
        header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=arlo_sync_log.csv');
		
		echo $csv;
		exit;
	}
	
	public function load_demo() {
		$settings = get_option('arlo_settings');
		$notice_id = self::$dismissible_notices['newpages'];
		$user = wp_get_current_user();
		update_user_meta($user->ID, $notice_id, 1);
		
		if (empty($settings['platform_name'])) {
			$settings['platform_name'] = 'websitetestdata';
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
	  				'name' => $page['name'],
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
        
    public function GUIDv4 ($trim = true) {
        // Windows
        if (function_exists('com_create_guid') === true) {
            if ($trim === true)
                return trim(com_create_guid(), '{}');
            else
                return com_create_guid();
        }

        // OSX/Linux
        if (function_exists('openssl_random_pseudo_bytes') === true) {
            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
    }
    
    public function get_random_int() {
        $guid = explode("-", $this->GUIDv4());
        
        return hexdec($guid[0]);
    }
    
    public function clear_import_lock() {
        global $wpdb;
        $table_name = "{$wpdb->prefix}arlo_import_lock";
      
        $query = $wpdb->query(
            'DELETE FROM  
                ' . $table_name
        );
    }     
    
    public function get_import_lock_entries_number() {
        global $wpdb;
        $table_name = "{$wpdb->prefix}arlo_import_lock";
        
        $sql = '
            SELECT 
                lock_acquired
            FROM
                ' . $table_name . '
            WHERE
                lock_expired > NOW()
            ';
	               
        $wpdb->get_results($sql);
        
        return $wpdb->num_rows;
    }
    
    public function cleanup_import_lock() {
        global $wpdb;
        $table_name = "{$wpdb->prefix}arlo_import_lock";
      
        $wpdb->query(
            'DELETE FROM  
                ' . $table_name . '
            WHERE 
                lock_expired < NOW()
            '
        );
    }
    
    public function add_import_lock($import_id) {
        global $wpdb;
        
        $table_lock = "{$wpdb->prefix}arlo_import_lock";
        $table_log = "{$wpdb->prefix}arlo_log";
        
        $query = $wpdb->query(
                'INSERT INTO ' . $table_lock . ' (import_id, lock_acquired, lock_expired)
                SELECT ' . $import_id . ', NOW(), ADDTIME(NOW(), "00:05:00.00") FROM ' . $table_log . ' WHERE (SELECT count(1) FROM ' . $table_lock . ') = 0 LIMIT 1');
                    
        return $query !== false && $query == 1;
    }
    
    public function acquire_import_lock($import_id) {
    	$lock_entries_num = $this->get_import_lock_entries_number();
        if ($lock_entries_num == 0) {
            $this->cleanup_import_lock();
            if ($this->add_import_lock($import_id)) {
                return true;
            }
        } else if ($lock_entries_num == 1) {
        	return $this->check_import_lock($import_id);
        }
        
        return false;
    }
    
    public function check_import_lock($import_id) {
        global $wpdb;
    	$table_name = "{$wpdb->prefix}arlo_import_lock";
        
        $sql = '
            SELECT 
                lock_acquired
            FROM
                ' . $table_name . '
            WHERE
                import_id = ' . $import_id . '
            AND    
                lock_expired > NOW()';
               
        $wpdb->get_results($sql);
        
        if ($wpdb->num_rows == 1) {
            return true;
        }
    
        return false;
    }
        
        	
	public function import($force = false, $task_id = 0) {
		global $wpdb;
		$task_id = intval($task_id);
		$scheduler = $this->get_scheduler();
		$importer = $this->get_importer();

		if ($task_id > 0) {
			$task = $scheduler->get_task_data($task_id);
			if (count($task)) {
				$task = $task[0];
			}
						
			if (empty($task->task_data_text)) {
				// check for last sucessful import. Continue if imported mor than an hour ago or forced. Otherwise, return.
				$last = $this->get_last_import();
		        $import_id = $this->get_random_int();
		                        
		        \Arlo\Logger::log('Synchronization Started', $import_id);
		        
		        $scheduler->update_task_data($task_id, ['import_id' => $import_id]);
								
				// MV: Untangled the if statements. 
				// If not forced
				if(!$force) {
					// LOG THIS AS AN AUTOMATIC IMPORT
					\Arlo\Logger::log('Synchronization identified as automatic synchronization.', $import_id);
					if(!empty($last)) {
						// LOG THAT A PREVIOUS SUCCESSFUL IMPORT HAS BEEN FOUND
						\Arlo\Logger::log('Previous succesful synchronization found.', $import_id);
						if(strtotime('-1 hour') > strtotime($last)) {
							// LOG THE FACT THAT PREVIOUS SUCCESSFUL IMPORT IS MORE THAN AN HOUR AGO
							\Arlo\Logger::log('Synchronization more than an hour old. Synchronization required.', $import_id);
						}
						else {
							// LOG THE FACT THAT PREVIOUS SUCCESSFUL IMPORT IS MORE THAN AN HOUR AGO
							\Arlo\Logger::log('Synchronization less than an hour old. Synchronization stopped.', $import_id);
							// LOG DATA USED TO DECIDE IMPORT NOT REQUIRED.
							\Arlo\Logger::log($last . '-'  . strtotime($last) . '-' . strtotime('-1 hour') . '-'  . !$force, $import_id);
							return false;
						}
					}
				} 				
			} else {
				$task->task_data_text = json_decode($task->task_data_text);
				if (empty($task->task_data_text->import_id)) {
					return false;
				} else {
					$import_id = $task->task_data_text->import_id;
				}				
			}
		}

		// excessive, but some servers are slow...
		ini_set('max_execution_time', 3000);
		set_time_limit(3000);		
				
        //if an import is already running, exit
        if (!$this->acquire_import_lock($import_id)) {
            \Arlo\Logger::log('Synchronization LOCK found, please wait 5 minutes and try again', $import_id);
            return false;
        }
                
		try {			
			$importer->set_import_id($import_id);

			$importer->set_state($task->task_data_text);

			if (!$importer::$is_finished) {
				$scheduler->update_task($task_id, 2, "Import is running: task " . ($importer->current_task_num + 1) . "/" . count($importer->import_tasks) . ": " . $importer->current_task_desc);
				$importer->run();

				$scheduler->update_task_data($task_id, $importer->get_state());
				
				$scheduler->update_task($task_id, 1);
				$scheduler->unlock_process('import');
			}
								
			if ($importer::$is_finished) {
				//finish task
				$scheduler->update_task($task_id, 4, "Import finished");
				$scheduler->clear_cron();
			} else {
				$url  = add_query_arg( $this->get_query_args(), $this->get_query_url() );
				$args = $this->get_post_args();
				
				wp_remote_post( esc_url_raw( $url ), $args );
			}
		} catch(\Exception $e) {
			\Arlo\Logger::log('Synchronization failed, please check the <a href="?page=arlo-for-wordpress-logs&s='.$import_id.'">Log</a> ', $import_id);

			$scheduler->update_task($task_id, 3);
			
			$this->clear_import_lock();
			
			return false;
		}
					
		// flush the rewrite rules
		flush_rewrite_rules(true);	
      	wp_cache_flush();
        
        $this->clear_import_lock();    
		
		return true;
	}

	protected function get_query_args() {
		if ( property_exists( $this, 'query_args' ) ) {
			return $this->query_args;
		}

		return array(
			'action' => 'arlo_run_scheduler',
			//'nonce'  => wp_create_nonce( $this->identifier ),
		);
	}

	protected function get_query_url() {
		if ( property_exists( $this, 'query_url' ) ) {
			return $this->query_url;
		}

		return admin_url( 'admin-ajax.php' );
	}

	protected function get_post_args() {
		if ( property_exists( $this, 'post_args' ) ) {
			return $this->post_args;
		}

		return array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'cookies'   => $_COOKIE,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);
	}

	public function delete_custom_posts($table, $column, $post_type) {
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
	
	public static function redirect_proxy() {
		$settings = get_option('arlo_settings');
		$import_id = self::get_instance()->get_import_id();
		
		if(!isset($_GET['object_post_type']) || !isset($_GET['arlo_id'])) return;
		
		switch($_GET['object_post_type']) {
			case 'event':
				//check if it's a private event				
				if (!empty($_GET['e']) || !empty($_GET['t']) && !empty($settings['platform_name'])) {
					$url = 'http://' . $settings['platform_name'] . '.arlo.co/events/' . intval($_GET['arlo_id']) . '-fake-redirect-url?';
					$url .= (!empty($_GET['e']) ? 'e=' . $_GET['e'] : '');
					$url .= (!empty($_GET['t']) ? 't=' . $_GET['t'] : '');
					
					$location = $url;
				} else {
					$event = \Arlo\Templates::get(array('id' => $_GET['arlo_id']), array(), 1, $import_id);
					
					if(!$event) return;
					
					$post = arlo_get_post_by_name($event->et_post_name, 'arlo_event');
					
					if(!$post) return;
					
					$location = get_permalink($post->ID);					
				}
			break;
			
			case 'venue':
				$venue = \Arlo\Venues::get(array('id' => $_GET['arlo_id']), array(), 1, $import_id);
				
				if(!$venue) return;
				
				$post = arlo_get_post_by_name($venue->v_post_name, 'arlo_venue');
				
				if(!$post) return;
				
				$location = get_permalink($post->ID);
			break;
			
			case 'presenter':
				$presenter = \Arlo\Presenters::get(array('id' => $_GET['arlo_id']), array(), 1, $import_id);
				
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
		
	public static function load_demo_notice($error = []) {
		global $wpdb;
		$settings = get_option('arlo_settings');
		$import_id = self::get_instance()->get_import_id();
		
		$events = arlo_get_post_by_name('events', 'page');
		$upcoming = arlo_get_post_by_name('upcoming', 'page');
		$presenters = arlo_get_post_by_name('presenters', 'page');
		$venues = arlo_get_post_by_name('venues', 'page');
		
		$notice_id = self::$dismissible_notices['newpages'];
		$user = wp_get_current_user();
		$meta = get_user_meta($user->ID, $notice_id, true);
				
		if (count($error)) {
			echo '
				<div class="' . (count($error) ? "error" : "") . ' notice is-dismissible" id="' . $notice_id . '">
		        	<p>' . sprintf(__('Couldn\'t set the following post types: %s', 'arlo-for-wordpress' ), implode(', ', $error)) . '</p>
		        	<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
			 	</div>';
		} else {
			if ($meta !== '0') {			
				if (!empty($settings['platform_name']) && $events !== false && $upcoming !== false && $presenters !== false && $venues !== false) {		
					//Get the first event template wich has event
					$sql = "
					SELECT 
						ID
					FROM
						{$wpdb->prefix}arlo_events AS e
					LEFT JOIN 		
						{$wpdb->prefix}arlo_eventtemplates AS et		
					ON
						e.et_arlo_id = et.et_arlo_id
					AND
						e.import_id = " . $import_id ."
					LEFT JOIN
						{$wpdb->prefix}posts
					ON
						et_post_name = post_name		
					AND
						post_status = 'publish'
					WHERE 
						et.import_id = " . $import_id ."
					LIMIT 
						1
					";

					$event = $wpdb->get_results($sql, ARRAY_A);
					$event_link = '';
					if (count($event)) {
						$event_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						get_post_permalink($event[0]['ID']),
						__('Event', 'arlo-for-wordpress' ));
					}					
					
					//Get the first presenter
					$sql = "
					SELECT 
						ID
					FROM
						{$wpdb->prefix}arlo_presenters AS p
					LEFT JOIN
						{$wpdb->prefix}posts
					ON
						p_post_name = post_name		
					AND
						post_status = 'publish'
					WHERE 
						p.import_id = " . $import_id ."
					LIMIT 
						1
					";
					$presenter = $wpdb->get_results($sql, ARRAY_A);		
					$presenter_link = '';
					if (count($event)) {
						$presenter_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						get_post_permalink($presenter[0]['ID']),
						__('Presenter profile', 'arlo-for-wordpress' ));
					}					
					
					//Get the first venue
					$sql = "
					SELECT 
						ID
					FROM
						{$wpdb->prefix}arlo_venues AS v
					LEFT JOIN
						{$wpdb->prefix}posts
					ON
						v_post_name = post_name		
					AND
						post_status = 'publish'
					WHERE 
						v.import_id = " . $import_id ."
					LIMIT 
						1
					";
					$venue = $wpdb->get_results($sql, ARRAY_A);							
					$venue_link = '';
					if (count($event)) {
						$venue_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						get_post_permalink($venue[0]['ID']),
						__('Venue information', 'arlo-for-wordpress' ));
					}					
					
					
					$message = '<h3>' . __('Start editing your new pages', 'arlo-for-wordpress' ) . '</h3><p>'.
											
					sprintf(__('View %s <a href="%s" target="_blank">%s</a>, <a href="%s" target="_blank">%s</a>, %s <a href="%s" target="_blank">%s</a> %s or <a href="%s" target="_blank">%s</a> pages', 'arlo-for-wordpress' ), 
						$event_link,
						$events->guid, 
						__('Catalogue', 'arlo-for-wordpress' ), 
						$upcoming->guid,  
						$upcoming->post_title,
						$presenter_link,
						$presenters->guid, 
						__('Presenters list', 'arlo-for-wordpress' ), 						
						$venue_link,
						$venues->guid,  
						__('Venues list', 'arlo-for-wordpress' )
					) . '</p><p>' . __('Edit the page <a href="#pages" class="arlo-pages-setup">templates</a> for each of these websites pages below.') . '</p>';
					
					echo '
					<div class="' . (count($error) ? "error" : "") . ' notice is-dismissible" id="' . $notice_id . '">
			        	' . $message . '
			        	<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
				 	</div>
				 	';
					
					unset($_SESSION['arlo-demo']);		
				}				
			}		
		}
	}	
	
	public static function welcome_notice() {
		$settings = get_option('arlo_settings');
		$notice_id = self::$dismissible_notices['welcome'];
		$user = wp_get_current_user();
		$meta = get_user_meta($user->ID, $notice_id, true);
		
		if ($meta !== '0') {
			echo '
			<div class="notice is-dismissible" id="' . $notice_id . '">
				<h3>' . __('Welcome to Arlo for WordPress', 'arlo-for-wordpress' ) . '</h3>
				<table class="arlo-welcome">
					<tr>
						<td class="logo" valign="top">
							<a href="http://www.arlo.co" target="_blank"><img src="' . plugins_url( '/assets/img/icon-128x128.png', __FILE__) . '" style="width: 65px"></a>
						</td>
						<td>
							<p>' . __( 'Create beautiful and interactive training and event websites using the Arlo for WordPress plugin. Access an extensive library of WordPress Shortcodes, Templates, and Widgets, all designed specifically for web developers to make integration easy.', 'arlo-for-wordpress' ) . '</p>
							<p>' . __('<a href="https://developer.arlo.co/doc/wordpress/index" target="_blank">Learn how to use</a> Arlo for WordPress or visit <a href="http://www.arlo.co" target="_blank">www.arlo.co</a> to find out more about Arlo.', 'arlo-for-wordpress' ) . '</p>
							<p>' . (empty($settings['platform_name']) ? '<a href="?page=arlo-for-wordpress&load-demo" class="button button-primary">' . __('Try with demo data', 'arlo-for-wordpress' ) . '</a> &nbsp; &nbsp; ' : '') .'<a href="http://www.arlo.co/register" target="_blank"  class="button button-primary">' . __('Get started with free trial', 'arlo-for-wordpress' ) . '</a></p>
						</td>
					</tr>
				</table>
				<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		    </div>
			';		
		}
		self::wp_video_notice();
		self::load_demo_notice(!empty($_SESSION['arlo-demo']) ? $_SESSION['arlo-demo'] : []);
		self::webinar_notice();
		self::developer_notice();
		
		
		unset($_SESSION['arlo-import']);
	}	
	
	public static function developer_notice() {
		$notice_id = self::$dismissible_notices['developer'];
		$user = wp_get_current_user();
		$meta = get_user_meta($user->ID, $notice_id, true);

		if ($meta !== '0') {
			echo '
			<div class="notice is-dismissible" id="' . $notice_id . '">
				<p class="developer">
					
					<img src="' . plugins_url( '/assets/img/tips-yellow.png', __FILE__) . '" style="width: 32px">
					' . __('Are you a web developer building a site for a client?', 'arlo-for-wordpress' ) . '
					' . sprintf(__('<a target="_blank" href="%s">Contact us to become an Arlo partner</a>', 'arlo-for-wordpress' ), 'https://www.arlo.co/contact') . '
				</p>
				<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		    </div>
			';	
		}
	}

	public static function wp_video_notice() {
		$notice_id = self::$dismissible_notices['wp_video'];
		$user = wp_get_current_user();
		$meta = get_user_meta($user->ID, $notice_id, true);

		if ($meta !== '0') {
			echo '
			<div class="notice is-dismissible" id="' . $notice_id . '">
				<p class="developer">
					<img src="' . plugins_url( '/assets/img/video-yellow.png', __FILE__) . '" style="width: 32px">
					' . sprintf(__('<a target="_blank" href="%s">Watch overview video</a>', 'arlo-for-wordpress' ), 'https://www.arlo.co/videos#-uUhu90cvoc') . '
					' . __('to see Arlo for WordPress in action.', 'arlo-for-wordpress' ) . '
				</p>
		    </div>
			';	
		}
	}	
	
	public static function webinar_notice() {
		$notice_id = self::$dismissible_notices['webinar'];
		$user = wp_get_current_user();
		$meta = get_user_meta($user->ID, $notice_id, true);

		if ($meta !== '0') {	
			echo '
			<div class="notice is-dismissible" id="' . $notice_id . '" style="display: none">
				<p class="webinar">
					<a target="_blank" href="https://www.arlo.co/video/wordpress-overview" target="_blank"><img src="' . plugins_url( '/assets/img/video-yellow.png', __FILE__) . '" style="width: 32px">' . __('Watch overview video', 'arlo-for-wordpress' ) .'</a>
					<img src="' . plugins_url( '/assets/img/training-yellow.png', __FILE__) . '" style="width: 32px">
					' . __('Join <a target="_blank" href="" class="webinar_url">Arlo for WordPress Getting started</a> webinar on <span id="webinar_date"></span>', 'arlo-for-wordpress' ) . '
					' . __('<a target="_blank" href="" class="webinar_url">Register now!</a> or <a target="_blank" href="" id="webinar_template_url">view more times</a>', 'arlo-for-wordpress' ) . '
				</p>
				<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		    </div>
			';	
		}
	}	
	
	public static function permalink_notice() {
		echo '
		<div class="error notice">
			<p><strong>' . __("Permalink setting change required.", 'arlo-for-wordpress' ) . '</strong> ' . sprintf(__('Arlo for WordPress requires <a target="_blank" href="%s">Permalinks</a> to be set to "Post name".', 'arlo-for-wordpress' ), admin_url('options-permalink.php')) . '</p>
	    </div>
		';		
	}		
	
	public static function posttype_notice() {
		echo '
		<div class="error notice">
			<p><strong>' . __("Page setup required.", 'arlo-for-wordpress' ) . '</strong> ' . __('Arlo for WordPress requires you to setup the pages which will host event information.', 'arlo-for-wordpress' ) .' '. sprintf(__('<a href="%s" class="arlo-pages-setup">Setup pages</a>', 'arlo-for-wordpress' ), admin_url('admin.php?page=arlo-for-wordpress#pages/events')) . '</p>
			<p>' . sprintf(__('<a target="_blank" href="%s">View documentation</a> for more information.', 'arlo-for-wordpress' ), 'http://developer.arlo.co/doc/wordpress/index#pages-and-post-types') . '</p>
	    </div>
		';
	}
	
	public static function global_notices() {
		$plugin = self::get_instance();
		$message_handler = $plugin->get_message_handler();
		$messages = $message_handler->get_messages('import_error', true);
		
		foreach ($messages as $message) {
			echo self::create_notice($message);
		}
	}

	public static function arlo_notices() {
		$plugin = self::get_instance();
		$message_handler = $plugin->get_message_handler();
		$messages = $message_handler->get_messages(null, false);
		
		foreach ($messages as $message) {
			echo self::create_notice($message);
		}
	}	

	
	public static function create_notice($message) {
		$notice_type = (isset(self::$message_notice_types[$message->type]) ? self::$message_notice_types[$message->type] : 'error');

		$global_message = '';
		if ($message->global) {
			$global_message = '<td class="logo" valign="top" style="width: 60px; padding-top: 1em;">
						<a href="http://www.arlo.co" target="_blank"><img src="' . plugins_url( '/assets/img/icon-128x128.png', __FILE__) . '" style="width: 48px"></a>
					</td>';
		}

		return '
		<div class="' . $notice_type . '  notice arlo-message is-dismissible arlo-' . $message->type .  '" id="arlo-message-' . $message->id . '">
			<table>
				<tr>
					' . $global_message . '
					<td>
						<p><strong>' . __( $message->title, 'arlo-for-wordpress' ) . '</strong></p>
						' . __( $message->message, 'arlo-for-wordpress' ) . '
					</td>
				</tr>
			</table>
	    </div>
		';
	}	
	
	
	public static function connected_platform_notice() {
		$settings = get_option('arlo_settings');
		echo '
			<div class="notice arlo-connected-message"> 
				<p>
					Arlo is connected to <strong>' . $settings['platform_name'] . '</strong> <span class="arlo-block">Last synchronized: <span class="arlo-last-sync-date">' . self::get_instance()->get_last_import() . ' UTC</span></span> 
					<a class="arlo-block arlo-sync-button" href="?page=arlo-for-wordpress&arlo-import">Synchronize now</a>
				</p>
			</div>
		';
		
		if (strtolower($settings['platform_name']) === "websitetestdata") {
			echo '
				<div class="notice updated"> 
					<p>
						<strong>Connected to demo data</strong>  Your site is currently using demo event, presenter, and venue data. Start an Arlo trial to load your own events!
					</p>
					<p>
						<a class="button button-primary" href="https://www.arlo.co/register">Get started with free Arlo trial</a>&nbsp;&nbsp;&nbsp;&nbsp;
						<a class="button button-primary arlo-block" href="#general" id="arlo-connet-platform">Connect existing Arlo platform</a>
					</p>
				</div>
			';
			
		}
	}		
	
	
	public static function start_scheduler_callback() {		
		do_action("arlo_scheduler");
		
		wp_die();
	}
	
	public static function arlo_get_last_import_log_callback() {
		global $wpdb;
		$sucessful = isset($_POST['sucessful']) ? true : false;
		
		$plugin = Arlo_For_Wordpress::get_instance();
		
		
		$sql = "
		SELECT
			message,
			created,
			successful
		FROM
			{$wpdb->prefix}arlo_log
			". ($sucessful ? "WHERE successful = 1" : "") ."
		ORDER BY
			id DESC
		LIMIT 
			1
		";
		
		$log = $wpdb->get_results($sql, ARRAY_A);
		
		if (count($log)) {
			if (strpos($log[0]['message'], "Error code 404") !== false ) {
				$log[0]['message'] = __('The provided platform name does not exist.', 'arlo-for-wordpress' );
			}
				
			$log[0]['last_import'] = $plugin->get_last_import();
			
			echo wp_json_encode($log[0]);
		}
		
		wp_die();
	}
	
	
	public static function arlo_terminate_task_callback() {
		$task_id = intval($_POST['taskID']);
		if ($task_id > 0) {
			$plugin = Arlo_For_Wordpress::get_instance();
			$scheduler = $plugin->get_scheduler();
			
			//need to terminate all the upcoming immediate tasks
			$scheduler->terminate_all_immediate_task($task_id);
			
			$plugin->clear_import_lock();
			
			echo $task_id;
		}
		
		wp_die();
	}
	
	
	public static function arlo_get_task_info_callback() {
		$task_id = intval($_POST['taskID']);
		
		if ($task_id > 0) {
			$plugin = Arlo_For_Wordpress::get_instance();
			$scheduler = $plugin->get_scheduler();
			
			$task = $scheduler->get_tasks(null, null, $task_id);
			
			echo wp_json_encode($task);
		}
		
		wp_die();
	}
	
	public static function dismiss_message_callback() {
		$id = intval($_POST['id']);
		
		if ($id > 0) {
			$plugin = Arlo_For_Wordpress::get_instance();
			$message_handler = $plugin->get_message_handler();		
			
			$message_handler->dismiss_message($id);
		}		
		
		echo $id;
		wp_die();
	}	

	public static function turn_off_send_data_callback() {		
		self::change_setting('arlo_send_data', 0);

		echo 0;
		wp_die();
	}		
	
	
	public static function dismissible_notice_callback() {		
		$user = wp_get_current_user();
		
		if (in_array($_POST['id'], self::$dismissible_notices)) {
			update_user_meta($user->ID, $_POST['id'], 0);
		}
		
		echo $_POST['id'];
		wp_die();
	}	

	public static function change_setting($setting_name, $value) {
		$settings = get_option('arlo_settings');

		$settings[$setting_name] = $value;

		update_option('arlo_settings', $settings);
	}
	
	public static function get_tag_by_id($tag_id) {
		global $wpdb;		
		
		$tag = $wpdb->get_row(
		"SELECT 
			id, 
			tag
		FROM 
			" . $wpdb->prefix . "arlo_tags
		WHERE 
			id = " . intval($tag_id), ARRAY_A);
			

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
	public static function determine_url_structure($platform_name = '') {
		$plugin = self::get_instance();
		$client = $plugin->get_api_client();
		
		$new_url = $client->transport->getRemoteURL($platform_name, true, true);
		
		$ch = curl_init($new_url);

		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 

		// update settings
		update_option('arlo_new_url_structure', $httpcode == 500 ? 1 : 0);
	}	
		

	/**
	 * add_pages function.
	 * 
	 * @access public
	 * @return void
	 */
	private static function add_pages() {
		
		$settings = get_option('arlo_settings');
	
		foreach(self::$pages as $page) {
			$current_page = get_page_by_title($page['title']);
		
			if(is_null($current_page)) {
				$post_id = wp_insert_post(array(
					'post_type'		=> 'page',
					'post_status'	=> 'draft',
					'post_name' 	=> $page['name'],
					'post_title'	=> $page['title'],
					'post_content' 	=> $page['content']
				));
				
				/*
				if(isset($page['child_post_type'])) {
					foreach(self::$post_types as $id => $type) {
						if($page['child_post_type'] == $id) {
							$settings['post_types'][$id]['posts_page'] = $post_id;
						}
					}
				}
				*/
			}
		}
	
		// update settings
		update_option('arlo_settings', $settings);
	}
}

