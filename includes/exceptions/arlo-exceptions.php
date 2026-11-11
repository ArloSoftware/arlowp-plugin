<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Generic.Files.OneObjectStructurePerFile.MultipleFound -- This established shared exception file intentionally declares the plugin's small exception family.

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * A local scheduler/configuration problem that should fail the current
 * import attempt immediately.
 *
 * Remarks:
 * - Used for conditions such as an invalid platform name/callback host, or
 *   failure to re-trigger the scheduler after a subtask. These are terminal
 *   for the current attempt and are not counted toward the platform-access
 *   health circuit breaker (`Importer::handle_platform_access_failure()`),
 *   because they do not prove the platform itself is chronically unreachable.
 */
class SchedulerException extends \Exception {}

/**
 * A failure attempting to send a request to, or receive a valid response
 * from, the Arlo platform — includes an outbound request that could not
 * even be constructed (e.g. the body failed to encode as JSON), a
 * network-level failure, and a non-success or otherwise unusable HTTP
 * response.
 *
 * Remarks:
 * - Always terminal for the current attempt, and counted toward the
 *   platform-access health circuit breaker on a scheduled sync (see
 *   `Importer::run_locked_import()`'s catch block).
 */
class PlatformAccessHttpException extends \Exception {}

/**
 * An inbound platform callback that failed validation — a malformed or
 * oversized body, or an unknown request ID. A bad nonce for a request ID that
 * does match an import is the more specific
 * `ImportCallbackNonceRejectedException`.
 *
 * Remarks:
 * - Thrown only from `Importer::callback()` and
 *   `Importer::get_and_validate_import_entry()`, not from the
 *   `run_locked_import()` subtask pipeline; logged and otherwise ignored.
 */
class ImportCallbackRejectedException extends \Exception {}

/**
 * An inbound platform callback that matched a pending import's request ID but
 * failed its nonce check (a mismatch, or a replay of a consumed nonce).
 *
 * Remarks:
 * - Unlike a rejection from a caller that matched nothing, this concerns a real
 *   import, so it is logged at info level to its own throttle bucket rather
 *   than sharing the unmatched-noise budget.
 */
class ImportCallbackNonceRejectedException extends ImportCallbackRejectedException {

	/**
	 * Import whose request ID the callback matched.
	 *
	 * @var int|null
	 */
	public $import_id;

	/**
	 * @param string   $message   Fixed rejection message.
	 * @param int|null $import_id Import whose request ID matched.
	 */
	public function __construct( $message = '', $import_id = null ) {
		parent::__construct( $message );
		$this->import_id = $import_id;
	}
}

/**
 * An invalid platform name submitted through Arlo Settings.
 *
 * Remarks:
 * - Thrown during settings validation
 *   (`Utilities::sanitize_and_validate_platform_name()`), not during an
 *   import run.
 */
class PlatformNameException extends \Exception {}

/**
 * The downloaded snapshot payload itself is unusable — it fails decryption,
 * exceeds a size limit, or fails a URI/hostname safety check (including
 * SSRF protections and validating the platform hostname declared in the
 * import response) — rather than the platform being unreachable.
 *
 * Remarks:
 * - Always terminal for the current attempt, and not counted toward the
 *   platform-access health circuit breaker, since retrying would not help a
 *   malformed payload.
 */
class SnapshotProcessException extends \Exception {}

/**
 * A snapshot manifest cannot be safely loaded within the configured PHP memory
 * limit. The byte measurements are retained for the importer diagnostic log.
 */
class SnapshotManifestMemoryException extends SnapshotProcessException {
	/** @var int */
	public $memory_limit_bytes;

	/** @var int */
	public $manifest_bytes;

	/** @var int */
	public $estimated_memory_required_bytes;

	/**
	 * @param int $memory_limit_bytes Configured PHP memory limit in bytes.
	 * @param int $manifest_bytes Advertised manifest size in bytes.
	 * @param int $estimated_memory_required_bytes Estimated parsing requirement in bytes.
	 */
	public function __construct( int $memory_limit_bytes, int $manifest_bytes, int $estimated_memory_required_bytes ) {
		parent::__construct( 'Snapshot manifest cannot be processed within PHP memory limit' );
		$this->memory_limit_bytes              = $memory_limit_bytes;
		$this->manifest_bytes                  = $manifest_bytes;
		$this->estimated_memory_required_bytes = $estimated_memory_required_bytes;
	}
}

/**
 * A delta write may have committed after the baseline was marked full-required.
 *
 * The current task must fail rather than retrying the delta route; the next
 * ordinary import converges through full shadow.
 */
class DeltaFatalException extends \Exception {}

/**
 * A bounded Event-availability delta requires more snapshot fragments than
 * the configured pre-write limit permits.
 *
 * This is handled before writes by DeltaRunCoordinator, which safely falls
 * back to ordinary full shadow rather than retrying the delta route.
 */
class DeltaFragmentLimitException extends \Exception {}

/**
 * A missing/undelivered import callback, distinguished from a plain
 * \Exception so callers can identify this specific failure without matching
 * on message text.
 *
 * Remarks:
 * - Retryable like a plain \Exception while the callback wait limit
 *   (`Arlo_For_Wordpress::IMPORT_CALLBACK_WAIT_TIMEOUT_SECONDS`, measured from the
 *   snapshot request) has not passed; this does not consume the retry ceiling
 *   (see `Importer::run_locked_import()`'s catch block). After the limit the
 *   terminal failure is counted toward the platform-access health circuit
 *   breaker like `PlatformAccessHttpException` is, and a callback that arrives
 *   later is ignored.
 */
class CallbackTimeoutException extends \Exception {}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName, Generic.Files.OneObjectStructurePerFile.MultipleFound
