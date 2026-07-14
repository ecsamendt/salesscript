<?php
/**
 * Fires only when the plugin is deleted via the WordPress admin (not on
 * simple deactivation). Removes all data this plugin created so it doesn't
 * leave orphaned rows behind in the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * WordPress loads this file on its own -- the plugin's normal bootstrap never
 * runs, so nothing the plugin registers on 'init' exists right now. The post
 * types can still be queried directly, but get_terms() hard-fails with an
 * "Invalid taxonomy" WP_Error unless the taxonomy is registered first, which
 * would leave every ssb_category term orphaned in the database.
 */
register_taxonomy( 'ssb_category', array( 'ssb_product' ) );

/**
 * Delete all ssb_product and ssb_special posts (and their meta, via
 * wp_delete_post's built-in cleanup).
 */
$post_types = array( 'ssb_product', 'ssb_special' );

foreach ( $post_types as $post_type ) {
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);

	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true ); // true = force delete, skip trash.
	}
}

/**
 * Delete the ssb_category taxonomy terms.
 */
$terms = get_terms(
	array(
		'taxonomy'   => 'ssb_category',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'ssb_category' );
	}
}

/**
 * Delete favorites stored in user meta across all users.
 */
delete_metadata( 'user', 0, 'ssb_favorite_scripts', '', true );

/**
 * Remove plugin-level options.
 */
delete_option( 'ssb_script_view_slug' );
delete_option( 'ssb_enforce_membership' );
