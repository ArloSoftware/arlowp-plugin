
<?php

	$plugin = Arlo_For_Wordpress::get_instance();

	$event_name = arlo_get_option('post_types')['event']['singular_name'];

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? $event_name . ' ' . __( 'Categories' ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . $title . $after_title;

	// number of levels to display, "0" displays the full tree
	$depth = isset($instance['depth']) ? $instance['depth'] : 0 ;

	$parent = ($instance['parent']) ? '' : ' parent="0"';

	$shortcode = '[arlo_categories' . $parent . ' depth="' . $depth . '"]';

	$output = '<div class="arlo-categories-widget">' . do_shortcode($shortcode) . '</div>';

	if(empty($output)) $output = __('No categories to display.',$this->plugin_slug);

	// output the events list
	echo $output;

	
