<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://themepure.net
 * @since             1.0.0
 * @package           tpmeta
 *
 * @wordpress-plugin
 * Plugin Name:       PureFields Pro – Meta Fields, Theme Options & Custom Post Type Builder
 * Plugin URI:        https://themepure.net/plugins/pure-metafields-pro/
 * Description:       The complete theme development framework — custom metaboxes, option panels, Customizer integration, CSS auto-output, advanced field types, Bake to PHP, and more.
 * Version:           1.7.4
 * Author:            ThemePure
 * Author URI:        https://themepure.net
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pure-metafields
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'TPMETA_VERSION', '1.7.4' );
define( 'TPMETA_PATH', plugin_dir_path(__FILE__) );
define( 'TPMETA_URL', plugin_dir_url(__FILE__) );

/**
 * Check whether Pro features are unlocked.
 *
 * The Pro plugin currently ships without license gating, so all Pro
 * features are always available.
 *
 * @return bool
 */
if ( ! function_exists( 'tpmeta_is_pro' ) ) {
	function tpmeta_is_pro() {
		return true;
	}
}

/**
 * Pro feature definitions used by the free version to render
 * locked-card upsells.  Each entry: id, label, icon (dashicon), description.
 *
 * @return array
 */
if ( ! function_exists( 'tpmeta_pro_features' ) ) {
	function tpmeta_pro_features() {
		return array(
			'option_builder' => array(
				'label' => __( 'Option Builder', 'pure-metafields' ),
				'icon'  => 'layout',
				'desc'  => __( 'Build theme option panels visually with drag-and-drop.', 'pure-metafields' ),
			),
			'options_framework' => array(
				'label' => __( 'Options Framework', 'pure-metafields' ),
				'icon'  => 'admin-settings',
				'desc'  => __( 'Code-based theme options with theme_mod storage.', 'pure-metafields' ),
			),
			'customizer' => array(
				'label' => __( 'Customizer Integration', 'pure-metafields' ),
				'icon'  => 'visibility',
				'desc'  => __( 'Mirror option panels into the WordPress Customizer with live preview.', 'pure-metafields' ),
			),
			'css_output' => array(
				'label' => __( 'CSS Auto-Output', 'pure-metafields' ),
				'icon'  => 'editor-code',
				'desc'  => __( 'Auto-generate frontend CSS from typography, spacing, and color fields.', 'pure-metafields' ),
			),
			'typography' => array(
				'label' => __( 'Typography Field', 'pure-metafields' ),
				'icon'  => 'editor-textcolor',
				'desc'  => __( 'Full font control — family, size, weight, line-height, letter-spacing.', 'pure-metafields' ),
			),
			'spacing' => array(
				'label' => __( 'Spacing Field', 'pure-metafields' ),
				'icon'  => 'image-flip-vertical',
				'desc'  => __( 'Top/right/bottom/left spacing with unit control.', 'pure-metafields' ),
			),
			'color_gradient' => array(
				'label' => __( 'Color Gradient Field', 'pure-metafields' ),
				'icon'  => 'art',
				'desc'  => __( 'Multi-stop gradient builder with angle and type control.', 'pure-metafields' ),
			),
			'code_field' => array(
				'label' => __( 'Code Field', 'pure-metafields' ),
				'icon'  => 'editor-ltr',
				'desc'  => __( 'Code editor for HTML, CSS, and JavaScript.', 'pure-metafields' ),
			),
			'radio_image' => array(
				'label' => __( 'Radio Image Field', 'pure-metafields' ),
				'icon'  => 'format-image',
				'desc'  => __( 'Image-based layout selector for visual choices.', 'pure-metafields' ),
			),
			'radio_buttonset' => array(
				'label' => __( 'Radio Buttonset', 'pure-metafields' ),
				'icon'  => 'forms',
				'desc'  => __( 'Styled button set selector.', 'pure-metafields' ),
			),
			'multicheck' => array(
				'label' => __( 'Multi-Check Field', 'pure-metafields' ),
				'icon'  => 'yes-alt',
				'desc'  => __( 'Multi-checkbox with select-all support.', 'pure-metafields' ),
			),
			'baketophp' => array(
				'label' => __( 'Bake to PHP', 'pure-metafields' ),
				'icon'  => 'media-code',
				'desc'  => __( 'Export visual builder panels as committable PHP files.', 'pure-metafields' ),
			),
			'kirki_migration' => array(
				'label' => __( 'Kirki Migration', 'pure-metafields' ),
				'icon'  => 'migrate',
				'desc'  => __( 'Import Kirki PHP code and convert to native panels.', 'pure-metafields' ),
			),
			'redux_migration' => array(
				'label' => __( 'Redux Migration', 'pure-metafields' ),
				'icon'  => 'migrate',
				'desc'  => __( 'Import Redux PHP code and convert to native panels.', 'pure-metafields' ),
			),
			'theme_scanner' => array(
				'label' => __( 'Theme Scanner', 'pure-metafields' ),
				'icon'  => 'search',
				'desc'  => __( 'Discover and import metaboxes registered by themes.', 'pure-metafields' ),
			),
			'starter_templates' => array(
				'label' => __( 'Starter Templates', 'pure-metafields' ),
				'icon'  => 'block-default',
				'desc'  => __( 'Pre-built panel templates to get started quickly.', 'pure-metafields' ),
			),
			'rest_api' => array(
				'label' => __( 'REST API', 'pure-metafields' ),
				'icon'  => 'cloud',
				'desc'  => __( 'Full CRUD access to builders via REST API endpoints.', 'pure-metafields' ),
			),
		);
	}
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-pure-metafields-activator.php
 */
if(!function_exists('tpmeta_activate_tp_metabox')){
	function tpmeta_activate_tp_metabox() {
		require_once TPMETA_PATH . 'includes/class-pure-metafields-activator.php';
		tpmeta_activator::activate();
	}
	register_activation_hook( __FILE__, 'tpmeta_activate_tp_metabox' );
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-pure-metafields-deactivator.php
 */
if(!function_exists('tpmeta_deactivate_tp_metabox')){
	function tpmeta_deactivate_tp_metabox() {
		require_once TPMETA_PATH . 'includes/class-pure-metafields-deactivator.php';
		tpmeta_deactivator::deactivate();
	}
	register_deactivation_hook( __FILE__, 'tpmeta_deactivate_tp_metabox' );
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require TPMETA_PATH . 'includes/class-pure-metafields.php';

/**
 * Centralised enqueue helpers for the shared field-runtime and
 * builder-runtime asset bundles. Required early so any class
 * registering admin_enqueue_scripts hooks can reference it.
 */
require_once TPMETA_PATH . 'includes/class-tpmeta-assets.php';



/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.4.0
 */
if(!function_exists('tpmeta_kick')){
	function tpmeta_kick() {
		$plugin = new tpmeta();
		$plugin->run();
	}
	tpmeta_kick();
}

require_once TPMETA_PATH . 'metaboxes/functions.php';
require_once TPMETA_PATH . 'metaboxes/class-metabox.php';

/**
 * The Pro feature bootstrap is deferred until after the theme has loaded.
 */
function tpmeta_load_pro_features() {
	if ( ! tpmeta_is_pro() ) {
		return;
	}

	require_once TPMETA_PATH . 'options/class-tpmeta-options.php';
	require_once TPMETA_PATH . 'options/class-tpmeta-options-store.php';
	require_once TPMETA_PATH . 'options/class-tpmeta-options-field.php';
	require_once TPMETA_PATH . 'options/class-tpmeta-options-render.php';
	require_once TPMETA_PATH . 'options/class-tpmeta-options-ajax.php';
	require_once TPMETA_PATH . 'options/class-tpmeta-options-output.php';
	require_once TPMETA_PATH . 'includes/class-tpmeta-css-target.php';
	require_once TPMETA_PATH . 'includes/class-tpmeta-metabox-output.php';

	if ( ! function_exists( 'tpmeta_options_boot' ) ) {
		function tpmeta_options_boot() {
			new TPMeta_Options_Render();
			new TPMeta_Options_Ajax();
			new TPMeta_Options_Output();
			new TPMeta_Metabox_CSS_Output();
			new TPMeta_Metabox_Generic_Output();
		}
	}
	tpmeta_options_boot();

	require_once TPMETA_PATH . 'options/builder/class-tpmeta-builder-store.php';
	require_once TPMETA_PATH . 'options/builder/class-tpmeta-builder-codegen.php';
	require_once TPMETA_PATH . 'options/builder/class-tpmeta-builder-loader.php';
	require_once TPMETA_PATH . 'options/builder/class-tpmeta-builder-rest.php';
	require_once TPMETA_PATH . 'options/builder/class-tpmeta-builder-demos.php';
	require_once TPMETA_PATH . 'options/builder/class-tpmeta-builder.php';
	require_once TPMETA_PATH . 'options/class-tpmeta-customizer.php';

	new TPMeta_Builder_Demos();

	if ( ! function_exists( 'tpmeta_builder_boot' ) ) {
		function tpmeta_builder_boot() {
			new TPMeta_Builder();
			new TPMeta_Builder_REST();
			new TPMeta_Builder_Loader();
			new TPMeta_Customizer();
		}
	}
	tpmeta_builder_boot();
}
add_action( 'after_setup_theme', 'tpmeta_load_pro_features', 15 );

/**
 * Pure Fields — extended feature suite.
 * Main menu + Metafield Builder + Post Types + Taxonomies + Export/Import.
 *
 * @since 1.7.0
 */

// Shared admin menu.
require_once TPMETA_PATH . 'admin/class-tpmeta-main-menu.php';

// Metafield Builder.
require_once TPMETA_PATH . 'metafields-builder/class-tpmeta-metafields-store.php';
require_once TPMETA_PATH . 'metafields-builder/class-tpmeta-metafields-loader.php';
require_once TPMETA_PATH . 'metafields-builder/class-tpmeta-metafields-rest.php';
require_once TPMETA_PATH . 'metafields-builder/class-tpmeta-metafields-builder.php';

// Metafield Builder — Pro features (Bake to PHP + Theme Scanner).
if ( tpmeta_is_pro() ) {
	require_once TPMETA_PATH . 'metafields-builder/class-tpmeta-metafields-codegen.php';
	require_once TPMETA_PATH . 'metafields-builder/class-tpmeta-metafields-scanner.php';
	TPMeta_Metafields_Scanner::init();
}

// Post Types.
require_once TPMETA_PATH . 'post-types/class-tpmeta-post-types-store.php';
require_once TPMETA_PATH . 'post-types/class-tpmeta-post-types-loader.php';
require_once TPMETA_PATH . 'post-types/class-tpmeta-post-types-page.php';

// Taxonomies.
require_once TPMETA_PATH . 'taxonomies/class-tpmeta-taxonomies-store.php';
require_once TPMETA_PATH . 'taxonomies/class-tpmeta-taxonomies-loader.php';
require_once TPMETA_PATH . 'taxonomies/class-tpmeta-taxonomies-page.php';

// Export / Import.
require_once TPMETA_PATH . 'export-import/class-tpmeta-export-import.php';

if ( ! function_exists( 'tpmeta_pure_fields_boot' ) ) {
	function tpmeta_pure_fields_boot() {
		new TPMeta_Main_Menu();
		new TPMeta_Metafields_Builder();
		new TPMeta_Metafields_REST();
		new TPMeta_Metafields_Loader();
		new TPMeta_Post_Types_Loader();
		new TPMeta_Post_Types_Page();
		new TPMeta_Taxonomies_Loader();
		new TPMeta_Taxonomies_Page();
		new TPMeta_Export_Import();
	}
	add_action( 'plugins_loaded', 'tpmeta_pure_fields_boot', 30 );
}