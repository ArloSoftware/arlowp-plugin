<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Resolves availability for known plugin features.
 *
 * Delta import is available without deployment configuration; the administrator's
 * importer-mode setting and schema checks still control whether it is used.
 */
class FeatureFlagManager {
	/**
	 * Canonical identifier for the delta-import feature.
	 */
	private const DELTA_IMPORT_FEATURE_ID = 'delta_import';

	/**
	 * Returns whether a known feature is available.
	 *
	 * @param mixed $feature_id Feature identifier.
	 * @return bool Whether the feature is available.
	 */
	public static function is_enabled( $feature_id ): bool {
		if ( ! is_string( $feature_id ) || self::DELTA_IMPORT_FEATURE_ID !== $feature_id ) {
			return false;
		}

		return true;
	}
}
