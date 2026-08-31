<?php
/**
 * Field: multicolor  –  color swatch checkboxes
 *
 * Data model:
 *   $field['options'] = { slot_key => css_color }
 *     - slot_key  : PHP identifier used to retrieve the value (e.g. 'primary')
 *     - css_color : any valid CSS color — hex, rgb(), named (e.g. 'red', '#3362FF')
 *       This value is BOTH the swatch background-color AND the stored value.
 *
 *   $field['default'] = { slot_key => css_color }  (pre-selected swatches on first load)
 *
 * Reading: tpmeta_get_option('id')  →  { slot_key => css_color } for SELECTED swatches only.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Slots: { slot_key => css_color }  (label IS the color after builder normalization)
$options = isset( $field['options'] ) && is_array( $field['options'] ) && count( $field['options'] )
	? $field['options']
	: ( isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array() );

// Default selection: { slot_key => css_color } for pre-checked swatches.
$_raw_def = isset( $field['default'] ) ? $field['default'] : array();
if ( is_string( $_raw_def ) && '' !== $_raw_def ) {
	$_decoded = json_decode( $_raw_def, true );
	$_raw_def = is_array( $_decoded ) ? $_decoded : array();
}
$def = is_array( $_raw_def ) ? $_raw_def : array();

// Saved value (get_theme_mod falls back to $default on first load).
$val = is_array( $value ) ? $value : array();

// JSON carrier: keeps selected map for the options-panel repeater's collectRow().
// The carrier has no name so it doesn't interfere with the standalone form POST.
// data-sfid is injected by the repeater renderer so collectRow detects it.
?>
<input type="hidden"
	class="tm-multicolor-carrier tm-multicolor-carrier-<?php echo esc_attr( $id ); ?>"
	value="<?php echo esc_attr( wp_json_encode( $val ) ); ?>"
/>
<div class="tm-multicolor-group">
	<?php foreach ( $options as $key => $slot_color ) :
		// The label value IS the CSS color.
		$color      = ( '' !== $slot_color ) ? $slot_color : '#cccccc';
		$is_checked = isset( $val[ $key ] );
	?>
	<label class="tm-mcswatch-item">
		<input
			type="checkbox"
			class="tm-mcswatch-input tm-mc-rpt-picker"
			name="<?php echo esc_attr( $id ); ?>[<?php echo esc_attr( $key ); ?>]"
			data-mc-carrier="<?php echo esc_attr( $id ); ?>"
			data-mc-key="<?php echo esc_attr( $key ); ?>"
			data-mc-color="<?php echo esc_attr( $color ); ?>"
			value="<?php echo esc_attr( $color ); ?>"
			<?php checked( $is_checked ); ?>
		/>
		<span class="tm-mcswatch-color" style="background-color:<?php echo esc_attr( $color ); ?>"></span>
		<span class="tm-mcswatch-label"><?php echo esc_html( $key ); ?></span>
	</label>
	<?php endforeach; ?>
</div>
