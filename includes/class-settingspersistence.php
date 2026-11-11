<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Writes the settings option from plugin code without running the settings form processing.
 *
 * The settings form sanitizer and the settings saved hooks assume a posted form: a checkbox that is not in the submission
 * becomes '0' and a stored '0' becomes '1'. Upgrade steps and state-only writes change one part of the stored array and
 * must leave every other setting as it is, so they persist through this class instead of a plain update_option().
 */
class SettingsPersistence {

	/**
	 * Whether a system write is in progress, so settings form processing must be skipped.
	 *
	 * @var bool
	 */
	private static $system_write = false;

	/**
	 * Whether the settings option is being written by the plugin itself (an upgrade step or a state-only write)
	 * rather than submitted from the settings form.
	 *
	 * @return bool
	 */
	public static function is_system_write(): bool {
		return self::$system_write;
	}

	/**
	 * Saves the complete settings array as a system write, without running the settings form sanitizer or the settings saved hooks.
	 *
	 * @param array $settings Complete settings array to store.
	 * @return bool Whether the option was updated.
	 */
	public static function write_as_system( array $settings ): bool {
		$previous           = self::$system_write;
		self::$system_write = true;
		try {
			return update_option( 'arlo_settings', $settings );
		} finally {
			self::$system_write = $previous;
		}
	}
}
