<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the membership plugin (currently MemberPress, still
 * being evaluated as of this writing). Every other file in the plugin should
 * call SSB_Access_Control::user_has_access() rather than calling MemberPress
 * classes/functions directly -- if the membership plugin changes later, only
 * this file needs to be rewritten.
 */
class SSB_Access_Control {

	public function __construct() {
		// Re-check access on every script-view request, not just at login,
		// so access dies the moment a subscription lapses or is cancelled.
		add_action( 'template_redirect', array( $this, 'maybe_block_script_view' ) );
	}

	/**
	 * Central gate. Returns true if the current (or given) user has an
	 * active membership that grants access to the Sales Script Builder.
	 */
	public static function user_has_access( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		// --- MemberPress integration ---
		if ( class_exists( 'MeprUser' ) ) {
			$mepr_user = new MeprUser( $user_id );

			// is_active() checks for ANY active membership. If you later want
			// tier-gating (e.g. only Premium sees specials/upsell paths),
			// swap this for $mepr_user->active_product_subscriptions()
			// and check against specific membership/product IDs.
			return (bool) $mepr_user->is_active();
		}

		// --- Fallback for local dev before MemberPress is installed/active ---
		// Defaults to "logged in = access" so the plugin is testable standalone.
		// Remove or tighten this fallback once MemberPress is confirmed as the
		// permanent membership solution.
		return is_user_logged_in();
	}

	/**
	 * Optional: returns a simple membership level slug/name, for future
	 * tier-based gating (e.g. "basic" vs "premium"). Currently a stub --
	 * wire this up once tier structure is finalized in MemberPress.
	 */
	public static function get_user_membership_level( ?int $user_id = null ): ?string {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id || ! class_exists( 'MeprUser' ) ) {
			return null;
		}

		$mepr_user     = new MeprUser( $user_id );
		$subscriptions = $mepr_user->active_product_subscriptions();

		if ( empty( $subscriptions ) ) {
			return null;
		}

		// Returns the first active membership's product ID as a placeholder
		// "level" -- replace with a real slug/label once tiers are defined.
		return (string) $subscriptions[0];
	}

	/**
	 * Blocks direct access to the script-view page/template for anyone
	 * without an active membership. Redirects to the membership/join page
	 * rather than showing a blank or error page.
	 */
	public function maybe_block_script_view(): void {
		if ( ! is_page( SSB_Settings::get_slug() ) ) {
			return;
		}

		if ( self::user_has_access() ) {
			return;
		}

		wp_safe_redirect( home_url( '/membership/' ) ); // Adjust to actual membership/join page.
		exit;
	}
}

