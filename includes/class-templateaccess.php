<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Who may change the raw markup of Arlo templates.
 *
 * Templates are shortcode markup that prints on the public site, so like core's own raw HTML and CSS they can only be
 * edited by users who have `unfiltered_html`: administrators on a single site, super administrators on a multisite
 * network, and nobody when DISALLOW_UNFILTERED_HTML is set. Template source is never filtered or rewritten, only
 * withheld, because filtering shortcode markup would mangle it without protecting anything.
 *
 * Only a user's own edit is restricted. It always arrives through the Settings API form post, which WordPress handles
 * in wp-admin/options.php. Writes made by the plugin itself (activation, theme switching, upgrade steps, the default
 * template for a new custom shortcode) and requests with no signed-in user (WP-CLI, cron, upgrades) are not user edits
 * and are unaffected. Nothing the requester sends decides what counts as a user's edit.
 */
class TemplateAccess {

	/**
	 * Whether the signed-in user is barred from editing template source.
	 *
	 * @return bool True for a signed-in user without `unfiltered_html`; false for a user who has it, and for requests with no user.
	 */
	public static function restricts_current_user(): bool {
		return get_current_user_id() > 0 && ! current_user_can( 'unfiltered_html' );
	}

	/**
	 * The explanation shown with a template the current user cannot edit.
	 *
	 * The wording names DISALLOW_UNFILTERED_HTML when that constant is the cause, because granting a role the
	 * capability would not help there.
	 *
	 * @return string Translated plain text.
	 */
	public static function read_only_message(): string {
		if ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML ) {
			return __( 'This template is read only. This site turns off raw HTML editing (DISALLOW_UNFILTERED_HTML in wp-config.php), so templates can\'t be edited until that is changed.', 'arlo-training-and-event-management-system' );
		}
		return __( 'This template is read only. Only users who can add raw HTML to WordPress (on a multisite network, super administrators) can edit templates.', 'arlo-training-and-event-management-system' );
	}

	/**
	 * The settings to store, with any template changes reverted when they come from a user who may not make them.
	 *
	 * When the request is a restricted user's settings form post, the existing templates replace whatever was submitted
	 * for them, whether the form omitted them, resubmitted them or was forged to change them. The Settings API replaces
	 * the whole option with the posted values, so without this a form that omits the template fields would delete every
	 * stored template. Every other setting is returned as submitted. In all other cases the updated settings are
	 * returned unchanged.
	 *
	 * @param array $updated_settings  Settings about to be stored.
	 * @param array $existing_settings Previously saved settings.
	 * @return array The settings to store.
	 */
	public static function maybe_revert_template_changes( array $updated_settings, array $existing_settings ): array {
		if ( ! self::is_settings_form_post() || ! self::restricts_current_user() ) {
			return $updated_settings;
		}
		if ( isset( $existing_settings['templates'] ) && is_array( $existing_settings['templates'] ) ) {
			$updated_settings['templates'] = $existing_settings['templates'];
		} else {
			unset( $updated_settings['templates'] );
		}
		return $updated_settings;
	}

	/**
	 * Whether this request is WordPress handling a Settings API form post, the only route for user-typed template source.
	 *
	 * Core fires `load-options.php` for every request to wp-admin/options.php, after `admin_init` and before it
	 * verifies the nonce and capability and saves the posted options. That makes it a fact about which script is running
	 * that the requester cannot influence, unlike the option page named in the request, which core reads from
	 * `$_REQUEST` and sanitises, so any comparison of our own can be made to disagree with core.
	 *
	 * Because it fires after `admin_init`, upgrade steps that run there and legitimately rewrite templates are not
	 * mistaken for a user's edit, even when a user's save is the first admin request after an update.
	 *
	 * @return bool
	 */
	private static function is_settings_form_post(): bool {
		return did_action( 'load-options.php' ) > 0;
	}
}
