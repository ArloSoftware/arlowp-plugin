<?php
namespace Arlo\Shortcodes;

class Venues {
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

    private static function shortcode_venue_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;
        
        $templates = arlo_get_option('templates');
        $content = $templates['venues']['html'];
        return do_shortcode($content);        
    }
    
    private static function shortcode_venue_list_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;   
        
        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));

        $t1 = "{$wpdb->prefix}arlo_venues";
        $t2 = "{$wpdb->prefix}posts";

        $items = $wpdb->get_results(
            "SELECT 
                v.v_id
            FROM 
                $t1 v 
            LEFT JOIN 
                $t2 post 
            ON 
                v.v_post_id = post.ID
            WHERE 
                post.post_type = 'arlo_venue'
            AND
                v.import_id = $import_id
            ORDER BY 
                v.v_name ASC", ARRAY_A);

        $num = $wpdb->num_rows;

        return arlo_pagination($num,$limit);        
    }
    
    private static function shortcode_venue_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
	
        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $offset = (get_query_var('paged') && intval(get_query_var('paged')) > 0) ? intval(get_query_var('paged')) * $limit - $limit: 0 ;

        $t1 = "{$wpdb->prefix}arlo_venues";
        $t2 = "{$wpdb->prefix}posts";

        $items = $wpdb->get_results(
            "SELECT 
                v.*, 
                post.ID as post_id
            FROM 
                $t1 v 
            LEFT JOIN 
                $t2 post 
            ON 
                v.v_post_id = post.ID
            WHERE 
                post.post_type = 'arlo_venue'
            AND
                v.import_id = $import_id
            ORDER BY 
                v.v_name ASC
            LIMIT 
                $offset, $limit", ARRAY_A);

        $output = '';

        foreach($items as $item) {

            $GLOBALS['arlo_venue_list_item'] = $item;

            $output .= do_shortcode($content);

            unset($GLOBALS['arlo_venue_list_item']);

        }

        return $output;        
    }
    
    private static function shortcode_venue_name($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_venue_list_item']['v_name'])) return '';

        return htmlentities($GLOBALS['arlo_venue_list_item']['v_name'], ENT_QUOTES, "UTF-8");        
    }

    private static function shortcode_venue_link($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_venue_list_item']['v_viewuri'])) return '';

        return htmlentities($GLOBALS['arlo_venue_list_item']['v_viewuri'], ENT_QUOTES, "UTF-8");        
    }    
    
    private static function shortcode_venue_permalink($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_venue_list_item']['post_id'])) return '';

        return get_permalink($GLOBALS['arlo_venue_list_item']['post_id']);        
    }
    
    private static function shortcode_venue_map($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $settings = get_option('arlo_settings');
        
        $api_key = $settings['googlemaps_api_key']; 
        if (empty($api_key)) {
            if (strtolower($settings['platform_name']) == \Arlo_For_Wordpress::DEFAULT_PLATFORM) {
                $api_key = \Arlo_For_Wordpress::GOOGLE_MAPS_API_KEY;
            } else {
                return;
            }
        }
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'height'	=> 400,
            'width'  	=> 400,
            'zoom'		=> 16
        ), $atts, $shortcode_name, $import_id));

        $name = $GLOBALS['arlo_venue_list_item']['v_name'];
        $lat = $GLOBALS['arlo_venue_list_item']['v_geodatapointlatitude'];
        $long = $GLOBALS['arlo_venue_list_item']['v_geodatapointlongitude'];

        if($lat != 0 || $long != 0) {

            if(intval($height) <= 0) $height = 400;
            if(intval($width) <= 0) $width = 400;

            $map = '<img src="https://maps.googleapis.com/maps/api/staticmap?markers=color:green%7C';
            $map .= $lat . ',' . $long;
            $map .= '&size=' . $width . 'x' . $height;
            $map .= '&zoom=' . $zoom;
            $map .= '&key=' . $api_key . '"';
            $map .= ' height="' . $height . '"';
            $map .= ' width="' . $width . '"';
            $map .= ' alt="' . esc_attr(sprintf(__('Map of %s', 'arlo-for-wordpress'), $name)) . '"'; 
            $map .= ' />';

            return $map;
        }        
    }
    
    private static function shortcode_venue_address($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        // merge and extract attributes
        extract(shortcode_atts(array(
            'layout' => 'list',
            'items' => 'line1,line2,line3,line4,suburb,city,state,post_code,country'
        ), $atts, $shortcode_name, $import_id));
        
        $items = str_replace(' ', '', $items);
        $items = explode(',', $items);
        
        //consrtuct array
        $address = array(
            'line1' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdressline1'], ENT_QUOTES, "UTF-8"),
            'line2' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdressline2'], ENT_QUOTES, "UTF-8"),
            'line3' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdressline3'], ENT_QUOTES, "UTF-8"),
            'line4' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdressline4'], ENT_QUOTES, "UTF-8"),
            'suburb' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdresssuburb'], ENT_QUOTES, "UTF-8"),
            'city' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdresscity'], ENT_QUOTES, "UTF-8"),
            'state' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdressstate'], ENT_QUOTES, "UTF-8"),
            'post_code' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdresspostcode'], ENT_QUOTES, "UTF-8"),
            'country' => htmlentities($GLOBALS['arlo_venue_list_item']['v_physicaladdresscountry'], ENT_QUOTES, "UTF-8"),
        );
        
        // check if we want to show all items
        foreach($address as $key => $value) {
            $value = trim($value);
            if(!in_array($key, $items) || empty($value)) {
                unset($address[$key]);
            }
        }
        
        switch($layout) {
            case 'list':
                $content = '<ul class="arlo-address-list">';
                
                foreach($address as $line) {
                    $content .= '<li>' . $line . '</li>';
                }
                
                $content .= '</ul>';
            break;
        
            default:
                $content = implode(', ', $address);
            break;
        }
        
        return $content;        
    }
    
    private static function shortcode_venue_directions($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_venue_list_item']['v_facilityinfodirections'])) return '';

        return $GLOBALS['arlo_venue_list_item']['v_facilityinfodirections'];        
    }

    private static function shortcode_venue_parking($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_venue_list_item']['v_facilityinfoparking'])) return '';

        return $GLOBALS['arlo_venue_list_item']['v_facilityinfoparking'];        
    }    
}