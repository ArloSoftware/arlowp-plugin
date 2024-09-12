<?php

namespace Arlo;


class NoticeHandler {
	
	private $message_handler;
	private $importer;
	private $settings;
	private $dbl;

    private static $message_notice_types = array(
        'import_error' => 'error',
        'information' => 'notice-warning',
    );
	
	public function __construct($message_handler, $importer, $dbl) {
		$this->message_handler = $message_handler;
		$this->importer = $importer;
		$this->dbl = &$dbl;
		
		$this->settings = get_option('arlo_settings');
	}

	public function global_notices() {
		$messages = array_merge($this->message_handler->get_messages('import_error', true), $this->message_handler->get_messages('error', true), $this->message_handler->get_messages('review', true));
		
		foreach ($messages as $message) {
			echo $this->create_notice($message);
		}
	}

	public function arlo_notices() {
		$messages = $this->message_handler->get_messages(null, false);
		
		foreach ($messages as $message) {
			echo $this->create_notice($message);
		}
	}	

	
	public function create_notice($message) {
		$notice_type = (!empty($message->type) && isset(self::$message_notice_types[$message->type]) ? self::$message_notice_types[$message->type] : (!empty($message->type) ? $message->type : 'error'));

		$global_message = '';
		if (!empty($message->global)) {
			$global_message = '<td class="logo" valign="top" style="width: 60px; padding-top: 1em;">
						<a href="http://www.arlo.co" target="_blank" class="arlo-logo"></a>
					</td>';
		}

		return '
		<div class="notice ' . $notice_type . ' ' . (!empty($message->class) ? $message->class : '' ) . ' arlo-message ' . (isset($message->is_dismissable) && $message->is_dismissable ? 'is-dismissible' : '' ) . (!empty($message->type) ? ' arlo-' . $message->type : '' ) .  '" ' . 
		(!empty($message->id) ? 'id="' . $message->id . '"' : '' ) . '>
			<table>
				<tr>
					' . $global_message . '
					<td>
						' . (!empty($message->title) ? '<p><strong>' . __($message->title , 'arlo-for-wordpress' ) . '</strong></p>' : '') . '
						' . __( $message->message, 'arlo-for-wordpress' ) . '
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
			$id = \Arlo\Utilities::filter_string_polyfill(INPUT_POST, 'id');
			update_user_meta($user->ID, $id, 0);
		}
	}
	
	public function connected_platform_notice() {
		if (strtolower($this->settings['platform_name']) === \Arlo_For_Wordpress::DEFAULT_PLATFORM) {
			$message = new \stdClass();
			$message->type = 'notice';
			$message->class = 'updated';
			$message->title = 'Connected to demo data';
			$message->global = true;
			$message->message = '<p>
						Your site is currently using demo event, presenter, and venue data. Start an Arlo trial to load your own events!
					</p>
					<p>
						<a class="button button-primary" href="https://www.arlo.co/register">Get started with free Arlo trial</a>&nbsp;&nbsp;&nbsp;&nbsp;
						<a class="button button-primary arlo-block" href="#general" id="arlo-connet-platform">Connect existing Arlo platform</a>
					</p>
					<p>' . __('<a href="https://developer.arlo.co/doc/wordpress/index" target="_blank">Learn how to use</a> Arlo for WordPress or visit <a href="http://www.arlo.co" target="_blank">www.arlo.co</a> to find out more about Arlo.', 'arlo-for-wordpress' ) . '</p>';

			echo $this->create_notice($message);
		}

		$message = new \stdClass();
		$message->type = 'notice';
		$message->class = 'arlo-connected-message';
		$message->message = '<p>
					Arlo is connected to <strong>' . $this->settings['platform_name'] . '</strong> <span class="arlo-block">Last synchronized: <span class="arlo-last-sync-date">' . $this->importer->get_last_import_date() . ' UTC</span></span> 
					' . (get_option('arlo_import_disabled', '0') != '1' ? '<a class="arlo-block arlo-sync-button" href="?page=arlo-for-wordpress&arlo-import">Synchronize now</a>' : '') . '
				</p>';

		echo $this->create_notice($message);
		
		
	}

	public function permalink_notice() {
		$message_obj = $this->create_message_object(
				__("Permalink setting change required.", 'arlo-for-wordpress' ),
				'<p>' . sprintf(__('Arlo for WordPress requires <a target="_blank" href="%s">Permalinks</a> to be set to "Post name".', 'arlo-for-wordpress' ), admin_url('options-permalink.php')) . '</p>',
				'error notice');

		echo $this->create_notice($message_obj);	
	}

	public function plugin_disabled() {
		$message = '<p>' . sprintf(__('The Arlo for WordPress plugin has been disabled, please check the <a href="%s" class="%s">System Requirements</a> page'), '?page=arlo-for-wordpress#systemrequirements', 'arlo-pages-systemrequirements') . '</p>';

		$message_obj = $this->create_message_object(
				__('Plugin disabled', 'arlo-for-wordpress'),
				$message,
				'error',
				true,
				false);

		echo $this->create_notice($message_obj);
	}

	public function import_disabled() {
		$message = '<p>' . sprintf(__('The import for the Arlo for WordPress plugin has been disabled, but the current existing data is still available. Please check the <a href="%s" class="%s">System Requirements</a> page'), '?page=arlo-for-wordpress#systemrequirements', 'arlo-pages-systemrequirements') . '</p>';

		$message_obj = $this->create_message_object(
				__('Import disabled', 'arlo-for-wordpress'),
				$message,
				'error',
				true,
				false);

		echo $this->create_notice($message_obj);
	}
	

	public function posttype_notice() {
		$message_obj = $this->create_message_object(
				__("Page setup required", 'arlo-for-wordpress' ),
				'<p>' .  __('Arlo for WordPress requires you to setup the pages which will host event information.', 'arlo-for-wordpress' ) .' '. sprintf(__('<a href="%s" class="arlo-pages-setup">Setup pages</a>', 'arlo-for-wordpress' ), admin_url('admin.php?page=arlo-for-wordpress#pages/events')) . '</p><p>' . sprintf(__('<a target="_blank" href="%s">View documentation</a> for more information.', 'arlo-for-wordpress' ), 'http://developer.arlo.co/doc/wordpress/index#pages-and-post-types') . '</p>',
				'error notice',
				false,
				true);

		echo $this->create_user_notice('pagesetup', $message_obj);
	}	

	public function welcome_notice() {
	
		$this->load_demo_notice(!empty($_SESSION['arlo-demo']) ? $_SESSION['arlo-demo'] : []);
		$this->webinar_notice();
		$this->developer_notice();
		
		unset($_SESSION['arlo-import']);
	}	
	
	public function developer_notice() {
		$message = '<p class="developer">
					<i class="arlo-icons8 arlo-icons8-us-dollar-2 size-36 arlo-blue arlo-middle"></i>
					' . __('Become an Arlo reseller and receive a ', 'arlo-for-wordpress' ) . '
					' . sprintf('<strong><span>%s</span> %s</strong>', __('20%', 'arlo-for-wordpress' ),  __('sales commission', 'arlo-for-wordpress' )) . '
					' . sprintf(__('<a target="_blank" href="%s">Contact us to become an Arlo partner</a>', 'arlo-for-wordpress' ), 'https://www.arlo.co/contact') . '
				</p>';
		

		$message_obj = $this->create_message_object(
				null,
				$message,
				'notice',
				false,
				true);

		echo $this->create_user_notice('developer', $message_obj);	
	}
	
	public function webinar_notice() {
		$message = '<p class="webinar">
					<a target="_blank" href="https://www.arlo.co/video/wordpress-overview" target="_blank"><i class="arlo-icons8 arlo-icons8-circled-play size-36 arlo-yellow arlo-middle "></i>' . __('Watch overview video', 'arlo-for-wordpress' ) .'</a>
					<span class="arlo-webinar">
						<i class="arlo-icons8 arlo-icons8-headset size-36 arlo-yellow arlo-middle"></i>
						' . __('Join <a target="_blank" href="" class="webinar_url">Arlo for WordPress Getting started</a> webinar on <span id="webinar_date"></span>', 'arlo-for-wordpress' ) . '
						' . __('<a target="_blank" href="" class="webinar_url">Register now!</a> or <a target="_blank" href="" id="webinar_template_url">view more times</a>', 'arlo-for-wordpress' ) . '
					</span>
				</p>';
		

		$message_obj = $this->create_message_object(
				null,
				$message,
				'notice',
				false,
				true);

		echo $this->create_user_notice('webinar', $message_obj);	
	}

	public function load_demo_notice($error = []) {
		$import_id = $this->importer->get_current_import_id();
		
		$events = arlo_get_post_by_name('events', 'page');
		$schedule = arlo_get_post_by_name('schedule', 'page');
		$upcoming = arlo_get_post_by_name('upcoming', 'page');
		$presenters = arlo_get_post_by_name('presenters', 'page');
		$venues = arlo_get_post_by_name('venues', 'page');
						
		if (count($error)) {
			echo $this->create_user_notice('newpages',
				$this->create_message_object(
					null,
					'<p>' . sprintf(__('Couldn\'t set the following post types: %s', 'arlo-for-wordpress' ), implode(', ', $error)) . '</p>',
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
					$sql = "
					SELECT 
						ID
					FROM
						{$this->dbl->prefix}arlo_events AS e
					LEFT JOIN 		
						{$this->dbl->prefix}arlo_eventtemplates AS et		
					ON
						e.et_arlo_id = et.et_arlo_id
					AND
						e.import_id = " . $import_id ."
					LEFT JOIN
						{$this->dbl->prefix}posts
					ON
						et_post_id = ID
					AND
						post_status = 'publish'
					WHERE 
						et.import_id = " . $import_id ."
					LIMIT 
						1
					";

					$event = $this->dbl->get_results($sql, ARRAY_A);
					$event_link = '';
					if (count($event)) {
						$event_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						get_post_permalink($event[0]['ID']),
						__('Event', 'arlo-for-wordpress' ));
					}					
					
					//Get the first presenter
					$sql = "
					SELECT 
						ID
					FROM
						{$this->dbl->prefix}arlo_presenters AS p
					LEFT JOIN
						{$this->dbl->prefix}posts
					ON
						p_post_id = ID
					AND
						post_status = 'publish'
					WHERE 
						p.import_id = " . $import_id ."
					LIMIT 
						1
					";
					$presenter = $this->dbl->get_results($sql, ARRAY_A);		
					$presenter_link = '';
					if (count($event) && count($presenter)) {
						$presenter_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						get_post_permalink($presenter[0]['ID']),
						__('Presenter profile', 'arlo-for-wordpress' ));
					}					
					
					//Get the first venue
					$sql = "
					SELECT 
						ID
					FROM
						{$this->dbl->prefix}arlo_venues AS v
					LEFT JOIN
						{$this->dbl->prefix}posts
					ON
						v_post_id = ID
					AND
						post_status = 'publish'
					WHERE 
						v.import_id = " . $import_id ."
					LIMIT 
						1
					";
					$venue = $this->dbl->get_results($sql, ARRAY_A);							
					$venue_link = '';
					if (count($event) && count($venue)) {
						$venue_link = sprintf('<a href="%s" target="_blank">%s</a>,',
						get_post_permalink($venue[0]['ID']),
						__('Venue information', 'arlo-for-wordpress' ));
					}

					echo $this->create_user_notice('newpages',
						$this->create_message_object(
							__('Start editing your new pages', 'arlo-for-wordpress' ),
							'<p>'.sprintf(__('View %s <a href="%s" target="_blank">%s</a>, <a href="%s" target="_blank">%s</a>, <a href="%s" target="_blank">%s</a>, %s <a href="%s" target="_blank">%s</a> %s or <a href="%s" target="_blank">%s</a> pages', 'arlo-for-wordpress' ), 
								$event_link,
								$events->guid, 
								__('Catalogue', 'arlo-for-wordpress' ), 
								$schedule->guid,
								__('Schedule', 'arlo-for-wordpress' ), 
								$upcoming->guid,  
								$upcoming->post_title,
								$presenter_link,
								$presenters->guid, 
								__('Presenters list', 'arlo-for-wordpress' ), 						
								$venue_link,
								$venues->guid,  
								__('Venues list', 'arlo-for-wordpress' )
							) . '</p><p>' . __('Edit the page <a href="#pages" class="arlo-pages-setup">templates</a> for each of these websites pages below.') . '</p>',
							'notice',
							false,
							true)
						);
										
					unset($_SESSION['arlo-demo']);		
				}				
			}		
		}
	}	
}
