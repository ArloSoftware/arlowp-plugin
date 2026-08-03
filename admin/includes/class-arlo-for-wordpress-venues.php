<?php
/**
 * Arlo For Wordpress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      https://arlo.co
 * @copyright 2018 Arlo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'class-arlo-for-wordpress-lists.php';
 

class Arlo_For_Wordpress_Venues extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_venues';

	public function __construct() {		
		$this->singular = __( 'Venue', 'arlo-training-and-event-management-system' );		
		$this->plural = __( 'Venues', 'arlo-training-and-event-management-system' );

		parent::__construct();		
	}
	
	public function get_title() {
		$title = parent::get_title();

		$v_e_id = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, 'v_e_id');

		if (!empty($v_e_id) && !empty(self::$filter_column_mapping['v_e_id']) && intval($v_e_id) > 0 && !empty($this->items[0]->e_name)) {
			$title .= ' for event: ' . esc_html( $this->items[0]->e_name );
		}
		
		return $title;
	}
	
	public function get_columns() {
		return $columns = [
			'v_name'    => esc_html__( 'Venue name', 'arlo-training-and-event-management-system' ),
			'address' => esc_html__( 'Address', 'arlo-training-and-event-management-system' ),
			'v_physicaladdresscity'    => esc_html__( 'City', 'arlo-training-and-event-management-system' ),
			'v_physicaladdresspostcode'    => esc_html__( 'Postcode', 'arlo-training-and-event-management-system' ),
			'v_physicaladdresscountry'    => esc_html__( 'Country', 'arlo-training-and-event-management-system' ),
			'v_facilityinfodirections'    => esc_html__( 'Directions', 'arlo-training-and-event-management-system' ),
			'v_facilityinfoparking'    => esc_html__( 'Parking', 'arlo-training-and-event-management-system' ),
		];
	}	
	
	public function get_hidden_columns() {
        return array();
    }	
	
	public function get_sortable_columns() {
		return array(
			'v_name' => array( 'name', true ),
			'v_physicaladdresscity' => array( 'city', false ),
			'v_physicaladdressstate' => array( 'state', false ),
			'v_physicaladdresspostcode' => array( 'postcode', false ),
			'v_physicaladdresscountry' => array( 'country', false )
		);
	}
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'v_name':
			case 'v_physicaladdressstate':
			case 'v_physicaladdresspostcode':
			case 'v_physicaladdresscountry':
			case 'v_physicaladdresscity':
				return esc_html($item->$column_name);
			case 'address':
				$address = [];
				for($i = 1; $i<5; $i++) {
					$key = 'v_physicaladdressline' . $i;
					if (!empty($item->$key)) {
						$address[] = esc_html($item->$key);
					}
				}
				return implode(', ', $address);
			case 'v_facilityinfodirections':
			case 'v_facilityinfoparking':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . esc_html( wp_strip_all_tags( $item->$column_name ) ) . '</div>';
				
				break;
			default:
				return '';
			}
	}
	
	public function column_v_name($item) {
		$actions = array(
            'edit' => sprintf('<a href="%s" target="_blank">Edit</a>', esc_url(sprintf('https://%s/management/Venues/Venue.aspx?id=%d',$this->platform_url, $item->v_arlo_id ))), // TODO: still uses legacy .aspx route — update when Console URL is confirmed
            'view' => sprintf('<a href="%s" target="_blank">View</a>', esc_url($item->guid)),
        );

		return sprintf('%1$s %2$s', esc_html($item->v_name), $this->row_actions($actions) );
	}
	
	protected function get_sql_where_array() {
		$where = [
			"v.import_id = %d" ,
		];
		$parameter = [$this->import_id];
		return array(
			'where' => $where,
			'parameter' => $parameter
		);
	}
	
	protected function get_searchable_fields() {
		return [
			'v_name',
			'v_physicaladdressline1',
			'v_physicaladdressline2',
			'v_physicaladdressline3',
			'v_physicaladdresssuburb',
			'v_physicaladdresscity',
			'v_physicaladdressstate',
			'v_physicaladdresspostcode',
			'v_physicaladdresscountry',
			'v_facilityinfodirections',
			'v_facilityinfoparking',
		];
	}	

	protected function get_total_items_count() {
		global $wpdb;

		$where = $this->get_sql_where_expression();
		$sql = "
		SELECT
			COUNT(DISTINCT v.v_arlo_id)
		FROM
			{$wpdb->prefix}arlo_venues AS v
		LEFT JOIN
			{$wpdb->prefix}arlo_events AS e
		ON
			v.v_arlo_id = e.v_id
		AND
			e.import_id = v.import_id
		WHERE
			" . $where['where'];

		$row = \ArloTraining\CacheControl::fetch_row(
			\ArloTraining\Utilities::prepare_sql($sql, $where['parameter']),
			ARRAY_N
		);

		return !empty($row) ? intval($row[0]) : 0;
	}
	
	public function get_sql_query() {
		global $wpdb;
		$where = $this->get_sql_where_expression();
	
		$sql_venues = "
		SELECT
			guid,
			v.v_arlo_id,
			v.v_name,
			v.v_physicaladdressline1,
			v.v_physicaladdressline2,
			v.v_physicaladdressline3,
			v.v_physicaladdressline4,
			v.v_physicaladdresssuburb,
			v.v_physicaladdresscity,
			v.v_physicaladdressstate,
			v.v_physicaladdresspostcode,
			v.v_physicaladdresscountry,
			v.v_viewuri,
			v.v_facilityinfodirections,
			v.v_facilityinfoparking,
			v.v_post_name,
			v.v_post_id,
			e.e_name
		FROM
			{$wpdb->prefix}arlo_venues AS v
		LEFT JOIN 
			{$wpdb->prefix}arlo_events AS e
		ON
			v.v_arlo_id = e.v_id
		AND
			e.import_id = v.import_id
		LEFT JOIN 
			{$wpdb->prefix}posts
		ON
			ID = v_post_id
		WHERE
			" . $where['where'] . "	
		GROUP BY
			v.v_arlo_id		
		";
		return \ArloTraining\Utilities::prepare_sql($sql_venues, $where['parameter']);
	}
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Console/#/venues/new/', $this->platform_url) );
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Console/#/venues/', $this->platform_url) );
	}
		
}
