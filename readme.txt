=== Buckaroo Woocommerce Payments Plugin ===
Contributors: buckaroosupport
Author: Buckaroo
Tags: WooCommerce, payments, Buckaroo
Requires at least: 4.4.10
Tested up to: 5.5.1
Stable tag: 2.18.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is a plug-in with countless payment methods, all of which are SEPA proof.

== Description ==

The Buckaroo ([Dutch](https://www.buckaroo.nl/resources/integratie/woocommerce) or [English](https://www.buckaroo.eu/resources/integration/woocommerce) plug-in is convenient and works like a charm, it's easy to install and takes all the trouble out of your hands.
It is a plug-in with many different payment methods, all of which are SEPA proof. This plug-in is completely free to download. WooCommerce is an excellent platform for a webshop to look professional, comes with built-in tools to analyze sales and itâ€™s also fully customizable. WooCommerce is used by 30% of all webshops worldwide, [download](https://www.buckaroo.nl/integratie/plugins/woocommerce/) this plugin now and find out more!
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
18. Apple Pay
19. KBC
20. PayPerEmail
21. Billink

== Installation ==

Easy installation to get the plug-in up and running!
1. Contact Buckaroo by phone 030 711 5010 or by mail sales@buckaroo.nl to request a Buckaroo account.
2. Use one of the manuals below to configure the plug-in and connect it with your Buckaroo account.

[Implementatiehandleiding NL](https://ps.w.org/wc-buckaroo-bpe-gateway/trunk/Wordpress_WooCommerce_3.9.x.NL.pdf)

[Implementation manual EN](https://ps.w.org/wc-buckaroo-bpe-gateway/trunk/Wordpress_WooCommerce_3.9.x.EN.pdf)

Please go to the [signup page](https://www.buckaroo.eu/solutions/request-form) (demo account) to ask for a Buckaroo account. Also you can contact info@buckaroo.nl or +31 (0)30 711 50 00

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

== Release notes ==
for more detailed release notes visit https://support.buckaroo.nl/categorie%C3%ABn/plugins/woocommerce/release-notes

== Additional Info ==
Please contact support@buckaroo.nl if you need help installing the WooCommerce plugin.

== Changelog ==

= 2.18.0 =
- Fixed MyParcel pickup points issue
- Afterpay old update licence agreement in checkout [BP-656]
- Fixed notice for PayPerEmail [BP-632]
- Changed bancontact logo
- Added: PayPerEmail available in the front end [BP-632]
- Fixed: Incorrect error message for Afterpay Product quantity doesn't choose [BP-637]
- Fixed: Status is not pushed to WooCommerce shop [BP-611]
- Fixed: Payment method checkbox to enable/disable is not saved [BP-606]
- KBC logo is very large displayed in the checkout (Billink Request To Pay, In3) [BP-615]
- Added Payment method Billink [BP-480]
- Wordfence user agent & referrer [BP-572]

= 2.17.0 =
- Fixed error in inspect console on every page of shop [BP-576]
- Add new bank to iDEAL payment method (Revolut) [BP-583]
- Fixed issue with Woocommerce Sequential Order Number Pro plugin compatibility
- Added payment method PayPerEmail [BP-564]
- Added carreir support for Afterpay in combination with pick-up points (Sendcloud, PostNL, DHL, MyParcel) [BP-563 ]
- Added payment data bank transfer to meta fields of order [BP-566]
- Credit Card: Added validation for 'Expiration Year' field [BP-532]

= 2.16.4 =
- WooCommerce - new iDEAL logo not scaling correctly [BP-536]

= 2.16.3 =
- Fixed partial refund is not working with USD currency [BP-525]
- Afterpay phone number is not asked when there is no phone number field in the checkout [BP-497]
- Fixed Creditcard empty fields  issue
- Changed the order of inline Credit card (CSE) fields + fill name [BP-494]
- Fixed incorrect VAT for products with reduced VAT rate. [BP-472]
- Fixed Afterpay fee tax value [BP-472]
- Added error text for unselected creditcard [BP-453]
- Fixed incorrect refund shipping price error message [BP-411]
- .htaccess file changes [BP-422]
- Fixed not all information is send with Apple Pay (adress, phone#) [BP-387]
- Fix for Woocommerce Sequensial Order Number plugin compatibility
- Fixed error with refunds from Plaza to wordpress site using Woocommerce Sequential Order Number Pro plugin [BP-328]

= 2.16.2 =
 - Fix Payconiq transaction redirects to unknown page [BP-386]
 - Fix payment fee Afterpay has wrong VAT percentage [BP-390]
 - Fixed Notice: Undefined index:plugin_id
 - Added translations for creditcard inline mode

= 2.16.1 =
 - Changed .htaccess file

= 2.16.0 =
 - In3 payment method [BP-305]
 - Fixed compatibility with new Woocommerce Sequential Order Numbers Pro [BP-328]
 - Fixed PHP Notice error [BP-344]

= 2.15.0 =
 - Incorrect VAT number BP-220
 - Fix Afterpay refund BP-220
 - Fix payment fee autorefund [BP-220]
 - Add changes for VAT fee price [BP-220]
 - KBC payment method [BP-219]
 - Place order fails [BP-225]
 - Request a change for an error message with custom gift cards (invalid retailer) [BP-236]
 - Added payment method Request To Pay [BP-147]
 - Apple pay button is not shown [BP-231]
 - Fixed giftcards refunds error BP-170
 - Added giftcards group refunds message notification
 - Trx Status updates [BP-167]
 - Fix for Afterpay refund connected with reduced tax
 - Fixed Afterpay taxes refund
 - Afterpay Tax Refunds - added taxId value
 - ApplePay - error request wit tax presence [BP-277]
 - Fix applepay's customer card name error notification

= 2.14.0 =
 - Compatibility with WooCommerce 4.0.1 and WordPress 5.3.2
 - Solved issue with servicename for Carte Bleue payments

= 2.13.2 =
 - Compatibility with WooCommerce 3.9

= 2.13.1 =
 - Fix for warning: Order ID was called incorrectly.
 - Fix for error while processing creditcard refund.
 - Compatibility with WooCommerce 3.8

= 2.13.0 =
 - Apple Pay: Add CustomerCardName parameter to API call based on SDK response.
 - Admin has availability to select which creditCard provider will be available in frontend by default all selected.
 - Ideal: change translation domain from woocommerce to wc-buckaroo-bpe-gateway for error message in case ideal issuer not selected.
 - Fix for warning: Customer ID was called incorrectly
 - Compatibility with WooCommerce 3.7

= 2.12.1 =
- Updated readme.txt

= 2.12.0 =
 - Added new payment method: Apple Pay

= 2.11.0 =
 - Implemented inline creditcard options
 - Implemented authorise and capture for creditcards
 - Implemented Partial capture for creditcards
 - Implemented Partial refund for all applicable payment methods

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
