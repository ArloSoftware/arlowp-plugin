<?php

namespace Arlo\Entities;

class Templates {
	static function get($conditions=array(), $order=array(), $limit=null, $import_id = null) {
		global $wpdb;

		$cache_key = md5(serialize(func_get_args()));
		$cache_category = 'ArloTemplates';
	
		if($cached = wp_cache_get($cache_key, $cache_category)) {
			return $cached;
		}
	
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

		$query = $wpdb->prepare($query, $parameters);

		if ($query) {
			$result = ($limit != 1) ? $wpdb->get_results($query) : $wpdb->get_row($query);
			
			wp_cache_add( $cache_key, $result, $cache_category, 30 );
			
			return $result;
		} else {
			throw new \Exception("Couldn't prepare the SQL statement");
		}	
	}
}