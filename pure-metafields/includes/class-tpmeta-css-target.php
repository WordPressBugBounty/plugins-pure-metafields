<?php
/**
 * TPMeta_CSS_Target
 *
 * Helpers + frontend emitter for the per-field CSS Target opt-in. When a
 * spacing field has `css_target.enabled = true` in its definition and the
 * end user has typed a non-empty selector into the field UI, the plugin
 * auto-injects a CSS rule on wp_head:99.
 *
 *   - Options panels:  emission runs alongside TPMeta_Options_Output's
 *                      <style id="tpmeta-options-output"> tag, regardless
 *                      of the panel's output_css flag (the css_target
 *                      opt-in is per-field, not per-panel).
 *   - Metabox fields:  emission runs in a separate <style> tag, scoped to
 *                      the singular page for the post that owns the meta
 *                      value (is_singular() + get_queried_object_id()).
 *
 * Storage / read contracts are unchanged. `tpmeta_field()`, `tpmeta_get_option()`
 * and `get_theme_mod()` still return the full spacing array including the
 * `selector` sub-key — themes that read those keys directly keep working.
 *
 * @package    tpmeta
 * @subpackage tpmeta/includes
 * @since      1.x.x
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_CSS_Target {

	/**
	 * Convert a single spacing field's saved value + css_target config into
	 * a single CSS rule. Returns '' when the feature is disabled, the
	 * selector is empty, or the value isn't a recognisable spacing array.
	 *
	 * Output shape:
	 *     <selector> { <property>: <T><unit> <R><unit> <B><unit> <L><unit>; }\n
	 *
	 * Defensive sanitisation: the selector is stripped of {, }, ; and HTML
	 * tags so a saved value can't break out of the rule. The property name
	 * is restricted to [a-zA-Z\-] so a malformed property can't inject CSS
	 * either. Both checks are belt-and-braces — selector / property are
	 * already sanitised on save — but the emit path runs on every frontend
	 * request, so we defend at the boundary too.
	 *
	 * @param  array $field Field def. Must contain css_target => [enabled, property].
	 * @param  mixed $value Saved value. Expected: array with top/right/bottom/left/unit/selector.
	 * @return string CSS rule (with trailing \n) or empty string.
	 */
	public static function build_spacing_rule( $field, $value ) {
		if ( ! is_array( $field ) || empty( $field['css_target'] ) || ! is_array( $field['css_target'] ) ) {
			return '';
		}
		if ( empty( $field['css_target']['enabled'] ) ) {
			return '';
		}
		if ( ! is_array( $value ) ) {
			return '';
		}

		// Selector is now configured in the builder modal at field-def
		// time (field.css_target.selector). Fall back to $value['selector']
		// for legacy data saved while the runtime input still existed.
		$selector = isset( $field['css_target']['selector'] ) && '' !== trim( (string) $field['css_target']['selector'] )
			? (string) $field['css_target']['selector']
			: ( isset( $value['selector'] ) ? (string) $value['selector'] : '' );
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return '';
		}
		$selector = wp_strip_all_tags( $selector );
		$selector = str_replace( array( '{', '}', ';' ), '', $selector );
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return '';
		}

		$property = isset( $field['css_target']['property'] ) && '' !== (string) $field['css_target']['property']
			? (string) $field['css_target']['property']
			: 'padding';
		$property = preg_replace( '/[^a-zA-Z\-]/', '', $property );
		if ( '' === $property ) {
			return '';
		}

		$allowed_units = array( 'px', 'em', 'rem', '%', 'vh', 'vw', 'pt', 'cm', 'mm' );
		$unit          = isset( $value['unit'] ) && in_array( $value['unit'], $allowed_units, true )
			? $value['unit']
			: 'px';

		$sides = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$v = isset( $value[ $side ] ) ? trim( (string) $value[ $side ] ) : '';
			if ( '' === $v || ! preg_match( '/^-?[\d.]+$/', $v ) ) {
				$v = '0';
			}
			$sides[] = $v . $unit;
		}

		$suffix = ! empty( $field['css_target']['important'] ) ? ' !important' : '';
		return sprintf( "%s { %s: %s%s; }\n", $selector, $property, implode( ' ', $sides ), $suffix );
	}
}

/**
 * Frontend emitter for metabox-side spacing fields with css_target enabled.
 *
 * Hooks wp_head:99. Early-returns on non-singular requests so listing pages
 * (archives, search, home) pay zero overhead. On singular pages, walks the
 * `tp_meta_boxes` filter, finds spacing fields with css_target.enabled,
 * looks up the saved post_meta for the queried post, and emits a single
 * <style> tag containing all matching rules.
 *
 * Spacing is currently an Options-only field type — there's no metabox-side
 * spacing template and `spacing` isn't in the Metafield Builder catalog —
 * but the emitter is plumbing for when those land. It is also a forward
 * compat path for developers who register spacing manually via the
 * `tp_meta_boxes` filter.
 */
class TPMeta_Metabox_CSS_Output {

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
			// Empty post_type = the registering code didn't restrict; we
			// match defensively. Otherwise the queried post type must be
			// in the list.
			if ( ! empty( $pt ) && ! in_array( $post_type, $pt, true ) ) {
				continue;
			}
			$fields = isset( $box['fields'] ) ? (array) $box['fields'] : array();
			foreach ( $fields as $field ) {
				if ( empty( $field['type'] ) || 'spacing' !== $field['type'] ) {
					continue;
				}
				if ( empty( $field['css_target']['enabled'] ) ) {
					continue;
				}
				if ( empty( $field['id'] ) ) {
					continue;
				}
				$val = get_post_meta( $post_id, $field['id'], true );
				$css .= TPMeta_CSS_Target::build_spacing_rule( $field, $val );
			}
		}

		$css = trim( $css );
		if ( '' === $css ) {
			return;
		}

		echo "\n<style id=\"tpmeta-metabox-output\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
