<?php
/**
 * Switch (user meta)
 *
 * Sibling of metaboxes/fields/switch.php — wrapped in a <tr> for the user
 * profile form-table. Storage stays on/off scalar strings (legacy shape).
 * `metadata_exists()` lets us distinguish "never saved" (apply default)
 * from "saved as off" (reflect the off state) so unchecking + saving
 * doesn't snap back to the default on reload.
 *
 * @var string $id
 * @var string $label
 * @var mixed  $default
 * @var int    $user_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! isset( $default ) ) {
    $default = false;
}
$_default_token = is_string( $default )
    ? strtolower( trim( $default ) )
    : ( $default ? 'on' : 'off' );
$_default_value = in_array( $_default_token, array( 'true', 'on', '1', 'yes' ), true ) ? 'on' : 'off';

if ( metadata_exists( 'user', $user_id, $id ) ) {
    $_raw   = get_user_meta( $user_id, $id, true );
    $_token = is_string( $_raw ) ? strtolower( trim( $_raw ) ) : ( $_raw ? 'on' : 'off' );
    $_current = in_array( $_token, array( 'on', 'true', '1', 'yes' ), true ) ? 'on' : 'off';
} else {
    $_current = $_default_value;
}
?>
<tr>
    <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
    <td>
        <label class="tm-switch">
            <input
                type="checkbox"
                id="<?php echo esc_attr( $id ); ?>"
                name="<?php echo esc_attr( $id ); ?>"
                class="<?php echo esc_attr( $id ); ?>"
                value="on"
                <?php checked( 'on', $_current ); ?>
            />
            <span class="tm-slider"></span>
        </label>
        <?php if ( ! empty( $description ) ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </td>
</tr>
