<?php
namespace ArloTraining\Shortcodes;

use ArloTraining\Entities\Categories as CategoriesEntity;
use ArloTraining\CacheControl;

class Filters {
    public static function get_filter_options($filter, $import_id, $post_id = NULL) {
        global $post, $wpdb;
        
        $arlo_region = \Arlo_For_Wordpress::get_region_parameter();
        $join = [];
        $where = [];
        $join_parameters = [];
        $where_parameters = [];

        $base_category = ((isset($GLOBALS['arlo_filter_base']['category']) && is_array($GLOBALS['arlo_filter_base']['category']) && count($GLOBALS['arlo_filter_base']['category'])) ? $GLOBALS['arlo_filter_base']['category'] : 0);
        $exclude_category = ((isset($GLOBALS['arlo_filter_base']['categoryhidden']) && is_array($GLOBALS['arlo_filter_base']['categoryhidden']) && count($GLOBALS['arlo_filter_base']['categoryhidden'])) ? $GLOBALS['arlo_filter_base']['categoryhidden'] : 0);

        switch ($filter) {
            case 'location':
                $where[] = " e_locationname != '' ";
                $where[] = " e.import_id = %d ";
                $where_parameters[] = $import_id;

                if (!empty($arlo_region)) {
                    $where[] = " e.e_region = %s";
                    $where_parameters[] = $arlo_region;
                }

                if (!empty($post_id) || $post_id === 0) {
                    $join['et'] = " LEFT JOIN 
                                {$wpdb->prefix}arlo_eventtemplates AS et
                            ON 
                                et.et_arlo_id = e.et_arlo_id
                            AND
                                et.import_id = e.import_id
                                " . (!empty($arlo_region) ? 'AND et.et_region = %s' : '' );
        
                    $where[] = ' et_post_id = %d' ;
                    if(!empty($arlo_region)) {
                        $join_parameters[] = $arlo_region;
                    }
                    $where_parameters[] = $post_id;
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
                        " . (!empty($arlo_region) ? 'AND et.et_region = %s' : '' );
                        
                        if(!empty($arlo_region)) {
                            $join_parameters[] = $arlo_region;
                        }
                    }

                    $join['etc'] = "
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_eventtemplates_categories AS etc
                    ON
                        etc.et_arlo_id = et.et_arlo_id
                    AND
                        etc.import_id = et.import_id
                    ";

                    $where[] = " (c_arlo_id IS NULL OR c_arlo_id IN (" . implode(", ", array_map(function($cat) { return '%d'; }, $categories_flatten_list)) . ")) ";
                    $where_parameters = array_merge($where_parameters, array_map(function($cat) { return $cat['id']; }, $categories_flatten_list));
                }

                $sql = "SELECT 
                        DISTINCT e.e_locationname
                    FROM 
                        {$wpdb->prefix}arlo_events e 
                    " . implode("\n", $join) . "
                    WHERE 
                    " . implode(" AND ", $where) . "
                    GROUP BY 
                        e.e_locationname 
                    ORDER BY 
                        e.e_locationname";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
                $sql = $wpdb->prepare($sql, array_merge($join_parameters, $where_parameters));
                
                $items = CacheControl::fetch_results($sql, ARRAY_A);

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

                $currentMonth = (int)gmdate('m');

                for ($x = $currentMonth; $x < $currentMonth + 12; $x++) {
                    $date = mktime(0, 0, 0, $x, 1);
                    $months[$x]['string'] = gmdate('F', $date);
                    $months[$x]['value'] = gmdate('Ym01', $date) . ':' . gmdate('Ymt', $date);

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
                    " . (!empty($arlo_region) ? 'AND et.et_region = %s' : '' );
                    $where[] = ' et_post_id = %d' ;

                    if(!empty($arlo_region)) {
                        $join_parameters[] = $arlo_region;
                    }
                    $where_parameters[] = $post_id;
                }                      

                $where[] = " e.import_id = %d ";
                $where_parameters[] = $import_id;

                if (!empty($arlo_region)) {
                    $where[] = " e.e_region = %s";
                    $where_parameters[] = $arlo_region;
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
                        " . (!empty($arlo_region) ? 'AND et.et_region = %s' : '' );
                        if(!empty($arlo_region)) {
                            $join_parameters[] = $arlo_region;
                        }
                    }


                    $join['etc'] = "
                    LEFT JOIN 
                        {$wpdb->prefix}arlo_eventtemplates_categories AS etc
                    ON
                        etc.et_arlo_id = et.et_arlo_id
                    AND
                        etc.import_id = et.import_id
                    ";

                    $where[] = " (c_arlo_id IS NULL OR c_arlo_id IN (" . implode(", ", array_map(function($cat) { return '%d'; }, $categories_flatten_list)) . ")) ";
                    $where_parameters = array_merge($where_parameters, array_map(function($cat) { return $cat['id']; }, $categories_flatten_list));
                }


                $sql = "SELECT DISTINCT
                        v.v_physicaladdressstate
                    FROM 
                        {$wpdb->prefix}arlo_venues AS v
                    " . implode("\n", $join) . "
                    WHERE 
                    " . implode(" AND ", $where) . "                           
                    ORDER BY v_name";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
                $sql = $wpdb->prepare($sql, array_merge($join_parameters, $where_parameters));
                
                $items = CacheControl::fetch_results($sql, ARRAY_A);


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
                $prepared_sql = $wpdb->prepare("SELECT DISTINCT
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
                        etag.import_id = %d
                    ORDER BY tag", $import_id);
                
                $items = CacheControl::fetch_results($prepared_sql, ARRAY_A);

                $tags = array();

                foreach ($items as $item) {
                    $tags[] = array(
                        'string' => $item['tag'],
                        'value' => $item['tag'],
                    );
                }

                return $tags;

            case 'templatetag':
                $prepared_sql = $wpdb->prepare("SELECT DISTINCT
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
                        ett.import_id = %d
                    ORDER BY tag", $import_id);
                
                $items = CacheControl::fetch_results($prepared_sql, ARRAY_A);

                $tags = array();
                
                foreach ($items as $item) {
                    $tags[] = array(
                        'string' => $item['tag'],
                        'value' => $item['tag']
                    );
                }

                return $tags;

            case 'presenter':
                $sql_presenter = $wpdb->prepare("SELECT DISTINCT
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
                        epresenter.import_id = %d
                    ORDER BY p_firstname", $import_id);
                
                $items = CacheControl::fetch_results($sql_presenter, ARRAY_A);

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
                $prepared_sql = $wpdb->prepare("SELECT DISTINCT
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
                        oatag.import_id = %d
                    ORDER BY tag", $import_id);
                
                $items = CacheControl::fetch_results($prepared_sql, ARRAY_A);

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