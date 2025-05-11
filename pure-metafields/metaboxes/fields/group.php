<?php
/**
 * Text
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


if( !empty($field['conditional']) ){
    $compare_results = tpmeta_is_condition_matched($field['conditional'], $fields);
}else{
    $compare_results = true;
}

$format = get_post_format() ? : 'standard';
$field['field_type'] = isset($field_type)? $field_type : '';
$field['post'] = $post;
$min_height = ($field['type'] == 'textarea')? '200px;' : '95px;';
?>

<?php if(isset($field['post_format']) && $field['post_format'] != ""): ?>
<div 
    class="tm-field-row <?php echo esc_attr($field['type']); ?> <?php echo esc_attr($field['id']); ?>" 
    style="display:<?php echo !$compare_results || ($format != $field['post_format'])? 'none' : 'block'; ?>;min-height:<?php echo esc_attr($min_height); ?>">
    <label class="label-<?php echo esc_attr($field['type']); ?>"><?php echo esc_html($field['label']); ?></label>
    <?php tpmeta_load_template('metaboxes/fields/'.$field['type'].'.php', $field); ?>
</div>
<?php else: ?>
    <?php if($field['type'] == 'tabs'): ?>
    <div class="tm-field-row <?php echo esc_attr($field['type']); ?> <?php echo esc_attr(esc_html($field['id'])); ?>" style="display:<?php echo !$compare_results? 'none' : 'block'; ?>;min-height:<?php echo esc_attr($min_height); ?>">
        <label class="label-<?php echo esc_attr($field['type']); ?>"><?php echo esc_html($field['label']); ?></label>
        <?php tpmeta_load_template('metaboxes/fields/'.$field['type'].'.php', $field); ?>
        <?php
            $tabs_childs = array_filter($fields, function($item) use ($field){
                if(isset($item['parent'])){
                    return $field['id'] == $item['parent'];
                }
            });
        ?>
        <?php foreach($tabs_childs as $tab_child): 
        $hide_tab_content = tpmeta_is_condition_matched($tab_child['conditional'], $tabs_childs);    
        ?>
        <div class="tm-field-row <?php echo esc_attr($tab_child['type']); ?> <?php echo esc_attr(esc_html($tab_child['id'])); ?>" style="display:<?php echo !$hide_tab_content? 'none' : 'block'; ?>">
            <label class="label-<?php echo esc_attr($tab_child['type']); ?>"><?php echo esc_html($tab_child['label']); ?></label>
            <?php tpmeta_load_template('metaboxes/fields/'.$tab_child['type'].'.php', $tab_child); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <?php if(!isset($field['parent'])): ?>
        <div class="tm-field-row <?php echo esc_attr($field['type']); ?> <?php echo esc_attr(esc_html($field['id'])); ?>" style="display:<?php echo !$compare_results? 'none' : 'block'; ?>;min-height:<?php echo esc_attr($min_height); ?>">
            <label class="label-<?php echo esc_attr($field['type']); ?>"><?php echo esc_html($field['label']); ?></label>
            <?php tpmeta_load_template('metaboxes/fields/'.$field['type'].'.php', $field); ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php
if(!empty($field['conditional'])): 
    $metabox_types = array_column($fields, 'type', 'id');
    $metafield_type = isset($metabox_types[$field['conditional'][0]])? $metabox_types[$field['conditional'][0]] : '';
?>
<script>
(function($) {
    "use strict";

    const fieldId = '<?php echo esc_html($field['id']); ?>';
    const conditionField = '<?php echo esc_html($field['conditional'][0]); ?>';
    const operator = '<?php echo esc_html($field['conditional'][1]); ?>';
    const targetValue = '<?php echo esc_html($field['conditional'][2]); ?>';
    const metafieldType = '<?php echo esc_html($metafield_type); ?>';

    function evaluateCondition(value) {
        switch (operator) {
            case '==': return value == targetValue;
            case '!=': return value != targetValue;
            case '>':  return value > targetValue;
            case '<':  return value < targetValue;
            case '>=': return value >= targetValue;
            case '<=': return value <= targetValue;
            default: return false;
        }
    }

    // function toggleField(show) {
    //     const $target = $('.' + fieldId);
    //     show ? $target.slideDown(200) : $target.slideUp(200);
    // }

    function toggleField(show) {
        const $target = $('.' + fieldId);
        show ? $target.fadeIn(200) : $target.fadeOut(200);
    }

    // function toggleField(show) {
    //     const $target = $('.' + fieldId);
    //     $target.addClass('zoom-toggle');

    //     if (show) {
    //         $target.removeClass('zoom-hidden');
    //     } else {
    //         $target.addClass('zoom-hidden');
    //     }
    // }



    if (metafieldType === 'switch') {
        const $switch = $('#' + conditionField);
        $switch.on('change', function() {
            const value = $(this).is(':checked') ? 'on' : 'off';
            toggleField(evaluateCondition(value));
        });
    }
    else if (metafieldType === 'tabs') {
        const $tab = $('.' + conditionField + '-tab');
        $tab.on('change', function() {
            toggleField(evaluateCondition($(this).val()));
        });
    }
    else if (metafieldType === 'select') {
        const $select = $('#' + conditionField + '-select');
        $select.on('change', function() {
            toggleField(evaluateCondition($(this).val()));
        });
    }
    else {
        console.log('No Conditional Element Found');
    }

})(jQuery);
</script>

<?php endif; ?>