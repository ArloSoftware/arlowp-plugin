<?php

$arlo_plugin = Arlo_For_Wordpress::get_instance();
$arlo_plugin_slug = $arlo_plugin->get_plugin_slug();

/*
 * Add event category to title when filtered by a category
 */
add_filter( 'the_title', function($title, $id = null){
	global $post, $arlo_plugin;
	
	$import_id = $arlo_plugin->get_importer()->get_current_import_id();	
	
	$title = htmlentities($title, ENT_QUOTES, "UTF-8", false);
	
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
		$cat = \Arlo\Entities\Categories::get(array('slug' => $cat_slug), null, $import_id);
		
		
	$location = !empty($_GET['arlo-location']) ? $_GET['arlo-location'] : get_query_var('arlo-location', '');
	$search = !empty($_GET['arlo-search']) ? $_GET['arlo-search'] : get_query_var('arlo-search', '');	
		
	$location = stripslashes(urldecode($location));
	$search = stripslashes(urldecode($search));
		
	if($id === null || !in_array($id, $pages) || $id != $post->ID || !in_the_loop() ) return $title;
		
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
		$subtitle = htmlentities($subtitle, ENT_QUOTES, "UTF-8");
		$subtitle = '<span class="cat-title-ext">' . (!empty($title) ? ': ':'') . $subtitle . ' </span>';
	}
        
	return $title . $subtitle;
	
}, 10, 2);

/*
 * Trick WP to treat custom post types as pages
 */
add_action('parse_query', function($wp_query){
	if (isset($wp_query->query['post_type']) && in_array($wp_query->query['post_type'], array('arlo_event', 'arlo_presenter', 'arlo_venue', 'arlo_region'))) {
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
					add_rewrite_rule('^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(delivery-([^/]*))?/?(eventtag-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-delivery=$matches[10]&arlo-eventtag=$matches[12]&paged=$matches[14]','top');
				break;			
				case 'event':					
					add_rewrite_rule('^' . $slug . '/(\d+-[^/]*)+/?(region-([^/]*))?/?$','index.php?arlo_event=$matches[1]&arlo-region=$matches[3]','top');
					add_rewrite_rule('^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(delivery-([^/]*))?/?(templatetag-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-delivery=$matches[10]&arlo-templatetag=$matches[12]&paged=$matches[14]','top');
				break;
				case 'eventsearch':
					add_rewrite_rule('^' . $slug . '/?(region-([^/]*))?/search/([^/]*)?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-search=$matches[3]&paged=$matches[5]','top');
					add_rewrite_rule('^' . $slug . '/?(region-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&paged=$matches[4]','top');
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
			wp_redirect( get_home_url() . $location );
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
	
	$page_obj = get_queried_object();
	$page_type = '';
			
	if (!empty($page_obj)) {
		$page_type = $page_obj->post_type;
		$page_id = $page_obj->ID;
	}
			
	foreach(Arlo_For_Wordpress::$post_types as $id => $arlo_post) {
		if (isset($arlo_post['regionalized']) && is_bool($arlo_post['regionalized']) && $arlo_post['regionalized']) {
			$arlo_page_ids[intval($settings['post_types'][$id]['posts_page'])] = $id;
		}
	}
	
	if (((array_key_exists($page_id, $arlo_page_ids) && !empty($settings['post_types'][$arlo_page_ids[$page_id]]['posts_page'])) || $page_type == 'arlo_event') && is_array($regions) && count($regions)) {
		if (empty($selected_region)) {
			//try to read the region from a cookie
			if (!empty($_COOKIE['arlo-region']) && in_array($_COOKIE['arlo-region'], array_keys($regions))) {
				$selected_region = $_COOKIE['arlo-region'];
			} else {
				$selected_region = reset(array_keys($regions));
			}
			
			setcookie("arlo-region", $selected_region, time()+60*60*24*30, '/');	
			
			if ($page_type == 'arlo_event') {
				$slug = substr(substr(str_replace(get_home_url(), '', get_post_permalink($page_id)), 0, -1), 1);	
			} else {
				$slug = substr(substr(str_replace(get_home_url(), '', get_permalink($settings['post_types'][$arlo_page_ids[$page_id]]['posts_page'])), 0, -1), 1);	
			}
			
			$location = str_replace($slug, $slug.'/region-' . $selected_region , $_SERVER['REQUEST_URI']);			
			
			wp_redirect($location);
			exit();				
		} else {
			setcookie("arlo-region", $selected_region, time()+60*60*24*30, '/');	
		}
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

	if(function_exists('arlo_the_content_'.$post_type) && in_the_loop()) {
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
	global $post, $wpdb;
	
	$templates = arlo_get_option('templates');
	$content = $templates['event']['html'];
	$regions = get_option('arlo_regions');	
	
	$arlo_region = get_query_var('arlo-region', '');
	$arlo_region = (!empty($arlo_region) && Utilities::array_ikey_exists($arlo_region, $regions) ? $arlo_region : '');	
	
	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}posts";	
	
	$sql = "
	SELECT 
		et.*, 
		post.ID as post_id
	FROM 
		$t1 et 
	LEFT JOIN 
		$t2 post 
	ON 
		et.et_post_name = post.post_name 
	WHERE 
		post.post_type = 'arlo_event' 
	AND 
		post.ID = $post->ID
	" . (!empty($arlo_region) ? " AND et.et_region = '" . $arlo_region . "'" : "") . "
	ORDER 
		BY et.et_name ASC
	";
	
	
	$item = $wpdb->get_row($sql, ARRAY_A);	

	$GLOBALS['arlo_eventtemplate'] = $item;

	$GLOBALS['no_event'] = $GLOBALS['no_onlineactivity'] = 1;

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
function arlo_get_option($key, $default = null) {
	$settings = get_option('arlo_settings', array());
	
	if(isset($settings[$key])) {
		return $settings[$key];
	}
	
	return $default;
}


/**
 * arlo_set_option function.
 * 
 * @access public
 * @param mixed $key
 * @param mixed $value (default: null)
 * @return boolean
 */
function arlo_set_option($key, $value = null) {
	$settings = get_option('arlo_settings', array());
	
	$settings[$key] = $value;
	
	return update_option('arlo_settings', $settings);
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

function arlo_add_datamodel() {
	$plugin = Arlo_For_Wordpress::get_instance();

	$plugin->get_schema_manager()->install_schema();
}


\Arlo\Shortcodes\Shortcodes::init(); 



