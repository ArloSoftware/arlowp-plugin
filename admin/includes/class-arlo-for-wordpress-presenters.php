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
 

class Arlo_For_Wordpress_Presenters extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_presenters';

	public function __construct() {		
		$this->singular = __( 'Presenter', 'arlo-for-wordpress' );		
		$this->plural = __( 'Presenters', 'arlo-for-wordpress' );

		parent::__construct();		
	}	
	
	public function set_table_name() {
		$this->table_name = $this->wpdb->prefix . self::TABLENAME;
	}
	
	public function get_title() {
		$title = parent::get_title();

		$ep_e_id = \Arlo\Utilities::filter_string_polyfill(INPUT_GET, 'ep_e_id');
		
		if (!empty($ep_e_id) && !empty(self::$filter_column_mapping['ep_e_id']) && intval($ep_e_id > 0) && !empty($this->items[0]->e_name)) {
			$title .= ' for event: ' . $this->items[0]->e_name;
		}
		
		return $title;
	}	

	public function get_columns() {
		return $columns = [
			'name'    => __( 'Name', 'arlo-for-wordpress' ),
			'p_profile'    => __( 'Profile', 'arlo-for-wordpress' ),
			'p_qualifications'    => __( 'Qualifications', 'arlo-for-wordpress' ),
			'p_interests'    => __( 'Interests', 'arlo-for-wordpress' ),
			'p_twitterid'    => __( 'Twitter', 'arlo-for-wordpress' ),
			'p_facebookid'    => __( 'Facebook', 'arlo-for-wordpress' ),
			'p_linkedinid'    => __( 'LinkedIn', 'arlo-for-wordpress' ),
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
		return [
			"p.import_id = " . $this->import_id,
		];
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
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'p_twitterid':
				if (!empty($item->$column_name)) {
					return '<a href="http://twitter.com/' . esc_attr($item->$column_name) . '" target="_blank">Twitter</a>';
				}
				break;
			case 'p_facebookid':
				if (!empty($item->$column_name)) {
					return '<a href="http://www.facebook.com/' . esc_attr($item->$column_name) . '" target="_blank">Facebook</a>';
				}
				break;
			case 'p_linkedinid':
				if (!empty($item->$column_name)) {
					return '<a href="http://www.linkedin.com/' . esc_attr($item->$column_name) . '" target="_blank">LinkedIn</a>';
				}
				break;
			case 'p_profile':
			case 'p_qualifications':
			case 'p_interests':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . strip_tags($item->$column_name) . '</div>';
				
				break;
			default:
				return '';
			}
	}
	
	function column_name($item) {
		$actions = array(
            'edit' => sprintf('<a href="https://%s/management/Users/User.aspx?id=%d" target="_blank">Edit</a>', esc_attr($this->platform_url), $item->p_arlo_id),
            'view' => sprintf('<a href="%s" target="_blank">View</a>', $item->guid),
        );
        
		return sprintf('%1$s %2$s', esc_html($item->p_firstname . ' ' . $item->p_lastname), $this->row_actions($actions) );
	}
	
	public function get_sql_query() {
		$where = $this->get_sql_where_expression();		
	
		return "
		SELECT
			guid,
			p_arlo_id,
			p_firstname,
			p_lastname,
			p_profile,
			p_qualifications,
			p_interests,
			p_twitterid,
			p_facebookid,
			p_linkedinid,
			p_post_name,
			p_post_id,
			e.e_name
		FROM
			". $this->table_name . " AS p
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_events_presenters AS ep
		USING
			(p_arlo_id)
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_events AS e
		ON
			e.e_id = ep.e_id			
		LEFT JOIN 
			" . $this->wpdb->prefix . "posts
		ON
			ID = p_post_id
		WHERE
			" . $where . "	
		GROUP BY
			p_arlo_id		
		";
	}	
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Users/User.aspx?i=1&r=p', $this->platform_url) );
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Users/Contacts2.aspx?t=presenters', $this->platform_url) );
	}
		
}
