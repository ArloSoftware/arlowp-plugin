<?php

namespace Arlo;

require_once 'arlo-singleton.php';

use Arlo\Singleton;

class OnlineActivities extends Singleton {
	static function get($conditions=array(), $order=array(), $limit=null) {
		global $wpdb;
	
		$query = "SELECT oa.* FROM {$wpdb->prefix}arlo_onlineactivities AS oa";
		
		$where = array();
	
		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value)) {
						$where[] = "oa.oa_arlo_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "oa.oa_arlo_id = $value";
						$limit = 1;
					}
				break;
				
				case 'template_id':
					if(is_array($value)) {
						$where[] = "oa.oat_arlo_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "oa.oat_arlo_id = $value";
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