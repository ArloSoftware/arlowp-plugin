<?php
	global $wpdb;
	$import_id = Arlo_For_Wordpress::get_instance()->get_importer()->get_current_import_id();
	
	$tags = [];
	if (!empty($import_id)) {
		$all_tags = $wpdb->get_results(
			"SELECT DISTINCT
				t.tag
			FROM 
				{$wpdb->prefix}arlo_tags AS t
			WHERE 
				t.import_id = $import_id
			ORDER BY t.tag", ARRAY_A);	
	}

	foreach ($all_tags as $tag) {
		$tags[] = $tag['tag'];
	}
?>


<!-- This file is used to markup the administration form of the widget. -->
<p>
	<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title',$this->widget_slug); ?>:</label>
	<input type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" class="widefat" value="<?php echo esc_attr($title); ?>" />
</p>
<p>
	<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of events to show',$this->widget_slug); ?>:</label>
	<input type="text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" size="3" value="<?php echo esc_attr($number); ?>" />
</p>
<p>
	<label for="<?php echo $this->get_field_id('eventtag'); ?>"><?php _e('Filter by event tag',$this->widget_slug); ?>:</label>
	<select id="<?php echo $this->get_field_id('eventtag'); ?>" name="<?php echo $this->get_field_name('eventtag'); ?>" class="widefat">
		<option value="">All event tags</option>
		<?php foreach($tags as $tag) {
			if (!empty($tag)) {
				$selected = (strcmp($tag, $eventtag) ? '' : 'selected');
				echo '<option value="' . esc_attr($tag) . '" ' . $selected . '>' . esc_html($tag) . '</option>';
			}
		} ?>
	</select>
</p>
<p><?php _e('AND',$this->widget_slug); ?></p>
<p>
	<label for="<?php echo $this->get_field_id('templatetag'); ?>"><?php _e('Filter by template tag',$this->widget_slug); ?>:</label>
	<select id="<?php echo $this->get_field_id('templatetag'); ?>" name="<?php echo $this->get_field_name('templatetag'); ?>" class="widefat">
		<option value="">All template tags</option>
		<?php foreach($tags as $tag) {
			if (!empty($tag)) {
				$selected = (strcmp($tag, $templatetag) ? '' : 'selected');
				echo '<option value="' . esc_attr($tag) . '" ' . $selected . '>' . esc_html($tag) . '</option>';
			}
		} ?>
	</select>
</p>
<p>
	<?php 
		$default_template = arlo_get_template('upcoming_widget') != "Template NOT found" ? arlo_get_template('upcoming_widget') : "";
		$template = !empty($template) ? $template : $default_template;
	?>
	<label for="<?php echo $this->get_field_id('template'); ?>"><?php _e('Template',$this->widget_slug); ?>:</label>
	<textarea id="<?php echo $this->get_field_id('template'); ?>" name="<?php echo $this->get_field_name('template'); ?>" class="widefat"><?php echo esc_html($template); ?></textarea>
</p>