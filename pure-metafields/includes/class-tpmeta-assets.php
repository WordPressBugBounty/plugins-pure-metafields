<?php
/**
 * TPMeta_Assets
 *
 * Centralised enqueue helpers for assets shared between the metabox
 * runtime, the options-panel runtime, and both visual builders.
 *
 * Two bundles:
 *   - field_runtime  → CSS/JS used wherever fields are rendered
 *                      (post-edit metaboxes + options panels).
 *   - builder_libs   → libs shared by the Options Builder and the
 *                      Metafields Builder admin pages.
 *
 * Public script/style handles are preserved so any theme/plugin
 * depending on them ('tm-metabox-js', 'tm-metabox-css', 'select2',
 * 'dragula', 'tpmeta-builder', 'tpmeta-sortable',
 * 'tpmeta-builder-modal') continues to work after the move.
 *
 * @package    tpmeta
 * @since      1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Assets {

	/**
	 * Enqueue the shared field runtime (CSS + JS) used wherever
	 * pure-metafields fields are rendered. Idempotent — safe to call
	 * multiple times.
	 */
	public static function enqueue_field_runtime() {
		// ---- Styles --------------------------------------------------
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'select2',
			TPMETA_URL . 'assets/vendor/css/select2.min.css',
			array(),
			TPMETA_VERSION
		);
		wp_enqueue_style(
			'dragula',
			TPMETA_URL . 'assets/vendor/css/dragula.min.css',
			array(),
			TPMETA_VERSION
		);
		wp_enqueue_style(
			'tm-metabox-css',
			TPMETA_URL . 'assets/css/tpmeta-fields.css',
			array(),
			TPMETA_VERSION
		);

		// ---- Scripts -------------------------------------------------
		wp_enqueue_script(
			'select2',
			TPMETA_URL . 'assets/vendor/js/select2.min.js',
			array( 'jquery' ),
			TPMETA_VERSION,
			true
		);
		wp_enqueue_script(
			'dragula',
			TPMETA_URL . 'assets/vendor/js/dragula.min.js',
			array( 'jquery' ),
			TPMETA_VERSION,
			true
		);
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script(
			'tm-metabox-js',
			TPMETA_URL . 'assets/js/tpmeta-fields.js',
			array( 'jquery', 'jquery-ui-datepicker', 'wp-color-picker' ),
			TPMETA_VERSION,
			true
		);
	}

	/**
	 * Enqueue the user-fields-only stylesheet. Loaded by the metabox
	 * bootstrap on profile.php and user-edit.php only, so its 50% width
	 * cap + gradient number-input visibility fixes can't leak into other
	 * admin screens. Depends on tm-metabox-css for the design tokens it
	 * overrides.
	 */
	public static function enqueue_user_fields_css() {
		wp_enqueue_style(
			'tpmeta-user-fields',
			TPMETA_URL . 'assets/css/tpmeta-user-fields.css',
			array( 'tm-metabox-css' ),
			TPMETA_VERSION
		);
	}

	/**
	 * Register (without enqueuing) the metabox repeater script. Field
	 * templates that need it call wp_enqueue_script('repeater') directly
	 * to avoid loading it on screens that don't use the repeater field.
	 */
	public static function register_metabox_repeater() {
		if ( wp_script_is( 'repeater', 'registered' ) ) return;
		wp_register_script(
			'repeater',
			TPMETA_URL . 'metaboxes/js/repeater.js',
			array( 'jquery', 'jquery-ui-datepicker', 'dragula' ),
			TPMETA_VERSION,
			true
		);
	}

	/**
	 * Enqueue the base CSS used by every builder page (Options Builder
	 * welcome screen, the builder itself, and the Metafields Builder).
	 *
	 * tm-metabox-css is pulled in so the field-editor modal's inline
	 * datepicker (rendered inside <dialog> via .tm-datepicker) inherits
	 * the same icon-sprite + header/cell styling as the front-end picker.
	 */
	public static function enqueue_builder_css() {
		wp_enqueue_style(
			'tm-metabox-css',
			TPMETA_URL . 'assets/css/tpmeta-fields.css',
			array(),
			TPMETA_VERSION
		);
		wp_enqueue_style(
			'tpmeta-builder',
			TPMETA_URL . 'assets/css/tpmeta-builder.css',
			array( 'dashicons', 'tm-metabox-css' ),
			TPMETA_VERSION
		);
	}

	/**
	 * Enqueue the JS libs shared by the Options Builder + Metafields
	 * Builder app pages: SortableJS and the field-editor modal.
	 * App-specific JS (builder.js / metafields-builder.js) is enqueued
	 * separately by each page and depends on these handles.
	 */
	public static function enqueue_builder_js_libs() {
		wp_enqueue_script(
			'tpmeta-sortable',
			TPMETA_URL . 'assets/vendor/js/sortable.min.js',
			array(),
			TPMETA_VERSION,
			true
		);
		// jquery-ui-datepicker is required by the field-editor modal so
		// the datepicker field's "Default value" input can be wired as
		// an inline calendar — see wireDefaultDatepicker() in
		// tpmeta-builder-modal.js.
		wp_enqueue_script(
			'tpmeta-builder-modal',
			TPMETA_URL . 'assets/js/tpmeta-builder-modal.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			TPMETA_VERSION,
			true
		);
		// Image-field "Default Image" picker uses wp.media() for the Media
		// Library button. Safe to call on every builder page — no-op when
		// already enqueued by another module.
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Convenience: enqueue both the builder CSS and the builder JS libs
	 * in one call. Use on pages that always need both (e.g., the
	 * Metafields Builder). The Options Builder splits these because its
	 * welcome page only needs CSS.
	 */
	public static function enqueue_builder_libs() {
		self::enqueue_builder_css();
		self::enqueue_builder_js_libs();
	}
}
