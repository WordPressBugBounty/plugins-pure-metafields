<?php
/**
 * TPMeta_Options_Field
 *
 * Renders a single field row inside an options panel.
 * Loads from options/templates/fields/{type}.php so we can reuse
 * the same CSS classes as the metabox UI without depending on
 * post-meta lookups baked into metaboxes/fields/*.php.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Options_Field {

	/**
	 * Render a single field (row + control).
	 *
	 * @param array $field Field definition.
	 */
	public static function render( $field ) {
		if ( empty( $field['id'] ) || empty( $field['type'] ) ) {
			return;
		}

		$type      = $field['type'];
		$id        = $field['id'];
		$label     = isset( $field['label'] )       ? $field['label']       : '';
		$desc      = isset( $field['description'] ) ? $field['description'] : '';
		$value     = TPMeta_Options_Store::value( $field );

		$template  = TPMETA_PATH . 'options/templates/fields/' . $type . '.php';

		if ( ! file_exists( $template ) ) {
			// Fallback to text for unknown types — keeps the panel from breaking.
			$template = TPMETA_PATH . 'options/templates/fields/text.php';
		}

		$row_classes = array(
			'tm-field-row',
			'tpmeta-field',
			'tpmeta-field-' . sanitize_html_class( $type ),
			sanitize_html_class( $id ),
		);
		?>
		<div class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>" data-field-id="<?php echo esc_attr( $id ); ?>">
			<?php if ( '' !== $label ): ?>
				<label class="label-<?php echo esc_attr( $type ); ?>" for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endif; ?>

			<div class="tpmeta-field-control">
				<?php
				// Make $field, $id, $value, $desc available inside template scope.
				include $template;
				?>
			</div>

			<?php if ( '' !== $desc ): ?>
				<p class="description tpmeta-field-description"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}

/**
 * Convenience wrapper used in the panel template.
 *
 * @param array $field
 */
function tpmeta_render_options_field( $field ) {
	TPMeta_Options_Field::render( $field );
}
