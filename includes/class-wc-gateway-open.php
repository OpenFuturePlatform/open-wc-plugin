<?php

if (!defined('ABSPATH')) {
    exit;
}

define('WC_OPEN_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

/**
 * WC_Gateway_Open Class.
 */
class WC_Gateway_Open extends WC_Payment_Gateway
{

    /** @var bool Whether logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;

    /**
     * @var bool
     */
    private $debug;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id = 'open';
        $this->has_fields = false;
        $this->method_title = __('Open Platform', 'open');
        $this->method_description = __('A payment gateway that sends your customers to Open Platform.', 'open');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->enabled = $this->get_option( 'enabled' );
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->debug = 'yes' === $this->get_option('debug', 'no');
        $this->testmode = 'yes' === $this->get_option( 'testmode' );

        self::$log_enabled = $this->debug;

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_open', array($this, 'handle_webhook'));
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log(string $message, string $level = 'info')
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'open'));
        }
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Open Commerce Payment', 'open'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Open Platform Gateway', 'open'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay with Open Platform.', 'open'),
            ),
            'api_key' => array(
                'title' => __('Access Key', 'open'),
                'type' => 'text',
                'default' => '',
                'description' => sprintf(
                    __(
                        'You can manage your Access keys within the Open Platform Application page, available here: %s',
                        'open'
                    ),
                    esc_url('https://api.openfuture.io/applications')
                )
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'open'),
                'type' => 'text',
                'default' => '',
                'description' => sprintf(
                    __(
                        'You can manage your API keys within the Open Platform Application page, available here: %s',
                        'open'
                    ),
                    esc_url('https://api.openfuture.io/applications')
                )
            ),
            'testmode'                            => [
                'title'       => __( 'Test mode', 'woocommerce' ),
                'label'       => __( 'Enable Test Mode', 'open' ),
                'type'        => 'checkbox',
                'description' => __( 'Place the payment gateway in test mode using test API keys.', 'open' ),
                'default'     => 'yes',
                'desc_tip'    => true,
            ],
            'webhook_secret' => array(
                'title' => __('Webhook', 'open'),
                'type' => 'text',
                'description' => __('Webhook to send ex: https://yoursite.com/?wc-api=WC_Gateway_Open', 'open'),

            ),
            'debug' => array(
                'title' => __('Debug log', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woocommerce'),
                'default' => 'no',
                'description' => sprintf(__('Log OPEN API events inside %s', 'open'), '<code>' . WC_Log_Handler_File::get_log_file_path('open') . '</code>'),
            ),
        );
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {

        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }
        ob_start();


        echo '<div  class="open-fields" style="padding:10px 0;">';

        if ($this->testmode){
            woocommerce_form_field('open_currency', array(
                'type' => 'select',
                'label' => __("Choose Test Network", "woocommerce"),
                'class' => array('form-row-wide'),
                'required' => true,
                'options' => array(
                    'ETH' => __("ETH Ropsten", "woocommerce")
                ),
            ), '');
        } else {
            woocommerce_form_field('open_currency', array(
                'type' => 'select',
                'label' => __("Choose Currency", "woocommerce"),
                'class' => array('form-row-wide'),
                'required' => true,
                'options' => array(
                    'BTC' => __("BTC", "woocommerce"),
                    'ETH' => __("ETH", "woocommerce"),
                    'BNB' => __("BNB", "woocommerce"),
                ),
            ), '');
        }

        echo '<div>';

        ob_end_flush();
    }

    /**
     * All available blockchain icons
     * WC core icons.
     * @return array
     */
    public function payment_icons(): array
    {
        return apply_filters(
            'wc_open_payment_icons',
            [
                'btc' => '<img src="' . WC_OPEN_PLUGIN_URL . '/assets/images/bitcoin.png" class="open-btc-icon open-icon" alt="Bitcoin" />',
                'eth' => '<img src="' . WC_OPEN_PLUGIN_URL . '/assets/images/ethereum.png" class="open-eth-icon open-icon" alt="Ethereum" />',
                'bnb' => '<img src="' . WC_OPEN_PLUGIN_URL . '/assets/images/usdc.png" class="open-bnb-icon open-icon" alt="Binance" />',
            ]
        );
    }

    /**
     * Process the payment and return the result.
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        $this->init_open_api_handler();

        $paymentCurrency = $_POST['open_currency'];
        // Create a new wallet request.
        $metadata = array(
            'amount' => $order->get_total(),
            'orderId' => $order->get_id(),
            'orderKey' => $order->get_order_key(),
            'paymentCurrency' => $paymentCurrency,
            'productCurrency' => $order->get_currency(),
            'source' => 'woocommerce',
            'test' => $this->testmode
        );

        $result = Open_API_Handler::create_wallet($metadata);

        if (!$result[0]) {
            return array('result' => 'fail');
        }

        $order->update_status('wc-blockchain-pending', __('Open Platform payment detected, but awaiting blockchain confirmation.', 'open'));
        $order->update_meta_data('_op_address', $result[1]['address']);
        $order->update_meta_data('_op_currency', $paymentCurrency);
        $order->save();

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }


    /**
     * Check payment statuses on orders and update order statuses.
     */
    public function check_orders()
    {
        $this->init_open_api_handler();

        // Check the status of non-archived Open orders.
        $orders = wc_get_orders(array('open_archived' => false, 'status' => array('wc-pending')));
        foreach ($orders as $order) {
            $address = $order->get_meta('_op_address');

            usleep(300000);
            $result = Open_API_Handler::send_request('widget/transactions/address/' . $address);

            if (!$result[0]) {
                self::log('Failed to fetch order updates for: ' . $order->get_id());
                continue;
            }

            $data['status'] = 'PROCESSING';
            $this->_update_order_status($order, $data);
        }
    }

    /**
     * Handle requests sent to webhook.
     */
    public function handle_webhook()
    {

        $request_body = file_get_contents('php://input');
        $request_headers = array_change_key_case($this->get_request_headers(), CASE_UPPER);

        $error_message = "Open Webhook Request Failure";

        if (!empty($request_body) && $this->validate_webhook($request_headers, $request_body)) {

            $data = json_decode($request_body, true);

            $order_id = $data['order_id'];

            global $error_message;
            if (!isset($order_id) || !wc_get_order($order_id)) {
                // Order not exist
                $error_message = "Order does not exist";
                exit;
            }

            $this->_update_order_status(wc_get_order($order_id), $data);

            exit;  // 200 response for acknowledgement.
        }

        wp_die($error_message, 'Open Webhook', array('response' => 500));
    }

    /**
     * Check Open Webhook request is valid.
     */
    public function validate_webhook(array $request_headers, $request_body): bool
    {
        if (!isset($request_headers['HTTP_X_OPEN_WEBHOOK_SIGNATURE'])) {
            return false;
        }

        $trimmedBody = trim(preg_replace('/\s+/', '', $request_body));

        self::log('Incoming webhook body: '.$trimmedBody);

        $timestamp = intval($request_headers['HTTP_X_OPEN_WEBHOOK_TIMESTAMP']);

        if (abs($timestamp - time()) > 5 * MINUTE_IN_SECONDS) {
            return false;
        }

        $sig = $request_headers['HTTP_X_OPEN_WEBHOOK_SIGNATURE'];
        $secret = $this->get_option('secret_key');

        $sig2 = hash_hmac('sha256', $trimmedBody, $secret);

        if ($sig === $sig2) {
            return true;
        }

        return false;
    }

    /**
     * Init the API class and set the API key etc.
     */
    protected function init_open_api_handler()
    {
        include_once dirname(__FILE__) . '/class-wc-gateway-api-handler.php';

        Open_API_Handler::$log = get_class($this) . '::log';
        Open_API_Handler::$api_key = $this->get_option('api_key');
        Open_API_Handler::$secret_key = $this->get_option('secret_key');
    }

    /**
     * Update the status of an order from a given transaction.
     * @param WC_Order $order
     * @param array $data
     */
    public function _update_order_status(WC_Order $order, array $data)
    {

        $prev_status = $order->get_meta('_open_status');

        $status = $data['status'];

        if ($status !== $prev_status) {
            $order->update_meta_data('_open_status', $status);

            if ('PROCESSING' === $status) {
                $order->update_status('processing', __('Open payment was successfully processed.', 'open'));
            } else if ('COMPLETED' === $status) {
                $order->update_status('completed', __('Open payment was successfully completed.', 'open'));
                $order->payment_complete();
            }
        }
    }

    /**
     * Gets the incoming request headers. Some servers are not using
     * Apache and "getallheaders()" will not work, so we may need to
     * build our own headers.
     */
    public function get_request_headers()
    {
        if (!function_exists('getallheaders')) {
            $headers = [];

            foreach ($_SERVER as $name => $value) {
                if ('HTTP_' === substr($name, 0, 5)) {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }

            return $headers;
        } else {
            return getallheaders();
        }
    }
}