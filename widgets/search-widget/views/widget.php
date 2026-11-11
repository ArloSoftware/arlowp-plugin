<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$arlo_eventsearch_page_id = \Arlo_For_Wordpress::get_posts_page_id( 'eventsearch' );
$arlo_search_url          = $arlo_eventsearch_page_id > 0 ? (string) get_permalink( $arlo_eventsearch_page_id ) : '';

if ( empty( $arlo_search_url ) ) {
	// Match the shortcode fallback: if the stored host-page ID is stale but the
	// current page is already the real event-search page, keep the widget usable.
	$arlo_search_page = \Arlo_For_Wordpress::get_current_page_if_contains_shortcode( 'arlo_event_template_search_list' );
	if ( $arlo_search_page ) {
		$arlo_search_url = (string) get_permalink( $arlo_search_page );
	}
}

if ( ! empty( $arlo_search_url ) ) {
	// output the widget title
	$arlo_widget_title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Event search', 'arlo-training-and-event-management-system' ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . esc_html( $arlo_widget_title ) . $after_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget title output. $arlo_widget_title is escaped, $before_title and $after_title are trusted widget arguments.

	$arlo_search = \ArloTraining\Utilities::clean_string_url_parameter( 'arlo-search' );

	echo '
		<form class="arlo-search-widget" action="' . esc_url( $arlo_search_url ) . '">'
	. wp_nonce_field( "arlo-search-widget", "arlo-nonce", true, false ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Search form nonce field HTML output. wp_nonce_field() outputs safe HTML.
	. '
			<input type="text" class="search-field" name="arlo-search" value="' . esc_attr( $arlo_search ) . '">
		</form>
		';
}
