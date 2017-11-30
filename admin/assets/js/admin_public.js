(function ( $ ) {
	"use strict";

	$(function () {
		//dismissible message
		$('.notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-dismiss, .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-dismiss-custom').click(function() {
			var id = $(this).closest('.notice.is-dismissible.arlo-message').attr('id');
			if (id != null) {
				var data = {
					action: 'arlo_dismiss_message',
					id: id
				}
				
				jQuery.post(ajaxurl, data);
			}
		})		
	});

	$('.notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-dismiss-custom, .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-ask-later').click(function(e) {
		e.preventDefault();
		$(this).closest('.arlo-message').fadeOut(function() {
			$(this).closest('.arlo-message').remove();
		});
	});

	$('.notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-ask-later').click(function() {
		var data = {
			action: 'arlo_increment_review_notice_date'
		}
		
		$.post(ajaxurl, data);
	});

}(jQuery));