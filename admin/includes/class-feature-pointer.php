<?php
/**
 * Arlo For Wordpress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      https://arlo.co
 * @copyright 2018 Arlo
 */

/**
 *
 * @package Feature_Pointer
 * @author  Arlo <info@arlo.co>
 */
class Feature_Pointer {

	private $pointerID = null;
	private $pointerTarget = null;
	private $pointerContent = null;
	private $pointerEdge = null;
	private $pointerAlign = null;

	/**
	 * Initialize the class
	 * 
	 *
	 * @since     1.0.0
	 */
	public function __construct($pointerID, $pointerTarget, $pointerTitle, $pointerContent, $pointerEdge = null, $pointerAlign = null) {

		$this->pointerID = $pointerID;

		$this->pointerTarget = $pointerTarget;

		$this->pointerContent = $this->set_pointer_title($pointerTitle);

		$this->pointerContent .= $this->set_pointer_content($pointerContent);

		$this->pointerEdge = ($pointerEdge == null) ? 'left' : $pointerEdge;

		$this->pointerAlign = ($pointerAlign == null) ? 'center' : $pointerAlign;


		$seen_it = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
		$do_add_script = false;
		if ( ! in_array( $pointerID, $seen_it ) ) {
			 $do_add_script = true;
			 add_action( 'admin_print_footer_scripts', array( $this, 'feature_pointer_script' ) );
		}

		if ( $do_add_script ) {
			wp_enqueue_script( 'wp-pointer' );
			wp_enqueue_style( 'wp-pointer' );
		}
	}

	/**
	 * Set the pointer title
	 *
	 * @since    1.0.0
	 */
	private function set_pointer_title($title) {

		// TODO convert single quotes to &quot;

		return '<h3>'.$title.'</h3>';
	}



	/**
	 * Set the pointer content
	 *
	 * @since    1.0.0
	 */
	private function set_pointer_content($content) {

		// TODO convert single quotes to &quot;

		return '<p>'.$content.'</p>';
	}

	/**
	 * Add the pointer code
	 *
	 * @since    1.0.0
	 */
	function feature_pointer_script() {
		?>
		<script type="text/javascript">// <![CDATA[
		jQuery(document).ready(function($) {
			if(typeof(jQuery().pointer) != 'undefined') {
				$('<?php echo $this->pointerTarget; ?>').pointer({
					content: '<?php echo $this->pointerContent; ?>',
					position: {
						edge: '<?php echo $this->pointerEdge; ?>',
						align: '<?php echo $this->pointerAlign; ?>'
					},
					close: function() {
						$.post( ajaxurl, {
							pointer: '<?php echo $this->pointerID; ?>',
							action: 'dismiss-wp-pointer'
						});
					}
				}).pointer('open');
			}
		});
		// ]]></script>
		<?php
	} // end arlo_pointer1_footer_script()

}
