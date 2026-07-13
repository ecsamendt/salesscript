<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the two custom post types (Product/Service, Special/Discount)
 * and the Category taxonomy shared between them.
 */
class SSB_Post_Types {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	public function register_post_types(): void {

		register_post_type(
			'ssb_product',
			array(
				'label'        => __( 'Products/Services', 'sales-script-builder' ),
				'labels'       => array(
					'name'          => __( 'Products/Services', 'sales-script-builder' ),
					'singular_name' => __( 'Product/Service', 'sales-script-builder' ),
					'add_new_item'  => __( 'Add New Product/Service', 'sales-script-builder' ),
					'edit_item'     => __( 'Edit Product/Service', 'sales-script-builder' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-megaphone',
				'supports'     => array( 'title', 'editor' ),
				'has_archive'  => false,
				'rewrite'      => false,
				'show_in_rest' => true, // Needed for block-editor meta boxes / future REST use.
			)
		);

		register_post_type(
			'ssb_special',
			array(
				'label'        => __( 'Specials/Discounts', 'sales-script-builder' ),
				'labels'       => array(
					'name'          => __( 'Specials/Discounts', 'sales-script-builder' ),
					'singular_name' => __( 'Special/Discount', 'sales-script-builder' ),
					'add_new_item'  => __( 'Add New Special/Discount', 'sales-script-builder' ),
					'edit_item'     => __( 'Edit Special/Discount', 'sales-script-builder' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=ssb_product', // Nest under Products/Services menu.
				'supports'     => array( 'title', 'editor' ),
				'has_archive'  => false,
				'rewrite'      => false,
				'show_in_rest' => true,
			)
		);
	}

	public function register_taxonomies(): void {

		register_taxonomy(
			'ssb_category',
			array( 'ssb_product' ),
			array(
				'label'        => __( 'Categories', 'sales-script-builder' ),
				'hierarchical' => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'rewrite'      => false,
			)
		);
	}
}
