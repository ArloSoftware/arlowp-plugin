<?php

namespace Arlo;

require_once 'arlo-singleton.php';

use Arlo\Singleton;

class Offers extends Singleton {
	static function get($conditions=array(), $order=array(), $limit=null) {
		global $wpdb;
	
		$query = "SELECT o.* FROM {$wpdb->prefix}arlo_offers AS o";
		
		$where = array();
	
		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value)) {
						$where[] = "o.o_arlo_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "o.o_arlo_id = $value";
						$limit = 1;
					}
				break;
				
				case 'event_id':
					if(is_array($value)) {
						$where[] = "o.e_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "o.e_id = $value";
					}
				break;
				
				case 'event_template_id':
					if(is_array($value)) {
						$where[] = "o.et_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "o.et_id = $value";
					}
				break;
				
				case 'discounts':
					$where[] = "o.o_isdiscountoffer = " . ($value ? 1 : 0);
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
		
		$result = ($limit != 1) ? $wpdb->get_results($query.$where.$order) : $wpdb->get_row($query.$where.$order);
		
		return $result;
	}
}