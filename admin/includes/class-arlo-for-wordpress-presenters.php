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
 

class Arlo_For_Wordpress_Presenters extends Arlo_For_Wordpress_Lists  {

	public function __construct() {		
		$this->singular = __( 'Presenter', $this->plugin_slug );		
		$this->plural = __( 'Presenters', $this->plugin_slug );

		parent::__construct();		
	}
	
	public function set_table_name() {
		$this->table_name = $this->wpdb->prefix . 'arlo_presenters';
	}
	
	public function get_columns() {
		return $columns = [
			'name'    => __( 'Name', $this->plugin_slug ),
			'p_profile'    => __( 'Profile', $this->plugin_slug ),
			'p_qualifications'    => __( 'Qualifications', $this->plugin_slug ),
			'p_interests'    => __( 'Interests', $this->plugin_slug ),
			'p_twitterid'    => __( 'Twitter', $this->plugin_slug ),
			'p_facebookid'    => __( 'Facebook', $this->plugin_slug ),
			'p_linkedinid'    => __( 'LinkedIn', $this->plugin_slug ),
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
			case 'p_facebookid':
			case 'p_linkedinid':
				return $item->$column_name;
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
            'edit' => sprintf('<a href="https://my.arlo.co/%s/Users/User.aspx?id=%d" target="_blank">Edit</a>', $this->platform_name, $item->p_arlo_id),
            'view' => sprintf('<a href="%s" target="_blank">View</a>', $item->guid),
        );
        
		return sprintf('%1$s %2$s', $item->p_firstname . ' ' . $item->p_lastname, $this->row_actions($actions) );
	}
	
	public function get_sql_query() {
		$where = $this->get_sql_where();
		$where = implode(" AND ", $where);	
	
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
			p_post_name
		FROM
			". $this->table_name . "
		LEFT JOIN 
			" . $this->wpdb->prefix . "posts
		ON
			post_name = p_post_name
		WHERE
			" . $where . "			
		";
	}		
}

?>