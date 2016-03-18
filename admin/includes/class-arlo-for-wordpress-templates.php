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
		$this->table_name = $this->wpdb->prefix . 'arlo_eventtemplates AS et';
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
				return $item->$column_name;
			case 'et_name':
			case 'et_descriptionsummary':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . strip_tags($item->$column_name) . '</div>';
				break;
			case 'et_registerinteresturi':
				if (!empty($item->$column_name)) 		
					return '<a href="' . $item->$column_name . '" target="_blank">' . __( 'Register interest', $this->plugin_slug ) . '</a>';
				break;
			case 'et_event_num':
				if (intval($item->$column_name) > 0)
					return '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug . '-events&et_id=' . $item->et_arlo_id)  .'" >' . $item->$column_name . '</a>';
				else 
					return 0;
			default:
				return '';
			}
	}
	
	function column_et_code($item) {
		$actions = array(
            'edit' => sprintf('<a href="https://my.arlo.co/%s/Courses/Course.aspx?id=%d" target="_blank">Edit</a>', $this->platform_name, $item->et_arlo_id),
            'view' => sprintf('<a href="%s" target="_blank">View</a>', $item->guid),
        );
        
		return sprintf('%1$s %2$s', $item->et_code, $this->row_actions($actions) );
	}
	
	protected function get_sql_where_array() {
		return ["et.active = '" . $this->active . "'"];
	}
	
	protected function get_searchable_fields() {
		return [
			'et.et_name',
			'et.et_code',
			'et.et_descriptionsummary',
		];
	}	
		
	public function get_sql_query() {
		$where = $this->get_sql_where_expression();
		$groupby = $this->get_sql_groupby_expression();	
	
		return "
		SELECT
			guid,
			et.et_arlo_id,
			et.et_code,
			et.et_name,
			et.et_descriptionsummary,
			et.et_registerinteresturi,
			COUNT(e_arlo_id) as et_event_num
		FROM
			" . $this->table_name . "
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_events
		USING 
			(et_arlo_id)
		LEFT JOIN 
			" . $this->wpdb->prefix . "posts
		ON
			post_name = et_post_name			
		WHERE
			" . $where . "
		GROUP BY
			et.et_arlo_id
		";
	}		
}

?>