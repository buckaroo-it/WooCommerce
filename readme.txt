=== Buckaroo Woocommerce Payments Plugin ===
Contributors: buckaroosupport
Author: Buckaroo
Tags: WooCommerce, payments, Buckaroo
Requires at least: 4.4.10
Tested up to: 6.6.2
Stable tag: 3.14.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is a plug-in with countless payment methods, all of which are SEPA proof.

== Description ==

The Buckaroo [Dutch](https://docs.buckaroo.io/docs/nl/woocommerce-installatie) or [English](https://docs.buckaroo.io/docs/woocommerce-installation) plug-in is convenient and works like a charm, it's easy to install and takes all the trouble out of your hands.
It is a plug-in with many different payment methods, all of which are SEPA proof. This plug-in is completely free to download. WooCommerce is an excellent platform for a webshop to look professional, comes with built-in tools to analyze sales and it's also fully customizable. WooCommerce is used by 30% of all webshops worldwide, [download](https://docs.buckaroo.io/docs/en/woocommerce-installation) this plugin now and find out more!
Payment method support list:

= Payment method support list by Buckaroo WooCommerce payments plugin =
1.iDEAL
2.PayPal
3.Credit and debit card (American Express, Visa, MasterCard, VPAY, Visa Electron, Maestro, Carte Bleue, Carte Bancaire, Dankort, Nexi, PostePay)
4.Riverty/Afterpay
5.Giftcards
6.Bancontact
7.Sofort
8.SEPA Credit Transfer (Bank transfer)
9.SEPA Direct Debit (With or without Credit management)
10.Payconiq
11.Przelewy24
12.Apple Pay
13.KBC
14.PayPerEmail
15.Billink
16.Belfius
17.Klarna
18.In3
19.PayByBank
20.EPS
21.Multibanco
22.MB Way
23.Knaken Settle
24.Blik

== Installation ==
 
Before proceeding, ensure that you have a Buckaroo account. If you haven't already, you can create or request an account via the following links:
For English: [Create Account](https://www.buckaroo.eu/create-account)
For Dutch: [Account Aanmaken](https://www.buckaroo.nl/account-aanmaken)
 
To install the Buckaroo plugin for WooCommerce, please refer to our comprehensive documentation pages. These guides provide detailed step-by-step instructions:
For English: [WooCommerce Installation Guide](https://docs.buckaroo.io/docs/woocommerce-installation)
For Dutch: [WooCommerce Installatiehandleiding](https://docs.buckaroo.io/docs/nl/woocommerce-installatie)
 
For assistance with setting up your account, reach out to our customer care team:
Email: wecare@buckaroo.nl
Phone: +31 (0)30 711 50 00
 
If you encounter any technical queries while installing or using the plugin, our dedicated technical support team is here to help:
Email: support@buckaroo.nl
 
We're committed to ensuring a smooth installation process and providing ongoing support for your WooCommerce integration with Buckaroo.

== Screenshots ==

1. Centrally manage your payment methods, with our Master Settings feature
2. Safely try out any payment method in TEST MODE.

== Frequently Asked Questions ==

= Minimum Requirements =
- WordPress 4.4.10
- WooCommerce 5.0

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
for more detailed release notes visit https://docs.buckaroo.io/docs/woocommerce-releases

== Additional Info ==
Please contact support@buckaroo.nl if you need help installing the WooCommerce plugin.

== Changelog ==

= 3.14.0 =
BP-3662 Add support for WordPress 6.6.2 & WooCommerce 9.2.3 & 9.3.1
BP-3632 Enhanced the handling of HTTP headers in requests to ensure better performance and stability.
BP-3603 Add new payment method: Blik
BP-3671 The default payment method name has changed from AfterPay/Riverty to just Riverty.
BP-3712 The logo was updated for Riverty.
BP-3782 Giropay has been removed (discontinued)
BP-3571 We’ve fixed a PHP error that was showed when moving an order to the WordPress trash bin.
BP-3661 We’ve fixed an error 404 on blocks.js #277
BP-3640 A fix was implemented for a Uncaught TypeError: number_format(): Argument #1 ($num) must be of type float, string given in.
BP-3852 Payment fee is not always working correctly for Blocks checkout.
BP-3786 We’ve fixed a few issues when using the Blocks checkout.

= 3.13.2 =
BP-3632 Improvement Enhanced the handling of HTTP headers in requests to ensure better performance and stability

= 3.13.1 =
BP-3586 Fix: Astra theme issue on iOS devices

= 3.13.0 =
Add support for WordPress 6.5.3
Add Support for WooCommerce 8.9.2
BP-3570 Change the default values for authorized giftcard brands.
BP-3569 Adjustments for the payment method frontend-label and descriptions.
BP-3572 Payconiq transactions are now being redirected to the Buckaroo Hosted Payment page.
BP-3585 Fix: Update the plugin WSDL file with HTTPS URL (visual issue).
BP-3596 Fix: Credit and debitcards error “Expirationyear” value missing.
BP-3599 Fix: User interface issue for PayByBank.
BP-3574 Fix: Wrong amount in PUSH notification order notes (visual issue).
BP-3598 Fix: Remove unnecessary COC & VAT number field for Billink.
BP-3586 Fix: Astra theme issue on iOS devices (payment logo’s not aligned correctly)

= 3.12.0 =
Add support for WordPress 6.5.2
Add support for WooCommerce 8.8.2

BP-3507 PayByBank transaction description is not identical to the other payment methods (no spaces)
BP-3523 / BP-3529 Wrong amount in PUSH notification order notes
BP-3535 Remove logo selection for “In3” and “iDEAL In3”

= 3.11.1 =
BP-3518 Price is wrongly communicated

= 3.11.0 =

Add support for WordPress 6.4.3 & WooCommerce 8.7.0
BP-3311 Add support for WooCommerce block based checkout
BP-3422 Add payment method: Knaken Settle
BP-3421 Add the option to show the payment page language dynamic (customer browser language)
BP-3374 Add more translations for validations
BP-3457 Update payment method logo's (Billink, iDEAL, iDEAL In3 Credit-debitcards)
BP-3484 Align Express payment buttons for visual improvements
BP-3499 Use the new payment method logo's by default
BP-3476 Change refund description in e-mail to customer
BP-3082 Add financial warning for the use of BNPL methods
BP-3146 Add a option to not show the iDEAL issuers selection in the checkout
BP-3284 Changed default credit and debitcard method to redirect instead of inline
BP-3323 Change new required fields for Riverty (DE - Germany)
BP-3379 Fix: Critical error
BP-3388 Fix: When creating a PayPerEmail in the admin area it returns null
BP-3374 Fix: Add a phone number field for iDEAL In3 (when a phone number field is not required in the checkout)
BP-3404 Fix: iDEAL In3 is not changing the status to on hold when pending processing is communicated
BP-3420 Fix: iDEAL without issuer is not sending ContinueOnIncomplete
BP-3483 Fix: PayPal Express button error "Can't place order"
BP-3487 Fix: PayPal Express order is created with no address information
BP-3485 Fix: Orders origin is unknown for Apple Pay and PayPal Express orders
BP-3477 Fix: Apple pay button is not visible in WooCommerce Blocks checkout

= 3.10.0 =
Add support for Wordpress 6.3.2
Add support for WooCommerce 8.2.1
- BP-2924 Add iDEAL issuer Nationale Nederlanden.
- BP-2905 iDEAL issuer "Van Lanschot" is renamed to "Van Lanschot Kempen".
- BP-2992 In3 (V3) set the iDEAL In3 logo as the default.
- BP-3030 Remove BIC/IBAN fields for Giropay.
- BP-2984 Add payment method "Multibanco".
- BP-3015 Add payment method "MB WAY".
- BP-2972 Payment fee display setting is showing the opposite result.
- BP-3044 Checkout order review table not refreshing when switching payment method.
- BP-3078 Riverty/Afterpay (old) failed transactions fix.

= 3.9.0 =
Add support for Wordpress 6.3.1
Add support for WooCommerce 8.1.0
- PayByBank improvements [BP-2842]
- Payment fee decimal amount not shown in front-end [BP-2866]
- Sofort support currencies CHF and GBP [BP-2837]
- Description field should not support multiple rows [BP-2839]
- Add In3 API V3 selection [BP-2849]

= 3.8.0 =
Add support for Wordpress 6.3.0
Add support for WooCommerce 8.0.0
- Add payment method: PayByBank [BP-2676]
- Rename creditcards into cards [BP-2620]

= 3.7.0 =
Add support for Wordpress 6.2.2
Add support for WooCommerce 7.8.0
- Update payment method logo's [BP-2446]
- In3 method version changes [BP-2294]
- Preparation for adding support for Buckaroo Subscriptions [BP-2323]
- Riverty/AfterPay (old) shipping cost bug fix [BP-2559]
- Riverty/AfterPay Separate Capture and Refund fix [BP-2486]
- Riverty | Afterpay (old) B2B and B2C fix [BP-2493]

= 3.6.1 =
- Fix Tested up to tag to correct wp version
= 3.6.0 =
- Fix Klarna gender values [BP-2112]
- Fatal error: Unsupported operand types: string + int (PHP 8.0) [BP-2126]
- Fix compatibility with WC < v5.8
- Remove the -R addition for refunds [BP-2012]
- Remove HTML tags from capture form [BP-2143]
- Fix for displaying the customer name in Plaza for Riverty (new) [BP-2238]
- Remove Request to Pay payment method [BP-2253]
- Apple Pay error: Something went wrong while processing [BP-2315]
- Support for WooCommerce 7.5.1 [BP-2339]
- Payment fee is not working for Klarna [BP-2295]
- Support sequential order numbers skyverge [BP-1485]
- Variable products incorrect amount [BP-2236]
- Minor translation changes (ENG) [BP-1764]
- Update README with new WooCommerce version support [BP-2339]
- Added YourSafe Issuer to Ideal[BP-2449]
- Add a notification when one of the payment methods is in test [BP-1982]
= 3.5.0 =
- Support for WooCommerce 7.0.0
- Update README.MD file [#127]
- Remove 'Handelsbanken' [BP-1471]
- Notice: Undefined index: PayPalExpressController.php [BP-2063]
- Rebranding Afterpay to Riverty [BP-1865]

= 3.4.0 =
- Change gender selection for BNPL methods [BP-1896]
- Add contribution guidelines [BP-1873]
- Add string 'A valid iDEAL bank is required' to .pot file and add Dutch ( Thank you @Robbertdk )
- Error when loading empty credicard
- Add KlarnaKP to WooCommerce BP-1886
- Add product images to the Afterpay request (ImageUrl) [BP-1768]
- Slow load time for javascript files [BP-1857]
- Fix Payconiq payment method in live mode. [BP-2030]

= 3.3.0 =
- Support for Wordpress version 6.0 & WooCommerce 6.7.0 [BP-1861]
- Fix undefined array key show_in_checkout
- Add paypal express merchandid admin field [BP-1477]
- Explain variable transaction description [BP-1492]
- Missing Payment method description in WooCommerce templates. [BP-1490]
- Update README file [BP-1508]
- The Apple Pay logo is not visible when the old logos are being used. [BP-1813]
- Support Afterpay B2B [BP-1468]
- Added support for payment fee vat display mode(including / excluding). [BP-1858]

= 3.2.3 =
- Update esc_html to esc_url for image urls
- Sanitized some fields
- Other Improvments

= 3.2.2 =
- Don't use calls like // phpcs:ignore [BP-1727]
- Use wp_enqueue commands [BP-1728]
- Sanitize, Escape and Validate variables [BP-1528]
- Adding code sniffer for Woocommerce to the GitHub pipeline [BP-1534]

= 3.2.1 =
- Don't use calls like // phpcs:ignore [BP-1727]
- Use wp_enqueue commands [BP-1728]
- Sanitize, Escape and Validate variables [BP-1528]
- Adding code sniffer for Woocommerce to the GitHub pipeline [BP-1534]

= 3.2.0 =
- Support for Wordpress: 6.0 and WooCommerce: 6.4.1
- Paypal seller protection address information jumbled [BP-1479]
- Variables and options must be escaped when echo'd [BP-1529]
- Sanitize, Escape and Validate variables [BP-1528]
- Remove Libraries Already In Core [BP-1527]
- Improve files naming [BP-1526]
- Remove "png instead of svg" setting and "new" png images [BP-1498]
- Limit payment logo size [BP-1459]

= 3.1.0 =

- Support for Wordpress: 5.9.3 and WooCommerce: 6.3.1
- Log report notice when no log file present
- Add new .SVG icons to all plugins [BP-1267]
- Fix for error when creating PayPerEmail [BP-1352]
- Apple Guid field missing form settings page [BP-1430]
- Buckaroo plugin settings are not visible for a specific merchant [BP-1431]
- Add PayPal express button [BP-209]

= 3.0.0 =

3.0.0 is a MAJOR release, it may break things so we advice to do additional testing and treat it with caution.

With 3.0.0 we are taking a big step in improving our services within WooCommerce. We have technically improved our installation and configuration process and made it more understandable for everyone. Below are some highlights of our improvements:

- Code upgrade: Removed a lot of duplication, started using constants for statuses and codes everywhere, removed unused services and code, started using code into common/single places, reduced long methods sizes.

- Improved logging: We upgraded our logging service and made it more flexible. Option to store in in files or database (or both).

- Improved our UI: We changed a lot, we improved and simplified our configuration process. Added a option to check you filled credentials, moved some advanced configuration to specific methods and changed icons for the payment methods to a single style/design.

For more details about this release: https://docs.buckaroo.io/docs/nl/woocommerce-releases

= 2.24.1 =

- transactions fail after update to 2.24.0[BP-1137]
- translated payment providers description, updated translation files

= 2.24.0 =

- Capture amount incorrect (fix admin area url & already captured amount) [BP-997]
- Add EPS as payment method in the plugin [BP-1106]
- Company name is not sent to Buckaroo platform [BP-1102]
- Blank page upon Payconiq cancellation [BP-1062]

= 2.23.0 =
- Fix VatNumber / translation [BP-957]
- Add accept terms & conditions for Billink [BP-996]
- 1 cent rounding issue with captures [BP-1009]
- iDIN verification does not work [BP-1050]
- Change SOAP certificate [BP-987]

= 2.22.1 =
- Capture amount incorrect (fix for cc and ap old) [BP-997]
- fix capture transactions description pattern handling

= 2.22.0 =
- Capture amount incorrect [BP-997]
- fix Fatal error: Uncaught Error: Call to a member function get_total() on null gateway-buckaroo.php on line 101
- Afterpay transaction can't be placed successfully (invisible error) [BP-998]
- Buckaroo plugin conflicts with a other plugin (Fliuentform) after our latest update [BP-999]

= 2.21.0 =
- Make it possible to set the minimum and maximum amount for a payment method to be displayed [BP-926]
- PayPal (V2) cancellation returns to the homepage and not back to the cart page [BP-941]
- "A non-numeric value encountered" fix
- Afterpay issue regarding wrong image file type [BP-963]
- Make VAT number field non-required (BILLINK) [BP-957]
- Add CreditCard brand PostePay [BP-970]
- Add translation for Billink error [BP-960]
- WooCommerce - Afterpay (new+old) date of birth field improvement [BP-964]

= 2.20.0 =
- Make it possible for Billink to enable both methods (B2B+B2C)
- update apple-developer-merchantid-domain-association
- Refunds from Plaza are not visible in Admin
- AP old - error during partial refund
- CC and AP - capture form has no payment fee and failed
- AP new - HTTP 500 during capture attempt
- capture button for CC and AP is not clickable
- AP new - error during partial refund

= 2.19.0 =
- Add payment method Belfius
- Add verification method iDIN (age verification)
- Add Klarna 'Pay' and 'PayInInstallments'
- Bancontact / MisterCash broken settings
- Plugin does not work when "Transaction Description" is not filled in
- Identifier 'customerTypeElement' has already been declared
- Change payment icons
- Add Payment method Billink (add article description)
- Remove iDEAL issuer Moneyou from all plugins.
- Status not pushed successfully after custom change to invoice number
- Fix some PHP notices
- PHP8 is giving errors for the payment method "Afterpaynew"

= 2.18.1 =
- Fix In3 not working [BP-751]
- Bancontact possibly disable after update to 2.18 [BP-725]
- change default Billink name for front end
- Fix for Billink remaining price item
- Fix for Billink price rounding
- tags in description field [BP-688]
- Fix for AP, Klarna with MyParcel pick-up points [BP-563]
- In3 Order As missing Dutch translations [BP-689]
- Deliverytype error [BP-684]
- Apple pay error on product page and checkout [BP-687]
- It isn't possible to do refund for order with 10% discount and shipping costs [BP-587]

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
