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
 * Remove any plugin-level options, if added in the future.
 * (No options are currently registered, but this is a placeholder so
 * whoever adds one later remembers to clean it up here too.)
 */
// delete_option( 'ssb_some_future_option' );
