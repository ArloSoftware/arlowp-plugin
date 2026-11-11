<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

use ArloTraining\DeltaFatalException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Applies one complete Event root/session availability bundle to the live import.
 *
 * The coordinator owns admission, baseline state, and promotion. This class owns
 * only the bounded row membership check and atomic availability update.
 */
class EventAvailabilityOverlayWriter {
	/** @var \wpdb */
	private $wpdb;

	/**
	 * Test-only callback invoked after verification and before the transaction commits.
	 *
	 * @var callable|null
	 */
	private $before_commit_hook;

	/**
	 * Whether the most recent apply call committed its Event availability bundle.
	 *
	 * @var bool
	 */
	private $bundle_committed = false;

	/**
	 * Uses the supplied WordPress database connection, or the global connection when omitted.
	 *
	 * @param \wpdb|null $wpdb WordPress database connection.
	 */
	public function __construct( $wpdb = null ) {
		$this->wpdb = null !== $wpdb ? $wpdb : $GLOBALS['wpdb'];
	}

	/**
	 * Applies the declared root and every declared session atomically.
	 *
	 * @param int           $import_id Active import identifier.
	 * @param object        $event Complete selected Event root record.
	 * @param callable|null $before_update Runs inside the transaction after membership locks and before updates.
	 * @param string        $transaction_scope Whether this writer owns a transaction or enlists in an existing transaction.
	 * @return void
	 * @throws \InvalidArgumentException When the transaction scope is invalid.
	 * @throws \RuntimeException When membership, update, audit, or transaction work fails.
	 */
	public function apply( int $import_id, object $event, ?callable $before_update = null, string $transaction_scope = DeltaRunParameters::EVENT_OVERLAY_TRANSACTION_SCOPE_STANDALONE ): void {
		$this->bundle_committed = false;
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are validated external Arlo Event payload field names.
		$this->assert_valid_event_contract_shape( $event );
		if ( ! in_array( $transaction_scope, [ DeltaRunParameters::EVENT_OVERLAY_TRANSACTION_SCOPE_STANDALONE, DeltaRunParameters::EVENT_OVERLAY_TRANSACTION_SCOPE_ENLISTED ], true ) ) {
			throw new \InvalidArgumentException( 'Event availability transaction scope is invalid.' );
		}
		// The coordinator declares ownership because transaction-state probes differ across supported database engines.
		$savepoint               = 'arlo_delta_event_overlay';
		$enlisted_in_transaction = DeltaRunParameters::EVENT_OVERLAY_TRANSACTION_SCOPE_ENLISTED === $transaction_scope;
		if ( $enlisted_in_transaction ) {
			$this->execute_transaction_statement( 'SAVEPOINT ' . $savepoint );
		} else {
			$this->execute_transaction_statement( 'START TRANSACTION' );
		}
		$standalone_commit_outcome_unknown = false;

		try {
			// Lock the live bundle and confirm it still matches the selected Event contract.
			$rows = $this->get_locked_event_bundle_rows( $import_id, $event );
			$this->assert_event_bundle_membership( $rows, $event );
			if ( null !== $before_update ) {
				$before_update();
			}
			// Apply availability to the root and every declared session while their rows remain locked.
			$this->update_row( (int) $rows[0]->e_id, EventAvailabilityExtractor::extract_root_db_row_values( $event ) );
			foreach ( $this->get_persisted_sessions_from_event( $event ) as $session ) {
				$this->update_row( $this->find_session_row_id( $rows, (int) $session->EventID ), EventAvailabilityExtractor::extract_session_db_row_values( $session ) );
			}
			// Re-read the locked rows to prove the mapped values persisted before they become visible.
			$this->assert_event_rows_match_availability( $rows, $event );
			if ( null !== $this->before_commit_hook ) {
				call_user_func( $this->before_commit_hook );
			}
			// Release the enlisted savepoint or commit this writer's standalone transaction.
			if ( $enlisted_in_transaction ) {
				$this->execute_transaction_statement( 'RELEASE SAVEPOINT ' . $savepoint );
			} else {
				try {
					$this->execute_transaction_statement( 'COMMIT' );
				} catch ( \Throwable $exception ) {
					// An unacknowledged commit is terminal; recovery must not infer its outcome.
					$standalone_commit_outcome_unknown = true;
					throw new DeltaFatalException( 'Event availability overlay commit outcome is unknown.', 0, $exception );
				}
			}
			$this->bundle_committed = true;
		} catch ( \Throwable $exception ) {
			if ( $standalone_commit_outcome_unknown ) {
				// Do not let generic cleanup infer that the unacknowledged commit rolled back.
				throw $exception;
			}
			try {
				// Keep the live dataset unchanged when any step before commit fails.
				$this->execute_transaction_statement( $enlisted_in_transaction ? 'ROLLBACK TO SAVEPOINT ' . $savepoint : 'ROLLBACK' );
			} catch ( \Throwable $rollback_exception ) {
				throw new \RuntimeException( 'Could not roll back Event availability overlay.', 0, $rollback_exception ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The chained rollback exception is propagated to the importer coordinator and logged; it is not rendered.
			}
			throw $exception;
		}

		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Locks the exact live Event root and declared session rows for the selected bundle.
	 *
	 * @param int    $import_id Active import identifier.
	 * @param object $event Selected Event root.
	 * @return array<int,object>
	 */
	private function get_locked_event_bundle_rows( int $import_id, object $event ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are validated external Arlo Event payload field names.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The bounded placeholder list is generated solely from validated selected session IDs for plugin-owned Event row locking.
		$session_ids = [];
		foreach ( $this->get_persisted_sessions_from_event( $event ) as $session ) {
			$session_ids[] = (int) $session->EventID;
		}
		sort( $session_ids, SORT_NUMERIC );
		$root_predicate   = '(
			e_arlo_id = %d
			AND e_parent_arlo_id = 0
		)';
		$arguments        = [ $import_id, (string) $event->Region, (int) $event->EventID ];
		$bundle_predicate = $root_predicate;
		if ( [] !== $session_ids ) {
			$placeholders     = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
			$bundle_predicate = '(
				' . $root_predicate . '
				OR (
					e_parent_arlo_id = %d
					AND e_arlo_id IN (' . $placeholders . ')
				)
			)';
			$arguments[]      = (int) $event->EventID;
			$arguments        = array_merge( $arguments, $session_ids );
		}
		$query = $this->wpdb->prepare(
			"SELECT
				e_id,
				e_arlo_id,
				e_parent_arlo_id
			FROM {$this->wpdb->prefix}arlo_events
			WHERE import_id = %d
				AND e_region = %s
				AND {$bundle_predicate}
			ORDER BY e_parent_arlo_id, e_arlo_id
			FOR UPDATE",
			$arguments
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned Event rows are locked by exact active-import, region, parent, and Arlo identities.
		$rows  = $this->wpdb->get_results( $query ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $query is prepared from fixed SQL and validated Event identities.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		if ( ! is_array( $rows ) ) {
			throw new \RuntimeException( 'Could not lock Event availability bundle rows.' );
		}

		return $rows;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Verifies the locked rows are exactly the declared root and sessions, with no missing or extra rows.
	 *
	 * @param array<int,object> $event_bundle_rows Locked local Event bundle rows.
	 * @param object            $selected_event Selected Event root.
	 * @return void
	 */
	private function assert_event_bundle_membership( array $event_bundle_rows, object $selected_event ): void {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are validated external Arlo Event payload field names.
		$sessions = $this->get_persisted_sessions_from_event( $selected_event );
		if ( count( $event_bundle_rows ) !== count( $sessions ) + 1 || ! isset( $event_bundle_rows[0]->e_parent_arlo_id, $event_bundle_rows[0]->e_arlo_id ) || 0 !== (int) $event_bundle_rows[0]->e_parent_arlo_id || (int) $selected_event->EventID !== (int) $event_bundle_rows[0]->e_arlo_id ) {
			throw new \RuntimeException( 'Event availability bundle membership does not match the live import.' );
		}
		foreach ( $sessions as $session ) {
			$session_row_found = false;
			foreach ( $event_bundle_rows as $event_bundle_row ) {
				if ( isset( $event_bundle_row->e_parent_arlo_id, $event_bundle_row->e_arlo_id ) && (int) $event_bundle_row->e_parent_arlo_id === (int) $selected_event->EventID && (int) $event_bundle_row->e_arlo_id === (int) $session->EventID ) {
					$session_row_found = true;
					break;
				}
			}
			if ( ! $session_row_found ) {
				throw new \RuntimeException( 'Event availability session parent membership does not match the live import.' );
			}
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Finds the locked local row for one declared session.
	 *
	 * @param array<int,object> $rows Locked local rows.
	 * @param int               $session_id Arlo session identity.
	 * @return int Local row id.
	 */
	private function find_session_row_id( array $rows, int $session_id ): int {
		foreach ( $rows as $row ) {
			if ( isset( $row->e_parent_arlo_id, $row->e_arlo_id, $row->e_id ) && (int) $row->e_parent_arlo_id > 0 && $session_id === (int) $row->e_arlo_id ) {
				return (int) $row->e_id;
			}
		}
		throw new \RuntimeException( 'Event availability session membership does not match the live import.' );
	}

	/**
	 * Updates availability fields on one row already locked by the bundle transaction.
	 *
	 * @param int                 $row_id Locked local Event row id.
	 * @param array<string,mixed> $values Availability fields.
	 * @return void
	 */
	private function update_row( int $row_id, array $values ): void {
		$updated = $this->wpdb->update( $this->wpdb->prefix . 'arlo_events', $values, [ 'e_id' => $row_id ] );
		if ( false === $updated || $updated > 1 ) {
			throw new \RuntimeException( 'Could not update Event availability row.' );
		}
	}

	/**
	 * Verifies the locked rows contain exactly the mapped availability values from the selected Event.
	 *
	 * @param array<int,object> $rows Locked local Event rows.
	 * @param object            $event Selected Event root.
	 * @return void
	 */
	private function assert_event_rows_match_availability( array $rows, object $event ): void {
		$row_ids      = array_map(
			static function ( object $row ): int {
				return (int) $row->e_id;
			},
			$rows
		);
		$placeholders = implode( ',', array_fill( 0, count( $row_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The dynamically generated %d list is populated solely from locked local Event row ids and audits their exact stored values before commit.
		$query        = $this->wpdb->prepare( "SELECT e_id, e_isfull, e_placesremaining, e_registermessage, e_registeruri FROM {$this->wpdb->prefix}arlo_events WHERE e_id IN ({$placeholders}) FOR UPDATE", $row_ids );
		$audited_rows = $this->wpdb->get_results( $query ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $query is prepared from locked local Event row IDs.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		if ( ! is_array( $audited_rows ) || count( $audited_rows ) !== count( $rows ) ) {
			throw new \RuntimeException( 'Could not audit Event availability bundle.' );
		}
		$actual = [];
		foreach ( $audited_rows as $row ) {
			if ( ! is_object( $row ) || ! isset( $row->e_id ) ) {
				throw new \RuntimeException( 'Event availability bundle audit is invalid.' );
			}
			$actual[ (int) $row->e_id ] = $row;
		}
		try {
			$this->assert_row_values_match( $actual, (int) $rows[0]->e_id, EventAvailabilityExtractor::extract_root_db_row_values( $event ) );
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are validated external Arlo Event payload field names.
			foreach ( $this->get_persisted_sessions_from_event( $event ) as $session ) {
				$this->assert_row_values_match( $actual, $this->find_session_row_id( $rows, (int) $session->EventID ), EventAvailabilityExtractor::extract_session_db_row_values( $session ) );
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		} catch ( \RuntimeException $exception ) {
			throw new \RuntimeException( 'Event availability bundle audit does not match the selected target.', 0, $exception ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The chained exception is propagated to the importer coordinator and logged; it is not rendered.
		}
	}

	/**
	 * Confirms a local row contains every expected value.
	 *
	 * @param array<int,object>   $rows_by_id Local rows by local id.
	 * @param int                 $row_id Local Event row id.
	 * @param array<string,mixed> $expected_values Expected column values.
	 * @return void
	 */
	private function assert_row_values_match( array $rows_by_id, int $row_id, array $expected_values ): void {
		if ( ! isset( $rows_by_id[ $row_id ] ) ) {
			throw new \RuntimeException( 'Local row is missing.' );
		}
		foreach ( $expected_values as $column => $value ) {
			if ( ! property_exists( $rows_by_id[ $row_id ], $column ) || $rows_by_id[ $row_id ]->$column != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- MySQL returns numeric availability values as strings while the mapped full-shadow values retain scalar source types.
				throw new \RuntimeException( 'Local row values do not match.' );
			}
		}
	}

	/**
	 * Validates the selected root and sessions before they are used to form database predicates.
	 *
	 * @param object $event Selected Event root.
	 * @return void
	 */
	private function assert_valid_event_contract_shape( object $event ): void {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are validated external Arlo Event payload field names.
		if ( ! isset( $event->EventID, $event->Region, $event->Sessions ) || ! is_numeric( $event->EventID ) || (int) $event->EventID <= 0 || ! is_string( $event->Region ) || '' === $event->Region || ! is_array( $event->Sessions ) ) {
			throw new \RuntimeException( 'Selected Event availability bundle is invalid.' );
		}
		$session_ids = [];
		foreach ( $event->Sessions as $session ) {
			if ( ! is_object( $session ) || ! isset( $session->EventID ) || ! is_numeric( $session->EventID ) || (int) $session->EventID <= 0 || isset( $session_ids[ (int) $session->EventID ] ) ) {
				throw new \RuntimeException( 'Selected Event availability sessions are invalid.' );
			}
			$session_ids[ (int) $session->EventID ] = true;
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Returns the Event's child sessions as they would exist in the persisted tables.
	 *
	 * Returns an empty array when the first nested session refers to the root
	 * Event; otherwise, returns the complete validated Sessions array.
	 *
	 * Remarks: This matches Events::import_event_cluster(), which defines the
	 * persisted-session semantics for self-referential first session records.
	 *
	 * @param object $event_payload Validated selected Event JSON payload.
	 * @return array<int,object> Child session records.
	 */
	private function get_persisted_sessions_from_event( object $event_payload ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- These are validated external Arlo Event payload field names.
		if ( [] !== $event_payload->Sessions && (int) $event_payload->Sessions[0]->EventID === (int) $event_payload->EventID ) {
			return [];
		}

		$sessions = $event_payload->Sessions;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return $sessions;
	}

	/**
	 * Executes one fixed transaction-control statement for the overlay transaction.
	 *
	 * @param string $query Transaction statement.
	 * @return void
	 */
	private function execute_transaction_statement( string $query ): void {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The Event overlay requires fixed transaction control statements around plugin-owned row updates.
		if ( false === $this->wpdb->query( $query ) ) {
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			throw new \RuntimeException( 'Event availability overlay transaction failed.' );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Test-only hook used to prove rollback before a first bundle commit.
	 *
	 * @param callable $hook Invoked after all updates and before commit.
	 * @return void
	 */
	public function set_before_commit_hook( callable $hook ): void {
		$this->before_commit_hook = $hook;
	}

	/**
	 * Returns whether the most recent apply call committed its data transaction.
	 *
	 * @return bool True when a visible Event bundle commit completed.
	 */
	public function has_committed_bundle(): bool {
		return $this->bundle_committed;
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
