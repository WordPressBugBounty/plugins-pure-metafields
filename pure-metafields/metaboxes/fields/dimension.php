<?php
/**
 * Dimension — Elementor-style range slider with number input + unit.
 *
 * Saved value (UNCHANGED contract): scalar string like "150px", "2.5em", "100%".
 * The hidden input with name="<id>" (or name="<id>[]" inside a repeater row)
 * carries this combined string.
 *
 * Config keys (read from the extracted $field array merged into scope):
 *   $units    array  Allowed units. Default: px, em, rem, %, vh, vw.
 *   $min      number Range slider min. Default 0.
 *   $max      number Range slider max. Default 100.
 *   $step     number Range slider step. Default 1.
 *   $default  string Default scalar value, e.g. "16px".
 *
 * @var string $id
 * @var string $default
 * @var mixed  $row_db_value
 * @var array  $units
 * @var mixed  $min
 * @var mixed  $max
 * @var mixed  $step
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$_units = ( isset( $units ) && is_array( $units ) && ! empty( $units ) )
	? array_values( array_filter( $units ) )
	: array( 'px', 'em', 'rem', '%', 'vh', 'vw' );

$_default = isset( $default ) ? (string) $default : '';
$_stored  = isset( $row_db_value )
	? (string) $row_db_value
	: ( is_array( tpmeta_field( $id ) ) ? (string) ( tpmeta_field( $id )[0] ?? '' ) : (string) tpmeta_field( $id ) );
if ( '' === $_stored ) {
	$_stored = $_default;
}

$_min  = isset( $min )  && is_numeric( $min )  ? 0 + $min  : 0;
$_max  = isset( $max )  && is_numeric( $max )  ? 0 + $max  : 100;
$_step = isset( $step ) && is_numeric( $step ) ? 0 + $step : 1;
if ( $_max <= $_min ) { $_max = $_min + 1; }
if ( $_step <= 0 )     { $_step = 1; }

preg_match( '/^(-?[\d.]*)(.*)$/', trim( $_stored ), $_m );
$_num_str = isset( $_m[1] ) ? $_m[1] : '';
$_unit    = isset( $_m[2] ) ? trim( $_m[2] ) : $_units[0];
if ( ! in_array( $_unit, $_units, true ) ) {
	$_unit = $_units[0];
}

$_range_val = is_numeric( $_num_str ) ? 0 + $_num_str : $_min;
if ( $_range_val < $_min ) { $_range_val = $_min; }
if ( $_range_val > $_max ) { $_range_val = $_max; }

$_in_repeater = isset( $row_db_value );
$_hidden_name = $_in_repeater ? ( $id . '[]' ) : $id;
$_wrap_id     = 'tpdim-mb-' . wp_unique_id( $id . '-' );
?>
<div class="tm-dimension-wrap tm-dimension-slider"
	id="<?php echo esc_attr( $_wrap_id ); ?>"
	data-min="<?php echo esc_attr( $_min ); ?>"
	data-max="<?php echo esc_attr( $_max ); ?>"
	data-step="<?php echo esc_attr( $_step ); ?>">

	<input
		type="hidden"
		class="tm-dim-value"
		<?php if ( ! $_in_repeater ) : ?>id="<?php echo esc_attr( $id ); ?>"<?php endif; ?>
		name="<?php echo esc_attr( $_hidden_name ); ?>"
		value="<?php echo esc_attr( $_stored ); ?>"
	/>

	<div class="tm-dim-input-pair">
		<input
			type="number"
			class="tm-input tm-input-sm tm-dim-num"
			min="<?php echo esc_attr( $_min ); ?>"
			max="<?php echo esc_attr( $_max ); ?>"
			step="<?php echo esc_attr( $_step ); ?>"
			value="<?php echo esc_attr( $_num_str ); ?>"
			placeholder="0"
			aria-label="<?php esc_attr_e( 'Value', 'pure-metafields' ); ?>"
		/>
		<select
			class="tm-dim-unit"
			aria-label="<?php esc_attr_e( 'Unit', 'pure-metafields' ); ?>">
			<?php foreach ( $_units as $u ) : ?>
				<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $_unit, $u ); ?>><?php echo esc_html( $u ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<input
		type="range"
		class="tm-dim-range"
		min="<?php echo esc_attr( $_min ); ?>"
		max="<?php echo esc_attr( $_max ); ?>"
		step="<?php echo esc_attr( $_step ); ?>"
		value="<?php echo esc_attr( $_range_val ); ?>"
		aria-label="<?php esc_attr_e( 'Slider', 'pure-metafields' ); ?>"
	/>
</div>
<script>
(function () {
	var w = document.getElementById( '<?php echo esc_js( $_wrap_id ); ?>' );
	if ( ! w ) return;
	var range = w.querySelector( '.tm-dim-range' );
	var num   = w.querySelector( '.tm-dim-num' );
	var sel   = w.querySelector( '.tm-dim-unit' );
	var hid   = w.querySelector( '.tm-dim-value' );

	function sync() {
		var v = ( num.value === '' || num.value === null ) ? '0' : num.value;
		hid.value = v + sel.value;
	}
	if ( range ) {
		range.addEventListener( 'input', function () {
			num.value = range.value;
			sync();
		} );
	}
	num.addEventListener( 'input', function () {
		if ( range && num.value !== '' && ! isNaN( parseFloat( num.value ) ) ) {
			range.value = num.value;
		}
		sync();
	} );
	sel.addEventListener( 'change', sync );
} )();
</script>
