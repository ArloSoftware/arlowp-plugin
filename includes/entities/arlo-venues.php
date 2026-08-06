<?php

namespace ArloTraining\Entities;

use ArloTraining\CacheControl;
use Exception;

class Venues {
	static function get($conditions = array(), $order = array(), $limit = null, $import_id = null) {
		if (!is_null($limit) && (!is_numeric($limit) || $limit <= 0)){
			throw new Exception('Limit must be a positive integer or null');
		}

		global $wpdb;

		$parameters = [];

		$query = "SELECT v.* FROM {$wpdb->prefix}arlo_venues AS v";
		
		$where = array("import_id = %d");
		$parameters[] = $import_id;

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

		$query = $wpdb->prepare($query, $parameters); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.

		if ($query) {
			return ($limit != 1) 
				? CacheControl::fetch_results($query, ARRAY_A)
				: CacheControl::fetch_row($query, ARRAY_A);
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}
	}
}