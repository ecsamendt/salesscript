<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end management for the Competitors library. Same dual-entry-point
 * pattern as SSB_Frontend_Editor (products) -- see that file's docblock for
 * the full explanation, including the important caveat that the standalone
 * page's Edit/Add New/Delete buttons no longer respond (only [ssb_app]'s
 * SPA tab is fully interactive now).
 */
class SSB_Frontend_Competitors {

	public function __construct() {
		add_shortcode( 'ssb_manage_competitors', array( $this, 'render_manager' ) );

		add_action( 'admin_post_ssb_save_competitor_frontend', array( $this, 'handle_save' ) );
		add_action( 'admin_post_ssb_delete_competitor_frontend', array( $this, 'handle_delete' ) );

		add_action( 'wp_ajax_ssb_save_competitor', array( $this, 'handle_save_ajax' ) );
		add_action( 'wp_ajax_ssb_delete_competitor', array( $this, 'handle_delete_ajax' ) );
		add_action( 'wp_ajax_ssb_load_competitor_form', array( $this, 'handle_load_form_ajax' ) );
		add_action( 'wp_ajax_ssb_load_competitor_list', array( $this, 'handle_load_list_ajax' ) );
	}

	public static function user_can_manage_competitors(): bool {
		return SSB_Access_Control::user_has_access();
	}

	/* ---------------------------------------------------------------
	 * FRAGMENT RENDERERS
	 * ------------------------------------------------------------- */

	public static function render_list_fragment(): string {
		$competitors = get_posts(
			array(
				'post_type'      => 'ssb_competitor',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		ob_start();
		include SSB_PLUGIN_DIR . 'templates/manage-competitors-list.php';
		return ob_get_clean();
	}

	public static function render_form_fragment( int $competitor_id = 0 ): string {
		ob_start();
		include SSB_PLUGIN_DIR . 'templates/manage-competitor-form.php';
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * STANDALONE PAGE
	 * ------------------------------------------------------------- */

	public function render_manager(): string {
		if ( ! self::user_can_manage_competitors() ) {
			return '<p>' . esc_html__( 'An active membership is required to manage competitors.', 'sales-script-builder' ) . '</p>';
		}

		wp_enqueue_style( 'ssb-admin' );
		wp_enqueue_style( 'ssb-manage' );
		wp_enqueue_script( 'ssb-admin-repeater' );

		$action        = isset( $_GET['ssb_action'] ) ? sanitize_key( wp_unslash( $_GET['ssb_action'] ) ) : '';
		$competitor_id = isset( $_GET['competitor_id'] ) ? absint( $_GET['competitor_id'] ) : 0;

		if ( 'new' === $action || ( 'edit' === $action && $competitor_id ) ) {
			return self::render_form_fragment( $competitor_id );
		}

		return self::render_list_fragment();
	}

	public function handle_save(): void {
		if ( ! isset( $_POST['ssb_frontend_competitor_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_frontend_competitor_nonce'] ) ), 'ssb_save_competitor_frontend' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'sales-script-builder' ) );
		}
		if ( ! self::user_can_manage_competitors() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		$competitor_id = self::save_from_request();
		if ( is_wp_error( $competitor_id ) ) {
			wp_die( esc_html( $competitor_id->get_error_message() ) );
		}

		$redirect_url = add_query_arg(
			array(
				'ssb_action'    => 'edit',
				'competitor_id' => $competitor_id,
				'ssb_saved'     => 1,
			),
			home_url( '/' . SSB_Settings::get_manage_competitors_slug() . '/' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function handle_delete(): void {
		if ( ! isset( $_POST['ssb_frontend_delete_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_frontend_delete_nonce'] ) ), 'ssb_delete_competitor_frontend' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'sales-script-builder' ) );
		}
		if ( ! self::user_can_manage_competitors() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		self::delete_from_request();
		wp_safe_redirect( home_url( '/' . SSB_Settings::get_manage_competitors_slug() . '/' ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * SPA TAB (AJAX)
	 * ------------------------------------------------------------- */

	public function handle_load_form_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_competitors() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		$competitor_id = isset( $_POST['competitor_id'] ) ? absint( $_POST['competitor_id'] ) : 0;
		wp_send_json_success( array( 'html' => self::render_form_fragment( $competitor_id ) ) );
	}

	public function handle_load_list_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_competitors() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		wp_send_json_success( array( 'html' => self::render_list_fragment() ) );
	}

	public function handle_save_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_competitors() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		$competitor_id = self::save_from_request();
		if ( is_wp_error( $competitor_id ) ) {
			wp_send_json_error( array( 'message' => $competitor_id->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'competitor_id' => $competitor_id,
				'form_html'     => self::render_form_fragment( $competitor_id ),
				'list_html'     => self::render_list_fragment(),
			)
		);
	}

	public function handle_delete_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_competitors() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		self::delete_from_request();
		wp_send_json_success( array( 'list_html' => self::render_list_fragment() ) );
	}

	/* ---------------------------------------------------------------
	 * SHARED SAVE/DELETE LOGIC
	 * ------------------------------------------------------------- */

	private static function save_from_request() {
		$competitor_id = isset( $_POST['competitor_id'] ) ? absint( $_POST['competitor_id'] ) : 0;
		$title         = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';

		if ( '' === trim( $title ) ) {
			return new WP_Error( 'ssb_missing_title', __( 'A competitor name is required.', 'sales-script-builder' ) );
		}

		if ( $competitor_id ) {
			$existing = get_post( $competitor_id );
			if ( ! $existing || 'ssb_competitor' !== $existing->post_type ) {
				return new WP_Error( 'ssb_not_found', __( 'That competitor could not be found.', 'sales-script-builder' ) );
			}
			wp_update_post( array( 'ID' => $competitor_id, 'post_title' => $title ) );
		} else {
			$competitor_id = wp_insert_post(
				array(
					'post_type'   => 'ssb_competitor',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_author' => get_current_user_id(),
				)
			);
		}

		if ( is_wp_error( $competitor_id ) || ! $competitor_id ) {
			return new WP_Error( 'ssb_save_failed', __( 'Something went wrong saving this competitor. Please try again.', 'sales-script-builder' ) );
		}

		SSB_Competitors::save_competitor_fields_from_post( $competitor_id );

		return $competitor_id;
	}

	private static function delete_from_request(): void {
		$competitor_id = isset( $_POST['competitor_id'] ) ? absint( $_POST['competitor_id'] ) : 0;
		$competitor    = $competitor_id ? get_post( $competitor_id ) : null;

		if ( $competitor && 'ssb_competitor' === $competitor->post_type ) {
			wp_trash_post( $competitor_id );
		}
	}
}
