(function ( $, LS, LSApi ) {
	"use strict";

	$(function () {
	
		//show the selected template editor
		var selectedTemplate = $('#arlo-template-select select').val();
		if (selectedTemplate == '') {
			selectedTemplate = event;
		}
		
		$('.arlo_template_section .' + selectedTemplate).show();

		// enable tooltips

		$('[data-tooltip]').darkTooltip({
			animation: 'fadeIn',
			gravity: 'east',
		});

		// prevent tooltip buttons from submitting the form

		$('[data-tooltip]').on('click touch', function(e) {
			e.preventDefault();
		});

		// show template editor on select change

		$('#arlo-template-select select').on('change', function() {
			var temp = '.'+$(this).val();
			$('.arlo_template_section > [class^="arlo"]').hide();
			//tinyMCE.DOM.setStyle(tinyMCE.DOM.get($(this).val() + '_ifr'), 'height', '400px');
			$(temp).show();
		});

		// basic validation

		// add novalidate to disable html5 validation, the html5 validation will still work if javascript is disabled
		$(document).ready(function() {
			$('#arlo-settings').attr('novalidate','novalidate');
		});

		// on field blur
		$('.arlo-validate').on('blur', function() {
			arloValidate(this);
		});

		// after set period after last keypress
		$('.arlo-validate').on('keyup', function() {
			var el = this;
			delay(function(){
				arloValidate(el);
			}, 1000 );
		});

		// on form submit
		$('#arlo-settings').on('submit', function(e) {

			// check each field
			$('.arlo-validate').each(function() {
				arloValidate(this);
			});

			var valid = true;
			
			// if any fields are invalid...
			if($('.arlo-validate.invalid').length > 0) {
				valid = false;
				$('.invalid').first().focus();
			}
			return valid;
		});

		// checks the input value against the specified pattern
		function arloValidate(el) {

			if($(el).attr('required') !== undefined || $(el).val() != '') {
				var val = $(el).val();
				var pattern = new RegExp($(el).attr('pattern'));
				if(pattern.test(val)) {
					$(el).removeClass('invalid');
				} else {
					$(el).addClass('invalid');
				}
			}
		}

		// delay function for keyup events
		var delay = (function(){
			var timer = 0;
			return function(callback, ms){
				clearTimeout (timer);
				timer = setTimeout(callback, ms);
			};
		})();
		
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
			
			if (templateSufix.length > 0) {
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
		jQuery('.settings_page_arlo-for-wordpress .notice.is-dismissible .notice-dismiss').click(function() {
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