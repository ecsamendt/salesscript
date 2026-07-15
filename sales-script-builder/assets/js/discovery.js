/**
 * Handles the discovery-question buttons at the top of the script view.
 * Outbound (cold) calls get a competitor picker pulling from the Competitors
 * library; inbound calls get a three-way branch with guidance text. All of
 * this is presentation only -- nothing here is saved.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const section = document.querySelector( '.ssb-discovery' );
		if ( ! section ) {
			return;
		}

		const buttons = section.querySelectorAll( '.ssb-discovery-btn' );
		const resultWrap = section.querySelector( '.ssb-discovery-result' );
		if ( ! buttons.length || ! resultWrap ) {
			return;
		}

		let competitors = [];
		if ( section.hasAttribute( 'data-competitors' ) ) {
			try {
				competitors = JSON.parse( section.getAttribute( 'data-competitors' ) || '[]' );
			} catch ( e ) {
				competitors = [];
			}
		}

		function renderCompetitorInfo( competitor ) {
			const wrap = document.createElement( 'div' );
			wrap.className = 'ssb-competitor-info';

			function buildList( title, items ) {
				if ( ! items.length ) {
					return '';
				}
				const lis = items.map( function ( item ) {
					return '<li>' + item.replace( /</g, '&lt;' ) + '</li>';
				} ).join( '' );
				return '<p class="ssb-objection-label">' + title + '</p><ul>' + lis + '</ul>';
			}

			wrap.innerHTML =
				buildList( 'Their pros', competitor.pros || [] ) +
				buildList( 'Their cons', competitor.cons || [] ) +
				buildList( 'Counter talking points', competitor.counters || [] );

			return wrap;
		}

		function handleUsingCompetitor() {
			resultWrap.innerHTML = '';

			if ( ! competitors.length ) {
				resultWrap.innerHTML = '<p class="ssb-outcome-note">No competitors in the library yet.</p>';
				return;
			}

			const select = document.createElement( 'select' );
			const placeholder = document.createElement( 'option' );
			placeholder.value = '';
			placeholder.textContent = 'Which competitor?';
			select.appendChild( placeholder );

			competitors.forEach( function ( c, i ) {
				const opt = document.createElement( 'option' );
				opt.value = String( i );
				opt.textContent = c.name;
				select.appendChild( opt );
			} );

			const infoHolder = document.createElement( 'div' );

			select.addEventListener( 'change', function () {
				infoHolder.innerHTML = '';
				if ( '' === select.value ) {
					return;
				}
				infoHolder.appendChild( renderCompetitorInfo( competitors[ Number( select.value ) ] ) );
			} );

			resultWrap.appendChild( select );
			resultWrap.appendChild( infoHolder );
		}

		function handleNotUsing() {
			resultWrap.innerHTML = '<p class="ssb-outcome-note">Ask why not \u2014 uncover the real reason, then continue to pain points below.</p>';
		}

		function handleLookingNew() {
			resultWrap.innerHTML = '<p class="ssb-outcome-note">Ask why they\u2019re looking \u2014 tie the answer to a pain point, then continue to pain points below.</p>';
		}

		function handleShopping() {
			resultWrap.innerHTML = '<p class="ssb-outcome-note">Lead with comparison.</p>';
			const compare = document.getElementById( 'ssb-compare' );
			if ( compare ) {
				compare.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		}

		function handleReady() {
			resultWrap.innerHTML = '<p class="ssb-outcome-note">Skip to close \u2014 confirm details and move straight to pricing/close.</p>';
		}

		const handlers = {
			'using-competitor': handleUsingCompetitor,
			'not-using': handleNotUsing,
			'looking-new': handleLookingNew,
			shopping: handleShopping,
			ready: handleReady
		};

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				buttons.forEach( function ( b ) {
					b.classList.toggle( 'is-active', b === btn );
				} );
				const action = btn.getAttribute( 'data-action' );
				if ( handlers[ action ] ) {
					handlers[ action ]();
				}
			} );
		} );
	} );
} )();
