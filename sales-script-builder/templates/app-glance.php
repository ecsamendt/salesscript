<?php
/**
 * "Competitors At A Glance" tab for [ssb_app]. Read-only flashcard tool --
 * no editing here (that's the Add Competitors tab). All data is embedded
 * as JSON on initial render since the competitor catalog is small; the
 * grid, search, and flashcard flip are all handled client-side in
 * assets/js/glance.js with no further server round-trips.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$competitors = get_posts(
	array(
		'post_type'      => 'ssb_competitor',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

$competitors_for_js = array();
foreach ( $competitors as $competitor ) {
	$linked_products = SSB_Meta_Fields::get_products_linked_to_competitor( $competitor->ID );

	$products_for_js = array();
	foreach ( $linked_products as $match ) {
		$products_for_js[] = array(
			'name'          => $match['product']->post_title,
			'next_tier'     => $match['next_tier'] ? $match['next_tier']->post_title : null,
		);
	}

	$competitors_for_js[] = array(
		'id'       => $competitor->ID,
		'name'     => $competitor->post_title,
		'pros'     => SSB_Competitors::get_pros( $competitor->ID ),
		'cons'     => SSB_Competitors::get_cons( $competitor->ID ),
		'counters' => SSB_Competitors::get_counters( $competitor->ID ),
		'products' => $products_for_js,
	);
}
?>
<div class="ssb-glance" data-competitors='<?php echo esc_attr( wp_json_encode( $competitors_for_js ) ); ?>'>
	<h2><?php esc_html_e( 'Competitors At A Glance', 'sales-script-builder' ); ?></h2>
	<p class="description"><?php esc_html_e( 'A fast reference for reps who already know their pitch and just need to see who they\'re up against.', 'sales-script-builder' ); ?></p>

	<?php if ( empty( $competitors ) ) : ?>
		<p class="ssb-empty-state"><?php esc_html_e( 'No competitors in the library yet.', 'sales-script-builder' ); ?></p>
	<?php else : ?>

		<div class="ssb-glance-deck">
			<input type="search" class="ssb-glance-search" placeholder="<?php esc_attr_e( 'Search competitors...', 'sales-script-builder' ); ?>" />
			<div class="ssb-glance-grid"></div>
		</div>

		<div class="ssb-glance-flashcard" hidden>
			<button type="button" class="ssb-link-btn ssb-glance-back-btn">&larr; <?php esc_html_e( 'Back to all competitors', 'sales-script-builder' ); ?></button>

			<div class="ssb-glance-card">
				<div class="ssb-glance-card-nav">
					<button type="button" class="ssb-glance-prev-btn" aria-label="<?php esc_attr_e( 'Previous competitor', 'sales-script-builder' ); ?>">&larr;</button>
					<h3 class="ssb-glance-card-name"></h3>
					<button type="button" class="ssb-glance-next-btn" aria-label="<?php esc_attr_e( 'Next competitor', 'sales-script-builder' ); ?>">&rarr;</button>
				</div>

				<div class="ssb-glance-card-body">
					<div class="ssb-glance-card-section">
						<p class="ssb-objection-label"><?php esc_html_e( 'Their Pros', 'sales-script-builder' ); ?></p>
						<ul class="ssb-glance-pros"></ul>
					</div>
					<div class="ssb-glance-card-section">
						<p class="ssb-objection-label"><?php esc_html_e( 'Their Cons', 'sales-script-builder' ); ?></p>
						<ul class="ssb-glance-cons"></ul>
					</div>
					<div class="ssb-glance-card-section">
						<p class="ssb-objection-label"><?php esc_html_e( 'Our Counters', 'sales-script-builder' ); ?></p>
						<ul class="ssb-glance-counters"></ul>
					</div>
					<div class="ssb-glance-card-section">
						<p class="ssb-objection-label"><?php esc_html_e( 'Comparable Products', 'sales-script-builder' ); ?></p>
						<ul class="ssb-glance-products"></ul>
					</div>
				</div>
			</div>
		</div>

	<?php endif; ?>
</div>
