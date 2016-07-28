(function ( $, LS, LSApi ) {
	"use strict";

	$(function () {
	
		var pluginSlug = 'arlo-for-wordpress';
		var editor = null;
		var tabIDs = document.location.hash.replace('#', '').split('/');
		var taskQueryStack = {};
	
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
		
		function reNumberRegions() {
			$("#arlo-regions li .arlo-order-number").each(function(index) {				
				$(this).html((index + 1) + '.');
			})
		}
		
		function addRegion() {
	    	var newElement = $('#arlo-region-empty ul li').clone();
	    	if (newElement.length == 1) {
	    		$('#arlo-regions').append(newElement);
	    	}		
		}

		// add novalidate to disable html5 validation, the html5 validation will still work if javascript is disabled
		$(document).ready(function() {
			$('#arlo-settings').attr('novalidate','novalidate');
			$('.arlo-section').hide();
			
			if (ArloImmediateTaskIDs != null && ArloImmediateTaskIDs.length > 0) {
				kickOffScheduler();
			}
			
			var ArloTasksIDs = ArloRunningTaskIDs.concat(ArloImmediateTaskIDs);
			
			if (ArloTasksIDs != null && ArloTasksIDs.length > 0) {
				for(var i in ArloTasksIDs) {
					if (ArloTasksIDs.hasOwnProperty(i)) {
						createTaskPlaceholder(ArloTasksIDs[i]);	
					}
				}
			}
			
			
			
			showNavTab(tabIDs[0]);
			
			if (typeof tabIDs[1] !== 'undefined') {
				showVerticalNavTab(tabIDs[1]);
			}
			
			$( "#arlo-regions" ).sortable({
				placeholder: "arlo-region-highlight",
				update: reNumberRegions
		    });
		    $( "#arlo-regions" ).disableSelection();	
		    
		    $('#arlo-regions').on('click', 'li .icons8-minus', function () {
		    	$(this).parentsUntil("li").parent().remove();
		    	if ($('#arlo-regions > li').length === 0) {
		    		addRegion();
		    	}
		    	reNumberRegions();
		    });
		    
		    $('#arlo-regions').on('click', 'li .icons8-plus', function () {
		    	addRegion();
		    	reNumberRegions();
		    });
		    
		});
		
		function createTaskPlaceholder(taskID) {
			var header = $('.arlo-wrap > h2');
			
			var content = $("<div>").addClass("notice arlo-task").attr("id", "arlo-task-" + taskID).html("<p>Task: <span class='desc'></span></p>");
			header.after(content);
			getTaskInfo(taskID);
			taskQueryStack[taskID] = setInterval(function() { getTaskInfo(taskID) }, 1500);
		}
		
		function getTaskInfo(taskID) {
			var data = {
				action: 'arlo_get_task_info',
				taskID: taskID
			}
				
			jQuery.post(ajaxurl, data, function(response) {
				var task = {};
				if (response[0] != null) {
					task = response[0];
					if (task.task_id == taskID) {
						var taskPlaceholder = $("#arlo-task-" + taskID);
						
						taskPlaceholder.find(".desc").html(task.task_status_text);
						
						if (task.task_status > 1) {
							taskPlaceholder.addClass("is-dismissible");
							
							taskPlaceholder.addClass(task.task_status == 3 ? "notice-success" : "notice-error");
							
							clearInterval(taskQueryStack[taskID]);
							
							setTimeout(function() {
								if (task.task_status == 3) {
									taskPlaceholder.fadeOut(function() {
										$(this).remove()
									});								
								}
							}, 6000)
						}
					}
				}
			}, 'json');
		}
		
		function showNavTab(tabID) {
			$('.arlo-section').hide();
			$('.nav-tab-wrapper.main-tab .nav-tab').removeClass('nav-tab-active');

			if ($('.arlo_' + tabID + '_section').length == 0) {
				tabID = 'welcome';
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
					} else {
						showVerticalNavTab(tabIDs[1]);
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
		
		function markPageSetupError() {
			$('.arlo-page-select > select').each(function() {
				if ($(this).val() == '' || $(this).val() == '0') {
					$(this).addClass('arlo-error');
				}
			});
		}
		
		//go to the pages section
		$('.arlo-pages-setup').click(function() {
			tabIDs = ['pages','events'];
			showNavTab(tabIDs[0]);
			markPageSetupError();
			scrollTo(0,10000);
		});	
		
		//remove error from the select
		$('.arlo-page-select > select').change(function() {
			if ($(this).val() == '' || $(this).val() == '0') {
				$(this).addClass('arlo-error');
			} else {
				$(this).removeClass('arlo-error');
			}
		});
		
		//go to the general section
		$('#arlo-connet-platform').click(function () {
			tabIDs = ['general'];
			showNavTab(tabIDs[0]);	
			scrollTo(0,10000);
			$('#arlo_platform_name').focus().select();	
		});
		
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
			var template = jQuery('.arlo-field-wrap:visible').attr('id');
			var templateSufix = $(".arlo-sub-template-select:visible > select:visible").val();			
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
		
		function kickOffScheduler() {
			var data = {
				action: 'start_scheduler'
			}
				
			jQuery.post(ajaxurl, data, function(response) {
			});
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