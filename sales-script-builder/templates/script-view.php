<?php
/**
 * Rep-facing script view. Included by SSB_Shortcodes::render_script_builder().
 * Kept deliberately simple/readable -- this is used live on calls, so speed
 * and clarity matter more than visual flourish.
 *
 * Reads $_GET['product_id'] and $_GET['call_type'] if present (e.g. linked
 * from a favorites list); otherwise shows the product/call-type picker.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
$product_id       = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
$call_type        = isset( $_GET['call_type'] ) ? sanitize_key( wp_unslash( $_GET['call_type'] ) ) : '';

$call_types = SSB_Favorites::get_call_type_labels();

$all_products = get_posts(
	array(
		'post_type'      => 'ssb_product',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>
<div class="ssb-script-builder" data-copy-protect="true">

	<form method="get" class="ssb-picker">
		<?php
		// Preserve any query args already on the URL (e.g. ?page_id=88143&preview=true
		// on staging, or any other param the page was reached with). A GET form with
		// no explicit action submits to the current path only and drops the existing
		// query string -- without this, submitting the picker can land the visitor on
		// the wrong page entirely (or lose preview mode) instead of reloading this one.
		foreach ( $_GET as $key => $value ) {
			if ( in_array( $key, array( 'product_id', 'call_type' ), true ) || is_array( $value ) ) {
				continue;
			}
			?>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( wp_unslash( $value ) ); ?>" />
			<?php
		}
		?>
		<label for="ssb-product-select"><?php esc_html_e( 'Product/Service', 'sales-script-builder' ); ?></label>
		<select id="ssb-product-select" name="product_id" class="ssb-product-select">
			<option value=""><?php esc_html_e( '-- Select --', 'sales-script-builder' ); ?></option>
			<?php foreach ( $all_products as $product ) : ?>
				<option value="<?php echo esc_attr( $product->ID ); ?>" <?php selected( $product_id, $product->ID ); ?>>
					<?php echo esc_html( $product->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label for="ssb-call-type-select"><?php esc_html_e( 'Call Type', 'sales-script-builder' ); ?></label>
		<select id="ssb-call-type-select" name="call_type" class="ssb-call-type-select">
			<?php foreach ( $call_types as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $call_type, $slug ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button"><?php esc_html_e( 'Load Script', 'sales-script-builder' ); ?></button>
	</form>

	<?php
	// Only ever render a published ssb_product. Without this check, any post ID
	// could be passed in via the query string and its title/content would be
	// echoed below -- including private pages and other users' drafts.
	$product = $product_id ? get_post( $product_id ) : null;

	if ( $product && ( 'ssb_product' !== $product->post_type || 'publish' !== $product->post_status ) ) {
		$product = null;
	}
	?>

	<?php if ( $product_id && ! $product ) : ?>
		<p class="ssb-error"><?php esc_html_e( 'That product/service could not be found.', 'sales-script-builder' ); ?></p>
	<?php endif; ?>

	<?php if ( $product && $call_type && isset( $call_types[ $call_type ] ) ) : ?>

		<?php
		$pain_points    = SSB_Meta_Fields::get_pain_points( $product_id );
		$competitors    = SSB_Meta_Fields::get_competitors( $product_id );
		$objections     = SSB_Meta_Fields::get_objections( $product_id );
		$upsell_paths   = SSB_Meta_Fields::get_upsell_paths( $product_id );
		$active_specials = SSB_Meta_Fields::get_active_specials( $product_id );
		$is_favorited    = SSB_Favorites::is_favorited( $current_user_id, $product_id, $call_type );
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

			<?php // Specials auto-inject, positioned first for upsell scripts. ?>
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

				<section class="ssb-section">
					<h3><?php esc_html_e( 'Overview', 'sales-script-builder' ); ?></h3>
					<div><?php echo wp_kses_post( $product->post_content ); ?></div>
				</section>
			<?php endif; ?>

			<?php // Specials for non-upsell scripts appear near pricing/overview instead of first. ?>
			<?php if ( 'upsell' !== $call_type && ! empty( $active_specials ) ) : ?>
				<?php foreach ( $active_specials as $special ) : ?>
					<div class="ssb-special-banner" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-special-id="<?php echo esc_attr( $special->ID ); ?>">
						<strong><?php echo esc_html( $special->post_title ); ?></strong>
						<p><?php echo wp_kses_post( $special->post_content ); ?></p>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( ! empty( $competitors ) ) : ?>
				<section class="ssb-section">
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

			<?php if ( ! empty( $objections ) ) : ?>
				<section class="ssb-section">
					<h3><?php esc_html_e( 'Objection Handling', 'sales-script-builder' ); ?></h3>
					<?php foreach ( $objections as $row ) : ?>
						<div class="ssb-objection">
							<p class="ssb-objection-text"><em>&ldquo;<?php echo esc_html( $row['objection'] ?? '' ); ?>&rdquo;</em></p>
							<p><?php echo esc_html( $row['response'] ?? '' ); ?></p>
						</div>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<?php if ( 'upsell' === $call_type && ! empty( $upsell_paths ) ) : ?>
				<section class="ssb-section">
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

	<?php endif; ?>

</div>
