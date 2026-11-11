<?php
namespace ArloTraining\Shortcodes;

use ArloTraining\Entities\Categories as CategoriesEntity;
use ArloTraining\Entities\Tags as TagsEntity;
use ArloTraining\CacheControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class UpcomingEvents {
	public static $upcoming_list_item_atts = [];

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

		$custom_shortcodes = Shortcodes::get_custom_shortcodes( 'upcoming' );

		foreach ( $custom_shortcodes as $shortcode_name => $shortcode ) {
			Shortcodes::add(
				$shortcode_name,
				function ( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
					if ( ! is_array( $atts ) && empty( $atts ) ) {
						$atts = [];
					}
					return self::shortcode_upcoming_list( $content, $atts, $shortcode_name, $import_id );
				}
			);
		}
	}

	private static function shortcode_upcoming_list( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		if ( get_option( 'arlo_plugin_disabled', '0' ) == '1' ) {
			return;
		}

		$filter_settings = get_option( 'arlo_page_filter_settings', [] );

		$template_name = Shortcodes::get_template_name( $shortcode_name, 'upcoming_list', 'upcoming' );
		$templates     = arlo_get_option( 'templates' );
		$content       = $templates[ $template_name ]['html'];

		self::$upcoming_list_item_atts = self::get_upcoming_atts( $atts, $import_id );

		\ArloTraining\Utilities::set_base_filter( $template_name, 'category', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Utilities::convert_string_to_int_array' );
		\ArloTraining\Utilities::set_base_filter( $template_name, 'category', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Utilities::convert_string_to_int_array', null, true );

		\ArloTraining\Utilities::set_base_filter( $template_name, 'templatetag', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Entities\Tags::get_tag_ids_by_tag', [ $import_id ] );
		\ArloTraining\Utilities::set_base_filter( $template_name, 'templatetag', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Entities\Tags::get_tag_ids_by_tag', [ $import_id ], true );

		\ArloTraining\Utilities::set_base_filter( $template_name, 'eventtag', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Entities\Tags::get_tag_ids_by_tag', [ $import_id ] );
		\ArloTraining\Utilities::set_base_filter( $template_name, 'eventtag', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Entities\Tags::get_tag_ids_by_tag', [ $import_id ], true );

		\ArloTraining\Utilities::set_base_filter( $template_name, 'delivery', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Utilities::convert_string_to_int_array' );
		\ArloTraining\Utilities::set_base_filter( $template_name, 'delivery', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Utilities::convert_string_to_int_array', null, true );

		\ArloTraining\Utilities::set_base_filter( $template_name, 'location', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Utilities::convert_string_to_string_array' );
		\ArloTraining\Utilities::set_base_filter( $template_name, 'location', $filter_settings, $atts, self::$upcoming_list_item_atts, '\ArloTraining\Utilities::convert_string_to_string_array', null, true );

		return do_shortcode( $content );
	}

	private static function get_upcoming_atts( $atts, $import_id ) {
		$new_atts = [];

		$templatetag = \ArloTraining\Entities\Tags::get_tag_ids_by_tag( \ArloTraining\Utilities::get_att_string( 'templatetag', $atts ), $import_id );
		$eventtag    = \ArloTraining\Entities\Tags::get_tag_ids_by_tag( \ArloTraining\Utilities::get_att_string( 'eventtag', $atts ), $import_id );

		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'location', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'locationhidden', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'venue', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'category', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'categoryhidden', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'search', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_int', 'delivery', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_int', 'deliveryhidden', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_int', 'templateid', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, null, 'templatetag', $atts, $templatetag );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, null, 'eventtag', $atts, $eventtag );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'presenter', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'month', $atts );
		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\ArloTraining\Utilities::get_att_string', 'state', $atts );

		$new_atts = \ArloTraining\Utilities::process_att( $new_atts, '\Arlo_For_Wordpress::get_region_parameter', 'region' );

		return $new_atts;
	}

	private static function shortcode_upcoming_list_pagination( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		global $wpdb;

		$atts['limit'] = intval( isset( self::$upcoming_list_item_atts['limit'] ) ? self::$upcoming_list_item_atts['limit'] : ( isset( $atts['limit'] ) && is_numeric( $atts['limit'] ) ? $atts['limit'] : get_option( 'posts_per_page' ) ) );

		$atts = array_merge( $atts, self::$upcoming_list_item_atts );

		$sql = self::generate_list_sql( $atts, $import_id, true );

		$items = CacheControl::fetch_results( $sql, ARRAY_A );
		$num   = is_array( $items ) ? count( $items ) : 0;

		return arlo_pagination( $num, $atts['limit'] );
	}

	private static function shortcode_upcoming_widget_list( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		$atts = shortcode_atts(
			array(
				'eventtag'    => '',
				'templatetag' => '',
				'limit'       => '',
			),
			$atts,
			$shortcode_name
		);

		$eventtag_tag = urldecode( $atts['eventtag'] );
		$eventtag_id  = TagsEntity::get_first_id_by_tag( $eventtag_tag, $import_id );

		$templatetag_tag = urldecode( $atts['templatetag'] );
		$templatetag_id  = TagsEntity::get_first_id_by_tag( $templatetag_tag, $import_id );

		self::$upcoming_list_item_atts['eventtag']    = $eventtag_id;
		self::$upcoming_list_item_atts['templatetag'] = $templatetag_id;
		self::$upcoming_list_item_atts['limit']       = $atts['limit'];

		$region = \Arlo_For_Wordpress::get_region_parameter();
		if ( ! empty( $region ) ) {
			self::$upcoming_list_item_atts['region'] = $region;
		}

		$template = $content ? $content : arlo_get_template( 'upcoming_widget' );

		return do_shortcode( $template );
	}

	private static function shortcode_upcoming_list_item( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		global $wpdb;

		if ( ! empty( $atts['limit'] ) ) {
			self::$upcoming_list_item_atts['limit'] = $atts['limit'];
		}

		$settings = (array) get_option( 'arlo_settings', [] );

		$output = '';

		if ( empty( $atts ) ) {
			$atts = [];
		}

		$atts = array_merge( $atts, self::$upcoming_list_item_atts );

		$items = self::fetch_upcoming_list_items( $atts, $import_id );

		if ( empty( $items ) ) :
			$before = isset( $atts['noevent_before'] ) ? wp_kses_post( $atts['noevent_before'] ) : "";
			$after  = isset( $atts['noevent_after'] ) ? wp_kses_post( $atts['noevent_after'] ) : "";

			$no_event_text = ! empty( $settings['noevent_text'] ) ? $settings['noevent_text'] : esc_html__( 'No events to show', 'arlo-training-and-event-management-system' );
			$output        = $before . '<p class="arlo-no-results">' . esc_html( $no_event_text ) . '</p>' . $after;

		else :
			$previous = null;

			$snippet_list_items = array();

			foreach ( $items as $key => $item ) {
				if ( is_null( $previous ) || gmdate( 'm', strtotime( $item['e_startdatetime'] ) ) != gmdate( 'm', strtotime( $previous['e_startdatetime'] ) ) ) {
					$item['show_divider'] = gmdate( 'F Y', strtotime( $item['e_startdatetime'] ) );
				}

				$GLOBALS['arlo_event_list_item'] = $item;
				$GLOBALS['arlo_eventtemplate']   = $item;

				if ( strpos( $content, '[arlo_venue_' ) !== false ) {
					$conditions = array(
						'id' => $item['v_id'],
					);

					$GLOBALS['arlo_venue_list_item'] = \ArloTraining\Entities\Venues::get( $conditions, null, null, $import_id );
				}

				$list_item_snippet             = array();
				$list_item_snippet['@type']    = 'ListItem';
				$list_item_snippet['position'] = $key + 1;
				$list_item_snippet['url']      = $item['e_viewuri'];

				array_push( $snippet_list_items, $list_item_snippet );

				$output .= do_shortcode( $content );

				unset( $GLOBALS['arlo_venue_list_item'] );
				unset( $GLOBALS['arlo_event_list_item'] );
				unset( $GLOBALS['arlo_eventtemplate'] );

				$previous = $item;

			}

			$item_list                    = array();
			$item_list['@type']           = 'ItemList';
			$item_list['itemListElement'] = $snippet_list_items;

			$output .= Shortcodes::create_rich_snippet( $item_list );

		endif;

		return $output;
	}

	private static function shortcode_upcoming_offer( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		$settings      = get_option( 'arlo_settings' );
		$price_setting = ( isset( $settings['price_setting'] ) ) ? $settings['price_setting'] : ARLO_PLUGIN_PREFIX . '-exclgst';
		$free_text     = ( isset( $settings['free_text'] ) ) ? $settings['free_text'] : esc_html__( 'Free', 'arlo-training-and-event-management-system' );

		$amount  = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $GLOBALS['arlo_event_list_item']['o_offeramounttaxexclusive'] : $GLOBALS['arlo_event_list_item']['o_offeramounttaxinclusive'];
		$famount = $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? $GLOBALS['arlo_event_list_item']['o_formattedamounttaxexclusive'] : $GLOBALS['arlo_event_list_item']['o_formattedamounttaxinclusive'];
		$tax     = $GLOBALS['arlo_event_list_item']['o_taxrateshortcode'];
		/* translators: %s: tax */
		$offer = ( $amount > 0 ) ? '<span class="arlo-amount">' . esc_html( $famount ) . '</span> <span class="arlo-price-tax">' . esc_html( ( $price_setting == ARLO_PLUGIN_PREFIX . '-exclgst' ? sprintf( esc_html__( ' excl. %s', 'arlo-training-and-event-management-system' ), $tax ) : sprintf( esc_html__( ' incl. %s', 'arlo-training-and-event-management-system' ), $tax ) ) ) . '</span>'
				: '<span class="arlo-amount">' . esc_html( $free_text ) . '</span>';

		return $offer;
	}

	private static function shortcode_upcoming_event_filters( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		global $post;

		extract(
			shortcode_atts(
				array(
					'filters'     => 'category,month,location,delivery',
					'resettext'   => esc_html__( 'Reset', 'arlo-training-and-event-management-system' ),
					'buttonclass' => 'button',
					'labeltype'   => 'default',
				),
				$atts,
				$shortcode_name
			)
		);

		$filters_array = explode( ',', $filters );

		$page_type = \Arlo_For_Wordpress::get_current_page_arlo_type( 'upcoming' );
		$page_id   = \Arlo_For_Wordpress::get_posts_page_id( $page_type );

		$page_link = $page_id > 0 ? get_permalink( $page_id ) : '';
		if ( empty( $page_link ) ) {
			$page_link = get_permalink( get_post( $post ) );
		}

		$filter_html = '<form class="arlo-filters" method="get" action="' . esc_url( $page_link ) . '">';

		foreach ( \Arlo_For_Wordpress::$available_filters['upcoming']['filters'] as $filter_key => $filter ) :

			$att = ( isset( self::$upcoming_list_item_atts[ $filter_key ] ) && is_string( self::$upcoming_list_item_atts[ $filter_key ] ) ? self::$upcoming_list_item_atts[ $filter_key ] : '' );

			if ( ! in_array( $filter_key, $filters_array, true ) ) {
				continue;
			}

			$items      = Filters::get_filter_options( $filter_key, $import_id );
			$label_dict = \Arlo_For_Wordpress::$filter_labels;
			if ( $labeltype == 'v1' ) {
				$label_dict = \Arlo_For_Wordpress::$filter_labels_v1;
			}
			$filter_html .= Shortcodes::create_filter( $filter_key, $items, $label_dict[ $filter_key ], 'generic', $att, 'upcoming', $atts );
		endforeach;

		$filter_html .= '<div class="arlo-filters-buttons"><input type="hidden" id="arlo-page" value="' . esc_url( $page_link ) . '"> ';
		$filter_html .= '<a href="' . esc_url( $page_link ) . '" class="' . esc_attr( $buttonclass ) . '">' . esc_html( $resettext ) . '</a></div>';

		$filter_html .= '</form>';

		return $filter_html;
	}

	private static function shortcode_upcoming_region_selector( $content = '', $atts = [], $shortcode_name = '', $import_id = '' ) {
		return Shortcodes::create_region_selector( "upcoming" );
	}

	/**
	 * Fetches Upcoming list rows using an ID-first query shape.
	 *
	 * The narrow ID query applies all filters, ordering, and pagination before the
	 * detail query loads the wide event/template/offer payload for only that page.
	 *
	 * @param array $atts Shortcode attributes and merged page filter settings.
	 * @param int|string $import_id Current Arlo import id.
	 * @return array Upcoming event rows keyed as expected by child shortcodes.
	 */
	private static function fetch_upcoming_list_items( $atts, $import_id ) {
		// Keep the expensive wide payload out of the grouped/order-limited query.
		$id_sql  = self::generate_list_sql( $atts, $import_id );
		$id_rows = CacheControl::fetch_results( $id_sql, ARRAY_A );

		if ( empty( $id_rows ) ) {
			return [];
		}

		$ids = self::extract_event_ids_from_rows( $id_rows );
		if ( empty( $ids ) ) {
			return [];
		}

		$sql = self::generate_list_detail_sql( $ids, $import_id );

		return CacheControl::fetch_results( $sql, ARRAY_A );
	}

	/**
	 * Parses the month filter into two dates.
	 *
	 * The Month filter supplies `YYYYMMDD:YYYYMMDD`; templates may also use `YYYY-MM-DD:YYYY-MM-DD`. Both parts must be
	 * real calendar dates written the same way.
	 *
	 * @param mixed $value Month filter value, which comes from the request or a shortcode attribute.
	 * @return array{0: string, 1: string}|null Start and end as `Y-m-d`, or null when the value is not a valid range.
	 */
	private static function parse_month_range( $value ): ?array {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		$parts = explode( ':', urldecode( $value ) );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		$dates = [];
		foreach ( $parts as $part ) {
			// The separator group is repeated with \2 so both separators are either absent or both dashes.
			if ( 1 !== preg_match( '/^(\d{4})(-?)(\d{2})\2(\d{2})$/D', $part, $matches ) || ! checkdate( (int) $matches[3], (int) $matches[4], (int) $matches[1] ) ) {
				return null;
			}

			$dates[] = $matches[1] . '-' . $matches[3] . '-' . $matches[4];
		}

		return $dates;
	}

	/**
	 * Builds the Upcoming filter query for pagination or ID-only reads.
	 *
	 * @param array $atts Shortcode attributes and merged page filter settings.
	 * @param int|string $import_id Current Arlo import id.
	 * @param bool $for_pagination Whether to build the pagination/count basis query.
	 * @return string Prepared Upcoming Events SQL.
	 */
	private static function generate_list_sql( $atts, $import_id, $for_pagination = false ) {
		global $wpdb;
		$parameters = [];

		$limit  = intval( isset( $atts['limit'] ) ? $atts['limit'] : get_option( 'posts_per_page' ) );
		$page   = arlo_current_page();
		$offset = ( $page - 1 ) * $limit;

		$join = [];

		$parameters[] = $import_id;

		$where        = 'WHERE e.e_parent_arlo_id = 0 AND e.import_id = %d';
		$parameters[] = $import_id;

		$arlo_location          = ! empty( $atts['location'] ) ? $atts['location'] : null;
		$arlo_locationhidden    = ! empty( $atts['locationhidden'] ) ? $atts['locationhidden'] : null;
		$arlo_venue             = ! empty( $atts['venue'] ) ? $atts['venue'] : null;
		$arlo_state             = ! empty( $atts['state'] ) ? $atts['state'] : null;
		$arlo_category          = ! empty( $atts['category'] ) ? $atts['category'] : null;
		$arlo_categoryhidden    = ! empty( $atts['categoryhidden'] ) ? $atts['categoryhidden'] : null;
		$arlo_delivery          = isset( $atts['delivery'] ) ? $atts['delivery'] : null;
		$arlo_deliveryhidden    = isset( $atts['deliveryhidden'] ) ? $atts['deliveryhidden'] : null;
		$arlo_month             = ! empty( $atts['month'] ) ? $atts['month'] : null;
		$arlo_eventtag          = ! empty( $atts['eventtag'] ) ? $atts['eventtag'] : null;
		$arlo_eventtaghidden    = ! empty( $atts['eventtaghidden'] ) ? $atts['eventtaghidden'] : null;
		$arlo_templatetag       = ! empty( $atts['templatetag'] ) ? $atts['templatetag'] : null;
		$arlo_templatetaghidden = isset( $atts['templatetaghidden'] ) ? $atts['templatetaghidden'] : null;
		$arlo_presenter         = ! empty( $atts['presenter'] ) ? $atts['presenter'] : null;
		$arlo_region            = ! empty( $atts['region'] ) ? $atts['region'] : null;
		$arlo_templateid        = ! empty( $atts['templateid'] ) ? $atts['templateid'] : null;

		// The month filter is `start:end`. A value that is not two real dates is ignored, as an unexpected region is.
		$month_range = self::parse_month_range( $arlo_month );
		if ( null !== $month_range ) :
			$where       .= ' AND (DATE(e.e_startdatetime) BETWEEN DATE(%s) AND DATE(%s))';
			$parameters[] = $month_range[0];
			$parameters[] = $month_range[1];
		endif;

		if ( isset( $arlo_location ) || isset( $arlo_locationhidden ) ) :
			if ( ! empty( $arlo_location ) ) {
				if ( ! is_array( $arlo_location ) ) {
					$arlo_location = [ $arlo_location ];
				}

				$where     .= " AND e.e_locationname IN (" . implode(
					',',
					array_map(
						function () {
							return "%s";
						},
						$arlo_location
					)
				) . ")";
				$parameters = array_merge( $parameters, $arlo_location );
			}

			if ( ! empty( $arlo_locationhidden ) ) {
				if ( ! is_array( $arlo_locationhidden ) ) {
					$arlo_locationhidden = [ $arlo_locationhidden ];
				}
				$where     .= " AND e.e_locationname NOT IN (" . implode(
					',',
					array_map(
						function () {
							return "%s";
						},
						$arlo_locationhidden
					)
				) . ")";
				$parameters = array_merge( $parameters, $arlo_locationhidden );
			}
		endif;

		if ( ! empty( $arlo_venue ) ) {
			$arlo_venue = \ArloTraining\Utilities::convert_string_to_int_array( $arlo_venue );
			if ( ! empty( $arlo_venue ) ) {
				if ( ! is_array( $arlo_venue ) ) {
					$arlo_venue = [ $arlo_venue ];
				}
				$where     .= " AND e.v_id IN (" . implode(
					',',
					array_map(
						function () {
							return "%s";
						},
						$arlo_venue
					)
				) . ")";
				$parameters = array_merge( $parameters, $arlo_venue );
			}
		}

		if ( ! empty( $arlo_templateid ) ) :
			$where       .= ' AND e.et_arlo_id = %d';
			$parameters[] = $arlo_templateid;
		endif;

		if ( ! empty( $arlo_category ) || ! empty( $arlo_categoryhidden ) ) :
			$arlo_category       = \ArloTraining\Utilities::convert_string_to_int_array( $arlo_category );
			$arlo_categoryhidden = \ArloTraining\Utilities::convert_string_to_int_array( $arlo_categoryhidden );

			$where .= ' AND (';

			if ( ! empty( $arlo_category ) ) {
				$where     .= " c.c_arlo_id IN (" . implode(
					',',
					array_map(
						function () {
							return "%d";
						},
						$arlo_category
					)
				) . ")";
				$parameters = array_merge( $parameters, $arlo_category );
			}

			if ( ! empty( $arlo_categoryhidden ) ) {
				if ( ! empty( $arlo_category ) ) {
					$where .= " AND ";
				}

				//need to exclude all the child categories
				$categoriesnot_flatten_list = CategoriesEntity::get_flattened_category_list_for_filter( $arlo_categoryhidden, [], $import_id );

				if ( count( $categoriesnot_flatten_list ) ) {
					$tag_id_substitutes = implode(
						', ',
						array_map(
							function () {
								return "%d";
							},
							$categoriesnot_flatten_list
						)
					);
					$where             .= " NOT EXISTS( SELECT c_arlo_id FROM {$wpdb->prefix}arlo_eventtemplates_categories WHERE c_arlo_id IN ($tag_id_substitutes) AND et_arlo_id = et.et_arlo_id AND import_id = et.import_id )";
					$parameters         = array_merge(
						$parameters,
						array_map(
							function ( $cat ) {
								return $cat['id'];
							},
							$categoriesnot_flatten_list
						)
					);
				} else {
					$where .= "1 = 1";
				}
			}

			$join['etc'] = " LEFT JOIN
                    {$wpdb->prefix}arlo_eventtemplates_categories AS etc
                        ON
                            etc.et_arlo_id = et.et_arlo_id
                        AND
                            etc.import_id = et.import_id
                        ";
			$join['c']   = "
                    LEFT JOIN
                    {$wpdb->prefix}arlo_categories AS c
                        ON
                            c.c_arlo_id = etc.c_arlo_id
                        AND
                            c.import_id = etc.import_id
                ";

			if ( ( isset( $atts['show_child_elements'] ) && $atts['show_child_elements'] == "true" ) || ( isset( $GLOBALS['arlo_show_child_elements'] ) && $GLOBALS['arlo_show_child_elements'] ) ) {
				$GLOBALS['arlo_show_child_elements'] = true;

				$categories_flatten_list = CategoriesEntity::get_flattened_category_list_for_filter( $arlo_category, $arlo_categoryhidden, $import_id );

				if ( is_array( $categories_flatten_list ) && count( $categories_flatten_list ) ) {
					$where     .= " OR c.c_arlo_id IN (" . implode(
						',',
						array_map(
							function () {
								return "%d";
							},
							$categories_flatten_list
						)
					) . ")";
					$parameters = array_merge(
						$parameters,
						array_map(
							function ( $cat ) {
								return $cat['id'];
							},
							$categories_flatten_list
						)
					);
				}
			}

				$where .= ')';

		endif;

		if ( isset( $arlo_delivery ) || isset( $arlo_deliveryhidden ) ) :
			if ( isset( $arlo_delivery ) ) {
				if ( ! is_array( $arlo_delivery ) ) {
					$arlo_delivery = [ $arlo_delivery ];
				}

				$where     .= " AND e.e_isonline IN (" . implode(
					',',
					array_map(
						function () {
							return "%d";
						},
						$arlo_delivery
					)
				) . ")";
				$parameters = array_merge( $parameters, $arlo_delivery );
			}

			if ( isset( $arlo_deliveryhidden ) ) {
				if ( ! is_array( $arlo_deliveryhidden ) ) {
					$arlo_deliveryhidden = [ $arlo_deliveryhidden ];
				}

				$where     .= " AND e.e_isonline NOT IN (" . implode(
					',',
					array_map(
						function () {
							return "%d";
						},
						$arlo_deliveryhidden
					)
				) . ")";
				$parameters = array_merge( $parameters, $arlo_deliveryhidden );
			}
		endif;

		if ( ! empty( $arlo_state ) ) :
			$join['ce'] = " LEFT JOIN {$wpdb->prefix}arlo_events AS ce ON e.e_arlo_id = ce.e_parent_arlo_id AND e.import_id = ce.import_id ";

			$venues = \ArloTraining\Entities\Venues::get( [ 'state' => $arlo_state ], null, null, $import_id );

			if ( count( $venues ) ) {
				$join['cev'] = " LEFT JOIN {$wpdb->prefix}arlo_venues v ON e.v_id = v.v_arlo_id AND v.import_id = e.import_id ";

				$venues = array_map(
					function ( $venue ) {
						return $venue['v_arlo_id'];
					},
					$venues
				);

				$where     .= " AND (ce.v_id IN (" . implode(
					',',
					array_map(
						function () {
							return "%d";
						},
						$venues
					)
				) . ") OR e.v_id IN (" . implode(
					',',
					array_map(
						function () {
							return "%d";
						},
						$venues
					)
				) . "))";
				$parameters = array_merge( $parameters, $venues );
				$parameters = array_merge( $parameters, $venues );
			}

		endif;

		if ( ! empty( $arlo_eventtag ) || ! empty( $arlo_eventtaghidden ) ) :
			$join['etag'] = " LEFT JOIN {$wpdb->prefix}arlo_events_tags AS etag ON etag.e_id = e.e_id AND etag.import_id = e.import_id";

			if ( ! empty( $arlo_eventtag ) ) {
				$arlo_eventtag = \ArloTraining\Utilities::convert_string_to_string_array( $arlo_eventtag );
				$where        .= " AND etag.tag_id IN (" . implode(
					',',
					array_map(
						function () {
							return "%d";
						},
						$arlo_eventtag
					)
				) . ")";
				$parameters    = array_merge( $parameters, $arlo_eventtag );
			}

			if ( ! empty( $arlo_eventtaghidden ) ) {
				$where     .= " AND (etag.tag_id NOT IN (" . implode(
					',',
					array_map(
						function () {
							return "%d";
						},
						$arlo_eventtaghidden
					)
				) . ") OR etag.tag_id IS NULL)";
				$parameters = array_merge( $parameters, $arlo_eventtaghidden );
			}
		endif;

		if ( ! empty( $arlo_templatetag ) || ! empty( $arlo_templatetaghidden ) ) :
			if ( ! empty( $arlo_templatetag ) ) {
				$join['ettag'] = " LEFT JOIN {$wpdb->prefix}arlo_eventtemplates_tags AS ettag ON ettag.et_id = et.et_id AND ettag.import_id = et.import_id";

				$arlo_templatetag = \ArloTraining\Utilities::convert_string_to_string_array( $arlo_templatetag );
				$where           .= " AND ettag.tag_id IN (" . implode(
					',',
					array_map(
						function () {
							return "%d";
						},
						$arlo_templatetag
					)
				) . ")";
				$parameters       = array_merge( $parameters, $arlo_templatetag );
			}

			if ( ! empty( $arlo_templatetaghidden ) ) {
				$tag_id_substitutes = implode(
					', ',
					array_map(
						function () {
							return "%d";
						},
						$arlo_templatetaghidden
					)
				);
				$where             .= " AND NOT EXISTS( SELECT tag_id FROM {$wpdb->prefix}arlo_eventtemplates_tags WHERE tag_id IN ($tag_id_substitutes) AND et.et_id = et_id AND import_id = et.import_id )";
				$parameters         = array_merge( $parameters, $arlo_templatetaghidden );
			}
		endif;

		if ( ! empty( $arlo_presenter ) ) :
			$join['epresenter'] = " LEFT JOIN {$wpdb->prefix}arlo_events_presenters AS epresenter ON epresenter.e_id = e.e_id AND epresenter.import_id = e.import_id";
			$where             .= " AND p_arlo_id = %d";
			$parameters[]       = intval( current( explode( '-', $arlo_presenter ) ) );
		endif;

		if ( ! empty( $arlo_region ) ) {
			$where       .= ' AND et.et_region = %s AND e.e_region = %s';
			$parameters[] = $arlo_region;
			$parameters[] = $arlo_region;
		}

		$field_list  = '
            e.e_id,
            e.e_locationname
            ';
		$limit_field = $order = '';

		if ( ! $for_pagination ) {
			$field_list = 'e.e_id';

			$order = '
            ORDER BY
                e.e_startdatetime, e.e_id';

			$limit_field = "
            LIMIT
                %d, %d";
		}

		$sql = "
        SELECT DISTINCT
            $field_list
        FROM
            {$wpdb->prefix}arlo_events e
        LEFT JOIN
            {$wpdb->prefix}arlo_eventtemplates et
        ON
            e.et_arlo_id = et.et_arlo_id
        AND
            et.import_id = e.import_id
        INNER JOIN
            (SELECT
                e_id,
                import_id
            FROM
                {$wpdb->prefix}arlo_offers
            WHERE
                o_order = 1
            AND
                import_id = %d
            ) o
        ON
            e.e_id = o.e_id
        " . implode( "\n", $join ) . "
        $where
        GROUP BY
            et.et_arlo_id, e.e_id
        $order
        $limit_field";

		if ( ! empty( $limit_field ) ) {
			$parameters[] = $offset;
			$parameters[] = $limit;
		}

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
		$query = $wpdb->prepare( $sql, $parameters );

		if ( $query ) {
			return $query;
		} else {
			throw new \Exception( "Couldn't prepare SQL statement" );
		}
	}

	/**
	 * Extracts clean positive event ids from ID-query result rows.
	 *
	 * @param array $rows Rows returned by the ID query.
	 * @return int[] Unique positive event row ids, preserving first occurrence order.
	 */
	private static function extract_event_ids_from_rows( $rows ) {
		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_values(
			array_unique(
				array_filter(
					array_map( 'intval', array_column( $rows, 'e_id' ) ),
					function ( $id ) {
						return $id > 0;
					}
				)
			)
		);
	}

	/**
	 * Builds the detail query for a selected page of event ids.
	 *
	 * @param int[] $ids Clean positive event row ids selected by the ID query.
	 * @param int|string $import_id Current Arlo import id.
	 * @return string Prepared SQL for the selected event detail rows.
	 * @throws \InvalidArgumentException If no valid event ids are provided.
	 */
	private static function generate_list_detail_sql( $ids, $import_id ) {
		global $wpdb;

		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					function ( $id ) {
						return $id > 0;
					}
				)
			)
		);
		if ( empty( $ids ) ) {
			throw new \InvalidArgumentException( 'Event IDs are required to build Upcoming detail SQL.' );
		}

		$id_placeholders = implode(
			',',
			array_map(
				function () {
					return '%d';
				},
				$ids
			)
		);
		$parameters      = array_merge( [ $import_id, $import_id ], $ids );

		$sql = "
        SELECT DISTINCT
            " . self::get_list_detail_fields() . "
        FROM
            {$wpdb->prefix}arlo_events e
        LEFT JOIN
            {$wpdb->prefix}arlo_eventtemplates et
        ON
            e.et_arlo_id = et.et_arlo_id
        AND
            et.import_id = e.import_id
        INNER JOIN
            {$wpdb->prefix}arlo_offers o
        ON
            e.e_id = o.e_id
        AND
            o.o_order = 1
        AND
            o.import_id = %d
        WHERE
            e.import_id = %d
        AND
            e.e_id IN ($id_placeholders)
        GROUP BY
            et.et_arlo_id, e.e_id
        ORDER BY
            e.e_startdatetime, e.e_id";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
		return $wpdb->prepare( $sql, $parameters );
	}

	/**
	 * Returns the wide field list required by Upcoming item templates.
	 *
	 * @return string SQL select-list fragment for event, template, and offer fields.
	 */
	private static function get_list_detail_fields() {
		return '
            e.e_id,
            e.e_arlo_id,
            e.et_arlo_id,
            e.e_code,
            e.e_name,
            e.e_startdatetime,
            e.e_finishdatetime,
            e.e_startdatetimeoffset,
            e.e_finishdatetimeoffset,
            e.e_starttimezoneabbr,
            e.e_finishtimezoneabbr,
            e.e_timezone_id,
            e.v_id,
            e.e_locationname,
            e.e_locationroomname,
            e.e_locationvisible,
            e.e_isfull,
            e.e_placesremaining,
            e.e_sessiondescription,
            e.e_notice,
            e.e_viewuri,
            e.e_registermessage,
            e.e_registeruri,
            e.e_providerorganisation,
            e.e_providerwebsite,
            e.e_isonline,
            e.e_parent_arlo_id,
            e.e_region,
            e.e_is_taxexempt,
            e.e_credits,
            et.et_id,
            et.et_code,
            et.et_name,
            et.et_post_name,
            et.et_post_id,
            et.et_descriptionsummary,
            et.et_advertised_duration,
            et.et_registerinteresturi,
            et.et_registerprivateinteresturi,
            et.et_credits,
            et.et_region,
            et.et_viewuri,
            et.et_list_image,
            o.o_formattedamounttaxexclusive,
            o.o_offeramounttaxexclusive,
            o.o_formattedamounttaxinclusive,
            o.o_offeramounttaxinclusive,
            o.o_taxrateshortcode
            ';
	}
}
