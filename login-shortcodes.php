<?php
/*
Plugin Name: Login Shortcodes
Plugin URI:
Description: Adds shortcodes to place a login form in pages, and to hide or show page content based on whether a user is logged in and their role.
Version: 0.1
Author: Robert O'Rourke
Author URI: http://sanchothefat.com
License: GPLv2 or later
*/

if ( ! class_exists( 'login_shortcodes' ) ) {

if ( ! defined( 'LOGIN_SHORTCODES_URL' ) )
	define( 'LOGIN_SHORTCODES_URL', plugins_url( '', __FILE__ ) );

add_action( 'init', array( 'login_shortcodes', 'instance' ) );

class login_shortcodes {

	protected static $instance = null;

	public $errors = null;

	public static function instance() {
		null === self :: $instance AND self :: $instance = new self;
		return self :: $instance;
	}

	public function __construct() {

		add_action( 'media_buttons', array( $this, 'form_button' ), 100000, 0 );

		// login form shortcode
		add_shortcode( 'loginform', array( $this, 'form_shortcode' ) );
		add_action( 'admin_footer', array( $this, 'form_insert' ) );

		if ( is_admin() ) {
			wp_enqueue_script( 'login-shortcodes', LOGIN_SHORTCODES_URL . '/js/admin.js', array( 'jquery' ) );
			wp_enqueue_style( 'login-shortcodes', LOGIN_SHORTCODES_URL . '/css/admin.css' );
		}

		// logged in / out shortcodes
		add_shortcode( 'loggedin', array( $this,  'logged_in_shortcode' ) );
		add_shortcode( 'loggedout', array( $this, 'logged_out_shortcode' ) );

		// add style dropdown if not present
		add_filter( 'mce_buttons', array( $this, 'mce_buttons' ) );

		// add plugin
		add_filter( 'mce_external_plugins', array( $this, 'mce_plugin' ) );

		// editor style for media items, tinymce.loadCSS puts us in wp-includes context
		add_filter( 'mce_css', create_function( '$mce_css', 'return $mce_css . "," . LOGIN_SHORTCODES_URL . "/css/editor.css";' ) );

		// enable removal of paragraph tags from shortcode when p tags have a class
		add_filter( 'term_description', array( $this, 'unautop' ) );
		add_filter( 'widget_text', array( $this, 'unautop' ) );
		add_filter( 'the_content', array( $this, 'unautop' ) );
		add_filter( 'the_excerpt', array( $this, 'unautop' ) );

		// add popup html
		add_action( 'admin_footer', array( $this, 'logged_in_popup' ) );

		// turn off cache at last minute if login state changed
		add_filter( 'login_redirect', create_function( '$url,$query,$user', 'return add_query_arg( array( \'nocache\' => \'true\' ), $url );' ), 10, 3 );

		// get errors
		$this->errors = get_transient( 'login_shortcode_errors' );
		if ( ! $this->errors )
			$this->errors = new WP_Error();

		// stay on shortcode form page if there's an error, follow standard redirect if not
		if ( isset( $_SERVER[ 'HTTP_REFERER' ] ) && preg_match( "/^" . addcslashes( home_url(), '/' ) . "/", $_SERVER[ 'HTTP_REFERER' ] ) && $_SERVER[ 'HTTP_REFERER' ] != wp_login_url() && isset( $_POST[ 'log' ] ) && isset( $_POST[ 'pwd' ] ) ) {
			require_once( 'includes/process-login.php' );
			$process_login = login_shortcodes_processing::process();
			$this->errors = $process_login[ 'errors' ];
			if ( $this->errors->get_error_code() ) {
				set_transient( 'login_shortcode_errors', $this->errors, 120 );
				wp_safe_redirect( $_SERVER[ 'HTTP_REFERER' ] );
			}
		}

	}

	public function form_button() {

		echo '<a href="#TB_inline?width=640&amp;height=557&amp;inlineId=loginform-shortcode" class="thickbox" title="' . __( 'Insert a login form' ) . '"><img src="' . LOGIN_SHORTCODES_URL . '/images/loginform.png" alt="Insert login form" /></a>';

	}

	public function form_shortcode( $attr, $content = null ) {
		global $error;

		if ( is_user_logged_in() )
			return '';

		extract( shortcode_atts( array(
			'redirect' => site_url( $_SERVER[ 'REQUEST_URI' ] ),
			'formid' => 'login-form-' . get_the_ID(),
			'label_username' => __( 'Username' ),
			'label_password' => __( 'Password' ),
	        'label_remember' => __( 'Please remember me on this computer' ),
	        'label_log_in' => __( 'Log In' ),
	        'id_username' => 'user_login-' . get_the_ID(),
	        'id_password' => 'user_pass-' . get_the_ID(),
	        'id_remember' => 'rememberme-' . get_the_ID(),
	        'id_submit' => 'wp-submit-' . get_the_ID(),
	        'remember' => false,
	        'value_username' => NULL,
	        'value_remember' => false
		), $attr ) );

		if ( $redirect == 'current' )
			$redirect = get_permalink( get_the_ID() );

		// redirect to page
		if ( is_int( $redirect ) )
			$redirect = get_permalink( $redirect );

		if ( is_string( $redirect ) && ! preg_match( "/^http(s)?:\/\//", $redirect ) )
			$redirect = get_permalink( get_page_by_path( $redirect )->ID );

		// setup output string
		$form = '';

		// In case a plugin uses $error rather than the $wp_errors object
		if ( ! empty( $error ) ) {
			$this->errors->add( 'error', $error );
			unset( $error );
		}

		// error display
		if ( $this->errors->get_error_code() ) {
			$errors = '';
			$messages = '';
			foreach ( $this->errors->get_error_codes() as $code ) {
				$severity = $this->errors->get_error_data( $code );
				foreach ( $this->errors->get_error_messages( $code ) as $error ) {
					if ( 'message' == $severity )
						$messages .= '	' . $error . "<br />\n";
					else
						$errors .= '	' . $error . "<br />\n";
				}
			}
			if ( ! empty( $errors ) )
				$form .= '<div class="login-error">' . apply_filters( 'login_errors', $errors ) . "</div>\n";
			if ( ! empty( $messages ) )
				$form .= '<p class="message">' . apply_filters( 'login_messages', $messages ) . "</p>\n";
		}

		// only needed once
		delete_transient( 'login_shortcode_errors' );

		$form .= wp_login_form( array(
			'echo' => false,
			'formid' => $formid,
			'redirect' => $redirect,
			'label_username' => $label_username,
			'label_password' => $label_password,
			'label_remember' => $label_remember,
			'label_log_in' => $label_log_in,
			'id_username' => $id_username,
			'id_password' => $id_password,
			'id_remember' => $id_remember,
			'id_submit' => $id_submit,
			'remember' => $remember,
			'value_username' => $value_username,
			'value_remember' => $value_remember
		) );

		return $form;
	}

	public function form_insert() {

		$args = array();
		if ( isset( $_REQUEST[ 'post' ] ) )
			$args[ 'exclude' ] = intval( $_REQUEST[ 'post' ] );

		$pages = get_pages( $args );

		?>
		<div style="display:none;">
			<div id="loginform-shortcode">
				<p>
					<label for="loginform-redirect"><?php _e( 'Redirect to' ); ?></label>
					<select name="loginform_redirect" id="loginform-redirect" class="widefat">
						<option value="current"><?php _e( 'Current page' ); ?></option>
						<option value="custom"><?php _e( 'Custom link' ); ?></option>
						<?php if ( count( $pages ) ) { ?>
						<optgroup label="<?php _e( 'Pages' ); ?>">
						<?php foreach( $pages as $page ) { ?>
							<option value="<?php echo $page->post_name; ?>"><?php echo $page->post_title; ?></option>
						<?php } ?>
						</optgroup>
						<?php } ?>
					</select>
					<br />
					<br />
					<input id="loginform-redirect-custom" type="text" name="loginform_redirect_custom" value="" class="widefat" />
				</p>
				<p>
					<label for="loginform-username-label"><?php _e( 'Username label' ); ?></label>
					<input id="loginform-username-label" type="text" name="loginform_username_label" value="" class="widefat" />
				</p>
				<p>
					<label for="loginform-password-label"><?php _e( 'Password label' ); ?></label>
					<input id="loginform-password-label" type="text" name="loginform_password_label" value="" class="widefat" />
				</p>
				<p>
					<label for="loginform-remember-label"><?php _e( 'Remember me label' ); ?></label>
					<input id="loginform-remember-label" type="text" name="loginform_remember_label" value="" class="widefat" />
				</p>
				<p>
					<label for="loginform-button-label"><?php _e( 'Button text' ); ?></label>
					<input id="loginform-button-label" type="text" name="loginform_button_label" value="" class="widefat" />
				</p>
				<p>
					<input type="submit" value="<?php _e( 'Insert into post' ); ?>" class="button-primary" />
				</p>
			</div>
		</div>
		<?php
	}


	/**
	 * Insert break out link button after unlink button
	 *
	 * @param array $buttons The buttons of the top row in the TinyMCE editor
	 *
	 * @return array    Modified buttons array
	 */
	public function mce_buttons( $buttons ) {
		$temp = array();

		foreach( $buttons as $button ) {
			$temp[] = $button;
			if ( $button == 'wp_more' ) {
				$temp[] = '|';
				$temp[] = 'loggedin';
				$temp[] = 'loggedout';
			}
		}

		$buttons = $temp;

		return $buttons;
	}

	public function mce_plugin( $plugins ) {
		$plugins[ 'loggedinout' ] = LOGIN_SHORTCODES_URL . '/js/editor_plugin.js';
		return $plugins;
	}

	public function mce_css( $css ) {

		return $css;
	}

	public function logged_in_shortcode( $attr, $content = null ) {

		extract( shortcode_atts( array(
			'role' => false
		), $attr ) );

		$roles = array();
		$user = new WP_User( get_current_user_id() );

		if ( $role )
			$roles = explode( ',', $role );

		$allowed_roles = array_intersect( $user->roles, $roles );

		if ( is_user_logged_in() && ( empty( $roles ) || ! empty( $allowed_roles ) ) )
			return do_shortcode( $content );

		return '';
	}

	public function logged_out_shortcode( $attr, $content = null ) {

		if ( ! is_user_logged_in() )
			return do_shortcode( $content );

		return '';
	}

	public function logged_in_popup() {
		global $wp_roles;
		?>

		<div style="display:none;">
			<form id="logged-in-popup" tabindex="-1">
				<p><?php _e( 'Choose which user roles this content should be visible to. If none are selected any logged in user will be able to see it.' ); ?></p>
				<ul class="roles-list">
				<?php foreach( $wp_roles->roles as $role => $data ) { ?>
					<li><label><input checked="checked" type="checkbox" name="logged_in_role[]" value="<?php esc_attr_e( $role ); ?>" /> <?php echo $data[ 'name' ]; ?></label></li>
				<?php } ?>
				</ul>
				<div class="submitbox">
					<div id="logged-in-cancel">
						<a class="submitdelete deletion" href="#"><?php _e( 'Cancel' ); ?></a>
					</div>
					<div id="logged-in-update">
						<input type="submit" tabindex="100" value="<?php esc_attr_e( 'Insert shortcode' ); ?>" class="button-primary" id="loggedin-submit" name="loggedin-submit" />
					</div>
				</div>
			</form>
		</div>

		<?php
	}


	/**
	 * Don't auto-p wrap shortcodes that stand alone
	 *
	 * Ensures that shortcodes are not wrapped in <<p>>...<</p>>.
	 *
	 * @param string $pee The content.
	 * @return string The filtered content.
	 */
	function unautop( $pee ) {
		global $shortcode_tags;

		if ( empty( $shortcode_tags ) || !is_array( $shortcode_tags ) ) {
			return $pee;
		}

		$tagregexp = join( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );

		$pattern =
			  '/'
			. '<(?:p|span)(?:[^>]*)>'            // Opening paragraph plus attributes
			. '\\s*+'                            // Optional leading whitespace
			. '('                                // 1: The shortcode
			.     '\\[\\/?'                      // Opening/Closing bracket
			.     "($tagregexp)"                 // 2: Shortcode name
			.     '\\b'                          // Word boundary
			                                     // Unroll the loop: Inside the opening shortcode tag
			.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
			.     '(?:'
			.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
			.         '[^\\]\\/]*'               // Not a closing bracket or forward slash
			.     ')*?'
			.     '(?:'
			.         '\\/\\]'                   // Self closing tag and closing bracket
			.     '|'
			.         '\\]'                      // Closing bracket
			//.         '(?:'                      // Unroll the loop: Optionally, anything between the opening and closing shortcode tags
			//.             '[^\\[]*+'             // Not an opening bracket
			//.             '(?:'
			//.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
			//.                 '[^\\[]*+'         // Not an opening bracket
			//.             ')*+'
			//.             '\\[\\/\\2\\]'         // Closing shortcode tag
			//.         ')?'
			.     ')'
			. ')'
			. '\\s*+'                            // optional trailing whitespace
			. '<\\/(?:p|span)>'                  // closing paragraph
			. '/s';

		return preg_replace( $pattern, '$1', $pee );
	}

}

}


?>
