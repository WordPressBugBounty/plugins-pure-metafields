<?php
/**
 * Field: colorpicker
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$default = isset( $field['default'] ) ? $field['default'] : '';
?>
<input
	type="text"
	class="tm-input tm-input-sm tm-colorpicker-input"
	id="<?php echo esc_attr( $id ); ?>"
	name="<?php echo esc_attr( $id ); ?>"
	value="<?php echo esc_attr( '' !== $value ? $value : $default ); ?>"
	data-default-color="<?php echo esc_attr( $default ); ?>"
/>
