(function ( $ ) {
	"use strict";

	$(function () {
		//dismissible message
		jQuery('.notice.is-dismissible.arlo-message .notice-dismiss').click(function() {
			var id = jQuery(this).parent().attr('id').split('-').pop();
			if (id != null) {
				var data = {
					action: 'arlo_dismiss_message',
					id: id
				}
				
				jQuery.post(ajaxurl, data);
			}
		})		
	});

}(jQuery));