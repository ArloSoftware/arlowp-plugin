(function ( $, LS, LSApi ) {
	"use strict";

	$(function () {
	
		var pluginSlug = 'arlo-for-wordpress';
		var editor = null;
		var tabIDs = document.location.hash.replace('#', '').split('/');
	
		//show the selected template editor
		var selectedTemplate = 'arlo-event';
		
		$('.arlo_pages_section .' + selectedTemplate).show();

		// show template editor on select change

		$('#arlo-template-select select').on('change', function() {
			var temp = '.'+$(this).val();
			$('.arlo_pages_section > [class^="arlo"]').hide();
			//tinyMCE.DOM.setStyle(tinyMCE.DOM.get($(this).val() + '_ifr'), 'height', '400px');
			$(temp).show();
		});

		// basic validation
		
		function initCodeMirror() {
			if (editor === null) {			
				editor = CodeMirror.fromTextArea( 
					document.getElementById( "arlo_customcss" ), 
					{
						lineNumbers: true, 
						lineWrapping: true
					}
				);		
			}
		}

		// add novalidate to disable html5 validation, the html5 validation will still work if javascript is disabled
		$(document).ready(function() {
			$('#arlo-settings').attr('novalidate','novalidate');
			$('.arlo-section').hide();
			
			showNavTab(tabIDs[0]);
			
			if (typeof tabIDs[1] !== 'undefined') {
				showVerticalNavTab(tabIDs[1]);
			}
		});
		
		function showNavTab(tabID) {
			$('.arlo-section').hide();
			$('.nav-tab-wrapper.main-tab .nav-tab').removeClass('nav-tab-active');

			if ($('.arlo_' + tabID + '_section').length == 0) {
				tabID = 'general';
			}
			
			$('.arlo_' + tabID + '_section').show();
			$('#' + pluginSlug + '-tab-' + tabID).addClass('nav-tab-active');
			
			switch (tabID) {
				case 'customcss':
					initCodeMirror();
				break;
				
				case 'pages':
					if (typeof(tabIDs[1]) === 'undefined') {
						document.location.hash += '/event';
						showVerticalNavTab('event');
					}
					
				break;
			}
		}
		
		function showVerticalNavTab(tabID) {
			$('.arlo_pages_section .arlo-field-wrap').hide();
			$('.arlo_pages_section .nav-tab').removeClass('nav-tab-active');

			if ($('.arlo-' + tabID).length == 0) {
				tabID = selectedTemplate;
			}
			
			$('.arlo_pages_section .arlo-' + tabID).show();
			$('.arlo-' + tabID + ' .' + pluginSlug + '-pages-' + tabID).addClass('nav-tab-active');			
		}		
		
		//nav-bar
		$('.nav-tab-wrapper.main-tab .nav-tab').click(function() {
			var tabID = $(this).attr('id').split('-').pop();
			showNavTab(tabID);
		});		
		
		$('.arlo_pages_section .nav-tab').click(function() {
			var tabID = $(this).attr('id').split('-').pop();
			showVerticalNavTab(tabID);
		});			
		
		// show confirm message to reload the template from the blueprint		
		$('.arlo-reload-template').on('click', function() {
			arloReloadTemplateConfirm()
		});
		
		var previousSubTemplate;
		$(".arlo-sub-template-select > select").on('focus', function () {
			previousSubTemplate = this.value;
		}).change(function() {
			arloReloadTemplateConfirm();
		});		
				
		function arloReloadTemplateConfirm() {
			var template = $('#arlo-template-select > select').val();
			var templateName = $("#arlo-template-select > select > option[value='" + template + "']").text();
			var message = "Do you really want to replace the existing template with the original one for '" + templateName + "'?";
			if (confirm(message)) {
				arloReloadTemplate();
			} else if (previousSubTemplate != null) {
				$(".arlo-sub-template-select > select:visible").val(previousSubTemplate);
			}
		}
		
		// reload the template from the blueprint
		function arloReloadTemplate() {
			var template = $('#arlo-template-select > select').val();
			var templateSufix = $(".arlo-sub-template-select > select:visible").val();			
			var editor = $('#' + template.replace("arlo-",""));
			
			if (typeof templateSufix !== "undefined" && templateSufix.length > 0) {
				template += '-'+templateSufix; 
			}
			
			if (arlo_blueprints[template] != null && editor.length) {
				$(editor).val(arlo_blueprints[template]);
			} else {
				alert("Couldn't find the template!");
			}
		}
				
		//get events for the webinar
		var webinarNotice = jQuery('#arlo-webinar-admin-notice');
		if (webinarNotice.length > 0) {
			var arloApiClient = new LS.Api.ApiClient({
				platformID: "presentations"
			});
			
			var eventSearchOptions = {
	            fields: ['ViewUri', 'RegistrationInfo', 'StartDateTime'],
	            filter: { templateCode: 'ARLO9'},
	            top: 1
	        };
	        
			var loadAPIResultsSuccess = function(data) {				
				if (data.Items != null && data.Count == 1) {
					var item = data.Items[0];
					var date = item.StartDateTime.substr(0,10);
					var time = item.StartDateTime.substr(11,5);
					
					jQuery('#webinar_date').html(date + ' ' + time + ' NZDT');
					
					jQuery('#webinar_template_url').attr('href', item.ViewUri);
					
					jQuery('.webinar_url').attr('href', item.RegistrationInfo.RegisterUri);
					
					jQuery('#arlo-webinar-admin-notice').fadeIn();
				}				
			}
			
			var loadAPIResultsError = function(error) {
				console.log(error);
			}	        
	        
	        var callback = {
				success: loadAPIResultsSuccess,
				error: loadAPIResultsError
	       	}
			
			arloApiClient.getResources().getEventSearchResource().searchEvents(eventSearchOptions, callback);
			
			
		}
		
		//dismissible admin notices
		jQuery('.toplevel_page_arlo-for-wordpress .notice.is-dismissible .notice-dismiss').click(function() {
			var id = jQuery(this).parent().attr('id');
			if (id != null) {
				var data = {
					action: 'dismissible_notice',
					id: id
				}
				
				jQuery.post(ajaxurl, data, function(response) {
				});
			}
		})		
		
	});

}(jQuery, window.LS, window.LS.Api));