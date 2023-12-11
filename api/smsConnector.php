<?php

namespace TelinfyMessaging\Api;

// Forbid accessing directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class telinfy_sms_connector{

	protected static $instance = null;

	public static function telinfy_get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

     /**
     * Send sms to the customers
     *
     * @return void
     */

	public function telinfy_send_sms($message,$to,$template_id){

	    // sms send logic

	    // API URL and parameters
	    $api_url = 'http://sapteleservices.com/SMS_API/sendsms.php';
	    $username = get_option('wc_settings_telinfy_messaging_api_key_sms');
	    $password = get_option('wc_settings_telinfy_messaging_api_secret_sms');;
	    $mobile = $to;
	    $sendername = get_option('wc_settings_telinfy_messaging_sms_sender_name'); //'GRNADS'
	    $routetype = 1;
	    
	    $encoded_url = preg_replace_callback(
		    '/[^-_.~a-zA-Z0-9]/',
		    function ($matches) {
		        return '%' . strtoupper(bin2hex($matches[0]));
		    },
		    $message
		);

	    // Build the request URL
	    $request_url = $api_url . "?username=$username&password=$password&mobile=$mobile&sendername=$sendername&message=$encoded_url&routetype=$routetype&tid=$template_id";


		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $request_url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		));

		$response_data = curl_exec($curl);

		$response = explode(";", $response_data);

		$response_array = array();

		foreach ($response as $pair) {

		    list($key, $value) = explode(':', $pair, 2);
		    $response_array[$key] = trim($value);
		}

		curl_close($curl);

		if(isset($response_array["Status"]) && $response_array["Status"] == 1){
			$result = array(
                "status"=>"success",
                "status_code"=>200,
                "message"=>"message send Successfully",
                "response"=>$response_array
            );
		}else{
			 $result = array(
                "status"=>"error",
                "message"=>"Failed",
                "response"=>$response_array
            );
		}
		return $result;

	}  

}