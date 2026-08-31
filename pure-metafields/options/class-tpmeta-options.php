<?php
/**
 * TPMeta_Options
 *
 * Static facade API for registering ThemePure options panels.
 * Inspired by Redux's developer ergonomics, but stores values in
 * WordPress theme_mods so the standard get_theme_mod() API works.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Options {

	/**
	 * Registered panel arguments, keyed by opt_name.
	 *
	 * @var array
	 */
	protected static $panels = array();

	/**
	 * Registered sections, keyed by opt_name then by section id.
	 *
	 * @var array
	 */
	protected static $sections = array();

	/**
	 * Field id => default value, keyed by opt_name.
	 *
	 * @var array
	 */
	protected static $defaults = array();

	/**
	 * Field id => field definition, keyed by opt_name.
	 *
	 * @var array
	 */
	protected static $field_index = array();

	/**
	 * Register a panel.
	 *
	 * Required args:
	 *   - menu_title (string)
	 *
	 * Optional args:
	 *   - page_title (string)         Defaults to menu_title.
	 *   - menu_slug  (string)         Defaults to opt_name.
	 *   - menu_icon  (string)         Dashicon or URL. Default 'dashicons-admin-generic'.
	 *   - menu_position (int|null)    Default null (after Comments).
	 *   - capability (string)         Default 'manage_options'.
	 *   - columns    (int)            1-12 column grid for fields. Default 1.
	 *   - output_css (bool)           Auto-print CSS from fields with 'output' key. Default true.
	 *   - customizer_integration (bool)  Mirror this panel into the WP Customizer
	 *                                    under the same opt_name. Default false.
	 *
	 * @param string $opt_name Panel identifier (used for hooks & internal grouping).
	 * @param array  $args     Panel arguments.
	 */
	public static function set_args( $opt_name, $args = array() ) {
		$opt_name = sanitize_key( $opt_name );
		if ( '' === $opt_name ) {
			return;
		}

		$defaults = array(
			'page_title'             => '',
			'menu_title'             => '',
			'menu_slug'              => $opt_name,
			'menu_icon'              => 'dashicons-admin-generic',
			'menu_position'          => null,
			'capability'             => 'manage_options',
			'columns'                => 1,
			'output_css'             => true,
			'customizer_integration' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( '' === $args['page_title'] ) {
			$args['page_title'] = $args['menu_title'];
		}

		$args['opt_name']  = $opt_name;
		$args['menu_slug'] = sanitize_key( $args['menu_slug'] );

		self::$panels[ $opt_name ] = $args;
	}

	/**
	 * Register a section under a panel.
	 *
	 * Section keys:
	 *   - id     (string, required)
	 *   - title  (string, required)
	 *   - icon   (string, optional)  Dashicon class.
	 *   - fields (array)             Array of field definitions.
	 *
	 * @param string $opt_name Panel identifier (must match a prior set_args call).
	 * @param array  $section  Section definition.
	 */
	public static function set_section( $opt_name, $section = array() ) {
		$opt_name = sanitize_key( $opt_name );
		if ( '' === $opt_name || empty( $section['id'] ) ) {
			return;
		}

		$section_id = sanitize_key( $section['id'] );
		$section    = wp_parse_args( $section, array(
			'title'  => '',
			'icon'   => '',
			'fields' => array(),
		) );
		$section['id'] = $section_id;

		self::$sections[ $opt_name ][ $section_id ] = $section;

		// Index fields & defaults for quick lookup.
		foreach ( (array) $section['fields'] as $field ) {
			if ( empty( $field['id'] ) || empty( $field['type'] ) ) {
				continue;
			}
			$field_id = $field['id'];
			self::$field_index[ $opt_name ][ $field_id ] = $field;

			if ( isset( $field['default'] ) ) {
				self::$defaults[ $opt_name ][ $field_id ] = $field['default'];
			}
		}
	}

	/**
	 * Get all registered panels.
	 *
	 * @return array
	 */
	public static function get_panels() {
		return self::$panels;
	}

	/**
	 * Get a single panel's args.
	 *
	 * @param string $opt_name
	 * @return array|null
	 */
	public static function get_panel( $opt_name ) {
		return isset( self::$panels[ $opt_name ] ) ? self::$panels[ $opt_name ] : null;
	}

	/**
	 * Get all sections registered for a panel.
	 *
	 * @param string $opt_name
	 * @return array
	 */
	public static function get_sections( $opt_name ) {
		return isset( self::$sections[ $opt_name ] ) ? self::$sections[ $opt_name ] : array();
	}

	/**
	 * Get the full field index for a panel.
	 *
	 * @param string $opt_name
	 * @return array
	 */
	public static function get_fields( $opt_name ) {
		return isset( self::$field_index[ $opt_name ] ) ? self::$field_index[ $opt_name ] : array();
	}

	/**
	 * Get a single field definition.
	 *
	 * @param string $opt_name
	 * @param string $field_id
	 * @return array|null
	 */
	public static function get_field( $opt_name, $field_id ) {
		return isset( self::$field_index[ $opt_name ][ $field_id ] )
			? self::$field_index[ $opt_name ][ $field_id ]
			: null;
	}

	/**
	 * Get the default value for a field id (across all panels).
	 *
	 * @param string $field_id
	 * @return mixed|null
	 */
	public static function get_default( $field_id ) {
		foreach ( self::$defaults as $panel_defaults ) {
			if ( array_key_exists( $field_id, $panel_defaults ) ) {
				return $panel_defaults[ $field_id ];
			}
		}
		return null;
	}
}
