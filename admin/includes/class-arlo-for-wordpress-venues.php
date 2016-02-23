<?php
/**
 * Arlo For Wordpress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      http://arlo.co
 * @copyright 2016 Arlo
 */
 
require_once 'class-arlo-for-wordpress-lists.php';
 

class Arlo_For_Wordpress_Venues extends Arlo_For_Wordpress_Lists  {

	public function __construct() {		
		$this->singular = __( 'Venue', $this->plugin_slug );		
		$this->plural = __( 'Venues', $this->plugin_slug );

		parent::__construct();		
	}
	
	public function set_table_name() {
		$this->table_name = $this->wpdb->prefix . 'arlo_venues';
	}
	
	public function get_columns() {
		return $columns = [
			'v_name'    => __( 'Venue name', $this->plugin_slug ),
			'address' => __( 'Address', $this->plugin_slug ),
			'v_physicaladdresscity'    => __( 'City', $this->plugin_slug ),
			'v_physicaladdresspostcode'    => __( 'Postcode', $this->plugin_slug ),
			'v_physicaladdresscountry'    => __( 'Country', $this->plugin_slug ),
			'v_facilityinfodirections'    => __( 'Directions', $this->plugin_slug ),
			'v_facilityinfoparking'    => __( 'Parking', $this->plugin_slug ),
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
				return $item->$column_name;
			case 'address':
				$address = [];
				for($i = 1; $i<5; $i++) {
					$key = 'v_physicaladdressline' . $i;
					if (!empty($item->$key)) {
						$address[] = $item->$key;
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
            'edit' => sprintf('<a href="https://my.arlo.co/%s/Venues/Venue.aspx?id=%d">Edit</a>', $this->platform_name, $item->v_arlo_id),
            'view' => sprintf('<a href="%s" target="_blank">View</a>', $item->guid),
        );

		return sprintf('%1$s %2$s', $item->v_name, $this->row_actions($actions) );
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
		$where = $this->get_sql_where();
		$where = implode(" AND ", $where);	
	
		return "
		SELECT
			guid,
			v_arlo_id,
			v_name,
			v_physicaladdressline1,
			v_physicaladdressline2,
			v_physicaladdressline3,
			v_physicaladdressline4,
			v_physicaladdresssuburb,
			v_physicaladdresscity,
			v_physicaladdressstate,
			v_physicaladdresspostcode,
			v_physicaladdresscountry,
			v_viewuri,
			v_facilityinfodirections,
			v_facilityinfoparking,
			v_post_name
		FROM
			". $this->table_name . "
		LEFT JOIN 
			" . $this->wpdb->prefix . "posts
		ON
			post_name = v_post_name
		WHERE
			" . $where . "			
		";
	}		
}

?>