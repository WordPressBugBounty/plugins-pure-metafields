<?php
/**
 * Editor (WordPress TinyMCE) — metabox context
 *
 * Standalone: renders wp_editor() bound to post meta key $id. Saved value
 * is the editor HTML (sanitized via wp_kses_post in class-metabox.php).
 *
 * Inside a repeater: falls back to a plain textarea. wp_editor() / TinyMCE
 * does not survive row cloning or sortable reorders cleanly, and dynamic
 * TinyMCE init in admin is fragile — consistent with how the Options
 * Builder treats this field type.
 *
 * Variables below are populated at runtime via extract() by the field
 * renderer — declared here for static analysis (Intelephense).
 *
 * @var string $id
 * @var string $post_type
 * @var string $placeholder
 * @var string $default
 * @var string $bind
 * @var mixed  $row_db_value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $row_db_value ) ) :
	// Repeater row: textarea fallback (no TinyMCE).
	?>
	<textarea
		name="<?php echo esc_attr( $id ); ?>[]"
		class="tm-input tm-textarea-field tm-editor-textarea"
		rows="6"
		placeholder="<?php echo esc_attr( isset( $placeholder ) ? $placeholder : '' ); ?>"
	><?php echo esc_textarea( (string) $row_db_value ); ?></textarea>
	<?php
else :
	// Standalone: full WP editor.
	$_editor_id = preg_replace( '/[^a-z0-9_]/', '_', strtolower( $id ) );
	$_saved     = tpmeta_field( $id );
	$_content   = ( '' !== (string) $_saved )
		? (string) $_saved
		: ( isset( $default ) ? (string) $default : '' );

	wp_editor(
		$_content,
		$_editor_id,
		array(
			'textarea_name' => $id,
			'textarea_rows' => isset( $rows ) ? (int) $rows : 8,
			'media_buttons' => isset( $media_buttons ) ? (bool) $media_buttons : true,
			'teeny'         => ! empty( $teeny ),
			'quicktags'     => true,
			'tinymce'       => true,
			'editor_class'  => 'tm-editor-field',
		)
	);
endif;
