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
	var reviewed = null;
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
	// True while the results on screen are the findings of a completed check.
	var showingFindings = false;
	function resetResults() {
		showingFindings = false;
		if (results) {
			results.textContent = '';
			results.classList.remove('arlo-css-analysis-issues');
		}
		if (change) {
			change.disabled = false;
		}
		syncEmpty();
	}
	// Findings stay on screen while the administrator works through them, so only say that they are from the last check.
	function onEdit() {
		if (!showingFindings || isBlank()) {
			resetResults();
			return;
		}
		if (results && !results.querySelector('.arlo-css-stale')) {
			var note = document.createElement('p');
			note.className = 'arlo-css-stale';
			note.textContent = arloCssMigration.stale;
			results.insertBefore(note, results.firstChild);
		}
		if (change) {
			// Migrating checks the CSS again, so a fix does not have to wait for a separate check.
			change.disabled = false;
		}
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
	textarea.addEventListener('input', onEdit);
	if (editor) {
		editor.on('change', onEdit);
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

	// Columns are one-based byte columns; CodeMirror counts UTF-16 characters, so a line with non-ASCII text needs converting.
	function characterIndex(lineText, column) {
		var bytes = 0;
		var index = 0;
		while (index < lineText.length && bytes < column - 1) {
			var code = lineText.codePointAt(index);
			bytes += code < 0x80 ? 1 : (code < 0x800 ? 2 : (code < 0x10000 ? 3 : 4));
			index += code > 0xffff ? 2 : 1;
		}
		return index;
	}

	// Puts the cursor in the editor where a finding was reported, and brings it into view.
	function goToPosition(line, column) {
		var lines;
		var offset = 0;
		var target;
		var index;
		if (editor) {
			target = Math.min(Math.max(line - 1, 0), editor.lastLine());
			index = characterIndex(editor.getLine(target), column);
			editor.focus();
			editor.setCursor({ line: target, ch: index });
			editor.scrollIntoView({ line: target, ch: index }, 80);
			return;
		}
		// Without the code editor the field is a plain textarea, whose value always uses LF.
		lines = textarea.value.split(/\r\n|\r|\n/);
		target = Math.min(Math.max(line - 1, 0), lines.length - 1);
		for (var i = 0; i < target; i++) {
			offset += lines[i].length + 1;
		}
		offset += characterIndex(lines[target], column);
		textarea.focus();
		textarea.setSelectionRange(offset, offset);
	}

	// The "Line N, pos M" at the start of a finding is a link that moves the cursor there; if the translated text does not start with it, the text is shown plain.
	function appendLocated(item, finding) {
		var full = finding.column > 1 ?
			wp.i18n.sprintf(arloCssMigration.linePosition, finding.line, finding.column, finding.message) :
			wp.i18n.sprintf(arloCssMigration.line, finding.line, finding.message);
		var label = finding.column > 1 ?
			wp.i18n.sprintf(arloCssMigration.posLabel, finding.line, finding.column) :
			wp.i18n.sprintf(arloCssMigration.lineLabel, finding.line);
		if (full.indexOf(label) !== 0) {
			item.textContent = full;
			return;
		}
		var anchor = document.createElement('a');
		anchor.href = '#arlo_customcss';
		anchor.textContent = label;
		var hint = document.createElement('span');
		hint.className = 'screen-reader-text';
		hint.textContent = ' ' + arloCssMigration.goTo;
		anchor.appendChild(hint);
		anchor.addEventListener('click', function (event) {
			event.preventDefault();
			goToPosition(finding.line, finding.column);
		});
		item.appendChild(anchor);
		item.appendChild(document.createTextNode(full.slice(label.length)));
	}

	// A finding the migration fixes on its own shows what it will do after a green arrow. The arrow is decorative; "Fix:" is read in its place.
	function appendFix(item, finding) {
		if (typeof finding.fix !== 'string' || finding.fix === '') {
			return;
		}
		var arrow = document.createElement('span');
		arrow.className = 'dashicons dashicons-arrow-right-alt arlo-css-fix-arrow';
		arrow.setAttribute('aria-hidden', 'true');
		var hint = document.createElement('span');
		hint.className = 'screen-reader-text';
		hint.textContent = arloCssMigration.fixHint + ' ';
		item.appendChild(document.createTextNode(' '));
		item.appendChild(arrow);
		item.appendChild(document.createTextNode(' '));
		item.appendChild(hint);
		item.appendChild(document.createTextNode(finding.fix));
	}

	// A "Must fix" list leads each item with the caution icon, in place of a bullet.
	function appendGroup(heading, findings, caution) {
		if (!findings.length) {
			return;
		}
		var title = document.createElement('strong');
		title.textContent = heading;
		results.appendChild(title);
		var list = document.createElement('ul');
		if (caution) {
			list.className = 'arlo-css-must-fix';
		}
		findings.forEach(function (finding) {
			var item = document.createElement('li');
			if (finding.line) {
				appendLocated(item, finding);
			} else {
				appendMessage(item, finding);
			}
			appendFix(item, finding);
			if (caution) {
				// Added after the text is built, because the text is set with textContent. Hidden from assistive technology: the "Must fix" heading carries the meaning.
				var icon = document.createElement('span');
				icon.className = 'dashicons dashicons-warning arlo-css-notice-icon';
				icon.setAttribute('aria-hidden', 'true');
				item.insertBefore(icon, item.firstChild);
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

	function hasAdjustments(data) {
		return data.findings.some(function (finding) {
			return finding.fixable === true;
		});
	}

	// confirm: the adjustments are being shown because the migrate button was selected, so say that nothing has been migrated yet.
	function showFindings(data, confirm) {
		showingFindings = true;
		results.classList.add('arlo-css-analysis-issues');
		results.textContent = '';
		var blocking = data.findings.filter(function (finding) {
			return finding.level === 'blocking';
		});
		// Brackets the migration rewrites for the administrator; they never block.
		var adjusted = data.findings.filter(function (finding) {
			return finding.level !== 'blocking' && finding.fixable === true;
		});
		var other = data.findings.filter(function (finding) {
			return finding.level !== 'blocking' && finding.fixable !== true;
		});
		appendGroup(arloCssMigration.mustFix, blocking, true);
		appendGroup(arloCssMigration.autoFixed, adjusted);
		appendGroup(arloCssMigration.warnings, other);
		var advice = document.createElement('p');
		if (data.blocked) {
			advice.textContent = arloCssMigration.blocked;
		} else if (confirm) {
			advice.textContent = arloCssMigration.reviewFirst;
		} else if (adjusted.length && !other.length) {
			advice.textContent = arloCssMigration.noAction;
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
		showingFindings = false;
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
		showingFindings = false;
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
				// What will be adjusted is shown before anything is migrated, once for each text, so it is never a surprise.
				// A second attempt for the same text goes ahead; so does one made after the check button showed it.
				if (hasAdjustments(data) && reviewed !== checked) {
					reviewed = checked;
					showFindings(data, true);
					return;
				}
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
		showingFindings = false;
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
					// The adjustments are on screen for this text, so migrating it next does not need to show them again.
					if (hasAdjustments(data)) {
						reviewed = checked;
					}
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
