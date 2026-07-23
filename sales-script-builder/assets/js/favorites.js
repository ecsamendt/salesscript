/**
 * Handles the favorite/bookmark star toggle on script-view pages.
 * Expects markup like:
 * <button class="ssb-favorite-btn" data-product-id="42" data-call-type="upsell" data-favorited="0">☆</button>
 *
 * Uses event delegation on document rather than binding to each button
 * individually -- favorite buttons in the SPA (assets/js/app.js) can appear
 * well after DOMContentLoaded, once the Call Script tab AJAX-loads a
 * script. Delegation means this keeps working with no re-init call needed.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		const btn = event.target.closest( '.ssb-favorite-btn' );
		if ( btn ) {
			toggleFavorite( btn );
		}
	} );

	function toggleFavorite( btn ) {
		const productId = btn.getAttribute( 'data-product-id' );
		const callType   = btn.getAttribute( 'data-call-type' );

		btn.disabled = true;

		const formData = new FormData();
		formData.append( 'action', 'ssb_toggle_favorite' );
		formData.append( 'nonce', ssbFavorites.nonce );
		formData.append( 'product_id', productId );
		formData.append( 'call_type', callType );

		fetch( ssbFavorites.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result.success ) {
					const isFavorited = result.data.favorited;

					// On the "My Scripts" dashboard, unfavoriting should remove
					// the whole row rather than just toggle the star.
					if ( ! isFavorited && 'true' === btn.getAttribute( 'data-remove-on-toggle' ) ) {
						const item = btn.closest( '.ssb-favorite-item' );
						if ( item ) {
							item.remove();
						}
						return;
					}

					btn.setAttribute( 'data-favorited', isFavorited ? '1' : '0' );
					btn.textContent = isFavorited ? '★' : '☆';
					btn.classList.toggle( 'is-favorited', isFavorited );

					// Let GA4 tracking know a favorite happened (see ga4-events.js).
					if ( isFavorited && typeof gtag === 'function' ) {
						gtag( 'event', 'favorite_script', {
							product_id: productId,
							call_type: callType,
						} );
					}
				}
			} )
			.catch( function () {
				// Fail quietly on the UI; favoriting isn't critical-path.
			} )
			.finally( function () {
				btn.disabled = false;
			} );
	}
} )();

