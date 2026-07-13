<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the repeater-style meta boxes for ssb_product and ssb_special.
 *
 * NOTE: This is a lightweight, dependency-free repeater implementation
 * (plain JS, stored as arrays in post meta). If Advanced Custom Fields
 * (Pro) is already part of the stack, swap this out for ACF repeater
 * fields instead -- the get_* helper methods below are written so the
 * rest of the plugin doesn't care which one is used under the hood.
 */
class SSB_Meta_Fields {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'edit_form_after_title', array( $this, 'render_nonces' ) );
		add_action( 'save_post_ssb_product', array( $this, 'save_product_meta' ) );
		add_action( 'save_post_ssb_special', array( $this, 'save_special_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Nonces live here rather than inside a meta box callback on purpose: a meta
	 * box can be hidden via Screen Options, in which case its callback never
	 * runs, the nonce is never printed, and every save_* handler below would
	 * bail out -- silently discarding the user's edits.
	 */
	public function render_nonces( WP_Post $post ): void {
		if ( 'ssb_product' === $post->post_type ) {
			wp_nonce_field( 'ssb_save_product_meta', 'ssb_product_nonce' );
		}

		if ( 'ssb_special' === $post->post_type ) {
			wp_nonce_field( 'ssb_save_special_meta', 'ssb_special_nonce' );
		}
	}

	public function enqueue_admin_assets( string $hook ): void {
		global $post_type;

		if ( ! in_array( $post_type, array( 'ssb_product', 'ssb_special' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'ssb-admin', SSB_PLUGIN_URL . 'assets/css/admin.css', array(), SSB_VERSION );
		wp_enqueue_script( 'ssb-admin-repeater', SSB_PLUGIN_URL . 'assets/js/admin-repeater.js', array(), SSB_VERSION, true );
	}

	public function register_meta_boxes(): void {

		add_meta_box( 'ssb_pain_points', __( 'Pain Points Solved', 'sales-script-builder' ), array( $this, 'render_pain_points' ), 'ssb_product', 'normal', 'high' );
		add_meta_box( 'ssb_competitors', __( 'Competitor Comparisons', 'sales-script-builder' ), array( $this, 'render_competitors' ), 'ssb_product', 'normal', 'default' );
		add_meta_box( 'ssb_objections', __( 'Objection Handling', 'sales-script-builder' ), array( $this, 'render_objections' ), 'ssb_product', 'normal', 'default' );
		add_meta_box( 'ssb_upsell', __( 'Upsell / Next Path', 'sales-script-builder' ), array( $this, 'render_upsell' ), 'ssb_product', 'normal', 'default' );
		add_meta_box( 'ssb_pricing', __( 'Pricing', 'sales-script-builder' ), array( $this, 'render_pricing' ), 'ssb_product', 'side', 'default' );

		add_meta_box( 'ssb_special_details', __( 'Special/Discount Details', 'sales-script-builder' ), array( $this, 'render_special_details' ), 'ssb_special', 'normal', 'high' );
	}

	/* ---------------------------------------------------------------
	 * PRODUCT META BOXES
	 * ------------------------------------------------------------- */

	public function render_pricing( WP_Post $post ): void {
		$price = get_post_meta( $post->ID, '_ssb_price', true );
		?>
		<label for="ssb_price"><?php esc_html_e( 'Price / Tier', 'sales-script-builder' ); ?></label>
		<input type="text" id="ssb_price" name="ssb_price" value="<?php echo esc_attr( $price ); ?>" class="widefat" placeholder="e.g. $49.99/mo or Tier 2" />
		<?php
	}

	public function render_pain_points( WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, '_ssb_pain_points', true );
		$rows = is_array( $rows ) ? $rows : array();
		$this->render_repeater(
			'ssb_pain_points',
			$rows,
			array(
				'pain_point'      => __( 'Pain Point', 'sales-script-builder' ),
				'trigger_phrases' => __( 'Trigger Phrases (comma separated)', 'sales-script-builder' ),
			)
		);
	}

	public function render_competitors( WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, '_ssb_competitors', true );
		$rows = is_array( $rows ) ? $rows : array();
		$this->render_repeater(
			'ssb_competitors',
			$rows,
			array(
				'competitor_name' => __( 'Competitor Name', 'sales-script-builder' ),
				'feature'         => __( 'Feature Compared', 'sales-script-builder' ),
				'us'              => __( 'Us', 'sales-script-builder' ),
				'them'            => __( 'Them', 'sales-script-builder' ),
				'why_it_matters'  => __( 'Why It Matters', 'sales-script-builder' ),
			)
		);
	}

	public function render_objections( WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, '_ssb_objections', true );
		$rows = is_array( $rows ) ? $rows : array();
		$this->render_repeater(
			'ssb_objections',
			$rows,
			array(
				'objection' => __( 'Customer Concern', 'sales-script-builder' ),
				'response'  => __( 'Suggested Response', 'sales-script-builder' ),
				'style'     => __( 'Tone (empathetic / direct / data-driven)', 'sales-script-builder' ),
			)
		);
	}

	public function render_upsell( WP_Post $post ): void {
		$rows           = get_post_meta( $post->ID, '_ssb_upsell_paths', true );
		$rows           = is_array( $rows ) ? $rows : array();
		$all_products   = get_posts(
			array(
				'post_type'      => 'ssb_product',
				'posts_per_page' => -1,
				'exclude'        => array( $post->ID ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$product_options = array();
		foreach ( $all_products as $product ) {
			$product_options[ $product->ID ] = $product->post_title;
		}
		$this->render_repeater(
			'ssb_upsell_paths',
			$rows,
			array(
				'next_product_id' => __( 'Next Product/Bundle', 'sales-script-builder' ),
				'benefit'         => __( 'Benefit of Upgrading/Bundling', 'sales-script-builder' ),
				'ideal_timing'    => __( 'Ideal Timing (e.g. renewal, 30 days in)', 'sales-script-builder' ),
			),
			array( 'next_product_id' => $product_options ) // Render this field as a <select>.
		);
	}

	/* ---------------------------------------------------------------
	 * SPECIAL/DISCOUNT META BOX
	 * ------------------------------------------------------------- */

	public function render_special_details( WP_Post $post ): void {
		$start_date       = get_post_meta( $post->ID, '_ssb_start_date', true );
		$end_date         = get_post_meta( $post->ID, '_ssb_end_date', true );
		$terms            = get_post_meta( $post->ID, '_ssb_terms', true );
		$applicable_ids   = get_post_meta( $post->ID, '_ssb_applicable_products', true );
		$applicable_ids   = is_array( $applicable_ids ) ? $applicable_ids : array();

		$all_products = get_posts(
			array(
				'post_type'      => 'ssb_product',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<?php // Marker so save_special_meta() can tell "box was rendered, user cleared the fields" apart from "box was never rendered". ?>
		<input type="hidden" name="ssb_special_details_present" value="1" />
		<p>
			<label for="ssb_start_date"><?php esc_html_e( 'Start Date', 'sales-script-builder' ); ?></label><br />
			<input type="date" id="ssb_start_date" name="ssb_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
		</p>
		<p>
			<label for="ssb_end_date"><?php esc_html_e( 'End Date', 'sales-script-builder' ); ?></label><br />
			<input type="date" id="ssb_end_date" name="ssb_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
		</p>
		<p>
			<label for="ssb_terms"><?php esc_html_e( 'Terms / Restrictions', 'sales-script-builder' ); ?></label><br />
			<textarea id="ssb_terms" name="ssb_terms" class="widefat" rows="3"><?php echo esc_textarea( $terms ); ?></textarea>
		</p>
		<p>
			<label><?php esc_html_e( 'Applicable Products/Services', 'sales-script-builder' ); ?></label><br />
			<?php foreach ( $all_products as $product ) : ?>
				<label style="display:block;">
					<input type="checkbox" name="ssb_applicable_products[]" value="<?php echo esc_attr( $product->ID ); ?>" <?php checked( in_array( $product->ID, $applicable_ids, true ) ); ?> />
					<?php echo esc_html( $product->post_title ); ?>
				</label>
			<?php endforeach; ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Active status is calculated automatically from the date range above -- no need to manually expire a special.', 'sales-script-builder' ); ?>
		</p>
		<?php
	}

	/* ---------------------------------------------------------------
	 * SHARED REPEATER RENDERER
	 * ------------------------------------------------------------- */

	/**
	 * Renders a generic repeater field block. JS (admin-repeater.js) handles
	 * add/remove row behavior; PHP just needs consistent name="field[__INDEX__][key]"
	 * patterns so save_* methods can re-index cleanly regardless of gaps.
	 *
	 * @param string $field_name   Meta key / repeater name (also used as data-repeater id).
	 * @param array  $rows         Existing saved rows.
	 * @param array  $columns      key => label pairs for each column in a row.
	 * @param array  $select_options Optional: [key => [value => label]] for columns that should render as <select> instead of <input>.
	 */
	private function render_repeater( string $field_name, array $rows, array $columns, array $select_options = array() ): void {
		if ( empty( $rows ) ) {
			$rows = array( array_fill_keys( array_keys( $columns ), '' ) );
		}
		?>
		<div class="ssb-repeater" data-repeater="<?php echo esc_attr( $field_name ); ?>">
			<div class="ssb-repeater-rows">
				<?php foreach ( $rows as $i => $row ) : ?>
					<div class="ssb-repeater-row">
						<?php foreach ( $columns as $key => $label ) : ?>
							<p>
								<label><?php echo esc_html( $label ); ?></label><br />
								<?php if ( isset( $select_options[ $key ] ) ) : ?>
									<select name="<?php echo esc_attr( $field_name . '[' . $i . '][' . $key . ']' ); ?>" class="widefat">
										<option value=""><?php esc_html_e( '-- Select --', 'sales-script-builder' ); ?></option>
										<?php foreach ( $select_options[ $key ] as $value => $option_label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $row[ $key ] ) ? $row[ $key ] : '', $value ); ?>>
												<?php echo esc_html( $option_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input type="text" class="widefat" name="<?php echo esc_attr( $field_name . '[' . $i . '][' . $key . ']' ); ?>" value="<?php echo esc_attr( isset( $row[ $key ] ) ? $row[ $key ] : '' ); ?>" />
								<?php endif; ?>
							</p>
						<?php endforeach; ?>
						<button type="button" class="button ssb-remove-row"><?php esc_html_e( 'Remove', 'sales-script-builder' ); ?></button>
						<hr />
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary ssb-add-row"><?php esc_html_e( 'Add Row', 'sales-script-builder' ); ?></button>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * SAVE HANDLERS
	 * ------------------------------------------------------------- */

	public function save_product_meta( int $post_id ): void {
		if ( ! isset( $_POST['ssb_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_product_nonce'] ) ), 'ssb_save_product_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['ssb_price'] ) ) {
			update_post_meta( $post_id, '_ssb_price', sanitize_text_field( wp_unslash( $_POST['ssb_price'] ) ) );
		}

		$this->save_repeater_field( $post_id, '_ssb_pain_points', 'ssb_pain_points' );
		$this->save_repeater_field( $post_id, '_ssb_competitors', 'ssb_competitors' );
		$this->save_repeater_field( $post_id, '_ssb_objections', 'ssb_objections' );
		$this->save_repeater_field( $post_id, '_ssb_upsell_paths', 'ssb_upsell_paths' );
	}

	public function save_special_meta( int $post_id ): void {
		if ( ! isset( $_POST['ssb_special_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ssb_special_nonce'] ) ), 'ssb_save_special_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// The details meta box was not on this screen -- don't clear its data.
		if ( ! isset( $_POST['ssb_special_details_present'] ) ) {
			return;
		}

		update_post_meta( $post_id, '_ssb_start_date', sanitize_text_field( wp_unslash( $_POST['ssb_start_date'] ?? '' ) ) );
		update_post_meta( $post_id, '_ssb_end_date', sanitize_text_field( wp_unslash( $_POST['ssb_end_date'] ?? '' ) ) );
		update_post_meta( $post_id, '_ssb_terms', sanitize_textarea_field( wp_unslash( $_POST['ssb_terms'] ?? '' ) ) );

		$applicable = isset( $_POST['ssb_applicable_products'] ) ? array_map( 'intval', (array) $_POST['ssb_applicable_products'] ) : array();
		update_post_meta( $post_id, '_ssb_applicable_products', $applicable );
	}

	private function save_repeater_field( int $post_id, string $meta_key, string $field_name ): void {
		// Absent means the meta box was not rendered on this screen (e.g. hidden
		// via Screen Options), NOT that the user cleared every row -- overwriting
		// with an empty array here would silently destroy saved rows. Clearing all
		// rows still works: the repeater always submits at least one (empty) row,
		// which is filtered out below.
		if ( ! isset( $_POST[ $field_name ] ) || ! is_array( $_POST[ $field_name ] ) ) {
			return;
		}

		$clean_rows = array();
		foreach ( $_POST[ $field_name ] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean_row = array();
			foreach ( $row as $key => $value ) {
				$clean_row[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
			}
			// Skip fully empty rows.
			if ( count( array_filter( $clean_row ) ) > 0 ) {
				$clean_rows[] = $clean_row;
			}
		}

		update_post_meta( $post_id, $meta_key, $clean_rows );
	}

	/* ---------------------------------------------------------------
	 * PUBLIC READ HELPERS (used by the script-assembly template)
	 * ------------------------------------------------------------- */

	public static function get_pain_points( int $product_id ): array {
		$rows = get_post_meta( $product_id, '_ssb_pain_points', true );
		return is_array( $rows ) ? $rows : array();
	}

	public static function get_competitors( int $product_id ): array {
		$rows = get_post_meta( $product_id, '_ssb_competitors', true );
		return is_array( $rows ) ? $rows : array();
	}

	public static function get_objections( int $product_id ): array {
		$rows = get_post_meta( $product_id, '_ssb_objections', true );
		return is_array( $rows ) ? $rows : array();
	}

	public static function get_upsell_paths( int $product_id ): array {
		$rows = get_post_meta( $product_id, '_ssb_upsell_paths', true );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Returns active specials (today falls within start/end date) for a given product.
	 */
	public static function get_active_specials( int $product_id ): array {
		$today = current_time( 'Y-m-d' );

		$specials = get_posts(
			array(
				'post_type'      => 'ssb_special',
				'posts_per_page' => -1,
				'meta_query'      => array(
					'relation' => 'AND',
					array(
						'key'     => '_ssb_start_date',
						'value'   => $today,
						'compare' => '<=',
						'type'    => 'DATE',
					),
					array(
						'key'     => '_ssb_end_date',
						'value'   => $today,
						'compare' => '>=',
						'type'    => 'DATE',
					),
				),
			)
		);

		return array_values(
			array_filter(
				$specials,
				function ( $special ) use ( $product_id ) {
					$applicable = get_post_meta( $special->ID, '_ssb_applicable_products', true );
					return is_array( $applicable ) && in_array( $product_id, $applicable, true );
				}
			)
		);
	}
}
