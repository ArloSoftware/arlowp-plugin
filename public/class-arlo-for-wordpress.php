<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use ArloTraining\Provisioning\SchemaManager;
use ArloTraining\Logger;
use ArloTraining\VersionHandler;
use ArloTraining\Scheduler;
use ArloTraining\Importer\Importer;
use ArloTraining\Importer\ImportingParts;
use ArloTraining\Importer\ImportRequest;
use ArloTraining\MessageHandler;
use ArloTraining\NoticeHandler;
use ArloTraining\API\Transports\Wordpress;
use ArloTraining\API\Client;
use ArloTraining\Utilities;
use ArloTraining\CacheControl;
use ArloTraining\Environment;
use ArloTraining\ThemeManager;
use ArloTraining\TimeZoneManager;
use ArloTraining\SystemRequirements;
use ArloTraining\Redirect;

/**
 * Arlo for WordPress.
 * Text Domain: arlo-for-wordpress
 *
 * @package   Arlo_For_Wordpress
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      https://arlo.co
 * @copyright 2018 Arlo
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
 * @author  Adam Fentosi <adam.fentosi@arlo.co>, Gabriel Oheix
 */
class Arlo_For_Wordpress {

	/**
	 * Init static member with translation
	 */
	public static function init() {
        self::$post_types = array(
			'upcoming' => array(
				'slug' => 'upcomingevents',
				'name' => esc_html__('Upcoming events', 'arlo-training-and-event-management-system'),
				'singular_name' => esc_html__('Upcoming event list', 'arlo-training-and-event-management-system'),
				'regionalized' => true
			),
			'event' => array(
				'slug' => 'event',
				'name' => esc_html__('Events', 'arlo-training-and-event-management-system'),
				'singular_name' => esc_html__('Catalogue', 'arlo-training-and-event-management-system'),
				'regionalized' => true
			),
			'venue' => array(
				'slug' => 'venue',
				'name' => esc_html__('Venues', 'arlo-training-and-event-management-system'),
				'singular_name' => esc_html__('Venue list', 'arlo-training-and-event-management-system')
			),		
			'presenter' => array(
				'slug' => 'presenter',
				'name' => esc_html__('Presenters', 'arlo-training-and-event-management-system'),
				'singular_name' => esc_html__('Presenter list', 'arlo-training-and-event-management-system')
			),
			'eventsearch' => array(
				'slug' => 'eventsearch',
				'name' => esc_html__('Event search', 'arlo-training-and-event-management-system'),
				'singular_name' => esc_html__('Event search', 'arlo-training-and-event-management-system'),
				'regionalized' => true
			),
			'schedule' => array(
				'slug' => 'schedule',
				'name' => esc_html__('Schedule', 'arlo-training-and-event-management-system'),
				'singular_name' => esc_html__('Schedule', 'arlo-training-and-event-management-system'),
				'regionalized' => true
			),
			'oa' => array(
				'slug' => 'onlineactivities',
				'name' => esc_html__('Online activities', 'arlo-training-and-event-management-system'),
				'singular_name' => esc_html__('Online activity', 'arlo-training-and-event-management-system'),
				'regionalized' => true
			)
		);

		self::get_templates();

		self::$filter_labels = array(
			'category' => esc_html__('All categories', 'arlo-training-and-event-management-system'),
			'delivery' => esc_html__('All delivery options', 'arlo-training-and-event-management-system'),
			'month' => esc_html__('All months', 'arlo-training-and-event-management-system'),
			'location' => esc_html__('All locations', 'arlo-training-and-event-management-system'),
			'state' => esc_html__('Select state', 'arlo-training-and-event-management-system'),
			'eventtag' => esc_html__('Select tag', 'arlo-training-and-event-management-system'),
			'templatetag' => esc_html__('Select tag', 'arlo-training-and-event-management-system'),
			'presenter' => esc_html__('All presenters', 'arlo-training-and-event-management-system'),
			'oatag' => esc_html__('All tags', 'arlo-training-and-event-management-system')
		);

		self::$filter_labels_v1 = array(
			'category' => esc_html__('Categories', 'arlo-training-and-event-management-system'),
			'delivery' => esc_html__('Delivery Methods', 'arlo-training-and-event-management-system'),
			'month' => esc_html__('Months', 'arlo-training-and-event-management-system'),
			'location' => esc_html__('Locations', 'arlo-training-and-event-management-system'),
			'region' => esc_html__('Regions', 'arlo-training-and-event-management-system'),
			'state' => esc_html__('States', 'arlo-training-and-event-management-system'),
			'eventtag' => esc_html__('Event Tags', 'arlo-training-and-event-management-system'),
			'templatetag' => esc_html__('Template Tags', 'arlo-training-and-event-management-system'),
			'presenter' => esc_html__('Presenters', 'arlo-training-and-event-management-system'),
			'oatag' => esc_html__('Tags', 'arlo-training-and-event-management-system')
		);
    }

	/**
	 * Minimum required PHP version
	 *
	 * @since   2.0.6
	 *
	 * @var     string
	 */
	const MIN_PHP_VERSION = '7.4.0';

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
	 * Deployment mode constants.
	 *
	 * @since   5.1.0
	 */
	const DEPLOYMENT_MODE_PRODUCTION     = 'production';
	const DEPLOYMENT_MODE_NON_PRODUCTION = 'non_production';

	/**
	 * Minimum age for showing the sync error notice after a confirmed failure.
	 *
	 * @since   5.1.0
	 */
	const SYNC_ERROR_NOTICE_MIN_AGE = 6 * HOUR_IN_SECONDS;

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
	public static $post_types = array();
    
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
				'content'			=> '[arlo_event_template_list]',
				'child_post_type'	=> 'event',
				'post_types_key'	=> 'event',
			),
			array(
				'name'				=> 'eventsearch',
				'title'				=> 'Event search',
				'content'			=> '[arlo_event_template_search_list]',
				'child_post_type'	=> 'event',
				'post_types_key'	=> 'eventsearch',
			),
			array(
				'name'				=> 'upcoming',
				'title'				=> 'Upcoming Events',
				'content'			=> '[arlo_upcoming_list]',
				'post_types_key'	=> 'upcoming',
			),
			array(
				'name'				=> 'presenters',
				'title'				=> 'Presenters',
				'content'			=> '[arlo_presenter_list]',
				'child_post_type'	=> 'presenter',
				'post_types_key'	=> 'presenter',
			),
			array(
				'name'				=> 'venues',
				'title'				=> 'Venues',
				'content'			=> '[arlo_venue_list]',
				'child_post_type'	=> 'venue',
				'post_types_key'	=> 'venue',
			),
			array(
				'name'				=> 'oa',
				'title'				=> 'Online Activities',
				'content'			=> '[arlo_onlineactivites_list]',
				'child_post_type'	=> 'event',
				'post_types_key'	=> 'oa',
			),
			array(
				'name'				=> 'schedule',
				'title'				=> 'Schedule',
				'content'			=> '[arlo_schedule]',
				'child_post_type'	=> 'event',
				'post_types_key'	=> 'schedule',
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
    	'newpages' => 'arlo-newpages-admin-notice',
		'wp_video' => 'arlo-wp-video',
		'pagesetup' => 'arlo-page-setup-admin-notice',
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
        99 => 'Online Activity'
    );
    
	/**
	 * $templates: defines the available templates for the plugin
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
	public static $templates = array();

	/**
	 * Lazy accessor for $templates. Ensures the array is populated on first
	 * access regardless of WordPress hook execution order — safe to call
	 * during register_activation_hook (which fires before 'init').
	 *
	 * @since    5.1.0
	 *
	 * @return   array
	 */
	public static function get_templates() {
		if (empty(self::$templates)) {
			self::$templates = array(
				'event' => array(
					'id' => 'event',
					'name' => esc_html__('Event', 'arlo-training-and-event-management-system' ),
				),
				'events' => array(
					'id' => 'events',
					'shortcode' => '[arlo_event_template_list]',
					'name' => esc_html__('Catalogue', 'arlo-training-and-event-management-system' )
				),
				'schedule' => array(
					'id' => 'schedule',
					'shortcode' => '[arlo_schedule]',
					'name' => esc_html__('Schedule', 'arlo-training-and-event-management-system' )
				),
				'eventsearch' => array(
					'id' => 'eventsearch',
					'shortcode' => '[arlo_event_template_search_list]',
					'name' => esc_html__('Event search list', 'arlo-training-and-event-management-system' )
				),
				'upcoming' => array(
					'id' => 'upcoming',
					'shortcode' => '[arlo_upcoming_list]',
					'name' => esc_html__('Upcoming event list', 'arlo-training-and-event-management-system' ),
				),
				'oa' => array(
					'id' => 'oa',
					'shortcode' => '[arlo_onlineactivites_list]',
					'name' => esc_html__('Online activity list', 'arlo-training-and-event-management-system' )
				),
				'presenter' => array(
					'id' => 'presenter',
					'name' => esc_html__('Presenter', 'arlo-training-and-event-management-system' )
				),
				'presenters' => array(
					'id' => 'presenters',
					'shortcode' => '[arlo_presenter_list]',
					'name' => esc_html__('Presenter list', 'arlo-training-and-event-management-system' )
				),
				'venue' => array(
					'id' => 'venue',
					'name' => esc_html__('Venue', 'arlo-training-and-event-management-system' )
				),
				'venues' => array(
					'id' => 'venues',
					'shortcode' => '[arlo_venue_list]',
					'name' => esc_html__('Venue list', 'arlo-training-and-event-management-system' )
				),
				'new_custom' => array(
					'id' => 'new_custom',
					'name' => esc_html__('New', 'arlo-training-and-event-management-system' )
				)
			);
		}

		return self::$templates;
	}


	/**
	 * $shortcoes_types: defines the available types of global shortcodes for the plugin
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
    public static $shortcode_types = array(
		'events' => 'Catalogue', 
		'schedule' => 'Schedule', 
		'upcoming' => 'Upcoming', 
		'oa' => 'Online Activities', 
		'presenters' => 'Presenters', 
		'venues' => 'Venues'
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
				'location' => 'Location',
				'state' => 'State'
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
				'presenter' => 'Presenter',
				'state' => 'State'
			)
		),
		'oa' => array(
			'name' => 'Online activities',
			'filters' => array(
				//'oatag' => 'Online activity tag',  //OA cannot be tagged currently
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
				'templatetag' => 'Tag',
				'state' => 'State'
			)
		),
		'schedule' => array(
			'name' => 'Schedule',
			'filters' => array(
				'category' => 'Category', 
				'delivery' => 'Delivery', 
				'location' => 'Location', 
				'templatetag' => 'Tag',
				'state' => 'State'
			)
		)
	);

	/**
	 * $available_page_filters: defines the available filters for the plugin
	 *
	 * @since    3.6.0
	 *
	 * @var      array
	 */

	public static  $available_page_filters = array(
		'upcoming' => array(
			'name' => 'Upcoming',
			'filters' => array(
				'category' => 'Category', 
				'location' => 'Location', 
				'delivery' => 'Delivery', 
				'eventtag' => 'Event tag', 
				'templatetag' => 'Template tag', 
			)
		),
		'oa' => array(
			'name' => 'Online activities',
			'filters' => array(
				'category' => 'Category',
				'templatetag' => 'Template tag',
			)
		),
		'template' => array(
			'name' => 'Catalogue',
			'filters' => array(
				'category' => 'Category', 
				'delivery' => 'Delivery', 
				'location' => 'Location', 
				'templatetag' => 'Template tag',
			)
		),
		'schedule' => array(
			'name' => 'Schedule',
			'filters' => array(
				'category' => 'Category', 
				'delivery' => 'Delivery', 
				'location' => 'Location', 
				'templatetag' => 'Template tag',
			)
		)
	);	

	/**
	 * $page_filter_options: behaviour selector for page filters
	 *
	 * @since    3.6.0
	 *
	 * @var      array
	 */

    public static $page_filter_options = array(
		'showonly' => 'Hide all except',
        'exclude' => 'Show all except', //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- 'exclude' here is not for a sql query.
    );	


	/**
	 * $filter_labels: maps filter name to their labels
	 *
	 * @since    3.5.0
	 *
	 * @var      array
	 */

    public static $filter_labels = array();
	public static $filter_labels_v1 = array();


	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {
		// check for a proxy redirect request
		add_action( 'wp', array( $this, 'redirect_proxy' ) );


		self::arlo_add_custom_shortcodes();

		// Register custom post types
		add_action( 'init', 'arlo_register_custom_post_types');

		// Add review notice
		add_action( 'init', array( $this, 'arlo_add_review_message' ) );

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
		global $wp_version;
		if ( version_compare( '4.6', $wp_version ) === 1 ) {
			add_action( 'wp_head', array( $this, 'add_canonical_urls' ) );
		}
		add_filter( 'get_canonical_url', array( $this, 'wp_canonical_fix' ), 10, 2 );
		add_filter( 'wpseo_canonical', array( $this, 'yoast_canonical_fix' ) );
		
		//add meta description
		add_action( 'wp_head', array( $this, 'add_meta_description' ) );
		
		// GP: Check if the scheduled task is entered. If it does not exist set it. (This ensures it is in as long as the plugin is activated.)
		if ( ! wp_next_scheduled('arlo_set_import')) {
			wp_schedule_event( time(), 'minutes_30', 'arlo_set_import' );
		}
		

		// content and excerpt filters to hijack arlo registered post types
		add_filter('the_content', 'arlo_the_content');
	
	
		add_action( 'wp_ajax_arlo_dismissible_notice', array($this, 'dismissible_notice_callback'));

		add_action( 'wp_ajax_arlo_increment_review_notice_date', array($this, 'increment_review_notice_date'));

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

		add_action( 'wp_ajax_arlo_check_page_shortcode', array($this, 'check_page_shortcode_callback') );
		add_action( 'save_post_page', array($this, 'invalidate_shortcode_check_cache') );
		add_action( 'transition_post_status', array($this, 'invalidate_shortcode_check_cache_on_status_change'), 10, 3 );
		add_action( 'before_delete_post', array($this, 'invalidate_shortcode_check_cache_on_delete'), 10, 2 );

		// the_post action - allows us to inject Arlo-specific data as required
		// consider this later
		//add_action( 'the_posts', array( $this, 'the_posts_action' ) );
		
		add_action( 'init', 'arlo_set_search_redirect');
		
		add_action( 'wp', 'arlo_set_region_redirect');

		\ArloTraining\Shortcodes\Shortcodes::init(); 
	}

	/**
	 * Import callback
	 *
	 * @since     2.5
	 *
	 * @return    null
	 */	

	public function import_callback() {
		// Treat the callback as a forced manual import so the soft gates (user_import_enabled
		// toggle, connection health auto-disable) do not silently drop it. If a request was
		// sent to the Arlo platform (which requires passing the gate at request time), the
		// resulting callback must be allowed to complete; swallowing it would orphan the task.
		// The hard system-requirements gate (arlo_import_disabled) still applies.
		if (!self::is_import_enabled(true)) return;
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
		// get_sites only exsit in multisite enviroment.
		if (function_exists('get_sites')) {
			$sites = get_sites(array(
				'archived'  => 0,
				'spam'      => 0,
				'deleted'   => 0,
				'fields'    => 'ids',
			));
			return array_map('strval', $sites);
		} else {
			return array();
		}
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
		
		try {
			$log = Logger::build_log_csv_string(1000);
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
			if (!empty($data['plugins'])){
				foreach($data['plugins'] as $each_plugin){
					if (basename($each_plugin) == 'arlo-for-wordpress.php'){
						Arlo_For_Wordpress::check_plugin_version();
					}
				}
			} else if (empty($data['plugin']) && basename($each_plugin) == 'arlo-for-wordpress.php'){
				Arlo_For_Wordpress::check_plugin_version();
			}
		}
	}


	/**
	 * Ensures the plugin's database schema is consistent with the current version.
	 * Drops and recreates all plugin tables if the schema hash does not match, then
	 * posts an admin notice and kicks off a fresh import.
	 *
	 * @since     5.1.0
	 *
	 * @return    void
	 */
	public function ensure_consistent_db_schema() {
		$this->get_schema_manager()->ensure_consistent_db_schema();
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
				update_option( 'arlo_import_disabled', '1' );
			} else {
				update_option( 'arlo_import_disabled', '0' );
				update_option( 'arlo_plugin_disabled', '0' );
			}			
            
            if (empty($import_id)) {
                if (empty($last_import)) {
                    $last_import = gmdate("Y");
                }
                $plugin->get_importer()->set_import_id(gmdate("Y", strtotime($last_import)));
            }

			if ($plugin_version != VersionHandler::VERSION) {
				$plugin->get_version_handler()->run_update($plugin_version);
				
				$plugin->get_schema_manager()->ensure_consistent_db_schema();
			}
		} else {
			arlo_add_datamodel();

			$plugin->get_version_handler()->set_installed_version();

			//check system requirements and disable the plugin/import
			if (!SystemRequirements::overall_check()) {
				update_option( 'arlo_plugin_disabled', '1' );
				update_option( 'arlo_import_disabled', '1' );
			} 
		}

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

		// set date to show review notice
		self::set_review_notice_date("+7 day");

		// now add pages
		$add_result = self::add_pages();
		self::assign_posts_page_defaults( $add_result['pages'] );

		update_option('arlo_plugin_version', VersionHandler::VERSION);

		if (!SystemRequirements::overall_check()) {
			update_option( 'arlo_plugin_disabled', '1' );
			update_option( 'arlo_import_disabled', '1' );
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
	 * Set the date to show the review notice
	 *
	 * @since    3.6.0
	 *
	 */	
	public function set_review_notice_date($increment) {
		$date = \DateTime::createFromFormat('U', strtotime($increment, time()))->format("Y-m-d H:i:s");
		update_option('arlo_review_notice_date',$date);
	}


	/**
	 * Increment the date to show the review notice
	 *
	 * @since    3.6.0
	 *
	 */	
	public function increment_review_notice_date() {
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if(empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		self::set_review_notice_date("+1 month");
		$this->get_message_handler()->delete_messages('review');
	}


	/**
	 * Add a review message
	 *
	 * @since    3.6.0
	 *
	 */	
	public function arlo_add_review_message() {
		$review_notice_date = get_option('arlo_review_notice_date');

		if (time() >= strtotime($review_notice_date) && $this->get_message_handler()->get_message_by_type_count('review', true) == 0) {
			$message = "<p>
				It's great to see that you've been using the <strong>Arlo for WordPress</strong> plugin for a while now. Hopefully you're happy with it! If so, would you consider leaving a positive review? It really helps to support the plugin and helps others to discover it too!
			</p>
			<p>
				<a href='https://wordpress.org/support/plugin/arlo-training-and-event-management-system/reviews/' target='_blank' class='arlo-review-option'>Sure, I'd love to!</a> &#8226; <a href='#' target='_blank' class='arlo-review-option notice-dismiss-custom'>No thanks</a> &#8226; <a href='https://support.arlo.co/hc/en-gb/requests/new' target='_blank' class='arlo-review-option'>I actually need some help</a> &#8226; <a href='#' target='_blank' class='arlo-review-option notice-ask-later'>Ask me later</a>
			</p>
			";

			$this->get_message_handler()->set_message('review', '', $message, true);
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
			foreach(self::get_templates() as $id => $template) {
				if (empty($settings['templates'][$id]['html'])) {
					$settings['templates'][$id] = array(
						'html' => arlo_get_template($id)
					);
				}
			}

			// Backfill deployment_mode on activation for installs that pre-date 5.1.0.
			// Auto-updates are covered by the do_update('5.1.0') migration in VersionHandler.
			if (!isset($settings['deployment_mode'])) {
				$settings['deployment_mode'] = self::get_deployment_mode_default($settings['platform_name'] ?? '');
			}

			// Backfill post_types when missing or corrupt — can happen when arlo_settings was
			// written by a partial or legacy install that pre-dates this key, or when a
			// re-activation finds a non-empty option that never had post_types written into it.
			if (!isset($settings['post_types']) || !is_array($settings['post_types'])) {
				$settings['post_types'] = self::$post_types;
			}
			
			update_option('arlo_settings', $settings);
			
		} else {
			$default_settings = array(
				'platform_name' => '',
				'post_types'    => self::$post_types,
				'templates'     => array()
			);
			
			foreach(self::get_templates() as $id => $template) {
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
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_slug . '-plugin-styles-tingle', plugins_url( '../public/custom-assets/tingle/tingle.css', __FILE__), [], VersionHandler::VERSION );

		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css?20170424', __FILE__ ), [], VersionHandler::VERSION );

		wp_enqueue_style( $this->plugin_slug . '-plugin-styles-bootstrap-modals', plugins_url( 'assets/css/libs/bootstrap-modals.css?20170424', __FILE__ ), [], VersionHandler::VERSION );

		wp_enqueue_style( $this->plugin_slug . '-plugin-styles-darktooltip', plugins_url( 'assets/css/libs/darktooltip.min.css', __FILE__ ), [], VersionHandler::VERSION );

		wp_enqueue_style( $this->plugin_slug .'-arlo-icons8', plugins_url( '../admin/assets/fonts/icons8/Arlo-WP.css', __FILE__ ), [], VersionHandler::VERSION );

		// Enqueue theme assets from the current plugin path to avoid stale stored URLs.
		$theme_settings = $this->get_theme_settings_for_assets();
		
		if (!empty($theme_settings)) {
			//internal resources
			if (isset($theme_settings->internalResources->stylesheets) && is_array($theme_settings->internalResources->stylesheets)) {
				foreach ($theme_settings->internalResources->stylesheets as $key => $stylesheet) {
					wp_enqueue_style( $this->plugin_slug . '-theme-internal-stylesheet-' . $key, $stylesheet, [], VersionHandler::VERSION );
				}
			} 

			//external resources
			if (isset($theme_settings->externalResources->stylesheets) && is_array($theme_settings->externalResources->stylesheets)) {
				foreach ($theme_settings->externalResources->stylesheets as $key => $stylesheet) {
					wp_enqueue_style( $this->plugin_slug . '-theme-external-stylesheet-' . $key, plugins_url( $stylesheet, __FILE__), [], VersionHandler::VERSION );
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
	 * @deprecated 4.1 Replaced by get_canonical_url filter
	 * @since    2.2.0
	 */
	public function add_canonical_urls() {
		$page_id = get_query_var('page_id', '');
		$obj = get_queried_object();
		
		$page_id = (empty($obj->ID) ? $page_id : $obj->ID);	
		
		$filter_enabled_page_ids = [];
		$post_types_settings = self::get_post_types_settings();
						
		foreach($this::$available_filters as $page => $filters) {
			if (!empty($post_types_settings[$page]['posts_page'])) {
				$filter_enabled_page_ids[] = intval($post_types_settings[$page]['posts_page']);
			}			
		}
				
		if (in_array($page_id, $filter_enabled_page_ids)) {
			$url = get_home_url() . '/' .$obj->post_name;
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL construction, no state change.
			//has to be the same order as in public.js to construct the same order
			if (!empty($_GET['arlo-category'])) {
				$url .= '/cat-' . sanitize_text_field(wp_unslash($_GET['arlo-category']));
			}
			
			if (!empty($_GET['arlo-month'])) {
				$url .= '/month-' . sanitize_text_field(wp_unslash($_GET['arlo-month']));
			}
			
			if (!empty($_GET['arlo-location'])) {
				$url .= '/location-' . sanitize_text_field(wp_unslash($_GET['arlo-location']));
			}

			if (isset($_GET['arlo-delivery']) && is_numeric($_GET['arlo-delivery'])) {
				$url .= '/delivery-' . intval($_GET['arlo-delivery']);
			}

			if (!empty($_GET['arlo-presenter'])) {
				$url .= '/presenter-' . sanitize_text_field(wp_unslash($_GET['arlo-presenter']));
			}

			if (!empty($_GET['arlo-eventtag'])) {
				if (is_numeric($_GET['arlo-eventtag'])) {
					$tag = self::get_tag_by_id(sanitize_key($_GET['arlo-eventtag']));
					if (!empty($tag['tag'])) {
						$_GET['arlo-eventtag'] = $tag['tag'];
					}
				}
				$url .= '/eventtag-' . sanitize_text_field(wp_unslash($_GET['arlo-eventtag']));
			}
			
			if (!empty($_GET['arlo-oatag'])) {
				if (is_numeric($_GET['arlo-oatag'])) {
					$tag = self::get_tag_by_id(sanitize_key($_GET['arlo-oatag']));
					if (!empty($tag['tag'])) {
						$_GET['arlo-oatag'] = $tag['tag'];
					}
				}
				$url .= '/oatag-' . sanitize_text_field(wp_unslash($_GET['arlo-oatag']));
			}

			if (!empty($_GET['arlo-templatetag'])) {
				if (is_numeric($_GET['arlo-templatetag'])) {
					$tag = self::get_tag_by_id(sanitize_key($_GET['arlo-templatetag']));
					if (!empty($tag['tag'])) {
						$_GET['arlo-templatetag'] = $tag['tag'];
					}					
				}
			
				$url .= '/templatetag-' . sanitize_text_field(wp_unslash($_GET['arlo-templatetag']));
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
			$desc = wp_strip_all_tags($obj->post_content);
		} else {
			$category = $this->fetch_category($page_id);
			if (!empty($category) && !empty($category->c_header)) {
				$desc = wp_strip_all_tags($category->c_header);
			}
		}

		if (!empty($desc)) {
			$ellipsis = '';
			if (strlen($desc) >= 150) {
				$end_pos = strpos($desc, " ", 140);
				$ellipsis = '...';
			} else {
				$end_pos = strlen($desc);
			}
			$desc = substr($desc, 0, $end_pos) . $ellipsis;
			
			echo '<meta name="description" content="' . esc_attr(htmlspecialchars($desc, ENT_COMPAT, 'UTF-8')) . '">';
		}
	}

	private function fetch_category($page_id) {
		$filter_settings = get_option('arlo_page_filter_settings', []);

		$page = get_post($page_id);
		if (!$page) return;
		
		$template_name = $page->post_name;

		//too early to call get_selected_categories()
		$stored_atts = [];
		\ArloTraining\Utilities::set_base_filter($template_name, 'category', $filter_settings, [], $stored_atts, '\ArloTraining\Utilities::convert_string_to_int_array');
		\ArloTraining\Utilities::set_base_filter($template_name, 'category', $filter_settings, [], $stored_atts, '\ArloTraining\Utilities::convert_string_to_int_array', null, true);
		$category_slug_or_array = \ArloTraining\Utilities::get_att_string('category', $stored_atts);

		$category_id = 0;
		if (is_array($category_slug_or_array)) {
			if (isset($category_slug_or_array[0])) {
				$category_id = $category_slug_or_array[0];
			}
		} else {
			$category_id = intval($category_slug_or_array);
		}

		if (empty($category_id)) {
			return '';
		}

		$import_id = $this->get_importer()->get_current_import_id();
		return \ArloTraining\Entities\Categories::get([ 'id' => intval($category_id)], 1, $import_id);
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
				echo "\n<style type=\"text/css\">\n" , wp_strip_all_tags($settings['customcss']) , "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Custom CSS output. Content is sanitized using wp_strip_all_tags().
			}
		}
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		
		wp_enqueue_script( $this->plugin_slug . '-plugin-script-tingle', plugins_url( '../public/custom-assets/tingle/tingle.min.js' , __FILE__), [], VersionHandler::VERSION, false );
		
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js?20170424', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, false );
		
		wp_enqueue_script( $this->plugin_slug . '-plugin-script-darktooltip', plugins_url( 'assets/js/libs/jquery.darktooltip.min.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, false );
		
		wp_enqueue_script( $this->plugin_slug . '-plugin-script-cookie', plugins_url( 'assets/js/libs/js.cookie.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, false );
		
		wp_localize_script( $this->plugin_slug . '-plugin-script', 'objectL10n', array(
			'showmoredates' => esc_html__( 'Show me more dates', 'arlo-training-and-event-management-system' ),
		) );
		// Enqueue theme assets from the current plugin path to avoid stale stored URLs.
		$theme_settings = $this->get_theme_settings_for_assets();

		if (!empty($theme_settings)) {
			//internal resources
			if (isset($theme_settings->internalResources->javascripts) && is_array($theme_settings->internalResources->javascripts)) {
				foreach ($theme_settings->internalResources->javascripts as $key => $script) {
					wp_enqueue_script( $this->plugin_slug . '-theme-internal-script-' . $key, $script, array( 'jquery' ), VersionHandler::VERSION, false );
				}
			} 

			//external resources
			if (isset($theme_settings->externalResources->javascripts) && is_array($theme_settings->externalResources->javascripts)) {
				foreach ($theme_settings->externalResources->javascripts as $key => $script) {
					wp_enqueue_script( $this->plugin_slug . '-theme-external-script-' . $key, plugins_url( $script , __FILE__), [], VersionHandler::VERSION, true );
				}
			} 			
		}
	}

	private function get_theme_settings_for_assets() {
		$theme_id = get_option('arlo_theme');
		if ( ! is_string( $theme_id ) || '' === $theme_id || '.' === $theme_id || '..' === $theme_id || false !== strpos( $theme_id, '/' ) || false !== strpos( $theme_id, '\\' ) ) {
			return null;
		}

		$theme_manager = $this->get_theme_manager();

		// Load only the active theme instead of scanning all theme directories.
		$theme_settings = $theme_manager->get_single_theme_settings( $theme_id );
		if ( $theme_settings !== null ) {
			return $theme_settings;
		}

		$stored_themes_settings = get_option('arlo_themes_settings', []);

		if ( is_array( $stored_themes_settings ) && isset( $stored_themes_settings[ $theme_id ] ) && is_object( $stored_themes_settings[ $theme_id ] ) ) {
			return $stored_themes_settings[ $theme_id ];
		}

		return null;
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
		
		$scheduler = new Scheduler($this);
		
		$this->__set('scheduler', $scheduler);
		
		return $scheduler;
	}

	public function get_theme_manager() {
		if($theme_manager = $this->__get('theme_manager')) {
			return $theme_manager;
		}
		
		$theme_manager = new ThemeManager($this);
		
		$this->__set('theme_manager', $theme_manager);
		
		return $theme_manager;
	}	

	public function get_importer() {
		if($importer = $this->__get('importer')) {
			return $importer;
		}

		$settings = get_option('arlo_settings');
		
		$importer = new Importer($this->get_environment(), $this->get_message_handler(), $this->get_api_client(), $this->get_scheduler(), $this->get_importing_parts());

		if (!empty($settings['import_fragment_size'])) {
			$importer->fragment_size = $settings['import_fragment_size'];
		}
		
		$this->__set('importer', $importer);
		
		return $importer;
	}

	public function get_importing_parts() {
		if($importing_parts = $this->__get('importing_parts')) {
			return $importing_parts;
		}

		$importing_parts = new ImportingParts();
		
		$this->__set('importing_parts', $importing_parts);
		
		return $importing_parts;
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
		
		$notice_handler = new NoticeHandler($this->get_message_handler(), $this->get_importer());
		
		$this->__set('notice_handler', $notice_handler);
		
		return $notice_handler;
	}		
	
	public function get_message_handler() {
		if($message_handler = $this->__get('message_handler')) {
			return $message_handler;
		}
		
		$message_handler = new MessageHandler();
		
		$this->__set('message_handler', $message_handler);
		
		return $message_handler;
	}	

	public function get_timezone_manager() {
		if($timezone_manager = $this->__get('timezone_manager')) {
			return $timezone_manager;
		}
		
		$timezone_manager = new TimeZoneManager($this);
		
		$this->__set('timezone_manager', $timezone_manager);
		
		return $timezone_manager;
	}	

	public function get_schema_manager() {
		if($schema_manager = $this->__get('schema_manager')) {
			return $schema_manager;
		}
		
		$schema_manager = new SchemaManager($this->get_message_handler(), $this);
		
		$this->__set('schema_manager', $schema_manager);
		
		return $schema_manager;
	}	

	public function get_version_handler() {
		if($version_handler = $this->__get('version_handler')) {
			return $version_handler;
		}
		
		$version_handler = new VersionHandler($this->get_message_handler(), $this, $this->get_theme_manager());
		
		$this->__set('version_handler', $version_handler);
		
		return $version_handler;
	}		
	
	public function get_api_client() {
		$platform_name = arlo_get_option('platform_name');
		
		if(!$platform_name) return false;
	
		if($client = $this->__get('api_client')) {
			return $client;
		}
	
		$transport = new Wordpress();
		$transport->setRequestTimeout(30);
		
		$client = new Client($platform_name, $transport, VersionHandler::VERSION);
		
		$this->__set('api_client', $client);
		
		return $client;
	}
	
	public function cron_set_import() {
		$settings = get_option('arlo_settings', []);
		$settings = is_array($settings) ? $settings : [];

		// Throttle: skip this tick if the required interval has not elapsed since
		// the last successful import. This lets the WP-Cron schedule remain at its
		// current cadence (including any custom frequency) while non-production
		// deployments simply absorb the extra ticks without hitting the platform.
		// Falls through when no successful import has ever run (empty last_import)
		// so the very first sync is never blocked.
		$last_import = $this->get_importer()->get_last_import_date();
		if (!empty($last_import)) {
			$interval = self::get_sync_interval($settings);
			if ($interval !== null) {
				$last_import_ts = strtotime($last_import . ' UTC');
				if ($last_import_ts !== false && (time() - $last_import_ts) < $interval) {
					return;
				}
			}
		}

		$scheduler = $this->get_scheduler();
		$scheduler->set_task("import");
		
		//check last import date
		$type = 'import_error';
		$last_import_ts = !empty($last_import) ? strtotime($last_import . ' UTC') : false;
		// If last_import is set but cannot be parsed as a timestamp, treat it as absent:
		// probe from the beginning of time and suppress the "last successful…" sentence.
		if (!empty($last_import) && $last_import_ts === false) {
			$last_import = '';
		}
		$has_clear_failure_evidence = false;

		// Neither probe nor notice applies when auto-sync is disabled: connection-health
		// auto-disable has its own dedicated admin notice; admin-disabled import is
		// visually clear in the settings UX.
		$import_enabled = self::is_import_enabled();

		if ($import_enabled && !empty($last_import) && $last_import_ts !== false) {
			$has_clear_failure_evidence = $scheduler->has_failed_scheduled_import_since(gmdate('Y-m-d H:i:s', $last_import_ts));
		} elseif ($import_enabled && empty($last_import)) {
			// No prior successful import: check for any failed import task since the beginning of time.
			$has_clear_failure_evidence = $scheduler->has_failed_scheduled_import_since('0000-00-00 00:00:00');
		}

		if ($import_enabled && !empty($settings['platform_name']) && self::should_show_sync_error_notice($last_import_ts !== false ? intval($last_import_ts) : null, $has_clear_failure_evidence)) {
			$message_handler = $this->get_message_handler();
			$sync_error_title = esc_html__('Event synchronisation error', 'arlo-training-and-event-management-system' );
			
			if ($message_handler->get_message_by_type_count($type) == 0) {	
				$message = [
				/* translators: %s: UTC time */
				'<p>' . esc_html__('Arlo for WordPress encountered problems when synchronising your event information. Information about your events may be out of date.', 'arlo-training-and-event-management-system' ) . (!empty($last_import) ? ' ' . sprintf(esc_html__('The last successful synchronisation was %s UTC', 'arlo-training-and-event-management-system' ), esc_html($last_import)) : '') . '</p>',
				'<p><a href="' . esc_url(get_admin_url() . 'admin.php?page=arlo-for-wordpress-logs').'" target="_blank" rel="noopener noreferrer">'. esc_html__('View diagnostic logs', 'arlo-training-and-event-management-system' ) . '</a> '. esc_html__('for more information.', 'arlo-training-and-event-management-system' ) . '</p>'
				];
				
				if ($message_handler->set_message($type, $sync_error_title, implode('', $message), true) === false) {
					Logger::log("Couldn't create Arlo import error message");
				}
				
				if (isset($settings['arlo_send_data']) && $settings['arlo_send_data'] == "1") {
					$this->send_log_to_arlo(wp_strip_all_tags($message[0]));
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
			Logger::log( $e->getMessage() );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				wp_die('<pre>' . esc_html( print_r($e, true) ) . '</pre>'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug detail gated behind WP_DEBUG; output is escaped via esc_html().
			} else {
				wp_die( esc_html__( 'An error occurred during the Arlo import scheduler. Please check the Arlo log for details.', 'arlo-training-and-event-management-system' ) );
			}
		}
	}
	
	public function clean_up_tasks() {
		$scheduler = $this->get_scheduler();
		
		$paused_running_tasks = array_merge($scheduler->get_paused_tasks(), $scheduler->get_running_tasks());
				
		foreach ($paused_running_tasks as $task) {
			$ts = strtotime($task->task_modified);
			$now = time() - gmdate('Z');
			if ($now - $ts > 28*60) {
				$task->task_data_text = json_decode($task->task_data_text);

				$message = "Import doesn't respond within 28 minutes, stopped";
				$scheduler->update_task($task->task_id, 3, 'Stopped by the scheduler: ');
				Logger::log($message, !empty($task->task_data_text->import_id) ? $task->task_data_text->import_id : 0);
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
			$settings['keep_settings'] = "1";
		}
		
		$error = [];

		foreach (self::$pages as $page) {
			//try to find and publish the page
			$args = array(
  				'name' => $page['name'],
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

				$posts_page = $page['name'];

				if ($page['name'] == 'events') {
					$posts_page = 'event';
				} else if ($page['name'] == 'venues') {
					$posts_page = 'venue';
				} else if ($page['name'] == 'presenters') {
					$posts_page = 'presenter';
				}
				
				$settings['post_types'][$posts_page]['posts_page'] = $posts[0]->ID;
			} else {
				$error[] = sanitize_text_field($page['name']);
			} 
		}
		
		update_option('arlo_settings', $settings);
		
		$_SESSION['arlo-demo'] = $error;
		
		$scheduler = $this->get_scheduler();
		$scheduler->set_task("import", -1);
	}       
        	
	/**
	 * Reset all connection-health state.
	 *
	 * Called on successful import, on platform name change, and on admin re-enable.
	 *
	 * @since    5.1.0
	 */
	public static function reset_connection_health() {
		update_option('arlo_import_connection_health_disabled', '0');
		delete_option('arlo_import_disabled_message');
		delete_option('arlo_import_disabled_since');
		update_option('arlo_platform_access_failure_count', 0);
		delete_option('arlo_platform_access_first_failure_at');
	}
	/**
	 * Determine whether an import is allowed to run.
	 *
	 * There are two categories of gate:
	 *
	 * Hard gate — always blocks, even for a forced manual import:
	 *   - arlo_import_disabled: set when system requirements (PHP version,
	 *     required extensions, etc.) are not met.
	 *
	 * Soft gates — apply to scheduled/automatic imports only; bypassed
	 * when $forced_manual_import is true so admins can run a manual sync
	 * for diagnostic purposes regardless of these states:
	 *   - arlo_import_connection_health_disabled: auto-set after sustained
	 *     platform access failures (only enforced when healthchecks are on).
	 *   - user_import_enabled: the "Automatic synchronisation enabled" admin
	 *     toggle. Turning it off disables cron imports but must not prevent
	 *     an explicitly requested manual sync.
	 *
	 * @param bool $forced_manual_import True when the import was explicitly
	 *                                   triggered by an admin action rather
	 *                                   than by the cron schedule.
	 */
	public static function is_import_enabled( bool $forced_manual_import = false ): bool {
		// Hard gate — applies to every import regardless of how it was triggered.
		if (get_option('arlo_import_disabled', '0') == '1') return false;

		// Soft gates — skipped when an admin has explicitly requested an import.
		if ( ! $forced_manual_import ) {
			$arlo_settings = get_option('arlo_settings', []);
			if (($arlo_settings['import_connection_healthchecks_enabled'] ?? '1') === '1' && get_option('arlo_import_connection_health_disabled', '0') == '1') return false;
			if (($arlo_settings['user_import_enabled'] ?? '1') !== '1') return false;
		}

		return true;
	}

	/**
	 * Run one step of the import pipeline, or do nothing if imports are currently gated.
	 *
	 * Returns false only when the importer signals a hard failure. Returns true in all
	 * other cases, including when no import action was taken because plugin configuration
	 * or connection health has disabled imports -- a deliberate skip is not a failure.
	 *
	 * @param bool $force    True for a manually triggered import (task_priority = -1).
	 *                       Passed through to the importer to bypass per-import throttle
	 *                       checks, and forwarded to is_import_enabled() so that the soft
	 *                       gates (connection health auto-disable, user_import_enabled
	 *                       toggle) do not block an explicit admin-initiated sync.
	 *                       The hard system-requirements gate (arlo_import_disabled) still
	 *                       applies regardless.
	 * @param int  $task_id  Async task ID passed through to the importer.
	 *
	 * @return bool False on importer failure; true otherwise.
	 */
	public function import($force = false, $task_id = 0): bool {
		if (!self::is_import_enabled($force)) {
			// Mark the task as finished so it does not re-enter the queue on subsequent
			// scheduler runs. Without this, a pending task at status=0 is never updated
			// and gets re-processed on every 5-minute arlo_scheduler cron tick, while
			// cron_set_import() (every 30 min) adds new status=0 tasks behind it.
			// Status 4 ("Import finished") is the correct terminal-success state.
			if ($task_id > 0) {
				$this->get_scheduler()->update_task($task_id, 4, 'Import skipped: sync is currently disabled');
			}
			return true;
		}
		$importer = $this->get_importer();

		//track warnings during the import
		if ($importer->run($force, $task_id) !== false) {
			if ($importer->is_finished) {
				// flush the rewrite rules
				flush_rewrite_rules(true);	
				// delete cache generated in Arlo plugin, instead of all caches.
      			CacheControl::cache_delete(CacheControl::GROUP_PRIMARY_OBJECTS);
			}
			return true;
		}

		return false;
	}

	public static function delete_custom_posts($table, $column, $post_type) {		
		global $wpdb;

		// Simple whitelist based on known calls in importer
		$allowed_map = array(
			'eventtemplates' => array('et_post_name', 'event'),
			'presenters'    => array('p_post_name', 'presenter'),
			'venues'        => array('v_post_name', 'venue'),
		);
		if (!isset($allowed_map[$table]) || $allowed_map[$table][0] !== $column || $allowed_map[$table][1] !== $post_type) {
			return; // parameters not from known safe calls
		}

		$sql = "SELECT `{$column}` FROM `{$wpdb->prefix}arlo_{$table}`";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct database query is required for custom table. Table and column are validated against a whitelist above. Do not need cache here. this is for uninstall process ,or data import process
		$items = $wpdb->get_results($sql, ARRAY_A);

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

	public static function arlo_template_source() {
		$plugin = Arlo_For_Wordpress::get_instance();
		$theme_manager = $plugin->get_theme_manager();

		$selected_theme_id = get_option('arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME);
		$theme_templates = $theme_manager->load_default_templates($selected_theme_id);
		
		$templates = [];
		
		foreach (Arlo_For_Wordpress::get_templates() as $key => $val) {
			if ($key == 'new_custom') { continue; }
			$template_type = array_key_exists( 'type', $val ) ? $val['type'] : $key;
			$templates[ARLO_PLUGIN_PREFIX . '-' . $key] = (isset($theme_templates[$template_type]['html']) ? $theme_templates[$template_type]['html'] : '');
		}

		return $templates;
	}
	
	public static function arlo_add_custom_shortcodes() {
		$settings = get_option('arlo_settings');

		if ( isset($settings['custom_shortcodes']) ) {
			array_walk($settings['custom_shortcodes'], function (&$type,$shortcode) {
				$type = array(
					'id' => $shortcode,
					'name' => ucfirst( str_replace("_"," ",$shortcode) ),
					'shortcode' => '[arlo_'.$shortcode.']',
					'type' => $type
				);
			});

			$current_templates = Arlo_For_Wordpress::get_templates();
			$first_elements = array_slice( $current_templates, 0, count($current_templates)-1 );

			$last_element = array_slice( $current_templates, count($current_templates)-1, 1 );

			Arlo_For_Wordpress::$templates = array_merge( $first_elements, $settings['custom_shortcodes'], $last_element );

			
			$custom_post_types = array();

			foreach ($settings['custom_shortcodes'] as $shortcode_id => $shortcode) {
				$shortcode_name = $shortcode['name'];
				$regionalized = !in_array($shortcode['type'],['venue','presenter','venues','presenters']);
				$shortcode_id = $shortcode['id'];

				$custom_post_types[$shortcode_id] = array(
					'slug' => $shortcode_id,
					'name' => $shortcode_name,
					'singular_name' => $shortcode_name,
					'regionalized' => $regionalized
				);

			}

			Arlo_For_Wordpress::$post_types = array_merge(Arlo_For_Wordpress::$post_types, $custom_post_types);
		}
	}

	public function add_cron_schedules($schedules) {
		// $schedules is placed last so WP core and third-party definitions always
		// win on key collisions. WordPress core already provides 'hourly', 'daily',
		// and (since 5.4) 'weekly'; the plugin-only keys ('minutes_5', 'minutes_15',
		// 'minutes_30') are only registered when nothing else has claimed them.
		return array_merge([
			'minutes_5' => [
				'interval' => 300,
				'display' => esc_html__('Once every 5 minutes','arlo-training-and-event-management-system')
			],
			'minutes_15' => [
				'interval' => 900,
				'display' => esc_html__('Once every 15 minutes','arlo-training-and-event-management-system')
			],
			'hourly' => [
				'interval' => 3600,
				'display' => esc_html__('Once every hour','arlo-training-and-event-management-system')
			],
			'minutes_30' => [
				'interval' => 1800,
				'display' => esc_html__('Every 30 minutes','arlo-training-and-event-management-system')
			],
			'daily' => [
				'interval' => DAY_IN_SECONDS,
				'display' => esc_html__('Once daily','arlo-training-and-event-management-system')
			],
			'weekly' => [
				'interval' => WEEK_IN_SECONDS,
				'display' => esc_html__('Once weekly','arlo-training-and-event-management-system')
			],
		], $schedules);
	}

	/**
	 * Returns the default deployment mode for a given platform name.
	 *
	 * Called during install/upgrade when the deployment_mode key is absent.
	 * Uses the platform name alone — no WP function calls — so it is safe
	 * to unit-test without a WordPress environment.
	 *
	 * @since  5.1.0
	 * @param  string $platform_name  The platform name from arlo_settings. Always a bare
	 *                                label (e.g. "websitetestdata") — Utilities::sanitize_and_validate_platform_name()
	 *                                strips protocol prefixes, trailing slashes, and the
	 *                                ".arlo.co" suffix before storing, so full hostnames or
	 *                                URLs cannot be present in a functioning install.
	 * @return string                 One of the DEPLOYMENT_MODE_* constants.
	 */
	public static function get_deployment_mode_default($platform_name): string {
		// Treat non-scalar values (e.g. a corrupted option) as an empty string so
		// callers do not need to normalise before passing and no array-to-string
		// conversion warning is emitted.
		$platform_name = is_scalar($platform_name) ? (string)$platform_name : '';
		if ($platform_name === '' || strtolower($platform_name) === self::DEFAULT_PLATFORM) {
			return self::DEPLOYMENT_MODE_NON_PRODUCTION;
		}
		return self::DEPLOYMENT_MODE_PRODUCTION;
	}

	/**
	 * Returns the recurrence key corresponding to the effective sync cadence
	 * for the given settings. Used by get_sync_interval() to derive the
	 * throttle interval enforced by the tick-skip gate in cron_set_import().
	 *
	 * Rules:
	 *   production                              → minutes_30 (null interval — gate disabled)
	 *   non_production + websitetestdata/empty  → weekly
	 *   non_production + any other platform     → daily
	 *
	 * Note: the arlo_set_import cron event is always registered at minutes_30
	 * regardless of this value. This method does NOT control the schedule
	 * registration — only the throttle gate cadence.
	 *
	 * This is a pure function (no WP calls) so it can be unit-tested directly.
	 *
	 * @since  5.1.0
	 * @param  array $settings  The arlo_settings option array.
	 * @return string           A schedule key registered in add_cron_schedules().
	 */
	public static function get_cron_schedule(array $settings): string {
		$mode = $settings['deployment_mode'] ?? null;

		// If the key is absent, derive the default from the platform name so
		// the method is self-contained even before set_default_options() runs.
		if ($mode === null) {
			$mode = self::get_deployment_mode_default($settings['platform_name'] ?? '');
		}

		if ($mode !== self::DEPLOYMENT_MODE_NON_PRODUCTION) {
			return 'minutes_30';
		}

		// Non-production: weekly for the default test platform, daily for everything else.
		$platform = is_scalar($settings['platform_name'] ?? '') ? (string)($settings['platform_name'] ?? '') : '';
		if ($platform === '' || strtolower($platform) === self::DEFAULT_PLATFORM) {
			return 'weekly';
		}

		return 'daily';
	}

	/**
	 * Returns the minimum number of seconds that must elapse between successful
	 * imports for the given settings. Used by cron_set_import() to skip ticks
	 * that fire before the configured interval has elapsed, without altering the
	 * WP-Cron schedule itself.
	 *
	 * Returns null for production mode — production installs have no forced
	 * backoff and rely entirely on the WP-Cron schedule cadence, preserving
	 * pre-5.1.0 behaviour exactly. This also means a custom cron frequency
	 * on a production install is honoured without interference.
	 *
	 * @since  5.1.0
	 * @param  array $settings  The arlo_settings option array.
	 * @return int|null         Interval in seconds, or null for no forced backoff.
	 */
	public static function get_sync_interval(array $settings): ?int {
		$map = [
			'daily'  => DAY_IN_SECONDS,
			'weekly' => WEEK_IN_SECONDS,
		];
		return $map[ self::get_cron_schedule($settings) ] ?? null;
	}

	/**
	 * Returns whether the sync error notice should be shown.
	 *
	 * Requires clear evidence that a scheduled sync has failed. When the site has
	 * never had a successful import, any confirmed failure triggers the notice
	 * immediately. When a prior successful import exists, the last successful import
	 * must be at least SYNC_ERROR_NOTICE_MIN_AGE old before the notice is shown.
	 *
	 * cron_set_import() gates on is_import_enabled() before calling this helper,
	 * so it will not be reached when auto-sync is intentionally or automatically
	 * disabled — those states have their own dedicated notices.
	 *
	 * On non-production installs, cron_set_import() only reaches this helper
	 * once the configured daily or weekly cadence is already due.
	 *
	 * @since  5.1.0
	 * @param  int|null $last_import_ts         UTC timestamp of the last successful import, or null if none has ever run.
	 * @param  bool     $has_failure_evidence   Whether a scheduled sync failure is known.
	 * @param  int|null $now_ts                 Optional current UTC timestamp for tests.
	 * @return bool                             True when the warning should be shown.
	 */
	public static function should_show_sync_error_notice(?int $last_import_ts, bool $has_failure_evidence, ?int $now_ts = null): bool {
		if (!$has_failure_evidence) {
			return false;
		}

		// No prior successful import: show immediately on the first confirmed failure.
		if ($last_import_ts === null || $last_import_ts <= 0) {
			return true;
		}

		$now_ts = $now_ts ?? time();
		if ($now_ts <= $last_import_ts) {
			return false;
		}

		return ($now_ts - $last_import_ts) >= self::SYNC_ERROR_NOTICE_MIN_AGE;
	}
	
	public function redirect_proxy() {
		Redirect::object_post_redirect();
	}		
	
	public function start_scheduler_callback() {	
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if(empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		do_action("arlo_scheduler");
		
		wp_die();
	}
	
	public function arlo_get_last_import_log_callback() {
		$successful = isset($_POST['successful']) ? true : false;
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if(empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		$log = Logger::get_log($successful, 1);

		if (count($log)) {
			if (strpos($log[0]['message'], "Error code 404") !== false ) {
				$log[0]['message'] = __('The provided platform name does not exist.', 'arlo-training-and-event-management-system' );
			}

			$log[0]['last_import'] = $this->get_importer()->get_last_import_date();

			wp_send_json($log[0]);
		}
		wp_die();
	}
	
	
	public function arlo_terminate_task_callback() {
		$task_id_string = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'taskID');
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if(empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		$task_id = intval($task_id_string);
		if ($task_id > 0) {
			
			//need to terminate all the upcoming immediate tasks
			$this->get_scheduler()->terminate_all_immediate_task($task_id);
			
			$this->get_importer()->clear_import_lock();
			
			echo esc_html(sprintf("%d", $task_id));
		}
		
		wp_die();
	}
	
	
	public function arlo_get_task_info_callback() {
		$task_id_string = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'taskID');
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if(empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		$task_id = intval($task_id_string);
		if ($task_id > 0) {
			$task = $this->get_scheduler()->get_tasks(null, null, $task_id);

			wp_send_json($task);
		}
		
		wp_die();
	}
	
	public function dismiss_message_callback() {
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if(empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		$id_string = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'id');
		$id = intval($id_string);
		
		if ($id > 0) {			
			$this->get_message_handler()->dismiss_message($id);
		}		
		
		echo esc_html(sprintf("%d", $id));
		wp_die();
	}	

	public function turn_off_send_data_callback() {		
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if(empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		$this->change_setting('arlo_send_data', 0);

		echo 0;
		wp_die();
	}		
	
	
	public function dismissible_notice_callback() {
		$id = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'id');
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if(empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}
		if (!empty($id)) {
			$this->get_notice_handler()->dismiss_user_notice($id);
		}		
		
		echo 0;
		wp_die();
	}

	public function change_setting($setting_name, $value) {
		$settings = get_option('arlo_settings');

		$settings[$setting_name] = $value;

		update_option('arlo_settings', $settings);
	}

	public static function get_post_types_settings(): array {
		$settings = get_option('arlo_settings', []);
		if ( ! is_array( $settings ) || ! isset( $settings['post_types'] ) || ! is_array( $settings['post_types'] ) ) {
			return [];
		}

		$post_types = [];
		foreach ( $settings['post_types'] as $post_type => $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}

			$config['posts_page'] = ( isset( $config['posts_page'] ) && is_numeric( $config['posts_page'] ) )
				? absint( $config['posts_page'] )
				: 0;

			$post_types[ $post_type ] = $config;
		}

		return $post_types;
	}

	public static function get_posts_page_id(string $post_type): int {
		$settings = get_option('arlo_settings', []);
		if ( ! is_array( $settings ) || ! isset( $settings['post_types'] ) || ! is_array( $settings['post_types'] ) ) {
			return 0;
		}

		$config = $settings['post_types'][ $post_type ] ?? null;
		if ( ! is_array( $config ) || ! isset( $config['posts_page'] ) || ! is_numeric( $config['posts_page'] ) ) {
			return 0;
		}

		return absint( $config['posts_page'] );
	}

	public static function get_current_page_arlo_type($default_page = null) {
		global $post;
		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return $default_page;
		}

		foreach (self::get_post_types_settings() as $key => $post_type) {
			if ( ( $post_type['posts_page'] ?? 0 ) === (int) $post->ID ) {
				return $key;
			}
		}

		return $default_page;
	}

	/**
	 * Fall back to the current page only when WP has already resolved a real page
	 * that contains the expected shortcode. This keeps broken host-page settings
	 * from making arbitrary pages look valid, while still allowing the active
	 * event-search page to keep working when its stored posts_page ID is stale.
	 */
	public static function get_current_page_if_contains_shortcode(string $shortcode) {
		global $post;

		$current_page = get_post( $post );
		if ( ! $current_page || $current_page->post_type !== 'page' || empty( $current_page->post_name ) ) {
			return null;
		}

		return has_shortcode( (string) $current_page->post_content, $shortcode ) ? $current_page : null;
	}

	public static function get_region_parameter() {
		$regions = get_option('arlo_regions');

		$arlo_region = get_query_var('arlo-region', '') 
			? strtoupper(get_query_var('arlo-region', ''))
			: (!empty($_COOKIE['arlo-region']) ? sanitize_text_field(wp_unslash( $_COOKIE['arlo-region'] )) : '');

		return (!empty($arlo_region) && is_array($regions) && \ArloTraining\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');
	}
    
    public function get_tag_by_id($tag_id) {
        global $wpdb;        
        
        $prepared_sql = $wpdb->prepare("SELECT id, tag FROM " . $wpdb->prefix . "arlo_tags WHERE id = %d", $tag_id);
        
        return CacheControl::fetch_row($prepared_sql, ARRAY_A);
	}	
	
	/**
	 * Ensures each Arlo host page exists as a WordPress page, creating missing ones as drafts.
	 *
	 * Uses an exact slug match (not full-text search) to determine whether a page already
	 * exists, skipping creation only when a live (publish/future/draft/pending/private) page holds
	 * the intended slug. Trashed pages are ignored — WordPress appends '__trashed' to their
	 * slug when they are moved to the trash, so the original slug is always free to reuse.
	 *
	 * @since 2.2.0
	 * @since 5.1.0 Return shape changed: now returns `array{ pages: array[], added_count: int }`.
	 *              Previously returned `array{ created: int[], existing: int[] }`.
	 *
	 * @param string $page_name Optional. When non-empty, only the page whose slug matches this
	 *                          value is processed. Defaults to '' (process all pages).
	 * @return array {
	 *     @type array[] $pages       One entry per $pages item in scope. Each entry contains:
	 *                                post_id (int|null), post_status (string|null),
	 *                                post_types_key (string), is_new (bool).
	 *     @type int     $added_count Count of newly created draft pages.
	 * }
	 */
	public static function add_pages( $page_name = '' ): array {
		$result = array(
			'pages'			=> array(),
			'added_count'	=> 0,
		);

		foreach ( self::$pages as $page ) {
			if ( ! empty( $page_name ) && $page['name'] !== $page_name ) {
				continue;
			}

			if ( empty( $page['post_types_key'] ) || ! is_string( $page['post_types_key'] ) ) {
				continue;
			}

			// Use an exact slug match to avoid false positives from full-text search.
			$existing = get_posts( array(
				'post_type'		 => 'page',
				'post_status'	 => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'name'			 => $page['name'],
				'posts_per_page' => 1,
				'fields'		 => 'ids',
			) );

			if ( ! empty( $existing ) ) {
				$found_id		= $existing[0];
				$found_status	= get_post_status( $found_id );

				// If the page vanished between get_posts() and get_post_status() (race condition),
				// treat both fields as null so assign_posts_page_defaults() skips this entry
				// rather than writing a dead post ID into settings.
				if ( false === $found_status ) {
					$found_id		= null;
					$found_status	= null;
				}

				$result['pages'][] = array(
					'post_id'		=> $found_id,
					'post_status'	=> $found_status,
					'post_types_key'	=> $page['post_types_key'],
					'is_new'		=> false,
				);
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_type'		=> 'page',
				'post_status'	=> 'draft',
				'post_name'		=> $page['name'],
				'post_title'	=> $page['title'],
				'post_content'	=> $page['content'],
			), true );

			if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
				$result['pages'][] = array(
					'post_id'		=> $post_id,
					'post_status'	=> 'draft',
					'post_types_key'	=> $page['post_types_key'],
					'is_new'		=> true,
				);
				++$result['added_count'];
			} else {
				$result['pages'][] = array(
					'post_id'		=> null,
					'post_status'	=> null,
					'post_types_key'	=> $page['post_types_key'],
					'is_new'		=> false,
				);
			}
		}

		return $result;
	}

	/**
	 * Writes posts_page settings for newly created Arlo pages.
	 *
	 * Assigns a mapping when the existing value is absent, zero, or points to a post that no
	 * longer exists or has been trashed. Only assigns when add_pages() created the page in the
	 * current run (is_new === true), preventing auto-adoption of a pre-existing page that
	 * merely shares an Arlo slug.
	 *
	 * For non-zero existing mappings, the mapped post is fetched to confirm it still exists
	 * and is not trashed. If the post is gone or trashed, a newly created replacement page
	 * is adopted. If the post is live, the mapping is left untouched regardless of status.
	 *
	 * Exits silently on any unexpected state (missing option, corrupt settings, null post IDs).
	 *
	 * Intended callers: single_activate() immediately after add_pages(), and the admin
	 * "Add draft pages" handler. Pass $result['pages'] from add_pages() directly.
	 *
	 * @since 5.1.0
	 *
	 * @param array[] $page_entries The `pages` array returned by add_pages() — one entry per page.
	 *                               Each entry: post_id (int|null), post_status (string|null),
	 *                               post_types_key (string), is_new (bool).
	 * @return void
	 */
	public static function assign_posts_page_defaults( array $page_entries ): void {
		if ( empty( $page_entries ) ) {
			return;
		}

		$settings = get_option( 'arlo_settings' );

		if ( ! is_array( $settings )
			|| ! isset( $settings['post_types'] )
			|| ! is_array( $settings['post_types'] )
		) {
			return;
		}

		$changed = false;

		foreach ( $page_entries as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}

			$key		= $page['post_types_key'] ?? null;
			$post_id	= $page['post_id'] ?? null;
			$is_new		= $page['is_new'] ?? false;

			if ( empty( $key ) || ! is_string( $key ) || empty( $post_id ) || ! is_int( $post_id ) || true !== $is_new ) {
				continue;
			}

			// Initialise a missing or null entry so the deep-key access below is safe on PHP 8.
			if ( ! is_array( $settings['post_types'][ $key ] ?? null ) ) {
				$settings['post_types'][ $key ] = array();
			}

			// If a non-zero mapping already exists, keep it — unless the mapped post has been
			// deleted or trashed, in which case a freshly created replacement should take over.
			$existing_id = absint( $settings['post_types'][ $key ]['posts_page'] ?? 0 );
			if ( $existing_id > 0 ) {
				$existing_post = get_post( $existing_id );
				if ( $existing_post && 'trash' !== $existing_post->post_status ) {
					continue; // Mapped post is live — do not overwrite.
				}
				// Mapped post is gone or trashed — allow the new page to replace it.
			}

			$settings['post_types'][ $key ]['posts_page'] = $post_id;
			$changed = true;
		}

		if ( $changed ) {
			update_option( 'arlo_settings', $settings );
		}
	}

	/**
	 * Add extra parameters to the canonical URL generated by WP (default)
	 * @param  string $canonical
	 * @todo Condition for single event
	 * @todo Check all the conditions in rewrite_rules are met
	 * @return string
	 */
	public function wp_canonical_fix( $canonical, $post ){
		$canonical = untrailingslashit( $canonical );

		$region = $this->get_region_parameter();
		$regionalized = false;
		if ( arlo_is_archive( $post ) ) {
			foreach(self::get_post_types_settings() as $post_type => $config) {
				if ( $config['posts_page'] == $post->ID && !empty( $this::$post_types[ $post_type ][ 'regionalized' ] ) ) {
					$regionalized = true;
				}
			}
		} else {
			if ( !empty( $this::$post_types[ str_replace( 'arlo_', '', $post->post_type ) ][ 'regionalized' ] ) ) {
				$regionalized = true;
			}
		}

		if ( ! empty( $region ) && $regionalized ) {
			$canonical .= '/region-' . rawurlencode( $region );
		}
		$canonical = $this->url_add_filters( $canonical, $post );

		// Ensure that the canonical URL doesn't result in a 302
		return user_trailingslashit( $canonical );
	}

	/**
	 * Add extra parameters to the canonical URL generated by Yoast SEO (wpseo)
	 * @param  string $canonical
	 * @return string
	 */
	public function yoast_canonical_fix( $canonical ){
		$post = get_queried_object();
		return $this->wp_canonical_fix( $canonical, $post );
	}

	/**
	 * Add currently selected filters to the supplied URL
	 * @param  string $url
	 * @param  string $page
	 * @return string
	 * @see Arlo_For_Wordpress->add_canonical_urls() This code was inspired by
	 */
	private function url_add_filters( $url, $page ) {
		$settings = get_option('arlo_settings');

		// Build a canonical URL by appending the active filter values as
		// path segments (e.g. /cat-Sales/presenter-12-Jos%C3%A9-Garc%C3%ADa/).
		//
		// Query vars captured by WP rewrite rules arrive percent-encoded
		// (e.g. Jos%C3%A9), and sanitize_text_field() would strip those
		// raw %XX sequences, mangling non-ASCII names. So we rawurldecode()
		// first to get clean UTF-8, then sanitize, then rawurlencode()
		// back into a safe path segment. This round-trip preserves the
		// readable value while ensuring no decoded character (/, ?, #,
		// etc.) can break URL structure.
		//
		// rawurldecode() is used instead of urldecode() because these
		// values come from URL path segments (rewrite-rule captures),
		// not query strings. urldecode() would convert literal '+' to
		// space, which is incorrect for path data.
		//
		// is_string() guards each value because get_query_var() can
		// return an array (e.g. ?param[]=x) and rawurldecode() would
		// throw a TypeError on non-string input on PHP 8+.

		//has to be the same order as in public.js to construct the same order
		$category = get_query_var('arlo-category');
		if (!empty($category) && is_string($category)) {
			$url .= '/cat-' . rawurlencode(sanitize_text_field(wp_unslash(rawurldecode($category))));
		}

		$month = get_query_var('arlo-month');
		if (!empty($month) && is_string($month)) {
			$url .= '/month-' . rawurlencode(sanitize_text_field(wp_unslash(rawurldecode($month))));
		}

		$location = get_query_var('arlo-location');
		if (!empty($location) && is_string($location)) {
			$url .= '/location-' . rawurlencode(sanitize_text_field(wp_unslash(rawurldecode($location))));
		}

		$venue = get_query_var('arlo-venue');
		if (!empty($venue) && is_string($venue)) {
			$url .= '/venue-' . rawurlencode(sanitize_text_field(wp_unslash(rawurldecode($venue))));
		}

		if (get_query_var('arlo-delivery') !== '' && is_numeric(get_query_var('arlo-delivery'))) {
			$url .= '/delivery-' . intval(get_query_var('arlo-delivery'));
		}

		$eventtag = get_query_var('arlo-eventtag');
		if (!empty($eventtag) && is_string($eventtag)) {
			$eventtag = rawurldecode($eventtag);
			if (is_numeric($eventtag)) {
				$tag = $this->get_tag_by_id($eventtag);
				if (!empty($tag['tag'])) {
					$eventtag = $tag['tag'];
				}
			}
			$url .= '/eventtag-' . rawurlencode(sanitize_text_field(wp_unslash($eventtag)));
		}

		$presenter = get_query_var('arlo-presenter');
		if (!empty($presenter) && is_string($presenter)) {
			$url .= '/presenter-' . rawurlencode(sanitize_text_field(wp_unslash(rawurldecode($presenter))));
		}

		$templatetag = get_query_var('arlo-templatetag');
		if (!empty($templatetag) && is_string($templatetag)) {
			$templatetag = rawurldecode($templatetag);
			if (is_numeric($templatetag)) {
				$tag = $this->get_tag_by_id($templatetag);
				if (!empty($tag['tag'])) {
					$templatetag = $tag['tag'];
				}					
			}
			$url .= '/templatetag-' . rawurlencode(sanitize_text_field(wp_unslash($templatetag)));
		}

		$state = get_query_var('arlo-state');
		if (!empty($state) && is_string($state)) {
			$url .= '/state-' . rawurlencode(sanitize_text_field(wp_unslash(rawurldecode($state))));
		}

		$event_id = get_query_var('arlo-event-id');
		if (!empty($event_id) && is_string($event_id)) {
			$url .= '/event-' . rawurlencode(sanitize_text_field(wp_unslash(rawurldecode($event_id))));
		}

		$search = get_query_var('arlo-search');
		if (!empty($search) && is_string($search)) {
			$url .= '/search/' . rawurlencode(sanitize_text_field(wp_unslash(rawurldecode($search))));
		}

		if (!empty(get_query_var('paged')) && is_numeric(get_query_var('paged')) && strpos($url, "/page/") === false) {
			$url .= '/page/' . wp_unslash(get_query_var('paged'));
		}

		return $url;
	}

	/**
	 * AJAX callback: check whether a WP page contains the expected Arlo shortcode.
	 *
	 * Privileged endpoint — no nopriv variant. Returns wp_send_json_success with
	 * the check result, or wp_send_json_error on invalid input. Indeterminate
	 * cases are success payloads (valid configured states), not errors.
	 *
	 * @since 5.1.0
	 */
	public function check_page_shortcode_callback() {
		$nonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'nonce');
		if (empty($nonce) || !wp_verify_nonce($nonce, $this->plugin_slug . '-arlo-for-wordpress-script')) {
			wp_die();
		}
		if (!current_user_can('manage_options')) {
			wp_die();
		}

		$post_id = absint(\ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'post_id'));
		$template_id = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'template_id');

		if ($post_id === 0) {
			wp_send_json_error(array('code' => 'invalid_post_id'), 400);
			return;
		}

		$templates = self::get_templates();
		if (!array_key_exists($template_id, $templates) || empty($templates[$template_id]['shortcode'])) {
			wp_send_json_error(array('code' => 'unknown_template'), 400);
			return;
		}

		$shortcode = $templates[$template_id]['shortcode'];
		$cache_key = 'arlo_sc_check_' . $template_id . '_' . $post_id;
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			wp_send_json_success($cached);
			return;
		}

		$post = get_post($post_id);

		if ($post === null) {
			$data = array(
				'post_id'          => $post_id,
				'template_id'      => $template_id,
				'shortcode'        => $shortcode,
				'shortcode_exists' => null,
				'reason'           => 'post_not_found',
				'post_slug'        => null,
				'post_status'      => null,
			);
			set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
			wp_send_json_success($data);
			return;
		}

		if ($post->post_type !== 'page') {
			$data = array(
				'post_id'          => $post_id,
				'template_id'      => $template_id,
				'shortcode'        => $shortcode,
				'shortcode_exists' => null,
				'reason'           => 'unsupported_post_type',
				'post_slug'        => null,
				'post_status'      => $post->post_status,
			);
			set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
			wp_send_json_success($data);
			return;
		}

		if ($post->post_status !== 'publish') {
			// Compute slug for draft/future/pending pages that already have a slug set.
			$draftable_statuses = [ 'draft', 'future', 'pending' ];
			$not_pub_slug       = null;
			if ( in_array( $post->post_status, $draftable_statuses, true ) && '' !== $post->post_name ) {
				$uri = get_page_uri( $post );
				if ( is_string( $uri ) && '' !== $uri ) {
					$not_pub_slug = wp_make_link_relative( get_home_url( null, '/' . trim( $uri, '/' ) . '/' ) ) ?: null;
				}
			}
			$data = array(
				'post_id'          => $post_id,
				'template_id'      => $template_id,
				'shortcode'        => $shortcode,
				'shortcode_exists' => null,
				'reason'           => 'not_publishable',
				'post_slug'        => $not_pub_slug,
				'post_status'      => $post->post_status,
			);
			set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
			wp_send_json_success($data);
			return;
		}

		$content = $post->post_content;

		// Both publish branches (scan_unavailable and null reason) need the slug.
		// Compute once here so get_permalink() is called only once.
		$pub_permalink = get_permalink( $post_id );
		$pub_slug      = ( is_string( $pub_permalink ) && '' !== $pub_permalink )
			? ( wp_make_link_relative( $pub_permalink ) ?: null )
			: null;

		preg_match('/^\[([^\s\]]+)/', $shortcode, $m);
		$tag = isset($m[1]) ? $m[1] : '';
		$found = ($tag !== '') && has_shortcode($content, $tag);

		// When post_content is genuinely empty, the content may be managed outside
		// the core editor (for example by a page builder or other plugin). Only
		// flag this case; short-but-non-empty content (e.g. a single shortcode
		// tag) should still receive a definitive true/false result.
		if (!$found && trim($content) === '') {
			$data = array(
				'post_id'          => $post_id,
				'template_id'      => $template_id,
				'shortcode'        => $shortcode,
				'shortcode_exists' => null,
				'reason'           => 'scan_unavailable',
				'post_slug'        => $pub_slug,
				'post_status'      => 'publish',
			);
			set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
			wp_send_json_success($data);
			return;
		}

		$data = array(
			'post_id'          => $post_id,
			'template_id'      => $template_id,
			'shortcode'        => $shortcode,
			'shortcode_exists' => $found,
			'reason'           => null,
			'post_slug'        => $pub_slug,
			'post_status'      => 'publish',
		);
		set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
		wp_send_json_success($data);
	}

	/**
	 * Invalidate the shortcode check transient cache when a page is saved or trashed.
	 *
	 * Scoped to the save_post_page hook (WP pages only) so saves to other post
	 * types never pay the iteration cost.
	 *
	 * @since 5.1.0
	 *
	 * @param int $post_id The saved post ID.
	 */
	public function invalidate_shortcode_check_cache($post_id) {
		$safe_id   = absint($post_id);
		$templates = self::get_templates();
		foreach ($templates as $template_id => $template) {
			if (empty($template['shortcode'])) {
				continue;
			}
			delete_transient('arlo_sc_check_' . $template_id . '_' . $safe_id);
		}
	}

	/**
	 * Invalidate the shortcode check cache when a page changes status.
	 *
	 * @since 5.1.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       The post object.
	 */
	public function invalidate_shortcode_check_cache_on_status_change($new_status, $old_status, $post) {
		if ($new_status === $old_status || !($post instanceof WP_Post) || $post->post_type !== 'page') {
			return;
		}

		$this->invalidate_shortcode_check_cache($post->ID);
	}

	/**
	 * Invalidate the shortcode check cache when a page is permanently deleted.
	 *
	 * `transition_post_status` does not fire for permanent deletes, so this
	 * hook ensures the cached indicator is cleared immediately on deletion.
	 *
	 * @since 5.1.0
	 *
	 * @param int     $post_id The post ID about to be deleted.
	 * @param WP_Post $post    The post object.
	 */
	public function invalidate_shortcode_check_cache_on_delete($post_id, $post) {
		if (!($post instanceof WP_Post) || $post->post_type !== 'page') {
			return;
		}

		$this->invalidate_shortcode_check_cache($post_id);
	}
}
