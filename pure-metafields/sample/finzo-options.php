<?php
/**
 * Sample ThemePure Options Panel
 *
 * Drop this file into your theme (or include from functions.php) to see
 * the framework in action. Demonstrates the full developer API.
 *
 * Usage:
 *   require_once get_template_directory() . '/inc/finzo-options.php';
 *
 * Reading values from the theme:
 *   $primary = tpmeta_get_option( 'finzo_primary_color', '#3362FF' );
 *   // OR (equivalent):
 *   $primary = get_theme_mod( 'finzo_primary_color', '#3362FF' );
 *
 * @package tpmeta-sample
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'TPMeta_Options' ) ) {
	return; // Framework not active.
}

add_action( 'init', function () {

	$opt_name = 'finzo_options';

	/**
	 * 1. Register the panel.
	 */
	TPMeta_Options::set_args( $opt_name, array(
		'menu_title'    => __( 'Finzo Options', 'finzo' ),
		'page_title'    => __( 'Finzo Theme Options', 'finzo' ),
		'menu_slug'     => 'finzo-options',
		'menu_icon'     => 'dashicons-admin-customizer',
		'menu_position' => 60,
		'capability'    => 'manage_options',
		'output_css'    => true,
	) );

	/**
	 * 2. Register sections.
	 */
	TPMeta_Options::set_section( $opt_name, array(
		'id'          => 'general',
		'title'       => __( 'General', 'finzo' ),
		'icon'        => 'dashicons-admin-generic',
		'description' => __( 'Site-wide branding settings.', 'finzo' ),
		'fields'      => array(
			array(
				'id'          => 'finzo_logo',
				'type'        => 'image',
				'label'       => __( 'Site Logo', 'finzo' ),
				'description' => __( 'Recommended size: 200×60 px.', 'finzo' ),
			),
			array(
				'id'      => 'finzo_tagline',
				'type'    => 'text',
				'label'   => __( 'Site Tagline', 'finzo' ),
				'default' => __( 'Smart finance, made simple.', 'finzo' ),
			),
			array(
				'id'    => 'finzo_footer_text',
				'type'  => 'textarea',
				'label' => __( 'Footer Copyright Text', 'finzo' ),
				'rows'  => 3,
			),
		),
	) );

	TPMeta_Options::set_section( $opt_name, array(
		'id'     => 'colors',
		'title'  => __( 'Colors', 'finzo' ),
		'icon'   => 'dashicons-art',
		'fields' => array(
			array(
				'id'      => 'finzo_primary_color',
				'type'    => 'colorpicker',
				'label'   => __( 'Primary Color', 'finzo' ),
				'default' => '#3362FF',
				'output'  => array(
					'a, .finzo-btn-primary' => 'color',
					'.finzo-bg-primary'     => 'background-color',
				),
			),
			array(
				'id'      => 'finzo_text_color',
				'type'    => 'colorpicker',
				'label'   => __( 'Body Text Color', 'finzo' ),
				'default' => '#010F1C',
				'output'  => array(
					'body' => 'color',
				),
			),
		),
	) );

	TPMeta_Options::set_section( $opt_name, array(
		'id'     => 'features',
		'title'  => __( 'Features', 'finzo' ),
		'icon'   => 'dashicons-yes-alt',
		'fields' => array(
			array(
				'id'      => 'finzo_enable_dark_mode',
				'type'    => 'switch',
				'label'   => __( 'Enable Dark Mode Toggle', 'finzo' ),
				'default' => 'off',
			),
			array(
				'id'      => 'finzo_layout',
				'type'    => 'tabs',
				'label'   => __( 'Default Layout', 'finzo' ),
				'default' => 'wide',
				'choices' => array(
					'wide'   => __( 'Wide', 'finzo' ),
					'boxed'  => __( 'Boxed', 'finzo' ),
					'fluid'  => __( 'Fluid', 'finzo' ),
				),
			),
			array(
				'id'       => 'finzo_blog_layout',
				'type'     => 'select',
				'label'    => __( 'Blog Listing Style', 'finzo' ),
				'default'  => 'grid',
				'options'  => array(
					'grid'    => __( 'Grid', 'finzo' ),
					'list'    => __( 'List', 'finzo' ),
					'masonry' => __( 'Masonry', 'finzo' ),
				),
			),
		),
	) );

	TPMeta_Options::set_section( $opt_name, array(
		'id'     => 'social',
		'title'  => __( 'Social Profiles', 'finzo' ),
		'icon'   => 'dashicons-share',
		'fields' => array(
			array( 'id' => 'finzo_social_facebook',  'type' => 'text', 'label' => 'Facebook URL'  ),
			array( 'id' => 'finzo_social_twitter',   'type' => 'text', 'label' => 'Twitter URL'   ),
			array( 'id' => 'finzo_social_instagram', 'type' => 'text', 'label' => 'Instagram URL' ),
			array( 'id' => 'finzo_social_linkedin',  'type' => 'text', 'label' => 'LinkedIn URL'  ),
		),
	) );

}, 5 );
