
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Upcoming event', 'arlo-training-and-event-management-system' ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . esc_html($title) . $after_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget title output. $title is escaped, $before_title and $after_title are trusted widget arguments.

	$arlo_limit = '';
	if (isset($instance['number']) && is_numeric($instance['number'])) {
		$arlo_limit = " limit='" . intval($instance['number']) . "' ";
	}

	$arlo_eventtag = '';
	if (!empty($instance['eventtag'])) {
		$arlo_eventtag = " eventtag='" . urlencode($instance['eventtag']) . "' ";	//esc_attr not enough (for example: ])
	}

	$arlo_templatetag = '';
	if (!empty($instance['templatetag'])) {
		$arlo_templatetag = " templatetag='" . urlencode($instance['templatetag']) . "' ";
	}

    $arlo_template = !empty($instance['template']) ? $instance['template'] : arlo_get_template('upcoming_widget');

    $arlo_content = "[arlo_upcoming_widget_list $arlo_limit $arlo_eventtag $arlo_templatetag ]" . $arlo_template . "[/arlo_upcoming_widget_list]";

	// output the events list
	echo do_shortcode($arlo_content);