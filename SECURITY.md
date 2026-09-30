# Security Policy

Buckaroo takes the security of its payment plugins seriously. This document explains how to report a vulnerability in the Buckaroo WooCommerce Payments Plugin.

## Supported versions

Only the most recent release of the plugin is supported. Security fixes are shipped in a new release on GitHub and in the WordPress.org plugin directory, and are not backported to older releases. Please confirm that your shop runs the latest version before reporting an issue.

## Reporting a vulnerability

Please do not report security issues through GitHub issues, pull requests, or the public support channels.

Report vulnerabilities to **security@buckaroo.nl**. Encrypt your findings with our public PGP key so that the information cannot fall into the wrong hands:
https://www.buckaroo.nl/media/2905/pub_key.txt

You can also use GitHub's private vulnerability reporting on this repository, through the Security tab.

Reports are handled under the Buckaroo Responsible Disclosure Policy:
https://www.buckaroo.nl/media/fwdj2uvv/responsible-disclosure-policy-eng.pdf

### What to include

- A description of the vulnerability and its impact.
- The plugin version, plus your WordPress, WooCommerce and PHP versions.
- Steps to reproduce, ideally with a proof of concept.
- Relevant log fragments or screenshots.

Never include real payment data, credentials, or personal data in a report. Use the Buckaroo test environment and test credentials to demonstrate the issue.

### What you can expect

- A confirmation that we received your report.
- An assessment of the issue and an indication of when we expect to resolve it.
- An update when a fix is released.
- Credit in the release notes, if you would like to be named.

A reward may be offered for a previously unknown issue. The conditions are described in the Responsible Disclosure Policy linked above.

### Out of scope

- Vulnerabilities in WordPress core, WooCommerce, or third party plugins and themes. Please report those to their maintainers.
- Reports about outdated plugin versions without a working proof of concept of an exploit.
- Findings from automated scanning tools without a demonstrable proof of concept.
- AI generated reports without proof of manual human validation.

### Please do not

- Abuse the issue or go further than needed to demonstrate it, for example by downloading large amounts of data.
- Share the vulnerability with others before we have resolved it.
- Use social engineering, denial of service attacks, or spam.

## Security in this plugin

The plugin communicates with the Buckaroo Payment Engine over HTTPS, and every push message is authenticated before it is processed. The push URL follows the shop's own WordPress address, so serving the shop over HTTPS remains the responsibility of the merchant.

If you believe a signature check, a push message handler, or the handling of credentials in the plugin can be bypassed, please treat it as a security report and use the channel above rather than the issue tracker.