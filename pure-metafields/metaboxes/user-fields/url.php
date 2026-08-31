<?php
/**
 * URL (user meta)
 *
 * @var string $id
 * @var string $label
 * @var string $default
 * @var string $placeholder
 * @var int    $user_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$_user_value = get_user_meta( $user_id, $id, true );
if ( '' === $_user_value || null === $_user_value || false === $_user_value ) {
    $_user_value = isset( $default ) ? $default : '';
}
?>
<tr>
    <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
    <td>
        <input
            type="url"
            name="<?php echo esc_attr( $id ); ?>"
            id="<?php echo esc_attr( $id ); ?>"
            value="<?php echo esc_url( $_user_value ); ?>"
            class="regular-text"
            placeholder="<?php echo esc_attr( isset( $placeholder ) ? $placeholder : '' ); ?>"
        />
        <?php if ( ! empty( $description ) ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </td>
</tr>
