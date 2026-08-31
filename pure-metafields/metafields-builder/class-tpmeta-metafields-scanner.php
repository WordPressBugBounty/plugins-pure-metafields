<?php
/**
 * TPMeta_Metafields_Scanner
 *
 * Discovers metaboxes registered against the `tp_meta_boxes` filter (active
 * theme + plugins) and converts them from filter shape into the builder's
 * JSON shape so they can be imported into the visual builder.
 *
 * Reverse direction of TPMeta_Metafields_Codegen — that goes builder JSON →
 * filter PHP; this goes filter array → builder JSON.
 *
 * Also scans `tp_user_meta`, but only for display: the user-profile filter
 * uses a different storage backend (update_user_meta vs update_post_meta) so
 * we don't import those into the post-metabox builder.
 *
 * @package    tpmeta
 * @subpackage tpmeta/metafields-builder
 * @since      1.7.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Metafields_Scanner {

	/**
	 * Cached scan payload lives in a single wp_option:
	 *   {
	 *     active_theme:        get_stylesheet() at build time,
	 *     generated_at:        unix ts,
	 *     theme_metaboxes_raw: [ ...converted-but-not-subtracted entries... ],
	 *     theme_user_meta:     null | { label, fields_count, fields[] },
	 *   }
	 *
	 * Cache is busted on switch_theme, on manual refresh, and automatically
	 * when the cached active_theme no longer matches get_stylesheet().
	 */
	const CACHE_KEY = 'tpmeta_metafields_scan_cache';

	/**
	 * Register cache-invalidation hooks. Call once at bootstrap.
	 */
	public static function init() {
		add_action( 'switch_theme',          array( __CLASS__, 'bust_cache' ) );
		add_action( 'after_switch_theme',    array( __CLASS__, 'bust_cache' ) );
		add_action( 'activated_plugin',      array( __CLASS__, 'bust_cache' ) );
		add_action( 'deactivated_plugin',    array( __CLASS__, 'bust_cache' ) );
	}

	/**
	 * Return entries from `tp_meta_boxes` that are NOT already managed by the
	 * builder (matched by metabox_id against the store). Each entry is
	 * converted to builder JSON shape ready to be passed to Store::save().
	 *
	 * Reads from cache; builds + persists on first call (or after invalidation).
	 *
	 * @return array
	 */
	public static function scan_theme_metaboxes() {
		$cache = self::get_cached();
		$raw   = isset( $cache['theme_metaboxes_raw'] ) && is_array( $cache['theme_metaboxes_raw'] )
			? $cache['theme_metaboxes_raw']
			: array();

		// Subtraction (vs builder store) happens at request time so the cache
		// stays valid across builder mutations.
		$own_ids = array();
		foreach ( TPMeta_Metafields_Store::all() as $box ) {
			if ( ! empty( $box['metabox_id'] ) ) {
				$own_ids[ $box['metabox_id'] ] = true;
			}
		}

		$result = array();
		foreach ( $raw as $box ) {
			if ( empty( $box['metabox_id'] ) || isset( $own_ids[ $box['metabox_id'] ] ) ) continue;
			$result[] = $box;
		}
		return $result;
	}

	/**
	 * Scan `tp_user_meta` filter. Returns the cached preview or null.
	 *
	 * @return array|null
	 */
	public static function scan_theme_user_meta() {
		$cache = self::get_cached();
		return isset( $cache['theme_user_meta'] ) ? $cache['theme_user_meta'] : null;
	}

	/**
	 * Read the cache, building it if missing, expired, or stale (active theme
	 * changed since last build).
	 *
	 * @return array
	 */
	public static function get_cached() {
		$cache = get_option( self::CACHE_KEY, false );
		if ( ! is_array( $cache ) || empty( $cache['active_theme'] ) || $cache['active_theme'] !== get_stylesheet() ) {
			$cache = self::build_cache();
		}
		return $cache;
	}

	/**
	 * Run the actual filter callbacks, convert results to builder shape, and
	 * persist. Returns the freshly-built cache payload.
	 *
	 * @return array
	 */
	public static function build_cache() {
		$payload = array(
			'active_theme'        => get_stylesheet(),
			'generated_at'        => time(),
			'theme_metaboxes_raw' => self::do_scan_metaboxes(),
			'theme_user_meta'     => self::do_scan_user_meta(),
		);
		// autoload = no — this can be sizeable and isn't read on every page.
		update_option( self::CACHE_KEY, $payload, false );
		return $payload;
	}

	/**
	 * Drop the cache. Next read rebuilds it.
	 */
	public static function bust_cache() {
		delete_option( self::CACHE_KEY );
	}

	/**
	 * Actual scan: runs `tp_meta_boxes` and converts every entry to builder
	 * JSON shape. Subtraction against the builder store happens later, in
	 * scan_theme_metaboxes(), so this output is stable across builder edits.
	 */
	private static function do_scan_metaboxes() {
		$all = apply_filters( 'tp_meta_boxes', array() );
		if ( ! is_array( $all ) ) return array();

		$result = array();
		foreach ( $all as $box ) {
			if ( ! is_array( $box ) || empty( $box['metabox_id'] ) ) continue;
			$result[] = self::metabox_filter_to_builder( $box );
		}
		return $result;
	}

	/**
	 * Actual scan: runs `tp_user_meta` and shapes the result for display.
	 */
	private static function do_scan_user_meta() {
		$um = apply_filters( 'tp_user_meta', array() );
		if ( ! is_array( $um ) || empty( $um['fields'] ) ) return null;

		$fields = array();
		foreach ( (array) $um['fields'] as $f ) {
			$f = (array) $f;
			$fields[] = array(
				'id'    => isset( $f['id'] )    ? $f['id']    : '',
				'type'  => isset( $f['type'] )  ? $f['type']  : 'text',
				'label' => isset( $f['label'] ) ? $f['label'] : '',
			);
		}
		return array(
			'label'        => isset( $um['label'] ) ? $um['label'] : '',
			'fields_count' => count( $fields ),
			'fields'       => $fields,
		);
	}

	/**
	 * Convert a single theme-provided metabox (filter shape) into the builder's
	 * JSON shape (sections → rows → fields). Wraps the flat fields[] in a single
	 * "Imported" section / single full-width row.
	 */
	public static function metabox_filter_to_builder( array $box ) {
		$post_types = isset( $box['post_type'] ) ? (array) $box['post_type'] : array();

		$fields_in = isset( $box['fields'] ) && is_array( $box['fields'] ) ? $box['fields'] : array();
		$rows = array();
		if ( ! empty( $fields_in ) ) {
			$rows[] = array(
				'id'        => 'row_' . self::short_uid(),
				'layout'    => 'grid',
				'columns'   => 1,
				'direction' => 'row',
				'wrap'      => 'wrap',
				'fields'    => array_map( array( __CLASS__, 'field_filter_to_builder' ), $fields_in ),
			);
		}

		$sections = array(
			array(
				'id'          => 'sec_' . self::short_uid(),
				'title'       => 'Imported',
				'icon'        => 'dashicons-download',
				'description' => '',
				'rows'        => $rows,
			),
		);

		$out = array(
			'metabox_id' => isset( $box['metabox_id'] ) ? $box['metabox_id'] : '',
			'title'      => isset( $box['title'] )      ? $box['title']      : '',
			'post_type'  => $post_types,
			'context'    => isset( $box['context'] )    ? $box['context']    : 'normal',
			'priority'   => isset( $box['priority'] )   ? $box['priority']   : 'default',
			'columns'    => max( 1, absint( isset( $box['columns'] ) ? $box['columns'] : 1 ) ),
			'sections'   => $sections,
		);

		// Pass-through any other top-level keys (post_format, etc.).
		// Note: Store::sanitize will drop keys outside its whitelist on save.
		// We forward them anyway so callers (e.g. the scan endpoint) can show
		// accurate previews; persistence happens via Store::save() later.
		$skip = array( 'metabox_id', 'title', 'post_type', 'context', 'priority', 'columns', 'fields', 'sections' );
		foreach ( $box as $k => $v ) {
			if ( in_array( $k, $skip, true ) ) continue;
			$out[ $k ] = $v;
		}

		return $out;
	}

	/**
	 * Convert a single filter-format field to the builder field shape:
	 *   - options assoc {value=>label}     → sequential [{value,label}]
	 *   - choices (tabs/radio_buttonset)   → options    [{value,label}]
	 *   - conditional [id, op, val]        → assoc {field, operator, value}
	 *   - multicolor default array         → JSON string (builder convention)
	 *   - repeater fields[]                → recursive convert
	 */
	public static function field_filter_to_builder( $f ) {
		$f    = (array) $f;
		$type = isset( $f['type'] ) ? $f['type'] : 'text';

		$out = array(
			'id'    => isset( $f['id'] )    ? $f['id']    : '',
			'type'  => $type,
			'label' => isset( $f['label'] ) ? $f['label'] : '',
		);

		if ( array_key_exists( 'default', $f ) ) {
			$out['default'] = self::default_filter_to_builder( $type, $f['default'] );
		}
		if ( ! empty( $f['placeholder'] ) ) $out['placeholder'] = $f['placeholder'];
		if ( ! empty( $f['description'] ) ) $out['description'] = $f['description'];

		// options/choices → sequential [{value,label}].
		$opts_in = null;
		if ( ! empty( $f['options'] ) && is_array( $f['options'] ) ) {
			$opts_in = $f['options'];
		} elseif ( ! empty( $f['choices'] ) && is_array( $f['choices'] ) ) {
			$opts_in = $f['choices'];
		}
		if ( null !== $opts_in ) {
			$out['options'] = self::assoc_to_options( $opts_in );
		}

		// conditional → assoc {field, operator, value}.
		$out['conditional'] = self::cond_to_assoc( isset( $f['conditional'] ) ? $f['conditional'] : array() );

		// Repeater: recurse.
		if ( 'repeater' === $type && ! empty( $f['fields'] ) && is_array( $f['fields'] ) ) {
			$out['fields'] = array_map( array( __CLASS__, 'field_filter_to_builder' ), $f['fields'] );
		}

		// Pass-through (multiple, bind, parent, post_type, data_type, save_format, …).
		$skip = array( 'id', 'type', 'label', 'default', 'placeholder', 'description', 'options', 'choices', 'conditional', 'fields' );
		foreach ( $f as $k => $v ) {
			if ( in_array( $k, $skip, true ) ) continue;
			$out[ $k ] = $v;
		}

		return $out;
	}

	private static function default_filter_to_builder( $type, $default ) {
		// Builder stores multicolor default as a JSON string.
		if ( 'multicolor' === $type && is_array( $default ) ) {
			return wp_json_encode( $default );
		}
		return $default;
	}

	private static function assoc_to_options( array $assoc ) {
		// Already sequential {value,label} list?
		if ( ! empty( $assoc ) && array_keys( $assoc ) === range( 0, count( $assoc ) - 1 ) ) {
			$first = reset( $assoc );
			if ( is_array( $first ) && ( isset( $first['value'] ) || isset( $first['label'] ) ) ) {
				return $assoc;
			}
		}
		$out = array();
		foreach ( $assoc as $k => $v ) {
			$out[] = array(
				'value' => (string) $k,
				'label' => is_scalar( $v ) ? (string) $v : '',
			);
		}
		return $out;
	}

	private static function cond_to_assoc( $cond ) {
		if ( ! is_array( $cond ) || empty( $cond ) ) return array();
		if ( isset( $cond['field'] ) && '' !== $cond['field'] ) {
			return array(
				'field'    => $cond['field'],
				'operator' => isset( $cond['operator'] ) ? $cond['operator'] : '==',
				'value'    => isset( $cond['value'] )    ? $cond['value']    : '',
			);
		}
		if ( isset( $cond[0] ) && '' !== $cond[0] ) {
			return array(
				'field'    => $cond[0],
				'operator' => isset( $cond[1] ) ? $cond[1] : '==',
				'value'    => isset( $cond[2] ) ? $cond[2] : '',
			);
		}
		return array();
	}

	private static function short_uid() {
		// Match the JS uid() shape used by the builder so IDs feel native.
		return substr( str_replace( array( '.', '-' ), '', uniqid( '', true ) ), 0, 12 );
	}
}
