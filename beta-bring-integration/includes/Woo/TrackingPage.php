<?php
declare( strict_types=1 );

namespace BeTA\Bring\Woo;

use BeTA\Bring\API\TrackingService;
use BeTA\Bring\Model\SettingsModel;

/**
 * Adds a "Shipment tracking" tab to the WooCommerce My Account → View Order page.
 *
 * Shows live tracking events fetched from the Bring Tracking API v2.
 */
class TrackingPage {
	public static function init(): void {
		// Add a tracking section to the order detail page in My Account.
		add_action( 'woocommerce_order_details_after_order_table', [ __CLASS__, 'render_tracking_section' ] );
	}

	/**
	 * Render the tracking section below the order table on My Account → View Order.
	 *
	 * @param \WC_Order $order
	 */
	public static function render_tracking_section( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$consignment_no = $order->get_meta( OrderData::META_CONSIGNMENT );
		if ( ! $consignment_no ) {
			return;
		}

		$tracking_url = $order->get_meta( OrderData::META_TRACKING_URL );
		$order_id     = $order->get_id();

		?>
		<section class="bbi-tracking-section">
			<h2><?php esc_html_e( 'Shipment tracking', 'bbi' ); ?></h2>

			<div class="bbi-tracking-summary">
				<p>
					<?php
					printf(
						/* translators: %s: consignment number */
						esc_html__( 'Tracking number: %s', 'bbi' ),
						'<strong>' . esc_html( $consignment_no ) . '</strong>'
					);
					?>
				</p>

				<?php if ( $tracking_url ) : ?>
					<p>
						<a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank" rel="noopener" class="bbi-tracking-external-link">
							<?php esc_html_e( 'Track on Bring.com', 'bbi' ); ?> &rarr;
						</a>
					</p>
				<?php endif; ?>
			</div>

			<div id="bbi-tracking-events"
				 data-order-id="<?php echo esc_attr( (string) $order_id ); ?>"
				 data-rest-url="<?php echo esc_url( rest_url( 'bbi/v1' ) ); ?>"
				 data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
				<p class="bbi-tracking-loading"><?php esc_html_e( 'Loading tracking information...', 'bbi' ); ?></p>
			</div>
		</section>
		<?php
	}
}
