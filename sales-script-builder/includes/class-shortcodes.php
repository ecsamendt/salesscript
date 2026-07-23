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
		add_action( 'wp_ajax_ssb_load_script_output', array( $this, 'handle_load_script_output_ajax' ) );
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
		wp_register_script( 'ssb-objection-buttons', SSB_PLUGIN_URL . 'assets/js/objection-buttons.js', array(), SSB_VERSION, true );
		wp_register_script( 'ssb-discovery', SSB_PLUGIN_URL . 'assets/js/discovery.js', array(), SSB_VERSION, true );
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
		wp_enqueue_script( 'ssb-objection-buttons' );
		wp_enqueue_script( 'ssb-discovery' );
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

	/**
	 * AJAX endpoint for the Call Script tab in [ssb_app] -- given a product
	 * ID and call type, returns the assembled script HTML. See
	 * templates/app-script-output.php.
	 */
	public function handle_load_script_output_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );

		if ( ! SSB_Access_Control::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$call_type  = isset( $_POST['call_type'] ) ? sanitize_key( wp_unslash( $_POST['call_type'] ) ) : '';

		ob_start();
		include SSB_PLUGIN_DIR . 'templates/app-script-output.php';
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
