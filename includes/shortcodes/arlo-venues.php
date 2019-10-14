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
                if (!is_array($atts) && empty($atts)) { $atts = []; }
                return self::$method_name($content, $atts, $shortcode_name, $import_id);
            });
        }

        $custom_shortcodes = Shortcodes::get_custom_shortcodes('venues');

        foreach ($custom_shortcodes as $shortcode_name => $shortcode) {
            Shortcodes::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id) {
                if (!is_array($atts) && empty($atts)) { $atts = []; }
                return self::shortcode_venue_list($content = '', $atts, $shortcode_name, $import_id);
            });
        }
    }

    private static function shortcode_venue_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;

        $template_name = Shortcodes::get_template_name($shortcode_name,'venue_list','venues');


        $templates = arlo_get_option('templates');
        $content = $templates[$template_name]['html'];
        return do_shortcode($content);    
    }
    
    private static function shortcode_venue_list_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;   
        
        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));

        $t1 = "{$wpdb->prefix}arlo_venues";
        $t2 = "{$wpdb->prefix}posts";

        $items = $wpdb->get_results(
            "SELECT 
                DISTINCT(v.v_arlo_id)
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
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $items = self::get_venues($atts,$import_id);

        $output = '';

        $snippet_list_items = array();

        foreach($items as $key => $item) {
            $GLOBALS['arlo_venue_list_item'] = $item;

            $list_item_snippet = array();
            $list_item_snippet['@type'] = 'ListItem';
            $list_item_snippet['position'] = $key + 1;
            $list_item_snippet['url'] = $item['v_viewuri'];

            array_push($snippet_list_items,$list_item_snippet);

            $output .= do_shortcode($content);

            unset($GLOBALS['arlo_venue_list_item']);
        }

        $item_list = array();
        $item_list['@type'] = 'ItemList';
        $item_list['itemListElement'] = $snippet_list_items;

        $output .= Shortcodes::create_rich_snippet( json_encode($item_list) );

        return $output;        
    }

    private static function get_venues($atts,$import_id) {
        global $wpdb;

        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $page = arlo_current_page();
        $offset = ($page - 1) * $limit;

        $t1 = "{$wpdb->prefix}arlo_venues";
        $t2 = "{$wpdb->prefix}posts";

        return $wpdb->get_results(
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
            GROUP BY
                v_arlo_id
            ORDER BY 
                v.v_name ASC
            LIMIT 
                $offset, $limit", ARRAY_A);
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
        if(!isset($GLOBALS['arlo_venue_list_item']['v_post_id'])) return '';

        return get_permalink($GLOBALS['arlo_venue_list_item']['v_post_id']);        
    }

    private static function get_map_query() {
        $array_fields = [
            'v_physicaladdressline1',
            'v_physicaladdressline2',
            'v_physicaladdressline3',
            'v_physicaladdressline4',
            'v_physicaladdresssuburb',
            'v_physicaladdresscity',
            'v_physicaladdressstate',
            'v_physicaladdresspostcode',
            'v_physicaladdresscountry'
        ];

        $query = [];

        foreach($array_fields as $field) {
            if (!empty($GLOBALS['arlo_venue_list_item'][$field])) {
                $query[] = urlencode($GLOBALS['arlo_venue_list_item'][$field]);
            }
        }
        return implode(',', $query);
    }
    
    private static function shortcode_venue_map($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $settings = get_option('arlo_settings');
        
        $api_key = (!empty($settings['googlemaps_api_key']) ? $settings['googlemaps_api_key'] : '');
        if (empty($api_key) && strtolower($settings['platform_name']) == \Arlo_For_Wordpress::DEFAULT_PLATFORM) {
            $api_key = \Arlo_For_Wordpress::GOOGLE_MAPS_API_KEY;
        }

        if (empty($api_key)) {
            return;
        }
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'height'    => 400,
            'width'     => 400,
            'zoom'      => 16,
            'type'      => 'dynamic'
        ), $atts, $shortcode_name, $import_id));

        $name = $GLOBALS['arlo_venue_list_item']['v_name'];
        $lat = $GLOBALS['arlo_venue_list_item']['v_geodatapointlatitude'];
        $long = $GLOBALS['arlo_venue_list_item']['v_geodatapointlongitude'];

        $query = self::get_map_query();

        if($lat != 0 || $long != 0) {
            if(intval($height) <= 0) $height = 400;
            if(intval($width) <= 0) $width = 400;

            switch ($type) {
                case 'static':
                    $map = '<img src="https://maps.googleapis.com/maps/api/staticmap?markers=color:green%7C';
                    $map .= $lat . ',' . $long;
                    $map .= '&size=' . $width . 'x' . $height;
                    $map .= '&zoom=' . $zoom;
                    $map .= '&key=' . $api_key . '"';
                    $map .= ' height="' . $height . '"';
                    $map .= ' width="' . $width . '"';
                    $map .= ' alt="' . esc_attr(sprintf(__('Map of %s', 'arlo-for-wordpress'), $name)) . '"'; 
                    $map .= ' />';
                break;

                default: 
                    $map = '<iframe src="https://www.google.com/maps/embed/v1/place?q=' ;
                    $map .= $query;
                    $map .= '&zoom=' . $zoom;
                    $map .= '&key=' . $api_key ;
                    $map .= '"';
                    $map .= ' height="' . $height . '"';
                    $map .= ' width="' . $width . '"';
                    $map .= ' frameborder="0" style="border:0"';

                    $map .= ' alt="' . esc_attr(sprintf(__('Map of %s', 'arlo-for-wordpress'), $name)) . '"'; 
                    $map .= ' allowfullscreen></iframe>';
                break;
            }

            return $map;
        }
    }
    
    private static function shortcode_venue_address($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (!isset($GLOBALS['arlo_venue_list_item'])) return;

        // merge and extract attributes
        extract(shortcode_atts(array(
            'layout' => 'list',
            'items' => 'line1,line2,line3,line4,suburb,city,state,post_code,country',
            'link' => null
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

        $address_link = null;
        switch($link) {
            case 'permalink':
                $address_link = self::shortcode_venue_permalink();
                break;
            case 'viewuri':
                $address_link = self::shortcode_venue_link();
                break;
            case 'map':
                $address_link = "https://www.google.com/maps/search/?api=1&query=".self::get_map_query();
                break;
            default:
                if ($link) {
                    $address_link = $link;
                }
                break;
        }

        $content = $address_link ? sprintf('<a href="%s" target="_blank">',esc_attr($address_link)) . $content : $content;
        
        switch($layout) {
            case 'list':
                $content .= '<ul class="arlo-address-list">';
                
                foreach($address as $line) {
                    $content .= '<li>' . $line . '</li>';
                }
                
                $content .= '</ul>';
            break;
        
            default:
                $locale = (array_key_exists('suburb',$address) ? $address['suburb'] . ', ' : '') .
                            (array_key_exists('city',$address) ? $address['city'] . ' ' : '') .
                            (array_key_exists('state',$address) ? $address['state'] . ' ' : '') .
                            (array_key_exists('post_code',$address) ? $address['post_code'] . ' ' : '');

                $country = array_key_exists('country',$address) ? $address['country'] : '';

                if (isset($address['suburb'])) {
                    unset($address['suburb']);
                }

                if (isset($address['city'])) {
                    unset($address['city']);
                }

                if (isset($address['state'])) {
                    unset($address['state']);
                }

                if (isset($address['post_code'])) {
                    unset($address['post_code']);
                }

                if (isset($address['country'])) {
                    unset($address['country']);
                }


                if (!empty($locale)) {
                    $address['locale'] = $locale;
                }

                if (!empty($country)) {
                    $address['country'] = $country;
                }

                $content .= count($address) > 1 ? implode('<br> ', $address) : implode('', $address);
            break;
        }

        $content = $address_link ? $content . '</a>' : $content;

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


    private static function shortcode_venue_rich_snippet($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $venue_snippet = self::get_venue_snippet($link);

        return Shortcodes::create_rich_snippet( json_encode($venue_snippet) ); 
    }

    /**
     * Output location name
     * @param  string $content
     * @param  array  $atts
     * @param  string $shortcode_name
     * @param  string $import_id
     * @return string                 Location name
     */
    private static function shortcode_venue_locationname($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_venue_list_item']['v_locationname'])) return '';

        return esc_html($GLOBALS['arlo_venue_list_item']['v_locationname']);
    }

    /**
     * Output links to event lists, filtered by venue
     * @param  string $content
     * @param  array  $atts
     * @param  string $shortcode_name
     * @param  string $import_id
     * @return string
     */
    private static function shortcode_venue_events_link($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_venue_list_item']['v_arlo_id'])){ return ''; }

        extract(shortcode_atts(array(
            'link_page' => 'upcoming'
        ), $atts, $shortcode_name, $import_id));

        // Only two pages currently supported
        if ($link_page != "upcoming" && $link_page != "schedule"){ return ''; }

        $settings = get_option('arlo_settings');
        $location_url = get_permalink($settings['post_types'][$link_page]['posts_page']);

        if (!empty($location_url)){
            $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
            $location_url .= (!empty($arlo_region) ? 'region-' . $arlo_region . '/' : '');

            $location_url .= "venue-" . urlencode($GLOBALS['arlo_venue_list_item']['v_arlo_id']) . '-' . urlencode($GLOBALS['arlo_venue_list_item']['v_name']) . '/';
            return esc_url($location_url);
        } else {
            return '';
        }
    }

    private static function get_venue_snippet($link) {
        $venue_snippet = array();

        if (!isset($GLOBALS['arlo_venue_list_item'])) return $venue_snippet;

        // Basic
        $venue_snippet = array();
        $venue_snippet["@type"] = "Place";
        $venue_snippet["name"] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_venue_list_item'],'v_name');

        $v_link = '';
        switch ($link) {
            case 'viewuri': 
                $v_link = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_venue_list_item'],'v_viewuri');
            break;  
            default: 
                $v_link = get_permalink(arlo_get_post_by_name(Shortcodes::get_rich_snippet_field($GLOBALS['arlo_venue_list_item'],'v_post_name'), 'arlo_venue'));
            break;
        }
        
        $v_link = \Arlo\Utilities::get_absolute_url($v_link);

        if (!empty($v_link)) {
            $venue_snippet["url"] = $v_link;
        }

        // Address
        $venue_snippet["address"] = array();
        $venue_snippet["address"]["@type"] = ["PostalAddress"];

        if (!empty($GLOBALS['arlo_venue_list_item']["v_physicaladdresscity"]) 
            || !empty($GLOBALS['arlo_venue_list_item']["v_physicaladdresspostcode"]) 
            || !empty($GLOBALS['arlo_venue_list_item']["v_physicaladdresscountry"]) 
            || !empty($GLOBALS['arlo_venue_list_item']["v_physicaladdressline1"])) {

                if (!empty($GLOBALS['arlo_venue_list_item']["v_physicaladdressline1"])) {
                    $address = $GLOBALS['arlo_venue_list_item']["v_physicaladdressline1"];

                    $address .= ( !empty($GLOBALS['arlo_venue_list_item']["v_physicaladdressline2"]) ? ' ' . $GLOBALS['arlo_venue_list_item']["v_physicaladdressline2"] : "");

                    $address .= ( !empty($GLOBALS['arlo_venue_list_item']["v_physicaladdressline3"]) ? ' ' . $GLOBALS['arlo_venue_list_item']["v_physicaladdressline3"] : "");

                    $address .= ( !empty($GLOBALS['arlo_venue_list_item']["v_physicaladdressline4"]) ? ' ' . $GLOBALS['arlo_venue_list_item']["v_physicaladdressline4"] : "");

                    $address .= ( !empty($GLOBALS['arlo_venue_list_item']["v_physicaladdresssuburb"]) ? ' ' . $GLOBALS['arlo_venue_list_item']["v_physicaladdresssuburb"] : "");

                    $venue_snippet["address"]["streetAddress"] = $address;
                }

                if (!empty($GLOBALS['arlo_venue_list_item']["v_physicaladdresscity"])) {
                    $venue_snippet["address"]["addressLocality"] = $GLOBALS['arlo_venue_list_item']["v_physicaladdresscity"];
                }

                if (!empty($GLOBALS['arlo_venue_list_item']["v_physicaladdresspostcode"])) {
                    $venue_snippet["address"]["postalCode"] = $GLOBALS['arlo_venue_list_item']["v_physicaladdresspostcode"];
                }

                if (!empty($GLOBALS['arlo_venue_list_item']["v_physicaladdresscountry"])) {
                    $venue_snippet["address"]["addressCountry"] = $GLOBALS['arlo_venue_list_item']["v_physicaladdresscountry"];
                }
        }

        // Geo coordinates
        $geolatitude = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_venue_list_item'],'v_geodatapointlatitude');
        $geolongitude = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_venue_list_item'],'v_geodatapointlongitude');

        if ( !empty($geolatitude) || !empty($geolongitude) ) {
            $venue_snippet["location"]["geo"] = array();
            $venue_snippet["location"]["geo"]["@type"] = "GeoCoordinates";
            $venue_snippet["location"]["geo"]["latitude"] = $geolatitude;
            $venue_snippet["location"]["geo"]["longitude"] = $geolongitude;
        }

        return $venue_snippet;
    }

}