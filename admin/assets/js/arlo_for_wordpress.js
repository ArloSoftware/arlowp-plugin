

if (typeof (Arlo) === "undefined") {
	Arlo = {};
}

(function(Arlo, $) {
	'use strict';

	Arlo.ArloForWordPress = function(config) {
		$.extend(this, config);

		this.tabIDs = document.location.hash.replace('#', '').split('/');
	}

	Arlo.ArloForWordPress.prototype = {
		VERSION: '3.0',
		apiClient: null,
		pluginSlug: 'arlo-for-wordpress',
		tabIDs: [],
		editor: null,
		selectedTemplate: 'arlo-event',
		ajaxUrl: null,
		bluePrints: null,
		immediateTaskIDs: [],
		runningTaskIDs: [],
		init: function() {
			var me = this;

			$('.arlo_pages_section .' + me.selectedTemplate).show();

			$('#arlo-settings').attr('novalidate','novalidate');
			$('.arlo-section').hide();

			me.initTabNavigation();

			me.checkTasks();
			me.showNavTab(me.tabIDs[0]);

			if (typeof me.tabIDs[1] !== 'undefined') {
				me.showVerticalNavTab(me.tabIDs[1]);
			}

			me.initRegionFields();

			me.getLastImportLog();

			me.initEvents();
		},
		initRegionFields: function() {
			var me = this;

			$( "#arlo-regions" ).sortable({
				placeholder: "arlo-region-highlight",
				update: me.reNumberRegions
			});
			$( "#arlo-regions" ).disableSelection();	
			
			$('#arlo-regions').on('click', 'li .icons8-minus', function () {
				$(this).parentsUntil("li").parent().remove();
				if ($('#arlo-regions > li').length === 0) {
					me.addRegion();
				}
				me.reNumberRegions();
			});
			
			$('#arlo-regions').on('click', 'li .icons8-plus', function () {
				me.addRegion();
				me.reNumberRegions();
			});
		},
		checkTasks: function() {
			var me = this,
				tasksIDs = me.runningTaskIDs.concat(me.immediateTaskIDs);

			if (me.immediateTaskIDs != null && me.immediateTaskIDs.length > 0) {
				me.kickOffScheduler();
			}
			
			if (tasksIDs != null && tasksIDs.length > 0) {
				for(var i in tasksIDs) {
					if (tasksIDs.hasOwnProperty(i)) {
						me.createTaskPlaceholder(tasksIDs[i]);	
					}
				}
			}
		},
		initCodeMirror: function() {
			var me = this;
			if (me.editor === null) {			
				me.editor = CodeMirror.fromTextArea( 
					document.getElementById( "arlo_customcss" ), 
					{
						lineNumbers: true, 
						lineWrapping: true
					}
				);
			}
		},
		reNumberRegions: function() {
			$("#arlo-regions li .arlo-order-number").each(function(index) {				
				$(this).html((index + 1) + '.');
			})
		},
		addRegion: function() {
			var newElement = $('#arlo-region-empty ul li').clone();
			if (newElement.length == 1) {
				$('#arlo-regions').append(newElement);
			}
		},
		createTaskPlaceholder: function(taskID) {
			var me = this,
				header = $('.arlo-wrap > h2'),
				content = $("<div>").addClass("notice arlo-task").attr("id", "arlo-task-" + taskID).html("<p>Background task: <span class='desc'></span></p>"),
				taskPlaceholder;

			header.after(content);
			taskPlaceholder = $("#arlo-task-" + taskID);
			
			taskPlaceholder.addClass("is-dismissible");
			taskPlaceholder.find(".desc").after('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');							
			
			//terminate background task
			taskPlaceholder.find("button").click(function() {
				if (!(taskPlaceholder.hasClass('notice-success') || taskPlaceholder.hasClass('notice-error'))) {
					var message = "Do you really want to terminate the current running background process?";
					if (confirm(message)) {
						me.terminateTask(taskID);
					}				
				} else {
					taskPlaceholder.fadeOut(function() {
						$(this).remove();
					});
				}
			});
			
			me.getTaskInfo(taskID);
		},
		terminateTask: function (taskID) {
			var me = this,
				data = {
					action: 'arlo_terminate_task',
					taskID: taskID
				},
				taskPlaceholder = $("#arlo-task-" + taskID);
			
			if (taskPlaceholder.length > 0 && !(taskPlaceholder.hasClass('notice-success') || taskPlaceholder.hasClass('notice-error'))) {
				$.post(me.ajaxUrl, data);
			}
		},
		getTaskInfo: function(taskID) {
			var me = this,
				data = {
					action: 'arlo_get_task_info',
					taskID: taskID
				},
				taskPlaceholder = $("#arlo-task-" + taskID);
						
			$.ajax({
				url: me.ajaxUrl,
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
									setTimeout(function() { me.getTaskInfo(taskID) }, 2000);				
								break;
								case "3":
								case "4":
									if (task.task_task == 'import') {
										$('.arlo-sync-button').fadeIn();

										me.getLastImportLog(function(response) {
											if (response.successful == 1) {
												$('.arlo-last-sync-date').fadeOut().html(response.last_import + ' UTC').fadeIn();
												
												//dismiss only, if the sync is not terminated by the user
												if (task.task_status_text.indexOf('terminate') == -1) {
													$('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message.arlo-import_error .notice-dismiss').trigger('click');
												}
											} else {
												taskPlaceholder.find(".desc").after(": <span>" + response.message + "</span>");
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
		},
		kickOffScheduler: function() {
			var me = this,
				data = {
					action: 'arlo_start_scheduler'
				}
				
			$.post(me.ajaxUrl, data);
		},
		arloReloadTemplate: function() {
			var me = this,
				template = $('.arlo-field-wrap:visible').attr('id'),
				templateSufix = $(".arlo-sub-template-select:visible > select:visible").val(),			
				editor = $('#' + template.replace("arlo-",""));
			
			if (typeof templateSufix !== "undefined" && templateSufix.length > 0) {
				template += '-'+templateSufix; 
			}
			
			if (me.bluePrints[template] != null && editor.length) {
				$(editor).val(me.bluePrints[template]);
			} else {
				alert("Couldn't find the template!");
			}
		},
		arloReloadTemplateConfirm: function(previousSubTemplate) {
			var me = this,
				message = "Do you really want to replace the existing template with the original one?";

			if (confirm(message)) {
				me.arloReloadTemplate();
			} else if (previousSubTemplate != null) {
				$(".arlo-sub-template-select > select:visible").val(previousSubTemplate);
			}
		},
		markPageSetupError: function() {
			$('.arlo-page-select > select').each(function() {
				if ($(this).val() == '' || $(this).val() == '0') {
					$(this).addClass('arlo-error');
				}
			});
		},
		showVerticalNavTab:function (tabID) {
			var me = this;

			$('.arlo_pages_section .arlo-field-wrap').hide();
			$('.arlo_pages_section .nav-tab').removeClass('nav-tab-active');

			if ($('.arlo-' + tabID).length == 0) {
				tabID = me.selectedTemplate;
			}
			
			$('.arlo_pages_section .arlo-' + tabID).show();
			$('.arlo-' + tabID + ' .' + me.pluginSlug + '-pages-' + tabID).addClass('nav-tab-active');			
		},
		showNavTab: function(tabID) {
			var me = this;

			$('.arlo-section').hide();
			$('.nav-tab-wrapper.main-tab .nav-tab').removeClass('nav-tab-active');

			if ($('.arlo_' + tabID + '_section').length == 0) {
				tabID = 'welcome';
			}
			
			$('.arlo_' + tabID + '_section').show();
			$('#' + me.pluginSlug + '-tab-' + tabID).addClass('nav-tab-active');
			
			switch (tabID) {
				case 'customcss':
					me.initCodeMirror();
				break;
				
				case 'pages':
					if (typeof(me.tabIDs[1]) === 'undefined') {
						document.location.hash += '/event';
						me.showVerticalNavTab('event');
					} else {
						me.showVerticalNavTab(me.tabIDs[1]);
					}
					
				break;
			}
		},
		getLastImportLog: function (callback, successful) {
			var me = this,
				data = {
					action: 'arlo_get_last_import_log'
				};
			
			if (successful) {
				data.successful = 1;
			} 
			
			$.post(me.ajaxUrl, data, function(response) {
				if ($.isFunction(callback)) {
					callback(response);
				}
			}, 'json');
		},
		initTabNavigation: function() {
			var me = this,
				tabIDs = [];	

			//go to the pages section
			$('.arlo-pages-setup').click(function() {
				tabIDs = ['pages','events'];
				me.showNavTab(tabIDs[0]);
				me.markPageSetupError();
				scrollTo(0,10000);
			});

			//go to the pages section
			$('.arlo-pages-systemrequirements').click(function() {
				tabIDs = ['systemrequirements'];
				me.showNavTab(tabIDs[0]);
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

			//go to any section
			$('.arlo-settings-link').click(function() {
				var id = $(this).attr('id').split('_').pop();
				me.showNavTab(id);	
				scrollTo(0,10000);
			});
			
			//go to the general section
			$('#arlo-connet-platform').click(function () {
				tabIDs = ['general'];
				me.showNavTab(tabIDs[0]);	
				scrollTo(0,10000);
				$('#arlo_platform_name').focus().select();	
			});
			
			//nav-bar
			$('.nav-tab-wrapper.main-tab .nav-tab').click(function() {
				var tabID = $(this).attr('id').split('-').pop();
				me.showNavTab(tabID);
			});		
			
			$('.arlo_pages_section .nav-tab').click(function() {
				var tabID = $(this).attr('id').split('-').pop();
				me.showVerticalNavTab(tabID);
			});
		},
		initEvents: function() {
			var me = this;		

			$('#arlo-page-select select').on('change', function() {
				var temp = '.' + $(this).val();
				$('.arlo_pages_section > [class^="arlo"]').hide();
				$(temp).show();
			});	
			
			// show confirm message to reload the template from the blueprint		
			$('.arlo-reload-template').on('click', function() {
				me.arloReloadTemplateConfirm();
			});
			
			var previousSubTemplate;
			$(".arlo-sub-template-select > select").on('focus', function () {
				previousSubTemplate = this.value;
			}).change(function() {
				me.arloReloadTemplateConfirm(previousSubTemplate);
			});				

			//check numeric field
			$("#arlo_import_fragment_size").keypress(function(event) {
				// Backspace, tab, enter, end, home, left, right
				// We don't support the del key in Opera because del == . == 46.
				var controlKeys = [8, 9, 13, 35, 36, 37, 39];
				// IE doesn't support indexOf
				var isControlKey = controlKeys.join(",").match(new RegExp(event.which));
				// Some browsers just don't raise events for control keys. Easy.
				// e.g. Safari backspace.
				if (!event.which || // Control keys in most browsers. e.g. Firefox tab is 0
					(49 <= event.which && event.which <= 57) || // Always 1 through 9
					(48 == event.which && $(this).attr("value")) || // No 0 first digit
					isControlKey) { // Opera assigns values for control keys.
					return;
				} else {
					event.preventDefault();
				}
			});
			
			//dismissible message
			$('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-dismiss').click(function() {
				var id = $(this).parent().attr('id').split('-').pop();
				if (id != null) {
					var data = {
						action: 'arlo_dismiss_message',
						id: id
					}
					
					$.post(me.ajaxUrl, data);
				}
			})		
			
			
			//dismissible admin notices
			$('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-user-dismissable-message .notice-dismiss').click(function() {
				var id = $(this).parent().attr('id');
				if (id != null) {
					var data = {
						action: 'arlo_dismissible_notice',
						id: id
					}
					
					$.post(me.ajaxUrl, data);
				}
			})	

			//turn off arlo_send_data
			$('#arlo_turn_off_send_data').click(function() {
				var el = $(this),
					data = {
						action: 'arlo_turn_off_send_data'
					}
				
				$.post(me.ajaxUrl, data, function() {
					el.parentsUntil('.arlo-message').parent().find('.notice-dismiss').trigger('click');
					$('#arlo_send_data').removeAttr("checked");
				});
			});					
		},
		getEventsForWebinar: function() {
			var me = this,
				arloApiClient = new me.apiClient.ApiClient({
					platformID: "presentations"
				}),
				eventSearchOptions = {
					fields: ['ViewUri', 'RegistrationInfo', 'StartDateTime'],
					filter: { templateCode: 'LEAR1'},
					top: 1
				},
				loadAPIResultsSuccess = function(data) {				
					if (data.Items != null && data.Count == 1) {
						var item = data.Items[0];
						var date = item.StartDateTime.substr(0,10);
						var time = item.StartDateTime.substr(11,5);
						
						$('#webinar_date').html(date + ' ' + time + ' NZDT');
						
						$('#webinar_template_url').attr('href', item.ViewUri);
						
						$('.webinar_url').attr('href', item.RegistrationInfo.RegisterUri);
					} else {
						$(".arlo-webinar").html('or <a href="https://www.arlo.co/contact" target="_blank">Contact us!</a>');
					}				

					$('#arlo-webinar-admin-notice').fadeIn();					
				},loadAPIResultsError = function(error) {
					console.log(error);
				},callback = {
					success: loadAPIResultsSuccess,
					error: loadAPIResultsError
				}
			
			arloApiClient.getResources().getEventSearchResource().searchEvents(eventSearchOptions, callback);
		}
	}
})(Arlo, jQuery);

