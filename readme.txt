=== Buckaroo Woocommerce Payments Plugin ===
Contributors: buckaroosupport
Author: Buckaroo
Tags: WooCommerce, payments, Buckaroo
Requires at least: 4.4.10
Tested up to: 5.1.1
Stable tag: 2.10.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is a plug-in with countless payment methods, all of which are SEPA proof.

== Description ==

The Buckaroo ([Dutch](https://www.buckaroo.nl/integratie/plugins/woocommerce/) or [English](https://www.buckaroo-payments.com/integration/plugins/woocommerce/)) plug-in is convenient and works like a charm, it's easy to install and takes all the trouble out of your hands.
It is a plug-in with many different payment methods, all of which are SEPA proof. This plug-in is completely free to download. WooCommerce is an excellent platform for a webshop to look professional, comes with built-in tools to analyze sales and it’s also fully customizable. WooCommerce is used by 30% of all webshops worldwide, [download](https://www.buckaroo.nl/integratie/plugins/woocommerce/) this plugin now and find out more!
Payment method support list:

= Payment method support list by Buckaroo WooCommerce payments plugin =
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
14. Payconiq
15. Nexi
16. P24
17. AfterPay 2.0

== Installation ==

Easy installation to get the plug-in up and running!
1. Contact Buckaroo by phone 030 711 5010 or by mail sales@buckaroo.nl to request a Buckaroo account.
2. Use one of the manuals below to configure the plug-in and connect it with your Buckaroo account.

[Implementatiehandleiding NL](https://ps.w.org/wc-buckaroo-bpe-gateway/trunk/Wordpress_WooCommerce_3.9.x.NL.pdf)

[Implementation manual EN](https://ps.w.org/wc-buckaroo-bpe-gateway/trunk/Wordpress_WooCommerce_3.9.x.EN.pdf)

Please go to the [signup page](https://www.buckaroo-payments.com/integration/buckaroo-payment-plaza/) (demo account) to ask for a Buckaroo account. Also you can contact info@buckaroo.nl or +31 (0)30 711 50 00

== Screenshots ==

1. Centrally manage your payment methods, with our Master Settings feature
2. Safely try out any payment method in TEST MODE.
 
== Frequently Asked Questions ==

= Minimum Requirements =
- WordPress 4.4 
- WooCommerce 2.2

= How do I automatically install the plugin? =
1. Install the plugin through the plugins menu in wp-admin
2. Activate the 'Buckaroo WooCommerce' plugin through the 'Plugins' menu in WordPress
3. Set your API key at WooCommerce -> Settings -> Checkout
4. Activate the payment methods you want in  WooCommerce -> Settings -> Checkout
5. You're done, the active payment methods should now be visible in your webshop's progress screen.

= I want to manually install the plugin, how can I do this? =
1. Download a .zip of the plugin from [WordPress](https://wordpress.org/plugins/wc-buckaroo-bpe-gateway/)
2. Unzip the downloaded .zip package
3. Upload the directory to the /wp-content/plugins/ directory
4. Activate the 'Buckaroo WooCommerce' plugin through the 'Plugins' menu in WordPress
5. Set your API key at WooCommerce -> Settings -> Checkout
6. Activate the payment methods you want in  WooCommerce -> Settings -> Checkout
7. You're done, the active payment methods should now be visible in your webshop's progress screen.

= I have installed the plugin, but the transactions are not working. What's wrong? =
Verify that all data is entered correctly. This is the website site, the secret key, the thumbprint and the certificate that must be uploaded. Also check the Buckaroo report in the left-hand menu of your Wordpress environment. If you are creating test transactions, please go to plaza.buckaroo.nl. Then, under My Buckaroo -> Websites -> Choose your website through the filter at the top right -> 3.0 Settings, choose "Accept Test Transactions".
 
= The transactions are good and work in the Buckaroo Plaza, but the status in my WooCommerce environment does not change. Why is this? =
Very likely, push settings are not setup correctly. The push is used to update the status of the order in WooCommerce. Please refer to the manual and check your push settings at plaza.buckaroo.nl under My Buckaroo -> Websites -> Choose your website through the filter at the top right -> Push settings. Two issues:
- If your website is secure (URL starts with https instead of http), then the push URL must also start with https
- Check that the return fields in our Plaza are in "lowercase letters".

= The customer does not see the thank you page, after a successful order, how can I fix this? =
Probably the return fields are not yet in lowercase letters in our plaza under My Buckaroo -> Websites -> Choose your website through the filter at the top right -> Push settings. Also check whether the push settings are filled in (see the manual for information on how to set this). Additionally, you can check that the Secret Key in the Buckaroo Plaza corresponds to the Secret Key that was filled in during the configuration of the Buckaroo plugin in WooCommerce. Also, verify that the Secret Key contains only alphanumeric characters.

== Additional Info ==
Please contact support@buckaroo.nl if you need help installing the WooCommerce plugin.

== Changelog ==

= 2.10.2 =
- Compatibility with WooCommerce 3.6

= 2.10.1 =
- Updated manual with explanation about new AfterPay payment method. (Section 4.2.4)

= 2.10.0 =
- Users can edit the giftcards available in the payment method configuration.
- New payment method Afterpay (new version) added.
- Fixed minor issue with loading the website key from the push response.

= 2.9.0 =
- Adds new payment methods (Nexi, P24 and SEPA B2B), improves iDeal banks display, updates documentation with PayPal Seller Protection information

= 2.8.3 =
- Fixing PHP warning with debug logfile

= 2.8.2 =
- Added Handelsbanken as new iDeal bank

= 2.8.1 =
- Enabled Payment Guarantee refund, fixed success redirect page for Payconiq and several small improvements

= 2.8.0 =
- Added functionality to handle payment settlements for payment method Bank Transfer. 

= 2.7.0 =
- Added Payment Method Payconiq

= 2.6.5 =
- Added Moneyou iDeal issuer

= 2.6.4 =
- Fixed issue with the reading secretkey in push notifications

= 2.6.3 =
- Fixed issue with the reading mastersettings in the configuration when settings are not set

= 2.6.2 =
- Fixed issue with the reading mastersettings in the configuration

= 2.6.1 =
- Use currency set in WooCommerce, rather than setting it seperately in the Plugin.
- Compatibility with WP-CLI
- Fixed an issue with the notification setting following the individual settings instead of the master settings
- Fixed an issue with Exodus sometimes being shown as payment method 
- Added Westlandbon to available Giftcards

= 2.6.0 =
- Added support for WooCommerce Sequential Order Numbers plugin by SkyVerge
- Only load JS files in wp-admin
- WooCommerce 3.2 support

= 2.5.0 =
Admin modernised/improved
- Option to use single certificate across all payment methods added
- Certificates now added via 'upload Certificate' button
- Certificates now stored in database, rather than as network file
- Master settings page added, allows centralised setting of
- Language
- Transaction Description
- Use notification
- Notification Delay
- Merchant Key
- Secret Key
- Thumbrint
- Certificate
- Currency
- Transaction Mode
- Debug Mode added (records requests to & responses from buckaroo)
- Code modernised/imporved
- Docblocks added
- Old commented code removed
- Calls to WooCommerce & Buckaroo centralised in wrapper functions
- Include/require function centralised
-Push Notifications Fixed
-Missing translations added

= 2.4.1 =
Calls to functions within empty() statements removed. Due to crashes with php 5.4 & below.

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
