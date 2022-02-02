=== Open Platform Payment Gateway for WooCommerce ===
Contributors: open platform
Plugin URL: https://commerce.openfuture.io/
Tags: open, payment, woo, woocommerce, ecommerce, bitcoin, ethereum, blockchain, crypto, cryptocurrency
Requires at least: 3.0
Requires PHP: 5.6
Tested up to: 5.2
Stable tag: 1.1.4
License: GPLv2 or later

== Description ==

This plugin implements a payment gateway for WooCommerce to let buyers pay through Open Platform with Bitcoin, Ethereum and Binance on your WooCommerce store.

== Installation ==

= From your WordPress dashboard =

1. Visit 'Plugins > Add New'
2. Search for 'open platform gateway'
3. Activate Open Gateway from your Plugins page.

= From WordPress.org =

1. Download Open Platform.
2. Upload to your '/wp-content/plugins/' directory, using your favorite method (ftp, sftp, scp, etc...)
3. Activate Open Gateway from your Plugins page.

= Once Activated =

1. Go to WooCommerce > Settings > Payments
2. Configure the plugin for your store

= Configuring Open Platform =

* You will need to set up an account on https://api.openfuture.io/applications
* Within the WordPress administration area, go to the WooCommerce > Settings > Payments page, and you will see Open Gateway in the table of payment gateways.
* Clicking the Manage button on the right side will take you into the settings page, where you can configure the plugin for your store.

= Enable / Disable =

Turn the Open Gateway payment method on / off for visitors at checkout.

= Title =

Title of the payment method on the checkout page

= Description =

Description of the payment method on the checkout page

= Public/Private Key =

Your Open Platform public/private key. Available within the https://api.openfuture.io/applications/{applicationId}

Using an API keys allows your website to periodically check Open Platform for payment transactions.

= Debug log =

Whether to store debug logs.

If this is checked, these are saved within your `wp-content/uploads/wc-logs/` folder in a .log file prefixed with `open-`

The plugin supports all cryptocurrencies available at https://commerce.coinbase.com/

= Prerequisites=

To use this plugin with your WooCommerce store you will need:
* WooCommerce plugin

== Changelog ==

= 1.0.0 =
* Coinbase Commerce
