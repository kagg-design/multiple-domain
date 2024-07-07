( function( $ ) {
	'use strict';

	const $d = $( document );
	let count = null;

	$d.on( 'click', '.multiple-domain-remove', function( event ) {
		event.preventDefault();
		$( this ).parent().remove();
	} );

	$d.on( 'click', '.multiple-domain-add', function( event ) {
		event.preventDefault();

		if ( count === null ) {
			count = $( '.multiple-domain-domain' ).length;
		}

		$( this ).parent().before( multipleDomainFields.replace( /COUNT/g, count++ ) );
	} );

} )( jQuery );
