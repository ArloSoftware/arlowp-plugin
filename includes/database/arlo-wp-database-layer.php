<?php

namespace Arlo\Database;

/* See the DatabaseLayer class for the function definition */

class WPDatabaseLayer extends DatabaseLayer {

	public function __construct() {
		global $wpdb;

		$this->wpdb = &$wpdb;
		$this->charset = $this->wpdb->charset;
		$this->prefix = $this->wpdb->prefix;
	}

	public function suppress_errors($suppress = true) {
		return $this->wpdb->suppress_errors($suppress);
	}

	public function query($sql) {
		return $this->wpdb->query($sql);
	}

	public function get_results($sql, $output) {
		return $this->wpdb->get_results($sql, $output);
	}

	public function sync_schema($sql, $execute = true) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta($sql);
	}

}