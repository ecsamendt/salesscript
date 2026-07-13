<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires GA4 events for script/product/call-type selection so usage patterns
 * can inform which fields matter most over time.
 *
 * IMPORTANT: Per product decision, tracking is fully anonymous/aggregate --
 * no user_id, email, or username is ever passed into these events. Only
 * product_id, category, and call_type are sent as event parameters.
 *
 * Assumes gtag.js (GA4) is already loaded on the site (e.g. via a separate
 * analytics plugin or theme). This class only queues the gtag('event', ...)
 * calls; it does not load the GA4 base snippet itself, to avoid double-firing
 * pageviews if GA4 is already installed elsewhere.
 */
class SSB_GA4_Events {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
	}

	/**
	 * Only needed on pages where the script builder shortcode renders, so this
	 * registers the handle and SSB_Shortcodes enqueues it on demand.
	 */
	public function register_frontend_assets(): void {
		wp_register_script( 'ssb-ga4-events', SSB_PLUGIN_URL . 'assets/js/ga4-events.js', array(), SSB_VERSION, true );
	}
}
