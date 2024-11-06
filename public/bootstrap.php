<?php

/*
 * Change meta title
 */
add_filter( 'document_title_parts', function($title) {
	global $post;

	if ($post) {
		$new_title = set_title($title['title'], $post->ID, true);

		if (!empty($new_title['subtitle'])) {
			$title['title'] = esc_attr($new_title['subtitle'] . ' - ' . $new_title['title']);
		}
	}

	return $title;
}, 10, 2);

/*
 * Add event category to title when filtered by a category
 */
add_filter( 'the_title', function($title, $id = null) {
	$new_title = set_title($title, $id);

	if (empty($new_title['subtitle'])) {
		return $new_title['title'];
	}

	$title_with_glue = (empty($new_title['title']) ? '' : $new_title['title'] . ': ');
	return $title_with_glue . '<span class="cat-title-ext">' . esc_html($new_title['subtitle']) . ' </span>';
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
 * set the title based on category, search and location
 *
 * @since    3.0.0
 *
 */
 
function set_title($title, $id = null, $meta = false){
	global $post;

	$plugin = Arlo_For_Wordpress::get_instance();
	$import_id = $plugin->get_importer()->get_current_import_id();	
	
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

	if (!empty($settings['post_types']['oa']['posts_page'])) {
		array_push($pages, $settings['post_types']['oa']['posts_page']);
	}	
	
	$subtitle = '';
	
	$arlo_category = \Arlo\Utilities::clean_string_url_parameter('arlo-category');
	$arlo_location = \Arlo\Utilities::clean_string_url_parameter('arlo-location');
	$arlo_search = \Arlo\Utilities::clean_string_url_parameter('arlo-search');
	
	$cat_slug = !empty($arlo_category) ? $arlo_category : '';	

	$cat = null;

	if (!empty($cat_slug))
		$cat = \Arlo\Entities\Categories::get(array('slug' => $cat_slug), null, $import_id);		
		
	if ($id === null || !in_array($id, $pages) || ($post && $id != $post->ID) || (!in_the_loop() && !$meta) || is_nav_menu_item($id)) return ['title' => $title];
	
	if(!$cat && empty($arlo_location) && empty($arlo_search)) return ['title' => $title];
	
	if (!empty($cat->c_name)) {
		$subtitle = $cat->c_name;
		
		if (!empty($arlo_location)) {
			$subtitle .= ' (' . $arlo_location . ')';
		}
	} else if (!empty($arlo_location)) {
		$subtitle = $arlo_location;
	} else if (!empty($arlo_search)) {
		$subtitle = $arlo_search;
	}
	        
	return [
		'title' => $title,
		'subtitle' => $subtitle
	];
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
		$custom_type = isset( Arlo_For_Wordpress::$templates[$id]['type'] ) ? Arlo_For_Wordpress::$templates[$id]['type'] : $id;
		$custom_type = in_array($custom_type,array('events','presenters','venues')) ? substr( $custom_type, 0, strlen($custom_type)-1 ) : $custom_type;

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
                'name' => __( $type['name'], 'arlo-for-wordpress'),
                'singular_name' => __( $type['singular_name'], 'arlo-for-wordpress')
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
			switch($custom_type) {
				case 'upcoming':
					add_rewrite_rule('^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(venue-([^/]*))?/?(delivery-([^/]*))?/?(eventtag-([^/]*))?/?(presenter-([^/]*))?/?(templatetag-([^/]*))?/?(state-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-venue=$matches[10]&arlo-delivery=$matches[12]&arlo-eventtag=$matches[14]&arlo-presenter=$matches[16]&arlo-templatetag=$matches[18]&arlo-state=$matches[20]&paged=$matches[22]','top');
				break;
				case 'oa':
					add_rewrite_rule('^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(oatag-([^/]*))?/?(page/([^/]*))?/?(templatetag-([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-oatag=$matches[6]&paged=$matches[8]&arlo-templatetag=$matches[10]','top');
				break;			
				case 'event':
					// Base event rule with only event and region
					add_rewrite_rule('^' . $slug . '/(\d+-[^/]*)+/?(region-([^/]*))?/?$','index.php?arlo_event=$matches[1]&arlo-region=$matches[3]','top');
					// Events with location and state
					add_rewrite_rule('^' . $slug . '/(\d+-[^/]*)+/?(region-([^/]*))?/?(location-([^/]*))?/?(state-([^/]*))?/?$','index.php?arlo_event=$matches[1]&arlo-region=$matches[3]&arlo-location=$matches[5]&arlo-state=$matches[7]','top');
					// Events with Event ID
					add_rewrite_rule('^' . $slug . '/(\d+-[^/]*)+/?(region-([^/]*))?/?(event-([^/]*))?/?$','index.php?arlo_event=$matches[1]&arlo-region=$matches[3]&arlo-event-id=$matches[5]','top');

					add_rewrite_rule('^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(delivery-([^/]*))?/?(templatetag-([^/]*))?/?(state-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-delivery=$matches[10]&arlo-templatetag=$matches[12]&arlo-state=$matches[14]&paged=$matches[16]','top');
				break;
				case 'eventsearch':
					add_rewrite_rule('^' . $slug . '/?(region-([^/]*))?/search/([^/]*)?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-search=$matches[3]&paged=$matches[5]','top');
					add_rewrite_rule('^' . $slug . '/?(region-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&paged=$matches[4]','top');
				break;
				case 'schedule':
					add_rewrite_rule('^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(venue-([^/]*))?/?(delivery-([^/]*))?/?(templatetag-([^/]*))?/?(state-([^/]*))?/?(page/([^/]*))?','index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-venue=$matches[10]&arlo-delivery=$matches[12]&arlo-templatetag=$matches[14]&arlo-state=$matches[16]&paged=$matches[18]','top');
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
	add_rewrite_tag('%arlo-venue%', '([^&]+)');
	add_rewrite_tag('%arlo-state%', '([^&]+)');
	add_rewrite_tag('%arlo-delivery%', '([^&]+)');
	add_rewrite_tag('%arlo-eventtag%', '([^&]+)');
	add_rewrite_tag('%arlo-oatag%', '([^&]+)');
	add_rewrite_tag('%arlo-presenter%', '([^&]+)');
	add_rewrite_tag('%arlo-templatetag%', '([^&]+)');
	add_rewrite_tag('%arlo-search%', '([^&]+)');
	add_rewrite_tag('%arlo-event-id%', '([^&]+)');
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
	if (!empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], 'arlo-search') !== false && !empty($_GET['arlo-search'])) {
		if(isset($settings['post_types']['eventsearch']['posts_page']) && $settings['post_types']['eventsearch']['posts_page'] != 0) {
			$slug = substr(substr(str_replace(get_home_url(), '', get_permalink($settings['post_types']['eventsearch']['posts_page'])), 0, -1), 1);
			$location = '/' . $slug . '/search/' . rawurlencode(str_replace(['/','\\'], '', wp_unslash($_GET['arlo-search']))) . '/';
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

	$cookie_time = time() + apply_filters('arlo_region_cookie_time', 60*60*24*30);
			
	if (!empty($page_obj)) {
		$page_type = $page_obj->post_type;
		$page_id = $page_obj->ID;
	}

	$arlo_page_ids = array();
	foreach(Arlo_For_Wordpress::$post_types as $id => $arlo_post) {
		if (isset($arlo_post['regionalized']) 
			&& is_bool($arlo_post['regionalized']) 
			&& $arlo_post['regionalized'] 
			&& !empty($settings['post_types'][$id]['posts_page'])) {
			$arlo_page_ids[intval($settings['post_types'][$id]['posts_page'])] = $id;
		}
	}


	if (is_array($regions) && count($regions)) {
		$urlparts = parse_url(site_url());
		$domain = $urlparts['host'];

		if (((array_key_exists($page_id, $arlo_page_ids) && !empty($settings['post_types'][$arlo_page_ids[$page_id]]['posts_page'])) || $page_type == 'arlo_event')) {
			if (empty($selected_region)) {
				//try to read the region from a cookie
				if (!empty($_COOKIE['arlo-region']) && in_array($_COOKIE['arlo-region'], array_keys($regions))) {
					$selected_region = $_COOKIE['arlo-region'];
				} else {
					$regions_keys = array_keys($regions);
					$selected_region = reset($regions_keys);
				}
				
				setcookie("arlo-region", $selected_region, $cookie_time, '/', $domain);	
				
				if ($page_type == 'arlo_event') {
					$slug = substr(substr(str_replace(get_home_url(), '', get_post_permalink($page_id)), 0, -1), 1);	
				} else {
					$slug = substr(substr(str_replace(get_home_url(), '', get_permalink($settings['post_types'][$arlo_page_ids[$page_id]]['posts_page'])), 0, -1), 1);	
				}
				
				$location = str_replace($slug, $slug.'/region-' . $selected_region , $_SERVER['REQUEST_URI']);			
				
				wp_redirect(esc_url($location));
				exit();				
			} else {
				setcookie("arlo-region", $selected_region, $cookie_time, '/', $domain);	
			}
		} else {
			if (empty($_COOKIE['arlo-region'])) {
				$regions_keys = array_keys($regions);
				setcookie("arlo-region", reset($regions_keys), $cookie_time, '/', $domain);
				
				//Some hosting has high level caching (caches the redirects) and no cookies are available which means it can stuck in a redirect loop
				if (!empty($_COOKIE['arlo-region']))
					wp_redirect($_SERVER['REQUEST_URI']);
			}
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

	if(function_exists('arlo_the_content_'.$post_type) && is_singular()) {
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

	if (get_option('arlo_plugin_disabled', '0') == '1') return;
	
	$templates = arlo_get_option('templates');
	$content = $templates['event']['html'];
    $arlo_region = \Arlo_For_Wordpress::get_region_parameter();	

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
		et.et_post_id = post.ID
	WHERE 
		post.post_type = 'arlo_event' 
	AND 
		post.ID = $post->ID
	" . (!empty($arlo_region) ? " AND et.et_region = '" . esc_sql($arlo_region) . "'" : "") . "
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
	if (get_option('arlo_plugin_disabled', '0') == '1') return;
        
	$templates = arlo_get_option('templates');
	$content = $templates['presenter']['html'];

	global $post, $wpdb;

	$t1 = "{$wpdb->prefix}arlo_presenters";
	$t2 = "{$wpdb->prefix}posts";

	$item = $wpdb->get_row(
		"SELECT p.*, post.ID as post_id
		FROM $t1 p 
		LEFT JOIN $t2 post 
		ON p.p_post_id = post.ID
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
	if (get_option('arlo_plugin_disabled', '0') == '1') return;
    
	$templates = arlo_get_option('templates');
	$content = $templates['venue']['html'];

	global $post, $wpdb;

	$t1 = "{$wpdb->prefix}arlo_venues";
	$t2 = "{$wpdb->prefix}posts";

	$item = $wpdb->get_row(
		"SELECT v.*, post.ID as post_id
		FROM $t1 v 
		LEFT JOIN $t2 post 
		ON v.v_post_id = post.ID
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
	$limit = intval(is_null($limit) ? get_option('posts_per_page') : $limit);
	
	$big = 999999999;
	
	$current = arlo_current_page();
	
	return paginate_links(array(
		'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
		'format' => '?paged=%#%',
		'current' => max( 1, $current ),
		'total' => ceil($num/$limit),
		'mid_size' => 6
	));
}

/**
 * Detect the current page from WordPress variables
 *
 * @since    4.0.0
 *
 * @return   int The current page
 */
function arlo_current_page() {
	$page = 0;

	//not sure why we watch that one first
	$paged = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'paged');
	if (!empty($paged)) {
		$page = intval($paged);
	}
	//the normal one used on post/pages
	else if(!empty(get_query_var('paged'))) {
		$page = intval(get_query_var('paged'));
	}
	//the one in use for static pages (like Homepage)
	else if(!empty(get_query_var('page'))) {
		$page = intval(get_query_var('page'));
	}

	return ($page > 0 ? $page : 1);
}

/**
 * Get the content of a template for the selected theme
 *
 * @since    3.0.0
 *
 * @param    string $name name of the template to get
 *
 * @return   string the contents of the template file
 */
function arlo_get_template($name) {

	$plugin = Arlo_For_Wordpress::get_instance();
	$theme_manager = $plugin->get_theme_manager();

	$selected_theme_id = get_option('arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME);
	$theme_templates = $theme_manager->load_default_templates($selected_theme_id);

	if (isset($theme_templates[$name]) && !empty($theme_templates[$name]['html']))
		return $theme_templates[$name]['html'];

	return 'Template NOT found';
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

/**
 * Check if current page is an Arlo Archive (Schedule/Upcoming/Presenters etc)
 * @param  ID|WP_Post $post Current post
 * @return bool
 */
function arlo_is_archive( $post = null ) {
	$post = get_post( $post );
	$settings = get_option('arlo_settings');

	if (!$post){ return false; }

	foreach($settings['post_types'] as $post_type => $config) {
		if ($config['posts_page'] == $post->ID) {
			return true;
		}
	}
	return false;
}

function arlo_add_datamodel() {
	$plugin = Arlo_For_Wordpress::get_instance();

	// error_log("DB Hash before install_schema: " . $plugin->get_schema_manager()->create_db_schema_hash());

	$plugin->get_schema_manager()->install_schema();

	// error_log("DB Hash after install_schema: " . $plugin->get_schema_manager()->create_db_schema_hash());
}
