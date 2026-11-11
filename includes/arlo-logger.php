<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Writes rows to the Arlo log table (`{$wpdb->prefix}arlo_log`)
 * and exposes bounded maintenance for trimming old log rows.
 *
 * Remarks:
 * - `execute_log_maintenance()` is called explicitly by import lifecycle
 *   boundaries, not by every write. The one exception is the throttled
 *   `log_rejected_callback()` and `log_rejected_matched_callback()`, which each
 *   run one bounded batch per throttle window.
 * - `get_log_rows()` reads back through `CacheControl::GROUP_LOG`, invalidated
 *   by `save_log()` and maintenance. CSV export queries directly so SQL errors
 *   are not hidden by cached rows.
 */
class Logger {
	/** Stored log row for ordinary operational information. */
	const DIAGNOSTIC_LEVEL_INFO = 0;

	/** Stored log row for support diagnostic detail. */
	const DIAGNOSTIC_LEVEL_DEBUG = 1;

	/** Stored log row for the most detailed support diagnostic trace. */
	const DIAGNOSTIC_LEVEL_TRACE = 2;

	/** Support diagnostic logging disabled; API callers use this for settings and write gating. */
	const DIAGNOSTIC_VERBOSITY_OFF = self::DIAGNOSTIC_LEVEL_INFO;

	/** Support debug diagnostic logging; rows written at this verbosity are stored as debug-level rows. */
	const DIAGNOSTIC_VERBOSITY_DEBUG = self::DIAGNOSTIC_LEVEL_DEBUG;

	/** Support trace diagnostic logging; rows written at this verbosity are stored as trace-level rows. */
	const DIAGNOSTIC_VERBOSITY_TRACE = self::DIAGNOSTIC_LEVEL_TRACE;

	/** arlo_settings key for the numeric support diagnostic verbosity. */
	const DIAGNOSTIC_VERBOSITY_SETTING_KEY = 'log_diagnostic_level';

	/** Default maximum log rows retained. */
	const LOG_RETENTION_DEFAULT_MAX_ROWS = 10000;

	/** Supported retention limits for log rows. */
	const LOG_RETENTION_SUPPORTED_MAX_ROWS = array( 10000, 25000, 100000, 250000 );

	/**
	 * Row count above which an explicit retention change clears the log.
	 * Repeated batch deletes can exceed request budgets on slow shared hosting;
	 * truncation is the pressure valve once the backlog is already extreme.
	 */
	const LOG_RETENTION_TRUNCATE_THRESHOLD_ROWS = 300000;

	/** Maximum number of delete batches allowed for explicit retention changes. */
	const LOG_RETENTION_CHANGE_MAX_BATCHES = 1000;

	/** Wall-clock ceiling, in seconds, for cleanup caused by an explicit retention change. */
	const LOG_RETENTION_CHANGE_MAX_SECONDS = 10;

	/** Maximum number of log rows deleted by one maintenance batch. */
	const LOG_CLEANUP_BATCH_LIMIT = 1000;

	/** Default maximum rows returned by get_log_rows() when callers omit a limit. */
	const LOG_ROWS_DEFAULT_LIMIT = 100;

	/** Maximum number of delete batches allowed in one automatic maintenance pass. */
	const LOG_CLEANUP_MAX_BATCHES = 3;

	/** Wall-clock ceiling, in seconds, for automatic maintenance inside importer requests. */
	const LOG_CLEANUP_MAX_SECONDS = 2;

	/** Option containing the latest maintenance status that needs admin/support visibility. */
	const LOG_CLEANUP_STATUS_OPTION = 'arlo_log_cleanup_status';

	/** Option holding the throttle state for rejected import callback log rows. Never autoloaded. */
	const REJECTED_CALLBACK_STATE_OPTION = 'arlo_rejected_callback_log_state';

	/** Option holding the throttle state for rejected callbacks that matched a pending import. Never autoloaded. */
	const REJECTED_MATCHED_CALLBACK_STATE_OPTION = 'arlo_rejected_matched_callback_log_state';

	/** Maximum rejected-callback rows written per throttle window, per bucket. */
	const REJECTED_CALLBACK_LOG_LIMIT = 5;

	/** Length, in seconds, of the rejected-callback throttle window. */
	const REJECTED_CALLBACK_WINDOW_SECONDS = 900;

	/** Cached support diagnostic verbosity for this request. */
	private static $diagnostic_verbosity = null;

	/** Correlation id for the current PHP request, generated once on first use. */
	private static $request_id = null;

	/**
	 * Write an error log row, then throw an exception carrying the same message.
	 *
	 * This is the throwing counterpart to log(); callers use it for failures that
	 * should leave an Arlo log entry before control moves to their exception path.
	 *
	 * @param string      $message         Message to store and to throw.
	 * @param int|null    $import_id       Import this entry belongs to, if any.
	 * @param string|null $timestamp       Explicit timestamp; defaults to now (UTC) if not a valid date string.
	 * @param bool        $successful      Stored success flag for this log row.
	 * @param string      $exception_class Fully-qualified class name to throw. Must be \Exception
	 *                                     or a subclass. Defaults to \Exception so existing
	 *                                     call sites keep their current exception type.
	 * @return void
	 * @throws \InvalidArgumentException If $exception_class is not \Exception or a subclass.
	 * @throws \Exception An instance of $exception_class, always.
	 */
	public static function log_error( $message, $import_id = null, $timestamp = null, $successful = false, $exception_class = \Exception::class ) {
		self::save_log( $message, $import_id, $timestamp, $successful, true, $exception_class );
	}

	/**
	 * Write a log entry.
	 *
	 * @param string      $message    Message to store.
	 * @param int|null    $import_id  Import this entry belongs to, if any.
	 * @param string|null $timestamp  Explicit timestamp; defaults to now (UTC) if not a valid date string.
	 * @param bool        $successful Stored success flag for this log row.
	 * @return void
	 */
	public static function log( $message, $import_id = null, $timestamp = null, $successful = false ) {
		self::save_log( $message, $import_id, $timestamp, $successful );
	}

	/**
	 * Write a support diagnostic entry when the requested verbosity is enabled.
	 *
	 * The verbosity check is intentionally hidden from callers. Pass a callable
	 * message builder for details that are expensive to assemble; it is evaluated
	 * only after the requested diagnostic verbosity is enabled for this request.
	 *
	 * @param int             $verbosity  Diagnostic verbosity required for this entry.
	 * @param string|callable $message    Message to store, or a no-arg callable returning the message.
	 * @param int|null        $import_id  Import this entry belongs to, if any.
	 * @param string|null     $timestamp  Explicit timestamp; defaults to now (UTC) if not a valid date string.
	 * @param bool            $successful Stored success flag for this log row.
	 * @return bool True when an entry was written, false when the requested verbosity is disabled.
	 */
	public static function log_diagnostic( $verbosity, $message, $import_id = null, $timestamp = null, $successful = false ): bool {
		if ( ! self::is_diagnostic_enabled( $verbosity ) ) {
			return false;
		}

		if ( ! is_string( $message ) && is_callable( $message ) ) {
			$message = $message();
		}

		self::save_log( (string) $message, $import_id, $timestamp, $successful, false, \Exception::class, $verbosity );
		return true;
	}

	/**
	 * Insert one log row.
	 *
	 * Expired-row cleanup is deliberately not performed here. Log writes happen
	 * inside retry and error paths; maintenance is run separately at lifecycle
	 * boundaries so a large log table cannot make every write expensive.
	 *
	 * @param string      $message         Message to store.
	 * @param int|null    $import_id       Import this entry belongs to, if any.
	 * @param string|null $timestamp       Explicit timestamp; defaults to now (UTC) if not a valid date string.
	 * @param bool        $successful      Stored success flag for this log row.
	 * @param bool        $raise_exception Whether to throw after writing the row.
	 * @param string      $exception_class Fully-qualified exception class name to throw when requested.
	 * @param int         $diagnostic_level Stored diagnostic level for this row.
	 * @return void
	 */
	private static function save_log( $message, $import_id = null, $timestamp = null, $successful = false, $raise_exception = false, $exception_class = \Exception::class, $diagnostic_level = self::DIAGNOSTIC_LEVEL_INFO ) {
		global $wpdb;
		$diagnostic_level = self::normalize_diagnostic_level( $diagnostic_level );
		$stored_message   = (string) $message;

		// Diagnostic (non-info) rows are prefixed with the per-request correlation id so a
		// single scheduler worker request can be reconstructed from its log lines. The `#`
		// sigil keeps the tag regex-distinct (`\[#[0-9a-f]{8}\]`) from the ordinary
		// `[key=value; ...]` detail blocks these messages already carry. Info rows are
		// ordinary operational output and are left unprefixed.
		if ( $diagnostic_level > self::DIAGNOSTIC_LEVEL_INFO ) {
			$stored_message = '[#' . self::get_request_id() . '] ' . $stored_message;
		}

		if ( ! is_string( $timestamp ) || strtotime( $timestamp ) === false ) {
			$now       = Utilities::get_now_utc();
			$timestamp = $now->format( "Y-m-d H:i:s" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching --  Direct database query is required for custom table. Cache is cleared after the insert operation
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}arlo_log
				(message, import_id, created, successful, diagnostic_level)
				VALUES ( %s, %d, %s, %d, %d )
				",
				$stored_message,
				$import_id,
				$timestamp,
				$successful,
				$diagnostic_level
			)
		);

		CacheControl::cache_delete( CacheControl::GROUP_LOG );
		if ( $raise_exception ) {
			self::raise_exception( $stored_message, $exception_class );
		}
	}

	/**
	 * Resolve a stored diagnostic level to its log label.
	 *
	 * @param int $level Stored diagnostic level.
	 * @return string Human-readable level label.
	 */
	public static function get_diagnostic_level_label( $level ): string {
		$parsed_level = self::parse_diagnostic_level( $level );

		switch ( $parsed_level ) {
			case self::DIAGNOSTIC_LEVEL_INFO:
				return 'Info';
			case self::DIAGNOSTIC_LEVEL_DEBUG:
				return 'Debug';
			case self::DIAGNOSTIC_LEVEL_TRACE:
				return 'Trace';
		}

		return is_int( $parsed_level ) ? 'Level ' . $parsed_level : 'Info';
	}

	/**
	 * Check whether the configured diagnostic verbosity includes a requested verbosity.
	 *
	 * @param int $verbosity Minimum diagnostic verbosity required.
	 * @return bool True when the configured verbosity is high enough.
	 */
	private static function is_diagnostic_enabled( $verbosity = self::DIAGNOSTIC_VERBOSITY_TRACE ): bool {
		$verbosity = self::normalize_diagnostic_level( $verbosity );
		return $verbosity > self::DIAGNOSTIC_VERBOSITY_OFF && self::get_diagnostic_verbosity() >= $verbosity;
	}

	/**
	 * Return a stable correlation id for the current PHP request.
	 *
	 * Generated once per request and cached so every lifecycle log line emitted
	 * during a single scheduler worker request can be joined by this id. Unlike
	 * the OS process id, it stays unique per request even when one PHP-FPM worker
	 * serves several sequential self-kick requests.
	 *
	 * @return string Short hex correlation id for this request.
	 */
	public static function get_request_id(): string {
		if ( is_null( self::$request_id ) ) {
			try {
				self::$request_id = bin2hex( random_bytes( 4 ) );
			} catch ( \Throwable $e ) {
				// The request id is only a diagnostic correlation token, not a security value,
				// so a non-CSPRNG fallback is acceptable when the system entropy source is
				// unavailable. This keeps logging itself from fatally failing on random_bytes().
				self::$request_id = substr( sha1( microtime( true ) . '|' . uniqid( '', true ) . '|' . wp_rand() ), 0, 8 );
			}
		}

		return self::$request_id;
	}

	/**
	 * Read and cache the configured diagnostic verbosity for this request.
	 *
	 * @return int Current diagnostic verbosity.
	 */
	private static function get_diagnostic_verbosity(): int {
		if ( ! is_null( self::$diagnostic_verbosity ) ) {
			return self::$diagnostic_verbosity;
		}

		$settings = get_option( 'arlo_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( array_key_exists( self::DIAGNOSTIC_VERBOSITY_SETTING_KEY, $settings ) ) {
			self::$diagnostic_verbosity = self::normalize_diagnostic_verbosity( $settings[ self::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] );
			return self::$diagnostic_verbosity;
		}

		self::$diagnostic_verbosity = self::DIAGNOSTIC_VERBOSITY_OFF;
		return self::$diagnostic_verbosity;
	}

	/**
	 * Normalize a submitted diagnostic verbosity value.
	 *
	 * @param mixed $value Submitted or stored verbosity value.
	 * @return int Supported diagnostic verbosity.
	 */
	private static function normalize_diagnostic_verbosity( $value ): int {
		return self::normalize_diagnostic_level( $value );
	}

	/**
	 * Return the supported stored diagnostic levels.
	 *
	 * These are the values persisted in `arlo_log.diagnostic_level`; the matching
	 * `DIAGNOSTIC_VERBOSITY_*` constants are for caller-facing logging settings.
	 *
	 * @return int[] Supported stored diagnostic levels.
	 */
	public static function get_supported_diagnostic_levels(): array {
		return [
			self::DIAGNOSTIC_LEVEL_INFO,
			self::DIAGNOSTIC_LEVEL_DEBUG,
			self::DIAGNOSTIC_LEVEL_TRACE,
		];
	}

	/**
	 * Check whether a submitted value is an explicit diagnostic level.
	 *
	 * @param mixed $value Submitted setting, filter, or stored row value.
	 * @return bool True when the value maps exactly to a supported diagnostic level.
	 */
	public static function is_supported_diagnostic_level( $value ): bool {
		$parsed_value = self::parse_diagnostic_level( $value );
		return ! is_null( $parsed_value ) && in_array( $parsed_value, self::get_supported_diagnostic_levels(), true );
	}

	/**
	 * Normalize a diagnostic level value for settings, writes, and row reads.
	 *
	 * Invalid or missing values become `DIAGNOSTIC_LEVEL_INFO`, matching the
	 * non-diagnostic log row default.
	 *
	 * @param mixed $value Submitted setting, filter, or stored row value.
	 * @return int Supported diagnostic level.
	 */
	public static function normalize_diagnostic_level( $value ): int {
		$parsed_value = self::parse_diagnostic_level( $value );
		return ! is_null( $parsed_value ) && in_array( $parsed_value, self::get_supported_diagnostic_levels(), true ) ? $parsed_value : self::DIAGNOSTIC_LEVEL_INFO;
	}

	/**
	 * Parse a diagnostic level from supported scalar shapes.
	 *
	 * @param mixed $value Submitted setting, filter, or stored row value.
	 * @return int|null Parsed diagnostic level, or null for unsupported shapes.
	 */
	private static function parse_diagnostic_level( $value ): ?int {

		if ( is_string( $value ) ) {
			$value = trim( $value );
			$label = strtoupper( $value );

			switch ( $label ) {
				case 'INFO':
				case 'OFF':
					return self::DIAGNOSTIC_LEVEL_INFO;
				case 'DEBUG':
					return self::DIAGNOSTIC_LEVEL_DEBUG;
				case 'TRACE':
					return self::DIAGNOSTIC_LEVEL_TRACE;
			}

			if ( preg_match( '/^\d+$/', $value ) === 1 ) {
				$value = intval( $value );
			} else {
				return null;
			}
		} elseif ( ! is_int( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Throw a configured exception type carrying the logged message.
	 *
	 * @param string $message         Exception message.
	 * @param string $exception_class Fully-qualified exception class name. Must extend \Exception.
	 * @return void
	 * @throws \InvalidArgumentException If the requested class is not an exception class.
	 * @throws \Exception Always throws the requested exception type on success.
	 */
	private static function raise_exception( $message, $exception_class = \Exception::class ) {
		$exception_class = is_string( $exception_class ) ? ltrim( $exception_class, '\\' ) : '';
		if ( ! is_a( $exception_class, \Exception::class, true ) ) {
			throw new \InvalidArgumentException( 'Logger exception class must be Exception or an Exception subclass.' );
		}
		throw new $exception_class( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
	}

	/**
	 * Log a rejected import callback from a caller that has not matched a pending import.
	 *
	 * The import callback endpoint is unauthenticated, so these rejections are
	 * untrusted noise rather than import diagnostics. They are written only at
	 * debug verbosity and are throttled so a flood cannot grow the log table.
	 *
	 * Remarks:
	 * - When debug diagnostics are off, nothing is read or written.
	 * - Per window, up to `REJECTED_CALLBACK_LOG_LIMIT` rows are written, then a
	 *   single summary row, then nothing until the window ends. While over the
	 *   limit each call costs one option read and no writes.
	 * - The first rejection in each window also triggers one bounded maintenance
	 *   batch, so growth between imports is capped at any rejection rate without
	 *   a trim on every write.
	 * - A new import starting resets the window; see `reset_rejected_callback_throttle()`.
	 * - Concurrent requests can overshoot the limit by roughly their concurrency;
	 *   the cap is a diagnostics bound, not an exact quota.
	 *
	 * @param string $message Fixed rejection message; never request bytes.
	 * @return bool True when a row was written, false when disabled or throttled.
	 */
	public static function log_rejected_callback( $message ): bool {
		if ( ! self::is_diagnostic_enabled( self::DIAGNOSTIC_VERBOSITY_DEBUG ) ) {
			return false;
		}

		return self::write_throttled_rejection(
			self::REJECTED_CALLBACK_STATE_OPTION,
			(string) $message,
			'Further rejected import callbacks are not logged until the current %d-minute window ends or an import starts (limit: %d per window)',
			null,
			self::DIAGNOSTIC_LEVEL_DEBUG
		);
	}

	/**
	 * Log a callback whose request ID matched a real import but failed its nonce check.
	 *
	 * Unlike `log_rejected_callback()` this concerns a genuine import (a platform
	 * retry, a mismatch or a replay), so it is stored at info level and is visible
	 * at the default verbosity. It has its own throttle bucket, so junk from
	 * callers that matched nothing cannot use up its budget, and anyone who learns
	 * a pending request ID still cannot flood the table. Same limits, trim and
	 * reset behaviour as `log_rejected_callback()`.
	 *
	 * @param string   $message   Fixed rejection message; never request bytes.
	 * @param int|null $import_id Import whose request ID matched.
	 * @return bool True when a row was written, false when throttled.
	 */
	public static function log_rejected_matched_callback( $message, $import_id = null ): bool {
		return self::write_throttled_rejection(
			self::REJECTED_MATCHED_CALLBACK_STATE_OPTION,
			(string) $message,
			'Further rejected callbacks for pending imports are not logged until the current %d-minute window ends or an import starts (limit: %d per window)',
			is_null( $import_id ) ? null : (int) $import_id,
			self::DIAGNOSTIC_LEVEL_INFO
		);
	}

	/**
	 * Write a rejected-callback row under a per-bucket fixed-window budget.
	 *
	 * @param string   $state_option    Option holding this bucket's window state (never autoloaded).
	 * @param string   $message         Row to write while under the limit.
	 * @param string   $summary_format  sprintf format for the summary row; receives the window in minutes and the limit.
	 * @param int|null $import_id       Import the rows belong to, if known.
	 * @param int      $diagnostic_level Stored level for the rows.
	 * @return bool True when a row was written.
	 */
	private static function write_throttled_rejection( $state_option, $message, $summary_format, $import_id, $diagnostic_level ): bool {
		$now          = time();
		$state        = get_option( $state_option, array() );
		$window_start = ( is_array( $state ) && isset( $state['window_start'] ) && is_int( $state['window_start'] ) ) ? $state['window_start'] : 0;
		$logged       = ( is_array( $state ) && isset( $state['logged'] ) && is_int( $state['logged'] ) ) ? $state['logged'] : 0;

		if ( $window_start <= 0 || $window_start > $now || $now - $window_start >= self::REJECTED_CALLBACK_WINDOW_SECONDS ) {
			$window_start = $now;
			$logged       = 0;
		}

		if ( $logged > self::REJECTED_CALLBACK_LOG_LIMIT ) {
			return false;
		}

		// Record the claim before writing so concurrent requests see it as early as possible.
		update_option(
			$state_option,
			array(
				'window_start' => $window_start,
				'logged'       => $logged + 1,
			),
			false
		);

		if ( $logged < self::REJECTED_CALLBACK_LOG_LIMIT ) {
			self::save_log( $message, $import_id, null, false, false, \Exception::class, $diagnostic_level );
			if ( 0 === $logged ) {
				self::execute_log_maintenance( 1 );
			}
			return true;
		}

		self::save_log(
			sprintf(
				$summary_format,
				(int) ( self::REJECTED_CALLBACK_WINDOW_SECONDS / MINUTE_IN_SECONDS ),
				self::REJECTED_CALLBACK_LOG_LIMIT
			),
			$import_id,
			null,
			false,
			false,
			\Exception::class,
			$diagnostic_level
		);
		return true;
	}

	/**
	 * Start fresh rejected-callback throttle windows.
	 *
	 * Called when an import starts, so rejected callbacks around a legitimate
	 * import attempt are always diagnosable. Cost is two option deletes.
	 *
	 * @return void
	 */
	public static function reset_rejected_callback_throttle(): void {
		delete_option( self::REJECTED_CALLBACK_STATE_OPTION );
		delete_option( self::REJECTED_MATCHED_CALLBACK_STATE_OPTION );
	}

	/**
	 * Run bounded, best-effort diagnostic log maintenance.
	 *
	 * This method is safe to call from importer lifecycle boundaries: it keeps a
	 * fixed newest-row window using the primary key, bounds delete work, records
	 * incomplete or failed status for admins, and never lets maintenance exceptions
	 * escape into the importer.
	 *
	 * @param int $max_batches Maximum delete batches to run in this pass.
	 * @return array Structured maintenance status.
	 */
	public static function execute_log_maintenance( $max_batches = self::LOG_CLEANUP_MAX_BATCHES ): array {
		$started_at         = microtime( true );
		$max_batches        = max( 1, intval( $max_batches ) );
		$retention_max_rows = self::get_retention_max_rows();

		try {
			$status = self::delete_rows_outside_retention_window( $retention_max_rows, $max_batches, self::LOG_CLEANUP_MAX_SECONDS, $started_at );
			if ( $status['rows_deleted'] > 0 ) {
				CacheControl::cache_delete( CacheControl::GROUP_LOG );
			}
		} catch ( \Throwable $e ) {
			$status = self::build_log_maintenance_status(
				'failed',
				'exception',
				$started_at,
				array(
					'error' => $e->getMessage(),
				)
			);
		}

		self::log_diagnostic( self::DIAGNOSTIC_VERBOSITY_DEBUG, 'Log maintenance ran [retention_max_rows=' . $retention_max_rows . '; max_batches=' . $max_batches . '; max_seconds=' . self::LOG_CLEANUP_MAX_SECONDS . ']' );
		self::log_diagnostic(
			self::DIAGNOSTIC_VERBOSITY_DEBUG,
			'Log maintenance completed [status=' . $status['status'] . '; reason=' . ( $status['reason'] !== '' ? $status['reason'] : 'none' ) . '; rows_deleted=' . ( is_null( $status['rows_deleted'] ) ? 'unknown' : $status['rows_deleted'] ) . '; batches=' . $status['batches'] . '; backlog=' . ( ! empty( $status['has_backlog_remaining'] ) ? 'true' : 'false' ) . '; elapsed_seconds=' . $status['elapsed_seconds'] . ']'
		);

		self::try_persist_log_maintenance_status( $status );
		return $status;
	}

	/**
	 * Return the supported diagnostic log retention row limits.
	 *
	 * @return int[] Supported row limits.
	 */
	public static function get_supported_retention_max_rows(): array {
		return self::LOG_RETENTION_SUPPORTED_MAX_ROWS;
	}

	/**
	 * Check whether a submitted retention value is explicitly supported.
	 *
	 * @param mixed $value Submitted setting value.
	 * @return bool True when the value is one of the supported retention limits.
	 */
	public static function is_supported_retention_max_rows( $value ): bool {
		$parsed_value = self::parse_retention_max_rows( $value );
		return ! is_null( $parsed_value ) && in_array( $parsed_value, self::LOG_RETENTION_SUPPORTED_MAX_ROWS, true );
	}

	/**
	 * Normalize a diagnostic log retention row limit.
	 *
	 * @param mixed $value Submitted or stored setting value.
	 * @return int Supported row limit, defaulting to 10,000 for blank or invalid values.
	 */
	public static function normalize_retention_max_rows( $value ): int {
		$parsed_value = self::parse_retention_max_rows( $value );
		return ! is_null( $parsed_value ) && in_array( $parsed_value, self::LOG_RETENTION_SUPPORTED_MAX_ROWS, true ) ? $parsed_value : self::LOG_RETENTION_DEFAULT_MAX_ROWS;
	}

	/**
	 * Parse an integer retention row limit from supported scalar shapes.
	 *
	 * @param mixed $value Submitted or stored setting value.
	 * @return int|null Parsed integer value, or null for unsupported shapes.
	 */
	private static function parse_retention_max_rows( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value;
		} elseif ( is_string( $value ) && preg_match( '/^\d+$/', trim( $value ) ) === 1 ) {
			return intval( trim( $value ) );
		}

		return null;
	}

	/**
	 * Read the configured diagnostic log retention row limit.
	 *
	 * @return int Supported row limit.
	 */
	public static function get_retention_max_rows(): int {
		$settings = get_option( 'arlo_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		return self::normalize_retention_max_rows( $settings['log_retention_max_rows'] ?? null );
	}

	/**
	 * Apply maintenance for a new retention limit.
	 *
	 * This method applies maintenance side effects only: it runs bounded cleanup,
	 * or clears the table when an explicit retention change hits an extreme backlog
	 * or timeout pressure valve. Persisting the configured retention value remains
	 * separate to avoid recursive option updates.
	 *
	 * @param mixed $max_row_count Submitted or stored retention limit.
	 * @return array Structured maintenance status.
	 */
	public static function set_retention_max_rows( $max_row_count ): array {
		$started_at    = microtime( true );
		$max_row_count = self::normalize_retention_max_rows( $max_row_count );
		// Detect extreme backlogs before attempting delete loops that may time out on shared hosting.
		$exceeds_truncate_threshold = self::log_has_more_than_rows( self::LOG_RETENTION_TRUNCATE_THRESHOLD_ROWS );

		if ( is_null( $exceeds_truncate_threshold ) ) {
			$status = self::build_log_maintenance_status(
				'failed',
				'threshold_query_failed',
				$started_at,
				array(
					'target_retention_max_rows' => $max_row_count,
				)
			);
			self::try_persist_log_maintenance_status( $status );
			return $status;
		}

		if ( $exceeds_truncate_threshold ) {
			$status = self::truncate_log(
				'row_count_threshold_exceeded',
				$started_at,
				array(
					'target_retention_max_rows' => $max_row_count,
				)
			);
			self::try_persist_log_maintenance_status( $status );
			return $status;
		}

		try {
			$status = self::delete_rows_outside_retention_window( $max_row_count, self::LOG_RETENTION_CHANGE_MAX_BATCHES, self::LOG_RETENTION_CHANGE_MAX_SECONDS, $started_at );
		} catch ( \Throwable $e ) {
			$status = self::build_log_maintenance_status(
				'failed',
				'exception',
				$started_at,
				array(
					'error'                     => $e->getMessage(),
					'target_retention_max_rows' => $max_row_count,
				)
			);
		}

		if ( $status['status'] === 'partial' && $status['reason'] === 'time_budget_exceeded' ) {
			$status = self::truncate_log(
				'time_budget_exceeded',
				$started_at,
				array(
					'rows_deleted_before_truncate' => $status['rows_deleted'],
					'target_retention_max_rows'    => $max_row_count,
				)
			);
		}

		if ( is_int( $status['rows_deleted'] ) && $status['rows_deleted'] > 0 ) {
			CacheControl::cache_delete( CacheControl::GROUP_LOG );
		}

		self::try_persist_log_maintenance_status( $status );
		return $status;
	}

	/**
	 * Check whether the log table contains more than a fixed number of rows.
	 *
	 * The probe is exact but bounded by a primary-key ordered offset, avoiding an
	 * unbounded count for destructive cleanup decisions. Database metadata row
	 * estimates are deliberately not used here because stale estimates must never
	 * decide whether the log is cleared.
	 *
	 * @param int $row_limit Row threshold to test.
	 * @return bool|null True when more than `$row_limit` rows exist, false when not, null on query error.
	 */
	private static function log_has_more_than_rows( $row_limit ) {
		global $wpdb;

		$row_limit = max( 0, intval( $row_limit ) );
		$table     = $wpdb->prefix . 'arlo_log';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact bounded threshold probe is required before destructive log cleanup; no caching applies.
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d",
				$row_limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! empty( $wpdb->last_error ) ) {
			return null;
		}

		return ! is_null( $row );
	}

	/**
	 * Delete log rows older than the fixed newest-row retention window.
	 *
	 * The cutoff lookup and deletes are both ordered by the primary key. This keeps
	 * retention bounded without requiring a `created` index or running an age-based
	 * scan over the admin-searchable log table.
	 *
	 * @param int   $retention_max_rows Maximum rows to retain.
	 * @param int   $max_batches        Maximum number of batches to run.
	 * @param int   $max_seconds        Wall-clock ceiling for the maintenance pass.
	 * @param float $started_at         Maintenance start time from microtime(true).
	 * @return array Structured maintenance status.
	 */
	private static function delete_rows_outside_retention_window( $retention_max_rows, $max_batches, $max_seconds, $started_at ): array {
		global $wpdb;

		$retention_max_rows = self::normalize_retention_max_rows( $retention_max_rows );
		$max_batches        = max( 1, intval( $max_batches ) );
		$max_seconds        = max( 1, intval( $max_seconds ) );
		$table              = $wpdb->prefix . 'arlo_log';
		$rows_deleted       = 0;
		$batches            = 0;
		$last_deleted_count = null;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct primary-key cutoff lookup is required for log retention; no caching applies.
		$cutoff_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d",
				$retention_max_rows - 1
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! empty( $wpdb->last_error ) ) {
			return self::build_log_maintenance_status(
				'failed',
				'cutoff_query_failed',
				$started_at,
				array(
					'error' => $wpdb->last_error,
				)
			);
		}

		if ( is_null( $cutoff_id ) ) {
			return self::build_log_maintenance_status(
				'cleaned',
				'',
				$started_at,
				array(
					'rows_remaining'            => self::has_log_maintenance_time_remaining( $started_at, $max_seconds ) ? self::get_log_row_count() : null,
					'target_retention_max_rows' => $retention_max_rows,
				)
			);
		}

		$cutoff_id = intval( $cutoff_id );

		while ( $batches < $max_batches && ( microtime( true ) - $started_at ) < $max_seconds ) {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log table name is plugin-owned; all values are passed through $wpdb->prepare().
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct primary-key delete is required for log retention; no caching applies.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE id < %d ORDER BY id ASC LIMIT %d",
					$cutoff_id,
					self::LOG_CLEANUP_BATCH_LIMIT
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

			if ( $deleted === false ) {
				return self::build_log_maintenance_status(
					'failed',
					'query_failed',
					$started_at,
					array(
						'rows_deleted' => $rows_deleted,
						'batches'      => $batches,
						'cutoff_id'    => $cutoff_id,
						'error'        => $wpdb->last_error,
					)
				);
			}

			$deleted       = intval( $deleted );
			$rows_deleted += $deleted;
			++$batches;
			$last_deleted_count = $deleted;

			if ( $deleted < self::LOG_CLEANUP_BATCH_LIMIT ) {
				break;
			}
		}

		if ( ! self::has_log_maintenance_time_remaining( $started_at, $max_seconds ) ) {
			$known_clean = ! is_null( $last_deleted_count ) && $last_deleted_count < self::LOG_CLEANUP_BATCH_LIMIT;

			return self::build_log_maintenance_status(
				$known_clean ? 'cleaned' : 'partial',
				$known_clean ? '' : 'time_budget_exceeded',
				$started_at,
				array(
					'rows_deleted'              => $rows_deleted,
					'batches'                   => $batches,
					'cutoff_id'                 => $cutoff_id,
					'target_retention_max_rows' => $retention_max_rows,
					'has_backlog_remaining'     => ! $known_clean,
				)
			);
		}

		// A bounded pass may make progress without catching up; surface that for admin/support UX.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct primary-key existence check is required for log retention; no caching applies.
		$backlog_row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE id < %d ORDER BY id ASC LIMIT 1",
				$cutoff_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! empty( $wpdb->last_error ) ) {
			return self::build_log_maintenance_status(
				'failed',
				'backlog_check_failed',
				$started_at,
				array(
					'rows_deleted' => $rows_deleted,
					'batches'      => $batches,
					'cutoff_id'    => $cutoff_id,
					'error'        => $wpdb->last_error,
				)
			);
		}

		$status         = is_null( $backlog_row ) ? 'cleaned' : 'partial';
		$reason         = is_null( $backlog_row ) ? '' : 'retention_backlog';
		$rows_remaining = is_null( $backlog_row ) && self::has_log_maintenance_time_remaining( $started_at, $max_seconds ) ? self::get_log_row_count() : null;

		return self::build_log_maintenance_status(
			$status,
			$reason,
			$started_at,
			array(
				'rows_deleted'              => $rows_deleted,
				'rows_remaining'            => $rows_remaining,
				'batches'                   => $batches,
				'cutoff_id'                 => $cutoff_id,
				'target_retention_max_rows' => $retention_max_rows,
				'has_backlog_remaining'     => ! is_null( $backlog_row ),
			)
		);
	}

	/**
	 * Clear the diagnostic log table after an explicit retention change.
	 *
	 * @param string $reason     Machine-readable reason for clearing the log.
	 * @param float  $started_at Maintenance start time from microtime(true).
	 * @param array  $extra      Additional status fields.
	 * @return array Structured maintenance status.
	 */
	private static function truncate_log( $reason, $started_at, array $extra = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'arlo_log';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log table name is plugin-owned; no user input is interpolated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Destructive log clear is the release valve for explicit admin retention changes.
		$deleted = $wpdb->query( "TRUNCATE TABLE {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( $deleted === false ) {
			return self::build_log_maintenance_status(
				'failed',
				'truncate_failed',
				$started_at,
				array_merge(
					$extra,
					array(
						'error' => $wpdb->last_error,
					)
				)
			);
		}

		CacheControl::cache_delete( CacheControl::GROUP_LOG );

		return self::build_log_maintenance_status(
			'truncated',
			$reason,
			$started_at,
			array_merge(
				array(
					'rows_deleted'          => null,
					'rows_remaining'        => 0,
					'has_backlog_remaining' => false,
				),
				$extra
			)
		);
	}

	/**
	 * Check whether maintenance still has time budget left for support-only work.
	 *
	 * @param float $started_at Maintenance start time from microtime(true).
	 * @param int   $max_seconds Wall-clock ceiling for the maintenance pass.
	 * @return bool True when another optional query may run inside the budget.
	 */
	private static function has_log_maintenance_time_remaining( $started_at, $max_seconds ): bool {
		return ( microtime( true ) - $started_at ) < max( 1, intval( $max_seconds ) );
	}

	/**
	 * Count current log rows for maintenance status reporting.
	 *
	 * This is intentionally not used as a cleanup preflight. It runs only after
	 * primary-key bounded maintenance, where an exact admin/support status is more
	 * useful than an estimate.
	 *
	 * @return int|null Current row count, or null if the database reports an error.
	 */
	public static function get_log_row_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'arlo_log';
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log table name is plugin-owned; no user input is interpolated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct count is used only for post-cleanup support status; no caching applies.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return empty( $wpdb->last_error ) ? intval( $count ) : null;
	}

	/**
	 * Persist maintenance status when admin/support visibility is needed.
	 *
	 * @param array $status Structured maintenance status.
	 * @return void
	 */
	private static function persist_log_maintenance_status( array $status ): void {
		if ( in_array( $status['status'], array( 'cleaned', 'truncated' ), true ) ) {
			self::clear_log_maintenance_status();
			return;
		}

		update_option( self::LOG_CLEANUP_STATUS_OPTION, $status, false );
	}

	/**
	 * Persist maintenance status without letting status storage affect import flow.
	 *
	 * @param array $status Structured maintenance status, updated with persistence
	 *                      error details if the option write/delete fails unexpectedly.
	 * @return void
	 */
	private static function try_persist_log_maintenance_status( array &$status ): void {
		try {
			self::persist_log_maintenance_status( $status );
		} catch ( \Throwable $e ) {
			$status['status_persisted'] = false;
			$status['persist_error']    = $e->getMessage();
		}
	}

	/**
	 * Clear any previously persisted maintenance status.
	 *
	 * @return void
	 */
	private static function clear_log_maintenance_status(): void {
		delete_option( self::LOG_CLEANUP_STATUS_OPTION );
	}

	/**
	 * Build a normalised maintenance status payload.
	 *
	 * @param string $status     High-level result (`cleaned`, `partial`, `failed`, or `truncated`).
	 * @param string $reason     Machine-readable detail for non-clean statuses.
	 * @param float  $started_at Maintenance start time from microtime(true).
	 * @param array  $extra      Additional status fields.
	 * @return array Structured maintenance status.
	 */
	private static function build_log_maintenance_status( $status, $reason, $started_at, array $extra = array() ): array {
		return array_merge(
			array(
				'status'                    => $status,
				'reason'                    => $reason,
				'rows_deleted'              => 0,
				'rows_remaining'            => null,
				'batches'                   => 0,
				'cutoff_id'                 => null,
				'target_retention_max_rows' => self::LOG_RETENTION_DEFAULT_MAX_ROWS,
				'has_backlog_remaining'     => false,
				'elapsed_seconds'           => round( microtime( true ) - $started_at, 6 ),
				'created_at'                => gmdate( 'Y-m-d H:i:s' ),
			),
			$extra
		);
	}

	/**
	 * Read recent log rows, optionally filtered by success and diagnostic level.
	 *
	 * The first argument is an options array.
	 *
	 * Supported filter options:
	 * - `successful`: bool|null Include only successful or unsuccessful rows.
	 * - `max_diagnostic_level`: int|null Include rows at or below this diagnostic level.
	 *
	 * Callers must pass a positive `$limit` to request a specific cap. When omitted
	 * or invalid, the read is limited to `LOG_ROWS_DEFAULT_LIMIT` rows.
	 *
	 * @param array|null $filter_options Filter options.
	 * @param int|null        $limit          Maximum number of rows to return; defaults to LOG_ROWS_DEFAULT_LIMIT.
	 * @return array<int,array<string,mixed>> Log rows ordered newest first.
	 */
	public static function get_log_rows( $filter_options = array(), $limit = null ) {
		return self::query_log_rows(
			is_array( $filter_options ) ? $filter_options : array(),
			$limit,
			array( 'message', 'created', 'successful', 'diagnostic_level' ),
			true,
			false
		);
	}

	/**
	 * Stream the import log as a CSV file download.
	 *
	 * Sends appropriate HTTP headers and outputs the full log as a UTF-8 CSV
	 * file, then terminates the request. Each field is sanitized against formula
	 * injection before being written. Returns without output if the CSV could
	 * not be generated.
	 *
	 * @return void
	 */
	public static function render_log_csv_attachment() {
		try {
			$csv = self::build_log_csv_string( 0 );
		} catch ( \RuntimeException $e ) {
			return;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="arlo_sync_log.csv"' );
		header( 'Content-Length: ' . strlen( $csv ) );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Diagnostic log CSV download. Each field is sanitized against formula injection before fputcsv() at write time; not HTML output.
		exit;
	}

	/**
	 * Build the import log as a CSV-formatted string.
	 *
	 * Queries the log table, writes rows to a temporary stream (php://temp,
	 * backed by memory up to 2 MB then spilled to a temp file) via fputcsv(),
	 * and returns the result as a string. Each field is sanitized
	 * against formula injection before being written. Throws if the temporary
	 * stream cannot be opened, the query fails, or the stream cannot be read.
	 *
	 * @param int $limit Maximum number of rows to include, ordered by id DESC.
	 *                   Pass 0 for no limit.
	 * @return string CSV-formatted log contents.
	 * @throws \RuntimeException If the temporary stream cannot be opened, the query fails, or the stream cannot be read.
	 */
	public static function build_log_csv_string( $limit = 1000 ) {
		$limit = is_numeric( $limit ) ? max( 0, intval( $limit ) ) : 1000;

		$fp = fopen( 'php://temp/maxmemory:2097125', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp is an in-memory stream; WP_Filesystem does not support in-memory streams.
		if ( $fp === false ) {
			self::log( 'Couldn\'t create log CSV' );
			throw new \RuntimeException( 'Couldn\'t create log CSV' );
		}

		$entries = self::query_log_rows(
			array(),
			$limit,
			array( 'id', 'created', 'successful', 'diagnostic_level', 'import_id', 'message' ),
			false,
			true
		);

		if ( is_array( $entries ) && count( $entries ) ) {
			fputcsv( $fp, array( 'id', 'created_date_utc', 'is_success', 'diagnostic_level', 'import_id', 'message' ), ',', '"', '\\' );

			foreach ( $entries as $entry ) {
				$row = array(
					$entry['id'],
					$entry['created'],
					$entry['successful'],
					$entry['diagnostic_level'],
					$entry['import_id'],
					$entry['message'],
				);
				$row = array_map( [ self::class, 'sanitize_csv_field_value' ], $row );
				fputcsv( $fp, $row, ',', '"', '\\' );
			}
		}

		rewind( $fp );
		$csv = stream_get_contents( $fp );
		if ( false === $csv ) {
			fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes a php://temp in-memory stream; WP_Filesystem does not support in-memory streams.
			self::log( 'Could not read log CSV stream.' );
			throw new \RuntimeException( 'Could not read log CSV stream.' );
		}
		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes a php://temp in-memory stream; WP_Filesystem does not support in-memory streams.

		return $csv;
	}

	/**
	 * Sanitize a single CSV field value against formula injection.
	 *
	 * Spreadsheet applications (Excel, Google Sheets) interpret cell values that
	 * begin with '=', '+', '-', or '@' as formulas, and '\t' / '\r' as potential
	 * triggers in some parsers. A leading single-quote forces the cell to be
	 * treated as literal text rather than a formula — it acts as a text-prefix
	 * marker recognised by those applications and is not displayed in the cell.
	 *
	 * @param mixed $field The field value to sanitize.
	 * @return mixed The original value, or the value prefixed with "'" if it begins
	 *               with a formula-injection character.
	 */
	private static function sanitize_csv_field_value( $field ) {
		if ( is_string( $field ) && strlen( $field ) > 0 && in_array( $field[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			return "'" . $field;
		}
		return $field;
	}

	/**
	 * Query log rows for bounded UI reads and direct CSV export.
	 *
	 * @param array    $filter_options Filter options.
	 * @param int|null $limit          Maximum number of rows to return.
	 * @param string[] $columns        Trusted column names to select.
	 * @param bool     $use_cache      Whether to read through CacheControl.
	 * @param bool     $allow_unbounded Whether limit 0 means no LIMIT clause.
	 * @return array<int,array<string,mixed>> Log rows ordered newest first.
	 * @throws \RuntimeException If a direct, uncached database query fails.
	 */
	private static function query_log_rows( array $filter_options, $limit, array $columns, $use_cache, $allow_unbounded ): array {
		global $wpdb;

		$successful = array_key_exists( 'successful', $filter_options ) && is_bool( $filter_options['successful'] ) ? $filter_options['successful'] : null;
		$max_level  = array_key_exists( 'max_diagnostic_level', $filter_options ) ? self::normalize_diagnostic_level( $filter_options['max_diagnostic_level'] ) : null;
		$limit      = self::normalize_log_rows_limit( $limit, $allow_unbounded );
		$select_sql = implode( ",\n\t\t\t", $columns );

		$parameters = [];
		$where      = [];

		if ( ! is_null( $successful ) ) {
			$where[]      = "successful = %d";
			$parameters[] = ( $successful ? 1 : 0 );
		}

		if ( ! is_null( $max_level ) ) {
			$where[]      = "diagnostic_level <= %d";
			$parameters[] = $max_level;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$limit_sql = '';
		if ( $limit !== 0 ) {
			$limit_sql    = 'LIMIT %d';
			$parameters[] = $limit;
		}

		$sql = "
		SELECT
			$select_sql
		FROM
			{$wpdb->prefix}arlo_log
			$where_sql
		ORDER BY
			id DESC
		$limit_sql
		";

		if ( ! empty( $parameters ) ) {
			$sql = Utilities::prepare_sql( $sql, $parameters );
		}

		if ( $use_cache ) {
			$items = CacheControl::fetch_results( $sql, ARRAY_A, CacheControl::GROUP_LOG );
			return is_array( $items ) ? $items : array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL preparation is handled above. CSV export requires a direct query so SQL failures are not masked or cached.
		$items = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) {
			self::log( $wpdb->last_error );
			throw new \RuntimeException( 'Could not query log entries.' );
		}

		return is_array( $items ) ? $items : array();
	}

	/**
	 * Normalize a log-row read limit.
	 *
	 * @param mixed $limit Submitted limit.
	 * @param bool  $allow_unbounded Whether 0 means no LIMIT clause.
	 * @return int Normalized row limit, or 0 for an allowed unbounded read.
	 */
	private static function normalize_log_rows_limit( $limit, $allow_unbounded ): int {
		if ( ! is_numeric( $limit ) ) {
			return self::LOG_ROWS_DEFAULT_LIMIT;
		}

		$limit = intval( $limit );
		if ( $allow_unbounded && $limit === 0 ) {
			return 0;
		}

		return max( 1, $limit );
	}
}
