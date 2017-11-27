<?php
namespace Arlo\Shortcodes;

use Arlo\Entities\Categories as CategoriesEntity;

class OnlineActivities extends Filters {
    public static $oa_list_atts = [];

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


        $custom_shortcodes = Shortcodes::get_custom_shortcodes('oa');

        foreach ($custom_shortcodes as $shortcode_name => $shortcode) {
            Shortcodes::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id) {
                return self::shortcode_onlineactivites_list($content = '', $atts, $shortcode_name, $import_id);
            });
        }

    }

    private static function shortcode_oa_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;
        $settings = get_option('arlo_settings');

        $where = '';
               
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();

        $t1 = "{$wpdb->prefix}arlo_eventtemplates";
        $t2 = "{$wpdb->prefix}arlo_onlineactivities";
        $t6 = "{$wpdb->prefix}arlo_offers";
        
        if (!empty($arlo_region)) {
            $where .= ' AND ' . $t1 . '.et_region = "' . esc_sql($arlo_region) . '" AND ' . $t2 . '.oa_region = "' . esc_sql($arlo_region) . '"';
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
                $t1.et_post_id = $post->ID
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
            $registration .= '<a class="' . $class . ' arlo-register" href="'. esc_url($registeruri) . '" target="_blank">' . __($registermessage, 'arlo-for-wordpress') . '</a>';
        } else {
            $registration .= $registermessage;
        }
        
        $registration .= '</div>';

        return $registration;        
    } 

    private static function shortcode_oa_offers ($content = '', $atts, $shortcode_name, $import_id = ''){
        return Shortcodes::advertised_offers($GLOBALS['arlo_oa_list_item']['oa_id'], 'oa_id', $import_id);
    }

    private static function get_oa_atts($atts) {
        $new_atts = [];
        
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'category', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'oatag', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'templatetag', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo_For_Wordpress::get_region_parameter', 'region');

        return $new_atts;
    }

    private static function shortcode_onlineactivites_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;

        $template_name = Shortcodes::get_template_name($shortcode_name,'onlineactivites_list','oa');

        self::$oa_list_atts = self::get_oa_atts($atts);

        $templates = arlo_get_option('templates');
        $content = $templates[$template_name]['html'];
        return do_shortcode($content);        
    }

    private static function shortcode_onlineactivites_list_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        
        $atts['limit'] = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));

        $atts = array_merge($atts,self::$oa_list_atts);

        $sql = self::generate_onlineactivites_list_sql($atts, $import_id, true);        

        $items = $wpdb->get_results($sql, ARRAY_A);
            
        $num = $wpdb->num_rows;

        return arlo_pagination($num,$atts['limit']);        
    }  

    private static function shortcode_onlineactivites_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;

        if (!empty($atts['limit'])) {
            self::$oa_list_atts['limit'] = $atts['limit'];
        }

        $settings = get_option('arlo_settings');

        if (empty($atts)) {
            $atts = [];
        }

        $atts = array_merge($atts, self::$oa_list_atts);

        $sql = self::generate_onlineactivites_list_sql($atts, $import_id);

        $items = $wpdb->get_results($sql, ARRAY_A);

        $output = '';

        if (empty($atts)) {
            $atts = [];
        }

        $atts = array_merge($atts, self::$oa_list_atts);

        if(empty($items)) :
            $no_event_text = !empty($settings['noevent_text']) ? $settings['noevent_text'] : __('No online activities to show', 'arlo-for-wordpress');
            $output = '<p class="arlo-no-results">' . esc_html($no_event_text) . '</p>';
            
        else :
            $previous = null;

            $snippet_list_items = array();

            foreach($items as $key => $item) {
                if(isset($atts['group'])) {

                    switch($atts['group']) {
                        case 'category':
                            if(is_null($previous) || $item['c_arlo_id'] != $previous['c_arlo_id']) {
                                $item['show_divider'] = $item['c_name'];
                            }
                        break;
                        case 'alpha':
                            if(is_null($previous) || strtolower(mb_substr($item['oa_name'], 0, 1)) != strtolower(mb_substr($previous['oa_name'], 0, 1))) {
                                $item['show_divider'] = mb_substr($item['oa_name'], 0, 1);
                            }
                        break;
                    }



                }

                $GLOBALS['arlo_eventtemplate'] = $item;
                $GLOBALS['arlo_oa_list_item'] = $item;
                
                $output .= do_shortcode($content);

                $list_item_snippet = array();
                $list_item_snippet['@type'] = 'ListItem';
                $list_item_snippet['position'] = $key + 1;
                $list_item_snippet['url'] = $item['et_viewuri'];

                array_push($snippet_list_items,$list_item_snippet);

                unset($GLOBALS['arlo_eventtemplate']);
                unset($GLOBALS['arlo_oa_list_item']);
                
                $previous = $item;
            }

            $item_list = array();
            $item_list['@type'] = 'ItemList';
            $item_list['itemListElement'] = $snippet_list_items;

            $output .= Shortcodes::create_rich_snippet( json_encode($item_list) );     

        endif;

        return $output;
    }


    private static function generate_onlineactivites_list_sql($atts, $import_id, $for_pagination = false) {
        global $wpdb;

        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        
        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $page = !empty($_GET['paged']) ? intval($_GET['paged']) : intval(get_query_var('paged'));

        $offset = ($page > 0) ? $page * $limit - $limit: 0 ;

        $output = '';

        $join = '';
        $where = '';
        $parameters = array();

        $t1 = "{$wpdb->prefix}arlo_onlineactivities";
        $t2 = "{$wpdb->prefix}arlo_eventtemplates";
        $t3 = "{$wpdb->prefix}arlo_eventtemplates_categories";
        $t4 = "{$wpdb->prefix}arlo_onlineactivities_tags";
        $t5 = "{$wpdb->prefix}arlo_categories";
        $t6 = "{$wpdb->prefix}arlo_eventtemplates_tags";

        $where .= " oa.import_id = %d ";
        $parameters[] = $import_id;

        if (!empty($arlo_region)) {
            $where .= ' AND oa_region = %s AND et_region = %s';
            $parameters[] = $arlo_region;
            $parameters[] = $arlo_region;
        }       

        $arlo_category = !empty($atts['category']) ? $atts['category'] : null;
        $arlo_oatag = !empty($atts['oatag']) ? $atts['oatag'] : null;
        $arlo_templatetag = isset($atts['templatetag']) ? $atts['templatetag'] : null;

        if(!empty($arlo_category)) :
            $where .= " AND etc.c_arlo_id = %d";
            $parameters[] = $arlo_category;
        endif;

        if(!empty($arlo_oatag)) :
            $join .= " LEFT JOIN $t4 oa_tag ON oa_tag.oa_id = oa.oa_id AND oa_tag.import_id = oa.import_id";

            $where .= " AND oa_tag.tag_id = %d";
            $parameters[] = $arlo_oatag;
        endif;

        if(!empty($arlo_templatetag)) :            
            $join .= " LEFT JOIN $t6 ett ON ett.et_id = et.et_id AND ett.import_id = et.import_id";

            $where .= " AND ett.tag_id = %d";
            
            $parameters[] = $arlo_templatetag;
        endif;

        $field_list = '
            DISTINCT oa.oa_id
        ';

        $limit_field = $order = '';

        if (!$for_pagination) {
            $field_list = '
                oa.oa_id,
                oa.oa_arlo_id,
                oa.oat_arlo_id,
                oa.oa_code,
                oa.oa_reference_terms,
                oa.oa_credits,
                oa.oa_name,
                oa.oa_delivery_description,
                oa.oa_viewuri,
                oa.oa_registermessage,
                oa.oa_registeruri,
                et.et_id,
                et.et_name, 
                et.et_post_name, 
                et.et_post_id,
                et.et_descriptionsummary, 
                et.et_registerinteresturi, 
                et.et_region,
                et.et_viewuri,
                c.c_arlo_id,
                c.c_name
            ';

            $limit_field = "
            LIMIT 
                $offset, $limit";

            //ordering
            $order = "ORDER BY et.et_name ASC";
            
            // if grouping is set...
            if(isset($atts['group'])) {
                switch($atts['group']) {
                    case 'category':
                        $order = "ORDER BY c.c_order ASC, etc.et_order ASC, c.c_name ASC, et.et_name ASC";
                    break;
                }
            }
        }

        $sql = 
            "SELECT 
            $field_list
            FROM 
                $t1 oa
            LEFT JOIN 
                $t2 et 
            ON 
                oa.oat_arlo_id = et.et_arlo_id 
            AND
                et.import_id = oa.import_id
            LEFT JOIN 
                $t3 etc
            ON 
                oa.oat_arlo_id = etc.et_arlo_id 
            AND 
                oa.import_id = etc.import_id
            LEFT JOIN 
                $t5 c
            ON 
                c.c_arlo_id = etc.c_arlo_id
            AND
                c.import_id = etc.import_id
            $join
            WHERE
            $where
            $order
            $limit_field
            ";

        return $wpdb->prepare($sql, $parameters);
    }  

    private static function shortcode_onlineactivites_filters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;

        extract(shortcode_atts(array(
            'filters'   => 'category',
            'resettext' => __('Reset', 'arlo-for-wordpress'),
            'buttonclass'   => 'button'
        ), $atts, $shortcode_name, $import_id));

        $filters_array = explode(',',$filters);

        $settings = get_option('arlo_settings');
        
        $page_type = \Arlo_For_Wordpress::get_current_page_arlo_type();

        if (!empty($settings['post_types']['oa']['posts_page'])) {
            $page_link = get_permalink(get_post($settings['post_types'][$page_type]['posts_page']));
        } else {
            $page_link = get_permalink(get_post($post));
        }        

        $filter_html = '';

        $filter_group = 'oa';

        foreach($filters_array as $filter_key):
            $att = strval(self::$oa_list_atts[$filter_key]);

            if (!array_key_exists($filter_key, \Arlo_For_Wordpress::$available_filters[$filter_group]['filters']))
                continue;

            $items = self::get_filter_options($filter_key, $import_id);
            
            $filter_html .= Shortcodes::create_filter($filter_key, $items, __(\Arlo_For_Wordpress::$filter_labels[$filter_key], 'arlo-for-wordpress'),$filter_group,$att);
        endforeach;

        if (!empty($filter_html)) {
            return '
            <form class="arlo-filters" method="get" action="' . $page_link . '">
                ' . $filter_html . '
                <div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' .  $page_link . '">
                    <a href="' . $page_link . '" class="' . esc_attr($buttonclass) . '">' . htmlentities($resettext, ENT_QUOTES, "UTF-8") . '</a>
                </div>
            </form>
            ';
        }
    }

    private static function shortcode_oa_rich_snippet($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $oa_snippet = self::get_snippet_data($atts,$shortcode_name,$import_id);
        return Shortcodes::create_rich_snippet( json_encode($oa_snippet) );
    }

    private static function get_snippet_data($atts,$shortcode_name,$import_id) {
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));
        
        $settings = get_option('arlo_settings');  

        $oa_link = '';
        switch ($link) {
            case 'viewuri': 
                $oa_link = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_oa_list_item'],'oa_viewuri');
            break;  
            default:
                if ( array_key_exists('et_post_name',$GLOBALS['arlo_oa_list_item']) ) {
                    $oa_link = Shortcodes::get_template_permalink($GLOBALS['arlo_oa_list_item']['et_post_name'], 
                        $GLOBALS['arlo_oa_list_item']['et_region']);
                }
            break;
        }
        
        $oa_link = \Arlo\Utilities::get_absolute_url($oa_link);

        $oa_snippet = array();

        // Basic
        $oa_snippet['@context'] = 'http://schema.org';
        $oa_snippet['@type'] = 'OnDemandEvent';
        $oa_snippet['name'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_oa_list_item'],'oa_name');

        $oa_snippet['url'] = $oa_link;

        if (!empty($GLOBALS['arlo_oa_list_item']['et_descriptionsummary'])) {
            $oa_snippet['description'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_oa_list_item'],'et_descriptionsummary');
        }


        // OFfers
        $price_setting = (isset($settings['price_setting'])) ? $settings['price_setting'] : ARLO_PLUGIN_PREFIX . '-exclgst';
        $price_field = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? 'o_offeramounttaxexclusive' : 'o_offeramounttaxinclusive';
        $offers = Shortcodes::get_offers_snippet_data($GLOBALS['arlo_oa_list_item']['oa_id'], 'oa_id', $import_id, $price_field);

        if (!empty($offers)) {
            $oa_snippet["offers"] = array();
            $oa_snippet["offers"]["@type"] = "AggregateOffer";

            $oa_snippet["offers"]["highPrice"] = $offers['high_price'];
            $oa_snippet["offers"]["lowPrice"] = $offers['low_price'];

            $oa_snippet["offers"]["price"] = $offers['low_price'];

            $oa_snippet["offers"]["priceCurrency"] = $offers['currency'];

            $oa_snippet["offers"]['url'] = $oa_link;
        }

        return $oa_snippet;
    }

}