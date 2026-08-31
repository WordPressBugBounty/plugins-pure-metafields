/**
 * Metafield Builder — adapts the Options Builder rendering engine for metaboxes.
 *
 * State shape:
 *   { metabox: { metabox_id, title, post_type[], context, priority, columns, sections[] },
 *     activeSectionId, dirty, status, statusMsg }
 *
 * Sections/rows/fields structure mirrors the Options Builder exactly so all
 * renderRow / renderFieldCard / Sortable wiring can be reused without changes.
 */
(function () {
	'use strict';

	/* ── Bootstrap ──────────────────────────────────────────────────────── */
	if (typeof TPMFBuilder === 'undefined') return;

	var root, state, sortables = [];
	var history    = { past: [], future: [] };
	var HISTORY_MAX = 50;
	var paletteFilter    = '';
	var STORAGE_KEY = 'tpmeta_mfb_ui_state';
	var allMetaboxes     = [];    // authoritative client-side list
	var currentFieldGroup = '';   // shared Sortable group name — set in setupSortables, read in wirePalette
	// Phase-2 scan state. Initialised from localized data; refreshed via REST after import.
	var themeMetaboxes   = [];
	var themeUserMeta    = null;

	document.addEventListener('DOMContentLoaded', boot);

	/* ── Boot ───────────────────────────────────────────────────────────── */
	function boot() {
		root = document.getElementById('mfb-root');
		if (!root) return;
		if (typeof TPMetaBuilderModal !== 'undefined') TPMetaBuilderModal.init();

		allMetaboxes = JSON.parse(JSON.stringify(TPMFBuilder.metaboxes || []));
		allMetaboxes = allMetaboxes.map(function (m) {
			m.sections = (m.sections || []).map(migrateSection);
			return m;
		});

		// Phase-2: hydrate theme-scan state from localized payload.
		themeMetaboxes = Array.isArray(TPMFBuilder.themeMetaboxes) ? TPMFBuilder.themeMetaboxes.slice() : [];
		themeUserMeta  = TPMFBuilder.themeUserMeta && TPMFBuilder.themeUserMeta.fields ? TPMFBuilder.themeUserMeta : null;

		renderSidebar();
		ensureToastHost();

		if (allMetaboxes.length) {
			var _saved   = loadUIState();
			var _startId = (_saved.metaboxId && allMetaboxes.find(function (m) { return m.metabox_id === _saved.metaboxId; }))
				? _saved.metaboxId
				: allMetaboxes[0].metabox_id;
			loadMetabox(_startId, _saved.sectionId || '');
		} else {
			renderNoneState();
		}

		wireGlobal();
	}

	/* ── Sidebar ─────────────────────────────────────────────────────────── */
	function renderSidebar() {
		var list = document.getElementById('mfb-sidebar-list');
		if (!list) return;

		var themeHtml = renderThemeGroup();

		if (!allMetaboxes.length && !themeHtml) {
			list.innerHTML = '<div class="mfb-sidebar-empty">'
				+ '<span class="dashicons dashicons-editor-table"></span>'
				+ '<p>No metaboxes yet.</p>'
				+ '</div>';
			return;
		}

		var activeId = state ? state.metabox.metabox_id : '';
		var ownHtml  = '';
		if (allMetaboxes.length) {
			ownHtml = '<div class="mfb-sidebar-group-head"><span>' + esc('Your Metaboxes') + '</span><small>(' + allMetaboxes.length + ')</small></div>'
				+ allMetaboxes.map(function (m) {
					var cls    = m.metabox_id === activeId ? ' is-active' : '';
					var isUser = ('user' === m.target);
					var icon   = isUser ? 'admin-users' : 'editor-table';
					var subtitle = isUser
						? ('User Profile' + (m.capability ? ' · ' + m.capability : ''))
						: ((m.post_type || []).slice(0, 3).join(', ') || '—');
					var badge  = isUser
						? '<span class="mfb-target-badge mfb-target-badge--user">USER</span>'
						: '';
					return '<div class="mfb-list-item' + cls + '" data-id="' + esc(m.metabox_id) + '" data-target="' + (isUser ? 'user' : 'post') + '">'
						+ '<div class="mfb-list-icon"><span class="dashicons dashicons-' + icon + '"></span></div>'
						+ '<div class="mfb-list-info">'
						+   '<div class="mfb-list-title">' + esc(m.title || m.metabox_id) + badge + '</div>'
						+   '<div class="mfb-list-pts">' + esc(subtitle) + '</div>'
						+ '</div>'
						+ '<div class="mfb-list-actions">'
						+   '<button class="mfb-list-edit" data-id="' + esc(m.metabox_id) + '" title="Settings">'
						+     '<span class="dashicons dashicons-admin-generic"></span>'
						+   '</button>'
						+   '<button class="mfb-list-delete" data-id="' + esc(m.metabox_id) + '" title="Delete">'
						+     '<span class="dashicons dashicons-trash"></span>'
						+   '</button>'
						+ '</div>'
						+ '</div>';
				}).join('');
		}

		list.innerHTML = themeHtml + ownHtml;
	}

	/* ── Theme-scan group (Phase 2) ──────────────────────────────────────── */
	function renderThemeGroup() {
		var html = '';

		if (themeMetaboxes.length || themeUserMeta) {
			html += '<div class="mfb-sidebar-group-head mfb-theme-head">'
				+ '<span>From Active Theme</span>'
				+ '<small>(' + themeMetaboxes.length + ')</small>'
				+ '<button class="mfb-theme-refresh" id="mfb-theme-refresh" title="Re-scan active theme (cache is reused otherwise)">'
				+   '<span class="dashicons dashicons-update"></span>'
				+ '</button>'
				+ '<span class="dashicons dashicons-info-outline" title="Detected via the tp_meta_boxes filter. Import a copy here to edit it visually. The scan is cached — click the refresh button if you changed your theme code."></span>'
				+ '</div>';
		}

		if (themeMetaboxes.length) {

			html += themeMetaboxes.map(function (m) {
				var pts = (m.post_type || []).slice(0, 3).join(', ') || '—';
				return '<div class="mfb-list-item mfb-theme-item" data-id="' + esc(m.metabox_id) + '">'
					+ '<div class="mfb-list-icon"><span class="dashicons dashicons-download"></span></div>'
					+ '<div class="mfb-list-info">'
					+   '<div class="mfb-list-title">' + esc(m.title || m.metabox_id) + '</div>'
					+   '<div class="mfb-list-pts">' + esc(pts) + '</div>'
					+ '</div>'
					+ '<div class="mfb-list-actions">'
					+   '<button class="mfb-theme-import" data-id="' + esc(m.metabox_id) + '" title="Import a copy into the builder">'
					+     '<span class="dashicons dashicons-plus-alt2"></span>'
					+   '</button>'
					+ '</div>'
					+ '</div>';
			}).join('');
		}

		if (themeUserMeta && themeUserMeta.fields_count) {
			html += '<div class="mfb-sidebar-group-head mfb-theme-head">'
				+ '<span>User Profile Fields</span>'
				+ '<small>(read-only)</small>'
				+ '</div>';
			html += '<div class="mfb-list-item mfb-theme-item is-readonly" title="Detected via tp_user_meta. Storage backend differs from post metaboxes — not editable in this builder yet.">'
				+ '<div class="mfb-list-icon"><span class="dashicons dashicons-admin-users"></span></div>'
				+ '<div class="mfb-list-info">'
				+   '<div class="mfb-list-title">' + esc(themeUserMeta.label || 'User Meta') + '</div>'
				+   '<div class="mfb-list-pts">' + themeUserMeta.fields_count + ' field' + (themeUserMeta.fields_count === 1 ? '' : 's') + '</div>'
				+ '</div>'
				+ '</div>';
		}

		return html;
	}

	function importFromTheme(id) {
		if (!id) return;
		wp.apiFetch({
			url:    TPMFBuilder.restRoot + 'metafields/scan/import',
			method: 'POST',
			data:   { metabox_id: id },
		}).then(function (res) {
			if (!res || !res.imported) { toast('Import failed', 'error', 3000); return; }

			// Add to builder list (or replace if already present by ID).
			var saved = res.imported;
			saved.sections = (saved.sections || []).map(migrateSection);
			var idx = allMetaboxes.findIndex(function (m) { return m.metabox_id === saved.metabox_id; });
			if (idx > -1) allMetaboxes[idx] = saved;
			else          allMetaboxes.push(saved);

			// Refresh theme list with what the server returned (the imported entry
			// is now excluded server-side by the same subtraction logic).
			themeMetaboxes = Array.isArray(res.theme_metaboxes) ? res.theme_metaboxes : themeMetaboxes.filter(function (m) {
				return m.metabox_id !== id;
			});

			renderSidebar();
			loadMetabox(saved.metabox_id);

			toast('Imported. Remove the original add_filter from your theme to avoid duplicate registration.', 'success', 5000);
		}).catch(function (err) {
			toast(err && err.message ? err.message : 'Import failed', 'error', 3500);
		});
	}

	function refreshThemeScan(btn) {
		if (btn) btn.classList.add('is-spinning');
		wp.apiFetch({
			url:    TPMFBuilder.restRoot + 'metafields/scan/refresh',
			method: 'POST',
		}).then(function (res) {
			themeMetaboxes = Array.isArray(res && res.theme_metaboxes) ? res.theme_metaboxes : [];
			themeUserMeta  = res && res.theme_user_meta && res.theme_user_meta.fields ? res.theme_user_meta : null;
			renderSidebar();
			toast('Theme scan refreshed (' + themeMetaboxes.length + ' metabox' + (themeMetaboxes.length === 1 ? '' : 'es') + ')', 'success', 2500);
		}).catch(function () {
			toast('Refresh failed', 'error', 3000);
		}).finally(function () {
			if (btn) btn.classList.remove('is-spinning');
		});
	}

	function loadMetabox(id, preferSectionId) {
		var found = allMetaboxes.find(function (m) { return m.metabox_id === id; });
		if (!found) return;
		if (state && state.dirty && !confirm('Unsaved changes — switch anyway?')) return;

		var _sec = (preferSectionId && found.sections.find(function (s) { return s.id === preferSectionId; }))
			? preferSectionId
			: (found.sections.length ? found.sections[0].id : '');

		state = {
			metabox:         JSON.parse(JSON.stringify(found)),
			activeSectionId: _sec,
			dirty:           false,
			status:          'idle',
			statusMsg:       '',
		};
		history.past   = [];
		history.future = [];
		saveUIState();
		renderSidebar();
		render();
	}

	/* ── Onboarding / welcome state ──────────────────────────────────────── */
	function renderNoneState() {
		var totalFields = 0;
		var postTypes   = [];
		allMetaboxes.forEach(function (m) {
			(m.sections || []).forEach(function (s) {
				(s.rows || []).forEach(function (r) { totalFields += (r.fields || []).length; });
			});
			(m.post_type || []).forEach(function (pt) {
				if (postTypes.indexOf(pt) === -1) postTypes.push(pt);
			});
		});

		var statsHtml = '';
		if (allMetaboxes.length) {
			statsHtml = '<div class="mfb-welcome-stats">'
				+ stat(allMetaboxes.length, 'Metabox' + (allMetaboxes.length !== 1 ? 'es' : ''), 'editor-table',  'blue')
				+ stat(totalFields,         'Field'   + (totalFields   !== 1 ? 's'  : ''), 'screenoptions', 'purple')
				+ stat(postTypes.length,    'Post Type'+(postTypes.length!== 1 ? 's' : ''), 'admin-post',    'pink')
				+ '</div>';
		}

		root.innerHTML = '<div class="mfb-welcome">'

			// Hero
			+ '<div class="mfb-welcome-hero">'
			+   '<div class="mfb-welcome-icon"><span class="dashicons dashicons-editor-table"></span></div>'
			+   '<h2 class="mfb-welcome-headline">Let\'s build something great</h2>'
			+   '<p class="mfb-welcome-sub">Create custom metaboxes and drag-and-drop fields for any post type &mdash; visually, without writing a single line of PHP.</p>'
			+ '</div>'

			// Stats (shown only when there are existing metaboxes)
			+ statsHtml

			// Feature hints (shown when no metaboxes yet)
			+ ( !allMetaboxes.length
				? '<div class="mfb-welcome-hints">'
				+   hint('editor-table',    'Create Metaboxes',   'Define the metabox title, post types, context, and column layout.')
				+   hint('layout',          'Build with Rows',    'Add sections, rows, and choose grid or flex column layouts.')
				+   hint('screenoptions',   '13+ Field Types',    'Text, select, color, image, switch, repeater and more.')
				+   hint('editor-code',     'No PHP Required',    'Visual builder saves to JSON, the plugin registers everything at runtime.')
				+ '</div>'
				: '' )

			// CTAs
			+ '<div class="mfb-welcome-cta">'
			+   '<button class="mfb-welcome-btn-primary" id="mfb-welcome-start">'
			+     '<span class="dashicons dashicons-plus-alt2"></span>'
			+     ( allMetaboxes.length ? 'Select or Create a Metabox' : 'Create Your First Metabox' )
			+   '</button>'
			+   '<button class="mfb-welcome-btn-ghost" id="mfb-welcome-import">'
			+     '<span class="dashicons dashicons-upload"></span>Import from JSON'
			+   '</button>'
			+ '</div>'

			+ '</div>';

		// Wire CTAs
		var startBtn = document.getElementById('mfb-welcome-start');
		if (startBtn) startBtn.addEventListener('click', function () {
			if (allMetaboxes.length) {
				// Already have metaboxes — just open the sidebar
				loadMetabox(allMetaboxes[0].metabox_id);
			} else {
				openNewBoxModal();
			}
		});

		var importBtn = document.getElementById('mfb-welcome-import');
		if (importBtn) importBtn.addEventListener('click', openImportForWelcome);
	}

	function stat(count, label, icon, color) {
		return '<div class="mfb-stat mfb-stat--' + color + '">'
			+ '<span class="dashicons dashicons-' + icon + '"></span>'
			+ '<span class="mfb-stat-num">' + count + '</span>'
			+ '<span class="mfb-stat-label">' + label + '</span>'
			+ '</div>';
	}

	function hint(icon, title, desc) {
		return '<div class="mfb-hint">'
			+ '<span class="mfb-hint-icon dashicons dashicons-' + icon + '"></span>'
			+ '<div><strong>' + title + '</strong><p>' + desc + '</p></div>'
			+ '</div>';
	}

	function openImportForWelcome() {
		var input = document.createElement('input');
		input.type   = 'file';
		input.accept = '.json,application/json';
		input.addEventListener('change', function () {
			var file = input.files[0];
			if (!file) return;
			var reader = new FileReader();
			reader.onload = function (e) {
				try {
					var data = JSON.parse(e.target.result);
					// Support single metabox object or array of metaboxes
					var list = Array.isArray(data) ? data : [data];
					var imported = 0;
					list.forEach(function (box) {
						if (!box.metabox_id) return;
						box.sections = (box.sections || []).map(migrateSection);
						var idx = allMetaboxes.findIndex(function (m) { return m.metabox_id === box.metabox_id; });
						if (idx > -1) allMetaboxes[idx] = box;
						else          allMetaboxes.push(box);
						imported++;
					});
					if (imported) {
						// Save all to server
						var pending = allMetaboxes.slice();
						var saveNext = function () {
							if (!pending.length) {
								loadMetabox(allMetaboxes[0].metabox_id);
								toast('Imported ' + imported + ' metabox(es)', 'success', 3000);
								return;
							}
							var box = pending.shift();
							wp.apiFetch({ url: TPMFBuilder.restRoot + 'metafields', method: 'POST', data: box })
								.then(saveNext).catch(saveNext);
						};
						saveNext();
					} else {
						toast('No valid metaboxes found in file', 'error', 3000);
					}
				} catch (err) {
					toast('Invalid JSON: ' + err.message, 'error', 4000);
				}
			};
			reader.readAsText(file);
		});
		input.click();
	}

	/* ── Main render (mirrors Options Builder layout exactly) ─────────────── */
	function render() {
		teardownSortables();
		if (!state) { renderNoneState(); return; }

		root.innerHTML = renderBar()
			+ '<div class="tpmeta-body">'
			+ renderRail() + renderCanvas() + renderPalette()
			+ '</div>';

		wireBar();
		wireRail();
		wireCanvas();
		wirePalette();
		setupSortables();
	}

	/* ── Bar ─────────────────────────────────────────────────────────────── */
	function renderBar() {
		var m = state.metabox;
		var sc = state.status === 'saved' ? 'is-success' : (state.status === 'error' ? 'is-error' : '');
		var canUndo = history.past.length > 0;
		var canRedo = history.future.length > 0;
		var pts = (m.post_type || []).map(function (p) {
			return '<span class="mfb-bar-pt">' + esc(p) + '</span>';
		}).join('');

		return '<header class="tpmeta-bar">'
			+ '<span class="tpmeta-bar-title">' + esc(m.title || m.metabox_id) + '</span>'
			+ '<span style="display:flex;gap:4px;align-items:center;margin-left:4px;">' + pts + '</span>'
			+ '<button class="button" id="mfb-edit-box" title="Metabox settings">'
			+   '<span class="dashicons dashicons-admin-generic"></span><span>Settings</span>'
			+ '</button>'
			+ '<div class="tpmeta-bar-history">'
			+   '<button class="button" id="mfb-undo" title="Undo" ' + (canUndo ? '' : 'disabled') + '>'
			+     '<span class="dashicons dashicons-undo"></span>'
			+   '</button>'
			+   '<button class="button" id="mfb-redo" title="Redo" ' + (canRedo ? '' : 'disabled') + '>'
			+     '<span class="dashicons dashicons-redo"></span>'
			+   '</button>'
			+ '</div>'
			+ '<div class="tpmeta-bar-spacer"></div>'
			+ '<span class="tpmeta-bar-status ' + sc + '">' + esc(state.statusMsg) + '</span>'
			+ '<button class="button" id="mfb-export" title="Export this metabox as JSON">'
			+   '<span class="dashicons dashicons-download"></span><span>Export</span>'
			+ '</button>'
			+ '<button class="button button-primary' + (state.dirty ? ' is-dirty' : '') + '" id="mfb-save" title="Save (Ctrl+S)">'
			+   '<span class="dashicons dashicons-saved"></span><span>Save</span>'
			+ '</button>'
			+ '</header>';
	}

	function wireBar() {
		wire('mfb-edit-box', 'click', editBoxSettings);
		wire('mfb-save',     'click', saveMetabox);
		wire('mfb-export',   'click', exportMetabox);
		wire('mfb-undo',     'click', undo);
		wire('mfb-redo',     'click', redo);
	}

	/* ── Rail (sections) — identical to Options Builder ──────────────────── */
	function renderRail() {
		var m = state.metabox;
		var fieldCount = 0;
		m.sections.forEach(function (s) {
			(s.rows || []).forEach(function (r) { fieldCount += (r.fields || []).length; });
		});

		var items = m.sections.map(function (s) {
			var active = s.id === state.activeSectionId ? ' is-active' : '';
			var icon   = s.icon || 'dashicons-admin-generic';
			var rc = (s.rows || []).length;
			var fc = 0; (s.rows || []).forEach(function (r) { fc += (r.fields || []).length; });
			return '<li class="tpmeta-rail-item' + active + '" data-section-id="' + esc(s.id) + '">'
				+ '<span class="tpmeta-rail-item-handle" aria-hidden="true">'
				+   '<svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor"><circle cx="2" cy="2" r="1.5"/><circle cx="8" cy="2" r="1.5"/><circle cx="2" cy="7" r="1.5"/><circle cx="8" cy="7" r="1.5"/><circle cx="2" cy="12" r="1.5"/><circle cx="8" cy="12" r="1.5"/></svg>'
				+ '</span>'
				+ '<span class="tpmeta-rail-item-icon dashicons ' + esc(icon) + '"></span>'
				+ '<div class="tpmeta-rail-item-body">'
				+   '<span class="tpmeta-rail-item-label">' + esc(s.title || TPMFBuilder.i18n.untitled) + '</span>'
				+   '<span class="tpmeta-rail-item-meta">' + rc + ' row' + (rc === 1 ? '' : 's') + ' · ' + fc + ' field' + (fc === 1 ? '' : 's') + '</span>'
				+ '</div>'
				+ '<div class="tpmeta-rail-item-actions">'
				+   '<button class="tpmeta-rail-item-edit"   data-action="edit-section"      title="Settings"><span class="dashicons dashicons-admin-generic"></span></button>'
				+   '<button class="tpmeta-rail-item-dupe"   data-action="duplicate-section" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>'
				+   '<button class="tpmeta-rail-item-delete" data-action="delete-section"    title="Delete"><span class="dashicons dashicons-trash"></span></button>'
				+ '</div>'
				+ '</li>';
		}).join('');

		return '<aside class="tpmeta-rail">'
			+ '<div class="tpmeta-rail-brand">'
			+   '<span class="dashicons dashicons-editor-table"></span>'
			+   '<span>' + esc(m.title || 'Metabox') + '</span>'
			+ '</div>'
			+ '<div class="tpmeta-rail-section-header">'
			+   '<span>SECTIONS</span>'
			+   '<button class="tpmeta-rail-add" id="tpmeta-add-section" title="Add section">'
			+     '<span class="dashicons dashicons-plus-alt2"></span>'
			+   '</button>'
			+ '</div>'
			+ '<ul class="tpmeta-rail-list" id="tpmeta-sections-list">' + items + '</ul>'
			+ '<div class="tpmeta-rail-stats">'
			+   '<span>' + m.sections.length + ' sections · ' + fieldCount + ' fields</span>'
			+ '</div>'
			+ '</aside>';
	}

	function wireRail() {
		var addBtn = document.getElementById('tpmeta-add-section');
		if (addBtn) addBtn.addEventListener('click', addSection);

		var list = document.getElementById('tpmeta-sections-list');
		if (!list) return;
		list.addEventListener('click', function (e) {
			var del  = e.target.closest('[data-action="delete-section"]');
			if (del)  { e.stopPropagation(); deleteSection(del.closest('.tpmeta-rail-item').dataset.sectionId); return; }
			var edit = e.target.closest('[data-action="edit-section"]');
			if (edit) { e.stopPropagation(); editSection(edit.closest('.tpmeta-rail-item').dataset.sectionId); return; }
			var dupe = e.target.closest('[data-action="duplicate-section"]');
			if (dupe) { e.stopPropagation(); duplicateSection(dupe.closest('.tpmeta-rail-item').dataset.sectionId); return; }
			var item = e.target.closest('.tpmeta-rail-item');
			if (item) selectSection(item.dataset.sectionId);
		});
	}

	/* ── Canvas — identical to Options Builder ────────────────────────────── */
	function getDuplicateFieldIds(metabox) {
		var counts = {};
		(metabox.sections || []).forEach(function (s) {
			(s.rows || []).forEach(function (r) {
				(r.fields || []).forEach(function (f) {
					if (f.id) counts[f.id] = (counts[f.id] || 0) + 1;
				});
			});
		});
		var dupes = {};
		Object.keys(counts).forEach(function (id) { if (counts[id] > 1) dupes[id] = true; });
		return dupes;
	}

	function renderCanvas() {
		var section = activeSection();
		if (!section) {
			return '<main class="tpmeta-canvas">'
				+ '<div class="tpmeta-canvas-empty">'
				+ '<span class="dashicons dashicons-layout"></span>'
				+ '<p>No sections yet.</p>'
				+ '<p>Click <strong>+</strong> in the sidebar to add your first section.</p>'
				+ '</div></main>';
		}

		var duplicateIds = getDuplicateFieldIds(state.metabox);
		var dupeBanner   = '';
		if (Object.keys(duplicateIds).length > 0) {
			dupeBanner = '<div class="tpmeta-dupe-warning">'
				+ '<span class="dashicons dashicons-warning"></span>'
				+ ' Duplicate field IDs: <strong>' + Object.keys(duplicateIds).map(esc).join(', ') + '</strong>'
				+ ' — each ID must be unique across all sections.'
				+ '</div>';
		}

		var rows = section.rows || [];
		var rowsHtml = renderAddRowTrigger(section.id, -1, rows.length === 0);
		rows.forEach(function (row, ri) {
			rowsHtml += renderRow(section, row, ri, duplicateIds);
			rowsHtml += renderAddRowTrigger(section.id, ri, false);
		});

		return '<main class="tpmeta-canvas">'
			+ dupeBanner
			+ '<div class="tpmeta-section-card">'
			+ '<div class="tpmeta-section-head">'
			+ '<input type="text" id="tpmeta-section-title" class="tpmeta-section-title-input" '
			+   'value="' + esc(section.title) + '" placeholder="Section title…" />'
			+ '<div class="tpmeta-section-head-actions">'
			+   '<button class="tpmeta-icon-btn" id="tpmeta-section-settings" title="Section settings">'
			+     '<span class="dashicons dashicons-admin-generic"></span>'
			+   '</button>'
			+ '</div>'
			+ '</div>'
			+ '<div class="tpmeta-row-list" id="tpmeta-row-list">' + rowsHtml + '</div>'
			+ '</div>'
			+ '</main>';
	}

	function wireCanvas() {
		var titleInput = document.getElementById('tpmeta-section-title');
		if (titleInput) {
			titleInput.addEventListener('input', function () {
				var s = activeSection();
				if (!s) return;
				s.title = this.value;
				markDirty();
				// Update rail label without full re-render.
				var label = document.querySelector('.tpmeta-rail-item.is-active .tpmeta-rail-item-label');
				if (label) label.textContent = this.value || TPMFBuilder.i18n.untitled;
			});
		}

		var settingsBtn = document.getElementById('tpmeta-section-settings');
		if (settingsBtn) settingsBtn.addEventListener('click', function () {
			editSection(state.activeSectionId);
		});

		var rowList = document.getElementById('tpmeta-row-list');
		if (!rowList) return;

		rowList.addEventListener('click', function (e) {
			// Close the inline slot picker
			if (e.target.closest('[data-action="close-slot-picker"]')) {
				closeAllSlotPickers();
				return;
			}

			// A field type button inside the slot picker was clicked
			var pickBtn = e.target.closest('.tpmeta-slot-pick-btn');
			if (pickBtn) {
				var slotRow  = pickBtn.dataset.rowId;
				var slotType = pickBtn.dataset.type;
				var slotIdx  = parseInt(pickBtn.dataset.slot, 10);
				closeAllSlotPickers();
				addFieldToRow(slotRow, slotType, slotIdx);
				return;
			}

			// Placeholder click → open inline field-type picker
			var ph = e.target.closest('.tpmeta-field-placeholder');
			if (ph && !ph.classList.contains('is-picking')) {
				openSlotPicker(ph);
				return;
			}

			// Add-row triggers
			var addEmpty = e.target.closest('.tpmeta-add-row-empty-btn, .tpmeta-add-row-mini');
			if (addEmpty) {
				var chooserId = 'chooser_' + addEmpty.dataset.sectionId + '_' + addEmpty.dataset.insert;
				var chooser = document.getElementById(chooserId);
				if (chooser) chooser.classList.toggle('is-open');
				return;
			}
			// Layout chooser — grid
			var layoutCard = e.target.closest('.tpmeta-layout-card[data-layout="grid"]');
			if (layoutCard) {
				var chooser2 = layoutCard.closest('.tpmeta-row-chooser');
				chooser2 && chooser2.querySelector('.tpmeta-row-chooser-cols').style.setProperty('display', 'flex');
				return;
			}
			// Layout chooser — flex
			var flexCard = e.target.closest('.tpmeta-layout-card[data-layout="flex"]');
			if (flexCard) {
				addRow(flexCard.dataset.sectionId, parseInt(flexCard.dataset.insert, 10), 1, 'flex');
				return;
			}
			// Grid col chooser
			var colBtn = e.target.closest('.tpmeta-chooser-col-btn');
			if (colBtn) {
				addRow(colBtn.dataset.sectionId, parseInt(colBtn.dataset.insert, 10), parseInt(colBtn.dataset.cols, 10), 'grid');
				return;
			}
			// Column picker (existing row)
			var cpBtn = e.target.closest('.tpmeta-col-btn');
			if (cpBtn) { changeRowCols(cpBtn.dataset.rowId, parseInt(cpBtn.dataset.cols, 10)); return; }
			// Flex toggles
			var flexBtn = e.target.closest('.tpmeta-flex-btn');
			if (flexBtn) { onFlexToggle(flexBtn); return; }
			// Add col
			var addCol = e.target.closest('.tpmeta-add-col-btn');
			if (addCol) { addColumnSlot(addCol.dataset.rowId); return; }
			// Delete row
			var delRow = e.target.closest('.tpmeta-row-delete');
			if (delRow) { deleteRow(delRow.dataset.rowId); return; }
			// Field actions
			var editF = e.target.closest('[data-action="edit-field"]');
			if (editF) { var card = editF.closest('.tpmeta-field-card'); editField(card.dataset.rowId, parseInt(card.dataset.fieldIndex, 10)); return; }
			var dupF = e.target.closest('[data-action="duplicate-field"]');
			if (dupF) { var card2 = dupF.closest('.tpmeta-field-card'); duplicateField(card2.dataset.rowId, parseInt(card2.dataset.fieldIndex, 10)); return; }
			var delF = e.target.closest('[data-action="delete-field"]');
			if (delF) { var card3 = delF.closest('.tpmeta-field-card'); deleteField(card3.dataset.rowId, parseInt(card3.dataset.fieldIndex, 10)); return; }
			// Copy field ID
			var copyBtn = e.target.closest('[data-action="copy-field-id"]');
			if (copyBtn) {
				e.stopPropagation();
				var idToCopy = copyBtn.dataset.copyId;
				function copyIdFallback() {
					var ta = document.createElement('textarea');
					ta.value = idToCopy;
					ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
					document.body.appendChild(ta);
					ta.select();
					try { document.execCommand('copy'); toast('ID copied', 'success', 1500); } catch (ex) {}
					ta.remove();
				}
				// navigator.clipboard only exists in secure contexts (HTTPS/localhost);
				// fall back to execCommand for plain-HTTP local installs.
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(idToCopy).then(function () {
						toast('ID copied', 'success', 1500);
					}).catch(copyIdFallback);
				} else {
					copyIdFallback();
				}
				return;
			}
		});
	}

	/* ── Slot picker — inline field-type chooser on placeholder click ───────── */

	function openSlotPicker(ph) {
		closeAllSlotPickers();
		ph.classList.add('is-picking');
		var rowId   = ph.dataset.rowId;
		var slotIdx = ph.dataset.slotIndex || '0';
		var typesHtml = availableFieldTypes().map(function (t) {
			return '<button class="tpmeta-slot-pick-btn"'
				+ ' data-type="' + esc(t.type) + '"'
				+ ' data-row-id="' + esc(rowId) + '"'
				+ ' data-slot="' + esc(slotIdx) + '">'
				+ '<span class="dashicons dashicons-' + esc(t.icon) + '"></span>'
				+ '<span>' + esc(t.label) + '</span>'
				+ '</button>';
		}).join('');
		ph.innerHTML = '<div class="tpmeta-slot-picker">'
			+ '<div class="tpmeta-slot-picker-header">'
			+   '<span>Choose field type</span>'
			+   '<button class="tpmeta-icon-btn tpmeta-slot-picker-close" data-action="close-slot-picker" title="Cancel">&times;</button>'
			+ '</div>'
			+ '<div class="tpmeta-slot-picker-types">' + typesHtml + '</div>'
			+ '</div>';
	}

	function closeAllSlotPickers() {
		document.querySelectorAll('.tpmeta-field-placeholder.is-picking').forEach(function (ph) {
			ph.classList.remove('is-picking');
			ph.innerHTML = '<span class="tpmeta-ph-icon dashicons dashicons-plus-alt2"></span>'
				+ '<span class="tpmeta-ph-label">Drop here</span>';
		});
	}

	function addFieldToRow(rowId, type, atIndex) {
		var section = activeSection();
		if (!section) return;
		var row = getRowById(section.id, rowId);
		if (!row) return;
		recordChange();
		var field = makeField(type);
		if (typeof atIndex === 'number') {
			row.fields.splice(atIndex, 0, field);
		} else {
			row.fields.push(field);
		}
		markDirty();
		render();
		// Open the field editor immediately so the user can configure the new field.
		var newIdx = row.fields.indexOf(field);
		if (newIdx !== -1) editField(rowId, newIdx);
	}

	/* ── Row (identical HTML to Options Builder — reuses all builder.css) ─── */
	function renderRow(section, row, _ri, duplicateIds) {
		duplicateIds = duplicateIds || {};
		var cols      = row.columns   || 1;
		var layout    = row.layout    || 'grid';
		var direction = row.direction || 'row';
		var wrap      = row.wrap      || 'wrap';
		var fields    = row.fields    || [];
		var gridId    = 'tpmeta-fg-' + esc(row.id);

		var colOptions  = layout === 'flex' ? [1, 2, 3, 4] : [1, 2, 3, 4, 6];
		var colPickerHtml = colOptions.map(function (n) {
			var active = n === cols ? ' is-active' : '';
			var spans  = ''; for (var i = 0; i < n; i++) spans += '<s></s>';
			return '<button class="tpmeta-col-btn' + active + '" data-cols="' + n
				+ '" data-row-id="' + esc(row.id) + '" title="' + n + ' col' + (n > 1 ? 's' : '') + '">'
				+ '<span class="tpmeta-col-visual c' + n + '">' + spans + '</span></button>';
		}).join('');

		var flexHtml = '';
		if (layout === 'flex') {
			flexHtml = '<div class="tpmeta-flex-controls" data-row-id="' + esc(row.id) + '">'
				+ '<span class="tpmeta-flex-label">Dir:</span>'
				+ '<div class="tpmeta-flex-toggle">'
				+   '<button class="tpmeta-flex-btn' + (direction === 'row' ? ' is-active' : '') + '" data-flex-prop="direction" data-flex-val="row" data-row-id="' + esc(row.id) + '" title="Row">'
				+     '<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M1 7h10M8 4l3 3-3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/></svg>'
				+   '</button>'
				+   '<button class="tpmeta-flex-btn' + (direction === 'column' ? ' is-active' : '') + '" data-flex-prop="direction" data-flex-val="column" data-row-id="' + esc(row.id) + '" title="Column">'
				+     '<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M7 1v10M4 8l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/></svg>'
				+   '</button>'
				+ '</div>'
				+ '<span class="tpmeta-flex-label">Wrap:</span>'
				+ '<div class="tpmeta-flex-toggle">'
				+   '<button class="tpmeta-flex-btn' + (wrap === 'wrap' ? ' is-active' : '') + '" data-flex-prop="wrap" data-flex-val="wrap" data-row-id="' + esc(row.id) + '" title="Wrap">'
				+     '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 4h7a3 3 0 010 6H2M4 8l-2 2 2 2"/></svg>'
				+   '</button>'
				+   '<button class="tpmeta-flex-btn' + (wrap === 'nowrap' ? ' is-active' : '') + '" data-flex-prop="wrap" data-flex-val="nowrap" data-row-id="' + esc(row.id) + '" title="No Wrap">'
				+     '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="2" y1="7" x2="12" y2="7"/></svg>'
				+   '</button>'
				+ '</div>'
				+ '</div>';
		}

		var maxSlots = (layout === 'flex' && wrap === 'nowrap') ? 0
			: (layout === 'flex' && direction === 'column') ? 1
			: cols;
		var phCount = Math.max(0, maxSlots - fields.length);
		var fieldsHtml = fields.map(function (f, fi) { return renderFieldCard(f, fi, row.id, duplicateIds); }).join('');
		var phHtml = '';
		for (var i = 0; i < phCount; i++) {
			phHtml += '<div class="tpmeta-field-placeholder" data-row-id="' + esc(row.id) + '" data-slot-index="' + (fields.length + i) + '">'
				+ '<span class="tpmeta-ph-icon dashicons dashicons-plus-alt2"></span>'
				+ '<span class="tpmeta-ph-label">Drop here</span>'
				+ '</div>';
		}

		var gridClasses = 'tpmeta-fields-grid cols-' + cols + ' ' + layout;
		if (layout === 'flex') gridClasses += ' dir-' + direction + ' ' + wrap;

		return '<div class="tpmeta-row" data-row-id="' + esc(row.id) + '" data-section-id="' + esc(section.id) + '">'
			+ '<div class="tpmeta-row-head">'
			+   '<span class="tpmeta-row-handle" aria-hidden="true"><svg width="10" height="14" viewBox="0 0 10 14" fill="currentColor"><circle cx="2" cy="2" r="1.5"/><circle cx="8" cy="2" r="1.5"/><circle cx="2" cy="6.5" r="1.5"/><circle cx="8" cy="6.5" r="1.5"/><circle cx="2" cy="11" r="1.5"/><circle cx="8" cy="11" r="1.5"/></svg></span>'
			+   '<span class="tpmeta-row-badge tpmeta-row-badge--' + layout + '">' + layout.charAt(0).toUpperCase() + layout.slice(1) + '</span>'
			+   '<div class="tpmeta-col-picker" data-row-id="' + esc(row.id) + '">' + colPickerHtml + '</div>'
			+   flexHtml
			+   '<button class="tpmeta-add-col-btn" data-action="add-col" data-row-id="' + esc(row.id) + '" title="Add a column slot">'
			+     '<span class="dashicons dashicons-plus-alt2"></span><span>+Col</span>'
			+   '</button>'
			+   '<button class="tpmeta-row-delete tpmeta-icon-btn is-danger" data-row-id="' + esc(row.id) + '" title="Delete row">'
			+     '<span class="dashicons dashicons-trash"></span>'
			+   '</button>'
			+ '</div>'
			+ '<div class="' + esc(gridClasses) + '" id="' + gridId + '" data-row-id="' + esc(row.id) + '">'
			+ fieldsHtml + phHtml
			+ '</div>'
			+ '</div>';
	}

	function renderAddRowTrigger(sectionId, insertAfterIdx, isEmpty) {
		var chooserId = 'chooser_' + sectionId + '_' + (insertAfterIdx + 1);
		if (isEmpty) {
			return '<div class="tpmeta-add-row-empty" data-section-id="' + esc(sectionId) + '">'
				+ '<button class="tpmeta-add-row-empty-btn" data-section-id="' + esc(sectionId) + '" data-insert="' + (insertAfterIdx + 1) + '">'
				+   '<span class="tpmeta-add-row-plus"><span class="dashicons dashicons-plus-alt2"></span></span>'
				+   '<span>Add a row</span>'
				+   '<span class="tpmeta-add-row-hint">Grid or Flex layout</span>'
				+ '</button>'
				+ renderRowChooser(chooserId, sectionId, insertAfterIdx + 1)
				+ '</div>';
		}
		return '<div class="tpmeta-add-row-divider" data-section-id="' + esc(sectionId) + '">'
			+ '<button class="tpmeta-add-row-mini" data-section-id="' + esc(sectionId) + '" data-insert="' + (insertAfterIdx + 1) + '">'
			+   '<span class="dashicons dashicons-plus-alt2"></span>'
			+ '</button>'
			+ renderRowChooser(chooserId, sectionId, insertAfterIdx + 1)
			+ '</div>';
	}

	function renderRowChooser(chooserId, sectionId, insertIndex) {
		var colBtns = [1, 2, 3, 4, 6].map(function (n) {
			var spans = ''; for (var i = 0; i < n; i++) spans += '<s></s>';
			return '<button class="tpmeta-chooser-col-btn" data-cols="' + n
				+ '" data-section-id="' + esc(sectionId) + '" data-insert="' + insertIndex + '">'
				+ '<span class="tpmeta-col-visual c' + n + '">' + spans + '</span>'
				+ '<small>' + n + ' col' + (n > 1 ? 's' : '') + '</small></button>';
		}).join('');

		return '<div class="tpmeta-row-chooser" id="' + esc(chooserId) + '">'
			+ '<div class="tpmeta-row-chooser-layouts">'
			+   '<button class="tpmeta-layout-card" data-layout="grid" data-chooser="' + esc(chooserId) + '">'
			+     '<span class="dashicons dashicons-screenoptions"></span><span>Grid</span>'
			+   '</button>'
			+   '<button class="tpmeta-layout-card" data-layout="flex" data-section-id="' + esc(sectionId) + '" data-insert="' + insertIndex + '">'
			+     '<span class="dashicons dashicons-align-left"></span><span>Flex</span>'
			+   '</button>'
			+ '</div>'
			+ '<div class="tpmeta-row-chooser-cols" style="display:none"><p>Choose columns</p>' + colBtns + '</div>'
			+ '</div>';
	}

	function renderFieldCard(field, idx, rowId, duplicateIds) {
		duplicateIds = duplicateIds || {};
		var meta     = fieldTypeMeta(field.type);
		var invalid  = !field.id || !/^[a-z0-9_]+$/.test(field.id);
		var isDupe   = !!(field.id && duplicateIds[field.id]);
		var warnMsg  = isDupe ? 'Duplicate field ID — must be unique' : 'Missing or invalid ID';
		return '<div class="tpmeta-field-card' + ((invalid || isDupe) ? ' has-warning' + (isDupe ? ' has-duplicate' : '') : '') + '" '
			+ 'data-field-index="' + idx + '" data-field-id="' + esc(field.id || '') + '" data-row-id="' + esc(rowId) + '">'
			+ '<span class="tpmeta-field-card-icon dashicons dashicons-' + esc(meta.icon) + '"></span>'
			+ '<div class="tpmeta-field-card-meta">'
			+   '<div class="tpmeta-field-card-label">' + esc(field.label || meta.label) + '</div>'
			+   '<div class="tpmeta-field-card-id">'
			+     ((invalid || isDupe) ? '<span class="tpmeta-warn-dot' + (isDupe ? ' is-dupe' : '') + '" title="' + warnMsg + '"></span>' : '')
			+     esc(field.id || '(no id)') + ' · ' + esc(field.type)
			+     (field.id ? '<button class="tpmeta-copy-id-btn" data-action="copy-field-id" data-copy-id="' + esc(field.id) + '" title="Copy ID"><span class="dashicons dashicons-clipboard"></span></button>' : '')
			+   '</div>'
			+ '</div>'
			+ '<div class="tpmeta-field-card-actions">'
			+   '<button data-action="edit-field"      title="Edit"><span class="dashicons dashicons-edit"></span></button>'
			+   '<button data-action="duplicate-field" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>'
			+   '<button data-action="delete-field" class="is-delete" title="Delete"><span class="dashicons dashicons-trash"></span></button>'
			+ '</div>'
			+ '</div>';
	}

	/* ── Palette ─────────────────────────────────────────────────────────── */
	// Field types the user-meta runtime can't render/save yet. Hidden from
	// the palette and slot picker when the active record has target=user.
	// 'repeater' is gated until Phase 3 of user-meta lands.
	var USER_META_UNSUPPORTED_TYPES = ['repeater'];

	function isUserTarget() {
		return state && state.metabox && 'user' === state.metabox.target;
	}

	function availableFieldTypes() {
		var all = TPMFBuilder.fieldTypes || [];
		if (!isUserTarget()) return all;
		return all.filter(function (t) {
			return USER_META_UNSUPPORTED_TYPES.indexOf(t.type) === -1;
		});
	}

	function renderPalette() {
		var q     = paletteFilter.toLowerCase();
		var types = availableFieldTypes().filter(function (t) {
			return !q || t.label.toLowerCase().indexOf(q) !== -1 || t.type.indexOf(q) !== -1;
		});
		var items = types.map(function (t) {
			return '<li class="tpmeta-palette-item" data-field-type="' + esc(t.type) + '">'
				+ '<span class="dashicons dashicons-' + esc(t.icon) + '"></span>'
				+ '<span>' + esc(t.label) + '</span></li>';
		}).join('');

		return '<aside class="tpmeta-palette">'
			+ '<div class="tpmeta-palette-header">'
			+   '<span class="tpmeta-palette-title">Fields</span>'
			+   '<input type="search" id="tpmeta-palette-search" placeholder="Filter fields…" value="' + esc(paletteFilter) + '" />'
			+ '</div>'
			+ '<ul class="tpmeta-palette-list" id="tpmeta-palette-list">' + items + '</ul>'
			+ '</aside>';
	}

	function wirePalette() {
		var search = document.getElementById('tpmeta-palette-search');
		if (search) {
			search.addEventListener('input', function () {
				paletteFilter = this.value;
				var list = document.getElementById('tpmeta-palette-list');
				if (!list) return;
				var q     = paletteFilter.toLowerCase();
				var types = availableFieldTypes().filter(function (t) {
					return !q || t.label.toLowerCase().indexOf(q) !== -1 || t.type.indexOf(q) !== -1;
				});
				list.innerHTML = types.map(function (t) {
					return '<li class="tpmeta-palette-item" data-field-type="' + esc(t.type) + '">'
						+ '<span class="dashicons dashicons-' + esc(t.icon) + '"></span>'
						+ '<span>' + esc(t.label) + '</span></li>';
				}).join('');
				// Re-init palette Sortable after re-rendering the list items.
				// Use currentFieldGroup (set by setupSortables) so the palette
				// stays in the same group as the field grids. Calling
				// setupPaletteSortable() here without args previously created a
				// second Sortable with group:undefined on the same element, which
				// conflicted with the correct instance created by setupSortables.
				setupPaletteSortable(currentFieldGroup);
			});
		}
		// Palette Sortable is fully initialised by setupSortables() which runs
		// right after wirePalette() in render(). Do NOT call it here — doing so
		// would create a duplicate instance with an undefined group name.
	}

	/* ── Sortable setup (mirrors Options Builder exactly) ─────────────────── */
	function teardownSortables() {
		sortables.forEach(function (s) { try { s.destroy(); } catch (e) {} });
		sortables = [];
	}

	function setupSortables() {
		if (typeof Sortable === 'undefined') return;

		// Section rail
		var rail = document.getElementById('tpmeta-sections-list');
		if (rail) {
			sortables.push(Sortable.create(rail, {
				handle: '.tpmeta-rail-item-handle',
				animation: 150,
				onEnd: function (evt) {
					var secs = state.metabox.sections;
					var moved = secs.splice(evt.oldIndex, 1)[0];
					secs.splice(evt.newIndex, 0, moved);
					recordChange();
					render();
				},
			}));
		}

		// Row lists in each section
		var rowLists = document.querySelectorAll('.tpmeta-row-list');
		rowLists.forEach(function (el) {
			var sectionId = el.closest('.tpmeta-section-card') ?
				state.activeSectionId : null;
			if (!sectionId) return;
			sortables.push(Sortable.create(el, {
				handle: '.tpmeta-row-handle',
				animation: 150,
				filter: '.tpmeta-add-row-divider,.tpmeta-add-row-empty',
				onEnd: function (evt) {
					var section = getSectionById(sectionId);
					if (!section) return;
					// evt.oldIndex/newIndex count every child of the container,
					// including the interspersed .tpmeta-add-row-divider elements,
					// so they don't map 1:1 to section.rows. Read the actual
					// post-drag order directly from the DOM instead.
					var rowMap   = {};
					section.rows.forEach(function (r) { rowMap[r.id] = r; });
					var newOrder = [];
					evt.to.querySelectorAll(':scope > .tpmeta-row').forEach(function (el) {
						var r = rowMap[el.dataset.rowId];
						if (r) newOrder.push(r);
					});
					if (newOrder.length !== section.rows.length) { render(); return; }
					recordChange();
					section.rows = newOrder;
					markDirty();
					render();
				},
			}));
		});

		// Field grids — all grids in this section share a group so fields can be
		// dragged across rows. onEnd uses evt.from / evt.to for correct row IDs.
		//
		// NOTE: filter: '.tpmeta-field-placeholder' is intentionally ABSENT.
		// When a flex row is empty, its grid contains only placeholder divs. If
		// those were filtered, SortableJS finds no valid insertion point and
		// silently blocks the drop. handle: '.tpmeta-field-card' already prevents
		// placeholders from being picked up as drag sources.
		currentFieldGroup = 'mfb-fields-' + (state.activeSectionId || 'none');
		document.querySelectorAll('.tpmeta-fields-grid').forEach(function (grid) {
			sortables.push(Sortable.create(grid, {
				group:     { name: currentFieldGroup, pull: true, put: [currentFieldGroup] },
				handle:    '.tpmeta-field-card',
				animation: 150,
				ghostClass:'tpmeta-field-ghost',
				dragClass: 'sortable-drag',
				onEnd: function (evt) {
					if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
					onFieldDrop(evt, evt.from.dataset.rowId, evt.to.dataset.rowId);
				},
			}));
		});

		setupPaletteSortable(currentFieldGroup);
	}

	function setupPaletteSortable(groupName) {
		var palette = document.getElementById('tpmeta-palette-list');
		if (!palette || typeof Sortable === 'undefined') return;
		sortables.push(Sortable.create(palette, {
			group:  { name: groupName, pull: 'clone', put: false },
			sort:   false,
			animation: 150,
			onEnd: function (evt) {
				if (evt.to === palette) return;
				var type    = evt.item.dataset.fieldType;
				var rowId   = evt.to.dataset.rowId;
				var newIdx  = evt.newIndex;
				var section = getSectionById(state.activeSectionId);
				var row     = section ? getRowById(section.id, rowId) : null;
				if (!row) { render(); return; }
				// Remove the clone Sortable inserted into the grid
				if (evt.item.parentNode) evt.item.parentNode.removeChild(evt.item);
				var field = makeField(type);
				row.fields.splice(newIdx, 0, field);
				recordChange();
				markDirty();
				render();
			},
		}));
	}

	function onFieldDrop(evt, fromRowId, toRowId) {
		var section = getSectionById(state.activeSectionId);
		if (!section) return;
		var fromRow = getRowById(section.id, fromRowId);
		var toRow   = getRowById(section.id, toRowId);
		if (!fromRow || !toRow) { render(); return; }

		var field = fromRow.fields.splice(evt.oldIndex, 1)[0];
		if (!field) { render(); return; }
		toRow.fields.splice(evt.newIndex, 0, field);
		recordChange();
		markDirty();
		render();
	}

	/* ── Section actions ──────────────────────────────────────────────────── */
	function addSection() {
		recordChange();
		var id = 'sec_' + uid();
		state.metabox.sections.push({ id: id, title: '', icon: 'dashicons-admin-generic', description: '', rows: [] });
		state.activeSectionId = id;
		markDirty();
		render();
		var titleInput = document.getElementById('tpmeta-section-title');
		if (titleInput) titleInput.focus();
	}

	function selectSection(id) {
		state.activeSectionId = id;
		saveUIState();
		render();
	}

	function deleteSection(id) {
		if (!confirm(TPMFBuilder.i18n.confirmDelete)) return;
		recordChange();
		state.metabox.sections = state.metabox.sections.filter(function (s) { return s.id !== id; });
		if (state.activeSectionId === id) {
			state.activeSectionId = state.metabox.sections.length ? state.metabox.sections[0].id : '';
		}
		markDirty();
		render();
	}

	function duplicateSection(id) {
		var s = getSectionById(id);
		if (!s) return;
		recordChange();
		var copy = JSON.parse(JSON.stringify(s));
		copy.id   = 'sec_' + uid();
		copy.rows = copy.rows.map(function (r) {
			r.id = 'row_' + uid();
			r.fields = r.fields.map(function (f) { return Object.assign({}, f, { id: f.id + '_copy' }); });
			return r;
		});
		var idx = state.metabox.sections.findIndex(function (x) { return x.id === id; });
		state.metabox.sections.splice(idx + 1, 0, copy);
		state.activeSectionId = copy.id;
		markDirty();
		render();
	}

	function editSection(id) {
		var s = getSectionById(id);
		if (!s || typeof TPMetaBuilderModal === 'undefined') return;
		TPMetaBuilderModal.openSection(s, function (updated) {
			Object.assign(s, updated);
			markDirty();
			render();
		});
	}

	/* ── Row actions ──────────────────────────────────────────────────────── */
	function addRow(sectionId, insertIndex, cols, layout) {
		var section = getSectionById(sectionId);
		if (!section) return;
		recordChange();
		var row = { id: 'row_' + uid(), layout: layout, columns: cols, direction: 'row', wrap: 'wrap', fields: [] };
		section.rows.splice(insertIndex, 0, row);
		markDirty();
		render();
	}

	function deleteRow(rowId) {
		var section = activeSection();
		if (!section) return;
		recordChange();
		section.rows = section.rows.filter(function (r) { return r.id !== rowId; });
		markDirty();
		render();
	}

	function changeRowCols(rowId, cols) {
		var section = activeSection();
		var row = section ? getRowById(section.id, rowId) : null;
		if (!row) return;
		recordChange();
		row.columns = cols;
		markDirty();
		render();
	}

	function addColumnSlot(rowId) {
		var section = activeSection();
		var row = section ? getRowById(section.id, rowId) : null;
		if (!row) return;
		recordChange();
		row.columns = Math.min(6, (row.columns || 1) + 1);
		markDirty();
		render();
	}

	function onFlexToggle(btn) {
		var section = activeSection();
		var row = section ? getRowById(section.id, btn.dataset.rowId) : null;
		if (!row) return;
		recordChange();
		row[btn.dataset.flexProp] = btn.dataset.flexVal;
		markDirty();
		render();
	}

	/* ── Field actions ────────────────────────────────────────────────────── */
	function editField(rowId, idx) {
		if (typeof TPMetaBuilderModal === 'undefined') return;
		var section = activeSection();
		var row = section ? getRowById(section.id, rowId) : null;
		if (!row || !row.fields[idx]) return;
		var allFields = [];
		section.rows.forEach(function (r) { allFields = allFields.concat(r.fields); });
		TPMetaBuilderModal.open(row.fields[idx], allFields, function (updated) {
			row.fields[idx] = updated;
			markDirty();
			render();
		});
	}

	function duplicateField(rowId, idx) {
		var section = activeSection();
		var row = section ? getRowById(section.id, rowId) : null;
		if (!row) return;
		recordChange();
		var copy = JSON.parse(JSON.stringify(row.fields[idx]));
		copy.id  = copy.id + '_copy';
		row.fields.splice(idx + 1, 0, copy);
		markDirty();
		render();
	}

	function deleteField(rowId, idx) {
		var section = activeSection();
		var row = section ? getRowById(section.id, rowId) : null;
		if (!row) return;
		recordChange();
		row.fields.splice(idx, 1);
		markDirty();
		render();
	}

	/* ── Metabox CRUD ─────────────────────────────────────────────────────── */
	function editBoxSettings() {
		var m = state.metabox;
		var target = ('user' === m.target) ? 'user' : 'post';
		document.getElementById('mfb-box-modal-title').textContent = 'Metabox Settings';
		document.getElementById('mfb-f-title').value       = m.title       || '';
		document.getElementById('mfb-f-id').value          = m.metabox_id  || '';
		document.getElementById('mfb-f-context').value     = m.context     || 'normal';
		document.getElementById('mfb-f-priority').value    = m.priority    || 'default';
		document.getElementById('mfb-f-columns').value     = m.columns     || 1;
		document.getElementById('mfb-f-post-format').value = m.post_format || '';
		document.getElementById('mfb-f-capability').value  = m.capability  || '';
		setTargetRadio(target);
		applyTargetVisibility(target);
		populatePostTypesCheckboxes(m.post_type || []);
		document.getElementById('mfb-box-modal').dataset.isNew = 'false';
		document.getElementById('mfb-box-modal').showModal();
	}

	function openNewBoxModal() {
		document.getElementById('mfb-box-modal-title').textContent = 'New Metabox';
		document.getElementById('mfb-f-title').value       = '';
		document.getElementById('mfb-f-id').value          = '';
		document.getElementById('mfb-f-context').value     = 'normal';
		document.getElementById('mfb-f-priority').value    = 'default';
		document.getElementById('mfb-f-columns').value     = 3;
		document.getElementById('mfb-f-post-format').value = '';
		document.getElementById('mfb-f-capability').value  = '';
		setTargetRadio('post');
		applyTargetVisibility('post');
		populatePostTypesCheckboxes([]);
		document.getElementById('mfb-box-modal').dataset.isNew = 'true';
		document.getElementById('mfb-box-modal').showModal();
	}

	function setTargetRadio(target) {
		document.querySelectorAll('[name="mfb_target"]').forEach(function (el) {
			el.checked = (el.value === target);
		});
	}

	function getTargetRadio() {
		var picked = document.querySelector('[name="mfb_target"]:checked');
		return (picked && 'user' === picked.value) ? 'user' : 'post';
	}

	function applyTargetVisibility(target) {
		var modal = document.getElementById('mfb-box-modal');
		if (!modal) return;
		modal.dataset.target = target;
		modal.querySelectorAll('.mfb-target-post-only').forEach(function (el) {
			el.style.display = ('post' === target) ? '' : 'none';
		});
		modal.querySelectorAll('.mfb-target-user-only').forEach(function (el) {
			el.style.display = ('user' === target) ? '' : 'none';
		});
	}

	function populatePostTypesCheckboxes(selected) {
		var container = document.getElementById('mfb-f-post-types');
		if (!container) return;
		container.innerHTML = (TPMFBuilder.postTypes || []).map(function (pt) {
			var chk = selected.indexOf(pt.value) > -1 ? ' checked' : '';
			return '<label class="mfb-pt-check">'
				+ '<input type="checkbox" name="mfb_pt" value="' + esc(pt.value) + '"' + chk + '>'
				+ '<span class="mfb-pt-check-indicator"></span>'
				+ '<span class="mfb-pt-check-label">' + esc(pt.label) + '</span>'
				+ '</label>';
		}).join('');
	}

	function saveBoxModal() {
		var isNew  = document.getElementById('mfb-box-modal').dataset.isNew === 'true';
		var target = getTargetRadio();
		var pts    = [];
		document.querySelectorAll('[name="mfb_pt"]:checked').forEach(function (el) { pts.push(el.value); });

		var data = {
			metabox_id: document.getElementById('mfb-f-id').value.trim()    || slugify(document.getElementById('mfb-f-title').value),
			title:      document.getElementById('mfb-f-title').value.trim(),
			post_type:  ('user' === target) ? [] : pts,
			context:    document.getElementById('mfb-f-context').value,
			priority:   document.getElementById('mfb-f-priority').value,
			columns:    parseInt(document.getElementById('mfb-f-columns').value, 10) || 1,
			target:     target,
			sections:   isNew ? [] : (state ? state.metabox.sections : []),
		};

		if ('user' === target) {
			// User-target: capability gate (optional). Post-format is meaningless here.
			var capability = document.getElementById('mfb-f-capability').value;
			if (capability) data.capability = capability;
		} else {
			// Post Format binding (optional). Empty string means "always show" —
			// the server-side sanitizer omits the key in that case so existing
			// records without a binding stay key-free.
			var postFormat = document.getElementById('mfb-f-post-format').value;
			if (postFormat) data.post_format = postFormat;
		}

		if (!data.metabox_id || !data.title) { alert('Title and ID are required.'); return; }
		document.getElementById('mfb-box-modal').close();

		wp.apiFetch({ url: TPMFBuilder.restRoot + 'metafields', method: 'POST', data: data })
			.then(function (saved) {
				var idx = allMetaboxes.findIndex(function (m) { return m.metabox_id === saved.metabox_id; });
				saved.sections = (saved.sections || []).map(migrateSection);
				if (idx > -1) allMetaboxes[idx] = saved;
				else          allMetaboxes.push(saved);

				if (isNew) {
					loadMetabox(saved.metabox_id);
				} else {
					state.metabox = JSON.parse(JSON.stringify(saved));
					state.dirty   = false;
					renderSidebar();
					render();
				}
				toast('Saved', 'success', 2000);
			})
			.catch(function () { toast('Save failed', 'error', 3000); });
	}

	function deleteMetabox(id) {
		var m = allMetaboxes.find(function (x) { return x.metabox_id === id; });
		if (!confirm('Delete "' + (m ? m.title || m.metabox_id : id) + '"? This cannot be undone.')) return;

		wp.apiFetch({ url: TPMFBuilder.restRoot + 'metafields/' + encodeURIComponent(id), method: 'DELETE' })
			.then(function () {
				allMetaboxes = allMetaboxes.filter(function (x) { return x.metabox_id !== id; });
				if (state && state.metabox.metabox_id === id) {
					state = null;
					history.past = []; history.future = [];
				}
				renderSidebar();
				if (allMetaboxes.length) loadMetabox(allMetaboxes[0].metabox_id);
				else renderNoneState();
				toast('Deleted', 'success', 2000);
			})
			.catch(function () { toast('Delete failed', 'error', 3000); });
	}

	/* ── Export current metabox as JSON ──────────────────────────────────── */
	function exportMetabox() {
		if (!state) return;
		var data     = JSON.parse(JSON.stringify(state.metabox));
		var filename = (data.metabox_id || 'metabox') + '-' + datestamp() + '.json';
		var blob     = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
		var url      = URL.createObjectURL(blob);
		var a        = document.createElement('a');
		a.href       = url;
		a.download   = filename;
		a.style.display = 'none';
		document.body.appendChild(a);
		a.click();
		requestAnimationFrame(function () {
			document.body.removeChild(a);
			URL.revokeObjectURL(url);
		});
		toast('Exported ' + filename, 'success', 2500);
	}

	function datestamp() {
		var d = new Date();
		var pad = function (n) { return n < 10 ? '0' + n : n; };
		return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
	}

	/* ── Save metabox to REST ─────────────────────────────────────────────── */
	function saveMetabox() {
		if (!state) return;
		var dupes = getDuplicateFieldIds(state.metabox);
		if (Object.keys(dupes).length > 0) {
			toast('Duplicate field IDs: ' + Object.keys(dupes).join(', ') + '. Please fix before saving.', 'error', 5000);
			return;
		}
		setStatus('saving', 'Saving…');
		wp.apiFetch({
			url:    TPMFBuilder.restRoot + 'metafields',
			method: 'POST',
			data:   JSON.parse(JSON.stringify(state.metabox)),
		}).then(function (saved) {
			var idx = allMetaboxes.findIndex(function (m) { return m.metabox_id === saved.metabox_id; });
			saved.sections = (saved.sections || []).map(migrateSection);
			if (idx > -1) allMetaboxes[idx] = saved;
			else          allMetaboxes.push(saved);
			state.dirty = false;
			setStatus('saved', 'Saved ✓');
			renderSidebar();
			setTimeout(function () { setStatus('idle', ''); }, 2500);
		}).catch(function () { setStatus('error', 'Save failed'); });
	}

	/* ── Undo / Redo ──────────────────────────────────────────────────────── */
	function recordChange() {
		history.past.push(JSON.parse(JSON.stringify(state)));
		if (history.past.length > HISTORY_MAX) history.past.shift();
		history.future = [];
	}

	function undo() {
		if (!history.past.length) return;
		history.future.push(JSON.parse(JSON.stringify(state)));
		state = history.past.pop();
		markDirty();
		render();
	}

	function redo() {
		if (!history.future.length) return;
		history.past.push(JSON.parse(JSON.stringify(state)));
		state = history.future.pop();
		markDirty();
		render();
	}

	/* ── Global wire ──────────────────────────────────────────────────────── */
	function wireGlobal() {
		// Close slot pickers when clicking outside a placeholder (capture phase
		// so it fires before any widget handlers, matching Options Builder behaviour).
		document.addEventListener('click', function (e) {
			if (!e.target.closest('.tpmeta-field-placeholder')) {
				closeAllSlotPickers();
			}
		}, true);

		// Sidebar: switch metabox
		document.addEventListener('click', function (e) {
			// Phase-2: import button on a theme entry — handled before generic .mfb-list-item.
			var importBtn = e.target.closest('.mfb-theme-import');
			if (importBtn) { importFromTheme(importBtn.dataset.id); return; }

			// Phase-2: refresh-scan button.
			var refreshBtn = e.target.closest('#mfb-theme-refresh');
			if (refreshBtn) { refreshThemeScan(refreshBtn); return; }

			// Read-only theme entries (user-meta) don't load — just no-op.
			var roItem = e.target.closest('.mfb-theme-item.is-readonly');
			if (roItem) return;

			// Plain theme item click (without import button) — surface a hint.
			var themeItem = e.target.closest('.mfb-theme-item');
			if (themeItem && !e.target.closest('.mfb-theme-import')) {
				toast('Click + to import this metabox into the builder', 'success', 2500);
				return;
			}

			var item = e.target.closest('.mfb-list-item');
			if (item && !e.target.closest('.mfb-list-edit, .mfb-list-delete')) {
				loadMetabox(item.dataset.id);
				return;
			}
			var editBtn = e.target.closest('.mfb-list-edit');
			if (editBtn) { loadMetabox(editBtn.dataset.id); editBoxSettings(); return; }

			var delBtn = e.target.closest('.mfb-list-delete');
			if (delBtn) { deleteMetabox(delBtn.dataset.id); return; }

			var newBtn = e.target.closest('#mfb-new-box');
			if (newBtn) { openNewBoxModal(); return; }

			var bakeBtn = e.target.closest('#mfb-bake-open');
			if (bakeBtn) { TPMFBake.open(); return; }
		});

		// Metabox settings modal
		var boxForm = document.getElementById('mfb-box-form');
		if (boxForm) boxForm.addEventListener('submit', function (e) { e.preventDefault(); saveBoxModal(); });

		var boxClose = document.getElementById('mfb-box-modal-close');
		if (boxClose) boxClose.addEventListener('click', function () { document.getElementById('mfb-box-modal').close(); });
		var boxCancel = document.getElementById('mfb-box-cancel');
		if (boxCancel) boxCancel.addEventListener('click', function () { document.getElementById('mfb-box-modal').close(); });

		// Target radio — toggle which sub-form (post vs user) is visible.
		document.addEventListener('change', function (e) {
			if (e.target && 'mfb_target' === e.target.name) {
				applyTargetVisibility(getTargetRadio());
			}
		});

		// Auto-generate ID from title
		document.addEventListener('input', function (e) {
			if (e.target.id === 'mfb-f-title') {
				var idField = document.getElementById('mfb-f-id');
				if (idField && !idField.dataset.manual) {
					idField.value = slugify(e.target.value);
				}
			}
			if (e.target.id === 'mfb-f-id') {
				e.target.dataset.manual = e.target.value ? '1' : '';
			}
		});

		// Keyboard save
		document.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveMetabox(); }
			if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); }
			if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'Z') { e.preventDefault(); redo(); }
		});

		window.addEventListener('beforeunload', function (e) {
			if (state && state.dirty) { e.preventDefault(); e.returnValue = ''; }
		});
	}

	/* ── State helpers ────────────────────────────────────────────────────── */
	function activeSection() {
		if (!state) return null;
		return getSectionById(state.activeSectionId);
	}

	function getSectionById(id) {
		if (!state) return null;
		return state.metabox.sections.find(function (s) { return s.id === id; }) || null;
	}

	function getRowById(sectionId, rowId) {
		var s = getSectionById(sectionId);
		return s ? (s.rows.find(function (r) { return r && r.id === rowId; }) || null) : null;
	}

	function migrateSection(s) {
		if (s.rows && Array.isArray(s.rows)) return s;
		var row = { id: 'row_' + uid(), layout: 'grid', columns: 1, direction: 'row', wrap: 'wrap',
			fields: Array.isArray(s.fields) ? s.fields : [] };
		return { id: s.id || ('sec_' + uid()), title: s.title || '', icon: s.icon || '', description: s.description || '',
			rows: row.fields.length ? [row] : [] };
	}

	function makeField(type) {
		var meta  = fieldTypeMeta(type);
		var field = { id: type + '_' + uid(), type: type, label: meta.label, default: '', placeholder: '', description: '', conditional: [] };
		// Seed one starter option for types that require an options list.
		if ( ['checkbox', 'select', 'tabs', 'radio_buttonset', 'multicheck'].indexOf(type) !== -1 ) {
			field.options = [{ value: 'option_1', label: 'Option 1' }];
		}
		// Multicolor: seed three common color slots with sensible defaults.
		if ( type === 'multicolor' ) {
			field.options = [
				{ value: 'color',  label: 'Color'  },
				{ value: 'hover',  label: 'Hover'  },
				{ value: 'active', label: 'Active' },
			];
			field.default = '{"color":"#3362FF","hover":"#0088cc","active":"#00aaff"}';
		}
		return field;
	}

	function fieldTypeMeta(type) {
		var map = {};
		(TPMFBuilder.fieldTypes || []).forEach(function (t) { map[t.type] = t; });
		return map[type] || { icon: 'admin-generic', label: type };
	}

	function saveUIState() {
		if (!state) return;
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify({
				metaboxId: state.metabox.metabox_id,
				sectionId: state.activeSectionId,
			}));
		} catch (e) {}
	}

	function loadUIState() {
		try {
			return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
		} catch (e) {
			return {};
		}
	}

	function markDirty() {
		if (!state) return;
		state.dirty = true;
		var btn = document.getElementById('mfb-save');
		if (btn) btn.classList.add('is-dirty');
	}

	function setStatus(type, msg) {
		if (!state) return;
		state.status    = type;
		state.statusMsg = msg;
		var el = document.querySelector('.tpmeta-bar-status');
		if (el) { el.className = 'tpmeta-bar-status' + (type === 'saved' ? ' is-success' : type === 'error' ? ' is-error' : ''); el.textContent = msg; }
		var saveBtn = document.getElementById('mfb-save');
		if (saveBtn) {
			saveBtn.classList.toggle('is-saved', type === 'saved');
			// Saving / saved → no longer dirty visually. Idle/error preserve state.dirty.
			if (type === 'saved' || type === 'saving') saveBtn.classList.remove('is-dirty');
			else if (type === 'idle' && !state.dirty)  saveBtn.classList.remove('is-dirty');
		}
	}

	/* ── Toast / helpers ──────────────────────────────────────────────────── */
	function ensureToastHost() {
		if (document.getElementById('tpmeta-toast-host')) return;
		var h = document.createElement('div');
		h.id = 'tpmeta-toast-host'; h.className = 'tpmeta-toast-host';
		document.body.appendChild(h);
	}

	function toast(msg, type, duration) {
		var host = document.getElementById('tpmeta-toast-host') || document.body;
		var el   = document.createElement('div');
		el.className = 'tpmeta-toast tpmeta-toast--' + (type || 'success');
		el.textContent = msg;
		host.appendChild(el);
		requestAnimationFrame(function () { el.classList.add('is-visible'); });
		setTimeout(function () {
			el.classList.remove('is-visible');
			setTimeout(function () { el.remove(); }, 300);
		}, duration || 2500);
	}

	function wire(id, ev, fn) {
		var el = document.getElementById(id);
		if (el) el.addEventListener(ev, fn);
	}

	function esc(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	function uid() {
		return Math.random().toString(36).slice(2, 10) + '_' + Date.now().toString(36);
	}

	function slugify(s) {
		return String(s).toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
	}

	// Bridge for the Bake module (separate IIFE below). Keeps the bake feature
	// fully isolated — it reads the live metabox list and posts toasts through
	// these helpers without touching any of the builder internals.
	window.TPMFBuilderBridge = {
		getMetaboxes: function () { return allMetaboxes; },
		removeFromList: function (id) {
			allMetaboxes = allMetaboxes.filter(function (m) { return m.metabox_id !== id; });
			renderSidebar();
			if (state && state.metabox.metabox_id === id) {
				state = null;
				if (allMetaboxes.length) loadMetabox(allMetaboxes[0].metabox_id);
				else renderNoneState();
			}
		},
		toast: toast,
	};

}());

/* ─────────────────────────────────────────────────────────────────────────
 * Bake to PHP — self-contained module.
 * Lives in its own IIFE so it can be removed in one block. Reads from the
 * builder via window.TPMFBuilderBridge, talks to its own REST endpoints.
 * ────────────────────────────────────────────────────────────────────── */
(function () {
	'use strict';
	if (typeof TPMFBuilder === 'undefined') return;

	var dirsLoaded = false;
	var modal;

	function $ (id) { return document.getElementById(id); }
	function esc (s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

	function open() {
		modal = $('mfb-bake-modal');
		if (!modal) return;

		renderMetaboxList();
		loadDirectories();
		modal.showModal();
	}

	function close() { if (modal) modal.close(); }

	function renderMetaboxList() {
		var box = $('mfb-bake-list');
		if (!box) return;
		var boxes = (window.TPMFBuilderBridge && window.TPMFBuilderBridge.getMetaboxes) ? window.TPMFBuilderBridge.getMetaboxes() : [];
		if (!boxes.length) {
			box.innerHTML = '<p style="margin:0;color:#666">No metaboxes to bake yet.</p>';
			return;
		}
		box.innerHTML = boxes.map(function (m) {
			var id    = esc(m.metabox_id || '');
			var title = esc(m.title || m.metabox_id || '(untitled)');
			var pts   = (m.post_type || []).join(', ') || '—';
			return '<label class="mfb-pt-check" style="display:flex;gap:8px;align-items:center;padding:6px 0;border-bottom:1px solid #f0f0f1;">'
				+ '<input type="checkbox" class="mfb-bake-id" value="' + id + '" checked>'
				+ '<span style="flex:1;">'
				+   '<strong>' + title + '</strong>'
				+   '<br><small style="color:#666;font-family:ui-monospace,Menlo,Consolas,monospace;">' + id + '</small>'
				+   ' <small style="color:#888;">· ' + esc(pts) + '</small>'
				+ '</span>'
				+ '</label>';
		}).join('');
	}

	function loadDirectories() {
		if (dirsLoaded) return;
		var sel = $('mfb-bake-dir');
		if (!sel) return;
		wp.apiFetch({ url: TPMFBuilder.restRoot + 'metafields/directories', method: 'GET' })
			.then(function (dirs) {
				if (!Array.isArray(dirs)) return;
				var groups = { themes: [], plugins: [] };
				dirs.forEach(function (d) {
					(groups[d.group] || (groups[d.group] = [])).push(d);
				});
				var html = '<option value="">— Download only (no disk write) —</option>';
				if (groups.themes.length) {
					html += '<optgroup label="Themes">';
					groups.themes.forEach(function (d) {
						html += '<option value="' + esc(d.value) + '">' + esc(d.label) + '</option>';
					});
					html += '</optgroup>';
				}
				if (groups.plugins.length) {
					html += '<optgroup label="Plugins">';
					groups.plugins.forEach(function (d) {
						html += '<option value="' + esc(d.value) + '">' + esc(d.label) + '</option>';
					});
					html += '</optgroup>';
				}
				sel.innerHTML = html;
				dirsLoaded = true;
			})
			.catch(function () {
				toast('Could not load directory list', 'error', 3000);
			});
	}

	function selectedIds() {
		var ids = [];
		document.querySelectorAll('.mfb-bake-id:checked').forEach(function (el) {
			if (el.value) ids.push(el.value);
		});
		return ids;
	}

	function buildPayload(includeWritePath) {
		var dirEl      = $('mfb-bake-dir');
		var fileEl     = $('mfb-bake-filename');
		var cbEl       = $('mfb-bake-callback');
		var deleteEl   = $('mfb-bake-delete-after');

		var dir       = dirEl ? dirEl.value : '';
		var filename  = (fileEl && fileEl.value.trim()) || 'pure-metaboxes.php';
		if (!/\.php$/i.test(filename)) filename += '.php';

		var payload = {
			metabox_ids:   selectedIds(),
			callback_name: (cbEl && cbEl.value.trim()) || 'tpmeta_baked_metaboxes',
			delete_after:  !!(deleteEl && deleteEl.checked),
		};

		if (includeWritePath && dir) {
			payload.write_path = dir.replace(/\/+$/, '') + '/' + filename;
		}
		return { payload: payload, filename: filename };
	}

	function downloadOnly() {
		var ids = selectedIds();
		if (!ids.length) { toast('Pick at least one metabox', 'error', 2500); return; }

		var built = buildPayload(false);
		// Force delete_after off for download-only — we never actually wrote.
		built.payload.delete_after = false;

		wp.apiFetch({
			url:    TPMFBuilder.restRoot + 'metafields/bake',
			method: 'POST',
			data:   built.payload,
		}).then(function (res) {
			if (!res || !res.source) { toast('Bake failed', 'error', 3000); return; }
			triggerDownload(res.source, built.filename);
			toast('Generated ' + (res.baked_count || ids.length) + ' metabox(es)', 'success', 2500);
			close();
		}).catch(function (err) {
			toast(err && err.message ? err.message : 'Bake failed', 'error', 3500);
		});
	}

	function bakeAndWrite() {
		var ids = selectedIds();
		if (!ids.length) { toast('Pick at least one metabox', 'error', 2500); return; }

		var dirEl = $('mfb-bake-dir');
		if (!dirEl || !dirEl.value) { toast('Choose a target directory (or use Download)', 'error', 3000); return; }

		var built = buildPayload(true);

		wp.apiFetch({
			url:    TPMFBuilder.restRoot + 'metafields/bake',
			method: 'POST',
			data:   built.payload,
		}).then(function (res) {
			if (!res) { toast('Bake failed', 'error', 3000); return; }

			toast('Wrote ' + (res.baked_count || ids.length) + ' metabox(es) to ' + (res.written_to || built.filename), 'success', 4000);

			// Mirror server-side delete back into the sidebar list.
			if (Array.isArray(res.deleted) && res.deleted.length && window.TPMFBuilderBridge) {
				res.deleted.forEach(function (id) { window.TPMFBuilderBridge.removeFromList(id); });
			}
			close();
		}).catch(function (err) {
			toast(err && err.message ? err.message : 'Bake failed', 'error', 3500);
		});
	}

	function triggerDownload(source, filename) {
		var blob = new Blob([source], { type: 'application/x-php' });
		var url  = URL.createObjectURL(blob);
		var a    = document.createElement('a');
		a.href     = url;
		a.download = filename;
		a.style.display = 'none';
		document.body.appendChild(a);
		a.click();
		requestAnimationFrame(function () {
			document.body.removeChild(a);
			URL.revokeObjectURL(url);
		});
	}

	function toast(msg, type, dur) {
		if (window.TPMFBuilderBridge && window.TPMFBuilderBridge.toast) {
			window.TPMFBuilderBridge.toast(msg, type, dur);
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		var closeBtn  = $('mfb-bake-close');
		var cancelBtn = $('mfb-bake-cancel');
		var dlBtn     = $('mfb-bake-download');
		var writeBtn  = $('mfb-bake-write');
		if (closeBtn)  closeBtn.addEventListener('click', close);
		if (cancelBtn) cancelBtn.addEventListener('click', close);
		if (dlBtn)     dlBtn.addEventListener('click', downloadOnly);
		if (writeBtn)  writeBtn.addEventListener('click', bakeAndWrite);

		// Auto-derive callback name from filename so they stay consistent unless
		// the user has manually edited the callback field.
		var fnInput = $('mfb-bake-filename');
		var cbInput = $('mfb-bake-callback');
		if (fnInput && cbInput) {
			fnInput.addEventListener('input', function () {
				if (cbInput.dataset.manual === '1') return;
				var base = (fnInput.value || '').replace(/\.php$/i, '');
				var derived = 'tpmeta_baked_' + base.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
				cbInput.value = derived || 'tpmeta_baked_metaboxes';
			});
			cbInput.addEventListener('input', function () {
				cbInput.dataset.manual = cbInput.value ? '1' : '';
			});
		}
	});

	// Public entry point used by the main IIFE's sidebar click delegate.
	window.TPMFBake = { open: open };
}());
