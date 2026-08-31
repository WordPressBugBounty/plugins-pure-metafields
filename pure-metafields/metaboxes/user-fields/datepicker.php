<?php
/**
 * Datepicker (user meta)
 *
 * Sibling of metaboxes/fields/datepicker.php — wrapped in a <tr> for the
 * user-profile form-table, and reads/writes user_meta instead of post_meta.
 * The tm-datepicker-input class is picked up by the shared field runtime
 * (assets/js/tpmeta-fields.js) which initialises jQuery UI datepicker.
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
            type="text"
            class="tm-input tm-input-sm tm-datepicker-input"
            id="<?php echo esc_attr( $id ); ?>"
            name="<?php echo esc_attr( $id ); ?>"
            value="<?php echo esc_attr( $_user_value ); ?>"
            placeholder="<?php echo esc_attr( isset( $placeholder ) ? $placeholder : '' ); ?>"
            autocomplete="off"
        />
        <?php if ( ! empty( $description ) ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </td>
</tr>
