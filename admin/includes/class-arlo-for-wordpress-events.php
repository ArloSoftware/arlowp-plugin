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
 

class Arlo_For_Wordpress_Events extends Arlo_For_Wordpress_Lists  {
	const TABLENAME = 'arlo_events';

	public function __construct() {		
		$this->singular = __( 'Event', 'arlo-training-and-event-management-system' );		
		$this->plural = __( 'Events', 'arlo-training-and-event-management-system' );

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
			'e_code'    => esc_html__( 'Code', 'arlo-training-and-event-management-system' ),
			'e_name'    => esc_html__( 'Name', 'arlo-training-and-event-management-system' ),
			'e_startdatetime'    => esc_html__( 'Start date', 'arlo-training-and-event-management-system' ),
			'e_finishdatetime'    => esc_html__( 'Finish date', 'arlo-training-and-event-management-system' ),
			'v_name' => esc_html__( 'Venue name', 'arlo-training-and-event-management-system' ),
			'et_descriptionsummary' => esc_html__( 'Summary', 'arlo-training-and-event-management-system' ),
			'e_sessiondescription' => esc_html__( 'Description', 'arlo-training-and-event-management-system' ),
			'e_notice' => esc_html__( 'Notice', 'arlo-training-and-event-management-system' ),
			'e_session_num' => esc_html__( 'Num. of sessions', 'arlo-training-and-event-management-system' ),
			'e_region' => esc_html__( 'Regions', 'arlo-training-and-event-management-system' ),
			//'e_isonline' => esc_html__( 'Online', 'arlo-for-wordpress' ),
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
				$field = '<div class="arlo-event-name">' . esc_html($item->e_name) . (is_numeric($item->e_placesremaining) && $item->e_placesremaining > 0 ? ' (' . intval( $item->e_placesremaining ) . ')' : '') . '</div>';
				
				if ($item->e_isonline) {
					$field .= '<div class="arlo-event-online">Live online</div>';
				}
				
				if (!empty($item->presenters))
					$field .= '<div class="arlo-event-presenter"><a href="' . esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-presenters&ep_e_id=' . $item->e_arlo_id) ) .'" >' . esc_html($item->presenters) . '</a></div>';

				if (!empty($item->e_providerorganisation)) {
					$field .= '<div class="arlo-event-provider">';
					if (!empty($item->e_providerwebsite)) {
						$field .= '<a href="' . esc_url($item->e_providerwebsite)  .'" target="_blank">' . esc_html($item->e_providerorganisation) . '</a>';					
					} else {
						$field .= esc_html($item->e_providerorganisation);
					}
					$field .= "</div>";					
				}
				
				if (!empty($item->e_registeruri)) 		
					$field .= '<div class="arlo-event_registeruri"><a href="' . esc_url($item->e_registeruri) . '" target="_blank">' . esc_html( wp_strip_all_tags( $item->e_registermessage ) ) . '</a></div>';

				return $field;

			case 'et_descriptionsummary':
			case 'e_sessiondescription':
				if (!empty($item->$column_name))
					return '<div class="arlo-list-ellipsis">' . esc_html( wp_strip_all_tags( $item->$column_name ) ) . '</div>';
				break;

			case 'e_startdatetime':
			case 'e_finishdatetime':
				//convert to the given timezone, if available
				if (!empty($this->timezones[$item->e_timezone_id])) {
					$timewithtz = str_replace(' ', 'T', $item->$column_name) . $item->{$column_name.'offset'};
					
					$date = new \DateTime($timewithtz);
							
					$timezone = new \DateTimeZone(\ArloTraining\Arrays::$arlo_timezone_system_names_to_php_tz_identifiers[$this->timezones[$item->e_timezone_id]['windows_tz_id']]);
					if ($timezone != null) {
						$date->setTimezone($timezone);

						$format_array = array("%"=>"","a"=>"D","A"=>"l","d"=>"d","e"=>"j","u"=>"N","w"=>"w","U"=>"W","V"=>"W","W"=>"W","b"=>"M","B"=>"F","h"=>"M","m"=>"m","C"=>"y","g"=>"y","G"=>"Y","y"=>"y","Y"=>"Y","H"=>"H","k"=>"G","I"=>"h","l"=>"g","M"=>"i","p"=>"A","P"=>"a","r"=>"h:i:s A","R"=>"H:i","S"=>"s","T"=>"H:i:s","X"=>"","z"=>"","Z"=>"","c"=>"","D"=>"m/d/y","F"=>"m/d/y","s"=>"U","x"=>"");
						$format = strtr("%Y-%m-%d %H:%M:%S",$format_array);									

						return esc_html( gmdate( $format, $date->getTimestamp() ) . ' ' . $date->format( 'T' ) );
					}
				}

				$abbreviation = ($column_name == 'e_startdatetime' ? $item->e_starttimezoneabbr : $item->e_finishtimezoneabbr);
				return esc_html($item->$column_name) . " " . esc_html($abbreviation);

			case 'v_name':
				$field = '';				
				if (!empty($item->$column_name)) {
					$field = '<div class="arlo-venue-name"><a href="' . esc_url(admin_url( 'admin.php?page=' . $this->plugin_slug . '-venues&v_e_id=' . $item->e_arlo_id))  .'" >' . esc_html($item->$column_name). '</a></div>';			
				}

				if (!empty($item->e_locationname)) {
					$field .= '<div class="arlo-location">' . esc_html($item->e_locationname) . (!empty($item->e_locationroomname) ? ' (' . esc_html($item->e_locationroomname) . ')' : '') . '</div>';
				} elseif (!empty($item->e_locationroomname)) {
					$field .= '<div class="arlo-locationroom">' . esc_html($item->e_locationroomname) . '</div>';
				}
				
				return $field;

			case 'e_session_num':
				if (!empty($item->$column_name))
					return '<a href="' . esc_url(admin_url( 'admin.php?page=' . $this->plugin_slug . '-sessions&e_parent_id=' . $item->e_arlo_id))  .'" >' . esc_html($item->$column_name) . '</a>';					
				break;

			default:
				return '';
		}
	}
	
	function column_e_code($item) {
		$actions = array(
			'edit' => '<a href="' . esc_url(sprintf('https://%s/management/Console/#/events/%d', $this->platform_url, $item->e_arlo_id)) . '" target="_blank">Edit</a>',
			'view' => sprintf('<a href="%s" target="_blank">View</a>', esc_url($item->guid)),
		);

		return sprintf('%1$s %2$s', esc_html($item->e_code), $this->row_actions($actions) );
	}
		
	protected function get_sql_where_array() {
		$where = [
			"e.import_id = %d",
			"e.e_parent_arlo_id = 0"
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

	protected function get_total_items_count() {
		global $wpdb;

		$where = $this->get_sql_where_expression();
		$sql = "
		SELECT
			COUNT(DISTINCT e.e_arlo_id)
		FROM
			{$wpdb->prefix}arlo_events AS e
		LEFT JOIN
			{$wpdb->prefix}arlo_eventtemplates AS et
		ON
			e.et_arlo_id = et.et_arlo_id
		AND
			et.import_id = e.import_id
		LEFT JOIN
			{$wpdb->prefix}arlo_venues AS v
		ON
			e.v_id = v.v_arlo_id
		AND
			v.import_id = e.import_id
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
		$parameter = [];
		$parameter[] = $this->import_id;
		$parameter[] = $this->import_id;

		$sql_events = "
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
			(SELECT GROUP_CONCAT(e_region) FROM {$wpdb->prefix}arlo_events WHERE e_arlo_id = e.e_arlo_id AND import_id = %d AND e.import_id = %d GROUP BY e_arlo_id) AS e_region,
			et.et_name,
			et.et_descriptionsummary,
			(SELECT COUNT(1) FROM {$wpdb->prefix}arlo_events WHERE e_parent_arlo_id = e.e_arlo_id AND e_region = e.e_region AND import_id = e.import_id) as e_session_num,
			GROUP_CONCAT(DISTINCT CONCAT_WS(' ', p.p_firstname, p.p_lastname) ORDER BY ep.p_order, p.p_firstname SEPARATOR ', ') AS presenters,
			posts.guid
		FROM
			{$wpdb->prefix}arlo_events AS e
		LEFT JOIN 
			{$wpdb->prefix}arlo_eventtemplates AS et
		ON
			e.et_arlo_id = et.et_arlo_id
		AND
			et.import_id = e.import_id
		LEFT JOIN 
			{$wpdb->prefix}arlo_events_presenters AS ep
		ON
			e.e_id = ep.e_id
		AND
			ep.import_id = e.import_id
		LEFT JOIN 
			{$wpdb->prefix}arlo_presenters AS p
		ON
			ep.p_arlo_id = p.p_arlo_id
		AND
			p.import_id = e.import_id
		LEFT JOIN 
			{$wpdb->prefix}arlo_venues AS v
		ON
			e.v_id = v.v_arlo_id
		AND
			v.import_id = e.import_id
		LEFT JOIN
			{$wpdb->prefix}posts AS posts
		ON
			et.et_post_id = posts.ID
		WHERE
			" . $where['where'] . "
		GROUP BY
			e.e_arlo_id
		";
		return \ArloTraining\Utilities::prepare_sql($sql_events, array_merge($parameter, $where['parameter']));
	}	
	
	public function get_new_link() {
		return esc_url(sprintf('https://%s/management/Console/#/events/new/', $this->platform_url));
	}
	
	public function get_list_link() {
		return esc_url(sprintf('https://%s/management/Console/#/events/', $this->platform_url));
	}			
}
