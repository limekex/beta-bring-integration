BeTA Bring Integration
======================

Overview
--------

This plugin integrates WooCommerce with Bring (Mybring) booking API to create consignments and fetch labels. It's an MVP focused on admin workflows: configure credentials, define service "presets", book from the shop order screen (single and bulk), and re-check label availability via a small REST route.

Goals
-----
- Minimal dependencies (no Composer)
- PHP 8.1+, WP 6.3+, WooCommerce 8+
- Clear, documented code using namespace BeTA\Bring and text domain `bbi`

Architecture (ASCII)
--------------------

 plugin bootstrap
  └─ includes/Autoloader.php
  └─ includes/Plugin.php
      ├─ Admin/Settings.php
      ├─ Admin/OrderMetaBox.php
      ├─ Woo/BulkBooking.php
      ├─ API/Client.php
      ├─ API/BookingService.php
      ├─ API/Routes.php
      └─ Model/SettingsModel.php

Development
-----------
- Install the plugin into a WP dev site with WooCommerce activated.
- Use Test mode to avoid real API calls. If test mode enabled and credentials missing the plugin returns fake booking responses.

Testing with Bring
------------------
Enable test mode and provide (optional) test credentials. The plugin will create a fake label URL and consignment in test mode if credentials are missing.

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
