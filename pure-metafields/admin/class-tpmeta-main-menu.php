<?php
/**
 * TPMeta_Main_Menu
 *
 * Registers the "Pure Fields" top-level admin menu.
 *
 * Menu structure:
 *   Pure Fields             → Welcome / Dashboard
 *   ├─ Welcome              (same slug — renames the duplicate WP adds)
 *   ├─ Metafield Builder    pure-fields-metafields
 *   ├─ Option Builder       pure-fields-options-builder
 *   ├─ Post Types           pure-fields-post-types
 *   ├─ Taxonomies           pure-fields-taxonomies
 *   └─ Export / Import      pure-fields-export-import
 *
 * @package tpmeta
 * @since   1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Main_Menu {

	const SLUG = 'pure-fields';

	/** Every admin hook that belongs to Pure Fields. */
	const SCREENS = array(
		'toplevel_page_pure-fields',
		'pure-fields_page_pure-fields-metafields',
		'pure-fields_page_pure-fields-options-builder',
		'pure-fields_page_pure-fields-post-types',
		'pure-fields_page_pure-fields-taxonomies',
		'pure-fields_page_pure-fields-export-import',
		'pure-fields_page_pure-fields-help',
		'pure-fields_page_pure-fields-pro',
	);

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_shared' ) );
		add_action( 'current_screen',        array( $this, 'suppress_notices' ) );
		add_action( 'admin_head',            array( $this, 'hide_welcome_submenu' ) );
	}

	/**
	 * WordPress always duplicates the parent menu as the first sub-menu item.
	 * Hide it so only the real sub-pages (Metafield Builder, Option Builder…) appear.
	 * .wp-first-item is the class WP always assigns to that auto-generated duplicate.
	 */
	public function hide_welcome_submenu() {
		echo '<style>#toplevel_page_pure-fields .wp-submenu .wp-first-item{display:none!important}'
			. '#adminmenu .pf-menu-tag{display:inline-block;margin-left:4px;padding:0 5px;font-size:9px;line-height:15px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;vertical-align:middle;border:1px solid currentColor;border-radius:9999px;opacity:.8}'
			. '#adminmenu .pf-menu-pro{font-weight:600}</style>';
	}

	/**
	 * Remove all third-party notice hooks on Pure Fields pages.
	 * `current_screen` fires after notices are registered but before they output.
	 */
	public function suppress_notices( $screen ) {
		if ( ! in_array( $screen->id, self::SCREENS, true ) ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	public function register() {
		$menu_label = tpmeta_is_pro()
			? __( 'Pure Fields', 'pure-metafields' )
			: __( 'Pure Fields', 'pure-metafields' );

		// Top-level — renders the welcome/dashboard page.
		add_menu_page(
			__( 'Pure Fields Pro', 'pure-metafields' ),
			$menu_label,
			'manage_options',
			self::SLUG,
			array( $this, 'render_welcome' ),
			$this->menu_icon(),
			58
		);

		// WordPress duplicates the parent as the first sub-menu — rename it.
		add_submenu_page(
			self::SLUG,
			__( 'Welcome', 'pure-metafields' ),
			__( 'Welcome', 'pure-metafields' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_welcome' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Metafield Builder', 'pure-metafields' ),
			__( 'Metafield Builder', 'pure-metafields' ),
			'manage_options',
			'pure-fields-metafields',
			array( $this, 'render_metafields' )
		);

		$option_builder_label = tpmeta_is_pro()
			? __( 'Option Builder', 'pure-metafields' )
			: __( 'Option Builder', 'pure-metafields' ) . ' <span class="pf-menu-tag">' . __( 'Pro', 'pure-metafields' ) . '</span>';

		add_submenu_page(
			self::SLUG,
			__( 'Option Builder', 'pure-metafields' ),
			$option_builder_label,
			'manage_options',
			'pure-fields-options-builder',
			array( $this, 'render_options_builder' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Post Types', 'pure-metafields' ),
			__( 'Post Types', 'pure-metafields' ),
			'manage_options',
			'pure-fields-post-types',
			array( $this, 'render_post_types' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Taxonomies', 'pure-metafields' ),
			__( 'Taxonomies', 'pure-metafields' ),
			'manage_options',
			'pure-fields-taxonomies',
			array( $this, 'render_taxonomies' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Export / Import', 'pure-metafields' ),
			__( 'Export / Import', 'pure-metafields' ),
			'manage_options',
			'pure-fields-export-import',
			array( $this, 'render_export_import' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Help & Docs', 'pure-metafields' ),
			__( 'Help & Docs', 'pure-metafields' ),
			'manage_options',
			'pure-fields-help',
			array( $this, 'render_help' )
		);
	}

	public function render_welcome() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die();
		include TPMETA_PATH . 'admin/templates/welcome.php';
	}

	public function render_metafields() {
		if ( class_exists( 'TPMeta_Metafields_Builder' ) ) {
			( new TPMeta_Metafields_Builder() )->render_page();
		}
	}

	public function render_options_builder() {
		if ( tpmeta_is_pro() && class_exists( 'TPMeta_Builder' ) ) {
			( new TPMeta_Builder() )->render_page_direct();
			return;
		}

		$page_title = __( 'Option Builder', 'pure-metafields' );
		$page_desc  = __( 'Activate your license to unlock the Option Builder and all other Pro features.', 'pure-metafields' );
		$locked_cards = array();
		$features = tpmeta_pro_features();
		$card_keys = array( 'option_builder', 'options_framework', 'customizer', 'css_output', 'baketophp', 'starter_templates' );
		foreach ( $card_keys as $key ) {
			if ( isset( $features[ $key ] ) ) {
				$locked_cards[] = array(
					'icon'  => $features[ $key ]['icon'],
					'title' => $features[ $key ]['label'],
					'desc'  => $features[ $key ]['desc'],
				);
			}
		}
		$is_pro_plugin = true;
		include TPMETA_PATH . 'admin/templates/pro-locked-page.php';
	}

	public function render_pro_features() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die();

		$page_title = __( 'Pro Features', 'pure-metafields' );
		$page_desc  = __( 'Activate your license to unlock all Pro features.', 'pure-metafields' );
		$locked_cards = array();
		foreach ( tpmeta_pro_features() as $feature ) {
			$locked_cards[] = array(
				'icon'  => $feature['icon'],
				'title' => $feature['label'],
				'desc'  => $feature['desc'],
			);
		}
		$is_pro_plugin = true;
		include TPMETA_PATH . 'admin/templates/pro-locked-page.php';
	}

	public function render_post_types() {
		if ( class_exists( 'TPMeta_Post_Types_Page' ) ) {
			( new TPMeta_Post_Types_Page() )->render();
		}
	}

	public function render_taxonomies() {
		if ( class_exists( 'TPMeta_Taxonomies_Page' ) ) {
			( new TPMeta_Taxonomies_Page() )->render();
		}
	}

	public function render_export_import() {
		if ( class_exists( 'TPMeta_Export_Import' ) ) {
			( new TPMeta_Export_Import() )->render();
		}
	}

	public function render_help() {
		if ( ! class_exists( 'TPMeta_Help_Page' ) ) {
			require_once TPMETA_PATH . 'admin/class-tpmeta-help-page.php';
		}
		( new TPMeta_Help_Page() )->render();
	}

	/** Enqueue shared admin CSS on every Pure Fields page. */
	public function enqueue_shared( $hook ) {
		if ( ! in_array( $hook, self::SCREENS, true ) ) {
			return;
		}

		wp_enqueue_style(
			'tpmeta-admin-shared',
			TPMETA_URL . 'admin/css/tpmeta-admin.css',
			array( 'dashicons' ),
			TPMETA_VERSION
		);

		// Locked-card styles on Option Builder (free) and Pro Features pages.
		if ( in_array( $hook, array(
			'pure-fields_page_pure-fields-options-builder',
			'pure-fields_page_pure-fields-pro',
		), true ) && ! tpmeta_is_pro() ) {
			wp_enqueue_style(
				'tpmeta-pro-locked',
				TPMETA_URL . 'admin/css/pro-locked.css',
				array( 'tpmeta-admin-shared' ),
				TPMETA_VERSION
			);
		}

		// Help page gets its own stylesheet + JS.
		if ( 'pure-fields_page_pure-fields-help' === $hook ) {
			if ( ! class_exists( 'TPMeta_Help_Page' ) ) {
				require_once TPMETA_PATH . 'admin/class-tpmeta-help-page.php';
			}
			( new TPMeta_Help_Page() )->enqueue();
		}

		// Welcome page gets its own stylesheet + JS.
		if ( 'toplevel_page_pure-fields' === $hook ) {
			wp_enqueue_style(
				'tpmeta-welcome',
				TPMETA_URL . 'admin/css/welcome.css',
				array( 'tpmeta-admin-shared' ),
				TPMETA_VERSION
			);
			wp_enqueue_script(
				'tpmeta-welcome',
				TPMETA_URL . 'admin/js/welcome.js',
				array(),
				TPMETA_VERSION,
				true
			);
		}

		// Dedicated handle guarantees the global is available on every PF page,
		// even those that don't enqueue jQuery (e.g. the welcome page).
		wp_register_script( 'pf-admin-globals', false, array(), TPMETA_VERSION, false );
		wp_enqueue_script( 'pf-admin-globals' );
		wp_add_inline_script(
			'pf-admin-globals',
			'window.PureFields = ' . wp_json_encode( array(
				'nonce'    => wp_create_nonce( 'pf_nonce' ),
				'restUrl'  => esc_url_raw( rest_url( 'purefields/v1/' ) ),
				'adminUrl' => esc_url_raw( admin_url( 'admin.php' ) ),
			) ) . ';'
		);
	}

	/**
	 * Inline SVG from the Pure Fields icon set, sanitised with tpmeta_allowed_svg_tags().
	 *
	 * @param string $name  Icon key: metafields, post-types, taxonomies, options, export-import,
	 *                      lock, key, arrow, external, book, globe, sparkle, check.
	 * @param string $class Extra class for the wrapper span.
	 * @return string
	 */
	public static function icon( $name, $class = '' ) {
		$icons = self::icon_set();
		if ( ! isset( $icons[ $name ] ) ) {
			return '';
		}
		$svg     = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="100%" height="100%" fill="none">' . $icons[ $name ] . '</svg>';
		$allowed = function_exists( 'tpmeta_allowed_svg_tags' ) ? tpmeta_allowed_svg_tags() : array();
		// kses matches lowercased attribute names, so the camelCase 'viewBox' key never matches.
		if ( isset( $allowed['svg'] ) ) {
			$allowed['svg']['viewbox'] = true;
		}

		return '<span class="pf-ico pf-ico--' . esc_attr( $name ) . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" aria-hidden="true">'
			. wp_kses( $svg, $allowed )
			. '</span>';
	}

	private static function icon_set() {
		$s = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
		$c = 'fill="none" stroke="currentColor" stroke-width="2"';

		return array(
			'metafields'    => '<path d="M5.5 4 H18.5 A1.5 1.5 0 0 1 20 5.5 V18.5 A1.5 1.5 0 0 1 18.5 20 H5.5 A1.5 1.5 0 0 1 4 18.5 V5.5 A1.5 1.5 0 0 1 5.5 4 Z" ' . $s . '/>'
				. '<path d="M7.5 9 H8.5 M11.5 9 H16.5 M7.5 12.5 H8.5 M11.5 12.5 H16.5 M7.5 16 H8.5 M11.5 16 H14" ' . $s . '/>',
			'post-types'    => '<path d="M9 3.5 H15 L19 7.5 V16.5 A1.5 1.5 0 0 1 17.5 18 H9 A1.5 1.5 0 0 1 7.5 16.5 V5 A1.5 1.5 0 0 1 9 3.5 Z M15 3.5 V7.5 H19" ' . $s . '/>'
				. '<path d="M4.5 7.5 V19 A1.5 1.5 0 0 0 6 20.5 H14.5" ' . $s . '/>',
			'taxonomies'    => '<path d="M3.5 11.5 V5 A1.5 1.5 0 0 1 5 3.5 H11.5 L20.5 12.5 L12.5 20.5 Z" ' . $s . '/>'
				. '<circle cx="8" cy="8" r="1.5" ' . $c . '/>',
			'options'       => '<path d="M5 4 V10 M5 14 V20 M12 4 V8 M12 12 V20 M19 4 V13 M19 17 V20 M3 12 H7 M10 10 H14 M17 15 H21" ' . $s . '/>',
			'export-import' => '<path d="M12 14 V3.5 M8 7.5 L12 3.5 L16 7.5" ' . $s . '/>'
				. '<path d="M4.5 14 V18.5 A2 2 0 0 0 6.5 20.5 H17.5 A2 2 0 0 0 19.5 18.5 V14" ' . $s . '/>',
			'download'      => '<path d="M12 3.5 V14 M8 10 L12 14 L16 10" ' . $s . '/>'
				. '<path d="M4.5 14 V18.5 A2 2 0 0 0 6.5 20.5 H17.5 A2 2 0 0 0 19.5 18.5 V14" ' . $s . '/>',
			'layers'        => '<path d="M12 3.5 L20.5 8 L12 12.5 L3.5 8 Z M3.5 12 L12 16.5 L20.5 12 M3.5 16 L12 20.5 L20.5 16" ' . $s . '/>',
			'gear'          => '<circle cx="12" cy="12" r="3" ' . $c . '/><path d="M12 3.5 V6 M12 18 V20.5 M3.5 12 H6 M18 12 H20.5 M6 6 L7.8 7.8 M16.2 16.2 L18 18 M6 18 L7.8 16.2 M16.2 7.8 L18 6" ' . $s . '/>',
			'pencil'        => '<path d="M4 20 L4.8 16.2 L15.5 5.5 A2.1 2.1 0 0 1 18.5 8.5 L7.8 19.2 Z M13.5 7.5 L16.5 10.5" ' . $s . '/>',
			'save'          => '<path d="M5.5 4 H16 L20 8 V18.5 A1.5 1.5 0 0 1 18.5 20 H5.5 A1.5 1.5 0 0 1 4 18.5 V5.5 A1.5 1.5 0 0 1 5.5 4 Z M8 4 V8.5 H15 V4 M8 20 V14 H16 V20" ' . $s . '/>',
			'list'          => '<path d="M9 6 H20 M9 12 H20 M9 18 H20 M4.5 6 H5 M4.5 12 H5 M4.5 18 H5" ' . $s . '/>',
			'monitor'       => '<path d="M4.5 4.5 H19.5 A1.5 1.5 0 0 1 21 6 V15 A1.5 1.5 0 0 1 19.5 16.5 H4.5 A1.5 1.5 0 0 1 3 15 V6 A1.5 1.5 0 0 1 4.5 4.5 Z M9 20.5 H15 M12 16.5 V20.5" ' . $s . '/>',
			'brush'         => '<path d="M18.5 3.5 L20.5 5.5 L12 14 L10 12 Z M10 14 C7.5 14 6 15.5 6 17.5 C6 19 5 20 3.5 20.5 C6 21.5 11 21 11 16.5" ' . $s . '/>',
			'plus'          => '<path d="M12 5 V19 M5 12 H19" ' . $s . '/>',
			'flag'          => '<path d="M5 21 V4 M5 4.5 H17 L15 8.5 L17 12.5 H5" ' . $s . '/>',
			'user'          => '<circle cx="12" cy="8" r="3.5" ' . $c . '/><path d="M4.5 20.5 C5.5 16.5 8.5 14.5 12 14.5 C15.5 14.5 18.5 16.5 19.5 20.5" ' . $s . '/>',
			'grid'          => '<path d="M4 4 H10 V10 H4 Z M14 4 H20 V10 H14 Z M4 14 H10 V20 H4 Z M14 14 H20 V20 H14 Z" ' . $s . '/>',
			'image'         => '<path d="M5.5 4 H18.5 A1.5 1.5 0 0 1 20 5.5 V18.5 A1.5 1.5 0 0 1 18.5 20 H5.5 A1.5 1.5 0 0 1 4 18.5 V5.5 A1.5 1.5 0 0 1 5.5 4 Z M20 15.5 L15.5 11 L5 20" ' . $s . '/><circle cx="9" cy="9" r="1.5" ' . $c . '/>',
			'repeat'        => '<path d="M17 3.5 L20.5 7 L17 10.5 M20.5 7 H8 A4.5 4.5 0 0 0 3.5 11.5 V12 M7 20.5 L3.5 17 L7 13.5 M3.5 17 H16 A4.5 4.5 0 0 0 20.5 12.5 V12" ' . $s . '/>',
			'code'          => '<path d="M8.5 7 L3.5 12 L8.5 17 M15.5 7 L20.5 12 L15.5 17 M13.5 4.5 L10.5 19.5" ' . $s . '/>',
			'table'         => '<path d="M5.5 4 H18.5 A1.5 1.5 0 0 1 20 5.5 V18.5 A1.5 1.5 0 0 1 18.5 20 H5.5 A1.5 1.5 0 0 1 4 18.5 V5.5 A1.5 1.5 0 0 1 5.5 4 Z M4 9.5 H20 M4 15 H20 M10 9.5 V20" ' . $s . '/>',
			'tip'           => '<path d="M9 18 H15 M10 21 H14 M12 3 A6 6 0 0 0 8.5 13.9 C9.3 14.6 9.5 15.4 9.5 16.5 V18 H14.5 V16.5 C14.5 15.4 14.7 14.6 15.5 13.9 A6 6 0 0 0 12 3 Z" ' . $s . '/>',
			'info'          => '<circle cx="12" cy="12" r="8.5" ' . $c . '/><path d="M12 11 V16.5 M12 7.5 V8" ' . $s . '/>',
			'lock'          => '<path d="M6.5 11 H17.5 A1.5 1.5 0 0 1 19 12.5 V19 A1.5 1.5 0 0 1 17.5 20.5 H6.5 A1.5 1.5 0 0 1 5 19 V12.5 A1.5 1.5 0 0 1 6.5 11 Z M8 11 V7.5 A4 4 0 0 1 16 7.5 V11" ' . $s . '/>',
			'key'           => '<circle cx="8" cy="15" r="4" ' . $c . '/><path d="M11 12 L20 3 M16.5 6.5 L19 9 M14 9 L16 11" ' . $s . '/>',
			'arrow'         => '<path d="M5 12 H19 M13 6 L19 12 L13 18" ' . $s . '/>',
			'external'      => '<path d="M14 4 H20 V10 M20 4 L11 13 M18 14 V18.5 A1.5 1.5 0 0 1 16.5 20 H5.5 A1.5 1.5 0 0 1 4 18.5 V7.5 A1.5 1.5 0 0 1 5.5 6 H10" ' . $s . '/>',
			'book'          => '<path d="M12 6.5 C10.5 5 8 4.5 4 4.5 V18 C8 18 10.5 18.5 12 20 C13.5 18.5 16 18 20 18 V4.5 C16 4.5 13.5 5 12 6.5 Z M12 6.5 V20" ' . $s . '/>',
			'globe'         => '<circle cx="12" cy="12" r="8.5" ' . $c . '/><path d="M3.5 12 H20.5 M12 3.5 C9.5 6 9.5 18 12 20.5 M12 3.5 C14.5 6 14.5 18 12 20.5" ' . $s . '/>',
			'sparkle'       => '<path d="M12 3.5 L13.9 10.1 L20.5 12 L13.9 13.9 L12 20.5 L10.1 13.9 L3.5 12 L10.1 10.1 Z" ' . $s . '/>',
			'check'         => '<path d="M5 12.5 L10 17.5 L19 7" ' . $s . '/>',
		);
	}

	private static $menu_icon_cache = null;

	private function menu_icon() {
		if ( null === self::$menu_icon_cache ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">'
				. '<rect x="2" y="3" width="7" height="7" rx="1.5"/>'
				. '<rect x="11" y="3" width="7" height="7" rx="1.5"/>'
				. '<rect x="2" y="12" width="7" height="5" rx="1.5"/>'
				. '<rect x="11" y="12" width="7" height="5" rx="1.5"/>'
				. '</svg>';
			self::$menu_icon_cache = 'data:image/svg+xml;base64,' . base64_encode( $svg );
		}
		return self::$menu_icon_cache;
	}
}
