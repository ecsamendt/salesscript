<?php
/**
 * Plugin Name:       Sales Script Builder
 * Plugin URI:         https://example.com/sales-script-builder
 * Description:        Stores products/services, pain points, competitor comparisons, objection handling, and upsell paths, then assembles them into live call scripts (cold call, inbound, upsell) for members.
 * Version:            0.1.0
 * Requires at least:  6.0
 * Requires PHP:       8.0
 * Author:             Your Company
 * Text Domain:        sales-script-builder
 * Domain Path:        /languages
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSB_VERSION', '0.1.0' );
define( 'SSB_PLUGIN_FILE', __FILE__ );
define( 'SSB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload plugin classes on demand.
 * Expects files named class-{lowercase-class-name-with-dashes}.php inside /includes.
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( strpos( $class_name, 'SSB_' ) !== 0 ) {
			return;
		}

		$file_slug = strtolower( str_replace( '_', '-', substr( $class_name, 4 ) ) );
		$file_path = SSB_PLUGIN_DIR . 'includes/class-' . $file_slug . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);

/**
 * Core plugin class. Wires everything together on 'plugins_loaded'.
 */
final class Sales_Script_Builder {

	private static ?Sales_Script_Builder $instance = null;

	public static function instance(): Sales_Script_Builder {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Loads translation files. Works alongside WPML/Polylang string translation,
	 * but keeps a standard .mo/.po fallback so the plugin degrades gracefully
	 * if a translation plugin is ever swapped out.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'sales-script-builder', false, dirname( plugin_basename( SSB_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Boots the pieces of the plugin. Each class is responsible for its own hooks
	 * (registered in its own constructor), so this just needs to instantiate them.
	 */
	public function init(): void {
		new SSB_Post_Types();
		new SSB_Meta_Fields();
		new SSB_Favorites();
		new SSB_GA4_Events();
		new SSB_Access_Control();
		new SSB_Shortcodes();
		new SSB_Settings();
	}
}

Sales_Script_Builder::instance();

/**
 * Activation: flush rewrite rules so the new CPTs get working permalinks immediately.
 */
function ssb_activate_plugin() {
	// Ensure post types are registered before flushing.
	( new SSB_Post_Types() )->register_post_types();
	( new SSB_Post_Types() )->register_taxonomies();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ssb_activate_plugin' );

/**
 * Deactivation: flush rewrite rules again to clean up.
 */
function ssb_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ssb_deactivate_plugin' );

