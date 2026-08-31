<?php
/**
 * TPMeta_Taxonomies_Page — admin page for the Taxonomy builder.
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Taxonomies_Page {

	const HOOK = 'pure-fields_page_pure-fields-taxonomies';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_pf_save_taxonomy',   array( $this, 'ajax_save'   ) );
		add_action( 'wp_ajax_pf_delete_taxonomy', array( $this, 'ajax_delete' ) );
	}

	public function enqueue( $hook ) {
		if ( self::HOOK !== $hook ) return;

		// Taxonomies shares the full layout CSS from post-types.css.
		wp_enqueue_style( 'tpmeta-post-types',
			TPMETA_URL . 'post-types/css/post-types.css',
			array( 'tpmeta-admin-shared', 'dashicons' ), TPMETA_VERSION );
		wp_enqueue_style(
			'tpmeta-taxonomies',
			TPMETA_URL . 'taxonomies/css/taxonomies.css',
			array( 'tpmeta-post-types' ),
			TPMETA_VERSION
		);
		wp_enqueue_script(
			'tpmeta-taxonomies',
			TPMETA_URL . 'taxonomies/js/taxonomies.js',
			array( 'jquery' ),
			TPMETA_VERSION,
			true
		);
		wp_localize_script( 'tpmeta-taxonomies', 'PFTaxonomies', array(
			'nonce'      => wp_create_nonce( 'pf_nonce' ),
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'taxonomies' => TPMeta_Taxonomies_Store::all(),
			'postTypes'  => $this->get_all_post_types(),
		) );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die();
		include TPMETA_PATH . 'taxonomies/templates/taxonomies.php';
	}

	public function ajax_save() {
		check_ajax_referer( 'pf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission', 403 );
		$data = json_decode( stripslashes( $_POST['data'] ?? '' ), true );
		if ( empty( $data ) ) wp_send_json_error( 'Invalid data' );
		$saved = TPMeta_Taxonomies_Store::save( $data );
		$saved ? wp_send_json_success( $saved ) : wp_send_json_error( 'Save failed' );
	}

	public function ajax_delete() {
		check_ajax_referer( 'pf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission', 403 );
		$slug = sanitize_key( $_POST['slug'] ?? '' );
		if ( empty( $slug ) ) wp_send_json_error( 'Missing slug' );
		TPMeta_Taxonomies_Store::delete( $slug );
		wp_send_json_success( array( 'deleted' => $slug ) );
	}

	private function get_all_post_types() {
		$pts    = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $pts as $pt ) {
			$result[] = array( 'value' => $pt->name, 'label' => $pt->label );
		}
		return $result;
	}
}
