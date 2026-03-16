<?php
namespace BeTA\Bring\Admin;

use BeTA\Bring\Woo\OrderData;

/**
 * Adds a "Bring Label" column to the WooCommerce orders list page.
 *
 * Supports both High-Performance Order Storage (HPOS, WC 7.1+)
 * and legacy post-based orders so the column is visible regardless
 * of which storage backend the store uses.
 */
class OrderListColumns {
	public static function init(): void {
		// HPOS orders list (woocommerce_page_wc-orders).
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ __CLASS__, 'add_column' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ __CLASS__, 'render_column_hpos' ], 10, 2 );

		// Legacy post-based orders list (edit.php?post_type=shop_order).
		add_filter( 'manage_shop_order_posts_columns', [ __CLASS__, 'add_column' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ __CLASS__, 'render_column_legacy' ], 10, 2 );
	}

	/**
	 * Append the Bring Label column header.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_column( array $columns ): array {
		$columns['bbi_label'] = __( 'Bring Label', 'bbi' );
		return $columns;
	}

	/**
	 * Render column for HPOS orders (callback receives WC_Order object).
	 *
	 * @param string    $column Column slug.
	 * @param \WC_Order $order  Current order object.
	 */
	public static function render_column_hpos( string $column, $order ): void {
		if ( 'bbi_label' !== $column ) {
			return;
		}
		self::render_cell( (int) $order->get_id() );
	}

	/**
	 * Render column for legacy post-based orders (callback receives post ID).
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post / order ID.
	 */
	public static function render_column_legacy( string $column, int $post_id ): void {
		if ( 'bbi_label' !== $column ) {
			return;
		}
		self::render_cell( $post_id );
	}

	/**
	 * Render the cell contents for an order.
	 *
	 * @param int $order_id
	 */
	private static function render_cell( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$consignment = $order->get_meta( OrderData::META_CONSIGNMENT );
		$label_url   = $order->get_meta( OrderData::META_LABEL_URL );
		$booked_at   = $order->get_meta( OrderData::META_BOOKED_AT );

		if ( $consignment ) {
			$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			$title = $booked_at
				/* translators: %s: booking date/time */
				? sprintf( __( 'Booked: %s', 'bbi' ), wp_date( $date_format, strtotime( $booked_at ) ) )
				: '';

			echo '<span class="bbi-consignment-no" title="' . esc_attr( $title ) . '">'
				. esc_html( $consignment )
				. '</span>';

			if ( $label_url ) {
				echo '<br><a href="' . esc_url( $label_url ) . '" target="_blank" class="bbi-label-link button button-small">'
					. esc_html__( 'Label (PDF)', 'bbi' )
					. '</a>';
			}
		} else {
			echo '<span class="bbi-not-booked">—</span>';
		}
	}
}
