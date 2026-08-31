<?php
/**
 * Field: color_gradient
 * Linear / radial gradient builder with live preview.
 * Stored as JSON: {"type":"linear","angle":135,"stops":[{"color":"#3362FF","pos":0},{"color":"#5F4AFE","pos":100}],"css":"..."}
 *
 * Reading: $g = json_decode(tpmeta_get_option('id'), true); echo $g['css'];
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value  JSON string or array.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$fallback = array(
	'type'  => 'linear',
	'angle' => 135,
	'stops' => array(
		array( 'color' => '#3362FF', 'pos' => 0 ),
		array( 'color' => '#5F4AFE', 'pos' => 100 ),
	),
	'css'   => 'linear-gradient(135deg, #3362FF 0%, #5F4AFE 100%)',
);

$val = array();
if ( ! empty( $value ) ) {
	$decoded = is_array( $value ) ? $value : json_decode( wp_unslash( (string) $value ), true );
	if ( is_array( $decoded ) ) {
		$val = $decoded;
	}
}
if ( empty( $val ) && ! empty( $field['default'] ) ) {
	$decoded = is_array( $field['default'] ) ? $field['default'] : json_decode( wp_unslash( (string) $field['default'] ), true );
	if ( is_array( $decoded ) ) {
		$val = $decoded;
	}
}
if ( empty( $val ) ) {
	$val = $fallback;
}

$g_type  = in_array( $val['type'] ?? 'linear', array( 'linear', 'radial' ), true ) ? $val['type'] : 'linear';
$g_angle = max( 0, min( 360, (int) ( $val['angle'] ?? 135 ) ) );
$g_stops = array_values( array_filter( (array) ( $val['stops'] ?? array() ), 'is_array' ) );
if ( count( $g_stops ) < 2 ) {
	$g_stops = $fallback['stops'];
}
$g_css  = isset( $val['css'] ) ? $val['css'] : '';

$wrap_id = 'tpgrd-' . $id;
$json    = wp_json_encode( array( 'type' => $g_type, 'angle' => $g_angle, 'stops' => $g_stops, 'css' => $g_css ) );
?>
<div class="tm-gradient-wrap" id="<?php echo esc_attr( $wrap_id ); ?>">

	<div class="tm-gradient-preview" style="background:<?php echo esc_attr( $g_css ?: 'linear-gradient(135deg,#3362FF 0%,#5F4AFE 100%)' ); ?>"></div>

	<div class="tm-gradient-controls">

		<div class="tm-gradient-row">
			<div class="tm-gradient-field">
				<span class="tm-typo-label"><?php esc_html_e( 'Type', 'pure-metafields' ); ?></span>
				<div class="tm-button-groups">
					<label class="tm-button-radio">
						<input type="radio" class="tpgrd-type" name="tpgrd-type-<?php echo esc_attr( $wrap_id ); ?>" value="linear" <?php checked( $g_type, 'linear' ); ?> />
						<span><?php esc_html_e( 'Linear', 'pure-metafields' ); ?></span>
					</label>
					<label class="tm-button-radio">
						<input type="radio" class="tpgrd-type" name="tpgrd-type-<?php echo esc_attr( $wrap_id ); ?>" value="radial" <?php checked( $g_type, 'radial' ); ?> />
						<span><?php esc_html_e( 'Radial', 'pure-metafields' ); ?></span>
					</label>
				</div>
			</div>
			<div class="tm-gradient-field tm-grd-angle-wrap">
				<span class="tm-typo-label"><?php esc_html_e( 'Angle', 'pure-metafields' ); ?></span>
				<div class="tm-dimension-wrap" style="max-width:120px;">
					<input type="number" class="tm-input tm-dim-num tpgrd-angle" min="0" max="360" value="<?php echo esc_attr( $g_angle ); ?>" />
					<select class="tm-dim-unit" style="flex:0 0 50px;width:50px!important;"><option>deg</option></select>
				</div>
			</div>
		</div>

		<div class="tm-gradient-stops" data-wrap="<?php echo esc_attr( $wrap_id ); ?>">
			<?php foreach ( $g_stops as $si => $stop ) : ?>
			<div class="tm-grd-stop">
				<input
					type="text"
					class="tm-input tm-colorpicker-input tpgrd-stop-color"
					value="<?php echo esc_attr( $stop['color'] ?? '#000000' ); ?>"
					data-default-color="<?php echo esc_attr( $stop['color'] ?? '#000000' ); ?>"
				/>
				<div class="tm-dimension-wrap" style="max-width:110px;">
					<input type="number" class="tm-input tm-dim-num tpgrd-stop-pos" min="0" max="100" value="<?php echo esc_attr( $stop['pos'] ?? 0 ); ?>" />
					<select class="tm-dim-unit" style="flex:0 0 46px;width:46px!important;"><option>%</option></select>
				</div>
				<?php if ( $si >= 2 ) : ?>
				<button type="button" class="tpgrd-remove-stop" title="<?php esc_attr_e( 'Remove stop', 'pure-metafields' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="tpgrd-add-stop">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Add Stop', 'pure-metafields' ); ?>
		</button>

	</div>
</div>
<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" class="tm-gradient-value" value="<?php echo esc_attr( $json ); ?>" />
<?php ob_start(); ?>
/* ── TPMetaGradient.boot — reusable per-instance gradient initialiser ─────
   Defined once; called once per rendered instance (including cloned rows).
   Takes the wrap element and hidden-input element directly so multiple
   instances with different IDs never collide via getElementById.           */
window.TPMetaGradient = window.TPMetaGradient || {};
if ( ! window.TPMetaGradient.boot ) {
	window.TPMetaGradient.boot = function ( wrap, hidden ) {
		if ( ! wrap || ! hidden ) return;
		var preview = wrap.querySelector( '.tm-gradient-preview' );

		var state = {};
		try { state = JSON.parse( hidden.value || '{}' ); } catch(e) {}
		if ( ! state.stops ) {
			state = { type:'linear', angle:135, stops:[{color:'#3362FF',pos:0},{color:'#5F4AFE',pos:100}], css:'' };
		}

		function buildCSS() {
			var stops = state.stops.map( function(s) { return s.color + ' ' + s.pos + '%'; } ).join( ', ' );
			return state.type === 'radial'
				? 'radial-gradient(circle, ' + stops + ')'
				: 'linear-gradient(' + state.angle + 'deg, ' + stops + ')';
		}

		function update() {
			state.css = buildCSS();
			hidden.value = JSON.stringify( state );
			hidden.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			if ( preview ) preview.style.background = state.css;
		}

		function syncStops() {
			state.stops = [];
			wrap.querySelectorAll( '.tm-grd-stop' ).forEach( function( row ) {
				var colorInp = row.querySelector( '.tpgrd-stop-color' );
				var posInp   = row.querySelector( '.tpgrd-stop-pos' );
				state.stops.push( {
					color: colorInp ? colorInp.value : '#000000',
					pos:   posInp   ? parseInt( posInp.value, 10 ) : 0,
				} );
			} );
			update();
		}

		function initColorPickerStop( input ) {
			if ( ! window.jQuery || ! jQuery.fn.wpColorPicker ) return;
			if ( input.closest( '.wp-picker-container' ) ) return;
			jQuery( input ).wpColorPicker( {
				change: function ( ev, ui ) {
					jQuery( input ).val( ui.color.toString() );
					syncStops();
				},
				clear: function () { syncStops(); }
			} );
		}

		function addStopRow( color, pos, removable ) {
			var stopsWrap = wrap.querySelector( '.tm-gradient-stops' );
			var div = document.createElement( 'div' );
			div.className = 'tm-grd-stop';
			div.innerHTML = '<input type="text" class="tm-input tm-colorpicker-input tpgrd-stop-color" value="' + color + '" data-default-color="' + color + '" />'
				+ '<div class="tm-dimension-wrap" style="max-width:110px;"><input type="number" class="tm-input tm-dim-num tpgrd-stop-pos" min="0" max="100" value="' + pos + '" /><select class="tm-dim-unit" style="flex:0 0 46px;width:46px!important;"><option>%</option></select></div>'
				+ ( removable ? '<button type="button" class="tpgrd-remove-stop"><span class="dashicons dashicons-no-alt"></span></button>' : '' );
			stopsWrap.appendChild( div );
			initColorPickerStop( div.querySelector( '.tpgrd-stop-color' ) );
			div.querySelector( '.tpgrd-stop-pos' ).addEventListener( 'input', syncStops );
			var rem = div.querySelector( '.tpgrd-remove-stop' );
			if ( rem ) rem.addEventListener( 'click', function() { div.remove(); syncStops(); } );
		}

		wrap.querySelectorAll( '.tpgrd-stop-color' ).forEach( initColorPickerStop );
		wrap.querySelectorAll( '.tpgrd-stop-pos' ).forEach( function(inp) { inp.addEventListener( 'input', syncStops ); } );
		wrap.querySelectorAll( '.tpgrd-remove-stop' ).forEach( function(btn) {
			btn.addEventListener( 'click', function() { btn.closest('.tm-grd-stop').remove(); syncStops(); } );
		} );
		wrap.querySelectorAll( '.tpgrd-type' ).forEach( function(r) {
			r.addEventListener( 'change', function() {
				// Enforce mutual exclusivity within this wrap. Native radio-group
				// deselection requires a shared name, but cloned repeater rows
				// inherit the same name value, merging groups across rows. Handling
				// it here keeps each wrap independent regardless of cloning.
				wrap.querySelectorAll( '.tpgrd-type' ).forEach( function(other) {
					other.checked = other === r;
				} );
				state.type = r.value;
				var aw = wrap.querySelector( '.tm-grd-angle-wrap' );
				if ( aw ) aw.style.opacity = r.value === 'radial' ? '.4' : '1';
				update();
			} );
		} );
		var angleInp = wrap.querySelector( '.tpgrd-angle' );
		if ( angleInp ) angleInp.addEventListener( 'input', function() { state.angle = parseInt( angleInp.value, 10 ) || 0; update(); } );
		var addBtn = wrap.querySelector( '.tpgrd-add-stop' );
		if ( addBtn ) addBtn.addEventListener( 'click', function() { addStopRow( '#cccccc', 50, true ); syncStops(); } );
		if ( state.type === 'radial' ) {
			var aw = wrap.querySelector( '.tm-grd-angle-wrap' );
			if ( aw ) aw.style.opacity = '.4';
		}
	};
}
/* Boot this specific instance — skip if we are inside the hidden clone-template
   row so the template's inputs stay as plain elements and can be freshly
   initialised (with their own closure scope) after cloning.                */
(function () {
	var wrap   = document.getElementById( '<?php echo esc_js( $wrap_id ); ?>' );
	var hidden = document.getElementById( '<?php echo esc_js( $id ); ?>' );
	if ( ! wrap || ! hidden ) return;
	if ( wrap.closest && wrap.closest( '.tp-hidden-template' ) ) return;
	window.TPMetaGradient.boot( wrap, hidden );
} )();
<?php wp_add_inline_script( 'wp-color-picker', ob_get_clean() ); ?>
