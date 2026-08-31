<?php
/**
 * Select Posttype Posts
 *
 * Variables below are populated at runtime via extract() by the field
 * renderer — declared here for static analysis (Intelephense).
 *
 * Optional config keys (all backward-compatible — when absent the field
 * behaves exactly as before: load every post of the given post_type and
 * save the post ID):
 *   taxonomy_filter (array)  — { taxonomy, term } to narrow results.
 *   meta_filters    (array)  — [ { key, value }, … ] to narrow results (AND).
 *   save_format     (string) — 'id' (default) or 'slug'.
 *   initial_limit   (int)    — Hard cap on rows. Default: -1 (no limit).
 *
 * @var string $id
 * @var string $post_type
 * @var string $placeholder
 * @var string $default
 * @var string $bind
 * @var mixed  $row_db_value
 * @var array  $taxonomy_filter
 * @var array  $meta_filters
 * @var string $save_format
 * @var int    $initial_limit
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$_pt          = isset( $post_type ) && $post_type ? $post_type : 'post';
$_save_format = ( isset( $save_format ) && 'slug' === $save_format ) ? 'slug' : 'id';
$_limit       = isset( $initial_limit ) && (int) $initial_limit > 0 ? (int) $initial_limit : -1;

$_query_args = array(
    'post_type'   => $_pt,
    'post_status' => 'publish',
    'numberposts' => $_limit,
);

if ( isset( $taxonomy_filter ) && is_array( $taxonomy_filter )
    && ! empty( $taxonomy_filter['taxonomy'] )
    && taxonomy_exists( $taxonomy_filter['taxonomy'] ) ) {
    $_query_args['tax_query'] = array(
        array(
            'taxonomy' => sanitize_key( $taxonomy_filter['taxonomy'] ),
            'field'    => 'slug',
            'terms'    => isset( $taxonomy_filter['term'] ) ? sanitize_text_field( $taxonomy_filter['term'] ) : '',
        ),
    );
}

if ( isset( $meta_filters ) && is_array( $meta_filters ) && ! empty( $meta_filters ) ) {
    $_meta_query = array();
    foreach ( $meta_filters as $_mf ) {
        $_mf = (array) $_mf;
        if ( empty( $_mf['key'] ) ) continue;
        $_meta_query[] = array(
            'key'     => sanitize_key( $_mf['key'] ),
            'value'   => isset( $_mf['value'] ) ? sanitize_text_field( $_mf['value'] ) : '',
            'compare' => '=',
        );
    }
    if ( $_meta_query ) {
        $_query_args['meta_query'] = $_meta_query;
    }
}

$get_posts = get_posts( $_query_args );

?>
<?php $_ph = isset($placeholder) ? $placeholder : 'Select...'; ?>
<?php if(!isset($row_db_value)): ?>
<select
    name="<?php echo esc_attr($id); ?>"
    id="<?php echo esc_attr($id); ?>-select"
    class="<?php echo esc_attr($id); ?> tm-select-field select2"
    data-placeholder="<?php echo esc_attr($_ph); ?>">
    <option value="<?php echo esc_html( $default ?? '' ); ?>"><?php echo esc_html($_ph); ?></option>
    <?php foreach($get_posts as $tp_post):
        $_opt_val = ( 'slug' === $_save_format ) ? $tp_post->post_name : $tp_post->ID;
    ?>
        <option
            value="<?php echo esc_attr($_opt_val); ?>"
            <?php selected( (string) tpmeta_field($id), (string) $_opt_val ); ?>><?php echo esc_html($tp_post->post_title); ?>
        </option>
    <?php endforeach; wp_reset_postdata();?>
</select>
<?php else:
$bind_keys = isset($bind)? $bind : '';
?>
<select
    data-key="<?php echo esc_attr($bind_keys); ?>"
    name="<?php echo esc_attr($id); ?>[]"
    class="<?php echo esc_attr($id); ?> tm-repeater-select-field tm-repeater-conditional select2"
    data-placeholder="<?php echo esc_attr($_ph); ?>">
    <option value="<?php echo esc_html( isset($default) ? $default : '' ); ?>"><?php echo esc_html($_ph); ?></option>
    <?php foreach($get_posts as $tp_post):
        $_opt_val = ( 'slug' === $_save_format ) ? $tp_post->post_name : $tp_post->ID;
    ?>
        <option
            value="<?php echo esc_attr($_opt_val); ?>"
            <?php selected( (string) $row_db_value, (string) $_opt_val ); ?>><?php echo esc_html($tp_post->post_title); ?>
        </option>
    <?php endforeach; wp_reset_postdata();?>
</select>
<?php endif; ?>
