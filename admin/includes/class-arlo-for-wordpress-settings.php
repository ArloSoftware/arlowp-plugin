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

 use Arlo\Logger;
 use Arlo\VersionHandler;
 use Arlo\NoticeHandler;
 use Arlo\SystemRequirements;
 use Arlo\Utilities;
 use Arlo\Importer\ImportRequest;

class Arlo_For_Wordpress_Settings {

	public function __construct() {
		
		if (!session_id()) {
			session_start();
		}
				
		// allocates the wp-options option key value pair that will store the plugin settings
		register_setting( 'arlo_settings', 'arlo_settings', array(
			'sanitize_callback' => function($input) {
				$region_id = (isset($input['regionid']) && is_array($input['regionid']) ? $input['regionid'] : []);
				$input['regionid'] = array_map('strtoupper', $region_id);
				return $input;
			}
		));		

		$plugin = Arlo_For_Wordpress::get_instance();
		$settings_object = get_option('arlo_settings');

		$message_handler = $plugin->get_message_handler();
		$notice_handler = $plugin->get_notice_handler();		
		$this->plugin_slug = $plugin->get_plugin_slug();
		
		if (isset($_GET['page']) && $_GET['page'] == 'arlo-for-wordpress' && get_option('permalink_structure') != "/%postname%/") {
			add_action( 'admin_notices', array($notice_handler, "permalink_notice") );
		}
		
		add_action( 'admin_notices', array($notice_handler, "global_notices") );

		if (get_option('arlo_plugin_disabled', '0') == '1') {
			add_action( 'admin_notices', array($notice_handler, "plugin_disabled") );
		} else if (get_option('arlo_import_disabled', '0') == '1') {
			add_action( 'admin_notices', array($notice_handler, "import_disabled") );
		}
		
		if(isset($_GET['page']) && $_GET['page'] == 'arlo-for-wordpress') {
			add_action( 'admin_notices', array($notice_handler, "arlo_notices") );

			add_action( 'admin_notices', array($notice_handler, "welcome_notice") );
			
			if (!empty($settings_object['platform_name'])) {
				$show_notice = false;
				foreach (Arlo_For_Wordpress::$post_types as $id => $post_type) {
					if (empty($settings_object['post_types'][$id]['posts_page'])) {
						$show_notice = true;
						break;
					}
				}
				
				if ($show_notice) {
					add_action( 'admin_notices', array($notice_handler, "posttype_notice") );
				}
				
				add_action( 'admin_notices', array($notice_handler, "connected_platform_notice") );
			}
			
			if (isset($_GET['arlo-donwload-sync-log'])) {
				Logger::download_log();
			}
		
			if (isset($_GET['arlo-import'])) {
				if (get_option('arlo_import_disabled', '0') != '1')
					$plugin->get_scheduler()->set_task("import", -1);

				//do_action('arlo_scheduler');
				//$plugin->import();
				//die('import');
				wp_redirect( admin_url( 'admin.php?page=arlo-for-wordpress'));
				exit;
			}
			
			
			if (isset($_GET['arlo-run-scheduler'])) {
				do_action('arlo_scheduler');
				wp_redirect( admin_url( 'admin.php?page=arlo-for-wordpress'));
				exit;				
			}
			
			if (isset($_GET['load-demo'])) {
				$plugin->load_demo();
				wp_redirect( admin_url( 'admin.php?page=arlo-for-wordpress'));
				exit;
			}

			$apply_theme = filter_input(INPUT_GET, 'apply-theme', FILTER_SANITIZE_STRING);
			$wpnonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING);
			$reset = filter_input(INPUT_GET, 'reset', FILTER_SANITIZE_STRING);

			if (!empty($apply_theme) && !empty($wpnonce) && wp_verify_nonce($wpnonce, 'arlo-apply-theme-nonce')) {
				$theme_id = $apply_theme;
				$theme_manager = $plugin->get_theme_manager();
				
				if ($theme_manager->is_theme_valid($theme_id)) {
					$theme_settings = $theme_manager->get_themes_settings();
					$stored_themes_settings = get_option( 'arlo_themes_settings', [] );

					//check if there is already a stored settings for the theme, or need to be reset
					if ((!empty($reset) && $reset == 1) || empty($stored_themes_settings[$theme_id])) {
						$stored_themes_settings[$theme_id] = $theme_settings[$theme_id];
						$stored_themes_settings[$theme_id]->templates = $theme_manager->load_default_templates($theme_id);
					}

					if ($stored_themes_settings[$theme_id]->templates === false && $theme_id === "custom") {
						$stored_themes_settings[$theme_id] = $theme_settings['basic.list'];
						$stored_themes_settings[$theme_id]->templates = $theme_manager->load_default_templates('basic.list');
					}

					if ($stored_themes_settings[$theme_id]->templates !== false) {
						//update the main setting with the stored theme
						foreach ($settings_object['templates'] as $page => $template) {
							if (isset($stored_themes_settings[$theme_id]->templates[$page]['html'])) {
								$settings_object['templates'][$page]['html'] = $stored_themes_settings[$theme_id]->templates[$page]['html'];
							}
						}
						update_option('arlo_settings', $settings_object, 1);
						update_option('arlo_themes_settings', $stored_themes_settings, 1);
						update_option('arlo_theme', $theme_id, 1);
						
						wp_redirect( admin_url('admin.php?page=arlo-for-wordpress#pages') );
					}					
				}
				wp_redirect( admin_url('admin.php?page=arlo-for-wordpress') );
			}

			$urlparts = parse_url(site_url());
			$domain = $urlparts['host'];

			$delete_shortcode = filter_input(INPUT_GET, 'delete-shortcode', FILTER_SANITIZE_STRING);

			if (!empty($delete_shortcode) && !empty($wpnonce) && wp_verify_nonce($wpnonce, 'arlo-delete-shortcode-nonce')) {
				unset( $_COOKIE['arlo-vertical-tab'] );
				setcookie('arlo-vertical-tab', null, -1, '/', $domain);
				$settings_object["delete_shortcode"] = $delete_shortcode;
				update_option('arlo_settings', $settings_object);
				wp_redirect( admin_url('admin.php?page=arlo-for-wordpress#pages') );
			}

			if ( array_key_exists('arlo-new-custom-shortcode', $_COOKIE) ) {
				$custom_shortcode = $_COOKIE['arlo-new-custom-shortcode'];

				// Remove all cookies... 
				// Old code has setup where cookies are very temporary. JS deletes them too. 
				unset( $_COOKIE['arlo-new-custom-shortcode'] );
				setcookie('arlo-new-custom-shortcode', null, -1, '/', $domain);
				unset($_COOKIE['arlo-nav-tab']);
				unset($_COOKIE['arlo-vertical-tab']);
				setcookie('arlo-nav-tab', null, -1, '/', $domain);
				setcookie('arlo-vertical-tab', null, -1, '/', $domain);

				//some shared hosting doesn't delete the cookie properly
				if (!isset($_SESSION['arlo-new-custom-shortcode'])) {
					$_SESSION['arlo-new-custom-shortcode'] = true;
					wp_redirect( admin_url('admin.php?page=arlo-for-wordpress#pages/' . $custom_shortcode) );
					exit;
				} else {
					unset($_SESSION['arlo-new-custom-shortcode']);
				}
			} else if (isset($_SESSION['arlo-new-custom-shortcode'])){
				unset($_SESSION['arlo-new-custom-shortcode']);
			}

			if ( array_key_exists('arlo-nav-tab', $_COOKIE) ) {
				$nav_tab = $_COOKIE['arlo-nav-tab'];
				unset( $_COOKIE['arlo-nav-tab'] );
				setcookie('arlo-nav-tab', null, -1, '/', $domain);

				//some shared hosting doesn't delete the cookie properly
				if (!isset($_SESSION['arlo-nav-tab'])) {
					$_SESSION['arlo-nav-tab'] = true;
					wp_redirect( admin_url('admin.php?page=arlo-for-wordpress#' . $nav_tab) );
					exit;
				} else {
					unset($_SESSION['arlo-nav-tab']);
				}
			}

			if ( array_key_exists('arlo-vertical-tab', $_COOKIE) ) {
				$page = $_COOKIE['arlo-vertical-tab'];
				unset( $_COOKIE['arlo-vertical-tab'] );
				setcookie('arlo-vertical-tab', null, -1, '/', $domain);

				//some shared hosting doesn't delete the cookie properly
				if (!isset($_SESSION['arlo-vertical-tab'])) {
					$_SESSION['arlo-vertical-tab'] = true;
					wp_redirect( admin_url('admin.php?page=arlo-for-wordpress#pages/' . $page) );
					exit;
				} else {
					unset($_SESSION['arlo-vertical-tab']);
				}
			}

			add_action( 'admin_print_scripts', array($this, "arlo_check_current_tasks") );			
		}
		                 
		/*
		 *
		 * General Settings
		 *
		 */

		// create a section for the API Endpoint
		add_settings_section( 'arlo_general_section', __('General Settings', 'arlo-for-wordpress' ), null, 'arlo-for-wordpress' );
		
		// create API Endpoint field                
		add_settings_field(
                        'arlo_platform_name', 
                        '<label for="arlo_platform_name">'.__('Arlo domain', 'arlo-for-wordpress' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'platform_name',
                            'label_for' => 'arlo_platform_name',
                            'before_html' => '<div class="arlo-domain arlo-left ">https://</div>',
							'after_html' => '<div class="arlo-domain arlo-left ">.arlo.co</div><div class="arlo-clear"></div>',
                            )
                );                
                
        if (!empty($settings_object['platform_name'])) {
			// create last import text
			add_settings_field(
                        'arlo_last_import', 
                        '<label for="arlo_last_import">'.__('Last import', 'arlo-for-wordpress' ).'</label>', 
                        array($this, 'arlo_simple_text_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'html' => '<span class="arlo-last-sync-date">' . $plugin->get_importer()->get_last_import_date() . ' UTC</span>&nbsp;&nbsp;' . (get_option('arlo_import_disabled', '0') != '1' ? '<a href="?page=arlo-for-wordpress&arlo-import" class="arlo-sync-button">' . __('Synchronize now', 'arlo-for-wordpress' ) . '</a>' : '' )
                            )
                );        
        }
                
              
		// create price settings dropdown
		add_settings_field('arlo_price_setting', '<label for="arlo_price_setting">'.__('Price shown', 'arlo-for-wordpress' ).'</label>', array($this, 'arlo_price_setting_callback'), $this->plugin_slug, 'arlo_general_section');                
        
                
		// create Free text field
		add_settings_field(
                        'arlo_free_text', 
                        '<label for="arlo_free_text">'.__('"Free" text', 'arlo-for-wordpress' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'free_text',
                            'label_for' => 'arlo_free_text',
                            'default_val' => __('Free', 'arlo-for-wordpress' ),
                            )
                );

		// create No events to show text field
		add_settings_field(
                        'arlo_noevent_text', 
                        '<label for="arlo_noevent_text">'.__('"No events to show" text', 'arlo-for-wordpress' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'noevent_text',
                            'label_for' => 'arlo_noevent_text',
                            'default_val' => __('No events to show', 'arlo-for-wordpress' ),
                            )
                );

		// create No events to show text field
		add_settings_field(
                        'arlo_noeventontemplate_text', 
                        '<label for="arlo_noeventontemplate_text">'.__('No event on a template text', 'arlo-for-wordpress' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'noeventontemplate_text',
                            'label_for' => 'arlo_noeventontemplate_text',
                            'default_val' => __('Interested in attending? Have a suggestion about running this event near you?', 'arlo-for-wordpress' ),
                            )
                );
                
		add_settings_field(
                        'arlo_googlemaps_api_key', 
                        '<label for="arlo_googlemaps_api_key">'.__('GoogleMaps API Key', 'arlo-for-wordpress' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'googlemaps_api_key',
                            'label_for' => 'googlemaps_api_key',
                            )
                );
                
		add_settings_field(
                        'arlo_import_callback_host', 
                        '<label for="arlo_import_callback_host">'.__('Import callback host', 'arlo-for-wordpress' ).' <a href="http://developer.arlo.co/doc/wordpress/settings#import-callback-host" target="_blank"><i class="arlo-icons8 arlo-icons8-help-filled size-16"></i></a></label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'import_callback_host',
                            'label_for' => 'import_callback_host',
                            )
				);
				
				add_settings_field(
					'arlo_taxexempt_tag', 
					'<label for="arlo_taxexempt_tag">'.__('Tax exempt tag', 'arlo-for-wordpress' ).' <a href="https://support.arlo.co/hc/en-gb/articles/115001999286-Set-an-event-as-tax-free" target="_blank"><i class="arlo-icons8 arlo-icons8-help-filled size-16"></i></a></label>', 
					array($this, 'arlo_simple_input_callback'), 
					$this->plugin_slug, 'arlo_general_section', 
					array(
						'id' => 'taxexempt_tag',
						'label_for' => 'taxexempt_tag',
						)
			);
			

		add_settings_field('arlo_filters', null, array($this, 'arlo_filter_settings_callback'), $this->plugin_slug, 'arlo_general_section',  array('id'=>'filters'));    
				

		/*
		 *
		 * Page Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_pages_section', null, array($this, 'arlo_pages_section_callback'), $this->plugin_slug );

		// loop though slug array and create each required slug field
	    foreach(Arlo_For_Wordpress::$templates as $id => $template) {
	    	$name = __($template['name'], 'arlo-for-wordpress' );
			add_settings_field( $id, '<label for="'.$id.'">'.$name.'</label>', array($this, 'arlo_template_callback'), $this->plugin_slug, 'arlo_pages_section', array('id'=>$id,'label_for'=>$id, 'type'=> isset($template["type"]) ? $template["type"] : null) );
		}
		
		/*
		 *
		 * Regions Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_regions_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_regions', null, array($this, 'arlo_regions_callback'), $this->plugin_slug, 'arlo_regions_section', array('id'=>'regions') );
		
		
		/*
		 *
		 * CustomCSS Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_customcss_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_customcss', null, array($this, 'arlo_simple_textarea_callback'), $this->plugin_slug, 'arlo_customcss_section', array('id'=>'customcss', 'after_html' => '<p>Learn how to <a href="https://support.arlo.co/hc/en-gb/articles/115001714006" target="_blank">override existing styles</a> by adding <a href="#" class="arlo-settings-link" id="theme_customcss">Custom CSS</a></p>') );

		
		/*
		 *
		 * Misc Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_misc_section',  __('Miscellaneous', 'arlo-for-wordpress' ), null, 'arlo-for-wordpress' );
		
		add_settings_field(
			'arlo_send_data_setting', 
			'<label for="arlo_send_data">'.__('Allow to send data to Arlo', 'arlo-for-wordpress' ).'</label>', 
			array($this, 'arlo_checkbox_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			['option_name' => 'arlo_send_data']);

		add_settings_field(
			'arlo_keep_settings_on_delete_setting', 
			'<label for="arlo_keep_settings_on_delete">'.__('Keep settings when deleting the plugin (highly recommended)', 'arlo-for-wordpress' ).'</label>', 
			array($this, 'arlo_checkbox_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			['option_name' => 'keep_settings']);

		$sitemap_generator = $plugin->get_sitemap_generator();
		if ($sitemap_generator->seo_plugin_installed()) {
			add_settings_field(
				'arlo_add_categories_to_sitemap', 
				'<label for="arlo_add_categories_to_sitemap">'.__('Add category pages to sitemap', 'arlo-for-wordpress' ).'</label>', 
				array($this, 'arlo_checkbox_callback'), 
				$this->plugin_slug, 
				'arlo_misc_section', 
				['option_name' => 'arlo_add_categories_to_sitemap']);
		}

		add_settings_field(
			'arlo_fragmented_import_setting', 
			'<label for="arlo_import_fragment_size">'.__('Import fragment size (in bytes, max 10 MB)', 'arlo-for-wordpress' ).'</label>', 
			array($this, 'arlo_simple_input_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			array(
				'id' => 'import_fragment_size',
				'label_for' => 'arlo_import_fragment_size',
				'class' => 'arlo-only-numeric',
				'default_val' => ImportRequest::FRAGMENT_DEFAULT_BYTE_SIZE,
				));	

		add_settings_field(
			'arlo_sleep_between_import_tasks_setting', 
			'<label for="arlo_sleep_between_import_tasks">' . sprintf(__('Wait between import tasks (seconds, max %s sec)', 'arlo-for-wordpress' ), \Arlo\Scheduler::MAX_SLEEP_BETWEEN_TASKS).'</label>', 
			array($this, 'arlo_simple_input_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			array(
				'id' => 'sleep_between_import_tasks',
				'label_for' => 'arlo_sleep_between_import_tasks',
				'class' => 'arlo-only-numeric',
				'default_val' => 0,
				));			

		add_settings_field(
			'arlo_disable_ssl_verification_setting', 
			'<label for="arlo_disable_ssl_verification">'.__('Disable SSL verification for import (not recommended)', 'arlo-for-wordpress' ).'</label>', 
			array($this, 'arlo_checkbox_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			['option_name' => 'disable_ssl_verification']);
			
		add_settings_field(
			'arlo_download_log_setting', 
			'<label for="arlo_download_log">'.__('Download log', 'arlo-for-wordpress' ).'</label>', 
			array($this, 'arlo_simple_text_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section',
			['html' => '<a href="?page=arlo-for-wordpress&arlo-donwload-sync-log">Download</a>']);

		add_settings_field(
			'arlo_wp_newsletter', 
			'<label for="arlo_wp_newsletter">'.__('Subscribe to our WP newsletter', 'arlo-for-wordpress' ).'</label>', 
			array($this, 'arlo_simple_text_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section',
			['html' => '<a href="https://confirmsubscription.com/h/r/41B80B5B566BCC0B" target="_blank">Subscribe</a>']);
		
		/*
		 *
		 * Changelog Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_changelog_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_changelog', null, array($this, 'arlo_changelog_callback'), $this->plugin_slug, 'arlo_changelog_section', array('id'=>'welcome') );


		/* Theme Section Settings */ 
		 
		add_settings_section('arlo_theme_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_theme', null, array($this, 'arlo_theme_callback'), $this->plugin_slug, 'arlo_theme_section', array('id'=>'theme') );

		/* Theme Section Settings */ 
		 
		add_settings_section('arlo_support_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_support', null, array($this, 'arlo_support_callback'), $this->plugin_slug, 'arlo_support_section', array('id'=>'support') );

		/* System requirements */

		add_settings_section('arlo_systemrequirements_section',  __('System requirements', 'arlo-for-wordpress' ), null, 'arlo-for-wordpress' );
		add_settings_field( 'arlo_systemrequirements', null, array($this, 'arlo_systemrequirements_callback'), $this->plugin_slug, 'arlo_systemrequirements_section', array('id'=>'systemrequirements') );
	}

	/*
	 *
	 * SECTION CALLBACKS
	 *
	 */


	function arlo_pages_section_callback() {
		echo '
	    		<script type="text/javascript"> 
	    			var arlo_templates = ' . json_encode(\Arlo_For_Wordpress::arlo_template_source()) . ';' . '
	    			var arlo_shortcodes = ' . json_encode(array_keys(Arlo_For_Wordpress::$templates)) . ';' .
	    		'</script>		
		';
	}
	
	/*
	 *
	 * FIELD CALLBACKS
	 *
	 */
	 
	function arlo_simple_textarea_callback($args) {
	    $settings_object = get_option('arlo_settings');
	    $val = (isset($settings_object[$args['id']])) ? esc_attr($settings_object[$args['id']]) : (!empty($args['default_val']) ? $args['default_val'] : '' );
	    
	    $html = '';
	        
        if (!empty($args['before_html'])) {
            $html .= $args['before_html'];
        }
            
	    $html .= '<textarea cols="70" rows="30" class="' . (!empty($args['class']) ? $args['class'] : "") . '" id="arlo_'.$args['id'].'" name="arlo_settings['.$args['id'].']" >'.$val.'</textarea>';
            
        if (!empty($args['after_html'])) {
            $html .= $args['after_html'];
        }
            
	    echo $html;
	}	 
        
	function arlo_price_setting_callback() {
		$settings_object = get_option('arlo_settings');
		$setting_id = 'price_setting';
		
		$output = '<div id="'.ARLO_PLUGIN_PREFIX.'-price-setting" class="cf">';
		$output .= '<select name="arlo_settings['.$setting_id.']">';            
		
		$val = (isset($settings_object[$setting_id])) ? esc_attr($settings_object[$setting_id]) : ARLO_PLUGIN_PREFIX . '-exclgst';
		
		foreach(Arlo_For_Wordpress::$price_settings as $key => $value) {
		    $key = ARLO_PLUGIN_PREFIX . '-' . $key;
		    $selected = $key == $val ? 'selected="selected"' : '';
		    $output .= '<option ' . $selected . ' value="'.$key.'" >'.$value.'</option>';
		}
		
		$output .= '</select></div>';
		
		echo $output;
	}
	
	function arlo_filter_settings_callback() {
		$filter_actions = [
			'rename' => __('Rename', 'arlo-for-wordpress'),
			'exclude' => __('Exclude', 'arlo-for-wordpress')
		];
		
		$output = '<div id="'.ARLO_PLUGIN_PREFIX.'-filter-terminology" class="cf">';

		$output .= '<h3>Filter terminology</h3>';

		$available_page_filters = array(
			'generic' => array(
				'name' => 'Generic',
				'filters' => array(
					'delivery' => 'Delivery', 
				)
			)
		);
				
		$output .= $this->arlo_output_filter_editor('arlo_filter_settings', $available_page_filters, 'generic', 'generic', $filter_actions, '', 'arlo-always-visible');

		$output .= '</div>';

		echo $output;
	}

	function get_select_filter_options(&$item, $key, $options = []) {
		if (empty($item['string']) && empty($item['value'])) {
			$item = array(
				'string' => $item,
				'value' => $key,
				'id' => $key
			);
		}

		extract($options);

		$value = (isset($value_field) && isset($item[$value_field]) ? $item[$value_field] : $item["string"]); 
		
		$selected = (isset($selected_value) && ($value == htmlentities($selected_value) || !strcmp($value, $selected_value)) ? ' selected="selected" ' : '');

		$item = "<option value='" . esc_attr($value) . "' " . $selected . ">" . esc_html($item["string"]) . "</option>";
	}

	function arlo_checkbox_callback($args) {
		if (empty($args['option_name'])) return;
		
		$option_name = $args['option_name'];
		$settings_object = get_option('arlo_settings');
				
		$output = '<div id="'.ARLO_PLUGIN_PREFIX.'-' . $option_name . '" class="cf">';
		$output .= '<input type="checkbox" value="1" name="arlo_settings['.$option_name.']" id="' . $option_name . '" ' . (isset($settings_object[$option_name]) && $settings_object[$option_name] == '1' ? 'checked="checked"' : '') . '>';
		
		$output .= '</div>';
		
		echo $output;
	}
	
        
	function arlo_simple_input_callback($args) {
	    $settings_object = get_option('arlo_settings');
	    $val = (isset($settings_object[$args['id']])) ? esc_attr($settings_object[$args['id']]) : (!empty($args['default_val']) ? $args['default_val'] : '' );
	    
	    $html = '';
	        
        if (!empty($args['before_html'])) {
            $html .= $args['before_html'];
        }
            
	    $html .= '<input type="text" class="' . (!empty($args['class']) ? $args['class'] : "") . '" id="arlo_'.$args['id'].'" name="arlo_settings['.$args['id'].']" value="'.$val.'" />';
            
        if (!empty($args['after_html'])) {
            $html .= $args['after_html'];
        }
            
	    echo $html;
	}  	
	
	function arlo_simple_text_callback($args) {
	    $html = '';
	        
        if (!empty($args['html'])) {
            $html .= $args['html'];
        }

	    echo $html;
	}

	function arlo_template_callback($args) {
		$id = $args['id'];
		$type = isset($args['type']) ? $args['type'] : $args['id'];
		$settings_object = get_option('arlo_settings');
		$is_new_shortcode_page = $id == 'new_custom';
		$is_custom_shortcode = isset( $args['type'] );

		echo '<h3>';
		if ($is_new_shortcode_page) {
			echo 'New custom shortcode';
		} else {
			echo sprintf(__('%s page', 'arlo-for-wordpress' ), Arlo_For_Wordpress::$templates[$id]['name']);

			if ($is_custom_shortcode) {
				echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&delete-shortcode=' . urlencode($id)), 'arlo-delete-shortcode-nonce') . '"
				class="red arlo-delete-button"><i class="arlo-icons8 arlo-icons8-cancel size-16"></i> Delete</a>';
			}
		}
		echo '</h3>';

    	if ($is_custom_shortcode) {
			$type_name = ($type == 'events' ? 'catalogue' : $type);
			echo '
			<div class="arlo-label"><label>' .  __("Shortcode type", 'arlo-for-wordpress' ) . '</label></div>
			<div class="arlo-field">
				<span>'.ucfirst($type_name).' '.__('page', 'arlo-for-wordpress' ).'</span>
			</div><br><br>';
    	}

		/*
		HACK because the keys in the $post_types arrays are bad, couldn't change because backward comp.
		*/
		
		if (!in_array($id, array('event', 'presenter', 'venue'))) {
			$post_type_id = in_array($id, array('events','presenters', 'venues')) ? substr($id, 0, strlen($id)-1) : $id;
		}

    	if (!empty($post_type_id) && !empty(Arlo_For_Wordpress::$post_types[$post_type_id])) {
    		$post_type = Arlo_For_Wordpress::$post_types[$post_type_id];
		    $val = isset($settings_object['post_types'][$post_type_id]['posts_page']) ? esc_attr($settings_object['post_types'][$post_type_id]['posts_page']) : 0;
	
			$select = wp_dropdown_pages(array(
				'id'				=> 'arlo_'.$post_type_id.'_posts_page',
			    'selected'         	=> $val,
			    'echo'             	=> 0,
			    'name'             	=> 'arlo_settings[post_types]['.$post_type_id.'][posts_page]',
			    'show_option_none'	=> '-- Select --',
			    'option_none_value'	=> 0
			));

			$shortcode_documentation_url = "https://developer.arlo.co/doc/wordpress/shortcodes/globalshortcodes#" . str_replace( "]", "", str_replace( "[", "", Arlo_For_Wordpress::$templates[$type]['shortcode'] ) );

			echo '
			<div class="arlo-label"><label>' .  __("Host page", 'arlo-for-wordpress' ) . '</label></div>
			<div class="arlo-field">
				<span class="arlo-page-select">' . $select . '</span>
				<span class="arlo-gray arlo-inlineblock">' . sprintf(__('Page must contain the %s shortcode. <a href="%s" target="_blank">Shortcode customisation options.</a>', 'arlo-for-wordpress' ), Arlo_For_Wordpress::$templates[$id]['shortcode'], $shortcode_documentation_url) . '</span>
			</div>';
    	}

    	if ($is_new_shortcode_page) {
    		$this->arlo_output_new_shortcode_page();
    	} else {
    		$this->arlo_output_template_page($settings_object, $id, $type);
    	}
	}

	function arlo_output_template_page($settings_object, $id, $type) {
		$val = isset($settings_object['templates'][$id]['html']) ? $settings_object['templates'][$id]['html'] : '';
	   
	    $this->arlo_reload_template($id);

	    if ( isset(Arlo_For_Wordpress::$templates[$id]['shortcode']) ) {
		    echo '<div class="arlo-label arlo-full-width">
	    		<label>'
	    		. sprintf(__('%s page ', 'arlo-for-wordpress' ), Arlo_For_Wordpress::$templates[$id]['name'])
	    		. 'shortcode <span class="arlo-gray">' . Arlo_For_Wordpress::$templates[$id]['shortcode'] . '</span>'
	    		. ' content
	    		</label>
	    	</div>';
		}
		
		echo '
		<div class="wp-editor-wrap">
		';

		wp_editor($val, $id, array('textarea_name'=>'arlo_settings[templates]['.$id.'][html]','textarea_rows'=>'20'));

		echo '
		<div class="cf"></div>
		<h2>' . __('Page filtered by ', 'arlo-for-wordpress' ) . '</h2>
			' . $this->arlo_output_filter_editor('arlo_page_filter_settings', Arlo_For_Wordpress::$available_page_filters, $type, $id, Arlo_For_Wordpress::$page_filter_options, 'page', 'arlo-always-visible') . '
		</div>';

		

	}

	function arlo_output_new_shortcode_page() {
		echo '
		<div class="arlo-label"><label>' .  __("Shortcode name", 'arlo-for-wordpress' ) . '</label></div>
		<div class="arlo-field">
			<span class="arlo-new-custom-shortcode-name arlo-gray"> [arlo_<input type="text" name="arlo_settings[new_custom_shortcode]" class="arlo-inline-input" maxlength="15">] </span>
		</div><br><br>';

		echo '
		<div class="arlo-label"><label>' .  __("Shortcode type", 'arlo-for-wordpress' ) . '</label></div>
		<div class="arlo-field">
			<select name="arlo_settings[new_custom_shortcode_type]" class="arlo-new-custom-shortcode-type">';
			echo '<option value=""></option>';
			foreach (Arlo_For_Wordpress::$shortcode_types as $shortcode_type => $shortcode_type_name) {
				echo '<option value="'.$shortcode_type.'">'.$shortcode_type_name.'</option>';
			}
		echo '</select>
		</div><br><br>';
		echo '
		<div class="arlo-label"><br></div>
		<div class="arlo-field">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
		</div>';
	}
	
	function arlo_reload_template($template) {
		$selected_theme_id = get_option('arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME);

		if ($selected_theme_id != 'custom') {
			echo '
				<div class="arlo-label">
					<label>'. __('Template', 'arlo-for-wordpress' ) . '</label>
				</div>
				<div class="arlo-field">
					<div class="' . ARLO_PLUGIN_PREFIX . '-reload-template"><a>' . __('Reload original template', 'arlo-for-wordpress' ) . '</a></div>
				</div>
				<div class="cf"></div>';
		}
	}

	function arlo_output_filter_editor($setting_name, $available_page_filters, $filter_type, $filter_group, $actions = [], $id_prefix = '', $class = '') {
		$filters_settings_html = '';
		$filter_settings = get_option($setting_name, array());

		//hack, because the key in the templates are not the same as in the filters
		$filter_type = ($filter_type == 'events' ? 'template' : $filter_type);

		$filter_group_values = (isset($available_page_filters[$filter_type]) ? $available_page_filters[$filter_type] : []);

		$available_filters = [];
		if (isset($filter_group_values['filters']) && is_array($filter_group_values['filters'])) {;
			$available_filters = $filter_group_values['filters'];
		}
		
		$filters_settings_html .= '<div id="arlo-' . (!empty($id_prefix) ? $id_prefix . '-' : '') . $filter_group . '-filters" class="arlo-filter-group ' . $class . '">';
		$import_id = Arlo_For_Wordpress::get_instance()->get_importer()->get_current_import_id();

		if (count($available_filters) && !empty($import_id)) {
			
			foreach($available_filters as $filter_key => $filter) {
				$filter_options = \Arlo\Shortcodes\Filters::get_filter_options($filter_key, $import_id);
				$default_filter_options = $filter_options;
				array_walk($default_filter_options, 'self::get_select_filter_options', ['value_field' => 'id']);

				$expand_filter = (!empty($filter_settings[$filter_group][$filter_key]) && count($filter_settings[$filter_group][$filter_key])) 
								|| (!empty($filter_settings["hiddenfilters"][$filter_group][$filter_key])) 
								|| (!empty($filter_settings["showonlyfilters"][$filter_group][$filter_key]));
	
								$filters_settings_html .= '
								<div class="arlo-filter-settings ' . ($expand_filter ? 'filter-section-expanded' : '') . '">
									<a class="arlo-filter-section-toggle"><h2>' . __($filter,'arlo-for-wordpress') . '</h2></a>
									<div id="arlo-filter-empty">
										<ul>
											' . $this->arlo_output_filter_actions($actions, $setting_name, $default_filter_options, $filter_group, $filter_key, '', 'setting_id') . '
										</ul>
									</div>
								
									<ul class="arlo-available-filters" style="display: ' . ($expand_filter ? 'block' : 'none') . '">
									';

				if (is_array($filter_settings)) {

					if (!empty($filter_settings[$filter_group][$filter_key]) && count($filter_settings[$filter_group][$filter_key])) {
						foreach($filter_settings[$filter_group][$filter_key] as $old_value => $new_value) {
							/* rename entries */								
							$filter_rename_setting_options = $filter_options;
							array_walk($filter_rename_setting_options, 'self::get_select_filter_options', ['selected_value' => $old_value, 'value_field' => 'id']);
							$filters_settings_html .= $this->arlo_output_filter_actions($actions, $setting_name, $filter_rename_setting_options, $filter_group, $filter_key, $new_value, rand(), 'rename');
						}
					}
	
					if (!empty($filter_settings["hiddenfilters"][$filter_group][$filter_key])) {
						foreach($filter_settings["hiddenfilters"][$filter_group][$filter_key] as $old_value) {
							/* hidden/exclude entries */	
							$filter_hidden_setting_options = $filter_options;
							array_walk($filter_hidden_setting_options, 'self::get_select_filter_options', ['selected_value' => $old_value, 'value_field' => 'id']);
							$filters_settings_html .= $this->arlo_output_filter_actions($actions, $setting_name, $filter_hidden_setting_options, $filter_group, $filter_key, '', rand(), 'exclude');
						}
					}
	
					if (!empty($filter_settings["showonlyfilters"][$filter_group][$filter_key])) {
						foreach($filter_settings["showonlyfilters"][$filter_group][$filter_key] as $old_value) {
							/* show only entries */
							$filter_hidden_setting_options = $filter_options;
							array_walk($filter_hidden_setting_options, 'self::get_select_filter_options', ['selected_value' => $old_value, 'value_field' => 'id']);
							$filters_settings_html .= $this->arlo_output_filter_actions($actions, $setting_name, $filter_hidden_setting_options, $filter_group, $filter_key, '', rand(), 'showonly');
						}
					}				
				}

				/* Empty entry */
				array_walk($filter_options, 'self::get_select_filter_options', ['value_field' => 'id']);
				$filters_settings_html .= $this->arlo_output_filter_actions($actions, $setting_name, $filter_options, $filter_group, $filter_key, '', rand());
	
				$filters_settings_html .= '</ul></div>';						
			}
		} else {
			$filters_settings_html .= '<div>' . __('No filter option available', 'arlo-for-wordpress') . '</div>';
		}

		$filters_settings_html .= '</div>';

		return $filters_settings_html;
	}

	function arlo_output_filter_actions($actions,  $setting_name, $filter_options_array = [], $filter_group, $filter_key, $filter_new_value, $settings_id, $selected_action = '') {
		$option_html = '
		<li>';
		
		if (is_array($actions) && count($actions)) {
			$actions = array_merge(['' => __('Select an action', 'arlo-for-wordpress')], $actions);
			$option_html .= '
			<div class="arlo-filter-action">
				<select name="arlo_settings[' . $setting_name . ']['.$filter_group.']['.$filter_key.'][' . $settings_id . '][filteraction]">
				';
				
			foreach ($actions as $value => $text) {
				$selected = ($value == $selected_action ? 'selected="selected"' : '');
				$option_html .= sprintf('<option value="%s" %s>%s</option>', esc_attr($value), $selected, esc_html($text));
			}
						
			$option_html .= '
					</select>
				</div>';
		}

		$option_html .= '
			<div class="arlo-filter-controls">
				<i class="arlo-icons8-minus arlo-icons8 size-21"></i>
				<i class="arlo-icons8-plus arlo-icons8 size-21"></i>
			</div>

			<div class="arlo-filter-old-value">
				<select name="arlo_settings[' . $setting_name . '][' . $filter_group . '][' . $filter_key . '][' . $settings_id . '][filteroldvalue]">
					<option value="">Select an option</option>' .
						implode($filter_options_array, '')
				. '</select>
			</div>

			<div class="arlo-filter-new-value" ' . (!empty($filter_new_value) ? 'style="display: block"' : '') . '><input type="text" name="arlo_settings[' . $setting_name . '][' . $filter_group . '][' . $filter_key . '][' . $settings_id . '][filternewvalue]" value="' . $filter_new_value . '" maxlength="64"></div>
		</li>';

		return $option_html;
	}
	
	function arlo_check_current_tasks() {
		global $wpdb;
		
		$plugin = Arlo_For_Wordpress::get_instance();
		$scheduler = $plugin->get_scheduler();
		
		$next_immediate_task = $scheduler->get_next_immediate_tasks();
		$next_immediate_task_ids = [];
		
		$running_task = $scheduler->get_running_tasks();
		$running_task_ids = [];
		
		$running_task = array_merge($running_task, $scheduler->get_paused_tasks());
		
		foreach ($next_immediate_task as $task) {
			$next_immediate_task_ids[] = $task->task_id;
		}
		
		foreach ($running_task as $task) {
			$running_task_ids[] = $task->task_id;
		}		
		
		echo "
		<script type='text/javascript'>
			var ArloImmediateTaskIDs = " . wp_json_encode($next_immediate_task_ids) . ";
			var ArloRunningTaskIDs = " . wp_json_encode($running_task_ids) . ";
		</script>
		";
		
	}
	                                                                                                                                                      	
	function arlo_regions_callback($args) {
		$regions = get_option('arlo_regions', array());
		
	    echo '
	    <h3>Regions</h3>
	    <p>Please specify your available regions in Arlo.</p>
		<p><strong>Please note, when you change the regions, you have to re-synchronize the data.</strong></p>
	    <div id="arlo-regions-header">
			<div class="arlo-order-number">#</div>
			<div class="arlo-region-id">Region ID</div>
			<div class="arlo-region-name">Region name</div>
	    </div>
	    <div id="arlo-region-empty">
			<ul>
				<li>
					<div class="arlo-order-number">1.</div>
					<div class="arlo-region-id"><input type="text" name="arlo_settings[regionid][]"></div>
					<div class="arlo-region-controls">
						<i class="arlo-icons8-minus arlo-icons8 size-21"></i>
						<i class="arlo-icons8-plus arlo-icons8 size-21"></i>
					</div>			
					<div class="arlo-region-name"><input type="text" name="arlo_settings[regionname][]"></div>
				</li>
			</ul>	    	
	    </div>
		<ul id="arlo-regions">';
		$key = 0;
		if (is_array($regions) && count($regions)) {
			foreach($regions as $regionid => $regionname) {
				echo '<li>
					<div class="arlo-order-number">' . (++$key) . '</div>
					<div class="arlo-region-id"><input type="text" name="arlo_settings[regionid][]" value="'.$regionid.'"></div>
					<div class="arlo-region-controls">
						<i class="arlo-icons8-minus arlo-icons8 size-21"></i>
						<i class="arlo-icons8-plus arlo-icons8 size-21"></i>
					</div>			
					<div class="arlo-region-name"><input type="text" name="arlo_settings[regionname][]" value="' . $regionname . '"></div>
				  </li>
				 ';
			}
		}
		
		echo '<li>
			<div class="arlo-order-number">' . ($key + 1) . '</div>
			<div class="arlo-region-id"><input type="text" name="arlo_settings[regionid][]"></div>
			<div class="arlo-region-controls">
				<i class="arlo-icons8-minus arlo-icons8 size-21"></i>
				<i class="arlo-icons8-plus arlo-icons8 size-21"></i>
			</div>			
			<div class="arlo-region-name"><input type="text" name="arlo_settings[regionname][]"></div>
		  </li>
		</ul>
		
		<p>For more information, please visit our <a href="http://developer.arlo.co/doc/wordpress/settings#regions" target="_blank">documentation</a></p>
	    ';
	} 		
	
	function arlo_changelog_callback($args) {
		
	    echo '
	    <h3>What\'s new in this release</h3>
		<p><strong>If you are experiencing problems after an update, please deactivate and re-activate the plugin and re-synchronize the data.</strong></p>
		
		<h4>Version ' .  VersionHandler::VERSION . '</h4>
		<p>
			<ul class="arlo-whatsnew-list">
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/templateshortcodes/#arlo_breadcrumbs">[arlo_breadcrumbs]</a> shortcode</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/venuerelated#arlo_venue_locationname">[arlo_venue_locationname]</a> shortcode</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/venuerelated#arlo_venue_events_link">[arlo_venue_events_link]</a> shortcode</li>
				<li>Event template page can now show a single event</li>
				<li>Tested up to Wordpress 5.0</li>
				<li>End date now uses correct timezone offset</li>
				<li>Automatic navigation to new Page after creation</li>
				<li>Timezone codes on Event template page</li>
				<li>Fixed initial sync with platforms that have no Venues</li>
				<li>Starter template Venue Schedule/Upcoming links now work</li>
				<li>Next running date removes only current year</li>
				<li>Preview button for all themes</li>
				<li>Event list item now has option to no close unopened &lt;ul&gt; it expects.</li>
				<li> - If you use any of the Starter templates we recommend updating your Event page to include `within_ul="false"` in the `<a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_list_item">[arlo_event_list_item]</a>` shortcode. If you have not customised you templates you can Reset your Starter theme.</li>
				<li>Jazz Register button text is now visible on hover</li>
				<li>Jazz search button aligment centered</li>
				<li>Failed redirects 404 instead of 301</li>
				<li>Check set_time_limit isn\'t disabled before use</li>
			</ul>
		</p>

		<h4>Version 4.0</h4>
		<p>
			<ul class="arlo-whatsnew-list">	  
				<li>New WordPress <a href="https://wordpress.org/plugins/arlo-training-and-event-management-system/" target="_blank">minimum version requirement</a> of 4.7</li>
				<li>New system requirements now checking against WordPress 4.7 and PHP 5.5</li>
				<li>"Download log" file structure ID changed for Id</li>
				<li>New "<a href="https://support.arlo.co/hc/en-gb/articles/360020232631" target="_blank">Starter Hero</a>" Arlo Theme</li>
				<li>Manual plugin upload process available from this version</li>
				<li>New "Keep settings" option for plugin deletion</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_event_template_hero_image" target="_blank">[arlo_event_template_hero_image]</a> shortcode</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_event_template_list_image" target="_blank">[arlo_event_template_list_image]</a> shortcode</li>
				<li>Security improvements</li>
				<li>Remove now unused "import" directory</li>
				<li>"Download log" was showing an error when open in Excel</li>
			</ul>
		</p>

		<h4>Version 3.9</h4>
		<p>
			<ul class="arlo-whatsnew-list">	  
				<li>Improvement of the Starter themes</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_event_template_advertised_price" target="_blank">[arlo_event_template_advertised_price]</a> shortcode</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_event_template_advertised_presenters" target="_blank">[arlo_event_template_advertised_presenters]</a> shortcode</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_event_template_credits" target="_blank">[arlo_event_template_credits]</a> shortcode</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_haslimitedplaces" target="_blank">[arlo_event_haslimitedplaces]</a> shortcode</li>
				<li>Make Timezone selector <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_timezones" target="_blank">[arlo_timezones]</a> available on Upcoming page</li>
				<li>Better support for load balanced architectures</li>
				<li>Venue map not showing on Venue page</li>
				<li>Multiple instance of upcoming widget are conflicting</li>
				<li>Upcoming widget do not keep event/template tag after import</li>
				<li>WordPress Menus and Custom Links including html in their text field are html-escaped</li>
				<li>Best event price (From) includes Events hidden from website</li>
				<li>Glitch on expand button on Starter theme</li>
				<li>Discount should not display on Online Activities if only applied to events</li>
			</ul>
		</p>

		<h4>Version 3.8.1</h4>
		<p>
			<ul class="arlo-whatsnew-list">	  
				<li>Load balanced architectures do not allow the import to run correctly</li>
			</ul>
		</p>

		<h4>Version 3.8</h4>
		<p>
			<ul class="arlo-whatsnew-list">	  
				<li>External URL support for Arlo&#39;s sitemap</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_summary" target="_blank">[arlo_event_summary]</a> shortcode to display sessions description</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_event_template_register_private_interest" target="_blank">[arlo_event_template_register_private_interest]</a> shortcode for private lead form link</li>
				<li>New <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_suggest_private_datelocation" target="_blank">[arlo_suggest_private_datelocation]</a> shortcode for private lead form link</li>
				<li>New "order" attribute for <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_price" target="_blank">[arlo_event_price]</a></li>
				<li>New "period" option to show end date for <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_next_running" target="_blank">[arlo_event_next_running]</a></li>
				<li>Year is added after the month used as a delimiter on the Upcoming page</li>
				<li>Add discount and places remaining corner labels on Schedule page of the <a href="https://support.arlo.co/hc/en-gb/articles/360000311006-Theme-Starter" target="_blank">Starter template theme</a></li>
				<li>Upcoming widget is not working when tags are specified</li>
				<li><a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_isfull" target="_blank">[arlo_event_isfull]</a> now working on sessions too</li>
				<li><a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_duration" target="_blank">[arlo_event_duration]</a> now showing session duration on sessions</li>
				<li>Event of 6 hours X minutes is not showing 1 day</li>
				<li>Other WordPress plugin errors are sometimes stopping the import</li>
			</ul>
		</p>

		<a href="https://wordpress.org/plugins/arlo-training-and-event-management-system/#developers" target="_blank">More change log</a>
	    ';
	}
	
	function arlo_systemrequirements_callback () {
		$good = '<i class="arlo-icons8-checkmark arlo-icons8 size-21 green"></i>';
		$bad = '<i class="arlo-icons8-cancel arlo-icons8 size-21 red"></i>';

		echo '
		<table class="arlo-system-requirements-table">
			<tr>
				<th class="arlo-required-setting-icon"></th>
				<th class="arlo-required-setting">Setting</th>
				<th class="arlo-required-setting-value">Expected</th>
				<th class="arlo-required-setting-value">Current</th>
			</tr>
		';

		foreach (SystemRequirements::get_system_requirements() as $req) {
			$current_value = $req['current_value']();
			$check = $req['check']($current_value, $req['expected_value']);

			$good_or_bad = ($check === null ? '' : ($check ? $good : $bad));
			$green_or_red = ($check === null ? '' : ($check ? 'green' : 'red'));

			echo '
			<tr>
				<td class="arlo-required-setting-icon">' . $good_or_bad . '</td>
				<td class="arlo-required-setting">' . $req['name'] . '</td>
				<td class="arlo-required-setting-value">' . $req['expected_value'] . '</td>
				<td class="arlo-required-setting-value ' . $green_or_red . '">' . $current_value . '</td>
			</tr>			
			';
		}

		echo '</table>';
	} 

	function arlo_support_callback () {
		echo '
		<h3>' . __('Support', 'arlo-for-wordpress') . '</h3>
		<p>
			<ul class="arlo-whatsnew-list">
				<li><a href="http://developer.arlo.co/doc/wordpress/index" target="_blank">Arlo for WordPress developer documentation</a> - Technical documentation on the setup and configuration of the Arlo for WordPress plugin. </li>
				<li><a href="https://support.arlo.co/hc/en-gb/sections/202320663-Website-Integration-Information" target="_blank">General Arlo website integration documentation</a> - General documentation on Arlo website integration including checkout and registration page options, custom URLs and the Arlo web team’s services.   </li>
				<li><a href="https://support.arlo.co/hc/en-gb/sections/202320703-WordPress-Plugin" target="_blank">Arlo for WordPress support  documentation</a> - Documentation on Arlo for WordPress plugin including the synchronisation between Arlo and WordPress, FAQ’s and troubleshooting.  </li>
				<li><a href="https://support.arlo.co/hc/en-gb/sections/115000452543-WordPress-Control-Themes" target="_blank">Arlo for WordPress control themes</a> - Documentation on the available Arlo for WordPress control themes and customisation options.</li>
				<li><a href="https://support.arlo.co/hc/en-gb/articles/115004910003-Website-WordPress-test-cases" target="_blank">Arlo for WordPress test plan</a> - Important test script to run through before your site goes live
			</ul>
		</p>
		';
	} 

	function arlo_theme_callback($args) {
		$plugin = Arlo_For_Wordpress::get_instance();
		$theme_manager = $plugin->get_theme_manager();

		$themes = $theme_manager->get_themes_settings();

		$selected_theme_id = get_option('arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME);

	    echo '
	    <h3>Select Arlo for WordPress control theme </h3>
		<p>' . __('Arlo powered pages will be updated to match the Arlo control theme selected.', 'arlo-for-wordpress' ) . '</p>
		<p> 
			Learn about themes <a href="javascript:;" data-fancybox="modal" data-src="#arlo-themes-for-designers">"For designers"</a>.
			Learn how to <a href="https://support.arlo.co/hc/en-gb/articles/115001714006" target="_blank">override existing styles</a> by adding <a href="#" class="arlo-settings-link" id="theme_customcss">Custom CSS</a>
		</p>
		<ul class="arlo-themes">';
		foreach ($themes as $theme_num => $theme_data) {
			$desc = $images = [];

			if (!empty($theme_data->images) && is_array($theme_data->images)) {
				foreach ($theme_data->images as $image) {
					$images[] = '<img src="' . $theme_data->url . $image . '">';
				}
			}

			$overlay = '
					<div class="arlo-theme-desc-text">
						' . (!empty($theme_data->icon) ? '<div class="arlo-theme-icon"><i class="arlo-icons8 ' . $theme_data->icon . ' size-48 "></i></div>' : '') . '
						<div class="arlo-theme-name">' . htmlentities(strip_tags($theme_data->name)) . '</div>
						<div class="arlo-theme-description">' . $theme_data->description . '</div>
						' . (!empty($theme_data->forDesigners) && $theme_data->forDesigners ? '<div class="arlo-theme-for-designer">Learn about themes <a href="javascript:;" data-fancybox="modal" data-src="#arlo-themes-for-designers">"For designers"</a></div>' : '') . '
					</div>
				';

			$desc = array_merge($desc, $images);

			if (!count($desc)) { 
				$desc[] = $overlay;
			}

			echo '
			<li class="arlo-theme">
				<div class="arlo-theme-desc ' . (count($images) == 0 ? 'arlo-theme-inverse' : '') . '">
					' . (!empty($theme_data->forDesigners) && $theme_data->forDesigners ? '<div class="arlo-themes-developer-banner" data-fancybox="modal" data-src="#arlo-themes-for-designers">For<br />designers</div>' : '') . '
					
					' . $desc[0] . '
					<div class="arlo-theme-overlay">' . $overlay  . '</div>
				</div>
				<div class="arlo-theme-information">
					<div class="arlo-theme-name">' . htmlentities(strip_tags($theme_data->name)) . '</div>
					<div class="arlo-clear"></div>
				</div>
				<div class="arlo-theme-buttons">
					<ul>
					' . ($selected_theme_id == $theme_data->id ? '
						<li class="arlo-theme-current">Current</li>
					':'
						<li><a class="theme-apply" href="' . wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&apply-theme=' . urlencode($theme_data->id)), 'arlo-apply-theme-nonce') . '">' . __('Apply', 'arlo-for-wordpress') . '</a></li>
					') .
						( $theme_data->id != 'custom' ? '<li><a class="theme-apply theme-reset" href="' . wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&apply-theme=' . urlencode($theme_data->id) . '&reset=1'), 'arlo-apply-theme-nonce') . '">' . ($selected_theme_id == $theme_data->id ? __('Reset', 'arlo-for-wordpress') : __('Apply & Reset', 'arlo-for-wordpress')) . '</a></li>' : '') . '
					' . (!empty($theme_data->demoUrl) ? '<li><a href="' . $theme_data->demoUrl . '" target="_blank">' . __('Preview', 'arlo-for-wordpress' ) . '</a></li>' : '' ) . '
					</ul>
				</div>
			</li>';
		}
		echo '	
		</ul>
		<div class="hidden">
			<div id="arlo-themes-for-designers">
				<p>
				' . __('Themes that include "For designers" label provide maximum flexibility as they inherit some of the main website’s styles. They will however require additional work by a web designer to fix any inadvertent styling issues.', 'arlo-for-wordpress' ) . '
				<br /> <a href="https://support.arlo.co/hc/en-gb/articles/115001738403" target="_blank">' . __('Learn more', 'arlo-for-wordpress') . '</a></p>
			</div>
		</div>
	    ';
	}	
}
