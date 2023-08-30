<?php

namespace TelinfyMessaging\Controllers;

// Forbid accessing directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use TelinfyMessaging\Api\TelinfyConnector;

require_once( ABSPATH . 'wp-admin/includes/file.php' );

class adminController {

    protected static $instance = null;
    public $connector;
    
    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }


    /**
     * Initiate admin actions for updating settings in WooCommerce
     *
     * @return void
     */
    public function init() {

        
        add_filter('woocommerce_settings_tabs_array',array($this,'add_settings_tab'),50);
        add_action('woocommerce_settings_tabs_settings_telinfy_messaging',  array($this,'settings_tab'),50);
        add_action('woocommerce_update_options_settings_telinfy_messaging', array($this,'update_settings'),50);
        add_action('woocommerce_admin_order_data_after_order_details', array($this,'add_custom_checkbox_to_order_admin'), 10, 1);
        //Custom feild hooks
        add_action('woocommerce_admin_field_file',array($this,'add_file_upload'),10,1);
        add_action('woocommerce_admin_field_button',array($this,'add_cred_check_button'),10,1);
        // Ajax hooks
        add_action('wp_ajax_tm_check_cred', array($this,'tm_check_cred'));
        add_action('wp_ajax_nopriv_tm_check_cred', array($this,'tm_check_cred'));

        add_action('wp_ajax_tm_list_templates', array($this,'tm_list_templates'));
        add_action('wp_ajax_nopriv_tm_list_templates', array($this,'tm_list_templates'));
    }
    
    /**
     * Adds new settings tab for the telinfy messaging plugin on the WooCommerce settings page
     *
     * @return array
     */
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['settings_telinfy_messaging'] = __( 'Telinfy Messaging', 'woocommerce-settings-telinfy-integration' );
        return $settings_tabs;
    }

    /**
     * Ajax function to validate the creditials
     *
     * @return void
     */

    public function tm_check_cred(){

        check_ajax_referer( 'tm_check_cred', 'security' );
        $post_data = $this->tm_sanitize_post_data();

        $username = $post_data['username'];
        $password = $post_data['password'];
        $type = $post_data['type'];
        if($username&& $password){

            if($type == "whatsapp-config"){
                $this->connector = TelinfyConnector::get_instance();
                $result = $this->connector->get_api_token($username,$password);

                if(isset($result['status']) && $result['status'] == "success"){

                    update_option("wc_settings_telinfy_messaging_api_key_whatsapp",$username,true);
                    update_option("wc_settings_telinfy_messaging_api_secret_whatsapp",$password,true);

                    echo json_encode(array("status" =>"success"));
                }else{
                    echo json_encode(array("status" =>"failed"));
                }
            }else{
                echo json_encode(array("status" =>"failed"));
            }
        }

        // Always remember to exit after handling AJAX
        wp_die();

    }

     /**
     * Ajax function to render templates
     *
     * @return void
     */

    public function tm_list_templates(){

        check_ajax_referer( 'tm_check_cred', 'security' );
        $post_data = $this->tm_sanitize_post_data();

        $type = $post_data['type'];

        if($type == "whatsapp-config"){
            $this->connector = TelinfyConnector::get_instance();
            sleep(2);
            $whatsapp_template_list = $this->connector->get_whatsapp_templates();

            if(is_array($whatsapp_template_list)){
                array_unshift($whatsapp_template_list,"select a template");
            }
            
            $whatsapp_selected = array();

            $whatsapp_selected["wc_settings_telinfy_messaging_whatsapp_template_name_order_confirmation"] = get_option("wc_settings_telinfy_messaging_whatsapp_template_name_order_confirmation");
            $whatsapp_selected["wc_settings_telinfy_messaging_whatsapp_template_name_order_cancellation"] = get_option("wc_settings_telinfy_messaging_whatsapp_template_name_order_cancellation");
            $whatsapp_selected["wc_settings_telinfy_messaging_whatsapp_template_name_order_refund"] = get_option("wc_settings_telinfy_messaging_whatsapp_template_name_order_refund");
            $whatsapp_selected["wc_settings_telinfy_messaging_whatsapp_template_name_order_notes"] = get_option("wc_settings_telinfy_messaging_whatsapp_template_name_order_notes"); 
            $whatsapp_selected["wc_settings_telinfy_messaging_whatsapp_template_name_order_shipment"] = get_option("wc_settings_telinfy_messaging_whatsapp_template_name_order_shipment"); 
            $whatsapp_selected["wc_settings_telinfy_messaging_whatsapp_template_name_other_order_status"] = get_option("wc_settings_telinfy_messaging_whatsapp_template_name_other_order_status"); 
            $whatsapp_selected["wc_settings_telinfy_messaging_whatsapp_template_name_abandoned_cart"] = get_option("wc_settings_telinfy_messaging_whatsapp_template_name_abandoned_cart"); 


            if(isset($whatsapp_template_list)){

                echo json_encode(array("status" =>"success","data" => $whatsapp_template_list,"selected" => $whatsapp_selected));
            }else{
                echo json_encode(array("status" =>"failed"));
            }
        }else{
            echo json_encode(array("status" =>"failed"));
        }

        // Always remember to exit after handling AJAX
        wp_die();

    }


    /**
     * Adds new plugin settings to the `Telinfy messaging` tab on the WooCommerce settings page
     *
     * @return void
     */
    public function settings_tab() {

        $settings = self::get_settings();
        woocommerce_admin_fields( $settings );

        // Add this line to display the file upload field
        wp_enqueue_script(
            'tm-cred-check',
            plugins_url( 'includes/assets/js/tm-check-cred.js', WOOCOMMERCE_TELINFY_MESSAGING_INCLUDES_PATH ) ,
            array( 'jquery' ),
            TM_ABANDON_VER,
            true
        );

        $vars = array(
            'ajaxurl'                   => admin_url( 'admin-ajax.php' ),
            '_nonce'                    => wp_create_nonce( 'tm_check_cred' ),
            '_post_id'                  => get_the_ID(),
            'enable_ca_tracking'        => true,
        );

        wp_localize_script( 'tm-cred-check', 'tm_cred_vars', $vars );

    }

    /**
     * Add the file upload in the settings for WhatsApp
     *
     * @return void
     */

    public function add_file_upload($value){

        $default_img = get_option('wc_settings_telinfy_messaging_whatsapp_file_upload');
    ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="wc_settings_telinfy_messaging_whatsapp_file_upload"><?php esc_html_e( 'Default header image', 'woocommerce-settings-telinfy-integration' ); ?></label>
            </th>
            <td class="forminp">
                <?php if($default_img){ ?>
                    <a href="<?=$default_img?>" class="whatsapp-config"><img src="<?=$default_img?>" alt="noimages" width="50px" height="50px" style="display:block" /></a>
                <?php }else{echo "<p style='display:block'>Image is not available</p>";}?>
                <input type="file" class="whatsapp-config" name="wc_settings_telinfy_messaging_whatsapp_file_upload" id="wc_settings_telinfy_messaging_whatsapp_file_upload">
                <p class="description"><?php esc_html_e( 'Upload your file here.', 'woocommerce-settings-telinfy-integration' ); ?></p>
            </td>
        </tr>
    <?php
    }

    /**
     * Add custom button for validating the credentials
     *
     * @return void
     */

    public function add_cred_check_button($value){

        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                
            </th>
            <td class="forminp">
                <span id="<?=$value["custom_attributes"]["custom_id"]?>-error" style="display: block;margin-bottom: 5px;"></span>
                <button id=<?=$value['id']?> class=<?=$value['class']?>><?=$value['name']?></button>
            </td>
        </tr>
    <?php

    }

    /**
     * Updates settings in the database
     *
     * @return void
     */
    public function update_settings() {

        $settings_data = self::get_settings();

        if (isset($_FILES['wc_settings_telinfy_messaging_whatsapp_file_upload'])) {
            // Handle the file upload and save it if necessary
            $uploaded_file = $_FILES['wc_settings_telinfy_messaging_whatsapp_file_upload'];
            $upload_overrides = array('test_form' => false);

            $movefile = wp_handle_upload($uploaded_file, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                // File successfully uploaded, you can save the file URL or other information here
                $file_url = $movefile['url'];
                // Save $file_url or other relevant information to your settings
                update_option('wc_settings_telinfy_messaging_whatsapp_file_upload', $file_url);
            } else {
                    // File upload failed, handle the error as per your requirement
            }
        }
        woocommerce_update_options($settings_data);

    }


    /**
     * Get an array of available settings for the plugin
     *
     * @return array
    */
    public function get_settings() {

        $this->connector = TelinfyConnector::get_instance();
        $whatsapp_template_list = $this->connector->get_whatsapp_templates();
        if(is_array($whatsapp_template_list)){
            array_unshift($whatsapp_template_list,"select a template");
        }else{
            $whatsapp_template_list = array("select a template");
        }
        
        // Configurations for whatsapp integration

        $settings_whatsapp = array(
            'telinfy_section_title_telinfy_whatsapp' => array(
                'name'     => __( 'WhatsApp integration settings', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'title',
                'desc'     => 'Please add configuration to enable WhatsApp messaging services. <a href="https://www.greenadsglobal.com/whatsapp-business-api-pricing/" target="_blank">Click here</a> for purchase plans',
                'id'       => 'wc_settings_telinfy_messaging_section_title_whatsapp'
            ),
            'telinfy_api_key_whatsapp' => array(
                'name'     => __( 'Username', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'text',
                'desc'     => __( '' ),
                'id'       => 'wc_settings_telinfy_messaging_api_key_whatsapp'
            ),
            'telinfy_api_secret_whatsapp' => array(
                'name'     => __( 'Password', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'password',
                'desc'     => __( ''),
                'id'       => 'wc_settings_telinfy_messaging_api_secret_whatsapp'
            ),
            'telinfy_whatsapp_check_cred' => array(
                'name'     => __( 'Validate credentials', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'button',
                'desc'     => __( ''),
                'custom_attributes'=> array("custom_id" =>'whatsapp-config'),
                'class'    => 'button-primary',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_cred_check'
            ),
            'telinfy_whatsapp_template_name_order_confirmation' => array(
                'name'     => __( 'Template for order confirmation', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'select',
                'options'  => $whatsapp_template_list,
                'desc'     => __( ''),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_template_name_order_confirmation'
            ),
            'telinfy_whatsapp_template_name_order_cancellation' => array(
                'name'     => __( 'Template for order cancellation', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'select',
                'options'  => $whatsapp_template_list,
                'desc'     => __( ''),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_template_name_order_cancellation'
            ),
            'telinfy_whatsapp_template_name_order_refund' => array(
                'name'     => __( 'Template for order refund', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'select',
                'options'  => $whatsapp_template_list,
                'desc'     => __( ''),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_template_name_order_refund'
            ),
            'telinfy_whatsapp_template_name_order_notes' => array(
                'name'     => __( 'Template for order notes', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'select',
                'options'  => $whatsapp_template_list,
                'desc'     => __( ''),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_template_name_order_notes'
            ),
            'telinfy_whatsapp_template_name_order_shipment' => array(
                'name'     => __( 'Template for order shipment', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'select',
                'options'  => $whatsapp_template_list,
                'desc'     => __( ''),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_template_name_order_shipment'
            ),
            'telinfy_whatsapp_template_name_other_order_status' => array(
                'name'     => __( 'Template for other order status', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'select',
                'options'  => $whatsapp_template_list,
                'desc'     => __( ''),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_template_name_other_order_status'
            ),
            'telinfy_whatsapp_template_name_abandoned_cart' => array(
                'name'     => __( 'Template for abandoned cart', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'select',
                'options'  => $whatsapp_template_list,
                'desc'     => __( ''),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_template_name_abandoned_cart'
            ),
            'telinfy_whatsapp_language' => array(
                'name'     => __( 'Language code', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'text',
                'desc'     => __( ''),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_language'
            ),
            'telinfy_whatsapp_file_upload' => array(
                'name'     => __( 'File Upload', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'file',
                'desc'     => __( 'Upload your file here.', 'woocommerce-settings-telinfy-integration' ),
                'class'    => 'whatsapp-config',
                'id'       => 'wc_settings_telinfy_messaging_whatsapp_file_upload'
            ),
            'telinfy_checkbox_order_confirmation_whatsapp' => array(
                'title'         => __( 'Order confirmation', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_confirmation_whatsapp',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'default'       => 'no',
                'class'         => 'whatsapp-config',
                'tooltip'       => 'Check this box to enable the WhatsApp messaging for order confirmation',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_cancellation_whatsapp' => array(
                'title'         => __( 'Order cancellation', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_cancellation_whatsapp',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'default'       => 'no',
                'class'         => 'whatsapp-config',
                'tooltip'       => 'Check this box to enable the WhatsApp messaging for order cancellation',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_refund_whatsapp' => array(
                'title'         => __( 'Order refund', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_refund_whatsapp',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'default'       => 'no',
                'class'         => 'whatsapp-config',
                'tooltip'       => 'Check this box to enable the WhatsApp messaging for order refund',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_notes_whatsapp' => array(
                'title'         => __( 'Order notes', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_notes_whatsapp',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'default'       => 'no',
                'class'         => 'whatsapp-config',
                'tooltip'       => 'Check this box to enable the WhatsApp messaging for order notes',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_shipment_whatsapp' => array(
                'title'         => __( 'Order shipment', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_shipment_whatsapp',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'default'       => 'no',
                'class'         => 'whatsapp-config',
                'tooltip'       => 'Check this box to enable the WhatsApp messaging for order shipment',
                'autoload'      => false,
            ),
            'telinfy_checkbox_other_order_status_whatsapp' => array(
                'title'         => __( 'Other order status', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_other_order_status_whatsapp',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'default'       => 'no',
                'class'         => 'whatsapp-config',
                'tooltip'       => 'Check this box to enable the WhatsApp messaging for all other order status change',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_abandoned_cart_whatsapp' => array(
                'title'         => __( 'Abandoned cart', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_abandoned_cart_whatsapp',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'default'       => 'no',
                'class'         => 'whatsapp-config',
                'tooltip'       => 'Check this box to enable the WhatsApp messaging for abandoned cart',
                'autoload'      => false,
            ),
            'telinfy_section_end_whatsapp' => array(
                 'type' => 'sectionend',
                 'id' => 'wc_settings_telinfy_section_end_whatsapp'
            )
        );

        // Configurations for sms integration

        $settings_sms = array(
            'telinfy_section_title_sms' => array(
                'name'     => __( 'SMS integration settings', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'title',
                'desc'     => 'Please add configuration to enable the SMS services. <a href="https://www.greenadsglobal.com/sms-pricing/" target="_blank">Click here</a> for purchase plans',
                'id'       => 'wc_settings_telinfy_messaging_section_title_sms'
            ),
            'telinfy_api_key_sms' => array(
                'name' => __( 'Username', 'woocommerce-settings-telinfy-integration' ),
                'type' => 'text',
                'desc' => __( '' ),
                'id'   => 'wc_settings_telinfy_messaging_api_key_sms'
            ),
            'telinfy_api_secret_sms' => array(
                'name' => __( 'Password', 'woocommerce-settings-telinfy-integration' ),
                'type' => 'password',
                'desc' => __( '',),
                'id'   => 'wc_settings_telinfy_messaging_api_secret_sms'
            ),
            'telinfy_messaging_sms_sender_name' => array(
                'title'         => __( 'Sender name', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_sender_name',
                'class'         => 'sms-config',
                'type'          => 'text'
            ),
            'telinfy_messaging_sms_tid_order_confirmation' => array(
                'title'         => __( 'Template id for order confirmation', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tid_order_confirmation',
                'class'         => 'sms-config',
                'type'          => 'text'
            ),
            'telinfy_messaging_sms_tdata_order_confirmation' => array(
                'title'         => __( 'Template for order confirmation', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tdata_order_confirmation',
                'class'         => 'sms-config',
                'type'          => 'textarea'
            ),
            'telinfy_messaging_sms_tid_order_cancellation' => array(
                'title'         => __( 'Template id for cancellation', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tid_order_cancellation',
                'class'         => 'sms-config',
                'type'          => 'text'
            ),
            'telinfy_messaging_sms_tdata_order_cancellation' => array(
                'title'         => __( 'Template for cancellation', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tdata_order_cancellation',
                'class'         => 'sms-config',
                'type'          => 'textarea'
            ),
            'telinfy_messaging_sms_tid_order_refund' => array(
                'title'         => __( 'Template id for order refund', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tid_order_refund',
                'class'         => 'sms-config',
                'type'          => 'text'
            ),
            'telinfy_messaging_sms_tdata_order_refund' => array(
                'title'         => __( 'Template for order refund', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tdata_order_refund',
                'class'         => 'sms-config',
                'type'          => 'textarea'
            ),
            'telinfy_messaging_sms_tid_order_notes' => array(
                'title'         => __( 'Template id for order notes', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tid_order_notes',
                'class'         => 'sms-config',
                'type'          => 'text'
            ),
            'telinfy_messaging_sms_tdata_order_notes' => array(
                'title'         => __( 'Template for order notes', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tdata_order_notes',
                'class'         => 'sms-config',
                'type'          => 'textarea'
            ),
            'telinfy_messaging_sms_tid_order_shipment' => array(
                'title'         => __( 'Template id for order shipment', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tid_order_shipment',
                'class'         => 'sms-config',
                'type'          => 'text'
            ),
            'telinfy_messaging_sms_tdata_order_shipment' => array(
                'title'         => __( 'Template for order shipment', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tdata_order_shipment',
                'class'         => 'sms-config',
                'type'          => 'textarea'
            ),
            'telinfy_messaging_sms_tid_other_order_status' => array(
                'title'         => __( 'Template id for other order status', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tid_other_order_status',
                'class'         => 'sms-config',
                'type'          => 'text'
            ),
            'telinfy_messaging_sms_tdata_other_order_status' => array(
                'title'         => __( 'Template for other order status', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tdata_other_order_status',
                'class'         => 'sms-config',
                'type'          => 'textarea'
            ),
            'telinfy_messaging_sms_tid_abandoned_cart' => array(
                'title'         => __( 'Template id for abandoned cart', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tid_abandoned_cart',
                'class'         => 'sms-config',
                'type'          => 'text'
            ),
            'telinfy_messaging_sms_tdata_abandoned_cart' => array(
                'title'         => __( 'Template for abandoned cart', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_sms_tdata_abandoned_cart',
                'class'         => 'sms-config',
                'type'          => 'textarea'
            ),
            'telinfy_checkbox_order_confirmation_sms' => array(
                'title'         => __( 'Order confirmation', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_confirmation_sms',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'tooltip'       => 'Check this box to enable the SMS services for order confirmation',
                'default'       => 'no',
                'class'         => 'sms-config',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_cancellation_sms' => array(
                'title'         => __( 'Order cancellation', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_cancellation_sms',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'tooltip'       => 'Check this box to enable the SMS services for order cancellation',
                'default'       => 'no',
                'class'         => 'sms-config',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_refund_sms' => array(
                'title'         => __( 'Order refund', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_refund_sms',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'tooltip'       => 'Check this box to enable the SMS services for order refund',
                'default'       => 'no',
                'class'         => 'sms-config',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_notes_sms' => array(
                'title'         => __( 'Order notes', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_notes_sms',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'tooltip'       => 'Check this box to enable the SMS services for order notes',
                'default'       => 'no',
                'class'         => 'sms-config',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_shipment_sms' => array(
                'title'         => __( 'Order shipment', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_order_shipment_sms',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'tooltip'       => 'Check this box to enable the SMS services for order shipment',
                'default'       => 'no',
                'class'         => 'sms-config',
                'autoload'      => false,
            ),
            'telinfy_checkbox_other_order_status_sms' => array(
                'title'         => __( 'Other order status', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_other_order_status_sms',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'tooltip'       => 'Check this box to enable the SMS services for all the other order status change',
                'default'       => 'no',
                'class'         => 'sms-config',
                'autoload'      => false,
            ),
            'telinfy_checkbox_order_abandoned_cart_sms' => array(
                'title'         => __( 'Abandoned cart', 'woocommerce-settings-telinfy-integration' ),
                'desc'          => __( '' ),
                'id'            => 'wc_settings_telinfy_messaging_checkbox_abandoned_cart_sms',
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'tooltip'       => 'Check this box to enable the SMS services for abandoned cart',
                'default'       => 'no',
                'class'         => 'sms-config',
                'autoload'      => false,
            ),
            'telinfy_section_end_sms' => array(
                 'type' => 'sectionend',
                 'id' => 'wc_settings_telinfy_messaging_section_end_sms'
            )
        );
        
        // Configuration for cron and abandoned cart

        $settings_cron = array(
             'telinfy_section_title_cron' => array(
                'name'     => __( 'Abandoned cart & cron schedule', 'woocommerce-settings-telinfy-integration' ),
                'type'     => 'title',
                'desc'     => 'Please setup time for cron schedules and abandoned cart timings',
                'id'       => 'wc_settings_telinfy_messaging_section_title_cron'
            ),
             'telinfy_cron_schedule_abd_cart' => array(
                'name'      => __( 'Abandoned cart cron interval', 'woocommerce-settings-telinfy-integration' ),
                'type'      => 'number',
                'desc'      => __( 'Time interval to run the cron to check abandoned cart. Please add time in <b>minutes</b>'),
                'id'        => 'wc_settings_telinfy_messaging_abd_cart_cron_interval'
            ),
             'telinfy_abd_cart_time' => array(
                'name'      => __( 'Abandoned cart time', 'woocommerce-settings-telinfy-integration' ),
                'type'      => 'number',
                'desc'      => __( 'Select the time to mark a cart as abandoned for logged in users. Please add time in <b>hours</b>'),
                'id'        => 'wc_settings_telinfy_messaging_abd_cart_time'
            ),
             'telinfy_abd_cart_send_time' => array(
                'name'      => __( 'Abandoned cart send message time', 'woocommerce-settings-telinfy-integration' ),
                'type'      => 'text',
                'desc'      => __( 'The time to send the the messages to customer after the cart is marked as abandoned. Enter the time in <b>hours</b> separated by comma '),
                'id'        => 'wc_settings_telinfy_messaging_abd_cart_send_time'
            ),
             'telinfy_cron_schedule' => array(
                'name'      => __( 'Abandoned cart remove cron interval', 'woocommerce-settings-telinfy-integration' ),
                'type'      => 'number',
                'desc'      => __( 'Time interval to run the cron to remove the abandoned cart record. Please add time in <b> days</b>',),
                'id'        => 'wc_settings_telinfy_messaging_abd_cart_remove_interval'
            ),
             'telinfy_abd_cart_remove_time' => array(
                'name'      => __( 'Delete abandoned records', 'woocommerce-settings-telinfy-integration' ),
                'type'      => 'number',
                'desc'      => __( 'Abandoned cart records will be automatically deleted after the above mentioned time. Please add time in <b>days</b>',),
                'id'        => 'wc_settings_telinfy_messaging_abd_cart_remove_time'
            ),
             'telinfy_section_end_cron' => array(
                 'type'     => 'sectionend',
                 'id'       => 'wc_settings_telinfy_messaging_section_end_cron'
            )
        );
    

        // Merge all the configuration arrays

        $settings = array_merge($settings_whatsapp,$settings_sms,$settings_cron);

        return apply_filters( 'wc_settings_telinfy_messaging_settings', $settings );
    }

    /**
     * Add a new checkbox in the admin order detail page to control customer notify when changing status
     *
     * @return void
     */

    public function add_custom_checkbox_to_order_admin( $order ) {
        // Get the value of the customer notify checkbox field for the order
        $customer_notify_checkbox_value = get_post_meta( $order->get_id(), 'customer_notify_checkbox_field', true );

        // Output the custom checkbox field HTML
        echo '<p class="form-field form-field-wide"><strong>' . __( 'Notify Customer') . '</strong> ';
        echo '<input type="checkbox" name="customer_notify_checkbox_field" value="1" ' . checked( $customer_notify_checkbox_value, '1', false ) . ' style="width:unset"/>';
        echo 'Notify using Telinfy messaging services';
        echo '</p>';
    }

    /**
    * Sanitize post data
    *
    * @return array
    */

    public function tm_sanitize_post_data() {

        $input_post_values = array(
            'username'=> array(
                'default'  => '',
                'sanitize' => FILTER_SANITIZE_STRING,
            ),
            'password'=> array(
                'default'  => '',
                'sanitize' => FILTER_SANITIZE_STRING,
            ),
            'type'=> array(
                'default'  => '',
                'sanitize' => FILTER_SANITIZE_STRING,
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