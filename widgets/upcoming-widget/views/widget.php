
<?php

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Upcoming event', 'arlo-for-wordpress-upcoming-widget' ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . $title . $after_title; 


	// number of events to list
	$num = $instance['number'];

	// event data
	$events = $this->arlo_widget_get_upcoming_list($num);

	$qty = (is_array($events)) ? count($events) : 0;

	if (is_array($events) && count($events)) {
		$output = '<ul class="arlo-list arlo-widget-upcoming">';

		foreach ($events as $event) {
			$id = arlo_get_post_by_name($event->et_post_name, 'arlo_event');
			$link = get_permalink($id->ID);

			$output .= '<li class="arlo-cf">';
			$output .= '<div class="arlo-left arlo-cal">';
			$output .= '<span class="arlo-cal-month">' . \Arlo\Shortcodes\Events::event_date_formatter(['format' => 'M'], $event->e_startdatetime, $event->e_datetimeoffset, $event->e_isonline, $event->e_timezone_id) . '</span>';
			$output .= '<span class="arlo-cal-day">' . \Arlo\Shortcodes\Events::event_date_formatter(['format' => 'd'], $event->e_startdatetime, $event->e_datetimeoffset, $event->e_isonline, $event->e_timezone_id) . '</span>';
			$output .= '</div>';
			$output .= '<p><a href="'.$link.'">' . htmlentities($event->et_name, ENT_QUOTES, "UTF-8") . '</a></p>';
			$output .= '<p>' . htmlentities($event->e_locationname, ENT_QUOTES, "UTF-8") . '</p>';
			$output .= '</li>';
		}

		$output .= '</ul>';
	} else {
		$output = '<p>'. __('No upcoming events found', 'arlo-for-wordpress-upcoming-widget') .'</p>';
	}

	// output the events list
	echo $output;

	
