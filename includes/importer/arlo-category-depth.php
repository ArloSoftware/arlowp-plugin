<?php

namespace Arlo\Importer;

class CategoryDepth extends BaseEntity {

	protected function save_entity($item) {}

	public function import() {
		//count the templates in the categories
		$sql = "
		SELECT
			COUNT(1) AS num,  
			c_arlo_id
		FROM
			" . $this->wpdb->prefix . "arlo_eventtemplates_categories
		WHERE
			import_id = " . $this->import_id . "
		GROUP BY
			c_arlo_id
		";

		$items = $this->wpdb->get_results($sql, ARRAY_A);
		if (!is_null($items)) {
			foreach ($items as $counts) {
				$sql = "
				UPDATE
					" . $this->wpdb->prefix . "arlo_categories
				SET
					c_template_num = %d
				WHERE
					c_arlo_id = %d
				AND
					import_id = " . $this->import_id . "
				";
				$query = $this->wpdb->query( $this->wpdb->prepare($sql, $counts['num'], $counts['c_arlo_id']) );
				
				if ($query === false) {
					Logger::log('SQL error: ' . $this->wpdb->last_error . ' ' . $this->wpdb->last_query, $this->import_id, null, false , true);
				}
			}		
		}
		
		$cats = \Arlo\Categories::getTree(0, 1000, 0, $this->import_id);
				
		$this->set_category_depth_level($cats, $this->import_id);
		
		$sql = "SELECT MAX(c_depth_level) FROM " . $this->wpdb->prefix . "arlo_categories WHERE import_id = " . $this->import_id . "";
		$max_depth = $this->wpdb->get_var($sql);
		
		$this->set_category_depth_order($cats, $max_depth, 0, $this->import_id);
				
		for ($i = $max_depth+1; $i--; $i < 0) {
			$sql = "
			SELECT 
				SUM(c_template_num) as num,
				c_parent_id
			FROM
				" . $this->wpdb->prefix . "arlo_categories
			WHERE
				c_depth_level = {$i}
			AND
				import_id = " . $this->import_id . "
			GROUP BY
				c_parent_id
			";

			$cats = $this->wpdb->get_results($sql, ARRAY_A);
			if (!is_null($cats)) {
				foreach ($cats as $cat) {
					$sql = "
					UPDATE
						" . $this->wpdb->prefix . "arlo_categories
					SET
						c_template_num = c_template_num + %d
					WHERE
						c_arlo_id = %d
					AND
						import_id = " . $this->import_id . "
					";
					$query = $this->wpdb->query( $this->wpdb->prepare($sql, $cat['num'], $cat['c_parent_id']) );
				}
			}
		}

		$this->is_finished = true;
	}

	private function set_category_depth_level($cats = []) {		
		foreach ($cats as $cat) {
			$sql = "
			UPDATE 
				" . $this->wpdb->prefix ."arlo_categories
			SET 
				c_depth_level = %d
			WHERE
				c_arlo_id = %d
			AND
				import_id = %s
			";
			$query = $this->wpdb->query( $this->wpdb->prepare($sql, $cat->depth_level, $cat->c_arlo_id, $this->import_id) );
			if (isset($cat->children) && is_array($cat->children)) {
				$this->set_category_depth_level($cat->children, $this->import_id);
			}
		}
	}
	
	private function set_category_depth_order($cats = [], $max_depth, $parent_order = 0) {
		$num = 100;
		
		foreach ($cats as $index => $cat) {		
			$order = $parent_order + pow($num, $max_depth - $cat->depth_level) * ($index + 1);

			$sql = "
			UPDATE
				" . $this->wpdb->prefix . "arlo_categories
			SET
				c_order = %d
			WHERE
				c_arlo_id = %d
			AND
				import_id = %s	
			";
			
			$query = $this->wpdb->query( $this->wpdb->prepare($sql, $order + $cat->c_order, $cat->c_arlo_id, $this->import_id) );
			if ($query === false) {
				Logger::log('SQL error: ' . $this->wpdb->last_error . ' ' . $this->wpdb->last_query, $this->import_id, null, false , true);
			} else if (is_array($cat->children)) {
				$this->set_category_depth_order($cat->children, $max_depth, $order, $this->import_id);
			}
		}
	}	
}