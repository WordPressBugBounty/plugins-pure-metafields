<?php
/**
 * Number (user meta)
 *
 * Optional field def keys honoured: min, max, step.
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

$_min  = isset( $min )  ? $min  : '';
$_max  = isset( $max )  ? $max  : '';
$_step = isset( $step ) ? $step : '';
?>
<tr>
    <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
    <td>
        <input
            type="number"
            name="<?php echo esc_attr( $id ); ?>"
            id="<?php echo esc_attr( $id ); ?>"
            value="<?php echo esc_attr( $_user_value ); ?>"
            class="small-text"
            placeholder="<?php echo esc_attr( isset( $placeholder ) ? $placeholder : '' ); ?>"
            <?php if ( '' !== $_min )  echo 'min="'  . esc_attr( $_min )  . '" '; ?>
            <?php if ( '' !== $_max )  echo 'max="'  . esc_attr( $_max )  . '" '; ?>
            <?php if ( '' !== $_step ) echo 'step="' . esc_attr( $_step ) . '" '; ?>
        />
        <?php if ( ! empty( $description ) ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </td>
</tr>
