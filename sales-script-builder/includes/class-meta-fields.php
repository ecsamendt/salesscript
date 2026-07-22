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

		if ( ! in_array( $post_type, array( 'ssb_product', 'ssb_special', 'ssb_competitor' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'ssb-admin', SSB_PLUGIN_URL . 'assets/css/admin.css', array(), SSB_VERSION );
		wp_enqueue_script( 'ssb-admin-repeater', SSB_PLUGIN_URL . 'assets/js/admin-repeater.js', array(), SSB_VERSION, true );
	}

	public function register_meta_boxes(): void {

		add_meta_box( 'ssb_pain_points', __( 'Pain Points Solved', 'sales-script-builder' ), array( $this, 'render_pain_points' ), 'ssb_product', 'normal', 'high' );
		add_meta_box( 'ssb_overview_highlights', __( 'Overview Highlights', 'sales-script-builder' ), array( $this, 'render_overview_highlights' ), 'ssb_product', 'normal', 'high' );
		add_meta_box( 'ssb_competitors', __( 'Competitor Comparisons', 'sales-script-builder' ), array( $this, 'render_competitors' ), 'ssb_product', 'normal', 'default' );
		add_meta_box( 'ssb_linked_competitors', __( 'Linked Competitors (from library)', 'sales-script-builder' ), array( $this, 'render_linked_competitors' ), 'ssb_product', 'side', 'default' );
		add_meta_box( 'ssb_objections', __( 'Objection Handling', 'sales-script-builder' ), array( $this, 'render_objections' ), 'ssb_product', 'normal', 'default' );
		add_meta_box( 'ssb_upsell', __( 'Upsell / Next Path', 'sales-script-builder' ), array( $this, 'render_upsell' ), 'ssb_product', 'normal', 'default' );
		add_meta_box( 'ssb_pricing', __( 'Pricing', 'sales-script-builder' ), array( $this, 'render_pricing' ), 'ssb_product', 'side', 'default' );
		add_meta_box( 'ssb_internal_notes', __( 'Internal Notes (not shown to reps)', 'sales-script-builder' ), array( $this, 'render_internal_notes' ), 'ssb_product', 'side', 'low' );

		add_meta_box( 'ssb_special_details', __( 'Special/Discount Details', 'sales-script-builder' ), array( $this, 'render_special_details' ), 'ssb_special', 'normal', 'high' );
	}

	/* ---------------------------------------------------------------
	 * PRODUCT META BOXES
	 * ------------------------------------------------------------- */

	public static function render_pricing( WP_Post $post ): void {
		$price = get_post_meta( $post->ID, '_ssb_price', true );
		?>
		<label for="ssb_price"><?php esc_html_e( 'Price / Tier', 'sales-script-builder' ); ?></label>
		<input type="text" id="ssb_price" name="ssb_price" value="<?php echo esc_attr( $price ); ?>" class="widefat" placeholder="e.g. $49.99/mo or Tier 2" />
		<?php
	}

	public static function render_pain_points( WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, '_ssb_pain_points', true );
		$rows = is_array( $rows ) ? $rows : array();
		self::render_repeater(
			'ssb_pain_points',
			$rows,
			array(
				'pain_point'      => __( 'Pain Point', 'sales-script-builder' ),
				'trigger_phrases' => __( 'Trigger Phrases (comma separated)', 'sales-script-builder' ),
				'pivot_script'    => __( 'Acknowledgment / Pivot Script (what the rep says when this pain point comes up)', 'sales-script-builder' ),
			),
			array(),
			array( 'pivot_script' ) // Render as <textarea>.
		);
	}

	/**
	 * Rep-facing "on top of that" highlights, shown alongside whichever pain
	 * point is currently active. Replaces the old single Overview paragraph
	 * (see render_internal_notes() for where that went instead). Each row is
	 * one short, independently mutable highlight -- the front-end tracks
	 * "mentioned" state client-side per product, per call; nothing here
	 * stores that state, this only stores the highlight text itself.
	 */
	public static function render_overview_highlights( WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, '_ssb_overview_highlights', true );
		$rows = is_array( $rows ) ? $rows : array();
		self::render_repeater(
			'ssb_overview_highlights',
			$rows,
			array(
				'highlight' => __( 'Highlight (short phrase, e.g. "No data cap")', 'sales-script-builder' ),
			)
		);
	}

	/**
	 * Admin/management-only notes. Was previously the native post_content
	 * editor, shown to reps as "Overview" -- now split out so nothing here
	 * is ever rendered in the script view.
	 */
	public static function render_internal_notes( WP_Post $post ): void {
		$value = get_post_meta( $post->ID, '_ssb_internal_notes', true );
		?>
		<p class="description"><?php esc_html_e( 'Not shown to reps. Use this for anything the team managing products needs to remember -- sourcing notes, pending changes, etc.', 'sales-script-builder' ); ?></p>
		<textarea class="widefat" rows="4" name="ssb_internal_notes"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	public static function render_competitors( WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, '_ssb_competitors', true );
		$rows = is_array( $rows ) ? $rows : array();
		self::render_repeater(
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

	/**
	 * Checkbox list linking this product to entries in the Competitors library
	 * (see class-competitors.php). Distinct from the per-product comparison
	 * repeater above: that's a manual feature-by-feature table, this is "which
	 * shared competitor profiles apply to this tier" so the script view can
	 * surface that competitor's general pros/cons/counters during discovery.
	 */
	public static function render_linked_competitors( WP_Post $post ): void {
		$linked = get_post_meta( $post->ID, '_ssb_linked_competitors', true );
		$linked = is_array( $linked ) ? $linked : array();

		$competitors = get_posts(
			array(
				'post_type'      => 'ssb_competitor',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		// Marker so save_product_meta() can tell "box was rendered, user unchecked
		// everything" apart from "box was hidden via Screen Options" -- otherwise
		// unchecking every competitor would look identical to the box being absent,
		// and the existing selections would never actually clear.
		echo '<input type="hidden" name="ssb_linked_competitors_present" value="1" />';

		if ( empty( $competitors ) ) {
			echo '<p class="description">' . esc_html__( 'No competitors in the library yet. Add some under Products/Services > Competitors.', 'sales-script-builder' ) . '</p>';
			return;
		}

		foreach ( $competitors as $competitor ) {
			?>
			<label style="display:block;margin-bottom:6px;">
				<input type="checkbox" name="ssb_linked_competitors[]" value="<?php echo esc_attr( $competitor->ID ); ?>" <?php checked( in_array( $competitor->ID, $linked, true ) ); ?> />
				<?php echo esc_html( $competitor->post_title ); ?>
			</label>
			<?php
		}
	}

	public static function render_objections( WP_Post $post ): void {
		$rows = get_post_meta( $post->ID, '_ssb_objections', true );
		$rows = is_array( $rows ) ? $rows : array();
		self::render_repeater(
			'ssb_objections',
			$rows,
			array(
				'objection_type' => __( 'Objection Type', 'sales-script-builder' ),
				'objection'      => __( 'Customer Concern', 'sales-script-builder' ),
				'response'       => __( 'Script (what to say)', 'sales-script-builder' ),
				'key_points'     => __( 'Key Points Recap (one per line, for going off script)', 'sales-script-builder' ),
				'counter_script' => __( 'Counter Script (optional -- shown if rep tries to overcome the objection)', 'sales-script-builder' ),
				'style'          => __( 'Tone (empathetic / direct / data-driven)', 'sales-script-builder' ),
			),
			array( 'objection_type' => self::get_objection_type_labels() ),
			array( 'key_points', 'counter_script' ) // Render as <textarea> instead of <input>.
		);
	}

	/**
	 * Objection type controls which decision path the rep sees in the script
	 * view: "timing" (and similarly structured types) offer a respect-it vs.
	 * counter-it choice before landing on a follow-up off-ramp; "standard"
	 * types go straight to the scripted response.
	 */
	public static function get_objection_type_labels(): array {
		return array(
			'timing'      => __( 'Timing', 'sales-script-builder' ),
			'price'       => __( 'Price / Budget', 'sales-script-builder' ),
			'need'        => __( 'Need / Value', 'sales-script-builder' ),
			'trust'       => __( 'Trust / Credibility', 'sales-script-builder' ),
			'authority'   => __( 'Authority / Decision-maker', 'sales-script-builder' ),
			'competitor'  => __( 'Competitor Comparison', 'sales-script-builder' ),
			'risk'        => __( 'Risk / Change Aversion', 'sales-script-builder' ),
			'skepticism'  => __( 'Skepticism', 'sales-script-builder' ),
		);
	}

	public static function render_upsell( WP_Post $post ): void {
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
		self::render_repeater(
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

	public static function render_special_details( WP_Post $post ): void {
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
	 * @param array  $textarea_fields Optional: list of keys that should render as <textarea> instead of <input>.
	 */
	private static function render_repeater( string $field_name, array $rows, array $columns, array $select_options = array(), array $textarea_fields = array() ): void {
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
								<?php elseif ( in_array( $key, $textarea_fields, true ) ) : ?>
									<textarea class="widefat" rows="3" name="<?php echo esc_attr( $field_name . '[' . $i . '][' . $key . ']' ); ?>"><?php echo esc_textarea( isset( $row[ $key ] ) ? $row[ $key ] : '' ); ?></textarea>
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

		self::save_product_fields_from_post( $post_id );
	}

	/**
	 * The actual field-saving logic, deliberately separated from the nonce and
	 * capability checks above. This is what SSB_Frontend_Editor calls after
	 * its own (different) nonce and access checks, so the sanitization rules
	 * live in exactly one place regardless of whether the save came from
	 * wp-admin or the front-end product editor.
	 */
	public static function save_product_fields_from_post( int $post_id ): void {
		if ( isset( $_POST['ssb_price'] ) ) {
			update_post_meta( $post_id, '_ssb_price', sanitize_text_field( wp_unslash( $_POST['ssb_price'] ) ) );
		}

		if ( isset( $_POST['ssb_internal_notes'] ) ) {
			update_post_meta( $post_id, '_ssb_internal_notes', sanitize_textarea_field( wp_unslash( $_POST['ssb_internal_notes'] ) ) );
		}

		if ( isset( $_POST['ssb_linked_competitors_present'] ) ) {
			$linked = isset( $_POST['ssb_linked_competitors'] ) ? array_map( 'intval', (array) $_POST['ssb_linked_competitors'] ) : array();
			update_post_meta( $post_id, '_ssb_linked_competitors', $linked );
		}

		self::save_repeater_field( $post_id, '_ssb_pain_points', 'ssb_pain_points' );
		self::save_repeater_field( $post_id, '_ssb_overview_highlights', 'ssb_overview_highlights' );
		self::save_repeater_field( $post_id, '_ssb_competitors', 'ssb_competitors' );
		self::save_repeater_field( $post_id, '_ssb_objections', 'ssb_objections' );
		self::save_repeater_field( $post_id, '_ssb_upsell_paths', 'ssb_upsell_paths' );
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

		self::save_special_fields_from_post( $post_id );
	}

	/**
	 * The actual field-saving logic, separated from the nonce/capability
	 * checks above for the same reason as save_product_fields_from_post() --
	 * so the front-end specials editor can reuse it after its own checks.
	 */
	public static function save_special_fields_from_post( int $post_id ): void {
		// The details fields were not on this screen -- don't clear their data.
		if ( ! isset( $_POST['ssb_special_details_present'] ) ) {
			return;
		}

		update_post_meta( $post_id, '_ssb_start_date', sanitize_text_field( wp_unslash( $_POST['ssb_start_date'] ?? '' ) ) );
		update_post_meta( $post_id, '_ssb_end_date', sanitize_text_field( wp_unslash( $_POST['ssb_end_date'] ?? '' ) ) );
		update_post_meta( $post_id, '_ssb_terms', sanitize_textarea_field( wp_unslash( $_POST['ssb_terms'] ?? '' ) ) );

		$applicable = isset( $_POST['ssb_applicable_products'] ) ? array_map( 'intval', (array) $_POST['ssb_applicable_products'] ) : array();
		update_post_meta( $post_id, '_ssb_applicable_products', $applicable );
	}

	private static function save_repeater_field( int $post_id, string $meta_key, string $field_name ): void {
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

	public static function get_overview_highlights( int $product_id ): array {
		$rows = get_post_meta( $product_id, '_ssb_overview_highlights', true );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Admin/management-only -- never render this in the script view. See
	 * render_internal_notes() for why this is separate from Overview Highlights.
	 */
	public static function get_internal_notes( int $product_id ): string {
		$notes = get_post_meta( $product_id, '_ssb_internal_notes', true );
		return is_string( $notes ) ? $notes : '';
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
	 * Returns the WP_Post objects for competitors linked to this product from
	 * the shared Competitors library (see class-competitors.php).
	 */
	public static function get_linked_competitors( int $product_id ): array {
		$ids = get_post_meta( $product_id, '_ssb_linked_competitors', true );
		$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();

		if ( empty( $ids ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => 'ssb_competitor',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
			)
		);
	}

	/**
	 * The reverse of get_linked_competitors(): given a competitor, which
	 * products link to it. Used by "Competitors At A Glance" so a rep can see
	 * everything comparable to a competitor without knowing which product to
	 * check first. Iterates all products in PHP rather than a meta_query,
	 * since "Linked Competitors" is stored as a serialized array and WP's
	 * meta_query can't reliably search inside one -- fine at this scale
	 * (a single business's product catalog), reconsider only if that catalog
	 * grows into the hundreds.
	 *
	 * Each result includes 'next_tier': the WP_Post for the next upsell step
	 * from that product, if one exists, so the flashcard can flag it without
	 * a second lookup.
	 */
	public static function get_products_linked_to_competitor( int $competitor_id ): array {
		$all_products = get_posts(
			array(
				'post_type'      => 'ssb_product',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$matches = array();

		foreach ( $all_products as $product ) {
			$linked_ids = get_post_meta( $product->ID, '_ssb_linked_competitors', true );
			$linked_ids = is_array( $linked_ids ) ? array_map( 'intval', $linked_ids ) : array();

			if ( ! in_array( $competitor_id, $linked_ids, true ) ) {
				continue;
			}

			$next_tier = null;
			$upsell_paths = self::get_upsell_paths( $product->ID );
			if ( ! empty( $upsell_paths[0]['next_product_id'] ) ) {
				$next_tier = get_post( $upsell_paths[0]['next_product_id'] );
				if ( ! $next_tier || 'ssb_product' !== $next_tier->post_type ) {
					$next_tier = null;
				}
			}

			$matches[] = array(
				'product'   => $product,
				'next_tier' => $next_tier,
			);
		}

		return $matches;
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
