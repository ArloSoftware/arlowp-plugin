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
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
require_once 'class-arlo-for-wordpress-lists.php';
 

class Arlo_For_Wordpress_OnlineActivities extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_onlineactivities';

	public function __construct() {		
		$this->singular = __( 'Online activity', 'arlo-training-and-event-management-system' );		
		$this->plural = __( 'Online activities', 'arlo-training-and-event-management-system' );

		parent::__construct();		
	}
	
	public function get_title() {
		$title = parent::get_title();

		$et_id = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, 'et_id');
		
		if (!empty($et_id) && !empty(self::$filter_column_mapping['et_id']) && intval($et_id) > 0 && !empty($this->items[0]->et_name)) {
			$title .= ' for template: ' . esc_html( $this->items[0]->et_name );
		}
		
		return $title;
	}	
	
	public function get_columns() {
		return $columns = [
			'oa_code'    => esc_html__( 'Code', 'arlo-training-and-event-management-system' ),
			'oa_name'    => esc_html__( 'Name', 'arlo-training-and-event-management-system' ),
			'oa_delivery_description'    => esc_html__( 'Delivery desc.', 'arlo-training-and-event-management-system' ),
			'oa_region' => esc_html__( 'Regions', 'arlo-training-and-event-management-system' ),
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
					$field .= '<div class="arlo-event_registeruri"><a href="' . esc_url($item->oa_registeruri) . '" target="_blank">' . esc_html( wp_strip_all_tags( $item->oa_registermessage ) ) . '</a></div>';

				return $field;
			default:
				return '';
			}
	}
	
	function column_oa_code($item) {
		$actions = array(
            'edit' => '<a href="' . esc_url(sprintf('https://%s/management/Console/#/onlineactivities/%d', $this->platform_url, $item->oa_arlo_id)) .'" target="_blank">Edit</a>',
        );
        
        if (!empty($item->guid)) {
        	$actions['view'] = sprintf('<a href="%s" target="_blank">View</a>', esc_url($item->guid));
        }
        
		return sprintf('%1$s %2$s', esc_html($item->oa_code), $this->row_actions($actions) );
	}
		
	protected function get_sql_where_array() {
		$where = [
			"oa.import_id = %d",
		];
		$parameter = [
			$this->import_id
		];
		return array(
			'where' => $where,
			'parameter' => $parameter
		);
	}
	
	protected function get_searchable_fields() {
		return [
			'oa_code',
			'oa_name',
			'oa_delivery_description',
		];
	}	

	protected function get_total_items_count() {
		global $wpdb;

		$where = $this->get_sql_where_expression();
		$sql = "
		SELECT
			COUNT(DISTINCT oa.oa_arlo_id)
		FROM
			{$wpdb->prefix}arlo_onlineactivities AS oa
		LEFT JOIN
			{$wpdb->prefix}arlo_eventtemplates AS et
		ON
			oa.oat_arlo_id = et.et_arlo_id
		AND
			et.import_id = oa.import_id
		WHERE
			" . $where['where'];

		$row = \ArloTraining\CacheControl::fetch_row(
			\ArloTraining\Utilities::prepare_sql($sql, $where['parameter']),
			ARRAY_N
		);

		return !empty($row) ? intval($row[0]) : 0;
	}
	
		
	public function get_sql_query() {
		global $wpdb;
		$where = $this->get_sql_where_expression();
	
		$sql_onlineactivities = "
		SELECT
			oa.oa_arlo_id,
			oa.oa_code,
			oa.oa_name,
			oa.oa_delivery_description,
			oa_registeruri,
			oa_registermessage,
			et_name,
			(SELECT GROUP_CONCAT(oa_region) FROM {$wpdb->prefix}arlo_onlineactivities WHERE oa_arlo_id = oa.oa_arlo_id AND import_id = oa.import_id AND oa.oa_region != 'NULL' AND oa_region != 'NULL' GROUP BY oa_arlo_id) AS oa_region,
			posts.guid
		FROM
			{$wpdb->prefix}arlo_onlineactivities AS oa
		LEFT JOIN 
			{$wpdb->prefix}arlo_eventtemplates AS et
		ON
			oat_arlo_id = et_arlo_id
		AND
			et.import_id = oa.import_id
		LEFT JOIN
			{$wpdb->prefix}posts AS posts
		ON
			et.et_post_id = posts.ID
		WHERE
			" . $where['where'] . "
		GROUP BY
			oa.oa_arlo_id
		";
		return \ArloTraining\Utilities::prepare_sql($sql_onlineactivities, $where['parameter']);
	}
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Console/#/onlineactivities/new/', $this->platform_url));
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Console/#/onlineactivities/', $this->platform_url));
	}			
}
