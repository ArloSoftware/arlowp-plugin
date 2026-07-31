<?php

namespace ArloTraining\Entities;

use ArloTraining\CacheControl;
use Exception;

class OnlineActivities {
	static function get($conditions = array(), $order = array(), $limit = null, $import_id = null) {
		if (!is_null($limit) && (!is_numeric($limit) || $limit <= 0)){
			throw new Exception('Limit must be a positive integer or null');
		}

		global $wpdb;
	
		$query = "SELECT oa.* FROM {$wpdb->prefix}arlo_onlineactivities AS oa";
		
		$parameters = [];
		
		$where = array("import_id = %d");
		$parameters[] =  $import_id;
	
		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value)) {
						$where[] = "oa.oa_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "oa.oa_arlo_id = %d";
						$parameters[] = $value;
						$limit = 1;
					}
				break;
				
				case 'template_id':
					if(is_array($value)) {
						$where[] = "oa.oat_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						$where[] = "oa.oat_arlo_id = %d";
						$parameters[] = $value;
					}
				break;
				
				default:
					$where[] = $key;
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
			$order = ' ORDER BY ' . implode(', ', $order);//ReviewNote, the value in $order is string literals defined in PHP(always none here), no parameter there.
		}
		
		//limit
		
		$limit = is_numeric($limit) ? (int)$limit : 0;
		$limit_sql = '';
		if ($limit > 1) {
			$limit_sql = ' LIMIT %d';
			$parameters[] = $limit;
		}

		$query = $wpdb->prepare($query.$where.$order.$limit_sql, $parameters);// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
		
		if ($query) {
			return ($limit > 1) 
				? CacheControl::fetch_results($query)
				: CacheControl::fetch_row($query);
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}
	}
}