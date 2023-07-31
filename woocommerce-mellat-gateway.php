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

function Init_BehPardakht_Gateway() {
	if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_BehPardakht' ) && ! function_exists( 'Woocommerce_Add_BehPardakht_Gateway' ) ) {
		add_filter( 'woocommerce_payment_gateways', 'Woocommerce_Add_BehPardakht_Gateway' );

		function Woocommerce_Add_BehPardakht_Gateway( $methods ) {
			$methods[] = 'WC_BehPardakht';

			return $methods;
		}

		add_filter( 'woocommerce_currencies', 'woo_behpardakht_IR_currency' );

		function woo_behpardakht_IR_currency( $currencies ) {
			$currencies['IRR']  = __( 'ریال' );
			$currencies['IRT']  = __( 'تومان' );
			$currencies['IRHR'] = __( 'هزار ریال' );
			$currencies['IRHT'] = __( 'هزار تومان' );

			return $currencies;
		}

		add_filter( 'woocommerce_currency_symbol', 'woo_behpardakht_IR_currency_symbol', 10, 2 );

		function woo_behpardakht_IR_currency_symbol( $currency_symbol, $currency ) {
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

		class WC_BehPardakht extends WC_Payment_Gateway {

			public function __construct() {

				$this->author             = 'اردوان شام روشن';
				$this->id                 = 'WC_BehPardakht';
				$this->method_title       = __( 'به پرداخت ملت' );
				$this->method_description = __( 'تنظیمات درگاه پرداخت به پرداخت ملت برای ووکامرس' );
				$this->icon               = apply_filters( 'WC_BehPardakht_logo', plugins_url( '/assets/images/logo.png', __FILE__ ) );
				$this->has_fields         = false;

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->settings['title'];
				$this->description = $this->settings['description'];

				$this->terminal_id = $this->settings['terminal_id'];
				$this->username    = $this->settings['username'];
				$this->password    = $this->settings['password'];

				$this->order_pay_show = $this->settings['order_pay_show'] ?? 'yes';

				$this->success_massage = $this->settings['success_massage'];
				$this->failed_massage  = $this->settings['failed_massage'];


				if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				} else {
					add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
				}
				add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'redirect_to_behpardakht_gateway' ] );
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), [ $this, 'return_from_behpardakht_gateway' ] );
			}

			public function init_form_fields() {
				$this->form_fields = apply_filters( 'WC_BehPardakht_Config', array(
						'base_config'     => array(
							'title'       => __( 'تنظیمات درگاه' ),
							'type'        => 'title',
							'description' => '',
						),
						'enabled'         => array(
							'title'       => __( 'فعالسازی/غیرفعالسازی' ),
							'type'        => 'checkbox',
							'label'       => __( 'فعالسازی درگاه پرداخت به پرداخت ملت' ),
							'description' => __( 'برای فعالسازی درگاه به پرداخت ملت باید این قسمت را  را علامتگذاری کنید.' ),
							'default'     => 'yes',
							'desc_tip'    => true,
						),
						'title'           => array(
							'title'       => __( 'عنوان درگاه' ),
							'type'        => 'text',
							'description' => __( 'عنوان درگاه که در طول خرید به مشتری نمایش داده می‌شود' ),
							'default'     => __( 'به پرداخت ملت' ),
							'desc_tip'    => true,
						),
						'description'     => array(
							'title'       => __( 'توضیحات درگاه' ),
							'type'        => 'text',
							'desc_tip'    => true,
							'description' => __( 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد' ),
							'default'     => __( 'پرداخت امن از طریق درگاه پرداخت به پرداخت ملت(قابل پرداخت با کلیه کارتهای عضو شتاب)' )
						),
						'account_config'  => array(
							'title'       => __( 'اطلاعات درگاه پرداخت' ),
							'type'        => 'title',
							'description' => '',
						),
						'terminal_id'     => array(
							'title'       => __( 'شماره ترمینال' ),
							'type'        => 'text',
							'description' => __( 'Terminal ID' ),
							'default'     => '',
							'desc_tip'    => true
						),
						'username'        => array(
							'title'       => __( 'نام کاربری' ),
							'type'        => 'text',
							'description' => __( 'Username' ),
							'default'     => '',
							'desc_tip'    => true
						),
						'password'        => array(
							'title'       => __( 'کلمه عبور' ),
							'type'        => 'text',
							'description' => __( 'Password' ),
							'default'     => '',
							'desc_tip'    => true
						),
						'payment_config'  => array(
							'title'       => __( 'تنظیمات عملیات پرداخت' ),
							'type'        => 'title',
							'description' => '',
						),
						'order_pay_show'  => array(
							'title'       => __( 'برگه پیش فاکتور' ),
							'type'        => 'checkbox',
							'label'       => __( 'نمایش برگه پیش فاکتور' ),
							'description' => __( 'برای نمایش برگه پیش فاکتور این قسمت را علامتگذاری کنید' ),
							'default'     => 'yes',
							'desc_tip'    => true,
						),
						'success_massage' => array(
							'title'       => __( 'پیام پرداخت موفق' ),
							'type'        => 'textarea',
							'description' => __( 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید.
                                            همچنین می توانید از کدهای کوتاه زیر استفاده کنید:<br/>
                                            <strong>%Transaction_id%</strong> : کد رهگیری<br/>
                                            <strong>%Order_Number%</strong> : شماره درخواست تراکنش<br/>' ),
							'default'     => __( 'پرداخت با موفقیت انجام شد.' ),
						),
						'failed_massage'  => array(
							'title'       => __( 'پیام پرداخت ناموفق' ),
							'type'        => 'textarea',
							'description' => __( 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد %fault% برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت به پرداخت ملت ارسال میگردد .' ),
							'default'     => __( 'پرداخت با شکست مواجه شد. شرح خطا: %fault%' ),
						),
					)
				);
			}

			public function process_payment( $order_id ): array {
				$order = new WC_Order( $order_id );

				return array(
					'result'   => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				);
			}

			public function redirect_to_behpardakht_gateway( $order_id ) {
				global $woocommerce;
				$woocommerce->session->order_id_BehPardakht = $order_id;

				$order = new WC_Order( $order_id );

				$currency = $order->get_currency();
				$currency = apply_filters( 'WC_BehPardakht_Currency', $currency, $order_id );

				$form = '<form action="" method="POST" class="BehPardakht-checkout-form" id="BehPardakht-checkout-form">
				<input type="submit" name="BehPardakht_submit" class="button alt" id="BehPardakht-payment-button" value="' . __( 'پرداخت' ) . '"/>
				<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __( 'بازگشت' ) . '</a>
				</form>
            	<br/>';
				$form = apply_filters( 'WC_BehPardakht_Form', $form, $order_id, $woocommerce );


				$show_factor = false;
				if ( $this->order_pay_show == 'yes' ) {
					$show_factor = true;
				}

				if ( isset( $_POST["BehPardakht_submit"] ) ) {
					$show_factor = false;
				}

				if ( $show_factor ) {
					if ( ! isset( $_POST["BehPardakht_submit"] ) ) {
						do_action( 'WC_BehPardakht_Gateway_Before_Form', $order_id, $woocommerce );
						echo $form;
						do_action( 'WC_BehPardakht_Gateway_After_Form', $order_id, $woocommerce );
					}
				}



				if ( ! $show_factor ) {
					$Amount = (int) ( $order->get_total() );
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

					do_action( 'WC_BehPardakht_Gateway_Payment', $order_id );

					$factor_id = time();

					$terminal_id = $this->terminal_id;
					$username    = $this->username;
					$password    = $this->password;

					$callBackUrl = add_query_arg( 'wc_order', $order_id, WC()->api_request_url( 'WC_BehPardakht' ) );

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
							'additionalData' => "Factor id: " . $factor_id,
							'callBackUrl'    => $callBackUrl,
							'payerId'        => 0
						);

						$result    = $soapclient->bpPayRequest( $parameters );
						$PayResult = explode( ',', $result->return );

						if ( $PayResult[0] == "0" ) {
							echo '<p>در حال اتصال به درگاه پرداخت ...</p>';
							$RefID = $PayResult[1];

							do_action( 'WC_BehPardakht_Before_Send_to_Gateway', $order_id );

							wc_update_order_item_meta( $order_id, 'foktor_id', $factor_id );
							wc_update_order_item_meta( $order_id, 'RefId', $RefID );

							$html = '<form name="behpardakht" method="post" action="https://bpm.shaparak.ir/pgwchannel/startpay.mellat">
									<input type="hidden" name="RefId" value="' . $RefID . '">
									<script type="text/javascript" language="JavaScript">document.behpardakht.submit();</script>
                                	</form>';
							die( $html );
						}
						else {
							$fault = $PayResult[0];
							$err   = $this->get_error_msg( $fault );

							$Note = sprintf( __( 'خطا در ارسال اطلاعات : %s', 'woocommerce' ), $err );
							$Note = apply_filters( 'WC_BehPardakht_Send_to_Gateway_Failed_Note', $Note, $order_id, $fault );
							$order->add_order_note( $Note );

							$Notice = sprintf( __( 'در هنگام اتصال به درگاه بانکی خطای زیر رخ داده است: <br/>%s', 'woocommerce' ), $err );
							$Notice = apply_filters( 'WC_BehPardakht_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $fault );
							if ( $Notice ) {
								wc_add_notice( $Notice, 'error' );
							}

							do_action( 'WC_BehPardakht_Send_to_Gateway_Failed', $order_id, $fault );
						}
					} catch ( Exception $ex ) {
						$err   = $ex->getMessage();
						$fault = 0;
						$Note  = sprintf( __( 'خطا در اتصال به شبکه بانکی : %s', 'woocommerce' ), '<p dir="ltr">' . $err . '</p>' );
						$Note  = apply_filters( 'WC_BehPardakht_Send_to_Gateway_Failed_Note', $Note, $order_id, $fault );
						$order->add_order_note( $Note );

						$Notice = sprintf( __( 'در هنگام اتصال به درگاه بانکی خطای زیر رخ داده است: <br/>%s', 'woocommerce' ), '<p dir="ltr">' . $err . '</p>' );
						$Notice = apply_filters( 'WC_BehPardakht_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $fault );
						if ( $Notice ) {
							wc_add_notice( $Notice, 'error' );
						}
						do_action( 'WC_BehPardakht_Send_to_Gateway_Failed', $order_id, $fault );
					}
				}
			}

			public function return_from_behpardakht_gateway() {

				global $woocommerce;
				$order_id = 0;
				$order_id = $_GET['wc_order'] ?? $woocommerce->session->order_id_BehPardakht;

				if ( ! isset( $order_id ) || $order_id == 0 ) {
					wp_die( 'شماره سفارش یافت نشد و یا پارامتری از سوی درگاه بانکی ارسال نشده است', 'خطا' );
				}

				$order    = new WC_Order( $order_id );
				$currency = $order->get_currency();
				$currency = apply_filters( 'WC_BehPardakht_Currency', $currency, $order_id );

				$terminal_id = $this->terminal_id;
				$username    = $this->username;
				$password    = $this->password;

				$Pay_Status      = $_POST['ResCode'] ?? '';
				$SaleOrderId     = $_POST['SaleOrderId'] ?? '';
				$SaleReferenceId = $_POST['SaleReferenceId'] ?? '';

				$factor_id = wc_get_order_item_meta( $order_id, 'foktor_id' );

				if ( $Pay_Status == '0' ) {
					if ( $order->get_status() != 'completed' && $order->get_status() != 'processing' ) {
						$Amount = (int) ( $order->get_total() );

						$parameters = array(
							'terminalId'      => $terminal_id,
							'userName'        => $username,
							'userPassword'    => $password,
							'orderId'         => $order_id,
							'saleOrderId'     => $SaleOrderId,
							'saleReferenceId' => $SaleReferenceId
						);

						// URL also can be ir.zarinpal.com or de.zarinpal.com
						$client = new SoapClient( 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl', [ 'encoding' => 'UTF-8' ] );

						$result = $client->bpVerifyRequest( $parameters );
						if ( empty( $result->return ) ) {
							$result = $client->bpInquiryRequest( $parameters );
						}
						$VerResult = explode( ',', $result->return );

						if ( $VerResult[0] == "0" )//OK
						{
							$SettResult     = $client->bpSettleRequest( $parameters );
							$SettResult_end = explode( ',', $SettResult->return );

							if ( $SettResult_end[0] == '0' ) {

								wc_update_order_item_meta( $order_id, '_transaction_id', $SaleReferenceId );

								$order->payment_complete( $SaleReferenceId );
								$woocommerce->cart->empty_cart();
								$Note = sprintf( __( 'پرداخت با موفقیت انجام شد.<br/> کد رهگیری (کد مرجع تراکنش): %s <br/> شماره درخواست تراکنش: %s', 'woocommerce' ), $SaleReferenceId, $SaleOrderId );
								$Note = apply_filters( 'WC_BehPardakht_Return_from_Gateway_Success_Note', $Note, $order_id, $SaleReferenceId, $SaleOrderId );
								if ( $Note ) {
									$order->add_order_note( $Note, 1 );
								}

								$Notice = wpautop( wptexturize( $this->success_massage ) );

								$arr1   = array( "%Transaction_id%", "%Order_Number%" );
								$arr2   = array( $SaleReferenceId, $SaleOrderId );
								$Notice = str_replace( $arr1, $arr2, $Notice );


								$Notice = apply_filters( 'WC_BehPardakht_Return_from_Gateway_Success_Notice', $Notice, $order_id, $SaleReferenceId, $SaleOrderId );
								if ( $Notice ) {
									wc_add_notice( $Notice, 'success' );
								}

								do_action( 'WC_BehPardakht_Return_from_Gateway_Success', $order_id, $SaleReferenceId, $SaleOrderId );

								wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );

							} else {
								$fault = $SettResult_end[0];
								$error = $this->get_error_msg( $fault );

								$tr_id         = ( $SaleReferenceId && $SaleReferenceId != 0 ) ? ( '<br/>کد رهگیری (کد مرجع تراکنش): ' . $SaleReferenceId ) : '';
								$sale_order_id = ( $SaleOrderId && $SaleOrderId != 0 ) ? ( '<br/>شماره درخواست تراکنش: ' . $SaleOrderId ) : '';

								$Note = sprintf( __( 'خطا در تایید تراکنش : %s %s %s', 'woocommerce' ), $error, $tr_id, $sale_order_id );
								$Note = apply_filters( 'WC_BehPardakht_Return_from_Gateway_Failed_Note', $Note, $order_id, $SaleReferenceId, $SaleOrderId, $res );
								if ( $Note ) {
									$order->add_order_note( $Note, 1 );
								}

								$Notice = wpautop( wptexturize( $this->failed_massage ) );

								$arr1 = array( "%Transaction_id%", "%Order_Number%" );
								$arr2 = array( $transaction_id, $SaleOrderId );

								$Notice = str_replace( $arr1, $arr2, $Notice );

								$Notice = str_replace( "%fault%", $error, $Notice );
								$Notice = apply_filters( 'WC_BehPardakht_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $SaleReferenceId, $SaleOrderId, $res );
								if ( $Notice ) {
									wc_add_notice( $Notice, 'error' );
								}
								do_action( 'WC_BehPardakht_Return_from_Gateway_Failed', $order_id, $SaleReferenceId, $SaleOrderId, $fault );
								wp_redirect( wc_get_cart_url() );
							}
						} else {
							$error         = $this->get_error_msg( $VerResult[0] );
							$tr_id         = '<br/>کد رهگیری (کد مرجع تراکنش): ' . $SaleReferenceId;
							$sale_order_id = '<br/>شماره درخواست تراکنش: ' . $SaleOrderId;

							$Note = sprintf( __( 'خطا در بازگشت از درگاه پرداخت : %s %s %s', 'woocommerce' ), $error, $tr_id, $sale_order_id );
							$Note = apply_filters( 'WC_BehPardakht_Return_from_Gateway_Failed_Note', $Note, $order_id, $SaleReferenceId, $SaleOrderId, $Pay_Status );
							if ( $Note ) {
								$order->add_order_note( $Note, 1 );
							}

							$Notice = wpautop( wptexturize( $this->failed_massage ) );

							$arr1 = array( "%Transaction_id%", "%Order_Number%" );
							$arr2 = array( $SaleReferenceId, $SaleOrderId );

							$Notice = str_replace( $arr1, $arr2, $Notice );

							$Notice = str_replace( "%fault%", $error, $Notice );
							$Notice = apply_filters( 'WC_BehPardakht_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $SaleReferenceId, $SaleOrderId, $Pay_Status );
							if ( $Notice ) {
								wc_add_notice( $Notice, 'error' );
							}
							do_action( 'WC_BehPardakht_Return_from_Gateway_Failed', $order_id, $SaleReferenceId, $SaleOrderId, $Pay_Status );
							wp_redirect( wc_get_cart_url() );
						}
						exit();
					} else //قبلاً پرداخت شده
					{
						$transaction_id = wc_get_order_item_meta( $order_id, '_transaction_id' );
						$Notice         = wpautop( wptexturize( $this->success_massage ) );

						$arr1   = array( "%Transaction_id%", "%Order_Number%" );
						$arr2   = array( $transaction_id, $factor_id );
						$Notice = str_replace( $arr1, $arr2, $Notice );
						$Notice = apply_filters( 'WC_BehPardakht_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $SaleReferenceId, $factor_id );
						if ( $Notice ) {
							wc_add_notice( $Notice, 'success' );
						}

						do_action( 'WC_BehPardakht_Return_from_Gateway_ReSuccess', $order_id, $SaleReferenceId, $SaleOrderId );
						wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
						exit();
					}
				} else {
					$error         = $this->get_error_msg( $Pay_Status );
					$tr_id         = '<br/>کد رهگیری (کد مرجع تراکنش): ' . $SaleReferenceId;
					$sale_order_id = '<br/>شماره درخواست تراکنش: ' . $SaleOrderId;

					$Note = sprintf( __( 'خطا در بازگشت از درگاه پرداخت : %s %s %s', 'woocommerce' ), $error, $tr_id, $sale_order_id );
					$Note = apply_filters( 'WC_BehPardakht_Return_from_Gateway_Failed_Note', $Note, $order_id, $SaleReferenceId, $SaleOrderId, $Pay_Status );
					if ( $Note ) {
						$order->add_order_note( $Note, 1 );
					}

					$Notice = wpautop( wptexturize( $this->failed_massage ) );

					$arr1 = array( "%Transaction_id%", "%Order_Number%" );
					$arr2 = array( $SaleReferenceId, $SaleOrderId );

					$Notice = str_replace( $arr1, $arr2, $Notice );

					$Notice = str_replace( "%fault%", $error, $Notice );
					$Notice = apply_filters( 'WC_BehPardakht_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $SaleReferenceId, $SaleOrderId, $Pay_Status );
					if ( $Notice ) {
						wc_add_notice( $Notice, 'error' );
					}
					do_action( 'WC_BehPardakht_Return_from_Gateway_Failed', $order_id, $SaleReferenceId, $SaleOrderId, $Pay_Status );
					wp_redirect( wc_get_cart_url() );
					exit();
				}
			}

			public function get_error_msg( $ErrorCode ): string {
				$ErrorDesc = "";
				switch ( $ErrorCode ) {
					case - 1:
					case - 2:
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

add_action( 'plugins_loaded', 'Init_BehPardakht_Gateway', 0 );
