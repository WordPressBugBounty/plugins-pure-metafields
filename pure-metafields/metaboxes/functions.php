<?php
/**
 * 
 * Functions
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Get a ThemePure theme option (theme_mod).
 *
 * Thin wrapper over get_theme_mod() so developers can use either
 * tpmeta_get_option() or get_theme_mod() interchangeably. Values
 * are stored per-field via set_theme_mod() by the options panel.
 *
 * @since  1.5.0
 * @param  string $key      Field id (theme_mod key).
 * @param  mixed  $default  Default value if mod is not set.
 * @return mixed
 */
function tpmeta_get_option( $key, $default = '' ) {
  $value = get_theme_mod( $key, $default );

  /*
   * Shape-detect fallback: if the value is still a JSON string of an
   * array (the repeater storage signature), decode it here.
   *
   * The `theme_mod_{$key}` filter normally handles this, but it can be
   * called BEFORE that filter is registered — for example from a theme's
   * `functions.php` top-level, which runs after `plugins_loaded` but
   * before init. Detection is conservative (string starting with '[' that
   * parses to an array) so non-JSON strings and color_gradient-style
   * '{...}' object payloads pass through untouched.
   */
  if ( is_string( $value ) && '' !== $value && '[' === $value[0] ) {
    $decoded = json_decode( $value, true );
    if ( is_array( $decoded ) ) return $decoded;
  }

  return $value;
}

/**
 * Bind `theme_mod_{$key}` decode filters at plugin boot, BEFORE theme
 * functions.php runs.
 *
 * We can't rely on the TPMeta_Options registry here because builder panels
 * register on `init:15` — which is AFTER theme `functions.php` top-level.
 * Instead we read the live theme_mods option once and detect repeater
 * storage by shape (string starting with `[` that parses to an array),
 * binding a filter for each matching key. False positives are unlikely
 * because no other field type starts a stored value with `[`.
 *
 * Hooked on `plugins_loaded:30`. By then the plugin is loaded and the
 * filter is registered before WP loads the theme, so even
 * `get_theme_mod()` calls at theme `functions.php` top-level get arrays.
 */
function tpmeta_register_repeater_decode_filters_early() {
  if ( ! function_exists( 'get_stylesheet' ) ) return;
  $theme = get_stylesheet();
  if ( ! $theme ) return;
  $mods  = get_option( "theme_mods_{$theme}", array() );
  if ( ! is_array( $mods ) || empty( $mods ) ) return;
  foreach ( $mods as $key => $value ) {
    if ( ! is_string( $value ) || '' === $value || '[' !== $value[0] ) continue;
    $decoded = json_decode( $value, true );
    if ( ! is_array( $decoded ) ) continue;
    add_filter( "theme_mod_{$key}", 'tpmeta_repeater_decode_filter' );
  }
}
add_action( 'plugins_loaded', 'tpmeta_register_repeater_decode_filters_early', 30 );

/**
 * Type-aware second pass: also bind decode filters for repeater fields
 * registered via TPMeta_Options (covers fields that have NEVER been
 * saved — no entry exists in theme_mods, so the boot scan misses them).
 *
 * Runs late on `init` so all panels (baked at init:5–10, builder at
 * init:15) are visible. add_filter is idempotent — re-binding the same
 * callback is a no-op.
 */
function tpmeta_register_repeater_decode_filters() {
  if ( ! class_exists( 'TPMeta_Options' ) ) return;
  foreach ( TPMeta_Options::get_panels() as $opt_name => $panel ) {
    $fields = TPMeta_Options::get_fields( $opt_name );
    if ( ! is_array( $fields ) ) continue;
    foreach ( $fields as $field_id => $field ) {
      if ( ! is_array( $field ) || empty( $field['type'] ) ) continue;
      if ( 'repeater' !== $field['type'] ) continue;
      add_filter( "theme_mod_{$field_id}", 'tpmeta_repeater_decode_filter' );
      // Also merge configured sub-field defaults into each row (priority 11,
      // after the priority-10 decode). Registry pass — covers baked-PHP panels
      // and any not in the builder store.
      tpmeta_bind_repeater_default_filter( $field_id, $field );
    }
  }
}
add_action( 'init', 'tpmeta_register_repeater_decode_filters', 99 );

/**
 * Early registration of the repeater default-merge filter by reading panel
 * JSON straight from the builder store — available at plugins_loaded, well
 * before builder panels register against TPMeta_Options on init. Without this
 * a get_theme_mod() call at theme `functions.php` / template-helper top level
 * (which runs after plugins_loaded but before init) would decode the rows but
 * NOT merge the sub-field defaults. Mirrors the switch-boolean early-bind.
 *
 * Hooked on plugins_loaded:30.
 */
function tpmeta_register_repeater_default_filters_early() {
  if ( ! class_exists( 'TPMeta_Builder_Store' ) ) return;
  $panels = TPMeta_Builder_Store::all();
  if ( ! is_array( $panels ) || empty( $panels ) ) return;
  foreach ( $panels as $panel ) {
    if ( empty( $panel['sections'] ) || ! is_array( $panel['sections'] ) ) continue;
    foreach ( $panel['sections'] as $section ) {
      // Support both rows-grouped and flat-fields section shapes.
      $field_groups = array();
      if ( ! empty( $section['rows'] ) && is_array( $section['rows'] ) ) {
        foreach ( $section['rows'] as $row ) {
          if ( ! empty( $row['fields'] ) && is_array( $row['fields'] ) ) {
            $field_groups[] = $row['fields'];
          }
        }
      }
      if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
        $field_groups[] = $section['fields'];
      }
      foreach ( $field_groups as $fields ) {
        foreach ( $fields as $field ) {
          if ( ! is_array( $field ) || empty( $field['id'] ) || empty( $field['type'] ) ) continue;
          if ( 'repeater' !== $field['type'] ) continue;
          tpmeta_bind_repeater_default_filter( $field['id'], $field );
        }
      }
    }
  }
}
add_action( 'plugins_loaded', 'tpmeta_register_repeater_default_filters_early', 30 );

/**
 * Bind the theme_mod default-merge filter for one repeater field, once.
 *
 * Shared by the early (builder-store) and late (TPMeta_Options registry)
 * registration passes. A static guard makes binding idempotent so the same
 * key is never double-filtered, regardless of which pass reaches it first.
 * Bound at priority 11 so it runs AFTER tpmeta_repeater_decode_filter (10),
 * receiving an already-decoded array. Skips fields with no non-empty
 * sub-field default (nothing to merge).
 *
 * @param string $field_id
 * @param array  $field     Repeater field definition (expects 'fields').
 */
function tpmeta_bind_repeater_default_filter( $field_id, $field ) {
  static $bound = array();
  if ( '' === $field_id || isset( $bound[ $field_id ] ) ) return;
  if ( empty( $field['fields'] ) || ! is_array( $field['fields'] ) ) return;

  $has_default = false;
  foreach ( $field['fields'] as $sf ) {
    if ( ! is_array( $sf ) || ! array_key_exists( 'default', $sf ) ) continue;
    $d = $sf['default'];
    if ( '' === $d || null === $d || ( is_array( $d ) && empty( $d ) ) ) continue;
    $has_default = true;
    break;
  }
  if ( ! $has_default ) return;

  $bound[ $field_id ] = true;
  add_filter( "theme_mod_{$field_id}", function ( $value ) use ( $field ) {
    $rows = tpmeta_repeater_decode_filter( $value );
    return tpmeta_apply_repeater_defaults( $rows, $field );
  }, 11 );
}

/**
 * Merge a repeater field's configured sub-field defaults into its decoded
 * rows. Precedence: a saved, non-empty value always wins; a default only
 * fills a sub-field that is missing from the row or saved as an empty string
 * (or, for composite sub-fields like typography, fills the empty/missing keys
 * inside the value array). Idempotent — running it on already-merged rows is
 * a no-op.
 *
 * @param  array $rows  Decoded repeater rows.
 * @param  array $field Repeater field definition (expects 'fields' sub-defs).
 * @return array
 */
function tpmeta_apply_repeater_defaults( $rows, $field ) {
  if ( ! is_array( $rows ) ) return array();
  if ( empty( $field['fields'] ) || ! is_array( $field['fields'] ) ) return $rows;

  // Build a map of sub-field id => default, skipping empty defaults.
  $defaults = array();
  foreach ( $field['fields'] as $sf ) {
    if ( ! is_array( $sf ) || empty( $sf['id'] ) || ! array_key_exists( 'default', $sf ) ) continue;
    $d = $sf['default'];
    if ( '' === $d || null === $d || ( is_array( $d ) && empty( $d ) ) ) continue;
    $defaults[ $sf['id'] ] = $d;
  }
  if ( empty( $defaults ) ) return $rows;

  foreach ( $rows as &$row ) {
    if ( ! is_array( $row ) ) continue;
    foreach ( $defaults as $sfid => $dval ) {
      $saved        = array_key_exists( $sfid, $row ) ? $row[ $sfid ] : null;
      $row[ $sfid ] = tpmeta_merge_default_value( $saved, $dval );
    }
  }
  unset( $row );

  return $rows;
}

/**
 * Resolve one sub-field value against its default. Scalars: the default fills
 * an empty/missing saved value only. Arrays (typography, spacing, …): merge
 * per key so saved keys win and the default supplies any empty/missing keys
 * (recursively for nested arrays).
 *
 * @param  mixed $saved   Saved value (null when the key was absent).
 * @param  mixed $default Configured default.
 * @return mixed
 */
function tpmeta_merge_default_value( $saved, $default ) {
  if ( null === $saved ) return $default;

  if ( is_array( $default ) ) {
    $out = is_array( $saved ) ? $saved : array();
    foreach ( $default as $k => $dv ) {
      $cur = array_key_exists( $k, $out ) ? $out[ $k ] : null;
      if ( null === $cur || '' === $cur ) {
        $out[ $k ] = $dv;
      } elseif ( is_array( $dv ) && is_array( $cur ) ) {
        $out[ $k ] = tpmeta_merge_default_value( $cur, $dv );
      }
    }
    return $out;
  }

  return ( '' === $saved ) ? $default : $saved;
}

/**
 * Filter callback: decode a repeater theme_mod JSON string into an array
 * of rows. Idempotent — already-decoded values pass through, malformed
 * JSON falls back to an empty array (the safe default for `foreach`).
 *
 * @param mixed $value Raw value from get_theme_mod().
 * @return array
 */
function tpmeta_repeater_decode_filter( $value ) {
  if ( is_array( $value ) ) return $value;
  if ( ! is_string( $value ) || '' === $value ) return array();
  $decoded = json_decode( $value, true );
  return is_array( $decoded ) ? $decoded : array();
}

/**
 * Switch field — boolean cast layer.
 *
 * When a switch field is configured with `data_type = 'boolean'`, the
 * value is stored as the string 'true' / 'false' (HTML forms can only POST
 * strings). Theme code that calls `get_theme_mod()` or `tpmeta_field()`
 * expects a real PHP boolean for clean ternaries, conditional checks, and
 * JSON output — comparing against the string 'true' is a footgun (both
 * 'true' and 'false' are truthy in PHP).
 *
 * The cast is opt-in: only fields the user explicitly marked as boolean
 * receive the filter, so legacy `'on'`/`'off'` switches are untouched.
 * Filter binding mirrors the repeater decode pattern so theme calls at any
 * load point — `functions.php` top-level included — receive bools.
 */

/**
 * Convert any of the truthy-form values to PHP true; everything else to
 * PHP false. Idempotent — bools pass through unchanged.
 *
 * @param mixed $value
 * @return bool
 */
function tpmeta_switch_to_bool( $value ) {
  if ( is_bool( $value ) ) return $value;
  if ( is_int( $value ) )  return ( 1 === $value );
  if ( ! is_string( $value ) ) return false;
  $v = strtolower( trim( $value ) );
  return in_array( $v, array( 'true', 'on', '1', 'yes' ), true );
}

/**
 * Filter callback for `theme_mod_{$key}` on switch+boolean fields.
 *
 * @param mixed $value
 * @return bool
 */
function tpmeta_switch_boolean_cast_filter( $value ) {
  return tpmeta_switch_to_bool( $value );
}

/**
 * Early registration of `theme_mod_{$key}` cast filters by reading panel
 * JSON directly from the builder store (available at plugins_loaded since
 * the store class is loaded by the plugin bootstrap — well before
 * `init` when builder panels actually register against TPMeta_Options).
 *
 * Hooked on `plugins_loaded:30`. By then the plugin is loaded and the
 * filter is registered before WP loads the theme, so even
 * `get_theme_mod()` calls at theme `functions.php` top-level type-cast.
 */
function tpmeta_register_switch_boolean_cast_filters_early() {
  if ( ! class_exists( 'TPMeta_Builder_Store' ) ) return;
  $panels = TPMeta_Builder_Store::all();
  if ( ! is_array( $panels ) || empty( $panels ) ) return;
  foreach ( $panels as $panel ) {
    if ( empty( $panel['sections'] ) || ! is_array( $panel['sections'] ) ) continue;
    foreach ( $panel['sections'] as $section ) {
      // Both flat-fields and rows-grouped sections are supported.
      $field_groups = array();
      if ( ! empty( $section['rows'] ) && is_array( $section['rows'] ) ) {
        foreach ( $section['rows'] as $row ) {
          if ( ! empty( $row['fields'] ) && is_array( $row['fields'] ) ) {
            $field_groups[] = $row['fields'];
          }
        }
      }
      if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
        $field_groups[] = $section['fields'];
      }
      foreach ( $field_groups as $fields ) {
        foreach ( $fields as $field ) {
          if ( ! is_array( $field ) ) continue;
          if ( empty( $field['id'] ) || empty( $field['type'] ) ) continue;

          // Top-level switch with boolean storage.
          if ( 'switch' === $field['type']
            && ! empty( $field['data_type'] )
            && 'boolean' === $field['data_type']
          ) {
            add_filter( "theme_mod_{$field['id']}", 'tpmeta_switch_boolean_cast_filter' );
            continue;
          }

          // Repeater carrying switch+boolean sub-fields. Bound at priority
          // 11 so it runs after `tpmeta_repeater_decode_filter` (default
          // priority 10) which turns the JSON string into an array.
          if ( 'repeater' === $field['type']
            && ! empty( $field['fields'] )
            && is_array( $field['fields'] )
            && tpmeta_repeater_has_switch_boolean_subfield( $field['fields'] )
          ) {
            add_filter( "theme_mod_{$field['id']}", 'tpmeta_repeater_subfield_bool_cast_filter', 11 );
          }
        }
      }
    }
  }
}
add_action( 'plugins_loaded', 'tpmeta_register_switch_boolean_cast_filters_early', 30 );

/**
 * Type-aware second pass: bind cast filters via the live TPMeta_Options
 * registry. Catches baked-PHP panels (which register at init:5–10) and any
 * panels that didn't ship via the builder store. add_filter is idempotent —
 * re-binding the same callback on the same hook is a no-op.
 *
 * Repeater fields that contain switch+boolean sub-fields also get a per-row
 * cast filter bound to their parent theme_mod, run after the JSON-decode
 * filter (priority 11) so the rows arrive as PHP arrays.
 */
function tpmeta_register_switch_boolean_cast_filters() {
  if ( ! class_exists( 'TPMeta_Options' ) ) return;
  foreach ( TPMeta_Options::get_panels() as $opt_name => $panel ) {
    $fields = TPMeta_Options::get_fields( $opt_name );
    if ( ! is_array( $fields ) ) continue;
    foreach ( $fields as $field_id => $field ) {
      if ( ! is_array( $field ) || empty( $field['type'] ) ) continue;
      if ( 'switch' === $field['type']
        && ! empty( $field['data_type'] )
        && 'boolean' === $field['data_type']
      ) {
        add_filter( "theme_mod_{$field_id}", 'tpmeta_switch_boolean_cast_filter' );
        continue;
      }
      if ( 'repeater' === $field['type']
        && ! empty( $field['fields'] )
        && is_array( $field['fields'] )
        && tpmeta_repeater_has_switch_boolean_subfield( $field['fields'] )
      ) {
        add_filter( "theme_mod_{$field_id}", 'tpmeta_repeater_subfield_bool_cast_filter', 11 );
      }
    }
  }
}
add_action( 'init', 'tpmeta_register_switch_boolean_cast_filters', 99 );

/**
 * Helper — does the supplied sub-field list contain at least one switch+boolean?
 *
 * @param array $sub_fields
 * @return bool
 */
function tpmeta_repeater_has_switch_boolean_subfield( $sub_fields ) {
  if ( ! is_array( $sub_fields ) ) return false;
  foreach ( $sub_fields as $sf ) {
    if ( ! is_array( $sf ) ) continue;
    if ( 'switch' === ( $sf['type'] ?? '' )
      && 'boolean' === ( $sf['data_type'] ?? '' )
    ) {
      return true;
    }
  }
  return false;
}

/**
 * Filter callback for `theme_mod_{$repeater_id}` (priority 11, after the
 * decode filter). Walks each row and casts switch+boolean sub-field values
 * to real PHP bools. Looks the field def up via TPMeta_Options at filter
 * time so the cast set always reflects the live registry.
 *
 * @param mixed $rows
 * @return mixed
 */
function tpmeta_repeater_subfield_bool_cast_filter( $rows ) {
  if ( ! is_array( $rows ) || empty( $rows ) ) return $rows;
  if ( ! class_exists( 'TPMeta_Options' ) ) return $rows;

  $current = current_filter();
  if ( 0 !== strpos( $current, 'theme_mod_' ) ) return $rows;
  $field_id = substr( $current, strlen( 'theme_mod_' ) );
  if ( '' === $field_id ) return $rows;

  // Find the repeater field def across all panels.
  $field_def = null;
  foreach ( TPMeta_Options::get_panels() as $opt_name => $_panel ) {
    $candidate = TPMeta_Options::get_field( $opt_name, $field_id );
    if ( is_array( $candidate ) && 'repeater' === ( $candidate['type'] ?? '' ) ) {
      $field_def = $candidate;
      break;
    }
  }
  if ( null === $field_def || empty( $field_def['fields'] ) ) return $rows;

  return tpmeta_repeater_apply_subfield_bool_cast( $rows, $field_def );
}

/**
 * Walk repeater rows and coerce switch+boolean sub-field values to bools.
 * Pure function — used by both the theme_mod filter and tpmeta_field()
 * for post_meta-backed metabox repeaters.
 *
 * @param mixed $rows
 * @param array $field
 * @return mixed
 */
function tpmeta_repeater_apply_subfield_bool_cast( $rows, array $field ) {
  if ( ! is_array( $rows ) || empty( $rows ) ) return $rows;
  if ( empty( $field['fields'] ) || ! is_array( $field['fields'] ) ) return $rows;

  $bool_keys = array();
  foreach ( $field['fields'] as $sf ) {
    if ( ! is_array( $sf ) ) continue;
    if ( 'switch' !== ( $sf['type'] ?? '' ) )      continue;
    if ( 'boolean' !== ( $sf['data_type'] ?? '' ) ) continue;
    if ( empty( $sf['id'] ) )                       continue;
    $bool_keys[] = $sf['id'];
  }
  if ( empty( $bool_keys ) ) return $rows;

  foreach ( $rows as $idx => $row ) {
    if ( ! is_array( $row ) ) continue;
    foreach ( $bool_keys as $sfid ) {
      if ( array_key_exists( $sfid, $row ) ) {
        $rows[ $idx ][ $sfid ] = tpmeta_switch_to_bool( $row[ $sfid ] );
      }
    }
  }
  return $rows;
}

/**
 * Read an image-field theme option, resolving portable tokens and
 * normalising the value to the configured return_format.
 *
 * Drop-in for `get_theme_mod()` / `tpmeta_get_option()` when the field
 * is an image field — handles all three return formats transparently:
 *
 *   'array' → [ 'id' => int, 'url' => 'https://…', 'alt' => '…' ]
 *   'url'   → 'https://…' (token resolved)
 *   'id'    → integer attachment ID
 *
 * If the saved value is an attachment ID and a $size is provided, the
 * URL part is fetched at that size (only meaningful for the 'array'
 * shape; ignored for 'id'; for 'url' shape the value is whatever was
 * saved, which is already a URL or a resolved token).
 *
 * @since 1.9.x
 * @param string $key      Field id (theme_mod key).
 * @param mixed  $default  Default returned when nothing is stored. The
 *                         default itself is also passed through token
 *                         resolution so theme-relative defaults work.
 * @param string $size     Image size for ID-based array shape. Ignored
 *                         for 'url' / 'id' return formats.
 * @return mixed Whatever the field's return_format says.
 */
function tpmeta_image_option( $key, $default = '', $size = 'full' ) {
  $value = get_theme_mod( $key, $default );
  if ( '' === $value || null === $value || ( is_array( $value ) && empty( $value ) ) ) {
    return $value;
  }

  // Array shape: { id, url, alt }. Resolve token in url.
  if ( is_array( $value ) ) {
    if ( ! isset( $value['url'] ) ) $value['url'] = '';
    if ( ! isset( $value['alt'] ) ) $value['alt'] = '';
    if ( ! isset( $value['id'] )  ) $value['id']  = 0;

    if ( '' !== $value['url'] && false !== strpos( (string) $value['url'], '{{' ) ) {
      $value['url'] = (string) tpmeta_resolve_image_url( $value['url'] );
    }
    // If we have an ID and a size, prefer the sized URL.
    if ( $value['id'] && $size && 'full' !== $size ) {
      $sized = wp_get_attachment_image_url( (int) $value['id'], $size );
      if ( $sized ) $value['url'] = (string) $sized;
    }
    // Fill missing alt from the attachment when ID is known.
    if ( '' === $value['alt'] && $value['id'] ) {
      $value['alt'] = (string) get_post_meta( (int) $value['id'], '_wp_attachment_image_alt', true );
    }
    return $value;
  }

  // Scalar URL (return_format = 'url'). Resolve any token.
  if ( is_string( $value ) ) {
    if ( false !== strpos( $value, '{{' ) ) {
      return (string) tpmeta_resolve_image_url( $value );
    }
    // Numeric string slipped in (legacy storage as ID).
    if ( ctype_digit( $value ) ) {
      return (int) $value;
    }
    return $value;
  }

  // Integer (return_format = 'id') — return as int.
  if ( is_int( $value ) ) {
    return $value;
  }

  return $value;
}

/**
* Get the meta field value
*/
function tpmeta_field($meta_key, $post_id = NULL){
  if($post_id != NULL){
    $tpmeta_field_value = get_post_meta($post_id, $meta_key, true);
    if(empty($tpmeta_field_value) || $tpmeta_field_value == ""){
      $tpmeta_get_field = tpmeta_get_field_value_by_id($meta_key);
      if(is_null($tpmeta_get_field) || empty($tpmeta_get_field)){
        return false;
      }else{
        if(isset($tpmeta_get_field['default'])){
          return tpmeta_field_post_cast( $tpmeta_get_field, $tpmeta_get_field['default'] );
        }else{
          return false;
        }
      }
    }else{
      return tpmeta_field_post_cast( tpmeta_get_field_value_by_id($meta_key), $tpmeta_field_value );
    }
  }else{
    global $post;
    if( !empty($post) ){
      $tpmeta_field_value = get_post_meta($post->ID, $meta_key, true);
      if(!metadata_exists('post', $post->ID, $meta_key)){
        if(is_null(tpmeta_get_field_value_by_id($meta_key))){
          return false;
        }
        if(isset(tpmeta_get_field_value_by_id($meta_key)['default'])){
          return tpmeta_field_post_cast( tpmeta_get_field_value_by_id($meta_key), tpmeta_get_field_value_by_id($meta_key)['default'] );
        }
        return false;
      }else{
        return tpmeta_field_post_cast( tpmeta_get_field_value_by_id($meta_key), $tpmeta_field_value );
      }
    }else{
      $tpmeta_get_field = tpmeta_get_field_value_by_id($meta_key);
      if(is_null($tpmeta_get_field) || empty($tpmeta_get_field)){
        return false;
      }else{
        return isset($tpmeta_get_field['default'])
          ? tpmeta_field_post_cast( $tpmeta_get_field, $tpmeta_get_field['default'] )
          : false;
      }
    }
  }
}

/**
 * Apply field-type-aware casting to a value about to be returned from
 * `tpmeta_field()`. Currently the only cast in play is switch+boolean →
 * real PHP bool, mirroring the `theme_mod_{key}` filter on the options
 * panel side. The function is a no-op for any other field type / shape,
 * so existing callers stay byte-identical.
 *
 * @param array|null $field Field def from tpmeta_get_field_value_by_id().
 * @param mixed      $value Raw post_meta value (or default).
 * @return mixed
 */
function tpmeta_field_post_cast( $field, $value ) {
  if ( ! is_array( $field ) ) return $value;
  $type = isset( $field['type'] ) ? $field['type'] : '';
  if ( '' === $type ) return $value;

  // Top-level switch with boolean storage → real PHP bool.
  if ( 'switch' === $type
    && ! empty( $field['data_type'] )
    && 'boolean' === $field['data_type']
  ) {
    return tpmeta_switch_to_bool( $value );
  }

  // Repeater rows: walk and cast switch+boolean sub-field values. Metabox
  // repeater data is stored as a PHP array of rows in post_meta, so the
  // value here is already structured. Mirrors the theme_mod filter for the
  // options-panel side.
  if ( 'repeater' === $type
    && ! empty( $field['fields'] )
    && is_array( $field['fields'] )
  ) {
    return tpmeta_repeater_apply_subfield_bool_cast( $value, $field );
  }

  return $value;
}

/**
* Get Gallery
*/
function tpmeta_gallery_field($key, $post_id = null ){
  global $post;
  $post_id = $post_id? $post_id : $post->ID;
  $get_field_value = get_post_meta($post_id, $key, true);
  if($get_field_value){
    return tpmeta_gallery_images($get_field_value);
  }else{
    return false;
  }
}

/**
 * Get an image meta-field value, resolving the configured return_format
 * automatically.
 *
 * Backward-compatible: existing fields stored as scalar attachment IDs
 * continue to return the legacy `[ url, alt ]` array. Fields configured
 * with a return_format on their definition return:
 *
 *   'array' → [ 'id' => int, 'url' => 'https://…', 'alt' => '…' ]
 *   'url'   → 'https://…' (token resolved when present)
 *   'id'    → integer attachment ID
 *
 * @param string   $key      Meta key (field id).
 * @param int|null $post_id  Post id; defaults to the global $post when null.
 * @return mixed             Whatever the field's return_format says, or
 *                           false when nothing is stored.
 */
function tpmeta_image_field($key, $post_id = null ){
  global $post;
  if( is_null($post) && is_null($post_id) ){
    return false;
  }
  $post_id = $post_id? $post_id : $post->ID;
  $get_field_value = get_post_meta($post_id, $key, true);

  if ( '' === $get_field_value || null === $get_field_value || ( is_array( $get_field_value ) && empty( $get_field_value ) ) ) {
    return false;
  }

  // Array shape (return_format = 'array') — resolve any token in the url
  // and fill missing alt from the attachment when ID is known.
  if ( is_array( $get_field_value ) ) {
    $url = isset( $get_field_value['url'] ) ? (string) $get_field_value['url'] : '';
    $alt = isset( $get_field_value['alt'] ) ? (string) $get_field_value['alt'] : '';
    $aid = isset( $get_field_value['id'] )  ? absint( $get_field_value['id'] ) : 0;
    if ( '' !== $url && false !== strpos( $url, '{{' ) ) {
      $url = (string) tpmeta_resolve_image_url( $url );
    }
    if ( '' === $alt && $aid ) {
      $alt = (string) get_post_meta( $aid, '_wp_attachment_image_alt', true );
    }
    return array( 'id' => $aid, 'url' => $url, 'alt' => $alt );
  }

  // String values: URL, portable token, or numeric ID.
  if ( is_string( $get_field_value ) ) {
    if ( false !== strpos( $get_field_value, '{{' ) ) {
      // return_format = 'url' with a portable token.
      return (string) tpmeta_resolve_image_url( $get_field_value );
    }
    if ( filter_var( $get_field_value, FILTER_VALIDATE_URL ) ) {
      // return_format = 'url' with a literal URL.
      return $get_field_value;
    }
    if ( ctype_digit( $get_field_value ) ) {
      // Legacy or return_format = 'id' stored as numeric string —
      // delegate to tpmeta_image() for the BC `[url, alt]` shape.
      return tpmeta_image( $get_field_value );
    }
  }

  // Integer (return_format = 'id') — return the ID directly so themes
  // can decide on size at the call site.
  if ( is_int( $get_field_value ) ) {
    return $get_field_value;
  }

  return tpmeta_image( $get_field_value );
}

/**
 * Compare two values with a given operator.
 * Uses a switch instead of eval() to avoid parse errors on invalid operators.
 */
function tpmeta_compare( $value, $operator, $compare ) {
  switch ( $operator ) {
    case '==':  return $value ==  $compare;
    case '===': return $value === $compare;
    case '!=':  return $value !=  $compare;
    case '!==': return $value !== $compare;
    case '>':   return $value >   $compare;
    case '<':   return $value <   $compare;
    case '>=':  return $value >=  $compare;
    case '<=':  return $value <=  $compare;
    default:    return false;
  }
}

/**
 * Normalise a conditional array to the numeric [field, op, value] format.
 * Builder JSON produces an assoc {field, operator, value}; legacy code and the
 * options-panel loader produce a numeric list. Handle both.
 */
function tpmeta_normalise_conditional( array $conditional ) {
  if ( isset( $conditional['field'] ) ) {
    return array(
      $conditional['field'],
      isset( $conditional['operator'] ) ? $conditional['operator'] : '==',
      isset( $conditional['value'] )    ? $conditional['value']    : '',
    );
  }
  return array(
    isset( $conditional[0] ) ? $conditional[0] : '',
    isset( $conditional[1] ) ? $conditional[1] : '==',
    isset( $conditional[2] ) ? $conditional[2] : '',
  );
}

/**
 * Check whether a conditional rule matches the current field values.
 */
function tpmeta_is_condition_matched( array $conditional, array $fields ) {
  $c = tpmeta_normalise_conditional( $conditional );
  if ( '' === $c[0] ) return true;

  $get_conditional_field       = array_column( $fields, 'default', 'id' );
  $get_conditional_field_value = isset( $get_conditional_field[ $c[0] ] ) ? $get_conditional_field[ $c[0] ] : '';

  $current = tpmeta_field( $c[0] );
  $subject = ( '' === $current ) ? $get_conditional_field_value : $current;
  return tpmeta_compare( $subject, $c[1], $c[2] );
}

/**
 * Check whether a conditional rule matches a repeater row's values.
 */
function tpmeta_is_row_matched( array $conditional, array $row ) {
  $c             = tpmeta_normalise_conditional( $conditional );
  $get_row_value = ( '' !== $c[0] && isset( $row[ $c[0] ] ) ) ? $row[ $c[0] ] : '';
  return tpmeta_compare( $get_row_value, $c[1], $c[2] );
}


/**
*  Match the field post format with admin post format
*/
function tpmeta_field_matched_with_post_format($field_with_post_format, $current_post_format){
  if(isset($field_with_post_format)){
    if($field_with_post_format != $current_post_format){
      return false;
    }else{
      return true;
    }
  }else{
    return true;
  }
}

/**
* Get images ids from meta field and return URL and ALT
*/
function tpmeta_gallery_images(string $gallery_value, string $size = "full"){
  if(gettype($gallery_value) == "string" && $gallery_value != ""){
    $get_images_ids = explode(",", $gallery_value);
    $gallery_items = array();
    foreach($get_images_ids as $image_id){
      $get_attachment = wp_get_attachment_image_src($image_id, $size);
      if(false != $get_attachment){
        array_push($gallery_items, array(
          'url' => $get_attachment[0],
          'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
        ));
      }
    }

    return $gallery_items;
  }else{
    return false;
  }
}

/**
* Return single image with URL and ALT
*/
function tpmeta_image($image_id, string $size = "full"){
  if($image_id != ""){
    $get_attachment = wp_get_attachment_image_src($image_id, $size);
    if(false != $get_attachment){
      $image_src = array(
        'url' => $get_attachment[0],
        'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
      );
      return $image_src;
    }else{
      return false;
    }
  }else{
    return false;
  }
}


/**
* Get the field by IT's ID
*/
function tpmeta_get_field_value_by_id($field_id){
  $_metaboxes = apply_filters('tp_meta_boxes', array());
  $_fields = array_column($_metaboxes, 'fields');
  foreach($_fields as $fields){
    foreach($fields as $field){
      if($field['id'] == $field_id){
        return $field;
      }
    }
  }
}


/**
 * Load Template
 */
function tpmeta_load_template($path, $args = array()){
  extract($args);
  if( file_exists(TPMETA_PATH . $path) ){
    include(TPMETA_PATH . $path);
  }
}


/**
 * Allowed svg tags
 */
function tpmeta_allowed_svg_tags(){
  $allowed_tags = array(
    'svg' => array(
      'xmlns' => true,
      'viewBox' => true,
      'width' => true,
      'height' => true,
      'preserveAspectRatio' => true,
      'fill' => true,
      'stroke' => true,
      'style' => true,
      // Add any other necessary attributes
    ),
    'circle' => array(
      'cx' => true,
      'cy' => true,
      'r' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      // Add other attributes as needed
    ),
    'rect' => array(
      'x' => true,
      'y' => true,
      'width' => true,
      'height' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'ellipse' => array(
      'cx' => true,
      'cy' => true,
      'rx' => true,
      'ry' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'line' => array(
      'x1' => true,
      'y1' => true,
      'x2' => true,
      'y2' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'polyline' => array(
      'points' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'polygon' => array(
      'points' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
    ),
    'path' => array(
      'd' => true,
      'fill' => true,
      'stroke' => true,
      'stroke-width' => true,
      'stroke-linecap' => true,
      'stroke-linejoin' => true,
    ),
    'g' => array(
      'fill' => true,
      'stroke' => true,
      // Add other attributes as needed
    ),
    'defs' => array(), // No attributes needed
    'clipPath' => array(), // No attributes needed
    'linearGradient' => array(
      'id' => true,
      'x1' => true,
      'y1' => true,
      'x2' => true,
      'y2' => true,
    ),
    'radialGradient' => array(
      'id' => true,
      'cx' => true,
      'cy' => true,
      'r' => true,
      'fx' => true,
      'fy' => true,
    ),
    'stop' => array(
      'offset' => true,
      'stop-color' => true,
      'stop-opacity' => true,
    ),
    // Add more SVG tags and attributes as needed
		'a' => [
			'class'    => [],
			'href'    => [],
			'title'    => [],
			'target'    => [],
			'rel'    => [],
		],
    'b' => [],
    'blockquote'  =>  [
      'cite' => [],
    ],
    'cite'                      => [
      'title' => [],
    ],
    'code'                      => [],
    'del'                    => [
      'datetime'   => [],
      'title'      => [],
  ],
    'dd'                     => [],
    'div'                    => [
      'class'   => [],
      'title'   => [],
      'style'   => [],
    ],
    'dl'                     => [],
    'dt'                     => [],
    'em'                     => [],
    'h1'                     => [],
    'h2'                     => [],
    'h3'                     => [],
    'h4'                     => [],
    'h5'                     => [],
    'h6'                     => [],
    'i'                         => [
      'class' => [],
    ],
    'img'                    => [
      'alt'  => [],
      'class'   => [],
      'height' => [],
      'src'  => [],
      'width'   => [],
    ],
    'li'                     => array(
      'class' => array(),
    ),
    'ul'                     => array(
      'class' => array(),
    ),
    'ol'                     => array(
      'class' => array(),
    ),
    'p'                         => array(
      'class' => array(),
    ),
    'q'                         => array(
      'cite'    => array(),
      'title'   => array(),
    ),
    'q'                         => array(
      'cite'    => array(),
      'title'   => array(),
    ),
    'span'                      => array(
      'class'   => array(),
      'title'   => array(),
      'style'   => array(),
    ),
    'iframe'                 => array(
      'width'         => array(),
      'height'     => array(),
      'scrolling'     => array(),
      'frameborder'   => array(),
      'allow'         => array(),
      'src'        => array(),
    ),
    'strike'                 => array(),
    'br'                     => array(),
    'strong'                 => array(),
  );

  return apply_filters('tpmeta_allowed_svg_tags', $allowed_tags);
}

/**
 * Portable image-URL token map.
 *
 * The image-default picker stores theme/plugin paths as portable tokens
 * (e.g. `{{theme_url}}/inc/img/x.jpg`) so saved panels survive a domain
 * change or migration. This map drives both the JS preview resolver
 * (localized into the builder) and the PHP runtime resolver below.
 *
 * Returned shape:
 *   [
 *     'theme_url'      => 'https://site.com/wp-content/themes/parent',
 *     'stylesheet_url' => 'https://site.com/wp-content/themes/child',
 *     'content_url'    => 'https://site.com/wp-content',
 *     'themes'         => [ 'finzo' => 'https://.../themes/finzo', ... ],
 *     'plugins'        => [ 'akismet' => 'https://.../plugins/akismet', ... ],
 *   ]
 *
 * @return array
 */
function tpmeta_image_token_prefix_map() {
  $map = array(
    'theme_url'      => get_template_directory_uri(),
    'stylesheet_url' => get_stylesheet_directory_uri(),
    'content_url'    => content_url(),
    'themes'         => array(),
    'plugins'        => array(),
  );

  // Per-theme prefixes (so a token can pin a specific theme by slug,
  // not just "active theme").
  foreach ( (array) glob( get_theme_root() . '/*', GLOB_ONLYDIR ) as $dir ) {
    $slug = basename( $dir );
    $map['themes'][ $slug ] = get_theme_root_uri() . '/' . $slug;
  }

  // Per-plugin prefixes.
  if ( defined( 'WP_PLUGIN_DIR' ) ) {
    foreach ( (array) glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR ) as $dir ) {
      $slug = basename( $dir );
      $map['plugins'][ $slug ] = plugins_url( '', $slug . '/' . $slug . '.php' );
      // plugins_url() falls back to a sane URL even when the bootstrap
      // file name doesn't match the slug; if not, override:
      if ( false === strpos( $map['plugins'][ $slug ], '/' . $slug ) ) {
        $map['plugins'][ $slug ] = plugins_url() . '/' . $slug;
      }
    }
  }

  return $map;
}

/**
 * Convert a wp-content-relative file path into a portable token.
 *
 * Examples:
 *   themes/parent/img/x.jpg              → {{theme_url}}/img/x.jpg            (active parent)
 *   themes/child/img/x.jpg               → {{stylesheet_url}}/img/x.jpg       (active child)
 *   themes/other-theme/img/x.jpg         → {{theme_url:other-theme}}/img/x.jpg
 *   plugins/some-plugin/img/x.jpg        → {{plugin_url:some-plugin}}/img/x.jpg
 *   uploads/2024/x.jpg                   → {{content_url}}/uploads/2024/x.jpg
 *
 * @param string $rel_path  Path relative to wp-content (no leading slash).
 * @return string Token form.
 */
function tpmeta_tokenize_content_path( $rel_path ) {
  $rel_path = ltrim( (string) $rel_path, '/' );
  if ( '' === $rel_path ) return '';

  $active_template   = get_template();   // parent slug
  $active_stylesheet = get_stylesheet(); // child slug (== template if no child)

  // Active child theme (only when actually a child theme — different from parent).
  if ( $active_stylesheet !== $active_template
    && 0 === strpos( $rel_path, 'themes/' . $active_stylesheet . '/' ) ) {
    return '{{stylesheet_url}}/' . substr( $rel_path, strlen( 'themes/' . $active_stylesheet . '/' ) );
  }

  // Active parent theme.
  if ( 0 === strpos( $rel_path, 'themes/' . $active_template . '/' ) ) {
    return '{{theme_url}}/' . substr( $rel_path, strlen( 'themes/' . $active_template . '/' ) );
  }

  // Other (inactive) themes — pin by slug.
  if ( preg_match( '#^themes/([^/]+)/(.+)$#', $rel_path, $m ) ) {
    return '{{theme_url:' . $m[1] . '}}/' . $m[2];
  }

  // Plugins.
  if ( preg_match( '#^plugins/([^/]+)/(.+)$#', $rel_path, $m ) ) {
    return '{{plugin_url:' . $m[1] . '}}/' . $m[2];
  }

  // Anything else under wp-content (uploads, mu-plugins, etc.).
  return '{{content_url}}/' . $rel_path;
}

/**
 * Resolve any portable image tokens inside a value to absolute URLs.
 *
 * Accepts a string, an array (recursive), or anything else (returned
 * unchanged). Strings without any `{{...}}` token pass through untouched —
 * existing absolute URLs and attachment IDs are unaffected.
 *
 * @param mixed $value
 * @return mixed
 */
function tpmeta_resolve_image_url( $value ) {
  if ( is_array( $value ) ) {
    foreach ( $value as $k => $v ) {
      $value[ $k ] = tpmeta_resolve_image_url( $v );
    }
    return $value;
  }
  if ( ! is_string( $value ) || false === strpos( $value, '{{' ) ) {
    return $value;
  }

  $map = tpmeta_image_token_prefix_map();

  // Simple top-level tokens.
  $value = strtr( $value, array(
    '{{theme_url}}'      => $map['theme_url'],
    '{{stylesheet_url}}' => $map['stylesheet_url'],
    '{{content_url}}'    => $map['content_url'],
  ) );

  // Parameterised tokens: {{theme_url:slug}}, {{plugin_url:slug}}.
  $value = preg_replace_callback( '/\{\{(theme_url|plugin_url):([a-z0-9_\-]+)\}\}/i', function ( $m ) use ( $map ) {
    $kind = strtolower( $m[1] );
    $slug = $m[2];
    if ( 'theme_url' === $kind && isset( $map['themes'][ $slug ] ) ) {
      return $map['themes'][ $slug ];
    }
    if ( 'plugin_url' === $kind && isset( $map['plugins'][ $slug ] ) ) {
      return $map['plugins'][ $slug ];
    }
    // Unknown slug — best-effort fallback so the URL still points somewhere reachable.
    return ( 'theme_url' === $kind )
      ? get_theme_root_uri() . '/' . $slug
      : ( defined( 'WP_PLUGIN_URL' ) ? WP_PLUGIN_URL . '/' . $slug : plugins_url() . '/' . $slug );
  }, $value );

  return $value;
}