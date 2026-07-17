<?php
/**
 * Front-end products list. Included by SSB_Frontend_Editor::render_manager()
 * when no ssb_action=edit/new is present in the query string.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$manage_url = home_url( '/' . SSB_Settings::get_manage_slug() . '/' );

$products = get_posts(
	array(
		'post_type'      => 'ssb_product',
		'post_status'    => array( 'publish', 'draft' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>
<div class="ssb-manage-list">
	<div class="ssb-manage-header">
		<h2><?php esc_html_e( 'Manage Products/Services', 'sales-script-builder' ); ?></h2>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'ssb_action', 'new', $manage_url ) ); ?>">
			<?php esc_html_e( '+ Add New Product', 'sales-script-builder' ); ?>
		</a>
	</div>

	<?php if ( empty( $products ) ) : ?>
		<p class="ssb-empty-state"><?php esc_html_e( 'No products yet. Add your first one above.', 'sales-script-builder' ); ?></p>
	<?php else : ?>
		<ul class="ssb-manage-products">
			<?php foreach ( $products as $product ) : ?>
				<li class="ssb-manage-product-item">
					<span class="ssb-manage-product-title">
						<?php echo esc_html( $product->post_title ); ?>
						<?php if ( 'draft' === $product->post_status ) : ?>
							<em class="ssb-draft-tag"><?php esc_html_e( '(draft)', 'sales-script-builder' ); ?></em>
						<?php endif; ?>
					</span>
					<span class="ssb-manage-product-actions">
						<a href="<?php echo esc_url( add_query_arg( array( 'ssb_action' => 'edit', 'product_id' => $product->ID ), $manage_url ) ); ?>">
							<?php esc_html_e( 'Edit', 'sales-script-builder' ); ?>
						</a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssb-inline-delete" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this product? This can be undone from wp-admin.', 'sales-script-builder' ) ); ?>');">
							<?php wp_nonce_field( 'ssb_delete_product_frontend', 'ssb_frontend_delete_nonce' ); ?>
							<input type="hidden" name="action" value="ssb_delete_product_frontend" />
							<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->ID ); ?>" />
							<button type="submit" class="ssb-link-btn"><?php esc_html_e( 'Delete', 'sales-script-builder' ); ?></button>
						</form>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
