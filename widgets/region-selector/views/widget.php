
<?php

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Region selector', 'arlo-for-wordpress-region-selector') : $instance['title'], $instance, $this->id_base );
	
	$settings = get_option('arlo_settings');
	
	echo $before_title . $title . $after_title; 
	echo \Arlo\Shortcodes\Shortcodes::create_region_selector('widget');	