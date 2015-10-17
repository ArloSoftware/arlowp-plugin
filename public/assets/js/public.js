(function ( $ ) {
	"use strict";

	$(function () {

		// Place your public-facing JavaScript here
                var showText = "Show me more dates";
                
		if($('.arlo-show-more').length == 1) {
                        if ($('.arlo-show-more[data-show]').attr('data-show') != null)
                        if ($('.arlo-show-more[data-show-text]').attr('data-show-text') != null)
                            showText = $('.arlo-show-more').attr('data-show-text');
                        
		}

		if($('.arlo-show-more-hidden').children().length > 0) {
                    $('.arlo-show-more-hidden').before('<div class="arlo-show-more-link-container"><a href="#" class="arlo-show-more-link">' + showText + '</a></div>');
		}

		$(document).on('click touch', '.arlo-show-more-link', function(e) {

			$(".arlo-show-more-link-container").remove();

			$('.arlo-show-more-hidden').show();

			e.preventDefault();

		} );
                
        $('.arlo-filters > select').change(function() {
            $('.arlo-filters').submit();
        });
                
                
		/*
		$('#arlo-event-filter').on('submit', function(e) {

			e.preventDefault();

			var cat = $('#arlo-filter-arlo_event_category').val();
			var location = $('#arlo-filter-arlo_event_location').val();
			var delivery = $('#arlo-filter-delivery').val();
			var page = $(this).attr('action');

			if(cat != 0)
				page += 'category/' + cat + '/';
				
			if (location != 0) 
				page += 'location/' + location + '/';
				
			if (delivery != "") 
				page += 'delivery/' + delivery + '/';
				
			window.location.href = page;
			
			

		} );
		
		*/

	});

}(jQuery));