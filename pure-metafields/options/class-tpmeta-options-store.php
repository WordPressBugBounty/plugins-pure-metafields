<?php
/**
 * TPMeta_Options_Store
 *
 * Reads/writes option values via the WordPress theme_mod API so the
 * standard get_theme_mod()/set_theme_mod() functions work alongside
 * tpmeta_get_option().
 *
 * @package    tpmeta
 * @subpackage tpmeta/options
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Options_Store {

	/**
	 * Get a single field value with default fallback.
	 *
	 * @param string $field_id  Theme mod key.
	 * @param mixed  $default   Default if mod is unset.
	 * @return mixed
	 */
	public static function get( $field_id, $default = '' ) {
		return get_theme_mod( $field_id, $default );
	}

	/**
	 * Save a single field value.
	 *
	 * @param string $field_id
	 * @param mixed  $value
	 */
	public static function set( $field_id, $value ) {
		set_theme_mod( $field_id, $value );
	}

	/**
	 * Delete a stored field value.
	 *
	 * @param string $field_id
	 */
	public static function delete( $field_id ) {
		remove_theme_mod( $field_id );
	}

	/**
	 * Resolve the value for a field. Falls back to the field's
	 * registered 'default' if no theme_mod is set.
	 *
	 * @param array $field Field definition (must contain 'id').
	 * @return mixed
	 */
	public static function value( $field ) {
		if ( empty( $field['id'] ) ) {
			return '';
		}
		$default = isset( $field['default'] ) ? $field['default'] : '';
		return get_theme_mod( $field['id'], $default );
	}

	/**
	 * Persist a batch of field values, sanitizing per type using the
	 * registered field definitions for the given panel.
	 *
	 * @param string $opt_name  Panel identifier.
	 * @param array  $values    Raw $field_id => $value map.
	 * @return array            Sanitized values that were saved.
	 */
	public static function save_batch( $opt_name, array $values ) {
		$fields = TPMeta_Options::get_fields( $opt_name );
		$saved  = array();

		foreach ( $fields as $field_id => $field ) {
			$incoming = isset( $values[ $field_id ] ) ? $values[ $field_id ] : null;
			$clean    = self::sanitize( $field, $incoming );
			self::set( $field_id, $clean );
			$saved[ $field_id ] = $clean;
		}

		return $saved;
	}

	/**
	 * Sanitize a value based on field type.
	 *
	 * @param array $field
	 * @param mixed $value
	 * @return mixed
	 */
	/**
	 * Returns true if $color is an acceptable CSS color value:
	 * hex, rgb/rgba(), hsl/hsla(), or a named color word (red, blue, …).
	 */
	public static function is_valid_color( $color ) {
		if ( '' === $color ) return true;
		if ( preg_match( '/^#([a-f0-9]{3,8})$/i', $color ) ) return true;
		if ( preg_match( '/^rgba?\([\s\d.,%-]+\)$/i', $color ) ) return true;
		if ( preg_match( '/^hsla?\([\s\d.,%-]+\)$/i', $color ) ) return true;
		if ( preg_match( '/^[a-zA-Z]+$/', $color ) ) return true;
		return false;
	}

	public static function sanitize( $field, $value ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		// Allow developer override per field via 'sanitize_callback'.
		if ( ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			return call_user_func( $field['sanitize_callback'], $value, $field );
		}

		switch ( $type ) {
			case 'textarea':
				if ( null === $value ) return '';
				return wp_kses( wp_unslash( $value ), tpmeta_allowed_svg_tags() );

			case 'switch':
				$data_type = isset( $field['data_type'] ) ? $field['data_type'] : 'string';
				// Detect the on-state from EVERY shape the value can arrive in:
				//  • raw HTML-form tokens 'on' / 'true' (normal AJAX save)
				//  • a real PHP bool or int 1/0 — which is what an export bundle
				//    carries, because collect_panel_values() reads via
				//    get_theme_mod() and the theme_mod_{key} boolean-cast filter
				//    has already coerced the stored 'true'/'false' string into a
				//    PHP bool. Without this, re-importing a boolean switch that
				//    was ON silently flips it OFF (strict in_array never matched
				//    the bool true against the string token list).
				$is_on = function_exists( 'tpmeta_switch_to_bool' )
					? tpmeta_switch_to_bool( $value )
					: in_array( $value, array( 'true', 'on', '1', 'yes', true, 1 ), true );
				return ( 'boolean' === $data_type )
					? ( $is_on ? 'true' : 'false' )
					: ( $is_on ? 'on' : 'off' );

			case 'checkbox':
				if ( ! is_array( $value ) ) {
					$value = array();
				}
				return array_map( 'sanitize_text_field', array_map( 'wp_unslash', $value ) );

			case 'select':
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', array_map( 'wp_unslash', $value ) );
				}
				return sanitize_text_field( wp_unslash( (string) $value ) );

			case 'colorpicker':
				$value = sanitize_text_field( wp_unslash( (string) $value ) );
				// Allow hex, rgb, rgba.
				if ( '' === $value ) return '';
				if ( preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $value ) ) return $value;
				if ( preg_match( '/^rgba?\([\s\d\.,%]+\)$/i', $value ) ) return $value;
				return '';

			case 'image':
				/*
				 * Three storage shapes, picked by the field's return_format:
				 *
				 *   'array' (default, BC): [ id => int, url => string, alt => string ]
				 *   'url'                : string   (URL or "{{theme_url}}/..." token)
				 *   'id'                 : integer (attachment ID)
				 *
				 * Inbound POST is ALWAYS the array form (see template). The
				 * sanitizer normalizes the inbound shape, then re-shapes it to
				 * match return_format.
				 *
				 * Portable tokens (containing "{{") survive verbatim — they are
				 * NOT routed through esc_url_raw() because the curly braces
				 * would be stripped, breaking domain portability.
				 */
				$rf = isset( $field['return_format'] ) ? (string) $field['return_format'] : 'array';
				if ( ! in_array( $rf, array( 'array', 'url', 'id' ), true ) ) {
					$rf = 'array';
				}

				// 1) Pull the inbound value into a normalized internal form.
				$in_url = '';
				$in_alt = '';
				$in_id  = 0;
				if ( is_array( $value ) ) {
					$in_url = isset( $value['url'] ) ? wp_unslash( (string) $value['url'] ) : '';
					$in_alt = isset( $value['alt'] ) ? wp_unslash( (string) $value['alt'] ) : '';
					$in_id  = isset( $value['id'] )  ? absint( $value['id'] ) : 0;
				} else {
					$raw = wp_unslash( (string) $value );
					if ( '' !== $raw ) {
						if ( false !== strpos( $raw, '{{' ) ) {
							$in_url = $raw;                          // token
						} elseif ( filter_var( $raw, FILTER_VALIDATE_URL ) ) {
							$in_url = $raw;                          // plain URL
						} elseif ( absint( $raw ) ) {
							$in_id  = absint( $raw );                // attachment ID
							$in_url = (string) wp_get_attachment_url( $in_id );
							$in_alt = (string) get_post_meta( $in_id, '_wp_attachment_image_alt', true );
						}
					}
				}

				// 2) Sanitize each part. Tokens are kept verbatim.
				$is_token  = ( '' !== $in_url && false !== strpos( $in_url, '{{' ) );
				$clean_url = $is_token ? sanitize_text_field( $in_url ) : esc_url_raw( $in_url );
				$clean_alt = sanitize_text_field( $in_alt );
				$clean_id  = (int) $in_id;

				// 3) Re-shape per return_format.
				if ( 'url' === $rf ) {
					return $clean_url;
				}
				if ( 'id' === $rf ) {
					// If user pasted a URL but ID is required, try a reverse lookup.
					if ( ! $clean_id && '' !== $clean_url && ! $is_token ) {
						$resolved_id = (int) attachment_url_to_postid( $clean_url );
						if ( $resolved_id ) $clean_id = $resolved_id;
					}
					return $clean_id ? $clean_id : '';
				}
				// 'array' — extends the legacy { url, alt } with id (additive, BC-safe).
				return array(
					'id'  => $clean_id,
					'url' => $clean_url,
					'alt' => $clean_alt,
				);

			case 'post_select':
				// Value is a post ID (integer string) or a slug (safe text).
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', array_map( 'wp_unslash', $value ) );
				}
				return sanitize_text_field( wp_unslash( (string) $value ) );

			case 'gallery':
				$value = sanitize_text_field( wp_unslash( (string) $value ) );
				$ids   = array_filter( array_map( 'absint', explode( ',', $value ) ) );
				return implode( ',', $ids );

			case 'editor':
				if ( null === $value ) return '';
				return wp_kses_post( wp_unslash( (string) $value ) );

			case 'multicolor':
				if ( ! is_array( $value ) ) return array();
				$clean = array();
				foreach ( $value as $k => $v ) {
					$color = sanitize_text_field( wp_unslash( (string) $v ) );
					if ( self::is_valid_color( $color ) ) {
						$clean[ sanitize_key( $k ) ] = $color;
					}
				}
				return $clean;

			case 'multicheck':
				if ( ! is_array( $value ) ) return array();
				return array_values( array_map( 'sanitize_text_field', array_map( 'wp_unslash', $value ) ) );

			case 'code':
				// Raw code: only unslash, no HTML stripping.
				return wp_unslash( (string) $value );

			case 'radio_buttonset':
				return sanitize_text_field( wp_unslash( (string) $value ) );

			case 'repeater':
				/*
				 * Stored as a JSON array of row objects. The new format is
				 * fully nested — complex sub-fields (color_gradient, dimension,
				 * spacing, multicolor, multicheck, typography, etc.) are saved
				 * as proper arrays inside each row instead of as JSON-string
				 * blobs, so consuming code can read everything in a single
				 * decode pass:
				 *
				 *   $rows = json_decode( get_theme_mod( 'my_repeater' ), true );
				 *   echo $rows[0]['gradient_field']['css'];
				 *
				 * Also normalises legacy data: any scalar value that looks like
				 * a JSON object/array is decoded on save, so the next read is
				 * already in the new shape.
				 */
				// IMPORTANT: do NOT wp_unslash() the JSON string before
				// json_decode. The AJAX handler already unslashed the form
				// payload once before parse_str(); a second wp_unslash here
				// would strip legitimate JSON-escape backslashes (e.g. \"
				// inside a nested-JSON sub-field value), corrupting the
				// payload and causing the save to fall through to '[]'.
				$raw = is_string( $value ) ? json_decode( $value, true ) : $value;
				if ( ! is_array( $raw ) ) return '[]';

				// Build a sub-field-id → sub-field-def map so per-type
				// reshape (e.g. image return_format) can run inside rows.
				$sf_map = array();
				if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
					foreach ( $field['fields'] as $sf ) {
						if ( ! is_array( $sf ) || empty( $sf['id'] ) ) continue;
						$sf_map[ $sf['id'] ] = $sf;
					}
				}

				$clean = array();
				foreach ( $raw as $row ) {
					if ( ! is_array( $row ) ) continue;
					$clean_row = array();
					foreach ( $row as $k => $v ) {
						$key = sanitize_key( $k );
						if ( '' === $key ) continue;

						$sf_def  = isset( $sf_map[ $key ] ) ? $sf_map[ $key ] : null;
						$sf_type = $sf_def ? ( $sf_def['type'] ?? '' ) : '';

						// Image sub-field — reshape per return_format so themes
						// reading $row['my_image'] get the developer's choice
						// (array | id | url) directly, just like top-level.
						if ( 'image' === $sf_type ) {
							$clean_row[ $key ] = self::sanitize( $sf_def, $v );
							continue;
						}

						// Already a nested structure — recurse.
						if ( is_array( $v ) ) {
							$clean_row[ $key ] = self::sanitize_repeater_value( $v );
							continue;
						}

						// Backwards-compat: legacy rows where complex sub-fields
						// shipped as JSON strings still need decoding. New rows
						// arrive as nested objects (parsed in collectRow) and
						// hit the is_array branch above.
						if ( is_string( $v ) && strlen( $v ) > 1 ) {
							$first = $v[0];
							if ( '{' === $first || '[' === $first ) {
								$decoded = json_decode( $v, true );
								if ( is_array( $decoded ) ) {
									$clean_row[ $key ] = self::sanitize_repeater_value( $decoded );
									continue;
								}
							}
						}

						$clean_row[ $key ] = sanitize_text_field( wp_unslash( (string) $v ) );
					}
					$clean[] = $clean_row;
				}
				return wp_json_encode( $clean );

			case 'color_gradient':
				// See repeater note above re: not double-unslashing JSON.
				$raw = is_string( $value ) ? json_decode( $value, true ) : $value;
				if ( ! is_array( $raw ) ) return '';
				$stops = array();
				foreach ( (array) ( $raw['stops'] ?? array() ) as $s ) {
					if ( ! is_array( $s ) ) continue;
					$stops[] = array(
						'color' => sanitize_text_field( wp_unslash( (string) ( $s['color'] ?? '' ) ) ),
						'pos'   => min( 100, max( 0, (int) ( $s['pos'] ?? 0 ) ) ),
					);
				}
				return wp_json_encode( array(
					'type'  => in_array( $raw['type'] ?? 'linear', array( 'linear', 'radial' ), true ) ? $raw['type'] : 'linear',
					'angle' => min( 360, max( 0, (int) ( $raw['angle'] ?? 135 ) ) ),
					'stops' => $stops,
					'css'   => sanitize_text_field( wp_unslash( (string) ( $raw['css'] ?? '' ) ) ),
				) );

			case 'spacing':
				if ( ! is_array( $value ) ) return array();
				$units = array( 'px', 'em', 'rem', '%', 'vh', 'vw', 'pt', 'cm', 'mm' );
				$clean = array();
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					$v = sanitize_text_field( wp_unslash( (string) ( $value[ $side ] ?? '' ) ) );
					$clean[ $side ] = preg_match( '/^-?[\d.]+$/', $v ) ? $v : '0';
				}
				$clean['unit'] = in_array( $value['unit'] ?? 'px', $units, true ) ? $value['unit'] : 'px';
				// CSS Target opt-in: preserve the end-user-typed selector so
				// the CSS emitter on wp_head can look it up at render time.
				// sanitize_text_field is safe for CSS selectors ('.foo > .bar',
				// '[data-x]', etc.) — it strips newlines and tags only.
				// Only stored when non-empty so legacy fields stay shape-
				// identical and reads without a selector return no key.
				if ( isset( $value['selector'] ) ) {
					$sel = sanitize_text_field( wp_unslash( (string) $value['selector'] ) );
					if ( '' !== $sel ) {
						$clean['selector'] = $sel;
					}
				}
				return $clean;

			case 'radio_image':
				return sanitize_text_field( wp_unslash( (string) $value ) );

			case 'dimension':
				$value = sanitize_text_field( wp_unslash( (string) $value ) );
				// Allow values like "150px", "2.5em", "100%", "50vh".
				return preg_match( '/^-?[\d.]+\s*(%|px|em|rem|vw|vh|pt|cm|mm|ex|ch)?$/i', trim( $value ) )
					? trim( $value ) : '';

			case 'typography':
				if ( ! is_array( $value ) ) return array();
				$allowed = array(
					'font_source', 'font_family', 'font_url',
					'font_size', 'font_size_unit', 'font_weight',
					'font_style', 'line_height', 'letter_spacing',
					'text_transform', 'color', 'selectors',
				);
				$clean = array();
				foreach ( $allowed as $k ) {
					if ( ! isset( $value[ $k ] ) ) continue;
					if ( 'font_url' === $k ) {
						$clean[ $k ] = esc_url_raw( wp_unslash( (string) $value[ $k ] ) );
					} elseif ( 'font_source' === $k ) {
						$src = sanitize_key( $value[ $k ] );
						$clean[ $k ] = in_array( $src, array( 'google', 'custom', 'system' ), true ) ? $src : 'google';
					} elseif ( 'font_style' === $k ) {
						$clean[ $k ] = in_array( $value[ $k ], array( 'normal', 'italic' ), true ) ? $value[ $k ] : 'normal';
					} elseif ( 'text_transform' === $k ) {
						$clean[ $k ] = in_array( $value[ $k ], array( 'none', 'uppercase', 'lowercase', 'capitalize' ), true ) ? $value[ $k ] : 'none';
					} elseif ( 'font_size_unit' === $k ) {
						$clean[ $k ] = in_array( $value[ $k ], array( 'px', 'em', 'rem', 'vw', '%' ), true ) ? $value[ $k ] : 'px';
					} elseif ( 'selectors' === $k ) {
						// Allow CSS selector characters; strip anything that looks like injection.
						$raw   = wp_unslash( (string) $value[ $k ] );
						$parts = array_map( 'trim', explode( ',', $raw ) );
						$safe  = array();
						foreach ( $parts as $part ) {
							// Keep only selector-safe chars: alphanum, space, .-_#:[]=>+~*()^$|"'
							$part = preg_replace( '/[^a-zA-Z0-9\s\.\-_#:\[\]=>\+\~\*\(\)\^\$\|"\'%]/', '', $part );
							$part = trim( $part );
							if ( '' !== $part ) {
								$safe[] = $part;
							}
						}
						$clean[ $k ] = implode( ', ', $safe );
					} else {
						$clean[ $k ] = sanitize_text_field( wp_unslash( (string) $value[ $k ] ) );
					}
				}
				return $clean;

			case 'text':
			case 'tabs':
			case 'datepicker':
			case 'select_posts':
			default:
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', array_map( 'wp_unslash', $value ) );
				}
				return sanitize_text_field( wp_unslash( (string) $value ) );
		}
	}

	/**
	 * Recursively sanitize a value that lives inside a repeater row.
	 * Used for complex sub-fields (color_gradient, dimension, spacing,
	 * typography, multicolor, multicheck, etc.) whose value is an array
	 * structure rather than a flat string. Numeric keys are preserved
	 * (e.g. for gradient stops); string keys are sanitised as keys.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	protected static function sanitize_repeater_value( $data ) {
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				$key       = is_int( $k ) ? $k : sanitize_key( (string) $k );
				$out[ $key ] = self::sanitize_repeater_value( $v );
			}
			return $out;
		}
		if ( is_bool( $data ) || is_int( $data ) || is_float( $data ) ) {
			return $data;
		}
		if ( is_string( $data ) ) {
			return sanitize_text_field( wp_unslash( $data ) );
		}
		return '';
	}
}

/**
 * Convenience helper: read a repeater field's saved rows as a fully-decoded
 * nested array.
 *
 * Returns an indexed array of rows, where each row is an associative array
 * of sub_field_id => value. Complex sub-field values (color_gradient,
 * dimension, etc.) come back as nested arrays — no second decode required.
 *
 * Handles both the new nested storage format and the legacy JSON-string-in-
 * JSON format, so existing data keeps working until it gets re-saved.
 *
 *   $rows = tpmeta_get_repeater_rows( 'my_repeater' );
 *   foreach ( $rows as $row ) {
 *       echo esc_html( $row['title'] );
 *       echo '<div style="background:' . esc_attr( $row['gradient']['css'] ) . '"></div>';
 *   }
 *
 * @param string $field_id
 * @param array  $default
 * @return array
 */
function tpmeta_get_repeater_rows( $field_id, $default = array() ) {
	$value = get_theme_mod( $field_id, '' );
	$rows  = is_array( $value ) ? $value : json_decode( (string) $value, true );
	if ( ! is_array( $rows ) ) return $default;

	$out = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) continue;
		$row_out = array();
		foreach ( $row as $k => $v ) {
			// Auto-decode legacy JSON-string sub-field values for backward compat.
			if ( is_string( $v ) && strlen( $v ) > 1 ) {
				$first = $v[0];
				if ( '{' === $first || '[' === $first ) {
					$decoded = json_decode( $v, true );
					if ( is_array( $decoded ) ) {
						$row_out[ $k ] = $decoded;
						continue;
					}
				}
			}
			$row_out[ $k ] = $v;
		}
		$out[] = $row_out;
	}
	return $out;
}
