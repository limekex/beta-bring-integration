# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

## 0.4.0

### Added
- **Bring shipment notification email** — A custom WooCommerce email (`bbi_shipment_booked`) is now sent to the customer automatically whenever a Bring shipment is booked (from the order meta box or bulk booking). The email includes the consignment number, a clickable tracking link, and the Bring service name. The template can be customised via WooCommerce → Settings → Emails → "Bring shipment booked", and overridden by copying `templates/emails/bbi-shipment-booked.php` to `yourtheme/woocommerce/emails/`.
- **Sender logo URL** field (`bbi_sender_logo_url`) added to WooCommerce → Settings → Shipping → BeTA Bring → Sender details section. The logo is embedded in the shipment notification email header.
- **Sender reference** field (`bbi_sender_reference`) added to the Sender details section. When set, this reference prefix is prepended to the WooCommerce order number in every Bring booking request (visible on the shipping label and in Mybring reports). Limited to 35 characters total per Bring API specification.

### Fixed
- `get_cart was called incorrectly` warnings in the WordPress debug log: these originate from the `WC_Donate_Checkout` third-party plugin calling `WC_Cart::get_cart()` too early (during `widgets_init` before `wp_loaded`). BeTA Bring Integration does not appear in the call stack and requires no code change on our side.

## 0.3.0

### Fixed
- **Fallback prices for all shipping options** — Bring Shipping Guide v2 always returns *numeric* product IDs (`3584`, `5800`, `5600`) even when the request sends legacy string codes (`PAKKE_I_POSTKASSEN`, `SERVICEPAKKE`, `PA_DOREN`). Added `LEGACY_CODE_MAP` in `ShippingMethod` so that API products are also indexed under their legacy aliases, allowing existing presets with string service IDs to resolve correct prices without any database changes.
- **Version bump busts stale transient / session caches** — the API-response transient key and the WooCommerce shipping session cache key both include `BBI_VER`, so upgrading from 0.2.0 to 0.3.0 automatically discards old cached rates and ensures users see live Bring prices immediately.

### Added
- **Classic checkout card UI** — each shipping option is now styled as a selectable card with hover/focus effects. Details (logo, delivery estimate, description, closest pickup point) are shown **only for the selected option** via a CSS `:has()` rule with a JS `.bbi-selected` class fallback for older browsers.
- **Full-width shipping row** — `checkout.js` moves the "Shipping" heading text inside the options cell and sets `colspan="2"`, so shipping options occupy the full table width instead of being squeezed into half the row.
- **WooCommerce Blocks checkout enrichment** — `BlocksIntegration` registers a Store API extension exposing `gui_info` and `expected_delivery` on every BBI rate in `/wc/store/v1/cart`. `checkout-blocks.js` subscribes to the `wc/store/cart` Redux store and uses a `MutationObserver` to inject logo, delivery estimate, description, and pickup point hint into Blocks checkout shipping option cards.
- **Updated default presets** — default `bbi_presets_json` now uses current numeric service IDs (`3584`, `5800`, `5600`) and corrects the max-weight for "Pakke i postkassen" from 2 kg to 5 kg (matching Bring's published limit).

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
