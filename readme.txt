=== Plugin Name ===
WC Buckaroo BPE Gateway
Contributors: buckaroosupport
Author: Buckaroo
Tags: WooCommerce, payments, Buckaroo
Requires at least: 3.0.0
Tested up to: 4.7
Stable tag: 2.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is a plug-in with countless payment methods, all of which are SEPA proof.

== Description ==

The Buckaroo (Dutch: https://www.buckaroo.nl/integratie/plugins/woocommerce/ or English: https://www.buckaroo-payments.com/integration/plugins/woocommerce/) plug-in is convenient and works like a charm, it's easy to install and takes all the trouble out of your hands.
It is a plug-in with many different payment methods, all of which are SEPA proof. This plug-in is completely free to download. WooCommerce is an excellent platform for a webshop to look professional, comes with built-in tools to analyze sales and it's also fully customizable. WoocCommerce is used by 30% of all webshops worldwide, download this plugin now and find out more!
Payment method support list:

1. iDEAL
2. PayPal
3. Creditcards (Visa, MasterCard, American Express, VPAY, Visa Electron, Carte Bleue, Carte Bancaire, Dankort)
4. eMaestro
5. Afterpay
6. Giftcards
7. Giropay
8. Bancontact
9. Payment Guarantee
10. SOFORT Banking
11. SEPA Credit Transfer (Bank transfer)
12. SEPA Direct Debit (With or without Credit management)
13. Paysafecard

== Installation ==

Easy installation to get the plug-in up and running!
1. Contact Buckaroo by phone 030 711 5010 or by mail sales@buckaroo.nl to request a Buckaroo account.
2. Use one of the manuals below to configure the plug-in and connect it with your Buckaroo account.

[Implementatiehandleiding NL](https://images.buckaroo.nl/plugins/Wordpress_WooCommerce/Wordpress_WooCommerce_2.3.x-2.5.x.NL.pdf)

[Implementation manual EN](https://images.buckaroo.nl/plugins/Wordpress_WooCommerce/Wordpress_WooCommerce_2.3.x-2.5.x.EN.pdf)

== Screenshots ==

1. https://www.buckaroo.nl/content/image/WooCommerce_backend1.PNG
2. https://www.buckaroo.nl/content/image/WooCommerce_frontend1.PNG

== Changelog ==
= 2.4.1 =
Compatibility update with php 5.4 & below.

= 2.4.0 =
Update plugin to work with WooCommerce 3.0. This includes the following changes:
- Fixed issue where duplicate orders were created. This happened when a payment method was chosen, then canceled and another was chosen to complete payment.
- Fixed function checking the version of WooCommerce. As it was only able to check if it was version 2. It now works, but will throw an exception in the log, if it breaks.
- Fixed an issue with the radio buttons to indicate gender were not rendering, on the afterpay and payment guarantee payment options.
- Fixed an issue with errors being thrown in the debug log, even with successful payments. Problem involved changing several functions across most payment methods.
- Updated calls to meet latest WooCommerce standards.
Removed pay guarantee by Juno from payment methods.


= 2.3.4 =
Added AfterPay error messages in case consumer fills in incorrect personal information.
Updated explanations for various fields in the backend.

= 2.3.3 =
Added Ippies to Giftcard payment method.
Fixed an issue with PayPal refund not sending an invoice number.
Fixed an issue with PayPal Seller Protection not working when Notification was disabled.

= 2.3.2 =
Fixed redirect issues with certain payment methods.

= 2.3.1 =
Fixed an issue where saving the payment fee didn't work.
Fixed an issue with redirect after a Sofort transaction.

= 2.3.0 =
Fixed an issue with double order creation in combination with WooCommerce 2.6, plug-in is now officially compatible with WooCommerce 2.6.
Fixed an issue with Payment Guarantee when customer checks out as guest.
Added PayPal seller protection.

= 2.2.12 =
Fixed an unintended result for error messages due to changes in 2.2.11.

= 2.2.11 =
Removed checkout as company if AfterPay B2B is disabled in backend.
Added AfterPay error message in case of reject.

= 2.2.10 =
Less AfterPay fields mandatory for B2B and different shipping address.
No longer creates order when frontend validation fails.
Fixed AfterPay issues in combination with discounts.
Added Carte Bancaire, Carte Bleue, Dankort and Visa Electron to creditcards.

= 2.2.9 =
Changes on GroupID representation.

= 2.2.8 =
iDeal choice of bank is mandatory, not automatically selecting ABN AMRO.

= 2.2.7 =
Added VVV Giftcard to the existing list of giftcards.
Added Parfum Cadeaukaart Giftcard to the existing list of giftcards.
Fixed compatibility issue with WooCommerce 2.3.
Added possibility to choose payment fee tax mode(include/exclude).


= 2.2.6 =
iDeal choice of bank is mandatory.
Fixed crashes if sopa extension not installed.
Invoice number change on transaction failure.
Added Bunq bank on iDeal list.
Problem with total amount fixed.
Unitprice with 2 decimals and it did count VAT in the order.


= 2.2.5 =
Notification type fix.

= 2.2.4 =
Strict mode notification fixed.

= 2.2.3 =
Certificate directory updated to prevent delete PEM file on update.

= 2.2.2 =
Updated plugin name.
Updated some translations.

= 2.2.1 =
Renamed methods to make it more unique.
Added NL translations.

= 2.2.0 =
Added payment fees to payment method.

= 2.1.2 =
Initial version.
