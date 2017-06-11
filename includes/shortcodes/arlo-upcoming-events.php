<?php
namespace Arlo\Shortcodes;

use Arlo\Entities\Categories as CategoriesEntity;

class UpcomingEvents {
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
            
    }

    private static function shortcode_upcoming_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;
        
        $templates = arlo_get_option('templates');
        $content = $templates['upcoming']['html'];
        return do_shortcode($content);        
    }   

    private static function shortcode_upcoming_list_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        
        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));

        $sql = self::generate_list_sql($atts, $import_id, true);        

        $items = $wpdb->get_results($sql, ARRAY_A);
            
        $num = $wpdb->num_rows;

        return arlo_pagination($num,$limit);        
    }  

    private static function shortcode_upcoming_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        $settings = get_option('arlo_settings');

        $output = '';

        $sql = self::generate_list_sql($atts, $import_id);	
             
        $items = $wpdb->get_results($sql, ARRAY_A);

        if(empty($items)) :
        
            $no_event_text = !empty($settings['noevent_text']) ? $settings['noevent_text'] : __('No events to show', 'arlo-for-wordpress');
            $output = '<p class="arlo-no-results">' . $no_event_text . '</p>';
            
        else :

            $previous = null;
            foreach($items as $item) {

                if(is_null($previous) || date('m',strtotime($item['e_startdatetime'])) != date('m',strtotime($previous['e_startdatetime']))) {
                    $item['show_divider'] = strftime('%B', strtotime($item['e_startdatetime']));
                }

                $GLOBALS['arlo_event_list_item'] = $item;
                $GLOBALS['arlo_eventtemplate'] = $item;

                $output .= do_shortcode($content);

                unset($GLOBALS['arlo_event_list_item']);
                unset($GLOBALS['arlo_eventtemplate']);

                $previous = $item;

            }

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

        $offer = ($amount > 0) ? '<span class="arlo-amount">' . $famount .'</span> <span class="arlo-price-tax">'. 
                ($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__(' excl. %s', 'arlo-for-wordpress'), $tax) : sprintf(__(' incl. %s', 'arlo-for-wordpress'), $tax)). '</span>' 
                : '<span class="arlo-amount">' . htmlentities($free_text, ENT_QUOTES, "UTF-8") . '</span>';

        return $offer;        
    }    

    private static function shortcode_upcoming_event_filters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;

        extract(shortcode_atts(array(
            'filters'	=> 'category,month,location,delivery',
            'resettext'	=> __('Reset', 'arlo-for-wordpress'),
            'buttonclass'   => 'button'
        ), $atts, $shortcode_name, $import_id));

        $filters_array = explode(',',$filters);
        
        $settings = get_option('arlo_settings');

        $page_link = get_permalink(get_post($post));
            
        $filter_html = '<form class="arlo-filters" method="get" action="' . $page_link . '">';

        $filter_group = "upcoming";
            
        foreach($filters_array as $filter) :

            switch($filter) :

                case 'category' :
                    //root category select
                    $cats = CategoriesEntity::getTree(0, 1, 0, $import_id);	
                    if (!empty($cats)) {
                        $cats = CategoriesEntity::getTree($cats[0]->c_arlo_id, 100, 0, $import_id);
                    }

                    if (is_array($cats)) {
                        $filter_html .= Shortcodes::create_filter($filter, CategoriesEntity::child_categories($cats), __('All categories', 'arlo-for-wordpress'),$filter_group);					
                    }

                    break;
                case 'delivery' :
                    $filter_html .= Shortcodes::create_filter($filter, \Arlo_For_Wordpress::$delivery_labels, __('All delivery options', 'arlo-for-wordpress'),$filter_group);

                    break;                                    
                case 'month' :
                    $months = array();

                    $currentMonth = (int)date('m');

                    for ($x = $currentMonth; $x < $currentMonth + 12; $x++) {
                        $date = mktime(0, 0, 0, $x, 1);
                        $months[$x]['string'] = strftime('%B', $date);
                        $months[$x]['value'] = date('Ym01', $date) . ':' . date('Ymt', $date);

                    }

                    $filter_html .= Shortcodes::create_filter($filter, $months, __('All months', 'arlo-for-wordpress'),$filter_group);

                    break;
                case 'location' :
                    $t1 = "{$wpdb->prefix}arlo_events";

                    $items = $wpdb->get_results(
                        "SELECT 
                            DISTINCT e.e_locationname
                        FROM 
                            $t1 e 
                        WHERE 
                            e_locationname != ''
                        AND
                            import_id = $import_id
                        GROUP BY 
                            e.e_locationname 
                        ORDER BY 
                            e.e_locationname", ARRAY_A);

                    $locations = array();

                    foreach ($items as $item) {
                        $locations[] = array(
                            'string' => $item['e_locationname'],
                            'value' => $item['e_locationname'],
                        );
                    }

                    $filter_html .= Shortcodes::create_filter($filter, $locations, __('All locations', 'arlo-for-wordpress'),$filter_group);

                    break;          
                case 'eventtag' :
                    $items = $wpdb->get_results(
                        "SELECT DISTINCT
                            t.id,
                            t.tag
                        FROM 
                            {$wpdb->prefix}arlo_events_tags AS etag
                        LEFT JOIN 
                            {$wpdb->prefix}arlo_tags AS t
                        ON
                            t.id = etag.tag_id
                        AND
                            t.import_id = etag.import_id
                        WHERE 
                            etag.import_id = $import_id
                        ORDER BY tag", ARRAY_A);

                    $tags = array();

                    foreach ($items as $item) {
                        $tags[] = array(
                            'string' => $item['tag'],
                            'value' => $item['tag'],
                        );
                    }

                    $filter_html .= Shortcodes::create_filter($filter, $tags, __('Select tag', 'arlo-for-wordpress'),$filter_group);				
                    
                    break;

                case 'presenter' :
                    $items = $wpdb->get_results(
                        "SELECT DISTINCT
                            p.p_arlo_id,
                            p.p_firstname,
                            p.p_lastname
                        FROM 
                            {$wpdb->prefix}arlo_events_presenters AS epresenter
                        LEFT JOIN 
                            {$wpdb->prefix}arlo_presenters AS p
                        ON
                            p.p_arlo_id = epresenter.p_arlo_id
                        WHERE 
                            epresenter.import_id = $import_id
                        ORDER BY p_firstname", ARRAY_A);

                    $presenters = array();

                    foreach ($items as $item) {
                        if (!is_null($item['p_firstname']) && !is_null($item['p_firstname'])) {
                            $presenters[] = array(
                                'string' => $item['p_firstname'] . " " . $item['p_lastname'],
                                'value' => $item['p_arlo_id'] . "-" . $item['p_firstname'] . "-" . $item['p_lastname'],
                            );
                        }
                    }

                    $filter_html .= Shortcodes::create_filter($filter, $presenters, __('All presenters', 'arlo-for-wordpress'),$filter_group);
                    
                    break;  


            endswitch;
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
        $regions = get_option('arlo_regions');
        $parameters = [];

        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $page = !empty($_GET['paged']) ? intval($_GET['paged']) : intval(get_query_var('paged'));
        $offset = ($page > 0) ? $page * $limit - $limit: 0 ;

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

        $join = '';
        $where = 'WHERE CURDATE() < DATE(e.e_startdatetime)  AND e_parent_arlo_id = 0 AND e.import_id = %d';
        $parameters[] = $import_id;

        $arlo_location = \Arlo\Utilities::clean_string_url_parameter('arlo-location');
        $arlo_category = \Arlo\Utilities::clean_string_url_parameter('arlo-category');
        $arlo_delivery = \Arlo\Utilities::clean_int_url_parameter('arlo-delivery');
        $arlo_month = \Arlo\Utilities::clean_string_url_parameter('arlo-month');
        $arlo_eventtag = \Arlo\Utilities::clean_string_url_parameter('arlo-eventtag');
        $arlo_presenter = \Arlo\Utilities::clean_string_url_parameter('arlo-presenter');
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && \Arlo\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');        
        
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

        if(!empty($arlo_category)) :
            $where .= ' AND c.c_arlo_id = %d';
            $parameters[] = intval(current(explode('-', $arlo_category)));
        endif;

        if(isset($arlo_delivery) && strlen($arlo_delivery) && is_numeric($arlo_delivery)) :
            $where .= ' AND e.e_isonline = %d';
            $parameters[] = intval($arlo_delivery);
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
                e.e_locationname, 
                c.c_arlo_id
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
            v.v_post_name, 
            v.v_post_id,
            c.c_arlo_id            
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
        LEFT JOIN 
            $t5 etc
        ON 
            et.et_arlo_id = etc.et_arlo_id 
        AND 
            et.import_id = etc.import_id
        LEFT JOIN 
            $t6 c
        ON 
            c.c_arlo_id = etc.c_arlo_id
        AND
            c.import_id = etc.import_id
        $join
        $where
        GROUP BY 
            etc.et_arlo_id, e.e_id
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