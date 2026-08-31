<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="pf-page cpt-page">

	<!-- Page header -->
	<div class="cpt-page-header">
		<div class="cpt-page-header-icon has-logo">
			<img class="cpt-page-header-logo" src="<?php echo esc_url( TPMETA_URL . 'assets/img/logo.png' ); ?>" alt="" />
		</div>
		<div class="cpt-page-header-title">
			<h1><?php esc_html_e( 'Post Types', 'pure-metafields' ); ?></h1>
			<p><?php esc_html_e( 'Register custom post types with all WordPress arguments — no PHP required.', 'pure-metafields' ); ?></p>
		</div>
	</div>

	<!-- Two-column layout -->
	<div class="cpt-layout">

		<!-- LEFT: main area — JS renders welcome or form here -->
		<div class="cpt-main" id="cpt-main"></div>

		<!-- RIGHT: persistent sidebar -->
		<aside class="cpt-sidebar" id="cpt-sidebar">
			<div class="cpt-sidebar-header">
				<h3><?php esc_html_e( 'Post Types', 'pure-metafields' ); ?></h3>
				<span class="cpt-count" id="cpt-count">0</span>
			</div>
			<div class="cpt-sidebar-list" id="cpt-list">
				<div class="cpt-sidebar-empty" id="cpt-list-empty">
					<span class="dashicons dashicons-admin-post"></span>
					<p><?php esc_html_e( 'No post types yet.', 'pure-metafields' ); ?></p>
				</div>
			</div>
		</aside>

	</div>

	<!-- Loader overlay -->
	<div class="cpt-loader" id="cpt-loader" aria-hidden="true">
		<div class="cpt-loader-inner">
			<div class="cpt-loader-ring"></div>
			<p class="cpt-loader-text" id="cpt-loader-text">Getting ready&hellip;</p>
		</div>
	</div>

</div>
