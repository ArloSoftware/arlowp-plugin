<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Imports timezone reference data used by event and session records.
 *
 * Each payload item becomes one timezone row for the current import so later
 * entity tasks can resolve timezone ids and offsets against local data.
 */
class Timezones extends AbstractImportStep {
	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
	}

	protected function import_entity( $entity_payload ) {
		global $wpdb;
		// Delete any prior row for this business identity + import to enable safe replay
		// if a previous save was interrupted before the checkpoint advanced.
		$this->execute_prepared_query(
			"DELETE FROM {$wpdb->prefix}arlo_timezones WHERE id = %d AND import_id = %d",
			[ $entity_payload->TimeZoneID, (int) $this->import_id ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. No cache needed for the insert operation in data import process. Cache will be reset after the import process done.
		$query = $wpdb->insert(
			$wpdb->prefix . 'arlo_timezones',
			array(
				'id'            => $entity_payload->TimeZoneID,
				'name'          => $entity_payload->Name,
				'windows_tz_id' => $entity_payload->WindowsTzID,
				'utc_offset'    => $entity_payload->UtcOffset,
				'import_id'     => $this->import_id,
			),
			array(
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
			)
		);

		if ( $query === false ) {
			throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
	}
}
