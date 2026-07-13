<?php
/**
 * "My Scripts" favorites dashboard. Included by
 * SSB_Shortcodes::render_favorites_dashboard().
 *
 * Links back into the main script-view page with product_id/call_type
 * pre-filled via query args. The page slug comes from SSB_Settings::get_slug()
 * (Sales Script Builder > Settings in wp-admin) -- no longer hardcoded here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
$favorites        = SSB_Favorites::get_favorites_for_user( $current_user_id );

$call_type_labels = SSB_Favorites::get_call_type_labels();
?>
<div class="ssb-favorites-dashboard">
	<h2><?php esc_html_e( 'My Scripts', 'sales-script-builder' ); ?></h2>

	<?php if ( empty( $favorites ) ) : ?>
		<p class="ssb-empty-state">
			<?php esc_html_e( 'You haven\'t favorited any scripts yet. Star a script while viewing it to add it here.', 'sales-script-builder' ); ?>
		</p>
	<?php else : ?>
		<ul class="ssb-favorites-list">
			<?php foreach ( $favorites as $favorite ) :
				$product = get_post( $favorite['product_id'] );

				if ( ! $product || 'ssb_product' !== $product->post_type ) {
					continue; // Product may have been deleted since favoriting.
				}

				$call_type_label = $call_type_labels[ $favorite['call_type'] ] ?? $favorite['call_type'];
				$script_url       = add_query_arg(
					array(
						'product_id' => $product->ID,
						'call_type'  => $favorite['call_type'],
					),
					home_url( '/' . SSB_Settings::get_slug() . '/' )
				);
				?>
				<li class="ssb-favorite-item">
					<a href="<?php echo esc_url( $script_url ); ?>">
						<span class="ssb-favorite-product"><?php echo esc_html( $product->post_title ); ?></span>
						<span class="ssb-favorite-call-type"><?php echo esc_html( $call_type_label ); ?></span>
					</a>
					<button type="button"
						class="ssb-favorite-btn is-favorited"
						data-product-id="<?php echo esc_attr( $product->ID ); ?>"
						data-call-type="<?php echo esc_attr( $favorite['call_type'] ); ?>"
						data-favorited="1"
						data-remove-on-toggle="true">
						&#9733;
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
