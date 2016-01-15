<!-- This file is used to markup the administration form of the widget. -->
<p>
	<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title',$this->widget_slug); ?>:</label>
	<input type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" class="widefat" value="<?php echo $title; ?>" />
</p>
<p>
	<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of events to show',$this->widget_slug); ?>:</label>
	<input type="text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" size="3" value="<?php echo $number; ?>" />
</p>