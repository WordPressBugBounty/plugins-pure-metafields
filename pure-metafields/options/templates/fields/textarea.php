<?php
/**
 * Field: textarea
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
$rows        = isset( $field['rows'] ) ? absint( $field['rows'] ) : 5;
?>
<textarea
	class="tm-textarea tm-input-sm"
	id="<?php echo esc_attr( $id ); ?>"
	name="<?php echo esc_attr( $id ); ?>"
	rows="<?php echo esc_attr( $rows ); ?>"
	placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
