<?php
/*
 * Plugin Name: Shoket Payment
 * Plugin URI: https://shoket.co
 * Description: Shoket payment for woocomerce allows your store in Tanzania to accept mobile money payment direct from your store
 * Author: Prosper Absalom
 * Author URI: https://shoket.co
 * Version: 1.0.0
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'shoket_add_gateway_class' );
function shoket_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Shoket_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'shoket_init_gateway_class' );
function shoket_init_gateway_class() {

	class WC_Shoket_Gateway extends WC_Payment_Gateway {

    /**
     * Class constructor, more about it in Step 3
        */
    public function __construct() {

	$this->id = 'shoket'; // payment gateway plugin ID
	$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
	$this->has_fields = true; // in case you need a custom credit card form
	$this->method_title = 'Shoket Payment';
	$this->method_description = 'Shoket payment for woocomerce allows your store in Tanzania to accept mobile money payment direct from your store'; // will be displayed on the options page

	// gateways can support subscriptions, refunds, saved payment methods,
	// but in this tutorial we begin with simple payments
	$this->supports = array(
		'products'
	);

	// Method with all the options fields
	$this->init_form_fields();

	// Load the settings.
	$this->init_settings();
	$this->title = $this->get_option( 'title' );
	$this->description = $this->get_option( 'description' );
	$this->enabled = $this->get_option( 'enabled' );
	$this->testmode = 'yes' === $this->get_option( 'testmode' );
	$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );

	// This action hook saves the settings
	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	// We need custom JavaScript to obtain a token
	// add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	
	// You can also register a webhook here
	// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
}

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
    public function init_form_fields(){

	$this->form_fields = array(
		'enabled' => array(
			'title'       => 'Enable/Disable',
			'label'       => 'Enable Shoket Payment',
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no'
		),
		'title' => array(
			'title'       => 'Title',
			'type'        => 'text',
			'description' => 'This controls the title which the user sees during checkout.',
			'default'     => 'Mobile money',
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => 'Description',
			'type'        => 'textarea',
			'description' => 'This controls the description which the user sees during checkout.',
			'default'     => 'Pay with your mobile money accout via Shoket payment.',
		),
		'testmode' => array(
			'title'       => 'Test mode',
			'label'       => 'Enable Test Mode',
			'type'        => 'checkbox',
			'description' => 'Place the payment gateway in test mode using test API keys.',
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'test_private_key' => array(
			'title'       => 'Test Private Key',
			'type'        => 'password',
		),
		'private_key' => array(
			'title'       => 'Live Private Key',
			'type'        => 'password'
		)
	);
}

    /**
     * You will need it if you want your custom credit card form, Step 4 is about it
     */
    public function payment_fields() {
        // ok, let's display some description before the payment form
        if ( $this->description ) {
            // you can instructions for test mode, I mean test card numbers etc.
            if ( $this->testmode ) {
                $this->description .= ' TEST MODE ENABLED. In test mode, there will be no any live payment happening, enables live mode to accept live payments.';
                $this->description  = trim( $this->description );
            }
            // display the description with <p> tags etc.
            echo wpautop( wp_kses_post( $this->description ) );
        }
    
        echo '<div class="clear"></div></fieldset>';
    
    }

    /*
        * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
        */
    // public function payment_scripts() {


    // }
    /*
        * We're processing the payments here, everything about it is in Step 5
        */
    public function validate_fields(){

        if( empty( $_POST[ 'billing_first_name' ]) ) {
            wc_add_notice(  'First name is required!', 'error' );
            return false;
        }
        if( empty( $_POST[ 'billing_last_name' ]) ) {
            wc_add_notice(  'Last name is required!', 'error' );
            return false;
        }
        if( empty( $_POST[ 'billing_email' ]) ) {
            wc_add_notice(  'Email Address is required!', 'error' );
            return false;
        }
        if( empty( $_POST[ 'billing_phone' ]) ) {
            wc_add_notice(  'Phone Number is required!', 'error' );
            return false;
        }
        return true;
}


public function process_payment( $order_id ) {

    global $woocommerce;

    // we need it to get any order detailes
    $order = wc_get_order( $order_id );


    /*
        * Array with parameters for API interaction
        */
    $data = $order->get_data(); 

    $full_name = $data['billing']['first_name'] . ' ' . $data['billing']['last_name'];

    $payment_amount = $data['total'];

    $subleng = strlen($payment_amount);

    if ( substr($payment_amount, -3) == '.00') {
        $payment_amount = substr($payment_amount, 0, ($subleng - 3));
    }

    $args = array(
        "amount"=>$payment_amount,
        "customer_name"=>$full_name,
        "email"=> $data['billing']['email'],
        "number_used"=> $data['billing']['phone'],
        "channel"=> 'Tigo',
    );

    /*
        * Your API interaction could be built with wp_remote_post()
        */
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.shoket.co/v1/charge/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($args),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $this->private_key,
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        wc_add_notice(  $response, 'error' );

        if( !is_wp_error( $response ) ) {

            $body = json_decode( $response['body'], true );

            wc_add_notice(  $body, 'error' );
            // it could be different depending on your payment processor
            // if ( $body['response']['responseCode'] == 'APPROVED' ) {

            // // we received the payment
            // $order->payment_complete();
            // $order->reduce_order_stock();

            // // some notes to customer (replace true with false to make it private)
            // $order->add_order_note( 'Hey, your order is paid! Thank you!', true );

            // // Empty cart
            // $woocommerce->cart->empty_cart();

            // // Redirect to the thank you page
            // return array(
            //     'result' => 'success',
            //     'redirect' => $this->get_return_url( $order )
            // );

        //     } else {
        //     wc_add_notice(  'Please try again.', 'error' );
        //     return;
        // }

        return;

    } else {
        wc_add_notice(  'Connection error.', 'error' );
        return;
    }
}

    /*
        * In case you need a webhook, like PayPal IPN etc
        */
    public function webhook() {


                
    }
}
}