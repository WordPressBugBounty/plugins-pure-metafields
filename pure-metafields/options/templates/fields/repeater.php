<?php
/**
 * Field: repeater (Options Panel)
 *
 * PHP renders rows using the same options/templates/fields/* templates
 * as every other options field, so all field types are supported with
 * full fidelity — identical to how the metabox repeater works.
 * A hidden clone-template row is used for "Add Row".
 *
 * Saved as a JSON blob in wp_options / theme_mods.
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value  JSON string or array.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$sub_fields = isset( $field['fields'] ) ? (array) $field['fields']
	: ( isset( $field['sub_fields'] ) ? (array) $field['sub_fields'] : array() );
$columns = isset( $field['columns'] ) ? max( 1, min( 4, (int) $field['columns'] ) ) : 1;

$rows_val = array();
if ( ! empty( $value ) ) {
	$decoded = is_array( $value ) ? $value : json_decode( $value, true );
	if ( is_array( $decoded ) ) $rows_val = $decoded;
}
if ( empty( $rows_val ) && ! empty( $field['default'] ) ) {
	$decoded = is_array( $field['default'] ) ? $field['default'] : json_decode( $field['default'], true );
	if ( is_array( $decoded ) ) $rows_val = $decoded;
}

$wrap_id     = 'tprptr-' . $id;
$json_val    = wp_json_encode( $rows_val ) ?: '[]';
$first_sf_id = ! empty( $sub_fields[0] ) ? $sub_fields[0]['id'] : '';

// The repeater's own (already-unique) field id — used to namespace per-row
// element ids so NESTED repeaters don't emit duplicate DOM ids. Captured into
// a separate variable because the closure reassigns its local $id, and a
// by-value `use ( $id )` would persist that reassignment across calls.
$rptr_field_id = $id;

/**
 * Renders one sub-field inside a repeater row using the real field template.
 * Injects data-sfid onto every input/select/textarea so JS can collect values.
 *
 * @param array $sf          Sub-field definition.
 * @param array $row_val     Stored row data.
 * @param bool  $is_template True when rendering the hidden clone-template row.
 */
$tpmeta_render_rptr_sf = function ( $sf, $row_val, $is_template = false, $row_idx = 0 ) use ( $rptr_field_id ) {
	$sfid    = $sf['id'];
	$sftype  = $sf['type'];
	$sflabel = isset( $sf['label'] ) ? $sf['label'] : '';

	// Variables used by the included template (matches TPMeta_Options_Field::render scope).
	// Use a unique $id per row so complex fields (gradient, datepicker, typography) get unique
	// element IDs and getElementById/widget init doesn't collide across rows. The id is also
	// namespaced with the repeater's own id ($rptr_field_id) so a NESTED repeater (same
	// sub-field id + same inner row index across different outer rows) can't emit duplicate
	// DOM ids — which previously broke typography's getElementById-based combobox/tabs.
	// $data-sfid always uses the original $sfid so the repeater's JS still collects values
	// by sub-field key regardless of the element id.
	$id    = $is_template ? $sfid : $rptr_field_id . '__' . $sfid . '__r' . $row_idx;
	$field = $sf;
	$desc  = isset( $sf['description'] ) ? $sf['description'] : '';
	$value = $is_template
		? ( isset( $sf['default'] ) ? $sf['default'] : '' )
		: ( isset( $row_val[ $sfid ] ) ? $row_val[ $sfid ] : ( isset( $sf['default'] ) ? $sf['default'] : '' ) );

	$tpl = TPMETA_PATH . 'options/templates/fields/' . $sftype . '.php';
	if ( ! file_exists( $tpl ) ) {
		$tpl = TPMETA_PATH . 'options/templates/fields/text.php';
	}

	ob_start();
	include $tpl;
	$html = ob_get_clean();

	// Inject data-sfid into every input/select/textarea opening tag.
	$sfid_attr = esc_attr( $sfid );
	$html = preg_replace_callback(
		'/(<(?:input|select|textarea)\b[^>]*?)(\/?>)/i',
		function ( $m ) use ( $sfid_attr ) {
			if ( false !== strpos( $m[1], 'data-sfid' ) ) return $m[0];
			return $m[1] . ' data-sfid="' . $sfid_attr . '"' . $m[2];
		},
		$html
	);

	echo '<div class="tm-rptr-field">';
	if ( $sflabel ) {
		echo '<label class="tm-rptr-label">' . esc_html( $sflabel ) . '</label>';
	}
	echo '<div class="tpmeta-field-control">' . $html . '</div>';
	echo '</div>';
};
?>

<div class="tm-repeater"
	id="<?php echo esc_attr( $wrap_id ); ?>"
	data-field-id="<?php echo esc_attr( $id ); ?>"
	data-columns="<?php echo esc_attr( $columns ); ?>">

	<div class="tm-repeater-rows">

		<?php
		$count = 0;
		foreach ( $rows_val as $row ) :
			$count++;
			$item_num  = isset( $row['_item_num'] ) && $row['_item_num'] > 0 ? (int) $row['_item_num'] : $count;
			// Title preview only makes sense for scalar string sub-fields.
			$title_val = '';
			if ( ! empty( $first_sf_id ) && isset( $row[ $first_sf_id ] ) && is_string( $row[ $first_sf_id ] ) ) {
				$title_val = $row[ $first_sf_id ];
			}
		?>
		<div class="tm-repeater-row" data-idx="<?php echo esc_attr( $count - 1 ); ?>">
			<div class="tm-rptr-header">
				<span class="tm-rptr-handle" title="<?php esc_attr_e( 'Drag to reorder', 'pure-metafields' ); ?>">
					<svg width="8" height="14" viewBox="0 0 8 14" fill="currentColor"><circle cx="2" cy="2" r="1.4"/><circle cx="6" cy="2" r="1.4"/><circle cx="2" cy="6" r="1.4"/><circle cx="6" cy="6" r="1.4"/><circle cx="2" cy="10" r="1.4"/><circle cx="6" cy="10" r="1.4"/></svg>
				</span>
				<button type="button" class="tm-rptr-toggle">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
				<span class="tm-rptr-title">
					<?php
					echo esc_html( 'Item ' . $item_num );
					if ( $title_val ) echo ' &mdash; ' . esc_html( substr( $title_val, 0, 30 ) );
					?>
				</span>
				<div class="tm-rptr-actions">
					<button type="button" class="tm-rptr-remove" title="<?php esc_attr_e( 'Remove', 'pure-metafields' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>
			<div class="tm-rptr-body">
				<?php foreach ( $sub_fields as $sf ) : $tpmeta_render_rptr_sf( $sf, $row, false, $count - 1 ); endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>

	</div><!-- .tm-repeater-rows -->

	<!-- Hidden clone-template row — JS clones this for every "Add Row" click -->
	<div class="tm-repeater-row tp-hidden-template" style="display:none" aria-hidden="true">
		<div class="tm-rptr-header">
			<span class="tm-rptr-handle" title="<?php esc_attr_e( 'Drag to reorder', 'pure-metafields' ); ?>">
				<svg width="8" height="14" viewBox="0 0 8 14" fill="currentColor"><circle cx="2" cy="2" r="1.4"/><circle cx="6" cy="2" r="1.4"/><circle cx="2" cy="6" r="1.4"/><circle cx="6" cy="6" r="1.4"/><circle cx="2" cy="10" r="1.4"/><circle cx="6" cy="10" r="1.4"/></svg>
			</span>
			<button type="button" class="tm-rptr-toggle">
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<span class="tm-rptr-title"><?php esc_html_e( 'Item 1', 'pure-metafields' ); ?></span>
			<div class="tm-rptr-actions">
				<button type="button" class="tm-rptr-remove" title="<?php esc_attr_e( 'Remove', 'pure-metafields' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</div>
		<div class="tm-rptr-body">
			<?php foreach ( $sub_fields as $sf ) : $tpmeta_render_rptr_sf( $sf, array(), true ); endforeach; ?>
		</div>
	</div>

	<button type="button" class="tm-repeater-add-btn">
		<span class="dashicons dashicons-plus-alt2"></span>
		<?php esc_html_e( 'Add Row', 'pure-metafields' ); ?>
	</button>

</div><!-- .tm-repeater -->

<input type="hidden"
	id="<?php echo esc_attr( $id ); ?>"
	name="<?php echo esc_attr( $id ); ?>"
	class="tm-repeater-value"
	value="<?php echo esc_attr( $json_val ); ?>"
/>

<?php ob_start(); ?>
( function ( $ ) {
	'use strict';

	var WRAP_ID    = '<?php echo esc_js( $wrap_id ); ?>';
	var FIELD_ID   = '<?php echo esc_js( $id ); ?>';
	var FIRST_SFID = '<?php echo esc_js( $first_sf_id ); ?>';
	var wrap       = document.getElementById( WRAP_ID );
	var hidden     = document.getElementById( FIELD_ID );
	if ( ! wrap || ! hidden ) return;

	var rows = [];
	try { rows = JSON.parse( hidden.value ) || []; } catch ( e ) { rows = []; }

	var isDragging = false;

	// Monotonic counter for cloned-instance id suffixes. Never reused, so
	// remove-then-add can't collide (rows.length-based indices repeat after a
	// removal — fine for data order, unsafe for globally-unique element ids).
	var cloneSeq = 0;

	/* ── max _item_num across all rows ──────────────────────────────── */

	function maxItemNum() {
		var m = 0;
		rows.forEach( function ( r ) {
			var n = parseInt( r._item_num, 10 ) || 0;
			if ( n > m ) m = n;
		} );
		return m;
	}

	/* ── collect field values from a single row element ─────────────── */

	// Extract the bracket keys from an input name, e.g.
	//   "field[font_size]"        → [ 'font_size' ]
	//   "field[stops][0][color]"  → [ 'stops', '0', 'color' ]
	//   "field"                   → []
	function bracketKeys( name ) {
		var keys = [], re = /\[([^\]]*)\]/g, m;
		while ( ( m = re.exec( name || '' ) ) ) keys.push( m[ 1 ] );
		return keys;
	}

	// Assign a value into a nested object following a key path. Empty keys
	// (name="x[]" array-append) are unsupported here and cause a no-op, since
	// repeater composites (typography, spacing, multicolor…) use named keys.
	function assignPath( obj, keys, value ) {
		if ( keys.indexOf( '' ) !== -1 || ! keys.length ) return;
		var o = obj;
		for ( var i = 0; i < keys.length - 1; i++ ) {
			if ( o[ keys[ i ] ] == null || typeof o[ keys[ i ] ] !== 'object' ) o[ keys[ i ] ] = {};
			o = o[ keys[ i ] ];
		}
		o[ keys[ keys.length - 1 ] ] = value;
	}

	function collectRow( rowEl ) {
		var data        = {};
		var inputs      = rowEl.querySelectorAll( '[data-sfid]' );
		var hasReal     = {};
		var jsonCarrier = {}; // sfid → hidden input whose value is a JSON blob
		var composite   = {}; // sfid → true when its inputs use name="base[key]" (typography, spacing…)

		// First pass: classify inputs per sfid.
		// A hidden input whose value starts with '{' or '[' is the canonical
		// carrier for complex sub-fields (color_gradient stores its full state
		// as a JSON blob in .tm-gradient-value; similar for other composites).
		// A bracketed name marks a multi-input composite (typography stores
		// font_source/font_family/… as separate name="id[key]" inputs) so we
		// can reconstruct the nested object instead of collapsing to one value.
		inputs.forEach( function ( inp ) {
			var sfid = inp.dataset.sfid;
			if ( ! sfid ) return;
			if ( bracketKeys( inp.name ).length ) composite[ sfid ] = true;
			if ( inp.type === 'hidden' ) {
				var v = inp.value;
				if ( v && ( v.charAt( 0 ) === '{' || v.charAt( 0 ) === '[' ) ) {
					jsonCarrier[ sfid ] = inp;
				}
			} else {
				hasReal[ sfid ] = true;
			}
		} );

		// Second pass: collect values.
		inputs.forEach( function ( inp ) {
			var sfid = inp.dataset.sfid;
			if ( ! sfid ) return;

			// JSON-carrying hidden inputs take priority — use them as the sole
			// value for this sfid and skip all visible sibling inputs (e.g. the
			// gradient angle, stop-colour, and type-radio inputs that all share
			// the same sfid but are internal controls, not the value carrier).
			//
			// PARSE the JSON blob into a real object so the row JSON we ship
			// to the server is cleanly nested. Without this we'd produce
			// JSON-string-in-JSON ("my_gradient":"{\"type\":\"linear\"...}")
			// which is harder to inspect, bloats the payload, and forces the
			// server to do a second decode pass.
			if ( jsonCarrier[ sfid ] ) {
				if ( inp === jsonCarrier[ sfid ] ) {
					try { data[ sfid ] = JSON.parse( inp.value ); }
					catch ( e ) { data[ sfid ] = inp.value; }
				}
				return;
			}

			// Bracket-name composites (typography, spacing, dimension, multicolor…).
			// Rebuild the nested object from each member's name="base[key]" so the
			// row JSON matches what TPMeta_Options_Store + themes expect. Without
			// this the scalar branch below would overwrite data[sfid] with the last
			// input's value — losing every sub-value AND skipping the hidden
			// font_source/font_family carriers (they'd be dropped by the
			// hidden+hasReal rule). Hidden inputs ARE collected here on purpose.
			if ( composite[ sfid ] ) {
				var keys = bracketKeys( inp.name );
				if ( ! keys.length ) return; // aux input with no name key (e.g. native colour picker)
				if ( ( inp.type === 'radio' || inp.type === 'checkbox' ) && ! inp.checked ) return;
				if ( data[ sfid ] == null || typeof data[ sfid ] !== 'object' ) data[ sfid ] = {};
				assignPath( data[ sfid ], keys, inp.value );
				return;
			}

			// Plain hidden inputs are auxiliary when a visible input exists for
			// the same sfid (e.g. WP switch's hidden "off" companion).
			if ( inp.type === 'hidden' && hasReal[ sfid ] ) return;

			if ( inp.tagName === 'INPUT' && inp.type === 'checkbox' ) {
				if ( data[ sfid ] === undefined ) {
					data[ sfid ] = inp.checked ? ( inp.value || 'on' ) : 'off';
				}
			} else if ( inp.tagName === 'SELECT' && inp.multiple ) {
				data[ sfid ] = Array.from( inp.selectedOptions ).map( function ( o ) { return o.value; } );
			} else {
				data[ sfid ] = inp.value;
			}
		} );
		return data;
	}

	/* ── rebuild rows[] from DOM then update hidden JSON input ───────── */

	function syncAll() {
		var container = wrap.querySelector( '.tm-repeater-rows' );
		var rowEls    = container.querySelectorAll( '.tm-repeater-row' );
		var newRows   = [];

		rowEls.forEach( function ( rowEl, i ) {
			var idx     = parseInt( rowEl.dataset.idx, 10 );
			var rowBase = ( ! isNaN( idx ) && rows[ idx ] )
				? JSON.parse( JSON.stringify( rows[ idx ] ) )
				: {};
			Object.assign( rowBase, collectRow( rowEl ) );
			if ( ! rowBase._item_num ) rowBase._item_num = i + 1;
			newRows.push( rowBase );
		} );

		rows = newRows;

		// Keep data-idx sequential so subsequent syncAll calls stay correct.
		rowEls.forEach( function ( rowEl, i ) { rowEl.dataset.idx = i; } );

		hidden.value = JSON.stringify( rows );
		hidden.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	/* ── update row header title from first sub-field value ──────────── */

	function updateRowTitle( rowEl ) {
		if ( ! FIRST_SFID ) return;
		var firstInp = rowEl.querySelector( '[data-sfid="' + FIRST_SFID + '"]' );
		var val      = firstInp ? firstInp.value : '';
		var idx      = parseInt( rowEl.dataset.idx, 10 );
		var itemNum  = ( rows[ idx ] && rows[ idx ]._item_num ) ? rows[ idx ]._item_num : ( idx + 1 );
		var t        = rowEl.querySelector( '.tm-rptr-title' );
		if ( t ) t.textContent = 'Item ' + itemNum + ( val ? ' — ' + val.substring( 0, 30 ) : '' );
	}

	/* ── colour pickers ──────────────────────────────────────────────── */

	function initColorPickers( container ) {
		if ( ! window.jQuery || ! $.fn.wpColorPicker ) return;
		$( container ).find( '.tm-colorpicker-input:not(.wp-color-picker)' ).each( function () {
			if ( $( this ).closest( '.tp-hidden-template' ).length ) return;
			// Gradient stop inputs are handled by window.TPMetaGradient.boot — skip here
			// so they get the gradient-specific syncStops/preview callbacks, not just syncAll.
			if ( $( this ).closest( '.tm-gradient-wrap' ).length ) return;
			var $inp = $( this );
			$inp.wpColorPicker( {
				change: function ( event, ui ) {
					$inp.val( ui.color.toString() );
					syncAll();
				}
			} );
		} );
		$( document ).off( 'mousedown.tprptr' + WRAP_ID ).on( 'mousedown.tprptr' + WRAP_ID, function ( e ) {
			if ( ! $( e.target ).closest( '.wp-picker-container' ).length ) {
				$( '#' + WRAP_ID ).find( '.wp-picker-container.wp-picker-active' ).each( function () {
					$( this ).removeClass( 'wp-picker-active' ).find( '.wp-picker-holder' ).hide();
					$( this ).find( '.wp-color-result' ).attr( 'aria-expanded', 'false' ).removeClass( 'wp-picker-open' );
				} );
			}
		} );
	}

	/* ── datepickers ─────────────────────────────────────────────────── */

	var TPMETA_RPTR_DATE_FORMAT    = 'mm/dd/yy';
	var TPMETA_RPTR_ACCEPT_FORMATS = [ 'mm/dd/yy', 'MM d, yy', 'M d, yy', 'yy-mm-dd', 'dd/mm/yy', 'D, M d, yy' ];

	function tpmetaRptrParseDateLenient( str ) {
		if ( ! str || ! $.datepicker ) return null;
		str = String( str ).trim();
		if ( ! str ) return null;
		for ( var i = 0; i < TPMETA_RPTR_ACCEPT_FORMATS.length; i++ ) {
			try {
				var d = $.datepicker.parseDate( TPMETA_RPTR_ACCEPT_FORMATS[ i ], str );
				if ( d ) return d;
			} catch ( e ) {}
		}
		var t = Date.parse( str );
		return isNaN( t ) ? null : new Date( t );
	}

	function initDatepickers( container ) {
		if ( ! window.jQuery || ! $.fn.datepicker ) return;
		$( container ).find( '.tm-datepicker-input' ).each( function () {
			if ( $( this ).closest( '.tp-hidden-template' ).length ) return;
			// Normalise any legacy long/ISO value so the popup pre-selects
			// the correct day. New values written by the picker are already
			// in canonical mm/dd/yy.
			if ( this.value ) {
				var d = tpmetaRptrParseDateLenient( this.value );
				if ( d ) this.value = $.datepicker.formatDate( TPMETA_RPTR_DATE_FORMAT, d );
			}
			$( this ).datepicker( {
				dateFormat: TPMETA_RPTR_DATE_FORMAT,
				onSelect: function () {
					// Native bubbling event — jQuery trigger() only fires
					// jQuery-bound handlers; dispatchEvent() fires both jQuery
					// and native addEventListener listeners (the repeater's
					// bindRow uses the latter).
					this.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				},
				beforeShow: function () {
					setTimeout( function () {
						$( '#ui-datepicker-div' ).addClass( 'tm-datepicker' );
					}, 0 );
				}
			} );
		} );
	}

	/* ── gradient re-init helper ─────────────────────────────────────── */

	function initGradients( container ) {
		if ( ! window.TPMetaGradient ) return;
		container.querySelectorAll( '.tm-gradient-wrap' ).forEach( function ( gradWrap ) {
			if ( gradWrap.closest( '.tp-hidden-template' ) ) return;
			var gradHidden = gradWrap.parentNode && gradWrap.parentNode.querySelector( '.tm-gradient-value' );
			if ( gradHidden ) window.TPMetaGradient.boot( gradWrap, gradHidden );
		} );
	}

	/* ── typography re-init helper ───────────────────────────────────── */

	// Typography is a self-contained composite whose inline <script> only runs
	// at page load. Cloned rows therefore have dead source tabs / Google-font
	// dropdowns until TPMetaTypography.boot() rebinds them. boot() is idempotent
	// (guards on data-tptypoBooted), so calling it on already-live page-load
	// rows is a harmless no-op.
	function initTypography( container ) {
		if ( ! window.TPMetaTypography || ! window.TPMetaTypography.boot ) return;
		container.querySelectorAll( '.tptypo-wrap' ).forEach( function ( w ) {
			if ( w.closest( '.tp-hidden-template' ) ) return;
			window.TPMetaTypography.boot( w );
		} );
	}

	/* ── select2 ─────────────────────────────────────────────────────── */

	function initSelect2( container ) {
		if ( ! window.jQuery || ! $.fn.select2 ) return;
		$( container ).find( 'select.select2, select.tm-select-field' ).each( function () {
			if ( $( this ).closest( '.tp-hidden-template' ).length ) return;
			if ( $( this ).hasClass( 'select2-hidden-accessible' ) ) return;
			$( this ).select2( { width: '100%' } ).on( 'change', function () {
				syncAll();
			} );
		} );
	}

	/* ── bind events for a single row ───────────────────────────────── */

	function bindRow( rowEl ) {
		var toggle = rowEl.querySelector( '.tm-rptr-toggle' );
		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				if ( isDragging ) return;
				rowEl.classList.toggle( 'is-collapsed' );
			} );
		}

		var remove = rowEl.querySelector( '.tm-rptr-remove' );
		if ( remove ) {
			remove.addEventListener( 'click', function () {
				if ( ! confirm( '<?php echo esc_js( __( 'Remove this row?', 'pure-metafields' ) ); ?>' ) ) return;
				rowEl.remove();
				syncAll();
				initSortable( wrap.querySelector( '.tm-repeater-rows' ) );
			} );
		}

		// Single delegated 'change' listener. Catches every [data-sfid] update
		// in this row — including:
		//   • normal text/select/checkbox edits
		//   • select2's bubbled change on the underlying <select>
		//   • datepicker's onSelect-triggered change
		//   • SYNTHETIC change events dispatched by complex sub-fields whose
		//     value lives in a hidden input (color_gradient calls
		//     hidden.dispatchEvent(new Event('change', {bubbles:true})) inside
		//     its update() — we listen at the row level so the bubble lands here)
		rowEl.addEventListener( 'change', function ( e ) {
			var t = e.target;
			if ( ! t || ! t.dataset || ! t.dataset.sfid ) return;
			syncAll();
			updateRowTitle( rowEl );
		} );

		// Live 'input' updates so typing in text/number/textarea sub-fields
		// streams into the repeater hidden as the user types.
		rowEl.addEventListener( 'input', function ( e ) {
			var t = e.target;
			if ( ! t || ! t.dataset || ! t.dataset.sfid ) return;
			if ( t.type === 'hidden' || t.type === 'checkbox' ) return;
			if ( t.classList && t.classList.contains( 'tm-colorpicker-input' ) ) return;
			syncAll();
			updateRowTitle( rowEl );
		} );
	}

	/* ── drag sort via dragula ───────────────────────────────────────── */

	function initSortable( container ) {
		if ( typeof dragula === 'undefined' ) return;
		if ( container._dragulaInstance ) {
			try { container._dragulaInstance.destroy(); } catch ( e ) {}
		}
		var drake = dragula( [ container ], {
			moves:   function ( el, src, handle ) { return !! handle.closest( '.tm-rptr-handle' ); },
			invalid: function ( el ) { return el.classList.contains( 'tp-hidden-template' ); },
			direction: 'vertical'
		} );
		drake.on( 'drag', function () {
			isDragging = true;
			var w = container.getBoundingClientRect().width;
			document.documentElement.style.setProperty( '--gu-mirror-w', w + 'px' );
		} );
		drake.on( 'dragend', function () {
			setTimeout( function () { isDragging = false; }, 50 );
		} );
		drake.on( 'drop', function () { syncAll(); } );
		container._dragulaInstance = drake;
	}

	/* ── strip initialised widget state from a cloned row ───────────── */

	function stripColorPickerClones( container ) {
		if ( ! window.jQuery ) return;
		// Replace each wp-picker-container wrapper with a fresh plain <input>.
		// main.js runs tpInitColorpicker() globally at load time, which wraps
		// every .tm-colorpicker-input (including those in the hidden template row)
		// in a wp-picker-container + Iris widget. cloneNode copies the full DOM
		// structure but NOT event listeners, so cloned pickers look initialised
		// but are completely dead. Replacing them here lets initColorPickers and
		// initGradients reinitialise them with proper callbacks.
		$( container ).find( '.wp-picker-container' ).each( function () {
			var $c   = $( this );
			var $inp = $c.find( 'input.wp-color-picker' );
			var val  = $inp.val() || '';
			var def  = $inp.data( 'default-color' ) || val;
			var sfid = $inp.data( 'sfid' ) || '';
			// Gradient stop inputs live inside .tm-grd-stop and need different classes.
			var cls  = $c.closest( '.tm-grd-stop' ).length
				? 'tm-input tm-colorpicker-input tpgrd-stop-color'
				: 'tm-input tm-input-sm tm-colorpicker-input';
			var attrs = { type: 'text', 'class': cls, value: val, 'data-default-color': def };
			if ( sfid ) attrs[ 'data-sfid' ] = sfid;
			$c.replaceWith( $( '<input>', attrs ) );
		} );
	}

	/* ── "Add Row" — clone hidden template ──────────────────────────── */

	wrap.querySelector( '.tm-repeater-add-btn' ).addEventListener( 'click', function () {
		var tmpl = wrap.querySelector( '.tp-hidden-template' );
		if ( ! tmpl ) return;

		var newRow = tmpl.cloneNode( true );
		newRow.classList.remove( 'tp-hidden-template' );
		newRow.removeAttribute( 'aria-hidden' );
		newRow.style.display = '';

		var newItemNum = maxItemNum() + 1;
		var newIdx     = rows.length;
		newRow.dataset.idx = newIdx;

		var t = newRow.querySelector( '.tm-rptr-title' );
		if ( t ) t.textContent = 'Item ' + newItemNum;

		// Destroy any stale select2 state copied from the template clone.
		if ( window.jQuery && $.fn.select2 ) {
			$( newRow ).find( '.select2-hidden-accessible' ).each( function () {
				try { $( this ).select2( 'destroy' ); } catch ( e ) {}
			} );
		}

		wrap.querySelector( '.tm-repeater-rows' ).appendChild( newRow );

		var newRowData = { _item_num: newItemNum };
		rows.push( newRowData );
		hidden.value = JSON.stringify( rows );
		hidden.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		bindRow( newRow );
		// Strip DOM state that main.js copied into the clone via cloneNode.
		// main.js initialises colorpickers / datepickers globally (including on
		// the hidden template row). cloneNode copies the resulting DOM structure
		// and CSS classes but NOT event listeners, leaving cloned widgets dead.
		// Stripping them here lets the init functions start completely fresh.
		stripColorPickerClones( newRow );
		if ( window.jQuery ) {
			$( newRow ).find( '.tm-datepicker-input' ).each( function () {
				$( this ).removeClass( 'hasDatepicker' );
				// jQuery UI datepicker renders day-cell onclick with '#' + input.id.
				// If the template and cloned row share the same id, clicking a date
				// targets the template's (hidden) input — so the cloned input never
				// receives the value and syncAll() is never triggered.  Make unique.
				if ( this.id ) this.id = this.id + '__n' + newIdx;
			} );
		}
		initColorPickers( newRow );
		initSelect2( newRow );
		// Give each cloned gradient wrap a unique ID before booting it, so its
		// getElementById calls don't collide with the (still-hidden) template row.
		newRow.querySelectorAll( '.tm-gradient-wrap' ).forEach( function ( gradWrap ) {
			gradWrap.id = ( gradWrap.id || 'tpgrd' ) + '__n' + newIdx;
		} );
		initGradients( newRow );
		initDatepickers( newRow );
		// Give each cloned typography instance a globally-unique id tree, clear
		// the stale boot flag copied by cloneNode, then (re)bind its handlers.
		// All of typography's lookups are getElementById( wrap.id + '_' + … ),
		// so the wrap id AND every descendant id must be re-prefixed together.
		newRow.querySelectorAll( '.tptypo-wrap' ).forEach( function ( w ) {
			var oldUid = w.id;
			if ( oldUid ) {
				var newUid = oldUid + '__n' + ( ++cloneSeq );
				w.id = newUid;
				w.querySelectorAll( '[id]' ).forEach( function ( n ) {
					if ( n.id.indexOf( oldUid ) === 0 ) n.id = newUid + n.id.slice( oldUid.length );
				} );
			}
			delete w.dataset.tptypoBooted;
			w.removeAttribute( 'data-tptypo-booted' );
		} );
		initTypography( newRow );
		initSortable( wrap.querySelector( '.tm-repeater-rows' ) );
	} );

	/* ── boot ────────────────────────────────────────────────────────── */

	wrap.querySelectorAll( '.tm-repeater-row:not(.tp-hidden-template)' ).forEach( function ( rowEl ) {
		bindRow( rowEl );
	} );
	initColorPickers( wrap );
	initSelect2( wrap );
	initGradients( wrap );
	initDatepickers( wrap );
	initTypography( wrap );
	initSortable( wrap.querySelector( '.tm-repeater-rows' ) );

	// Pre-save flush: the options-panel save handler dispatches this event on
	// every .tm-repeater element before serializing the form, so any color-
	// picker or gradient state that hasn't triggered a 'change' event yet
	// (e.g. user typed a hex value but didn't blur) is committed to the
	// hidden JSON input before the POST is sent.
	wrap.addEventListener( 'tpmeta:presave', function () { syncAll(); } );

} )( jQuery );
<?php wp_add_inline_script( 'dragula', ob_get_clean() ); ?>
