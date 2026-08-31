<?php
/**
 * TPMeta_Metafields_Builder
 * Page class for the Metafield Builder admin page.
 *
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Metafields_Builder {

	const HOOK = 'pure-fields_page_pure-fields-metafields';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ) {
		if ( self::HOOK !== $hook ) return;

		// Shared base CSS + JS libs (SortableJS + field-editor modal).
		TPMeta_Assets::enqueue_builder_libs();

		wp_enqueue_style(
			'tpmeta-metafields-builder',
			TPMETA_URL . 'metafields-builder/css/metafields-builder.css',
			array( 'tpmeta-builder' ),
			TPMETA_VERSION
		);

		wp_enqueue_script(
			'tpmeta-metafields-builder',
			TPMETA_URL . 'metafields-builder/js/metafields-builder.js',
			array( 'tpmeta-sortable', 'tpmeta-builder-modal', 'wp-api-fetch' ),
			TPMETA_VERSION,
			true
		);

		wp_localize_script( 'tpmeta-metafields-builder', 'TPMFBuilder', array(
			'restRoot'       => esc_url_raw( rest_url( 'purefields/v1/' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'metaboxes'      => TPMeta_Metafields_Store::all(),
			// Portable image-token resolver map — used by the field-editor
			// modal's image-default picker to live-preview tokenised paths.
			'imageTokenMap'  => function_exists( 'tpmeta_image_token_prefix_map' )
				? tpmeta_image_token_prefix_map()
				: new stdClass(),
			// Initial scan results — saves a REST round-trip on first paint.
			// Empty arrays/null when the scanner class isn't loaded yet.
			'themeMetaboxes' => class_exists( 'TPMeta_Metafields_Scanner' ) ? TPMeta_Metafields_Scanner::scan_theme_metaboxes() : array(),
			'themeUserMeta'  => class_exists( 'TPMeta_Metafields_Scanner' ) ? TPMeta_Metafields_Scanner::scan_theme_user_meta()  : null,
			'postTypes'      => $this->get_post_types(),
			'taxonomies'     => $this->get_taxonomies(),
			'fieldTypes'     => $this->field_type_catalog(),
			'i18n'           => array(
				'newSection'    => __( 'New Section',   'pure-metafields' ),
				'untitled'      => __( 'Untitled',      'pure-metafields' ),
				'addField'      => __( 'Drag a field type here', 'pure-metafields' ),
				'editField'     => __( 'Edit Field',    'pure-metafields' ),
				'confirmDelete' => __( 'Delete this item?', 'pure-metafields' ),
			),
		) );

		// builder-modal.js reads window.TPMetaBuilder (Options Builder global).
		// Shim it from TPMFBuilder so the field editor modal works in this context.
		wp_add_inline_script(
			'tpmeta-metafields-builder',
			'if (typeof TPMetaBuilder === "undefined" && typeof TPMFBuilder !== "undefined") {
				window.TPMetaBuilder = {
					i18n:          Object.assign({ editField: "Edit Field", untitled: "Untitled" }, TPMFBuilder.i18n || {}),
					postTypes:     TPMFBuilder.postTypes     || [],
					taxonomies:    TPMFBuilder.taxonomies    || [],
					fieldTypes:    TPMFBuilder.fieldTypes    || [],
					imageTokenMap: TPMFBuilder.imageTokenMap || {},
				};
			}',
			'after'
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pure-metafields' ) );
		}
		include TPMETA_PATH . 'metafields-builder/templates/metafields-builder.php';
	}

	private function get_post_types() {
		$pts    = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $pts as $pt ) {
			$result[] = array( 'value' => $pt->name, 'label' => $pt->label );
		}
		return $result;
	}

	private function get_taxonomies() {
		$taxs   = get_taxonomies( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $taxs as $tax ) {
			$result[] = array( 'value' => $tax->name, 'label' => $tax->label );
		}
		return $result;
	}

	private function field_type_catalog() {
		return array(
			array( 'type' => 'text',           'label' => 'Text',           'icon' => 'editor-textcolor',  'group' => 'basic' ),
			array( 'type' => 'textarea',        'label' => 'Textarea',       'icon' => 'editor-paragraph',  'group' => 'basic' ),
			array( 'type' => 'editor',          'label' => 'Rich Editor',    'icon' => 'editor-kitchensink','group' => 'basic' ),
			array( 'type' => 'switch',          'label' => 'Switch',         'icon' => 'controls-volumeon', 'group' => 'basic' ),
			array( 'type' => 'select',          'label' => 'Select',         'icon' => 'menu-alt',          'group' => 'basic' ),
			array( 'type' => 'checkbox',        'label' => 'Checkbox',       'icon' => 'yes',               'group' => 'basic' ),
			array( 'type' => 'tabs',            'label' => 'Button Set',     'icon' => 'screenoptions',     'group' => 'basic' ),
			array( 'type' => 'colorpicker',     'label' => 'Color Picker',   'icon' => 'art',               'group' => 'media' ),
			array( 'type' => 'multicolor',      'label' => 'Multi Color',    'icon' => 'color-picker',      'group' => 'media' ),
			array( 'type' => 'image',           'label' => 'Image',          'icon' => 'format-image',      'group' => 'media' ),
			array( 'type' => 'gallery',         'label' => 'Gallery',        'icon' => 'images-alt2',       'group' => 'media' ),
			array( 'type' => 'datepicker',      'label' => 'Date Picker',    'icon' => 'calendar-alt',      'group' => 'advanced' ),
			array( 'type' => 'select_posts',    'label' => 'Post Select',    'icon' => 'admin-post',        'group' => 'advanced' ),
			array( 'type' => 'dimension',       'label' => 'Dimension',      'icon' => 'image-flip-horizontal', 'group' => 'advanced' ),
			array( 'type' => 'repeater',        'label' => 'Repeater',       'icon' => 'list-view',         'group' => 'advanced' ),
		);
	}
}
