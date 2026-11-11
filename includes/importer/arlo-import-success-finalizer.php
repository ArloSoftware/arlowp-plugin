<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

use ArloTraining\Logger;
use ArloTraining\Provisioning\SchemaManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Shared successful-import finalization helper invoked by import steps.
 *
 * Callers own the durable decision and any one-time claim before invoking it.
 */
class ImportSuccessFinalizer {
	/**
	 * Applies shared success side effects for an already-successful import.
	 *
	 * @param mixed    $importer Owning importer.
	 * @param mixed    $message_handler Import message handler.
	 * @param int      $import_id Completed import id.
	 * @return void
	 */
	public function finalize_import( $importer, $message_handler, int $import_id ): void {
		$importer->set_last_import_date();
		$message_handler->dismiss_by_type( 'import_error' );
		$message_handler->dismiss_by_type_and_title( 'error', SchemaManager::get_schema_upgrade_warning_title() );
		\Arlo_For_Wordpress::reset_connection_health();

		Logger::log( 'Import processing complete', $import_id, null, true );

		try {
			do_action( 'arlo_import_finished' );
		} catch ( \Throwable $exception ) {
			Logger::log( $exception->getMessage(), $import_id );
		}
	}
}
