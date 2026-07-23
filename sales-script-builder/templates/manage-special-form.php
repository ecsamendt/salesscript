<?php
/**
 * Front-end special/discount create/edit form -- HTML fragment. Expects
 * $special_id (int, 0 for new) set by the caller. Reuses
 * render_special_details() which already has start date, end date, terms,
 * and applicable products.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing = $special_id > 0;

if ( $is_editing ) {
	$special = get_post( $special_id );
	if ( ! $special || 'ssb_special' !== $special->post_type ) {
		echo '<p class="ssb-error">' . esc_html__( 'That special could not be found.', 'sales-script-builder' ) . '</p>';
		return;
	}
} else {
	$special = new WP_Post( (object) array( 'ID' => 0 ) );
}
?>
<div class="ssb-manage-form" data-form="special">
	<button type="button" class="ssb-link-btn ssb-back-to-list-btn">&larr; <?php esc_html_e( 'Back to all specials', 'sales-script-builder' ); ?></button>

	<h2><?php echo $is_editing ? esc_html__( 'Edit Special/Discount', 'sales-script-builder' ) : esc_html__( 'Add New Special/Discount', 'sales-script-builder' ); ?></h2>

	<div class="ssb-form-notice" hidden></div>

	<form class="ssb-ajax-form" data-ajax-action="ssb_save_special">
		<?php wp_nonce_field( 'ssb_app_nonce', 'ssb_nonce' ); ?>
		<input type="hidden" name="special_id" value="<?php echo esc_attr( $special->ID ); ?>" />

		<p>
			<label for="ssb-special-title-<?php echo esc_attr( $special->ID ); ?>"><?php esc_html_e( 'Special/Discount Name', 'sales-script-builder' ); ?></label><br />
			<input type="text" id="ssb-special-title-<?php echo esc_attr( $special->ID ); ?>" name="post_title" class="widefat" required value="<?php echo esc_attr( $special->post_title ?? '' ); ?>" />
		</p>

		<p>
			<label for="ssb-special-content-<?php echo esc_attr( $special->ID ); ?>"><?php esc_html_e( 'Description', 'sales-script-builder' ); ?></label><br />
			<textarea id="ssb-special-content-<?php echo esc_attr( $special->ID ); ?>" name="post_content" class="widefat" rows="3"><?php echo esc_textarea( $special->post_content ?? '' ); ?></textarea>
		</p>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Dates, Terms &amp; Applicable Products', 'sales-script-builder' ); ?></h3>
			<?php SSB_Meta_Fields::render_special_details( $special ); ?>
		</div>

		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Special', 'sales-script-builder' ); ?></button>
		</p>
	</form>
</div>
