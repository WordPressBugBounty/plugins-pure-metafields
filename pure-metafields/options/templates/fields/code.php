<?php
/**
 * Field: code
 * Monospace code editor textarea with syntax-language badge, tab-key support, and copy button.
 *
 * Options:
 *   $field['language'] — language hint badge: 'css', 'js', 'php', 'html', etc. (default 'css')
 *   $field['rows']     — textarea height in rows (default 12)
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$language = isset( $field['language'] ) ? strtolower( sanitize_key( $field['language'] ) ) : 'css';
$rows     = isset( $field['rows'] ) ? max( 4, (int) $field['rows'] ) : 12;
$content  = '' !== (string) $value ? (string) $value
	: ( isset( $field['default'] ) ? (string) $field['default'] : '' );
$wrap_id  = 'tmcode-' . $id;

$lang_colors = array(
	'css'        => '#569cd6',
	'js'         => '#f0db4f',
	'javascript' => '#f0db4f',
	'php'        => '#9b59b6',
	'html'       => '#e44d26',
	'json'       => '#6a9955',
	'text'       => '#6b7280',
);
$badge_color = isset( $lang_colors[ $language ] ) ? $lang_colors[ $language ] : '#6b7280';
?>
<div class="tm-code-editor-wrap" id="<?php echo esc_attr( $wrap_id ); ?>">
	<div class="tm-code-toolbar">
		<span class="tm-code-lang-badge" style="background:<?php echo esc_attr( $badge_color ); ?>">
			<?php echo esc_html( strtoupper( $language ) ); ?>
		</span>
		<button type="button" class="tm-code-copy-btn">
			<span class="dashicons dashicons-clipboard"></span>
			<?php esc_html_e( 'Copy', 'pure-metafields' ); ?>
		</button>
	</div>
	<textarea
		class="tm-code-textarea"
		id="<?php echo esc_attr( $id ); ?>"
		name="<?php echo esc_attr( $id ); ?>"
		rows="<?php echo esc_attr( $rows ); ?>"
		spellcheck="false"
		autocomplete="off"
		autocorrect="off"
		autocapitalize="off"
		data-language="<?php echo esc_attr( $language ); ?>"
	><?php echo esc_textarea( $content ); ?></textarea>
</div>
<script>
(function () {
	var wrap = document.getElementById( '<?php echo esc_js( $wrap_id ); ?>' );
	if ( ! wrap ) return;

	var ta   = wrap.querySelector( '.tm-code-textarea' );
	var copy = wrap.querySelector( '.tm-code-copy-btn' );

	// Tab key → insert tab/spaces instead of moving focus.
	if ( ta ) {
		ta.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Tab' ) return;
			e.preventDefault();
			var start = ta.selectionStart;
			var end   = ta.selectionEnd;
			ta.value  = ta.value.substring( 0, start ) + '\t' + ta.value.substring( end );
			ta.selectionStart = ta.selectionEnd = start + 1;
			ta.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	}

	// Copy button.
	if ( copy && ta ) {
		copy.addEventListener( 'click', function () {
			var txt  = copy.querySelector( 'span.dashicons' );
			var label = copy.lastChild;
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( ta.value ).then( function () {
					if ( label ) label.textContent = ' Copied!';
					copy.classList.add( 'is-copied' );
					setTimeout( function () {
						if ( label ) label.textContent = ' Copy';
						copy.classList.remove( 'is-copied' );
					}, 1800 );
				} );
			} else {
				ta.select();
				document.execCommand( 'copy' );
			}
		} );
	}
} )();
</script>
