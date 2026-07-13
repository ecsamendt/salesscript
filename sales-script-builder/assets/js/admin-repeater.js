/**
 * Generic add/remove row behavior for the repeater meta boxes
 * (pain points, competitors, objections, upsell paths).
 *
 * Re-indexes input/select `name` attributes on every add/remove so saved
 * rows always submit as a clean, gap-free array -- PHP just needs to
 * iterate $_POST[field_name] without worrying about missing indexes.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.ssb-repeater' ).forEach( initRepeater );
	} );

	function initRepeater( repeaterEl ) {
		const fieldName = repeaterEl.getAttribute( 'data-repeater' );
		const rowsWrap  = repeaterEl.querySelector( '.ssb-repeater-rows' );
		const addBtn    = repeaterEl.querySelector( '.ssb-add-row' );

		addBtn.addEventListener( 'click', function () {
			const rows    = rowsWrap.querySelectorAll( '.ssb-repeater-row' );
			const lastRow = rows[ rows.length - 1 ];
			const newRow  = lastRow.cloneNode( true );

			// Clear values in the cloned row.
			newRow.querySelectorAll( 'input[type="text"]' ).forEach( function ( input ) {
				input.value = '';
			} );
			newRow.querySelectorAll( 'select' ).forEach( function ( select ) {
				select.selectedIndex = 0;
			} );

			rowsWrap.appendChild( newRow );
			reindexRows( rowsWrap, fieldName );
			bindRemove( repeaterEl, fieldName );
		} );

		bindRemove( repeaterEl, fieldName );
	}

	function bindRemove( repeaterEl, fieldName ) {
		repeaterEl.querySelectorAll( '.ssb-remove-row' ).forEach( function ( btn ) {
			btn.onclick = function () {
				const rowsWrap = repeaterEl.querySelector( '.ssb-repeater-rows' );
				const rows     = rowsWrap.querySelectorAll( '.ssb-repeater-row' );

				// Always keep at least one row so the meta box never looks broken/empty.
				if ( rows.length <= 1 ) {
					btn.closest( '.ssb-repeater-row' ).querySelectorAll( 'input[type="text"]' ).forEach( function ( input ) {
						input.value = '';
					} );
					return;
				}

				btn.closest( '.ssb-repeater-row' ).remove();
				reindexRows( rowsWrap, fieldName );
			};
		} );
	}

	function reindexRows( rowsWrap, fieldName ) {
		const rows = rowsWrap.querySelectorAll( '.ssb-repeater-row' );
		rows.forEach( function ( row, index ) {
			row.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace(
					new RegExp( '^' + fieldName + '\\[\\d+\\]' ),
					fieldName + '[' + index + ']'
				);
			} );
		} );
	}
} )();
