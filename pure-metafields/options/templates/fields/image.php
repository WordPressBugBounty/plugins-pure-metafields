<?php
/**
 * Field: image (single attachment)
 *
 * Storage: associative array { url, alt }. The template also accepts the two
 * legacy formats and renders a preview, then re-saves as the array shape.
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value Array { url, alt } | URL string | attachment ID.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * The form ALWAYS POSTs back the array shape { id, url, alt } via the three
 * hidden inputs below. The sanitizer reads return_format on the field def
 * and re-shapes to scalar (id or url) before storage, so the template stays
 * shape-agnostic.
 *
 * Read-side: $value here may already be a scalar (return_format = id|url
 * stored that way) or an array (return_format = array). We normalize to:
 *   - $form_url    : token / URL written into the hidden input (round-trips)
 *   - $form_id     : attachment ID written into the hidden input
 *   - $image_alt   : alt text
 *   - $preview_url : token-resolved URL for the <img src>
 */
$form_url    = '';
$form_id     = 0;
$image_alt   = '';
if ( is_array( $value ) ) {
	$form_url  = isset( $value['url'] ) ? (string) $value['url'] : '';
	$image_alt = isset( $value['alt'] ) ? (string) $value['alt'] : '';
	$form_id   = isset( $value['id'] )  ? absint( $value['id'] )  : 0;
} else {
	$value_str = (string) $value;
	if ( '' !== $value_str ) {
		if ( false !== strpos( $value_str, '{{' ) ) {
			// Portable token (return_format = url with a token).
			$form_url = $value_str;
		} elseif ( filter_var( $value_str, FILTER_VALIDATE_URL ) ) {
			// Plain URL (return_format = url with a literal URL).
			$form_url = $value_str;
		} elseif ( absint( $value_str ) ) {
			// Attachment ID (return_format = id, or legacy ID storage).
			$form_id   = absint( $value_str );
			$form_url  = (string) wp_get_attachment_image_url( $form_id, 'medium' );
			$image_alt = (string) get_post_meta( $form_id, '_wp_attachment_image_alt', true );
		}
	}
}
// Build the preview URL from the form URL, resolving tokens for display only.
$preview_url = $form_url;
if ( '' !== $preview_url && function_exists( 'tpmeta_resolve_image_url' ) && false !== strpos( $preview_url, '{{' ) ) {
	$preview_url = (string) tpmeta_resolve_image_url( $preview_url );
}
$has_image = (bool) $preview_url;
?>
<?php
// JSON carrier for repeater context. The repeater's collectRow() detects any
// hidden input whose value starts with '{' as the canonical sub-field carrier
// — same pattern multicolor uses. NO name attribute so the standalone options
// form POST is unaffected (the [url]/[alt]/[id] inputs are still the carriers
// there). data-sfid is auto-injected by the repeater renderer.
$_carrier_json = wp_json_encode( array(
	'id'  => (int) $form_id,
	'url' => $form_url,
	'alt' => $image_alt,
) );
?>
<div class="tm-image-field tpmeta-image-field" data-field-id="<?php echo esc_attr( $id ); ?>">
<input type="hidden" class="tm-image-carrier" value="<?php echo esc_attr( $_carrier_json ); ?>" />
	<div class="tm-image-container" style="<?php echo $has_image ? '' : 'display:none;'; ?>">
		<div class="tm-image-item">
			<div class="tm-image-img">
				<?php if ( $has_image ): ?>
					<img src="<?php echo esc_url( $preview_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" />
				<?php endif; ?>
			</div>
			<div class="tm-image-actions">
				<a href="#" class="tpmeta-image-edit" aria-label="<?php esc_attr_e( 'Edit image', 'pure-metafields' ); ?>"><span class="dashicons dashicons-edit"></span></a>
				<a href="#" class="tpmeta-image-remove" aria-label="<?php esc_attr_e( 'Remove image', 'pure-metafields' ); ?>">&times;</a>
			</div>
		</div>
	</div>
	<button type="button" class="button tm-add-image tpmeta-image-upload" style="<?php echo $has_image ? 'display:none;' : ''; ?>">
		<?php esc_html_e( 'Choose Image', 'pure-metafields' ); ?>
	</button>
	<input
		type="hidden"
		id="<?php echo esc_attr( $id ); ?>"
		name="<?php echo esc_attr( $id ); ?>[url]"
		value="<?php echo esc_attr( $form_url ); ?>"
		class="tpmeta-image-url"
	/>
	<input
		type="hidden"
		name="<?php echo esc_attr( $id ); ?>[alt]"
		value="<?php echo esc_attr( $image_alt ); ?>"
		class="tpmeta-image-alt"
	/>
	<input
		type="hidden"
		name="<?php echo esc_attr( $id ); ?>[id]"
		value="<?php echo esc_attr( $form_id ?: '' ); ?>"
		class="tpmeta-image-id"
	/>
</div>
