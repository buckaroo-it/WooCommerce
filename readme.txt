=== Buckaroo Woocommerce Payments Plugin ===
Contributors: buckaroosupport
Author: Buckaroo
Tags: WooCommerce, payments, Buckaroo
Requires at least: 5.3.18
Tested up to: 7.0
Stable tag: 4.9.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is a plug-in with countless payment methods, all of which are SEPA proof.

== Description ==

The [Buckaroo WooCommerce Payments](https://www.buckaroo.nl/plugins/woocommerce) plugin is a simple, effective solution for handling payments in your WooCommerce webshop.
It supports a wide range of SEPA-compliant and international payment methods, is easy to install, and saves you valuable time.
Improve your checkout experience and increase conversions - try the Buckaroo plugin today!

Payment method support list by Buckaroo WooCommerce payments plugin

1. Alipay
2. Apple Pay
3. Bancontact
4. Belfius
5. Billink
6. Bizum
7. Blik
8. Cards (American Express, Visa, MasterCard, VPAY, Visa Electron, Maestro, Carte Bleue, Carte Bancaire, Dankort, Nexi, PostePay)
9. EPS
10. Giftcards
11. Google Pay
12. goSettle
13. iDEAL | Wero
14. In3
15. KBC
16. Klarna
17. MB Way
18. Multibanco
19. PayByBank
20. Payconiq
21. PayPal
22. PayPerEmail
23. Przelewy24
24. Riverty
25. SEPA Credit Transfer (Bank transfer)
26. SEPA Direct Debit
27. Swish
28. Trustly
29. Twint
30. WeChat Pay
31. Wero (BE, DE, FR)

== Release notes ==
For detailed release notes, please visit our [GitHub repository](https://github.com/buckaroo-it/WooCommerce).

== Contact ==
Need help installing the WooCommerce plugin? Reach out to our technical support team at [support@buckaroo.nl](mailto:support@buckaroo.nl).

== Installation ==
Before proceeding, ensure that you have a Buckaroo account.
If you haven’t already, you can create or request an account via the following links:
- For English: [Create Account](https://www.buckaroo.eu/sign-up)
- For Dutch: [Account Aanmaken](https://www.buckaroo.nl/account-aanmaken)

To install the Buckaroo plugin for WooCommerce, please refer to our comprehensive documentation pages.
These guides provide detailed step-by-step instructions: [WooCommerce Installation Guide](https://docs.buckaroo.io/docs/woocommerce-installation).

For assistance with setting up your account, reach out to our customer care team:
Email: [wecare@buckaroo.nl](mailto:wecare@buckaroo.nl)
Phone: [+31 (0)30 711 50 00](tel:+31 (0)30 711 50 00)

If you encounter any technical queries while installing or using the plugin, our dedicated technical support team is here to help:
Email: [support@buckaroo.nl](mailto:support@buckaroo.nl)

We’re committed to ensuring a smooth installation process and providing ongoing support for your WooCommerce integration with Buckaroo.

== Contributors & Developers ==
The “Buckaroo Woocommerce Payments Plugin” has been translated into 3 locales. Thank you to [the translators](https://translate.wordpress.org/projects/wp-plugins/wc-buckaroo-bpe-gateway/contributors/) for their contributions!

[Translate “Buckaroo Woocommerce Payments Plugin” into your language.](https://translate.wordpress.org/projects/wp-plugins/wc-buckaroo-bpe-gateway/)

== Changelog ==
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

Security updates:
BTI-1129 Resolved an issue with AJAX action.

= 4.8.2 =
Improvements
BTI-1124 Fixed an issue where orders using Klarna (MoR) could not be placed through the WooCommerce legacy checkout when the address was in the Netherlands.

= 4.8.1 =
Maintenance release: corrected version metadata; includes all 4.8.0 features

= 4.8.0 =
Improvements & New Features
BTI-902 Add support for WooCommerce 10.7.0 en WordPress 7.0
BTI-685 Added support for WooCommerce 10.5.0, 10.5.1, 10.5.2, and 10.5.3.
BTI-603 Added Google Pay as a payment method.
BTI-577 Added Klarna (MoR) as a payment method.
BTI-638 Removed the API version setting for the In3 payment method and enforced the V3 API.
BTI-629 Prevented Express payment method scripts from loading on product pages when Express methods are disabled.
BTI-717 Fixed an issue where stock was not updated when a pending refund was processed later.
BTI-665 Fixed an issue where bank transfer payment instructions were always displayed in English despite different culture codes being sent.
BTI-708 Fixed an issue where the iDEAL | Wero frontend label translation was not saved and remained in English.
BTI-509 Fixed an Riverty tax issue regarding the percentage of payment fee’s.
BTI-1035 Avoid duplicate Riverty phone number field when WooCommerce billing phone is already provided.

= 4.7.2 =
Bug Fixes
BTI-4 Fixed Apple Pay order creation with bundle / discount-rule plugins: line subtotals, taxes, and shipping cost now read from the customer's existing cart so the order matches what was authorized in the wallet sheet.

= 4.7.1 =
Bug Fixes
BTI-4 Fixed Apple Pay incorrect order amount when used with third-party discount plugins (e.g. Woo Discount Rules "Buy X Get Y").

= 4.7.0 =
Improvements & New Features
BP-5249 Added support for WordPress 6.9 and WooCommerce 10.4.2 / 10.4.3.
BTI-102 Updated the payment method name &  logo from “iDEAL” to the co-branding “iDEAL | Wero”.
BP-5136 Added the Wero payment method (BE, DE, FR).
BP-4950 Added the Bizum payment method.
BP-4951 Added the Swish payment method.
BP-4952 Added the Twint payment method.
BP-4929 Added priority handling for Express payment buttons.
BP-5199 Removed the separate authorize/capture flow for Billink; only the PAY flow is now supported
BTI-102 We’ve updated the readme.txt file with the list of supported payment methods.
Bug Fixes
BP-5081 Fixed an issue where shipping costs were sent with 0% VAT when the shipping tax class was based on cart products.
BP-5109 Fixed an issue where “Show payment procedures” did not work correctly for Bank Transfer.
BP-5108 Fixed an issue where PUSH payments failed due to special characters in the description.
BP-5331 Fixed an issue preventing a new transaction attempt after cancelling the first attempt.
BP-5347 Fixed missing payment method input fields on the WooCommerce checkout (related to BP-5249).
BP-5348 Fixed an issue where payments remained in a pending state (related to BP-5249).
= 4.6.1 =
BP-4600 Add payment method: Twint
BP-4578 Add payment method: Trustly
BP-4543 The payment method "iDEAL In3" is rebranded back to its original name "In3".
BP-4567 Add "Automatic Configuration" button to check the Buckaroo subscriptions and configure the plugin.
BP-4587 Restructure plugin settings.
BP-4569 Improve the way express payment buttons are displayed (like: Apple Pay & PayPal Express).
BP-4592 Update README.md with the latest supported versions.
BP-4534 Fix: No clear error message displayed when the payment was getting rejected.
BP-4553 Fix: Klarna (authorize/capture) refund from the Buckaroo plaza not updating in WooCommerce (order status and amount).
BP-4666 Fix: Rejection message for Rivery was not showed correctly in some specific scenario's.
BP-4581 Fix: Riverty Missing articles in partial refund request (JSON).
= 4.5.1 =
BP-4538: Optimized storage of buckaroo_settlement data in WooCommerce orders.
BP-4537: Prevented autoloading of unnecessary options on the frontend.
BP-4539: Fix: Corrected incorrect usage warning for billing_address_1.
BP-4549: Fix: Resolved warning for undefined array key result.
BP-4546: Fix: Resolved undefined array key emailAddress issue for Apple Pay.
BP-4559: Fix: Ensured failed PUSH status does not overwrite custom status.
= 4.4.1 =
BP-4511 Fix: Duplicate order created and no plaza transaction is generated for Klarna authorize/capture orders.
BP-4540 Fix: Orders paid with Apple Pay have sometimes an incorrect amount in WooCommerce with discount rules (price paid is correct).
BP-4555: Billink transaction rounding issue with tax
= 4.4.0 =
Add support for WooCommerce 10.0.3, 10.0.2, 10.0.1 & 10.0.0.
BP-4531: Cart empties when adding product (WooCommerce 10 issue)
BP-4494: Keep track for transactionKey for Pending processing orders
= 4.3.1 =
BP-4476: Add support for Billink V2
BP-4479: Keep track for transactionKey for SEPA on-hold orders
New PHP SDK Version that will solve “guzzle not found” error
= 4.3.0 =
BP-3630: Add payment method: Alipay
BP-3631: Add payment method: WeChat Pay
BP-4428: PayPal Express - Plugin does not process response Address
BP-4372: Push cannot be processed if it contains special characters in customer's IBAN
= 4.2.3 =
BP-4362 Fix: Apple pay incorrect amount in WooCommerce in combination with discount rules
= 4.2.2 =
BP-4406 Fix: iDEAL in3 critical error
= 4.2.1 =
BP-4404 Fix: Invoice/order number for Riverty transactions in the Buckaroo plaza are incorrect due to a suffix that is added.
BP-4402 Fix: PayPal Express checkout returns error “Cannot process Buckaroo transaction”
= 4.2.0 =
BP-4323 Fix: Warning: get_cart was called incorrectly.
BP-4326 Fix: CustomerNumber has a wrong value (“0”) for Riverty transactions.
BP-4325 Fix: Error with redirect no reply handler strategy applied.
BP-4334 Improve code styling in general.
= 4.1.0 =
BP-4304 Payment method authorize on payment noservice you requested does not exist
BP-4307 Implement "coenjacobs/mozart" package to scope namespaces to avoid plugin conflicts
BP-4301 Test Transaction Message is not shown for HPOS
BP-4300 Restore Removed Card Types for Redirect Flow
= 4.0.3 =
add new svg logo as plugin icon
in the DisabledGateways class, skip methods that are not Buckaroo payment methods
fix an issue with deprecated cards (these will need to be reverted for the "redirect" type)
= 4.0.2 =
Add .wordpress-org directory to store all plugin images for display
BP-4294 Update SVN job to upload images and exclude them from plugin compilation
BP-4292 Refactor credit card script to allow regular form submission listeners to function correctly
= 4.0.1 =
Reorder main GitHub Action to submit to WordPress before zipping the plugin
Update Babel dependencies to fix security vulnerabilities
BP-4294 fix credit card issue on redirect and hiding non-supported currency methods
BP-4292 Update rebranding logo and header image
= 4.0.0 =
BP-3820 Refactor WooCommerce Plugin Structure and Setup Composer Autoloading
BP-3821 Install PHP SDK and Integrate It into WooCommerce Plugin
BP-3822 Refactor Class Structure for Better Design and Maintainability
BP-3853 Change plugin menu icon to the new Buckaroo branding design
BP-4019 Add support for EUR currency for Przelewy24
BP-4040 Always send an identifier if none is sent
BP-4112 Apple Pay orders receive duplicate processing status updates
BP-4147 Apple Pay "Something went wrong while processing your payment"
BP-4163 Add separate authorize/capture setting for Billink
BP-4164 Remove "Use new icons" selection setting
BP-4171 Add refund description functionality
BP-4190 Replace "Cards (CSE)" with "Cards Hosted Fields"
BP-4228 Update "Website Key" to "Store Key" in the WooCommerce plugin
BP-4233 Use more generic "Pay by Bank" logo
BP-4258 Remove the payment method Sofort (discontinued)
BP-4261 Remove iDEAL issuer configuration option (due to iDEAL 2.0)
BP-4262 Implement handling for serialize_precision to prevent JSON precision issues
BP-4021 Rebranding "Knaken Settle" into "goSettle" (WooCommerce Refactor)
BP-3864 Payconiq doesn't place an order
BP-3865 Credit cards don't place an order
BP-3870 PayByBank doesn't update status when refunding
BP-3871 Translation is not working
BP-3876 Translation issue when using Blocks checkout with Billink
BP-4003 Improve Apple Pay button alignment on the product page
BP-4041 Buckaroo enqueues Gutenberg block JS files even when no block theme is used (#302)
BP-4179 Credit cards shown in both Inline and Redirect checkout when Inline option is selected
BP-4182 Issue with Payment Fee Handling Between Apple Pay and PayPal at Checkout
BP-4218 Always en-GB culture code in the API requests
BP-4240 Apple Pay button is not shown on the cart page
BP-4241 Status doesn't change after refund from Plaza with 'Authorize' flow
BP-4243 First invalid refund affects final order status
BP-4246 Partial refunds fail with Riverty from WooCommerce admin
BP-4247 Error with Klarna payments for orders above €1000
BP-4249 Issues with PayPal Express button

= 3.14.3 =
BP-4075 Fix missing credit card capture button on the order edit page #303.

= 3.14.2 =
BP-4075 Resolve missing capture button on order edit page #303

= 3.14.1 =
BP-3907 Issuer list not displayed correctly in legacy checkout on latest Chrome version

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
