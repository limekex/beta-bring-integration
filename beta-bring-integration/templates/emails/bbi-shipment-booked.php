<?php
/**
 * Bring shipment booked – HTML email template.
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/bbi-shipment-booked.php
 *
 * @var \WC_Order $order
 * @var array     $booking_data  Keys: consignment_no, tracking_url, service_id, booked_at
 * @var string    $email_heading
 * @var string    $sender_logo   Full URL to the sender logo, or empty string.
 * @var \WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() – outputs the email header / wrapper.
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php if ( $sender_logo ) : ?>
<p style="text-align:center;margin-bottom:20px;">
	<img src="<?php echo esc_url( $sender_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
		style="max-height:60px;width:auto;" />
</p>
<?php endif; ?>

<p><?php
	printf(
		/* translators: %s: customer first name */
		esc_html__( 'Hi %s,', 'bbi' ),
		esc_html( $order->get_billing_first_name() )
	);
?></p>

<p><?php esc_html_e( 'Great news — your order has been picked up and is on its way to you via Bring!', 'bbi' ); ?></p>

<h2 style="color:#333;font-size:18px;margin:24px 0 8px;"><?php esc_html_e( 'Shipment details', 'bbi' ); ?></h2>

<table cellspacing="0" cellpadding="6" border="0"
	style="width:100%;border-collapse:collapse;margin-bottom:20px;">
	<tbody>
		<tr>
			<td style="padding:8px 0;border-bottom:1px solid #eee;font-weight:bold;width:40%;">
				<?php esc_html_e( 'Order number', 'bbi' ); ?>
			</td>
			<td style="padding:8px 0;border-bottom:1px solid #eee;">
				<?php echo esc_html( $order->get_order_number() ); ?>
			</td>
		</tr>
		<tr>
			<td style="padding:8px 0;border-bottom:1px solid #eee;font-weight:bold;">
				<?php esc_html_e( 'Consignment number', 'bbi' ); ?>
			</td>
			<td style="padding:8px 0;border-bottom:1px solid #eee;">
				<?php echo esc_html( $booking_data['consignment_no'] ?? '' ); ?>
			</td>
		</tr>
		<?php if ( ! empty( $booking_data['service_id'] ) ) : ?>
		<tr>
			<td style="padding:8px 0;border-bottom:1px solid #eee;font-weight:bold;">
				<?php esc_html_e( 'Shipping service', 'bbi' ); ?>
			</td>
			<td style="padding:8px 0;border-bottom:1px solid #eee;">
				<?php echo esc_html( $booking_data['service_id'] ); ?>
			</td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php if ( ! empty( $booking_data['tracking_url'] ) ) : ?>
<p style="text-align:center;margin:28px 0;">
	<a href="<?php echo esc_url( $booking_data['tracking_url'] ); ?>"
		style="background:#007cba;color:#fff;padding:12px 28px;border-radius:4px;
			text-decoration:none;font-weight:bold;display:inline-block;">
		<?php esc_html_e( 'Track your package', 'bbi' ); ?>
	</a>
</p>
<?php endif; ?>

<p><?php esc_html_e( 'If you have any questions about your delivery, please reply to this email or use your tracking link above.', 'bbi' ); ?></p>

<?php
/*
 * @hooked WC_Emails::email_order_details() – outputs order details table.
 * @hooked WC_Emails::email_footer()        – outputs the email footer / wrapper.
 */
do_action( 'woocommerce_email_order_details', $order, false, false, $email );
do_action( 'woocommerce_email_footer', $email );
