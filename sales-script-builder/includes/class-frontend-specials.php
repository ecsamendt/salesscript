<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end management for Specials/Discounts. Same dual-entry-point
 * pattern as SSB_Frontend_Editor and SSB_Frontend_Competitors -- see
 * SSB_Frontend_Editor's docblock for the full explanation, including the
 * caveat that the standalone page's Edit/Add New/Delete buttons no longer
 * respond (only [ssb_app]'s SPA tab is fully interactive now). Reuses
 * SSB_Meta_Fields::render_special_details() (already has start date, end
 * date, terms, applicable products) and save_special_fields_from_post().
 */
class SSB_Frontend_Specials {

	public function __construct() {
		add_shortcode( 'ssb_manage_specials', array( $this, 'render_manager' ) );

		add_action( 'admin_post_ssb_save_special_frontend', array( $this, 'handle_save' ) );
		add_action( 'admin_post_ssb_delete_special_frontend', array( $this, 'handle_delete' ) );

		add_action( 'wp_ajax_ssb_save_special', array( $this, 'handle_save_ajax' ) );
		add_action( 'wp_ajax_ssb_delete_special', array( $this, 'handle_delete_ajax' ) );
		add_action( 'wp_ajax_ssb_load_special_form', array( $this, 'handle_load_form_ajax' ) );
		add_action( 'wp_ajax_ssb_load_special_list', array( $this, 'handle_load_list_ajax' ) );
	}

	public static function user_can_manage_specials(): bool {
		return SSB_Access_Control::user_has_access();
	}

	/* ---------------------------------------------------------------
	 * FRAGMENT RENDERERS
	 * ------------------------------------------------------------- */

	public static function render_list_fragment(): string {
		$specials = get_posts(
			array(
				'post_type'      => 'ssb_special',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		ob_start();
		include SSB_PLUGIN_DIR . 'templates/manage-specials-list.php';
		return ob_get_clean();
	}

	public static function render_form_fragment( int $special_id = 0 ): string {
		ob_start();
		include SSB_PLUGIN_DIR . 'templates/manage-special-form.php';
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * STANDALONE PAGE
	 * ------------------------------------------------------------- */

	public function render_manager(): string {
		if ( ! self::user_can_manage_specials() ) {
			return '<p>' . esc_html__( 'An active membership is required to manage specials.', 'sales-script-builder' ) . '</p>';
		}

		wp_enqueue_style( 'ssb-admin' );
		wp_enqueue_style( 'ssb-manage' );
		wp_enqueue_script( 'ssb-admin-repeater' );

		$action     = isset( $_GET['ssb_action'] ) ? sanitize_key( wp_unslash( $_GET['ssb_action'] ) ) : '';
		$special_id = isset( $_GET['special_id'] ) ? absint( $_GET['special_id'] ) : 0;

		if ( 'new' === $action || ( 'edit' === $action && $special_id ) ) {
			return self::render_form_fragment( $special_id );
		}

		return self::render_list_fragment();
	}

	public function handle_save(): void {
		if ( ! isset( $_POST['ssb_frontend_special_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_frontend_special_nonce'] ) ), 'ssb_save_special_frontend' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'sales-script-builder' ) );
		}
		if ( ! self::user_can_manage_specials() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		$special_id = self::save_from_request();
		if ( is_wp_error( $special_id ) ) {
			wp_die( esc_html( $special_id->get_error_message() ) );
		}

		$redirect_url = add_query_arg(
			array(
				'ssb_action' => 'edit',
				'special_id' => $special_id,
				'ssb_saved'  => 1,
			),
			home_url( '/' . SSB_Settings::get_manage_specials_slug() . '/' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function handle_delete(): void {
		if ( ! isset( $_POST['ssb_frontend_delete_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_frontend_delete_nonce'] ) ), 'ssb_delete_special_frontend' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'sales-script-builder' ) );
		}
		if ( ! self::user_can_manage_specials() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		self::delete_from_request();
		wp_safe_redirect( home_url( '/' . SSB_Settings::get_manage_specials_slug() . '/' ) );
		exit;
	}

	/* ---------------------------------------------------------------
	 * SPA TAB (AJAX)
	 * ------------------------------------------------------------- */

	public function handle_load_form_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_specials() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		$special_id = isset( $_POST['special_id'] ) ? absint( $_POST['special_id'] ) : 0;
		wp_send_json_success( array( 'html' => self::render_form_fragment( $special_id ) ) );
	}

	public function handle_load_list_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_specials() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		wp_send_json_success( array( 'html' => self::render_list_fragment() ) );
	}

	public function handle_save_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_specials() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		$special_id = self::save_from_request();
		if ( is_wp_error( $special_id ) ) {
			wp_send_json_error( array( 'message' => $special_id->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'special_id' => $special_id,
				'form_html'  => self::render_form_fragment( $special_id ),
				'list_html'  => self::render_list_fragment(),
			)
		);
	}

	public function handle_delete_ajax(): void {
		check_ajax_referer( 'ssb_app_nonce', 'ssb_nonce' );
		if ( ! self::user_can_manage_specials() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'sales-script-builder' ) ), 403 );
		}

		self::delete_from_request();
		wp_send_json_success( array( 'list_html' => self::render_list_fragment() ) );
	}

	/* ---------------------------------------------------------------
	 * SHARED SAVE/DELETE LOGIC
	 * ------------------------------------------------------------- */

	private static function save_from_request() {
		$special_id = isset( $_POST['special_id'] ) ? absint( $_POST['special_id'] ) : 0;
		$title      = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$content    = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';

		if ( '' === trim( $title ) ) {
			return new WP_Error( 'ssb_missing_title', __( 'A special/discount name is required.', 'sales-script-builder' ) );
		}

		if ( $special_id ) {
			$existing = get_post( $special_id );
			if ( ! $existing || 'ssb_special' !== $existing->post_type ) {
				return new WP_Error( 'ssb_not_found', __( 'That special could not be found.', 'sales-script-builder' ) );
			}
			wp_update_post(
				array(
					'ID'           => $special_id,
					'post_title'   => $title,
					'post_content' => $content,
				)
			);
		} else {
			$special_id = wp_insert_post(
				array(
					'post_type'    => 'ssb_special',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => $content,
					'post_author'  => get_current_user_id(),
				)
			);
		}

		if ( is_wp_error( $special_id ) || ! $special_id ) {
			return new WP_Error( 'ssb_save_failed', __( 'Something went wrong saving this special. Please try again.', 'sales-script-builder' ) );
		}

		SSB_Meta_Fields::save_special_fields_from_post( $special_id );

		return $special_id;
	}

	private static function delete_from_request(): void {
		$special_id = isset( $_POST['special_id'] ) ? absint( $_POST['special_id'] ) : 0;
		$special    = $special_id ? get_post( $special_id ) : null;

		if ( $special && 'ssb_special' === $special->post_type ) {
			wp_trash_post( $special_id );
		}
	}
}
