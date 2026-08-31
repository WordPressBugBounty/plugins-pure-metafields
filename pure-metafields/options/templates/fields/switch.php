<?php
/**
 * Field: switch
 *
 * Supports two stored-value modes, configurable via $field['data_type']:
 *   'string'  (default) — stores 'on' / 'off'
 *   'boolean'           — stores 'true' / 'false'
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$data_type = isset( $field['data_type'] ) ? $field['data_type'] : 'string';
$is_bool   = ( 'boolean' === $data_type );

if ( $is_bool ) {
	// Accept any of: PHP bool true (after the theme_mod cast filter),
	// integer 1, or the legacy 'true' / 'on' / '1' string tokens.
	$is_on = ( true === $value )
		|| ( 1 === $value )
		|| ( is_string( $value ) && in_array( strtolower( trim( $value ) ), array( 'true', 'on', '1', 'yes' ), true ) );
	$val   = 'true';
} else {
	// String mode also tolerates a real bool in case the field's data_type
	// was just flipped from boolean → string and old values are still cast.
	$is_on = ( 'on' === $value ) || ( true === $value ) || ( 1 === $value );
	$val   = 'on';
}
?>
<label class="tm-switch">
	<input
		type="checkbox"
		id="<?php echo esc_attr( $id ); ?>"
		name="<?php echo esc_attr( $id ); ?>"
		class="<?php echo esc_attr( $id ); ?>"
		value="<?php echo esc_attr( $val ); ?>"
		data-switch-type="<?php echo esc_attr( $data_type ); ?>"
		<?php checked( $is_on ); ?>
	/>
	<span class="tm-slider"></span>
</label>
