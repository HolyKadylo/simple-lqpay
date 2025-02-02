<?php
/*
Plugin Name: WooCommerce Simple LiqPay
Plugin URI:
Description: LiqPay gateway for WooCommerce
Version: 1.7.1
Author: Alex Shandor
Author URI: http://pupuga.net
*/
if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'woocommerce_init', 0);

function woocommerce_init() {

    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Gateway_Liqpay extends WC_Payment_Gateway
    {
        private $_checkout_url = 'https://www.liqpay.ua/api/checkout';
        protected $_supportedCurrencies = array('EUR','UAH','USD');

        public function __construct() {

            global $woocommerce;

            $this->id = 'liqpay';
            $this->has_fields = false;
            $this->method_title = __('liqPay', 'woocommerce');
            $this->method_description = __('LiqPay', 'woocommerce');
            $this->init_form_fields();
            $this->init_settings();
            $this->public_key = $this->get_option('public_key');
            $this->private_key = $this->get_option('private_key');
            $this->sandbox = $this->get_option('sandbox');
            if ($this->get_option('lang') == 'uk/en' && !is_admin()) {
                $this->lang = call_user_func($this->get_option('lang_function'));
                if ($this->lang == 'uk') {
                    $key = 0;
                } else {
                    $key = 1;
                }
                $array_explode = explode('::', $this->get_option('title'));
                $this->title = $array_explode[$key];
                $array_explode = explode('::', $this->get_option('description'));
                $this->description = $array_explode[$key];
                $array_explode = explode('::', $this->get_option('pay_message'));
                $this->pay_message = $array_explode[$key];
            } else {
                $this->lang = $this->get_option('lang');
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->pay_message = $this->get_option('pay_message');
            }
            $this->icon = $this->get_option('icon');
            $this->status = $this->get_option('status');
            $this->redirect_page = $this->get_option('redirect_page');
            $this->function_id = $this->get_option('function_id');
            $this->button = $this->get_option('button');

            // Actions
            add_action('woocommerce_receipt_liqpay', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_gateway_liqpay', array($this, 'check_ipn_response'));

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }


        public function admin_options() { ?>

            <h3><?php _e('LiqPay', 'woocommerce'); ?></h3>

            <?php if ($this->is_valid_for_use()) { ?>
                <table class="form-table"><?php $this->generate_settings_html(); ?></table>
            <?php } else { ?>

                <div class="inline error">
                    <p>
                        <strong><?php _e('Gateway error', 'woocommerce'); ?></strong>: <?php _e('Liqpay не підтримує такі валюти', 'woocommerce'); ?>
                    </p>
                </div>

            <?php } ?>

        <?php }

        public function init_form_fields() {

            $this->form_fields = array(
                'enabled'     => array(
                    'title'   => __('Ввімкнути/Вимкнути', 'woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Ввімкнути', 'woocommerce'),
                    'default' => 'yes',
                ),
                'title'       => array(
                    'title'       => __('Заголовок', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('Заголовок, який відображається на сторінці оформлення', 'woocommerce'),
                    'default'     => __('Оплата карткою Visa/MasterCard (LiqPay)::Payment via Visa / MasterCard (LiqPay)'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Опис', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('Опис, який відображається на сторінці оформлення замовлення', 'woocommerce'),
                    'default'     => __('Сплатити за допомогою LiqPay::Pay with LiqPay', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'pay_message' => array(
                    'title'       => __('Повідомлення перед оплатою', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('Повідомлення перед оплатою', 'woocommerce'),
                    'default'     => __('Дякуємо за замовлення! Натисніть кнопку нижче::Thank you for your order, click the button'),
                    'desc_tip'    => true,
                ),
                'public_key'  => array(
                    'title'       => __('Public key', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Public key LiqPay. Необхідно заповнити', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'private_key' => array(
                    'title'       => __('Private key', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Private key LiqPay. Необхідно заповнити', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'lang' => array(
                    'title'       => __('Мова', 'woocommerce'),
                    'type'        => 'select',
                    'default'     => 'uk',
                    'options'     => array('uk'=> __('uk', 'woocommerce'), 'en'=> __('en', 'woocommerce'), 'uk/en'=> __('uk + en', 'woocommerce')),
                    'description' => __('Мова интерфейсу (Для uk + en встановіть плагін. Розділ мов за допомогою (?) :: .)', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'lang_function'     => array(
                    'title'       => __('Функція визначення мови', 'woocommerce'),
                    'type'        => 'text',
                    'default'     => 'pll_current_language',
                    'description' => __('Функція визначення мови вашого плагіну', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'icon'     => array(
                    'title'       => __('Логотип', 'woocommerce'),
                    'type'        => 'text',
                    'default'     => 'https://www.liqpay.ua/1440663992860980/static/img/business/logo.png',
                    'description' => __('Повний шлях до логотипу', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'button'     => array(
                    'title'       => __('Кнопка', 'woocommerce'),
                    'type'        => 'text',
                    'default'     => '',
                    'description' => __('Повний шлях до зображення кнопки для переходу на LiqPay', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'status'     => array(
                    'title'       => __('Статус замовлення', 'woocommerce'),
                    'type'        => 'text',
                    'default'     => 'processing',
                    'description' => __('Статус замовлення після успішної оплати', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'sandbox'     => array(
                    'title'       => __('Тестовий режим', 'woocommerce'),
                    'label'       => __('Ввімкнути', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => __('Даний режим не передбачає зняття грошей з карток', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'redirect_page'     => array(
                    'title'       => __('URL сторінки з подякою', 'woocommerce'),
                    'type'        => 'text',
                    'default'     => '',
                    'description' => __('URL сторінки, на яку переспрямовується клієнт після успішної оплати LiqPay', 'woocommerce'),
                    'desc_tip'    => true,
                ),
                'function_id'     => array(
                    'title'       => __('Функція замовлення', 'woocommerce'),
                    'type'        => 'text',
                    'default'     => '',
                    'description' => __('Повертає номер замовлення', 'woocommerce'),
                    'desc_tip'    => true,
                ),
            );
        }

        function is_valid_for_use() {
            if (!in_array(get_option('woocommerce_currency'), array('UAH', 'USD', 'EUR'))) {
                return false;
            }
            return true;
        }

        function process_payment($order_id) {
            $order = new WC_Order($order_id);
            return array(
                'result'   => 'success',
                'redirect' => add_query_arg('order', $order->get_id(), add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id('pay'))))
            );
        }

        

        public function receipt_page($order) {
            echo '<p>' . __(esc_attr($this->pay_message), 'woocommerce') . '</p><br/>';
            echo $this->generate_form($order);
        }

        public function generate_form($order_id) {

            global $woocommerce;

            $order = new WC_Order($order_id);
            $result_url = add_query_arg('wc-api', 'wc_gateway_liqpay', home_url('/'));

            $currency= get_woocommerce_currency();

            if ($this->sandbox == 'yes') {
                $sandbox = 1;
            } else {
                $sandbox = 0;
            }

            if (trim($this->redirect_page) == '') {
                $redirect_page_url = $order->get_checkout_order_received_url();
            } else {
                $redirect_page_url = trim($this->redirect_page);
            }

            if (trim($this->function_id) == '') {
                $order_number = $order_id;
            } else {
                $order_number = $order->get_id();
            }

            $html = $this->cnb_form(array(
                'version'     => '3',
                'amount'      => esc_attr($order->get_total()),
                'currency'    => esc_attr($currency),
                'description' => _("№") . $order_number,
                'order_id'    => esc_attr($order_id),
                'result_url'  => $redirect_page_url,
                'server_url'  => esc_attr($result_url),
                'language'    => $this->lang,
                'sandbox'     => $sandbox
            ));
            return $html;
        }

        function check_ipn_response() {
            global $woocommerce;

            $success = isset($_POST['data']) && isset($_POST['signature']);

            if ($success) {
                $data = $_POST['data'];
                $parsed_data = json_decode(base64_decode($data));
                $received_signature = $_POST['signature'];
                $received_public_key = $parsed_data->public_key;
                $order_id = $parsed_data->order_id;
                $status = $parsed_data->status;
                $sender_phone = $parsed_data->sender_phone;
                $amount = $parsed_data->amount;
                $currency = $parsed_data->currency;
                $transaction_id = $parsed_data->transaction_id;

                $generated_signature = base64_encode(sha1($this->private_key . $data . $this->private_key, 1));

                if ($received_signature != $generated_signature || $this->public_key != $received_public_key) wp_die('IPN Request Failure');

                $order = new WC_Order($order_id);

                if ($status == 'success' || ($status == 'sandbox' && $this->sandbox == 'yes')) {
                    $order->update_status($this->status, __('Замовлення сплачено (оплата отримана)', 'woocommerce'));
                    $order->add_order_note(__('Клієнт сплатив замовлення', 'woocommerce'));
                    $woocommerce->cart->empty_cart();
                } else {
                    $order->update_status('failed', __('Оплата не була отримана', 'woocommerce'));
                    wp_redirect($order->get_cancel_order_url());
                    exit;
                }
            } else {
                wp_die('IPN Request Failure');
            }

        }

        public function cnb_form($params) {

            if (!isset($params['language'])) $language = 'uk';
            else $language = $params['language'];

            $params    = $this->cnb_params($params);
            $data      = base64_encode( json_encode($params) );
            $signature = $this->cnb_signature($params);

            if (trim($this->button) == '') {
                $button = '<input type="image" style="width: 160px" src="//static.liqpay.com/buttons/p1%s.radius.png" name="btn_text" />';
            } else {
                $button = '<input type="image" style="width: 160px" src="'.$this->button.'" name="btn_text" />';
            }

            return sprintf('
            <form method="POST" action="%s" accept-charset="utf-8">
                %s
                %s'. $button . '
            </form>
            ',
                $this->_checkout_url,
                sprintf('<input type="hidden" name="%s" value="%s" />', 'data', $data),
                sprintf('<input type="hidden" name="%s" value="%s" />', 'signature', $signature),
                $language
            );
        }

        private function cnb_params($params) {

            $params['public_key'] = $this->public_key;

            if (!isset($params['version'])) {
                throw new InvalidArgumentException('version is null');
            }
            if (!isset($params['amount'])) {
                throw new InvalidArgumentException('amount is null');
            }
            if (!isset($params['currency'])) {
                throw new InvalidArgumentException('currency is null');
            }
            if (!in_array($params['currency'], $this->_supportedCurrencies)) {
                throw new InvalidArgumentException('currency is not supported');
            }
            if (!isset($params['description'])) {
                throw new InvalidArgumentException('description is null');
            }

            return $params;
        }

        public function cnb_signature($params) {
            $params      = $this->cnb_params($params);
            $private_key = $this->private_key;

            $json      = base64_encode( json_encode($params) );
            $signature = $this->str_to_sign($private_key . $json . $private_key);

            return $signature;
        }

        public function str_to_sign($str) {

            $signature = base64_encode(sha1($str,1));

            return $signature;
        }

    }

    function simple_liqpay($methods) {
        $methods[] = 'WC_Gateway_Liqpay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'simple_liqpay');

}
