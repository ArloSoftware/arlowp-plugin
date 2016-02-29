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
 

class Arlo_For_Wordpress_Events extends Arlo_For_Wordpress_Lists  {

	public function __construct() {		
		$this->singular = __( 'Event', $this->plugin_slug );		
		$this->plural = __( 'Events', $this->plugin_slug );

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
		$this->table_name = $this->wpdb->prefix . 'arlo_events AS e';
	}
	
	public function get_columns() {
		return $columns = [
			'e_code'    => __( 'Code', $this->plugin_slug ),
			'e_name'    => __( 'Name', $this->plugin_slug ),
			'e_startdatetime'    => __( 'Start date', $this->plugin_slug ),
			'e_finishdatetime'    => __( 'Finish date', $this->plugin_slug ),
			'v_name' => __( 'Venue name', $this->plugin_slug ),
			'e_locationname' => __( 'Location name', $this->plugin_slug ),
			'e_roomname' => __( 'Room name', $this->plugin_slug ),
			'e_placesremaining' => __( 'Places remaining', $this->plugin_slug ),
			'e_summary' => __( 'Summary', $this->plugin_slug ),
			'e_sessiondescription' => __( 'Description', $this->plugin_slug ),
			'e_notice' => __( 'Notice', $this->plugin_slug ),
			'e_register' => __( 'Register link', $this->plugin_slug ),
			'e_providerorganisation' => __( 'Provider', $this->plugin_slug ),
			'presenter' => __( 'Presenters', $this->plugin_slug ),
			'e_session_num' => __( 'Num. of sessions', $this->plugin_slug ),
			//'e_isonline' => __( 'Online', $this->plugin_slug ),
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
			case 'e_locationname':
			case 'e_roomname':
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
			case 'e_register':
				if (!empty($item->e_registeruri)) 		
					return '<a href="'.$item->e_registeruri.'" target="_blank">' . $item->e_registermessage . '</a>';
				break;
			case 'e_providerorganisation':				
				if (!empty($item->$column_name)) {
					if (!empty($item->e_providerwebsite)) {
						return '<a href="' . $item->e_providerwebsite  .'" target="_blank">' . $item->$column_name . '</a>';					
					} else {
						return $item->$column_name;
					}					
				}
				break;
			case 'v_name':				
				if (!empty($item->$column_name))
					return '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug . '-venues&v_e_id=' . $item->e_arlo_id)  .'" >' . $item->$column_name . '</a>';			
				break;
			case 'presenter':
				if (!empty($item->$column_name))
					return '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug . '-presenters&ep_e_id=' . $item->e_arlo_id)  .'" >' . $item->$column_name . '</a>';
				break;
			case 'e_session_num':
				if (!empty($item->$column_name))
					return '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug . '-sessions&e_parent_id=' . $item->e_arlo_id)  .'" >' . $item->$column_name . '</a>';					
				break;
			default:
				return '';
			}
	}
	
	function column_e_code($item) {
		$actions = array(
            'edit' => sprintf('<a href="https://my.arlo.co/%s/Courses/ScheduleItem.aspx?id=%d" target="_blank">Edit</a>', $this->platform_name, $item->e_arlo_id)
        );
        
		return sprintf('%1$s %2$s', $item->e_code, $this->row_actions($actions) );
	}
	
	protected function get_sql_where_array() {
		return [
			"e.active = '" . $this->active . "'",
			"e.e_parent_arlo_id = 0"
		];
	}
	
	protected function get_searchable_fields() {
		return [
			'e_code',
			'e_name',
			'v_name',
			'e_locationname',
			'e_locationroomname',
			'e_summary',
			'e_sessiondescription',
			'e_notice',
			'e_registermessage',
			'e_providerorganisation',
		];
	}	
	
		
	public function get_sql_query() {
		$where = $this->get_sql_where_expression();
	
		return "
		SELECT
			e.e_arlo_id,
			e.e_code,
			e.e_name,
			e.e_startdatetime,
			e.e_finishdatetime,
			e.e_timezone,
			v.v_name,
			e.e_locationname,
			e.e_locationroomname,
			e.e_isfull,
			e.e_placesremaining,
			e.e_summary,
			e.e_sessiondescription,
			e.e_notice,
			e.e_registermessage,
			e.e_registeruri,
			e.e_providerorganisation,
			e.e_providerwebsite,
			e.e_isonline,
			et.et_name,
			(SELECT COUNT(1) FROM " . $this->wpdb->prefix . "arlo_events WHERE e_parent_arlo_id = e.e_arlo_id) as e_session_num,
			GROUP_CONCAT(CONCAT_WS(' ', p.p_firstname, p.p_lastname) ORDER BY ep.p_order, p.p_firstname SEPARATOR ', ') AS presenter
		FROM
			" . $this->table_name . "
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_eventtemplates AS et
		USING
			(et_arlo_id)
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_events_presenters AS ep
		USING
			(e_arlo_id)			
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_presenters AS p
		USING
			(p_arlo_id)
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_venues AS v
		ON
			e.v_id = v.v_arlo_id
		WHERE
			" . $where . "
		GROUP BY
			e.e_arlo_id
		";
	}		
}

?>