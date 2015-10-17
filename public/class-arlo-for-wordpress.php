<?php
/**
 * Plugin Name.
 *
 * @package   Arlo_For_Wordpress
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      http://arlo.co
 * @copyright 2015 Arlo
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to 'class-arlo-for-wordpress-admin.php'
 *
 * @TODO: Rename this class to a proper name for your plugin.
 *
 * @package Arlo_For_Wordpress
 * @author  Your Name <email@example.com>
 */
class Arlo_For_Wordpress {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '2.0.6';

	/**
	 * Minimum required PHP version
	 *
	 * @since   2.0.6
	 *
	 * @var     string
	 */
	const MIN_PHP_VERSION = '5.4.0';

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
	protected $plugin_slug = 'arlo-for-wordpress';

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
                'event' => array(
			'slug' => 'event',
			'name' => 'Events',
			'singular_name' => 'Event'
		),
		'presenter' => array(
			'slug' => 'presenter',
			'name' => 'Presenters',
			'singular_name' => 'Presenter'
		),
		'venue' => array(
			'slug' => 'venue',
			'name' => 'Venues',
			'singular_name' => 'Venue'
		),
		'upcoming' => array(
			'slug' => 'upcomingevents',
			'name' => 'Upcoming events',
			'singular_name' => 'Upcoming event'
		)        
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
	 * $delivery_labels: used to show the different delivery types
	 *
	 * @since    2.0.6
	 *
	 * @var      array
	 */
    public static $delivery_labels = array(
        0 => 'Workshop',
        1 => 'Online (Webinar)',
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
			'name' => 'Event'
		),
		'events' => array(
			'id' => 'events',
			'name' => 'Event List'
		),
		'upcoming' => array(
			'id' => 'upcoming',
			'name' => 'Upcoming Events'
		),
		'presenter' => array(
			'id' => 'presenter',
			'name' => 'Presenter'
		),
		'presenters' => array(
			'id' => 'presenters',
			'name' => 'Presenter List'
		),
		'venue' => array(
			'id' => 'venue',
			'name' => 'Venue'
		),
		'venues' => array(
			'id' => 'venues',
			'name' => 'Venue List'
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

		// Activate plugin when new blog is added
		//add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		/* Define custom functionality.
		 * Refer To http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		//add_action( '@TODO', array( $this, 'action_method_name' ) );
		//add_filter( '@TODO', array( $this, 'filter_method_name' ) );
		
		// cron actions
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) ); 
		add_action( 'arlo_import', array( $this, 'cron_import' ) );
		

		// GP: Check if the scheduled task is entered. If it does not exist set it. (This ensures it is in as long as the plugin is activated.  
		if ( ! wp_next_scheduled('arlo_import')) {
			// wp_clear_scheduled_hook( 'arlo_import' );
			wp_schedule_event( time(), 'minutes_15', 'arlo_import' );
		}

		// content and excerpt filters to hijack arlo registered post types
		add_filter('the_content', 'arlo_the_content');
	
		// the_post action - allows us to inject Arlo-specific data as required
		// consider this later
		//add_action( 'the_posts', array( $this, 'the_posts_action' ) );
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
		
		//check the PHP version
		if (version_compare(phpversion(), self::MIN_PHP_VERSION) === -1) {
    		wp_die('The minimum required PHP version for the Arlo plugin is ' . self::MIN_PHP_VERSION);
		}

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
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here

		// flush permalinks upon plugin deactivation
		arlo_add_datamodel();

		flush_rewrite_rules();

		// must happen before adding pages
		self::set_default_options();
		
		// run import every 15 minutes
		$temp = wp_schedule_event( time(), 'minutes_15', 'arlo_import' ) . ': Log initiated.';
		self::get_instance()->add_import_log($temp, $timestamp);

		// now add pages
		self::add_pages();
	}

	/**
	 * Set the default values for arlo wp_options table option
	 *
	 * @since    1.0.0
	 *
	 */
	function set_default_options() {
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

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here

		// flush permalinks upon plugin deactivation
		flush_rewrite_rules();
		
		wp_clear_scheduled_hook( 'arlo_import' );
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css?04102015', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js?21092015', __FILE__ ), array( 'jquery' ), self::VERSION );
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

        /*$trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);*/
        return null;
    }

	/* API & import functionality */
	
	public function get_import_log($limit=1) {
		global $wpdb;
		
		$table_name = "{$wpdb->prefix}arlo_import_log";
		
		$items = $wpdb->get_results(
			"SELECT log.* 
			FROM $table_name log 
			ORDER BY log.created DESC
			LIMIT $limit"
		);
		
		return $items;
	}
	
	public function get_last_successful_import_log() {
		global $wpdb;
		
		$table_name = "{$wpdb->prefix}arlo_import_log";
		
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
	
	public function add_import_log($message, $timestamp, $successful=true) {
		global $wpdb;
		
		$table_name = "{$wpdb->prefix}arlo_import_log";
		
		$wpdb->query(
			$wpdb->prepare( 
				"INSERT INTO $table_name 
				(message, created, successful) 
				VALUES ( %s, %s, %d ) 
				", 
			    $message,
				$timestamp,
				$successful
			)
		);
		
		// MV: Automatically purge the log after two weeks day. 
		$wpdb->query(
			$wpdb->prepare("DELETE FROM $table_name WHERE CREATED < NOW() - INTERVAL 14 DAY ORDER BY ID ASC LIMIT 10", '')
		);

		// $wpdb->delete(
		// 	$table_name,
		// 	array(
		// 		'CREATED' => 
		// 	);
			
		// );

		if($successful) {
			$this->set_last_import($timestamp);
		}
	}
	
	// should only be used when successful
	public function set_last_import($timestamp) {
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
		
		// need to get platform name from options table - using theme-test for development purposes
		$client = new \ArloAPI\Client($platform_name, $transport);
		
		$this->__set('api_client', $client);
		
		return $client;
	}
	
	public function cron_import() {
		ob_start();
		try{
			$this->import();
		}catch(\Exception $e){}
		ob_end_clean();
	}
	
	public function import($force=false) {
		// check for last sucessful import. Continue if imported mor than an hour ago or forced. Otherwise, return.
            
		$timestamp = date('Y-m-d H:i:s');
		$this->add_import_log('Import Started', $timestamp, false);
		$last = $this->get_last_import();
		
		// MV: Untangled the if statements. 
		// If not forced
		if(!$force) {
			// LOG THIS AS AN AUTOMATIC IMPORT
			$this->add_import_log('Import identified as automatic import.', $timestamp, false);
			if(!empty($last)) {
				// LOG THAT A PREVIOUS SUCCESSFUL IMPORT HAS BEEN FOUND
				$this->add_import_log('Previous succesful import found.', $timestamp, false);
				if(strtotime('-1 hour') > strtotime($last)) {
					// LOG THE FACT THAT PREVIOUS SUCCESSFUL IMPORT IS MORE THAN AN HOUR AGO
					$this->add_import_log('Import more than an hour old. Import required.', $timestamp, false);
				}
				else {
					// LOG THE FACT THAT PREVIOUS SUCCESSFUL IMPORT IS MORE THAN AN HOUR AGO
					$this->add_import_log('Import less than an hour old. Import stopped.', $timestamp, false);
					// LOG DATA USED TO DECIDE IMPORT NOT REQUIRED.
					$this->add_import_log($last . '-'  . strtotime($last) . '-' . strtotime('-1 hour') . '-'  . !$force, $timestamp, false);
					return false;
				}
			}
		}
	
		// excessive, but some servers are slow...
		ini_set('max_execution_time', 300);
		set_time_limit(300);
		
		// first check valid api_client
		if(!$this->get_api_client()) return false;
		
		// need to check for valid platform name here - return false if none found
		// can we ping tha API?
	
		// set import start time
		$timestamp = date('Y-m-d H:i:s');
		
		try {
			global $wpdb;
			
			// lets put it all in a transaction
			$wpdb->query('START TRANSACTION');
			
			// import from arlo endpoints
			$this->import_timezones($timestamp);
			
			$this->import_presenters($timestamp);
			
			$this->import_event_templates($timestamp);
			
			$this->import_events($timestamp);
			
			$this->import_venues($timestamp);
			
			$this->import_categories($timestamp);
			
			// now delete the old data
			$this->import_cleanup($timestamp);
                        
			// commit
			$wpdb->query('COMMIT');
		} catch(\Exception $e) {
			// rollback
			$wpdb->query('ROLLBACK');
                        
			$this->add_import_log('Import Failed: ' . $e->getMessage(), $timestamp, false);
			
			return false;
		}
                
		// delete unneeded custom posts

		$this->delete_custom_posts('eventtemplates','et_post_name','event');

		$this->delete_custom_posts('presenters','p_post_name','presenter');

		$this->delete_custom_posts('venues','v_post_name','venue');
			
		// flush the rewrite rules
		flush_rewrite_rules(true);
		
		// update logs
		$this->add_import_log('Import Successful', $timestamp);
		
		return true;
	}
	
	private function import_event_templates($timestamp) {
		global $wpdb;
	
		$client = $this->get_api_client();
		
		$items = $client->EventTemplateSearch()->getAllEventTemplates(
			array(
				'TemplateID',
				'Code',
				'Name',
				'Description',
				'AdvertisedPresenters',
				'BestAdvertisedOffers',
				'ViewUri',
				'RegisterInterestUri',
				'Categories',
			)
		);
		
		$table_name = "{$wpdb->prefix}arlo_eventtemplates";
		
		if(!empty($items)) {
			foreach($items as $item) {
				$slug = sanitize_title($item->TemplateID . ' ' . $item->Name);
				$query = $wpdb->query(
					$wpdb->prepare( 
						"INSERT INTO $table_name 
						(et_arlo_id, et_code, et_name, et_descriptionsummary, et_post_name, active, et_registerinteresturi) 
						VALUES ( %d, %s, %s, %s, %s, %s, %s ) 
						", 
					    $item->TemplateID,
						@$item->Code,
						$item->Name,
						@$item->Description->Summary,
						$slug,
						$timestamp,
						$item->RegisterInterestUri
					)
				);
                                
                                if ($query === false) {
                                    throw new Exception('Database insert failed: ' . $table_name);
                                }
				
				$template_event_id = $wpdb->insert_id;
				
				// create associated custom post, if it dosen't exist
				if(!arlo_get_post_by_name($slug, 'arlo_event')) {
					wp_insert_post(array(
						'post_title'    => $item->Name,
						'post_content'  => @$item->Description->Summary,
						'post_status'   => 'publish',
						'post_author'   => 1,
						'post_type'		=> 'arlo_event',
						'post_name'		=> $slug
					));
				}
				
				// need to insert associated data here
				// advertised offers
				if(isset($item->BestAdvertisedOffers) && !empty($item->BestAdvertisedOffers)) {
					$offers = array_reverse($item->BestAdvertisedOffers);
					foreach($offers as $key => $offer) {
						$query = $wpdb->query( $wpdb->prepare( 
							"INSERT INTO {$wpdb->prefix}arlo_offers 
							(o_arlo_id, et_id, e_id, o_label, o_isdiscountoffer, o_currencycode, o_offeramounttaxexclusive, o_offeramounttaxinclusive, o_formattedamounttaxexclusive, o_formattedamounttaxinclusive, o_taxrateshortcode, o_taxratename, o_taxratepercentage, o_message, o_order, o_replaces, active) 
							VALUES ( %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s ) 
							", 
							$offer->OfferID+1,
						    $template_event_id,
							null,
							@$offer->Label,
							@$offer->IsDiscountOffer,
							@$offer->OfferAmount->CurrencyCode,
							@$offer->OfferAmount->AmountTaxExclusive,
							@$offer->OfferAmount->AmountTaxInclusive,
							@$offer->OfferAmount->FormattedAmountTaxExclusive,
							@$offer->OfferAmount->FormattedAmountTaxInclusive,
							@$offer->OfferAmount->TaxRate->ShortName,
							@$offer->OfferAmount->TaxRate->Name,
							@$offer->OfferAmount->TaxRate->RatePercent,
							@$offer->Message,
							$key+1,
							(isset($offer->ReplacesOfferID)) ? $offer->ReplacesOfferID+1 : null,
							$timestamp
						) );
                                                        
                                                if ($query === false) {
                                                    throw new Exception('Database insert failed: ' . $table_name);
                                                }
					}
				}
				
				// content fields
				if(isset($item->Description->ContentFields) && !empty($item->Description->ContentFields)) {
					foreach($item->Description->ContentFields as $index => $content) {
						$query = $wpdb->query( $wpdb->prepare( 
							"INSERT INTO {$wpdb->prefix}arlo_contentfields 
							(et_id, cf_fieldname, cf_text, cf_order, e_contenttype, active) 
							VALUES ( %d, %s, %s, %s, %s, %s ) 
							", 
						    $template_event_id,
							@$content->FieldName,
							@$content->Content->Text,
							$index,
							@$content->Content->ContentType,
							$timestamp
						) );
                                                if ($query === false) {
                                                    throw new Exception('Database insert failed: ' . $table_name);
                                                }
					}
				}
				
				// prsenters
				if(isset($item->AdvertisedPresenters) && !empty($item->AdvertisedPresenters)) {
					foreach($item->AdvertisedPresenters as $index => $presenter) {
						$query = $wpdb->query( $wpdb->prepare( 
							"REPLACE INTO {$wpdb->prefix}arlo_eventtemplates_presenters 
							(et_arlo_id, p_arlo_id, p_order, active) 
							VALUES ( %d, %d, %d, %s ) 
							", 
						    $item->TemplateID,
						    $presenter->PresenterID,
						    $index,
						    $timestamp
						) );
                                                        
                                                if ($query === false) {
                                                    throw new Exception('Database insert failed: ' . $table_name);
                                                }
					}
				}
				
				// categories
				if(isset($item->Categories) && !empty($item->Categories)) {
					foreach($item->Categories as $index => $category) {
						$query = $wpdb->query( $wpdb->prepare( 
							"REPLACE INTO {$wpdb->prefix}arlo_eventtemplates_categories 
							(et_arlo_id, c_arlo_id, active) 
							VALUES ( %d, %d, %s ) 
							", 
						    $item->TemplateID,
						    $category->CategoryID,
						    $timestamp
						) );
                                                        
                                                if ($query === false) {
                                                    throw new Exception('Database insert failed: ' . $table_name);
                                                }
					}
				}
			}
		}
		
		return $items;
	}
	
	private function import_events($timestamp) {
		global $wpdb;
	
		$client = $this->get_api_client();
		
		$items = $client->EventSearch()->getAllEvents(
			array(
				'EventID',
				'EventTemplateID',
				'Name',
				'Code',
				'Summary',
				'Description',
				'StartDateTime',
				'EndDateTime',
				'TimeZoneID',
				'TimeZone',
				'Location',
				'IsFull',
				'PlacesRemaining',
				'AdvertisedOffers',
				'SessionsDescription',
				'Presenters',
				'Notice',
				'ViewUri',
				'RegistrationInfo',
				'Provider',
				'TemplateCode'
			)
		);
		
		$table_name = "{$wpdb->prefix}arlo_events";
				
		if(!empty($items)) {
			foreach($items as $item) {
				$query = $wpdb->query(
					$wpdb->prepare( 
						"INSERT INTO $table_name 
						(e_arlo_id, et_arlo_id, e_code, e_startdatetime, e_finishdatetime, e_datetimeoffset, e_timezone, e_timezone_id, v_id, e_locationname, e_locationroomname, e_locationvisible , e_isfull, e_placesremaining, e_sessiondescription, e_notice, e_viewuri, e_registermessage, e_registeruri, e_providerorganisation, e_providerwebsite, e_isonline, active) 
						VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) 
						", 
					    $item->EventID,
						$item->EventTemplateID,
						@$item->Code,
						substr(@$item->StartDateTime,0,26),
						substr(@$item->EndDateTime,0,26),
						substr(@$item->StartDateTime,27,6),
						@$item->TimeZone,
						@$item->TimeZoneID,
						@$item->Location->VenueID,
						@$item->Location->Name,
						@$item->Location->VenueRoomName,
						(!empty($item->Location->ViewUri) ? 1 : 0 ),
						@$item->IsFull,
						@$item->PlacesRemaining,
						@$item->SessionsDescription,
						@$item->Notice,
						@$item->ViewUri,
						@$item->RegistrationInfo->RegisterMessage,
						@$item->RegistrationInfo->RegisterUri,
						@$item->Provider->Name,
						@$item->Provider->WebsiteUri,
						@$item->Location->IsOnline,
						$timestamp
					)
				);
                                
				if ($query === false) {
					
					die($wpdb->prepare( 
						"INSERT INTO $table_name 
						(e_arlo_id, et_arlo_id, e_code, e_startdatetime, e_finishdatetime, e_datetimeoffset, e_timezone, e_timezone_id, v_id, e_locationname, e_locationroomname, e_locationvisible , e_isfull, e_placesremaining, e_sessiondescription, e_notice, e_viewuri, e_registermessage, e_registeruri, e_providerorganisation, e_providerwebsite, e_isonline, active) 
						VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) 
						", 
					    $item->EventID,
						$item->EventTemplateID,
						@$item->Code,
						substr(@$item->StartDateTime,0,26),
						substr(@$item->EndDateTime,0,26),
						substr(@$item->StartDateTime,27,6),
						@$item->TimeZone,
						@$item->TimeZoneID,
						@$item->Location->VenueID,
						@$item->Location->Name,
						@$item->Location->VenueRoomName,
						(!empty($item->Location->ViewUri) ? 1 : 0 ),
						@$item->IsFull,
						@$item->PlacesRemaining,
						@$item->SessionsDescription,
						@$item->Notice,
						@$item->ViewUri,
						@$item->RegistrationInfo->RegisterMessage,
						@$item->RegistrationInfo->RegisterUri,
						@$item->Provider->Name,
						@$item->Provider->WebsiteUri,
						@$item->Location->IsOnline,
						$timestamp
					));
					throw new Exception('Database insert failed: ' . $table_name);
				}
				
				$event_id = $wpdb->insert_id;
				
				// need to insert associated data here
				// advertised offers
				if(isset($item->AdvertisedOffers) && !empty($item->AdvertisedOffers)) {
					$offers = array_reverse($item->AdvertisedOffers);
					foreach($offers as $key => $offer) {
						$query = $wpdb->query( $wpdb->prepare( 
							"INSERT INTO {$wpdb->prefix}arlo_offers 
							(o_arlo_id, et_id, e_id, o_label, o_isdiscountoffer, o_currencycode, o_offeramounttaxexclusive, o_offeramounttaxinclusive, o_formattedamounttaxexclusive, o_formattedamounttaxinclusive, o_taxrateshortcode, o_taxratename, o_taxratepercentage, o_message, o_order, o_replaces, active) 
							VALUES ( %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s ) 
							", 
							$offer->OfferID+1,
						    null,
							$event_id,
							@$offer->Label,
							@$offer->IsDiscountOffer,
							@$offer->OfferAmount->CurrencyCode,
							@$offer->OfferAmount->AmountTaxExclusive,
							@$offer->OfferAmount->AmountTaxInclusive,
							@$offer->OfferAmount->FormattedAmountTaxExclusive,
							@$offer->OfferAmount->FormattedAmountTaxInclusive,
							@$offer->OfferAmount->TaxRate->ShortName,
							@$offer->OfferAmount->TaxRate->Name,
							@$offer->OfferAmount->TaxRate->RatePercent,
							@$offer->Message,
							$key+1,
							(isset($offer->ReplacesOfferID)) ? $offer->ReplacesOfferID+1 : null,
							$timestamp
						) );
						
						if ($query === false) {
							throw new Exception('Database insert failed: ' . $table_name);
						}
					}
				}
				
				// prsenters
				if(isset($item->Presenters) && !empty($item->Presenters)) {
					foreach($item->Presenters as $index => $presenter) {
						$query = $wpdb->query( $wpdb->prepare( 
							"REPLACE INTO {$wpdb->prefix}arlo_events_presenters 
							(e_arlo_id, p_arlo_id, p_order, active) 
							VALUES ( %d, %d, %d, %s ) 
							", 
						    $item->EventID,
						    $presenter->PresenterID,
						    $index,
						    $timestamp
						) );
						
						if ($query === false) {
							throw new Exception('Database insert failed: ' . $table_name);
						}
					}
				}
			}
			
		}
		
		return $items;
	}
	
	private function import_timezones($timestamp) {
		global $wpdb;
	
		$client = $this->get_api_client();
		
		$items = $client->Timezones()->getAllTimezones();
		
		$table_name = "{$wpdb->prefix}arlo_timezones";
		
		if(!empty($items)) {
			foreach($items as $item) {
				$query = $wpdb->query( $wpdb->prepare( 
					"REPLACE INTO $table_name 
					(id, name, active) 
					VALUES (%d, %s, %s)", 
				    $item->TimeZoneID,
					$item->Name,
					$timestamp
				) );
                                
				if ($query === false) {
					throw new Exception('Database insert failed: ' . $table_name);
				}				
			}
		}
		
		return $items;
	}	
	
	private function import_presenters($timestamp) {
		global $wpdb;
	
		$client = $this->get_api_client();
		
		$items = $client->Presenters()->getAllPresenters(
			array(
				'PresenterID',
				'FirstName',
				'LastName',
				'ViewUri',
				'Profile',
				'SocialNetworkInfo',
			)
		);
		
		$table_name = "{$wpdb->prefix}arlo_presenters";
		
		if(!empty($items)) {
			foreach($items as $item) {
				$slug = sanitize_title($item->PresenterID . ' ' . $item->FirstName . ' ' . $item->LastName);
				$query = $wpdb->query( $wpdb->prepare( 
					"INSERT INTO $table_name 
					(p_arlo_id, p_firstname, p_lastname, p_viewuri, p_profile, p_qualifications, p_interests, p_twitterid, p_facebookid, p_linkedinid, p_post_name, active) 
					VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) 
					", 
				    $item->PresenterID,
					$item->FirstName,
					$item->LastName,
					@$item->ViewUri,
					@$item->Profile->ProfessionalProfile->Text,
					@$item->Profile->Qualifications->Text,
					@$item->Profile->Interests->Text,
					@$item->SocialNetworkInfo->TwitterID,
					@$item->SocialNetworkInfo->FacebookID,
					@$item->SocialNetworkInfo->LinkedInID,
					$slug,
					$timestamp
				) );
                                
                                if ($query === false) {
                                    throw new Exception('Database insert failed: ' . $table_name);
                                }

				
				$name = $item->FirstName . ' ' . $item->LastName;
				
				// create associated custom post, if it dosen't exist
				if(!arlo_get_post_by_name($slug, 'arlo_presenter')) {
					wp_insert_post(array(
						'post_title'    => $name,
						'post_content'  => '',
						'post_status'   => 'publish',
						'post_author'   => 1,
						'post_type'		=> 'arlo_presenter',
						'post_name'		=> $slug
					));
				}
			}
		}
		
		return $items;
	}
	
	private function import_venues($timestamp) {
		global $wpdb;
	
		$client = $this->get_api_client();
		
		$items = $client->Venues()->getAllVenues(
			array(
				'VenueID',
				'Name',
				'GeoData',
				'PhysicalAddress',
				'FacilityInfo',
				'ViewUri'
			)
		);
		
		$table_name = "{$wpdb->prefix}arlo_venues";
		
		if(!empty($items)) {
			foreach($items as $item) {
				$slug = sanitize_title($item->VenueID . ' ' . $item->Name);
				$query = $wpdb->query( $wpdb->prepare( 
					"INSERT INTO $table_name 
					(v_arlo_id, v_name, v_geodatapointlatitude, v_geodatapointlongitude, v_physicaladdressline1, v_physicaladdressline2, v_physicaladdressline3, v_physicaladdressline4, v_physicaladdresssuburb, v_physicaladdresscity, v_physicaladdressstate, v_physicaladdresspostcode, v_physicaladdresscountry, v_viewuri, v_facilityinfodirections, v_facilityinfoparking, v_post_name, active) 
					VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )
					", 
				    $item->VenueID,
					$item->Name,
					@$item->GeoData->PointLatitude,
					@$item->GeoData->PointLongitude,
					@$item->PhysicalAddress->StreetLine1,
					@$item->PhysicalAddress->StreetLine2,
					@$item->PhysicalAddress->StreetLine3,
					@$item->PhysicalAddress->StreetLine4,
					@$item->PhysicalAddress->Suburb,
					@$item->PhysicalAddress->City,
					@$item->PhysicalAddress->State,
					@$item->PhysicalAddress->PostCode,
					@$item->PhysicalAddress->Country,
					@$item->ViewUri,
					@$item->FacilityInfo->Directions->Text,
					@$item->FacilityInfo->Parking->Text,
					$slug,
					$timestamp
				) );
                                
                                if ($query === false) {
                                    throw new Exception('Database insert failed: ' . $table_name);
                                }
                                
				
				// create associated custom post, if it dosen't exist
				// should be arlo_venues
				if(!arlo_get_post_by_name($slug, 'arlo_venue')) {
					wp_insert_post(array(
						'post_title'    => $item->Name,
						'post_content'  => '',
						'post_status'   => 'publish',
						'post_author'   => 1,
						'post_type'		=> 'arlo_venue',
						'post_name'		=> $slug
					));
				}
			}
		}
		
		return $items;
	}
	
	private function import_categories($timestamp) {
		global $wpdb;
	
		$client = $this->get_api_client();
		
		$items = $client->Categories()->getAllCategories(
			array(
				'CategoryID',
				'ParentCategoryID',
				'Name',
				'SequenceIndex',
				'Description',
				'Footer',
			)
		);
		
		$table_name = "{$wpdb->prefix}arlo_categories";
		
		if(!empty($items)) {
			foreach($items as $item) {
				$slug = sanitize_title($item->CategoryID . ' ' . $item->Name);
				$query = $wpdb->query( $wpdb->prepare( 
					"REPLACE INTO $table_name 
					(c_arlo_id, c_name, c_slug, c_header, c_footer, c_order, c_parent_id, active) 
					VALUES ( %d, %s, %s, %s, %s, %d, %d, %s ) 
					", 
				    $item->CategoryID,
					$item->Name,
					$slug,
					@$item->Description->Text,
					@$item->Footer->Text,
					@$item->SequenceIndex,
					@$item->ParentCategoryID,
					$timestamp
				) );
                                
                if ($query === false) {
                    throw new Exception('Database insert failed: ' . $table_name);
                }               
			}
		}
		
		$this->import_eventtemplatescategoriesitems($timestamp);
		
		//count the templates in the categories
		$sql = "
		SELECT
			COUNT(1) AS num,  
			c_arlo_id
		FROM
			{$wpdb->prefix}arlo_eventtemplates_categories
		WHERE
			active = '{$timestamp}'
		GROUP BY
			c_arlo_id
		";

		$items = $wpdb->get_results($sql, ARRAY_A);
		if (!is_null($items)) {
			foreach ($items as $counts) {
				$sql = "
				UPDATE
					{$wpdb->prefix}arlo_categories
				SET
					c_template_num = %d
				WHERE
					c_arlo_id = %d
				";
				$query = $wpdb->query( $wpdb->prepare($sql, $counts['num'], $counts['c_arlo_id']) );
				
		        if ($query === false) {
		        	throw new Exception('Database insert failed: ' . $table_name);
		        }
			}		
		}
		
		$this->set_categories_count(0, $timestamp);
		
		$cats = \Arlo\Categories::getTree($cat_id, null);
		$this->set_category_depth_level($cats, $timestamp);
		
		$sql = "SELECT MAX(c_depth_level) FROM {$wpdb->prefix}arlo_categories WHERE active = '{$timestamp}'";
		$max_depth = $wpdb->get_var($sql);
		
		$this->set_category_depth_order($cats, $max_depth, 0, $timestamp);
		
		return $items;
	}
	
	private function set_categories_count($cat_id, $timestamp) {
		global $wpdb;
		$cat_id = intval($cat_id);
		
		$where = "
		c_arlo_id = {$cat_id}
		";
		
		if ($cat_id == 0) {
			$where = "
			(
			SELECT 
				COUNT(1) 
			FROM 
				{$wpdb->prefix}arlo_categories
			WHERE 
				wpc.c_arlo_id = wp_arlo_categories.c_parent_id
			AND
				active = '{$timestamp}'
			) = 0			
			";
		}
		
		$sql = "
		SELECT 
			c_arlo_id,
			c_template_num,
			c_parent_id
		FROM 
			{$wpdb->prefix}arlo_categories AS wpc
		WHERE 
			{$where}
		AND
			active = '{$timestamp}'
		";

		$cats = $wpdb->get_results($sql, ARRAY_A);
		if (!is_null($cats)) {
			foreach ($cats as $cat) {
				if ($cat['c_parent_id'] > 0) {
					$sql = "
					UPDATE 
						{$wpdb->prefix}arlo_categories
					SET 
						c_template_num = c_template_num + %d
					WHERE
						c_arlo_id = %d
					AND
						active = '%s'
					";
					$query = $wpdb->query( $wpdb->prepare($sql, $cat['c_template_num'], $cat['c_parent_id'], $timestamp) );
					$this->set_categories_count($cat['c_parent_id'], $timestamp);
				}
			}		
		}
	}
	
	private function set_category_depth_level($cats, $timestamp) {
		global $wpdb;
		
		foreach ($cats as $cat) {
			$sql = "
			UPDATE 
				{$wpdb->prefix}arlo_categories
			SET 
				c_depth_level = %d
			WHERE
				c_arlo_id = %d
			AND
				active = '%s'
			";
			$query = $wpdb->query( $wpdb->prepare($sql, $cat->depth_level, $cat->c_arlo_id, $timestamp) );
			if (is_array($cat->children)) {
				$this->set_category_depth_level($cat->children, $timestamp);
			}
		}
	}
	
	private function set_category_depth_order($cats, $max_depth, $parent_order = 0, $timestamp) {
		global $wpdb;
		$num = 100;
		
		foreach ($cats as $index => $cat) {		
			$order = $parent_order + pow($num, $max_depth - $cat->depth_level) * ($index + 1);

			$sql = "
			UPDATE
				{$wpdb->prefix}arlo_categories
			SET
				c_order = %d
			WHERE
				c_arlo_id = %d
			AND
				active = '%s'	
			";
			
			$query = $wpdb->query( $wpdb->prepare($sql, $order + $cat->c_order, $cat->c_arlo_id, $timestamp) );
			if ($query === false) {
				throw new Exception('Database update failed in set_category_depth_order()');
			} else if (is_array($cat->children)) {
				$this->set_category_depth_order($cat->children, $max_depth, $order, $timestamp);
			}
		}
	}	
		
	private function import_eventtemplatescategoriesitems($timestamp) {
		global $wpdb;
	
		$client = $this->get_api_client();
		
		$sql = "
		SELECT 
			c_arlo_id
		FROM
			{$wpdb->prefix}arlo_categories
		WHERE 
			active = '{$timestamp}'
		";
		$category_ids = $wpdb->get_results($sql, ARRAY_A);
		$category_ids = array_map(function($item) {
			return $item['c_arlo_id'];
		}, $category_ids);
		
		$items = $client->EventTemplateCategoryItems()->getAllTemplateCategoriesItems(
			array(
				'CategoryID',
				'EventTemplateID',
				'SequenceIndex',
			),
			$category_ids
		);
		
		$table_name = "{$wpdb->prefix}arlo_eventtemplates_categories";
		
		if(!empty($items)) {
			foreach($items as $item) {
				$sql = "
				UPDATE
					{$table_name}
				SET
					et_order = %d
				WHERE
					et_arlo_id = %d
				AND
					c_arlo_id = %d
				";
								
				$query = $wpdb->query( $wpdb->prepare($sql, $item->SequenceIndex, $item->EventTemplateID, $item->CategoryID) );
				
		        if ($query === false) {
		        	throw new Exception('Database insert failed: ' . $table_name);
		        }
			}
		}
	}
		
	private function import_cleanup($timestamp) {
		global $wpdb;
		
		// need to delete posts and join rows no longer needed
		
		$tables = array(
			'eventtemplates',
			'contentfields',
			'offers',
			'events',
			'presenters',
			'venues',
			'categories',
                        'events_presenters',
                        'eventtemplates_categories',
                        'eventtemplates_presenters',
		);
                		
		foreach($tables as $table) {
			$table = $wpdb->prefix . 'arlo_' . $table;
			$wpdb->query($wpdb->prepare("DELETE FROM $table WHERE active <> %s", $timestamp));
		}
                
	}

	private function delete_custom_posts($table, $column, $post_type) {
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
		$schedules['minutes_15'] = array(
			'interval' => 900,
			'display' => __('Once every 15 minutes')
		);
		return $schedules;
	}
	
	public static function redirect_proxy() {
		if(!isset($_GET['object_post_type']) || !isset($_GET['arlo_id'])) return;
		
		switch($_GET['object_post_type']) {
			case 'event':
				$event = \Arlo\EventTemplates::get(array('id' => $_GET['arlo_id']), array(), 1);
				
				if(!$event) return;
				
				$post = arlo_get_post_by_name($event->et_post_name, 'arlo_event');
				
				if(!$post) return;
				
				$location = get_permalink($post->ID);
			break;
			
			case 'venue':
				$venue = \Arlo\Venues::get(array('id' => $_GET['arlo_id']), array(), 1);
				
				if(!$venue) return;
				
				$post = arlo_get_post_by_name($venue->v_post_name, 'arlo_venue');
				
				if(!$post) return;
				
				$location = get_permalink($post->ID);
			break;
			
			case 'presenter':
				$presenter = \Arlo\Presenters::get(array('id' => $_GET['arlo_id']), array(), 1);
				
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

	/**
	 * add_pages function.
	 * 
	 * @access public
	 * @return void
	 */
	function add_pages() {
		$pages = array(
			array(
				'name'				=> 'events',
				'title'				=> 'Events',
				'content' 			=> '[arlo_event_template_list]',
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
		
		$settings = get_option('arlo_settings');
	
		foreach($pages as $page) {
			$current_page = get_page_by_title($page['title']);
		
			if(is_null($current_page)) {
				$post_id = wp_insert_post(array(
					'post_type'		=> 'page',
					'post_status'	=> 'draft',
					'post_name' 	=> $page['name'],
					'post_title'	=> $page['title'],
					'post_content' 	=> $page['content']
				));
				
				if(isset($page['child_post_type'])) {
					foreach(self::$post_types as $id => $type) {
						if($page['child_post_type'] == $id) {
							$settings['post_types'][$id]['posts_page'] = $post_id;
						}
					}
				}
			}
		}
	
		// update settings
		update_option('arlo_settings', $settings);
	}
	
	// maybe later...
	/*public function the_posts_action($posts) {
		foreach($posts as &$post) {
		
			switch($post->post_type) {
				case 'arlo_presenter':
					$post = $this->the_post_presenter($post);
				break;
			}
		}
		
		return $posts;
	}
	
	public function the_post_presenter($post) {
		// get associated arlo data here
		
		// save to an arlo object in the post
		$post->presenter_name = 'bro';
		
		// parse the shortcodes/macros and save back to post_content or post_excerpt
		$post->post_excerpt = arlo_replace_macros($post, '[arlo_presenter_name]');
		
		// return post to proceed as normal
		return $post;
	}*/
}

