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
		templates: null,
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
			me.initFilterSettingsFields();

			me.getLastImportLog();

			me.initEvents();

			me.showFilterGroupSettings($('#arlo-filter-settings').val());

			$('#arlo-filter-settings').change(function() {
				me.showFilterGroupSettings($(this).val());
			});

			//clear cookies
			Cookies.remove("arlo-vertical-tab", { path: '/' });
			Cookies.remove("arlo-nav-tab", { path: '/' });
		},
		initRegionFields: function() {
			var me = this;

			$( "#arlo-regions" ).sortable({
				placeholder: "arlo-region-highlight",
				update: me.reNumberRegions
			});
			$( "#arlo-regions" ).disableSelection();	
			
			$('#arlo-regions').on('click', 'li .arlo-icons8-minus', function () {
				$(this).parentsUntil("li").parent().remove();
				if ($('#arlo-regions > li').length === 0) {
					me.addRegion();
				}
				me.reNumberRegions();
			});
			
			$('#arlo-regions').on('click', 'li .arlo-icons8-plus', function () {
				me.addRegion();
				me.reNumberRegions();
			});
		},
		initFilterSettingsFields: function() {
			var me = this;

			$( ".arlo-filter-group" ).disableSelection();	
			
			$('.arlo-filter-group').on('click', 'li .arlo-icons8-minus', function () {
				var parent = $(this).closest('.arlo-available-filters');
				$(this).parentsUntil("li").parent().remove();
				
				if (parent.find('li').length === 0) {
					me.addFilter(parent);
				}
			});
			
			$('.arlo-filter-group').on('click', 'li .arlo-icons8-plus', function () {
				var parent = $(this).closest('.arlo-available-filters');
				me.addFilter(parent);
			});
		},
		filterActionChange: function() {
			$('.arlo-filter-action select').each(function() {
				var val = $(this).val();
				if (val == 'rename') {
					$(this).closest('li').find('.arlo-filter-new-value').show();
				}
			});
			$('.arlo-filter-action select').change(function() {
				var val = $(this).val()
				if (val == 'rename') {
					$(this).closest('li').find('.arlo-filter-new-value').show();
				} else {
					$(this).closest('li').find('.arlo-filter-new-value').hide();
				}
			});
			$('.arlo_pages_section .arlo-filter-action select').change(function() {
				var val = $(this).val();
				if (val == 'exclude' || val == 'showonly') {
					$(this).closest('.arlo-available-filters').find('.arlo-filter-action select').val(val);
				}
			});
		},
		showFilterGroupSettings: function(val) {
			$('.arlo-filter-group:not(.arlo-always-visible)').hide();
			$("#arlo-" + val + "-filters").show();
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
		addFilter: function(parent) {
			var newElement = parent.parent().find('#arlo-filter-empty ul li').clone();

			var setting_id = Math.floor(Math.random() * 1000000);

			newElement.find('input, select').each( function(index, element) {
				var name = $(element).attr('name').replace('setting_id',setting_id);
				$(element).attr('name',name);
			});

			if (newElement.length == 1) {
				newElement.find('.arlo-filter-action select').val(parent.find('.arlo-filter-action select').val());
				parent.append(newElement);
				this.filterActionChange();
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
				editor = $('#' + template.replace("arlo-",""));
			
			if (typeof templateSufix !== "undefined" && templateSufix.length > 0) {
				template += '-'+templateSufix; 
			}
			
			if (me.templates[template] != null && editor.length) {
				$(editor).val(me.templates[template]);
			} else {
				alert("Couldn't find the template!");
			}
		},
		arloReloadTemplateConfirm: function() {
			var me = this,
				message = "Do you really want to replace the existing template with the original one?";

			if (confirm(message)) {
				me.arloReloadTemplate();
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

			if (tabID !== 'new_custom') {
				Cookies.set("arlo-vertical-tab", tabID, { path: '/', domain: window.location.hostname, expires: 7 });
			}

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
				tabID = 'theme';
			}

			$('.arlo_' + tabID + '_section').show();
			$('#' + me.pluginSlug + '-tab-' + tabID).addClass('nav-tab-active');
			
			Cookies.remove("arlo-vertical-tab", { path: '/' });
			Cookies.set("arlo-nav-tab", tabID, { path: '/', domain: window.location.hostname, expires: 7 });

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
			
			setTimeout(function() {document.location.hash = tabID},1);

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
				scrollTo(0,jQuery('#arlo-settings').offset().top)
			});

			//go to the pages section
			$('.arlo-pages-systemrequirements').click(function() {
				tabIDs = ['systemrequirements'];
				me.showNavTab(tabIDs[0]);
				scrollTo(0,jQuery('#arlo-settings').offset().top)
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
				scrollTo(0,jQuery('#arlo-settings').offset().top)
			});
			
			//go to the general section
			$('#arlo-connet-platform').click(function () {
				tabIDs = ['general'];
				me.showNavTab(tabIDs[0]);	
				scrollTo(0,jQuery('#arlo-settings').offset().top)
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
			
			// show confirm message to reload the template from the theme		
			$('.arlo-reload-template').on('click', function() {
				me.arloReloadTemplateConfirm();
			});

			//check numeric field
			$(".arlo-only-numeric").keypress(function(event) {
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

			$('.arlo-delete-button').click(function(e){
				e.preventDefault();

				if (confirm("Are you sure you want to delete this custom shortcode across all the themes?")) {
					document.location = $(e.target).attr('href');
				} 				
			});

			$('#arlo-settings').on('submit', function() {
				var customShortcodeType = $('.arlo-new-custom-shortcode-type'),
					customShortcodeName = $('.arlo-new-custom-shortcode-name'),
					errors = false,
					requiredFieldError = 'Field is required',
					re = new RegExp(/^\w+$/),
					input = customShortcodeName.find('input').val();

				if (customShortcodeName.find('input').val() == '' && customShortcodeType.val() == '') {
					return true;
				}

				if (customShortcodeName.find('input').val() == '') {
					errors = true;
					showValidationError(customShortcodeName, requiredFieldError);
				}

				if (customShortcodeType.val() == '') {
					errors = true;
					showValidationError(customShortcodeType, requiredFieldError);
				}

				if ( arlo_shortcodes.indexOf( input ) !== -1 ) {
					errors = true;
					showValidationError(customShortcodeName, 'A shortcode with that name already exists');
				}

				if ( !re.test(input) ) {
					errors = true;
					showValidationError(customShortcodeName, 'Shortcode names must only contain letters, numbers and underscores');
				}

				function showValidationError(field, message) {
					$('.arlo-new-custom-shortcode-error').remove();
					field.after('<span class="arlo-new-custom-shortcode-error red">' + message + '</span>');
				}

				if (errors) {
					return false;
				}

				return true;
			});

			//dismissible message
			$('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-dismiss, .toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-dismiss-custom').click(function() {
				var id = $(this).closest('.notice.is-dismissible.arlo-message').attr('id');
				if (id != null) {
					var data = {
						action: 'arlo_dismiss_message',
						id: id
					}
					
					$.post(me.ajaxUrl, data);
				}
			})

			$('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-dismiss-custom, .toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-ask-later').click(function(e) {
				e.preventDefault();
				$(this).closest('.arlo-message').fadeOut(function() {
					$(this).closest('.arlo-message').remove();
				});
			});
			
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
			});

			$('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-ask-later').click(function() {
				var data = {
					action: 'arlo_increment_review_notice_date'
				}
				
				$.post(me.ajaxUrl, data);
			});

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

			$(".theme-apply").click(function(e) {
				var target = $(e.currentTarget),
					message;

				e.preventDefault();

				if (target.hasClass('theme-reset')) {
					message = "Warning: Delete / reset customisation! Are you sure you want to delete / reset any customisations you have made and reapply the default theme? You will not be able to recover any customisations if you proceed."
				} else {
					message = "Warning: Change theme! Are you sure you want to change your existing theme? Any customisations you have made previously to the chosen theme will be reapplied."
				}

				if (confirm(message)) {
					document.location = target.attr('href');
				} 				
			});

			this.filterActionChange();

			$('.arlo-filter-section-toggle').click(function() {
				$(this).closest('.arlo-filter-settings').find('.arlo-available-filters').slideToggle();
				$(this).closest('.arlo-filter-settings').toggleClass('filter-section-expanded');
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

