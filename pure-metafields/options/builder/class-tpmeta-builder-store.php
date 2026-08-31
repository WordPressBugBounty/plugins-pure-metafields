<?php
/**
 * TPMeta_Builder_Store
 *
 * Persists builder schemas to the wp_options table as JSON.
 * Each schema describes a single options panel (panel + sections + fields)
 * that the runtime loader will register via TPMeta_Options at boot.
 *
 * @package    tpmeta
 * @subpackage tpmeta/options/builder
 * @since      1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Builder_Store {

	const OPTION_KEY = 'tpmeta_builder_panels';

	/**
	 * Get all builder-defined panels.
	 *
	 * @return array Indexed array of panel schemas.
	 */
	public static function all() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array_values( $raw );
	}

	/**
	 * Get a single panel by opt_name.
	 *
	 * @param string $opt_name
	 * @return array|null
	 */
	public static function get( $opt_name ) {
		$opt_name = sanitize_key( $opt_name );
		foreach ( self::all() as $panel ) {
			if ( ! empty( $panel['opt_name'] ) && $panel['opt_name'] === $opt_name ) {
				return $panel;
			}
		}
		return null;
	}

	/**
	 * Persist (insert or replace) a panel by opt_name.
	 *
	 * @param array $panel Schema (must contain opt_name).
	 * @return bool
	 */
	public static function save( array $panel ) {
		if ( empty( $panel['opt_name'] ) ) {
			return false;
		}
		$panel    = self::sanitize_schema( $panel );
		$opt_name = $panel['opt_name'];

		$panels = self::all();
		$found  = false;
		foreach ( $panels as $i => $existing ) {
			if ( ! empty( $existing['opt_name'] ) && $existing['opt_name'] === $opt_name ) {
				self::purge_removed_fields( $existing, $panel );
				$panels[ $i ] = $panel;
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$panels[] = $panel;
		}

		return update_option( self::OPTION_KEY, $panels, false );
	}

	/**
	 * Delete a panel by opt_name.
	 *
	 * @param string $opt_name
	 * @return bool
	 */
	public static function delete( $opt_name ) {
		$opt_name = sanitize_key( $opt_name );
		$panel    = self::get( $opt_name );
		if ( $panel ) {
			foreach ( self::collect_field_ids( $panel ) as $field_id ) {
				remove_theme_mod( $field_id );
			}
		}
		$panels = self::all();
		$kept   = array();
		foreach ( $panels as $p ) {
			if ( empty( $p['opt_name'] ) || $p['opt_name'] !== $opt_name ) {
				$kept[] = $p;
			}
		}
		return update_option( self::OPTION_KEY, $kept, false );
	}

	/** Remove theme_mods for fields present in $old panel but absent in $new panel. */
	private static function purge_removed_fields( array $old_panel, array $new_panel ) {
		$removed = array_diff( self::collect_field_ids( $old_panel ), self::collect_field_ids( $new_panel ) );
		foreach ( $removed as $field_id ) {
			remove_theme_mod( $field_id );
		}
	}

	/** Returns a flat list of all top-level field IDs in a panel (sections → rows → fields). */
	private static function collect_field_ids( array $panel ) {
		$ids = array();
		foreach ( $panel['sections'] ?? array() as $section ) {
			foreach ( $section['rows'] ?? array() as $row ) {
				foreach ( $row['fields'] ?? array() as $field ) {
					if ( ! empty( $field['id'] ) ) {
						$ids[] = $field['id'];
					}
				}
			}
		}
		return $ids;
	}

	/**
	 * Normalize a schema coming in from the builder UI.
	 * Defensive sanitization — values were already JSON-decoded by REST,
	 * but keys/strings need cleaning before persisting.
	 *
	 * @param array $panel
	 * @return array
	 */
	public static function sanitize_schema( array $panel ) {
		$clean = array(
			'opt_name'      => isset( $panel['opt_name'] )    ? sanitize_key( $panel['opt_name'] ) : '',
			'menu_title'    => isset( $panel['menu_title'] )  ? sanitize_text_field( $panel['menu_title'] ) : '',
			'page_title'    => isset( $panel['page_title'] )  ? sanitize_text_field( $panel['page_title'] ) : '',
			'menu_slug'     => isset( $panel['menu_slug'] )   ? sanitize_key( $panel['menu_slug'] ) : '',
			'menu_icon'     => isset( $panel['menu_icon'] )   ? sanitize_text_field( $panel['menu_icon'] ) : 'dashicons-admin-generic',
			'menu_position' => isset( $panel['menu_position'] ) ? (int) $panel['menu_position'] : 60,
			'capability'    => isset( $panel['capability'] )  ? sanitize_key( $panel['capability'] ) : 'manage_options',
			'output_css'             => ! empty( $panel['output_css'] ),
			'customizer_integration' => ! empty( $panel['customizer_integration'] ),
			'sections'               => array(),
		);

		// ID prefix applied to auto-generated field/section IDs (set via the
		// builder's Panel Settings). Only persisted when provided so legacy
		// panels stay shape-identical. May be an empty string (prefix cleared).
		if ( isset( $panel['field_prefix'] ) ) {
			$clean['field_prefix'] = sanitize_key( $panel['field_prefix'] );
		}

		// Customizer panel priority (separate from admin menu_position).
		// Only persist when explicitly provided so legacy panels stay
		// shape-identical and fall back to WP's default (160) at render time.
		if ( isset( $panel['customizer_priority'] ) && '' !== (string) $panel['customizer_priority'] && is_numeric( $panel['customizer_priority'] ) ) {
			$clean['customizer_priority'] = (int) $panel['customizer_priority'];
		}

		if ( '' === $clean['menu_slug'] ) {
			$clean['menu_slug'] = $clean['opt_name'];
		}
		if ( '' === $clean['page_title'] ) {
			$clean['page_title'] = $clean['menu_title'];
		}

		if ( ! empty( $panel['sections'] ) && is_array( $panel['sections'] ) ) {
			foreach ( $panel['sections'] as $section ) {
				$clean['sections'][] = self::sanitize_section( (array) $section );
			}
		}

		return $clean;
	}

	protected static function sanitize_section( array $section ) {
		$clean = array(
			'id'          => isset( $section['id'] )          ? sanitize_key( $section['id'] ) : '',
			'title'       => isset( $section['title'] )       ? sanitize_text_field( $section['title'] ) : '',
			'icon'        => isset( $section['icon'] )        ? sanitize_text_field( $section['icon'] ) : '',
			'description' => isset( $section['description'] ) ? sanitize_text_field( $section['description'] ) : '',
			'rows'        => array(),
		);

		// New row-based format.
		if ( ! empty( $section['rows'] ) && is_array( $section['rows'] ) ) {
			foreach ( $section['rows'] as $row ) {
				$clean['rows'][] = self::sanitize_row( (array) $row );
			}
		} elseif ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
			// Migrate legacy flat-fields format into a single default row.
			$clean['rows'][] = array(
				'id'      => 'row_1',
				'layout'  => 'grid',
				'columns' => isset( $section['columns'] ) ? max( 1, min( 12, (int) $section['columns'] ) ) : 1,
				'fields'  => array_map( array( 'TPMeta_Builder_Store', 'sanitize_field_pub' ), $section['fields'] ),
			);
		}

		return $clean;
	}

	protected static function sanitize_row( array $row ) {
		$allowed_layouts = array( 'grid', 'flex' );
		$layout          = isset( $row['layout'] ) && in_array( $row['layout'], $allowed_layouts, true )
			? $row['layout'] : 'grid';

		$allowed_directions = array( 'row', 'column' );
		$direction = isset( $row['direction'] ) && in_array( $row['direction'], $allowed_directions, true )
			? $row['direction'] : 'row';

		$allowed_wraps = array( 'wrap', 'nowrap' );
		$wrap = isset( $row['wrap'] ) && in_array( $row['wrap'], $allowed_wraps, true )
			? $row['wrap'] : 'wrap';

		$clean = array(
			'id'        => isset( $row['id'] )      ? sanitize_key( $row['id'] ) : uniqid( 'row_' ),
			'layout'    => $layout,
			'columns'   => isset( $row['columns'] ) ? max( 1, min( 12, (int) $row['columns'] ) ) : 1,
			'direction' => $direction,
			'wrap'      => $wrap,
			'fields'    => array(),
		);

		if ( ! empty( $row['fields'] ) && is_array( $row['fields'] ) ) {
			foreach ( $row['fields'] as $field ) {
				$clean['fields'][] = self::sanitize_field( (array) $field );
			}
		}

		return $clean;
	}

	// Public-facing alias so array_map() can call it.
	public static function sanitize_field_pub( $field ) {
		return self::sanitize_field( (array) $field );
	}

	protected static function sanitize_field( array $field ) {
		$type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';

		$clean = array(
			'id'          => isset( $field['id'] )          ? sanitize_key( $field['id'] ) : '',
			'type'        => $type,
			'label'       => isset( $field['label'] )       ? sanitize_text_field( $field['label'] ) : '',
			'description' => isset( $field['description'] ) ? sanitize_text_field( $field['description'] ) : '',
			'placeholder' => isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '',
			'default'     => isset( $field['default'] )     ? self::sanitize_default( $field['default'], $type ) : '',
		);

		if ( in_array( $type, array( 'select', 'checkbox', 'tabs', 'radio_buttonset', 'multicheck', 'multicolor' ), true ) && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			$clean['options'] = array();
			foreach ( $field['options'] as $opt ) {
				$opt = (array) $opt;
				// For multicolor, 'value' is the slot key — sanitize_key keeps it slug-safe.
				$v = isset( $opt['value'] ) ? ( 'multicolor' === $type ? sanitize_key( $opt['value'] ) : sanitize_text_field( $opt['value'] ) ) : '';
				$l = isset( $opt['label'] ) ? sanitize_text_field( $opt['label'] ) : '';
				if ( '' === $v ) continue;
				$clean['options'][] = array( 'value' => $v, 'label' => $l !== '' ? $l : $v );
			}
		}

		// radio_image: label is an image URL — use esc_url_raw instead of sanitize_text_field.
		if ( 'radio_image' === $type && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			$clean['options'] = array();
			foreach ( $field['options'] as $opt ) {
				$opt = (array) $opt;
				$clean['options'][] = array(
					'value' => isset( $opt['value'] ) ? sanitize_text_field( $opt['value'] ) : '',
					'label' => isset( $opt['label'] ) ? esc_url_raw( $opt['label'] ) : '',
				);
			}
		}

		if ( ! empty( $field['output'] ) && is_array( $field['output'] ) ) {
			$clean['output'] = array();
			foreach ( $field['output'] as $rule ) {
				$rule = (array) $rule;
				if ( empty( $rule['selector'] ) || empty( $rule['property'] ) ) {
					continue;
				}
				$clean['output'][] = array(
					'selector'  => sanitize_text_field( $rule['selector'] ),
					'property'  => sanitize_text_field( $rule['property'] ),
					'important' => ! empty( $rule['important'] ),
				);
			}
		}

		if ( ! empty( $field['conditional'] ) && is_array( $field['conditional'] ) ) {
			$cond = (array) $field['conditional'];
			$op   = isset( $cond['operator'] ) ? $cond['operator'] : '==';
			if ( ! in_array( $op, array( '==', '!=', '>', '<', '>=', '<=' ), true ) ) {
				$op = '==';
			}
			if ( ! empty( $cond['field'] ) ) {
				$clean['conditional'] = array(
					'field'    => sanitize_key( $cond['field'] ),
					'operator' => $op,
					'value'    => isset( $cond['value'] ) ? sanitize_text_field( $cond['value'] ) : '',
				);
			}
		}

		// Switch: preserve data_type ('string' or 'boolean').
		if ( 'switch' === $type && ! empty( $field['data_type'] ) ) {
			$clean['data_type'] = in_array( $field['data_type'], array( 'string', 'boolean' ), true )
				? $field['data_type'] : 'string';
		}

		// Image: preserve return_format ('array' | 'id' | 'url').
		if ( 'image' === $type && ! empty( $field['return_format'] ) ) {
			$clean['return_format'] = in_array( $field['return_format'], array( 'array', 'id', 'url' ), true )
				? $field['return_format'] : 'array';
		}

		// Post Select: preserve all query-config fields.
		if ( 'post_select' === $type ) {
			if ( ! empty( $field['post_type'] ) ) {
				$clean['post_type'] = sanitize_key( $field['post_type'] );
			}
			if ( ! empty( $field['save_format'] ) ) {
				$clean['save_format'] = in_array( $field['save_format'], array( 'id', 'slug' ), true )
					? $field['save_format'] : 'id';
			}
			if ( ! empty( $field['initial_limit'] ) ) {
				$clean['initial_limit'] = max( 1, min( 200, (int) $field['initial_limit'] ) );
			}
			if ( ! empty( $field['taxonomy_filter'] ) && is_array( $field['taxonomy_filter'] ) ) {
				$tf = (array) $field['taxonomy_filter'];
				$clean['taxonomy_filter'] = array(
					'taxonomy' => isset( $tf['taxonomy'] ) ? sanitize_key( $tf['taxonomy'] ) : '',
					'term'     => isset( $tf['term'] )     ? sanitize_text_field( $tf['term'] ) : '',
				);
			}
			if ( ! empty( $field['meta_filters'] ) && is_array( $field['meta_filters'] ) ) {
				$clean['meta_filters'] = array();
				foreach ( $field['meta_filters'] as $mf ) {
					$mf = (array) $mf;
					if ( empty( $mf['key'] ) ) {
						continue;
					}
					$clean['meta_filters'][] = array(
						'key'   => sanitize_key( $mf['key'] ),
						'value' => isset( $mf['value'] ) ? sanitize_text_field( $mf['value'] ) : '',
					);
				}
			}
		}

		// CSS Target: opt-in per-field-def feature. Persist only when
		// enabled so legacy fields stay shape-identical. Property defaults
		// to 'padding' on read; only stored when explicitly set.
		if ( ! empty( $field['css_target'] ) && is_array( $field['css_target'] ) ) {
			$ct = (array) $field['css_target'];
			if ( ! empty( $ct['enabled'] ) ) {
				$prop = isset( $ct['property'] ) ? sanitize_text_field( $ct['property'] ) : '';
				$sel  = isset( $ct['selector'] ) ? sanitize_text_field( $ct['selector'] ) : '';
				$clean['css_target'] = array(
					'enabled'   => true,
					'property'  => '' !== $prop ? $prop : 'padding',
					'selector'  => $sel,
					'important' => ! empty( $ct['important'] ),
				);
			}
		}

		// Dimension: preserve range-slider config (units / min / max / step).
		// Stored value is still a scalar "150px" string; these only configure
		// the slider UI bounds rendered by the field template.
		if ( 'dimension' === $type ) {
			if ( isset( $field['units'] ) && is_array( $field['units'] ) ) {
				$_u = array_values( array_filter( array_map( 'sanitize_text_field', $field['units'] ) ) );
				if ( ! empty( $_u ) ) {
					$clean['units'] = $_u;
				}
			}
			foreach ( array( 'min', 'max', 'step' ) as $_k ) {
				if ( isset( $field[ $_k ] ) && '' !== (string) $field[ $_k ] && is_numeric( $field[ $_k ] ) ) {
					$clean[ $_k ] = 0 + $field[ $_k ];
				}
			}
		}

		// Repeater: column layout (1–4).
		if ( 'repeater' === $type && isset( $field['columns'] ) ) {
			$clean['columns'] = max( 1, min( 4, (int) $field['columns'] ) );
		}

		// Repeater: sanitize and preserve the sub-fields definition array.
		if ( 'repeater' === $type && ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$clean['fields'] = array();
			$allowed_sf_types = array(
					'text', 'textarea', 'select', 'radio_buttonset', 'checkbox', 'multicheck',
					'radio_image', 'switch', 'colorpicker', 'color_gradient', 'datepicker',
					'image', 'editor', 'code', 'post_select', 'dimension', 'spacing',
					'typography', 'multicolor',
				);
			foreach ( $field['fields'] as $sf ) {
				$sf      = (array) $sf;
				$sf_type = isset( $sf['type'] ) && in_array( $sf['type'], $allowed_sf_types, true ) ? $sf['type'] : 'text';
				$sf_id   = isset( $sf['id'] ) ? sanitize_key( $sf['id'] ) : '';
				if ( ! $sf_id ) continue;
				$sf_clean = array(
					'id'      => $sf_id,
					'type'    => $sf_type,
					'label'   => isset( $sf['label'] )   ? sanitize_text_field( $sf['label'] ) : '',
					// Use sanitize_default (not a forced string cast) so composite
					// sub-field defaults survive — typography stores its default as
					// an assoc array { font_source, font_family, … }; a (string) cast
					// would flatten it to "Array" and wipe the saved defaults.
					'default' => isset( $sf['default'] ) ? self::sanitize_default( $sf['default'], $sf_type ) : '',
				);
				if ( ! empty( $sf['description'] ) ) {
					$sf_clean['description'] = sanitize_text_field( $sf['description'] );
				}
				if ( in_array( $sf_type, array( 'select', 'radio_buttonset', 'checkbox', 'multicheck', 'radio_image', 'multicolor' ), true ) && ! empty( $sf['options'] ) && is_array( $sf['options'] ) ) {
					$sf_clean['options'] = array();
					foreach ( $sf['options'] as $opt ) {
						$opt = (array) $opt;
						$v   = isset( $opt['value'] ) ? sanitize_text_field( $opt['value'] ) : '';
						if ( '' === $v ) continue;
						$sf_clean['options'][] = array(
							'value' => $v,
							'label' => isset( $opt['label'] ) ? sanitize_text_field( $opt['label'] ) : $v,
						);
					}
				}

				// Post Select sub-field: preserve the post_type binding the modal
				// exposes inline. Only stored when non-empty so existing records
				// without it remain key-free on save.
				if ( 'post_select' === $sf_type && ! empty( $sf['post_type'] ) ) {
					$sf_clean['post_type'] = sanitize_key( $sf['post_type'] );
				}

				// Image sub-field: preserve return_format ('array' | 'id' | 'url').
				if ( 'image' === $sf_type && ! empty( $sf['return_format'] ) ) {
					$sf_clean['return_format'] = in_array( $sf['return_format'], array( 'array', 'id', 'url' ), true )
						? $sf['return_format'] : 'array';
				}

				// Switch sub-field: preserve data_type ('string' | 'boolean')
				// so the renderer/sanitizer/cast filter all see the chosen
				// stored format. Mirrors the top-level switch handling above.
				if ( 'switch' === $sf_type && ! empty( $sf['data_type'] ) ) {
					$sf_clean['data_type'] = in_array( $sf['data_type'], array( 'string', 'boolean' ), true )
						? $sf['data_type'] : 'string';
				}

				// CSS Target sub-field: mirror the top-level branch so repeater
				// sub-field spacing rows can opt in to per-row selector input.
				if ( ! empty( $sf['css_target'] ) && is_array( $sf['css_target'] ) ) {
					$_sf_ct = (array) $sf['css_target'];
					if ( ! empty( $_sf_ct['enabled'] ) ) {
						$_sf_prop = isset( $_sf_ct['property'] ) ? sanitize_text_field( $_sf_ct['property'] ) : '';
						$_sf_sel  = isset( $_sf_ct['selector'] ) ? sanitize_text_field( $_sf_ct['selector'] ) : '';
						$sf_clean['css_target'] = array(
							'enabled'  => true,
							'property' => '' !== $_sf_prop ? $_sf_prop : 'padding',
							'selector' => $_sf_sel,
						);
					}
				}

				// CSS output sub-field: persist selector + property for colorpicker
				// and color_gradient sub-fields so the frontend CSS generator can use them.
				if ( in_array( $sf_type, array( 'colorpicker', 'color_gradient' ), true ) && ! empty( $sf['css_output'] ) && is_array( $sf['css_output'] ) ) {
					$_co_sel  = isset( $sf['css_output']['selector'] ) ? sanitize_text_field( $sf['css_output']['selector'] ) : '';
					$_co_prop = isset( $sf['css_output']['property'] ) ? sanitize_text_field( $sf['css_output']['property'] ) : '';
					if ( '' !== $_co_sel && '' !== $_co_prop ) {
						$sf_clean['css_output'] = array(
							'selector' => $_co_sel,
							'property' => $_co_prop,
						);
					}
				}

				$clean['fields'][] = $sf_clean;
			}
		}

		return $clean;
	}

	protected static function sanitize_default( $value, $type ) {
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', array_map( 'strval', $value ) );
		}
		return sanitize_text_field( (string) $value );
	}
}
