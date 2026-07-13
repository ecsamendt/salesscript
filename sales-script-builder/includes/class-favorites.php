<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles favoriting a script view (product + call type combination) so reps
 * can quickly get back to the ones they use most. Stored as user meta --
 * no separate table needed at this scale.
 */
class SSB_Favorites {

	const META_KEY = 'ssb_favorite_scripts';

	public function __construct() {
		add_action( 'wp_ajax_ssb_toggle_favorite', array( $this, 'ajax_toggle_favorite' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function enqueue_frontend_assets(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		wp_enqueue_script( 'ssb-favorites', SSB_PLUGIN_URL . 'assets/js/favorites.js', array( 'jquery' ), SSB_VERSION, true );
		wp_localize_script(
			'ssb-favorites',
			'ssbFavorites',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ssb_favorites_nonce' ),
			)
		);
	}

	/**
	 * A "favorite key" combines product ID + call type, e.g. "42_upsell",
	 * since the same product can be favorited differently per scenario.
	 */
	private function build_key( int $product_id, string $call_type ): string {
		return $product_id . '_' . sanitize_key( $call_type );
	}

	public function ajax_toggle_favorite(): void {
		check_ajax_referer( 'ssb_favorites_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'sales-script-builder' ) ), 401 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$call_type  = isset( $_POST['call_type'] ) ? sanitize_key( wp_unslash( $_POST['call_type'] ) ) : '';

		if ( ! $product_id || ! $call_type ) {
			wp_send_json_error( array( 'message' => __( 'Missing product or call type.', 'sales-script-builder' ) ), 400 );
		}

		$user_id   = get_current_user_id();
		$key       = $this->build_key( $product_id, $call_type );
		$favorites = get_user_meta( $user_id, self::META_KEY, true );
		$favorites = is_array( $favorites ) ? $favorites : array();

		$is_now_favorited = ! in_array( $key, $favorites, true );

		if ( $is_now_favorited ) {
			$favorites[] = $key;
		} else {
			$favorites = array_diff( $favorites, array( $key ) );
		}

		update_user_meta( $user_id, self::META_KEY, array_values( $favorites ) );

		wp_send_json_success( array( 'favorited' => $is_now_favorited ) );
	}

	public static function is_favorited( int $user_id, int $product_id, string $call_type ): bool {
		$favorites = get_user_meta( $user_id, self::META_KEY, true );
		$favorites = is_array( $favorites ) ? $favorites : array();
		return in_array( $product_id . '_' . sanitize_key( $call_type ), $favorites, true );
	}

	/**
	 * Returns favorites as an array of ['product_id' => int, 'call_type' => string].
	 */
	public static function get_favorites_for_user( int $user_id ): array {
		$favorites = get_user_meta( $user_id, self::META_KEY, true );
		$favorites = is_array( $favorites ) ? $favorites : array();

		return array_map(
			function ( $key ) {
				$parts = explode( '_', $key, 2 );
				return array(
					'product_id' => (int) $parts[0],
					'call_type'  => $parts[1] ?? '',
				);
			},
			$favorites
		);
	}
}
