<?php

namespace Arlo;

// load main Transport class for extending
require_once 'arlo-singleton.php';

use Arlo\Singleton;

class Categories extends Singleton {
	static function get($conditions=array(), $limit=null) {
		global $wpdb;
	
		$query = "SELECT c.* FROM {$wpdb->prefix}arlo_categories AS c";
		
		$where = array();
	
		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value)) {
						$where[] = "c.c_arlo_id IN (" . implode(',', $value) . ")";
					} else if(!empty($value)) {
						$where[] = "c.c_arlo_id = $value";
						$limit = 1;
					}
				break;
				
				case 'slug':
					if(is_array($value)) {
						$where[] = "c.c_slug IN (" . implode(',', $value) . ")";
					} else if(!empty($value)) {
						$where[] = "c.c_slug = '$value'";
						$limit = 1;
					}
				break;
				
				case 'parent_id':
					if(is_array($value)) {
						$where[] = "c.c_parent_id IN (" . implode(',', $value) . ")";
					} else if(!empty($value)) {
						$where[] = "c.c_parent_id = $value";
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
		
		$result = ($limit != 1) ? $wpdb->get_results($query) : $wpdb->get_row($query);
		
		return $result;
	}
	
	//$categories = \Arlo\Categories::getTree();
	static function getTree($start_id=0, $depth=null, $level = 0) {
		$result = null;
		$conditions = array('parent_id' => $start_id);
		
		foreach(self::get($conditions) as $item) {
			if($depth === null || $depth >= 1) {
				$new_depth = ($depth === null) ? null : $depth-1;
				$item->depth_level = $level;
				$item->children = self::getTree($item->c_arlo_id, $new_depth, $level+1);
			}
			$result[] = $item;
		}
		
		return $result;
	}
}