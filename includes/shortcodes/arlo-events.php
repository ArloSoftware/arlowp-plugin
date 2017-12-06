<?php
namespace Arlo\Shortcodes;

use Arlo\DateFormatter;

class Events {
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

        Shortcodes::add('event_list', function($content = '', $atts, $shortcode_name, $import_id) {
            return $content;
        });         
    }

    private static function shortcode_event_filters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {  
        global $post, $wpdb;

        extract(shortcode_atts(array(
            'filters'   => 'location',
            'resettext' => __('Reset', 'arlo-for-wordpress'),
            'buttonclass'   => 'button'
        ), $atts, $shortcode_name, $import_id));
        
        $filters_array = explode(',',$filters);

        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        
        $settings = get_option('arlo_settings');  

        $page_link = get_permalink(get_post($post));

        $filter_html = '';
        
        $filter_group = 'event';

        foreach($filters_array as $filter_key):

            if (!array_key_exists($filter_key, \Arlo_For_Wordpress::$available_filters[$filter_group]['filters']))
                continue;

            $items = \Arlo\Shortcodes\Filters::get_filter_options($filter_key, $import_id, $post->ID);

            $filter_html .= Shortcodes::create_filter($filter_key, $items, __(\Arlo_For_Wordpress::$filter_labels[$filter_key], 'arlo-for-wordpress'),$filter_group);

        endforeach; 
            
        if (!empty($filter_html)) {
            return '
            <form id="arlo-event-filter" class="arlo-filters" method="get" action="' . $page_link . '">
                ' . $filter_html . '
                <div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' .  $page_link . '">
                    <a href="' . $page_link . '" class="' . esc_attr($buttonclass) . '">' . htmlentities($resettext, ENT_QUOTES, "UTF-8") . '</a>
                </div>
            </form>';
        }
    }    

    private static function shortcode_event_tags($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_id'])) return '';
        
        global $wpdb;
        $output = '';
        $tags = [];
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'layout' => '',
            'prefix' => 'arlo-',
        ), $atts, $shortcode_name, $import_id));
        
        $items = $wpdb->get_results("
            SELECT 
                tag
            FROM 
                {$wpdb->prefix}arlo_tags AS t
            LEFT JOIN 
                {$wpdb->prefix}arlo_events_tags AS et 
            ON
                tag_id = id
            WHERE
                et.e_id = {$GLOBALS['arlo_event_list_item']['e_id']}
            AND 
                t.import_id = " . $import_id . "
            AND
                et.import_id = " . $import_id . "
            ", ARRAY_A);    
            
        foreach ($items as $t) {
            $tags[] = $t['tag'];
        }
        
        if (count($tags)) {
            switch($layout) {
                case 'list':
                    $output = '<ul class="arlo-event_tags-list">';
                    
                    foreach($tags as $tag) {
                        $output .= '<li>' . htmlentities($tag, ENT_QUOTES, "UTF-8") . '</li>';
                    }
                    
                    $output .= '</ul>';
                break;
                
                case 'class':
                
                    $classes = [];
                    foreach($tags as $tag) {
                        $classes[] = htmlentities(sanitize_title($prefix . $tag), ENT_QUOTES, "UTF-8");
                    }
                    
                    $output = implode(' ', $classes);
                    
                break;      
            
                default:
                        $output = '<div class="arlo-event_tags-list">' . implode(', ', array_map(function($tag) { return htmlentities($tag, ENT_QUOTES, "UTF-8"); }, $tags)) . '</div>';
                break;
            }   
        }
        
        return $output;        
    }

    private static function shortcode_event_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;
        $sql = self::generate_events_list_sql($atts, $import_id);

        $items = $wpdb->get_results($sql, ARRAY_A);
        
        $output = '';
        
        if (is_array($items) && count($items)) {
            unset($GLOBALS['no_event']);
            foreach($items as $key => $item) {
        
                $GLOBALS['arlo_event_list_item'] = $item;
                        
                if (!empty($atts['show']) && $key == $atts['show']) {
                    $output .= '</ul><ul class="arlo-list arlo-show-more-hidden events">';
                }
        
                $output .= do_shortcode($content);
        
                unset($GLOBALS['arlo_event_list_item']);
            }   
        } 
        
        return $output;        
    }

    private static function generate_events_list_sql($atts, $import_id) {
        global $post, $wpdb;
        $settings = get_option('arlo_settings');
        
        $where = '';
        $join = '';
        $parameters = [];
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        $arlo_location = \Arlo\Utilities::clean_string_url_parameter('arlo-location');
        $arlo_state = \Arlo\Utilities::clean_string_url_parameter('arlo-state');
        
        $t1 = "{$wpdb->prefix}arlo_eventtemplates";
        $t2 = "{$wpdb->prefix}arlo_events";
        $t3 = "{$wpdb->prefix}arlo_venues";
        $t5 = "{$wpdb->prefix}arlo_presenters";
        $t6 = "{$wpdb->prefix}arlo_offers";

        if (!empty($arlo_region)) {
            $where .= ' AND ' . $t1 . '.et_region = %s AND ' . $t2 . '.e_region = %s';
            $parameters[] = $arlo_region;
            $parameters[] = $arlo_region;
        }

        if (!empty($arlo_location)) {
            $where .= ' AND ' . $t2 .'.e_locationname = %s';
            $parameters[] = $arlo_location;
        };
        
        if (!empty($arlo_state)) {
            $join .= "
                LEFT JOIN $t2 ce ON $t2.e_arlo_id = ce.e_parent_arlo_id AND $t2.import_id = ce.import_id
            ";

            $venues_query = $wpdb->prepare("SELECT v.v_arlo_id FROM $t3 v WHERE v.v_physicaladdressstate = %s", $arlo_state);
            $venues = implode(', ', array_map(function ($venue) {
              return $venue['v_arlo_id'];
            }, $wpdb->get_results( $venues_query, ARRAY_A)));

            $where .= " AND (ce.v_id IN (%s) OR $t3.v_arlo_id IN (%s))";

            $parameters[] = $venues;
            $parameters[] = $venues;
        };        

        $sql = 
            "SELECT 
                $t2.*, 
                $t1.et_descriptionsummary,
                $t3.v_name,
                $t3.v_post_name,
                $t3.v_post_id,
                $t3.v_viewuri,
                $t3.v_id,
                $t3.v_physicaladdressline1,
                $t3.v_physicaladdressline2,
                $t3.v_physicaladdressline3,
                $t3.v_physicaladdressline4,
                $t3.v_physicaladdresssuburb,
                $t3.v_physicaladdresscity,
                $t3.v_physicaladdressstate,
                $t3.v_physicaladdresspostcode,
                $t3.v_physicaladdresscountry,
                $t3.v_geodatapointlatitude,
                $t3.v_geodatapointlongitude
            FROM 
                $t2
            LEFT JOIN 
                $t3
            ON 
                $t2.v_id = $t3.v_arlo_id
            LEFT JOIN 
                $t1
            ON 
                $t2.et_arlo_id = $t1.et_arlo_id
            AND
                $t1.import_id = $t2.import_id
            $join
            WHERE 
                $t1.import_id = $import_id
            AND
                $t1.et_post_id = $post->ID
            AND 
                $t2.e_parent_arlo_id = 0
            $where
            GROUP BY 
                e_arlo_id
            ORDER BY 
                $t2.e_startdatetime";

        if (count($parameters)) {
            return $wpdb->prepare($sql, $parameters);
        }


        return $sql;
    }

    private static function shortcode_event_code($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_code'])) return '';

        return htmlentities($GLOBALS['arlo_event_list_item']['e_code'], ENT_QUOTES, "UTF-8");        
    }

    private static function shortcode_event_name($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_name']) && !isset($GLOBALS['arlo_event_session_list_item']['e_code'])) return '';
        
        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        return htmlentities($event['e_name'], ENT_QUOTES, "UTF-8");        
    }

    private static function shortcode_event_notice($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_notice'])) return '';
        
        return htmlentities($GLOBALS['arlo_event_list_item']['e_notice'], ENT_QUOTES, "UTF-8");  
    }

    private static function shortcode_event_location($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_locationname']) && !isset($GLOBALS['arlo_event_session_list_item']['e_locationname'])) return '';

        // merge and extract attributes
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));
        
        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        $location = htmlentities($event['e_locationname'], ENT_QUOTES, "UTF-8");

        switch ($link) {
            case 'permalink': 
                if(!($event['e_isonline'] || $event['v_id'] == 0 || $event['e_locationvisible'] == 0)) {
                    $permalink = get_permalink(arlo_get_post_by_name($event['v_post_name'], 'arlo_venue'));
                }                   
            break;
            case 'viewuri': 
                if($event['e_locationvisible'] == 1) {
                    $permalink = $event['v_viewuri'];
                }
            break;            
            default: 
                $permalink = $link;
            break;
        }

        if (!empty($permalink)) {
            $location = '<a href="'.$permalink.'">'.$location.'</a>';    
        }
        
        return $location;
    }

    private static function shortcode_event_start_date($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_startdatetime']) && !isset($GLOBALS['arlo_event_session_list_item']['e_startdatetime'])) return '';

        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];
        
        return esc_html(self::event_date_formatter($atts, $event['e_startdatetime'], $event['e_datetimeoffset'], $event['e_isonline'], $event['e_timezone_id']));
    }

    private static function shortcode_event_end_date($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_finishdatetime']) && !isset($GLOBALS['arlo_event_session_list_item']['e_finishdatetime'])) return '';
        
        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        return esc_html(self::event_date_formatter($atts, $event['e_finishdatetime'], $event['e_datetimeoffset'], $event['e_isonline'], $event['e_timezone_id']));
    }

    private static function shortcode_event_session_description($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_sessiondescription'])) return '';

        return esc_html($GLOBALS['arlo_event_list_item']['e_sessiondescription']);
    }                    

    private static function shortcode_event_credits($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_credits'])) return '';
        $output = '';
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'layout' => 'list',
        ), $atts, $shortcode_name, $import_id));
        
        $credits = json_decode($GLOBALS['arlo_event_list_item']['e_credits']);
        
        if (is_array($credits) && count($credits)) {
            switch($layout) {
                default:
                    $output .= '<ul class="arlo-event-credits">';
                    foreach ($credits as $credit) {
                        $output .= '<li>' . htmlentities($credit->Type, ENT_QUOTES, "UTF-8") . ': ' . htmlentities($credit->Value, ENT_QUOTES, "UTF-8") . '</li>';
                    }
                    $output .= '</ul>';
                break;
            }   
        }   

        return $output;        
    }

    private static function shortcode_event_registration($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $isfull = $GLOBALS['arlo_event_list_item']['e_isfull'];
        $registeruri = $GLOBALS['arlo_event_list_item']['e_registeruri'];
        $registermessage = $GLOBALS['arlo_event_list_item']['e_registermessage'];
        $placesremaining = intval($GLOBALS['arlo_event_list_item']['e_placesremaining']);
            
        $class = (!empty($atts['class']) ? $atts['class'] : 'button' );

        $registration = '<div class="arlo-event-registration">';
        $registration .= (($isfull) ? '<span class="arlo-event-full">' . __('Event is full', 'arlo-for-wordpress') . '</span>' : '');
        // test if there is a register uri string, if so display the button
        if(!is_null($registeruri) && $registeruri != '') {
            $registration .= '<a class="' . esc_attr($class) . ' ' . (($isfull) ? 'arlo-waiting-list' : 'arlo-register') . '" href="'. esc_url($registeruri) . '" target="_blank">';
            $registration .= (($isfull) ? __('Join waiting list', 'arlo-for-wordpress') : __($registermessage, 'arlo-for-wordpress')) . '</a>';
        } else {
            $registration .= $registermessage;
        }

        if ($placesremaining > 0) {
            $registration .= '<span class="arlo-places-remaining">' . sprintf( _n( '%d place remaining', '%d places remaining', $placesremaining, 'arlo-for-wordpress' ), $placesremaining ) .'</span>';    
        }
        
        $registration .= '</div>';

        return $registration;        
    }

    private static function shortcode_event_offers($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        return Shortcodes::advertised_offers($GLOBALS['arlo_event_list_item']['e_id'], 'e_id', $import_id);
    }

    private static function get_event_presenters($import_id) {
        global $wpdb;

        $cache_key = md5( serialize( $GLOBALS['arlo_event_list_item']['e_id'] ) );
        $cache_category = 'ArloPresenters';

        if($cached = wp_cache_get($cache_key, $cache_category)) {
            return $cached;
        }

        $t1 = "{$wpdb->prefix}arlo_events_presenters";
        $t2 = "{$wpdb->prefix}arlo_presenters";

        $result = $wpdb->get_results("
        SELECT 
            p.p_firstname, 
            p.p_lastname, 
            p.p_post_name,
            p.p_post_id,
            p.p_viewuri 
        FROM 
            $t1 exp 
        INNER JOIN 
            $t2 p
        ON 
            exp.p_arlo_id = p.p_arlo_id 
        AND 
            exp.import_id = p.import_id
        WHERE 
            exp.e_id = {$GLOBALS['arlo_event_list_item']['e_id']}
        AND 
            p.import_id = $import_id
        GROUP BY 
            p.p_arlo_id
        ORDER BY 
            exp.p_order", ARRAY_A);

        wp_cache_add( $cache_key, $result, $cache_category, 30 );

        return $result;

    }

    private static function shortcode_event_presenters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {

        // merge and extract attributes
        extract(shortcode_atts(array(
            'layout' => '',
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $output = '';

        if ($layout == 'list') {
            $output .= '<ul class="arlo-list event-presenters">';
        }

        $items = self::get_event_presenters($import_id);

        $presenters = array();

        foreach($items as $item) {

            switch($link) {
                case 'yes':
                case 'permalink': 
                    $permalink = get_permalink(arlo_get_post_by_name($item['p_post_name'], 'arlo_presenter'));
                    break;
                case 'viewuri': 
                    $permalink = $item['p_viewuri'];
                    break;
                case 'false':
                    $permalink = '';
                    break;
                default: 
                    $permalink = $link;
            }

            $presenter_name = htmlentities($item['p_firstname'], ENT_QUOTES, "UTF-8") . ' ' . htmlentities($item['p_lastname'], ENT_QUOTES, "UTF-8");

            $presenters[] = ($layout == 'list' ? '<li>' : '') . (!empty($link) ? '<a href="' . $permalink . '">' . $presenter_name . '</a>' : $presenter_name) . ($layout == 'list' ? '<li>' : '');
        }

        $output .= implode(($layout == 'list' ? '' : ', '), $presenters);

        if ($layout == 'list') {
            $output .= '</ul>';
        }

        return $output;        
    }

    private static function shortcode_event_delivery($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $e_arlo_id = $GLOBALS['arlo_event_list_item']['e_arlo_id'];
        
        $output = \Arlo_For_Wordpress::$delivery_labels[$GLOBALS['arlo_event_list_item']['e_isonline']];

        return $output;        
    }

    private static function shortcode_event_provider($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $e_arlo_id = $GLOBALS['arlo_event_list_item']['e_arlo_id'];
            
        if (!empty($GLOBALS['arlo_event_list_item']['e_providerwebsite'])) {
            $output = '<a href="' . esc_attr($GLOBALS['arlo_event_list_item']['e_providerwebsite']) . '" target="_blank">' . htmlentities($GLOBALS['arlo_event_list_item']['e_providerorganisation'], ENT_QUOTES, "UTF-8") . "</a>";
        } else {
            $output = htmlentities($GLOBALS['arlo_event_list_item']['e_providerorganisation'], ENT_QUOTES, "UTF-8");
        }   

        return $output;        
    }

    private static function shortcode_event_session_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_arlo_id'])) return '';
        global $post, $wpdb;
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        $output = $where = '';
        
        extract(shortcode_atts(array(
            'label' => __('Session information', 'arlo-for-wordpress'),
            'header' => __('Sessions', 'arlo-for-wordpress'),
        ), $atts, $shortcode_name, $import_id));
        
        if (!empty($arlo_region)) {
            $where = ' AND e_region = "' . esc_sql($arlo_region) . '"';
        }       
        
        $sql = "
            SELECT 
                e_name, 
                e_locationname,
                e_locationvisible,
                e_startdatetime,
                e_finishdatetime,
                e_datetimeoffset,
                e_isonline,
                e_timezone_id,
                0 AS v_id
            FROM
                {$wpdb->prefix}arlo_events
            WHERE 
                e_parent_arlo_id = {$GLOBALS['arlo_event_list_item']['e_arlo_id']}
            AND
                import_id = " . $import_id . "
                {$where}
            ORDER BY 
                e_startdatetime";
                        
        $items = $wpdb->get_results($sql, ARRAY_A);
        if (is_array($items) && count($items)) {
            $output .= '<div data-tooltip="#' . ARLO_PLUGIN_PREFIX . '_session_tooltip_' . $GLOBALS['arlo_event_list_item']['e_arlo_id'] . '" class="' . ARLO_PLUGIN_PREFIX . '-tooltip-button">' . htmlentities($label, ENT_QUOTES, "UTF-8") . '</div>
            <div class="' . ARLO_PLUGIN_PREFIX . '-tooltip-html" id="' . ARLO_PLUGIN_PREFIX . '_session_tooltip_' . $GLOBALS['arlo_event_list_item']['e_arlo_id'] . '"><h5>' . htmlentities($header, ENT_QUOTES, "UTF-8") . '</h5>';
            
            foreach($items as $key => $item) {
        
                $GLOBALS['arlo_event_session_list_item'] = $item;
                
                $output .= do_shortcode($content);
                
                unset($GLOBALS['arlo_event_session_list_item']);
            }
            
            $output .= '</div>';    
        }
        
        return $output;        
    }

    private static function shortcode_event_duration($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['et_arlo_id'])) return;

        return self::get_event_duration($import_id);
    }

    private static function get_event_duration($import_id) {
    
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();

        $conditions = array(
            'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id'],
            'parent_id' => 0
        );

        if (!empty($arlo_region)) {
            $conditions['region'] = $arlo_region; 
        }

        if (!empty($GLOBALS['arlo_event_list_item']['e_startdatetime']) && !empty($GLOBALS['arlo_event_list_item']['e_finishdatetime'])) {
            $start = $GLOBALS['arlo_event_list_item']['e_startdatetime'];
            $end = $GLOBALS['arlo_event_list_item']['e_finishdatetime'];
        } else {
            $events = \Arlo\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), 1, $import_id);
            
            if(empty($events)) return;
            
            $start = $events->e_startdatetime;
            $end = $events->e_finishdatetime;
        }

        $difference = strtotime($end)-strtotime($start);// seconds

        $hours = floor($difference/60/60);
            
        // if we're the same day, display hours
        if(date('d-m', strtotime($start)) == date('d-m', strtotime($end)) || $hours <= 6) {
            
                    
            if ($hours > 6) {
                return __('1 day', 'arlo-for-wordpress');
            }

            $minutes = ceil(($difference % 3600)/60);

            $duration = '';
            
            if($hours > 0) {
                $duration .= sprintf(_n('%d hour', '%d hours', $hours, 'arlo-for-wordpress'), $hours);
            }

            if($hours > 0 && $minutes > 0) {
                $duration .= ', ';
            }

            if($minutes > 0) {
                $duration .= sprintf(_n('%d minute', '%d minutes', $minutes, 'arlo-for-wordpress'), $minutes);
            }
            
            return $duration;
        }
        
        // if not the same day, and less than 7 days, then show number of days
        if(ceil($difference/60/60/24) <= 7) {
            $days = ceil($difference/60/60/24);
            
            return sprintf(_n('%d day','%d days', $days, 'arlo-for-wordpress'), $days);
        }
        
        // if not the same day, and more than 7 days, then show number of weeks
        if(ceil($difference/60/60/24) > 7) {
            $weeks = ceil($difference/60/60/24/7);
            
            return sprintf(_n('%d week','%d weeks', $weeks, 'arlo-for-wordpress'), $weeks);     
        }
        
        return;        
    }
    
    private static function shortcode_event_duration_description($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item'])) return;

        if(empty($GLOBALS['arlo_event_list_item']['e_sessiondescription'])) {
            // basic event
            if(empty($GLOBALS['arlo_event_list_item']['et_arlo_id'])) return;
            if(empty($GLOBALS['arlo_event_list_item']['e_startdatetime'])) return;

            $duration = self::get_event_duration($import_id);

            $event =  $GLOBALS['arlo_event_list_item'];
            $start = self::event_date_formatter($atts, $event['e_startdatetime'], $event['e_datetimeoffset'], $event['e_isonline'], $event['e_timezone_id']);
            $end = self::event_date_formatter($atts, $event['e_finishdatetime'], $event['e_datetimeoffset'], $event['e_isonline'], $event['e_timezone_id']);

            return esc_html($duration . ', ' . $start . ' - ' . $end);
        } else {
            // multi-session event
            return esc_html($GLOBALS['arlo_event_list_item']['e_sessiondescription']);
        }
    }

    private static function shortcode_event_price($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['et_arlo_id'])) return;
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'showfrom' => 'true',
        ), $atts, $shortcode_name, $import_id));
        

        $settings = get_option('arlo_settings');  
        $price_setting = (isset($settings['price_setting'])) ? $settings['price_setting'] : ARLO_PLUGIN_PREFIX . '-exclgst';
        $price_field = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? 'o_offeramounttaxexclusive' : 'o_offeramounttaxinclusive';
        $price_field_show = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? 'o_formattedamounttaxexclusive' : 'o_formattedamounttaxinclusive';
        $free_text = (isset($settings['free_text'])) ? $settings['free_text'] : __('Free', 'arlo-for-wordpress');
        
        $offer;
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();

        // attempt to find event template offer
        $conditions = array(
            'event_template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id'],
            'parent_id' => 0
        );
        
        if (!empty($arlo_region)) {
            $conditions['region'] = $arlo_region; 
        }

        $offer = \Arlo\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);

        // if none, try the associated events
        if(!$offer) {
            $conditions = array(
                'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id']
            );

            if (!empty($arlo_region)) {
                $conditions['region'] = $arlo_region; 
            }               

            $event = \Arlo\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), 1, $import_id);

            if(empty($event)) return;
            
            $conditions = array(
                'event_id' => $event->e_id,
                'discounts' => false
            );
            
            if (!empty($arlo_region)) {
                $conditions['region'] = $arlo_region; 
            }       
            
            $offer = \Arlo\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
        }

       
        // if none, try the associated online activity
        if(!$offer) {
            $conditions = array(
                'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id']
            );
            
            $oa = \Arlo\Entities\OnlineActivities::get($conditions, null, 1, $import_id);
            
            if(empty($oa)) return;
            
            $conditions = array(
                'oa_id' => $oa->oa_id,
                'discounts' => false
            );
            
            if (!empty($arlo_region)) {
                $conditions['region'] = $arlo_region; 
            }       
            
            $offer = \Arlo\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
        }   

        if(empty($offer)) return;
        
        // if $0.00, return "Free"
        if((float)$offer->$price_field == 0) {
            return htmlentities($free_text, ENT_QUOTES, "UTF-8");
        }
        
        $fromtext = '';
        if (strtolower($showfrom) === "true") {
            $fromtext = '<span class="arlo-from-text">' . __('From', 'arlo-for-wordpress') . '</span> ';
        }
        
        return $fromtext . $offer->$price_field_show;        
    }

    private static function shortcode_event_rich_snippet($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $event_snippet = self::get_rich_snippet_data($content, $atts, $shortcode_name, $import_id);
        $event_snippet = Shortcodes::create_rich_snippet( json_encode($event_snippet) );

        $course_snippet = array();
        $course_snippet['@context'] = 'http://schema.org';
        $course_snippet['@type'] = 'Course';
        $course_snippet['name'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'e_name');

        $course_snippet['description'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'et_descriptionsummary');

        $et_link = \Arlo\Utilities::get_absolute_url( self::get_et_link($GLOBALS['arlo_eventtemplate'],$link) );

        $course_snippet['url'] = $et_link;
        $course_snippet = Shortcodes::create_rich_snippet( json_encode($course_snippet) );

        return $event_snippet . $course_snippet;
    }

    private static function get_et_link($event_template,$link) {
        $et_link = '';
        switch ($link) {
            case 'viewuri': 
                $et_link = Shortcodes::get_rich_snippet_field($event_template,'et_viewuri');
            break;  
            default: 
                $et_link = Shortcodes::get_template_permalink($event_template['et_post_name'], $event_template['et_region']);
            break;
        }
        return $et_link;
    }

    public static function get_rich_snippet_data($content, $atts, $shortcode_name, $import_id) {
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $settings = get_option('arlo_settings');  

        $event_snippet = array();
        
        // Basic
        $event_snippet['@context'] = 'http://schema.org';
        $event_snippet['@type'] = 'Event';
        $event_snippet['name'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'e_name');

        $event_snippet['startDate'] = !empty($GLOBALS['arlo_event_list_item']['e_startdatetime']) ? date(DATE_ISO8601, strtotime($GLOBALS['arlo_event_list_item']['e_startdatetime'])) : '';
        $event_snippet['endDate'] = !empty($GLOBALS['arlo_event_list_item']['e_startdatetime']) ? date(DATE_ISO8601, strtotime($GLOBALS['arlo_event_list_item']['e_finishdatetime'])) : '';

        $et_link = \Arlo\Utilities::get_absolute_url( self::get_et_link($GLOBALS['arlo_eventtemplate'],$link) );

        if (!empty($GLOBALS['arlo_event_list_item']['et_descriptionsummary'])) {
            $event_snippet['description'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'et_descriptionsummary');
        }

        $event_snippet['url'] = $et_link;


        // Venue
        $event_snippet["location"] = array();
        $event_snippet["location"]["@type"] = "Place";


        $v_name = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_name');
        if (!empty($v_name)) {
            $event_snippet["location"]["name"] = $v_name;
        } else if ($v_name = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'e_locationname')) {
            $event_snippet["location"]["name"] = $v_name;
        }

        $v_is_hidden = false;
        if ( array_key_exists('e_locationvisible',$GLOBALS['arlo_event_list_item']) ) {
            $v_is_hidden = $GLOBALS['arlo_event_list_item']['e_locationvisible'] === "0" ? true : false;
        }

        if ( Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'e_locationname') == "Online" ) {
            $event_snippet["location"]["name"] = "Online";

            $event_snippet["location"]["address"] = array(
                "@type" => "PostalAddress",
                "streetAddress" => "",
                "addressLocality" => "Online"
            );
        } else if ( $v_is_hidden && !empty( Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'e_locationname') ) ) {
            $event_snippet["location"]["name"] = $GLOBALS['arlo_event_list_item']['e_locationname'];

            $event_snippet["location"]["address"] = array(
                "@type" => "PostalAddress",
                "streetAddress" => "",
                "addressLocality" => $GLOBALS['arlo_event_list_item']['e_locationname']
            );
        } else {
            $city = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdresscity');
            $state = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdressstate');
            $post_code = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdresspostcode');
            $country = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdresscountry');

            $street_address = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdressline1') . " "
                            . Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdressline2') . " " 
                            . Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdressline3') . " " 
                            . Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdressline4') . " " 
                            . Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_physicaladdresssuburb');
            
            if ( ( !empty($street_address) && !ctype_space($street_address) ) || 
                ( !empty($city) && !ctype_space($city) ) || 
                ( !empty($state) && !ctype_space($state) ) || 
                ( !empty($post_code) && !ctype_space($post_code) ) || 
                ( !empty($country) && !ctype_space($country) ) ) {
                $event_snippet["location"]["address"] = array();
                $event_snippet["location"]["address"]["@type"] = "PostalAddress";
            }

            if (!empty($street_address) && !ctype_space($street_address)) {
                $event_snippet["location"]["address"]["streetAddress"] = trim($street_address);
            }

            if (!empty($city) && !ctype_space($city)) {
                $event_snippet["location"]["address"]["addressLocality"] = $city;
            }

            if (!empty($post_code) && !ctype_space($post_code)) {
                $event_snippet["location"]["address"]["postalCode"] = $post_code;
            }

            if (!empty($state) && !ctype_space($state)) {
                $event_snippet["location"]["address"]["addressRegion"] = $state;
            }

            // Geo coordinates
            $geolatitude = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_geodatapointlatitude');
            $geolongitude = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_geodatapointlongitude');

            if ( !empty($geolatitude) || !empty($geolongitude) ) {
                $event_snippet["location"]["geo"] = array();
                $event_snippet["location"]["geo"]["@type"] = "GeoCoordinates";
                $event_snippet["location"]["geo"]["latitude"] = $geolatitude;
                $event_snippet["location"]["geo"]["longitude"] = $geolongitude;
            }
        }

        $v_link = get_permalink(arlo_get_post_by_name(Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'v_post_name'), 'arlo_venue'));
        
        $v_link = \Arlo\Utilities::get_absolute_url($v_link);

        if (!empty($v_link) && !$v_is_hidden && Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'e_locationname') !== "Online") {
            $event_snippet["location"]["url"] = $v_link;
        }

        // OFfers
        $price_setting = (isset($settings['price_setting'])) ? $settings['price_setting'] : ARLO_PLUGIN_PREFIX . '-exclgst';
        $price_field = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? 'o_offeramounttaxexclusive' : 'o_offeramounttaxinclusive';

        $offers = Shortcodes::get_offers_snippet_data( $GLOBALS['arlo_event_list_item']['e_id'], 'e_id', $import_id, $price_field);

        if (!empty($offers)) {
            $event_snippet["offers"] = array();
            $event_snippet["offers"]["@type"] = "AggregateOffer";

            $event_snippet["offers"]["highPrice"] = $offers['high_price'];
            $event_snippet["offers"]["lowPrice"] = $offers['low_price'];
            
            $event_snippet["offers"]["price"] = $offers['low_price'];

            $event_snippet["offers"]["priceCurrency"] = $offers['currency'];

            $event_snippet["offers"]['url'] = $et_link;

            if ($GLOBALS['arlo_event_list_item']["e_isfull"] == "0") {
                $event_snippet["offers"]['availability'] = "http://schema.org/InStock";
            } else {
                $event_snippet["offers"]['availability'] = "http://schema.org/SoldOut";
            }

        }


        // Presenters
        $performers = array();
        foreach (self::get_event_presenters($import_id) as $i => $presenter) {
            array_push($performers,Shortcodes::get_performer($presenter,$link));
        }

        $event_snippet["performer"] = $performers;

        return $event_snippet;
    }

    private static function shortcode_no_event_text($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (!empty($GLOBALS['no_event_text'])) {
            return '<span class="arlo-no-results">' . $GLOBALS['no_event_text'] . '</span>';
        }        
    }

    private static function shortcode_event_next_running($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']) || empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return;
        $return = "";

        $arlo_location = \Arlo\Utilities::clean_string_url_parameter('arlo-location');
        $arlo_delivery = \Arlo\Utilities::clean_int_url_parameter('arlo-delivery');
        $arlo_state = \Arlo\Utilities::clean_string_url_parameter('arlo-state');

        if (!empty($GLOBALS['arlo_eventtemplate']['et_region'])) {
            $arlo_region = $GLOBALS['arlo_eventtemplate']['et_region'];
        } else {
            $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        }

        // merge and extract attributes
        extract(shortcode_atts(array(
            'buttonclass' => '',
            'dateclass' => '',
            'format' => 'd M y',
            'layout' => '',
            'limit' => 1,
            'removeyear' => "true",
            'text' => '{%date%}',
            'template_link' => 'registerlink'
        ), $atts, $shortcode_name, $import_id));
        
        if (strpos($format, '%') === false) {
            $format = DateFormatter::date_format_to_strftime_format($format);
        }

        $removeyear = ($removeyear == "false" || $removeyear == "0" ? false : true);
        
        $conditions = array(
            'template_id' => $GLOBALS['arlo_eventtemplate']['et_arlo_id'],
            'e.e_startdatetime > NOW()' => null,
            'parent_id' => 0
        );
        
        $oaconditions = array(
            'template_id' => $GLOBALS['arlo_eventtemplate']['et_arlo_id'],
        );  
        
        if (!empty($arlo_region)) {
            $conditions['e.e_region = %s'] = $arlo_region;
            $oaconditions['oa.oa_region = %s'] = $arlo_region;
        }

        if (!empty($arlo_location)) {
            $conditions['e.e_locationname = %s'] = $arlo_location;
        }

        if(isset($arlo_delivery) && is_numeric($arlo_delivery) && $arlo_delivery <= 1) {
            $conditions['e.e_isonline = %d'] = $arlo_delivery;
        }

        if (isset($arlo_state) && isset($GLOBALS['state_filter_venues'])) {
            $conditions['state'] = $GLOBALS['state_filter_venues'];
        }
        
        $events = \Arlo\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), $limit, $import_id);
        $oa = \Arlo\Entities\OnlineActivities::get($oaconditions, null, 1, $import_id);
        
        if ($layout == "list") {
            $return = '<ul class="arlo-event-next-running">';
        }
        
        if(count($events) == 0 && count($oa) == 0 && !empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi'])) {
            $return = '<a href="' . esc_url($GLOBALS['arlo_eventtemplate']['et_registerinteresturi']) . '" title="' . __('Register interest', 'arlo-for-wordpress') . '" class="' . esc_attr($buttonclass) . '">' . __('Register interest', 'arlo-for-wordpress') . '</a>';
        } else {

            if (count($events)) {
                $return_links = [];
                
                if (!is_array($events)) {
                    $events = array($events);
                }       
    
                foreach ($events as $event) {
                    if (!empty($event->e_startdatetime)) {
                        if(date('y', strtotime($event->e_startdatetime)) == date('y') && $removeyear) {
                            $format = trim(preg_replace('/\s+/', ' ', str_replace(["%Y", "%y", "Y", "y", "%g", "%G"], "", $format)));
                        }
                        
                        $location = $event->e_locationname;
                       
                        $date = self::event_date_formatter(['format' => $format], $event->e_startdatetime, $event->e_datetimeoffset, $event->e_isonline, $event->e_timezone_id);
    
                        $display_text = str_replace(['{%date%}', '{%location%}'], [esc_html($date), esc_html($location)], $text);
    
                        $link = ($layout == 'list' ? "<li>" : "");
    
                        $buttonclass = $event->e_isfull ? $buttonclass . ' arlo-event-full' : $buttonclass . ' arlo-register' ;
    
                        switch ($template_link) {
                            case "permalink":
                                $url = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);
    
                                $link .= self::get_event_date_link($url, $buttonclass, $display_text);
                                break;
                            case "none":
                                $link .= '<span class="' . esc_attr($dateclass) . '">' . $display_text . '</span>';
                                break;
                            case "viewuri":
                                $url = $GLOBALS['arlo_eventtemplate']['et_viewuri'];
                                $link .= self::get_event_date_link($url, $buttonclass, $display_text);
                                break;
                            case "registerlink":
                                if ($event->e_registeruri && !$event->e_isfull) {
                                    $url = $event->e_registeruri;
                                } else {
                                    $url = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);
                                }
                                $link .= self::get_event_date_link($url, $buttonclass, $display_text);
                                break;
                        }
    
                        $link .= ($layout == 'list' ? "</li>" : "");
    
                        $return_links[] = $link;
                    }   
                }   
                
                $return .= implode(($layout == 'list' ? "" : ", "), $return_links);
            } 
            
            //show only, if there is no events or delivery filter set to "OA"
            if ((count($events) == 0 || (isset($arlo_delivery) && is_numeric($arlo_delivery) && $arlo_delivery > 1)) && count($oa)) {
                $reference_terms = json_decode($oa->oa_reference_terms, true);
                $buttonclass = 'arlo-register';
    
                if (is_array($reference_terms) && isset($reference_terms['Plural'])) {
                    $tag = 'a';
                    $class = esc_attr($buttonclass);
                    $href = '';
                    switch ($template_link) {
                        case "permalink":
                            $url = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);
                            $href = 'href="' . esc_url($url) . '"';
                            break;
                        case "none":
                            $tag = 'span';
                            $class = esc_attr($dateclass);        
                            break;
                        case "viewuri":
                            $url = $GLOBALS['arlo_eventtemplate']['et_viewuri'];
                            $href = 'href="' . esc_url($url) . '"';
                            break;
                        case "registerlink":
                            $url = $oa->oa_registeruri;
                            $href = 'href="' . esc_url($url) . '"';
                            break;
                    }
    
                    $return .= sprintf('<%s %s class="%s">%s</%s>', $tag, $href, $class, $reference_terms['Plural'], $tag);
                }
            }
        }
        
        if ($layout == "list") {
            $return .= '</ul>';
        }
            
        return $return;
    }


    private static function get_event_date_link($url, $buttonclass, $display_text) {
        return sprintf('<a href="%s" class="%s">%s</a>', esc_attr($url), esc_attr($buttonclass), $display_text);
    }
    

    public static function event_date_formatter($atts, $date, $offset, $is_online = false, $timezoneid = null) {
        global $arlo_plugin;
        $timezone = $wp_timezone = $selected_timezone = null;
        $original_timezone = date_default_timezone_get();
        
        $timewithtz = str_replace(' ', 'T', $date) . $offset;

        $date = new \DateTime($timewithtz);

        $utc_timezone_name = "UTC";

        $timezone_array = $arlo_plugin->get_timezone_manager()->get_indexed_timezones($timezoneid);
        if (!is_null($timezone_array) && !empty($timezone_array['windows_tz_id']) && !empty(\Arlo\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_array['windows_tz_id']])) {
            $timezone = new \DateTimeZone(\Arlo\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_array['windows_tz_id']]);
        } else {
            try {
                $timezone = new \DateTimeZone(get_option('timezone_string'));
            } catch(\Exception $e) {
                $timezone = new \DateTimeZone($utc_timezone_name);
            }
        }

        if ($timezone != null) {
            $date->setTimezone($timezone);
            date_default_timezone_set($timezone->getName());
        }

        $selected_timezone = null;

        if (!empty($GLOBALS['selected_timezone_names']))
            $selected_timezone = new \DateTimeZone($GLOBALS['selected_timezone_names']);
      
        if($is_online) {
            if (!empty($selected_timezone)) {
                try {
                    $timezone = $selected_timezone;
                } catch (Exception $e) {}
                
                if (!is_null($timezone)) {
                    $date->setTimezone($timezone);
                    date_default_timezone_set($timezone->getName());
                }   
            }
        }

        $format = 'D g:i A';

        if(isset($atts['format'])) $format = $atts['format'];
                    
        if (strpos($format, '%') === false) {
            $format = DateFormatter::date_format_to_strftime_format($format);
        }

        $wp_timezone = null;

        try {
            $wp_timezone = new \DateTimeZone(get_option('timezone_string'));
        } catch (\Exception $e) {}

        if (!is_null($timezone) && ($timezone->getName() == $utc_timezone_name || (!is_null($wp_timezone) && $wp_timezone->getOffset($date) != $timezone->getOffset($date)) || !is_null($selected_timezone) || $is_online) && preg_match('[I|M]', $format) === 1 && preg_match('[Z|z]', $format) === 0) {
            $format .= " %Z";
        }        

        if (strpos($format, '%Z')) {
            $format = str_replace('%Z', '{TZ_ABBREV}', $format); //T
        }

        if (strpos($format, '%z')) {
            $format = str_replace('%z', '{TZ_OFFSET}', $format); //P
        }        

        $date = str_replace(['{TZ_ABBREV}', '{TZ_OFFSET}'], [$date->format('T'), $date->format('P')], strftime($format, $date->getTimestamp()));

        //if we haven't got timezone, we need to append the timezone abbrev
        if ($is_online && is_null($timezone) && (preg_match('[I|M]', $format) === 1) && !empty($offset)) {
            $date .=  " (" . $offset . ")";
        }

        date_default_timezone_set($original_timezone);

        return $date;
    }  
}