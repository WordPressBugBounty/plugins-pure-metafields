<?php
/**
 * Field: text
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
?>
<input
	type="text"
	class="tm-input tm-input-sm"
	id="<?php echo esc_attr( $id ); ?>"
	name="<?php echo esc_attr( $id ); ?>"
	value="<?php echo esc_attr( $value ); ?>"
	placeholder="<?php echo esc_attr( $placeholder ); ?>"
/>
