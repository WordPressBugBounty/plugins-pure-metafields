<?php

/**
 * Tabs
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
?>
<?php
// Support both keys: builder-registered fields use 'options' (normalised to
// assoc {value:label} by the loader); manually-registered fields may use
// the legacy 'choices' key. Try choices first for backward compat.
$_choices = isset($choices) ? $choices : ( isset($options) ? $options : array() );
?>
<?php if(!isset($row_db_value)): ?>
<div class="tm-button-groups">
    <?php foreach($_choices as $key => $val): ?>
    <label class="tm-button-radio">
        <?php if(tpmeta_field($id) == ''): ?>
        <input 
        type="radio" 
        name="<?php echo esc_attr($id); ?>" <?php checked(esc_html($default), $key); ?> 
        value="<?php echo esc_html($key); ?>"
        class="<?php echo esc_attr($id); ?>-tab">
        <?php else: ?>
        <input 
        type="radio" 
        name="<?php echo esc_attr($id); ?>" <?php checked(tpmeta_field($id), $key); ?> 
        value="<?php echo esc_html($key); ?>"
        class="<?php echo esc_attr($id); ?>-tab">
        <?php endif; ?>
        <span><?php echo esc_html($val); ?></span>
    </label>
    <?php endforeach; ?>
</div>
<?php else:
$bind_keys = isset($bind)? $bind : '';
?>
<div class="tm-button-groups">
    <?php foreach($_choices as $key => $val): ?>
    <label class="tm-button-radio">
        <input
        data-key="<?php echo esc_attr($bind_keys); ?>" 
        type="radio" 
        name="<?php echo esc_attr($id); ?>[]" <?php checked(esc_html($row_db_value), $key); ?> 
        value="<?php echo esc_html($key); ?>"
        class="<?php echo esc_attr($id); ?>-tab tm-repeater-conditional">
        <span><?php echo esc_html($val); ?></span>
    </label>
    <?php endforeach; ?>
</div>
<?php endif; ?>