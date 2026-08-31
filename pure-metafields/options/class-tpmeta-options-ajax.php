<?php
/**
 * TPMeta_Options_Ajax
 *
 * Handles the AJAX save endpoint for the options panel.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Options_Ajax {

	const ACTION = 'tpmeta_options_save';
	const NONCE  = 'tpmeta_options_save';

	const POST_SELECT_ACTION = 'tpmeta_post_select_search';
	const POST_SELECT_NONCE  = 'tpmeta_post_select';

	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION,              array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_' . self::POST_SELECT_ACTION,  array( $this, 'handle_post_select_search' ) );
	}

	/**
	 * Save handler.
	 *
	 * Expects POST: nonce, opt_name, values (array).
	 */
	public function handle_save() {
		// 1. Nonce check.
		check_ajax_referer( self::NONCE, 'nonce' );

		// 2. Identify panel.
		$opt_name = isset( $_POST['opt_name'] ) ? sanitize_key( wp_unslash( $_POST['opt_name'] ) ) : '';
		$panel    = TPMeta_Options::get_panel( $opt_name );

		if ( ! $panel ) {
			wp_send_json_error( array( 'message' => __( 'Unknown options panel.', 'pure-metafields' ) ), 400 );
		}

		// 3. Capability check.
		if ( ! current_user_can( $panel['capability'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'pure-metafields' ) ), 403 );
		}

		// 4. Collect raw values.
		// We allow values to be sent either as $_POST['values'] (assoc array)
		// or as a serialized form-encoded string $_POST['form'].
		$values = array();

		if ( isset( $_POST['values'] ) && is_array( $_POST['values'] ) ) {
			$values = wp_unslash( $_POST['values'] );
		} elseif ( isset( $_POST['form'] ) ) {
			parse_str( wp_unslash( $_POST['form'] ), $values );
		}

		// 5. Persist.
		$saved = TPMeta_Options_Store::save_batch( $opt_name, $values );

		/**
		 * Fires after a panel has been saved.
		 *
		 * @param string $opt_name
		 * @param array  $saved    Sanitized values written to theme_mods.
		 */
		do_action( 'tpmeta/options/saved', $opt_name, $saved );
		do_action( 'tpmeta/options/' . $opt_name . '/saved', $saved );

		wp_send_json_success( array(
			'message' => __( 'Settings saved.', 'pure-metafields' ),
			'values'  => $saved,
		) );
	}

	/**
	 * AJAX: Search posts for a post_select field.
	 *
	 * Accepts POST: nonce, post_type, save_format, search, page, tax_filter (JSON), meta_filters (JSON).
	 * Returns: { results: [{id, text}], more: bool }
	 */
	public function handle_post_select_search() {
		check_ajax_referer( self::POST_SELECT_NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'pure-metafields' ) ), 403 );
		}

		$post_type   = isset( $_POST['post_type'] )   ? sanitize_key( wp_unslash( $_POST['post_type'] ) )   : 'post';
		$save_format = isset( $_POST['save_format'] )  ? sanitize_key( wp_unslash( $_POST['save_format'] ) )  : 'id';
		$search      = isset( $_POST['search'] )       ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$page        = max( 1, isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1 );
		$per_page    = 20;

		// Validate post type exists before running a query.
		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'pure-metafields' ) ), 400 );
		}

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $search ? 'relevance' : 'title',
			'order'          => 'ASC',
			's'              => $search,
		);

		// Taxonomy filter.
		if ( ! empty( $_POST['tax_filter'] ) ) {
			$tax_raw = json_decode( sanitize_text_field( wp_unslash( $_POST['tax_filter'] ) ), true );
			if ( ! empty( $tax_raw['taxonomy'] ) && taxonomy_exists( sanitize_key( $tax_raw['taxonomy'] ) ) ) {
				$query_args['tax_query'] = array( array(
					'taxonomy' => sanitize_key( $tax_raw['taxonomy'] ),
					'field'    => 'slug',
					'terms'    => isset( $tax_raw['term'] ) ? sanitize_text_field( $tax_raw['term'] ) : '',
				) );
			}
		}

		// Meta filters.
		if ( ! empty( $_POST['meta_filters'] ) ) {
			$meta_raw = json_decode( sanitize_text_field( wp_unslash( $_POST['meta_filters'] ) ), true );
			if ( is_array( $meta_raw ) ) {
				$meta_q = array();
				foreach ( $meta_raw as $mf ) {
					$mf = (array) $mf;
					if ( empty( $mf['key'] ) ) continue;
					$meta_q[] = array(
						'key'     => sanitize_key( $mf['key'] ),
						'value'   => isset( $mf['value'] ) ? sanitize_text_field( $mf['value'] ) : '',
						'compare' => '=',
					);
				}
				if ( $meta_q ) {
					$query_args['meta_query'] = $meta_q;
				}
			}
		}

		$query   = new WP_Query( $query_args );
		$results = array();
		foreach ( $query->posts as $p ) {
			$val       = ( 'slug' === $save_format ) ? $p->post_name : $p->ID;
			$results[] = array(
				'id'   => $val,
				'text' => $p->post_title,
			);
		}

		wp_send_json_success( array(
			'results' => $results,
			'more'    => $query->max_num_pages > $page,
		) );
	}
}
