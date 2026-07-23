<?php
/**
 * Call Script tab for [ssb_app]. Just the picker -- the script output loads
 * via AJAX into #ssb-app-script-output once both fields are selected, so
 * switching product/call type never reloads the page.
 *
 * NOTE: this deliberately duplicates the picker markup from
 * templates/script-view.php rather than sharing it, since the standalone
 * [ssb_script_builder] page (GET-driven) and this SPA tab (AJAX-driven)
 * need different submit behavior. templates/app-script-output.php mirrors
 * script-view.php's script-output section the same way, for the same
 * reason. This duplication is intentional and temporary -- the whole
 * Call Script content structure is getting rebuilt in the next pass (see
 * the SPA spec doc's Section 3.3, tappable pain points + mutable overview
 * highlights), so unifying them now would be short-lived work.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

	<form class="ssb-picker" id="ssb-app-picker">
		<label for="ssb-app-product-select"><?php esc_html_e( 'Product/Service', 'sales-script-builder' ); ?></label>
		<select id="ssb-app-product-select" name="product_id" class="ssb-product-select">
			<option value=""><?php esc_html_e( '-- Select --', 'sales-script-builder' ); ?></option>
			<?php foreach ( $all_products as $product ) : ?>
				<option value="<?php echo esc_attr( $product->ID ); ?>">
					<?php echo esc_html( $product->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label for="ssb-app-call-type-select"><?php esc_html_e( 'Call Type', 'sales-script-builder' ); ?></label>
		<select id="ssb-app-call-type-select" name="call_type" class="ssb-call-type-select">
			<?php foreach ( $call_types as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>">
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button"><?php esc_html_e( 'Load Script', 'sales-script-builder' ); ?></button>
	</form>

	<div id="ssb-app-script-output"></div>

</div>
