<?php
/**
 * Options Builder — welcome screen (software-UI layout).
 * $first_visit (set by render_page) controls the transition animation.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$logo_url    = TPMETA_URL . 'assets/img/logo.png';
$first_visit = isset( $first_visit ) ? (bool) $first_visit : false;
$builder_url = add_query_arg( 'start', '1' );

// Load panels + compute stats
$panels       = class_exists( 'TPMeta_Builder_Store' ) ? TPMeta_Builder_Store::all() : array();
$total_fields = 0;
$panel_stats  = array();

foreach ( $panels as $idx => $panel ) {
	$fc = 0;
	foreach ( $panel['sections'] ?? [] as $section ) {
		foreach ( $section['rows'] ?? [] as $row ) {
			$fc += count( $row['fields'] ?? [] );
		}
	}
	$total_fields          += $fc;
	$panel_stats[ $idx ]    = array(
		'sections' => count( $panel['sections'] ?? [] ),
		'fields'   => $fc,
	);
}
?>

<div class="tpmeta-welcome-page">

	<!-- ── TOP BAR ─────────────────────────────────────────── -->
	<div class="tpmeta-wlc-bar">
		<div class="tpmeta-wlc-bar-brand">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="tpmeta-wlc-bar-logo" width="22" height="22">
			<span class="tpmeta-wlc-bar-name">Options Builder</span>
		</div>
		<div class="tpmeta-wlc-bar-sep"></div>
		<span class="tpmeta-wlc-bar-crumb">ThemePure</span>
		<div class="tpmeta-wlc-bar-mid"></div>
		<span class="tpmeta-wlc-version-chip">v<?php echo esc_html( TPMETA_VERSION ); ?></span>
		<a href="https://themepure.net" target="_blank" rel="noopener" class="tpmeta-wlc-bar-link">
			themepure.net
			<svg width="10" height="10" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M1 11L11 1M11 1H4M11 1V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</a>
	</div>

	<!-- ── BODY ─────────────────────────────────────────────── -->
	<div class="tpmeta-wlc-body">

		<!-- LEFT SIDEBAR -->
		<aside class="tpmeta-wlc-sidebar">

			<button class="tpmeta-wlc-open-btn" id="tpmeta-get-started" type="button">
				<span class="dashicons dashicons-layout"></span>
				<?php esc_html_e( 'Open Builder', 'pure-metafields' ); ?>
				<span class="tpmeta-wlc-open-arrow">→</span>
			</button>

			<div class="tpmeta-wlc-sb-group">
				<div class="tpmeta-wlc-sb-label"><?php esc_html_e( 'Create', 'pure-metafields' ); ?></div>
				<button type="button" class="tpmeta-wlc-sb-action tpmeta-wlc-goto">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'New Panel', 'pure-metafields' ); ?>
				</button>
				<button type="button" class="tpmeta-wlc-sb-action tpmeta-wlc-goto">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Import JSON', 'pure-metafields' ); ?>
				</button>
				<button type="button" class="tpmeta-wlc-sb-action tpmeta-wlc-goto">
					<span class="dashicons dashicons-images-alt2"></span>
					<?php esc_html_e( 'Starter Templates', 'pure-metafields' ); ?>
				</button>
			</div>

			<div class="tpmeta-wlc-sb-divider"></div>

			<div class="tpmeta-wlc-sb-group">
				<div class="tpmeta-wlc-sb-label"><?php esc_html_e( 'Capabilities', 'pure-metafields' ); ?></div>
				<ul class="tpmeta-wlc-caps">
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Visual drag & drop', 'pure-metafields' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( '21 field types', 'pure-metafields' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Export to PHP', 'pure-metafields' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Customizer sync', 'pure-metafields' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Auto CSS output', 'pure-metafields' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Bake to PHP file', 'pure-metafields' ); ?></li>
				</ul>
			</div>

			<div class="tpmeta-wlc-sb-footer">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="" width="14" height="14">
				ThemePure &middot; v<?php echo esc_html( TPMETA_VERSION ); ?>
			</div>

		</aside>

		<!-- MAIN CONTENT -->
		<main class="tpmeta-wlc-main">

			<!-- PANELS -->
			<section class="tpmeta-wlc-section">
				<div class="tpmeta-wlc-section-hd">
					<h2>
						<span class="dashicons dashicons-layout tpmeta-wlc-section-icon"></span>
						<?php esc_html_e( 'Panels', 'pure-metafields' ); ?>
						<span class="tpmeta-wlc-count"><?php echo count( $panels ); ?></span>
					</h2>
					<?php if ( ! empty( $panels ) ) : ?>
					<a href="<?php echo esc_url( $builder_url ); ?>" class="tpmeta-wlc-section-link"><?php esc_html_e( 'Open Builder →', 'pure-metafields' ); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $panels ) ) : ?>
				<div class="tpmeta-wlc-panels-grid">
					<?php foreach ( $panels as $idx => $panel ) :
						$sc   = $panel_stats[ $idx ]['sections'];
						$fc   = $panel_stats[ $idx ]['fields'];
						$icon = ! empty( $panel['menu_icon'] ) ? $panel['menu_icon'] : 'admin-settings';
					?>
					<a href="<?php echo esc_url( $builder_url ); ?>" class="tpmeta-wlc-panel-card">
						<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?> tpmeta-wlc-panel-icon"></span>
						<div class="tpmeta-wlc-panel-info">
							<div class="tpmeta-wlc-panel-name"><?php echo esc_html( $panel['menu_title'] ?? $panel['opt_name'] ); ?></div>
							<div class="tpmeta-wlc-panel-meta">
								<?php
								/* translators: %d section count */
								printf( _n( '%d section', '%d sections', $sc, 'pure-metafields' ), $sc );
								echo ' &middot; ';
								/* translators: %d field count */
								printf( _n( '%d field', '%d fields', $fc, 'pure-metafields' ), $fc );
								?>
							</div>
						</div>
						<span class="dashicons dashicons-arrow-right-alt2 tpmeta-wlc-panel-arrow"></span>
					</a>
					<?php endforeach; ?>

					<button type="button" class="tpmeta-wlc-panel-card tpmeta-wlc-panel-new tpmeta-wlc-goto">
						<span class="dashicons dashicons-plus-alt2 tpmeta-wlc-panel-icon"></span>
						<div class="tpmeta-wlc-panel-info">
							<div class="tpmeta-wlc-panel-name"><?php esc_html_e( 'New Panel', 'pure-metafields' ); ?></div>
							<div class="tpmeta-wlc-panel-meta"><?php esc_html_e( 'Create from scratch', 'pure-metafields' ); ?></div>
						</div>
					</button>
				</div>

				<?php else : ?>
				<div class="tpmeta-wlc-empty-panels">
					<span class="dashicons dashicons-layout tpmeta-wlc-empty-icon"></span>
					<p><?php esc_html_e( 'No panels yet. Create your first options panel to get started.', 'pure-metafields' ); ?></p>
					<button type="button" class="tpmeta-wlc-create-btn tpmeta-wlc-goto">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Create First Panel', 'pure-metafields' ); ?>
					</button>
				</div>
				<?php endif; ?>
			</section>

			<!-- TWO-COLUMN: checklist + quick reference -->
			<div class="tpmeta-wlc-two-col">

				<!-- GETTING STARTED -->
				<section class="tpmeta-wlc-section">
					<div class="tpmeta-wlc-section-hd">
						<h2>
							<span class="dashicons dashicons-list-view tpmeta-wlc-section-icon"></span>
							<?php esc_html_e( 'Get Started', 'pure-metafields' ); ?>
						</h2>
					</div>
					<ol class="tpmeta-wlc-checklist">
						<li class="tpmeta-wlc-check is-done">
							<span class="tpmeta-wlc-check-dot"></span>
							<span><?php esc_html_e( 'Install PureFields plugin', 'pure-metafields' ); ?></span>
						</li>
						<li class="tpmeta-wlc-check <?php echo ! empty( $panels ) ? 'is-done' : 'is-next'; ?>">
							<span class="tpmeta-wlc-check-dot"></span>
							<span><?php esc_html_e( 'Create an options panel', 'pure-metafields' ); ?></span>
							<?php if ( empty( $panels ) ) : ?>
							<button type="button" class="tpmeta-wlc-check-cta tpmeta-wlc-goto"><?php esc_html_e( 'Create →', 'pure-metafields' ); ?></button>
							<?php endif; ?>
						</li>
						<li class="tpmeta-wlc-check <?php echo ! empty( $panels ) ? 'is-next' : ''; ?>">
							<span class="tpmeta-wlc-check-dot"></span>
							<span><?php esc_html_e( 'Add sections & drag in fields', 'pure-metafields' ); ?></span>
						</li>
						<li class="tpmeta-wlc-check">
							<span class="tpmeta-wlc-check-dot"></span>
							<span><?php esc_html_e( 'Save — values auto-stored as theme mods', 'pure-metafields' ); ?></span>
						</li>
						<li class="tpmeta-wlc-check">
							<span class="tpmeta-wlc-check-dot"></span>
							<span><?php esc_html_e( 'Read values in your theme PHP', 'pure-metafields' ); ?></span>
						</li>
					</ol>
				</section>

				<!-- QUICK REFERENCE -->
				<section class="tpmeta-wlc-section">
					<div class="tpmeta-wlc-section-hd">
						<h2>
							<span class="dashicons dashicons-editor-code tpmeta-wlc-section-icon"></span>
							<?php esc_html_e( 'Quick Reference', 'pure-metafields' ); ?>
						</h2>
					</div>
					<div class="tpmeta-wlc-ref-list">
						<?php
						$snippets = array(
							array( __( 'Read option', 'pure-metafields' ),   "tpmeta_get_option( 'field_id', \$default )" ),
							array( __( 'Theme mod', 'pure-metafields' ),     "get_theme_mod( 'field_id', \$default )" ),
							array( __( 'Repeater', 'pure-metafields' ),      "tpmeta_get_repeater_rows( 'repeater_id' )" ),
							array( __( 'Metabox', 'pure-metafields' ),       "tpmeta_field( 'field_id', \$post_id )" ),
						);
						foreach ( $snippets as $snip ) :
						?>
						<div class="tpmeta-wlc-ref-item">
							<span class="tpmeta-wlc-ref-label"><?php echo esc_html( $snip[0] ); ?></span>
							<code class="tpmeta-wlc-ref-code"><?php echo esc_html( $snip[1] ); ?></code>
							<button type="button" class="tpmeta-wlc-copy-btn" data-copy="<?php echo esc_attr( $snip[1] ); ?>" title="<?php esc_attr_e( 'Copy to clipboard', 'pure-metafields' ); ?>">
								<span class="dashicons dashicons-clipboard"></span>
							</button>
						</div>
						<?php endforeach; ?>
					</div>
				</section>

			</div><!-- /.tpmeta-wlc-two-col -->

		</main>
	</div><!-- /.tpmeta-wlc-body -->

	<!-- ── STATUS BAR ──────────────────────────────────────── -->
	<div class="tpmeta-wlc-statusbar">
		<span class="dashicons dashicons-admin-settings tpmeta-wlc-sb-icon"></span>
		<span>
			<?php
			printf(
				_n( '%d panel', '%d panels', count( $panels ), 'pure-metafields' ),
				count( $panels )
			);
			?>
		</span>
		<span class="tpmeta-wlc-sb-dot"></span>
		<span>
			<?php
			printf(
				_n( '%d field', '%d fields', $total_fields, 'pure-metafields' ),
				$total_fields
			);
			?>
		</span>
		<span class="tpmeta-wlc-sb-dot"></span>
		<span>Options Builder v<?php echo esc_html( TPMETA_VERSION ); ?></span>
		<span style="flex:1 1 auto"></span>
		<a href="https://themepure.net" target="_blank" rel="noopener" class="tpmeta-wlc-sb-link">themepure.net</a>
	</div>

</div><!-- /.tpmeta-welcome-page -->

<!-- ── TRANSITION OVERLAY ───────────────────────────────── -->
<div class="tpmeta-transition-overlay" id="tpmeta-transition-overlay" aria-hidden="true">
	<div class="tpmeta-transition-inner">

		<div class="tpmeta-transition-greeting">
			<div class="tpmeta-transition-wave">👋</div>
			<h2 class="tpmeta-transition-hi"><?php esc_html_e( 'Hello! Welcome aboard.', 'pure-metafields' ); ?></h2>
		</div>

		<div class="tpmeta-transition-logo">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="tpmeta-transition-mark" width="52" height="52" aria-hidden="true">
			<div class="tpmeta-transition-brand-wrap">
				<span class="tpmeta-transition-brand-name">themepure<span class="tpmeta-transition-brand-dot">.</span></span>
				<span class="tpmeta-transition-brand-sub">Options Builder</span>
			</div>
		</div>

		<div class="tpmeta-transition-spinner">
			<div class="tpmeta-transition-ring"></div>
		</div>

		<p class="tpmeta-transition-msg" id="tpmeta-transition-msg"><?php esc_html_e( 'Opening something exciting…', 'pure-metafields' ); ?></p>

		<div class="tpmeta-transition-progress">
			<div class="tpmeta-transition-progress-bar" id="tpmeta-transition-progress-bar"></div>
		</div>

		<div class="tpmeta-transition-steps" id="tpmeta-transition-steps">
			<div class="tpmeta-transition-step is-active">
				<div class="tpmeta-transition-step-dot"></div>
				<span><?php esc_html_e( 'Opening builder', 'pure-metafields' ); ?></span>
			</div>
			<div class="tpmeta-transition-step">
				<div class="tpmeta-transition-step-dot"></div>
				<span><?php esc_html_e( 'Loading tools', 'pure-metafields' ); ?></span>
			</div>
			<div class="tpmeta-transition-step">
				<div class="tpmeta-transition-step-dot"></div>
				<span><?php esc_html_e( 'Getting ready', 'pure-metafields' ); ?></span>
			</div>
			<div class="tpmeta-transition-step">
				<div class="tpmeta-transition-step-dot"></div>
				<span><?php esc_html_e( 'Almost there', 'pure-metafields' ); ?></span>
			</div>
		</div>

	</div>
</div>

<script>
(function () {
	var overlay     = document.getElementById('tpmeta-transition-overlay');
	var msgEl       = document.getElementById('tpmeta-transition-msg');
	var progressBar = document.getElementById('tpmeta-transition-progress-bar');
	var stepEls     = document.querySelectorAll('.tpmeta-transition-step');
	var firstVisit  = <?php echo $first_visit ? 'true' : 'false'; ?>;
	var builderUrl  = <?php echo wp_json_encode( $builder_url ); ?>;

	var messages = [
		<?php
		echo "'" . esc_js( __( 'Opening something exciting…',   'pure-metafields' ) ) . "',\n";
		echo "\t\t'" . esc_js( __( 'Getting your builder ready…',   'pure-metafields' ) ) . "',\n";
		echo "\t\t'" . esc_js( __( 'Loading fields and tools…',     'pure-metafields' ) ) . "',\n";
		echo "\t\t'" . esc_js( __( 'Ready to build great things!',  'pure-metafields' ) ) . "',\n";
		?>
	];

	function advanceStep(idx) {
		stepEls.forEach(function (el, i) {
			el.classList.toggle('is-active', i === idx);
			el.classList.toggle('is-done',   i < idx);
		});
	}

	function launchTransition() {
		if ( ! firstVisit || ! overlay ) {
			window.location.href = builderUrl;
			return;
		}
		overlay.classList.add('is-active');
		overlay.removeAttribute('aria-hidden');

		if (progressBar) {
			setTimeout(function () { progressBar.style.width = '25%'; },   80);
			setTimeout(function () { progressBar.style.width = '55%'; },  700);
			setTimeout(function () { progressBar.style.width = '80%'; }, 1400);
			setTimeout(function () { progressBar.style.width = '100%'; }, 2050);
		}

		var idx = 0;
		var interval = setInterval(function () {
			idx++;
			advanceStep(idx);
			if (idx < messages.length && msgEl) {
				msgEl.classList.add('is-fading');
				setTimeout(function () {
					msgEl.textContent = messages[idx];
					msgEl.classList.remove('is-fading');
				}, 280);
			}
		}, 650);

		setTimeout(function () {
			clearInterval(interval);
			window.location.href = builderUrl;
		}, 2600);
	}

	// ── Primary "Open Builder" button ──
	var mainBtn = document.getElementById('tpmeta-get-started');
	if (mainBtn) {
		mainBtn.addEventListener('click', function () {
			mainBtn.disabled = true;
			launchTransition();
		});
	}

	// ── All sidebar/checklist/empty-state action buttons ──
	document.querySelectorAll('.tpmeta-wlc-goto').forEach(function (btn) {
		btn.addEventListener('click', launchTransition);
	});

	// ── Copy buttons ──
	document.querySelectorAll('.tpmeta-wlc-copy-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var text = btn.getAttribute('data-copy');
			if (!text || !navigator.clipboard) return;
			navigator.clipboard.writeText(text).then(function () {
				var icon = btn.querySelector('.dashicons');
				btn.classList.add('is-copied');
				if (icon) {
					icon.classList.remove('dashicons-clipboard');
					icon.classList.add('dashicons-yes');
				}
				setTimeout(function () {
					btn.classList.remove('is-copied');
					if (icon) {
						icon.classList.remove('dashicons-yes');
						icon.classList.add('dashicons-clipboard');
					}
				}, 1600);
			});
		});
	});
}());
</script>
