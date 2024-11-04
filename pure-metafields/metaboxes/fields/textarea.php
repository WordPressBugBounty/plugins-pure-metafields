<?php
/**
 * Textarea
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if(!isset($default)){
    $default = false;
}

$allowed_tags = tpmeta_allowed_svg_tags();
?>
<?php if(!isset($row_db_value)): ?>
<textarea
    class="tm-textarea" 
    name="<?php echo esc_attr($id); ?>" 
    id="<?php echo esc_attr($id); ?>"
    placeholder="<?php echo esc_attr($placeholder?? 'Something...'); ?>"
><?php echo wp_kses(tpmeta_field($id), $allowed_tags)?? wp_kses($default, $allowed_tags)?? ''; ?></textarea>
<?php else: ?>
<textarea
    class="tm-textarea" 
    name="<?php echo esc_attr($id); ?>[]" 
    id="<?php echo esc_attr($id); ?>"
    placeholder="<?php echo esc_attr($placeholder?? 'Something...'); ?>"
><?php echo wp_kses($row_db_value, $allowed_tags); ?></textarea>
<?php endif; ?>