<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class NoticeHandler {

	private $message_handler;
	private $importer;
	private $settings;

	private static $message_notice_types = array(
		'import_error' => 'error',
		'information'  => 'notice-warning',
	);

	/**
	 * Message types that global_notices() renders on every admin screen.
	 *
	 * @var string[]
	 */
	private static $global_message_types = array( 'import_error', 'error' );

	public function __construct( $message_handler, $importer ) {
		$this->message_handler = $message_handler;
		$this->importer        = $importer;

		$this->settings = get_option( 'arlo_settings' );
	}

	public function global_notices() {
		$messages = array();
		foreach ( self::$global_message_types as $type ) {
			$messages = array_merge( $messages, $this->message_handler->get_messages( $type, true ) );
		}

		foreach ( $messages as $message ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
			echo $this->create_notice( $message );
		}
	}

	/**
	 * Whether any Arlo notice registered for every admin screen will render on this request.
	 *
	 * Admin asset loading uses this on admin_enqueue_scripts so notice styles and the
	 * dismissal script are only loaded outside Arlo screens when a notice is pending.
	 *
	 * This reads the callbacks registered on admin_notices rather than the stored options,
	 * because the options can change after registration: check_plugin_version() resets the
	 * disabled flags on admin_init once the system requirements pass. Call it after admin_init.
	 *
	 * @return bool
	 */
	public function has_global_notices() {
		foreach ( array( 'plugin_disabled', 'import_disabled', 'connection_health_disabled' ) as $callback ) {
			if ( false !== has_action( 'admin_notices', array( $this, $callback ) ) ) {
				return true;
			}
		}

		return false !== has_action( 'admin_notices', array( $this, 'global_notices' ) ) && $this->has_global_messages();
	}

	/**
	 * Whether global_notices() has at least one undismissed message to render.
	 *
	 * @return bool
	 */
	public function has_global_messages() {
		foreach ( self::$global_message_types as $type ) {
			if ( count( $this->message_handler->get_messages( $type, true ) ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns why the current permalink structure cannot route Arlo pages.
	 *
	 * Arlo host-page routing needs pretty permalinks that end with a slash and do not
	 * use WordPress's index.php (PATHINFO) rewrite mode. Prefixes and extra segments,
	 * such as the multisite /blog prefix or /%category%/, are supported.
	 *
	 * @return string 'plain', 'index_php', 'trailing_slash', or '' when the structure is supported.
	 */
	public function get_permalink_notice_reason() {
		global $wp_rewrite;

		$structure = get_option( 'permalink_structure', '' );
		$structure = is_string( $structure ) ? trim( $structure ) : '';

		if ( '' === $structure ) {
			return 'plain';
		}

		if ( is_object( $wp_rewrite ) && $wp_rewrite->using_index_permalinks() ) {
			return 'index_php';
		}

		return '/' !== substr( $structure, -1 ) ? 'trailing_slash' : '';
	}

	/**
	 * Whether the permalink notice is registered for this request.
	 *
	 * @return bool
	 */
	public function should_show_permalink_notice() {
		return '' !== $this->get_permalink_notice_reason();
	}

	/**
	 * Whether the plugin-disabled notice is registered for this request.
	 *
	 * @return bool
	 */
	public function should_show_plugin_disabled() {
		return '1' === (string) get_option( 'arlo_plugin_disabled', '0' );
	}

	/**
	 * Whether the import-disabled notice is registered for this request.
	 *
	 * The plugin-disabled notice takes precedence when both flags are set.
	 *
	 * @return bool
	 */
	public function should_show_import_disabled() {
		return ! $this->should_show_plugin_disabled() && '1' === (string) get_option( 'arlo_import_disabled', '0' );
	}

	/**
	 * Whether the connection-health-disabled notice is registered for this request.
	 *
	 * @return bool
	 */
	public function should_show_connection_health_disabled() {
		if ( '1' !== (string) get_option( 'arlo_import_connection_health_disabled', '0' ) ) {
			return false;
		}

		$settings = get_option( 'arlo_settings', [] );

		return ( is_array( $settings ) ? ( $settings['import_connection_healthchecks_enabled'] ?? '1' ) : '1' ) === '1';
	}

	public function arlo_notices() {
		$messages = $this->message_handler->get_messages( null, false );

		foreach ( $messages as $message ) {
			echo $this->create_notice( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
		}
	}


	public function create_notice( $message ) {
		if ( ! is_object( $message ) ) {
			return '';
		}

		$type           = ( isset( $message->type ) && is_scalar( $message->type ) ) ? (string) $message->type : '';
		$class          = ( isset( $message->class ) && is_scalar( $message->class ) ) ? (string) $message->class : '';
		$id             = ( isset( $message->id ) && is_scalar( $message->id ) ) ? (string) $message->id : '';
		$title          = ( isset( $message->title ) && is_scalar( $message->title ) ) ? (string) $message->title : '';
		$notice_type    = ( $type !== '' && isset( self::$message_notice_types[ $type ] ) ? self::$message_notice_types[ $type ] : ( $type !== '' ? $type : 'error' ) );
		$notice_message = ( isset( $message->message ) && is_scalar( $message->message ) ) ? wp_kses( (string) $message->message, self::get_notice_body_allowed_html() ) : '';

		$global_message = '';
		if ( ! empty( $message->global ) ) {
			$global_message = '<td class="logo" valign="top" style="width: 60px; padding-top: 1em;">
						<a href="https://www.arlo.co" target="_blank" class="arlo-logo"></a>
					</td>';
		}

		// $notice_message is sanitised with wp_kses() above, so this returned admin-notice HTML intentionally preserves the allowed markup.
		return '
		<div class="' . esc_attr( 'notice ' . $notice_type . ' ' . $class . ' arlo-message ' . ( isset( $message->is_dismissable ) && $message->is_dismissable ? 'is-dismissible' : '' ) . ( $type !== '' ? ' arlo-' . $type : '' ) ) . '" ' .
		( $id !== '' ? 'id="' . esc_attr( $id ) . '"' : '' ) . '>
			<table>
				<tr>
					' . $global_message . '
					<td>
						' . ( $title !== '' ? '<p><strong>' . esc_html( $title ) . '</strong></p>' : '' ) . '
						' . $notice_message . '
					</td>
				</tr>
			</table>
	    </div>
		';
	}

	public function create_user_notice( $notice_key, $message_obj ) {
		$notice_id = \Arlo_For_Wordpress::$dismissible_notices[ $notice_key ];
		$user      = wp_get_current_user();
		$meta      = get_user_meta( $user->ID, $notice_id, true );

		if ( is_null( $message_obj->id ) ) {
			$message_obj->id = $notice_id;
		}

		if ( empty( $message_obj->class ) ) {
			$message_obj->class = '';
		}

		$message_obj->class .= ' arlo-user-dismissable-message';

		if ( $meta !== '0' ) {
			return $this->create_notice( $message_obj );
		}

		return '';
	}

	public function create_message_object( $title = '', $message = '', $type = null, $global = false, $is_dismissable = false, $id = null, $class = null ) {
		$message_obj = new \stdClass();

		$message_obj->title          = $title;
		$message_obj->message        = $message;
		$message_obj->type           = $type;
		$message_obj->global         = $global;
		$message_obj->is_dismissable = $is_dismissable;
		$message_obj->id             = $id;
		$message_obj->class          = $class;

		return $message_obj;
	}

	public function dismiss_user_notice( $notice_key = '' ) {
		if ( ! empty( $notice_key ) && in_array( $notice_key, \Arlo_For_Wordpress::$dismissible_notices, true ) ) {
			$user = wp_get_current_user();
			$id   = \ArloTraining\Utilities::filter_string_polyfill( INPUT_POST, 'id' );
			update_user_meta( $user->ID, $id, 0 );
		}
	}

	public function permalink_notice() {
		$link = esc_url( admin_url( 'options-permalink.php' ) );

		switch ( $this->get_permalink_notice_reason() ) {
			case 'index_php':
				/* translators: %s: link url */
				$text = __( 'Arlo for WordPress does not support <a target="_blank" href="%s">Permalinks</a> using WordPress index.php (PATHINFO) mode. Choose a pretty permalink structure without the leading index.php prefix, such as "Post name". Enable URL rewriting on your server first if needed.', 'arlo-training-and-event-management-system' );
				break;
			case 'trailing_slash':
				/* translators: %s: link url */
				$text = __( 'Arlo for WordPress requires your <a target="_blank" href="%s">Permalinks</a> structure to end with a slash. Choose "Post name", or add a trailing "/" to your custom structure.', 'arlo-training-and-event-management-system' );
				break;
			default:
				/* translators: %s: link url */
				$text = __( 'Arlo for WordPress requires pretty <a target="_blank" href="%s">Permalinks</a> that end with a slash. Choose "Post name" or another structure ending in "/", not "Plain" or "Numeric".', 'arlo-training-and-event-management-system' );
		}

		$message_obj = $this->create_message_object(
			esc_html__( 'Permalink setting change required.', 'arlo-training-and-event-management-system' ),
			'<p>' .
				wp_kses(
					sprintf( $text, $link ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
						],
					]
				) . '</p>',
			'error notice'
		);

		echo $this->create_notice( $message_obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function plugin_disabled() {
		$message = '<p>' . wp_kses(
			/* translators: 1 : link url 2 : css class */
			sprintf( __( 'The Arlo for WordPress plugin has been disabled, please check the <a href="%1$s" class="%2$s">System Requirements</a> page', 'arlo-training-and-event-management-system' ), esc_url( admin_url( 'admin.php?page=arlo-for-wordpress#systemrequirements' ) ), 'arlo-pages-systemrequirements' ),
			[
				'a' => [
					'href'  => [],
					'class' => [],
				],
			]
		) . '</p>';

		$message_obj = $this->create_message_object(
			esc_html__( 'Plugin disabled', 'arlo-training-and-event-management-system' ),
			$message,
			'error',
			true,
			false
		);

		echo $this->create_notice( $message_obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function demo_platform_notice() {
		if ( ! is_array( $this->settings ) || empty( $this->settings['platform_name'] ) || strtolower( (string) $this->settings['platform_name'] ) !== \Arlo_For_Wordpress::DEFAULT_PLATFORM ) {
			return;
		}

		$settings_url = esc_url( admin_url( 'admin.php?page=arlo-for-wordpress#general' ) );
		$message      = '<p>'
			. esc_html__( 'Your site is currently using demo event, presenter, and venue data. Start an Arlo trial to load your own events!', 'arlo-training-and-event-management-system' )
			. '</p>'
			. '<p>'
			. '<a class="button button-primary" href="https://www.arlo.co/register">' . esc_html__( 'Get started with free Arlo trial', 'arlo-training-and-event-management-system' ) . '</a> '
			. '<a class="button button-primary arlo-block" href="' . $settings_url . '" id="arlo-connet-platform">' . esc_html__( 'Connect existing Arlo platform', 'arlo-training-and-event-management-system' ) . '</a>'
			. '</p>'
			. '<p>'
			. wp_kses(
				__( '<a href="https://developer.arlo.co/doc/wordpress/index" target="_blank">Learn how to use</a> Arlo for WordPress or visit <a href="https://www.arlo.co" target="_blank">www.arlo.co</a> to find out more about Arlo.', 'arlo-training-and-event-management-system' ),
				[
					'a' => [
						'href'   => [],
						'target' => [],
					],
				]
			)
			. '</p>';

		$message_obj = $this->create_message_object(
			esc_html__( 'Connected to demo data', 'arlo-training-and-event-management-system' ),
			$message,
			'notice',
			true,
			false,
			'arlo-demo-platform-message',
			'updated'
		);

		echo $this->create_notice( $message_obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function connection_health_disabled() {
		$disabled_since   = get_option( 'arlo_import_disabled_since', '' );
		$disabled_message = get_option( 'arlo_import_disabled_message', '' );

		$time_ago = '';
		if ( ! empty( $disabled_since ) ) {
			$from = strtotime( $disabled_since . ' UTC' );
			if ( $from !== false ) {
				$diff_secs = time() - $from;
				$time_ago  = $diff_secs < 60
					? __( 'just now', 'arlo-training-and-event-management-system' )
					: sprintf(
						/* translators: %s: human-readable time difference, e.g. "2 hours" */
						__( '%s ago', 'arlo-training-and-event-management-system' ),
						human_time_diff( $from, time() )
					);
			}
		}

		$arlo_settings = get_option( 'arlo_settings', [] );
		$platform_host = ! empty( $arlo_settings['platform_name'] ) ? esc_html( $arlo_settings['platform_name'] ) . '.arlo.co' : esc_html__( 'the Arlo platform', 'arlo-training-and-event-management-system' );

		$text = ! empty( $time_ago )
			? sprintf(
				/* translators: 1: platform hostname e.g. "example.arlo.co", 2: relative time e.g. "2 hours ago" */
				esc_html__( 'Disconnected from %1$s %2$s due to repeated platform access failures.', 'arlo-training-and-event-management-system' ),
				$platform_host,
				esc_html( $time_ago )
			)
			: sprintf(
				/* translators: %s: platform hostname e.g. "example.arlo.co" */
				esc_html__( 'Disconnected from %s due to repeated platform access failures.', 'arlo-training-and-event-management-system' ),
				$platform_host
			);

		if ( ! empty( $disabled_message ) ) {
			$text .= '<br>' . esc_html__( 'Reason:', 'arlo-training-and-event-management-system' ) . ' ' . esc_html( $disabled_message );
		}

		$settings_url  = esc_url( admin_url( 'admin.php?page=arlo-for-wordpress#general' ) );
		$logs_url      = esc_url( admin_url( 'admin.php?page=arlo-for-wordpress-logs' ) );
		$reconnect_url = esc_url( wp_nonce_url( admin_url( 'admin.php?page=arlo-for-wordpress&arlo-reconnect' ), 'arlo-reconnect' ) );

		$links = sprintf(
			'<a href="%1$s">%2$s</a><a href="%3$s">%4$s</a><a href="%5$s">%6$s</a>',
			$reconnect_url,
			esc_html__( 'Reconnect', 'arlo-training-and-event-management-system' ),
			$settings_url,
			esc_html__( 'View settings', 'arlo-training-and-event-management-system' ),
			$logs_url,
			esc_html__( 'View logs', 'arlo-training-and-event-management-system' )
		);

		$message_obj = $this->create_message_object(
			esc_html__( 'Disconnected from platform', 'arlo-training-and-event-management-system' ),
			'<p>' . $text . '</p><p>' . $links . '</p>',
			'error',
			true,
			true,
			null,
			'arlo-health-disabled-notice'
		);

		echo $this->create_notice( $message_obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function import_disabled() {
		$message = '<p>' . wp_kses(
			/* translators: 1 : link url 2 : css class */
			sprintf( __( 'The import for the Arlo for WordPress plugin has been disabled, but the current existing data is still available. Please check the <a href="%1$s" class="%2$s">System Requirements</a> page', 'arlo-training-and-event-management-system' ), esc_url( admin_url( 'admin.php?page=arlo-for-wordpress#systemrequirements' ) ), 'arlo-pages-systemrequirements' ),
			[
				'a' => [
					'href'  => [],
					'class' => [],
				],
			]
		) . '</p>';

		$message_obj = $this->create_message_object(
			esc_html__( 'Import disabled', 'arlo-training-and-event-management-system' ),
			$message,
			'error',
			true,
			false
		);

		echo $this->create_notice( $message_obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}


	/**
	 * Displays the host-page setup notice.
	 *
	 * Shows context-aware content based on what still needs to be done:
	 * - Pages with no assignment or a trashed page are listed under "Finish setup".
	 * - Pages assigned but not yet published are listed under "Review these pages".
	 *
	 * @param string[]                                            $needs_setup     Post-type singular names whose posts_page slot is
	 *                                                                             empty or points to a trashed/missing page.
	 * @param array<int,array{id:int,title:string,status:string}> $needs_publishing Entries for assigned-but-unpublished pages,
	 *                                                                             keyed by page ID.
	 */
	public function posttype_notice( array $needs_setup = [], array $needs_publishing = [] ) {
		$body = '';

		if ( ! empty( $needs_setup ) ) {
			$body .= '<p><strong>' .
				esc_html__( 'Complete setup for these pages in the Pages tab:', 'arlo-training-and-event-management-system' ) .
			'</strong></p><ul>';
			foreach ( $needs_setup as $name ) {
				$body .= '<li>' . esc_html( $name ) . '</li>';
			}
			$body .= '</ul>';
		}

		if ( ! empty( $needs_publishing ) ) {
			$body .= '<p><strong>' .
				wp_kses(
					/* translators: %s: WP pages list URL */
					sprintf( __( '<a href="%s">Review</a> these pages to make them live:', 'arlo-training-and-event-management-system' ), esc_url( admin_url( 'edit.php?post_type=page' ) ) ),
					[ 'a' => [ 'href' => [] ] ]
				) .
			'</strong></p><ul>';
			foreach ( $needs_publishing as $page ) {
				$status_label  = self::get_unpublished_page_status_label( $page['status'] );
				$display_title = trim( $page['title'] ) !== ''
					? esc_html( $page['title'] )
					/* translators: %d: WordPress page ID */
					: esc_html( sprintf( __( '#%d (no title)', 'arlo-training-and-event-management-system' ), $page['id'] ) );
				$body .= '<li>' . $display_title . ' (' . esc_html( $status_label ) . ')</li>';
			}
			$body .= '</ul>';
		}

		$message_obj = $this->create_message_object(
			null,
			$body . '<p>' .
				wp_kses(
					/* translators: %s: documentation link */
					sprintf( __( '<a target="_blank" href="%s">View documentation</a> for more information.', 'arlo-training-and-event-management-system' ), 'https://developer.arlo.co/doc/wordpress/index#pages-and-post-types' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
						],
					]
				) .
			'</p>',
			'notice-warning',
			false,
			true
		);

		echo $this->create_user_notice( 'pagesetup', $message_obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- User admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	/**
	 * Offers the public credit opt-in to undecided sites with credit-bearing templates.
	 *
	 * Shown only on the Arlo settings screen, to users who can manage options, until a choice is recorded.
	 * Ticking the box opts in, and dismissing the banner records "no". Nothing is shown publicly without the tick.
	 *
	 * @return void
	 */
	public function powered_by_opt_in_notice() {
		$settings = get_option( 'arlo_settings', [] );
		if ( ! is_array( $settings ) || array_key_exists( \Arlo_For_Wordpress::POWERED_BY_SETTING, $settings ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Arlo shortcodes, including the credit, render only while an import is live.
		if ( empty( $this->importer->get_current_import_id() ) ) {
			return;
		}

		if ( ! \Arlo_For_Wordpress::templates_contain_powered_by_credit( $settings ) ) {
			return;
		}

		$was_enabled = '1' === get_option( \Arlo_For_Wordpress::POWERED_BY_UPGRADE_NOTICE_OPTION );

		$lead = '';
		if ( $was_enabled ) {
			$lead = '<p><strong>' . sprintf(
				/* translators: %s: the product credit name in italics. */
				esc_html__( '%s is now off by default.', 'arlo-training-and-event-management-system' ),
				'<em>' . esc_html__( 'Powered by Arlo', 'arlo-training-and-event-management-system' ) . '</em>'
			) . '</strong> <span class="arlo-powered-by-opt-in-pill">' . esc_html__( 'Previously enabled', 'arlo-training-and-event-management-system' ) . '</span></p>';
		}

		// Inline preview of the public credit, in place of the quoted name, so the label shows what learners will see.
		$credit = '<span class="arlo-powered-by-opt-in-credit"><span class="arlo-powered-by-opt-in-credit-content">' . esc_html__( 'Powered by ', 'arlo-training-and-event-management-system' )
			. '<img src="' . esc_url( ARLO_PLUGIN_ROOT_URL . 'public/assets/img/Arlo-logo.svg' ) . '" alt="' . esc_attr__( 'Arlo', 'arlo-training-and-event-management-system' ) . '" width="27" height="27"></span></span>';
		/* translators: %s: the "Powered by" text followed by the Arlo logo, as it appears on the public site. */
		$label = sprintf( esc_html__( 'Show learners they\'re booking on a fast and secure platform with a small %s link to arlo.co.', 'arlo-training-and-event-management-system' ), $credit );

		echo '<div id="arlo-powered-by-opt-in-notice" class="notice notice-info is-dismissible">'
			. '<div class="arlo-powered-by-opt-in-prompt">'
			. $lead // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $lead is built above from esc_html__() strings and static markup.
			. '<p><label><input type="checkbox" id="arlo-powered-by-opt-in" aria-describedby="arlo-powered-by-opt-in-description"> ' . $label . '</label></p>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $label is built above from esc_html__() strings and the escaped credit markup.
			. '<p id="arlo-powered-by-opt-in-description" class="description">' . esc_html__( 'This appears on Arlo pages and widgets where the template contains the shortcode.', 'arlo-training-and-event-management-system' ) . '</p>'
			. '</div>'
			. '<p class="arlo-powered-by-opt-in-saved" role="status" hidden><span class="dashicons dashicons-yes" aria-hidden="true"></span> '
			. sprintf(
				/* translators: %s: the product credit name in italics. */
				esc_html__( 'Thanks! %s is now shown on your Arlo pages. You can change this any time in Arlo > Settings > Misc.', 'arlo-training-and-event-management-system' ),
				'<em>' . esc_html__( 'Powered by Arlo', 'arlo-training-and-event-management-system' ) . '</em>'
			) . '</p>'
			. '<p class="arlo-powered-by-opt-in-failed" role="alert" hidden>'
			. esc_html__( 'Your choice could not be saved. Please try again, or reload this page and change the setting in Arlo > Settings > Misc.', 'arlo-training-and-event-management-system' ) . '</p>'
			// An existing dismiss button prevents WordPress from attaching its immediate notice-removal handler.
			. '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'arlo-training-and-event-management-system' ) . '</span></button>'
			. '</div>';
	}

	public function welcome_notice() {

		$demo_connect_error = UserFlash::take( UserFlash::DEMO_CONNECT_ERROR );
		if ( is_string( $demo_connect_error ) && '' !== $demo_connect_error ) {
			echo $this->create_notice( $this->create_message_object( null, '<p>' . esc_html( $demo_connect_error ) . '</p>', 'error', false, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. The message text is escaped above and create_notice() filters the body HTML with KSES.
		}

		$demo_setup_errors = UserFlash::take( UserFlash::DEMO_SETUP_ERRORS );
		$this->load_demo_notice( is_array( $demo_setup_errors ) ? array_map( 'sanitize_text_field', $demo_setup_errors ) : [] );
	}

	public function load_demo_notice( $error = [] ) {
		$import_id = $this->importer->get_current_import_id();

		$events     = arlo_get_post_by_name( 'events', 'page' );
		$schedule   = arlo_get_post_by_name( 'schedule', 'page' );
		$upcoming   = arlo_get_post_by_name( 'upcoming', 'page' );
		$presenters = arlo_get_post_by_name( 'presenters', 'page' );
		$venues     = arlo_get_post_by_name( 'venues', 'page' );

		if ( count( $error ) ) {

			echo $this->create_user_notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- User admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
				'newpages',
				$this->create_message_object( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The notice built here is output by create_user_notice() above, and create_notice() filters its body with wp_kses().
					null,
					$this->get_demo_setup_error_html( $error ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns markup escaped by get_demo_setup_error_html(); create_notice() also filters the body with wp_kses().
					'error notice',
					false,
					true
				)
			);
		} else {
			$notice_id = \Arlo_For_Wordpress::$dismissible_notices['newpages'];
			$user      = wp_get_current_user();
			$meta      = get_user_meta( $user->ID, $notice_id, true );

			if ( $meta !== '0' ) {
				if ( ! empty( $this->settings['platform_name'] ) && $events !== false && $schedule !== false && $upcoming !== false && $presenters !== false && $venues !== false && ! empty( $import_id ) ) {
					//Get the first event template wich has event
					global $wpdb;
					$event      = CacheControl::fetch_results(
						$wpdb->prepare(
							"
					SELECT
						ID
					FROM
						{$wpdb->prefix}arlo_events AS e
					LEFT JOIN
						{$wpdb->prefix}arlo_eventtemplates AS et
					ON
						e.et_arlo_id = et.et_arlo_id
					AND
						e.import_id = %d
					LEFT JOIN
						{$wpdb->prefix}posts
					ON
						et_post_id = ID
					AND
						post_status = 'publish'
					WHERE
						et.import_id = %d
					LIMIT
						1
					",
							$import_id,
							$import_id
						),
						ARRAY_A
					);
					$event_link = '';
					if ( count( $event ) ) {
						$event_link = sprintf(
							'<a href="%s" target="_blank">%s</a>,',
							esc_url( get_post_permalink( $event[0]['ID'] ) ),
							esc_html__( 'Event', 'arlo-training-and-event-management-system' )
						);
					}

					//Get the first presenter
					$presenter      = CacheControl::fetch_results(
						$wpdb->prepare(
							"
					SELECT
						ID
					FROM
						{$wpdb->prefix}arlo_presenters AS p
					LEFT JOIN
						{$wpdb->prefix}posts
					ON
						p_post_id = ID
					AND
						post_status = 'publish'
					WHERE
						p.import_id = %d
					LIMIT
						1
					",
							$import_id
						),
						ARRAY_A
					);
					$presenter_link = '';
					if ( count( $event ) && count( $presenter ) ) {
						$presenter_link = sprintf(
							'<a href="%s" target="_blank">%s</a>,',
							esc_url( get_post_permalink( $presenter[0]['ID'] ) ),
							esc_html__( 'Presenter profile', 'arlo-training-and-event-management-system' )
						);
					}

					//Get the first venue
					$venue      = CacheControl::fetch_results(
						$wpdb->prepare(
							"
					SELECT
						ID
					FROM
						{$wpdb->prefix}arlo_venues AS v
					LEFT JOIN
						{$wpdb->prefix}posts
					ON
						v_post_id = ID
					AND
						post_status = 'publish'
					WHERE
						v.import_id = %d
					LIMIT
						1
					",
							$import_id
						),
						ARRAY_A
					);
					$venue_link = '';
					if ( count( $event ) && count( $venue ) ) {
						$venue_link = sprintf(
							'<a href="%s" target="_blank">%s</a>,',
							esc_url( get_post_permalink( $venue[0]['ID'] ) ),
							esc_html__( 'Venue information', 'arlo-training-and-event-management-system' )
						);
					}

					echo $this->create_user_notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- User admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
						'newpages',
						$this->create_message_object( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The notice built here is output by create_user_notice() above, and create_notice() filters its body with wp_kses().
							esc_html__( 'Start editing your new pages', 'arlo-training-and-event-management-system' ),
							'<p>' . sprintf(
								/* translators: 1: Event link 2: Events link 3: Catalogue 4: Schedule link 5: Schedule 6: Upcoming link 7: Upcoming page name 8: Presenter page 9: presenters list link 10: Presenters list 11: Venue link 12: Venue list link 13: Venues list */
								__( 'View %1$s <a href="%2$s" target="_blank">%3$s</a>, <a href="%4$s" target="_blank">%5$s</a>, <a href="%6$s" target="_blank">%7$s</a>, %8$s <a href="%9$s" target="_blank">%10$s</a> %11$s or <a href="%12$s" target="_blank">%13$s</a> pages', 'arlo-training-and-event-management-system' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formatted HTML link output. Content is constructed from safe, escaped HTML strings.
								$event_link, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Event link HTML output. Content is constructed from static HTML and escaped values.
								esc_url( $events->guid ),
								esc_html__( 'Catalogue', 'arlo-training-and-event-management-system' ),
								esc_url( $schedule->guid ),
								esc_html__( 'Schedule', 'arlo-training-and-event-management-system' ),
								esc_url( $upcoming->guid ),
								esc_html( $upcoming->post_title ),
								$presenter_link, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Presenter link HTML output. Content is constructed from static HTML and escaped values.
								esc_url( $presenters->guid ),
								esc_html__( 'Presenters list', 'arlo-training-and-event-management-system' ),
								$venue_link, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Venue link HTML output. Content is constructed from static HTML and escaped values.
								esc_url( $venues->guid ),
								esc_html__( 'Venues list', 'arlo-training-and-event-management-system' )
							) . '</p><p>' . wp_kses(
								__( 'Edit the page <a href="#pages" class="arlo-pages-setup">templates</a> for each of these websites pages below.', 'arlo-training-and-event-management-system' ),
								[
									'a' => [
										'href'  => [],
										'class' => [],
									],
								]
							) . '</p>',
							'notice',
							false,
							true
						)
					);
				}
			}
		}
	}

	/**
	 * Lists the Arlo pages the demo setup left without a host page, and why.
	 *
	 * An existing page at an Arlo address is named, because the demo setup never publishes or assigns
	 * a page the admin did not choose.
	 *
	 * @param string[] $slugs Slugs of the Arlo pages that have no usable page assigned.
	 * @return string Notice body HTML, escaped.
	 */
	private function get_demo_setup_error_html( array $slugs ): string {
		$titles = [];
		foreach ( \Arlo_For_Wordpress::$pages as $page ) {
			$titles[ $page['name'] ] = $page['title'];
		}

		$items = '';
		foreach ( $slugs as $slug ) {
			$name     = esc_html( $titles[ $slug ] ?? $slug );
			$existing = get_page_by_path( $slug, OBJECT, 'page' );

			if ( $existing instanceof \WP_Post ) {
				$existing_title = trim( $existing->post_title ) !== ''
					? esc_html( $existing->post_title )
					/* translators: %d: WordPress page ID */
					: esc_html( sprintf( __( '#%d (no title)', 'arlo-training-and-event-management-system' ), $existing->ID ) );
				$status = 'publish' === $existing->post_status
					? __( 'published', 'arlo-training-and-event-management-system' )
					: self::get_unpublished_page_status_label( $existing->post_status );
				$items .= '<li>' . sprintf(
					/* translators: 1: Arlo page name, 2: page address, 3: title of the existing page, 4: status of the existing page, for example "draft". */
					esc_html__( '%1$s: your site already has a page at /%2$s/ ("%3$s", %4$s). It was not changed.', 'arlo-training-and-event-management-system' ),
					$name,
					esc_html( $slug ),
					$existing_title,
					esc_html( $status )
				) . '</li>';
			} else {
				$items .= '<li>' . sprintf(
					/* translators: %s: Arlo page name, for example "Events". */
					esc_html__( '%s: no page is assigned.', 'arlo-training-and-event-management-system' ),
					$name
				) . '</li>';
			}
		}

		$pages_link = '<a href="' . esc_url( admin_url( 'admin.php?page=arlo-for-wordpress#pages' ) ) . '" class="arlo-pages-setup">' . esc_html__( 'Arlo > Settings > Pages', 'arlo-training-and-event-management-system' ) . '</a>';

		return '<p><strong>' . esc_html__( 'These Arlo pages still need a host page:', 'arlo-training-and-event-management-system' ) . '</strong></p><ul>' . $items . '</ul><p>'
			. sprintf(
				/* translators: %s: link to the Arlo Pages settings tab. */
				esc_html__( 'Choose a host page for each of them in %s. To use an existing page, publish it first if it is a draft.', 'arlo-training-and-event-management-system' ),
				$pages_link
			) . '</p>';
	}

	private static function get_unpublished_page_status_label( $status ) {
		$status_labels = [
			'future'  => __( 'scheduled', 'arlo-training-and-event-management-system' ),
			'trash'   => __( 'deleted', 'arlo-training-and-event-management-system' ),
			'draft'   => __( 'draft', 'arlo-training-and-event-management-system' ),
			'pending' => __( 'pending', 'arlo-training-and-event-management-system' ),
			'private' => __( 'private', 'arlo-training-and-event-management-system' ),
		];

		return $status_labels[ $status ] ?? __( 'unpublished', 'arlo-training-and-event-management-system' );
	}

	private static function get_notice_body_allowed_html() {
		return array(
			'a'      => array(
				'class'  => array(),
				'href'   => array(),
				'id'     => array(),
				'target' => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'i'      => array(
				'class' => array(),
			),
			'li'     => array(),
			'ul'     => array(),
			'p'      => array(
				'class' => array(),
			),
			'span'   => array(

				'class' => array(),
				'id'    => array(),
			),
			'strong' => array(),
		);
	}
}
