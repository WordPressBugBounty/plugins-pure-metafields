<?php
/**
 * Select Posts (user meta) — Select2 dropdown of posts of a given post_type.
 *
 * Optional field def keys (mirror the metabox template):
 *   post_type       (string)  — required for sensible output (default 'post').
 *   placeholder     (string)
 *   save_format     (string)  — 'id' (default) or 'slug'.
 *   initial_limit   (int)     — Hard cap on rows. Default: -1 (no limit).
 *   taxonomy_filter (array)   — { taxonomy, term } to narrow results.
 *   meta_filters    (array)   — [ { key, value }, … ] to narrow (AND).
 *
 * @var string $id
 * @var string $label
 * @var string $post_type
 * @var string $placeholder
 * @var string $default
 * @var int    $user_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

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
    if ( $_meta_query ) $_query_args['meta_query'] = $_meta_query;
}

$_get_posts  = get_posts( $_query_args );
$_ph         = isset( $placeholder ) ? $placeholder : 'Select...';
$_user_value = get_user_meta( $user_id, $id, true );
if ( '' === $_user_value || null === $_user_value || false === $_user_value ) {
    $_user_value = isset( $default ) ? $default : '';
}
?>
<tr>
    <th><label for="<?php echo esc_attr( $id ); ?>-select"><?php echo esc_html( $label ); ?></label></th>
    <td>
        <select
            name="<?php echo esc_attr( $id ); ?>"
            id="<?php echo esc_attr( $id ); ?>-select"
            class="<?php echo esc_attr( $id ); ?> tm-select-field select2"
            data-placeholder="<?php echo esc_attr( $_ph ); ?>"
        >
            <option value=""><?php echo esc_html( $_ph ); ?></option>
            <?php foreach ( $_get_posts as $tp_post ) :
                $_opt_val = ( 'slug' === $_save_format ) ? $tp_post->post_name : $tp_post->ID;
            ?>
                <option value="<?php echo esc_attr( $_opt_val ); ?>" <?php selected( (string) $_user_value, (string) $_opt_val ); ?>>
                    <?php echo esc_html( $tp_post->post_title ); ?>
                </option>
            <?php endforeach; wp_reset_postdata(); ?>
        </select>
        <?php if ( ! empty( $description ) ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
    </td>
</tr>
<script type="text/javascript">
;(function($){
    $(function(){
        if ( $.fn && $.fn.select2 ) {
            $('#<?php echo esc_js( $id ); ?>-select').select2({ width: '350px' });
        }
    });
})(jQuery);
</script>
