<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Custom CSS state, legacy output, and PHP output preparation.
 *
 * The state says where a site's custom CSS lives. It now lives in WordPress's own Additional CSS (see CustomCssMigration),
 * and the plugin keeps three modes: 'disabled' (the plugin holds no CSS), 'legacy' (CSS from earlier versions is still held and
 * printed by the plugin until an administrator migrates it) and 'migrated' (the CSS is in Additional CSS and the plugin prints nothing).
 * Writing site data into the plugin folder is not a supported WordPress pattern, so the plugin never writes a CSS file.
 */
class CustomCss {

	const STATE_KEY = 'customcss_state';

	const STATE_VERSION = 2;

	/**
	 * The plugin holds no CSS: new installs and sites whose saved CSS is blank.
	 */
	const MODE_DISABLED = 'disabled';

	/**
	 * The plugin still holds the CSS from earlier versions and prints it until the administrator migrates it.
	 */
	const MODE_LEGACY = 'legacy';

	/**
	 * The CSS lives in WordPress's Additional CSS; the plugin prints nothing.
	 */
	const MODE_MIGRATED = 'migrated';

	/**
	 * Legacy source: the pre-5.2.0 plugin-folder stylesheet, used while that file exists.
	 */
	const LEGACY_SOURCE_FILE = 'file';

	/**
	 * Legacy source: the CSS saved in the plugin's own settings (arlo_settings['customcss']), which the plugin prints inline in the page head.
	 */
	const LEGACY_SOURCE_PLUGIN_SETTING = 'plugin_setting';

	/**
	 * Largest legacy stylesheet the editor will load, in bytes.
	 */
	const LEGACY_FILE_MAX_BYTES = 4194304;

	/**
	 * Violation code: the CSS contains, or ends in a partial, closing style tag that would end its style element.
	 */
	const VIOLATION_STYLE_CLOSE = 'style_close';

	/**
	 * Violation code: the CSS is not valid UTF-8 and the database layer would silently drop it.
	 */
	const VIOLATION_INVALID_ENCODING = 'invalid_encoding';

	/**
	 * Whether this request is persisting only the Custom CSS state.
	 *
	 * @return bool
	 */
	public static function is_state_update(): bool {
		return SettingsPersistence::is_system_write();
	}

	/**
	 * Persists the Custom CSS state without resaving unrelated settings surfaces.
	 *
	 * @param array $settings Saved settings with an updated Custom CSS state.
	 * @return bool
	 */
	public static function persist_state( array $settings ): bool {
		return SettingsPersistence::write_as_system( $settings );
	}

	/**
	 * Returns the stored state when it is valid, otherwise infers it from data written by earlier plugin versions.
	 *
	 * Inference never changes CSS: a site that holds any CSS, saved or in the old file, starts as legacy, so it keeps the styling it has today.
	 *
	 * @param array $settings Saved settings.
	 * @return array State.
	 */
	public static function resolve_state( array $settings ): array {
		$stored = isset( $settings[ self::STATE_KEY ] ) ? static::normalize_state( $settings[ self::STATE_KEY ] ) : null;
		if ( null !== $stored ) {
			// A legacy state with nothing left to migrate, for example after a revert that restored an emptied block, is simply disabled.
			if ( self::MODE_LEGACY === $stored['mode'] && ! static::holds_legacy_css( $settings, self::LEGACY_SOURCE_FILE === $stored['legacy_source'] && static::has_legacy_file() ) ) {
				$stored['mode'] = self::MODE_DISABLED;
			}
			return $stored;
		}

		// Legacy mode only: before 5.2.0 the option 'arlo_customcss' recorded that the plugin-folder file was in use.
		$file_flag = 'file' === get_option( 'arlo_customcss', false ) && static::has_legacy_file();
		return [
			'version'       => self::STATE_VERSION,
			'mode'          => static::holds_legacy_css( $settings, $file_flag ) ? self::MODE_LEGACY : self::MODE_DISABLED,
			'legacy_source' => $file_flag ? self::LEGACY_SOURCE_FILE : self::LEGACY_SOURCE_PLUGIN_SETTING,
			'migration'     => null,
			'reverted_at'   => null,
		];
	}

	/**
	 * Whether the plugin still holds CSS from earlier versions: saved in its settings, or in the old plugin-folder file when that is in use.
	 *
	 * @param array $settings  Saved settings.
	 * @param bool  $file_used Whether the old plugin-folder file is the source in use.
	 * @return bool
	 */
	private static function holds_legacy_css( array $settings, bool $file_used ): bool {
		return ! static::is_blank_css( $settings['customcss'] ?? null ) || ( $file_used && ! static::legacy_file_is_blank() );
	}

	/**
	 * Validates a stored state, so an unreadable or older shape is inferred again instead of trusted.
	 *
	 * @param mixed $state Stored state.
	 * @return array|null Normalized state, or null when it cannot be used.
	 */
	private static function normalize_state( $state ): ?array {
		if ( ! is_array( $state ) || ( $state['version'] ?? null ) !== self::STATE_VERSION ) {
			return null;
		}
		if ( ! in_array( $state['mode'] ?? null, [ self::MODE_DISABLED, self::MODE_LEGACY, self::MODE_MIGRATED ], true ) ) {
			return null;
		}
		$source    = $state['legacy_source'] ?? null;
		$migration = $state['migration'] ?? null;
		if ( self::MODE_MIGRATED === $state['mode'] && ! is_array( $migration ) ) {
			return null;
		}
		return [
			'version'       => self::STATE_VERSION,
			'mode'          => $state['mode'],
			'legacy_source' => self::LEGACY_SOURCE_FILE === $source ? self::LEGACY_SOURCE_FILE : self::LEGACY_SOURCE_PLUGIN_SETTING,
			'migration'     => is_array( $migration ) ? $migration : null,
			'reverted_at'   => isset( $state['reverted_at'] ) && is_int( $state['reverted_at'] ) ? $state['reverted_at'] : null,
		];
	}

	/**
	 * Whether CSS has no visible content: only whitespace, including Unicode spaces and invisible format characters.
	 *
	 * Comments are content, because they can hold rules an administrator has switched off. Malformed UTF-8 and non-string values are never treated as blank.
	 *
	 * @param mixed $css Saved CSS, or null when never saved.
	 * @return bool
	 */
	public static function is_blank_css( $css ): bool {
		if ( null === $css ) {
			return true;
		}
		if ( ! is_string( $css ) ) {
			return false;
		}
		return '' === preg_replace( '/[\s\p{Z}\p{Cc}\p{Cf}]+/u', '', $css );
	}

	/**
	 * Whether the historical static response is absent or contains only whitespace.
	 *
	 * An unreadable or large file is never treated as blank.
	 *
	 * @return bool
	 */
	public static function legacy_file_is_blank(): bool {
		// Legacy mode only: reads the pre-5.2.0 plugin-folder file to classify it; no new callers.
		if ( ! static::has_legacy_file() ) {
			return true;
		}
		$path = static::legacy_file_path();
		$size = filesize( $path );
		if ( false === $size || $size > 4096 ) {
			return false;
		}
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the fixed plugin-owned stylesheet, at most 4 KiB, to classify it as blank.
		return false !== $contents && static::is_blank_css( $contents );
	}

	/**
	 * Records the Custom CSS state once, without changing CSS.
	 *
	 * @return void
	 */
	public static function initialize(): void {
		$settings = get_option( 'arlo_settings', [] );
		if ( ! is_array( $settings ) ) {
			return;
		}
		if ( isset( $settings[ self::STATE_KEY ] ) && null !== static::normalize_state( $settings[ self::STATE_KEY ] ) ) {
			return;
		}
		$settings[ self::STATE_KEY ] = static::resolve_state( $settings );
		if ( ! self::persist_state( $settings ) ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Custom CSS state initialization could not be saved.' );
		}
	}

	/**
	 * Fixed historical asset path; never selected by a request.
	 *
	 * @return string
	 */
	public static function legacy_file_path(): string {
		// Legacy mode only: the plugin never writes this file; it is left in place and lost on the next plugin upgrade.
		return dirname( __DIR__ ) . '/public/assets/css/custom.css';
	}

	/**
	 * Whether the historical static response is available.
	 *
	 * @return bool
	 */
	public static function has_legacy_file(): bool {
		// Legacy mode only: no new callers.
		return is_file( static::legacy_file_path() );
	}

	/**
	 * Whether the plugin should link the old plugin-folder stylesheet for this state.
	 *
	 * @param array $state State from resolve_state().
	 * @return bool
	 */
	public static function links_legacy_file( array $state ): bool {
		// Legacy mode only: a migrated or reverted site never links the old file, even if it is still on disk.
		return self::MODE_LEGACY === $state['mode'] && self::LEGACY_SOURCE_FILE === $state['legacy_source'] && static::has_legacy_file();
	}

	/**
	 * Whether the plugin should print the saved CSS in the page head for this state.
	 *
	 * @param array $state State from resolve_state().
	 * @return bool
	 */
	public static function prints_saved_css( array $state ): bool {
		return self::MODE_LEGACY === $state['mode'] && ! static::links_legacy_file( $state );
	}

	/**
	 * Returns the CSS the editor should start from, which is what visitors receive today.
	 *
	 * @param array $settings Saved settings.
	 * @return array{css: string, from_file: bool, differs: bool} The text, whether it came from the old file, and whether it differs from the saved copy.
	 */
	public static function get_editor_source( array $settings ): array {
		$saved = isset( $settings['customcss'] ) && is_string( $settings['customcss'] ) ? $settings['customcss'] : '';
		$state = static::resolve_state( $settings );
		if ( static::links_legacy_file( $state ) ) {
			$size = filesize( static::legacy_file_path() );
			if ( false !== $size && $size > 0 && $size <= self::LEGACY_FILE_MAX_BYTES ) {
				$contents = file_get_contents( static::legacy_file_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the fixed plugin-owned stylesheet, bounded above, so the editor shows the CSS visitors receive.
				if ( false !== $contents && ! static::is_blank_css( $contents ) && 1 === preg_match( '//u', $contents ) ) {
					return [
						'css'       => $contents,
						'from_file' => true,
						'differs'   => static::normalize_line_endings( $contents ) !== static::normalize_line_endings( $saved ),
					];
				}
			}
		}
		return [
			'css'       => $saved,
			'from_file' => false,
			'differs'   => false,
		];
	}

	/**
	 * Converts CRLF and CR line endings to LF, which is how WordPress stores Additional CSS after an editor save.
	 *
	 * @param string $css CSS text.
	 * @return string
	 */
	public static function normalize_line_endings( string $css ): string {
		return str_replace( [ "\r\n", "\r" ], "\n", $css );
	}

	/**
	 * Whether the current user may write CSS into core's Additional CSS stores, and so may migrate or revert Custom CSS.
	 *
	 * Follows core's Additional CSS: `edit_css` maps to `unfiltered_html`, so on multisite only super admins qualify and
	 * DISALLOW_UNFILTERED_HTML disables it for everyone. Core drops or corrupts CSS written by a user without it (the
	 * global-styles route answers 200 and discards the CSS; kses rewrites `>` for other writers), so callers must check
	 * this before writing and must never write with no current user.
	 *
	 * @return bool
	 */
	public static function current_user_can_edit(): bool {
		return current_user_can( 'edit_css' );
	}

	/**
	 * Whether the site configuration turns off unfiltered HTML for every user, so granting a role the capability cannot help.
	 *
	 * @return bool
	 */
	public static function is_editing_disabled_by_configuration(): bool {
		return defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML;
	}

	/**
	 * Keeps the saved CSS and the Custom CSS state fixed across ordinary settings saves.
	 *
	 * The CSS is never part of the settings form; any submitted value is ignored, so only the migration commands change it.
	 *
	 * @param array $settings Settings about to be stored.
	 * @param array $old      Previously saved settings.
	 * @return array
	 */
	public static function prepare_settings_for_save( array $settings, array $old ): array {
		$settings[ self::STATE_KEY ] = self::resolve_state( $old );
		if ( array_key_exists( 'customcss', $old ) ) {
			$settings['customcss'] = $old['customcss'];
		} else {
			unset( $settings['customcss'] );
		}
		return $settings;
	}

	/**
	 * Returns the rule violations that prevent Custom CSS from being written to Additional CSS.
	 *
	 * Like core's Additional CSS, this guards only the HTML parser boundary. It does not parse or filter CSS rule syntax.
	 *
	 * @param string $css Submitted Custom CSS.
	 * @return string[] Violation codes in a stable order; empty when the CSS may be written.
	 */
	public static function find_violations( string $css ): array {
		$violations = [];

		// Maintainer note: like core's Additional CSS, we only stop the CSS closing its own style element and trust users who can edit settings.
		// A trailing partial closing tag is rejected too, because later concatenation could complete it.
		if ( 1 === preg_match( '~</style(?:[ \t\f\r\n/>]|\z)|<(?:/(?:s(?:t(?:y(?:l)?)?)?)?)?\z~i', $css ) ) {
			$violations[] = self::VIOLATION_STYLE_CLOSE;
		}

		// Maintainer note: a data-integrity guard, not a security filter; the database layer would otherwise silently drop invalid UTF-8.
		if ( 1 !== preg_match( '//u', $css ) ) {
			$violations[] = self::VIOLATION_INVALID_ENCODING;
		}

		return $violations;
	}

	/**
	 * Prepares stored Custom CSS for an inline style element.
	 *
	 * Escapes any closing style tag in place, exactly as core's HTML API does when printing style text,
	 * so CSS saved before validation existed cannot end the element. Escaping here also keeps
	 * wp_add_inline_style() from unwrapping the text or reporting incorrect usage.
	 *
	 * @param string $css Stored Custom CSS.
	 * @return string
	 */
	public static function prepare_for_output( string $css ): string {
		// Deliberately no CSS parsing or stripping; legacy script vectors such as expression() no longer run in supported browsers.
		return (string) preg_replace_callback(
			'~</(style)~i',
			static function ( array $matches ): string {
				return '\\3c\\2f' . $matches[1];
			},
			$css
		);
	}
}
