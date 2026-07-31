if (typeof (Arlo) === "undefined") {
	Arlo = {};
}

(function(Arlo, $) {
	'use strict';

	Arlo.ArloForWordPress = function(config) {
		$.extend(this, config);

		this.tabIDs = document.location.hash.replace('#', '').split('/').map(function(id) {
			return (id && /^[a-zA-Z0-9_-]+$/.test(id)) ? id : undefined;
		});
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
		/**
		 * Maps each list-page template key to the entity detail-page tab whose base
		 * URL is a sub-route under the list page's host-page assignment.
		 *
		 * Detail tabs (Event, Presenter, Venue) represent WP custom post type records
		 * (arlo_event / arlo_presenter / arlo_venue). They have no host-page dropdown
		 * of their own, so when a list host page changes, the dependent detail tab's
		 * slug row must cascade-update to reflect the new base path.
		 */
		slugDependents: { events: 'event', presenters: 'presenter', venues: 'venue' },
		init: function() {
			var me = this;

			try {
				$('.arlo_pages_section .arlo-' + me.getDefaultVerticalTab()).show();

				$('#arlo-settings').attr('novalidate','novalidate');
				$('.arlo-section').hide();

				me.initTabNavigation();

				me.checkTasks();

				// Initialise shortcode-check state before showing any tab so that
				// showVerticalNavTab can safely call checkPanelOnFirstVisit.
				me.initShortcodeChecks();

				// When there is no hash in the URL (e.g. after a form-save POST/redirect),
				// fall back to the saved tab cookie. If only the legacy vertical cookie is
				// present, treat it as a Pages sub-tab selection.
				var savedTab = me.getSavedTabParts();
				var initialTab = me.tabIDs[0] || savedTab[0];
				var initialVerticalTab = me.tabIDs[1] || savedTab[1];
				me.showNavTab(initialTab, initialVerticalTab);

				me.initRegionFields();
				me.initFilterSettingsFields();
				me.initDeploymentModeField();
				me.getLastImportLog();

				me.initEvents();

				me.showFilterGroupSettings($('#arlo-filter-settings').val());

				$('#arlo-filter-settings').change(function() {
					me.showFilterGroupSettings($(this).val());
				});

				me.cleanupLegacyTabCookies();
			} catch (e) {
				console.error(e);
				$('.arlo-section, p.submit').show();
			} finally {
				$('.arlo-sections-wrap').removeClass('arlo-initializing');
			}

		},
		sanitizeTabID: function(id) {
			return (id && /^[a-zA-Z0-9_-]+$/.test(id)) ? id : '';
		},
		getSavedTabParts: function() {
			var me = this;
			var currentTab = Cookies.get('arlo-current-tab');
			var savedTab = currentTab ? currentTab.split('/').map(function(id) {
				return me.sanitizeTabID(id);
			}) : [];

			if (savedTab[0]) {
				return savedTab;
			}

			var legacyMainTab = me.sanitizeTabID(Cookies.get('arlo-nav-tab'));
			var legacyVerticalTab = me.sanitizeTabID(Cookies.get('arlo-vertical-tab'));

			if (!legacyMainTab && legacyVerticalTab) {
				legacyMainTab = 'pages';
			}

			return [legacyMainTab, legacyVerticalTab];
		},
		getDefaultVerticalTab: function() {
			var me = this;
			var defaultTab = '';

			$('.arlo_pages_section .nav-tab').each(function() {
				var elementID = $(this).attr('id') || '';
				var candidateTab = me.sanitizeTabID(elementID.split('-').pop());

				if (candidateTab && candidateTab !== 'new_custom') {
					defaultTab = candidateTab;
					return false;
				}
			});

			return defaultTab || me.sanitizeTabID((me.selectedTemplate || '').replace(/^arlo-/, ''));
		},
		resolveVerticalTab: function(tabID) {
			var me = this;
			var verticalTab = me.sanitizeTabID(tabID);

			if (verticalTab && $('.arlo_pages_section .arlo-' + verticalTab).length > 0) {
				return verticalTab;
			}

			return me.getDefaultVerticalTab();
		},
		persistCurrentTab: function(tabID, verticalTab) {
			var cookieValue = tabID;

			if (verticalTab) {
				cookieValue += '/' + verticalTab;
			}

			Cookies.set('arlo-current-tab', cookieValue, { path: '/', expires: 7 });
		},
		cleanupLegacyTabCookies: function() {
			['arlo-nav-tab', 'arlo-vertical-tab'].forEach(function(name) {
				Cookies.remove(name, { path: '/' });
				Cookies.remove(name, { path: '/', domain: window.location.hostname });
			});
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
				header = $('.arlo-wrap .arlo-page-header'),
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
					taskID: taskID,
					nonce: admin_ajax_var.nonce
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
					taskID: taskID,
					nonce: admin_ajax_var.nonce
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
							taskPlaceholder.find(".desc").text(task.task_status_text);
														
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
												$('.arlo-last-sync-date').fadeOut().text(response.last_import + ' UTC').fadeIn();
												
												//dismiss only, if the sync is not terminated by the user
												if (task.task_status_text.indexOf('terminate') == -1) {
													$('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message.arlo-import_error .notice-dismiss').trigger('click');
												}
											} else {
												taskPlaceholder.find(".desc").after($('<span>').text(': ' + response.message));
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
					action: 'arlo_start_scheduler',
					nonce: admin_ajax_var.nonce
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
			var verticalTab = me.resolveVerticalTab(tabID);

			if (verticalTab === 'new_custom') {
				me.persistCurrentTab('pages');
			} else {
				me.persistCurrentTab('pages', verticalTab);
			}

			$('.arlo_pages_section .arlo-field-wrap').hide();
			$('.arlo_pages_section .nav-tab').removeClass('nav-tab-active');
			
			$('.arlo_pages_section .arlo-' + verticalTab).show();
			$('.arlo-' + verticalTab + ' .' + me.pluginSlug + '-pages-' + verticalTab).addClass('nav-tab-active');

			// Lazy shortcode check: only fires the first time this panel is opened.
			me.checkPanelOnFirstVisit(verticalTab);
		},
		showNavTab: function(tabID, requestedVerticalTab) {
			var me = this;
			var verticalTab = null;
			tabID = me.sanitizeTabID(tabID);
			requestedVerticalTab = me.sanitizeTabID(requestedVerticalTab);

			$('.arlo-section').hide();
			$('.nav-tab-wrapper.main-tab .nav-tab').removeClass('nav-tab-active');

			if ($('.arlo_' + tabID + '_section').length == 0) {
				tabID = 'theme';
			}

			$('.arlo_' + tabID + '_section').show();
			$('#' + me.pluginSlug + '-tab-' + tabID).addClass('nav-tab-active');

			switch (tabID) {
				case 'customcss':
					me.persistCurrentTab(tabID);
					me.initCodeMirror();
				break;
				
				case 'pages':
					verticalTab = me.resolveVerticalTab(requestedVerticalTab);
					me.showVerticalNavTab(verticalTab);
				break;

				default:
					me.persistCurrentTab(tabID);
				break;
			}

			// Include the vertical segment in the hash so a refresh on #pages/oa
			// returns to #pages/oa rather than losing the vertical tab.
			setTimeout(function() {
				document.location.hash = verticalTab ? tabID + '/' + verticalTab : tabID;
			}, 1);

		},
		getLastImportLog: function (callback, successful) {
			var me = this,
				data = {
					action: 'arlo_get_last_import_log',
					nonce: admin_ajax_var.nonce
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
				me.showNavTab(tabIDs[0], tabIDs[1]);
				me.markPageSetupError();
				scrollTo(0,jQuery('#arlo-settings').offset().top)
			});

			//go to the pages section
			$('.arlo-pages-systemrequirements').click(function() {
				tabIDs = ['systemrequirements'];
				me.showNavTab(tabIDs[0]);
				scrollTo(0,jQuery('#arlo-settings').offset().top)
			});				
			
			//remove error from the select; also trigger shortcode presence check
			$('.arlo-page-select > select').change(function() {
				if ($(this).val() == '' || $(this).val() == '0') {
					$(this).addClass('arlo-error');
				} else {
					$(this).removeClass('arlo-error');
				}
				var $sel       = $(this);
				var panelId    = $sel.closest('.arlo-field-wrap[id]').attr('id');
				var templateId = panelId ? panelId.replace('arlo-', '') : '';
				if ( templateId ) {
					me.checkPageShortcode( $sel, templateId );
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
		/**
		 * Initialise shortcode-check state. Checks are deferred until each panel
		 * is first opened — see checkPanelOnFirstVisit().
		 */
		initShortcodeChecks: function() {
			var me = this;
			me.rowState      = {};
			me.checkedPanels = {};
		},
		/**
		 * Fire the host-page shortcode check for a panel the first time it is
		 * opened. Subsequent visits to the same panel are no-ops.
		 *
		 * @param {string} templateId  Vertical-tab / panel key (e.g. "events").
		 */
		checkPanelOnFirstVisit: function( templateId ) {
			var me = this;
			if ( !me.checkedPanels ) { me.checkedPanels = {}; }
			if ( me.checkedPanels[ templateId ] ) { return; }
			me.checkedPanels[ templateId ] = true;
			var $select = $( '#arlo-' + templateId + ' .arlo-page-select > select' );
			if ( $select.length && parseInt( $select.val(), 10 ) > 0 ) {
				me.checkPageShortcode( $select, templateId );
			}
		},
		/**
		 * Fire a debounced AJAX check for a single host-page dropdown row.
		 * Cancels any pending debounce timer and aborts any in-flight request
		 * for the same row before starting a new one.
		 *
		 * @param {jQuery} $select    The host-page <select> element.
		 * @param {string} templateId Template key (e.g. "events", "my_short_code").
		 */
		checkPageShortcode: function( $select, templateId ) {
			var me         = this;
			var currentVal = parseInt( $select.val(), 10 );

			if ( !me.rowState ) {
				me.rowState = {};
			}
			if ( !me.rowState[ templateId ] ) {
				me.rowState[ templateId ] = { debounceTimer: null, activeXhr: null };
			}
			var state = me.rowState[ templateId ];

			clearTimeout( state.debounceTimer );
			state.debounceTimer = null;

			if ( state.activeXhr ) {
				state.activeXhr.abort();
				state.activeXhr = null;
			}

			if ( currentVal <= 0 ) {
				me.renderShortcodeIndicator( $select, 'remove', null );
				me.renderUrlSlugRow( $select, templateId, null, null, null );
				var detailIdClear = me.slugDependents[ templateId ];
				if ( detailIdClear ) { me.updateDetailPageSlugRow( detailIdClear, null ); }
				return;
			}

			me.renderShortcodeIndicator( $select, 'checking', null );

			state.debounceTimer = setTimeout( function() {
				var capturedPostId = parseInt( $select.val(), 10 );
				state.activeXhr = $.post( me.ajaxUrl, {
					action:      'arlo_check_page_shortcode',
					nonce:       admin_ajax_var.nonce,
					post_id:     capturedPostId,
					template_id: templateId
				} )
				.done( function( response ) {
					state.activeXhr = null;
					if ( parseInt( $select.val(), 10 ) !== capturedPostId ) {
						// Stale response - a newer selection is already in flight.
						return;
					}
					var detailIdDone = me.slugDependents[ templateId ];
					if ( response === null || typeof response !== 'object' ) {
						me.renderUrlSlugRow( $select, templateId, null, null, null );
						if ( detailIdDone ) { me.updateDetailPageSlugRow( detailIdDone, null, null ); }
						me.renderShortcodeIndicator( $select, null, 'request_failed', null, null );
						return;
					}
					if ( true !== response.success ) {
						me.renderUrlSlugRow( $select, templateId, null, null, null );
						if ( detailIdDone ) { me.updateDetailPageSlugRow( detailIdDone, null, null ); }
						if ( response.data && response.data.code ) {
							me.renderShortcodeIndicator( $select, null, response.data.code, null, null );
							return;
						}
						me.renderShortcodeIndicator( $select, null, 'request_failed', null, null );
						return;
					}
					me.renderShortcodeIndicator( $select, response.data.shortcode_exists, response.data.reason, response.data.shortcode, response.data.post_id );
					me.renderUrlSlugRow(
						$select, templateId,
						response.data.post_slug   || null,
						response.data.post_status || null,
						response.data.post_id     || null
					);
					if ( detailIdDone ) {
						me.updateDetailPageSlugRow( detailIdDone, response.data.post_slug || null, response.data.post_status || null );
					}
				} )
					.fail( function( xhr, status ) {
					if ( 'abort' === status ) { return; }
					state.activeXhr = null;
					if ( parseInt( $select.val(), 10 ) !== capturedPostId ) {
						return;
					}
					// Hide slug row on any non-stale, non-aborted request failure.
					me.renderUrlSlugRow( $select, templateId, null, null, null );
					var detailIdFail = me.slugDependents[ templateId ];
					if ( detailIdFail ) { me.updateDetailPageSlugRow( detailIdFail, null, null ); }
					if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.code ) {
						me.renderShortcodeIndicator( $select, null, xhr.responseJSON.data.code, null, null );
						return;
					}
					me.renderShortcodeIndicator( $select, null, 'request_failed', null, null );
				} );
			}, 300 );
		},
		getShortcodeCheckReasonText: function( reason ) {
			switch ( reason ) {
				case 'post_not_found':
					return 'Selected page no longer exists';
				case 'not_publishable':
					return 'Selected page is not published';
				case 'unsupported_post_type':
					return 'Selected content is not a page';
				case 'page_builder':
				case 'scan_unavailable':
					return 'Shortcode check unavailable - page content may be managed outside the editor';
				case 'invalid_post_id':
					return 'Selected page is invalid';
				case 'unknown_template':
					return 'Shortcode check unavailable for this page type';
				case 'request_failed':
					return 'Shortcode check failed \u2014 please try again';
				default:
					return '';
			}
		},
		/**
		 * @param {jQuery}                              $select   Host-page <select>.
		 * @param {true|false|null|'checking'|'remove'} state
		 * @param {string|null}                         reason    Reason code for unknown/unsupported states.
		 * @param {string|null}                         shortcode Expected shortcode tag, e.g. '[arlo_event_template_list]'
		 * @param {number|null}                         postId    WordPress post ID, used for the "Edit page" link.
		 */
		renderShortcodeIndicator: function( $select, state, reason, shortcode, postId ) {
			var me         = this;
			var $panel      = $select.closest('.arlo-field-wrap[id]');
			var $existing   = $panel.find('.arlo-sc-check');
			var $pageSelect = $panel.find('.arlo-page-select');

			if ( 'remove' === state ) {
				$existing.remove();
				return;
			}

			var $indicator = $existing.length ? $existing : $('<span class="arlo-sc-check"></span>');
			$indicator.removeClass( 'arlo-sc-check--checking arlo-sc-check--valid arlo-sc-check--invalid arlo-sc-check--unknown' );
			$indicator.removeAttr( 'title' );
			$indicator.removeAttr( 'aria-label' );
			$indicator.removeAttr( 'role' );
			$indicator.empty(); // clear content from any previous --invalid state

			if ( 'checking' === state ) {
				$indicator.addClass( 'arlo-sc-check--checking' )
					.attr( 'aria-label', 'Checking for shortcode' )
					.attr( 'role', 'img' );
			} else if ( true === state ) {
				$indicator.addClass( 'arlo-sc-check--valid' );
				$indicator.attr( 'title', 'Page contains expected shortcode' )
					.attr( 'aria-label', 'Page contains expected shortcode' )
					.attr( 'role', 'img' );
			} else if ( false === state ) {
				$indicator.addClass( 'arlo-sc-check--invalid' );
				// Build message with DOM API — no HTML injection of response data.
				var $icon = $( '<span class="dashicons dashicons-warning" aria-hidden="true"></span>' );
				var $msg  = $( '<span class="arlo-sc-check__msg"></span>' );
				var $code = $( '<code></code>' ).text( shortcode || '' );
				$msg.append(
					document.createTextNode( 'Shortcode ' ),
					$code,
					document.createTextNode( ' was not found on this page.' ),
					$( '<br>' ),
					document.createTextNode( 'Content may not render correctly.' )
				);
				var $links      = $( '<span class="arlo-sc-check__links"></span>' );
				var panelId     = $select.closest( '.arlo-field-wrap[id]' ).attr( 'id' );
				var templateId  = panelId ? panelId.replace( 'arlo-', '' ) : '';
				if ( postId ) {
					var adminBase = me.ajaxUrl.replace( /admin-ajax\.php.*$/, '' );
					var $editLink = $( '<a class="arlo-sc-check__link" target="_blank" rel="noopener noreferrer"></a>' )
						.attr( 'href', adminBase + 'post.php?post=' + parseInt( postId, 10 ) + '&action=edit' )
						.text( 'Edit page' );
					$links.append( $editLink );
				}
				var $checkLink = $( '<a class="arlo-sc-check__link" href="#"></a>' ).text( 'Check again' );
				$checkLink.on( 'click', function( e ) {
					e.preventDefault();
					me.checkPageShortcode( $select, templateId );
				} );
				$links.append( $checkLink );
				$msg.append( $links );
				$indicator.append( $icon, $msg );
			} else {
				// null - indeterminate; show tooltip from reason code.
				$indicator.addClass( 'arlo-sc-check--unknown' );
				var reasonText = me.getShortcodeCheckReasonText( reason );
				if ( reasonText ) {
					$indicator.attr( 'title', reasonText )
						.attr( 'aria-label', reasonText )
						.attr( 'role', 'img' );
				}
			}

			if ( !$existing.length ) {
				$pageSelect.after( $indicator );
			}
		},
		/**
		 * Update the URL slug row for a regular (host-page) template tab.
		 *
		 * View/Preview URLs are constructed locally from window.location and ajaxUrl
		 * rather than from JSON to avoid consuming domain-bearing URLs from the response.
		 *
		 * @param {jQuery}      $select    Host-page <select> element.
		 * @param {string}      templateId Template key e.g. 'events'.
		 * @param {string|null} postSlug   Root-relative path e.g. '/events/' or null.
		 * @param {string|null} postStatus WP post_status or null.
		 * @param {number|null} postId     WP post ID.
		 */
		renderUrlSlugRow: function( $select, templateId, postSlug, postStatus, postId ) {
			var me      = this;
			var $panel  = $select.closest( '.arlo-field-wrap[id]' );
			var $row    = $panel.find( '.arlo-url-slug-row' );
			var $field  = $row.find( '.arlo-url-slug-field' );

			var showableStatuses = [ 'publish', 'draft', 'future', 'pending' ];
			var isVisible = postSlug && postStatus && showableStatuses.indexOf( postStatus ) !== -1;

			if ( ! isVisible ) {
				$row.addClass( 'arlo-url-slug-row--hidden' );
				return;
			}

			$row.removeClass( 'arlo-url-slug-row--hidden' );
			$row.find( '.arlo-url-slug' ).text( postSlug );

			var originalSlug = $field.data( 'originalSlug' ) || '';
			var isChanged    = ( postSlug !== originalSlug );

			// (Updated) badge
			$row.find( '.arlo-url-slug-updated' ).toggle( isChanged );

			// Private badge — visible when the page is assigned but not yet published
			$row.find( '.arlo-url-slug-private' ).toggle(
				[ 'draft', 'future', 'pending' ].indexOf( postStatus ) !== -1
			);

			// Links — rebuild every time; clear first
			var $links = $row.find( '.arlo-url-slug-links' );
			$links.empty();

			if ( ! isChanged ) {
				// Construct URLs from trusted browser/ajaxUrl sources — NOT from JSON.
				var adminBase = me.ajaxUrl.replace( /admin-ajax\.php.*$/, '' );

				// Guard: only use postSlug in a URL if it is genuinely root-relative.
				var slugIsSafe = postSlug && /^\//.test( postSlug );

				if ( postStatus === 'publish' && slugIsSafe ) {
					$links.append(
						$( '<a class="arlo-sc-check__link" target="_blank" rel="noopener noreferrer"></a>' )
							.attr( 'href', window.location.origin + postSlug )
							.text( 'View page' )
					);
				} else if ( [ 'draft', 'future', 'pending' ].indexOf( postStatus ) !== -1 && postId ) {
					// home_url_path is the path component of get_home_url(), normalised to
					// end with '/'. On standard installs it is '/'; on WP-in-directory
					// installs (core files in /wp/, site at /) it correctly reflects home.
					var homePath = ( admin_ajax_var.home_url_path || '/' );
					$links.append(
						$( '<a class="arlo-sc-check__link" target="_blank" rel="noopener noreferrer"></a>' )
							.attr( 'href', window.location.origin + homePath + '?page_id=' + parseInt( postId, 10 ) + '&preview=true' )
							.text( 'Preview page' )
					);
				}
				if ( postId ) {
					$links.append(
						$( '<a class="arlo-sc-check__link" target="_blank" rel="noopener noreferrer"></a>' )
							.attr( 'href', adminBase + 'post.php?post=' + parseInt( postId, 10 ) + '&action=edit' )
							.text( 'Edit page' )
					);
				}
			}
			// If isChanged: no links emitted (links already cleared above)
		},
		/**
		 * Refresh the URL slug row on an entity detail-page tab (event/presenter/venue).
		 *
		 * Detail tabs represent individual WP custom post type records (arlo_event,
		 * arlo_presenter, arlo_venue). They have no host-page dropdown of their own —
		 * their URLs are always sub-routes under the corresponding list page. This
		 * function is called whenever the parent list page's host-page assignment changes.
		 *
		 * @param {string}           detailId       Template key: 'event', 'presenter', 'venue'.
		 * @param {string|null}      listPageSlug   Root-relative slug of the list page, or null.
		 * @param {string|undefined} listPageStatus WP post_status of the list page, or undefined
		 *                                          when the "(None)" option was selected.
		 */
		updateDetailPageSlugRow: function( detailId, listPageSlug, listPageStatus ) {
			// Detail-page tab rows use id="arlo-url-slug-detail-{id}". Scope to the panel
			// class rather than the ID so this works even if the DOM is re-rendered.
			var $detailPanel = $( '.arlo_pages_section .arlo-' + detailId );
			var $row         = $detailPanel.find( '.arlo-url-slug-row' );
			var $field        = $row.find( '.arlo-url-slug-field' );

			// When called without a listPageStatus argument (undefined) the dropdown
			// was set to "(None)" — post_id is 0, no page is assigned at all.
			// Show the fallback /arlo/... pattern so the row stays informative.
			//
			// When listPageStatus is anything else (null or a real status string) the
			// call came from an AJAX response where a page IS assigned but the slug
			// is unavailable (private, trash, non-page type, draft without slug yet,
			// or deleted post). Hide the row rather than guessing.
			var noPageAssigned = ( typeof listPageStatus === 'undefined' );

			if ( ! listPageSlug ) {
				if ( noPageAssigned ) {
					var fallback = $field.data( 'fallbackSlug' ) || '';
					var suffix   = $field.data( 'slugSuffix' )   || '';
					var newSlug  = fallback.replace( /\/$/, '' ) + suffix;
					$row.removeClass( 'arlo-url-slug-row--hidden' );
					$row.find( '.arlo-url-slug' ).text( newSlug );
					var originalSlug = $field.data( 'originalSlug' ) || '';
					$row.find( '.arlo-url-slug-updated' ).toggle( newSlug !== originalSlug );
					$row.find( '.arlo-url-slug-private' ).hide();
				} else {
					$row.addClass( 'arlo-url-slug-row--hidden' );
				}
				return;
			}

			// Query-string slugs (plain permalinks) cannot have a path suffix appended.
			if ( listPageSlug.indexOf( '?' ) !== -1 || listPageSlug.indexOf( '#' ) !== -1 ) {
				$row.addClass( 'arlo-url-slug-row--hidden' );
				return;
			}
			var suffix  = $field.data( 'slugSuffix' ) || '';
			var newSlug = listPageSlug.replace( /\/$/, '' ) + suffix;

			$row.removeClass( 'arlo-url-slug-row--hidden' );
			$row.find( '.arlo-url-slug' ).text( newSlug );

			var originalSlug = $field.data( 'originalSlug' ) || '';
			$row.find( '.arlo-url-slug-updated' ).toggle( newSlug !== originalSlug );

			// Private badge — show when the list page is assigned but not yet published.
			$row.find( '.arlo-url-slug-private' ).toggle(
				!! listPageStatus && listPageStatus !== 'publish'
			);

			// Detail pages have no links — nothing to manage there.
		},
		initDeploymentModeField: function() {
			var $sel = $('#arlo_deployment_mode');
			var $hint = $sel.siblings('.arlo-deployment-mode-hint');
			if ($sel.length && $hint.length) {
				$sel.on('change', function() {
					$hint.toggle($sel.val() === 'non_production');
				});
			}
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
						id: id,
						nonce: admin_ajax_var.nonce
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
						id: id,
						nonce: admin_ajax_var.nonce
					}
					
					$.post(me.ajaxUrl, data);
				}
			});

			$('.toplevel_page_arlo-for-wordpress .notice.is-dismissible.arlo-message:not(.arlo-user-dismissable-message) .notice-ask-later').click(function() {
				var data = {
					action: 'arlo_increment_review_notice_date',
					nonce: admin_ajax_var.nonce
				}
				
				$.post(me.ajaxUrl, data);
			});

			//turn off arlo_send_data
			$('#arlo_turn_off_send_data').click(function() {
				var el = $(this),
					data = {
						action: 'arlo_turn_off_send_data',
						nonce: admin_ajax_var.nonce
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

		}
	}
})(Arlo, jQuery);

