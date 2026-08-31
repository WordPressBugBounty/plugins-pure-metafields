<?php
/**
 * TPMeta_Taxonomies_Store — persists custom taxonomy definitions.
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Taxonomies_Store {

	const OPTION_KEY = 'tpmeta_custom_taxonomies';

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
		foreach ( self::all() as $tax ) {
			if ( ( $tax['slug'] ?? '' ) === $slug ) return $tax;
		}
		return null;
	}

	public static function save( array $tax ) {
		$tax = self::sanitize( $tax );
		if ( empty( $tax['slug'] ) ) return false;

		$all   = self::all();
		$found = false;
		foreach ( $all as &$item ) {
			if ( ( $item['slug'] ?? '' ) === $tax['slug'] ) { $item = $tax; $found = true; break; }
		}
		unset( $item );
		if ( ! $found ) $all[] = $tax;
		update_option( self::OPTION_KEY, $all, false );
		self::bust();
		return $tax;
	}

	public static function delete( $slug ) {
		$all = array_filter( self::all(), function( $t ) use ( $slug ) {
			return ( $t['slug'] ?? '' ) !== $slug;
		} );
		update_option( self::OPTION_KEY, array_values( $all ), false );
		self::bust();
	}

	private static function sanitize( array $tax ) {
		return array(
			'slug'          => sanitize_key( $tax['slug'] ?? '' ),
			'singular_name' => sanitize_text_field( $tax['singular_name'] ?? '' ),
			'plural_name'   => sanitize_text_field( $tax['plural_name']   ?? '' ),
			'description'   => sanitize_text_field( $tax['description']   ?? '' ),
			'hierarchical'  => (bool) ( $tax['hierarchical']  ?? false ),
			'public'        => (bool) ( $tax['public']        ?? true  ),
			'show_in_rest'  => (bool) ( $tax['show_in_rest']  ?? true  ),
			'show_admin_column' => (bool) ( $tax['show_admin_column'] ?? true ),
			'rewrite_slug'  => sanitize_key( $tax['rewrite_slug'] ?? '' ),
			'post_types'    => array_map( 'sanitize_key', (array) ( $tax['post_types'] ?? array() ) ),
		);
	}
}
