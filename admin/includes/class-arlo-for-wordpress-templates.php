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
 

class Arlo_For_Wordpress_Templates extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_eventtemplates';

	public function __construct() {		
		$this->singular = __( 'Template', 'arlo-training-and-event-management-system' );		
		$this->plural = __( 'Templates', 'arlo-training-and-event-management-system' );

		parent::__construct();		
	}
	
	public function get_columns() {
		return $columns = [
			'et_code'    => esc_html__( 'Code', 'arlo-training-and-event-management-system' ),
			'et_name'    => esc_html__( 'Name', 'arlo-training-and-event-management-system' ),
			'et_descriptionsummary'    => esc_html__( 'Description', 'arlo-training-and-event-management-system' ),
			'et_registerinteresturi'    => esc_html__( 'Register interest', 'arlo-training-and-event-management-system' ),
			'et_event_num' => esc_html__( 'Num. of events', 'arlo-training-and-event-management-system' ),
			'et_region' => esc_html__( 'Regions', 'arlo-training-and-event-management-system' ),
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
			case 'et_region':
				return esc_html($item->$column_name);
			case 'et_name':
			case 'et_descriptionsummary':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . esc_html( wp_strip_all_tags( $item->$column_name ) ) . '</div>';
				break;
			case 'et_registerinteresturi':
				if (!empty($item->$column_name)) 		
					return '<a href="' . esc_url($item->$column_name) . '" target="_blank">' . esc_html__( 'Register interest', 'arlo-training-and-event-management-system' ) . '</a>';
				break;
			case 'et_event_num':
				$retval = '0';
				if (intval($item->$column_name) > 0)
					$retval = '<a href="' . esc_url(admin_url( 'admin.php?page=' . $this->plugin_slug . '-events&et_id=' . $item->et_arlo_id))  .'" >' . esc_html($item->$column_name) . '</a>';
				
				if (intval($item->oa_id) > 0) {
					$retval .= ' / <a href="' . esc_url(admin_url( 'admin.php?page=' . $this->plugin_slug . '-onlineactivities&et_id=' . $item->et_arlo_id))  .'" >' . esc_html__( 'OA', 'arlo-training-and-event-management-system' ) . '</a>';
				}
				
				return $retval;
					
			default:
				return '';
			}
	}
	
	function column_et_code($item) {
		$actions = array(
            'edit' => sprintf('<a href="%s" target="_blank">Edit</a>', esc_url(sprintf("https://%s/management/Console/#/events/%d", $this->platform_url, $item->et_arlo_id))),
            'view' => sprintf('<a href="%s" target="_blank">View</a>', esc_url($item->guid)),
        );
        
		return sprintf('%1$s %2$s', esc_html($item->et_code), $this->row_actions($actions) );
	}
	
	protected function get_sql_where_array() {
		$where = ["et.import_id = %d"];
		$parameter = [$this->import_id];
		return array(
			'where' => $where,
			'parameter' => $parameter
		);
	}
	
	protected function get_searchable_fields() {
		return [
			'et.et_name',
			'et.et_code',
			'et.et_descriptionsummary',
		];
	}	

	protected function get_total_items_count() {
		global $wpdb;

		$where = $this->get_sql_where_expression();
		$sql = "
		SELECT
			COUNT(DISTINCT et.et_arlo_id)
		FROM
			{$wpdb->prefix}arlo_eventtemplates AS et
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
	
		$sql_templates = "
		SELECT
			guid,
			et.et_arlo_id,
			et.et_code,
			et.et_name,
			et.et_descriptionsummary,
			et.et_registerinteresturi,
			COUNT(DISTINCT e_arlo_id) AS et_event_num,
			oa_id,
			GROUP_CONCAT(DISTINCT et.et_region) AS et_region
		FROM
			{$wpdb->prefix}arlo_eventtemplates AS et
		LEFT JOIN 
			{$wpdb->prefix}arlo_events AS e
		ON 
			et.et_arlo_id = e.et_arlo_id
		AND
			et.et_region = e.e_region
		AND
			e_parent_arlo_id = 0
		AND
			e.import_id = et.import_id
		LEFT JOIN
			{$wpdb->prefix}arlo_onlineactivities AS oa
		ON
			et.et_arlo_id = oat_arlo_id
		AND
			oa.import_id = et.import_id
		LEFT JOIN 
			{$wpdb->prefix}posts
		ON
			ID = et_post_id
		WHERE
			" . $where['where'] . "
		GROUP BY
			et.et_arlo_id
		";
		return \ArloTraining\Utilities::prepare_sql($sql_templates, $where['parameter']);
	}
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Console/#/events/new/', $this->platform_url) );
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Console/#/events/', $this->platform_url) );
	}				
}
