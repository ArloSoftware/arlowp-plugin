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
 

class Arlo_For_Wordpress_Templates extends Arlo_For_Wordpress_Lists  {

	public function __construct() {		
		$this->singular = __( 'Template', $this->plugin_slug );		
		$this->plural = __( 'Templates', $this->plugin_slug );

		parent::__construct();		
	}
	
	public function set_table_name() {
		$this->table_name = $this->wpdb->prefix . 'arlo_eventtemplates';
	}
	
	public function get_columns() {
		return $columns = [
			'et_code'    => __( 'Code', $this->plugin_slug ),
			'et_name'    => __( 'Name', $this->plugin_slug ),
			'et_descriptionsummary'    => __( 'Description', $this->plugin_slug ),
			'et_registerinteresturi'    => __( 'Register interest', $this->plugin_slug ),
			'et_event_num' => __( 'Num. of events', $this->plugin_slug ),
		];
	}	
	
	public function get_hidden_columns() {
        return array();
    }	
	
	public function get_sortable_columns() {
		return array(
			'et_code' => array( 'et_code', true ),
			'et_name' => array( 'et_name', true ),
			'et_descriptionsummary' => array( 'et_descriptionsummary', true ),
			'et_registerinteresturi' => array( 'et_registerinteresturi', true ),
			'et_event_num' => array( 'et_event_num', true ),
		);
	}
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'et_code':
			case 'et_event_num':
				return $item->$column_name;
			case 'et_name':
			case 'et_descriptionsummary':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . strip_tags($item->$column_name) . '</div>';
				break;
			case 'et_registerinteresturi':
				if (!empty($item->$column_name)) 		
					return '<a href="'.$item->$column_name.'" target="_blank">' . __( 'Register interest', $this->plugin_slug ) . '</a>';
				break;
			default:
				return '';
			}
	}
	
	function column_et_code($item) {
		$actions = array(
            'edit' => sprintf('<a href="https://my.arlo.co/%s/Templates/Template.aspx?id=%d">Edit</a>', $this->platform_name, $item->et_arlo_id),
        );
        
		return sprintf('%1$s %2$s', $item->et_code, $this->row_actions($actions) );
	}
	
	protected function get_sql_where() {
		return ["et.active = '" . $this->active . "'"];
	}
		
	public function get_sql_query() {
		$where = $this->get_sql_where();
		$where = implode(" AND ", $where);
	
		return "
		SELECT
			et.et_arlo_id,
			et.et_code,
			et.et_name,
			et.et_descriptionsummary,
			et.et_registerinteresturi,
			COUNT(e_arlo_id) as et_event_num
		FROM
			" . $this->table_name . " AS et
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_events
		USING 
			(et_arlo_id)
		WHERE
			" . $where . "
		GROUP BY
			et.et_arlo_id
		";
	}		
}

?>