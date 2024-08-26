(function ( $, LSApi, Arlo) {
	"use strict";

	$(function () {
		$(document).ready(function() {
			var config = {
				apiClient: LSApi,
				ajaxUrl: window.ajaxurl,
				templates: window.arlo_templates,
				immediateTaskIDs: window.ArloImmediateTaskIDs,
				runningTaskIDs: window.ArloRunningTaskIDs
			}, 
			arloForWordpress = new Arlo.ArloForWordPress(config); 

			arloForWordpress.init();

			//get events for the webinar
			var webinarNotice = jQuery('#arlo-webinar-admin-notice');
			if (webinarNotice.length > 0) {
				arloForWordpress.getEventsForWebinar();
			}
		});
	});

}(jQuery, window.LS.Api, window.Arlo));