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
			var $link = $('<a>', { href: '#', 'class': 'arlo-show-more-link' }).text(showText);
			var $container = $('<div>', { 'class': 'arlo-show-more-link-container' }).append($link);
			$('.arlo-show-more-hidden').before($container);
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
				page += 'region-' + encodeURIComponent($('#arlo-filter-region').val()) + '/';
			}

			if ($(this).val() != "") {
				page += 'location-' + $(this).val() + '/';
			}

            document.location = page;
        });				
                
        $('.arlo-filters > select').change(function() {
        	var filters = {
        		'search': 'arlo-filter-search',
        		'cat': 'arlo-filter-category',
        		'month': 'arlo-filter-month',
        		'location': 'arlo-filter-location',
        		'delivery': 'arlo-filter-delivery',
        		'eventtag': 'arlo-filter-eventtag',
        		'templatetag': 'arlo-filter-templatetag',
	    		'oatag': 'arlo-filter-oatag',
        		'presenter': 'arlo-filter-presenter',
        		'state': 'arlo-filter-state'
        	};
        	
        	var page = $('#arlo-page').val();
        	
        	if (page[page.length-1] != '/') {
        		page = page + '/';
        	}

			if ($('#arlo-filter-region').length > 0) {
				page += 'region-' + encodeURIComponent($('#arlo-filter-region').val()) + '/';
			}

        	var url = page;
			var urlParams = [];

			for (var i in filters) {
        		if (filters.hasOwnProperty(i) && $('#' + filters[i]).length == 1) {
					var content = $('#' + filters[i]).val().trim();
					if (content != '') {
						if (content.indexOf('/') === -1 || content.indexOf('\\') === -1) {
							if (i == 'search') {
								url += 'search/' + encodeURIComponent(content) + '/'; 
							} else {
								url += i + '-' + encodeURIComponent(content) + '/'; 
							}
						} else {
							//default url structure does not work with slashes
							if (i == 'cat') {
								urlParams.push('arlo-category=' + encodeURIComponent(content));
							} else {
								urlParams.push('arlo-' + i + '=' + encodeURIComponent(content));
							}
						}
					}
        		} 
			}

			if (urlParams.length > 0) {
				url += '?' + urlParams.join('&');
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


		//modal init
		$('.arlo-sessions-popup-trigger').each(function(i, el) {
			// The shortcode emits the popup content beside its trigger, so only an element with the target's id inside
			// the trigger's parent counts. A trigger without content of its own is left alone, so it cannot stop the
			// triggers after it or open another row's content.
			// themes/theme.z/js/data.js repeats this lookup for the rows a Show more appends, because this script is loaded
			// with every theme; keep the two in step.
			var target = String($(el).attr('data-target') || '').trim();
			var $content = target.length < 2 || target.charAt(0) !== '#' ? $() : $(el).parent().find('[id]').filter(function(j, candidate) {
				return candidate.id === target.slice(1);
			});
			var popupContent = $content.length === 1 ? $content.html().trim() : '';
			if (!popupContent) {
				// The trigger's empty link would reload the page.
				$(el).click(function(e) {
					e.preventDefault();
				});
				return;
			}

			var modal = new tingle.modal({
				footer: true,
				cssClass: ['arlo'],
			    closeMethods: ['overlay', 'button', 'escape']
			});

			modal.setContent(popupContent);

			modal.addFooterBtn('Close', 'arlo-close-btn button btn btn-primary', function() {
			    modal.close();
			});

			$(el).click(function(e) {
				e.preventDefault();
				modal.open()
			});
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
    	Cookies.set("arlo-region", newRegion, { path: "/", domain: window.location.hostname });

    	if (uriRegion) {
		    window.location.href = window.location.href.replace("/region-" + uriRegion + "/", "/region-" + encodeURIComponent(newRegion) + "/").replace(/location-\w+(%\d+)?\w*/g,"");
    	} else {
    		window.location.reload();
    	}
	}	
	
	$(document).ready(function() {
		initRegionChanger();
	});

}(jQuery));