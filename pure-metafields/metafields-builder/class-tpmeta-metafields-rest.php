<?php
/**
 * TPMeta_Metafields_REST
 * REST API endpoints for the Metafield Builder.
 *
 * Routes (all under purefields/v1/metafields/):
 *   GET    /              — list all metaboxes
 *   POST   /              — create / update a metabox
 *   DELETE /{id}          — delete a metabox
 *   POST   /bake          — generate (and optionally write) PHP source
 *   GET    /directories   — list writable target dirs for the bake modal
 *   GET    /scan          — discover theme-provided metaboxes + user_meta (cached)
 *   POST   /scan/refresh  — bust the scan cache and return fresh results
 *   POST   /scan/import   — import a scanned metabox into the builder store
 *
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Metafields_REST {

	const NS = 'purefields/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( self::NS, '/metafields', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_all' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_one' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );

		register_rest_route( self::NS, '/metafields/(?P<id>[a-z0-9_\-]+)', array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_one' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );

		// ── Bake to PHP + Theme Scanner — PRO features ─────────────────
		if ( ! tpmeta_is_pro() ) {
			return;
		}

		register_rest_route( self::NS, '/metafields/bake', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bake' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		register_rest_route( self::NS, '/metafields/directories', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_directories' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		register_rest_route( self::NS, '/metafields/scan', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'scan' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		register_rest_route( self::NS, '/metafields/scan/import', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'scan_import' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		register_rest_route( self::NS, '/metafields/scan/refresh', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'scan_refresh' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
	}

	public function get_all( $request ) {
		return rest_ensure_response( TPMeta_Metafields_Store::all() );
	}

	public function save_one( $request ) {
		$data = $request->get_json_params();
		if ( empty( $data['metabox_id'] ) ) {
			return new WP_Error( 'missing_id', 'metabox_id is required', array( 'status' => 400 ) );
		}
		$saved = TPMeta_Metafields_Store::save( $data );
		if ( ! $saved ) {
			return new WP_Error( 'save_failed', 'Could not save metabox', array( 'status' => 500 ) );
		}
		return rest_ensure_response( $saved );
	}

	public function delete_one( $request ) {
		$id = $request->get_param( 'id' );
		TPMeta_Metafields_Store::delete( $id );
		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Generate PHP source for one or more builder metaboxes. Optionally writes
	 * the source to disk (path validated to be inside wp-content, ending in
	 * .php, no traversal). Optionally deletes the baked builder entries
	 * afterwards (only when a successful disk write occurred).
	 *
	 * Body: {
	 *   metabox_ids:  string[],          // builder IDs to bake
	 *   write_path?:  string,            // optional, wp-content-relative or absolute
	 *   callback_name?: string,          // PHP function name; default tpmeta_baked_metaboxes
	 *   delete_after?: bool              // remove builder copies after a successful write
	 * }
	 */
	public function bake( $request ) {
		$body          = $request->get_json_params();
		$ids           = isset( $body['metabox_ids'] )    ? array_values( array_filter( array_map( 'sanitize_key', (array) $body['metabox_ids'] ) ) ) : array();
		$write_path    = isset( $body['write_path'] )     ? (string) $body['write_path'] : '';
		$callback_name = isset( $body['callback_name'] )  ? (string) $body['callback_name'] : 'tpmeta_baked_metaboxes';
		$delete_after  = ! empty( $body['delete_after'] );

		if ( empty( $ids ) ) {
			return new WP_Error( 'tpmeta_no_ids', 'No metabox IDs provided.', array( 'status' => 400 ) );
		}

		$all   = TPMeta_Metafields_Store::all();
		$boxes = array();
		$index = array();
		foreach ( $all as $b ) {
			if ( ! empty( $b['metabox_id'] ) ) {
				$index[ $b['metabox_id'] ] = $b;
			}
		}
		// Preserve user-selected order in the output file.
		foreach ( $ids as $id ) {
			if ( isset( $index[ $id ] ) ) {
				$boxes[] = $index[ $id ];
			}
		}
		if ( empty( $boxes ) ) {
			return new WP_Error( 'tpmeta_no_match', 'No matching metaboxes found.', array( 'status' => 404 ) );
		}

		if ( ! class_exists( 'TPMeta_Metafields_Codegen' ) ) {
			return new WP_Error( 'tpmeta_codegen_missing', 'Codegen class not loaded.', array( 'status' => 500 ) );
		}

		// Split selection by target so post-target records emit a
		// tp_meta_boxes filter and user-target records emit a tp_user_meta
		// filter. Mixed selections produce a single combined file with
		// one <?php opener and both add_filter() callbacks.
		$post_boxes = array();
		$user_boxes = array();
		foreach ( $boxes as $b ) {
			if ( 'user' === ( $b['target'] ?? 'post' ) ) {
				$user_boxes[] = $b;
			} else {
				$post_boxes[] = $b;
			}
		}

		if ( ! empty( $post_boxes ) && ! empty( $user_boxes ) ) {
			$source             = TPMeta_Metafields_Codegen::compose_combined(
				$post_boxes,
				$user_boxes,
				$callback_name,
				$callback_name . '_user'
			);
			$suggested_filename = 'pure-metafields.php';
		} elseif ( ! empty( $user_boxes ) ) {
			$source             = TPMeta_Metafields_Codegen::from_user_meta( $user_boxes, $callback_name );
			$suggested_filename = 'pure-user-meta.php';
		} else {
			$source             = TPMeta_Metafields_Codegen::from_metaboxes( $post_boxes, $callback_name );
			$suggested_filename = 'pure-metaboxes.php';
		}

		$written = null;
		if ( '' !== $write_path ) {
			$written = $this->write_to_disk( $source, $write_path );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
		}

		$deleted = array();
		if ( $delete_after && $written ) {
			foreach ( $boxes as $b ) {
				if ( ! empty( $b['metabox_id'] ) ) {
					TPMeta_Metafields_Store::delete( $b['metabox_id'] );
					$deleted[] = $b['metabox_id'];
				}
			}
		}

		return rest_ensure_response( array(
			'source'             => $source,
			'written_to'         => $written,
			'suggested_filename' => $suggested_filename,
			'baked_count'        => count( $boxes ),
			'baked_post_count'   => count( $post_boxes ),
			'baked_user_count'   => count( $user_boxes ),
			'deleted'            => $deleted,
		) );
	}

	/**
	 * List writable directories under wp-content (themes + plugins) for the
	 * bake modal directory picker. Mirrors the safety rules used by the
	 * Options Builder's directory list — kept inline so this module stays
	 * self-contained and deletable without touching the options builder.
	 */
	public function list_directories( $request ) {
		$dirs        = array();
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );

		// Themes (root + common subfolders).
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

		// Plugins.
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
	 * Write the generated source to disk after path validation.
	 * Rules mirror TPMeta_Builder_REST::write_to_disk():
	 *   - resolved path must live under wp-content
	 *   - no `..` traversal post-normalisation
	 *   - filename must end in .php
	 *
	 * @param string $source
	 * @param string $path Relative (wp-content) or absolute path.
	 * @return string|WP_Error Absolute path written to, or error.
	 */
	protected function write_to_disk( $source, $path ) {
		if ( ! path_is_absolute( $path ) ) {
			$path = WP_CONTENT_DIR . '/' . ltrim( $path, '/\\' );
		}
		$path = wp_normalize_path( $path );

		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		if ( 0 !== strpos( $path, $content_dir ) ) {
			return new WP_Error( 'tpmeta_bad_path', 'Write path must be inside wp-content.', array( 'status' => 400 ) );
		}
		if ( false !== strpos( $path, '..' ) ) {
			return new WP_Error( 'tpmeta_bad_path', 'Path traversal not allowed.', array( 'status' => 400 ) );
		}
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

	/**
	 * Scan the active theme + plugins for `tp_meta_boxes` and `tp_user_meta`
	 * registrations not already managed by this builder. Returns previews in
	 * builder JSON shape, ready to be persisted via /scan/import.
	 */
	public function scan( $request ) {
		if ( ! class_exists( 'TPMeta_Metafields_Scanner' ) ) {
			return new WP_Error( 'tpmeta_scanner_missing', 'Scanner class not loaded.', array( 'status' => 500 ) );
		}
		return rest_ensure_response( array(
			'theme_metaboxes' => TPMeta_Metafields_Scanner::scan_theme_metaboxes(),
			'theme_user_meta' => TPMeta_Metafields_Scanner::scan_theme_user_meta(),
		) );
	}

	/**
	 * Import a scanned theme metabox into the builder store. Body: { metabox_id }.
	 * Re-runs the scan to find the matching entry (so the conversion is fresh
	 * and we don't trust client-supplied JSON for anything but the id).
	 *
	 * Returns:
	 *   { imported: <saved record>, theme_metaboxes: <updated remaining list> }
	 */
	public function scan_import( $request ) {
		if ( ! class_exists( 'TPMeta_Metafields_Scanner' ) ) {
			return new WP_Error( 'tpmeta_scanner_missing', 'Scanner class not loaded.', array( 'status' => 500 ) );
		}

		$body = $request->get_json_params();
		$id   = isset( $body['metabox_id'] ) ? sanitize_key( $body['metabox_id'] ) : '';
		if ( '' === $id ) {
			return new WP_Error( 'tpmeta_no_id', 'metabox_id is required.', array( 'status' => 400 ) );
		}

		$found = null;
		foreach ( TPMeta_Metafields_Scanner::scan_theme_metaboxes() as $box ) {
			if ( ! empty( $box['metabox_id'] ) && $box['metabox_id'] === $id ) {
				$found = $box;
				break;
			}
		}
		if ( ! $found ) {
			return new WP_Error( 'tpmeta_not_in_theme', 'Metabox not found in active theme scan.', array( 'status' => 404 ) );
		}

		$saved = TPMeta_Metafields_Store::save( $found );
		if ( ! $saved ) {
			return new WP_Error( 'tpmeta_save_failed', 'Could not save imported metabox.', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'imported'        => $saved,
			'theme_metaboxes' => TPMeta_Metafields_Scanner::scan_theme_metaboxes(),
		) );
	}

	/**
	 * Manually bust the scan cache and return fresh results. Used by the
	 * sidebar "refresh" button so users can pick up theme code changes
	 * without waiting for a theme-switch event.
	 */
	public function scan_refresh( $request ) {
		if ( ! class_exists( 'TPMeta_Metafields_Scanner' ) ) {
			return new WP_Error( 'tpmeta_scanner_missing', 'Scanner class not loaded.', array( 'status' => 500 ) );
		}
		TPMeta_Metafields_Scanner::bust_cache();
		return rest_ensure_response( array(
			'theme_metaboxes' => TPMeta_Metafields_Scanner::scan_theme_metaboxes(),
			'theme_user_meta' => TPMeta_Metafields_Scanner::scan_theme_user_meta(),
			'refreshed_at'    => time(),
		) );
	}
}
