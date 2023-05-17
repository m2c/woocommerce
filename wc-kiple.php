<?php
/**
 * Kiple WooCommerce Shopping Cart Plugin
 *
 * + Kiple Suppport Team <customercare@kiplepay.com>
 * + version 4.5.6
 * + Please change callback url to your domain in line no 20 below : http://shoppingcartdomainurl/?wc-api=WC_webcash_Gateway
 */


/**
 * Plugin Name: WooCommerce Kiple
 * Plugin URI: https://kiplepay.com/
 * Description: WooCommerce Kiple is a Kiple payment gateway for WooCommerce v3.6.x, v3.7.x, v3.8.x, v3.9.x, v4.0.x
 * Author: Kiple Tech Team
 * Author URI: https://kiplepay.com/
 * Version: 4.5.6
 * License: MIT
 * Text Domain: wcwebcash
 * Domain Path: /languages/
 * For callback : http://shoppingcartdomainurl/?wc-api=WC_webcash_Gateway
 * Invalid Transaction maybe is because vkey not found / skey wrong generated
 */

/**
 * If WooCommerce plugin is not available
 *
 */



function wcwebcash_woocommerce_fallback_notice() {
    $message = '<div class="error">';
    $message .= '<p>' . __( 'WooCommerce Kiple Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> to work!' , 'wcwebcash' ) . '</p>';
    $message .= '</div>';
    echo $message;
}

//Load the function
add_action( 'plugins_loaded', 'wcwebcash_gateway_load', 0 );

/**
 * Load Kiple gateway plugin function
 *
 * @return mixed
 */
function wcwebcash_gateway_load() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wcwebcash_woocommerce_fallback_notice' );
        return;
    }

    //Load language
    load_plugin_textdomain( 'wcwebcash', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    add_filter( 'woocommerce_payment_gateways', 'wcwebcash_add_gateway' );

    /**
     * Add Kiple gateway to ensure WooCommerce can load it
     *
     * @param array $methods
     * @return array
     */
    function wcwebcash_add_gateway( $methods ) {
        $methods[] = 'WC_webcash_Gateway';
        return $methods;
    }

    /**
     * Define the Kiple gateway
     *
     */
    class WC_webcash_Gateway extends WC_Payment_Gateway {

        /**
         * Construct the Kiple gateway class
         *
         * @global mixed $woocommerce
         */
        public function __construct() {
            global $woocommerce;

            $this->id = 'webcash';
            $this->icon = plugins_url( 'images/kiple.jpg', __FILE__ );
            $this->has_fields = false;
            $this->method_title = __( 'Kiple', 'wcwebcash' );

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user setting variables.
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->verify_key = $this->settings['verify_key'];
            $this->test_mode = $this->settings['test_mode'];

            // Actions.
            add_action( 'valid_webcash_request_returnurl', array( &$this, 'check_webcash_response_returnurl' ) );
            add_action( 'valid_webcash_request_callback', array( &$this, 'check_webcash_response_callback' ) );
            add_action( 'woocommerce_receipt_webcash', array( &$this, 'receipt_page' ) );

            //save setting configuration
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Payment listener/API hook
            add_action( 'woocommerce_api_wc_webcash_gateway', array( $this, 'check_ipn_response' ) );

            // Checking if merchant_id is not empty.
            $this->merchant_id == '' ? add_action( 'admin_notices', array( &$this, 'merchant_id_missing_message' ) ) : '';

            // Checking if verify_key is not empty.
            $this->verify_key == '' ? add_action( 'admin_notices', array( &$this, 'verify_key_missing_message' ) ) : '';
        }

        /**
         * Checking if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use() {
            if ( !in_array( get_woocommerce_currency() , array( 'MYR' ) ) ) {
                return false;
            }
            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         */
        public function admin_options() {
            ?>
            <h3><?php _e( 'Kiple Online Payment', 'wcwebcash' ); ?></h3>
            <p><?php _e( 'Kiple Online Payment works by sending the user to Kiple to enter their payment information.', 'wcwebcash' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <?php
        }

        /**
         * Gateway Settings Form Fields.
         *
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'wcwebcash' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Kiple', 'wcwebcash' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'wcwebcash' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'wcwebcash' ),
                    'default' => __( 'Kiple Malaysia Online Payment', 'wcwebcash' )
                ),
                'description' => array(
                    'title' => __( 'Description', 'wcwebcash' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'wcwebcash' ),
                    'default' => __( 'Pay with Kiple Malaysia Online Payment', 'wcwebcash' )
                ),
                'merchant_id' => array(
                    'title' => __( 'Merchant ID', 'wcwebcash' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Kiple Merchant ID.', 'wcwebcash' ) . ' ' . sprintf( __( 'You can refer to Kiple customer service in: %swebcash Account%s.', 'wcwebcash' ), '<a href="https://kiple.com/contactus" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'verify_key' => array(
                    'title' => __( 'Verify Key', 'wcwebcash' ),
                    'type' => 'text',
                    'description' => __( 'Just enter any value. This is for future update.', 'wcwebcash' ) . ' ' . sprintf( __( 'You can to get this information in: %swebcash Account%s.', 'wcwebcash' ), '<a href="https://kiple.com/contactus" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'test_mode' => array(
                    'title' => __( 'Test Mode', 'wcwebcash' ),
                    'type' => 'checkbox',
                    'description' => __( 'Tick to test in UAT Environment'),
                    'default' => 'yes'
                )
            );
        }

        /**
         * Generate the form.
         *
         * @param mixed $order_id
         * @return string
         */
        public function generate_form( $order_id ) {
            $order = new WC_Order( $order_id );
            if($this->test_mode != 'yes') {
                $pay_url = 'https://webcash.com.my/wcgatewayinit.php';
            } else {
                $pay_url = 'https://uat.kiplepay.com/wcgatewayinit.php';
            }
            
            //$pay_url = $this->pay_url;
            $total = $order->get_total();
   //       $vcode = md5($total.$this->merchant_id.$order->get_id().$this->verify_key);

            if ( sizeof( $order->get_items() ) > 0 )
                foreach ( $order->get_items() as $item )
                    if ( $item['qty'] )
                        $item_names[] = $item['name'] . ' x ' . $item['qty'];

            $desc = sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode( ', ', $item_names );

            $mercref = $order->get_id() . '-' . uniqid();
            $webcash_args = array(
                'ord_mercID' => $this->merchant_id,
                'ord_mercref' => $mercref,
                'ord_totalamt' => $total,
                'ord_shipname' => $order->get_billing_first_name()." ".$order->get_billing_last_name(),
                'ord_telephone' => $order->get_billing_phone(),
                'ord_email' => $order->get_billing_email(),
                'ord_date' => date('Y-m-d H:i:s'),
                'ord_shipcountry' => $order->get_billing_country(),
                'currency' => get_woocommerce_currency(),
                'ord_customfield4' => "plg_woocommerce",
                'ord_returnURL' => add_query_arg( 'wc-api', 'WC_webcash_Gateway', home_url( '/' ) ),
                'dynamic_callback_url' => add_query_arg( 'wc-api', 'WC_webcash_Gateway', home_url( '/' ) ),
                'version' => '2.0',
                'merchant_hashvalue' => sha1($this->verify_key . $this->merchant_id . $mercref . str_replace(".","",str_replace(",","",$total)))
            );

            $webcash_args_array = array();

            foreach ($webcash_args as $key => $value) {
                $webcash_args_array[] = "<input type='hidden' name='".$key."' value='". $value ."' />";
            }

            return "<form action='".$pay_url."' method='post' id='webcash_payment_form' name='webcash_payment_form'>"
                    . implode('', $webcash_args_array)
                    . "<input type='submit' class='button-alt' id='submit_webcash_payment_form' value='" . __('Pay via webcash', 'woothemes') . "' /> "
                    . "<a class='button cancel' href='" . $order->get_cancel_order_url() . "'>".__('Cancel order &amp; restore cart', 'woothemes')."</a>"
                    . "<script>document.webcash_payment_form.submit();</script>"
                    . "</form>";
        }

        /**
         * Order error button.
         *
         * @param  object $order Order data.
         * @return string Error message and cancel button.
         */
        protected function webcash_order_error( $order ) {
            $html = '<p>' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'wcwebcash' ) . '</p>';
            $html .='<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Click to try again', 'wcwebcash' ) . '</a>';
            return $html;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );
            $url = $order->get_checkout_payment_url(true);
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true))
            );
        }

        /**
         * Output for the order received page.
         *
         */
        public function receipt_page( $order ) {
            echo $this->generate_form( $order );
        }

        /**
         * Check for Kiple Response
         *
         * @access public
         * @return void
         */
        function check_ipn_response() {
            @ob_clean();

            if ( !( $_POST['nbcb'] )) {
                do_action( "valid_webcash_request_returnurl", $_POST );
            }
            else if ( $_POST['nbcb'] ) {
                do_action ( "valid_webcash_request_callback", $_POST );
            }
            else {
                wp_die( "Kiple Request Failure" );
            }
        }

        /**
         * This part is returnurl function for Kiple
         *
         * @global mixed $woocommerce
         */
        function check_webcash_response_returnurl() {
            global $woocommerce;

            $amount = $_POST['ord_totalamt'];
            $orderid = $_POST['ord_mercref'];
            $appcode = $_POST['appcode'];
            $tranID = $_POST['wcID'];
            $domain = $_POST['domain'];
            $status = $_POST['returncode'];
            $currency = $_POST['currency'];
            $paydate = $_POST['ord_date'];
            $channel = $_POST['channel'];
            $skey = $_POST['ord_key'];

            $orderid = explode('-', $orderid);
            $orderid = $orderid[0];
            $order = new WC_Order( $orderid );

           if (!in_array($order->status, ['failed', 'pending'])) {
                
                if($order->status == "processing"){
                    wp_redirect($order->get_checkout_order_received_url());
                } else {
                    wp_redirect($order->get_view_order_url());
                }
                exit;
            }

			
			$referer = "<br>Referer: ReturnURL";
            $HashAmount = str_replace(".","",str_replace(",","",$amount));
            $returnHashKey = sha1($this->verify_key . $this->merchant_id . $_POST['ord_mercref'] . $HashAmount . $status);

            if ($skey != $returnHashKey) {
                $order->add_order_note('Kiple Payment Status: FAILED (Invalid Hash Key)'.'<br>Transaction ID: ' . $tranID . $referer);
                $order->update_status('failed', sprintf(__('Payment %s via Kiple.', 'woocommerce'), $tranID ) );
                wp_redirect($order->get_view_order_url());
                exit;
            }


             if ($status == "100") {
                    // success transaction
                    $order->set_transaction_id($tranID);
                    $order->add_order_note('Kiple Payment Status: SUCCESSFUL'.'<br>Transaction ID: ' . $tranID . $referer);
                    $order->payment_complete();
                    wp_redirect($order->get_checkout_order_received_url());
                    exit;
            } else if ($status == "E1") {
                $order->add_order_note('Kiple Payment Status: FAILED (Other Reason : '.$status.')'.'<br>Transaction ID: ' . $tranID . $referer);
                $order->update_status('failed', sprintf(__('Payment %s via Kiple.', 'woocommerce'), $tranID ) );
                //$order->payment_complete($tranID);
                wp_redirect($order->get_view_order_url());
                exit;
            } else if ($status == "E2") {  //status 11 which is failed
                $order->add_order_note('Kiple Payment Status: ABORTED'.'<br>Transaction ID: ' . $tranID . $referer);
                $order->update_status('failed', sprintf(__('Payment %s via Kiple.', 'woocommerce'), $tranID ) );
                //$order->payment_complete();
                //$woocommerce->cart->empty_cart();
                wp_redirect($order->get_view_order_url());
                //wp_redirect($order->get_cancel_order_url());
                exit;
            } else   {  //invalid transaction
                $order->add_order_note('Kiple Payment Status: FAILED (Other Reason : '.$status.')'.'<br>Transaction ID: ' . $tranID . $referer);
                $order->update_status('failed', sprintf(__('Payment %s via Kiple.', 'woocommerce'), $tranID ) );
                //$woocommerce->cart->empty_cart();
                wp_redirect($order->get_view_order_url());
                //wp_redirect($order->get_cancel_order_url());
                exit;
            }
        }

        /**
         * This part is callback function for Kiple
         *
         * @global mixed $woocommerce
         */
        function check_webcash_response_callback() {
            global $woocommerce;

            $amount = $_POST['ord_totalamt'];
            $orderid = $_POST['ord_mercref'];
            $appcode = $_POST['appcode'];
            $tranID = $_POST['wcID'];
            $domain = $_POST['domain'];
            $status = $_POST['returncode'];
            $currency = $_POST['currency'];
            $paydate = $_POST['ord_date'];
            $channel = $_POST['channel'];
            $skey = $_POST['ord_key'];


            $orderid = explode('-', $orderid);
            $orderid = $orderid[0];
            $order = new WC_Order( $orderid );

            if (!in_array($order->status, ['failed', 'pending'])) {
                
                if($order->status == "processing"){
                    wp_redirect($order->get_checkout_order_received_url());
                } else {
                    wp_redirect($order->get_view_order_url());
                }
                exit;
            }

            $referer = "<br>Referer: CallBackURL";
            $HashAmount = str_replace(".","",str_replace(",","",$amount));
            $returnHashKey = sha1($this->verify_key . $this->merchant_id . $_POST['ord_mercref'] . $HashAmount . $status);

            if ($skey != $returnHashKey) {
                $order->add_order_note('Kiple Payment Status: FAILED (Invalid Hash Key)'.'<br>Transaction ID: ' . $tranID . $referer);
                $order->update_status('failed', sprintf(__('Payment %s via Kiple.', 'woocommerce'), $tranID ) );
                wp_redirect($order->get_view_order_url());
                exit;
            }

           if ($status == "100") {
                {
                    $order->add_order_note('Kiple Payment Status: SUCCESSFUL'.'<br>Transaction ID: ' . $tranID . $referer);
                    $order->set_transaction_id( $tranID );
                    $order->payment_complete();
                }
            }
            else if ($status == "E1") {
                $order->add_order_note('Kiple Payment Status: FAILED (Other Reason : '.$status.')'.'<br>Transaction ID: ' . $tranID . $referer);
                $order->update_status('on-hold', sprintf(__('Payment %s via Kiple.', 'woocommerce'), $tranID ) );
            }
            else if ($status == "E2") {
                $order->add_order_note('Kiple Payment Status: ABORTED'.'<br>Transaction ID: ' . $tranID . $referer);
                $order->update_status('on-hold', sprintf(__('Payment %s via Kiple.', 'woocommerce'), $tranID ) );
            }
            else { //status 11 which is failed
                $order->add_order_note('Kiple Payment Status: FAILED (Other Reason : '.$status.')'.'<br>Transaction ID: ' . $tranID . $referer);
                $order->update_status('failed', sprintf(__('Payment %s via Kiple.', 'woocommerce'), $tranID ) );
            }
        }

        /**
         * Adds error message when not configured the app_key.
         *
         */
        public function merchant_id_missing_message() {
            $message = '<div class="error">';
            $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your Merchant ID in Kiple. %sClick here to configure!%s' , 'wcwebcash' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_webcash_Gateway">', '</a>' ) . '</p>';
            $message .= '</div>';
            echo $message;
        }

        /**
         * Adds error message when not configured the app_secret.
         *
         */
        public function verify_key_missing_message() {
            $message = '<div class="error">';
            $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your Verify Key in Kiple. %sClick here to configure!%s' , 'wcwebcash' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_webcash_Gateway">', '</a>' ) . '</p>';
            $message .= '</div>';
            echo $message;
        }
    }
}
