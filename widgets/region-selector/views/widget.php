
<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly.
	}

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Region selector', 'arlo-training-and-event-management-system') : $instance['title'], $instance, $this->id_base );

	echo $before_title . esc_html($title) . $after_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget title output. $title is escaped, $before_title and $after_title are trusted widget arguments.
	echo \ArloTraining\Shortcodes\Shortcodes::create_region_selector('widget'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Region selector HTML output. Content is constructed safely with escaped values.