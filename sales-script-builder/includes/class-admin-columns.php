<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds useful custom columns to the Products/Services and Specials/Discounts
 * admin list tables, so it's easy to scan content at a glance once there's
 * more than a handful of entries. Price and Start Date are sortable.
 */
class SSB_Admin_Columns {

	public function __construct() {
		// Products/Services list table.
		add_filter( 'manage_ssb_product_posts_columns', array( $this, 'add_product_columns' ) );
		add_action( 'manage_ssb_product_posts_custom_column', array( $this, 'render_product_column' ), 10, 2 );
		add_filter( 'manage_edit-ssb_product_sortable_columns', array( $this, 'sortable_product_columns' ) );

		// Specials/Discounts list table.
		add_filter( 'manage_ssb_special_posts_columns', array( $this, 'add_special_columns' ) );
		add_action( 'manage_ssb_special_posts_custom_column', array( $this, 'render_special_column' ), 10, 2 );
		add_filter( 'manage_edit-ssb_special_sortable_columns', array( $this, 'sortable_special_columns' ) );

		// Make the "Price" and "Start Date" sort clicks actually sort by that meta value.
		add_action( 'pre_get_posts', array( $this, 'handle_sorting' ) );
	}

	/* ---------------------------------------------------------------
	 * PRODUCTS/SERVICES COLUMNS
	 * ------------------------------------------------------------- */

	public function add_product_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			// Insert our columns right after the title column.
			if ( 'title' === $key ) {
				$new_columns['ssb_category']     = __( 'Category', 'sales-script-builder' );
				$new_columns['ssb_price']        = __( 'Price', 'sales-script-builder' );
				$new_columns['ssb_pain_points']  = __( 'Pain Points', 'sales-script-builder' );
				$new_columns['ssb_objections']   = __( 'Objections', 'sales-script-builder' );
				$new_columns['ssb_upsell']       = __( 'Upsell Path', 'sales-script-builder' );
			}
		}

		return $new_columns;
	}

	public function render_product_column( string $column, int $post_id ): void {
		switch ( $column ) {

			case 'ssb_category':
				$terms = get_the_terms( $post_id, 'ssb_category' );
				if ( is_array( $terms ) && ! empty( $terms ) ) {
					echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
				} else {
					echo '&#8212;';
				}
				break;

			case 'ssb_price':
				$price = get_post_meta( $post_id, '_ssb_price', true );
				echo $price ? esc_html( $price ) : '&#8212;';
				break;

			case 'ssb_pain_points':
				$rows = SSB_Meta_Fields::get_pain_points( $post_id );
				echo esc_html( (string) count( $rows ) );
				break;

			case 'ssb_objections':
				$rows = SSB_Meta_Fields::get_objections( $post_id );
				echo esc_html( (string) count( $rows ) );
				break;

			case 'ssb_upsell':
				$paths = SSB_Meta_Fields::get_upsell_paths( $post_id );
				if ( empty( $paths ) ) {
					echo '&#8212;';
					break;
				}
				$labels = array();
				foreach ( $paths as $row ) {
					if ( ! empty( $row['next_product_id'] ) ) {
						$next = get_post( $row['next_product_id'] );
						if ( $next ) {
							$labels[] = $next->post_title;
						}
					}
				}
				echo $labels ? esc_html( implode( ', ', $labels ) ) : '&#8212;';
				break;
		}
	}

	public function sortable_product_columns( array $columns ): array {
		$columns['ssb_price'] = 'ssb_price';
		return $columns;
	}

	/* ---------------------------------------------------------------
	 * SPECIALS/DISCOUNTS COLUMNS
	 * ------------------------------------------------------------- */

	public function add_special_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$new_columns['ssb_status']      = __( 'Status', 'sales-script-builder' );
				$new_columns['ssb_dates']       = __( 'Date Range', 'sales-script-builder' );
				$new_columns['ssb_applicable']  = __( 'Applies To', 'sales-script-builder' );
			}
		}

		return $new_columns;
	}

	public function render_special_column( string $column, int $post_id ): void {
		switch ( $column ) {

			case 'ssb_status':
				echo wp_kses_post( $this->get_status_badge( $post_id ) );
				break;

			case 'ssb_dates':
				$start = get_post_meta( $post_id, '_ssb_start_date', true );
				$end   = get_post_meta( $post_id, '_ssb_end_date', true );
				if ( ! $start && ! $end ) {
					echo '&#8212;';
					break;
				}
				echo esc_html( ( $start ? $start : '?' ) . ' &rarr; ' . ( $end ? $end : '?' ) );
				break;

			case 'ssb_applicable':
				$product_ids = get_post_meta( $post_id, '_ssb_applicable_products', true );
				$product_ids = is_array( $product_ids ) ? $product_ids : array();
				if ( empty( $product_ids ) ) {
					echo '&#8212;';
					break;
				}
				$titles = array();
				foreach ( $product_ids as $product_id ) {
					$product = get_post( $product_id );
					if ( $product ) {
						$titles[] = $product->post_title;
					}
				}
				echo esc_html( implode( ', ', $titles ) );
				break;
		}
	}

	public function sortable_special_columns( array $columns ): array {
		$columns['ssb_dates'] = 'ssb_start_date';
		return $columns;
	}

	/**
	 * Returns a colored badge: Active (green), Upcoming (blue), or Expired (gray),
	 * based on today's date vs. the special's start/end range.
	 */
	private function get_status_badge( int $post_id ): string {
		$start = get_post_meta( $post_id, '_ssb_start_date', true );
		$end   = get_post_meta( $post_id, '_ssb_end_date', true );
		$today = current_time( 'Y-m-d' );

		if ( ! $start || ! $end ) {
			return '<span style="color:#888;">' . esc_html__( 'No dates set', 'sales-script-builder' ) . '</span>';
		}

		if ( $today < $start ) {
			return '<span style="color:#2271b1;font-weight:600;">' . esc_html__( 'Upcoming', 'sales-script-builder' ) . '</span>';
		}

		if ( $today > $end ) {
			return '<span style="color:#888;">' . esc_html__( 'Expired', 'sales-script-builder' ) . '</span>';
		}

		return '<span style="color:#008a20;font-weight:600;">' . esc_html__( 'Active', 'sales-script-builder' ) . '</span>';
	}

	/* ---------------------------------------------------------------
	 * SORTING
	 * ------------------------------------------------------------- */

	public function handle_sorting( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'ssb_price' === $orderby ) {
			$query->set( 'meta_key', '_ssb_price' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'ssb_start_date' === $orderby ) {
			$query->set( 'meta_key', '_ssb_start_date' );
			$query->set( 'orderby', 'meta_value' );
		}
	}
}
