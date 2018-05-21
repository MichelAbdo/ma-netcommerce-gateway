<?php
/**
 * Plugin Name: MA NetCommerce Gateway
 * Description: NetCommerce payment gateway addon for WooCommerce.
 * Version: 1.0
 * Author: Michel Abdo
 * Author URI: https://twitter.com/AbdoMichel
 * WC tested up to: 3.3.5
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

add_action('plugins_loaded', 'init_ma_netcommerce_gateway');

function init_ma_netcommerce_gateway()
{

    /**
     * NetCommerce Payment Gateway.
     *
     * @class 		WC_Gateway_MA_NetCommerce
     * @extends		WC_Payment_Gateway
     * @since       3.3.5
     * @version		1.0.0
     * @package		MA NetCommerce Gateway
     * @author 		Michel Abdo
     */
    class WC_Gateway_MA_NetCommerce extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id                 = 'ma_netcommerce_gateway';
            $this->has_fields         = false;
            $this->method_title       = __('NetCommerce', 'ma-netcommerce-gateway');
            $this->method_description = __('Developed by Michel Abdo', 'ma-netcommerce-gateway');

            $this->init_form_fields();
            $this->init_settings();

            $this->icon              = $this->get_option('icon');
            $this->language          = $this->get_option('language');
            $this->merchant_number   = $this->get_option('merchant_number');
            $this->request_url       = $this->get_option('request_url');
            $this->sha_key           = $this->get_option('sha_key');
            $this->test_payment_mode = $this->get_option('test_payment_mode');
            $this->title             = $this->get_option('title');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_gateway_ma_netcommerce', array($this, 'return_handler'));
        }

        /**
         * Initialize settings form fields.
         */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled'           => array(
                    'title'   => __('Enable/Disable', 'ma-netcommerce-gateway'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable NetCommerce Payment', 'ma-netcommerce-gateway'),
                    'default' => 'yes',
                ),
                'title'             => array(
                    'title'       => __('Title', 'ma-netcommerce-gateway'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'ma-netcommerce-gateway'),
                    'default'     => __('NetCommerce', 'ma-netcommerce-gateway'),
                    'desc_tip'    => true,
                ),
                'description'       => array(
                    'title'       => __('Customer Message', 'ma-netcommerce-gateway'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'ma-netcommerce-gateway'),
                    'default'     => __('Secure online payment services with real time credit card transaction validation.', 'ma-netcommerce-gateway'),
                    'desc_tip'    => true,
                ),
                'icon'              => array(
                    'title'       => __('Icon'),
                    'type'        => 'select',
                    'options'     => array(
                        'https://www.netcommercepay.com/logo/NCseal_L.gif' => 'NetCommerce Security Seal (Large)',
                        'https://www.netcommercepay.com/logo/NCseal_M.gif' => 'NetCommerce Security Seal (Medium)',
                        'https://www.netcommercepay.com/logo/NCseal_S.gif' => 'NetCommerce Security Seal (Small)',
                    ),
                    'description' => __('This controls the icon which the user sees during checkout.', 'ma-netcommerce-gateway'),
                    'desc_tip'    => true,
                ),
                'merchant_number'   => array(
                    'title'       => __('Merchant Number', 'ma-netcommerce-gateway'),
                    'type'        => 'text',
                    'description' => __('Provided by NetCommerce'),
                    'desc_tip'    => true,
                ),
                'sha_key'           => array(
                    'title'       => __('Signature Key (sha_key)', 'ma-netcommerce-gateway'),
                    'type'        => 'text',
                    'description' => __('The secret key between NetCommerce and the merchant. <br/>Provided by NetCommerce', 'ma-netcommerce-gateway'),
                    'desc_tip'    => true,
                ),
                'request_url'       => array(
                    'title'       => __('NetCommerce Request URL', 'ma-netcommerce-gateway'),
                    'type'        => 'text',
                    'description' => __('Provided by NetCommerce', 'ma-netcommerce-gateway'),
                    'desc_tip'    => true,
                ),
                'test_payment_mode' => array(
                    'title'       => __('Payment mode', 'ma-netcommerce-gateway'),
                    'type'        => 'checkbox',
                    'label'       => __('Testing', 'ma-netcommerce-gateway'),
                    'description' => __('Uncheck to go live', 'ma-netcommerce-gateway'),
                    'default'     => 'yes',
                ),
                'language'          => array(
                    'title'   => __('Language'),
                    'type'    => 'select',
                    'options' => array(
                        'EN' => 'English',
                        'AR' => 'Arabic',
                    ),
                ),
            );
        }

        /**
         * Run on submitting the checkout page - process payment fields and redirect to payment e.g receipt page
         * Redirect to receipt page for automatic post to external gateway
         *
         * @param int $order_id
         *
         * @return array
         */
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Output redirect form on receipt page
         * Receipt page creates POST to gateway
         *
         * @param array $order
         */
        public function receipt_page($order_id)
        {
            $order = wc_get_order($order_id);
            $args  = $this->get_netcommerce_args($order);
            echo $this->generate_redirect_form_html($order, $args);
        }

        /**
         * Payment args
         *
         * The order ID (txtIndex) should be re-generated at each submit trial to NetCommerce.
         * (even if the user clicks back from NetCommerce to the merchant website, when resubmitting, the order ID should be changed.
         *
         * @param object $order WC_Order
         *
         * @return array
         */
        protected function get_netcommerce_args($order)
        {
            $currency    = get_woocommerce_currency();
            $txtAmount   = ($currency == 'LBP') ? number_format($order->get_total()) : number_format($order->get_total(), 2, '.', '');
            $txtCurrency = ($currency == 'USD') ? 840 : (($currency == 'LBP') ? 422 : '');
            $txtIndex    = $order->get_id() . '_' . time();
            $txtMerchNum = $this->merchant_number;
            $txthttp     = WC()->api_request_url('WC_Gateway_MA_NetCommerce');
            $sha_key     = $this->sha_key;
            $signature   = hash('sha256', $txtAmount . $txtCurrency . $txtIndex . $txtMerchNum . $txthttp . $sha_key);
            $args        = array(
                'address_line1' => $order->get_billing_address_1(),
                'address_line2' => $order->get_billing_address_2(), // Optional
                'city'          => $order->get_billing_city(),
                'country'       => $order->get_billing_country(),
                'customer_id'   => $order->get_customer_id(), // Optional
                'email'         => $order->get_billing_email(),
                'first_name'    => $order->get_billing_first_name(),
                'last_name'     => $order->get_billing_last_name(),
                'Lng'           => $this->language, // Optional // Value=”AR” for Arabic language.
                'mobile'        => preg_replace('/\D/', '', $order->get_billing_phone()), // No spaces or characters allowed. Ex: 009613123456
                'payment_mode'  => ($this->test_payment_mode === 'yes') ? 'test' : 'real',
                // 'phone'         => '', // Optional // No spaces or characters allowed. Ex: 009613123456
                'postal_code'   => $order->get_billing_postcode(), // Required only if state exist
                'signature'     => $signature, //SHA256 encryption of all the data sent to NetCommerce
                'state'         => $order->get_billing_state(), // Optional // Used only for US addresses
                'txtAmount'     => $txtAmount, // Total Amount value without symbol *
                'txtCurrency'   => $txtCurrency, //840 for USD, 422 for LBP
                'txthttp'       => $txthttp, // Callback // Return URL for payment response
                'txtIndex'      => $txtIndex, // Order ID reference Alpha numeric
                'txtMerchNum'   => $txtMerchNum, // Merchant number Left Justified
            );
            return $args;
        }

        /**
         * Build redirect form and autosubmit for receipt page
         *
         * @param object $order WC_Order
         * @param type $args
         *
         * @return string
         */
        public function generate_redirect_form_html($order, $args)
        {
            $form_inputs = '';
            foreach ($args as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                $form_inputs .= "<input type='hidden' name='$key' value='$value' />";
            }

            $form_html = "<form action='$this->request_url' method='post' id='netcommerce_payment_form'>
                    $form_inputs
                    <input id='submit_netcommerce_payment' type='submit' class='button alt' value='" . __('Pay now', 'ma-netcommerce-gateway') . "' />
                    <a class='button cancel' href='" . esc_url($order->get_cancel_order_url()) . "'>" . __('Cancel order &amp; restore cart', 'ma-netcommerce-gateway') . "</a>
                </form>";

            wc_enqueue_js('
                jQuery.blockUI({
                    message: "' . __('<b>Thank you for your order.</b> <br /> We are now redirecting you to <b>NetCommerce</b>\'s payment gateway.', 'ma-netcommerce-gateway') . '",
                    baseZ: 99999,
                    overlayCSS: {
                        background: "#fff",
                        opacity: 0.6
                    },
                    css: {
                        padding:        "20px",
                        zindex:         "9999999",
                        textAlign:      "center",
                        color:          "#555",
                        border:         "3px solid #aaa",
                        backgroundColor:"#fff",
                        cursor:         "wait",
                        lineHeight:		"24px",
                    }
                });
                jQuery("#netcommerce_payment_form").submit();
            ');

            return $form_html;
        }

        /**
         * Return handler for Hosted Payments.
         * handles the callback response
         * callback url -> WC()->api_request_url('WC_Gateway_MA_NetCommerce');
         * /wc-api/WC_Gateway_MA_NetCommerce/
         */
        public function return_handler()
        {
            $txtMerchNum = $_POST['txtMerchNum'];
            $txtIndex    = $_POST['txtIndex'];
            $txtAmount   = $_POST['txtAmount'];
            $txtCurrency = $_POST['txtCurrency'];
            $txtNumAut   = $_POST['txtNumAut'];
            $RespVal     = $_POST['RespVal'];
            $RespMsg     = $_POST['RespMsg'];
            $NCSignature = $_POST['signature'];

            if (isset($txtMerchNum) && isset($txtIndex) && isset($txtAmount)
                && isset($txtCurrency) && isset($txtNumAut) && isset($RespVal)
                && isset($RespMsg) && isset($NCSignature)
            ) {
                $sha_key      = $this->sha_key;
                $ma_signature = hash('sha256', $txtMerchNum . $txtIndex . $txtAmount . $txtCurrency . $txtNumAut . $RespVal . $RespMsg . $sha_key);
                if ($NCSignature === $ma_signature) {
                    $order_id = explode('_', $txtIndex);
                    $order_id = absint($order_id[0]);
                    $order    = wc_get_order($order_id);
                    if ($RespVal === '1') {
                        $order->payment_complete($txtNumAut);
                        $order->add_order_note(sprintf(__('NetCommerce payment approved (Order ID: %1$s, NetCommerce Authorization Number: %2$s)', 'ma-netcommerce-gateway'), $order_id, $txtNumAut));
                        WC()->cart->empty_cart();
                    } elseif ($RespVal === '0') {
                        $order->update_status('failed', __('Payment was declined by NetCommerce.', 'ma-netcommerce-gateway'));
                    }
                    wp_redirect($this->get_return_url($order));
                    exit();
                }
            }
            wp_redirect(wc_get_page_permalink('cart'));
            exit();
        }

    }

    /**
     * Add NetCommerce gateway to WooCommerce payment gateways
     *
     * @param array $methods
     *
     * @return string
     */
    function add_ma_netcommerce_gateway($methods)
    {
        $methods[] = 'WC_Gateway_MA_NetCommerce';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_ma_netcommerce_gateway');
}
