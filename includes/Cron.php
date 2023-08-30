<?php

namespace TelinfyMessaging\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cron {

	protected static $instance = null;

	public function __construct() {
		
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );

		$whatsapp_abd_enabled = get_option('wc_settings_telinfy_messaging_checkbox_abandoned_cart_whatsapp');
		$sms_abd_enabled = get_option('wc_settings_telinfy_messaging_checkbox_abandoned_cart_sms');

		if($whatsapp_abd_enabled == "yes" || $sms_abd_enabled =="yes" ){

			if ( ! wp_next_scheduled( 'tm_execute_cron' ) ) {
				wp_schedule_event( time(), 'tm_abd_cart', 'tm_execute_cron' );
			}

			add_action( 'tm_execute_cron', array( $this, 'tm_execute_cron' ) );

			if ( ! wp_next_scheduled( 'tm_remove_abandoned_cart' ) ) {
				wp_schedule_event( time(), 'tm_abd_cart_remove', 'tm_remove_abandoned_cart' );
			}

			add_action( 'tm_remove_abandoned_cart', array( $this, 'tm_remove_abandoned_cart' ) );
		}

	}

	public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }


	public function add_cron_schedule( $schedules ) {

		$whatsapp_abd_enabled = get_option('wc_settings_telinfy_messaging_checkbox_abandoned_cart_whatsapp');
		$sms_abd_enabled = get_option('wc_settings_telinfy_messaging_checkbox_abandoned_cart_sms');

		if($whatsapp_abd_enabled == "yes" || $sms_abd_enabled =="yes" ){

			$interval = get_option('wc_settings_telinfy_messaging_abd_cart_cron_interval');
			$interval_seconds =$interval* MINUTE_IN_SECONDS;

			$time_in_day = get_option('wc_settings_telinfy_messaging_abd_cart_remove_interval');
			$time_in_seconds = $time_in_day * DAY_IN_SECONDS;

			$schedules['tm_abd_cart'] = array(
				'interval' => $interval_seconds,
				'display'  => __( 'Tm Abandoned Cart' ),
			);
			$schedules['tm_abd_cart_remove'] = array(
				'interval' => $time_in_seconds,
				'display'  => __( 'Tm Abandoned Cart Remove' ),
			);

			return $schedules;
		}

	}

	public function tm_execute_cron() {
		
		do_action( 'tm_cron_send_message_abd_cart' );
	}

	public function tm_remove_abandoned_cart() {

		do_action( 'tm_cron_abd_cart_remove' );
		
	}
}
