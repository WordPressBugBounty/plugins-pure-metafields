<?php
/**
 * TPMeta_Help_Page
 *
 * Renders the Pure Fields → Help developer quick-reference page.
 *
 * @package tpmeta
 * @since   1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Help_Page {

	public function enqueue() {
		wp_enqueue_style(
			'tpmeta-help',
			TPMETA_URL . 'admin/css/tpmeta-help.css',
			array( 'dashicons' ),
			TPMETA_VERSION
		);
		wp_enqueue_script(
			'tpmeta-help',
			TPMETA_URL . 'admin/js/tpmeta-help.js',
			array( 'jquery' ),
			TPMETA_VERSION,
			true
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		include TPMETA_PATH . 'admin/templates/help.php';
	}
}
