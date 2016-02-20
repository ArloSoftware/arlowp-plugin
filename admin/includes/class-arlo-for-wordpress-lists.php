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

class Arlo_For_Wordpress_Lists extends WP_List_Table  {
	protected $wpdb;
	protected $singular;
	protected $plural;
	protected $platform_name;
	protected $order;
	protected $orderby;
	protected $paged;
	protected $table_name;
	protected $active;
	
	const PERPAGE = 15;

	public function __construct() {
		$this->init_variables();	
		
		$this->_column_headers = array($this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns());
		
		$this->init_sql_variables();
		$this->set_table_name();

		parent::__construct( [
			'singular' => $this->singular,
			'plural'   => $this->plural,
			'ajax'     => false
		] );
	}
	
	private function init_variables() {
		global $wpdb;		
	
		$plugin = Arlo_For_Wordpress::get_instance();
		$settings = get_option('arlo_settings');
		
		$this->active = $plugin->last_imported;
		$this->plugin_slug = $plugin->get_plugin_slug();
		$this->version = Arlo_For_Wordpress::VERSION;	
		$this->wpdb = &$wpdb;
		$this->platform_name = $settings['platform_name'];	
	}
	
	private function init_sql_variables() {
		$this->orderby = $this->get_orderby_columnname();
		$this->order = (!empty($_GET['order']) && in_array(strtolower($_GET['order']), ['asc','desc']) ? $_GET['order'] : '');
		
		$this->paged = !empty($_GET["paged"]) ? intval($_GET["paged"]) : 1;	
		$this->paged = ($this->paged <= 0 ? 1 : $this->paged);
	}
	
	private function get_orderby_columnname() {
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
		die( 'function Arlo_For_Wordpress_Lists::column_default() must be over-ridden in a sub-class.' );
	}	
	
	private function get_num_rows() {
		$sql = "
		SELECT 
			COUNT(1) as num
		FROM
			" . $this->table_name . "
		";
		
		$result = $this->wpdb->get_results($sql);
		
		if (is_array($result) && count($result)) {
			return intval($result[0]->num);
		}
		
		return 0;
	}
	
	protected function get_sql_where() {
		return ["active = '" . $this->active . "'"];
	}
		
	public function prepare_items() {
        
		$sql = $this->get_sql_query();
		
		if (!empty($this->orderby)) {
			$sql .= " ORDER BY " . $this->orderby ." ". $this->order;
		}
		
		$limit = ($this->paged-1) * self::PERPAGE;
		$sql .= ' LIMIT ' . $limit . ',' . self::PERPAGE;
		
		$num = $this->get_num_rows();
				
		$this->set_pagination_args( array(
			"total_items" => $num,
			"total_pages" => ceil($num / self::PERPAGE),
			"per_page" => self::PERPAGE,
      	));		
      	
      	$items = $this->wpdb->get_results($sql);
      			
		$this->items = $items;		
	}	
	
	public function set_table_name() {
		die( 'function Arlo_For_Wordpress_Lists::set_table_name() must be over-ridden in a sub-class.' );
	}	
	
	public function get_columns() {
		die( 'function Arlo_For_Wordpress_Lists::get_columns() must be over-ridden in a sub-class.' );
	}	
	
	public function get_hidden_columns() {
        die( 'function Arlo_For_Wordpress_Lists::get_hidden_columns() must be over-ridden in a sub-class.' );
    }	
	
	public function get_sortable_columns() {
		die( 'function Arlo_For_Wordpress_Lists::get_sortable_columns() must be over-ridden in a sub-class.' );
	}	
}

?>