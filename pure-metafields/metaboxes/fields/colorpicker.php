<?php
/**
 * Colorpicker
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
?>
<?php if(!isset($row_db_value)): ?>
<input 
    type="text" 
    class="tm-input tm-input-sm tm-colorpicker-input" 
    id="<?php echo esc_attr($id); ?>" 
    name="<?php echo esc_attr($id); ?>" 
    value="<?php echo esc_html( tpmeta_field($id) ?: ( isset($default) ? $default : '#2271b1' ) ); ?>"
/>
<?php else: ?>
<input 
    type="text" 
    class="tm-input tm-input-sm tm-colorpicker-input" 
    id="<?php echo esc_attr($id); ?>" 
    name="<?php echo esc_attr($id); ?>[]" 
    value="<?php echo esc_html($row_db_value); ?>"
/>
<?php endif; ?>