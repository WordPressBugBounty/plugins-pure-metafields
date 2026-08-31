<?php
/**
 * TPMeta_Metabox_Generic_Output
 *
 * Frontend CSS emitter for post-metabox fields that declare an 'output' rule
 * array in their field definition (colorpicker, dimension, spacing, etc.).
 *
 * Mirrors TPMeta_Options_Output::generic_css_for_panel() but reads values
 * from post_meta instead of theme_mods. Scoped to singular pages only so
 * archive / listing pages pay zero overhead.
 *
 * @package    tpmeta
 * @subpackage tpmeta/includes
 * @since      1.7.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Metabox_Generic_Output {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'print_css' ), 99 );
	}

	public function print_css() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return;
		}

		$metaboxes = apply_filters( 'tp_meta_boxes', array() );
		if ( empty( $metaboxes ) || ! is_array( $metaboxes ) ) {
			return;
		}

		$css = '';
		foreach ( $metaboxes as $box ) {
			$pt = isset( $box['post_type'] ) ? (array) $box['post_type'] : array();
			// Empty post_type means no post-type restriction — match all.
			if ( ! empty( $pt ) && ! in_array( $post_type, $pt, true ) ) {
				continue;
			}
			$fields = isset( $box['fields'] ) ? (array) $box['fields'] : array();
			foreach ( $fields as $field ) {
				if ( empty( $field['type'] ) || empty( $field['id'] ) ) {
					continue;
				}
				// Typography needs its own emitter (fonts + multi-property block).
				if ( 'typography' === $field['type'] ) {
					continue;
				}
				if ( empty( $field['output'] ) || ! is_array( $field['output'] ) ) {
					continue;
				}
				$value = get_post_meta( $post_id, $field['id'], true );
				// Spacing arrays: convert {top,right,bottom,left,unit} → shorthand string.
				if ( 'spacing' === $field['type'] && is_array( $value ) ) {
					$value = self::spacing_shorthand( $value );
				}
				// Skip empty or still-array values (color_gradient, multicolor, etc.).
				if ( '' === $value || null === $value || is_array( $value ) ) {
					continue;
				}
				foreach ( $field['output'] as $key => $rule ) {
					// Builder saves [{selector,property,important}]; hand-coded uses [selector => property].
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
		}

		$css = trim( $css );
		if ( '' === $css ) {
			return;
		}

		echo "\n<style id=\"tpmeta-metabox-generic-output\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Convert a spacing array {top,right,bottom,left,unit} to a CSS shorthand
	 * string. Mirrors TPMeta_Options_Output::build_spacing_value().
	 */
	private static function spacing_shorthand( $val ) {
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
		$result   = implode( ' ', $sides );
		$all_zero = ! array_filter( explode( ' ', $result ), function ( $p ) { return (float) $p !== 0.0; } );
		return $all_zero ? '0' : $result;
	}
}
