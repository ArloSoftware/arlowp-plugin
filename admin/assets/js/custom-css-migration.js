/* global arloCssMigration, wp */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	var textarea = document.getElementById('arlo_customcss');
	var check = document.getElementById('arlo-check-css-migration');
	var change = document.getElementById('arlo-css-migration-change');
	var migrateForm = document.getElementById('arlo-css-migrate-form');
	var results = document.getElementById('arlo-css-analysis');
	var discardForm = document.getElementById('arlo-css-discard-form');
	var discardToggle = document.getElementById('arlo-css-discard-toggle');
	var discardPanel = document.getElementById('arlo-css-discard-controls');

	// Each control set stays hidden until its link is used, so a destructive choice is never one click away.
	[
		['arlo-css-revert-toggle', 'arlo-css-migration-controls'],
		['arlo-css-discard-toggle', 'arlo-css-discard-controls']
	].forEach(function (ids) {
		var toggle = document.getElementById(ids[0]);
		var panel = document.getElementById(ids[1]);
		if (toggle && panel) {
			toggle.addEventListener('click', function () {
				panel.hidden = !panel.hidden;
				toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
			});
		}
	});

	if (!textarea) {
		return;
	}

	var initial = textarea.value.replace(/\r\n/g, '\n');
	var submitting = false;
	var preflightPassed = false;
	var editorElement = textarea.parentNode.querySelector('.CodeMirror');
	var editor = editorElement && editorElement.CodeMirror;

	function contents() {
		return editor ? editor.getValue() : textarea.value;
	}
	function dirty() {
		return contents().replace(/\r\n/g, '\n') !== initial;
	}
	function isBlank() {
		return contents().trim() === '';
	}
	// With nothing in the editor a migration can only be refused, so the only thing left to offer is discarding.
	function syncEmpty() {
		if (!isBlank()) {
			return;
		}
		if (change) {
			change.disabled = true;
		}
		if (results) {
			results.classList.add('arlo-css-analysis-issues');
			results.textContent = arloCssMigration.empty;
		}
		if (discardToggle && discardPanel && discardPanel.hidden) {
			discardToggle.click();
		}
	}
	function resetResults() {
		if (results) {
			results.textContent = '';
			results.classList.remove('arlo-css-analysis-issues');
		}
		if (change) {
			change.disabled = false;
		}
		syncEmpty();
	}

	// The editor is a scratchpad, so leaving with edits in it loses them.
	window.addEventListener('beforeunload', function (event) {
		if (!submitting && dirty()) {
			event.preventDefault();
			event.returnValue = arloCssMigration.unsaved;
		}
	});
	if (discardForm) {
		// Discarding deliberately gives up whatever is in the editor, so it must not trigger the unsaved-changes warning.
		discardForm.addEventListener('submit', function () {
			submitting = true;
		});
	}
	if (migrateForm) {
		migrateForm.addEventListener('submit', function (event) {
			if (editor) {
				editor.save();
			}
			if (preflightPassed || !check || !results) {
				submitting = true;
				return;
			}
			// Check first so a blocked migration explains itself here instead of through a generic notice after the redirect.
			event.preventDefault();
			preflight();
		});
	}
	textarea.addEventListener('input', resetResults);
	if (editor) {
		editor.on('change', resetResults);
		// CodeMirror measures itself when created, which is while this tab is hidden; repaint when it is shown.
		var repaint = function () {
			window.setTimeout(function () {
				editor.refresh();
			}, 0);
		};
		window.addEventListener('hashchange', repaint);
		document.querySelectorAll('.nav-tab-wrapper .nav-tab').forEach(function (tab) {
			tab.addEventListener('click', repaint);
		});
		repaint();
	}
	syncEmpty();

	if (!check || !results) {
		return;
	}

	function appendGroup(heading, findings) {
		if (!findings.length) {
			return;
		}
		var title = document.createElement('strong');
		title.textContent = heading;
		results.appendChild(title);
		var list = document.createElement('ul');
		findings.forEach(function (finding) {
			var item = document.createElement('li');
			if (finding.line) {
				item.textContent = finding.column > 1 ?
					wp.i18n.sprintf(arloCssMigration.linePosition, finding.line, finding.column, finding.message) :
					wp.i18n.sprintf(arloCssMigration.line, finding.line, finding.message);
			} else {
				appendMessage(item, finding);
			}
			list.appendChild(item);
		});
		results.appendChild(list);
	}

	// Builds the message from text nodes only, so a finding can never inject markup; its first mention of the link text becomes a link.
	function appendMessage(item, finding) {
		var link = finding.link;
		if (!link || !link.text || !/^https?:\/\//i.test(String(link.url))) {
			item.textContent = finding.message;
			return;
		}
		var at = finding.message.indexOf(link.text);
		var before = at === -1 ? finding.message + ' ' : finding.message.slice(0, at);
		var after = at === -1 ? '' : finding.message.slice(at + link.text.length);
		var anchor = document.createElement('a');
		anchor.href = link.url;
		anchor.target = '_blank';
		anchor.rel = 'noopener noreferrer';
		anchor.textContent = link.text;
		var hint = document.createElement('span');
		hint.className = 'screen-reader-text';
		hint.textContent = ' ' + arloCssMigration.newTab;
		anchor.appendChild(hint);
		item.appendChild(document.createTextNode(before));
		item.appendChild(anchor);
		item.appendChild(document.createTextNode(after));
	}

	function showFindings(data) {
		results.classList.add('arlo-css-analysis-issues');
		results.textContent = '';
		var blocking = data.findings.filter(function (finding) {
			return finding.level === 'blocking';
		});
		var other = data.findings.filter(function (finding) {
			return finding.level !== 'blocking';
		});
		appendGroup(arloCssMigration.mustFix, blocking);
		appendGroup(arloCssMigration.warnings, other);
		var advice = document.createElement('p');
		if (data.blocked) {
			advice.textContent = arloCssMigration.blocked;
		} else if (data.status === 'clear' && data.findings.length) {
			advice.textContent = arloCssMigration.clearNotes;
		} else {
			advice.textContent = arloCssMigration[data.status];
		}
		results.appendChild(advice);
		if (change) {
			change.disabled = data.blocked;
		}
	}

	function showFailure() {
		results.classList.add('arlo-css-analysis-issues');
		results.textContent = '';
		var title = document.createElement('strong');
		title.textContent = arloCssMigration.checkFailed;
		results.appendChild(title);
		var message = document.createElement('p');
		message.textContent = arloCssMigration.failed;
		results.appendChild(message);
	}

	function requestCheck() {
		var payload = new FormData();
		payload.append('action', 'arlo_check_custom_css_migration');
		payload.append('nonce', arloCssMigration.nonce);
		payload.append('css', contents());
		return fetch(arloCssMigration.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function (response) {
				var data = response.data;
				if (!response.success || !data || !Array.isArray(data.findings) ||
					['clear', 'warning', 'unknown'].indexOf(data.status) === -1) {
					throw new Error('Invalid analysis response');
				}
				return data;
			});
	}

	function preflight() {
		var checked = contents();
		results.classList.remove('arlo-css-analysis-issues');
		results.textContent = arloCssMigration.checking;
		check.disabled = true;
		change.disabled = true;
		requestCheck()
			.then(function (data) {
				// Edits made while the check ran were never checked, so nothing is submitted until the administrator asks again.
				if (contents() !== checked) {
					resetResults();
					results.classList.add('arlo-css-analysis-issues');
					results.textContent = arloCssMigration.edited;
					return;
				}
				if (data.blocked) {
					showFindings(data);
					return;
				}
				// The server runs the same check again before it writes anything.
				preflightPassed = true;
				submitting = true;
				if (editor) {
					editor.save();
				}
				migrateForm.submit();
			})
			.catch(function () {
				showFailure();
				change.disabled = false;
			})
			.finally(function () {
				check.disabled = false;
			});
	}

	check.addEventListener('click', function () {
		var checked = contents();
		// A response for CSS that has since been edited says nothing about what is in the editor now.
		var stale = function () {
			return contents() !== checked;
		};
		results.classList.remove('arlo-css-analysis-issues');
		results.textContent = arloCssMigration.checking;
		check.disabled = true;
		requestCheck()
			.then(function (data) {
				if (stale()) {
					return;
				}
				if (data.status === 'clear' && !data.findings.length) {
					results.textContent = arloCssMigration.clear;
					if (change) {
						change.disabled = false;
					}
				} else {
					showFindings(data);
				}
			})
			.catch(function () {
				if (!stale()) {
					showFailure();
				}
			})
			.finally(function () {
				check.disabled = false;
			});
	});
});
