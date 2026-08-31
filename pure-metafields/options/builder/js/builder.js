/**
 * TPMeta Options Builder — main app.
 * Vanilla JS + SortableJS. Mounts into #tpmeta-builder-root.
 *
 * State shape:
 *   {
 *     panel: { opt_name, menu_title, ..., sections: [...] },
 *     activeSectionId: 'general',
 *     dirty: bool,
 *     status: 'idle'|'saving'|'saved'|'error'
 *   }
 *
 * Section shape (v2 with row system):
 *   { id, title, icon, description, rows: [
 *       { id, layout: 'grid'|'flex', columns: 1-12, fields: [...] }
 *   ]}
 */
(function () {
	'use strict';

	var root, state, sortables = [];
	var history = { past: [], future: [] };
	var HISTORY_MAX = 50;
	var paletteFilter = '';

	document.addEventListener('DOMContentLoaded', boot);

	function boot() {
		root = document.getElementById('tpmeta-builder-root');
		if (!root) return;
		if (typeof TPMetaBuilderModal !== 'undefined') TPMetaBuilderModal.init();

		var initialPanel = pickInitialPanel();

		if (!initialPanel) {
			renderNoPanelsState();
			return;
		}

		var _savedScreen = 'gallery';
		try { _savedScreen = localStorage.getItem(SCREEN_KEY) === 'builder' ? 'builder' : 'gallery'; } catch (e) {}

		state = {
			panel: initialPanel,
			activeSectionId: '',
			dirty: false,
			status: 'idle',
			statusMsg: '',
			screen: _savedScreen,   // 'gallery' | 'builder'
		};

		state.panel.sections = state.panel.sections.map(migrateSection);
		if (state.panel.sections.length) {
			state.activeSectionId = recallSection(state.panel.opt_name, state.panel.sections)
				|| state.panel.sections[0].id;
		}

		ensureToastHost();
		render();
		wireBakeModal();
		wireImportModal();
		wireDemosModal();
		wireGlobalListeners();
		wireKeyboard();
		wireBeforeunload();
	}

	function renderNoPanelsState() {
		ensureToastHost();
		root.innerHTML = '<div class="tpmeta-bar">'
			+ '<h1 class="tpmeta-bar-title">Options Builder</h1>'
			+ '<div class="tpmeta-bar-spacer"></div>'
			+ '</div>'
			+ '<div class="tpmeta-no-panels-state">'
			+ '<div class="tpmeta-no-panels-icon"><span class="dashicons dashicons-layout"></span></div>'
			+ '<h2 class="tpmeta-no-panels-heading">No panels yet</h2>'
			+ '<p class="tpmeta-no-panels-desc">Create your first options panel to start building theme settings.</p>'
			+ '<div class="tpmeta-no-panels-actions">'
			+ '<button type="button" class="button button-primary" id="tpmeta-np-create">'
			+   '<span class="dashicons dashicons-plus-alt2"></span> Create Panel'
			+ '</button>'
			+ '<button type="button" class="button" id="tpmeta-np-demo">'
			+   '<span class="dashicons dashicons-images-alt2"></span> Load Starter Template'
			+ '</button>'
			+ '</div>'
			+ '</div>';

		var createBtn = document.getElementById('tpmeta-np-create');
		if (createBtn) {
			createBtn.addEventListener('click', function () {
				state = {
					panel: {
						opt_name: 'my_panel',
						menu_title: 'My Panel',
						page_title: 'My Panel',
						menu_slug: 'my-panel',
						menu_icon: 'dashicons-admin-customizer',
						menu_position: 60,
						capability: 'manage_options',
						output_css: true,
						customizer_integration: false,
						sections: [],
					},
					activeSectionId: '',
					dirty: false,
					status: 'idle',
					statusMsg: '',
				};
				history.past = [];
				history.future = [];
				render();
				wireBakeModal();
				wireImportModal();
				wireDemosModal();
				wireGlobalListeners();
				wireKeyboard();
				wireBeforeunload();
				editPanelArgs();
			});
		}

		wireImportModal();
		wireDemosModal();
		var demoBtn = document.getElementById('tpmeta-np-demo');
		if (demoBtn) {
			demoBtn.addEventListener('click', function () {
				if (!state) {
					state = {
						panel: { opt_name: '', menu_title: '', sections: [], menu_slug: '', menu_icon: 'dashicons-admin-customizer', menu_position: 60, capability: 'manage_options', output_css: true },
						activeSectionId: '', dirty: false, status: 'idle', statusMsg: '',
					};
				}
				openDemosModal(true);
			});
		}
	}

	// ---------- Migration (legacy flat-fields → row-based) ----------

	function migrateSection(section) {
		if (section.rows && Array.isArray(section.rows)) return section;
		var row = {
			id: 'row_1',
			layout: 'grid',
			columns: section.columns || 1,
			fields: Array.isArray(section.fields) ? section.fields : [],
		};
		return {
			id: section.id,
			title: section.title || '',
			icon: section.icon || '',
			description: section.description || '',
			rows: row.fields.length ? [row] : [],
		};
	}

	function getRowById(sectionId, rowId) {
		var section = getSectionById(sectionId);
		if (!section) return null;
		// Guard against undefined entries that can creep in from bad Sortable index math.
		return section.rows.find(function (r) { return r && r.id === rowId; }) || null;
	}

	function getSectionById(sectionId) {
		return state.panel.sections.find(function (s) { return s.id === sectionId; }) || null;
	}

	// ---------- Toast notifications ----------

	function ensureToastHost() {
		if (document.getElementById('tpmeta-toast-host')) return;
		var host = document.createElement('div');
		host.id = 'tpmeta-toast-host';
		host.className = 'tpmeta-toast-host';
		document.body.appendChild(host);
	}

	function getToastContainer() {
		// Native <dialog> showModal() creates a browser top layer that z-index
		// cannot pierce from outside. Inject the toast inside the open dialog
		// so it renders within the same top-layer context.
		var openDialog = document.querySelector('dialog[open]');
		if (openDialog) {
			var inner = openDialog.querySelector('.tpmeta-toast-host-inner');
			if (!inner) {
				inner = document.createElement('div');
				inner.className = 'tpmeta-toast-host tpmeta-toast-host-inner';
				openDialog.appendChild(inner);
			}
			return inner;
		}
		ensureToastHost();
		return document.getElementById('tpmeta-toast-host');
	}

	function toast(msg, kind, ttl) {
		var host = getToastContainer();
		var el = document.createElement('div');
		el.className = 'tpmeta-toast tpmeta-toast--' + (kind || 'info');
		el.textContent = msg;
		host.appendChild(el);
		// Force reflow then add visible class for transition.
		// eslint-disable-next-line no-unused-expressions
		el.offsetWidth;
		el.classList.add('is-visible');
		setTimeout(function () {
			el.classList.remove('is-visible');
			setTimeout(function () { el.remove(); }, 250);
		}, ttl || 3000);
	}

	// ---------- History (undo / redo) ----------

	function snapshot() {
		return JSON.stringify({
			panel: state.panel,
			activeSectionId: state.activeSectionId,
		});
	}

	function pushHistory() {
		history.past.push(snapshot());
		if (history.past.length > HISTORY_MAX) history.past.shift();
		history.future.length = 0;
	}

	function applySnapshot(snap) {
		var data = JSON.parse(snap);
		state.panel = data.panel;
		state.activeSectionId = data.activeSectionId;
	}

	function undo() {
		if (!history.past.length) return;
		history.future.push(snapshot());
		applySnapshot(history.past.pop());
		state.dirty = true;
		setStatus('idle', '*');
		render();
		toast('Undo', 'info', 1200);
	}

	function redo() {
		if (!history.future.length) return;
		history.past.push(snapshot());
		applySnapshot(history.future.pop());
		state.dirty = true;
		setStatus('idle', '*');
		render();
		toast('Redo', 'info', 1200);
	}

	// ---------- Validation ----------

	function validatePanel() {
		var errors = [];
		var seenSection = {};
		var seenField   = {};

		if (!state.panel.opt_name || !/^[a-z0-9_]+$/.test(state.panel.opt_name)) {
			errors.push({ message: 'Panel opt_name must be lowercase letters, numbers, and underscores.' });
		}
		if (!state.panel.menu_title) {
			errors.push({ message: 'Panel menu title is required.' });
		}

		state.panel.sections.forEach(function (s) {
			if (!s.id)              errors.push({ message: 'A section is missing an ID.' });
			else if (seenSection[s.id]) errors.push({ message: 'Duplicate section ID: ' + s.id });
			seenSection[s.id] = true;

			(s.rows || []).forEach(function (row) {
				(row.fields || []).forEach(function (f) {
					if (!f.id) {
						errors.push({ message: 'Field in "' + (s.title || s.id) + '" is missing an ID.' });
					} else if (!/^[a-z0-9_]+$/.test(f.id)) {
						errors.push({ message: 'Field ID "' + f.id + '" must be lowercase with underscores only.' });
					} else if (seenField[f.id]) {
						errors.push({ message: 'Duplicate field ID: ' + f.id });
					}
					seenField[f.id] = true;
				});
			});
		});

		return errors;
	}

	// ---------- Keyboard ----------

	function wireKeyboard() {
		document.addEventListener('keydown', function (e) {
			var meta = e.metaKey || e.ctrlKey;
			// Ignore when typing in inputs.
			var inEditable = e.target.matches('input, textarea, select, [contenteditable="true"]');

			if (meta && e.key.toLowerCase() === 's') {
				e.preventDefault();
				savePanel();
				return;
			}
			if (meta && !e.shiftKey && e.key.toLowerCase() === 'z') {
				if (inEditable) return;
				e.preventDefault();
				undo();
				return;
			}
			if (meta && (e.shiftKey && e.key.toLowerCase() === 'z' || e.key.toLowerCase() === 'y')) {
				if (inEditable) return;
				e.preventDefault();
				redo();
				return;
			}
		});
	}

	function wireBeforeunload() {
		window.addEventListener('beforeunload', function (e) {
			if (state.dirty) {
				e.preventDefault();
				e.returnValue = '';
			}
		});
	}

	var STORAGE_KEY      = 'tpmeta_builder_panel';
	var SCREEN_KEY       = 'tpmeta_builder_screen';
	var SECTION_KEY_PFX  = 'tpmeta_builder_section_';

	function rememberPanel(optName) {
		try { localStorage.setItem(STORAGE_KEY, optName); } catch (e) {}
	}

	function rememberScreen(screen) {
		try { localStorage.setItem(SCREEN_KEY, screen); } catch (e) {}
	}

	function forgetPanel() {
		try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
	}

	function rememberSection(optName, sectionId) {
		try { localStorage.setItem(SECTION_KEY_PFX + optName, sectionId); } catch (e) {}
	}

	function recallSection(optName, sections) {
		try {
			var id = localStorage.getItem(SECTION_KEY_PFX + optName);
			if (id && sections.find(function (s) { return s.id === id; })) return id;
		} catch (e) {}
		return '';
	}

	function pickInitialPanel() {
		var panels = TPMetaBuilder.panels || [];
		// ?panel=<opt_name> deep-link wins over localStorage. Used by the
		// options-panel header "Edit with Builder" button.
		try {
			var requested = new URLSearchParams(window.location.search).get('panel');
			if (requested) {
				var deep = panels.find(function (p) { return p.opt_name === requested; });
				if (deep) {
					rememberPanel(requested);
					try { localStorage.setItem(SCREEN_KEY, 'builder'); } catch (e2) {}
					return JSON.parse(JSON.stringify(deep));
				}
			}
		} catch (e) {}
		try {
			var saved = localStorage.getItem(STORAGE_KEY);
			if (saved) {
				var match = panels.find(function (p) { return p.opt_name === saved; });
				if (match) return JSON.parse(JSON.stringify(match));
			}
		} catch (e) {}
		if (panels[0]) return JSON.parse(JSON.stringify(panels[0]));
		// No saved panels — let boot() decide what to show.
		return null;
	}

	// ---------- RENDER ----------

	function render() {
		teardownSortables();

		if (state.screen === 'gallery') {
			root.innerHTML = renderGallery();
			wireGallery();
			return;
		}

		root.innerHTML = renderBar() + '<div class="tpmeta-body">'
			+ renderRail() + renderCanvas() + renderPalette()
			+ '</div>' + renderFooter();

		wireBar();
		wireRail();
		wireCanvas();
		wirePalette();
		setupSortables();
	}

	/* ── Builder bar — only shown inside the builder screen ─────────────── */
	function renderBar() {
		var p = state.panel;
		var statusClass = state.status === 'saved' ? 'is-success' : (state.status === 'error' ? 'is-error' : '');
		var canUndo = history.past.length > 0;
		var canRedo = history.future.length > 0;

		return '<header class="tpmeta-bar">'
			+ '<button class="button tpmeta-bar-back-btn" id="tpmeta-back-to-gallery">'
			+   '<span class="dashicons dashicons-arrow-left-alt"></span><span>All Panels</span>'
			+ '</button>'
			+ '<div class="tpmeta-bar-divider" aria-hidden="true"></div>'
			+ '<span class="dashicons ' + esc(p.menu_icon || 'dashicons-admin-customizer') + ' tpmeta-bar-panel-icon"></span>'
			+ '<span class="tpmeta-bar-panel-name">' + esc(p.menu_title || 'My Panel') + '</span>'
			+ '<button class="button tpmeta-bar-settings-btn" id="tpmeta-edit-panel-args" title="Panel settings">'
			+   '<span class="dashicons dashicons-admin-settings"></span>'
			+ '</button>'
			+ '<div class="tpmeta-bar-history">'
			+   '<button class="button" id="tpmeta-undo" title="Undo (Ctrl+Z)" '
			+     (canUndo ? '' : 'disabled') + '><span class="dashicons dashicons-undo"></span></button>'
			+   '<button class="button" id="tpmeta-redo" title="Redo (Ctrl+Shift+Z)" '
			+     (canRedo ? '' : 'disabled') + '><span class="dashicons dashicons-redo"></span></button>'
			+ '</div>'
			+ '<div class="tpmeta-bar-spacer"></div>'
			+ '<span class="tpmeta-bar-status ' + statusClass + '">' + esc(state.statusMsg) + '</span>'
			+ '<div class="tpmeta-bar-io">'
			+   '<span class="tpmeta-bar-io-label">Import:</span>'
			+   '<button class="button" id="tpmeta-kirki-import-btn" title="Import from Kirki PHP code">'
			+     '<span class="dashicons dashicons-admin-customizer"></span><span>Kirki</span>'
			+   '</button>'
			+   '<button class="button" id="tpmeta-redux-import-btn" title="Import from Redux Framework PHP code">'
			+     '<span class="dashicons dashicons-admin-settings"></span><span>Redux</span>'
			+   '</button>'
			+   '<div class="tpmeta-bar-io-sep"></div>'
			+   '<button class="button" id="tpmeta-export" title="Export panel as JSON">'
			+     '<span class="dashicons dashicons-download"></span><span>Export</span>'
			+   '</button>'
			+   '<button class="button" id="tpmeta-import" title="Import panel from JSON">'
			+     '<span class="dashicons dashicons-upload"></span><span>Import</span>'
			+   '</button>'
			+ '</div>'
			+ '<button class="button" id="tpmeta-bake">' + esc(TPMetaBuilder.i18n.bakeToPhp) + '</button>'
			+ '<button class="tpmeta-bar-save-btn" id="tpmeta-save" title="Save panel (Ctrl+S)">'
			+   '<span class="dashicons dashicons-saved"></span><span>' + esc(TPMetaBuilder.i18n.savePanel) + '</span>'
			+ '</button>'
			+ '</header>';
	}

	/* ── Footer bar — mirrors top bar, shows save button at bottom ──────── */
	function renderFooter() {
		var statusClass = state.status === 'saved' ? 'is-success' : (state.status === 'error' ? 'is-error' : '');
		return '<footer class="tpmeta-footer-bar">'
			+ '<span class="tpmeta-bar-status ' + statusClass + '">' + esc(state.statusMsg) + '</span>'
			+ '<button class="tpmeta-bar-save-btn" id="tpmeta-save-footer" title="Save panel (Ctrl+S)">'
			+   '<span class="dashicons dashicons-saved"></span><span>' + esc(TPMetaBuilder.i18n.savePanel) + '</span>'
			+ '</button>'
			+ '</footer>';
	}

	/* ── Gallery screen — grid of all saved panels ───────────────────────── */
	function renderGallery() {
		var panels = TPMetaBuilder.panels || [];
		var totalFields = 0;
		panels.forEach(function (pp) {
			(pp.sections || []).forEach(function (s) {
				(s.rows || []).forEach(function (r) { totalFields += (r.fields || []).length; });
			});
		});

		var header = '<div class="tpmeta-gallery-header">'
			+ '<div class="tpmeta-gallery-header-text">'
			+   '<h1 class="tpmeta-gallery-title">Options Builder</h1>'
			+   '<p class="tpmeta-gallery-sub">Build and manage your theme option panels visually — no PHP required.</p>'
			+ '</div>'
			+ '<div class="tpmeta-gallery-header-actions">'
			+   '<button class="button" id="tpmeta-kirki-import-btn" title="Import from Kirki PHP code">'
			+     '<span class="dashicons dashicons-admin-customizer"></span><span>Kirki Import</span>'
			+   '</button>'
			+   '<button class="button" id="tpmeta-redux-import-btn" title="Import from Redux Framework PHP code">'
			+     '<span class="dashicons dashicons-admin-settings"></span><span>Redux Import</span>'
			+   '</button>'
			+ '</div>'
			+ '</div>';

		if (panels.length === 0) {
			return '<div class="tpmeta-gallery">'
				+ header
				+ '<div class="tpmeta-gallery-empty">'
				+   '<span class="dashicons dashicons-admin-customizer tpmeta-gallery-empty-icon"></span>'
				+   '<h2>No panels yet</h2>'
				+   '<p>Create your first option panel to get started, or import one from Kirki or Redux PHP code.</p>'
				+   '<div class="tpmeta-gallery-empty-actions">'
				+     '<button class="tpmeta-gallery-create-btn" id="tpmeta-new-panel-empty">'
				+       '<span class="dashicons dashicons-plus-alt2"></span><span>Create New Panel</span>'
				+     '</button>'
				+   '</div>'
				+ '</div>'
				+ '</div>';
		}

		var statsBar = '<div class="tpmeta-gallery-stats">'
			+ '<span class="tpmeta-gallery-stat"><strong>' + panels.length + '</strong> panel' + (panels.length !== 1 ? 's' : '') + '</span>'
			+ '<span class="tpmeta-gallery-stat-sep"></span>'
			+ '<span class="tpmeta-gallery-stat"><strong>' + totalFields + '</strong> field' + (totalFields !== 1 ? 's' : '') + ' total</span>'
			+ '</div>';

		var cardsHtml = panels.map(function (pp) {
			var secCount   = (pp.sections || []).length;
			var fieldCount = 0;
			(pp.sections || []).forEach(function (s) {
				(s.rows || []).forEach(function (r) { fieldCount += (r.fields || []).length; });
			});
			return '<div class="tpmeta-gallery-card" data-opt-name="' + esc(pp.opt_name) + '" tabindex="0" role="button">'
				+ '<div class="tpmeta-gallery-card-icon-wrap">'
				+   '<span class="dashicons ' + esc(pp.menu_icon || 'dashicons-admin-customizer') + ' tpmeta-gallery-card-icon"></span>'
				+ '</div>'
				+ '<div class="tpmeta-gallery-card-body">'
				+   '<h3 class="tpmeta-gallery-card-title">' + esc(pp.menu_title || pp.opt_name) + '</h3>'
				+   '<code class="tpmeta-gallery-card-slug">' + esc(pp.opt_name) + '</code>'
				+   '<div class="tpmeta-gallery-card-meta">'
				+     '<span><span class="dashicons dashicons-screenoptions"></span>' + secCount + ' section' + (secCount !== 1 ? 's' : '') + '</span>'
				+     '<span><span class="dashicons dashicons-list-view"></span>' + fieldCount + ' field' + (fieldCount !== 1 ? 's' : '') + '</span>'
				+   '</div>'
				+ '</div>'
				+ '<div class="tpmeta-gallery-card-footer">'
				+   '<button class="tpmeta-gallery-card-edit-btn" data-opt-name="' + esc(pp.opt_name) + '" type="button">'
				+     '<span class="dashicons dashicons-edit"></span> Edit Panel'
				+   '</button>'
				+   '<button class="tpmeta-gallery-card-delete-btn" data-opt-name="' + esc(pp.opt_name) + '" type="button" title="Delete panel">'
				+     '<span class="dashicons dashicons-trash"></span>'
				+   '</button>'
				+ '</div>'
				+ '</div>';
		}).join('');

		var newPanelCardHtml = '<div class="tpmeta-gallery-card tpmeta-gallery-card--new" id="tpmeta-gallery-new-card" tabindex="0" role="button" title="Create a new options panel">'
			+ '<div class="tpmeta-gallery-card-icon-wrap tpmeta-gallery-new-icon-wrap">'
			+   '<span class="dashicons dashicons-plus-alt2 tpmeta-gallery-card-icon tpmeta-gallery-new-icon"></span>'
			+ '</div>'
			+ '<div class="tpmeta-gallery-card-body">'
			+   '<h3 class="tpmeta-gallery-card-title">New Panel</h3>'
			+   '<code class="tpmeta-gallery-card-slug">create fresh panel</code>'
			+   '<div class="tpmeta-gallery-card-meta"><span>Start blank or use a template</span></div>'
			+ '</div>'
			+ '<div class="tpmeta-gallery-card-footer tpmeta-gallery-card-footer--new">'
			+   '<button class="tpmeta-gallery-card-edit-btn tpmeta-gallery-card-create-btn" type="button">'
			+     '<span class="dashicons dashicons-plus-alt2"></span> Create Panel'
			+   '</button>'
			+ '</div>'
			+ '</div>';

		return '<div class="tpmeta-gallery">'
			+ header
			+ statsBar
			+ '<div class="tpmeta-gallery-grid">' + cardsHtml + newPanelCardHtml + '</div>'
			+ '</div>';
	}

	function renderRail() {
		var p = state.panel;
		var fieldCount = 0;
		state.panel.sections.forEach(function (s) {
			(s.rows || []).forEach(function (r) { fieldCount += (r.fields || []).length; });
		});

		var items = state.panel.sections.map(function (s) {
			var active = (s.id === state.activeSectionId) ? ' is-active' : '';
			var icon = s.icon || 'dashicons-admin-generic';
			var rowCount  = (s.rows || []).length;
			var fCount = 0;
			(s.rows || []).forEach(function (r) { fCount += (r.fields || []).length; });
			return '<li class="tpmeta-rail-item' + active + '" data-section-id="' + esc(s.id) + '">'
				+ '<span class="tpmeta-rail-item-handle" aria-hidden="true">'
				+   '<svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor"><circle cx="2" cy="2" r="1.5"/><circle cx="8" cy="2" r="1.5"/><circle cx="2" cy="7" r="1.5"/><circle cx="8" cy="7" r="1.5"/><circle cx="2" cy="12" r="1.5"/><circle cx="8" cy="12" r="1.5"/></svg>'
				+ '</span>'
				+ '<span class="tpmeta-rail-item-icon dashicons ' + esc(icon) + '"></span>'
				+ '<div class="tpmeta-rail-item-body">'
				+   '<span class="tpmeta-rail-item-label">' + esc(s.title || TPMetaBuilder.i18n.untitled) + '</span>'
				+   '<span class="tpmeta-rail-item-meta">' + rowCount + ' row' + (rowCount === 1 ? '' : 's') + ' · ' + fCount + ' field' + (fCount === 1 ? '' : 's') + '</span>'
				+ '</div>'
				+ '<div class="tpmeta-rail-item-actions">'
				+   '<button class="tpmeta-rail-item-edit" data-action="edit-section" title="Settings"><span class="dashicons dashicons-admin-generic"></span></button>'
				+   '<button class="tpmeta-rail-item-dupe" data-action="duplicate-section" title="Duplicate section"><span class="dashicons dashicons-admin-page"></span></button>'
				+   '<button class="tpmeta-rail-item-delete" data-action="delete-section" title="Delete"><span class="dashicons dashicons-trash"></span></button>'
				+ '</div>'
				+ '</li>';
		}).join('');

		return '<aside class="tpmeta-rail">'
			+ '<div class="tpmeta-rail-brand">'
			+   '<span class="dashicons ' + esc(p.menu_icon || 'dashicons-admin-customizer') + '"></span>'
			+   '<span>' + esc(p.menu_title || 'Options Panel') + '</span>'
			+ '</div>'
			+ '<div class="tpmeta-rail-section-header">'
			+   '<span>SECTIONS</span>'
			+   '<button class="tpmeta-rail-add" id="tpmeta-add-section" title="Add section">'
			+     '<span class="dashicons dashicons-plus-alt2"></span>'
			+   '</button>'
			+ '</div>'
			+ '<ul class="tpmeta-rail-list" id="tpmeta-sections-list">' + items + '</ul>'
			+ '<div class="tpmeta-rail-stats">'
			+   '<span>' + state.panel.sections.length + ' sections · ' + fieldCount + ' fields</span>'
			+ '</div>'
			+ '</aside>';
	}

	function renderCanvas() {
		var section = activeSection();

		if (!section) {
			return '<main class="tpmeta-canvas">'
				+ '<div class="tpmeta-canvas-empty">'
				+ '<span class="dashicons dashicons-layout"></span>'
				+ '<p>No sections yet.</p>'
				+ '<p>Click <strong>+</strong> in the sidebar to add your first section.</p>'
				+ '</div>'
				+ '</main>';
		}

		var rows = section.rows || [];
		var rowsHtml = '';

		rowsHtml += renderAddRowTrigger(section.id, -1, rows.length === 0);

		rows.forEach(function (row, rowIdx) {
			rowsHtml += renderRow(section, row, rowIdx);
			rowsHtml += renderAddRowTrigger(section.id, rowIdx, false);
		});

		return '<main class="tpmeta-canvas">'
			+ '<div class="tpmeta-section-card">'
			+ '<div class="tpmeta-section-head">'
			+ '<input type="text" id="tpmeta-section-title" class="tpmeta-section-title-input" '
			+   'value="' + esc(section.title) + '" placeholder="Section title" />'
			+ '<div class="tpmeta-section-head-actions">'
			+   '<button class="tpmeta-icon-btn" id="tpmeta-section-edit-btn" title="Section settings">'
			+     '<span class="dashicons dashicons-admin-generic"></span>'
			+   '</button>'
			+ '</div>'
			+ '</div>'
			+ '<div class="tpmeta-row-list" id="tpmeta-row-list" data-section-id="' + esc(section.id) + '">'
			+ rowsHtml
			+ '</div>'
			+ '</div>'
			+ '</main>';
	}

	function renderRow(section, row) {
		var cols      = row.columns  || 1;
		var layout    = row.layout   || 'grid';
		var direction = row.direction || 'row';
		var wrap      = row.wrap     || 'wrap';
		var fields    = row.fields   || [];
		var gridId    = 'tpmeta-fg-' + esc(row.id);

		// Column picker — flex rows cap at 4; grid rows allow up to 6.
		var colOptions   = layout === 'flex' ? [1, 2, 3, 4] : [1, 2, 3, 4, 6];
		var colPickerHtml = colOptions.map(function (n) {
			var active = (n === cols) ? ' is-active' : '';
			var spans  = '';
			for (var i = 0; i < n; i++) spans += '<s></s>';
			return '<button class="tpmeta-col-btn' + active + '" data-cols="' + n
				+ '" data-row-id="' + esc(row.id) + '" title="' + n + ' col' + (n > 1 ? 's' : '') + '">'
				+ '<span class="tpmeta-col-visual c' + n + '">' + spans + '</span>'
				+ '</button>';
		}).join('');

		// Flex-only controls (direction + wrap)
		var flexControlsHtml = '';
		if (layout === 'flex') {
			flexControlsHtml =
				'<div class="tpmeta-flex-controls" data-row-id="' + esc(row.id) + '">'
				+ '<span class="tpmeta-flex-label">Dir:</span>'
				+ '<div class="tpmeta-flex-toggle">'
				+   '<button class="tpmeta-flex-btn' + (direction === 'row' ? ' is-active' : '') + '" '
				+     'data-flex-prop="direction" data-flex-val="row" data-row-id="' + esc(row.id) + '" title="Row (horizontal)">'
				+     '<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M1 7h10M8 4l3 3-3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/></svg>'
				+   '</button>'
				+   '<button class="tpmeta-flex-btn' + (direction === 'column' ? ' is-active' : '') + '" '
				+     'data-flex-prop="direction" data-flex-val="column" data-row-id="' + esc(row.id) + '" title="Column (vertical)">'
				+     '<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M7 1v10M4 8l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/></svg>'
				+   '</button>'
				+ '</div>'
				+ '<span class="tpmeta-flex-label">Wrap:</span>'
				+ '<div class="tpmeta-flex-toggle">'
				+   '<button class="tpmeta-flex-btn' + (wrap === 'wrap' ? ' is-active' : '') + '" '
				+     'data-flex-prop="wrap" data-flex-val="wrap" data-row-id="' + esc(row.id) + '" title="Wrap">'
				+     '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 4h7a3 3 0 010 6H2M4 8l-2 2 2 2"/></svg>'
				+   '</button>'
				+   '<button class="tpmeta-flex-btn' + (wrap === 'nowrap' ? ' is-active' : '') + '" '
				+     'data-flex-prop="wrap" data-flex-val="nowrap" data-row-id="' + esc(row.id) + '" title="No Wrap">'
				+     '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="2" y1="7" x2="12" y2="7"/></svg>'
				+   '</button>'
				+ '</div>'
				+ '</div>';
		}

		// Field cards
		var fieldsHtml = fields.map(function (field, fIdx) {
			return renderFieldCard(field, fIdx, row.id);
		}).join('');

		// Placeholder cells — show remaining empty slots.
		// nowrap: no placeholders (items flow freely on one line).
		// flex column: always 1 slot shown.
		var maxSlots = (layout === 'flex' && wrap === 'nowrap') ? 0
			: (layout === 'flex' && direction === 'column') ? 1
			: cols;
		var phCount  = Math.max(0, maxSlots - fields.length);
		var phHtml   = '';
		for (var i = 0; i < phCount; i++) {
			phHtml += '<div class="tpmeta-field-placeholder"'
				+ ' data-row-id="' + esc(row.id) + '"'
				+ ' data-slot-index="' + (fields.length + i) + '">'
				+ '<span class="tpmeta-ph-icon dashicons dashicons-plus-alt2"></span>'
				+ '<span class="tpmeta-ph-label">Drop here</span>'
				+ '</div>';
		}

		// Grid class for the field area
		var gridClasses = 'tpmeta-fields-grid cols-' + cols + ' ' + layout;
		if (layout === 'flex') {
			gridClasses += ' dir-' + direction + ' ' + wrap;
		}

		return '<div class="tpmeta-row" data-row-id="' + esc(row.id) + '" data-section-id="' + esc(section.id) + '">'
			+ '<div class="tpmeta-row-head">'
			+   '<span class="tpmeta-row-handle" aria-hidden="true">'
			+     '<svg width="10" height="14" viewBox="0 0 10 14" fill="currentColor"><circle cx="2" cy="2" r="1.5"/><circle cx="8" cy="2" r="1.5"/><circle cx="2" cy="6.5" r="1.5"/><circle cx="8" cy="6.5" r="1.5"/><circle cx="2" cy="11" r="1.5"/><circle cx="8" cy="11" r="1.5"/></svg>'
			+   '</span>'
			+   '<span class="tpmeta-row-badge tpmeta-row-badge--' + layout + '">' + layout.charAt(0).toUpperCase() + layout.slice(1) + '</span>'
			+   '<div class="tpmeta-col-picker" data-row-id="' + esc(row.id) + '">' + colPickerHtml + '</div>'
			+   flexControlsHtml
			+   '<button class="tpmeta-add-col-btn" data-action="add-col" data-row-id="' + esc(row.id) + '" title="Add a column slot (' + cols + ' now)">'
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
		var chooserId = 'tpmeta-chooser-' + sectionId + '-' + (insertAfterIdx + 1);
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
			var spans = '';
			for (var i = 0; i < n; i++) spans += '<s></s>';
			return '<button class="tpmeta-chooser-col-btn" data-cols="' + n + '" '
				+ 'data-section-id="' + esc(sectionId) + '" data-insert="' + insertIndex + '">'
				+ '<span class="tpmeta-col-visual c' + n + '">' + spans + '</span>'
				+ '<small>' + n + ' col' + (n > 1 ? 's' : '') + '</small>'
				+ '</button>';
		}).join('');

		return '<div class="tpmeta-row-chooser" id="' + esc(chooserId) + '">'
			+ '<div class="tpmeta-row-chooser-layouts">'
			+   '<button class="tpmeta-layout-card" data-layout="grid" data-chooser="' + esc(chooserId) + '">'
			+     '<span class="dashicons dashicons-screenoptions"></span><span>Grid</span>'
			+   '</button>'
			+   '<button class="tpmeta-layout-card" data-layout="flex" '
			+     'data-section-id="' + esc(sectionId) + '" data-insert="' + insertIndex + '">'
			+     '<span class="dashicons dashicons-align-left"></span><span>Flex</span>'
			+   '</button>'
			+ '</div>'
			+ '<div class="tpmeta-row-chooser-cols" style="display:none">'
			+   '<p>Choose columns</p>'
			+   colBtns
			+ '</div>'
			+ '</div>';
	}

	function renderFieldCard(field, idx, rowId) {
		var meta = fieldTypeMeta(field.type);
		var hasWarning = !field.id || !/^[a-z0-9_]+$/.test(field.id);
		return '<div class="tpmeta-field-card' + (hasWarning ? ' has-warning' : '') + '" '
			+ 'data-field-index="' + idx + '" data-field-id="' + esc(field.id || '') + '" data-row-id="' + esc(rowId) + '">'
			+ '<span class="tpmeta-field-card-icon dashicons dashicons-' + esc(meta.icon) + '"></span>'
			+ '<div class="tpmeta-field-card-meta">'
			+   '<div class="tpmeta-field-card-label">' + esc(field.label || meta.label) + '</div>'
			+   '<div class="tpmeta-field-card-id">'
			+     (hasWarning ? '<span class="tpmeta-warn-dot" title="Missing or invalid ID"></span>' : '')
			+     esc(field.id || '(no id)') + ' · ' + esc(field.type)
			+     (field.type === 'post_select' && field.post_type ? ' <span class="tpmeta-field-badge">' + esc(field.post_type) + (field.save_format ? ' / ' + esc(field.save_format) : '') + '</span>' : '')
			+     (field.type === 'switch' && field.data_type ? ' <span class="tpmeta-field-badge">' + esc(field.data_type) + '</span>' : '')
			+     (field.id ? '<button class="tpmeta-copy-id-btn" data-action="copy-field-id" data-copy-id="' + esc(field.id) + '" title="Copy ID"><span class="dashicons dashicons-clipboard"></span><span class="tpmeta-copy-id-tooltip">Copy ID</span></button>' : '')
			+   '</div>'
			+ '</div>'
			+ '<div class="tpmeta-field-card-actions">'
			+   '<button data-action="edit-field" title="Edit"><span class="dashicons dashicons-edit"></span></button>'
			+   '<button data-action="duplicate-field" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>'
			+   '<button data-action="delete-field" class="is-delete" title="Delete"><span class="dashicons dashicons-trash"></span></button>'
			+ '</div>'
			+ '</div>';
	}

	function renderPalette() {
		var q = paletteFilter.toLowerCase();
		var items = (TPMetaBuilder.fieldTypes || []).filter(function (t) {
			if (!q) return true;
			return t.label.toLowerCase().indexOf(q) !== -1 || t.type.toLowerCase().indexOf(q) !== -1;
		}).map(function (t) {
			return '<li class="tpmeta-palette-item" data-field-type="' + esc(t.type) + '">'
				+ '<span class="dashicons dashicons-' + esc(t.icon) + '"></span>'
				+ '<span>' + esc(t.label) + '</span>'
				+ '</li>';
		}).join('');
		return '<aside class="tpmeta-palette">'
			+ '<div class="tpmeta-palette-header"><h3>Field types</h3></div>'
			+ '<div class="tpmeta-palette-search">'
			+   '<span class="dashicons dashicons-search"></span>'
			+   '<input type="search" id="tpmeta-palette-search" placeholder="Filter fields…" value="' + esc(paletteFilter) + '" />'
			+ '</div>'
			+ '<ul class="tpmeta-palette-list" id="tpmeta-palette-list">' + items + '</ul>'
			+ '</aside>';
	}

	// ---------- WIRING ----------

	function wireBar() {
		function wire(id, event, fn) {
			var el = document.getElementById(id);
			if (el) el.addEventListener(event, fn);
		}
		wire('tpmeta-back-to-gallery', 'click', goToGallery);
		wire('tpmeta-save',            'click', savePanel);
		wire('tpmeta-save-footer',     'click', savePanel);
		wire('tpmeta-bake',            'click', openBake);
		wire('tpmeta-edit-panel-args', 'click', editPanelArgs);
		wire('tpmeta-undo',            'click', undo);
		wire('tpmeta-redo',            'click', redo);
		wire('tpmeta-export',          'click', exportPanel);
		wire('tpmeta-import',          'click', function () { openImportModal('full'); });
		wire('tpmeta-kirki-import-btn','click', function () { openKirkiModal('kirki'); });
		wire('tpmeta-redux-import-btn','click', function () { openKirkiModal('redux'); });
	}

	/* ── Gallery wiring ─────────────────────────────────────────────────── */
	function wireGallery() {
		function wire(id, fn) {
			var el = document.getElementById(id);
			if (el) el.addEventListener('click', fn);
		}
		wire('tpmeta-new-panel-empty', createNewPanel);
		wire('tpmeta-kirki-import-btn', function () { openKirkiModal('kirki'); });
		wire('tpmeta-redux-import-btn', function () { openKirkiModal('redux'); });

		// New Panel card in the grid
		var newCard = document.getElementById('tpmeta-gallery-new-card');
		if (newCard) {
			newCard.addEventListener('click', createNewPanel);
			newCard.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); createNewPanel(); }
			});
		}

		// Edit panel → open in builder
		root.querySelectorAll('.tpmeta-gallery-card-edit-btn').forEach(function (btn) {
			if (!btn.dataset.optName) return; // skip the "Create Panel" button
			btn.addEventListener('click', function (e) {
				e.stopPropagation();
				openPanelInBuilder(btn.dataset.optName);
			});
		});

		// Delete panel
		root.querySelectorAll('.tpmeta-gallery-card-delete-btn').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.stopPropagation();
				var optName = btn.dataset.optName;
				var pp = (TPMetaBuilder.panels || []).find(function (p) { return p.opt_name === optName; });
				if (!confirm('Permanently delete "' + (pp ? pp.menu_title || optName : optName) + '"?\nThis cannot be undone.')) return;
				wp.apiFetch({ path: 'tpmeta/v1/builder/panels/' + encodeURIComponent(optName), method: 'DELETE' })
					.then(function () {
						TPMetaBuilder.panels = (TPMetaBuilder.panels || []).filter(function (p) { return p.opt_name !== optName; });
						toast('Panel deleted.', 'success');
						render();
					})
					.catch(function (err) { toast('Delete failed: ' + (err.message || 'error'), 'error', 5000); });
			});
		});

		// Card body click → open in builder
		root.querySelectorAll('.tpmeta-gallery-card[data-opt-name]').forEach(function (card) {
			card.addEventListener('click', function () { openPanelInBuilder(card.dataset.optName); });
			card.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPanelInBuilder(card.dataset.optName); }
			});
		});
	}

	function openPanelInBuilder(optName) {
		var found = (TPMetaBuilder.panels || []).find(function (p) { return p.opt_name === optName; });
		if (!found) return;
		state.panel = JSON.parse(JSON.stringify(found));
		state.panel.sections = state.panel.sections.map(migrateSection);
		state.activeSectionId = recallSection(state.panel.opt_name, state.panel.sections)
			|| (state.panel.sections.length ? state.panel.sections[0].id : '');
		state.dirty = false;
		state.screen = 'builder';
		rememberPanel(state.panel.opt_name);
		rememberScreen('builder');
		render();
	}

	function goToGallery() {
		if (state.dirty && !confirm('You have unsaved changes. Leave the builder?')) return;
		state.screen = 'gallery';
		state.dirty  = false;
		rememberScreen('gallery');
		render();
	}

	function createNewPanel() {
		if (state.dirty && !confirm('You have unsaved changes. Create a new panel anyway?')) return;
		openDemosModal(true);
	}

	function doCreateBlankPanel() {
		var opt = uniquePanelOptName('tpmeta_panel');
		state.panel = {
			opt_name:   opt,
			menu_slug:  opt.replace(/_/g, '-'),
			menu_title: '',
			menu_icon:  'dashicons-admin-generic',
			capability: 'manage_options',
			output_css: true,
			sections:   [],
		};
		state.activeSectionId = '';
		state.screen = 'builder';
		rememberScreen('builder');
		markDirty();
		render();
		editPanelArgs();
	}

	function deletePanel() {
		var p = state.panel;
		var stored = (TPMetaBuilder.panels || []).some(function (pp) { return pp.opt_name === p.opt_name; });

		if (!stored) {
			if (!confirm('Discard this unsaved panel?')) return;
			if (TPMetaBuilder.panels && TPMetaBuilder.panels.length) {
				state.panel = JSON.parse(JSON.stringify(TPMetaBuilder.panels[0]));
				state.panel.sections = state.panel.sections.map(migrateSection);
				state.activeSectionId = state.panel.sections.length ? state.panel.sections[0].id : '';
				state.dirty = false;
				render();
			} else {
				window.location.reload();
			}
			return;
		}

		if (!confirm('Permanently delete the panel "' + (p.menu_title || p.opt_name) + '"? This cannot be undone.')) {
			return;
		}

		setStatus('saving', 'Deleting…');

		wp.apiFetch({
			path:   'tpmeta/v1/builder/panels/' + encodeURIComponent(p.opt_name),
			method: 'DELETE',
		}).then(function () {
			// Drop from the local cache.
			TPMetaBuilder.panels = (TPMetaBuilder.panels || []).filter(function (pp) {
				return pp.opt_name !== p.opt_name;
			});
			forgetPanel();
			// Switch to the next existing panel, or to a fresh one.
			if (TPMetaBuilder.panels.length) {
				state.panel = JSON.parse(JSON.stringify(TPMetaBuilder.panels[0]));
				state.panel.sections = state.panel.sections.map(migrateSection);
				state.activeSectionId = state.panel.sections.length ? state.panel.sections[0].id : '';
				rememberPanel(state.panel.opt_name);
			} else {
				// Last panel removed — reload so PHP shows the welcome screen.
				window.location.reload();
				return;
			}
			state.dirty = false;
			setStatus('saved', 'Deleted');
			render();
			setTimeout(function () { setStatus('idle', ''); }, 2000);
		}).catch(function (err) {
			console.error(err);
			setStatus('error', 'Delete failed');
		});
	}

	function wireRail() {
		var addBtn = document.getElementById('tpmeta-add-section');
		if (addBtn) addBtn.addEventListener('click', addSection);

		var list = document.getElementById('tpmeta-sections-list');
		if (!list) return;
		list.addEventListener('click', function (e) {
			var del = e.target.closest('[data-action="delete-section"]');
			if (del) { e.stopPropagation(); deleteSection(del.closest('.tpmeta-rail-item').dataset.sectionId); return; }
			var edit = e.target.closest('[data-action="edit-section"]');
			if (edit) { e.stopPropagation(); editSection(edit.closest('.tpmeta-rail-item').dataset.sectionId); return; }
			var dupe = e.target.closest('[data-action="duplicate-section"]');
			if (dupe) { e.stopPropagation(); duplicateSection(dupe.closest('.tpmeta-rail-item').dataset.sectionId); return; }
			var item = e.target.closest('.tpmeta-rail-item');
			if (item) selectSection(item.dataset.sectionId);
		});
	}

	function editSection(sectionId) {
		var section = getSectionById(sectionId);
		if (!section) return;
		TPMetaBuilderModal.openSection(section, function (updated) {
			recordChange();
			Object.assign(section, updated);
			markDirty();
			render();
		});
	}

	function duplicateSection(sectionId) {
		var section = getSectionById(sectionId);
		if (!section) return;
		recordChange();
		// Deep-clone the section and give it a fresh unique ID
		var clone = JSON.parse(JSON.stringify(section));
		clone.id    = uniqueSectionId(section.id + '_copy');
		clone.title = (section.title || 'Section') + ' (Copy)';
		// Give every row a fresh ID
		clone.rows = (clone.rows || []).map(function (row) {
			row.id = uniqueRowId();
			// Give every field a fresh unique ID
			row.fields = (row.fields || []).map(function (field) {
				field.id = uniqueFieldId((field.id || 'field') + '_copy');
				return field;
			});
			return row;
		});
		var idx = state.panel.sections.findIndex(function (s) { return s.id === sectionId; });
		state.panel.sections.splice(idx + 1, 0, clone);
		state.activeSectionId = clone.id;
		markDirty();
		render();
		toast('Section duplicated', 'info', 1800);
	}

	function wireCanvas() {
		var section = activeSection();
		if (!section) return;

		// Section title inline edit
		var titleInput = document.getElementById('tpmeta-section-title');
		if (titleInput) {
			var preTitleVal = titleInput.value;
			titleInput.addEventListener('focus', function () { preTitleVal = this.value; });
			titleInput.addEventListener('input', function (e) {
				section.title = e.target.value;
				markDirty();
				var li = document.querySelector('.tpmeta-rail-item[data-section-id="' + section.id + '"] .tpmeta-rail-item-label');
				if (li) li.textContent = section.title || TPMetaBuilder.i18n.untitled;
			});
			titleInput.addEventListener('change', function () {
				if (this.value !== preTitleVal) recordChange();
			});
		}

		// Section settings gear
		var editBtn = document.getElementById('tpmeta-section-edit-btn');
		if (editBtn) editBtn.addEventListener('click', function () { editSection(section.id); });

		// Canvas-level event delegation (rows, fields, choosers)
		var canvas = document.querySelector('.tpmeta-canvas');
		if (!canvas) return;

		canvas.addEventListener('click', function (e) {
			// Open row chooser
			var addRowBtn = e.target.closest('.tpmeta-add-row-empty-btn, .tpmeta-add-row-mini');
			if (addRowBtn) {
				var chooserId = 'tpmeta-chooser-' + addRowBtn.dataset.sectionId + '-' + addRowBtn.dataset.insert;
				closeAllChoosers();
				var chooser = document.getElementById(chooserId);
				if (chooser) chooser.classList.toggle('is-open');
				return;
			}

			// Layout card click — Grid or Flex
			var layoutCard = e.target.closest('.tpmeta-layout-card');
			if (layoutCard) {
				var layout = layoutCard.dataset.layout;
				if (layout === 'flex') {
					// Add flex row immediately
					var sid = layoutCard.dataset.sectionId;
					var ins = parseInt(layoutCard.dataset.insert, 10);
					closeAllChoosers();
					addRow(sid, 'flex', 1, ins);
				} else if (layout === 'grid') {
					// Show column picker inside chooser
					var chooser2 = document.getElementById(layoutCard.dataset.chooser);
					if (chooser2) {
						chooser2.querySelector('.tpmeta-row-chooser-layouts').style.display = 'none';
						chooser2.querySelector('.tpmeta-row-chooser-cols').style.display = '';
					}
				}
				return;
			}

			// Column count selected in chooser
			var chooserColBtn = e.target.closest('.tpmeta-chooser-col-btn');
			if (chooserColBtn) {
				var cols   = parseInt(chooserColBtn.dataset.cols, 10);
				var sid2   = chooserColBtn.dataset.sectionId;
				var ins2   = parseInt(chooserColBtn.dataset.insert, 10);
				closeAllChoosers();
				addRow(sid2, 'grid', cols, ins2);
				return;
			}

			// Inline column picker (in row head)
			var colBtn = e.target.closest('.tpmeta-col-btn');
			if (colBtn) {
				updateRowColumns(section.id, colBtn.dataset.rowId, parseInt(colBtn.dataset.cols, 10));
				return;
			}

			// Flex direction / wrap buttons
			var flexBtn = e.target.closest('.tpmeta-flex-btn');
			if (flexBtn) {
				updateRowFlex(section.id, flexBtn.dataset.rowId, flexBtn.dataset.flexProp, flexBtn.dataset.flexVal);
				return;
			}

			// Delete row
			var delRow = e.target.closest('.tpmeta-row-delete');
			if (delRow) { deleteRow(section.id, delRow.dataset.rowId); return; }

			// Add column to row
			var addColBtn = e.target.closest('[data-action="add-col"]');
			if (addColBtn) { addColumnToRow(section.id, addColBtn.dataset.rowId); return; }

			// Close slot picker
			if (e.target.closest('[data-action="close-slot-picker"]')) { closeAllSlotPickers(); return; }

			// Slot pick button (choose field type from placeholder picker)
			var pickBtn = e.target.closest('.tpmeta-slot-pick-btn');
			if (pickBtn) {
				var slotRow  = pickBtn.dataset.rowId;
				var slotType = pickBtn.dataset.type;
				var slotIdx  = parseInt(pickBtn.dataset.slot, 10);
				closeAllSlotPickers();
				addFieldToRow(slotRow, slotType, slotIdx);
				return;
			}

			// Placeholder click → inline field type picker
			var ph = e.target.closest('.tpmeta-field-placeholder');
			if (ph && !ph.classList.contains('is-picking')) { openSlotPicker(ph); return; }

			// Copy field ID
			var copyIdBtn = e.target.closest('[data-action="copy-field-id"]');
			if (copyIdBtn) {
				e.stopPropagation();
				var idToCopy = copyIdBtn.dataset.copyId;
				function doCopyFallback() {
					var ta = document.createElement('textarea');
					ta.value = idToCopy;
					ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
					document.body.appendChild(ta);
					ta.select();
					try { document.execCommand('copy'); showFieldIdToast(copyIdBtn, idToCopy); } catch (ex) {}
					ta.remove();
				}
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(idToCopy).then(function () {
						showFieldIdToast(copyIdBtn, idToCopy);
					}).catch(doCopyFallback);
				} else {
					doCopyFallback();
				}
				return;
			}

			// Edit / duplicate / delete field
			var card = e.target.closest('.tpmeta-field-card');
			if (card) {
				var rowId2  = card.dataset.rowId;
				var fIdx    = parseInt(card.dataset.fieldIndex, 10);
				if (e.target.closest('[data-action="edit-field"]'))      { editField(rowId2, fIdx); return; }
				if (e.target.closest('[data-action="duplicate-field"]')) { duplicateField(rowId2, fIdx); return; }
				if (e.target.closest('[data-action="delete-field"]'))    { deleteField(rowId2, fIdx); return; }
			}
		});

	}

	function showFieldIdToast(anchorEl, id) {
		// Tiny inline toast anchored near the copy button
		var existing = document.querySelector('.tpmeta-field-id-toast');
		if (existing) existing.remove();
		var tip = document.createElement('div');
		tip.className = 'tpmeta-field-id-toast';
		tip.textContent = 'ID copied';
		document.body.appendChild(tip);
		var rect = anchorEl.getBoundingClientRect();
		tip.style.top  = (rect.bottom + window.scrollY + 6) + 'px';
		tip.style.left = (rect.left  + window.scrollX)      + 'px';
		// force reflow then show
		tip.offsetWidth; // eslint-disable-line no-unused-expressions
		tip.classList.add('is-visible');
		setTimeout(function () {
			tip.classList.remove('is-visible');
			setTimeout(function () { tip.remove(); }, 200);
		}, 1400);
	}

	function closeAllChoosers() {
		document.querySelectorAll('.tpmeta-row-chooser.is-open').forEach(function (c) {
			c.classList.remove('is-open');
			// Reset grid/flex layout buttons inside
			var layouts = c.querySelector('.tpmeta-row-chooser-layouts');
			var cols    = c.querySelector('.tpmeta-row-chooser-cols');
			if (layouts) layouts.style.display = '';
			if (cols) cols.style.display = 'none';
		});
	}

	function wirePalette() {
		var input = document.getElementById('tpmeta-palette-search');
		if (!input) return;
		input.addEventListener('input', function (e) {
			paletteFilter = e.target.value;
			// Only update the palette list, keep focus.
			var list = document.getElementById('tpmeta-palette-list');
			if (!list) return;
			var q = paletteFilter.toLowerCase();
			var html = (TPMetaBuilder.fieldTypes || []).filter(function (t) {
				if (!q) return true;
				return t.label.toLowerCase().indexOf(q) !== -1 || t.type.toLowerCase().indexOf(q) !== -1;
			}).map(function (t) {
				return '<li class="tpmeta-palette-item" data-field-type="' + esc(t.type) + '">'
					+ '<span class="dashicons dashicons-' + esc(t.icon) + '"></span>'
					+ '<span>' + esc(t.label) + '</span>'
					+ '</li>';
			}).join('');
			list.innerHTML = html;
			// Re-init sortable for the new items.
			refreshPaletteSortable();
		});
	}

	function refreshPaletteSortable() {
		var palette = document.getElementById('tpmeta-palette-list');
		if (!palette) return;
		sortables = sortables.filter(function (s) {
			if (s.el === palette) { try { s.destroy(); } catch (e) {} return false; }
			return true;
		});
		sortables.push(Sortable.create(palette, {
			group: { name: 'tpmeta-palette', pull: 'clone', put: false },
			sort: false, animation: 150,
		}));
	}

	function setupSortables() {
		var section = activeSection();

		// Sections rail
		var rail = document.getElementById('tpmeta-sections-list');
		if (rail) {
			sortables.push(Sortable.create(rail, {
				handle:    '.tpmeta-rail-item-handle',
				animation: 150,
				onEnd: function (evt) {
					if (evt.oldIndex === evt.newIndex) return;
					recordChange();
					var moved = state.panel.sections.splice(evt.oldIndex, 1)[0];
					state.panel.sections.splice(evt.newIndex, 0, moved);
					markDirty();
				},
			}));
		}

		// Row list (drag rows within a section).
		// IMPORTANT: evt.oldIndex / evt.newIndex count ALL children including
		// the .tpmeta-add-row-divider elements, so index math is wrong.
		// Instead, re-read the DOM order after Sortable finishes moving the element.
		var rowList = document.getElementById('tpmeta-row-list');
		if (rowList && section) {
			sortables.push(Sortable.create(rowList, {
				handle:    '.tpmeta-row-handle',
				draggable: '.tpmeta-row',
				animation: 150,
				onEnd: function () {
					var ordered = [];
					rowList.querySelectorAll('.tpmeta-row').forEach(function (el) {
						var row = section.rows.find(function (r) { return r && r.id === el.dataset.rowId; });
						if (row) ordered.push(row);
					});
					if (ordered.length !== section.rows.length) return;
					recordChange();
					section.rows = ordered;
					markDirty();
				},
			}));
		}

		// Per-row field grids (all share same group so fields can cross rows)
		var sharedGroup = 'tpmeta-row-fields-' + (section ? section.id : 'none');
		document.querySelectorAll('[id^="tpmeta-fg-"]').forEach(function (grid) {
			var rowId = grid.dataset.rowId;
			sortables.push(Sortable.create(grid, {
				group:      { name: sharedGroup, pull: true, put: [sharedGroup, 'tpmeta-palette'] },
				draggable:  '.tpmeta-field-card',
				animation:  150,
				ghostClass: 'tpmeta-field-ghost',
				dragClass:  'tpmeta-field-dragging',
				onStart: function () {
					document.body.classList.add('is-dragging');
				},
				onMove: function (evt) {
					document.querySelectorAll('.tpmeta-fields-grid').forEach(function (g) {
						g.classList.remove('is-drop-target');
					});
					if (evt.to && evt.to.classList.contains('tpmeta-fields-grid')) {
						evt.to.classList.add('is-drop-target');
					}
				},
				onEnd: function (evt) {
					document.body.classList.remove('is-dragging');
					document.querySelectorAll('.tpmeta-fields-grid').forEach(function (g) {
						g.classList.remove('is-drop-target');
					});
					// Guard: placeholder accidentally dragged (shouldn't happen with draggable: '.tpmeta-field-card')
					if (evt.item.classList.contains('tpmeta-field-placeholder')) { render(); return; }
					if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
					recordChange();
					var srcRowId  = evt.from.dataset.rowId;
					var dstRowId  = evt.to.dataset.rowId;
					var srcRow    = getRowById(section ? section.id : '', srcRowId);
					var dstRow    = getRowById(section ? section.id : '', dstRowId);
					if (!srcRow || !dstRow) { render(); return; }
					var moved = srcRow.fields.splice(evt.oldIndex, 1)[0];
					if (!moved) { render(); return; }
					// Clamp: evt.newIndex may point beyond fields into placeholder area
					var insertAt = Math.min(evt.newIndex, dstRow.fields.length);
					dstRow.fields.splice(insertAt, 0, moved);
					markDirty();
					// Flash the destination grid before re-render
					var destGrid = evt.to;
					if (destGrid) {
						destGrid.classList.add('tpmeta-drop-accepted');
						setTimeout(function () { render(); }, 350);
					} else {
						render();
					}
				},
				onAdd: function (evt) {
					document.body.classList.remove('is-dragging');
					document.querySelectorAll('.tpmeta-fields-grid').forEach(function (g) {
						g.classList.remove('is-drop-target');
					});
					var type = evt.item.dataset.fieldType;
					if (!type) return;
					evt.item.remove();
					var dstRow2 = getRowById(section ? section.id : '', rowId);
					if (!dstRow2) return;
					var insertAt = Math.min(evt.newIndex, dstRow2.fields.length);
					// Flash the destination grid before adding the field
					var destGrid2 = document.getElementById('tpmeta-fg-' + rowId);
					if (destGrid2) {
						destGrid2.classList.add('tpmeta-drop-accepted');
						setTimeout(function () { addFieldToRow(rowId, type, insertAt); }, 350);
					} else {
						addFieldToRow(rowId, type, insertAt);
					}
				},
			}));
		});

		// Palette (clone-only source)
		var palette = document.getElementById('tpmeta-palette-list');
		if (palette) {
			sortables.push(Sortable.create(palette, {
				group:     { name: 'tpmeta-palette', pull: 'clone', put: false },
				sort:      false,
				animation: 150,
				onStart: function () { document.body.classList.add('is-dragging'); },
				onMove: function (evt) {
					document.querySelectorAll('.tpmeta-fields-grid').forEach(function (g) {
						g.classList.remove('is-drop-target');
					});
					if (evt.to && evt.to.classList.contains('tpmeta-fields-grid')) {
						evt.to.classList.add('is-drop-target');
					}
				},
				onEnd: function () {
					document.body.classList.remove('is-dragging');
					document.querySelectorAll('.tpmeta-fields-grid').forEach(function (g) {
						g.classList.remove('is-drop-target');
					});
				},
			}));
		}
	}

	function teardownSortables() {
		sortables.forEach(function (s) { try { s.destroy(); } catch (e) {} });
		sortables = [];
	}

	// ---------- ACTIONS ----------

	function activeSection() {
		return state.panel.sections.find(function (s) { return s.id === state.activeSectionId; }) || null;
	}

	function addSection() {
		recordChange();
		var n = state.panel.sections.length + 1;
		var id = uniqueSectionId(fieldIdPrefix() + 'section_' + n);
		state.panel.sections.push({
			id:          id,
			title:       TPMetaBuilder.i18n.newSection + ' ' + n,
			icon:        '',
			description: '',
			rows:        [],
		});
		state.activeSectionId = id;
		markDirty();
		render();
	}

	function deleteSection(sectionId) {
		if (!confirm(TPMetaBuilder.i18n.confirmDelete)) return;
		recordChange();
		state.panel.sections = state.panel.sections.filter(function (s) { return s.id !== sectionId; });
		if (state.activeSectionId === sectionId) {
			state.activeSectionId = state.panel.sections.length ? state.panel.sections[0].id : '';
		}
		markDirty();
		render();
	}

	function selectSection(sectionId) {
		state.activeSectionId = sectionId;
		rememberSection(state.panel.opt_name, sectionId);
		render();
	}

	// ---------- Row actions ----------

	function addRow(sectionId, layout, columns, insertIndex) {
		var section = getSectionById(sectionId);
		if (!section) return;
		recordChange();
		var row = {
			id:        uniqueRowId(),
			layout:    layout || 'grid',
			columns:   columns || 1,
			direction: 'row',
			wrap:      'wrap',
			fields:    [],
		};
		if (typeof insertIndex === 'number' && insertIndex >= 0) {
			section.rows.splice(insertIndex, 0, row);
		} else {
			section.rows.push(row);
		}
		markDirty();
		render();
	}

	function deleteRow(sectionId, rowId) {
		var section = getSectionById(sectionId);
		if (!section) return;
		var row = getRowById(sectionId, rowId);
		if (!row) return;
		if (row.fields.length && !confirm('Delete this row and its ' + row.fields.length + ' field(s)?')) return;
		recordChange();
		section.rows = section.rows.filter(function (r) { return r.id !== rowId; });
		markDirty();
		render();
	}

	function updateRowColumns(sectionId, rowId, cols) {
		var row = getRowById(sectionId, rowId);
		if (!row) return;
		recordChange();
		row.columns = cols;
		markDirty();
		render();
	}

	function updateRowFlex(sectionId, rowId, prop, value) {
		var row = getRowById(sectionId, rowId);
		if (!row) return;
		recordChange();
		row[prop] = value;
		markDirty();
		render();
	}

	// ---------- Field actions ----------

	function duplicateField(rowId, idx) {
		var section = activeSection();
		if (!section) return;
		var row = getRowById(section.id, rowId);
		if (!row || !row.fields[idx]) return;
		recordChange();
		var copy  = JSON.parse(JSON.stringify(row.fields[idx]));
		copy.id   = uniqueFieldId((copy.id || 'field') + '_copy');
		row.fields.splice(idx + 1, 0, copy);
		markDirty();
		render();
		toast('Field duplicated', 'info', 1500);
	}

	function addColumnToRow(sectionId, rowId) {
		var row = getRowById(sectionId, rowId);
		if (!row || (row.columns || 1) >= 12) return;
		recordChange();
		row.columns = (row.columns || 1) + 1;
		markDirty();
		render();
	}

	function openSlotPicker(ph) {
		closeAllSlotPickers();
		ph.classList.add('is-picking');
		var rowId   = ph.dataset.rowId;
		var slotIdx = ph.dataset.slotIndex || '0';
		var typesHtml = (TPMetaBuilder.fieldTypes || []).map(function (t) {
			return '<button class="tpmeta-slot-pick-btn" data-type="' + esc(t.type) + '"'
				+ ' data-row-id="' + esc(rowId) + '" data-slot="' + esc(slotIdx) + '">'
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
		var meta  = fieldTypeMeta(type);
		var field = {
			id:      uniqueFieldId(fieldIdPrefix() + type),
			type:    type,
			label:   meta.label,
			default: '',
		};
		if (typeof atIndex === 'number') {
			row.fields.splice(atIndex, 0, field);
		} else {
			row.fields.push(field);
		}
		markDirty();
		render();
		var idx = row.fields.indexOf(field);
		setTimeout(function () { editField(rowId, idx); }, 50);
	}

	function editField(rowId, idx) {
		var section = activeSection();
		if (!section) return;
		var row = getRowById(section.id, rowId);
		if (!row) return;
		var field = row.fields[idx];
		if (!field) return;
		// Gather all fields across all rows for the conditional trigger picker.
		var allFields = [];
		section.rows.forEach(function (r) { allFields = allFields.concat(r.fields); });
		TPMetaBuilderModal.open(field, allFields, function (updated) {
			recordChange();
			row.fields[idx] = updated;
			markDirty();
			render();
		});
	}

	function deleteField(rowId, idx) {
		if (!confirm(TPMetaBuilder.i18n.confirmDelete)) return;
		recordChange();
		var section = activeSection();
		var row = getRowById(section.id, rowId);
		if (!row) return;
		row.fields.splice(idx, 1);
		markDirty();
		render();
	}

	// Kept for backward compat — called from addSection when auto-opening modal.
	function addField(type, atIndex) {
		var section = activeSection();
		if (!section) return;
		if (!section.rows.length) { addRow(section.id, 'grid', 1, 0); }
		var row = section.rows[section.rows.length - 1];
		addFieldToRow(row.id, type, atIndex);
	}

	function editPanelArgs() {
		var p = state.panel;
		// What to show in the prefix box: an explicitly-set field_prefix wins,
		// else the prefix auto-detected from existing field IDs, else the
		// legacy opt_name-based prefix (so applying unchanged is a true no-op).
		var displayPrefix = (typeof p.field_prefix === 'string') ? p.field_prefix : (detectImportPrefix(p) || fieldIdPrefix());
		var shownPrefix   = normalizeIdPrefix(displayPrefix);
		var fieldIds      = collectFieldIds(p);
		var sampleId      = fieldIds.length ? fieldIds[0] : (shownPrefix + 'fieldname');

		TPMetaBuilderModal.openPanel(p, function (updated) {
			recordChange();
			var requested = normalizeIdPrefix(updated.field_prefix || '');
			delete updated.field_prefix;
			Object.assign(state.panel, updated);

			// Only bulk-rename when the user actually changed the prefix box —
			// merely opening Panel Settings and clicking Apply must not silently
			// rewrite IDs (which would orphan saved values keyed by the old IDs).
			if (requested !== shownPrefix) {
				state.panel = applyImportPrefix(state.panel, requested);
				if (state.activeSectionId && !getSectionById(state.activeSectionId)) {
					state.activeSectionId = state.panel.sections.length ? state.panel.sections[0].id : '';
				}
			}
			// Persist the choice so new fields/sections auto-adopt it. Stored
			// even when unchanged so the box keeps its value next open.
			state.panel.field_prefix = requested;

			markDirty();
			render();
		}, {
			prefix: shownPrefix,
			exampleFor: function (raw) {
				var renamed = remapId(sampleId, normalizeIdPrefix(raw));
				return (renamed === sampleId)
					? 'Example: ' + sampleId + ' (unchanged)'
					: 'Example: ' + sampleId + ' → ' + renamed;
			},
		});
	}

	function onPanelSelect(e) {
		var v = e.target.value;
		var found = (TPMetaBuilder.panels || []).find(function (p) { return p.opt_name === v; });
		if (found) {
			state.panel = JSON.parse(JSON.stringify(found));
			state.panel.sections = state.panel.sections.map(migrateSection);
			state.activeSectionId = state.panel.sections.length ? state.panel.sections[0].id : '';
			state.dirty = false;
			rememberPanel(state.panel.opt_name);
			render();
		}
	}

	// ---------- EXPORT / IMPORT ----------

	function exportPanel() {
		// Bundle saved theme_mod values with the panel structure so a re-import
		// on a different WP install restores typography, post_select, switches,
		// repeater rows, etc. — not just the panel shape.
		var optName = state.panel.opt_name || '';
		var savedExists = (TPMetaBuilder.panels || []).some(function (p) { return p.opt_name === optName; });

		function writeDownload(panelObject) {
			var json = JSON.stringify(panelObject, null, 2);
			var blob = new Blob([json], { type: 'application/json' });
			var a    = document.createElement('a');
			a.href     = URL.createObjectURL(blob);
			a.download = (panelObject.opt_name || 'panel') + '.tpmeta-panel.json';
			document.body.appendChild(a);
			a.click();
			a.remove();
			URL.revokeObjectURL(a.href);
			toast('Panel exported as ' + a.download, 'success', 3000);
		}

		// Unsaved panel → no theme_mods to fetch; export structure only.
		if (!savedExists) {
			writeDownload(state.panel);
			toast('Panel has no saved values yet — exported structure only.', 'info', 4000);
			return;
		}

		setStatus('saving', 'Bundling saved values…');
		wp.apiFetch({
			path:   'tpmeta/v1/builder/panels/' + encodeURIComponent(optName) + '?include_values=1',
			method: 'GET',
		}).then(function (resp) {
			// Compose: live state.panel (reflects unsaved edits) + server-side
			// values/meta (reflects what's actually stored as theme_mods).
			var bundle = Object.assign({}, state.panel, {
				_values:      (resp && resp._values) ? resp._values : {},
				_meta:        (resp && resp._meta)   ? resp._meta   : {},
				_exported_at: new Date().toISOString(),
			});
			var valCount = Object.keys(bundle._values).length;
			console.log('[TPMETA_EXPORT] bundle for ' + optName + ': sections=' + (bundle.sections || []).length + ', values=' + valCount + ', value_keys=' + JSON.stringify(Object.keys(bundle._values)));
			setStatus('idle', '');
			writeDownload(bundle);
			if (valCount) {
				toast(valCount + ' saved value' + (valCount !== 1 ? 's' : '') + ' bundled into the export.', 'success', 4000);
			} else {
				toast('Warning: no saved values were bundled. Either the panel has no saved data, or you need to click Save in the builder before exporting.', 'error', 7000);
			}
		}).catch(function (err) {
			console.error(err);
			setStatus('error', 'Export error');
			toast('Could not fetch saved values — exported structure only. (' + (err.message || 'unknown error') + ')', 'error', 5000);
			writeDownload(state.panel);
			setTimeout(function () { setStatus('idle', ''); }, 2500);
		});
	}

	function openImportModal(mode) {
		var dlg = document.getElementById('tpmeta-import-modal');
		if (!dlg) return;
		var initialMode = (mode === 'sections') ? 'sections' : 'full';
		// Reset state.
		document.getElementById('tpmeta-import-json').value           = '';
		document.getElementById('tpmeta-import-file').value           = '';
		document.getElementById('tpmeta-import-file-name').textContent = '';
		document.getElementById('tpmeta-import-preview').style.display = 'none';
		document.getElementById('tpmeta-import-submit').disabled       = true;
		var prefixWrap = document.getElementById('tpmeta-import-prefix-wrap');
		if (prefixWrap) {
			prefixWrap.style.display = 'none';
			document.getElementById('tpmeta-import-prefix').value = '';
			document.getElementById('tpmeta-import-prefix-example').textContent = '';
		}
		// Preselect the mode radio + sync notice/button to it.
		var radios = dlg.querySelectorAll('input[name="tpmeta-import-mode"]');
		for (var i = 0; i < radios.length; i++) {
			radios[i].checked = (radios[i].value === initialMode);
		}
		applyImportMode(initialMode);
		dlg.showModal();
	}

	function applyImportMode(mode) {
		var noticeEl = document.querySelector('#tpmeta-import-modal .tpmeta-import-notice-text');
		var submitBtn = document.getElementById('tpmeta-import-submit');
		if (mode === 'sections') {
			if (noticeEl) noticeEl.textContent = 'Sections and fields from the file will be injected into the currently selected panel. Panel settings and saved values are not changed.';
			if (submitBtn) submitBtn.textContent = 'Import Sections';
		} else {
			if (noticeEl) noticeEl.textContent = 'The full panel — name, slug, customizer settings, conditions, every field, and any saved values bundled with the export — will be restored. Save to apply.';
			if (submitBtn) submitBtn.textContent = 'Import Panel';
		}
	}

	function getImportMode() {
		var checked = document.querySelector('#tpmeta-import-modal input[name="tpmeta-import-mode"]:checked');
		return (checked && checked.value === 'sections') ? 'sections' : 'full';
	}

	/* ── Import ID prefixing ──────────────────────────────────────────────────
	 * Lets the user prefix (or re-prefix) every field + section ID at import
	 * time so an imported panel doesn't collide with existing theme_mods.
	 * Field IDs are the theme_mod keys, so bundled _values/_meta and any
	 * conditional.field references are remapped alongside the ID rename.
	 */

	// Normalise a user-typed prefix to the field-id charset. Empty stays empty;
	// anything non-empty is guaranteed to end in a single underscore.
	function normalizeIdPrefix(raw) {
		var s = String(raw == null ? '' : raw).toLowerCase()
			.replace(/[^a-z0-9_]/g, '_')
			.replace(/_+/g, '_')
			.replace(/^_+/, '')
			.replace(/_+$/, '');
		return s ? s + '_' : '';
	}

	// An id's "prefix" is its FIRST WORD — the token up to and including the
	// first underscore (e.g. "finzo_header_logo" → "finzo_"). Returns '' when
	// the id has no underscore (no leading prefix word to replace).
	function idFirstWord(id) {
		var s = String(id == null ? '' : id);
		var u = s.indexOf('_');
		return u === -1 ? '' : s.slice(0, u + 1);
	}

	// Iterate every top-level field across all sections (rows[] and legacy
	// fields[]). Repeater sub-fields are intentionally skipped — their IDs are
	// row keys inside the repeater's JSON value, not theme_mod keys.
	function eachPanelField(panel, cb) {
		(panel.sections || []).forEach(function (s) {
			(s.rows || []).forEach(function (r) {
				(r.fields || []).forEach(function (f) { if (f) cb(f); });
			});
			if (Array.isArray(s.fields)) s.fields.forEach(function (f) { if (f) cb(f); });
		});
	}

	function collectFieldIds(panel) {
		var ids = [];
		eachPanelField(panel, function (f) { if (f.id) ids.push(String(f.id)); });
		return ids;
	}

	// Swap an id's first-word prefix for newPrefix: drop the leading token (up
	// to the first underscore) and prepend newPrefix. Ids with no underscore
	// have no prefix word, so newPrefix is simply prepended.
	//   "finzo_header_logo", "shop_" → "shop_header_logo"
	//   "logo",              "shop_" → "shop_logo"
	function remapId(id, newPrefix) {
		var s = String(id == null ? '' : id);
		var u = s.indexOf('_');
		var rest = (u === -1) ? s : s.slice(u + 1);
		return newPrefix + rest;
	}

	// The prefix shown to the user by default — the first word of the first
	// field id (the de-facto prefix for typical, uniformly-prefixed panels).
	function detectImportPrefix(panel) {
		var ids = collectFieldIds(panel);
		return ids.length ? idFirstWord(ids[0]) : '';
	}

	// Return a deep clone of `panel` with `newPrefix` applied to every field +
	// section id. Each id's own first-word prefix is replaced, so a panel with
	// mixed prefixes is unified under newPrefix. _values / _meta keys and
	// conditional.field references are remapped via the same field-id map.
	function applyImportPrefix(panel, newPrefix) {
		var clone = JSON.parse(JSON.stringify(panel));

		var fieldMap = {};
		collectFieldIds(clone).forEach(function (id) {
			fieldMap[id] = remapId(id, newPrefix);
		});

		eachPanelField(clone, function (f) {
			if (f.conditional && f.conditional.field && fieldMap[f.conditional.field]) {
				f.conditional.field = fieldMap[f.conditional.field];
			}
			if (f.id && fieldMap[f.id]) f.id = fieldMap[f.id];
		});

		(clone.sections || []).forEach(function (s) {
			if (s && s.id) s.id = remapId(s.id, newPrefix);
		});

		['_values', '_meta'].forEach(function (key) {
			if (clone[key] && typeof clone[key] === 'object' && !Array.isArray(clone[key])) {
				var remapped = {};
				Object.keys(clone[key]).forEach(function (k) {
					remapped[fieldMap[k] || k] = clone[key][k];
				});
				clone[key] = remapped;
			}
		});

		return clone;
	}

	function wireImportModal() {
		var dlg = document.getElementById('tpmeta-import-modal');
		if (!dlg) return;

		var textarea   = document.getElementById('tpmeta-import-json');
		var fileInput  = document.getElementById('tpmeta-import-file');
		var fileLabel  = document.getElementById('tpmeta-import-file-name');
		var preview    = document.getElementById('tpmeta-import-preview');
		var previewName = document.getElementById('tpmeta-import-preview-name');
		var previewMeta = document.getElementById('tpmeta-import-preview-meta');
		var submitBtn  = document.getElementById('tpmeta-import-submit');
		var dropzone   = document.getElementById('tpmeta-import-dropzone');
		var prefixWrap    = document.getElementById('tpmeta-import-prefix-wrap');
		var prefixInput   = document.getElementById('tpmeta-import-prefix');
		var prefixExample = document.getElementById('tpmeta-import-prefix-example');

		var parsedPanel    = null;
		var detectedPrefix = '';

		function updatePrefixExample() {
			if (!prefixExample) return;
			var ids = parsedPanel ? collectFieldIds(parsedPanel) : [];
			if (!ids.length) { prefixExample.textContent = ''; return; }
			var newPrefix = normalizeIdPrefix(prefixInput.value);
			var sample = ids[0];
			var renamed = remapId(sample, newPrefix);
			prefixExample.textContent = (renamed === sample)
				? 'Example: ' + sample + ' (unchanged)'
				: 'Example: ' + sample + ' → ' + renamed;
		}

		function tryParse(json) {
			parsedPanel = null;
			submitBtn.disabled = true;
			preview.style.display = 'none';
			if (prefixWrap) prefixWrap.style.display = 'none';
			try {
				var p = JSON.parse(json.trim());
				// Accept files exported from Tools → Export / Import too. Those
				// wrap panels in a bundle: { _exported, options: [ ...panels ] }.
				// A bare array of panels is also tolerated. Unwrap to a single
				// panel here (the builder imports one at a time).
				var bundle = null;
				if (p && typeof p === 'object' && !p.opt_name) {
					if (Array.isArray(p.options))      bundle = p.options;
					else if (Array.isArray(p))          bundle = p;
				}
				if (bundle) {
					var panels = bundle.filter(function (x) {
						return x && typeof x === 'object' && x.opt_name && Array.isArray(x.sections);
					});
					if (panels.length) {
						p = panels[0];
						if (panels.length > 1) {
							toast('File contains ' + panels.length + ' panels — loaded the first ("' + (p.menu_title || p.opt_name) + '"). Use Tools → Export / Import to restore all of them.', 'info', 6000);
						}
					}
				}
				if (!p || typeof p !== 'object' || !p.opt_name || !Array.isArray(p.sections)) {
					toast('JSON is not a valid TPMeta panel schema.', 'error');
					return;
				}
				parsedPanel = p;
				var sectionCount = p.sections.length;
				var fieldCount   = 0;
				p.sections.forEach(function (s) {
					(s.rows || s.fields || []).forEach(function (r) {
						fieldCount += Array.isArray(r.fields) ? r.fields.length : 1;
					});
				});
				// Surface bundled values so the user knows whether the export
				// actually carried saved data (typography, post selects, etc.)
				// or just the panel schema.
				var valueCount = (p._values && typeof p._values === 'object') ? Object.keys(p._values).length : 0;
				previewName.textContent = p.menu_title || p.opt_name;
				var metaLine = sectionCount + ' section' + (sectionCount !== 1 ? 's' : '')
					+ ' · ' + fieldCount + ' field' + (fieldCount !== 1 ? 's' : '');
				if (valueCount) {
					metaLine += ' · ' + valueCount + ' saved value' + (valueCount !== 1 ? 's' : '');
				} else {
					metaLine += ' · no saved values bundled';
				}
				previewMeta.textContent = metaLine;
				preview.style.display = 'flex';
				submitBtn.disabled = false;
				// Prefill the prefix box with the detected common prefix so the
				// default action is a no-op; the user can edit/clear to re-prefix.
				if (prefixWrap) {
					detectedPrefix = detectImportPrefix(parsedPanel);
					prefixInput.value = detectedPrefix;
					prefixWrap.style.display = 'block';
					updatePrefixExample();
				}
			} catch (e) {
				toast('Could not parse JSON: ' + e.message, 'error');
			}
		}

		// Textarea → parse on input
		textarea.addEventListener('input', function () {
			if (this.value.trim()) tryParse(this.value);
			else { parsedPanel = null; submitBtn.disabled = true; preview.style.display = 'none'; }
		});

		// File picker
		function loadFile(file) {
			if (!file) return;
			fileLabel.textContent = file.name;
			var reader = new FileReader();
			reader.onload = function (e) {
				textarea.value = e.target.result;
				tryParse(e.target.result);
			};
			reader.readAsText(file);
		}
		fileInput.addEventListener('change', function () { loadFile(this.files[0]); });

		// Drag-and-drop on the dropzone
		dropzone.addEventListener('dragover',  function (e) { e.preventDefault(); dropzone.classList.add('is-over'); });
		dropzone.addEventListener('dragleave', function ()  { dropzone.classList.remove('is-over'); });
		dropzone.addEventListener('drop', function (e) {
			e.preventDefault();
			dropzone.classList.remove('is-over');
			var file = e.dataTransfer.files[0];
			if (file) loadFile(file);
		});

		// Prefix box → live example
		if (prefixInput) prefixInput.addEventListener('input', updatePrefixExample);

		// Close / cancel
		dlg.querySelector('.tpmeta-builder-modal-close').addEventListener('click', function () { dlg.close(); });
		document.getElementById('tpmeta-import-cancel').addEventListener('click', function () { dlg.close(); });

		// Mode toggle → live-update notice + submit button label.
		var modeRadios = dlg.querySelectorAll('input[name="tpmeta-import-mode"]');
		for (var i = 0; i < modeRadios.length; i++) {
			modeRadios[i].addEventListener('change', function () { applyImportMode(this.value); });
		}

		// Submit
		submitBtn.addEventListener('click', function () {
			if (!parsedPanel) return;
			var newPrefix = normalizeIdPrefix(prefixInput ? prefixInput.value : '');
			// Only transform when the prefix actually changed — keeps a plain
			// re-import byte-for-byte identical.
			var toImport = (newPrefix === detectedPrefix)
				? parsedPanel
				: applyImportPrefix(parsedPanel, newPrefix);
			doImport(toImport, getImportMode());
			dlg.close();
		});
	}

	function doImport(imported, mode) {
		var sections = (imported.sections || []).map(migrateSection);

		if (mode === 'sections') {
			// Legacy path — inject sections only, leave panel identity intact.
			recordChange();
			state.panel.sections  = sections;
			state.activeSectionId = sections.length ? sections[0].id : '';
			state.dirty           = true;
			setStatus('idle', '*');
			render();
			toast('Sections imported into "' + (state.panel.menu_title || state.panel.opt_name) + '"', 'success', 4000);
			return;
		}

		// Full restore — rebuild state.panel from the imported JSON, preserving every
		// top-level setting (menu title, slug, capability, customizer integration,
		// customizer priority, etc.). Field-level data (conditions, options, output
		// rules, repeater sub-fields) rides along inside sections and is preserved
		// by the server's sanitize_field() on save.
		var optName = (imported.opt_name || '').toString().toLowerCase().replace(/[^a-z0-9_]/g, '_') || 'imported_panel';
		var currentOpt = (state.panel && state.panel.opt_name) ? state.panel.opt_name : '';
		// Uniqueify only if a DIFFERENT existing saved panel claims this opt_name.
		// A self-reimport (same opt_name as currently loaded panel) keeps its slug.
		var clashes = (TPMetaBuilder.panels || []).some(function (p) {
			return p.opt_name === optName && p.opt_name !== currentOpt;
		});
		if (clashes) {
			optName = uniquePanelOptName(optName);
		}

		var newPanel = {
			opt_name:               optName,
			menu_title:             imported.menu_title || '',
			page_title:             imported.page_title || imported.menu_title || '',
			menu_slug:              imported.menu_slug ? String(imported.menu_slug).toLowerCase().replace(/[^a-z0-9_-]/g, '-') : optName.replace(/_/g, '-'),
			menu_icon:              imported.menu_icon || 'dashicons-admin-generic',
			menu_position:          (typeof imported.menu_position !== 'undefined' && imported.menu_position !== '' && !isNaN(imported.menu_position)) ? parseInt(imported.menu_position, 10) : 60,
			capability:             imported.capability || 'manage_options',
			output_css:             !!imported.output_css,
			customizer_integration: !!imported.customizer_integration,
			sections:               sections,
		};
		if (typeof imported.customizer_priority !== 'undefined' && imported.customizer_priority !== '' && !isNaN(imported.customizer_priority)) {
			newPanel.customizer_priority = parseInt(imported.customizer_priority, 10);
		}

		// Stash imported saved values + cross-site hints so savePanel() can
		// forward them to the server in the same POST. Keyed by opt_name so a
		// panel switch before save doesn't apply them to the wrong panel.
		state.pendingImportValues = null;
		if (imported._values && typeof imported._values === 'object') {
			state.pendingImportValues = {
				opt_name: optName,
				values:   imported._values,
				meta:     (imported._meta && typeof imported._meta === 'object') ? imported._meta : {},
			};
		}

		recordChange();
		state.panel           = newPanel;
		state.activeSectionId = sections.length ? sections[0].id : '';
		state.dirty           = true;
		state.screen          = 'builder';
		rememberScreen('builder');
		setStatus('idle', '*');
		render();

		var optChanged = clashes ? ' (renamed to "' + optName + '" — original slug was taken)' : '';
		var valCount   = state.pendingImportValues ? Object.keys(state.pendingImportValues.values).length : 0;
		console.log('[TPMETA_IMPORT] doImport full: opt_name=' + optName + ', sections=' + sections.length + ', values_in_bundle=' + valCount);
		toast('Imported "' + (newPanel.menu_title || newPanel.opt_name) + '" — ' + sections.length + ' section' + (sections.length !== 1 ? 's' : '') + (valCount ? ' + ' + valCount + ' saved value' + (valCount !== 1 ? 's' : '') : '') + optChanged + '. Saving + applying values…', 'success', 4000);

		// Auto-save the structure, then apply values via the dedicated REST
		// route. This removes the manual-Save dance and prevents the "I
		// imported but values aren't there" trap caused by users navigating
		// away before clicking Save.
		autoSaveImportedPanel();
	}

	function autoSaveImportedPanel() {
		var errors = validatePanel();
		if (errors.length) {
			errors.slice(0, 3).forEach(function (e) { toast(e.message, 'error', 5000); });
			setStatus('error', errors.length + ' error' + (errors.length === 1 ? '' : 's') + ' — fix and click Save');
			return;
		}

		setStatus('saving', 'Saving imported panel…');
		var importStash = state.pendingImportValues;
		console.log('[TPMETA_IMPORT] auto-save POST /panels with payload:', state.panel);
		wp.apiFetch({
			path:   'tpmeta/v1/builder/panels',
			method: 'POST',
			data:   state.panel,
		}).then(function (resp) {
			state.dirty = false;
			rememberPanel(resp.panel.opt_name);
			var idx = (TPMetaBuilder.panels || []).findIndex(function (p) { return p.opt_name === resp.panel.opt_name; });
			if (idx >= 0) TPMetaBuilder.panels[idx] = resp.panel;
			else TPMetaBuilder.panels.push(resp.panel);

			// No values to apply → done.
			if (!importStash || !importStash.values || !Object.keys(importStash.values).length) {
				state.pendingImportValues = null;
				setStatus('saved', 'Saved');
				toast('Panel saved (no values to apply).', 'success', 4000);
				setTimeout(function () { setStatus('idle', ''); }, 2500);
				return;
			}

			// Structure is in place — push values to the dedicated apply route.
			setStatus('saving', 'Applying ' + Object.keys(importStash.values).length + ' saved values…');
			console.log('[TPMETA_IMPORT] applying values via /values route, count=' + Object.keys(importStash.values).length);
			return wp.apiFetch({
				path:   'tpmeta/v1/builder/panels/' + encodeURIComponent(resp.panel.opt_name) + '/values',
				method: 'POST',
				data:   { values: importStash.values, meta: importStash.meta || {} },
			}).then(function (vr) {
				state.pendingImportValues = null;
				var applied  = (vr && vr.applied  || []).length;
				var skipped  = (vr && vr.skipped  || []).length;
				var remapped = vr && vr.remapped  ? Object.keys(vr.remapped).length : 0;
				console.log('[TPMETA_IMPORT] /values response:', vr);
				setStatus('saved', 'Saved');
				var msg = applied + ' of ' + Object.keys(importStash.values).length + ' saved value' + (applied !== 1 ? 's' : '') + ' applied';
				if (remapped) msg += ' (' + remapped + ' post reference' + (remapped !== 1 ? 's' : '') + ' remapped)';
				if (skipped)  msg += ' — ' + skipped + ' skipped (no matching field on this site)';
				toast(msg, applied ? 'success' : 'error', 7000);
				setTimeout(function () { setStatus('idle', ''); }, 2500);
			});
		}).catch(function (err) {
			console.error('[TPMETA_IMPORT] auto-save error', err);
			setStatus('error', 'Save error');
			toast('Auto-save failed: ' + (err.message || 'unknown error'), 'error', 6000);
		});
	}

	// ---------- SAVE ----------

	function savePanel() {
		var errors = validatePanel();
		if (errors.length) {
			errors.slice(0, 3).forEach(function (e) { toast(e.message, 'error', 5000); });
			if (errors.length > 3) {
				toast('+ ' + (errors.length - 3) + ' more issue(s)', 'error', 5000);
			}
			setStatus('error', errors.length + ' error' + (errors.length === 1 ? '' : 's'));
			return;
		}

		setStatus('saving', TPMetaBuilder.i18n.saving);

		// Piggyback any stashed import values + cross-site hints. The server
		// strips these before sanitize_schema() and applies them as theme_mods
		// AFTER the structure save (so post_select cross-site lookups can find
		// the field defs they need). Cleared after success.
		var payload = state.panel;
		var pendingImport = state.pendingImportValues || null;
		// Only forward when the pending stash still matches the panel being
		// saved — guards against the user switching panels after import.
		if (pendingImport && pendingImport.opt_name === state.panel.opt_name) {
			payload = Object.assign({}, state.panel, {
				_values: pendingImport.values,
				_meta:   pendingImport.meta,
			});
		} else {
			pendingImport = null;
		}

		wp.apiFetch({
			path:   'tpmeta/v1/builder/panels',
			method: 'POST',
			data:   payload,
		}).then(function (resp) {
			state.dirty = false;
			state.pendingImportValues = null;
			setStatus('saved', TPMetaBuilder.i18n.saved);
			toast('Panel saved', 'success');
			// Surface a separate toast for value-import outcome.
			if (resp && resp.values) {
				var v = resp.values;
				var applied  = (v.applied  || []).length;
				var remapped = Object.keys(v.remapped || {}).length;
				var skipped  = (v.skipped  || []).length;
				if (applied) {
					var msg = applied + ' saved value' + (applied !== 1 ? 's' : '') + ' restored';
					if (remapped) msg += ' (' + remapped + ' post reference' + (remapped !== 1 ? 's' : '') + ' remapped to local IDs)';
					if (skipped)  msg += ' — ' + skipped + ' value' + (skipped !== 1 ? 's' : '') + ' skipped (no matching field on this site)';
					toast(msg, 'success', 6000);
				}
			}
			rememberPanel(resp.panel.opt_name);
			// Refresh the in-memory panel list so the dropdown updates.
			var idx = (TPMetaBuilder.panels || []).findIndex(function (p) { return p.opt_name === resp.panel.opt_name; });
			if (idx >= 0) TPMetaBuilder.panels[idx] = resp.panel;
			else TPMetaBuilder.panels.push(resp.panel);
			setTimeout(function () { setStatus('idle', ''); }, 2500);
		}).catch(function (err) {
			console.error(err);
			setStatus('error', TPMetaBuilder.i18n.saveError);
			toast('Save failed: ' + (err.message || 'unknown error'), 'error', 5000);
		});
	}

	function setStatus(s, msg) {
		state.status = s;
		state.statusMsg = msg;
		var cls = 'tpmeta-bar-status ' + (s === 'saved' ? 'is-success' : s === 'error' ? 'is-error' : '');
		root.querySelectorAll('.tpmeta-bar-status').forEach(function (el) {
			el.className = cls;
			el.textContent = msg;
		});
	}

	/**
	 * Push the PRE-change snapshot, then mark dirty. Call BEFORE mutating state.
	 */
	function recordChange() {
		pushHistory();
	}

	function markDirty() {
		state.dirty = true;
		setStatus('idle', '*');
	}

	// ---------- BAKE ----------

	/* PHP tokenizer — produces VS Code Dark+ style highlighted HTML */
	function highlightPHP(source) {
		var KW = {
			'function':1,'return':1,'if':1,'else':1,'elseif':1,'foreach':1,'for':1,
			'while':1,'do':1,'switch':1,'case':1,'break':1,'continue':1,'class':1,
			'new':1,'extends':1,'implements':1,'public':1,'private':1,'protected':1,
			'static':1,'abstract':1,'final':1,'interface':1,'trait':1,'namespace':1,
			'use':1,'require':1,'require_once':1,'include':1,'include_once':1,
			'echo':1,'print':1,'die':1,'exit':1,'array':1,'list':1,
			'true':1,'false':1,'null':1,'NULL':1,'TRUE':1,'FALSE':1,
			'this':1,'self':1,'parent':1,'const':1,'define':1,
			'isset':1,'unset':1,'empty':1,'var':1,'throw':1,'try':1,'catch':1,
			'finally':1,'match':1,'fn':1,'yield':1,'global':1,'declare':1,
			'default':1,'instanceof':1,'as':1,'readonly':1,
		};
		var toks = [], i = 0, len = source.length;

		while (i < len) {
			var c = source[i];
			// PHP tags
			if (source.substr(i, 5) === '<?php') { toks.push({t:'tag', v:'<?php'}); i+=5; }
			else if (source.substr(i, 2) === '?>') { toks.push({t:'tag', v:'?>'}); i+=2; }
			// Comments
			else if (source.substr(i, 2) === '//') {
				var e = source.indexOf('\n', i); if (e < 0) e = len;
				toks.push({t:'comment', v:source.substring(i,e)}); i = e;
			}
			else if (c === '#' && source.substr(i,2) !== '#[') {
				var e = source.indexOf('\n', i); if (e < 0) e = len;
				toks.push({t:'comment', v:source.substring(i,e)}); i = e;
			}
			else if (source.substr(i, 2) === '/*') {
				var e = source.indexOf('*/', i+2); if (e < 0) e = len-2;
				toks.push({t:'comment', v:source.substring(i, e+2)}); i = e+2;
			}
			// Strings
			else if (c === '"') {
				var j = i+1;
				while (j < len) { if (source[j]==='\\'){j+=2;continue;} if (source[j]==='"'){j++;break;} j++; }
				toks.push({t:'string', v:source.substring(i,j)}); i = j;
			}
			else if (c === "'") {
				var j = i+1;
				while (j < len) { if (source[j]==='\\'){j+=2;continue;} if (source[j]==="'"){j++;break;} j++; }
				toks.push({t:'string', v:source.substring(i,j)}); i = j;
			}
			// Heredoc / Nowdoc (simplified — treat as string to EOF-marker)
			else if (source.substr(i,3) === '<<<') {
				var nl = source.indexOf('\n', i); if (nl < 0) nl = len;
				var marker = source.substring(i+3, nl).trim().replace(/['"]/g,'');
				var end = source.indexOf('\n'+marker, nl); if (end < 0) end = len-marker.length-1;
				toks.push({t:'string', v:source.substring(i, end+marker.length+1)}); i = end+marker.length+1;
			}
			// Variables
			else if (c === '$') {
				var j = i+1;
				while (j < len && /[a-zA-Z0-9_]/.test(source[j])) j++;
				toks.push({t:'var', v:source.substring(i,j)}); i = j;
			}
			// Numbers
			else if (/[0-9]/.test(c) && (i===0 || !/[a-zA-Z_$]/.test(source[i-1]))) {
				var j = i;
				while (j < len && /[0-9._xXa-fA-F]/.test(source[j])) j++;
				toks.push({t:'num', v:source.substring(i,j)}); i = j;
			}
			// Identifiers / keywords / function calls
			else if (/[a-zA-Z_\\]/.test(c)) {
				var j = i;
				while (j < len && /[a-zA-Z0-9_\\]/.test(source[j])) j++;
				var word = source.substring(i,j);
				var type = KW[word] ? 'kw' : 'id';
				if (type === 'id') {
					var k = j;
					while (k < len && (source[k]===' '||source[k]==='\t')) k++;
					if (source[k]==='(') type = 'fn';
				}
				toks.push({t:type, v:word}); i = j;
			}
			// Arrow / scope operators
			else if (c==='-' && source[i+1]==='>') { toks.push({t:'op', v:'->'}); i+=2; }
			else if (c===':' && source[i+1]===':') { toks.push({t:'op', v:'::'}); i+=2; }
			// Punctuation
			else if ('(){}[];,'.indexOf(c) !== -1) { toks.push({t:'punct', v:c}); i++; }
			// Plain (merge consecutive)
			else {
				if (toks.length && toks[toks.length-1].t==='plain') { toks[toks.length-1].v += c; }
				else { toks.push({t:'plain', v:c}); }
				i++;
			}
		}

		var esc = function(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); };
		var cm  = { comment:'tp-c-comment', string:'tp-c-string', kw:'tp-c-kw', fn:'tp-c-fn',
		            var:'tp-c-var', num:'tp-c-num', tag:'tp-c-tag', op:'tp-c-op', punct:'tp-c-punct' };
		var html = '';
		for (var t = 0; t < toks.length; t++) {
			var tok = toks[t], cls = cm[tok.t], val = esc(tok.v);
			html += cls ? '<span class="'+cls+'">'+val+'</span>' : val;
		}
		return html;
	}

	function updateBakeDisplay(source) {
		var code = document.getElementById('tpmeta-bake-highlighted-code');
		if (code) { code.innerHTML = highlightPHP(source); }
		updateBakeLineNumbers(source);
	}

	function updateBakePath() {
		var dir     = (document.getElementById('tpmeta-bake-dir')      || {}).value || '';
		var fn      = ((document.getElementById('tpmeta-bake-filename') || {}).value || '').trim();
		var display = document.getElementById('tpmeta-bake-path-display');
		var title   = document.getElementById('tpmeta-bake-code-title');
		dir = dir.replace(/\/$/, '');
		if (display) {
			display.textContent = 'wp-content/' + (dir ? dir + '/' : '') + fn;
		}
		if (title && fn) {
			title.textContent = fn;
		}
	}

	function populateBakeDirs() {
		var sel = document.getElementById('tpmeta-bake-dir');
		if (!sel || sel.dataset.loaded) return;

		wp.apiFetch({ path: 'tpmeta/v1/builder/directories' }).then(function (dirs) {
			var lastGroup = '';
			sel.innerHTML = '<option value="">— Select directory —</option>';
			dirs.forEach(function (d) {
				if (d.group && d.group !== lastGroup) {
					var og = document.createElement('optgroup');
					og.label = d.group === 'themes' ? 'Themes' : 'Plugins';
					sel.appendChild(og);
					lastGroup = d.group;
				}
				var opt = document.createElement('option');
				opt.value       = d.value;
				opt.textContent = d.label;
				sel.appendChild(opt);
			});
			sel.dataset.loaded = '1';
			updateBakePath();
		}).catch(function () {
			sel.innerHTML = '<option value="">— Could not load directories —</option>';
		});
	}

	function openBake() {
		if (state.dirty) {
			if (!confirm('Unsaved changes will not be in the baked file. Save first?')) return;
		}
		var dlg = document.getElementById('tpmeta-builder-bake-modal');
		if (!dlg) return;

		var ta = document.getElementById('tpmeta-bake-source');
		ta.value = '';
		updateBakeDisplay('// Generating…');
		dlg.showModal();

		populateBakeDirs();

		wp.apiFetch({
			path:   'tpmeta/v1/builder/bake',
			method: 'POST',
			data:   { opt_name: state.panel.opt_name },
		}).then(function (resp) {
			ta.value = resp.source;
			ta.dataset.filename = resp.suggested_filename;
			updateBakeDisplay(resp.source);
			var fn = document.getElementById('tpmeta-bake-filename');
			if (fn && !fn.value) {
				fn.value = resp.suggested_filename || (state.panel.opt_name + '-options.php');
				updateBakePath();
			}
		}).catch(function (err) {
			var msg = '/* Error: ' + (err.message || 'bake failed') + ' */';
			ta.value = msg;
			updateBakeDisplay(msg);
		});
	}

	function updateBakeLineNumbers(source) {
		var host = document.getElementById('tpmeta-bake-line-numbers');
		if (!host) return;
		var lines = (source || '').split('\n').length;
		var html  = '';
		for (var i = 1; i <= lines; i++) { html += '<span>' + i + '</span>'; }
		host.innerHTML = html;
	}

	function wireBakeModal() {
		var dlg = document.getElementById('tpmeta-builder-bake-modal');
		if (!dlg) return;

		dlg.querySelector('.tpmeta-builder-modal-close').addEventListener('click', function () { dlg.close(); });

		document.getElementById('tpmeta-bake-dir').addEventListener('change', updateBakePath);
		document.getElementById('tpmeta-bake-filename').addEventListener('input', updateBakePath);

		// Sync line numbers with the highlighted code scroll
		var scrollDiv = document.getElementById('tpmeta-bake-code-scroll');
		var lns       = document.getElementById('tpmeta-bake-line-numbers');
		if (scrollDiv && lns) {
			scrollDiv.addEventListener('scroll', function () { lns.scrollTop = scrollDiv.scrollTop; });
		}

		document.getElementById('tpmeta-bake-copy').addEventListener('click', function () {
			var text = document.getElementById('tpmeta-bake-source').value;
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					toast('Copied to clipboard', 'success', 1500);
				}).catch(function () { toast('Copy failed', 'error', 2000); });
			} else {
				var ta = document.getElementById('tpmeta-bake-source');
				ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;width:1px;height:1px;';
				ta.removeAttribute('aria-hidden'); ta.removeAttribute('tabindex');
				document.body.appendChild(ta); ta.select(); document.execCommand('copy');
				ta.style.cssText = ''; ta.setAttribute('aria-hidden','true'); ta.setAttribute('tabindex','-1');
				document.getElementById('tpmeta-bake-code-body') && document.getElementById('tpmeta-bake-code-body').appendChild(ta);
				toast('Copied to clipboard', 'success', 1500);
			}
		});

		document.getElementById('tpmeta-bake-download').addEventListener('click', function () {
			var src = document.getElementById('tpmeta-bake-source');
			var fn  = (document.getElementById('tpmeta-bake-filename') || {}).value || src.dataset.filename || (state.panel.opt_name + '-options.php');
			var blob = new Blob([src.value], { type: 'application/x-php' });
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
			a.download = fn.trim() || (state.panel.opt_name + '-options.php');
			document.body.appendChild(a); a.click(); a.remove();
			toast('Downloaded', 'success', 1500);
		});

		document.getElementById('tpmeta-bake-write').addEventListener('click', function () {
			var dir = ((document.getElementById('tpmeta-bake-dir') || {}).value || '').trim().replace(/\/$/, '');
			var fn  = ((document.getElementById('tpmeta-bake-filename') || {}).value || '').trim();
			if (!dir) { toast('Select a directory first', 'error'); return; }
			if (!fn)  { toast('Enter a file name', 'error'); return; }
			if (!/\.php$/i.test(fn)) { toast('Filename must end in .php', 'error'); return; }

			var btn = document.getElementById('tpmeta-bake-write');
			btn.disabled = true;

			wp.apiFetch({
				path:   'tpmeta/v1/builder/bake',
				method: 'POST',
				data:   { opt_name: state.panel.opt_name, write_path: dir + '/' + fn },
			}).then(function () {
				toast('Written to: wp-content/' + dir + '/' + fn, 'success', 4000);
				btn.disabled = false;
			}).catch(function (err) {
				toast('Write failed: ' + (err.message || 'unknown error'), 'error', 5000);
				btn.disabled = false;
			});
		});
	}

	// ---------- DEMOS / STARTER TEMPLATES ----------

	/**
	 * Open the template picker modal.
	 *
	 * @param {boolean} asNew  true  = "New Panel" flow: creates a fresh copy, opens panel settings after load.
	 *                         false = "Templates" flow: reload/overwrite existing.
	 */

	// ═══════════════════════════════════════════════════════════════════════
	// KIRKI / REDUX PHP IMPORT
	// ═══════════════════════════════════════════════════════════════════════

	var KIRKI_TYPE_MAP = {
		// Text / editor / URL
		'text':'text','textarea':'textarea','editor':'editor','raw':'editor',
		'code':'code','ace_editor':'code','url':'text','u_r_l':'text',
		// Color
		'color':'colorpicker','color-alpha':'colorpicker','color_alpha':'colorpicker',
		'background':'colorpicker','color_rgba':'colorpicker','link_color':'colorpicker',
		'gradient':'color_gradient',
		// Select / sortable
		'select':'select','sortable':'select','palette':'select',
		// Toggle / switch
		'checkbox':'switch','toggle':'switch','switch':'switch',
		'checkbox_switch':'switch','checkbox_switch_input':'switch',
		// Radio / button set
		'radio':'radio_buttonset','radio_buttonset':'radio_buttonset',
		'button_set':'radio_buttonset','button_set_arrow':'radio_buttonset',
		// Radio image
		'radio-image':'radio_image','radio_image':'radio_image',
		'radio_image_select':'radio_image','image_select':'radio_image',
		// Image / media
		'image':'image','upload':'image','media':'image','cropped_image':'image',
		// Typography / spacing
		'typography':'typography',
		'dimensions':'spacing','spacing':'spacing','border':'spacing','dimension':'dimension',
		// Other mapped types
		'multicheck':'multicheck','multicolor':'multicolor','repeater':'repeater',
		'number':'text','slider':'text','spinner':'text',
		'password':'text','multi_text':'text','custom':'text',
		'date':'datepicker','post-type':'post_select',
		// Null — layout/structural, skip on import
		'section':null,'info':null,'divide':null,'group':null,'tab':null,
		'import_export':null,'callback':null,'hook':null,'custom_link':null,
	};

	/* Elusive Icons / FontAwesome → Dashicons lookup */
	var _EL_ICON_MAP = {
		'cog':'admin-settings','cogs':'admin-tools','sliders':'admin-settings','adjust':'admin-appearance',
		'arrow-up':'arrow-up-alt','arrow-down':'arrow-down-alt','arrow-left':'arrow-left-alt','arrow-right':'arrow-right-alt',
		'font':'editor-textcolor','text-width':'editor-textcolor',
		'pencil':'edit','pencil-alt':'edit','paint-brush':'art','brush':'art','magic':'admin-appearance',
		'home':'admin-home','th-large':'grid-view','th':'grid-view','list':'list-view','columns':'columns',
		'picture':'format-image','image':'format-image','photo':'format-image',
		'film':'format-video','video':'format-video','video-camera':'format-video',
		'music':'format-audio','headphones':'format-audio',
		'share':'share','globe':'admin-site','lock':'lock','unlock':'unlock',
		'user':'admin-users','group':'admin-users',
		'info-circle':'info','info-sign':'info','question-circle':'editor-help',
		'bell':'bell','star':'star-filled','heart':'heart',
		'file':'media-default','file-text':'media-text','file-alt':'media-text',
		'folder':'folder','folder-close':'folder','folder-open':'folder-open',
		'trash':'trash','check':'yes','times':'no-alt','remove':'no-alt','ban':'dismiss',
		'upload':'upload','download':'download',
		'link':'admin-links','external-link':'external',
		'calendar':'calendar','tag':'tag','tags':'tag',
		'map-marker':'location','map':'location-alt',
		'code':'editor-code','refresh':'update','repeat':'update','spinner':'update-alt',
		'search':'search','eye':'visibility','eye-slash':'hidden',
		'cloud':'cloud','rss':'rss','phone':'phone','envelope':'email',
		'comment':'admin-comments','comments':'admin-comments',
		'table':'editor-table','layout':'layout',
		'align-left':'editor-alignleft','align-center':'editor-aligncenter','align-right':'editor-alignright',
		'key':'admin-network','ok':'yes',
	};

	function mapPluginIcon(iconStr) {
		if (!iconStr) return 'dashicons-admin-generic';
		var s = String(iconStr).trim();
		if (/^dashicons-\S/.test(s)) return s;
		var dm = s.match(/dashicons-(\S+)/);
		if (dm) return 'dashicons-' + dm[1];
		var stripped = s.replace(/^(?:el|fa|fas|far|fab)\s+(?:el|fa|fas|far|fab)-/i, '').toLowerCase();
		return 'dashicons-' + (_EL_ICON_MAP[stripped] || 'admin-generic');
	}

	function phpExtractBlock(code, pos) {
		var open = code[pos], close = open === '(' ? ')' : ']';
		var depth = 1, i = pos + 1, inStr = false, strCh = '';
		while (i < code.length && depth > 0) {
			var c = code[i];
			if (inStr) { if (c === '\\') { i += 2; continue; } if (c === strCh) inStr = false; }
			else if (c === '"' || c === "'") { inStr = true; strCh = c; }
			else if (c === '(' || c === '[') depth++;
			else if (c === ')' || c === ']') depth--;
			i++;
		}
		return depth === 0 ? code.slice(pos + 1, i - 1) : null;
	}

	function phpParseArray(str) {
		var obj = {}, i = 0, len = str.length, seqIdx = 0;
		function ws() { while (i < len && /\s/.test(str[i])) i++; }
		function readString(q) {
			i++;
			var s = '';
			while (i < len) {
				var c = str[i];
				if (c === '\\') { i++; s += str[i] || ''; i++; continue; }
				if (c === q) { i++; return s; }
				s += c; i++;
			}
			return s;
		}
		function readValue() {
			ws(); if (i >= len) return '';
			var c = str[i];
			if (c === '"' || c === "'") return readString(c);
			if (c === '[') {
				var blk = phpExtractBlock(str, i);
				i += blk !== null ? blk.length + 2 : 1;
				return blk !== null ? phpParseArray(blk) : {};
			}
			if (str.slice(i, i + 5).toLowerCase() === 'array') {
				var ap = str.indexOf('(', i);
				if (ap !== -1) { var ab = phpExtractBlock(str, ap); if (ab !== null) { i = ap + ab.length + 2; return phpParseArray(ab); } }
			}
			var tm = str.slice(i).match(/^(?:esc_html__|esc_attr__|__|_x|_ex|_n)\s*\(/);
			if (tm) {
				i += tm[0].length - 1;
				var tb = phpExtractBlock(str, i);
				if (tb !== null) {
					i += tb.length + 2;
					var fq = tb.trimLeft();
					if (fq[0] === '"' || fq[0] === "'") return phpParseArray("'k'=>" + tb.slice(tb.indexOf(fq[0])))['k'] || '';
					return '';
				}
			}
			var fm = str.slice(i).match(/^[\w\\:]+\s*\(/);
			if (fm) { i += fm[0].length - 1; var fb = phpExtractBlock(str, i); i += fb !== null ? fb.length + 2 : 1; return ''; }
			var bm = str.slice(i).match(/^(true|false|TRUE|FALSE|null|NULL)\b/);
			if (bm) { i += bm[0].length; return bm[0].toLowerCase() === 'true'; }
			var nm = str.slice(i).match(/^-?\d+(\.\d+)?/);
			if (nm) { i += nm[0].length; return Number(nm[0]); }
			var id = str.slice(i).match(/^[\w]+/);
			if (id) { i += id[0].length; return id[0]; }
			i++; return '';
		}
		while (i < len) {
			ws(); if (i >= len) break;
			if (str[i] === ',') { i++; continue; }
			if (str[i] === ')' || str[i] === ']') break;
			// ── Speculative key read: save position, try to read key then look for '=>'.
			// If '=>' is absent the token was a VALUE (e.g. an unkeyed array item like
			// array(...)), so we backtrack and call readValue() directly.
			var savedI = i, key = null;
			if (str[i] === '"' || str[i] === "'") {
				key = readString(str[i]); ws();
				if (str.slice(i, i + 2) !== '=>') { i = savedI; key = null; }
			} else if (str[i] !== '[') {
				var km = str.slice(i).match(/^[\w]+/);
				if (km) {
					key = km[0]; i += km[0].length; ws();
					if (str.slice(i, i + 2) !== '=>') { i = savedI; key = null; }
				}
			}
			if (key !== null) {
				i += 2; obj[key] = readValue();
			} else {
				var prevI = i;
				var sv = readValue();
				if (i > prevI) obj[seqIdx++] = sv;
			}
			ws();
		}
		return obj;
	}

	// Parses a PHP *expression* (e.g. the raw argument string "array('k'=>'v',...)").
	// Unlike phpParseArray — which expects the content BETWEEN the delimiters — this
	// function first strips a single top-level array()/[] wrapper when the entire
	// string is just that one call.  readValue() already handles inner arrays by
	// extracting their block content before calling phpParseArray, so this wrapper
	// is only needed at outer call-sites where we receive a raw argument string.
	function phpParseExpr(str) {
		var s = str.trim();
		if (/^array\s*\(/i.test(s)) {
			var _ap = s.search(/\(/);
			var _ab = phpExtractBlock(s, _ap);
			if (_ab !== null) {
				var _rest = s.slice(_ap + _ab.length + 2).trim().replace(/^;+/, '').trim();
				if (_rest === '') return phpParseArray(_ab);
			}
		} else if (s.length > 0 && s[0] === '[') {
			var _ab2 = phpExtractBlock(s, 0);
			if (_ab2 !== null) {
				var _rest2 = s.slice(_ab2.length + 2).trim().replace(/^;+/, '').trim();
				if (_rest2 === '') return phpParseArray(_ab2);
			}
		}
		return phpParseArray(s);
	}

	function phpFindCalls(code, patterns) {
		var results = [];
		patterns.forEach(function (pat) {
			var re = new RegExp(pat, 'g'), m;
			while ((m = re.exec(code)) !== null) {
				var op = code.indexOf('(', m.index + m[0].length - 1);
				if (op === -1) continue;
				var blk = phpExtractBlock(code, op);
				if (blk !== null) results.push({ raw: blk, pos: op });
			}
		});
		return results;
	}

	function mapKirkiField(raw, defaultSection, keyMap) {
		var id      = String(raw[keyMap.id] || raw.id || raw.name || '').replace(/['"]/g, '').trim().replace(/-/g, '_');
		var rawType = String(raw[keyMap.type] || raw.type || 'text').toLowerCase().replace(/-/g, '_');
		var label   = String(raw[keyMap.label] || raw.label || raw.title || id).replace(/_/g, ' ');
		var desc    = String(raw[keyMap.desc]  || raw.description || raw.subtitle || raw.desc || '');
		var dflt    = raw['default'] !== undefined ? raw['default'] : '';
		if (typeof dflt === 'object') dflt = '';
		dflt = String(dflt);
		var section = String(raw.section || raw.panel || defaultSection || '').replace(/['"]/g, '').trim();
		var bType   = KIRKI_TYPE_MAP.hasOwnProperty(rawType) ? KIRKI_TYPE_MAP[rawType] : 'text';
		if (bType === null || !id) return null;
		var field   = { id: id, type: bType, label: label, description: desc, default: dflt, _section: section };

		// choices / options
		var choices = raw.choices || raw.options || null;
		if (choices && typeof choices === 'object') {
			if (Array.isArray(choices)) {
				field.options = choices.map(function (c) {
					if (typeof c === 'object') return { value: String(c.value||c.id||''), label: String(c.label||c.name||'') };
					return { value: String(c), label: String(c) };
				});
			} else {
				field.options = Object.keys(choices).map(function (k) {
					var v = choices[k];
					// Redux image_select sub-object: { alt: 'Label', img: '...' }
					if (v && typeof v === 'object') {
						return { value: String(k), label: String(v.alt || v.label || v.name || v.title || k) };
					}
					return { value: String(k), label: String(v) };
				});
			}
		}

		// active_callback / required → builder conditional
		// Kirki: [ [ 'setting' => 'field_id', 'operator' => '==', 'value' => false ] ]
		// Redux: [ [ 'id'      => 'field_id', 'operator' => '==', 'value' => '1'   ] ]
		var cb = raw.active_callback || raw.required || null;
		if (cb && typeof cb === 'object') {
			// Our PHP parser returns numeric-keyed objects {0:{...}} instead of real
			// JS Arrays when it encounters [[...]] PHP sequences — handle both forms.
			var cond = Array.isArray(cb) ? cb[0] : (cb['0'] !== undefined ? cb['0'] : cb);
			if (cond && typeof cond === 'object') {
				var condField = String(cond.setting || cond.id || cond.field || '').replace(/['"]/g, '').trim();
				var condOp    = String(cond.operator || '==').trim();
				var allowed = ['==','!=','>','<','>=','<='];
				if (allowed.indexOf(condOp) === -1) condOp = '==';
				var condVal = cond.value;
				if      (condVal === false) condVal = 'off';
				else if (condVal === true)  condVal = 'on';
				else condVal = String(condVal !== undefined && condVal !== null ? condVal : '');
				if (condField) {
					field.conditional = { field: condField, operator: condOp, value: condVal };
				}
			}
		}

		return field;
	}

	function parseKirkiCode(phpCode) {
		var code = phpCode.replace(/\/\/[^\n]*/g,' ').replace(/\/\*[\s\S]*?\*\//g,' ').replace(/#[^\n]*/g,' ');
		var sections = {}, fields = [], panelInfo = { title: '', optName: '' };

		// ── Resolve top-level PHP variable assignments: $opt_name = 'my_theme'
		var varDefs = {};
		var varRe = /\$(\w+)\s*=\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")/g, vM;
		while ((vM = varRe.exec(code)) !== null) {
			varDefs[vM[1]] = vM[2] !== undefined ? vM[2] : vM[3];
		}
		function resolveArg(raw) {
			var s = (raw || '').replace(/\s/g, '');
			if (s[0] === '$') return varDefs[s.slice(1)] || s.slice(1).replace(/[^a-zA-Z0-9_]/g, '');
			return s.replace(/['"]/g, '');
		}

		// Panel (Kirki::add_panel / new Kirki\Panel)
		phpFindCalls(code, ['Kirki\\s*::\\s*add_panel\\s*\\(','new\\s+(?:\\\\)?Kirki\\\\Panel\\s*\\(']).forEach(function(c){
			var p = c.raw.split(',');
			panelInfo.optName = resolveArg(p[0]);
			var pa = phpParseExpr(p.slice(1).join(','));
			panelInfo.title = String(pa.title||pa.label||panelInfo.optName);
		});

		// Redux::set_args → opt_name + menu_title
		// Second arg may be a $variable — always do regex fallback on the full code.
		phpFindCalls(code, ['Redux\\s*::\\s*set_args\\s*\\(']).forEach(function(c) {
			var fc = c.raw.indexOf(','); if (fc === -1) return;
			var optN = resolveArg(c.raw.slice(0, fc));
			if (!panelInfo.optName) panelInfo.optName = optN;
			// Try inline array first, then regex-scan the whole code
			var d = phpParseExpr(c.raw.slice(fc + 1));
			var title = String(d.menu_title || d.page_title || d.display_name || '');
			if (!title) {
				var mtM = code.match(/'menu_title'\s*=>\s*(?:(?:esc_html__|esc_attr__|__)\s*\(\s*)?(?:'([^'\\]+)'|"([^"\\]+)")/);
				if (mtM) title = mtM[1] || mtM[2] || '';
			}
			if (!panelInfo.title) panelInfo.title = title;
		});

		// Kirki sections
		phpFindCalls(code,['Kirki\\s*::\\s*add_section\\s*\\(','new\\s+(?:\\\\)?Kirki\\\\Section\\s*\\(']).forEach(function(c){
			var p = c.raw.split(',');
			var sid = resolveArg(p[0]); if (!sid) return;
			var d = phpParseExpr(p.slice(1).join(','));
			sections[sid] = { id:sid, title:String(d.title||sid).replace(/_/g,' '), description:String(d.description||d.subtitle||''), icon:mapPluginIcon(d.icon||''), fields:[] };
		});

		// Redux::set_section
		phpFindCalls(code,['Redux\\s*::\\s*set_section\\s*\\(']).forEach(function(c){
			var fc = c.raw.indexOf(','); if (fc === -1) return;
			var optN = resolveArg(c.raw.slice(0, fc));
			if (!panelInfo.optName) panelInfo.optName = optN;
			var d = phpParseExpr(c.raw.slice(fc + 1));
			var rawId = String(d.id || d.slug || '');
			var sid = resolveArg(rawId).replace(/[^a-zA-Z0-9_]/g, '') || ('s_' + Object.keys(sections).length);
			sections[sid] = { id:sid, title:String(d.title||sid).replace(/_/g,' '), description:String(d.description||d.desc||''), icon:mapPluginIcon(d.icon||''), fields:[] };
			var ff = d.fields; if (!ff) return;
			var farr = Array.isArray(ff) ? ff : Object.values(ff);
			farr.forEach(function(f){ if (f && typeof f === 'object') { var m = mapKirkiField(f, sid, {id:'id',type:'type',label:'title',desc:'desc'}); if (m) fields.push(m); } });
		});

		// Redux::set_field($opt_name, 'section_id', array(...))
		phpFindCalls(code, ['Redux\\s*::\\s*set_field\\s*\\(']).forEach(function(c) {
			var parts = c.raw.split(','); if (parts.length < 3) return;
			var secId = resolveArg(parts[1]);
			var d = phpParseExpr(parts.slice(2).join(','));
			var m = mapKirkiField(d, secId, {id:'id',type:'type',label:'title',desc:'desc'});
			if (m) fields.push(m);
		});

		// Kirki::add_field
		phpFindCalls(code,['Kirki\\s*::\\s*add_field\\s*\\(']).forEach(function(c){
			var fc = c.raw.indexOf(','); if (fc === -1) return;
			var d = phpParseExpr(c.raw.slice(fc + 1));
			var m = mapKirkiField(d, d.section||'', {id:'settings',type:'type',label:'label',desc:'description'});
			if (m) fields.push(m);
		});

		// Kirki OOP: new Kirki\Field\Type(...)
		var oopRe = /new\s+(?:\\)?Kirki\\Field\\(\w+)\s*\(/g, om;
		while ((om = oopRe.exec(code)) !== null) {
			var kt = om[1]
				.replace(/([A-Z])/g, function(_, ch, idx){ return idx ? '_' + ch.toLowerCase() : ch.toLowerCase(); })
				.replace(/__+/g, '_').replace(/^_|_$/g, '');
			var op2 = code.indexOf('(', om.index + om[0].length - 1); if (op2 === -1) continue;
			var b2 = phpExtractBlock(code, op2); if (!b2) continue;
			var d2 = phpParseExpr(b2); d2._kirkiOopType = kt;
			var m2 = mapKirkiField(d2, d2.section||'', {id:'settings',type:'_kirkiOopType',label:'label',desc:'description'});
			if (m2) fields.push(m2);
		}

		// Ensure every field's section exists
		fields.forEach(function(f){
			if (f._section && !sections[f._section]) sections[f._section] = { id:f._section, title:f._section.replace(/_/g,' '), description:'', icon:'dashicons-admin-generic', fields:[] };
		});
		if (!Object.keys(sections).length) {
			sections['general'] = { id:'general', title:'General', description:'', icon:'dashicons-admin-generic', fields:[] };
			fields.forEach(function(f){ f._section = 'general'; });
		}
		fields.forEach(function(f){ var sec = sections[f._section] || sections[Object.keys(sections)[0]]; if (sec) sec.fields.push(f); delete f._section; });

		return { panelInfo: panelInfo, sections: sections };
	}

	var _kirkiParsed = null;

	function openKirkiModal(defaultSource) {
		var dlg = document.getElementById('tpmeta-kirki-modal');
		if (!dlg) return;
		_kirkiParsed = null;
		showKirkiStep(1);
		var codeEl = document.getElementById('tpmeta-kirki-code');
		var importBtn = document.getElementById('tpmeta-kirki-import');
		if (codeEl) codeEl.value = '';
		if (importBtn) importBtn.disabled = true;
		if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open','');
		// Pre-select source tab when opened from a specific button
		if (defaultSource) {
			var srcHints = {
				kirki: 'Paste Kirki OOP (new \\Kirki\\Field\\...) or Kirki static API code',
				redux: 'Paste Redux Framework PHP — Redux::set_section(), Redux::set_args()'
			};
			dlg.querySelectorAll('input[name="tpmeta_import_source"]').forEach(function (r) {
				r.checked = (r.value === defaultSource);
			});
			dlg.querySelectorAll('.tpmeta-kirki-source-tab').forEach(function (tab) {
				var r = tab.querySelector('input');
				tab.classList.toggle('is-active', r && r.value === defaultSource);
			});
			var hintEl = document.getElementById('tpmeta-kirki-source-hint');
			if (hintEl) hintEl.textContent = srcHints[defaultSource] || '';
		}
		dlg.querySelectorAll('.tpmeta-builder-modal-close, #tpmeta-kirki-cancel').forEach(function(btn){
			btn.onclick = function(){ dlg.close ? dlg.close() : dlg.removeAttribute('open'); };
		});
		var clearBtn = document.getElementById('tpmeta-kirki-clear');
		if (clearBtn) clearBtn.onclick = function(){ if(codeEl) codeEl.value=''; };
		var backBtn = document.getElementById('tpmeta-kirki-back');
		if (backBtn) backBtn.onclick = function(){ showKirkiStep(1); if(importBtn) importBtn.disabled=true; };

		// ── Source tabs: Kirki / Redux ──────────────────────────────────────
		var KIRKI_HINTS = {
			kirki: 'Paste Kirki OOP (new \\Kirki\\Field\\...) or Kirki static API code',
			redux: 'Paste Redux Framework code — Redux::set_section(), Redux::set_args()'
		};
		var KIRKI_PLACEHOLDERS = {
			kirki: "new \\Kirki\\Panel('my_panel', ['title' => 'My Options']);\n\nnew \\Kirki\\Section('my_section', [\n    'title' => 'My Section',\n    'panel' => 'my_panel',\n]);\n\nnew \\Kirki\\Field\\Color([\n    'settings' => 'my_color',\n    'label'    => 'My Color',\n    'section'  => 'my_section',\n    'default'  => '#000000',\n]);",
			redux: "$opt_name = 'my_theme';\n\nRedux::set_args( $opt_name, array(\n    'menu_title' => 'Theme Options',\n) );\n\nRedux::set_section( $opt_name, array(\n    'title'  => 'General',\n    'id'     => 'general',\n    'fields' => array(\n        array(\n            'id'    => 'my_color',\n            'type'  => 'color',\n            'title' => 'My Color',\n        ),\n    )\n) );"
		};
		var hintEl = document.getElementById('tpmeta-kirki-source-hint');
		dlg.querySelectorAll('input[name="tpmeta_import_source"]').forEach(function(radio) {
			radio.addEventListener('change', function() {
				var src = this.value;
				dlg.querySelectorAll('.tpmeta-kirki-source-tab').forEach(function(tab) {
					tab.classList.toggle('is-active', tab.querySelector('input').value === src);
				});
				if (hintEl) hintEl.textContent = KIRKI_HINTS[src] || '';
				if (codeEl && !codeEl.value.trim()) codeEl.placeholder = KIRKI_PLACEHOLDERS[src] || '';
			});
		});

		var parseBtn = document.getElementById('tpmeta-kirki-parse');
		if (parseBtn) parseBtn.onclick = function(){
			var code = codeEl ? codeEl.value.trim() : '';
			if (!code) { alert('Please paste some PHP code first.'); return; }
			try {
				var result = parseKirkiCode(code);
				_kirkiParsed = result;
				renderKirkiPreview(result);
				showKirkiStep(2);
				if(importBtn) importBtn.disabled = false;
			} catch(e) {
				console.error('Kirki parse error:', e);
				alert('Could not parse the PHP code. Check the format and try again.\n\n' + e.message);
			}
		};
		if (importBtn) importBtn.onclick = doKirkiImport;
	}

	function showKirkiStep(n){
		var s1 = document.getElementById('tpmeta-kirki-step-1');
		var s2 = document.getElementById('tpmeta-kirki-step-2');
		if(s1) s1.hidden = (n!==1);
		if(s2) s2.hidden = (n!==2);
	}

	function renderKirkiPreview(result){
		var secs = result.sections, sk = Object.keys(secs);
		var total = sk.reduce(function(a,k){return a+secs[k].fields.length;},0);
		var sumEl = document.getElementById('tpmeta-kirki-summary');
		if(sumEl) sumEl.innerHTML='<div class="tpmeta-kirki-chips">'
			+'<span class="tpmeta-kirki-chip"><span class="dashicons dashicons-layout"></span>'+sk.length+' section'+(sk.length!==1?'s':'')+'</span>'
			+'<span class="tpmeta-kirki-chip"><span class="dashicons dashicons-list-view"></span>'+total+' field'+(total!==1?'s':'')+'</span>'
			+'</div>';
		var titleEl = document.getElementById('tpmeta-kirki-panel-title');
		var optEl   = document.getElementById('tpmeta-kirki-opt-name');
		if(titleEl && result.panelInfo.title) titleEl.value = result.panelInfo.title;
		if(optEl && result.panelInfo.optName) optEl.value   = result.panelInfo.optName;
		var treeEl = document.getElementById('tpmeta-kirki-tree');
		if(!treeEl) return;
		var html='';
		sk.forEach(function(sid){
			var s=secs[sid];
			html+='<div class="tpmeta-kirki-section">'
				+'<div class="tpmeta-kirki-sec-head"><span class="dashicons '+(s.icon||'dashicons-admin-generic')+'"></span>'
				+'<strong>'+esc(s.title)+'</strong><code class="tpmeta-kirki-sec-id">'+esc(s.id)+'</code>'
				+'<span class="tpmeta-kirki-badge">'+s.fields.length+' field'+(s.fields.length!==1?'s':'')+'</span></div>';
			if(s.fields.length){
				html+='<ul class="tpmeta-kirki-fields">';
				s.fields.forEach(function(f){
					html+='<li class="tpmeta-kirki-field">'
						+'<span class="tpmeta-kirki-ftype">'+esc(f.type)+'</span>'
						+'<span class="tpmeta-kirki-fname">'+esc(f.label||f.id)+'</span>'
						+'<code class="tpmeta-kirki-fid">'+esc(f.id)+'</code>'
						+'</li>';
				});
				html+='</ul>';
			} else {
				html+='<p class="tpmeta-kirki-empty">No fields detected in this section.</p>';
			}
			html+='</div>';
		});
		treeEl.innerHTML = html||'<p class="tpmeta-kirki-empty">Nothing detected. Check the PHP code format.</p>';
	}

	function doKirkiImport(){
		if(!_kirkiParsed) return;
		var titleEl = document.getElementById('tpmeta-kirki-panel-title');
		var optEl   = document.getElementById('tpmeta-kirki-opt-name');
		var title   = (titleEl?titleEl.value.trim():'')||'Imported Panel';
		var optName = (optEl?optEl.value.trim():'');
		optName = optName.toLowerCase().replace(/[^a-z0-9_]/g,'_')||'imported_panel';
		optName = uniquePanelOptName(optName);
		var secs = _kirkiParsed.sections, sk = Object.keys(secs);
		var builtSections = sk.map(function(sid){
			var sec=secs[sid], rows=[];
			sec.fields.forEach(function(f){
				var fCopy=JSON.parse(JSON.stringify(f)); delete fCopy._section;
				rows.push({id:'row_'+Math.random().toString(36).slice(2,8),layout:'grid',columns:1,fields:[fCopy]});
			});
			return {id:sec.id,title:sec.title,description:sec.description||'',icon:sec.icon||'dashicons-admin-generic',rows:rows};
		});
		var newPanel={opt_name:optName,menu_slug:optName.replace(/_/g,'-'),menu_title:title,page_title:title,menu_icon:'dashicons-admin-generic',menu_position:60,capability:'manage_options',output_css:true,sections:builtSections};
		var dlg=document.getElementById('tpmeta-kirki-modal');
		if(dlg){dlg.close?dlg.close():dlg.removeAttribute('open');}
		recordChange();
		state.panel=newPanel; state.activeSectionId=builtSections.length?builtSections[0].id:''; state.dirty=true; state.screen='builder';
		rememberScreen('builder');
		render();
		var fieldCount=builtSections.reduce(function(a,s){return a+s.rows.length;},0);
		toast('Imported '+sk.length+' section(s) and '+fieldCount+' field(s). Save to persist.','success',5000);
		editPanelArgs();
	}

	function openDemosModal(asNew) {
		var dlg  = document.getElementById('tpmeta-demos-modal');
		var grid = document.getElementById('tpmeta-demos-grid');
		if (!dlg || !grid) return;

		// Mode-specific title
		var titleEl = dlg.querySelector('h2');
		if (titleEl) titleEl.textContent = asNew ? 'Start with a Template' : 'Starter Templates';

		// Mode-specific footer button
		var footerBtn = document.getElementById('tpmeta-demos-close');
		if (footerBtn) {
			footerBtn.textContent = asNew ? 'Skip — Start Blank' : 'Close';
			footerBtn.onclick = asNew
				? function () { dlg.close(); doCreateBlankPanel(); }
				: function () { dlg.close(); };
		}

		grid.innerHTML = '<div class="tpmeta-demos-loading"><span class="spinner is-active"></span> Loading templates…</div>';
		dlg.showModal();

		wp.apiFetch({ path: 'tpmeta/v1/builder/demos' }).then(function (demos) {

			// Blank card — only in New Panel mode
			var blankHtml = asNew
				? '<div class="tpmeta-demo-card tpmeta-demo-card--blank">'
				+   '<div class="tpmeta-demo-card-visual">'
				+     '<span class="dashicons dashicons-plus-alt tpmeta-demo-card-icon"></span>'
				+   '</div>'
				+   '<div class="tpmeta-demo-card-body">'
				+     '<h3 class="tpmeta-demo-card-title">Blank Panel</h3>'
				+     '<p class="tpmeta-demo-card-desc">Start from scratch with no sections or fields.</p>'
				+     '<p class="tpmeta-demo-card-meta"><span class="dashicons dashicons-category"></span><strong>0</strong> sections</p>'
				+   '</div>'
				+   '<div class="tpmeta-demo-card-footer">'
				+     '<button class="button button-primary" id="tpmeta-demo-blank-btn">Start Blank</button>'
				+   '</div>'
				+ '</div>'
				: '';

			// Two import cards — sit inline with template cards, no divider.
			var kirkiHtml =
				// JSON import card
				'<div class="tpmeta-demo-card tpmeta-demo-card--import">'
				+   '<div class="tpmeta-demo-card-visual">'
				+     '<span class="dashicons dashicons-media-code tpmeta-demo-card-icon"></span>'
				+   '</div>'
				+   '<div class="tpmeta-demo-card-body">'
				+     '<h3 class="tpmeta-demo-card-title">JSON Panel File</h3>'
				+     '<p class="tpmeta-demo-card-desc">Drop or paste a previously exported <code>.json</code> panel file to restore it instantly.</p>'
				+   '</div>'
				+   '<div class="tpmeta-demo-card-footer">'
				+     '<button class="button button-primary" id="tpmeta-demo-import-btn">Import JSON</button>'
				+   '</div>'
				+ '</div>'

				// Kirki / Redux PHP import card
				+ '<div class="tpmeta-demo-card tpmeta-demo-card--kirki">'
				+   '<div class="tpmeta-demo-card-visual">'
				+     '<span class="dashicons dashicons-editor-code tpmeta-demo-card-icon"></span>'
				+   '</div>'
				+   '<div class="tpmeta-demo-card-body">'
				+     '<h3 class="tpmeta-demo-card-title">Kirki / Redux PHP</h3>'
				+     '<p class="tpmeta-demo-card-desc">Paste existing Kirki or Redux PHP code and convert it to builder panels automatically.</p>'
				+   '</div>'
				+   '<div class="tpmeta-demo-card-footer">'
				+     '<button class="button button-primary" id="tpmeta-demo-kirki-btn">Import Code</button>'
				+   '</div>'
				+ '</div>';

			if (!demos || !demos.length) {
				grid.innerHTML = (blankHtml || '<p class="tpmeta-demos-empty">No starter templates found.</p>') + kirkiHtml;
			} else {
				grid.innerHTML = blankHtml + demos.map(function (d) {
					var alreadyLoaded = !asNew && (TPMetaBuilder.panels || []).some(function (p) { return p.opt_name === d.opt_name; });
					var badge    = alreadyLoaded ? '<span class="tpmeta-demo-badge tpmeta-demo-badge--exists">Loaded</span>' : '';
					var btnLabel = asNew ? 'Use Template' : (alreadyLoaded ? 'Reload' : 'Load Template');
					return '<div class="tpmeta-demo-card">'
						+ '<div class="tpmeta-demo-card-visual">'
						+   '<span class="dashicons dashicons-admin-appearance tpmeta-demo-card-icon"></span>'
						+ '</div>'
						+ '<div class="tpmeta-demo-card-body">'
						+   '<h3 class="tpmeta-demo-card-title">' + esc(d.menu_title) + badge + '</h3>'
						+   (d.description ? '<p class="tpmeta-demo-card-desc">' + esc(d.description) + '</p>' : '')
						+   '<p class="tpmeta-demo-card-meta"><span class="dashicons dashicons-category"></span><strong>' + d.sections + '</strong> sections</p>'
						+ '</div>'
						+ '<div class="tpmeta-demo-card-footer">'
						+   '<button class="button button-primary tpmeta-demo-load-btn"'
						+     ' data-file="' + esc(d.file) + '" data-opt="' + esc(d.opt_name) + '">'
						+     btnLabel
						+   '</button>'
						+ '</div>'
						+ '</div>';
				}).join('') + kirkiHtml;
			}

			// Wire blank card button
			var blankBtn = document.getElementById('tpmeta-demo-blank-btn');
			if (blankBtn) {
				blankBtn.addEventListener('click', function () { dlg.close(); doCreateBlankPanel(); });
			}

			// Wire JSON import card button — full-panel restore mode (menu title,
			// slug, customizer settings, conditions, every field).
			var importBtn2 = document.getElementById('tpmeta-demo-import-btn');
			if (importBtn2) {
				importBtn2.addEventListener('click', function () { dlg.close(); openImportModal('full'); });
			}

			// Wire Kirki import card button
			var kirkiBtn = document.getElementById('tpmeta-demo-kirki-btn');
			if (kirkiBtn) {
				kirkiBtn.addEventListener('click', function () { dlg.close(); openKirkiModal(); });
			}

			// Wire template load buttons
			grid.querySelectorAll('.tpmeta-demo-load-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var file     = btn.dataset.file;
					var opt      = btn.dataset.opt;
					var existing = !asNew && (TPMetaBuilder.panels || []).some(function (p) { return p.opt_name === opt; });

					if (existing && !confirm('A panel "' + opt + '" already exists. Overwrite it?')) return;

					btn.disabled    = true;
					btn.textContent = 'Loading…';

					wp.apiFetch({
						path:   'tpmeta/v1/builder/demos',
						method: 'POST',
						data:   { file: file, as_new: !!asNew },
					}).then(function (resp) {
						var panels = TPMetaBuilder.panels || [];
						var replaced = false;
						for (var i = 0; i < panels.length; i++) {
							if (panels[i].opt_name === resp.opt_name) { panels[i] = resp.panel; replaced = true; break; }
						}
						if (!replaced) panels.push(resp.panel);
						TPMetaBuilder.panels = panels;

						state.panel = JSON.parse(JSON.stringify(resp.panel));
						state.panel.sections = state.panel.sections.map(migrateSection);
						state.activeSectionId = state.panel.sections.length ? state.panel.sections[0].id : '';
						state.dirty = false;
						rememberPanel(state.panel.opt_name);

						dlg.close();
						render();
						if (asNew) { editPanelArgs(); }
						toast(
							(asNew ? 'Panel created from template' : 'Template loaded') + ': ' + esc(resp.panel.menu_title || resp.opt_name),
							'success', 3000
						);
					}).catch(function (err) {
						btn.disabled    = false;
						btn.textContent = existing ? 'Reload' : (asNew ? 'Use Template' : 'Load Template');
						toast('Failed: ' + (err.message || 'unknown error'), 'error', 4000);
					});
				});
			});

		}).catch(function () {
			grid.innerHTML = '<p class="tpmeta-demos-empty">Could not load templates.</p>';
		});
	}

	function wireDemosModal() {
		var dlg = document.getElementById('tpmeta-demos-modal');
		if (!dlg) return;
		dlg.querySelector('.tpmeta-builder-modal-close').addEventListener('click', function () { dlg.close(); });
		// Footer button onclick is set dynamically in openDemosModal()
	}

	function wireGlobalListeners() {
		// Registered ONCE at boot — never inside render/wireCanvas to avoid accumulation.
		document.addEventListener('click', function (e) {
			// Close row chooser when clicking outside the chooser or its trigger buttons
			if (!e.target.closest('.tpmeta-row-chooser, .tpmeta-add-row-mini, .tpmeta-add-row-empty-btn')) {
				closeAllChoosers();
			}
			// Close slot pickers when clicking outside a placeholder
			if (!e.target.closest('.tpmeta-field-placeholder')) {
				closeAllSlotPickers();
			}
		}, true); // capture phase so it fires before any widget handlers
	}

	// ---------- HELPERS ----------

	function fieldTypeMeta(type) {
		return (TPMetaBuilder.fieldTypes || []).find(function (t) { return t.type === type; })
			|| { type: type, label: type, icon: 'editor-textcolor' };
	}

	function uniqueSectionId(base) {
		var existing = state.panel.sections.map(function (s) { return s.id; });
		var id = base, i = 1;
		while (existing.indexOf(id) !== -1) { id = base + '_' + i++; }
		return id;
	}

	// The prefix new auto-generated field/section IDs should carry. An
	// explicitly-configured field_prefix wins (including a deliberate empty
	// string); otherwise fall back to the legacy opt_name-based scheme.
	function fieldIdPrefix() {
		if (state.panel && typeof state.panel.field_prefix === 'string') {
			return state.panel.field_prefix;
		}
		return (state.panel && state.panel.opt_name) ? state.panel.opt_name + '_' : '';
	}

	function uniqueFieldId(base) {
		var all = [];
		state.panel.sections.forEach(function (s) {
			(s.rows || []).forEach(function (r) {
				(r.fields || []).forEach(function (f) { all.push(f.id); });
			});
		});
		var id = base, i = 1;
		while (all.indexOf(id) !== -1) { id = base + '_' + i++; }
		return id;
	}

	function uniqueRowId() {
		return 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
	}

	function uniquePanelOptName(base) {
		var existing = (TPMetaBuilder.panels || []).map(function (p) { return p.opt_name; });
		var id = base, i = 1;
		while (existing.indexOf(id) !== -1) { id = base + '_' + i++; }
		return id;
	}

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}
})();
