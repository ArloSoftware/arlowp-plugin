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
 

class Arlo_For_Wordpress_Events extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_events';

	public function __construct() {		
		$this->singular = __( 'Event', 'arlo-for-wordpress' );		
		$this->plural = __( 'Events', 'arlo-for-wordpress' );

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
		$this->table_name = $this->wpdb->prefix . self::TABLENAME . ' AS e';
	}
	
	public function get_columns() {
		return $columns = [
			'e_code'    => __( 'Code', 'arlo-for-wordpress' ),
			'e_name'    => __( 'Name', 'arlo-for-wordpress' ),
			'e_startdatetime'    => __( 'Start date', 'arlo-for-wordpress' ),
			'e_finishdatetime'    => __( 'Finish date', 'arlo-for-wordpress' ),
			'v_name' => __( 'Venue name', 'arlo-for-wordpress' ),
			'et_descriptionsummary' => __( 'Summary', 'arlo-for-wordpress' ),
			'e_sessiondescription' => __( 'Description', 'arlo-for-wordpress' ),
			'e_notice' => __( 'Notice', 'arlo-for-wordpress' ),
			'e_session_num' => __( 'Num. of sessions', 'arlo-for-wordpress' ),
			'e_region' => __( 'Regions', 'arlo-for-wordpress' ),
			//'e_isonline' => __( 'Online', 'arlo-for-wordpress' ),
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
			'e_placesremaining' => array( 'e_placesremaining', true ),
			'et_descriptionsummary' => array( 'et_descriptionsummary', true ),
			'e_sessiondescription' => array( 'e_sessiondescription', true ),			
		);
	}
	
	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'e_code':
			case 'e_placesremaining':
			case 'e_region':
				return esc_html($item->$column_name);
			case 'e_name':
				$field = '<div class="arlo-event-name">' . esc_html($item->e_name) . (is_numeric($item->e_placesremaining) && $item->e_placesremaining > 0 ? ' (' . $item->e_placesremaining . ')' : '') . '</div>';
				
				if ($item->e_isonline) {
					$field .= '<div class="arlo-event-online">Live online</div>';
				}
				
				if (!empty($item->presenters))
					$field .= '<div class="arlo-event-presenter"><a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug . '-presenters&ep_e_id=' . $item->e_arlo_id)  .'" >' . esc_html($item->presenters) . '</a>';

				if (!empty($item->e_providerorganisation)) {
					$field .= '<div class="arlo-event-provider">';
					if (!empty($item->e_providerwebsite)) {
						$field .= '<a href="' . esc_attr($item->e_providerwebsite)  .'" target="_blank">' . esc_html($item->$column_name) . '</a>';					
					} else {
						$field .= esc_html($item->e_providerorganisation);
					}
					$field .= "</div>";					
				}
				
				if (!empty($item->e_registeruri)) 		
					$field .= '<div class="arlo-event_registeruri"><a href="' . esc_attr($item->e_registeruri) . '" target="_blank">' . strip_tags($item->e_registermessage) . '</a></div>';

				return $field;
			case 'et_descriptionsummary':
			case 'e_sessiondescription':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . strip_tags($item->$column_name) . '</div>';
				break;
			case 'e_startdatetime':
			case 'e_finishdatetime':
				//convert to the given timezone, if available
				if (!empty($this->timezones[$item->e_timezone_id])) {
					 $timewithtz = str_replace(' ', 'T', $item->$column_name) . $item->{$column_name.'offset'};
					 
        			 $date = new \DateTime($timewithtz);
							 
					 $timezone = new \DateTimeZone(\Arlo\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$this->timezones[$item->e_timezone_id]['windows_tz_id']]);
					 //Old function URL - https://www.php.net/manual/en/function.strftime.php - This function is deprecated from PHP 8.1
					 //New function URL - https://www.php.net/manual/en/datetime.format.php
					 //I am actually converting time from using strftime function to new datetime function so it convert same time format.
					 if ($timezone != null) {
            			$date->setTimezone($timezone);

									$format_array = array("%"=>"","a"=>"D","A"=>"l","d"=>"d","e"=>"j","u"=>"N","w"=>"w","U"=>"W","V"=>"W","W"=>"W","b"=>"M","B"=>"F","h"=>"M","m"=>"m","C"=>"y","g"=>"y","G"=>"Y","y"=>"y","Y"=>"Y","H"=>"H","k"=>"G","I"=>"h","l"=>"g","M"=>"i","p"=>"A","P"=>"a","r"=>"h:i:s A","R"=>"H:i","S"=>"s","T"=>"H:i:s","X"=>"","z"=>"","Z"=>"","c"=>"","D"=>"m/d/y","F"=>"m/d/y","s"=>"U","x"=>"");
									$format = strtr("%Y-%m-%d %H:%M:%S",$format_array);									

						return date($format, $date->getTimestamp()) . " " . $date->format("T");
					 }
				}

				$abbreviation = ($column_name == 'e_startdatetime' ? $item->e_starttimezoneabbr : $item->e_finishtimezoneabbr);
				return esc_html($item->$column_name) . " " . esc_html($abbreviation);
			break;
			case 'v_name':
				$field = '';				
				if (!empty($item->$column_name)) {
					$field = '<div class="arlo-venue-name"><a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug . '-venues&v_e_id=' . $item->e_arlo_id)  .'" >' . esc_html($item->$column_name). '</a></div>';			
				}

				if (!empty($item->e_locationname)) {
					$field .= '<div class="arlo-location">' . esc_html($item->e_locationname) . (!empty($item->e_locationroomname) ? ' (' . esc_html($item->e_locationroomname) . ')' : '') . '</div>';
				} elseif (!empty($item->e_locationroomname)) {
					$field .= '<div class="arlo-locationroom">' . esc_html($item->e_locationroomname) . '</div>';
				}
				
				return $field;
			case 'e_session_num':
				if (!empty($item->$column_name))
					return '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_slug . '-sessions&e_parent_id=' . $item->e_arlo_id)  .'" >' . esc_html($item->$column_name) . '</a>';					
				break;
			default:
				return '';
			}
	}
	
	function column_e_code($item) {
		$actions = array(
            'edit' => sprintf('<a href="https://%s/management/Courses/ScheduleItem.aspx?id=%d" target="_blank">Edit</a>', esc_attr($this->platform_url), $item->e_arlo_id),
            'view' => sprintf('<a href="%s" target="_blank">View</a>', $item->guid),
        );
        
		return sprintf('%1$s %2$s', esc_html($item->e_code), $this->row_actions($actions) );
	}
		
	protected function get_sql_where_array() {
		return [
			"e.import_id = " . $this->import_id,
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
			'et_descriptionsummary',
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
			e.e_startdatetimeoffset,
			e.e_finishdatetimeoffset,
			e.e_starttimezoneabbr,
			e.e_finishtimezoneabbr,
			e.e_timezone_id,
			v.v_name,
			e.e_locationname,
			e.e_locationroomname,
			e.e_isfull,
			e.e_placesremaining,
			e.e_sessiondescription,
			e.e_notice,
			e.e_registermessage,
			e.e_registeruri,
			e.e_providerorganisation,
			e.e_providerwebsite,
			e.e_isonline,
			(SELECT GROUP_CONCAT(e_region) FROM " . $this->wpdb->prefix . "arlo_events WHERE e_arlo_id = e.e_arlo_id AND import_id = " . $this->import_id . " AND e.import_id = " . $this->import_id . " GROUP BY e_arlo_id) AS e_region,
			et.et_name,
			et.et_descriptionsummary,
			(SELECT COUNT(1) FROM " . $this->wpdb->prefix . "arlo_events WHERE e_parent_arlo_id = e.e_arlo_id AND e_region = e.e_region) as e_session_num,
			GROUP_CONCAT(DISTINCT CONCAT_WS(' ', p.p_firstname, p.p_lastname) ORDER BY ep.p_order, p.p_firstname SEPARATOR ', ') AS presenters,
			posts.guid
		FROM
			" . $this->table_name . "
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_eventtemplates AS et
		USING
			(et_arlo_id)
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_events_presenters AS ep
		ON
			e.e_id = ep.e_id		
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_presenters AS p
		USING
			(p_arlo_id)
		LEFT JOIN 
			" . $this->wpdb->prefix . "arlo_venues AS v
		ON
			e.v_id = v.v_arlo_id
		LEFT JOIN
			" . $this->wpdb->prefix . "posts AS posts
		ON
			et.et_post_id = posts.ID
		WHERE
			" . $where . "
		GROUP BY
			e.e_arlo_id
		";
	}	
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Console/#/events/new/', $this->platform_url));
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Courses/Courses2.aspx', esc_attr($this->platform_url)));
	}			
}
