<?php
/*
Plugin Name: WooCommerce Payka PG
Plugin URI: http://www.sahazamarline.com
Description: Payka Payment gateway for woocommerce
Version: 1.1
Author: SM.Labs
Author URI: http://www.sahazamarline.com
*/
add_action('plugins_loaded', 'woocommerce_sm_payka_init', 0);
function woocommerce_sm_payka_init(){
if(!class_exists('WC_Payment_Gateway')) return;
class WC_SM_Payka extends WC_Payment_Gateway{
	public function __construct(){
		$this -> id = 'payka';
		$this -> medthod_title = 'Payka';
		$this -> has_fields = false;
		$this -> init_form_fields();
		$this -> init_settings();
		$this -> title = $this -> settings['title'];
		$this -> description = $this -> settings['description'];
		$this -> merchant_id = $this -> settings['merchant_id'];
		$this -> salt = $this -> settings['salt'];
		$this -> redirect_page_id = $this -> settings['redirect_page_id'];
		$this -> liveurl = 'http://www.payka.co/view/payment';
		$this -> msg['message'] = "";
		$this -> msg['class'] = "";
		add_action('init', array(&$this, 'check_payka_response'));
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
		} else {
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
		}
		add_action('woocommerce_receipt_payka', array(&$this, 'receipt_page'));
	}
	function init_form_fields(){
		$this -> form_fields = array(
			'enabled' => array(
			'title' => __('Enable/Disable', 'sm'),
			'type' => 'checkbox',
			'label' => __('Enable Payka Payment Module.', 'sm'),
			'default' => 'no'),
			'title' => array(
			'title' => __('Title:', 'sm'),
			'type'=> 'text',
			'description' => __('This controls the title which the user sees during checkout.', 'sm'),
			'default' => __('Payka', 'sm')),
			'description' => array(
			'title' => __('Description:', 'sm'),
			'type' => 'textarea',
			'description' => __('This controls the description which the user sees during checkout.', 'sm'),
			'default' => __('Pay securely with your Payka Wallet.', 'sm')),
			'merchant_id' => array(
			'title' => __('Wallet address', 'sm'),
			'type' => 'text',
			'description' => __('You can get a free Wallet address on <a href="http://payka.co/" target="_blank">Payka.co</a>')),
			'salt' => array(
			'title' => __('PIN code', 'sm'),
			'type' => 'text',
			'description' =>  __('The PIN code associated with your Payka wallet', 'sm'),
			),
			'redirect_page_id' => array(
			'title' => __('Return Page'),
			'type' => 'select',
			'options' => $this -> get_pages('Select Page'),
			'description' => "URL of success page"
			)
		);
	}
	public function admin_options(){
		echo '<h3>'.__('Payka Payment Gateway', 'sm').'</h3>';
		echo '<p>'.__('Payka is most popular payment gateway in Madagascar').'</p>';
		echo '<table class="form-table">';
		// Generate the HTML For the settings form.
		$this -> generate_settings_html();
		echo '</table>';
	}
	/**
	*  There are no payment fields for payka, but we want to show the description if set.
	**/
	function payment_fields(){
		if($this -> description) echo wpautop(wptexturize($this -> description));
	}
	/**
	* Receipt Page
	**/
	function receipt_page($order){
		echo '<p>'.__('Thank you for your order, please click the button below to pay with Payka.', 'sm').'</p>';
		echo $this -> generate_payka_form($order);
	}
	/**
	* Generate payka button link
	**/
	public function generate_payka_form($order_id){
		global $woocommerce;
		$order = new WC_Order( $order_id );
		$txnid = $order_id.'_'.date("ymds");
		$redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
		$productinfo = "Order $order_id";
		$str = "$this->merchant_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->salt";
		$hash = hash('sha512', $str);
		$payka_args = array(
			'key' => $this -> merchant_id,
			'txnid' => $txnid,
			'amount' => $order -> order_total,
			'productinfo' => $productinfo,
			'firstname' => $order -> billing_first_name,
			'lastname' => $order -> billing_last_name,
			'address1' => $order -> billing_address_1,
			'address2' => $order -> billing_address_2,
			'city' => $order -> billing_city,
			'state' => $order -> billing_state,
			'country' => $order -> billing_country,
			'zipcode' => $order -> billing_zip,
			'email' => $order -> billing_email,
			'phone' => $order -> billing_phone,
			'surl' => $redirect_url,
			'furl' => $redirect_url,
			'curl' => $redirect_url,
			'hash' => $hash,
			'pg' => 'NB'
		);
		$payka_args_array = array();
		foreach($payka_args as $key => $value){
			$payka_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
		}
		return '<form action="'.$this -> liveurl.'" method="post" id="payka_payment_form">
		' . implode('', $payka_args_array) . '
		<input type="submit" class="button-alt" id="submit_payka_payment_form" value="'.__('Pay via Payka', 'sm').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'sm').'</a>
		<script type="text/javascript">
		jQuery(function(){
		jQuery("body").block(
		{
		message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'sm').'",
		overlayCSS:
		{
		background: "#fff",
		opacity: 0.6
		},
		css: {
		padding:        20,
		textAlign:      "center",
		color:          "#555",
		border:         "3px solid #aaa",
		backgroundColor:"#fff",
		cursor:         "wait",
		lineHeight:"32px"
		}
		});
		jQuery("#submit_payka_payment_form").click();});</script>
		</form>';
	}
	/**
	* Process the payment and return the result
	**/
	function process_payment($order_id){
		global $woocommerce;
		$order = new WC_Order( $order_id );
		return array('result' => 'success', 'redirect' => add_query_arg('order',
		$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
		);
	}
	/**
	* Check for valid payka server callback
	**/
	function check_payka_response(){
		global $woocommerce;
		if(isset($_REQUEST['txnid']) && isset($_REQUEST['mihpayid'])){
			$order_id_time = $_REQUEST['txnid'];
			$order_id = explode('_', $_REQUEST['txnid']);
			$order_id = (int)$order_id[0];
			if($order_id != ''){
				try{
					$order = new WC_Order( $order_id );
					$merchant_id = $_REQUEST['key'];
					$amount = $_REQUEST['Amount'];
					$hash = $_REQUEST['hash'];
					$status = $_REQUEST['status'];
					$productinfo = "Order $order_id";
					echo $hash;
					echo "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}";
					$checkhash = hash('sha512', "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}");
					$transauthorised = false;
					if($order -> status !=='completed'){
						if($hash == $checkhash){
							$status = strtolower($status);
							if($status=="success"){
								$transauthorised = true;
								$this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
								$this -> msg['class'] = 'woocommerce_message';
								if($order -> status == 'processing'){
								} else{
									$order -> payment_complete();
									$order -> add_order_note('Payka payment successful<br/>Unnique Id from Payka: '.$_REQUEST['mihpayid']);
									$order -> add_order_note($this->msg['message']);
									$woocommerce -> cart -> empty_cart();
								}
							} else if($status=="pending"){
								$this -> msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
								$this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
								$order -> add_order_note('Payka payment status is pending<br/>Unnique Id from Payka: '.$_REQUEST['mihpayid']);
								$order -> add_order_note($this->msg['message']);
								$order -> update_status('on-hold');
								$woocommerce -> cart -> empty_cart();
							}
							else{
								$this -> msg['class'] = 'woocommerce_error';
								$this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
								$order -> add_order_note('Transaction Declined: '.$_REQUEST['Error']);
								//Here you need to put in the routines for a failed
								//transaction such as sending an email to customer
								//setting database status etc etc
							}
						} else{
							$this -> msg['class'] = 'error';
							$this -> msg['message'] = "Security Error. Illegal access detected";
							//Here you need to simply ignore this and dont need
							//to perform any operation in this condition
						}
						if($transauthorised==false){
							$order -> update_status('failed');
							$order -> add_order_note('Failed');
							$order -> add_order_note($this->msg['message']);
						}
						add_action('the_content', array(&$this, 'showMessage'));
					}
				} catch(Exception $e){
					// $errorOccurred = true;
					$msg = "Error";
				}
			}
		}
	}
	function showMessage($content){
		return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
	}
	// get all pages
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
	/**
	* Add the Gateway to WooCommerce
	**/
	function woocommerce_add_sm_payka_gateway($methods) {
		$methods[] = 'WC_SM_Payka';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_sm_payka_gateway' );
	
	/**
	* Add KA currency
	**/
	add_filter( 'woocommerce_currencies', 'add_payka_currency' );

	function add_payka_currency( $currencies ) {
		 $currencies['ABC'] = __( 'KiloAriary', 'woocommerce' );
		 return $currencies;
	}

	add_filter('woocommerce_currency_symbol', 'add_payka_currency_symbol', 10, 2);

	function add_payka_currency_symbol( $currency_symbol, $currency ) {
		 switch( $currency ) {
			  case 'ABC': $currency_symbol = 'KA'; break;
		 }
		 return $currency_symbol;
	}

}
