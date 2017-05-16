<?php

namespace Arlo\Entities;

class Categories {
	static function get($conditions = array(), $limit = null, $import_id = null) {
		global $wpdb;

		$args = func_get_args();
				
		$cache_key = md5(serialize($args));
		$cache_category = 'ArloCategories';
	
		if($cached = wp_cache_get($cache_key, $cache_category)) {
			return $cached;
		}

		$parameters = [];
	
		$query = "SELECT c.* FROM {$wpdb->prefix}arlo_categories AS c";
		
		$where = array("import_id = %d");
		$parameters[] =  $import_id;

		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value) && count($value)) {
						$where[] = "c.c_arlo_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else if(!empty($value)) {
						$where[] = "c.c_arlo_id = %d";
						$parameters[] = $value;
						$limit = 1;
					}
				break;
				
				case 'slug':
					if(is_array($value) && count($value)) {
						$where[] = "c.c_slug IN (" . implode(',', array_map(function() {return "%s";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else if(!empty($value)) {
						$where[] = "c.c_slug = %s";
						$parameters[] = $value;
						$limit = 1;
					}
				break;
				
				case 'parent_id':
					if(is_array($value) && count($value)) {
						$where[] = "c.c_parent_id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else if(!empty($value)) {
						$where[] = "c.c_parent_id = %d";
						$parameters[] = $value;
					} else {
						$where[] = "c.c_parent_id = 0";
					}
					continue;
				break;
			}
		}
		
		if(!empty($where)) {
			$query .= ' WHERE ' . implode(' AND ', $where);
			$query .= ' ORDER BY c_order ASC';
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
	
	static function getTree($start_id = 0, $depth = 1, $level = 0, $import_id = null) {
		$result = null;
		$depth = intval($depth);
		$level = intval($level);
		$conditions = array('parent_id' => intval($start_id));
		
		$categories = self::get($conditions, null, $import_id);

		foreach($categories as $item) {		
			$item->depth_level = $level;	
			if($depth - 1 > $level) {
				$item->children = self::getTree($item->c_arlo_id, $depth, $level+1, $import_id);
			}
			$result[] = $item;
		}
		
		return $result;
	}

	static function child_categories($cats, $depth=0) {
		if(!is_array($cats)) return [];
		$depth = intval($depth);

		$space = ($depth > 0) ? ' ' : '';

		$output = array();

		foreach($cats as $cat) {

			$output[] = array(
				'string' => str_repeat('&ndash;', $depth) . $space . $cat->c_name,
				'value' => $cat->c_slug,
				'id' => $cat->c_arlo_id
				);
			$output = array_merge($output, self::child_categories($cat->children, $depth+1));
		}

		return $output;
	} 
}