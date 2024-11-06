<?php
namespace Arlo\Shortcodes;

use Arlo_For_Wordpress;

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
		self::add('group_divider', function($content = '', $atts = [], $shortcode_name = '', $import_id = ''){
			if(isset($GLOBALS['arlo_event_list_item']['show_divider'])) return $GLOBALS['arlo_event_list_item']['show_divider'];
			if(isset($GLOBALS['arlo_oa_list_item']['show_divider'])) return $GLOBALS['arlo_oa_list_item']['show_divider'];
		});

		// timezones
		self::add('timezones', function($content = '', $atts = [], $shortcode_name = '', $import_id = ''){
			return self::shortcode_timezones($content, $atts, $shortcode_name, $import_id);	
		});

		// search_field
		self::add('search_field', function($content = '', $atts = [], $shortcode_name = '', $import_id = ''){
			return self::shortcode_search_field($content, $atts, $shortcode_name, $import_id);	
		});

		// label
		self::add('label', function($content = '', $atts = [], $shortcode_name = '', $import_id = ''){
			return $content;
		});

		// breadcrumbs
		self::add('breadcrumbs', function($content = '', $atts = [], $shortcode_name = '', $import_id = ''){
			return self::shortcode_breadcrumbs($content, $atts, $shortcode_name, $import_id);
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
	    add_filter('arlo_shortcode_content_' . $shortcode_name, function($content='', $atts = [], $shortcode_name = '', $import_id='') use($closure) {
			$plugin = Arlo_For_Wordpress::get_instance();
			$import_id = $plugin->get_importer()->get_current_import_id();

			if (!empty($import_id))
		    	return $closure->invokeArgs(array($content, $atts, $shortcode_name, $import_id, ''));
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
		
		$valid_page_names = ['upcoming', 'event', 'eventsearch', 'widget'];
		
		$settings = get_option('arlo_settings');  
		$regions = get_option('arlo_regions');  
		
		if (!in_array($page_name, $valid_page_names) || !(is_array($regions) && count($regions))) return "";
			
		return self::create_filter('region', $regions, null, null, \Arlo_For_Wordpress::get_region_parameter());
	}

	public static function create_filter($type, $items, $label, $group, $att_default=null, $page = '') {
		if (count($items) == 0 || !is_array($items)) {
			return '';
		}

		$filter_settings = get_option('arlo_filter_settings', []);
		$page_filter_settings = get_option('arlo_page_filter_settings', []);

		if (!empty($filter_settings[$group][$type][$label]) ) {
			$label = $filter_settings[$group][$type][$label];
		}

		$urlParameter = \Arlo\Utilities::clean_string_url_parameter('arlo-' . $type);

		$selected_value = !empty($urlParameter) || $urlParameter == "0" ? $urlParameter : (!empty($att_default) || $att_default == "0" ? $att_default : '');

		$options_html = '';

		foreach($items as $key => $item) {
			if (empty($item['string']) && empty($item['value'])) {
				$item = array(
					'string' => $item,
					'value' => $key,
					'id' => $key
				);
			}
			
			if (!isset($item['id'])) {
				$item['id'] = $item['value'];
			}

			$option_label = '';
			if (!empty($filter_settings[$group][$type][$item['id']])) {
				$option_label = $filter_settings[$group][$type][$item['id']];
			}
			else if (!empty($filter_settings[$group][$type][$item['string']])) {
				$option_label = $filter_settings[$group][$type][$item['string']];
			}

			$is_hidden = false;
			if (!empty($filter_settings['hiddenfilters'][$group][$type])) {
				$is_hidden = in_array($item['id'], $filter_settings['hiddenfilters'][$group][$type]);
			}

			if (!empty($page_filter_settings['hiddenfilters'][$page][$type])) {
				$is_hidden = in_array($item['id'], $page_filter_settings['hiddenfilters'][$page][$type]);
			}

			$show_only = true;
			if (!empty($page_filter_settings['showonlyfilters'][$page][$type])) {
				$show_only = in_array($item['id'], $page_filter_settings['showonlyfilters'][$page][$type]);
			}			

			if (!$is_hidden && $show_only) {
				$option_label = !empty($option_label) ? $option_label : $item['string'];

                $selected = (strlen($selected_value) && strtolower($selected_value) == strtolower($item['value'])) ? ' selected="selected"' : '';

				$options_html .= '<option value="' . esc_attr($item['value']) . '"' . $selected.'>' . esc_html($option_label) . '</option>';
			}
		}

		if (strlen($options_html) == 0) {
			return '';
		}

		$filter_html = '<select id="arlo-filter-' . esc_attr($type) . '" class="arlo-filter-' . esc_attr($type) . '" name="arlo-' . esc_attr($type) . '">';
		
		if (!is_null($label))
			$filter_html .= '<option value="">' . esc_html($label) . '</option>';

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
                $p_link = self::get_rich_snippet_field($presenter,"p_viewuri");
            break;  
            default:
            	$p_post_id = empty($presenter['post_id']) ? $presenter['p_post_id'] : $presenter['post_id'];
                $p_link = get_permalink($p_post_id);
            break;
        }

        $p_link = \Arlo\Utilities::get_absolute_url($p_link);

        $performer["url"] = $p_link;

        if (!empty($presenter["p_profile"])) {
        	$performer["description"] = strip_tags( $presenter["p_profile"] );
        }

        $same_as = array();

        if (!empty($presenter["p_twitterid"])) {
        	array_push($same_as,"https://www.twitter.com/".$presenter["p_twitterid"]);
        }

        if (!empty($presenter["p_facebookid"])) {
        	array_push($same_as,"https://www.facebook.com/".$presenter["p_facebookid"]);
        }

        if (!empty($presenter["p_linkedinid"])) {
        	array_push($same_as,"https://www.linkedin.com/".$presenter["p_linkedinid"]);
        }

        if (!empty($same_as)) {
	        $performer["sameAs"] = $same_as;
        }

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
		global $post, $wpdb;
	
		$arlo_region = \Arlo_For_Wordpress::get_region_parameter();
		
		// eventtemplate page using Post ID
		if($post->post_type === 'arlo_event') {
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
				" . (empty($arlo_region) ? "" : " AND $t2.e_region = '" . esc_sql($arlo_region) . "'") . "
				", ARRAY_A);
		}
		else {
			$t1 = "{$wpdb->prefix}arlo_events";
			
			$items = $wpdb->get_results("
				SELECT 
					$t1.e_isonline, 
					$t1.e_timezone_id 
				FROM 
					$t1
				WHERE 
					$t1.e_isonline = 1 
				AND 
					$t1.e_parent_arlo_id = 0
				AND 
					$t1.import_id = $import_id
				" . (empty($arlo_region) ? "" : " AND $t1.e_region = '" . esc_sql($arlo_region) . "'") . "
				", ARRAY_A);
		}
		
		if(empty($items)) {
			return '';
		}
		
		$content = '<form method="GET" class="arlo-timezone">';
		$content .= '<select name="timezone"><option value="">' . __('Select a time zone', 'arlo-for-wordpress') . '</option>';

		$plugin = Arlo_For_Wordpress::get_instance();
		$timezones = $plugin->get_timezone_manager()->get_indexed_timezones();

		foreach($timezones as $timezone_id => $timezone) {
			$selected = false;
			if (!empty($timezone['id'])) {
				$timezone_id = $timezone['id'];
			}

			$timezone_windows_tz_id = $timezone['windows_tz_id'];
			$timezone_get =  \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'timezone');


			if ( (!empty($timezone_get) && $timezone_get == $timezone_id) || (empty($timezone_get) && isset($timezone_id) && $timezone_id == $items[0]['e_timezone_id']) ) {
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

	/**
     * Shortcode for event template / catalogue breadcrumbs
     * @param  string $content
     * @param  array  $atts
     * @param  string $shortcode_name
     * @param  integer $import_id
     * @return string
     */
    private static function shortcode_breadcrumbs($content = '', $atts = [], $shortcode_name = '', $import_id = ''){
        global $post;

        $settings = get_option('arlo_settings');

        $origin_txt = "";
        $origin_url = "#";
        $is_single = true;

        foreach ($settings['post_types'] as $post_type => $config) {
            if ($config['posts_page'] == $post->ID){
                $is_single = false;
                $origin_txt = $post->post_title;
                $origin_url = get_page_link( $post );
                break;
            }
        }
        if ( $is_single && $post ){
            $post_type = str_replace('arlo_', '', $post->post_type);
            if (isset($settings['post_types'][$post_type])){
                $origin_id = $settings['post_types'][$post_type]['posts_page'];
                $origin_txt = get_the_title( $origin_id );
                $origin_url = get_page_link( $origin_id );
            }
        }

        // We have failed...
        if ( empty( $origin_txt ) ){ return ''; }

        $html = '<div class="arlo-breadcrumbs breadcrumbs">
            <div class="outer">
                <div class="inner">
                    <ul>
                        <li class="root">
                            <a href="' . esc_url($origin_url) . '">' . esc_html($origin_txt) . '</a>
                        </li>';

        $arlo_category = \Arlo\Utilities::clean_string_url_parameter('arlo-category');
        $cat_slug = (!empty($arlo_category) ? $arlo_category : '');
        $cat = null;

        if (!empty($cat_slug)){
            $cat = \Arlo\Entities\Categories::get(array('slug' => $cat_slug), null, $import_id);

            $tree = \Arlo\Entities\Categories::get_tree_from_child($cat->c_arlo_id, $import_id);
            $arlo_region = \Arlo_For_Wordpress::get_region_parameter();

            foreach ($tree as $key => $currentCategory) {
                $categoryUrl = $origin_url . (!empty($arlo_region) ? 'region-' . $arlo_region . '/' : '') . ($currentCategory->c_parent_id != 0 ? 'cat-' . esc_attr($currentCategory->c_slug) : '');
                $html .= '<li>
                    <a href="' . esc_url( user_trailingslashit( $categoryUrl ) ) . '">' . esc_html( $currentCategory->c_name ) . '</a>
                </li>';
            }
        }

        if ( $is_single && !empty( $post->post_title ) ) {
            $html .= '<li class="current"><a href="#">'.esc_html($post->post_title).'</a></li>';
        }

        $html .= '</ul>
                </div>
            </div>
        </div>';

        return $html;
    }

	public static function get_advertised_offers($id, $id_field, $import_id) {
		global $wpdb;
               
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();

        $cache_key = md5( serialize( array( $id => $id_field ) ) );
        $cache_category = 'ArloOffers';

        if($cached = wp_cache_get($cache_key, $cache_category)) {
            return $cached;
        }

        $sql = "
        SELECT 
            offer.*,
            replaced_by.o_label AS replacement_label,
            replaced_by.o_isdiscountoffer AS replacement_discount,
            replaced_by.o_currencycode AS replacement_currency_code,
            replaced_by.o_formattedamounttaxexclusive AS replacement_formatted_amount_taxexclusive,
			replaced_by.o_formattedamounttaxinclusive AS replacement_formatted_amount_taxinclusive,
            replaced_by.o_message AS replacement_message,
            replaced_by.o_offeramounttaxexclusive AS replacement_amount_taxexclusive,
            replaced_by.o_offeramounttaxinclusive AS replacement_amount_taxinclusive
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

        wp_cache_add( $cache_key, $offers, $cache_category, 30 );

        return $offers;
	}

	public static function get_offers_snippet_data($id, $id_field, $import_id, $price_field) {
        $offers_array = self::get_advertised_offers($id, $id_field, $import_id);

        $high_price = 0;
        $low_price = 0;
        $currency = "";

        foreach ($offers_array as $offer) {
	        $replacement_price_field = strpos('inclusive',$price_field) !== false ? "replacement_amount_taxinclusive" : "replacement_amount_taxexclusive" ;

        	$low_price = ( $offer[$price_field] < $low_price || $low_price == 0 ? $offer[$price_field] : $low_price );
        	$high_price = ( $offer[$price_field] > $high_price || $high_price == 0 ? $offer[$price_field] : $high_price );

        	if ( array_key_exists($replacement_price_field, $offer) && !empty($offer[$replacement_price_field]) ) {
	        	$low_price = ( $offer[$replacement_price_field] < $low_price ? $offer[$replacement_price_field] : $low_price );
	        	$high_price = ( $offer[$replacement_price_field] > $high_price ? $offer[$replacement_price_field] : $high_price );
        	}

        	$currency = $offer['o_currencycode'];
        }

        return array(
        	'high_price' => $high_price,
        	'low_price' => $low_price,
        	'currency' => $currency
        );
	}

	public static function advertised_offers($id, $id_field, $import_id, $is_tax_exempt = false) {
		global $wpdb;
               
        $regions = get_option('arlo_regions');	
        
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && is_array($regions) && \Arlo\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');	

        $t1 = "{$wpdb->prefix}arlo_offers";
        
        $offers_array = self::get_advertised_offers($id, $id_field, $import_id);

		$offers = '<ul class="arlo-list arlo-event-offers">';
		
        $settings = get_option('arlo_settings');  
        $price_setting = (isset($settings['price_setting']) && $is_tax_exempt != true ? esc_attr($settings['price_setting']) : ARLO_PLUGIN_PREFIX . '-exclgst');
		$free_text = (isset($settings['free_text']) ? esc_attr($settings['free_text']) : __('Free', 'arlo-for-wordpress'));

		if (empty($offers_array)) { return ''; }
		
        foreach($offers_array as $offer) {

            extract($offer);
                    
            $amount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $o_offeramounttaxexclusive : $o_offeramounttaxinclusive;
            $famount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $o_formattedamounttaxexclusive : $o_formattedamounttaxinclusive;

            // set to true if there is a replacement offer returned for this event offer
            $replaced = (!empty($replacement_formatted_amount_taxexclusive) && !empty($replacement_formatted_amount_taxinclusive));

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
                // only include the excl. tax if the offer is not replaced and not tax exempt
                $offers .= $replaced ? '' : (!$is_tax_exempt ? '<span class="arlo-price-tax">' . esc_html(($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__('excl. %s', 'arlo-for-wordpress'), $o_taxrateshortcode) : sprintf(__('incl. %s', 'arlo-for-wordpress'), $o_taxrateshortcode))) . '</span>'  : '');
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
				$offers .= '<span class="amount">' . ($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $replacement_formatted_amount_taxexclusive : $replacement_formatted_amount_taxinclusive) . '</span>';
				
				if (!$is_tax_exempt) {
					$offers.= '<span class="arlo-price-tax">' . esc_html(($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__('excl. %s', 'arlo-for-wordpress'), $o_taxrateshortcode) : sprintf(__('incl. %s', 'arlo-for-wordpress'), $o_taxrateshortcode))) . '</span>';
				}

                // display replacement offer message if there is one
                $offers .= (!is_null($replacement_message) || $replacement_message != '') ? ' ' . esc_html($replacement_message) : '';

            } // end if

            $offers .= '</span></li>';

        } // end foreach

        $offers .= '</ul>';

        return $offers;
	}

	public static function get_rich_snippet_field($event, $field) {
		if (!empty($event)) {
			return array_key_exists($field, $event) ? ( !empty($event[$field]) ? $event[$field] : '' ) : '';
		}
	}

    public static function get_template_permalink($post_name, $region) {
        if(!isset($post_name)) return '';
        
        $region_link_suffix = '';

        $regions = get_option('arlo_regions');

        if (!empty($region) && is_array($regions) && count($regions)) {
            $arlo_region = $region;
        } else {
            $arlo_region = get_query_var('arlo-region', '');
            $arlo_region = (!empty($arlo_region) && is_array($regions) && \Arlo\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');
        }

        if (!empty($arlo_region)) {
            $region_link_suffix = 'region-' . $arlo_region . '/';
        }
        
        $et_id = arlo_get_post_by_name($post_name, 'arlo_event');

        return get_permalink($et_id) . $region_link_suffix;
    }


    public static function get_custom_shortcodes($type) {
    	$shortcodes = array();

    	foreach(\Arlo_For_Wordpress::$templates as $shortcode_name => $shortcode) {
    		if ( isset($shortcode["type"]) ) {
    			if (is_string($type) && $shortcode["type"] == $type ) {
    				$shortcodes[$shortcode_name] = $shortcode;
    			} else if (is_array($type) && in_array($shortcode["type"], $type)) {
    				$shortcodes[$shortcode_name] = $shortcode;
    			}
    		}
    	}

    	return $shortcodes;
    }


    public static function get_template_name($shortcode_name,$default_shortcode_name,$default_template_name) {
        $shortcode_name_root = str_replace('arlo_', '', $shortcode_name);
        return $shortcode_name_root != $default_shortcode_name ? $shortcode_name_root : $default_template_name;
	}


	public static function build_custom_link($text, $url, $cssclass) {
        $link = '';

        $open = sprintf('<a href="%s" class="%s">', esc_url($url), esc_attr($cssclass));
        $close = '</a>';

		// Allow syntax: "Before link and %s link itself %s"
        if (strpos($text, '%s')) {
            $parts = explode('%s', esc_html($text));
            $parts[1] = $open . $parts[1] . $close;
            $link = implode('', $parts);
        } else {
            $link = $open . esc_html($text) . $close;
		}
		
		return $link;
	}

}