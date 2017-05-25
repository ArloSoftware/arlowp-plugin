
<?php

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Event search', 'arlo-for-wordpress-search-widget') : $instance['title'], $instance, $this->id_base );
	
	$settings = get_option('arlo_settings');
	if (!empty($settings['post_types']['eventsearch']['posts_page'])) {
		echo $before_title . $title . $after_title; 
		
		$slug = get_post($settings['post_types']['eventsearch']['posts_page'])->post_name;
			
		$arlo_search = \Arlo\Utilities::clean_string_url_parameter('arlo-search');

		echo '
		<form class="arlo-search-widget" action="'.site_url().'/'.$slug.'/">
			<input type="text" class="search-field" name="arlo-search" value="' . esc_attr( $arlo_search ) . '">
		</form>
		';	
	}
