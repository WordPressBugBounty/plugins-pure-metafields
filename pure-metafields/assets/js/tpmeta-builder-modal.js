/**
 * TPMeta Options Builder — field editor modal.
 * Uses native <dialog>. Exports a single global: TPMetaBuilderModal.
 */
(function () {
	'use strict';

	var dlg, titleEl, bodyEl, saveBtn, cancelBtn, closeBtn;
	var currentField         = null;
	var onApplyCb            = null;
	var sectionFieldsForCond = [];

	var CSS_TARGET_TYPES = [];
	var CSS_OUTPUT_TYPES = ['colorpicker', 'typography', 'spacing', 'dimension', 'color_gradient', 'multicolor'];

	// Single active handler — swapped per modal mode so only one ever fires.
	var activeHandler = null;

	function setHandler(fn) {
		if (activeHandler) saveBtn.removeEventListener('click', activeHandler);
		activeHandler = fn;
		saveBtn.addEventListener('click', fn);
	}

	function init() {
		dlg       = document.getElementById('tpmeta-builder-modal');
		if (!dlg) return;
		titleEl   = dlg.querySelector('.tpmeta-builder-modal-title');
		bodyEl    = dlg.querySelector('.tpmeta-builder-modal-body');
		saveBtn   = dlg.querySelector('.tpmeta-builder-modal-save');
		cancelBtn = dlg.querySelector('.tpmeta-builder-modal-cancel');
		closeBtn  = dlg.querySelector('.tpmeta-builder-modal-close');

		cancelBtn.addEventListener('click', close);
		closeBtn.addEventListener('click', close);
		// No handler on saveBtn yet — each open* call sets one.
	}

	function open(field, otherFields, cb) {
		if (!dlg) init();
		currentField = JSON.parse(JSON.stringify(field || {}));
		sectionFieldsForCond = (otherFields || []).filter(function (f) {
			return f && f.id && f.id !== currentField.id;
		});
		onApplyCb = cb;

		titleEl.textContent = (TPMetaBuilder.i18n.editField) + ' — ' + (currentField.type || '');
		setHandler(doApplyField);
		render();
		showDialog();
	}

	function close() {
		if (typeof dlg.close === 'function') dlg.close();
		else dlg.removeAttribute('open');
	}

	function showDialog() {
		if (typeof dlg.showModal === 'function') dlg.showModal();
		else dlg.setAttribute('open', '');
	}

	function doApplyField() {
		readForm();
		close();
		if (onApplyCb) onApplyCb(currentField);
	}

	// ---------- rendering ----------

	function render() {
		var f = currentField;
		var hasOptions = ['select', 'checkbox', 'tabs', 'radio_buttonset', 'multicheck', 'radio_image', 'multicolor'].indexOf(f.type) !== -1;

		// Label and description per type
		var optionsLegend = f.type === 'multicolor' ? 'Color Slots'
			: ( f.type === 'radio_buttonset' || f.type === 'tabs' ) ? 'Choices'
			: ( f.type === 'radio_image' ? 'Image Choices' : 'Options' );

		var optionsHint = {
			radio_buttonset: '<div class="description tpmeta-opts-hint">'
				+ '<strong>Value</strong> = the stored key returned by <code>tpmeta_get_option()</code> &nbsp;|&nbsp; '
				+ '<strong>Label</strong> = the button text shown to the user.<br>'
				+ 'Example: value&nbsp;<code>red</code> → label&nbsp;<code>Red</code></div>',
			tabs: '<div class="description tpmeta-opts-hint">'
				+ 'Each row is one button in the segment group. <strong>Value</strong> is stored, <strong>Label</strong> is displayed.</div>',
			select: '<div class="description tpmeta-opts-hint">'
				+ 'Each row is a <code>&lt;option&gt;</code>. <strong>Value</strong> is stored, <strong>Label</strong> is displayed.</div>',
			checkbox: '<div class="description tpmeta-opts-hint">'
				+ 'Each row is one checkbox item. <strong>Value</strong> is stored in the saved array when checked.</div>',
			multicheck: '<div class="description tpmeta-opts-hint">'
				+ 'Each row is one checkbox item. <strong>Value</strong> is stored, <strong>Label</strong> is the visible text.</div>',
			radio_image: '<div class="description tpmeta-opts-hint">'
				+ '<strong>Value</strong> = the stored key (e.g. <code>layout-1</code>) &nbsp;|&nbsp; '
				+ '<strong>Image</strong> = full URL to the image file in your theme or plugin.<br>'
				+ 'Use the <strong>Browse</strong> button to pick from a directory, or paste a URL directly.</div>',
			multicolor: '<div class="description tpmeta-opts-hint">'
				+ '<strong>Key</strong> (Value column) = PHP identifier for this swatch, e.g. <code>primary</code>, <code>hover</code>.<br>'
				+ '<strong>Label</strong> = the CSS color value for that swatch, e.g. <code>red</code>, <code>#3362FF</code>, <code>rgba(0,136,204,1)</code>.<br>'
				+ 'The label IS the background color of the swatch — it is also stored as the saved value when that swatch is selected.</div>',
		}[ f.type ] || '';

		var html = '';

		// Basic — Label first so we can auto-derive the ID from it as the user types.
		html += row('Label', input('label', f.label || ''));
		html += row('ID (theme_mod key)', input('id', f.id || '', { mono: true }), 'Used as the get_theme_mod() key. Lowercase, underscores. Auto-filled from the label until you edit it.');
		html += row('Description', textarea('description', f.description || '', 2));

		// Type-specific basics
		if (['text', 'textarea', 'datepicker', 'select_posts'].indexOf(f.type) !== -1) {
			html += row('Placeholder', input('placeholder', f.placeholder || ''));
		}

		if (f.type === 'image') {
			html += renderImageDefaultRow(f);
			html += renderImageReturnFormatRow(f);
		} else if (f.type === 'datepicker') {
			// Inline calendar: popup datepickers can never appear above a native <dialog>
			// opened with showModal() because showModal() places the dialog in the browser
			// top layer, which no z-index can pierce. Rendering the calendar inline inside
			// the modal body avoids the z-index/positioning problem entirely.
			html += row('Default value',
				'<input type="text" id="tpmeta-f-default" value="' + esc(defaultValueAsString(f.default)) + '" autocomplete="off" placeholder="MM/DD/YYYY — or type any common date format" />'
				+ '<div id="tpmeta-f-default-picker"></div>'
			);
		} else if (f.type !== 'switch' && f.type !== 'checkbox' && f.type !== 'multicheck') {
			// Switch renders its default value picker inside renderSwitchConfig().
			// checkbox / multicheck render a checkbox-based default picker inside their options fieldset.
			html += row('Default value', input('default', defaultValueAsString(f.default)));
		}

		// Switch data type + default value picker
		if (f.type === 'switch') {
			html += renderSwitchConfig(f);
		}

		// Choices / Options (select, checkbox, tabs, radio_buttonset, multicheck)
		if (hasOptions) {
			// Normalise: Kirki-imported fields may store choices as an assoc object {key:label},
			// while the builder stores them as [{value,label}] arrays. Support both.
			var existingOpts = [];
			if (Array.isArray(f.options) && f.options.length) {
				existingOpts = f.options;
			} else if (Array.isArray(f.choices) && f.choices.length) {
				existingOpts = f.choices;
			} else if (f.choices && typeof f.choices === 'object' && !Array.isArray(f.choices)) {
				// Convert {red:'Red', green:'Green'} → [{value:'red', label:'Red'}, ...]
				Object.keys(f.choices).forEach(function (k) {
					existingOpts.push({ value: k, label: f.choices[k] });
				});
			}

			html += '<fieldset class="tpmeta-form-fieldset">';
			html += '<legend>' + esc(optionsLegend) + '</legend>';
			html += optionsHint;
			html += '<ul class="tpmeta-form-list" id="tpmeta-options-list">';
			if (f.type === 'radio_image') {
				existingOpts.forEach(function (opt, i) {
					html += imageOptionRow(opt.value || '', opt.label || '', i);
				});
			} else {
				var _lPH = f.type === 'multicolor' ? 'CSS color (e.g. red, #3362FF)' : '';
				existingOpts.forEach(function (opt, i) {
					html += optionRow(opt.value || '', opt.label || '', i, _lPH);
				});
			}
			html += '</ul>';
			var addLabel = f.type === 'radio_image' ? '+ Add image'
				: f.type === 'multicolor' ? '+ Add color slot'
				: ( f.type === 'radio_buttonset' || f.type === 'tabs' ? '+ Add choice' : '+ Add option' );
			html += '<button type="button" class="tpmeta-form-add-row" id="tpmeta-options-add">' + addLabel + '</button>';
			html += '</fieldset>';
			if (f.type === 'checkbox' || f.type === 'multicheck') {
				html += renderCheckboxDefaultPicker(existingOpts, f.default);
			}
			if (f.type === 'radio_image') {
				html += renderImageBrowserPanel();
			}
		}

		// Dimension: range-slider config (min / max / step / units).
		if (f.type === 'dimension') {
			html += renderDimensionConfig(f);
		}

		// CSS Target: end-user-fills-in-selector feature. Gated to the type
		// allowlist; extend the list (and the spacing template's equivalent
		// branch) when another field type opts in.
		if (CSS_TARGET_TYPES.indexOf(f.type) !== -1) {
			html += renderCssTargetConfig(f);
		}

		// Post Select config
		if (f.type === 'post_select') {
			html += renderPostSelectConfig(f);
		}

		// Metabox builder's "select_posts" — simpler than Options Builder's
		// post_select. Renderer only consumes f.post_type (single string).
		if (f.type === 'select_posts') {
			html += renderSelectPostsConfig(f);
		}

		// Repeater: column layout selector + sub-fields editor
		if (f.type === 'repeater') {
			var cols = f.columns || 1;
			html += '<div class="tpmeta-form-row">'
				+ '<label>Column Layout</label>'
				+ '<select id="tpmeta-rsf-columns" class="tpmeta-form-control" style="max-width:220px">'
				+ '<option value="1"' + (cols === 1 ? ' selected' : '') + '>1 Column (full width)</option>'
				+ '<option value="2"' + (cols === 2 ? ' selected' : '') + '>2 Columns</option>'
				+ '<option value="3"' + (cols === 3 ? ' selected' : '') + '>3 Columns</option>'
				+ '<option value="4"' + (cols === 4 ? ' selected' : '') + '>4 Columns</option>'
				+ '</select>'
				+ '</div>';
			html += renderRepeaterSubFields(f);
		}

		// Output CSS rules — only for field types that produce frontend CSS values.
		if (CSS_OUTPUT_TYPES.indexOf(f.type) !== -1) {
			html += '<fieldset class="tpmeta-form-fieldset">';
			html += '<legend>Output CSS (optional)</legend>';
			if (f.type === 'colorpicker') {
				var _readId = esc(f.id || 'field_id');
				html += '<p class="description" style="margin:0 0 8px;">'
					+ 'The saved color is injected into <code>&lt;head&gt;</code> for every selector below — works with any theme. '
					+ 'Check <strong>!imp</strong> on a rule to append <code>!important</code> and override stubborn theme styles. '
					+ 'Read the value in PHP: <code>tpmeta_get_option(\'' + _readId + '\')</code>.'
					+ '</p>';
			}
			html += '<ul class="tpmeta-form-list" id="tpmeta-output-list">';
			(f.output || []).forEach(function (rule, i) {
				html += outputRow(rule.selector || '', rule.property || '', rule.important || false, i);
			});
			html += '</ul>';
			html += '<button type="button" class="tpmeta-form-add-row" id="tpmeta-output-add">+ Add CSS rule</button>';
			html += '</fieldset>';
		}

		// Conditional
		if (sectionFieldsForCond.length) {
			html += '<fieldset class="tpmeta-form-fieldset">';
			html += '<legend>Conditional show/hide (optional)</legend>';
			html += '<div class="tpmeta-form-grid" style="grid-template-columns: 1fr auto 1fr;">';
			html += '<select id="tpmeta-cond-field"><option value="">— None —</option>';
			sectionFieldsForCond.forEach(function (other) {
				var sel = (f.conditional && f.conditional.field === other.id) ? ' selected' : '';
				html += '<option value="' + esc(other.id) + '"' + sel + '>' + esc(other.label || other.id) + '</option>';
			});
			html += '</select>';
			var ops = ['==', '!=', '>', '<', '>=', '<='];
			html += '<select id="tpmeta-cond-op">';
			ops.forEach(function (op) {
				var sel = (f.conditional && f.conditional.operator === op) ? ' selected' : '';
				html += '<option value="' + op + '"' + sel + '>' + op + '</option>';
			});
			html += '</select>';
			html += '<input type="text" id="tpmeta-cond-value" value="' + esc((f.conditional && f.conditional.value) || '') + '" placeholder="value" />';
			html += '</div>';
			html += '</fieldset>';
		}

		bodyEl.innerHTML = html;

		// Auto-derive the ID from the Label as the user types.
		// Safety: if an ID is already set (existing field), keep it locked so
		// renaming the label does not silently rename the saved theme_mod key.
		// Once the user manually edits the ID, the link is permanently broken
		// for this modal session.
		(function wireLabelToId() {
			var labelEl = document.getElementById('tpmeta-f-label');
			var idEl    = document.getElementById('tpmeta-f-id');
			if (!labelEl || !idEl) return;

			function slugify(s) {
				return String(s || '')
					.toLowerCase()
					.replace(/[^a-z0-9]+/g, '_')
					.replace(/^_+|_+$/g, '')
					.replace(/_+/g, '_');
			}

			var idLocked = !!idEl.value.trim();
			idEl.addEventListener('input', function () { idLocked = true; });
			labelEl.addEventListener('input', function () {
				if (idLocked) return;
				idEl.value = slugify(labelEl.value);
			});
		})();

		// Wire dynamic add/remove for options & output rows
		var optAdd = document.getElementById('tpmeta-options-add');
		if (optAdd) optAdd.addEventListener('click', function () {
			var ul = document.getElementById('tpmeta-options-list');
			var i  = ul.children.length;
			if (f.type === 'radio_image') {
				ul.insertAdjacentHTML('beforeend', imageOptionRow('', '', i));
				wireImageRows();
			} else {
				var _lph = f.type === 'multicolor' ? 'CSS color (e.g. red, #3362FF)' : '';
				ul.insertAdjacentHTML('beforeend', optionRow('', '', i, _lph));
			}
		});
		var outAdd = document.getElementById('tpmeta-output-add');
		if (outAdd) outAdd.addEventListener('click', function () {
			var ul = document.getElementById('tpmeta-output-list');
			var i = ul.children.length;
			// Colorpicker defaults the CSS property to `color` so the typical
			// case is one-input (selector only). User can still override.
			var defaultProp = f.type === 'colorpicker' ? 'color' : '';
			ul.insertAdjacentHTML('beforeend', outputRow('', defaultProp, false, i));
		});
		var metaAdd = document.getElementById('tpmeta-ps-meta-add');
		if (metaAdd) metaAdd.addEventListener('click', function () {
			var ul = document.getElementById('tpmeta-ps-meta-list');
			var i = ul.children.length;
			ul.insertAdjacentHTML('beforeend', metaFilterRow('', '', i));
		});
		var spMetaAdd = document.getElementById('tpmeta-sp-meta-add');
		if (spMetaAdd) spMetaAdd.addEventListener('click', function () {
			var ul = document.getElementById('tpmeta-sp-meta-list');
			var i = ul.children.length;
			ul.insertAdjacentHTML('beforeend', metaFilterRow('', '', i));
		});
		bodyEl.addEventListener('click', function (e) {
			var btn = e.target.closest('.button-link-delete');
			if (btn) {
				e.preventDefault();
				btn.closest('.tpmeta-form-list-row').remove();
			}
		});

		if (f.type === 'radio_image') {
			wireImageRows();
			wireImageBrowser();
		}

		if (f.type === 'image') {
			wireImageDefault();
		}

		if (f.type === 'switch') {
			wireSwitchConfig();
		}

		if (f.type === 'repeater') {
			wireRepeaterSubFields();
		}

		if (f.type === 'datepicker') {
			wireDefaultDatepicker();
		}
	}

	function readForm() {
		var f = currentField;
		f.id          = val('id');
		f.label       = val('label');
		f.description = val('description');
		f.placeholder = val('placeholder');

		if (f.type === 'checkbox' || f.type === 'multicheck') {
			var defPicker = document.getElementById('tpmeta-checkbox-defaults');
			if (defPicker) {
				f.default = [];
				Array.prototype.forEach.call(
					defPicker.querySelectorAll('[data-default-item]:checked'),
					function (cb) { f.default.push(cb.value); }
				);
			} else {
				f.default = '';
			}
		} else {
			f.default = val('default');
		}

		// Image: read default + return_format from the image-specific form rows.
		if (f.type === 'image') {
			var imgDefaultEl = document.getElementById('tpmeta-f-image-default');
			if (imgDefaultEl) f.default = imgDefaultEl.value.trim();
			var imgIdEl = document.getElementById('tpmeta-f-image-default-id');
			if (imgIdEl && imgIdEl.value !== '') {
				// User picked from Media Library — prefer numeric ID over URL.
				f.default = imgIdEl.value;
			}
			var rfEl = document.querySelector('input[name="tpmeta-image-return-format"]:checked');
			f.return_format = rfEl ? rfEl.value : 'array';
		}

		// Switch data type + default value (radio cards replace the text input).
		if (f.type === 'switch') {
			var dtEl = document.querySelector('input[name="tpmeta-switch-data-type"]:checked');
			f.data_type = dtEl ? dtEl.value : 'string';
			var dvEl = document.querySelector('input[name="tpmeta-switch-default"]:checked');
			if (dvEl) {
				f.default = dvEl.value;
			} else {
				// Fallback if the picker isn't rendered for any reason.
				f.default = (f.data_type === 'boolean') ? 'false' : 'off';
			}
		}

		// CSS Target — only persist when enabled so disabled fields stay
		// byte-identical to their pre-feature shape.
		if (CSS_TARGET_TYPES.indexOf(f.type) !== -1) {
			var ctEn = document.getElementById('tpmeta-f-css-target-enabled');
			var ctPr = document.getElementById('tpmeta-f-css-target-property');
			var ctSe = document.getElementById('tpmeta-f-css-target-selector');
			if (ctEn && ctEn.checked) {
				var prop  = ctPr ? ctPr.value.trim() : '';
				var sel   = ctSe ? ctSe.value.trim() : '';
				var ctImp = document.getElementById('tpmeta-f-css-target-important');
				f.css_target = {
					enabled:   true,
					property:  prop !== '' ? prop : 'padding',
					selector:  sel,
					important: !!(ctImp && ctImp.checked),
				};
			} else {
				delete f.css_target;
			}
		}

		// Dimension: range-slider config — only persist provided values so
		// absent keys mean "use template defaults" on the consumer side.
		if (f.type === 'dimension') {
			['min', 'max', 'step'].forEach(function (k) {
				var el = document.getElementById('tpmeta-f-dim-' + k);
				if (!el) { return; }
				var raw = el.value.trim();
				if (raw === '' || isNaN(parseFloat(raw))) {
					delete f[k];
				} else {
					f[k] = parseFloat(raw);
				}
			});
			var unitsEl = document.getElementById('tpmeta-f-dim-units');
			if (unitsEl) {
				var unitsRaw = unitsEl.value.trim();
				if (unitsRaw === '') {
					delete f.units;
				} else {
					f.units = unitsRaw.split(',').map(function (u) { return u.trim(); }).filter(Boolean);
				}
			}
		}

		// Post Select config
		if (f.type === 'post_select') {
			readPostSelectConfig();
		}

		// Metabox builder's "select_posts" config (post_type only).
		if (f.type === 'select_posts') {
			readSelectPostsConfig();
		}

		// Options
		var optsList   = document.getElementById('tpmeta-options-list');
		var isImgField = f.type === 'radio_image';
		if (optsList) {
			f.options = [];
			Array.prototype.forEach.call(optsList.querySelectorAll('.tpmeta-form-list-row'), function (row) {
				var v = row.querySelector('[data-key="value"]').value.trim();
				var l = row.querySelector('[data-key="label"]').value.trim();
				if (v !== '') f.options.push({ value: v, label: isImgField ? l : (l || v) });
			});
		}

		// Output
		var outList = document.getElementById('tpmeta-output-list');
		if (outList) {
			f.output = [];
			Array.prototype.forEach.call(outList.querySelectorAll('.tpmeta-form-list-row'), function (row) {
				var s   = row.querySelector('[data-key="selector"]').value.trim();
				var p   = row.querySelector('[data-key="property"]').value.trim();
				var imp = row.querySelector('[data-key="important"]');
				if (s && p) {
					var rule = { selector: s, property: p };
					if (imp && imp.checked) rule.important = true;
					f.output.push(rule);
				}
			});
		} else if (CSS_OUTPUT_TYPES.indexOf(f.type) === -1) {
			delete f.output;
		}

		// Conditional
		var cf = document.getElementById('tpmeta-cond-field');
		if (cf && cf.value) {
			f.conditional = {
				field:    cf.value,
				operator: document.getElementById('tpmeta-cond-op').value,
				value:    document.getElementById('tpmeta-cond-value').value,
			};
		} else {
			delete f.conditional;
		}

		// Repeater sub-fields
		if (f.type === 'repeater') {
			readRepeaterSubFields();
		}
	}

	// ---------- HTML helpers ----------
	function row(label, controlHtml, descHtml) {
		return '<div class="tpmeta-form-row">'
			+ '<label>' + esc(label) + '</label>'
			+ controlHtml
			+ (descHtml ? '<div class="description">' + esc(descHtml) + '</div>' : '')
			+ '</div>';
	}
	function input(key, value, opts) {
		opts = opts || {};
		var style = opts.mono ? ' style="font-family:ui-monospace,Menlo,Consolas,monospace;"' : '';
		return '<input type="text" id="tpmeta-f-' + key + '" value="' + esc(value) + '"' + style + ' />';
	}
	function textarea(key, value, rows) {
		return '<textarea id="tpmeta-f-' + key + '" rows="' + (rows || 3) + '">' + esc(value) + '</textarea>';
	}
	function optionRow(value, label, i, labelPH) {
		return '<li class="tpmeta-form-list-row">'
			+ '<input type="text" data-key="value" value="' + esc(value) + '" placeholder="value" />'
			+ '<input type="text" data-key="label" value="' + esc(label) + '" placeholder="' + ( labelPH || 'label' ) + '" />'
			+ '<button type="button" class="button button-link-delete" aria-label="remove">&times;</button>'
			+ '</li>';
	}

	function imageOptionRow(value, url, i) {
		var thumb = url
			? '<img class="tpmeta-img-thumb" src="' + esc(url) + '" alt="" />'
			: '<span class="tpmeta-img-thumb tpmeta-img-thumb--empty"></span>';
		return '<li class="tpmeta-form-list-row tpmeta-img-opt-row">'
			+ '<input type="text" data-key="value" value="' + esc(value) + '" placeholder="key  e.g. layout-1" />'
			+ '<div class="tpmeta-img-url-cell">'
			+   thumb
			+   '<input type="text" data-key="label" value="' + esc(url) + '" placeholder="Image URL — or use Browse ↗" />'
			+   '<button type="button" class="tpmeta-img-browse-btn" title="Browse images">&#128247; Browse</button>'
			+ '</div>'
			+ '<button type="button" class="button button-link-delete" aria-label="remove">&times;</button>'
			+ '</li>';
	}

	function renderImageBrowserPanel() {
		return '<div id="tpmeta-img-browser" class="tpmeta-img-browser">'
			+ '<div class="tpmeta-img-browser-bar">'
			+   '<select id="tpmeta-img-dir-sel"><option value="">Loading directories…</option></select>'
			+   '<input type="text" id="tpmeta-img-subpath" placeholder="Subdirectory  e.g. inc/img/headers" />'
			+   '<button type="button" class="button tpmeta-img-scan-btn" id="tpmeta-img-scan-btn">Scan</button>'
			+   '<button type="button" class="tpmeta-img-browser-close" id="tpmeta-img-close-btn" aria-label="Close">&#10005;</button>'
			+ '</div>'
			+ '<div id="tpmeta-img-grid" class="tpmeta-img-grid">'
			+   '<span class="tpmeta-img-grid-hint">Select a directory and click Scan to browse images.</span>'
			+ '</div>'
			+ '</div>';
	}

	var activeBrowseRow = null;

	function wireImageRows() {
		var ul = document.getElementById('tpmeta-options-list');
		if (!ul) return;
		Array.prototype.forEach.call(ul.querySelectorAll('.tpmeta-img-opt-row'), function (li) {
			var urlInput = li.querySelector('[data-key="label"]');
			if (urlInput._imgWired) return;
			urlInput._imgWired = true;
			urlInput.addEventListener('input', function () {
				var url   = urlInput.value.trim();
				var thumb = li.querySelector('.tpmeta-img-thumb');
				if (!thumb) return;
				if (thumb.tagName === 'IMG') {
					thumb.src = url || '';
				} else if (url) {
					var img = document.createElement('img');
					img.className = 'tpmeta-img-thumb';
					img.src = url;
					thumb.parentNode.replaceChild(img, thumb);
				}
			});
		});
	}

	function wireImageBrowser() {
		var browser  = document.getElementById('tpmeta-img-browser');
		var dirSel   = document.getElementById('tpmeta-img-dir-sel');
		var subpath  = document.getElementById('tpmeta-img-subpath');
		var scanBtn  = document.getElementById('tpmeta-img-scan-btn');
		var closeBtn = document.getElementById('tpmeta-img-close-btn');
		var grid     = document.getElementById('tpmeta-img-grid');
		var ul       = document.getElementById('tpmeta-options-list');
		if (!browser || !ul) return;

		// Load directory list
		wp.apiFetch({ path: 'tpmeta/v1/builder/directories' }).then(function (dirs) {
			var opts = '<option value="">— Select directory —</option>';
			dirs.forEach(function (d) {
				opts += '<option value="' + esc(d.value) + '">' + esc(d.label) + '</option>';
			});
			dirSel.innerHTML = opts;
		}).catch(function () {
			dirSel.innerHTML = '<option value="">Failed to load directories</option>';
		});

		// Open browser from any row's Browse button
		ul.addEventListener('click', function (e) {
			var btn = e.target.closest('.tpmeta-img-browse-btn');
			if (!btn) return;
			activeBrowseRow = btn.closest('.tpmeta-img-opt-row');
			browser.classList.add('is-open');
		});

		closeBtn.addEventListener('click', function () {
			browser.classList.remove('is-open');
			activeBrowseRow = null;
		});

		scanBtn.addEventListener('click', function () {
			var dir = dirSel.value;
			if (!dir) {
				grid.innerHTML = '<span class="tpmeta-img-grid-hint">Please select a directory first.</span>';
				return;
			}
			var sub      = subpath.value.trim().replace(/^\/+/, '').replace(/\/+$/, '');
			var fullPath = dir + (sub ? '/' + sub : '');

			grid.innerHTML = '<span class="tpmeta-img-grid-hint tpmeta-img-grid-scanning">Scanning…</span>';

			wp.apiFetch({ path: 'tpmeta/v1/builder/images?dir=' + encodeURIComponent(fullPath) }).then(function (data) {
				var images = data.images || [];
				if (!images.length) {
					grid.innerHTML = '<span class="tpmeta-img-grid-hint">No images found. Try a different subdirectory.</span>';
					return;
				}
				var html = '';
				images.forEach(function (img) {
					html += '<button type="button" class="tpmeta-img-cell" data-url="' + esc(img.url) + '" title="' + esc(img.filename) + '">'
						+   '<img src="' + esc(img.url) + '" alt="' + esc(img.filename) + '" loading="lazy" />'
						+   '<span class="tpmeta-img-cell-name">' + esc(img.filename) + '</span>'
						+ '</button>';
				});
				grid.innerHTML = html;
			}).catch(function () {
				grid.innerHTML = '<span class="tpmeta-img-grid-hint">Failed to load images.</span>';
			});
		});

		// Click image → fill active row's URL input + thumbnail
		grid.addEventListener('click', function (e) {
			var cell = e.target.closest('.tpmeta-img-cell');
			if (!cell || !activeBrowseRow) return;
			var url      = cell.dataset.url;
			var urlInput = activeBrowseRow.querySelector('[data-key="label"]');
			var thumb    = activeBrowseRow.querySelector('.tpmeta-img-thumb');

			urlInput.value = url;

			if (thumb && thumb.tagName === 'IMG') {
				thumb.src = url;
			} else {
				var img = document.createElement('img');
				img.className = 'tpmeta-img-thumb';
				img.src = url;
				if (thumb) thumb.parentNode.replaceChild(img, thumb);
			}

			browser.classList.remove('is-open');
			activeBrowseRow = null;
		});
	}

	// ---------- Image field: default value picker + return format ----------

	/**
	 * Resolve a portable image token like "{{theme_url}}/img/x.jpg" to an
	 * absolute URL using the prefix map localized into TPMetaBuilder.
	 * Pass-through when no token is present.
	 */
	function resolveImageToken(value) {
		if (!value || typeof value !== 'string') return value || '';
		if (value.indexOf('{{') === -1) return value;
		var map = (window.TPMetaBuilder && TPMetaBuilder.imageTokenMap) || {};
		return value
			.replace(/\{\{theme_url\}\}/g,      map.theme_url      || '')
			.replace(/\{\{stylesheet_url\}\}/g, map.stylesheet_url || '')
			.replace(/\{\{content_url\}\}/g,    map.content_url    || '')
			.replace(/\{\{(theme_url|plugin_url):([a-z0-9_\-]+)\}\}/gi, function (_, kind, slug) {
				var bag = (kind.toLowerCase() === 'theme_url') ? (map.themes || {}) : (map.plugins || {});
				return bag[slug] || '';
			});
	}

	/**
	 * Image-default value can be:
	 *   - empty
	 *   - a numeric attachment ID (picked from Media Library)
	 *   - a URL string (pasted, or picked from theme directory browser)
	 * Stored on f.default. The picker UI keeps a hidden ID field so a
	 * Media-Library pick survives a re-open.
	 */
	function renderImageDefaultRow(f) {
		var def = f.default;
		var inputValue = ''; // what shows in the input (URL or token)
		var id         = '';
		if (def && typeof def === 'object') {
			// Baked panels may carry default as { url, alt, id }.
			if (def.id != null && /^\d+$/.test(String(def.id))) id = String(def.id);
			if (def.url) inputValue = String(def.url);
		} else {
			var s = def == null ? '' : String(def);
			if (/^\d+$/.test(s)) id = s;
			else if (s) inputValue = s;
			// URL for an ID is rehydrated lazily after the modal is in the DOM.
		}
		var previewUrl = resolveImageToken(inputValue);
		var thumbHtml  = previewUrl
			? '<img class="tpmeta-img-default-preview" src="' + esc(previewUrl) + '" alt="" />'
			: '<span class="tpmeta-img-default-preview tpmeta-img-default-preview--empty">'
			+   '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>'
			+ '</span>';
		var isPortable = inputValue && inputValue.indexOf('{{') !== -1;
		var badgeHtml  = isPortable
			? '<span class="tpmeta-img-default-badge" title="This is a portable token. It auto-resolves to the correct URL on any installation.">'
			+   '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01L12 2z"/></svg>'
			+   ' Portable'
			+ '</span>'
			: '';

		var html = '<div class="tpmeta-form-row tpmeta-image-default-row">';
		html += '<label>Default Image</label>';
		html += '<div class="tpmeta-img-default">';
		html +=   '<div class="tpmeta-img-default-input">';
		html +=     thumbHtml;
		html +=     '<input type="text" id="tpmeta-f-image-default" value="' + esc(inputValue) + '" placeholder="Paste image URL — or use the buttons below" />';
		html +=     badgeHtml;
		html +=     '<input type="hidden" id="tpmeta-f-image-default-id" value="' + esc(id) + '" />';
		html +=     '<button type="button" class="tpmeta-img-default-clear" id="tpmeta-img-default-clear" title="Clear" aria-label="Clear default image">'
			+         '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>'
			+       '</button>';
		html +=   '</div>';
		html +=   '<div class="tpmeta-img-default-actions">';
		html +=     '<button type="button" class="tpmeta-img-action" id="tpmeta-img-default-browse">'
			+         '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/></svg>'
			+         '<span>Browse Theme</span>'
			+       '</button>';
		html +=   '</div>';
		html += '</div>';
		html += '<div class="description">Used as the fallback when no image is saved. <strong>Browse Theme</strong> picks are stored as portable tokens (e.g. <code>{{theme_url}}/img/x.jpg</code>) so they survive a domain change. <strong>Media Library</strong> picks store the attachment ID. Pasted URLs are stored as-is.</div>';
		html += '</div>';
		// Inline browser panel (reused only when "Browse Theme" is clicked for the default).
		html += renderImageBrowserPanel();
		return html;
	}

	function renderImageReturnFormatRow(f) {
		var rf = f.return_format || 'array';
		var cards = [
			{
				value: 'array',
				title: 'Array',
				hint:  '{ id, url, alt }',
				desc:  'Full attachment data',
				icon:  '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3"/><path d="M16 3h3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-3"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>'
			},
			{
				value: 'id',
				title: 'Attachment ID',
				hint:  '123',
				desc:  'Numeric ID only',
				icon:  '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 9h2v6"/><path d="M14 9h1.5a1.5 1.5 0 0 1 1.5 1.5v3A1.5 1.5 0 0 1 15.5 15H14V9z"/></svg>'
			},
			{
				value: 'url',
				title: 'URL string',
				hint:  'https://…',
				desc:  'Plain image URL',
				icon:  '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1"/><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/></svg>'
			}
		];

		var html = '<fieldset class="tpmeta-form-fieldset tpmeta-img-rf-fieldset">';
		html += '<legend>Return Format</legend>';
		html += '<div class="tpmeta-img-rf-grid">';
		cards.forEach(function (c) {
			var checked = (rf === c.value);
			html += '<label class="tpmeta-img-rf-card' + (checked ? ' is-active' : '') + '">';
			html +=   '<input type="radio" name="tpmeta-image-return-format" value="' + esc(c.value) + '"' + (checked ? ' checked' : '') + ' />';
			html +=   '<span class="tpmeta-img-rf-check" aria-hidden="true">'
				+       '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 12 10 17 19 7"/></svg>'
				+     '</span>';
			html +=   '<span class="tpmeta-img-rf-icon">' + c.icon + '</span>';
			html +=   '<span class="tpmeta-img-rf-body">';
			html +=     '<span class="tpmeta-img-rf-title">' + esc(c.title) + '</span>';
			html +=     '<span class="tpmeta-img-rf-desc">' + esc(c.desc) + '</span>';
			html +=     '<code class="tpmeta-img-rf-hint">' + esc(c.hint) + '</code>';
			html +=   '</span>';
			html += '</label>';
		});
		html += '</div>';
		html += '<div class="description">Shape of the value returned by <code>tpmeta_get_option()</code> / <code>tpmeta_image_field()</code>. <strong>Array</strong> is the default for backward compatibility.</div>';
		html += '</fieldset>';
		return html;
	}

	function wireImageDefault() {
		var urlInput  = document.getElementById('tpmeta-f-image-default');
		var idInput   = document.getElementById('tpmeta-f-image-default-id');
		var browseBtn = document.getElementById('tpmeta-img-default-browse');
		var clearBtn  = document.getElementById('tpmeta-img-default-clear');
		var row       = urlInput && urlInput.closest('.tpmeta-image-default-row');
		if (!row) return;

		// If the stored default is an attachment ID, look up its URL for the thumbnail.
		// (Attachment ID storage is independent of the URL/token in the input.)
		if (idInput.value && !urlInput.value && window.wp && wp.media) {
			var attachment = wp.media.attachment(parseInt(idInput.value, 10));
			attachment.fetch().then(function () {
				var att = attachment.toJSON();
				if (att && att.url) {
					urlInput.value = att.url;
					setThumb(row, att.url);
					updatePortableBadge(row, att.url);
				}
			}, function () { /* attachment missing — leave fields empty */ });
		}

		// Live thumbnail on URL input (clears the stored ID, since user is overriding).
		// Token-form inputs are resolved for the preview; raw URLs pass through.
		urlInput.addEventListener('input', function () {
			var raw = urlInput.value.trim();
			setThumb(row, resolveImageToken(raw));
			updatePortableBadge(row, raw);
			idInput.value = '';
		});

		// Theme directory browser — pasting a URL clears the ID.
		var browser = row.parentElement.querySelector('#tpmeta-img-browser');
		browseBtn.addEventListener('click', function (e) {
			e.preventDefault();
			activeBrowseRow = row; // reuse the existing browser plumbing
			browser.classList.add('is-open');
		});
		// Override the existing grid click handler scope so it writes to our row's URL input.
		// The grid click handler in wireImageBrowser already sets activeBrowseRow.querySelector('[data-key="label"]').
		// Our row uses a different selector — listen here too.
		browser.addEventListener('click', function (e) {
			var cell = e.target.closest('.tpmeta-img-cell');
			if (!cell) return;
			if (activeBrowseRow !== row) return;
			// Prefer the portable token over the absolute URL — that's what
			// makes the saved default survive a domain change.
			var stored  = cell.dataset.token || cell.dataset.url;
			var preview = resolveImageToken(stored);
			urlInput.value = stored;
			idInput.value  = '';
			setThumb(row, preview);
			updatePortableBadge(row, stored);
			browser.classList.remove('is-open');
			activeBrowseRow = null;
		});

		// Initial directories load + scan wiring (mirrors wireImageBrowser; runs once).
		wireImageBrowserPlumbing(browser);

		clearBtn.addEventListener('click', function (e) {
			e.preventDefault();
			urlInput.value = '';
			idInput.value  = '';
			setThumb(row, '');
			updatePortableBadge(row, '');
		});

		// Return-format card active-state sync (visual only — :checked drives styling too).
		var rfRadios = document.querySelectorAll('input[name="tpmeta-image-return-format"]');
		rfRadios.forEach(function (r) {
			r.addEventListener('change', function () {
				rfRadios.forEach(function (x) {
					var card = x.closest('.tpmeta-img-rf-card');
					if (card) card.classList.toggle('is-active', x.checked);
				});
			});
		});
	}

	function updatePortableBadge(row, value) {
		var input = row.querySelector('#tpmeta-f-image-default');
		if (!input) return;
		var badge = row.querySelector('.tpmeta-img-default-badge');
		var isToken = !!(value && typeof value === 'string' && value.indexOf('{{') !== -1);
		if (isToken && !badge) {
			badge = document.createElement('span');
			badge.className = 'tpmeta-img-default-badge';
			badge.title = 'This is a portable token. It auto-resolves to the correct URL on any installation.';
			badge.innerHTML = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01L12 2z"/></svg> Portable';
			input.parentNode.insertBefore(badge, input.nextSibling);
		} else if (!isToken && badge) {
			badge.remove();
		}
	}

	function setThumb(row, url) {
		var thumb = row.querySelector('.tpmeta-img-default-preview');
		if (!thumb) return;
		var emptyMarkup = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>';
		if (url) {
			if (thumb.tagName !== 'IMG') {
				var img = document.createElement('img');
				img.className = 'tpmeta-img-default-preview';
				img.src = url;
				thumb.parentNode.replaceChild(img, thumb);
			} else {
				thumb.src = url;
			}
		} else {
			if (thumb.tagName === 'IMG') {
				var span = document.createElement('span');
				span.className = 'tpmeta-img-default-preview tpmeta-img-default-preview--empty';
				span.innerHTML = emptyMarkup;
				thumb.parentNode.replaceChild(span, thumb);
			}
		}
	}

	/**
	 * Standalone browser plumbing for the image-default picker.
	 * radio_image's wireImageBrowser binds to its own options-list — this
	 * wires the dir dropdown / scan button without binding a row click target.
	 */
	function wireImageBrowserPlumbing(browser) {
		if (browser._tpmetaPlumbed) return;
		browser._tpmetaPlumbed = true;
		var dirSel   = browser.querySelector('#tpmeta-img-dir-sel');
		var subpath  = browser.querySelector('#tpmeta-img-subpath');
		var scanBtn  = browser.querySelector('#tpmeta-img-scan-btn');
		var closeBtn = browser.querySelector('#tpmeta-img-close-btn');
		var grid     = browser.querySelector('#tpmeta-img-grid');

		wp.apiFetch({ path: 'tpmeta/v1/builder/directories' }).then(function (dirs) {
			var opts = '<option value="">— Select directory —</option>';
			dirs.forEach(function (d) {
				opts += '<option value="' + esc(d.value) + '">' + esc(d.label) + '</option>';
			});
			dirSel.innerHTML = opts;
		}).catch(function () {
			dirSel.innerHTML = '<option value="">Failed to load directories</option>';
		});

		closeBtn.addEventListener('click', function () {
			browser.classList.remove('is-open');
			activeBrowseRow = null;
		});

		scanBtn.addEventListener('click', function () {
			var dir = dirSel.value;
			if (!dir) {
				grid.innerHTML = '<span class="tpmeta-img-grid-hint">Please select a directory first.</span>';
				return;
			}
			var sub      = subpath.value.trim().replace(/^\/+/, '').replace(/\/+$/, '');
			var fullPath = dir + (sub ? '/' + sub : '');
			grid.innerHTML = '<span class="tpmeta-img-grid-hint tpmeta-img-grid-scanning">Scanning…</span>';
			wp.apiFetch({ path: 'tpmeta/v1/builder/images?dir=' + encodeURIComponent(fullPath) }).then(function (data) {
				var images = data.images || [];
				if (!images.length) {
					grid.innerHTML = '<span class="tpmeta-img-grid-hint">No images found. Try a different subdirectory.</span>';
					return;
				}
				var html = '';
				images.forEach(function (img) {
					html += '<button type="button" class="tpmeta-img-cell"'
						+   ' data-url="' + esc(img.url) + '"'
						+   ' data-token="' + esc(img.token || img.url) + '"'
						+   ' title="' + esc(img.filename) + '">'
						+   '<img src="' + esc(img.url) + '" alt="' + esc(img.filename) + '" loading="lazy" />'
						+   '<span class="tpmeta-img-cell-name">' + esc(img.filename) + '</span>'
						+ '</button>';
				});
				grid.innerHTML = html;
			}).catch(function () {
				grid.innerHTML = '<span class="tpmeta-img-grid-hint">Failed to load images.</span>';
			});
		});
	}

	function outputRow(sel, prop, imp, i) {
		var chk = imp ? ' checked' : '';
		return '<li class="tpmeta-form-list-row">'
			+ '<input type="text" data-key="selector" value="' + esc(sel) + '" placeholder="selector e.g. a.btn" />'
			+ '<input type="text" data-key="property" value="' + esc(prop) + '" placeholder="property e.g. color" />'
			+ '<label class="tpmeta-output-imp" title="Append !important to override theme styles"><input type="checkbox" data-key="important"' + chk + '> !imp</label>'
			+ '<button type="button" class="button button-link-delete" aria-label="remove">&times;</button>'
			+ '</li>';
	}
	function val(key) {
		var el = document.getElementById('tpmeta-f-' + key);
		return el ? el.value : '';
	}
	function defaultValueAsString(v) {
		if (v == null) return '';
		if (typeof v === 'object') return JSON.stringify(v);
		return String(v);
	}

	function normalizeCheckboxDefault(val) {
		if (Array.isArray(val)) return val.map(String);
		if (typeof val === 'string' && val.length > 0) {
			if (val[0] === '[') {
				try { var p = JSON.parse(val); if (Array.isArray(p)) return p.map(String); } catch (e) {}
			}
			return val.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
		}
		return [];
	}

	function renderCheckboxDefaultPicker(opts, defaultVal) {
		var defaultArr = normalizeCheckboxDefault(defaultVal);
		var pickerHtml = '<div id="tpmeta-checkbox-defaults" class="tpmeta-checkbox-default-picker">';
		if (opts.length) {
			opts.forEach(function (opt) {
				var checked = defaultArr.indexOf(String(opt.value)) !== -1 ? ' checked' : '';
				pickerHtml += '<label class="tm-checkbox-item">'
					+ '<input type="checkbox" data-default-item value="' + esc(opt.value) + '"' + checked + '>'
					+ ' ' + esc(opt.label || opt.value)
					+ '</label>';
			});
		} else {
			pickerHtml += '<em style="color:#888;font-size:12px">Add options above to set defaults.</em>';
		}
		pickerHtml += '</div>';
		return row('Default pre-checked', pickerHtml);
	}

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	// ---------- Section editor ----------

	// A small curated set of common dashicons. Users can also type any class.
	var COMMON_ICONS = [
		'dashicons-admin-generic', 'dashicons-admin-customizer', 'dashicons-admin-appearance',
		'dashicons-admin-site', 'dashicons-admin-tools', 'dashicons-admin-settings',
		'dashicons-art', 'dashicons-format-image', 'dashicons-format-gallery',
		'dashicons-editor-textcolor', 'dashicons-editor-paragraph', 'dashicons-editor-table',
		'dashicons-layout', 'dashicons-screenoptions', 'dashicons-menu',
		'dashicons-yes', 'dashicons-yes-alt', 'dashicons-controls-volumeon',
		'dashicons-share', 'dashicons-email', 'dashicons-phone',
		'dashicons-cart', 'dashicons-products', 'dashicons-money-alt',
		'dashicons-chart-line', 'dashicons-chart-bar', 'dashicons-analytics',
		'dashicons-calendar-alt', 'dashicons-clock', 'dashicons-location',
		'dashicons-search', 'dashicons-filter', 'dashicons-tag',
		'dashicons-lightbulb', 'dashicons-shield', 'dashicons-lock',
	];

	var sectionData = null;
	var sectionApplyCb = null;

	function openSection(section, cb) {
		if (!dlg) init();
		sectionData    = JSON.parse(JSON.stringify(section || {}));
		sectionApplyCb = cb;

		titleEl.textContent = 'Section Settings';
		setHandler(doApplySection);
		renderSectionForm();
		showDialog();
	}

	function doApplySection() {
		sectionData.title       = document.getElementById('tpmeta-s-title').value;
		sectionData.description = document.getElementById('tpmeta-s-description').value;
		sectionData.icon        = document.getElementById('tpmeta-s-icon').value.trim();
		close();
		if (sectionApplyCb) sectionApplyCb(sectionData);
	}

	function renderSectionForm() {
		var s = sectionData;
		var html = '';

		html += row('Section Title', '<input type="text" id="tpmeta-s-title" value="' + esc(s.title || '') + '" />');
		html += row('Description', '<textarea id="tpmeta-s-description" rows="2">' + esc(s.description || '') + '</textarea>',
			'Shown under the section heading on the options page.');

		html += '<div class="tpmeta-form-row">';
		html += '<label>Icon</label>';
		html += '<div class="tpmeta-icon-input-row">';
		html += '<input type="text" id="tpmeta-s-icon" value="' + esc(s.icon || '') + '" placeholder="dashicons-art" />';
		html += '<span id="tpmeta-s-icon-preview" class="dashicons ' + esc(s.icon || 'dashicons-admin-generic') + '"></span>';
		html += '</div>';
		html += '<div class="tpmeta-icon-grid" id="tpmeta-icon-grid">';
		COMMON_ICONS.forEach(function (cls) {
			var sel = (s.icon === cls) ? ' is-selected' : '';
			html += '<button type="button" class="tpmeta-icon-cell' + sel + '" data-icon="' + esc(cls) + '" title="' + esc(cls) + '">'
				+ '<span class="dashicons ' + esc(cls) + '"></span>'
				+ '</button>';
		});
		html += '</div>';
		html += '<div class="description">Click a preset or type any dashicons-* class.</div>';
		html += '</div>';

		bodyEl.innerHTML = html;

		// Live icon preview as user types.
		var iconInput   = document.getElementById('tpmeta-s-icon');
		var iconPreview = document.getElementById('tpmeta-s-icon-preview');
		iconInput.addEventListener('input', function () {
			iconPreview.className = 'dashicons ' + (iconInput.value.trim() || 'dashicons-admin-generic');
		});

		// Click a preset = fill input + highlight cell.
		document.getElementById('tpmeta-icon-grid').addEventListener('click', function (e) {
			var cell = e.target.closest('.tpmeta-icon-cell');
			if (!cell) return;
			iconInput.value = cell.dataset.icon;
			iconPreview.className = 'dashicons ' + cell.dataset.icon;
			Array.prototype.forEach.call(this.querySelectorAll('.tpmeta-icon-cell'), function (c) {
				c.classList.toggle('is-selected', c === cell);
			});
		});
	}

	// ---------- Panel settings editor ----------

	var panelData = null;
	var panelApplyCb = null;
	var panelPrefixOpts = {};

	function openPanel(panel, cb, opts) {
		if (!dlg) init();
		panelData       = JSON.parse(JSON.stringify(panel || {}));
		panelApplyCb    = cb;
		panelPrefixOpts = opts || {};

		titleEl.textContent = 'Panel Settings';
		setHandler(doApplyPanel);
		renderPanelForm();
		showDialog();
	}

	function doApplyPanel() {
		var optName = (document.getElementById('tpmeta-p-opt-name').value || '').toLowerCase().replace(/[^a-z0-9_]/g, '_');
		var slug    = (document.getElementById('tpmeta-p-slug').value || '').toLowerCase().replace(/[^a-z0-9_\-]/g, '-');
		var pos     = parseInt(document.getElementById('tpmeta-p-position').value, 10);
		var cprEl   = document.getElementById('tpmeta-p-customizer-priority');
		var cprRaw  = cprEl ? cprEl.value.trim() : '';

		panelData.opt_name      = optName || panelData.opt_name;
		panelData.menu_slug     = slug || optName;
		panelData.menu_title    = document.getElementById('tpmeta-p-menu-title').value;
		panelData.page_title    = document.getElementById('tpmeta-p-page-title').value || panelData.menu_title;
		panelData.menu_icon     = document.getElementById('tpmeta-p-icon').value.trim() || 'dashicons-admin-generic';
		panelData.menu_position = isNaN(pos) ? 60 : pos;
		panelData.capability             = document.getElementById('tpmeta-p-capability').value || 'manage_options';
		panelData.output_css             = document.getElementById('tpmeta-p-output-css').checked;
		panelData.customizer_integration = document.getElementById('tpmeta-p-customizer').checked;

		// Raw prefix value — builder.js normalises it, decides whether to bulk
		// re-prefix existing IDs, and persists it for new-field auto-IDs.
		var pfxEl = document.getElementById('tpmeta-p-field-prefix');
		if (pfxEl) panelData.field_prefix = pfxEl.value;

		// Customizer panel position (separate from admin Menu Position).
		// Empty input = use WP default (160). Stored as int only when set.
		if (cprRaw === '' || isNaN(parseInt(cprRaw, 10))) {
			delete panelData.customizer_priority;
		} else {
			panelData.customizer_priority = parseInt(cprRaw, 10);
		}

		close();
		if (panelApplyCb) panelApplyCb(panelData);
	}

	function renderPanelForm() {
		var p = panelData;
		var html = '';

		html += '<div class="tpmeta-form-grid">';
		html += row('Menu Title', '<input type="text" id="tpmeta-p-menu-title" value="' + esc(p.menu_title || '') + '" />');
		html += row('Page Title', '<input type="text" id="tpmeta-p-page-title" value="' + esc(p.page_title || '') + '" placeholder="(same as menu title)" />');
		html += '</div>';

		html += '<div class="tpmeta-form-grid">';
		html += row('opt_name (panel ID)', '<input type="text" id="tpmeta-p-opt-name" value="' + esc(p.opt_name || '') + '" style="font-family:ui-monospace,Menlo,Consolas,monospace;" />', 'Lowercase letters, numbers, underscores. This is the unique panel identifier.');
		html += row('Menu Slug (URL)', '<input type="text" id="tpmeta-p-slug" value="' + esc(p.menu_slug || '') + '" style="font-family:ui-monospace,Menlo,Consolas,monospace;" />', 'URL slug for the admin page. Lowercase, dashes.');
		html += '</div>';

		var pfxDisplay = (panelPrefixOpts && typeof panelPrefixOpts.prefix === 'string') ? panelPrefixOpts.prefix : (p.field_prefix || '');
		html += '<div class="tpmeta-form-row tpmeta-p-prefix-row">';
		html += '<label for="tpmeta-p-field-prefix">Field &amp; Section ID Prefix</label>';
		html += '<input type="text" id="tpmeta-p-field-prefix" value="' + esc(pfxDisplay) + '" autocomplete="off" spellcheck="false" placeholder="e.g. mytheme_" style="font-family:ui-monospace,Menlo,Consolas,monospace;" />';
		html += '<div class="description">Prefix for every field &amp; section ID. Change it to re-prefix all existing IDs (conditional rules are remapped to match); clear it to strip the prefix. New fields you drag in are auto-named with this prefix.</div>';
		html += '<div class="description tpmeta-p-prefix-example" id="tpmeta-p-field-prefix-example"></div>';
		html += '</div>';

		html += '<div class="tpmeta-form-row">';
		html += '<label>Menu Icon</label>';
		html += '<div class="tpmeta-icon-input-row">';
		html += '<input type="text" id="tpmeta-p-icon" value="' + esc(p.menu_icon || 'dashicons-admin-generic') + '" />';
		html += '<span id="tpmeta-p-icon-preview" class="dashicons ' + esc(p.menu_icon || 'dashicons-admin-generic') + '"></span>';
		html += '</div>';
		html += '<div class="tpmeta-icon-grid" id="tpmeta-p-icon-grid">';
		COMMON_ICONS.forEach(function (cls) {
			var sel = (p.menu_icon === cls) ? ' is-selected' : '';
			html += '<button type="button" class="tpmeta-icon-cell' + sel + '" data-icon="' + esc(cls) + '" title="' + esc(cls) + '">'
				+ '<span class="dashicons ' + esc(cls) + '"></span>'
				+ '</button>';
		});
		html += '</div>';
		html += '</div>';

		html += '<div class="tpmeta-form-grid">';
		html += row('Menu Position', '<input type="number" id="tpmeta-p-position" value="' + esc(p.menu_position || 60) + '" min="1" max="100" />', 'Lower numbers appear higher in the admin menu.');
		html += row('Capability', '<input type="text" id="tpmeta-p-capability" value="' + esc(p.capability || 'manage_options') + '" />', 'WP capability required to access this panel.');
		html += '</div>';

		html += '<div class="tpmeta-form-row">';
		html += '<label><input type="checkbox" id="tpmeta-p-output-css" ' + (p.output_css ? 'checked' : '') + ' /> '
			+ 'Auto-output CSS to wp_head from fields with "output" rules</label>';
		html += '</div>';

		html += '<hr style="margin:12px 0;border:none;border-top:1px solid #e2e4e7;">';

		html += '<div class="tpmeta-form-row">';
		html += '<label style="font-weight:600;display:flex;align-items:flex-start;gap:8px;">'
			+ '<input type="checkbox" id="tpmeta-p-customizer" ' + (p.customizer_integration ? 'checked' : '') + ' style="margin-top:3px;" />'
			+ '<span>Customizer Integration'
			+ '<span style="display:block;font-weight:400;color:#646970;font-size:12px;margin-top:2px;">'
			+ 'Mirror this panel in the WordPress native Customizer (Appearance → Customize). '
			+ 'Fields are registered as Customizer controls using the same <code>theme_mod</code> storage, '
			+ 'so values are always in sync. Ideal for users already familiar with the Customizer workflow.'
			+ '</span></span></label>';
		html += '</div>';

		// Customizer panel position. WP's native default is 160; leave empty
		// to use it. Lower numbers place the panel higher in the Customizer
		// sidebar list. Separate from the admin Menu Position above so the
		// two contexts can be ordered independently.
		var cprVal = (typeof p.customizer_priority === 'number' || (typeof p.customizer_priority === 'string' && p.customizer_priority !== ''))
			? String(p.customizer_priority) : '';
		html += '<div class="tpmeta-form-row tpmeta-p-cust-priority-row" style="margin-top:8px;">';
		html += '<label for="tpmeta-p-customizer-priority">Customizer Position Priority</label>';
		html += '<input type="number" id="tpmeta-p-customizer-priority" value="' + esc(cprVal) + '" placeholder="160" min="1" style="max-width:120px;" />';
		html += '<div class="description">Order within the Customizer panel list. Lower = higher. Empty = WordPress default (160). Independent of the admin Menu Position.</div>';
		html += '</div>';

		bodyEl.innerHTML = html;

		// Live prefix example (e.g. "header_logo → shop_logo"), driven by a
		// callback supplied from builder.js so all the rename logic stays there.
		var pfxInput   = document.getElementById('tpmeta-p-field-prefix');
		var pfxExample = document.getElementById('tpmeta-p-field-prefix-example');
		function updatePfxExample() {
			if (!pfxExample) return;
			pfxExample.textContent = (panelPrefixOpts && typeof panelPrefixOpts.exampleFor === 'function')
				? panelPrefixOpts.exampleFor(pfxInput.value) : '';
		}
		if (pfxInput) { pfxInput.addEventListener('input', updatePfxExample); updatePfxExample(); }

		// Live icon preview + grid clicks
		var iconInput   = document.getElementById('tpmeta-p-icon');
		var iconPreview = document.getElementById('tpmeta-p-icon-preview');
		iconInput.addEventListener('input', function () {
			iconPreview.className = 'dashicons ' + (iconInput.value.trim() || 'dashicons-admin-generic');
		});
		document.getElementById('tpmeta-p-icon-grid').addEventListener('click', function (e) {
			var cell = e.target.closest('.tpmeta-icon-cell');
			if (!cell) return;
			iconInput.value = cell.dataset.icon;
			iconPreview.className = 'dashicons ' + cell.dataset.icon;
			Array.prototype.forEach.call(this.querySelectorAll('.tpmeta-icon-cell'), function (c) {
				c.classList.toggle('is-selected', c === cell);
			});
		});

		// Auto-derive menu_slug from opt_name if slug field is empty.
		var optInput  = document.getElementById('tpmeta-p-opt-name');
		var slugInput = document.getElementById('tpmeta-p-slug');
		var slugDirty = !!slugInput.value && slugInput.value !== (p.opt_name || '').replace(/_/g, '-');
		slugInput.addEventListener('input', function () { slugDirty = true; });
		optInput.addEventListener('input', function () {
			if (!slugDirty) {
				slugInput.value = optInput.value.toLowerCase().replace(/_/g, '-').replace(/[^a-z0-9\-]/g, '');
			}
		});
	}

	// ---------- Dimension config helper ----------
	//
	// Dimension stores a scalar string ("150px"). The template renders an
	// Elementor-style range slider; these inputs configure its bounds.
	// All values are optional — absent keys fall back to the template's
	// defaults (min 0, max 100, step 1, units px/em/rem/%/vh/vw).

	// CSS Target: developer toggles a feature that surfaces a Target Selector
	// input in the FIELD UI at save time (post edit / panel save). End user
	// types a CSS selector there; plugin emits CSS keyed off that selector
	// on wp_head. Developer also picks the CSS property here (locked at
	// modal level; default 'padding'). Storage is additive to the field def:
	// f.css_target = { enabled: bool, property: string }.
	// Absent / disabled = legacy behaviour unchanged.

	function renderCssTargetConfig(f) {
		var ct       = (f.css_target && typeof f.css_target === 'object') ? f.css_target : {};
		var enabled  = !!ct.enabled;
		var property = ( typeof ct.property === 'string' && ct.property.trim() !== '' ) ? ct.property.trim() : 'padding';
		var selector = ( typeof ct.selector === 'string' ) ? ct.selector : '';

		var _readId  = esc(f.id || 'field_id');
		var html = '<fieldset class="tpmeta-form-fieldset">';
		html += '<legend>CSS Output Target (optional)</legend>';
		html += '<p class="description">When enabled, the saved value is auto-injected as a CSS rule '
			+ 'on the target selector you configure below. '
			+ 'Developers can still read the raw value with '
			+ '<code>tpmeta_get_option(\'' + _readId + '\')</code> '
			+ 'or <code>get_theme_mod(\'' + _readId + '\')</code>.</p>';

		html += '<div class="tpmeta-form-row">'
			+ '<label><input type="checkbox" id="tpmeta-f-css-target-enabled"' + (enabled ? ' checked' : '') + ' /> '
			+ 'Enable CSS output target</label>'
			+ '</div>';

		html += row('Target Selector',
			'<input type="text" id="tpmeta-f-css-target-selector" value="' + esc(selector) + '" placeholder=".my-section, body &gt; .hero" />',
			'CSS selector(s) the rule is applied to. Multiple selectors comma-separated.');

		html += row('CSS Property',
			'<input type="text" id="tpmeta-f-css-target-property" value="' + esc(property) + '" placeholder="padding" />',
			'CSS property to write (e.g. padding, margin, scroll-padding). Default: padding.');

		var imp = !!ct.important;
		html += '<div class="tpmeta-form-row">'
			+ '<label><input type="checkbox" id="tpmeta-f-css-target-important"' + (imp ? ' checked' : '') + ' /> '
			+ 'Append <code>!important</code> to override theme styles</label>'
			+ '</div>';

		html += '</fieldset>';
		return html;
	}

	function renderDimensionConfig(f) {
		var minV  = ( typeof f.min  === 'number' || ( typeof f.min  === 'string' && f.min  !== '' ) ) ? String(f.min)  : '';
		var maxV  = ( typeof f.max  === 'number' || ( typeof f.max  === 'string' && f.max  !== '' ) ) ? String(f.max)  : '';
		var stepV = ( typeof f.step === 'number' || ( typeof f.step === 'string' && f.step !== '' ) ) ? String(f.step) : '';
		var units = Array.isArray(f.units) && f.units.length ? f.units.join(', ') : '';

		var html = '<fieldset class="tpmeta-form-fieldset">';
		html += '<legend>Range Slider</legend>';
		html += '<p class="description">Configure the slider bounds. Stored value is still a scalar string like <code>16px</code>.</p>';

		html += '<div class="tpmeta-form-grid" style="grid-template-columns:1fr 1fr 1fr;gap:10px;">';
		html += '<div><label for="tpmeta-f-dim-min">Min</label>'
			+   '<input type="number" id="tpmeta-f-dim-min" value="' + esc(minV) + '" placeholder="0" step="any" /></div>';
		html += '<div><label for="tpmeta-f-dim-max">Max</label>'
			+   '<input type="number" id="tpmeta-f-dim-max" value="' + esc(maxV) + '" placeholder="100" step="any" /></div>';
		html += '<div><label for="tpmeta-f-dim-step">Step</label>'
			+   '<input type="number" id="tpmeta-f-dim-step" value="' + esc(stepV) + '" placeholder="1" step="any" min="0" /></div>';
		html += '</div>';

		html += row('Units (comma-separated)',
			'<input type="text" id="tpmeta-f-dim-units" value="' + esc(units) + '" placeholder="px, em, rem, %, vh, vw" />',
			'Order matters — first unit is the default. Leave empty for the standard set.');

		html += '</fieldset>';
		return html;
	}

	// ---------- Switch config helper ----------

	function renderSwitchConfig(f) {
		var isBoolean = (f.data_type === 'boolean');
		var html = '<fieldset class="tpmeta-form-fieldset">';
		html += '<legend>Switch Options</legend>';

		// Stored value format toggle.
		html += '<div class="tpmeta-form-row">';
		html += '<label>Stored value format</label>';
		html += '<div class="tpmeta-ps-format-toggle">';
		html += '<label class="tpmeta-ps-radio"><input type="radio" name="tpmeta-switch-data-type" value="string"' + (!isBoolean ? ' checked' : '') + '> String &nbsp;<code>on</code> / <code>off</code></label>';
		html += '<label class="tpmeta-ps-radio"><input type="radio" name="tpmeta-switch-data-type" value="boolean"' + (isBoolean ? ' checked' : '') + '> Boolean &nbsp;<code>true</code> / <code>false</code></label>';
		html += '</div>';
		html += '<div class="description">String mode: <code>get_theme_mod()</code> / <code>tpmeta_field()</code> return <code>&quot;on&quot;</code> or <code>&quot;off&quot;</code>. Boolean mode: returns PHP <code>true</code> / <code>false</code> (auto type-cast). Use the matching value in conditional rules.</div>';
		html += '</div>';

		// Default value: radio cards keyed to the chosen format.
		html += '<div class="tpmeta-form-row">';
		html += '<label>Default value</label>';
		html += '<div class="tpmeta-switch-default-wrap" id="tpmeta-switch-default-wrap">';
		html += renderSwitchDefaultPicker(f, isBoolean);
		html += '</div>';
		html += '<div class="description">Used when no value has been saved yet.</div>';
		html += '</div>';

		html += '</fieldset>';
		return html;
	}

	// Truthy/falsy normaliser for the saved default. Accepts both formats so
	// switching between string and boolean preserves the user's intent.
	function switchDefaultIsTruthy(raw) {
		if (raw === true || raw === 1) return true;
		if (raw === false || raw === 0) return false;
		var s = String(raw == null ? '' : raw).toLowerCase().trim();
		return ['true', 'on', '1', 'yes'].indexOf(s) !== -1;
	}

	function renderSwitchDefaultPicker(f, isBoolean) {
		var truthy   = switchDefaultIsTruthy(f.default);
		var trueVal  = isBoolean ? 'true'  : 'on';
		var falseVal = isBoolean ? 'false' : 'off';
		var trueDesc  = isBoolean ? 'PHP boolean true'  : 'String "on"';
		var falseDesc = isBoolean ? 'PHP boolean false' : 'String "off"';

		function card(value, label, desc, active) {
			return '<label class="tpmeta-switch-default-card' + (active ? ' is-active' : '') + '">'
				+ '<input type="radio" name="tpmeta-switch-default" value="' + esc(value) + '"' + (active ? ' checked' : '') + '>'
				+ '<span class="tpmeta-switch-default-card-pill" aria-hidden="true"></span>'
				+ '<span class="tpmeta-switch-default-card-body">'
				+   '<span class="tpmeta-switch-default-card-title">' + esc(label) + '</span>'
				+   '<span class="tpmeta-switch-default-card-sub">' + esc(desc) + '</span>'
				+ '</span>'
				+ '</label>';
		}

		return '<div class="tpmeta-switch-default-grid">'
			+    card(falseVal, falseVal, falseDesc, !truthy)
			+    card(trueVal,  trueVal,  trueDesc,   truthy)
			+ '</div>';
	}

	function wireDefaultDatepicker() {
		var el        = document.getElementById('tpmeta-f-default');
		var pickerDiv = document.getElementById('tpmeta-f-default-picker');
		if (!el || !pickerDiv || typeof jQuery === 'undefined' || !jQuery.fn.datepicker) return;

		// Canonical storage format — matches the front-end metabox renderer
		// (tpmeta-fields.js uses jQuery UI's default 'mm/dd/yy'). Everything
		// the user types in any reasonable format gets normalized to this
		// before save, so post_meta and theme_mod values stay consistent.
		var DATE_FORMAT    = 'mm/dd/yy';
		var ACCEPT_FORMATS = [ 'mm/dd/yy', 'MM d, yy', 'M d, yy', 'yy-mm-dd', 'dd/mm/yy', 'D, M d, yy' ];

		function parseDateLenient(str) {
			if (!str) return null;
			for (var i = 0; i < ACCEPT_FORMATS.length; i++) {
				try {
					var d = jQuery.datepicker.parseDate(ACCEPT_FORMATS[i], str);
					if (d) return d;
				} catch (e) {}
			}
			var t = Date.parse(str);
			return isNaN(t) ? null : new Date(t);
		}

		jQuery(pickerDiv).datepicker({
			dateFormat: DATE_FORMAT,
			onSelect: function (dateText) {
				el.value = dateText;
				el.dispatchEvent(new Event('change', { bubbles: true }));
			}
		});

		// jQuery UI wraps the calendar in a child .ui-datepicker-inline whose
		// styling needs the chained .tm-datepicker.ui-datepicker.* selectors
		// from tpmeta-fields.css. Any internal redraw (setDate, prev/next nav)
		// rebuilds that child via dpDiv.empty().append(), dropping the class —
		// a MutationObserver re-applies it on every regeneration so styling
		// persists for the life of the modal.
		function applyInnerClass() {
			var inner = pickerDiv.querySelector('.ui-datepicker-inline');
			if (inner && !inner.classList.contains('tm-datepicker')) {
				inner.classList.add('tm-datepicker');
			}
		}
		applyInnerClass();
		if (typeof MutationObserver !== 'undefined') {
			new MutationObserver(applyInnerClass).observe(pickerDiv, { childList: true });
		}

		// Parse whatever's in the input (accepts "May 14, 2026", "05/14/2026",
		// "2026-05-14", etc.), normalize the input to canonical format, and
		// reflect the date in the inline calendar. Used on init, after user
		// types, and after onSelect.
		var syncing = false;
		function syncCalendar() {
			if (syncing) return;
			var d = parseDateLenient(el.value);
			if (!d) return;
			syncing = true;
			try {
				el.value = jQuery.datepicker.formatDate(DATE_FORMAT, d);
				jQuery(pickerDiv).datepicker('setDate', d);
			} catch (e) {}
			syncing = false;
		}
		// Defer initial sync one tick — guarantees the picker's internal
		// state is fully initialised before setDate runs.
		setTimeout(syncCalendar, 0);

		el.addEventListener('input',  syncCalendar);
		el.addEventListener('change', syncCalendar);
		el.addEventListener('blur',   syncCalendar);
	}

	function wireSwitchConfig() {
		// 1) Re-render default picker when format toggles, preserving the
		//    truthy/falsy intent of the current selection.
		var formatRadios = document.querySelectorAll('input[name="tpmeta-switch-data-type"]');
		Array.prototype.forEach.call(formatRadios, function (r) {
			r.addEventListener('change', function () {
				var wrap = document.getElementById('tpmeta-switch-default-wrap');
				if (!wrap) return;
				var pickedTruthy = switchDefaultIsTruthy(readSwitchDefaultRaw());
				var nextBoolean  = (r.value === 'boolean');
				// Snap currentField.default to the matching value so re-render reflects it.
				wrap.innerHTML = renderSwitchDefaultPicker({ default: pickedTruthy ? (nextBoolean ? 'true' : 'on') : (nextBoolean ? 'false' : 'off') }, nextBoolean);
				wireSwitchDefaultActive();
			});
		});
		wireSwitchDefaultActive();
	}

	function wireSwitchDefaultActive() {
		// Toggle .is-active on the card whose radio is checked.
		var radios = document.querySelectorAll('input[name="tpmeta-switch-default"]');
		Array.prototype.forEach.call(radios, function (r) {
			r.addEventListener('change', function () {
				Array.prototype.forEach.call(radios, function (other) {
					var card = other.closest('.tpmeta-switch-default-card');
					if (card) card.classList.toggle('is-active', other.checked);
				});
			});
		});
	}

	function readSwitchDefaultRaw() {
		var picked = document.querySelector('input[name="tpmeta-switch-default"]:checked');
		return picked ? picked.value : '';
	}

	// ---------- Post Select config helpers ----------

	function renderPostSelectConfig(f) {
		var postTypes  = TPMetaBuilder.postTypes  || [];
		var taxonomies = TPMetaBuilder.taxonomies || [];

		var ptOptions = '<option value="">— Select post type —</option>';
		postTypes.forEach(function (pt) {
			var sel = (f.post_type === pt.value) ? ' selected' : '';
			ptOptions += '<option value="' + esc(pt.value) + '"' + sel + '>' + esc(pt.label) + ' (' + esc(pt.value) + ')</option>';
		});

		var taxOptions = '<option value="">— None —</option>';
		taxonomies.forEach(function (tax) {
			var sel = (f.taxonomy_filter && f.taxonomy_filter.taxonomy === tax.value) ? ' selected' : '';
			taxOptions += '<option value="' + esc(tax.value) + '"' + sel + '>' + esc(tax.label) + ' (' + esc(tax.value) + ')</option>';
		});

		var saveId   = (f.save_format !== 'slug') ? ' checked' : '';
		var saveSlug = (f.save_format === 'slug')  ? ' checked' : '';

		var metaRows = '';
		(f.meta_filters || []).forEach(function (mf, i) {
			metaRows += metaFilterRow(mf.key || '', mf.value || '', i);
		});

		var html = '<fieldset class="tpmeta-form-fieldset tpmeta-ps-fieldset">';
		html += '<legend><span class="dashicons dashicons-admin-post"></span> Post Select Configuration</legend>';

		// Post type
		html += '<div class="tpmeta-form-row">'
			+ '<label>Post Type</label>'
			+ '<select id="tpmeta-ps-post-type">' + ptOptions + '</select>'
			+ '<div class="description">Which post type to populate the select options from.</div>'
			+ '</div>';

		// Initial load limit
		html += '<div class="tpmeta-form-row">'
			+ '<label>Posts to pre-load</label>'
			+ '<input type="number" id="tpmeta-ps-initial-limit" value="' + esc(f.initial_limit || '20') + '" min="1" max="200" style="width:80px" />'
			+ '<div class="description">How many posts to show on first open. Extra posts load via live search (AJAX).</div>'
			+ '</div>';

		// Save format
		html += '<div class="tpmeta-form-row">'
			+ '<label>Save Format</label>'
			+ '<div class="tpmeta-ps-format-toggle">'
			+   '<label class="tpmeta-ps-radio"><input type="radio" name="tpmeta-ps-format" value="id"' + saveId + '> Save as <strong>ID</strong></label>'
			+   '<label class="tpmeta-ps-radio"><input type="radio" name="tpmeta-ps-format" value="slug"' + saveSlug + '> Save as <strong>Slug</strong></label>'
			+ '</div>'
			+ '<div class="description">IDs are stable; slugs are human-readable. Both display the post title in the select.</div>'
			+ '</div>';

		// Taxonomy filter
		html += '<div class="tpmeta-form-row">'
			+ '<label>Taxonomy Filter <span class="tpmeta-ps-optional">(optional)</span></label>'
			+ '<div class="tpmeta-ps-tax-row">'
			+   '<select id="tpmeta-ps-tax-taxonomy">' + taxOptions + '</select>'
			+   '<input type="text" id="tpmeta-ps-tax-term" value="' + esc((f.taxonomy_filter && f.taxonomy_filter.term) || '') + '" placeholder="term slug  (e.g. featured)" />'
			+ '</div>'
			+ '<div class="description">Only show posts that belong to this taxonomy term. Leave blank to skip.</div>'
			+ '</div>';

		// Meta filters
		html += '<div class="tpmeta-form-row">'
			+ '<label>Meta Filters <span class="tpmeta-ps-optional">(optional)</span></label>'
			+ '<ul class="tpmeta-form-list" id="tpmeta-ps-meta-list">' + metaRows + '</ul>'
			+ '<button type="button" class="tpmeta-form-add-row" id="tpmeta-ps-meta-add">+ Add meta filter</button>'
			+ '<div class="description">Only show posts where a custom field equals a given value — e.g. <code>type</code> = <code>canvas</code>. Multiple filters use AND logic.</div>'
			+ '</div>';

		html += '</fieldset>';
		return html;
	}

	function metaFilterRow(key, value) {
		return '<li class="tpmeta-form-list-row tpmeta-ps-meta-row">'
			+ '<input type="text" data-key="meta-key"   value="' + esc(key)   + '" placeholder="meta key  (e.g. type)" />'
			+ '<span class="tpmeta-ps-equals">=</span>'
			+ '<input type="text" data-key="meta-value" value="' + esc(value) + '" placeholder="value  (e.g. canvas)" />'
			+ '<button type="button" class="button button-link-delete" aria-label="remove">&times;</button>'
			+ '</li>';
	}

	function readPostSelectConfig() {
		var f = currentField;

		var ptEl = document.getElementById('tpmeta-ps-post-type');
		f.post_type = ptEl ? ptEl.value : '';

		var limitEl = document.getElementById('tpmeta-ps-initial-limit');
		f.initial_limit = limitEl ? (parseInt(limitEl.value, 10) || 20) : 20;

		var fmtEl = document.querySelector('input[name="tpmeta-ps-format"]:checked');
		f.save_format = fmtEl ? fmtEl.value : 'id';

		var taxTaxEl  = document.getElementById('tpmeta-ps-tax-taxonomy');
		var taxTermEl = document.getElementById('tpmeta-ps-tax-term');
		var taxTax    = taxTaxEl  ? taxTaxEl.value.trim()  : '';
		var taxTerm   = taxTermEl ? taxTermEl.value.trim() : '';
		if (taxTax) {
			f.taxonomy_filter = { taxonomy: taxTax, term: taxTerm };
		} else {
			delete f.taxonomy_filter;
		}

		var metaList = document.getElementById('tpmeta-ps-meta-list');
		if (metaList) {
			f.meta_filters = [];
			Array.prototype.forEach.call(metaList.querySelectorAll('.tpmeta-form-list-row'), function (row) {
				var k = row.querySelector('[data-key="meta-key"]').value.trim();
				var v = row.querySelector('[data-key="meta-value"]').value.trim();
				if (k) f.meta_filters.push({ key: k, value: v });
			});
			if (!f.meta_filters.length) delete f.meta_filters;
		}
	}

	// ---------- select_posts (metabox builder) config ----------
	// Mirrors the Options Builder's post_select config — same controls so the
	// metabox renderer can apply taxonomy/meta filters and honor save_format.
	// All keys are optional; missing keys preserve legacy behaviour (load all
	// posts of post_type, no filters, save IDs).

	function renderSelectPostsConfig(f) {
		var postTypes  = (window.TPMetaBuilder && TPMetaBuilder.postTypes)  || [];
		var taxonomies = (window.TPMetaBuilder && TPMetaBuilder.taxonomies) || [];

		var ptOptions = '<option value="">— Select post type —</option>';
		postTypes.forEach(function (pt) {
			var sel = (f.post_type === pt.value) ? ' selected' : '';
			ptOptions += '<option value="' + esc(pt.value) + '"' + sel + '>' + esc(pt.label) + ' (' + esc(pt.value) + ')</option>';
		});

		var taxOptions = '<option value="">— None —</option>';
		taxonomies.forEach(function (tax) {
			var sel = (f.taxonomy_filter && f.taxonomy_filter.taxonomy === tax.value) ? ' selected' : '';
			taxOptions += '<option value="' + esc(tax.value) + '"' + sel + '>' + esc(tax.label) + ' (' + esc(tax.value) + ')</option>';
		});

		var saveId   = (f.save_format !== 'slug') ? ' checked' : '';
		var saveSlug = (f.save_format === 'slug')  ? ' checked' : '';

		var metaRows = '';
		(f.meta_filters || []).forEach(function (mf, i) {
			metaRows += metaFilterRow(mf.key || '', mf.value || '', i);
		});

		var html = '<fieldset class="tpmeta-form-fieldset tpmeta-ps-fieldset">';
		html += '<legend><span class="dashicons dashicons-admin-post"></span> Post Select Configuration</legend>';

		// Post type
		html += '<div class="tpmeta-form-row">'
			+ '<label>Post Type</label>'
			+ '<select id="tpmeta-sp-post-type">' + ptOptions + '</select>'
			+ '<div class="description">Which post type to populate the select options from.</div>'
			+ '</div>';

		// Save format
		html += '<div class="tpmeta-form-row">'
			+ '<label>Save Format</label>'
			+ '<div class="tpmeta-ps-format-toggle">'
			+   '<label class="tpmeta-ps-radio"><input type="radio" name="tpmeta-sp-format" value="id"' + saveId + '> Save as <strong>ID</strong></label>'
			+   '<label class="tpmeta-ps-radio"><input type="radio" name="tpmeta-sp-format" value="slug"' + saveSlug + '> Save as <strong>Slug</strong></label>'
			+ '</div>'
			+ '<div class="description">IDs are stable; slugs are human-readable. Both display the post title in the select.</div>'
			+ '</div>';

		// Taxonomy filter
		html += '<div class="tpmeta-form-row">'
			+ '<label>Taxonomy Filter <span class="tpmeta-ps-optional">(optional)</span></label>'
			+ '<div class="tpmeta-ps-tax-row">'
			+   '<select id="tpmeta-sp-tax-taxonomy">' + taxOptions + '</select>'
			+   '<input type="text" id="tpmeta-sp-tax-term" value="' + esc((f.taxonomy_filter && f.taxonomy_filter.term) || '') + '" placeholder="term slug  (e.g. featured)" />'
			+ '</div>'
			+ '<div class="description">Only show posts that belong to this taxonomy term. Leave blank to skip.</div>'
			+ '</div>';

		// Meta filters
		html += '<div class="tpmeta-form-row">'
			+ '<label>Meta Filters <span class="tpmeta-ps-optional">(optional)</span></label>'
			+ '<ul class="tpmeta-form-list" id="tpmeta-sp-meta-list">' + metaRows + '</ul>'
			+ '<button type="button" class="tpmeta-form-add-row" id="tpmeta-sp-meta-add">+ Add meta filter</button>'
			+ '<div class="description">Only show posts where a custom field equals a given value — e.g. <code>type</code> = <code>canvas</code>. Multiple filters use AND logic.</div>'
			+ '</div>';

		html += '</fieldset>';
		return html;
	}

	function readSelectPostsConfig() {
		var f    = currentField;

		var ptEl = document.getElementById('tpmeta-sp-post-type');
		var pt   = ptEl ? ptEl.value : '';
		if (pt) {
			f.post_type = pt;
		} else {
			delete f.post_type;
		}

		var fmtEl = document.querySelector('input[name="tpmeta-sp-format"]:checked');
		var fmt   = fmtEl ? fmtEl.value : 'id';
		if (fmt === 'slug') {
			f.save_format = 'slug';
		} else {
			delete f.save_format;
		}

		var taxTaxEl  = document.getElementById('tpmeta-sp-tax-taxonomy');
		var taxTermEl = document.getElementById('tpmeta-sp-tax-term');
		var taxTax    = taxTaxEl  ? taxTaxEl.value.trim()  : '';
		var taxTerm   = taxTermEl ? taxTermEl.value.trim() : '';
		if (taxTax) {
			f.taxonomy_filter = { taxonomy: taxTax, term: taxTerm };
		} else {
			delete f.taxonomy_filter;
		}

		var metaList = document.getElementById('tpmeta-sp-meta-list');
		if (metaList) {
			f.meta_filters = [];
			Array.prototype.forEach.call(metaList.querySelectorAll('.tpmeta-form-list-row'), function (row) {
				var k = row.querySelector('[data-key="meta-key"]').value.trim();
				var v = row.querySelector('[data-key="meta-value"]').value.trim();
				if (k) f.meta_filters.push({ key: k, value: v });
			});
			if (!f.meta_filters.length) delete f.meta_filters;
		}
	}

	// ── Repeater sub-fields editor ─────────────────────────────────────────

	var RSF_TYPES = [
		{ v: 'text',            l: 'Text' },
		{ v: 'textarea',        l: 'Textarea' },
		{ v: 'select',          l: 'Select (dropdown)' },
		{ v: 'radio_buttonset', l: 'Radio Buttons' },
		{ v: 'checkbox',        l: 'Checkbox' },
		{ v: 'multicheck',      l: 'Multi Checkbox' },
		{ v: 'radio_image',     l: 'Radio Image' },
		{ v: 'switch',          l: 'Toggle Switch' },
		{ v: 'colorpicker',     l: 'Color Picker' },
		{ v: 'color_gradient',  l: 'Color Gradient' },
		{ v: 'datepicker',      l: 'Date Picker' },
		{ v: 'image',           l: 'Image / Media' },
		{ v: 'editor',          l: 'Rich Text Editor' },
		{ v: 'code',            l: 'Code Editor' },
		{ v: 'post_select',     l: 'Post Select' },
		{ v: 'dimension',       l: 'Dimension' },
		{ v: 'spacing',         l: 'Spacing' },
		{ v: 'typography',      l: 'Typography' },
		{ v: 'multicolor',      l: 'Multi Color' },
	];

	var RSF_OPTION_TYPES = ['select', 'radio_buttonset', 'checkbox', 'multicheck', 'radio_image', 'multicolor'];

	// Both names mean "post-picker" — Options Builder uses "post_select", the
	// Metabox Builder uses "select_posts" (different renderer expectations).
	var RSF_POSTSELECT_TYPES = ['post_select', 'select_posts'];

	// Returns the canonical post-select type for the current builder context.
	// Metabox Builder loads window.TPMFBuilder; Options Builder does not.
	function rsfPostSelectTypeKey() {
		return (typeof window.TPMFBuilder !== 'undefined') ? 'select_posts' : 'post_select';
	}

	function renderRepeaterSubFields(f) {
		var sfs = Array.isArray(f.fields) ? f.fields : [];
		var rowsHtml = sfs.map(function (sf, i) { return rsfRow(sf, i); }).join('');

		return '<fieldset class="tpmeta-form-fieldset tpmeta-rsf-wrap">'
			+ '<legend><span class="dashicons dashicons-list-view"></span> Sub-Fields'
			+ ' <span class="tpmeta-rsf-count" id="tpmeta-rsf-count">' + sfs.length + '</span></legend>'
			+ '<p class="description tpmeta-rsf-intro">Define the fields that appear in each repeater row — each row is saved as one data object.</p>'
			+ '<div class="tpmeta-rsf-col-heads">'
			+   '<span class="tpmeta-rsf-ch-type">Type</span>'
			+   '<span class="tpmeta-rsf-ch-id">Field ID</span>'
			+   '<span class="tpmeta-rsf-ch-label">Label</span>'
			+   '<span class="tpmeta-rsf-ch-default">Default</span>'
			+   '<span class="tpmeta-rsf-ch-cols" title="Column span within the repeater grid">Cols</span>'
			+ '</div>'
			+ '<div class="tpmeta-rsf-list" id="tpmeta-rsf-list">'
			+ (rowsHtml || '<p class="tpmeta-rsf-empty-hint">No sub-fields yet. Click "+ Add Sub-Field" to start.</p>')
			+ '</div>'
			+ '<button type="button" class="tpmeta-rsf-add-btn" id="tpmeta-rsf-add">'
			+   '<span class="dashicons dashicons-plus-alt2"></span> Add Sub-Field'
			+ '</button>'
			+ '</fieldset>';
	}

	function rsfTypeOpts(current) {
		var ctxKey = rsfPostSelectTypeKey();
		// Auto-correct stale post-select type values (e.g. an old metabox repeater
		// sub-field with the Options Builder's 'post_select' type → 'select_posts')
		// so the dropdown shows the right entry as selected.
		if (RSF_POSTSELECT_TYPES.indexOf(current) !== -1 && current !== ctxKey) {
			current = ctxKey;
		}
		return RSF_TYPES.map(function (t) {
			var v = (t.v === 'post_select') ? ctxKey : t.v;
			return '<option value="' + v + '"' + (v === current ? ' selected' : '') + '>' + t.l + '</option>';
		}).join('');
	}

	// Inline Post Type panel for repeater sub-fields whose type is
	// post_select / select_posts. Mirrors the structure of .tpmeta-rsf-opts so
	// existing CSS spacing applies. Includes optional taxonomy/meta filters and
	// save_format to match the Options Builder's post_select configuration.
	// All filter keys are optional — when absent the metabox renderer falls
	// back to "load all posts of post_type, save IDs", matching legacy output.
	function rsfPostTypePanel(sf) {
		// Backward-compat: callers that pass a string (legacy) get post_type only.
		if (typeof sf === 'string') sf = { post_type: sf };
		sf = sf || {};

		// Per-render unique radio group name so multiple post-select sub-field
		// rows don't share a global group (which would let two radios stay
		// checked at once across rows, or — worse — checking a radio in one row
		// would uncheck the selection in another).
		var fmtGroup = 'tpmeta-rsf-fmt-' + Math.random().toString(36).slice(2, 10);

		var postTypes  = (window.TPMetaBuilder && TPMetaBuilder.postTypes)  || [];
		var taxonomies = (window.TPMetaBuilder && TPMetaBuilder.taxonomies) || [];

		var ptOpts = '<option value="">— Select post type —</option>';
		postTypes.forEach(function (pt) {
			var sel = (sf.post_type === pt.value) ? ' selected' : '';
			ptOpts += '<option value="' + esc(pt.value) + '"' + sel + '>'
				+ esc(pt.label) + ' (' + esc(pt.value) + ')</option>';
		});

		var taxOpts = '<option value="">— None —</option>';
		taxonomies.forEach(function (tax) {
			var sel = (sf.taxonomy_filter && sf.taxonomy_filter.taxonomy === tax.value) ? ' selected' : '';
			taxOpts += '<option value="' + esc(tax.value) + '"' + sel + '>'
				+ esc(tax.label) + ' (' + esc(tax.value) + ')</option>';
		});

		var saveId   = (sf.save_format !== 'slug') ? ' checked' : '';
		var saveSlug = (sf.save_format === 'slug')  ? ' checked' : '';

		var metaRowsHtml = '';
		(sf.meta_filters || []).forEach(function (mf) {
			metaRowsHtml += rsfMetaFilterRow(mf.key || '', mf.value || '');
		});

		return '<div class="tpmeta-rsf-postsel tpmeta-rsf-opts">'
			+ '<p class="tpmeta-rsf-opts-label">Post Type <span>(which post type to populate the dropdown from)</span></p>'
			+ '<select class="tpmeta-rsf-postsel-pt">' + ptOpts + '</select>'

			+ '<p class="tpmeta-rsf-opts-label">Save Format</p>'
			+ '<div class="tpmeta-ps-format-toggle">'
			+   '<label class="tpmeta-ps-radio"><input type="radio" name="' + fmtGroup + '" class="tpmeta-rsf-postsel-fmt" value="id"' + saveId + '> Save as <strong>ID</strong></label>'
			+   '<label class="tpmeta-ps-radio"><input type="radio" name="' + fmtGroup + '" class="tpmeta-rsf-postsel-fmt" value="slug"' + saveSlug + '> Save as <strong>Slug</strong></label>'
			+ '</div>'

			+ '<p class="tpmeta-rsf-opts-label">Taxonomy Filter <span>(optional — narrow by term slug)</span></p>'
			+ '<div class="tpmeta-ps-tax-row">'
			+   '<select class="tpmeta-rsf-postsel-tax">' + taxOpts + '</select>'
			+   '<input type="text" class="tpmeta-rsf-postsel-term" value="' + esc((sf.taxonomy_filter && sf.taxonomy_filter.term) || '') + '" placeholder="term slug  (e.g. featured)" />'
			+ '</div>'

			+ '<p class="tpmeta-rsf-opts-label">Meta Filters <span>(optional — AND logic)</span></p>'
			+ '<div class="tpmeta-rsf-postsel-meta-list">' + metaRowsHtml + '</div>'
			+ '<button type="button" class="tpmeta-form-add-row tpmeta-rsf-postsel-meta-add">+ Add meta filter</button>'

			+ '</div>';
	}

	function rsfMetaFilterRow(key, value) {
		return '<div class="tpmeta-form-list-row tpmeta-ps-meta-row">'
			+ '<input type="text" data-key="meta-key"   value="' + esc(key)   + '" placeholder="meta key  (e.g. type)" />'
			+ '<span class="tpmeta-ps-equals">=</span>'
			+ '<input type="text" data-key="meta-value" value="' + esc(value) + '" placeholder="value  (e.g. canvas)" />'
			+ '<button type="button" class="button button-link-delete" aria-label="remove">&times;</button>'
			+ '</div>';
	}

	function rsfOptRows(options, isMulticolor) {
		var vPH = isMulticolor ? 'key (e.g. primary)' : 'value';
		var lPH = isMulticolor ? 'CSS color (e.g. red, #3362FF)' : 'label';
		return (Array.isArray(options) ? options : []).map(function (o) {
			return '<div class="tpmeta-rsf-opt-row">'
				+ '<input type="text" class="tpmeta-rsf-opt-v" placeholder="' + vPH + '" value="' + esc(o.value || '') + '" />'
				+ '<input type="text" class="tpmeta-rsf-opt-l" placeholder="' + lPH + '" value="' + esc(o.label || '') + '" />'
				+ '<button type="button" class="tpmeta-rsf-opt-del" aria-label="Remove option">&times;</button>'
				+ '</div>';
		}).join('');
	}

	function rsfRow(sf, i) {
		var sfType  = sf.type || 'text';
		var sfCols  = parseInt(sf.columns || 1, 10);
		if (sfCols < 1 || sfCols > 3) sfCols = 1;
		var isMC    = sfType === 'multicolor';
		var hasOpts = RSF_OPTION_TYPES.indexOf(sfType) !== -1;

		var optLabel  = isMC ? 'Color Slots <span>(key → label)</span>'   : 'Options <span>(value → label)</span>';
		var optAddBtn = isMC ? '+ Add color slot' : '+ Add option';
		var defVal    = sf['default'] != null ? String(sf['default']) : '';
		// For multicolor, default is a JSON object — show a JSON hint when empty.
		var defPlaceholder = isMC ? '{"key":"#hex"} (JSON)' : 'Default';
		// If the default is an already-decoded object (from a re-opened saved field),
		// serialise it back so the text input shows something readable.
		if ( isMC && sf['default'] && typeof sf['default'] === 'object' ) {
			defVal = JSON.stringify(sf['default']);
		}

		var optPanel = hasOpts
			? '<div class="tpmeta-rsf-opts">'
			+   '<p class="tpmeta-rsf-opts-label">' + optLabel + '</p>'
			+   '<div class="tpmeta-rsf-opts-rows">' + rsfOptRows(sf.options || [], isMC) + '</div>'
			+   '<button type="button" class="tpmeta-rsf-opt-add">' + optAddBtn + '</button>'
			+ '</div>'
			: '';

		// Post Type config panel for post-select-style sub-fields.
		var psPanel = (RSF_POSTSELECT_TYPES.indexOf(sfType) !== -1)
			? rsfPostTypePanel(sf)
			: '';

		// Image config panel for image sub-fields — return_format selector.
		var imgPanel = (sfType === 'image')
			? rsfImagePanel(sf)
			: '';

		// Switch config panel — stored-format toggle + radio-card default.
		// Hides the row's default text input (it's repurposed as a hidden
		// carrier so existing readRepeaterSubFields() picks it up unchanged).
		var swPanel = (sfType === 'switch')
			? rsfSwitchPanel(sf)
			: '';

		// Typography config panel — composite default editor. The plain text
		// default cell can't represent a typography object, so it's hidden and
		// the object is captured from this panel instead.
		var typoPanel = (sfType === 'typography')
			? rsfTypographyPanel(sf)
			: '';

		// CSS output panel — optional frontend CSS generation for color fields.
		var cssPanel = (sfType === 'colorpicker' || sfType === 'color_gradient')
			? rsfCssOutputPanel(sf)
			: '';

		var colsSelect = '<select class="tpmeta-rsf-cols" data-key="columns" title="Column span">'
			+ '<option value="1"' + (sfCols === 1 ? ' selected' : '') + '>1</option>'
			+ '<option value="2"' + (sfCols === 2 ? ' selected' : '') + '>2</option>'
			+ '<option value="3"' + (sfCols === 3 ? ' selected' : '') + '>3</option>'
			+ '</select>';

		// For switch sub-fields, the default text input becomes a hidden carrier
		// (kept so readRepeaterSubFields keeps reading the same `[data-key="default"]`).
		// The visible picker lives inside `rsfSwitchPanel`.
		var defInputClass = 'tpmeta-rsf-default';
		var defInputType  = 'text';
		var defInputAttr  = '';
		if (sfType === 'switch') {
			defInputType = 'hidden';
			defInputAttr = ' aria-hidden="true"';
			// Normalise the carrier value to the chosen format so PHP sanitize gets a clean token.
			var swIsBoolean = (sf.data_type === 'boolean');
			var truthy = switchDefaultIsTruthy(sf['default']);
			defVal = truthy ? (swIsBoolean ? 'true' : 'on') : (swIsBoolean ? 'false' : 'off');
		} else if (sfType === 'typography') {
			// Typography default is captured as an object from rsfTypographyPanel,
			// so the inline cell carries nothing (String(object) would be junk).
			defInputType = 'hidden';
			defInputAttr = ' aria-hidden="true"';
			defVal = '';
		}

		return '<div class="tpmeta-rsf-row" data-rsf-i="' + i + '">'
			+ '<div class="tpmeta-rsf-main">'
			+   '<span class="tpmeta-rsf-grip" aria-hidden="true">'
			+     '<svg width="8" height="14" viewBox="0 0 8 14" fill="currentColor"><circle cx="2" cy="2" r="1.4"/><circle cx="6" cy="2" r="1.4"/><circle cx="2" cy="6" r="1.4"/><circle cx="6" cy="6" r="1.4"/><circle cx="2" cy="10" r="1.4"/><circle cx="6" cy="10" r="1.4"/></svg>'
			+   '</span>'
			+   '<select class="tpmeta-rsf-type" data-key="type">' + rsfTypeOpts(sfType) + '</select>'
			+   '<input type="text" class="tpmeta-rsf-id" data-key="id" value="' + esc(sf.id || '') + '" placeholder="field_id" />'
			+   '<input type="text" class="tpmeta-rsf-label" data-key="label" value="' + esc(sf.label || '') + '" placeholder="Label" />'
			+   '<input type="' + defInputType + '" class="' + defInputClass + '" data-key="default" value="' + esc(defVal) + '" placeholder="' + defPlaceholder + '"' + defInputAttr + ' />'
			+   colsSelect
			+   '<button type="button" class="tpmeta-rsf-del" aria-label="Remove">&times;</button>'
			+ '</div>'
			+ optPanel
			+ psPanel
			+ imgPanel
			+ swPanel
			+ typoPanel
			+ cssPanel
			+ '</div>';
	}

	/**
	 * Inline switch config panel for repeater sub-fields:
	 * stored value format toggle + radio-card default value picker.
	 *
	 * Contract with the rest of the row:
	 *   - The visible radio cards drive the selection.
	 *   - On change, we mirror the picked value into the hidden
	 *     `[data-key="default"]` carrier inside the same row, so
	 *     readRepeaterSubFields() (which only reads that input) keeps
	 *     working unchanged.
	 *   - data_type is read separately via `.tpmeta-rsf-sw-dt` radios.
	 */
	function rsfSwitchPanel(sf) {
		var dataType  = (sf && sf.data_type === 'boolean') ? 'boolean' : 'string';
		var isBoolean = (dataType === 'boolean');
		// Per-render unique radio group names so multiple switch sub-fields
		// don't share state across rows.
		var dtGroup = 'tpmeta-rsf-sw-dt-' + Math.random().toString(36).slice(2, 8);
		var dvGroup = 'tpmeta-rsf-sw-dv-' + Math.random().toString(36).slice(2, 8);

		var truthy   = switchDefaultIsTruthy(sf && sf['default']);
		var trueVal  = isBoolean ? 'true'  : 'on';
		var falseVal = isBoolean ? 'false' : 'off';
		var trueDesc  = isBoolean ? 'PHP boolean true'  : 'String "on"';
		var falseDesc = isBoolean ? 'PHP boolean false' : 'String "off"';

		function dvCard(value, label, desc, active) {
			return '<label class="tpmeta-switch-default-card' + (active ? ' is-active' : '') + '">'
				+ '<input type="radio" class="tpmeta-rsf-sw-dv" name="' + dvGroup + '" value="' + esc(value) + '"' + (active ? ' checked' : '') + '>'
				+ '<span class="tpmeta-switch-default-card-pill" aria-hidden="true"></span>'
				+ '<span class="tpmeta-switch-default-card-body">'
				+   '<span class="tpmeta-switch-default-card-title">' + esc(label) + '</span>'
				+   '<span class="tpmeta-switch-default-card-sub">' + esc(desc) + '</span>'
				+ '</span>'
				+ '</label>';
		}

		return '<div class="tpmeta-rsf-sw">'
			+   '<p class="tpmeta-rsf-sw-label">Stored value format <span>(what is saved &amp; returned)</span></p>'
			+   '<div class="tpmeta-ps-format-toggle">'
			+     '<label class="tpmeta-ps-radio"><input type="radio" class="tpmeta-rsf-sw-dt" name="' + dtGroup + '" value="string"' + (!isBoolean ? ' checked' : '') + '> String &nbsp;<code>on</code> / <code>off</code></label>'
			+     '<label class="tpmeta-ps-radio"><input type="radio" class="tpmeta-rsf-sw-dt" name="' + dtGroup + '" value="boolean"' + (isBoolean ? ' checked' : '') + '> Boolean &nbsp;<code>true</code> / <code>false</code></label>'
			+   '</div>'
			+   '<p class="tpmeta-rsf-sw-label">Default value</p>'
			+   '<div class="tpmeta-switch-default-grid tpmeta-rsf-sw-grid">'
			+     dvCard(falseVal, falseVal, falseDesc, !truthy)
			+     dvCard(trueVal,  trueVal,  trueDesc,   truthy)
			+   '</div>'
			+ '</div>';
	}

	/**
	 * Inline Return Format selector for image sub-fields.
	 * Compact 3-radio segment so it doesn't blow up the row height.
	 */
	function rsfImagePanel(sf) {
		var rf = (sf && sf.return_format) ? String(sf.return_format) : 'array';
		// Per-render unique radio group name so multiple image sub-fields don't share state.
		var groupName = 'tpmeta-rsf-img-rf-' + Math.random().toString(36).slice(2, 8);
		function opt(val, title, hint) {
			var checked = (rf === val);
			return '<label class="tpmeta-rsf-img-card' + (checked ? ' is-active' : '') + '">'
				+   '<input type="radio" class="tpmeta-rsf-img-rf" name="' + groupName + '" value="' + esc(val) + '"' + (checked ? ' checked' : '') + ' />'
				+   '<span class="tpmeta-rsf-img-card-title">' + esc(title) + '</span>'
				+   '<code class="tpmeta-rsf-img-card-hint">' + esc(hint) + '</code>'
				+ '</label>';
		}
		return '<div class="tpmeta-rsf-img">'
			+   '<p class="tpmeta-rsf-img-label">Return Format <span>(what get_theme_mod / tpmeta_field returns)</span></p>'
			+   '<div class="tpmeta-rsf-img-grid">'
			+     opt('array', 'Array',         '{ id, url, alt }')
			+     opt('id',    'Attachment ID', '123')
			+     opt('url',   'URL string',    'https://…')
			+   '</div>'
			+ '</div>';
	}

	// Popular Google fonts (mirrors the curated list in the typography template)
	// so the default-family dropdown shows real choices without an API call.
	var TPMETA_RSF_GF = [
		'Abel','Abril Fatface','Alegreya','Alegreya Sans','Almarai','Alfa Slab One',
		'Arimo','Arvo','Assistant','Barlow','Barlow Condensed','Bebas Neue',
		'Bitter','Bree Serif','Cabin','Cairo','Cantarell','Catamaran','Caveat',
		'Cinzel','Comfortaa','Cormorant Garamond','Crimson Text','Dancing Script',
		'DM Sans','DM Serif Display','Dosis','EB Garamond','Exo','Exo 2',
		'Fira Code','Fira Sans','Fjalla One','Frank Ruhl Libre','Fredoka One',
		'Geologica','Gilda Display','Gothic A1','Heebo','IBM Plex Mono',
		'IBM Plex Sans','IBM Plex Serif','Inter','Josefin Sans','Josefin Slab',
		'Jost','Kanit','Kaushan Script','Lato','Libre Baskerville','Libre Franklin',
		'Lora','Merriweather','Merriweather Sans','Montserrat','Mukta','Mulish',
		'Nanum Gothic','Noto Sans','Noto Serif','Nunito','Nunito Sans','Open Sans',
		'Oswald','Outfit','Overpass','Oxygen','Pacifico','Playfair Display',
		'Poppins','PT Sans','PT Serif','Quattrocento','Quicksand','Raleway',
		'Righteous','Roboto','Roboto Condensed','Roboto Mono','Roboto Slab',
		'Rubik','Sacramento','Satisfy','Signika','Slabo 27px','Source Code Pro',
		'Source Sans 3','Source Serif 4','Space Grotesk','Space Mono','Teko',
		'Titillium Web','Ubuntu','Ubuntu Mono','Unbounded','Urbanist',
		'Varela Round','Vollkorn','Work Sans','Yanone Kaffeesatz','Yrsa','Zilla Slab'
	];

	// System font stacks (value = full stack, label = friendly name).
	var TPMETA_RSF_SYS = [
		{ v: 'Arial, Helvetica, sans-serif',            l: 'Arial' },
		{ v: "'Arial Black', Gadget, sans-serif",       l: 'Arial Black' },
		{ v: "Georgia, 'Times New Roman', serif",       l: 'Georgia' },
		{ v: "'Times New Roman', Times, serif",         l: 'Times New Roman' },
		{ v: "'Trebuchet MS', Helvetica, sans-serif",   l: 'Trebuchet MS' },
		{ v: 'Verdana, Geneva, sans-serif',             l: 'Verdana' },
		{ v: 'Tahoma, Geneva, sans-serif',              l: 'Tahoma' },
		{ v: 'Impact, Charcoal, sans-serif',            l: 'Impact' },
		{ v: "'Courier New', Courier, monospace",       l: 'Courier New' },
		{ v: "'Lucida Console', Monaco, monospace",     l: 'Lucida Console' },
		{ v: 'system-ui, -apple-system, sans-serif',    l: 'System UI' },
		{ v: 'ui-sans-serif, system-ui, sans-serif',    l: 'UI Sans' },
		{ v: 'ui-serif, Georgia, serif',                l: 'UI Serif' },
		{ v: "ui-monospace, 'Cascadia Code', monospace", l: 'UI Mono' }
	];

	/**
	 * Source-aware "Font family" control for the typography default editor:
	 *   google → <select> of popular Google fonts
	 *   system → <select> of system font stacks
	 *   custom → free-text input (the user types their uploaded family name)
	 * An unknown saved value is preserved as an extra selected option.
	 */
	function rsfTypoFamilyControl(source, current) {
		current = current || '';
		if (source === 'custom') {
			return '<input type="text" class="tpmeta-rsf-typo-f" data-tk="font_family" value="' + esc(current) + '" placeholder="e.g. MyBrandFont" />';
		}
		var list  = (source === 'system')
			? TPMETA_RSF_SYS
			: TPMETA_RSF_GF.map(function (f) { return { v: f, l: f }; });
		var found = false;
		var opts  = '<option value="">— Default —</option>';
		list.forEach(function (it) {
			var sel = (it.v === current);
			if (sel) found = true;
			opts += '<option value="' + esc(it.v) + '"' + (sel ? ' selected' : '') + '>' + esc(it.l) + '</option>';
		});
		if (current && !found) {
			opts += '<option value="' + esc(current) + '" selected>' + esc(current) + ' (saved)</option>';
		}
		return '<select class="tpmeta-rsf-typo-f" data-tk="font_family">' + opts + '</select>';
	}

	/**
	 * Inline default-value editor for typography sub-fields. A typography value
	 * is a composite object, so a single free-text "Default" cell can't express
	 * it — this panel collects each sub-property. Values are read by
	 * readRepeaterSubFields() from the [data-tk] inputs and stored as an OBJECT
	 * on sf.default, which the typography template consumes via $field['default']
	 * (so new rows / empty values render with these defaults, and — when an
	 * "Apply to selectors" default is set — emit frontend CSS).
	 */
	function rsfTypographyPanel(sf) {
		var d = (sf && sf['default'] && typeof sf['default'] === 'object') ? sf['default'] : {};
		function g(k) { return d[k] != null ? String(d[k]) : ''; }
		function opt(v, label, cur) { return '<option value="' + esc(v) + '"' + (cur === v ? ' selected' : '') + '>' + esc(label) + '</option>'; }
		function tf(label, ctrl) {
			return '<label class="tpmeta-rsf-typo-cell"><span class="tpmeta-rsf-typo-lbl">' + label + '</span>' + ctrl + '</label>';
		}

		var src    = g('font_source') || 'google';
		var unit   = g('font_size_unit') || 'px';
		var weight = g('font_weight');
		var style  = g('font_style') || 'normal';
		var tx     = g('text_transform') || 'none';

		var wOpts = ['', '100', '200', '300', '400', '500', '600', '700', '800', '900']
			.map(function (w) { return opt(w, w === '' ? '— Default —' : w, weight); }).join('');
		var uOpts = ['px', 'em', 'rem', 'vw', '%'].map(function (u) { return opt(u, u, unit); }).join('');
		var txOpts = ['none', 'capitalize', 'uppercase', 'lowercase'].map(function (t) { return opt(t, t, tx); }).join('');

		return '<div class="tpmeta-rsf-typo">'
			+   '<p class="tpmeta-rsf-typo-label">Default typography <span>(applied to new rows &amp; when a row leaves it empty)</span></p>'
			+   '<div class="tpmeta-rsf-typo-grid">'
			+     tf('Source', '<select class="tpmeta-rsf-typo-f tpmeta-rsf-typo-src" data-tk="font_source">' + opt('google', 'Google', src) + opt('system', 'System', src) + opt('custom', 'Custom', src) + '</select>')
			+     tf('Font family', '<span class="tpmeta-rsf-typo-fam">' + rsfTypoFamilyControl(src, g('font_family')) + '</span>')
			+     tf('Font URL', '<input type="text" class="tpmeta-rsf-typo-f" data-tk="font_url" value="' + esc(g('font_url')) + '" placeholder="custom only — https://…/font.woff2" />')
			+     tf('Size', '<span class="tpmeta-rsf-typo-size"><input type="number" class="tpmeta-rsf-typo-f" data-tk="font_size" value="' + esc(g('font_size')) + '" min="0" step="1" placeholder="16" /><select class="tpmeta-rsf-typo-f" data-tk="font_size_unit">' + uOpts + '</select></span>')
			+     tf('Weight', '<select class="tpmeta-rsf-typo-f" data-tk="font_weight">' + wOpts + '</select>')
			+     tf('Style', '<select class="tpmeta-rsf-typo-f" data-tk="font_style">' + opt('normal', 'Normal', style) + opt('italic', 'Italic', style) + '</select>')
			+     tf('Line height', '<input type="text" class="tpmeta-rsf-typo-f" data-tk="line_height" value="' + esc(g('line_height')) + '" placeholder="1.6" />')
			+     tf('Letter spacing', '<input type="text" class="tpmeta-rsf-typo-f" data-tk="letter_spacing" value="' + esc(g('letter_spacing')) + '" placeholder="0px" />')
			+     tf('Transform', '<select class="tpmeta-rsf-typo-f" data-tk="text_transform">' + txOpts + '</select>')
			+     tf('Color', '<input type="text" class="tpmeta-rsf-typo-f" data-tk="color" value="' + esc(g('color')) + '" placeholder="#000000" />')
			+     tf('Apply to selectors', '<input type="text" class="tpmeta-rsf-typo-f" data-tk="selectors" value="' + esc(g('selectors')) + '" placeholder="h1, .title (enables frontend CSS)" />')
			+   '</div>'
			+ '</div>';
	}

	function wireRepeaterSubFields() {
		var list   = document.getElementById('tpmeta-rsf-list');
		var addBtn = document.getElementById('tpmeta-rsf-add');
		if (!list) return;

		// NEW: drag-to-sort sub-field rows via the grip handle.
		// Sortable (SortableJS) is already loaded on builder pages.
		if (typeof Sortable !== 'undefined') {
			Sortable.create(list, {
				handle:    '.tpmeta-rsf-grip',
				animation: 150,
				ghostClass: 'tpmeta-rsf-row--ghost',
				onEnd: function () { updateRSFCount(); },
			});
		}

		// Wire type-change → toggle options panel on each existing row
		Array.prototype.forEach.call(list.querySelectorAll('.tpmeta-rsf-type'), function (sel) {
			wireRSFTypeSelect(sel);
		});

		// Auto-fill ID from label when ID is still untouched
		list.addEventListener('input', function (e) {
			if (!e.target.classList.contains('tpmeta-rsf-label')) return;
			var row     = e.target.closest('.tpmeta-rsf-row');
			var idInput = row && row.querySelector('.tpmeta-rsf-id');
			if (idInput && !idInput.dataset.dirty) {
				idInput.value = e.target.value.toLowerCase()
					.replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '').slice(0, 48);
			}
		});

		// Mark ID as user-edited once they type in it
		list.addEventListener('change', function (e) {
			if (e.target.classList.contains('tpmeta-rsf-id') && e.target.value.trim()) {
				e.target.dataset.dirty = '1';
			}

			// Image sub-field return_format radios: keep .is-active in sync
			// across siblings in the same group so styling reflects the active
			// choice (the initial render seeds is-active on one card; without
			// this it would never move).
			if (e.target.classList.contains('tpmeta-rsf-img-rf')) {
				var groupName = e.target.name;
				var siblings  = list.querySelectorAll('.tpmeta-rsf-img-rf[name="' + groupName + '"]');
				siblings.forEach(function (r) {
					var card = r.closest('.tpmeta-rsf-img-card');
					if (card) card.classList.toggle('is-active', r.checked);
				});
			}

			// Typography default Source change → rebuild the Font family control
			// (Google dropdown / System dropdown / custom text), preserving the
			// current value if it remains valid for the new source.
			if (e.target.classList.contains('tpmeta-rsf-typo-src')) {
				var typoPanel = e.target.closest('.tpmeta-rsf-typo');
				var famWrap   = typoPanel && typoPanel.querySelector('.tpmeta-rsf-typo-fam');
				if (famWrap) {
					var curEl  = famWrap.querySelector('[data-tk="font_family"]');
					var curVal = curEl ? curEl.value : '';
					famWrap.innerHTML = rsfTypoFamilyControl(e.target.value, curVal);
				}
			}

			// Switch sub-field — default value picker: mirror the chosen
			// value into the hidden carrier and toggle .is-active on cards.
			if (e.target.classList.contains('tpmeta-rsf-sw-dv')) {
				var dvGroup = e.target.name;
				var dvSibs  = list.querySelectorAll('.tpmeta-rsf-sw-dv[name="' + dvGroup + '"]');
				dvSibs.forEach(function (r) {
					var card = r.closest('.tpmeta-switch-default-card');
					if (card) card.classList.toggle('is-active', r.checked);
				});
				var swRow      = e.target.closest('.tpmeta-rsf-row');
				var defCarrier = swRow && swRow.querySelector('[data-key="default"]');
				if (defCarrier) defCarrier.value = e.target.value;
			}

			// Switch sub-field — stored format toggle: re-render the default
			// picker into the matching token set, preserving truthy intent,
			// and update the hidden carrier accordingly.
			if (e.target.classList.contains('tpmeta-rsf-sw-dt')) {
				var dtRow      = e.target.closest('.tpmeta-rsf-row');
				if (!dtRow) return;
				var grid       = dtRow.querySelector('.tpmeta-rsf-sw-grid');
				var dvCarrier  = dtRow.querySelector('[data-key="default"]');
				var checkedDV  = dtRow.querySelector('.tpmeta-rsf-sw-dv:checked');
				var truthy     = switchDefaultIsTruthy(checkedDV ? checkedDV.value : (dvCarrier ? dvCarrier.value : ''));
				var nextBool   = (e.target.value === 'boolean');
				if (grid) {
					var temp = document.createElement('div');
					temp.innerHTML = rsfSwitchPanel({ data_type: nextBool ? 'boolean' : 'string', 'default': truthy ? (nextBool ? 'true' : 'on') : (nextBool ? 'false' : 'off') });
					var newGrid = temp.querySelector('.tpmeta-rsf-sw-grid');
					if (newGrid) grid.replaceWith(newGrid);
				}
				if (dvCarrier) dvCarrier.value = truthy ? (nextBool ? 'true' : 'on') : (nextBool ? 'false' : 'off');
			}
		});

		// Delegated: delete row, delete option, add option
		list.addEventListener('click', function (e) {
			if (e.target.closest('.tpmeta-rsf-del')) {
				e.target.closest('.tpmeta-rsf-row').remove();
				updateRSFCount();
				return;
			}
			if (e.target.closest('.tpmeta-rsf-opt-del')) {
				e.target.closest('.tpmeta-rsf-opt-row').remove();
				return;
			}
			var addOpt = e.target.closest('.tpmeta-rsf-opt-add');
			if (addOpt) {
				var optRows = addOpt.previousElementSibling;
				if (optRows && optRows.classList.contains('tpmeta-rsf-opts-rows')) {
					var sfRow  = addOpt.closest('.tpmeta-rsf-row');
					var sfType = sfRow ? sfRow.querySelector('[data-key="type"]').value : '';
					optRows.insertAdjacentHTML('beforeend', rsfOptRows([{ value: '', label: '' }], sfType === 'multicolor'));
				}
				return;
			}
			// "+ Add meta filter" inside a post-select sub-field panel.
			var addMeta = e.target.closest('.tpmeta-rsf-postsel-meta-add');
			if (addMeta) {
				var metaList = addMeta.previousElementSibling;
				if (metaList && metaList.classList.contains('tpmeta-rsf-postsel-meta-list')) {
					metaList.insertAdjacentHTML('beforeend', rsfMetaFilterRow('', ''));
				}
			}
		});

		// Add new sub-field row
		if (addBtn) {
			addBtn.addEventListener('click', function () {
				// Remove empty-hint paragraph if present
				var hint = list.querySelector('.tpmeta-rsf-empty-hint');
				if (hint) hint.remove();
				var idx = list.querySelectorAll('.tpmeta-rsf-row').length;
				list.insertAdjacentHTML('beforeend', rsfRow({ id: '', type: 'text', label: '', 'default': '' }, idx));
				updateRSFCount();
				var newRow = list.lastElementChild;
				wireRSFTypeSelect(newRow.querySelector('.tpmeta-rsf-type'));
				var newId = newRow.querySelector('.tpmeta-rsf-label');
				if (newId) newId.focus();
			});
		}
	}

	function wireRSFTypeSelect(sel) {
		if (!sel || sel._rsfWired) return;
		sel._rsfWired = true;
		sel.addEventListener('change', function () {
			var row      = sel.closest('.tpmeta-rsf-row');
			var sfType   = sel.value;
			var isMC     = sfType === 'multicolor';
			var hasOpts  = RSF_OPTION_TYPES.indexOf(sfType) !== -1;
			var existing = row.querySelector('.tpmeta-rsf-opts');
			var optLabel  = isMC ? 'Color Slots <span>(key → label)</span>' : 'Options <span>(value → label)</span>';
			var optAddBtn = isMC ? '+ Add color slot' : '+ Add option';
			// Update the default input placeholder too.
			var defInput = row.querySelector('[data-key="default"]');
			if (defInput) defInput.placeholder = isMC ? '{"key":"#hex"} (JSON)' : 'Default';
			if (hasOpts && !existing) {
				row.insertAdjacentHTML('beforeend',
					'<div class="tpmeta-rsf-opts">'
					+ '<p class="tpmeta-rsf-opts-label">' + optLabel + '</p>'
					+ '<div class="tpmeta-rsf-opts-rows"></div>'
					+ '<button type="button" class="tpmeta-rsf-opt-add">' + optAddBtn + '</button>'
					+ '</div>');
			} else if (hasOpts && existing) {
				// Update labels when switching between option-bearing types (e.g. checkbox → multicolor).
				existing.querySelector('.tpmeta-rsf-opts-label').innerHTML = optLabel;
				existing.querySelector('.tpmeta-rsf-opt-add').textContent  = optAddBtn;
			} else if (!hasOpts && existing) {
				existing.remove();
			}

			// Post-select-style sub-field: show / hide an inline Post Type panel.
			var existingPS  = row.querySelector('.tpmeta-rsf-postsel');
			var isPostSelect = RSF_POSTSELECT_TYPES.indexOf(sfType) !== -1;
			if (isPostSelect && !existingPS) {
				row.insertAdjacentHTML('beforeend', rsfPostTypePanel({}));
			} else if (!isPostSelect && existingPS) {
				existingPS.remove();
			}

			// Image sub-field: show / hide return_format selector panel.
			var existingImg = row.querySelector('.tpmeta-rsf-img');
			var isImage     = (sfType === 'image');
			if (isImage && !existingImg) {
				row.insertAdjacentHTML('beforeend', rsfImagePanel({}));
			} else if (!isImage && existingImg) {
				existingImg.remove();
			}

			// Switch sub-field: show / hide stored-format + default picker.
			// Also flip the row's default input between hidden (carrier) and
			// the standard text input so other types keep their free-text
			// default editor.
			var existingSw = row.querySelector('.tpmeta-rsf-sw');
			var isSwitch   = (sfType === 'switch');
			var defInput   = row.querySelector('[data-key="default"]');
			if (isSwitch && !existingSw) {
				row.insertAdjacentHTML('beforeend', rsfSwitchPanel({}));
				if (defInput) {
					defInput.type = 'hidden';
					defInput.setAttribute('aria-hidden', 'true');
					// Seed carrier with the picker's initial selection ('off').
					var pickedSw = row.querySelector('.tpmeta-rsf-sw-dv:checked');
					defInput.value = pickedSw ? pickedSw.value : 'off';
				}
			} else if (!isSwitch && existingSw) {
				existingSw.remove();
				if (defInput) {
					defInput.type = 'text';
					defInput.removeAttribute('aria-hidden');
				}
			}

			// Typography sub-field: show / hide the composite default editor and
			// flip the inline default cell to a hidden carrier (the object default
			// is captured from the panel, not the cell).
			var existingTypo = row.querySelector('.tpmeta-rsf-typo');
			var isTypo       = (sfType === 'typography');
			if (isTypo && !existingTypo) {
				row.insertAdjacentHTML('beforeend', rsfTypographyPanel({}));
				if (defInput) {
					defInput.type = 'hidden';
					defInput.setAttribute('aria-hidden', 'true');
					defInput.value = '';
				}
			} else if (!isTypo && existingTypo) {
				existingTypo.remove();
				if (defInput && sfType !== 'switch') {
					defInput.type = 'text';
					defInput.removeAttribute('aria-hidden');
				}
			}

			// CSS output panel: visible only for colorpicker and color_gradient.
			var existingCss  = row.querySelector('.tpmeta-rsf-css');
			var isColorField = (sfType === 'colorpicker' || sfType === 'color_gradient');
			if (isColorField && !existingCss) {
				row.insertAdjacentHTML('beforeend', rsfCssOutputPanel({}));
			} else if (!isColorField && existingCss) {
				existingCss.remove();
			}
		});
	}

	function updateRSFCount() {
		var list  = document.getElementById('tpmeta-rsf-list');
		var badge = document.getElementById('tpmeta-rsf-count');
		if (list && badge) badge.textContent = list.querySelectorAll('.tpmeta-rsf-row').length;
	}

	/**
	 * Inline CSS output panel for colorpicker / color_gradient sub-fields.
	 * Lets the builder define a CSS selector + property so the options panel
	 * auto-generates frontend CSS when the user saves a color value.
	 * Supports {n} in selector/property as a 1-based row-index placeholder.
	 */
	function rsfCssOutputPanel(sf) {
		var co       = (sf && sf.css_output && typeof sf.css_output === 'object') ? sf.css_output : {};
		var selector = co.selector || '';
		var property = co.property || '';
		var uid      = Math.random().toString(36).slice(2, 8);
		var propOpts = [
			'color', 'background-color', 'background', 'background-image',
			'border-color', 'border-top-color', 'border-bottom-color',
			'border-left-color', 'border-right-color',
			'fill', 'stroke', 'outline-color',
		];
		var dlHtml = '<datalist id="tpmeta-rsf-css-dl-' + uid + '">'
			+ propOpts.map(function (p) { return '<option value="' + esc(p) + '">'; }).join('')
			+ '</datalist>';
		return '<div class="tpmeta-rsf-css">'
			+ '<p class="tpmeta-rsf-css-label">'
			+   '<span class="dashicons dashicons-editor-code"></span> CSS Output'
			+   '<span class="tpmeta-rsf-css-hint"> — auto-generates frontend CSS when the user saves a color</span>'
			+ '</p>'
			+ '<div class="tpmeta-rsf-css-grid">'
			+   '<div class="tpmeta-rsf-css-col">'
			+     '<label>CSS Selector</label>'
			+     '<input type="text" class="tpmeta-rsf-css-sel" value="' + esc(selector) + '" placeholder=".my-element, body" />'
			+     '<span class="tpmeta-rsf-css-note">Use <code>{n}</code> for the row index — e.g. <code>.card:nth-child({n})</code></span>'
			+   '</div>'
			+   '<div class="tpmeta-rsf-css-col">'
			+     '<label>CSS Property</label>'
			+     dlHtml
			+     '<input type="text" class="tpmeta-rsf-css-prop" list="tpmeta-rsf-css-dl-' + uid + '" value="' + esc(property) + '" placeholder="background-color" />'
			+   '</div>'
			+ '</div>'
			+ '</div>';
	}

	function readRepeaterSubFields() {
		var f    = currentField;
		var list = document.getElementById('tpmeta-rsf-list');
		f.fields = [];
		var colSel = document.getElementById('tpmeta-rsf-columns');
		f.columns = colSel ? (parseInt(colSel.value, 10) || 1) : 1;
		if (!list) return;

		Array.prototype.forEach.call(list.querySelectorAll('.tpmeta-rsf-row'), function (row) {
			var sfType    = (row.querySelector('[data-key="type"]').value || 'text');
			var sfId      = (row.querySelector('[data-key="id"]').value || '').trim().replace(/[^a-z0-9_]/gi, '_');
			var sfLabel   = (row.querySelector('[data-key="label"]').value || '').trim();
			var sfDefault = (row.querySelector('[data-key="default"]').value || '');
			// NEW: read per-sub-field column span (defaults to 1 if not present)
			var sfColsEl  = row.querySelector('[data-key="columns"]');
			var sfColumns = sfColsEl ? (parseInt(sfColsEl.value, 10) || 1) : 1;
			if (!sfId) return;

			var sf = { id: sfId, type: sfType, label: sfLabel, 'default': sfDefault, columns: sfColumns };

			// Image sub-field: capture return_format choice.
			if (sfType === 'image') {
				var rfChecked = row.querySelector('.tpmeta-rsf-img-rf:checked');
				sf.return_format = rfChecked ? rfChecked.value : 'array';
			}

			// Switch sub-field: capture data_type + normalise the default
			// token so 'true'/'false' or 'on'/'off' aligns with the chosen
			// format. The hidden carrier input is the source of truth here.
			if (sfType === 'switch') {
				var dtChecked = row.querySelector('.tpmeta-rsf-sw-dt:checked');
				sf.data_type = dtChecked ? dtChecked.value : 'string';
				var dvChecked = row.querySelector('.tpmeta-rsf-sw-dv:checked');
				if (dvChecked) {
					sf['default'] = dvChecked.value;
				} else {
					sf['default'] = (sf.data_type === 'boolean') ? 'false' : 'off';
				}
			}

			// Typography sub-field: build the composite default OBJECT from the
			// panel's [data-tk] inputs (only non-empty keys), so the typography
			// template's $field['default'] resolves each property. Omit entirely
			// when nothing is set so the field def stays clean.
			if (sfType === 'typography') {
				var typoDef = {};
				Array.prototype.forEach.call(row.querySelectorAll('.tpmeta-rsf-typo-f'), function (inp) {
					var k = inp.getAttribute('data-tk');
					var v = (inp.value || '').trim();
					if (k && v !== '') typoDef[k] = v;
				});
				if (Object.keys(typoDef).length) {
					sf['default'] = typoDef;
				} else {
					delete sf['default'];
				}
			}

			// CSS output: persist selector + property for color sub-fields.
			if (sfType === 'colorpicker' || sfType === 'color_gradient') {
				var cssSel  = row.querySelector('.tpmeta-rsf-css-sel');
				var cssProp = row.querySelector('.tpmeta-rsf-css-prop');
				var selVal  = cssSel  ? cssSel.value.trim()  : '';
				var propVal = cssProp ? cssProp.value.trim() : '';
				if (selVal && propVal) {
					sf.css_output = { selector: selVal, property: propVal };
				}
			}

			var optsContainer = row.querySelector('.tpmeta-rsf-opts-rows');
			if (optsContainer && RSF_OPTION_TYPES.indexOf(sfType) !== -1) {
				sf.options = [];
				Array.prototype.forEach.call(optsContainer.querySelectorAll('.tpmeta-rsf-opt-row'), function (optRow) {
					var v = (optRow.querySelector('.tpmeta-rsf-opt-v').value || '').trim();
					var l = (optRow.querySelector('.tpmeta-rsf-opt-l').value || '').trim();
					if (v) sf.options.push({ value: v, label: l || v });
				});
			}

			// Post Type + filters for post-select-style sub-fields.
			if (RSF_POSTSELECT_TYPES.indexOf(sfType) !== -1) {
				var psSel = row.querySelector('.tpmeta-rsf-postsel-pt');
				var pt    = psSel ? psSel.value : '';
				if (pt) sf.post_type = pt;

				// save_format: omit when 'id' (the default) so legacy rows stay clean.
				var fmtEl = row.querySelector('.tpmeta-rsf-postsel-fmt:checked');
				if (fmtEl && fmtEl.value === 'slug') {
					sf.save_format = 'slug';
				}

				// taxonomy_filter: only attach when a taxonomy is chosen.
				var taxSel  = row.querySelector('.tpmeta-rsf-postsel-tax');
				var termEl  = row.querySelector('.tpmeta-rsf-postsel-term');
				var taxVal  = taxSel  ? taxSel.value.trim()  : '';
				var termVal = termEl  ? termEl.value.trim() : '';
				if (taxVal) {
					sf.taxonomy_filter = { taxonomy: taxVal, term: termVal };
				}

				// meta_filters: collect non-empty rows.
				var metaRows = row.querySelectorAll('.tpmeta-rsf-postsel-meta-list .tpmeta-form-list-row');
				var metas    = [];
				Array.prototype.forEach.call(metaRows, function (mr) {
					var k = mr.querySelector('[data-key="meta-key"]').value.trim();
					var v = mr.querySelector('[data-key="meta-value"]').value.trim();
					if (k) metas.push({ key: k, value: v });
				});
				if (metas.length) sf.meta_filters = metas;
			}

			f.fields.push(sf);
		});
	}

	window.TPMetaBuilderModal = {
		open: open,
		openSection: openSection,
		openPanel: openPanel,
		close: close,
		init: init,
	};
})();
