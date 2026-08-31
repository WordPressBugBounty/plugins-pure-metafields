<?php
/**
 * Field: radio_image
 * Visual card-style image radio selector.
 *
 * Options (from builder loader):
 *   $field['options'] = [ 'value_key' => 'https://…/image.jpg', … ]
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$options = isset( $field['options'] ) ? (array) $field['options'] : array();
$current = '' !== (string) $value
	? (string) $value
	: ( isset( $field['default'] ) ? (string) $field['default'] : '' );

// Auto-select first option when no value is saved yet.
if ( '' === $current && ! empty( $options ) ) {
	$current = (string) array_key_first( $options );
}
?>
<div class="tm-ri-wrap" id="tmri-<?php echo esc_attr( $id ); ?>">

	<?php if ( empty( $options ) ) : ?>
		<div class="tm-ri-empty">
			<span class="dashicons dashicons-format-image"></span>
			<p><?php esc_html_e( 'No image choices defined. Open the Options Builder and add images to this field.', 'pure-metafields' ); ?></p>
		</div>

	<?php else : ?>
		<?php foreach ( $options as $opt_val => $opt_url ) :
			$checked    = ( $current === (string) $opt_val );
			$valid_url  = $opt_url && filter_var( $opt_url, FILTER_VALIDATE_URL );
		?>
		<label class="tm-ri-card<?php echo $checked ? ' is-active' : ''; ?>">

			<input
				type="radio"
				name="<?php echo esc_attr( $id ); ?>"
				value="<?php echo esc_attr( $opt_val ); ?>"
				<?php checked( $checked ); ?>
			/>

			<div class="tm-ri-thumb">
				<?php if ( $valid_url ) : ?>
					<img
						src="<?php echo esc_url( $opt_url ); ?>"
						alt="<?php echo esc_attr( $opt_val ); ?>"
						loading="lazy"
					/>
				<?php else : ?>
					<div class="tm-ri-thumb-placeholder">
						<span class="dashicons dashicons-format-image"></span>
					</div>
				<?php endif; ?>

				<!-- Selected checkmark badge -->
				<div class="tm-ri-badge">
					<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="9" cy="9" r="9" fill="white"/>
						<path d="M5 9L7.5 12L13 6" stroke="#3362FF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>

			<span class="tm-ri-label"><?php echo esc_html( $opt_val ); ?></span>

		</label>
		<?php endforeach; ?>
	<?php endif; ?>

</div>
