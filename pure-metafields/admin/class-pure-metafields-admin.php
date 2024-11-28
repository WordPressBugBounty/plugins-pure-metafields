<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://themepure.net
 * @since      1.3.1
 *
 * @package    tpmeta
 * @subpackage tpmeta/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    tpmeta
 * @subpackage tpmeta/admin
 * @author     ThemePure <basictheme400@gmail.com>
 */
class tpmeta_admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.3.1
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.3.1
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.3.1
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.3.1
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in tpmeta_loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The tpmeta_loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pure-metafields-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.3.1
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in tpmeta_loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The tpmeta_loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pure-metafields-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.3.1
	 */
	public function enqueue_editor_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in tpmeta_loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The tpmeta_loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		wp_enqueue_script( 'pure-metafields-admin-editor', plugin_dir_url( __FILE__ ) . 'js/pure-metafields-admin-editor.js', array('wp-element', 'wp-data', 'wp-hooks', 'jquery'), $this->version, false );

	}

	public function tp_metaboxes(){
		
	}

	/**
	 * Menus
	 */
	public function custom_plugin_menu() {
		// Main menu item
		add_menu_page(
			__('Pure MetaFields', 'pure-metafields'), // Page title
			__('Pure MetaFields', 'pure-metafields'),       // Menu title
			'manage_options',                    // Capability
			$this->plugin_name,        // Menu slug
			'',        // Callback function
			'dashicons-admin-generic',           // Icon URL
			6                                    // Position
		);
	
		// Submenu item 1
		add_submenu_page(
			$this->plugin_name,        // Parent slug
			__('All Field Groups', 'pure-metafields'), // Page title
			__('All Field Groups', 'pure-metafields'),       // Menu title
			'manage_options',                    // Capability
			'my_custom_plugin_submenu1',         // Menu slug
			''     // Callback function
		);
	
		// Submenu item 2
		add_submenu_page(
			$this->plugin_name,        // Parent slug
			__('Add New', 'pure-metafields'), // Page title
			__('Add New', 'pure-metafields'),       // Menu title
			'manage_options',                    // Capability
			'my_custom_plugin_submenu2',         // Menu slug
			''     // Callback function
		);
	}

}
