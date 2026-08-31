<?php
/**
 * TPMeta_Customize_TinyMCE_Control
 *
 * Customizer control for the rich-text editor field (Classic TinyMCE 4.x).
 *
 * Architecture:
 *   - PHP renders nothing server-side. content_template() defines an
 *     Underscore template that is rendered into live DOM when the section
 *     opens. This avoids wp_editor()'s inline init script running before
 *     the textarea exists in the page.
 *
 *   - The template renders only a <textarea>. TinyMCE 4 (the version
 *     shipped with WordPress) is initialised via tinymce.init() with a
 *     CSS selector pointing to that textarea. wp.editor.initialize() is
 *     NOT used because it requires tinyMCEPreInit, which is never emitted
 *     when wp_editor() hasn't been called server-side.
 *
 *   - Visual / Text tabs are injected by JS after TinyMCE boots. Switching
 *     tabs calls editor.show() / editor.hide() — TinyMCE 4 built-ins that
 *     handle textarea↔iframe toggling cleanly.
 *
 * @package    tpmeta
 * @since      1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Customize_TinyMCE_Control extends WP_Customize_Control {

	public $type = 'tpmeta_tinymce';

	public $editor_settings = array();
	public $field           = array();

	public function to_json() {
		parent::to_json();
		$this->json['editor_id']       = $this->editor_id();
		$this->json['setting_id']      = $this->setting->id;
		$this->json['editor_settings'] = (array) $this->editor_settings;
	}

	private function editor_id() {
		return 'tpmeta_ed_' . preg_replace( '/[^a-z0-9_]/', '_', strtolower( $this->setting->id ) );
	}

	/**
	 * Underscore JS template. Renders on first section open — fresh DOM,
	 * no stale TinyMCE state. {{{ data.link }}} expands to
	 * data-customize-setting-link="…" for WP's automatic two-way binding.
	 */
	protected function content_template() {
		?>
		<# if ( data.label ) { #>
			<span class="customize-control-title">{{{ data.label }}}</span>
		<# } #>
		<# if ( data.description ) { #>
			<span class="description customize-control-description">{{{ data.description }}}</span>
		<# } #>
		<div class="tpmeta-editor-field"
			data-editor-id="{{ data.editor_id }}"
			data-setting-id="{{ data.setting_id }}">
			<textarea
				id="{{ data.editor_id }}"
				class="tpmeta-editor-textarea"
				rows="{{ ( data.editor_settings && data.editor_settings.textarea_rows ) ? data.editor_settings.textarea_rows : 8 }}"
				{{{ data.link }}}>{{ data.value }}</textarea>
		</div>
		<?php
	}

	/** Server-side fallback (used if JS template cannot render). */
	protected function render_content() {
		$id    = $this->editor_id();
		$value = (string) $this->value();
		if ( ! empty( $this->field['label'] ) ) {
			echo '<span class="customize-control-title">' . esc_html( $this->field['label'] ) . '</span>';
		}
		echo '<div class="tpmeta-editor-field"'
			. ' data-editor-id="'  . esc_attr( $id )                  . '"'
			. ' data-setting-id="' . esc_attr( $this->setting->id )   . '">';
		printf(
			'<textarea id="%1$s" class="tpmeta-editor-textarea" data-customize-setting-link="%2$s" rows="8">%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( $this->setting->id ),
			esc_textarea( $value )
		);
		echo '</div>';
	}
}
