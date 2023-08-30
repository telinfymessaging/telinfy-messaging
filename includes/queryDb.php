<?php

namespace TelinfyMessaging\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class queryDb {

	public $cart_record_tb;
	public $wpdb;
	public $user_phone_tb;
	protected static $instance = null;

	protected $format = array(
		'user_id'             => '%d',
		'abandoned_cart_info' => '%s',
		'abandoned_cart_time' => '%s',
		'abd_sent'			  => '%d',
		'current_lang'        => '%s',
		'user_type'           => '%s',
		'session_id'          => '%s',
		'whatsapp_sent'       => '%d',
		'whatsapp_complete'   => '%d',
		'sms_sent'     		  => '%d',
		'sms_complete'        => '%s',
		'message_complete'    => '%s',
		'customer_ip'         => '%s',
	);

	public function __construct() {

		global $wpdb;
		$this->wpdb             = $wpdb;
		$this->cart_record_tb   = $wpdb->prefix . "tm_abandoned_cart_record";
		$this->user_phone_tb	= $wpdb->prefix . "tm_user_phone";
	}

	public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
	* Set the session value
	*
	*/

	public static function set_session( $key, $value ) {
		WC()->session->set( $key, $value );
	}

	/**
	* Get the session value 
	*
	*/

	public static function get_session( $key ) {
		return WC()->session ? WC()->session->get( $key ) : '';
	}

	/**
	* Update abandoned cart record
	*
	*/

	public function update_abd_cart_record_data($data = array(), $where = array()){

		global $wpdb;

		$data_fm = $where_fm = array();

		foreach ( $data as $item ) {
			if ( isset( $this->format[ $item ] ) ) {
				$data_fm[] = $this->format[ $item ];
			}
		}

		foreach ( $where as $item ) {
			if ( isset( $this->format[ $item ] ) ) {
				$where_fm[] = $this->format[ $item ];
			}
		}

		return $wpdb->update( $this->cart_record_tb, $data, $where, $data_fm, $where_fm );

	}

	/**
	* Fetch abandoned cart record
	*
	*/

	public function get_abdct_record( $abdc_id ) {
		global $wpdb;
		$query = "SELECT * FROM {$this->cart_record_tb} WHERE abd_id = %s";

		return $wpdb->get_row( $wpdb->prepare( $query, $abdc_id ) );
	}

	/**
	* Remove abandoned cart record
	*
	*/

	public function remove_abd_cart_record( $id) {
		global $wpdb;
		$where = array( 'abd_id' => $id );

		return $wpdb->delete( $this->cart_record_tb, $where, array( '%d' ) );
	}

	/**
	* Remove abandoned cart record by user id
	*
	*/

	public function remove_abd_cart_record_by_user_id( $id ) {
		global $wpdb;
		$wpdb->delete( $this->cart_record_tb, array( 'user_id' => $id ), array( '%d', '%d' ) );
	}

	/**
	* Insert abandoned cart record
	*
	*/

	public function create_abd_cart_record( $data = array() ) {
		global $wpdb;

		$data_fm = $where_fm = array();

		foreach ( $data as $item ) {
			if ( isset( $format[ $item ] ) ) {
				$data_fm[] = $this->format[ $item ];
			}
		}	
		$wpdb->insert( $this->cart_record_tb, $data, $data_fm );

		return $wpdb->insert_id;
	}

	/**
	* Bulk remove abandoned cart record
	*
	*/

	public function bulk_remove_abd_record( $ids ) {

		global $wpdb;
		$ids   = implode( ',', array_map( 'absint', $ids ) );
		$query = "delete from {$this->cart_record_tb} where abd_id in({$ids}) ";
		$wpdb->query( $query );
	}

	/**
	* Remove abandoned cart phone number record
	*
	*/

	public function remove_phone_record( $user_id ) {
		global $wpdb;
		$query = "delete from {$this->user_phone_tb} where user_id = %d ";
		$sql = $wpdb->prepare($query,$user_id);
        $res = $wpdb->query($sql);
	}


	/**
	* Remove abandoned cart record by time
	*
	*/

	public function remove_abd_cart_record_by_time( $time,$abd_cart_time_hour ) {
		global $wpdb;
		$time_in_seconds  = $time * DAY_IN_SECONDS;
		$abd_cart_time_hour_in_seconds = $abd_cart_time_hour * HOUR_IN_SECONDS;
		$total_time_to_leave_abd_cart = $time_in_seconds + $abd_cart_time_hour_in_seconds;
		$target_time_to_remove =  TM_CURRENT_TIME - $total_time_to_leave_abd_cart;

		$query = "delete from {$this->cart_record_tb} where abandoned_cart_time < %d limit 500";
		$wpdb->query( $wpdb->prepare( $query, $target_time_to_remove ) );
	}

	/**
	* Get abandoned cart records to notify customers
	*
	*/
	public function get_list_message_to_send( $sent_count, $time ) {

		global $wpdb;
		$sent_count = $sent_count;


		$que_member  = " AND (abandoned_cart_time < $time AND abd_sent < $sent_count AND user_type = 'user')";
		
		$query = "SELECT acr.* ,upt.*, wpu.user_login, wpu.user_email FROM {$this->cart_record_tb} AS acr LEFT JOIN {$wpdb->users} AS wpu ON acr.user_id = wpu.id INNER JOIN {$this->user_phone_tb} AS upt on acr.user_id=upt.user_id";
		$query .= " WHERE acr.abandoned_cart_info NOT LIKE '\"\"' AND acr.abandoned_cart_info NOT LIKE '[]' AND acr.abandoned_cart_info NOT LIKE '{\"cart\":[]}' ";
		$query .= "AND acr.message_complete is null {$que_member} ORDER BY acr.abd_id DESC";

		return ( $wpdb->get_results( $query ) );

	}

	public function update_sent_status($count,$type,$last_one,$abd_record_id){
		global $wpdb;
		$data = array($type."_sent" => $count);
		if($last_one){
			$data[$type."_complete"] = 1;
		}

		$where = array("abd_id" => $abd_record_id);

		$wpdb->update( $this->cart_record_tb, $data, $where);

	}

	public function update_notified($abd_record_id,$last_one,$abd_sent =""){
		global $wpdb;
		$data = array();
		if($last_one){
			$data["message_complete"] = 1;
		}
		if($abd_sent){
			$data["abd_sent"] = $abd_sent;
		}

		$where = array("abd_id" => $abd_record_id);

		$wpdb->update( $this->cart_record_tb, $data, $where);

	}

	/**
	* Insert abandoned cart phone number record
	*
	*/

	public function insert_abd_phone_record($phone,$user_id){

		global $wpdb;

		$update_query = "INSERT INTO {$this->user_phone_tb} (`user_id`,`phone`) VALUES (%d,%d) ON DUPLICATE KEY UPDATE `phone` = %d";

		$sql = $wpdb->prepare($update_query,$user_id,$phone,$phone);
        $res = $wpdb->query($sql);


	}

}

