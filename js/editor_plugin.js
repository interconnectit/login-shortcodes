(function() {
	tinymce.create( 'tinymce.plugins.loggedinout', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function( ed, url ) {
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
					id: 		'logged-in-popup',
					width:		480,
					height:		400,
					title:		'Insert logged in only container',
					url:		ajaxurl + '?action=icit_logged_in'
				}, {
					plugin_url: url
				});

				return true;
			});

			ed.addCommand( 'ICIT_Logged_Out', function() {
				if ( t.isTag || ( t.nearest && t.isShortcode( ed, t.nearest ) ) )
					return t.removeShortcode( ed );
				t.insertShortcode( 'loggedout' );

				return true;
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

			ed.on( 'nodeChange', function( e ) {
				var cm = ed.controlManager,
					n = ed.selection.getNode();

				// bust a move if the node isn't actually in the tinymce DOM
				if ( !ed.dom.select( n ) )
					return;

				t.node = n;
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
				if ( !t.isTag ) {
					var tag_up = n,
						max = 0;

					// Need to rework this so that it will check all the way up the tree.
					while( tag_up && !t.isShortcode( ed, tag_up ) ) {
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
			ed.on( 'keyUp', function( e ) {
				if ( ( e.keyCode == 8 || e.keyCode == 46 ) && t.isTag )
					t.removeShortcode( ed );
			} );
		},

		isShortcode : function ( ed, n ) {
			return n && !!ed.dom.getAttrib( n, 'class' ).match( /logged(in|out)-(opening|closing)/ );
		},

		isOpeningShortcode : function ( ed, n ) {
			return n && !!ed.dom.getAttrib( n, 'class' ).match( /logged(in|out)-opening/ );
		},

		isClosingShortcode : function ( ed, n ) {
			return n && !!ed.dom.getAttrib( n, 'class' ).match( /logged(in|out)-closing/ );
		},

		isInShortcode : function ( ed, n ) {
			return n && !!ed.dom.getAttrib( n, 'class' ).match( /loggedin-(opening|closing)/ );
		},

		isOutShortcode : function ( ed, n ) {
			return n && !!ed.dom.getAttrib( n, 'class' ).match( /loggedout-(opening|closing)/ );
		},

		insertShortcode : function( tag, attr ) {
			attr = attr || '';

			var opening_tag = '[' + tag + attr + ']',
				closing_tag = '[/' + tag + ']',
				ed = tinyMCE.activeEditor,
				t = this,
				node = t.node,
				block = t.block,
				ot = ed.dom.create( 'p', { class: tag + '-opening' }, opening_tag ),
				et = ed.dom.create( 'p', { class: tag + '-closing' }, closing_tag );


			// console.log( 'insertShortcode', t.nearest, node );

			if ( t.isTag || ( t.nearest && t.isOpeningShortcode( ed, t.nearest ) ) )
				return;

			if ( t.nearest && ! t.isShortcode( ed, t.nearest ) && ! ed.dom.isBlock( node ) ) {
				node = t.nearest;
				block = '' == ed.selection.getContent() && ed.dom.isBlock( node );
			}

			// Selection
			if ( t.block && t.start !== t.end ) {
				ed.selection.setContent( '<div class="' + tag + '-opening">' + opening_tag + '</div>' + ed.selection.getContent() + '<div class="' + tag + '-closing">' + closing_tag + '</div>' );
			}
			else if ( t.start !== t.end ) {
				ed.selection.setContent( '<span class="' + tag + '-opening">' + opening_tag + '</span>' + ed.selection.getContent() + '<span class="' + tag + '-closing">' + closing_tag + '</span>' );
			}
			else {
				// Run up the tree until we find a block level element
				while ( !ed.dom.isBlock( node ) && node.parentNode.nodeName !== 'BODY' )
					node = node.parentNode;

				// We've not killed all parents?
				if ( node.parentNode ) {
					node.parentNode.insertBefore( ot, node );
					ed.dom.insertAfter( et, node);
				}
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
