<?php
/**
 * Field: spacing
 * Compact 2×2 grid control (Top/Right/Bottom/Left) with link toggle and unit selector.
 *
 * Default: $field['default'] = ['top'=>'0','right'=>'0','bottom'=>'0','left'=>'0','unit'=>'px']
 *
 * Reading:
 *   $s   = tpmeta_get_option('id');
 *   $u   = $s['unit'] ?? 'px';
 *   $css = "padding:{$s['top']}{$u} {$s['right']}{$u} {$s['bottom']}{$u} {$s['left']}{$u}";
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$units  = array( 'px', 'em', 'rem', '%', 'vh', 'vw' );
$def    = isset( $field['default'] ) && is_array( $field['default'] ) ? $field['default'] : array();
$val    = is_array( $value ) ? $value : array();
$merged = array_merge( array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '', 'unit' => 'px' ), $def, $val );
$unit   = in_array( $merged['unit'], $units, true ) ? $merged['unit'] : 'px';
$sides  = array(
	'top'    => __( 'Top',    'pure-metafields' ),
	'right'  => __( 'Right',  'pure-metafields' ),
	'bottom' => __( 'Bottom', 'pure-metafields' ),
	'left'   => __( 'Left',   'pure-metafields' ),
);
$wrap_id = 'tpspc-' . $id;
?>
<div class="tm-spacing-wrap" id="<?php echo esc_attr( $wrap_id ); ?>">

	<div class="tm-spacing-grid">
		<?php foreach ( $sides as $side => $label ) : ?>
			<div class="tm-spc-cell">
				<input
					type="number"
					class="tm-spc-num"
					data-side="<?php echo esc_attr( $side ); ?>"
					value="<?php echo esc_attr( $merged[ $side ] ); ?>"
					placeholder="—"
					step="any"
				/>
				<span class="tm-spc-lbl"><?php echo esc_html( $label ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="tm-spacing-footer">
		<button
			type="button"
			class="tm-spc-link is-linked"
			title="<?php esc_attr_e( 'Link all sides', 'pure-metafields' ); ?>"
		><span class="dashicons dashicons-admin-links"></span></button>
		<select class="tm-spc-unit-sel">
			<?php foreach ( $units as $u ) : ?>
				<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $unit, $u ); ?>><?php echo esc_html( $u ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<?php
	// CSS Target opt-in is configured entirely in the builder modal now:
	// the developer sets css_target.{enabled,property,selector} at field
	// definition time. No runtime input is rendered here. The wp_head CSS
	// emitter reads field['css_target']['selector']; for legacy data that
	// still has $val['selector'] saved, TPMeta_CSS_Target::build_spacing_rule
	// falls back to it. See [[feedback_storage_shape_normalize_helper]].
	?>

</div>

<?php foreach ( $sides as $side => $label ) : ?>
	<input type="hidden"
		name="<?php echo esc_attr( $id ); ?>[<?php echo esc_attr( $side ); ?>]"
		class="tm-spc-val" data-side="<?php echo esc_attr( $side ); ?>"
		value="<?php echo esc_attr( $merged[ $side ] ); ?>" />
<?php endforeach; ?>
<input type="hidden"
	name="<?php echo esc_attr( $id ); ?>[unit]"
	class="tm-spc-unit-val"
	value="<?php echo esc_attr( $unit ); ?>" />

<script>
(function () {
	var wrap = document.getElementById( '<?php echo esc_js( $wrap_id ); ?>' );
	if ( ! wrap ) return;

	var nums    = Array.from( wrap.querySelectorAll( '.tm-spc-num' ) );
	var unitSel = wrap.querySelector( '.tm-spc-unit-sel' );
	var linkBtn = wrap.querySelector( '.tm-spc-link' );
	var linked  = true;

	var parent = wrap.parentNode;
	function hiddenBySide( side ) { return parent.querySelector( '.tm-spc-val[data-side="' + side + '"]' ); }
	var unitHid = parent.querySelector( '.tm-spc-unit-val' );

	nums.forEach( function ( num ) {
		num.addEventListener( 'input', function () {
			if ( linked ) {
				nums.forEach( function (n) { n.value = num.value; var h = hiddenBySide(n.dataset.side); if(h) h.value = n.value; } );
			} else {
				var h = hiddenBySide( num.dataset.side );
				if ( h ) h.value = num.value;
			}
		} );
	} );

	if ( unitSel ) unitSel.addEventListener( 'change', function () { if ( unitHid ) unitHid.value = unitSel.value; } );
	if ( linkBtn ) linkBtn.addEventListener( 'click',  function () { linked = !linked; linkBtn.classList.toggle( 'is-linked', linked ); } );
} )();
</script>
