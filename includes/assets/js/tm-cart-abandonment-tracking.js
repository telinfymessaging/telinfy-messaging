( function ( $ ) {
	let timer;
	const TM_cart_abandonment = {
		init() {

			$( document ).on(
				'focusout',
				'#billing_phone',
				this._getCheckoutData
			);

			$( document.body ).on( 'updated_checkout', function () {
				TM_cart_abandonment._getCheckoutData();
			} );

			$( function () {
				setTimeout( function () {
					TM_cart_abandonment._getCheckoutData();
				}, 800 );
			} );
		},

		_validate_phone_number( value ) {
			// var re = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/im;
			var re = /^[\+]?\d+$/;
			return re.test(value);
		},

		_getCheckoutData() {
			
			const tm_email = jQuery( '#billing_email' ).val();

			if ( typeof tm_email === 'undefined' ) {
				return;
			}

			let tm_phone = jQuery( '#billing_phone' ).val();
			
			if ( typeof tm_phone === 'undefined' || tm_phone === null ) {
				//If phone number field does not exist on the Checkout form
				tm_phone = '';
			}

			clearTimeout( timer );

			if (
				tm_phone.length >= 1
			) {
				//Checking if the email field is valid or phone number is longer than 1 digit
				//If Email or Phone valid
				tm_phone = jQuery( '#billing_phone' ).val();

				const data = {
					action: 'tm_update_cart_abandonment_data',
					tm_email,
					tm_phone,
					security: tm_ca_vars._nonce,
					tm_post_id: tm_ca_vars._post_id,
				};

				timer = setTimeout( function () {
					if (
						TM_cart_abandonment._validate_phone_number( data.tm_phone )
					) {
						jQuery.post(
							tm_ca_vars.ajaxurl,
							data, //Ajaxurl coming from localized script and contains the link to wp-admin/admin-ajax.php file that handles AJAX requests on Wordpress
							function () {
								// success response
							}
						);
					}
				}, 500 );
			} else {
				//console.log("Not a valid e-mail or phone address");
			}
		},
	};

	TM_cart_abandonment.init();
} )( jQuery );
