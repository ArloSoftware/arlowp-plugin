<?php
namespace Arlo\Shortcodes;

class Shortcodes {
	public static function init() {
		//TODO: Autoloader

		Categories::init();
		OnlineActivities::init();
		Templates::init();
		Venues::init();
		Presenters::init();
		Events::init();
		UpcomingEvents::init();

		// group devider
		self::add('group_divider', function($content = '', $atts, $shortcode_name, $import_id){
			if(isset($GLOBALS['arlo_event_list_item']['show_divider'])) return $GLOBALS['arlo_event_list_item']['show_divider'];
		});

		// timezones
		self::add('timezones', function($content = '', $atts, $shortcode_name, $import_id){
			return self::shortcode_timezones($content, $atts, $shortcode_name, $import_id);	
		});

		// timezones
		self::add('label', function($content = '', $atts, $shortcode_name, $import_id){
			return $content;
		});
	}

	/*
	public static function autoload_shortcodes($class_name) {
		$class = new \ReflectionClass("\Arlo\Shortcodes\\" . $class_name);
		$methods = $class->getMethods();

		foreach ($methods as $method) {
			if (strpos( $method->name, 'shortcode_' ) === 0) {
				$shortcode_name = str_replace('shortcode_', '', $method->name);
				$method_name_with_classname = $method->class . '::' . $method->name;

				self::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id, $method_name_with_classname = '') {
            		//return $method_name_with_classname($content, $atts, $shortcode_name, $import_id);
        		}, $method_name_with_classname);
			}
		}
	}
	*/

    public static function add() {
	    $args = func_get_args();

	    if(is_array($args[0])) {
		    $options = $args[0];
		    
		    $name = $options['name'];
		    $function = $options['function'];
	    } else {
		    $name = $args[0];
		    $function = $args[1];
	    }
	    
	    $shortcode_name = 'arlo_' . $name;
	
	    // add the shortcode
	    add_shortcode($shortcode_name, array('\Arlo\Shortcodes\Shortcodes', 'the_shortcode'));
	     
	    $closure = new \ReflectionFunction($function);
	    
	    // assign the passed function to a filter
	    // all shortcodes are run through filters to allow external manipulation if required, however we also need a means of running the passed function
	    add_filter('arlo_shortcode_content_' . $shortcode_name, function($content='', $atts, $shortcode_name, $import_id = '') use($closure) {
			global $arlo_plugin;

		    return $closure->invokeArgs(array($content, $atts, $shortcode_name, $arlo_plugin->get_importer()->get_current_import_id(), ''));
	    }, 10, 3);
		
    }
    
    public static function the_shortcode($atts, $content="", $shortcode_name = '') {
		// merge and extract attributes
		extract(shortcode_atts(array(
			'wrap' => '%s',
			'label' => '',
			'strip_html'	=> 'false',
		), $atts, $shortcode_name));
	
		// need to decide ordering - currently makes sense to process the specific filter first
		$content = apply_filters('arlo_shortcode_content_'.$shortcode_name, $content, $atts, $shortcode_name);
		$content = apply_filters('arlo_shortcode_content', $content, $atts, $shortcode_name);
		
		// run any shortcodes prior to conituning
		$content = do_shortcode($content);
		
		// if not empty, process labels and wrapping
		if(trim($content) != '') {
			//strip html, if neccessary 
			if ($strip_html !== 'false') {
				if ($strip_html == 'true') {
					$content = strip_tags($content);
				} else {
					$content = strip_tags($content, $strip_html);
				}
			}
			
			// prepend label
            if (!empty($label)) {
                $content = '<label>' . $label . '</label> ' . $content;
            }
			
			// wrap content			
			$content = sprintf($wrap, $content);                        
		}
		
		return do_shortcode($content);
    }

	public static function create_region_selector($page_name) {
		global $post;
		
		$valid_page_names = ['upcoming', 'event', 'eventsearch'];
		
		$settings = get_option('arlo_settings');  
		$regions = get_option('arlo_regions');  
		
		if (!in_array($page_name, $valid_page_names) || !(is_array($regions) && count($regions))) return "";
			
		$regionselector_html .= self::create_filter('region', $regions);					
		
		return $regionselector_html;
	}

	public static function create_filter($type, $items, $label=null) {
		$filter_html = '<select id="arlo-filter-' . $type . '" name="arlo-' . $type . '">';
		
		if (!is_null($label))
			$filter_html .= '<option value="">' . $label . '</option>';
		
		$selected_value = (isset($_GET['arlo-' . $type]) ? urldecode($_GET['arlo-' . $type]) : get_query_var('arlo-' . $type, ''));
			
		foreach($items as $key => $item) {

			if (empty($item['string']) && empty($item['value'])) {
				$item = array(
					'string' => $item,
					'value' => $key
				);
			}
			
			$selected = (strlen($selected_value) && urldecode($selected_value) == $item['value']) ? ' selected="selected"' : '';
			
			$filter_html .= '<option value="' . esc_attr($item['value']) . '"' . $selected.'>';
			$filter_html .= htmlentities($item['string'], ENT_QUOTES, "UTF-8", false);
			$filter_html .= '</option>';

		}

		$filter_html .= '</select>';

		return $filter_html;
	}

	private static function getTimezones() {
		global $wpdb, $arlo_plugin;
		
		$table = $wpdb->prefix . "arlo_timezones";
		$import_id = $arlo_plugin->get_importer()->get_current_import_id();
		
		$sql = "
		SELECT
			id,
			name
		FROM
			{$table}
		WHERE
			import_id = " . $import_id . "
		ORDER BY name
		";
		return $wpdb->get_results($sql);
	}

	private static function getTimezoneOlsonNames($timezone_id = 0) {
		global $wpdb, $arlo_plugin;
		
		$timezone_id = intval($timezone_id);
		
		$table = $wpdb->prefix . "arlo_timezones_olson";
		$import_id = $arlo_plugin->get_importer()->get_current_import_id();
		$where = '';
		
		if ($timezone_id > 0) {
			$where = "
				timezone_id = {$timezone_id}
			AND		
			";
		}
		
		$sql = "
		SELECT
			olson_name
		FROM
			{$table}
		WHERE
			{$where}	
			import_id = " . $import_id . "
		";
		return $wpdb->get_results($sql);
	}	

	private static function shortcode_timezones($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
		global $post, $wpdb;
	
		// only allow this to be used on the eventtemplate page
		if($post->post_type != 'arlo_event') {
			return '';
		}
		
		// find out if we have any online events
		$t1 = "{$wpdb->prefix}arlo_eventtemplates";
		$t2 = "{$wpdb->prefix}arlo_events";
		
		$items = $wpdb->get_results("
			SELECT 
				$t2.e_isonline, 
				$t2.e_timezone_id 
			FROM 
				$t2
			LEFT JOIN 
				$t1
			ON 
				$t2.et_arlo_id = $t1.et_arlo_id 
			AND 
				$t2.e_isonline = 1 
			AND 
				$t2.e_parent_arlo_id = 0
			AND
				$t1.import_id = $t2.import_id
			WHERE 
				$t1.et_post_name = '$post->post_name'
			AND 
				$t2.import_id = $import_id
			", ARRAY_A);
		
		if(empty($items)) {
			return '';
		}
		
		$olson_names = self::getTimezoneOlsonNames();	

		$content = '<form method="GET" class="arlo-timezone">';
		$content .= '<select name="timezone">';
		
		foreach(self::getTimezones() as $timezone) {		
			$selected = false;
			if((isset($_GET['timezone']) && $_GET['timezone'] == $timezone->id) || (!isset($_GET['timezone']) && $timezone->id == $items[0]['e_timezone_id'])) {
				$selected = true;
				//get olson timezones
				$olson_names = self::getTimezoneOlsonNames($timezone->id);
				$GLOBALS['selected_timezone_olson_names'] = $olson_names;
			}
			
			$content .= '<option value="' . $timezone->id . '" ' . ($selected ? 'selected' : '') . '>'. htmlentities($timezone->name, ENT_QUOTES, "UTF-8") . '</option>';
		}
		
		$content .= '</select>';
		$content .= '</form>';
		
		//if there is no olson names in the database, that means we couldn't do a timezone conversion
		if (!(is_array($olson_names) && count($olson_names))) {
			$content = '';
		}

		return $content;
	}

	public static function advertised_offers($id, $id_field, $import_id) {
		global $wpdb;
               
        $regions = get_option('arlo_regions');	
        
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');	

        $t1 = "{$wpdb->prefix}arlo_offers";
        
        $sql = "
        SELECT 
            offer.*,
            replaced_by.o_label AS replacement_label,
            replaced_by.o_isdiscountoffer AS replacement_discount,
            replaced_by.o_currencycode AS replacement_currency_code,
            replaced_by.o_formattedamounttaxexclusive AS replacement_amount,
            replaced_by.o_message AS replacement_message
        FROM 
            {$wpdb->prefix}arlo_offers AS offer
        LEFT JOIN 
            {$wpdb->prefix}arlo_offers AS replaced_by 
        ON 
            offer.o_arlo_id = replaced_by.o_replaces 
        AND
            offer.import_id = replaced_by.import_id
        AND 
            offer.$id_field = replaced_by.$id_field	
        " . (!empty($arlo_region) ? " AND replaced_by.o_region = '" . $arlo_region . "'" : "") . "
        WHERE 
            offer.o_replaces = 0 
        AND
            offer.import_id = $import_id
        AND 
            offer.$id_field = $id
        " . (!empty($arlo_region) ? " AND offer.o_region = '" . $arlo_region . "'" : "") . "		
        ORDER BY 
            offer.o_order";
            
        $offers_array = $wpdb->get_results($sql, ARRAY_A);

        $offers = '<ul class="arlo-list arlo-event-offers">';
            
        $settings = get_option('arlo_settings');  
        $price_setting = (isset($settings['price_setting'])) ? esc_attr($settings['price_setting']) : ARLO_PLUGIN_PREFIX . '-exclgst';      
        $free_text = (isset($settings['free_text'])) ? esc_attr($settings['free_text']) : __('Free', $GLOBALS['arlo_plugin_slug']);


        foreach($offers_array as $offer) {

            extract($offer);
                    
            $amount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $o_offeramounttaxexclusive : $o_offeramounttaxinclusive;
            $famount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $o_formattedamounttaxexclusive : $o_formattedamounttaxinclusive;

            // set to true if there is a replacement offer returned for this event offer
            $replaced = (!is_null($replacement_amount) && $replacement_amount != '');

            $offers .= '<li><span';
            // if the offer is discounted
            if($o_isdiscountoffer) {
                $offers .= ' class="discount"';
            // if the offer is replace by another offer
            } elseif($replaced) {
                $offers .= ' class="replaced"';
            }
            $offers .= '>';
            // display label if there is one
            $offers .= (!is_null($o_label) || $o_label != '') ? $o_label.' ':'';
            if($amount > 0) {
                $offers .= '<span class="amount">'.$famount.'</span> ';
                // only include the excl. tax if the offer is not replaced			
                $offers .= $replaced ? '' : '<span class="arlo-price-tax">' . ($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__('excl. %s', $GLOBALS['arlo_plugin_slug']), $o_taxrateshortcode) : sprintf(__('incl. %s', $GLOBALS['arlo_plugin_slug']), $o_taxrateshortcode) . '</span>');
            } else {
                $offers .= '<span class="amount free">'.$free_text.'</span> ';
            }
            // display message if there is one
            $offers .= (!is_null($o_message) || $o_message != '') ? ' '.$o_message:'';
            // if a replacement offer exists
            if($replaced) {
                $offers .= '</span><span ' . ($replacement_discount ? 'class="discount"' : '') . '>';
                
                // display replacement offer label if there is one
                $offers .= (!is_null($replacement_label) || $replacement_label != '') ? $replacement_label.' ':'';
                $offers .= '<span class="amount">'.$replacement_amount.'</span> <span class="arlo-price-tax">'.($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__('excl. %s', $GLOBALS['arlo_plugin_slug']), $o_taxrateshortcode) : sprintf(__('incl. %s', $GLOBALS['arlo_plugin_slug']), $o_taxrateshortcode)) . '</span>';
                // display replacement offer message if there is one
                $offers .= (!is_null($replacement_message) || $replacement_message != '') ? ' '.$replacement_message:'';

            } // end if

            $offers .= '</span></li>';

        } // end foreach

        $offers .= '</ul>';

        return $offers;
	}
}