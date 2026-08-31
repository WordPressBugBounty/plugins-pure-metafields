<?php
/**
 * Gallery (user meta)
 *
 * Storage shape: comma-separated string of attachment IDs in user_meta —
 * identical to the metabox storage so `tpmeta_gallery_images()` works on
 * the value if the caller wants it.
 *
 * @var string $id
 * @var string $label
 * @var int    $user_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$_saved = get_user_meta( $user_id, $id, true );
$_saved = is_string( $_saved ) ? $_saved : '';
?>
<tr>
    <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
    <td>
        <div class="tm-gallery-field">
            <input
                type="hidden"
                name="<?php echo esc_attr( $id ); ?>"
                id="<?php echo esc_attr( $id ); ?>"
                value="<?php echo esc_attr( $_saved ); ?>"
            />
            <div class="tm-gallery-container" id="<?php echo esc_attr( $id ); ?>-g-container">
                <?php if ( '' !== $_saved ) :
                    foreach ( explode( ',', $_saved ) as $image_id ) :
                        $image_id  = (int) $image_id;
                        if ( ! $image_id ) continue;
                        $image_src = wp_get_attachment_image_src( $image_id, 'full' );
                        if ( ! $image_src ) continue;
                ?>
                <div class="tm-gallery-item" data-image_id="<?php echo esc_attr( $image_id ); ?>">
                    <div class="tm-gallery-img">
                        <img src="<?php echo esc_url( $image_src[0] ); ?>" alt=""/>
                    </div>
                    <div class="tm-gallery-actions">
                        <a data-attachment-id="<?php echo esc_attr( $image_id ); ?>" href="#" class="tm-edit" title="<?php esc_attr_e( 'Edit', 'pure-metafields' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <a data-attachment-id="<?php echo esc_attr( $image_id ); ?>" href="#" class="tm-delete" title="<?php esc_attr_e( 'Remove', 'pure-metafields' ); ?>">
                            <span class="dashicons dashicons-no-alt"></span>
                        </a>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <button id="<?php echo esc_attr( $id ); ?>-gallery" type="button">
                <span class="dashicons dashicons-format-gallery"></span>
                <span><?php echo esc_html__( 'Add Gallery', 'pure-metafields' ); ?></span>
            </button>
        </div>
        <?php if ( ! empty( $description ) ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </td>
</tr>
<script type="text/javascript">
;(function($){
    "use strict";
    $( document ).ready(function(){
        var frame;
        var $hidden    = $('#<?php echo esc_js( $id ); ?>');
        var $container = $('#<?php echo esc_js( $id ); ?>-g-container');

        $('#<?php echo esc_js( $id ); ?>-gallery').on('click', function(){
            if ( frame ) { frame.open(); return false; }
            frame = wp.media({
                title:    'Choose images for your gallery',
                button:   { text: 'Add Images' },
                multiple: true
            });
            frame.on('select', function(){
                var attachments = frame.state().get('selection').toJSON();
                var ids = $hidden.val() !== '' ? $hidden.val().split(',') : [];
                attachments.map(function(el){
                    ids.push(el.id);
                    var url = (el.sizes && el.sizes.full && el.sizes.full.url) ? el.sizes.full.url : el.url;
                    $container.append(
                        '<div class="tm-gallery-item" data-image_id="' + el.id + '">' +
                            '<div class="tm-gallery-img"><img src="' + url + '" alt=""/></div>' +
                            '<div class="tm-gallery-actions">' +
                                '<a data-attachment-id="' + el.id + '" href="#" class="tm-edit" title="Edit"><span class="dashicons dashicons-edit"></span></a>' +
                                '<a data-attachment-id="' + el.id + '" href="#" class="tm-delete" title="Remove"><span class="dashicons dashicons-no-alt"></span></a>' +
                            '</div>' +
                        '</div>'
                    );
                });
                $hidden.val( ids.join(',') );
                sortableGallery();
            });
            frame.open();
            return;
        });

        $(document).on('click', '#<?php echo esc_js( $id ); ?>-g-container .tm-gallery-actions > a.tm-delete', function(e){
            e.preventDefault();
            var selected = $(this).data('attachment-id');
            var ids = $hidden.val().split(',').filter(function(id){ return String(id) !== String(selected); });
            $hidden.val( ids.join(',') );
            $(this).closest('.tm-gallery-item').remove();
        });

        function sortableGallery(){
            if ( typeof dragula === 'undefined' ) return;
            var drake = dragula([ document.getElementById('<?php echo esc_js( $id ); ?>-g-container') ], {
                direction:'horizontal', revertOnSpill:true, removeOnSpill:false
            });
            drake.on('drop', function(el, target){
                if ( ! target || ! $(target).hasClass('tm-gallery-container') ) return;
                var ids = [];
                $(target).children('.tm-gallery-item').each(function(){ ids.push( $(this).data('image_id') ); });
                $hidden.val( ids.join(',') );
            });
        }
        sortableGallery();
    });
})(jQuery);
</script>
