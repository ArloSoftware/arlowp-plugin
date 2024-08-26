<?php
namespace Arlo\Shortcodes;

use Arlo\Entities\Categories as CategoriesEntity;
use Arlo\Entities\Presenters as PresentersEntity;

class Templates {
    public static $event_template_atts = [];

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


        $custom_shortcodes = Shortcodes::get_custom_shortcodes(array('events','schedule','eventsearch'));

        foreach ($custom_shortcodes as $shortcode_name => $shortcode) {
            switch($shortcode["type"]) {
                case 'schedule':
                    Shortcodes::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id) {
                        if (!is_array($atts) && empty($atts)) { $atts = []; }
                        return self::shortcode_schedule($content = '', $atts, $shortcode_name, $import_id);
                    });
                    break;
                case 'eventsearch':
                    Shortcodes::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id) {
                        if (!is_array($atts) && empty($atts)) { $atts = []; }
                        return self::shortcode_event_template_search_list($content = '', $atts, $shortcode_name, $import_id);
                    });
                    break;
                default:
                    Shortcodes::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id) {
                        if (!is_array($atts) && empty($atts)) { $atts = []; }
                        return self::shortcode_event_template_list($content = '', $atts, $shortcode_name, $import_id);
                    });
                    break;            
            }
        }
    }
    
    private static function shortcode_suggest_templates($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb, $wp_query;
        if (empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return '';

        $settings = get_option('arlo_settings');  
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();

        $join = [];

        extract(shortcode_atts(array(
            'limit'	=> 5,
            'base' => 'category',
            'tagprefix'	=> 'group_',
            'onlyscheduled' => 'false',
            'regionalized' => 'false'
        ), $atts, $shortcode_name, $import_id));
        
        switch ($base) {
            case 'tag': 
                //select the tag_id associated with the template and starts with the prefix
                
                $where = "
                t.tag_id IN (SELECT 
                                ett.tag_id
                            FROM 
                                {$wpdb->prefix}arlo_eventtemplates_tags AS ett
                            LEFT JOIN 
                                {$wpdb->prefix}arlo_tags AS t
                            ON
                                ett.tag_id = t.id AND t.import_id = " . $import_id . "
                            WHERE
                                t.tag LIKE '" . esc_sql($tagprefix) . "%'
                            AND
                                ett.import_id = " . $import_id . "
                            AND
                                ett.et_id = {$GLOBALS['arlo_eventtemplate']['et_id']}
                            )
                ";
                
                $join['t'] = "		
                LEFT JOIN 
                    {$wpdb->prefix}arlo_eventtemplates_tags AS t
                ON
                    t.et_id = et.et_id
                AND
                    t.import_id = et.import_id
                ";
            break;
            default:
                //select the categories associated with the template
                $where = "
                c.c_arlo_id IN (SELECT 
                                ecc.c_arlo_id
                            FROM 
                                {$wpdb->prefix}arlo_eventtemplates_categories AS ecc
                            WHERE
                                ecc.import_id = " . $import_id . "
                            AND
                                ecc.et_arlo_id = {$GLOBALS['arlo_eventtemplate']['et_arlo_id']}
                            )
                ";		
            
                $join['c'] = "
                LEFT JOIN 
                    {$wpdb->prefix}arlo_eventtemplates_categories AS c
                ON
                    et.et_arlo_id = c.et_arlo_id
                AND
                    c.import_id = et.import_id
                ";			
            break;
        }
            
        if ($onlyscheduled === "true") {
            $join['e'] = "
            INNER JOIN 
                {$wpdb->prefix}arlo_events AS e
            ON
                e.et_arlo_id = et.et_arlo_id
            AND
                et.import_id = e.import_id
            ";
        } 
        
        if (!empty($arlo_region) && $regionalized === "true") {
            $where .= ' AND et.et_region = "' . esc_sql($arlo_region) . '"';
        }	
        
        $sql = "
            SELECT 
                et.et_id,
                et.et_region,
                et.et_arlo_id,
                et.et_code,
                et.et_name,
                et.et_descriptionsummary,
                et.et_post_name,
                et.et_post_id,
                et.et_registerinteresturi 
            FROM 
                {$wpdb->prefix}arlo_eventtemplates AS et
            " . implode("\n", $join) ."
            WHERE 
                et.import_id = " . $import_id . "
            AND
                et.et_arlo_id != {$GLOBALS['arlo_eventtemplate']['et_arlo_id']}
            AND
                {$where}
            GROUP BY
                et.et_arlo_id
            ORDER BY 
                RAND()
            LIMIT 
                $limit";

                
        $items = $wpdb->get_results($sql, ARRAY_A);
            
        $output = '';
        if(!empty($items)) :
            foreach($items as $item) {
                $GLOBALS['arlo_eventtemplate'] = $item;
                $output .= do_shortcode($content);
                unset($GLOBALS['arlo_eventtemplate']);
            }
        endif;

        return $output;
    }

    private static function shortcode_content_field_name($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_content_field_item']['cf_fieldname'])) return '';

        return htmlentities($GLOBALS['arlo_content_field_item']['cf_fieldname'], ENT_QUOTES, "UTF-8");        
    }
    

    private static function shortcode_content_field_text($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
	    if(!isset($GLOBALS['arlo_content_field_item']['cf_text'])) return '';

    	return wpautop($GLOBALS['arlo_content_field_item']['cf_text']);
    }

    private static function shortcode_content_field_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();

        extract(shortcode_atts(array(
            'fields'	=> 'all',
        ), $atts, $shortcode_name, $import_id));
        
        $where_fields = null;
        
        if (strtolower($fields) != 'all') {
            $where_fields = explode(',', $fields);
            $where_fields = array_map(function($field) {
                return '"' . trim(esc_sql($field)) . '"';
            }, $where_fields);
        }
        
        $t1 = "{$wpdb->prefix}arlo_eventtemplates";
        $t2 = "{$wpdb->prefix}arlo_contentfields";	

        if (!empty($GLOBALS['arlo_event_list_item']['et_id'])) {
            $where = $t1 . ".et_id = " . $GLOBALS['arlo_event_list_item']['et_id'];
        } else {
            $where = $t1 . ".et_post_id = " . $post->ID;
        }
                
        $sql = "
        SELECT 
            $t2.cf_fieldname, 
            $t2.cf_text 
        FROM 
            $t1 
        INNER JOIN 
            $t2
        ON 
            $t1.et_id = $t2.et_id
        " . (!empty($arlo_region) ? " AND $t1.et_region = '" . esc_sql($arlo_region) . "'" : "" ) . "
        WHERE 
            " . $where . "
            " . (is_array($where_fields) && count($where_fields) > 0 ? " AND cf_fieldname IN (" . implode(',', $where_fields) . ") " : "") . "
        AND 
            $t1.import_id = $import_id
        AND
            $t2.import_id = $import_id
        ORDER BY 
            $t2.cf_order";
                    
        $items = $wpdb->get_results($sql, ARRAY_A);

        $output = '';

        foreach($items as $item) {

            $GLOBALS['arlo_content_field_item'] = $item;

            $output .= do_shortcode($content);

            unset($GLOBALS['arlo_content_field_item']);

        }

        return $output;
    }

    private static function shortcode_event_template_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        
        $template_name = Shortcodes::get_template_name($shortcode_name,'event_template_list','events');

	    return self::template_list_initializer($content, $atts, $shortcode_name, $import_id, $template_name);
    }

    private static function shortcode_schedule($content = '', $atts = [], $shortcode_name = '', $import_id = '') {

        $template_name = Shortcodes::get_template_name($shortcode_name,'schedule','schedule');

        return self::template_list_initializer($content, $atts, $shortcode_name, $import_id, $template_name);
    }

    private static function template_list_initializer($content = '', $atts = [], $shortcode_name = '', $import_id = '', $template_name) {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;

        $filter_settings = get_option('arlo_page_filter_settings', []);        
        
        $templates = arlo_get_option('templates');
        $content = $templates[$template_name]['html'];

        self::$event_template_atts = self::get_event_template_atts($atts, $import_id);

        \Arlo\Utilities::set_base_filter($template_name, 'category', $filter_settings, $atts, self::$event_template_atts, '\Arlo\Utilities::convert_string_to_int_array');
        \Arlo\Utilities::set_base_filter($template_name, 'category', $filter_settings, $atts, self::$event_template_atts, '\Arlo\Utilities::convert_string_to_int_array', null, true);
        
        \Arlo\Utilities::set_base_filter($template_name, 'templatetag', $filter_settings, $atts, self::$event_template_atts, '\Arlo\Entities\Tags::get_tag_ids_by_tag', [$import_id]);
        \Arlo\Utilities::set_base_filter($template_name, 'templatetag', $filter_settings, $atts, self::$event_template_atts, '\Arlo\Entities\Tags::get_tag_ids_by_tag', [$import_id], true);

        \Arlo\Utilities::set_base_filter($template_name, 'delivery', $filter_settings, $atts, self::$event_template_atts, '\Arlo\Utilities::convert_string_to_int_array');
        \Arlo\Utilities::set_base_filter($template_name, 'delivery', $filter_settings, $atts, self::$event_template_atts, '\Arlo\Utilities::convert_string_to_int_array', null, true);

        \Arlo\Utilities::set_base_filter($template_name, 'location', $filter_settings, $atts, self::$event_template_atts, '\Arlo\Utilities::convert_string_to_string_array');
        \Arlo\Utilities::set_base_filter($template_name, 'location', $filter_settings, $atts, self::$event_template_atts, '\Arlo\Utilities::convert_string_to_string_array', null, true);        

        return $content;        
    }

    private static function shortcode_event_template_search_list ($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;

        $templates = arlo_get_option('templates');
        $content = $templates['eventsearch']['html'];

        self::$event_template_atts = self::get_event_template_atts($atts, $import_id);

        return $content;
    }

    private static function get_event_template_atts($atts, $import_id) {
        $new_atts = [];

        $templatetag = \Arlo\Entities\Tags::get_tag_ids_by_tag(\Arlo\Utilities::get_att_string('templatetag', $atts), $import_id);        

        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'location', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'locationhidden', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'venue', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'category', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'categoryhidden', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'search', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_int', 'delivery', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_int', 'deliveryhidden', $atts);        
        $new_atts = \Arlo\Utilities::process_att($new_atts, null, 'templatetag', $atts, $templatetag);        
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo\Utilities::get_att_string', 'state', $atts);
        $new_atts = \Arlo\Utilities::process_att($new_atts, '\Arlo_For_Wordpress::get_region_parameter', 'region');

        return $new_atts;
    }

    private static function shortcode_event_template_list_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;

        if (isset($GLOBALS['show_only_at_bottom']) && $GLOBALS['show_only_at_bottom']) return;

        $atts['limit'] = intval(isset(self::$event_template_atts['limit']) ? self::$event_template_atts['limit'] : (isset($atts['limit']) && is_numeric($atts['limit']) ? $atts['limit'] : get_option('posts_per_page')));

        $atts = array_merge($atts,self::$event_template_atts);

        $sql = self::generate_list_sql($atts, $import_id, true);

        $items = $wpdb->get_results($sql, ARRAY_A);

        $num = $wpdb->num_rows;

        return arlo_pagination($num, $atts['limit']);        
    }

    private static function shortcode_schedule_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;

        if (isset($GLOBALS['show_only_at_bottom']) && $GLOBALS['show_only_at_bottom']) return;

        $atts['limit'] = intval(isset(self::$event_template_atts['limit']) ? self::$event_template_atts['limit'] : (isset($atts['limit']) && is_numeric($atts['limit']) ? $atts['limit'] : get_option('posts_per_page')));
        
        $atts = array_merge($atts, self::$event_template_atts);
        
        $sql = self::generate_list_sql($atts, $import_id, true);

        $items = $wpdb->get_results($sql, ARRAY_A);

        $num = $wpdb->num_rows;

        return arlo_pagination($num, $atts['limit']);        
    }


    private static function shortcode_event_template_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;

        if (!empty($atts['limit'])) {
            self::$event_template_atts['limit'] = $atts['limit'];
        }

        $settings = get_option('arlo_settings');  

        $output = '';

        if (empty($atts)) {
            $atts = [];
        }

        $atts = array_merge($atts, self::$event_template_atts);

        $sql = self::generate_list_sql($atts, $import_id);

        $items = $wpdb->get_results($sql, ARRAY_A);

        if(empty($items)) :
            if (!(isset($atts['show_only_at_bottom']) && $atts['show_only_at_bottom'] == "true" && isset($GLOBALS['arlo_categories_count']) && $GLOBALS['arlo_categories_count'])) :
                $GLOBALS['no_event_text'] = !empty($settings['noevent_text']) ? $settings['noevent_text'] : __('No events to show', 'arlo-for-wordpress');
            endif;
        else :
                
            $output = $GLOBALS['no_event_text'] = '';			
            
            $previous = null;

            $snippet_list_items = array();

            foreach($items as $key => $item) {
                if(isset($atts['group'])) {
                    switch($atts['group']) {
                        case 'category':
                            if(is_null($previous) || $item['c_id'] != $previous['c_id']) {
                                $item['show_divider'] = $item['c_name'];
                            }
                        break;
                        case 'alpha':
                            if(is_null($previous) || strtolower(mb_substr($item['et_name'], 0, 1)) != strtolower(mb_substr($previous['et_name'], 0, 1))) {
                                $item['show_divider'] = mb_substr($item['et_name'], 0, 1);
                            }
                        break;
                    }
                }
                
                $GLOBALS['arlo_eventtemplate'] = $item;
                $GLOBALS['arlo_event_list_item'] = $item;

                $output .= do_shortcode($content);

                $list_item_snippet = array();
                $list_item_snippet['@type'] = 'ListItem';
                $list_item_snippet['position'] = $key + 1;
                $list_item_snippet['url'] = $item['et_viewuri'];

                array_push($snippet_list_items,$list_item_snippet);

                unset($GLOBALS['arlo_eventtemplate']);
                unset($GLOBALS['arlo_event_list_item']);

                $previous = $item;
            }

            $item_list = array();
            $item_list['@type'] = 'ItemList';
            $item_list['itemListElement'] = $snippet_list_items;

            $output .= Shortcodes::create_rich_snippet( json_encode($item_list) );

        endif;

        return $output;        
    }

    private static function shortcode_event_template_tags($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return '';
        
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
                {$wpdb->prefix}arlo_eventtemplates_tags AS ett 
            ON
                tag_id = id
            WHERE
                ett.et_id = {$GLOBALS['arlo_eventtemplate']['et_id']}
            AND	
                t.import_id = " . $import_id . "
            AND
                ett.import_id = " . $import_id . "
            ", ARRAY_A);	
        
        foreach ($items as $t) {
            $tags[] = $t['tag'];
        }
        
        if (count($tags)) {
            switch($layout) {
                case 'list':
                    $output = '<ul class="arlo-template_tags-list">';
                    
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
                    $output = '<div class="arlo-template_tags-list">' . implode(', ', array_map(function($tag) { return htmlentities($tag, ENT_QUOTES, "UTF-8"); }, $tags)) . '</div>';
                break;
            }	
        }
        
        return $output;
    }

    private static function shortcode_event_template_register_interest($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $settings = get_option('arlo_settings');
        
        $output = '';
        
        if (!empty($GLOBALS['no_event']) && !empty($GLOBALS['no_onlineactivity'])) {
            $no_event_text = '';

            if (!empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi'])) {
                $no_event_text = !empty($settings['noeventontemplate_text']) ? $settings['noeventontemplate_text'] : __('Interested in attending? Have a suggestion about running this event near you?', 'arlo-for-wordpress');
                $no_event_text .= '<br /><a href="' . esc_url($GLOBALS['arlo_eventtemplate']['et_registerinteresturi']) . '">' . __('Register your interest now', 'arlo-for-wordpress') . '</a>';
            } else {
                $no_event_text = (!empty($settings['noevent_text']) ? $settings['noevent_text'] : __('No events to show', 'arlo-for-wordpress'));
            }
            
            $output = '<p class="arlo-no-results">' . $no_event_text . '</p>';	
        }

        return $output;
    }

    private static function shortcode_event_template_register_private_interest($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['no_event']) || empty($GLOBALS['arlo_eventtemplate']['et_registerprivateinteresturi'])) return;

        // merge and extract attributes
        extract(shortcode_atts(array(
            'text' => __('Want to run this event in-house? %s Enquire about running this event in-house %s', 'arlo-for-wordpress')
        ), $atts, $shortcode_name, $import_id));

        $link = Shortcodes::build_custom_link($text, $GLOBALS['arlo_eventtemplate']['et_registerprivateinteresturi'], 'arlo-register-private-interest-link');

        $output = '<p class="arlo-register-private-interest">' . $link . '</p>';

        return $output;
    }

    private static function shortcode_event_template_code($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']['et_code'])) return '';
        
        return htmlentities($GLOBALS['arlo_eventtemplate']['et_code'], ENT_QUOTES, "UTF-8");        
    }

    private static function shortcode_event_template_name($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']['et_name'])) return '';

        return htmlentities($GLOBALS['arlo_eventtemplate']['et_name'], ENT_QUOTES, "UTF-8");        
    }

    private static function shortcode_event_template_permalink($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        return Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);
    }

    private static function shortcode_event_template_link($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']['et_viewuri'])) return '';

        return htmlentities($GLOBALS['arlo_eventtemplate']['et_viewuri'], ENT_QUOTES, "UTF-8");        
    }

    private static function shortcode_event_template_summary($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']['et_descriptionsummary'])) return '';

        return esc_html($GLOBALS['arlo_eventtemplate']['et_descriptionsummary']);
    }

    private static function shortcode_event_template_advertised_duration($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_eventtemplate']['et_advertised_duration'])) return '';

        return esc_html($GLOBALS['arlo_eventtemplate']['et_advertised_duration']);
    }

    private static function shortcode_event_template_advertised_price($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        if (empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return;

        extract(shortcode_atts(array(
            'showfrom' => 'true',
            'showtaxsuffix' => 'true'
        ), $atts, $shortcode_name, $import_id));

        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        
        $sql = self::generate_bestoffer_list_sql($GLOBALS['arlo_eventtemplate']['et_arlo_id'], $arlo_region, 2, $import_id);

        $offers = $wpdb->get_results($sql, OBJECT);
        if (empty($offers)) return;
        $offer = $offers[0];
        if (empty($offer)) return;

        //price setting and free text
        $settings = get_option('arlo_settings');
        $free_text = (isset($settings['free_text'])) ? $settings['free_text'] : __('Free', 'arlo-for-wordpress');
        $exclgst = (isset($settings['price_setting']) && $settings['price_setting'] === ARLO_PLUGIN_PREFIX . '-exclgst');

        if ($offer->o_offeramounttaxexclusive == 0) {
            return esc_html($free_text);
        }

        $fromtext = '';
        if (strtolower($showfrom) === "true" && count($offers) > 1) {
            $fromtext = __('From', 'arlo-for-wordpress') . ' ';
        }

        $taxsuffix = '';
        if (strtolower($showtaxsuffix) === "true" && !$offer->e_is_taxexempt) {
            if ($exclgst) {
                $taxsuffix = ' ' . sprintf(__('excl. %s', 'arlo-for-wordpress'), $offer->o_taxrateshortcode);
            } else {
                $taxsuffix = ' ' . sprintf(__('incl. %s', 'arlo-for-wordpress'), $offer->o_taxrateshortcode);
            }
        }

        $formattedprice = ($exclgst || $offer->e_is_taxexempt ? $offer->o_formattedamounttaxexclusive : $offer->o_formattedamounttaxinclusive);

        return esc_html($fromtext . $formattedprice . $taxsuffix);
    }

    private static function shortcode_event_template_advertised_presenters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return;

        $presenters = PresentersEntity::get(['template_id' => $GLOBALS['arlo_eventtemplate']['et_arlo_id']], null, null, $import_id);
        if (empty($presenters)) return;

        $presenters_fullnames = array_map(function($presenter) {
            return $presenter['p_firstname'] . ' ' . $presenter['p_lastname'];
        }, $presenters);

        //one presenter occurrence per region (p_viewuri) - we would need a region on presenter too
        $presenters_fullnames = array_unique($presenters_fullnames);

        $output = implode(', ', $presenters_fullnames);
        return esc_html($output);
    }
    
    private static function shortcode_event_template_credits($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_eventtemplate']['et_credits'])) return;

        // merge and extract attributes
        extract(shortcode_atts(array(
            'layout' => 'list',
            'text' => '{%label%}: {%points%}'
        ), $atts, $shortcode_name, $import_id));

        $credits = json_decode($GLOBALS['arlo_eventtemplate']['et_credits']);
        if (empty($credits) || !is_array($credits)) return;

        $output = '';
        switch ($layout) {
            default:
                $output .= '<ul class="arlo-event-template-credits">';
                foreach ($credits as $credit) {
                    if (!empty($credit->Type) && !empty($credit->Value)) {
                        $credit_type = esc_html($credit->Type);
                        $credit_value = esc_html($credit->Value);
                        $html = '<li>' . $text . '</li>';
                        $html = str_replace('{%label%}', $credit_type, $html);
                        $html = str_replace('{%points%}', $credit_value, $html);
                        $output .= $html;
                    }
                }
                $output .= '</ul>';
            break;
        }
        return $output;
    }

    private static function shortcode_event_template_filters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        return self::generate_template_filters_form($atts, $shortcode_name, $import_id, 'event');
    }

    private static function shortcode_schedule_filters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        return self::generate_template_filters_form($atts, $shortcode_name, $import_id, 'schedule');
    }

    private static function generate_template_filters_form($atts, $shortcode_name, $import_id, $default_page = 'event') {
        global $post, $wpdb;

        extract(shortcode_atts(array(
            'filters'   => 'category,location,delivery',
            'resettext' => __('Reset', 'arlo-for-wordpress'),
            'buttonclass'   => 'button'
        ), $atts, $shortcode_name, $import_id));
        
        $filters_array = explode(',',$filters);
        
        $settings = get_option('arlo_settings');

        $page_type = \Arlo_For_Wordpress::get_current_page_arlo_type($default_page);        
        $filter_group = $page_type == 'event' ? 'events' : $page_type;

        if (!empty($settings['post_types'][$page_type]['posts_page'])) {
            $page_link = get_permalink(get_post($settings['post_types'][$page_type]['posts_page']));
        } else {
            $page_link = get_permalink(get_post($post));
        }

        $filter_html = '';
        
        $atts = is_array($atts) ? $atts : [];
        $atts = array_merge($atts, self::$event_template_atts);

        foreach(\Arlo_For_Wordpress::$available_filters[$page_type == 'schedule' ? 'schedule' : 'template']['filters'] as $filter_key => $filter):

            $att = (isset(self::$event_template_atts[$filter_key]) && is_string(self::$event_template_atts[$filter_key]) ? self::$event_template_atts[$filter_key] : '');

            if (!in_array($filter_key, $filters_array))
                continue;

            $items = Filters::get_filter_options($filter_key, $import_id);

            $filter_html .= Shortcodes::create_filter($filter_key, $items, __(\Arlo_For_Wordpress::$filter_labels[$filter_key], 'arlo-for-wordpress'), 'generic', $att, $filter_group);

        endforeach; 
            
        // category select
        if (!empty($filter_html)) {
            return '
            <form id="arlo-event-filter" class="arlo-filters" method="get" action="'. $page_link .'">
                ' . $filter_html .'
                <div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' . $page_link . '">
                    <a href="' . $page_link . '" class="' . esc_attr($buttonclass) . '">' . htmlentities($resettext, ENT_QUOTES, "UTF-8") . '</a>
                </div>
            </form>
            ';
        }
    }

    private static function shortcode_suggest_datelocation($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post;
        if (!empty($GLOBALS['no_event']) || empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi']) || $post->post_type != 'arlo_event') return;

        // merge and extract attributes
        extract(shortcode_atts(array(
            'text' => __('None of these dates work for you? %s Suggest another date & time %s', 'arlo-for-wordpress')
        ), $atts, $shortcode_name, $import_id));

        $link = Shortcodes::build_custom_link($text, $GLOBALS['arlo_eventtemplate']['et_registerinteresturi'], 'arlo-register-interest');

        return $link;
    }

    private static function shortcode_suggest_private_datelocation($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (!empty($GLOBALS['no_event']) || empty($GLOBALS['arlo_eventtemplate']['et_registerprivateinteresturi'])) return;

        // merge and extract attributes
        extract(shortcode_atts(array(
            'text' => __('Want to run this event in-house? %s Enquire about running this event in-house %s', 'arlo-for-wordpress')
        ), $atts, $shortcode_name, $import_id));

        $link = Shortcodes::build_custom_link($text, $GLOBALS['arlo_eventtemplate']['et_registerprivateinteresturi'], 'arlo-suggest-private-datelocation-link');

        $output = '<p class="arlo-suggest-private-datelocation">' . $link . '</p>';

        return $output;
    }

    private static function shortcode_template_region_selector ($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        return Shortcodes::create_region_selector("event");
    }

    private static function shortcode_template_search_region_selector ($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        return Shortcodes::create_region_selector("eventsearch");
    }

    private static function shortcode_event_template_rich_snippet($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $et_snippet = self::get_rich_snippet_data($atts,$import_id,$shortcode_name);
        return Shortcodes::create_rich_snippet( json_encode($et_snippet) );
    }

    private static function get_rich_snippet_data($atts,$import_id,$shortcode_name) {
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $event_template_snippet = array();

        if (isset($GLOBALS["arlo_eventtemplate"])) {
            $event_template_snippet['@context'] = 'http://schema.org';
            $event_template_snippet['@type'] = 'Course';
            $event_template_snippet['name'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_eventtemplate'],'et_name');

            $et_link = '';
            switch ($link) {
                case 'viewuri': 
                    $et_link = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_eventtemplate'],'et_viewuri');
                break;  
                default: 
                    $et_link = Shortcodes::get_template_permalink($GLOBALS['arlo_eventtemplate']['et_post_name'], $GLOBALS['arlo_eventtemplate']['et_region']);
                break;
            }

            $et_link = \Arlo\Utilities::get_absolute_url($et_link);

            $event_template_snippet['url'] = $et_link;

            $event_template_snippet['description'] = Shortcodes::get_rich_snippet_field($GLOBALS['arlo_eventtemplate'],'et_descriptionsummary');

            return $event_template_snippet;
        } else {
            return '';
        }
    }

    private static function shortcode_event_template_hero_image($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_eventtemplate']['et_hero_image'])) return;

        $url = $GLOBALS['arlo_eventtemplate']['et_hero_image'];
        $filename = basename($url);
        return '<img src="' . esc_attr($url) . '" alt="' . esc_attr($filename) . '">';
    }

    private static function shortcode_event_template_list_image($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (empty($GLOBALS['arlo_eventtemplate']['et_list_image'])) return;

        $url = $GLOBALS['arlo_eventtemplate']['et_list_image'];
        $filename = basename($url);
        return '<img src="' . esc_attr($url) . '" alt="' . esc_attr($filename) . '">';
    }


    private static function generate_list_sql($atts, $import_id, $for_pagination = false) {
        global $wpdb;
       
        if (isset($atts['show_only_at_bottom']) && $atts['show_only_at_bottom'] == "true" && isset($GLOBALS['arlo_categories_count']) && $GLOBALS['arlo_categories_count']) {
            $GLOBALS['show_only_at_bottom'] = true;
            return;
        } 

        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $page = arlo_current_page();
        $offset = ($page - 1) * $limit;

        $parameters = [];
        $additional_fields = [];

        $t1 = "{$wpdb->prefix}arlo_eventtemplates";
        $t2 = "{$wpdb->prefix}posts";
        $t3 = "{$wpdb->prefix}arlo_eventtemplates_categories";
        $t4 = "{$wpdb->prefix}arlo_categories";
        $t5 = "{$wpdb->prefix}arlo_events";
        $t6 = "{$wpdb->prefix}arlo_eventtemplates_tags";
        $t7 = "{$wpdb->prefix}arlo_tags";
        $t8 = "{$wpdb->prefix}arlo_onlineactivities";
            
        $where = "WHERE post.post_type = 'arlo_event' AND et.import_id = %d";
        $parameters[] = $import_id;

        $join = [];
        $field_list = "";
        $group = "";
        $order = "";
        $limit_field = "";

        $arlo_location = !empty($atts['location']) ? $atts['location'] : null;
        $arlo_locationhidden = !empty($atts['locationhidden']) ? $atts['locationhidden'] : null;
        $arlo_venue = !empty($atts['venue']) ? $atts['venue'] : null;
        $arlo_state = !empty($atts['state']) ? $atts['state'] : null;
        $arlo_category = !empty($atts['category']) ? $atts['category'] : null;
        $arlo_categoryhidden = !empty($atts['categoryhidden']) ? $atts['categoryhidden'] : null;        
        $arlo_delivery = isset($atts['delivery']) ? $atts['delivery'] : null;
        $arlo_deliveryhidden = isset($atts['deliveryhidden']) ? $atts['deliveryhidden'] : null;        
        $arlo_templatetag = !empty($atts['templatetag']) ? $atts['templatetag'] : null;
        $arlo_templatetaghidden = isset($atts['templatetaghidden']) ? $atts['templatetaghidden'] : null;        
        $arlo_search = !empty($atts['search']) ? $atts['search'] : null;
        $arlo_region = !empty($atts['region']) ? $atts['region'] : null;

        if (isset($arlo_delivery) && !is_array($arlo_delivery) && strlen($arlo_delivery) && is_numeric($arlo_delivery)) {
            $arlo_delivery = [$arlo_delivery];
        }

        if (isset($arlo_deliveryhidden) && !is_array($arlo_deliveryhidden) && strlen($arlo_deliveryhidden) && is_numeric($arlo_deliveryhidden)) {
            $arlo_deliveryhidden = [$arlo_deliveryhidden];
        }

        if (!empty($arlo_location) && !is_array($arlo_location)) {
            $arlo_location = [$arlo_location];
        }

        if (!empty($arlo_locationhidden) && !is_array($arlo_locationhidden)) {
            $arlo_locationhidden = [$arlo_locationhidden];
        }

        if (!empty($arlo_location)) {
            $where .= " AND e.e_locationname IN (" . implode(',', array_map(function() {return "%s";}, $arlo_location)) . ")";                
            $parameters = array_merge($parameters, $arlo_location);    
        }

        if (!empty($arlo_locationhidden)) {    
            $where .= " AND e.e_locationname NOT IN (" . implode(',', array_map(function() {return "%s";}, $arlo_locationhidden)) . ")";                
            $parameters = array_merge($parameters, $arlo_locationhidden);    
        }

        if (!empty($arlo_venue)) {
            $arlo_venue = \Arlo\Utilities::convert_string_to_int_array($arlo_venue);
            if (!empty($arlo_venue)) {
                if (!is_array($arlo_venue)) { $arlo_venue = [$arlo_venue]; }
                $where .= " AND e.v_id IN (" . implode(',', array_map(function() {return "%s";}, $arlo_venue)) . ")";
                $parameters = array_merge($parameters, $arlo_venue);
            }
        }

        if(isset($arlo_delivery) || isset($arlo_deliveryhidden)) {
            if (isset($arlo_delivery)) {
                $where .= ' AND ( 1 ';
                foreach ($arlo_delivery as $delivery) {
                    switch ($delivery) {
                        case 0:
                        case 1: 
                            $where .=  " AND e.e_isonline = %d ";
                            $parameters[] = $delivery;    
                            $where .= " AND e.e_parent_arlo_id = 0 ";
                        break;
                        case 99: 
                            $join['oa'] = " LEFT JOIN $t8 AS oa ON oa.oat_arlo_id = et.et_arlo_id AND oa.import_id = et.import_id ";
                            $where .= (count($arlo_delivery) > 1 ? ' OR ' : ' AND ') . ' oa_id IS NOT NULL ';
                        break;        
                    } 
                }
                $where .= ' ) ';
                
            }

            if (isset($arlo_deliveryhidden)) {            
                $join['oa'] = " LEFT JOIN $t8 AS oa ON oa.oat_arlo_id = et.et_arlo_id AND oa.import_id = et.import_id ";
                foreach ($arlo_deliveryhidden as $delivery) {
                    switch ($delivery) {
                        case 0:
                        case 1: 
                            $where .= " AND ( (e.e_parent_arlo_id = 0 AND e.e_isonline != %d ) OR oa_id IS NOT NULL )";
                            $parameters[] = $delivery;
                        break;
                        case 99: 
                            $where .= " AND e.e_parent_arlo_id = 0 ";
                        break;        
                    } 
                }
            }
        } else {
            $where .= " AND (e.e_parent_arlo_id = 0 OR e.e_parent_arlo_id IS NULL) ";
        }

        if(!empty($arlo_state)) :                
            $join['ce']  = " LEFT JOIN $t5 ce ON e.e_arlo_id = ce.e_parent_arlo_id AND e.import_id = ce.import_id ";

            $venues = \Arlo\Entities\Venues::get(['state' => $arlo_state], null, null, $import_id);

            if(is_array($venues) && count($venues) > 1) {
                $venues = array_map(function ($venue) {
                    return $venue['v_arlo_id'];
                }, $venues);
                
                $GLOBALS['state_filter_venues'] = $venues;

                $ids_string = implode(',', array_map(function() {return "%d";}, $venues));
                $where .= " AND (ce.v_id IN (" . $ids_string . ") OR e.v_id IN (" . $ids_string . "))";

                $parameters = array_merge($parameters, $venues);
                $parameters = array_merge($parameters, $venues);
            } else {
                if (is_array($venues)) {
                    $venues = array_shift($venues);
                }

                $where .= " AND (ce.v_id = %d OR e.v_id = %d)";
                $parameters[] = $venues;
                $parameters[] = $venues;	
            }                

        endif;

        if(!empty($arlo_templatetag) || !empty($arlo_templatetaghidden)) :

            if (!empty($arlo_templatetag)) {
                $join['ett'] = " LEFT JOIN $t6 AS ett ON et.et_id = ett.et_id AND ett.import_id = et.import_id ";

                $where .= " AND ett.tag_id IN (" . implode(',', array_map(function() {return "%d";}, $arlo_templatetag)) . ")";
                $parameters = array_merge($parameters, $arlo_templatetag);    
            }
            
            if (!empty($arlo_templatetaghidden)) {
                $tag_id_substitutes = implode(', ', array_map(function() {return "%d";}, $arlo_templatetaghidden));
                $where .= " AND NOT EXISTS( SELECT tag_id FROM $t6 WHERE tag_id IN ($tag_id_substitutes) AND et.et_id = et_id AND import_id = et.import_id )";
                $parameters = array_merge($parameters, $arlo_templatetaghidden);
            }
        endif;

        if (!empty($arlo_search)) {
            $where .= '
            AND (
                    et_code like %s
                OR
                    et_name like %s
                OR 
                    et_descriptionsummary like %s
            )
            ';
            $parameters[] = '%' . $arlo_search . '%';

            // We want search to allow for missing words in titles. 
            // MySQL full text search functionality is not a guarantee on all hosts, even though it came out nearly 10 years ago. 
            // with % on each side of the query we are already in a poorly optimized query, so a few more for accuracy is not that bad.
            $words = explode(' ',  trim($arlo_search));
            if (count($words) < 4 && count($words) > 1){
                // Only do complex searches on 3 words or less to limit potential impact
                $parameters[] = '%' . implode('%', $words) . '%';
                // TODO In future it would be nice to score & sort these. See class-wp-query.php parse_search_order() for a WP core example.
            } else {
                $parameters[] = '%' . $arlo_search . '%';
            }
            
            
            $parameters[] = '%' . $arlo_search . '%';
            
            $atts['show_child_elements'] = "true";
        }	
        
        if (!empty($arlo_region)) {
            $where .= ' AND et.et_region = %s AND (e.e_region = et.et_region OR e.e_region IS NULL)';
            $parameters[] = $arlo_region;
        }		
        
        $GLOBALS['show_child_elements'] = false;
        if(!empty($arlo_category) || !empty($arlo_categoryhidden)) {
            $arlo_category = \Arlo\Utilities::convert_string_to_int_array($arlo_category);
            $arlo_categoryhidden = \Arlo\Utilities::convert_string_to_int_array($arlo_categoryhidden);

            $where .= ' AND (';

            if (!empty($arlo_category)) {
                $where .= " c.c_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $arlo_category)) . ")";                
                $parameters = array_merge($parameters, $arlo_category);    
            }

            if (!empty($arlo_categoryhidden)) {
                if (!empty($arlo_category))
                    $where .= " AND ";

                //need to exclude all the child categories
                $categoriesnot_flatten_list = CategoriesEntity::get_flattened_category_list_for_filter($arlo_categoryhidden, [], $import_id);
                
                if (count($categoriesnot_flatten_list)) {
                    $where .= " ( c.c_arlo_id NOT IN (" . implode(',', array_map(function() {return "%d";}, $categoriesnot_flatten_list)) . ") OR c.c_arlo_id IS NULL)";
                    $parameters = array_merge($parameters, array_map(function($cat) { return $cat['id']; }, $categoriesnot_flatten_list));
                } else {
                    $where .= "1 = 1";
                }
            }
            
            if ((isset($atts['show_child_elements']) && $atts['show_child_elements'] == "true") || (isset($GLOBALS['show_child_elements']) && $GLOBALS['show_child_elements'])) {
                $GLOBALS['show_child_elements'] = true;

                $categories_flatten_list = CategoriesEntity::get_flattened_category_list_for_filter($arlo_category, $arlo_categoryhidden, $import_id);
                    
                if (is_array($categories_flatten_list) && count($categories_flatten_list)) {
                    $where .= " OR c.c_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $categories_flatten_list)) . ")";
                    $parameters = array_merge($parameters, array_map(function($cat) { return $cat['id']; }, $categories_flatten_list));
                }
            } 
            
            $where .= ')';
        } else if (!(isset($atts['show_child_elements']) && $atts['show_child_elements'] == "true")) {
            $where .= ' AND (c.c_parent_id = (SELECT c_arlo_id FROM ' . $t4 . ' WHERE c_parent_id = 0 AND import_id = %d) OR c.c_parent_id IS NULL)';
            $parameters[] = $import_id;
        }

        //grouping
        $att_group = (empty($atts['group']) ? '' : $atts['group']);
        switch ($att_group) {
            case 'category':
                $group = 'GROUP BY c.c_arlo_id, et.et_arlo_id';
            break;
            default:
                $group = 'GROUP BY et.et_arlo_id';
            break;
        }

        if (!$for_pagination) {
            //ordering
            switch ($att_group) {
                case 'category':
                    $order = "ORDER BY c.c_order ASC, etc.et_order ASC, c.c_name ASC, et.et_name ASC";
                break;
                default:
                    $order = "ORDER BY et.et_name ASC";
                break;
            }
            
            $limit_field = " LIMIT $offset,$limit ";

            $field_list = "et.*, post.ID as post_id, etc.c_arlo_id, c.*, e.e_is_taxexempt" . ($additional_fields ? ' ,' . implode(' ,', $additional_fields) : '');
        } else {
            $field_list = "et.et_id";
        }

        $sql = "
        SELECT
            $field_list 
        FROM 
            $t1 et 
        " . implode("\n", $join) . "
        LEFT JOIN $t2 post 
            ON et.et_post_id = post.ID 
        LEFT JOIN $t3 etc
            ON etc.et_arlo_id = et.et_arlo_id AND etc.import_id = et.import_id
        LEFT JOIN $t4 c
            ON c.c_arlo_id = etc.c_arlo_id AND c.import_id = etc.import_id
        LEFT JOIN $t5 e
            ON e.et_arlo_id = et.et_arlo_id AND e.import_id = et.import_id
        $where 
        $group 
        $order
        $limit_field";

        $query = $wpdb->prepare($sql, $parameters);
        
        if ($query) {
            return $query;
        } else {
            throw new \Exception("Couldn't prepapre SQL statement");
        }
    }

    private static function generate_bestoffer_list_sql($template_id, $region, $limit, $import_id) {
        global $wpdb;

        $t1 = "{$wpdb->prefix}arlo_offers";
        $t2 = "{$wpdb->prefix}arlo_eventtemplates";
        $t3 = "{$wpdb->prefix}arlo_events";
        $t4 = "{$wpdb->prefix}arlo_onlineactivities";

        $parameters = [];

        $sql = "
        SELECT
            o.o_offeramounttaxexclusive,
            o.o_offeramounttaxinclusive,
            o.o_formattedamounttaxexclusive,
            o.o_formattedamounttaxinclusive,
            o.o_taxrateshortcode,
            e.e_is_taxexempt
        FROM 
            $t1 o
        LEFT JOIN $t2 et
            ON et.et_id = o.et_id AND et.import_id = o.import_id
        LEFT JOIN $t3 e
            ON e.e_id = o.e_id AND e.import_id = o.import_id
        LEFT JOIN $t4 oa
            ON oa.oa_id = o.oa_id AND e.import_id = o.import_id
        WHERE o.import_id = %d
            AND (et.et_arlo_id = %d OR e.et_arlo_id = %d OR oa.oat_arlo_id = %d)";

        $parameters[] = $import_id;
        $parameters[] = $template_id; //et
        $parameters[] = $template_id; //e
        $parameters[] = $template_id; //oa

        if ($region) {
            $sql .= " AND o.o_region = %s";
            $parameters[] = $region;
        }

        $sql .= "
        GROUP BY o.o_offeramounttaxexclusive
        ORDER BY o.o_offeramounttaxexclusive ASC
        LIMIT %d";

        $parameters[] = $limit;

        $query = $wpdb->prepare($sql, $parameters);
        return $query;
    }

}