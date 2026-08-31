<?php
/**
 * TPMeta_Builder_REST
 *
 * REST endpoints used by the builder UI.
 *
 *   GET    /tpmeta/v1/builder/panels             — list all panels
 *   GET    /tpmeta/v1/builder/panels/(slug)      — fetch one panel
 *   POST   /tpmeta/v1/builder/panels             — create or update
 *   DELETE /tpmeta/v1/builder/panels/(slug)      — delete
 *   POST   /tpmeta/v1/builder/bake               — generate PHP source
 *
 * All endpoints require `manage_options`.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options/builder
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Builder_REST {

	const NAMESPACE_BASE = 'tpmeta/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE_BASE, '/builder/panels', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_panels' ),
				'permission_callback' => array( $this, 'auth' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_panel' ),
				'permission_callback' => array( $this, 'auth' ),
			),
		) );

		register_rest_route( self::NAMESPACE_BASE, '/builder/panels/(?P<slug>[a-z0-9_\-]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_panel' ),
				'permission_callback' => array( $this, 'auth' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_panel' ),
				'permission_callback' => array( $this, 'auth' ),
			),
		) );

		// POST /builder/panels/(slug)/values — apply imported theme_mod values
		// to a panel that has already been saved structurally. Used by the
		// import flow so values land immediately (decoupled from the user
		// clicking the Save button afterward).
		register_rest_route( self::NAMESPACE_BASE, '/builder/panels/(?P<slug>[a-z0-9_\-]+)/values', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'apply_values' ),
			'permission_callback' => array( $this, 'auth' ),
		) );

		register_rest_route( self::NAMESPACE_BASE, '/builder/bake', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bake_panel' ),
			'permission_callback' => array( $this, 'auth' ),
		) );

		register_rest_route( self::NAMESPACE_BASE, '/builder/directories', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_directories' ),
			'permission_callback' => array( $this, 'auth' ),
		) );

		register_rest_route( self::NAMESPACE_BASE, '/builder/images', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_images' ),
			'permission_callback' => array( $this, 'auth' ),
		) );

		register_rest_route( self::NAMESPACE_BASE, '/builder/demos', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_demos' ),
				'permission_callback' => array( $this, 'auth' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'seed_demo' ),
				'permission_callback' => array( $this, 'auth' ),
			),
		) );
	}

	/**
	 * Capability + nonce check.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function auth( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'tpmeta_forbidden', __( 'You do not have permission to use the options builder.', 'pure-metafields' ), array( 'status' => 403 ) );
		}
		// REST nonce is auto-checked by WP when using wp_rest action; verify presence as defense-in-depth.
		$nonce = $request->get_header( 'x-wp-nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'tpmeta_bad_nonce', __( 'Invalid security token.', 'pure-metafields' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function list_panels( $request ) {
		return rest_ensure_response( array(
			'panels' => TPMeta_Builder_Store::all(),
		) );
	}

	public function get_panel( $request ) {
		$slug  = $request->get_param( 'slug' );
		$panel = TPMeta_Builder_Store::get( $slug );
		if ( ! $panel ) {
			return new WP_Error( 'tpmeta_not_found', 'Panel not found', array( 'status' => 404 ) );
		}
		// Optional: bundle saved theme_mod values + cross-site portability hints.
		// Used by the JSON export flow so a re-import restores actual user data
		// (typography, post_select, switches, repeater rows, etc.).
		if ( $request->get_param( 'include_values' ) ) {
			$bundle = self::collect_panel_values( $panel );
			$panel['_values'] = $bundle['values'];
			$panel['_meta']   = $bundle['meta'];
		}
		return rest_ensure_response( $panel );
	}

	public function save_panel( $request ) {
		$panel = $request->get_json_params();
		if ( ! is_array( $panel ) || empty( $panel['opt_name'] ) ) {
			return new WP_Error( 'tpmeta_bad_request', 'Missing or invalid panel payload', array( 'status' => 400 ) );
		}

		// Extract import-only sidecar keys BEFORE save — they are not part of
		// the panel schema and would be stripped by sanitize_schema(). Applied
		// after the structure save so cross-site post_select lookups can use
		// the freshly-stored field definitions.
		$import_values = ( isset( $panel['_values'] ) && is_array( $panel['_values'] ) ) ? $panel['_values'] : array();
		$import_meta   = ( isset( $panel['_meta'] )   && is_array( $panel['_meta'] ) )   ? $panel['_meta']   : array();
		unset( $panel['_values'], $panel['_meta'] );

			$ok = TPMeta_Builder_Store::save( $panel );
		if ( ! $ok ) {
			// update_option returns false if value is identical — treat as success.
			$existing = TPMeta_Builder_Store::get( $panel['opt_name'] );
			if ( ! $existing ) {
				return new WP_Error( 'tpmeta_save_failed', 'Failed to save panel', array( 'status' => 500 ) );
			}
		}

		$saved_panel = TPMeta_Builder_Store::get( $panel['opt_name'] );

		$values_report = null;
		if ( ! empty( $import_values ) && is_array( $saved_panel ) ) {
			$values_report = self::apply_panel_values( $saved_panel, $import_values, $import_meta );
		}

		$response = array(
			'saved' => true,
			'panel' => $saved_panel,
		);
		if ( null !== $values_report ) {
			$response['values'] = $values_report;
		}
		return rest_ensure_response( $response );
	}

	/**
	 * Flat list of every storage-bearing field inside a panel schema.
	 *
	 * Walks sections → rows → fields. Each top-level field is exactly one
	 * theme_mod (incl. repeater, which stores the whole rows array as JSON
	 * in a single key). Layout-only types (section_heading) are excluded.
	 *
	 * @param array $panel
	 * @return array<int, array> Field defs with at least id + type.
	 */
	public static function walk_panel_fields( array $panel ) {
		$flat   = array();
		$skip   = array( 'section_heading' );
		$sects  = isset( $panel['sections'] ) && is_array( $panel['sections'] ) ? $panel['sections'] : array();
		foreach ( $sects as $section ) {
			$rows = isset( $section['rows'] ) && is_array( $section['rows'] ) ? $section['rows'] : array();
			// Legacy flat-fields fallback (pre row-format saves).
			if ( empty( $rows ) && ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
				$rows = array( array( 'fields' => $section['fields'] ) );
			}
			foreach ( $rows as $row ) {
				$fields = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
				foreach ( $fields as $field ) {
					if ( ! is_array( $field ) ) continue;
					if ( empty( $field['id'] ) || empty( $field['type'] ) ) continue;
					if ( in_array( $field['type'], $skip, true ) ) continue;
					$flat[] = $field;
				}
			}
		}
		return $flat;
	}

	/**
	 * Build the {values, meta} bundle for a panel's saved theme_mod data.
	 *
	 * `values` is a raw `field_id => theme_mod` map (untouched). `meta` adds
	 * cross-site portability hints used on re-import to remap IDs that don't
	 * survive moves between WP installs (currently: post_select ID values
	 * → recorded with slug + post_type so the destination can resolve).
	 *
	 * @param array $panel
	 * @return array{values:array<string,mixed>,meta:array<string,array<string,mixed>>}
	 */
	public static function collect_panel_values( array $panel ) {
		$values = array();
		$meta   = array();

		foreach ( self::walk_panel_fields( $panel ) as $field ) {
			$id   = (string) $field['id'];
			$type = (string) $field['type'];

			// Use a unique sentinel so we can distinguish "no theme_mod set"
			// from "stored value is literally null/false/empty string".
			$sentinel = '__tpmeta_no_value__';
			$value    = get_theme_mod( $id, $sentinel );
			if ( $value === $sentinel ) {
				continue;
			}
			$values[ $id ] = $value;

			// post_select: bundle a slug hint so the importer can remap the
			// post ID to the local site's matching post. Works for any CPT.
			// Slug values are already portable — no hint needed for save_format='slug'.
			if ( 'post_select' === $type ) {
				$save_format = isset( $field['save_format'] ) ? $field['save_format'] : 'id';
				$field_pt    = isset( $field['post_type'] )   ? (string) $field['post_type'] : '';
				if ( 'id' === $save_format && '' !== $value && null !== $value && is_numeric( $value ) ) {
					$p = get_post( (int) $value );
					if ( $p instanceof WP_Post ) {
						$meta[ $id ] = array(
							'type'        => 'post_select',
							'save_format' => 'id',
							'slug'        => $p->post_name,
							'post_type'   => $p->post_type,
							'field_post_type' => $field_pt, // field config fallback
							'title'       => $p->post_title,
						);
					} elseif ( '' !== $field_pt ) {
						// Post was deleted on source site — store field config so
						// the destination can at least try a title-based lookup.
						$meta[ $id ] = array(
							'type'        => 'post_select',
							'save_format' => 'id',
							'slug'        => '',
							'post_type'   => $field_pt,
							'field_post_type' => $field_pt,
							'title'       => '',
						);
					}
				}
			}
		}

		return array( 'values' => $values, 'meta' => $meta );
	}

	/**
	 * Apply imported values to the destination site as theme_mods.
	 *
	 * For each value:
	 *   - If the destination panel has a field with that id, run it through the
	 *     same per-type sanitizer the normal AJAX save uses, then write
	 *     `set_theme_mod`. Sanitizing is essential here because a JSON round-
	 *     trip can subtly shift value shapes (PHP empty array ↔ JS {} ↔ PHP
	 *     empty array; numeric strings vs ints for switches; repeater rows
	 *     decoded into arrays vs stored as JSON string) — without it the
	 *     renderer reads the value but can't interpret it and falls back to
	 *     defaults, which is exactly what shows up as "missing data" on the
	 *     dashboard/Customizer after a fresh import.
	 *   - For post_select fields tagged with a slug hint in `$meta`, resolve
	 *     the slug → local post ID before writing (preserves cross-site refs).
	 *
	 * @param array $panel  Destination panel definition.
	 * @param array $values Field-id → value map.
	 * @param array $meta   Field-id → portability hints.
	 * @return array{applied:array,skipped:array,remapped:array} Report.
	 */
	public static function apply_panel_values( array $panel, array $values, array $meta ) {
		$opt_name = isset( $panel['opt_name'] ) ? (string) $panel['opt_name'] : '(unknown)';

		$by_id = array();
		foreach ( self::walk_panel_fields( $panel ) as $field ) {
			$by_id[ (string) $field['id'] ] = $field;
		}

		// Ensure the just-saved panel is in the runtime TPMeta_Options registry
		// for this request. The Builder Loader normally does this at init:15
		// (which fires BEFORE rest_api_init), so the freshly-imported panel is
		// invisible to anything that reads from the registry.
		if ( class_exists( 'TPMeta_Builder_Loader' ) && method_exists( 'TPMeta_Builder_Loader', 'register_one' ) ) {
			TPMeta_Builder_Loader::register_one( $panel );
		}

		$applied  = array();
		$skipped  = array();
		$remapped = array();

		foreach ( $values as $id => $value ) {
			$id = (string) $id;
			if ( ! isset( $by_id[ $id ] ) ) {
				$skipped[] = $id;
				continue;
			}
			$field = $by_id[ $id ];
			$type  = $field['type'];

			// post_select cross-site ID remap: resolve slug → local post ID.
			// Uses get_posts (not get_page_by_path) so it works correctly for
			// non-hierarchical and hierarchical CPTs of any depth.
			if ( 'post_select' === $type && isset( $meta[ $id ] ) ) {
				$hint        = $meta[ $id ];
				$slug        = isset( $hint['slug'] )      ? (string) $hint['slug']      : '';
				$post_type   = isset( $hint['post_type'] ) ? (string) $hint['post_type'] : '';
				$save_format = isset( $field['save_format'] ) ? $field['save_format'] : 'id';

				// Prefer the field's configured post_type if the hint's is empty.
				if ( '' === $post_type ) {
					$post_type = isset( $hint['field_post_type'] ) ? (string) $hint['field_post_type'] : '';
				}
				if ( '' === $post_type ) {
					$post_type = isset( $field['post_type'] ) ? (string) $field['post_type'] : 'post';
				}

				if ( '' !== $slug ) {
					$candidates = get_posts( array(
						'name'           => $slug,
						'post_type'      => $post_type,
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'no_found_rows'  => true,
					) );
					$candidate = ! empty( $candidates ) ? $candidates[0] : null;
					if ( $candidate instanceof WP_Post ) {
						$old_value = $value;
						$value     = ( 'slug' === $save_format ) ? $candidate->post_name : (string) $candidate->ID;
						$remapped[ $id ] = array( 'from' => $old_value, 'to' => $value, 'slug' => $slug );
					}
				}
			}

			// Canonical sanitize — same path the normal save AJAX takes.
			$clean = class_exists( 'TPMeta_Options_Store' )
				? TPMeta_Options_Store::sanitize( $field, $value )
				: $value;

			set_theme_mod( $id, $clean );
			$applied[] = $id;
		}

		return array( 'applied' => $applied, 'skipped' => $skipped, 'remapped' => $remapped );
	}

	public function delete_panel( $request ) {
		$slug = $request->get_param( 'slug' );
		TPMeta_Builder_Store::delete( $slug );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Apply imported `_values` / `_meta` to an already-saved panel.
	 *
	 * @param WP_REST_Request $request Body: { values: {field_id: value, …}, meta: {field_id: hint, …} }
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_values( $request ) {
		$slug  = $request->get_param( 'slug' );
		$panel = TPMeta_Builder_Store::get( $slug );
		if ( ! $panel ) {
			return new WP_Error( 'tpmeta_not_found', 'Panel not found', array( 'status' => 404 ) );
		}

		$body   = $request->get_json_params();
		$values = ( isset( $body['values'] ) && is_array( $body['values'] ) ) ? $body['values'] : array();
		$meta   = ( isset( $body['meta'] )   && is_array( $body['meta'] ) )   ? $body['meta']   : array();

		if ( empty( $values ) ) {
			return rest_ensure_response( array(
				'applied'  => array(),
				'skipped'  => array(),
				'remapped' => array(),
				'note'     => 'No values supplied — nothing to apply.',
			) );
		}

		$report = self::apply_panel_values( $panel, $values, $meta );
		return rest_ensure_response( $report );
	}

	/**
	 * List available starter demo panels from the demos/ directory.
	 *
	 * @return WP_REST_Response
	 */
	public function list_demos( $request ) {
		return rest_ensure_response( TPMeta_Builder_Demos::available() );
	}

	/**
	 * Seed (load) a specific demo panel into the builder store.
	 *
	 * @param WP_REST_Request $request  Must contain { file: 'filename.json' }.
	 * @return WP_REST_Response|WP_Error
	 */
	public function seed_demo( $request ) {
		$body     = $request->get_json_params();
		$filename = isset( $body['file'] ) ? basename( (string) $body['file'] ) : '';
		$as_new   = ! empty( $body['as_new'] );

		if ( empty( $filename ) || ! preg_match( '/\.json$/i', $filename ) ) {
			return new WP_Error( 'tpmeta_bad_request', 'Invalid demo filename.', array( 'status' => 400 ) );
		}

		$demo_path = TPMeta_Builder_Demos::DEMO_DIR . $filename;
		if ( ! file_exists( $demo_path ) ) {
			return new WP_Error( 'tpmeta_not_found', 'Demo file not found.', array( 'status' => 404 ) );
		}

		$raw = json_decode( file_get_contents( $demo_path ), true );
		if ( ! is_array( $raw ) || empty( $raw['opt_name'] ) ) {
			return new WP_Error( 'tpmeta_seed_failed', 'Invalid demo JSON.', array( 'status' => 500 ) );
		}

		// as_new: always create a fresh copy so no existing panel is overwritten.
		if ( $as_new ) {
			$raw['opt_name']  = $this->unique_opt_name( sanitize_key( $raw['opt_name'] ) );
			$raw['menu_slug'] = str_replace( '_', '-', $raw['opt_name'] );
		}

		TPMeta_Builder_Store::save( $raw );
		$opt_name = $raw['opt_name'];

		return rest_ensure_response( array(
			'seeded'   => true,
			'opt_name' => $opt_name,
			'panel'    => TPMeta_Builder_Store::get( $opt_name ),
		) );
	}

	/**
	 * Generate a unique opt_name that does not already exist in the store.
	 *
	 * @param string $base
	 * @return string
	 */
	private function unique_opt_name( $base ) {
		$existing = array_column( TPMeta_Builder_Store::all(), 'opt_name' );
		if ( ! in_array( $base, $existing, true ) ) {
			return $base;
		}
		$i = 2;
		while ( in_array( $base . '_' . $i, $existing, true ) ) {
			$i++;
		}
		return $base . '_' . $i;
	}

	/**
	 * Return writable directories under wp-content (themes + plugins) for the
	 * bake modal directory picker.
	 *
	 * @return WP_REST_Response
	 */
	public function list_directories( $request ) {
		$dirs        = array();
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );

		// --- Themes ---
		$themes_root = wp_normalize_path( get_theme_root() );
		if ( is_dir( $themes_root ) ) {
			foreach ( (array) glob( $themes_root . '/*', GLOB_ONLYDIR ) as $theme_path ) {
				$theme_path = wp_normalize_path( $theme_path );
				$name       = basename( $theme_path );
				$rel        = ltrim( str_replace( $content_dir, '', $theme_path ), '/' );

				$dirs[] = array(
					'value' => $rel,
					'label' => 'Theme: ' . $name,
					'group' => 'themes',
				);

				foreach ( array( 'inc', 'includes', 'options', 'framework', 'lib', 'functions' ) as $sub ) {
					if ( is_dir( $theme_path . '/' . $sub ) ) {
						$dirs[] = array(
							'value' => $rel . '/' . $sub,
							'label' => $name . ' / ' . $sub,
							'group' => 'themes',
						);
					}
				}
			}
		}

		// --- Plugins ---
		$plugins_root = wp_normalize_path( WP_PLUGIN_DIR );
		if ( is_dir( $plugins_root ) ) {
			foreach ( (array) glob( $plugins_root . '/*', GLOB_ONLYDIR ) as $plugin_path ) {
				$plugin_path = wp_normalize_path( $plugin_path );
				$name        = basename( $plugin_path );
				$rel         = ltrim( str_replace( $content_dir, '', $plugin_path ), '/' );

				$dirs[] = array(
					'value' => $rel,
					'label' => 'Plugin: ' . $name,
					'group' => 'plugins',
				);
			}
		}

		return rest_ensure_response( $dirs );
	}

	/**
	 * List image files inside a given wp-content-relative directory.
	 * Used by the radio_image field builder to browse theme/plugin images.
	 *
	 * Query param: dir — relative path inside wp-content, e.g. "themes/finzo/inc/img/headers"
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_images( $request ) {
		$rel_dir     = $request->get_param( 'dir' );
		$rel_dir     = $rel_dir ? sanitize_text_field( (string) $rel_dir ) : '';
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		$content_url = content_url();

		if ( false !== strpos( $rel_dir, '..' ) ) {
			return new WP_Error( 'tpmeta_bad_path', 'Invalid path.', array( 'status' => 400 ) );
		}

		$abs_dir = wp_normalize_path( $content_dir . '/' . ltrim( $rel_dir, '/' ) );

		// Must still be under wp-content.
		if ( 0 !== strpos( $abs_dir, $content_dir ) || ! is_dir( $abs_dir ) ) {
			return rest_ensure_response( array( 'images' => array() ) );
		}

		$exts    = array( 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif' );
		$pattern = $abs_dir . '/*.{' . implode( ',', $exts ) . '}';
		$images  = array();

		foreach ( (array) glob( $pattern, GLOB_BRACE ) as $file ) {
			$file     = wp_normalize_path( $file );
			$rel_file = ltrim( str_replace( $content_dir, '', $file ), '/' );
			$images[] = array(
				'filename' => basename( $file ),
				'url'      => $content_url . '/' . $rel_file,
				// Portable form for the image-default picker. Resolves to
				// the same URL on any installation via tpmeta_resolve_image_url().
				'token'    => function_exists( 'tpmeta_tokenize_content_path' )
					? tpmeta_tokenize_content_path( $rel_file )
					: $content_url . '/' . $rel_file,
			);
		}

		usort( $images, function ( $a, $b ) {
			return strcmp( $a['filename'], $b['filename'] );
		} );

		return rest_ensure_response( array( 'images' => $images ) );
	}

	/**
	 * Generate PHP source for a panel. If `write_path` is provided the
	 * source is also written to disk (must be under wp-content/themes
	 * or wp-content/plugins for safety).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function bake_panel( $request ) {
		$body       = $request->get_json_params();
		$opt_name   = isset( $body['opt_name'] )   ? sanitize_key( $body['opt_name'] ) : '';
		$write_path = isset( $body['write_path'] ) ? (string) $body['write_path']      : '';

		$panel = TPMeta_Builder_Store::get( $opt_name );
		if ( ! $panel ) {
			return new WP_Error( 'tpmeta_not_found', 'Panel not found', array( 'status' => 404 ) );
		}

		$source = TPMeta_Builder_Codegen::from_panel( $panel );

		$write_result = null;
		if ( '' !== $write_path ) {
			$write_result = $this->write_to_disk( $source, $write_path );
			if ( is_wp_error( $write_result ) ) {
				return $write_result;
			}
		}

		return rest_ensure_response( array(
			'source'        => $source,
			'written_to'    => $write_result,
			'suggested_filename' => $opt_name . '-options.php',
		) );
	}

	/**
	 * Write source to disk after validating the path.
	 *
	 * @param string $source
	 * @param string $path Relative or absolute path inside wp-content.
	 * @return string|WP_Error Absolute path written to, or error.
	 */
	protected function write_to_disk( $source, $path ) {
		// Resolve to absolute path.
		if ( ! path_is_absolute( $path ) ) {
			$path = WP_CONTENT_DIR . '/' . ltrim( $path, '/\\' );
		}
		$path = wp_normalize_path( $path );

		// Reject unless inside wp-content (themes or plugins).
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		if ( 0 !== strpos( $path, $content_dir ) ) {
			return new WP_Error( 'tpmeta_bad_path', 'Write path must be inside wp-content.', array( 'status' => 400 ) );
		}

		// Reject directory traversal post-normalization.
		if ( false !== strpos( $path, '..' ) ) {
			return new WP_Error( 'tpmeta_bad_path', 'Path traversal not allowed.', array( 'status' => 400 ) );
		}

		// Must end in .php.
		if ( '.php' !== strtolower( substr( $path, -4 ) ) ) {
			return new WP_Error( 'tpmeta_bad_path', 'Filename must end in .php', array( 'status' => 400 ) );
		}

		$dir = dirname( $path );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'tpmeta_mkdir_failed', 'Could not create directory.', array( 'status' => 500 ) );
		}

		$bytes = file_put_contents( $path, $source );
		if ( false === $bytes ) {
			return new WP_Error( 'tpmeta_write_failed', 'Failed to write file.', array( 'status' => 500 ) );
		}

		return $path;
	}
}
