<?php

$arlo_plugin = Arlo_For_Wordpress::get_instance();
$arlo_plugin_slug = $arlo_plugin->get_plugin_slug();

/*
 * Add event category to title when filtered by a category
 */
add_filter( 'the_title', function($title, $id = null){
	$settings = get_option('arlo_settings');
	
	$pages = [];
	
	if (!empty($settings['post_types']['event']['posts_page'])) {
		array_push($pages, $settings['post_types']['event']['posts_page']);
	}
	
	if (!empty($settings['post_types']['eventsearch']['posts_page'])) {
		array_push($pages, $settings['post_types']['eventsearch']['posts_page']);
	}

	if (!empty($settings['post_types']['upcoming']['posts_page'])) {
		array_push($pages, $settings['post_types']['upcoming']['posts_page']);
	}
	
	
	$subtitle = '';
	
	$arlo_category = isset($_GET['arlo-category']) && !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : get_query_var('arlo-category', '');
	
	$cat_slug = !empty($arlo_category) ? $arlo_category : '';	
	
	$cat = null;
	
	if (!empty($cat_slug))
		$cat = \Arlo\Categories::get(array('slug' => $cat_slug));
		
		
	$location = !empty($_GET['arlo-location']) ? $_GET['arlo-location'] : get_query_var('arlo-location', '');
	$search = !empty($_GET['arlo-search']) ? $_GET['arlo-search'] : get_query_var('arlo-search', '');	
		
	$location = stripslashes(urldecode($location));
	$search = stripslashes(urldecode($search));
	
	if($id === null || !in_array($id, $pages)) return $title;
		
	if(!$cat && empty($location) && empty($search)) return $title;
	
	if (!empty($cat->c_name)) {
		$subtitle = $cat->c_name;
		
		if (!empty($location)) {
			$subtitle .= ' (' . $location . ')';
		}
	} else if (!empty($location)) {
		$subtitle = htmlentities($location);		
	} else if (!empty($search)) {
		$subtitle = htmlentities($search);
	}
	
	// append category name to events page
	if (!empty($subtitle)) {
		$subtitle = '<span class="cat-title-ext">' . (!empty($title) ? ': ':'') . $subtitle . ' </span>';
	}

        
	return $title . $subtitle;
}, 10, 2);

/*
 * Trick WP to treat custom post types as pages
 */
add_action('parse_query', function($wp_query){
	if (isset($wp_query->query['post_type']) && in_array($wp_query->query['post_type'], array('arlo_event', 'arlo_presenter', 'arlo_venue'))) {
		$wp_query->is_single = false;
		$wp_query->is_page = true;
	}

	return $wp_query;
});

/*
 * Now allow custom templates for these custom post types
 */
add_filter('page_template', function($template){
	global $post;
	
	if(in_array($post->post_type, array('arlo_event', 'arlo_presenter', 'arlo_venue'))) {
		$type = str_replace('arlo_', '', $post->post_type);
				
		if(($file = locate_template($type . '.php')) && is_page()) {
			return $file;
		}
	}
        
        add_filter( 'body_class', function( $classes ) {
            $classes[] = 'arlo';
            return $classes;
        });
	
	return $template;
}, 100, 1);

/**
 * arlo_child_categories function.
 * 
 * @access public
 * @param mixed $cats
 * @param int $depth (default: 0)
 * @return void
 */
 
function arlo_child_categories($cats, $depth=0) {
	if(!is_array($cats)) return array();

	$space = ($depth > 0) ? ' ' : '';

	$output = array();

	foreach($cats as $cat) {

		$output[] = array(
			'string' => str_repeat('&ndash;', $depth) . $space . $cat->c_name,
			'value' => $cat->c_slug,
			'id' => $cat->c_arlo_id
			);
		$output = array_merge($output, arlo_child_categories($cat->children, $depth+1));
	}

	return $output;
}

/**
 * arlo_create_region_selector function.
 * 
 * @access public
 * @param string $page_name
 * @return string
 */
function arlo_create_region_selector($page_name) {
	global $post;
	
	$valid_page_names = ['upcoming', 'event', 'eventsearch'];
	
	$settings = get_option('arlo_settings');  
	$regions = get_option('arlo_regions');  
	
	if (!in_array($page_name, $valid_page_names) || !(is_array($regions) && count($regions))) return "";
		
	$regionselector_html .= arlo_create_filter('region', $regions);					
	
	return $regionselector_html;
}



/**
 * arlo_create_filter function.
 * 
 * @access public
 * @param mixed $type
 * @param mixed $items
 * @param mixed $label (default: null)
 * @return void
 */
function arlo_create_filter($type, $items, $label=null) {
	$filter_html = '<select id="arlo-filter-' . $type . '" name="arlo-' . $type . '">';
	
	if (!is_null($label))
		$filter_html .= '<option value="">' . $label . '</option>';
	
	$selected_value = (isset($_GET['arlo-' . $type]) ? urldecode($_GET['arlo-' . $type]) : get_query_var('arlo-' . $type, ''));
		
	foreach($items as $key => $item) {

		if (empty($item['string']) && empty($item['value'])) {
			$item = array(
				'string' => $item,
				'value' => $key
			);
		}
		
		$selected = (strlen($selected_value) && urldecode($selected_value) == $item['value']) ? ' selected="selected"' : '';
		
		$filter_html .= '<option value="' . $item['value'] . '"' . $selected.'>';
		$filter_html .= $item['string'];
		$filter_html .= '</option>';

	}

	$filter_html .= '</select>';

	return $filter_html;
}

/**
 * Replace all arlo marcos with data from arlo tables
 *
 * @since    1.0.0
 *
 * @param    array $items 
 * @param    string $content 
 *
 * @return   string Macro filtered content
 */

function arlo_replace_macros($items, $content) {
	foreach($items as $key => $value) {
		$content = str_replace('[arlo_' . $key . ']', $value, $content);
	}

	return $content;
}

/**
 * Registers the arlo custom post types
 *
 * @since    1.0.0
 *
 */
function arlo_register_custom_post_types() {
	$settings = get_option('arlo_settings');

	foreach(Arlo_For_Wordpress::$post_types as $id => $type) {
		// default slug
		
		$slug = str_replace('_', '-', strtolower(trim(preg_replace('/[^A-Za-z]+/', '', $type['name']))));
		$slug = 'arlo/' . $slug;
		
		// slug based on page, if it exists
		$page_id = null; 
		if(isset($settings['post_types'][$id]['posts_page']) && $settings['post_types'][$id]['posts_page'] != 0) {
			$page_id = $settings['post_types'][$id]['posts_page'];
			$slug = substr(substr(str_replace(get_home_url(), '', get_permalink($settings['post_types'][$id]['posts_page'])), 0, -1), 1);
		}
                
		$args = array(
			'labels' => array(
                'name' => __( $type['name'], $GLOBALS['arlo_plugin_slug']),
                'singular_name' => __( $type['singular_name'], $GLOBALS['arlo_plugin_slug'])
            ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array(
				'slug' => $slug,
				'with_front' => false // false ensures no blog-like url
			),
			'capability_type'    => 'page',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'			 => array(
				'comments' => false
			)
		);
		
		// let's try some custom rewrite rules
		if($page_id) {
			switch($id) {
				case 'upcoming':
					add_rewrite_rule('^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(delivery-([^/]*))?/?(tag-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-delivery=$matches[10]&arlo-eventtag=$matches[12]&paged=$matches[14]','top');
				break;			
				case 'event':					
					add_rewrite_rule('^' . $slug . '/(\d+-[^/]*)?/?$','index.php?arlo_event=$matches[1]','top');
					add_rewrite_rule('^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(delivery-([^/]*))?/?(tag-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-delivery=$matches[10]&arlo-templatetag=$matches[12]&paged=$matches[14]','top');
				break;
				case 'eventsearch':
					add_rewrite_rule('^' . $slug . '/?(region-([^/]*))?/search/([^/]*)?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-search=$matches[3]&paged=$matches[5]','top');
				break;
				case 'presenter':
					add_rewrite_rule('^' . $slug . '/page/([^/]*)/?','index.php?page_id=' . $page_id . '&paged=$matches[1]','top');
				break;
				case 'venue':
					add_rewrite_rule('^' . $slug . '/page/([^/]*)/?','index.php?page_id=' . $page_id . '&paged=$matches[1]','top');
				break;
			}
		}
		
		register_post_type('arlo_' . $id, $args);
	}
	
	// these should possibly be in there own function?
	add_rewrite_tag('%page_id%', '([^&]+)');
	add_rewrite_tag('%arlo-region%', '([^&]+)');
	add_rewrite_tag('%arlo-category%', '([^&]+)');
	add_rewrite_tag('%arlo-month%', '([^&]+)');
	add_rewrite_tag('%arlo-location%', '([^&]+)');
	add_rewrite_tag('%arlo-delivery%', '([^&]+)');
	add_rewrite_tag('%arlo-eventtag%', '([^&]+)');
	add_rewrite_tag('%arlo-templatetag%', '([^&]+)');
	add_rewrite_tag('%arlo-search%', '([^&]+)');
	add_rewrite_tag('%paged%', '([^&]+)');
	
	// flush cached rewrite rules if we've just updated the arlo settings
	if(isset($_GET['settings-updated'])) flush_rewrite_rules();
}

/**
 * If there is a search term for arlo-search, we need to redirect to a friendlier url.
 *
 * @since    2.2.0
 *
 */
 
 function set_search_redirect() {
	$settings = get_option('arlo_settings');
	if (strpos($_SERVER['QUERY_STRING'], 'arlo-search') !== false && !empty($_GET['arlo-search'])) {
		if(isset($settings['post_types']['eventsearch']['posts_page']) && $settings['post_types']['eventsearch']['posts_page'] != 0) {
			$slug = substr(substr(str_replace(get_home_url(), '', get_permalink($settings['post_types']['eventsearch']['posts_page'])), 0, -1), 1);
			$location = '/' . $slug . '/search/' . urlencode(stripslashes_deep($_GET['arlo-search'])) . '/';
			wp_redirect( $location );
			exit();
		}
	}
}

/**
 * If there is at least one region, and the url doesn't contain any region information, we have to construct the url with region, 
 * and set the cookie according to the default (first) region
 *
 * @since    2.2.0
 *
 */
 
 function set_region_redirect() {
 	global $post;
	$regions = get_option('arlo_regions');
	$settings = get_option('arlo_settings');
	$selected_region = get_query_var('arlo-region', '');
	$page_id = get_query_var('page_id', '');
	$arlo_regionalized_page_ids = [];
	
	$obj = get_queried_object();
	
	$page_id = (empty($obj->ID) ? $page_id : $obj->ID);
		
	foreach(Arlo_For_Wordpress::$post_types as $id => $post) {
		if (isset($post['regionalized']) && is_bool($post['regionalized']) && $post['regionalized']) {
			$arlo_page_ids[intval($settings['post_types'][$id]['posts_page'])] = $id;
		}
	}
	
	if (array_key_exists($page_id, $arlo_page_ids) && is_array($regions) && count($regions) && empty($selected_region)) {
		$first_region_id = reset(array_keys($regions));
		
		setcookie("arlo-region", $first_region_id);	
		
		$slug = substr(substr(str_replace(get_home_url(), '', get_permalink($settings['post_types'][$arlo_page_ids[$page_id]]['posts_page'])), 0, -1), 1);
		
		$location = str_replace($slug, $slug.'/region-' . $first_region_id , $_SERVER['REQUEST_URI']);
		
		wp_redirect($location);
		exit();		
	}
}

/**
 * Checks if a post is a arlo custom post and sends the content to the revelant function
 *
 * @since    1.0.0
 *
 * @param    string $content The content of the custom post
 *
 * @return   string
 */
function arlo_the_content($content) {
	global $post;
	
	$post_type = str_replace('arlo_', '', get_post_type($post));

	if(function_exists('arlo_the_content_'.$post_type)) {
		return call_user_func_array('arlo_the_content_'.$post_type, func_get_args());
	}

	return $content;
}

/**
 * Returns arlo custom post event page content parsed of shortcodes and macros
 *
 * @since    1.0.0
 *
 * @param    string $content The content of the custom post
 *
 * @return   string The content replaced by the filtered event template
 */
function arlo_the_content_event($content) {
	$templates = arlo_get_option('templates');
	$content = $templates['event']['html'];

	global $post, $wpdb;

	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}posts";

	$item = $wpdb->get_row(
		"SELECT et.*, post.ID as post_id
		FROM $t1 et 
		LEFT JOIN $t2 post 
		ON et.et_post_name = post.post_name 
		WHERE post.post_type = 'arlo_event' AND post.ID = $post->ID
		ORDER BY et.et_name ASC", ARRAY_A);

	$GLOBALS['arlo_eventtemplate'] = $item;

	$output = do_shortcode($content);

	unset($GLOBALS['arlo_eventtemplate']);

	return $output;
}

/**
 * Returns arlo custom post presenter page content parsed of shortcodes and macros
 *
 * @since    1.0.0
 *
 * @param    string $content The content of the custom post
 *
 * @return   string The content replaced by the filtered presenter template
 */
function arlo_the_content_presenter($content) {
	$templates = arlo_get_option('templates');
	$content = $templates['presenter']['html'];

	global $post, $wpdb;

	$t1 = "{$wpdb->prefix}arlo_presenters";
	$t2 = "{$wpdb->prefix}posts";

	$item = $wpdb->get_row(
		"SELECT p.*, post.ID as post_id
		FROM $t1 p 
		LEFT JOIN $t2 post 
		ON p.p_post_name = post.post_name 
		WHERE post.post_type = 'arlo_presenter' AND post.ID = $post->ID
		ORDER BY p.p_lastname ASC", ARRAY_A);

	$GLOBALS['arlo_presenter_list_item'] = $item;

	$output = do_shortcode($content);

	unset($GLOBALS['arlo_presenter_list_item']);

	return $output;
}

/**
 * Returns arlo custom post venue page content parsed of shortcodes and macros
 *
 * @since    1.0.0
 *
 * @param    string $content The content of the custom post
 *
 * @return   string The content replaced by the filtered venue template
 */
function arlo_the_content_venue($content) {
	$templates = arlo_get_option('templates');
	$content = $templates['venue']['html'];

	global $post, $wpdb;

	$t1 = "{$wpdb->prefix}arlo_venues";
	$t2 = "{$wpdb->prefix}posts";

	$item = $wpdb->get_row(
		"SELECT v.*, post.ID as post_id
		FROM $t1 v 
		LEFT JOIN $t2 post 
		ON v.v_post_name = post.post_name 
		WHERE post.post_type = 'arlo_venue' AND post.ID = $post->ID
		ORDER BY v.v_name ASC", ARRAY_A);

	$GLOBALS['arlo_venue_list_item'] = $item;

	$output = do_shortcode($content);

	unset($GLOBALS['arlo_venue_list_item']);

	return $output;
}

/**
 * Returns pagination HTML for a list page such as Event Templates or Venues
 *
 * @since    2.0.0
 *
 * @param    int $num Total amount of items e.g. Event Templates or Venues
 *
 * @return   string The pagination HTML
 */
function arlo_pagination($num, $limit=null) {
	// the wordpress posts per page option value
	$limit = is_null($limit) ? get_option('posts_per_page') : $limit;
	
	$big = 999999999;
	
	$current = !empty($_GET['paged']) ? intval($_GET['paged']) : intval(get_query_var('paged'));
	
	return paginate_links(array(
		'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
		'format' => '?paged=%#%',
		'current' => max( 1, $current ),
		'total' => ceil($num/$limit),
		'mid_size' => 6
	));
}

/**
 * Get the content of a blueprint file
 *
 * @since    1.0.0
 *
 * @param    string $name name of the blueprint file to get
 *
 * @return   string the contents of the blueprint file
 */
function arlo_get_blueprint($name) {
	$path = ARLO_PLUGIN_DIR.'/includes/blueprints/'.$name.'.tmpl';

	if(file_exists($path)) {

		return file_get_contents($path);

	}

	return 'Blueprint NOT found';
}

/**
 * Flush permalinks
 *
 * @since    1.0.0
 *
 */
function arlo_flush_permalinks() {
	//update_option('rewrite_rules','');
	//arlo_register_custom_post_types();
	//flush_rewrite_rules();
}

/**
 * arlo_get_option function.
 * 
 * @access public
 * @param mixed $key
 * @param mixed $default (default: null)
 * @return void
 */
function arlo_get_option($key, $default=null) {
	$settings = get_option('arlo_settings', array());
	
	if(isset($settings[$key])) {
		return $settings[$key];
	}
	
	return $default;
}

/**
 * arlo_add_datamodel function.
 * 
 * @access public
 * @return void
 */
function arlo_add_datamodel() {
	install_table_arlo_eventtemplate();
	install_table_arlo_contentfields();
	install_table_arlo_tags();
	install_table_arlo_events();
	install_table_arlo_venues();
	install_table_arlo_presenters();
	install_table_arlo_offers();
	install_table_arlo_eventtemplates_presenters();
	install_table_arlo_events_presenters();
	install_table_arlo_import_log();
	install_table_arlo_categories();
	install_table_arlo_eventtemplates_categories();
	install_table_arlo_timezones();
	return;
}

/**
 * core_set_charset function.
 * 
 * @access public
 * @return void
 */
function core_set_charset() {
	global $wpdb;

	/* Load db functions */
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	if ( !empty($wpdb->charset) )
		return "DEFAULT CHARACTER SET $wpdb->charset";
	return '';
}

/**
 * install_table_arlo_eventtemplate function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_eventtemplate() {	
	global $wpdb, $current_user;
	$table_name = $wpdb->prefix . "arlo_eventtemplates";

	$sql = "CREATE TABLE " . $table_name . " (
		et_id INT(11) NOT NULL AUTO_INCREMENT,
		et_arlo_id INT(11) NOT NULL,
		et_code VARCHAR(255) NULL,
		et_name VARCHAR(255) NULL,
		et_descriptionsummary TEXT NULL,
		et_post_name VARCHAR(255) NULL,
		active DATETIME NULL,
		et_registerinteresturi TEXT NULL,
		et_region VARCHAR(5) NULL,
		PRIMARY KEY  (et_id),
		KEY et_arlo_id (et_arlo_id),
		KEY et_region (et_region))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);
}

/**
 * install_table_arlo_contentfields function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_contentfields() {	
	global $wpdb, $current_user;
	$table_name = $wpdb->prefix . "arlo_contentfields";

	$sql = "CREATE TABLE " . $table_name . " (
		cf_id INT(11) NOT NULL AUTO_INCREMENT,
		et_id INT(11) NOT NULL,
		cf_fieldname VARCHAR(255) NULL,
		cf_text TEXT NULL,
		cf_order INT(11) NULL,
		e_contenttype VARCHAR(255) NULL,
		active DATETIME NULL,
		PRIMARY KEY  (cf_id),
		KEY cf_order (cf_order),
		KEY et_id (et_id))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);
}

/**
 * install_table_arlo_events function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_events() {	
	global $wpdb, $current_user;
	$table_name = $wpdb->prefix . "arlo_events";

	$sql = "CREATE TABLE " . $table_name . " (
		e_id INT(11) NOT NULL AUTO_INCREMENT,
		e_arlo_id INT(11) NOT NULL,
		et_arlo_id INT(11) NULL,
		e_code VARCHAR(255) NULL,
		e_name VARCHAR(255) NULL,
		e_startdatetime DATETIME NOT NULL,
		e_finishdatetime DATETIME NULL,
		e_datetimeoffset VARCHAR(6) NULL,
		e_timezone VARCHAR(10) NULL,
		e_timezone_id TINYINT(3) UNSIGNED NULL,
		v_id INT(11) NULL,
		e_locationname VARCHAR(255) NULL,
		e_locationroomname VARCHAR(255) NULL,
	    e_locationvisible TINYINT(1) NOT NULL DEFAULT '0',
		e_isfull TINYINT(1) NOT NULL DEFAULT FALSE,
		e_placesremaining INT(11) NULL,
		e_summary VARCHAR(255) NULL,
		e_sessiondescription VARCHAR(255) NULL,
		e_notice TEXT NULL,
		e_viewuri VARCHAR(255) NULL,
		e_registermessage VARCHAR(255) NULL,
		e_registeruri VARCHAR(255) NULL,
		e_providerorganisation VARCHAR(255) NULL,
		e_providerwebsite VARCHAR(255) NULL,
		e_isonline TINYINT(1) NOT NULL DEFAULT FALSE,
		e_parent_arlo_id INT(11) NOT NULL,
		e_region VARCHAR(5) NOT NULL,
		active DATETIME NULL,
		PRIMARY KEY  (e_id),
		KEY et_arlo_id (et_arlo_id),
		KEY e_arlo_id (e_arlo_id),
		KEY e_region (e_region),
		KEY v_id (v_id))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($sql);
}

/**
 * install_table_arlo_venues function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_venues() {	
	global $wpdb, $current_user;
	$table_name = $wpdb->prefix . "arlo_venues";

	$sql = "CREATE TABLE " . $table_name . " (
		v_id INT(11) NOT NULL AUTO_INCREMENT,
		v_arlo_id INT(11) NOT NULL,
		v_name VARCHAR(255) NULL,
		v_geodatapointlatitude DECIMAL(10,6) NULL,
		v_geodatapointlongitude DECIMAL(10,6) NULL,
		v_physicaladdressline1 VARCHAR(255) NULL,
		v_physicaladdressline2 VARCHAR(255) NULL,
		v_physicaladdressline3 VARCHAR(255) NULL,
		v_physicaladdressline4 VARCHAR(255) NULL,
		v_physicaladdresssuburb VARCHAR(255) NULL,
		v_physicaladdresscity VARCHAR(255) NULL,
		v_physicaladdressstate VARCHAR(255) NULL,
		v_physicaladdresspostcode VARCHAR(255) NULL,
		v_physicaladdresscountry VARCHAR(255) NULL,
		v_viewuri VARCHAR(255) NULL,
		v_facilityinfodirections TEXT NULL,
		v_facilityinfoparking TEXT NULL,
		v_post_name VARCHAR(255) NULL,
		active DATETIME NULL,
		PRIMARY KEY  (v_id),
		KEY v_arlo_id (v_arlo_id))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);
}

/**
 * install_table_arlo_presenters function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_presenters() {	
	global $wpdb, $current_user;
	$table_name = $wpdb->prefix . "arlo_presenters";

	$sql = "CREATE TABLE " . $table_name . " (
		p_id INT(11) NOT NULL AUTO_INCREMENT,
		p_arlo_id INT(11) NOT NULL,
		p_firstname VARCHAR(64) NULL,
		p_lastname VARCHAR(64) NULL,
		p_viewuri VARCHAR(255) NULL,
		p_profile TEXT NULL,
		p_qualifications TEXT NULL,
		p_interests TEXT NULL,
		p_twitterid VARCHAR(255) NULL,
		p_facebookid VARCHAR(255) NULL,
		p_linkedinid VARCHAR(255) NULL,
		p_post_name VARCHAR(255) NULL,
		active DATETIME NULL,
		PRIMARY KEY  (p_id),
		KEY p_arlo_id (p_arlo_id))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);
}

/**
 * install_table_arlo_offers function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_offers() {	
	global $wpdb, $current_user;
	$charset_collate = core_set_charset();
	$table_name = $wpdb->prefix . "arlo_offers";

	$sql = "CREATE TABLE " . $table_name . " (
		o_id INT(11) NOT NULL AUTO_INCREMENT,
		o_arlo_id INT,
		et_id INT,
		e_id INT,
		o_label VARCHAR(255) NULL,
		o_isdiscountoffer TINYINT(1) NOT NULL DEFAULT FALSE,
		o_currencycode VARCHAR(255) NULL,
		o_offeramounttaxexclusive DECIMAL(15,2) NULL,
		o_offeramounttaxinclusive DECIMAL(15,2) NULL,
		o_formattedamounttaxexclusive VARCHAR(255) NULL,
		o_formattedamounttaxinclusive VARCHAR(255) NULL,
		o_taxrateshortcode VARCHAR(255) NULL,
		o_taxratename VARCHAR(255) NULL,
		o_taxratepercentage DECIMAL(3,2) NULL,
		o_message TEXT NULL,
		o_order INT(11) NULL,
		o_replaces INT(11) NULL,
		o_region VARCHAR(5) NOT NULL,
		active DATETIME NULL,
		PRIMARY KEY  (o_id),
		KEY o_arlo_id (o_arlo_id),
		KEY et_id (et_id),
		KEY e_id (e_id),
		KEY o_region (o_region),
		KEY o_order (o_order))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);
}

/**
 * install_table_arlo_eventtemplates_presenters function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_eventtemplates_presenters() {	
	global $wpdb, $current_user;
	$charset_collate = core_set_charset();
	$table_name = $wpdb->prefix . "arlo_eventtemplates_presenters";

	$sql = "CREATE TABLE " . $table_name . " (
		et_arlo_id INT(11) NULL,
		p_arlo_id INT(11) NULL,
		p_order INT(11) NULL COMMENT 'Order of the presenters for the event template.',
		active datetime DEFAULT NULL,
		PRIMARY KEY  (et_arlo_id,p_arlo_id),
		KEY cf_order (p_order),
		KEY fk_et_id_idx (et_arlo_id ASC),
		KEY fk_p_id_idx (p_arlo_id ASC))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($sql);
}

/**
 * install_table_arlo_tags function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_tags() {	
	global $wpdb, $current_user;
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
	$charset_collate = core_set_charset();

	$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_tags (
  		id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  		tag varchar(255) NOT NULL,
		active datetime NOT NULL,
		PRIMARY KEY  (id))
		CHARACTER SET utf8 COLLATE=utf8_general_ci";
		
	dbDelta($sql);

	$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_events_tags (
		e_arlo_id int(11) NOT NULL,
		tag_id mediumint(8) unsigned NOT NULL,
		active datetime NOT NULL,
		PRIMARY KEY  (e_arlo_id,tag_id))
  		CHARACTER SET utf8 COLLATE=utf8_general_ci";
  		
	dbDelta($sql);  		

	$sql = "CREATE TABLE " . $wpdb->prefix . "arlo_eventtemplates_tags (
		et_arlo_id int(11) NOT NULL,
		tag_id mediumint(8) unsigned NOT NULL,
		active datetime NOT NULL,
		PRIMARY KEY  (et_arlo_id,tag_id))
  		CHARACTER SET utf8 COLLATE=utf8_general_ci";

	dbDelta($sql);
}

/**
 * install_table_arlo_events_presenters function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_events_presenters() {	
	global $wpdb, $current_user;
	$charset_collate = core_set_charset();
	$table_name = $wpdb->prefix . "arlo_events_presenters";

	$sql = "CREATE TABLE " . $table_name . " (
		e_arlo_id INT(11) NULL,
		p_arlo_id INT(11) NULL,
		p_order INT(11) NULL COMMENT 'Order of the presenters for the event.',
		active datetime DEFAULT NULL,
		PRIMARY KEY  (e_arlo_id,p_arlo_id),		
		KEY fk_e_id_idx (e_arlo_id ASC),
		KEY fk_p_id_idx (p_arlo_id ASC))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($sql);
}

/**
 * install_table_arlo_categories function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_categories() {	
	global $wpdb, $current_user;
	$charset_collate = core_set_charset();
	$table_name = $wpdb->prefix . "arlo_categories";

	$sql = "CREATE TABLE " . $table_name . " (
		c_id INT(11) NOT NULL AUTO_INCREMENT,
		c_arlo_id INT(11) NOT NULL,
		c_name varchar(255) NOT NULL DEFAULT '',
		c_slug varchar(255) NOT NULL DEFAULT '',
		c_header text,
		c_footer text,
		c_template_num SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		c_order bigint(20) DEFAULT NULL,
		c_depth_level tinyint(3) unsigned NOT NULL DEFAULT '0',
		c_parent_id INT(11) DEFAULT NULL,
		active datetime DEFAULT NULL,
		PRIMARY KEY  (c_id),
		UNIQUE KEY c_arlo_id (c_arlo_id),
		KEY c_parent_id (c_parent_id))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($sql);
}

/**
 * install_table_arlo_eventtemplates_categories function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_eventtemplates_categories() {	
	global $wpdb, $current_user;
	$charset_collate = core_set_charset();
	$table_name = $wpdb->prefix . "arlo_eventtemplates_categories";

	$sql = "CREATE TABLE " . $table_name . " (
		et_arlo_id INT(11) NULL,
		c_arlo_id INT(11) NULL,
		et_order SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
		active datetime DEFAULT NULL,
		PRIMARY KEY  (et_arlo_id,c_arlo_id),
		KEY fk_et_id_idx (et_arlo_id ASC),
		KEY fk_c_id_idx (c_arlo_id ASC))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($sql);
}

/**
 * install_table_arlo_timezones function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_timezones() {	
	global $wpdb, $current_user;
	$charset_collate = core_set_charset();
	$table_name = $wpdb->prefix . "arlo_timezones";

	$sql = "
		CREATE TABLE " . $table_name . " (
		id tinyint(3) unsigned NOT NULL,
		name varchar(256) NOT NULL,
		active datetime NOT NULL,
		PRIMARY KEY  (id)) 
		CHARACTER SET utf8 COLLATE=utf8_general_ci;	
  	";
  			
	$sql2 = " 
		CREATE TABLE IF NOT EXISTS " . $table_name . "_olson (
		timezone_id int(11) NOT NULL,
		olson_name varchar(255) NOT NULL,
		active datetime NOT NULL,
		PRIMARY KEY  (timezone_id,olson_name)
		) CHARACTER SET utf8 COLLATE=utf8_general_ci;
	";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($sql);
	dbDelta($sql2);	
}

/**
 * install_table_arlo_import_log function.
 * 
 * @access public
 * @return void
 */
function install_table_arlo_import_log() {	
	global $wpdb, $current_user;
	$charset_collate = core_set_charset();
	$table_name = $wpdb->prefix . "arlo_import_log";

	$sql = "CREATE TABLE $table_name (
		  id int(11) unsigned NOT NULL AUTO_INCREMENT,
		  message text,
		  created datetime DEFAULT NULL,
		  successful tinyint(1) DEFAULT NULL,
		  PRIMARY KEY  (id)) 
		  CHARACTER SET utf8 COLLATE=utf8_general_ci;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);
}

/**
 * arlo_import_from_api function.
 * 
 * @access public
 * @return void
 */
function arlo_import_from_api() {
	$plugin = Arlo_For_Wordpress::get_instance();
	$plugin->import();
}

/**
 * arlo_get_post_by_name function.
 * 
 * @access public
 * @param mixed $name
 * @param string $post_type (default: 'post')
 * @return void
 */
function arlo_get_post_by_name($name, $post_type='post') {
	$args = array(
	  'name' => $name,
	  'post_type' => $post_type,
	  'post_status' => 'publish',
	  'numberposts' => 1
	);
	
	$posts = get_posts($args);
		
	if( $posts ) {
		return $posts[0];
	}
	
	return false;
}

/**
 * getTimezones function.
 * 
 * @access public
 * @return void
 */
function getTimezones() {
	global $wpdb, $arlo_plugin;
	
	$table = $wpdb->prefix . "arlo_timezones";
	$active = $arlo_plugin->get_last_import();
	
	$sql = "
	SELECT
		id,
		name
	FROM
		{$table}
	WHERE
		active = '{$active}'
	ORDER BY name
	";
	return $wpdb->get_results($sql);
}

function getTimezoneOlsonNames($timezone_id = 0) {
	global $wpdb, $arlo_plugin;
	
	$timezone_id = intval($timezone_id);
	
	$table = $wpdb->prefix . "arlo_timezones_olson";
	$active = $arlo_plugin->get_last_import();
	$where = '';
	
	if ($timezone_id > 0) {
		$where = "
			timezone_id = {$timezone_id}
		AND		
		";
	}
	
	$sql = "
	SELECT
		olson_name
	FROM
		{$table}
	WHERE
		{$where}	
		active = '{$active}'
	";
	return $wpdb->get_results($sql);
}


/*
 * Shortcodes
 */

$shortcodes = \Arlo\Shortcodes::init();

// event template list shortcode
$shortcodes->add('event_template_search_list', function($content='', $atts, $shortcode_name){
	$templates = arlo_get_option('templates');
	$content = $templates['eventsearch']['html'];

	return $content;
});

// upcoming event list region selector shortcode

$shortcodes->add('template_search_region_selector', function($content='', $atts, $shortcode_name){
	return arlo_create_region_selector("eventsearch");
});

// upcoming event list region selector shortcode

$shortcodes->add('template_region_selector', function($content='', $atts, $shortcode_name){
	return arlo_create_region_selector("event");
});

// event template list shortcode
$shortcodes->add('event_template_list', function($content='', $atts, $shortcode_name){
	$templates = arlo_get_option('templates');
	$content = $templates['events']['html'];

	return $content;
});

// event template list pagination

$shortcodes->add('event_template_list_pagination', function($content='', $atts, $shortcode_name){
	global $wpdb, $wp_query, $arlo_plugin;

	$active = $arlo_plugin->get_last_import();
	
	if (isset($GLOBALS['show_only_at_bottom']) && $GLOBALS['show_only_at_bottom']) return;
	
	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');

	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}posts";
	$t3 = "{$wpdb->prefix}arlo_eventtemplates_categories";
	$t4 = "{$wpdb->prefix}arlo_categories";
	$t5 = "{$wpdb->prefix}arlo_events";
	$t6 = "{$wpdb->prefix}arlo_eventtemplates_tags";
		
	$where = "WHERE post.post_type = 'arlo_event'";
	$join = "";
	
	$arlo_location = isset($_GET['arlo-location']) && !empty($_GET['arlo-location']) ? $_GET['arlo-location'] : get_query_var('arlo-location', '');
	$arlo_category = isset($_GET['arlo-category']) && !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : get_query_var('arlo-category', '');
	$arlo_delivery = isset($_GET['arlo-delivery']) && !empty($_GET['arlo-delivery']) ? $_GET['arlo-delivery'] : get_query_var('arlo-delivery', '');
	$arlo_templatetag = isset($_GET['arlo-templatetag']) && !empty($_GET['arlo-templatetag']) ? $_GET['arlo-templatetag'] : get_query_var('arlo-templatetag', '');
	$arlo_search = isset($_GET['arlo-search']) && !empty($_GET['arlo-search']) ? $_GET['arlo-search'] : get_query_var('arlo-search', '');
	$arlo_search = esc_sql(stripslashes(urldecode($arlo_search)));

	
	if(!empty($arlo_location) || (isset($arlo_delivery) && strlen($arlo_delivery) && is_numeric($arlo_delivery)) ) :

		$join .= " LEFT JOIN $t5 e USING (et_arlo_id)";
		$where .= " AND e.e_parent_arlo_id = 0";
		
		if(!empty($arlo_location)) :
			$where .= " AND e.e_locationname = '" . urldecode($arlo_location) . "'";
		endif;	
		
		if(isset($arlo_delivery) && strlen($arlo_delivery) && is_numeric($arlo_delivery)) :
			$where .= " AND e.e_isonline = " . $arlo_delivery;
		endif;	
		
	endif;	
	
	if(!empty($arlo_templatetag) && is_numeric($arlo_templatetag)) :
		$where .= " AND ett.tag_id = '" . intval($arlo_templatetag) . "'";
		$join .= " LEFT JOIN $t6 ett USING (et_arlo_id) ";
	endif;			
	
	
	if (!empty($arlo_search)) {
		$where .= '
		AND (
				et_code like "%' . $arlo_search . '%"
			OR
				et_name like "%' . $arlo_search . '%"
			OR 
				et_descriptionsummary like "%' . $arlo_search . '%"
		)
		';
	}	
	
	if(!empty($arlo_category) || !empty($atts['category'])) {

		$cat_id = 0;

		if(!empty($atts['category'])) {
			$cat_slug = $atts['category'];
		} else {
			$cat_slug = $arlo_category;
		}
		$where .= " AND ( c.c_slug = '$cat_slug'";
		
		$cat_id = $wpdb->get_var("
		SELECT
			c_arlo_id
		FROM 
			{$wpdb->prefix}arlo_categories
		WHERE 
			c_slug = '{$cat_slug}'
		");
		
		if (is_null($cat_id)) {
			$cat_id = 0;
		} 
		
		if (isset($GLOBALS['show_child_elements']) && $GLOBALS['show_child_elements']) {
			$cats = \Arlo\Categories::getTree($cat_id, null);
			
			$categories_tree = arlo_child_categories($cats);
			
			$ids = array_map(function($item) {
				return $item['id'];
			}, $categories_tree);
			
			
			if (is_array($ids) && count($ids)) {
				$where .= " OR c.c_arlo_id IN (" . implode(',', $ids) . ")";
			}
		}
		
		$where .= ')';
	} else if (!(isset($atts['show_child_elements']) && $atts['show_child_elements'] == "true")) {
		$where .= ' AND (c.c_parent_id = (SELECT c_arlo_id FROM ' . $t4 . ' WHERE c_parent_id = 0 AND active = "' . $active . '") OR c.c_parent_id IS NULL)';
	}
	
	// grouping
	$group = "GROUP BY et.et_arlo_id";
	
	// if grouping is set...
	if(isset($atts['group'])) {
		switch($atts['group']) {
			case 'category':
				$group = '';
			break;
		}
	}

	$items = $wpdb->get_results(
		"SELECT et.et_id
		FROM $t1 et 
		{$join}
		LEFT JOIN $t2 post 
			ON et.et_post_name = post.post_name 
		LEFT JOIN $t3 etc
			ON etc.et_arlo_id=et.et_arlo_id AND etc.active = et.active
		LEFT JOIN $t4 c
			ON c.c_arlo_id=etc.c_arlo_id AND c.active = etc.active
		$where
		$group
		ORDER BY et.et_name ASC", ARRAY_A);

	$num = $wpdb->num_rows;

	return arlo_pagination($num,$limit);
});

// event template list item shortcode

$shortcodes->add('event_template_list_item', function($content='', $atts, $shortcode_name){
	global $wpdb, $wp_query, $arlo_plugin;

	$settings = get_option('arlo_settings');  
	$regions = get_option('arlo_regions');
	
	if (isset($atts['show_only_at_bottom']) && $atts['show_only_at_bottom'] == "true" && isset($GLOBALS['categories_count']) && $GLOBALS['categories_count']) {
		$GLOBALS['show_only_at_bottom'] = true;
		return;
	}

	$active = $arlo_plugin->get_last_import();

	$limit = !empty($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');
	$page = !empty($_GET['paged']) ? intval($_GET['paged']) : intval(get_query_var('paged'));
	$offset = ($page > 0) ? $page * $limit - $limit: 0 ;

	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}posts";
	$t3 = "{$wpdb->prefix}arlo_eventtemplates_categories";
	$t4 = "{$wpdb->prefix}arlo_categories";
	$t5 = "{$wpdb->prefix}arlo_events";
	$t6 = "{$wpdb->prefix}arlo_eventtemplates_tags";
		
	$where = "WHERE post.post_type = 'arlo_event' AND et.active = '{$active}'";
	$join = "";
	
	$arlo_location = isset($_GET['arlo-location']) && !empty($_GET['arlo-location']) ? $_GET['arlo-location'] : get_query_var('arlo-location', '');
	$arlo_category = isset($_GET['arlo-category']) && !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : get_query_var('arlo-category', '');
	$arlo_delivery = isset($_GET['arlo-delivery']) && !empty($_GET['arlo-delivery']) ? $_GET['arlo-delivery'] : get_query_var('arlo-delivery', '');
	$arlo_templatetag = isset($_GET['arlo-templatetag']) && !empty($_GET['arlo-templatetag']) ? $_GET['arlo-templatetag'] : get_query_var('arlo-templatetag', '');
	$arlo_search = isset($_GET['arlo-search']) && !empty($_GET['arlo-search']) ? $_GET['arlo-search'] : get_query_var('arlo-search', '');
	$arlo_search = esc_sql(stripslashes(urldecode($arlo_search)));
	$arlo_region = get_query_var('arlo-region', '');
	$arlo_region = (!empty($arlo_region) && array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');
	
	if(!empty($arlo_location) || (isset($arlo_delivery) && strlen($arlo_delivery) && is_numeric($arlo_delivery)) ) :

		$join .= " LEFT JOIN $t5 e USING (et_arlo_id)";
		$where .= " AND e.e_parent_arlo_id = 0";
		
		if(!empty($arlo_location)) :
			$where .= " AND e.e_locationname = '" . urldecode($arlo_location) . "'";
		endif;	
		
		if(isset($arlo_delivery) && strlen($arlo_delivery) && is_numeric($arlo_delivery)) :
			$where .= " AND e.e_isonline = " . $arlo_delivery;
		endif;	
		
	endif;	
	
	if(!empty($arlo_templatetag) && is_numeric($arlo_templatetag)) :
		$where .= " AND ett.tag_id = '" . intval($arlo_templatetag) . "'";
		$join .= " LEFT JOIN $t6 ett USING (et_arlo_id) ";
	endif;			
	
	
	if (!empty($arlo_search)) {
		$where .= '
		AND (
				et_code like "%' . $arlo_search . '%"
			OR
				et_name like "%' . $arlo_search . '%"
			OR 
				et_descriptionsummary like "%' . $arlo_search . '%"
		)
		';
		
		$atts['show_child_elements'] = "true";
	}	
	
	if (!empty($arlo_region)) {
		$where .= ' AND et.et_region = "' . $arlo_region . '"';
	}		
			
	if(!empty($arlo_category) || !empty($atts['category'])) {

		$cat_id = 0;

		if(!empty($atts['category'])) {
			$cat_slug = $atts['category'];
		} else {
			$cat_slug = $arlo_category;
		}
		$where .= " AND ( c.c_slug = '$cat_slug'";
		
		$cat_id = $wpdb->get_var("
		SELECT
			c_arlo_id
		FROM 
			{$wpdb->prefix}arlo_categories
		WHERE 
			c_slug = '{$cat_slug}'
		");
		
		if (is_null($cat_id)) {
			$cat_id = 0;
		} 
		
		if (isset($atts['show_child_elements']) && $atts['show_child_elements'] == "true") {
			$GLOBALS['show_child_elements'] = true;
		
			$cats = \Arlo\Categories::getTree($cat_id, null);
			
			$categories_tree = arlo_child_categories($cats);
			
			$ids = array_map(function($item) {
				return $item['id'];
			}, $categories_tree);
			
			
			if (is_array($ids) && count($ids)) {
				$where .= " OR c.c_arlo_id IN (" . implode(',', $ids) . ")";
			}
		} 
		
		$where .= ')';
	} else if (!(isset($atts['show_child_elements']) && $atts['show_child_elements'] == "true")) {
		$where .= ' AND (c.c_parent_id = (SELECT c_arlo_id FROM ' . $t4 . ' WHERE c_parent_id = 0 AND active = "' . $active . '") OR c.c_parent_id IS NULL)';
	}	
		
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
	
	$sql = "SELECT et.*, post.ID as post_id, etc.c_arlo_id, c.*
		FROM $t1 et 
		{$join}
		LEFT JOIN $t2 post 
			ON et.et_post_name = post.post_name 
		LEFT JOIN $t3 etc
			ON etc.et_arlo_id=et.et_arlo_id AND etc.active = et.active
		LEFT JOIN $t4 c
			ON c.c_arlo_id=etc.c_arlo_id AND c.active = etc.active
		$where 
		$group 
		$order
		LIMIT $offset,$limit";
		
	$items = $wpdb->get_results($sql, ARRAY_A);
		
	if(empty($items)) :
		$no_event_text = !empty($settings['noevent_text']) ? $settings['noevent_text'] : __('No events to show', $GLOBALS['arlo_plugin_slug']);
		$output = '</table><style> .arlo table.event-templates {display: none;} </style><table class="arlo-no-results"><tr><td>' . $no_event_text . '</td></tr>';

	else :
					
		$output = '';
			
		$previous = null;
	
		foreach($items as $item) {
			if(isset($atts['group'])) {
				switch($atts['group']) {
					case 'category':
						if(is_null($previous) || $item['c_id'] != $previous['c_id']) {
							$item['show_divider'] = $item['c_name'];
						}
					break;
					case 'alpha':
						if(is_null($previous) || $item['et_name'][0] != $previous['et_name'][0]) {
							$item['show_divider'] = $item['et_name'][0];
						}
					break;
				}
			}
			
			$GLOBALS['arlo_eventtemplate'] = $item;
			$GLOBALS['arlo_event_list_item'] = $item;
			
			$output .= do_shortcode($content);
			unset($GLOBALS['arlo_eventtemplate']);
			unset($GLOBALS['arlo_event_list_item']);
			
			$previous = $item;
		}
	
	endif;

	return $output;
});

// event template tags shortcode

$shortcodes->add('event_template_tags', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return '';
	
	global $wpdb, $arlo_plugin;
	$output = '';
	$tags = [];
	
	$active = $arlo_plugin->get_last_import();
		
	// merge and extract attributes
	extract(shortcode_atts(array(
		'layout' => '',
	), $atts, $shortcode_name));
	
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
			ett.et_arlo_id = {$GLOBALS['arlo_eventtemplate']['et_arlo_id']}
		AND	
			t.active = '{$active}'
		AND
			ett.active = '{$active}'
		", ARRAY_A);	
	
	foreach ($items as $t) {
		$tags[] = $t['tag'];
	}
	
	switch($layout) {
		case 'list':
			$output = '<ul class="arlo-template_tags-list">';
			
			foreach($tags as $tag) {
				$output .= '<li>' . $tag . '</li>';
			}
			
			$output .= '</ul>';
		break;
	
		default:
			$output = '<div class="arlo-template_tags-list">' . implode(', ', $tags) . '</div>';
		break;
	}
	
	return $output;
});

// event template tags shortcode

$shortcodes->add('event_tags', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_arlo_id'])) return '';
	
	global $wpdb, $arlo_plugin;
	$output = '';
	$tags = [];
	
	$active = $arlo_plugin->get_last_import();
		
	// merge and extract attributes
	extract(shortcode_atts(array(
		'layout' => '',
	), $atts, $shortcode_name));
	
	$items = $wpdb->get_results("
		SELECT 
			tag
		FROM 
			{$wpdb->prefix}arlo_tags AS t
		LEFT JOIN 
			{$wpdb->prefix}arlo_events_tags AS et 
		ON
			tag_id = id
		WHERE
			et.e_arlo_id = {$GLOBALS['arlo_event_list_item']['e_arlo_id']}
		AND	
			t.active = '{$active}'
		AND
			et.active = '{$active}'
		", ARRAY_A);	
		
	foreach ($items as $t) {
		$tags[] = $t['tag'];
	}
	
	switch($layout) {
		case 'list':
			$output = '<ul class="arlo-event_tags-list">';
			
			foreach($tags as $tag) {
				$output .= '<li>' . $tag . '</li>';
			}
			
			$output .= '</ul>';
		break;
	
		default:
			$output = '<div class="arlo-event_tags-list">' . implode(', ', $tags) . '</div>';
		break;
	}
	
	return $output;
});


// event template code shortcode

$shortcodes->add('event_template_code', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_eventtemplate']['et_code'])) return '';
	
	return $GLOBALS['arlo_eventtemplate']['et_code'];
});

// event template name shortcode

$shortcodes->add('event_template_name', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_eventtemplate']['et_name'])) return '';

	return $GLOBALS['arlo_eventtemplate']['et_name'];
});

// event template permalink shortcode

$shortcodes->add('event_template_permalink', function($content='', $atts, $shortcode_name){        
	if(!isset($GLOBALS['arlo_eventtemplate']['et_post_name'])) return '';

	$et_id = arlo_get_post_by_name($GLOBALS['arlo_eventtemplate']['et_post_name'], 'arlo_event');
	
	return get_permalink($et_id);
});

// event template summary shortcode

$shortcodes->add('event_template_summary', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_eventtemplate']['et_descriptionsummary'])) return '';

	return $GLOBALS['arlo_eventtemplate']['et_descriptionsummary'];
});

// content field shortcode

$shortcodes->add('content_field_item', function($content='', $atts, $shortcode_name){
	global $post, $wpdb;
	
	extract(shortcode_atts(array(
		'fields'	=> 'all',
	), $atts, $shortcode_name));
	
	$where_fields = null;
	
	if (strtolower($fields) != 'all') {
		$where_fields = explode(',', $fields);
		$where_fields = array_map(function($field) {
			return '"' . trim($field) . '"';
		}, $where_fields);
	}
	
	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}arlo_contentfields";

	$items = $wpdb->get_results("SELECT $t2.cf_fieldname, $t2.cf_text FROM $t1 
		INNER JOIN $t2
		ON $t1.et_id = $t2.et_id
		WHERE $t1.et_post_name = '$post->post_name'
		" . (is_array($where_fields) && count($where_fields) > 0 ? " AND cf_fieldname IN (" . implode(',', $where_fields) . ") " : "") . "
		ORDER BY $t2.cf_order", ARRAY_A);

	$output = '';

	foreach($items as $item) {

		$GLOBALS['arlo_content_field_item'] = $item;

		$output .= do_shortcode($content);

		unset($GLOBALS['arlo_content_field_item']);

	}

	return $output;
});

// content field name shortcode

$shortcodes->add('content_field_name', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_content_field_item']['cf_fieldname'])) return '';

	return $GLOBALS['arlo_content_field_item']['cf_fieldname'];
});

// content field text shortcode

$shortcodes->add('content_field_text', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_content_field_item']['cf_text'])) return '';

	return wpautop($GLOBALS['arlo_content_field_item']['cf_text']);
});

// event template filter shortcode

$shortcodes->add('event_template_filters', function($content='', $atts, $shortcode_name){
	global $post, $wpdb, $arlo_plugin;

	$active = $arlo_plugin->get_last_import();

	extract(shortcode_atts(array(
		'filters'	=> 'category,location,delivery',
		'resettext'	=> __('Reset', $GLOBALS['arlo_plugin_slug']),
		'buttonclass'   => 'button'
	), $atts, $shortcode_name));
	
	$filters_array = explode(',',$filters);
	
	$settings = get_option('arlo_settings');  
        
	if (!empty($settings['post_types']['event']['posts_page'])) {
		$slug = get_post($settings['post_types']['event']['posts_page'])->post_name;
	} else {
		$slug = get_post($post)->post_name;
	}

	$filter_html = '<form id="arlo-event-filter" class="arlo-filters" method="get" action="'.site_url().'/'.$slug.'/">';
	
	foreach($filters_array as $filter) :

		switch($filter) :

			case 'category' :

				// category select
				
				$cats = \Arlo\Categories::getTree();
				
				if (is_array($cats)) {
					$filter_html .= arlo_create_filter('category', arlo_child_categories($cats[0]->children), __('All categories', $GLOBALS['arlo_plugin_slug']));
				}
				
				break;
				
			case 'delivery' :

				// delivery select

				$filter_html .= arlo_create_filter($filter, Arlo_For_Wordpress::$delivery_labels, __('All delivery options', $GLOBALS['arlo_plugin_slug']));

				break;				

			case 'location' :

				// location select

				$t1 = "{$wpdb->prefix}arlo_events";

				$items = $wpdb->get_results(
					"SELECT e.e_locationname
					FROM $t1 e 
					WHERE 
						e_locationname != ''
					GROUP BY e.e_locationname 
					ORDER BY e.e_locationname", ARRAY_A);

				$locations = array();

				foreach ($items as $item) {
					$locations[] = array(
						'string' => $item['e_locationname'],
						'value' => $item['e_locationname'],
					);
				}

				$filter_html .= arlo_create_filter($filter, $locations, __('All locations', $GLOBALS['arlo_plugin_slug']));

				break;
				
			case 'templatetag' :
				//template tag select
				
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
					WHERE 
						ett.active = '$active'
					ORDER BY tag", ARRAY_A);

				$tags = array();

				foreach ($items as $item) {
					$tags[] = array(
						'string' => $item['tag'],
						'value' => $item['id'],
					);
				}

				$filter_html .= arlo_create_filter($filter, $tags, __('Select tag', $GLOBALS['arlo_plugin_slug']));				
				
				break;

		endswitch;

	endforeach;	
        
	// category select


	$filter_html .= '<div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' . $slug . '">';
        
	$filter_html .= '<a href="'.get_page_link().'" class="' . $buttonclass . '">'.$resettext.'</a></div>';

	$filter_html .= '</form>';
	
	return $filter_html;

});

// event list item shortcode

$shortcodes->add('event_list_item', function($content='', $atts, $shortcode_name){
	global $post, $wpdb;
	$settings = get_option('arlo_settings');
	
	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}arlo_events";
	$t3 = "{$wpdb->prefix}arlo_venues";
	$t4 = "{$wpdb->prefix}arlo_events_presenters";
	$t5 = "{$wpdb->prefix}arlo_presenters";
	$t6 = "{$wpdb->prefix}arlo_offers";
	
	$sql = 
		"SELECT $t2.*, $t3.v_post_name FROM $t2
		LEFT JOIN $t3
		ON $t2.v_id = $t3.v_arlo_id
		LEFT JOIN $t1
		ON $t2.et_arlo_id = $t1.et_arlo_id
		WHERE $t1.et_post_name = '$post->post_name'
		AND $t2.e_parent_arlo_id = 0
		ORDER BY $t2.e_startdatetime";
		
	$items = $wpdb->get_results($sql, ARRAY_A);
	
	$output = '';
	
	if (is_array($items) && count($items)) {
		foreach($items as $key => $item) {
	
			$GLOBALS['arlo_event_list_item'] = $item;
	                
			if (!empty($atts['show']) && $key == $atts['show']) {
			    $output .= '</ul><div class="arlo-clear-both"></div><ul class="arlo-list arlo-show-more-hidden events">';
			}
	
			$output .= do_shortcode($content);
	
			unset($GLOBALS['arlo_event_list_item']);
		}	
	} else {
		$no_event_text = !empty($settings['noeventontemplate_text']) ? $settings['noeventontemplate_text'] : __('Interested in attending? Have a suggestion about running this course near you?', $GLOBALS['arlo_plugin_slug']);
		
		if (!empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi'])) {
			$no_event_text .= '<br /><a href="' . $GLOBALS['arlo_eventtemplate']['et_registerinteresturi'] . '">Register your interest now</a>';
		}
		
		$output = '
		<p class="arlo-no-results">' . 
			$no_event_text . 
		'</p>';
	}
	
	
	return $output;
});


// event code shortcode

$shortcodes->add('event_code', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_code'])) return '';

	return $GLOBALS['arlo_event_list_item']['e_code'];
});


// event name shortcode

$shortcodes->add('event_name', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_name']) && !isset($GLOBALS['arlo_event_session_list_item']['e_code'])) return '';
	
	$event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

	return $event['e_name'];
});

// event location shortcode

$shortcodes->add('event_location', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_locationname']) && !isset($GLOBALS['arlo_event_session_list_item']['e_locationname'])) return '';
	
	$event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];

	$location = $event['e_locationname'];

	if($event['e_isonline'] || $event['v_id'] == 0 || $event['e_locationvisible'] == 0) {

		return $location;

	} else {

		$permalink = get_permalink(arlo_get_post_by_name($event['v_post_name'], 'arlo_venue'));

		return '<a href="'.$permalink.'">'.$location.'</a>';

	}
	
});

// event start date shortcode

$shortcodes->add('event_start_date', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_startdatetime']) && !isset($GLOBALS['arlo_event_session_list_item']['e_startdatetime'])) return '';
	
	$timezone = null;
	
	$event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];
	
	$timewithtz = str_replace(' ', 'T', $event['e_startdatetime']) . $event['e_datetimeoffset'];
	
	$start_date = new DateTime($timewithtz);
		
	if($event['e_isonline']) {
		if (!empty($GLOBALS['selected_timezone_olson_names']) && is_array($GLOBALS['selected_timezone_olson_names'])) {
			foreach ($GLOBALS['selected_timezone_olson_names'] as $TzName) {
				try {
					$timezone = new DateTimeZone($TzName->olson_name);
				} catch (Exception $e) {
				}
				
				if ($timezone !== null) {
					break;
				}
			}
			
			if (!is_null($timezone)) {
				$start_date->setTimezone($timezone);
			}
			
		}
	}

	$format = 'D g:i A';

	if(isset($atts['format'])) $format = $atts['format'];
	
	//if we haven't got timezone, we need to append the timezone abbrev
	if ($event['e_isonline'] && is_null($timezone) && (preg_match('[G|g|i]', $format) === 1)) {
		$format .= " T";
	}

	return $start_date->format($format);
});

// event end date shortcode

$shortcodes->add('event_end_date', function($content='', $atts, $shortcode_name){	
	if(!isset($GLOBALS['arlo_event_list_item']['e_finishdatetime']) && !isset($GLOBALS['arlo_event_session_list_item']['e_finishdatetime'])) return '';
	
	$event = !empty($GLOBALS['arlo_event_session_list_item']) ? $GLOBALS['arlo_event_session_list_item'] : $GLOBALS['arlo_event_list_item'];
	
	$timezone = null;

	$timewithtz = str_replace(' ','T',$event['e_finishdatetime']) . $event['e_datetimeoffset'];
	
	$end_date = new DateTime($timewithtz);
		
	if($event['e_isonline']) {
		if (!empty($GLOBALS['selected_timezone_olson_names']) && is_array($GLOBALS['selected_timezone_olson_names'])) {
			foreach ($GLOBALS['selected_timezone_olson_names'] as $TzName) {
				try {
					$timezone = new DateTimeZone($TzName->olson_name);
				} catch (Exception $e) {
				}
				
				if ($timezone !== null) {
					break;
				}
			}
			
			if (!is_null($timezone)) {
				$end_date->setTimezone($timezone);
			}
			
		}
	}

	$format = 'D g:i A';

	if(isset($atts['format'])) $format = $atts['format'];
		
	//if we haven't got timezone, we need to append the timezone abbrev
	if ($event['e_isonline'] && is_null($timezone) && (preg_match('[G|g|i]', $format) === 1)) {
		$format .= " T";
	}	

	return $end_date->format($format);
});

// event session description shortcode

$shortcodes->add('event_session_description', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_sessiondescription'])) return '';

	return $GLOBALS['arlo_event_list_item']['e_sessiondescription'];
});

// event registration shortcode

$shortcodes->add('event_registration', function($content='', $atts, $shortcode_name){
	$isfull = $GLOBALS['arlo_event_list_item']['e_isfull'];
	$registeruri = $GLOBALS['arlo_event_list_item']['e_registeruri'];
	$registermessage = $GLOBALS['arlo_event_list_item']['e_registermessage'];
	$placesremaining = $GLOBALS['arlo_event_list_item']['e_placesremaining'];
        
	$class = (!empty($atts['class']) ? $atts['class'] : 'button' );

	$registration = '<div class="arlo-event-registration">';
	$registration .= (($isfull) ? '<span class="arlo-event-full">' . __('Event is full', $GLOBALS['arlo_plugin_slug']) . '</span>' : '');
	// test if there is a register uri string, if so display the button
	if(!is_null($registeruri) && $registeruri != '') {
		$registration .= '<a class="' . $class . ' ' . (($isfull) ? 'arlo-waiting-list' : 'arlo-register') . '" href="'. $registeruri . '" target="_blank">';
		$registration .= (($isfull) ? __('Join waiting list', $GLOBALS['arlo_plugin_slug']) : __($registermessage, $GLOBALS['arlo_plugin_slug'])) . '</a>';
	} else {
		$registration .= $registermessage;
	}

	if ($placesremaining > 0) {
		$registration .= '<span class="arlo-places-remaining">' . sprintf( _n( '%d place remaining', '%d places remaining', $placesremaining, $GLOBALS['arlo_plugin_slug'] ), $placesremaining ) .'</span>';	
	}
	
	$registration .= '</div>';

	return $registration;
});

// event offers shortcode

$shortcodes->add('event_offers', function($content='', $atts, $shortcode_name){
	global $wpdb;

	$e_id = $GLOBALS['arlo_event_list_item']['e_id'];

	$t1 = "{$wpdb->prefix}arlo_offers";

	$offers_array = $wpdb->get_results(
		"SELECT offer.*,
		replaced_by.o_label AS replacement_label,
		replaced_by.o_isdiscountoffer AS replacement_discount,
		replaced_by.o_currencycode AS replacement_currency_code,
		replaced_by.o_formattedamounttaxexclusive AS replacement_amount,
		replaced_by.o_message AS replacement_message
		FROM `wp_arlo_offers` AS offer
		LEFT JOIN `wp_arlo_offers` AS replaced_by ON offer.o_arlo_id = replaced_by.o_replaces AND offer.e_id = replaced_by.e_id
		WHERE offer.o_replaces = 0 AND offer.e_id = $e_id
		ORDER BY offer.o_order", ARRAY_A);

	$offers = '<ul class="arlo-list arlo-event-offers">';
        
        $settings = get_option('arlo_settings');  
        $price_setting = (isset($settings['price_setting'])) ? esc_attr($settings['price_setting']) : ARLO_PLUGIN_PREFIX . '-exclgst';      
        $free_text = (isset($settings['free_text'])) ? esc_attr($settings['free_text']) : __('Free', $GLOBALS['arlo_plugin_slug']);


	foreach($offers_array as $offer) {

		extract($offer);
                
		$amount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $o_offeramounttaxexclusive : $o_offeramounttaxinclusive;
		$famount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $o_formattedamounttaxexclusive : $o_formattedamounttaxinclusive;

		// set to true if there is a replacement offer returned for this event offer
		$replaced = (!is_null($replacement_amount) && $replacement_amount != '');

		$offers .= '<li><span';
		// if the offer is discounted
		if($o_isdiscountoffer) {
			$offers .= ' class="discount"';
		// if the offer is replace by another offer
		} elseif($replaced) {
			$offers .= ' class="replaced"';
		}
		$offers .= '>';
		// display label if there is one
		$offers .= (!is_null($o_label) || $o_label != '') ? $o_label.' ':'';
		if($amount > 0) {
			$offers .= '<span class="amount">'.$famount.'</span> ';
			// only include the excl. tax if the offer is not replaced			
			$offers .= $replaced ? '' : '<span class="arlo-price-tax">' . ($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__('excl. %s', $GLOBALS['arlo_plugin_slug']), $o_taxrateshortcode) : sprintf(__('incl. %s', $GLOBALS['arlo_plugin_slug']), $o_taxrateshortcode) . '</span>');
		} else {
			$offers .= '<span class="amount free">'.$free_text.'</span> ';
		}
		// display message if there is one
		$offers .= (!is_null($o_message) || $o_message != '') ? ' '.$o_message:'';
		// if a replacement offer exists
		if($replaced) {
			$offers .= '</span><span ' . $replacement_discount ? 'class="discount"' : '' . '>';
			
			// display replacement offer label if there is one
			$offers .= (!is_null($replacement_label) || $replacement_label != '') ? $replacement_label.' ':'';
			$offers .= '<span class="amount">'.$replacement_amount.'</span> <span class="arlo-price-tax">'.($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__('excl. %s', $GLOBALS['arlo_plugin_slug']), $o_taxrateshortcode) : sprintf(__('incl. %s', $GLOBALS['arlo_plugin_slug']), $o_taxrateshortcode)) . '</span>';
			// display replacement offer message if there is one
			$offers .= (!is_null($replacement_message) || $replacement_message != '') ? ' '.$replacement_message:'';

		} // end if

		$offers .= '</span></li>';

	} // end foreach

	$offers .= '</ul>';

	return $offers;
});

// event presenters shortcode

$shortcodes->add('event_presenters', function($content='', $atts, $shortcode_name){
	global $wpdb, $arlo_plugin;

	$e_arlo_id = $GLOBALS['arlo_event_list_item']['e_arlo_id'];
		
	$active = $arlo_plugin->get_last_import();

	$t1 = "{$wpdb->prefix}arlo_events_presenters";
	$t2 = "{$wpdb->prefix}arlo_presenters";

	$items = $wpdb->get_results("SELECT p.p_firstname, p.p_lastname, p.p_post_name FROM $t1 exp 
			INNER JOIN $t2 p
			ON exp.p_arlo_id = p.p_arlo_id AND exp.active = p.active
		WHERE exp.e_arlo_id = $e_arlo_id AND p.active = '$active'
		GROUP BY p.p_arlo_id ORDER BY exp.p_order", ARRAY_A);

	// merge and extract attributes
	extract(shortcode_atts(array(
		'layout' => '',
		'link' => 'true'
	), $atts, $shortcode_name));

	$np = count($items);

	$output = '';

	if($link === 'false') $link = false;

	if($layout == 'list') {

		$output .= '<figure>';
		$output .= '<figcaption class="arlo-event-presenters-title">'._n('Presenter', 'Presenters', $np, $GLOBALS['arlo_plugin_slug']).'</figcaption>';
		$output .= '<ul class="arlo-list event-presenters">';

		foreach($items as $item) {

			$permalink = get_permalink(arlo_get_post_by_name($item['p_post_name'], 'arlo_presenter'));

			$link_start = ($link) ? '<a href="'.$permalink.'">' : '' ;

			$link_end = ($link) ? '</a>' : '' ;

			$output .= '<li>'.$link_start.$item['p_firstname'].' '.$item['p_lastname'].$link_end.'</li>';

		}

		$output .= '</ul>';
		$output .= '</figure>';

	} else {

		$presenters = array();

		foreach($items as $item) {

			$permalink = get_permalink(arlo_get_post_by_name($item['p_post_name'], 'arlo_presenter'));

			$link_start = ($link) ? '<a href="'.$permalink.'">' : '' ;

			$link_end = ($link) ? '</a>' : '' ;

			$presenters[] = $link_start.$item['p_firstname'].' '.$item['p_lastname'].$link_end;

		}

		$output .= implode(', ', $presenters);

	}

	return $output;
});

// event provider shortcode

$shortcodes->add('event_provider', function($content='', $atts, $shortcode_name){
	global $wpdb, $arlo_plugin;

	$e_arlo_id = $GLOBALS['arlo_event_list_item']['e_arlo_id'];
		
	if (!empty($GLOBALS['arlo_event_list_item']['e_providerwebsite'])) {
		$output = '<a href="' . $GLOBALS['arlo_event_list_item']['e_providerwebsite'] . '" target="_blank">' . $GLOBALS['arlo_event_list_item']['e_providerorganisation'] . "</a>";
	} else {
		$output = $GLOBALS['arlo_event_list_item']['e_providerorganisation'];
	}	

	return $output;
});

// event delivery shortcode

$shortcodes->add('event_delivery', function($content='', $atts, $shortcode_name){
	global $wpdb, $arlo_plugin;

	$e_arlo_id = $GLOBALS['arlo_event_list_item']['e_arlo_id'];
	
	$output = Arlo_For_Wordpress::$delivery_labels[$GLOBALS['arlo_event_list_item']['e_isonline']];

	return $output;
});


//session 

// event list item shortcode

$shortcodes->add('event_session_list_item', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_arlo_id'])) return '';
	global $post, $wpdb;
	
	$output = '';
	
	extract(shortcode_atts(array(
		'label'	=> __('Session information', $GLOBALS['arlo_plugin_slug']),
		'header' => __('Sessions', $GLOBALS['arlo_plugin_slug']),
	), $atts, $shortcode_name));	
	
	$sql = "
		SELECT 
			e_name, 
			e_locationname,
			e_locationvisible,
			e_startdatetime,
			e_finishdatetime,
			e_datetimeoffset,
			e_isonline,
			0 AS v_id
		FROM
			{$wpdb->prefix}arlo_events
		WHERE 
			e_parent_arlo_id = {$GLOBALS['arlo_event_list_item']['e_arlo_id']}
		ORDER BY 
			e_startdatetime";
					
	$items = $wpdb->get_results($sql, ARRAY_A);
	if (is_array($items) && count($items)) {
		$output .= '<div data-tooltip="#' . ARLO_PLUGIN_PREFIX . '_session_tooltip_' . $GLOBALS['arlo_event_list_item']['e_arlo_id'] . '" class="' . ARLO_PLUGIN_PREFIX . '-tooltip-button">'.$label.'</div>
		<div class="' . ARLO_PLUGIN_PREFIX . '-tooltip-html" id="' . ARLO_PLUGIN_PREFIX . '_session_tooltip_' . $GLOBALS['arlo_event_list_item']['e_arlo_id'] . '"><h5>' . $header . '</h5>';
		
		foreach($items as $key => $item) {
	
			$GLOBALS['arlo_event_session_list_item'] = $item;
			
			$output .= do_shortcode($content);
			
			unset($GLOBALS['arlo_event_session_list_item']);
		}
		
		$output .= '</div>';	
	}
	
	return $output;
});




$shortcodes->add('suggest_templates', function($content='', $atts, $shortcode_name){
	global $wpdb, $wp_query, $arlo_plugin;
	if (empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return '';

	$settings = get_option('arlo_settings');  
	
	extract(shortcode_atts(array(
		'limit'	=> 5,
		'base' => 'category',
		'tagprefix'	=> 'group_',
		'onlyscheduled' => 'false'
	), $atts, $shortcode_name));
	
	$active = $arlo_plugin->get_last_import();
	
	switch ($base) {
		case 'tag': 
			//select the tag_id associated with the template and starts with the prefix
			
			$where = "
			t.tag_id IN (SELECT 
							GROUP_CONCAT(ett.tag_id)
						FROM 
							{$wpdb->prefix}arlo_eventtemplates_tags AS ett
						LEFT JOIN 
							{$wpdb->prefix}arlo_tags AS t
						ON
							ett.tag_id = t.id AND t.active = '{$active}'
						WHERE
							t.tag LIKE '{$tagprefix}%'
						AND
							ett.active = '{$active}'
						AND
							ett.et_arlo_id = {$GLOBALS['arlo_eventtemplate']['et_arlo_id']}
						)
			";
			
			$join = "		
			LEFT JOIN 
				{$wpdb->prefix}arlo_eventtemplates_tags AS t
			USING
				(et_arlo_id)
			";
		break;
		default:
			//select the categories associated with the template
			$where = "
			c.c_arlo_id IN (SELECT 
							GROUP_CONCAT(ecc.c_arlo_id)
						FROM 
							{$wpdb->prefix}arlo_eventtemplates_categories AS ecc
						WHERE
							ecc.active = '{$active}'
						AND
							ecc.et_arlo_id = {$GLOBALS['arlo_eventtemplate']['et_arlo_id']}
						)
			";		
		
			$join = "
			LEFT JOIN 
				{$wpdb->prefix}arlo_eventtemplates_categories AS c
			USING
				(et_arlo_id)
			";			
		break;
	}
		
	if ($onlyscheduled === "true") {
		$join .= "
		INNER JOIN 
			{$wpdb->prefix}arlo_events
		USING 
			(et_arlo_id)
		";
	} 
	
	$sql = "
		SELECT 
			et.et_arlo_id,
			et.et_code,
			et.et_name,
			et.et_descriptionsummary,
			et.et_post_name,
			et.et_registerinteresturi 
		FROM 
			{$wpdb->prefix}arlo_eventtemplates AS et
		{$join}
		WHERE 
			et.active = '{$active}'
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
});


// upcoming event list region selector shortcode

$shortcodes->add('upcoming_region_selector', function($content='', $atts, $shortcode_name){
	return arlo_create_region_selector("upcoming");
});

// upcoming event list shortcode

$shortcodes->add('upcoming_list', function($content='', $atts, $shortcode_name){
	$templates = arlo_get_option('templates');
	$content = $templates['upcoming']['html'];
	return do_shortcode($content);
});

// upcoming event list pagination shortcode

$shortcodes->add('upcoming_list_pagination', function($content='', $atts, $shortcode_name){
	global $wpdb, $wp_query;
	
	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');

	$t1 = "{$wpdb->prefix}arlo_events";
	$t2 = "{$wpdb->prefix}arlo_eventtemplates";
	$t3 = "{$wpdb->prefix}arlo_venues";
	$t4 = "{$wpdb->prefix}arlo_offers";
	$t5 = "{$wpdb->prefix}arlo_eventtemplates_categories";
	$t6 = "{$wpdb->prefix}arlo_categories";
	$t7 = "{$wpdb->prefix}arlo_events_tags";

	$where = 'WHERE CURDATE() < DATE(e.e_startdatetime) AND e_parent_arlo_id = 0 ';
	$join = '';
		
	$arlo_month = isset($_GET['arlo-month']) && !empty($_GET['arlo-month']) ? $_GET['arlo-month'] : get_query_var('arlo-month', '');
	$arlo_location = isset($_GET['arlo-location']) && !empty($_GET['arlo-location']) ? $_GET['arlo-location'] : get_query_var('arlo-location', '');
	$arlo_category = isset($_GET['arlo-category']) && !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : get_query_var('arlo-category', '');
	$arlo_delivery = isset($_GET['arlo-delivery']) && !empty($_GET['arlo-delivery']) ? $_GET['arlo-delivery'] : get_query_var('arlo-delivery', '');
	$arlo_eventtag = isset($_GET['arlo-eventtag']) && !empty($_GET['arlo-eventtag']) ? $_GET['arlo-eventtag'] : get_query_var('arlo-eventtag', '');

	if(!empty($arlo_month)) :
		$dates = explode(':',urldecode($arlo_month));
		$where .= " AND (DATE(e.e_startdatetime) BETWEEN DATE('$dates[0]')";
		$where .= " AND DATE('$dates[1]'))";

	endif;
	if(!empty($arlo_location)) :
		$where .= " AND e.e_locationname = '" . urldecode($arlo_location) . "'";
	endif;

	if(!empty($arlo_category)) :
		$where .= " AND c.c_arlo_id = " . intval(current(explode('-', $arlo_category)));
	endif;
	
	if(!empty($arlo_delivery) && is_numeric($arlo_delivery)) :
		$where .= " AND e.e_isonline = " . intval($arlo_delivery);
	endif;	
	
	if(!empty($arlo_eventtag) && is_numeric($arlo_eventtag)) :
		$where .= " AND etag.tag_id = '" . intval($arlo_eventtag) . "'";
		$join .= " LEFT JOIN $t7 etag USING (e_arlo_id) ";
	endif;		

	$items = $wpdb->get_results(
		"SELECT DISTINCT e.e_id, e.e_locationname, c.c_arlo_id
		FROM $t1 e 
		LEFT JOIN $t2 et 
		ON e.et_arlo_id = et.et_arlo_id 
		LEFT JOIN $t3 v
		ON e.v_id = v.v_arlo_id
		INNER JOIN 
		(SELECT * 
		FROM $t4
		WHERE o_order = 1
		) o
		ON e.e_id = o.e_id
		LEFT JOIN $t5 etc
		ON et.et_arlo_id = etc.et_arlo_id AND et.active = etc.active
		LEFT JOIN $t6 c
		ON c.c_arlo_id = etc.c_arlo_id
		$join
		$where
		GROUP BY etc.et_arlo_id, e.e_id
		ORDER BY e.e_startdatetime", ARRAY_A);

	$num = $wpdb->num_rows;

	return arlo_pagination($num,$limit);
});

// upcoming event list item shortcode

$shortcodes->add('upcoming_list_item', function($content='', $atts, $shortcode_name){
	global $wpdb;
	$settings = get_option('arlo_settings');
	$regions = get_option('arlo_regions');

	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');
	$page = !empty($_GET['paged']) ? intval($_GET['paged']) : intval(get_query_var('paged'));
	$offset = ($page > 0) ? $page * $limit - $limit: 0 ;

	$output = '';

	$t1 = "{$wpdb->prefix}arlo_events";
	$t2 = "{$wpdb->prefix}arlo_eventtemplates";
	$t3 = "{$wpdb->prefix}arlo_venues";
	$t4 = "{$wpdb->prefix}arlo_offers";
	$t5 = "{$wpdb->prefix}arlo_eventtemplates_categories";
	$t6 = "{$wpdb->prefix}arlo_categories";
	$t7 = "{$wpdb->prefix}arlo_events_tags";

	$where = 'WHERE CURDATE() < DATE(e.e_startdatetime)  AND e_parent_arlo_id = 0 ';
	$join = '';

	$arlo_month = isset($_GET['arlo-month']) && !empty($_GET['arlo-month']) ? $_GET['arlo-month'] : get_query_var('arlo-month', '');
	$arlo_location = isset($_GET['arlo-location']) && !empty($_GET['arlo-location']) ? $_GET['arlo-location'] : get_query_var('arlo-location', '');
	$arlo_category = isset($_GET['arlo-category']) && !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : get_query_var('arlo-category', '');
	$arlo_delivery = isset($_GET['arlo-delivery']) && !empty($_GET['arlo-delivery']) ? $_GET['arlo-delivery'] : get_query_var('arlo-delivery', '');
	$arlo_eventtag = isset($_GET['arlo-eventtag']) && !empty($_GET['arlo-eventtag']) ? $_GET['arlo-eventtag'] : get_query_var('arlo-eventtag', '');
	$arlo_region = get_query_var('arlo-region', '');
	$arlo_region = (!empty($arlo_region) && array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');
	

	if(!empty($arlo_month)) :
		$dates = explode(':',urldecode($arlo_month));
		$where .= " AND (DATE(e.e_startdatetime) BETWEEN DATE('$dates[0]')";
		$where .= " AND DATE('$dates[1]'))";

	endif;
	if(!empty($arlo_location)) :
		$where .= " AND e.e_locationname = '" . urldecode($arlo_location) . "'";
	endif;

	if(!empty($arlo_category)) :
		$where .= " AND c.c_arlo_id = " . intval(current(explode('-', $arlo_category)));
	endif;
	
	if(!empty($arlo_delivery)) :
		$where .= " AND e.e_isonline = " . intval($arlo_delivery);
	endif;	
	
	if(!empty($arlo_eventtag)) :
		$where .= " AND etag.tag_id = '" . intval($arlo_eventtag) . "'";
		$join .= " LEFT JOIN $t7 etag USING (e_arlo_id) ";
	endif;	
	
	if (!empty($arlo_region)) {
		$where .= ' AND et.et_region = "' . $arlo_region . '" AND e.e_region = "' . $arlo_region . '"';
	}		
	
	$sql = "SELECT DISTINCT
		e.*, et.et_name, et.et_post_name, et.et_descriptionsummary, et.et_registerinteresturi, o.o_formattedamounttaxexclusive, o_offeramounttaxexclusive, o.o_formattedamounttaxinclusive, o_offeramounttaxinclusive, o.o_taxrateshortcode, v.v_post_name, c.c_arlo_id
		FROM $t1 e 
		LEFT JOIN $t2 et 
		ON e.et_arlo_id = et.et_arlo_id 
		LEFT JOIN $t3 v
		ON e.v_id = v.v_arlo_id
		INNER JOIN 
		(SELECT * 
		FROM $t4
		WHERE o_order = 1
		) o
		ON e.e_id = o.e_id
		LEFT JOIN $t5 etc
		ON et.et_arlo_id = etc.et_arlo_id AND et.active = etc.active
		LEFT JOIN $t6 c
		ON c.c_arlo_id = etc.c_arlo_id
		$join
		$where
	    GROUP BY etc.et_arlo_id, e.e_id
		ORDER BY e.e_startdatetime
		LIMIT $offset, $limit";
		
	$items = $wpdb->get_results($sql, ARRAY_A);

	if(empty($items)) :
	
		$no_event_text = !empty($settings['noevent_text']) ? $settings['noevent_text'] : __('No events to show', $GLOBALS['arlo_plugin_slug']);
		$output = '<p class="arlo-no-results">' . $no_event_text . '</p>';
		
	else :

		$previous = null;
		foreach($items as $item) {

			if(is_null($previous) || date('m',strtotime($item['e_startdatetime'])) != date('m',strtotime($previous['e_startdatetime']))) {

				$item['show_divider'] = date('F', strtotime($item['e_startdatetime']));

			}

			$GLOBALS['arlo_event_list_item'] = $item;
			$GLOBALS['arlo_eventtemplate'] = $item;

			$output .= do_shortcode($content);

			unset($GLOBALS['arlo_event_list_item']);
			unset($GLOBALS['arlo_eventtemplate']);

			$previous = $item;

		}

	endif;

	return $output;
});

// upcoming event offer shortcode

$shortcodes->add('upcoming_offer', function($content='', $atts, $shortcode_name){
	$settings = get_option('arlo_settings');  
	$price_setting = (isset($settings['price_setting'])) ? esc_attr($settings['price_setting']) : ARLO_PLUGIN_PREFIX . '-exclgst';
	$free_text = (isset($settings['free_text'])) ? esc_attr($settings['free_text']) : __('Free', $GLOBALS['arlo_plugin_slug']);
            
	$amount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $GLOBALS['arlo_event_list_item']['o_offeramounttaxexclusive'] : $GLOBALS['arlo_event_list_item']['o_offeramounttaxinclusive'];
	$famount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $GLOBALS['arlo_event_list_item']['o_formattedamounttaxexclusive'] : $GLOBALS['arlo_event_list_item']['o_formattedamounttaxinclusive'];
	$tax = $GLOBALS['arlo_event_list_item']['o_taxrateshortcode'];

	$offer = ($amount > 0) ? '<span class="arlo-amount">'.$famount .'</span> <span class="arlo-price-tax">'. ($price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf(__(' excl. %s', $GLOBALS['arlo_plugin_slug']), $tax) : sprintf(__(' incl. %s', $GLOBALS['arlo_plugin_slug']), $tax)) . '</span>' : '<span class="arlo-amount">'.$free_text.'</span>';

	return $offer;
});

// upcoming event filters

$shortcodes->add('upcoming_event_filters', function($content='', $atts, $shortcode_name){
	global $post, $wpdb, $arlo_plugin;

	$active = $arlo_plugin->get_last_import();
	
	extract(shortcode_atts(array(
		'filters'	=> 'category,month,location,delivery',
		'resettext'	=> __('Reset', $GLOBALS['arlo_plugin_slug']),
		'buttonclass'   => 'button'
	), $atts, $shortcode_name));

	$filters_array = explode(',',$filters);
	
	$settings = get_option('arlo_settings');  
		
	if (!empty($settings['post_types']['upcoming']['posts_page'])) {
		$slug = get_post($settings['post_types']['upcoming']['posts_page'])->post_name;
	} else {
		$slug = get_post($post)->post_name;
	}
	
	$filter_html = '<form class="arlo-filters" method="get" action="'.site_url().'/'.$slug.'">';

	foreach($filters_array as $filter) :

		switch($filter) :

			case 'category' :

				// category select

				$cats = \Arlo\Categories::getTree();

				if (is_array($cats)) {
					$filter_html .= arlo_create_filter($filter, arlo_child_categories($cats[0]->children), __('All categories', $GLOBALS['arlo_plugin_slug']));					
				}

				break;
				
			case 'delivery' :

				// delivery select

				$filter_html .= arlo_create_filter($filter, Arlo_For_Wordpress::$delivery_labels, __('All delivery options', $GLOBALS['arlo_plugin_slug']));

				break;
								

			case 'month' :

				// month select

				$months = array();

				$currentMonth = (int)date('m');

				for ($x = $currentMonth; $x < $currentMonth + 12; $x++) {
					$date = mktime(0, 0, 0, $x, 1);
					$months[$x]['string'] = date('F', $date);
					$months[$x]['value'] = date('Ym01', $date) . ':' . date('Ymt', $date);

				}

				$filter_html .= arlo_create_filter($filter, $months, __('All months', $GLOBALS['arlo_plugin_slug']));

				break;

			case 'location' :

				// location select

				$t1 = "{$wpdb->prefix}arlo_events";

				$items = $wpdb->get_results(
					"SELECT e.e_locationname
					FROM $t1 e 
					WHERE 
						e_locationname != ''
					GROUP BY e.e_locationname 
					ORDER BY e.e_locationname", ARRAY_A);

				$locations = array();

				foreach ($items as $item) {
					$locations[] = array(
						'string' => $item['e_locationname'],
						'value' => $item['e_locationname'],
					);
				}

				$filter_html .= arlo_create_filter($filter, $locations, __('All locations', $GLOBALS['arlo_plugin_slug']));

				break;
				
			case 'eventtag' :
				//event tag select
				
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
					WHERE 
						etag.active = '$active'
					ORDER BY tag", ARRAY_A);

				$tags = array();

				foreach ($items as $item) {
					$tags[] = array(
						'string' => $item['tag'],
						'value' => $item['id'],
					);
				}

				$filter_html .= arlo_create_filter($filter, $tags, __('Select tag', $GLOBALS['arlo_plugin_slug']));				
				
				break;				

		endswitch;

	endforeach;

	$filter_html .= '<div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' . $slug . '"> ';    
	$filter_html .= '<a href="'.get_page_link().'" class="' . $buttonclass . '">'.$resettext.'</a></div>';

	$filter_html .= '</form>';
	
	return $filter_html;
});

// venue list shortcode

$shortcodes->add('venue_list', function($content='', $atts, $shortcode_name){
	$templates = arlo_get_option('templates');
	$content = $templates['venues']['html'];
	return do_shortcode($content);
});

// venue list pagination shortcode

$shortcodes->add('venue_list_pagination', function($content='', $atts, $shortcode_name){
	global $wpdb;
	
	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');

	$t1 = "{$wpdb->prefix}arlo_venues";
	$t2 = "{$wpdb->prefix}posts";

	$items = $wpdb->get_results(
		"SELECT v.v_id
		FROM $t1 v 
		LEFT JOIN $t2 post 
		ON v.v_post_name = post.post_name 
		WHERE post.post_type = 'arlo_venue'
		ORDER BY v.v_name ASC", ARRAY_A);

	$num = $wpdb->num_rows;

	return arlo_pagination($num,$limit);
});

// venue list item shortcode

$shortcodes->add('venue_list_item', function($content='', $atts, $shortcode_name){
	global $wpdb;

	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');
	$offset = (get_query_var('paged') && intval(get_query_var('paged')) > 0) ? intval(get_query_var('paged')) * $limit - $limit: 0 ;

	$t1 = "{$wpdb->prefix}arlo_venues";
	$t2 = "{$wpdb->prefix}posts";

	$items = $wpdb->get_results(
		"SELECT v.*, post.ID as post_id
		FROM $t1 v 
		LEFT JOIN $t2 post 
		ON v.v_post_name = post.post_name 
		WHERE post.post_type = 'arlo_venue'
		ORDER BY v.v_name ASC
		LIMIT $offset, $limit", ARRAY_A);

	$output = '';

	foreach($items as $item) {

		$GLOBALS['arlo_venue_list_item'] = $item;

		$output .= do_shortcode($content);

		unset($GLOBALS['arlo_venue_list_item']);

	}

	return $output;
});

// venue name shortcode

$shortcodes->add('venue_name', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_venue_list_item']['v_name'])) return '';

	return $GLOBALS['arlo_venue_list_item']['v_name'];
});

// venue permalink shortcode

$shortcodes->add('venue_permalink', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_venue_list_item']['post_id'])) return '';

	return get_permalink($GLOBALS['arlo_venue_list_item']['post_id']);
});

// venue map shortcode

$shortcodes->add('venue_map', function($content='', $atts, $shortcode_name){
	// merge and extract attributes
	extract(shortcode_atts(array(
		'height'	=> 400,
		'width'  	=> 400,
		'zoom'		=> 16
	), $atts, $shortcode_name));

	$name = $GLOBALS['arlo_venue_list_item']['v_name'];
	$lat = $GLOBALS['arlo_venue_list_item']['v_geodatapointlatitude'];
	$long = $GLOBALS['arlo_venue_list_item']['v_geodatapointlongitude'];

	if($lat != 0 || $long != 0) {

		if(intval($height) <= 0) $height = 400;
		if(intval($width) <= 0) $width = 400;


		$map = '<img src="https://maps.googleapis.com/maps/api/staticmap?markers=color:green%7C';
		$map .= $lat . ',' . $long;
		$map .= '&size=' . $width . 'x' . $height;
		$map .= '&zoom=' . $zoom . '"';
		$map .= ' height="' . $height . '"';
		$map .= ' width="' . $width . '"';
		$map .= ' alt="' .sprintf(__('Map of %s', $GLOBALS['arlo_plugin_slug']), $name) . '"'; 
		$map .= ' />';

		return $map;
	}
});

// venue address shortcode

$shortcodes->add('venue_address', function($content='', $atts, $shortcode_name){
	// merge and extract attributes
	extract(shortcode_atts(array(
		'layout' => 'list',
		'items' => 'line1,line2,line3,line4,suburb,city,state,post_code,country'
	), $atts, $shortcode_name));
	
	$items = str_replace(' ', '', $items);
	$items = explode(',', $items);
	
	//consrtuct array
	$address = array(
		'line1' => $GLOBALS['arlo_venue_list_item']['v_physicaladdressline1'],
		'line2' => $GLOBALS['arlo_venue_list_item']['v_physicaladdressline2'],
		'line3' => $GLOBALS['arlo_venue_list_item']['v_physicaladdressline3'],
		'line4' => $GLOBALS['arlo_venue_list_item']['v_physicaladdressline4'],
		'suburb' => $GLOBALS['arlo_venue_list_item']['v_physicaladdresssuburb'],
		'city' => $GLOBALS['arlo_venue_list_item']['v_physicaladdresscity'],
		'state' => $GLOBALS['arlo_venue_list_item']['v_physicaladdressstate'],
		'post_code' => $GLOBALS['arlo_venue_list_item']['v_physicaladdresspostcode'],
		'country' => $GLOBALS['arlo_venue_list_item']['v_physicaladdresscountry'],
	);
	
	// check if we want to show all items
	foreach($address as $key => $value) {
		$value = trim($value);
		if(!in_array($key, $items) || empty($value)) {
			unset($address[$key]);
		}
	}
	
	switch($layout) {
		case 'list':
			$content = '<ul class="arlo-address-list">';
			
			foreach($address as $line) {
				$content .= '<li>' . $line . '</li>';
			}
			
			$content .= '</ul>';
		break;
	
		default:
			$content = implode(', ', $address);
		break;
	}
	
	// get map info here
	return $content;
});

// venue directions shortcode

$shortcodes->add('venue_directions', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_venue_list_item']['v_facilityinfodirections'])) return '';

	return $GLOBALS['arlo_venue_list_item']['v_facilityinfodirections'];
});

// venue parking shortcode

$shortcodes->add('venue_parking', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_venue_list_item']['v_facilityinfoparking'])) return '';

	return $GLOBALS['arlo_venue_list_item']['v_facilityinfoparking'];
});

/*
 * PRESENTER SHORTCODES
 */

// presenter list shortcode

$shortcodes->add('presenter_list', function($content='', $atts, $shortcode_name){
	$templates = arlo_get_option('templates');
	$content = $templates['presenters']['html'];
	return do_shortcode($content);
});

// presenter list pagination shortcode

$shortcodes->add('presenter_list_pagination', function($content='', $atts, $shortcode_name){
	global $wpdb;
	
	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');

	$t1 = "{$wpdb->prefix}arlo_presenters";
	$t2 = "{$wpdb->prefix}posts";

	$items = $wpdb->get_results(
		"SELECT p.p_id
		FROM $t1 p 
		LEFT JOIN $t2 post 
		ON p.p_post_name = post.post_name 
		WHERE post.post_type = 'arlo_presenter'
		ORDER BY p.p_lastname ASC", ARRAY_A);

	$num = $wpdb->num_rows;

	return arlo_pagination($num,$limit);
});

// presenter list item shortcode

$shortcodes->add('presenter_list_item', function($content='', $atts, $shortcode_name){
	global $wpdb;

	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');
	$offset = (get_query_var('paged') && intval(get_query_var('paged')) > 0) ? intval(get_query_var('paged')) * $limit - $limit: 0 ;

	$t1 = "{$wpdb->prefix}arlo_presenters";
	$t2 = "{$wpdb->prefix}posts";

	$items = $wpdb->get_results(
		"SELECT p.*, post.ID as post_id
		FROM $t1 p 
		LEFT JOIN $t2 post 
		ON p.p_post_name = post.post_name 
		WHERE post.post_type = 'arlo_presenter'
		ORDER BY p.p_lastname ASC
		LIMIT $offset, $limit", ARRAY_A);

	$output = '';

	foreach($items as $item) {

		$GLOBALS['arlo_presenter_list_item'] = $item;

		$output .= do_shortcode($content);

		unset($GLOBALS['arlo_presenter_list_item']);

	}

	return $output;
});

// presenter name shortcode

$shortcodes->add('presenter_name', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_presenter_list_item']['p_firstname']) && !isset($GLOBALS['arlo_presenter_list_item']['p_lastname'])) return '';

	$first_name = $GLOBALS['arlo_presenter_list_item']['p_firstname'];
	$last_name = $GLOBALS['arlo_presenter_list_item']['p_lastname'];

	return $first_name . ' ' . $last_name;
});

// presenter permalink shortcode

$shortcodes->add('presenter_permalink', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_presenter_list_item']['post_id'])) return '';

	return get_permalink($GLOBALS['arlo_presenter_list_item']['post_id']);
});

// presenter viewuri shortcode

$shortcodes->add('presenter_link', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_presenter_list_item']['p_viewuri'])) return '';

	return $GLOBALS['arlo_presenter_list_item']['p_viewuri'];
});

// presenter profile shortcode

$shortcodes->add('presenter_profile', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_presenter_list_item']['p_profile'])) return '';

	return $GLOBALS['arlo_presenter_list_item']['p_profile'];
});

// presenter qualifications shortcode

$shortcodes->add('presenter_qualifications', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_presenter_list_item']['p_qualifications'])) return '';

	return $GLOBALS['arlo_presenter_list_item']['p_qualifications'];
});

// presenter interests shortcode

$shortcodes->add('presenter_interests', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_presenter_list_item']['p_interests'])) return '';

	return $GLOBALS['arlo_presenter_list_item']['p_interests'];
});

// presenter social link shortcode
$shortcodes->add('presenter_social_link', function($content='', $atts, $shortcode_name){
	// merge and extract attributes
	extract(shortcode_atts(array(
		'network' => '',
		'linktext'=> ''
	), $atts, $shortcode_name));

	// tidy up network so we can use it
	$network = trim(strtolower($network));

	// if no valid platform is specified, return nothing
	if(is_null($network) || ($network != 'facebook' && $network != 'twitter' && $network != 'linkedin')) return '';

	// if the presenter has no social media
	if(!isset($GLOBALS['arlo_presenter_list_item']['p_twitterid']) && !isset($GLOBALS['arlo_presenter_list_item']['p_facebookid']) && !isset($GLOBALS['arlo_presenter_list_item']['p_linkedinid'])) return '';

	$fb_link = 'https://facebook.com/';
	$li_link = 'https://www.linkedin.com/';
	$tw_link = 'https://twitter.com/';

	$fb_id = $GLOBALS['arlo_presenter_list_item']['p_facebookid'];
	$li_id = $GLOBALS['arlo_presenter_list_item']['p_linkedinid'];
	$tw_id = $GLOBALS['arlo_presenter_list_item']['p_twitterid'];

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
});

// presenter events list shortcode
$shortcodes->add('presenter_events_list', function($content='', $atts, $shortcode_name){
	global $post, $wpdb;
	$slug = get_post( $post )->post_name;

	$slug_a = explode('-', $slug);
	$p_id = $slug_a[0];

	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}arlo_events";
	$t3 = "{$wpdb->prefix}arlo_events_presenters";

	$items = $wpdb->get_results(
		"SELECT et.et_name, et.et_post_name 
		FROM $t1 et
		LEFT JOIN $t2 e ON  e.et_arlo_id = et.et_arlo_id
		INNER JOIN $t3 exp ON exp.e_arlo_id = e.e_arlo_id AND exp.active = e.active
		WHERE exp.p_arlo_id = $p_id AND e_parent_arlo_id = 0
		GROUP BY et.et_name
		ORDER BY et.et_name ASC", ARRAY_A);

	$events = '';

	if($wpdb->num_rows > 0) {

		$events .= '<ul class="presenter-events">';

		foreach($items as $item) {

			$et_id = arlo_get_post_by_name($item['et_post_name'], 'arlo_event');

			$permalink = get_permalink($et_id);

			$events .= '<li><a href="'.$permalink.'">'.$item['et_name'].'</a></li>';

		}

		$events .= '</ul>';

	}

	return $events;
});

// timezones
$shortcodes->add('timezones', function($content='', $atts, $shortcode_name){
	global $post, $wpdb;
	
	// only allow this to be used on the eventtemplate page
	if($post->post_type != 'arlo_event') {
		return '';
	}
	
	// find out if we have any online events
	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}arlo_events";
	
	$items = $wpdb->get_results("SELECT $t2.e_isonline, $t2.e_timezone_id FROM $t2
		LEFT JOIN $t1
		ON $t2.et_arlo_id = $t1.et_arlo_id AND $t2.e_isonline = 1 AND $t2.e_parent_arlo_id = 0
		WHERE $t1.et_post_name = '$post->post_name'
		", ARRAY_A);
	
	if(empty($items)) {
		return '';
	}
	
	$olson_names = getTimezoneOlsonNames();	

	$content = '<form method="GET" class="arlo-timezone">';
	$content .= '<select name="timezone">';
	
	foreach(getTimezones() as $timezone) {		
		$selected = false;
		if((isset($_GET['timezone']) && $_GET['timezone'] == $timezone->id) || (!isset($_GET['timezone']) && $timezone->id == $items[0]['e_timezone_id'])) {
			$selected = true;
			//get olson timezones
			$olson_names = getTimezoneOlsonNames($timezone->id);
			$GLOBALS['selected_timezone_olson_names'] = $olson_names;
		}
		
		$content .= '<option value="' . $timezone->id . '" ' . ($selected ? 'selected' : '') . '>'. $timezone->name . '</option>';
	}
	
	$content .= '</select>';
	$content .= '</form>';
	
	//if there is no olson names in the database, that means we couldn't do a timezone conversion
	if (!(is_array($olson_names) && count($olson_names))) {
		$content = '';
	}

	return $content;
});

// suggest date/location
$shortcodes->add('suggest_datelocation', function($content='', $atts, $shortcode_name){
	global $post, $wpdb;

	// merge and extract attributes
	extract(shortcode_atts(array(
		'text'	=> __('Suggest another date/location', $GLOBALS['arlo_plugin_slug']),
	), $atts, $shortcode_name));
	
	if(!isset($GLOBALS['arlo_eventtemplate']['et_registerinteresturi']) || empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi'])) return '';
	
	// only allow this to be used on the eventtemplate page
	if($post->post_type != 'arlo_event') {
		return '';
	}
	
	// find out if we have any online events
	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}arlo_events";
	
	$items = $wpdb->get_results("SELECT $t2.e_isonline, $t2.e_datetimeoffset FROM $t2
		LEFT JOIN $t1
		ON $t2.et_arlo_id = $t1.et_arlo_id AND $t2.e_parent_arlo_id = 0
		WHERE $t1.et_post_name = '$post->post_name'
		", ARRAY_A);
	
	if(empty($items)) {
		return '';
	}

	$content = '<a href="' . $GLOBALS['arlo_eventtemplate']['et_registerinteresturi'] . '" class="arlo-register-interest">' . $text . '</a>';

	return $content;
});

// group devider
$shortcodes->add('group_divider', function($content='', $atts, $shortcode_name){
	if(isset($GLOBALS['arlo_event_list_item']['show_divider'])) return $GLOBALS['arlo_event_list_item']['show_divider'];
});

// category list
function category_ul($items, $counts) {
	$post_types = arlo_get_option('post_types');
	$events_url = get_page_link($post_types['event']['posts_page']);
	
	if(!is_array($items) || empty($items)) return '';
	
	$regions = get_option('arlo_regions');	
	$arlo_region = get_query_var('arlo-region', '');
	$arlo_region = (!empty($arlo_region) && array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');	
	
	$html = '<ul class="arlo-category-list">';
	
	foreach($items as $cat) {
		$html .= '<li>';
		$html .= '<a href="';
		$html .= $events_url . (!empty($arlo_region) ? 'region-' . $arlo_region . '/' : '');
		
		if($cat->c_parent_id != 0) {
			$html .= 'cat-' . $cat->c_slug;
		}
		
		$html .= '">';
		$html .= $cat->c_name . ( !is_null($counts) ?  sprintf($counts, $cat->c_template_num) : '' );
		$html .= '</a>';
		if(isset($cat->children)) {
			$html .= category_ul($cat->children, $counts);
		}
		$html .= '</li>';
	}
	
	$html .= '</ul>';
	
	return $html;
}
	
$shortcodes->add('categories', function($content='', $atts, $shortcode_name){
	$return = '';
	
	$arlo_category = isset($_GET['arlo-category']) && !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : get_query_var('arlo-category', '');
	
	// calculate depth
	$depth = (isset($atts['depth'])) ? (int)$atts['depth'] : 1;
	if($depth == 0) $depth = null;
	
	// show title?
	$title = (isset($atts['title'])) ? $atts['title'] : null;
	
	// show counts
	$counts = (isset($atts['counts'])) ? $atts['counts'] : null;
        		
	// start at
	$start_at = (isset($atts['parent'])) ? (int)$atts['parent'] : 0;
	if(!isset($atts['parent']) && $start_at == 0 && !empty($arlo_category)) {
		$slug = $arlo_category;
		$start_at = current(explode('-', $slug));
	}
	
	$tree = \Arlo\Categories::getTree($start_at, $depth);
	
	$GLOBALS['categories_count'] = count($tree);
	
	if(empty($tree)) return;
	
	if($start_at == 0) {
		$tree = $tree[0]->children;
	}
	
	if($title) {
		$conditions = array('id' => $start_at);
		
		if($start_at == 0) {
			$conditions = array('parent_id' => 0);
		}
		
		$current = \Arlo\Categories::get($conditions, 1);
		
		$return .= sprintf($title, $current->c_name);
	}
	
	$return .= category_ul($tree, $counts);
	
	return $return;
});

// event template duration
$shortcodes->add('event_duration', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['et_arlo_id'])) return;
	
	$conditions = array(
		'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id']
	);
	
	$events = \Arlo\Events::get($conditions, array('e.e_startdatetime ASC'), 1);
	
	if(empty($events)) return;
	
	$start = $events->e_startdatetime;
	$end = $events->e_finishdatetime;
	$difference = strtotime($end)-strtotime($start);// seconds
        
	// if we're the same day, display hours
	if(date('d-m', strtotime($start)) == date('d-m', strtotime($end))) {
		$hours = floor($difference/60/60);
                
		if ($hours > 6) {
			return __('1 day', $GLOBALS['arlo_plugin_slug']);
		}

		$minutes = ceil(($difference % 3600)/60);

		$duration = '';
		
		if($hours > 0) {
			$duration .= sprintf(_n('%d hour', '%d hours', $hours, $GLOBALS['arlo_plugin_slug']), $hours);
		}

		if($hours > 0 && $minutes > 0) {
			$duration .= ', ';
		}

		if($minutes > 0) {
			$duration .= sprintf(_n('%d minute', '%d minutes', $minutes, $GLOBALS['arlo_plugin_slug']), $minutes);
		}
		
		return $duration;
	}
	
	// if not the same day, and less than 7 days, then show number of days
	if(ceil($difference/60/60/24) <= 7) {
		$days = ceil($difference/60/60/24);
		
		return sprintf(_n('%d day','%d days', $days, $GLOBALS['arlo_plugin_slug']), $days);
	}
	
	// if not the same day, and more than 7 days, then show number of weeks
	if(ceil($difference/60/60/24) > 7) {
		$weeks = ceil($difference/60/60/24/7);
		
		return sprintf(_n('%d week','%d weeks', $weeks, $GLOBALS['arlo_plugin_slug']), $weeks);		
	}
	
	return;
});

// event template price
$shortcodes->add('event_price', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['et_arlo_id'])) return;
	
	$settings = get_option('arlo_settings');  
	$price_setting = (isset($settings['price_setting'])) ? esc_attr($settings['price_setting']) : ARLO_PLUGIN_PREFIX . '-exclgst';
	$price_field = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? 'o_offeramounttaxexclusive' : 'o_offeramounttaxinclusive';
	$price_field_show = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? 'o_formattedamounttaxexclusive' : 'o_formattedamounttaxinclusive';
	$free_text = (isset($settings['free_text'])) ? esc_attr($settings['free_text']) : __('Free', $GLOBALS['arlo_plugin_slug']);
        
        
	// attempt to find event template offer
	$conditions = array(
		'event_template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id'],
		'discounts' => false
	);
	
	$offer = \Arlo\Offers::get($conditions, array("o.{$price_field} ASC"), 1);
	
	// if none, try the associated events
	if(!$offer) {
		$conditions = array(
			'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id']
		);
		
		$event = \Arlo\Events::get($conditions, array('e.e_startdatetime ASC'), 1);
		
		if(empty($event)) return;
		
		$conditions = array(
			'event_id' => $event->e_id,
			'discounts' => false
		);
		
		$offer = \Arlo\Offers::get($conditions, array("o.{$price_field} ASC"), 1);
	}
	
	if(empty($offer)) return;
	
	// if $0.00, return "Free"
	if((float)$offer->$price_field == 0) {
		return $free_text;
	}
	
	return '<span class="arlo-from-text">' . __('From', $GLOBALS['arlo_plugin_slug']) . '</span> ' . $offer->$price_field_show;
});

// event template next running
$shortcodes->add('event_next_running', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_eventtemplate']) || empty($GLOBALS['arlo_eventtemplate']['et_arlo_id'])) return;
	$return = "";
	
	
	$regions = get_option('arlo_regions');
	$arlo_region = get_query_var('arlo-region', '');
	$arlo_region = (!empty($arlo_region) && array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');
	

	$conditions = array(
		'template_id' => $GLOBALS['arlo_eventtemplate']['et_arlo_id'],
		'date' => 'e.e_startdatetime > NOW()',
		'parent_id' => 'e.e_parent_arlo_id = 0',
	);
	
	if (!empty($arlo_region)) {
		$conditions['region'] = 'e.e_region = "' . $arlo_region . '"';
	}
	
	// merge and extract attributes
	extract(shortcode_atts(array(
		'buttonclass' => '',
		'dateclass' => '',
		'format' => 'd M y',
		'layout' => '',
		'limit' => 1,
		'removeyear' => "true"
	), $atts, $shortcode_name));
        
	$removeyear = ($removeyear == "false" || $removeyear == "0" ? false : true);
	
	$events = \Arlo\Events::get($conditions, array('e.e_startdatetime ASC'), $limit);
			
	if ($layout == "list") {
		$return = '<ul class="arlo-event-next-running">';
	}
	
	if(count($events) == 0 && !empty($GLOBALS['arlo_eventtemplate']['et_registerinteresturi'])) {
		$return = '<a href="' . $GLOBALS['arlo_eventtemplate']['et_registerinteresturi'] . '" title="' . __('Register interest', $GLOBALS['arlo_plugin_slug']) . '" class="' . $buttonclass . '">' . __('Register interest', $GLOBALS['arlo_plugin_slug']) . '</a>';
	} else if (count($events)) {
		$return_links = [];
		
		if (!is_array($events)) {
			$events = array($events);
		}		

		foreach ($events as $event) {
			if (!empty($event->e_startdatetime)) {
	            if(date('y', strtotime($event->e_startdatetime)) == date('y') && $removeyear) {
	            	$format = trim(preg_replace('/\s+/', ' ', str_replace(["Y", "y"], "", $format)));
	            }	
	            
	            if ($event->e_registeruri && !$event->e_isfull) {
	                $return_links[] = ($layout == 'list' ? "<li>" : "") . '<a href="' . $event->e_registeruri . '" class="' . $dateclass . ' arlo-register">' . date($format, strtotime($event->e_startdatetime)) . '</a>' . ($layout == 'list' ? "</li>" : "");
	            } else {
	                $return_links[] = ($layout == 'list' ? "<li>" : "") . '<span class="' . $dateclass . '">' . date($format, strtotime($event->e_startdatetime)) . '</span>' . ($layout == 'list' ? "</li>" : "");
	            }
	        }	
		}	
		
		$return .= implode(($layout == 'list' ? "" : ", "), $return_links);
	}
	
	if ($layout == "list") {
		$return .= '</ul>';
	}   
        
	return $return;
});

// category header
$shortcodes->add('category_header', function($content='', $atts, $shortcode_name) {
	$arlo_category = isset($_GET['arlo-category']) && !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : get_query_var('arlo-category', '');
	
	if (!empty($arlo_category)) {
		$category = \Arlo\Categories::get(array('id' => current(explode('-', $arlo_category))), 1);
	} else {
		$category = \Arlo\Categories::get(array('parent_id' => 0), 1);
	}
	
	if(!$category) return;
	
	return $category->c_header;
});

// category footer
$shortcodes->add('category_footer', function($content='', $atts, $shortcode_name){
	$arlo_category = isset($_GET['arlo-category']) && !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : get_query_var('arlo-category', '');


	if (!empty($arlo_category)) {
		$category = \Arlo\Categories::get(array('id' => current(explode('-', $arlo_category))), 1);
	} else {
		$category = \Arlo\Categories::get(array('parent_id' => 0), 1);
	}
	
	if(!$category) return;
	
	return $category->c_footer;
});

// label
$shortcodes->add('label', function($content='', $atts, $shortcode_name){
	return $content;
});

// event list wrapper
$shortcodes->add('event_list', function($content='', $atts, $shortcode_name){
		return $content;
});