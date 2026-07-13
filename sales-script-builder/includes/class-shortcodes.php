<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the [ssb_script_builder] shortcode -- the main rep-facing view.
 * Access is gated by SSB_Access_Control; specials are auto-injected by
 * SSB_Meta_Fields::get_active_specials().
 */
class SSB_Shortcodes {

	public function __construct() {
		add_shortcode( 'ssb_script_builder', array( $this, 'render_script_builder' ) );
		add_shortcode( 'ssb_favorites', array( $this, 'render_favorites_dashboard' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function enqueue_frontend_assets(): void {
		wp_enqueue_style( 'ssb-frontend', SSB_PLUGIN_URL . 'assets/css/frontend.css', array(), SSB_VERSION );
		wp_enqueue_script( 'ssb-copy-protect', SSB_PLUGIN_URL . 'assets/js/copy-protect.js', array(), SSB_VERSION, true );
	}

	public function render_script_builder(): string {
		if ( ! SSB_Access_Control::user_has_access() ) {
			return '<p>' . esc_html__( 'An active membership is required to view sales scripts.', 'sales-script-builder' ) . '</p>';
		}

		ob_start();
		include SSB_PLUGIN_DIR . 'templates/script-view.php';
		return ob_get_clean();
	}

	/**
	 * Renders [ssb_favorites] -- a "My Scripts" dashboard listing everything
	 * the current user has starred, each linking straight into the script
	 * view with product_id/call_type pre-filled.
	 */
	public function render_favorites_dashboard(): string {
		if ( ! SSB_Access_Control::user_has_access() ) {
			return '<p>' . esc_html__( 'An active membership is required to view favorites.', 'sales-script-builder' ) . '</p>';
		}

		ob_start();
		include SSB_PLUGIN_DIR . 'templates/favorites-dashboard.php';
		return ob_get_clean();
	}
}
