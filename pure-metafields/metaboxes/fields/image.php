<?php
/**
 * Image
 *
 * Variables below are populated at runtime via extract() by the field
 * renderer — declared here for static analysis (Intelephense).
 *
 * @var string $id
 * @var string $post_type
 * @var string $placeholder
 * @var string $default
 * @var string $bind
 * @var mixed  $row_db_value
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Normalise an image-field stored value into a [id, url] pair regardless of
 * the field's `return_format`:
 *   'array' → ['id'=>int, 'url'=>'…', 'alt'=>'…']
 *   'id'    → integer attachment ID
 *   'url'   → URL string (may be a portable token like '{{theme_url}}/img.jpg')
 *   legacy  → comma-separated ID string (only the first ID is used here —
 *             the metabox image template was always single-image)
 *
 * Returns ['id' => int, 'url' => string, 'form_value' => string] where
 * `form_value` is what the hidden input should post back so the existing
 * dedicated save branch can re-process it (preferring the ID when known).
 */
if ( ! function_exists( 'tpmeta_metabox_image_normalize' ) ) {
    function tpmeta_metabox_image_normalize( $raw ) {
        $out = array( 'id' => 0, 'url' => '', 'form_value' => '' );
        if ( is_array( $raw ) ) {
            $out['id']  = isset( $raw['id'] )  ? (int) $raw['id']  : 0;
            $out['url'] = isset( $raw['url'] ) ? (string) $raw['url'] : '';
        } elseif ( is_int( $raw ) ) {
            $out['id'] = (int) $raw;
        } elseif ( is_string( $raw ) && '' !== $raw ) {
            // Token URL — keep verbatim, don't try to resolve to an ID.
            if ( false !== strpos( $raw, '{{' ) ) {
                $out['url'] = $raw;
            } elseif ( filter_var( $raw, FILTER_VALIDATE_URL ) ) {
                $out['url'] = $raw;
            } elseif ( ctype_digit( $raw ) ) {
                $out['id'] = (int) $raw;
            } elseif ( false !== strpos( $raw, ',' ) ) {
                // Legacy comma-separated IDs — take the first.
                $first = trim( strtok( $raw, ',' ) );
                if ( ctype_digit( $first ) ) $out['id'] = (int) $first;
            }
        }
        // Cross-fill from attachment when we have an ID but no URL.
        if ( $out['id'] && '' === $out['url'] ) {
            $url = wp_get_attachment_image_url( $out['id'], 'full' );
            if ( $url ) $out['url'] = (string) $url;
        }
        // Resolve portable tokens for the <img src> only — keep the token in form_value.
        $out['form_value'] = $out['id']
            ? (string) $out['id']
            : (string) $out['url'];
        if ( '' !== $out['url'] && false !== strpos( $out['url'], '{{' ) && function_exists( 'tpmeta_resolve_image_url' ) ) {
            $out['url'] = (string) tpmeta_resolve_image_url( $out['url'] );
        }
        return $out;
    }
}
?>
<?php if(isset($row_db_value)):
$tpmeta_img = tpmeta_metabox_image_normalize( $row_db_value );
?>
<div class="tm-image-field">
    <input
        type="hidden"
        name="<?php echo esc_attr($id); ?>[]"
        class="<?php echo esc_attr($id); ?> tm-image-value"
        value="<?php echo esc_attr( $tpmeta_img['form_value'] ); ?>"/>

    <div class="tm-image-container">
        <?php if ( '' !== $tpmeta_img['url'] ) : ?>
        <div class="tm-image-item">
            <div class="tm-image-prev">
                <img src="<?php echo esc_url( $tpmeta_img['url'] ); ?>" alt=""/>
            </div>
            <div class="tm-image-actions">
                <a data-attachment-id="<?php echo esc_attr( $tpmeta_img['id'] ); ?>" href="#" class="tm-delete">
                    <span class="dashicons dashicons-no-alt"></span>
                </a>
                <a data-attachment-id="<?php echo esc_attr( $tpmeta_img['id'] ); ?>" href="#" class="tm-edit">
                    <span class="dashicons dashicons-edit"></span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <button class="tm-add-image" type="button">
        <span class="">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M13.4444 1H2.55556C1.69645 1 1 1.69645 1 2.55556V13.4444C1 14.3036 1.69645 15 2.55556 15H13.4444C14.3036 15 15 14.3036 15 13.4444V2.55556C15 1.69645 14.3036 1 13.4444 1Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M5.27799 6.44466C5.92233 6.44466 6.44466 5.92233 6.44466 5.27799C6.44466 4.63366 5.92233 4.11133 5.27799 4.11133C4.63366 4.11133 4.11133 4.63366 4.11133 5.27799C4.11133 5.92233 4.63366 6.44466 5.27799 6.44466Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M14.9991 10.3332L11.1102 6.44434L2.55469 14.9999" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        <span><?php echo esc_html__('Add Image', 'pure-metafields'); ?></span>
    </button>
</div>
<?php else:
$tpmeta_img = tpmeta_metabox_image_normalize( tpmeta_field( $id ) );
?>
<div class="tm-gallery-field">
    <input
        type="hidden"
        name="<?php echo esc_attr($id); ?>"
        id="<?php echo esc_attr($id); ?>"
        value="<?php echo esc_attr( $tpmeta_img['form_value'] ); ?>"/>
    <div class="tm-gallery-container" id="<?php echo esc_attr($id); ?>-g-container">
    <?php if ( '' !== $tpmeta_img['url'] ) : ?>
    <div class="tm-image-item">
        <div class="tm-image-prev">
            <img src="<?php echo esc_url( $tpmeta_img['url'] ); ?>" alt=""/>
        </div>
        <div class="tm-image-actions">
            <a data-attachment-id="<?php echo esc_attr( $tpmeta_img['id'] ); ?>" href="#" class="tm-delete"><span class="dashicons dashicons-no-alt"></span></a>
            <a data-attachment-id="<?php echo esc_attr( $tpmeta_img['id'] ); ?>" href="#" class="tm-edit"><span class="dashicons dashicons-edit"></span></a>
        </div>
    </div>
    <?php endif; ?>
    </div>
    <button id="<?php echo esc_attr($id); ?>-image" type="button">
        <span class="dashicons dashicons-format-gallery"></span>
        <span><?php echo esc_html__('Add Image', 'pure-metafields'); ?></span>
    </button>
</div>

<script type="text/javascript">
    (function($){
        $(document).ready(function(){
            var frame, editFrame;

            $('#<?php echo esc_attr($id); ?>-image').on('click', function(){
                if(frame){
                    frame.open()
                    return false;
                }

                frame = wp.media({
                    title:'Select an image',
                    button:{
                        text:'Add Image'
                    },
                    multiple:false
                })

                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    // Use the original uploaded URL so the preview shows the
                    // actual file (no WP crop/resize). attachment.sizes.full.url
                    // is identical to attachment.url for the original asset;
                    // we fall back to attachment.url when 'full' is missing.
                    var attchmentURL = (attachment.sizes && attachment.sizes.full && attachment.sizes.full.url)
                        ? attachment.sizes.full.url
                        : attachment.url;
                    
                    $('#<?php echo esc_attr($id); ?>-g-container').html(`
                    <div class="tm-image-item">
                        <div class="tm-image-prev">
                            <img src="${attchmentURL}" alt=""/>
                        </div>
                        <div class="tm-image-actions">
                            <a data-attachment-id="${attachment.id}" href="#" class="tm-delete"><span class="dashicons dashicons-no-alt"></span></a>
                            <a data-attachment-id="${attachment.id}" href="#" class="tm-edit"><span class="dashicons dashicons-edit"></span></a>
                        </div>
                    </div>
                    `)
                    
                    $('#<?php echo esc_attr($id); ?>').val(attachment.id)


                    $('.tm-image-actions > a.tm-delete').on('click', function(e){
                        e.preventDefault();
                        var selected = $( e.target ).closest('.tm-gallery-field');
                        var input = selected.find('input[type="hidden"]');
                        var imageItem = selected.find('.tm-image-item');
                        input.val('');
                        imageItem.remove();
                    })
                    
                })


                frame.on('open', function(){
                    
                })


                frame.open()
                return;
            })


            $('.tm-image-actions > a.tm-delete').click(function(e){
                e.preventDefault();
                var selected = $( e.target ).closest('.tm-gallery-field');
                var input = selected.find('input[type="hidden"]');
                var imageItem = selected.find('.tm-image-item');
                input.val('');
                imageItem.remove();
            })
        })
    })(jQuery)
</script>
<?php endif; ?>
