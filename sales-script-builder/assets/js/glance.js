/**
 * Handles the "Competitors At A Glance" tab: grid, search filter, and the
 * flashcard flip with next/prev navigation. All data was embedded
 * server-side as JSON on initial render (see templates/app-glance.php) --
 * this is entirely client-side, no AJAX round-trips.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const root = document.querySelector( '.ssb-glance' );
		if ( ! root ) {
			return;
		}

		let competitors = [];
		try {
			competitors = JSON.parse( root.getAttribute( 'data-competitors' ) || '[]' );
		} catch ( e ) {
			return;
		}
		if ( ! competitors.length ) {
			return;
		}

		const deck        = root.querySelector( '.ssb-glance-deck' );
		const grid         = root.querySelector( '.ssb-glance-grid' );
		const searchInput  = root.querySelector( '.ssb-glance-search' );
		const flashcard    = root.querySelector( '.ssb-glance-flashcard' );
		const backBtn      = root.querySelector( '.ssb-glance-back-btn' );
		const prevBtn      = root.querySelector( '.ssb-glance-prev-btn' );
		const nextBtn      = root.querySelector( '.ssb-glance-next-btn' );
		const cardName     = root.querySelector( '.ssb-glance-card-name' );
		const prosList     = root.querySelector( '.ssb-glance-pros' );
		const consList     = root.querySelector( '.ssb-glance-cons' );
		const countersList = root.querySelector( '.ssb-glance-counters' );
		const productsList = root.querySelector( '.ssb-glance-products' );

		let activeIndex = 0;

		function renderGrid( filterText ) {
			grid.innerHTML = '';
			const term = ( filterText || '' ).toLowerCase();

			competitors.forEach( function ( competitor, index ) {
				if ( term && competitor.name.toLowerCase().indexOf( term ) === -1 ) {
					return;
				}
				const card = document.createElement( 'button' );
				card.type = 'button';
				card.className = 'ssb-glance-grid-card';
				card.textContent = competitor.name;
				card.addEventListener( 'click', function () {
					openFlashcard( index );
				} );
				grid.appendChild( card );
			} );

			if ( ! grid.children.length ) {
				const empty = document.createElement( 'p' );
				empty.className = 'ssb-empty-state';
				empty.textContent = 'No competitors match that search.';
				grid.appendChild( empty );
			}
		}

		function fillList( ul, items ) {
			ul.innerHTML = '';
			( items || [] ).forEach( function ( item ) {
				const li = document.createElement( 'li' );
				li.textContent = item;
				ul.appendChild( li );
			} );
			if ( ! items || ! items.length ) {
				const li = document.createElement( 'li' );
				li.textContent = '\u2014';
				ul.appendChild( li );
			}
		}

		function renderFlashcard() {
			const competitor = competitors[ activeIndex ];
			cardName.textContent = competitor.name;
			fillList( prosList, competitor.pros );
			fillList( consList, competitor.cons );
			fillList( countersList, competitor.counters );

			productsList.innerHTML = '';
			if ( competitor.products && competitor.products.length ) {
				competitor.products.forEach( function ( product ) {
					const li = document.createElement( 'li' );
					li.textContent = product.name;
					if ( product.next_tier ) {
						const upsellTag = document.createElement( 'span' );
						upsellTag.className = 'ssb-glance-upsell-tag';
						upsellTag.textContent = 'Upsell available: ' + product.next_tier;
						li.appendChild( document.createElement( 'br' ) );
						li.appendChild( upsellTag );
					}
					productsList.appendChild( li );
				} );
			} else {
				const li = document.createElement( 'li' );
				li.textContent = 'No products currently linked to this competitor.';
				productsList.appendChild( li );
			}
		}

		function openFlashcard( index ) {
			activeIndex = index;
			renderFlashcard();
			deck.hidden = true;
			flashcard.hidden = false;
		}

		function closeFlashcard() {
			deck.hidden = false;
			flashcard.hidden = true;
		}

		searchInput.addEventListener( 'input', function () {
			renderGrid( searchInput.value );
		} );

		backBtn.addEventListener( 'click', closeFlashcard );

		prevBtn.addEventListener( 'click', function () {
			activeIndex = ( activeIndex - 1 + competitors.length ) % competitors.length;
			renderFlashcard();
		} );

		nextBtn.addEventListener( 'click', function () {
			activeIndex = ( activeIndex + 1 ) % competitors.length;
			renderFlashcard();
		} );

		renderGrid( '' );
	} );
} )();
