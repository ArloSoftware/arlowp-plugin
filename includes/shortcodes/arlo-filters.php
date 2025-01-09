<?php
namespace Arlo\Shortcodes;

use Arlo\Entities\Categories as CategoriesEntity;

class Filters {
    public static function get_filter_options($filter, $import_id, $post_id = NULL) {
        global $post, $wpdb;
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        $join = [];
        $where = [];

        $base_category = ((isset($GLOBALS['arlo_filter_base']['category']) && is_array($GLOBALS['arlo_filter_base']['category']) && count($GLOBALS['arlo_filter_base']['category'])) ? $GLOBALS['arlo_filter_base']['category'] : 0);
        $exclude_category = ((isset($GLOBALS['arlo_filter_base']['categoryhidden']) && is_array($GLOBALS['arlo_filter_base']['categoryhidden']) && count($GLOBALS['arlo_filter_base']['categoryhidden'])) ? $GLOBALS['arlo_filter_base']['categoryhidden'] : 0);

        switch ($filter) {
            case 'location':
                $t1 = "{$wpdb->prefix}arlo_events";
                $where[] = " e_locationname != '' ";
                $where[] = " e.import_id = $import_id ";

                if (!empty($arlo_region)) {
                    $where[] = " e.e_region = '" . esc_sql($arlo_region) . "'";
                }

                if (!empty($post_id) || $post_id === 0) {
                    $join['et'] = " LEFT JOIN 
                                {$wpdb->prefix}arlo_eventtemplates AS et
                            ON 
                                et.et_arlo_id = e.et_arlo_id
                            AND
                                et.import_id = e.import_id
                                " . (!empty($arlo_region) ? 'AND et.et_region = "' . esc_sql($arlo_region) . '"' : '' );
        
                    $where[] = ' et_post_id = ' . $post_id;
                }                

                if (is_array($base_category) || is_array($exclude_category)) {

                    $categories_flatten_list = CategoriesEntity::get_flattened_category_list_for_filter($base_category, $exclude_category, $import_id);

                    if (!is_array($categories_flatten_list) || !count($categories_flatten_list)) {
                        $categories_flatten_list = [ ['id' => 0, 'value' => 'none', 'string' => 'none']];
                    }
                    
                    if (empty($post_id)) {
                        $join['et'] = "
                        LEFT JOIN 
                            {$wpdb->prefix}arlo_eventtemplates AS et
                        ON 
                            et.et_arlo_id = e.et_arlo_id
                        AND
                            et.import_id = e.import_id
                        " . (!empty($arlo_region) ? 'AND et.et_region = "' . esc_sql($arlo_region) . '"' : '' );
                    }

                    $join['etc'] = "
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_eventtemplates_categories AS etc
                    ON
                        etc.et_arlo_id = et.et_arlo_id
                    AND
                        etc.import_id = et.import_id
                    ";

                    $where[] = " (c_arlo_id IS NULL OR c_arlo_id IN (" . implode(", ", array_map(function($cat) { return $cat['id']; }, $categories_flatten_list)) . ")) ";
                }

                $items = $wpdb->get_results(
                    "SELECT 
                        DISTINCT e.e_locationname
                    FROM 
                        $t1 e 
                    " . implode("\n", $join) . "
                    WHERE 
                    " . implode(" AND ", $where) . "
                    GROUP BY 
                        e.e_locationname 
                    ORDER BY 
                        e.e_locationname", ARRAY_A);

                $locations = array();

                foreach ($items as $item) {
                    $locations[] = array(
                        'string' => $item['e_locationname'],
                        'value' => $item['e_locationname'],
                    );
                }

                return $locations;

            case 'month':
                $months = array();

                $currentMonth = (int)date('m');

                for ($x = $currentMonth; $x < $currentMonth + 12; $x++) {
                    $date = mktime(0, 0, 0, $x, 1);
                    $months[$x]['string'] = date('F', $date);
                    $months[$x]['value'] = date('Ym01', $date) . ':' . date('Ymt', $date);

                }

                return $months;

            case 'state':

                $join['e'] =
                    "
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_events AS e
                    ON
                        v.v_arlo_id = e.v_id
                    AND
                        v.import_id = e.import_id                    
                    ";

                if (!empty($post_id) || $post_id === 0) {
                    $join['et'] = "
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_eventtemplates AS et
                    ON 
                        et.et_arlo_id = e.et_arlo_id
                    AND
                        et.import_id = e.import_id
                    " . (!empty($arlo_region) ? 'AND et.et_region = "' . esc_sql($arlo_region) . '"' : '' );
                    $where[] = ' et_post_id = ' . $post_id;
                }                      

                $where[] = " e.import_id = $import_id ";

                if (!empty($arlo_region)) {
                    $where[] = " e.e_region = '" . esc_sql($arlo_region) . "'";
                }

                if (is_array($base_category) || is_array($exclude_category)) {

                    $categories_flatten_list = CategoriesEntity::get_flattened_category_list_for_filter($base_category, $exclude_category, $import_id);   
                    
                    if (!is_array($categories_flatten_list) || !count($categories_flatten_list)) {
                        $categories_flatten_list = [ ['id' => 0, 'value' => 'none', 'string' => 'none']];
                    }
                    
                    if (empty($post_id)) {
                        $join['et'] = "
                        LEFT JOIN 
                            {$wpdb->prefix}arlo_eventtemplates AS et
                        ON 
                            et.et_arlo_id = e.et_arlo_id
                        AND
                            et.import_id = e.import_id
                        " . (!empty($arlo_region) ? 'AND et.et_region = "' . esc_sql($arlo_region) . '"' : '' );
                    }


                    $join['etc'] = "
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_eventtemplates_categories AS etc
                    ON
                        etc.et_arlo_id = et.et_arlo_id
                    AND
                        etc.import_id = et.import_id
                    ";

                    $where[] = " (c_arlo_id IS NULL OR c_arlo_id IN (" . implode(", ", array_map(function($cat) { return $cat['id']; }, $categories_flatten_list)) . ")) ";
                }


                $items = $wpdb->get_results(
                    "SELECT DISTINCT
                        v.v_physicaladdressstate
                    FROM 
                        {$wpdb->prefix}arlo_venues AS v
                    " . implode("\n", $join) . "
                    WHERE 
                    " . implode(" AND ", $where) . "                           
                    ORDER BY v_name", ARRAY_A);


                $states = array();

                foreach ($items as $item) {
                    if (!empty($item['v_physicaladdressstate']) || in_array($item['v_physicaladdressstate'],[0,"0"], true) ) {
                        $states[] = array(
                            'string' => $item['v_physicaladdressstate'],
                            'value' => $item['v_physicaladdressstate'],
                        );
                    }
                }

                return $states;

            case 'delivery':
                return \Arlo_For_Wordpress::$delivery_labels;

            case 'category':
                return CategoriesEntity::get_flattened_category_list_for_filter($base_category, $exclude_category, $import_id);

            case 'eventtag':
                $items = $wpdb->get_results(
                    "SELECT DISTINCT
                        t.id,
                        t.tag
                    FROM 
                        {$wpdb->prefix}arlo_events_tags AS etag
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_tags AS t
                    ON
                        t.id = etag.tag_id
                    AND
                        t.import_id = etag.import_id
                    WHERE 
                        etag.import_id = $import_id
                    ORDER BY tag", ARRAY_A);

                $tags = array();

                foreach ($items as $item) {
                    $tags[] = array(
                        'string' => $item['tag'],
                        'value' => $item['tag'],
                    );
                }

                return $tags;

            case 'templatetag':
                $items = $wpdb->get_results(
                    "SELECT DISTINCT
                        t.id,
                        t.tag
                    FROM 
                        {$wpdb->prefix}arlo_eventtemplates_tags AS ett
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_tags AS t
                    ON
                        t.id = ett.tag_id
                    AND
                        t.import_id = ett.import_id
                    WHERE 
                        ett.import_id = $import_id
                    ORDER BY tag", ARRAY_A);

                $tags = array();
                
                foreach ($items as $item) {
                    $tags[] = array(
                        'string' => $item['tag'],
                        'value' => $item['tag']
                    );
                }

                return $tags;

            case 'presenter':
                $items = $wpdb->get_results(
                    "SELECT DISTINCT
                        p.p_arlo_id,
                        p.p_firstname,
                        p.p_lastname
                    FROM 
                        {$wpdb->prefix}arlo_events_presenters AS epresenter
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_presenters AS p
                    ON
                        p.p_arlo_id = epresenter.p_arlo_id
                    WHERE 
                        epresenter.import_id = $import_id
                    ORDER BY p_firstname", ARRAY_A);

                $presenters = array();

                foreach ($items as $item) {
                    if (!is_null($item['p_firstname']) && !is_null($item['p_firstname'])) {
                        $presenters[] = array(
                            'string' => $item['p_firstname'] . " " . $item['p_lastname'],
                            'value' => $item['p_arlo_id'] . "-" . $item['p_firstname'] . "-" . $item['p_lastname'],
                        );
                    }
                }

                return $presenters;

            case 'oatag':
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

                return $tags;

            }

    }
}