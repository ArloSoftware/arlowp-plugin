<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Stores complete full-shadow baseline candidates and the current comparison baseline.
 */
class IncrementalBaselineRepository {
	const STATUS_CLEAN         = 'clean';
	const STATUS_FULL_REQUIRED = 'full_required';
	const STATUS_INVALID       = 'invalid';

	/** @var \wpdb */
	private $wpdb;

	/**
	 * Creates a repository using the provided or global WordPress database connection.
	 *
	 * @param \wpdb|null $wpdb WordPress database connection.
	 */
	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb !== null ? $wpdb : $GLOBALS['wpdb'];
	}

	/**
	 * Stages one complete, validated manifest as a candidate owned by a full-shadow import.
	 *
	 * @param int                     $import_id Owning full-shadow import id.
	 * @param SnapshotManifestRequest $request Admitted package metadata.
	 * @param SnapshotManifest        $manifest Complete validated manifest.
	 * @param string                  $image_content Snapshot image JSON content.
	 * @param string                  $configuration_signature Caller-owned import compatibility signature.
	 * @return bool True when staging is durable or was already staged.
	 * @throws \ArloTraining\SnapshotProcessException When the image fragment elements are invalid or incompatible with the manifest.
	 * @throws \RuntimeException When candidate persistence fails.
	 */
	public function stage_full_shadow_candidate( int $import_id, SnapshotManifestRequest $request, SnapshotManifest $manifest, string $image_content, string $configuration_signature ): bool {
		$this->validate_image_fragment_elements( $image_content, $manifest );

		return $this->run_in_transaction(
			function ( IncrementalBaselineRepository $repository ) use ( $import_id, $request, $manifest, $image_content, $configuration_signature ) {
				if ( ! $repository->prepare_for_staging( $import_id ) ) {
					return false;
				}

				$existing = $repository->wpdb->get_var( $repository->wpdb->prepare( 'SELECT candidate_id FROM %i WHERE import_id = %d FOR UPDATE', $repository->candidate_table(), $import_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Candidate staging requires a transaction-local idempotency check against a plugin-owned table.
				if ( ! empty( $existing ) ) {
					return true;
				}

				if ( false === $repository->wpdb->insert(
					$repository->candidate_table(),
					[
						'import_id'               => $import_id,
						'baseline_generation_id'  => 0,
						'schema_level'            => $manifest->schema_level,
						'configuration_signature' => $configuration_signature,
						'source_snapshot_id'      => $request->snapshot_id,
						'snapshot_package_digest' => hash( 'sha256', $image_content, true ),
						'manifest_count'          => count( $manifest->entries ),
						'census_tag'              => $manifest->census_tag,
					]
				) ) {
					throw new \RuntimeException( 'Could not stage incremental baseline candidate.' );
				}

				$generation_id = (int) $repository->wpdb->insert_id;
				if ( $generation_id <= 0 || false === $repository->wpdb->update( $repository->candidate_table(), [ 'baseline_generation_id' => $generation_id ], [ 'candidate_id' => $generation_id ] ) ) {
					throw new \RuntimeException( 'Could not allocate incremental baseline generation.' );
				}

				foreach ( $manifest->entries as $entry ) {
					if ( false === $repository->wpdb->insert(
						$repository->baseline_table(),
						[
							'baseline_generation_id' => $generation_id,
							'baseline_owner_kind'    => 'full_shadow_import',
							'baseline_owner_id'      => $import_id,
							'entity_kind'            => $entry->kind,
							'entity_key_1'           => $entry->key[0],
							'entity_key_2'           => $entry->key[1] ?? '',
							'entity_region_code'     => $entry->region,
							'structural_tag'         => $entry->structural_tag,
							'availability_tag'       => $entry->availability_tag,
							'source_snapshot_id'     => $request->snapshot_id,
						]
					) ) {
						throw new \RuntimeException( 'Could not stage incremental baseline entry.' );
					}
				}

				return true;
			}
		);
	}

	/**
	 * Promotes a proven candidate generation to the current clean baseline.
	 *
	 * @param object $candidate Proven candidate row to make current.
	 * @return bool True when the baseline state was persisted and the transient candidate was deleted.
	 * @throws \RuntimeException When baseline persistence fails.
	 */
	public function promote_candidate_as_baseline( $candidate ): bool {
		$this->ensure_baseline_state_row();
		$this->acquire_baseline_state_row_lock();
		$previous_generation_id = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT current_baseline_generation_id FROM %i WHERE baseline_state_id = %d', $this->baseline_state_table(), 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The singleton baseline row is already locked for this promotion transaction.
		$this->throw_if_database_error();
		$updated = $this->wpdb->update(
			$this->baseline_state_table(),
			[
				'current_baseline_generation_id' => $candidate->baseline_generation_id,
				'schema_level'                   => $candidate->schema_level,
				'configuration_signature'        => $candidate->configuration_signature,
				'projection_count'               => $candidate->manifest_count,
				'census_tag'                     => $candidate->census_tag,
				'baseline_status'                => self::STATUS_CLEAN,
				'modified'                       => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'baseline_state_id' => 1 ]
		);

		if ( false === $updated ) {
			$this->throw_database_error( 'Could not promote incremental baseline candidate.' );
		}
		if ( $previous_generation_id > 0 && $previous_generation_id !== (int) $candidate->baseline_generation_id && false === $this->wpdb->delete( $this->baseline_table(), [ 'baseline_generation_id' => $previous_generation_id ] ) ) {
			$this->throw_database_error( 'Could not remove the previous incremental baseline generation.' );
		}

		if ( false === $this->wpdb->delete( $this->candidate_table(), [ 'candidate_id' => $candidate->candidate_id ] ) ) {
			$this->throw_database_error( 'Could not remove promoted incremental baseline candidate.' );
		}

		return true;
	}

	/**
	 * Counts the staged baseline entries in a candidate generation.
	 *
	 * @param int $generation_id Candidate baseline generation id.
	 * @return int Number of staged baseline rows in the generation.
	 * @throws \RuntimeException When the baseline count cannot be read.
	 */
	public function get_baseline_generation_entry_count( int $generation_id ): int {
		$count = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE baseline_generation_id = %d', $this->baseline_table(), $generation_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Finish verifies the staged row count against a plugin-owned table before promotion.
		$this->throw_if_database_error();

		return $count;
	}

	/**
	 * Locks and verifies the tainted baseline generation before delta promotion.
	 *
	 * @param int $expected_generation_id Baseline generation tainted by the first delta write.
	 * @return bool True when the locked state is full-required and references the expected generation; false when the state row is absent or either value differs.
	 * @throws \RuntimeException When the singleton state cannot be read.
	 */
	public function lock_and_verify_full_required_baseline_generation( int $expected_generation_id ): bool {
		$baseline = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT current_baseline_generation_id, baseline_status FROM %i WHERE baseline_state_id = %d FOR UPDATE', $this->baseline_state_table(), 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delta promotion locks the singleton state before proving its first-write taint is unchanged.
		$this->throw_if_database_error();

		return is_object( $baseline )
			&& self::STATUS_FULL_REQUIRED === $baseline->baseline_status
			&& $expected_generation_id === (int) $baseline->current_baseline_generation_id;
	}

	/**
	 * Returns whether this full-shadow import already owns the current clean baseline.
	 *
	 * @param int $import_id Full-shadow import id to check.
	 * @return bool True when this import's generation is already current and clean.
	 * @throws \RuntimeException When the baseline ownership cannot be read.
	 */
	public function is_current_clean_baseline_for_import( int $import_id ): bool {
		$is_current = 1 === (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT 1 FROM %i AS baseline_state INNER JOIN %i AS baseline ON baseline.baseline_generation_id = baseline_state.current_baseline_generation_id WHERE baseline_state.baseline_state_id = %d AND baseline_state.baseline_status = %s AND baseline.baseline_owner_kind = %s AND baseline.baseline_owner_id = %d LIMIT 1', $this->baseline_state_table(), $this->baseline_table(), 1, self::STATUS_CLEAN, 'full_shadow_import', $import_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Finish recognizes a completed promotion when its separate scheduler checkpoint was not persisted.
		$this->throw_if_database_error();

		return $is_current;
	}

	/**
	 * Deletes a transient candidate and its staged baseline generation.
	 *
	 * @param int $candidate_id Candidate row id.
	 * @param int $baseline_generation_id Staged baseline generation id.
	 * @return bool True when the candidate and its staged rows were deleted.
	 * @throws \RuntimeException When candidate cleanup fails.
	 */
	public function remove_candidate_and_generation( int $candidate_id, int $baseline_generation_id ): bool {
		if ( false === $this->wpdb->delete( $this->baseline_table(), [ 'baseline_generation_id' => $baseline_generation_id ] ) ) {
			$this->throw_database_error( 'Could not remove incremental baseline generation.' );
		}

		if ( false === $this->wpdb->delete( $this->candidate_table(), [ 'candidate_id' => $candidate_id ] ) ) {
			$this->throw_database_error( 'Could not remove incremental baseline candidate.' );
		}

		return true;
	}

	/**
	 * Invalidates the current baseline while retaining prior staged generations.
	 *
	 * @return bool True when the baseline state was invalidated.
	 * @throws \RuntimeException When baseline invalidation fails.
	 */
	public function mark_baseline_invalid(): bool {
		$this->ensure_baseline_state_row();
		$this->acquire_baseline_state_row_lock();

		if ( false === $this->wpdb->update(
			$this->baseline_state_table(),
			[
				'current_baseline_generation_id' => null,
				'schema_level'                   => null,
				'configuration_signature'        => null,
				'projection_count'               => 0,
				'census_tag'                     => null,
				'baseline_status'                => self::STATUS_INVALID,
				'modified'                       => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'baseline_state_id' => 1 ]
		) ) {
			$this->throw_database_error( 'Could not invalidate incremental baseline.' );
		}

		return true;
	}

	/**
	 * Validates the image fragment elements against the manifest entries.
	 *
	 * @param string           $image_content Snapshot image JSON content.
	 * @param SnapshotManifest $manifest Complete validated manifest.
	 * @return void
	 * @throws \ArloTraining\SnapshotProcessException When the image fragment elements are invalid or incompatible with the manifest.
	 */
	private function validate_image_fragment_elements( string $image_content, SnapshotManifest $manifest ): void {
		$elements = SnapshotImageSpec::from_content( $image_content )->get_fragment_elements();
		foreach ( $manifest->entries as $entry ) {
			if ( $entry->fragment_element_index !== null && ! array_key_exists( $entry->fragment_element_index, $elements ) ) {
				throw new \ArloTraining\SnapshotProcessException( 'Snapshot image fragment elements are incompatible with the manifest.' );
			}
		}
	}

	/**
	 * Creates the singleton baseline-state row when it does not yet exist.
	 *
	 * @return void
	 * @throws \RuntimeException When the singleton state cannot be created.
	 */
	private function ensure_baseline_state_row(): void {
		if ( false === $this->wpdb->query( $this->wpdb->prepare( "INSERT IGNORE INTO %i (baseline_state_id, projection_count, baseline_status, modified) VALUES (1, 0, %s, %s)", $this->baseline_state_table(), self::STATUS_INVALID, gmdate( 'Y-m-d H:i:s' ) ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The singleton baseline-state row must exist before promotion can lock and update it.
			$this->throw_database_error( 'Could not create incremental baseline state.' );
		}
	}

	/**
	 * Retrieves and locks the transient candidate owned by a full-shadow import.
	 *
	 * A null result means no candidate exists for the import; it does not indicate
	 * that the row lock failed.
	 *
	 * @param int $import_id Full-shadow import id that owns the candidate.
	 * @return object|null Candidate row locked for the surrounding transaction.
	 * @throws \RuntimeException When the candidate cannot be read.
	 */
	public function get_locked_candidate_for_import( int $import_id ) {
		$candidate = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE import_id = %d FOR UPDATE', $this->candidate_table(), $import_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Finish locks the candidate while deciding its durable lifecycle state.
		$this->throw_if_database_error();

		return $candidate;
	}

	/**
	 * Acquires an exclusive lock on the singleton baseline-state row.
	 *
	 * @return void
	 * @throws \RuntimeException When the singleton state row cannot be locked.
	 */
	private function acquire_baseline_state_row_lock(): void {
		$locked = 1 === (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT baseline_state_id FROM %i WHERE baseline_state_id = %d FOR UPDATE', $this->baseline_state_table(), 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Promotion locks the singleton baseline state in the active transaction before changing its current generation.
		$this->throw_if_database_error();
		if ( ! $locked ) {
			throw new \RuntimeException( 'Incremental baseline state row is unavailable.' );
		}
	}

	/**
	 * Removes stale, noncurrent candidate generations before staging a new candidate.
	 *
	 * @param int $active_import_id Full-shadow import id currently staging a candidate.
	 * @return bool True when all stale candidates were removed.
	 */
	private function prepare_for_staging( int $active_import_id ): bool {
		$candidates = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT candidate_id, baseline_generation_id FROM %i WHERE import_id <> %d AND baseline_generation_id <> COALESCE((SELECT current_baseline_generation_id FROM %i WHERE baseline_state_id = 1), 0) FOR UPDATE', $this->candidate_table(), $active_import_id, $this->baseline_state_table() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Candidate staging removes only plugin-owned stale generations that are not the current baseline.
		$this->throw_if_database_error();
		if ( [] === $candidates ) {
			return true;
		}
		if ( ! is_array( $candidates ) ) {
			throw new \RuntimeException( 'Incremental baseline candidate rows are unavailable.' );
		}

		foreach ( $candidates as $candidate ) {
			if ( ! isset( $candidate->candidate_id, $candidate->baseline_generation_id ) || ! $this->remove_candidate_and_generation( (int) $candidate->candidate_id, (int) $candidate->baseline_generation_id ) ) {
				throw new \RuntimeException( 'Incremental baseline candidate row is invalid.' );
			}
		}

		return true;
	}

	/**
	 * Runs one caller-defined baseline lifecycle decision atomically.
	 *
	 * A callback that reads before it writes the baseline-state row must ask for the lock up front. With the lock
	 * taken before any read, no transaction snapshot can predate it, so an invalidation from another request waits
	 * for this transaction instead of being overwritten (or, where innodb_snapshot_isolation is on, failing it).
	 *
	 * @param callable $callback Receives this repository and returns whether its writes succeeded.
	 * @param bool     $lock_baseline_state Whether to lock the singleton baseline-state row before the callback's first read.
	 * @return bool True when the callback succeeded and its transaction committed; false when it deliberately declined the transition.
	 * @throws \RuntimeException When transaction or callback persistence fails.
	 */
	public function run_in_transaction( callable $callback, bool $lock_baseline_state = false ): bool {
		if ( $lock_baseline_state ) {
			// Created outside the transaction so the lock below never waits on, or upgrades, a shared lock from the insert.
			$this->ensure_baseline_state_row();
		}
		$this->begin_transaction();
		try {
			if ( $lock_baseline_state ) {
				$this->acquire_baseline_state_row_lock();
			}
			if ( ! $callback( $this ) ) {
				$this->rollback_transaction();
				return false;
			}
			$this->commit_transaction();
			return true;
		} catch ( \Throwable $exception ) {
			$this->rollback_transaction();
			throw $exception;
		}
	}

	/**
	 * Starts a database transaction for an atomic repository operation.
	 *
	 * @return void
	 * @throws \RuntimeException When the transaction cannot start.
	 */
	private function begin_transaction(): void {
		if ( false === $this->wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Candidate staging and promotion require atomic custom-table writes.
			$this->throw_database_error( 'Could not start incremental baseline transaction.' );
		}
	}

	/**
	 * Commits the active repository transaction.
	 *
	 * @return void
	 * @throws \RuntimeException When the transaction cannot commit.
	 */
	private function commit_transaction(): void {
		if ( false === $this->wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Candidate staging and promotion require atomic custom-table writes.
			$this->throw_database_error( 'Could not commit incremental baseline transaction.' );
		}
	}

	/**
	 * Rolls back the active repository transaction.
	 *
	 * @return void
	 * @throws \RuntimeException When the transaction cannot be rolled back.
	 */
	private function rollback_transaction(): void {
		if ( false === $this->wpdb->query( 'ROLLBACK' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Candidate staging and promotion require atomic custom-table writes.
			$this->throw_database_error( 'Could not roll back incremental baseline transaction.' );
		}
	}

	/**
	 * @return void
	 * @throws \RuntimeException When WordPress reported a database error.
	 */
	private function throw_if_database_error(): void {
		if ( isset( $this->wpdb->last_error ) && '' !== $this->wpdb->last_error ) {
			$this->throw_database_error( 'Incremental baseline database operation failed.' );
		}
	}

	/**
	 * @param string $operation Failed database operation.
	 * @return void
	 * @throws \RuntimeException Always, with the WordPress database error when available.
	 */
	private function throw_database_error( string $operation ): void {
		$error   = isset( $this->wpdb->last_error ) ? (string) $this->wpdb->last_error : '';
		$message = '' === $error ? $operation : $operation . ' ' . $error;

		throw new \RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
	}

	/**
	 * Returns the plugin-owned baseline entry table name.
	 *
	 * @return string Fully qualified baseline entry table name.
	 */
	private function baseline_table(): string {
		return $this->wpdb->prefix . 'arlo_incremental_baseline';
	}

	/**
	 * Returns the plugin-owned singleton baseline-state table name.
	 *
	 * @return string Fully qualified baseline-state table name.
	 */
	private function baseline_state_table(): string {
		return $this->wpdb->prefix . 'arlo_incremental_baseline_state';
	}

	/**
	 * Returns the plugin-owned full-shadow candidate table name.
	 *
	 * @return string Fully qualified candidate table name.
	 */
	private function candidate_table(): string {
		return $this->wpdb->prefix . 'arlo_incremental_full_baseline_candidates';
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
