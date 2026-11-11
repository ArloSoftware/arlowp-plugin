<?php
/**
 * @package   Arlo_For_Wordpress
 * @author    Arlo Software <support@arlo.co>
 * @license   GPL-2.0-or-later
 * @link      https://arlo.co
 * @copyright 2015-2026 Arlo Software
 */

use ArloTraining\Utilities;
use ArloTraining\VersionHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Arlo_For_Wordpress_Upcoming_Widget extends WP_Widget {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Unique identifier for the widget.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $widget_slug = 'arlo-for-wordpress-upcoming-widget';

	/**
	 * Registers the widget.
	 */
	public function __construct() {

		parent::__construct(
			$this->get_widget_slug(),
			esc_html__( 'Arlo Upcoming Events', 'arlo-training-and-event-management-system' ),
			array(
				'classname'   => $this->get_widget_slug() . '-class',
				'description' => esc_html__( 'Display Upcoming Events.', 'arlo-training-and-event-management-system' ),
			)
		);

		add_filter( 'pre_update_option_widget_' . $this->id_base, array( $this, 'maybe_revert_template_changes' ), 10, 2 );

		/*
		 * Currently we have no widget styles or scripts, so no point enqueuing empty files.
		 * The register_* methods below are therefore not hooked.
		 */
	} // end constructor

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Return the widget slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_widget_slug() {
		return $this->widget_slug;
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array args  The array of form elements
	 * @param array instance The current instance of the widget
	 */
	public function widget( $args, $instance ) {
		if ( get_option( 'arlo_plugin_disabled', '0' ) == '1' ) {
			return;
		}

		// Not cached: the output depends on the region, the page's filters and pagination, and CacheControl already caches the queries.
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		extract( $args, EXTR_SKIP );

		$widget_string = $before_widget;

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'views/widget.php';
		$widget_string .= ob_get_clean();
		$widget_string .= $after_widget;

		echo $widget_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The content is built from safe sources.
	} // end widget

	/**
	 * The widget instances to store, with each instance's template kept as stored when the current user may not change it.
	 *
	 * The widget screens already keep the stored template through update(); this also covers writes that reach the
	 * option without it, such as core's All Settings screen. An instance a restricted user adds that way starts with
	 * no template, so it uses the default. Requests with no signed-in user, and users who may edit templates, are
	 * stored as given.
	 *
	 * @param mixed $updated_instances  Instances about to be stored, keyed by instance number.
	 * @param mixed $existing_instances Instances currently stored.
	 * @return mixed The instances to store.
	 */
	public function maybe_revert_template_changes( $updated_instances, $existing_instances ) {
		if ( ! is_array( $updated_instances ) || ! \ArloTraining\TemplateAccess::restricts_current_user() ) {
			return $updated_instances;
		}
		$existing_instances = is_array( $existing_instances ) ? $existing_instances : [];
		foreach ( $updated_instances as $number => $instance ) {
			if ( ! is_array( $instance ) ) {
				continue;
			}
			$stored                       = isset( $existing_instances[ $number ]['template'] ) ? $existing_instances[ $number ]['template'] : '';
			$instance['template']         = is_string( $stored ) ? $stored : '';
			$updated_instances[ $number ] = $instance;
		}
		return $updated_instances;
	}

	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param array new_instance The new instance of values to be generated via the update.
	 * @param array old_instance The previous instance of values before the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$new_instance = wp_parse_args(
			$new_instance,
			array(
				'title'       => '',
				'number'      => 5,
				'template'    => '',
				'eventtag'    => '',
				'templatetag' => '',
			)
		);

		$instance = array();

		$instance['title']       = sanitize_text_field( wp_unslash( $new_instance['title'] ) );
		$instance['number']      = intval( $new_instance['number'] );
		$instance['eventtag']    = sanitize_text_field( wp_unslash( $new_instance['eventtag'] ) );
		$instance['templatetag'] = sanitize_text_field( wp_unslash( $new_instance['templatetag'] ) );

		// The template is raw markup, so it is never filtered. A user who may not add raw HTML keeps the stored template instead.
		$instance['template'] = \ArloTraining\TemplateAccess::restricts_current_user()
			? ( isset( $old_instance['template'] ) && is_string( $old_instance['template'] ) ? $old_instance['template'] : '' )
			: wp_unslash( $new_instance['template'] );

		return $instance;
	}

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param array instance The array of keys and values for the widget.
	 */
	public function form( $instance ) {

		$instance = wp_parse_args(
			(array) $instance,
			array(
				'title'       => '',
				'number'      => 5,
				'template'    => '',
				'eventtag'    => '',
				'templatetag' => '',
			)
		);

		$title       = $instance['title'];
		$number      = $instance['number'];
		$template    = $instance['template'];
		$eventtag    = $instance['eventtag'];
		$templatetag = $instance['templatetag'];

		// Display the admin form
		include plugin_dir_path( __FILE__ ) . 'views/admin.php';
	} // end form

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {

		wp_enqueue_style( $this->get_widget_slug() . '-admin-styles', plugins_url( 'css/admin.css', __FILE__ ), [], VersionHandler::VERSION );
	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {

		wp_enqueue_script( $this->get_widget_slug() . '-admin-script', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, false );
	} // end register_admin_scripts

	/**
	 * Registers and enqueues widget-specific styles.
	 */
	public function register_widget_styles() {

		wp_enqueue_style( $this->get_widget_slug() . '-widget-styles', plugins_url( 'css/widget.css', __FILE__ ), [], VersionHandler::VERSION );
	} // end register_widget_styles

	/**
	 * Registers and enqueues widget-specific scripts.
	 */
	public function register_widget_scripts() {

		wp_enqueue_script( $this->get_widget_slug() . '-script', plugins_url( 'js/widget.js', __FILE__ ), array( 'jquery' ), VersionHandler::VERSION, false );
	} // end register_widget_scripts
} // end class

add_action( 'widgets_init', 'arlo_register_widget_upcoming_widget' );
function arlo_register_widget_upcoming_widget() {
	register_widget( "Arlo_For_Wordpress_Upcoming_Widget" );
}
