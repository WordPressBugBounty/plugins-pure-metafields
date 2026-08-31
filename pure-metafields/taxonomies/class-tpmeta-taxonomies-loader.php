<?php
/**
 * TPMeta_Taxonomies_Loader — registers saved custom taxonomies on init.
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Taxonomies_Loader {

	public function __construct() {
		add_action( 'init', array( $this, 'register_all' ), 5 );
	}

	public function register_all() {
		foreach ( TPMeta_Taxonomies_Store::all() as $tax ) {
			$this->register( $tax );
		}
	}

	private function register( array $tax ) {
		$slug = $tax['slug'] ?? '';
		if ( empty( $slug ) || taxonomy_exists( $slug ) ) return;

		$singular = $tax['singular_name'] ?? $slug;
		$plural   = $tax['plural_name']   ?? $slug . 's';

		$labels = array(
			'name'              => $plural,
			'singular_name'     => $singular,
			'search_items'      => 'Search ' . $plural,
			'all_items'         => 'All '    . $plural,
			'parent_item'       => 'Parent ' . $singular,
			'parent_item_colon' => 'Parent ' . $singular . ':',
			'edit_item'         => 'Edit '   . $singular,
			'update_item'       => 'Update ' . $singular,
			'add_new_item'      => 'Add New ' . $singular,
			'new_item_name'     => 'New ' . $singular . ' Name',
			'menu_name'         => $plural,
		);

		$args = array(
			'labels'            => $labels,
			'description'       => $tax['description']      ?? '',
			'hierarchical'      => $tax['hierarchical']      ?? false,
			'public'            => $tax['public']            ?? true,
			'show_in_rest'      => $tax['show_in_rest']      ?? true,
			'show_admin_column' => $tax['show_admin_column'] ?? true,
			'rewrite'           => array( 'slug' => $tax['rewrite_slug'] ?: $slug ),
		);

		register_taxonomy( $slug, $tax['post_types'] ?? array(), $args );
	}
}
