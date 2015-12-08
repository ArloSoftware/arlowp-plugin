<?php

$arlo_plugin = Arlo_For_Wordpress::get_instance();
$arlo_plugin_slug = $arlo_plugin->get_plugin_slug();

/*
 * Add event category to title when filtered by a category
 */
add_filter( 'the_title', function($title, $id = null){
	$settings = get_option('arlo_settings');
	
	$pages = array(
		$settings['post_types']['event']['posts_page'],
		$settings['post_types']['eventsearch']['posts_page'],
		$settings['post_types']['upcoming']['posts_page'],
	);
	
	$cat_slug = !empty($_GET['arlo-category']) ? $_GET['arlo-category'] : '';	
        	
	$cat = \Arlo\Categories::get(array('slug' => $cat_slug));
	$location = stripslashes(urldecode($_GET['arlo-location']));
	$search = stripslashes(urldecode($_GET['arlo-search']));	
                
	if($id === null || !in_array($id, $pages)) return $title;
		
	if(!$cat && empty($location) && empty($search)) return $title;
	
	if (!empty($cat->c_name)) {
		$subtitle = $cat->c_name;
		
		if (!empty($location)) {
			$subtitle .= ' (' . $location . ')';
		}
	} else if (!empty($location)) {
		$subtitle = $location;		
	} else if (!empty($search)) {
		$subtitle = $search;
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
	if(ISSET($wp_query->query['post_type']) && in_array($wp_query->query['post_type'], array('arlo_event', 'arlo_presenter', 'arlo_venue'))) {
		$wp_query->is_single = false;
		$wp_query->is_page = true;
	}

	r