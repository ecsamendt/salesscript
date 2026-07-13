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
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
	}

	/**
	 * Registers (but does not enqueue) the front-end assets. They are only
	 * enqueued once a shortcode actually renders -- otherwise every page on the
	 * site would load the script-builder CSS/JS, including the copy-protection
	 * handlers, whether or not any of it is used there.
	 */
	public function register_frontend_assets(): void {
		wp_register_style( 'ssb-frontend', SSB_PLUGIN_URL . 'assets/css/frontend.css', array(), SSB_VERSION );
		wp_register_script( 'ssb-copy-protect', SSB_PLUGIN_URL . 'assets/js/copy-protect.js', array(), SSB_VERSION, true );
	}

	/**
	 * Pulls in everything a rendered shortcode needs. SSB_Favorites and
	 * SSB_GA4_Events register their own handles on 'wp_enqueue_scripts', which
	 * has already run by the time a shortcode renders inside the_content.
	 */
	private function enqueue_shortcode_assets(): void {
		wp_enqueue_style( 'ssb-frontend' );
		wp_enqueue_script( 'ssb-copy-protect' );
		wp_enqueue_script( 'ssb-ga4-events' );
		wp_enqueue_script( 'ssb-favorites' );
	}

	public function render_script_builder(): string {
		if ( ! SSB_Access_Control::user_has_access() ) {
			return '<p>' . esc_html__( 'An active membership is required to view sales scripts.', 'sales-script-builder' ) . '</p>';
		}

		$this->enqueue_shortcode_assets();

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

		$this->enqueue_shortcode_assets();

		ob_start();
		include SSB_PLUGIN_DIR . 'templates/favorites-dashboard.php';
		return ob_get_clean();
	}
}
