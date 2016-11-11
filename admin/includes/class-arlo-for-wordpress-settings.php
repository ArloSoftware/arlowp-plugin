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

class Arlo_For_Wordpress_Settings {

	public function __construct() {
		
		if (!session_id()) {
			session_start();
		}
				
		// allocates the wp-options option key value pair that will store the plugin settings
		register_setting( 'arlo_settings', 'arlo_settings' );		

		$plugin = Arlo_For_Wordpress::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
		$this->version = Arlo_For_Wordpress::VERSION;
		
		if (isset($_GET['page']) && $_GET['page'] == 'arlo-for-wordpress' && get_option('permalink_structure') != "/%postname%/") {
			add_action( 'admin_notices', array($plugin, "permalink_notice") );
		}					
		
		$settings = get_option('arlo_settings');
		$message_handler = $plugin->get_message_handler();
		
		add_action( 'admin_notices', array($plugin, "global_notices") );
		
		if(isset($_GET['page']) && $_GET['page'] == 'arlo-for-wordpress') {
			add_action( 'admin_notices', array($plugin, "arlo_notices") );
			
			if (!empty($settings['platform_name'])) {
				$show_notice = false;
				foreach (Arlo_For_Wordpress::$post_types as $id => $post_type) {
					if (empty($settings['post_types'][$id]['posts_page'])) {
						$show_notice = true;
						break;
					}
				}
				
				if ($show_notice) {
					add_action( 'admin_notices', array($plugin, "posttype_notice") );
				}
				
				add_action( 'admin_notices', array($plugin, "connected_platform_notice") );
			}
			
			if (isset($_GET['arlo-donwload-sync-log'])) {
				$plugin->download_synclog();
			}
		
			if (isset($_GET['arlo-import'])) {
				$scheduler = $plugin->get_scheduler();
				$scheduler->set_task("import", -1);
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
						
			add_action( 'admin_notices', array($plugin, "welcome_notice") );
			
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
                        '<label for="arlo_platform_name">'.__('Platform Name', 'arlo-for-wordpress' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'platform_name',
                            'label_for' => 'arlo_platform_name',
                            'before_html' => '<div class="arlo_platform">https://my.arlo.co/</div>',
                            )
                );                
                
        if (!empty($settings['platform_name'])) {
			// create last import text
			add_settings_field(
                        'arlo_last_import', 
                        '<label for="arlo_last_import">'.__('Last import', 'arlo-for-wordpress' ).'</label>', 
                        array($this, 'arlo_simple_text_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'html' => '<span class="arlo-last-sync-date">' . $plugin->get_last_import() . ' UTC</span>&nbsp;&nbsp;<a href="?page=arlo-for-wordpress&arlo-import" class="arlo-sync-button">'.__('Synchronize now', 'arlo-for-wordpress' ).'</a>'
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
                
		// create No events to show text field
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
		add_settings_field( 'arlo_customcss', null, array($this, 'arlo_simple_textarea_callback'), $this->plugin_slug, 'arlo_customcss_section', array('id'=>'customcss') );
		
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
		 * Welcome Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_welcome_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_welcome', null, array($this, 'arlo_welcome_callback'), $this->plugin_slug, 'arlo_welcome_section', array('id'=>'welcome') );
	}

	/*
	 *
	 * SECTION CALLBACKS
	 *
	 */


	function arlo_pages_section_callback() {
		echo '
	    		<script type="text/javascript"> 
	    			var arlo_blueprints = ' . json_encode($this->arlo_template_source()) . ';
	    		</script>		
		';
	}
	
	/*
	 *
	 * FIELD CALLBACKS
	 *
	 */
	 
	function arlo_simple_textarea_callback($args) {
	    $settings = get_option('arlo_settings');
	    $val = (isset($settings[$args['id']])) ? esc_attr($settings[$args['id']]) : (!empty($args['default_val']) ? $args['default_val'] : '' );
	    
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
		$settings = get_option('arlo_settings');
		$setting_id = 'price_setting';
		
		$output = '<div id="'.ARLO_PLUGIN_PREFIX.'-price-setting" class="cf">';
		$output .= '<select name="arlo_settings['.$setting_id.']">';            
		
		$val = (isset($settings[$setting_id])) ? esc_attr($settings[$setting_id]) : ARLO_PLUGIN_PREFIX . '-exclgst';
		
		foreach(Arlo_For_Wordpress::$price_settings as $key => $value) {
		    $key = ARLO_PLUGIN_PREFIX . '-' . $key;
		    $selected = $key == $val ? 'selected="selected"' : '';
		    $output .= '<option ' . $selected . ' value="'.$key.'" >'.$value.'</option>';
		}
		
		$output .= '</select></div>';
		
		echo $output;
	}
	
	
	function arlo_checkbox_callback($args) {
		if (empty($args['option_name'])) return;
		
		$option_name = $args['option_name'];
		$settings = get_option('arlo_settings');
				
		$output = '<div id="'.ARLO_PLUGIN_PREFIX.'-' . $option_name . '" class="cf">';
		$output .= '<input type="checkbox" value="1" name="arlo_settings['.$option_name.']" id="' . $option_name . '" ' . (isset($settings[$option_name]) && $settings[$option_name] == '1' ? 'checked="checked"' : '') . '>';
		
		$output .= '</div>';
		
		echo $output;
	}
	
        
	function arlo_simple_input_callback($args) {
	    $settings = get_option('arlo_settings');
	    $val = (isset($settings[$args['id']])) ? esc_attr($settings[$args['id']]) : (!empty($args['default_val']) ? $args['default_val'] : '' );
	    
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
		$settings = get_option('arlo_settings');
		
		echo '<h3>' . sprintf(__('%s page', 'arlo-for-wordpress' ), Arlo_For_Wordpress::$templates[$id]['name']) . '</h3>';
		
		/*
		HACK because the keys in the $post_types arrays are bad, couldn't change because backward comp.
		*/
		
		if (in_array($id, array('eventsearch', 'upcoming', 'events', 'presenters', 'venues'))) {
			$post_type_id = !in_array($id, array('eventsearch','upcoming')) ? substr($id, 0, strlen($id)-1) : $id;
		}
	
    	if (!empty($post_type_id) && !empty(Arlo_For_Wordpress::$post_types[$post_type_id])) {
    		$post_type = Arlo_For_Wordpress::$post_types[$post_type_id];
		    $val = isset($settings['post_types'][$post_type_id]['posts_page']) ? esc_attr($settings['post_types'][$post_type_id]['posts_page']) : 0;
	
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
	    
	    $val = isset($settings['templates'][$id]['html']) ? $settings['templates'][$id]['html'] : '';
	    
	    
	    $this->arlo_reload_template($id, $settings);
	    
	    echo '<div class="arlo-label arlo-full-width">
	    		<label>
	    		' . sprintf(__('%s page', 'arlo-for-wordpress' ), Arlo_For_Wordpress::$templates[$id]['name']) . '
	    		' . (!empty(Arlo_For_Wordpress::$templates[$id]['shortcode']) ? 'shortcode <span class="arlo-gray">' . Arlo_For_Wordpress::$templates[$id]['shortcode'] . '</span>' : '') . ' content
	    		</label>
	    	</div>';
	    	
	    wp_editor($val, $id, array('textarea_name'=>'arlo_settings[templates]['.$id.'][html]','textarea_rows'=>'20'));
	}
	
	function arlo_reload_template($template, $settings) {
	
	    echo '
	    	<div class="arlo-label">
	    		<label>'. __('Style and layout', 'arlo-for-wordpress' ) . '</label>
	    	</div>
	    	<div class="arlo-field">';
				    if (!empty(Arlo_For_Wordpress::$templates[$template])) {
						$template_definition = Arlo_For_Wordpress::$templates[$template];
						if (isset($template_definition['sub']) && is_array($template_definition['sub'])) {
							echo '<div class="' . ARLO_PLUGIN_PREFIX . '-sub-template-select">
								<select name="arlo_settings[subtemplate]['.$template.']">';
						    foreach ($template_definition['sub'] as $k => $v) {
						    	$selected = (!empty($settings['subtemplate'][$template]) && $settings['subtemplate'][$template] == $k ? 'selected' : '');
						    	echo '<option value="' . $k . '" '.$selected.'>' . $v . '</option>';
						    }
					    				
					    	echo '</select>
					    	</div>';
						}	    
				    }	    	
				echo '
	    		<div class="' . ARLO_PLUGIN_PREFIX . '-reload-template"><a>' . __('Reload original template', 'arlo-for-wordpress' ) . '</a></div>
	    	</div>
	    	<div class="cf"></div>';
	}
	
	function arlo_template_source() {
		
		$templates = [];
		
		foreach (Arlo_For_Wordpress::$templates as $key => $val) {
			$templates[ARLO_PLUGIN_PREFIX . '-' . $key] = $this->arlo_get_blueprint($key);
			
			if (isset($val['sub']) && is_array($val['sub'])) {
				foreach ($val['sub'] as $sufix => $sufixname) {
					if (!empty($sufix)) {
						$templates[ARLO_PLUGIN_PREFIX . '-' . $key . '-' . $sufix] = $this->arlo_get_blueprint($key . '_' . $sufix);			
					}
				}
			}
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
	                                                                                                                                                      
	function arlo_get_blueprint($name) {
		$path = ARLO_PLUGIN_DIR.'/includes/blueprints/'.$name.'.tmpl';

		if(file_exists($path)) {
			return file_get_contents($path);
		}
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
	
	function arlo_welcome_callback($args) {
		
	    echo '
	    <h3>What\'s new in this release</h3>
		<p><strong>If you are experiencing problems after an update, please deactivate and re-activate the plugin and re-synchronize the data.</strong></p>
	    <h4>Version ' . $this->version . '</h4>
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
	    
	    <h4>Version 2.3.5.1</h4>
	    <p>
	    	<ul class="arlo-whatsnew-list">	    
	    		<li>Fix start and end date times when the dates are returning a UTC value</li>
	    	</ul>
	    </p>				
		<h4>Version 2.3.5</h4>
	    <p>
	    	<ul class="arlo-whatsnew-list">
	    		<li>New asynchronous import, for more information, please visit our <a href="http://developer.arlo.co/doc/wordpress/import" target="_blank">documentation</a>.</li>
	    		<li>Support localization for dates and times</li>
	    		<li>Few minor bugfixes</li>
	    	</ul>
	    </p>		
		<h4>Version 2.3.1</h4>
	    <p>
	    	<ul class="arlo-whatsnew-list">
	    		<li>Important fix to solve the compatibility issue with some external plugin</li>
	    		<li>Enhancement of the plugin update mechanism</li>
	    		<li>Few minor bugfixes</li>
	    	</ul>
	    </p>
	    <h4>Version 2.3</h4>	
	    <p>
	    	<ul class="arlo-whatsnew-list">
	    		<li>Regionalized plugin, for more information, please visit our <a href="http://developer.arlo.co/doc/wordpress/settings#regions" target="_blank">documentation</a></li>
	    		<li>New region selector shortcodes 
	    		<a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/upcomingeventrelated#arlo_upcoming_region_selector" target="_blank">[arlo_upcoming_region_selector]</a>, 
	    		<a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_template_search_region_selector" target="_blank">[arlo_template_search_region_selector]</a>, 
	    		<a href="http://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated#arlo_template_region_selector" target="_blank">[arlo_template_region_selector]</a></li>
	    		<li>Many minor bug fixes and enhancements</li>
	    	</ul>
	    </p>
	    <p>If you are experiencing problems with the URLs, please save changes to the Arlo settings page and resynchronize the data under the general tab.</p>
	    ';
	} 		
}

?>