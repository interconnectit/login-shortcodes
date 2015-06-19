;(function($) {
	'use strict';

	function insert_content() {
		var ed = tinyMCE.activeEditor,
			plugin = ed.plugins.loggedinout,
			roles = [],
			checks = $( '#logged-in-popup input[type="checkbox"]' ),
			attr = '';

		checks.each( function() {
			if ( $( this ).is( ':checked' ) )
				roles.push( $( this ).val() );
		} );

		if ( checks.length != roles.length )
			attr = ' role="' + roles.join( ',' ) + '"';

		plugin.insertShortcode( 'loggedin', attr );
		return;
	}


	$( document ).ready( function() {
		$( '.submitbox' ).on( 'click', '#logged-in-update input', function( e ) {
			e.preventDefault();

			insert_content();

			tinyMCEPopup.editor.execCommand( 'mceRepaint' );
			tinyMCEPopup.close();
		} );

		$( '.submitbox' ).on( 'click', '#logged-in-cancel a', function( e ) {
			e.preventDefault();
			tinyMCEPopup.editor.execCommand( 'mceRepaint' );
			tinyMCEPopup.close();
		})

		$( 'body').show();
	} );
}(jQuery));
