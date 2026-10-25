<?php
/**
 * Repeater Fields — uses the same tm-repeater design as the options panel.
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
if ( ! defined( 'ABSPATH' ) ) exit;

$args = array(
    'field_type'  => 'repeater',
    'repeater_id' => $id,
);
if ( ! isset( $fields ) || empty( $fields ) || ! isset( $type ) || $type !== 'repeater' ) {
    return;
}

$_get_db_values = tpmeta_field( $id );
// tpmeta_field() can return false / a scalar default when no rows have been
// saved (or when the field type was previously a non-repeater). Normalize to
// an array so count() / foreach below never receive a non-iterable.
if ( ! is_array( $_get_db_values ) ) {
	$_get_db_values = array();
}
$r_cols  = isset( $columns ) ? max( 1, min( 4, (int) $columns ) ) : 1;
$wrap_id = 'tprptr-' . $id;
?>
<div class="tm-repeater"
    id="<?php echo esc_attr( $wrap_id ); ?>"
    data-field-id="<?php echo esc_attr( $id ); ?>"
    data-columns="<?php echo esc_attr( $r_cols ); ?>">

    <input type="hidden" class="tp-row-counter"
        name="<?php echo esc_attr( $id ); ?>_counter"
        value="<?php echo esc_attr( ! empty( $_get_db_values ) ? count( $_get_db_values ) : 0 ); ?>">

    <div class="tm-repeater-rows">

        <?php if ( ! empty( $_get_db_values ) ) : ?>
            <?php
            $count = 0;
            foreach ( $_get_db_values as $key => $row ) :
                $count++;
                $item_num = isset( $row['_item_num'] ) && $row['_item_num'] > 0 ? (int) $row['_item_num'] : $count;
            ?>
            <div class="tm-repeater-row is-collapsed" data-idx="<?php echo esc_attr( $count ); ?>">
                <div class="tm-rptr-header">
                    <span class="tm-rptr-handle" title="<?php esc_attr_e( 'Drag to reorder', 'pure-metafields' ); ?>">
                        <svg width="8" height="14" viewBox="0 0 8 14" fill="currentColor"><circle cx="2" cy="2" r="1.4"/><circle cx="6" cy="2" r="1.4"/><circle cx="2" cy="6" r="1.4"/><circle cx="6" cy="6" r="1.4"/><circle cx="2" cy="10" r="1.4"/><circle cx="6" cy="10" r="1.4"/></svg>
                    </span>
                    <button type="button" class="tm-rptr-toggle">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <span class="tm-rptr-title" data-count="<?php echo esc_attr( $item_num ); ?>">
                        <?php echo esc_html__( 'Item', 'pure-metafields' ) . ' ' . esc_html( $item_num ); ?>
                    </span>
                    <div class="tm-rptr-actions">
                        <button type="button" class="tm-rptr-remove" title="<?php esc_attr_e( 'Remove', 'pure-metafields' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="<?php echo esc_attr( $id ); ?>[]" class="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_html( $id ); ?>">
                <input type="hidden" name="<?php echo esc_attr( $id ); ?>_item_num[]" value="<?php echo esc_attr( $item_num ); ?>">
                <div class="tm-rptr-body">
                    <?php foreach ( $fields as $field ) :
                        $new_args = wp_parse_args( $args, array(
                            'row_db_value' => isset( $row[ $field['id'] ] ) ? $row[ $field['id'] ] : '',
                            'field'        => $field,
                            'row'          => $row,
                            'index'        => $count,
                        ) );
                    ?>
                    <div class="tm-rptr-field">
                        <?php tpmeta_load_template( 'metaboxes/fields/repeater-group.php', $new_args ); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

        <?php else : ?>
            <div class="tm-repeater-row tp-hidden-template" style="display:none">
                <div class="tm-rptr-header">
                    <span class="tm-rptr-handle" title="<?php esc_attr_e( 'Drag to reorder', 'pure-metafields' ); ?>">
                        <svg width="8" height="14" viewBox="0 0 8 14" fill="currentColor"><circle cx="2" cy="2" r="1.4"/><circle cx="6" cy="2" r="1.4"/><circle cx="2" cy="6" r="1.4"/><circle cx="6" cy="6" r="1.4"/><circle cx="2" cy="10" r="1.4"/><circle cx="6" cy="10" r="1.4"/></svg>
                    </span>
                    <button type="button" class="tm-rptr-toggle">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <span class="tm-rptr-title" data-count="0">
                        <?php echo esc_html__( 'Item', 'pure-metafields' ) . ' 1'; ?>
                    </span>
                    <div class="tm-rptr-actions">
                        <button type="button" class="tm-rptr-remove" title="<?php esc_attr_e( 'Remove', 'pure-metafields' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="<?php echo esc_attr( $id ); ?>[]" class="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_html( $id ); ?>">
                <input type="hidden" name="<?php echo esc_attr( $id ); ?>_item_num[]" value="0">
                <div class="tm-rptr-body">
                    <?php foreach ( $fields as $field ) :
                        $new_args = wp_parse_args( $args, array( 'field' => $field, 'fields' => $fields ) );
                    ?>
                    <div class="tm-rptr-field">
                        <?php tpmeta_load_template( 'metaboxes/fields/repeater-group.php', $new_args ); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- .tm-repeater-rows -->

    <button type="button" class="tm-repeater-add-btn">
        <span class="dashicons dashicons-plus-alt2"></span>
        <?php esc_html_e( 'Add Row', 'pure-metafields' ); ?>
    </button>

</div><!-- .tm-repeater -->

