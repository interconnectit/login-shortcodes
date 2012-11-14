(function() {
	tinymce.create('tinymce.plugins.loggedinout', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			var t = this;

			t.block = true,
			t.node = null,
			t.start = null,
			t.end = null,
			t.parent = null,
			t.url = url,
			t.ed = ed,
			t.isTag = false,
			t.cm = null,
			t.nearest = null;


			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand( 'ICIT_Logged_In', function() {
				if ( t.isTag || ( t.nearest && t.isShortcode( ed, t.nearest ) ) )
					return t.removeShortcode( ed );
				ed.windowManager.open({
					id : 'logged-in-popup',
					width : 480,
					height : "auto",
					title : 'Insert logged in only container',
    				wpDialog: true
				}, {
					plugin_url : url
				});
			});

			ed.addCommand( 'ICIT_Logged_Out', function() {
				if ( t.isTag || ( t.nearest && t.isShortcode( ed, t.nearest ) ) )
					return t.removeShortcode( ed );
				t.insertShortcode( 'loggedout' );
			});

			// Register buttons
			ed.addButton( 'loggedin', {
				title : 'Only show contained content to logged in users', // ed.getLang('advanced.link_desc'),
				cmd : 'ICIT_Logged_In'
			} );

			ed.addButton( 'loggedout', {
				title : 'Only show contained content to logged out users', // ed.getLang('advanced.link_desc'),
				cmd : 'ICIT_Logged_Out'
			} );

			ed.onNodeChange.add( function( ed, cm, n, co ) {
				t.node = n;

				// bust a move if the node isn't actually in the tinymce DOM
				if ( ! ed.dom.select( n ).length ) return;

				t.start = ed.selection.getStart();
				t.end = ed.selection.getEnd();
				t.block = ( '' == ed.selection.getContent() && ed.dom.isBlock( n ) ) || t.start !== t.end;
				t.isTag = t.isShortcode( ed, n );
				t.cm = cm;
				t.nearest = null;

				// work out whether to disable/enable buttons
				cm.setDisabled( 'loggedin', t.isTag && t.isTag[ 1 ] != 'in' );
				cm.setDisabled( 'loggedout', t.isTag && t.isTag[ 1 ] != 'out' );
				cm.setActive( 'loggedin', t.isTag && t.isTag[ 1 ] == 'in' );
				cm.setActive( 'loggedout', t.isTag && t.isTag[ 1 ] == 'out' );

				// if there's an opening tag before any closing tags
				if ( ! t.isTag ) {
					var tag_up = n,
						max = 0;

					while( tag_up && ! t.isShortcode( ed, tag_up ) ) {
						if ( ed.dom.getPrev( tag_up, ed.dom.isBlock ) )
							tag_up = ed.dom.getPrev( tag_up, ed.dom.isBlock );
						else if ( ed.dom.getParent( tag_up, ed.dom.isBlock ) )
							tag_up = ed.dom.getParent( tag_up, ed.dom.isBlock );
						else
							break;

						// escape crate
						if ( max++ == 100 )
							break;
					}

					if ( ! t.isClosingShortcode( ed, tag_up ) )
						t.nearest = tag_up;

					if ( tag_up && t.isOpeningShortcode( ed, tag_up ) ) {
						if ( ed.dom.getAttrib( tag_up, 'class' ).match( /loggedout/ ) ) {
							cm.setDisabled( 'loggedin', 1 );
							cm.setActive( 'loggedout', 1 );
						}
						if ( ed.dom.getAttrib( tag_up, 'class' ).match( /loggedin/ ) ) {
							cm.setDisabled( 'loggedout', 1 );
							cm.setActive( 'loggedin', 1 );
						}
					}

				}

				//console.log( 'nodeChange', tag_up );

			} );

			// remove corresponding shortcode if deleted
			ed.onKeyUp.add( function( ed, e ) {
				if ( e.keyCode == 8 || e.keyCode == 46 && t.isTag ) {
					t.removeShortcode( ed );
				}
			} );
		},

		isShortcode : function ( ed, n ) {
			console.log( ed.dom.select( n ), ed.dom.get( n ), ed, n, ed.dom.getAttrib( n, 'class' ) );
			return n ? ed.dom.getAttrib( n, 'class' ).match( /logged(in|out)-(opening|closing)/ ) : false;
		},

		isOpeningShortcode : function ( ed, n ) {
			return n ? ed.dom.getAttrib( n, 'class' ).match( /logged(in|out)-opening/ ) : false;
		},

		isClosingShortcode : function ( ed, n ) {
			return n ? ed.dom.getAttrib( n, 'class' ).match( /logged(in|out)-closing/ ) : false;
		},

		isInShortcode : function ( ed, n ) {
			return n ? ed.dom.getAttrib( n, 'class' ).match( /loggedin-(opening|closing)/ ) : false;
		},

		isOutShortcode : function ( ed, n ) {
			return n ? ed.dom.getAttrib( n, 'class' ).match( /loggedout-(opening|closing)/ ) : false;
		},

		insertShortcode : function( tag, attr ) {
			var attr = attr || '',
				opening_tag = '[' + tag + attr + ']',
				closing_tag = '[/' + tag + ']',
				ed = tinyMCE.activeEditor,
				t = this,
				node = t.node,
				block = t.block;

			// console.log( 'insertShortcode', t.nearest, node );

			if ( t.isTag || ( t.nearest && t.isOpeningShortcode( ed, t.nearest ) ) )
				return;

			if ( t.nearest && ! t.isShortcode( ed, t.nearest ) && ! ed.dom.isBlock( node ) ) {
				node = t.nearest;
				block = '' == ed.selection.getContent() && ed.dom.isBlock( node );
			}

			// console.log( 'insertShortcode', t.nearest, node, t.block, block, t.start, t.end, ed.dom.getPrev( t.start, '*' ) );

			if ( t.block && t.start !== t.end ) {
				ed.getBody().insertBefore( ed.dom.create( 'p', { class: tag + '-opening' }, opening_tag ), t.start );
				ed.dom.insertAfter( ed.dom.create( 'p', { class: tag + '-closing' }, closing_tag ), t.end );
			} else if ( block && t.start == t.end ) {
				ed.getBody().insertBefore( ed.dom.create( 'p', { class: tag + '-opening' }, opening_tag ), node );
				ed.dom.insertAfter( ed.dom.create( 'p', { class: tag + '-closing' }, closing_tag ), node );

			} else
				ed.selection.setContent( '<span class="' + tag + '-opening">' + opening_tag + '</span>' + ed.selection.getContent() + '<span class="' + tag + '-closing">' + closing_tag + '</span>' );

			if ( block && tag.match( /loggedout/ ) ) {
				t.cm.setDisabled( 'loggedin', 1 );
				t.cm.setActive( 'loggedout', 1 );
			}
			if ( block && tag.match( /loggedin/ ) ) {
				t.cm.setDisabled( 'loggedout', 1 );
				t.cm.setActive( 'loggedin', 1 );
			}
			ed.focus();
		},

		removeShortcode: function( ed ) {
			var t = this,
				tag = null,
				s = null,
				o = t.node,
				isTag = t.isTag;

			//console.log( 'removeShortcode', t.nearest, o );

			if ( ! t.isTag && ! t.isShortcode( ed, t.nearest ) )
				return;

			//console.log( 'removeShortcode after escape', t.nearest, o );

			if ( t.nearest ) {
				o = t.nearest;
				isTag = ed.dom.getAttrib( o, 'class' ).match( /logged(in|out)-(opening|closing)/ );
			}

			//console.log( 'removeShortcode after t.nearest', o, isTag );


			// find corresponding tag
			if ( isTag[ 2 ] == 'closing' ) { // search backwards
				tag = ed.dom.getPrev( o, '.logged' + isTag[ 1 ] + '-opening' );
				s = ed.dom.getPrev( o, '*' );
			}
			if ( isTag[ 2 ] == 'opening' ) { // search forwards
				tag = ed.dom.getNext( o, '.logged' + isTag[ 1 ] + '-closing' );
				s = ed.dom.getNext( o, '*' );
			}

			// remove shortcodes
			ed.dom.remove( tag );
			ed.dom.remove( o );
			ed.focus();
		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : 'Logged in / out content',
				author : 'interconnect/it',
				authorurl : 'http://interconnectit.com',
				infourl : '',
				version : "1.0"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add( 'loggedinout', tinymce.plugins.loggedinout );
})();
