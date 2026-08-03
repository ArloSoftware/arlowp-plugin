<?php

namespace ArloTraining;


class NoticeHandler {
	
	private $message_handler;
	private $importer;
	private $settings;

    private static $message_notice_types = array(
        'import_error' => 'error',
        'information' => 'notice-warning',
    );
	
	public function __construct($message_handler, $importer) {
		$this->message_handler = $message_handler;
		$this->importer = $importer;
		
		$this->settings = get_option('arlo_settings');
	}

	public function global_notices() {
		$messages = array_merge($this->message_handler->get_messages('import_error', true), $this->message_handler->get_messages('error', true), $this->message_handler->get_messages('review', true));
		
		foreach ($messages as $message) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
			echo $this->create_notice($message);
		}
	}

	public function arlo_notices() {
		$messages = $this->message_handler->get_messages(null, false);
		
		foreach ($messages as $message) {
			echo $this->create_notice($message); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
		}
	}	

	
	public function create_notice($message) {
		if (!is_object($message)) {
			return '';
		}

		$type = (isset($message->type) && is_scalar($message->type)) ? (string) $message->type : '';
		$class = (isset($message->class) && is_scalar($message->class)) ? (string) $message->class : '';
		$id = (isset($message->id) && is_scalar($message->id)) ? (string) $message->id : '';
		$title = (isset($message->title) && is_scalar($message->title)) ? (string) $message->title : '';
		$notice_type = ($type !== '' && isset(self::$message_notice_types[$type]) ? self::$message_notice_types[$type] : ($type !== '' ? $type : 'error'));
		$notice_message = (isset($message->message) && is_scalar($message->message)) ? wp_kses((string) $message->message, self::get_notice_body_allowed_html()) : '';

		$global_message = '';
		if (!empty($message->global)) {
			$global_message = '<td class="logo" valign="top" style="width: 60px; padding-top: 1em;">
						<a href="https://www.arlo.co" target="_blank" class="arlo-logo"></a>
					</td>';
		}

		// $notice_message is sanitised with wp_kses() above, so this returned admin-notice HTML intentionally preserves the allowed markup.
		return '
		<div class="'. esc_attr('notice ' . $notice_type . ' ' . $class . ' arlo-message ' . (isset($message->is_dismissable) && $message->is_dismissable ? 'is-dismissible' : '' ) . ($type !== '' ? ' arlo-' . $type : '' )). '" ' . 
		($id !== '' ? 'id="' . esc_attr($id) . '"' : '' ) . '>
			<table>
				<tr>
					' . $global_message . '
					<td>
						' . ($title !== '' ? '<p><strong>' . esc_html($title) . '</strong></p>' : '') . '
						' . $notice_message . '
					</td>
				</tr>
			</table>
	    </div>
		';
	}

	public function create_user_notice($notice_key, $message_obj) {
		$notice_id = \Arlo_For_Wordpress::$dismissible_notices[$notice_key];
		$user = wp_get_current_user();
		$meta = get_user_meta($user->ID, $notice_id, true);

		if (is_null($message_obj->id)) {
			$message_obj->id = $notice_id;
		}

		if (empty($message_obj->class)) {
			$message_obj->class = '';
		}

		$message_obj->class .= ' arlo-user-dismissable-message';

		if ($meta !== '0') {
			return $this->create_notice($message_obj);	
		}

		return '';
	}

	public function create_message_object($title = '', $message = '',  $type = null, $global = false, $is_dismissable = false, $id = null, $class = null) {
		$message_obj = new \stdClass();

		$message_obj->title = $title;
		$message_obj->message = $message;
		$message_obj->type = $type;
		$message_obj->global = $global;
		$message_obj->is_dismissable = $is_dismissable;
		$message_obj->id = $id;
		$message_obj->class = $class;

		return $message_obj;
	}

	public function dismiss_user_notice($notice_key = '') {
		if (!empty($notice_key) && in_array($notice_key, \Arlo_For_Wordpress::$dismissible_notices)) {
			$user = wp_get_current_user();
			$id = \ArloTraining\Utilities::filter_string_polyfill(INPUT_POST, 'id');
			update_user_meta($user->ID, $id, 0);
		}
	}
	
	public function permalink_notice() {
		$message_obj = $this->create_message_object(
				esc_html__("Permalink setting change required.", 'arlo-training-and-event-management-system' ),
				'<p>' . 
				/* translators: %s: link url */
				wp_kses(sprintf(__('Arlo for WordPress requires <a target="_blank" href="%s">Permalinks</a> to be set to "Post name".', 'arlo-training-and-event-management-system' ), esc_url(admin_url('options-permalink.php'))), ['a'=>['href'=>[], 'target'=>[]]]) . '</p>',
				'error notice');

		echo $this->create_notice($message_obj); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function plugin_disabled() {
		/* translators: 1 : link url 2 : css class */
		$message = '<p>' . wp_kses(sprintf(__('The Arlo for WordPress plugin has been disabled, please check the <a href="%1$s" class="%2$s">System Requirements</a> page', 'arlo-training-and-event-management-system'), '?page=arlo-for-wordpress#systemrequirements', 'arlo-pages-systemrequirements'), ['a' => ['href'=>[], 'class'=>[]]]) . '</p>';

		$message_obj = $this->create_message_object(
				esc_html__('Plugin disabled', 'arlo-training-and-event-management-system'),
				$message,
				'error',
				true,
				false);

		echo $this->create_notice($message_obj); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function demo_platform_notice() {
		if (!is_array($this->settings) || empty($this->settings['platform_name']) || strtolower((string) $this->settings['platform_name']) !== \Arlo_For_Wordpress::DEFAULT_PLATFORM) {
			return;
		}

		$settings_url = esc_url(admin_url('admin.php?page=arlo-for-wordpress#general'));
		$message = '<p>'
			. esc_html__('Your site is currently using demo event, presenter, and venue data. Start an Arlo trial to load your own events!', 'arlo-training-and-event-management-system')
			. '</p>'
			. '<p>'
			. '<a class="button button-primary" href="https://www.arlo.co/register">' . esc_html__('Get started with free Arlo trial', 'arlo-training-and-event-management-system') . '</a> '
			. '<a class="button button-primary arlo-block" href="' . $settings_url . '" id="arlo-connet-platform">' . esc_html__('Connect existing Arlo platform', 'arlo-training-and-event-management-system') . '</a>'
			. '</p>'
			. '<p>'
			. wp_kses(
				__('<a href="https://developer.arlo.co/doc/wordpress/index" target="_blank">Learn how to use</a> Arlo for WordPress or visit <a href="https://www.arlo.co" target="_blank">www.arlo.co</a> to find out more about Arlo.', 'arlo-training-and-event-management-system'),
				['a' => ['href' => [], 'target' => []]]
			)
			. '</p>';

		$message_obj = $this->create_message_object(
			esc_html__('Connected to demo data', 'arlo-training-and-event-management-system'),
			$message,
			'notice',
			true,
			false,
			'arlo-demo-platform-message',
			'updated'
		);

		echo $this->create_notice($message_obj); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function connection_health_disabled() {
		$disabled_since   = get_option('arlo_import_disabled_since', '');
		$disabled_message = get_option('arlo_import_disabled_message', '');

		$time_ago = '';
		if (!empty($disabled_since)) {
			$from = strtotime($disabled_since . ' UTC');
			if ($from !== false) {
				$diff_secs = time() - $from;
				$time_ago = $diff_secs < 60
					? __('just now', 'arlo-training-and-event-management-system')
					: sprintf(
						/* translators: %s: human-readable time difference, e.g. "2 hours" */
						__('%s ago', 'arlo-training-and-event-management-system'),
						human_time_diff($from, time())
					);
			}
		}

		$arlo_settings    = get_option('arlo_settings', []);
		$platform_host    = !empty($arlo_settings['platform_name']) ? esc_html($arlo_settings['platform_name']) . '.arlo.co' : esc_html__('the Arlo platform', 'arlo-training-and-event-management-system');

		$text = !empty($time_ago)
			? sprintf(
				/* translators: 1: platform hostname e.g. "example.arlo.co", 2: relative time e.g. "2 hours ago" */
				esc_html__('Disconnected from %1$s %2$s due to repeated platform access failures.', 'arlo-training-and-event-management-system'),
				$platform_host,
				esc_html($time_ago)
			)
			: sprintf(
				/* translators: %s: platform hostname e.g. "example.arlo.co" */
				esc_html__('Disconnected from %s due to repeated platform access failures.', 'arlo-training-and-event-management-system'),
				$platform_host
			);

		if (!empty($disabled_message)) {
			$text .= '<br>' . esc_html__('Reason:', 'arlo-training-and-event-management-system') . ' ' . esc_html($disabled_message);
		}

		$settings_url   = esc_url(admin_url('admin.php?page=arlo-for-wordpress#general'));
		$logs_url       = esc_url(admin_url('admin.php?page=arlo-for-wordpress-logs'));
		$reconnect_url  = esc_url(wp_nonce_url(admin_url('admin.php?page=arlo-for-wordpress&arlo-reconnect'), 'arlo-reconnect'));

		$links = sprintf(
			'<a href="%1$s">%2$s</a><a href="%3$s">%4$s</a><a href="%5$s">%6$s</a>',
			$reconnect_url,
			esc_html__('Reconnect', 'arlo-training-and-event-management-system'),
			$settings_url,
			esc_html__('View settings', 'arlo-training-and-event-management-system'),
			$logs_url,
			esc_html__('View logs', 'arlo-training-and-event-management-system')
		);

		$message_obj = $this->create_message_object(
			esc_html__('Disconnected from platform', 'arlo-training-and-event-management-system'),
			'<p>' . $text . '</p><p>' . $links . '</p>',
			'error',
			true,
			true,
			null,
			'arlo-health-disabled-notice'
		);

		echo $this->create_notice($message_obj); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function import_disabled() {
		/* translators: 1 : link url 2 : css class */
		$message = '<p>' . wp_kses(sprintf(__('The import for the Arlo for WordPress plugin has been disabled, but the current existing data is still available. Please check the <a href="%1$s" class="%2$s">System Requirements</a> page', 'arlo-training-and-event-management-system'), '?page=arlo-for-wordpress#systemrequirements', 'arlo-pages-systemrequirements'), ['a' => ['href'=>[], 'class'=>[]]] ). '</p>';

		$message_obj = $this->create_message_object(
				esc_html__('Import disabled', 'arlo-training-and-event-management-system'),
				$message,
				'error',
				true,
				false);

		echo $this->create_notice($message_obj); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
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
				$status_label = self::get_unpublished_page_status_label( $page['status'] );
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
					[ 'a' => [ 'href' => [], 'target' => [] ] ]
				) .
			'</p>',
			'notice-warning',
			false,
			true
		);

		echo $this->create_user_notice( 'pagesetup', $message_obj ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- User admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}	

	public function welcome_notice() {
	
		$this->load_demo_notice(array_map('sanitize_text_field', !empty($_SESSION['arlo-demo']) ? $_SESSION['arlo-demo'] : []));
		$this->developer_notice();
		
		unset($_SESSION['arlo-import']);
	}	
	
	public function developer_notice() {
		$message = '<p class="developer">
					<i class="arlo-icons8 arlo-icons8-us-dollar-2 size-36 arlo-blue arlo-middle"></i>
					' . esc_html__('Become an Arlo reseller and receive a ', 'arlo-training-and-event-management-system' ) . '
					' . sprintf('<strong><span>%s</span> %s</strong>', esc_html__('20%', 'arlo-training-and-event-management-system' ),  esc_html__('sales commission', 'arlo-training-and-event-management-system' )) . '
					' . 
					/* translators: %s: contact us page link */
					wp_kses(sprintf(__('<a target="_blank" href="%s">Contact us to become an Arlo partner</a>', 'arlo-training-and-event-management-system' ), 'https://www.arlo.co/contact'), ['a'=>['href'=>[], 'target'=>[]]]) . '
				</p>';
		

		$message_obj = $this->create_message_object(
				null,
				$message,
				'notice',
				false,
				true);

		echo $this->create_user_notice('developer', $message_obj); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- User admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
	}

	public function load_demo_notice($error = []) {
		$import_id = $this->importer->get_current_import_id();
		
		$events = arlo_get_post_by_name('events', 'page');
		$schedule = arlo_get_post_by_name('schedule', 'page');
		$upcoming = arlo_get_post_by_name('upcoming', 'page');
		$presenters = arlo_get_post_by_name('presenters', 'page');
		$venues = arlo_get_post_by_name('venues', 'page');
						
		if (count($error)) {

			echo $this->create_user_notice('newpages', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- User admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
				$this->create_message_object( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message object creation. No output here.
					null,
					/* translators: %s: errors */
					'<p>' . sprintf(esc_html__('Couldn\'t set the following post types: %s', 'arlo-training-and-event-management-system' ), implode(', ', $error)) . '</p>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Error message HTML output. Content is escaped using esc_html__ and safe concatenation.
					'error notice',
					false,
					true)
			);
		} else {
			$notice_id = \Arlo_For_Wordpress::$dismissible_notices['newpages'];
			$user = wp_get_current_user();
			$meta = get_user_meta($user->ID, $notice_id, true);

			if ($meta !== '0') {			
				if (!empty($this->settings['platform_name']) && $events !== false && $schedule !== false && $upcoming !== false && $presenters !== false && $venues !== false && !empty($import_id)) {		
					//Get the first event template wich has event
					global $wpdb;
					$event = CacheControl::fetch_results($wpdb->prepare("
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
					", $import_id, $import_id), ARRAY_A);
					$event_link = '';
					if (count($event)) {
						$event_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						esc_url(get_post_permalink($event[0]['ID'])),
						esc_html__('Event', 'arlo-training-and-event-management-system' ));
					}					
					
					//Get the first presenter
					$presenter = CacheControl::fetch_results($wpdb->prepare("
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
					", $import_id), ARRAY_A);		
					$presenter_link = '';
					if (count($event) && count($presenter)) {
						$presenter_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						esc_url(get_post_permalink($presenter[0]['ID'])),
						esc_html__('Presenter profile', 'arlo-training-and-event-management-system' ));
					}					
					
					//Get the first venue
					$venue = CacheControl::fetch_results($wpdb->prepare("
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
					", $import_id), ARRAY_A);							
					$venue_link = '';
					if (count($event) && count($venue)) {
						$venue_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						esc_url(get_post_permalink($venue[0]['ID'])),
						esc_html__('Venue information', 'arlo-training-and-event-management-system' ));
					}

					echo $this->create_user_notice('newpages', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- User admin notice HTML output. create_notice() filters notice body HTML with KSES before output.
						$this->create_message_object( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message object creation. No output here.
							esc_html__('Start editing your new pages', 'arlo-training-and-event-management-system' ),
							/* translators: 1: Event link 2: Events link 3: Catalogue 4: Schedule link 5: Schedule 6 : Upcomming link 7: Upcomming page name 8: Presenter page 9: presenters list link 10:Presenters list 11:Venue link 12:Venue list link 13:Venues list */
							'<p>'.sprintf(__('View %1$s <a href="%2$s" target="_blank">%3$s</a>, <a href="%4$s" target="_blank">%5$s</a>, <a href="%6$s" target="_blank">%7$s</a>, %8$s <a href="%9$s" target="_blank">%10$s</a> %11$s or <a href="%12$s" target="_blank">%13$s</a> pages', 'arlo-training-and-event-management-system' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formatted HTML link output. Content is constructed from safe, escaped HTML strings.
								$event_link, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Event link HTML output. Content is constructed from static HTML and escaped values.
								esc_url($events->guid), 
								esc_html__('Catalogue', 'arlo-training-and-event-management-system' ), 
								esc_url($schedule->guid),
								esc_html__('Schedule', 'arlo-training-and-event-management-system' ), 
								esc_url($upcoming->guid),  
								esc_html($upcoming->post_title),
								$presenter_link, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Presenter link HTML output. Content is constructed from static HTML and escaped values.
								esc_url($presenters->guid), 
								esc_html__('Presenters list', 'arlo-training-and-event-management-system' ), 						
								$venue_link, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Venue link HTML output. Content is constructed from static HTML and escaped values.
								esc_url($venues->guid),  
								esc_html__('Venues list', 'arlo-training-and-event-management-system' )
						) . '</p><p>' . wp_kses(__('Edit the page <a href="#pages" class="arlo-pages-setup">templates</a> for each of these websites pages below.', 'arlo-training-and-event-management-system'), ['a'=>['href'=>[],'class'=>[]]]) . '</p>',
							'notice',
							false,
							true)
						);
										
					unset($_SESSION['arlo-demo']);		
				}				
			}		
		}
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
