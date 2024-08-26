<?php

namespace Arlo\Entities;

class Events {
	static function get($conditions=array(), $order=array(), $limit=null, $import_id = null) {
		global $wpdb;
			
		$parameters = [];
		
		$where = array("e.import_id = %d");
		$parameters[] =  $import_id;
		$t1 = "{$wpdb->prefix}arlo_events";
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
					$join['ce'] = " LEFT JOIN $t1 AS ce ON e.e_arlo_id = ce.e_parent_arlo_id AND e.import_id = ce.import_id";

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
		
		// order
		if(!empty($order)) {
			$order = ' ORDER BY ' . implode(', ', $order);
		}
		
		//limit
		$limit = ($limit > 1 ? ' LIMIT ' . $limit : '');

		$query = "SELECT e.* FROM $t1 AS e";

		$group = " GROUP BY e.e_id";

		$query = $wpdb->prepare($query.implode("\n", $join).$where.$group.$order, $parameters);
	
		if ($query) {
			return (!empty($limit)) ? $wpdb->get_results($query.$limit) : $wpdb->get_row($query);
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}
	}
}