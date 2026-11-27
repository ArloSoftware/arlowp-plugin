<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Parses and sanitizes values the settings form posts into the arlo_settings option.
 *
 * Each method takes a submitted value and returns a value of the shape the settings save reads. A method that needs the stored
 * settings takes them as an argument, so none of them reads or writes stored options or the request, and they can be unit
 * tested on their own. Values that need user feedback or a lookup outside the settings (the platform name check, the
 * deployment mode first-save rule, the importer mode) stay with the settings controller.
 */
class SettingsSanitizer {

	/**
	 * Top-level keys the filter options builder writes its hide and show-only lists to, so a submitted filter group cannot use them.
	 *
	 * @var string[]
	 */
	private const RESERVED_FILTER_GROUPS = [ 'hiddenfilters', 'showonlyfilters' ];

	/**
	 * Longest region ID, in characters. Region IDs are ASCII, so this is also the byte length of the 16 character and 16 byte
	 * region columns.
	 *
	 * @var int
	 */
	public const REGION_ID_MAX_LENGTH = 16;

	/**
	 * A valid region ID: ASCII letters and digits, with hyphens and underscores after the first character. This is the
	 * pattern the delta manifest parser accepts for a Region, so a configured region can match the platform's. It also keeps
	 * quotes, ampersands, angle brackets, slashes, colons, spaces, percent signs and non-ASCII characters out of region IDs,
	 * which appear in URLs as a path segment (region-XX).
	 *
	 * @var string
	 */
	public const REGION_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,' . ( self::REGION_ID_MAX_LENGTH - 1 ) . '}$/D';

	/**
	 * The same rule for an HTML pattern attribute, which browsers anchor themselves. The hyphen is escaped so the pattern is
	 * valid in unicode sets mode.
	 *
	 * @var string
	 */
	public const REGION_ID_HTML_PATTERN = '[A-Za-z0-9][A-Za-z0-9_\-]{0,' . ( self::REGION_ID_MAX_LENGTH - 1 ) . '}';

	/**
	 * Settings that the settings screen submits from single-line text inputs.
	 *
	 * @var string[]
	 */
	public const TEXT_SETTING_KEYS = [ 'free_text', 'noevent_text', 'noeventontemplate_text', 'googlemaps_api_key', 'taxexempt_tag' ];

	/**
	 * Settings whose value is a structure posted by the settings form, each with its own sanitiser, so sanitize_scalar_settings()
	 * leaves them alone.
	 *
	 * @var string[]
	 */
	public const STRUCTURED_SETTING_KEYS = [
		'regionid',
		'regionname',
		'post_types',
		'templates',
		'arlo_filter_settings',
		'arlo_page_filter_settings',
	];

	/**
	 * Settings that hold raw text by design and must never be reduced to plain text. The value must still be a string.
	 *
	 * The legacy stylesheet is written only by the Custom CSS migration commands and is never part of the settings form.
	 *
	 * @var string[]
	 */
	public const RAW_SETTING_KEYS = [ 'customcss' ];

	/**
	 * One-off commands that the settings form and the delete shortcode link send with the settings. The settings save reads
	 * and removes them before anything is stored, and validates each one itself.
	 *
	 * @var string[]
	 */
	public const FORM_COMMAND_KEYS = [ 'new_custom_shortcode', 'new_custom_shortcode_type', 'delete_shortcode' ];

	/**
	 * Settings that hold an on/off choice. The settings form posts 1 for a ticked box and omits an unticked one.
	 *
	 * @var string[]
	 */
	public const TOGGLE_SETTING_KEYS = [ 'keep_settings', 'disable_ssl_verification', 'show_powered_by', 'legacy_region_id_handling' ];

	/**
	 * Settings that hold a number of bytes or seconds. The settings save range-checks them, and resets a blank or non-numeric
	 * value to the default (the fragment size) or to 0 (the delay).
	 *
	 * @var string[]
	 */
	public const NUMBER_SETTING_KEYS = [ 'import_fragment_size', 'sleep_between_import_tasks' ];

	/**
	 * Normalize the submitted host-page assignments.
	 *
	 * A host page is a page ID, so the posts_page value is reduced to a digit string (the empty string when it is not one).
	 * It is the only value the plugin reads from an entry, so each entry is rebuilt from it alone; activation and earlier
	 * releases stored the full post type definitions here, and they are not kept. Entries that are not arrays are dropped,
	 * because the settings save reads posts_page from every entry. An entry that was submitted without posts_page stays
	 * without one here; the settings save then keeps the stored host page and does not re-parent any posts.
	 *
	 * @param array      $post_types The post_types sub-array from submitted settings input.
	 * @param array|null $registered The IDs of the registered post types, which are the only ones kept; null keeps every ID.
	 * @return array Post type settings with sanitized host-page IDs.
	 */
	public static function sanitize_post_type_settings( array $post_types, ?array $registered = null ): array {
		$clean = [];
		foreach ( $post_types as $post_type_id => $config ) {
			if ( ! is_array( $config ) || ( null !== $registered && ! in_array( $post_type_id, $registered, true ) ) ) {
				continue;
			}

			$entry = [];
			if ( array_key_exists( 'posts_page', $config ) ) {
				$page_id             = $config['posts_page'];
				$entry['posts_page'] = ( is_scalar( $page_id ) && ctype_digit( (string) $page_id ) ) ? (string) absint( $page_id ) : '';
			}
			$clean[ $post_type_id ] = $entry;
		}

		return $clean;
	}

	/**
	 * Check the shape of the submitted templates without changing their content.
	 *
	 * Templates are content strings by design, and only users with unfiltered_html can save them, so the content is not
	 * sanitised here. The plugin reads nothing from a template entry but its html value, so a new or changed entry is rebuilt
	 * from that value alone, and it must be a string: an array there would reach the template editor and the renderers as a
	 * value they cannot handle. An entry of any other shape keeps the stored entry, or is dropped when none is stored. An
	 * entry that equals the stored one is left as stored.
	 *
	 * @param mixed $templates Submitted templates, keyed by template ID.
	 * @param array $stored    Stored templates.
	 * @return array The templates; the stored templates when the submitted value is not an array.
	 */
	public static function sanitize_template_settings( $templates, array $stored = [] ): array {
		if ( ! is_array( $templates ) ) {
			return $stored;
		}

		$clean = [];
		foreach ( $templates as $template_id => $entry ) {
			if ( array_key_exists( $template_id, $stored ) && $stored[ $template_id ] === $entry ) {
				$clean[ $template_id ] = $entry;
			} elseif ( is_array( $entry ) && ( ! array_key_exists( 'html', $entry ) || is_string( $entry['html'] ) ) ) {
				$clean[ $template_id ] = array_key_exists( 'html', $entry ) ? [ 'html' => $entry['html'] ] : [];
			} elseif ( isset( $stored[ $template_id ] ) && is_array( $stored[ $template_id ] ) ) {
				$clean[ $template_id ] = $stored[ $template_id ];
			}
		}

		return $clean;
	}

	/**
	 * Normalize the submitted filter rename, exclude and show-only rules.
	 *
	 * The input and result have the same shape: group, filter, rule ID, then the posted fields (filteraction,
	 * filteroldvalue, filternewvalue). In the result:
	 * - A rule keeps only those three fields, and a malformed rule, filter or group is dropped.
	 * - filteraction is rename, exclude or showonly; any other value becomes an empty string.
	 * - filternewvalue is plain text.
	 * - filteroldvalue is unchanged, because it is matched exactly against imported values. A rule is kept only when its old
	 *   value is blank or one of the values the lookup offers for its filter; any other rule is dropped.
	 * - A filter for which the lookup returns null keeps no rules, whatever group it is in.
	 * - A group named like one of the generated hide or show-only lists is dropped.
	 *
	 * @param mixed    $groups                        Submitted filter settings.
	 * @param callable $get_filter_offered_old_values Returns the old values the settings dropdown offers for a filter, as
	 *                                                strings, or null when no dropdown offers the filter:
	 *                                                callable( string $filter ): ?string[]. It is called for every submitted
	 *                                                filter and group, so the caller should keep its answers, and it is the
	 *                                                only way this method learns the valid values.
	 * @return array The normalized rules; an input that is not an array gives an empty array.
	 */
	public static function sanitize_filter_rules( $groups, callable $get_filter_offered_old_values ): array {
		$clean = [];
		if ( ! is_array( $groups ) ) {
			return $clean;
		}

		foreach ( $groups as $group_name => $group ) {
			// A rule in a reserved group would collide with the hide and show-only lists the options builder writes.
			if ( ! is_array( $group ) || in_array( $group_name, self::RESERVED_FILTER_GROUPS, true ) ) {
				continue;
			}
			foreach ( $group as $filter_name => $rules ) {
				if ( ! is_array( $rules ) ) {
					continue;
				}
				// The values come from the caller so this class makes no database calls.
				$offered = $get_filter_offered_old_values( (string) $filter_name );
				if ( ! is_array( $offered ) ) {
					continue;
				}
				foreach ( $rules as $rule_id => $rule ) {
					if ( ! is_array( $rule ) ) {
						continue;
					}
					$action    = ( isset( $rule['filteraction'] ) && is_scalar( $rule['filteraction'] ) ) ? (string) $rule['filteraction'] : '';
					$old_value = ( isset( $rule['filteroldvalue'] ) && is_scalar( $rule['filteroldvalue'] ) ) ? (string) $rule['filteroldvalue'] : '';
					// Not cleaned as text, which could turn a valid value into one that no longer matches an imported value. A
					// value the dropdown could not have posted is dropped instead. The blank value is the form's empty row.
					if ( '' !== $old_value && ! in_array( $old_value, $offered, true ) ) {
						continue;
					}
					$new_value = ( isset( $rule['filternewvalue'] ) && is_scalar( $rule['filternewvalue'] ) ) ? sanitize_text_field( (string) $rule['filternewvalue'] ) : '';

					$clean[ $group_name ][ $filter_name ][ $rule_id ] = [
						'filteraction'   => in_array( $action, [ 'rename', 'exclude', 'showonly' ], true ) ? $action : '',
						'filteroldvalue' => $old_value,
						'filternewvalue' => $new_value,
					];
				}
			}
		}

		return $clean;
	}

	/**
	 * Normalize the submitted region IDs: trimmed, upper case, and only IDs that match REGION_ID_PATTERN.
	 *
	 * An ID that does not match (a quote, tag, slash, colon, space, non-ASCII character, or more than 16 characters) is not a
	 * valid code, so the whole ID is rejected (the empty string) rather than cleaned or cut into a different one.
	 *
	 * @param mixed $values Submitted region IDs.
	 * @return array Region IDs with the array keys kept; a non-array input gives an empty array, and a non-scalar or invalid value an empty string.
	 */
	public static function sanitize_region_ids( $values ): array {
		if ( ! is_array( $values ) ) {
			return [];
		}

		return array_map(
			static function ( $value ) {
				$region_id = is_scalar( $value ) ? trim( (string) $value ) : '';

				return 1 === preg_match( self::REGION_ID_PATTERN, $region_id ) ? strtoupper( $region_id ) : '';
			},
			$values
		);
	}

	/**
	 * Whether any submitted region ID is not blank and does not match REGION_ID_PATTERN, so sanitize_region_ids() rejects it.
	 *
	 * @param mixed $values Submitted region IDs.
	 * @return bool
	 */
	public static function has_invalid_region_id( $values ): bool {
		if ( ! is_array( $values ) ) {
			return false;
		}

		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$region_id = trim( (string) $value );
			if ( '' !== $region_id && 1 !== preg_match( self::REGION_ID_PATTERN, $region_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize a list of plain-text values, keeping the array keys.
	 *
	 * @param mixed $values Submitted values.
	 * @return array Sanitized values; a non-array input gives an empty array and a non-scalar value an empty string.
	 */
	public static function sanitize_text_values( $values ): array {
		if ( ! is_array( $values ) ) {
			return [];
		}

		return array_map(
			static function ( $value ) {
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
			},
			$values
		);
	}

	/**
	 * Sanitize the plain-text settings that were edited.
	 *
	 * A setting that was not submitted stays absent, a value that is unchanged from the stored one is left as stored, and any
	 * other setting is left as it is.
	 *
	 * @param array $input  Submitted settings.
	 * @param array $stored Stored settings.
	 * @return array The settings, with each edited plain-text value reduced to plain text and a non-scalar value emptied.
	 */
	public static function sanitize_plain_text_settings( array $input, array $stored = [] ): array {
		foreach ( self::TEXT_SETTING_KEYS as $key ) {
			if ( array_key_exists( $key, $input ) && ! self::is_unchanged( $input[ $key ], $stored, $key ) ) {
				$input[ $key ] = is_scalar( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : '';
			}
		}

		return $input;
	}

	/**
	 * Normalize the submitted import callback host to an http or https URL.
	 *
	 * @param mixed $value Submitted host or URL.
	 * @return string The URL, or the empty string when the value is not scalar, is empty after cleaning, or is not a valid http or https URL.
	 */
	public static function sanitize_callback_host( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$host = trim( (string) $value );
		// Strip any characters outside the RFC 3986 URL character set.
		$host = (string) preg_replace( '/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/', '', $host );
		if ( empty( $host ) ) {
			return '';
		}

		if ( false === strpos( $host, '://' ) ) {
			$host = 'https://' . $host;
		}

		$scheme = strtolower( (string) wp_parse_url( $host, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || false === filter_var( $host, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		return esc_url_raw( $host );
	}

	/**
	 * Normalize supported log-retention values in a settings array.
	 *
	 * Unsupported values are preserved unchanged so invalid input is never mistaken
	 * for a valid retention change.
	 *
	 * @param array $input Settings array.
	 * @return array Settings array with supported log-retention values normalized.
	 */
	public static function sanitize_log_retention_max_rows( array $input ): array {
		if ( array_key_exists( 'log_retention_max_rows', $input ) && Logger::is_supported_retention_max_rows( $input['log_retention_max_rows'] ) ) {
			$input['log_retention_max_rows'] = Logger::normalize_retention_max_rows( $input['log_retention_max_rows'] );
		}

		return $input;
	}

	/**
	 * Normalize supported diagnostic log level values in a settings array.
	 *
	 * Unsupported values are preserved unchanged so invalid input is never mistaken
	 * for an intentional diagnostic level change.
	 *
	 * @param array $input Settings array.
	 * @return array Settings array with supported diagnostic level values normalized.
	 */
	public static function sanitize_log_diagnostic_level( array $input ): array {
		if ( array_key_exists( Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY, $input ) && Logger::is_supported_diagnostic_level( $input[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] ) ) {
			$input[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] = Logger::normalize_diagnostic_level( $input[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] );
		}

		return $input;
	}

	/**
	 * Sanitize the settings that hold a single value, whichever setting it is.
	 *
	 * Each value that is new or changed is handled by its type:
	 * - A string for a setting that has a rule must satisfy it exactly as submitted. A rule is a callable that takes the string
	 *   and returns the string to store, or null to reject it; one_of() builds one from a list of accepted values. A rule that
	 *   is not callable, or returns anything but a string or null, rejects the value. A rejected string is not stored: the
	 *   stored value is kept, or the setting is left out when none is stored.
	 * - A string for a setting without a rule is reduced to plain text. That is only right for a setting that nothing
	 *   validates later, such as a key the plugin does not know. A setting that a later check accepts or rejects (a toggle,
	 *   a number, a closed set of values) needs a rule, because cleaning a malformed value into a valid one would turn it
	 *   into an accepted change, for example a tag-wrapped log retention limit into a valid limit.
	 * - A number or boolean cannot carry markup and keeps its type, whatever the rule. Raw text is the exception: it must be a
	 *   string, so any other changed value is rejected.
	 * - An array, object or null is rejected in the same way as a rejected string.
	 *
	 * A value that equals the stored one (scalars compare as strings) is left exactly as submitted and no rule is applied, so
	 * a legacy value that the form posts back unchanged is never rejected or rewritten, whatever type or shape an earlier
	 * release stored it as.
	 *
	 * Settings that hold structures or form commands are skipped, and raw text is stored as submitted if it is a string; see the
	 * *_SETTING_KEYS constants.
	 *
	 * @param array                $input                Submitted settings.
	 * @param array                $stored               Stored settings.
	 * @param array<string, mixed> $rules_by_setting_key Rules keyed by setting name, each a callable. See above.
	 * @return array The submitted settings, with each single-value setting sanitised or rejected.
	 */
	public static function sanitize_scalar_settings( array $input, array $stored = [], array $rules_by_setting_key = [] ): array {
		$exempt = array_merge( self::STRUCTURED_SETTING_KEYS, self::FORM_COMMAND_KEYS );

		foreach ( $input as $key => $value ) {
			if ( in_array( $key, $exempt, true ) || self::equals_stored( $value, $stored, $key ) ) {
				continue;
			}

			$is_raw = in_array( $key, self::RAW_SETTING_KEYS, true );

			// Raw text is stored as submitted, but it must still be a string.
			if ( $is_raw && is_string( $value ) ) {
				continue;
			}

			if ( is_string( $value ) ) {
				// Look for a rule for this setting, which does its own validation; without one, apply the default of plain text.
				if ( array_key_exists( $key, $rules_by_setting_key ) ) {
					$rule     = $rules_by_setting_key[ $key ];
					$accepted = is_callable( $rule ) ? call_user_func( $rule, $value ) : null;
					if ( ! is_string( $accepted ) ) {
						$accepted = null;
					}
				} else {
					$accepted = sanitize_text_field( $value );
				}

				if ( null !== $accepted ) {
					$input[ $key ] = $accepted;
					continue;
				}
			} elseif ( is_scalar( $value ) && ! $is_raw ) {
				continue;
			}

			if ( array_key_exists( $key, $stored ) ) {
				$input[ $key ] = $stored[ $key ];
			} else {
				unset( $input[ $key ] );
			}
		}

		return $input;
	}

	/**
	 * Rules for the single-value settings that a later check accepts or rejects, so that a malformed value is never cleaned
	 * into an accepted one: the on/off toggles, the numbers (a blank or non-numeric value is left for the settings save to
	 * reset), the callback host URL, the price choice (limited to the choices the settings screen offers) and the two logging
	 * settings, which reuse the logger's own validation.
	 *
	 * A setting without a rule is reduced to plain text, so every setting that the form posts must either have a rule here or
	 * be handled earlier in the settings save; a test checks this against the rendered settings screens. An empty set of price
	 * choices means they are not registered, so no price rule is applied rather than rejecting every value.
	 *
	 * @return array<string, callable> Rules for sanitize_scalar_settings(), keyed by setting name.
	 */
	public static function scalar_rules_by_setting_key(): array {
		$rules_by_setting_key = array_fill_keys( self::TOGGLE_SETTING_KEYS, [ self::class, 'resolve_toggle' ] );
		$rules_by_setting_key = array_merge( $rules_by_setting_key, array_fill_keys( self::NUMBER_SETTING_KEYS, [ self::class, 'resolve_number' ] ) );

		// The logger accepts a value after trimming, and its own normalisation runs again when the settings are saved.
		$rules_by_setting_key[ Logger::DIAGNOSTIC_VERBOSITY_SETTING_KEY ] = static function ( string $value ): ?string {
			return Logger::is_supported_diagnostic_level( $value ) ? $value : null;
		};
		$rules_by_setting_key['log_retention_max_rows']                   = static function ( string $value ): ?string {
			return Logger::is_supported_retention_max_rows( $value ) ? $value : null;
		};

		// The URL goes through its own sanitiser, which is idempotent, because plain-text sanitising would remove percent-encoded
		// characters from a valid URL. The settings save has already applied it; this keeps the rule with the setting.
		$rules_by_setting_key['import_callback_host'] = [ self::class, 'sanitize_callback_host' ];

		$price_choices = array_map(
			static function ( $price_key ) {
				return ARLO_PLUGIN_PREFIX . '-' . $price_key;
			},
			array_keys( \Arlo_For_Wordpress::$price_settings )
		);
		if ( ! empty( $price_choices ) ) {
			$rules_by_setting_key['price_setting'] = self::one_of( $price_choices );
		}

		return $rules_by_setting_key;
	}

	/**
	 * Build a rule that accepts only the given strings, exactly as submitted.
	 *
	 * Use it as a rule for sanitize_scalar_settings().
	 *
	 * @param string[] $accepted The accepted values.
	 * @return \Closure Takes the submitted string and returns it, or null when it is not one of the accepted values.
	 */
	public static function one_of( array $accepted ): \Closure {
		return static function ( string $value ) use ( $accepted ): ?string {
			return in_array( $value, $accepted, true ) ? $value : null;
		};
	}

	/**
	 * Resolve a submitted on/off choice: the only values a toggle accepts are 1, 0 and the empty string.
	 *
	 * It checks the string exactly as submitted, so a value that only becomes valid after trimming or cleaning is rejected.
	 * Use it as a rule for sanitize_scalar_settings().
	 *
	 * @param string $value Submitted value.
	 * @return string|null The value, or null when it is not a toggle value.
	 */
	public static function resolve_toggle( string $value ): ?string {
		return in_array( $value, [ '', '0', '1' ], true ) ? $value : null;
	}

	/**
	 * Resolve a submitted number for a setting that the settings save range-checks: a numeric string is kept, with ordinary
	 * surrounding whitespace removed, and anything else resolves to the empty string.
	 *
	 * A blank value is a real choice, because the settings save resets it to the default, so it must reach that reset rather
	 * than the stored value being restored. A value that is not a number gets the same reset, as it always did. It is never
	 * cleaned instead, because cleaning '<b>5</b>' would turn it into a valid 5. The range checks (a negative delay, a
	 * fragment size above the maximum) stay with the settings save.
	 *
	 * Use it as a rule for sanitize_scalar_settings().
	 *
	 * @param string $value Submitted value.
	 * @return string The trimmed numeric string, or the empty string.
	 */
	public static function resolve_number( string $value ): string {
		$trimmed = trim( $value, " \t\n\r" );

		return is_numeric( $trimmed ) ? $trimmed : '';
	}

	/**
	 * Whether a submitted value, of any type, equals what the setting already has.
	 *
	 * Scalars compare as strings, as is_unchanged() does. Anything else must be identical, so a stored array or null only
	 * matches itself.
	 *
	 * @param mixed      $submitted Submitted value.
	 * @param array      $stored    Stored settings.
	 * @param int|string $key       Setting name.
	 * @return bool
	 */
	private static function equals_stored( $submitted, array $stored, $key ): bool {
		if ( ! array_key_exists( $key, $stored ) ) {
			return false;
		}
		if ( is_scalar( $submitted ) ) {
			return is_scalar( $stored[ $key ] ) && (string) $submitted === (string) $stored[ $key ];
		}

		return $stored[ $key ] === $submitted;
	}

	/**
	 * Whether a submitted value is the value the setting already has.
	 *
	 * The settings form resubmits every field on each save, so a value that has not been edited is left exactly as it is
	 * stored, and saving the form does not change existing values unnecessarily.
	 *
	 * @param mixed  $submitted Submitted value.
	 * @param array  $stored    Stored settings.
	 * @param string $key       Setting name.
	 * @return bool
	 */
	private static function is_unchanged( $submitted, array $stored, string $key ): bool {
		return array_key_exists( $key, $stored )
			&& is_scalar( $submitted )
			&& is_scalar( $stored[ $key ] )
			&& (string) $submitted === (string) $stored[ $key ];
	}
}
