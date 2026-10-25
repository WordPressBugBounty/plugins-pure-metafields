<?php
/**
 * Repeater Group
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
 * @var array  $fields
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( !empty($field['conditional']) ){
    if(isset($row)){
        $compare_results = tpmeta_is_row_matched($field['conditional'], $row);
    }else{
        $compare_results = tpmeta_is_condition_matched($field['conditional'], $fields);
    }
}else{
    $compare_results = true;
}

$format = get_post_format() ? : 'standard';
$field['row_db_value']      = isset($row_db_value)? $row_db_value : '';
$field['field_type']        = isset($field_type)? esc_html($field_type) : '';
$field['repeater_id']       = isset($repeater_id)? esc_html($repeater_id) : '';
// Propagate the row counter so field templates (datepicker, color_gradient)
// can generate unique per-row element IDs, avoiding jQuery UI collisions.
$field['repeater_row_index'] = isset($index) ? (int)$index : null;

wp_enqueue_script('repeater');
?>

<?php if(isset($post_format) && $post_format != ""): ?>
<div data-operand="<?php echo !empty($field['conditional'])? esc_attr($field['conditional'][1]) : ''; ?>" data-value="<?php echo !empty($field['conditional'])? esc_attr($field['conditional'][2]) : ''; ?>" class="tm-field-row <?php echo esc_attr(esc_html($field['id'])); ?>" style="display:<?php echo !$compare_results || ($format != $post_format)? 'none' : 'block'; ?>">
    <label><?php echo esc_html($field['label']); ?></label>
    <?php tpmeta_load_template('metaboxes/fields/'.$field['type'].'.php', $field); ?>
</div>
<?php else: ?>
<div data-operand="<?php echo !empty($field['conditional'])? esc_attr($field['conditional'][1]) : ''; ?>" data-value="<?php echo !empty($field['conditional'])? esc_attr($field['conditional'][2]) : ''; ?>" class="tm-field-row <?php echo esc_attr(esc_html($field['id'])); ?>" style="display:<?php echo !esc_html($compare_results)? 'none' : 'block'; ?>">
    <label><?php echo esc_html($field['label']); ?></label>
    <?php if($field['type'] == 'checkbox'):
        $json_arr = array();
        $defaults = isset($field['default']) ? $field['default'] : array();
        // The builder/scanner can deliver `default` as a JSON-encoded string
        // (e.g. '{"key":"1"}' or '["key"]') or a single string key. Normalize
        // to an array before iterating so foreach never gets a string.
        if ( is_string( $defaults ) ) {
            if ( '' === $defaults ) {
                $defaults = array();
            } else {
                $decoded  = json_decode( $defaults, true );
                $defaults = is_array( $decoded ) ? $decoded : array( $defaults );
            }
        } elseif ( ! is_array( $defaults ) ) {
            $defaults = array();
        }
        $options_arr = !empty($field['options']) && is_array($field['options']) ? $field['options'] : array();

        foreach($defaults as $key => $val){
            // Support both shapes: sequential ['key1','key2'] (value is the
            // option key) and associative {'key1' => '1'} (key is the option key).
            $opt_key = is_int( $key ) ? $val : $key;
            if(array_key_exists($opt_key, $options_arr)){
                $json_arr[$opt_key] = $options_arr[$opt_key];
            }
        }
        $json_arr =  !empty($row_db_value)? $row_db_value : $json_arr;
    ?>
        <input type="hidden" name="<?php echo esc_attr($field['id']); ?>[]" value="<?php echo esc_html(json_encode($json_arr)); ?>" class="checkbox-input <?php echo esc_attr($field['id']); ?>">
    <?php endif; ?>
    <?php tpmeta_load_template('metaboxes/fields/'.$field['type'].'.php', $field); ?>
</div>
<?php endif; ?>