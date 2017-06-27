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
		register_setting( 'arlo_settings', 'arlo_settings' );		

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

			if (!empty($_GET['apply-theme']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'arlo-apply-theme-nonce')) {
				$theme_id = $_GET['apply-theme'];
				$theme_manager = $plugin->get_theme_manager();
				
				if ($theme_manager->is_theme_valid($theme_id)) {
					$theme_settings = $theme_manager->get_themes_settings();
					$stored_themes_settings = get_option( 'arlo_themes_settings', [] );

					//check if there is already a stored settings for the theme, or need to be reset
					if ($_GET['reset'] == 1 || empty($stored_themes_settings[$theme_id])) {
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
							$settings_object['templates'][$page]['html'] = $stored_themes_settings[$theme_id]->templates[$page]['html'];
						}
						update_option('arlo_settings', $settings_object, 1);
						update_option('arlo_themes_settings', $stored_themes_settings, 1);
						update_option('arlo_theme', $theme_id, 1);
						
						wp_redirect( admin_url('admin.php?page=arlo-for-wordpress#pages') );
					}					
				}
				wp_redirect( admin_url('admin.php?page=arlo-for-wordpress') );
			}
						
			add_action( 'admin_notices', array($notice_handler, "welcome_notice") );
			
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
                            'default_val' => __('Interested in attending? Have a suggestion about running this course near you?', 'arlo-for-wordpress' ),
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
                        '<label for="arlo_import_callback_host">'.__('Import callback host', 'arlo-for-wordpress' ).' <a href="http://developer.arlo.co/doc/wordpress/settings#import-callback-host" target="_blank"><i class="icons8 icons8-help-filled size-16"></i></a></label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'import_callback_host',
                            'label_for' => 'import_callback_host',
                            )
                );


		/*
		 *
		 * Page Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_pages_section', null, array($this, 'arlo_pages_section_callback'), $this->plugin_slug );

		// loop though slug array and create each required slug field
	    foreach(Arlo_For_Wordpress::$templates as $id => $template) {
	    	$name = __($template['name'], 'arlo-for-wordpress' );
			add_settings_field( $id, '<label for="'.$id.'">'.$name.'</label>', array($this, 'arlo_template_callback'), $this->plugin_slug, 'arlo_pages_section', array('id'=>$id,'label_for'=>$id) );
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
		 * Filters Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_filters_section', null, null, $this->plugin_slug );	

		add_settings_field('arlo_filters', null, array($this, 'arlo_filter_settings_callback'), $this->plugin_slug, 'arlo_filters_section',  array('id'=>'filters'));    

		
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
			'arlo_send_data_setting', 
			'<label for="arlo_send_data">'.__('Allow to send data to Arlo', 'arlo-for-wordpress' ).'</label>', 
			array($this, 'arlo_checkbox_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			['option_name' => 'arlo_send_data']);
								
			
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
	    			var arlo_templates = ' . json_encode($this->arlo_template_source()) . ';
	    		</script>		
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
		$settings_object = get_option('arlo_settings');
		$setting_id = 'filter_settings';
		$filter_settings = get_option('arlo_filter_settings', array());
		
		$output = '<div id="'.ARLO_PLUGIN_PREFIX.'-filter-select" class="cf">';

		$output .= '<h3>Filter Settings</h3>';

		$output .= '<label>Select page: </label>';

		$output .= '<select name="arlo_settings['.$setting_id.']" id="arlo-filter-settings">';

		$filters_settings_html = '';

		foreach(Arlo_For_Wordpress::$available_filters as $filter_group => $filter_group_values) {
		    $output .= '<option value="'.$filter_group.'" >'.$filter_group_values['name'].'</option>';

		    $filters_settings_html .= '<div id="arlo-' . $filter_group . '-filters" class="arlo-filter-group">';

		    foreach($filter_group_values['filters'] as $filter_key => $filter) {
		    	$filters_settings_html .= '<div class="arlo-filter-settings">';

		    	$filters_settings_html .= '<h4>' . __($filter,'arlo-for-wordpress') . '</h4>';

			    $filters_settings_html .= '
				    <div id="arlo-filters-header">
						<div class="arlo-filter-old-value">Option text</div>
						<div class="arlo-filter-new-value">Replacement text</div>
						<div class="arlo-filter-toggle">Hide this option?</div>
				    </div>
			    ';

			    $filters_settings_html .= '
				    <div id="arlo-filter-empty">
						<ul>
							<li>
								<div class="arlo-filter-old-value"><input type="text" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.'][setting_id][filteroldvalue]"></div>
								<div class="arlo-filter-controls">
									<i class="icons8-minus icons8 size-21"></i>
									<i class="icons8-plus icons8 size-21"></i>
								</div>
								<div class="arlo-filter-toggle">
									<input type="checkbox" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.'][setting_id][filterhideoption]" value="hidden">
								</div>
								<div class="arlo-filter-new-value"><input type="text" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.'][setting_id][filternewvalue]"></div>
							</li>
						</ul>
				    </div>
				    ';

				$filters_settings_html .= '<ul class="arlo-available-filters">';

				if (is_array($filter_settings)) {

					if (!empty($filter_settings[$filter_group][$filter_key]) && count($filter_settings[$filter_group][$filter_key])) {

						foreach($filter_settings[$filter_group][$filter_key] as $old_value => $new_value) {
							$is_hidden = false;
					    	$existing_filter_setting_id = rand();

							if ($filter_settings["arlohiddenfilters"][$filter_group][$filter_key]) {
								$is_hidden = in_array($old_value,$filter_settings["arlohiddenfilters"][$filter_group][$filter_key]);
							}

							$checked = $is_hidden ? ' checked="checked"' : '';

							$filters_settings_html .=  '<li>
								<div class="arlo-filter-old-value"><input type="text" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.']['.$existing_filter_setting_id.'][filteroldvalue]" value="'.$old_value.'"></div>
								<div class="arlo-filter-controls">
									<i class="icons8-minus icons8 size-21"></i>
									<i class="icons8-plus icons8 size-21"></i>
								</div>
								<div class="arlo-filter-toggle">
									<input type="checkbox" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.']['.$existing_filter_setting_id.'][filterhideoption]" value="hidden" ' . $checked . '>
								</div>
								<div class="arlo-filter-new-value"><input type="text" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.']['.$existing_filter_setting_id.'][filternewvalue]" value="' . $new_value . '"></div>
							  </li>
							 ';
						}

					}

				}

				$new_filter_setting_id = rand();
		
				$filters_settings_html .= '<li>
						<div class="arlo-filter-old-value"><input type="text" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.']['.$new_filter_setting_id.'][filteroldvalue]"></div>
						<div class="arlo-filter-controls">
							<i class="icons8-minus icons8 size-21"></i>
							<i class="icons8-plus icons8 size-21"></i>
						</div>
						<div class="arlo-filter-toggle">
							<input type="checkbox" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.']['.$new_filter_setting_id.'][filterhideoption]" value="hidden">
						</div>
						<div class="arlo-filter-new-value"><input type="text" name="arlo_settings[arlo_filter_settings]['.$filter_group.']['.$filter_key.']['.$new_filter_setting_id.'][filternewvalue]"></div>
				  </li>
				</ul>';

				$filters_settings_html .= '</div><hr>';
		    }



		    $filters_settings_html .= '</div>';

		}
				
		$output .= '</select>';

		$output .= $filters_settings_html;

		$output .= '</div>';

		echo $output;
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
		$settings_object = get_option('arlo_settings');
		
		echo '<h3>' . sprintf(__('%s page', 'arlo-for-wordpress' ), Arlo_For_Wordpress::$templates[$id]['name']) . '</h3>';
		
		/*
		HACK because the keys in the $post_types arrays are bad, couldn't change because backward comp.
		*/
		
		if (in_array($id, array('eventsearch', 'upcoming', 'events', 'presenters', 'venues', 'oa'))) {
			$post_type_id = !in_array($id, array('eventsearch','upcoming', 'oa')) ? substr($id, 0, strlen($id)-1) : $id;
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
	
			echo '
			<div class="arlo-label"><label>' .  __("Host page", 'arlo-for-wordpress' ) . '</label></div>
			<div class="arlo-field">
				<span class="arlo-page-select">' . $select . '</span>
				<span class="arlo-gray arlo-inlineblock">' . sprintf(__('Page must contain the %s shortcode', 'arlo-for-wordpress' ), Arlo_For_Wordpress::$templates[$id]['shortcode']) . '</span>
			</div>';
    	}
	    
	    $val = isset($settings_object['templates'][$id]['html']) ? $settings_object['templates'][$id]['html'] : '';
	    
	    
	    $this->arlo_reload_template($id);
	    
	    echo '<div class="arlo-label arlo-full-width">
	    		<label>
	    		' . sprintf(__('%s page', 'arlo-for-wordpress' ), Arlo_For_Wordpress::$templates[$id]['name']) . '
	    		' . (!empty(Arlo_For_Wordpress::$templates[$id]['shortcode']) ? 'shortcode <span class="arlo-gray">' . Arlo_For_Wordpress::$templates[$id]['shortcode'] . '</span>' : '') . ' content
	    		</label>
	    	</div>';
	    	
	    wp_editor($val, $id, array('textarea_name'=>'arlo_settings[templates]['.$id.'][html]','textarea_rows'=>'20'));
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
	
	function arlo_template_source() {
		$plugin = Arlo_For_Wordpress::get_instance();
		$theme_manager = $plugin->get_theme_manager();

		$selected_theme_id = get_option('arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME);
		$theme_templates = $theme_manager->load_default_templates($selected_theme_id);
		
		$templates = [];
		
		foreach (Arlo_For_Wordpress::$templates as $key => $val) {
			$templates[ARLO_PLUGIN_PREFIX . '-' . $key] = $theme_templates[$key]['html'];
		}
		
		return $templates;
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
						<i class="icons8-minus icons8 size-21"></i>
						<i class="icons8-plus icons8 size-21"></i>
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
						<i class="icons8-minus icons8 size-21"></i>
						<i class="icons8-plus icons8 size-21"></i>
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
				<i class="icons8-minus icons8 size-21"></i>
				<i class="icons8-plus icons8 size-21"></i>
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
				<li>New presenter filter for <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/upcomingeventrelated#arlo_upcoming_event_filters" target="_blank">[arlo_upcoming_event_filters]</a></li>
				<li>Support new URL structure on the admin pages and for private events</li>
				<li>Fix broken link in <a href="https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_suggest_datelocation" target="_blank">[arlo_suggest_datelocation]</a> when used the "text" attribute</li>
			</ul>
		</p>		



		<h4>Version 3.1.2</h4>
		<p>
	    	<ul class="arlo-whatsnew-list">	  
				<li>Improve error logging and reliability for data synchronization</li>
			</ul>
		</p>
		<h4>Version 3.1</h4>
		<p>
	    	<ul class="arlo-whatsnew-list">	  
				<li>New \'link\' attribute for <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_location" target="_blank">[arlo_event_location]</a> and <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_presenters" target="_blank">[arlo_event_presenters]</a> shortcodes</li>
				<li>New <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/venuerelated#arlo_venue_link" target="_blank">[arlo_venue_link]</a> shortcode</li>
				<li>New <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/presenterrelated#arlo_presenter_link" target="_blank">[arlo_presenter_link]</a> shortcode</li>
				<li>Fix timezone issues on the upcoming events widget</li>
	    		<li>Fix timezone error if the WP hasn\'t got a named timezone</li>
	    	</ul>
	    </p>			    
		<h4>Version 3.0.1</h4>
		<p>
	    	<ul class="arlo-whatsnew-list">	  
				<li>Enhance time zone indicators on events</li>
	    		<li>Fix permission issues with WPEngine hosting</li>
				<li>Fix missing session information on a reginalized platform</li>
				<li>Fix saving tags for multisession events</li>
				<li>Other minor fixes and improvements</li>
	    	</ul>
	    </p>		
		<h4>Version 3.0</h4>
	    <p>
	    	<ul class="arlo-whatsnew-list">	    
	    		<li>New, improved, more reliable <a href="http://developer.arlo.co/doc/wordpress/import#import-snapshot" target="_blank">snapshot import</a></li>
				<li>New <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_filters" target="_blank">[arlo_event_filters]</a> shortcode</li>
				<li>New <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/#arlo_search_field" target="_blank">[arlo_search_field]</a> shortcode</li>
				<li>Minor fixes and improvements</li>
				<li>Revamp the codebase behind the plugin</li>
				<li>New built-in theme selection</li>
	    	</ul>
	    </p>		
		<h4>Version 2.4.1.2</h4>
	    <p>
	    	<ul class="arlo-whatsnew-list">	    
	    		<li>Fixed wrong [arlo_event_duration] return value</li>
				<li>Fix when [arlo_event_price] shortcode doesn\'t return the "cheapest" price</li>
				<li>Fix when [arlo_event_next_running] shortcode returns a session date</li>
	    	</ul>
	    </p>		
		<h4>Version 2.4.1.1</h4>
	    <p>
	    	<ul class="arlo-whatsnew-list">	    
	    		<li>Improvement the stability of the import</li>
				<li><a href="https://confirmsubscription.com/h/r/41B80B5B566BCC0B" target="_blank">Subscribe</a> to our WP newsletter</li>
	    	</ul>
	    </p>
		<h4>Version 2.4</h4>		
	    <p>
	    	<ul class="arlo-whatsnew-list">
	    		<li>The plugin supports <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/onlineactivityrelated" target="_blank">Online Activities</a></li>
				<li>
					New 
					<a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_credits" target="_blank">[arlo_event_credits]</a>,  
					<a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_event_template_advertised_duration" target="_blank">[arlo_event_template_advertised_duration]</a>,  
					<a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/categoryrelated#arlo_category_title" target="_blank">[arlo_category_title]</a> 
					shortcodes
				</li>
				<li>New "showfrom" attribute for <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_price" target="_blank">[arlo_event_price]</a> shortcode</li>
				<li>New "strip_html" attribute every <a href="http://developer.arlo.co/doc/wordpress/shortcodes/" target="_blank">shortcode</a></li>
				<li>New "text" attribute for <a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated#arlo_event_next_running" target="_blank">[arlo_event_next_running] shortcode</a></li>
				<li>Sending error data to Arlo</li>
				<li>Detailed log</li>
				<li>Many bug fixes and enhancements</li>
	    	</ul>
	    </p>
	    ';
	}
	
	function arlo_systemrequirements_callback () {
		$good = '<i class="icons8-checkmark icons8 size-21 green"></i>';
		$bad = '<i class="icons8-cancel icons8 size-21 red"></i>';

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

			echo '
			<tr>
				<td class="arlo-required-setting-icon">' . ($check ? $good : $bad) . '</td>
				<td class="arlo-required-setting">' . $req['name'] . '</td>
				<td class="arlo-required-setting-value">' . $req['expected_value'] . '</td>
				<td class="arlo-required-setting-value ' . ($check ? 'green' : 'red') . '">' . $current_value . '</td>
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
				<li>Can\'t find what you\'re looking for? <a href="https://support.arlo.co/hc/en-gb/requests/new/" target="_blank">Submit a ticket</a></li>
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
						' . (!empty($theme_data->icon) ? '<div class="arlo-theme-icon"><i class="icons8 ' . $theme_data->icon . ' size-48 "></i></div>' : '') . '
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
					' . (!empty($theme_data->demoUrl) ? '<li><a href="' . $theme_data->demoUrl . '" target="_blank">' . __('Preview', 'arlo-for-wordpress' ) . '</a></li>' : '' ) . '
					' . ($selected_theme_id == $theme_data->id ? '
						<li class="arlo-theme-current">Current</li>
					':'
						<li><a class="theme-apply" href="' . wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&apply-theme=' . urlencode($theme_data->id)), 'arlo-apply-theme-nonce') . '">' . __('Apply', 'arlo-for-wordpress') . '</a></li>
					') .
						( $theme_data->id != 'custom' ? '<li><a class="theme-apply theme-reset" href="' . wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&apply-theme=' . urlencode($theme_data->id) . '&reset=1'), 'arlo-apply-theme-nonce') . '">' . ($selected_theme_id == $theme_data->id ? __('Reset', 'arlo-for-wordpress') : __('Apply & Reset', 'arlo-for-wordpress')) . '</a></li>' : '') . '
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
