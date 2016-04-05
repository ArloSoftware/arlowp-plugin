(function ( $ ) {
	"use strict";
	
	var uriRegion;

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
        		'search-': 'arlo-filter-search',
        		'cat-': 'arlo-filter-category',
        		'month-': 'arlo-filter-month',
        		'location-': 'arlo-filter-location',
        		'delivery-': 'arlo-filter-delivery',
        		'tag-': 'arlo-filter-eventtag'
        	}, 
        	url = '/'+ $('#arlo-page').val() + '/';
        	
        	for (var i in filters) {
        		if (filters.hasOwnProperty(i) && $('#' + filters[i]).length == 1 && $('#' + filters[i]).val().trim() != '') {
        			if (i == 'search-') {
        				url += 'search/' + $('#' + filters[i]).val().trim() + '/'; 
        			} else {
        				url += i + $('#' + filters[i]).val().trim() + '/'; 
        			}
        			
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
	
	function getUriRegion() {
		try {
			
			var patt = new RegExp("region-([^/]*)");
			var region = patt.exec(window.location.href);
			
			if (region[1] != null) {
				return region[1];
			} else {
				return null;
			}
			
		}
		catch (e) {
		    return null;
		}
	}        
    
	function initRegionChanger() {
		uriRegion = getUriRegion();
		
		if (uriRegion != null) {
			$("#arlo-filter-region").bind("change", function () {
				changeRegion(uriRegion, jQuery("#arlo-filter-region").val());
			});		
		}
		
	}        
    
    function changeRegion(uriRegion, newRegion) {

	    //Manually set cookie
    	$.cookie("arlo-region", newRegion, { path: "/" });
    	$.cookie("arlo-regionch", "1", { path: "/" });

	    window.location.href = window.location.href.replace("/region-" + uriRegion + "/", "/region-" + newRegion + "/");
	}	
	
	$(document).ready(function() {
		initRegionChanger();
	});

}(jQuery));