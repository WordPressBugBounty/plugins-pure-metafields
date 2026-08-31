<?php
/**
 * Options panel layout template.
 *
 * @var array      $panel         Panel args.
 * @var array      $sections      Sections keyed by id (framework-registered, flat fields).
 * @var array|null $builder_panel Raw builder JSON (contains row layout if panel was built via builder).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$first_section = ! empty( $sections ) ? key( $sections ) : '';

// Build a quick lookup: section_id → rows[] (from builder JSON).
$builder_rows = array();
if ( ! empty( $builder_panel['sections'] ) ) {
	foreach ( $builder_panel['sections'] as $bs ) {
		if ( ! empty( $bs['id'] ) && ! empty( $bs['rows'] ) ) {
			$builder_rows[ $bs['id'] ] = $bs['rows'];
		}
	}
}
?>
<div class="tpmeta-options-wrap" data-opt-name="<?php echo esc_attr( $panel['opt_name'] ); ?>">

	<?php
	// Builder edit link — only available if this panel was created via the visual builder.
	$builder_edit_url = '';
	if ( ! empty( $builder_panel ) && ! empty( $panel['opt_name'] ) ) {
		$builder_edit_url = add_query_arg(
			array(
				'page'  => 'pure-fields-options-builder',
				'start' => '1',
				'panel' => $panel['opt_name'],
			),
			admin_url( 'admin.php' )
		);
	}

	// Customizer deep-link — only when the panel opts in.
	$customizer_url = '';
	if ( ! empty( $panel['customizer_integration'] ) && ! empty( $panel['opt_name'] ) ) {
		$customizer_url = add_query_arg(
			array(
				'autofocus[panel]' => $panel['opt_name'],
				'return'           => rawurlencode( esc_url_raw( ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ) ) ),
			),
			admin_url( 'customize.php' )
		);
	}
	?>
	<header class="tpmeta-options-header">
		<div class="tpmeta-options-header-left">
			<img class="tpmeta-options-header-logo" src="<?php echo esc_url( TPMETA_URL . 'assets/img/logo.png' ); ?>" alt="" />
			<h1 class="tpmeta-options-title"><?php echo esc_html( $panel['page_title'] ); ?></h1>
		</div>
		<div class="tpmeta-options-header-actions">
			<?php if ( $builder_edit_url ) : ?>
				<a class="tpmeta-options-header-btn tpmeta-options-header-btn--builder"
				   href="<?php echo esc_url( $builder_edit_url ); ?>"
				   title="<?php esc_attr_e( 'Open this panel in the Options Builder', 'pure-metafields' ); ?>">
					<span class="dashicons dashicons-edit"></span>
					<span class="tpmeta-options-header-btn-label"><?php esc_html_e( 'Edit with Builder', 'pure-metafields' ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $customizer_url ) : ?>
				<a class="tpmeta-options-header-btn tpmeta-options-header-btn--customizer"
				   href="<?php echo esc_url( $customizer_url ); ?>"
				   title="<?php esc_attr_e( 'Open this panel in the WordPress Customizer', 'pure-metafields' ); ?>">
					<span class="dashicons dashicons-admin-appearance"></span>
					<span class="tpmeta-options-header-btn-label"><?php esc_html_e( 'Open in Customizer', 'pure-metafields' ); ?></span>
				</a>
			<?php endif; ?>
			<span class="tpmeta-options-status" aria-live="polite"></span>
			<button type="button" class="tpmeta-options-save">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Save Changes', 'pure-metafields' ); ?>
			</button>
		</div>
	</header>

	<div class="tpmeta-options-body">

		<nav class="tpmeta-options-nav" aria-label="<?php esc_attr_e( 'Options sections', 'pure-metafields' ); ?>">
			<ul>
				<?php foreach ( $sections as $section_id => $section ) : ?>
					<li>
						<a href="#tpmeta-section-<?php echo esc_attr( $section_id ); ?>"
						   class="tpmeta-options-nav-item<?php echo ( $section_id === $first_section ) ? ' is-active' : ''; ?>"
						   data-section="<?php echo esc_attr( $section_id ); ?>">
							<span class="tpmeta-options-nav-icon dashicons <?php echo ! empty( $section['icon'] ) ? esc_attr( $section['icon'] ) : 'dashicons-admin-generic'; ?>"></span>
							<span class="tpmeta-options-nav-label"><?php echo esc_html( $section['title'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<form class="tpmeta-options-form" onsubmit="return false;">
			<?php wp_nonce_field( 'tpmeta_options_save', '_tpmeta_options_nonce' ); ?>

			<?php
			/*
			 * Zero-flash init: synchronous inline script that runs before the
			 * browser renders any <section>. At this point the <nav> is already
			 * painted so we can correct its active state immediately.
			 * Sections are not yet in the DOM, so we inject a temporary <style>
			 * that overrides the PHP-default is-active class and forces only the
			 * hash-targeted section to show. jQuery cleans this up on DOM-ready.
			 */
			?>
			<script>
			(function () {
				var h = location.hash;
				if ( ! h ) return;
				var id    = h.replace( /^#tpmeta-section-/, '' );
				var valid = <?php echo wp_json_encode( array_keys( $sections ) ); ?>;
				var first = <?php echo wp_json_encode( $first_section ); ?>;
				if ( valid.indexOf( id ) === -1 || id === first ) return;

				/* 1. Fix nav — already in DOM */
				document.querySelectorAll( '.tpmeta-options-nav-item' ).forEach( function ( el ) {
					el.classList.toggle( 'is-active', el.getAttribute( 'data-section' ) === id );
				} );

				/* 2. Inject style before sections are painted */
				var s  = document.createElement( 'style' );
				s.id   = 'tpmeta-no-flash';
				s.textContent =
					'.tpmeta-options-section.is-active{display:none!important;animation:none!important}' +
					'#tpmeta-section-' + id + '{display:block!important;animation:none!important}';
				document.head.appendChild( s );
			}());
			</script>

			<?php foreach ( $sections as $section_id => $section ) :
				$is_first = ( $section_id === $first_section );
				$rows     = isset( $builder_rows[ $section_id ] ) ? $builder_rows[ $section_id ] : array();
			?>
			<section
				id="tpmeta-section-<?php echo esc_attr( $section_id ); ?>"
				class="tpmeta-options-section<?php echo $is_first ? ' is-active' : ''; ?>"
				data-section="<?php echo esc_attr( $section_id ); ?>">

				<div class="tpmeta-options-section-header">
					<?php if ( ! empty( $section['icon'] ) ) : ?>
						<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?> tpmeta-section-header-icon"></span>
					<?php endif; ?>
					<div>
						<h2><?php echo esc_html( $section['title'] ); ?></h2>
						<?php if ( ! empty( $section['description'] ) ) : ?>
							<p class="tpmeta-section-description"><?php echo esc_html( $section['description'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( ! empty( $rows ) ) :
					// Builder-defined panel — render with row/grid/flex layout.
					// $rows contains layout + field IDs; we match against $section['fields']
					// which has the actual field definitions (with defaults etc.).
					$fields_by_id = array();
					foreach ( (array) $section['fields'] as $f ) {
						if ( ! empty( $f['id'] ) ) {
							$fields_by_id[ $f['id'] ] = $f;
						}
					}
				?>
					<?php foreach ( $rows as $row ) :
						$layout  = isset( $row['layout'] ) ? $row['layout'] : 'grid';
						$cols    = isset( $row['columns'] ) ? absint( $row['columns'] ) : 1;
						$r_fields = isset( $row['fields'] ) ? (array) $row['fields'] : array();
						if ( empty( $r_fields ) ) continue;
						$direction  = isset( $row['direction'] ) && 'column' === $row['direction'] ? 'column' : 'row';
						$wrap       = isset( $row['wrap'] ) && 'nowrap' === $row['wrap'] ? 'nowrap' : 'wrap';
						$grid_class = 'tpmeta-panel-row-inner layout-' . esc_attr( $layout ) . ' cols-' . $cols;
						if ( 'flex' === $layout ) {
							$grid_class .= ' dir-' . esc_attr( $direction ) . ' ' . esc_attr( $wrap );
						}
					?>
					<div class="tpmeta-panel-row">
						<div class="<?php echo esc_attr( $grid_class ); ?>">
							<?php foreach ( $r_fields as $row_field ) :
								$field_id = is_array( $row_field ) ? ( $row_field['id'] ?? '' ) : $row_field;
								$field    = isset( $fields_by_id[ $field_id ] ) ? $fields_by_id[ $field_id ] : null;
								if ( ! $field ) continue;
								$cond_attr = '';
								if ( ! empty( $field['conditional'] ) && is_array( $field['conditional'] ) && count( $field['conditional'] ) === 3 ) {
									$cond_attr = ' data-conditional="' . esc_attr( wp_json_encode( array(
										'field'    => $field['conditional'][0],
										'operator' => $field['conditional'][1],
										'value'    => $field['conditional'][2],
									) ) ) . '"';
								}
							?>
								<div class="tpmeta-panel-field"<?php echo $cond_attr; ?>>
									<?php tpmeta_render_options_field( $field ); ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endforeach; ?>

				<?php else :
					// PHP-defined panel (no builder rows) — render flat with one column.
				?>
					<div class="tpmeta-panel-row">
						<div class="tpmeta-panel-row-inner layout-grid cols-1">
							<?php foreach ( (array) $section['fields'] as $field ) :
								$cond_attr = '';
								if ( ! empty( $field['conditional'] ) && is_array( $field['conditional'] ) && count( $field['conditional'] ) === 3 ) {
									$cond_attr = ' data-conditional="' . esc_attr( wp_json_encode( array(
										'field'    => $field['conditional'][0],
										'operator' => $field['conditional'][1],
										'value'    => $field['conditional'][2],
									) ) ) . '"';
								}
							?>
								<div class="tpmeta-panel-field"<?php echo $cond_attr; ?>>
									<?php tpmeta_render_options_field( $field ); ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

			</section>
			<?php endforeach; ?>

			<!-- Sticky panel footer: mirrors the header Save so users don't have to scroll up.
			     Uses the same .tpmeta-options-save class — the existing handler binds to it. -->
			<footer class="tpmeta-options-footer">
				<span class="tpmeta-options-footer-hint"><?php esc_html_e( 'Unsaved changes are kept until you save.', 'pure-metafields' ); ?></span>
				<span class="tpmeta-options-status tpmeta-options-status--footer" aria-live="polite"></span>
				<button type="button" class="tpmeta-options-save">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Changes', 'pure-metafields' ); ?>
				</button>
			</footer>
		</form>
	</div>
</div>
