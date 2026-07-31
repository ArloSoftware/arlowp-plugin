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
		});
	});

}(jQuery, window.LS.Api, window.Arlo));