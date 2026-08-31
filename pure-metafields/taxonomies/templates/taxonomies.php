<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="pf-page tax-page">

	<!-- Page header -->
	<div class="cpt-page-header cpt-page-header--pink">
		<div class="cpt-page-header-icon has-logo">
			<img class="cpt-page-header-logo" src="<?php echo esc_url( TPMETA_URL . 'assets/img/logo.png' ); ?>" alt="" />
		</div>
		<div class="cpt-page-header-title">
			<h1><?php esc_html_e( 'Taxonomies', 'pure-metafields' ); ?></h1>
			<p><?php esc_html_e( 'Create custom taxonomies and attach them to any post type — no PHP required.', 'pure-metafields' ); ?></p>
		</div>
	</div>

	<div class="cpt-layout">
		<div class="cpt-main" id="tax-main"></div>
		<aside class="cpt-sidebar cpt-sidebar--pink" id="tax-sidebar">
			<div class="cpt-sidebar-header">
				<h3><?php esc_html_e( 'Taxonomies', 'pure-metafields' ); ?></h3>
				<span class="cpt-count" id="tax-count">0</span>
			</div>
			<div class="cpt-sidebar-list" id="tax-list">
				<div class="cpt-sidebar-empty" id="tax-list-empty">
					<span class="dashicons dashicons-tag"></span>
					<p><?php esc_html_e( 'No taxonomies yet.', 'pure-metafields' ); ?></p>
				</div>
			</div>
		</aside>
	</div>

	<div class="cpt-loader" id="tax-loader" aria-hidden="true">
		<div class="cpt-loader-inner">
			<div class="cpt-loader-ring cpt-loader-ring--pink"></div>
			<p class="cpt-loader-text" id="tax-loader-text">Getting ready&hellip;</p>
		</div>
	</div>

</div>
