<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Best-effort admin state carried across a redirect, scoped to the current user and site.
 *
 * Cache eviction may discard state early; overlapping tabs share the same keys.
 */
class UserFlash {

	const DEMO_SETUP_ERRORS    = 'demo_setup_errors';
	const DEMO_CONNECT_ERROR   = 'demo_connect_error';
	const NEW_CUSTOM_SHORTCODE = 'new_custom_shortcode';
	const CUSTOM_CSS_MIGRATION = 'custom_css_migration';

	/**
	 * @param string $key   One of the class constants.
	 * @param mixed  $value Value to return on the next take().
	 */
	public static function set( string $key, $value ): void {
		$name = self::transient_name( $key );
		if ( null !== $name ) {
			set_transient( $name, $value, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Returns the stored value after successful deletion, or null on a miss or failed deletion.
	 *
	 * @param string $key One of the class constants.
	 * @return mixed|null
	 */
	public static function take( string $key ) {
		$name = self::transient_name( $key );
		if ( null === $name ) {
			return null;
		}

		$value = get_transient( $name );
		if ( false === $value ) {
			return null;
		}
		if ( ! delete_transient( $name ) ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Arlo admin flash state was not returned because transient deletion did not succeed.' );
			return null;
		}

		return $value;
	}

	private static function transient_name( string $key ): ?string {
		$user_id = get_current_user_id();

		return $user_id > 0 ? 'arlo_flash_' . $key . '_' . $user_id : null;
	}
}
