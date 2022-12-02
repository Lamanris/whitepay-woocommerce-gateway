<?php
/*
Plugin Name: WooCommerce - Whitepay payment gateway
Plugin URI: https://whitepay.com/
Description: Whitepay Crypto Payment Gateway for WooCommerce.
Version: 1.0
*/

defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}


/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + whitepay gateway
 */
function wc_whitepay_add_to_gateways( $gateways ) {
    $gateways[] = 'WC_Gateway_Whitepay';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_whitepay_add_to_gateways' );


/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_whitepay_gateway_plugin_links( $links ) {

    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=whitepay_gateway' ) . '">' . __( 'Configure', 'wc-gateway-whitepay' ) . '</a>'
    );

    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_whitepay_gateway_plugin_links' );


/**
 * Whitepay Payment Gateway
 *
 * Provides an Whitepay Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Whitepay
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_whitepay_gateway_init', 11 );

function wc_whitepay_gateway_init() {

    class WC_Gateway_Whitepay extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->id                 = 'whitepay_gateway';
            $this->icon               = apply_filters('woocommerce_whitepay_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Whitepay', 'wc-gateway-whitepay' );
            $this->method_description = __( 'Allow crypto payments using Whitepay', 'wc-gateway-whitepay' );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->apiKey       = $this->get_option( 'apiKey' );
            $this->webhookSecret    = $this->get_option( 'webhookSecret' );
            $this->paymentSuccessText = $this->get_option( 'paymentSuccessText' );
            $this->paymentFailText = $this->get_option( 'paymentFailText' );
            $this->acquiringText = '';

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

            // You can also register a webhook here
            add_action( 'woocommerce_api_payment_whitepay', array( $this, 'webhookPaymentWhitepay' ) );
        }


        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {

            $this->form_fields = apply_filters( 'wc_whitepay_form_fields', array(

                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'wc-gateway-whitepay' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Whitepay Payment', 'wc-gateway-whitepay' ),
                    'default' => 'yes'
                ),

                'title' => array(
                    'title'       => __( 'Title', 'wc-gateway-whitepay' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-whitepay' ),
                    'default'     => __( 'Whitepay Payment', 'wc-gateway-whitepay' ),
                    'desc_tip'    => true,
                ),

                'description' => array(
                    'title'       => __( 'Description', 'wc-gateway-whitepay' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-whitepay' ),
                    'default'     => __( 'Please remit payment to Store Name upon pickup or delivery.', 'wc-gateway-whitepay' ),
                    'desc_tip'    => true,
                ),

                'instructions' => array(
                    'title'       => __( 'Instructions', 'wc-gateway-whitepay' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-whitepay' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'apiKey' => array(
                    'title'       => __( 'Api Key', 'wc-gateway-whitepay' ),
                    'type'        => 'text',
                    'description' => __( 'Api Key to Whitepay platform', 'wc-gateway-whitepay' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'webhookSecret' => array(
                    'title'       => __( 'Webhook Secret', 'wc-gateway-whitepay' ),
                    'type'        => 'text',
                    'description' => __( 'Webhook Secret to Whitepay platform', 'wc-gateway-whitepay' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'paymentSuccessText' => array(
                    'title'       => __( 'Payment Success Text', 'wc-gateway-whitepay' ),
                    'type'        => 'textarea',
                    'description' => __( 'Success Text in Note when payment is completted. Also sending the note text to customer email.', 'wc-gateway-whitepay' ),
                    'default'     => 'Hey, your order is paid! Thank you!',
                    'desc_tip'    => true,
                ),

                'paymentFailText' => array(
                    'title'       => __( 'Payment Fail Text', 'wc-gateway-whitepay' ),
                    'type'        => 'textarea',
                    'description' => __( 'Fail Text in Note when payment link is invalid, expired. Also sending the note text to customer email.', 'wc-gateway-whitepay' ),
                    'default'     => 'The link was expired. Payment failed. You can try another payment method or contact us.',
                    'desc_tip'    => true,
                ),
            ) );
        }


        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions ) {
                echo wpautop( wptexturize( $this->instructions ) );
            }
        }


        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */

        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

            if ( $this->acquiringText && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->acquiringText ) ) . PHP_EOL;
            }
        }


        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
            global $woocommerce;

            $order = new WC_Order( $order_id );

            $payload = array (
                "amount" => $order->order_total,
                "currency" => get_woocommerce_currency(),
                "external_order_id" => $order_id
            );

            // Send this payload to Whitepay.com for processing
            $response = wp_remote_post( 'https://api.whitepay.com/private-api/crypto-orders/feisch6', array(
                'method'    => 'POST',
                'headers'   => array(
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ),
                'body'      => http_build_query( $payload ),
                'timeout'   => 90,
                'sslverify' => false,
            ) );

            if ( is_wp_error( $response ) )
                throw new Exception( __( 'There is issue for connecting payment gateway. Sorry for the inconvenience.', 'wc-gateway-whitepay' ) );

            if ( empty( $response['body'] ) )
                throw new Exception( __( 'Whitepay.com\'s Response was not get any data.', 'wc-gateway-whitepay' ) );

            $body = json_decode( $response['body'], true );

            if ( $body['order']['status'] == 'INIT' ) {

                $this->acquiringText = 'Please use this link to pay for your order:' . $body['order']['acquiring_url'] . ' .The link is valid for 30 minutes.';
                // Mark as on-hold (we're awaiting the payment)
                $order->update_status( 'on-hold', __( 'Awaiting Whitepay payment', 'wc-gateway-whitepay' ) );

                // Reduce stock levels
                wc_reduce_stock_levels( $order_id );

                // Remove cart
                WC()->cart->empty_cart();

                // Return thankyou redirect
                return array(
                    'result' 	=> 'success',
                    'redirect'	=> $body['order']['acquiring_url']
                );

            } else {
                wc_add_notice(  'Connection error. Try again', 'error' );
                return;
            }

        }

        public function webhookPaymentWhitepay() {

            $payload = file_get_contents('php://input');
            $payloadJson = json_encode($payload);
            $payloadJsonArray = json_decode($payload, true);
            $signature = hash_hmac('sha256', $payload, $this->webhookSecret);
            $sig_header = $_SERVER['HTTP_SIGNATURE'];
            $payloadExternalOrderId = $payloadJsonArray['order']['external_order_id'];
            $payloadOrderStatus = $payloadJsonArray['order']['status'];

            if($signature == $sig_header) {
                $order = wc_get_order( $payloadExternalOrderId );
                if($payloadOrderStatus == 'COMPLETE') {
                    $order->payment_complete();
                    $order->add_order_note( $this->paymentSuccessText, true );
                } else if($payloadOrderStatus == 'DECLINED') {
                    $order->update_status( 'failed', __( 'Whitepay payment expired', 'wc-gateway-whitepay' ) );
                    $order->add_order_note( $this->paymentFailText, true );
                }
                update_option('webhook_debug', $_GET);
            }

        }

    } // end \WC_Gateway_Whitepay class
}