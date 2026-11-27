<?php
/**
 * Arlo For WordPress
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo Software <support@arlo.co>
 * @license   GPL-2.0-or-later
 * @link      https://arlo.co
 * @copyright 2015-2026 Arlo Software
 */

/**
 * Displays the first-run admin pointer.
 *
 * @package Arlo_Feature_Pointer
 * @author  Arlo Software <support@arlo.co>
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
class Arlo_Feature_Pointer {

	private $pointerID      = null;
	private $pointerTarget  = null;
	private $pointerContent = null;
	private $pointerEdge    = null;
	private $pointerAlign   = null;

	/**
	 * Initialize the class
	 *
	 *
	 * @since     1.0.0
	 */
	public function __construct( $pointerID, $pointerTarget, $pointerTitle, $pointerContent, $pointerEdge = null, $pointerAlign = null ) {

		$this->pointerID = $pointerID;

		$this->pointerTarget = $pointerTarget;

		$this->pointerContent = $this->set_pointer_title( $pointerTitle );

		$this->pointerContent .= $this->set_pointer_content( $pointerContent );

		$this->pointerEdge = ( $pointerEdge == null ) ? 'left' : $pointerEdge;

		$this->pointerAlign = ( $pointerAlign == null ) ? 'center' : $pointerAlign;

		$seen_it = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
		if ( ! in_array( $pointerID, $seen_it, true ) ) {
			wp_enqueue_script( 'wp-pointer' );
			wp_enqueue_style( 'wp-pointer' );
			wp_add_inline_script( 'wp-pointer', $this->get_pointer_script() );
		}
	}

	/**
	 * Set the pointer title
	 *
	 * @since    1.0.0
	 */
	private function set_pointer_title( $title ) {

		return '<h3>' . $title . '</h3>';
	}



	/**
	 * Set the pointer content
	 *
	 * @since    1.0.0
	 */
	private function set_pointer_content( $content ) {

		return '<p>' . $content . '</p>';
	}

	/**
	 * Builds the script that opens the pointer and records its dismissal.
	 *
	 * @since    1.0.0
	 *
	 * @return string
	 */
	private function get_pointer_script(): string {
		$args = array(
			'id'       => $this->pointerID,
			'target'   => $this->pointerTarget,
			'content'  => $this->pointerContent,
			'position' => array(
				'edge'  => $this->pointerEdge,
				'align' => $this->pointerAlign,
			),
		);

		return 'jQuery(function ($) {
	var args = ' . wp_json_encode( $args, JSON_HEX_TAG ) . ';

	if (typeof $.fn.pointer === "undefined") {
		return;
	}

	$(args.target).pointer({
		content: args.content,
		position: args.position,
		close: function () {
			$.post(ajaxurl, { pointer: args.id, action: "dismiss-wp-pointer" });
		}
	}).pointer("open");
});';
	}
}
