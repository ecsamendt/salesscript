/**
 * Renders the objection buttons from the JSON embedded in
 * #ssb-objections[data-objections]. All state (which objection is active,
 * which have been discussed) lives in memory for this page load only --
 * it intentionally does not persist, since it should reset for each new call.
 *
 * Exposed as window.SSBObjectionButtons.init() so the SPA (assets/js/app.js)
 * can re-run it after AJAX-loading new script content into the Call Script
 * tab -- see discovery.js for the same pattern and the reasoning.
 */
( function () {
	'use strict';

	function init() {
		const section = document.getElementById( 'ssb-objections' );
		if ( ! section ) {
			return;
		}

		let objections = [];
		try {
			objections = JSON.parse( section.getAttribute( 'data-objections' ) || '[]' );
		} catch ( e ) {
			return;
		}
		if ( ! objections.length ) {
			return;
		}

		const buttonsWrap = section.querySelector( '.ssb-objection-buttons' );
		const discussedNote = section.querySelector( '.ssb-discussed-note' );
		const panel = section.querySelector( '.ssb-objection-panel' );

		let activeIndex = null;
		const discussed = new Set();

		function labelFor( o ) {
			return o.type_label || ( o.objection ? o.objection.slice( 0, 28 ) : 'Objection' );
		}

		function renderButtons() {
			buttonsWrap.innerHTML = '';
			objections.forEach( function ( o, i ) {
				if ( discussed.has( i ) ) {
					return;
				}
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'ssb-objection-btn';
				btn.textContent = labelFor( o );
				if ( i === activeIndex ) {
					btn.classList.add( 'is-active' );
				}
				btn.addEventListener( 'click', function () {
					activeIndex = i;
					discussed.add( i );
					renderButtons();
					renderPanel();
				} );
				buttonsWrap.appendChild( btn );
			} );

			if ( discussed.size ) {
				const names = Array.from( discussed ).map( function ( i ) {
					return labelFor( objections[ i ] );
				} );
				discussedNote.textContent = 'Already discussed: ' + names.join( ', ' );
			} else {
				discussedNote.textContent = '';
			}
		}

		function scrollToSection( id, fallbackMessage ) {
			const target = document.getElementById( id );
			if ( target ) {
				target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} else if ( fallbackMessage ) {
				alert( fallbackMessage );
			}
		}

		function renderOutcomes( wrap ) {
			wrap.innerHTML = '';
			const outcomes = [
				{ key: 'hesitant', label: 'Still hesitant \u2192 follow-up' },
				{ key: 'compare', label: 'Compare vs competitors' },
				{ key: 'close', label: 'Close sale' },
				{ key: 'upsell', label: 'Discuss upsell' }
			];
			outcomes.forEach( function ( outcome ) {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'ssb-outcome-btn';
				btn.textContent = outcome.label;
				btn.addEventListener( 'click', function () {
					if ( 'hesitant' === outcome.key ) {
						wrap.insertAdjacentHTML( 'afterend', '<p class="ssb-outcome-note">Follow-up noted \u2014 wrap up warmly.</p>' );
					} else if ( 'compare' === outcome.key ) {
						scrollToSection( 'ssb-compare', 'No comparison table set up for this product yet.' );
					} else if ( 'close' === outcome.key ) {
						wrap.insertAdjacentHTML( 'afterend', '<p class="ssb-outcome-note">Great \u2014 move to close.</p>' );
					} else if ( 'upsell' === outcome.key ) {
						scrollToSection( 'ssb-upsell', 'No upsell path set up for this product/call type.' );
					}
				} );
				wrap.appendChild( btn );
			} );
		}

		function renderPanel() {
			if ( null === activeIndex ) {
				panel.hidden = true;
				panel.innerHTML = '';
				return;
			}

			const o = objections[ activeIndex ];
			panel.hidden = false;
			panel.innerHTML = '';

			const main = document.createElement( 'div' );
			main.className = 'ssb-objection-main';

			const scriptLabel = document.createElement( 'p' );
			scriptLabel.className = 'ssb-objection-label';
			scriptLabel.textContent = 'Say this';
			main.appendChild( scriptLabel );

			const scriptText = document.createElement( 'p' );
			scriptText.className = 'ssb-objection-script';
			scriptText.textContent = o.response || '';
			main.appendChild( scriptText );

			if ( o.counter_script ) {
				const counterBtn = document.createElement( 'button' );
				counterBtn.type = 'button';
				counterBtn.className = 'ssb-try-counter-btn';
				counterBtn.textContent = 'Try to counter';
				main.appendChild( counterBtn );

				const counterBlock = document.createElement( 'div' );
				counterBlock.className = 'ssb-counter-block';
				counterBlock.hidden = true;

				const counterText = document.createElement( 'p' );
				counterText.className = 'ssb-objection-script';
				counterText.textContent = o.counter_script;
				counterBlock.appendChild( counterText );

				const outcomesWrap = document.createElement( 'div' );
				outcomesWrap.className = 'ssb-counter-outcomes';
				renderOutcomes( outcomesWrap );
				counterBlock.appendChild( outcomesWrap );

				main.appendChild( counterBlock );

				counterBtn.addEventListener( 'click', function () {
					counterBlock.hidden = false;
					counterBtn.hidden = true;
				} );
			}

			const sidebar = document.createElement( 'div' );
			sidebar.className = 'ssb-objection-sidebar';

			const sidebarLabel = document.createElement( 'p' );
			sidebarLabel.className = 'ssb-objection-label';
			sidebarLabel.textContent = 'Key points (recap, in case you go off script)';
			sidebar.appendChild( sidebarLabel );

			const ul = document.createElement( 'ul' );
			( o.key_points || [] ).forEach( function ( point ) {
				const li = document.createElement( 'li' );
				li.textContent = point;
				ul.appendChild( li );
			} );
			sidebar.appendChild( ul );

			panel.appendChild( main );
			panel.appendChild( sidebar );
		}

		renderButtons();
	}

	window.SSBObjectionButtons = { init: init };
	document.addEventListener( 'DOMContentLoaded', init );
} )();
