<?php
/**
 * TPMeta_Customize_Field_Control
 *
 * WP_Customize_Control subclass that renders any TPMeta options field template
 * directly inside the Customizer panel sidebar — giving all 20+ field types
 * full UI parity with the options admin panel.
 *
 * The template receives the same three variables it always expects:
 *   $field  — full field definition array
 *   $id     — field / setting ID (same as theme_mod key)
 *   $value  — current saved value (scalar or array depending on field type)
 *
 * For array-value fields (typography, spacing, dimension, …) the JS companion
 * tpmeta-customizer-fields.js watches all sub-inputs and syncs the combined
 * object into wp.customize so the Customizer can save it correctly.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options/customizer-fields
 * @since      1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPMeta_Customize_Field_Control extends WP_Customize_Control {

	/** Matched in JS to our custom constructor. */
	public $type = 'tpmeta_field';

	/** Full field definition array passed from the orchestrator. */
	public $field = array();

	/**
	 * Field types whose theme_mod value is stored as an associative array
	 * (not a scalar). The JS layer uses this flag to decide how to collect
	 * and push the setting value.
	 *
	 * @var string[]
	 */
	public static $array_types = array(
		'typography',
		'spacing',
		// 'dimension' intentionally NOT listed — its theme_mod value is a
		// scalar string ("150px"), carried by a single hidden input with
		// name="<id>" (no [key] brackets). Listing it here routes the JS
		// through bindArrayField → collectSubFields, which searches for
		// bracket-named sub-inputs, finds none, and pushes {} to a setting
		// whose sanitize_callback is sanitize_text_field — corrupting the
		// saved value. Scalar binding reads the hidden input and works.
		'color_gradient',
		'multicolor',
		'gallery',
		'multicheck',
		'repeater',
		'image',
	);

	// ── Customizer JS params ─────────────────────────────────────────────────

	public function to_json() {
		parent::to_json();

		$field_type = $this->field['type'] ?? 'text';
		$is_array   = in_array( $field_type, self::$array_types, true );

		// Image field's array-ness depends on return_format — only the
		// 'array' choice stores an associative array; 'id' and 'url' store
		// scalars and must be synced via the scalar [name] watcher.
		if ( 'image' === $field_type ) {
			$rf       = isset( $this->field['return_format'] ) ? (string) $this->field['return_format'] : 'array';
			$is_array = ( 'array' === $rf );
		}

		$this->json['tpmeta_field_type'] = $field_type;
		$this->json['tpmeta_is_array']   = $is_array;
	}

	// ── Render ───────────────────────────────────────────────────────────────

	protected function render_content() {
		$id    = $this->id;
		$field = $this->field;
		$value = $this->value();

		$field_type = $field['type'] ?? 'text';

		// Normalise: if a previous save stored an array value as a JSON string,
		// decode it so templates that expect an array receive an array.
		if ( is_string( $value ) && '' !== $value && in_array( $field_type, self::$array_types, true ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$value = $decoded;
			}
		}

		// Merge configured sub-field defaults into repeater rows.
		//
		// The options panel reads this value through get_theme_mod(), whose
		// theme_mod_{id} filter chain decodes the rows (priority 10) and then
		// merges each sub-field's builder default into empty/missing keys
		// (priority 11) — that's what makes a select sub-field show its
		// default. But the Customizer control's value() returns the setting's
		// post/changeset value, which bypasses get_theme_mod() and its filters,
		// so the rows arrive un-merged and the default never shows. Re-apply
		// the same merge here so the Customizer matches the panel. Idempotent
		// (saved values always win), so it can't override real data.
		if ( 'repeater' === $field_type && is_array( $value )
			&& function_exists( 'tpmeta_apply_repeater_defaults' ) ) {
			$value = tpmeta_apply_repeater_defaults( $value, $field );
		}

		// Label.
		if ( ! empty( $field['label'] ) ) {
			echo '<span class="customize-control-title">' . esc_html( $field['label'] ) . '</span>';
		}

		// Description.
		if ( ! empty( $field['description'] ) ) {
			echo '<span class="description customize-control-description">'
				. esc_html( $field['description'] )
				. '</span>';
		}

		// section_heading has no input — render a visual divider only.
		if ( 'section_heading' === $field_type ) {
			echo '<div class="tpmeta-cust-section-heading">'
				. esc_html( $field['label'] ?? '' )
				. '</div>';
			return;
		}

		// Include the existing options field template.
		// Wrap in .tpmeta-panel-field so all `.tpmeta-panel-field .X` widget
		// rules (ported into tpmeta-customizer-parity.css with a .wp-customizer
		// prefix) match here, giving the Customizer full UI parity with the
		// options panel without touching the option-panel CSS.
		$tpl = TPMETA_PATH . 'options/templates/fields/' . sanitize_key( $field_type ) . '.php';
		if ( file_exists( $tpl ) ) {
			echo '<div class="tpmeta-panel-field">';
			include $tpl;
			echo '</div>';
		}
	}
}
