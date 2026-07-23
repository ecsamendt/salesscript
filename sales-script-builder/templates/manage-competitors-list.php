<?php
/**
 * Front-end competitors list -- HTML fragment. Expects $competitors to be
 * set by the caller (SSB_Frontend_Competitors::render_list_fragment()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ssb-manage-list" data-manage="competitors">
	<div class="ssb-manage-header">
		<h2><?php esc_html_e( 'Manage Competitors', 'sales-script-builder' ); ?></h2>
		<button type="button" class="button ssb-add-new-btn" data-manage-action="new"><?php esc_html_e( '+ Add New Competitor', 'sales-script-builder' ); ?></button>
	</div>

	<div class="ssb-manage-list-body">
		<?php if ( empty( $competitors ) ) : ?>
			<p class="ssb-empty-state"><?php esc_html_e( 'No competitors yet. Add your first one above.', 'sales-script-builder' ); ?></p>
		<?php else : ?>
			<ul class="ssb-manage-products">
				<?php foreach ( $competitors as $competitor ) : ?>
					<li class="ssb-manage-product-item">
						<span class="ssb-manage-product-title"><?php echo esc_html( $competitor->post_title ); ?></span>
						<span class="ssb-manage-product-actions">
							<button type="button" class="ssb-link-btn ssb-edit-btn" data-manage-action="edit" data-id="<?php echo esc_attr( $competitor->ID ); ?>">
								<?php esc_html_e( 'Edit', 'sales-script-builder' ); ?>
							</button>
							<button type="button" class="ssb-link-btn ssb-delete-btn" data-manage-action="delete" data-id="<?php echo esc_attr( $competitor->ID ); ?>">
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
