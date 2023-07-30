<?php


/**
 * Plugin Name: درگاه پرداخت بانک ملت برای ووکامرس
 * Version: 1.0
 * Description:  این افزونه درگاه پرداخت الکترونیکی به پرداخت ملت را به افزونه فروشگاهی ووکامرس اضافه می‌کند.
 * Plugin URI: https://themedoni.com/
 * Author: اردوان شام روشن
 * Author URI: https://themedoni.com/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// die if accessed externally
defined('ABSPATH') || exit;

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
    return;

add_action('plugins_loaded', 'mellat_gateway_init', 11);

function mellat_gateway_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        class WC_Mellat_Gateway extends WC_Payment_Gateway
        {
            public function __construct()
            {
                // credentials
                $this->id = 'mellat_gateway';
                $this->icon = apply_filters('woocommerce_mellat_gateway', plugins_url() . '/assets/images/logo.png');
                $this->has_fields = false;
                $this->method_title = __('بانک ملت');
                $this->method_description = __('درگاه پرداخت بانک ملت');

                // initialize form fields
                $this->init_form_fields();
                // initialize settings
                $this->init_settings();
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('woocommerce_mellat_gateway_fields', [
                    'enabled' => [
                        'title' => __('فعال / غیرفعال'),
                        ''
                    ]
                ]);
            }
        }
    }
}

add_filter('woocommerce_payment_gateways', 'add_woocommerce_mellat_gateway');

function add_woocommerce_mellat_gateway($gateways)
{
    $gateways[] = 'WC_Mellat_Gateway';
    return $gateways;
}
