<?php
/**
 * TPMeta_Options_Output
 *
 * Auto-generates frontend CSS and loads fonts with ZERO developer config.
 *
 * Typography fields:
 *   - Google fonts are enqueued automatically for every typography field
 *     that has a font_family saved, regardless of the panel's output_css flag.
 *   - Custom uploaded fonts get an @font-face rule automatically.
 *   - When the user has filled in the "Apply To" selectors, a complete CSS
 *     block is injected into wp_head — no PHP or field config needed.
 *
 * Other field types (colorpicker, text, …):
 *   - Still require 'output_css' => true on the panel AND an 'output' key
 *     on the field definition (existing behaviour, unchanged).
 *
 * @package    tpmeta
 * @subpackage tpmeta/options
 * @since      1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Options_Output {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'enqueue_fonts' ), 5  );
		add_action( 'wp_head', array( $this, 'print_css' ),    99  );
	}

	// -------------------------------------------------------------------------
	// Font loading — runs for EVERY panel, no output_css flag required
	// -------------------------------------------------------------------------

	/**
	 * Collect every typography field across all panels, enqueue Google Fonts in
	 * one combined request, and inject @font-face for custom uploads.
	 */
	public function enqueue_fonts() {
		$google_families = array(); // family => true
		$face_css        = '';

		foreach ( array_keys( TPMeta_Options::get_panels() ) as $opt_name ) {
			foreach ( $this->collect_typography_values( $opt_name ) as $entry ) {
				$val = $entry['val'];
				if ( empty( $val['font_family'] ) ) {
					continue;
				}

				$source = isset( $val['font_source'] ) ? $val['font_source'] : 'google';
				$family = trim( $val['font_family'] );

				if ( 'google' === $source && '' !== $family ) {
					$google_families[ $family ] = true;

				} elseif ( 'custom' === $source && ! empty( $val['font_url'] ) ) {
					$ext    = strtolower( pathinfo( $val['font_url'], PATHINFO_EXTENSION ) );
					$format = 'woff2' === $ext ? 'woff2' : ( 'woff' === $ext ? 'woff' : 'truetype' );
					$face_css .=
						"@font-face {\n"
						. "  font-family: '" . esc_attr( $family ) . "';\n"
						. "  src: url('" . esc_url( $val['font_url'] ) . "') format('" . esc_attr( $format ) . "');\n"
						. "  font-display: swap;\n"
						. "}\n";
				}
			}
		}

		// One combined Google Fonts request for all families
		if ( ! empty( $google_families ) ) {
			$frag = implode( '&', array_map(
				function ( $f ) {
					return 'family=' . rawurlencode( $f ) . ':ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700';
				},
				array_keys( $google_families )
			) );
			$url = 'https://fonts.googleapis.com/css2?' . $frag . '&display=swap';

			echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";                   // phpcs:ignore
			echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";          // phpcs:ignore
			echo '<link rel="stylesheet" href="' . esc_url( $url ) . '">' . "\n";                       // phpcs:ignore
		}

		if ( '' !== $face_css ) {
			echo "<style id=\"tpmeta-custom-fonts\">\n" . $face_css . "</style>\n"; // phpcs:ignore
		}
	}

	// -------------------------------------------------------------------------
	// CSS output
	// -------------------------------------------------------------------------

	public function print_css() {
		$css = '';

		foreach ( TPMeta_Options::get_panels() as $opt_name => $args ) {
			$css .= $this->typography_css_for_panel( $opt_name );

			// CSS Target opt-in is per-field, not per-panel — runs regardless
			// of the panel's output_css flag (mirrors typography behaviour).
			$css .= $this->css_target_css_for_panel( $opt_name );

			// Per-field opt-in: the 'output' key on a field is the gate,
			// not the panel-level output_css flag (mirrors typography/css_target).
			$css .= $this->generic_css_for_panel( $opt_name );

			// Repeater color sub-fields that have css_output defined.
			$css .= $this->repeater_color_css_for_panel( $opt_name );
		}

		$css = trim( $css );
		if ( '' === $css ) {
			return;
		}

		echo "\n<style id=\"tpmeta-options-output\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// -------------------------------------------------------------------------
	// Internals
	// -------------------------------------------------------------------------

	/**
	 * Build CSS for every spacing field in $opt_name that has css_target
	 * enabled and a non-empty saved selector. Mirrors typography in that
	 * it runs unconditionally (no panel output_css flag required) — the
	 * css_target opt-in lives on the field definition.
	 *
	 * @param  string $opt_name
	 * @return string
	 */
	protected function css_target_css_for_panel( $opt_name ) {
		if ( ! class_exists( 'TPMeta_CSS_Target' ) ) {
			return '';
		}
		$css = '';
		foreach ( TPMeta_Options::get_fields( $opt_name ) as $field ) {
			if ( empty( $field['type'] ) || 'spacing' !== $field['type'] ) {
				continue;
			}
			if ( empty( $field['css_target']['enabled'] ) ) {
				continue;
			}
			$value = TPMeta_Options_Store::value( $field );
			$css  .= TPMeta_CSS_Target::build_spacing_rule( $field, $value );
		}
		return $css;
	}

	/**
	 * Build CSS for every typography field in $opt_name that has selectors.
	 * No output_css panel flag required.
	 *
	 * @param  string $opt_name
	 * @return string
	 */
	protected function typography_css_for_panel( $opt_name ) {
		$css = '';
		foreach ( $this->collect_typography_values( $opt_name ) as $entry ) {
			$css .= $this->build_typography_css_value( $entry['val'], $entry['field'] );
		}
		return $css;
	}

	/**
	 * Collect every typography VALUE in a panel — top-level fields AND
	 * typography sub-fields nested inside repeater rows. Returns a flat list of
	 * [ 'val' => <typography value array>, 'field' => <field/sub-field def> ]
	 * so the font-loader and CSS builder can treat both the same way.
	 *
	 * Repeater rows are read via tpmeta_get_repeater_rows(), which returns the
	 * fully-decoded nested arrays — so $row[ $sub_id ] is already the typography
	 * value array (font_source, font_family, selectors, …).
	 *
	 * @param  string $opt_name
	 * @return array
	 */
	protected function collect_typography_values( $opt_name ) {
		$out = array();

		foreach ( TPMeta_Options::get_fields( $opt_name ) as $field ) {
			$type = isset( $field['type'] ) ? $field['type'] : '';

			if ( 'typography' === $type ) {
				$val = TPMeta_Options_Store::value( $field );
				if ( is_array( $val ) ) {
					$out[] = array( 'val' => $val, 'field' => $field );
				}
				continue;
			}

			if ( 'repeater' !== $type || empty( $field['id'] ) ) {
				continue;
			}

			// Find typography sub-fields of this repeater.
			$subs = ! empty( $field['fields'] ) ? (array) $field['fields']
				: ( ! empty( $field['sub_fields'] ) ? (array) $field['sub_fields'] : array() );
			$typo_subs = array();
			foreach ( $subs as $sf ) {
				if ( ! empty( $sf['id'] ) && ! empty( $sf['type'] ) && 'typography' === $sf['type'] ) {
					$typo_subs[ $sf['id'] ] = $sf;
				}
			}
			if ( empty( $typo_subs ) || ! function_exists( 'tpmeta_get_repeater_rows' ) ) {
				continue;
			}

			foreach ( tpmeta_get_repeater_rows( $field['id'] ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				foreach ( $typo_subs as $sub_id => $sf ) {
					if ( ! empty( $row[ $sub_id ] ) && is_array( $row[ $sub_id ] ) ) {
						$out[] = array( 'val' => $row[ $sub_id ], 'field' => $sf );
					}
				}
			}
		}

		return $out;
	}

	/**
	 * Build CSS for non-typography fields that have an 'output' key.
	 * Only called when the panel has output_css => true.
	 *
	 * @param  string $opt_name
	 * @return string
	 */
	protected function generic_css_for_panel( $opt_name ) {
		$css = '';
		foreach ( TPMeta_Options::get_fields( $opt_name ) as $field ) {
			if ( 'typography' === $field['type'] ) {
				continue; // handled separately above
			}
			if ( empty( $field['output'] ) || ! is_array( $field['output'] ) ) {
				continue;
			}
			$value = TPMeta_Options_Store::value( $field );
			if ( 'spacing' === $field['type'] && is_array( $value ) ) {
				$value = self::build_spacing_value( $value );
			}
			if ( '' === $value || null === $value || is_array( $value ) ) {
				continue;
			}
			foreach ( $field['output'] as $key => $rule ) {
				// Builder saves [{selector,property,important}]; hand-coded uses selector=>property assoc.
				if ( is_array( $rule ) ) {
					$selector  = isset( $rule['selector'] ) ? $rule['selector'] : '';
					$property  = isset( $rule['property'] ) ? $rule['property'] : '';
					$important = ! empty( $rule['important'] );
				} else {
					$selector  = $key;
					$property  = $rule;
					$important = false;
				}
				$selector = trim( wp_strip_all_tags( (string) $selector ) );
				$property = trim( wp_strip_all_tags( (string) $property ) );
				if ( '' === $selector || '' === $property ) {
					continue;
				}
				$suffix = $important ? ' !important' : '';
				$css .= sprintf( "%s { %s: %s%s; }\n", $selector, $property, esc_html( $value ), $suffix );
			}
		}
		return $css;
	}

	/**
	 * Build the CSS block for one typography field.
	 *
	 * Selectors are read from the value['selectors'] key set by the Apply To UI.
	 * A field-level string 'output' key is also accepted for backwards compat.
	 * Returns '' when no selectors are configured.
	 *
	 * @param  array  $field
	 * @return string
	 */
	protected function build_typography_css( array $field ) {
		$val = TPMeta_Options_Store::value( $field );
		return is_array( $val ) ? $this->build_typography_css_value( $val, $field ) : '';
	}

	/**
	 * Build the CSS block for one typography VALUE array. Takes the already
	 * resolved value (theme_mod for top-level fields, decoded row for repeater
	 * sub-fields) rather than reading storage, so both paths share this logic.
	 * $field is used only for the legacy string 'output' selector fallback.
	 *
	 * @param  array $val   Typography value (font_source, font_family, selectors, …).
	 * @param  array $field Optional field/sub-field def for the 'output' fallback.
	 * @return string
	 */
	protected function build_typography_css_value( array $val, array $field = array() ) {
		// Primary: selectors saved by the Apply To UI
		$selectors = isset( $val['selectors'] ) ? trim( $val['selectors'] ) : '';

		// Fallback: field-definition 'output' key (string form only)
		if ( '' === $selectors && ! empty( $field['output'] ) && is_string( $field['output'] ) ) {
			$selectors = trim( $field['output'] );
		}

		if ( '' === $selectors ) {
			return '';
		}

		$props  = array();
		$source = isset( $val['font_source'] ) ? $val['font_source'] : 'google';

		// font-family
		$family = isset( $val['font_family'] ) ? trim( $val['font_family'] ) : '';
		if ( '' !== $family ) {
			$q        = strpos( $family, ' ' ) !== false ? "'{$family}'" : $family;
			$fallback = 'custom' === $source ? 'sans-serif'
				: ( false !== stripos( $family, 'serif' ) ? 'serif' : 'sans-serif' );
			$props[]  = "font-family: {$q}, {$fallback}";
		}

		// font-size
		$size = isset( $val['font_size'] ) ? trim( $val['font_size'] ) : '';
		if ( '' !== $size ) {
			$unit    = isset( $val['font_size_unit'] ) ? $val['font_size_unit'] : 'px';
			$props[] = is_numeric( $size )
				? "font-size: {$size}{$unit}"
				: "font-size: {$size}";
		}

		// font-weight
		$weight = isset( $val['font_weight'] ) ? trim( $val['font_weight'] ) : '';
		if ( '' !== $weight ) {
			$props[] = "font-weight: {$weight}";
		}

		// font-style
		$style = isset( $val['font_style'] ) ? trim( $val['font_style'] ) : '';
		if ( '' !== $style && 'normal' !== $style ) {
			$props[] = "font-style: {$style}";
		}

		// line-height
		$lh = isset( $val['line_height'] ) ? trim( $val['line_height'] ) : '';
		if ( '' !== $lh ) {
			$props[] = "line-height: {$lh}";
		}

		// letter-spacing
		$ls = isset( $val['letter_spacing'] ) ? trim( $val['letter_spacing'] ) : '';
		if ( '' !== $ls && '0' !== $ls && '0px' !== $ls ) {
			$props[] = "letter-spacing: {$ls}";
		}

		// text-transform
		$tt = isset( $val['text_transform'] ) ? trim( $val['text_transform'] ) : '';
		if ( '' !== $tt && 'none' !== $tt ) {
			$props[] = "text-transform: {$tt}";
		}

		// color
		$color = isset( $val['color'] ) ? trim( $val['color'] ) : '';
		if ( '' !== $color ) {
			$props[] = "color: {$color}";
		}

		if ( empty( $props ) ) {
			return '';
		}

		return $selectors . " {\n  " . implode( ";\n  ", $props ) . ";\n}\n";
	}

	/**
	 * Build frontend CSS for colorpicker and color_gradient sub-fields inside
	 * repeater fields that have a css_output definition saved by the builder.
	 *
	 * Each sub-field definition stores:
	 *   css_output: { selector: ".my-el", property: "background-color" }
	 *
	 * Supports {n} as a 1-based row-index placeholder in selector/property,
	 * e.g. selector ".card:nth-child({n})" becomes ".card:nth-child(1)" for
	 * the first row, ".card:nth-child(2)" for the second, etc.
	 *
	 * @param  string $opt_name
	 * @return string
	 */
	protected function repeater_color_css_for_panel( $opt_name ) {
		if ( ! function_exists( 'tpmeta_get_repeater_rows' ) ) {
			return '';
		}
		$css = '';
		foreach ( TPMeta_Options::get_fields( $opt_name ) as $field ) {
			if ( empty( $field['type'] ) || 'repeater' !== $field['type'] || empty( $field['id'] ) ) {
				continue;
			}
			$subs = ! empty( $field['fields'] ) ? (array) $field['fields']
				: ( ! empty( $field['sub_fields'] ) ? (array) $field['sub_fields'] : array() );

			// Collect color sub-fields that carry a css_output definition.
			$color_subs = array();
			foreach ( $subs as $sf ) {
				if ( empty( $sf['id'] ) || empty( $sf['type'] ) || empty( $sf['css_output'] ) ) {
					continue;
				}
				if ( 'colorpicker' !== $sf['type'] && 'color_gradient' !== $sf['type'] ) {
					continue;
				}
				$selector = isset( $sf['css_output']['selector'] ) ? trim( (string) $sf['css_output']['selector'] ) : '';
				$property = isset( $sf['css_output']['property'] ) ? trim( (string) $sf['css_output']['property'] ) : '';
				if ( '' === $selector || '' === $property ) {
					continue;
				}
				$color_subs[ $sf['id'] ] = array(
					'type'     => $sf['type'],
					'selector' => $selector,
					'property' => $property,
				);
			}

			if ( empty( $color_subs ) ) {
				continue;
			}

			$rows = tpmeta_get_repeater_rows( $field['id'] );
			if ( empty( $rows ) || ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $idx => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$n = $idx + 1; // 1-based index for {n} placeholder

				foreach ( $color_subs as $sub_id => $sub ) {
					if ( ! isset( $row[ $sub_id ] ) || '' === $row[ $sub_id ] ) {
						continue;
					}
					$val      = $row[ $sub_id ];
					$selector = str_replace( '{n}', $n, $sub['selector'] );
					$property = str_replace( '{n}', $n, $sub['property'] );
					$selector = trim( wp_strip_all_tags( $selector ) );
					$property = trim( wp_strip_all_tags( $property ) );

					if ( 'color_gradient' === $sub['type'] ) {
						$grad = is_array( $val ) ? $val : json_decode( $val, true );
						if ( ! is_array( $grad ) || empty( $grad['css'] ) ) {
							continue;
						}
						$css_val = sanitize_text_field( $grad['css'] );
					} else {
						// colorpicker — hex/rgb/rgba.
						$css_val = sanitize_hex_color( $val );
						if ( ! $css_val ) {
							$css_val = sanitize_text_field( $val ); // rgba / named colour fallback
						}
					}

					if ( '' === $css_val ) {
						continue;
					}

					$css .= sprintf( "%s { %s: %s; }\n", $selector, $property, $css_val );
				}
			}
		}
		return $css;
	}

	/**
	 * Convert a spacing array value {top,right,bottom,left,unit} to a CSS
	 * shorthand string, e.g. "10px 20px 15px 20px". Mirrors the logic in
	 * TPMeta_CSS_Target::build_spacing_rule() so both output paths stay in sync.
	 *
	 * @param  array $val Saved spacing value.
	 * @return string CSS shorthand or '' when the array is unrecognisable.
	 */
	private static function build_spacing_value( $val ) {
		if ( ! is_array( $val ) ) {
			return '';
		}
		$allowed_units = array( 'px', 'em', 'rem', '%', 'vh', 'vw', 'pt', 'cm', 'mm' );
		$unit  = isset( $val['unit'] ) && in_array( $val['unit'], $allowed_units, true ) ? $val['unit'] : 'px';
		$sides = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$v       = isset( $val[ $side ] ) ? trim( (string) $val[ $side ] ) : '';
			$sides[] = preg_match( '/^-?[\d.]+$/', $v ) ? $v . $unit : '0' . $unit;
		}
		$result = implode( ' ', $sides );
		// All-zero shorthand for any unit → unitless '0' (valid CSS for all length properties).
		// Previously returned '' which caused the downstream empty-value guard to suppress the rule,
		// meaning an explicit "padding: 0" could never be output.
		$all_zero = ! array_filter( explode( ' ', $result ), function ( $part ) { return (float) $part !== 0.0; } );
		return $all_zero ? '0' : $result;
	}
}
