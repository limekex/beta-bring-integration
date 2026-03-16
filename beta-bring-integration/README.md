BeTA Bring Integration
======================

Overview
--------

This plugin integrates WooCommerce with Bring (Mybring) shipping APIs to create consignments, fetch labels, look up pickup points, validate postal codes, and query shipping guide rates. It supports admin workflows: configure credentials, define service "presets", book from the shop order screen (single and bulk), and check label availability via REST routes.

Goals
-----
- Minimal dependencies (no Composer)
- PHP 8.1+, WP 6.3+, WooCommerce 8+
- WooCommerce HPOS (High-Performance Order Storage) compatible
- Clear, documented code using namespace BeTA\Bring and text domain `bbi`

Architecture (ASCII)
--------------------

 plugin bootstrap
  └─ includes/Autoloader.php
  └─ includes/Plugin.php
      ├─ Admin/Settings.php
      ├─ Admin/OrderMetaBox.php
      ├─ Admin/OrderListColumns.php
      ├─ Woo/BulkBooking.php
      ├─ API/Client.php
      ├─ API/BookingService.php
      ├─ API/ShippingGuideService.php
      ├─ API/PickupPointService.php
      ├─ API/PostalCodeService.php
      ├─ API/CustomerService.php
      ├─ API/Routes.php
      └─ Model/SettingsModel.php

REST Endpoints (all require manage_woocommerce capability)
----------------------------------------------------------
- GET /wp-json/bbi/v1/order/{id}/label
  Returns stored label_url and tracking_url for an order.
  Optional ?refresh=1 to re-verify the label is still reachable.

- GET /wp-json/bbi/v1/shipping-guide?fromPostalCode=0101&toPostalCode=5003
  Returns available shipping products from the Bring Shipping Guide API v2.
  Optional: fromcountrycode, tocountrycode, weightInGrams, volumeInDm3, language

- GET /wp-json/bbi/v1/pickup-points/NO/0101
  Returns pickup points near a postal code (Bring Pickup Point API).
  Optional: ?max=10 (1–50)

- GET /wp-json/bbi/v1/postal-code/NO/0101
  Returns city / municipality / county for a postal code (Bring Address API).

- GET /wp-json/bbi/v1/customer-settings
  Returns Mybring user settings including available customer numbers.

Development
-----------
- Install the plugin into a WP dev site with WooCommerce activated.
- Use Test mode to avoid real API calls. If test mode enabled and credentials missing
  the plugin returns fake booking responses.

HPOS Compatibility
------------------
The plugin declares compatibility with WooCommerce High-Performance Order Storage.
Order meta is persisted via WC_Order::update_meta_data() / WC_Order::save()
and read via WC_Order::get_meta() for full HPOS support.

Preset with Pickup Point
------------------------
Add `"requiresPickupPoint": true` to a preset definition so the order meta box
automatically loads matching pickup points for the recipient postal code:

  "hent_i_butikk": {
    "label": "Hent i butikk",
    "serviceId": "SERVICEPAKKE",
    "requiresPickupPoint": true,
    "vas": ["NOTIFY_RECIPIENT_SMS"],
    "packageTemplate": {"length": 60, "width": 35, "height": 35},
    "maxWeightKg": 35.0
  }

Example presets JSON
--------------------
{
  "pakke_i_postkassen": {
    "label": "Pakke i postkassen",
    "serviceId": "PAKKE_I_POSTKASSEN",
    "vas": [],
    "packageTemplate": {"length": 30, "width": 20, "height": 10},
    "maxWeightKg": 2.0
  }
}

Booking response snippet (example)
----------------------------------
{
  "consignmentNo": "123456789",
  "labelUrl": "https://mybring.example/labels/123.pdf",
  "trackingUrl": "https://tracking.bring.com/123456789"
}

Developer notes
---------------
- To add re-booking/advanced label handling extend BookingService and Routes.
- Consider storing label PDFs in the media library for long-term availability.
- ShippingGuideService, PickupPointService and PostalCodeService can be used
  directly in custom code by instantiating with a SettingsModel instance.
