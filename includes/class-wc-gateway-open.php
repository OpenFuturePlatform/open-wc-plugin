<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_Open Class.
 */
class WC_Gateway_Open extends WC_Payment_Gateway {

    /** @var bool Whether or not logging is enabled */
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
    public function __construct() {
        $this->id                 = 'open';
        $this->has_fields         = false;
        //$this->order_button_text  = __( 'Proceed to Open', 'open' );
        $this->method_title       = __( 'Open Platform', 'open' );
        $this->method_description = __( 'A payment gateway that sends your customers to Open Platform.', 'coinbase' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->debug       = 'yes' === $this->get_option( 'debug', 'no' );

        self::$log_enabled = $this->debug;

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, '_custom_query_var' ), 10, 2 );
        add_action( 'woocommerce_api_wc_gateway_open', array( $this, 'handle_webhook' ) );
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log(string $message, string $level = 'info' ) {
        if ( self::$log_enabled ) {
            if ( empty( self::$log ) ) {
                self::$log = wc_get_logger();
            }
            self::$log->log( $level, $message, array( 'source' => 'open' ) );
        }
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'        => array(
                'title'   => __( 'Enable/Disable', 'woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Open Commerce Payment', 'open' ),
                'default' => 'yes',
            ),
            'title'          => array(
                'title'       => __( 'Title', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default'     => __( 'Open Platform Gateway', 'open' ),
                'desc_tip'    => true,
            ),
            'description'    => array(
                'title'       => __( 'Description', 'woocommerce' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                'default'     => __( 'Pay with Open Platform.', 'open' ),
            ),
            'api_key'        => array(
                'title'       => __( 'API Key', 'open' ),
                'type'        => 'text',
                'default'     => '',
                'description' => sprintf(
                    __(
                        'You can manage your API keys within the Open Platform Application page, available here: %s',
                        'open'
                    ),
                    esc_url( 'https://api.openfuture.io/applications' )
                )
            ),
            'secret_key'        => array(
                'title'       => __( 'Secret Key', 'open' ),
                'type'        => 'text',
                'default'     => '',
                'description' => sprintf(
                    __(
                        'You can manage your API keys within the Open Platform Application page, available here: %s',
                        'open'
                    ),
                    esc_url( 'https://api.openfuture.io/applications' )
                )
            ),
            'webhook_secret' => array(
                'title'       => __( 'Webhook', 'open' ),
                'type'        => 'text',
                'description' => __( 'Webhook to send', 'open' ),

            ),
            'debug'          => array(
                'title'       => __( 'Debug log', 'woocommerce' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'woocommerce' ),
                'default'     => 'no',
                'description' => sprintf( __( 'Log OPEN API events inside %s', 'open' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'open' ) . '</code>' ),
            ),
        );
    }

    /**
     * Process the payment and return the result.
     * @param  int $order_id
     * @return array
     */
    public function process_payment( $order_id ): array
    {
        $order = wc_get_order( $order_id );

        // Create description for charge based on order's products. Ex: 1 x Product1, 2 x Product2
        try {
            $order_items = array_map( function( $item ) {
                return $item['quantity'] . ' x ' . $item['name'];
            }, $order->get_items() );

            $description = mb_substr( implode( ', ', $order_items ), 0, 200 );
        } catch ( Exception $e ) {
            $description = null;
        }

        $this->init_open_api_handler();

        // Create a new wallet request.
        $metadata = array(
            'order_id'  => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'source' => 'woocommerce'
        );

        $result   = Open_API_Handler::create_wallet($metadata);

        if ( ! $result[0] ) {
            return array( 'result' => 'fail' );
        }

        $order->update_status( 'wc-blockchain-pending', __( 'Open Platform payment detected, but awaiting blockchain confirmation.', 'open' ) );
        $order->update_meta_data( '_open_charge_id', $result[1]['address'] );
        $order->save();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }


    /**
     * Check payment statuses on orders and update order statuses.
     */
    public function check_orders() {
        $this->init_open_api_handler();

        // Check the status of non-archived Open orders.
        $orders = wc_get_orders( array( 'open_archived' => false, 'status'   => array( 'wc-pending' ) ) );
        foreach ( $orders as $order ) {
            $charge_id = $order->get_meta( '_open_charge_id' );

            usleep( 300000 );
            $result = Open_API_Handler::send_request( 'charges/' . $charge_id );

            if ( ! $result[0] ) {
                self::log( 'Failed to fetch order updates for: ' . $order->get_id() );
                continue;
            }

            $timeline = $result[1]['data']['timeline'];
            self::log( 'Timeline: ' . print_r( $timeline, true ) );
            $this->_update_order_status( $order, $timeline );
        }
    }

    /**
     * Handle requests sent to webhook.
     */
    public function handle_webhook() {
        $payload = file_get_contents( 'php://input' );
        if ( ! empty( $payload ) && $this->validate_webhook( $payload ) ) {
            $data       = json_decode( $payload, true );
            $event_data = $data['event']['data'];

            self::log( 'Webhook received event: ' . print_r( $data, true ) );

            if ( ! isset( $event_data['metadata']['order_id'] ) ) {
                // Probably a charge not created by us.
                exit;
            }

            $order_id = $event_data['metadata']['order_id'];

            $this->_update_order_status( wc_get_order( $order_id ), $event_data['timeline'] );

            exit;  // 200 response for acknowledgement.
        }

        wp_die( 'Open Webhook Request Failure', 'Open Webhook', array( 'response' => 500 ) );
    }

    /**
     * Check Open webhook request is valid.
     * @param string $payload
     */
    public function validate_webhook(string $payload ): bool
    {
        if ( ! isset( $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] ) ) {
            return false;
        }

        $sig    = $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'];
        $secret = $this->get_option( 'api_secret' );

        $sig2 = hash_hmac( 'sha256', $payload, $secret );

        if ( $sig === $sig2 ) {
            return true;
        }

        return false;
    }

    /**
     * Init the API class and set the API key etc.
     */
    protected function init_open_api_handler() {
        include_once dirname( __FILE__ ) . '/class-wc-gateway-api-handler.php';

        Open_API_Handler::$log     = get_class( $this ) . '::log';
        Open_API_Handler::$api_key = $this->get_option( 'api_key' );
        Open_API_Handler::$secret_key = $this->get_option( 'secret_key' );
    }

    /**
     * Update the status of an order from a given timeline.
     * @param WC_Order $order
     * @param array $timeline
     */
    public function _update_order_status(WC_Order $order, array $timeline ) {
        $prev_status = $order->get_meta( '_open_status' );

        $last_update = end( $timeline );
        $status      = $last_update['status'];
        if ( $status !== $prev_status ) {
            $order->update_meta_data( '_open_status', $status );

            if ( 'EXPIRED' === $status && 'pending' == $order->get_status() ) {
                $order->update_status( 'cancelled', __( 'Open payment expired.', 'open' ) );
            } elseif ( 'CANCELED' === $status ) {
                $order->update_status( 'cancelled', __( 'Open payment cancelled.', 'open' ) );
            } elseif ( 'UNRESOLVED' === $status ) {
                if ($last_update['context'] === 'OVERPAID') {
                    $order->update_status( 'processing', __( 'Open payment was successfully processed.', 'open' ) );
                    $order->payment_complete();
                } else {
                    // translators: Coinbase error status for "unresolved" payment. Includes error status.
                    $order->update_status( 'failed', sprintf( __( 'Open payment unresolved, reason: %s.', 'open' ), $last_update['context'] ) );
                }
            } elseif ( 'PENDING' === $status ) {
                $order->update_status( 'blockchain-pending', __( 'Open payment detected, but awaiting blockchain confirmation.', 'open' ) );
            } elseif ( 'RESOLVED' === $status ) {
                // We don't know the resolution, so don't change order status.
                $order->add_order_note( __( 'Open payment marked as resolved.', 'open' ) );
            } elseif ( 'COMPLETED' === $status ) {
                $order->update_status( 'processing', __( 'Open payment was successfully processed.', 'open' ) );
                $order->payment_complete();
            }
        }

        // Archive if in a resolved state and idle more than timeout.
        if ( in_array( $status, array( 'EXPIRED', 'COMPLETED', 'RESOLVED' ), true ) &&
            $order->get_date_modified() < $this->timeout ) {
            self::log( 'Archiving order: ' . $order->get_order_number() );
            $order->update_meta_data( '_open_archived', true );
        }
    }

    /**
     * Handle a custom 'open_archived' query var to get orders
     * payed through Open Platform with the '_open_archived' meta.
     * @param array $query - Args for WP_Query.
     * @param array $query_vars - Query vars from WC_Order_Query.
     * @return array modified $query
     */
    public function _custom_query_var(array $query, array $query_vars ): array
    {
        if ( array_key_exists( 'open_archived', $query_vars ) ) {
            $query['meta_query'][] = array(
                'key'     => '_open_archived',
                'compare' => $query_vars['open_archived'] ? 'EXISTS' : 'NOT EXISTS',
            );
            // Limit only to orders payed through Coinbase.
            $query['meta_query'][] = array(
                'key'     => '_open_charge_id',
                'compare' => 'EXISTS',
            );
        }

        return $query;
    }
}