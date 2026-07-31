<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!-- This file is used to markup the administration form of the widget. -->
<p>
	<label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title', 'arlo-training-and-event-management-system'); ?>:</label>
	<input type="text" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" class="widefat" value="<?php echo esc_attr($title); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr($this->get_field_id('depth')); ?>"><?php esc_html_e('Depth', 'arlo-training-and-event-management-system'); ?>:</label>
	<input type="text" id="<?php echo esc_attr($this->get_field_id('depth')); ?>" name="<?php echo esc_attr($this->get_field_name('depth')); ?>" size="3" value="<?php echo esc_attr($depth); ?>" /><br />
	<small><?php esc_html_e('Sets the depth of categories to display. Entering 0 (zero) will display all levels.', 'arlo-training-and-event-management-system'); ?></small>
</p>
<p>
	<label for="<?php echo esc_attr($this->get_field_id('parent')); ?>"><?php esc_html_e('Match category', 'arlo-training-and-event-management-system'); ?>:</label>

	<input type="checkbox" id="<?php echo esc_attr($this->get_field_id('parent')); ?>" name="<?php echo esc_attr($this->get_field_name('parent')); ?>" <?php if($parent) echo 'checked="checked"'; ?> /><br />
	<small><?php esc_html_e('Checking this box will show only children categories if on a page where the events are being filtered by category.', 'arlo-training-and-event-management-system'); ?></small>
</p>