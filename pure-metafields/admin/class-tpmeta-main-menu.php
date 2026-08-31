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
		echo '<style>#toplevel_page_pure-fields .wp-submenu .wp-first-item{display:none!important}</style>';
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
		// Top-level — renders the welcome/dashboard page.
		add_menu_page(
			__( 'Pure Fields', 'pure-metafields' ),
			__( 'Pure Fields', 'pure-metafields' ),
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
			: __( 'Option Builder', 'pure-metafields' ) . ' <span style="font-size:9px;opacity:.7">🔒 PRO</span>';

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
			__( '📖 Help & Docs', 'pure-metafields' ),
			'manage_options',
			'pure-fields-help',
			array( $this, 'render_help' )
		);

		if ( ! tpmeta_is_pro() ) {
			add_submenu_page(
				self::SLUG,
				__( 'Pro Features', 'pure-metafields' ),
				'<span style="color:#5F4AFE;font-weight:600">⭐ ' . __( 'Pro Features', 'pure-metafields' ) . '</span>',
				'manage_options',
				'pure-fields-pro',
				array( $this, 'render_pro_features' )
			);
		}
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
		$page_desc  = __( 'The Option Builder lets you create theme option panels visually with drag-and-drop. Upgrade to Pro to unlock this feature and more.', 'pure-metafields' );
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
		include TPMETA_PATH . 'admin/templates/pro-locked-page.php';
	}

	public function render_pro_features() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die();

		$page_title = __( 'Pro Features', 'pure-metafields' );
		$page_desc  = __( 'Unlock the complete theme development framework with these powerful features.', 'pure-metafields' );
		$locked_cards = array();
		foreach ( tpmeta_pro_features() as $feature ) {
			$locked_cards[] = array(
				'icon'  => $feature['icon'],
				'title' => $feature['label'],
				'desc'  => $feature['desc'],
			);
		}
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
