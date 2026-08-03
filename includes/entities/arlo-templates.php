<?php

namespace ArloTraining\Entities;

use ArloTraining\CacheControl;
use Exception;

class Templates {
	static function get($conditions=array(), $order=array(), $limit=null, $import_id = null) {
		if (!is_null($limit) && (!is_numeric($limit) || $limit <= 0)){
			throw new Exception('Limit must be a positive integer or null');
		}

		global $wpdb;
		
		$parameters = [];
	
		$query = "SELECT et.* FROM {$wpdb->prefix}arlo_eventtemplates AS et";
		
		$where = array("import_id = %d");
		$parameters[] =  $import_id;
	
		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value)) {
						$where[] = "et.et_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "et.et_arlo_id = %d";
						$parameters[] = $value;
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

		$query = $wpdb->prepare($query, $parameters);// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.

		if ($query) {
			return ($limit != 1) 
				? CacheControl::fetch_results($query)
				: CacheControl::fetch_row($query);
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}	
	}
}