<?php
namespace Arlo\Shortcodes;

class Presenters {
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

        $custom_shortcodes = Shortcodes::get_custom_shortcodes('presenters');

        foreach ($custom_shortcodes as $shortcode_name => $shortcode) {
            Shortcodes::add($shortcode_name, function($content = '', $atts, $shortcode_name, $import_id) {
                if (!is_array($atts) && empty($atts)) { $atts = []; }                
                return self::shortcode_presenter_list($content = '', $atts, $shortcode_name, $import_id);
            });
        }
    }

    private static function shortcode_presenter_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if (get_option('arlo_plugin_disabled', '0') == '1') return;

        $template_name = Shortcodes::get_template_name($shortcode_name,'presenter_list','presenters');

        $templates = arlo_get_option('templates');
        $content = $templates[$template_name]['html'];
        return do_shortcode($content);        
    }


    private static function shortcode_presenter_list_pagination($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        
        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));

        $t1 = "{$wpdb->prefix}arlo_presenters";
        $t2 = "{$wpdb->prefix}posts";

        $items = $wpdb->get_results(
            "SELECT 
                DISTINCT(p.p_arlo_id)
            FROM 
                $t1 p 
            LEFT JOIN 
                $t2 post 
            ON 
                p.p_post_id = post.ID
            WHERE 
                post.post_type = 'arlo_presenter'
            AND
                p.import_id = $import_id
            ORDER BY 
                p.p_lastname ASC", ARRAY_A);

        $num = $wpdb->num_rows;

        return arlo_pagination($num,$limit);        
    }

    private static function shortcode_presenter_list_item($content = '', $atts = [], $shortcode_name = '', $import_id = '') { 
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $items = self::get_presenters($atts,$import_id);

        $output = '';

        $snippet_list_items = array();

        foreach($items as $key => $item) {

            $GLOBALS['arlo_presenter_list_item'] = $item;

            $list_item_snippet = array();
            $list_item_snippet['@type'] = 'ListItem';
            $list_item_snippet['position'] = $key + 1;
            $list_item_snippet['url'] = $item['p_viewuri'];

            array_push($snippet_list_items,$list_item_snippet);

            $output .= do_shortcode($content);

            unset($GLOBALS['arlo_presenter_list_item']);
        }

        $item_list = array();
        $item_list['@type'] = 'ItemList';
        $item_list['itemListElement'] = $snippet_list_items;

        $output .= Shortcodes::create_rich_snippet( json_encode($item_list) );

        return $output;        
    }

    private static function get_presenters($atts, $import_id) {
        global $wpdb;

        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $page = arlo_current_page();
        $offset = ($page - 1) * $limit;

        $t1 = "{$wpdb->prefix}arlo_presenters";
        $t2 = "{$wpdb->prefix}posts";

        return $wpdb->get_results(
           "SELECT 
                p_arlo_id,
                p_id,
                p_firstname,
                p_lastname,
                p_viewuri,
                p_profile,
                p_qualifications,
                p_interests,
                p_twitterid,
                p_facebookid,
                p_linkedinid,
                p_post_name,
                p_post_id,
                post.ID as post_id
            FROM 
                $t1 p 
            LEFT JOIN 
                $t2 post 
            ON 
                p.p_post_id = post.ID
            WHERE 
                post.post_type = 'arlo_presenter'
            AND
                p.import_id = $import_id
            GROUP BY
                p_arlo_id                               
            ORDER 
                BY p.p_firstname ASC, p.p_lastname ASC
            LIMIT 
                $offset, $limit", ARRAY_A);
    }
    
    private static function shortcode_presenter_name($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_firstname']) && !isset($GLOBALS['arlo_presenter_list_item']['p_lastname'])) return '';

        $first_name = $GLOBALS['arlo_presenter_list_item']['p_firstname'];
        $last_name = $GLOBALS['arlo_presenter_list_item']['p_lastname'];

        return htmlentities($first_name . ' ' . $last_name, ENT_QUOTES, "UTF-8");
    }
    
    private static function shortcode_presenter_permalink($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
    	if(!isset($GLOBALS['arlo_presenter_list_item']['post_id'])) return '';

	    return get_permalink($GLOBALS['arlo_presenter_list_item']['post_id']);
    }
    
    private static function shortcode_presenter_link($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_viewuri'])) return '';

        return htmlentities($GLOBALS['arlo_presenter_list_item']['p_viewuri'], ENT_QUOTES, "UTF-8");        
    }
    
    private static function shortcode_presenter_profile($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_profile'])) return '';

        return $GLOBALS['arlo_presenter_list_item']['p_profile'];        
    }
    
    private static function shortcode_presenter_qualifications($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_qualifications'])) return '';

        return $GLOBALS['arlo_presenter_list_item']['p_qualifications'];        
    }
    
    private static function shortcode_presenter_interests($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_interests'])) return '';

        return $GLOBALS['arlo_presenter_list_item']['p_interests'];
    }

    private static function shortcode_presenter_rich_snippet($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $performer = !empty($GLOBALS['arlo_presenter_list_item']) ? Shortcodes::get_performer($GLOBALS['arlo_presenter_list_item'], $link) : "";

        return Shortcodes::create_rich_snippet( json_encode($performer) ); 
    }

    private static function shortcode_presenter_social_link($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        // merge and extract attributes
        extract(shortcode_atts(array(
            'network' => '',
            'linktext'=> ''
        ), $atts, $shortcode_name, $import_id));

        // tidy up network so we can use it
        $network = trim(strtolower($network));

        // if no valid platform is specified, return nothing
        if(is_null($network) || ($network != 'facebook' && $network != 'twitter' && $network != 'linkedin')) return '';

        // if the presenter has no social media
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_twitterid']) && !isset($GLOBALS['arlo_presenter_list_item']['p_facebookid']) && !isset($GLOBALS['arlo_presenter_list_item']['p_linkedinid'])) return '';

        $fb_link = 'https://facebook.com/';
        $li_link = 'https://www.linkedin.com/';
        $tw_link = 'https://twitter.com/';

        $fb_id = htmlentities($GLOBALS['arlo_presenter_list_item']['p_facebookid'], ENT_QUOTES, "UTF-8");
        $li_id = htmlentities($GLOBALS['arlo_presenter_list_item']['p_linkedinid'], ENT_QUOTES, "UTF-8");
        $tw_id = htmlentities($GLOBALS['arlo_presenter_list_item']['p_twitterid'], ENT_QUOTES, "UTF-8");

        $network = trim(strtolower($network));

        // if not link text is supplied, return raw url string
        if(is_null($linktext) || trim($linktext == '')) {

            switch($network) {
                case "facebook":
                    if(!$fb_id) return '';
                    $link = $fb_link . $fb_id;
                    break;
                case "linkedin":
                    if(!$li_id) return '';
                    $link = $li_link . $li_id;
                    break;
                case "twitter":
                    if(!$tw_id) return '';
                    $link = $tw_link . $tw_id;
                    break;	
            }

        // else return a tag with the link text
        } else {
            $link = '<a href="';

            switch($network) {
                case "facebook":
                    if(!$fb_id) return '';
                    $link .= $fb_link . $fb_id;
                    break;
                case "linkedin":
                    if(!$li_id) return '';
                    $link .= $li_link . $li_id;
                    break;
                case "twitter":
                    if(!$tw_id) return '';
                    $link .= $tw_link . $tw_id;
                    break;
            }

            $link .= '" class="arlo-social-'.$network.'">'.$linktext.'</a>';

        }

        return $link;        
    }
    
    private static function shortcode_presenter_events_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;
        $slug = get_post( $post )->post_name;

        $slug_a = explode('-', $slug);
        $p_id = $slug_a[0];

        $t1 = "{$wpdb->prefix}arlo_eventtemplates";
        $t2 = "{$wpdb->prefix}arlo_events";
        $t3 = "{$wpdb->prefix}arlo_events_presenters";

        $items = $wpdb->get_results(
            "SELECT 
                et.et_name, et.et_post_name,et_post_id
            FROM 
                $t1 et
            LEFT JOIN 
                $t2 e 
            ON  
                e.et_arlo_id = et.et_arlo_id
            AND
                e.import_id = et.import_id
            INNER JOIN 
                $t3 exp 
            ON 
                exp.e_id = e.e_id
            AND 
                exp.import_id = e.import_id
            WHERE 
                exp.p_arlo_id = $p_id 
            AND 
                e_parent_arlo_id = 0
            AND
                e.import_id = $import_id
            GROUP BY 
                et.et_name
            ORDER BY 
                et.et_name ASC", ARRAY_A);

        $events = '';

        if($wpdb->num_rows > 0) {

            $events .= '<ul class="presenter-events">';

            foreach($items as $item) {

                $et_id = arlo_get_post_by_name($item['et_post_name'], 'arlo_event');

                $permalink = get_permalink($et_id);

                $events .= '<li><a href="'.$permalink.'">' . htmlentities($item['et_name'], ENT_QUOTES, "UTF-8") . '</a></li>';

            }

            $events .= '</ul>';

        }

        return $events;        

    }        
}