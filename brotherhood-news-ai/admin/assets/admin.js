/* Brotherhood News AI – admin UI behaviour (no dependencies). */
( function () {
	'use strict';

	document.documentElement.classList.add( 'bhna-js' );

	document.addEventListener( 'DOMContentLoaded', function () {
		// Tabs: buttons [data-tab] show the matching .bhna-tab-panel[data-panel]; the choice survives reloads via the URL hash.
		document.querySelectorAll( '.bhna-tabs' ).forEach( function ( tabs ) {
			var buttons = tabs.querySelectorAll( 'button[data-tab]' );
			var scope = tabs.closest( 'form' ) || document;

			function activate( name ) {
				var found = false;
				buttons.forEach( function ( b ) {
					var on = b.getAttribute( 'data-tab' ) === name;
					b.classList.toggle( 'is-active', on );
					b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					found = found || on;
				} );
				if ( ! found ) {
					return false;
				}
				scope.querySelectorAll( '.bhna-tab-panel' ).forEach( function ( p ) {
					p.classList.toggle( 'is-active', p.getAttribute( 'data-panel' ) === name );
				} );
				return true;
			}

			buttons.forEach( function ( b ) {
				b.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					var name = b.getAttribute( 'data-tab' );
					activate( name );
					history.replaceState( null, '', '#' + name );
				} );
			} );

			var initial = window.location.hash.replace( '#', '' );
			if ( ! initial || ! activate( initial ) ) {
				activate( buttons.length ? buttons[ 0 ].getAttribute( 'data-tab' ) : '' );
			}
		} );

		// Show / hide secret fields.
		document.querySelectorAll( '[data-bhna-reveal]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var input = btn.parentNode.querySelector( 'input' );
				if ( ! input ) {
					return;
				}
				var show = input.type === 'password';
				input.type = show ? 'text' : 'password';
				btn.textContent = show ? btn.getAttribute( 'data-hide' ) : btn.getAttribute( 'data-show' );
			} );
		} );

		// Feeds: add a new feed card from the template, remove cards.
		var list = document.getElementById( 'bhna-feed-list' );
		var template = document.getElementById( 'bhna-feed-template' );
		var add = document.getElementById( 'bhna-add-feed' );
		if ( list && template && add ) {
			add.addEventListener( 'click', function () {
				var index = Date.now();
				var html = template.innerHTML.replace( /__INDEX__/g, index );
				list.insertAdjacentHTML( 'beforeend', html );
				var card = list.lastElementChild;
				var first = card && card.querySelector( 'input[type="text"]' );
				if ( first ) {
					first.focus();
				}
			} );
		}
		document.addEventListener( 'click', function ( e ) {
			var remove = e.target.closest( '[data-bhna-remove-feed]' );
			if ( ! remove ) {
				return;
			}
			e.preventDefault();
			var card = remove.closest( '.bhna-feed' );
			if ( ! card ) {
				return;
			}
			var flag = card.querySelector( 'input[data-delete-flag]' );
			if ( flag ) {
				if ( ! window.confirm( remove.getAttribute( 'data-confirm' ) || '¿Eliminar?' ) ) {
					return;
				}
				flag.value = '1';
				card.style.display = 'none';
			} else {
				card.remove();
			}
		} );

		// Confirmation for destructive buttons.
		document.querySelectorAll( '[data-bhna-confirm]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				if ( ! window.confirm( el.getAttribute( 'data-bhna-confirm' ) ) ) {
					e.preventDefault();
				}
			} );
		} );

		// Feed card: reflect the on/off toggle in the card style.
		document.addEventListener( 'change', function ( e ) {
			if ( e.target.matches( '.bhna-feed .bhna-feed-toggle' ) ) {
				e.target.closest( '.bhna-feed' ).classList.toggle( 'is-disabled', ! e.target.checked );
			}
		} );
	} );
} )();
