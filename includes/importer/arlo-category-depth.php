<?php

namespace ArloTraining\Importer;
use ArloTraining\CacheControl;
use ArloTraining\Entities\Categories as CategoriesEntity;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Rebuilds derived category metadata after template/category links are imported.
 *
 * This importer does not consume a decoded API payload item-by-item. Instead it
 * runs as a post-processing step over the already imported category and
 * event-template relationship tables to:
 *
 * - count directly linked templates per category
 * - calculate each category's depth within the category tree
 * - roll descendant template counts up to parent categories
 * - assign a stable tree-order value used for hierarchical display
 *
 * The result is a fully populated category tree that shortcode and admin views
 * can query without recalculating these aggregates at render time.
 */
class CategoryDepth extends AbstractImportStep {

	protected function import_entity( $entity_payload ) {}

	public function run() {
		global $wpdb;
		$this->execute_prepared_query(
			"UPDATE {$wpdb->prefix}arlo_categories SET c_template_num = 0, c_depth_level = 0 WHERE import_id = %d",
			[ $this->import_id ]
		);

		// Phase 1: seed each category with its direct template count from the
		// imported template/category link table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table.Do not need cache for data import process. Cache will be reset after the import process done.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"
		SELECT
			COUNT(1) AS num,
			c_arlo_id
		FROM
			{$wpdb->prefix}arlo_eventtemplates_categories
		WHERE
			import_id = %d
		GROUP BY
			c_arlo_id
		",
				$this->import_id
			),
			ARRAY_A
		);
		if ( ! is_null( $items ) ) {
			foreach ( $items as $counts ) {
				$this->execute_prepared_query(
					"UPDATE {$wpdb->prefix}arlo_categories SET c_template_num = %d WHERE c_arlo_id = %d AND import_id = %d",
					[ $counts['num'], $counts['c_arlo_id'], $this->import_id ]
				);
			}
		}

		CacheControl::cache_delete( CacheControl::GROUP_PRIMARY_OBJECTS );
		$cats = CategoriesEntity::getTree( 0, 1000, 0, null, $this->import_id );
		if ( is_array( $cats ) ) {
			// Phase 2: walk the imported tree to derive display metadata from those
			// direct counts: depth, rolled-up descendant totals, and stable order.
			$this->set_category_depth_level( $cats );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process.
			$max_depth = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(c_depth_level) FROM {$wpdb->prefix}arlo_categories WHERE import_id = %d", $this->import_id ) );

			$this->set_category_depth_order( $cats, $max_depth, 0 );

			for ( $i = $max_depth + 1; $i--; $i < 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process.
				$cats = $wpdb->get_results(
					$wpdb->prepare(
						"
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
				",
						$i,
						$this->import_id
					),
					ARRAY_A
				);
				if ( ! is_null( $cats ) ) {
					foreach ( $cats as $cat ) {
						$this->execute_prepared_query(
							"UPDATE {$wpdb->prefix}arlo_categories SET c_template_num = c_template_num + %d WHERE c_arlo_id = %d AND import_id = %d",
							[ $cat['num'], $cat['c_parent_id'], $this->import_id ]
						);
					}
				}
			}
		}

		$this->is_finished = true;
	}

	/**
	 * Persist the precomputed depth level for each node in the imported category tree.
	 *
	 * The tree returned by CategoriesEntity::getTree() already carries each node's
	 * derived depth_level value. This helper walks that tree recursively and stores
	 * the value on the matching arlo_categories rows for the current import.
	 *
	 * @param array $cats Category tree nodes with depth_level and optional children.
	 * @return void
	 */
	private function set_category_depth_level( $cats = [] ) {
		global $wpdb;
		foreach ( $cats as $cat ) {
			$this->execute_prepared_query(
				"UPDATE {$wpdb->prefix}arlo_categories SET c_depth_level = %d WHERE c_arlo_id = %d AND import_id = %d",
				[ $cat->depth_level, $cat->c_arlo_id, $this->import_id ]
			);
			if ( isset( $cat->children ) && is_array( $cat->children ) ) {
				$this->set_category_depth_level( $cat->children );
			}
		}
	}

	/**
	 * Persist a stable hierarchical display order for each node in the category tree.
	 *
	 * Ordering is derived recursively from the parent's order plus the node's
	 * position at its depth, using the maximum tree depth to keep sibling ranges
	 * from overlapping across levels.
	 *
	 * @param array $cats         Category tree nodes with depth_level, c_order, and optional children.
	 * @param int   $max_depth    Maximum depth present in the current category tree.
	 * @param int   $parent_order Accumulated order prefix from the parent branch.
	 * @return void
	 */
	private function set_category_depth_order( $cats, $max_depth, $parent_order = 0 ) {
		global $wpdb;
		$num = 100;

		foreach ( $cats as $index => $cat ) {
			$order        = $parent_order + pow( $num, $max_depth - $cat->depth_level ) * ( $index + 1 );
			$source_order = (int) $cat->c_order;
			if ( $source_order >= $order ) {
				$source_order -= $order;
			}

			$this->execute_prepared_query(
				"UPDATE {$wpdb->prefix}arlo_categories SET c_order = %d WHERE c_arlo_id = %d AND import_id = %d",
				[ $order + $source_order, $cat->c_arlo_id, $this->import_id ]
			);
			if ( isset( $cat->children ) && is_array( $cat->children ) ) {
				$this->set_category_depth_order( $cat->children, $max_depth, $order );
			}
		}
	}
}
