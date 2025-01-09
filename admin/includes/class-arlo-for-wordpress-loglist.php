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
 
if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Arlo_For_Wordpress_LogList extends WP_List_Table  {
	
	public $singular;
	public $plural;
	public $table_name;

	protected $wpdb;
	protected $order;
	protected $orderby;
	protected $paged;
	protected $plugin_slug;
	
	const PERPAGE = 30;
	const TABLENAME = 'arlo_log';

	public function __construct() {
		$this->init_variables();	
		$this->table_name =  $this->wpdb->prefix . self::TABLENAME;
		
		$this->singular = __( 'Log entry', 'arlo-for-wordpress' );		
		$this->plural = __( 'Log entries', 'arlo-for-wordpress' ); 
		
		$this->_column_headers = array($this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns());
		
		$this->init_sql_variables();

		parent::__construct( [
			'singular' => $this->singular,
			'plural'   => $this->plural,
			'ajax'     => false
		] );
		
		$this->prepare_items();
	}

	public function get_db_prefix() {
		return $this->wpdb->prefix;
	}
	
	private function init_variables() {
		global $wpdb;		
	
		$plugin = Arlo_For_Wordpress::get_instance();
		$settings = get_option('arlo_settings');
				
		$this->plugin_slug = $plugin->plugin_slug;
		$this->wpdb = &$wpdb;
	}
	
	private function init_sql_variables() {
		$order = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'order');
		$paged = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'paged');

		$this->orderby = $this->get_orderby_columnname();
		$this->order = (!empty($order) && in_array(strtolower($order), ['asc','desc']) ? $order : 'desc');
		
		$this->paged = !empty($paged) ? intval($paged) : 1;	
		$this->paged = ($this->paged <= 0 ? 1 : $this->paged);
	}
	
	private function get_orderby_columnname() {
		$orderby = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'orderby');
		$orderby = (!empty($orderby) ? $orderby : 'id');
		$columns = $this->_column_headers[2];
				
		if (!empty($orderby)) {
			foreach ($columns as $field_name => $data) {
				if ($data[0] == $orderby)
					return $field_name;
			}
		}
		
		return '';
	}
	
	public function get_title() {
		return get_admin_page_title();
	}	
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'id':
			case 'message':
			case 'created':
				return $item->$column_name;
			
			case 'import_id':
				if ($item->$column_name != '0') {
					return $item->$column_name;
				}
			default:
				return '';
			}
	}

	private function get_sql_search_where_array() {
		$where = array();

		$s = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 's');

		if (!empty($s)) {
			$search_fields = $this->get_searchable_fields();
			foreach ($search_fields as $field) {
				$where[] = $field . " LIKE '%" . esc_sql(wp_unslash($s)) . "%'";
			}
		}
		return $where;	
	}
	
	protected function get_sql_where_expression() {	
		$search_where = $this->get_sql_search_where_array();
		if (count($search_where)) {
			$where = " (" . implode(" OR ", $search_where) . ")";
		}
		
		return !empty($where) ? $where : '1';
	}		

	public function get_sql_query() {
		$where = $this->get_sql_where_expression();
	
		return "
		SELECT
			id,
			import_id,
			message,
			created
		FROM
			" . $this->table_name . "
		WHERE
			" . $where . "
		";
	}		
	
	private function get_num_rows() {	
		$sql = $this->get_sql_query();
			
		$result = $this->wpdb->get_results($sql);
				
		return $this->wpdb->num_rows;
	}
		
	public function prepare_items() {
        
		$sql = $this->get_sql_query();
		
		if (!empty($this->orderby)) {
			$sql .= ' ORDER BY ' . $this->orderby . ' ' . $this->order;
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
	
	protected function get_searchable_fields() {
		return [
			'message',
			'import_id',
		];
	}
	
	public function get_columns() {
		return $columns = [
			'id'    => __( 'ID', 'arlo-for-wordpress' ),
			'import_id'    => __( 'Import ID', 'arlo-for-wordpress' ),
			'message'    => __( 'Message', 'arlo-for-wordpress' ),
			'created'    => __( 'Created date', 'arlo-for-wordpress' ),
		];
	}	

	public function get_hidden_columns() {
        return array();
    }			

	public function get_sortable_columns() {
		return array(
			'id' => array( 'id', true ),
			'import_id' => array( 'id', true ),
			'created' => array( 'created', true ),
		);
	}		
}
