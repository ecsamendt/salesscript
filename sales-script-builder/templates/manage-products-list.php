<?php
/**
 * Front-end products list -- rendered as an HTML fragment, used both for
 * the initial [ssb_app] page load and for AJAX refreshes after a save or
 * delete. No $_GET dependency: this template only ever reads $products,
 * built by the caller (SSB_Frontend_Editor::render_list_fragment()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ssb-manage-list" data-manage="products">
	<div class="ssb-manage-header">
		<h2><?php esc_html_e( 'Manage Products/Services', 'sales-script-builder' ); ?></h2>
		<button type="button" class="button ssb-add-new-btn" data-manage-action="new"><?php esc_html_e( '+ Add New Product', 'sales-script-builder' ); ?></button>
	</div>

	<div class="ssb-manage-list-body">
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
							<button type="button" class="ssb-link-btn ssb-edit-btn" data-manage-action="edit" data-id="<?php echo esc_attr( $product->ID ); ?>">
								<?php esc_html_e( 'Edit', 'sales-script-builder' ); ?>
							</button>
							<button type="button" class="ssb-link-btn ssb-delete-btn" data-manage-action="delete" data-id="<?php echo esc_attr( $product->ID ); ?>">
								<?php esc_html_e( 'Delete', 'sales-script-builder' ); ?>
							</button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<div class="ssb-manage-form-body" hidden></div>
</div>
