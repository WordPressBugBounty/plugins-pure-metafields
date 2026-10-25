<?php
/**
 * Pro-locked feature page template.
 *
 * Rendered when a user visits a pro-exclusive admin page but the feature is
 * not available. Works in both the free plugin (upgrade CTA) and the pro
 * plugin (activate license CTA).
 *
 * Expected variables (set by the caller):
 *   $page_title     string  Human-readable feature name (e.g. "Option Builder").
 *   $page_desc      string  Short description of what the feature does.
 *   $locked_cards   array   Optional list of sub-features to show as cards.
 *                           Each: ['icon'=>dashicon, 'title'=>string, 'desc'=>string].
 *   $is_pro_plugin  bool    Optional. True when rendered from the pro plugin.
 *
 * @package tpmeta
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** @var string $page_title */
/** @var string $page_desc */
/** @var array  $locked_cards */
/** @var bool   $is_pro_plugin */

$page_title    = isset( $page_title ) ? $page_title : '';
$page_desc     = isset( $page_desc ) ? $page_desc : '';
$is_pro_plugin = ! empty( $is_pro_plugin );
$license_url   = admin_url( 'admin.php?page=pure-fields-license' );
$upgrade_url   = 'https://themepure.net/plugins/pure-metafields-pro/';
$cta_url       = $is_pro_plugin ? $license_url : $upgrade_url;

if ( ! isset( $locked_cards ) ) {
	$locked_cards = array();
}
?>
<div class="pf-page">
	<div class="pf-locked-page">

		<header class="pf-locked-hero">
			<span class="pf-locked-hero-icon">
				<?php echo TPMeta_Main_Menu::icon( $is_pro_plugin ? 'key' : 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
			</span>
			<div class="pf-locked-hero-text">
				<h1><?php echo esc_html( $page_title ); ?></h1>
				<p><?php echo esc_html( $page_desc ); ?></p>
			</div>
			<span class="pf-locked-tag">
				<?php echo $is_pro_plugin ? esc_html__( 'License required', 'pure-metafields' ) : esc_html__( 'Pro', 'pure-metafields' ); ?>
			</span>
		</header>

		<?php if ( ! empty( $locked_cards ) ) : ?>
		<h2 class="screen-reader-text"><?php esc_html_e( 'Included features', 'pure-metafields' ); ?></h2>
		<ul class="pf-locked-grid">
			<?php foreach ( $locked_cards as $card ) : ?>
			<li class="pf-locked-card">
				<div class="pf-locked-card-top">
					<span class="pf-locked-card-icon">
						<span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ?? 'admin-generic' ); ?>" aria-hidden="true"></span>
					</span>
					<span class="pf-locked-card-badge">
						<?php echo TPMeta_Main_Menu::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
						<?php esc_html_e( 'Pro', 'pure-metafields' ); ?>
					</span>
				</div>
				<h3><?php echo esc_html( $card['title'] ); ?></h3>
				<p><?php echo esc_html( $card['desc'] ); ?></p>
				<?php if ( $is_pro_plugin ) : ?>
				<a href="<?php echo esc_url( $cta_url ); ?>" class="pf-locked-card-cta">
					<?php esc_html_e( 'Activate License', 'pure-metafields' ); ?>
					<?php echo TPMeta_Main_Menu::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
				</a>
				<?php else : ?>
				<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener" class="pf-locked-card-cta">
					<?php esc_html_e( 'Upgrade to Pro', 'pure-metafields' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'pure-metafields' ); ?></span>
					<?php echo TPMeta_Main_Menu::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
				</a>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>

		<section class="pf-locked-cta-banner">
			<div class="pf-locked-cta-text">
				<h2>
					<?php echo $is_pro_plugin
						? esc_html__( 'Activate Your License to Unlock All Features', 'pure-metafields' )
						: esc_html__( 'Unlock All Pro Features', 'pure-metafields' ); ?>
				</h2>
				<p>
					<?php echo $is_pro_plugin
						? esc_html__( 'Enter your license key to unlock the Option Builder, Customizer integration, CSS auto-output, advanced field types, Bake to PHP, and more.', 'pure-metafields' )
						: esc_html__( 'Get the Option Builder, Customizer integration, CSS auto-output, advanced field types, Bake to PHP, Kirki & Redux migration, and more.', 'pure-metafields' ); ?>
				</p>
			</div>
			<?php if ( $is_pro_plugin ) : ?>
			<a href="<?php echo esc_url( $cta_url ); ?>" class="pf-btn--cta">
				<?php esc_html_e( 'Go to License Activation', 'pure-metafields' ); ?>
				<?php echo TPMeta_Main_Menu::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
			</a>
			<?php else : ?>
			<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener" class="pf-btn--cta">
				<?php esc_html_e( 'Upgrade to Pure Metafields Pro', 'pure-metafields' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'pure-metafields' ); ?></span>
				<?php echo TPMeta_Main_Menu::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
			</a>
			<?php endif; ?>
		</section>

	</div>
</div>
