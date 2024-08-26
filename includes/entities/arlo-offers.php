<?php

namespace Arlo\Entities;

class Offers {
	static function get($conditions = array(), $order = array(), $limit = null, $import_id = null) {
		global $wpdb;
	
		$query = "SELECT o.* FROM {$wpdb->prefix}arlo_offers AS o";

		$parameters = [];
		
		$where = array("o.import_id = %d");
		$parameters[] = $import_id;

		$join = [];

		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value) && count($value)) {
						$where[] = "o.o_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "o.o_arlo_id = %d";
						$parameters[] = $value;
						$limit = 1;
					}
				break;
				
				case 'event_id':
					if(is_array($value) && count($value)) {
						$where[] = "o.e_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "o.e_id = %d";
						$parameters[] = $value;
					}
				break;
				
				case 'oa_id':
					if(is_array($value) && count($value)) {
						$where[] = "o.oa_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "o.oa_id = %d";
						$parameters[] = $value;
					}
				break;
				
				
				case 'event_template_id':
					$join[] = " LEFT JOIN {$wpdb->prefix}arlo_eventtemplates AS et USING (et_id) ";
					$where[] = "et.import_id = %d ";
					$parameters[] = $import_id;

					if(is_array($value) && count($value)) {
						$where[] = "et.et_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "et.et_arlo_id = %d";
						$parameters[] = $value;
					}
				break;
				
				case 'discounts':
					$where[] = "o.o_isdiscountoffer = %d";
					$parameters[] = ($value ? 1 : 0);
				break;
				case 'region':
					$where[] = "o.o_region = %s";
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
		
		$join = implode("\n", $join);

		$query = $wpdb->prepare($query.$join.$where.$order, $parameters);

		if ($query) {
			return ($limit != 1) ? $wpdb->get_results($query) : $wpdb->get_row($query);
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}
	}
}