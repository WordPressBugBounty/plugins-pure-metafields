<?php
/**
 * Pure Fields — Dashboard
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$logo_url  = TPMETA_URL . 'assets/img/logo.png';
$admin_url = admin_url( 'admin.php' );

$overview = array(
	array(
		'slug'  => 'pure-fields-metafields',
		'icon'  => 'editor-table',
		'color' => 'blue',
		'count' => count( TPMeta_Metafields_Store::all() ),
		'label' => __( 'Metaboxes', 'pure-metafields' ),
	),
	array(
		'slug'  => 'pure-fields-options-builder',
		'icon'  => 'admin-customizer',
		'color' => 'purple',
		'count' => class_exists( 'TPMeta_Builder_Store' ) ? count( TPMeta_Builder_Store::all() ) : '🔒',
		'label' => __( 'Option Panels', 'pure-metafields' ),
	),
	array(
		'slug'  => 'pure-fields-post-types',
		'icon'  => 'admin-post',
		'color' => 'pink',
		'count' => count( TPMeta_Post_Types_Store::all() ),
		'label' => __( 'Post Types', 'pure-metafields' ),
	),
	array(
		'slug'  => 'pure-fields-taxonomies',
		'icon'  => 'tag',
		'color' => 'teal',
		'count' => count( TPMeta_Taxonomies_Store::all() ),
		'label' => __( 'Taxonomies', 'pure-metafields' ),
	),
);

$tools = array(
	array(
		'slug'  => 'pure-fields-metafields',
		'icon'  => 'editor-table',
		'color' => 'blue',
		'title' => __( 'Metafield Builder', 'pure-metafields' ),
		'desc'  => __( 'Drag-and-drop metaboxes for any post type — no PHP required.', 'pure-metafields' ),
	),
	array(
		'slug'  => 'pure-fields-options-builder',
		'icon'  => 'admin-customizer',
		'color' => 'purple',
		'title' => __( 'Option Builder', 'pure-metafields' ),
		'desc'  => __( 'Visual theme options panels, exported as clean production-ready PHP.', 'pure-metafields' ),
	),
	array(
		'slug'  => 'pure-fields-post-types',
		'icon'  => 'admin-post',
		'color' => 'pink',
		'title' => __( 'Post Types', 'pure-metafields' ),
		'desc'  => __( 'Register custom post types with every WordPress argument from a visual form.', 'pure-metafields' ),
	),
	array(
		'slug'  => 'pure-fields-taxonomies',
		'icon'  => 'tag',
		'color' => 'teal',
		'title' => __( 'Taxonomies', 'pure-metafields' ),
		'desc'  => __( 'Create and attach custom taxonomies to any post type in one click.', 'pure-metafields' ),
	),
	array(
		'slug'  => 'pure-fields-export-import',
		'icon'  => 'database-import',
		'color' => 'orange',
		'title' => __( 'Export / Import', 'pure-metafields' ),
		'desc'  => __( 'Back up and restore all fields, post types, and panels as JSON.', 'pure-metafields' ),
		'wide'  => true,
	),
);
?>

<div class="pfw-app" id="pfw-page">

	<!-- ── DASHBOARD ────────────────────────────────────────────────────── -->
	<div class="pfw-dashboard">

		<!-- Main column -->
		<main class="pfw-main">

			<!-- Welcome panel -->
			<div class="pfw-banner">
				<div class="pfw-banner-brand">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="" width="38" height="38" aria-hidden="true">
					<div>
						<div class="pfw-banner-name">Pure Fields</div>
						<div class="pfw-banner-sub"><?php esc_html_e( 'The complete WordPress fields framework', 'pure-metafields' ); ?></div>
					</div>
				</div>
				<div class="pfw-banner-chips">
					<span class="pfw-chip"><strong>5</strong>&nbsp;<?php esc_html_e( 'Tools', 'pure-metafields' ); ?></span>
					<span class="pfw-chip"><strong>13+</strong>&nbsp;<?php esc_html_e( 'Field Types', 'pure-metafields' ); ?></span>
					<span class="pfw-chip pfw-chip--green">
						<svg width="9" height="9" viewBox="0 0 16 16" fill="none" aria-hidden="true">
							<path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'PHP Export', 'pure-metafields' ); ?>
					</span>
				</div>
			</div>

			<!-- Overview stats -->
			<div class="pfw-section-head">
				<span class="pfw-eyebrow"><?php esc_html_e( 'Overview', 'pure-metafields' ); ?></span>
			</div>

			<div class="pfw-stats">
				<?php foreach ( $overview as $o ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'page', $o['slug'], $admin_url ) ); ?>"
				   class="pfw-stat pfw-color-<?php echo esc_attr( $o['color'] ); ?>">
					<span class="pfw-stat-icon" aria-hidden="true">
						<span class="dashicons dashicons-<?php echo esc_attr( $o['icon'] ); ?>"></span>
					</span>
					<span class="pfw-stat-body">
						<span class="pfw-stat-count"><?php echo esc_html( $o['count'] ); ?></span>
						<span class="pfw-stat-label"><?php echo esc_html( $o['label'] ); ?></span>
					</span>
				</a>
				<?php endforeach; ?>
			</div>

			<!-- Section label -->
			<div class="pfw-section-head">
				<span class="pfw-eyebrow"><?php esc_html_e( 'Tools', 'pure-metafields' ); ?></span>
			</div>

			<!-- 2-col tool grid -->
			<div class="pfw-grid">
				<?php foreach ( $tools as $t ) :
					$is_wide = ! empty( $t['wide'] );
				?>
				<a href="<?php echo esc_url( add_query_arg( 'page', $t['slug'], $admin_url ) ); ?>"
				   class="pfw-tool pfw-color-<?php echo esc_attr( $t['color'] ); ?><?php echo $is_wide ? ' pfw-tool--wide' : ''; ?>">

					<div class="pfw-tool-hd">
						<span class="pfw-tool-icon" aria-hidden="true">
							<span class="dashicons dashicons-<?php echo esc_attr( $t['icon'] ); ?>"></span>
						</span>
						<span class="pfw-tool-name"><?php echo esc_html( $t['title'] ); ?></span>
					</div>

					<div class="pfw-tool-bd">
						<p class="pfw-tool-desc"><?php echo esc_html( $t['desc'] ); ?></p>
					</div>

					<div class="pfw-tool-ft">
						<span class="pfw-tool-open">
							<?php esc_html_e( 'Open', 'pure-metafields' ); ?>
							<svg width="12" height="12" viewBox="0 0 16 16" fill="none" aria-hidden="true">
								<path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>
					</div>

				</a>
				<?php endforeach; ?>
			</div>

		</main>

		<!-- Sidebar -->
		<aside class="pfw-sidebar">

			<!-- System card -->
			<div class="pfw-card">
				<div class="pfw-card-head">
					<span class="pfw-eyebrow"><?php esc_html_e( 'System', 'pure-metafields' ); ?></span>
					<span class="pfw-pill pfw-pill--green">
						<span class="pfw-dot" aria-hidden="true"></span>
						<?php esc_html_e( 'Active', 'pure-metafields' ); ?>
					</span>
				</div>
				<dl class="pfw-meta">
					<div class="pfw-meta-row">
						<dt><?php esc_html_e( 'Version', 'pure-metafields' ); ?></dt>
						<dd>v<?php echo esc_html( TPMETA_VERSION ); ?></dd>
					</div>
					<div class="pfw-meta-row">
						<dt><?php esc_html_e( 'Tools', 'pure-metafields' ); ?></dt>
						<dd>5</dd>
					</div>
					<div class="pfw-meta-row">
						<dt><?php esc_html_e( 'Field Types', 'pure-metafields' ); ?></dt>
						<dd>13+</dd>
					</div>
					<div class="pfw-meta-row">
						<dt><?php esc_html_e( 'PHP Export', 'pure-metafields' ); ?></dt>
						<dd class="pfw-meta-yes">
							<svg width="11" height="11" viewBox="0 0 16 16" fill="none" aria-hidden="true">
								<path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<?php esc_html_e( 'Supported', 'pure-metafields' ); ?>
						</dd>
					</div>
				</dl>
			</div>

			<!-- Quick links card -->
			<div class="pfw-card">
				<div class="pfw-card-head">
					<span class="pfw-eyebrow"><?php esc_html_e( 'Quick Links', 'pure-metafields' ); ?></span>
				</div>
				<ul class="pfw-links">
					<li>
						<a href="https://themepure.net" target="_blank" rel="noopener" class="pfw-link">
							<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Documentation', 'pure-metafields' ); ?>
							<svg class="pfw-link-ext" width="9" height="9" viewBox="0 0 10 10" fill="none" aria-hidden="true">
								<path d="M1 9L9 1M9 1H3.5M9 1V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
						</a>
					</li>
					<li>
						<a href="https://themepure.net" target="_blank" rel="noopener" class="pfw-link">
							<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
							<?php esc_html_e( 'ThemePure.net', 'pure-metafields' ); ?>
							<svg class="pfw-link-ext" width="9" height="9" viewBox="0 0 10 10" fill="none" aria-hidden="true">
								<path d="M1 9L9 1M9 1H3.5M9 1V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
						</a>
					</li>
					<li>
						<a href="https://themepure.net" target="_blank" rel="noopener" class="pfw-link">
							<span class="dashicons dashicons-sos" aria-hidden="true"></span>
							<?php esc_html_e( 'Support', 'pure-metafields' ); ?>
							<svg class="pfw-link-ext" width="9" height="9" viewBox="0 0 10 10" fill="none" aria-hidden="true">
								<path d="M1 9L9 1M9 1H3.5M9 1V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
						</a>
					</li>
				</ul>
			</div>

		</aside>
	</div>

</div>
