<p align="center">
  <a href="https://www.buckaroo.nl">
    <img src="https://raw.githubusercontent.com/buckaroo-it/Media/main/Buckaroo/README.md%20Headers/buckaroo-woocommerce-header-rounded.png" alt="Buckaroo — Payments for WooCommerce" width="100%">
  </a>
</p>

<h1 align="center">Buckaroo for WooCommerce</h1>

<p align="center">
  <a href="https://wordpress.org/plugins/wc-buckaroo-bpe-gateway/"><img src="https://img.shields.io/wordpress/plugin/v/wc-buckaroo-bpe-gateway.svg?label=release" alt="Latest release"></a>
  <a href="https://wordpress.org/plugins/wc-buckaroo-bpe-gateway/"><img src="https://img.shields.io/wordpress/plugin/wp-version/wc-buckaroo-bpe-gateway.svg?label=WordPress" alt="WordPress version"></a>
  <a href="https://docs.buckaroo.io/docs/woocommerce"><img src="https://img.shields.io/badge/docs-docs.buckaroo.io-1a1a4b.svg" alt="Documentation"></a>
  <a href="https://wordpress.org/plugins/wc-buckaroo-bpe-gateway/"><img src="https://img.shields.io/badge/WooCommerce-download-7f54b3.svg" alt="Download the plugin"></a>
</p>

<p align="center">
  <a href="#about">About</a> &middot;
  <a href="#requirements">Requirements</a> &middot;
  <a href="#installation">Installation</a> &middot;
  <a href="#upgrade">Upgrade</a> &middot;
  <a href="#configuration">Configuration</a> &middot;
  <a href="#payment-methods">Payment methods</a> &middot;
  <a href="#support">Support</a> &middot;
  <a href="#contribute">Contribute</a>
</p>

---

## About

WooCommerce is an open source e-commerce plugin for WordPress, used by shops of every size from small stores to large retailers.

The Buckaroo plugin for WooCommerce connects your shop to the Buckaroo payment gateway, so you can start accepting payments within minutes. Buckaroo is a Dutch Payment Service Provider. The plugin is free to download and every payment method it offers is SEPA proof.

Card payments run through Hosted Fields, which keeps the card entry inside your own checkout instead of redirecting the customer away. Express payment buttons can be placed on the product, cart and checkout pages.

[Full plugin documentation on docs.buckaroo.io](https://docs.buckaroo.io/docs/woocommerce)

---

## Requirements

| Requirement | Supported versions |
|---|---|
| WordPress | 5.3.18 up to 7.0 |
| WooCommerce | 5.0 up to 11.0.0 |
| PHP | 8.0 or higher |

You also need a Buckaroo account. Don't have one yet? [Request an account](https://www.buckaroo.nl/start).

---

## Installation

The quickest route is straight from the WordPress plugin directory:

1. Sign in to your WordPress admin and go to **Plugins → Add new**.
2. Search for **Buckaroo** and open the [Buckaroo Woocommerce Payments Plugin](https://wordpress.org/plugins/wc-buckaroo-bpe-gateway/).
3. Click **Install now**, then **Activate**.
4. Buckaroo now appears in the left-hand admin menu.

<details>
<summary>Installing manually from a .ZIP file</summary>

1. Go to the [releases page](https://github.com/buckaroo-it/WooCommerce/releases) and download the .ZIP file of the latest version.
2. In your WordPress admin, go to **Plugins → Add new → Upload plugin** and select the .ZIP file.
3. Click **Activate** next to the Buckaroo plugin.

</details>

---

## Upgrade

WordPress notifies you when a new version is available. Go to **Plugins** and click **Update now** on the Buckaroo plugin, or enable automatic updates.

> [!TIP]
> Always test an upgrade on a staging environment first and check the [release notes](https://github.com/buckaroo-it/WooCommerce/releases) for breaking changes.

---

## Configuration

After activating the plugin, select **Buckaroo** in the left-hand menu of your WordPress admin to open the general plugin settings.

You will need your **Store key** and **Secret key**, which you can find under [API credentials in Buckaroo Plaza](https://plaza.buckaroo.nl/Configuration/Merchant/ApiKeys). The Store key is unique per store, the Secret key applies to your whole account.

The settings page has an **Automatic Configuration** button that checks which Buckaroo subscriptions are active on your account and configures the matching payment methods for you.

Step-by-step instructions: [Configuring the WooCommerce plugin](https://docs.buckaroo.io/docs/woocommerce-configuration)

---

## Payment methods

The plugin supports the following payment methods. Each one can be enabled or disabled individually and switched between live and test mode.

| | | |
|---|---|---|
| [Alipay](https://docs.buckaroo.io/docs/alipay) | [Apple Pay](https://docs.buckaroo.io/docs/apple-pay) | [Bancontact](https://docs.buckaroo.io/docs/bancontact) |
| [Bank Transfer](https://docs.buckaroo.io/docs/transfer) | [Belfius](https://docs.buckaroo.io/docs/belfius) | [Billink](https://docs.buckaroo.io/docs/billink) |
| [Bizum](https://docs.buckaroo.io/docs/bizum) | [Blik](https://docs.buckaroo.io/docs/blik) | [Credit and debit cards](https://docs.buckaroo.io/docs/creditcards) |
| [EPS](https://docs.buckaroo.io/docs/eps) | [Giftcards](https://docs.buckaroo.io/docs/giftcards) | [iDEAL / Wero](https://docs.buckaroo.io/docs/ideal) |
| [In3](https://docs.buckaroo.io/docs/in3) | [KBC](https://docs.buckaroo.io/docs/kbc) | [Klarna](https://docs.buckaroo.io/docs/klarna-kp) |
| [MB Way](https://docs.buckaroo.io/docs/mb-way) | [Multibanco](https://docs.buckaroo.io/docs/multibanco) | [Pay by Bank](https://docs.buckaroo.io/docs/pay-by-bank) |
| [PayPal](https://docs.buckaroo.io/docs/paypal) | [PayPerEmail](https://docs.buckaroo.io/docs/payperemail) | [Przelewy24](https://docs.buckaroo.io/docs/przelewy24) |
| [Riverty](https://docs.buckaroo.io/docs/riverty) | [SEPA Direct Debit](https://docs.buckaroo.io/docs/sepa-direct-debit) | [Swish](https://docs.buckaroo.io/docs/swish) |
| [Trustly](https://docs.buckaroo.io/docs/trustly) | [Twint](https://docs.buckaroo.io/docs/twint) | [WeChatPay](https://docs.buckaroo.io/docs/wechatpay) |
| [Wero](https://docs.buckaroo.io/docs/wero) |  |  |

> [!IMPORTANT]
> All supported methods appear in the WordPress admin, but you need an active Buckaroo subscription for a method before you can offer it in your checkout.

---

## Support

Having trouble? Work through this list before reaching out:

1. Check the [frequently asked questions](https://docs.buckaroo.io/docs/woocommerce-faq).
2. Confirm you are on the [latest release](https://github.com/buckaroo-it/WooCommerce/releases).
3. Enable debug mode in the plugin settings and reproduce the issue. Logs can be stored in a file, in the database, or both.
4. Verify that your push URL is reachable from outside your network. Buckaroo sends push messages from fixed IP addresses and ports, so make sure these are on your allow list. See [push messages](https://docs.buckaroo.io/docs/integration-push-messages) for the current list.

Still stuck? Contact us and include your WordPress version, WooCommerce version, plugin version, PHP version, the relevant log lines and the transaction key.

- **Bug reports and feature requests:** [open an issue](https://github.com/buckaroo-it/WooCommerce/issues)
- **Technical support:** [support@buckaroo.nl](mailto:support@buckaroo.nl)
- **Phone:** +31 (0)30 711 50 50
- **Gateway status:** [status.buckaroo.io](https://status.buckaroo.io/)

---

## Contribute

We really appreciate it when developers help improve the Buckaroo plugins. Please read our [Contribution Guidelines](https://github.com/buckaroo-it/WooCommerce/blob/develop/CONTRIBUTING.md) before opening a pull request, and target the `develop` branch.

Found a security issue? Please report it privately to [support@buckaroo.nl](mailto:support@buckaroo.nl) instead of opening a public issue.

---

## Versioning

We follow semantic versioning (`MAJOR.MINOR.PATCH`):

- **MAJOR** — breaking changes that require additional testing and caution.
- **MINOR** — new functionality with limited impact.
- **PATCH** — bug fixes and hotfixes only.

All changes are documented on the [releases page](https://github.com/buckaroo-it/WooCommerce/releases).

---

<p align="center">
  <sub>Made with care by <a href="https://www.buckaroo.nl">Buckaroo</a>.<br>
  This document is subject to change; typos and language errors are possible.</sub>
</p>
