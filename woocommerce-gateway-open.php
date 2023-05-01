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

if (!function_exists('oppg_init_gateway')) {

    function oppg_init_gateway()
    {

        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            require_once 'includes/class-wc-gateway-open.php';
            require_once 'includes/class-wc-gateway-api-handler.php';
            add_action('init', 'oppg_wc_register_blockchain_status');
            add_action('admin_enqueue_scripts', 'oppg_scripts');
            add_filter('script_loader_tag', 'moduleTypeScripts', 10, 2);
            add_filter('woocommerce_valid_order_statuses_for_payment', 'oppg_wc_status_valid_for_payment', 10, 2);
            add_action('open_check_orders', 'oppg_wc_check_orders');
            add_filter('woocommerce_payment_gateways', 'oppg_wc_add_open_class');
            add_filter('wc_order_statuses', 'oppg_wc_add_status');
            add_action('woocommerce_admin_order_data_after_order_details', 'oppg_order_admin_meta_general');
            add_action('woocommerce_order_details_after_order_table', 'oppg_order_meta_general');

            add_action('woocommerce_settings_tabs', 'wc_settings_tabs_open_wallet_list_tab');
            add_action('woocommerce_settings_open_wallet_list', 'display_open_wallet_list_tab_content');
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

function moduleTypeScripts($tag, $handle)
{
    $tyype = wp_scripts()->get_data($handle, 'type');

    if ($tyype) {
        $tag = str_replace('src', 'type="' . esc_attr($tyype) . '" src', $tag);
    }

    return $tag;
}

function oppg_scripts()
{
    wp_enqueue_script(
        'qrcode',
        plugins_url('/js/qrcode.min.js#deferload', __FILE__),
        array('jquery')
    );
    wp_enqueue_script(
        'clipboard',
        plugins_url('/js/clipboard.min.js#deferload', __FILE__),
        array('jquery')
    );
    wp_enqueue_script(
        'sweetalert',
        'https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js',
        array('jquery')
    );
    wp_enqueue_script(
        'open',
        plugins_url('/js/open.js', __FILE__),
        array('jquery')
    );
    wp_enqueue_script(
        'crypto',
        'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js',
        array('open')
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
        <br class="clear" />
        <h3>Open Platform Data</h3>
        <div class="open">
            <p>Open Wallet Address#</p>
            <div class="open-qr" style="width: 100%">
                <?php
                foreach ($addresses as $address) {
                    echo esc_textarea($address['blockchain'] . ":" . $address['address']) . "</br>";
                }
                ?>
            </div>
        </div>
        <br class="clear" />
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

        <br class="clear" />
        <h3>Open Platform Data</h3>
        <div class="open">
            <p>Open Wallet Address#</p>
            <div class="open-qr" style="width: 100%">
                <iframe src="<?php echo esc_url($url); ?>" style="border:0px #ffffff none;" name="openPaymentTrackWidget" height="360px" width="640px" allowfullscreen></iframe>
            </div>
        </div>
        <br class="clear" />
<?php
    }
}


function wc_settings_tabs_open_wallet_list_tab()
{
    $current_tab = (isset($_GET['tab']) && $_GET['tab'] === 'open_wallet_list') ? 'nav-tab-active' : '';
    echo '<a href="admin.php?page=wc-settings&tab=open_wallet_list" class="nav-tab ' . $current_tab . '">' . __("Open Wallets", "woocommerce") . '</a>';
}

function display_open_wallet_list_tab_content()
{

    $gateway = new WC_Gateway_Open();
    $result = $gateway->get_wallets();
    $url = $gateway->get_open_base_url();

    echo '<style> table.user-data th { font-weight: bold; } table.user-data, th, td { border: solid 1px #999; } </style>';

    $table_display = '<table class="user-data" cellspacing="0" cellpadding="6"><thead><tr>
    <th>' . __('Order Key', 'woocommerce') . '</th>
    <th>' . __('Amount', 'woocommerce') . '</th>
    <th>' . __('Paid', 'woocommerce') . '</th>
    <th colspan="4" scope="colgroup">' . __('Blockchains', 'woocommerce') . '</th>
    </tr></thead>
    <tbody>';

    if ($result[0]) {
        $index = 0;

        $table_display_row = '';

        foreach ($result[1] as $order) {

            $table_inner_display_[$index]  = '<table class="user-data" cellspacing="0" cellpadding="6"><thead><tr>
          
            <th>' . __('Blockchain', 'woocommerce') . '</th>
            <th>' . __('Address', 'woocommerce') . '</th>
            <th>' . __('Rate', 'woocommerce') . '</th>
            <th>' . __('Actions', 'woocommerce') . '</th>
            </tr></thead>
            <tbody>';


            foreach ($order['blockchains'] as $address) {
                $table_inner_display_[$index]  .= '<tr>
                <td>' . esc_html($address['blockchain']) . '</td>
                <td><a href="' . esc_html($url) . 'widget/trx/address/' . $address['address'] . '" target="_blank">' . esc_attr($address['address']) . '</a></td>
                <td>' . esc_html($address['rate']) . '</td>';
                if($address['encrypted'] != ''){
                    $table_inner_display_[$index]  .=
                    '<td>     
                        <span id="btnDialog" class="encrypt button-secondary" onclick="myFunction(\'' . $address['encrypted'] . '\')">Decrypt</span>
                    </td>';
                }
                
                $table_inner_display_[$index]  .='</tr>';
            }

            $table_inner_display_[$index]  .= '</tbody></table>';

            $table_display_row  .= '<tr>
            <td>' . esc_html($order['orderKey']) . '</td>
            <td>' . esc_html($order['amount'] . ' ' . $order['currency']) . '</td>
            <td>' . esc_html($order['totalPaid'] . ' ' . $order['currency']) . '</td>
            <td>' . $table_inner_display_[$index] . '</td>
            </tr>';


            $index++;
        }
        $table_display .= $table_display_row;
        echo $table_display . '</tbody></table>';
    } else {
        echo "Not Found";
    }
}
