
<?php

	$plugin = Arlo_For_Wordpress::get_instance();
	$plugin_slug = $plugin->get_plugin_slug();

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Event search', $plugin_slug ) : $instance['title'], $instance, $this->id_base );
	
	$settings = get_option('arlo_settings');
	if (!empty($settings['post_types']['eventsearch']['posts_page'])) {
		echo $before_title . $title . $after_title; 
		
		$slug = get_post($settings['post_types']['eventsearch']['posts_page'])->post_name;
			
		$search_term = !empty($_GET['arlo-search']) ? stripslashes(esc_attr(urldecode($_GET['arlo-search']))) : '';
	
		echo '
		<form class="arlo-search-widget" action="'.site_url().'/'.$slug.'/">
			<input type="text" class="search-field" name="arlo-search" value="' . $search_term . '">
		</form>
		';	
	}
