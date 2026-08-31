/**
 * TPMeta Options Panel — frontend logic.
 * - Tab switching
 * - AJAX save (serialized form payload)
 * - Image picker (WP Media)
 * - Conditional show/hide
 * - Post Select (select2 + AJAX search)
 */
(function ($) {
	'use strict';

	$(function () {
		var $wrap = $('.tpmeta-options-wrap');
		if (!$wrap.length) {
			return;
		}

		var $form    = $wrap.find('.tpmeta-options-form');
		var $status  = $wrap.find('.tpmeta-options-status');
		var $saveBtn = $wrap.find('.tpmeta-options-save');

		// ---------- Tab switching ----------
		$wrap.on('click', '.tpmeta-options-nav-item', function (e) {
			e.preventDefault();
			var $link   = $(this);
			var section = $link.data('section');

			$wrap.find('.tpmeta-options-nav-item').removeClass('is-active');
			$link.addClass('is-active');

			$wrap.find('.tpmeta-options-section').removeClass('is-active');
			$wrap.find('.tpmeta-options-section[data-section="' + section + '"]').addClass('is-active');

			if (history.replaceState) {
				history.replaceState(null, '', '#tpmeta-section-' + section);
			}
		});

		// Restore section from hash — clean up the no-flash init style and
		// properly set is-active without triggering the slide-in animation.
		(function () {
			var noFlash = document.getElementById( 'tpmeta-no-flash' );
			var h       = window.location.hash;
			if ( h ) {
				var id      = h.replace( /^#tpmeta-section-/, '' );
				var $sec    = $wrap.find( '#tpmeta-section-' + id );
				var $navBtn = $wrap.find( '.tpmeta-options-nav-item[data-section="' + id + '"]' );
				if ( $sec.length ) {
					// Switch active section silently (no animation on initial paint)
					$wrap.find( '.tpmeta-options-section' ).removeClass( 'is-active' );
					$wrap.find( '.tpmeta-options-nav-item' ).removeClass( 'is-active' );
					$sec.addClass( 'is-active tpmeta-section-no-anim' );
					$navBtn.addClass( 'is-active' );
					// Remove no-anim guard after one frame so future clicks animate
					requestAnimationFrame( function () {
						$sec.removeClass( 'tpmeta-section-no-anim' );
					} );
					if ( history.replaceState ) {
						history.replaceState( null, '', h );
					}
				}
			}
			// Remove the synchronous init style now that JS has taken over
			if ( noFlash ) noFlash.parentNode.removeChild( noFlash );
		}());

		// ---------- Color picker init (handles multicolor + gradient stop inputs) ----------
		function initColorPickers( $scope ) {
			if ( ! $.fn.wpColorPicker ) return;
			$scope.find( '.tm-colorpicker-input' ).each( function () {
				if ( ! $( this ).closest( '.wp-picker-container' ).length ) {
					$( this ).wpColorPicker();
				}
			} );
		}
		initColorPickers( $wrap );

		// ---------- AJAX save ----------
		$saveBtn.on('click', function (e) {
			e.preventDefault();
			// Sync TinyMCE editors to their textarea before serializing.
			if ( typeof tinyMCE !== 'undefined' ) {
				tinyMCE.triggerSave();
			}
			// Flush every repeater so color-picker and gradient sub-fields
			// commit any in-flight state to the hidden JSON carrier before
			// the form is serialized. Each repeater's inline JS listens for
			// this event and calls syncAll() in response.
			$form.find( '.tm-repeater' ).each( function () {
				this.dispatchEvent( new CustomEvent( 'tpmeta:presave', { bubbles: false } ) );
			} );
			if ($saveBtn.hasClass('is-busy')) {
				return;
			}

			$saveBtn.addClass('is-busy');
			$status.removeClass('is-success is-error').text(TPMetaOptions.i18n.saving);

			$.ajax({
				url:  TPMetaOptions.ajaxUrl,
				type: 'POST',
				data: {
					action:   'tpmeta_options_save',
					nonce:    TPMetaOptions.nonce,
					opt_name: TPMetaOptions.optName,
					form:     $form.serialize()
				}
			}).done(function (resp) {
				if (resp && resp.success) {
					$status.addClass('is-success').text(TPMetaOptions.i18n.saved);
				} else {
					$status.addClass('is-error').text(
						(resp && resp.data && resp.data.message) || TPMetaOptions.i18n.saveError
					);
				}
			}).fail(function () {
				$status.addClass('is-error').text(TPMetaOptions.i18n.saveError);
			}).always(function () {
				$saveBtn.removeClass('is-busy');
				setTimeout(function () { $status.text(''); }, 3500);
			});
		});

		// ---------- Image picker ----------
		function tpApplyImage($field, att) {
			var alt     = att.alt || '';
			var altAttr = $('<div>').text(alt).html();
			$field.find('.tpmeta-image-url').val(att.url);
			$field.find('.tpmeta-image-alt').val(alt);
			// Attachment ID is required when return_format = "id" and is
			// additive for "array" — set it whenever the input exists.
			$field.find('.tpmeta-image-id').val(att.id || '');
			tpSyncImageCarrier($field);
			$field.find('.tm-image-img').html('<img src="' + att.url + '" alt="' + altAttr + '" />');
		}

		/**
		 * Keep the JSON carrier in sync with the three semantic inputs.
		 * Required for repeater context — collectRow() reads the carrier
		 * as the canonical sub-field value and would otherwise pick up
		 * only one of the three array-style inputs.
		 */
		function tpSyncImageCarrier($field) {
			var $c = $field.find('.tm-image-carrier');
			if (!$c.length) return;
			var payload = {
				id:  parseInt($field.find('.tpmeta-image-id').val(), 10) || 0,
				url: $field.find('.tpmeta-image-url').val() || '',
				alt: $field.find('.tpmeta-image-alt').val() || ''
			};
			$c.val(JSON.stringify(payload));
			// Native event so the repeater's syncAll() is notified — jQuery
			// .trigger('change') is invisible to addEventListener listeners.
			var node = $c.get(0);
			if (node) node.dispatchEvent(new Event('change', { bubbles: true }));
		}

		$wrap.on('click', '.tpmeta-image-upload', function (e) {
			e.preventDefault();
			var $btn   = $(this);
			var $field = $btn.closest('.tpmeta-image-field');

			var frame = wp.media({
				title:    'Select Image',
				multiple: false,
				library:  { type: 'image' }
			});

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				tpApplyImage($field, att);
				$field.find('.tm-image-container').show();
				$btn.hide();
			});

			frame.open();
		});

		$wrap.on('click', '.tpmeta-image-edit', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.tpmeta-image-field');
			var currentId  = parseInt($field.find('.tpmeta-image-id').val(), 10) || 0;
			var currentUrl = $field.find('.tpmeta-image-url').val() || '';

			var frame = wp.media({
				title:    'Replace Image',
				multiple: false,
				library:  { type: 'image' }
			});

			// Pre-select the current image in the modal so the user sees what
			// they're replacing. Works for two cases:
			//   - currentId is known (Media Library pick) → fetch + reset.
			//   - only currentUrl is known (legacy / pasted URL) → reverse-lookup
			//     by URL; falls back to no selection if not found.
			frame.on('open', function () {
				var selection = frame.state().get('selection');
				if (currentId) {
					var attachment = wp.media.attachment(currentId);
					attachment.fetch();
					selection.reset([attachment]);
					return;
				}
				if (currentUrl && wp.media.query) {
					// Best-effort: query for an attachment matching the URL.
					var lib = wp.media.query({ s: currentUrl, type: 'image', posts_per_page: 1 });
					lib.more().done(function () {
						var first = lib.first();
						if (first) selection.reset([first]);
					});
				}
			});

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				tpApplyImage($field, att);
			});

			frame.open();
		});

		$wrap.on('click', '.tpmeta-image-remove', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.tpmeta-image-field');
			$field.find('.tpmeta-image-url').val('');
			$field.find('.tpmeta-image-alt').val('');
			$field.find('.tpmeta-image-id').val('');
			tpSyncImageCarrier($field);
			$field.find('.tm-image-img').empty();
			$field.find('.tm-image-container').hide();
			$field.find('.tpmeta-image-upload').show();
		});

		// ---------- Color picker — close after Default/Clear ----------
		// WP's Default handler only resets the value; it leaves wp-picker-open and
		// wp-picker-active set. The next swatch click then sees "open" and calls
		// close() instead of open(). Defer so WP's own handler runs first.
		$wrap.on('click', '.wp-picker-default, .wp-picker-clear', function () {
			var $input = $(this).closest('.wp-picker-container').find('.wp-color-picker');
			if ( $input.length ) {
				setTimeout( function () { $input.wpColorPicker('close'); }, 0 );
			}
		});

		// ---------- Color picker — clear display:none BEFORE WP's click handler ----------
		// The outside-click handler in tpmeta-fields.js sets `display:none` on
		// .wp-picker-holder. WP's open() only calls iris('toggle') — it never
		// touches the holder's display — so without this the holder stays hidden
		// on re-open even though iris-picker inside has been re-shown.
		//
		// Using mousedown (capture-phase native listener) guarantees we fire
		// BEFORE WP's click handler regardless of whether it calls
		// event.stopPropagation() on click (which would otherwise block a
		// jQuery-delegated click handler bound on $wrap).
		var wrapEl = $wrap.get(0);
		if (wrapEl) {
			wrapEl.addEventListener('mousedown', function (e) {
				var btn = e.target && e.target.closest
					? e.target.closest('.wp-color-result')
					: null;
				if (!btn || !wrapEl.contains(btn)) return;
				var container = btn.closest('.wp-picker-container');
				if (!container) return;
				var holder = container.querySelector('.wp-picker-holder');
				if (holder) holder.style.display = '';
			}, true);
		}

		// ---------- Color picker — position fixed holder below button ----------
		// position:fixed escapes all overflow:hidden ancestors; JS places it
		// precisely under the swatch button using viewport coordinates.
		$wrap.on('click', '.wp-color-result', function () {
			var btn = this;
			setTimeout(function () {
				var $container = $(btn).closest('.wp-picker-container');
				var $holder    = $container.find('.wp-picker-holder');
				if (!$holder.length) return;
				if (!$(btn).hasClass('wp-picker-open')) {
					// Picker was closed — ensure the holder is hidden so it does
					// not linger as an empty card. Mirrors the mousedown handler.
					$holder.hide();
					return;
				}
				// Belt-and-suspenders: also clear here in case mousedown didn't
				// catch it (e.g. keyboard/Space activation).
				$holder.css('display', '');
				var rect = btn.getBoundingClientRect();
				$holder.css({ top: (rect.bottom + 5) + 'px', left: rect.left + 'px' });
			}, 0);
		});

		// ---------- Single-select checkbox groups (2+ options → only one allowed) ----------
		$wrap.on('change', '.tm-checkbox-group[data-single-select] input[type="checkbox"]', function () {
			if (this.checked) {
				$(this).closest('.tm-checkbox-group')
				       .find('input[type="checkbox"]')
				       .not(this)
				       .prop('checked', false);
			}
		});

		// ---------- Radio Image — keep .is-active in sync ----------
		$wrap.on('change', '.tm-ri-wrap input[type="radio"]', function () {
			var $group = $(this).closest('.tm-ri-wrap');
			$group.find('.tm-ri-card').removeClass('is-active');
			$(this).closest('.tm-ri-card').addClass('is-active');
		});

		// ---------- Select2 init — explicitly exclude radio_image fields ----------
		if ($.fn.select2) {
			$wrap.find('select.select2:not(.tpmeta-post-select)')
				.not('.tpmeta-field-radio_image select, .tpmeta-field-radio_image *')
				.select2({ width: '100%' });

			// Belt-and-suspenders: destroy any Select2 that main.js may have
			// applied globally to a <select> inside a radio_image field wrapper.
			$wrap.find('.tpmeta-field-radio_image select').each(function () {
				if ($(this).data('select2')) {
					$(this).select2('destroy');
				}
			});
			// Also remove any stray Select2 container nodes inside the wrapper.
			$wrap.find('.tpmeta-field-radio_image .select2-container').remove();
		}

		// ---------- Post Select (select2 + AJAX live search) ----------
		$wrap.find('select.tpmeta-post-select').each(function () {
			var $sel        = $(this);
			var ajaxUrl     = $sel.data('ajax-url');
			var nonce       = $sel.data('nonce');
			var postType    = $sel.data('post-type')    || 'post';
			var saveFormat  = $sel.data('save-format')  || 'id';
			var placeholder = $sel.data('placeholder')  || '— Select post —';
			var taxFilter   = $sel.data('tax-filter')   || null;
			var metaFilters = $sel.data('meta-filters') || null;

			if (!$.fn.select2 || !ajaxUrl) return;

			$sel.select2({
				width:              '100%',
				placeholder:        placeholder,
				allowClear:         true,
				minimumInputLength: 0,
				ajax: {
					url:      ajaxUrl,
					type:     'POST',
					dataType: 'json',
					delay:    300,
					data: function (params) {
						return {
							action:       'tpmeta_post_select_search',
							nonce:        nonce,
							post_type:    postType,
							save_format:  saveFormat,
							search:       params.term  || '',
							page:         params.page  || 1,
							tax_filter:   taxFilter   ? JSON.stringify(taxFilter)   : '',
							meta_filters: metaFilters ? JSON.stringify(metaFilters) : '',
						};
					},
					processResults: function (resp) {
						if (!resp || !resp.success) return { results: [] };
						return {
							results:    resp.data.results,
							pagination: { more: resp.data.more },
						};
					},
					cache: true,
				},
			});
		});

		// ---------- Conditional show/hide ----------

		/**
		 * Read the current submitted value of a field by its ID.
		 * Handles checkboxes (switch), selects, and plain inputs.
		 */
		function getFieldCurrentValue(fieldId) {
			// Switch / checkbox
			var $cb = $form.find('input[type="checkbox"][name="' + fieldId + '"]');
			if ($cb.length) {
				if ($cb.is(':checked')) {
					return $cb.val(); // 'on' (string) or 'true' (boolean)
				}
				// Return the "off" equivalent based on data-switch-type.
				return $cb.data('switch-type') === 'boolean' ? 'false' : 'off';
			}

			// Radio group (tabs / radio_buttonset / radio_image / radio).
			// Multiple inputs share the same name, so resolve :checked explicitly —
			// $form.find('[name=X]').val() returns option 1's value, not the checked one.
			var $radios = $form.find('input[type="radio"][name="' + fieldId + '"]');
			if ($radios.length) {
				var $checked = $radios.filter(':checked');
				return $checked.length ? String($checked.val() || '') : '';
			}

			// Post select / regular select
			var $sel = $form.find(
				'select[name="' + fieldId + '"], select[name="' + fieldId + '[]"]'
			);
			if ($sel.length) {
				return String($sel.val() || '');
			}

			// Anything else (text, textarea, color…)
			var $inp = $form.find('[name="' + fieldId + '"]');
			return $inp.length ? String($inp.val() || '') : '';
		}

		/**
		 * Evaluate a single conditional object { field, operator, value }.
		 * Returns true when the field SHOULD be visible.
		 */
		function evaluateCondition(cond) {
			var current = getFieldCurrentValue(cond.field);
			var target  = String(cond.value);

			switch (cond.operator) {
				case '==':  return current === target;
				case '!=':  return current !== target;
				case '>':   return parseFloat(current) > parseFloat(target);
				case '<':   return parseFloat(current) < parseFloat(target);
				case '>=':  return parseFloat(current) >= parseFloat(target);
				case '<=':  return parseFloat(current) <= parseFloat(target);
				default:    return true;
			}
		}

		/** Re-evaluate every conditional field and show/hide accordingly. */
		function updateAllConditionals() {
			$form.find('.tpmeta-panel-field[data-conditional]').each(function () {
				var cond = $(this).data('conditional');
				if (!cond || typeof cond !== 'object') return;
				var show = evaluateCondition(cond);
				$(this).toggle(show);
			});
		}

		// Run once on load, then re-run on any field change.
		updateAllConditionals();

		$form.on('change input', function (e) {
			var $t = $(e.target);
			if ($t.is('input, select, textarea') && $t.attr('name')) {
				updateAllConditionals();
			}
		});
	});
})(jQuery);
