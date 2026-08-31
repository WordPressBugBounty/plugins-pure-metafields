<?php
/**
 * Pro-locked feature page template.
 *
 * Rendered when a free-version user visits a pro-exclusive admin page
 * (Option Builder, Migrations, etc.).
 *
 * Expected variables (set by the caller):
 *   $page_title    string  Human-readable feature name (e.g. "Option Builder").
 *   $page_desc     string  Short description of what the feature does.
 *   $locked_cards  array   Optional list of sub-features to show as cards.
 *                           Each: ['icon'=>dashicon, 'title'=>string, 'desc'=>string].
 *
 * @package tpmeta
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** @var string $page_title */
/** @var string $page_desc */
/** @var array  $locked_cards */

$page_title   = isset( $page_title ) ? $page_title : '';
$page_desc    = isset( $page_desc ) ? $page_desc : '';
$upgrade_url  = 'https://themepure.net/plugins/pure-metafields-pro/';

if ( ! isset( $locked_cards ) ) {
	$locked_cards = array();
}
?>
<div class="pf-page">
	<div class="pf-locked-page">

		<div class="pf-locked-hero">
			<div class="pf-locked-hero-icon">
				<span class="dashicons dashicons-lock"></span>
			</div>
			<h2><?php echo esc_html( $page_title ); ?> <span style="font-size:14px;vertical-align:middle;opacity:.6">🔒</span></h2>
			<p><?php echo esc_html( $page_desc ); ?></p>
		</div>

		<?php if ( ! empty( $locked_cards ) ) : ?>
		<div class="pf-locked-grid">
			<?php foreach ( $locked_cards as $card ) : ?>
			<div class="pf-locked-card">
				<div class="pf-locked-card-badge">
					<span class="dashicons dashicons-lock"></span> PRO
				</div>
				<div class="pf-locked-card-icon">
					<span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ?? 'admin-generic' ); ?>"></span>
				</div>
				<h3><?php echo esc_html( $card['title'] ); ?></h3>
				<p><?php echo esc_html( $card['desc'] ); ?></p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="pf-locked-card-cta">
					<?php esc_html_e( 'Upgrade to Pro', 'pure-metafields' ); ?>
					<span class="dashicons dashicons-external"></span>
				</a>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="pf-locked-cta-banner">
			<h3><?php esc_html_e( 'Unlock All Pro Features', 'pure-metafields' ); ?></h3>
			<p><?php esc_html_e( 'Get the Option Builder, Customizer integration, CSS auto-output, advanced field types, Bake to PHP, Kirki & Redux migration, and more.', 'pure-metafields' ); ?></p>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="pf-btn--cta">
				<?php esc_html_e( 'Upgrade to Pure Metafields Pro', 'pure-metafields' ); ?>
				<span class="dashicons dashicons-arrow-right-alt"></span>
			</a>
		</div>

	</div>
</div>
