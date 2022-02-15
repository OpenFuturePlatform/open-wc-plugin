<?php

/**
 * Plugin Name: OPEN Platform Gateway
 * Plugin URI: https://openfuture.io/plugins/woocommerce-gateway-open/
 * Description: Blockchain address payment using OPEN Platform.
 * Author: OPEN Platform
 * Author URI: https://openfuture.io/
 * Version: 1.0.0
 * Requires at least: 5.6
 * Tested up to: 5.8
 * WC requires at least: 5.7
 * WC tested up to: 5.9
 * Text Domain: open-platform-gateway
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function open_init_gateway()
{

    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        require_once 'includes/class-wc-gateway-open.php';
        add_action('init', 'open_wc_register_blockchain_status');
        add_filter('woocommerce_valid_order_statuses_for_payment', 'open_wc_status_valid_for_payment', 10, 2);
        add_action('open_check_orders', 'open_wc_check_orders');
        add_action('wp_enqueue_scripts', 'scripts');
        add_filter('woocommerce_payment_gateways', 'open_wc_add_open_class');
        add_filter('wc_order_statuses', 'open_wc_add_status');
        add_action('woocommerce_admin_order_data_after_order_details', 'open_order_admin_meta_general');
        add_action('woocommerce_order_details_after_order_table', 'open_order_meta_general');
    }
}

add_action('plugins_loaded', 'open_init_gateway');

// Used for checking payment at background
function open_activation()
{
    if (!wp_next_scheduled('open_check_orders')) {
        wp_schedule_event(time(), 'hourly', 'open_check_orders');
    }
}

register_activation_hook(__FILE__, 'open_activation');

function open_deactivation()
{
    wp_clear_scheduled_hook('open_check_orders');
}

register_deactivation_hook(__FILE__, 'open_deactivation');

// Script for showing QRCode
function scripts()
{
    wp_enqueue_script(
        'jquery',
        plugins_url('js/jquery.min.js#deferload', __FILE__),
        array('jquery')
    );
    wp_enqueue_script(
        'qrcode',
        plugins_url('js/qrcode.min.js#deferload', __FILE__),
        array('jquery')
    );
    wp_enqueue_script(
        'clipboard',
        plugins_url('js/clipboard.min.js#deferload', __FILE__),
        array('jquery')
    );
}

// WooCommerce
function open_wc_add_open_class($methods)
{
    $methods[] = 'WC_Gateway_Open';
    return $methods;
}

function open_wc_check_orders()
{
    $gateway = WC()->payment_gateways()->payment_gateways()['open'];
    return $gateway->check_orders();
}

/**
 * Register new status with ID "wc-blockchain-pending" and label "Blockchain Pending"
 */
function open_wc_register_blockchain_status()
{
    register_post_status('wc-blockchain-pending', array(
        'label' => __('Blockchain Pending', 'open'),
        'public' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Blockchain pending <span class="count">(%s)</span>', 'Blockchain pending <span class="count">(%s)</span>'),
    ));
}

/**
 * Register wc-blockchain-pending status as valid for payment.
 */
function open_wc_status_valid_for_payment($statuses, $order)
{
    $statuses[] = 'wc-blockchain-pending';
    return $statuses;
}

/**
 * Add registered status to list of WC Order statuses
 * @param array $wc_statuses_arr Array of all order statuses on the website.
 */
function open_wc_add_status(array $wc_statuses_arr): array
{
    $new_statuses_arr = array();

    // Add new order status after payment pending.
    foreach ($wc_statuses_arr as $id => $label) {
        $new_statuses_arr[$id] = $label;

        if ('wc-pending' === $id) {  // after "Payment Pending" status.
            $new_statuses_arr['wc-blockchain-pending'] = __('Blockchain Pending', 'open');
        }
    }

    return $new_statuses_arr;
}

/**
 * Add Admin Page order Open meta after General Billing
 *
 * @param WC_Order $order WC order instance
 */
function open_order_admin_meta_general(WC_Order $order)
{
    if ($order->get_payment_method() == 'open') {

        $url = "https://ropsten.etherscan.io/address/{$order->get_meta('_op_address')}";

        ?>
        <br class="clear"/>
        <h3>Open Platform Data</h3>
        <div class="open">
            <p>Open Wallet Address#</p>
            <div class="open-qr" style="width: 100%">
                <a href="<?php echo $url; ?>" target="_blank">
                    <?php echo esc_html($order->get_meta('_op_address')); ?>
                </a>
            </div>
        </div>
        <br class="clear"/>
        <?php
    }
}

/**
 * Add order Open meta after General and before Billing
 *
 * @param WC_Order $order WC order instance
 */
function open_order_meta_general(WC_Order $order)
{
    if ($order->get_payment_method() == 'open') {

        $address = $order->get_meta('_op_address');
        $url = "https://ropsten.etherscan.io/address/{$address}";

        ?>

        <br class="clear"/>
        <h3>Open Platform Data</h3>
        <div class="open">
            <p>Open Wallet Address#</p>
            <div class="open-qr" style="width: 100%">
                <a href="<?php echo $url; ?>" target="_blank">
                    <?php echo esc_html($order->get_meta('_op_address')); ?>
                </a>
                <iframe src="https://api.openfuture.io/widget/transactions/address/<?php echo esc_html($order->get_meta('_op_address'));  ?>" style="border:0px #ffffff none;" name="openPaymentTrackWidget"  height="360px" width="640px" allowfullscreen></iframe>
                <div id="qrcode" style="width:128px; height:128px; margin-top:15px;">

                </div>
                <script type="text/javascript">
                    const qrcode = new QRCode(document.getElementById("qrcode"), {
                        width: 128,
                        height: 128
                    });
                    qrcode.makeCode("<?php echo $address; ?>");
                </script>
            </div>
        </div>
        <br class="clear"/>
        <?php
    }
}

function binance_get_currency_live(): array
{
    $args = array(
        'method' => "GET",
        'headers' => array(
            'Content-Type' => 'application/json'
        )
    );

    $url = "https://api.binance.com/api/v3/avgPrice?symbol=ETHBUSD";
    $response = wp_remote_request(esc_url_raw($url), $args);
    if (is_wp_error($response)) {
        return array(false, $response->get_error_message());
    } else {
        $result = json_decode($response['body'], true);
        return array(true, $result);
    }
}