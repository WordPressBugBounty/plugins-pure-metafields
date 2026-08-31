<?php
/**
 * Field: editor
 * WordPress TinyMCE / WP Editor rich-text field.
 *
 * Options:
 *   $field['rows']          — textarea height in rows (default 8)
 *   $field['media_buttons'] — show "Add Media" button (default false)
 *   $field['teeny']         — minimal toolbar (default false)
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// wp_editor() requires an ID that is lowercase alphanumeric + underscores only.
$editor_id = preg_replace( '/[^a-z0-9_]/', '_', strtolower( $id ) );

$settings = array(
	'textarea_name' => $id,
	'textarea_rows' => isset( $field['rows'] ) ? (int) $field['rows'] : 8,
	'media_buttons' => ! empty( $field['media_buttons'] ),
	'teeny'         => ! empty( $field['teeny'] ),
	'quicktags'     => true,
	'tinymce'       => true,
	'editor_class'  => 'tm-editor-field',
);

$content = '' !== (string) $value ? $value : ( isset( $field['default'] ) ? $field['default'] : '' );

echo '<div class="tm-editor-wrap">';
wp_editor( $content, $editor_id, $settings );
echo '</div>';
