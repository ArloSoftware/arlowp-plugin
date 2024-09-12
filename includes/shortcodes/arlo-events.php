<?php
namespace Arlo\Shortcodes;

use Arlo_For_Wordpress;
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
                if (!is_array($atts) && empty($atts)) { $atts = []; }
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
        
        foreach($filters_array as $filter_key):

            if (!array_key_exists($filter_key, \Arlo_For_Wordpress::$available_filters['event']['filters']))
                continue;

            $items = \Arlo\Shortcodes\Filters::get_filter_options($filter_key, $import_id, $post->ID);

            $filter_html .= Shortcodes::create_filter($filter_key, $items, __(\Arlo_For_Wordpress::$filter_labels[$filter_key], 'arlo-for-wordpress'), 'generic', null, 'event');

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
        if(!isset($GLOBALS['arlo_event_list_item']['e_id']) && !isset($GLOBALS['arlo_event_session_list_item']['e_id'])) return '';

        $id = isset($GLOBALS['arlo_event_session_list_item']['e_id']) ? $GLOBALS['arlo_event_session_list_item']['e_id'] : $GLOBALS['arlo_event_list_item']['e_id'];
         
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
                et.e_id = {$id}
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

        extract(shortcode_atts(array(
            'show' => '',
            'within_ul' => 'true'
        ), $atts, $shortcode_name, $import_id));
        $within_ul = filter_var($within_ul, FILTER_VALIDATE_BOOLEAN);
        
        $output = '';
        
        if (is_array($items) && count($items)) {
            unset($GLOBALS['no_event']);
            foreach($items as $key => $item) {
        
                $GLOBALS['arlo_event_list_item'] = $item;

                if (strpos($content, '[arlo_venue_') !== false) {
                    $conditions = array(
                        'id' => $item['v_id']
                    );
    
                    $GLOBALS['arlo_venue_list_item'] = \Arlo\Entities\Venues::get($conditions, null, null, $import_id);    
                }

                if (!empty($show) && $key == $show) {
                    if ($within_ul){ $output .= "</ul>"; }
                    $output .= '<ul class="arlo-list arlo-show-more-hidden events">';
                    if (!$within_ul){ $output .= "</ul>"; }
                }
        
                $output .= do_shortcode($content);

                unset($GLOBALS['arlo_venue_list_item']);
            }
            if ($within_ul){ $output .= "</ul>"; }


            $arlo_event_id = \Arlo\Utilities::clean_string_url_parameter('arlo-event-id');
            if (!empty($arlo_event_id)){
                $url = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);

                $output .= "<div class='arlo-single-show-wrapper'>
                            <a href='" . esc_url($url) . "' class='arlo-show-more arlo-button button'>
                                " . __("Show More", "arlo-for-wordpress") . "
                            </a>
                    </div>";
            }
        } 
        
        return $output;        
    }

    private static function generate_events_list_sql($atts, $import_id) {
        global $post, $wpdb;
        $settings = get_option('arlo_settings');

        $where = '';
        $join = [];
        $parameters = [];
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        $arlo_location = \Arlo\Utilities::clean_string_url_parameter('arlo-location');
        $arlo_state = \Arlo\Utilities::clean_string_url_parameter('arlo-state');
        $arlo_event_id = \Arlo\Utilities::clean_string_url_parameter('arlo-event-id');
        
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

        if (!empty($arlo_event_id)){
            $where .= ' AND ' . $t2 . '.e_arlo_id = %d';
            $parameters[] = $arlo_event_id;
        }

        if (!empty($arlo_state)) {
            $venues = \Arlo\Entities\Venues::get(['state' => $arlo_state], null, null, $import_id);

            if (count($venues)) {
                $join['ce'] = " LEFT JOIN $t2 AS ce ON $t2.e_arlo_id = ce.e_parent_arlo_id AND $t2.import_id = ce.import_id ";

                $venues = array_map(function ($venue) {
                    return $venue['v_arlo_id'];
                }, $venues);
                
                $where .= " AND (ce.v_id IN (" . implode(',', array_map(function() {return "%d";}, $venues)) . ") OR $t2.v_id IN (" . implode(',', array_map(function() {return "%d";}, $venues)) . "))";
                $parameters = array_merge($parameters, $venues);
                $parameters = array_merge($parameters, $venues);
            }
        };        

        $sql = 
            "SELECT 
                $t2.*, 
                $t1.et_descriptionsummary
            FROM 
                $t2
            LEFT JOIN 
                $t1
            ON 
                $t2.et_arlo_id = $t1.et_arlo_id
            AND
                $t1.import_id = $t2.import_id
            " . implode("\n", $join) ."
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

        $conditions = array(
            'id' => $event['v_id']
        );

        switch ($link) {
            case 'permalink': 
                if(!($event['e_isonline'] || $event['v_id'] == 0 || $event['e_locationvisible'] == 0)) {
                    $venue = \Arlo\Entities\Venues::get($conditions, null, null, $import_id);
                    $permalink = get_permalink(arlo_get_post_by_name($venue['v_post_name'], 'arlo_venue'));
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

        $format = (!empty($atts['format']) ? $atts['format'] : '');
        return esc_html(self::event_date_formatter($format, $event['e_startdatetime'], $event['e_startdatetimeoffset'], $event['e_starttimezoneabbr'], $event['e_timezone_id'], $event['e_isonline']));
    }

    private static function shortcode_event_end_date($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_finishdatetime']) && !isset($GLOBALS['arlo_event_session_list_item']['e_finishdatetime'])) return '';
        
        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        $format = (!empty($atts['format']) ? $atts['format'] : '');
        return esc_html(self::event_date_formatter($format, $event['e_finishdatetime'], $event['e_finishdatetimeoffset'], $event['e_finishtimezoneabbr'], $event['e_timezone_id'], $event['e_isonline']));
    }

    private static function shortcode_event_dates($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_finishdatetime']) && !isset($GLOBALS['arlo_event_session_list_item']['e_finishdatetime'])) return '';

        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        $args = func_get_args();

        // merge and extract attributes
        extract(shortcode_atts(array(
            'startdateformat' => '%e %b',
            'enddateformat' => '%e %b',
        ), $atts, $shortcode_name, $import_id));

        $start_date = new \DateTime($event['e_startdatetime']);
        $end_date = new \DateTime($event['e_finishdatetime']);

        $args[1]['format'] = $startdateformat;
        $formatted_start_date = '<span class="arlo-start-date">' .  call_user_func_array('self::shortcode_event_start_date', $args) . '</span>';

        $formatted_end_date = '';
        if ($start_date->format('Y-m-d') !== $end_date ->format('Y-m-d')) {
            $args[1]['format'] = $enddateformat;
            $formatted_end_date = ' - <span class="arlo-end-date">' . call_user_func_array('self::shortcode_event_end_date', $args) . '</span>';
        }

        return $formatted_start_date . $formatted_end_date;
    }

    private static function shortcode_event_session_description($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_sessiondescription'])) return '';

        return esc_html($GLOBALS['arlo_event_list_item']['e_sessiondescription']);
    }

    private static function shortcode_event_summary($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(empty($GLOBALS['arlo_event_list_item']['e_summary']) && empty($GLOBALS['arlo_event_session_list_item']['e_summary'])) return '';

        $event = (!empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item']);

        return esc_html($event['e_summary']);
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
        $id = isset($GLOBALS['arlo_event_session_list_item']['e_id']) ? $GLOBALS['arlo_event_session_list_item']['e_id'] : $GLOBALS['arlo_event_list_item']['e_id'];
        return Shortcodes::advertised_offers($id, 'e_id', $import_id, $GLOBALS['arlo_event_list_item']['e_is_taxexempt']);
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

        $e_id = $id = isset($GLOBALS['arlo_event_session_list_item']['e_id']) ? $GLOBALS['arlo_event_session_list_item']['e_id'] : $GLOBALS['arlo_event_list_item']['e_id'];
        $items = \Arlo\Entities\Presenters::get(['e_id' => $e_id], null, null, $import_id);

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
            'layout' => 'tooltip'
        ), $atts, $shortcode_name, $import_id));
        
        if (!empty($arlo_region)) {
            $where = ' AND e_region = "' . esc_sql($arlo_region) . '"';
        }
        
        $sql = "
            SELECT 
                e_name, 
                e_id,
                e_arlo_id,
                e_locationname,
                e_locationvisible,
                e_startdatetime,
                e_finishdatetime,
                e_startdatetimeoffset,
                e_finishdatetimeoffset,
                e_starttimezoneabbr,
                e_finishtimezoneabbr,
                e_isonline,
                e_timezone_id,
                e_sessiondescription,
                e_summary,
                e_isfull,
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
            $open = '';
            $close = '';
            $item_tag = '%s';

            switch($layout) {
                case 'popup':
                    $modal_id = ARLO_PLUGIN_PREFIX . '_session_modal_' . $GLOBALS['arlo_event_list_item']['e_arlo_id'];

                    $open = '
                        <a href="" class="arlo-sessions-popup-trigger" data-target="#' . $modal_id . '">
                          ' .  htmlentities($label, ENT_QUOTES, "UTF-8") . '
                        </a>

                        <div class="arlo-sessions-popup-content" id="' . $modal_id . '">
                            <div class="arlo-sessions-popup-header"><h2>' . htmlentities($header, ENT_QUOTES, "UTF-8") . '</h2></div>
                            <div class="arlo-sessions-popup-inner">
                            ';

                    $close = '</div></div>';
                    break;

                case 'none':
                    $open = '<ul class="arlo-sessions">';
                    $close = '</ul>';
                    $item_tag = '<li class="arlo-session">%s</li>';
                    break;

                default:
                    $open = '<div data-tooltip="#' . ARLO_PLUGIN_PREFIX . '_session_tooltip_' . $GLOBALS['arlo_event_list_item']['e_arlo_id'] . '" class="' . ARLO_PLUGIN_PREFIX . '-tooltip-button">' . htmlentities($label, ENT_QUOTES, "UTF-8") . '</div>
                <div class="' . ARLO_PLUGIN_PREFIX . '-tooltip-html" id="' . ARLO_PLUGIN_PREFIX . '_session_tooltip_' . $GLOBALS['arlo_event_list_item']['e_arlo_id'] . '"><h5>' . htmlentities($header, ENT_QUOTES, "UTF-8") . '</h5>';

                    $close = '</div>';
                    break;
            }

            $output .= $open;
            
            foreach($items as $key => $item) {
        
                $GLOBALS['arlo_event_session_list_item'] = $item;
                
                $output .= sprintf($item_tag, do_shortcode($content));
                
                unset($GLOBALS['arlo_event_session_list_item']);
            }
            
            $output .= $close;    
        }
        
        return $output;        
    }

    private static function shortcode_event_duration($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(empty($GLOBALS['arlo_event_list_item']['et_arlo_id']) && empty($GLOBALS['arlo_event_list_item']['e_startdatetime']) && empty($GLOBALS['arlo_event_session_list_item']['e_startdatetime'])) return;

        $event = (!empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item']);

        if (!empty($event['e_startdatetime']) && !empty($event['e_finishdatetime'])) {
            $start = $event['e_startdatetime'];
            $end = $event['e_finishdatetime'];
        } else {
            $conditions = array(
                'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id'],
                'parent_id' => 0
            );

            $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
            if (!empty($arlo_region)) {
                $conditions['region'] = $arlo_region; 
            }
    
            $events = \Arlo\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), 1, $import_id);
            
            if(empty($events)) return;
            
            $start = $events->e_startdatetime;
            $end = $events->e_finishdatetime;
        }

        $difference = strtotime($end)-strtotime($start);// seconds

        $hours = floor($difference/60/60);
            
        // if we're the same day, display hours
        if(date('d-m', strtotime($start)) == date('d-m', strtotime($end)) || $hours <= 6) {

            if ($hours >= 6) {
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
            $duration = self::shortcode_event_duration('', [], '', $import_id);
            $start = self::shortcode_event_start_date('', $atts);
            $end = self::shortcode_event_end_date('', $atts);

            return $duration . esc_html(', ') . $start . esc_html(' - ') . $end;
        } else {
            // multi-session event
            return self::shortcode_event_session_description();
        }
    }

    private static function shortcode_event_price($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['et_arlo_id'])) return;
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'showfrom' => 'true',
            'order' => 'session,template,firstevent,onlineactivity',
        ), $atts, $shortcode_name, $import_id));
        

        $settings = get_option('arlo_settings');  
        $price_setting = (isset($settings['price_setting'])) ? $settings['price_setting'] : ARLO_PLUGIN_PREFIX . '-exclgst';
        $price_field = ($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' || $GLOBALS['arlo_event_list_item']['e_is_taxexempt'] == '1' ? 'o_offeramounttaxexclusive' : 'o_offeramounttaxinclusive');
        $price_field_show = ($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' || $GLOBALS['arlo_event_list_item']['e_is_taxexempt'] == '1' ? 'o_formattedamounttaxexclusive' : 'o_formattedamounttaxinclusive');
        $free_text = (isset($settings['free_text'])) ? $settings['free_text'] : __('Free', 'arlo-for-wordpress');
        
        $offer = '';
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();

        $order_array = explode(',', $order);
        foreach($order_array as $order_item) {
            $order_item = trim($order_item);

            switch ($order_item) {

                case 'session':
                    // attempt to find session offer
                    if (isset($GLOBALS['arlo_event_session_list_item']['e_id'])) {
                        $showfrom = false;
                        
                        $conditions = array(
                            'event_id' => $GLOBALS['arlo_event_session_list_item']['e_id'],
                            'discounts' => false
                        );
                        
                        if (!empty($arlo_region)) {
                            $conditions['region'] = $arlo_region; 
                        }

                        $offer = \Arlo\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
                    }
                    break;

                case 'template':
                    // if none, try the event template offer
                    if (isset($GLOBALS['arlo_event_list_item']['et_arlo_id'])) {
                        $conditions = array(
                            'event_template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id'],
                            'parent_id' => 0
                        );
                        
                        if (!empty($arlo_region)) {
                            $conditions['region'] = $arlo_region; 
                        }

                        $offer = \Arlo\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
                    }
                    break;

                case 'firstevent':
                    // if none, try the associated events
                    if (isset($GLOBALS['arlo_event_list_item']['et_arlo_id'])) {
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
                    break;

                case 'onlineactivity':
                    // if none, try the associated online activity
                    if (isset($GLOBALS['arlo_event_list_item']['et_arlo_id'])) {
                        $showfrom = false;

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
                    break;

                case 'event':
                    // this specific event only
                    if (isset($GLOBALS['arlo_event_list_item']['e_id'])) {
                        $showfrom = false;

                        $conditions = array(
                            'event_id' => $GLOBALS['arlo_event_list_item']['e_id'],
                            'discounts' => false
                        );
                        
                        if (!empty($arlo_region)) {
                            $conditions['region'] = $arlo_region; 
                        }       
                        
                        $offer = \Arlo\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
                    }
                    break;
            }

            if($offer) break; // exit foreach
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

        return $fromtext . esc_html($offer->$price_field_show);
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

        $event_snippet['startDate'] = Events::rich_snippet_time_format(
            $GLOBALS['arlo_event_list_item']['e_startdatetime'],
            $GLOBALS['arlo_event_list_item']['e_startdatetimeoffset'],
            $GLOBALS['arlo_event_list_item']['e_timezone_id']
        );
        $event_snippet['endDate'] = Events::rich_snippet_time_format(
            $GLOBALS['arlo_event_list_item']['e_finishdatetime'],
            $GLOBALS['arlo_event_list_item']['e_finishdatetimeoffset'],
            $GLOBALS['arlo_event_list_item']['e_timezone_id']
        );

        $et_link = \Arlo\Utilities::get_absolute_url( self::get_et_link($GLOBALS['arlo_eventtemplate'],$link) );

        if (!empty($GLOBALS['arlo_event_list_item']['et_descriptionsummary'])) {
            $event_snippet['description'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'et_descriptionsummary');
        }

        $event_snippet['url'] = $et_link;


        // Venue
        $event_snippet["location"] = array();
        $event_snippet["location"]["@type"] = "Place";

        $conditions = array(
            'id' => $GLOBALS['arlo_event_list_item']['v_id']
        );

        $venue = \Arlo\Entities\Venues::get($conditions, null, null, $import_id);

        $v_name = Shortcodes::get_rich_snippet_field($venue,'v_name');

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
            $city = Shortcodes::get_rich_snippet_field($venue,'v_physicaladdresscity');
            $state = Shortcodes::get_rich_snippet_field($venue,'v_physicaladdressstate');
            $post_code = Shortcodes::get_rich_snippet_field($venue,'v_physicaladdresspostcode');
            $country = Shortcodes::get_rich_snippet_field($venue,'v_physicaladdresscountry');

            $street_address = Shortcodes::get_rich_snippet_field($venue,'v_physicaladdressline1') . " "
                            . Shortcodes::get_rich_snippet_field($venue,'v_physicaladdressline2') . " " 
                            . Shortcodes::get_rich_snippet_field($venue,'v_physicaladdressline3') . " " 
                            . Shortcodes::get_rich_snippet_field($venue,'v_physicaladdressline4') . " " 
                            . Shortcodes::get_rich_snippet_field($venue,'v_physicaladdresssuburb');
            
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
            $geolatitude = Shortcodes::get_rich_snippet_field($venue,'v_geodatapointlatitude');
            $geolongitude = Shortcodes::get_rich_snippet_field($venue,'v_geodatapointlongitude');

            if ( !empty($geolatitude) || !empty($geolongitude) ) {
                $event_snippet["location"]["geo"] = array();
                $event_snippet["location"]["geo"]["@type"] = "GeoCoordinates";
                $event_snippet["location"]["geo"]["latitude"] = $geolatitude;
                $event_snippet["location"]["geo"]["longitude"] = $geolongitude;
            }
        }

        $v_link = get_permalink(arlo_get_post_by_name(Shortcodes::get_rich_snippet_field($venue,'v_post_name'), 'arlo_venue'));

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
        $e_id = $id = isset($GLOBALS['arlo_event_session_list_item']['e_id']) ? $GLOBALS['arlo_event_session_list_item']['e_id'] : $GLOBALS['arlo_event_list_item']['e_id'];
        $presenters = \Arlo\Entities\Presenters::get(['e_id' => $e_id], null, null, $import_id);
        foreach ($presenters as $i => $presenter) {
            array_push($performers,Shortcodes::get_performer($presenter,$link));
        }

        $event_snippet["performer"] = $performers;

        return $event_snippet;
    }

    /**
     * @see Arlo\Shortcodes\Events->event_date_formatter()
     * @param  string $datetime
     * @param  string $offset
     * @param  integer $timezoneid
     * @return string
     */
    private static function rich_snippet_time_format($datetime, $offset, $timezoneid){
        $timezone = null;
        $timezone_array = \Arlo_For_Wordpress::get_instance()->get_timezone_manager()->get_indexed_timezones($timezoneid);
        if (!is_null($timezone_array) && !empty($timezone_array['windows_tz_id']) && !empty(\Arlo\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_array['windows_tz_id']])) {
            $timezone = new \DateTimeZone(\Arlo\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_array['windows_tz_id']]);
        } else {
            try {
                $timezone = new \DateTimeZone(str_replace(':', '', $offset));
            } catch(\Exception $e) {
                $timezone = new \DateTimeZone('UTC');
            }
        }

        $time = new \DateTime($datetime, $timezone);
        return $time->format(DATE_ISO8601);
    }

    private static function shortcode_no_event_text($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (!empty($GLOBALS['no_event_text'])) {
            return '<span class="arlo-no-results">' . $GLOBALS['no_event_text'] . '</span>';
        }        
    }

    private static function shortcode_event_isfull($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_event_list_item']['e_isfull']) && empty($GLOBALS['arlo_event_session_list_item']['e_isfull'])) return;

        $event = (!empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item']);

        extract(shortcode_atts(array(
            'output' => 'Full'
        ), $atts, $shortcode_name, $import_id));

        if ($event["e_isfull"] == 1) {
            return $output;
        }
    }

    private static function shortcode_event_offers_hasdiscount($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['e_id'])) return;

        extract(shortcode_atts(array(
            'output' => 'Discount'
        ), $atts, $shortcode_name, $import_id));

        $offers = Shortcodes::get_advertised_offers($GLOBALS['arlo_event_list_item']['e_id'], 'e_id', $import_id, $GLOBALS['arlo_event_list_item']['e_is_taxexempt']);

        if (array_search('1', array_column($offers, 'o_isdiscountoffer')) !== false || array_search('1', array_column($offers, 'replacement_discount')) !== false) {
            return $output;
        }
    }

    private static function shortcode_event_haslimitedplaces($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_event_list_item']['e_placesremaining']) && empty($GLOBALS['arlo_event_session_list_item']['e_placesremaining'])) return;

        $event = (!empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item']);

        extract(shortcode_atts(array(
            'output' => 'Limited places'
        ), $atts, $shortcode_name, $import_id));

        if ($event["e_placesremaining"] > 0) {
            return $output;
        }
    }

    private static function shortcode_event_next_running($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']) || empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return;
        $return = "";

        $arlo_location = \Arlo\Utilities::get_filter_keys_string_array('location');
        $arlo_venue = \Arlo\Utilities::get_att_string('venue');
        $arlo_delivery = \Arlo\Utilities::get_filter_keys_int_array('delivery');
        $arlo_state = \Arlo\Utilities::clean_string_url_parameter('arlo-state');

        $arlo_locationhidden = \Arlo\Utilities::get_filter_keys_string_array('locationhidden');
        $arlo_deliveryhidden = \Arlo\Utilities::get_filter_keys_int_array('deliveryhidden');
        
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

        if (strpos($format, '%') === false && strcmp($format, 'period') != 0) {
            $format = DateFormatter::date_format_to_strftime_format($format);
        }

        $display_count = (strpos($text, '{%count%}') !== false);
        if ($display_count) {
            $limit = 100;
        }
        
        $removeyear = ($removeyear == "false" || $removeyear == "0" ? false : true);
        
        $conditions = array(
            'template_id' => $GLOBALS['arlo_eventtemplate']['et_arlo_id'],
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
            $conditions['e.e_locationname IN ( %s )'] = $arlo_location;
        }
        else if (!empty($arlo_locationhidden)) {
            $conditions['e.e_locationname NOT IN ( %s )'] = $arlo_locationhidden;
        }

        if (!empty($arlo_venue)) {
            $arlo_venue = \Arlo\Utilities::convert_string_to_int_array($arlo_venue);
            if (!empty($arlo_venue)) {
                if (!is_array($arlo_venue)) { $arlo_venue = [$arlo_venue]; }
                $conditions["e.v_id IN ( %s )"] = $arlo_venue;
            }
        }

        if(!empty($arlo_delivery)) {
            $conditions['e.e_isonline IN ( %d )'] = $arlo_delivery;
        }
        else if(!empty($arlo_deliveryhidden)) {
            $conditions['e.e_isonline NOT IN ( %d )'] = $arlo_deliveryhidden;
        }

        if (isset($arlo_state) && isset($GLOBALS['state_filter_venues'])) {
            $conditions['state'] = $GLOBALS['state_filter_venues'];
        }

        $events = [];
        if (empty($arlo_delivery) || !(in_array(0, $arlo_delivery) && in_array(1, $arlo_delivery))) {
            $events = \Arlo\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), $limit, $import_id);
        }
        $oa = \Arlo\Entities\OnlineActivities::get($oaconditions, null, 1, $import_id);

        $events_count = ($events == null ? 0 : (is_object($events) ? 1 : count($events)));
        $oa_count = ($oa == null ? 0 : 1);

        if ($layout == "list") {
            $return = '<ul class="arlo-event-next-running">';
        }
        
        if($events_count == 0 && $oa_count == 0 && !empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi'])) {
            $return .= ($layout == 'list' ? "<li>" : "");
            $return .= '<a href="' . esc_url($GLOBALS['arlo_eventtemplate']['et_registerinteresturi']) . '" title="' . __('Register interest', 'arlo-for-wordpress') . '" class="' . esc_attr($buttonclass) . '">' . __('Register interest', 'arlo-for-wordpress') . '</a>';
            $return .= ($layout == 'list' ? "</li>" : "");
        } else {
            if ($display_count && $events_count) {
                $return .= ($layout == 'list' ? "<li>" : "");

                $display_text = str_replace('{%count%}', $oa_count + $events_count, $text);

                $href = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);

                $return .= sprintf('<a href="%s">%s</a>', esc_html($href), esc_html($display_text));

                $return .= ($layout == 'list' ? "</li>" : "");
            } else if ($events_count) {
                $return_links = [];
                
                if (!is_array($events)) {
                    $events = array($events);
                }

                $event_has_discount_offer = self::get_event_has_discount_offer_array($events, $import_id);

                foreach ($events as $event) {
                    if (!empty($event->e_startdatetime)) {
                        $dateFormat = $format;
                        if(date('y', strtotime($event->e_startdatetime)) == date('y') && $removeyear) {
                            $dateFormat = trim(preg_replace('/\s+/', ' ', str_replace(["%Y", "%y", "Y", "y", "%g", "%G"], "", $dateFormat)));
                        }
                        
                        $location = $event->e_locationname;

                        if ($dateFormat == 'period') {
                            $startDay = self::event_date_formatter('j', $event->e_startdatetime, $event->e_startdatetimeoffset, $event->e_starttimezoneabbr, $event->e_timezone_id, $event->e_isonline);
                            $startMonth = self::event_date_formatter('M', $event->e_startdatetime, $event->e_startdatetimeoffset, $event->e_starttimezoneabbr, $event->e_timezone_id, $event->e_isonline);
                            $startYear = self::event_date_formatter('y', $event->e_startdatetime, $event->e_startdatetimeoffset, $event->e_starttimezoneabbr, $event->e_timezone_id, $event->e_isonline);
                            $finishDay = self::event_date_formatter('j', $event->e_finishdatetime, $event->e_finishdatetimeoffset, $event->e_finishtimezoneabbr, $event->e_timezone_id, $event->e_isonline);
                            $finishMonth = self::event_date_formatter('M', $event->e_finishdatetime, $event->e_finishdatetimeoffset, $event->e_finishtimezoneabbr, $event->e_timezone_id, $event->e_isonline);
                            $finishYear = self::event_date_formatter('y', $event->e_finishdatetime, $event->e_finishdatetimeoffset, $event->e_finishtimezoneabbr, $event->e_timezone_id, $event->e_isonline);

                            if (strcmp($startYear, $finishYear) != 0 || strcmp($startMonth, $finishMonth) != 0) {
                                $date = sprintf("%s %s - %s %s", $startDay, $startMonth, $finishDay, $finishMonth);
                            }
                            else if (strcmp($startDay, $finishDay) != 0) {
                                $date = sprintf("%s - %s %s", $startDay, $finishDay, $startMonth);
                            } else {
                                $date = sprintf("%s %s", $startDay, $startMonth);
                            }
                        } else {
                            $date = self::event_date_formatter($dateFormat, $event->e_startdatetime, $event->e_startdatetimeoffset, $event->e_starttimezoneabbr, $event->e_timezone_id, $event->e_isonline);
                        }
    
                        $display_text = str_replace(['{%date%}', '{%location%}'], [esc_html($date), esc_html($location)], $text);

                        $link = ($layout == 'list' ? "<li>" : "");
    
                        $fullclass = $event->e_isfull ? ' arlo-event-full' : ' arlo-register';
                        $limitedclass = (!empty($event->e_placesremaining) ? ' arlo-event-limited' : '');
                        $discountclass = (!empty($event_has_discount_offer[ $event->e_id ]) ? ' arlo-event-discount' : '');

                        switch ($template_link) {
                            case "permalink":
                                $url = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);
                                $link .= self::get_event_date_link($url, $buttonclass . $fullclass . $limitedclass . $discountclass, $display_text);
                                break;
                            case "none":
                                $link .= '<span class="' . esc_attr($dateclass) . '">' . $display_text . '</span>';
                                break;
                            case "viewuri":
                                $url = $GLOBALS['arlo_eventtemplate']['et_viewuri'];
                                $link .= self::get_event_date_link($url, $buttonclass . $fullclass . $limitedclass . $discountclass, $display_text);
                                break;
                            case "registerlink":
                                if ($event->e_registeruri && !$event->e_isfull) {
                                    $url = $event->e_registeruri;
                                } else {
                                    $url = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);
                                }
                                $link .= self::get_event_date_link($url, $buttonclass . $fullclass . $limitedclass . $discountclass, $display_text);
                                break;
                            case "single":
                                $url = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);
                                if (substr($url, -1) != '/'){ $url .= '/'; }
                                $url .= "event-" . $event->e_arlo_id . '/';
                                $link .= self::get_event_date_link($url, $buttonclass . $fullclass . $limitedclass . $discountclass, $display_text);
                                break;
                        }
    
                        $link .= ($layout == 'list' ? "</li>" : "");
    
                        $return_links[] = $link;
                    }   
                }   
                
                $return .= implode(($layout == 'list' ? "" : ", "), $return_links);
            } 
            
            //show only, if there is no events or delivery filter set to "OA"
            if (($events_count == 0 || (count($arlo_delivery) == 1 && $arlo_delivery[0] == 99)) && $oa_count) {
                $reference_terms = json_decode($oa->oa_reference_terms, true);
                $buttonclass = 'arlo-register';

                if (is_array($reference_terms) && isset($reference_terms['Plural'])) {
                    $tag = 'a';
                    $class = esc_attr($buttonclass);
                    $href = '';
                    switch ($template_link) {
                        case "permalink":
                        case "single":
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
    
                    $return .= sprintf('<%s %s class="%s">%s</%s>', $tag, $href, $class, $reference_terms['Singular'], $tag);
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


    private static function get_event_has_discount_offer_array($events, $import_id) {
        $array = [];

        foreach ($events as $event) {
            if (isset($event->e_id)) {
                $array[ $event->e_id ] = false;
            }
        }
        $ids = array_keys($array);

        $conditions = array(
            'event_id' => $ids,
            'discounts' => true
        );
        $offers = \Arlo\Entities\Offers::get($conditions, null, null, $import_id);

        foreach ($offers as $offer) {
            $array[ $offer->e_id ] = true;
        }

        return $array;
    }


    public static function event_date_formatter($format, $date, $offset, $abbreviation, $timezoneid, $is_online) {
        $plugin = Arlo_For_Wordpress::get_instance();
        $timezone = $wp_timezone = $selected_timezone = null;
        $original_timezone = date_default_timezone_get();
        
        $timewithtz = str_replace(' ', 'T', $date) . $offset;

        $date = new \DateTime($timewithtz);

        $utc_timezone_name = "UTC";

        $timezone_array = $plugin->get_timezone_manager()->get_indexed_timezones($timezoneid);
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

        if (!empty($GLOBALS['selected_timezone_names'])) {
            $selected_timezone = new \DateTimeZone($GLOBALS['selected_timezone_names']);
        }
      
        if($is_online) {
            if ($timezone instanceof \DateTimeZone && $selected_timezone instanceof \DateTimeZone && $timezone->getName() != $selected_timezone->getName()) {
                $abbreviation = "";
            }
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

        if (empty($abbreviation)) {
            $abbreviation = $date->format('T');
        }


        if (empty($format)) {
            $format = 'D g:i A';
        }

        if (strpos($format, '%') === false) {
            $format = DateFormatter::date_format_to_strftime_format($format);
        }

        $wp_timezone = null;

        try {
            $wp_timezone = new \DateTimeZone(get_option('timezone_string'));
        } catch (\Exception $e) {}
        $format_abbreviation = '';
        if (!is_null($timezone) && ($timezone->getName() == $utc_timezone_name || (!is_null($wp_timezone) && $wp_timezone->getOffset($date) != $timezone->getOffset($date)) || !is_null($selected_timezone) || $is_online) && preg_match('[I|M]', $format) === 1 && preg_match('[Z|z]', $format) === 0) {
            $format .= " %Z";            
        }

        if (strpos($format, '%Z')) {
            $format = str_replace('%Z', '', $format); //T
            $format_abbreviation = '{TZ_ABBREV}';
        }

        if (strpos($format, '%z')) {
            $format = str_replace('%z', '', $format); //P
            $format_abbreviation = '{TZ_OFFSET}';
        }
        //Old function URL - https://www.php.net/manual/en/function.strftime.php - This function is deprecated from PHP 8.1
        //New function URL - https://www.php.net/manual/en/datetime.format.php
        //I am actually converting time from using strftime function to new datetime function so it convert same time format.
        $format_array = array("%"=>"","a"=>"D","A"=>"l","d"=>"d","e"=>"j","u"=>"N","w"=>"w","U"=>"W","V"=>"W","W"=>"W","b"=>"M","B"=>"F","h"=>"M","m"=>"m","C"=>"y","g"=>"y","G"=>"Y","y"=>"y","Y"=>"Y","H"=>"H","k"=>"G","I"=>"h","l"=>"g","M"=>"i","p"=>"A","P"=>"a","r"=>"h:i:s A","R"=>"H:i","S"=>"s","T"=>"H:i:s","X"=>"","z"=>"","Z"=>"","c"=>"","D"=>"m/d/y","F"=>"m/d/y","s"=>"U","x"=>"");
        $format = strtr($format,$format_array);

        $date->setTimezone(new \DateTimeZone($timezone->getName()));

        $date = str_replace(['{TZ_ABBREV}', '{TZ_OFFSET}'], [$abbreviation, $date->format('P')], date($format, $date->getTimestamp()).$format_abbreviation);

        //if we haven't got timezone, we need to append the timezone abbrev
        if ($is_online && is_null($timezone) && (preg_match('[I|M]', $format) === 1) && !empty($offset)) {
            $date .=  " (" . $offset . ")";
        }

        date_default_timezone_set($original_timezone);

        return $date;
    }

}