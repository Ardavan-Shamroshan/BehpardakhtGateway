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
defined( 'ABSPATH' ) || exit;

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

// create a class after plugins are loaded
add_action( 'plugins_loaded', 'mellat_gateway_init', 11 );

function mellat_gateway_init() {
	if (
		class_exists( 'WC_Payment_Gateway' ) &&
		! class_exists( 'WC_Mellat_GatewayGateway' ) &&
		! function_exists( 'woocommerce_add_mellat_gateway' )
	) {
		// tell WooCommerce (WC) that it exists by filtering
		add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_mellat_gateway' );

		function woocommerce_add_mellat_gateway( $methods ) {
			$methods[] = 'WC_Mellat_GatewayGateway';

			return $methods;
		}

		add_filter( 'woocommerce_currencies', 'woocommerce_mellat_gateway_ir_currency' );
		function woocommerce_mellat_gateway_ir_currency( $currencies ) {
			$currencies['IRR']  = __( 'ریال' );
			$currencies['IRT']  = __( 'تومان' );
			$currencies['IRHR'] = __( 'هزار ریال' );
			$currencies['IRHT'] = __( 'هزار تومان' );

			return $currencies;
		}

		add_filter( 'woocommerce_currency_symbol', 'woocommerce_mellat_gateway_ir_currency_symbol', 10, 2 );

		function woocommerce_mellat_gateway_ir_currency_symbol( $currency_symbol, $currency ) {
			switch ( $currency ) {
				case 'IRR':
					$currency_symbol = __( 'ریال' );
					break;
				case 'IRT':
					$currency_symbol = __( 'تومان' );
					break;
				case 'IRHR':
					$currency_symbol = __( 'هزار ریال' );
					break;
				case 'IRHT':
					$currency_symbol = __( 'هزار تومان' );
					break;
			}

			return $currency_symbol;
		}


		// gateway class extends the WooCommerce base gateway class,
		// so you have access to important methods and the settings API
		class WC_Mellat_GatewayGateway extends WC_Payment_Gateway {
			public function __construct() {
				// credentials
				$this->author             = __( 'اردوان شام روشن' );
				$this->id                 = 'WC_Mellat_GatewayGateway';                                                                                  // Unique ID for your gateway, e.g., ‘your_gateway’
				$this->icon               = apply_filters( 'WC_Mellat_GatewayGateway_Icon', plugins_url( '/assets/images/logo.png', __FILE__ ) );        // show an image next to the gateway’s name on the frontend
				$this->has_fields         = false;                                                                                                       // Can be set to true if you want payment fields to show on the checkout (if doing a direct integration)
				$this->method_title       = __( 'بانک ملت' );                                                                                            // Title of the payment method shown on the admin page.
				$this->method_description = __( 'درگاه پرداخت بانک ملت' );                                                                               // Description for the payment method shown on the admin page.

				// options you’ll show in admin on your gateway settings page and make use of the WC Settings API.
				$this->init_form_fields();
				// get the settings and load them into variables,
				$this->init_settings();

				$this->title       = $this->settings['title'];
				$this->description = $this->settings['description'];

				$this->terminal_id = $this->settings['terminal_id'];
				$this->username    = $this->settings['username'];
				$this->password    = $this->settings['password'];

				$this->order_pay_show = $this->settings['order_pay_show'] ?? 'yes';

				$this->success_massage = $this->settings['success_massage'];
				$this->failed_massage  = $this->settings['failed_massage'];

				// add a save hook for your settings:
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				} else {
					add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
				}
				add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'redirect_to_mellat_gateway' ) );
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'return_from_mellat_gateway' ) );
			}

			public function admin_options() {
				parent::admin_options();
			}

			/**
			 * A basic set of settings for your gateway would consist of enabled, title and description
			 */
			public function init_form_fields() {
				$this->form_fields = apply_filters(
					'woocommerce_mellat_gateway_fields',
					[
						'base_config'     => [
							'title'       => __( 'تنظیمات درگاه' ),
							'type'        => 'title',
							'description' => '',
						],
						'enabled'         => [
							'title'       => __( 'فعالسازی/غیرفعالسازی' ),
							'type'        => 'checkbox',
							'label'       => __( 'فعالسازی درگاه پرداخت به پرداخت ملت' ),
							'description' => __( 'برای فعالسازی درگاه به پرداخت ملت باید این قسمت را  را علامتگذاری کنید.' ),
							'default'     => 'yes',
							'desc_tip'    => true,
						],
						'title'           => [
							'title'       => __( 'عنوان درگاه' ),
							'type'        => 'text',
							'description' => __( 'عنوان درگاه که در طول خرید به مشتری نمایش داده می‌شود' ),
							'default'     => __( 'به پرداخت ملت' ),
							'desc_tip'    => true,
						],
						'description'     => [
							'title'       => __( 'توضیحات درگاه' ),
							'type'        => 'text',
							'desc_tip'    => true,
							'description' => __( 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد' ),
							'default'     => __( 'پرداخت امن از طریق درگاه پرداخت به پرداخت ملت(قابل پرداخت با کلیه کارتهای عضو شتاب)' )
						],
						'account_confing' => [
							'title'       => __( 'اطلاعات درگاه پرداخت' ),
							'type'        => 'title',
							'description' => '',
						],
						'terminal_id'     => [
							'title'       => __( 'شماره ترمینال' ),
							'type'        => 'text',
							'description' => __( 'Terminal ID' ),
							'default'     => '',
							'desc_tip'    => true
						],
						'username'        => [
							'title'       => __( 'نام کاربری' ),
							'type'        => 'text',
							'description' => __( 'Username' ),
							'default'     => '',
							'desc_tip'    => true
						],
						'password'        => [
							'title'       => __( 'کلمه عبور' ),
							'type'        => 'text',
							'description' => __( 'Password' ),
							'default'     => '',
							'desc_tip'    => true
						],
						'payment_confing' => [
							'title'       => __( 'تنظیمات عملیات پرداخت' ),
							'type'        => 'title',
							'description' => '',
						],
						'order_pay_show'  => [
							'title'       => __( 'برگه پیش فاکتور' ),
							'type'        => 'checkbox',
							'label'       => __( 'نمایش برگه پیش فاکتور' ),
							'description' => __( 'برای نمایش برگه پیش فاکتور این قسمت را علامتگذاری کنید' ),
							'default'     => 'yes',
							'desc_tip'    => true
						],
						'success_massage' => [
							'title'       => __( 'پیام پرداخت موفق' ),
							'type'        => 'textarea',
							'description' => __( 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید.
                                            همچنین می توانید از کدهای کوتاه زیر استفاده کنید:<br/>
                                            <strong>%Transaction_id%</strong> : کد رهگیری<br/>
                                            <strong>%Order_Number%</strong> : شماره درخواست تراکنش<br/>' ),
							'default'     => __( 'پرداخت با موفقیت انجام شد.' )
						],
						'failed_massage'  => [
							'title'       => __( 'پیام پرداخت ناموفق' ),
							'type'        => 'textarea',
							'description' => __( 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد %failed% برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت به پرداخت ملت ارسال میگردد .' ),
							'default'     => __( 'پرداخت با شکست مواجه شد. شرح خطا: %failed%' )
						],
					]
				);
			}


			/**
			 * Now for the most important part of the gateway — handling payment and processing the order.
			 * Process_payment also tells WC where to redirect the user, and this is done with a returned array.
			 *
			 * @param $order_id
			 *
			 * @return array
			 */
			public function process_payment( $order_id ) {
				global $woocommerce;
				$order = new WC_Order( $order_id );

				// Mark as on-hold (we're awaiting the cheque)
				// $order->update_status( 'on-hold', __( 'در انتظار پرداخت', 'woocommerce' ) );

				// Remove cart
				// $woocommerce->cart->empty_cart();

				// Return thank you redirect
				return [
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				];
			}


			public function redirect_to_mellat_gateway( $order_id ) {
				global $woocommerce;
				$woocommerce->session->order_id_mellat_gateway = $order_id;

				$order = new WC_Order( $order_id );

				$currency = $order->get_currency();
				$currency = apply_filters( 'WC_Mellat_GatewayCurrency', $currency, $order_id );

				$form = '
				<form action="" method="POST" class="BehPardakht-checkout-form" id="BehPardakht-checkout-form">
					<input type="submit" name="BehPardakht_submit" class="button alt" id="BehPardakht-payment-button" value="' . __( 'پرداخت' ) . '"/>
					<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __( 'بازگشت' ) . '</a>
				</form>
				<br/>
				';

				$form = apply_filters( 'WC_Mellat_GatewayForm', $form, $order_id, $woocommerce );

				$show_factor = false;
				if ( $this->order_pay_show == 'yes' ) {
					$show_factor = true;
				}

				if ( isset( $_POST["Mellat_submit"] ) ) {
					$show_factor = false;
				}

				if ( $show_factor ) {
					if ( ! isset( $_POST["Mellat_submit"] ) ) {
						do_action( 'WC_Mellat_GatewayGateway_Before_Form', $order_id, $woocommerce );
						echo $form;
						do_action( 'WC_Mellat_GatewayGateway_After_Form', $order_id, $woocommerce );
					}
				}

				if ( ! $show_factor ) {
					$Amount = (int) $order->get_total();
					$Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency );

					if ( strtolower( $currency ) == strtolower( 'IRT' ) || strtolower( $currency ) == strtolower( 'TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran-TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian-TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran_TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian_TOMAN' ) || strtolower( $currency ) == strtolower( 'تومان' ) || strtolower( $currency ) == strtolower( 'تومان ایران' ) ) {
						$Amount = $Amount * 10;
					} else if ( strtolower( $currency ) == strtolower( 'IRHT' ) ) {
						$Amount = $Amount * 1000 * 10;
					} else if ( strtolower( $currency ) == strtolower( 'IRHR' ) ) {
						$Amount = $Amount * 1000;
					} else if ( strtolower( $currency ) == strtolower( 'IRR' ) ) {
						$Amount = $Amount * 1;
					}

					$Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency );
					$Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_irr', $Amount, $currency );
					$Amount = apply_filters( 'woocommerce_order_amount_total_Saderat_gateway', $Amount, $currency );

					do_action( 'WC_Mellat_GatewayGateway_Payment', $order_id );

					$factor_id = time();

					$terminal_id = $this->terminal_id;
					$username    = $this->username;
					$password    = $this->password;

					$callBackUrl = add_query_arg( 'wc_order', $order_id, WC()->api_request_url( 'WC_Mellat' ) );

					try {
						$soapclient = new SoapClient( 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl', [ 'encoding' => 'UTF-8' ] );

						$parameters = array(
							'terminalId'     => $terminal_id,
							'userName'       => $username,
							'userPassword'   => $password,
							'orderId'        => $factor_id,
							'amount'         => $Amount,
							'localDate'      => date( "Ymd" ),
							'localTime'      => date( "His" ),
							'additionalData' => "factor id: " . $factor_id,
							'callBackUrl'    => $callBackUrl,
							'payerId'        => 0
						);

						$result    = $soapclient->bpPayRequest( $parameters );
						$PayResult = explode( ',', $result->return );

						if ( $PayResult[0] == "0" ) {
							echo '<p>در حال اتصال به درگاه پرداخت ...</p>';
							$RefID = $PayResult[1];

							do_action( 'WC_Mellat_GatewayBefore_Send_to_Gateway', $order_id );

							wc_update_order_item_meta( $order_id, 'factor_id', $factor_id );
							wc_update_order_item_meta( $order_id, 'RefId', $RefID );

							$html = '
							<form name="behpardakht" method="post" action="https://bpm.shaparak.ir/pgwchannel/startpay.mellat">
								<input type="hidden" name="RefId" value="' . $RefID . '">
								<script type="text/javascript" language="JavaScript">document.behpardakht.submit();</script>
                            </form>
                           ';
							die( $html );
						} else {
							$fault = $PayResult[0];
							$err   = $this->get_error_msg( $fault );

							$Note = sprintf( __( 'خطا در ارسال اطلاعات : %s', 'woocommerce' ), $err );
							$Note = apply_filters( 'WC_Mellat_GatewaySend_to_Gateway_Failed_Note', $Note, $order_id, $fault );
							$order->add_order_note( $Note );

							$Notice = sprintf( __( 'در هنگام اتصال به درگاه بانکی خطای زیر رخ داده است: <br/>%s', 'woocommerce' ), $err );
							$Notice = apply_filters( 'WC_Mellat_GatewaySend_to_Gateway_Failed_Notice', $Notice, $order_id, $fault );
							if ( $Notice ) {
								wc_add_notice( $Notice, 'error' );
							}

							do_action( 'WC_Mellat_GatewaySend_to_Gateway_Failed', $order_id, $fault );
						}
					} catch ( Exception $ex ) {
						$err   = $ex->getMessage();
						$fault = 0;
						$Note  = sprintf( __( 'خطا در اتصال به شبکه بانکی : %s', 'woocommerce' ), '<p dir="ltr">' . $err . '</p>' );
						$Note  = apply_filters( 'WC_Mellat_GatewaySend_to_Gateway_Failed_Note', $Note, $order_id, $fault );
						$order->add_order_note( $Note );

						$Notice = sprintf( __( 'در هنگام اتصال به درگاه بانکی خطای زیر رخ داده است: <br/>%s', 'woocommerce' ), '<p dir="ltr">' . $err . '</p>' );
						$Notice = apply_filters( 'WC_Mellat_GatewaySend_to_Gateway_Failed_Notice', $Notice, $order_id, $fault );
						if ( $Notice ) {
							wc_add_notice( $Notice, 'error' );
						}
						do_action( 'WC_Mellat_GatewaySend_to_Gateway_Failed', $order_id, $fault );
					}
				}
			}


			public function get_error_msg( $ErrorCode ) {
				$ErrorDesc = "";
				switch ( $ErrorCode ) {
					case - 2:
						$ErrorDesc .= "شکست در ارتباط با بانک";
						break;
					case - 1:
						$ErrorDesc .= "شکست در ارتباط با بانک";
						break;
					case 0:
						$ErrorDesc .= "تراکنش با موفقیت انجام شد";
						break;
					case 11:
						$ErrorDesc .= "شماره کارت معتبر نیست";
						break;
					case 12:
						$ErrorDesc .= "موجودی کافی نیست";
						break;
					case 13:
						$ErrorDesc .= "رمز دوم شما صحیح نیست";
						break;
					case 14:
						$ErrorDesc .= "دفعات مجاز ورود رمز بیش از حد است";
						break;
					case 15:
						$ErrorDesc .= "کارت معتبر نیست";
						break;
					case 16:
						$ErrorDesc .= "دفعات برداشت وجه بیش از حد مجاز است";
						break;
					case 17:
						$ErrorDesc .= "شما از انجام تراکنش منصرف شده اید";
						break;
					case 18:
						$ErrorDesc .= "تاریخ انقضای کارت گذشته است";
						break;
					case 19:
						$ErrorDesc .= "مبلغ برداشت وجه بیش از حد مجاز است";
						break;
					case 111:
						$ErrorDesc .= "صادر کننده کارت نامعتبر است";
						break;
					case 112:
						$ErrorDesc .= "خطای سوییچ صادر کننده کارت";
						break;
					case 113:
						$ErrorDesc .= "پاسخی از صادر کننده کارت دریافت نشد";
						break;
					case 114:
						$ErrorDesc .= "دارنده کارت مجاز به انجام این تراکنش نمی باشد";
						break;
					case 21:
						$ErrorDesc .= "پذیرنده معتبر نیست";
						break;
					case 23:
						$ErrorDesc .= "خطای امنیتی رخ داده است";
						break;
					case 24:
						$ErrorDesc .= "اطلاعات کاربری پذیرنده معتبر نیست";
						break;
					case 25:
						$ErrorDesc .= "مبلغ نامعتبر است";
						break;
					case 31:
						$ErrorDesc .= "پاسخ نامعتبر است";
						break;
					case 32:
						$ErrorDesc .= "فرمت اطلاعات وارد شده صحیح نیست";
						break;
					case 33:
						$ErrorDesc .= "حساب نامعتبر است";
						break;
					case 34:
						$ErrorDesc .= "خطای سیستمی";
						break;
					case 35:
						$ErrorDesc .= "تاریخ نامعتبر است";
						break;
					case 41:
						$ErrorDesc .= "شماره درخواست تکراری است";
						break;
					case 42:
						$ErrorDesc .= "تراکنش Sale یافت نشد";
						break;
					case 43:
						$ErrorDesc .= "قبلا درخواست Verify داده شده است";
						break;
					case 44:
						$ErrorDesc .= "درخواست Verify یافت نشد";
						break;
					case 45:
						$ErrorDesc .= "تراکنش Settle شده است";
						break;
					case 46:
						$ErrorDesc .= "تراکنش Settle نشده است";
						break;
					case 47:
						$ErrorDesc .= "تراکنش Settle یافت نشد";
						break;
					case 48:
						$ErrorDesc .= "تراکنش Reverse شده است";
						break;
					case 49:
						$ErrorDesc .= "تراکنش Refund یافت نشد";
						break;
					case 412:
						$ErrorDesc .= "شناسه قبض نادرست است";
						break;
					case 413:
						$ErrorDesc .= "شناسه پرداخت نادرست است";
						break;
					case 414:
						$ErrorDesc .= "سازمان صادر کننده قبض معتبر نیست";
						break;
					case 415:
						$ErrorDesc .= "زمان جلسه کاری به پایان رسیده است";
						break;
					case 416:
						$ErrorDesc .= "خطا در ثبت اطلاعات";
						break;
					case 417:
						$ErrorDesc .= "شناسه پرداخت کننده نامعتبر است";
						break;
					case 418:
						$ErrorDesc .= "اشکال در تعریف اطلاعات مشتری";
						break;
					case 419:
						$ErrorDesc .= "تعداد دفعات ورود اطلاعات بیش از حد مجاز است";
						break;
					case 421:
						$ErrorDesc .= "IP معتبر نیست";
						break;
					case 51:
						$ErrorDesc .= "تراکنش تکراری است";
						break;
					case 54:
						$ErrorDesc .= "تراکنش مرجع موجود نیست";
						break;
					case 55:
						$ErrorDesc .= "تراکنش نامعتبر است";
						break;
					case 61:
						$ErrorDesc .= "خطا در واریز";
						break;
					default:
						$ErrorDesc .= "خطای تعریف نشده";
				}

				return $ErrorCode . ': ' . $ErrorDesc;
			}
		}
	}
}