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
 
require_once 'class-arlo-for-wordpress-lists.php';
 

class Arlo_For_Wordpress_Venues extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_venues';

	public function __construct() {		
		$this->singular = __( 'Venue', 'arlo-for-wordpress' );		
		$this->plural = __( 'Venues', 'arlo-for-wordpress' );

		parent::__construct();		
	}
	
	public function set_table_name() {
		$this->table_name = $this->wpdb->prefix . self::TABLENAME . ' AS v';
	}
	
	public function get_title() {
		$title = parent::get_title();

		$v_e_id = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'v_e_id');

		if (!empty($v_e_id) && !empty(self::$filter_column_mapping['v_e_id']) && intval($v_e_id > 0) && !empty($this->items[0]->e_name)) {
			$title .= ' for event: ' . $this->items[0]->e_name;
		}
		
		return $title;
	}
	
	public function get_columns() {
		return $columns = [
			'v_name'    => __( 'Venue name', 'arlo-for-wordpress' ),
			'address' => __( 'Address', 'arlo-for-wordpress' ),
			'v_physicaladdresscity'    => __( 'City', 'arlo-for-wordpress' ),
			'v_physicaladdresspostcode'    => __( 'Postcode', 'arlo-for-wordpress' ),
			'v_physicaladdresscountry'    => __( 'Country', 'arlo-for-wordpress' ),
			'v_facilityinfodirections'    => __( 'Directions', 'arlo-for-wordpress' ),
			'v_facilityinfoparking'    => __( 'Parking', 'arlo-for-wordpress' ),
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
					return '<div class="arlo-list-ellipsis">' . strip_tags($item->$column_name) . '</div>';
				
				break;
			default:
				return '';
			}
	}
	
	public function column_v_name($item) {
		$actions = array(
            'edit' => sprintf(sprintf(esc_url('<a href="https://%s/management/Venues/Venue.aspx?id=%d" target="_blank">Edit</a>', $this->platform_url, $item->v_arlo_id))),
            'view' => sprintf('<a href="%s" target="_blank">View</a>', $item->guid),
        );

		return sprintf('%1$s %2$s', esc_html($item->v_name), $this->row_actions($actions) );
	}
	
	protected function get_sql_where_array() {
		return [
			"v.import_id = " . $this->import_id,
		];
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
	
	public function get_sql_query() {
		$where = $this->get_sql_where_expression();
	
		return "
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
			". $this->table_name . "
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_events AS e
		ON
			v.v_arlo_id = e.v_id							
		LEFT JOIN 
			" . $this->wpdb->prefix . "posts
		ON
			ID = v_post_id
		WHERE
			" . $where . "	
		GROUP BY
			v.v_arlo_id		
		";
	}	
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Venues/Venue.aspx?i=1', $this->platform_url) );
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Venues/Venues.aspx', $this->platform_url) );
	}
		
}
