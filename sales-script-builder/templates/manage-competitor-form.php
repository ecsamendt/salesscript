<?php
/**
 * Front-end competitor create/edit form -- HTML fragment. Expects
 * $competitor_id (int, 0 for new) set by the caller.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing = $competitor_id > 0;

if ( $is_editing ) {
	$competitor = get_post( $competitor_id );
	if ( ! $competitor || 'ssb_competitor' !== $competitor->post_type ) {
		echo '<p class="ssb-error">' . esc_html__( 'That competitor could not be found.', 'sales-script-builder' ) . '</p>';
		return;
	}
} else {
	$competitor = new WP_Post( (object) array( 'ID' => 0 ) );
}
?>
<div class="ssb-manage-form" data-form="competitor">
	<button type="button" class="ssb-link-btn ssb-back-to-list-btn">&larr; <?php esc_html_e( 'Back to all competitors', 'sales-script-builder' ); ?></button>

	<h2><?php echo $is_editing ? esc_html__( 'Edit Competitor', 'sales-script-builder' ) : esc_html__( 'Add New Competitor', 'sales-script-builder' ); ?></h2>

	<div class="ssb-form-notice" hidden></div>

	<form class="ssb-ajax-form" data-ajax-action="ssb_save_competitor">
		<?php wp_nonce_field( 'ssb_app_nonce', 'ssb_nonce' ); ?>
		<input type="hidden" name="competitor_id" value="<?php echo esc_attr( $competitor->ID ); ?>" />

		<p>
			<label for="ssb-competitor-title-<?php echo esc_attr( $competitor->ID ); ?>"><?php esc_html_e( 'Competitor Name', 'sales-script-builder' ); ?></label><br />
			<input type="text" id="ssb-competitor-title-<?php echo esc_attr( $competitor->ID ); ?>" name="post_title" class="widefat" required value="<?php echo esc_attr( $competitor->post_title ?? '' ); ?>" />
		</p>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Their Pros', 'sales-script-builder' ); ?></h3>
			<?php SSB_Competitors::render_pros( $competitor ); ?>
		</div>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Their Cons', 'sales-script-builder' ); ?></h3>
			<?php SSB_Competitors::render_cons( $competitor ); ?>
		</div>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Counter Talking Points', 'sales-script-builder' ); ?></h3>
			<?php SSB_Competitors::render_counters( $competitor ); ?>
		</div>

		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Competitor', 'sales-script-builder' ); ?></button>
		</p>
	</form>
</div>
