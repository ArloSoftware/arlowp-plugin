<?php

namespace Arlo;

require_once( plugin_dir_path( __FILE__ ) . '../arlo-singleton.php');

use Arlo\Singleton;

class Offers extends Singleton {
	static function get($conditions = array(), $order = array(), $limit = null, $import_id = null) {
		global $wpdb;
	
		$query = "SELECT o.* FROM {$wpdb->prefix}arlo_offers AS o";
		
		$where = array("o.import_id = " . $import_id);
		$join = array();
	
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
				
				case 'oa_id':
					if(is_array($value)) {
						$where[] = "o.oa_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "o.oa_id = $value";
					}
				break;
				
				
				case 'event_template_id':
					$join[] = "
					LEFT JOIN {$wpdb->prefix}arlo_eventtemplates AS et USING (et_id) 
					";
					$where = array("et.import_id = " . $import_id);
					if(is_array($value)) {
						$where[] = "et.et_arlo_id IN (" . implode(',', $value) . ")";
					} else {
						$where[] = "et.et_arlo_id = $value";
					}
				break;
				
				case 'discounts':
					$where[] = "o.o_isdiscountoffer = " . ($value ? 1 : 0);
				break;
				case 'region':
					$where[] = "o.o_region = '" . $value . "'";
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
		
		$join = implode("\r", $join);
		
		$result = ($limit != 1) ? $wpdb->get_results($query.$join.$where.$order) : $wpdb->get_row($query.$join.$where.$order);
		
		return $result;
	}
}