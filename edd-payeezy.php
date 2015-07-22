<?php
/*
Plugin Name: Easy Digital Downloads - Payeezy Gateway
Plugin URL: http://easydigitaldownloads.com/downloads/payeezy
Description: Adds a payment gateway for Payeezy.com
Version: 1.0
Author: Easy Digital Downloads
Author URI: https://easydigitaldownloads.com
*/

if ( class_exists( 'EDD_License' ) && is_admin() ) {
	$license = new EDD_License( __FILE__, 'Payeezy Payment Gateway', '1.0', 'Easy Digital Downloads' );
}

class EDD_Payeezy_Gateway {

	public $plugin_url;

	/**
	 * Get things started
	 *
	 * @since 1.0
	 */
	public function __construct() {

		$this->plugin_url = plugin_dir_url( __FILE__ );

		add_action( 'edd_payeezy_cc_form', array( $this, 'card_form' ) );
		add_action( 'edd_gateway_2checkout', array( $this, 'process_payment' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'init', array( $this, 'process_webhooks' ) );

		add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'edd_settings_gateways', array( $this, 'settings' ) );
		add_filter( 'edd_payment_details_transaction_id-payeezy', array( $this, 'link_transaction_id' ), 11, 2 );
	}

	/**
	 * Register the gateway
	 *
	 * @since 1.0
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways['payeezy'] = array(
			'admin_label'    => __( 'Payeezy', 'edd-payeezy' ),
			'checkout_label' => __( 'Credit / Debit Card', 'edd-payeezy' )
		);
		return $gateways;
	}


	/**
	 * Process the purchase data and send to 2checkout
	 *
	 * @since 1.0
	 * @return void
	 */
	public function process_payment( $purchase_data ) {
		global $edd_options;

		$credentials = $this->get_api_credentials();
		foreach ( $credentials as $cred ) {
			if ( is_null( $cred ) ) {
				edd_set_error( 0, __( 'You must enter your Account Name and Secret Word in settings', 'edd-payeezy' ) );
				edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
			}
		}

		$payment_data = array(
			'price'         => $purchase_data['price'],
			'date'          => $purchase_data['date'],
			'user_email'    => $purchase_data['post_data']['edd_email'],
			'purchase_key'  => $purchase_data['purchase_key'],
			'currency'      => edd_get_currency(),
			'downloads'     => $purchase_data['downloads'],
			'cart_details'  => $purchase_data['cart_details'],
			'user_info'     => $purchase_data['user_info'],
			'status'        => 'pending'
		);

		// record the pending payment
		$payment = edd_insert_payment( $payment_data );

		if ( $payment ) {


		} else {
			// if errors are present, send the user back to the purchase page so they can be corrected
			edd_send_back_to_checkout( '?payment-mode=payeezy' );
		}
	}

	/**
	 * Register the gateway settings
	 *
	 * @since 1.0
	 * @return array
	 */
	public function settings( $settings ) {

		$edd_payeezy_settings = array(
			array(
				'id' => 'tco_settings',
				'name' => '<strong>' . __( 'Payeezy Gateway Settings', 'edd-payeezy' ) . '</strong>',
				'desc' => __( 'Configure your Payeezy Gateway Settings', 'edd-payeezy' ),
				'type' => 'header'
			),
			array(
				'id' => 'payeezy_merchant_id',
				'name' => __( 'Payeezy Merchant ID', 'edd-payeezy' ),
				'desc' => __( 'Enter your Payeezy merchant ID, obtained from the <a href="https://developer.payeezy.com/user/me/apps">Payeezy Developer</a> site', 'edd-payeezy' ),
				'type' => 'text'
			),
			array(
				'id' => 'payeezy_api_key',
				'name' => __( 'Payeezy API Key', 'edd-payeezy' ),
				'desc' => __( 'Enter your Payeezy API key, obtained from the <a href="https://developer.payeezy.com/user/me/apps">Payeezy Developer</a> site', 'edd-payeezy' ),
				'type' => 'text'
			),
			array(
				'id' => 'payeezy_api_secret',
				'name' => __( 'Payeezy API Secret', 'edd-payeezy' ),
				'desc' => __( 'Enter your Payeezy API secret, obtained from the <a href="https://developer.payeezy.com/user/me/apps">Payeezy Developer</a> site', 'edd-payeezy' ),
				'type' => 'text'
			),
			array(
				'id' => 'payeezy_js_security_key',
				'name' => __( 'Payeezy JS Security Key', 'edd-payeezy' ),
				'desc' => __( 'Enter your Payeezy JS Security Key, obtained from the <a href="https://developer.payeezy.com/user/me/apps">Payeezy Developer</a> site', 'edd-payeezy' ),
				'type' => 'text'
			),
			array(
				'id' => 'payeezy_ta_token',
				'name' => __( 'Payeezy Transarmor Token', 'edd-payeezy' ),
				'desc' => __( 'Enter your Payeezy Transarmer Token, obtained from the <a href="https://developer.payeezy.com/user/me/apps">Payeezy Developer</a> site', 'edd-payeezy' ),
				'type' => 'text'
			),
			array(
				'id' => 'payeezy_reporting_token',
				'name' => __( 'Payeezy Reporting Token', 'edd-payeezy' ),
				'desc' => __( 'Enter your Payeezy reporting token, obtained from the <a href="https://developer.payeezy.com/user/me/apps">Payeezy Developer</a> site', 'edd-payeezy' ),
				'type' => 'text'
			),
		);

		return array_merge( $settings, $edd_payeezy_settings );
	}

	/**
	 * Payeezy uses it's own credit card form because the card details are tokenized.
	 *
	 * We don't want the name attributes to be present on the fields in order to prevent them from getting posted to the server
	 *
	 * @access      public
	 * @since       1.0
	 * @return      void
	 */
	public function card_form( $echo = true ) {

		global $edd_options;

		ob_start(); ?>

		<?php if ( ! wp_script_is ( 'payeezy-js' ) ) : ?>
			<?php $this->scripts( true ); ?>
		<?php endif; ?>

		<?php do_action( 'edd_before_cc_fields' ); ?>

		<fieldset id="edd_cc_fields" class="edd-do-validate">
			<span><legend><?php _e( 'Credit Card Info', 'edd-payeezy' ); ?></legend></span>
			<?php if( is_ssl() ) : ?>
				<div id="edd_secure_site_wrapper">
					<span class="padlock"></span>
					<span><?php _e( 'This is a secure SSL encrypted payment.', 'edd-payeezy' ); ?></span>
				</div>
			<?php endif; ?>
			<p id="edd-card-number-wrap">
				<label for="card_number" class="edd-label">
					<?php _e( 'Card Number', 'edd-payeezy' ); ?>
					<span class="edd-required-indicator">*</span>
					<span class="card-type"></span>
				</label>
				<span class="edd-description"><?php _e( 'The (typically) 16 digits on the front of your credit card.', 'edd-payeezy' ); ?></span>
				<input type="text" autocomplete="off" payeezy-data="cc_number" id="card_number" class="card-number edd-input required" placeholder="<?php _e( 'Card number', 'edd-payeezy' ); ?>" />
			</p>
			<p id="edd-card-cvc-wrap">
				<label for="card_cvc" class="edd-label">
					<?php _e( 'CVC', 'edd-payeezy' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The 3 digit (back) or 4 digit (front) value on your card.', 'edd-payeezy' ); ?></span>
				<input type="text" size="4" autocomplete="off" payeezy-data="cvv_code" id="card_cvc" class="card-cvc edd-input required" placeholder="<?php _e( 'Security code', 'edd-payeezy' ); ?>" />
			</p>
			<p id="edd-card-name-wrap">
				<label for="card_name" class="edd-label">
					<?php _e( 'Name on the Card', 'edd-payeezy' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The name printed on the front of your credit card.', 'edd-payeezy' ); ?></span>
				<input type="text" autocomplete="off" payeezy-data="cardholder_name" id="card_name" class="card-name edd-input required" placeholder="<?php _e( 'Card name', 'edd-payeezy' ); ?>" />
			</p>
			<?php do_action( 'edd_before_cc_expiration' ); ?>
			<p class="card-expiration">
				<label for="card_exp_month" class="edd-label">
					<?php _e( 'Expiration (MM/YY)', 'edd-payeezy' ); ?>
					<span class="edd-required-indicator">*</span>
				</label>
				<span class="edd-description"><?php _e( 'The date your credit card expires, typically on the front of the card.', 'edd-payeezy' ); ?></span>
				<select payeezy-data="exp_month" id="card_exp_month" class="card-expiry-month edd-select edd-select-small required">
					<?php for( $i = 1; $i <= 12; $i++ ) { echo '<option value="' . $i . '">' . sprintf ('%02d', $i ) . '</option>'; } ?>
				</select>
				<span class="exp-divider"> / </span>
				<select payeezy-data="exp_year" id="card_exp_year" class="card-expiry-year edd-select edd-select-small required">
					<?php for( $i = date('Y'); $i <= date('Y') + 10; $i++ ) { echo '<option value="' . $i . '">' . substr( $i, 2 ) . '</option>'; } ?>
				</select>
			</p>
				<td><select name="auth" id="auth" payeezy-data="auth">
						<option value="false">false</option>
						<option value="true">true</option>
				</select>
				<select payeezy-data="card_type">
						<option value="visa">Visa</option>
						<option value="mastercard">Master Card</option>
						<option value="American Express">American Express</option>
						<option value="discover">Discover</option>
				</select>
			<?php do_action( 'edd_after_cc_expiration' ); ?>

		</fieldset>
		<div id="edd-payeezy-payment-errors"></div>
		<?php
		do_action( 'edd_after_cc_fields' );

		$form = ob_get_clean();

		if ( false !== $echo ) {
			echo $form;
		}

		return $form;
	}

	public function scripts( $override = false ) {

		if ( ! function_exists( 'edd_is_checkout' ) ) {
			return;
		}

		if ( ( edd_is_checkout() || $override ) && edd_is_gateway_active( 'payeezy' ) ) {

			wp_enqueue_script( 'edd-payeezy-js', $this->plugin_url . 'js/payeezy_v3.2.js', array( 'jquery' ), '3.2' );
			wp_enqueue_script( 'edd-payeezy-gateway', $this->plugin_url . 'js/edd-payeezy-gateway.js', array( 'jquery', 'edd-payeezy-js' ), '3.2' );

			$payeezy_vars = array(
				'merchant_id'  => edd_get_option( 'payeezy_merchant_id' ),
				'api_key'      => edd_get_option( 'payeezy_api_key' ),
				'security_key' => edd_get_option( 'payeezy_js_security_key' ),
				'ta_token'     => edd_is_test_mode() ? 'NOIW' : edd_get_option( 'payeezy_ta_token' )
			);

			wp_localize_script( 'edd-payeezy-gateway', 'edd_payeezy_vars', $payeezy_vars );

		}
	}

	/**
	 * Process webhooks sent from 2checkout
	 *
	 * @since 1.0
	 * @return void
	 */
	public function process_webhooks() {

	}

	/**
	 * Turn transaction ID into a URL
	 *
	 * @since 1.0
	 * @return string
	 */
	public function link_transaction_id( $transaction_id = '', $payment_id = 0 ) {
		
		$base_url = 'https://' . ( edd_is_test_mode() ? 'sandbox.' : '' ) . '2checkout.com/sandbox/sales/detail?sale_id=';
		$url      = '<a href="' . esc_url( $base_url . $transaction_id ) . '" target="_blank">' . $transaction_id . '</a>';

		return apply_filters( 'edd_2checkout_link_payment_details_transaction_id', $url );
	}

}

/**
 * Load our plugin
 *
 * @since 1.0
 * @return void
 */
function edd_payeezy_load() {
	$gateway = new EDD_Payeezy_Gateway;
	unset( $gateway );
}
add_action( 'plugins_loaded', 'edd_payeezy_load' );