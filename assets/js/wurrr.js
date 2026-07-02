( function( $ ) {
	'use strict';

	$( document ).on( 'change', '.wurrr-currency-select', function() {
		var currency = $( this ).val();

		$.post( wurrr.ajax_url, {
			action:   'wp_exchange_set_currency',
			currency: currency,
			nonce:    wurrr.nonce
		}, function( response ) {
			if ( response.success ) {
				window.location.reload();
			}
		} );
	} );

	window.wurrr_convert = function( amount, from, to, callback ) {
		$.post( wurrr.ajax_url, {
			action: 'wp_exchange_convert',
			amount: amount,
			from:   from,
			to:     to,
			nonce:  wurrr.nonce
		}, function( response ) {
			if ( response.success && callback ) {
				callback( response.data );
			}
		} );
	};

} )( jQuery );
