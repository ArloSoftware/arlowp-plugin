<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * @package   Arlo_For_Wordpress
 * @author    Arlo Software <support@arlo.co>
 * @license   GPL-2.0-or-later
 * @link      https://arlo.co
 * @copyright 2015-2026 Arlo Software
 */

use ArloTraining\VersionHandler;
use ArloTraining\CacheControl;

class Arlo_For_Wordpress_Region_Selector extends WP_Widget {

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
	protected $widget_slug = 'arlo-for-wordpress-region-selector';

	/**
	 * Registers the widget.
	 */
	public function __construct() {

		parent::__construct(
			$this->get_widget_slug(),
			esc_html__( 'Arlo region selector', 'arlo-training-and-event-management-system' ),
			array(
				'classname'   => $this->get_widget_slug() . '-class',
				'description' => esc_html__( 'Arlo region selector', 'arlo-training-and-event-management-system' ),
			)
		);

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

		// Not cached: the selected region comes from the request or the visitor's cookie.
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		extract( $args, EXTR_SKIP );

		$widget_string = $before_widget;

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'views/widget.php';
		$widget_string .= ob_get_clean();
		$widget_string .= $after_widget;

		echo $widget_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget output. The content is built from safe sources.
	} // end widget

	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param array new_instance The new instance of values to be generated via the update.
	 * @param array old_instance The previous instance of values before the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		$instance['title'] = wp_strip_all_tags( wp_unslash( $new_instance['title'] ) );

		return $instance;
	} // end widget

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param array instance The array of keys and values for the widget.
	 */
	public function form( $instance ) {

		$instance = wp_parse_args(
			(array) $instance,
			array( 'title' => '' )
		);

		$title = esc_attr( $instance['title'] );

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

add_action( 'widgets_init', 'arlo_register_widget_region_selector' );
function arlo_register_widget_region_selector() {
	register_widget( "Arlo_For_Wordpress_Region_Selector" );
}
