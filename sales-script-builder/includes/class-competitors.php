<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Competitors library is separate from the per-product "Competitor
 * Comparisons" repeater in class-meta-fields.php. That repeater is a manual,
 * feature-by-feature table entered fresh per product. This library holds
 * general, reusable knowledge about a competitor -- their pros, their cons,
 * and suggested counter talking points -- entered once and referenced from
 * any product via the "Linked Competitors" field. It's also what the
 * discovery-question flow (outbound: "what are you using now?") surfaces
 * when a prospect names a competitor by name.
 */
class SSB_Competitors {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'edit_form_after_title', array( $this, 'render_nonce' ) );
		add_action( 'save_post_ssb_competitor', array( $this, 'save_meta' ) );
	}

	public function register_post_type(): void {
		register_post_type(
			'ssb_competitor',
			array(
				'label'        => __( 'Competitors', 'sales-script-builder' ),
				'labels'       => array(
					'name'          => __( 'Competitors', 'sales-script-builder' ),
					'singular_name' => __( 'Competitor', 'sales-script-builder' ),
					'add_new_item'  => __( 'Add New Competitor', 'sales-script-builder' ),
					'edit_item'     => __( 'Edit Competitor', 'sales-script-builder' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=ssb_product', // Nest under Products/Services menu.
				'supports'     => array( 'title' ),
				'has_archive'  => false,
				'rewrite'      => false,
				'show_in_rest' => true,
			)
		);
	}

	public function render_nonce( WP_Post $post ): void {
		if ( 'ssb_competitor' === $post->post_type ) {
			wp_nonce_field( 'ssb_save_competitor_meta', 'ssb_competitor_nonce' );
		}
	}

	public function register_meta_boxes(): void {
		add_meta_box( 'ssb_competitor_pros', __( 'Their Pros', 'sales-script-builder' ), array( $this, 'render_pros' ), 'ssb_competitor', 'normal', 'high' );
		add_meta_box( 'ssb_competitor_cons', __( 'Their Cons', 'sales-script-builder' ), array( $this, 'render_cons' ), 'ssb_competitor', 'normal', 'high' );
		add_meta_box( 'ssb_competitor_counters', __( 'Counter Talking Points', 'sales-script-builder' ), array( $this, 'render_counters' ), 'ssb_competitor', 'normal', 'default' );
	}

	public function render_pros( WP_Post $post ): void {
		$value = get_post_meta( $post->ID, '_ssb_competitor_pros', true );
		?>
		<p class="description"><?php esc_html_e( 'One per line. Be honest here -- reps need to know what\'s genuinely good about them to counter it credibly.', 'sales-script-builder' ); ?></p>
		<textarea class="widefat" rows="5" name="ssb_competitor_pros"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	public function render_cons( WP_Post $post ): void {
		$value = get_post_meta( $post->ID, '_ssb_competitor_cons', true );
		?>
		<p class="description"><?php esc_html_e( 'One per line. This is your ammunition.', 'sales-script-builder' ); ?></p>
		<textarea class="widefat" rows="5" name="ssb_competitor_cons"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	public function render_counters( WP_Post $post ): void {
		$value = get_post_meta( $post->ID, '_ssb_competitor_counters', true );
		?>
		<p class="description"><?php esc_html_e( 'One per line. Ready-to-say lines a rep can use the moment a prospect names this competitor.', 'sales-script-builder' ); ?></p>
		<textarea class="widefat" rows="5" name="ssb_competitor_counters"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['ssb_competitor_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_competitor_nonce'] ) ), 'ssb_save_competitor_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['ssb_competitor_pros'] ) ) {
			update_post_meta( $post_id, '_ssb_competitor_pros', sanitize_textarea_field( wp_unslash( $_POST['ssb_competitor_pros'] ) ) );
		}
		if ( isset( $_POST['ssb_competitor_cons'] ) ) {
			update_post_meta( $post_id, '_ssb_competitor_cons', sanitize_textarea_field( wp_unslash( $_POST['ssb_competitor_cons'] ) ) );
		}
		if ( isset( $_POST['ssb_competitor_counters'] ) ) {
			update_post_meta( $post_id, '_ssb_competitor_counters', sanitize_textarea_field( wp_unslash( $_POST['ssb_competitor_counters'] ) ) );
		}
	}

	/* ---------------------------------------------------------------
	 * PUBLIC READ HELPERS
	 * ------------------------------------------------------------- */

	private static function lines( int $competitor_id, string $meta_key ): array {
		$raw = get_post_meta( $competitor_id, $meta_key, true );
		if ( ! $raw ) {
			return array();
		}
		$lines = array_map( 'trim', explode( "\n", $raw ) );
		return array_values( array_filter( $lines ) );
	}

	public static function get_pros( int $competitor_id ): array {
		return self::lines( $competitor_id, '_ssb_competitor_pros' );
	}

	public static function get_cons( int $competitor_id ): array {
		return self::lines( $competitor_id, '_ssb_competitor_cons' );
	}

	public static function get_counters( int $competitor_id ): array {
		return self::lines( $competitor_id, '_ssb_competitor_counters' );
	}

	/**
	 * Used by the outbound discovery step: given free text naming a competitor
	 * (typed or selected by the rep), find the matching library entry.
	 */
	public static function find_by_name( string $name ): ?WP_Post {
		$matches = get_posts(
			array(
				'post_type'      => 'ssb_competitor',
				'title'          => $name,
				'posts_per_page' => 1,
			)
		);
		return $matches ? $matches[0] : null;
	}
}
