<?php


/**
 * Sends API requests to Open Platform.
 */
class Open_API_Handler
{

    /** @var string Open Platform API url. */
    public static $api_url;

    /** @var string Open Platform API application access key. */
    public static $api_key;

    /** @var string Open Platform API application secret key. */
    public static $secret_key;


    /** @var string/array Log variable function. */
    public static $log;

    /**
     * Call the $log variable function.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log(string $message, string $level = 'info')
    {
        return call_user_func(self::$log, $message, $level);
    }


    /**
     * Get the response from an API request.
     * @param string $endpoint
     * @param array $params
     * @param string $method
     * @return array
     */
    public static function send_request(string $endpoint, string $hash, array $params = array(), string $method = 'GET'): array
    {

        self::log('Open Platform Request Args for ' . $endpoint . ': ' . print_r($params, true));
        $args = array(
            'method' => $method,
            'headers' => array(
                'X-API-KEY' => self::$api_key,
                'X-API-SIGNATURE' => $hash,
                'X-API-TIMESTAMP' => self::get_timestamp(),
                'Content-Type' => 'application/json'
            )
        );

        $url = self::$api_url . $endpoint;

        if (in_array($method, array('POST', 'PUT'))) {
            $args['body'] = json_encode($params);
        } else {
            $url = add_query_arg($params, $url);
        }
        $response = wp_remote_request(esc_url_raw($url), $args);

        if (is_wp_error($response)) {
            self::log('WP response error: ' . $response->get_error_message(), 'error');
            return array(false, $response->get_error_message());
        } else {
            $result = json_decode($response['body'], true);

            $code = $response['response']['code'];

            if (in_array($code, array(200, 201), true)) {
                return array(true, $result);
            } else {
                $e = empty($result['error']['message']) ? '' : $result['error']['message'];
                $errors = array(
                    400 => 'Error response from API: ' . $e,
                    401 => 'Authentication error, please check your API key.'
                );

                if (array_key_exists($code, $errors)) {
                    $msg = $errors[$code];
                } else {
                    $msg = 'Unknown response from API: ' . $code;
                }
                self::log($msg);

                return array(false, $code);
            }
        }
    }

    /**
     * Create a new wallet address request.
     * @param $metadata
     * @return array
     */
    public static function create_wallet($metadata = null): array
    {
        $args = array(
            'metadata' => $metadata
        );

        $sign = self::get_signature($args);

        return self::send_request('wallet/process', $sign, $args, 'POST');
    }


    /**
     * Get application wallets
     * @return array
     */
    public static function get_public_wallet(): array
    {
        return self::send_request('wallet/details', "", array(), 'GET');

    }

    public static function get_timestamp(): int
    {
        $currentDate = new DateTime();
        return $currentDate->getTimestamp();
    }

    public static function get_signature($args): string
    {
        $jsonString = json_encode($args);
        return hash_hmac('sha256', $jsonString, self::$secret_key);
    }
}
