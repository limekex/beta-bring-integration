<?php
declare(strict_types=1);

namespace BeTA\Bring\Admin\Email;

use BeTA\Bring\Model\SettingsModel;

/**
 * WooCommerce email notification sent to the customer when a Bring shipment
 * is booked for their order.
 *
 * Triggered by the `bbi_shipment_booked` action which is fired from
 * OrderMetaBox::ajax_book_order() and BulkBooking::handle_bulk_action()
 * immediately after a successful booking.
 *
 * The email includes:
 *  - Optional sender logo (configured in plugin settings → Sender logo URL)
 *  - Consignment / tracking number
 *  - Clickable tracking link (when available)
 *  - The Bring service name (from the preset label or service ID)
 *  - A plain-text fallback for email clients that prefer it
 */
class BookingEmail extends \WC_Email {

	/** @var array Booking result data (consignment_no, tracking_url, service_id, …) */
	public array $booking_data = [];

	public function __construct() {
		$this->id             = 'bbi_shipment_booked';
		$this->title          = __( 'Bring shipment booked', 'bbi' );
		$this->description    = __( 'Notification sent to the customer when a Bring shipment is booked for their order. Contains the tracking link and consignment number.', 'bbi' );
		$this->template_html  = 'emails/bbi-shipment-booked.php';
		$this->template_plain = 'emails/plain/bbi-shipment-booked.php';
		$this->template_base  = BBI_DIR . '/templates/';
		$this->customer_email = true;

		// Default subject / heading — translators may use {order_number} placeholder.
		$this->subject = $this->get_default_subject();
		$this->heading = $this->get_default_heading();

		parent::__construct();

		// Listen for our custom action.
		add_action( 'bbi_shipment_booked', [ $this, 'trigger' ], 10, 2 );
	}

	public function get_default_subject(): string {
		/* translators: {order_number} is replaced with the WooCommerce order number */
		return __( 'Your order #{order_number} has been shipped', 'bbi' );
	}

	public function get_default_heading(): string {
		return __( 'Your order is on its way!', 'bbi' );
	}

	/**
	 * Trigger the email send.
	 *
	 * @param \WC_Order $order        The WooCommerce order.
	 * @param array     $booking_data Booking result array from BookingResult::to_array().
	 */
	public function trigger( \WC_Order $order, array $booking_data ): void {
		$this->setup_locale();

		$this->object       = $order;
		$this->booking_data = $booking_data;
		$this->recipient    = $order->get_billing_email();

		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);
		}

		$this->restore_locale();
	}

	public function get_content_html(): string {
		$settings = new SettingsModel();

		return wc_get_template_html(
			$this->template_html,
			[
				'order'          => $this->object,
				'booking_data'   => $this->booking_data,
				'email_heading'  => $this->get_heading(),
				'sender_logo'    => esc_url( $settings->get_sender_logo_url() ),
				'email'          => $this,
				'sent_to_admin'  => false,
				'plain_text'     => false,
			],
			'',
			$this->template_base
		);
	}

	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'          => $this->object,
				'booking_data'   => $this->booking_data,
				'email_heading'  => $this->get_heading(),
				'email'          => $this,
				'sent_to_admin'  => false,
				'plain_text'     => true,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Return extra settings shown in WooCommerce → Settings → Emails.
	 *
	 * @return array
	 */
	public function get_extra_form_fields(): array {
		return [];
	}
}
