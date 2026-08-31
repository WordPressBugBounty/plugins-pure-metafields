<?php
/**
 * Field: typography — modern composite font control.
 *
 * Sub-values stored as field_id[key].
 * Keys: font_source, font_family, font_url, font_size, font_size_unit,
 *       font_weight, font_style, line_height, letter_spacing, text_transform, color
 *
 * NOTE: All input name="field_id[key]" attributes, element IDs (uid_*),
 * and JS-hook class names (.tptypo-src-tab, .tptypo-system-btn, .tptypo-gf-item,
 * .tptypo-tag-btn, .tptypo-toggle, .tptypo-pill-remove, .tptypo-panel)
 * are part of the public contract and MUST NOT be renamed.
 *
 * @var array  $field
 * @var string $id
 * @var mixed  $value
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$def = isset( $field['default'] ) && is_array( $field['default'] ) ? $field['default'] : array();
$val = is_array( $value ) ? $value : array();

// Resolve a key from saved value → default → fallback.
$v = function( $key, $dash_key = '', $fallback = '' ) use ( $val, $def ) {
	if ( isset( $val[ $key ] ) && '' !== $val[ $key ] ) return $val[ $key ];
	if ( $dash_key && isset( $val[ $dash_key ] ) && '' !== $val[ $dash_key ] ) return $val[ $dash_key ];
	if ( isset( $def[ $key ] ) && '' !== $def[ $key ] ) return $def[ $key ];
	if ( $dash_key && isset( $def[ $dash_key ] ) && '' !== $def[ $dash_key ] ) return $def[ $dash_key ];
	return $fallback;
};

$font_source    = $v( 'font_source',    '',               'google' );
$font_family    = $v( 'font_family',    'font-family',    '' );
$font_url       = $v( 'font_url',       '',               '' );
$font_size      = $v( 'font_size',      'font-size',      '' );
$font_size_unit = $v( 'font_size_unit', '',               'px' );
$font_weight    = $v( 'font_weight',    'font-weight',    '' );
$font_style     = $v( 'font_style',     'font-style',     'normal' );
$line_height    = $v( 'line_height',    'line-height',    '' );
$letter_spacing = $v( 'letter_spacing', 'letter-spacing', '0' );
$text_transform = $v( 'text_transform', 'text-transform', 'none' );
$color          = $v( 'color',          '',               '#000000' );

$uid = 'tptypo_' . esc_attr( $id );

$weights = array(
	''    => '— Default —',
	'100' => 'Thin 100',
	'200' => 'Extra Light 200',
	'300' => 'Light 300',
	'400' => 'Regular 400',
	'500' => 'Medium 500',
	'600' => 'Semi Bold 600',
	'700' => 'Bold 700',
	'800' => 'Extra Bold 800',
	'900' => 'Black 900',
);

$transforms = array(
	'none'       => array( 'label' => 'Ag',  'title' => 'None' ),
	'capitalize' => array( 'label' => 'Ag',  'title' => 'Capitalize' ),
	'uppercase'  => array( 'label' => 'AG',  'title' => 'Uppercase' ),
	'lowercase'  => array( 'label' => 'ag',  'title' => 'Lowercase' ),
);

$system_fonts = array(
	'Arial, Helvetica, sans-serif'               => 'Arial',
	"'Arial Black', Gadget, sans-serif"           => 'Arial Black',
	"Georgia, 'Times New Roman', serif"          => 'Georgia',
	"'Times New Roman', Times, serif"            => 'Times New Roman',
	"'Trebuchet MS', Helvetica, sans-serif"       => 'Trebuchet MS',
	"Verdana, Geneva, sans-serif"                => 'Verdana',
	"Tahoma, Geneva, sans-serif"                 => 'Tahoma',
	"Impact, Charcoal, sans-serif"               => 'Impact',
	"'Courier New', Courier, monospace"          => 'Courier New',
	"'Lucida Console', Monaco, monospace"        => 'Lucida Console',
	"system-ui, -apple-system, sans-serif"       => 'System UI',
	"ui-sans-serif, system-ui, sans-serif"       => 'UI Sans',
	"ui-serif, Georgia, serif"                   => 'UI Serif',
	"ui-monospace, 'Cascadia Code', monospace"   => 'UI Mono',
);
?>
<div class="tptypo-wrap" id="<?php echo esc_attr( $uid ); ?>">

	<!-- ═════════════════════════════════════════════════════
	     SECTION 1 — Font Source
	     ═════════════════════════════════════════════════════ -->
	<section class="tptypo-section">
		<header class="tptypo-section-head">
			<span class="tptypo-section-title"><?php esc_html_e( 'Font Source', 'pure-metafields' ); ?></span>
		</header>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Source', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<div class="tptypo-source-tabs" role="tablist">
					<button type="button" class="tptypo-src-tab<?php echo 'google' === $font_source ? ' is-active' : ''; ?>"
						data-src="google" role="tab" aria-selected="<?php echo 'google' === $font_source ? 'true' : 'false'; ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-1.96 1.25-5.54 3.69-.52.36-1 .53-1.42.52-.47-.01-1.37-.26-2.03-.48-.82-.27-1.47-.42-1.42-.88.03-.25.38-.51 1.07-.78 4.19-1.82 6.98-3.02 8.38-3.61 3.99-1.66 4.82-1.95 5.36-1.96.12 0 .38.03.55.17.14.12.18.28.2.45-.02.07-.02.19-.04.27z" fill="currentColor"/></svg>
						<?php esc_html_e( 'Google Fonts', 'pure-metafields' ); ?>
					</button>
					<button type="button" class="tptypo-src-tab<?php echo 'custom' === $font_source ? ' is-active' : ''; ?>"
						data-src="custom" role="tab" aria-selected="<?php echo 'custom' === $font_source ? 'true' : 'false'; ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z" fill="currentColor"/></svg>
						<?php esc_html_e( 'Custom Font', 'pure-metafields' ); ?>
					</button>
					<button type="button" class="tptypo-src-tab<?php echo 'system' === $font_source ? ' is-active' : ''; ?>"
						data-src="system" role="tab" aria-selected="<?php echo 'system' === $font_source ? 'true' : 'false'; ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H4V5h16v14zM6 7h2v2H6V7zm0 4h2v2H6v-2zm0 4h8v2H6v-2zm4-8h8v2h-8V7zm0 4h6v2h-6v-2z" fill="currentColor"/></svg>
						<?php esc_html_e( 'System', 'pure-metafields' ); ?>
					</button>
				</div>
				<input type="hidden" name="<?php echo esc_attr( $id ); ?>[font_source]"
					id="<?php echo esc_attr( $uid ); ?>_source"
					value="<?php echo esc_attr( $font_source ); ?>" />
			</div>
		</div>
	</section>

	<!-- ═════════════════════════════════════════════════════
	     SECTION 2 — Font Family (source-dependent panels)
	     ═════════════════════════════════════════════════════ -->
	<section class="tptypo-section">
		<header class="tptypo-section-head">
			<span class="tptypo-section-title"><?php esc_html_e( 'Font Family', 'pure-metafields' ); ?></span>
		</header>

		<!-- ── Google Fonts panel: searchable combobox ── -->
		<div class="tptypo-panel" id="<?php echo esc_attr( $uid ); ?>_panel_google"
			<?php echo 'google' !== $font_source ? 'hidden' : ''; ?>>
			<div class="tptypo-row">
				<div class="tptypo-row-label"><?php esc_html_e( 'Google Font', 'pure-metafields' ); ?></div>
				<div class="tptypo-row-ctrl">
					<div class="tptypo-combo" id="<?php echo esc_attr( $uid ); ?>_combo">
						<button type="button"
							class="tptypo-combo-trigger"
							id="<?php echo esc_attr( $uid ); ?>_combo_trigger"
							aria-haspopup="listbox"
							aria-expanded="false">
							<span class="tptypo-combo-current"
								id="<?php echo esc_attr( $uid ); ?>_combo_current"
								data-placeholder="<?php esc_attr_e( 'Select a Google Font…', 'pure-metafields' ); ?>"></span>
							<svg class="tptypo-combo-caret" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M0 0l5 6 5-6z" fill="currentColor"/></svg>
						</button>

						<!-- Popover: contains the existing #..._gf_search + #..._gf_list (IDs preserved) -->
						<div class="tptypo-combo-pop" id="<?php echo esc_attr( $uid ); ?>_combo_pop" hidden>
							<div class="tptypo-gf-search-wrap">
								<span class="tptypo-gf-search-icon" aria-hidden="true">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="currentColor"/></svg>
								</span>
								<input
									type="text"
									id="<?php echo esc_attr( $uid ); ?>_gf_search"
									class="tptypo-gf-search"
									placeholder="<?php esc_attr_e( 'Type to filter fonts…', 'pure-metafields' ); ?>"
									autocomplete="off"
									value="" />
							</div>
							<div class="tptypo-gf-list" id="<?php echo esc_attr( $uid ); ?>_gf_list" role="listbox"></div>
						</div>
					</div>

					<!-- Hidden: carries the selected Google font family name (JS-managed) -->
					<input type="hidden"
						name="<?php echo esc_attr( $id ); ?>[font_family]"
						id="<?php echo esc_attr( $uid ); ?>_gf_val"
						value="<?php echo 'google' === $font_source ? esc_attr( $font_family ) : ''; ?>" />
				</div>
			</div>
		</div>

		<!-- ── Custom upload panel ── -->
		<div class="tptypo-panel" id="<?php echo esc_attr( $uid ); ?>_panel_custom"
			<?php echo 'custom' !== $font_source ? 'hidden' : ''; ?>>

			<div class="tptypo-row">
				<div class="tptypo-row-label"><?php esc_html_e( 'Font File', 'pure-metafields' ); ?></div>
				<div class="tptypo-row-ctrl">
					<div class="tptypo-custom-row">
						<div class="tptypo-custom-preview" id="<?php echo esc_attr( $uid ); ?>_custom_preview">
							<?php echo $font_url ? esc_html( basename( $font_url ) ) : '<span>' . esc_html__( 'No font uploaded', 'pure-metafields' ) . '</span>'; ?>
						</div>
						<button type="button" class="tptypo-upload-btn button"
							id="<?php echo esc_attr( $uid ); ?>_upload_btn">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z" fill="currentColor"/></svg>
							<?php esc_html_e( 'Upload', 'pure-metafields' ); ?>
						</button>
						<button type="button" class="tptypo-upload-remove"
							id="<?php echo esc_attr( $uid ); ?>_upload_remove"
							title="<?php esc_attr_e( 'Remove font', 'pure-metafields' ); ?>"
							aria-label="<?php esc_attr_e( 'Remove font', 'pure-metafields' ); ?>"
							<?php echo $font_url ? '' : 'hidden'; ?>>&#10005;</button>
					</div>
					<input type="hidden"
						name="<?php echo esc_attr( $id ); ?>[font_url]"
						id="<?php echo esc_attr( $uid ); ?>_font_url"
						value="<?php echo esc_attr( $font_url ); ?>" />
				</div>
			</div>

			<div class="tptypo-row">
				<div class="tptypo-row-label"><?php esc_html_e( 'Family Name', 'pure-metafields' ); ?></div>
				<div class="tptypo-row-ctrl">
					<input type="text"
						name="<?php echo 'custom' === $font_source ? esc_attr( $id ) . '[font_family]' : ''; ?>"
						id="<?php echo esc_attr( $uid ); ?>_custom_family"
						class="tptypo-input"
						value="<?php echo 'custom' === $font_source ? esc_attr( $font_family ) : ''; ?>"
						placeholder="<?php esc_attr_e( 'e.g. MyBrandFont', 'pure-metafields' ); ?>" />
				</div>
			</div>
		</div>

		<!-- ── System fonts panel: searchable combobox ── -->
		<div class="tptypo-panel" id="<?php echo esc_attr( $uid ); ?>_panel_system"
			<?php echo 'system' !== $font_source ? 'hidden' : ''; ?>>
			<div class="tptypo-row">
				<div class="tptypo-row-label"><?php esc_html_e( 'System Font', 'pure-metafields' ); ?></div>
				<div class="tptypo-row-ctrl">
					<div class="tptypo-combo" id="<?php echo esc_attr( $uid ); ?>_sys_combo">
						<button type="button"
							class="tptypo-combo-trigger"
							id="<?php echo esc_attr( $uid ); ?>_sys_combo_trigger"
							aria-haspopup="listbox"
							aria-expanded="false">
							<span class="tptypo-combo-current"
								id="<?php echo esc_attr( $uid ); ?>_sys_combo_current"
								data-placeholder="<?php esc_attr_e( 'Select a system font…', 'pure-metafields' ); ?>"></span>
							<svg class="tptypo-combo-caret" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M0 0l5 6 5-6z" fill="currentColor"/></svg>
						</button>

						<!-- Popover: search input + .tptypo-system-btn items (existing click handler still applies) -->
						<div class="tptypo-combo-pop" id="<?php echo esc_attr( $uid ); ?>_sys_combo_pop" hidden>
							<div class="tptypo-gf-search-wrap">
								<span class="tptypo-gf-search-icon" aria-hidden="true">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="currentColor"/></svg>
								</span>
								<input
									type="text"
									id="<?php echo esc_attr( $uid ); ?>_sys_search"
									class="tptypo-gf-search"
									placeholder="<?php esc_attr_e( 'Type to filter system fonts…', 'pure-metafields' ); ?>"
									autocomplete="off"
									value="" />
							</div>
							<div class="tptypo-gf-list tptypo-sys-list" id="<?php echo esc_attr( $uid ); ?>_sys_list" role="listbox">
								<?php foreach ( $system_fonts as $stack => $name ) :
									$is_active = ( $font_family === $stack );
								?>
									<button type="button"
										class="tptypo-gf-item tptypo-system-btn<?php echo $is_active ? ' is-active' : ''; ?>"
										role="option"
										aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
										data-family="<?php echo esc_attr( $stack ); ?>"
										data-name="<?php echo esc_attr( $name ); ?>"
										style="font-family: <?php echo esc_attr( $stack ); ?>;">
										<span class="tptypo-gf-item-name"><?php echo esc_html( $name ); ?></span>
										<span class="tptypo-gf-item-sample">Ag</span>
									</button>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<input type="hidden"
						name="<?php echo 'system' === $font_source ? esc_attr( $id ) . '[font_family]' : ''; ?>"
						id="<?php echo esc_attr( $uid ); ?>_sys_val"
						value="<?php echo 'system' === $font_source ? esc_attr( $font_family ) : ''; ?>" />
				</div>
			</div>
		</div>
	</section>

	<!-- ═════════════════════════════════════════════════════
	     SECTION 3 — Typography Settings
	     ═════════════════════════════════════════════════════ -->
	<section class="tptypo-section">
		<header class="tptypo-section-head">
			<span class="tptypo-section-title"><?php esc_html_e( 'Typography', 'pure-metafields' ); ?></span>
		</header>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Font Size', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<div class="tptypo-size-wrap">
					<input type="number" min="1" max="999" step="1"
						class="tptypo-input tptypo-num"
						name="<?php echo esc_attr( $id ); ?>[font_size]"
						id="<?php echo esc_attr( $uid ); ?>_size"
						value="<?php echo esc_attr( preg_replace( '/[^0-9.]/', '', $font_size ) ); ?>"
						placeholder="16" />
					<select class="tptypo-unit-sel"
						name="<?php echo esc_attr( $id ); ?>[font_size_unit]"
						id="<?php echo esc_attr( $uid ); ?>_size_unit"
						aria-label="<?php esc_attr_e( 'Font size unit', 'pure-metafields' ); ?>">
						<?php foreach ( array( 'px', 'em', 'rem', 'vw', '%' ) as $unit ) : ?>
							<option value="<?php echo esc_attr( $unit ); ?>"
								<?php selected( $font_size_unit, $unit ); ?>>
								<?php echo esc_html( $unit ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Font Weight', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<select class="tptypo-select"
					name="<?php echo esc_attr( $id ); ?>[font_weight]"
					id="<?php echo esc_attr( $uid ); ?>_weight">
					<?php foreach ( $weights as $wval => $wlabel ) : ?>
						<option value="<?php echo esc_attr( $wval ); ?>"
							<?php selected( $font_weight, $wval ); ?>>
							<?php echo esc_html( $wlabel ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Font Style', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<div class="tptypo-toggle-grp">
					<?php foreach ( array( 'normal' => 'Normal', 'italic' => '<em>Italic</em>' ) as $sv => $sl ) : ?>
						<label class="tptypo-toggle<?php echo $font_style === $sv ? ' is-active' : ''; ?>">
							<input type="radio"
								name="<?php echo esc_attr( $id ); ?>[font_style]"
								value="<?php echo esc_attr( $sv ); ?>"
								<?php checked( $font_style, $sv ); ?> />
							<?php echo wp_kses( $sl, array( 'em' => array() ) ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- ═════════════════════════════════════════════════════
	     SECTION 4 — Advanced (line-height / letter-spacing / transform)
	     ═════════════════════════════════════════════════════ -->
	<section class="tptypo-section">
		<header class="tptypo-section-head">
			<span class="tptypo-section-title"><?php esc_html_e( 'Advanced', 'pure-metafields' ); ?></span>
		</header>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Line Height', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<input type="text"
					class="tptypo-input"
					name="<?php echo esc_attr( $id ); ?>[line_height]"
					id="<?php echo esc_attr( $uid ); ?>_lh"
					value="<?php echo esc_attr( $line_height ); ?>"
					placeholder="1.6" />
			</div>
		</div>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Letter Spacing', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<input type="text"
					class="tptypo-input"
					name="<?php echo esc_attr( $id ); ?>[letter_spacing]"
					id="<?php echo esc_attr( $uid ); ?>_ls"
					value="<?php echo esc_attr( $letter_spacing ); ?>"
					placeholder="0px" />
			</div>
		</div>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Text Transform', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<select class="tptypo-select"
					name="<?php echo esc_attr( $id ); ?>[text_transform]"
					id="<?php echo esc_attr( $uid ); ?>_tx">
					<?php foreach ( $transforms as $tv => $tdata ) : ?>
						<option value="<?php echo esc_attr( $tv ); ?>"
							style="text-transform: <?php echo esc_attr( $tv ); ?>;"
							<?php selected( $text_transform, $tv ); ?>>
							<?php echo esc_html( $tdata['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	</section>

	<!-- ═════════════════════════════════════════════════════
	     SECTION 5 — Color
	     ═════════════════════════════════════════════════════ -->
	<section class="tptypo-section">
		<header class="tptypo-section-head">
			<span class="tptypo-section-title"><?php esc_html_e( 'Color', 'pure-metafields' ); ?></span>
		</header>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Text Color', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<div class="tptypo-color-row">
					<input type="color"
						class="tptypo-color-native"
						id="<?php echo esc_attr( $uid ); ?>_color_native"
						value="<?php echo esc_attr( $color ?: '#000000' ); ?>"
						aria-label="<?php esc_attr_e( 'Pick text color', 'pure-metafields' ); ?>" />
					<div class="tptypo-color-swatch" id="<?php echo esc_attr( $uid ); ?>_swatch"
						style="background: <?php echo esc_attr( $color ?: '#000000' ); ?>;"
						role="button"
						tabindex="0"
						aria-label="<?php esc_attr_e( 'Open color picker', 'pure-metafields' ); ?>"></div>
					<input type="text"
						class="tptypo-input tptypo-color-text"
						name="<?php echo esc_attr( $id ); ?>[color]"
						id="<?php echo esc_attr( $uid ); ?>_color_text"
						value="<?php echo esc_attr( $color ); ?>"
						placeholder="#000000"
						maxlength="25" />
				</div>
			</div>
		</div>
	</section>

	<!-- ═════════════════════════════════════════════════════
	     SECTION 6 — Apply To
	     ═════════════════════════════════════════════════════ -->
	<?php
	$selectors = $v( 'selectors', '', '' );

	$tag_groups = array(
		'Headings'  => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ),
		'Text'      => array( 'p', 'a', 'span', 'li', 'label', 'blockquote' ),
		'Structure' => array( 'body', 'header', 'footer', 'nav', 'main', 'section' ),
		'Classes'   => array( '.site-title', '.entry-title', '.entry-content', '.site-description', '.widget-title', '.nav-link', '.btn' ),
	);
	?>
	<section class="tptypo-section tptypo-apply-wrap">
		<header class="tptypo-section-head">
			<span class="tptypo-section-title"><?php esc_html_e( 'Apply To', 'pure-metafields' ); ?></span>
			<span class="tptypo-section-hint"><?php esc_html_e( 'CSS selectors where these styles auto-apply on the frontend.', 'pure-metafields' ); ?></span>
		</header>

		<?php foreach ( $tag_groups as $group_label => $tags ) : ?>
		<div class="tptypo-row tptypo-row--tags">
			<div class="tptypo-row-label"><?php echo esc_html( $group_label ); ?></div>
			<div class="tptypo-row-ctrl">
				<div class="tptypo-tag-row">
					<?php foreach ( $tags as $tag ) : ?>
					<button type="button"
						class="tptypo-tag-btn"
						data-tag="<?php echo esc_attr( $tag ); ?>">
						<?php echo esc_html( $tag ); ?>
					</button>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php endforeach; ?>

		<div class="tptypo-row">
			<div class="tptypo-row-label"><?php esc_html_e( 'Selectors', 'pure-metafields' ); ?></div>
			<div class="tptypo-row-ctrl">
				<div class="tptypo-selector-row">
					<span class="tptypo-selector-icon" aria-hidden="true">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z" fill="currentColor"/></svg>
					</span>
					<input
						type="text"
						class="tptypo-input tptypo-selector-input"
						name="<?php echo esc_attr( $id ); ?>[selectors]"
						id="<?php echo esc_attr( $uid ); ?>_selectors"
						value="<?php echo esc_attr( $selectors ); ?>"
						placeholder="<?php esc_attr_e( 'e.g.  h1, .site-title, .entry-title', 'pure-metafields' ); ?>"
						autocomplete="off" />
					<button type="button" class="tptypo-selector-clear"
						id="<?php echo esc_attr( $uid ); ?>_sel_clear"
						title="<?php esc_attr_e( 'Clear selectors', 'pure-metafields' ); ?>"
						aria-label="<?php esc_attr_e( 'Clear selectors', 'pure-metafields' ); ?>">&#10005;</button>
				</div>
				<div class="tptypo-selector-pills" id="<?php echo esc_attr( $uid ); ?>_pills"></div>
			</div>
		</div>
	</section>

	<!-- ═════════════════════════════════════════════════════
	     SECTION 7 — Live Preview
	     ═════════════════════════════════════════════════════ -->
	<section class="tptypo-section tptypo-preview-wrap">
		<header class="tptypo-section-head">
			<span class="tptypo-section-title"><?php esc_html_e( 'Preview', 'pure-metafields' ); ?></span>
			<span class="tptypo-section-hint"><?php esc_html_e( 'Live preview of the current settings.', 'pure-metafields' ); ?></span>
		</header>
		<div class="tptypo-preview-card">
			<div class="tptypo-preview-text" id="<?php echo esc_attr( $uid ); ?>_preview">
				<?php esc_html_e( 'The quick brown fox jumps over the lazy dog', 'pure-metafields' ); ?>
			</div>
			<div class="tptypo-preview-meta" aria-hidden="true">Aa · 0123456789</div>
		</div>
	</section>

</div>
<?php
// Google Fonts list (top 120 popular fonts — no API key needed).
$google_fonts_json = wp_json_encode( array(
	'Abel','Abril Fatface','Alegreya','Alegreya Sans','Almarai','Alfa Slab One',
	'Arimo','Arvo','Assistant','Barlow','Barlow Condensed','Bebas Neue',
	'Bitter','Bree Serif','Cabin','Cairo','Cantarell','Catamaran','Caveat',
	'Cinzel','Comfortaa','Cormorant Garamond','Crimson Text','Dancing Script',
	'DM Sans','DM Serif Display','Dosis','EB Garamond','Exo','Exo 2',
	'Fira Code','Fira Sans','Fjalla One','Frank Ruhl Libre','Fredoka One',
	'Geologica','Gilda Display','Gothic A1','Heebo','IBM Plex Mono',
	'IBM Plex Sans','IBM Plex Serif','Inter','Josefin Sans','Josefin Slab',
	'Jost','Kanit','Kaushan Script','Lato','Libre Baskerville','Libre Franklin',
	'Lora','Merriweather','Merriweather Sans','Montserrat','Mukta','Mulish',
	'Nanum Gothic','Noto Sans','Noto Serif','Nunito','Nunito Sans','Open Sans',
	'Oswald','Outfit','Overpass','Oxygen','Pacifico','Playfair Display',
	'Poppins','PT Sans','PT Serif','Quattrocento','Quicksand','Raleway',
	'Righteous','Roboto','Roboto Condensed','Roboto Mono','Roboto Slab',
	'Rubik','Sacramento','Satisfy','Signika','Slabo 27px','Source Code Pro',
	'Source Sans 3','Source Serif 4','Space Grotesk','Space Mono','Teko',
	'Titillium Web','Ubuntu','Ubuntu Mono','Unbounded','Urbanist',
	'Varela Round','Vollkorn','Work Sans','Yanone Kaffeesatz','Yrsa','Zilla Slab',
) );
?>
<script>
window.TPMetaTypography = window.TPMetaTypography || {};
window.TPMetaTypography.GF_LIST = window.TPMetaTypography.GF_LIST || <?php echo $google_fonts_json; /* phpcs:ignore */ ?>;
if ( ! window.TPMetaTypography.boot ) {
	/**
	 * Boot one typography field instance. Defined once, called once per
	 * rendered instance — including cloned repeater rows whose inline
	 * <script> never re-executes. Everything is keyed off wrap.id, so each
	 * instance must carry a globally-unique id (the repeater rewrites ids on
	 * clone; nested page-load rows get unique ids from the repeater PHP).
	 */
	window.TPMetaTypography.boot = function ( wrap ) {
	'use strict';

	if ( ! wrap || wrap.dataset.tptypoBooted ) return;
	wrap.dataset.tptypoBooted = '1';

	var uid     = wrap.id;
	var GF_LIST = window.TPMetaTypography.GF_LIST || [];

	// ── Helpers ──────────────────────────────────────────────────────────
	function el( id ) { return document.getElementById( uid + '_' + id ); }

	// Input-name base, read from the always-present font_source hidden input
	// (e.g. name="myfield[font_source]" → "myfield"); falls back to stripping
	// the tptypo_ id prefix. Used to (re)assign the active source's
	// font_family input name so only the chosen source posts a value.
	var srcForId = el( 'source' );
	var fieldId  = ( srcForId && srcForId.name )
		? srcForId.name.replace( /\[.*$/, '' )
		: ( uid.indexOf( 'tptypo_' ) === 0 ? uid.slice( 7 ) : uid );

	// Programmatic .value assignments (font pick, tab switch, colour, selectors)
	// don't fire a change event, so a parent repeater row's listener would never
	// re-collect them. Dispatch a bubbling change — harmless standalone (the
	// wrap-level change handler just re-runs updatePreview), essential in a
	// repeater (the row's [data-sfid] change listener calls syncAll). Mirrors
	// TPMetaGradient.update()'s hidden.dispatchEvent( ... ).
	function emitChange( inp ) {
		if ( inp ) inp.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	var loadedFonts = {};
	function loadGoogleFont( family ) {
		if ( ! family || loadedFonts[ family ] ) return;
		loadedFonts[ family ] = true;
		var url  = 'https://fonts.googleapis.com/css2?family='
			+ encodeURIComponent( family ) + ':wght@400;700&display=swap';
		var link = document.createElement( 'link' );
		link.rel  = 'stylesheet';
		link.href = url;
		document.head.appendChild( link );
	}

	// ── Preview updater ──────────────────────────────────────────────────
	function updatePreview() {
		var prev   = el( 'preview' );
		if ( ! prev ) return;

		var src    = el( 'source' )       ? el( 'source' ).value       : '';
		var family = '';

		if ( src === 'google' ) {
			family = el( 'gf_val' ) ? el( 'gf_val' ).value : '';
		} else if ( src === 'custom' ) {
			family = el( 'custom_family' ) ? el( 'custom_family' ).value : '';
			var fontUrl = el( 'font_url' ) ? el( 'font_url' ).value : '';
			if ( fontUrl && family ) {
				if ( ! loadedFonts[ family ] ) {
					loadedFonts[ family ] = true;
					var style = document.createElement( 'style' );
					style.textContent = '@font-face{font-family:"' + family.replace( /"/g, '' ) + '";src:url("' + fontUrl + '");font-display:swap;}';
					document.head.appendChild( style );
				}
			}
		} else {
			var sysBtn = wrap.querySelector( '.tptypo-system-btn.is-active' );
			family = sysBtn ? sysBtn.dataset.family : '';
		}

		var size      = ( el( 'size' )      ? el( 'size' ).value      : '' );
		var sizeUnit  = ( el( 'size_unit' ) ? el( 'size_unit' ).value : 'px' );
		var weight    = el( 'weight' )      ? el( 'weight' ).value      : '';
		var styleVal  = wrap.querySelector( 'input[name="' + fieldId + '[font_style]"]:checked' );
		var lh        = el( 'lh' )          ? el( 'lh' ).value          : '';
		var ls        = el( 'ls' )          ? el( 'ls' ).value          : '';
		var txform    = el( 'tx' );
		var color     = el( 'color_text' )  ? el( 'color_text' ).value  : '';

		prev.style.fontFamily     = family ? "'" + family + "', sans-serif" : '';
		prev.style.fontSize       = size   ? size + sizeUnit : '';
		prev.style.fontWeight     = weight;
		prev.style.fontStyle      = styleVal  ? styleVal.value  : '';
		prev.style.lineHeight     = lh;
		prev.style.letterSpacing  = ls;
		prev.style.textTransform  = txform ? txform.value : '';
		prev.style.color          = color || '';
	}

	// ── Source tab switching ─────────────────────────────────────────────
	wrap.addEventListener( 'click', function ( e ) {
		var tab = e.target.closest( '.tptypo-src-tab' );
		if ( ! tab ) return;
		var src = tab.dataset.src;

		wrap.querySelectorAll( '.tptypo-src-tab' ).forEach( function ( t ) {
			var on = ( t === tab );
			t.classList.toggle( 'is-active', on );
			t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
		wrap.querySelectorAll( '.tptypo-panel' ).forEach( function ( p ) { p.hidden = true; } );

		var panel = el( 'panel_' + src );
		if ( panel ) panel.hidden = false;

		var sourceInput = el( 'source' );
		if ( sourceInput ) sourceInput.value = src;

		// When switching, clear all family hidden fields and set the right one.
		[ 'gf_val', 'sys_val' ].forEach( function ( k ) {
			var inp = el( k );
			if ( inp ) { inp.name = ''; }
		} );
		var custFam = el( 'custom_family' );
		if ( custFam ) custFam.name = '';

		if ( src === 'google'  && el( 'gf_val' ) )      el( 'gf_val' ).name      = fieldId + '[font_family]';
		if ( src === 'system'  && el( 'sys_val' ) )     el( 'sys_val' ).name     = fieldId + '[font_family]';
		if ( src === 'custom'  && custFam )             custFam.name             = fieldId + '[font_family]';

		updatePreview();
		emitChange( sourceInput );
	} );

	// ── Google Fonts combobox (search + list inside popover) ─────────────
	var gfSearch = el( 'gf_search' );
	var gfList   = el( 'gf_list' );
	var comboTrg = el( 'combo_trigger' );
	var comboPop = el( 'combo_pop' );
	var comboCur = el( 'combo_current' );

	function updateComboLabel() {
		if ( ! comboCur ) return;
		var cur = el( 'gf_val' ) ? el( 'gf_val' ).value : '';
		if ( cur ) {
			comboCur.textContent      = cur;
			comboCur.style.fontFamily = "'" + cur + "', sans-serif";
			comboCur.classList.remove( 'is-placeholder' );
		} else {
			comboCur.textContent      = comboCur.dataset.placeholder || '';
			comboCur.style.fontFamily = '';
			comboCur.classList.add( 'is-placeholder' );
		}
	}

	function openCombo() {
		if ( ! comboPop || ! comboTrg ) return;
		comboPop.hidden = false;
		comboTrg.setAttribute( 'aria-expanded', 'true' );
		comboTrg.classList.add( 'is-open' );
		if ( gfSearch ) {
			gfSearch.value = '';
			buildGFList( '' );
			setTimeout( function () { gfSearch.focus(); }, 0 );
		}
	}
	function closeCombo() {
		if ( ! comboPop || ! comboTrg ) return;
		comboPop.hidden = true;
		comboTrg.setAttribute( 'aria-expanded', 'false' );
		comboTrg.classList.remove( 'is-open' );
	}

	if ( comboTrg ) {
		comboTrg.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( comboPop && comboPop.hidden ) openCombo(); else closeCombo();
		} );
	}
	// Close on outside click
	document.addEventListener( 'click', function ( e ) {
		if ( ! comboPop || comboPop.hidden ) return;
		if ( comboPop.contains( e.target ) || ( comboTrg && comboTrg.contains( e.target ) ) ) return;
		closeCombo();
	} );
	// Close on Escape
	if ( comboPop ) {
		comboPop.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { closeCombo(); if ( comboTrg ) comboTrg.focus(); }
		} );
	}

	function buildGFList( query ) {
		if ( ! gfList ) return;
		var q    = ( query || '' ).toLowerCase().trim();
		var hits = GF_LIST.filter( function ( f ) {
			return ! q || f.toLowerCase().indexOf( q ) !== -1;
		} ).slice( 0, 60 );

		var html    = '';
		var current = el( 'gf_val' ) ? el( 'gf_val' ).value : '';
		hits.forEach( function ( f ) {
			loadGoogleFont( f );
			var active = ( f === current ) ? ' is-active' : '';
			html += '<button type="button" class="tptypo-gf-item' + active + '"'
				+ ' role="option" aria-selected="' + ( active ? 'true' : 'false' ) + '"'
				+ ' data-family="' + f.replace( /"/g, '&quot;' ) + '"'
				+ ' style="font-family:\'' + f.replace( /'/g, "\\'" ) + '\',sans-serif">'
				+ '<span class="tptypo-gf-item-name">' + f + '</span>'
				+ '<span class="tptypo-gf-item-sample">Ag</span>'
				+ '</button>';
		} );
		gfList.innerHTML = html || '<span class="tptypo-gf-empty">No fonts found.</span>';
	}

	if ( gfSearch ) {
		gfSearch.addEventListener( 'input', function () { buildGFList( this.value ); } );
		buildGFList( '' );
	}

	if ( gfList ) {
		gfList.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.tptypo-gf-item' );
			if ( ! btn ) return;
			var family = btn.dataset.family;
			gfList.querySelectorAll( '.tptypo-gf-item' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b === btn );
				b.setAttribute( 'aria-selected', b === btn ? 'true' : 'false' );
			} );
			var gfVal = el( 'gf_val' );
			if ( gfVal ) gfVal.value = family;
			loadGoogleFont( family );
			updateComboLabel();
			updatePreview();
			closeCombo();
			emitChange( gfVal );
		} );
	}

	// ── System fonts combobox (search + list inside popover) ─────────────
	var sysTrg    = el( 'sys_combo_trigger' );
	var sysPop    = el( 'sys_combo_pop' );
	var sysCur    = el( 'sys_combo_current' );
	var sysSearch = el( 'sys_search' );
	var sysList   = el( 'sys_list' );

	function updateSysComboLabel() {
		if ( ! sysCur ) return;
		var cur = el( 'sys_val' ) ? el( 'sys_val' ).value : '';
		if ( cur && sysList ) {
			var match = sysList.querySelector( '.tptypo-system-btn[data-family="' + cur.replace( /"/g, '\\"' ) + '"]' );
			if ( match ) {
				sysCur.textContent      = match.dataset.name || cur;
				sysCur.style.fontFamily = cur;
				sysCur.classList.remove( 'is-placeholder' );
				return;
			}
		}
		sysCur.textContent      = sysCur.dataset.placeholder || '';
		sysCur.style.fontFamily = '';
		sysCur.classList.add( 'is-placeholder' );
	}

	function openSysCombo() {
		if ( ! sysPop || ! sysTrg ) return;
		sysPop.hidden = false;
		sysTrg.setAttribute( 'aria-expanded', 'true' );
		sysTrg.classList.add( 'is-open' );
		if ( sysSearch ) {
			sysSearch.value = '';
			filterSysList( '' );
			setTimeout( function () { sysSearch.focus(); }, 0 );
		}
	}
	function closeSysCombo() {
		if ( ! sysPop || ! sysTrg ) return;
		sysPop.hidden = true;
		sysTrg.setAttribute( 'aria-expanded', 'false' );
		sysTrg.classList.remove( 'is-open' );
	}

	function filterSysList( query ) {
		if ( ! sysList ) return;
		var q     = ( query || '' ).toLowerCase().trim();
		var items = sysList.querySelectorAll( '.tptypo-system-btn' );
		var shown = 0;
		items.forEach( function ( item ) {
			var name = ( item.dataset.name || '' ).toLowerCase();
			var fam  = ( item.dataset.family || '' ).toLowerCase();
			var hit  = ! q || name.indexOf( q ) !== -1 || fam.indexOf( q ) !== -1;
			item.hidden = ! hit;
			if ( hit ) shown++;
		} );
		var empty = sysList.querySelector( '.tptypo-gf-empty' );
		if ( ! shown ) {
			if ( ! empty ) {
				empty = document.createElement( 'span' );
				empty.className = 'tptypo-gf-empty';
				empty.textContent = 'No fonts found.';
				sysList.appendChild( empty );
			}
			empty.hidden = false;
		} else if ( empty ) {
			empty.hidden = true;
		}
	}

	if ( sysTrg ) {
		sysTrg.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( sysPop && sysPop.hidden ) openSysCombo(); else closeSysCombo();
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		if ( ! sysPop || sysPop.hidden ) return;
		if ( sysPop.contains( e.target ) || ( sysTrg && sysTrg.contains( e.target ) ) ) return;
		closeSysCombo();
	} );
	if ( sysPop ) {
		sysPop.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { closeSysCombo(); if ( sysTrg ) sysTrg.focus(); }
		} );
	}
	if ( sysSearch ) {
		sysSearch.addEventListener( 'input', function () { filterSysList( this.value ); } );
	}

	// ── System font selection ────────────────────────────────────────────
	// Still works via the .tptypo-system-btn class — only addition is closing
	// the popover and refreshing the trigger label after a pick.
	wrap.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.tptypo-system-btn' );
		if ( ! btn ) return;
		var family = btn.dataset.family;
		wrap.querySelectorAll( '.tptypo-system-btn' ).forEach( function ( b ) {
			b.classList.toggle( 'is-active', b === btn );
			b.setAttribute( 'aria-selected', b === btn ? 'true' : 'false' );
		} );
		var sysVal = el( 'sys_val' );
		if ( sysVal ) sysVal.value = family;
		updateSysComboLabel();
		updatePreview();
		closeSysCombo();
		emitChange( sysVal );
	} );

	// ── Custom font upload ───────────────────────────────────────────────
	var uploadBtn    = el( 'upload_btn' );
	var uploadRemove = el( 'upload_remove' );
	var fontUrlInput = el( 'font_url' );
	var customPrev   = el( 'custom_preview' );

	function setCustomFont( url, filename ) {
		if ( fontUrlInput ) fontUrlInput.value = url;
		if ( customPrev ) {
			if ( url ) {
				customPrev.textContent = filename || url.split( '/' ).pop();
			} else {
				customPrev.innerHTML = '<span>No font uploaded</span>';
			}
		}
		if ( uploadRemove ) uploadRemove.hidden = ! url;
		updatePreview();
		emitChange( fontUrlInput );
	}

	if ( uploadBtn ) {
		uploadBtn.addEventListener( 'click', function () {
			if ( typeof wp === 'undefined' || ! wp.media ) {
				alert( 'Media library not available. Please refresh the page and try again.' );
				return;
			}
			var frame = wp.media( {
				title:    'Select Font File',
				button:   { text: 'Use this font' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				setCustomFont( att.url, att.filename || '' );
			} );
			frame.open();
		} );
	}

	if ( uploadRemove ) {
		uploadRemove.addEventListener( 'click', function () { setCustomFont( '', '' ); } );
	}

	// ── Color sync ───────────────────────────────────────────────────────
	var colorNative = el( 'color_native' );
	var colorText   = el( 'color_text' );
	var colorSwatch = el( 'swatch' );

	function syncColor( hex ) {
		if ( colorNative ) colorNative.value = hex;
		if ( colorText   ) colorText.value   = hex;
		if ( colorSwatch ) colorSwatch.style.background = hex;
		updatePreview();
		emitChange( colorText ); // colorText carries the [color] name; native picker sets it programmatically
	}

	if ( colorSwatch && colorNative ) {
		colorSwatch.addEventListener( 'click',   function () { colorNative.click(); } );
		colorSwatch.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); colorNative.click(); }
		} );
	}
	if ( colorNative ) colorNative.addEventListener( 'input', function () { syncColor( this.value ); } );
	if ( colorText   ) colorText.addEventListener( 'input', function () {
		if ( /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test( this.value ) ) syncColor( this.value );
		else updatePreview();
	} );

	// ── Live update on any control change ────────────────────────────────
	wrap.addEventListener( 'change', updatePreview );
	wrap.addEventListener( 'input',  updatePreview );

	// ── Apply To — tag buttons + selector pills ───────────────────────────
	var selInput  = el( 'selectors' );
	var clearBtn  = el( 'sel_clear' );
	var pillsWrap = el( 'pills' );

	function parseSelectors( str ) {
		return ( str || '' ).split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
	}

	function syncTagButtons() {
		var active = parseSelectors( selInput ? selInput.value : '' );
		wrap.querySelectorAll( '.tptypo-tag-btn' ).forEach( function ( btn ) {
			btn.classList.toggle( 'is-active', active.indexOf( btn.dataset.tag ) !== -1 );
		} );
		renderPills( active );
	}

	function renderPills( list ) {
		if ( ! pillsWrap ) return;
		if ( ! list.length ) { pillsWrap.innerHTML = ''; return; }
		pillsWrap.innerHTML = list.map( function ( sel ) {
			return '<span class="tptypo-pill">'
				+ '<code>' + sel.replace( /</g, '&lt;' ) + '</code>'
				+ '<button type="button" class="tptypo-pill-remove" aria-label="Remove" data-sel="' + sel.replace( /"/g, '&quot;' ) + '">&times;</button>'
				+ '</span>';
		} ).join( '' );
	}

	wrap.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.tptypo-tag-btn' );
		if ( ! btn || ! selInput ) return;
		var tag  = btn.dataset.tag;
		var list = parseSelectors( selInput.value );
		var idx  = list.indexOf( tag );
		if ( idx === -1 ) { list.push( tag ); } else { list.splice( idx, 1 ); }
		selInput.value = list.join( ', ' );
		syncTagButtons();
		emitChange( selInput );
	} );

	if ( pillsWrap ) {
		pillsWrap.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.tptypo-pill-remove' );
			if ( ! btn || ! selInput ) return;
			var sel  = btn.dataset.sel;
			var list = parseSelectors( selInput.value ).filter( function ( s ) { return s !== sel; } );
			selInput.value = list.join( ', ' );
			syncTagButtons();
			emitChange( selInput );
		} );
	}

	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', function () {
			if ( selInput ) selInput.value = '';
			syncTagButtons();
			emitChange( selInput );
		} );
	}

	if ( selInput ) {
		selInput.addEventListener( 'input', syncTagButtons );
	}

	syncTagButtons();

	// ── Toggle groups (Style / Transform) — keep is-active exclusive ──────
	wrap.addEventListener( 'change', function ( e ) {
		var radio = e.target;
		if ( radio.type !== 'radio' ) return;
		var grp = radio.closest( '.tptypo-toggle-grp' );
		if ( ! grp ) return;
		grp.querySelectorAll( '.tptypo-toggle' ).forEach( function ( lbl ) {
			var inp = lbl.querySelector( 'input[type="radio"]' );
			lbl.classList.toggle( 'is-active', !! ( inp && inp.checked ) );
		} );
	} );

	// ── Init: load saved Google font if applicable ────────────────────────
	// Read from the DOM (not PHP) so the once-defined boot stays per-instance.
	var bootSrc = el( 'source' ) ? el( 'source' ).value : '';
	if ( bootSrc === 'google' ) {
		var bootFam = el( 'gf_val' ) ? el( 'gf_val' ).value : '';
		if ( bootFam ) loadGoogleFont( bootFam );
	}

	// Init name fields for non-active sources so they don't POST duplicate values.
	( function initNames() {
		var src = el( 'source' ) ? el( 'source' ).value : 'google';
		[ 'gf_val', 'sys_val' ].forEach( function ( k ) {
			var inp = el( k );
			if ( inp && src !== 'google' && k === 'gf_val' )  inp.name = '';
			if ( inp && src !== 'system' && k === 'sys_val' ) inp.name = '';
		} );
		var custFam = el( 'custom_family' );
		if ( custFam && src !== 'custom' ) custFam.name = '';
	} )();

	updateComboLabel();
	updateSysComboLabel();
	updatePreview();
	};
}

// Boot this specific instance. Skip the hidden clone-template row — the
// repeater clones it and calls TPMetaTypography.boot() on the fresh copy.
( function () {
	var wrap = document.getElementById( <?php echo wp_json_encode( $uid ); ?> );
	if ( ! wrap ) return;
	if ( wrap.closest && wrap.closest( '.tp-hidden-template' ) ) return;
	window.TPMetaTypography.boot( wrap );
} )();
</script>
