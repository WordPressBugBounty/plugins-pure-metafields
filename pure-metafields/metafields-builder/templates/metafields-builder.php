<?php
/**
 * Metafield Builder — two-panel shell.
 * Left: metabox list sidebar.  Right: same builder UI as Options Builder.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="mfb-page">

	<!-- ── LEFT SIDEBAR: metabox list ──────────────────────────────────── -->
	<aside class="mfb-sidebar">
		<div class="mfb-sidebar-header">
			<span class="mfb-sidebar-title"><?php esc_html_e( 'Metaboxes', 'pure-metafields' ); ?></span>
			<button class="mfb-sidebar-add" id="mfb-bake-open" title="<?php esc_attr_e( 'Bake to PHP', 'pure-metafields' ); ?>">
				<span class="dashicons dashicons-editor-code"></span>
			</button>
			<button class="mfb-sidebar-add" id="mfb-new-box" title="<?php esc_attr_e( 'New Metabox', 'pure-metafields' ); ?>">
				<span class="dashicons dashicons-plus-alt2"></span>
			</button>
		</div>
		<div class="mfb-sidebar-list" id="mfb-sidebar-list">
			<div class="mfb-sidebar-loading">
				<span class="spinner is-active"></span>
			</div>
		</div>
	</aside>

	<!-- ── RIGHT: builder (same structure as Options Builder) ───────────── -->
	<div class="mfb-builder-wrap">
		<div id="mfb-root">
			<div class="tpmeta-builder-loading">
				<span class="spinner is-active"></span>
				<?php esc_html_e( 'Loading…', 'pure-metafields' ); ?>
			</div>
		</div>
	</div>

</div>

<!-- ── Field editor modal (reused from Options Builder) ─────────────────── -->
<dialog id="tpmeta-builder-modal" class="tpmeta-builder-modal">
	<form method="dialog" class="tpmeta-builder-modal-form">
		<header class="tpmeta-builder-modal-header">
			<h2 class="tpmeta-builder-modal-title"></h2>
			<button type="button" class="tpmeta-builder-modal-close" aria-label="Close">&times;</button>
		</header>
		<div class="tpmeta-builder-modal-body"></div>
		<footer class="tpmeta-builder-modal-footer">
			<button type="button" class="button tpmeta-builder-modal-cancel"><?php esc_html_e( 'Cancel', 'pure-metafields' ); ?></button>
			<button type="button" class="button button-primary tpmeta-builder-modal-save"><?php esc_html_e( 'Apply', 'pure-metafields' ); ?></button>
		</footer>
	</form>
</dialog>

<!-- ── Metabox settings modal ───────────────────────────────────────────── -->
<dialog id="mfb-box-modal" class="tpmeta-builder-modal mfb-box-modal">
	<form method="dialog" class="tpmeta-builder-modal-form" id="mfb-box-form">
		<header class="tpmeta-builder-modal-header">
			<h2 id="mfb-box-modal-title"><?php esc_html_e( 'Metabox Settings', 'pure-metafields' ); ?></h2>
			<button type="button" class="tpmeta-builder-modal-close" id="mfb-box-modal-close">&times;</button>
		</header>
		<div class="tpmeta-builder-modal-body">

			<div class="tpmeta-form-row mfb-target-row">
				<label><?php esc_html_e( 'Attach to', 'pure-metafields' ); ?></label>
				<div class="mfb-target-toggle" id="mfb-f-target">
					<label class="mfb-pt-check">
						<input type="radio" name="mfb_target" value="post" checked>
						<span class="mfb-pt-check-indicator"></span>
						<span class="mfb-pt-check-label"><?php esc_html_e( 'Post Type', 'pure-metafields' ); ?></span>
					</label>
					<label class="mfb-pt-check">
						<input type="radio" name="mfb_target" value="user">
						<span class="mfb-pt-check-indicator"></span>
						<span class="mfb-pt-check-label"><?php esc_html_e( 'User Profile', 'pure-metafields' ); ?></span>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Post Type renders inside post-edit screens via tp_meta_boxes. User Profile renders inside profile.php / user-edit.php via tp_user_meta.', 'pure-metafields' ); ?></p>
			</div>

			<div class="tpmeta-form-grid">
				<div class="tpmeta-form-row">
					<label><?php esc_html_e( 'Title', 'pure-metafields' ); ?></label>
					<input type="text" id="mfb-f-title" class="regular-text" placeholder="e.g. Page Options" required />
				</div>
				<div class="tpmeta-form-row">
					<label><?php esc_html_e( 'Metabox ID', 'pure-metafields' ); ?></label>
					<input type="text" id="mfb-f-id" class="regular-text"
						style="font-family:ui-monospace,Menlo,Consolas,monospace;"
						placeholder="e.g. my_page_options" />
					<p class="description"><?php esc_html_e( 'Auto-generated. Must be unique.', 'pure-metafields' ); ?></p>
				</div>
			</div>

			<div class="tpmeta-form-row mfb-target-post-only">
				<label><?php esc_html_e( 'Attach to Post Types', 'pure-metafields' ); ?></label>
				<div class="mfb-pt-grid" id="mfb-f-post-types"></div>
			</div>

			<div class="tpmeta-form-grid mfb-form-grid-3 mfb-target-post-only">
				<div class="tpmeta-form-row">
					<label><?php esc_html_e( 'Context', 'pure-metafields' ); ?></label>
					<select id="mfb-f-context">
						<option value="normal"><?php esc_html_e( 'Normal', 'pure-metafields' ); ?></option>
						<option value="side"><?php esc_html_e( 'Side', 'pure-metafields' ); ?></option>
						<option value="advanced"><?php esc_html_e( 'Advanced', 'pure-metafields' ); ?></option>
					</select>
				</div>
				<div class="tpmeta-form-row">
					<label><?php esc_html_e( 'Priority', 'pure-metafields' ); ?></label>
					<select id="mfb-f-priority">
						<option value="default"><?php esc_html_e( 'Default', 'pure-metafields' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'pure-metafields' ); ?></option>
						<option value="low"><?php esc_html_e( 'Low', 'pure-metafields' ); ?></option>
						<option value="core"><?php esc_html_e( 'Core', 'pure-metafields' ); ?></option>
					</select>
				</div>
				<div class="tpmeta-form-row">
					<label><?php esc_html_e( 'Columns', 'pure-metafields' ); ?></label>
					<select id="mfb-f-columns">
						<option value="1">1</option>
						<option value="2">2</option>
						<option value="3" selected>3</option>
						<option value="4">4</option>
					</select>
				</div>
			</div>

			<div class="tpmeta-form-row mfb-target-post-only">
				<label for="mfb-f-post-format"><?php esc_html_e( 'Post Format', 'pure-metafields' ); ?></label>
				<select id="mfb-f-post-format">
					<option value=""><?php esc_html_e( '— None (always show) —', 'pure-metafields' ); ?></option>
					<option value="aside"><?php esc_html_e( 'Aside',   'pure-metafields' ); ?></option>
					<option value="chat"><?php esc_html_e( 'Chat',     'pure-metafields' ); ?></option>
					<option value="gallery"><?php esc_html_e( 'Gallery','pure-metafields' ); ?></option>
					<option value="link"><?php esc_html_e( 'Link',     'pure-metafields' ); ?></option>
					<option value="image"><?php esc_html_e( 'Image',   'pure-metafields' ); ?></option>
					<option value="quote"><?php esc_html_e( 'Quote',   'pure-metafields' ); ?></option>
					<option value="status"><?php esc_html_e( 'Status', 'pure-metafields' ); ?></option>
					<option value="video"><?php esc_html_e( 'Video',   'pure-metafields' ); ?></option>
					<option value="audio"><?php esc_html_e( 'Audio',   'pure-metafields' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'If set, this metabox only shows when the post matches the selected format. Requires Post Formats theme support.', 'pure-metafields' ); ?></p>
			</div>

			<div class="tpmeta-form-row mfb-target-user-only">
				<label for="mfb-f-capability"><?php esc_html_e( 'Capability', 'pure-metafields' ); ?></label>
				<select id="mfb-f-capability">
					<option value=""><?php esc_html_e( '— Any logged-in user —', 'pure-metafields' ); ?></option>
					<option value="read">read</option>
					<option value="edit_posts">edit_posts</option>
					<option value="upload_files">upload_files</option>
					<option value="publish_posts">publish_posts</option>
					<option value="edit_users">edit_users</option>
					<option value="manage_options">manage_options</option>
				</select>
				<p class="description"><?php esc_html_e( 'When set, the block only renders for users with this capability. Leave blank to show on every profile.', 'pure-metafields' ); ?></p>
			</div>

		</div>
		<footer class="tpmeta-builder-modal-footer">
			<button type="button" class="button" id="mfb-box-cancel"><?php esc_html_e( 'Cancel', 'pure-metafields' ); ?></button>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Metabox', 'pure-metafields' ); ?></button>
		</footer>
	</form>
</dialog>

<!-- ── Bake to PHP modal ────────────────────────────────────────────────── -->
<dialog id="mfb-bake-modal" class="tpmeta-builder-modal mfb-bake-modal">
	<form method="dialog" class="tpmeta-builder-modal-form" id="mfb-bake-form">
		<header class="tpmeta-builder-modal-header">
			<h2><?php esc_html_e( 'Bake Metaboxes to PHP', 'pure-metafields' ); ?></h2>
			<button type="button" class="tpmeta-builder-modal-close" id="mfb-bake-close">&times;</button>
		</header>
		<div class="tpmeta-builder-modal-body">

			<div class="tpmeta-form-row">
				<label><?php esc_html_e( 'Metaboxes to include', 'pure-metafields' ); ?></label>
				<div class="mfb-bake-list" id="mfb-bake-list"></div>
				<p class="description"><?php esc_html_e( 'All baked metaboxes will live in the same generated PHP file.', 'pure-metafields' ); ?></p>
			</div>

			<div class="tpmeta-form-grid">
				<div class="tpmeta-form-row">
					<label for="mfb-bake-dir"><?php esc_html_e( 'Target directory', 'pure-metafields' ); ?></label>
					<select id="mfb-bake-dir">
						<option value=""><?php esc_html_e( '— Download only (no disk write) —', 'pure-metafields' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'wp-content-relative. Leave on “Download only” to skip writing to disk.', 'pure-metafields' ); ?></p>
				</div>
				<div class="tpmeta-form-row">
					<label for="mfb-bake-filename"><?php esc_html_e( 'Filename', 'pure-metafields' ); ?></label>
					<input type="text" id="mfb-bake-filename" class="regular-text"
						style="font-family:ui-monospace,Menlo,Consolas,monospace;"
						value="pure-metaboxes.php" />
				</div>
			</div>

			<div class="tpmeta-form-row">
				<label for="mfb-bake-callback"><?php esc_html_e( 'Filter callback function name', 'pure-metafields' ); ?></label>
				<input type="text" id="mfb-bake-callback" class="regular-text"
					style="font-family:ui-monospace,Menlo,Consolas,monospace;"
					value="tpmeta_baked_metaboxes" />
				<p class="description"><?php esc_html_e( 'Used as the named callback for add_filter(\'tp_meta_boxes\', …). Must be unique across all bake files.', 'pure-metafields' ); ?></p>
			</div>

			<div class="tpmeta-form-row">
				<label class="mfb-pt-check" style="display:inline-flex;align-items:center;gap:8px;">
					<input type="checkbox" id="mfb-bake-delete-after">
					<span><?php esc_html_e( 'Remove these metaboxes from the builder after a successful disk write', 'pure-metafields' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Recommended after baking to a theme — prevents the same metabox being registered twice (once from the builder, once from the baked PHP file).', 'pure-metafields' ); ?></p>
			</div>

		</div>
		<footer class="tpmeta-builder-modal-footer">
			<button type="button" class="button" id="mfb-bake-cancel"><?php esc_html_e( 'Cancel', 'pure-metafields' ); ?></button>
			<button type="button" class="button" id="mfb-bake-download"><?php esc_html_e( 'Generate &amp; Download', 'pure-metafields' ); ?></button>
			<button type="button" class="button button-primary" id="mfb-bake-write"><?php esc_html_e( 'Bake &amp; Write to disk', 'pure-metafields' ); ?></button>
		</footer>
	</form>
</dialog>
