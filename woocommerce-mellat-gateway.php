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

defined('ABSPATH') || exit;

if (!in_array('woocommerce/woocomerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
    return;

add_action('plugins_loaded', 'mellat_gateway_init', 11);


function mellat_gateway_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        class WC_Mellat_Gateway extends WC_Payment_Gateway
        {
        }
    }
}
