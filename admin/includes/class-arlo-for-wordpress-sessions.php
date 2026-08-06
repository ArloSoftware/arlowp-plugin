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
 

class Arlo_For_Wordpress_Sessions extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_events';

	public function __construct() {		
		$this->singular = __( 'Session', 'arlo-training-and-event-management-system' );		
		$this->plural = __( 'Sessions', 'arlo-training-and-event-management-system' );

		parent::__construct();		
	}
	
	public function get_title() {
		$title = parent::get_title();

		$e_parent_id = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, 'e_parent_id');
		
		if (!empty($e_parent_id) && !empty(self::$filter_column_mapping['e_parent_id']) && intval($e_parent_id) > 0 && !empty($this->items[0]->event_name)) {
			$title .= ' for event: ' . esc_html( $this->items[0]->event_name );
		}
		
		return $title;
	}

	public function get_columns() {
		return $columns = [
			'e_code'    => esc_html__( 'Event code', 'arlo-training-and-event-management-system' ),
			'event_name'    => esc_html__( 'Name', 'arlo-training-and-event-management-system' ),
			'e_startdatetime'    => esc_html__( 'Start date', 'arlo-training-and-event-management-system' ),
			'e_finishdatetime'    => esc_html__( 'Finish date', 'arlo-training-and-event-management-system' ),
			'v_name' => esc_html__( 'Venue name', 'arlo-training-and-event-management-system' ),
			'et_descriptionsummary' => esc_html__( 'Summary', 'arlo-training-and-event-management-system' ),
			'e_sessiondescription' => esc_html__( 'Description', 'arlo-training-and-event-management-system' ),
			'e_region' => esc_html__( 'Regions', 'arlo-training-and-event-management-system' ),
		];
	}	
	
	public function get_hidden_columns() {
        return array();
    }	
	
	public function get_sortable_columns() {
		return array(
			'e_code' => array( 'e_code', true ),
			'e_startdatetime' => array( 'e_startdatetime', true ),
			'e_finishdatetime' => array( 'e_finishdatetime', true ),
			'v_name' => array( 'v_name', true ),
			'e_locationname' => array( 'e_locationname', true ),
			'e_placesremaining' => array( 'e_placesremaining', true ),
			'et_descriptionsummary' => array( 'et_descriptionsummary', true ),
			'e_sessiondescription' => array( 'e_sessiondescription', true ),
		);
	}
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'e_code':
			case 'e_locationname':
			case 'e_placesremaining':
			case 'e_region':
				return esc_html($item->$column_name);
			case 'et_descriptionsummary':
			case 'e_sessiondescription':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . esc_html( wp_strip_all_tags( $item->$column_name ) ) . '</div>';
				break;
			case 'e_startdatetime':
				return esc_html($item->e_startdatetime . " " . $item->e_starttimezoneabbr);
			case 'e_finishdatetime':
				return esc_html($item->e_finishdatetime . " " . $item->e_finishtimezoneabbr);
			case 'v_name':
				$field = '';				
				if (!empty($item->$column_name)) {
					$field = '<div class="arlo-venue-name"><a href="' . esc_url(admin_url( 'admin.php?page=' . $this->plugin_slug . '-venues&v_e_id=' . $item->e_arlo_id))  .'" >' . esc_html($item->$column_name) . '</a></div>';			
				}

				if (!empty($item->e_locationname)) {
					$field .= '<div class="arlo-location">' . esc_html($item->e_locationname) . (!empty($item->e_locationroomname) ? ' (' . esc_html($item->e_locationroomname) . ')' : '') . '</div>';
				} elseif (!empty($item->e_locationroomname)) {
					$field .= '<div class="arlo-locationroom">' . esc_html($item->e_locationroomname) . '</div>';
				}
				
				return $field;
			case 'event_name':
				return '<div class="arlo-session-name">' . esc_html($item->e_name) . '</div><div class="arlo-session-event-name">' . esc_html($item->event_name) . '</div>';
			case 'e_register':
				if (!empty($item->e_registeruri)) 		
					return '<a href="'.esc_url($item->e_registeruri).'" target="_blank">' . esc_html( wp_strip_all_tags( $item->e_registermessage ) ) . '</a>';
				break;
			default:
				return '';
			}
	}
	
	public function column_e_code($item) {
		$actions = array(
            'edit' => '<a href="' . esc_url(sprintf('https://%s/management/Console/#/events/%d', $this->platform_url, $item->e_parent_arlo_id)) . '" target="_blank">Edit</a>'
        );
        
		return sprintf('%1$s %2$s', esc_html($item->e_code), $this->row_actions($actions) );
	}
	
	protected function get_sql_where_array() {
		$where = [
			"es.import_id = %d" ,
			"es.e_parent_arlo_id != 0"
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
			'e.e_name',
			'es.e_code',
			'es.e_name',
			'es.e_locationname',
			'es.e_locationroomname',
		];
	}

	protected function get_total_items_count() {
		global $wpdb;

		$where = $this->get_sql_where_expression();
		$sql = "
		SELECT
			COUNT(DISTINCT es.e_arlo_id)
		FROM
			{$wpdb->prefix}arlo_events AS es
		LEFT JOIN
			{$wpdb->prefix}arlo_events AS e
		ON
			es.e_parent_arlo_id = e.e_arlo_id
		AND
			e.import_id = es.import_id
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
	
		$sql_sessions = "
		SELECT
			e.e_name AS event_name,
			es.e_parent_arlo_id,
			es.e_arlo_id,
			es.e_code,
			es.e_name,
			es.e_startdatetime,
			es.e_finishdatetime,
			es.e_starttimezoneabbr,
			es.e_finishtimezoneabbr,
			v.v_name,
			es.e_locationname,
			es.e_locationroomname,
			es.e_isfull,
			es.e_placesremaining,
			et.et_descriptionsummary,
			es.e_sessiondescription,
			GROUP_CONCAT(DISTINCT es.e_region) as e_region
		FROM
			{$wpdb->prefix}arlo_events AS es
		LEFT JOIN 
			{$wpdb->prefix}arlo_events AS e
		ON
			es.e_parent_arlo_id = e.e_arlo_id
		AND
			e.import_id = es.import_id
		LEFT JOIN 
			{$wpdb->prefix}arlo_eventtemplates AS et
		ON
			e.et_arlo_id = et.et_arlo_id
		AND
			et.import_id = es.import_id
		LEFT JOIN 
			{$wpdb->prefix}arlo_venues AS v
		ON
			es.v_id = v_arlo_id
		AND
			v.import_id = es.import_id
		WHERE
			" . $where['where'] . "
		GROUP BY 
			es.e_arlo_id
		";
		return \ArloTraining\Utilities::prepare_sql($sql_sessions, $where['parameter']);
	}
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Console/#/events/new/', $this->platform_url) );
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Console/#/events/', $this->platform_url) );
	}			
		
}
