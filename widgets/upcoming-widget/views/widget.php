
<?php

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Upcoming event', 'arlo-for-wordpress-upcoming-widget' ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . $title . $after_title;

	$default_template = '<div class="arlo-left arlo-cal">

		<span class="arlo-cal-month">[arlo_event_start_date format="M"]</span>

		<span class="arlo-cal-day">[arlo_event_start_date format="d"]</span>

		</div>

		<p>[arlo_event_template_permalink wrap=\'<a href="%s">\'][arlo_event_template_name]</a></p>

		<p>[arlo_event_location]</p>';

	// number of events to list
	$num = $instance['number'];
	$template = $instance['template'] ? $instance['template'] : $default_template;

	if (!empty($instance['eventtag'])) {
		$eventtag = array_map(esc_html,explode(',',$instance['eventtag']));
	}

	if (!empty($instance['templatetag'])) {
		$templatetag = array_map(esc_html,explode(',',$instance['templatetag']));
	}

	// event data
	$events = $this->arlo_widget_get_upcoming_list($num,$eventtag,$templatetag);

	$qty = (is_array($events)) ? count($events) : 0;

	if (is_array($events) && count($events)) {
		$output = '<ul class="arlo-list arlo-widget-upcoming">';

		foreach ($events as $event) {
	        $GLOBALS['arlo_event_list_item'] = $event;
	        $GLOBALS['arlo_eventtemplate'] = $event;

	        $output .= '<li class="arlo-cf">';

			$output .= do_shortcode($template);

			$output .= '</li>';

            unset($GLOBALS['arlo_event_list_item']);
            unset($GLOBALS['arlo_eventtemplate']);
		}

		$output .= '</ul>';
	} else {
		$output = '<p>'. __('No upcoming events found', 'arlo-for-wordpress-upcoming-widget') .'</p>';
	}

	// output the events list
	echo $output;

	
