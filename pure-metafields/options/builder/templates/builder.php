<?php
/**
 * Builder page shell — JS app mounts into #tpmeta-builder-root.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="tpmeta-builder-page">
	<div id="tpmeta-builder-root">
		<div class="tpmeta-builder-loading">
			<span class="spinner is-active"></span>
			<?php esc_html_e( 'Loading Options Builder…', 'pure-metafields' ); ?>
		</div>
	</div>

	<dialog id="tpmeta-builder-modal" class="tpmeta-builder-modal">
		<form method="dialog" class="tpmeta-builder-modal-form">
			<header class="tpmeta-builder-modal-header">
				<h2 class="tpmeta-builder-modal-title"></h2>
				<button type="button" class="tpmeta-builder-modal-close" aria-label="Close">&times;</button>
			</header>
			<div class="tpmeta-builder-modal-body"></div>
			<footer class="tpmeta-builder-modal-footer">
				<button type="button" class="button tpmeta-builder-modal-cancel">
					<?php esc_html_e( 'Cancel', 'pure-metafields' ); ?>
				</button>
				<button type="button" class="button button-primary tpmeta-builder-modal-save">
					<?php esc_html_e( 'Apply', 'pure-metafields' ); ?>
				</button>
			</footer>
		</form>
	</dialog>

	<dialog id="tpmeta-import-modal" class="tpmeta-builder-modal tpmeta-import-modal">
		<form method="dialog" class="tpmeta-builder-modal-form">
			<header class="tpmeta-builder-modal-header">
				<h2><?php esc_html_e( 'Import Panel', 'pure-metafields' ); ?></h2>
				<button type="button" class="tpmeta-builder-modal-close" aria-label="Close">&times;</button>
			</header>
			<div class="tpmeta-builder-modal-body">

				<div class="tpmeta-import-dropzone" id="tpmeta-import-dropzone">
					<span class="dashicons dashicons-upload tpmeta-import-dropzone-icon"></span>
					<p><?php esc_html_e( 'Drop a .json panel file here', 'pure-metafields' ); ?></p>
					<p class="tpmeta-import-dropzone-or"><?php esc_html_e( 'or', 'pure-metafields' ); ?></p>
					<label class="button tpmeta-import-file-label">
						<?php esc_html_e( 'Choose File', 'pure-metafields' ); ?>
						<input type="file" id="tpmeta-import-file" accept=".json,application/json" style="display:none;" />
					</label>
					<p class="tpmeta-import-file-name" id="tpmeta-import-file-name"></p>
				</div>

				<div class="tpmeta-import-paste-wrap">
					<label for="tpmeta-import-json">
						<?php esc_html_e( 'Or paste JSON', 'pure-metafields' ); ?>
					</label>
					<textarea id="tpmeta-import-json" class="tpmeta-import-json" rows="8"
						placeholder='{"opt_name":"my_panel","menu_title":"My Panel",...}'></textarea>
				</div>

				<div class="tpmeta-import-preview" id="tpmeta-import-preview" style="display:none;">
					<div class="tpmeta-import-preview-icon dashicons dashicons-yes-alt"></div>
					<div class="tpmeta-import-preview-info">
						<strong id="tpmeta-import-preview-name"></strong>
						<span id="tpmeta-import-preview-meta"></span>
					</div>
				</div>

				<div class="tpmeta-import-prefix-wrap" id="tpmeta-import-prefix-wrap" style="display:none;">
					<label for="tpmeta-import-prefix"><?php esc_html_e( 'Field &amp; section ID prefix', 'pure-metafields' ); ?></label>
					<input type="text" id="tpmeta-import-prefix" class="tpmeta-import-prefix" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'e.g. mytheme_', 'pure-metafields' ); ?>" />
					<p class="tpmeta-import-prefix-help description">
						<?php esc_html_e( 'Applied to every field and section ID on import. Leave as detected to keep IDs unchanged, edit it to replace the existing prefix, or clear it to strip the prefix entirely. Saved values and conditional rules are remapped to match.', 'pure-metafields' ); ?>
					</p>
					<p class="tpmeta-import-prefix-example" id="tpmeta-import-prefix-example"></p>
				</div>

				<div class="tpmeta-import-mode" id="tpmeta-import-mode">
					<label class="tpmeta-import-mode-option">
						<input type="radio" name="tpmeta-import-mode" value="full" checked />
						<span class="tpmeta-import-mode-label">
							<strong><?php esc_html_e( 'Import as new panel', 'pure-metafields' ); ?></strong>
							<em><?php esc_html_e( 'Restores menu title, slug, customizer settings, conditions, every field — and saved field values (typography, post selects, switches, repeater rows, …) when the export bundle includes them.', 'pure-metafields' ); ?></em>
						</span>
					</label>
					<label class="tpmeta-import-mode-option">
						<input type="radio" name="tpmeta-import-mode" value="sections" />
						<span class="tpmeta-import-mode-label">
							<strong><?php esc_html_e( 'Replace sections only', 'pure-metafields' ); ?></strong>
							<em><?php esc_html_e( "Inject sections and fields into the currently selected panel. Don't touch panel settings.", 'pure-metafields' ); ?></em>
						</span>
					</label>
				</div>

				<div class="tpmeta-import-notice">
					<span class="dashicons dashicons-info-outline"></span>
					<span class="tpmeta-import-notice-text"><?php esc_html_e( 'The full panel — name, slug, customizer settings, conditions and every field — will be restored exactly as exported.', 'pure-metafields' ); ?></span>
				</div>

			</div>
			<footer class="tpmeta-builder-modal-footer">
				<button type="button" class="button" id="tpmeta-import-cancel">
					<?php esc_html_e( 'Cancel', 'pure-metafields' ); ?>
				</button>
				<button type="button" class="button button-primary" id="tpmeta-import-submit" disabled>
					<?php esc_html_e( 'Import Panel', 'pure-metafields' ); ?>
				</button>
			</footer>
		</form>
	</dialog>

	<dialog id="tpmeta-demos-modal" class="tpmeta-builder-modal tpmeta-demos-modal">
		<form method="dialog" class="tpmeta-builder-modal-form">
			<header class="tpmeta-builder-modal-header">
				<div class="tpmeta-bake-modal-title-wrap">
					<span class="tpmeta-bake-modal-icon dashicons dashicons-images-alt2"></span>
					<h2><?php esc_html_e( 'Starter Templates', 'pure-metafields' ); ?></h2>
				</div>
				<button type="button" class="tpmeta-builder-modal-close" aria-label="Close">&times;</button>
			</header>
			<div class="tpmeta-builder-modal-body">
				<p class="tpmeta-demos-intro">
					<?php esc_html_e( 'Load a ready-made panel. Keep what you need, delete the rest.', 'pure-metafields' ); ?>
				</p>
				<div class="tpmeta-demos-grid" id="tpmeta-demos-grid">
					<div class="tpmeta-demos-loading">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Loading templates…', 'pure-metafields' ); ?>
					</div>
				</div>
			</div>
			<footer class="tpmeta-builder-modal-footer">
				<button type="button" class="button" id="tpmeta-demos-close">
					<?php esc_html_e( 'Close', 'pure-metafields' ); ?>
				</button>
			</footer>
		</form>
	</dialog>

	<!-- ══ Kirki / Redux PHP Import modal ══════════════════════════════════ -->
	<dialog id="tpmeta-kirki-modal" class="tpmeta-builder-modal tpmeta-kirki-modal">
		<form method="dialog" class="tpmeta-builder-modal-form">
			<header class="tpmeta-builder-modal-header">
				<div class="tpmeta-bake-modal-title-wrap">
					<span class="tpmeta-bake-modal-icon dashicons dashicons-editor-code"></span>
					<h2><?php esc_html_e( 'Import from Kirki / Redux PHP', 'pure-metafields' ); ?></h2>
				</div>
				<button type="button" class="tpmeta-builder-modal-close" aria-label="Close">&times;</button>
			</header>

			<div class="tpmeta-builder-modal-body">

				<!-- Step 1 — source selector + paste code -->
				<div class="tpmeta-kirki-step" id="tpmeta-kirki-step-1">

					<!-- Source toggle: Kirki / Redux -->
					<div class="tpmeta-kirki-source-row">
						<span class="tpmeta-kirki-source-label-text"><?php esc_html_e( 'Source:', 'pure-metafields' ); ?></span>
						<div class="tpmeta-kirki-source-tabs">
							<label class="tpmeta-kirki-source-tab is-active" id="tpmeta-source-tab-kirki">
								<input type="radio" name="tpmeta_import_source" value="kirki" checked>
								<span class="dashicons dashicons-admin-customizer"></span>
								Kirki
							</label>
							<label class="tpmeta-kirki-source-tab" id="tpmeta-source-tab-redux">
								<input type="radio" name="tpmeta_import_source" value="redux">
								<span class="dashicons dashicons-admin-settings"></span>
								Redux
							</label>
						</div>
						<span class="tpmeta-kirki-source-hint" id="tpmeta-kirki-source-hint">
							<?php esc_html_e( 'Paste Kirki OOP (new \\Kirki\\Field\\...) or Kirki static API code', 'pure-metafields' ); ?>
						</span>
					</div>

					<div class="tpmeta-kirki-editor-wrap">
						<div class="tpmeta-kirki-editor-header">
							<span class="tpmeta-kirki-editor-label">PHP</span>
							<button type="button" class="tpmeta-kirki-clear-btn" id="tpmeta-kirki-clear">&times; Clear</button>
						</div>
						<textarea
							id="tpmeta-kirki-code"
							class="tpmeta-kirki-code"
							spellcheck="false"
							placeholder="<?php echo esc_attr( "new \\Kirki\\Panel('my_panel', ['title' => 'My Options']);\n\nnew \\Kirki\\Section('my_section', [\n    'title' => 'My Section',\n    'panel' => 'my_panel',\n]);\n\nnew \\Kirki\\Field\\Color([\n    'settings' => 'my_color',\n    'label'    => 'My Color',\n    'section'  => 'my_section',\n    'default'  => '#000000',\n]);" ); ?>"
						></textarea>
					</div>
					<button type="button" class="button button-primary tpmeta-kirki-parse-btn" id="tpmeta-kirki-parse">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Detect Sections & Fields', 'pure-metafields' ); ?>
					</button>
				</div>

				<!-- Step 2 — preview & configure -->
				<div class="tpmeta-kirki-step" id="tpmeta-kirki-step-2" hidden>

					<div class="tpmeta-kirki-summary" id="tpmeta-kirki-summary"></div>

					<div class="tpmeta-kirki-settings">
						<div class="tpmeta-form-grid">
							<div class="tpmeta-form-row">
								<label><?php esc_html_e( 'Panel Title', 'pure-metafields' ); ?></label>
								<input type="text" id="tpmeta-kirki-panel-title" placeholder="My Options Panel" />
							</div>
							<div class="tpmeta-form-row">
								<label><?php esc_html_e( 'Panel ID (opt_name)', 'pure-metafields' ); ?></label>
								<input type="text" id="tpmeta-kirki-opt-name" placeholder="my_panel"
									style="font-family:ui-monospace,Menlo,Consolas,monospace;" />
							</div>
						</div>
					</div>

					<div class="tpmeta-kirki-tree" id="tpmeta-kirki-tree"></div>

					<button type="button" class="tpmeta-kirki-back-btn" id="tpmeta-kirki-back">
						&#8592; <?php esc_html_e( 'Back to Code', 'pure-metafields' ); ?>
					</button>
				</div>

			</div>

			<footer class="tpmeta-builder-modal-footer">
				<button type="button" class="button" id="tpmeta-kirki-cancel">
					<?php esc_html_e( 'Cancel', 'pure-metafields' ); ?>
				</button>
				<button type="button" class="button button-primary" id="tpmeta-kirki-import" disabled>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Import as New Panel', 'pure-metafields' ); ?>
				</button>
			</footer>
		</form>
	</dialog>

	<dialog id="tpmeta-builder-bake-modal" class="tpmeta-builder-modal tpmeta-builder-bake-modal">
		<form method="dialog" class="tpmeta-builder-modal-form">
			<header class="tpmeta-builder-modal-header">
				<div class="tpmeta-bake-modal-title-wrap">
					<span class="tpmeta-bake-modal-icon dashicons dashicons-editor-code"></span>
					<h2><?php esc_html_e( 'Export to PHP', 'pure-metafields' ); ?></h2>
				</div>
				<button type="button" class="tpmeta-builder-modal-close" aria-label="Close">&times;</button>
			</header>
			<div class="tpmeta-builder-modal-body">

				<!-- Directory + filename picker -->
				<div class="tpmeta-bake-write-section">
					<div class="tpmeta-bake-write-label">
						<span class="dashicons dashicons-portfolio"></span>
						<?php esc_html_e( 'Write to file', 'pure-metafields' ); ?>
					</div>
					<div class="tpmeta-bake-path-row">
						<div class="tpmeta-bake-dir-wrap">
							<span class="tpmeta-bake-dir-prefix">wp-content /</span>
							<select id="tpmeta-bake-dir" class="tpmeta-bake-dir-select">
								<option value=""><?php esc_html_e( 'Loading…', 'pure-metafields' ); ?></option>
							</select>
						</div>
						<span class="tpmeta-bake-path-sep">/</span>
						<input type="text" id="tpmeta-bake-filename" class="tpmeta-bake-filename"
							placeholder="my-options.php" autocomplete="off" spellcheck="false" />
					</div>
					<div class="tpmeta-bake-path-preview">
						<span class="dashicons dashicons-media-code"></span>
						<code id="tpmeta-bake-path-display">wp-content/</code>
					</div>
				</div>

				<!-- Generated PHP — VS Code style editor -->
				<div class="tpmeta-bake-code-wrap">
					<div class="tpmeta-bake-code-header">
						<div class="tpmeta-bake-code-dots">
							<span></span><span></span><span></span>
						</div>
						<span class="tpmeta-bake-code-lang">PHP</span>
						<span class="tpmeta-bake-code-filename" id="tpmeta-bake-code-title">generated-options.php</span>
						<div style="flex:1"></div>
						<button type="button" class="tpmeta-bake-code-copy-btn" id="tpmeta-bake-copy" title="<?php esc_attr_e( 'Copy to clipboard', 'pure-metafields' ); ?>">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy', 'pure-metafields' ); ?>
						</button>
					</div>
					<div class="tpmeta-bake-code-body">
						<div class="tpmeta-bake-line-numbers" id="tpmeta-bake-line-numbers" aria-hidden="true"></div>
						<div class="tpmeta-bake-code-scroll" id="tpmeta-bake-code-scroll">
							<pre class="tpmeta-bake-highlighted" id="tpmeta-bake-highlighted"><code id="tpmeta-bake-highlighted-code"></code></pre>
						</div>
						<textarea id="tpmeta-bake-source" class="tpmeta-bake-source" readonly aria-hidden="true" tabindex="-1" spellcheck="false"></textarea>
					</div>
				</div>

			</div>
			<footer class="tpmeta-builder-modal-footer">
				<button type="button" class="button tpmeta-bake-btn-secondary" id="tpmeta-bake-download">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Download', 'pure-metafields' ); ?>
				</button>
				<button type="button" class="button button-primary tpmeta-bake-btn-write" id="tpmeta-bake-write">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Write File', 'pure-metafields' ); ?>
				</button>
			</footer>
		</form>
	</dialog>
</div>
