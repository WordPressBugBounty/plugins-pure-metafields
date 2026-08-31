<?php
/**
 * TPMeta_Post_Types_Page — admin page for the Post Types builder.
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Post_Types_Page {

	const HOOK = 'pure-fields_page_pure-fields-post-types';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_pf_save_post_type',   array( $this, 'ajax_save'   ) );
		add_action( 'wp_ajax_pf_delete_post_type', array( $this, 'ajax_delete' ) );
	}

	public function enqueue( $hook ) {
		if ( self::HOOK !== $hook ) return;

		wp_enqueue_style(
			'tpmeta-post-types',
			TPMETA_URL . 'post-types/css/post-types.css',
			array( 'tpmeta-admin-shared', 'dashicons' ),
			TPMETA_VERSION
		);
		wp_enqueue_script(
			'tpmeta-post-types',
			TPMETA_URL . 'post-types/js/post-types.js',
			array( 'jquery' ),
			TPMETA_VERSION,
			true
		);
		wp_localize_script( 'tpmeta-post-types', 'PFPostTypes', array(
			'nonce'     => wp_create_nonce( 'pf_nonce' ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'postTypes' => TPMeta_Post_Types_Store::all(),
			'taxonomies'=> $this->get_all_taxonomies(),
		) );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die();
		include TPMETA_PATH . 'post-types/templates/post-types.php';
	}

	public function ajax_save() {
		check_ajax_referer( 'pf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission', 403 );
		$data = json_decode( stripslashes( $_POST['data'] ?? '' ), true );
		if ( empty( $data ) ) wp_send_json_error( 'Invalid data' );
		$saved = TPMeta_Post_Types_Store::save( $data );
		$saved ? wp_send_json_success( $saved ) : wp_send_json_error( 'Save failed' );
	}

	public function ajax_delete() {
		check_ajax_referer( 'pf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission', 403 );
		$slug = sanitize_key( $_POST['slug'] ?? '' );
		if ( empty( $slug ) ) wp_send_json_error( 'Missing slug' );
		TPMeta_Post_Types_Store::delete( $slug );
		wp_send_json_success( array( 'deleted' => $slug ) );
	}

	private function get_all_taxonomies() {
		$taxs   = get_taxonomies( array(), 'objects' );
		$result = array();
		foreach ( $taxs as $tax ) {
			$result[] = array( 'value' => $tax->name, 'label' => $tax->label );
		}
		return $result;
	}
}
