<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$options_on = class_exists( 'TPMeta_Builder_Store' );

$export_rows = array(
	array(
		'type'  => 'metafields',
		'icon'  => 'metafields',
		'label' => __( 'Metaboxes', 'pure-metafields' ),
		'unit'  => __( 'metabox definition(s)', 'pure-metafields' ),
	),
	array(
		'type'  => 'post_types',
		'icon'  => 'post-types',
		'label' => __( 'Post Types', 'pure-metafields' ),
		'unit'  => __( 'custom post type(s)', 'pure-metafields' ),
	),
	array(
		'type'  => 'taxonomies',
		'icon'  => 'taxonomies',
		'label' => __( 'Taxonomies', 'pure-metafields' ),
		'unit'  => __( 'custom taxonomy/ies', 'pure-metafields' ),
	),
);

if ( $options_on ) {
	$export_rows[] = array(
		'type'  => 'options',
		'icon'  => 'options',
		'label' => __( 'Option Panels', 'pure-metafields' ),
		'unit'  => __( 'option panel(s)', 'pure-metafields' ),
	);
}
?>
<div class="pf-page ei-page">

	<header class="ei-head">
		<h1 class="ei-title"><?php esc_html_e( 'Export / Import', 'pure-metafields' ); ?></h1>
		<p class="ei-subtitle"><?php esc_html_e( 'Back up your Pure Fields setup, or move it to another site, as one JSON file.', 'pure-metafields' ); ?></p>
	</header>

	<div class="ei-grid">

		<section class="ei-panel" aria-labelledby="ei-export-heading">
			<div class="ei-panel-head">
				<span class="ei-panel-icon">
					<?php echo TPMeta_Main_Menu::icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
				</span>
				<div>
					<h2 id="ei-export-heading"><?php esc_html_e( 'Export', 'pure-metafields' ); ?></h2>
					<p><?php esc_html_e( 'Each option downloads one JSON file you can import on another site.', 'pure-metafields' ); ?></p>
				</div>
			</div>

			<ul class="ei-rows">
				<li class="ei-row ei-row--all ei-export-card" data-type="all">
					<span class="ei-row-icon">
						<?php echo TPMeta_Main_Menu::icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
					</span>
					<div class="ei-row-info">
						<span class="ei-row-label"><?php esc_html_e( 'Everything', 'pure-metafields' ); ?></span>
						<span class="ei-row-desc">
							<?php
							echo $options_on
								? esc_html__( 'Metaboxes, post types, taxonomies and option panels.', 'pure-metafields' )
								: esc_html__( 'Metaboxes, post types and taxonomies.', 'pure-metafields' );
							?>
						</span>
					</div>
					<button type="button" class="pf-btn pf-btn--primary ei-export-btn" data-type="all"><?php esc_html_e( 'Export All', 'pure-metafields' ); ?></button>
				</li>

				<?php foreach ( $export_rows as $row ) : ?>
				<li class="ei-row ei-export-card" data-type="<?php echo esc_attr( $row['type'] ); ?>">
					<span class="ei-row-icon">
						<?php echo TPMeta_Main_Menu::icon( $row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
					</span>
					<div class="ei-row-info">
						<span class="ei-row-label"><?php echo esc_html( $row['label'] ); ?></span>
						<span class="ei-row-desc">
							<span class="ei-num" id="ei-count-<?php echo esc_attr( $row['type'] ); ?>">—</span>
							<?php echo esc_html( $row['unit'] ); ?>
						</span>
					</div>
					<button type="button" class="pf-btn ei-export-btn" data-type="<?php echo esc_attr( $row['type'] ); ?>"><?php esc_html_e( 'Export', 'pure-metafields' ); ?></button>
				</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="ei-panel" aria-labelledby="ei-import-heading">
			<div class="ei-panel-head">
				<span class="ei-panel-icon">
					<?php echo TPMeta_Main_Menu::icon( 'export-import' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
				</span>
				<div>
					<h2 id="ei-import-heading"><?php esc_html_e( 'Import', 'pure-metafields' ); ?></h2>
					<p><?php esc_html_e( 'Items with the same ID or slug are overwritten. Saved field values on existing posts are kept.', 'pure-metafields' ); ?></p>
				</div>
			</div>

			<div class="ei-dropzone" id="ei-dropzone">
				<?php echo TPMeta_Main_Menu::icon( 'export-import', 'ei-dropzone-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
				<p class="ei-drop-title"><?php esc_html_e( 'Drop a Pure Fields .json file here', 'pure-metafields' ); ?></p>
				<p class="ei-drop-or"><?php esc_html_e( 'or', 'pure-metafields' ); ?></p>
				<label class="pf-btn">
					<?php esc_html_e( 'Choose File', 'pure-metafields' ); ?>
					<input type="file" id="ei-file-input" accept=".json,application/json" style="display:none;" />
				</label>
				<p class="ei-file-name" id="ei-file-name" style="display:none;"></p>
			</div>

			<div class="ei-import-preview" id="ei-import-preview" style="display:none;">
				<div class="pf-notice pf-notice--info" id="ei-preview-notice"></div>
				<button type="button" class="pf-btn pf-btn--primary" id="ei-import-confirm"><?php esc_html_e( 'Confirm Import', 'pure-metafields' ); ?></button>
			</div>

			<div id="ei-import-result" style="display:none;"></div>
		</section>

	</div>
</div>
