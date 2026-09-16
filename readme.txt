=== Buckaroo Payments for WooCommerce ===
Contributors: buckaroosupport
Tags: payment gateway, payments, ideal, klarna, checkout
Requires at least: 5.3.18
Tested up to: 7.1
Stable tag: 4.10.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept iDEAL | Wero, Bancontact, cards, Klarna, PayPal and more in your WooCommerce checkout, all through one Buckaroo account.

== Description ==

[Buckaroo Payments for WooCommerce](https://www.buckaroo.nl/plugins/woocommerce) connects your webshop to the Buckaroo payment platform. You offer the local and international payment methods your customers already trust, and you manage every transaction, refund and payout from one place.

The plugin is built and maintained by Buckaroo, so new payment methods, logos and platform updates reach your shop without custom development work.

= Sell to more customers =

* Local favourites and international methods side by side, from a single Buckaroo account.
* Buy now, pay later options so customers can pay after delivery or spread the cost.
* Express buttons on the product page, cart and checkout, so wallet users can pay in a few taps.
* Payment methods are shown only when they apply to the customer's currency and country, which keeps the checkout clean.

= Fits the shop you already run =

* Works with both the block-based checkout and the classic checkout.
* Compatible with High-Performance Order Storage.
* Available in several languages, with the payment page following the customer's language.
* Optional payment fees per method, applied to the order total.

= Less work in the Administration =

* Automatic Configuration checks your Buckaroo subscriptions and sets up the matching payment methods for you.
* Full and partial refunds straight from the WooCommerce order screen, with the order status kept in sync.
* Order statuses updated automatically through Buckaroo push messages.
* Send a payment request to a customer with PayPerEmail or a PayLink, without leaving the order screen.
* Test mode per payment method, with a clear notice in the Administration while a method is still in test.

= Supported payment methods =

* Alipay
* Apple Pay
* Bancontact
* Belfius
* Billink
* Bizum
* Blik
* Cards (American Express, Visa, Mastercard, VPAY, Visa Electron, Maestro, Carte Bleue, Carte Bancaire, Dankort, Nexi, PostePay)
* EPS
* Giftcards
* Google Pay
* iDEAL | Wero
* In3
* KBC
* Klarna
* MB Way
* Multibanco
* PayByBank
* PayPal
* PayPerEmail
* Przelewy24
* Riverty
* SEPA Credit Transfer (bank transfer)
* SEPA Direct Debit
* Swish
* Trustly
* Twint
* WeChat Pay
* Wero (BE, DE, FR)
* Zakelijk op rekening (ABN AMRO)

Which methods you can offer depends on the subscriptions active on your Buckaroo account.

= Before you start =

You need a Buckaroo account to use this plugin. Create one via [buckaroo.eu](https://signup.buckaroo.nl/?country=NL&lang=en) (English) or [buckaroo.nl](https://signup.buckaroo.nl/?country=NL&lang=nl) (Dutch). Once your account is active, you configure the plugin with the Store key, Secret key and Merchant key from your Buckaroo Plaza.

= Documentation and support =

Step by step setup instructions are in the [WooCommerce installation guide](https://docs.buckaroo.io/docs/woocommerce-installation). The source code and release notes are on [GitHub](https://github.com/buckaroo-it/WooCommerce).

== Installation ==

1. Make sure WooCommerce is installed and active, and that you have a Buckaroo account.
2. Install the plugin through Plugins > Add New in your WordPress Administration, or upload the plugin folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins screen.
4. Go to WooCommerce > Settings > Payments > Buckaroo and enter your Store key, Secret key and Merchant key.
5. Use the Automatic Configuration button to enable the payment methods that match your Buckaroo subscriptions, or enable and configure them one by one.
6. Set the push URL in your Buckaroo Plaza as described in the [installation guide](https://docs.buckaroo.io/docs/woocommerce-installation), so order statuses are updated automatically.

Full instructions, including the settings for each payment method, are in the [WooCommerce installation guide](https://docs.buckaroo.io/docs/woocommerce-installation).

== Frequently Asked Questions ==

= Do I need a Buckaroo account? =

Yes. The plugin sends transactions to the Buckaroo payment platform, so you need an active Buckaroo account. You can request one via [buckaroo.eu](https://www.buckaroo.eu/sign-up) or [buckaroo.nl](https://www.buckaroo.nl/account-aanmaken).

= Does the plugin work with the block-based checkout? =

Yes. The payment methods and the express buttons work with both the block-based checkout and the classic checkout.

= A payment method I need is not shown in my checkout. What should I check? =

First check that the method is enabled in WooCommerce > Settings > Payments > Buckaroo. If it is enabled but still not visible, check that the subscription for that method is active on your Buckaroo account, and that the method supports the currency and country of the order.

= Order statuses are not updated after a payment. What should I check? =

Order statuses are updated through push messages from Buckaroo. Check that the push URL in your Buckaroo Plaza matches the URL in the installation guide and that it is reachable from outside your network.

= Can I refund an order from WordPress? =

Yes. Full and partial refunds can be started from the WooCommerce order screen, and the order status is updated once Buckaroo confirms the refund.

= In which languages is the plugin available? =

The plugin ships with translations and you can [translate it into your own language](https://translate.wordpress.org/projects/wp-plugins/wc-buckaroo-bpe-gateway/) on WordPress.org.

== Screenshots ==

1. Enable and manage the payment methods your customers expect, all from one screen.
2. Apple Pay, Google Pay and PayPal Express appear on the product, cart and checkout pages, so customers can check out in a single tap.
3. Buckaroo Hosted Fields keep customers on your checkout page while Buckaroo securely handles the card data.
4. Add your API credentials and use auto-configure to enable all your active payment methods at once.

== External services ==

This plugin connects your shop to the Buckaroo payment platform in order to create, process and refund payments. Without this connection the plugin cannot function.

Data is sent to Buckaroo when a customer places an order with a Buckaroo payment method, and when you refund or capture an order from the WordPress Administration. Depending on the payment method, this can include the order number and amount, the currency, the billing and shipping address, the customer name, email address, phone number and, for buy now, pay later methods, the order lines and date of birth.

Buckaroo also sends push messages back to your shop to report the status of a transaction.

Service provided by Buckaroo B.V.: [terms and conditions](https://www.buckaroo.eu/terms-and-conditions) and [privacy statement](https://www.buckaroo.eu/privacy-statement).

== Contact ==

Questions about your account or subscriptions: [wecare@buckaroo.nl](mailto:wecare@buckaroo.nl) or [+31 (0)30 711 50 00](tel:+31307115000).

Technical questions about the plugin: [support@buckaroo.nl](mailto:support@buckaroo.nl).

== Upgrade Notice ==

= 4.10.1 =
Fixes payment method icons that were displayed oversized and overlapping in the checkout since version 4.10.0.

= 4.10.0 =
Refreshed plugin Administration with restructured settings, Google Pay as a regular payment method and automatic Klarna (MoR) capture on shipment. Includes fixes for Apple Pay, Google Pay Express and the classic checkout.

= 4.9.1 =
Adds support for High-Performance Order Storage. Existing orders are updated automatically in the background after the update.

== Changelog ==

For release notes of earlier versions, see the [releases on GitHub](https://github.com/buckaroo-it/WooCommerce/releases).

= 4.10.1 =

Improvements

BTI-1218 The long and short description of the plugin have been rewritten for the plugin listing
BTI-1220 The plugin screenshots on the plugin download page have been updated
BTI-1473 A SECURITY.md file has been added to the repository

Bug fixes

BTI-1550 Payment method icons were displayed oversized and overlapping in the checkout since version 4.10.0

= 4.10.0 =

Improvements & new features

BTI-1245 Support for WordPress 7.1 and WooCommerce 11.0 and 11.1.0
BTI-778 The plugin Administration has a refreshed design, and the menu and settings have been restructured so payment methods and their configuration are easier to find
BTI-1158 Google Pay is now selectable as a regular (non-Express) payment method
BTI-1042 New payment method: Zakelijk op rekening "ABN-AMRO"
BTI-1113 Automatic capture on shipment creation for Klarna (MoR)
BTI-1179 Redirect information text in the checkout for redirect-based payment methods
BTI-1187 The iDIN logo is now the co-branded iDIN | itsme logo
BTI-1341 GoSettle has been removed as a payment method (deprecated)
BTI-1219 The WordPress.org plugin display name is now "Buckaroo Payments for WooCommerce"
BTI-1210 "Klarna pay later (authorize/capture)" is now "Klarna (KP)", matching the Buckaroo subscription name
BTI-1137 The Google Pay ID setting is no longer labelled as GUID
BTI-1177 The optional Date of Birth field for Billink has been removed from the checkout payment selection
BTI-1211 Hosted fields settings are only shown when hosted fields are enabled
BTI-1510 Information subtext added for Zakelijk op rekening "ABN-AMRO"
BTI-1221 The WordPress.org changelog has been trimmed to resolve the 5,000-word truncation warning
BTI-1127 JS dist files are built automatically in CI for pull requests to develop
BTI-1289 The README.md has been updated

Bug fixes

BTI-1167 Google Pay Express could create orders without a billing address and email address
BTI-1229 A duplicate Buckaroo error notice appeared in the classic checkout (#521)
BTI-1272 The cart could crash when Apple Pay was used
BTI-1242 Belgian customers were sent culture code nl_NL instead of nl_BE for Klarna (MoR)
BTI-1308 wp_redirect() was called with an array instead of a string in the admin PayPerEmail and PayLink actions
BTI-1136 Payment method icons were misaligned in the legacy and blocks-based checkout

= 4.9.1 =

Improvements

BTI-1245 Added support for WooCommerce 11.0.
BTI-1245 Order data is now stored through the WooCommerce order API, so the plugin works with High-Performance Order Storage. Existing orders are updated automatically in the background after the update.

Bug Fixes

BTI-1245 Resolved an issue where a new order could not be started after a failed payment on shops using High-Performance Order Storage.

= 4.9.0 =

Improvements & New Features

BTI-777 Apple Pay is now visible across all web browsers and is also displayed as a separate checkout option alongside the Apple Pay buttons.
BTI-1036 Removed the financial warning setting for BNPL methods, as this warning is already displayed on the redirect page.
BTI-1037 Removed the optional gender field for Billink to reduce the number of consumer checkout steps.
BTI-1038 Removed the optional gender field for Klarna to reduce the number of consumer checkout steps.
BTI-1059 Added sandbox environment support for PayPal Express transactions.
BTI-899 Product image URLs are now sent in Riverty requests so they can be displayed on Riverty invoices and in the Riverty app.
Fixed block checkout compatibility issues with multi-currency setups.
Updated the salutation payload for Klarna.
Updated payment method logos.

Bug Fixes

BTI-1079 Resolved an issue with the date picker in combination with the WooCommerce legacy checkout for all BNPL methods.
BTI-770 Resolved an issue where Apple Pay was not displayed in the WooCommerce order details.
BTI-1111 Resolved an issue caused by a hardcoded wp-admin URL for AJAX/admin paths.
BTI-747 Resolved an issue where PayPal orders were not always moved to Processing status after a successful PUSH notification.

Security updates

BTI-1129 Resolved an issue with AJAX action.

= 4.8.2 =

Improvements

BTI-1124 Fixed an issue where orders using Klarna (MoR) could not be placed through the WooCommerce legacy checkout when the address was in the Netherlands.

= 4.8.1 =

Maintenance release: corrected version metadata; includes all 4.8.0 features

= 4.8.0 =

Improvements & New Features

BTI-902 Add support for WooCommerce 10.7.0 and WordPress 7.0
BTI-685 Added support for WooCommerce 10.5.0, 10.5.1, 10.5.2, and 10.5.3.
BTI-603 Added Google Pay as a payment method.
BTI-577 Added Klarna (MoR) as a payment method.
BTI-638 Removed the API version setting for the In3 payment method and enforced the V3 API.
BTI-629 Prevented Express payment method scripts from loading on product pages when Express methods are disabled.
BTI-717 Fixed an issue where stock was not updated when a pending refund was processed later.
BTI-665 Fixed an issue where bank transfer payment instructions were always displayed in English despite different culture codes being sent.
BTI-708 Fixed an issue where the iDEAL | Wero frontend label translation was not saved and remained in English.
BTI-509 Fixed a Riverty tax issue regarding the percentage of payment fees.
BTI-1035 Avoid duplicate Riverty phone number field when WooCommerce billing phone is already provided.
