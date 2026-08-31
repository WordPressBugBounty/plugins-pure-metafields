<?php
/**
 * Field: post_select
 *
 * Renders a searchable select populated with posts.
 * - Initial N posts are pre-loaded server-side (configurable via 'initial_limit').
 * - Additional posts are fetched via AJAX (select2 ajax) when the user types.
 *
 * Configuration keys (all optional, set via the Options Builder):
 *   post_type     (string)  — Which post type to query. Default 'post'.
 *   save_format   (string)  — 'id' (default) or 'slug'.
 *   initial_limit (int)     — Posts to pre-load. Default 20.
 *   taxonomy_filter (array) — { taxonomy, term } to narrow results.
 *   meta_filters  (array)   — [ { key, value }, … ] to narrow results.
 *   placeholder   (string)  — Custom placeholder text.
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$post_type     = isset( $field['post_type'] )     ? sanitize_key( $field['post_type'] )     : 'post';
$save_format   = isset( $field['save_format'] )   ? $field['save_format']                   : 'id';
$initial_limit = isset( $field['initial_limit'] ) ? absint( $field['initial_limit'] )        : 20;
$tax_filter    = isset( $field['taxonomy_filter'] ) && is_array( $field['taxonomy_filter'] )
	? $field['taxonomy_filter'] : null;
$meta_filters  = isset( $field['meta_filters'] ) && is_array( $field['meta_filters'] )
	? $field['meta_filters'] : array();
$placeholder   = isset( $field['placeholder'] )
	? $field['placeholder']
	: __( '— Select post —', 'pure-metafields' );

// Validate post type exists so we never run a broken query.
if ( ! post_type_exists( $post_type ) ) {
	$post_type = 'post';
}

// ------------------------------------------------------------------
// Build initial post list (server-side pre-load)
// ------------------------------------------------------------------
$query_args = array(
	'post_type'      => $post_type,
	'post_status'    => 'publish',
	'posts_per_page' => $initial_limit,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'no_found_rows'  => false,
);

if ( ! empty( $tax_filter['taxonomy'] ) && taxonomy_exists( $tax_filter['taxonomy'] ) ) {
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => sanitize_key( $tax_filter['taxonomy'] ),
			'field'    => 'slug',
			'terms'    => isset( $tax_filter['term'] ) ? sanitize_text_field( $tax_filter['term'] ) : '',
		),
	);
}

if ( ! empty( $meta_filters ) ) {
	$meta_query = array();
	foreach ( $meta_filters as $mf ) {
		$mf = (array) $mf;
		if ( empty( $mf['key'] ) ) continue;
		$meta_query[] = array(
			'key'     => sanitize_key( $mf['key'] ),
			'value'   => isset( $mf['value'] ) ? sanitize_text_field( $mf['value'] ) : '',
			'compare' => '=',
		);
	}
	if ( $meta_query ) {
		$query_args['meta_query'] = $meta_query;
	}
}

$initial_query = new WP_Query( $query_args );
$initial_posts = $initial_query->posts;
$has_more      = $initial_query->max_num_pages > 1;
$current_val   = (string) $value;

// ------------------------------------------------------------------
// If saved value isn't in the initial set, load it separately
// ------------------------------------------------------------------
$saved_post    = null;
if ( '' !== $current_val && '0' !== $current_val ) {
	$found = false;
	foreach ( $initial_posts as $p ) {
		$opt_val = ( 'slug' === $save_format ) ? $p->post_name : (string) $p->ID;
		if ( $opt_val === $current_val ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		if ( 'slug' === $save_format ) {
			$slug_results = get_posts( array(
				'post_type'   => $post_type,
				'name'        => $current_val,
				'post_status' => 'publish',
				'numberposts' => 1,
			) );
			if ( $slug_results ) {
				$saved_post = $slug_results[0];
			}
		} else {
			$maybe = get_post( (int) $current_val );
			if ( $maybe && 'publish' === $maybe->post_status ) {
				$saved_post = $maybe;
			}
		}
	}
}

// ------------------------------------------------------------------
// Encode filter data for AJAX (passed as data attributes)
// ------------------------------------------------------------------
$tax_json  = $tax_filter    ? wp_json_encode( $tax_filter )    : '';
$meta_json = $meta_filters  ? wp_json_encode( $meta_filters )  : '';
?>
<select
	id="<?php echo esc_attr( $id ); ?>-select"
	name="<?php echo esc_attr( $id ); ?>"
	class="tpmeta-post-select select2 <?php echo esc_attr( $id ); ?>"
	data-post-type="<?php echo esc_attr( $post_type ); ?>"
	data-save-format="<?php echo esc_attr( $save_format ); ?>"
	data-initial-limit="<?php echo esc_attr( $initial_limit ); ?>"
	data-has-more="<?php echo $has_more ? '1' : '0'; ?>"
	data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'tpmeta_post_select' ) ); ?>"
	data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
	<?php if ( $tax_json )  : ?>data-tax-filter="<?php echo esc_attr( $tax_json ); ?>"<?php endif; ?>
	<?php if ( $meta_json ) : ?>data-meta-filters="<?php echo esc_attr( $meta_json ); ?>"<?php endif; ?>
>
	<option value=""><?php echo esc_html( $placeholder ); ?></option>

	<?php foreach ( $initial_posts as $p ) :
		$opt_value = ( 'slug' === $save_format ) ? $p->post_name : $p->ID;
	?>
		<option value="<?php echo esc_attr( $opt_value ); ?>"<?php selected( $current_val, (string) $opt_value ); ?>>
			<?php echo esc_html( $p->post_title ); ?>
		</option>
	<?php endforeach; ?>

	<?php if ( $saved_post ) :
		$opt_value = ( 'slug' === $save_format ) ? $saved_post->post_name : $saved_post->ID;
	?>
		<option value="<?php echo esc_attr( $opt_value ); ?>" selected>
			<?php echo esc_html( $saved_post->post_title ); ?>
		</option>
	<?php endif; ?>
</select>
