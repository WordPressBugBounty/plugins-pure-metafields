<?php
/**
 * Editor (user meta) — WordPress TinyMCE
 *
 * Storage shape: HTML string in user_meta, sanitized via wp_kses_post on save.
 *
 * @var string $id
 * @var string $label
 * @var string $default
 * @var int    $user_id
 * @var int    $rows
 * @var bool   $media_buttons
 * @var bool   $teeny
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$_editor_id = preg_replace( '/[^a-z0-9_]/', '_', strtolower( $id ) );
$_saved     = get_user_meta( $user_id, $id, true );
$_content   = ( '' !== (string) $_saved )
    ? (string) $_saved
    : ( isset( $default ) ? (string) $default : '' );
?>
<tr>
    <th><label for="<?php echo esc_attr( $_editor_id ); ?>"><?php echo esc_html( $label ); ?></label></th>
    <td>
        <?php
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
        ?>
        <?php if ( ! empty( $description ) ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </td>
</tr>
