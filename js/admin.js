( function( $ ) {

	$( document ).ready( function() {

		$( '#loginform-redirect' ).change( function() {

			if ( $( this ).val() == 'custom' )
				$( '#loginform-redirect-custom' ).show().focus();
			else
				$( '#loginform-redirect-custom' ).hide();

		} );

		$( '#loginform-redirect' ).change();

		$( '#loginform-shortcode input[type="submit"]' ).on( 'click', function() {

			var shortcode = '[loginform',
				redirect = $( '#loginform-redirect' ).val(),
				username_label = $( '#loginform-username-label' ).val(),
				password_label = $( '#loginform-password-label' ).val(),
				remember_label = $( '#loginform-remember-label' ).val(),
				button_label = $( '#loginform-button-label' ).val();

			if ( redirect == 'custom' )
				shortcode += ' redirect="' + $( '#loginform-redirect-custom' ).val() + '"';
			if ( redirect != 'current' )
				shortcode += ' redirect="' + redirect + '"';

			if ( '' != username_label )
				shortcode += ' label_username="' + username_label + '"';
			if ( '' != password_label )
				shortcode += ' label_password="' + password_label + '"';
			if ( '' != remember_label )
				shortcode += ' label_remember="' + remember_label + '"';
			if ( '' != button_label )
				shortcode += ' label_log_in="' + button_label + '"';

			shortcode += ']';

			return send_to_editor( shortcode );

		} );

		// tinymce popup
		$( '#logged-in-popup' ).on( 'click', '#logged-in-update input', function( e ) {
			e.preventDefault();

			var ed = tinyMCE.activeEditor,
				plugin = ed.plugins.loggedinout,
				roles = [],
				checks = $( '#logged-in-popup input[type="checkbox"]' ),
				attr = '';

			tinyMCEPopup.close();

			checks.each( function() {
				if ( $( this ).is( ':checked' ) )
					roles.push( $( this ).val() );
			} );

			if ( checks.length != roles.length )
				attr = ' role="' + roles.join( ',' ) + '"';

			plugin.insertShortcode( 'loggedin', attr );
		} );
		
		$( '#logged-in-popup' ).on( 'click', '#logged-in-cancel a', function( e ) {
			e.preventDefault();
			tinyMCEPopup.close();
		} );
		

	} );

} )( jQuery );
