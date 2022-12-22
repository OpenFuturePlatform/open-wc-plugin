# Open Platform Payment Gateway for WooCommerce

A WooCommerce payment gateway that allows your customers to pay with cryptocurrency with Open Platform Integration

## Configuring Open Platform Woocommerce

You will need to set up an account on [Open Platform](https://api.openfuture.io).

Within the WordPress administration area, go to the WooCommerce > Settings > Payments page, and you will see Open Platform in the table of payment gateways.

Clicking the Manage button on the right-hand side will take you into the settings page, where you can configure the plugin for your store.

## Settings

### Enable / Disable

Turn the Open Platform payment method on / off for visitors at checkout.

### Title

Title of the payment method on the checkout page

### Description

Description of the payment method on the checkout page

### Access Key / Secret Key

Your Open Platform Access/Secret keys. Available within the [Open Platform](https://api.openfuture.io/applications).

Using an API keys allows your website to periodically check Open Platform for payment confirmation.

### Test mode

If this is checked, gateway will use ropsten network as a blockchain

### Debug log

Whether to store debug logs.

If this is checked, these are saved within your `wp-content/uploads/wc-logs/` folder in a .log file prefixed with `open-`

## Prerequisites

To use this plugin with your WooCommerce store you will need:

* [WordPress] (tested up to 6.1.1)
* [WooCommerce] (tested up to 3.4.3)

## License

This project is licensed under the Apache 2.0 License

## Changelog

## 1.0.0 ##
* Open Platform Woocommerce

[//]: # (Comments for storing reference material in. Stripped out when processing the markdown)

[Open Platform]: <https://api.openfuture.io/>
[WooCommerce]: <https://woocommerce.com/>
[WordPress]: <https://wordpress.org/>
