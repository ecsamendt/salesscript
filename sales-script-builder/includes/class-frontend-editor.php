<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end product management, separate from wp-admin entirely. Members
 * hit the [ssb_manage_products] shortcode to list, create, and edit products
 * -- reusing the exact same field-rendering (SSB_Meta_Fields::render_*, all
 * static) and field-saving (SSB_Meta_Fields::save_product_fields_from_post())
 * logic that wp-admin uses, so there is exactly one place sanitization rules
 * live regardless of which surface the save came from.
 *
 * SCOPE FOR THIS PASS: products only. Competitors library and Specials
 * front-end management are intentionally not included yet -- see the
 * README's open items.
 *
 * ACCESS: currently, any member with SSB_Access_Control::user_has_access()
 * can create/edit ANY product -- there is no per-team or per-owner
 * restriction yet, matching the decision to keep this open for now. Every
 * product created here still gets post_author set to the creating user, so
 * ownership-based filtering is a straightforward addition later without a
 * data migration. user_can_manage_products() below is the single place to
 * tighten this to a dedicated capability/role once that's needed.
 */
class SSB_Frontend_Editor {

	public function __construct() {
		add_shortcode( 'ssb_manage_products', array( $this, 'render_manager' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_post_ssb_save_product_frontend', array( $this, 'handle_save' ) );
		add_action( 'admin_post_ssb_delete_product_frontend', array( $this, 'handle_delete' ) );
	}

	public function register_assets(): void {
		// Reuses the same repeater JS/CSS as wp-admin -- it's plain vanilla JS
		// operating on class names, nothing wp-admin-specific about it.
		wp_register_style( 'ssb-admin', SSB_PLUGIN_URL . 'assets/css/admin.css', array(), SSB_VERSION );
		wp_register_script( 'ssb-admin-repeater', SSB_PLUGIN_URL . 'assets/js/admin-repeater.js', array(), SSB_VERSION, true );
		wp_register_style( 'ssb-manage', SSB_PLUGIN_URL . 'assets/css/manage.css', array(), SSB_VERSION );
	}

	/**
	 * THE place to restrict product management later -- e.g. to a custom
	 * capability or a specific role -- without touching the rest of this
	 * file. Currently just mirrors script-view access.
	 */
	public static function user_can_manage_products(): bool {
		return SSB_Access_Control::user_has_access();
	}

	public function render_manager(): string {
		if ( ! self::user_can_manage_products() ) {
			return '<p>' . esc_html__( 'An active membership is required to manage products.', 'sales-script-builder' ) . '</p>';
		}

		wp_enqueue_style( 'ssb-admin' );
		wp_enqueue_style( 'ssb-manage' );
		wp_enqueue_script( 'ssb-admin-repeater' );

		$action     = isset( $_GET['ssb_action'] ) ? sanitize_key( wp_unslash( $_GET['ssb_action'] ) ) : '';
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

		ob_start();

		if ( 'new' === $action || ( 'edit' === $action && $product_id ) ) {
			include SSB_PLUGIN_DIR . 'templates/manage-product-form.php';
		} else {
			include SSB_PLUGIN_DIR . 'templates/manage-products-list.php';
		}

		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * SAVE
	 * ------------------------------------------------------------- */

	public function handle_save(): void {
		if ( ! isset( $_POST['ssb_frontend_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_frontend_product_nonce'] ) ), 'ssb_save_product_frontend' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'sales-script-builder' ) );
		}

		if ( ! self::user_can_manage_products() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$title      = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$category   = isset( $_POST['ssb_category'] ) ? absint( $_POST['ssb_category'] ) : 0;

		if ( '' === trim( $title ) ) {
			wp_die( esc_html__( 'A product name is required.', 'sales-script-builder' ) );
		}

		if ( $product_id ) {
			// Editing an existing product -- confirm it's actually one of ours
			// before touching it, in case a stale/tampered ID was submitted.
			$existing = get_post( $product_id );
			if ( ! $existing || 'ssb_product' !== $existing->post_type ) {
				wp_die( esc_html__( 'That product could not be found.', 'sales-script-builder' ) );
			}
			wp_update_post(
				array(
					'ID'         => $product_id,
					'post_title' => $title,
				)
			);
		} else {
			$product_id = wp_insert_post(
				array(
					'post_type'   => 'ssb_product',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_author' => get_current_user_id(),
				)
			);
		}

		if ( is_wp_error( $product_id ) || ! $product_id ) {
			wp_die( esc_html__( 'Something went wrong saving this product. Please try again.', 'sales-script-builder' ) );
		}

		if ( $category ) {
			wp_set_post_terms( $product_id, array( $category ), 'ssb_category' );
		}

		// Same sanitization/save logic wp-admin uses -- see
		// SSB_Meta_Fields::save_product_fields_from_post().
		SSB_Meta_Fields::save_product_fields_from_post( $product_id );

		$redirect_url = add_query_arg(
			array(
				'ssb_action' => 'edit',
				'product_id' => $product_id,
				'ssb_saved'  => 1,
			),
			home_url( '/' . SSB_Settings::get_manage_slug() . '/' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/* ---------------------------------------------------------------
	 * DELETE
	 * ------------------------------------------------------------- */

	public function handle_delete(): void {
		if ( ! isset( $_POST['ssb_frontend_delete_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_frontend_delete_nonce'] ) ), 'ssb_delete_product_frontend' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'sales-script-builder' ) );
		}

		if ( ! self::user_can_manage_products() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? get_post( $product_id ) : null;

		if ( $product && 'ssb_product' === $product->post_type ) {
			wp_trash_post( $product_id ); // Soft delete -- recoverable from wp-admin if needed.
		}

		wp_safe_redirect( home_url( '/' . SSB_Settings::get_manage_slug() . '/' ) );
		exit;
	}
}
