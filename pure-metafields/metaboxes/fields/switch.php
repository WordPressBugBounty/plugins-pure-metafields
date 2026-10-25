<?php
/**
 * Switch
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
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if(!isset($default)){
    $default = false;
}

// The Field Builder's switch modal lets developers pick a boolean-style
// default ('true'/'false') alongside the legacy string default ('on'/'off').
// The stored post_meta is always 'on'/'off' (the form posts those literals),
// so render-time we map any truthy default token onto 'on' so the checkbox
// initial-checked state reflects the developer's intent.
$tpmeta_switch_default_token = is_string( $default )
    ? strtolower( trim( $default ) )
    : ( $default ? 'on' : 'off' );
$tpmeta_switch_default_is_on = in_array( $tpmeta_switch_default_token, array( 'true', 'on', '1', 'yes' ), true );
$tpmeta_switch_default_value = $tpmeta_switch_default_is_on ? 'on' : 'off';
?>
<?php if(isset($field_type) && $field_type == 'repeater'):
$row_db_value = isset($row_db_value) ? $row_db_value : '';
$bind_keys = isset($bind)? esc_attr($bind) : '';
// Coerce a real PHP bool back into the on/off token the input value
// attribute and `checked()` helper expect. The cast filter
// (`tpmeta_repeater_apply_subfield_bool_cast`) replaces 'on'/'off' with
// true/false for switch+boolean sub-fields when reading the parent
// repeater, so by the time we render a row, $row_db_value can be bool.
if ( true === $row_db_value || 1 === $row_db_value ) {
    $row_db_value = 'on';
} elseif ( false === $row_db_value || null === $row_db_value ) {
    $row_db_value = '';
}
$tpmeta_switch_row_value = ( '' === $row_db_value ) ? $tpmeta_switch_default_value : $row_db_value;
?>
<label class="tm-switch">
    <input
    type="hidden"
    name="<?php echo esc_html($id); ?>[]"
    class="<?php echo esc_attr($id); ?>"
    value="<?php echo esc_html( $tpmeta_switch_row_value ); ?>">
    <input
    data-key="<?php echo esc_attr($bind_keys); ?>"
    type="checkbox" <?php checked( 'on', $tpmeta_switch_row_value ); ?>
    class="<?php echo esc_attr($id); ?> tm-repeater-conditional"
    value="<?php echo esc_html( $tpmeta_switch_row_value ); ?>">
    <span class="tm-slider"></span>
</label>
<?php else:
// Read raw post_meta directly — going through tpmeta_field() collapses a
// stored 'off' to PHP false when data_type=boolean (the read-time cast),
// which the legacy on/off coercion below would then treat as "unset" and
// fall back to the default. With default=true that snaps an unchecked
// save back to checked on reload, looking like the value never saved.
// metadata_exists() lets us tell "saved" from "never saved" so the
// default only applies in the latter case. Storage shape is unchanged.
global $post;
$tpmeta_switch_post_id   = isset( $post->ID ) ? (int) $post->ID : 0;
$tpmeta_switch_has_saved = $tpmeta_switch_post_id
    && metadata_exists( 'post', $tpmeta_switch_post_id, $id );
if ( $tpmeta_switch_has_saved ) {
    $tpmeta_switch_raw = get_post_meta( $tpmeta_switch_post_id, $id, true );
    $tpmeta_switch_token = is_string( $tpmeta_switch_raw )
        ? strtolower( trim( $tpmeta_switch_raw ) )
        : ( $tpmeta_switch_raw ? 'on' : 'off' );
    $tpmeta_switch_current = in_array( $tpmeta_switch_token, array( 'on', 'true', '1', 'yes' ), true ) ? 'on' : 'off';
} else {
    $tpmeta_switch_current = $tpmeta_switch_default_value;
}
?>
<label class="tm-switch">
    <input
    type="checkbox" <?php checked( 'on', $tpmeta_switch_current ); ?>
    name="<?php echo esc_html($id); ?>"
    id="<?php echo esc_attr($id); ?>"
    class="<?php echo esc_attr($id); ?>"
    value="on">
    <span class="tm-slider"></span>
</label>
<?php endif; ?>