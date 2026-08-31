<?php
/**
 * TPMeta_Options_Render
 *
 * Registers admin menu pages and renders the options panel UI.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Options_Render {

	/**
	 * Bootstrap hooks.
	 */
	/** Font extensions → canonical MIME types. */
	private static $font_mimes = array(
		'woff2' => 'font/woff2',
		'woff'  => 'font/woff',
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
		'eot'   => 'application/vnd.ms-fontobject',
	);

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menus' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Two filters are required to allow font uploads.
		// 1. upload_mimes  — adds extensions to the allowed list.
		// 2. wp_check_filetype_and_ext — bypasses server-side MIME sniffing which
		//    typically returns 'application/octet-stream' for font files, causing WP
		//    to reject them even if the extension is in upload_mimes.
		add_filter( 'upload_mimes',              array( $this, 'allow_font_mimes' ), 10, 1 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_font_filetype' ), 10, 4 );
	}

	/**
	 * Add font extensions to the allowed upload MIME types.
	 *
	 * @param  array $mimes
	 * @return array
	 */
	public function allow_font_mimes( array $mimes ) {
		return array_merge( $mimes, self::$font_mimes );
	}

	/**
	 * Override WordPress's server-side MIME detection for font files.
	 *
	 * wp_check_filetype_and_ext() uses finfo/mime_content_type to sniff the
	 * file's actual MIME, which returns 'application/octet-stream' for most font
	 * formats. When the sniffed MIME doesn't match the declared one WordPress
	 * rejects the upload. We short-circuit that check for known font extensions.
	 *
	 * @param  array       $data      { ext, type, proper_filename }
	 * @param  string      $file      Temporary file path.
	 * @param  string      $filename  Original file name.
	 * @param  array|null  $mimes     Allowed MIME map.
	 * @return array
	 */
	public function fix_font_filetype( $data, $file, $filename, $mimes ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( isset( self::$font_mimes[ $ext ] ) ) {
			$data['ext']  = $ext;
			$data['type'] = self::$font_mimes[ $ext ];
		}
		return $data;
	}

	/**
	 * Register a top-level menu for each registered panel.
	 */
	public function register_menus() {
		$panels = TPMeta_Options::get_panels();

		foreach ( $panels as $opt_name => $args ) {
			add_menu_page(
				$args['page_title'],
				$args['menu_title'],
				$args['capability'],
				$args['menu_slug'],
				array( $this, 'render_page' ),
				$args['menu_icon'],
				$args['menu_position']
			);
		}
	}

	/**
	 * Enqueue assets only on our pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$panel = $this->detect_panel_from_hook( $hook );
		if ( ! $panel ) {
			return;
		}

		// Shared field runtime (CSS + JS) — same bundle the metabox screen uses.
		TPMeta_Assets::enqueue_field_runtime();

		// Panel chrome (sidebar tabs, header, save bar) — additive on top of field CSS.
		wp_enqueue_style(
			'tpmeta-options',
			TPMETA_URL . 'options/css/tpmeta-options.css',
			array( 'tm-metabox-css' ),
			TPMETA_VERSION,
			'all'
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'tpmeta-options',
			TPMETA_URL . 'options/js/tpmeta-options.js',
			array( 'jquery', 'tm-metabox-js' ),
			TPMETA_VERSION,
			true
		);

		wp_localize_script( 'tpmeta-options', 'TPMetaOptions', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tpmeta_options_save' ),
			'optName' => $panel['opt_name'],
			'i18n'    => array(
				'saving'    => __( 'Saving…', 'pure-metafields' ),
				'saved'     => __( 'Settings saved.', 'pure-metafields' ),
				'saveError' => __( 'Could not save settings.', 'pure-metafields' ),
			),
		) );
	}

	/**
	 * Try to match the current admin hook to a registered panel.
	 *
	 * @param string $hook
	 * @return array|null
	 */
	protected function detect_panel_from_hook( $hook ) {
		$panels = TPMeta_Options::get_panels();
		foreach ( $panels as $args ) {
			// add_menu_page hook suffix is "toplevel_page_{menu_slug}".
			if ( $hook === 'toplevel_page_' . $args['menu_slug'] ) {
				return $args;
			}
		}
		return null;
	}

	/**
	 * Render the options page (called by add_menu_page callback).
	 */
	public function render_page() {
		$slug   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$panels = TPMeta_Options::get_panels();
		$panel  = null;
		foreach ( $panels as $args ) {
			if ( $args['menu_slug'] === $slug ) {
				$panel = $args;
				break;
			}
		}

		if ( ! $panel ) {
			return;
		}

		if ( ! current_user_can( $panel['capability'] ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pure-metafields' ) );
		}

		$sections = TPMeta_Options::get_sections( $panel['opt_name'] );

		// If this panel was built via the Options Builder, pass the raw builder
		// JSON so the template can render the correct row/grid/flex layout.
		$builder_panel = null;
		if ( class_exists( 'TPMeta_Builder_Store' ) ) {
			$builder_panel = TPMeta_Builder_Store::get( $panel['opt_name'] );
		}

		tpmeta_load_template( 'options/templates/panel.php', array(
			'panel'         => $panel,
			'sections'      => $sections,
			'builder_panel' => $builder_panel,
		) );
	}
}
