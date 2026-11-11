<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Token-scoped, database-backed mutex for a single import scheduler kick.
 *
 * The lock is intentionally not held for an entire import lifecycle. Each scheduler
 * request acquires the fixed row before reading task state, writes checkpoints only
 * while it still owns the token, and releases when that request finishes.
 */
class ImportMutex {
	/** Fixed primary-key value used as the atomic mutex row. */
	const LOCK_ROW_ID = 1;

	/** Lease length in seconds; longer than one import request limit, shorter than cron safety-net cadence. */
	const TTL_SECONDS = 120;

	const ACQUIRED     = 'acquired';
	const ALREADY_HELD = 'already_held';
	const DB_ERROR     = 'db_error';
	const DB_RETRYABLE = 'db_retryable';

	private $wpdb;
	private $table;
	private $token;
	private $held = false;

	/**
	 * Creates a mutex instance with a fresh owner token for this request only.
	 *
	 * @param \wpdb $wpdb WordPress database connection.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'arlo_import_lock';
		$this->token = bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Attempts to acquire the lock row for this request.
	 *
	 * @return string One of the ImportMutex result constants.
	 */
	public function acquire(): string {
		// Expired rows are removed first so an abandoned kick does not block the next request forever.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct lock-table write is required for the import mutex; no caching applies.
		$deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE lock_row_id = %d AND lock_expired <= UTC_TIMESTAMP()",
				self::LOCK_ROW_ID
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $deleted === false ) {
			return $this->classify_database_error( false );
		}

		$previous_suppress_errors = $this->set_suppress_errors( true );

		try {
			// The fixed-row INSERT is the atomic compare-and-set; duplicate-key means another owner won.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock table name is plugin-owned; all values are passed through $wpdb->prepare().
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic INSERT into the lock table is the mutex primitive; no caching applies.
			$inserted = $this->wpdb->query(
				$this->wpdb->prepare(
					"INSERT INTO {$this->table} (lock_row_id, lock_token, lock_acquired, lock_expired) VALUES (%d, %s, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND))",
					self::LOCK_ROW_ID,
					$this->token,
					self::TTL_SECONDS
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} finally {
			$this->set_suppress_errors( $previous_suppress_errors );
		}

		if ( $inserted === 1 ) {
			$this->held = true;
			return self::ACQUIRED;
		}

		return $this->classify_database_error( true );
	}

	/**
	 * Verifies that this request still owns the unexpired lock row.
	 *
	 * @return bool True when the token still matches the live lock row.
	 */
	public function confirm_ownership(): bool {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct lock-table read is required for token ownership; no caching applies.
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(1) FROM {$this->table} WHERE lock_row_id = %d AND lock_token = %s AND lock_expired > UTC_TIMESTAMP()",
				self::LOCK_ROW_ID,
				$this->token
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return intval( $count ) === 1;
	}

	/**
	 * Extends this owner's lease if the token still owns the lock row.
	 *
	 * @param int $ttl_seconds Number of seconds to extend from the current DB time.
	 * @return bool True when ownership is retained, including same-second no-op updates.
	 */
	public function extend_ownership( int $ttl_seconds = self::TTL_SECONDS ): bool {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct lock-table write is required for owner-only lease extension; no caching applies.
		$updated = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table} SET lock_expired = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND) WHERE lock_row_id = %d AND lock_token = %s AND lock_expired > UTC_TIMESTAMP()",
				$ttl_seconds,
				self::LOCK_ROW_ID,
				$this->token
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $updated === false ) {
			return false;
		}

		if ( intval( $updated ) === 0 ) {
			// MySQL reports 0 affected rows if DATE_ADD(UTC_TIMESTAMP()) resolves to the same DATETIME value.
			return $this->confirm_ownership();
		}

		return true;
	}

	/**
	 * Stores the import id on the lock row for diagnostics while this request owns it.
	 *
	 * @param int $import_id Import id currently being processed.
	 * @return void
	 */
	public function set_import_id( int $import_id ): void {
		if ( ! $this->held ) {
			return;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct lock-table write updates diagnostic lock metadata for the current owner; no caching applies.
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table} SET import_id = %d WHERE lock_row_id = %d AND lock_token = %s",
				$import_id,
				self::LOCK_ROW_ID,
				$this->token
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns this request's private lock token.
	 *
	 * @return string Hex token used for ownership checks.
	 */
	public function get_token(): string {
		return $this->token;
	}

	/**
	 * Releases the lock row only if this request still owns it.
	 *
	 * @return bool True when this call deleted the owned lock row.
	 */
	public function release(): bool {
		if ( ! $this->held ) {
			return false;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct lock-table write is required to release the import mutex; no caching applies.
		$deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE lock_row_id = %d AND lock_token = %s",
				self::LOCK_ROW_ID,
				$this->token
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $deleted === false ) {
			return false;
		}

		$this->held = false;
		return intval( $deleted ) === 1;
	}

	/**
	 * Clears the lock table without checking ownership.
	 *
	 * Reserved for schema repair/admin recovery paths. Normal cancellation is cooperative
	 * and should mark the task terminal rather than deleting another request's live lock.
	 *
	 * @return void
	 */
	public function force_release(): void {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Emergency lock-table repair requires a direct delete; no caching applies.
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE lock_row_id = %d",
				self::LOCK_ROW_ID
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->held = false;
	}

	/**
	 * Checks whether any request currently owns an unexpired lock row.
	 *
	 * @return bool True when the fixed lock row exists and has not expired.
	 */
	public function is_held_by_anyone(): bool {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lock table name is plugin-owned; all values are passed through $wpdb->prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct lock-table read is required for a mutex status check; no caching applies.
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(1) FROM {$this->table} WHERE lock_row_id = %d AND lock_expired > UTC_TIMESTAMP()",
				self::LOCK_ROW_ID
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return intval( $count ) === 1;
	}

	/**
	 * Best-effort release if the object is destroyed before the normal finally block runs.
	 */
	public function __destruct() {
		$this->release();
	}

	/**
	 * Temporarily toggles wpdb error suppression when the expected duplicate-key path is noisy.
	 *
	 * @param bool $suppress Whether errors should be suppressed.
	 * @return bool Previous suppression value when available.
	 */
	private function set_suppress_errors( bool $suppress ): bool {
		if ( ! method_exists( $this->wpdb, 'suppress_errors' ) ) {
			return false;
		}

		return (bool) $this->wpdb->suppress_errors( $suppress );
	}

	/**
	 * Classifies a failed lock-table operation into scheduler control-flow outcomes.
	 *
	 * @param bool $duplicate_key_is_held Whether duplicate-key means a valid competing lock.
	 * @return string One of the ImportMutex result constants.
	 */
	private function classify_database_error( bool $duplicate_key_is_held ): string {
		$errno = $this->get_database_errno();

		if ( $duplicate_key_is_held && $errno === 1062 ) {
			return self::ALREADY_HELD;
		}

		if ( in_array( $errno, [ 1205, 1213 ], true ) || ( $errno >= 2000 && $errno < 2100 ) ) {
			return self::DB_RETRYABLE;
		}

		if ( in_array( $errno, [ 1054, 1072, 1146 ], true ) ) {
			return self::DB_ERROR;
		}

		$error = strtolower( (string) $this->wpdb->last_error );
		if ( $duplicate_key_is_held && strpos( $error, 'duplicate entry' ) !== false ) {
			return self::ALREADY_HELD;
		}

		if ( strpos( $error, 'deadlock' ) !== false
			|| strpos( $error, 'lock wait timeout' ) !== false
			|| strpos( $error, 'server has gone away' ) !== false
			|| strpos( $error, 'lost connection' ) !== false ) {
			return self::DB_RETRYABLE;
		}

		if ( strpos( $error, "doesn't exist" ) !== false
			|| strpos( $error, 'unknown column' ) !== false
			|| strpos( $error, 'key column' ) !== false ) {
			return self::DB_ERROR;
		}

		return self::DB_RETRYABLE;
	}

	/**
	 * Returns the numeric MySQL errno when the active wpdb handle exposes mysqli.
	 *
	 * @return int MySQL errno, or 0 when unavailable.
	 */
	private function get_database_errno(): int {
		if ( isset( $this->wpdb->dbh ) && $this->wpdb->dbh instanceof \mysqli ) {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_errno -- Numeric MySQL errno is required to distinguish duplicate-key lock contention from transient database errors and schema faults.
			return mysqli_errno( $this->wpdb->dbh );
		}

		return 0;
	}
}
