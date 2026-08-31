<?php
/**
 * TPMeta_Builder_Demos
 *
 * Seeds the builder with a ready-made starter panel on first use.
 * The JSON file at demos/theme-starter-panel.json contains a complete
 * set of common theme options (header, logo, breadcrumb, blog, colors …).
 *
 * The seed runs once — if ANY panels already exist in the store the
 * seeder does nothing, so existing developer setups are never touched.
 *
 * To re-seed (e.g. during development): delete the tpmeta_builder_panels
 * option from the wp_options table, or call TPMeta_Builder_Demos::seed()
 * manually from wp-cli / a temporary action.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options/builder
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Builder_Demos {

	const SEEDED_FLAG = 'tpmeta_builder_demos_seeded';
	const DEMO_DIR    = __DIR__ . '/demos/';

	public function __construct() {
		// Auto-seeding removed — fresh installs start blank and use the
		// onboarding welcome screen. Demos are available via the builder UI.
	}

	/**
	 * Seed the starter panel if the store is still empty and the seed
	 * has not been run before.
	 */
	public function maybe_seed() {
		if ( get_option( self::SEEDED_FLAG ) ) {
			return;
		}

		// Don't overwrite panels a developer already created.
		if ( ! empty( TPMeta_Builder_Store::all() ) ) {
			update_option( self::SEEDED_FLAG, true, false );
			return;
		}

		$this->seed( 'theme-starter-panel.json' );

		update_option( self::SEEDED_FLAG, true, false );
	}

	/**
	 * Load a demo JSON file and push it into the builder store.
	 *
	 * @param string $filename Filename inside the demos/ directory.
	 * @return bool True on success.
	 */
	public function seed( $filename ) {
		$path = self::DEMO_DIR . $filename;

		if ( ! file_exists( $path ) ) {
			return false;
		}

		$json = file_get_contents( $path );
		if ( ! $json ) {
			return false;
		}

		$panel = json_decode( $json, true );
		if ( ! is_array( $panel ) || empty( $panel['opt_name'] ) ) {
			return false;
		}

		return (bool) TPMeta_Builder_Store::save( $panel );
	}

	/**
	 * Return a list of all available demo files with their panel metadata.
	 * Used by the REST endpoint so the builder UI can show a "Load Demo" picker.
	 *
	 * @return array
	 */
	public static function available() {
		$demos = array();
		$files = glob( self::DEMO_DIR . '*.json' ) ?: array();

		foreach ( $files as $file ) {
			$json  = file_get_contents( $file );
			$panel = $json ? json_decode( $json, true ) : null;
			if ( ! is_array( $panel ) || empty( $panel['opt_name'] ) ) {
				continue;
			}
			$demos[] = array(
				'file'        => basename( $file ),
				'opt_name'    => $panel['opt_name'],
				'menu_title'  => isset( $panel['menu_title'] )       ? $panel['menu_title']       : $panel['opt_name'],
				'description' => isset( $panel['panel_description'] ) ? $panel['panel_description'] : '',
				'sections'    => count( isset( $panel['sections'] ) ? $panel['sections'] : array() ),
			);
		}

		return $demos;
	}
}
