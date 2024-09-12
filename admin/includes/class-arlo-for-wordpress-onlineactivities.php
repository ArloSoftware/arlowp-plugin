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
 

class Arlo_For_Wordpress_OnlineActivities extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_onlineactivities';

	public function __construct() {		
		$this->singular = __( 'Online activity', 'arlo-for-wordpress' );		
		$this->plural = __( 'Online activities', 'arlo-for-wordpress' );

		parent::__construct();		
	}
	
	public function get_title() {
		$title = parent::get_title();

		$et_id = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'et_id');
		
		if (!empty($et_id) && !empty(self::$filter_column_mapping['et_id']) && intval($et_id > 0) && !empty($this->items[0]->et_name)) {
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
				return esc_html($item->$column_name);
			case 'oa_name':
				$field = '<div class="arlo-event-name">' . esc_html($item->oa_name) . '</div>';
												
				if (!empty($item->oa_registeruri)) 		
					$field .= '<div class="arlo-event_registeruri"><a href="' . esc_attr($item->oa_registeruri) . '" target="_blank">' . strip_tags($item->oa_registermessage) . '</a></div>';

				return $field;
			default:
				return '';
			}
	}
	
	function column_oa_code($item) {
		$actions = array(
            'edit' => sprintf('<a href="https://%s/management/Console/#/onlineactivities/%d" target="_blank">Edit</a>', esc_attr($this->platform_url), $item->oa_arlo_id),
        );
        
        if (!empty($item->guid)) {
        	$actions['view'] = sprintf('<a href="%s" target="_blank">View</a>', $item->guid);
        }
        
		return sprintf('%1$s %2$s', esc_html($item->oa_code), $this->row_actions($actions) );
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
		return esc_url(sprintf('https://%s/management/Console/#/onlineactivities/new/', $this->platform_url));
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Console/#/onlineactivities/', $this->platform_url));
	}			
}
