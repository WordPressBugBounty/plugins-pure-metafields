/**
 * Taxonomies Builder — same state-driven pattern as Post Types.
 */
(function ($) {
	'use strict';
	if (typeof PFTaxonomies === 'undefined') return;

	var state = {
		taxonomies: PFTaxonomies.taxonomies || [],
		view:       'welcome',
		step:       1,
		editSlug:   null,
		form:       blankForm(),
	};

	var allPostTypes  = PFTaxonomies.postTypes || [];
	var TOTAL_STEPS   = 3;

	var LOADER_MESSAGES = ['Setting up your workspace…', 'Loading fields…', 'Ready!'];

	$(function () {
		renderSidebar();
		renderMain();
		wireGlobal();
	});

	/* ── Sidebar ────────────────────────────────────────────── */
	function renderSidebar() {
		var $list  = $('#tax-list');
		var $empty = $('#tax-list-empty');
		$('#tax-count').text(state.taxonomies.length);

		if (!state.taxonomies.length) { $empty.show(); $list.find('.cpt-item').remove(); return; }
		$empty.hide();

		var html = state.taxonomies.map(function (t) {
			var active = state.editSlug === t.slug ? ' is-active' : '';
			return '<div class="cpt-item' + active + '" data-slug="' + esc(t.slug) + '">'
				+ '<div class="cpt-item-icon"><span class="dashicons dashicons-tag"></span></div>'
				+ '<div class="cpt-item-info">'
				+   '<div class="cpt-item-name">' + esc(t.plural_name || t.slug) + '</div>'
				+   '<div class="cpt-item-slug">' + esc(t.slug) + '</div>'
				+ '</div>'
				+ '<div class="cpt-item-actions">'
				+   '<button class="tax-edit" data-slug="' + esc(t.slug) + '" title="Edit"><span class="dashicons dashicons-edit"></span></button>'
				+   '<button class="tax-del cpt-del" data-slug="' + esc(t.slug) + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>'
				+ '</div>'
				+ '</div>';
		}).join('');

		$list.find('.cpt-item').remove();
		$list.prepend(html);
	}

	/* ── Main area ──────────────────────────────────────────── */
	function renderMain() {
		if (state.view === 'welcome') {
			$('#tax-main').html(welcomeHtml());
		} else {
			$('#tax-main').html(formHtml());
			hydrateForm();
			wireForm();
		}
	}

	/* ── Welcome screen ─────────────────────────────────────── */
	function welcomeHtml() {
		return '<div class="cpt-welcome">'
			+ '<div class="cpt-welcome-hero">'
			+   '<div class="cpt-welcome-hero-icon"><span class="dashicons dashicons-tag"></span></div>'
			+   '<div class="cpt-welcome-hero-text">'
			+     '<h2>Custom Taxonomies</h2>'
			+     '<p>Create flat or hierarchical taxonomies and attach them to any post type — no PHP needed.</p>'
			+   '</div>'
			+ '</div>'
			+ '<div class="cpt-welcome-features">'
			+   feature('tag',       'pink',   'Flat or Hierarchical', 'Works like tags or categories')
			+   feature('rest-api',  'purple', 'REST API / Gutenberg', 'Enables block editor support')
			+   feature('admin-post','green',  'Attach to Any Type',   'Link to multiple post types')
			+   feature('admin-appearance','orange','Archive Support', 'Optional term archive pages')
			+ '</div>'
			+ '<button class="cpt-create-btn" id="tax-create-btn">'
			+   '<span class="dashicons dashicons-plus-alt2"></span> Create Taxonomy'
			+ '</button>'
			+ '</div>';
	}

	function feature(icon, color, title, desc) {
		return '<div class="cpt-feature">'
			+ '<div class="cpt-feature-icon cpt-feature-icon--' + color + '"><span class="dashicons dashicons-' + icon + '"></span></div>'
			+ '<div class="cpt-feature-text"><strong>' + title + '</strong><span>' + desc + '</span></div>'
			+ '</div>';
	}

	/* ── Multi-step form (3 steps for taxonomy) ─────────────── */
	function formHtml() {
		var title = state.editSlug ? 'Edit Taxonomy' : 'Create Taxonomy';
		return '<div class="cpt-form-screen">'
			+ '<div class="cpt-form-header">'
			+   '<button class="cpt-back-btn" id="tax-back"><span class="dashicons dashicons-arrow-left-alt"></span> Back</button>'
			+   '<div class="cpt-steps" id="tax-steps">' + stepsHtml() + '</div>'
			+   '<h2 class="cpt-form-title">' + title + '</h2>'
			+ '</div>'
			+ '<div class="cpt-form-body">'
			+   stepPanel(1) + stepPanel(2) + stepPanel(3)
			+   '<div class="cpt-form-footer">'
			+     '<div class="cpt-footer-left"><button class="cpt-btn" id="tax-prev" style="display:none"><span class="dashicons dashicons-arrow-left-alt2"></span> Previous</button></div>'
			+     '<div class="cpt-footer-right">'
			+       '<button class="cpt-btn cpt-btn--primary" id="tax-next">Next Step <span class="dashicons dashicons-arrow-right-alt2"></span></button>'
			+       '<button class="cpt-btn cpt-btn--primary" id="tax-save-btn" style="display:none"><span class="dashicons dashicons-saved"></span> Save Taxonomy</button>'
			+     '</div>'
			+   '</div>'
			+ '</div>'
			+ '</div>';
	}

	function stepsHtml() {
		var labels = ['Basics', 'Settings', 'Post Types'];
		var out = '';
		for (var i = 1; i <= TOTAL_STEPS; i++) {
			var cls = i < state.step ? ' is-done' : (i === state.step ? ' is-active' : '');
			out += '<div class="cpt-step-indicator">';
			if (i > 1) out += '<div class="cpt-step-line' + (i <= state.step ? ' is-done' : '') + '"></div>';
			out += '<div style="display:flex;flex-direction:column;align-items:center;gap:3px;">'
				+ '<div class="cpt-step-num' + cls + '">'
				+   (i < state.step ? '<span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px;"></span>' : i)
				+ '</div>'
				+ '<span class="cpt-step-label">' + labels[i - 1] + '</span>'
				+ '</div>';
			out += '</div>';
		}
		return out;
	}

	function stepPanel(n) {
		var active = n === state.step ? ' is-active' : '';
		return '<div class="cpt-step-panel' + active + '" data-step="' + n + '">' + stepContent(n) + '</div>';
	}

	function stepContent(n) {
		switch (n) {
		case 1: return ''
			+ '<h3>Basic Information</h3>'
			+ '<p class="cpt-step-desc">Define the name and key identifier for your taxonomy.</p>'
			+ '<div class="cpt-form-grid-2">'
			+   row('Singular Name', 'tax-singular', 'e.g. Category', true)
			+   row('Plural Name',   'tax-plural',   'e.g. Categories', true)
			+ '</div>'
			+ '<div class="cpt-form-grid-2">'
			+   '<div class="cpt-form-row"><label>Taxonomy Key <span style="color:#d63638">*</span></label>'
			+     '<input type="text" id="tax-slug" class="cpt-mono" placeholder="portfolio_category" maxlength="32">'
			+     '<p class="cpt-form-hint">Max 32 characters. Auto-generated from singular name.</p>'
			+   '</div>'
			+   '<div class="cpt-form-row"><label>Custom Rewrite Slug</label>'
			+     '<input type="text" id="tax-rewrite" class="cpt-mono" placeholder="Leave blank to use taxonomy key">'
			+   '</div>'
			+ '</div>'
			+ '<div class="cpt-form-row"><label>Description</label>'
			+   '<textarea id="tax-desc" placeholder="Optional description…"></textarea>'
			+ '</div>';

		case 2: return ''
			+ '<h3>Visibility &amp; Settings</h3>'
			+ '<p class="cpt-step-desc">Configure how this taxonomy behaves in WordPress.</p>'
			+ '<div class="cpt-toggles-grid">'
			+   toggle('tax-hierarchical',    'Hierarchical',       'Works like Categories (parent/child terms)', false)
			+   toggle('tax-public',          'Public',             'Visible on the front-end', true)
			+   toggle('tax-rest',            'REST API / Gutenberg','Enables block editor term selection', true)
			+   toggle('tax-admin-col',       'Show Admin Column',  'Display terms column in post list tables', true)
			+ '</div>';

		case 3:
			var ptHtml = allPostTypes.map(function (pt) {
				return '<label class="cpt-check-item"><input type="checkbox" value="' + esc(pt.value) + '"> ' + esc(pt.label) + '</label>';
			}).join('') || '<p style="color:#a8b4bf;font-size:12px;">No public post types found.</p>';
			return '<h3>Attach to Post Types</h3>'
				+ '<p class="cpt-step-desc">Select which post types this taxonomy will be registered for.</p>'
				+ '<div class="cpt-check-grid" id="tax-pt-group">' + ptHtml + '</div>';
		}
		return '';
	}

	function row(label, id, placeholder, required) {
		return '<div class="cpt-form-row"><label>' + label + (required ? ' <span style="color:#d63638">*</span>' : '') + '</label>'
			+ '<input type="text" id="' + id + '" placeholder="' + (placeholder || '') + '">'
			+ '</div>';
	}

	function toggle(id, title, desc, checked) {
		return '<div class="cpt-toggle-item">'
			+ '<div class="cpt-toggle-info"><strong>' + title + '</strong><span>' + desc + '</span></div>'
			+ '<label class="cpt-toggle"><input type="checkbox" id="' + id + '"' + (checked ? ' checked' : '') + '>'
			+ '<span class="cpt-toggle-slider"></span></label>'
			+ '</div>';
	}

	/* ── Hydrate form ───────────────────────────────────────── */
	function hydrateForm() {
		var f = state.form;
		setVal('tax-singular',   f.singular_name);
		setVal('tax-plural',     f.plural_name);
		setVal('tax-slug',       f.slug);
		setVal('tax-desc',       f.description);
		setVal('tax-rewrite',    f.rewrite_slug);
		setChk('tax-hierarchical', f.hierarchical);
		setChk('tax-public',     f.public);
		setChk('tax-rest',       f.show_in_rest);
		setChk('tax-admin-col',  f.show_admin_column);

		$('#tax-pt-group input[type="checkbox"]').each(function () {
			$(this).prop('checked', (f.post_types || []).indexOf($(this).val()) > -1);
		});
	}

	function setVal(id, val) { if (val !== undefined && val !== null) $('#' + id).val(val); }
	function setChk(id, val) { $('#' + id).prop('checked', !!val); }

	/* ── Wire form ──────────────────────────────────────────── */
	function wireForm() {
		$('#tax-singular').on('input', function () {
			if (!$('#tax-slug').data('manual')) {
				$('#tax-slug').val(slugify($(this).val()).slice(0, 32));
			}
			if (!$('#tax-plural').val()) { /* leave empty */ }
		});
		$('#tax-slug').on('input', function () { $(this).data('manual', !!$(this).val()); });
		$('#tax-singular').on('blur', function () {
			if (!$('#tax-plural').val()) $('#tax-plural').val($(this).val() + 's');
		});
		updateNavButtons();
	}

	function updateNavButtons() {
		$('#tax-prev').toggle(state.step > 1);
		$('#tax-next').toggle(state.step < TOTAL_STEPS);
		$('#tax-save-btn').toggle(state.step === TOTAL_STEPS);
	}

	function updateSteps() {
		$('#tax-steps').html(stepsHtml());
		$('.cpt-step-panel').removeClass('is-active');
		$('.cpt-step-panel[data-step="' + state.step + '"]').addClass('is-active');
		updateNavButtons();
	}

	function readFormData() {
		var pts = [];
		$('#tax-pt-group input:checked').each(function () { pts.push($(this).val()); });
		state.form = {
			slug:               $('#tax-slug').val().trim()     || slugify($('#tax-singular').val()),
			singular_name:      $('#tax-singular').val().trim(),
			plural_name:        $('#tax-plural').val().trim(),
			description:        $('#tax-desc').val().trim(),
			rewrite_slug:       $('#tax-rewrite').val().trim(),
			hierarchical:       $('#tax-hierarchical').is(':checked'),
			public:             $('#tax-public').is(':checked'),
			show_in_rest:       $('#tax-rest').is(':checked'),
			show_admin_column:  $('#tax-admin-col').is(':checked'),
			post_types:         pts,
		};
	}

	function validateStep() {
		if (state.step === 1) {
			if (!$('#tax-singular').val().trim() || !$('#tax-plural').val().trim()) {
				alert('Singular and plural names are required.'); return false;
			}
		}
		return true;
	}

	/* ── Global wire ────────────────────────────────────────── */
	function wireGlobal() {
		$(document).on('click', '#tax-create-btn', function () {
			state.editSlug = null;
			state.form     = blankForm();
			state.step     = 1;
			showLoader(LOADER_MESSAGES, function () {
				state.view = 'form';
				renderMain();
			});
		});

		$(document).on('click', '#tax-back', function () {
			if (state.step > 1) { readFormData(); state.step--; updateSteps(); return; }
			state.view = 'welcome'; state.editSlug = null; renderMain();
		});

		$(document).on('click', '#tax-next', function () {
			if (!validateStep()) return;
			readFormData(); state.step++; updateSteps();
		});

		$(document).on('click', '#tax-prev', function () {
			readFormData(); state.step--; updateSteps();
		});

		$(document).on('click', '#tax-save-btn', function () {
			readFormData();
			var data = state.form;
			if (!data.singular_name || !data.plural_name || !data.slug) {
				alert('Please fill in singular name, plural name, and slug.'); return;
			}
			var $btn = $(this).prop('disabled', true).text('Saving…');
			$.post(PFTaxonomies.ajaxUrl, {
				action: 'pf_save_taxonomy',
				nonce:  PFTaxonomies.nonce,
				data:   JSON.stringify(data),
			}, function (res) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Taxonomy');
				if (res.success) {
					var idx = state.taxonomies.findIndex(function (t) { return t.slug === data.slug; });
					if (idx > -1) state.taxonomies[idx] = res.data;
					else          state.taxonomies.push(res.data);
					state.editSlug = data.slug;
					renderSidebar();
					showToast('Taxonomy saved!', 'success');
				} else {
					showToast('Save failed: ' + (res.data || 'unknown error'), 'error');
				}
			});
		});

		$(document).on('click', '.tax-edit', function (e) {
			e.stopPropagation();
			var slug = $(this).data('slug');
			var t    = state.taxonomies.find(function (x) { return x.slug === slug; });
			if (!t) return;
			state.editSlug = slug;
			state.form     = Object.assign(blankForm(), t);
			state.step     = 1;
			showLoader(LOADER_MESSAGES, function () {
				state.view = 'form'; renderSidebar(); renderMain();
			});
		});

		$(document).on('click', '.tax-del', function (e) {
			e.stopPropagation();
			var slug = $(this).data('slug');
			var t    = state.taxonomies.find(function (x) { return x.slug === slug; });
			if (!confirm('Delete "' + (t ? t.plural_name || slug : slug) + '"?\nExisting terms will remain in the database.')) return;
			$.post(PFTaxonomies.ajaxUrl, {
				action: 'pf_delete_taxonomy', nonce: PFTaxonomies.nonce, slug: slug,
			}, function (res) {
				if (res.success) {
					state.taxonomies = state.taxonomies.filter(function (x) { return x.slug !== slug; });
					if (state.editSlug === slug) { state.view = 'welcome'; state.editSlug = null; renderMain(); }
					renderSidebar();
					showToast('Deleted.', 'success');
				} else { showToast('Delete failed.', 'error'); }
			});
		});

		$(document).on('click', '.cpt-item', function (e) {
			if ($(e.target).closest('.cpt-item-actions').length) return;
			$(this).find('.tax-edit').trigger('click');
		});
	}

	/* ── Loader ─────────────────────────────────────────────── */
	function showLoader(messages, cb) {
		var $loader = $('#tax-loader');
		var $text   = $('#tax-loader-text');
		var idx = 0;
		$text.text(messages[0]);
		$loader.addClass('is-active').removeAttr('aria-hidden');

		var interval = setInterval(function () {
			idx++;
			if (idx < messages.length) {
				$text.addClass('is-fading');
				setTimeout(function () { $text.text(messages[idx]).removeClass('is-fading'); }, 250);
			}
		}, 600);

		setTimeout(function () {
			clearInterval(interval);
			$loader.removeClass('is-active');
			setTimeout(function () { $loader.attr('aria-hidden', 'true'); cb(); }, 300);
		}, 1400);
	}

	function showToast(msg, type) {
		var $t = $('<div class="cpt-toast cpt-toast--' + (type || 'success') + '">' + esc(msg) + '</div>');
		$('body').append($t);
		setTimeout(function () { $t.addClass('is-visible'); }, 10);
		setTimeout(function () { $t.removeClass('is-visible'); setTimeout(function () { $t.remove(); }, 300); }, 2500);
	}

	function blankForm() {
		return {
			slug: '', singular_name: '', plural_name: '', description: '', rewrite_slug: '',
			hierarchical: false, public: true, show_in_rest: true, show_admin_column: true, post_types: [],
		};
	}

	function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
	function slugify(s) { return String(s).toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,''); }

}(jQuery));
