<?php

namespace ArloTraining\Entities;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Validates the SQL fragments that entity query builders append to a statement as text.
 *
 * Values are always bound with $wpdb->prepare(). Condition keys and ORDER BY entries are the
 * only caller-supplied text in those statements, so they must match the fixed shapes below.
 */
class QueryShape {

	/**
	 * A column reference with an optional alias and direction, for example "e.e_startdatetime ASC".
	 */
	const ORDER_ENTRY_PATTERN = '/\A[a-z_][a-z0-9_]*(?:\.[a-z_][a-z0-9_]*)?(?: +(?:ASC|DESC))?\z/i';

	/**
	 * An aliased column compared with one placeholder, for example "e.e_region = %s" or "e.v_id IN ( %s )".
	 * Identifiers and the NOT and IN keywords are case-insensitive; the placeholder letter is not, because
	 * $wpdb->prepare() supports only lower case %s and %d.
	 */
	const CONDITION_KEY_PATTERN = '/\A[a-zA-Z]+\.[a-zA-Z][a-zA-Z0-9_]*(?: += +%[sd]| +(?:(?i:NOT) +)?(?i:IN) +\( *%[sd] *\))\z/';

	/**
	 * Validates ORDER BY entries before a builder adds its own.
	 *
	 * @param array|null $order Order entries such as "e.e_startdatetime ASC"; null means none.
	 * @return array The validated entries.
	 * @throws InvalidArgumentException When $order is not an array or null, or an entry is not a column reference.
	 */
	public static function get_validated_order( $order ): array {
		if ( null === $order ) {
			return array();
		}

		if ( ! is_array( $order ) ) {
			throw new InvalidArgumentException( 'ORDER BY entries must be an array of column references.' );
		}

		foreach ( $order as $entry ) {
			if ( ! is_string( $entry ) || 1 !== preg_match( self::ORDER_ENTRY_PATTERN, $entry ) ) {
				throw new InvalidArgumentException( 'An ORDER BY entry must be a column reference with an optional ASC or DESC.' );
			}
		}

		return $order;
	}

	/**
	 * Builds an ORDER BY clause from column references.
	 *
	 * @param array|null $order Order entries such as "e.e_startdatetime ASC".
	 * @return string The clause with a leading space, or an empty string when there are no entries.
	 * @throws InvalidArgumentException When an entry is not a column reference.
	 */
	public static function build_order_clause( $order ): string {
		$order = self::get_validated_order( $order );

		return empty( $order ) ? '' : ' ORDER BY ' . implode( ', ', $order );
	}

	/**
	 * Confirms a condition key is one of the fixed SQL shapes the builders accept.
	 *
	 * @param mixed $key Condition key containing a single %s or %d placeholder.
	 * @return string The key, unchanged.
	 * @throws InvalidArgumentException When the key is not an accepted shape.
	 */
	public static function get_validated_condition_key( $key ): string {
		if ( ! is_string( $key ) || 1 !== preg_match( self::CONDITION_KEY_PATTERN, $key ) ) {
			throw new InvalidArgumentException( 'A condition key must be an aliased column compared with one placeholder.' );
		}

		return $key;
	}
}
