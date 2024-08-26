
<?php

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Upcoming event', 'arlo-for-wordpress-upcoming-widget' ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . $title . $after_title;

	$limit = '';
	if (isset($instance['number']) && is_numeric($instance['number'])) {
		$limit = " limit='" . intval($instance['number']) . "' ";
	}

	$eventtag = '';
	if (!empty($instance['eventtag'])) {
		$eventtag = " eventtag='" . urlencode($instance['eventtag']) . "' ";	//esc_attr not enough (for example: ])
	}

	$templatetag = '';
	if (!empty($instance['templatetag'])) {
		$templatetag = " templatetag='" . urlencode($instance['templatetag']) . "' ";
	}

    $template = !empty($instance['template']) ? $instance['template'] : arlo_get_template('upcoming_widget');

    $content = "[arlo_upcoming_widget_list $limit $eventtag $templatetag ]" . $template . "[/arlo_upcoming_widget_list]";

	// output the events list
	echo do_shortcode($content);