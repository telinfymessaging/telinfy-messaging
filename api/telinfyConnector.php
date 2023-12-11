<?php

namespace TelinfyMessaging\Api;

use TelinfyMessaging\Includes\telinfy_query_db;

// Forbid accessing directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class telinfy_whatsapp_connector{

    private $client_id;
    private $client_secret;
    private $refresh_token;
    private $access_token;
    private $instance_url;
    public $querydb;
    private $retry = 0;
    protected static $instance = null;


    public function __construct(){

        $this->client_id = get_option( 'wc_settings_telinfy_messaging_api_key_whatsapp' );
        $this->client_secret = get_option( 'wc_settings_telinfy_messaging_api_secret_whatsapp' );
    }

    public static function telinfy_get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
    * Get API credentials from database
    *
    * @return void
    */
    protected function telinfy_set_api_info($api_token ="")
    {
        $api_access_token ="";

        if(isset($api_token)){

             $api_access_token = $api_token;

        }

        if(isset($api_access_token)){
            $this->access_token = $api_access_token;

        }else{
            $this->telinfy_get_api_token();
        }
    }

    

    /**
    * get token for the API
    *
    * @return array
    */
    public function telinfy_get_api_token($username="",$password="")
    {

        $cred_check = 0;
        if (!isset($this->client_id) || !isset($this->client_secret)) {
            return false;
        }
        if($username && $password){

            $cred_check = 1;
            $body = array(
            "userName" => $username,
            "password" => $password
            );
        }else{

            $body = array(
            "userName" => $this->client_id,
            "password" => $this->client_secret
            );            
        }

        
        $path = "https://api.telinfy.net/gaus/login";
        $header = array('content-type' => 'application/json');
        $body = json_encode($body);

        $header['content-length'] = !empty($body) ? strlen($body) : 0;
        $response = wp_remote_post(
            $path,
            array(
                'method' => "POST",
                'timeout' => 240,
                'headers' => $header,
                'body' => $body
            )
        );

        $res = !is_wp_error($response) && isset($response['body']) ? $response['body'] : "";
        $response = json_decode($res, true);
        if (isset($response['data']['accessToken']) && $response['data']['accessToken'] != "") {

            $this->telinfy_set_api_info($response['data']['accessToken']);
            return array(
                "status"=>"success",
                "token"=>$response['data']['accessToken']
            );
        }
        else{
            $message = isset($response['data']["error"])?"Generate Token Error: ".$response['data']["error"]:"Error in generating token";
            return array(
                "status"=>"error",
                "message"=>$message
            );
        }
    }

    /**
    * Build Api request body
    *
    * @return array
    */

    public function telinfy_render_whatsapp_body($body_params,$to,$templateName,$header_image_link){
        
        $language=get_option("wc_settings_telinfy_messaging_whatsapp_language"); 
        // $button_redirect_url =  get_permalink(wc_get_page_id('myaccount'));

        $page_slug = 'myaccount';
        $page_id = wc_get_page_id($page_slug);

        if (isset($page_id)) {
            $page_permalink = get_permalink($page_id);

            // Get the base URL
            $base_url = site_url();

            // Remove the base URL from the permalink
            $relative_permalink = str_replace($base_url, '', $page_permalink);
        }


        $params = array(
                    array(
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => array(
                            array(
                                'type' => 'text',
                                'text' => $relative_permalink
                            )
                        )
                    )
                );

        $body = array(
                "to"=>$to,
                "type"=>"template",
                "templateName"=>$templateName,
                "language"=>$language,
                "header"=>array(
                    'parameters' => array(
                        array(
                            'type' => 'image',
                            'image' => array(
                                'link' => $header_image_link
                            )
                        )
                    )
                ),
                "body"=>array(
                    'parameters' => $body_params
                ),
                "button"=> $params
            );
        return $body;
    }


    /**
    * Call to Api end point
    *
    * @return void
    */

    public function telinfy_send_message($body,$to){

        $method = "POST";
        $api_base_url_whatsapp = get_option('wc_settings_telinfy_messaging_api_base_url_whatsapp');
        $trimmed_api_base_url = rtrim($api_base_url_whatsapp, '/');
        $endpoint = $trimmed_api_base_url."/whatsapp/templates/message";
        $response = $this->telinfy_send_api_request($endpoint,$method,json_encode($body));
        return $response;

    }

    /**
    * Function to fetch bussiness id of the account
    *
    * @return string
    */

    private function telinfy_get_whatsapp_bussiness_id(){

        $api_base_url_whatsapp = get_option('wc_settings_telinfy_messaging_api_base_url_whatsapp');
        $trimmed_api_base_url = rtrim($api_base_url_whatsapp, '/');
        $endpoint = $trimmed_api_base_url ."/whatsapp-business/accounts";
        $method = "GET";
        $get_whatsapp_bussiness_id_data = $this->telinfy_send_api_request($endpoint,$method);
        
        if(isset($get_whatsapp_bussiness_id_data["business_id"]) && $get_whatsapp_bussiness_id_data["business_id"]){
            return $get_whatsapp_bussiness_id_data["business_id"];
        }else{
            return 0;
        }
    }


    /**
    * Function to fetch WhatsApp templates
    *
    * @return array
    */

    public function telinfy_get_whatsapp_templates(){

        $business_id = $this->telinfy_get_whatsapp_bussiness_id();

        if(isset($business_id)){

            $api_base_url_whatsapp = get_option('wc_settings_telinfy_messaging_api_base_url_whatsapp');
            $trimmed_api_base_url = rtrim($api_base_url_whatsapp, '/');

            $endpoint = $trimmed_api_base_url."/whatsapp/templates?whatsAppBusinessId=$business_id";
            $method = "GET";
            $whatsapp_templates = $this->telinfy_send_api_request($endpoint,$method);

            if(isset($whatsapp_templates['data']['status']) && $whatsapp_templates['data']['status'] == "404"){
                $new_business_id = $this->telinfy_get_whatsapp_bussiness_id();
                
                $endpoint = $trimmed_api_base_url."/whatsapp/templates?whatsAppBusinessId=$new_business_id";
                $whatsapp_templates = $this->telinfy_send_api_request($endpoint,$method);
            }

            if(isset($whatsapp_templates["business_id"])){

                $whatsapp_templates_list = $whatsapp_templates["business_id"];

                if(isset($whatsapp_templates_list)){

                    $whatsapp_template_names = array_column($whatsapp_templates_list, "name", "name");
                    
                    return $whatsapp_template_names;

                }else{

                    return 0;
                }
            }
        }else{
            return 0;
        }
    }

    /**
    * Connection to the endpoint
    *
    * @return void
    */

    private function telinfy_send_api_request($endpoint,$method,$body =""){
        
        if(!isset($endpoint) || (!isset($this->access_token))){
            $response = $this->telinfy_get_api_token();// creating new token
            if(isset($response["status"]) && $response["status"] == "error"){
                return $response;
            }
        }
        $path = $endpoint;
        if($method =="POST"){
            $header = array(
                "Authorization" => " Bearer " . $this->access_token,
                "content-type" => "application/json"
            );
            if (is_array($body) && count($body) > 0) {
                $body = http_build_query($body);
            }
            if ($method != "get") {
                $header['content-length'] = !empty($body) ? strlen($body) : 0;
            }
            $request = wp_remote_post(
                $path,
                array(
                    'method' => strtoupper($method),
                    'timeout' => 240,
                    'headers' => $header,
                    'body' => $body
                )
            );
        }else{
            $header = array(
                "Authorization" => " Bearer " . $this->access_token
            );

            $request = wp_remote_get(
                $path,
                array(
                    'method' => strtoupper($method),
                    'timeout' => 240,
                    'headers' => $header
                )
            );
        }
        if(is_wp_error($request)){
            return array("status"=>"error","message"=>"Telinfy API: Request failed");
        }

        $response = isset($request['body']) ? json_decode($request['body'],true) : [];

        if(wp_remote_retrieve_response_code( $request ) == 201 && $response["success"]){

            $response = array(
                "status"=>"created",
                "message"=>"Successfull",
                "data"=>$response
            );
        }else if(is_array($response) && wp_remote_retrieve_response_code( $request ) == 401 && ($response["message"] == "Wrong authentication token"|| $response["message"] == "Invalid session")){
            // Token expired. 

            $this->retry = $this->retry +1;

            if($this->retry == 2){
                return false;
            }

            $response = $this->telinfy_get_api_token();// creating new token.
            if($response["status"] == "success"){

               return $this->telinfy_send_api_request($endpoint,$method,$body);

            }
        }else if(wp_remote_retrieve_response_code( $request ) == 400 && $response["message"] == "Username or Password is incorrect"){

            $response = array(
                "status"=>"error",
                "message"=>"Incorrect username or password. Please check the username and password"
            );

        }else if(isset($response["data"][0]["whatsAppBusinessId"])){

            $business_id = $response["data"][0]["whatsAppBusinessId"];

            $response = array(
                "status"=>"success",
                "message"=>"business id fetched",
                "business_id"=>$business_id
            );

        }else if(isset($response["data"]["waba_templates"])){

            $weba_templates = $response["data"]["waba_templates"];
            $response = array(
                "status"=>"success",
                "message"=>"business templates fetched",
                "business_id"=>$weba_templates
            );

        }else if(isset($response["data"]["status"]) && $response["data"]["status"] == "ACCEPTED"){
            $response = array(
                "status"=>"success",
                "status_code" => 200,
                "message"=>"message send Successfully",
                "response"=>$response["data"]
            );
        }
        else{

            $response = array(
                "status"=>"error",
                "message"=>isset($response[0]["message"])?$response[0]["message"]:"telinfy api error",
                "data"=>$response
            );
        }

        return $response;

    }

}