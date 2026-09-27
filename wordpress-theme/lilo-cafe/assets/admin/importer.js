/* Lilo Cafe one-click demo import: runs each step in turn and shows progress. */
( function () {
	'use strict';

	var cfg = window.liloImporter;
	var button = document.getElementById( 'lilo-import-start' );
	if ( ! cfg || ! button ) {
		return;
	}

	var list = document.getElementById( 'lilo-steps' );
	var ok = document.getElementById( 'lilo-result' );
	var err = document.getElementById( 'lilo-error' );

	function row( step ) {
		return list.querySelector( '[data-step="' + step + '"]' );
	}

	function setRow( step, state, note ) {
		var li = row( step );
		li.classList.remove( 'is-running', 'is-done', 'is-error' );
		if ( state ) {
			li.classList.add( 'is-' + state );
		}
		if ( typeof note === 'string' ) {
			li.querySelector( 'small' ).textContent = note;
		}
	}

	function post( step ) {
		var body = new FormData();
		body.append( 'action', 'lilo_demo_step' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'step', step );
		body.append( 'front', document.getElementById( 'lilo-opt-front' ).checked ? '1' : '' );
		body.append( 'title', document.getElementById( 'lilo-opt-title' ).checked ? '1' : '' );
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( res ) {
			return res.text().then( function ( text ) {
				// Tolerate stray output (notices) before the JSON.
				var start = text.indexOf( '{' );
				try {
					return JSON.parse( start > 0 ? text.slice( start ) : text );
				} catch ( e ) {
					throw new Error( cfg.strings.network );
				}
			} );
		} );
	}

	function fail( step, message ) {
		setRow( step, 'error', '' );
		err.querySelector( '#lilo-error-text' ).textContent = cfg.strings.failed + ' ' + message;
		err.classList.add( 'is-visible' );
		button.disabled = false;
	}

	function run( index ) {
		if ( index >= cfg.steps.length ) {
			ok.querySelector( '#lilo-result-text' ).textContent = cfg.strings.done;
			ok.classList.add( 'is-visible' );
			button.disabled = false;
			return;
		}
		var step = cfg.steps[ index ];
		setRow( step, 'running' );

		post( step )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					fail( step, ( res && res.data && res.data.message ) || cfg.strings.network );
					return;
				}
				if ( res.data.editHome ) {
					document.getElementById( 'lilo-edit-home' ).href = res.data.editHome;
				}
				if ( res.data.done === false ) {
					setRow( step, 'running', res.data.note || '' );
					run( index );
					return;
				}
				setRow( step, 'done', res.data.note || '' );
				run( index + 1 );
			} )
			.catch( function ( e ) {
				fail( step, e.message || cfg.strings.network );
			} );
	}

	button.addEventListener( 'click', function () {
		if ( cfg.strings.confirm && ! window.confirm( cfg.strings.confirm ) ) {
			return;
		}
		button.disabled = true;
		ok.classList.remove( 'is-visible' );
		err.classList.remove( 'is-visible' );
		cfg.steps.forEach( function ( s ) {
			setRow( s, '', '' );
		} );
		run( 0 );
	} );
} )();
