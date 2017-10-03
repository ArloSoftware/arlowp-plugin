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
			if(isset($GLOBALS['arlo_oa_list_item']['show_divider'])) return $GLOBALS['arlo_oa_list_item']['show_divider'];
		});

		// timezones
		self::add('timezones', function($content = '', $atts, $shortcode_name, $import_id){
			return self::shortcode_timezones($content, $atts, $shortcode_name, $import_id);	
		});

		// search_field
		self::add('search_field', function($content = '', $atts, $shortcode_name, $import_id){
			return self::shortcode_search_field($content, $atts, $shortcode_name, $import_id);	
		});

		// label
		self::add('label', function($content = '', $atts, $shortcode_name, $import_id){
			return $content;
		});

		//powered by Arlo
		self::add('powered_by', function ($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
       		return '<div class="arlo-powered-by"><a href="https://www.arlo.co/?utm_source=arlo%20client%20site&utm_medium=referral%20arlo%20powered%20by&utm_campaign=powered%20by" target="_blank">' .  sprintf(__('Powered by %s', 'arlo-for-wordpress'), '<img src="' . plugins_url("", __FILE__ ) . '/../../public/assets/img/Arlo-logo.svg" alt="Arlo training & Event Software">') . '</a></div>';
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
			'decode_quotes_in_shortcodes' => 'false',
		), $atts, $shortcode_name));
	
		// need to decide ordering - currently makes sense to process the specific filter first
		$content = apply_filters('arlo_shortcode_content_'.$shortcode_name, $content, $atts, $shortcode_name);
		$content = apply_filters('arlo_shortcode_content', $content, $atts, $shortcode_name);

		if ($decode_quotes_in_shortcodes === 'true') {
			if (preg_match('/\[(?:[^\/])(?:[^\]\[]*)(?:&quot;|&apos;)(?:[^\]\[]*)\]/', $content) === 1) {
				$content = str_replace(['&quot;','&apos;'], ['"','\''], $content);
			}
		}
		
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
			
		return self::create_filter('region', $regions);
	}

	public static function create_filter($type, $items, $label=null, $group=null) {
		if (!empty(get_option('arlo_filter_settings')[$group][$type][$label]) ) {
			$label = get_option('arlo_filter_settings')[$group][$type][$label];
		}

		if (!empty(get_option('arlo_filter_settings')['arlohiddenfilters'])) {
			$hidden_filters = get_option('arlo_filter_settings')['arlohiddenfilters'];
		}

		$filter_html = '<select id="arlo-filter-' . esc_attr($type) . '" name="arlo-' . esc_attr($type) . '">';

		if (!is_null($label))
			$filter_html .= '<option value="">' . esc_html($label) . '</option>';

		$selected_value = \Arlo\Utilities::clean_string_url_parameter('arlo-' . $type);

		$options_html = '';
			
		foreach($items as $key => $item) {
			if (empty($item['string']) && empty($item['value'])) {
				$item = array(
					'string' => $item,
					'value' => $key
				);
			}

			$is_hidden = false;
			if (!empty($hidden_filters[$group][$type])) {
				$is_hidden = in_array(esc_html($item['string']),$hidden_filters[$group][$type]);
			}

			if (!$is_hidden) {
				$value_label = $item['string'];

				if (!empty(get_option('arlo_filter_settings')[$group][$type][$value_label])) {
				if (!empty(get_option('arlo_filter_settings')[$group][$type][htmlspecialchars($value_label)])) {
					$value_label = get_option('arlo_filter_settings')[$group][$type][htmlspecialchars($value_label)];
				}

                $selected = (strlen($selected_value) && strtolower($selected_value) == strtolower($item['value'])) ? ' selected="selected"' : '';
				
				$options_html .= '<option value="' . esc_attr($item['value']) . '"' . $selected.'>' . esc_html($value_label) . '</option>';
			}
		}

		if (strlen($options_html) == 0) {
			return '';
		}

		$filter_html .= $options_html;

		$filter_html .= '</select>';

		return $filter_html;
	}

	public static function create_rich_snippet($content) {
		return '<script type="application/ld+json">' . $content . '</script>';
	}

    public static function get_performer($presenter, $link) {
        $performer = array("@type" => "Person");
        $name_separator = (!empty($presenter["p_firstname"]) && !empty($presenter["p_lastname"]) ? " " : "");
        $performer["name"] = $presenter["p_firstname"] . $name_separator . $presenter["p_lastname"];

        $p_link = '';
        switch ($link) {
            case 'viewuri': 
                $p_link = Shortcodes::get_rich_snippet_field($presenter["p_viewuri"]);
            break;  
            default:
            	$p_post_id = empty($presenter['post_id']) ? $presenter['p_post_id'] : $presenter['post_id'];
                $p_link = get_permalink($p_post_id);
            break;
        }

        $p_link = \Arlo\Utilities::get_absolute_url($p_link);

        $performer["url"] = $p_link;

        if (!empty($presenter["p_profile"])) {
        	$performer["description"] = $presenter["p_profile"];
        }

        $same_as = array($p_link);

        if (!empty($presenter["p_twitterid"])) {
        	array_push($same_as,"https://www.twitter.com/".$presenter["p_twitterid"]);
        }

        if (!empty($presenter["p_facebookid"])) {
        	array_push($same_as,"https://www.facebook.com/".$presenter["p_facebookid"]);
        }

        if (!empty($presenter["p_linkedinid"])) {
        	array_push($same_as,"https://www.linkedin.com/".$presenter["p_linkedinid"]);
        }

        $performer["sameAs"] = $same_as;

        return $performer;
    }

	private static function shortcode_search_field($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
		global $post, $wpdb;

		extract(shortcode_atts(array(
			'showbutton' => "true",
			'buttonclass' => '',
			'inputclass' => '',
			'placeholder' => __('Search for an event', 'arlo-for-wordpress'),
            'buttontext'	=> __('Search', 'arlo-for-wordpress'),
        ), $atts, $shortcode_name, $import_id));

		$settings = get_option('arlo_settings');
		if (!empty($settings['post_types']['eventsearch']['posts_page'])) {

			$slug = get_post($settings['post_types']['eventsearch']['posts_page'])->post_name;
				
			$search_term = \Arlo\Utilities::clean_string_url_parameter('arlo-search');
			
			return '
			<form class="arlo-search" action="'.site_url().'/'.$slug.'/">
				<input type="text" class="arlo-search-field ' . esc_attr($inputclass) . '" placeholder="'. esc_attr($placeholder) .'" name="arlo-search" value="' . esc_attr($search_term) . '">
				' . ($showbutton == "true" ? '<input type="submit" class="arlo-search-button ' . esc_attr($buttonclass) . '" value="' . esc_attr($buttontext) . '">' : '') . '
			</form>
			';	
		}
	}	

	private static function shortcode_timezones($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
		//TODO: nasty to use the global $arlo_plugin, need to refactor 
		global $post, $wpdb, $arlo_plugin;
	
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
				$t1.et_post_id = $post->ID
			AND 
				$t2.import_id = $import_id
			", ARRAY_A);
		
		if(empty($items)) {
			return '';
		}
		
		$content = '<form method="GET" class="arlo-timezone">';
		$content .= '<select name="timezone"><option value="">' . __('Select a time zone', 'arlo-for-wordpress') . '</option>';

		$timezones = $arlo_plugin->get_timezone_manager()->get_indexed_timezones();

		foreach($timezones as $timezone_id => $timezone) {
			$selected = false;
			if (!empty($timezone['id'])) {
				$timezone_id = $timezone['id'];
			}

         	$timezone_windows_tz_id = $timezone['windows_tz_id'];
			
			if((isset($_GET['timezone']) && $_GET['timezone'] == $timezone_id) || (!isset($_GET['timezone']) && $timezone_id == $items[0]['e_timezone_id'])) {
				$selected = true;
				//get PHP timezones
				$GLOBALS['selected_timezone_names'] = null;
				if (!is_null($timezone_windows_tz_id)) {
					$GLOBALS['selected_timezone_names'] = \Arlo\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_windows_tz_id];
        		}
			}
			
			if (!is_null($timezone_windows_tz_id)) {
				$content .= '<option value="' . intval($timezone_id) . '" ' . ($selected ? 'selected' : '') . '>'. esc_html($timezone['name']) . '</option>';
			}
		}

		$content .= '</select>';
		$content .= '</form>';
		
		return $content;
	}

	public static function get_advertised_offers($id, $id_field, $import_id) {
		global $wpdb;
               
        $arlo_region = \Arlo\Utilities::get_region_parameter();

        $sql = "
        SELECT 
            offer.*,
            replaced_by.o_label AS replacement_label,
            replaced_by.o_isdiscountoffer AS replacement_discount,
            replaced_by.o_currencycode AS replacement_currency_code,
            replaced_by.o_formattedamounttaxexclusive AS replacement_amount_taxexclusive,
			replaced_by.o_formattedamounttaxinclusive AS replacement_amount_taxinclusive,
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
        " . (!empty($arlo_region) ? " AND replaced_by.o_region = '" . esc_sql($arlo_region) . "'" : "") . "
        WHERE 
            offer.o_replaces = 0 
        AND
            offer.import_id = $import_id
        AND 
            offer.$id_field = $id
        " . (!empty($arlo_region) ? " AND offer.o_region = '" . esc_sql($arlo_region) . "'" : "") . "		
        ORDER BY 
            offer.o_order";

        $offers = $wpdb->get_results($sql, ARRAY_A);

        return $offers;
	}

	public static function get_offers_snippet_data($id, $id_field, $import_id, $price_field) {
        $offers_array = self::get_advertised_offers($id, $id_field, $import_id);

        $high_price = 0;
        $low_price = 0;
        $currency = "";

        foreach ($offers_array as $offer) {
        	$low_price = ( $offer[$price_field] < $low_price || $low_price == 0 ? $offer[$price_field] : $low_price );
        	$high_price = ( $offer[$price_field] > $high_price || $high_price == 0 ? $offer[$price_field] : $high_price );
        	$currency = ( $offer['o_currencycode'] < $currency || $currency == 0 ? $offer['o_currencycode'] : $currency );
        }

        return array(
        	'high_price' => $high_price,
        	'low_price' => $low_price,
        	'currency' => $currency
        );
	}

	public static function advertised_offers($id, $id_field, $import_id) {
		global $wpdb;
               
        $regions = get_option('arlo_regions');	
        
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && \Arlo\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');	

        $t1 = "{$wpdb->prefix}arlo_offers";
        
        $offers_array = self::get_advertised_offers($id, $id_field, $import_id);

        $offers = '<ul class="arlo-list arlo-event-offers">';
            
        $settings = get_option('arlo_settings');  
        $price_setting = (isset($settings['price_setting'])) ? esc_attr($settings['price_setting']) : ARLO_PLUGIN_PREFIX . '-exclgst';      
        $free_text = (isset($settings['free_text'])) ? esc_attr($settings['free_text']) : __('Free', 'arlo-for-wordpress');

        foreach($offers_array as $offer) {

            extract($offer);
                    
            $amount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $o_offeramounttaxexclusive : $o_offeramounttaxinclusive;
            $famount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $o_formattedamounttaxexclusive : $o_formattedamounttaxinclusive;

            // set to true if there is a replacement offer returned for this event offer
            $replaced = (!empty($replacement_amount_taxexclusive) && !empty($replacement_amount_taxinclusive));

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
                $offers .= '<span class="amount">' . esc_html($famount) . '</span> ';
                // only include the excl. tax if the offer is not replaced			
                $offers .= $replaced ? '' : '<span class="arlo-price-tax">' . esc_html(($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__('excl. %s', 'arlo-for-wordpress'), $o_taxrateshortcode) : sprintf(__('incl. %s', 'arlo-for-wordpress'), $o_taxrateshortcode))) . '</span>';
            } else {
                $offers .= '<span class="amount free">' . esc_html($free_text) . '</span> ';
            }
            // display message if there is one
            $offers .= (!is_null($o_message) || $o_message != '') ? ' ' . esc_html($o_message) : '';
            // if a replacement offer exists
            if($replaced) {
                $offers .= '</span><span ' . ($replacement_discount ? 'class="discount"' : '') . '>';
                
                // display replacement offer label if there is one
                $offers .= (!is_null($replacement_label) || $replacement_label != '') ? esc_html($replacement_label) . ' ' : '';
                $offers .= '<span class="amount">' . ($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $replacement_amount_taxexclusive : $replacement_amount_taxinclusive) . '</span> <span class="arlo-price-tax">' . esc_html(($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__('excl. %s', 'arlo-for-wordpress'), $o_taxrateshortcode) : sprintf(__('incl. %s', 'arlo-for-wordpress'), $o_taxrateshortcode))) . '</span>';
                // display replacement offer message if there is one
                $offers .= (!is_null($replacement_message) || $replacement_message != '') ? ' ' . esc_html($replacement_message) : '';

            } // end if

            $offers .= '</span></li>';

        } // end foreach

        $offers .= '</ul>';

        return $offers;
	}

	public static function get_rich_snippet_field($field) {
		return !empty($field) ? $field : '';
	}

    public static function get_template_permalink($post_name, $region) {
        if(!isset($post_name)) return '';
        
        $region_link_suffix = '';

        $regions = get_option('arlo_regions');

        if (!empty($region) && is_array($regions) && count($regions)) {
            $arlo_region = $region;
        } else {
            $arlo_region = get_query_var('arlo-region', '');
            $arlo_region = (!empty($arlo_region) && \Arlo\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');
        }

        if (!empty($arlo_region)) {
            $region_link_suffix = 'region-' . $arlo_region . '/';
        }
        
        $et_id = arlo_get_post_by_name($post_name, 'arlo_event');

        return get_permalink($et_id) . $region_link_suffix;
    }


}
