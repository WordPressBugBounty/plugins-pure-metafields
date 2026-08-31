<?php
/**
 * TPMeta_Post_Types_Loader — registers saved custom post types on init.
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Post_Types_Loader {

	public function __construct() {
		add_action( 'init', array( $this, 'register_all' ), 5 );
	}

	public function register_all() {
		foreach ( TPMeta_Post_Types_Store::all() as $pt ) {
			$this->register( $pt );
		}
	}

	private function register( array $pt ) {
		$slug = $pt['slug'] ?? '';
		if ( empty( $slug ) || post_type_exists( $slug ) ) return;

		$singular = $pt['singular_name'] ?? $slug;
		$plural   = $pt['plural_name']   ?? $slug . 's';

		$labels = array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $pt['menu_name'] ?: $plural,
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New ' . $singular,
			'edit_item'          => 'Edit '    . $singular,
			'new_item'           => 'New '     . $singular,
			'view_item'          => 'View '    . $singular,
			'search_items'       => 'Search '  . $plural,
			'not_found'          => 'No '      . $plural . ' found.',
			'not_found_in_trash' => 'No '      . $plural . ' in Trash.',
			'all_items'          => 'All '     . $plural,
		);

		$args = array(
			'labels'              => $labels,
			'description'         => $pt['description']         ?? '',
			'public'              => $pt['public']              ?? true,
			'show_in_menu'        => $pt['show_in_menu']        ?? true,
			'show_in_rest'        => $pt['show_in_rest']        ?? true,
			'hierarchical'        => $pt['hierarchical']        ?? false,
			'has_archive'         => $pt['has_archive']         ?? false,
			'menu_icon'           => $pt['menu_icon']           ?? 'dashicons-admin-post',
			'menu_position'       => $pt['menu_position']       ?? 25,
			'supports'            => $pt['supports']            ?? array( 'title', 'editor', 'thumbnail' ),
			'taxonomies'          => $pt['taxonomies']          ?? array(),
			'capability_type'     => $pt['capability_type']     ?? 'post',
			'exclude_from_search' => $pt['exclude_from_search'] ?? false,
			'rewrite'             => array( 'slug' => $pt['rewrite_slug'] ?: $slug ),
		);

		register_post_type( $slug, $args );
	}
}
