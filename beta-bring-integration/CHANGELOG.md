# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

## 0.2.0
- **Shipping Guide API** — new `ShippingGuideService` queries available shipping products for a sender/recipient postal code pair (`GET /bbi/v1/shipping-guide`)
- **Pickup Point API** — new `PickupPointService` looks up Bring pickup points by country + postal code (`GET /bbi/v1/pickup-points/{country}/{postalCode}`)
- **Postal Code API** — new `PostalCodeService` validates postal codes and resolves city/municipality (`GET /bbi/v1/postal-code/{country}/{postalCode}`)
- **Customer API** — new `CustomerService` validates Mybring API credentials and fetches user settings/customer numbers (`GET /bbi/v1/customer-settings`)
- **Order list column** — "Bring Label" column added to the WooCommerce orders overview showing consignment number and a direct PDF label link; supports both HPOS and legacy post-based orders
- **HPOS compatibility** — declared WooCommerce High-Performance Order Storage compatibility; order meta is now saved via `WC_Order::update_meta_data()` / `WC_Order::save()` for full HPOS support
- **Pickup point selector** in order meta box — presets can now set `requiresPickupPoint: true`; the meta box will automatically load matching pickup points for the recipient's postal code via the REST API
- **Order notes** — a note is added to the order timeline whenever a shipment is booked (single or bulk), recording consignment number and service ID
- **Bulk booking** — action now also registered for HPOS orders list
- **Admin JS/CSS** improvements — tracking copy button shows brief "Copied!" confirmation instead of an alert; improved error messages

## 0.1.0 - Initial MVP
- Settings UI for Bring credentials and sender
- Presets JSON editor
- Order meta box for booking and label download
- Bulk booking action
- REST endpoint for label availability
- Logging via WC_Logger
