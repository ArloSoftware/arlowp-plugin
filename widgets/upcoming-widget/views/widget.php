
<?php

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Upcoming event', 'arlo-for-wordpress-upcoming-widget' ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . $title . $after_title;

	$limit = '';
	if (!empty($instance['number'])) {
		$limit = " limit='".$instance['number']."' ";
	}

	$eventtag = '';
	if (!empty($instance['eventtag'])) {
		$eventtag = " eventtag='".$instance['eventtag']."' ";
	}

    $template = $instance['template'] ? $instance['template'] : arlo_get_template('upcoming_widget');

	$shortcode = "<ul class='arlo-widget-upcoming arlo-list'>[arlo_upcoming_list_item $limit $eventtag]".$template."[/arlo_upcoming_list_item]</ul>";

	// output the events list
	echo do_shortcode($shortcode);