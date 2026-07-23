<?php
/**
 * Front-end product create/edit form -- rendered as an HTML fragment.
 * Expects $product_id (int, 0 for new) to be set by the caller
 * (SSB_Frontend_Editor::render_form_fragment()). No $_GET dependency.
 *
 * Reuses SSB_Meta_Fields::render_pricing/render_pain_points/
 * render_overview_highlights/render_competitors/render_linked_competitors/
 * render_objections/render_upsell/render_internal_notes directly -- these
 * are the exact same static methods wp-admin uses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing = $product_id > 0;

if ( $is_editing ) {
	$product = get_post( $product_id );
	if ( ! $product || 'ssb_product' !== $product->post_type ) {
		echo '<p class="ssb-error">' . esc_html__( 'That product could not be found.', 'sales-script-builder' ) . '</p>';
		return;
	}
} else {
	// A stub, unsaved WP_Post so the same render_* methods work identically
	// for "new" as they do for "edit".
	$product = new WP_Post( (object) array( 'ID' => 0 ) );
}

$current_category_id = 0;
if ( $is_editing ) {
	$terms = get_the_terms( $product->ID, 'ssb_category' );
	if ( is_array( $terms ) && ! empty( $terms ) ) {
		$current_category_id = $terms[0]->term_id;
	}
}

$categories = get_terms(
	array(
		'taxonomy'   => 'ssb_category',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $categories ) ) {
	$categories = array();
}
?>
<div class="ssb-manage-form" data-form="product">
	<button type="button" class="ssb-link-btn ssb-back-to-list-btn">&larr; <?php esc_html_e( 'Back to all products', 'sales-script-builder' ); ?></button>

	<h2><?php echo $is_editing ? esc_html__( 'Edit Product', 'sales-script-builder' ) : esc_html__( 'Add New Product', 'sales-script-builder' ); ?></h2>

	<div class="ssb-form-notice" hidden></div>

	<form class="ssb-ajax-form" data-ajax-action="ssb_save_product">
		<?php wp_nonce_field( 'ssb_app_nonce', 'ssb_nonce' ); ?>
		<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->ID ); ?>" />

		<p>
			<label for="ssb-post-title-<?php echo esc_attr( $product->ID ); ?>"><?php esc_html_e( 'Product/Service Name', 'sales-script-builder' ); ?></label><br />
			<input type="text" id="ssb-post-title-<?php echo esc_attr( $product->ID ); ?>" name="post_title" class="widefat" required value="<?php echo esc_attr( $product->post_title ?? '' ); ?>" />
		</p>

		<p>
			<label for="ssb-category-<?php echo esc_attr( $product->ID ); ?>"><?php esc_html_e( 'Category', 'sales-script-builder' ); ?></label><br />
			<select id="ssb-category-<?php echo esc_attr( $product->ID ); ?>" name="ssb_category">
				<option value=""><?php esc_html_e( '-- None --', 'sales-script-builder' ); ?></option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $current_category_id, $category->term_id ); ?>>
						<?php echo esc_html( $category->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Pricing', 'sales-script-builder' ); ?></h3>
			<?php SSB_Meta_Fields::render_pricing( $product ); ?>
		</div>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Pain Points Solved', 'sales-script-builder' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Each pain point can include an acknowledgment/pivot script -- what the rep says when a customer names this specific issue.', 'sales-script-builder' ); ?></p>
			<?php SSB_Meta_Fields::render_pain_points( $product ); ?>
		</div>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Overview Highlights', 'sales-script-builder' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Short, individually-mentionable features -- shown to the rep alongside whichever pain point is active, as "on top of that, you also get..." material.', 'sales-script-builder' ); ?></p>
			<?php SSB_Meta_Fields::render_overview_highlights( $product ); ?>
		</div>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Competitor Comparisons', 'sales-script-builder' ); ?></h3>
			<?php SSB_Meta_Fields::render_competitors( $product ); ?>
		</div>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Linked Competitors (from library)', 'sales-script-builder' ); ?></h3>
			<?php SSB_Meta_Fields::render_linked_competitors( $product ); ?>
		</div>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Objection Handling', 'sales-script-builder' ); ?></h3>
			<?php SSB_Meta_Fields::render_objections( $product ); ?>
		</div>

		<?php if ( $is_editing ) : ?>
			<div class="ssb-manage-section">
				<h3><?php esc_html_e( 'Upsell / Next Path', 'sales-script-builder' ); ?></h3>
				<?php SSB_Meta_Fields::render_upsell( $product ); ?>
			</div>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Upsell paths can be added once this product is saved, since it needs to exist to be linked from another product.', 'sales-script-builder' ); ?></p>
		<?php endif; ?>

		<div class="ssb-manage-section">
			<h3><?php esc_html_e( 'Internal Notes', 'sales-script-builder' ); ?></h3>
			<?php SSB_Meta_Fields::render_internal_notes( $product ); ?>
		</div>

		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Product', 'sales-script-builder' ); ?></button>
		</p>
	</form>
</div>
