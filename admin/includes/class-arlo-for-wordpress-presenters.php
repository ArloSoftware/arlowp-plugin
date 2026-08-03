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
 

class Arlo_For_Wordpress_Presenters extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_presenters';

	public function __construct() {		
		$this->singular = __( 'Presenter', 'arlo-training-and-event-management-system' );		
		$this->plural = __( 'Presenters', 'arlo-training-and-event-management-system' );

		parent::__construct();		
	}	
	
	public function get_title() {
		$title = parent::get_title();

		$ep_e_id = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, 'ep_e_id');
		
		if (!empty($ep_e_id) && !empty(self::$filter_column_mapping['ep_e_id']) && intval($ep_e_id) > 0 && !empty($this->items[0]->e_name)) {
			$title .= ' for event: ' . esc_html( $this->items[0]->e_name );
		}
		
		return $title;
	}	

	public function get_columns() {
		return $columns = [
			'name'    => esc_html__( 'Name', 'arlo-training-and-event-management-system' ),
			'p_profile'    => esc_html__( 'Profile', 'arlo-training-and-event-management-system' ),
			'p_qualifications'    => esc_html__( 'Qualifications', 'arlo-training-and-event-management-system' ),
			'p_interests'    => esc_html__( 'Interests', 'arlo-training-and-event-management-system' ),
			'p_twitterid'    => esc_html__( 'Twitter', 'arlo-training-and-event-management-system' ),
			'p_facebookid'    => esc_html__( 'Facebook', 'arlo-training-and-event-management-system' ),
			'p_linkedinid'    => esc_html__( 'LinkedIn', 'arlo-training-and-event-management-system' ),
		];
	}	
	
	public function get_hidden_columns() {
        return array();
    }	
	
	public function get_sortable_columns() {
		return array(
			'name' => array( 'name', true ),
		);
	}
	
	protected function get_sql_where_array() {
		$where = [
			"p.import_id = %d",
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
			'p_firstname',
			'p_lastname',
			'p_profile',
			'p_qualifications',
			'p_interests',
		];
	}	

	protected function get_total_items_count() {
		global $wpdb;

		$where = $this->get_sql_where_expression();
		$sql = "
		SELECT
			COUNT(DISTINCT p.p_arlo_id)
		FROM
			{$wpdb->prefix}arlo_presenters AS p
		LEFT JOIN
			{$wpdb->prefix}arlo_events_presenters AS ep
		ON
			ep.p_arlo_id = p.p_arlo_id
		AND
			ep.import_id = p.import_id
		LEFT JOIN
			{$wpdb->prefix}arlo_events AS e
		ON
			e.e_id = ep.e_id
		AND
			e.import_id = p.import_id
		WHERE
			" . $where['where'];

		$row = \ArloTraining\CacheControl::fetch_row(
			\ArloTraining\Utilities::prepare_sql($sql, $where['parameter']),
			ARRAY_N
		);

		return !empty($row) ? intval($row[0]) : 0;
	}
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'p_twitterid':
				if (!empty($item->$column_name)) {
					return '<a href="'.esc_url('https://twitter.com/' .$item->$column_name) . '" target="_blank">Twitter</a>';
				}
				break;
			case 'p_facebookid':
				if (!empty($item->$column_name)) {
					return '<a href="'.esc_url('https://www.facebook.com/' . $item->$column_name) . '" target="_blank">Facebook</a>';
				}
				break;
			case 'p_linkedinid':
				if (!empty($item->$column_name)) {
					return '<a href="'.esc_url('https://www.linkedin.com/' . $item->$column_name) . '" target="_blank">LinkedIn</a>';
				}
				break;
			case 'p_profile':
			case 'p_qualifications':
			case 'p_interests':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . esc_html( wp_strip_all_tags( $item->$column_name ) ) . '</div>';
				
				break;
			default:
				return '';
			}
	}
	
	function column_name($item) {
		$actions = array(
            'edit' => '<a href="' . esc_url(sprintf('https://%s/management/Console/#/contacts/%d',$this->platform_url, $item->p_arlo_id )) .'" target="_blank">Edit</a>',
            'view' => sprintf('<a href="%s" target="_blank">View</a>', esc_url($item->guid)),
        );
        
		return sprintf('%1$s %2$s', esc_html($item->p_firstname . ' ' . $item->p_lastname), $this->row_actions($actions) );
	}
	
	public function get_sql_query() {
		global $wpdb;
		$where = $this->get_sql_where_expression();		
	
		$sql_presenters = "
		SELECT
			guid,
			p.p_arlo_id,
			p.p_firstname,
			p.p_lastname,
			p.p_profile,
			p.p_qualifications,
			p.p_interests,
			p.p_twitterid,
			p.p_facebookid,
			p.p_linkedinid,
			p.p_post_name,
			p.p_post_id,
			e.e_name
		FROM
			{$wpdb->prefix}arlo_presenters AS p
		LEFT JOIN 
			{$wpdb->prefix}arlo_events_presenters AS ep
		ON
			ep.p_arlo_id = p.p_arlo_id
		AND
			ep.import_id = p.import_id
		LEFT JOIN 
			{$wpdb->prefix}arlo_events AS e
		ON
			e.e_id = ep.e_id
		AND
			e.import_id = p.import_id
		LEFT JOIN 
			{$wpdb->prefix}posts
		ON
			ID = p.p_post_id
		WHERE
			" . $where['where'] . "	
		GROUP BY
			p.p_arlo_id		
		";
		return \ArloTraining\Utilities::prepare_sql($sql_presenters, $where['parameter']);
	}	
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Console/#/contacts/new/', $this->platform_url) );
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Console/#/contacts/', $this->platform_url) );
	}
		
}
