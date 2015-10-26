
<?php

	$plugin = Arlo_For_Wordpress::get_instance();
	$plugin_slug = $plugin->get_plugin_slug();

	// output the widget title
	$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Event search', $plugin_slug ) : $instance['title'], $instance, $this->id_base );
	echo $before_title . $title . $after_title; 
	
	$settings = get_option('arlo_settings');
	$slug = get_post($settings['post_types']['event']['posts_page'])->post_name;
	
	$search_term = !empty($_GET['arlo-search']) ? urlencode($_GET['arlo-search']) : '';

	echo '
	<form class="arlo-search-widget" action="'.site_url().'/'.$slug.'/">
		<input type="text" class="search-field" name="arlo-search" value="' . $search_term . '">
	</form>
	';