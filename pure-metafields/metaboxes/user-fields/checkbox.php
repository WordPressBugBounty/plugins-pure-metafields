<?php
/**
 * Checkbox field
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
 * @var int    $user_id
 * @var string $label
 * @var array  $options
 */
if( !defined('ABSPATH') ) exit;

$user_value = empty(get_user_meta($user_id, $id, true))? $default : get_user_meta($user_id, $id, true);

?>
<tr>
    <th><?php echo esc_html($label); ?></th>
    <td>
        <label for="<?php echo esc_attr($id); ?>">
            <input type="checkbox" name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" <?php checked($user_value, 'on'); ?>/>
            <?php echo esc_html($label); ?>
        </label>
    </td>
</tr>