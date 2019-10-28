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
				break;
				
				case 'ignored':
					if(is_array($value) && count($value)) {
						$where[] = "c.c_arlo_id NOT IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else if(!empty($value)) {
						$where[] = "c.c_arlo_id <> %d";
						$parameters[] = $value;
					}
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
	
	static function getTree($start_id = 0, $depth = 1, $level = 0, $ignored_categories = null, $import_id = null, $insert_self = false) {
		$result = null;
		$depth = intval($depth);
		$level = intval($level);
		$start_id = intval($start_id);

		$categories = self::get(['parent_id' => $start_id, 'ignored' => $ignored_categories], null, $import_id);

		foreach($categories as $item) {		
			$item->depth_level = $level;	
			if($depth - 1 > $level) {
				$item->children = self::getTree($item->c_arlo_id, $depth, $level+1, null, $import_id);
			} else {
				unset($item->children);
			}
			$result[] = $item;
		}
		
		if ($insert_self) {
			$category = self::get(['id' => $start_id], null, $import_id);
			if (is_object($category)) {
				$category->children = $result;
				return [$category];
			}
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

	static function get_flattened_category_list_for_filter($base_category, $exclude_category, $import_id) {
		$cache_key = md5(serialize(func_get_args()));
		
        if(!($categories_flatten_list = wp_cache_get($cache_key, 'ArloFilterCategoryList'))) {
			
			$cats = self::get_merged_tree($base_category, $import_id);
			$categories_flatten_list = self::child_categories($cats);

			if (!is_array($exclude_category)) {
				$exclude_category = [$exclude_category];
			}
			$exclude_category = array_filter($exclude_category, function($val) {
				return intval($val) > 0;
			});
			if (count($exclude_category)) {
				$cats_not = self::get_merged_tree($exclude_category, $import_id);
				$categoriesnot_flatten_list = self::child_categories($cats_not);

				$categories_flatten_list = array_udiff($categories_flatten_list, $categoriesnot_flatten_list, function($a, $b) {
					return ($a['id'] - $b['id']);
				});
			}
			
            wp_cache_add( $cache_key, $categories_flatten_list, 'ArloFilterCategoryList', 30 );
        }

        return $categories_flatten_list;
	}
	
	static function get_merged_tree($categories = [], $import_id) {
		$cache_key = md5(serialize(func_get_args()));
                            
        if(!($cats = wp_cache_get($cache_key, 'ArloMergedCategoryList'))) {
			if (is_array($categories)) {                    
                $cats = [];
            
                foreach ($categories as $cat_id) {
                    $cat_tree = self::getTree($cat_id, 100, 0, null, $import_id, true);
                    $cats = array_merge($cats, (is_array($cat_tree) ? $cat_tree : []));
                }
            } else {
                $cats = self::getTree($categories, 1, 0, null, $import_id);
                if (!empty($cats)) {
                    $cats = self::getTree($cats[0]->c_arlo_id, 100, 0, null, $import_id);
                }
            }

            wp_cache_add( $cache_key, $cats, 'ArloMergedCategoryList', 30 );
        }

        return $cats;
	}

	/**
	 * Get a tree from a child element up
	 * @param  integer $child_id  Child c_arlo_id to start from
	 * @param  integer $import_id Import ID
	 * @return array              Ordered array of categories with root first
	 */
	public static function get_tree_from_child($child_id, $import_id){
		$cat = self::get(array('id' => $child_id), null, $import_id);
		$results = [$cat];

		while ($cat->c_parent_id > 1){
			$cat = self::get(array('id' => $cat->c_parent_id), null, $import_id);
			$results[] = $cat;
		}

		return array_reverse($results);
	}
}