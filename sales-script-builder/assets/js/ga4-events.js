/**
 * Fires anonymous GA4 events for script-builder interactions.
 *
 * IMPORTANT: Per product decision, this stays fully anonymous/aggregate.
 * Never add user_id, email, or username to any event below -- only
 * product_id, category, and call_type. Assumes gtag.js is already loaded
 * elsewhere on the site.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( typeof gtag !== 'function' ) {
			return; // GA4 not loaded on this page; fail silently.
		}

		bindSelectEvent( '.ssb-product-select', 'select_product', 'product_id' );
		bindSelectEvent( '.ssb-call-type-select', 'select_call_type', 'call_type' );

		document.querySelectorAll( '.ssb-special-banner' ).forEach( function ( banner ) {
			gtag( 'event', 'view_special', {
				product_id: banner.getAttribute( 'data-product-id' ),
				special_id: banner.getAttribute( 'data-special-id' ),
			} );
		} );
	} );

	function bindSelectEvent( selector, eventName, paramName ) {
		const el = document.querySelector( selector );
		if ( ! el ) {
			return;
		}
		el.addEventListener( 'change', function () {
			const params = {};
			params[ paramName ] = el.value;
			gtag( 'event', eventName, params );
		} );
	}
} )();
