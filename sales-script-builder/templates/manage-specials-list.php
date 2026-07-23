<?php
/**
 * Front-end specials list -- HTML fragment. Expects $specials to be set by
 * the caller (SSB_Frontend_Specials::render_list_fragment()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ssb-manage-list" data-manage="specials">
	<div class="ssb-manage-header">
		<h2><?php esc_html_e( 'Manage Specials/Discounts', 'sales-script-builder' ); ?></h2>
		<button type="button" class="button ssb-add-new-btn" data-manage-action="new"><?php esc_html_e( '+ Add New Special', 'sales-script-builder' ); ?></button>
	</div>

	<div class="ssb-manage-list-body">
		<?php if ( empty( $specials ) ) : ?>
			<p class="ssb-empty-state"><?php esc_html_e( 'No specials yet. Add your first one above.', 'sales-script-builder' ); ?></p>
		<?php else : ?>
			<ul class="ssb-manage-products">
				<?php foreach ( $specials as $special ) :
					$start = get_post_meta( $special->ID, '_ssb_start_date', true );
					$end   = get_post_meta( $special->ID, '_ssb_end_date', true );
					?>
					<li class="ssb-manage-product-item">
						<span class="ssb-manage-product-title">
							<?php echo esc_html( $special->post_title ); ?>
							<?php echo wp_kses_post( SSB_Admin_Columns::get_status_badge( $special->ID ) ); ?>
							<?php if ( $start || $end ) : ?>
								<span class="ssb-special-dates"><?php echo esc_html( ( $start ? $start : '?' ) . ' - ' . ( $end ? $end : '?' ) ); ?></span>
							<?php endif; ?>
						</span>
						<span class="ssb-manage-product-actions">
							<button type="button" class="ssb-link-btn ssb-edit-btn" data-manage-action="edit" data-id="<?php echo esc_attr( $special->ID ); ?>">
								<?php esc_html_e( 'Edit', 'sales-script-builder' ); ?>
							</button>
							<button type="button" class="ssb-link-btn ssb-delete-btn" data-manage-action="delete" data-id="<?php echo esc_attr( $special->ID ); ?>">
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
