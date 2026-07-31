<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Arlo For Wordpress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      https://arlo.co
 * @copyright 2018 Arlo
 */

 use ArloTraining\Logger;
 use ArloTraining\VersionHandler;
 use ArloTraining\NoticeHandler;
 use ArloTraining\SystemRequirements;
 use ArloTraining\Utilities;
 use ArloTraining\Importer\ImportRequest;
 #[\AllowDynamicProperties]
class Arlo_For_Wordpress_Settings {

	public function __construct() {
		
		if (!session_id()) {
			session_start(['read_and_close' => true]);
		}
				
		// allocates the wp-options option key value pair that will store the plugin settings
		register_setting( 'arlo_settings', 'arlo_settings', array(
			'sanitize_callback' => function($input) {
				$region_id = (isset($input['regionid']) && is_array($input['regionid']) ? $input['regionid'] : []);
				$input['regionid'] = array_map('strtoupper', $region_id);

				if (isset($input['platform_name'])) {
					$old = get_option( 'arlo_settings', array() );
					try {
						$input['platform_name'] = Utilities::sanitize_and_validate_platform_name(
							$input['platform_name'],
							$old['platform_name'] ?? ''
						);
					} catch ( \ArloTraining\PlatformNameException $e ) {
						// The assignment in the try block threw before completing, so
						// $input['platform_name'] still holds the raw submitted value.
						// Apply the same normalisation used inside sanitize_and_validate_platform_name()
						// so the display name is clean, then esc_html() before embedding in any
						// message to guard against XSS via wp_kses_post().
						$display_name = rtrim(
							preg_replace( '#^https?://#i', '', trim( sanitize_text_field( wp_unslash( $input['platform_name'] ) ) ) ),
							'/'
						);
						$display_name = preg_replace( '#\.arlo\.co$#i', '', $display_name );
						$safe_name = esc_html( $display_name ) . '.arlo.co';
						$messages = [
							'invalid_format'       => __( 'Arlo platform name must contain only letters, numbers and hyphens (e.g. myplatform).', 'arlo-training-and-event-management-system' ),
							/* translators: %s: the platform URL the user typed, e.g. myplatform.arlo.co */
							'timeout'              => sprintf( __( "Arlo platform '%s' could not be reached — the connection timed out. Please check the name and try again.", 'arlo-training-and-event-management-system' ), $safe_name ),
							/* translators: %s: the platform URL the user typed, e.g. myplatform.arlo.co */
							'ssl_error'            => sprintf( __( "Could not establish a secure connection to Arlo platform '%s'. Please check the name and contact support if the problem persists.", 'arlo-training-and-event-management-system' ), $safe_name ),
							/* translators: %s: the platform URL the user typed, e.g. myplatform.arlo.co */
							'not_found'            => sprintf( __( "Arlo platform '%s' could not be found. Please check it and try again.", 'arlo-training-and-event-management-system' ), $safe_name ),
							/* translators: %s: the platform URL the user typed, e.g. myplatform.arlo.co */
							'redirect'             => sprintf( __( "Arlo platform '%s' appears to have moved to a new address. Open it in your browser to find the correct domain, then enter it here.", 'arlo-training-and-event-management-system' ), $safe_name ),
							/* translators: %s: the platform URL the user typed, e.g. myplatform.arlo.co */
							'server_error'         => sprintf( __( "Arlo platform '%s' returned an unexpected error. Please check the name and try again.", 'arlo-training-and-event-management-system' ), $safe_name ),
							/* translators: %s: the platform URL the user typed, e.g. myplatform.arlo.co */
							'unexpected_http'      => sprintf( __( "Arlo platform '%s' returned an unexpected response. Please check the name and try again.", 'arlo-training-and-event-management-system' ), $safe_name ),
							/* translators: %s: the platform URL the user typed, e.g. myplatform.arlo.co */
							'invalid_content_type' => sprintf( __( "Arlo platform '%s' returned an unexpected response. Please check the name and try again.", 'arlo-training-and-event-management-system' ), $safe_name ),
							/* translators: %s: the platform URL the user typed, e.g. myplatform.arlo.co */
							'request_failed'       => sprintf( __( "Could not reach Arlo platform '%s'. Please check the name and try again.", 'arlo-training-and-event-management-system' ), $safe_name ),
						];
						add_settings_error(
							'arlo_settings',
							'invalid_platform_name',
							$messages[ $e->getMessage() ] ?? $messages['request_failed']
						);
						$input['platform_name'] = $old['platform_name'] ?? '';
					}
				}

				if (!empty($input['import_callback_host'])) {
					$host = trim($input['import_callback_host']);
					// Strip any characters outside the RFC 3986 URL character set.
					$host = preg_replace('/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/', '', $host);
					if (empty($host)) {
						$input['import_callback_host'] = '';
					} else {
						if (strpos($host, '://') === false) {
							$host = 'https://' . $host;
						}
						$scheme = strtolower((string) wp_parse_url($host, PHP_URL_SCHEME));
						if (!in_array($scheme, array('http', 'https'), true) || filter_var($host, FILTER_VALIDATE_URL) === false) {
							$input['import_callback_host'] = '';
						} else {
							$input['import_callback_host'] = esc_url_raw($host);
						}
					}
				}
				$input['import_connection_healthchecks_enabled'] = isset($input['import_connection_healthchecks_enabled']) ? '1' : '0';
				$input['user_import_enabled'] = isset($input['user_import_enabled']) ? '1' : '0';

				// Sanitize deployment_mode.
				$allowed_modes = [ Arlo_For_Wordpress::DEPLOYMENT_MODE_PRODUCTION, Arlo_For_Wordpress::DEPLOYMENT_MODE_NON_PRODUCTION ];
				$submitted_mode = (isset($input['deployment_mode']) && in_array($input['deployment_mode'], $allowed_modes, true))
					? $input['deployment_mode']
					: Arlo_For_Wordpress::get_deployment_mode_default($input['platform_name'] ?? '');

				// First-save override: on a brand-new install deployment_mode is not yet
				// stored (set_default_options() intentionally omits it so the UI dropdown
				// shows Production as a safe unknown-state default). On the very first save,
				// if the submitted platform name is websitetestdata or empty we know this
				// is a demo/dev site and forcibly set non_production — regardless of what
				// the dropdown said. This prevents a fresh install from accidentally syncing
				// at full frequency just because the admin didn't notice the dropdown.
				// On all subsequent saves the key is already stored, so this block is skipped
				// and the admin's explicit dropdown choice is honoured as-is.
				$previously_stored = get_option('arlo_settings', []);
				$previously_stored = is_array($previously_stored) ? $previously_stored : [];
				if (!isset($previously_stored['deployment_mode'])) {
					$new_platform = is_scalar($input['platform_name'] ?? '') ? strtolower((string)($input['platform_name'] ?? '')) : '';
					if ($new_platform === '' || $new_platform === Arlo_For_Wordpress::DEFAULT_PLATFORM) {
						$submitted_mode = Arlo_For_Wordpress::DEPLOYMENT_MODE_NON_PRODUCTION;
					}
				}
				$input['deployment_mode'] = $submitted_mode;

				// Check for conflicting host page assignments.
				// If any are found, add a settings error for each.
				foreach ( $this->validate_post_page_mappings( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? $input['post_types'] : [] ) as $conflict ) {
					$labels  = array_column( $conflict['post_types'], 'label' );
					$message = wp_kses(
						sprintf(
							/* translators: 1: WordPress page title hyperlinked to the public page 2: comma-separated list of conflicting Arlo page type names */
							__( 'Host page %1$s is assigned to multiple conflicting Arlo page types (%2$s).<br>The page will not function correctly until the conflict is fixed.', 'arlo-training-and-event-management-system' ),
							'<a href="' . esc_url( $conflict['page_url'] ) . '" target="_blank" rel="noreferrer noopener">' . esc_html( $conflict['page_title'] ) . '</a>',
							esc_html( implode( ', ', $labels ) )
						),
						[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'br' => [] ]
					);
					add_settings_error( 'arlo_settings', 'duplicate_host_page_' . $conflict['page_id'], $message, 'warning' );
				}

				return $input;
			}
		));		

		$plugin = Arlo_For_Wordpress::get_instance();
		$settings_object = get_option('arlo_settings', []);

		$message_handler = $plugin->get_message_handler();
		$notice_handler = $plugin->get_notice_handler();		
		$this->plugin_slug = $plugin->get_plugin_slug();
		//This is just checking which menu the current page is, not a form submission.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Context implies safe execution. 	/wp-admin/admin.php?page=arlo-for-wordpress
		if (isset($_GET['page']) && sanitize_key($_GET['page']) == 'arlo-for-wordpress' && get_option('permalink_structure') != "/%postname%/") {
			add_action( 'admin_notices', array($notice_handler, "permalink_notice") );
		}
		add_action( 'admin_notices', array($notice_handler, "global_notices") );

		if (get_option('arlo_plugin_disabled', '0') == '1') {
			add_action( 'admin_notices', array($notice_handler, "plugin_disabled") );
		} else if (get_option('arlo_import_disabled', '0') == '1') {
			add_action( 'admin_notices', array($notice_handler, "import_disabled") );
		}

		if (get_option('arlo_import_connection_health_disabled', '0') == '1' && ($settings_object['import_connection_healthchecks_enabled'] ?? '1') === '1') {
			add_action( 'admin_notices', array($notice_handler, "connection_health_disabled") );
		}
		
		//This is shared class for all admin pages, just for checking which admin page the user is currently on. arlo-for-wordpress is menu slug 
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Context implies safe execution.  /wp-admin/admin.php?page=arlo-for-wordpress
		if(isset($_GET['page']) && sanitize_key($_GET['page']) == 'arlo-for-wordpress') {
			add_action( 'admin_notices', array($notice_handler, "arlo_notices") );

			add_action( 'admin_notices', array($notice_handler, "welcome_notice") );

			// Show success banner after "Add draft pages" action.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of result count from a nonce-verified action that already completed.
			$added_count = absint( \ArloTraining\Utilities::filter_string_polyfill( INPUT_GET, 'arlo-pages-added' ) );
			if ( $added_count > 0 ) {
				$pages_list_url = admin_url( 'edit.php?post_type=page&post_status=draft' );
				add_action( 'admin_notices', function() use ( $added_count, $pages_list_url, $notice_handler ) {
					$message_obj = $notice_handler->create_message_object(
						null,
						'<p>' .
							wp_kses(
								sprintf(
									/* translators: 1: number of draft pages created 2: URL to WP pages list */
									_n(
										'%1$d draft page added. <a href="%2$s">View pages</a> to publish them, then return here to assign each one.',
										'%1$d draft pages added. <a href="%2$s">View pages</a> to publish them, then return here to assign each one.',
										$added_count,
										'arlo-training-and-event-management-system'
									),
									$added_count,
									esc_url( $pages_list_url )
								),
								[ 'a' => [ 'href' => [] ] ]
							) .
						'</p>',
						'updated notice',
						false,
						false
					);
					echo $notice_handler->create_notice( $message_obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- create_notice filters body with KSES before output.
				} );
			}


			

			if (!empty($settings_object['platform_name']) && strtolower((string) $settings_object['platform_name']) === Arlo_For_Wordpress::DEFAULT_PLATFORM) {
				add_action( 'admin_notices', array($notice_handler, "demo_platform_notice") );
			}
			
			if (!empty($settings_object['platform_name'])) {
				$has_published_host_page = false;
				$needs_setup             = [];
				$needs_publishing        = [];
				$user_id = get_current_user_id();
				$pagesetup_notice_state = \Arlo_For_Wordpress::$dismissible_notices['pagesetup'] . '-broken-state';
				$was_pagesetup_broken = get_user_meta( $user_id, $pagesetup_notice_state, true ) === '1';
				foreach (Arlo_For_Wordpress::$post_types as $id => $post_type) {
					$page_id = \Arlo_For_Wordpress::get_posts_page_id( $id );
					if ( empty( $page_id ) ) {
						$needs_setup[] = $post_type['singular_name'];
						continue;
					}

					// A partial setup is allowed. Only show the global setup notice when none
					// of the configured host pages point to a published page at all.
					$assigned_page = get_post( $page_id );
					if ( ! $assigned_page || $assigned_page->post_status === 'trash' ) {
						// Trashed or deleted post counts the same as no assignment.
						$needs_setup[] = $post_type['singular_name'];
						continue;
					}
					if ( $assigned_page->post_status === 'publish' ) {
						$has_published_host_page = true;
						break;
					}
					// Assigned but not yet published (draft, pending, future, etc.).
					// Key by page ID so multiple post types sharing the same page produce only one entry.
					$needs_publishing[ $assigned_page->ID ] = [
						'id'     => $assigned_page->ID,
						'title'  => $assigned_page->post_title,
						'status' => $assigned_page->post_status,
					];
				}
				
				if ( ! $has_published_host_page ) {
					// Reset the dismissal only when the site newly enters a broken state,
					// so the notice remains meaningfully dismissible while still resurfacing
					// after a healthy setup later becomes broken again.
					if ( ! $was_pagesetup_broken ) {
						delete_user_meta( $user_id, \Arlo_For_Wordpress::$dismissible_notices['pagesetup'] );
					}
					update_user_meta( $user_id, $pagesetup_notice_state, '1' );
					add_action( 'admin_notices', function() use ( $notice_handler, $needs_setup, $needs_publishing ) {
						$notice_handler->posttype_notice( $needs_setup, $needs_publishing );
					} );
				} elseif ( $was_pagesetup_broken ) {
					delete_user_meta( $user_id, $pagesetup_notice_state );
				}
				
			}
			
			if (isset($_GET['arlo-download-sync-log']) && isset($_GET['_wpnonce']) && wp_verify_nonce(\ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, '_wpnonce'), 'arlo-download-sync-log')) {
				if (current_user_can('manage_options')) {
					Logger::render_log_csv_attachment();
				}
			}
		
			if (isset($_GET['arlo-import']) && isset($_GET['_wpnonce']) && wp_verify_nonce(\ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, '_wpnonce'), 'arlo-import')) {
				if (current_user_can('manage_options')) {
					// Intentionally not using is_import_enabled() — this is the "Synchronize now"
					// manual trigger, which must bypass user-toggle and connection-health gates so
					// admins can force a sync for diagnostic purposes. Only the hard system-requirements
					// flag (arlo_import_disabled) blocks it.
					if (get_option('arlo_import_disabled', '0') != '1')
						$plugin->get_scheduler()->set_task("import", -1);
				}
				wp_safe_redirect( admin_url( 'admin.php?page=arlo-for-wordpress'));
				exit;
			}

			if (isset($_GET['arlo-reconnect']) && isset($_GET['_wpnonce']) && wp_verify_nonce(\ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, '_wpnonce'), 'arlo-reconnect')) {
				// Clears the connection-health auto-disable flag only — does not touch the
				// user's manual auto-sync toggle (user_import_enabled). Also queues an
				// immediate import so the connection is tested straight away rather than
				// waiting for the next cron tick.
				if (current_user_can('manage_options')) {
					\Arlo_For_Wordpress::reset_connection_health();
					if (get_option('arlo_import_disabled', '0') != '1') {
						$plugin->get_scheduler()->set_task("import", -1);
					}
				}
				wp_safe_redirect( admin_url( 'admin.php?page=arlo-for-wordpress'));
				exit;
			}

			if (isset($_GET['arlo-enable-sync']) && isset($_GET['_wpnonce']) && wp_verify_nonce(\ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, '_wpnonce'), 'arlo-enable-sync')) {
				// Re-enables the user's manual auto-sync toggle and also clears the
				// connection-health flag in case both were set simultaneously.
				if (current_user_can('manage_options')) {
					$arlo_settings = get_option('arlo_settings', []);
					$arlo_settings['user_import_enabled'] = '1';
					update_option('arlo_settings', $arlo_settings);
					\Arlo_For_Wordpress::reset_connection_health();
				}
				wp_safe_redirect( admin_url( 'admin.php?page=arlo-for-wordpress'));
				exit;
			}
			
			
			if (isset($_GET['arlo-run-scheduler']) && isset($_GET['_wpnonce']) && wp_verify_nonce(\ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, '_wpnonce'), 'arlo-run-scheduler')) {
				if (current_user_can('manage_options')) {
					do_action('arlo_scheduler');
				}
				wp_safe_redirect( admin_url( 'admin.php?page=arlo-for-wordpress'));
				exit;				
			}

			if (isset($_GET['arlo-add-pages']) && isset($_GET['_wpnonce']) && wp_verify_nonce(\ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, '_wpnonce'), 'arlo-add-pages')) {
				$count = 0;
				if (current_user_can('manage_options')) {
					$result = Arlo_For_Wordpress::add_pages();
					Arlo_For_Wordpress::assign_posts_page_defaults( $result['pages'] );
					$count = $result['added_count'];
				}
				wp_safe_redirect( admin_url( 'admin.php?page=arlo-for-wordpress&arlo-pages-added=' . intval( $count ) ) );
				exit;
			}
			
			if (isset($_GET['load-demo']) && isset($_GET['_wpnonce']) && wp_verify_nonce(\ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, '_wpnonce'), 'arlo-load-demo')) {
				if (current_user_can('manage_options')) {
					$plugin->load_demo();
				}
				wp_safe_redirect( admin_url( 'admin.php?page=arlo-for-wordpress'));
				exit;
			}



			$apply_theme = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, 'apply-theme');
			$wpnonce = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, '_wpnonce');			
			$reset = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, 'reset');

			if (!empty($apply_theme) && !empty($wpnonce) && wp_verify_nonce($wpnonce, 'arlo-apply-theme-nonce')) {
				if (current_user_can('manage_options')) {
					$theme_id = $apply_theme;
					$theme_manager = $plugin->get_theme_manager();
				
					if ($theme_manager->is_theme_valid($theme_id)) {
						$theme_settings = $theme_manager->get_themes_settings();
						$stored_themes_settings = get_option( 'arlo_themes_settings', [] );

						//check if there is already a stored settings for the theme, or need to be reset
						if ((!empty($reset) && $reset == 1) || empty($stored_themes_settings[$theme_id])) {
							$stored_themes_settings[$theme_id] = $theme_settings[$theme_id];
							$stored_themes_settings[$theme_id]->templates = $theme_manager->load_default_templates($theme_id);
						}

						if ($stored_themes_settings[$theme_id]->templates === false && $theme_id === "custom") {
							$stored_themes_settings[$theme_id] = $theme_settings['basic.list'];
							$stored_themes_settings[$theme_id]->templates = $theme_manager->load_default_templates('basic.list');
						}

						if ($stored_themes_settings[$theme_id]->templates !== false) {
							//update the main setting with the stored theme
							foreach ($settings_object['templates'] as $page => $template) {
								if (isset($stored_themes_settings[$theme_id]->templates[$page]['html'])) {
									$settings_object['templates'][$page]['html'] = $stored_themes_settings[$theme_id]->templates[$page]['html'];
								}
							}
							update_option('arlo_settings', $settings_object, 1);
							update_option('arlo_themes_settings', $stored_themes_settings, 1);
							update_option('arlo_theme', $theme_id, 1);
						
							wp_safe_redirect( admin_url('admin.php?page=arlo-for-wordpress#pages') );
							exit;
						}
					}
				}
				wp_safe_redirect( admin_url('admin.php?page=arlo-for-wordpress') );
				exit;
			}

			$urlparts = wp_parse_url(site_url());
			$domain = $urlparts['host'];

			$delete_shortcode = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, 'delete-shortcode');
			

			if (!empty($delete_shortcode) && !empty($wpnonce) && wp_verify_nonce($wpnonce, 'arlo-delete-shortcode-nonce')) {
				if (current_user_can('manage_options')) {
					$settings_object["delete_shortcode"] = $delete_shortcode;
					update_option('arlo_settings', $settings_object);
				}
				wp_safe_redirect( admin_url('admin.php?page=arlo-for-wordpress#pages') );
				exit;
			}

			$redirect_args = ['page' => 'arlo-for-wordpress'];
			if ( isset( $_GET['settings-updated'] ) ) {
				$redirect_args['settings-updated'] = 'true';
			}
			$base_redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );

			if ( array_key_exists('arlo-new-custom-shortcode', $_COOKIE) && is_scalar($_COOKIE['arlo-new-custom-shortcode']) ) {
				$custom_shortcode = sanitize_key(wp_unslash($_COOKIE['arlo-new-custom-shortcode']));

				unset( $_COOKIE['arlo-new-custom-shortcode'] );
				setcookie('arlo-new-custom-shortcode', '', -1, '/', $domain);

				//some shared hosting doesn't delete the cookie properly
				if (!isset($_SESSION['arlo-new-custom-shortcode'])) {
					$_SESSION['arlo-new-custom-shortcode'] = true;
					wp_safe_redirect( $base_redirect_url . '#pages/' . $custom_shortcode );
					exit;
				} else {
					unset($_SESSION['arlo-new-custom-shortcode']);
				}
			} else if (isset($_SESSION['arlo-new-custom-shortcode'])){
				unset($_SESSION['arlo-new-custom-shortcode']);
			}

			add_action( 'admin_print_scripts', array($this, "arlo_check_current_tasks") );			
		}
		                 
		/*
		 *
		 * General Settings
		 *
		 */

		// create a section for the API Endpoint
		add_settings_section( 'arlo_general_section', esc_html__('General Settings', 'arlo-training-and-event-management-system' ), null, 'arlo-for-wordpress' );
		
		// create API Endpoint field                
		add_settings_field(
                        'arlo_platform_name', 
                        '<label for="arlo_platform_name">'.esc_html__('Arlo platform URL', 'arlo-training-and-event-management-system' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'platform_name',
                            'label_for' => 'arlo_platform_name',
                            'before_html' => '<div class="arlo-platform-url-wrap"><div class="arlo-domain arlo-left ">https://</div>',
							'after_html' => '<div class="arlo-domain arlo-left ">.arlo.co</div></div>',
                            )
                );                
                
        if (!empty($settings_object['platform_name'])) {

			// Resolve last sync date and a human-readable time-diff string.
			$_arlo_last_sync_date = $plugin->get_importer()->get_last_import_date();
			$_arlo_sync_time_diff = '';
			if (!empty($_arlo_last_sync_date)) {
				$_arlo_sync_from = strtotime($_arlo_last_sync_date . ' UTC');
				if ($_arlo_sync_from !== false) {
					$_arlo_sync_diff_secs = time() - $_arlo_sync_from;
					$_arlo_sync_time_diff = $_arlo_sync_diff_secs < 60
						? esc_html__('just now', 'arlo-training-and-event-management-system')
						: sprintf(
							/* translators: %s: human-readable time difference, e.g. "2 hours" */
							esc_html__('%s ago', 'arlo-training-and-event-management-system'),
							esc_html(human_time_diff($_arlo_sync_from, time()))
						);
				}
			}

			$_arlo_last_sync_line = '<div class="arlo-status-last-sync">'
				. esc_html__('Last sync:', 'arlo-training-and-event-management-system') . ' '
				. (empty($_arlo_last_sync_date)
					? esc_html__('(None)', 'arlo-training-and-event-management-system')
					: '<span class="arlo-last-sync-date">' . esc_html($_arlo_last_sync_date) . ' UTC</span>' . (!empty($_arlo_sync_time_diff) ? ' (' . $_arlo_sync_time_diff . ')' : ''))
				. '</div>';

			$_arlo_reconnect_url   = esc_url(wp_nonce_url('?page=arlo-for-wordpress&arlo-reconnect', 'arlo-reconnect'));
			$_arlo_enable_sync_url = esc_url(wp_nonce_url('?page=arlo-for-wordpress&arlo-enable-sync', 'arlo-enable-sync'));
			$_arlo_sync_now_url    = esc_url(wp_nonce_url('?page=arlo-for-wordpress&arlo-import', 'arlo-import'));
			$_arlo_view_logs_url   = esc_url(admin_url('admin.php?page=arlo-for-wordpress-logs'));

			$_arlo_is_import_disabled = get_option('arlo_import_disabled', '0') === '1';
			$_arlo_is_health_disabled = ($settings_object['import_connection_healthchecks_enabled'] ?? '1') === '1'
				&& get_option('arlo_import_connection_health_disabled', '0') === '1';
			$_arlo_failure_count      = (int) get_option('arlo_platform_access_failure_count', 0);
			$_arlo_is_user_sync_off   = ($settings_object['user_import_enabled'] ?? '1') !== '1';
			$_arlo_is_reconnecting    = !$_arlo_is_import_disabled && !$_arlo_is_health_disabled
				&& !$_arlo_is_user_sync_off
				&& $_arlo_failure_count > 0;

			if ($_arlo_is_import_disabled) {
				// State 5: System requirements not met — connectivity unknown.
				$_arlo_status_html = '<div class="arlo-platform-status arlo-requirements-not-met">'
					. '<div class="arlo-status-badges"><span class="arlo-badge arlo-badge-warning">' . esc_html__('Setup required', 'arlo-training-and-event-management-system') . '</span></div>'
					. $_arlo_last_sync_line
					. '</div>';
			} elseif ($_arlo_is_health_disabled) {
				// State 1: Connection health auto-disabled.
				$_arlo_disconnected_since = get_option('arlo_import_disabled_since', '');
				$_arlo_disconnected_time_ago = '';
				if (!empty($_arlo_disconnected_since)) {
					$_arlo_dc_from = strtotime($_arlo_disconnected_since . ' UTC');
					if ($_arlo_dc_from !== false) {
						$_arlo_dc_diff_secs = time() - $_arlo_dc_from;
						$_arlo_disconnected_time_ago = $_arlo_dc_diff_secs < 60
							? __('just now', 'arlo-training-and-event-management-system')
							: sprintf(
								/* translators: %s: human-readable time difference */
								__('%s ago', 'arlo-training-and-event-management-system'),
								human_time_diff($_arlo_dc_from, time())
							);
					}
				}
				$_arlo_since_text = !empty($_arlo_disconnected_since)
					? ' ' . esc_html__('since', 'arlo-training-and-event-management-system')
						. ' ' . esc_html($_arlo_disconnected_since) . ' UTC'
						. (!empty($_arlo_disconnected_time_ago) ? ' (' . esc_html($_arlo_disconnected_time_ago) . ')' : '')
					: '';
				$_arlo_disconnect_reason = get_option('arlo_import_disabled_message', '');
				$_arlo_reason_line = !empty($_arlo_disconnect_reason)
					? '<div class="arlo-status-last-sync">' . esc_html__('Disconnect reason:', 'arlo-training-and-event-management-system') . ' ' . esc_html($_arlo_disconnect_reason) . '</div>'
					: '';
				$_arlo_status_html = '<div class="arlo-platform-status arlo-disconnected">'
					. '<div class="arlo-status-badges">'
					. '<span class="arlo-badge arlo-badge-error">' . esc_html__('Disconnected', 'arlo-training-and-event-management-system') . '</span>'
					. $_arlo_since_text
					. '<a href="' . $_arlo_reconnect_url . '">' . esc_html__('Reconnect', 'arlo-training-and-event-management-system') . '</a>'
					. '</div>'
					. $_arlo_reason_line
					. $_arlo_last_sync_line
					. '</div>';
			} elseif ($_arlo_is_reconnecting) {
				// State 1.5: Health flag cleared but failures already accumulating — import is in progress.
				$_arlo_failure_count_line = '<div class="arlo-status-last-sync">'
					. esc_html(sprintf(
						/* translators: %d: number of recent connection failures */
						_n('%d recent connect failure', '%d recent connect failures', $_arlo_failure_count, 'arlo-training-and-event-management-system'),
						$_arlo_failure_count
					))
					. ' &mdash; <a href="' . $_arlo_view_logs_url . '">' . esc_html__('View logs', 'arlo-training-and-event-management-system') . '</a>'
					. '</div>';
				$_arlo_status_html = '<div class="arlo-platform-status arlo-reconnecting">'
					. '<div class="arlo-status-badges">'
					. '<span class="arlo-badge arlo-badge-neutral">' . esc_html__('Connecting', 'arlo-training-and-event-management-system') . '</span>'
					. '<a href="' . $_arlo_sync_now_url . '" class="arlo-sync-button">' . esc_html__('Retry', 'arlo-training-and-event-management-system') . '</a>'
					. '</div>'
					. $_arlo_failure_count_line
					. $_arlo_last_sync_line
					. '</div>';
			} elseif ($_arlo_is_user_sync_off) {
				// State 4: User manually disabled auto-sync — platform still reachable.
				$_arlo_status_html = '<div class="arlo-platform-status arlo-connected arlo-manual-sync">'
					. '<div class="arlo-status-badges">'
					. '<span class="arlo-badge arlo-badge-success">' . esc_html__('Connected', 'arlo-training-and-event-management-system') . '</span>'
					. '<span class="arlo-badge arlo-badge-outline">' . esc_html__('Manual sync only', 'arlo-training-and-event-management-system') . '</span>'
					. '<a href="' . $_arlo_sync_now_url . '" class="arlo-sync-button">' . esc_html__('Synchronize now', 'arlo-training-and-event-management-system') . '</a>'
					. '<a href="' . $_arlo_enable_sync_url . '">' . esc_html__('Re-enable auto-sync', 'arlo-training-and-event-management-system') . '</a>'
					. '</div>'
					. $_arlo_last_sync_line
					. '</div>';
			} else {
				// States 2 & 3: Connected, auto-sync on.
				$_arlo_status_html = '<div class="arlo-platform-status arlo-connected">'
					. '<div class="arlo-status-badges">'
					. '<span class="arlo-badge arlo-badge-success">' . esc_html__('Connected', 'arlo-training-and-event-management-system') . '</span>'
					. '<a href="' . $_arlo_sync_now_url . '" class="arlo-sync-button">' . esc_html__('Synchronize now', 'arlo-training-and-event-management-system') . '</a>'
					. '</div>'
					. $_arlo_last_sync_line
					. '</div>';
			}

			add_settings_field(
				'arlo_platform_status',
				esc_html__('Status', 'arlo-training-and-event-management-system'),
				array($this, 'arlo_simple_text_callback'),
				$this->plugin_slug, 'arlo_general_section',
				array('html' => $_arlo_status_html)
			);

        }

		// create Deployment mode field
		add_settings_field(
				'arlo_deployment_mode',
				'<label for="arlo_deployment_mode">' . esc_html__('Deployment', 'arlo-training-and-event-management-system') . '</label>',
				array($this, 'arlo_deployment_mode_callback'),
				$this->plugin_slug, 'arlo_general_section'
		);

		// create price settings dropdown
		add_settings_field('arlo_price_setting', '<label for="arlo_price_setting">'.esc_html__('Price shown', 'arlo-training-and-event-management-system' ).'</label>', array($this, 'arlo_price_setting_callback'), $this->plugin_slug, 'arlo_general_section');
		
		// create Free text field
		add_settings_field(
                        'arlo_free_text', 
                        '<label for="arlo_free_text">'.esc_html__('"Free" text', 'arlo-training-and-event-management-system' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'free_text',
                            'label_for' => 'arlo_free_text',
                            'default_val' => esc_html__('Free', 'arlo-training-and-event-management-system' ),
                            )
                );

		// create No events to show text field
		add_settings_field(
                        'arlo_noevent_text', 
                        '<label for="arlo_noevent_text">'.esc_html__('"No events to show" text', 'arlo-training-and-event-management-system' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'noevent_text',
                            'label_for' => 'arlo_noevent_text',
                            'default_val' => esc_html__('No events to show', 'arlo-training-and-event-management-system' ),
                            )
                );

		// create No events to show text field
		add_settings_field(
                        'arlo_noeventontemplate_text', 
                        '<label for="arlo_noeventontemplate_text">'.esc_html__('No event on a template text', 'arlo-training-and-event-management-system' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'noeventontemplate_text',
                            'label_for' => 'arlo_noeventontemplate_text',
                            'default_val' => esc_html__('Interested in attending? Have a suggestion about running this event near you?', 'arlo-training-and-event-management-system' ),
                            )
                );
                
		add_settings_field(
                        'arlo_googlemaps_api_key', 
                        '<label for="arlo_googlemaps_api_key">'.esc_html__('GoogleMaps API Key', 'arlo-training-and-event-management-system' ).'</label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'googlemaps_api_key',
                            'label_for' => 'googlemaps_api_key',
                            )
                );
                
		add_settings_field(
                        'arlo_import_callback_host', 
                        '<label for="arlo_import_callback_host">'.esc_html__('Import callback host', 'arlo-training-and-event-management-system' ).' <a href="https://developer.arlo.co/doc/wordpress/settings#import-callback-host" target="_blank"><i class="arlo-icons8 arlo-icons8-help-filled size-16"></i></a></label>', 
                        array($this, 'arlo_simple_input_callback'), 
                        $this->plugin_slug, 'arlo_general_section', 
                        array(
                            'id' => 'import_callback_host',
                            'label_for' => 'import_callback_host',
                            )
				);
				
				add_settings_field(
					'arlo_taxexempt_tag', 
					'<label for="arlo_taxexempt_tag">'.esc_html__('Tax exempt tag', 'arlo-training-and-event-management-system' ).' <a href="https://support.arlo.co/hc/en-gb/articles/115001999286-Set-an-event-as-tax-free" target="_blank"><i class="arlo-icons8 arlo-icons8-help-filled size-16"></i></a></label>', 
					array($this, 'arlo_simple_input_callback'), 
					$this->plugin_slug, 'arlo_general_section', 
					array(
						'id' => 'taxexempt_tag',
						'label_for' => 'taxexempt_tag',
						)
			);
			

		add_settings_field('arlo_filters', null, array($this, 'arlo_filter_settings_callback'), $this->plugin_slug, 'arlo_general_section',  array('id'=>'filters'));    
				

		/*
		 *
		 * Page Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_pages_section', null, array($this, 'arlo_pages_section_callback'), $this->plugin_slug );

		// loop though slug array and create each required slug field
	    foreach(Arlo_For_Wordpress::get_templates() as $id => $template) {
	    	$name = $template['name'];
			add_settings_field( $id, '<label for="'.$id.'">'.$name.'</label>', array($this, 'arlo_template_callback'), $this->plugin_slug, 'arlo_pages_section', array('id'=>$id,'label_for'=>$id, 'type'=> isset($template["type"]) ? $template["type"] : null) );
		}
		
		/*
		 *
		 * Regions Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_regions_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_regions', null, array($this, 'arlo_regions_callback'), $this->plugin_slug, 'arlo_regions_section', array('id'=>'regions') );
		
		
		/*
		 *
		 * CustomCSS Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_customcss_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_customcss', null, array($this, 'arlo_simple_textarea_callback'), $this->plugin_slug, 'arlo_customcss_section', array('id'=>'customcss', 'after_html' => '<p>Learn how to <a href="https://support.arlo.co/hc/en-gb/articles/115001714006" target="_blank">override existing styles</a> by adding <a href="#" class="arlo-settings-link" id="theme_customcss">Custom CSS</a></p>') );

		
		/*
		 *
		 * Misc Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_misc_section',  esc_html__('Miscellaneous', 'arlo-training-and-event-management-system' ), null, 'arlo-for-wordpress' );
		
		add_settings_field(
			'arlo_send_data_setting', 
			'<label for="arlo_send_data">'.esc_html__('Allow to send data to Arlo', 'arlo-training-and-event-management-system' ).'</label>', 
			array($this, 'arlo_checkbox_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			['option_name' => 'arlo_send_data']);

		add_settings_field(
			'arlo_keep_settings_on_delete_setting', 
			'<label for="arlo_keep_settings_on_delete">'.esc_html__('Keep settings when deleting the plugin (highly recommended)', 'arlo-training-and-event-management-system' ).'</label>', 
			array($this, 'arlo_checkbox_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			['option_name' => 'keep_settings']);

		add_settings_field(
			'arlo_fragmented_import_setting', 
			'<label for="arlo_import_fragment_size">'.esc_html__('Import fragment size (in bytes, max 10 MB)', 'arlo-training-and-event-management-system' ).'</label>', 
			array($this, 'arlo_simple_input_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			array(
				'id' => 'import_fragment_size',
				'label_for' => 'arlo_import_fragment_size',
				'class' => 'arlo-only-numeric',
				'default_val' => ImportRequest::FRAGMENT_DEFAULT_BYTE_SIZE,
				));	

		add_settings_field(
			'arlo_sleep_between_import_tasks_setting', 
			/* translators: %s: max seconds */
			'<label for="arlo_sleep_between_import_tasks">' . esc_html(sprintf(__('Wait between import tasks (seconds, max %s sec)', 'arlo-training-and-event-management-system' ), \ArloTraining\Scheduler::MAX_SLEEP_BETWEEN_TASKS)).'</label>', 
			array($this, 'arlo_simple_input_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			array(
				'id' => 'sleep_between_import_tasks',
				'label_for' => 'arlo_sleep_between_import_tasks',
				'class' => 'arlo-only-numeric',
				'default_val' => 0,
				));			

		add_settings_field(
			'arlo_disable_ssl_verification_setting', 
			'<label for="arlo_disable_ssl_verification">'.esc_html__('Disable SSL verification for import (not recommended)', 'arlo-training-and-event-management-system' ).'</label>', 
			array($this, 'arlo_checkbox_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section', 
			['option_name' => 'disable_ssl_verification']);
			
		add_settings_field(
			'arlo_user_import_enabled_setting',
			'<label for="user_import_enabled">'.esc_html__('Automatic synchronization enabled', 'arlo-training-and-event-management-system' ).'</label>',
			array($this, 'arlo_checkbox_callback'),
			$this->plugin_slug,
			'arlo_misc_section',
			['option_name' => 'user_import_enabled', 'default_checked' => true]);

		add_settings_field(
			'arlo_import_connection_healthchecks_enabled_setting',
			'<label for="import_connection_healthchecks_enabled">'.esc_html__('Enable connection healthchecks (recommended)', 'arlo-training-and-event-management-system' ).'</label>',
			array($this, 'arlo_checkbox_callback'),
			$this->plugin_slug,
			'arlo_misc_section',
			['option_name' => 'import_connection_healthchecks_enabled', 'default_checked' => true]);

		add_settings_field(
			'arlo_download_log_setting', 
			esc_html__('Download log', 'arlo-training-and-event-management-system' ),
			array($this, 'arlo_simple_text_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section',
			['html' => '<a href="' . esc_url(wp_nonce_url('?page=arlo-for-wordpress&arlo-download-sync-log', 'arlo-download-sync-log')) . '">Download</a>']);

		add_settings_field(
			'arlo_wp_newsletter', 
			esc_html__('Subscribe to our WP newsletter', 'arlo-training-and-event-management-system' ),
			array($this, 'arlo_simple_text_callback'), 
			$this->plugin_slug, 
			'arlo_misc_section',
			['html' => '<a href="https://confirmsubscription.com/h/r/41B80B5B566BCC0B" target="_blank">Subscribe</a>']);
		
		/*
		 *
		 * Changelog Section Settings
		 *
		 */ 
		 
		add_settings_section('arlo_changelog_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_changelog', null, array($this, 'arlo_changelog_callback'), $this->plugin_slug, 'arlo_changelog_section', array('id'=>'welcome') );


		/* Theme Section Settings */ 
		 
		add_settings_section('arlo_theme_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_theme', null, array($this, 'arlo_theme_callback'), $this->plugin_slug, 'arlo_theme_section', array('id'=>'theme') );

		/* Theme Section Settings */ 
		 
		add_settings_section('arlo_support_section', null, null, $this->plugin_slug );				
		add_settings_field( 'arlo_support', null, array($this, 'arlo_support_callback'), $this->plugin_slug, 'arlo_support_section', array('id'=>'support') );

		/* System requirements */

		add_settings_section('arlo_systemrequirements_section',  esc_html__('System requirements', 'arlo-training-and-event-management-system' ), null, 'arlo-for-wordpress' );
		add_settings_field( 'arlo_systemrequirements', null, array($this, 'arlo_systemrequirements_callback'), $this->plugin_slug, 'arlo_systemrequirements_section', array('id'=>'systemrequirements') );
	}

	/*
	 *
	 * SECTION CALLBACKS
	 *
	 */


	function arlo_pages_section_callback() {
		echo '
	    		<script type="text/javascript"> 
	    			var arlo_templates = ' . wp_json_encode(\Arlo_For_Wordpress::arlo_template_source()) . ';' . '
	    			var arlo_shortcodes = ' . wp_json_encode(array_keys(Arlo_For_Wordpress::get_templates())) . ';' .
	    		'</script>		
		';
	}

	/*
	 *
	 * FIELD CALLBACKS
	 *
	 */

	function arlo_simple_textarea_callback($args)
	{
		$settings_object = get_option('arlo_settings');
		$val = (isset($settings_object[$args['id']])) ? $settings_object[$args['id']] : (!empty($args['default_val']) ? $args['default_val'] : '');

		echo '';

		if (!empty($args['before_html'])) {
			echo wp_kses_post($args['before_html']);
		}

		echo '<textarea cols="70" rows="30" class="' .
			esc_attr((!empty($args['class']) ? $args['class'] : "")) .
			'" id="' . esc_attr("arlo_{$args['id']}") . '" name="' . esc_attr("arlo_settings[{$args['id']}]") . '" >' .
			esc_textarea($val) .
			'</textarea>';

		if (!empty($args['after_html'])) {
			echo wp_kses_post($args['after_html']);
		}
	}

	function arlo_price_setting_callback() {
		$settings_object = get_option('arlo_settings');
		$setting_id = 'price_setting';
		
		echo '<div id="'.esc_attr(ARLO_PLUGIN_PREFIX.'-price-setting').'" class="cf">';
		echo '<select name="'.esc_attr("arlo_settings[$setting_id]").'">';
		
		$val = (isset($settings_object[$setting_id])) ? $settings_object[$setting_id] : ARLO_PLUGIN_PREFIX . '-exclgst';
		
		foreach(Arlo_For_Wordpress::$price_settings as $key => $value) {
		    $key = ARLO_PLUGIN_PREFIX . '-' . $key;
		    $selected = $key == $val ? 'selected="selected"' : '';
		    echo '<option ' . esc_attr($selected)  . ' value="'.esc_attr($key).'" >'.esc_html($value).'</option>';
		}
		
		echo '</select></div>';
	}

	function arlo_deployment_mode_callback() {
		$settings_object = get_option('arlo_settings', []);
		$settings_object = is_array($settings_object) ? $settings_object : [];
		$current = $settings_object['deployment_mode']
			?? (empty($settings_object['platform_name'])
				? Arlo_For_Wordpress::DEPLOYMENT_MODE_PRODUCTION
				: Arlo_For_Wordpress::get_deployment_mode_default($settings_object['platform_name']));  // is_scalar normalization is inside the helper

		$options = [
			Arlo_For_Wordpress::DEPLOYMENT_MODE_PRODUCTION     => __('Production', 'arlo-training-and-event-management-system'),
			Arlo_For_Wordpress::DEPLOYMENT_MODE_NON_PRODUCTION => __('Non-production', 'arlo-training-and-event-management-system'),
		];

		echo '<div id="' . esc_attr(ARLO_PLUGIN_PREFIX . '-deployment-mode') . '" class="cf">';
		echo '<select id="arlo_deployment_mode" name="' . esc_attr('arlo_settings[deployment_mode]') . '">';
		foreach ($options as $value => $label) {
			$selected = selected($current, $value, false);
			echo '<option ' . $selected . ' value="' . esc_attr($value) . '">' . esc_html($label) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a pre-formatted attribute string; esc_attr() would corrupt the quotes.
		}
		echo '</select>';
		echo '<p class="description arlo-deployment-mode-hint" style="' . esc_attr($current === Arlo_For_Wordpress::DEPLOYMENT_MODE_NON_PRODUCTION ? '' : 'display:none;') . '">'
			. esc_html__('Non-production deployments synchronise less frequently.', 'arlo-training-and-event-management-system')
			. '</p>';
		echo '</div>';
	}
	
	function arlo_filter_settings_callback() {
		$filter_actions = [
			'rename' => esc_html__('Rename', 'arlo-training-and-event-management-system'),
			'exclude' => esc_html__('Exclude', 'arlo-training-and-event-management-system') // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- 'exclude' here is not for a sql query.
		];
		
		echo '<div id="'.esc_attr(ARLO_PLUGIN_PREFIX.'-filter-terminology').'" class="cf">';

		echo '<h3>Filter terminology</h3>';

		$available_page_filters = array(
			'generic' => array(
				'name' => 'Generic',
				'filters' => array(
					'delivery' => 'Delivery', 
				)
			)
		);
				
		echo $this->arlo_output_filter_editor('arlo_filter_settings', $available_page_filters, 'generic', 'generic', $filter_actions, '', 'arlo-always-visible'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filter editor HTML output. HTML is generated safely by arlo_output_filter_editor().

		echo '</div>';
	}

	//[x]: checked escaped html
	function get_select_filter_options(&$item, $key, $options = []) {
		if (empty($item['string']) && empty($item['value'])) {
			$item = array(
				'string' => $item,
				'value' => $key,
				'id' => $key
			);
		}

		extract($options);

		$value = (isset($value_field) && isset($item[$value_field]) ? $item[$value_field] : $item["string"]); 
		
		$selected = (isset($selected_value) && ($value == htmlentities($selected_value) || !strcmp($value, $selected_value)) ? ' selected="selected" ' : '');

		$item = "<option value='" . esc_attr($value) . "' " . $selected . ">" . esc_html($item["string"]) . "</option>";
	}

	function arlo_checkbox_callback($args) {
		if (empty($args['option_name'])) return;
		
		$option_name = $args['option_name'];
		$default_checked = !empty($args['default_checked']);
		$settings_object = get_option('arlo_settings', []);

		$is_checked = isset($settings_object[$option_name])
			? $settings_object[$option_name] == '1'
			: $default_checked;
				
		echo '<div id="' . esc_attr(ARLO_PLUGIN_PREFIX . '-' . $option_name) . '" class="cf">';
		echo '<input type="checkbox" value="1" name="'.esc_attr("arlo_settings[$option_name]").'" id="' . esc_attr($option_name) . '" ' . ($is_checked ? 'checked="checked"' : '') . '>';
		echo '</div>';
	}
	
	function arlo_simple_input_callback($args) {
	    $settings_object = get_option('arlo_settings');
	    $val = (isset($settings_object[$args['id']])) ? esc_attr($settings_object[$args['id']]) : (!empty($args['default_val']) ? $args['default_val'] : '' );
	    
	    $html = '';
	        
        if (!empty($args['before_html'])) {
            $html .= $args['before_html'];
        }
            
	    $html .= '<input type="text" class="' . esc_attr( (!empty($args['class']) ? $args['class'] : "") ). '" id="'.esc_attr("arlo_{$args['id']}") .'" name="'.esc_attr("arlo_settings[{$args['id']}]").'" value="'.esc_attr($val).'" />';
            
        if (!empty($args['after_html'])) {
            $html .= $args['after_html'];
        }
            
	    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML output. Input attributes are escaped using esc_attr(). The 'before_html' and 'after_html' arguments are trusted HTML strings passed from add_settings_field().
	}  	
	
	function arlo_simple_text_callback($args) {
	    $html = '';
	        
        if (!empty($args['html'])) {
            $html .= $args['html'];
        }

	    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML output. The 'html' argument is a trusted HTML string passed from add_settings_field().
	}

	/**
	 * Identifies host pages assigned to more than one Arlo page type.
	 * Only published pages are considered, as unpublished pages have no active rewrite rules.
	 *
	 * @param array $post_types The post_types sub-array from submitted settings input.
	 * @return array List of conflicts. Each entry: {
	 *   page_id    int,
	 *   page_title string,
	 *   page_url   string,
	 *   post_types array of { key: string, label: string }
	 * }
	 */
	private function validate_post_page_mappings( array $post_types ): array {
		$page_to_types = [];
		foreach ( $post_types as $pt_key => $pt_config ) {
			if ( ! is_array( $pt_config ) ) {
				continue;
			}
			$page_id = isset( $pt_config['posts_page'] ) && is_numeric( $pt_config['posts_page'] ) ? absint( $pt_config['posts_page'] ) : 0;
			if ( $page_id === 0 ) {
				continue;
			}
			$page = get_post( $page_id );
			if ( ! $page || $page->post_type !== 'page' || $page->post_status !== 'publish' ) {
				continue;
			}
			$page_to_types[ $page_id ][] = $pt_key;
		}

		$conflicts = [];
		foreach ( $page_to_types as $page_id => $pt_keys ) {
			if ( count( $pt_keys ) < 2 ) {
				continue;
			}
			$type_entries = [];
			foreach ( $pt_keys as $pt_key ) {
				$type_entries[] = [
					'key'   => $pt_key,
					'label' => Arlo_For_Wordpress::$post_types[ $pt_key ]['singular_name'] ?? $pt_key,
				];
			}
			$conflicts[] = [
				'page_id'    => $page_id,
				'page_title' => get_the_title( $page_id ),
				'page_url'   => (string) get_permalink( $page_id ),
				'post_types' => $type_entries,
			];
		}
		return $conflicts;
	}

	private function get_host_page_name_by_post_type($post_type_id) {
		if ($post_type_id === 'event') {
			return 'events';
		}

		if ($post_type_id === 'presenter') {
			return 'presenters';
		}

		if ($post_type_id === 'venue') {
			return 'venues';
		}

		return $post_type_id;
	}

	/**
	 * Returns the scheme+host+port origin of the site's home URL.
	 *
	 * Used for the URL/slug display row. Using the full get_home_url() would
	 * duplicate the subdirectory prefix on sites where WordPress is served from
	 * a subfolder: wp_make_link_relative() already includes the subdir in the
	 * slug, so the base span must show only the origin.
	 *
	 * @return string e.g. "https://mysite.com" or "https://mysite.com:8443"
	 */
	private function get_home_origin(): string {
		$parsed = wp_parse_url( get_home_url() );
		// wp_parse_url() returns false on a completely malformed URL. Guard before
		// indexing so PHP 8 does not raise an illegal-offset notice.
		if ( ! is_array( $parsed ) ) {
			return '';
		}
		// Use the parsed scheme when present; fall back to is_ssl() so non-SSL
		// sites get 'http' rather than a hardcoded 'https'.
		$scheme = $parsed['scheme'] ?? ( is_ssl() ? 'https' : 'http' );
		$host   = $parsed['host'] ?? '';
		if ( '' === $host ) {
			return '';
		}
		// IPv6 literal hosts: wp_parse_url() strips the brackets (e.g. [::1] → ::1).
		// Restore them so the assembled origin is a valid URI authority.
		if ( false !== strpos( $host, ':' ) ) {
			$host = '[' . $host . ']';
		}
		$origin = $scheme . '://' . $host;
		if ( ! empty( $parsed['port'] ) ) {
			$origin .= ':' . $parsed['port'];
		}
		return $origin;
	}

	/**
	 * Compute URL slug display info for a host-page assignment.
	 *
	 * Used for the server-side HTML render only. The view_url and preview_url
	 * values are NOT included in the AJAX response — JS constructs those URLs
	 * from window.location and ajaxUrl to avoid returning fully-qualified
	 * domain-bearing URLs in JSON.
	 *
	 * @param string $post_type_id Key into Arlo_For_Wordpress::$post_types,
	 *                             e.g. 'event', 'schedule', 'presenter'.
	 * @return array {
	 *     @type string|null $slug        Root-relative path e.g. '/events/' or null (hide row).
	 *     @type string|null $status      WP post_status or null.
	 *     @type string|null $view_url    Full URL for published pages; PHP render only, not in AJAX.
	 *     @type string|null $preview_url Preview URL for draft/future/pending; PHP render only, not in AJAX.
	 * }
	 */
	private function get_page_slug_info( string $post_type_id ): array {
		$none = [ 'slug' => null, 'status' => null, 'view_url' => null, 'preview_url' => null ];

		$post_id = \Arlo_For_Wordpress::get_posts_page_id( $post_type_id );
		if ( $post_id <= 0 ) {
			return $none;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'page' ) {
			return $none;
		}

		$draftable = [ 'draft', 'future', 'pending' ];

		if ( $post->post_status === 'publish' ) {
			// get_permalink() returns string|false. Call once, test for falsiness,
			// then reuse — avoids a double call and prevents type-unsafe coercion.
			$permalink = get_permalink( $post_id );
			if ( ! is_string( $permalink ) || '' === $permalink ) {
				return array_merge( $none, [ 'status' => 'publish' ] );
			}
			// wp_make_link_relative() returns string; use ?: null to normalise an
			// unexpected empty result (e.g. non-http/https permalink) to null.
			$slug = wp_make_link_relative( $permalink ) ?: null;
			return [
				'slug'        => $slug,
				'status'      => 'publish',
				'view_url'    => $permalink,
				'preview_url' => null,
			];
		}

		if ( in_array( $post->post_status, $draftable, true ) ) {
			// Guard: draft saved without a slug yet. If the page has a parent,
			// get_page_uri() would return 'parent-name/' (trailing slash from the
			// empty post_name), producing '/parent-name//' after our wrapping.
			// Bail early so we show nothing rather than a broken slug.
			if ( '' === $post->post_name ) {
				return array_merge( $none, [ 'status' => $post->post_status ] );
			}

			// get_page_uri() returns apply_filters('get_page_uri', string, WP_Post).
			// apply_filters() is typed mixed, so a misbehaving filter could return
			// anything (e.g. boolean true). Use is_string() to narrow explicitly —
			// empty() would not catch a truthy non-string value.
			$uri = get_page_uri( $post );
			if ( ! is_string( $uri ) || '' === $uri ) {
				return array_merge( $none, [ 'status' => $post->post_status ] );
			}
			return [
				'slug'        => wp_make_link_relative( get_home_url( null, '/' . trim( $uri, '/' ) . '/' ) ) ?: null,
				'status'      => $post->post_status,
				'view_url'    => null,
				'preview_url' => add_query_arg(
					[ 'page_id' => $post_id, 'preview' => 'true' ],
					get_home_url()
				),
			];
		}

		// private, trash, or other — hide row
		return array_merge( $none, [ 'status' => $post->post_status ] );
	}

	private function has_unpublished_host_page($post_type_id, $assigned_page = null) {
		// Include 'trash' so a trashed mapped page returns true here, showing the
		// "Review pages" recovery link rather than "Add draft pages" (which would
		// create a duplicate of a page that can simply be restored from the trash).
		if ( $assigned_page && in_array( $assigned_page->post_status, array( 'future', 'draft', 'pending', 'private', 'trash' ), true ) ) {
			return true;
		}

		$page_name = $this->get_host_page_name_by_post_type( $post_type_id );
		if ( empty( $page_name ) ) {
			return false;
		}

		static $host_page_states = [];
		if ( ! array_key_exists( $page_name, $host_page_states ) ) {
			$host_page_states[ $page_name ] = ! empty( get_posts( array(
				'post_type'      => 'page',
				'post_status'    => array( 'future', 'draft', 'pending', 'private', 'trash' ),
				'name'           => $page_name,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) ) );
		}

		return $host_page_states[ $page_name ];
	}

	function arlo_template_callback($args) {
		$id = $args['id'];
		$type = isset($args['type']) ? $args['type'] : $args['id'];
		$settings_object = get_option('arlo_settings');
		$is_new_shortcode_page = $id == 'new_custom';
		$is_custom_shortcode = isset( $args['type'] );

		echo '<h3>';
		if ($is_new_shortcode_page) {
			echo 'New custom shortcode';
		} else {
			/* translators: %s: page name */
			echo esc_html(sprintf(__('%s page', 'arlo-training-and-event-management-system' ), Arlo_For_Wordpress::get_templates()[$id]['name']));

			if ($is_custom_shortcode) {
				echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&delete-shortcode=' . urlencode($id)), 'arlo-delete-shortcode-nonce')) . '"
				class="red arlo-delete-button"><i class="arlo-icons8 arlo-icons8-cancel size-16"></i> Delete</a>';
			}
		}
		echo '</h3>';

    	if ($is_custom_shortcode) {
			$type_name = ($type == 'events' ? 'catalogue' : $type);
			echo '
			<div class="arlo-label"><label>' .  esc_html__("Shortcode type", 'arlo-training-and-event-management-system' ) . '</label></div>
			<div class="arlo-field">
				<span>'.esc_html(ucfirst($type_name)).' '.esc_html__('page', 'arlo-training-and-event-management-system' ).'</span>
			</div><br><br>';
    	}

		/*
		HACK because the keys in the $post_types arrays are bad, couldn't change because backward comp.
		*/
		
		if (!in_array($id, array('event', 'presenter', 'venue'))) {
			$post_type_id = in_array($id, array('events','presenters', 'venues')) ? substr($id, 0, strlen($id)-1) : $id;
		}

    	if (!empty($post_type_id) && !empty(Arlo_For_Wordpress::$post_types[$post_type_id])) {
    		$post_type = Arlo_For_Wordpress::$post_types[$post_type_id];
		    $val = \Arlo_For_Wordpress::get_posts_page_id( $post_type_id );
	
			$select = wp_dropdown_pages(array(
				'id'				=> esc_attr('arlo_'.$post_type_id.'_posts_page'),
			    'selected'         	=> esc_attr($val),
			    'echo'             	=> 0,
			    'name'             	=> esc_attr('arlo_settings[post_types]['.$post_type_id.'][posts_page]'),
			    'show_option_none'	=> esc_html__( '(None)', 'arlo-training-and-event-management-system' ),
			    'option_none_value'	=> 0
			));
			$no_published_pages = empty( $select );
			$assigned_page = null;
			$inject_label  = '';

			// If the stored page is non-published or permanently deleted, wp_dropdown_pages
			// omits it entirely, causing the dropdown to show "(None)" and silently
			// zero out the value on next save. Inject a selected option so the stored ID
			// is preserved and the admin can see the page needs attention.
			// Note: wp_dropdown_pages returns an empty string when there are no published
			// pages at all, so we also reconstruct the <select> element in that case.
			if ( $val > 0 ) {
				$assigned_page = get_post( $val );

				if ( $assigned_page && $assigned_page->post_status !== 'publish' ) {
					$status_labels = [
						'future'  => __( 'scheduled', 'arlo-training-and-event-management-system' ),
						'trash'   => __( 'deleted', 'arlo-training-and-event-management-system' ),
						'draft'   => __( 'draft', 'arlo-training-and-event-management-system' ),
						'pending' => __( 'pending', 'arlo-training-and-event-management-system' ),
						'private' => __( 'private', 'arlo-training-and-event-management-system' ),
					];
					$status_label = $status_labels[ $assigned_page->post_status ] ?? __( 'unpublished', 'arlo-training-and-event-management-system' );
					if ( trim( $assigned_page->post_title ) === '' ) {
						/* translators: 1: WordPress page ID 2: page status label e.g. "draft" */
						$inject_label = sprintf( esc_html__( '#%1$d (no title) (%2$s)', 'arlo-training-and-event-management-system' ), absint( $val ), esc_html( $status_label ) );
					} else {
						$inject_label = esc_html( $assigned_page->post_title ) . ' (' . esc_html( $status_label ) . ')';
					}
				} elseif ( ! $assigned_page ) {
					/* translators: %d: WordPress page ID */
					$inject_label = esc_html( sprintf( __( 'Page #%d (deleted)', 'arlo-training-and-event-management-system' ), $val ) );
				}

				if ( $inject_label ) {
					$injected = '<option value="' . esc_attr( $val ) . '" selected="selected">' . $inject_label . '</option>';
					if ( $select ) {
						$select = str_replace( '</select>', $injected . '</select>', $select );
					} else {
						$select_name = esc_attr( 'arlo_settings[post_types][' . $post_type_id . '][posts_page]' );
						$select_id   = esc_attr( 'arlo_' . $post_type_id . '_posts_page' );
						$select      = '<select name="' . $select_name . '" id="' . $select_id . '">'
							. "\n\t" . '<option value="0">' . esc_html__( '(None)', 'arlo-training-and-event-management-system' ) . '</option>'
							. "\n\t" . $injected
							. "\n" . '</select>';
					}
				}
			}

			// Defensive fallback: if $select is still empty here, no published pages
			// exist and there was no stored assignment to inject. When that is true,
			// $no_published_pages is also true and the recovery help below is shown
			// while the select remains in the DOM but hidden via CSS.
			// It prevents PHP notices from uninitialized string usage and keeps the
			// variable in a safe state if the render logic changes.
			if ( ! $select ) {
				$select = '<select name="' . esc_attr( 'arlo_settings[post_types][' . $post_type_id . '][posts_page]' ) . '" id="' . esc_attr( 'arlo_' . $post_type_id . '_posts_page' ) . '">'
					. "\n\t" . '<option value="0">' . esc_html__( '(None)', 'arlo-training-and-event-management-system' ) . '</option>'
					. "\n" . '</select>';
			}

			// When no published pages exist for this row, replace the dropdown with a
			// contextual recovery action: review an existing publishable host page for
			// this Arlo page type, or recreate the stub page when no such page exists.
			$add_pages_help = '';
			if ( $no_published_pages ) {
				$has_unpublished_host_page = $this->has_unpublished_host_page( $post_type_id, $assigned_page );

				if ( $has_unpublished_host_page ) {
					$review_url     = admin_url( 'edit.php?post_type=page' );
					$add_pages_help = '<p>' .
						'<span class="dashicons dashicons-warning"></span> ' .
						esc_html__( 'No published pages available.', 'arlo-training-and-event-management-system' ) . '<br>' .
						wp_kses(
							/* translators: %s: URL to WordPress pages list */
							sprintf( __( '<a href="%s">Review pages</a> to publish them first.', 'arlo-training-and-event-management-system' ), esc_url( $review_url ) ),
							[ 'a' => [ 'href' => [] ] ]
						) .
						'</p>';
				} else {
					$add_pages_url  = wp_nonce_url(
						admin_url( 'admin.php?page=arlo-for-wordpress&arlo-add-pages=1' ),
						'arlo-add-pages'
					);
					$add_pages_help = '<p>' .
						'<span class="dashicons dashicons-warning"></span> ' .
						esc_html__( 'No published pages available.', 'arlo-training-and-event-management-system' ) . '<br>' .
						wp_kses(
							/* translators: %s: URL to recreate Arlo draft pages */
							sprintf( __( '<a href="%s">Add draft pages</a> to get started.', 'arlo-training-and-event-management-system' ), esc_url( $add_pages_url ) ),
							[ 'a' => [ 'href' => [] ] ]
						) .
						'</p>';
				}
			}

			$shortcode_tag = Arlo_For_Wordpress::get_templates()[ $id ]['shortcode'];
			$shortcode_documentation_url = 'https://developer.arlo.co/doc/wordpress/shortcodes/globalshortcodes#' . str_replace( array( '[', ']' ), '', $shortcode_tag );

			echo '
			<div class="arlo-row">
				<div class="arlo-label"><label>' . esc_html__( 'Shortcode', 'arlo-training-and-event-management-system' ) . '</label></div>
				<div class="arlo-field">
					<code class="arlo-sc-tag">' . esc_html( $shortcode_tag ) . '</code>
					' . ( ! $is_custom_shortcode ? '<a class="arlo-gray" href="' . esc_url( $shortcode_documentation_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Customisation options', 'arlo-training-and-event-management-system' ) . '</a>' : '' ) . '
				</div>
			</div>
			<div class="arlo-row">
				<div class="arlo-label"><label>' . esc_html__( 'Host page', 'arlo-training-and-event-management-system' ) . '</label></div>
				<div class="arlo-field arlo-sc-host-field">' .
					'<span class="arlo-page-select' . ( $no_published_pages && empty( $inject_label ) ? ' arlo-page-select--hidden' : '' ) . '">' . $select . '</span>' . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Page select HTML output. $select is either from wp_dropdown_pages or reconstructed locally using esc_attr() for attributes and esc_html() for labels.
					$add_pages_help . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_html__, esc_url, and wp_kses; safe to output directly.
				'</div>
			</div>';
    	}

    	if ( ! $is_new_shortcode_page ) {
    		if ( in_array( $id, [ 'event', 'presenter', 'venue' ], true ) ) {
    			// Event, Presenter, and Venue tabs represent individual entity records
    			// (arlo_event / arlo_presenter / arlo_venue WP custom post types), not
    			// independently hosted WP pages. They have no host-page dropdown of their
    			// own; their URLs are sub-routes under the corresponding list page
    			// (Catalogue, Presenters, Venues). Derive the base slug from that list
    			// page's assignment rather than from a local dropdown value.
    			$slug_info = $this->get_page_slug_info( $id );
    			$base_slug = $slug_info['slug'] ?? null;

    			// Fallback slug shown when no list page is assigned — must stay in sync
    			// with the default-slug derivation in arlo_register_custom_post_types()
    			// (public/bootstrap.php). That function uses the same chain:
    			//   preg_replace('/[^A-Za-z]+/', '') → trim → strtolower → str_replace('_','-')
    			// rtrim + trailing slash normalises the edge case where the sanitised name is
    			// empty (e.g. a fully non-Latin type name), producing '/arlo/' rather than '/arlo//'.
    			$post_types    = \Arlo_For_Wordpress::$post_types;
    			$fallback_slug = str_replace( '_', '-', strtolower( trim( preg_replace( '/[^A-Za-z]+/', '', $post_types[ $id ]['name'] ) ) ) );
    			$fallback_path = rtrim( '/arlo/' . $fallback_slug, '/' ) . '/';
    			$fallback      = wp_make_link_relative( get_home_url( null, $fallback_path ) ) ?: $fallback_path;

    			// Only fall back to the /arlo/... pattern when no list page is assigned
    			// at all. If a page IS assigned but unusable (private, trash, or a
    			// non-page post type), hide the row — showing the fallback would
    			// misrepresent a broken configuration as a working URL.
    			$list_page_assigned = \Arlo_For_Wordpress::get_posts_page_id( $id ) > 0;
    			if ( $base_slug && false === strpos( $base_slug, '?' ) ) {
    				$base_for_display = rtrim( $base_slug, '/' );
    			} elseif ( ! $list_page_assigned ) {
    				$base_for_display = rtrim( $fallback, '/' );
    			} else {
    				$base_for_display = null; // assigned but unusable — skip row
    			}

    			$suffix_map = [
    				// Note: arlo_event pages are regionalized — arlo_set_region_redirect()
    				// inserts /region-{key}/ before the post_name at runtime, so the real
    				// URL is /events/region-NZ/{id}-{event_name}/. The slug row shows the
    				// base pattern without the region segment because the region is visitor-
    				// specific (cookie-driven) and not part of the WP rewrite slug itself.
    				'event'     => '/{id}-{event_name}',
    				// arlo_presenter and arlo_venue are not regionalized — no region segment.
    				'presenter' => '/{id}-{presenter_name}',
    				'venue'     => '/{id}-{venue_name}',
    			];
    			$suffix       = $suffix_map[ $id ];

    			$full_pattern         = null !== $base_for_display ? $base_for_display . $suffix : null;
    			$detail_hidden_class  = null === $base_for_display ? ' arlo-url-slug-row--hidden' : '';
    			$private_badge = sprintf(
    				'<span class="arlo-badge arlo-badge-neutral arlo-url-slug-private"%s>%s</span>',
    				( null !== $base_for_display && ! empty( $slug_info['status'] ) && 'publish' !== $slug_info['status'] ) ? '' : ' style="display:none"',
    				esc_html__( 'Private', 'arlo-training-and-event-management-system' )
    			);

    			echo '
					<div class="arlo-row arlo-url-slug-row' . esc_attr( $detail_hidden_class ) . '"
						id="arlo-url-slug-detail-' . esc_attr( $id ) . '">
						<div class="arlo-label">
							<label>' . esc_html__( 'URL / slug', 'arlo-training-and-event-management-system' ) . '</label>
						</div>
						<div class="arlo-field arlo-url-slug-field"
							data-original-slug="' . esc_attr( $full_pattern ?? '' ) . '"
							data-slug-suffix="' . esc_attr( $suffix ) . '"
							data-fallback-slug="' . esc_attr( $fallback ) . '">
							' . $private_badge . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with static markup and esc_html__(); safe to output directly.
							'<span class="arlo-url-base">' . esc_html( $this->get_home_origin() ) . '</span><code class="arlo-url-slug">' . esc_html( $full_pattern ?? '' ) . '</code>
							<span class="arlo-badge arlo-badge-outline arlo-url-slug-updated" style="display:none">' . esc_html__( 'Updated', 'arlo-training-and-event-management-system' ) . '</span>
						</div>
					</div>';
    		} elseif ( ! empty( $post_type_id ) ) {
    			// Regular page tabs: compute slug via helper then build the row.
    			$slug_info = $this->get_page_slug_info( $post_type_id );

    			$hidden_class = empty( $slug_info['slug'] ) ? ' arlo-url-slug-row--hidden' : '';
    			$links_html   = '';
    			if ( ! empty( $slug_info['view_url'] ) ) {
    				$links_html .= '<a class="arlo-sc-check__link" href="' . esc_url( $slug_info['view_url'] ) . '"'
    					. ' target="_blank" rel="noopener noreferrer">'
    					. esc_html__( 'View page', 'arlo-training-and-event-management-system' ) . '</a>';
    			}
    			if ( ! empty( $slug_info['preview_url'] ) ) {
    				$links_html .= '<a class="arlo-sc-check__link" href="' . esc_url( $slug_info['preview_url'] ) . '"'
    					. ' target="_blank" rel="noopener noreferrer">'
    					. esc_html__( 'Preview page', 'arlo-training-and-event-management-system' ) . '</a>';
    			}
    			// $val is set by the host-page block above when $post_type_id is non-empty
    			// and $post_types[$post_type_id] exists. Use ?? 0 to guard against the
    			// degenerate case (unknown custom shortcode type) and avoid PHP 8+ E_WARNING.
    			if ( ( $val ?? 0 ) > 0 ) {
    				$edit_url    = admin_url( 'post.php?post=' . absint( $val ) . '&action=edit' );
    				$links_html .= '<a class="arlo-sc-check__link" href="' . esc_url( $edit_url ) . '"'
    					. ' target="_blank" rel="noopener noreferrer">'
    					. esc_html__( 'Edit page', 'arlo-training-and-event-management-system' ) . '</a>';
    			}

    			$private_badge = sprintf(
    				'<span class="arlo-badge arlo-badge-neutral arlo-url-slug-private"%s>%s</span>',
    				( ! empty( $slug_info['status'] ) && 'publish' !== $slug_info['status'] ) ? '' : ' style="display:none"',
    				esc_html__( 'Private', 'arlo-training-and-event-management-system' )
    			);

    			echo '
					<div class="arlo-row arlo-url-slug-row' . esc_attr( $hidden_class ) . '"
						id="arlo-url-slug-' . esc_attr( $post_type_id ) . '">
						<div class="arlo-label">
							<label>' . esc_html__( 'URL / slug', 'arlo-training-and-event-management-system' ) . '</label>
						</div>
						<div class="arlo-field arlo-url-slug-field"
							data-original-slug="' . esc_attr( $slug_info['slug'] ?? '' ) . '">
							' . $private_badge . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with static markup and esc_html__(); safe to output directly.
							'<span class="arlo-url-base">' . esc_html( $this->get_home_origin() ) . '</span><code class="arlo-url-slug">' . esc_html( $slug_info['slug'] ?? '' ) . '</code>
							<span class="arlo-url-slug-links">' . $links_html . '</span>' . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_url() and esc_html__(); safe to output directly.
							'<span class="arlo-badge arlo-badge-outline arlo-url-slug-updated" style="display:none">' . esc_html__( 'Updated', 'arlo-training-and-event-management-system' ) . '</span>
						</div>
					</div>';
    		}
    		$this->arlo_output_template_page($settings_object, $id, $type);
    	} else {
    		$this->arlo_output_new_shortcode_page();
    	}
	}

	//[x]: checked escaped html
	function arlo_output_template_page($settings_object, $id, $type) {
		$val = isset($settings_object['templates'][$id]['html']) ? $settings_object['templates'][$id]['html'] : '';
	   
	    $this->arlo_reload_template($id);

	    if ( isset(Arlo_For_Wordpress::get_templates()[$id]['shortcode']) ) {
		    echo '<div class="arlo-label arlo-full-width">
	    		<label>'
				/* translators: %s: page name */
	    		. esc_html(sprintf(__('%s page ', 'arlo-training-and-event-management-system' ), Arlo_For_Wordpress::get_templates()[$id]['name']))
	    		. 'shortcode <span class="arlo-gray">' . esc_html(Arlo_For_Wordpress::get_templates()[$id]['shortcode']) . '</span>'
	    		. ' content
	    		</label>
	    	</div>';
		}
		
		echo '
		<div class="wp-editor-wrap">
		';

		wp_editor($val, $id, array('textarea_name'=>'arlo_settings[templates]['.$id.'][html]','textarea_rows'=>'20'));

		echo '
		<div class="cf"></div>
		<h2>' . esc_html__('Page filtered by ', 'arlo-training-and-event-management-system' ) . '</h2>
			'
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filter editor HTML output. HTML is generated safely by arlo_output_filter_editor().
			.  $this->arlo_output_filter_editor('arlo_page_filter_settings', Arlo_For_Wordpress::$available_page_filters, $type, $id, Arlo_For_Wordpress::$page_filter_options, 'page', 'arlo-always-visible') . '
		</div>';

		

	}

	function arlo_output_new_shortcode_page() {
		echo '
		<div class="arlo-label"><label>' .  esc_html__("Shortcode name", 'arlo-training-and-event-management-system' ) . '</label></div>
		<div class="arlo-field">
			<span class="arlo-new-custom-shortcode-name arlo-gray"> [arlo_<input type="text" name="arlo_settings[new_custom_shortcode]" class="arlo-inline-input" maxlength="15">] </span>
		</div><br><br>';

		echo '
		<div class="arlo-label"><label>' .  esc_html__("Shortcode type", 'arlo-training-and-event-management-system' ) . '</label></div>
		<div class="arlo-field">
			<select name="arlo_settings[new_custom_shortcode_type]" class="arlo-new-custom-shortcode-type">';
			echo '<option value=""></option>';
			foreach (Arlo_For_Wordpress::$shortcode_types as $shortcode_type => $shortcode_type_name) {
				echo '<option value="'.esc_attr($shortcode_type).'">'.esc_html($shortcode_type_name).'</option>';
			}
		echo '</select>
		</div><br><br>';
		echo '
		<div class="arlo-label"><br></div>
		<div class="arlo-field">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
		</div>';
	}
	
	function arlo_reload_template($template) {
		$selected_theme_id = get_option('arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME);

		if ($selected_theme_id != 'custom') {
			echo '
				<div class="arlo-label">
					<label>'. esc_html__('Template', 'arlo-training-and-event-management-system' ) . '</label>
				</div>
				<div class="arlo-field">
					<div class="' . esc_attr(ARLO_PLUGIN_PREFIX . '-reload-template') . '"><a>' . esc_html__('Reload original template', 'arlo-training-and-event-management-system' ) . '</a></div>
				</div>
				<div class="cf"></div>';
		}
	}

	//[x]: checked escaped html
	function arlo_output_filter_editor($setting_name, $available_page_filters, $filter_type, $filter_group, $actions = [], $id_prefix = '', $class = '') {
		$filters_settings_html = '';
		$filter_settings = get_option($setting_name, array());

		//hack, because the key in the templates are not the same as in the filters
		$filter_type = ($filter_type == 'events' ? 'template' : $filter_type);

		$filter_group_values = (isset($available_page_filters[$filter_type]) ? $available_page_filters[$filter_type] : []);

		$available_filters = [];
		if (isset($filter_group_values['filters']) && is_array($filter_group_values['filters'])) {;
			$available_filters = $filter_group_values['filters'];
		}
		
		$filters_settings_html .= '<div id="' . esc_attr('arlo-' . (!empty($id_prefix) ? ($id_prefix . '-') : '') . $filter_group . '-filters').'" class="'. esc_attr('arlo-filter-group ' . $class) . '">';
		$import_id = Arlo_For_Wordpress::get_instance()->get_importer()->get_current_import_id();

		if (count($available_filters) && !empty($import_id)) {
			
			foreach($available_filters as $filter_key => $filter) {
				$filter_options = \ArloTraining\Shortcodes\Filters::get_filter_options($filter_key, $import_id);
				$default_filter_options = $filter_options;
				array_walk($default_filter_options, [$this, 'get_select_filter_options'], ['value_field' => 'id']);

				$expand_filter = (!empty($filter_settings[$filter_group][$filter_key]) && count($filter_settings[$filter_group][$filter_key])) 
								|| (!empty($filter_settings["hiddenfilters"][$filter_group][$filter_key])) 
								|| (!empty($filter_settings["showonlyfilters"][$filter_group][$filter_key]));
	
								$filters_settings_html .= '
								<div class="arlo-filter-settings ' . ($expand_filter ? 'filter-section-expanded' : '') . '">
									<a class="arlo-filter-section-toggle"><h2>' . esc_html($filter) . '</h2></a>
									<div id="arlo-filter-empty">
										<ul>
											' . $this->arlo_output_filter_actions($actions, $setting_name, $default_filter_options, $filter_group, $filter_key, '', 'setting_id') . '
										</ul>
									</div>
								
									<ul class="arlo-available-filters" style="display: ' . ($expand_filter ? 'block' : 'none') . '">
									';

				if (is_array($filter_settings)) {

					if (!empty($filter_settings[$filter_group][$filter_key]) && count($filter_settings[$filter_group][$filter_key])) {
						foreach($filter_settings[$filter_group][$filter_key] as $old_value => $new_value) {
							/* rename entries */								
							$filter_rename_setting_options = $filter_options;
							array_walk($filter_rename_setting_options, [$this, 'get_select_filter_options'], ['selected_value' => $old_value, 'value_field' => 'id']);
							$filters_settings_html .= $this->arlo_output_filter_actions($actions, $setting_name, $filter_rename_setting_options, $filter_group, $filter_key, $new_value, wp_rand(), 'rename');
						}
					}
	
					if (!empty($filter_settings["hiddenfilters"][$filter_group][$filter_key])) {
						foreach($filter_settings["hiddenfilters"][$filter_group][$filter_key] as $old_value) {
							/* hidden/exclude entries */	
							$filter_hidden_setting_options = $filter_options;
							array_walk($filter_hidden_setting_options, [$this, 'get_select_filter_options'], ['selected_value' => $old_value, 'value_field' => 'id']);
							$filters_settings_html .= $this->arlo_output_filter_actions($actions, $setting_name, $filter_hidden_setting_options, $filter_group, $filter_key, '', wp_rand(), 'exclude');
						}
					}
	
					if (!empty($filter_settings["showonlyfilters"][$filter_group][$filter_key])) {
						foreach($filter_settings["showonlyfilters"][$filter_group][$filter_key] as $old_value) {
							/* show only entries */
							$filter_hidden_setting_options = $filter_options;
							array_walk($filter_hidden_setting_options, [$this, 'get_select_filter_options'], ['selected_value' => $old_value, 'value_field' => 'id']);
							$filters_settings_html .= $this->arlo_output_filter_actions($actions, $setting_name, $filter_hidden_setting_options, $filter_group, $filter_key, '', wp_rand(), 'showonly');
						}
					}				
				}

				/* Empty entry */
				array_walk($filter_options, [$this, 'get_select_filter_options'], ['value_field' => 'id']);
				$filters_settings_html .= $this->arlo_output_filter_actions($actions, $setting_name, $filter_options, $filter_group, $filter_key, '', wp_rand());
	
				$filters_settings_html .= '</ul></div>';						
			}
		} else {
			$filters_settings_html .= '<div>' . esc_html__('No filter option available', 'arlo-training-and-event-management-system') . '</div>';
		}

		$filters_settings_html .= '</div>';

		return $filters_settings_html;
	}

	function arlo_output_filter_actions($actions,  $setting_name, $filter_options_array, $filter_group, $filter_key, $filter_new_value, $settings_id, $selected_action = '') {
		$option_html = '
		<li>';
		
		if (is_array($actions) && count($actions)) {
			$actions = array_merge(['' => esc_html__('Select an action', 'arlo-training-and-event-management-system')], $actions);
			$option_html .= '
			<div class="arlo-filter-action">
				<select name="' . esc_attr("arlo_settings[$setting_name][$filter_group][$filter_key][$settings_id][filteraction]") . '">
				';
				
			foreach ($actions as $value => $text) {
				$selected = ($value == $selected_action ? 'selected="selected"' : '');
				$option_html .= sprintf('<option value="%s" %s>%s</option>', esc_attr($value), esc_attr($selected), esc_html($text));
			}
						
			$option_html .= '
					</select>
				</div>';
		}

		$option_html .= '
			<div class="arlo-filter-controls">
				<i class="arlo-icons8-minus arlo-icons8 size-21"></i>
				<i class="arlo-icons8-plus arlo-icons8 size-21"></i>
			</div>

			<div class="arlo-filter-old-value">
				<select name="'.esc_attr("arlo_settings[$setting_name][$filter_group][$filter_key][$settings_id][filteroldvalue]").'">
					<option value="">Select an option</option>' .
						implode('', $filter_options_array)
				. '</select>
			</div>

			<div class="arlo-filter-new-value" ' . (!empty($filter_new_value) ? 'style="display: block"' : '') . '><input type="text" name="'.esc_attr("arlo_settings[{$setting_name}][{$filter_group}][{$filter_key}][{$settings_id}][filternewvalue]").'" value="' . esc_attr($filter_new_value) . '" maxlength="64"></div>
		</li>';

		return $option_html;
	}
	
	function arlo_check_current_tasks() {
		global $wpdb;
		
		$plugin = Arlo_For_Wordpress::get_instance();
		$scheduler = $plugin->get_scheduler();
		
		$next_immediate_task = $scheduler->get_next_immediate_tasks();
		$next_immediate_task_ids = [];
		
		$running_task = $scheduler->get_running_tasks();
		$running_task_ids = [];
		
		$running_task = array_merge($running_task, $scheduler->get_paused_tasks());
		
		foreach ($next_immediate_task as $task) {
			$next_immediate_task_ids[] = $task->task_id;
		}
		
		foreach ($running_task as $task) {
			$running_task_ids[] = $task->task_id;
		}		
		
		echo "
		<script type='text/javascript'>
			var ArloImmediateTaskIDs = " . wp_json_encode($next_immediate_task_ids) . ";
			var ArloRunningTaskIDs = " . wp_json_encode($running_task_ids) . ";
		</script>
		";
		
	}
	                                                                                                                                                      	
	function arlo_regions_callback($args) {
		$regions = get_option('arlo_regions', array());
		
	    echo '
	    <h3>Regions</h3>
	    <p>Please specify your available regions in Arlo.</p>
		<p><strong>Please note, when you change the regions, you have to re-synchronize the data.</strong></p>
	    <div id="arlo-regions-header">
			<div class="arlo-order-number">#</div>
			<div class="arlo-region-id">Region ID</div>
			<div class="arlo-region-name">Region name</div>
	    </div>
	    <div id="arlo-region-empty">
			<ul>
				<li>
					<div class="arlo-order-number">1.</div>
					<div class="arlo-region-id"><input type="text" name="arlo_settings[regionid][]"></div>
					<div class="arlo-region-controls">
						<i class="arlo-icons8-minus arlo-icons8 size-21"></i>
						<i class="arlo-icons8-plus arlo-icons8 size-21"></i>
					</div>			
					<div class="arlo-region-name"><input type="text" name="arlo_settings[regionname][]"></div>
				</li>
			</ul>	    	
	    </div>
		<ul id="arlo-regions">';
		$key = 0;
		if (is_array($regions) && count($regions)) {
			foreach($regions as $regionid => $regionname) {
				echo '<li>
					<div class="arlo-order-number">' . esc_html((++$key)) . '</div>
					<div class="arlo-region-id"><input type="text" name="arlo_settings[regionid][]" value="'.esc_attr($regionid).'"></div>
					<div class="arlo-region-controls">
						<i class="arlo-icons8-minus arlo-icons8 size-21"></i>
						<i class="arlo-icons8-plus arlo-icons8 size-21"></i>
					</div>			
					<div class="arlo-region-name"><input type="text" name="arlo_settings[regionname][]" value="' . esc_attr($regionname) . '"></div>
				  </li>
				 ';
			}
		}
		
		echo '<li>
			<div class="arlo-order-number">' . esc_html(($key + 1)) . '</div>
			<div class="arlo-region-id"><input type="text" name="arlo_settings[regionid][]"></div>
			<div class="arlo-region-controls">
				<i class="arlo-icons8-minus arlo-icons8 size-21"></i>
				<i class="arlo-icons8-plus arlo-icons8 size-21"></i>
			</div>			
			<div class="arlo-region-name"><input type="text" name="arlo_settings[regionname][]"></div>
		  </li>
		</ul>
		
		<p>For more information, please visit our <a href="https://developer.arlo.co/doc/wordpress/settings#regions" target="_blank">documentation</a></p>
	    ';
	} 		

	function arlo_changelog_callback( $args ) {
		echo '<h3>' . sprintf(
			/* translators: %s: plugin version number */
			esc_html__( "What's new in v%s", 'arlo-training-and-event-management-system' ),
			esc_html( \ArloTraining\VersionHandler::VERSION )
		) . '</h3>';
		echo '<p><strong>' . esc_html__( 'If you are experiencing problems after an update, please deactivate and re-activate the plugin and re-synchronize the data.', 'arlo-training-and-event-management-system' ) . '</strong></p>';
		echo $this->get_changelog_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output contains only fixed wrapper HTML plus esc_html()-escaped changelog text generated by get_changelog_content().
	}

	
	function arlo_systemrequirements_callback () {
		$good = '<i class="arlo-icons8-checkmark arlo-icons8 size-21 green"></i>';
		$bad = '<i class="arlo-icons8-cancel arlo-icons8 size-21 red"></i>';

		echo '
		<table class="arlo-system-requirements-table">
			<tr>
				<th class="arlo-required-setting-icon"></th>
				<th class="arlo-required-setting">Setting</th>
				<th class="arlo-required-setting-value">Expected</th>
				<th class="arlo-required-setting-value">Current</th>
			</tr>
		';

		foreach (SystemRequirements::get_system_requirements() as $req) {
			$current_value = $req['current_value']();
			$check = $req['check']($current_value, $req['expected_value']);

			$good_or_bad = ($check === null ? '' : ($check ? $good : $bad));
			$green_or_red = ($check === null ? '' : ($check ? 'green' : 'red'));

			echo '
			<tr>
				<td class="arlo-required-setting-icon">' . $good_or_bad . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- System requirement status icon HTML output. Content is constructed from safe, hardcoded HTML strings.
				. '<td class="arlo-required-setting">' . esc_html($req['name']) . '</td>
				<td class="arlo-required-setting-value">' . esc_html($req['expected_value']) . '</td>
				<td class="' . esc_attr("arlo-required-setting-value $green_or_red") . '">' . esc_html($current_value) . '</td>
			</tr>			
			';
		}

		echo '</table>';
	} 

	function arlo_support_callback () {
		echo '
		<h3>' . esc_html__('Support', 'arlo-training-and-event-management-system') . '</h3>
		<p>
			<ul class="arlo-whatsnew-list">
				<li><a href="https://developer.arlo.co/doc/wordpress/index" target="_blank">Arlo for WordPress developer documentation</a> - Technical documentation on the setup and configuration of the Arlo for WordPress plugin. </li>
				<li><a href="https://support.arlo.co/hc/en-gb/sections/202320663-Website-Integration-Information" target="_blank">General Arlo website integration documentation</a> - General documentation on Arlo website integration including checkout and registration page options, custom URLs and the Arlo web team’s services.   </li>
				<li><a href="https://support.arlo.co/hc/en-gb/sections/202320703-WordPress-Plugin" target="_blank">Arlo for WordPress support  documentation</a> - Documentation on Arlo for WordPress plugin including the synchronisation between Arlo and WordPress, FAQ’s and troubleshooting.  </li>
				<li><a href="https://support.arlo.co/hc/en-gb/sections/115000452543-WordPress-Control-Themes" target="_blank">Arlo for WordPress control themes</a> - Documentation on the available Arlo for WordPress control themes and customisation options.</li>
			</ul>
		</p>
		';
	} 

	function arlo_theme_callback($args) {
		$plugin = Arlo_For_Wordpress::get_instance();
		$theme_manager = $plugin->get_theme_manager();

		$themes = $theme_manager->get_themes_settings();

		$selected_theme_id = get_option('arlo_theme', Arlo_For_Wordpress::DEFAULT_THEME);

	    echo '
	    <h3>Select Arlo for WordPress control theme </h3>
		<p>' . esc_html__('Arlo powered pages will be updated to match the Arlo control theme selected.', 'arlo-training-and-event-management-system' ) . '</p>
		<p> 
			Learn about themes <a href="javascript:;" data-fancybox="modal" data-src="#arlo-themes-for-designers">"For designers"</a>.
			Learn how to <a href="https://support.arlo.co/hc/en-gb/articles/115001714006" target="_blank">override existing styles</a> by adding <a href="#" class="arlo-settings-link" id="theme_customcss">Custom CSS</a>
		</p>
		<ul class="arlo-themes">';
		foreach ($themes as $theme_num => $theme_data) {
			$desc = $images = [];

			if (!empty($theme_data->images) && is_array($theme_data->images)) {
				foreach ($theme_data->images as $image) {
					$images[] = '<img src="' . esc_url($theme_data->url . $image) . '">';
				}
			}

			$overlay = '
					<div class="arlo-theme-desc-text">
						' . (!empty($theme_data->icon) ? '<div class="arlo-theme-icon"><i class="'.esc_attr("arlo-icons8 ' . $theme_data->icon . ' size-48").'"></i></div>' : '') . '
						<div class="arlo-theme-name">' . esc_html(htmlentities(wp_strip_all_tags($theme_data->name))) . '</div>
						<div class="arlo-theme-description">' . wp_kses_post($theme_data->description) . '</div>
						' . (!empty($theme_data->forDesigners) && $theme_data->forDesigners ? '<div class="arlo-theme-for-designer">Learn about themes <a href="javascript:;" data-fancybox="modal" data-src="#arlo-themes-for-designers">"For designers"</a></div>' : '') . '
					</div>
				';

			$desc = array_merge($desc, $images);

			if (!count($desc)) { 
				$desc[] = $overlay;
			}

			echo '
			<li class="arlo-theme">
				<div class="arlo-theme-desc ' . (count($images) == 0 ? 'arlo-theme-inverse' : '') . '">
					' . (!empty($theme_data->forDesigners) && $theme_data->forDesigners ? '<div class="arlo-themes-developer-banner" data-fancybox="modal" data-src="#arlo-themes-for-designers">For<br />designers</div>' : '') .  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dynamically constructed safe HTML string
					  $desc[0]   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Description HTML output. Content is constructed from safe HTML elements.
				   . ' <div class="arlo-theme-overlay">' . $overlay  . '</div>' . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Overlay HTML output. Content is constructed from safe HTML elements.
				'</div>
				<div class="arlo-theme-information">
					<div class="arlo-theme-name">' . esc_html(wp_strip_all_tags($theme_data->name)) . '</div>
					<div class="arlo-clear"></div>
				</div>
				<div class="arlo-theme-buttons">
					<ul>
					' . ($selected_theme_id == $theme_data->id ? '
						<li class="arlo-theme-current">Current</li>
					':'
						<li><a class="theme-apply" href="' . esc_url( wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&apply-theme=' . urlencode($theme_data->id)), 'arlo-apply-theme-nonce') ). '">' . esc_html__('Apply', 'arlo-training-and-event-management-system') . '</a></li>
					') .
						( $theme_data->id != 'custom' ? '<li><a class="theme-apply theme-reset" href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&apply-theme=' . urlencode($theme_data->id) . '&reset=1'), 'arlo-apply-theme-nonce')) . '">' . ($selected_theme_id == $theme_data->id ? esc_html__('Reset', 'arlo-training-and-event-management-system') : esc_html__('Apply & Reset', 'arlo-training-and-event-management-system')) . '</a></li>' : '') . '
					' . (!empty($theme_data->demoUrl) ? '<li><a href="' . esc_url($theme_data->demoUrl) . '" target="_blank">' . esc_html__('Preview', 'arlo-training-and-event-management-system' ) . '</a></li>' : '' ) . '
					</ul>
				</div>
			</li>';
		}
		echo '	
		</ul>
		<div class="hidden">
			<div id="arlo-themes-for-designers">
				<p>
				' . esc_html__('Themes that include "For designers" label provide maximum flexibility as they inherit some of the main website’s styles. They will however require additional work by a web designer to fix any inadvertent styling issues.', 'arlo-training-and-event-management-system' ) . '
				<br /> <a href="https://support.arlo.co/hc/en-gb/articles/115001738403" target="_blank">' . esc_html__('Learn more', 'arlo-training-and-event-management-system') . '</a></p>
			</div>
		</div>
	    ';
	}

	private function get_changelog_content() {
		static $html = null;
		if ( null !== $html ) {
			return $html;
		}

		$changelog_path = ARLO_PLUGIN_DIR . 'CHANGELOG.txt';
		if ( ! is_readable( $changelog_path ) ) {
			$html = '<p>' . esc_html__( 'Changelog not available.', 'arlo-training-and-event-management-system' ) . '</p>';
			return $html;
		}

		$raw = file_get_contents( $changelog_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local read of bundled CHANGELOG.txt.
		if ( ! is_string( $raw ) ) {
			$html = '<p>' . esc_html__( 'Changelog not available.', 'arlo-training-and-event-management-system' ) . '</p>';
			return $html;
		}

		$show_last_release_count = 10;

		// Split into release blocks on lines beginning with "==", keep the heading with its block.
		$blocks   = preg_split( '/^(?===)/m', trim( $raw ), -1, PREG_SPLIT_NO_EMPTY );
		$excerpts = array_slice( $blocks, 0, $show_last_release_count );
		$trimmed  = implode( "\n\n", array_map( 'trim', $excerpts ) );

		$html = '<div class="arlo-changelog-content">' . nl2br( esc_html( $trimmed ) ) . '</div>';

		return $html;
	}

}
