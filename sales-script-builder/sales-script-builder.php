<?php
/**
 * Plugin Name:       Sales Script Builder
 * Plugin URI:         https://example.com/sales-script-builder
 * Description:        Stores products/services, pain points, competitor comparisons, objection handling, and upsell paths, then assembles them into live call scripts (cold call, inbound, upsell) for members.
 * Version:            0.1.2
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

define( 'SSB_VERSION', '0.1.2' );
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
		// Must be 'plugins_loaded', not 'init': the classes below register their
		// own 'init' callbacks from their constructors, and a callback added to
		// a priority that is already executing never runs in that same pass.
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads translation files. Works alongside WPML/Polylang string translation,
	 * but keeps a standard .mo/.po fallback so the plugin degrades gracefully
	 * if a translation plugin is ever swapped out.
	 *
	 * Hooked to 'init' rather than 'plugins_loaded' -- WP 6.7+ emits a
	 * _doing_it_wrong notice for translations loaded before 'init'.
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
		new SSB_Competitors();
		new SSB_Meta_Fields();
		new SSB_Favorites();
		new SSB_GA4_Events();
		new SSB_Access_Control();
		new SSB_Shortcodes();
		new SSB_Settings();

		if ( is_admin() ) {
			new SSB_Admin_Columns();
			new SSB_Sample_Content();
		}
	}
}

Sales_Script_Builder::instance();

/**
 * Activation: flush rewrite rules so the new CPTs get working permalinks immediately.
 */
function ssb_activate_plugin() {
	// Ensure post types are registered before flushing. 'plugins_loaded' has
	// already fired by the time an activation hook runs, so the normal bootstrap
	// has not happened yet on this request -- register them directly.
	$post_types = new SSB_Post_Types();
	$post_types->register_post_types();
	$post_types->register_taxonomies();
	( new SSB_Competitors() )->register_post_type();
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

