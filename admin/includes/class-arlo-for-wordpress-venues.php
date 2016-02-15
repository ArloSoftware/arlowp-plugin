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
 
if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Arlo_For_Wordpress_Venues extends WP_List_Table  {
	private $wpdb;

	public function __construct() {
		global $wpdb;
	
		$plugin = Arlo_For_Wordpress::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
		$this->version = Arlo_For_Wordpress::VERSION;	
		$this->wpdb = &$wpdb;

		parent::__construct( [
			'singular' => __( 'Venue', $this->plugin_slug ),
			'plural'   => __( 'Venues', $this->plugin_slug ),
			'ajax'     => false
		] );

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
	
	private function get_orderby_columnname($orderby) {
		$orderby = (!empty($_GET['orderby']) ? $_GET['orderby'] : '');
		$columns = $this->_column_headers[2];
		
		if (!empty($orderby)) {
			foreach ($columns as $field_name => $data) {
				if ($data[0] == $orderby)
					return $field_name;
			}
		}
		
		return '';
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
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
			}
	}
	
	function column_v_name($item) {
		$settings = get_option('arlo_settings');
		
		$actions = array(
            'edit' => sprintf('<a href="https://my.arlo.co/%s/Venues/Venue.aspx?id=%d">Edit</a>',$settings['platform_name'],$item->v_arlo_id),
        );

		return sprintf('%1$s %2$s', $item->v_name, $this->row_actions($actions) );
	}	
		
	public function prepare_items() {
		$perpage = get_option('posts_per_page');
		
		$columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        $orderby = $this->get_orderby_columnname();
        $order = (!empty($_GET['order']) && in_array(strtolower($_GET['order']), ['asc','desc']) ? $_GET['order'] : '');
		
		$sql = "
		SELECT
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
			{$this->wpdb->prefix}arlo_venues
		";
		
		if (!empty($orderby)) {
			$sql .= " ORDER BY " . $orderby ." ". $order;
		}
		
		$num = $this->wpdb->num_rows;
		
		$this->set_pagination_args( array(
			"total_items" => $num,
			"total_pages" => ceil($num/$perpage),
			"per_page" => $perpage,
      	));		
      	
      	$items = $this->wpdb->get_results($sql);
      	
      			
		$this->items = $items;
	}	
}

?>