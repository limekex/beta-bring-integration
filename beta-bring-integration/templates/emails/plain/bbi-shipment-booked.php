<?php
/**
 * Bring shipment booked – plain-text email template.
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/plain/bbi-shipment-booked.php
 *
 * @var \WC_Order $order
 * @var array     $booking_data  Keys: consignment_no, tracking_url, service_id, booked_at
 * @var string    $email_heading
 * @var \WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html( $email_heading ) . "\n\n";

echo sprintf(
	/* translators: %s: customer first name */
	esc_html__( 'Hi %s,', 'bbi' ),
	esc_html( $order->get_billing_first_name() )
) . "\n\n";

echo esc_html__( 'Great news — your order has been picked up and is on its way to you via Bring!', 'bbi' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__( 'SHIPMENT DETAILS', 'bbi' ) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf(
	/* translators: %s: order number */
	esc_html__( 'Order number: %s', 'bbi' ),
	$order->get_order_number()
) . "\n";

echo sprintf(
	/* translators: %s: consignment number */
	esc_html__( 'Consignment number: %s', 'bbi' ),
	$booking_data['consignment_no'] ?? ''
) . "\n";

if ( ! empty( $booking_data['service_id'] ) ) {
	echo sprintf(
		/* translators: %s: shipping service name */
		esc_html__( 'Shipping service: %s', 'bbi' ),
		$booking_data['service_id']
	) . "\n";
}

if ( ! empty( $booking_data['tracking_url'] ) ) {
	echo "\n" . esc_html__( 'Track your package:', 'bbi' ) . "\n";
	echo esc_url( $booking_data['tracking_url'] ) . "\n";
}

echo "\n" . esc_html__( 'If you have any questions about your delivery, please reply to this email or use your tracking link above.', 'bbi' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

do_action( 'woocommerce_email_order_details', $order, false, true, $email );
