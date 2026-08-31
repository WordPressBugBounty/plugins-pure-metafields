<?php
/**
 * Field: multicheck
 * Modern checkbox group with styled checkmarks and a Select All toggle.
 * Stores an array of selected option values (same storage as 'checkbox').
 *
 * Options: $field['options'] = [ 'val' => 'Label', ... ]
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value  Array of checked values, or '' on first load.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$options = isset( $field['options'] ) ? (array) $field['options'] : array();
$current = is_array( $value ) ? array_map( 'strval', $value )
	: ( is_array( $field['default'] ?? null ) ? array_map( 'strval', (array) $field['default'] ) : array() );

$wrap_id = 'tmmc-' . $id;
?>
<div class="tm-multicheck-wrap" id="<?php echo esc_attr( $wrap_id ); ?>">
	<div class="tm-multicheck-header">
		<label class="tm-multicheck-all-label">
			<input type="checkbox" class="tm-multicheck-toggle-all" />
			<span class="tm-multicheck-checkmark"></span>
			<span class="tm-multicheck-all-text"><?php esc_html_e( 'Select All', 'pure-metafields' ); ?></span>
		</label>
		<span class="tm-multicheck-count"></span>
	</div>
	<div class="tm-multicheck-items">
		<?php foreach ( $options as $opt_val => $opt_label ) : ?>
			<label class="tm-multicheck-item">
				<input
					type="checkbox"
					name="<?php echo esc_attr( $id ); ?>[]"
					value="<?php echo esc_attr( $opt_val ); ?>"
					<?php checked( in_array( (string) $opt_val, $current, true ) ); ?>
					class="tm-multicheck-input"
				/>
				<span class="tm-multicheck-checkmark"></span>
				<span class="tm-multicheck-item-label"><?php echo esc_html( $opt_label ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
</div>
<script>
(function () {
	var wrap  = document.getElementById( '<?php echo esc_js( $wrap_id ); ?>' );
	if ( ! wrap ) return;

	var all   = wrap.querySelector( '.tm-multicheck-toggle-all' );
	var items = wrap.querySelectorAll( '.tm-multicheck-input' );
	var count = wrap.querySelector( '.tm-multicheck-count' );

	function updateState() {
		var checked = Array.from( items ).filter( function (c) { return c.checked; } ).length;
		if ( count ) count.textContent = checked + ' / ' + items.length;
		if ( all ) {
			all.indeterminate = checked > 0 && checked < items.length;
			all.checked = checked === items.length;
		}
	}

	if ( all ) {
		all.addEventListener( 'change', function () {
			items.forEach( function (c) { c.checked = all.checked; } );
			updateState();
		} );
	}

	items.forEach( function (c) {
		c.addEventListener( 'change', updateState );
	} );

	updateState();
} )();
</script>
