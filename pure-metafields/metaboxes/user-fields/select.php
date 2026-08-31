<?php
/**
 * Select field
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
$placeholder = empty($placeholder)? 'Select an option' : $placeholder;
?>
<tr>
    <th><?php echo esc_html($label); ?></th>
    <td>
        <select name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>">
            <option value="-1"><?php echo esc_attr($placeholder); ?></option>
            <?php 
            if(!empty($options)):
            foreach($options as $key => $val): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $user_value); ?>><?php echo esc_html($val); ?></option>
            <?php endforeach; endif; ?>
        </select>
    </td>
</tr>