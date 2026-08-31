<?php
/**
 * TPMeta_Metafields_Loader
 *
 * Loads metaboxes saved via the Metafield Builder into the tp_meta_boxes filter.
 * Converts sections → rows → fields into the flat fields[] the metabox renderer expects,
 * injecting section_heading pseudo-fields so the post edit page shows labelled groups.
 * Column count is derived from the highest row.columns value across all sections.
 *
 * @package tpmeta
 * @since   1.7.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TPMeta_Metafields_Loader {

	public function __construct() {
		add_filter( 'tp_meta_boxes', array( $this, 'inject' ),           5 );
		add_filter( 'tp_user_meta',  array( $this, 'inject_user_meta' ), 5 );
	}

	public function inject( $metaboxes ) {
		foreach ( TPMeta_Metafields_Store::all() as $box ) {
			// Skip user-target records — they're injected via tp_user_meta below.
			// Records without a 'target' key default to 'post' (legacy behaviour).
			if ( 'user' === ( $box['target'] ?? 'post' ) ) continue;
			if ( ! empty( $box['metabox_id'] ) ) {
				$metaboxes[] = $this->build( $box );
			}
		}
		return $metaboxes;
	}

	/**
	 * Inject user-target builder records into the `tp_user_meta` filter.
	 *
	 * Accepts either the new multi-block shape (array of `{label, fields}`)
	 * or the legacy single-block shape (assoc with a top-level `fields` key)
	 * and normalises to a sequential array so multiple blocks coexist
	 * cleanly. Mirrors `tpmeta_get_user_meta_blocks()` in class-metabox.php.
	 *
	 * @param array $blocks Existing blocks from prior filter callbacks.
	 * @return array
	 */
	public function inject_user_meta( $blocks ) {
		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			$blocks = array();
		} elseif ( isset( $blocks['fields'] ) ) {
			$blocks = array( $blocks );
		} else {
			$blocks = array_values( $blocks );
		}

		foreach ( TPMeta_Metafields_Store::all() as $box ) {
			if ( 'user' !== ( $box['target'] ?? 'post' ) ) continue;
			if ( ! empty( $box['metabox_id'] ) ) {
				$blocks[] = $this->build_user_meta_block( $box );
			}
		}
		return $blocks;
	}

	/**
	 * Shape a stored user-target record as a `tp_user_meta` block. Reuses
	 * `build()` so sections → rows → fields flattening, options/conditional
	 * normalisation, and repeater sub-field handling are shared — then strips
	 * the post-only keys (metabox_id, post_type, context, priority, columns)
	 * and emits the `{label, fields, capability?}` shape user_meta expects.
	 */
	private function build_user_meta_block( array $box ) {
		$flat = $this->build( $box );
		$out  = array(
			'label'  => isset( $flat['title'] )  ? $flat['title']  : ( $box['title']  ?? '' ),
			'fields' => isset( $flat['fields'] ) ? $flat['fields'] : array(),
		);
		if ( ! empty( $box['capability'] ) ) {
			$out['capability'] = $box['capability'];
		}
		return $out;
	}

	/**
	 * Convert sections → rows → fields into a flat fields array.
	 * Also injects section_heading pseudo-fields and derives the column count.
	 */
	private function build( array $box ) {
		$fields   = array();
		$max_cols = max( 1, absint( $box['columns'] ?? 1 ) );

		if ( ! empty( $box['sections'] ) ) {
			foreach ( array_values( (array) $box['sections'] ) as $sec_idx => $section ) {

				// Inject a section heading before each section (always, even if first).
				$fields[] = array(
					'id'          => 'tpmf_sec_' . ( $section['id'] ?? $sec_idx ),
					'type'        => 'section_heading',
					'label'       => $section['title'] ?? '',
					'description' => $section['description'] ?? '',
					'conditional' => array(),
					'default'     => '',
				);

				foreach ( (array) ( $section['rows'] ?? array() ) as $row_idx => $row ) {
					// Track the widest column count so the outer grid is wide enough.
					$row_cols = absint( $row['columns'] ?? 1 ) ?: 1;
					if ( $row_cols > $max_cols ) {
						$max_cols = $row_cols;
					}

					// Row-start pseudo-field: the metabox renderer (see
					// tpmeta_metabox_render) opens a `<div class="tm-meta-row
					// tm-meta-wrapper tm-meta-column-N">` flex wrapper on
					// every `_tpmf_row` marker and closes it before the
					// next row / section_heading / loop end. Carrying the
					// row's own `columns` here is what lets each row render
					// with the column count the user picked in the builder
					// — without this, all fields shared the section's max
					// column count.
					$fields[] = array(
						'id'      => 'tpmf_row_' . ( $section['id'] ?? $sec_idx ) . '_' . $row_idx,
						'type'    => '_tpmf_row',
						'columns' => $row_cols,
					);

					foreach ( (array) ( $row['fields'] ?? array() ) as $f ) {
						$fields[] = $this->normalize_field( $f );
					}
				}
			}
		} elseif ( ! empty( $box['fields'] ) ) {
			// Legacy flat format — pass through unchanged.
			foreach ( (array) $box['fields'] as $f ) {
				$fields[] = $this->normalize_field( $f );
			}
		}

		$out = array(
			'metabox_id' => $box['metabox_id'],
			'title'      => $box['title']    ?? '',
			'post_type'  => $box['post_type'] ?? array(),
			'context'    => $box['context']  ?? 'normal',
			'priority'   => $box['priority'] ?? 'default',
			'columns'    => $max_cols,
			'fields'     => $fields,
		);

		// Forward post_format (binds the metabox to a specific WP post format).
		// Only included when present so the legacy filter consumer's isset()
		// branch in tpmeta_metabox_render keeps working unchanged.
		if ( ! empty( $box['post_format'] ) ) {
			$out['post_format'] = $box['post_format'];
		}

		return $out;
	}

	/**
	 * Normalise a builder-shaped conditional to the indexed [field, op, value]
	 * format every PHP template expects. Accepts both assoc {field, operator,
	 * value} (builder/scanner output) and already-indexed input. Empty/missing
	 * input returns an empty array.
	 */
	private function normalize_conditional( $raw ) {
		if ( empty( $raw ) || ! is_array( $raw ) ) return array();
		if ( isset( $raw['field'] ) && '' !== $raw['field'] ) {
			return array(
				$raw['field'],
				isset( $raw['operator'] ) ? $raw['operator'] : '==',
				isset( $raw['value'] )    ? $raw['value']    : '',
			);
		}
		if ( isset( $raw[0] ) && '' !== $raw[0] ) {
			return array(
				$raw[0],
				isset( $raw[1] ) ? $raw[1] : '==',
				isset( $raw[2] ) ? $raw[2] : '',
			);
		}
		return array();
	}

	private function normalize_field( array $f ) {
		$field = array(
			'id'          => $f['id']          ?? '',
			'type'        => $f['type']         ?? 'text',
			'label'       => $f['label']        ?? '',
			'description' => $f['description']  ?? '',
			'default'     => $f['default']      ?? '',
			'placeholder' => $f['placeholder']  ?? '',
			'conditional' => $this->normalize_conditional( $f['conditional'] ?? array() ),
		);
		foreach ( $f as $k => $v ) {
			if ( ! isset( $field[ $k ] ) ) {
				$field[ $k ] = $v;
			}
		}

		// Builder stores options as [{value,label}] sequential arrays.
		// PHP templates expect assoc {value => label} — normalize here.
		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			$field['options'] = $this->normalize_options( $field['options'] );
		}

		// Multicolor: also accept Kirki-style 'choices' key as slot definitions.
		if ( 'multicolor' === $field['type'] && empty( $field['options'] ) && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
			$field['options'] = $field['choices'];
		}

		// Multicolor: the builder stores the default as a JSON string — decode it so
		// PHP templates can use it as an array directly.
		if ( 'multicolor' === $field['type'] && isset( $field['default'] ) && is_string( $field['default'] ) && '' !== $field['default'] ) {
			$decoded = json_decode( $field['default'], true );
			if ( is_array( $decoded ) ) {
				$field['default'] = $decoded;
			}
		}

		// For repeater fields, normalize each sub-field's options, multicolor
		// defaults, and — critically — conditional. Without this last step the
		// sub-field's conditional stays in builder assoc form {field, operator,
		// value} and templates that read $field['conditional'][1] / [2] (e.g.
		// metaboxes/fields/repeater-group.php) emit "Undefined array key" warnings.
		if ( 'repeater' === $field['type'] && ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
			foreach ( $field['fields'] as $idx => $sf ) {
				$sf      = (array) $sf;
				$updates = array();

				if ( isset( $sf['options'] ) && is_array( $sf['options'] ) ) {
					$updates['options'] = $this->normalize_options( $sf['options'] );
				}

				// Always normalize conditional so sub-fields match top-level shape.
				if ( array_key_exists( 'conditional', $sf ) ) {
					$updates['conditional'] = $this->normalize_conditional( $sf['conditional'] );
				}

				// Multicolor sub-field: decode the JSON-string default so the
				// repeater template receives a plain array, not a string.
				if ( isset( $sf['type'] ) && 'multicolor' === $sf['type']
					&& isset( $sf['default'] ) && is_string( $sf['default'] ) && '' !== $sf['default'] ) {
					$_mc_dec = json_decode( $sf['default'], true );
					if ( is_array( $_mc_dec ) ) {
						$updates['default'] = $_mc_dec;
					}
				}

				if ( ! empty( $updates ) ) {
					$field['fields'][ $idx ] = array_merge( $sf, $updates );
				}
			}
		}

		return $field;
	}

	/**
	 * Convert sequential [{value,label}] option arrays to assoc {value:label}.
	 * Already-assoc arrays are returned unchanged.
	 */
	private function normalize_options( array $opts ) {
		if ( empty( $opts ) ) {
			return $opts;
		}
		// If keys are not 0,1,2... it's already assoc — leave it.
		if ( array_keys( $opts ) !== range( 0, count( $opts ) - 1 ) ) {
			return $opts;
		}
		$assoc = array();
		$idx   = 0;
		foreach ( $opts as $opt ) {
			$opt   = (array) $opt;
			$label = isset( $opt['label'] ) ? $opt['label'] : '';
			if ( ! isset( $opt['value'] ) || '' === $opt['value'] ) {
				// Auto-derive a stable key: prefer sanitized label, fall back to index.
				$value = sanitize_key( $label );
				if ( '' === $value ) {
					$value = 'option_' . $idx;
				}
			} else {
				$value = $opt['value'];
			}
			$assoc[ $value ] = '' !== $label ? $label : $value;
			$idx++;
		}
		return $assoc;
	}
}
