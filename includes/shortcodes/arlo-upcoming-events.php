<?php
namespace Arlo\Shortcodes;

use Arlo\Entities\Categories as CategoriesEntity;

class UpcomingEvents {
    public static $upcoming_list_item_atts = [];

    public static function init() {
        $class = new \ReflectionClass(__CLASS__);

        $shortcodes = array_filter($class->getMethods(), function($method) {
            return strpos($method->name, 'shortcode_') === 0;
        });

        foreach ($shortcodes as $shortcode) {
            $shortcode_name = str_replace('shortcode_', '', $shortcode->name);

            Shortcodes::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id) {
                $method_name = 'shortcode_' . str_replace('arlo_', '', $shortcode_name);
                return self::$method_name($content, $atts, $shortcode_name, $import_id);
            });
        }

        $custom_shortcodes = Shortcodes::get_custom_shortcodes('upcoming');

        foreach ($custom_shortcodes as $shortcode_name => $shortcode) {
            Shortcodes::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id) {
                return self::shortcode_upcoming_list($content = '', $atts, $shortcode_name, $import_id);
            });
        }
    }

    private static function shortcode_upcoming_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;

        $filter_settings = get_option('page_filter_settings', []);               

        $template_name = Shortcodes::get_template_name($shortcode_name,'upcoming_list','upcoming');
        $templates = arlo_get_option('templates');
        $content = $templates[$template_name]['html'];
        
        self::$upcoming_list_item_atts = self::get_upcoming_atts($atts, $import_id);
        $category_parameter = \Arlo\Utilities::clean_string_url_parameter('arlo-category');
        $templatetag_parameter = \Arlo\Utilities::clean_string_url_parameter('arlo-templatetag');

        //category
        if (!empty($atts["category"])) {
            $GLOBALS['arlo_filter_base']['category'] = \Arlo\Utilities::convert_string_array_to_int_array($atts["category"]);
        } else if (isset($filter_settings['showonlyfilters']) && isset($filter_settings['showonlyfilters'][$template_name]) && isset($filter_settings['showonlyfilters'][$template_name]['category'])) {
            $GLOBALS['arlo_filter_base']['category'] = array_values($filter_settings['showonlyfilters'][$template_name]['category']);
            if (empty($category_parameter))
                self::$upcoming_list_item_atts['category'] = implode(',',$GLOBALS['arlo_filter_base']['category']);
        }

        //categoryhidden
        if (!empty($atts["categoryhidden"])) {
            $GLOBALS['arlo_filter_base']['categoryhidden'] = \Arlo\Utilities::convert_string_array_to_int_array($atts["categoryhidden"]);
        } else if (isset($filter_settings['hiddenfilters']) && isset($filter_settings['hiddenfilters'][$template_name]) && isset($filter_settings['hiddenfilters'][$template_name]['category'])) {
            $GLOBALS['arlo_filter_base']['categoryhidden'] = array_values($filter_settings['hiddenfilters'][$template_name]['category']);
            self::$upcoming_list_item_atts['categoryhidden'] = implode(',',$GLOBALS['arlo_filter_base']['categoryhidden']);
        }

        //templatetag
        if (!empty($atts["templatetag"])) {
            $GLOBALS['arlo_filter_base']['templatetag'] = \Arlo\Entities\Tags::get_tag_ids_by_tag($atts["templatetag"], $import_id);
        } else if (isset($filter_settings['showonlyfilters']) && isset($filter_settings['showonlyfilters'][$template_name]) && isset($filter_settings['showonlyfilters'][$template_name]['templatetag'])) {
            $GLOBALS['arlo_filter_base']['templatetag'] = \Arlo\Entities\Tags::get_tag_ids_by_tag($filter_settings['showonlyfilters'][$template_name]['templatetag'], $import_id);
            if (empty($templatetag_parameter))
                self::$upcoming_list_item_atts['templatetag'] = $GLOBALS['arlo_filter_base']['templatetag'];
        }

        //templatetag hidden
        if (!empty($atts["templatetaghidden"])) {
            $GLOBALS['arlo_filter_base']['templatetaghidden'] = \Arlo\Entities\Tags::get_tag_ids_by_tag($atts["templatetaghidden"], $import_id);
        } else if (isset($filter_settings['hiddenfilters']) && isset($filter_settings['hiddenfilters'][$template_name]) && isset($filter_settings['hiddenfilters'][$template_name]['templatetag'])) {
            $GLOBALS['arlo_filter_base']['templatetaghidden'] = \Arlo\Entities\Tags::get_tag_ids_by_tag($filter_settings['hiddenfilters'][$template_name]['templatetag'], $import_id);
            self::$upcoming_list_item_atts['templatetaghidden'] = $GLOBALS['arlo_filter_base']['templatetaghidden'];
        }
        
        return do_shortcode($content);        
    }

    private static function get_upcoming_atts($atts, $import_id) {
        $new_atts = [];

        $templatetag = \Arlo\Entities\Tags::get_tag_ids_by_tag(\Arlo\Utilities::get_att_string('templatetag', $atts), $import_id);
        
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'location', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'category', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'categoryhidden', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'search', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_int', 'delivery', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_int', 'templateid', $atts);       
        $new_atts = \Arlo\Utilities::process_att($new_atts, null, 'templatetag', $atts, $templatetag);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'eventtag', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'presenter', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'month', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'state', $atts);
        
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo_For_Wordpress::get_region_parameter', 'region');

        return $new_atts;
    }

    private static function shortcode_upcoming_list_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        
        $atts['limit'] = intval(isset(self::$upcoming_list_item_atts['limit']) ? self::$upcoming_list_item_atts['limit'] : isset($atts['limit']) && is_numeric($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));

        $atts = array_merge($atts,self::$upcoming_list_item_atts);

        $sql = self::generate_list_sql($atts, $import_id, true);        

        $items = $wpdb->get_results($sql, ARRAY_A);
        
        $num = $wpdb->num_rows;

        return arlo_pagination($num, $atts['limit']);        
    }  

    private static function shortcode_upcoming_widget_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $atts = shortcode_atts(array(
            'eventtag' => '',
            'templatetag' => '',
            'limit' => ''
        ), $atts, $shortcode_name, $import_id);

        if (isset($atts['limit']) && is_numeric($atts['limit'])) {
            self::$upcoming_list_item_atts['limit'] = $atts['limit'];
        }

        if (!empty($atts['eventtag'])) {
            self::$upcoming_list_item_atts['eventtag'] = trim($atts['eventtag']);
        }

        if (!empty($atts['templatetag'])) {
            self::$upcoming_list_item_atts['templatetag'] = trim($atts['templatetag']);
        }

        $template = $content ? $content : arlo_get_template('upcoming_widget');

        return do_shortcode($template);
    }

    private static function shortcode_upcoming_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;

        if (!empty($atts['limit'])) {
            self::$upcoming_list_item_atts['limit'] = $atts['limit'];
        }

        $settings = get_option('arlo_settings');

        $output = '';

        if (empty($atts)) {
            $atts = [];
        }

        $atts = array_merge($atts, self::$upcoming_list_item_atts);      

        $sql = self::generate_list_sql($atts, $import_id);

        $items = $wpdb->get_results($sql, ARRAY_A);

        if(empty($items)) :
        
            $no_event_text = !empty($settings['noevent_text']) ? $settings['noevent_text'] : __('No events to show', 'arlo-for-wordpress');
            $output = '<p class="arlo-no-results">' . esc_html($no_event_text) . '</p>';
            
        else :
            $previous = null;

            $snippet_list_items = array();

            foreach($items as $key => $item) {
                if(is_null($previous) || date('m',strtotime($item['e_startdatetime'])) != date('m',strtotime($previous['e_startdatetime']))) {
                    $item['show_divider'] = strftime('%B', strtotime($item['e_startdatetime']));
                }

                $GLOBALS['arlo_event_list_item'] = $item;
                $GLOBALS['arlo_eventtemplate'] = $item;

                $list_item_snippet = array();
                $list_item_snippet['@type'] = 'ListItem';
                $list_item_snippet['position'] = $key + 1;
                $list_item_snippet['url'] = $item['e_viewuri'];

                array_push($snippet_list_items,$list_item_snippet);

                $output .= do_shortcode($content);

                unset($GLOBALS['arlo_event_list_item']);
                unset($GLOBALS['arlo_eventtemplate']);

                $previous = $item;

            }

            $item_list = array();
            $item_list['@type'] = 'ItemList';
            $item_list['itemListElement'] = $snippet_list_items;

            $output .= Shortcodes::create_rich_snippet( json_encode($item_list) );


        endif;

        return $output;        
    }  

    private static function shortcode_upcoming_offer($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $settings = get_option('arlo_settings');  
        $price_setting = (isset($settings['price_setting'])) ? $settings['price_setting'] : ARLO_PLUGIN_PREFIX . '-exclgst';
        $free_text = (isset($settings['free_text'])) ? $settings['free_text'] : __('Free', 'arlo-for-wordpress');
                
        $amount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $GLOBALS['arlo_event_list_item']['o_offeramounttaxexclusive'] : $GLOBALS['arlo_event_list_item']['o_offeramounttaxinclusive'];
        $famount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $GLOBALS['arlo_event_list_item']['o_formattedamounttaxexclusive'] : $GLOBALS['arlo_event_list_item']['o_formattedamounttaxinclusive'];
        $tax = $GLOBALS['arlo_event_list_item']['o_taxrateshortcode'];

        $offer = ($amount > 0) ? '<span class="arlo-amount">' . $famount .'</span> <span class="arlo-price-tax">'. esc_html(($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__(' excl. %s', 'arlo-for-wordpress'), $tax) : sprintf(__(' incl. %s', 'arlo-for-wordpress'), $tax))). '</span>' 
                : '<span class="arlo-amount">' . esc_html($free_text) . '</span>';

        return $offer;        
    }

    private static function shortcode_upcoming_event_filters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;

        extract(shortcode_atts(array(
            'filters'   => 'category,month,location,delivery',
            'resettext' => __('Reset', 'arlo-for-wordpress'),
            'buttonclass'   => 'button'
        ), $atts, $shortcode_name, $import_id));

        $filters_array = explode(',',$filters);
        
        $settings = get_option('arlo_settings');
        
        $page_type = \Arlo_For_Wordpress::get_current_page_arlo_type();

        if (!empty($settings['post_types']['upcoming']['posts_page'])) {
            $page_link = get_permalink(get_post($settings['post_types'][$page_type]['posts_page']));
        } else {
            $page_link = get_permalink(get_post($post));
        }
            
        $filter_html = '<form class="arlo-filters" method="get" action="' . $page_link . '">';

        $filter_group = \Arlo_For_Wordpress::get_current_page_arlo_type();
            
        foreach(\Arlo_For_Wordpress::$available_filters['upcoming']['filters'] as $filter_key => $filter):

            $att = (isset(self::$upcoming_list_item_atts[$filter_key]) ? strval(self::$upcoming_list_item_atts[$filter_key]) : '');

            if (!in_array($filter_key, $filters_array))
                continue;

            $items = Filters::get_filter_options($filter_key, $import_id);

            $filter_html .= Shortcodes::create_filter($filter_key, $items, __(\Arlo_For_Wordpress::$filter_labels[$filter_key], 'arlo-for-wordpress'),$filter_group,$att);
        endforeach;

        $filter_html .= '<div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' .  $page_link . '"> ';    
        $filter_html .= '<a href="' . $page_link . '" class="' . esc_attr($buttonclass) . '">' . htmlentities($resettext, ENT_QUOTES, "UTF-8") . '</a></div>';

        $filter_html .= '</form>';
        
        return $filter_html;        
    }       

    private static function shortcode_upcoming_region_selector($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        return Shortcodes::create_region_selector("upcoming");
    }

    private static function generate_list_sql($atts, $import_id, $for_pagination = false) {
        global $wpdb;
        $parameters = [];

        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $page = !empty($_GET['paged']) ? intval($_GET['paged']) : intval(get_query_var('paged'));
        $offset = ($page > 0) ? $page * $limit - $limit: 0;

        $t1 = "{$wpdb->prefix}arlo_events";
        $t2 = "{$wpdb->prefix}arlo_eventtemplates";
        $t3 = "{$wpdb->prefix}arlo_venues";
        $t4 = "{$wpdb->prefix}arlo_offers";
        $t5 = "{$wpdb->prefix}arlo_eventtemplates_categories";
        $t6 = "{$wpdb->prefix}arlo_categories";
        $t7 = "{$wpdb->prefix}arlo_events_tags";
        $t8 = "{$wpdb->prefix}arlo_tags";
        $t9 = "{$wpdb->prefix}arlo_events_presenters";
        $t10 = "{$wpdb->prefix}arlo_presenters";
        $t11 = "{$wpdb->prefix}arlo_eventtemplates_tags";

        $join = '';
        $where = 'WHERE CURDATE() < DATE(e.e_startdatetime)  AND e.e_parent_arlo_id = 0 AND e.import_id = %d';
        $parameters[] = $import_id;

        $arlo_location = !empty($atts['location']) ? $atts['location'] : null;
        $arlo_state = !empty($atts['state']) ? $atts['state'] : null;
        $arlo_category = !empty($atts['category']) ? $atts['category'] : null;
        $arlo_categoryhidden = !empty($atts['categoryhidden']) ? $atts['categoryhidden'] : null;
        $arlo_delivery = isset($atts['delivery']) ? $atts['delivery'] : null;
        $arlo_month = !empty($atts['month']) ? $atts['month'] : null;
        $arlo_eventtag = !empty($atts['eventtag']) ? $atts['eventtag'] : null;
        $arlo_templatetag = !empty($atts['templatetag']) ? $atts['templatetag'] : null;
        $arlo_templatetaghidden = isset($atts['templatetaghidden']) ? $atts['templatetaghidden'] : null;
        $arlo_presenter = !empty($atts['presenter']) ? $atts['presenter'] : null;
        $arlo_region = !empty($atts['region']) ? $atts['region'] : null;
        $arlo_templateid = !empty($atts['templateid']) ? $atts['templateid'] : null;

        if(!empty($arlo_month)) :
            $dates = explode(':',urldecode($arlo_month));
            $where .= ' AND (DATE(e.e_startdatetime) BETWEEN DATE(%s) AND DATE(%s))';
            $parameters[] = $dates[0];
            $parameters[] = $dates[1];
        endif;

        if(!empty($arlo_location)) :
            $where .= ' AND e.e_locationname = %s';
            $parameters[] = $arlo_location;
        endif;

        if(!empty($arlo_templateid)) :
            $where .= ' AND e.et_arlo_id = %d';
            $parameters[] = $arlo_templateid;
        endif;

        if(!empty($arlo_category) || !empty($arlo_categoryhidden)) :
            $arlo_category = \Arlo\Utilities::convert_string_array_to_int_array($arlo_category);
            $arlo_categoryhidden = \Arlo\Utilities::convert_string_array_to_int_array($arlo_categoryhidden);

            if (!empty($arlo_category)) {
                $where .= " AND c.c_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $arlo_category)) . ")";                
                $parameters = array_merge($parameters, $arlo_category);    
            }

            if (!empty($arlo_categoryhidden)) {
                //need to exclude all the child categories
                $categoriesnot_flatten_list = CategoriesEntity::get_flattened_category_list_for_filter($arlo_categoryhidden, [], $import_id);
                
                $where .= " AND (c.c_arlo_id NOT IN (" . implode(',', array_map(function() {return "%d";}, $categoriesnot_flatten_list)) . ") OR c.c_arlo_id IS NULL)";
                $parameters = array_merge($parameters, array_map(function($cat) { return $cat['id']; }, $categoriesnot_flatten_list));
            }

            $join .= "LEFT JOIN 
                    $t5 etc
                        ON 
                            etc.et_arlo_id = et.et_arlo_id 
                        AND 
                            etc.import_id = et.import_id

                    LEFT JOIN 
                    $t6 c
                        ON 
                            c.c_arlo_id = etc.c_arlo_id
                        AND
                            c.import_id = etc.import_id
                ";

        endif;

        if(isset($arlo_delivery) && strlen($arlo_delivery) && is_numeric($arlo_delivery)) :
            $where .= ' AND e.e_isonline = %d';
            $parameters[] = intval($arlo_delivery);
        endif;  
            
        if(!empty($arlo_state)) :
            $join .= "
                LEFT JOIN $t1 ce ON e.e_arlo_id = ce.e_parent_arlo_id AND e.import_id = ce.import_id
            ";

            $venues_query = $wpdb->prepare("SELECT v.v_arlo_id FROM $t3 v WHERE v.v_physicaladdressstate = %s", $arlo_state);
            $venues = implode(', ', array_map(function ($venue) {
              return $venue['v_arlo_id'];
            }, $wpdb->get_results( $venues_query, ARRAY_A)));

            $where .= " AND (ce.v_id IN (%s) OR v.v_arlo_id IN (%s))";

            $parameters[] = $venues;
            $parameters[] = $venues;
        endif;

        if(!empty($arlo_eventtag)) :
            $join .= " LEFT JOIN $t7 etag ON etag.e_id = e.e_id AND etag.import_id = e.import_id";

            if (!is_numeric($arlo_eventtag)) {
                $where .= ' AND tag.tag = %s';
                $parameters[] = $arlo_eventtag;
                $join .= " LEFT JOIN $t8 AS tag ON tag.id = etag.tag_id AND tag.import_id = etag.import_id";
            } else {
                $where .= " AND etag.tag_id = %d";
                $parameters[] = intval($arlo_eventtag);
            }
        endif;
        
        if(!empty($arlo_templatetag) || !empty($arlo_templatetaghidden)) :
            $join .= " LEFT JOIN $t11 ettag ON ettag.et_id = et.et_id AND ettag.import_id = et.import_id";

            if (!empty($arlo_templatetag)) {
                $where .= " AND ettag.tag_id IN (" . implode(',', array_map(function() {return "%d";}, $arlo_templatetag)) . ")";
                $parameters = array_merge($parameters, $arlo_templatetag);    
            }
            
            if (!empty($arlo_templatetaghidden)) {
                $where .= " AND (ettag.tag_id NOT IN (" . implode(',', array_map(function() {return "%d";}, $arlo_templatetaghidden)) . ") OR ettag.tag_id IS NULL)";
                $parameters = array_merge($parameters, $arlo_templatetaghidden);    
            }
        endif;

        if(!empty($arlo_presenter)) :
            $join .= " LEFT JOIN $t9 epresenter ON epresenter.e_id = e.e_id AND epresenter.import_id = e.import_id";
            $where .= " AND p_arlo_id = %d";
            $parameters[] = intval(current(explode('-', $arlo_presenter)));
        endif;      

        if (!empty($arlo_region)) {
            $where .= ' AND et.et_region = %s AND e.e_region = %s';
            $parameters[] = $arlo_region;
            $parameters[] = $arlo_region;
        }   

        $field_list = '
                DISTINCT e.e_id, 
                e.e_locationname
            ';
        $limit_field = $order = '';

        if (!$for_pagination) {
            $field_list = '
            e.e_id,
            e.e_arlo_id,
            e.et_arlo_id,
            e.e_code,
            e.e_name,
            e.e_startdatetime,
            e.e_finishdatetime,
            e.e_datetimeoffset,
            e.e_timezone,
            e.e_timezone_id,
            e.v_id,
            e.e_locationname,
            e.e_locationroomname,
            e.e_locationvisible,
            e.e_isfull,
            e.e_placesremaining,
            e.e_sessiondescription,
            e.e_notice,
            e.e_viewuri,
            e.e_registermessage,
            e.e_registeruri,
            e.e_providerorganisation,
            e.e_providerwebsite,
            e.e_isonline,
            e.e_parent_arlo_id,
            e.e_region,
            e.e_credits,
            et.et_id,
            et.et_name, 
            et.et_post_name, 
            et.et_post_id,
            et.et_descriptionsummary, 
            et.et_registerinteresturi, 
            et.et_region,
            et.et_viewuri,
            et.et_advertised_duration,
            o.o_formattedamounttaxexclusive, 
            o_offeramounttaxexclusive, 
            o.o_formattedamounttaxinclusive, 
            o_offeramounttaxinclusive, 
            o.o_taxrateshortcode, 
            v.v_name, 
            v.v_post_name, 
            v.v_post_id,
            v.v_physicaladdressline1,
            v.v_physicaladdressline2,
            v.v_physicaladdressline3,
            v.v_physicaladdressline4,
            v.v_physicaladdresssuburb,
            v.v_physicaladdresscity,
            v.v_physicaladdressstate,
            v.v_physicaladdresspostcode,
            v.v_physicaladdresscountry,
            v.v_geodatapointlatitude,
            v.v_geodatapointlongitude
            ';

            $order = '
            ORDER BY 
                e.e_startdatetime';

            $limit_field = "
            LIMIT 
                $offset, $limit";
        }
        
        $sql = "
        SELECT DISTINCT
            $field_list
        FROM 
            $t1 e 
        LEFT JOIN 
            $t2 et 
        ON 
            e.et_arlo_id = et.et_arlo_id 
        AND
            et.import_id = e.import_id
        LEFT JOIN 
            $t3 v
        ON
            e.v_id = v.v_arlo_id
        AND
            v.import_id = e.import_id
        INNER JOIN 
            (SELECT 
                * 
            FROM 
                $t4
            WHERE 
                o_order = 1
            AND
                import_id = $import_id
            ) o
        ON 
            e.e_id = o.e_id
        $join
        $where
        GROUP BY 
            et.et_arlo_id, e.e_id
        $order
        $limit_field";


        $query = $wpdb->prepare($sql, $parameters);

        if ($query) {
            return $query;
        } else {
            throw new \Exception("Couldn't prepapre SQL statement");
        }
    }
}