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
 

class Arlo_For_Wordpress_OnlineActivities extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_onlineactivities';

	public function __construct() {		
		$this->singular = __( 'Online activity', 'arlo-for-wordpress' );		
		$this->plural = __( 'Online activities', 'arlo-for-wordpress' );

		parent::__construct();		
	}
	
	public function get_title() {
		$title = parent::get_title();
		
		if (!empty($_GET['et_id']) && !empty(self::$filter_column_mapping['et_id']) && intval($_GET['et_id'] > 0) && !empty($this->items[0]->et_name)) {
			$title .= ' for template: ' . $this->items[0]->et_name;
		}
		
		return $title;
	}	
		
	public function set_table_name() {
		$this->table_name = $this->wpdb->prefix . self::TABLENAME . ' AS oa';
	}
	
	public function get_columns() {
		return $columns = [
			'oa_code'    => __( 'Code', 'arlo-for-wordpress' ),
			'oa_name'    => __( 'Name', 'arlo-for-wordpress' ),
			'oa_delivery_description'    => __( 'Delivery desc.', 'arlo-for-wordpress' ),
			'oa_region' => __( 'Regions', 'arlo-for-wordpress' ),
		];
	}	
	
	public function get_hidden_columns() {
        return array();
    }	
	
	public function get_sortable_columns() {
		return array(
			'oa_code' => array( 'oa_code', true ),
			'oa_name' => array( 'oa_name', true ),
		);
	}
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'oa_code':
			case 'oa_region':
			case 'oa_delivery_description':
				return htmlentities($item->$column_name, ENT_QUOTES, "UTF-8");
			case 'oa_name':
				$field = '<div class="arlo-event-name">' . htmlentities($item->oa_name, ENT_QUOTES, "UTF-8") . '</div>';
												
				if (!empty($item->oa_registeruri)) 		
					$field .= '<div class="arlo-event_registeruri"><a href="'.$item->oa_registeruri.'" target="_blank">' . strip_tags($item->oa_registermessage) . '</a></div>';

				return $field;
			default:
				return '';
			}
	}
	
	function column_oa_code($item) {
		$actions = array(
            'edit' => sprintf('<a href="https://%s.arlo.co/management/Console/#/onlineactivities/%d" target="_blank">Edit</a>', $this->platform_name, $item->oa_arlo_id),
        );
        
        if (!empty($item->guid)) {
        	$actions['view'] = sprintf('<a href="%s" target="_blank">View</a>', $item->guid);
        }
        
		return sprintf('%1$s %2$s', $item->oa_code, $this->row_actions($actions) );
	}
		
	protected function get_sql_where_array() {
		return [
			"oa.import_id = " . $this->import_id,
		];
	}
	
	protected function get_searchable_fields() {
		return [
			'oa_code',
			'oa_name',
			'oa_delivery_description',
		];
	}	
	
		
	public function get_sql_query() {
		$where = $this->get_sql_where_expression();
	
		return "
		SELECT
			oa.oa_arlo_id,
			oa.oa_code,
			oa.oa_name,
			oa.oa_delivery_description,
			oa_registeruri,
			oa_registermessage,
			et_name,
			(SELECT GROUP_CONCAT(oa_region) FROM " . $this->wpdb->prefix . "arlo_onlineactivities WHERE oa_arlo_id = oa.oa_arlo_id AND oa.oa_region != 'NULL' AND oa_region != 'NULL' GROUP BY oa_arlo_id) AS oa_region,
			posts.guid
		FROM
			" . $this->table_name . "
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_eventtemplates AS et
		ON
			oat_arlo_id = et_arlo_id
		LEFT JOIN
			" . $this->wpdb->prefix . "posts AS posts
		ON
			et.et_post_id = posts.ID
		WHERE
			" . $where . "
		GROUP BY
			oa.oa_arlo_id
		";
	}	
	
	public function get_new_link() {
		return sprintf('https://%s.arlo.co/management/Console/#/onlineactivities/new/', $this->platform_name );
	}
	
	public function get_list_link() {
		return sprintf('https://%s.arlo.co/management/Console/#/onlineactivities/', $this->platform_name );
	}			
}
