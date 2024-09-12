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

use Arlo\VersionHandler;

class Arlo_For_Wordpress_Lists extends WP_List_Table  {
	public $singular;
	public $plural;
	public $table_name;

	protected $wpdb;	
	protected $platform_name;
	protected $platform_url;
	protected $order;
	protected $orderby;
	protected $paged;
	protected $import_id;
	protected $plugin_slug;
	protected $timezones;
	
	protected static $filter_column_mapping = array(
		'et_id' => 'et.et_arlo_id',
		'ep_e_id' => 'ep.e_arlo_id',
		'v_e_id' => 'e.e_arlo_id',
		'e_parent_id' => 'es.e_parent_arlo_id',
	);	
	
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
		
		$this->prepare_items();
	}
	
	private function init_variables() {
		global $wpdb;		
	
		$plugin = Arlo_For_Wordpress::get_instance();
		$settings = get_option('arlo_settings');
		$import_id = $plugin->get_importer()->get_current_import_id();
				
		$this->import_id = (!empty($import_id) ? $import_id : '0');

		$this->plugin_slug = $plugin->plugin_slug;
		$this->version = VersionHandler::VERSION;	
		$this->wpdb = &$wpdb;

		$this->platform_name = $settings['platform_name'];	
		$this->platform_url = strpos($this->platform_name, '.') !== false ? $this->platform_name : $this->platform_name . '.arlo.co';


		$this->timezones = $this->get_timezones();
	}

	private function get_timezones() {
		global $wpdb;
		$timezones = [];

		$sql = "
		SELECT
			id,
			name,
			windows_tz_id
		FROM
			" . $wpdb->prefix . "arlo_timezones
		WHERE
			import_id = " . $this->import_id . "
		ORDER BY name		
		";

		$items = $wpdb->get_results($sql, ARRAY_A);

		foreach ($items as $timezone) {
			$timezones[$timezone['id']] = $timezone;
		}

		return $timezones;
	}
	
	private function init_sql_variables() {
		$order = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'order');
		$paged = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'paged');

		$this->orderby = $this->get_orderby_columnname();
		$this->order = (!empty($order) && in_array(strtolower($order), ['asc','desc']) ? $order : '');
		
		$this->paged = !empty($paged) ? intval($paged) : 1;	
		$this->paged = ($this->paged <= 0 ? 1 : $this->paged);
	}
	
	private function get_orderby_columnname() {
		$orderby = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'orderby');
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
		die( 'function Arlo_For_Wordpress_Lists::column_default() must be over-ridden in a sub-class.' );
	}	
	
	private function get_num_rows() {	
		$sql = $this->get_sql_query();

		$result = $this->wpdb->get_results($sql);
				
		return $this->wpdb->num_rows;
	}
	
	protected function get_sql_where_array() {
		return ["import_id = " . $this->import_id];
	}
	
	private function get_sql_search_where_array() {
		$where = array();

		$s = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 's');
		$et_id = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'et_id');
		$ep_e_id = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'ep_e_id');
		$v_e_id = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'v_e_id');
		$e_parent_id = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'e_parent_id');

		if (!empty($s)) {
			$search_fields = $this->get_searchable_fields();
			foreach ($search_fields as $field) {
				$where[] = $field . " LIKE '%" . esc_sql(wp_unslash($s)) . "%'";
			}
		}
		
		if (!empty($et_id) && !empty(self::$filter_column_mapping['et_id']) && intval($et_id > 0)) {
			$where[] = self::$filter_column_mapping['et_id'] .' = ' . intval($et_id);
		}
		
		if (!empty($ep_e_id) && !empty(self::$filter_column_mapping['ep_e_id']) && intval($ep_e_id > 0)) {
			$where[] = self::$filter_column_mapping['ep_e_id'] .' = ' . intval($ep_e_id);
		}

		if (!empty($v_e_id) && !empty(self::$filter_column_mapping['v_e_id']) && intval($v_e_id > 0)) {
			$where[] = self::$filter_column_mapping['v_e_id'] .' = ' . intval($v_e_id);
		}

		if (!empty($e_parent_id) && !empty(self::$filter_column_mapping['e_parent_id']) && intval($e_parent_id > 0)) {
			$where[] = self::$filter_column_mapping['e_parent_id'] .' = ' . intval($e_parent_id);
		}
	

		return $where;	
	}
	
	protected function get_sql_where_expression() {
		$where = $this->get_sql_where_array();
		$where = implode(" AND ", $where);
		
		$search_where = $this->get_sql_search_where_array();
		if (count($search_where)) {
			$where .= " AND (" . implode(" OR ", $search_where) . ")";
		}
		
		return !empty($where) ? $where : '1';
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
		die( 'function Arlo_For_Wordpress_Lists::get_searchable_fields() must be over-ridden in a sub-class.' );
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
	
	public function get_new_link() {
		die( 'function Arlo_For_Wordpress_Lists::get_new_link() must be over-ridden in a sub-class.' );
	}	

	public function get_list_link() {
		die( 'function Arlo_For_Wordpress_Lists::get_list_link() must be over-ridden in a sub-class.' );
	}	
	
}
