<?php
/**
 * Checkbox
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
<?php if(isset($row_db_value)):
    $_raw_opts = isset($options) && is_array($options) ? $options : ( isset($choices) && is_array($choices) ? $choices : array() );
    $_options  = count($_raw_opts) ? $_raw_opts : ( !empty($label) ? array(sanitize_key($label) => $label) : array() );
    $_default = isset($default) ? (array) $default : array();
?>
<?php foreach($_options as $key => $value): ?>
<div class="tpmeta-checkbox">
<input
    type="checkbox"
    class="tm-input tm-input-sm"
    id="<?php echo esc_attr($key); ?>"
    name="<?php echo esc_attr($key); ?>"
    value="<?php echo esc_html($value); ?>"
    <?php checked(!empty($row_db_value)? array_key_exists($key, (array) $row_db_value) : in_array($key, $_default), 1); ?>
/>
<label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></label>
</div>
<?php endforeach; ?>
<?php else:
    global $post;
    $db_value_exist     = (!empty($post) && metadata_exists('post', $post->ID, $id))? get_post_meta( $post->ID, $id, true) : '';
    $default_val        = !empty($default)? $default : '';
    $_default           = isset($default) ? $default : false;
    $_raw_options       = isset($options) && is_array($options) ? $options : ( isset($choices) && is_array($choices) ? $choices : array() );
    // If the field was saved without any options, derive one from the field label so
    // it renders rather than producing a silent empty field.
    $_options           = count($_raw_options) ? $_raw_options : ( !empty($label) ? array(sanitize_key($label) => $label) : array() );
?>
<?php foreach($_options as $key => $value): ?>
<div class="tpmeta-checkbox">
<input
    type="checkbox"
    class="tm-input tm-input-sm"
    id="<?php echo esc_attr($id.'_'.$key); ?>"
    name="<?php echo esc_attr($id.'_'.$key); ?>"
    value="<?php echo esc_html($key); ?>"
    <?php checked( ($db_value_exist? array_key_exists($key, (array) $db_value_exist) : (is_array($_default)? in_array($key, $_default) : $_default)), 1); ?>
/>
<label for="<?php echo esc_attr($id.'_'.$key); ?>"><?php echo esc_html($value); ?></label>
</div>
<?php endforeach; ?>
<?php endif; ?>
<script>
    ;(function($){
        "use strict";
        var combination = {};
        $( document ).on('change', '.tpmeta-checkbox input', function(){
            var name = $( this ).attr('name'),
                val = $( this ).val(),
                isChecked = $( this ).prop('checked');
                combination = $( this ).closest('.<?php echo esc_attr($id); ?>').find('.checkbox-input').val() != undefined? JSON.parse($( this ).closest('.<?php echo esc_attr($id); ?>').find('.checkbox-input').val()) : {};
            if(isChecked){
                combination = {...combination, ...{[name]:val}};
            }else{
                delete combination[name];
            }
            $( this ).closest('.<?php echo esc_attr($id); ?>').find('.checkbox-input').val(JSON.stringify(combination));
        });
    })( jQuery );
</script>