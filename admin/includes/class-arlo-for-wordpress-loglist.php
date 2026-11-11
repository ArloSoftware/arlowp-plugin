<?php
/**
 * Arlo For WordPress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo Software <support@arlo.co>
 * @license   GPL-2.0-or-later
 * @link      https://arlo.co
 * @copyright 2015-2026 Arlo Software
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use ArloTraining\Logger;
use ArloTraining\Utilities;

class Arlo_For_Wordpress_LogList extends WP_List_Table {

	public $singular;
	public $plural;

	protected $order;
	protected $orderby;
	protected $paged;
	protected $plugin_slug;

	const PERPAGE   = 30;
	const TABLENAME = 'arlo_log';

	public function __construct() {
		$this->init_variables();

		$this->singular = __( 'Log entry', 'arlo-training-and-event-management-system' );
		$this->plural   = __( 'Log entries', 'arlo-training-and-event-management-system' );

		$this->_column_headers = array( $this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns() );

		$this->init_sql_variables();

		parent::__construct(
			[
				'singular' => $this->singular,
				'plural'   => $this->plural,
				'ajax'     => false,
			]
		);

		$this->prepare_items();
	}

	private function init_variables() {
		$plugin   = Arlo_For_Wordpress::get_instance();
		$settings = get_option( 'arlo_settings' );

		$this->plugin_slug = $plugin->plugin_slug;
	}

	private function init_sql_variables() {
		$order = \ArloTraining\Utilities::filter_string_polyfill( INPUT_GET, 'order' );
		$paged = \ArloTraining\Utilities::filter_string_polyfill( INPUT_GET, 'paged' );

		$this->orderby = $this->get_orderby_columnname();
		$this->order   = ( ! empty( $order ) && in_array( strtolower( $order ), [ 'asc', 'desc' ], true ) ? $order : 'desc' );

		$this->paged = ! empty( $paged ) ? intval( $paged ) : 1;
		$this->paged = ( $this->paged <= 0 ? 1 : $this->paged );
	}

	private function get_orderby_columnname() {
		$orderby = \ArloTraining\Utilities::filter_string_polyfill( INPUT_GET, 'orderby' );
		$orderby = ( ! empty( $orderby ) ? $orderby : 'id' );
		$columns = $this->_column_headers[2];

		if ( ! empty( $orderby ) ) {
			foreach ( $columns as $field_name => $data ) {
				if ( $data[0] == $orderby ) {
					return $field_name;
				}
			}
		}

		return '';
	}

	public function get_title() {
		return get_admin_page_title();
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'message':
			case 'created':
				return esc_html( $item->$column_name );

			case 'diagnostic_level':
				return esc_html( Logger::get_diagnostic_level_label( $item->$column_name ) );

			case 'import_id':
				if ( $item->$column_name != '0' ) {
					return esc_html( $item->$column_name );
				}
			default:
				return '';
		}
	}

	private function get_sql_search_where_array() {
		global $wpdb;
		$where     = array();
		$parameter = array();

		$s = \ArloTraining\Utilities::filter_string_polyfill( INPUT_GET, 's' );

		if ( ! empty( $s ) ) {
			$search_fields = $this->get_searchable_fields();
			foreach ( $search_fields as $field ) {
				$where[]     = $field . " LIKE %s";
				$parameter[] = '%' . $wpdb->esc_like( wp_unslash( $s ) ) . '%';
			}
		}
		return array(
			'where'     => $where,
			'parameter' => $parameter,
		);
	}

	protected function get_sql_where_expression() {
		$search = $this->get_sql_search_where_array();
		$where  = '';
		if ( count( $search['where'] ) ) {
			$where = " (" . implode( " OR ", $search['where'] ) . ")";
		}

		return array(
			'where'     => ! empty( $where ) ? $where : '1',
			'parameter' => $search['parameter'],
		);
	}

	public function get_sql_query() {
		global $wpdb;
		$where = $this->get_sql_where_expression();

		$sql_log = "
		SELECT
			id,
			import_id,
			message,
			created,
			diagnostic_level
		FROM
			{$wpdb->prefix}arlo_log
		WHERE
			" . $where['where'] . "
		";
		return Utilities::prepare_sql( $sql_log, $where['parameter'] );
	}

	private function get_num_rows() {
		global $wpdb;
		$where = $this->get_sql_where_expression();
		$sql   = "
		SELECT
			COUNT(*)
		FROM
			{$wpdb->prefix}arlo_log
		WHERE
			" . $where['where'] . "
		";
		$sql   = Utilities::prepare_sql( $sql, $where['parameter'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is prepared through Utilities::prepare_sql(); the admin list count must reflect the live table so pagination does not strand users on stale pages.
		$result = $wpdb->get_var( $sql );

		return is_null( $result ) ? 0 : intval( $result );
	}

	public function prepare_items() {
		global $wpdb;
		$sql = $this->get_sql_query();

		if ( ! empty( $this->orderby ) ) {
			$sql .= ' ORDER BY ' . $this->orderby . ' ' . $this->order;
		}
		$num         = $this->get_num_rows();
		$total_pages = (int) ceil( $num / self::PERPAGE );
		$last_page   = max( 1, $total_pages );
		if ( $this->paged > $last_page ) {
			$this->paged = $last_page;
		}

		$limit = ( $this->paged - 1 ) * self::PERPAGE;
		$sql  .= ' LIMIT %d,%d';

		$this->set_pagination_args(
			array(
				"total_items" => $num,
				"total_pages" => $total_pages,
				"per_page"    => self::PERPAGE,
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql already contains prepared filter values from get_sql_query(); only the LIMIT placeholders are bound here. The list must read the live page after pagination is clamped.
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $limit, self::PERPAGE ), OBJECT );

		$this->items = $items;
	}

	protected function get_searchable_fields() {
		return [
			'message',
			'import_id',
		];
	}

	public function get_columns() {
		return $columns = [
			'id'               => esc_html__( 'ID', 'arlo-training-and-event-management-system' ),
			'import_id'        => esc_html__( 'Import ID', 'arlo-training-and-event-management-system' ),
			'message'          => esc_html__( 'Message', 'arlo-training-and-event-management-system' ),
			'diagnostic_level' => esc_html__( 'Diagnostic level', 'arlo-training-and-event-management-system' ),
			'created'          => esc_html__( 'Created date (UTC)', 'arlo-training-and-event-management-system' ),
		];
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
		return array(
			'id'        => array( 'id', true ),
			'import_id' => array( 'id', true ),
			'created'   => array( 'created', true ),
		);
	}
}
