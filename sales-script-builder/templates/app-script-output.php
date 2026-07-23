<?php
/**
 * Script output fragment for the Call Script SPA tab. Expects $product_id
 * and $call_type to be set explicitly by the caller (the AJAX handler in
 * class-shortcodes.php) -- no $_GET dependency, unlike script-view.php.
 * See app-call-script.php's docblock for why this duplicates rather than
 * shares logic with script-view.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
$call_types       = SSB_Favorites::get_call_type_labels();

// Only ever render a published ssb_product -- without this check, any post
// ID could be passed in and its title/content echoed, including private
// pages and other users' drafts.
$product = $product_id ? get_post( $product_id ) : null;
if ( $product && ( 'ssb_product' !== $product->post_type || 'publish' !== $product->post_status ) ) {
	$product = null;
}

if ( $product_id && ! $product ) {
	echo '<p class="ssb-error">' . esc_html__( 'That product/service could not be found.', 'sales-script-builder' ) . '</p>';
	return;
}

if ( ! $product || ! $call_type || ! isset( $call_types[ $call_type ] ) ) {
	return;
}

$pain_points     = SSB_Meta_Fields::get_pain_points( $product_id );
$competitors     = SSB_Meta_Fields::get_competitors( $product_id );
$objections      = SSB_Meta_Fields::get_objections( $product_id );
$upsell_paths    = SSB_Meta_Fields::get_upsell_paths( $product_id );
$active_specials = SSB_Meta_Fields::get_active_specials( $product_id );
$is_favorited    = SSB_Favorites::is_favorited( $current_user_id, $product_id, $call_type );

$linked_competitors = SSB_Meta_Fields::get_linked_competitors( $product_id );
if ( empty( $linked_competitors ) ) {
	$linked_competitors = get_posts(
		array(
			'post_type'      => 'ssb_competitor',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
}
$competitors_for_js = array();
foreach ( $linked_competitors as $competitor ) {
	$competitors_for_js[] = array(
		'id'       => $competitor->ID,
		'name'     => $competitor->post_title,
		'pros'     => SSB_Competitors::get_pros( $competitor->ID ),
		'cons'     => SSB_Competitors::get_cons( $competitor->ID ),
		'counters' => SSB_Competitors::get_counters( $competitor->ID ),
	);
}

$objections_for_js     = array();
$objection_type_labels = SSB_Meta_Fields::get_objection_type_labels();
foreach ( $objections as $row ) {
	$key_points_raw = $row['key_points'] ?? '';
	$key_points     = $key_points_raw ? array_values( array_filter( array_map( 'trim', explode( "\n", $key_points_raw ) ) ) ) : array();
	$type           = $row['objection_type'] ?? '';

	$objections_for_js[] = array(
		'type'           => $type,
		'type_label'     => $objection_type_labels[ $type ] ?? '',
		'objection'      => $row['objection'] ?? '',
		'response'       => $row['response'] ?? '',
		'key_points'     => $key_points,
		'counter_script' => trim( $row['counter_script'] ?? '' ),
	);
}

$category_terms = get_the_terms( $product_id, 'ssb_category' );
$category_label = ( is_array( $category_terms ) && ! empty( $category_terms ) ) ? $category_terms[0]->name : __( 'this', 'sales-script-builder' );
?>

<div class="ssb-script-output">

	<div class="ssb-script-header">
		<h2><?php echo esc_html( $product->post_title ); ?> — <?php echo esc_html( $call_types[ $call_type ] ?? '' ); ?></h2>
		<button type="button"
			class="ssb-favorite-btn <?php echo $is_favorited ? 'is-favorited' : ''; ?>"
			data-product-id="<?php echo esc_attr( $product_id ); ?>"
			data-call-type="<?php echo esc_attr( $call_type ); ?>"
			data-favorited="<?php echo $is_favorited ? '1' : '0'; ?>">
			<?php echo $is_favorited ? '&#9733;' : '&#9734;'; ?>
		</button>
	</div>

	<?php if ( 'cold' === $call_type ) : ?>
		<section class="ssb-section ssb-discovery" data-competitors='<?php echo esc_attr( wp_json_encode( $competitors_for_js ) ); ?>'>
			<h3><?php esc_html_e( 'Discovery', 'sales-script-builder' ); ?></h3>
			<p class="ssb-discovery-question">
				<?php
				/* translators: %s: product category name */
				printf( esc_html__( 'What are you using now for %s?', 'sales-script-builder' ), esc_html( $category_label ) );
				?>
			</p>
			<div class="ssb-discovery-buttons">
				<button type="button" class="ssb-discovery-btn" data-action="using-competitor"><?php esc_html_e( 'Using a competitor', 'sales-script-builder' ); ?></button>
				<button type="button" class="ssb-discovery-btn" data-action="not-using"><?php esc_html_e( 'Not using anyone', 'sales-script-builder' ); ?></button>
			</div>
			<div class="ssb-discovery-result"></div>
		</section>
	<?php elseif ( 'inbound' === $call_type ) : ?>
		<section class="ssb-section ssb-discovery">
			<h3><?php esc_html_e( 'Discovery', 'sales-script-builder' ); ?></h3>
			<p class="ssb-discovery-question"><?php esc_html_e( 'Why are you calling today?', 'sales-script-builder' ); ?></p>
			<div class="ssb-discovery-buttons">
				<button type="button" class="ssb-discovery-btn" data-action="looking-new"><?php esc_html_e( 'Looking for a new provider', 'sales-script-builder' ); ?></button>
				<button type="button" class="ssb-discovery-btn" data-action="shopping"><?php esc_html_e( 'Shopping around', 'sales-script-builder' ); ?></button>
				<button type="button" class="ssb-discovery-btn" data-action="ready"><?php esc_html_e( 'Already interested in buying', 'sales-script-builder' ); ?></button>
			</div>
			<div class="ssb-discovery-result"></div>
		</section>
	<?php endif; ?>

	<?php if ( 'upsell' === $call_type && ! empty( $active_specials ) ) : ?>
		<?php foreach ( $active_specials as $special ) : ?>
			<div class="ssb-special-banner" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-special-id="<?php echo esc_attr( $special->ID ); ?>">
				<strong><?php echo esc_html( $special->post_title ); ?></strong>
				<p><?php echo wp_kses_post( $special->post_content ); ?></p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( 'cold' === $call_type || 'inbound' === $call_type ) : ?>
		<section class="ssb-section">
			<h3><?php esc_html_e( 'Pain Points', 'sales-script-builder' ); ?></h3>
			<ul>
				<?php foreach ( $pain_points as $point ) : ?>
					<li><?php echo esc_html( $point['pain_point'] ?? '' ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( 'upsell' !== $call_type && ! empty( $active_specials ) ) : ?>
		<?php foreach ( $active_specials as $special ) : ?>
			<div class="ssb-special-banner" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-special-id="<?php echo esc_attr( $special->ID ); ?>">
				<strong><?php echo esc_html( $special->post_title ); ?></strong>
				<p><?php echo wp_kses_post( $special->post_content ); ?></p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( ! empty( $competitors ) ) : ?>
		<section class="ssb-section" id="ssb-compare">
			<h3><?php esc_html_e( 'How We Compare', 'sales-script-builder' ); ?></h3>
			<table class="ssb-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Competitor', 'sales-script-builder' ); ?></th>
						<th><?php esc_html_e( 'Feature', 'sales-script-builder' ); ?></th>
						<th><?php esc_html_e( 'Us', 'sales-script-builder' ); ?></th>
						<th><?php esc_html_e( 'Them', 'sales-script-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $competitors as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['competitor_name'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['feature'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['us'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['them'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $objections_for_js ) ) : ?>
		<section class="ssb-section" id="ssb-objections" data-objections='<?php echo esc_attr( wp_json_encode( $objections_for_js ) ); ?>'>
			<h3><?php esc_html_e( 'Objection Handling', 'sales-script-builder' ); ?></h3>
			<p class="ssb-objection-instructions"><?php esc_html_e( 'Tap the objection you\'re hearing.', 'sales-script-builder' ); ?></p>
			<div class="ssb-objection-buttons"></div>
			<p class="ssb-discussed-note"></p>
			<div class="ssb-objection-panel" hidden></div>
		</section>
	<?php endif; ?>

	<?php if ( 'upsell' === $call_type && ! empty( $upsell_paths ) ) : ?>
		<section class="ssb-section" id="ssb-upsell">
			<h3><?php esc_html_e( 'Next Path / Upsell', 'sales-script-builder' ); ?></h3>
			<?php foreach ( $upsell_paths as $row ) :
				$next_product = ! empty( $row['next_product_id'] ) ? get_post( $row['next_product_id'] ) : null;
				?>
				<div class="ssb-upsell-path">
					<?php if ( $next_product ) : ?>
						<strong><?php echo esc_html( $next_product->post_title ); ?></strong>
					<?php endif; ?>
					<p><?php echo esc_html( $row['benefit'] ?? '' ); ?></p>
					<?php if ( ! empty( $row['ideal_timing'] ) ) : ?>
						<p class="ssb-timing"><?php esc_html_e( 'Ideal timing:', 'sales-script-builder' ); ?> <?php echo esc_html( $row['ideal_timing'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

</div>
