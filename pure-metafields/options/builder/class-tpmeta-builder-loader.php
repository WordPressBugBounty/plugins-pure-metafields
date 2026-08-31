<?php
/**
 * TPMeta_Builder_Loader
 *
 * Reads builder schemas from TPMeta_Builder_Store and registers them
 * via TPMeta_Options so they appear as live admin pages.
 *
 * Builder JSON shape (per panel):
 *   {
 *     "opt_name": "...",
 *     "menu_title": "...",
 *     ...
 *     "sections": [
 *       {
 *         "id": "general",
 *         "title": "General",
 *         "columns": 1,
 *         "fields": [
 *           {
 *             "id": "primary_color",
 *             "type": "colorpicker",
 *             "label": "Primary Color",
 *             "default": "#3362FF",
 *             "options": [{"value":"a","label":"A"}, ...],   // when applicable
 *             "output":  [{"selector":"a","property":"color"}, ...],
 *             "conditional": {"field":"foo","operator":"==","value":"bar"}
 *           }
 *         ]
 *       }
 *     ]
 *   }
 *
 * Note: builder JSON stores 'options' as an array of {value,label} pairs
 * (preserves order in JSON). The framework expects an associative array
 * keyed by value. We convert here.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options/builder
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Builder_Loader {

	public function __construct() {
		// Priority 15: run AFTER any baked-PHP option files loaded by themes/plugins at
		// priority 5-10, so this loader's set_section() calls (which carry the actual
		// field definitions) always win over the empty-fields stubs that codegen emits.
		// Still fires well before admin_menu (priority 20 on a later hook).
		add_action( 'init', array( $this, 'register_panels' ), 15 );
	}

	/**
	 * Register every JSON-defined panel.
	 */
	public function register_panels() {
		$panels = TPMeta_Builder_Store::all();
		foreach ( $panels as $panel ) {
			$this->register_panel( $panel );
		}
	}

	/**
	 * Static entry point that mirrors the instance register_panel() so other
	 * subsystems (REST import flow) can register a panel into the runtime
	 * TPMeta_Options registry on demand — e.g. during the same request that
	 * just saved an imported panel, where init:15 already fired BEFORE the
	 * REST request was even authenticated.
	 *
	 * @param array $panel
	 */
	public static function register_one( array $panel ) {
		if ( empty( $panel['opt_name'] ) ) {
			return;
		}
		$instance = new self();
		$instance->register_panel( $panel );
	}

	/**
	 * Register a single panel.
	 *
	 * @param array $panel
	 */
	protected function register_panel( array $panel ) {
		if ( empty( $panel['opt_name'] ) ) {
			return;
		}

		$opt_name = $panel['opt_name'];

		$set_args_payload = array(
			'menu_title'             => isset( $panel['menu_title'] )    ? $panel['menu_title']    : '',
			'page_title'             => isset( $panel['page_title'] )    ? $panel['page_title']    : '',
			'menu_slug'              => isset( $panel['menu_slug'] )     ? $panel['menu_slug']     : $opt_name,
			'menu_icon'              => isset( $panel['menu_icon'] )     ? $panel['menu_icon']     : 'dashicons-admin-generic',
			'menu_position'          => isset( $panel['menu_position'] ) ? (int) $panel['menu_position'] : null,
			'capability'             => isset( $panel['capability'] )    ? $panel['capability']    : 'manage_options',
			'output_css'             => ! empty( $panel['output_css'] ),
			// Forward to TPMeta_Options so TPMeta_Customizer (which now reads from
			// TPMeta_Options::get_panels()) can see the toggle. Without this the
			// flag was silently dropped here and every builder panel defaulted to
			// false in the Customizer.
			'customizer_integration' => ! empty( $panel['customizer_integration'] ),
		);

		// Forward Customizer panel priority only when explicitly set. set_args
		// uses wp_parse_args which preserves any extra keys; TPMeta_Customizer
		// reads this back from get_panels() at customize_register.
		if ( isset( $panel['customizer_priority'] ) && '' !== (string) $panel['customizer_priority'] && is_numeric( $panel['customizer_priority'] ) ) {
			$set_args_payload['customizer_priority'] = (int) $panel['customizer_priority'];
		}

		TPMeta_Options::set_args( $opt_name, $set_args_payload );

		if ( empty( $panel['sections'] ) || ! is_array( $panel['sections'] ) ) {
			return;
		}

		foreach ( $panel['sections'] as $section ) {
			TPMeta_Options::set_section( $opt_name, $this->normalize_section( (array) $section ) );
		}
	}

	/**
	 * Translate builder section shape into the framework's section shape.
	 *
	 * @param array $section
	 * @return array
	 */
	protected function normalize_section( array $section ) {
		$out = array(
			'id'          => isset( $section['id'] )          ? $section['id']          : '',
			'title'       => isset( $section['title'] )       ? $section['title']       : '',
			'icon'        => isset( $section['icon'] )        ? $section['icon']        : '',
			'description' => isset( $section['description'] ) ? $section['description'] : '',
			'fields'      => array(),
		);

		// Flatten all rows into a single ordered fields array for the framework.
		// The row layout/columns context is captured in the builder JSON but the
		// options-panel framework only needs a flat list of field definitions.
		if ( ! empty( $section['rows'] ) && is_array( $section['rows'] ) ) {
			foreach ( $section['rows'] as $row ) {
				if ( empty( $row['fields'] ) || ! is_array( $row['fields'] ) ) {
					continue;
				}
				foreach ( $row['fields'] as $field ) {
					$out['fields'][] = self::normalize_field( (array) $field );
				}
			}
		} elseif ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
			// Legacy flat-fields support.
			foreach ( $section['fields'] as $field ) {
				$out['fields'][] = self::normalize_field( (array) $field );
			}
		}

		return $out;
	}

	/**
	 * Translate a builder field shape into framework field shape.
	 *
	 * Public + static so other registrars (e.g. TPMeta_Customizer) can apply
	 * the same normalization when they read directly from TPMeta_Builder_Store
	 * and bypass this loader. The function has no instance state.
	 *
	 * @param array $field
	 * @return array
	 */
	public static function normalize_field( array $field ) {
		$out = array(
			'id'          => isset( $field['id'] )          ? $field['id']          : '',
			'type'        => isset( $field['type'] )        ? $field['type']        : 'text',
			'label'       => isset( $field['label'] )       ? $field['label']       : '',
			'description' => isset( $field['description'] ) ? $field['description'] : '',
			'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
		);

		if ( array_key_exists( 'default', $field ) ) {
			$out['default'] = $field['default'];
		}

		// options: array of {value,label} â†’ assoc [value => label].
		//
		// Idempotency: this function is also invoked by TPMeta_Customizer on
		// fields that the loader has already normalized once. If `options` is
		// already an assoc map (string keys), re-running the foreach below
		// would treat each label/URL string as an array, fail isset['value'],
		// and silently clear the field's choices — radio_image / select / tabs
		// would render with no options in the Customizer pane. Pass the assoc
		// shape through unchanged.
		if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			$opts             = $field['options'];
			$is_already_assoc = ( array_keys( $opts ) !== range( 0, count( $opts ) - 1 ) );

			if ( $is_already_assoc ) {
				$assoc = $opts;
			} else {
				$assoc = array();
				foreach ( $opts as $opt ) {
					$opt = (array) $opt;
					if ( ! isset( $opt['value'] ) ) continue;
					$assoc[ $opt['value'] ] = isset( $opt['label'] ) ? $opt['label'] : $opt['value'];
				}
			}

			$out['options'] = $assoc;
			// `tabs` template reads `choices`; mirror for compatibility.
			$out['choices'] = $assoc;
		}
		// output: pass through [{selector,property,important}] unchanged.
		// generic_css_for_panel() handles both this object-array format and the
		// legacy hand-coded assoc [selector => property] form — no conversion
		// needed. Converting to assoc previously lost the `important` flag and
		// silently collapsed duplicate-selector rules (second overwrote first).
		if ( ! empty( $field['output'] ) && is_array( $field['output'] ) ) {
			$out['output'] = $field['output'];
		}

		// conditional: {field,operator,value} â†’ [field, operator, value].
		//
		// Idempotency: when TPMeta_Customizer re-normalizes a field that the
		// loader already processed once, $field['conditional'] arrives in the
		// numeric [field, op, value] shape. The original branch keyed on
		// $c['field'] (assoc), so on the second pass the conditional was
		// silently dropped — active_callback_for() then returned __return_true
		// and the localized JS rule map ended up empty, so dependent fields
		// always showed in the Customizer regardless of the dependency value.
		// Accept both shapes and emit the canonical numeric form.
		if ( ! empty( $field['conditional'] ) && is_array( $field['conditional'] ) ) {
			$c        = $field['conditional'];
			$dep_id   = '';
			$operator = '==';
			$compare  = '';

			if ( ! empty( $c['field'] ) ) {
				$dep_id   = (string) $c['field'];
				$operator = isset( $c['operator'] ) ? (string) $c['operator'] : '==';
				$compare  = isset( $c['value'] )    ? $c['value']             : '';
			} elseif ( isset( $c[0] ) && '' !== (string) $c[0] ) {
				$dep_id   = (string) $c[0];
				$operator = isset( $c[1] ) ? (string) $c[1] : '==';
				$compare  = $c[2] ?? '';
			}

			if ( '' !== $dep_id ) {
				$out['conditional'] = array( $dep_id, $operator, $compare );
			}
		}

		// Switch: pass data_type so the template knows which format to use.
		if ( 'switch' === $out['type'] && ! empty( $field['data_type'] ) ) {
			$out['data_type'] = $field['data_type'];
		}

		// CSS Target: forward the per-field-def opt-in so the template knows
		// to render the Target Selector input and the CSS emitter knows
		// which property to write. Idempotent: the value is a small assoc
		// with stable keys, so re-running normalize_field on already-
		// normalized input produces the same output. Forward only when
		// enabled so absent / off keeps the legacy shape.
		if ( ! empty( $field['css_target'] ) && is_array( $field['css_target'] ) ) {
			$ct = (array) $field['css_target'];
			if ( ! empty( $ct['enabled'] ) ) {
				$prop = isset( $ct['property'] ) && '' !== (string) $ct['property'] ? (string) $ct['property'] : 'padding';
				$sel  = isset( $ct['selector'] ) ? (string) $ct['selector'] : '';
				$out['css_target'] = array(
					'enabled'   => true,
					'property'  => $prop,
					'selector'  => $sel,
					'important' => ! empty( $ct['important'] ),
				);
			}
		}

		// Image: forward return_format ('array' | 'id' | 'url') so the
		// sanitizer reshapes the saved value to the developer's choice.
		if ( 'image' === $out['type'] && ! empty( $field['return_format'] ) ) {
			$out['return_format'] = in_array( $field['return_format'], array( 'array', 'id', 'url' ), true )
				? $field['return_format'] : 'array';
		}

		// Dimension: forward range-slider config so the template renders
		// the configured bounds. Stored value remains a scalar string.
		if ( 'dimension' === $out['type'] ) {
			if ( isset( $field['units'] ) && is_array( $field['units'] ) && ! empty( $field['units'] ) ) {
				$out['units'] = array_values( $field['units'] );
			}
			foreach ( array( 'min', 'max', 'step' ) as $_dim_k ) {
				if ( isset( $field[ $_dim_k ] ) && '' !== (string) $field[ $_dim_k ] && is_numeric( $field[ $_dim_k ] ) ) {
					$out[ $_dim_k ] = 0 + $field[ $_dim_k ];
				}
			}
		}

		// Post Select: pass query config so the template can pre-load & filter.
		if ( 'post_select' === $out['type'] ) {
			if ( ! empty( $field['post_type'] ) )       $out['post_type']       = $field['post_type'];
			if ( ! empty( $field['save_format'] ) )     $out['save_format']     = $field['save_format'];
			if ( ! empty( $field['initial_limit'] ) )   $out['initial_limit']   = (int) $field['initial_limit'];
			if ( ! empty( $field['taxonomy_filter'] ) ) $out['taxonomy_filter'] = (array) $field['taxonomy_filter'];
			if ( ! empty( $field['meta_filters'] ) )    $out['meta_filters']    = (array) $field['meta_filters'];
			if ( ! empty( $field['placeholder'] ) )     $out['placeholder']     = $field['placeholder'];
		}

		// Repeater: pass column count and sub-field definitions so the template
		// can render the add-row form. Options are normalized to assoc {value => label}
		// because the inline JS reads them with Object.keys().
		if ( 'repeater' === $out['type'] ) {
			if ( isset( $field['columns'] ) ) {
				$out['columns'] = max( 1, min( 4, (int) $field['columns'] ) );
			}
			if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$sf_out = array();
				foreach ( $field['fields'] as $sf ) {
					$sf = (array) $sf;
					if ( empty( $sf['id'] ) ) continue;
					$sf_item = array(
						'id'      => $sf['id'],
						'type'    => isset( $sf['type'] )    ? $sf['type']    : 'text',
						'label'   => isset( $sf['label'] )   ? $sf['label']   : '',
						'default' => isset( $sf['default'] ) ? $sf['default'] : '',
					);
					if ( ! empty( $sf['placeholder'] ) ) {
						$sf_item['placeholder'] = $sf['placeholder'];
					}
					// Normalize sequential [{value,label}] options to assoc {value => label}.
					//
					// Idempotency guard (mirrors the top-level options branch): the
					// Customizer normalizes each field TWICE — once in the loader
					// (into TPMeta_Options) and again in register_section. On the
					// second pass $sf['options'] is already an assoc {value => label}
					// map, so the foreach below would iterate label strings, fail
					// isset($opt['value']), and silently DROP every option — leaving
					// the sub-field's select / checkbox / multicheck with no choices
					// in the Customizer (the options panel normalizes only once, so
					// it was unaffected). Pass an already-assoc map through unchanged.
					if ( ! empty( $sf['options'] ) && is_array( $sf['options'] ) ) {
						$sf_opts = $sf['options'];
						$sf_is_assoc = ( array_keys( $sf_opts ) !== range( 0, count( $sf_opts ) - 1 ) );
						if ( $sf_is_assoc ) {
							$sf_item['options'] = $sf_opts;
						} else {
							$assoc = array();
							foreach ( $sf_opts as $opt ) {
								$opt = (array) $opt;
								if ( empty( $opt['value'] ) ) continue;
								$assoc[ $opt['value'] ] = isset( $opt['label'] ) ? $opt['label'] : $opt['value'];
							}
							if ( $assoc ) {
								$sf_item['options'] = $assoc;
							}
						}
					}

					// Multicolor sub-field: decode the JSON-string default so the
					// template receives a plain array instead of a string.
					if ( 'multicolor' === $sf_item['type']
						&& isset( $sf_item['default'] )
						&& is_string( $sf_item['default'] )
						&& '' !== $sf_item['default'] ) {
						$_mc_dec = json_decode( $sf_item['default'], true );
						if ( is_array( $_mc_dec ) ) {
							$sf_item['default'] = $_mc_dec;
						}
					}

					// Image sub-field: forward return_format so the repeater
					// sanitizer reshapes the saved row value to the developer's
					// chosen shape (array | id | url).
					if ( 'image' === $sf_item['type'] && ! empty( $sf['return_format'] ) ) {
						$sf_item['return_format'] = in_array( $sf['return_format'], array( 'array', 'id', 'url' ), true )
							? $sf['return_format'] : 'array';
					}

					// Switch sub-field: forward data_type so:
					//  • the rendered template (options/templates/fields/switch.php)
					//    knows whether to mark `'true'`/`'on'` as the on-state token
					//  • the post-load cast filter on the parent repeater knows
					//    which sub-field keys to coerce to PHP bool.
					// Without this forward, the sub-field ships to the renderer
					// without `data_type` and silently falls back to string mode.
					if ( 'switch' === $sf_item['type'] && ! empty( $sf['data_type'] ) ) {
						$sf_item['data_type'] = in_array( $sf['data_type'], array( 'string', 'boolean' ), true )
							? $sf['data_type'] : 'string';
					}

					// Post Select sub-field: forward the same query config the
					// top-level post_select template reads. Mirrors lines 204-211
					// for top-level fields. Without this, post_select sub-fields
					// silently fall back to 'post' as the queried post type even
					// when the user picked 'page' in the modal.
					if ( 'post_select' === $sf_item['type'] ) {
						if ( ! empty( $sf['post_type'] ) )       $sf_item['post_type']       = $sf['post_type'];
						if ( ! empty( $sf['save_format'] ) )     $sf_item['save_format']     = $sf['save_format'];
						if ( ! empty( $sf['initial_limit'] ) )   $sf_item['initial_limit']   = (int) $sf['initial_limit'];
						if ( ! empty( $sf['taxonomy_filter'] ) ) $sf_item['taxonomy_filter'] = (array) $sf['taxonomy_filter'];
						if ( ! empty( $sf['meta_filters'] ) )    $sf_item['meta_filters']    = (array) $sf['meta_filters'];
					}

					// CSS Target sub-field: forward the per-sub-field opt-in
					// so a repeater row's spacing sub-field can expose its
					// own Target Selector input. Idempotent. Mirrors the
					// top-level css_target branch above.
					if ( ! empty( $sf['css_target'] ) && is_array( $sf['css_target'] ) ) {
						$_sf_ct = (array) $sf['css_target'];
						if ( ! empty( $_sf_ct['enabled'] ) ) {
							$_sf_prop = isset( $_sf_ct['property'] ) && '' !== (string) $_sf_ct['property'] ? (string) $_sf_ct['property'] : 'padding';
							$_sf_sel  = isset( $_sf_ct['selector'] ) ? (string) $_sf_ct['selector'] : '';
							$sf_item['css_target'] = array(
								'enabled'  => true,
								'property' => $_sf_prop,
								'selector' => $_sf_sel,
							);
						}
					}

					// CSS Output sub-field: forward selector + property so
					// TPMeta_Options_Output::repeater_color_css_for_panel()
					// can emit frontend CSS. Without this forward the key is
					// absent from $field_index and CSS generation is silently
					// skipped. Idempotent — safe to re-apply on second-pass
					// normalization (e.g. Customizer).
					if ( in_array( $sf_item['type'], array( 'colorpicker', 'color_gradient' ), true )
						&& ! empty( $sf['css_output'] ) && is_array( $sf['css_output'] ) ) {
						$_co_sel  = isset( $sf['css_output']['selector'] ) ? (string) $sf['css_output']['selector'] : '';
						$_co_prop = isset( $sf['css_output']['property'] ) ? (string) $sf['css_output']['property'] : '';
						if ( '' !== $_co_sel && '' !== $_co_prop ) {
							$sf_item['css_output'] = array(
								'selector' => $_co_sel,
								'property' => $_co_prop,
							);
						}
					}

					// Dimension sub-field: forward range-slider config so the
					// repeater template renders the configured bounds. Mirrors
					// the top-level dimension branch above. Stored row value
					// remains the scalar "150px" string.
					if ( 'dimension' === $sf_item['type'] ) {
						if ( ! empty( $sf['units'] ) && is_array( $sf['units'] ) ) {
							$sf_item['units'] = array_values( $sf['units'] );
						}
						foreach ( array( 'min', 'max', 'step' ) as $_sf_dim_k ) {
							if ( isset( $sf[ $_sf_dim_k ] ) && '' !== (string) $sf[ $_sf_dim_k ] && is_numeric( $sf[ $_sf_dim_k ] ) ) {
								$sf_item[ $_sf_dim_k ] = 0 + $sf[ $_sf_dim_k ];
							}
						}
					}

					$sf_out[] = $sf_item;
				}
				if ( $sf_out ) {
					$out['fields'] = $sf_out;
				}
			}
		}

		return $out;
	}
}

