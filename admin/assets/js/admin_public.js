(function ( $ ) {
	"use strict";

	$(function () {
		//dismissible message
		$('.notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-dismiss').click(function() {
			var id = $(this).closest('.notice.is-dismissible.arlo-message').attr('id');
			if (id != null) {
				var data = {
					action: 'arlo_dismiss_message',
					id: id,
					nonce: arloAdmin.nonce
				};
				jQuery.post(ajaxurl, data);
			}
		});
	});

}(jQuery));