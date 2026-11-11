<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Request-local limits used by DeltaRunCoordinator while attempting an Event
 * availability delta.
 *
 * This object is intentionally not persisted in importer task state or plugin
 * settings. A fresh importer request begins with the production defaults.
 */
final class DeltaRunParameters {
	public const MAX_SELECTED_EVENT_ROOTS_DEFAULT     = 16;
	public const MAX_SELECTED_FRAGMENT_COUNT_DEFAULT  = 4;
	public const MAX_SELECTED_DECODED_BYTES_DEFAULT   = ImportRequest::FRAGMENT_MAX_BYTE_SIZE;
	public const PREWRITE_BUDGET_MILLISECONDS_DEFAULT = 18000;

	/** The Event availability writer owns the bundle transaction. */
	public const EVENT_OVERLAY_TRANSACTION_SCOPE_STANDALONE = 'standalone';

	/** The enclosing operation owns the Event availability bundle transaction. */
	public const EVENT_OVERLAY_TRANSACTION_SCOPE_ENLISTED = 'enlisted';

	/** @var int */
	public $max_selected_event_roots = self::MAX_SELECTED_EVENT_ROOTS_DEFAULT;

	/** @var int */
	public $max_selected_fragment_count = self::MAX_SELECTED_FRAGMENT_COUNT_DEFAULT;

	/** @var int */
	public $max_selected_decoded_bytes = self::MAX_SELECTED_DECODED_BYTES_DEFAULT;

	/** @var int */
	public $prewrite_budget_milliseconds = self::PREWRITE_BUDGET_MILLISECONDS_DEFAULT;

	/** @var callable|null */
	public $prewrite_budget_clock;

	/** @var callable|null */
	public $before_fragment_fetch_hook;

	/**
	 * Controls ownership of each selected Event availability transaction.
	 *
	 * Production uses the standalone default. A caller that already owns the
	 * connection transaction must select the savepoint-based enlisted scope.
	 *
	 * @var string
	 */
	public $event_overlay_transaction_scope = self::EVENT_OVERLAY_TRANSACTION_SCOPE_STANDALONE;
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
