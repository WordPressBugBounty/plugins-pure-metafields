<?php
/**
 * TPMeta_Builder
 *
 * Admin page registration & asset enqueue for the visual options
 * builder. The page itself is a single mount-point div populated by
 * the builder JS app.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options/builder
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Builder {

	const WELCOMED_FLAG = 'tpmeta_builder_welcomed';
	const PF_HOOK       = 'pure-fields_page_pure-fields-options-builder';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	protected function should_show_builder() {
		return isset( $_GET['start'] );
	}

	public function enqueue_assets( $hook ) {
		if ( self::PF_HOOK !== $hook ) {
			return;
		}

		// CSS covers both the welcome page and the builder UI.
		TPMeta_Assets::enqueue_builder_css();

		// Heavy builder JS is only needed when the builder itself is shown.
		if ( ! $this->should_show_builder() ) {
			return;
		}

		// Sortable + field-editor modal — shared with the Metafields Builder.
		TPMeta_Assets::enqueue_builder_js_libs();

		wp_enqueue_script(
			'tpmeta-builder',
			TPMETA_URL . 'options/builder/js/builder.js',
			array( 'tpmeta-sortable', 'tpmeta-builder-modal', 'wp-api-fetch' ),
			TPMETA_VERSION,
			true
		);

		// Initial state: list of existing panels (so the user can pick one
		// to edit) plus the field-type catalog.
		$bootstrap = array(
			'restRoot'  => esc_url_raw( rest_url( 'tpmeta/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'panels'    => TPMeta_Builder_Store::all(),
			'fieldTypes'=> $this->field_type_catalog(),
			'postTypes' => $this->get_registered_post_types(),
			'taxonomies'=> $this->get_registered_taxonomies(),
			// Portable image-token resolver map — drives live preview of
			// theme/plugin-relative defaults (e.g. {{theme_url}}/img/x.jpg).
			'imageTokenMap' => function_exists( 'tpmeta_image_token_prefix_map' )
				? tpmeta_image_token_prefix_map()
				: new stdClass(),
			'i18n'      => array(
				'newPanel'      => __( 'New Panel', 'pure-metafields' ),
				'newSection'    => __( 'New Section', 'pure-metafields' ),
				'untitled'      => __( 'Untitled', 'pure-metafields' ),
				'savePanel'     => __( 'Save Panel', 'pure-metafields' ),
				'bakeToPhp'     => __( 'Bake to PHP', 'pure-metafields' ),
				'saved'         => __( 'Saved', 'pure-metafields' ),
				'saving'        => __( 'Saving…', 'pure-metafields' ),
				'saveError'     => __( 'Save failed', 'pure-metafields' ),
				'confirmDelete' => __( 'Delete this item?', 'pure-metafields' ),
				'addField'      => __( 'Drag a field type here', 'pure-metafields' ),
				'editField'     => __( 'Edit Field', 'pure-metafields' ),
			),
		);

		wp_localize_script( 'tpmeta-builder', 'TPMetaBuilder', $bootstrap );
	}

	/**
	 * The catalog of field types the builder offers.
	 *
	 * @return array
	 */
	protected function field_type_catalog() {
		return array(
			array( 'type' => 'text',        'label' => __( 'Text',         'pure-metafields' ), 'icon' => 'editor-textcolor',  'hasOptions' => false ),
			array( 'type' => 'textarea',    'label' => __( 'Textarea',     'pure-metafields' ), 'icon' => 'editor-paragraph',  'hasOptions' => false ),
			array( 'type' => 'switch',      'label' => __( 'Switch',       'pure-metafields' ), 'icon' => 'controls-volumeon', 'hasOptions' => false ),
			array( 'type' => 'colorpicker', 'label' => __( 'Color',        'pure-metafields' ), 'icon' => 'art',               'hasOptions' => false ),
			array( 'type' => 'select',      'label' => __( 'Select',       'pure-metafields' ), 'icon' => 'menu-alt',          'hasOptions' => true  ),
			array( 'type' => 'checkbox',    'label' => __( 'Checkbox',     'pure-metafields' ), 'icon' => 'yes',               'hasOptions' => true  ),
			array( 'type' => 'tabs',        'label' => __( 'Button Set',   'pure-metafields' ), 'icon' => 'screenoptions',     'hasOptions' => true  ),
			array( 'type' => 'image',       'label' => __( 'Image',        'pure-metafields' ), 'icon' => 'format-image',      'hasOptions' => false ),
			array( 'type' => 'datepicker',  'label' => __( 'Date',         'pure-metafields' ), 'icon' => 'calendar-alt',      'hasOptions' => false ),
			array( 'type' => 'post_select', 'label' => __( 'Post Select',  'pure-metafields' ), 'icon' => 'admin-post',        'hasOptions' => false ),
			array( 'type' => 'radio_image',    'label' => __( 'Image Radio',    'pure-metafields' ), 'icon' => 'images-alt2',        'hasOptions' => true  ),
			array( 'type' => 'dimension',      'label' => __( 'Dimension',      'pure-metafields' ), 'icon' => 'editor-expand',      'hasOptions' => false ),
			array( 'type' => 'typography',     'label' => __( 'Typography',     'pure-metafields' ), 'icon' => 'editor-bold',        'hasOptions' => false ),
			array( 'type' => 'editor',         'label' => __( 'Editor',         'pure-metafields' ), 'icon' => 'edit',               'hasOptions' => false ),
			array( 'type' => 'multicolor',     'label' => __( 'Multi Color',    'pure-metafields' ), 'icon' => 'color-picker',       'hasOptions' => true  ),
			array( 'type' => 'multicheck',     'label' => __( 'Multi Check',    'pure-metafields' ), 'icon' => 'yes-alt',            'hasOptions' => true  ),
			array( 'type' => 'code',           'label' => __( 'Code',           'pure-metafields' ), 'icon' => 'editor-code',        'hasOptions' => false ),
			array( 'type' => 'radio_buttonset','label' => __( 'Radio Buttonset','pure-metafields' ), 'icon' => 'button',             'hasOptions' => true  ),
			array( 'type' => 'repeater',       'label' => __( 'Repeater',       'pure-metafields' ), 'icon' => 'list-view',          'hasOptions' => false ),
			array( 'type' => 'color_gradient', 'label' => __( 'Color Gradient', 'pure-metafields' ), 'icon' => 'admin-customizer',   'hasOptions' => false ),
			array( 'type' => 'spacing',        'label' => __( 'Spacing',        'pure-metafields' ), 'icon' => 'editor-expand',      'hasOptions' => false ),
		);
	}

	/**
	 * Returns all public post types for the bootstrap.
	 *
	 * @return array
	 */
	protected function get_registered_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $post_types as $pt ) {
			$result[] = array(
				'value' => $pt->name,
				'label' => $pt->label,
			);
		}
		return $result;
	}

	/**
	 * Returns all public taxonomies for the bootstrap.
	 *
	 * @return array
	 */
	protected function get_registered_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $taxonomies as $tax ) {
			$result[] = array(
				'value' => $tax->name,
				'label' => $tax->label,
			);
		}
		return $result;
	}

	/** Called directly from the Pure Fields sub-menu (no hook guard needed). */
	public function render_page_direct() {
		$this->render_page();
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pure-metafields' ) );
		}
		if ( $this->should_show_builder() ) {
			// Mark as welcomed the first time the user enters the builder.
			if ( ! get_option( self::WELCOMED_FLAG ) ) {
				update_option( self::WELCOMED_FLAG, true, false );
			}
			tpmeta_load_template( 'options/builder/templates/builder.php' );
		} else {
			// $first_visit is used by welcome.php to decide whether to play the
			// transition animation (only on the very first "Get Started" click).
			$first_visit = ! get_option( self::WELCOMED_FLAG );
			tpmeta_load_template( 'options/builder/templates/welcome.php' );
		}
	}
}
