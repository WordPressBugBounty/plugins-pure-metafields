/**
 * Post Types Builder — state-driven UI.
 * Views: welcome → (loader) → form (4 steps)
 * Sidebar always shows the list.
 */
(function ($) {
	'use strict';
	if (typeof PFPostTypes === 'undefined') return;

	/* ── State ──────────────────────────────────────────────── */
	var state = {
		postTypes: PFPostTypes.postTypes || [],
		view:      'welcome',   // welcome | form
		step:      1,
		editSlug:  null,
		form:      blankForm(),
	};

	var allTaxonomies = PFPostTypes.taxonomies || [];
	var TOTAL_STEPS   = 4;

	var LOADER_MESSAGES = [
		'Getting ready…',
		'Loading your workspace…',
		'Almost there…',
	];

	/* ── Boot ───────────────────────────────────────────────── */
	$(function () {
		renderSidebar();
		renderMain();
		wireGlobal();
	});

	/* ── Sidebar ────────────────────────────────────────────── */
	function renderSidebar() {
		var $list  = $('#cpt-list');
		var $empty = $('#cpt-list-empty');
		$('#cpt-count').text(state.postTypes.length);

		if (!state.postTypes.length) {
			$empty.show();
			$list.find('.cpt-item').remove();
			return;
		}
		$empty.hide();

		var html = state.postTypes.map(function (pt) {
			var active = state.editSlug === pt.slug ? ' is-active' : '';
			var icon   = pt.menu_icon && pt.menu_icon.indexOf('dashicons-') === 0
				? pt.menu_icon.replace('dashicons-', '') : 'admin-post';
			return '<div class="cpt-item' + active + '" data-slug="' + esc(pt.slug) + '">'
				+ '<div class="cpt-item-icon"><span class="dashicons dashicons-' + esc(icon) + '"></span></div>'
				+ '<div class="cpt-item-info">'
				+   '<div class="cpt-item-name">' + esc(pt.plural_name || pt.slug) + '</div>'
				+   '<div class="cpt-item-slug">' + esc(pt.slug) + '</div>'
				+ '</div>'
				+ '<div class="cpt-item-actions">'
				+   '<button class="cpt-edit" data-slug="' + esc(pt.slug) + '" title="Edit"><span class="dashicons dashicons-edit"></span></button>'
				+   '<button class="cpt-del" data-slug="' + esc(pt.slug) + '" title="Delete"><span class="dashicons dashicons-trash"></span></button>'
				+ '</div>'
				+ '</div>';
		}).join('');

		$list.find('.cpt-item').remove();
		$list.prepend(html);
	}

	/* ── Main area ──────────────────────────────────────────── */
	function renderMain() {
		if (state.view === 'welcome') {
			$('#cpt-main').html(welcomeHtml());
		} else {
			$('#cpt-main').html(formHtml());
			hydrateForm();
			wireForm();
		}
	}

	/* ── Welcome screen ─────────────────────────────────────── */
	function welcomeHtml() {
		return '<div class="cpt-welcome">'

			+ '<div class="cpt-welcome-hero">'
			+   '<div class="cpt-welcome-hero-icon"><span class="dashicons dashicons-admin-post"></span></div>'
			+   '<div class="cpt-welcome-hero-text">'
			+     '<h2>Custom Post Types</h2>'
			+     '<p>Build powerful content types for your WordPress site without writing any PHP.</p>'
			+   '</div>'
			+ '</div>'

			+ '<div class="cpt-welcome-features">'
			+   feature('admin-generic',  'blue',   'Custom Labels',    'Full control over all WordPress labels')
			+   feature('rest-api',       'purple', 'REST API Ready',   'Enable Gutenberg & headless support')
			+   feature('admin-page',     'green',  'Archive Pages',    'Optional archive page per post type')
			+   feature('layout',         'orange', 'Flexible Columns', 'Choose context, priority, and position')
			+ '</div>'

			+ '<button class="cpt-create-btn" id="cpt-create-btn">'
			+   '<span class="dashicons dashicons-plus-alt2"></span> Create Post Type'
			+ '</button>'

			+ '</div>';
	}

	function feature(icon, color, title, desc) {
		return '<div class="cpt-feature">'
			+ '<div class="cpt-feature-icon cpt-feature-icon--' + color + '"><span class="dashicons dashicons-' + icon + '"></span></div>'
			+ '<div class="cpt-feature-text"><strong>' + title + '</strong><span>' + desc + '</span></div>'
			+ '</div>';
	}

	/* ── Multi-step form ────────────────────────────────────── */
	function formHtml() {
		var isEdit = !!state.editSlug;
		var title  = isEdit ? 'Edit Post Type' : 'Create Post Type';

		return '<div class="cpt-form-screen">'

			// Header: back + steps
			+ '<div class="cpt-form-header">'
			+   '<button class="cpt-back-btn" id="cpt-back"><span class="dashicons dashicons-arrow-left-alt"></span> Back</button>'
			+   '<div class="cpt-steps" id="cpt-steps">' + stepsHtml() + '</div>'
			+   '<h2 class="cpt-form-title">' + title + '</h2>'
			+ '</div>'

			// Form body
			+ '<div class="cpt-form-body">'
			+   stepPanel(1)
			+   stepPanel(2)
			+   stepPanel(3)
			+   stepPanel(4)
			+   '<div class="cpt-form-footer">'
			+     '<div class="cpt-footer-left">'
			+       '<button class="cpt-btn" id="cpt-prev" style="display:none"><span class="dashicons dashicons-arrow-left-alt2"></span> Previous</button>'
			+     '</div>'
			+     '<div class="cpt-footer-right">'
			+       '<button class="cpt-btn cpt-btn--primary" id="cpt-next">Next Step <span class="dashicons dashicons-arrow-right-alt2"></span></button>'
			+       '<button class="cpt-btn cpt-btn--primary" id="cpt-save-btn" style="display:none"><span class="dashicons dashicons-saved"></span> Save Post Type</button>'
			+     '</div>'
			+   '</div>'
			+ '</div>'

			+ '</div>';
	}

	function stepsHtml() {
		var labels = ['Basics', 'Display', 'Settings', 'Supports'];
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
			+ '<p class="cpt-step-desc">Define the core identity of your post type.</p>'
			+ '<div class="cpt-form-grid-2">'
			+   row('Singular Name', 'text', 'cpt-singular', 'e.g. Portfolio Item', true)
			+   row('Plural Name',   'text', 'cpt-plural',   'e.g. Portfolio Items', true)
			+ '</div>'
			+ '<div class="cpt-form-grid-2">'
			+   '<div class="cpt-form-row"><label>Post Type Key <span style="color:#d63638">*</span></label>'
			+     '<input type="text" id="cpt-slug" class="cpt-mono" placeholder="portfolio" maxlength="20">'
			+     '<p class="cpt-form-hint">Max 20 characters. Auto-generated from singular name.</p>'
			+   '</div>'
			+   row('Menu Name', 'text', 'cpt-menu-name', 'e.g. Portfolio')
			+ '</div>'
			+ '<div class="cpt-form-row"><label>Description</label>'
			+   '<textarea id="cpt-desc" placeholder="Optional description for this post type…"></textarea>'
			+ '</div>';

		case 2: return ''
			+ '<h3>Display &amp; Navigation</h3>'
			+ '<p class="cpt-step-desc">Control how this post type appears in the WordPress admin menu.</p>'
			+ '<div class="cpt-form-grid-2">'
			+   '<div class="cpt-form-row"><label>Menu Icon <small>(Dashicon slug)</small></label>'
			+     '<input type="text" id="cpt-icon" placeholder="dashicons-admin-post">'
			+     '<p class="cpt-form-hint"><a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">Browse Dashicons &rarr;</a></p>'
			+   '</div>'
			+   '<div class="cpt-form-row"><label>Menu Position</label>'
			+     '<input type="number" id="cpt-menu-pos" min="1" max="100" value="25">'
			+     '<p class="cpt-form-hint">Numeric position in the admin sidebar.</p>'
			+   '</div>'
			+ '</div>'
			+ '<div class="cpt-form-grid-2">'
			+   '<div class="cpt-form-row"><label>Custom Rewrite Slug</label>'
			+     '<input type="text" id="cpt-rewrite" class="cpt-mono" placeholder="Leave blank to use post type key">'
			+   '</div>'
			+   '<div class="cpt-form-row"><label>Capability Type</label>'
			+     '<select id="cpt-cap-type"><option value="post">post</option><option value="page">page</option></select>'
			+   '</div>'
			+ '</div>';

		case 3: return ''
			+ '<h3>Visibility &amp; Settings</h3>'
			+ '<p class="cpt-step-desc">Configure how this post type behaves across WordPress.</p>'
			+ '<div class="cpt-toggles-grid">'
			+   toggle('cpt-public',      'Public',               'Visible on the front-end',            true)
			+   toggle('cpt-rest',        'REST API / Gutenberg', 'Enables the block editor',            true)
			+   toggle('cpt-hierarchical','Hierarchical',         'Allows parent / child posts',         false)
			+   toggle('cpt-archive',     'Has Archive',          'Enables an archive listing page',     false)
			+   toggle('cpt-in-menu',     'Show in Admin Menu',   'Appears in the WP admin sidebar',     true)
			+   toggle('cpt-excl-search', 'Exclude from Search',  'Hidden from WordPress search results', false)
			+ '</div>';

		case 4:
			var pillsHtml = ['title','editor','thumbnail','excerpt','author','comments','revisions','custom-fields','page-attributes','post-formats'].map(function (s) {
				var def = ['title','editor','thumbnail'].indexOf(s) > -1;
				return '<label class="cpt-pill' + (def ? ' is-active' : '') + '" data-support="' + s + '">'
					+ '<input type="checkbox" value="' + s + '"' + (def ? ' checked' : '') + '>' + s + '</label>';
			}).join('');
			var taxHtml = allTaxonomies.map(function (t) {
				return '<label class="cpt-check-item"><input type="checkbox" value="' + esc(t.value) + '"> ' + esc(t.label) + '</label>';
			}).join('') || '<p style="color:#a8b4bf;font-size:12px;">No registered taxonomies.</p>';
			return '<h3>Supports &amp; Taxonomies</h3>'
				+ '<p class="cpt-step-desc">Select which features and taxonomies this post type uses.</p>'
				+ '<div class="cpt-form-row"><label>Supported Features</label>'
				+   '<div class="cpt-pill-group" id="cpt-supports-group">' + pillsHtml + '</div>'
				+ '</div>'
				+ '<div class="cpt-form-row"><label>Attach Taxonomies</label>'
				+   '<div class="cpt-check-grid" id="cpt-tax-group">' + taxHtml + '</div>'
				+ '</div>';
		}
		return '';
	}

	function row(label, type, id, placeholder, required) {
		return '<div class="cpt-form-row"><label>' + label + (required ? ' <span style="color:#d63638">*</span>' : '') + '</label>'
			+ '<input type="' + type + '" id="' + id + '" placeholder="' + (placeholder || '') + '">'
			+ '</div>';
	}

	function toggle(id, title, desc, checked) {
		return '<div class="cpt-toggle-item">'
			+ '<div class="cpt-toggle-info"><strong>' + title + '</strong><span>' + desc + '</span></div>'
			+ '<label class="cpt-toggle"><input type="checkbox" id="' + id + '"' + (checked ? ' checked' : '') + '>'
			+ '<span class="cpt-toggle-slider"></span></label>'
			+ '</div>';
	}

	/* ── Hydrate form from state.form ───────────────────────── */
	function hydrateForm() {
		var f = state.form;
		setVal('cpt-singular',  f.singular_name);
		setVal('cpt-plural',    f.plural_name);
		setVal('cpt-slug',      f.slug);
		setVal('cpt-menu-name', f.menu_name);
		setVal('cpt-desc',      f.description);
		setVal('cpt-icon',      f.menu_icon);
		setVal('cpt-menu-pos',  f.menu_position);
		setVal('cpt-rewrite',   f.rewrite_slug);
		setChk('cpt-cap-type',  f.capability_type);
		setChk('cpt-public',    f.public);
		setChk('cpt-rest',      f.show_in_rest);
		setChk('cpt-hierarchical', f.hierarchical);
		setChk('cpt-archive',   f.has_archive);
		setChk('cpt-in-menu',   f.show_in_menu);
		setChk('cpt-excl-search', f.exclude_from_search);

		// Supports pills
		var supports = f.supports || [];
		$('#cpt-supports-group .cpt-pill').each(function () {
			var s = $(this).data('support');
			var active = supports.indexOf(s) > -1;
			$(this).toggleClass('is-active', active).find('input').prop('checked', active);
		});

		// Taxonomies
		$('#cpt-tax-group input[type="checkbox"]').each(function () {
			$(this).prop('checked', (f.taxonomies || []).indexOf($(this).val()) > -1);
		});
	}

	function setVal(id, val) { if (val !== undefined && val !== null) $('#' + id).val(val); }
	function setChk(id, val) { $('#' + id).prop('checked', !!val); }

	/* ── Wire form events ───────────────────────────────────── */
	function wireForm() {
		// Pill toggles for supports
		$(document).on('click', '.cpt-pill', function (e) {
			e.preventDefault();
			$(this).toggleClass('is-active');
			$(this).find('input').prop('checked', $(this).hasClass('is-active'));
		});

		// Auto-generate slug and menu name from singular
		$('#cpt-singular').on('input', function () {
			if (!$('#cpt-slug').data('manual')) {
				$('#cpt-slug').val(slugify($(this).val()).slice(0, 20));
			}
			if (!$('#cpt-menu-name').val()) {
				$('#cpt-menu-name').val($(this).val());
			}
		});
		$('#cpt-slug').on('input', function () { $(this).data('manual', !!$(this).val()); });

		// Auto-fill plural from singular if empty
		$('#cpt-singular').on('blur', function () {
			if (!$('#cpt-plural').val()) {
				$('#cpt-plural').val($(this).val() + 's');
			}
		});

		updateNavButtons();
	}

	function updateNavButtons() {
		$('#cpt-prev').toggle(state.step > 1);
		$('#cpt-next').toggle(state.step < TOTAL_STEPS);
		$('#cpt-save-btn').toggle(state.step === TOTAL_STEPS);
	}

	function updateSteps() {
		$('#cpt-steps').html(stepsHtml());
		$('.cpt-step-panel').removeClass('is-active');
		$('.cpt-step-panel[data-step="' + state.step + '"]').addClass('is-active');
		updateNavButtons();
	}

	function readFormData() {
		var supports = [];
		$('#cpt-supports-group .cpt-pill.is-active input').each(function () { supports.push($(this).val()); });
		var taxes = [];
		$('#cpt-tax-group input:checked').each(function () { taxes.push($(this).val()); });

		state.form = {
			slug:                 $('#cpt-slug').val().trim()      || slugify($('#cpt-singular').val()),
			singular_name:        $('#cpt-singular').val().trim(),
			plural_name:          $('#cpt-plural').val().trim(),
			menu_name:            $('#cpt-menu-name').val().trim(),
			description:          $('#cpt-desc').val().trim(),
			menu_icon:            $('#cpt-icon').val().trim()      || 'dashicons-admin-post',
			menu_position:        parseInt($('#cpt-menu-pos').val(), 10) || 25,
			rewrite_slug:         $('#cpt-rewrite').val().trim(),
			capability_type:      $('#cpt-cap-type').val()         || 'post',
			public:               $('#cpt-public').is(':checked'),
			show_in_rest:         $('#cpt-rest').is(':checked'),
			hierarchical:         $('#cpt-hierarchical').is(':checked'),
			has_archive:          $('#cpt-archive').is(':checked'),
			show_in_menu:         $('#cpt-in-menu').is(':checked'),
			exclude_from_search:  $('#cpt-excl-search').is(':checked'),
			supports:             supports,
			taxonomies:           taxes,
		};
	}

	function validateStep() {
		if (state.step === 1) {
			var s = $('#cpt-singular').val().trim();
			var p = $('#cpt-plural').val().trim();
			if (!s || !p) { alert('Singular and plural names are required.'); return false; }
		}
		return true;
	}

	/* ── Global wire ────────────────────────────────────────── */
	function wireGlobal() {
		// Welcome: create button
		$(document).on('click', '#cpt-create-btn', function () {
			state.editSlug = null;
			state.form     = blankForm();
			state.step     = 1;
			showLoader(['Setting up your workspace…', 'Loading fields and tools…', 'Ready!'], function () {
				state.view = 'form';
				renderMain();
			});
		});

		// Back button
		$(document).on('click', '#cpt-back', function () {
			if (state.step > 1) {
				readFormData();
				state.step--;
				updateSteps();
				return;
			}
			state.view     = 'welcome';
			state.editSlug = null;
			renderMain();
		});

		// Next / Prev
		$(document).on('click', '#cpt-next', function () {
			if (!validateStep()) return;
			readFormData();
			state.step++;
			updateSteps();
		});

		$(document).on('click', '#cpt-prev', function () {
			readFormData();
			state.step--;
			updateSteps();
		});

		// Save
		$(document).on('click', '#cpt-save-btn', function () {
			readFormData();
			var data = state.form;
			if (!data.singular_name || !data.plural_name || !data.slug) {
				alert('Please fill in singular name, plural name, and slug.');
				return;
			}
			var $btn = $(this).prop('disabled', true).text('Saving…');
			$.post(PFPostTypes.ajaxUrl, {
				action: 'pf_save_post_type',
				nonce:  PFPostTypes.nonce,
				data:   JSON.stringify(data),
			}, function (res) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Post Type');
				if (res.success) {
					var idx = state.postTypes.findIndex(function (p) { return p.slug === data.slug; });
					if (idx > -1) state.postTypes[idx] = res.data;
					else          state.postTypes.push(res.data);
					state.editSlug = data.slug;
					renderSidebar();
					showToast('Post type saved!', 'success');
				} else {
					showToast('Save failed: ' + (res.data || 'unknown error'), 'error');
				}
			});
		});

		// Sidebar: edit
		$(document).on('click', '.cpt-edit', function (e) {
			e.stopPropagation();
			var slug = $(this).data('slug');
			var pt   = state.postTypes.find(function (p) { return p.slug === slug; });
			if (!pt) return;
			state.editSlug = slug;
			state.form     = Object.assign(blankForm(), pt);
			state.step     = 1;
			showLoader(['Loading post type…', 'Getting your fields ready…', 'Ready!'], function () {
				state.view = 'form';
				renderSidebar();
				renderMain();
			});
		});

		// Sidebar: delete
		$(document).on('click', '.cpt-del', function (e) {
			e.stopPropagation();
			var slug = $(this).data('slug');
			var pt   = state.postTypes.find(function (p) { return p.slug === slug; });
			if (!confirm('Delete "' + (pt ? pt.plural_name || slug : slug) + '"?\nExisting posts will NOT be deleted.')) return;
			$.post(PFPostTypes.ajaxUrl, {
				action: 'pf_delete_post_type',
				nonce:  PFPostTypes.nonce,
				slug:   slug,
			}, function (res) {
				if (res.success) {
					state.postTypes = state.postTypes.filter(function (p) { return p.slug !== slug; });
					if (state.editSlug === slug) {
						state.view     = 'welcome';
						state.editSlug = null;
						renderMain();
					}
					renderSidebar();
					showToast('Deleted.', 'success');
				} else {
					showToast('Delete failed.', 'error');
				}
			});
		});

		// Sidebar: click item to edit
		$(document).on('click', '.cpt-item', function (e) {
			if ($(e.target).closest('.cpt-item-actions').length) return;
			$(this).find('.cpt-edit').trigger('click');
		});
	}

	/* ── Loader ─────────────────────────────────────────────── */
	function showLoader(messages, cb) {
		var $loader  = $('#cpt-loader');
		var $text    = $('#cpt-loader-text');
		var msgIdx   = 0;
		$text.text(messages[0]);
		$loader.addClass('is-active').removeAttr('aria-hidden');

		var interval = setInterval(function () {
			msgIdx++;
			if (msgIdx < messages.length) {
				$text.addClass('is-fading');
				setTimeout(function () {
					$text.text(messages[msgIdx]).removeClass('is-fading');
				}, 250);
			}
		}, 600);

		setTimeout(function () {
			clearInterval(interval);
			$loader.removeClass('is-active');
			setTimeout(function () {
				$loader.attr('aria-hidden', 'true');
				cb();
			}, 300);
		}, 1400);
	}

	/* ── Toast ──────────────────────────────────────────────── */
	function showToast(msg, type) {
		var $t = $('<div class="cpt-toast cpt-toast--' + (type || 'success') + '">' + esc(msg) + '</div>');
		$('body').append($t);
		setTimeout(function () { $t.addClass('is-visible'); }, 10);
		setTimeout(function () { $t.removeClass('is-visible'); setTimeout(function () { $t.remove(); }, 300); }, 2500);
	}

	/* ── Helpers ────────────────────────────────────────────── */
	function blankForm() {
		return {
			slug: '', singular_name: '', plural_name: '', menu_name: '', description: '',
			menu_icon: 'dashicons-admin-post', menu_position: 25, rewrite_slug: '',
			capability_type: 'post', public: true, show_in_rest: true,
			hierarchical: false, has_archive: false, show_in_menu: true, exclude_from_search: false,
			supports: ['title', 'editor', 'thumbnail'], taxonomies: [],
		};
	}

	function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
	function slugify(s) { return String(s).toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,''); }

}(jQuery));
