<?php
namespace ArloTraining\Shortcodes;

use Arlo_For_Wordpress;
use ArloTraining\DateFormatter;
use ArloTraining\Entities\Categories as CategoriesEntity;
use ArloTraining\CacheControl;
use Exception;

class Events {
    public static function init() {
        $class = new \ReflectionClass(__CLASS__);

        $shortcodes = array_filter($class->getMethods(), function($method) {
            return strpos($method->name, 'shortcode_') === 0;
        });

        foreach ($shortcodes as $shortcode) {
            $shortcode_name = str_replace('shortcode_', '', $shortcode->name);

            Shortcodes::add($shortcode_name, function($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
                $method_name = 'shortcode_' . str_replace('arlo_', '', $shortcode_name);
                if (!is_array($atts) && empty($atts)) { $atts = []; }
                return self::$method_name($content, $atts, $shortcode_name, $import_id);
            });
        }

        Shortcodes::add('event_list', function($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
            return $content;
        });         
    }

    private static function shortcode_event_filters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {  
        global $post, $wpdb;

        extract(shortcode_atts(array(
            'filters'   => 'location',
            'resettext' => esc_html__('Reset', 'arlo-training-and-event-management-system'),
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

            $items = \ArloTraining\Shortcodes\Filters::get_filter_options($filter_key, $import_id, $post->ID);

            $filter_html .= Shortcodes::create_filter($filter_key, $items, \Arlo_For_Wordpress::$filter_labels[$filter_key], 'generic', null, 'event');

        endforeach; 
            
        if (!empty($filter_html)) {
            return '
            <form id="arlo-event-filter" class="arlo-filters" method="get" action="' . esc_url($page_link) . '">
                ' . $filter_html . '
                <div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' . esc_url($page_link) . '">
                    <a href="' . esc_url($page_link) . '" class="' . esc_attr($buttonclass) . '">' . esc_html($resettext) . '</a>
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
        
        $prepared_sql = $wpdb->prepare(
            "
            SELECT 
                tag
            FROM 
                {$wpdb->prefix}arlo_tags AS t
            LEFT JOIN 
                {$wpdb->prefix}arlo_events_tags AS et 
            ON
                tag_id = id
            WHERE
                et.e_id = %d
            AND 
                t.import_id = %d
            AND
                et.import_id = %d", $id, $import_id, $import_id);
        
        $items = CacheControl::fetch_results($prepared_sql, ARRAY_A);
            
        foreach ($items as $t) {
            $tags[] = $t['tag'];
        }

        if (count($tags)) {
            switch($layout) {
                case 'list':
                    $output = '<ul class="arlo-event_tags-list">';
                    
                    foreach($tags as $tag) {
                        $output .= '<li>' . esc_html($tag) . '</li>';
                    }
                    
                    $output .= '</ul>';
                break;
                
                case 'class':
                
                    $classes = [];
                    foreach($tags as $tag) {
                        $classes[] = esc_attr(sanitize_title($prefix . $tag));
                    }
                    
                    $output = implode(' ', $classes);
                    
                break;      
            
                default:
                        $output = '<div class="arlo-event_tags-list">' . implode(', ', array_map(function($tag) { return esc_html($tag); }, $tags)) . '</div>';
                break;
            }   
        }
        
        return $output;        
    }

    private static function shortcode_event_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;
        $sql = self::generate_events_list_sql($atts, $import_id);

        $items = CacheControl::fetch_results($sql, ARRAY_A);

        extract(shortcode_atts(array(
            'show' => '',
            'within_ul' => 'true'
        ), $atts, $shortcode_name, $import_id));
        $within_ul = filter_var($within_ul, FILTER_VALIDATE_BOOLEAN);
        
        $output = '';
        
        if (is_array($items) && count($items)) {
            unset($GLOBALS['arlo_no_event']);
            foreach($items as $key => $item) {
        
                $GLOBALS['arlo_event_list_item'] = $item;

                if (strpos($content, '[arlo_venue_') !== false) {
                    $conditions = array(
                        'id' => $item['v_id']
                    );
    
                    $GLOBALS['arlo_venue_list_item'] = \ArloTraining\Entities\Venues::get($conditions, null, null, $import_id);    
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


            $arlo_event_id = \ArloTraining\Utilities::clean_string_url_parameter('arlo-event-id');
            if (!empty($arlo_event_id)){
                $url = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);

                $output .= "<div class='arlo-single-show-wrapper'>
                            <a href='" . esc_url($url) . "' class='arlo-show-more arlo-button button'>
                                " . esc_html__("Show More", "arlo-training-and-event-management-system") . "
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

        $parameters[] = $import_id;
        $parameters[] = $post->ID;
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        $arlo_location = \ArloTraining\Utilities::clean_string_url_parameter('arlo-location');
        $arlo_state = \ArloTraining\Utilities::clean_string_url_parameter('arlo-state');
        $arlo_event_id = \ArloTraining\Utilities::clean_string_url_parameter('arlo-event-id');
        
        if (!empty($arlo_region)) {
            $where .= " AND {$wpdb->prefix}arlo_eventtemplates.et_region = %s AND {$wpdb->prefix}arlo_events.e_region = %s";
            $parameters[] = $arlo_region;
            $parameters[] = $arlo_region;
        }

        if (!empty($arlo_location)) {
            $where .= " AND {$wpdb->prefix}arlo_events.e_locationname = %s";
            $parameters[] = $arlo_location;
        };

        if (!empty($arlo_event_id)){
            $where .= " AND {$wpdb->prefix}arlo_events.e_arlo_id = %d";
            $parameters[] = $arlo_event_id;
        }

        if (!empty($arlo_state)) {
            $venues = \ArloTraining\Entities\Venues::get(['state' => $arlo_state], null, null, $import_id);

            if (count($venues)) {
                $join['ce'] = " LEFT JOIN {$wpdb->prefix}arlo_events AS ce ON {$wpdb->prefix}arlo_events.e_arlo_id = ce.e_parent_arlo_id AND {$wpdb->prefix}arlo_events.import_id = ce.import_id ";

                $venues = array_map(function ($venue) {
                    return $venue['v_arlo_id'];
                }, $venues);
                
                $where .= " AND (ce.v_id IN (" . implode(',', array_map(function() {return "%d";}, $venues)) . ") OR {$wpdb->prefix}arlo_events.v_id IN (" . implode(',', array_map(function() {return "%d";}, $venues)) . "))";
                $parameters = array_merge($parameters, $venues);
                $parameters = array_merge($parameters, $venues);
            }
        };        

        $sql = 
            "SELECT 
                {$wpdb->prefix}arlo_events.*, 
                {$wpdb->prefix}arlo_eventtemplates.et_descriptionsummary,
                {$wpdb->prefix}arlo_eventtemplates.et_hero_image,
                {$wpdb->prefix}arlo_eventtemplates.et_list_image
            FROM 
                {$wpdb->prefix}arlo_events
            LEFT JOIN 
                {$wpdb->prefix}arlo_eventtemplates
            ON 
                {$wpdb->prefix}arlo_events.et_arlo_id = {$wpdb->prefix}arlo_eventtemplates.et_arlo_id
            AND
                {$wpdb->prefix}arlo_eventtemplates.import_id = {$wpdb->prefix}arlo_events.import_id
            " . implode("\n", $join) ." 
            WHERE 
                {$wpdb->prefix}arlo_eventtemplates.import_id = %d
            AND
                {$wpdb->prefix}arlo_eventtemplates.et_post_id = %d
            AND 
                {$wpdb->prefix}arlo_events.e_parent_arlo_id = 0
            $where
            GROUP BY 
                e_arlo_id
            ORDER BY 
                {$wpdb->prefix}arlo_events.e_startdatetime";

        if (count($parameters)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
            return $wpdb->prepare($sql, $parameters);
        }


        return $sql;
    }

    private static function shortcode_event_code($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_code'])) return '';

        return esc_html($GLOBALS['arlo_event_list_item']['e_code']);        
    }

    private static function shortcode_event_name($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_name']) && !isset($GLOBALS['arlo_event_session_list_item']['e_code'])) return '';
        
        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        return esc_html($event['e_name']);        
    }

    private static function shortcode_event_notice($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_notice'])) return '';
        
        return esc_html($GLOBALS['arlo_event_list_item']['e_notice']);  
    }

    private static function shortcode_event_location($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_locationname']) && !isset($GLOBALS['arlo_event_session_list_item']['e_locationname'])) return '';

        // merge and extract attributes
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));
        
        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        $location = esc_html($event['e_locationname']);

        $conditions = array(
            'id' => $event['v_id']
        );
        switch ($link) {
            case 'permalink': 
                if(!($event['e_isonline'] || $event['v_id'] == 0 || $event['e_locationvisible'] == 0)) {
                    $venue = \ArloTraining\Entities\Venues::get($conditions, null, null, $import_id);
                    
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
            $location = '<a href="'.esc_url($permalink).'">'.$location.'</a>';    
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
            'hidesameentry' => 'false', //if we just want Y and start Y == end Y, then only show Y ,ranther than Y - Y
            'connectwith'  => ' - '
        ), $atts, $shortcode_name, $import_id));
        $connectwith = esc_html($connectwith);

        $start_date = new \DateTime($event['e_startdatetime']);
        $end_date = new \DateTime($event['e_finishdatetime']);

        $args[1]['format'] = $startdateformat;
        $formated_start = call_user_func_array([self::class, 'shortcode_event_start_date'], $args);
        $formatted_start_date = '<span class="arlo-start-date">' . $formated_start  . '</span>';

        $formatted_end_date = '';
        if ($start_date->format('Y-m-d') !== $end_date ->format('Y-m-d')) {
            $args[1]['format'] = $enddateformat;
            $formated_end = call_user_func_array([self::class, 'shortcode_event_end_date'], $args);
            $formatted_end_date = $connectwith  . '<span class="arlo-end-date">' . $formated_end . '</span>';
            if($hidesameentry === "true" && $formated_end == $formated_start) {
                $formatted_end_date = '';
            }
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
                        $output .= '<li>' . esc_html($credit->Type) . ': ' . esc_html($credit->Value) . '</li>';
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
        $fullclass = (!empty($atts['fullclass']) ? $atts['fullclass'] : $class );

        $extra_cls = $isfull ? 'arlo-event-registration-full' : '';
        $registration = '<div class="arlo-event-registration ' . $extra_cls . '">';
        $registration .= (($isfull) ? '<span class="arlo-event-full">' . esc_html__('Event is full', 'arlo-training-and-event-management-system') . '</span>' : '');
        // test if there is a register uri string, if so display the button
        if(!is_null($registeruri) && $registeruri != '') {
            $linktext = (($isfull) ? __('Join waiting list', 'arlo-training-and-event-management-system') : $registermessage);
            $registration .= '<a aria-label="' . esc_attr($linktext . ', opens in a new tab') .'" class="' . esc_attr($isfull ? $fullclass : $class) . ' ' . (($isfull) ? 'arlo-waiting-list' : 'arlo-register') . '" href="'. esc_url($registeruri) . '" target="_blank">';
            $registration .= esc_html($linktext) . '</a>';
        } else {
            $registration .= esc_html($registermessage);
        }

        if ($placesremaining > 0) {
            /* translators: %d: places count */
            $registration .= '<span class="arlo-places-remaining">' . esc_html(sprintf( _n( '%d place remaining', '%d places remaining', $placesremaining, 'arlo-training-and-event-management-system' ), $placesremaining )) .'</span>';    
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
        $items = \ArloTraining\Entities\Presenters::get(['e_id' => $e_id], null, null, $import_id);

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

            $presenter_name = esc_html($item['p_firstname']) . ' ' . esc_html($item['p_lastname']);

            $presenter_html = ! empty( $permalink )
                ? '<a href="' . esc_url( $permalink ) . '">' . $presenter_name . '</a>'
                : $presenter_name;

            if ( $layout === 'list' ) {
                $presenter_html = '<li>' . $presenter_html . '</li>';
            }

            $presenters[] = $presenter_html;
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
            $output = '<a href="' . esc_url($GLOBALS['arlo_event_list_item']['e_providerwebsite']) . '" target="_blank">' . esc_html($GLOBALS['arlo_event_list_item']['e_providerorganisation']) . "</a>";
        } else {
            $output = esc_html($GLOBALS['arlo_event_list_item']['e_providerorganisation']);
        }   

        return $output;        
    }

    private static function shortcode_event_session_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_arlo_id'])) return '';
        global $post, $wpdb;

        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        $output = $where = '';

        extract(shortcode_atts(array(
            'label' => esc_html__('Session information', 'arlo-training-and-event-management-system'),
            'header' => esc_html__('Sessions', 'arlo-training-and-event-management-system'),
            'layout' => 'tooltip'
        ), $atts, $shortcode_name, $import_id));

        $parameters[] = $GLOBALS['arlo_event_list_item']['e_arlo_id'];
        $parameters[] = $import_id;
        
        if (!empty($arlo_region)) {
            $where = ' AND e_region = %s';
            $parameters[] = $arlo_region;
        }
        

        $sql_sessions = "
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
                e_parent_arlo_id = %d
            AND
                import_id = %d
                {$where}
            ORDER BY 
                e_startdatetime";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
        $sql = $wpdb->prepare($sql_sessions, $parameters);
                        
        $items = CacheControl::fetch_results($sql, ARRAY_A);
        if (is_array($items) && count($items)) {
            $open = '';
            $close = '';
            $item_tag = '%s';

            switch($layout) {
                case 'popup':
                    $modal_id = ARLO_PLUGIN_PREFIX . '_session_modal_' . $GLOBALS['arlo_event_list_item']['e_arlo_id'];

                    $open = '
                        <a href="" class="arlo-sessions-popup-trigger" data-target="'.esc_attr('#' . $modal_id) . '">
                          ' .  esc_html($label) . '
                        </a>

                        <div class="arlo-sessions-popup-content" id="' . esc_attr($modal_id) . '">
                            <div class="arlo-sessions-popup-header"><h2>' . esc_html($header) . '</h2></div>
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
                    $open = '<div data-tooltip="'.esc_attr('#' . ARLO_PLUGIN_PREFIX . '_session_tooltip_' . $GLOBALS['arlo_event_list_item']['e_arlo_id']) . '" class="' . esc_attr(ARLO_PLUGIN_PREFIX . '-tooltip-button').'">' . esc_html($label) . '</div>
                <div class="' . esc_attr(ARLO_PLUGIN_PREFIX . '-tooltip-html').'" id="' . esc_attr(ARLO_PLUGIN_PREFIX . '_session_tooltip_' . $GLOBALS['arlo_event_list_item']['e_arlo_id']) . '"><h5>' . esc_html($header) . '</h5>';

                    $close = '</div>';
                    break;
            }

            $output .= $open;
            $idx = 1;
            $contenthtml = $content;
            foreach($items as $key => $item) {
        
                $GLOBALS['arlo_event_session_list_item'] = $item;
                
                $contenthtml = $content;
                $contenthtml = str_replace('{%index%}', $idx, $contenthtml);

                $output .= sprintf($item_tag, do_shortcode($contenthtml));
                
                unset($GLOBALS['arlo_event_session_list_item']);
                $idx += 1;
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
    
            $events = \ArloTraining\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), 1, $import_id);
            
            if(empty($events)) return;
            
            $start = $events->e_startdatetime;
            $end = $events->e_finishdatetime;
        }

        $difference = strtotime($end)-strtotime($start);// seconds

        $hours = floor($difference/60/60);

        $showweek = isset($atts['showweek']) ? 'true' : 'false';
            
        // if we're the same day, display hours
        if(gmdate('d-m', strtotime($start)) == gmdate('d-m', strtotime($end)) || $hours <= 6) {

           
            
            if ($hours >= 6) {
                $weekday = '';
                if($showweek === 'true') {
                    $weekday = ', ' . gmdate('D', strtotime($start));
                }
                return esc_html__('1 day', 'arlo-training-and-event-management-system') . $weekday;
            }

            if($showweek === 'true') {
                return gmdate('D', strtotime($start));
            }

            $minutes = ceil(($difference % 3600)/60);

            $duration = '';
            
            if($hours > 0) {
                /* translators: %d: hour count */
                $duration .= sprintf(esc_html(_n('%d hour', '%d hours', $hours, 'arlo-training-and-event-management-system')), esc_html($hours));
            }

            if($hours > 0 && $minutes > 0) {
                $duration .= ', ';
            }

            if($minutes > 0) {
                /* translators: %d: minute count */
                $duration .= sprintf(esc_html(_n('%d minute', '%d minutes', $minutes, 'arlo-training-and-event-management-system')), esc_html($minutes));
            }
            
            return $duration;
        }
        $after = isset($atts['after']) ? wp_kses_post($atts['after']) : '';
        // if not the same day, and less than 7 days, then show number of days
        if(ceil($difference/60/60/24) <= 7) {
            $days = ceil($difference/60/60/24);
            /* translators: %d: day count */
            return sprintf(esc_html(_n('%d day','%d days', $days, 'arlo-training-and-event-management-system')), esc_html($days)) . $after;
        }
        
        // if not the same day, and more than 7 days, then show number of weeks
        if(ceil($difference/60/60/24) > 7) {
            $weeks = ceil($difference/60/60/24/7);
            /* translators: %d: week count */
            return sprintf(esc_html(_n('%d week','%d weeks', $weeks, 'arlo-training-and-event-management-system')), esc_html($weeks)) . $after;     
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
        $free_text = (isset($settings['free_text'])) ? $settings['free_text'] : esc_html__('Free', 'arlo-training-and-event-management-system');
        
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

                        $offer = \ArloTraining\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
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

                        $offer = \ArloTraining\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
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

                        $event = \ArloTraining\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), 1, $import_id);

                        if(empty($event)) return;
                        
                        $conditions = array(
                            'event_id' => $event->e_id,
                            'discounts' => false
                        );
                        
                        if (!empty($arlo_region)) {
                            $conditions['region'] = $arlo_region; 
                        }       
                        
                        $offer = \ArloTraining\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
                    }
                    break;

                case 'onlineactivity':
                    // if none, try the associated online activity
                    if (isset($GLOBALS['arlo_event_list_item']['et_arlo_id'])) {
                        $showfrom = false;

                        $conditions = array(
                            'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id']
                        );
                        
                        $oa = \ArloTraining\Entities\OnlineActivities::get($conditions, null, 1, $import_id);
                        
                        if(empty($oa)) return;
                        
                        $conditions = array(
                            'oa_id' => $oa->oa_id,
                            'discounts' => false
                        );
                        
                        if (!empty($arlo_region)) {
                            $conditions['region'] = $arlo_region; 
                        }       
                        
                        $offer = \ArloTraining\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);   
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
                        
                        $offer = \ArloTraining\Entities\Offers::get($conditions, array("o.{$price_field} ASC"), 1, $import_id);
                    }
                    break;
            }

            if($offer) break; // exit foreach
        }

        if(empty($offer)) return;
        
        // if $0.00, return "Free"
        if((float)$offer->$price_field == 0) {
            return esc_html($free_text);
        }

        $fromtext = '';
        if (strtolower($showfrom) === "true") {
            $fromtext = '<span class="arlo-from-text">' . esc_html__('From', 'arlo-training-and-event-management-system') . '</span> ';
        }

        return $fromtext . esc_html($offer->$price_field_show);
    }

    private static function shortcode_event_rich_snippet($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $event_snippet = self::get_rich_snippet_data($content, $atts, $shortcode_name, $import_id);
        $event_snippet = Shortcodes::create_rich_snippet( $event_snippet );

        $course_snippet = array();
        $course_snippet['@context'] = 'https://schema.org';
        $course_snippet['@type'] = 'Course';
        $course_snippet['name'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_eventtemplate'],'et_name');

        $course_snippet['description'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'et_descriptionsummary');

        $et_link = \ArloTraining\Utilities::get_absolute_url( self::get_et_link($GLOBALS['arlo_eventtemplate'],$link) );

        $course_snippet['url'] = $et_link;

        // Provider: use the WordPress site name and URL as the course provider.
        $course_snippet['provider'] = array(
            '@type' => 'Organization',
            'name'  => get_bloginfo( 'name' ),
            'url'   => home_url(),
        );

        // courseCode: optional, only emit when the template has a code.
        $et_code = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_eventtemplate'],'et_code');
        if ( !empty( $et_code ) ) {
            $course_snippet['courseCode'] = $et_code;
        }

        // image: use the template hero image when present.
        $hero_image = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_eventtemplate'],'et_hero_image');
        if ( !empty( $hero_image ) ) {
            $course_snippet['image'] = [ $hero_image ];
        }

        $course_snippet = Shortcodes::create_rich_snippet( $course_snippet );

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
        $event_snippet['@context'] = 'https://schema.org';
        $event_snippet['@type'] = 'Event';
        $event_snippet['eventStatus'] = 'https://schema.org/EventScheduled';
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

        $et_link = \ArloTraining\Utilities::get_absolute_url( self::get_et_link($GLOBALS['arlo_eventtemplate'],$link) );

        if (!empty($GLOBALS['arlo_event_list_item']['et_descriptionsummary'])) {
            $event_snippet['description'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_event_list_item'],'et_descriptionsummary');
        }

        $event_snippet['url'] = $et_link;

        // Determine attendance mode: online flag or location name equals "Online" (case-insensitive)
        $location_name = Shortcodes::get_rich_snippet_field( $GLOBALS['arlo_event_list_item'], 'e_locationname' );
        $is_virtual = !empty( $GLOBALS['arlo_event_list_item']['e_isonline'] ) || ( strcasecmp( $location_name, 'online' ) === 0 );
        $event_snippet['eventAttendanceMode'] = $is_virtual
            ? 'https://schema.org/OnlineEventAttendanceMode'
            : 'https://schema.org/OfflineEventAttendanceMode';

        // Image (recommended)
        $hero_image = Shortcodes::get_rich_snippet_field( $GLOBALS['arlo_event_list_item'], 'et_hero_image' );
        if ( !empty( $hero_image ) ) {
            $event_snippet['image'] = [ $hero_image ];
        }

        // Location
        if ( $is_virtual ) {
            $event_snippet["location"] = [ '@type' => 'VirtualLocation' ];
            if ( !empty( $et_link ) ) {
                $event_snippet["location"]['url'] = $et_link;
            }
        } else {
            $event_snippet["location"] = array();
            $event_snippet["location"]["@type"] = "Place";

            $conditions = array(
                'id' => $GLOBALS['arlo_event_list_item']['v_id']
            );

            $venue = \ArloTraining\Entities\Venues::get($conditions, null, null, $import_id);

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

            if ( $v_is_hidden && !empty( $location_name ) ) {
                $event_snippet["location"]["name"] = $location_name;

                $event_snippet["location"]["address"] = array(
                    "@type" => "PostalAddress",
                    "addressLocality" => $location_name
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

                if (!empty($country) && !ctype_space($country)) {
                    $event_snippet["location"]["address"]["addressCountry"] = $country;
                }

                // Geo coordinates
                $geolatitude = Shortcodes::get_rich_snippet_field($venue,'v_geodatapointlatitude');
                $geolongitude = Shortcodes::get_rich_snippet_field($venue,'v_geodatapointlongitude');

                if ( !empty($geolatitude) && !empty($geolongitude) ) {
                    $event_snippet["location"]["geo"] = array();
                    $event_snippet["location"]["geo"]["@type"] = "GeoCoordinates";
                    $event_snippet["location"]["geo"]["latitude"] = $geolatitude;
                    $event_snippet["location"]["geo"]["longitude"] = $geolongitude;
                }
            }

            $v_link = get_permalink(arlo_get_post_by_name(Shortcodes::get_rich_snippet_field($venue,'v_post_name'), 'arlo_venue'));

            $v_link = \ArloTraining\Utilities::get_absolute_url($v_link);

            if (!empty($v_link) && !$v_is_hidden) {
                $event_snippet["location"]["url"] = $v_link;
            }
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
                $event_snippet["offers"]['availability'] = "https://schema.org/InStock";
            } else {
                $event_snippet["offers"]['availability'] = "https://schema.org/SoldOut";
            }

        }


        // Presenters
        $performers = array();
        $e_id = $id = isset($GLOBALS['arlo_event_session_list_item']['e_id']) ? $GLOBALS['arlo_event_session_list_item']['e_id'] : $GLOBALS['arlo_event_list_item']['e_id'];
        $presenters = \ArloTraining\Entities\Presenters::get(['e_id' => $e_id], null, null, $import_id);
        foreach ($presenters as $i => $presenter) {
            array_push($performers,Shortcodes::get_performer($presenter,$link));
        }

        if ( !empty( $performers ) ) {
            $event_snippet["performer"] = $performers;
        }

        // Organizer (recommended): use the event-level provider when available,
        // falling back to the WordPress site name and URL.
        $organiser_name = Shortcodes::get_rich_snippet_field( $GLOBALS['arlo_event_list_item'], 'e_providerorganisation' );
        if ( !empty( $organiser_name ) ) {
            $event_snippet['organizer'] = [
                '@type' => 'Organization',
                'name'  => $organiser_name,
            ];
            $organiser_url = Shortcodes::get_rich_snippet_field( $GLOBALS['arlo_event_list_item'], 'e_providerwebsite' );
            if ( !empty( $organiser_url ) ) {
                $event_snippet['organizer']['url'] = $organiser_url;
            }
        } else {
            $event_snippet['organizer'] = [
                '@type'  => 'Organization',
                'name'   => get_bloginfo( 'name' ),
                'url'    => home_url(),
            ];
        }

        return $event_snippet;
    }

    /**
     * @see ArloTraining\Shortcodes\Events->event_date_formatter()
     * @param  string $datetime
     * @param  string $offset
     * @param  integer $timezoneid
     * @return string
     */
    private static function rich_snippet_time_format($datetime, $offset, $timezoneid){
        $timezone = null;
        $timezone_array = \Arlo_For_Wordpress::get_instance()->get_timezone_manager()->get_indexed_timezones($timezoneid);
        if (!is_null($timezone_array) && !empty($timezone_array['windows_tz_id']) && !empty(\ArloTraining\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_array['windows_tz_id']])) {
            $timezone = new \DateTimeZone(\ArloTraining\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_array['windows_tz_id']]);
        } else {
            try {
                $timezone = new \DateTimeZone(str_replace(':', '', $offset));
            } catch(\Exception $e) {
                $timezone = new \DateTimeZone('UTC');
            }
        }

        $time = new \DateTime($datetime, $timezone);
        return $time->format(DATE_RFC3339);
    }

    private static function shortcode_no_event_text($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (!empty($GLOBALS['arlo_no_event_text'])) {
            $before = isset($atts['before']) ? wp_kses_post($atts['before']) : "";
            $after = isset($atts['after']) ? wp_kses_post($atts['after']) : "";
            $text = '<span class="arlo-no-results">' . esc_html($GLOBALS['arlo_no_event_text']) . '</span>';
            return $before . $text . $after;
        }
    }

    private static function shortcode_event_isfull($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_event_list_item']['e_isfull']) && empty($GLOBALS['arlo_event_session_list_item']['e_isfull'])) return;

        $event = (!empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item']);

        extract(shortcode_atts(array(
            'output' => 'Full'
        ), $atts, $shortcode_name, $import_id));

        if ($event["e_isfull"] == 1) {
            return esc_html($output);
        }
    }

    private static function shortcode_event_offers_hasdiscount($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['e_id'])) return;

        extract(shortcode_atts(array(
            'output' => 'Discount'
        ), $atts, $shortcode_name, $import_id));

        $offers = Shortcodes::get_advertised_offers($GLOBALS['arlo_event_list_item']['e_id'], 'e_id', $import_id, $GLOBALS['arlo_event_list_item']['e_is_taxexempt']);

        if (array_search('1', array_column($offers, 'o_isdiscountoffer')) !== false || array_search('1', array_column($offers, 'replacement_discount')) !== false) {
            return esc_html($output);
        }
    }

    private static function shortcode_event_haslimitedplaces($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_event_list_item']['e_placesremaining']) && empty($GLOBALS['arlo_event_session_list_item']['e_placesremaining'])) return;

        $event = (!empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item']);

        extract(shortcode_atts(array(
            'output' => 'Limited places'
        ), $atts, $shortcode_name, $import_id));

        if ($event["e_placesremaining"] > 0) {
            return esc_html($output);
        }
    }

    private static function shortcode_event_next_running($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']) || empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return;
        $return = "";

        $arlo_location = \ArloTraining\Utilities::get_filter_keys_string_array('location');
        $arlo_venue = \ArloTraining\Utilities::get_att_string('venue');
        $arlo_delivery = \ArloTraining\Utilities::get_filter_keys_int_array('delivery');
        $arlo_state = \ArloTraining\Utilities::clean_string_url_parameter('arlo-state');

        $arlo_locationhidden = \ArloTraining\Utilities::get_filter_keys_string_array('locationhidden');
        $arlo_deliveryhidden = \ArloTraining\Utilities::get_filter_keys_int_array('deliveryhidden');
        
        if (!empty($GLOBALS['arlo_eventtemplate']['et_region'])) {
            $arlo_region = $GLOBALS['arlo_eventtemplate']['et_region'];
        } else {
            $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        }

        // merge and extract attributes
        extract(shortcode_atts(array(
            'buttonclass' => '',
            'dateclass' => '',
            'registerclass' => 'arlo-single-register',
            'format' => 'd M y',
            'format_as_html' => 'false',
            'ignore_resiter_link' => 'false',
            'layout' => '',
            'limit' => 1,
            'removeyear' => "true",
            'text' => '{%date%}',
            'template_link' => 'registerlink',
            'count_event_only' => 'false',
            'list_type' => ''
        ), $atts, $shortcode_name, $import_id));
        $aftertext = isset($atts['aftertext']) ? wp_kses_post($atts['aftertext']): '';
        $text = wp_kses_post($text);

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
            $arlo_venue = \ArloTraining\Utilities::convert_string_to_int_array($arlo_venue);
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

        if (isset($arlo_state) && isset($GLOBALS['arlo_state_filter_venues'])) {
            $conditions['state'] = $GLOBALS['arlo_state_filter_venues'];
        }

        $events = [];
        if (empty($arlo_delivery) || !(in_array(0, $arlo_delivery) && in_array(1, $arlo_delivery))) {
            $events = \ArloTraining\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), $limit, $import_id);
        }
        $oa = \ArloTraining\Entities\OnlineActivities::get($oaconditions, null, 1, $import_id);

        $events_count = ($events == null ? 0 : (is_object($events) ? 1 : count($events)));
        $oa_count = ($oa == null ? 0 : 1);

        if ($layout == "list") {
            $return = '<ul class="arlo-event-next-running">';
        }
        
        if($events_count == 0 && $oa_count == 0 && !empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi'])) {
            //if we just want the date string
            if($ignore_resiter_link != 'true') {
                $return .= ($layout == 'list' ? "<li>" : "");
                $reglabel = esc_html__('Register interest', 'arlo-training-and-event-management-system') . $aftertext;
                $return .= '<a role="button" href="' . esc_url($GLOBALS['arlo_eventtemplate']['et_registerinteresturi']) . '" title="' . esc_html__('Register interest', 'arlo-training-and-event-management-system') . '" class="' . esc_attr($buttonclass) . ' ' . esc_attr($registerclass) . '">' . $reglabel . '</a>';
                $return .= ($layout == 'list' ? "</li>" : "");
            }
        } else {
            if ($display_count && $events_count) {
                $return .= ($layout == 'list' ? "<li>" : "");

                $total_count = $oa_count + $events_count;
                if($count_event_only === 'true') {
                    $total_count = $events_count;
                }
                $display_text = str_replace('{%count%}', $total_count, $text);

                $href = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);

                $return .= sprintf('<a href="%s">%s</a>', esc_url($href), esc_html($display_text));

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
                        if(gmdate('y', strtotime($event->e_startdatetime)) == gmdate('y') && $removeyear) {
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
    
                        $datestr = $format_as_html == 'true' ? $date : esc_html($date);
                        // Assemble template, substitute tokens, then sanitise the result.
                        // wp_kses_post is applied after substitution because {%token%} placeholders are
                        // position-agnostic — they may appear in element content or attribute values —
                        // so context-specific escaping (esc_url/esc_attr) cannot be applied upfront.
                        $display_template = $text . $aftertext;
                        $display_text     = str_replace(['{%date%}', '{%location%}'], [$datestr, esc_html($location)], $display_template);
                        $display_text     = wp_kses_post($display_text);

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
                                if ($event->e_registeruri && !$event->e_isfull && $list_type != 'schedules') {
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
                            case "locationlink":
                                $url = self::get_next_running_location($event, $import_id);
                                if($url) { //this line is copied from [shortcode_event_location]
                                    $link .= self::get_event_date_link($url, $buttonclass . $fullclass . $limitedclass . $discountclass, $display_text);
                                } else {
                                    $link .= '<span class="' . esc_attr($dateclass) . '">' . $display_text . '</span>';
                                }
                                break;
                            case "presenterlist":
                                $items = \ArloTraining\Entities\Presenters::get(['e_id' => $event->e_id], null, null, $import_id);
                                $presenters = array();
                                if(count($items) > 0) {
                                    $link .= '<p class="arlo-list event-presenters">';
                                }
                                foreach($items as $item) {
                                    $permalink = get_permalink(arlo_get_post_by_name($item['p_post_name'], 'arlo_presenter'));
                                    $presenter_name = esc_html($item['p_firstname']) . ' ' . esc_html($item['p_lastname']);
                                    $presenters[] = (!empty($link) ? '<a href="' . esc_url($permalink) . '">' . $presenter_name . '</a>' : $presenter_name) ;
                                }
                                $link .= implode(', ', $presenters);

                                if (count($items) > 0) {
                                    $link .= '</p>';
                                }
                                break;

                        }
    
                        $link .= ($layout == 'list' ? "</li>" : "");

                        $return_links[] = $link;
                    }   
                }   
                
                $return .= implode(($layout == 'list' ? "" : ", "), $return_links);
            } 
            
            if (($events_count == 0 || (count($arlo_delivery) == 1 && $arlo_delivery[0] == 99)) && $oa_count && $template_link != 'presenterlist' && $template_link != 'none') {
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
                        case "locationlink":
                            $tag = 'span';
                            break;
                    }
                    
                    $return .= sprintf('<%s %s class="%s">%s'. $aftertext .'</%s>', $tag, $href, $class, esc_html($reference_terms['Singular']), $tag);
                }
            }
        }
        
        if ($layout == "list") {
            $return .= '</ul>';
        }
        return $return;
    }

    private static function shortcode_event_category_path($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $return = '';
        if(!isset($GLOBALS['arlo_eventtemplate']) || empty($GLOBALS['arlo_eventtemplate']['c_arlo_id'])) return $return;

        $wrap = isset($atts['item']) ? wp_kses_post($atts['item']) : '%s';
       
        $items = CategoriesEntity::get(array(),null,$import_id);
        $dict = array(); 
        $current = null;
        $index = 0;
        foreach($items as $item) {
            $dict[$item->c_arlo_id] = $item;
            if(intval($GLOBALS['arlo_eventtemplate']['c_arlo_id']) == $item->c_arlo_id) {
                $return = str_replace(['{slug}', '{label}'], [esc_attr($item->c_slug), esc_html($item->c_name)], $wrap);
                $current = $item;
            } else if(intval($GLOBALS['arlo_eventtemplate']['c_arlo_id']) && $item->c_parent_id == 0) {
                $return = str_replace(['{slug}', '{label}'], [esc_attr($item->c_slug), esc_html($item->c_name)], $wrap);
            }
        }
        while($current != null) {
            if(array_key_exists($current->c_parent_id, $dict)) {
                $current = $dict[$current->c_parent_id];
                $return = str_replace(
                    ['{slug}', '{label}'],
                    [esc_attr($current->c_slug), esc_html($current->c_name)],
                    $wrap
                ) . $return;
            } else {
                $current = null;
            }
        }

        return $return;
    }

    private static function get_event_date_link($url, $buttonclass, $display_text) {
        return sprintf('<a href="%s" class="%s">%s</a>', esc_url($url), esc_attr($buttonclass), $display_text);
    }

    private static function get_next_running_location($event, $import_id) {
        if(!($event->e_isonline || $event->v_id == 0 || $event->e_locationvisible == 0)) { //this line is copied from [shortcode_event_location]
            $conditions = array(
                'id' => $event->v_id
            );
            $venue = \ArloTraining\Entities\Venues::get($conditions, null, null, $import_id);
            if($venue != null) {
                $permalink = get_permalink(arlo_get_post_by_name($venue['v_post_name'], 'arlo_venue'));
                return $permalink;
            }
        }
        return '';
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
        $offers = \ArloTraining\Entities\Offers::get($conditions, null, null, $import_id);

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
        if (!is_null($timezone_array) && !empty($timezone_array['windows_tz_id']) && !empty(\ArloTraining\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_array['windows_tz_id']])) {
            $timezone = new \DateTimeZone(\ArloTraining\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$timezone_array['windows_tz_id']]);
        } else {
            try {
                $timezone = new \DateTimeZone(get_option('timezone_string'));
            } catch(\Exception $e) {
                $timezone = new \DateTimeZone($utc_timezone_name);
            }
        }

        if ($timezone != null) {
            $date->setTimezone($timezone);
        }

        $selected_timezone = null;

        if (!empty($GLOBALS['arlo_selected_timezone_names'])) {
            $selected_timezone = new \DateTimeZone($GLOBALS['arlo_selected_timezone_names']);
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

        preg_match_all('/%\w/', $format, $matches_date);

        $date_str_to_format = '';
        if (count($matches_date[0]) > 0) {
            $date_str_to_format = implode(' ', $matches_date[0]);
            $format = preg_replace('/%\w/', '%s', $format);
        }

        $format_array = array("%"=>"","a"=>"D","A"=>"l","d"=>"d","e"=>"j","u"=>"N","w"=>"w","U"=>"W","V"=>"W","W"=>"W","b"=>"M","B"=>"F","h"=>"M","m"=>"m","C"=>"y","g"=>"y","G"=>"Y","y"=>"y","Y"=>"Y","H"=>"H","k"=>"G","I"=>"h","l"=>"g","M"=>"i","p"=>"A","P"=>"a","r"=>"h:i:s A","R"=>"H:i","S"=>"s","T"=>"H:i:s","X"=>"","z"=>"","Z"=>"","c"=>"","D"=>"m/d/y","F"=>"m/d/y","s"=>"U","x"=>"");
        $date_str_to_format = strtr($date_str_to_format, $format_array);

        $date->setTimezone(new \DateTimeZone($timezone->getName()));

        $date = str_replace(['{TZ_ABBREV}', '{TZ_OFFSET}'], [$abbreviation, $date->format('P')], $date->format($date_str_to_format).$format_abbreviation);

        $date = sprintf($format, ...explode(' ', $date));
        
        //if we haven't got timezone, we need to append the timezone abbrev
        if ($is_online && is_null($timezone) && (preg_match('[I|M]', $format) === 1) && !empty($offset)) {
            $date .=  " (" . $offset . ")";
        }

        return $date;
    }

}