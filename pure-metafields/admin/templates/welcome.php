<?php
/**
 * Pure Fields — Welcome
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$admin_url  = admin_url( 'admin.php' );
$is_pro     = tpmeta_is_pro();
$options_on = class_exists( 'TPMeta_Builder_Store' );

// Unlicensed Pro replaces the "Pro Features" page with its license screen.
$has_pro_page  = (bool) menu_page_url( 'pure-fields-pro', false );
$pro_slug      = $has_pro_page ? 'pure-fields-pro' : 'pure-fields-license';
$needs_license = ! $is_pro && ! $has_pro_page;

$n_boxes = count( TPMeta_Metafields_Store::all() );
$n_types = count( TPMeta_Post_Types_Store::all() );
$n_taxes = count( TPMeta_Taxonomies_Store::all() );
$n_panel = $options_on ? count( TPMeta_Builder_Store::all() ) : 0;

$recent = array();
foreach ( array_slice( array_reverse( TPMeta_Metafields_Store::all() ), 0, 3 ) as $box ) {
	if ( ! is_array( $box ) ) {
		continue;
	}
	if ( 'user' === ( $box['target'] ?? 'post' ) ) {
		$where = __( 'user profiles', 'pure-metafields' );
	} else {
		$where = implode( ', ', array_map( 'sanitize_key', (array) ( $box['post_type'] ?? array() ) ) );
	}
	$recent[] = array(
		'title' => ! empty( $box['title'] ) ? $box['title'] : ( $box['metabox_id'] ?? '' ),
		'where' => $where,
	);
}

$tools = array(
	array(
		'id'     => 'metafields',
		'slug'   => 'pure-fields-metafields',
		'title'  => __( 'Metafield Builder', 'pure-metafields' ),
		'lead'   => __( 'Add custom fields to posts, pages and user profiles.', 'pure-metafields' ),
		'count'  => $n_boxes,
		'unit'   => _n( 'metabox', 'metaboxes', $n_boxes, 'pure-metafields' ),
		'facts'  => array(
			__( 'Makes', 'pure-metafields' )       => __( 'Metaboxes with drag-and-drop fields', 'pure-metafields' ),
			__( 'Saved as', 'pure-metafields' )    => __( 'Post meta or user meta', 'pure-metafields' ),
			__( 'Shows up in', 'pure-metafields' ) => __( 'Post, page and user edit screens', 'pure-metafields' ),
		),
		'action' => __( 'Open builder', 'pure-metafields' ),
		'wide'   => true,
		'recent' => $recent,
	),
	array(
		'id'     => 'post-types',
		'slug'   => 'pure-fields-post-types',
		'title'  => __( 'Post Types', 'pure-metafields' ),
		'lead'   => __( 'Add new content types, such as Portfolio or Team.', 'pure-metafields' ),
		'count'  => $n_types,
		'unit'   => _n( 'post type', 'post types', $n_types, 'pure-metafields' ),
		'facts'  => array(
			__( 'Makes', 'pure-metafields' )       => __( 'Custom post types', 'pure-metafields' ),
			__( 'Saved as', 'pure-metafields' )    => __( 'Plugin settings', 'pure-metafields' ),
			__( 'Shows up in', 'pure-metafields' ) => __( 'The admin menu', 'pure-metafields' ),
		),
		'action' => __( 'Manage', 'pure-metafields' ),
	),
	array(
		'id'     => 'taxonomies',
		'slug'   => 'pure-fields-taxonomies',
		'title'  => __( 'Taxonomies', 'pure-metafields' ),
		'lead'   => __( 'Group content with your own categories or tags.', 'pure-metafields' ),
		'count'  => $n_taxes,
		'unit'   => _n( 'taxonomy', 'taxonomies', $n_taxes, 'pure-metafields' ),
		'facts'  => array(
			__( 'Makes', 'pure-metafields' )       => __( 'Custom taxonomies', 'pure-metafields' ),
			__( 'Saved as', 'pure-metafields' )    => __( 'Plugin settings', 'pure-metafields' ),
			__( 'Shows up in', 'pure-metafields' ) => __( 'Editors of the post types you attach', 'pure-metafields' ),
		),
		'action' => __( 'Manage', 'pure-metafields' ),
	),
	array(
		'id'     => 'options',
		'slug'   => $options_on ? 'pure-fields-options-builder' : $pro_slug,
		'title'  => __( 'Option Builder', 'pure-metafields' ),
		'lead'   => __( 'Build theme settings panels, optionally inside the Customizer.', 'pure-metafields' ),
		'count'  => $n_panel,
		'unit'   => _n( 'option panel', 'option panels', $n_panel, 'pure-metafields' ),
		'facts'  => array(
			__( 'Makes', 'pure-metafields' )       => __( 'Theme option panels', 'pure-metafields' ),
			__( 'Saved as', 'pure-metafields' )    => __( 'Theme mods', 'pure-metafields' ),
			__( 'Shows up in', 'pure-metafields' ) => __( 'Its own admin page, and the Customizer if enabled', 'pure-metafields' ),
		),
		'action' => $options_on
			? __( 'Open builder', 'pure-metafields' )
			: ( $needs_license ? __( 'Activate license', 'pure-metafields' ) : __( 'See Pro features', 'pure-metafields' ) ),
		'locked' => ! $options_on,
	),
	array(
		'id'     => 'export-import',
		'slug'   => 'pure-fields-export-import',
		'title'  => __( 'Export / Import', 'pure-metafields' ),
		'lead'   => __( 'Back up your setup or move it to another site.', 'pure-metafields' ),
		'count'  => null,
		'unit'   => '',
		'facts'  => array(
			__( 'Makes', 'pure-metafields' )       => __( 'One JSON file', 'pure-metafields' ),
			__( 'Includes', 'pure-metafields' )    => $options_on
				? __( 'Metaboxes, post types, taxonomies, option panels', 'pure-metafields' )
				: __( 'Metaboxes, post types, taxonomies', 'pure-metafields' ),
			__( 'Restores on', 'pure-metafields' ) => __( 'Any site running Pure Fields', 'pure-metafields' ),
		),
		'action' => __( 'Export or import', 'pure-metafields' ),
	),
);

$features = array(
	__( 'Option Builder', 'pure-metafields' )         => $options_on,
	__( 'Bake to PHP', 'pure-metafields' )            => class_exists( 'TPMeta_Metafields_Codegen' ),
	__( 'Customizer integration', 'pure-metafields' ) => class_exists( 'TPMeta_Customizer' ),
);

if ( $is_pro ) {
	$edition_label = __( 'Pro edition', 'pure-metafields' );
	$edition_tone  = 'green';
} elseif ( $needs_license ) {
	$edition_label = __( 'Pro, license inactive', 'pure-metafields' );
	$edition_tone  = 'yellow';
} else {
	$edition_label = __( 'Free edition', 'pure-metafields' );
	$edition_tone  = 'neutral';
}
$locked_value = $needs_license ? __( 'License needed', 'pure-metafields' ) : __( 'Pro only', 'pure-metafields' );
?>

<div class="pfw" id="pfw-page">
	<div class="pfw-inner">

		<header class="pfw-head">
			<div class="pfw-head-text">
				<h1 class="pfw-title"><?php esc_html_e( 'Pure Fields', 'pure-metafields' ); ?></h1>
				<p class="pfw-subtitle"><?php esc_html_e( 'Build metaboxes, post types and taxonomies without writing registration code.', 'pure-metafields' ); ?></p>
			</div>
			<div class="pfw-head-meta">
				<kbd class="pfw-kbd">v<?php echo esc_html( TPMETA_VERSION ); ?></kbd>
				<span class="pfw-tag pfw-tag--<?php echo esc_attr( $edition_tone ); ?>"><?php echo esc_html( $edition_label ); ?></span>
			</div>
		</header>

		<h2 class="screen-reader-text"><?php esc_html_e( 'Tools', 'pure-metafields' ); ?></h2>

		<ul class="pfw-bento">
			<?php foreach ( $tools as $i => $t ) :
				$locked = ! empty( $t['locked'] );
				if ( $locked ) {
					$tag_tone  = 'yellow';
					$tag_label = $needs_license ? __( 'License', 'pure-metafields' ) : __( 'Pro', 'pure-metafields' );
				} elseif ( 0 === $t['count'] ) {
					$tag_tone  = 'neutral';
					$tag_label = __( 'Empty', 'pure-metafields' );
				} else {
					$tag_tone  = 'green';
					$tag_label = __( 'Ready', 'pure-metafields' );
				}
				$classes = 'pfw-card pfw-tool pfw-tool--' . $t['id'] . ( ! empty( $t['wide'] ) ? ' is-wide' : '' ) . ( $locked ? ' is-locked' : '' );
				$btn     = ( ! empty( $t['wide'] ) && ! $locked ) ? 'pfw-btn pfw-btn--primary' : 'pfw-btn';
			?>
			<li class="<?php echo esc_attr( $classes ); ?>" style="--i:<?php echo (int) $i; ?>">
				<div class="pfw-tool-main">
					<div class="pfw-tool-top">
						<span class="pfw-tool-icon">
							<?php echo TPMeta_Main_Menu::icon( $t['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
						</span>
						<span class="pfw-tag pfw-tag--<?php echo esc_attr( $tag_tone ); ?>">
							<?php
							if ( $locked ) {
								echo TPMeta_Main_Menu::icon( $needs_license ? 'key' : 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon().
							}
							echo esc_html( $tag_label );
							?>
						</span>
					</div>

					<h3 class="pfw-tool-name"><?php echo esc_html( $t['title'] ); ?></h3>
					<p class="pfw-tool-lead"><?php echo esc_html( $t['lead'] ); ?></p>

					<?php if ( ! empty( $t['recent'] ) ) : ?>
					<ul class="pfw-recent" aria-label="<?php esc_attr_e( 'Your metaboxes', 'pure-metafields' ); ?>">
						<?php foreach ( $t['recent'] as $box ) : ?>
						<li>
							<span class="pfw-recent-title"><?php echo esc_html( $box['title'] ); ?></span>
							<span class="pfw-recent-meta"><?php echo esc_html( $box['where'] ); ?></span>
						</li>
						<?php endforeach; ?>
						<?php if ( $t['count'] > count( $t['recent'] ) ) : ?>
						<li class="pfw-recent-more">
							<?php
							/* translators: %s: number of further metaboxes. */
							echo esc_html( sprintf( __( '%s more in the builder', 'pure-metafields' ), number_format_i18n( $t['count'] - count( $t['recent'] ) ) ) );
							?>
						</li>
						<?php endif; ?>
					</ul>
					<?php elseif ( isset( $t['recent'] ) ) : ?>
					<p class="pfw-recent-empty"><?php esc_html_e( 'Start with one metabox, pick the post types it belongs to, then drag fields into it.', 'pure-metafields' ); ?></p>
					<?php endif; ?>
				</div>

				<dl class="pfw-facts">
					<?php foreach ( $t['facts'] as $label => $value ) : ?>
					<div class="pfw-fact">
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd><?php echo esc_html( $value ); ?></dd>
					</div>
					<?php endforeach; ?>
				</dl>

				<div class="pfw-tool-foot">
					<span class="pfw-count">
						<?php
						if ( $locked ) {
							echo $needs_license
								? esc_html__( 'Activate your license to unlock', 'pure-metafields' )
								: esc_html__( 'Available in Pure Fields Pro', 'pure-metafields' );
						} elseif ( 0 === $t['count'] ) {
							/* translators: %s: plural item noun (e.g. "metaboxes"). */
							echo esc_html( sprintf( __( 'No %s yet', 'pure-metafields' ), $t['unit'] ) );
						} elseif ( null !== $t['count'] ) {
							echo '<span class="pfw-num">' . esc_html( number_format_i18n( $t['count'] ) ) . '</span> ' . esc_html( $t['unit'] );
						}
						?>
					</span>
					<a class="<?php echo esc_attr( $btn ); ?>" href="<?php echo esc_url( add_query_arg( 'page', $t['slug'], $admin_url ) ); ?>">
						<span><?php echo esc_html( $t['action'] ); ?></span>
						<span class="screen-reader-text"><?php echo esc_html( ' — ' . $t['title'] ); ?></span>
						<?php echo TPMeta_Main_Menu::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
					</a>
				</div>
			</li>
			<?php endforeach; ?>

			<li class="pfw-card pfw-install" style="--i:5">
				<h2 class="pfw-panel-title"><?php esc_html_e( 'This install', 'pure-metafields' ); ?></h2>
				<dl class="pfw-install-grid">
					<div class="pfw-install-item">
						<dt><?php esc_html_e( 'Version', 'pure-metafields' ); ?></dt>
						<dd class="pfw-num"><?php echo esc_html( TPMETA_VERSION ); ?></dd>
					</div>
					<div class="pfw-install-item">
						<dt><?php esc_html_e( 'Edition', 'pure-metafields' ); ?></dt>
						<dd><?php echo esc_html( $edition_label ); ?></dd>
					</div>
					<?php foreach ( $features as $label => $on ) : ?>
					<div class="pfw-install-item">
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd class="pfw-avail <?php echo $on ? 'is-on' : 'is-off'; ?>">
							<?php
							echo TPMeta_Main_Menu::icon( $on ? 'check' : 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon().
							echo $on ? esc_html__( 'Available', 'pure-metafields' ) : esc_html( $locked_value );
							?>
						</dd>
					</div>
					<?php endforeach; ?>
				</dl>
			</li>

			<li class="pfw-card pfw-help" style="--i:6">
				<h2 class="pfw-panel-title"><?php esc_html_e( 'Help', 'pure-metafields' ); ?></h2>
				<ul class="pfw-links">
					<li>
						<a class="pfw-link" href="<?php echo esc_url( add_query_arg( 'page', 'pure-fields-help', $admin_url ) ); ?>">
							<?php echo TPMeta_Main_Menu::icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
							<span><?php esc_html_e( 'Guides and field reference', 'pure-metafields' ); ?></span>
						</a>
					</li>
					<?php if ( ! $is_pro ) : ?>
					<li>
						<a class="pfw-link" href="<?php echo esc_url( add_query_arg( 'page', $pro_slug, $admin_url ) ); ?>">
							<?php echo TPMeta_Main_Menu::icon( $needs_license ? 'key' : 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
							<span><?php echo $needs_license ? esc_html__( 'Activate your license', 'pure-metafields' ) : esc_html__( 'What Pro adds', 'pure-metafields' ); ?></span>
						</a>
					</li>
					<?php endif; ?>
					<li>
						<a class="pfw-link" href="https://themepure.net" target="_blank" rel="noopener">
							<?php echo TPMeta_Main_Menu::icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
							<span><?php esc_html_e( 'ThemePure.net', 'pure-metafields' ); ?></span>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'pure-metafields' ); ?></span>
							<?php echo TPMeta_Main_Menu::icon( 'external', 'pfw-link-ext' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised in icon(). ?>
						</a>
					</li>
				</ul>
			</li>
		</ul>

	</div>
</div>
