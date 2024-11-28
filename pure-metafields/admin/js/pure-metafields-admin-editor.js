// Make sure these are globally available if you prefer ES5
const { data } = wp;
const { domReady } = wp;

jQuery(document).ready(function($) {
    wp.domReady(() => {
        data.subscribe(() => {
            const currentFormat = wp.data.select('core/editor').getEditedPostAttribute('format');
            $('[data-post-format]').filter(function() {
                return $(this).data('post-format') !== '';
            }).each(function() {
                let metaBoxId = $(this).data('metabox-id');
                if ($(this).data('post-format') === currentFormat) {
                    $(`#${metaBoxId}`).show();
                } else {
                    $(`#${metaBoxId}`).hide();
                }
            });
        });
    });
});
