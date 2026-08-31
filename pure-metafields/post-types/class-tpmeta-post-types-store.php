<?php
/**
 * TPMeta_Post_Types_Store — persists custom post type definitions.
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Post_Types_Store {

	const OPTION_KEY = 'tpmeta_custom_post_types';

	private static $cache = null;

	public static function all() {
		if ( null === self::$cache ) {
			$raw         = get_option( self::OPTION_KEY, array() );
			self::$cache = is_array( $raw ) ? array_values( $raw ) : array();
		}
		return self::$cache;
	}

	private static function bust() { self::$cache = null; }

	public static function get( $slug ) {
		foreach ( self::all() as $pt ) {
			if ( ( $pt['slug'] ?? '' ) === $slug ) return $pt;
		}
		return null;
	}

	public static function save( array $pt ) {
		$pt = self::sanitize( $pt );
		if ( empty( $pt['slug'] ) ) return false;

		$all   = self::all();
		$found = false;
		foreach ( $all as &$item ) {
			if ( ( $item['slug'] ?? '' ) === $pt['slug'] ) { $item = $pt; $found = true; break; }
		}
		unset( $item );
		if ( ! $found ) $all[] = $pt;
		update_option( self::OPTION_KEY, $all, false );
		self::bust();
		return $pt;
	}

	public static function delete( $slug ) {
		$all = array_filter( self::all(), function( $pt ) use ( $slug ) {
			return ( $pt['slug'] ?? '' ) !== $slug;
		} );
		update_option( self::OPTION_KEY, array_values( $all ), false );
		self::bust();
	}

	private static function sanitize( array $pt ) {
		return array(
			'slug'                => sanitize_key( $pt['slug'] ?? '' ),
			'singular_name'       => sanitize_text_field( $pt['singular_name'] ?? '' ),
			'plural_name'         => sanitize_text_field( $pt['plural_name']   ?? '' ),
			'menu_name'           => sanitize_text_field( $pt['menu_name']     ?? '' ),
			'description'         => sanitize_text_field( $pt['description']   ?? '' ),
			'public'              => (bool) ( $pt['public']              ?? true ),
			'show_in_rest'        => (bool) ( $pt['show_in_rest']        ?? true ),
			'hierarchical'        => (bool) ( $pt['hierarchical']        ?? false ),
			'has_archive'         => (bool) ( $pt['has_archive']         ?? false ),
			'show_in_menu'        => (bool) ( $pt['show_in_menu']        ?? true ),
			'menu_icon'           => sanitize_text_field( $pt['menu_icon'] ?? 'dashicons-admin-post' ),
			'menu_position'       => absint( $pt['menu_position'] ?? 25 ),
			'supports'            => array_map( 'sanitize_key', (array) ( $pt['supports'] ?? array( 'title', 'editor', 'thumbnail' ) ) ),
			'taxonomies'          => array_map( 'sanitize_key', (array) ( $pt['taxonomies'] ?? array() ) ),
			'rewrite_slug'        => sanitize_key( $pt['rewrite_slug'] ?? '' ),
			'capability_type'     => sanitize_key( $pt['capability_type'] ?? 'post' ),
			'exclude_from_search' => (bool) ( $pt['exclude_from_search'] ?? false ),
		);
	}
}
