/**
 * Orchestrates the [ssb_app] single-page interface:
 *   - Tab switching (show/hide panels, no navigation)
 *   - Call Script tab: AJAX-loads script output on product/call-type
 *     selection, always resets when the tab is (re-)activated
 *   - Add Products / Add Competitors / Specials tabs: AJAX-driven
 *     list <-> form, with saves/deletes updating in place. State is
 *     "remembered" for free -- these panels are never unmounted, just
 *     hidden, so nothing needs to be restored on tab switch.
 *
 * Relies on window.ssbApp (ajaxUrl, nonce) localized in class-app.php, and
 * calls window.SSBDiscovery.init() / window.SSBObjectionButtons.init()
 * after injecting new Call Script content, since those scripts only
 * self-run once on DOMContentLoaded and the SPA loads that content later.
 */
( function () {
	'use strict';

	const MANAGE_CONFIG = {
		products: {
			idField: 'product_id',
			loadForm: 'ssb_load_product_form',
			loadList: 'ssb_load_product_list',
			deleteAction: 'ssb_delete_product',
			deleteConfirm: 'Delete this product? This can be undone from wp-admin.'
		},
		competitors: {
			idField: 'competitor_id',
			loadForm: 'ssb_load_competitor_form',
			loadList: 'ssb_load_competitor_list',
			deleteAction: 'ssb_delete_competitor',
			deleteConfirm: 'Delete this competitor? This can be undone from wp-admin.'
		},
		specials: {
			idField: 'special_id',
			loadForm: 'ssb_load_special_form',
			loadList: 'ssb_load_special_list',
			deleteAction: 'ssb_delete_special',
			deleteConfirm: 'Delete this special? This can be undone from wp-admin.'
		}
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		const app = document.getElementById( 'ssb-app' );
		if ( ! app || typeof ssbApp === 'undefined' ) {
			return;
		}

		initTabs( app );
		initManageTabs( app );
		initCallScript( app );
	} );

	/* ---------------------------------------------------------------
	 * TAB SWITCHING
	 * ------------------------------------------------------------- */

	function initTabs( app ) {
		const tabs = app.querySelectorAll( '.ssb-app-tab' );

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				const target = tab.getAttribute( 'data-tab' );

				tabs.forEach( function ( t ) {
					t.classList.toggle( 'is-active', t === tab );
				} );

				app.querySelectorAll( '.ssb-app-panel' ).forEach( function ( panel ) {
					panel.classList.toggle( 'is-active', panel.getAttribute( 'data-panel' ) === target );
				} );

				// Call Script always resets when (re-)activated -- this is the
				// one tab that does NOT remember where the rep was.
				if ( 'call-script' === target ) {
					resetCallScript( app );
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------
	 * CALL SCRIPT TAB
	 * ------------------------------------------------------------- */

	function resetCallScript( app ) {
		const picker = app.querySelector( '#ssb-app-picker' );
		const output = app.querySelector( '#ssb-app-script-output' );
		if ( picker ) {
			picker.reset();
		}
		if ( output ) {
			output.innerHTML = '';
		}
	}

	function initCallScript( app ) {
		const picker = app.querySelector( '#ssb-app-picker' );
		const output = app.querySelector( '#ssb-app-script-output' );
		if ( ! picker || ! output ) {
			return;
		}

		picker.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			const productId = picker.querySelector( '[name="product_id"]' ).value;
			const callType  = picker.querySelector( '[name="call_type"]' ).value;

			if ( ! productId || ! callType ) {
				return;
			}

			const formData = new FormData();
			formData.append( 'action', 'ssb_load_script_output' );
			formData.append( 'ssb_nonce', ssbApp.nonce );
			formData.append( 'product_id', productId );
			formData.append( 'call_type', callType );

			fetch( ssbApp.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( ! result.success ) {
						return;
					}
					output.innerHTML = result.data.html;

					// Re-run the scripts that only self-initialize once on
					// DOMContentLoaded, since this content just arrived after that.
					if ( window.SSBDiscovery ) {
						window.SSBDiscovery.init();
					}
					if ( window.SSBObjectionButtons ) {
						window.SSBObjectionButtons.init();
					}
				} )
				.catch( function () {
					output.innerHTML = '<p class="ssb-error">Something went wrong loading this script. Please try again.</p>';
				} );
		} );
	}

	/* ---------------------------------------------------------------
	 * MANAGE TABS (Add Products / Add Competitors / Specials)
	 * ------------------------------------------------------------- */

	function initManageTabs( app ) {
		Object.keys( MANAGE_CONFIG ).forEach( function ( type ) {
			const panel = app.querySelector( '[data-manage="' + type + '"]' );
			if ( panel ) {
				initManagePanel( panel, MANAGE_CONFIG[ type ] );
			}
		} );
	}

	function extractListBody( html ) {
		const temp = document.createElement( 'div' );
		temp.innerHTML = html;
		const inner = temp.querySelector( '.ssb-manage-list-body' );
		return inner ? inner.innerHTML : html;
	}

	function initManagePanel( panel, config ) {
		const listBody = panel.querySelector( '.ssb-manage-list-body' );
		const formBody = panel.querySelector( '.ssb-manage-form-body' );

		function showList() {
			listBody.hidden = false;
			formBody.hidden = true;
			formBody.innerHTML = '';
		}

		function showForm( html ) {
			formBody.innerHTML = html;
			listBody.hidden = true;
			formBody.hidden = false;
		}

		function loadForm( id ) {
			const formData = new FormData();
			formData.append( 'action', config.loadForm );
			formData.append( 'ssb_nonce', ssbApp.nonce );
			formData.append( config.idField, id );

			fetch( ssbApp.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						showForm( result.data.html );
					}
				} );
		}

		// Delegated click handling -- list items and buttons get replaced via
		// innerHTML on every save/delete, so individual listeners would be
		// lost; delegation on the stable panel element keeps working.
		panel.addEventListener( 'click', function ( event ) {
			const addBtn = event.target.closest( '[data-manage-action="new"]' );
			if ( addBtn ) {
				loadForm( 0 );
				return;
			}

			const editBtn = event.target.closest( '[data-manage-action="edit"]' );
			if ( editBtn ) {
				loadForm( editBtn.getAttribute( 'data-id' ) );
				return;
			}

			const deleteBtn = event.target.closest( '[data-manage-action="delete"]' );
			if ( deleteBtn ) {
				if ( ! window.confirm( config.deleteConfirm ) ) {
					return;
				}
				const formData = new FormData();
				formData.append( 'action', config.deleteAction );
				formData.append( 'ssb_nonce', ssbApp.nonce );
				formData.append( config.idField, deleteBtn.getAttribute( 'data-id' ) );

				fetch( ssbApp.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
					.then( function ( response ) { return response.json(); } )
					.then( function ( result ) {
						if ( result.success ) {
							listBody.innerHTML = extractListBody( result.data.list_html );
						}
					} );
				return;
			}

			const backBtn = event.target.closest( '.ssb-back-to-list-btn' );
			if ( backBtn ) {
				showList();
			}
		} );

		panel.addEventListener( 'submit', function ( event ) {
			const form = event.target.closest( '.ssb-ajax-form' );
			if ( ! form ) {
				return;
			}
			event.preventDefault();

			const action = form.getAttribute( 'data-ajax-action' );
			const formData = new FormData( form );
			formData.set( 'action', action );

			fetch( ssbApp.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( result.success ) {
						formBody.innerHTML = result.data.form_html;
						listBody.innerHTML = extractListBody( result.data.list_html );

						const notice = formBody.querySelector( '.ssb-form-notice' );
						if ( notice ) {
							notice.hidden = false;
							notice.classList.remove( 'ssb-form-notice-error' );
							notice.classList.add( 'ssb-form-notice-success' );
							notice.textContent = 'Saved.';
						}
					} else {
						const container = form.closest( '.ssb-manage-form' ) || panel;
						const notice = container.querySelector( '.ssb-form-notice' );
						if ( notice && result.data && result.data.message ) {
							notice.hidden = false;
							notice.classList.remove( 'ssb-form-notice-success' );
							notice.classList.add( 'ssb-form-notice-error' );
							notice.textContent = result.data.message;
						}
					}
				} );
		} );
	}
} )();
