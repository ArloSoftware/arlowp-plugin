<?php
namespace ArloTraining\Shortcodes;

use ArloTraining\CacheControl;

class Presenters {
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

        $custom_shortcodes = Shortcodes::get_custom_shortcodes('presenters');

        foreach ($custom_shortcodes as $shortcode_name => $shortcode) {
            Shortcodes::add($shortcode_name, function($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
                if (!is_array($atts) && empty($atts)) { $atts = []; }                
                return self::shortcode_presenter_list($content, $atts, $shortcode_name, $import_id);
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

        $sql = $wpdb->prepare(
            "SELECT 
                DISTINCT(p.p_arlo_id)
            FROM 
                {$wpdb->prefix}arlo_presenters p 
            LEFT JOIN 
                {$wpdb->prefix}posts post 
            ON 
                p.p_post_id = post.ID
            WHERE 
                post.post_type = 'arlo_presenter'
            AND
                p.import_id = %d
            ORDER BY 
                p.p_lastname ASC",
            $import_id
        );
        
        $items = CacheControl::fetch_results($sql, ARRAY_A);

        $num = is_array($items) ? count($items) : 0;

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

        $output .= Shortcodes::create_rich_snippet( $item_list );

        return $output;        
    }

    private static function get_presenters($atts, $import_id) {
        global $wpdb;

        $limit = intval(isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page'));
        $page = arlo_current_page();
        $offset = ($page - 1) * $limit;

        $sql = $wpdb->prepare("SELECT 
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
                {$wpdb->prefix}arlo_presenters p 
            LEFT JOIN 
                {$wpdb->prefix}posts post 
            ON 
                p.p_post_id = post.ID
            WHERE 
                post.post_type = 'arlo_presenter'
            AND
                p.import_id = %d
            GROUP BY
                p_arlo_id                               
            ORDER 
                BY p.p_firstname ASC, p.p_lastname ASC
            LIMIT 
                %d, %d", $import_id, $offset, $limit);
        $items = CacheControl::fetch_results($sql, ARRAY_A);
        return $items;
    }
    
    private static function shortcode_presenter_name($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_firstname']) && !isset($GLOBALS['arlo_presenter_list_item']['p_lastname'])) return '';

        $first_name = $GLOBALS['arlo_presenter_list_item']['p_firstname'];
        $last_name = $GLOBALS['arlo_presenter_list_item']['p_lastname'];

        return esc_html($first_name . ' ' . $last_name);
    }
    
    private static function shortcode_presenter_permalink($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
    	if(!isset($GLOBALS['arlo_presenter_list_item']['post_id'])) return '';

	    return esc_url(get_permalink($GLOBALS['arlo_presenter_list_item']['post_id']));
    }
    
    private static function shortcode_presenter_link($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_viewuri'])) return '';

        return esc_url($GLOBALS['arlo_presenter_list_item']['p_viewuri']);
    }
    
    private static function shortcode_presenter_profile($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_profile'])) return '';

        return wp_kses_post($GLOBALS['arlo_presenter_list_item']['p_profile']);
    }

    private static function shortcode_presenter_profile_avatar($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if ( ! isset( $GLOBALS['arlo_presenter_list_item'] ) || ! is_array( $GLOBALS['arlo_presenter_list_item'] ) ) {
            return '';
        }

        $raw_placeholder = $atts['placeholder'] ?? null;
        $placeholder_image_path = \ArloTraining\Utilities::sanitize_relative_url(
            is_scalar( $raw_placeholder ) ? (string) $raw_placeholder : null,
            'themes/theme.z/images/presenter_placeholder.png'
        );
        $placeholder_image_url = ARLO_PLUGIN_ROOT_URL . $placeholder_image_path;
        $cls = 'noimage';
        if ( isset( $GLOBALS['arlo_presenter_list_item']['p_profile'] ) ) {
            $raw_profile = $GLOBALS['arlo_presenter_list_item']['p_profile'];
            $src = \ArloTraining\Utilities::try_parse_first_image_src(
                is_scalar( $raw_profile ) ? (string) $raw_profile : null
            );
            if ( $src !== null ) {
                $placeholder_image_url = $src;
                $cls = '';
            }
        }

        $raw_first_name = $GLOBALS['arlo_presenter_list_item']['p_firstname'] ?? '';
        $first_name = is_scalar( $raw_first_name ) ? (string) $raw_first_name : '';
        $raw_last_name = $GLOBALS['arlo_presenter_list_item']['p_lastname'] ?? '';
        $last_name = is_scalar( $raw_last_name ) ? (string) $raw_last_name : '';
        $alt_text = trim( $first_name . ' ' . $last_name );

        return "<img class='" . esc_attr( $cls ) . "' src='" . esc_url( $placeholder_image_url ) . "' alt='" . esc_attr( $alt_text ) . "' />";
    }
    
    private static function shortcode_presenter_qualifications($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_qualifications'])) return '';

        return wp_kses_post($GLOBALS['arlo_presenter_list_item']['p_qualifications']);
    }
    
    private static function shortcode_presenter_interests($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        if(!isset($GLOBALS['arlo_presenter_list_item']['p_interests'])) return '';

        return wp_kses_post($GLOBALS['arlo_presenter_list_item']['p_interests']);
    }

    private static function shortcode_presenter_rich_snippet($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        extract(shortcode_atts(array(
            'link' => 'permalink'
        ), $atts, $shortcode_name, $import_id));

        $performer = !empty($GLOBALS['arlo_presenter_list_item']) ? Shortcodes::get_performer($GLOBALS['arlo_presenter_list_item'], $link) : "";

        return Shortcodes::create_rich_snippet( $performer ); 
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

        $network = trim(strtolower($network));

        // $linktext may contain HTML — theme templates commonly pass icon markup such as
        // <i class="fa-brands fa-twitter"></i> as the linktext attribute value. It is
        // sanitised with wp_kses_post() when inserted into the anchor body below.
        if(is_null($linktext) || trim($linktext) === '') {

            switch($network) {
                case "facebook":
                    if( empty( $GLOBALS['arlo_presenter_list_item']['p_facebookid'] ) ) return '';
                    $link = esc_url( $fb_link . $GLOBALS['arlo_presenter_list_item']['p_facebookid'] );
                    break;
                case "linkedin":
                    if( empty( $GLOBALS['arlo_presenter_list_item']['p_linkedinid'] ) ) return '';
                    $link = esc_url( $li_link . $GLOBALS['arlo_presenter_list_item']['p_linkedinid'] );
                    break;
                case "twitter":
                    if( empty( $GLOBALS['arlo_presenter_list_item']['p_twitterid'] ) ) return '';
                    $link = esc_url( $tw_link . $GLOBALS['arlo_presenter_list_item']['p_twitterid'] );
                    break;	
            }

        // else return a tag with the link text
        } else {
            $url = '';
            switch($network) {
                case "facebook":
                    if( empty( $GLOBALS['arlo_presenter_list_item']['p_facebookid'] ) ) return '';
                    $url = $fb_link . $GLOBALS['arlo_presenter_list_item']['p_facebookid'];
                    break;
                case "linkedin":
                    if( empty( $GLOBALS['arlo_presenter_list_item']['p_linkedinid'] ) ) return '';
                    $url = $li_link . $GLOBALS['arlo_presenter_list_item']['p_linkedinid'];
                    break;
                case "twitter":
                    if( empty( $GLOBALS['arlo_presenter_list_item']['p_twitterid'] ) ) return '';
                    $url = $tw_link . $GLOBALS['arlo_presenter_list_item']['p_twitterid'];
                    break;
            }
            $extracls = isset($atts['linkclass']) ? esc_attr($atts['linkclass']) : '';
            $link = '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $network ) . '" class="arlo-social-' . esc_attr( $network ) . ' ' . $extracls . '">' . wp_kses_post( $linktext ) . '</a>';

        }

        return $link;        
    }
    
    private static function shortcode_presenter_events_list($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $post, $wpdb;
        $slug = get_post( $post )->post_name;

        $slug_a = explode('-', $slug);
        $p_id = $slug_a[0];

        $sql = $wpdb->prepare("SELECT 
                et.et_name, et.et_post_name,et_post_id
            FROM 
                {$wpdb->prefix}arlo_eventtemplates et
            LEFT JOIN 
                {$wpdb->prefix}arlo_events e 
            ON  
                e.et_arlo_id = et.et_arlo_id
            AND
                e.import_id = et.import_id
            INNER JOIN 
                {$wpdb->prefix}arlo_events_presenters exp 
            ON 
                exp.e_id = e.e_id
            AND 
                exp.import_id = e.import_id
            WHERE 
                exp.p_arlo_id = %d
            AND 
                e_parent_arlo_id = 0
            AND
                e.import_id = %d
            GROUP BY 
                et.et_name
            ORDER BY 
                et.et_name ASC", $p_id, $import_id);
        $items = CacheControl::fetch_results($sql, ARRAY_A);

        $events = '';

        if(!empty($items)) {

            $events .= '<ul class="presenter-events">';

            foreach($items as $item) {

                $et_id = arlo_get_post_by_name($item['et_post_name'], 'arlo_event');

                $permalink = get_permalink($et_id);

                $events .= '<li><a href="'.esc_url($permalink).'">' . esc_html($item['et_name']) . '</a></li>';

            }

            $events .= '</ul>';

        }

        return $events;        

    }        

    private static function shortcode_presenter_events_list_advanced($content = '', $atts = [], $shortcode_name = '', $import_id = '') {
        global $wpdb;
        global $post, $wpdb;
        $slug = get_post( $post )->post_name;

        $slug_a = explode('-', $slug);
        $p_id = $slug_a[0];

        $sql = $wpdb->prepare("SELECT 
                et.*, e.e_is_taxexempt, e.e_locationname, e.v_id, e.e_locationvisible, e.e_isonline, e.e_startdatetime
            FROM  {$wpdb->prefix}arlo_eventtemplates et
            LEFT JOIN  {$wpdb->prefix}arlo_events e ON e.et_arlo_id = et.et_arlo_id AND e.import_id = et.import_id
            INNER JOIN  {$wpdb->prefix}arlo_events_presenters exp  ON exp.e_id = e.e_id AND exp.import_id = e.import_id
            WHERE 
                exp.p_arlo_id = %d 
            AND 
                e_parent_arlo_id = 0
            AND
                e.import_id = %d
            GROUP BY 
                et.et_name
            ORDER BY 
                et.et_name ASC", $p_id, $import_id);
        $items = CacheControl::fetch_results($sql, ARRAY_A);

        $events = '';
        if(!empty($items)) {
            foreach($items as $item) {
                $GLOBALS['arlo_eventtemplate'] = $item;
                $GLOBALS['arlo_event_list_item'] = $item;
                $events .= do_shortcode($content);;

            }
        }
        return $events;        

    }
}