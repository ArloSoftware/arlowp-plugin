(function ( $ ) {
	"use strict";

	$(function () {

		// Place your public-facing JavaScript here
		var showText = objectL10n.showmoredates;
                
		if ($('.arlo-show-more').length == 1) {
			if ($('.arlo-show-more[data-show]').attr('data-show') != null)
			if ($('.arlo-show-more[data-show-text]').attr('data-show-text') != null)
				showText = $('.arlo-show-more').attr('data-show-text');        
		}

		if ($('.arlo-show-more-hidden').children().length > 0) {
			$('.arlo-show-more-hidden').before('<div class="arlo-show-more-link-container"><a href="#" class="arlo-show-more-link">' + showText + '</a></div>');
		}

		$(document).on('click touch', '.arlo-show-more-link', function(e) {

			$(".arlo-show-more-link-container").remove();

			$('.arlo-show-more-hidden').show();

			e.preventDefault();

		} );
		
        $('.arlo-timezone > select').change(function() {
            $('.arlo-timezone').submit();
        });		
                
        $('.arlo-filters > select').change(function() {
        	var filters = {
        		'cat-': 'arlo-filter-category',
        		'month-': 'arlo-filter-month',
        		'location-': 'arlo-filter-location',
        		'delivery-': 'arlo-filter-delivery',
        		'eventtag-': 'arlo-filter-eventtag',
        		'templatetag-': 'arlo-filter-templatetag',
        	}, 
        	url = WPUrls.home_url + '/'+ $('#arlo-page').val() + '/';
        	
        	for (var i in filters) {
        		if (filters.hasOwnProperty(i) && $('#' + filters[i]).length == 1 && $('#' + filters[i]).val().trim() != '') {
        			url += i + encodeURIComponent($('#' + filters[i]).val()) + '/'; 
        		} 
        	}
        	
        	document.location = url;
        	
        });
        
   		//if boxed (grid) layout, make the boxes' height even
		if ($('.arlo-boxed').length) {
			var boxedMaxHeight = 0;
			$('.arlo-boxed .arlo-list li.arlo-cf:not(.arlo-group-divider)').each(function() {
				if ($(this).height() > boxedMaxHeight) {
					boxedMaxHeight = $(this).height()
				}
			});
			$('.arlo-boxed .arlo-list li.arlo-cf:not(.arlo-group-divider)').height(boxedMaxHeight);
		}
		
		//tooltip init
		$('.arlo-tooltip-button').darkTooltip({
			gravity: 'north'
		});

	});

}(jQuery));