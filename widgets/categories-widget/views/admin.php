<!-- This file is used to markup the administration form of the widget. -->
<p>
	<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'arlo-for-wordpress-categories-widget'); ?>:</label>
	<input type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" class="widefat" value="<?php echo $title; ?>" />
</p>
<p>
	<label for="<?php echo $this->get_field_id('depth'); ?>"><?php _e('Depth',$this->widget_slug); ?>:</label>
	<input type="text" id="<?php echo $this->get_field_id('depth'); ?>" name="<?php echo $this->get_field_name('depth'); ?>" size="3" value="<?php echo $depth; ?>" /><br />
	<small><?php _e('Sets the depth of categories to display. Entering 0 (zero) will display all levels.', 'arlo-for-wordpress-categories-widget'); ?></small>
</p>
<p>
	<label for="<?php echo $this->get_field_id('parent'); ?>"><?php _e('Match category', 'arlo-for-wordpress-categories-widget'); ?>:</label>

	<input type="checkbox" id="<?php echo $this->get_field_id('parent'); ?>" name="<?php echo $this->get_field_name('parent'); ?>" <?php if($parent) echo 'checked="checked"'; ?> /><br />
	<small><?php _e('Checking this box will show only children categories if on a page where the events are being filtered by category.', 'arlo-for-wordpress-categories-widget'); ?></small>
</p>