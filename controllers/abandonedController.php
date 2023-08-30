<?php

namespace TelinfyMessaging\Includes;

// Forbid accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . 'includes/queryDb.php' );

use TelinfyMessaging\Includes\QueryDb;

class abandonedController {

	protected static $instance = null;
	public $query;

	public function __construct() {

		$this->query = QueryDb::get_instance();

		add_action( 'woocommerce_cart_updated', array( $this, 'save_abandoned_cart_record' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'save_abandoned_cart_record' ), 999 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'save_abandoned_cart_record' ), 999 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'save_abandoned_cart_record' ), 999 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'save_abandoned_cart_record' ), 999 );
		add_action( 'woocommerce_calculate_totals', array( $this, 'save_abandoned_cart_record' ), 999 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'remove_abd_cart_after_success_order' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'remove_abd_cart_at_thank_you_page' ) );
		add_action( 'wp_login', array( $this, 'user_login' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'tm_cart_abandonment_tracking_script' ) );

		// update phone number from the checkout page.
		add_action( 'wp_ajax_tm_update_cart_abandonment_data', array( $this, 'tm_update_cart_abandonment_data' ) );
		add_action( 'wp_ajax_nopriv_tm_update_cart_abandonment_data', array( $this, 'tm_update_cart_abandonment_data' ) );

	}

	public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * update abandoned record inlogin
     *
     * @return void
     */

	public function user_login( $user_name, $user_info ) {
		$user_id        = $user_info->ID;
		if ( ! WC()->session ) return;
		$session_user_id        = WC()->session->get( 'user_id' );
		$session_key = WC()->session->get_customer_id();
		if ( $session_user_id ) {
			$this->query->update_abd_cart_record_data(
				array( 'user_id' => $user_id, 'user_type' => 'user' ),
				array( 'user_id' => $session_user_id )
			);
		} else {
			$this->query->update_abd_cart_record_data(
				array( 'user_id' => $user_id, 'user_type' => 'user' ),
				array( 'session_id' => $session_key )
			);
		}
	}

	/**
     * Save abandoned cart data to the table
     *
     * @return void
     */
	public function save_abandoned_cart_record() {


		if ( $this->check_bot() || ( is_admin() && ! wp_doing_ajax() ) || current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->os_platform = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$this->ip_add      = \WC_Geolocation::get_ip_address();

		if ( is_user_logged_in() ) {
			$whatsapp_abd_enabled = get_option('wc_settings_telinfy_messaging_checkbox_abandoned_cart_whatsapp');
			$sms_abd_enabled = get_option('wc_settings_telinfy_messaging_checkbox_abandoned_cart_sms');

			if($whatsapp_abd_enabled == "yes" || $sms_abd_enabled =="yes" ){
				$this->track_abandoned_cart( 'user' );
			}
		} 
	}

	/**
     * Track abandoned cart 
     *
     * @return void
     */

	public function track_abandoned_cart($type) {

		$cart_count   = wc()->cart->get_cart_contents_count();
		$abdc_data    = $cart_count ? json_encode( array( 'cart' => wc()->session->cart, 'currency' => get_woocommerce_currency() ) ) : '';

		$abdc_id = wc()->session->get( 'tm_cart_record_id' );
		$user_id = $type === 'user' ? get_current_user_id() : wc()->session->get( 'user_id' );
		$user_id = $user_id ? $user_id : 0;
		
		$check_insert = false;

		if ( $abdc_id ) {
			$result = $this->query->get_abdct_record( $abdc_id );
			if ( $result ) {
				if ( $abdc_data ) {
					$cut_time = 'user' == $type ? $this->member_abd_time() : 0;

					if ( $cut_time > $result->abandoned_cart_time ) { //out time	
						$this->query->remove_abd_cart_record( $abdc_id );

						$check_insert = true;
					} else {  //in time
						
						$this->query->update_abd_cart_record_data(
							array(
								'abandoned_cart_info' => $abdc_data,
								'abandoned_cart_time' => current_time( 'U' ),
								'current_lang'        => "",
								'user_id'             => $user_id,
								'user_type'           => $type
							),
							array( 'abd_id' => $abdc_id ) );
					}
				} else {
					$this->query->remove_abd_cart_record( $abdc_id );
					if($user_id){
						$this->query->remove_phone_record($user_id);
					}
				}
			} else {
				$check_insert = true;
			}
		} else {

			$check_insert = true;
		}
		if ( $check_insert && $abdc_data ) {
			if ( $user_id ) {
				$this->query->remove_abd_cart_record_by_user_id( $user_id );
				$this->query->remove_phone_record($user_id);
			}
			$insert_id = $this->query->create_abd_cart_record( array(
				'user_id'             => $user_id,
				'abandoned_cart_info' => $abdc_data,
				'abandoned_cart_time' => current_time( 'U' ),
				'user_type'           => $type,
				'customer_ip'         => $this->ip_add
			) );
			$billing_phone = get_user_meta( $user_id, 'billing_phone', true );
			$this->query->insert_abd_phone_record($billing_phone,$user_id);
			wc()->session->set( 'tm_cart_record_id', $insert_id );
			$current_list   = (array) wc()->session->get( 'tm_cart_record_ids_list' );
			$current_list[] = $insert_id;
			wc()->session->set( 'tm_cart_record_ids_list', $current_list );

		}

	}

	/**
	* check for bot requests 
	*
	* @return boolean
	*/

	public function check_bot() {

		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return true;
		}

		$bots = array(
			'rambler',
			'scoutjet',
			'similarpages',
			'oozbot',
			'shrinktheweb.com',
			'aboutusbot',
			'followsite.com',
			'googlebot',
			'aport',
			'yahoo',
			'msnbot',
			'turtle',
			'sape_context',
			'gigabot',
			'snapbot',
			'alexa.com',
			'megadownload.net',
			'askpeter.info',
			'igde.ru',
			'ask.com',
			'qwartabot',
			'yanga.co.uk',
			'yandex',
			'yandexSomething',
			'Copyscape.com',
			'AdsBot-Google',
			'domaintools.com',
			'dataparksearch',
			'google-sitemaps',
			'appEngine-google',
			'feedfetcher-google',
			'liveinternet.ru',
			'xml-sitemaps.com',
			'agama',
			'mail.ru',
			'omsktele',
			'yetibot',
			'Nigma.ru',
			'bing.com',
			'dotnetdotcom',
			'AspiegelBot',
			'curl',
			'picsearch',
			'sape.bot',
			'metadatalabs.com',
			'h1.hrn.ru',
			'googlealert.com',
			'seo-rus.com',
			'yaDirectBot',
			'yandeG',
			
		);
		foreach ( $bots as $bot ) {
			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && ( stripos( $_SERVER['HTTP_USER_AGENT'], $bot ) !== false || preg_match( '/bot|crawl|slurp|spider|mediapartners/i', $_SERVER['HTTP_USER_AGENT'] ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	* Remove abandoned cart data on thankyou page event
	*
	* @return void
	*/

	public function remove_abd_cart_at_thank_you_page( $order_id ) {
		$this->remove_abd_cart_when_cart_is_purchase( $order_id );
	}

	/**
	* Remove abandoned cart on purchase
	*
	* @return void
	*/

	public function remove_abd_cart_when_cart_is_purchase( $order_id ) {


		
		$id = $this->query->get_session( 'tm_cart_record_id' );
		if ( ! wc()->session ) {
			return;
		}
		$user_id = get_current_user_id();
		$ids = wc()->session->get( 'tm_cart_record_ids_list' );
		if ( ! empty( $ids ) ) {

			$this->query->bulk_remove_abd_record( $ids );
			wc()->session->__unset( 'tm_cart_record_ids_list' );

			$this->query->remove_phone_record($user_id);
		}

	}

	/**
	* Remove abandoned cart data on order success
	*
	* @return void
	*/
	public function remove_abd_cart_after_success_order( $order_id ) {

		//Remove record if order success
		$this->remove_abd_cart_when_cart_is_purchase( $order_id );
	}

	public function member_abd_time() {

		$abd_cart_time = get_option('wc_settings_telinfy_messaging_abd_cart_time');

		$abd_cart_time_seconds = $abd_cart_time * HOUR_IN_SECONDS;

		return TM_CURRENT_TIME -  $abd_cart_time_seconds;
	}

	/**
	* Add script to update cart data on chekout form field events
	*
	* @return void
	*/
	public function tm_cart_abandonment_tracking_script(){
		$tm_ca_ignore_users = get_option( 'tm_ca_ignore_users' );
		$current_user           = wp_get_current_user();
		$roles                  = $current_user->roles;
		$role                   = array_shift( $roles );
		if ( ! empty( $tm_ca_ignore_users ) ) {
			foreach ( $tm_ca_ignore_users as $user ) {
				$user = strtolower( $user );
				$role = preg_replace( '/_/', ' ', $role );
				if ( $role === $user ) {
					return;
				}
			}
		}

		global $post;
		wp_enqueue_script(
			'tm-abandon-tracking',
			plugins_url( 'includes/assets/js/tm-cart-abandonment-tracking.js', WOOCOMMERCE_TELINFY_MESSAGING_INCLUDES_PATH ),
			array( 'jquery' ),
			TM_ABANDON_VER,
			true
		);

		$vars = array(
			'ajaxurl'                   => admin_url( 'admin-ajax.php' ),
			'_nonce'                    => wp_create_nonce( 'tm_update_cart_abandonment_data' ),
			'_post_id'                  => get_the_ID(),
			'enable_ca_tracking'        => true,
		);

		wp_localize_script( 'tm-abandon-tracking', 'tm_ca_vars', $vars );
	}

	/**
	* Update phone number on filling address
	*
	* @return void
	*/

	public function tm_update_cart_abandonment_data(){

		check_ajax_referer( 'tm_update_cart_abandonment_data', 'security' );
		$post_data = $this->tm_sanitize_post_data();

		if ( isset( $post_data['tm_phone'] ) ) {
			$user_id = get_current_user_id();
			$this->query->insert_abd_phone_record($post_data['tm_phone'],$user_id);
			
		}

	}

	/**
	* Sanitize post data
	*
	* @return array
	*/

	public function tm_sanitize_post_data() {

		$input_post_values = array(
			'tm_email'                       => array(
				'default'  => '',
				'sanitize' => FILTER_SANITIZE_EMAIL,
			),
			'tm_phone'                       => array(
				'default'  => '',
				'sanitize' => FILTER_SANITIZE_STRING,
			),
			'tm_post_id'                     => array(
				'default'  => 0,
				'sanitize' => FILTER_SANITIZE_NUMBER_INT,
			),
		);

		$sanitized_post = array();
		foreach ( $input_post_values as $key => $input_post_value ) {

			if ( isset( $_POST[ $key ] ) ) {
				$sanitized_post[ $key ] = filter_input( INPUT_POST, $key, $input_post_value['sanitize'] );
			} else {
				$sanitized_post[ $key ] = $input_post_value['default'];
			}
		}
		return $sanitized_post;

	}

}