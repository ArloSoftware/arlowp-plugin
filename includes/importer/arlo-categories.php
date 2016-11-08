<?php

namespace Arlo\Importer;

class Categories extends Importer {

	public function __construct() {	}

	public function import() {
		$table_name = parent::$wpdb->prefix . 'arlo_categories'; 
	
		if (!empty(parent::$data_json->Categories) && is_array(parent::$data_json->Categories)) {
			foreach(parent::$data_json->Categories as $item) {
				$slug = sanitize_title($item->CategoryID . ' ' . $item->Name);
				$query = parent::$wpdb->query( parent::$wpdb->prepare( 
					"INSERT INTO $table_name 
					(c_arlo_id, c_name, c_slug, c_header, c_footer, c_order, c_parent_id, active) 
					VALUES ( %d, %s, %s, %s, %s, %d, %d, %s ) 
					", 
				    $item->CategoryID,
					$item->Name,
					$slug,
					@$item->Description->Text,
					@$item->Footer->Text,
					@$item->SequenceIndex,
					@$item->ParentCategoryID,
					parent::$import_id
				) );
                                
                if ($query === false) {
                	parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' .parent::$wpdb->last_query, parent::$import_id);
                    throw new Exception('Database insert failed: ' . $table_name);
                }  
			}
		}
	

		$this->import_eventtemplatescategoriesitems();

		//count the templates in the categories
		$sql = "
		SELECT
			COUNT(1) AS num,  
			c_arlo_id
		FROM
			" . parent::$wpdb->prefix . "arlo_eventtemplates_categories
		WHERE
			active = " . parent::$import_id . "
		GROUP BY
			c_arlo_id
		";

		$items = parent::$wpdb->get_results($sql, ARRAY_A);
		if (!is_null($items)) {
			foreach ($items as $counts) {
				$sql = "
				UPDATE
					" . parent::$wpdb->prefix . "arlo_categories
				SET
					c_template_num = %d
				WHERE
					c_arlo_id = %d
				AND
					active = " . parent::$import_id . "
				";
				$query = parent::$wpdb->query( parent::$wpdb->prepare($sql, $counts['num'], $counts['c_arlo_id']) );
				
				if ($query === false) {
					parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' . parent::$wpdb->last_query, parent::$import_id);
					throw new Exception('Database insert failed: ' . $table_name);
				}
			}		
		}
		
		$cats = \Arlo\Categories::getTree(0, 1000, 0, parent::$import_id);
				
		$this->set_category_depth_level($cats, parent::$import_id);
		
		$sql = "SELECT MAX(c_depth_level) FROM " . parent::$wpdb->prefix . "arlo_categories WHERE active = " . parent::$import_id . "";
		$max_depth = parent::$wpdb->get_var($sql);
		
		$this->set_category_depth_order($cats, $max_depth, 0, parent::$import_id);
				
		for ($i = $max_depth+1; $i--; $i < 0) {
			$sql = "
			SELECT 
				SUM(c_template_num) as num,
				c_parent_id
			FROM
				" . parent::$wpdb->prefix . "arlo_categories
			WHERE
				c_depth_level = {$i}
			AND
				active = " . parent::$import_id . "
			GROUP BY
				c_parent_id
			";

			$cats = parent::$wpdb->get_results($sql, ARRAY_A);
			if (!is_null($cats)) {
				foreach ($cats as $cat) {
					$sql = "
					UPDATE
						" . parent::$wpdb->prefix . "arlo_categories
					SET
						c_template_num = c_template_num + %d
					WHERE
						c_arlo_id = %d
					AND
						active = " . parent::$import_id . "
					";
					$query = parent::$wpdb->query( parent::$wpdb->prepare($sql, $cat['num'], $cat['c_parent_id']) );
				}
			}
		}
	}

private function set_category_depth_level($cats = []) {		
		foreach ($cats as $cat) {
			$sql = "
			UPDATE 
				" . parent::$wpdb->prefix ."arlo_categories
			SET 
				c_depth_level = %d
			WHERE
				c_arlo_id = %d
			AND
				active = %s
			";
			$query = parent::$wpdb->query( parent::$wpdb->prepare($sql, $cat->depth_level, $cat->c_arlo_id, parent::$import_id) );
			if (isset($cat->children) && is_array($cat->children)) {
				$this->set_category_depth_level($cat->children, parent::$import_id);
			}
		}
	}
	
	private function set_category_depth_order($cats = [], $max_depth, $parent_order = 0) {
		$num = 100;
		
		foreach ($cats as $index => $cat) {		
			$order = $parent_order + pow($num, $max_depth - $cat->depth_level) * ($index + 1);

			$sql = "
			UPDATE
				" . parent::$wpdb->prefix . "arlo_categories
			SET
				c_order = %d
			WHERE
				c_arlo_id = %d
			AND
				active = %s	
			";
			
			$query = parent::$wpdb->query( parent::$wpdb->prepare($sql, $order + $cat->c_order, $cat->c_arlo_id, parent::$import_id) );
			if ($query === false) {
				parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' . parent::$wpdb->last_query, parent::$import_id);
				throw new Exception('Database update failed in set_category_depth_order()');
			} else if (is_array($cat->children)) {
				$this->set_category_depth_order($cat->children, $max_depth, $order, parent::$import_id);
			}
		}
	}	

	private function import_eventtemplatescategoriesitems() {
		$table_name = parent::$wpdb->prefix . 'arlo_eventtemplates_categories'; 

		if (!empty(parent::$data_json->CategoryItems) && is_array(parent::$data_json->CategoryItems)) {
			foreach(parent::$data_json->CategoryItems as $item) {
		
				$sql = "
				UPDATE
					" . $table_name . "
				SET
					et_order = %d
				WHERE
					et_arlo_id = %d
				AND
					c_arlo_id = %d
				";

				$query = parent::$wpdb->query( parent::$wpdb->prepare($sql, !empty($item->SequenceIndex) ? $item->SequenceIndex : 0, $item->EventTemplateID, $item->CategoryID) );
				
				if ($query === false) {
					parent::$plugin->add_log('SQL error: ' . parent::$wpdb->last_error . ' ' .parent::$wpdb->last_query, parent::$import_id);
					throw new Exception('Database insert failed: ' . $table_name);
				}
					
				
			}
		}
	}
}