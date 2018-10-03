<?php

namespace Arlo\Entities;

class Tags {
	static function get($conditions = array(), $limit = null, $import_id = null) {
		global $wpdb;

		$cache_key = md5(serialize(func_get_args()));
		$cache_category = 'ArloTags';
	
		if($cached = wp_cache_get($cache_key, $cache_category)) {
			return $cached;
		}

		$parameters = [];
	
		$query = "SELECT t.* FROM {$wpdb->prefix}arlo_tags AS t";
		
		$where = array("import_id = %d");
		$parameters[] =  $import_id;

		foreach($conditions as $key => $value) {
			// what to do?
			switch($key) {
				case 'id':
					if(is_array($value) && count($value) > 1) {
						$where[] = "t.id IN (" . implode(',', array_map(function() {return "%d";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						if (is_array($value) && count($value) == 1) {
							$value = array_pop($value);
						}

						if(!empty($value)) {						
							$where[] = "t.id = %d";
							$parameters[] = $value;
							$limit = 1;
						}	
					}
				break;
				
				case 'tag':
					if(is_array($value) && count($value) > 1) {
						$where[] = "t.tag IN (" . implode(',', array_map(function() {return "%s";}, $value)) . ")";
						$parameters = array_merge($parameters, $value);
					} else {
						if (is_array($value) && count($value) == 1) {
							$value = array_pop($value);
						}

						if(!empty($value)) {
							$where[] = "t.tag = %s";
							$parameters[] = $value;
							$limit = 1;
						}
					}
						
				break;				
			}
		}
		
		if(!empty($where)) {
			$query .= ' WHERE ' . implode(' AND ', $where);
			$query .= ' ORDER BY tag ASC';
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

	public static function look_up_tags_by_tag($tags, $import_id) {
		if (!isset($tags) || empty($tags)) return [];
		
		$condition = [
			'tag' => $tags
		];

		$tags = self::get($condition, 2, $import_id);

		if (!is_array($tags) && !is_null($tags)) {
			$tags = [$tags];
		}

		return $tags;
	}

	public static function get_tag_ids_by_tag($tags, $import_id) {
		if (!isset($tags) || empty($tags)) return [];

		$tags = self::look_up_tags_by_tag($tags, $import_id);

		if (!is_null($tags)) {
			return array_map(function($tag) {
				return $tag->id;
			}, $tags);
		}

		return null;
	}

	public static function get_first_id_by_tag($tag, $import_id) {
		if (empty($tag)) return null;

		$condition = [
			'tag' => $tag
		];

		$tag_obj = self::get($condition, 1, $import_id);
		if (empty($tag_obj->id)) return null;

		return $tag_obj->id;
	}

}