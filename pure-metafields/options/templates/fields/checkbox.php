<?php
/**
 * Field: checkbox (multi)
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$options = isset( $field['options'] ) ? (array) $field['options'] : array();
$current = is_array( $value ) ? array_map( 'strval', $value ) : array();
?>
<div class="tm-checkbox-group<?php echo count( $options ) > 1 ? ' tm-checkbox-single-select' : ''; ?>"<?php echo count( $options ) > 1 ? ' data-single-select="1"' : ''; ?>>
	<?php foreach ( $options as $opt_value => $opt_label ): ?>
		<label class="tm-checkbox-item">
			<input
				type="checkbox"
				name="<?php echo esc_attr( $id ); ?>[]"
				value="<?php echo esc_attr( $opt_value ); ?>"
				<?php checked( in_array( (string) $opt_value, $current, true ) ); ?>
			/>
			<span><?php echo esc_html( $opt_label ); ?></span>
		</label>
	<?php endforeach; ?>
</div>
