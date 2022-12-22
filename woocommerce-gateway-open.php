<?php

/**
 * Plugin Name: OPEN Platform Gateway
 * Plugin URI: https://openfuture.io/plugins/woocommerce-gateway-open/
 * Description: Blockchain address payment using OPEN Platform.
 * Author: OPEN Platform
 * Author URI: https://openfuture.io/
 * Version: 1.0.0
 * Requires at least: 5.6
 * Tested up to: 6.1.1
 * WC requires at least: 6.1.1
 * WC tested up to: 6.1.1
 * Text Domain: open-platform-gateway
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if ( ! function_exists( 'oppg_init_gateway' ) ){

    function oppg_init_gateway()
    {

        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            require_once 'includes/class-wc-gateway-open.php';
            add_action('init', 'oppg_wc_register_blockchain_status');
            add_filter('woocommerce_valid_order_statuses_for_payment', 'oppg_wc_status_valid_for_payment', 10, 2);
            add_action('open_check_orders', 'oppg_wc_check_orders');
            add_action('wp_enqueue_scripts', 'oppg_scripts');
            add_filter('woocommerce_payment_gateways', 'oppg_wc_add_open_class');
            add_filter('wc_order_statuses', 'oppg_wc_add_status');
            add_action('woocommerce_admin_order_data_after_order_details', 'oppg_order_admin_meta_general');
            add_action('woocommerce_order_details_after_order_table', 'oppg_order_meta_general');
        }
    }
}

add_action('plugins_loaded', 'oppg_init_gateway');

// Used for checking payment at background
function oppg_activation()
{
    if (!wp_next_scheduled('open_check_orders')) {
        wp_schedule_event(time(), 'hourly', 'open_check_orders');
    }
}

register_activation_hook(__FILE__, 'oppg__activation');

function oppg_deactivation()
{
    wp_clear_scheduled_hook('open_check_orders');
}

register_deactivation_hook(__FILE__, 'oppg__deactivation');


function oppg_scripts()
{
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
    wp_enqueue_script(
        'sweetalert',
        plugins_url('js/sweetalert.min.js#deferload', __FILE__),
        array('jquery')
    );
}

// WooCommerce
function oppg_wc_add_open_class($methods)
{
    $methods[] = 'WC_Gateway_Open';
    return $methods;
}

function oppg_wc_check_orders()
{
    $gateway = WC()->payment_gateways()->payment_gateways()['open'];
    return $gateway->check_orders();
}

/**
 * Register new status with ID "wc-blockchain-pending" and label "Blockchain Pending"
 */
function oppg_wc_register_blockchain_status()
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
function oppg_wc_status_valid_for_payment($statuses, $order)
{
    $statuses[] = 'wc-blockchain-pending';
    return $statuses;
}

/**
 * Add registered status to list of WC Order statuses
 * @param array $wc_statuses_arr Array of all order statuses on the website.
 */
function oppg_wc_add_status(array $wc_statuses_arr): array
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
function oppg_order_admin_meta_general(WC_Order $order)
{
    if ($order->get_payment_method() == 'open') {

        $addresses = $order->get_meta('_op_address')[1];

        ?>
        <br class="clear"/>
        <h3>Open Platform Data</h3>
        <div class="open">
            <p>Open Wallet Address#</p>
            <div class="open-qr" style="width: 100%">
                <?php
                echo time();
                    foreach ($addresses as $address){
                        echo esc_textarea($address['blockchain'].":".$address['address'])."</br>";
                    }
                ?>
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
function oppg_order_meta_general(WC_Order $order)
{
    if ($order->get_payment_method() == 'open') {

        $url = WC_Gateway_Open::generate_open_url($order)

        ?>

        <br class="clear"/>
        <h3>Open Platform Data</h3>
        <div class="open">
            <p>Open Wallet Address#</p>
            <div class="open-qr" style="width: 100%">
                <iframe src="<?php echo esc_url($url); ?>" style="border:0px #ffffff none;" name="openPaymentTrackWidget"  height="360px" width="640px" allowfullscreen></iframe>
            </div>
        </div>
        <br class="clear"/>
        <?php
    }
}