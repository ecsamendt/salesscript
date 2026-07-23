<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end product management. Two front doors call into this class:
 *   1. [ssb_manage_products] -- the original standalone page. STILL RENDERS
 *      (list/form display work), but Edit/Add New/Delete buttons no longer
 *      respond: the shared templates were converted to data-attribute
 *      buttons wired up by assets/js/app.js, which only initializes inside
 *      the [ssb_app] container (#ssb-app). This page is effectively
 *      superseded -- see the README's migration note. The admin-post.php
 *      handlers below (handle_save/handle_delete) are consequently dead
 *      code now (nothing submits to them anymore) but are left in place
 *      rather than removed, in case a page reachable only via that flow
 *      still exists somewhere.
 *   2. [ssb_app] (class-app.php) -- the single-page-app tab, and the
 *      actually-functional entry point going forward. Uses
 *      render_list_fragment()/render_form_fragment() below, with
 *      saves/deletes via AJAX (handle_save_ajax/handle_delete_ajax) so
 *      nothing reloads.
 *
 * Both paths funnel through SSB_Meta_Fields::save_product_fields_from_post()
 * for the actual field-saving logic, so there is exactly one place
 * sanitization rules live regardless of entry point.
 *
 * ACCESS: currently, any member with SSB_Access_Control::user_has_access()
 * can create/edit ANY product -- there is no per-team or per-owner
 * restriction yet. Every product created here still gets post_author set to
 * the creating user, so ownership-based filtering is a straightforward
 * addition later without a data migration. user_can_manage_products() below
 * is the single place to tighten this to a dedicated capability/role once
 * that's needed.
 */
class SSB_Frontend_Editor {

	public function __construct() {
		add_shortcode( 'ssb_manage_products', array( $this, 'render_manager' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Standalone page (page reload + redirect).
		add_action( 'admin_post_ssb_save_product_frontend', array( $this, 'handle_save' ) );
		add_action( 'admin_post_ssb_delete_product_frontend', array( $this, 'handle_delete' ) );

		// SPA tab (AJAX, no reload).
		add_action( 'wp_ajax_ssb_save_product', array( $this, 'handle_save_ajax' ) );
		add_action( 'wp_ajax_ssb_delete_product', array( $this, 'handle_delete_ajax' ) );
		add_action( 'wp_ajax_ssb_load_product_form', array( $this, 'handle_load_form_ajax' ) );
		add_action( 'wp_ajax_ssb_load_product_list', array( $this, 'handle_load_list_ajax' ) );
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
	 * file.
	 */
	public static function user_can_manage_products(): bool {
		return SSB_Access_Control::user_has_access();
	}

	/* ---------------------------------------------------------------
	 * FRAGMENT RENDERERS -- shared by the standalone page and the SPA tab
	 * ------------------------------------------------------------- */

	public static function render_list_fragment(): string {
		$products = get_posts(
			array(
				'post_type'      => 'ssb_product',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		ob_start();
		include SSB_PLUGIN_DIR . 'templates/manage-products-list.php';
		return ob_get_clean();
	}

	public static function render_form_fragment( int $product_id = 0 ): string {
		ob_start();
		include SSB_PLUGIN_DIR . 'templates/manage-product-form.php';
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * STANDALONE PAGE ([ssb_manage_products]) -- $_GET driven, unchanged
	 * ------------------------------------------------------------- */

	public function render_manager(): string {
		if ( ! self::user_can_manage_products() ) {
			return '<p>' . esc_html__( 'An active membership is required to manage products.', 'sales-script-builder' ) . '</p>';
		}

		wp_enqueue_style( 'ssb-admin' );
		wp_enqueue_style( 'ssb-manage' );
		wp_enqueue_script( 'ssb-admin-repeater' );

		$action     = isset( $_GET['ssb_action'] ) ? sanitize_key( wp_unslash( $_GET['ssb_action'] ) ) : '';
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

		if ( 'new' === $action || ( 'edit' === $action && $product_id ) ) {
			return self::render_form_fragment( $product_id );
		}

		return self::render_list_fragment();
	}

	public function handle_save(): void {
		if ( ! isset( $_POST['ssb_frontend_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_frontend_product_nonce'] ) ), 'ssb_save_product_frontend' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'sales-script-builder' ) );
		}
		if ( ! self::user_can_manage_products() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		$product_id = self::save_from_request();
		if ( is_wp_error( $product_id ) ) {
			wp_die( esc_html( $product_id->get_error_message() ) );
		}

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

	public function handle_delete(): void {
		if ( ! isset( $_POST['ssb_frontend_delete_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_frontend_delete_nonce'] ) ), 'ssb_delete_product_frontend' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'sales-script-builder' ) );
		}
		if ( ! self::user_can_manage_products() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		self::delete_from_request();
		wp_safe_redirect( home_url( '/' . SSB_Settings::get_manage_slug() . '/' ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * SPA TAB ([ssb_app]) -- AJAX, no reload
	 * ------------------------------------------------------------- */

	public function handle_load_form_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_products() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		wp_send_json_success( array( 'html' => self::render_form_fragment( $product_id ) ) );
	}

	public function handle_load_list_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_products() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		wp_send_json_success( array( 'html' => self::render_list_fragment() ) );
	}

	public function handle_save_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_products() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		$product_id = self::save_from_request();
		if ( is_wp_error( $product_id ) ) {
			wp_send_json_error( array( 'message' => $product_id->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'product_id' => $product_id,
				'form_html'  => self::render_form_fragment( $product_id ),
				'list_html'  => self::render_list_fragment(),
			)
		);
	}

	public function handle_delete_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_products() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		self::delete_from_request();
		wp_send_json_success( array( 'list_html' => self::render_list_fragment() ) );
	}

	/* ---------------------------------------------------------------
	 * SHARED SAVE/DELETE LOGIC -- reads $_POST, used by both entry points
	 * ------------------------------------------------------------- */

	/**
	 * @return int|WP_Error Saved product ID, or a WP_Error with a
	 *                       user-facing message on failure.
	 */
	private static function save_from_request() {
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$title      = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$category   = isset( $_POST['ssb_category'] ) ? absint( $_POST['ssb_category'] ) : 0;

		if ( '' === trim( $title ) ) {
			return new WP_Error( 'ssb_missing_title', __( 'A product name is required.', 'sales-script-builder' ) );
		}

		if ( $product_id ) {
			$existing = get_post( $product_id );
			if ( ! $existing || 'ssb_product' !== $existing->post_type ) {
				return new WP_Error( 'ssb_not_found', __( 'That product could not be found.', 'sales-script-builder' ) );
			}
			wp_update_post( array( 'ID' => $product_id, 'post_title' => $title ) );
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
			return new WP_Error( 'ssb_save_failed', __( 'Something went wrong saving this product. Please try again.', 'sales-script-builder' ) );
		}

		if ( $category ) {
			wp_set_post_terms( $product_id, array( $category ), 'ssb_category' );
		}

		SSB_Meta_Fields::save_product_fields_from_post( $product_id );

		return $product_id;
	}

	private static function delete_from_request(): void {
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? get_post( $product_id ) : null;

		if ( $product && 'ssb_product' === $product->post_type ) {
			wp_trash_post( $product_id );
		}
	}
}
