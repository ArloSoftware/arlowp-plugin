<?php

namespace Arlo\Entities;

class Events {
	static function get($conditions=array(), $order=array(), $limit=null, $import_id = null) {
		global $wpdb;
	
		$query = "SELECT e.* FROM {$wpdb->prefix}arlo_events AS e";
		
		$parameters = [];
		
		$where = array("import_id = %d");
		$parameters[] =  $import_id;

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
				
				default:
					$where[] = $key;

					if (strpos($key, '%') !== false && !is_null($value))
						$parameters[] = $value;
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

		$query = $wpdb->prepare($query.$where.$order, $parameters);

		if ($query) {
			return (!empty($limit)) ? $wpdb->get_results($query.$limit) : $wpdb->get_row($query);
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}
	}
}