<?php

$arlo_plugin = Arlo_For_Wordpress::get_instance();
$arlo_plugin_slug = $arlo_plugin->get_plugin_slug();

/*
 * Add event category to title when filtered by a category
 */
add_filter( 'the_title', function($title, $id = null){
	$settings = get_option('arlo_settings');
	
	$pages = array(
		$settings['post_types']['event']['posts_page']
	);
	
	if($id === null || !in_array($id, $pages)) return $title;
	
	$cat = \Arlo\Categories::get(array('slug' => get_query_var('arlo_event_category')));
	$location = urldecode(get_query_var('arlo_event_location'));
	
	if(!$cat && empty($location)) return $title;
	
	if (!empty($cat->c_name)) {
		$subtitle = $cat->c_name;
		
		if (!empty($location)) {
			$subtitle .= ' (' . $location . ')';
		}
	} else if (!empty($location)) {
		$subtitle = $location;		
	}
	
	// append category name to events page
	return '<span class="cat-title-ext">' . $title . ': </span>' . $subtitle;
}, 10, 2);

/*
 * Trick WP to treat custom post types as pages
 */
add_action('parse_query', function($wp_query){
	if(ISSET($wp_query->query['post_type']) && in_array($wp_query->query['post_type'], array('arlo_event', 'arlo_presenter', 'arlo_venue'))) {
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
			'value' => $cat->c_slug
			);
		$output = array_merge($output, arlo_child_categories($cat->children, $depth+1));

	}

	return $output;
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
	if(is_null($label)) $label = $type;
	$filter_html = '<select id="arlo-filter-'.$type.'" name="'.$type.'">';
	$filter_html .= '<option value="0">'.__('Filter by '.$label, 'arlo').'</option>';

	foreach($items as $item) {
	
		$selected = (urldecode(get_query_var($type)) == $item['value']) ? ' selected="selected"' : '';

		$filter_html .= '<option value="'.$item['value'].'"'.$selected.'>';
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
		$slug = str_replace('_', '-', strtolower(trim(preg_replace('/[^A-Za-z]+/', '', $settings['post_types'][$id]['name']))));
		$slug = 'arlo/' . $slug;
		
		// slug based on page, if it exists
		$page_id = null; 
		if(isset($settings['post_types'][$id]['posts_page']) && $settings['post_types'][$id]['posts_page'] != 0) {
			$page_id = $settings['post_types'][$id]['posts_page'];
			$slug = substr(substr(str_replace(get_home_url(), '', get_permalink($settings['post_types'][$id]['posts_page'])), 0, -1), 1);
		}
                
		$args = array(
			'labels' => array(
                'name' => __( $settings['post_types'][$id]['name'] ),
                'singular_name' => __( $settings['post_types'][$id]['singular_name'] )
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
				case 'event':
					add_rewrite_rule('^' . $slug . '/page/([^/]*)/?','index.php?page_id=' . $page_id . '&paged=$matches[1]','top');
					
					add_rewrite_rule('^' . $slug . '/location/([^/]*)/page/([^/]*)/?','index.php?page_id=' . $page_id . '&arlo_event_location=$matches[1]&paged=$matches[2]','top');
					add_rewrite_rule('^' . $slug . '/location/([^/]*)/?','index.php?page_id=' . $page_id . '&arlo_event_location=$matches[1]','top');
										
					
					add_rewrite_rule('^' . $slug . '/category/([^/]*)/location/([^/]*)/page/([^/]*)/?','index.php?page_id=' . $page_id . '&arlo_event_category=$matches[1]&arlo_event_location=$matches[2]&paged=$matches[3]','top');					
					add_rewrite_rule('^' . $slug . '/category/([^/]*)/location/([^/]*)/?','index.php?page_id=' . $page_id . '&arlo_event_category=$matches[1]&arlo_event_location=$matches[2]','top');					
					
					add_rewrite_rule('^' . $slug . '/category/([^/]*)/page/([^/]*)/?','index.php?page_id=' . $page_id . '&arlo_event_category=$matches[1]&paged=$matches[2]','top');
					add_rewrite_rule('^' . $slug . '/category/([^/]*)/?','index.php?page_id=' . $page_id . '&arlo_event_category=$matches[1]','top');
										
					add_rewrite_tag('%arlo_event_category%', '([^&]+)');
					add_rewrite_tag('%arlo_event_location%', '([^&]+)');
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

	add_rewrite_tag('%category%', '([^&]+)');
	add_rewrite_tag('%month%', '([^&]+)');
	add_rewrite_tag('%location%', '([^&]+)');
	
	// flush cached rewrite rules if we've just updated the arlo settings
	if(isset($_GET['settings-updated'])) flush_rewrite_rules();
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
	
	return paginate_links(array(
		'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
		'format' => '?paged=%#%',
		'current' => max( 1, get_query_var('paged') ),
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
	$path = PLUGIN_DIR.'/includes/blueprints/'.$name.'.tmpl';

	if(file_exists($path)) {

		return file_get_contents($path);

	}

	return 'Blueprint not found';
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
	/* 1 */ install_table_arlo_eventtemplate();
	/* 2 */ install_table_arlo_contentfields();
	/* 3 */ install_table_arlo_events();
	/* 4 */ install_table_arlo_venues();
	/* 5 */ install_table_arlo_presenters();
	/* 6 */ install_table_arlo_offers();
	/* 7 */ install_table_arlo_eventtemplates_presenters();
	/* 8 */ install_table_arlo_events_presenters();
	/* 0 */ install_table_arlo_import_log();
	install_table_arlo_categories();
	install_table_arlo_eventtemplates_categories();
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			`et_id` INT NOT NULL AUTO_INCREMENT,
			`et_arlo_id` INT NOT NULL,
			`et_code` VARCHAR(255) NULL,
			`et_name` VARCHAR(255) NULL,
			`et_descriptionsummary` TEXT NULL,
			`et_post_name` VARCHAR(255) NULL,
			`active` DATETIME NULL,
			`et_registerinteresturi` TEXT NULL,
			PRIMARY KEY (`et_id`),
			INDEX `et_arlo_id` (`et_arlo_id`))
			CHARACTER SET utf8 COLLATE=utf8_general_ci;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			`cf_id` INT NOT NULL AUTO_INCREMENT,
			`et_id` INT NOT NULL,
			`cf_fieldname` VARCHAR(255) NULL,
			`cf_text` TEXT NULL,
			`cf_order` INT NULL,
			`e_contenttype` VARCHAR(255) NULL,
			`active` DATETIME NULL,
			PRIMARY KEY (`cf_id`),
			INDEX `cf_order` (`cf_order`),
			INDEX `et_id` (`et_id`))
			CHARACTER SET utf8 COLLATE=utf8_general_ci;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		`e_id` INT NOT NULL AUTO_INCREMENT,
		`e_arlo_id` INT NOT NULL,
		`et_arlo_id` INT NULL,
		`e_code` VARCHAR(255) NULL,
		`e_startdatetime` DATETIME NOT NULL,
		`e_finishdatetime` DATETIME NULL,
		`e_datetimeoffset` VARCHAR(6) NULL COMMENT 'Date time offset in minutes\n',
		`e_timezone` VARCHAR(10) NULL,
		`v_id` INT NULL,
		`e_locationname` VARCHAR(255) NULL,
		`e_locationroomname` VARCHAR(255) NULL,
        `e_locationvisible` TINYINT(1) NOT NULL DEFAULT '0',
		`e_isfull` TINYINT(1) NOT NULL DEFAULT FALSE,
		`e_placesremaining` INT NULL,
		`e_sessiondescription` VARCHAR(255) NULL,
		`e_notice` TEXT NULL,
		`e_viewuri` VARCHAR(255) NULL,
		`e_registermessage` VARCHAR(255) NULL,
		`e_registeruri` VARCHAR(255) NULL,
		`e_providerorganisation` VARCHAR(255) NULL,
		`e_isonline` TINYINT(1) NOT NULL DEFAULT FALSE,
		`active` DATETIME NULL,
		PRIMARY KEY (`e_id`),
		INDEX `et_arlo_id` (`et_arlo_id`),
		INDEX `e_arlo_id` (`e_arlo_id`),
		INDEX `v_id` (`v_id`))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		`v_id` INT NOT NULL AUTO_INCREMENT,
		`v_arlo_id` INT NOT NULL,
		`v_name` VARCHAR(255) NULL,
		`v_geodatapointlatitude` DECIMAL(10,6) NULL,
		`v_geodatapointlongitude` DECIMAL(10,6) NULL,
		`v_physicaladdressline1` VARCHAR(255) NULL,
		`v_physicaladdressline2` VARCHAR(255) NULL,
		`v_physicaladdressline3` VARCHAR(255) NULL,
		`v_physicaladdressline4` VARCHAR(255) NULL,
		`v_physicaladdresssuburb` VARCHAR(255) NULL,
		`v_physicaladdresscity` VARCHAR(255) NULL,
		`v_physicaladdressstate` VARCHAR(255) NULL,
		`v_physicaladdresspostcode` VARCHAR(255) NULL,
		`v_physicaladdresscountry` VARCHAR(255) NULL,
		`v_viewuri` VARCHAR(255) NULL,
		`v_facilityinfodirections` TEXT NULL,
		`v_facilityinfoparking` TEXT NULL,
		`v_post_name` VARCHAR(255) NULL,
		`active` DATETIME NULL,
		PRIMARY KEY (`v_id`),
		INDEX `v_arlo_id` (`v_arlo_id`))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		`p_id` INT NOT NULL AUTO_INCREMENT,
		`p_arlo_id` INT NOT NULL,
		`p_firstname` VARCHAR(64) NULL,
		`p_lastname` VARCHAR(64) NULL,
		`p_viewuri` VARCHAR(255) NULL,
		`p_profile` TEXT NULL,
		`p_qualifications` TEXT NULL,
		`p_interests` TEXT NULL,
		`p_twitterid` VARCHAR(255) NULL,
		`p_facebookid` VARCHAR(255) NULL,
		`p_linkedinid` VARCHAR(255) NULL,
		`p_post_name` VARCHAR(255) NULL,
		`active` DATETIME NULL,
		PRIMARY KEY (`p_id`),
		INDEX `p_arlo_id` (`p_arlo_id`))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		`o_id` INT NOT NULL AUTO_INCREMENT,
		`o_arlo_id` INT,
		`et_id` INT,
		`e_id` INT,
		`o_label` VARCHAR(255) NULL,
		`o_isdiscountoffer` TINYINT(1) NOT NULL DEFAULT FALSE,
		`o_currencycode` VARCHAR(255) NULL,
		`o_offeramounttaxexclusive` DECIMAL(15,2) NULL,
		`o_offeramounttaxinclusive` DECIMAL(15,2) NULL,
		`o_formattedamounttaxexclusive` VARCHAR(255) NULL,
		`o_formattedamounttaxinclusive` VARCHAR(255) NULL,
		`o_taxrateshortcode` VARCHAR(255) NULL,
		`o_taxratename` VARCHAR(255) NULL,
		`o_taxratepercentage` DECIMAL(3,2) NULL,
		`o_message` TEXT NULL,
		`o_order` INT NULL,
		`o_replaces` INT NULL,
		`active` DATETIME NULL,
		PRIMARY KEY (`o_id`),
		INDEX `o_arlo_id` (`o_arlo_id`),
		INDEX `et_id` (`et_id`),
		INDEX `e_id` (`e_id`),
		INDEX `o_order` (`o_order`))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		`et_arlo_id` INT NULL,
		`p_arlo_id` INT NULL,
		`p_order` INT NULL COMMENT 'Order of the presenters for the event template.',
		`active` datetime DEFAULT NULL,
		PRIMARY KEY (`et_arlo_id`,`p_arlo_id`),
		INDEX `cf_order` (`p_order`),
		INDEX `fk_et_id_idx` (`et_arlo_id` ASC),
		INDEX `fk_p_id_idx` (`p_arlo_id` ASC))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
		
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		`e_arlo_id` INT NULL,
		`p_arlo_id` INT NULL,
		`p_order` INT NULL COMMENT 'Order of the presenters for the event.',
		`active` datetime DEFAULT NULL,
		PRIMARY KEY (`e_arlo_id`,`p_arlo_id`),		
		INDEX `fk_e_id_idx` (`e_arlo_id` ASC),
		INDEX `fk_p_id_idx` (`p_arlo_id` ASC))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
		
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		`c_id` INT NOT NULL AUTO_INCREMENT,
		`c_arlo_id` INT NOT NULL,
		`c_name` varchar(255) NOT NULL DEFAULT '',
		`c_slug` varchar(255) NOT NULL DEFAULT '',
		`c_header` text,
		`c_footer` text,
		`c_template_num` SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		`c_order` INT DEFAULT NULL,
		`c_parent_id` INT DEFAULT NULL,
		`active` datetime DEFAULT NULL,
		PRIMARY KEY (`c_id`),
		UNIQUE KEY `c_arlo_id` (`c_arlo_id`),
		KEY `c_parent_id` (`c_parent_id`))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		`et_arlo_id` INT NULL,
		`c_arlo_id` INT NULL,
		`active` datetime DEFAULT NULL,
		PRIMARY KEY (`et_arlo_id`,`c_arlo_id`),
		INDEX `fk_et_id_idx` (`et_arlo_id` ASC),
		INDEX `fk_c_id_idx` (`c_arlo_id` ASC))
		CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE `$table_name` (
		  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		  `message` text,
		  `created` datetime DEFAULT NULL,
		  `successful` tinyint(1) DEFAULT NULL,
		  PRIMARY KEY (`id`)) 
		  CHARACTER SET utf8 COLLATE=utf8_general_ci;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
	  }
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
	return array(
		array(
			'name' => 'Perth',
			'timezone_id' => 'Australia/Perth',
			'abbreviation' => 'AWST'
		),
		array(
			'name' => 'Adelaide',
			'timezone_id' => 'Australia/Adelaide',
			'abbreviation' => 'ACST'
		),
		array(
			'name' => 'Darwin',
			'timezone_id' => 'Australia/Darwin',
			'abbreviation' => 'ACST'
		),
		array(
			'name' => 'Brisbane',
			'timezone_id' => 'Australia/Brisbane',
			'abbreviation' => 'AEST'
		),
		array(
			'name' => 'Canberra, Melbourne, Sydney',
			'timezone_id' => 'Australia/Canberra',
			'abbreviation' => 'AEST'
		),
		array(
			'name' => 'Hobart',
			'timezone_id' => 'Australia/Hobart',
			'abbreviation' => 'AEST'
		),
		array(
			'name' => 'Auckland, Wellington',
			'timezone_id' => 'Pacific/Auckland',
			'abbreviation' => 'NZST'
		),
	);
}

/*
 * Shortcodes
 */

$shortcodes = \Arlo\Shortcodes::init();

// event template list shortcode
$shortcodes->add('event_template_list', function($content='', $atts, $shortcode_name){
	$templates = arlo_get_option('templates');
	$content = $templates['events']['html'];

	return $content;
});

// event template list pagination

$shortcodes->add('event_template_list_pagination', function($content='', $atts, $shortcode_name){
	global $wpdb, $wp_query;
	
	if (isset($GLOBALS['show_only_at_bottom']) && $GLOBALS['show_only_at_bottom']) return;
	
	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');

	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}posts";
	$t3 = "{$wpdb->prefix}arlo_eventtemplates_categories";
	$t4 = "{$wpdb->prefix}arlo_categories";
	$t5 = "{$wpdb->prefix}arlo_events";
		
	$where = "WHERE post.post_type = 'arlo_event'";
	$join = "";
	
	if(!empty($wp_query->query_vars['arlo_event_location'])) :

		$where .= " AND e.e_locationname = '" . urldecode($wp_query->query_vars['arlo_event_location']) . "'";
		$join .= " LEFT JOIN $t5 e USING (et_arlo_id)";

	endif;	
	
	
	if(isset($wp_query->query_vars['arlo_event_category']) || isset($atts['category'])) {
		$cat_slug = '';

		if(isset($atts['category'])) {
			$cat_slug = $atts['category'];
		} else {
			$cat_slug = $wp_query->query_vars['arlo_event_category'];
		}
		$where .= " AND c.c_slug = '$cat_slug'";
	// } else {
	// 	// take advantage of getTree
	// 	$cats = \Arlo\Categories::getTree(0, null);
		
	// 	$ids = array();
		
	// 	foreach($cats[0]->children as $cat) {
	// 		$ids[] = $cat->c_arlo_id;
	// 	}
		
	// 	$where .= " AND (c.c_arlo_id IN (" . implode(',', $ids) . ") OR c.c_arlo_id IS NULL)";
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
	global $wpdb, $wp_query;
		
	if (isset($atts['show_only_at_bottom']) && $atts['show_only_at_bottom'] == "true" && isset($GLOBALS['categories_count']) && $GLOBALS['categories_count']) {
		$GLOBALS['show_only_at_bottom'] = true;
		return;
	}

	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');
	$offset = (get_query_var('paged') && intval(get_query_var('paged')) > 0) ? intval(get_query_var('paged')) * $limit - $limit: 0 ;

	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}posts";
	$t3 = "{$wpdb->prefix}arlo_eventtemplates_categories";
	$t4 = "{$wpdb->prefix}arlo_categories";
	$t5 = "{$wpdb->prefix}arlo_events";
		
	$where = "WHERE post.post_type = 'arlo_event'";
	$join = "";
	
	if(!empty($wp_query->query_vars['arlo_event_location'])) :

		$where .= " AND e.e_locationname = '" . urldecode($wp_query->query_vars['arlo_event_location']) . "'";
		$join .= " LEFT JOIN $t5 e USING (et_arlo_id)";

	endif;	
		
	if(isset($wp_query->query_vars['arlo_event_category']) || isset($atts['category'])) {

		$cat_slug = '';

		if(isset($atts['category'])) {
			$cat_slug = $atts['category'];
		} else {
			$cat_slug = $wp_query->query_vars['arlo_event_category'];
		}
		$where .= " AND c.c_slug = '$cat_slug'";
	// } else {
	// 	// take advantage of getTree
	// 	$cats = \Arlo\Categories::getTree(0, null);
		
	// 	$ids = array();
		
	// 	foreach($cats[0]->children as $cat) {
	// 		$ids[] = $cat->c_arlo_id;
	// 	}
		
	// 	$where .= " AND (c.c_arlo_id IN (" . implode(',', $ids) . ") OR c.c_arlo_id IS NULL)";
	}
	
	// grouping
	$group = "GROUP BY et.et_arlo_id";
	
	//ordering
	$order = "ORDER BY et.et_name ASC";
	
	// if grouping is set...
	if(isset($atts['group'])) {
		switch($atts['group']) {
			case 'category':
				$group = '';
				$order = "ORDER BY c.c_order ASC, c.c_name ASC, et.et_name ASC";
			break;
		}
	}
	
	$items = $wpdb->get_results("SELECT et.*, post.ID as post_id, etc.c_arlo_id, c.*
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
		LIMIT $offset,$limit", ARRAY_A);
				
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

	global $post;

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

// event template category filter shortcode

$shortcodes->add('event_template_category_filter', function($content='', $atts, $shortcode_name){

	extract(shortcode_atts(array(
		'filtertext'  	=> 'Filter Events',
		'resettext'		=> 'Reset'
	), $atts, $shortcode_name));

	global $post;
        
        $slug = get_post( $post )->post_name;
        
	$uri = explode('?', $_SERVER['REQUEST_URI']);
	$action = preg_replace('/(.*)\/page\/([^\/]*)\/?/i', '$1/', $uri[0]);            
        
	$filter_html = '<form id="arlo-event-filter" class="arlo-filters" method="get" action="'.site_url().'/'.$slug.'/">';

	// category select

	$cats = \Arlo\Categories::getTree();

	$filter_html .= arlo_create_filter('arlo_event_category', arlo_child_categories($cats[0]->children), 'category');

	$filter_html .= '<div class="arlo-filters-buttons">';
        
	$filter_html .= '<a href="'.get_page_link().'" class="arlo-button">'.$resettext.'</a></div>';

	$filter_html .= '</form>';
	
	return $filter_html;
});

// event template filter shortcode

$shortcodes->add('event_template_filters', function($content='', $atts, $shortcode_name){

	extract(shortcode_atts(array(
		'filters'	=> 'category,location',
		'filtertext'  	=> 'Filter Events',
		'resettext'		=> 'Reset'
	), $atts, $shortcode_name));
	
	$filters_array = explode(',',$filters);

	global $post;
        
    $slug = get_post( $post )->post_name;
        
	$uri = explode('?', $_SERVER['REQUEST_URI']);
	$action = preg_replace('/(.*)\/page\/([^\/]*)\/?/i', '$1/', $uri[0]);
	
	$filter_html = '<form id="arlo-event-filter" class="arlo-filters" method="get" action="'.site_url().'/'.$slug.'/">';
           
	
	foreach($filters_array as $filter) :

		switch($filter) :

			case 'category' :

				// category select
				
				$cats = \Arlo\Categories::getTree();

				$filter_html .= arlo_create_filter('arlo_event_category', arlo_child_categories($cats[0]->children), 'category');
				
				break;

			case 'location' :

				// location select

				global $wpdb;

				$t1 = "{$wpdb->prefix}arlo_events";

				$items = $wpdb->get_results(
					"SELECT e.e_locationname
					FROM $t1 e 
					GROUP BY e.e_locationname 
					ORDER BY e.e_locationname", ARRAY_A);

				$locations = array();

				$l = count($items);

				for($i=0;$i<$l;$i++) :

					$locations[$i]['string'] = $items[$i]['e_locationname'];
					$locations[$i]['value'] = $items[$i]['e_locationname'];

				endfor;

				$filter_html .= arlo_create_filter('arlo_event_location', $locations, 'location');

				break;

		endswitch;

	endforeach;	
        
	// category select


	$filter_html .= '<div class="arlo-filters-buttons">';
        
	$filter_html .= '<a href="'.get_page_link().'" class="arlo-button">'.$resettext.'</a></div>';

	$filter_html .= '</form>';
	
	return $filter_html;

});

// event list item shortcode

$shortcodes->add('event_list_item', function($content='', $atts, $shortcode_name){
	global $post, $wpdb;
	
	$t1 = "{$wpdb->prefix}arlo_eventtemplates";
	$t2 = "{$wpdb->prefix}arlo_events";
	$t3 = "{$wpdb->prefix}arlo_venues";
	$t4 = "{$wpdb->prefix}arlo_events_presenters";
	$t5 = "{$wpdb->prefix}arlo_presenters";
	$t6 = "{$wpdb->prefix}arlo_offers";

	$items = $wpdb->get_results("SELECT $t2.*, $t3.v_post_name FROM $t2
		LEFT JOIN $t3
		ON $t2.v_id = $t3.v_arlo_id
		LEFT JOIN $t1
		ON $t2.et_arlo_id = $t1.et_arlo_id
		WHERE $t1.et_post_name = '$post->post_name'
		ORDER BY $t2.e_startdatetime", ARRAY_A);
	
	$output = '';

	foreach($items as $key => $item) {

		$GLOBALS['arlo_event_list_item'] = $item;
                
                if (!empty($atts['show']) && $key == $atts['show']) {
                    $output .= '</ul><div class="arlo-clear-both"></div><ul class="arlo-list arlo-show-more-hidden events">';
                }

		$output .= do_shortcode($content);

		unset($GLOBALS['arlo_event_list_item']);
	}
	
	return $output;
});

// event code shortcode

$shortcodes->add('event_code', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_code'])) return '';

	return $GLOBALS['arlo_event_list_item']['e_code'];
});

// event location shortcode

$shortcodes->add('event_location', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_locationname'])) return '';

	$location = $GLOBALS['arlo_event_list_item']['e_locationname'];

	if($GLOBALS['arlo_event_list_item']['e_isonline'] || $GLOBALS['arlo_event_list_item']['v_id'] == 0 || $GLOBALS['arlo_event_list_item']['e_locationvisible'] == 0) {

		return $location;

	} else {

		$permalink = get_permalink(arlo_get_post_by_name($GLOBALS['arlo_event_list_item']['v_post_name'], 'arlo_venue'));

		return '<a href="'.$permalink.'">'.$location.'</a>';

	}
	
});

// event start date shortcode

$shortcodes->add('event_start_date', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_startdatetime'])) return '';
	
	$start_date = new DateTime($GLOBALS['arlo_event_list_item']['e_startdatetime']);
	
	if(isset($_GET['timezone']) && $GLOBALS['arlo_event_list_item']['e_isonline']) {
		$start_date->modify(($GLOBALS['arlo_event_list_item']['e_datetimeoffset'] * -1) . ' hours');
		$start_date->setTimezone(new DateTimeZone($_GET['timezone']));
	}

	$format = 'D g:i A';

	if(isset($atts['format'])) $format = $atts['format'];

	return $start_date->format($format);
});

// event end date shortcode

$shortcodes->add('event_end_date', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']['e_finishdatetime'])) return '';

	$end_date = new DateTime($GLOBALS['arlo_event_list_item']['e_finishdatetime']);
	
	if(isset($_GET['timezone']) && $GLOBALS['arlo_event_list_item']['e_isonline']) {
		$end_date->modify(($GLOBALS['arlo_event_list_item']['e_datetimeoffset'] * -1) . ' hours');
		$end_date->setTimezone(new DateTimeZone($_GET['timezone']));
	}

	$format = 'D g:i A';

	if(isset($atts['format'])) $format = $atts['format'];

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

	$registration = '<div class="arlo-event-registration">';
	$registration .= (($isfull) ? '<span class="arlo-event-full">' . __('Event is full', 'arlo') . '</span>' : '');
	// test if there is a register uri string, if so display the button
	if(!is_null($registeruri) && $registeruri != '') {
		$registration .= '<a class="arlo-button ' . (($isfull) ? 'arlo-waiting-list' : 'arlo-register') . '" href="'. $registeruri . '" target="_blank">';
		$registration .= (($isfull) ? __('Join waiting list', 'arlo') : __($registermessage, 'arlo')) . '</a>';
	} else {
            $registration .= $registermessage;
        }
	$registration .= (($placesremaining > 1) ? '<span class="arlo-places-remaining">' . $placesremaining . ' ' . __('places remaining', 'arlo') .'</span>' : '');
	$registration .= (($placesremaining == 1) ? '<span class="arlo-places-remaining">' . $placesremaining . ' ' . __('place remaining', 'arlo') .'</span>' : '');
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
        $price_setting = (isset($settings['price_setting'])) ? esc_attr($settings['price_setting']) : PLUGIN_PREFIX . '-exclgst';      
        $free_text = (isset($settings['free_text'])) ? esc_attr($settings['free_text']) : __('Free', 'arlo');


	foreach($offers_array as $offer) {

		extract($offer);
                
                $amount = $price_setting == PLUGIN_PREFIX . '-exclgst' ? $o_offeramounttaxexclusive : $o_offeramounttaxinclusive;
                $famount = $price_setting == PLUGIN_PREFIX . '-exclgst' ? $o_formattedamounttaxexclusive : $o_formattedamounttaxinclusive;

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
			$offers .= $replaced ? '' : ($price_setting == PLUGIN_PREFIX . '-exclgst' ? __('excl.', 'arlo') : __('incl.', 'arlo')).' '.$o_taxrateshortcode;
		} else {
			$offers .= '<span class="amount free">'.$free_text.'</span> ';
		}
		// display message if there is one
		$offers .= (!is_null($o_message) || $o_message != '') ? ' '.$o_message:'';
		// if a replacement offer exists
		if($replaced) {
			$offers .= '</span><span';
			$offers .= $replacement_discount ? ' class="discount"' : '';
			$offers .= '>';
			// display replacement offer label if there is one
			$offers .= (!is_null($replacement_label) || $replacement_label != '') ? $replacement_label.' ':'';
			$offers .= '<span class="amount">'.$replacement_amount.'</span> '.__('excl.', 'arlo').' '.$o_taxrateshortcode;
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
		$output .= '<figcaption class="arlo-event-presenters-title">'._n('Presenter', 'Presenters', $np, 'arlo').'</figcaption>';
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
		
	$active = $arlo_plugin->get_last_import();

	// merge and extract attributes
	extract(shortcode_atts(array(
		'layout' => '',
		'link' => 'true'
	), $atts, $shortcode_name));


        $output = $GLOBALS['arlo_event_list_item']['e_providerorganisation'];

	return $output;
});


// upcoming event list shortcode

$shortcodes->add('upcoming_list', function($content='', $atts, $shortcode_name){
	$templates = arlo_get_option('templates');
	$content = $templates['upcoming']['html'];
	return do_shortcode($content);
});

// upcoming event list pagination shortcode

$shortcodes->add('upcoming_list_pagination', function($content='', $atts, $shortcode_name){
	global $wpdb;
	
	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');

	$t1 = "{$wpdb->prefix}arlo_events";
	$t2 = "{$wpdb->prefix}arlo_eventtemplates";
	$t3 = "{$wpdb->prefix}arlo_venues";
	$t4 = "{$wpdb->prefix}arlo_offers";
	$t5 = "{$wpdb->prefix}arlo_eventtemplates_categories";
	$t6 = "{$wpdb->prefix}arlo_categories";

	$where = 'WHERE CURDATE() < DATE(e.e_startdatetime)';

	if(isset($_GET['month']) && !empty($_GET['month'])) :

		$dates = explode(':',urldecode($_GET['month']));

		$where .= " AND (DATE(e.e_startdatetime) BETWEEN DATE('$dates[0]')";

		$where .= " AND DATE('$dates[1]'))";

	endif;

	if(isset($_GET['location']) && !empty($_GET['location'])) :

		$where .= " AND e.e_locationname = '" . $_GET['location'] . "'";

	endif;

	if(isset($_GET['category']) && !empty($_GET['category'])) :

		$where .= " AND c.c_arlo_id = " . current(explode('-',$_GET['category']));

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
		$where
		GROUP BY etc.et_arlo_id, e.e_id
		ORDER BY e.e_startdatetime", ARRAY_A);

	$num = $wpdb->num_rows;

	return arlo_pagination($num,$limit);
});

// upcoming event list item shortcode

$shortcodes->add('upcoming_list_item', function($content='', $atts, $shortcode_name){
	global $wpdb;

	$limit = isset($atts['limit']) ? $atts['limit'] : get_option('posts_per_page');
	$offset = (get_query_var('paged') && intval(get_query_var('paged')) > 0) ? intval(get_query_var('paged')) * $limit - $limit: 0 ;

	$output = '';

	$t1 = "{$wpdb->prefix}arlo_events";
	$t2 = "{$wpdb->prefix}arlo_eventtemplates";
	$t3 = "{$wpdb->prefix}arlo_venues";
	$t4 = "{$wpdb->prefix}arlo_offers";
	$t5 = "{$wpdb->prefix}arlo_eventtemplates_categories";
	$t6 = "{$wpdb->prefix}arlo_categories";

	$where = 'WHERE CURDATE() < DATE(e.e_startdatetime)';

	if(isset($_GET['month']) && !empty($_GET['month'])) :

		$dates = explode(':',urldecode($_GET['month']));

		$where .= " AND (DATE(e.e_startdatetime) BETWEEN DATE('$dates[0]')";

		$where .= " AND DATE('$dates[1]'))";

	endif;

	if(isset($_GET['location']) && !empty($_GET['location'])) :

		$where .= " AND e.e_locationname = '" . $_GET['location'] . "'";

	endif;

	if(isset($_GET['category']) && !empty($_GET['category'])) :

		$where .= " AND c.c_arlo_id = " . current(explode('-',$_GET['category']));

	endif;

	$items = $wpdb->get_results(
		"SELECT DISTINCT
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
		$where
	    GROUP BY etc.et_arlo_id, e.e_id
		ORDER BY e.e_startdatetime
		LIMIT $offset, $limit", ARRAY_A);

	if(empty($items)) :

		$output = '<p class="arlo-no-results">' . __('No events to show', 'arlo') . '</p>';

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
        $price_setting = (isset($settings['price_setting'])) ? esc_attr($settings['price_setting']) : PLUGIN_PREFIX . '-exclgst';
        $free_text = (isset($settings['free_text'])) ? esc_attr($settings['free_text']) : __('Free', 'arlo');
            
	$amount = $price_setting == PLUGIN_PREFIX . '-exclgst' ? $GLOBALS['arlo_event_list_item']['o_offeramounttaxexclusive'] : $GLOBALS['arlo_event_list_item']['o_offeramounttaxinclusive'];
	$famount = $price_setting == PLUGIN_PREFIX . '-exclgst' ? $GLOBALS['arlo_event_list_item']['o_formattedamounttaxexclusive'] : $GLOBALS['arlo_event_list_item']['o_formattedamounttaxinclusive'];
	$tax = $GLOBALS['arlo_event_list_item']['o_taxrateshortcode'];

	$offer = ($amount > 0) ? '<span class="arlo-amount">'.$famount .'</span> '. ($price_setting == PLUGIN_PREFIX . '-exclgst' ? __(' excl.', 'arlo') : __(' incl.', 'arlo')).' '.$tax : '<span class="arlo-amount">'.$free_text.'</span>';

	return $offer;
});

// upcoming event filters

$shortcodes->add('upcoming_event_filters', function($content='', $atts, $shortcode_name){
	extract(shortcode_atts(array(
		'filters'	=> 'category,month,location',
		'filtertext'  	=> 'Filter Events',
		'resettext'	=> 'Reset'
	), $atts, $shortcode_name));

	$filters_array = explode(',',$filters);
        
    $slug = get_post( $post )->post_name;
	
	$uri = explode('?', $_SERVER['REQUEST_URI']);
	$action = preg_replace('/(.*)\/page\/([^\/]*)\/?/i', '$1/', $uri[0]);

	$filter_html = '<form class="arlo-filters" method="get" action="'.site_url().'/'.$slug.'">';

	foreach($filters_array as $filter) :

		switch($filter) :

			case 'category' :

				// category select

				$cats = \Arlo\Categories::getTree();

				$filter_html .= arlo_create_filter($filter, arlo_child_categories($cats[0]->children));

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

				$filter_html .= arlo_create_filter($filter, $months);

				break;

			case 'location' :

				// location select

				global $wpdb;

				$t1 = "{$wpdb->prefix}arlo_events";

				$items = $wpdb->get_results(
					"SELECT e.e_locationname
					FROM $t1 e 
					GROUP BY e.e_locationname 
					ORDER BY e.e_locationname", ARRAY_A);


				$locations = array();

				$l = count($items);

				for($i=0;$i<$l;$i++) :

					$locations[$i]['string'] = $items[$i]['e_locationname'];
					$locations[$i]['value'] = $items[$i]['e_locationname'];

				endfor;

				$filter_html .= arlo_create_filter($filter, $locations);

				break;

		endswitch;

	endforeach;

	$filter_html .= '<div class="arlo-filters-buttons">';
        
	$filter_html .= '<a href="'.get_page_link().'" class="arlo-button">'.$resettext.'</a></div>';

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
		$map .= ' alt="Map of ' . $name . '"'; 
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
		WHERE exp.p_arlo_id = $p_id
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
	
	$items = $wpdb->get_results("SELECT $t2.e_isonline, $t2.e_datetimeoffset FROM $t2
		LEFT JOIN $t1
		ON $t2.et_arlo_id = $t1.et_arlo_id AND $t2.e_isonline = 1
		WHERE $t1.et_post_name = '$post->post_name'
		", ARRAY_A);
	
	if(empty($items)) {
		return '';
	}

	$content = '<form method="GET">';
	$content .= '<select name="timezone">';
	
	foreach(getTimezones() as $timezone) {
		$timezone_object = new DateTimeZone($timezone['timezone_id']);
		$time = new DateTime('now', $timezone_object);
		$offset = $time->format('P');
		
		$selected = false;
		if((isset($_GET['timezone']) && $_GET['timezone'] == $timezone['timezone_id']) || (!isset($_GET['timezone']) && $offset == $items[0]['e_datetimeoffset'])) {
			$selected = true;
		}
	
		$content .= '<option value="' . $timezone['timezone_id'] . '" ' . ($selected ? 'selected' : '') . '>(' . $offset . ') ' . $timezone['name'] . '</option>';
	}
	
	$content .= '</select>';
	$content .= '<input type="submit" value="Go" />';
	$content .= '</form>';

	return $content;
});

// suggest date/location
$shortcodes->add('suggest_datelocation', function($content='', $atts, $shortcode_name){
	global $post, $wpdb;

	// merge and extract attributes
	extract(shortcode_atts(array(
		'text'	=> 'Suggest another date/location',
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
		ON $t2.et_arlo_id = $t1.et_arlo_id
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
	
	$html = '<ul class="arlo-category-list">';
	
	foreach($items as $cat) {
		$html .= '<li>';
		$html .= '<a href="';
		$html .= $events_url;
		
		if($cat->c_parent_id != 0) {
		$html .= 'category/' . $cat->c_slug . '/';
		}
		
		$html .= '">';
		$html .= $cat->c_name . ( !is_null($counts) ?  sprintf($counts, $cat->c_template_num) : '' );
		$html .= '</a>';
		if(isset($cat->children)) {
			$html .= category_ul($cat->children);
		}
		$html .= '</li>';
	}
	
	$html .= '</ul>';
	
	return $html;
}
	
$shortcodes->add('categories', function($content='', $atts, $shortcode_name){
	$return = '';

	// calculate depth
	$depth = (isset($atts['depth'])) ? (int)$atts['depth'] : 1;
	if($depth == 0) $depth = null;
	
	// show title?
	$title = (isset($atts['title'])) ? $atts['title'] : null;
	
	// show counts
	$counts = (isset($atts['counts'])) ? $atts['counts'] : null;
		
	// start at
	$start_at = (isset($atts['parent'])) ? (int)$atts['parent'] : 0;
	if(!isset($atts['parent']) && $start_at == 0 && $slug = get_query_var('arlo_event_category')) {
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
                    return "1 day";
                }

		$minutes = ceil(($difference % 3600)/60);

		$duration = '';
		
		if($hours > 0) {
			$duration .= $hours;
			if($hours > 1) {
				$duration .= ' hours';
			} else {
				$duration .= ' hour';
			}
		}

		if($hours > 0 && $minutes > 0) {
			$duration .= ', ';
		}

		if($minutes > 0) {
			$duration .= $minutes;
			if($minutes > 1) {
				$duration .= ' minutes';
			} else {
				$duration .= ' minute';
			}
		}
		
		return $duration;
	}
	
	// if not the same day, and less than 7 days, then show number of days
	if(ceil($difference/60/60/24) <= 7) {
		$days = ceil($difference/60/60/24);
		
		if($days > 1) {
			$days .= ' days';
		} else {
			$days .= ' day';
		}
		
		return $days;
	}
	
	// if not the same day, and more than 7 days, then show number of weeks
	if(ceil($difference/60/60/24) > 7) {
		$weeks = ceil($difference/60/60/24/7);
		
		if($weeks > 1) {
			$weeks .= ' weeks';
		} else {
			$weeks .= ' week';
		}
		
		return $weeks;
	}
	
	return;
});

// event template price
$shortcodes->add('event_price', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['et_arlo_id'])) return;
	
        $settings = get_option('arlo_settings');  
        $price_setting = (isset($settings['price_setting'])) ? esc_attr($settings['price_setting']) : PLUGIN_PREFIX . '-exclgst';
        $price_field = $price_setting == PLUGIN_PREFIX . '-exclgst' ? 'o_offeramounttaxexclusive' : 'o_offeramounttaxinclusive';
        $price_field_show = $price_setting == PLUGIN_PREFIX . '-exclgst' ? 'o_formattedamounttaxexclusive' : 'o_formattedamounttaxinclusive';
        $free_text = (isset($settings['free_text'])) ? esc_attr($settings['free_text']) : __('Free', 'arlo');
        
        
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
	
	return __('From') . ' ' . $offer->$price_field_show;
});

// event template next running
$shortcodes->add('event_next_running', function($content='', $atts, $shortcode_name){
	if(!isset($GLOBALS['arlo_event_list_item']) || empty($GLOBALS['arlo_event_list_item']['et_arlo_id'])) return;
	
	$conditions = array(
		'template_id' => $GLOBALS['arlo_event_list_item']['et_arlo_id']
	);
	
	$event = \Arlo\Events::get($conditions, array('e.e_startdatetime ASC'), 1);
        	
	if(empty($event) && !empty($GLOBALS['arlo_event_list_item']['et_registerinteresturi'])) {
		return '<a href="' . $GLOBALS['arlo_event_list_item']['et_registerinteresturi'] . '" title="' . __('Register interest') . '">' . __('Register interest') . '</a>';
	}
        
        if (!empty($event->e_startdatetime)) {
            $format = 'd M y';
            if(date('y', strtotime($event->e_startdatetime)) == date('y')) {
                    $format = 'd M';
            }

            return date($format, strtotime($event->e_startdatetime));            
        }
        
	return '';
});

// category header
$shortcodes->add('category_header', function($content='', $atts, $shortcode_name){
	if($slug = get_query_var('arlo_event_category')) {
		$category = \Arlo\Categories::get(array('slug' => $slug), 1);
	} else {
		$category = \Arlo\Categories::get(array('parent_id' => 0), 1);
	}
	
	if(!$category) return;
	
	return $category->c_header;
});

// category footer
$shortcodes->add('category_footer', function($content='', $atts, $shortcode_name){
	if($slug = get_query_var('arlo_event_category')) {
		$category = \Arlo\Categories::get(array('slug' => $slug), 1);
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