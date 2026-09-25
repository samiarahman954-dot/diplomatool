( function () {
	'use strict';

	var l10n = window.sfmL10n || {};

	document.querySelectorAll( '.sfm-rename-form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			var current = form.querySelector( '[name="sfm_target"]' ).value;
			var name = window.prompt( l10n.renamePrompt || 'New name:', current );
			if ( ! name || name === current ) {
				e.preventDefault();
				return;
			}
			form.querySelector( '[name="sfm_name"]' ).value = name;
		} );
	} );

	document.querySelectorAll( '.sfm-delete-form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			var name = form.querySelector( '[name="sfm_target"]' ).value;
			var msg = ( l10n.deleteConfirm || 'Delete "%s"?' ).replace( '%s', name );
			if ( ! window.confirm( msg ) ) {
				e.preventDefault();
			}
		} );
	} );
} )();
