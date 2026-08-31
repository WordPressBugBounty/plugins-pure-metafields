<?php
/**
 * Field: dimension — Elementor-style range slider with number input + unit.
 *
 * Stored value (UNCHANGED contract): scalar string like "150px", "2.5em", "100%".
 * The hidden input with name="<id>" carries this combined string.
 *
 * Config keys:
 *   $field['units']   array  Allowed units. Default: px, em, rem, %, vh, vw.
 *   $field['min']     number Range slider min. Default 0.
 *   $field['max']     number Range slider max. Default 100.
 *   $field['step']    number Range slider step. Default 1.
 *   $field['default'] string Default scalar value, e.g. "16px".
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$units = isset( $field['units'] ) && is_array( $field['units'] ) && ! empty( $field['units'] )
	? array_values( array_filter( $field['units'] ) )
	: array( 'px', 'em', 'rem', '%', 'vh', 'vw' );

$default = isset( $field['default'] ) ? (string) $field['default'] : '';
$stored  = '' !== (string) $value ? (string) $value : $default;

$min  = isset( $field['min'] )  && is_numeric( $field['min'] )  ? 0 + $field['min']  : 0;
$max  = isset( $field['max'] )  && is_numeric( $field['max'] )  ? 0 + $field['max']  : 100;
$step = isset( $field['step'] ) && is_numeric( $field['step'] ) ? 0 + $field['step'] : 1;
if ( $max <= $min ) { $max = $min + 1; }
if ( $step <= 0 )    { $step = 1; }

// Parse "150px" → num="150", unit="px"
preg_match( '/^(-?[\d.]*)(.*)$/', trim( $stored ), $m );
$num_str = isset( $m[1] ) ? $m[1] : '';
$unit    = isset( $m[2] ) ? trim( $m[2] ) : $units[0];
if ( ! in_array( $unit, $units, true ) ) {
	$unit = $units[0];
}

// Range slider thumb position: use numeric value if present, otherwise min.
$range_val = is_numeric( $num_str ) ? 0 + $num_str : $min;
if ( $range_val < $min ) { $range_val = $min; }
if ( $range_val > $max ) { $range_val = $max; }

$wrap_id = 'tpdim-' . $id;
?>
<div class="tm-dimension-wrap tm-dimension-slider"
	id="<?php echo esc_attr( $wrap_id ); ?>"
	data-min="<?php echo esc_attr( $min ); ?>"
	data-max="<?php echo esc_attr( $max ); ?>"
	data-step="<?php echo esc_attr( $step ); ?>">

	<input
		type="hidden"
		id="<?php echo esc_attr( $id ); ?>"
		name="<?php echo esc_attr( $id ); ?>"
		class="tm-dim-value"
		value="<?php echo esc_attr( $stored ); ?>"
	/>

	<div class="tm-dim-input-pair">
		<input
			type="number"
			class="tm-input tm-dim-num"
			min="<?php echo esc_attr( $min ); ?>"
			max="<?php echo esc_attr( $max ); ?>"
			step="<?php echo esc_attr( $step ); ?>"
			value="<?php echo esc_attr( $num_str ); ?>"
			placeholder="0"
			aria-label="<?php esc_attr_e( 'Value', 'pure-metafields' ); ?>"
		/>
		<select
			class="tm-dim-unit"
			aria-label="<?php esc_attr_e( 'Unit', 'pure-metafields' ); ?>">
			<?php foreach ( $units as $u ) : ?>
				<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $unit, $u ); ?>><?php echo esc_html( $u ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<input
		type="range"
		class="tm-dim-range"
		min="<?php echo esc_attr( $min ); ?>"
		max="<?php echo esc_attr( $max ); ?>"
		step="<?php echo esc_attr( $step ); ?>"
		value="<?php echo esc_attr( $range_val ); ?>"
		aria-label="<?php esc_attr_e( 'Slider', 'pure-metafields' ); ?>"
	/>
</div>
<script>
(function () {
	var w = document.getElementById( '<?php echo esc_js( $wrap_id ); ?>' );
	if ( ! w ) return;
	var range = w.querySelector( '.tm-dim-range' );
	var num   = w.querySelector( '.tm-dim-num' );
	var sel   = w.querySelector( '.tm-dim-unit' );
	var hid   = w.querySelector( '.tm-dim-value' );

	function fireChange( el ) {
		// Native event so delegated listeners (e.g. Customizer's
		// bindScalarField) see the hidden input's new value.
		try { el.dispatchEvent( new Event( 'change', { bubbles: true } ) ); }
		catch ( e ) {
			var ev = document.createEvent( 'HTMLEvents' );
			ev.initEvent( 'change', true, false );
			el.dispatchEvent( ev );
		}
	}

	function sync() {
		var v = ( num.value === '' || num.value === null ) ? '0' : num.value;
		hid.value = v + sel.value;
		fireChange( hid );
	}

	if ( range ) {
		range.addEventListener( 'input', function () {
			num.value = range.value;
			sync();
		} );
	}

	num.addEventListener( 'input', function () {
		// Keep the slider thumb in sync when the typed value is within range.
		if ( range && num.value !== '' && ! isNaN( parseFloat( num.value ) ) ) {
			range.value = num.value;
		}
		sync();
	} );

	sel.addEventListener( 'change', sync );
} )();
</script>
