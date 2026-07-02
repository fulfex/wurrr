( function( $ ) {
	'use strict';

	/**
	 * Currency switcher change handler.
	 */
	$( document ).on( 'change', '.wp-exchange-currency-select', function() {
		var currency = $( this ).val();

		$.post( wp_exchange.ajax_url, {
			action:   'wp_exchange_set_currency',
			currency: currency,
			nonce:    wp_exchange.nonce
		}, function( response ) {
			if ( response.success ) {
				window.location.reload();
			}
		} );
	} );

	/**
	 * Convert prices via AJAX (used for dynamic cart updates).
	 *
	 * @param {number} amount The price amount.
	 * @param {string} from   Source currency code.
	 * @param {string} to     Target currency code.
	 * @param {Function} callback Callback with converted price.
	 */
	window.wp_exchange_convert = function( amount, from, to, callback ) {
		$.post( wp_exchange.ajax_url, {
			action: 'wp_exchange_convert',
			amount: amount,
			from:   from,
			to:     to,
			nonce:  wp_exchange.nonce
		}, function( response ) {
			if ( response.success && callback ) {
				callback( response.data );
			}
		} );
	};

} )( jQuery );
