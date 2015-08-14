(function ( $ ) {
	"use strict";

	$(function () {

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

	});

}(jQuery));