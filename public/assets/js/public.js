(function ( $ ) {
	"use strict";
	
	var uriRegion;

	$(function () {

		// Place your public-facing JavaScript here
		var showText = objectL10n.showmoredates;
                
		if ($('.arlo-show-more').length == 1 && $('.arlo-show-more[data-show-text]').attr('data-show-text') != null) {
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

        $('.arlo-event-filters > select').change(function() {
			var page = $('#arlo-page').val();

        	if (page[page.length-1] != '/') {
        		page = page + '/';
        	}

			if ($('#arlo-filter-region').length > 0) {
				page += 'region-' + $('#arlo-filter-region').val() + '/';
			}

			if ($(this).val() != "") {
				page += 'location-' + $(this).val() + '/';
			}

            document.location = page;
        });				
                
        $('.arlo-filters > select').change(function() {
        	var filters = {
        		'search-': 'arlo-filter-search',
        		'cat-': 'arlo-filter-category',
        		'month-': 'arlo-filter-month',
        		'location-': 'arlo-filter-location',
        		'delivery-': 'arlo-filter-delivery',
        		'eventtag-': 'arlo-filter-eventtag',
        		'templatetag-': 'arlo-filter-templatetag',
	    		'oatag-': 'arlo-filter-oatag',
        		'presenter-': 'arlo-filter-presenter',
        		'state-': 'arlo-filter-state'
        	};
        	
        	var page = $('#arlo-page').val();
        	
        	if (page[page.length-1] != '/') {
        		page = page + '/';
        	}

			if ($('#arlo-filter-region').length > 0) {
				page += 'region-' + $('#arlo-filter-region').val() + '/';
			}

        	var url = page;
        	
        	for (var i in filters) {
        		if (filters.hasOwnProperty(i) && $('#' + filters[i]).length == 1 && $('#' + filters[i]).val().trim() != '') {
        			if (i == 'search-') {
        				url += 'search/' + encodeURIComponent($('#' + filters[i]).val().trim()) + '/'; 
        			} else {
        				url += i + encodeURIComponent($('#' + filters[i]).val().trim()) + '/'; 
        			}
        		} 
        	}
        	
        	document.location = url;
        });
        
   		//if boxed (grid) layout, make the boxes' height even
		if ($('.arlo-boxed').length) {

	        $(".arlo-boxed .events.arlo-show-more-hidden").show();

			var boxedMaxHeight = 0;
			$('.arlo-boxed .arlo-list li.arlo-cf:not(.arlo-group-divider)').each(function() {
				if ($(this).height() > boxedMaxHeight) {
					boxedMaxHeight = $(this).height()
				}
			});

	        $(".arlo-boxed .events.arlo-show-more-hidden").hide();

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
		
		$(".arlo-filter-region").bind("change", function () {
			changeRegion(uriRegion, $(this).val());
		});		
	}        
    
    function changeRegion(uriRegion, newRegion) {

	    //Manually set cookie
    	Cookies.set("arlo-region", newRegion, { path: "/" });

    	if (uriRegion) {
		    window.location.href = window.location.href.replace("/region-" + uriRegion + "/", "/region-" + newRegion + "/").replace(/location-\w+(%\d+)?\w*/g,"");
    	} else {
    		window.location.reload();
    	}
	}	
	
	$(document).ready(function() {
		initRegionChanger();
	});

}(jQuery));