<?php

namespace Arlo\Database;

/* See the DatabaseLayer class for the function definition */

class WPDatabaseLayer extends DatabaseLayer {

	public function __construct() {
		global $wpdb;

		$this->wpdb = &$wpdb;
		$this->charset_collate = $this->wpdb->get_charset_collate();
		$this->prefix = $this->wpdb->prefix;
	}

	private function set_variables() {
		$this->insert_id = $this->wpdb->insert_id;
		$this->last_error = $this->wpdb->last_error;
		$this->last_query = $this->wpdb->last_query;
		$this->num_rows = $this->wpdb->num_rows;
	}

	public function suppress_errors($suppress = true) {
		return $this->wpdb->suppress_errors($suppress);
	}

	public function query($sql) {
		$return = $this->wpdb->query($sql);
		$this->set_variables();

		return $return;
	}

	public function get_results($sql, $output = 'OBJECT') {
		$return = $this->wpdb->get_results($sql, $output);
		$this->set_variables();

		return $return; 
	}

	public function get_var( $query = null, $x = 0, $y = 0 ) {
		$return = $this->wpdb->get_var($query, $x, $y);
		$this->set_variables();
		return $return;
	}

	public function sync_schema($sql, $execute = true) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta($sql);
	}

	public function insert( $table, $data, $format = null ) {
		$return = $this->wpdb->insert($table, $data, $format);

		$this->set_variables();
		return $return; 
	}

	public function prepare( $query, $args ) {
		$args = func_get_args();
		array_shift( $args );

		// If args were passed as an array (as in vsprintf), move them up
		if ( isset( $args[0] ) && is_array($args[0]) )
			$args = $args[0];

		return $this->wpdb->prepare($query, $args);
	}

	public function _real_escape($query) {
		return $this->wpdb->_real_escape($query);
	}

	public function determine_charset( $charset, $collate ) {
		return $this->wpdb->determine_charset($charset, $collate);
	}

	public function init_charset() {
		$this->wpdb->init_charset();
	}

	public function get_col_charset($table, $column) {
		return $this->wpdb->get_col_charset($table, $column);
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		return $this->wpdb->update($table, $data, $where, $format, $where_format);
	}
}