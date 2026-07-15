<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Sample Content" admin page with one-click buttons to insert or
 * remove fully populated example data -- two products (with pain points,
 * competitors, objections, and an upsell path linking them) plus one active
 * special. Meant for local testing before real content is entered.
 *
 * All sample posts are tagged with meta '_ssb_is_sample_content' => 1 so
 * "Remove Sample Data" can find and delete exactly what it created, without
 * touching any real content someone may have already added.
 */
class SSB_Sample_Content {

	const SAMPLE_FLAG_META = '_ssb_is_sample_content';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_ssb_insert_sample_content', array( $this, 'handle_insert' ) );
		add_action( 'admin_post_ssb_remove_sample_content', array( $this, 'handle_remove' ) );
		add_action( 'admin_notices', array( $this, 'show_notices' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=ssb_product',
			__( 'Sample Content', 'sales-script-builder' ),
			__( 'Sample Content', 'sales-script-builder' ),
			'manage_options',
			'ssb-sample-content',
			array( $this, 'render_page' )
		);
	}

	private function sample_exists(): bool {
		$existing = get_posts(
			array(
				'post_type'      => array( 'ssb_product', 'ssb_special', 'ssb_competitor' ),
				'posts_per_page' => 1,
				'meta_key'       => self::SAMPLE_FLAG_META,
				'meta_value'     => 1,
				'fields'         => 'ids',
			)
		);
		return ! empty( $existing );
	}

	public function render_page(): void {
		$has_sample = $this->sample_exists();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sample Content', 'sales-script-builder' ); ?></h1>
			<p>
				<?php esc_html_e( 'Insert two fully populated example products (pain points, competitor comparisons, objections with key points and a counter script, and an upsell path linking them), one active special, and one Competitors library entry linked to both products -- so you have real data to test the script view against before entering your own content.', 'sales-script-builder' ); ?>
			</p>

			<?php if ( $has_sample ) : ?>
				<p><strong><?php esc_html_e( 'Sample content is currently installed.', 'sales-script-builder' ); ?></strong></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ssb_remove_sample_content' ); ?>
					<input type="hidden" name="action" value="ssb_remove_sample_content" />
					<?php submit_button( __( 'Remove Sample Data', 'sales-script-builder' ), 'delete' ); ?>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ssb_insert_sample_content' ); ?>
					<input type="hidden" name="action" value="ssb_insert_sample_content" />
					<?php submit_button( __( 'Insert Sample Data', 'sales-script-builder' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function show_notices(): void {
		if ( ! isset( $_GET['ssb_sample_result'] ) ) {
			return;
		}

		$result = sanitize_key( wp_unslash( $_GET['ssb_sample_result'] ) );

		if ( ! in_array( $result, array( 'inserted', 'removed' ), true ) ) {
			return;
		}

		$message = 'inserted' === $result
			? __( 'Sample content inserted.', 'sales-script-builder' )
			: __( 'Sample content removed.', 'sales-script-builder' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * INSERT
	 * ------------------------------------------------------------- */

	public function handle_insert(): void {
		check_admin_referer( 'ssb_insert_sample_content' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		if ( ! $this->sample_exists() ) {
			$this->insert_sample_data();
		}

		wp_safe_redirect( add_query_arg( 'ssb_sample_result', 'inserted', admin_url( 'edit.php?post_type=ssb_product&page=ssb-sample-content' ) ) );
		exit;
	}

	private function insert_sample_data(): void {

		// --- Category ---
		$term = wp_insert_term( 'Internet Service', 'ssb_category' );
		$term_id = is_wp_error( $term ) ? 0 : $term['term_id'];

		// --- Product 1: base plan ---
		$product_1_id = wp_insert_post(
			array(
				'post_type'    => 'ssb_product',
				'post_status'  => 'publish',
				'post_title'   => 'FastNet 300 Home Internet',
				'post_content' => 'Reliable 300 Mbps fiber internet for everyday browsing, streaming, and video calls. No data caps, no annual contract.',
			)
		);

		// --- Product 2: upgrade tier (created first so Product 1 can link to it) ---
		$product_2_id = wp_insert_post(
			array(
				'post_type'    => 'ssb_product',
				'post_status'  => 'publish',
				'post_title'   => 'FastNet 1 Gig Home Internet',
				'post_content' => 'Our fastest residential tier at 1000 Mbps -- built for multi-device households, 4K streaming on several screens at once, and heavy remote work/gaming use.',
			)
		);

		if ( is_wp_error( $product_1_id ) || is_wp_error( $product_2_id ) ) {
			return;
		}

		// --- Competitors library entry ---
		$competitor_id = wp_insert_post(
			array(
				'post_type'   => 'ssb_competitor',
				'post_status' => 'publish',
				'post_title'  => 'MegaCable',
			)
		);
		if ( ! is_wp_error( $competitor_id ) ) {
			update_post_meta( $competitor_id, self::SAMPLE_FLAG_META, 1 );
			update_post_meta( $competitor_id, '_ssb_competitor_pros', "Bundles with cable TV, which some households still want\nWidely recognized brand name\nBrick-and-mortar stores for in-person support" );
			update_post_meta( $competitor_id, '_ssb_competitor_cons', "1TB data cap with steep overage fees\nRequires a 2-year contract\nAsymmetrical upload speeds on their gig tier" );
			update_post_meta( $competitor_id, '_ssb_competitor_counters', "We don't cap data, so multi-device households never see a surprise overage fee.\nNo annual contract means switching later costs nothing.\nOur gig tier is symmetrical, which matters for video calls and cloud backups." );
			update_post_meta( $product_1_id, '_ssb_linked_competitors', array( $competitor_id ) );
			update_post_meta( $product_2_id, '_ssb_linked_competitors', array( $competitor_id ) );
		}

		if ( $term_id ) {
			wp_set_post_terms( $product_1_id, array( $term_id ), 'ssb_category' );
			wp_set_post_terms( $product_2_id, array( $term_id ), 'ssb_category' );
		}

		update_post_meta( $product_1_id, self::SAMPLE_FLAG_META, 1 );
		update_post_meta( $product_2_id, self::SAMPLE_FLAG_META, 1 );

		/* ----- Product 1 meta ----- */
		update_post_meta( $product_1_id, '_ssb_price', '$49.99/mo' );

		update_post_meta(
			$product_1_id,
			'_ssb_pain_points',
			array(
				array(
					'pain_point'      => 'Current internet is too slow for streaming and working from home at the same time.',
					'trigger_phrases' => 'buffering, slow wifi, video keeps freezing',
				),
				array(
					'pain_point'      => 'Frustrated with data caps or overage charges from current provider.',
					'trigger_phrases' => 'data cap, extra charges, overage fee',
				),
			)
		);

		update_post_meta(
			$product_1_id,
			'_ssb_competitors',
			array(
				array(
					'competitor_name' => 'MegaCable Basic',
					'feature'         => 'Data cap',
					'us'              => 'Unlimited data',
					'them'            => '1TB cap, $10/50GB overage',
					'why_it_matters'  => 'Households streaming across multiple devices routinely exceed 1TB.',
				),
				array(
					'competitor_name' => 'MegaCable Basic',
					'feature'         => 'Contract',
					'us'              => 'No annual contract',
					'them'            => '2-year contract required',
					'why_it_matters'  => 'Removes the early termination fee risk if the customer moves or switches.',
				),
			)
		);

		update_post_meta(
			$product_1_id,
			'_ssb_objections',
			array(
				array(
					'objection_type' => 'price',
					'objection'      => "That's more than what I'm paying now.",
					'response'       => "I understand -- and part of that is because there's no data cap here, so you won't see a surprise overage charge later. When you factor that in, most switching customers end up paying about the same or less each month.",
					'key_points'     => "No data cap, no surprise overage fees\nMost switchers pay about the same or less overall\nCurrent special: 50% off first 3 months",
					'counter_script' => '',
					'style'          => 'empathetic',
				),
				array(
					'objection_type' => 'timing',
					'objection'      => 'I need to think about it / I\'m locked into a contract right now.',
					'response'       => 'Totally fair. Would it help if I followed up closer to when your current contract wraps up, so you\'re not paying for two services at once?',
					'key_points'     => "Respect the no, don't push\nOffer a specific follow-up window\nKeep the door open",
					'counter_script' => "Actually, a lot of customers switch even mid-contract once they see the savings from no data caps -- it can offset an early termination fee within a few months. Want me to run the numbers for your specific situation?",
					'style'          => 'direct',
				),
			)
		);

		update_post_meta(
			$product_1_id,
			'_ssb_upsell_paths',
			array(
				array(
					'next_product_id' => (string) $product_2_id,
					'benefit'         => 'Doubles speed to 1 Gig for households adding more streaming devices, gaming, or remote work bandwidth needs.',
					'ideal_timing'    => 'At renewal, or within first 30 days if customer mentions multiple heavy users in the home.',
				),
			)
		);

		/* ----- Product 2 meta ----- */
		update_post_meta( $product_2_id, '_ssb_price', '$74.99/mo' );

		update_post_meta(
			$product_2_id,
			'_ssb_pain_points',
			array(
				array(
					'pain_point'      => 'Multiple people in the household streaming, gaming, and working on video calls simultaneously causes lag.',
					'trigger_phrases' => 'lag, everyone online at once, slows down when kids are home',
				),
			)
		);

		update_post_meta(
			$product_2_id,
			'_ssb_competitors',
			array(
				array(
					'competitor_name' => 'MegaCable Gig',
					'feature'         => 'Upload speed',
					'us'              => '1000 Mbps symmetrical (equal up/down)',
					'them'            => '1000 Mbps down / 35 Mbps up',
					'why_it_matters'  => 'Symmetrical speeds matter for video calls, cloud backups, and livestreaming.',
				),
			)
		);

		update_post_meta(
			$product_2_id,
			'_ssb_objections',
			array(
				array(
					'objection_type' => 'need',
					'objection'      => 'Do I really need that much speed?',
					'response'       => "If you've got more than 3-4 people streaming or gaming at once, yes -- this tier is built specifically so nobody has to compete for bandwidth.",
					'key_points'     => "Built for 3-4+ simultaneous heavy users\nNo bandwidth competition between devices",
					'counter_script' => '',
					'style'          => 'data-driven',
				),
			)
		);

		/* ----- Special tied to Product 1 ----- */
		$special_id = wp_insert_post(
			array(
				'post_type'    => 'ssb_special',
				'post_status'  => 'publish',
				'post_title'   => 'First 3 Months, 50% Off',
				'post_content' => 'New customers get 50% off FastNet 300 for the first 3 months. No promo code needed -- applied automatically at signup.',
			)
		);

		if ( ! is_wp_error( $special_id ) ) {
			update_post_meta( $special_id, self::SAMPLE_FLAG_META, 1 );
			update_post_meta( $special_id, '_ssb_start_date', gmdate( 'Y-m-d', strtotime( '-1 day' ) ) );
			update_post_meta( $special_id, '_ssb_end_date', gmdate( 'Y-m-d', strtotime( '+30 days' ) ) );
			update_post_meta( $special_id, '_ssb_terms', 'Valid for new residential customers only. Cannot be combined with other offers.' );
			update_post_meta( $special_id, '_ssb_applicable_products', array( $product_1_id ) );
		}
	}

	/* ---------------------------------------------------------------
	 * REMOVE
	 * ------------------------------------------------------------- */

	public function handle_remove(): void {
		check_admin_referer( 'ssb_remove_sample_content' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sales-script-builder' ) );
		}

		$sample_posts = get_posts(
			array(
				'post_type'      => array( 'ssb_product', 'ssb_special', 'ssb_competitor' ),
				'posts_per_page' => -1,
				'meta_key'       => self::SAMPLE_FLAG_META,
				'meta_value'     => 1,
				'fields'         => 'ids',
			)
		);

		foreach ( $sample_posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		// Clean up the sample taxonomy term only if nothing else is using it.
		$term = get_term_by( 'name', 'Internet Service', 'ssb_category' );
		if ( $term && 0 === (int) $term->count ) {
			wp_delete_term( $term->term_id, 'ssb_category' );
		}

		wp_safe_redirect( add_query_arg( 'ssb_sample_result', 'removed', admin_url( 'edit.php?post_type=ssb_product&page=ssb-sample-content' ) ) );
		exit;
	}
}

