/**
 * Raises friction against casual copy/paste of script content. This is a
 * deterrent, not real protection -- screenshots and browser dev tools
 * always remain an option for anyone determined to get the text out.
 * Combine with server-side gating (SSB_Access_Control) as the real control.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const protectedEls = document.querySelectorAll( '[data-copy-protect="true"]' );

		protectedEls.forEach( function ( el ) {
			el.addEventListener( 'contextmenu', function ( e ) {
				e.preventDefault();
			} );

			el.addEventListener( 'copy', function ( e ) {
				e.preventDefault();
				showNotice( el );
			} );

			el.addEventListener( 'keydown', function ( e ) {
				const isCopyShortcut = ( e.ctrlKey || e.metaKey ) && 'c' === e.key.toLowerCase();
				if ( isCopyShortcut ) {
					e.preventDefault();
					showNotice( el );
				}
			} );
		} );
	} );

	function showNotice( el ) {
		let notice = el.querySelector( '.ssb-copy-notice' );
		if ( ! notice ) {
			notice = document.createElement( 'div' );
			notice.className = 'ssb-copy-notice';
			notice.textContent = 'Copying script content is disabled for this membership.';
			el.prepend( notice );
		}
		notice.style.display = 'block';
		clearTimeout( notice._hideTimer );
		notice._hideTimer = setTimeout( function () {
			notice.style.display = 'none';
		}, 2500 );
	}
} )();
