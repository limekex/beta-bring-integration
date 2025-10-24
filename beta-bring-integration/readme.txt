=== BeTA Bring Integration ===
Contributors: beta
Donate link: https://example.com
Tags: shipping, bring, booking, label, woo
Requires at least: 6.3
Tested up to: 8.9
Stable tag: 0.1.0

== Description ==
Bring booking integration for WooCommerce. Allows admins to configure Bring API credentials, define service presets, book consignments from the order screen, download labels and copy tracking links.

== Installation ==
1. Upload the plugin to /wp-content/plugins/
2. Activate the plugin.
3. Go to WooCommerce -> Settings -> Shipping -> BeTA Bring and configure your Mybring credentials and presets.

== Frequently Asked Questions ==
= How do I get API credentials? =
Sign up for Mybring and request API credentials.

= Is there a test mode? =
Yes. Enable Test mode in the settings. In test mode and without real credentials the plugin will generate fake booking responses for development.

== Changelog ==
= 0.1.0 =
* Initial MVP: settings, presets JSON, order meta box booking, bulk booking, REST label endpoint, logging.
