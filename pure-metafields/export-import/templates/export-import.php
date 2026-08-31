<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="pf-page">
	<div class="pf-page-header">
		<div class="pf-page-header-icon has-logo">
			<img class="pf-page-header-logo" src="<?php echo esc_url( TPMETA_URL . 'assets/img/logo.png' ); ?>" alt="" />
		</div>
		<div class="pf-page-header-title">
			<h1><?php esc_html_e( 'Export / Import', 'pure-metafields' ); ?></h1>
			<p><?php esc_html_e( 'Back up and restore all Pure Fields data — metaboxes, post types, taxonomies, and options panels.', 'pure-metafields' ); ?></p>
		</div>
	</div>

	<div class="pf-page-body">
		<div class="ei-grid">

			<!-- ── EXPORT ─────────────────────────────────────────────── -->
			<div class="pf-content-panel">
				<div class="pf-content-panel-header">
					<div class="pf-card-icon pf-icon-green" style="width:36px;height:36px;border-radius:8px;">
						<span class="dashicons dashicons-download"></span>
					</div>
					<h2><?php esc_html_e( 'Export', 'pure-metafields' ); ?></h2>
				</div>
				<div class="pf-content-panel-body">
					<p style="font-size:13px;color:var(--pf-muted);margin:0 0 20px;">
						<?php esc_html_e( 'Choose what to export. Each selection creates a single JSON file you can import on another site.', 'pure-metafields' ); ?>
					</p>

					<div class="ei-export-cards">

						<div class="ei-export-card" data-type="all">
							<div class="ei-export-card-icon pf-icon-blue">
								<span class="dashicons dashicons-database"></span>
							</div>
							<div class="ei-export-card-info">
								<div class="ei-export-card-label"><?php esc_html_e( 'Export Everything', 'pure-metafields' ); ?></div>
								<div class="ei-export-card-desc"><?php esc_html_e( 'All metafields, post types, taxonomies, and option panels.', 'pure-metafields' ); ?></div>
							</div>
							<button class="pf-btn pf-btn--primary ei-export-btn" data-type="all">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export All', 'pure-metafields' ); ?>
							</button>
						</div>

						<div class="ei-export-card" data-type="metafields">
							<div class="ei-export-card-icon pf-icon-blue">
								<span class="dashicons dashicons-editor-table"></span>
							</div>
							<div class="ei-export-card-info">
								<div class="ei-export-card-label"><?php esc_html_e( 'Metafields', 'pure-metafields' ); ?></div>
								<div class="ei-export-card-desc">
									<span id="ei-count-metafields">—</span> <?php esc_html_e( 'metabox definition(s)', 'pure-metafields' ); ?>
								</div>
							</div>
							<button class="pf-btn ei-export-btn" data-type="metafields">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export', 'pure-metafields' ); ?>
							</button>
						</div>

						<div class="ei-export-card" data-type="post_types">
							<div class="ei-export-card-icon pf-icon-purple">
								<span class="dashicons dashicons-admin-post"></span>
							</div>
							<div class="ei-export-card-info">
								<div class="ei-export-card-label"><?php esc_html_e( 'Post Types', 'pure-metafields' ); ?></div>
								<div class="ei-export-card-desc">
									<span id="ei-count-post_types">—</span> <?php esc_html_e( 'custom post type(s)', 'pure-metafields' ); ?>
								</div>
							</div>
							<button class="pf-btn ei-export-btn" data-type="post_types">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export', 'pure-metafields' ); ?>
							</button>
						</div>

						<div class="ei-export-card" data-type="taxonomies">
							<div class="ei-export-card-icon pf-icon-pink">
								<span class="dashicons dashicons-tag"></span>
							</div>
							<div class="ei-export-card-info">
								<div class="ei-export-card-label"><?php esc_html_e( 'Taxonomies', 'pure-metafields' ); ?></div>
								<div class="ei-export-card-desc">
									<span id="ei-count-taxonomies">—</span> <?php esc_html_e( 'custom taxonomy/ies', 'pure-metafields' ); ?>
								</div>
							</div>
							<button class="pf-btn ei-export-btn" data-type="taxonomies">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export', 'pure-metafields' ); ?>
							</button>
						</div>

						<div class="ei-export-card" data-type="options">
							<div class="ei-export-card-icon pf-icon-orange">
								<span class="dashicons dashicons-admin-customizer"></span>
							</div>
							<div class="ei-export-card-info">
								<div class="ei-export-card-label"><?php esc_html_e( 'Option Panels', 'pure-metafields' ); ?></div>
								<div class="ei-export-card-desc">
									<span id="ei-count-options">—</span> <?php esc_html_e( 'option panel(s)', 'pure-metafields' ); ?>
								</div>
							</div>
							<button class="pf-btn ei-export-btn" data-type="options">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export', 'pure-metafields' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- ── IMPORT ─────────────────────────────────────────────── -->
			<div class="pf-content-panel">
				<div class="pf-content-panel-header">
					<div class="pf-card-icon pf-icon-purple" style="width:36px;height:36px;border-radius:8px;">
						<span class="dashicons dashicons-upload"></span>
					</div>
					<h2><?php esc_html_e( 'Import', 'pure-metafields' ); ?></h2>
				</div>
				<div class="pf-content-panel-body">
					<p style="font-size:13px;color:var(--pf-muted);margin:0 0 20px;">
						<?php esc_html_e( 'Import a Pure Fields JSON file. Existing items with the same ID/slug will be overwritten.', 'pure-metafields' ); ?>
					</p>

					<div class="ei-dropzone" id="ei-dropzone">
						<span class="dashicons dashicons-upload ei-dropzone-icon"></span>
						<p><?php esc_html_e( 'Drop a Pure Fields .json file here', 'pure-metafields' ); ?></p>
						<p style="font-size:12px;color:var(--pf-muted);margin:4px 0 14px;"><?php esc_html_e( 'or', 'pure-metafields' ); ?></p>
						<label class="pf-btn pf-btn--primary">
							<?php esc_html_e( 'Choose File', 'pure-metafields' ); ?>
							<input type="file" id="ei-file-input" accept=".json,application/json" style="display:none;" />
						</label>
						<p class="ei-file-name" id="ei-file-name" style="display:none;"></p>
					</div>

					<div class="ei-import-preview" id="ei-import-preview" style="display:none;">
						<div class="pf-notice pf-notice--info" id="ei-preview-notice"></div>
						<button class="pf-btn pf-btn--primary" id="ei-import-confirm" style="width:100%;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Confirm Import', 'pure-metafields' ); ?>
						</button>
					</div>

					<div id="ei-import-result" style="display:none;"></div>
				</div>
			</div>
		</div>
	</div>
</div>
