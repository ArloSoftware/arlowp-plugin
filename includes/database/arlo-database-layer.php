<?php

namespace Arlo\Database;

/* This class is an abstraction of the WPDB, to make the unit testing easier */

abstract class DatabaseLayer {
	private $wpdb;
	
	public $charset;
	public $prefix;

	public function __construct() {}

	/**
	 * Whether to suppress database errors.
	 *
	 * By default database errors are suppressed, with a simple
	 * call to this function they can be enabled.
	 *
	 * @see wpdb::hide_errors()
	 * @param bool $suppress Optional. New value. Defaults to true.
	 * @return bool Old value
	 */
	abstract public function suppress_errors($suppress = true);


	/**
	 * Perform a MySQL database query, using current database connection.
	 *
	 * More information can be found on the codex page.
	 *
	 * @param string $query Database query
	 * @return int|false Number of rows affected/selected or false on error
	 */
	abstract public function query($sql);


	/**
	 * Retrieve an entire SQL result set from the database (i.e., many rows)
	 *
	 * Executes a SQL query and returns the entire SQL result.
	 *
	 * @param string $query  SQL query.
	 * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants.
	 *                       With one of the first three, return an array of rows indexed from 0 by SQL result row number.
	 *                       Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
	 *                       With OBJECT_K, return an associative array of row objects keyed by the value of each row's first column's value.
	 *                       Duplicate keys are discarded.
	 * @return array|object|null Database query results
	 */
	abstract public function get_results($sql, $output);


	/**
	* Modifies the database based on specified SQL statements.
	*
	* Useful for creating new tables and updating existing tables to a new structure.
	*
	* @param string|array $queries Optional. The query to run. Can be multiple queries
	*                              in an array, or a string of queries separated by
	*                              semicolons. Default empty.
	* @param bool         $execute Optional. Whether or not to execute the query right away.
	*                              Default true.
	* @return array Strings containing the results of the various update queries.
	*/
	abstract public function sync_schema($sql, $execute = true );
}