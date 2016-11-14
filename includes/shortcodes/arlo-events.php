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
        $settings = get_option('arlo_settings');
        $regions = get_option('arlo_regions');
        
        $where = '';
        
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');	
        
        $t1 = "{$wpdb->prefix}arlo_eventtemplates";
        $t2 = "{$wpdb->prefix}arlo_events";
        $t3 = "{$wpdb->prefix}arlo_venues";
        $t5 = "{$wpdb->prefix}arlo_presenters";
        $t6 = "{$wpdb->prefix}arlo_offers";
        
        if (!empty($arlo_region)) {
            $where .= ' AND ' . $t1 . '.et_region = "' . $arlo_region . '" AND ' . $t2 . '.e_region = "' . $arlo_region . '"';
        }					
        
        $sql = 
            "SELECT 
                $t2.*, 
                $t3.v_post_name 
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
            WHERE 
                $t1.import_id = $import_id
            AND
                $t1.et_post_name = '$post->post_name'
            AND 
                $t2.e_parent_arlo_id = 0
            $where
            ORDER BY 
                $t2.e_startdatetime";
            
            
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        $output = '';
        
        if (is_array($items) && count($items)) {
            unset($GLOBALS['no_event']);
            foreach($items as $key => $item) {
        
                $GLOBALS['arlo_event_list_item'] = $item;
                        
                if (!empty($atts['show']) && $key == $atts['show']) {
                    $output .= '</ul><div class="arlo-clear-both"></div><ul class="arlo-list arlo-show-more-hidden events">';
                }
        
                $output .= do_shortcode($content);
        
                unset($GLOBALS['arlo_event_list_item']);
            }	
        } 
        
        return $output;        
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

    private static function shortcode_event_location($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_locationname']) && !isset($GLOBALS['arlo_event_session_list_item']['e_locationname'])) return '';
        
        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        $location = htmlentities($event['e_locationname'], ENT_QUOTES, "UTF-8");

        if(!($event['e_isonline'] || $event['v_id'] == 0 || $event['e_locationvisible'] == 0)) {
            $permalink = get_permalink(arlo_get_post_by_name($event['v_post_name'], 'arlo_venue'));

            $location = '<a href="'.$permalink.'">'.$location.'</a>';
        }        

        return $location;
    }

    private static function shortcode_event_start_date($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_startdatetime']) && !isset($GLOBALS['arlo_event_session_list_item']['e_startdatetime'])) return '';

        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];
        
        return self::event_date_formatter($atts, $event['e_startdatetime'], $event['e_datetimeoffset'], $event['e_isonline']);
    }

    private static function shortcode_event_end_date($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_finishdatetime']) && !isset($GLOBALS['arlo_event_session_list_item']['e_finishdatetime'])) return '';
        
        $event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

        return self::event_date_formatter($atts, $event['e_finishdatetime'], $event['e_datetimeoffset'], $event['e_isonline']);        
    }

    private static function shortcode_event_session_description($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_event_list_item']['e_sessiondescription'])) return '';

        return htmlentities($GLOBALS['arlo_event_list_item']['e_sessiondescription'], ENT_QUOTES, "UTF-8");        
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
        $registration .= (($isfull) ? '<span class="arlo-event-full">' . __('Event is full', $GLOBALS['arlo_plugin_slug']) . '</span>' : '');
        // test if there is a register uri string, if so display the button
        if(!is_null($registeruri) && $registeruri != '') {
            $registration .= '<a class="' . $class . ' ' . (($isfull) ? 'arlo-waiting-list' : 'arlo-register') . '" href="'. esc_attr($registeruri) . '" target="_blank">';
            $registration .= (($isfull) ? __('Join waiting list', $GLOBALS['arlo_plugin_slug']) : __($registermessage, $GLOBALS['arlo_plugin_slug'])) . '</a>';
        } else {
            $registration .= $registermessage;
        }

        if ($placesremaining > 0) {
            $registration .= '<span class="arlo-places-remaining">' . sprintf( _n( '%d place remaining', '%d places remaining', $placesremaining, $GLOBALS['arlo_plugin_slug'] ), $placesremaining ) .'</span>';	
        }
        
        $registration .= '</div>';

        return $registration;        
    }

    private static function shortcode_event_offers($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        return Shortcodes::advertised_offers($GLOBALS['arlo_event_list_item']['e_id'], 'e_id', $import_id);
    }

    private static function shortcode_event_presenters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;

        $t1 = "{$wpdb->prefix}arlo_events_presenters";
        $t2 = "{$wpdb->prefix}arlo_presenters";

        $items = $wpdb->get_results("
        SELECT 
            p.p_firstname, 
            p.p_lastname, 
            p.p_post_name 
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

        // merge and extract attributes
        extract(shortcode_atts(array(
            'layout' => '',
            'link' => 'true'
        ), $atts, $shortcode_name, $import_id));

        $np = count($items);

        $output = '';

        if($link === 'false') $link = false;

        if($layout == 'list') {

            $output .= '<figure>';
            $output .= '<figcaption class="arlo-event-presenters-title">'._n('Presenter', 'Presenters', $np, $GLOBALS['arlo_plugin_slug']).'</figcaption>';
            $output .= '<ul class="arlo-list event-presenters">';

            foreach($items as $item) {

                $permalink = get_permalink(arlo_get_post_by_name($item['p_post_name'], 'arlo_presenter'));

                $link_start = ($link) ? '<a href="'.$permalink.'">' : '' ;

                $link_end = ($link) ? '</a>' : '' ;

                $output .= '<li>' . $link_start . htmlentities($item['p_firstname'], ENT_QUOTES, "UTF-8") . ' ' . htmlentities($item['p_lastname'], ENT_QUOTES, "UTF-8") . $link_end . '</li>';

            }

            $output .= '</ul>';
            $output .= '</figure>';

        } else {

            $presenters = array();

            foreach($items as $item) {

                $permalink = get_permalink(arlo_get_post_by_name($item['p_post_name'], 'arlo_presenter'));

                $link_start = ($link) ? '<a href="'.$permalink.'">' : '' ;

                $link_end = ($link) ? '</a>' : '' ;

                $presenters[] = $link_start . htmlentities($item['p_firstname'], ENT_QUOTES, "UTF-8") . ' ' . htmlentities($item['p_lastname'], ENT_QUOTES, "UTF-8") . $link_end;

            }

            $output .= implode(', ', $presenters);

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
        
        $regions = get_option('arlo_regions');
        
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');		
        
        $output = $where = '';
        
        extract(shortcode_atts(array(
            'label'	=> __('Session information', $GLOBALS['arlo_plugin_slug']),
            'header' => __('Sessions', $GLOBALS['arlo_plugin_slug']),
        ), $atts, $shortcode_name, $import_id));
        
        if (!empty($arlo_region)) {
            $where = ' AND e_region = "' . $arlo_region . '"';
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
        
        $conditions = array(
            'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id']
        );
        
        $events = \Arlo\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), 1, $import_id);
        
        if(empty($events)) return;
        
        $start = $events->e_startdatetime;
        $end = $events->e_finishdatetime;
        $difference = strtotime($end)-strtotime($start);// seconds
            
        // if we're the same day, display hours
        if(date('d-m', strtotime($start)) == date('d-m', strtotime($end))) {
            $hours = floor($difference/60/60);
                    
            if ($hours > 6) {
                return __('1 day', $GLOBALS['arlo_plugin_slug']);
            }

            $minutes = ceil(($difference % 3600)/60);

            $duration = '';
            
            if($hours > 0) {
                $duration .= sprintf(_n('%d hour', '%d hours', $hours, $GLOBALS['arlo_plugin_slug']), $hours);
            }

            if($hours > 0 && $minutes > 0) {
                $duration .= ', ';
            }

            if($minutes > 0) {
                $duration .= sprintf(_n('%d minute', '%d minutes', $minutes, $GLOBALS['arlo_plugin_slug']), $minutes);
            }
            
            return $duration;
        }
        
        // if not the same day, and less than 7 days, then show number of days
        if(ceil($difference/60/60/24) <= 7) {
            $days = ceil($difference/60/60/24);
            
            return sprintf(_n('%d day','%d days', $days, $GLOBALS['arlo_plugin_slug']), $days);
        }
        
        // if not the same day, and more than 7 days, then show number of weeks
        if(ceil($difference/60/60/24) > 7) {
            $weeks = ceil($difference/60/60/24/7);
            
            return sprintf(_n('%d week','%d weeks', $weeks, $GLOBALS['arlo_plugin_slug']), $weeks);		
        }
        
        return;        
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
        $free_text = (isset($settings['free_text'])) ? $settings['free_text'] : __('Free', $GLOBALS['arlo_plugin_slug']);
        
        $offer;
        
        $regions = get_option('arlo_regions');	
        
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');
            
        // attempt to find event template offer
        $conditions = array(
            'event_template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id'],
            'discounts' => false
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
            $fromtext = '<span class="arlo-from-text">' . __('From', $GLOBALS['arlo_plugin_slug']) . '</span> ';
        }
        
        return $fromtext . $offer->$price_field_show;        
    }

    private static function shortcode_no_event_text($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (!empty($GLOBALS['no_event_text'])) {
            return '<span class="arlo-no-results">' . $GLOBALS['no_event_text'] . '</span>';
        }        
    }

    private static function shortcode_event_next_running($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']) || empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return;
        $return = "";

        $arlo_location = isset($_GET['arlo-location']) && !empty($_GET['arlo-location']) ? $_GET['arlo-location'] : get_query_var('arlo-location', '');
        $arlo_delivery = isset($_GET['arlo-delivery']) && !empty($_GET['arlo-delivery']) ? $_GET['arlo-delivery'] : get_query_var('arlo-delivery', '');
        
        if (!empty($GLOBALS['arlo_eventtemplate']['et_region'])) {
            $arlo_region = $GLOBALS['arlo_eventtemplate']['et_region'];
        } else {
            $regions = get_option('arlo_regions');
            $arlo_region = get_query_var('arlo-region', '');
            $arlo_region = (!empty($arlo_region) && Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');
        }
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'buttonclass' => '',
            'dateclass' => '',
            'format' => 'd M y',
            'layout' => '',
            'limit' => 1,
            'removeyear' => "true",
            'text' => '{%date%}'
        ), $atts, $shortcode_name, $import_id));
        
        if (strpos($format, '%') === false) {
            $format = DateFormatter::date_format_to_strftime_format($format);
        }
            
        $removeyear = ($removeyear == "false" || $removeyear == "0" ? false : true);
        
        $conditions = array(
            'template_id' => $GLOBALS['arlo_eventtemplate']['et_arlo_id'],
            'date' => 'e.e_startdatetime > NOW()',
            'parent_id' => 'e.e_parent_arlo_id = 0',
        );
        
        $oaconditions = array(
            'template_id' => $GLOBALS['arlo_eventtemplate']['et_arlo_id'],
        );	
        
        if (!empty($arlo_region)) {
            $conditions['region'] = 'e.e_region = "' . $arlo_region . '"';
            $oaconditions['region'] = 'oa.oa_region = "' . $arlo_region . '"';
        }

        if (!empty($arlo_location)) {
            $conditions['location'] = "e.e_locationname = '" . urldecode($arlo_location) . "'";
        }

        if(!empty($arlo_delivery) && is_numeric($arlo_delivery)) {
            $conditions['delivery'] .= "e.e_isonline = " . intval($arlo_delivery);
        }
        
        $events = \Arlo\Entities\Events::get($conditions, array('e.e_startdatetime ASC'), $limit, $import_id);
        $oa = \Arlo\Entities\OnlineActivities::get($oaconditions, null, 1, $import_id);
        
        if ($layout == "list") {
            $return = '<ul class="arlo-event-next-running">';
        }
        
        if(count($events) == 0 && count($oa) == 0 && !empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi'])) {
            $return = '<a href="' . $GLOBALS['arlo_eventtemplate']['et_registerinteresturi'] . '" title="' . __('Register interest', $GLOBALS['arlo_plugin_slug']) . '" class="' . esc_attr($buttonclass) . '">' . __('Register interest', $GLOBALS['arlo_plugin_slug']) . '</a>';
        } else if (count($events)) {
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
                    
                    $date = strftime($format, strtotime($event->e_startdatetime));

                    $display_text = str_replace(['{%date%}', '{%location%}'], [$date, $location], $text);
                    
                    if ($event->e_registeruri && !$event->e_isfull) {
                        $return_links[] = ($layout == 'list' ? "<li>" : "") . '<a href="' . $event->e_registeruri . '" class="' . esc_attr($dateclass) . ' arlo-register">' . $display_text  . '</a>' . ($layout == 'list' ? "</li>" : "");
                    } else {
                        $return_links[] = ($layout == 'list' ? "<li>" : "") . '<span class="' . esc_attr($dateclass) . '">' . $display_text . '</span>' . ($layout == 'list' ? "</li>" : "");
                    }
                }	
            }	
            
            $return .= implode(($layout == 'list' ? "" : ", "), $return_links);
        } else if (count($oa)) {
            $reference_terms = json_decode($oa->oa_reference_terms, true);
            
            if (is_array($reference_terms) && isset($reference_terms['Plural']))
                $return .= '<a href="' . $oa->oa_registeruri . '" class="arlo-register">' . $reference_terms['Plural'] . '</a>';
        }
        
        if ($layout == "list") {
            $return .= '</ul>';
        }
            
        return $return;        
    }
    

    private static function event_date_formatter($atts, $date, $offset, $is_online = false) {
        $timezone = null;
              
        $timewithtz = str_replace(' ', 'T', $date) . $offset;
        
        $date = new \DateTime($timewithtz);
            
        if($is_online) {
            if (!empty($GLOBALS['selected_timezone_olson_names']) && is_array($GLOBALS['selected_timezone_olson_names'])) {
                foreach ($GLOBALS['selected_timezone_olson_names'] as $TzName) {
                    try {
                        $timezone = new \DateTimeZone($TzName->olson_name);
                    } catch (Exception $e) {}
                    
                    if ($timezone !== null) {
                        break;
                    }
                }
                
                if (!is_null($timezone)) {
                    $date->setTimezone($timezone);
                }   
            }
        }

        $format = 'D g:i A';

        if(isset($atts['format'])) $format = $atts['format'];
            
        //if we haven't got timezone, we need to append the timezone abbrev
        if ($is_online && is_null($timezone) && (preg_match('[G|g|i]', $format) === 1)) {
            $format .= " T";
        }
        
        if (strpos($format, '%') === false) {
            $format = DateFormatter::date_format_to_strftime_format($format);
        }	

        return strftime($format, $date->getTimestamp() + $date->getOffset());
    }  
}