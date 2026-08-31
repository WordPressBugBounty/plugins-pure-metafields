/* global wp, jQuery, tinymce */
/**
 * tpmeta-customize-tinymce.js
 *
 * Why tinymce.init({ selector }) and NOT wp.editor.initialize():
 *   wp.editor.initialize() silently returns early when `tinyMCEPreInit` is
 *   undefined. tinyMCEPreInit is only emitted by _WP_Editors::editor_js()
 *   when wp_editor() was called at least once server-side. Our template
 *   approach never calls wp_editor(), so wp.editor.initialize() is always
 *   a no-op. We call tinymce.init() directly — no such restriction.
 *
 * Why selector not target:
 *   `target` (element reference) was added in TinyMCE 5. WordPress ships
 *   TinyMCE 4.x where it is silently ignored. Always use `selector`.
 *
 * Timing (Kirki pattern):
 *   control.deferred.embedded resolves when the section first opens and the
 *   Underscore template is rendered into live DOM. We THEN check the section
 *   is expanded so TinyMCE has a visible parent to measure.
 */
( function ( $, api ) {
	'use strict';

	api.controlConstructor['tpmeta_tinymce'] = api.Control.extend( {

		ready: function () {
			var control = this;

			// deferred.embedded resolves inside actuallyEmbed() right after
			// renderContent() places the template HTML in the container.
			control.deferred.embedded.done( function () {
				var sectionId = typeof control.section === 'function'
					? control.section() : null;
				var section   = sectionId ? api.section( sectionId ) : null;

				if ( ! section ) {
					control.initEditor();
					return;
				}
				// Section may already be open when deferred fires (1st open).
				if ( section.expanded() ) {
					control.initEditor();
				}
				// Bind for collapse → re-expand cycles.
				section.expanded.bind( function ( isOpen ) {
					if ( isOpen ) control.initEditor();
				} );
			} );
		},

		initEditor: function () {
			var control   = this;
			var editorId  = control.params.editor_id;
			var settingId = control.params.setting_id;
			var $wrap     = control.container.find( '.tpmeta-editor-field' );

			if ( ! $wrap.length ) return;

			// Already booted — just refresh visibility.
			if ( window.tinymce && tinymce.get( editorId ) ) {
				var existing = tinymce.get( editorId );
				var c = existing.getContainer ? existing.getContainer() : null;
				if ( c ) c.style.visibility = 'visible';
				try { existing.execCommand( 'mceRepaint' ); } catch ( e ) {}
				return;
			}

			// Wait for the tinymce global (wp_enqueue_editor loads it async).
			if ( typeof window.tinymce === 'undefined' ) {
				var poll = 0;
				var t    = setInterval( function () {
					if ( typeof window.tinymce !== 'undefined' ) {
						clearInterval( t );
						control.bootEditor( editorId, settingId, $wrap );
					} else if ( ++poll > 25 ) {
						clearInterval( t );
					}
				}, 200 );
				return;
			}

			control.bootEditor( editorId, settingId, $wrap );
		},

		bootEditor: function ( editorId, settingId, $wrap ) {
			// The textarea must be VISIBLE when tinymce.init() runs — TinyMCE 4
			// measures the parent during init and leaves the container as
			// visibility:hidden if the parent is display:none.
			var $ta = $wrap.find( '#' + editorId );
			if ( ! $ta.length ) return;

			// Ensure the textarea is visible for TinyMCE to measure.
			$ta.css( { display: 'block', visibility: 'visible' } );

			tinymce.init( {
				selector:  '#' + editorId,  // TinyMCE 4 API (not `target`)
				plugins:   'lists link paste textcolor',
				toolbar:   'bold italic | bullist numlist | link | forecolor | removeformat',
				menubar:   false,
				statusbar: false,
				resize:    false,
				height:    220,
				skin:      'lightgray',

				init_instance_callback: function ( editor ) {
					// TinyMCE sets visibility:hidden during init — clear it.
					var c = editor.getContainer();
					if ( c ) c.style.visibility = 'visible';

					// Inject Visual / Text tab bar above the editor container.
					var $mceWrap = $( c );
					if ( ! $wrap.find( '.tpmeta-editor-tabs' ).length ) {
						var $tabs = $( '<div class="tpmeta-editor-tabs">'
							+ '<button type="button" class="tpmeta-tab is-active" data-mode="visual">Visual</button>'
							+ '<button type="button" class="tpmeta-tab" data-mode="text">Text</button>'
							+ '</div>' );
						$mceWrap.before( $tabs );

						$tabs.on( 'click', '.tpmeta-tab', function () {
							var mode = $( this ).data( 'mode' );
							$tabs.find( '.tpmeta-tab' ).removeClass( 'is-active' );
							$( this ).addClass( 'is-active' );

							if ( 'visual' === mode ) {
								// Sync textarea → editor then show TinyMCE.
								var ed = tinymce.get( editorId );
								if ( ed ) {
									ed.setContent( $ta.val() );
									ed.show();
								}
							} else {
								// Flush editor → textarea then show plain textarea.
								var ed2 = tinymce.get( editorId );
								if ( ed2 ) ed2.hide(); // save() + show textarea is built-in
								$ta.focus();
							}
						} );
					}
				},

				setup: function ( editor ) {
					editor.on( 'change keyup undo redo NodeChange', function () {
						editor.save();
						// Push to wp.customize setting.
						var setting = wp.customize( settingId );
						if ( setting ) setting.set( editor.getContent() );
					} );
				}
			} );

			// Text (HTML) mode sync — fires when textarea is directly edited.
			$ta.on( 'input keyup change', function () {
				var setting = wp.customize( settingId );
				if ( setting ) setting.set( this.value );
			} );
		}
	} );

}( jQuery, wp.customize ) );
