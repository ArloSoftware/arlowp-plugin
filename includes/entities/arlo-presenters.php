<?php

namespace Arlo\Entities;

class Presenters {
	static function get($conditions=array(), $order=array(), $limit=null, $import_id = null) {
		global $wpdb;

		$cache_key = md5(serialize(func_get_args()));
		$cache_category = 'ArloPresenters';
	
		if($cached = wp_cache_get($cache_key, $cache_category)) {
			return $cached;
		}
	
		$query = "SELECT p.* FROM {$wpdb->prefix}arlo_presenters AS p";

		$where = array("p.import_id = %d");
		$parameters[] =  $import_id;
		$group_by = $join = [];
		
		// conditions
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value) && count($value) > 1) {
						$where[] = "p.p_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						if (is_array($value) && count($value) == 1) {
							$value = array_pop($value);
						}

						if(!empty($value)) {						
							$where[] = "p.p_arlo_id = %d";
							$parameters[] = $value;
						}	
					}
				break;
				case 'e_id': 
					$order[] = 'p_order';
					$group_by[] = ' p.p_arlo_id ';
					$join[] = '
					INNER JOIN 
						' . $wpdb->prefix . 'arlo_events_presenters AS ep
					ON 
						p.p_arlo_id = ep.p_arlo_id 
					AND 
						p.import_id = ep.import_id
					';

					if(is_array($value) && count($value) > 1) {
						$where[] = "ep.e_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						if (is_array($value) && count($value) == 1) {
							$value = array_pop($value);
						}

						if(!empty($value)) {						
							$where[] = "ep.e_id = %d";
							$parameters[] = $value;
						}	
					}
				break;
				case 'template_id': 
					$order[] = 'p_order';
					$join[] = '
					INNER JOIN 
						' . $wpdb->prefix . 'arlo_eventtemplates_presenters AS etp
					ON 
						p.p_arlo_id = etp.p_arlo_id 
					AND 
						p.import_id = etp.import_id
					';
					$join[] = '
					INNER JOIN 
						' . $wpdb->prefix . 'arlo_eventtemplates AS et
					ON 
						etp.et_id = et.et_id 
					AND 
						etp.import_id = et.import_id 
					';
					$where[] = "et.et_arlo_id = %d";
					$parameters[] = $value;
				break;
			}
		}

		if (count($join)) {
			$query .= implode("\n", $join);
		}

		if (count($where)) {
			$query .= ' WHERE ' . implode(' AND ', $where);
		}

		if (count($group_by)) {
			$query .= ' GROUP BY ' . implode(', ', $group_by);
		}

		if (count($order)) {
			$query .= ' ORDER BY ' . implode(', ', $order);
		}

		if (intval($limit) > 1) {
			$query .= 'LIMIT ' . $limit;
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