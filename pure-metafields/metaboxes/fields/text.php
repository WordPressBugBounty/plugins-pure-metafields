<?php
/**
 * Text
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
if(!isset($placeholder)){
    $placeholder = '';
}
?>
<?php if(isset($row_db_value)): ?>
<input 
    type="text" 
    class="tm-input tm-input-sm" 
    id="<?php echo esc_attr($id); ?>" 
    name="<?php echo esc_attr($id); ?>[]"
    value="<?php echo esc_html($row_db_value); ?>"
    placeholder="<?php echo esc_html($placeholder) != ''? esc_html($placeholder): '';?>"
/>
<?php else: 
    $_value = is_array(tpmeta_field($id))? tpmeta_field($id)[0] : tpmeta_field($id);
?>
<input 
    type="text" 
    class="tm-input tm-input-sm" 
    id="<?php echo esc_attr($id); ?>" 
    name="<?php echo esc_attr($id); ?>"
    value="<?php echo  esc_html($_value)?? esc_html($_value)?? esc_html($default)?? ''; ?>"
    placeholder="<?php echo esc_html($placeholder) != ''? esc_html($placeholder): '';?>"
/>
<?php endif; ?>