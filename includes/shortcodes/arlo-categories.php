<?php
namespace ArloTraining\Shortcodes;

use ArloTraining\Entities\Categories as CategoriesEntity;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Categories {
	/**
	 * Depth used when depth="0" asks for every level. It bounds the recursion if imported parent links ever form a cycle.
	 */
	private const ALL_LEVELS_DEPTH = 100;

	public static function init() {
		$class = new \ReflectionClass( __CLASS__ );

		$shortcodes = array_filter(
			$class->getMethods(),
			function ( $method ) {
				return strpos( $method->name, 'shortcode_' ) === 0;
			}
		);

		foreach ( $shortcodes as $shortcode ) {
			$shortcode_name = str_replace( 'shortcode_', '', $shortcode->name );

			Shortcodes::add(
				$shortcode_name,
				function ( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
					$method_name = 'shortcode_' . str_replace( 'arlo_', '', $shortcode_name );
					if ( ! is_array( $atts ) && empty( $atts ) ) {
						$atts = [];
					}
					return self::$method_name( $content, $atts, $shortcode_name, $import_id );
				}
			);
		}
	}

	private static function shortcode_categories( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		$return = '';

		$arlo_categories = self::get_selected_categories();

		// Determine depth: the number of levels of child categories listed beneath the
		// current category (or the root). The default of 1 lists only its direct children.
		// An explicit 0 displays all levels, as the Categories widget describes. Any other
		// value that is not a positive number, such as "abc" or a negative number, lists nothing.
		$depth = 1;
		if ( isset( $atts['depth'] ) ) {
			$raw_depth = is_scalar( $atts['depth'] ) ? trim( (string) $atts['depth'] ) : '';
			$depth     = preg_match( '/^0+$/', $raw_depth ) ? self::ALL_LEVELS_DEPTH : (int) $raw_depth;
		}

		// show title?
		$title = ( isset( $atts['title'] ) ) ? wp_kses_post( $atts['title'] ) : null;

		// show counts
		$counts = ( isset( $atts['counts'] ) ) ? $atts['counts'] : null;

		//from widget
		$widget = ( isset( $atts['widget'] ) ) ? $atts['widget'] == "true" : false;

		// start at
		$start_at = ( isset( $atts['parent'] ) ) ? (int) $atts['parent'] : 0;

		if ( is_array( $arlo_categories ) ) {
			if ( count( $arlo_categories ) > 1 ) {
				$title = str_replace( '%s', '', $title );
			}
			$page_type = \Arlo_For_Wordpress::get_current_page_arlo_type();
			$page_type = ( $page_type == 'event' ? 'events' : $page_type );

			$page_filter_settings = get_option( "arlo_page_filter_settings" );
			$ignored_categories   = ( isset( $page_filter_settings['hiddenfilters'][ $page_type ]['category'] ) ? $page_filter_settings['hiddenfilters'][ $page_type ]['category'] : [] );

			foreach ( $arlo_categories as $i => $arlo_category ) {
				if ( ! isset( $atts['parent'] ) && $start_at == 0 && ! empty( $arlo_category ) ) {
					$slug     = $arlo_category;
					$start_at = intval( current( explode( '-', $slug ) ) );
				}

				if ( $title && $i == 0 ) {
					$conditions = array( 'id' => $start_at );

					if ( $start_at == 0 ) {
						$conditions = array( 'parent_id' => 0 );
					}

					$current = CategoriesEntity::get( $conditions, 1, $import_id );

					$return .= wp_kses_post( Shortcodes::apply_placeholder( $title, esc_html( $current->c_name ) ) );
				}

				if ( $depth > 0 ) {

					if ( $start_at == 0 ) {
						$root = CategoriesEntity::getTree( $start_at, 1, 0, $ignored_categories, $import_id );

						if ( ! empty( $root ) ) {
							$start_at = $root[0]->c_arlo_id;
						}
					}

					$tree = CategoriesEntity::getTree( $start_at, $depth, 0, $ignored_categories, $import_id );

					$GLOBALS['arlo_categories_count'] = ( empty( $tree ) ? 0 : count( $tree ) );

					if ( ! empty( $tree ) ) {
						$return .= self::generate_category_html( $tree, $counts, $widget );
					}
				}
				$start_at = 0;
			}
		}

		return $return;
	}

	private static function shortcode_category_breadcrumb( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		$return = '';

		$divider                               = isset( $atts['divider'] ) ? wp_kses_post( $atts['divider'] ) : '';
		$allowed_item_html                     = wp_kses_allowed_html( 'post' );
		$allowed_item_html['span']['tabindex'] = true;
		$wrap                                  = isset( $atts['item'] ) ? wp_kses( $atts['item'], $allowed_item_html ) : '%s';

		$selected_categories = self::get_selected_categories();

		$items   = CategoriesEntity::get( array(), null, $import_id );
		$dict    = array();
		$current = null;
		$index   = 0;
		foreach ( $items as $item ) {
			$dict[ $item->c_arlo_id ] = $item;
			if ( count( $selected_categories ) > 0 && $selected_categories[0] == $item->c_arlo_id ) {
				$return  = str_replace( [ '{slug}', '{label}' ], [ esc_attr( $item->c_slug ), esc_html( $item->c_name ) ], $wrap );
				$current = $item;
			} elseif ( count( $selected_categories ) > 0 && $selected_categories[0] == 0 && $item->c_parent_id == 0 ) {
				$return = str_replace( [ '{slug}', '{label}' ], [ esc_attr( $item->c_slug ), esc_html( $item->c_name ) ], $wrap );
			}
		}
		while ( $current != null ) {
			if ( array_key_exists( $current->c_parent_id, $dict ) ) {
				$current = $dict[ $current->c_parent_id ];
				$return  = str_replace(
					[ '{slug}', '{label}' ],
					[ esc_attr( $current->c_slug ), esc_html( $current->c_name ) ],
					$wrap
				) . $divider . $return;
			} else {
				$current = null;
			}
		}

		// Sanitise before substitution so attributes are quoted and values stay whole, and again after because markup and platform values can combine into a URL scheme that neither passes alone.
		return wp_kses( $return, $allowed_item_html );
	}

	private static function shortcode_category_title( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		$selected_categories = self::get_selected_categories();
		$arlo_category       = array_shift( $selected_categories );

		if ( ! empty( $arlo_category ) ) {
			$category = CategoriesEntity::get( array( 'id' => current( explode( '-', $arlo_category ) ) ), 1, $import_id );
		} else {
			$category = CategoriesEntity::get( array( 'parent_id' => 0 ), 1, $import_id );
		}

		if ( ! $category ) {
			return;
		}

		return esc_html( $category->c_name );
	}

	private static function shortcode_category_header( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		$selected_categories = self::get_selected_categories();
		$arlo_category       = array_shift( $selected_categories );

		if ( ! empty( $arlo_category ) ) {
			$category = CategoriesEntity::get( array( 'id' => current( explode( '-', $arlo_category ) ) ), 1, $import_id );
		} else {
			$category = CategoriesEntity::get( array( 'parent_id' => 0 ), 1, $import_id );
		}

		if ( ! $category ) {
			return;
		}

		return wp_kses_post( (string) $category->c_header );
	}

	private static function shortcode_category_footer( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		$selected_categories = self::get_selected_categories();
		$arlo_category       = array_shift( $selected_categories );

		if ( ! empty( $arlo_category ) ) {
			$category = CategoriesEntity::get( array( 'id' => current( explode( '-', $arlo_category ) ) ), 1, $import_id );
		} else {
			$category = CategoriesEntity::get( array( 'parent_id' => 0 ), 1, $import_id );
		}

		if ( ! $category ) {
			return;
		}

		return wp_kses_post( (string) $category->c_footer );
	}

	private static function get_selected_categories() {
		$category_atts = ! empty( \ArloTraining\Shortcodes\Templates::$event_template_atts ) ? \ArloTraining\Shortcodes\Templates::$event_template_atts :
		( ! empty( \ArloTraining\Shortcodes\UpcomingEvents::$upcoming_list_item_atts ) ? \ArloTraining\Shortcodes\UpcomingEvents::$upcoming_list_item_atts :
		( ! empty( \ArloTraining\Shortcodes\OnlineActivities::$oa_list_atts ) ? \ArloTraining\Shortcodes\OnlineActivities::$oa_list_atts :
		null ) );

		return \ArloTraining\Utilities::convert_string_to_int_array( \ArloTraining\Utilities::get_att_string( 'category', $category_atts ) );
	}

	// category list
	private static function generate_category_html( $items, $counts, $widget = false ) {
		$page_type = \Arlo_For_Wordpress::get_current_page_arlo_type();

		if ( empty( $page_type ) || $widget ) {
			$page_type = 'event';
		}

		// Resolved on every top-level call, not kept in a static, so a changed page mapping is never served stale.
		$page_id    = \Arlo_For_Wordpress::get_posts_page_id( $page_type );
		$events_url = $page_id > 0 ? get_page_link( $page_id ) : '';

		if ( empty( $events_url ) ) {
			return null;
		}

		return self::generate_category_html_items( $items, $counts, $events_url, \Arlo_For_Wordpress::get_region_parameter() );
	}

	// Recursive part of generate_category_html(), which passes the events page URL and region down so each is resolved once per list.
	private static function generate_category_html_items( $items, $counts, $events_url, $arlo_region ) {
		if ( ! is_array( $items ) || empty( $items ) ) {
			return '';
		}

		$html = '<ul class="arlo-category-list">';

		foreach ( $items as $cat ) {
			$href     = $events_url . ( ! empty( $arlo_region ) ? 'region-' . rawurlencode( $arlo_region ) . '/' : '' ) . ( $cat->c_parent_id != 0 ? 'cat-' . $cat->c_slug : '' );
			$cat_name = $cat->c_name . ( ! is_null( $counts ) ? Shortcodes::apply_placeholder( (string) $counts, (string) $cat->c_template_num, 'd' ) : '' );
			$child_li = ( isset( $cat->children ) ? self::generate_category_html_items( $cat->children, $counts, $events_url, $arlo_region ) : '' );

			$html .= sprintf( '<li><a href="%s">%s</a>%s</li>', esc_url( $href ), esc_html( $cat_name ), $child_li );
		}

		$html .= '</ul>';

		return $html;
	}
}
