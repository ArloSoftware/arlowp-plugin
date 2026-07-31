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