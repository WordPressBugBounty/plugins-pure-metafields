<?php
/**
 * TPMeta_Metafields_Store
 * Persists metabox definitions as JSON in wp_options.
 *
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Metafields_Store {

	const OPTION_KEY = 'tpmeta_metafields_panels';

	private static $cache = null;

	public static function all() {
		if ( null === self::$cache ) {
			$raw         = get_option( self::OPTION_KEY, array() );
			self::$cache = is_array( $raw ) ? array_values( $raw ) : array();
		}
		return self::$cache;
	}

	private static function bust() { self::$cache = null; }

	/** @return array|null Single metabox by id. */
	public static function get( $metabox_id ) {
		$all = self::all();
		foreach ( $all as $box ) {
			if ( isset( $box['metabox_id'] ) && $box['metabox_id'] === $metabox_id ) {
				return $box;
			}
		}
		return null;
	}

	/** Insert or update a metabox definition. Returns saved record. */
	public static function save( array $box ) {
		$box = self::sanitize( $box );
		if ( empty( $box['metabox_id'] ) ) {
			return false;
		}

		$all = self::all();
		$found = false;
		foreach ( $all as &$existing ) {
			if ( $existing['metabox_id'] === $box['metabox_id'] ) {
				self::purge_removed_fields( $existing, $box );
				$existing = $box;
				$found    = true;
				break;
			}
		}
		unset( $existing );

		if ( ! $found ) {
			$all[] = $box;
		}

		update_option( self::OPTION_KEY, $all, false );
		self::bust();
		return $box;
	}

	/** Delete a metabox by id. */
	public static function delete( $metabox_id ) {
		$box = self::get( $metabox_id );
		if ( $box ) {
			self::purge_all_fields( $box );
		}
		$all = self::all();
		$all = array_filter( $all, function( $b ) use ( $metabox_id ) {
			return isset( $b['metabox_id'] ) && $b['metabox_id'] !== $metabox_id;
		} );
		update_option( self::OPTION_KEY, array_values( $all ), false );
		self::bust();
		return true;
	}

	/** Sanitize incoming data. */
	private static function sanitize( array $box ) {
		// New optional target — 'post' (legacy default) or 'user'. Existing
		// records have no 'target' key; the Loader treats absent as 'post' so
		// nothing already in storage changes shape until it's re-saved.
		$target = ( isset( $box['target'] ) && 'user' === $box['target'] ) ? 'user' : 'post';

		$out = array(
			'metabox_id' => sanitize_key( $box['metabox_id'] ?? '' ),
			'title'      => sanitize_text_field( $box['title']    ?? '' ),
			'post_type'  => ( 'user' === $target )
								? array()
								: array_map( 'sanitize_key', (array) ( $box['post_type'] ?? array() ) ),
			'context'    => in_array( $box['context'] ?? '', array( 'normal', 'side', 'advanced' ), true )
							 ? $box['context'] : 'normal',
			'priority'   => in_array( $box['priority'] ?? '', array( 'default', 'high', 'low', 'core' ), true )
							 ? $box['priority'] : 'default',
			'columns'    => absint( $box['columns'] ?? 1 ) ?: 1,
			'target'     => $target,
			// New visual-builder format: sections → rows → fields.
			'sections'   => self::sanitize_sections( $box['sections'] ?? array() ),
		);

		if ( 'post' === $target ) {
			// post_format binds the metabox to a specific WP post format
			// (gallery, video, audio, …). Only meaningful for post-target;
			// stored only when explicitly set so existing records remain
			// untouched on next save.
			$pf = isset( $box['post_format'] ) ? sanitize_key( $box['post_format'] ) : '';
			if ( '' !== $pf ) {
				$out['post_format'] = $pf;
			}
		} else {
			// user-target — optional WP capability gate (e.g. 'edit_users',
			// 'manage_options'). Empty value means no gate at render/save time.
			if ( ! empty( $box['capability'] ) ) {
				$out['capability'] = sanitize_key( $box['capability'] );
			}
		}

		return $out;
	}

	/** Delete DB data for fields present in $old but absent in $new. */
	private static function purge_removed_fields( array $old, array $new ) {
		$removed = array_diff( self::collect_field_ids( $old ), self::collect_field_ids( $new ) );
		if ( empty( $removed ) ) return;
		$target = isset( $old['target'] ) ? $old['target'] : 'post';
		foreach ( $removed as $field_id ) {
			if ( 'user' === $target ) {
				delete_metadata( 'user', 0, $field_id, '', true );
			} else {
				delete_post_meta_by_key( $field_id );
			}
		}
	}

	/** Delete DB data for every field in $box (used when deleting the whole metabox). */
	private static function purge_all_fields( array $box ) {
		$target = isset( $box['target'] ) ? $box['target'] : 'post';
		foreach ( self::collect_field_ids( $box ) as $field_id ) {
			if ( 'user' === $target ) {
				delete_metadata( 'user', 0, $field_id, '', true );
			} else {
				delete_post_meta_by_key( $field_id );
			}
		}
	}

	/** Returns a flat list of all field IDs in a metabox definition (sections → rows → fields). */
	private static function collect_field_ids( array $box ) {
		$ids = array();
		foreach ( $box['sections'] ?? array() as $section ) {
			foreach ( $section['rows'] ?? array() as $row ) {
				foreach ( $row['fields'] ?? array() as $field ) {
					if ( ! empty( $field['id'] ) ) {
						$ids[] = $field['id'];
					}
				}
			}
		}
		return $ids;
	}

	private static function sanitize_sections( array $sections ) {
		return array_values( array_map( function( $s ) {
			return array(
				'id'          => sanitize_key( $s['id']          ?? '' ),
				'title'       => sanitize_text_field( $s['title'] ?? '' ),
				'icon'        => sanitize_text_field( $s['icon']  ?? '' ),
				'description' => sanitize_text_field( $s['description'] ?? '' ),
				'rows'        => self::sanitize_rows( $s['rows'] ?? array() ),
			);
		}, $sections ) );
	}

	private static function sanitize_rows( array $rows ) {
		return array_values( array_filter( array_map( function( $r ) {
			if ( ! is_array( $r ) || empty( $r['id'] ) ) return null;
			return array(
				'id'        => sanitize_key( $r['id']     ?? '' ),
				'layout'    => in_array( $r['layout'] ?? '', array( 'grid', 'flex' ), true ) ? $r['layout'] : 'grid',
				'columns'   => absint( $r['columns'] ?? 1 ) ?: 1,
				'direction' => in_array( $r['direction'] ?? '', array( 'row', 'column' ), true ) ? $r['direction'] : 'row',
				'wrap'      => in_array( $r['wrap'] ?? '', array( 'wrap', 'nowrap' ), true ) ? $r['wrap'] : 'wrap',
				'fields'    => self::sanitize_fields( $r['fields'] ?? array() ),
			);
		}, $rows ) ) );
	}

	private static function sanitize_fields( array $fields ) {
		return array_values( array_filter( array_map( function( $f ) {
			if ( empty( $f['id'] ) || empty( $f['type'] ) ) return null;
			$merged = array_merge( $f, array(
				'id'    => sanitize_key( $f['id'] ),
				'type'  => sanitize_key( $f['type'] ),
				'label' => sanitize_text_field( $f['label'] ?? '' ),
			) );
			// Sanitize columns for repeater fields.
			if ( isset( $merged['columns'] ) ) {
				$merged['columns'] = max( 1, min( 3, (int) $merged['columns'] ) );
			}
			return $merged;
		}, $fields ) ) );
	}
}
