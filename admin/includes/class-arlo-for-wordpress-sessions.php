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
 

class Arlo_For_Wordpress_Sessions extends Arlo_For_Wordpress_Lists  {

	public function __construct() {		
		$this->singular = __( 'Session', $this->plugin_slug );		
		$this->plural = __( 'Sessions', $this->plugin_slug );

		parent::__construct();		
	}
	
	public function set_table_name() {
		$this->table_name = $this->wpdb->prefix . 'arlo_events AS es';
	}
	
	public function get_title() {
		$title = parent::get_title();
		
		if (!empty($_GET['e_parent_id']) && !empty(self::$filter_column_mapping['e_parent_id']) && intval($_GET['e_parent_id'] > 0) && !empty($this->items[0]->event_name)) {
			$title .= ' for event: ' . $this->items[0]->event_name;
		}
		
		return $title;
	}		
	
	public function get_columns() {
		return $columns = [
			'e_code'    => __( 'Event code', $this->plugin_slug ),
			'event_name'    => __( 'Event name', $this->plugin_slug ),
			'e_name'    => __( 'Session name', $this->plugin_slug ),
			'e_startdatetime'    => __( 'Start date', $this->plugin_slug ),
			'e_finishdatetime'    => __( 'Finish date', $this->plugin_slug ),
			'v_name' => __( 'Venue name', $this->plugin_slug ),
			'e_locationname' => __( 'Location name', $this->plugin_slug ),
			'e_locationroomname' => __( 'Room name', $this->plugin_slug ),
			'e_placesremaining' => __( 'Places remaining', $this->plugin_slug ),
			'e_summary' => __( 'Summary', $this->plugin_slug ),
			'e_sessiondescription' => __( 'Description', $this->plugin_slug ),
		];
	}	
	
	public function get_hidden_columns() {
        return array();
    }	
	
	public function get_sortable_columns() {
		return array(
			'e_code' => array( 'e_code', true ),
			'e_name' => array( 'e_name', true ),
			'e_startdatetime' => array( 'e_startdatetime', true ),
			'e_finishdatetime' => array( 'e_finishdatetime', true ),
			'v_name' => array( 'v_name', true ),
			'e_locationname' => array( 'e_locationname', true ),
			'e_locationroomname' => array( 'e_locationroomname', true ),
			'e_placesremaining' => array( 'e_placesremaining', true ),
			'e_summary' => array( 'e_summary', true ),
			'e_sessiondescription' => array( 'e_sessiondescription', true ),
		);
	}
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'e_code':
			case 'e_name':
			case 'event_name':
			case 'e_locationname':
			case 'e_locationroomname':
			case 'e_placesremaining':
				return $item->$column_name;
			case 'e_summary':
			case 'e_sessiondescription':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . strip_tags($item->$column_name) . '</div>';
				break;
			case 'e_startdatetime':
			case 'e_finishdatetime':
				return $item->$column_name . " " . $item->e_timezone;
			break;
			case 'v_name':				
				if (!empty($item->$column_name))
					return '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug . '-venues&v_e_id=' . $item->e_arlo_id)  .'" >' . $item->$column_name . '</a>';			
				break;			
			case 'e_register':
				if (!empty($item->e_registeruri)) 		
					return '<a href="'.$item->e_registeruri.'" target="_blank">' . $item->e_registermessage . '</a>';
				break;
			default:
				return '';
			}
	}
	
	public function column_e_code($item) {
		$actions = array(
            'edit' => sprintf('<a href="https://my.arlo.co/%s/Console/#/events/%d" target="_blank">Edit</a>', $this->platform_name, $item->e_parent_arlo_id)
        );
        
		return sprintf('%1$s %2$s', $item->e_code, $this->row_actions($actions) );
	}
	
	protected function get_sql_where_array() {
		return [
			"es.active = '" . $this->active . "'",
			"es.e_parent_arlo_id != 0"
		];
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
		
	public function get_sql_query() {
		$where = $this->get_sql_where_expression();
	
		return "
		SELECT
			e.e_name AS event_name,
			es.e_parent_arlo_id,
			es.e_arlo_id,
			es.e_code,
			es.e_name,
			es.e_startdatetime,
			es.e_finishdatetime,
			es.e_timezone,
			v.v_name,
			es.e_locationname,
			es.e_locationroomname,
			es.e_isfull,
			es.e_placesremaining,
			es.e_summary,
			es.e_sessiondescription
		FROM
			" . $this->table_name . "
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_events AS e
		ON
			es.e_parent_arlo_id = e.e_arlo_id
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_venues AS v
		ON
			es.v_id = v_arlo_id
		WHERE
			" . $where . "
		";
	}		
}

?>