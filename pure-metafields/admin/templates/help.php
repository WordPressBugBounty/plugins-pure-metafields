<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php

/* ── Code snippet strings (HTML-escaped for output) ───────────── */

$snip = array();

$snip['mf_register'] = <<<'PHP'
add_filter( 'tp_meta_boxes', 'my_metaboxes' );
function my_metaboxes( $meta_boxes ) {

    $meta_boxes[] = array(
        'metabox_id' => '_my_box',
        'title'      => 'Page Options',
        'post_type'  => 'page',    // post · page · custom-post-type
        'context'    => 'normal',  // normal · side · advanced
        'priority'   => 'high',    // high · core · default · low
        'fields'     => array(

            array(
                'id'          => '_hero_heading',
                'type'        => 'text',
                'label'       => 'Hero Heading',
                'placeholder' => 'Enter heading…',
                'default'     => '',
            ),

            array(
                'id'      => '_layout',
                'type'    => 'select',
                'label'   => 'Page Layout',
                'default' => 'boxed',
                'options' => array(
                    'boxed'  => 'Boxed',
                    'fluid'  => 'Fluid',
                    'framed' => 'Framed',
                ),
            ),

        ),
    );

    return $meta_boxes;
}
PHP;

$snip['mf_fields'] = <<<'PHP'
// ── Text & Content ──────────────────────────────
array( 'id' => '_field', 'type' => 'text',        'label' => 'Text',        'default' => '' )
array( 'id' => '_field', 'type' => 'textarea',    'label' => 'Textarea',    'default' => '' )
array( 'id' => '_field', 'type' => 'editor',      'label' => 'Editor',      'default' => '' )
array( 'id' => '_field', 'type' => 'url',         'label' => 'URL',         'default' => '' )
array( 'id' => '_field', 'type' => 'number',      'label' => 'Number',      'default' => 0  )

// ── Media ────────────────────────────────────────
array( 'id' => '_field', 'type' => 'image',       'label' => 'Image',       'default' => '' )
array( 'id' => '_field', 'type' => 'gallery',     'label' => 'Gallery',     'default' => '' )

// ── Choice ───────────────────────────────────────
array( 'id' => '_field', 'type' => 'switch',      'label' => 'Toggle',      'default' => 'off' )
array( 'id' => '_field', 'type' => 'checkbox',    'label' => 'Checkboxes',  'options' => array( /* value => label */ ) )
array( 'id' => '_field', 'type' => 'select',      'label' => 'Select',      'options' => array( /* value => label */ ) )
array( 'id' => '_field', 'type' => 'tabs',        'label' => 'Tabs',        'choices' => array( /* value => label */ ) )
array( 'id' => '_field', 'type' => 'select_posts','label' => 'Post Select', 'post_type' => 'post' )

// ── Date & Colour ────────────────────────────────
array( 'id' => '_field', 'type' => 'datepicker',     'label' => 'Date',         'default' => '' )
array( 'id' => '_field', 'type' => 'colorpicker',    'label' => 'Color',        'default' => '#fff' )
array( 'id' => '_field', 'type' => 'color_gradient', 'label' => 'Gradient',     'default' => '' )
array( 'id' => '_field', 'type' => 'multicolor',     'label' => 'Multi Color',  'options' => array( /* value => label */ ) )

// ── Layout ───────────────────────────────────────
array( 'id' => '_field', 'type' => 'repeater',    'label' => 'Repeater',    'fields' => array( /* sub-fields */ ) )
array( 'id' => '_field', 'type' => 'group',       'label' => 'Group',       'fields' => array( /* sub-fields */ ) )
PHP;

$snip['mf_conditional'] = <<<'PHP'
// Field that controls visibility
array(
    'id'      => '_show_cta',
    'type'    => 'switch',
    'label'   => 'Show Call to Action',
    'default' => 'off',
),

// Field shown only when switch is 'on'
array(
    'id'          => '_cta_text',
    'type'        => 'text',
    'label'       => 'CTA Button Text',
    'default'     => '',
    'conditional' => array( '_show_cta', '==', 'on' ),
),
PHP;

$snip['mf_fetch'] = <<<'PHP'
// Current post
$value = tpmeta_field( '_hero_heading' );

// Specific post
$value = tpmeta_field( '_hero_heading', $post_id );

// With fallback
$value = tpmeta_field( '_hero_heading' ) ?: 'Default heading';

echo esc_html( $value );
PHP;

$snip['mf_editor'] = <<<'PHP'
// Editor fields are stored as HTML — already sanitized with wp_kses_post()
// at save time, so the value is safe to echo. Best rendered with
// the_content filter so paragraphs and shortcodes work.

// ✅ Inside a template (single.php, page.php, content-*.php) — works because
//    The Loop has already set up the global $post.
$content = tpmeta_field( '_about_description' );
echo apply_filters( 'the_content', $content );

// ✅ Anywhere else (functions.php, hooks before the_post(), AJAX, REST) —
//    pass an explicit post ID. tpmeta_field() with no 2nd arg reads
//    $post->ID from the global $post which is not set yet outside the Loop.
$post_id = get_queried_object_id();              // current page/post
$content = tpmeta_field( '_about_description', $post_id );
echo apply_filters( 'the_content', $content );

// ✅ Render the raw sanitized HTML without paragraph/shortcode processing
echo wp_kses_post( tpmeta_field( '_about_description' ) );

// ❌ This will not work — $post is not set at top level of functions.php
echo tpmeta_field( '_about_description' );        // returns false here
PHP;

$snip['mf_image'] = <<<'PHP'
$img = tpmeta_image_field( '_hero_image', 'full' );
// Returns: [ 'url' => '…', 'alt' => '…', 'width' => 0, 'height' => 0 ]

if ( $img ) {
    echo '<img src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $img['alt'] ) . '">';
}
PHP;

$snip['mf_gallery'] = <<<'PHP'
$gallery = tpmeta_gallery_field( '_project_gallery', 'medium' );
// Returns array of [ 'url', 'alt', 'width', 'height' ]

foreach ( $gallery as $img ) {
    echo '<img src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $img['alt'] ) . '">';
}
PHP;

$snip['mf_repeater'] = <<<'PHP'
// tpmeta_field() returns an array of row arrays
$rows = tpmeta_field( '_team_members' );

foreach ( $rows as $row ) {
    echo esc_html( $row['_name'] );
    echo esc_url(  $row['_link'] );

    // Image inside a repeater row
    $photo = tpmeta_image( $row['_photo'], 'thumbnail' );
    echo '<img src="' . esc_url( $photo['url'] ) . '" alt="' . esc_attr( $photo['alt'] ) . '">';
}
PHP;

/* ── Options Panel snippets ─────────────────────────── */

$snip['opt_setup'] = <<<'PHP'
add_action( 'init', function () {

    $opt = 'my_theme_options';  // unique panel ID

    TPMeta_Options::set_args( $opt, array(
        'menu_title'             => 'Theme Options',
        'page_title'             => 'Theme Options',
        'menu_slug'              => 'my-theme-options',
        'menu_icon'              => 'dashicons-admin-appearance',
        'menu_position'          => 60,
        'capability'             => 'manage_options',
        'output_css'             => true,   // auto-print CSS from 'output' rules
        'customizer_integration' => false,  // true = mirror into Appearance > Customize
    ) );

    TPMeta_Options::set_section( $opt, array(
        'id'     => 'general',
        'title'  => 'General',
        'icon'   => 'dashicons-admin-generic',
        'fields' => array(

            array(
                'id'      => 'primary_color',
                'type'    => 'colorpicker',
                'label'   => 'Primary Color',
                'default' => '#3362FF',
                // Auto-inject CSS when output_css = true:
                'output'  => array( 'a, .btn-primary' => 'color' ),
            ),

            array(
                'id'          => 'site_logo',
                'type'        => 'image',
                'label'       => 'Site Logo',
                'default'     => '',
            ),

        ),
    ) );

} );
PHP;

$snip['opt_fields'] = <<<'PHP'
// ── In TPMeta_Options::set_section() fields array ────
array( 'id' => 'field', 'type' => 'text',            'label' => 'Text',        'default' => '' )
array( 'id' => 'field', 'type' => 'textarea',        'label' => 'Textarea',    'default' => '' )
array( 'id' => 'field', 'type' => 'editor',          'label' => 'Editor',      'default' => '' )
array( 'id' => 'field', 'type' => 'colorpicker',     'label' => 'Color',       'default' => '#fff',
       'output' => array( '.selector' => 'color' ) )
array( 'id' => 'field', 'type' => 'color_gradient',  'label' => 'Gradient',    'default' => '' )
array( 'id' => 'field', 'type' => 'multicolor',      'label' => 'Multi Color', 'options' => array( 'key' => 'Label' ) )
array( 'id' => 'field', 'type' => 'image',           'label' => 'Image',       'default' => '' )
array( 'id' => 'field', 'type' => 'gallery',         'label' => 'Gallery',     'default' => '' )
array( 'id' => 'field', 'type' => 'select',          'label' => 'Select',      'options' => array( 'key' => 'Label' ) )
array( 'id' => 'field', 'type' => 'switch',          'label' => 'Toggle',      'default' => 'off' )
array( 'id' => 'field', 'type' => 'checkbox',        'label' => 'Checkboxes',  'options' => array( 'key' => 'Label' ) )
array( 'id' => 'field', 'type' => 'tabs',            'label' => 'Tab Group',   'choices' => array( 'key' => 'Label' ) )
array( 'id' => 'field', 'type' => 'datepicker',      'label' => 'Date',        'default' => '' )
array( 'id' => 'field', 'type' => 'post_select',     'label' => 'Post Select', 'post_type' => 'page' )
array( 'id' => 'field', 'type' => 'repeater',        'label' => 'Repeater',    'fields' => array( /* sub-fields */ ) )
PHP;

$snip['opt_read'] = <<<'PHP'
// Recommended helper — returns default when not set
$color = tpmeta_get_option( 'primary_color', '#3362FF' );

// Standard WordPress API (same storage)
$color = get_theme_mod( 'primary_color', '#3362FF' );

echo '<style>a { color: ' . esc_attr( $color ) . '; }</style>';
PHP;

$snip['opt_repeater'] = <<<'PHP'
$rows = tpmeta_get_repeater_rows( 'social_links' );

foreach ( $rows as $row ) {
    echo '<a href="' . esc_url( $row['url'] ) . '">'
       . esc_html( $row['platform'] )
       . '</a>';
}
PHP;

/* ── Customizer snippets ─────────────────────────── */

$snip['cust_enable_php'] = <<<'PHP'
// When registering via PHP — set the flag to true
TPMeta_Options::set_args( 'my_theme_options', array(
    'menu_title'             => 'Theme Options',
    'customizer_integration' => true,  // ← that's it
) );
PHP;

$snip['cust_read'] = <<<'PHP'
// Values are stored as WordPress theme_mods.
// Both helpers read from the same storage:

$value = tpmeta_get_option( 'primary_color', '#3362FF' );

$value = get_theme_mod( 'primary_color', '#3362FF' );
PHP;

/* ── User Meta snippets ─────────────────────────── */

$snip['um_register'] = <<<'PHP'
add_filter( 'tp_user_meta', 'my_user_meta' );
function my_user_meta( $blocks ) {

    $blocks[] = array(
        'label'      => 'Social Profiles',
        'capability' => 'edit_users',   // optional — gate who sees the block
        'fields'     => array(

            array(
                'id'                  => 'twitter_handle',
                'type'                => 'text',
                'label'               => 'Twitter / X',
                'placeholder'         => '@username',
                'default'             => '',
                'show_in_admin_table' => 1,   // add a column to Users list
            ),

            array(
                'id'      => 'member_tier',
                'type'    => 'select',
                'label'   => 'Membership Tier',
                'default' => 'free',
                'options' => array(
                    'free' => 'Free',
                    'pro'  => 'Pro',
                ),
            ),

        ),
    );

    return $blocks;
}
PHP;

$snip['um_fields'] = <<<'PHP'
// ── Text & Content ──────────────────────────────
array( 'id' => 'field', 'type' => 'text',        'label' => 'Text',      'default' => '' )
array( 'id' => 'field', 'type' => 'textarea',    'label' => 'Textarea',  'default' => '' )
array( 'id' => 'field', 'type' => 'editor',      'label' => 'Editor',    'default' => '' )
array( 'id' => 'field', 'type' => 'url',         'label' => 'URL',       'default' => '' )
array( 'id' => 'field', 'type' => 'number',      'label' => 'Number',    'default' => 0  )

// ── Media ────────────────────────────────────────
array( 'id' => 'field', 'type' => 'image',       'label' => 'Image',     'default' => '' )
array( 'id' => 'field', 'type' => 'gallery',     'label' => 'Gallery',   'default' => '' )

// ── Choice ───────────────────────────────────────
array( 'id' => 'field', 'type' => 'switch',      'label' => 'Toggle',       'default' => 'off' )
array( 'id' => 'field', 'type' => 'checkbox',    'label' => 'Checkbox',     'default' => 'off' )
array( 'id' => 'field', 'type' => 'select',      'label' => 'Select',       'options' => array( /* value => label */ ) )
array( 'id' => 'field', 'type' => 'select',      'label' => 'Multi Select', 'multiple' => true,
       'options' => array( /* value => label */ ) )
array( 'id' => 'field', 'type' => 'select_posts','label' => 'Post Select',  'post_type' => 'post' )

// ── Date & Colour ────────────────────────────────
array( 'id' => 'field', 'type' => 'datepicker',     'label' => 'Date',        'default' => '' )
array( 'id' => 'field', 'type' => 'colorpicker',    'label' => 'Color',       'default' => '#fff' )
array( 'id' => 'field', 'type' => 'color_gradient', 'label' => 'Gradient',    'default' => '' )
array( 'id' => 'field', 'type' => 'multicolor',     'label' => 'Multi Color', 'options' => array( /* key => label */ ) )

// ── Layout ───────────────────────────────────────
// Visual divider only — no value is read or saved.
array( 'type' => 'section_heading', 'label' => 'Section Title', 'description' => 'Optional sub-text' )
PHP;

$snip['um_read'] = <<<'PHP'
// User-meta values are stored in wp_usermeta with the field 'id' as the
// meta key, so read them with the standard WordPress function:

$handle = get_user_meta( $user_id, 'twitter_handle', true );
echo esc_html( $handle );

// Current logged-in user
$tier = get_user_meta( get_current_user_id(), 'member_tier', true );

// The author of the current post (e.g. inside The Loop)
$author_id     = get_the_author_meta( 'ID' );
$author_handle = get_user_meta( $author_id, 'twitter_handle', true );
PHP;

$snip['um_table'] = <<<'PHP'
// Add 'show_in_admin_table' => 1 to any field and its value becomes a
// column on Users -> All Users. For 'image' fields the thumbnail is
// rendered automatically; every other type prints the raw stored value.

array(
    'id'                  => 'member_tier',
    'type'                => 'select',
    'label'               => 'Tier',
    'show_in_admin_table' => 1,   // <- that's it
    'options'             => array( 'free' => 'Free', 'pro' => 'Pro' ),
),
PHP;

$snip['um_multiblock'] = <<<'PHP'
add_filter( 'tp_user_meta', function ( $blocks ) {

    // Each array element is a separate <h2> section on the profile screen.
    $blocks[] = array(
        'label'  => 'Contact',
        'fields' => array( /* fields */ ),
    );

    $blocks[] = array(
        'label'      => 'Admin Notes',
        'capability' => 'manage_options',  // only admins see this block
        'fields'     => array( /* fields */ ),
    );

    return $blocks;
} );
PHP;

?>
<div class="pf-help">

	<!-- ══ HERO ═══════════════════════════════════════════════════ -->
	<header class="pf-hero">
		<div class="pf-hero-grid" aria-hidden="true"></div>

		<div class="pf-hero-inner">
			<div class="pf-badge"><span>⚡</span> PureFields v<?php echo esc_html( TPMETA_VERSION ); ?></div>
			<h1>Developer <em>Quick Reference</em></h1>
			<p class="pf-hero-desc">Everything you need to integrate metafields, option panels, the visual builder, and Customizer into your WordPress theme.</p>

			<div class="pf-hero-tabs">
				<button class="pf-tab-pill active" data-tab="metafields">📋 Metafields</button>
				<button class="pf-tab-pill" data-tab="options">⚙️ Options Panel</button>
				<button class="pf-tab-pill" data-tab="builder">🎨 Visual Builder</button>
				<button class="pf-tab-pill" data-tab="customizer">🖌️ Customizer</button>
				<button class="pf-tab-pill" data-tab="usermeta">👤 User Meta</button>
			</div>
		</div>

		<div class="pf-hero-deco" aria-hidden="true">
			<div class="pf-hero-window">
				<div class="pf-hero-window-bar">
					<span class="pf-hero-dot"></span>
					<span class="pf-hero-dot"></span>
					<span class="pf-hero-dot"></span>
				</div>
				<div class="pf-hero-code-block"><span class="hcm">// Get an option value</span>
<span class="hvar">$color</span> = <span class="hfn">tpmeta_get_option</span>(
  <span class="hst">'primary_color'</span>,
  <span class="hst">'#3362FF'</span>
);

<span class="hcm">// Get a meta field</span>
<span class="hvar">$title</span> = <span class="hfn">tpmeta_field</span>(
  <span class="hst">'_hero_heading'</span>
);</div>
			</div>
		</div>
	</header><!-- /hero -->

	<!-- ══ BODY ════════════════════════════════════════════════════ -->
	<div class="pf-body">

		<!-- ── Sidebar nav ───────────────────────────────────────── -->
		<aside class="pf-sidebar">
			<nav class="pf-nav">

				<!-- Metafields nav -->
				<div class="pf-nav-group active" data-tab="metafields">
					<div class="pf-nav-label">Metafields</div>
					<a href="#mf-flow">How It Works</a>
					<a href="#mf-register">Register Metabox</a>
					<a href="#mf-field-types">Field Types</a>
					<a href="#mf-fetch">Fetch a Field</a>
					<a href="#mf-image">Image &amp; Gallery</a>
					<a href="#mf-editor">Editor (Rich Text)</a>
					<a href="#mf-repeater">Repeater Data</a>
				</div>

				<!-- Options nav -->
				<div class="pf-nav-group" data-tab="options">
					<div class="pf-nav-label">Options Panel</div>
					<a href="#opt-flow">How It Works</a>
					<a href="#opt-setup">Create a Panel</a>
					<a href="#opt-fields">Field Types</a>
					<a href="#opt-read">Read Values</a>
					<a href="#opt-repeater">Repeater Rows</a>
				</div>

				<!-- Builder nav -->
				<div class="pf-nav-group" data-tab="builder">
					<div class="pf-nav-label">Visual Builder</div>
					<a href="#bld-flow">How It Works</a>
					<a href="#bld-steps">Step-by-Step</a>
					<a href="#bld-bake">Bake to PHP</a>
				</div>

				<!-- Customizer nav -->
				<div class="pf-nav-group" data-tab="customizer">
					<div class="pf-nav-label">Customizer</div>
					<a href="#cst-flow">How It Works</a>
					<a href="#cst-enable">Enable</a>
					<a href="#cst-fields">Supported Fields</a>
					<a href="#cst-read">Read Values</a>
				</div>

				<!-- User Meta nav -->
				<div class="pf-nav-group" data-tab="usermeta">
					<div class="pf-nav-label">User Meta</div>
					<a href="#um-flow">How It Works</a>
					<a href="#um-register">Register Fields</a>
					<a href="#um-field-types">Field Types</a>
					<a href="#um-read">Read Values</a>
					<a href="#um-table">Users List Column</a>
					<a href="#um-multiblock">Multiple Blocks</a>
				</div>
			</nav>
		</aside><!-- /sidebar -->

		<!-- ── Main content ──────────────────────────────────────── -->
		<main class="pf-main">

			<!-- ════════════════════════════════════════════════════
			     TAB 1 — METAFIELDS
			     ════════════════════════════════════════════════════ -->
			<div class="pf-panel" data-tab="metafields">

				<!-- Flow diagram -->
				<div class="pf-flow" id="flow-metafields">
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-indigo">⚙️</div>
						<div class="pf-flow-step-lbl">Register</div>
						<div class="pf-flow-step-sub">tp_meta_boxes filter</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-green">✏️</div>
						<div class="pf-flow-step-lbl">Post Editor</div>
						<div class="pf-flow-step-sub">Metabox renders</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-amber">💾</div>
						<div class="pf-flow-step-lbl">User Saves</div>
						<div class="pf-flow-step-sub">wp_postmeta</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-violet">📤</div>
						<div class="pf-flow-step-lbl">Read Value</div>
						<div class="pf-flow-step-sub">tpmeta_field()</div>
					</div>
				</div>

				<!-- Section: Register -->
				<section class="pf-section" id="mf-flow">
					<div class="pf-section-hd">
						<div class="pf-section-ico">⚙️</div>
						<h2>Register a Metabox</h2>
					</div>
					<p class="pf-section-desc">Hook into <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">tp_meta_boxes</code> to declare your metabox and its fields. The example below shows the full structure.</p>
					<div class="pf-code-card" id="mf-register">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Register Metabox <span class="pf-cc-tag">functions.php</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['mf_register'] ); ?></pre>
					</div>
				</section>

				<!-- Section: Field Types -->
				<section class="pf-section" id="mf-field-types">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🗂️</div>
						<h2>All Field Types</h2>
					</div>
					<p class="pf-section-desc">Every <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">type</code> value you can use inside a field definition.</p>

					<div class="pf-chip-grid">
						<?php
						// [type, accent color, dashicon]
						$field_types = array(
							array( 'text',           '#6366f1', 'editor-textcolor'  ),
							array( 'textarea',       '#6366f1', 'editor-paragraph'  ),
							array( 'editor',         '#6366f1', 'edit-page'         ),
							array( 'url',            '#6366f1', 'admin-links'       ),
							array( 'number',         '#6366f1', 'calculator'        ),
							array( 'image',          '#0891b2', 'format-image'      ),
							array( 'gallery',        '#0891b2', 'images-alt2'       ),
							array( 'switch',         '#059669', 'controls-volumeon' ),
							array( 'checkbox',       '#059669', 'yes'               ),
							array( 'select',         '#059669', 'menu-alt'          ),
							array( 'tabs',           '#059669', 'screenoptions'     ),
							array( 'select_posts',   '#059669', 'admin-post'        ),
							array( 'datepicker',     '#d97706', 'calendar-alt'      ),
							array( 'colorpicker',    '#7c3aed', 'art'               ),
							array( 'color_gradient', '#7c3aed', 'image-filter'      ),
							array( 'multicolor',     '#7c3aed', 'color-picker'      ),
							array( 'repeater',       '#be185d', 'list-view'         ),
							array( 'group',          '#be185d', 'category'          ),
						);
						foreach ( $field_types as $ft ) :
						?>
						<span class="pf-chip">
							<span class="pf-chip-icon dashicons dashicons-<?php echo esc_attr( $ft[2] ); ?>" style="color:<?php echo esc_attr( $ft[1] ); ?>"></span>
							<?php echo esc_html( $ft[0] ); ?>
						</span>
						<?php endforeach; ?>
					</div>

					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Field type quick reference</span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['mf_fields'] ); ?></pre>
					</div>

					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Conditional logic <span class="pf-cc-tag">show / hide fields</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<div class="pf-cc-desc">Use the <code style="font-family:monospace;font-size:12px">conditional</code> key to show or hide a field based on another field's value.</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['mf_conditional'] ); ?></pre>
					</div>
				</section>

				<!-- Section: Fetch Data -->
				<section class="pf-section" id="mf-fetch">
					<div class="pf-section-hd">
						<div class="pf-section-ico">📤</div>
						<h2>Fetch a Field Value</h2>
					</div>
					<p class="pf-section-desc">Use <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">tpmeta_field()</code> to read any meta value for the current or a specific post.</p>
					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">tpmeta_field() <span class="pf-cc-tag green">theme / template</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['mf_fetch'] ); ?></pre>
					</div>
				</section>

				<!-- Section: Image & Gallery -->
				<section class="pf-section" id="mf-image">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🖼️</div>
						<h2>Image &amp; Gallery</h2>
					</div>
					<div class="pf-code-grid">
						<div class="pf-code-card">
							<div class="pf-cc-head">
								<span class="pf-cc-title">tpmeta_image_field() <span class="pf-cc-tag">single image</span></span>
								<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
							</div>
							<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['mf_image'] ); ?></pre>
						</div>
						<div class="pf-code-card">
							<div class="pf-cc-head">
								<span class="pf-cc-title">tpmeta_gallery_field() <span class="pf-cc-tag">multiple images</span></span>
								<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
							</div>
							<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['mf_gallery'] ); ?></pre>
						</div>
					</div>
					<div class="pf-tip">
						<span class="pf-tip-icon">💡</span>
						<span>Both helpers accept any registered WordPress image size as the second argument: <code style="font-family:monospace;font-size:12px">'thumbnail'</code>, <code style="font-family:monospace;font-size:12px">'medium'</code>, <code style="font-family:monospace;font-size:12px">'large'</code>, <code style="font-family:monospace;font-size:12px">'full'</code>, or a custom size slug.</span>
					</div>
				</section>

				<!-- Section: Editor (Rich Text) -->
				<section class="pf-section" id="mf-editor">
					<div class="pf-section-hd">
						<div class="pf-section-ico">📝</div>
						<h2>Editor (Rich Text) Data</h2>
					</div>
					<p class="pf-section-desc">Editor field values are stored as <strong>HTML</strong>, sanitized at save time with <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">wp_kses_post()</code>. Render them with the <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">the_content</code> filter so paragraphs and shortcodes work.</p>
					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Editor field <span class="pf-cc-tag green">retrieve &amp; render</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['mf_editor'] ); ?></pre>
					</div>
					<div class="pf-tip">
						<span class="pf-tip-icon">💡</span>
						<span><code style="font-family:monospace;font-size:12px">tpmeta_field( $key )</code> with no 2nd argument reads <code style="font-family:monospace;font-size:12px">$post-&gt;ID</code> from the global <code style="font-family:monospace;font-size:12px">$post</code>. That global is set by The Loop, so calling it at the top of <code style="font-family:monospace;font-size:12px">functions.php</code> returns <code style="font-family:monospace;font-size:12px">false</code>. Inside <code style="font-family:monospace;font-size:12px">single.php</code> / <code style="font-family:monospace;font-size:12px">page.php</code> it just works; everywhere else, pass <code style="font-family:monospace;font-size:12px">get_queried_object_id()</code> as the second argument.</span>
					</div>
				</section>

				<!-- Section: Repeater -->
				<section class="pf-section" id="mf-repeater">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🔁</div>
						<h2>Repeater Data</h2>
					</div>
					<p class="pf-section-desc"><code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">tpmeta_field()</code> returns an array of row arrays for repeater fields. Each row is keyed by the sub-field ID.</p>
					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Iterate repeater rows <span class="pf-cc-tag violet">includes images</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['mf_repeater'] ); ?></pre>
					</div>
				</section>

			</div><!-- /tab metafields -->


			<!-- ════════════════════════════════════════════════════
			     TAB 2 — OPTIONS PANEL
			     ════════════════════════════════════════════════════ -->
			<div class="pf-panel hidden" data-tab="options">

				<!-- Flow -->
				<div class="pf-flow" id="flow-options">
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-indigo">⚙️</div>
						<div class="pf-flow-step-lbl">set_args()</div>
						<div class="pf-flow-step-sub">Register panel</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-cyan">📋</div>
						<div class="pf-flow-step-lbl">set_section()</div>
						<div class="pf-flow-step-sub">Add fields</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-green">🖥️</div>
						<div class="pf-flow-step-lbl">Admin Page</div>
						<div class="pf-flow-step-sub">Auto-created</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-amber">💾</div>
						<div class="pf-flow-step-lbl">User Saves</div>
						<div class="pf-flow-step-sub">theme_mod</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-violet">📤</div>
						<div class="pf-flow-step-lbl">Read Value</div>
						<div class="pf-flow-step-sub">tpmeta_get_option()</div>
					</div>
				</div>

				<!-- Section: Create Panel -->
				<section class="pf-section" id="opt-setup">
					<div class="pf-section-hd">
						<div class="pf-section-ico">⚙️</div>
						<h2>Create an Options Panel</h2>
					</div>
					<p class="pf-section-desc">Call <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">TPMeta_Options::set_args()</code> and <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">set_section()</code> inside an <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">init</code> action. The admin page is created automatically.</p>

					<div class="pf-tip">
						<span class="pf-tip-icon">💡</span>
						<span>Values are stored as <strong>WordPress theme_mods</strong> — readable anywhere with the standard <code style="font-family:monospace;font-size:12px">get_theme_mod()</code> function, no plugin dependency needed.</span>
					</div>

					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Full panel setup <span class="pf-cc-tag">functions.php</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['opt_setup'] ); ?></pre>
					</div>

					<div class="pf-tile-grid">
						<div class="pf-tile"><span class="pf-tile-icon">🏷️</span><div><div class="pf-tile-lbl">Hook</div><div class="pf-tile-val">init</div></div></div>
						<div class="pf-tile"><span class="pf-tile-icon">💾</span><div><div class="pf-tile-lbl">Storage</div><div class="pf-tile-val">theme_mod</div></div></div>
						<div class="pf-tile"><span class="pf-tile-icon">🔑</span><div><div class="pf-tile-lbl">Capability</div><div class="pf-tile-val">manage_options</div></div></div>
						<div class="pf-tile"><span class="pf-tile-icon">📐</span><div><div class="pf-tile-lbl">Sections</div><div class="pf-tile-val">unlimited</div></div></div>
					</div>
				</section>

				<!-- Section: Field Types -->
				<section class="pf-section" id="opt-fields">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🗂️</div>
						<h2>Options Panel Field Types</h2>
					</div>
					<p class="pf-section-desc">All the same field types as metafields, plus the <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">output</code> key for automatic CSS injection.</p>
					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">All supported types</span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['opt_fields'] ); ?></pre>
					</div>
				</section>

				<!-- Section: Read Values -->
				<section class="pf-section" id="opt-read">
					<div class="pf-section-hd">
						<div class="pf-section-ico">📤</div>
						<h2>Read Option Values</h2>
					</div>
					<div class="pf-code-grid">
						<div class="pf-code-card">
							<div class="pf-cc-head">
								<span class="pf-cc-title">tpmeta_get_option() <span class="pf-cc-tag green">recommended</span></span>
								<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
							</div>
							<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['opt_read'] ); ?></pre>
						</div>
						<div class="pf-code-card">
							<div class="pf-cc-head">
								<span class="pf-cc-title">tpmeta_get_repeater_rows() <span class="pf-cc-tag violet">repeater</span></span>
								<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
							</div>
							<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['opt_repeater'] ); ?></pre>
						</div>
					</div>
				</section>

				<!-- Section: Repeater anchor -->
				<section class="pf-section" id="opt-repeater" style="margin-bottom:0;padding-bottom:1px"></section>

			</div><!-- /tab options -->


			<!-- ════════════════════════════════════════════════════
			     TAB 3 — VISUAL BUILDER
			     ════════════════════════════════════════════════════ -->
			<div class="pf-panel hidden" data-tab="builder">

				<!-- Flow -->
				<div class="pf-flow" id="flow-builder">
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-indigo">🎨</div>
						<div class="pf-flow-step-lbl">Open Builder</div>
						<div class="pf-flow-step-sub">Options Builder</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-cyan">➕</div>
						<div class="pf-flow-step-lbl">Add Fields</div>
						<div class="pf-flow-step-sub">Drag &amp; drop</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-green">✅</div>
						<div class="pf-flow-step-lbl">Save Panel</div>
						<div class="pf-flow-step-sub">JSON stored</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-violet">🖥️</div>
						<div class="pf-flow-step-lbl">Live Page</div>
						<div class="pf-flow-step-sub">Admin menu</div>
					</div>
				</div>

				<!-- Section: Steps -->
				<section class="pf-section" id="bld-flow">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🚀</div>
						<h2>How to Use the Builder</h2>
					</div>
				</section>

				<section class="pf-section" id="bld-steps">
					<div class="pf-steps">
						<div class="pf-step">
							<div class="pf-step-num">1</div>
							<div class="pf-step-body">
								<div class="pf-step-title">Go to Pure Fields → Option Builder</div>
								<div class="pf-step-text">Click <strong>Add New Panel</strong> and give it a name, slug, icon, and menu position. These become the WordPress admin menu entry.</div>
							</div>
						</div>
						<div class="pf-step">
							<div class="pf-step-num">2</div>
							<div class="pf-step-body">
								<div class="pf-step-title">Add Sections</div>
								<div class="pf-step-text">Each panel has one or more sections (tabs on the left of the options page). Give each section a title and icon dashicon, e.g. <code>dashicons-art</code>.</div>
							</div>
						</div>
						<div class="pf-step">
							<div class="pf-step-num">3</div>
							<div class="pf-step-body">
								<div class="pf-step-title">Add Fields to Sections</div>
								<div class="pf-step-text">Drag any field type into a section row. Set the field ID, label, type, default value, and any type-specific options (choices for select, sub-fields for repeater, etc.).</div>
							</div>
						</div>
						<div class="pf-step">
							<div class="pf-step-num">4</div>
							<div class="pf-step-body">
								<div class="pf-step-title">Enable Customizer Integration (optional)</div>
								<div class="pf-step-text">Tick <strong>Customizer Integration</strong> in the panel settings. Every field will automatically appear under Appearance → Customize.</div>
							</div>
						</div>
						<div class="pf-step">
							<div class="pf-step-num">5</div>
							<div class="pf-step-body">
								<div class="pf-step-title">Save the Panel</div>
								<div class="pf-step-text">Click <strong>Save Panel</strong>. The admin page appears immediately in the sidebar — no page refresh needed. Use <code>tpmeta_get_option()</code> or <code>get_theme_mod()</code> to read the values in your theme.</div>
							</div>
						</div>
					</div>
				</section>

				<!-- Section: Bake to PHP -->
				<section class="pf-section" id="bld-bake">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🥧</div>
						<h2>Bake to PHP</h2>
					</div>
					<p class="pf-section-desc">Export any builder panel to a standalone PHP file you can commit into your theme. The exported file uses <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">TPMeta_Options::set_args()</code> / <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">set_section()</code> directly and works even if the builder is deactivated.</p>

					<div class="pf-steps">
						<div class="pf-step">
							<div class="pf-step-num">1</div>
							<div class="pf-step-body">
								<div class="pf-step-title">Open a panel in the builder</div>
								<div class="pf-step-text">Navigate to Option Builder and open the panel you want to export.</div>
							</div>
						</div>
						<div class="pf-step">
							<div class="pf-step-num">2</div>
							<div class="pf-step-body">
								<div class="pf-step-title">Click "Bake to PHP" in the toolbar</div>
								<div class="pf-step-text">A dialog opens with the generated PHP code. Click Copy.</div>
							</div>
						</div>
						<div class="pf-step">
							<div class="pf-step-num">3</div>
							<div class="pf-step-body">
								<div class="pf-step-title">Paste into your theme</div>
								<div class="pf-step-text">Drop the code into your theme's <code>functions.php</code> or a dedicated <code>options.php</code> file. The panel registers itself on <code>init</code> priority 8.</div>
							</div>
						</div>
					</div>

					<div class="pf-note">
						<span class="pf-tip-icon">ℹ️</span>
						<span>The Loader reads both JSON (builder) and PHP registrations. PHP always wins when both exist for the same <code style="font-family:monospace;font-size:12px">opt_name</code> — baked files use priority 8, the loader uses priority 15.</span>
					</div>
				</section>

			</div><!-- /tab builder -->


			<!-- ════════════════════════════════════════════════════
			     TAB 4 — CUSTOMIZER
			     ════════════════════════════════════════════════════ -->
			<div class="pf-panel hidden" data-tab="customizer">

				<!-- Flow -->
				<div class="pf-flow" id="flow-customizer">
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-indigo">🚩</div>
						<div class="pf-flow-step-lbl">Enable Flag</div>
						<div class="pf-flow-step-sub">customizer_integration: true</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-green">🖌️</div>
						<div class="pf-flow-step-lbl">Customize</div>
						<div class="pf-flow-step-sub">Panel appears</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-amber">💾</div>
						<div class="pf-flow-step-lbl">Publish</div>
						<div class="pf-flow-step-sub">theme_mod saved</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-violet">📤</div>
						<div class="pf-flow-step-lbl">Read Value</div>
						<div class="pf-flow-step-sub">get_theme_mod()</div>
					</div>
				</div>

				<!-- Section: Enable -->
				<section class="pf-section" id="cst-flow">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🚩</div>
						<h2>Enable Customizer Integration</h2>
					</div>
				</section>

				<section class="pf-section" id="cst-enable">
					<p class="pf-section-desc">One flag is all it takes. The panel and every section/field in it will automatically appear in <strong>Appearance → Customize</strong>.</p>

					<div class="pf-code-grid">
						<div class="pf-code-card">
							<div class="pf-cc-head">
								<span class="pf-cc-title">Via PHP <span class="pf-cc-tag">set_args()</span></span>
								<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
							</div>
							<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['cust_enable_php'] ); ?></pre>
						</div>
						<div class="pf-code-card">
							<div class="pf-cc-head">
								<span class="pf-cc-title">Via Visual Builder <span class="pf-cc-tag green">no code</span></span>
								<button class="pf-copy-btn" style="display:none"></button>
							</div>
							<div class="pf-cc-desc" style="padding:16px">
								<ol style="padding-left:18px;line-height:2;color:#1e293b">
									<li>Open any panel in <strong>Option Builder</strong></li>
									<li>Tick the <strong>Customizer Integration</strong> checkbox in the panel settings</li>
									<li>Click <strong>Save Panel</strong></li>
								</ol>
								<p style="margin-top:10px;font-size:12.5px;color:#64748b">The panel appears in the Customizer immediately — no page reload required.</p>
							</div>
						</div>
					</div>
				</section>

				<!-- Section: Supported field types -->
				<section class="pf-section" id="cst-fields">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🗂️</div>
						<h2>Supported Field Types</h2>
					</div>
					<p class="pf-section-desc">All field types render with full UI in the Customizer sidebar.</p>
					<div class="pf-chip-grid">
						<?php
						// [type, accent color, dashicon]
						$cust_types = array(
							array( 'colorpicker',    '#7c3aed', 'art'               ),
							array( 'select',         '#059669', 'menu-alt'          ),
							array( 'switch',         '#059669', 'controls-volumeon' ),
							array( 'checkbox',       '#059669', 'yes'               ),
							array( 'tabs',           '#059669', 'screenoptions'     ),
							array( 'datepicker',     '#d97706', 'calendar-alt'      ),
							array( 'image',          '#0891b2', 'format-image'      ),
							array( 'gallery',        '#0891b2', 'images-alt2'       ),
							array( 'editor',         '#6366f1', 'edit-page'         ),
							array( 'color_gradient', '#7c3aed', 'image-filter'      ),
							array( 'multicolor',     '#7c3aed', 'color-picker'      ),
							array( 'post_select',    '#059669', 'admin-post'        ),
							array( 'repeater',       '#be185d', 'list-view'         ),
							array( 'text',           '#6366f1', 'editor-textcolor'  ),
							array( 'textarea',       '#6366f1', 'editor-paragraph'  ),
						);
						foreach ( $cust_types as $ct ) :
						?>
						<span class="pf-chip">
							<span class="pf-chip-icon dashicons dashicons-<?php echo esc_attr( $ct[2] ); ?>" style="color:<?php echo esc_attr( $ct[1] ); ?>"></span>
							<?php echo esc_html( $ct[0] ); ?>
						</span>
						<?php endforeach; ?>
					</div>
				</section>

				<!-- Section: Read Values -->
				<section class="pf-section" id="cst-read">
					<div class="pf-section-hd">
						<div class="pf-section-ico">📤</div>
						<h2>Read Values</h2>
					</div>
					<p class="pf-section-desc">Customizer and admin-panel saves go to the same <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">theme_mod</code> storage — use either helper interchangeably.</p>
					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Reading in your theme <span class="pf-cc-tag green">same API</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['cust_read'] ); ?></pre>
					</div>
				</section>

			</div><!-- /tab customizer -->

			<!-- ════════════════════════════════════════════════════
			     TAB 5 — USER META
			     ════════════════════════════════════════════════════ -->
			<div class="pf-panel hidden" data-tab="usermeta">

				<!-- Flow -->
				<div class="pf-flow" id="flow-usermeta">
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-indigo">⚙️</div>
						<div class="pf-flow-step-lbl">Register</div>
						<div class="pf-flow-step-sub">tp_user_meta filter</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-green">👤</div>
						<div class="pf-flow-step-lbl">Profile Screen</div>
						<div class="pf-flow-step-sub">Fields render</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-amber">💾</div>
						<div class="pf-flow-step-lbl">User Saves</div>
						<div class="pf-flow-step-sub">wp_usermeta</div>
					</div>
					<div class="pf-flow-conn"><div class="pf-conn-line"></div><div class="pf-conn-head"></div></div>
					<div class="pf-flow-step">
						<div class="pf-flow-icon pf-fi-violet">📤</div>
						<div class="pf-flow-step-lbl">Read Value</div>
						<div class="pf-flow-step-sub">get_user_meta()</div>
					</div>
				</div>

				<!-- Section: Register -->
				<section class="pf-section" id="um-flow">
					<div class="pf-section-hd">
						<div class="pf-section-ico">⚙️</div>
						<h2>Register User Meta Fields</h2>
					</div>
					<p class="pf-section-desc">Hook into <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">tp_user_meta</code> and push one or more <strong>blocks</strong>. Each block renders as a titled section with its own table of fields on the <strong>Users → Profile</strong> and <strong>Edit User</strong> screens.</p>
					<div class="pf-code-card" id="um-register">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Register a user-meta block <span class="pf-cc-tag">functions.php</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['um_register'] ); ?></pre>
					</div>
					<div class="pf-tip">
						<span class="pf-tip-icon">💡</span>
						<span>Each field's <code style="font-family:monospace;font-size:12px">id</code> is used verbatim as the <code style="font-family:monospace;font-size:12px">wp_usermeta</code> key — keep it unique and avoid clashing with WordPress core keys like <code style="font-family:monospace;font-size:12px">first_name</code> or <code style="font-family:monospace;font-size:12px">description</code>.</span>
					</div>
				</section>

				<!-- Section: Field Types -->
				<section class="pf-section" id="um-field-types">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🗂️</div>
						<h2>All Field Types</h2>
					</div>
					<p class="pf-section-desc">Every <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">type</code> you can use inside a user-meta field definition.</p>

					<div class="pf-chip-grid">
						<?php
						// [type, accent color, dashicon]
						$user_field_types = array(
							array( 'text',           '#6366f1', 'editor-textcolor'  ),
							array( 'textarea',       '#6366f1', 'editor-paragraph'  ),
							array( 'editor',         '#6366f1', 'edit-page'         ),
							array( 'url',            '#6366f1', 'admin-links'       ),
							array( 'number',         '#6366f1', 'calculator'        ),
							array( 'image',          '#0891b2', 'format-image'      ),
							array( 'gallery',        '#0891b2', 'images-alt2'       ),
							array( 'switch',         '#059669', 'controls-volumeon' ),
							array( 'checkbox',       '#059669', 'yes'               ),
							array( 'select',         '#059669', 'menu-alt'          ),
							array( 'select_posts',   '#059669', 'admin-post'        ),
							array( 'datepicker',     '#d97706', 'calendar-alt'      ),
							array( 'colorpicker',    '#7c3aed', 'art'               ),
							array( 'color_gradient', '#7c3aed', 'image-filter'      ),
							array( 'multicolor',     '#7c3aed', 'color-picker'      ),
							array( 'section_heading', '#be185d', 'minus'            ),
						);
						foreach ( $user_field_types as $ft ) :
						?>
						<span class="pf-chip">
							<span class="pf-chip-icon dashicons dashicons-<?php echo esc_attr( $ft[2] ); ?>" style="color:<?php echo esc_attr( $ft[1] ); ?>"></span>
							<?php echo esc_html( $ft[0] ); ?>
						</span>
						<?php endforeach; ?>
					</div>

					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Field type quick reference</span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['um_fields'] ); ?></pre>
					</div>
				</section>

				<!-- Section: Read Values -->
				<section class="pf-section" id="um-read">
					<div class="pf-section-hd">
						<div class="pf-section-ico">📤</div>
						<h2>Read User Meta Values</h2>
					</div>
					<p class="pf-section-desc">Values save straight to the WordPress <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">wp_usermeta</code> table — read them anywhere with the standard <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">get_user_meta()</code> function, no plugin dependency required.</p>
					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">get_user_meta() <span class="pf-cc-tag green">theme / template</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['um_read'] ); ?></pre>
					</div>
				</section>

				<!-- Section: Users List Column -->
				<section class="pf-section" id="um-table">
					<div class="pf-section-hd">
						<div class="pf-section-ico">📊</div>
						<h2>Show a Field in the Users List</h2>
					</div>
					<p class="pf-section-desc">Set <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">show_in_admin_table</code> to <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">1</code> and the field value appears as a column on <strong>Users → All Users</strong>.</p>
					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Add a Users table column <span class="pf-cc-tag">show_in_admin_table</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['um_table'] ); ?></pre>
					</div>
					<div class="pf-tip">
						<span class="pf-tip-icon">💡</span>
						<span><code style="font-family:monospace;font-size:12px">image</code> fields render their thumbnail in the column automatically; all other types print the raw stored value.</span>
					</div>
				</section>

				<!-- Section: Multiple Blocks -->
				<section class="pf-section" id="um-multiblock">
					<div class="pf-section-hd">
						<div class="pf-section-ico">🧱</div>
						<h2>Multiple Blocks &amp; Capability Gating</h2>
					</div>
					<p class="pf-section-desc">Push more than one block to group fields under separate headings. Add an optional <code style="background:#f1f5f9;padding:1px 6px;border-radius:4px;font-family:monospace;color:#4338ca">capability</code> key to a block so only users with that capability can see and save it.</p>
					<div class="pf-code-card">
						<div class="pf-cc-head">
							<span class="pf-cc-title">Several blocks on one profile <span class="pf-cc-tag violet">capability gated</span></span>
							<button class="pf-copy-btn"><span class="dashicons dashicons-clipboard"></span> Copy</button>
						</div>
						<pre class="pf-pre" data-lang="php"><?php echo esc_html( $snip['um_multiblock'] ); ?></pre>
					</div>
					<div class="pf-note">
						<span class="pf-tip-icon">ℹ️</span>
						<span>A single block can also be returned in the legacy shape — an array with a top-level <code style="font-family:monospace;font-size:12px">label</code> and <code style="font-family:monospace;font-size:12px">fields</code> key — and it will still render. The array-of-blocks form shown above is recommended for new code.</span>
					</div>
				</section>

			</div><!-- /tab usermeta -->


		</main><!-- /main -->
	</div><!-- /body -->
</div><!-- /pf-help -->
