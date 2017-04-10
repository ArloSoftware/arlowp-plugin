<?php
namespace Arlo\Shortcodes;

class OnlineActivities {
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

        Shortcodes::add('oa_list', function($content = '', $atts, $shortcode_name, $import_id){
            return $content;
        });
    }

    private static function shortcode_oa_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;
        $settings = get_option('arlo_settings');
        $regions = get_option('arlo_regions');

        $where = '';
               
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && \Arlo\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');	
        
        $t1 = "{$wpdb->prefix}arlo_eventtemplates";
        $t2 = "{$wpdb->prefix}arlo_onlineactivities";
        $t6 = "{$wpdb->prefix}arlo_offers";
        
        if (!empty($arlo_region)) {
            $where .= ' AND ' . $t1 . '.et_region = "' . $arlo_region . '" AND ' . $t2 . '.oa_region = "' . $arlo_region . '"';
        }					
        
        $sql = 
            "SELECT 
                oa_id,
                oa_arlo_id,
                oat_arlo_id,
                oa_code,
                oa_reference_terms,
                oa_credits,
                oa_name,
                oa_delivery_description,
                oa_viewuri,
                oa_registermessage,
                oa_registeruri
            FROM 
                $t2
            LEFT JOIN 
                $t1
            ON 
                $t1.et_arlo_id = $t2.oat_arlo_id
            AND
                $t1.import_id = " . $import_id . "
            WHERE 
                $t1.et_post_name = $post->ID
            AND
                $t2.import_id = ". $import_id ."
            $where
            ";
        
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        $output = '';
        
        if (is_array($items) && count($items)) {
        
            unset($GLOBALS['no_onlineactivity']);
            
            foreach($items as $key => $item) {
        
                $GLOBALS['arlo_oa_list_item'] = $item;
                            
                $output .= do_shortcode($content);
        
                unset($GLOBALS['arlo_oa_list_item']);
            }	
        } else {
            $GLOBALS['no_onlineactivity'] = 1;
        }
        
        return $output;        
    }

    private static function shortcode_oa_code ($content = '', $atts, $shortcode_name, $import_id = '') {
        if(!isset($GLOBALS['arlo_oa_list_item']['oa_code'])) return '';

        return htmlentities($GLOBALS['arlo_oa_list_item']['oa_code'], ENT_QUOTES, "UTF-8");        
    }

    private static function shortcode_oa_name ($content = '', $atts, $shortcode_name, $import_id = '') {
        if(!isset($GLOBALS['arlo_oa_list_item']['oa_name'])) return '';

        return htmlentities($GLOBALS['arlo_oa_list_item']['oa_name'], ENT_QUOTES, "UTF-8");        
    } 

    private static function shortcode_oa_delivery_description ($content = '', $atts, $shortcode_name, $import_id = ''){
        if(!isset($GLOBALS['arlo_oa_list_item']['oa_delivery_description'])) return '';

        return htmlentities($GLOBALS['arlo_oa_list_item']['oa_delivery_description'], ENT_QUOTES, "UTF-8");        
    }

    private static function shortcode_oa_reference_term ($content = '', $atts, $shortcode_name, $import_id = ''){
        if(!isset($GLOBALS['arlo_oa_list_item']['oa_reference_terms'])) return '';
        
        $output = '';
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'type' => 'singular',
        ), $atts, $shortcode_name));
        
        $type = ucfirst(strtolower($type));
        
        $terms = json_decode($GLOBALS['arlo_oa_list_item']['oa_reference_terms']);
        
        if (!empty($terms->$type)) {
            $output = $terms->$type;
        }

        return $output;        
    }

    private static function shortcode_oa_credits ($content = '', $atts, $shortcode_name, $import_id = '') {
        if(!isset($GLOBALS['arlo_oa_list_item']['oa_credits'])) return '';
        $output = '';
        
        // merge and extract attributes
        extract(shortcode_atts(array(
            'layout' => 'list',
        ), $atts, $shortcode_name));
        
        $credits = json_decode($GLOBALS['arlo_oa_list_item']['oa_credits']);
        
        if (is_array($credits) && count($credits)) {
            switch($layout) {
                default:
                    $output .= '<ul class="arlo-oa-credits">';
                    foreach ($credits as $credit) {
                        $output .= '<li>' . htmlentities($credit->Type, ENT_QUOTES, "UTF-8") . ': ' . htmlentities($credit->Value, ENT_QUOTES, "UTF-8") . '</li>';
                    }
                    $output .= '</ul>';
                break;
            }	
        }	

        return $output;        
    }

    private static function shortcode_oa_registration ($content = '', $atts, $shortcode_name, $import_id = '') {
        $registeruri = $GLOBALS['arlo_oa_list_item']['oa_registeruri'];
        $registermessage = $GLOBALS['arlo_oa_list_item']['oa_registermessage'];
            
        $class = (!empty($atts['class']) ? $atts['class'] : 'button' );

        $registration = '<div class="arlo-oa-registration">';
        // test if there is a register uri string, if so display the button
        if(!is_null($registeruri) && $registeruri != '') {
            $registration .= '<a class="' . $class . ' arlo-register" href="'. esc_attr($registeruri) . '" target="_blank">' . __($registermessage, 'arlo-for-wordpress') . '</a>';
        } else {
            $registration .= $registermessage;
        }
        
        $registration .= '</div>';

        return $registration;        
    } 

    private static function shortcode_oa_offers ($content = '', $atts, $shortcode_name, $import_id = ''){
        return Shortcodes::advertised_offers($GLOBALS['arlo_oa_list_item']['oa_id'], 'oa_id', $import_id);
    }
}