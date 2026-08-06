<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;
use ArloTraining\Entities\Categories as CategoriesEntity;

class CategoryDepth extends BaseImporter {

	protected function save_entity($item) {}

	public function run() {
		global $wpdb;
		//count the templates in the categories
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table.Do not need cache for data import process. Cache will be reset after the import process done.
		$items = $wpdb->get_results($wpdb->prepare("
		SELECT
			COUNT(1) AS num,  
			c_arlo_id
		FROM
			{$wpdb->prefix}arlo_eventtemplates_categories
		WHERE
			import_id = %d
		GROUP BY
			c_arlo_id
		", $this->import_id), ARRAY_A);
		if (!is_null($items)) {
			foreach ($items as $counts) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process.
				$query = $wpdb->query( $wpdb->prepare("
				UPDATE
					{$wpdb->prefix}arlo_categories
				SET
					c_template_num = %d
				WHERE
					c_arlo_id = %d
				AND
					import_id = %d
				", $counts['num'], $counts['c_arlo_id'], $this->import_id) );
				
				if ($query === false) {
					throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
				}
			}		
		}
		
		if (is_array($cats = CategoriesEntity::getTree(0, 1000, 0, null, $this->import_id))) {
			$this->set_category_depth_level($cats, $this->import_id);
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process.
			$max_depth = $wpdb->get_var($wpdb->prepare("SELECT MAX(c_depth_level) FROM {$wpdb->prefix}arlo_categories WHERE import_id = %d", $this->import_id));
			
			$this->set_category_depth_order($cats, $max_depth, 0, $this->import_id);
					
			for ($i = $max_depth+1; $i--; $i < 0) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process.
				$cats = $wpdb->get_results($wpdb->prepare("
				SELECT 
					SUM(c_template_num) as num,
					c_parent_id
				FROM
					{$wpdb->prefix}arlo_categories
				WHERE
					c_depth_level = %d
				AND
					import_id = %d
				GROUP BY
					c_parent_id
				", $i, $this->import_id), ARRAY_A);
				if (!is_null($cats)) {
					foreach ($cats as $cat) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database query is required for custom table. Do not need cache for import process.
						$query = $wpdb->query( $wpdb->prepare("
						UPDATE
							{$wpdb->prefix}arlo_categories
						SET
							c_template_num = c_template_num + %d
						WHERE
							c_arlo_id = %d
						AND
							import_id = %d
						", $cat['num'], $cat['c_parent_id'], $this->import_id) );
					}
				}
			}
		}
				

		$this->is_finished = true;
	}

	private function set_category_depth_level($cats = []) {		
		global $wpdb;
		foreach ($cats as $cat) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process.
			$query = $wpdb->query( $wpdb->prepare("
			UPDATE 
				{$wpdb->prefix}arlo_categories
			SET 
				c_depth_level = %d
			WHERE
				c_arlo_id = %d
			AND
				import_id = %d
			", $cat->depth_level, $cat->c_arlo_id, $this->import_id) );
            // phpcs:enable
			if (isset($cat->children) && is_array($cat->children)) {
				$this->set_category_depth_level($cat->children, $this->import_id);
			}
		}
	}
	
	private function set_category_depth_order($cats, $max_depth, $parent_order = 0) {
		global $wpdb;
		$num = 100;
		
		foreach ($cats as $index => $cat) {		
			$order = $parent_order + pow($num, $max_depth - $cat->depth_level) * ($index + 1);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process.
			$query = $wpdb->query( $wpdb->prepare("
			UPDATE
				{$wpdb->prefix}arlo_categories
			SET
				c_order = %d
			WHERE
				c_arlo_id = %d
			AND
				import_id = %d	
			", $order + $cat->c_order, $cat->c_arlo_id, $this->import_id) );
			if ($query === false) {
				throw new \Exception('SQL error: ' . $wpdb->last_error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			} else if (is_array($cat->children)) {
				$this->set_category_depth_order($cat->children, $max_depth, $order, $this->import_id);
			}
		}
	}	
}