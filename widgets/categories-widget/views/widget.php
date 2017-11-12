
<?php

	$plugin = Arlo_For_Wordpress::get_instance();
	$plugin_slug = $plugin->get_plugin_slug();

	$event_name = Arlo_For_Wordpress::$post_types['event']['singular_name'];

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? $event_name . ' ' . __( 'Categories', $plugin_slug ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . $title . $after_title;

	// number of levels to display, "0" displays the full tree
	$depth = isset($instance['depth']) ? $instance['depth'] : 0 ;

	$parent = ($instance['parent']) ? '' : ' parent="0"';

	$shortcode = '[arlo_categories' . $parent . ' depth="' . $depth . '" widget="true"]';

	$output = do_shortcode($shortcode);

	if (!empty($output)) {
		$output = '<div class="arlo-categories-widget">' . $output . '</div>';	
	} else {
		$output = '<p>' . __('No categories to display.', $plugin_slug) . '</p>';
	} 

	// output the events list
	echo $output;

	
