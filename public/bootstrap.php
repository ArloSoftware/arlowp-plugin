<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use ArloTraining\CacheControl;

/*
 * Change meta title
 */
add_filter(
	'document_title_parts',
	function ( $title ) {
		global $post;

		if ( $post ) {
			$new_title = arlo_set_title( $title['title'], $post->ID, true );

			if ( ! empty( $new_title['subtitle'] ) ) {
				$title['title'] = esc_html( $new_title['subtitle'] . ' - ' . $new_title['title'] );
			}
		}

		return $title;
	},
	10,
	2
);

/*
 * Add event category to title when filtered by a category
 */
add_filter(
	'the_title',
	function ( $title, $id = null ) {
		$new_title = arlo_set_title( $title, $id );

		if ( empty( $new_title['subtitle'] ) ) {
			return $new_title['title'];
		}

		$title_with_glue = ( empty( $new_title['title'] ) ? '' : $new_title['title'] . ': ' );
		return $title_with_glue . '<span class="cat-title-ext">' . esc_html( $new_title['subtitle'] ) . ' </span>';
	},
	10,
	2
);

/*
 * Trick WP to treat custom post types as pages
 */
add_action(
	'parse_query',
	function ( $wp_query ) {
		if ( isset( $wp_query->query['post_type'] ) && in_array( $wp_query->query['post_type'], array( 'arlo_event', 'arlo_presenter', 'arlo_venue', 'arlo_region' ), true ) ) {
			$wp_query->is_single = false;
			$wp_query->is_page   = true;
		}

		return $wp_query;
	}
);

/*
 * Allow themes to provide event.php, presenter.php, and venue.php templates for
 * Arlo detail pages. List/archive pages are ordinary WP pages and keep the
 * normal page template selected by WordPress.
 *
 * The body.arlo class is intentionally registered here using the historical
 * broad behaviour. It gives existing Arlo themes and customer CSS a wrapper for
 * pages that may contain Arlo output from configured pages, shortcodes, widgets,
 * or template customisations. Do not narrow this to only known Arlo post types
 * without a compatibility review; body_class runs before shortcode/widget output
 * is rendered, so it cannot cheaply know every page that will later display Arlo
 * catalogue content.
 */
add_filter(
	'page_template',
	function ( $template ) {
		global $post;

		if ( in_array( $post->post_type, array( 'arlo_event', 'arlo_presenter', 'arlo_venue' ), true ) ) {
			$type = str_replace( 'arlo_', '', $post->post_type );

			$file = locate_template( $type . '.php' );
			if ( $file && is_page() ) {
				return $file;
			}
		}

		add_filter(
			'body_class',
			function ( $classes ) {
				$classes[] = 'arlo';
				return $classes;
			}
		);

		return $template;
	},
	100,
	1
);

/**
 * set the title based on category, search and location
 *
 * @since    3.0.0
 *
 */

function arlo_set_title( $title, $id = null, $meta = false ) {
	global $post;

	$plugin              = Arlo_For_Wordpress::get_instance();
	$import_id           = $plugin->get_importer()->get_current_import_id();
	$post_types_settings = Arlo_For_Wordpress::get_post_types_settings();

	$pages = [];
	foreach ( array( 'event', 'eventsearch', 'upcoming', 'oa' ) as $page_type ) {
		if ( ! empty( $post_types_settings[ $page_type ]['posts_page'] ) ) {
			array_push( $pages, (int) $post_types_settings[ $page_type ]['posts_page'] );
		}
	}

	$subtitle = '';

	$arlo_category = \ArloTraining\Utilities::clean_string_url_parameter( 'arlo-category' );
	$arlo_location = \ArloTraining\Utilities::clean_string_url_parameter( 'arlo-location' );
	$arlo_search   = \ArloTraining\Utilities::clean_string_url_parameter( 'arlo-search' );

	$cat_slug = ! empty( $arlo_category ) ? $arlo_category : '';

	$cat = null;

	if ( ! empty( $cat_slug ) ) {
		$cat = \ArloTraining\Entities\Categories::get( array( 'slug' => $cat_slug ), null, $import_id );
	}

	if ( $id === null || ! in_array( intval( $id ), $pages, true ) || ( $post && $id != $post->ID ) || ( ! in_the_loop() && ! $meta ) || is_nav_menu_item( $id ) ) {
		return [ 'title' => $title ];
	}

	if ( ! $cat && empty( $arlo_location ) && empty( $arlo_search ) ) {
		return [ 'title' => $title ];
	}

	if ( ! empty( $cat->c_name ) ) {
		$subtitle = $cat->c_name;

		if ( ! empty( $arlo_location ) ) {
			$subtitle .= ' (' . $arlo_location . ')';
		}
	} elseif ( ! empty( $arlo_location ) ) {
		$subtitle = $arlo_location;
	} elseif ( ! empty( $arlo_search ) ) {
		$subtitle = $arlo_search;
	}

	return [
		'title'    => $title,
		'subtitle' => $subtitle,
	];
}

/**
 * Registers the arlo custom post types
 *
 * @since    1.0.0
 *
 */
function arlo_register_custom_post_types() {
	$post_types_settings = Arlo_For_Wordpress::get_post_types_settings();
	$templates           = Arlo_For_Wordpress::get_templates();

	foreach ( Arlo_For_Wordpress::$post_types as $id => $type ) {
		$custom_type = isset( $templates[ $id ]['type'] ) ? $templates[ $id ]['type'] : $id;
		$custom_type = in_array( $custom_type, array( 'events', 'presenters', 'venues' ), true ) ? substr( $custom_type, 0, strlen( $custom_type ) - 1 ) : $custom_type;

		// default slug
		$slug = str_replace( '_', '-', strtolower( trim( preg_replace( '/[^A-Za-z]+/', '', $type['name'] ) ) ) );
		$slug = 'arlo/' . $slug;

		// slug based on page, if it exists
		$page_id = null;
		if ( ! empty( $post_types_settings[ $id ]['posts_page'] ) ) {
			$page_id   = (int) $post_types_settings[ $id ]['posts_page'];
			$page_link = get_permalink( $page_id );
			// A draft or pending page only has a ?page_id= permalink. It is not a routable base, and using it as
			// the slug would build invalid rewrite regexes, so such a page falls back to the default slug.
			if ( ! empty( $page_link ) && empty( wp_parse_url( $page_link, PHP_URL_QUERY ) ) ) {
				$slug = substr( substr( str_replace( get_home_url(), '', $page_link ), 0, -1 ), 1 );
			} else {
				$page_id = null;
			}
		}

		$args = array(
			'labels'             => array(
				'name'          => $type['name'],
				'singular_name' => $type['singular_name'],
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $slug,
				'with_front' => false, // false ensures no blog-like url
			),
			'capability_type'    => 'page',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array(
				'comments' => false,
			),
		);

		// let's try some custom rewrite rules

		if ( $page_id ) {
			switch ( $custom_type ) {
				case 'upcoming':
					add_rewrite_rule( '^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(venue-([^/]*))?/?(delivery-([^/]*))?/?(eventtag-([^/]*))?/?(presenter-([^/]*))?/?(templatetag-([^/]*))?/?(state-([^/]*))?/?(page/([^/]*))?', 'index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-venue=$matches[10]&arlo-delivery=$matches[12]&arlo-eventtag=$matches[14]&arlo-presenter=$matches[16]&arlo-templatetag=$matches[18]&arlo-state=$matches[20]&paged=$matches[22]', 'top' );
					break;
				case 'oa':
					add_rewrite_rule( '^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(oatag-([^/]*))?/?(page/([^/]*))?/?(templatetag-([^/]*))?', 'index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-oatag=$matches[6]&paged=$matches[8]&arlo-templatetag=$matches[10]', 'top' );
					break;
				case 'event':
					// Base event rule with only event and region
					add_rewrite_rule( '^' . $slug . '/(\d+-[^/]*)+/?(region-([^/]*))?/?$', 'index.php?arlo_event=$matches[1]&arlo-region=$matches[3]', 'top' );
					// Events with location and state
					add_rewrite_rule( '^' . $slug . '/(\d+-[^/]*)+/?(region-([^/]*))?/?(location-([^/]*))?/?(state-([^/]*))?/?$', 'index.php?arlo_event=$matches[1]&arlo-region=$matches[3]&arlo-location=$matches[5]&arlo-state=$matches[7]', 'top' );
					// Events with Event ID
					add_rewrite_rule( '^' . $slug . '/(\d+-[^/]*)+/?(region-([^/]*))?/?(event-([^/]*))?/?$', 'index.php?arlo_event=$matches[1]&arlo-region=$matches[3]&arlo-event-id=$matches[5]', 'top' );

					add_rewrite_rule( '^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(delivery-([^/]*))?/?(templatetag-([^/]*))?/?(state-([^/]*))?/?(page/([^/]*))?', 'index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-delivery=$matches[10]&arlo-templatetag=$matches[12]&arlo-state=$matches[14]&paged=$matches[16]', 'top' );
					break;
				case 'eventsearch':
					add_rewrite_rule( '^' . $slug . '/?(region-([^/]*))?/search/([^/]*)?/?(page/([^/]*))?', 'index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-search=$matches[3]&paged=$matches[5]', 'top' );
					add_rewrite_rule( '^' . $slug . '/?(region-([^/]*))?/?(page/([^/]*))?', 'index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&paged=$matches[4]', 'top' );
					break;
				case 'schedule':
					add_rewrite_rule( '^' . $slug . '/(region-([^/]*))?/?(cat-([^/]*))?/?(month-([^/]*))?/?(location-([^/]*))?/?(venue-([^/]*))?/?(delivery-([^/]*))?/?(templatetag-([^/]*))?/?(state-([^/]*))?/?(page/([^/]*))?', 'index.php?page_id=' . $page_id . '&arlo-region=$matches[2]&arlo-category=$matches[4]&arlo-month=$matches[6]&arlo-location=$matches[8]&arlo-venue=$matches[10]&arlo-delivery=$matches[12]&arlo-templatetag=$matches[14]&arlo-state=$matches[16]&paged=$matches[18]', 'top' );
					break;
				case 'presenter':
					add_rewrite_rule( '^' . $slug . '/page/([^/]*)/?', 'index.php?page_id=' . $page_id . '&paged=$matches[1]', 'top' );
					break;
				case 'venue':
					add_rewrite_rule( '^' . $slug . '/page/([^/]*)/?', 'index.php?page_id=' . $page_id . '&paged=$matches[1]', 'top' );
					break;
			}
		}

		register_post_type( 'arlo_' . $id, $args );
	}

	// these should possibly be in there own function?
	add_rewrite_tag( '%page_id%', '([^&]+)' );
	add_rewrite_tag( '%arlo-region%', '([^&]+)' );
	add_rewrite_tag( '%arlo-category%', '([^&]+)' );
	add_rewrite_tag( '%arlo-month%', '([^&]+)' );
	add_rewrite_tag( '%arlo-location%', '([^&]+)' );
	add_rewrite_tag( '%arlo-venue%', '([^&]+)' );
	add_rewrite_tag( '%arlo-state%', '([^&]+)' );
	add_rewrite_tag( '%arlo-delivery%', '([^&]+)' );
	add_rewrite_tag( '%arlo-eventtag%', '([^&]+)' );
	add_rewrite_tag( '%arlo-oatag%', '([^&]+)' );
	add_rewrite_tag( '%arlo-presenter%', '([^&]+)' );
	add_rewrite_tag( '%arlo-templatetag%', '([^&]+)' );
	add_rewrite_tag( '%arlo-search%', '([^&]+)' );
	add_rewrite_tag( '%arlo-event-id%', '([^&]+)' );
	add_rewrite_tag( '%paged%', '([^&]+)' );

	// flush cached rewrite rules if we've just updated the arlo settings
	// It is triggered when the user saves changes on the WordPress settings page.
	// wp_safe_redirect( admin_url( 'admin.php?page=arlo-for-wordpress&settings-updated=true' ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- flush_rewrite_rules() is idempotent; capability check is sufficient.
	if ( is_admin() && isset( $_GET['settings-updated'] ) && isset( $_GET['page'] ) && sanitize_key( wp_unslash( $_GET['page'] ) ) === 'arlo-for-wordpress' && current_user_can( 'manage_options' ) ) {
		flush_rewrite_rules();
	}
}


/**
 * If there is a search term for arlo-search, we need to redirect to a friendlier url.
 *
 * @since    2.2.0
 *
 */

function arlo_set_search_redirect() {
	$eventsearch_page_id = Arlo_For_Wordpress::get_posts_page_id( 'eventsearch' );
	$arlo_search         = \ArloTraining\Utilities::filter_string_polyfill( INPUT_GET, 'arlo-search' );
	if ( $arlo_search !== '' ) {
		if ( $eventsearch_page_id > 0 ) {

			$arlo_nonce = \ArloTraining\Utilities::filter_string_polyfill( INPUT_GET, 'arlo-nonce' );
			if ( ! wp_verify_nonce( $arlo_nonce, 'arlo-search-widget' ) ) {
				wp_safe_redirect( get_home_url() );
				exit();
			}
			$search_page_url = get_permalink( $eventsearch_page_id );
			if ( ! empty( $search_page_url ) ) {
				$slug     = substr( substr( str_replace( get_home_url(), '', $search_page_url ), 0, -1 ), 1 );
				$location = '/' . $slug . '/search/' . rawurlencode( str_replace( [ '/','\\' ], '', $arlo_search ) ) . '/';
				wp_safe_redirect( get_home_url() . $location );
				exit();
			}
		}
	} elseif ( $eventsearch_page_id > 0 && isset( $_GET['arlo-search'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence-only check; value is not used here.
		// Empty search submitted: redirect to the clean eventsearch base URL, stripping
		// the query string (which would otherwise expose the nonce in the browser URL bar).
		$search_page_url = get_permalink( $eventsearch_page_id );
		if ( ! empty( $search_page_url ) ) {
			wp_safe_redirect( $search_page_url );
			exit();
		}
	}
}

/**
 * Maps internal region redirect page keys to stable public filter values.
 *
 * @since 5.2.0
 * @param string $internal_key Internal host-page key or arlo_event post type.
 * @return string Public page type, or an empty string for an unknown key.
 */
function arlo_map_region_redirect_page_type( string $internal_key ): string {
	$map = [
		'arlo_event'  => 'event',
		'event'       => 'events',
		'upcoming'    => 'upcoming',
		'schedule'    => 'schedule',
		'eventsearch' => 'eventsearch',
		'oa'          => 'oa',
	];

	return $map[ $internal_key ] ?? '';
}

/**
 * Normalizes rooted URL paths solely for region redirect loop comparisons.
 *
 * @param string $path URL path without query or fragment.
 * @return string Path with literal and percent-encoded dot segments removed.
 */
function arlo_normalize_region_redirect_path( string $path ): string {
	if ( '' === $path || '/' !== $path[0] ) {
		return $path;
	}

	$normalized_segments = [];
	$is_dot_segment      = false;
	foreach ( explode( '/', $path ) as $segment ) {
		$decoded_segment = str_ireplace( '%2e', '.', $segment );
		$is_dot_segment  = '.' === $decoded_segment || '..' === $decoded_segment;
		if ( $is_dot_segment ) {
			if ( '..' === $decoded_segment && count( $normalized_segments ) > 1 ) {
				array_pop( $normalized_segments );
			}
		} else {
			$normalized_segments[] = $segment;
		}
	}

	if ( $is_dot_segment ) {
		$normalized_segments[] = '';
	}

	return implode( '/', $normalized_segments );
}

/**
 * Accepts only root-relative paths and absolute HTTP(S) redirect destinations.
 *
 * @param string $location Trimmed callback destination.
 * @return bool Whether the destination has a supported format.
 */
function arlo_is_supported_region_redirect_location( string $location ): bool {
	if ( '' === $location || false !== strpos( $location, '\\' ) ) {
		return false;
	}

	$parts = wp_parse_url( $location );
	if ( false === $parts ) {
		return false;
	}

	if ( '/' === $location[0] && 0 !== strpos( $location, '//' ) ) {
		return ! isset( $parts['scheme'] ) && ! isset( $parts['host'] );
	}

	return isset( $parts['scheme'], $parts['host'] )
		&& '' !== $parts['host']
		&& in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true );
}

/**
 * If there is at least one region, and the url doesn't contain any region information, we have to construct the url with region,
 * and set the cookie according to the default (first) region
 *
 * @since    2.2.0
 *
 */

function arlo_set_region_redirect() {
	global $post;
	$regions             = get_option( 'arlo_regions' );
	$post_types_settings = Arlo_For_Wordpress::get_post_types_settings();
	$selected_region     = Arlo_For_Wordpress::get_region_from_query();
	$page_id             = get_query_var( 'page_id', '' );

	$page_obj  = get_queried_object();
	$page_type = '';

	$cookie_time = time() + apply_filters( 'arlo_region_cookie_time', 60 * 60 * 24 * 30 );

	if ( ! empty( $page_obj ) ) {
		$page_type = $page_obj->post_type;
		$page_id   = $page_obj->ID;
	}

	$arlo_page_ids = array();
	foreach ( Arlo_For_Wordpress::$post_types as $id => $arlo_post ) {
		if ( isset( $arlo_post['regionalized'] )
			&& is_bool( $arlo_post['regionalized'] )
			&& $arlo_post['regionalized']
			&& ! empty( $post_types_settings[ $id ]['posts_page'] ) ) {
			$arlo_page_ids[ intval( $post_types_settings[ $id ]['posts_page'] ) ] = $id;
		}
	}

	if ( is_array( $regions ) && count( $regions ) ) {
		$urlparts = wp_parse_url( site_url() );
		$domain   = $urlparts['host'];

		if ( ( ( array_key_exists( $page_id, $arlo_page_ids ) && ! empty( $post_types_settings[ $arlo_page_ids[ $page_id ] ]['posts_page'] ) ) || $page_type == 'arlo_event' ) ) {
			if ( empty( $selected_region ) ) {
				$arlo_region_source = 'default';
				//try to read the region from a cookie
				if ( ! empty( $_COOKIE['arlo-region'] ) && is_scalar( $_COOKIE['arlo-region'] ) ) {
					$cookie_region = sanitize_text_field( wp_unslash( $_COOKIE['arlo-region'] ) );

					if ( in_array( $cookie_region, array_map( 'strval', array_keys( $regions ) ), true ) ) {
						$selected_region    = $cookie_region;
						$arlo_region_source = 'cookie';
					} else {
						$regions_keys    = array_keys( $regions );
						$selected_region = reset( $regions_keys );
					}
				} else {
					$regions_keys    = array_keys( $regions );
					$selected_region = reset( $regions_keys );
				}

				$selected_region = (string) $selected_region;
				setcookie( "arlo-region", $selected_region, $cookie_time, '/', $domain );
				/*
				 * setcookie() only affects subsequent requests. Registered redirect policies
				 * need the resolved region now so cancellation renders region-scoped content.
				 * Without a policy, preserve incoming cookie state and legacy first-visit
				 * rendering on pages that already return without redirecting, such as the front page.
				 */
				if ( has_filter( 'arlo_region_redirect_location' ) ) {
					$_COOKIE['arlo-region'] = wp_slash( $selected_region );
				}

				$page_link = '';
				if ( $page_type == 'arlo_event' ) {
					$page_link = get_post_permalink( $page_id );
				} else {
					$host_page_id = (int) $post_types_settings[ $arlo_page_ids[ $page_id ] ]['posts_page'];
					$page_link    = get_permalink( $host_page_id );
				}

				if ( ! empty( $page_link ) && ! empty( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
					// Guard: a draft/pending page returns a ?page_id= permalink whose path
					// equals the home-url path with no slug segment (and has a non-empty
					// query string). Compare against the home-url path rather than a
					// hard-coded '/' so subdirectory installs are handled correctly.
					$home_path       = rtrim( wp_parse_url( get_home_url(), PHP_URL_PATH ) ?: '', '/' ) . '/';
					$page_link_path  = rtrim( wp_parse_url( $page_link, PHP_URL_PATH ) ?: '', '/' ) . '/';
					$page_link_query = wp_parse_url( $page_link, PHP_URL_QUERY );
					if ( $page_link_path === $home_path && ! empty( $page_link_query ) ) {
						return;
					}

					$request_uri = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ), [ 'http', 'https' ] );
					if ( 0 !== strpos( $request_uri, '/' ) || 0 === strpos( $request_uri, '//' ) ) {
						return;
					}

					$slug = substr( substr( str_replace( get_home_url(), '', $page_link ), 0, -1 ), 1 );

					/*
					 * Rebuild the request with the resolved region. Any region already in the URL, whether
					 * the path segment (/region-US) or the query argument (?arlo-region=US), is stripped
					 * first so it cannot conflict with or repeat the region added here. Other path
					 * segments and query arguments are preserved.
					 */
					$base_location   = \ArloTraining\Utilities::remove_uri_region_query_arg( $request_uri );
					$base_location   = \ArloTraining\Utilities::remove_uri_region_path_segment( $base_location, $slug );
					$target_location = str_replace( $slug, $slug . '/region-' . rawurlencode( $selected_region ), $base_location );
					$arlo_page_type  = arlo_map_region_redirect_page_type( 'arlo_event' === $page_type ? 'arlo_event' : $arlo_page_ids[ $page_id ] );

					/**
					 * Filters the automatic region redirect destination after cookie persistence.
					 *
					 * Return a root-relative path starting with a single slash or an absolute
					 * HTTP(S) URL with a host to redirect. Other formats, non-strings, empty
					 * strings and '0' continue normal WordPress rendering. Query strings and
					 * fragments may accompany supported destinations, but not stand alone.
					 * The resolved cookie is already available to this request's region readers.
					 * Destinations remain subject to WordPress safe-redirect host validation.
					 *
					 * @since 5.2.0
					 * @param string $target_location    Computed site-relative path and query string.
					 * @param string $selected_region    Resolved region key.
					 * @param string $request_uri        Sanitized original request URI.
					 * @param int    $page_id            Host-page or event-entity post ID.
					 * @param string $arlo_region_source 'cookie' for a valid incoming cookie, otherwise 'default'.
					 * @param string $arlo_page_type     'events', 'event', 'upcoming', 'schedule', 'eventsearch' or 'oa';
					 *                                   an empty string for unrecognized/custom internal page keys.
					 */
					$target_location = apply_filters( 'arlo_region_redirect_location', $target_location, $selected_region, $request_uri, (int) $page_id, $arlo_region_source, $arlo_page_type );

					if ( ! is_string( $target_location ) ) {
						return;
					}

					/*
					 * Avoid a self-redirect cycle when different URL spellings identify this same
					 * HTTP resource: fragments, dot segments, WordPress's
					 * trailing-slash form or equivalent origins. Check exact equality before
					 * sanitation can alter query data, then compare normalized path/query and
					 * origin without changing the destination.
					 * Genuine scheme, host, port, path or query changes must remain distinct.
					 */
					$target_location = trim( $target_location );
					if ( '' === $target_location || '0' === $target_location || ! arlo_is_supported_region_redirect_location( $target_location ) || $target_location === $request_uri ) {
						return;
					}

					$target_location = wp_sanitize_redirect( esc_url_raw( $target_location, [ 'http', 'https' ] ) );
					if ( ! arlo_is_supported_region_redirect_location( $target_location ) || $target_location === $request_uri ) {
						return;
					}

					$target_parts  = wp_parse_url( $target_location );
					$request_parts = wp_parse_url( $request_uri );

					$target_path  = arlo_normalize_region_redirect_path( $target_parts['path'] ?? ( isset( $target_parts['host'] ) ? '/' : '' ) );
					$request_path = arlo_normalize_region_redirect_path( $request_parts['path'] ?? '' );
					/*
					 * With pretty permalinks, a callback can send /events/ to /events while
					 * redirect_canonical() sends it back (or vice versa on a no-slash site).
					 * Match paths differing only in trailing slash count, including /events//,
					 * in WordPress's preferred slash form. Internal separators and the
					 * dispatched destination remain untouched.
					 * The checks below still require the same query and origin before cancelling.
					 */
					if ( get_option( 'permalink_structure' )
						&& $target_path !== $request_path
						&& rtrim( $target_path, '/' ) === rtrim( $request_path, '/' ) ) {
						$url_type     = is_single() ? 'single' : 'page';
						$target_path  = user_trailingslashit( $target_path, $url_type );
						$request_path = user_trailingslashit( $request_path, $url_type );
					}

					$target_request_uri     = $target_path . ( isset( $target_parts['query'] ) && '' !== $target_parts['query'] ? '?' . $target_parts['query'] : '' );
					$normalized_request_uri = $request_path . ( isset( $request_parts['query'] ) && '' !== $request_parts['query'] ? '?' . $request_parts['query'] : '' );
					if ( ! isset( $target_parts['host'] ) && ! isset( $target_parts['scheme'] ) && $target_request_uri === $normalized_request_uri ) {
						return;
					}

					$request_scheme = is_ssl() ? 'https' : 'http';
					$request_origin = [];
					if ( isset( $_SERVER['HTTP_HOST'] ) && is_string( $_SERVER['HTTP_HOST'] ) ) {
						$request_host = wp_unslash( $_SERVER['HTTP_HOST'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shape checked above and authority characters validated below; parsed only for self-target comparison, never redirect host authorization.
						if ( '' !== $request_host && false === strpbrk( $request_host, '/\\?#@' ) && ! preg_match( '/[\x00-\x20\x7f]/', $request_host ) ) {
							$request_origin = wp_parse_url( $request_scheme . '://' . $request_host );
						}
					}
					$target_scheme = strtolower( $target_parts['scheme'] ?? '' );
					$request_port  = $request_origin['port'] ?? ( 'https' === $request_scheme ? 443 : 80 );
					$target_port   = $target_parts['port'] ?? ( 'https' === $target_scheme ? 443 : 80 );
					if ( isset( $target_parts['host'], $request_origin['host'] )
						&& strtolower( $target_parts['host'] ) === strtolower( $request_origin['host'] )
						&& $target_scheme === $request_scheme
						&& $target_port === $request_port
						&& $target_request_uri === $normalized_request_uri ) {
						return;
					}

					if ( wp_safe_redirect( $target_location ) ) {
						exit();
					}

					return;
				}
			} else {
				setcookie( "arlo-region", $selected_region, $cookie_time, '/', $domain );
			}
		} else {
			if ( empty( $_COOKIE['arlo-region'] ) ) {
				$regions_keys = array_keys( $regions );
				setcookie( "arlo-region", reset( $regions_keys ), $cookie_time, '/', $domain );
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
function arlo_the_content( $content ) {
	global $post;

	$post_type = str_replace( 'arlo_', '', get_post_type( $post ) );

	if ( function_exists( 'arlo_the_content_' . $post_type ) && is_singular() ) {
		return call_user_func_array( 'arlo_the_content_' . $post_type, func_get_args() );
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
function arlo_the_content_event( $content ) {
	global $post, $wpdb;

	if ( get_option( 'arlo_plugin_disabled', '0' ) == '1' ) {
		return $content;
	}

	$plugin    = Arlo_For_Wordpress::get_instance();
	$import_id = $plugin->get_importer()->get_current_import_id();

	$templates   = arlo_get_option( 'templates' );
	$content     = $templates['event']['html'];
	$arlo_region = \Arlo_For_Wordpress::get_region_parameter();

	$parameters       = array( $post->ID, $import_id );
	$filter_by_region = ! empty( $arlo_region );
	if ( $filter_by_region ) {
		$parameters[] = $arlo_region;
	}

	$sql_event = "
	SELECT
		et.*,
		post.ID as post_id
	FROM
		{$wpdb->prefix}arlo_eventtemplates et
	LEFT JOIN
		{$wpdb->prefix}posts post
	ON
		et.et_post_id = post.ID
	WHERE
		post.post_type = 'arlo_event'
	AND
		post.ID = %d
	AND
		et.import_id = %d
	" . ( $filter_by_region ? " AND et.et_region = %s" : "" ) . "
	ORDER
		BY et.et_name ASC
	";
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
	$prepared_sql = $wpdb->prepare( $sql_event, $parameters );

	$item = CacheControl::fetch_row( $prepared_sql, ARRAY_A );

	$GLOBALS['arlo_eventtemplate'] = $item;

	$GLOBALS['arlo_no_event'] = $GLOBALS['arlo_no_onlineactivity'] = 1;

	$output = do_shortcode( $content );

	unset( $GLOBALS['arlo_eventtemplate'] );

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
function arlo_the_content_presenter( $content ) {
	if ( get_option( 'arlo_plugin_disabled', '0' ) == '1' ) {
		return $content;
	}

	$plugin    = Arlo_For_Wordpress::get_instance();
	$import_id = $plugin->get_importer()->get_current_import_id();

	$templates = arlo_get_option( 'templates' );
	$content   = $templates['presenter']['html'];

	global $post, $wpdb;

	$prepared_sql = $wpdb->prepare(
		"SELECT p.*, post.ID as post_id
		FROM {$wpdb->prefix}arlo_presenters p
		LEFT JOIN {$wpdb->prefix}posts post
		ON p.p_post_id = post.ID
		WHERE post.post_type = 'arlo_presenter' AND post.ID = %d AND p.import_id = %d
		ORDER BY p.p_lastname ASC",
		$post->ID,
		$import_id
	);

	$item = CacheControl::fetch_row( $prepared_sql, ARRAY_A );

	$GLOBALS['arlo_presenter_list_item'] = $item;

	$output = do_shortcode( $content );

	unset( $GLOBALS['arlo_presenter_list_item'] );

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
function arlo_the_content_venue( $content ) {
	if ( get_option( 'arlo_plugin_disabled', '0' ) == '1' ) {
		return $content;
	}

	$plugin    = Arlo_For_Wordpress::get_instance();
	$import_id = $plugin->get_importer()->get_current_import_id();

	$templates = arlo_get_option( 'templates' );
	$content   = $templates['venue']['html'];

	global $post, $wpdb;

	$prepared_sql = $wpdb->prepare(
		"SELECT v.*, post.ID as post_id
		FROM {$wpdb->prefix}arlo_venues v
		LEFT JOIN {$wpdb->prefix}posts post
		ON v.v_post_id = post.ID
		WHERE post.post_type = 'arlo_venue' AND post.ID = %d AND v.import_id = %d
		ORDER BY v.v_name ASC",
		$post->ID,
		$import_id
	);

	$item = CacheControl::fetch_row( $prepared_sql, ARRAY_A );

	$GLOBALS['arlo_venue_list_item'] = $item;

	$output = do_shortcode( $content );

	unset( $GLOBALS['arlo_venue_list_item'] );

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
function arlo_pagination( $num, $limit = null ) {
	// the WordPress posts per page option value
	$limit = intval( is_null( $limit ) ? get_option( 'posts_per_page' ) : $limit );

	$big = 999999999;

	$current = arlo_current_page();

	return paginate_links(
		array(
			'base'     => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
			'format'   => '?paged=%#%',
			'current'  => max( 1, $current ),
			'total'    => ceil( $num / $limit ),
			'mid_size' => 6,
		)
	);
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
	$paged = \ArloTraining\Utilities::filter_string_polyfill( INPUT_GET, 'paged' );
	if ( ! empty( $paged ) ) {
		$page = intval( $paged );
	}
	//the normal one used on post/pages
	elseif ( ! empty( get_query_var( 'paged' ) ) ) {
		$page = intval( get_query_var( 'paged' ) );
	}
	//the one in use for static pages (like Homepage)
	elseif ( ! empty( get_query_var( 'page' ) ) ) {
		$page = intval( get_query_var( 'page' ) );
	}

	return ( $page > 0 ? $page : 1 );
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
function arlo_get_template( $name ) {

	$plugin        = Arlo_For_Wordpress::get_instance();
	$theme_manager = $plugin->get_theme_manager();

	$selected_theme_id = get_option( 'arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME );
	$theme_templates   = $theme_manager->load_default_templates( $selected_theme_id );

	if ( isset( $theme_templates[ $name ] ) && ! empty( $theme_templates[ $name ]['html'] ) ) {
		return $theme_templates[ $name ]['html'];
	}

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
function arlo_get_option( $key, $default = null ) {
	$settings = get_option( 'arlo_settings', array() );

	if ( isset( $settings[ $key ] ) ) {
		return $settings[ $key ];
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
function arlo_set_option( $key, $value = null ) {
	$settings = get_option( 'arlo_settings', array() );

	$settings[ $key ] = $value;

	return update_option( 'arlo_settings', $settings );
}


/**
 * arlo_get_post_by_name function.
 *
 * @access public
 * @param mixed $name
 * @param string $post_type (default: 'post')
 * @return void
 */
function arlo_get_post_by_name( $name, $post_type = 'post' ) {
	$args = array(
		'name'        => $name,
		'post_type'   => $post_type,
		'post_status' => 'publish',
		'numberposts' => 1,
	);

	$posts = get_posts( $args );

	if ( $posts ) {
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

	if ( ! $post ) {
		return false; }

	foreach ( Arlo_For_Wordpress::get_post_types_settings() as $post_type => $config ) {
		if ( $config['posts_page'] == $post->ID ) {
			return true;
		}
	}
	return false;
}

function arlo_add_datamodel() {
	$plugin = Arlo_For_Wordpress::get_instance();

	// error_log("DB Hash before install_schema: " . $plugin->get_schema_manager()->get_plugin_db_schema_hash());

	$plugin->get_schema_manager()->install_schema();

	// error_log("DB Hash after install_schema: " . $plugin->get_schema_manager()->get_plugin_db_schema_hash());
}
