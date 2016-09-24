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
		    
		    getLastImportLog();
		    
		});
		
		function createTaskPlaceholder(taskID) {
			var header = $('.arlo-wrap > h2');
			
			var content = $("<div>").addClass("notice arlo-task").attr("id", "arlo-task-" + taskID).html("<p>Background task: <span class='desc'></span></p>");
			header.after(content);
			
			var taskPlaceholder = $("#arlo-task-" + taskID);

			taskPlaceholder.addClass("is-dismissible");
			taskPlaceholder.find(".desc").after('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');							
			
			//terminate background task
			taskPlaceholder.find("button").click(function() {
				if (!(taskPlaceholder.hasClass('notice-success') || taskPlaceholder.hasClass('notice-error'))) {
					var message = "Do you really want to terminate the current running background process?";
					if (confirm(message)) {
						terminateTask(taskID);
					}				
				} else {
					taskPlaceholder.fadeOut(function() {
						$(this).remove();
					});
				}
			});
			
			getTaskInfo(taskID);
		}
		
		function terminateTask(taskID) {
			var data = {
				action: 'arlo_terminate_task',
				taskID: taskID
			},
			taskPlaceholder = $("#arlo-task-" + taskID);
			
			if (taskPlaceholder.length > 0 && !(taskPlaceholder.hasClass('notice-success') || taskPlaceholder.hasClass('notice-error'))) {
				jQuery.post(ajaxurl, data);
			}
		}
		
		function getTaskInfo(taskID) {
			var data = {
				action: 'arlo_get_task_info',
				taskID: taskID
			},
			taskPlaceholder = $("#arlo-task-" + taskID);
						
						
			jQuery.ajax({
				url: ajaxurl,
				data: data,
				method: 'post',
				dataType: 'json',
				success: function(response) {
					var task = {};
					if (response[0] != null) {
						task = response[0];
						if (task.task_id == taskID) {							
							taskPlaceholder.find(".desc").html(task.task_status_text);
														
							switch(task.task_status) {
								case "0":
								case "1": 
								case "2": 
									if (task.task_task == 'import') {
										$('.arlo-sync-button').fadeOut('fast');
									}
									setTimeout(function() { getTaskInfo(taskID) }, 2000);				
								break;
								case "3":
								case "4":
									if (task.task_task == 'import') {
										$('.arlo-sync-button').fadeIn();
										getLastImportLog(function(response) {
											if (response.successful == 1) {
												jQuery('.arlo-last-sync-date').fadeOut().html(response.last_import + ' UTC').fadeIn();
											} else {
												taskPlaceholder.find(".desc").after(": <span>" + response.message.replace(/\d{4,}/, '') + "</span>");
											}
										}, task.task_status == 4);
									}
																		
									taskPlaceholder.addClass(task.task_status == 4 ? "notice-success" : "notice-error");
																
									setTimeout(function() {
										if (task.task_status == 4) {
											taskPlaceholder.fadeOut(function() {
												$(this).remove()
											});								
										}
									}, 10000);
								break;
							}							
						}
					}
				}			
			});
		}
		
		function getLastImportLog(callback, sucessful) {
			var data = {
				action: 'arlo_get_last_import_log'
			};
			
			if (sucessful) {
				data.sucessful = 1;
			} 
			
			jQuery.post(ajaxurl, data, function(response) {
				if (jQuery.isFunction(callback)) {
					callback(response);
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
				action: 'arlo_start_scheduler'
			}
				
			jQuery.post(ajaxurl, data);
		}
		
		//dismissible message
		jQuery('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message .notice-dismiss').click(function() {
			var id = jQuery(this).parent().attr('id').split('-').pop();
			if (id != null) {
				var data = {
					action: 'arlo_dismiss_message',
					id: id
				}
				
				jQuery.post(ajaxurl, data);
			}
		})		
		
		
		//dismissible admin notices
		jQuery('.toplevel_page_arlo-for-wordpress .notice.is-dismissible:not(.arlo-message) .notice-dismiss').click(function() {
			var id = jQuery(this).parent().attr('id');
			if (id != null) {
				var data = {
					action: 'arlo_dismissible_notice',
					id: id
				}
				
				jQuery.post(ajaxurl, data);
			}
		})		
		
	});

}(jQuery, window.LS, window.LS.Api));