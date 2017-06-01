<?php
namespace Arlo\Shortcodes;

use Arlo\Entities\Categories as CategoriesEntity;

class OnlineActivitiesList {
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

    private static function shortcode_all_oa_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;
        
        $templates = arlo_get_option('templates');
        $content = $templates['onlineactivities']['html'];
        return do_shortcode($content);        
    }

    private static function shortcode_all_oa_list_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        
        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));

        $sql = self::generate_list_sql($atts, $import_id, true);        

        $items = $wpdb->get_results($sql, ARRAY_A);
            
        $num = $wpdb->num_rows;

        return arlo_pagination($num,$limit);        
    }  


    private static function shortcode_all_oa_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        $settings = get_option('arlo_settings');

        $sql = self::generate_list_sql($atts, $import_id);

        $items = $wpdb->get_results($sql, ARRAY_A);
                  
        if(empty($items)) :
        
            $no_event_text = !empty($settings['noevent_text']) ? $settings['noevent_text'] : __('No events to show', 'arlo-for-wordpress');
            $output = '<p class="arlo-no-results">' . $no_event_text . '</p>';
            
        else :
            foreach($items as $item) {
                $GLOBALS['arlo_oa_list_item'] = $item;
                $GLOBALS['arlo_eventtemplate'] = $item;

                $output .= do_shortcode($content);

                unset($GLOBALS['arlo_oa_list_item']);
                unset($GLOBALS['arlo_eventtemplate']);
            }

        endif;

        return $output;
    }


    private static function generate_list_sql($atts, $import_id, $for_pagination = false) {
        global $wpdb;

        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && \Arlo\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : ''); 

        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $page = !empty($_GET['paged']) ? intval($_GET['paged']) : intval(get_query_var('paged'));

        $offset = ($page > 0) ? $page * $limit - $limit: 0 ;

        $output = '';

        $t1 = "{$wpdb->prefix}arlo_onlineactivities";
        $t2 = "{$wpdb->prefix}arlo_eventtemplates";
        $t3 = "{$wpdb->prefix}arlo_eventtemplates_categories";
        $t4 = "{$wpdb->prefix}arlo_onlineactivities_tags";

        if (!empty($arlo_region)) {
            $where .= '" AND ' . $t1 . '.oa_region = "' . $arlo_region . '"';
        }

        $arlo_category = \Arlo\Utilities::clean_int_url_parameter('arlo-category');
        $arlo_tag = \Arlo\Utilities::clean_int_url_parameter('arlo-oatag');

        if(!empty($arlo_category)) :
            $join .= " LEFT JOIN $t3 et_category ON et_category.et_arlo_id = oa.oat_arlo_id";

            $where .= " AND et_category.c_arlo_id = %d";
            $parameters[] = $arlo_category;
        endif;

        if(!empty($arlo_tag)) :
            $join .= " LEFT JOIN $t4 oa_tag ON oa_tag.oa_id = oa.oa_id";

            $where .= " AND oa_tag.tag_id = %d";
            $parameters[] = $arlo_tag;
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
                et.et_viewuri
            ';

            $limit_field = "
            LIMIT 
                $offset, $limit";
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
            $join
            WHERE 
                oa.import_id = ". $import_id ."
            $where
            $limit_field
            ";
        
        return $wpdb->prepare($sql, $parameters);
    }  


    private static function shortcode_all_oa_filters($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;

        extract(shortcode_atts(array(
            'filters'   => 'category,oatag',
            'resettext' => __('Reset', 'arlo-for-wordpress'),
            'buttonclass'   => 'button'
        ), $atts, $shortcode_name, $import_id));

        $filters_array = explode(',',$filters);
        
        $settings = get_option('arlo_settings');

        $page_link = get_permalink(get_post($post));
            
        $filter_html = '<form class="arlo-filters" method="get" action="' . $page_link . '">';
            
        foreach($filters_array as $filter) :

            switch($filter) :

                case 'category' :
                    $cats = CategoriesEntity::getTree(0, 1, 0, $import_id);
                    
                    if (!empty($cats)) {
                        $cats = CategoriesEntity::getTree($cats[0]->c_arlo_id, 100, 0, $import_id);
                    }

                    if (is_array($cats)) {
                        $filter_html .= Shortcodes::create_filter($filter, CategoriesEntity::child_categories($cats), __('All categories', 'arlo-for-wordpress'));                  
                    }

                    break;

                case 'oatag' :
                    $items = $wpdb->get_results(
                        "SELECT DISTINCT
                            t.id,
                            t.tag
                        FROM 
                            {$wpdb->prefix}arlo_onlineactivities_tags AS oatag
                        LEFT JOIN 
                            {$wpdb->prefix}arlo_tags AS t
                        ON
                            t.id = oatag.tag_id
                        AND
                            t.import_id = oatag.import_id
                        WHERE 
                            oatag.import_id = $import_id
                        ORDER BY tag", ARRAY_A);

                    $tags = array();

                    foreach ($items as $item) {
                        $tags[] = array(
                            'string' => $item['tag'],
                            'value' => $item['id'] . '-' . $item['tag'],
                        );
                    }

                    $filter_html .= Shortcodes::create_filter($filter, $tags, __('Select tag', 'arlo-for-wordpress'));              

                    break;

            endswitch;
        endforeach;

        $filter_html .= '<div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' .  $page_link . '"> ';    
        $filter_html .= '<a href="' . $page_link . '" class="' . esc_attr($buttonclass) . '">' . htmlentities($resettext, ENT_QUOTES, "UTF-8") . '</a></div>';

        $filter_html .= '</form>';
        
        return $filter_html;
    }
}