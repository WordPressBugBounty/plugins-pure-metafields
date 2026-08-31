<?php
/**
 * Multicolor (user meta)
 *
 * Storage shape: assoc { slot_key => css_color }. POST shape is
 * `name="id[slot_key]"` so PHP receives an associative array directly —
 * identical to the standalone metabox path.
 *
 * @var string $id
 * @var string $label
 * @var mixed  $default
 * @var array  $options
 * @var array  $choices
 * @var int    $user_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$_slots = array();
if ( isset( $options ) && is_array( $options ) && count( $options ) ) {
    $_slots = $options;
} elseif ( isset( $choices ) && is_array( $choices ) && count( $choices ) ) {
    $_slots = $choices;
}

$_def = array();
if ( isset( $default ) ) {
    if ( is_array( $default ) ) {
        $_def = $default;
    } elseif ( is_string( $default ) && '' !== $default ) {
        $_dec = json_decode( $default, true );
        if ( is_array( $_dec ) ) $_def = $_dec;
    }
}

$_has_meta = metadata_exists( 'user', $user_id, $id );
$_saved    = $_has_meta ? get_user_meta( $user_id, $id, true ) : null;
$_current  = is_array( $_saved ) ? $_saved : ( $_has_meta ? array() : $_def );
?>
<tr>
    <th><?php echo esc_html( $label ); ?></th>
    <td>
        <div class="tm-multicolor-group">
            <?php foreach ( $_slots as $_mc_key => $_mc_slot_color ) :
                $_mc_color   = ( '' !== $_mc_slot_color ) ? $_mc_slot_color : '#cccccc';
                $_mc_checked = isset( $_current[ $_mc_key ] );
            ?>
            <label class="tm-mcswatch-item">
                <input
                    type="checkbox"
                    class="tm-mcswatch-input"
                    name="<?php echo esc_attr( $id ); ?>[<?php echo esc_attr( $_mc_key ); ?>]"
                    id="<?php echo esc_attr( $id . '_' . $_mc_key ); ?>"
                    value="<?php echo esc_attr( $_mc_color ); ?>"
                    <?php checked( $_mc_checked ); ?>
                />
                <span class="tm-mcswatch-color" style="background-color:<?php echo esc_attr( $_mc_color ); ?>"></span>
                <span class="tm-mcswatch-label"><?php echo esc_html( $_mc_key ); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php if ( ! empty( $description ) ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </td>
</tr>
