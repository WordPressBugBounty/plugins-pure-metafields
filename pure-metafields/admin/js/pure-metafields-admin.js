(function( $, wp ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	document.addEventListener('DOMContentLoaded', function() {
		jQuery(function($) {
			$('[data-post-format]').filter(function() {
				return $(this).data('post-format') !== '';
			}).each(function() {
				let metaBoxId = $(this).data('metabox-id');
				if($('#post-formats-select input[name="post_format"]:checked').val() !== $(this).data('post-format')) {
					$(`#${metaBoxId}`).hide();
				}
			});
		});
	});	

	$(document).on('change', '#post-formats-select input[name="post_format"]', function(e) {
		e.preventDefault();
		let format = $(this).val();
		$('[data-post-format]').filter(function() {
			return $(this).data('post-format') !== '';
		}).each(function() {
			let metaBoxId = $(this).data('metabox-id');
			if ($(this).data('post-format') === format) {
				$(`#${metaBoxId}`).show();
			} else {
				$(`#${metaBoxId}`).hide();
			}
		});
	});

})( jQuery );
