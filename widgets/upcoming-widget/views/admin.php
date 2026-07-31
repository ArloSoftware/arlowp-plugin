<?php

use ArloTraining\CacheControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
	global $wpdb;
	$arlo_import_id = Arlo_For_Wordpress::get_instance()->get_importer()->get_current_import_id();
	
	$arlo_tags = [];
	$arlo_all_tags = [];
	if (!empty($arlo_import_id)) {
        $arlo_prepared_sql = $wpdb->prepare("SELECT DISTINCT t.tag FROM {$wpdb->prefix}arlo_tags AS t WHERE t.import_id = %d ORDER BY t.tag", $arlo_import_id);
        
        $arlo_all_tags = CacheControl::fetch_results($arlo_prepared_sql, ARRAY_A);
	}

	foreach ($arlo_all_tags as $tag) {
		$arlo_tags[] = $tag['tag'];
	}
?>


<!-- This file is used to markup the administration form of the widget. -->
<p>
	<label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title', 'arlo-training-and-event-management-system'); ?>:</label>
	<input type="text" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" class="widefat" value="<?php echo esc_attr($title); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr($this->get_field_id('number')); ?>"><?php esc_html_e('Number of events to show', 'arlo-training-and-event-management-system'); ?>:</label>
	<input type="text" id="<?php echo esc_attr($this->get_field_id('number')); ?>" name="<?php echo esc_attr($this->get_field_name('number')); ?>" size="3" value="<?php echo esc_attr($number); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr($this->get_field_id('eventtag')); ?>"><?php esc_html_e('Filter by event tag', 'arlo-training-and-event-management-system'); ?>:</label>
	<select id="<?php echo esc_attr($this->get_field_id('eventtag')); ?>" name="<?php echo esc_attr($this->get_field_name('eventtag')); ?>" class="widefat">
		<option value="">All event tags</option>
		<?php foreach($arlo_tags as $tag) {
			if (!empty($tag)) {
				$arlo_selected = (strcmp($tag, $eventtag) ? '' : 'selected');
				echo '<option value="' . esc_attr($tag) . '" ' . esc_attr($arlo_selected) . '>' . esc_html($tag) . '</option>';
			}
		} ?>
	</select>
</p>
<p><?php esc_html_e('AND', 'arlo-training-and-event-management-system'); ?></p>
<p>
	<label for="<?php echo esc_attr($this->get_field_id('templatetag')); ?>"><?php esc_html_e('Filter by template tag','arlo-training-and-event-management-system'); ?>:</label>
	<select id="<?php echo esc_attr($this->get_field_id('templatetag')); ?>" name="<?php echo esc_attr($this->get_field_name('templatetag')); ?>" class="widefat">
		<option value="">All template tags</option>
		<?php foreach($arlo_tags as $tag) {
			if (!empty($tag)) {
				$arlo_selected = (strcmp($tag, $templatetag) ? '' : 'selected');
				echo '<option value="' . esc_attr($tag) . '" ' . esc_attr($arlo_selected) . '>' . esc_html($tag) . '</option>';
			}
		} ?>
	</select>
</p>
<p>
	<?php 
		$arlo_default_template = arlo_get_template('upcoming_widget') != "Template NOT found" ? arlo_get_template('upcoming_widget') : "";
		$arlo_template = !empty($template) ? $template : $arlo_default_template;
	?>
	<label for="<?php echo esc_attr($this->get_field_id('template')); ?>"><?php esc_html_e('Template','arlo-training-and-event-management-system'); ?>:</label>
	<textarea id="<?php echo esc_attr($this->get_field_id('template')); ?>" name="<?php echo esc_attr($this->get_field_name('template')); ?>" class="widefat"><?php echo esc_html($arlo_template); ?></textarea>
</p>