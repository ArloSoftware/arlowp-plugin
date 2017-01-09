<?php

namespace Arlo\Entities;

class Events {
	static function get($conditions=array(), $order=array(), $limit=null, $import_id = null) {
		global $wpdb;
	
		$query = "SELECT e.* FROM {$wpdb->prefix}arlo_events AS e";
		
		$where = array("import_id = " . $import_id);
	
		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value)) {
						$where[] = "e.e_arlo_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "e.e_arlo_id = $value";
						$limit = 1;
					}
				break;

				case 'event_template_id':
				case 'template_id':
					if(is_array($value)) {
						$where[] = "e.et_arlo_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "e.et_arlo_id = $value";
					}
				break;

				case 'parent_id':
 					if(is_array($value)) {
 						$where[] = "e.e_parent_arlo_id IN (" . implode(',', $value) . ")";
 					} else {
 						$where[] = "e.e_parent_arlo_id = $value";
 					}
 				break;	
				
				default:
					$where[] = $value;
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
		
		if ($limit > 1) {
			$limit = ' LIMIT ' . $limit;
		} else {
			$limit = '';
		}
		
		$result = (!empty($limit)) ? $wpdb->get_results($query.$where.$order.$limit) : $wpdb->get_row($query.$where.$order);
		
		return $result;
	}
}