<?php
/**
Plugin Name: Sark Migs
Plugin URI: http://sarkware.com/sark-migs-a-wordpress-plugin-for-adding-migs-payment-gateway-support-for-woocommerce/
Description: Extends WooCommerce with MasterCard Internet Gateway Service (MIGS).
Version: 1.1
Author: Saravana Kumar K
Author URI: http://iamsark.com
Copyright: © 2014-2015 sarkware
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action('plugins_loaded', 'sark_migs_init', 0);

function sark_migs_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	class SarkMigs extends WC_Payment_Gateway {
		public function __construct() {			
			$this -> id           = 'sarkmigs';
			$this -> method_title = __('Sark Migs', 'sark');
			$this -> method_description  = __('', 'sark');
			$this -> icon         =  plugins_url( 'images/migs_logo.jpg' , __FILE__ );
			$this -> has_fields   = false;
	
			$this -> init_form_fields();
			$this -> init_settings();
	
			$this -> title            		= $this -> settings['title'];
			$this -> description      		= $this -> settings['description'];				
			$this -> merchant_id      		= $this -> settings['merchant_id'];
			$this -> access_code      		= $this -> settings['access_code'];
			$this -> secure_hash_secret     = $this -> settings['secure_hash_secret'];	
			$this -> service_host  			= $this -> settings['service_host'];
			
			$this->callback = str_replace( 'https:', 'http:', home_url( '/wc-api/SarkMigs' )  );
	
			$this -> msg['message'] = "";
			$this -> msg['class']   = "";	
	
			add_action( 'woocommerce_api_sarkmigs', array( $this, 'check_sark_migs_response' ) );	
			add_action('valid-sarkmigs-request', array($this, 'successful_request'));
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			add_action('woocommerce_receipt_sarkmigs', array($this, 'receipt_page'));			
		}
		
		function init_form_fields() {
		
			$this -> form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'sark'),
					'type' => 'checkbox',
					'label' => __('Enable MIGS Payment Module.', 'sark'),
					'default' => 'no'
				),
				'title' => array(
					'title' => __('Title:', 'sark'),
					'type'=> 'text',
					'desc_tip'    => true,
					'placeholder' => __( 'MIGS', 'woocommerce' ),
					'description' => __('Your desire title name .it will show during checkout proccess.', 'sark'),
					'default' => __('Sark Migs', 'sark')
				),
				'description' => array(
					'title' => __('Description:', 'sark'),
					'type' => 'textarea',
					'desc_tip'    => true,
					'placeholder' => __( 'Description', 'woocommerce' ),
					'description' => __('Pay securely by Credit Card/Debit Card through MasterCard Internet Gateway Service.', 'sark'),
					'default' => __('Pay securely by Credit Card/Debit Card through MasterCard Internet Gateway Service.', 'sark')
				),
				'merchant_id' => array(
					'title' => __('Merchant ID', 'sark'),
					'type' => 'text',
					'desc_tip'    => true,
					'placeholder' => __( 'Merchant ID', 'woocommerce' ),
					'description' => __('Merchant ID, Given by MIGS')
				),
				'access_code' => array(
					'title' => __('Access Code', 'sark'),
					'type' => 'text',
					'desc_tip'    => true,
					'placeholder' => __( 'Access Code', 'woocommerce' ),
					'description' =>  __('Access Code, Given by MIGS', 'sark')
				),
				'secure_hash_secret' => array(
					'title' => __('Secure Hash Secret', 'sark'),
					'type' => 'text',
					'desc_tip'    => true,
					'placeholder' => __( 'Secure Hash Secret', 'woocommerce' ),
					'description' =>  __('Encrypted/Secure Hash Secret key Given to Merchant by MIGS', 'sark')
				),
				'service_host' => array(
					'title' => __('MIGS URL', 'sark'),
					'type' => 'text',
					'desc_tip'    => true,
					'placeholder' => __( 'MIGS URL', 'woocommerce' ),
					'description' =>  __('(For example: https://migs.mastercard.com.au/vpcpay) Given to Merchant by MIGS', 'sark'),
					'default' => __('https://migs.mastercard.com.au/vpcpay', 'sark')
				)
		
			);
		
		}
		
		public function admin_options(){
			echo '<h3>'.__('MasterCard Internet Gateway Service', 'sark').'</h3>';			
			echo '<p>'.__('<a href="http://iamsark.com" target="_blank">This module developed by Sark </a> ').'</p>';
	        echo '<p>'.__('MIGS is most popular payment gateway for online shopping').'</p>';
	        echo '<table class="form-table">';
	        $this -> generate_settings_html();
	        echo '</table>';
		}
		
		function payment_fields() {
			if($this -> description) echo wpautop(wptexturize($this -> description));
		}
		
		function receipt_page($order) {			
			echo '<p>'.__('Thank you for your order, please click the button below to pay with MasterCard Internet Gateway Service.', 'sark').'</p>';
			echo $this -> generate_axis_gate_form($order);
		}
		
		function process_payment($order_id) {			
			$order = new WC_Order($order_id);			
			return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
		}
		
		function check_sark_migs_response() {
			$authorised = false;
			global $woocommerce;
			
			$md5Hash = $this->secure_hash_secret;
			$txnSecureHash = $_REQUEST['vpc_SecureHash'];
			
			$order_id = explode( '_', $_REQUEST['vpc_MerchTxnRef'] );
			$order_id = (int) $order_id[0];
			$order = new WC_Order($order_id);
			
			$DR = $this->parseDigitalReceipt();
			$ThreeDSecureData = $this->parse3DSecureData();			
			
			$msg['class']   = 'error';
			$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
			
			if ( strlen($md5Hash) > 0 && $_REQUEST['vpc_TxnResponseCode'] != "7" && $_REQUEST['vpc_TxnResponseCode'] != "No Value Returned") {
			
				foreach( $_REQUEST as $key => $value ) {
					if ( $key != "vpc_SecureHash" && strlen( $value ) > 0) {
						$md5Hash .= $value;
					}
				}
			
				if ( strtoupper( $txnSecureHash ) != strtoupper( md5( $md5Hash )) ) {
					$authorised = false;
				} else {					
					if( $DR["txnResponseCode"] == "0" ) {									
						$authorised = true;
					} else {
						$authorised = false;
					}
				}
			
			} else {
				$authorised = false;
			}
			
			if( $authorised ) {
				try {
					$order_status = $decryptValues['order_status'];
					if( $order -> status !== 'completed' ) {
						$transauthorised = true;
						$msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
						$msg['class'] = 'success';
						if( $order -> status != 'processing' ) {
							$order -> payment_complete();
							$order -> add_order_note('MIGS Payment successful<br/>Receipt Number: '.$DR["receiptNo"]);
							$woocommerce -> cart -> empty_cart();
						}
					}
				} catch( Exception $e ) {
					$msg['class'] = 'error';
					$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
				
					$order -> update_status('failed');
					$order -> add_order_note('Payment Transaction ');
					$order -> add_order_note($this->msg['message']);
				}
			} else {
				$msg['class'] = 'error';
				$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
				
				$order -> update_status('failed');
				$order -> add_order_note('Payment Transaction Failed');
				$order -> add_order_note($this->msg['message']);
			}
		
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $msg['message'], $msg['class'] );
			}
			else {
				if($msg['class']=='success') {
					$woocommerce->add_message( $msg['message']);
				}else {
					$woocommerce->add_error( $msg['message'] );
				}
				$woocommerce->set_messages();
			}
		
			wp_redirect( $order->get_checkout_order_received_url() );
			exit;		
		}
		
		public function generate_axis_gate_form($order_id) {			
			global $woocommerce;
			$order = new WC_Order($order_id);
			$order_id = $order_id.'_'.date("ymds");
			$order_amount = 100 * intval( $order->order_total );
			
			$md5Hash = $this->secure_hash_secret;
			
			/* Make sure user entered MIGS url, otherwise use the default one */
			if( trim( $this->service_host ) == "" || $this->service_host == null ) {
				$this->service_host = "https://migs.mastercard.com.au/vpcpay";
			}
			$service_host = $this->service_host."?";
			
			$DigitalOrder = array(
				"vpc_Version" => "1",
				"vpc_Command" => "pay",
				"vpc_AccessCode" => $this->access_code,
				"vpc_MerchTxnRef" => $order_id,
				"vpc_Merchant" => $this->merchant_id,
				"vpc_OrderInfo" => "VPC",
				"vpc_Amount" => $order_amount,
				"vpc_Locale" => "en",
				"vpc_ReturnURL" => $this->callback
			);
			
			ksort ( $DigitalOrder );
			
			foreach( $DigitalOrder as $key => $value ) {
				if ( strlen( $value ) > 0 ) {
					if ( $appendAmp == 0 ) {
						$service_host .= urlencode( $key ) . '=' . urlencode( $value );
						$appendAmp = 1;
					} else {
						$service_host .= '&' . urlencode( $key ) . "=" . urlencode( $value );
					}
					$md5Hash .= $value;
				}
			}	

			$service_host .= "&vpc_SecureHash=". strtoupper( md5( $md5Hash ) );
			header("Location: ".$service_host);
			exit();
		}	
		
		private function parseDigitalReceipt() {
			$dReceipt = array(
				"amount" 			=> $this->null2unknown( $_REQUEST['vpc_Amount'] ),
				"locale"          	=> $this->null2unknown( $_REQUEST['vpc_Locale'] ),
				"batchNo"         	=> $this->null2unknown( $_REQUEST['vpc_BatchNo'] ),
				"command"         	=> $this->null2unknown( $_REQUEST['vpc_Command'] ),
				"message"         	=> $this->null2unknown( $_REQUEST['vpc_Message'] ),
				"version"         	=> $this->null2unknown( $_REQUEST['vpc_Version'] ),
				"cardType"        	=> $this->null2unknown( $_REQUEST['vpc_Card'] ),
				"orderInfo"       	=> $this->null2unknown( $_REQUEST['vpc_OrderInfo'] ),
				"receiptNo"       	=> $this->null2unknown( $_REQUEST['vpc_ReceiptNo'] ),
				"merchantID"      	=> $this->null2unknown( $_REQUEST['vpc_Merchant'] ),
				"authorizeID"     	=> $this->null2unknown( $_REQUEST['vpc_AuthorizeId'] ),
				"merchTxnRef"     	=> $this->null2unknown( $_REQUEST['vpc_MerchTxnRef'] ),
				"transactionNo"   	=> $this->null2unknown( $_REQUEST['vpc_TransactionNo'] ),
				"acqResponseCode" 	=> $this->null2unknown( $_REQUEST['vpc_AcqResponseCode'] ),
				"txnResponseCode" 	=> $this->null2unknown( $_REQUEST['vpc_TxnResponseCode'] )
			);
			return $dReceipt;
		}
		
		private function parse3DSecureData() {
			$threeDSecure = array(
				"verType"         	=> array_key_exists( "vpc_VerType", $_REQUEST )          ? $_REQUEST['vpc_VerType']          : "No Value Returned",
				"verStatus"       	=> array_key_exists( "vpc_VerStatus", $_REQUEST )        ? $_REQUEST['vpc_VerStatus']        : "No Value Returned",
				"token"           	=> array_key_exists( "vpc_VerToken", $_REQUEST )         ? $_REQUEST['vpc_VerToken']         : "No Value Returned",
				"verSecurLevel"   	=> array_key_exists( "vpc_VerSecurityLevel", $_REQUEST ) ? $_REQUEST['vpc_VerSecurityLevel'] : "No Value Returned",
				"enrolled"        	=> array_key_exists( "vpc_3DSenrolled", $_REQUEST )      ? $_REQUEST['vpc_3DSenrolled']      : "No Value Returned",
				"xid"             	=> array_key_exists( "vpc_3DSXID", $_REQUEST )           ? $_REQUEST['vpc_3DSXID']           : "No Value Returned",
				"acqECI"          	=> array_key_exists( "vpc_3DSECI", $_REQUEST )           ? $_REQUEST['vpc_3DSECI']           : "No Value Returned",
				"authStatus"      	=> array_key_exists( "vpc_3DSstatus", $_REQUEST )        ? $_REQUEST['vpc_3DSstatus']        : "No Value Returned"
			);
			return $threeDSecure;
		}
		
		private function responseDescription( $responseCode ) {
			switch ( $responseCode ) {
				case "0" : $result = "Transaction Successful"; break;
				case "?" : $result = "Transaction status is unknown"; break;
				case "1" : $result = "Unknown Error"; break;
				case "2" : $result = "Bank Declined Transaction"; break;
				case "3" : $result = "No Reply from Bank"; break;
				case "4" : $result = "Expired Card"; break;
				case "5" : $result = "Insufficient funds"; break;
				case "6" : $result = "Error Communicating with Bank"; break;
				case "7" : $result = "Payment Server System Error"; break;
				case "8" : $result = "Transaction Type Not Supported"; break;
				case "9" : $result = "Bank declined transaction (Do not contact Bank)"; break;
				case "A" : $result = "Transaction Aborted"; break;
				case "C" : $result = "Transaction Cancelled"; break;
				case "D" : $result = "Deferred transaction has been received and is awaiting processing"; break;
				case "F" : $result = "3D Secure Authentication failed"; break;
				case "I" : $result = "Card Security Code verification failed"; break;
				case "L" : $result = "Shopping Transaction Locked (Please try the transaction again later)"; break;
				case "N" : $result = "Cardholder is not enrolled in Authentication scheme"; break;
				case "P" : $result = "Transaction has been received by the Payment Adaptor and is being processed"; break;
				case "R" : $result = "Transaction was not processed - Reached limit of retry attempts allowed"; break;
				case "S" : $result = "Duplicate SessionID (OrderInfo)"; break;
				case "T" : $result = "Address Verification Failed"; break;
				case "U" : $result = "Card Security Code Failed"; break;
				case "V" : $result = "Address Verification and Card Security Code Failed"; break;
				default  : $result = "Unable to be determined";
			}
			return $result;
		}
		
		private function null2unknown($data) {
			if ($data == "") {
				return "No Value Returned";
			} else {
				return $data;
			}
		}
		
		function get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
					$has_parent = $page->post_parent;
					while($has_parent) {
						$prefix .=  ' - ';
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}
		
	}

	function woocommerce_add_sark_migs_gateway($methods) {
		$methods[] = 'SarkMigs';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_sark_migs_gateway' );
	
}
	