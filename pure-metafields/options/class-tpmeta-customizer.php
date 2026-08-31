<?php
/**
 * TPMeta_Customizer
 *
 * Mirrors any Options-Panel-Builder panel that has customizer_integration = true
 * into the WordPress native Customizer (Appearance → Customize).
 *
 * Single source of truth for field-type → control routing. Each plugin field
 * type is either:
 *
 *   - mode=native  → registered with a stock WP_Customize_Control (or subclass)
 *                    when our stored data shape matches what the native control
 *                    expects.
 *   - mode=custom  → registered with TPMeta_Customize_Field_Control which
 *                    includes our existing options/templates/fields/<type>.php
 *                    template, so the sidebar gets full UI parity with the
 *                    options admin panel for fields with no native equivalent
 *                    (or where the native one would corrupt our stored data).
 *
 * Storage is identical to the options panel: both use set/get_theme_mod() under
 * the same field IDs, so values stay in sync regardless of which UI the user
 * edits them through.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options
 * @since      1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Customizer {

	/**
	 * Field-type → routing rule. The single source of truth.
	 *
	 *   mode=native + 'class' → instantiate that WP_Customize_Control subclass
	 *   mode=native + 'type'  → use base WP_Customize_Control with this 'type' attr
	 *   mode=custom            → render our PHP template via TPMeta_Customize_Field_Control
	 *
	 * @var array<string,array{mode:string,class?:string,type?:string}>
	 */
	private static array $field_map = array(
		// ── Native: data shape matches WP, UX acceptable ───────────────────
		'text'            => array( 'mode' => 'native', 'type'  => 'text' ),
		'textarea'        => array( 'mode' => 'native', 'type'  => 'textarea' ),
		'select'          => array( 'mode' => 'native', 'type'  => 'select' ),  // overridden to custom if multiple=true
		// tabs / radio_buttonset render as a segmented .tm-button-groups pill on
		// the options page. Native 'radio' would lose that look in the
		// Customizer — route through the custom template instead. Storage shape
		// is unchanged: scalar choice key, sanitize_text_field.
		'tabs'            => array( 'mode' => 'custom', 'transport' => 'postMessage' ),
		'radio_buttonset' => array( 'mode' => 'custom', 'transport' => 'postMessage' ),
		'colorpicker'     => array( 'mode' => 'native', 'class' => 'WP_Customize_Color_Control' ),
		'code'            => array( 'mode' => 'native', 'class' => 'WP_Customize_Code_Editor_Control' ),

		// ── Custom: no native equivalent OR our data shape would be lost ────
		'image'           => array( 'mode' => 'custom' ),  // we store attachment ID; WP_Customize_Image_Control returns URL
		'checkbox'        => array( 'mode' => 'custom' ),  // we store 'on'/'off'; native checkbox returns '1'/''
		'datepicker'      => array( 'mode' => 'custom' ),  // jQuery UI 'YYYY-MM-DD' vs WP 'Y-m-d H:i:s'
		'switch'          => array( 'mode' => 'custom' ),
		// Editor uses a dedicated control (TinyMCE needs lifecycle handling
		// the generic template-include path can't provide). Sanitised with
		// wp_kses_post and live-preview-friendly via postMessage transport.
		'editor'          => array(
			'mode'      => 'custom',
			'class'     => 'TPMeta_Customize_TinyMCE_Control',
			'sanitize'  => 'wp_kses_post',
			'transport' => 'postMessage',
		),
		'post_select'     => array( 'mode' => 'custom' ),
		'typography'      => array( 'mode' => 'custom' ),
		'spacing'         => array( 'mode' => 'custom' ),
		'dimension'       => array( 'mode' => 'custom' ),
		'color_gradient'  => array( 'mode' => 'custom' ),
		'multicolor'      => array( 'mode' => 'custom' ),
		'gallery'         => array( 'mode' => 'custom' ),
		'multicheck'      => array( 'mode' => 'custom' ),
		'repeater'        => array( 'mode' => 'custom' ),
		'radio_image'     => array( 'mode' => 'custom' ),
		'section_heading' => array( 'mode' => 'custom' ),
	);

	/**
	 * Custom-mode types whose stored value is an associative/JSON array — drives
	 * sanitize routing inside register_custom().
	 *
	 * @var string[]
	 */
	private static array $array_types = array(
		'typography', 'spacing', 'color_gradient', 'multicolor',
		'gallery',    'multicheck', 'repeater', 'image',
	);

	private string $version;

	/**
	 * Field-ID → conditional rule map collected during register_field().
	 *
	 * Populated as PHP `active_callback`s are wired up; serialized to JS in
	 * enqueue_assets() so the Customizer JS can react to in-progress setting
	 * changes (active_callback alone only runs at first render and never re-
	 * evaluates as deps change in the sidebar).
	 *
	 * Shape: [ field_id => [ 'field'=>dep, 'operator'=>op, 'value'=>v ] ]
	 *
	 * @var array<string,array{field:string,operator:string,value:mixed}>
	 */
	private array $conditionals_for_js = array();

	public function __construct( string $version = TPMETA_VERSION ) {
		$this->version = $version;
		add_action( 'customize_register',                 array( $this, 'register' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// ── Registration entry point ─────────────────────────────────────────────

	public function register( WP_Customize_Manager $wp_customize ) {
		// WP_Customize_Control subclasses are only available during Customizer
		// requests — load custom-control classes here, not at plugin boot.
		require_once TPMETA_PATH . 'options/customizer-fields/class-tpmeta-customize-field-control.php';
		require_once TPMETA_PATH . 'options/customizer-fields/class-tpmeta-customize-tinymce-control.php';

		// TEMP TRACE: capture backtrace for "Array to string conversion" warnings
		// fired during the customizer request, so we can pinpoint the caller.
		// Remove after the issue is fixed.
		set_error_handler( function ( $errno, $errstr, $errfile, $errline ) {
			if ( false !== strpos( $errstr, 'Array to string conversion' ) ) {
				$bt    = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
				$lines = array();
				foreach ( $bt as $i => $f ) {
					$where   = ( $f['file'] ?? '?' ) . ':' . ( $f['line'] ?? '?' );
					$call    = ( $f['class'] ?? '' ) . ( $f['type'] ?? '' ) . ( $f['function'] ?? '' );
					$lines[] = "  #$i  $call  ($where)";
				}
				error_log( "[TPMETA TRACE] Array→string at $errfile:$errline\n" . implode( "\n", $lines ) );
			}
			return false; // let PHP continue with default warning behavior
		}, E_WARNING );

		// Read panels from TPMeta_Options — the single registry every panel
		// lands in regardless of source (builder JSON via the loader, OR baked
		// PHP via direct TPMeta_Options::set_args() calls). This means baked
		// panels also get Customizer integration when their flag is true.
		if ( ! class_exists( 'TPMeta_Options' ) ) {
			return;
		}

		foreach ( TPMeta_Options::get_panels() as $opt_name => $panel ) {
			if ( empty( $panel['customizer_integration'] ) ) {
				continue;
			}
			// Sections are stored separately on TPMeta_Options — attach here so
			// register_panel() can iterate them just like a builder-JSON panel.
			$panel['sections'] = array_values( TPMeta_Options::get_sections( $opt_name ) );
			if ( empty( $panel['opt_name'] ) ) {
				$panel['opt_name'] = $opt_name;
			}
			$this->register_panel( $wp_customize, $panel );
		}
	}

	// ── Per-panel ────────────────────────────────────────────────────────────

	private function register_panel( WP_Customize_Manager $wp_customize, array $panel ) {
		$opt_name   = $panel['opt_name']   ?? '';
		$menu_title = $panel['menu_title'] ?? $opt_name;
		$capability = $panel['capability'] ?? 'manage_options';

		if ( ! $opt_name ) return;

		// Resolve Customizer panel priority. Dedicated 'customizer_priority'
		// wins (set via Panel Settings → Customizer Position Priority); if
		// absent, fall back to the legacy reuse of admin menu_position; if
		// neither is set, use WP's default (160). Keeping the menu_position
		// fallback preserves the position of every panel that was saved
		// before this field existed.
		if ( isset( $panel['customizer_priority'] ) && '' !== (string) $panel['customizer_priority'] && is_numeric( $panel['customizer_priority'] ) ) {
			$cust_priority = (int) $panel['customizer_priority'];
		} elseif ( isset( $panel['menu_position'] ) && '' !== (string) $panel['menu_position'] && is_numeric( $panel['menu_position'] ) ) {
			$cust_priority = (int) $panel['menu_position'];
		} else {
			$cust_priority = 160;
		}

		$wp_customize->add_panel( $opt_name, array(
			'title'       => $menu_title,
			'priority'    => $cust_priority,
			'capability'  => $capability,
			'description' => sprintf(
				/* translators: %s: panel name */
				__( 'Settings from the "%s" options panel.', 'pure-metafields' ),
				$menu_title
			),
		) );

		$sec_priority = 10;
		foreach ( $panel['sections'] ?? array() as $section ) {
			$this->register_section( $wp_customize, $section, $opt_name, $capability, $sec_priority );
			$sec_priority += 10;
		}
	}

	// ── Per-section ──────────────────────────────────────────────────────────

	private function register_section(
		WP_Customize_Manager $wp_customize,
		array $section,
		string $panel_id,
		string $capability,
		int $priority
	) {
		$sec_id    = sanitize_key( $panel_id . '_' . ( $section['id'] ?? '' ) );
		$sec_title = $section['title']       ?? $sec_id;
		$sec_desc  = $section['description'] ?? '';

		$wp_customize->add_section( $sec_id, array(
			'panel'       => $panel_id,
			'title'       => $sec_title,
			'description' => $sec_desc,
			'priority'    => $priority,
			'capability'  => $capability,
		) );

		// Flatten rows → fields (builder JSON groups fields into rows).
		$fields = array();
		if ( ! empty( $section['rows'] ) && is_array( $section['rows'] ) ) {
			foreach ( $section['rows'] as $row ) {
				foreach ( (array) ( $row['fields'] ?? array() ) as $f ) {
					$fields[] = $f;
				}
			}
		} elseif ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
			$fields = $section['fields'];
		}

		// Normalize every field through the loader's transform so options become
		// {value => label}, conditional becomes [field, op, value], etc. — same
		// shape the admin-panel templates expect. Without this, templates like
		// multicolor.php iterate raw [{value,label}, …] and pass arrays to
		// esc_attr(), which triggers Array→string conversion warnings.
		$ctrl_priority = 10;
		foreach ( $fields as $field ) {
			$normalized = class_exists( 'TPMeta_Builder_Loader' )
				? TPMeta_Builder_Loader::normalize_field( (array) $field )
				: (array) $field;
			$this->register_field( $wp_customize, $normalized, $sec_id, $capability, $ctrl_priority );
			$ctrl_priority += 10;
		}
	}

	// ── Per-field — dispatches to native or custom path ──────────────────────

	private function register_field(
		WP_Customize_Manager $wp_customize,
		array $field,
		string $section_id,
		string $capability,
		int $priority
	) {
		$id   = sanitize_key( $field['id'] ?? '' );
		$type = $field['type'] ?? 'text';

		if ( ! $id ) return;

		$rule = self::$field_map[ $type ] ?? array( 'mode' => 'native', 'type' => 'text' );

		// Edge override: native <select> can't render multiple — use custom UI.
		if ( 'select' === $type && ! empty( $field['multiple'] ) ) {
			$rule = array( 'mode' => 'custom' );
		}

		// Record conditional so the JS can re-evaluate on in-progress changes.
		// PHP active_callback only runs at first render; JS keeps it reactive.
		$cond_norm = $this->normalize_conditional( $field['conditional'] ?? array() );
		if ( $cond_norm ) {
			$setting_id_for_js                          = ( 'section_heading' === $type ) ? $id . '_cust_hdg' : $id;
			$this->conditionals_for_js[ $setting_id_for_js ] = $cond_norm;
		}

		if ( 'native' === $rule['mode'] ) {
			$this->register_native( $wp_customize, $field, $rule, $id, $type, $section_id, $capability, $priority );
		} else {
			$this->register_custom( $wp_customize, $field, $id, $type, $section_id, $capability, $priority );
		}
	}

	/**
	 * Normalise a conditional rule from either numeric or assoc shape.
	 *
	 * @param mixed $cond
	 * @return array{field:string,operator:string,value:mixed}|null
	 */
	private function normalize_conditional( $cond ) {
		if ( empty( $cond ) || ! is_array( $cond ) ) return null;
		if ( isset( $cond['field'] ) ) {
			$dep_id   = (string) ( $cond['field']    ?? '' );
			$operator = (string) ( $cond['operator'] ?? '==' );
			$compare  = $cond['value'] ?? '';
		} else {
			$dep_id   = (string) ( $cond[0] ?? '' );
			$operator = (string) ( $cond[1] ?? '==' );
			$compare  = $cond[2] ?? '';
		}
		if ( ! $dep_id ) return null;
		return array( 'field' => $dep_id, 'operator' => $operator, 'value' => $compare );
	}

	// ── Native registration path ─────────────────────────────────────────────

	private function register_native(
		WP_Customize_Manager $wp_customize,
		array $field,
		array $rule,
		string $id,
		string $type,
		string $section_id,
		string $capability,
		int $priority
	) {
		// Defaults that aren't representable as a theme_mod scalar are reset.
		$default = $field['default'] ?? '';
		if ( is_array( $default ) ) {
			$default = '';
		}

		$wp_customize->add_setting( $id, array(
			'type'              => 'theme_mod',
			'default'           => $default,
			'capability'        => $capability,
			'sanitize_callback' => $this->sanitize_callback_native( $type ),
			'transport'         => 'postMessage',
		) );

		$control_args = array(
			'section'         => $section_id,
			'label'           => $field['label']       ?? '',
			'description'     => $field['description'] ?? '',
			'priority'        => $priority,
			'active_callback' => $this->active_callback_for( $field ),
		);

		// Native-control-specific args (type / class / choices / code_type).
		$ctrl_class = $rule['class'] ?? 'WP_Customize_Control';
		if ( isset( $rule['type'] ) ) {
			$control_args['type'] = $rule['type'];
		}

		// Choices for select / radio inputs.
		if ( in_array( $rule['type'] ?? '', array( 'select', 'radio' ), true ) ) {
			$opts = $field['options'] ?? $field['choices'] ?? array();
			$control_args['choices'] = is_array( $opts ) ? $opts : array();
		}

		// Code editor language.
		if ( 'WP_Customize_Code_Editor_Control' === $ctrl_class ) {
			$control_args['code_type'] = 'text/css';
		}

		if ( 'WP_Customize_Control' === $ctrl_class || ! class_exists( $ctrl_class ) ) {
			$wp_customize->add_control( $id, $control_args );
		} else {
			$wp_customize->add_control( new $ctrl_class( $wp_customize, $id, $control_args ) );
		}
	}

	private function sanitize_callback_native( string $type ) {
		switch ( $type ) {
			case 'colorpicker':
				return array( $this, 'sanitize_color' );
			case 'checkbox':
			case 'switch':
				return array( $this, 'sanitize_checkbox' );
			case 'textarea':
			case 'editor':
				return 'wp_kses_post';
			case 'code':
				return 'wp_unslash';
			case 'image':
				return 'esc_url_raw';
			default:
				return 'sanitize_text_field';
		}
	}

	public function sanitize_color( $color ) {
		if ( is_array( $color ) || '' === $color ) return '';
		if ( preg_match( '/^#([a-f0-9]{3,8})$/i',          $color ) ) return $color;
		if ( preg_match( '/^rgba?\([\s\d.,%-]+\)$/i',      $color ) ) return $color;
		if ( preg_match( '/^[a-zA-Z]+$/',                  $color ) ) return $color;
		return '';
	}

	public function sanitize_checkbox( $value ) {
		return ( '1' === $value || true === $value || 'on' === $value ) ? '1' : '';
	}

	// ── Custom registration path ─────────────────────────────────────────────

	private function register_custom(
		WP_Customize_Manager $wp_customize,
		array $field,
		string $id,
		string $type,
		string $section_id,
		string $capability,
		int $priority
	) {
		$rule     = self::$field_map[ $type ] ?? array();
		$is_array = in_array( $type, self::$array_types, true );

		// Image field's array-ness depends on return_format — only 'array'
		// stores an associative value; 'id' / 'url' store scalars.
		if ( 'image' === $type ) {
			$rf       = isset( $field['return_format'] ) ? (string) $field['return_format'] : 'array';
			$is_array = ( 'array' === $rf );
		}

		// section_heading carries no value — use a dummy read-only setting so
		// the custom control can call value() without notices.
		if ( 'section_heading' === $type ) {
			$sanitize   = '__return_empty_string';
			$default    = '';
			$setting_id = $id . '_cust_hdg';
		} else {
			$sanitize = $rule['sanitize']
				?? ( $is_array ? array( $this, 'sanitize_array' ) : 'sanitize_text_field' );

			// Image field always routes to the canonical store sanitizer so
			// return_format is respected (otherwise the customizer would call
			// a generic array sanitizer that doesn't reshape to scalar).
			if ( 'image' === $type && class_exists( 'TPMeta_Options_Store' ) ) {
				$field_def = $field;
				$sanitize  = function ( $value ) use ( $field_def ) {
					return TPMeta_Options_Store::sanitize( $field_def, $value );
				};
			}

			$default    = $field['default'] ?? ( $is_array ? array() : '' );
			if ( $is_array && ! is_array( $default ) ) {
				// Image-array default may be a scalar URL/token/ID — normalize
				// to the { id, url, alt } shape so the Customizer template
				// gets a usable preview when nothing is saved yet.
				if ( 'image' === $type && '' !== $default && class_exists( 'TPMeta_Options_Store' ) ) {
					$default = TPMeta_Options_Store::sanitize( $field, $default );
				} else {
					$default = array();
				}
			}
			$setting_id = $id;
		}

		// 'refresh' is safer for complex array fields until live-preview JS is
		// implemented per-field. Per-type rules can opt into 'postMessage' (e.g.
		// the editor field, which has a clean string value).
		$transport = $rule['transport'] ?? 'refresh';

		$wp_customize->add_setting( $setting_id, array(
			'type'              => 'theme_mod',
			'default'           => $default,
			'capability'        => $capability,
			'sanitize_callback' => $sanitize,
			'transport'         => $transport,
		) );

		$control_class = $rule['class'] ?? 'TPMeta_Customize_Field_Control';
		$args = array(
			'section'         => $section_id,
			'priority'        => $priority,
			'field'           => $field,
			'active_callback' => $this->active_callback_for( $field ),
		);
		// Per-type extras the dedicated control reads from its constructor args.
		if ( 'TPMeta_Customize_TinyMCE_Control' === $control_class ) {
			$args['editor_settings'] = $field['editor_settings'] ?? array();
		}

		$wp_customize->add_control( new $control_class( $wp_customize, $setting_id, $args ) );
	}

	/**
	 * Sanitize an array-value field (typography, spacing, gallery, …).
	 *
	 * Accepts a PHP array or a JSON string (edge-case fallback). Returns a
	 * sanitized associative array that set_theme_mod() will store directly.
	 *
	 * @param  mixed                $value
	 * @param  WP_Customize_Setting $setting
	 * @return array
	 */
	public function sanitize_array( $value, WP_Customize_Setting $setting ) {
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( wp_unslash( $value ), true );
			$value   = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : array();
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		return $this->deep_sanitize( $value );
	}

	private function deep_sanitize( array $data ): array {
		$out = array();
		foreach ( $data as $key => $val ) {
			$key         = is_int( $key ) ? $key : sanitize_key( (string) $key );
			$out[ $key ] = is_array( $val )
				? $this->deep_sanitize( $val )
				: sanitize_text_field( (string) $val );
		}
		return $out;
	}

	// ── Active callback — supports numeric and assoc conditional shapes ──────

	private function active_callback_for( array $field ) {
		$cond = $field['conditional'] ?? array();

		if ( ! empty( $cond ) && is_array( $cond ) ) {
			if ( isset( $cond['field'] ) ) {
				$dep_id   = $cond['field']    ?? '';
				$operator = $cond['operator'] ?? '==';
				$compare  = $cond['value']    ?? '';
			} else {
				$dep_id   = $cond[0] ?? '';
				$operator = $cond[1] ?? '==';
				$compare  = $cond[2] ?? '';
			}

			if ( $dep_id ) {
				return function() use ( $dep_id, $operator, $compare ) {
					$current = get_theme_mod( $dep_id, '' );
					switch ( $operator ) {
						case '==':  return $current ==  $compare;
						case '===': return $current === $compare;
						case '!=':  return $current !=  $compare;
						case '!==': return $current !== $compare;
						case '>':   return $current >   $compare;
						case '<':   return $current <   $compare;
						case '>=':  return $current >=  $compare;
						case '<=':  return $current <=  $compare;
						default:    return true;
					}
				};
			}
		}

		return '__return_true';
	}

	// ── Asset enqueueing in the Customizer controls pane ─────────────────────

	public function enqueue_assets() {
		// Shared field runtime (colorpicker, datepicker, select2, dragula …).
		if ( class_exists( 'TPMeta_Assets' ) ) {
			TPMeta_Assets::enqueue_field_runtime();
		}

		// wp.media for the image-field picker.
		wp_enqueue_media();

		// TinyMCE for the editor field.
		// wp_enqueue_editor() hooks wp_enqueue_script('wp-tinymce') to
		// customize_controls_print_footer_scripts at priority 50 — after the
		// script queue has already printed. The tinymce global never lands on
		// the page that way. We enqueue wp-tinymce DIRECTLY here (early, in
		// customize_controls_enqueue_scripts) so WP includes it in the normal
		// script output before any of our code runs.
		wp_enqueue_script( 'wp-tinymce' );

		// Bridges sub-input changes → wp.customize setting for array-value fields.
		wp_enqueue_script(
			'tpmeta-customizer-fields',
			TPMETA_URL . 'options/customizer-fields/js/tpmeta-customizer-fields.js',
			array( 'jquery', 'customize-controls', 'tm-metabox-js', 'select2' ),
			$this->version,
			true
		);

		// Pass conditionals to JS so dependent fields toggle live (active_callback
		// alone only runs at first render). Object is keyed by control-setting-id
		// so the JS can look up the wp.customize.control directly.
		wp_localize_script(
			'tpmeta-customizer-fields',
			'TPMetaCustomizerConditionals',
			(object) $this->conditionals_for_js
		);

		// Dedicated TinyMCE control constructor.
		// Declares wp-tinymce as a hard dep so WP guarantees load order:
		// tinymce global exists before tpmeta-customize-tinymce.js executes.
		wp_enqueue_script(
			'tpmeta-customize-tinymce',
			TPMETA_URL . 'options/customizer-fields/js/tpmeta-customize-tinymce.js',
			array( 'jquery', 'customize-controls', 'wp-tinymce' ),
			$this->version,
			true
		);

		// Sidebar layout overrides for our custom-control templates.
		wp_enqueue_style(
			'tpmeta-customizer-fields-css',
			TPMETA_URL . 'options/customizer-fields/css/tpmeta-customizer-fields.css',
			array( 'tm-metabox-css', 'customize-controls' ),
			$this->version
		);

		// UI parity layer — brings the FULL option-panel UI for the two field
		// widgets whose styles only live in tpmeta-options.css (typography +
		// radio_image). Every rule in this file is prefixed with `.wp-customizer`
		// so it can ONLY take effect inside the Customizer pane.
		wp_enqueue_style(
			'tpmeta-customizer-parity',
			TPMETA_URL . 'options/customizer-fields/css/tpmeta-customizer-parity.css',
			array( 'tpmeta-customizer-fields-css' ),
			$this->version
		);

		// Dedicated, isolated repeater UI for the narrow pane (compact rows,
		// collapse-by-default, single-column body, full-width Add Row). Kept in
		// its own file so the repeater styling never touches the options panel.
		wp_enqueue_style(
			'tpmeta-customizer-repeater',
			TPMETA_URL . 'options/customizer-fields/css/tpmeta-customizer-repeater.css',
			array( 'tpmeta-customizer-fields-css' ),
			$this->version
		);
	}
}
