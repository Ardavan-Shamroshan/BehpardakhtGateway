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

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// create a class after plugins are loaded
add_action('plugins_loaded', 'mellat_gateway_init', 11);

function mellat_gateway_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        // gateway class extends the WooCommerce base gateway class,
        // so you have access to important methods and the settings API
        class WC_Mellat_Gateway extends WC_Payment_Gateway
        {
            public function __construct()
            {

                // credentials
                $this->id = 'mellat_gateway'; // Unique ID for your gateway, e.g., ‘your_gateway’
                $this->icon = apply_filters('woocommerce_mellat_gateway', plugins_url('/assets/images/logo.png', __FILE__)); // show an image next to the gateway’s name on the frontend
                $this->has_fields = false; //Can be set to true if you want payment fields to show on the checkout (if doing a direct integration)
                $this->method_title = __('بانک ملت'); // Title of the payment method shown on the admin page.
                $this->method_description = __('درگاه پرداخت بانک ملت'); // Description for the payment method shown on the admin page.


                // options you’ll show in admin on your gateway settings page and make use of the WC Settings API.
                $this->init_form_fields();
                // get the settings and load them into variables,
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }
                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_BehPardakht_Gateway_by_ham3da'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_BehPardakht_Gateway_by_ham3da'));
            }

            /**
             * A basic set of settings for your gateway would consist of enabled, title and description
             */
            public function init_form_fields()
            {
                $this->form_fields = apply_filters(
                    'woocommerce_mellat_gateway_fields',
                    [
                        'base_confing' => [
                            'title' => __('تنظیمات درگاه'),
                            'type' => 'title',
                            'description' => '',
                        ],
                        'enabled' => [
                            'title' => __('فعالسازی/غیرفعالسازی'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه پرداخت به پرداخت ملت'),
                            'description' => __('برای فعالسازی درگاه به پرداخت ملت باید این قسمت را  را علامتگذاری کنید.'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ],
                        'title' => [
                            'title' => __('عنوان درگاه'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طول خرید به مشتری نمایش داده می‌شود'),
                            'default' => __('به پرداخت ملت'),
                            'desc_tip' => true,
                        ],
                        'description' => [
                            'title' => __('توضیحات درگاه'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد'),
                            'default' => __('پرداخت امن از طریق درگاه پرداخت به پرداخت ملت(قابل پرداخت با کلیه کارتهای عضو شتاب)')
                        ],
                        'account_confing' => [
                            'title' => __('اطلاعات درگاه پرداخت'),
                            'type' => 'title',
                            'description' => '',
                        ],
                        'terminal_id' => [
                            'title' => __('شماره ترمینال'),
                            'type' => 'text',
                            'description' => __('Terminal ID'),
                            'default' => '',
                            'desc_tip' => true
                        ],
                        'username' => [
                            'title' => __('نام کاربری'),
                            'type' => 'text',
                            'description' => __('Username'),
                            'default' => '',
                            'desc_tip' => true
                        ],
                        'password' => [
                            'title' => __('کلمه عبور'),
                            'type' => 'text',
                            'description' => __('Password'),
                            'default' => '',
                            'desc_tip' => true
                        ],
                        'payment_confing' => [
                            'title' => __('تنظیمات عملیات پرداخت'),
                            'type' => 'title',
                            'description' => '',
                        ],
                        'order_pay_show' => [
                            'title' => __('برگه پیش فاکتور'),
                            'type' => 'checkbox',
                            'label' => __('نمایش برگه پیش فاکتور'),
                            'description' => __('برای نمایش برگه پیش فاکتور این قسمت را علامتگذاری کنید'),
                            'default' => 'yes',
                            'desc_tip' => true
                        ],
                        'success_massage' => [
                            'title' => __('پیام پرداخت موفق'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید.
                                            همچنین می توانید از کدهای کوتاه زیر استفاده کنید:<br/>
                                            <strong>%Transaction_id%</strong> : کد رهگیری<br/>
                                            <strong>%Order_Number%</strong> : شماره درخواست تراکنش<br/>'),
                            'default' => __('پرداخت با موفقیت انجام شد.')
                        ],
                        'failed_massage' => [
                            'title' => __('پیام پرداخت ناموفق'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد %failed% برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت به پرداخت ملت ارسال میگردد .'),
                            'default' => __('پرداخت با شکست مواجه شد. شرح خطا: %failed%')
                        ],
                    ]
                );
            }

            public function proccess_payments($order_id)
            {
                global $woocommerce;
                $order = new WC_Order($order_id);

                // Mark as on-hold (we're awaiting the cheque)
                // $order->update_status('on-hold', __('در انتظار پرداخت', 'woocommerce'));

                // Remove cart
                // $woocommerce->cart->empty_cart();

                // Return thankyou redirect
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }
        }
    }
}


// tell WooCommerce (WC) that it exists by filtering
add_filter('woocommerce_payment_gateways', 'add_woocommerce_mellat_gateway');

function add_woocommerce_mellat_gateway($gateways)
{
    $gateways[] = 'WC_Mellat_Gateway';
    return $gateways;
}
