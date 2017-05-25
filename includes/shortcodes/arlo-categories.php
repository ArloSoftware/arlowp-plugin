<?php
namespace Arlo\Shortcodes;

use Arlo\Entities\Categories as CategoriesEntity;

class Categories {
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

    private static function shortcode_categories($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        $return = '';
        
        $arlo_category = \Arlo\Utilities::clean_string_url_parameter('arlo-category');
        
        // calculate depth
        $depth = (isset($atts['depth'])) ? (int)$atts['depth'] : 1;

        // show title?
        $title = (isset($atts['title'])) ? $atts['title'] : null;
        
        // show counts
        $counts = (isset($atts['counts'])) ? $atts['counts'] : null;
                    
        // start at
        $start_at = (isset($atts['parent'])) ? (int)$atts['parent'] : 0;
        if(!isset($atts['parent']) && $start_at == 0 && !empty($arlo_category)) {
            $slug = $arlo_category;
            $start_at = intval(current(explode('-', $slug)));
        }
        
        if($title) {
            $conditions = array('id' => $start_at);
            
            if($start_at == 0) {
                $conditions = array('parent_id' => 0);
            }
            
            $current = CategoriesEntity::get($conditions, 1, $import_id);
            
            $return .= sprintf($title, esc_html($current->c_name));
        }
        
        if ($depth > 0) {
            if ($start_at == 0) {
                $root = CategoriesEntity::getTree($start_at, 1, 0, $import_id);
                        
                if (!empty($root)) {
                    $start_at = $root[0]->c_arlo_id;
                }
            }
            
            $tree = CategoriesEntity::getTree($start_at, $depth, 0, $import_id);	
            
            $GLOBALS['categories_count'] = count($tree);		
                    
            if(!empty($tree)) {		
                $return .= self::generate_category_ul($tree, $counts);	
            }	
        }
        
        return $return;
    }

    private static function shortcode_category_title($content = '', $atts, $shortcode_name, $import_id = '') {
        $arlo_category = \Arlo\Utilities::clean_string_url_parameter('arlo-category');
        
        if (!empty($arlo_category)) {
            $category = CategoriesEntity::get(array('id' => current(explode('-', $arlo_category))), 1, $import_id);
        } else {
            $category = CategoriesEntity::get(array('parent_id' => 0), 1, $import_id);
        }
        
        if(!$category) return;
        
        return htmlentities($category->c_name, ENT_QUOTES, "UTF-8");
    }

    private static function shortcode_category_header($content = '', $atts, $shortcode_name, $import_id = '') {
        $arlo_category = \Arlo\Utilities::clean_string_url_parameter('arlo-category');
        
        if (!empty($arlo_category)) {
            $category = CategoriesEntity::get(array('id' => current(explode('-', $arlo_category))), 1, $import_id);
        } else {
            $category = CategoriesEntity::get(array('parent_id' => 0), 1, $import_id);
        }
        
        if(!$category) return;
        
        return $category->c_header;
    } 

    private static function shortcode_category_footer ($content = '', $atts, $shortcode_name, $import_id = ''){
        $arlo_category = \Arlo\Utilities::clean_string_url_parameter('arlo-category');
        
        if (!empty($arlo_category)) {
            $category = CategoriesEntity::get(array('id' => current(explode('-', $arlo_category))), 1, $import_id);
        } else {
            $category = CategoriesEntity::get(array('parent_id' => 0), 1, $import_id);
        }
        
        if(!$category) return;
        
        return $category->c_footer;
    }

    // category list
    private static function generate_category_ul($items, $counts) {
        $post_types = arlo_get_option('post_types');
        $events_url = get_page_link($post_types['event']['posts_page']);
        
        if(!is_array($items) || empty($items)) return '';
        
        $regions = get_option('arlo_regions');	
        $arlo_region = get_query_var('arlo-region', '');
        $arlo_region = (!empty($arlo_region) && \Arlo\Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');	
        
        $html = '<ul class="arlo-category-list">';
        
        foreach($items as $cat) {
            $html .= '<li>';
            $html .= '<a href="';
            $html .= $events_url . (!empty($arlo_region) ? 'region-' . $arlo_region . '/' : '');
            
            if($cat->c_parent_id != 0) {
                $html .= 'cat-' . esc_attr($cat->c_slug);
            }
            
            $html .= '">';
            $html .= htmlentities($cat->c_name, ENT_QUOTES, "UTF-8") . ( !is_null($counts) ?  sprintf($counts, $cat->c_template_num) : '' );
            $html .= '</a>';
            if(isset($cat->children)) {
                $html .= self::generate_category_ul($cat->children, $counts);
            }
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        return $html;
    }   
}