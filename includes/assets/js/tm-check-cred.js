( function ( $ ) {
	let timer;

	const TM_cred_check = {
		init() {

			$( window ).on(
				'load',
				this._initial_page_load
			);
			$( document ).on(
				'click',
				'#wc_settings_telinfy_messaging_whatsapp_cred_check',
				this._handle_click
			);
			$(document).on(
				'focusout',
				'#wc_settings_telinfy_messaging_api_key_sms,#wc_settings_telinfy_messaging_api_secret_sms',
				this._toggleTargetField
			);
			
		},
		_initial_page_load(){
			const whatsapp_username = jQuery( '#wc_settings_telinfy_messaging_api_key_whatsapp' ).val();
			const whatsapp_password = jQuery( '#wc_settings_telinfy_messaging_api_secret_whatsapp' ).val();

	    const sms_username = jQuery("#wc_settings_telinfy_messaging_api_key_sms").val();
	    const sms_password = jQuery("#wc_settings_telinfy_messaging_api_secret_sms").val();


	    if(whatsapp_username && whatsapp_password){
	    	TM_cred_check._send_ajax_request(whatsapp_username,whatsapp_password,"whatsapp-config");
	    }else{
	    	const targetFields = $(".whatsapp-config");
	    	const msgBox = "#whatsapp-config-error";
	    	TM_cred_check._disable_fields(targetFields,msgBox);
	    }

    	const smsTargetFields = $(".sms-config");
    	const smsTargetFieldsR = $(".sms-readonly");
	    if(sms_username && sms_password){

	    	smsTargetFields.prop("disabled", false).attr("title", "");
	    	smsTargetFieldsR.prop("readonly", false).attr("title", "");
	    }else{
	    	TM_cred_check._disable_fields(smsTargetFields);
	    	TM_cred_check._readonly_fields(smsTargetFieldsR);
	    }		    

		},
		_toggleTargetField(event){
			const feild_id = event.target.id;
			const targetFields ="";
			if(feild_id == "wc_settings_telinfy_messaging_api_key_sms" || feild_id == "wc_settings_telinfy_messaging_api_secret_sms"){
				const smsTargetFields = $(".sms-config");
				const smsTargetFieldsR = $(".sms-readonly");
				username = jQuery("#wc_settings_telinfy_messaging_api_key_sms").val();
				password = jQuery("#wc_settings_telinfy_messaging_api_secret_sms").val();
				if(username && password){
					smsTargetFields.prop("disabled", false).attr("title", "");
					smsTargetFieldsR.prop("readonly", false).attr("title", "");
				}else{
					TM_cred_check._disable_fields(smsTargetFields);
					TM_cred_check._readonly_fields(smsTargetFieldsR);
				}
				
			}
		    	
		},
		_handle_click(event) {

			event.preventDefault();
				const whatsapp_api = jQuery( '#wc_settings_telinfy_messaging_api_base_url_whatsapp' ).val();
				const whatsapp_username = jQuery( '#wc_settings_telinfy_messaging_api_key_whatsapp' ).val();
				const whatsapp_password = jQuery( '#wc_settings_telinfy_messaging_api_secret_whatsapp' ).val();

		    const sms_username = jQuery("#wc_settings_telinfy_messaging_api_key_sms").val();
		    const sms_password = jQuery("#wc_settings_telinfy_messaging_api_secret_sms").val();


			const buttonId = event.target.id;
            switch (buttonId) {
              case 'wc_settings_telinfy_messaging_whatsapp_cred_check':
              	const msgBox = $("#whatsapp-config-error");
              	const targetFields = $(".whatsapp-config");
              	if ( whatsapp_username == "" || whatsapp_password =="" || whatsapp_api =="") {
				              		TM_cred_check._disable_fields(targetFields,msgBox);
									return;
								}
                // Perform action for WhatsApp credentials check
                $(msgBox).css("color","black");
                $(msgBox).text("Validating...");                
                TM_cred_check._send_ajax_request(whatsapp_username,whatsapp_password,whatsapp_api,"whatsapp-config",1);

                break;

              default:
                // Handle other cases, if needed
                break;
            }

		},
		_send_ajax_request(username,password,apiEndpoint,type,click = 0){

        const data = {
					action: 'tm_check_cred',
					username: username,
	        password: password,
	        apiEndpoint: apiEndpoint,
	        type:type,
					security: tm_cred_vars._nonce,
					tm_post_id: tm_cred_vars._post_id,
				};

        jQuery.post(
				tm_cred_vars.ajaxurl,
				data, //Ajaxurl coming from localized script and contains the link to wp-admin/admin-ajax.php file that handles AJAX requests on Wordpress
				function (response) {
					 result = jQuery.parseJSON(response);
					 const targetFields = $("."+ type);
					 const msgBox = $("#"+type+"-error");
					 if(result['status'] == "success"){
					 	$(msgBox).css("color","green");
					 	$(msgBox).text("Success");
					 	if(click){
					 		TM_cred_check._render_templates(type,msgBox);
					 	}
					 	
					 }else{
					 	TM_cred_check._disable_fields(targetFields,msgBox);

					 }
				}
			).fail(function(xhr, status, error) {
			  console.error('AJAX Error:', error);
			  // Error response handling
			});

        },
        _disable_fields(targetFields,msgBox){

        	
        	targetFields.prop("disabled", true).attr("title", "Please fill username and password");
        	$(msgBox).css("color","red");
        	$(msgBox).text("Please check the credentials");
        },
        _readonly_fields(targetFields){
        	
        	targetFields.prop("readonly", true).attr("title", "Please fill username and password");
        },
        _render_templates(type,msgBox){

        	const targetFields = $("."+ type);
        	const data = {
						action: 'tm_list_templates',
		        type:type,
						security: tm_cred_vars._nonce,
						tm_post_id: tm_cred_vars._post_id,
					};
					$(msgBox).css("color","black");
          $(msgBox).text("Loading templates...");
					jQuery.post(
					tm_cred_vars.ajaxurl,
					data, //Ajaxurl coming from localized script and contains the link to wp-admin/admin-ajax.php file that handles AJAX requests on Wordpress
					function (response) {
						 	result = jQuery.parseJSON(response);
						 	
						 	if(result["status"]== "success"){
						 		var options = result["data"];
						 		var selected = result["selected"];

							 	var targetElements = ["wc_settings_telinfy_messaging_whatsapp_template_name_order_confirmation",
							 												"wc_settings_telinfy_messaging_whatsapp_template_name_order_cancellation",
							 												"wc_settings_telinfy_messaging_whatsapp_template_name_order_refund",
							 												"wc_settings_telinfy_messaging_whatsapp_template_name_order_notes",
							 												"wc_settings_telinfy_messaging_whatsapp_template_name_order_shipment",
							 												"wc_settings_telinfy_messaging_whatsapp_template_name_other_order_status",
							 												"wc_settings_telinfy_messaging_whatsapp_template_name_abandoned_cart"];

							 	jQuery.each(targetElements,function(key,elementId){

							 		var selectElement = jQuery("#"+elementId);
							    jQuery.each(options, function(value, label) {
							    		var option = jQuery('<option></option>').attr('value', value).text(label);

							        // Check if this is the option you want to mark as selected

							        if (value == selected[elementId]) {
							        	// console.log(elementId);
							            option.prop('selected', true); // Mark the option as selected
							        }

							        selectElement.append(option);
							    });

							 	});
							 	$(msgBox).css("color","green");
         	 			$(msgBox).text("Template loaded");
							 	targetFields.prop("disabled", false).attr("title", "");
						 	}else{
						 		$(msgBox).css("color","red");
						 		$(msgBox).text("failed to load templates");
						 	}
							
					}
				).fail(function(xhr, status, error) {
				  console.error('AJAX Error:', error);
				  // Error response handling
				});

        }
	};

	TM_cred_check.init();
} )( jQuery );
