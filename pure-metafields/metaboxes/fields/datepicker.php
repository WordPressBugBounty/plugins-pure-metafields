<?php
/**
 * Datepicker
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
<?php
// Unique element ID per repeater row (avoids jQuery UI datepicker
// onclick('#id') collision — it always targets the first DOM match).
$_dp_suffix = ( isset($row_db_value) && isset($repeater_row_index) && $repeater_row_index !== null )
    ? '__r' . $repeater_row_index
    : '';
$_dp_id = $id . $_dp_suffix;
?>
<?php if(!isset($row_db_value)): ?>
<input
    type="text"
    class="tm-input tm-input-sm tm-datepicker-input"
    id="<?php echo esc_attr($_dp_id); ?>"
    name="<?php echo esc_attr($id); ?>"
    value="<?php echo esc_html( tpmeta_field($id) ?: ( isset($default) ? $default : '' ) ); ?>"
    autocomplete="off"
/>
<?php else: ?>
<input
    type="text"
    class="tm-input tm-input-sm tm-datepicker-input"
    id="<?php echo esc_attr($_dp_id); ?>"
    name="<?php echo esc_attr($id); ?>[]"
    value="<?php echo esc_html($row_db_value); ?>"
    autocomplete="off"
/>
<?php endif; ?>