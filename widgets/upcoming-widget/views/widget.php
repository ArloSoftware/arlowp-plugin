
<?php

	$plugin = Arlo_For_Wordpress::get_instance();

	$event_name = Arlo_For_Wordpress::$post_types['event']['singular_name'];

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Upcoming' ) . ' ' . $event_name : $instance['title'], $instance, $this->id_base );
	echo $before_title . $title . $after_title; 


	// number of events to list
	$num = $instance['number'];

	// event data
	$events = $this->arlo_widget_get_upcoming_list($num);

	$qty = (is_array($events)) ? count($events) : 0;

	if($qty > 0) {

		// start the list
		$output = '<ul class="arlo-list arlo-widget-upcoming">';

		for($i = 0; $i < $qty; $i++) {

			$date = new DateTime($events[$i]->e_startdatetime);

			$id = arlo_get_post_by_name($events[$i]->et_post_name, 'arlo_event');

			$link = get_permalink($id->ID);

			// the event link
			$output .= '<li class="arlo-cf">';
			$output .= '<div class="arlo-left arlo-cal">';
			$output .= '<span class="arlo-cal-month">'.$date->format('M').'</span>';
			$output .= '<span class="arlo-cal-day">'.$date->format('d').'</span>';
			$output .= '</div>';
			$output .= '<p><a href="'.$link.'">'.$events[$i]->et_name.'</a></p>';
			$output .= '<p>'.$events[$i]->e_locationname.'</p>';
			$output .= '</li>';

		}

		// end the list
		$output .= '</ul>';

	} else {

		$output = '<p>No upcoming events found</p>';

	}

	// output the events list
	echo $output;

	
