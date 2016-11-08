<?php

namespace Arlo;

require_once 'arlo-singleton.php';

use Arlo\Singleton;

class EventTemplates extends Singleton {
	static function get($conditions=array(), $order=array(), $limit=null, $import_id = null) {
		global $wpdb;
	
		$query = "SELECT et.* FROM {$wpdb->prefix}arlo_eventtemplates AS et";
		
		$where = array("import_id = " . $import_id);
	
		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value)) {
						$where[] = "et.et_arlo_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "et.et_arlo_id = $value";
						$limit = 1;
					}
				break;
			}
		}
		
		// where
		if(!empty($where)) {
			$query .= ' WHERE ' . implode(' AND ', $where);
		}
		
		// order
		if(!empty($order)) {
			$query .= ' ORDER BY ' . implode(', ', $order);
		}
		
		$result = ($limit != 1) ? $wpdb->get_results($query) : $wpdb->get_row($query);
		
		return $result;
	}
}