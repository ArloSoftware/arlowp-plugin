<?php

namespace ArloTraining\Entities;

use ArloTraining\CacheControl;
use Exception;

class Events {
	static function get($conditions=array(), $order=array(), $limit=null, $import_id = null) {
		if (!is_null($limit) && (!is_numeric($limit) || $limit <= 0)){
			throw new Exception('Limit must be a positive integer or null');
		}
		global $wpdb;
			
		$parameters = [];
		
		$where = array("e.import_id = %d");
		$parameters[] =  $import_id;
        $join = [];

		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value) && count($value)) {
						$where[] = "e.e_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "e.e_arlo_id = %d";
						$parameters[] = $value;

						$limit = 1;
					}
				break;
				case 'event_template_id':
				case 'template_id':
					if(is_array($value) && count($value)) {
						$where[] = "e.et_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "e.et_arlo_id = %d";
						$parameters[] = $value;
					}
				break;

				case 'parent_id':
 					if(is_array($value) && count($value)) {
 						$where[] = "e.e_parent_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
 					} else {
 						$where[] = "e.e_parent_arlo_id = %d";
						$parameters[] = $value;
 					}
 				break;	

				case 'region':
					$where[] = "e.e_region = %s";
					$parameters[] = $value;
				break;

				case 'state':
					$join['ce'] = " LEFT JOIN {$wpdb->prefix}arlo_events AS ce ON e.e_arlo_id = ce.e_parent_arlo_id AND e.import_id = ce.import_id";

					if(is_array($value) && count($value) > 1) {
						$ids_string = implode(',', array_map(function() {return "%d";}, $value));
						$where[] = " (ce.v_id IN (" . $ids_string . ") OR e.v_id IN (" . $ids_string . "))";
						$parameters = array_merge($parameters, $value);
						$parameters = array_merge($parameters, $value);
					} else {
						if (is_array($value)) {
							$value = array_shift($value);
						}

						$where[] = " (ce.v_id = %d OR e.v_id = %d)";
						$parameters[] = $value;
						$parameters[] = $value;	
					}
				break;

				default:
					if (is_array($value)) {
						$enhanced = str_replace('%s', substr(str_repeat('%s, ', count($value)), 0, -2), $key);
						$where[] = str_replace('%d', substr(str_repeat('%d, ', count($value)), 0, -2), $enhanced);
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = $key;

						if (strpos($key, '%') !== false && !is_null($value))
							$parameters[] = $value;
					}
				break;
			}
		}
		
		// where
		if(!empty($where)) {
			$where = ' WHERE ' . implode(' AND ', $where);
		}
		
		// order ,
		if(!empty($order)) {
			$order = ' ORDER BY ' . implode(', ', $order);
		}
		
		//limit
		$limit = is_numeric($limit) ? (int)$limit : 0;
		$limit_sql = '';
		if ($limit > 1) {
			$limit_sql = ' LIMIT %d';
			$parameters[] = $limit;
		}

		$query = "SELECT e.* FROM {$wpdb->prefix}arlo_events AS e";

		$group = " GROUP BY e.e_id";

		$sql = $query.implode("\n", $join).$where.$group.$order.$limit_sql;
		$query = $wpdb->prepare($sql, $parameters);// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
		
		if ($query) {
			return ($limit > 1) 
				? CacheControl::fetch_results($query)
				: CacheControl::fetch_row($query);
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}
	}
}