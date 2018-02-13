<?php

namespace Arlo\Entities;

class Venues {
	static function get($conditions = array(), $order = array(), $limit = null, $import_id = null) {
		global $wpdb;

		$cache_key = md5(serialize(func_get_args()));
		$cache_category = 'ArloVenues';
	
		if($cached = wp_cache_get($cache_key, $cache_category)) {
			return $cached;
		}
		
		$parameters = [];

		$query = "SELECT v.* FROM {$wpdb->prefix}arlo_venues AS v";
		
		$where = array("import_id = " . $import_id);
	
		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value)) {
						$where[] = "v.v_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "v.v_arlo_id = %d";
						$parameters[] = $value;
						$limit = 1;
					}
				break;
				case 'state':
					if(is_array($value)) {
						$where[] = "v.v_physicaladdressstate IN (" . implode(',', array_map(function() {return "%s";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "v.v_physicaladdressstate = %s";
						$parameters[] = $value;
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

		$query = $wpdb->prepare($query, $parameters);

		if ($query) {
			$result = ($limit != 1) ? $wpdb->get_results($query, ARRAY_A) : $wpdb->get_row($query, ARRAY_A);

			wp_cache_add( $cache_key, $result, $cache_category, 30 );

			return $result;
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}
	}
}