<?php
/**
 * Plugin Name: WooCommerce Order Sync
 * Description: Syncs orders between two WooCommerce sites
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-user-validator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-order-sync-logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-checkout-handler.php';

class WC_Order_Sync {
    private $remote_site_url;
    private $api_key;
    private $api_secret;
    private $user_validator;
    private $checkout_handler;

    public function __construct() {
        $this->remote_site_url = get_option('wc_order_sync_remote_url');
        $this->api_key = get_option('wc_order_sync_api_key');
        $this->api_secret = get_option('wc_order_sync_api_secret');

        $this->user_validator = new WC_Order_Sync_User_Validator(
            $this->remote_site_url,
            $this->api_key,
            $this->api_secret
        );

        $this->checkout_handler = new WC_Order_Sync_Checkout_Handler($this->user_validator);

        add_action('woocommerce_payment_complete', array($this, 'sync_order_to_primary_site'), 10, 1);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_options_page(
            'Order Sync Settings',
            'Order Sync',
            'manage_options',
            'wc-order-sync',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('wc_order_sync_options', 'wc_order_sync_remote_url');
        register_setting('wc_order_sync_options', 'wc_order_sync_api_key');
        register_setting('wc_order_sync_options', 'wc_order_sync_api_secret');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h2>Order Sync Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('wc_order_sync_options'); ?>
                <table class="form-table">
                    <tr>
                        <th>Primary Site URL</th>
                        <td>
                            <input type="url" name="wc_order_sync_remote_url" value="<?php echo esc_attr(get_option('wc_order_sync_remote_url')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>API Key</th>
                        <td>
                            <input type="text" name="wc_order_sync_api_key" value="<?php echo esc_attr(get_option('wc_order_sync_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>API Secret</th>
                        <td>
                            <input type="password" name="wc_order_sync_api_secret" value="<?php echo esc_attr(get_option('wc_order_sync_api_secret')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function sync_order_to_primary_site($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            WC_Order_Sync_Logger::log_error('Invalid order ID: ' . $order_id);
            return;
        }

        $customer_email = $order->get_billing_email();
        $customer_phone = $order->get_billing_phone();
        
        // Validate email with caching
        if (!$this->user_validator->validate_user_email($customer_email)) {
            WC_Order_Sync_Logger::log_error('Invalid user email for sync: ' . $customer_email);
            return;
        }

        $order_data = array(
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'status' => 'completed',
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'billing' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $customer_email,
                'phone' => $customer_phone
            ),
            'line_items' => array()
        );

        // Add order items
        foreach ($order->get_items() as $item) {
            $order_data['line_items'][] = array(
                'product_id' => $item->get_product_id(),
                'quantity' => $item->get_quantity(),
                'name' => $item->get_name(),
                'total' => $item->get_total()
            );
        }

        $this->create_remote_order($order_data);
    }

    private function create_remote_order($order_data) {
        $max_retries = 3;
        $retry_delay = 2; // seconds
        
        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            if ($attempt > 0) {
                sleep($retry_delay);
            }

            $endpoint = $this->remote_site_url . '/wp-json/wc/v3/orders';
            
            $args = array(
                'method' => 'POST',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret),
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($order_data)
            );

            $response = wp_remote_post($endpoint, $args);

            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                
                if ($response_code === 201) {
                    WC_Order_Sync_Logger::log_success('Order synced successfully', array(
                        'order_data' => $order_data,
                        'attempt' => $attempt + 1
                    ));
                    return true;
                }
            }

            WC_Order_Sync_Logger::log_error('Order sync attempt failed', array(
                'attempt' => $attempt + 1,
                'error' => is_wp_error($response) ? $response->get_error_message() : 'Response code: ' . $response_code,
                'order_data' => $order_data
            ));
        }

        return false;
    }
}

// Initialize the plugin
function wc_order_sync_init() {
    new WC_Order_Sync();
}
add_action('plugins_loaded', 'wc_order_sync_init');