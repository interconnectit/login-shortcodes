<?php
/*
 Plugin Name: Login Shortcodes
 Plugin URI:
 Description: Adds shortcodes to place a login form in pages, and to hide or show page content based on whether a user is logged in and their role. Requires WordPress 3.9+
 Version: 0.2
 Author: Robert O'Rourke
 Author URI: http://sanchothefat.com
 License: GPLv2 or later
 Text Domain: icit_losh
*/

if ( ! class_exists( 'login_shortcodes' ) ) {

	if ( ! defined( 'LOGIN_SHORTCODES_URL' ) ) {
		define( 'LOGIN_SHORTCODES_URL', plugins_url( '', __FILE__ ) );
	}

	add_action( 'init', array( 'login_shortcodes', 'instance' ) );

	class login_shortcodes {

		protected static $instance = null;

		public $errors   = null;
		public $messages = array();

		public static function instance() {
			null === self::$instance AND self::$instance = new self;
			return self::$instance;
		}

		public function __construct() {

			if ( current_user_can( 'manage_options' ) ) {
				add_action( 'media_buttons', array( $this, 'form_button' ), 100000, 0 );
			}

			// login form shortcode
			add_shortcode( 'loginform', array( $this, 'form_shortcode' ) );
			add_action( 'admin_footer', array( $this, 'form_insert' ) );

			if ( is_admin() ) {
				wp_enqueue_script( 'login-shortcodes', LOGIN_SHORTCODES_URL . '/js/admin.js', array( 'jquery', 'wpdialogs' ) );
				wp_enqueue_style( 'login-shortcodes', LOGIN_SHORTCODES_URL . '/css/admin.css', 'wp-jquery-ui-dialog' );

				// TinyMCE popup stuff
				wp_register_script( 'tinymce-popup', includes_url( '/js/tinymce/tiny_mce_popup.js' ), array(), '1' );
				wp_register_script( 'tinymce-form-utils', includes_url( '/js/tinymce/utils/form_utils.js' ), array(), '1' );
				wp_register_script( 'icit-login-popup', LOGIN_SHORTCODES_URL . '/js/popup.js', array( 'jquery', 'tinymce-popup', 'tinymce-form-utils' ), '1' );
				wp_register_style( 'icit-login-popup-css', LOGIN_SHORTCODES_URL . '/css/popup.css', array( 'buttons' ), 1, 'all' );
			}

			// logged in / out shortcodes
			add_shortcode( 'loggedin', array( $this, 'logged_in_shortcode' ) );
			add_shortcode( 'loggedout', array( $this, 'logged_out_shortcode' ) );

			// add style dropdown if not present
			if ( current_user_can( 'manage_options' ) ) {
				add_filter( 'mce_buttons', array( $this, 'mce_buttons' ) );
			}

			// add plugin
			add_filter( 'mce_external_plugins', array( $this, 'mce_plugin' ) );

			// editor style for media items, tinymce.loadCSS puts us in wp-includes context
			add_filter( 'mce_css', function ( $mce_css ) {
				return $mce_css . "," . LOGIN_SHORTCODES_URL . "/css/editor.css";
			} );

			// enable removal of paragraph tags from shortcode when p tags have a class
			add_filter( 'term_description', array( $this, 'unautop' ) );
			add_filter( 'widget_text', array( $this, 'unautop' ) );
			add_filter( 'the_content', array( $this, 'unautop' ) );
			add_filter( 'the_excerpt', array( $this, 'unautop' ) );

			// add popup html
			add_action( 'wp_ajax_icit_logged_in', array( $this, 'logged_in_popup' ) );

			// turn off cache at last minute if login state changed
			add_filter( 'login_redirect', function ( $url, $query, $user ) {
				nocache_headers();
				return add_query_arg( array( 'nocache' => 'true' ), $url );
			}, 10, 3 );
			add_filter( 'lostpassword_redirect', function ( $url ) {
				nocache_headers();
				return add_query_arg( array( 'nocache' => 'true' ), $url );
			}, 10, 1 );

			// if errors found on login redirect back with error in query param
			add_filter( 'wp_login_errors', array( $this, 'login_errors' ), 9999, 2 );
			add_action( 'lost_password', array( $this, 'lost_password' ), 9999 );
			//add_filter( 'shake_error_codes', array( $this, 'login_errors' ), 9999, 1 );

			// add a custom nonce field so we can test if our front end form is referrer
			add_filter( 'login_form_bottom', array( $this, 'frontend_nonce' ), 10, 2 );

			// set messages
			$this->messages = apply_filters( 'login_shortcodes_error_messages', array(
				'empty_username'          => array( 'message' => __( '<strong>ERROR</strong>: Enter a username or e-mail address' ), 'severity' => 'error' ),
				'empty_password'          => array( 'message' => __( '<strong>ERROR</strong>: Enter your password' ), 'severity' => 'error' ),
				'incorrect_password'      => array( 'message' => __( '<strong>ERROR</strong>: The password you entered is incorrect' ), 'severity' => 'error' ),
				'invalid_email'           => array( 'message' => __( '<strong>ERROR</strong>: There is no registered user with that email address' ), 'severity' => 'error' ),
				'invalid_username'        => array( 'message' => __( '<strong>ERROR</strong>: There is no registered user with that username' ), 'severity' => 'error' ),
				'invalidcombo'            => array( 'message' => __( '<strong>ERROR</strong>: Invalid username or password combination' ), 'severity' => 'error' ),
				'invalidkey'              => array( 'message' => __( 'Sorry, that key does not appear to be valid.' ), 'severity' => 'error' ),
				'expiredkey'              => array( 'message' => __( 'Sorry that key has expired. Please try again.' ), 'severity' => 'error' ),
				'password_reset_mismatch' => array( 'message' => __( 'The passwords do not match.' ), 'severity' => 'error' ),
				'expired'                 => array( 'message' => __( 'Session expired. Please log in again. You will not move away from this page.' ), 'severity' => 'messsage' ), // message
				'loggedout'               => array( 'message' => __( 'You are now logged out' ), 'severity' => 'messsage' ), // message
				'registerdisabled'        => array( 'message' => __( 'User registration is currently not allowed.' ), 'severity' => 'error' ),
				'confirm'                 => array( 'message' => __( 'Check your e-mail for the confirmation link.' ), 'severity' => 'messsage' ), // message
				'newpass'                 => array( 'message' => __( 'Check your e-mail for your new password.' ), 'severity' => 'messsage' ), // message
				'registered'              => array( 'message' => __( 'Registration complete. Please check your e-mail.' ), 'severity' => 'messsage' ), // message
				'updated'                 => array( 'message' => __( '<strong>You have successfully updated WordPress!</strong> Please log back in to experience the awesomeness.' ), 'severity' => 'messsage' ) // message
			) );

			// check frontend nonce
			$this->errors = new WP_Error();
			if ( isset( $_REQUEST['_lsnonce'] ) && wp_verify_nonce( $_REQUEST['_lsnonce'], 'frontend_login_errors' ) ) {
				// get errors
				$get_errors = isset( $_REQUEST['login_errors'] ) ? $_REQUEST['login_errors'] : false;
				if ( $get_errors && is_array( $get_errors ) ) {
					foreach ( $get_errors as $code ) {
						if ( ! isset( $this->messages[ $code ] ) ) {
							continue;
						}
						$this->errors->add( $code, $this->messages[ $code ]['message'], $this->messages[ $code ]['severity'] );
					}
				}
			}

		}

		/**
		 * Redirects if we have login errors on our front end form
		 *
		 * @param WP_Error $errors
		 * @param string   $redirect_to
		 * @param string   $error_redirect_to
		 *
		 * @return WP_Error
		 */
		public function login_errors( WP_Error $errors, $redirect_to = '', $error_redirect_to = '' ) {

			if ( ! isset( $_POST['_lsnonce'] ) ) {
				return $errors;
			}

			// attempt to get $redirect_to
			if ( empty( $redirect_to ) && isset( $_REQUEST['redirect_to'] ) ) {
				$redirect_to = esc_url_raw( urldecode( $_REQUEST['redirect_to'] ) );
			}

			// attempt to get $redirect_to
			if ( empty( $error_redirect_to ) && isset( $_REQUEST['error_redirect_to'] ) ) {
				$error_redirect_to = esc_url_raw( urldecode( $_REQUEST['error_redirect_to'] ) );
				$error_redirect_to = add_query_arg( array( 'error_redirect_to' => urlencode( $error_redirect_to ) ), $error_redirect_to );
			}

			// standard login
			if ( ! isset( $_GET['action'] ) ) {

				$log  = isset( $_POST['log'] ) ? sanitize_text_field( $_POST['log'] ) : '';
				$pass = isset( $_POST['pwd'] ) ? sanitize_text_field( $_POST['pwd'] ) : '';

				$check = wp_authenticate_username_password( null, $log, $pass );
				$check = apply_filters( 'login_check_auth_user_pass', $check, $log, $pass );

				if ( is_wp_error( $check ) ) {
					$errors = $check;
				}

				// other login actions
			} else {

				switch ( $_GET['action'] ) {

					case 'lostpassword':
						if ( isset( $_GET['error'] ) ) {
							if ( 'invalidkey' == $_GET['error'] ) {
								$errors->add( 'invalidkey', $this->messages['invalidkey'] );
							} elseif ( 'expiredkey' == $_GET['error'] ) {
								$errors->add( 'expiredkey', $this->messages['expiredkey'] );
							}
						}
						if ( isset( $_POST['user_login'] ) ) {
							if ( empty( $_POST['user_login'] ) ) {
								$errors->add( 'empty_username', $this->messages['empty_username'] );
							} else {
								if ( strpos( $_POST['user_login'], '@' ) ) {
									$user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );
									if ( empty( $user_data ) ) {
										$errors->add( 'invalid_email', $this->messages['invalid_email'] );
									}
								} else {
									$login     = trim( $_POST['user_login'] );
									$user_data = get_user_by( 'login', $login );
									if ( empty( $user_data ) ) {
										$errors->add( 'invalidcombo', $this->messages['invalidcombo'] );
									}
								}
							}
						}
						break;

				}

			}

			$url_errors = $errors->get_error_codes();

			if ( ! empty( $url_errors ) ) {
				$redirect_to = $error_redirect_to ? $error_redirect_to : $redirect_to;
				$redirect_to = add_query_arg( array( 'login_errors' => $url_errors, '_lsnonce' => wp_create_nonce( 'frontend_login_errors' ) ), $redirect_to );
				$redirect_to = remove_query_arg( array( 'success' ), $redirect_to );

				// remember field values
				if ( isset( $_POST['log'] ) && ! empty( $_POST['log'] ) ) {
					$redirect_to = add_query_arg( 'log', urlencode( sanitize_text_field( $_POST['log'] ) ), $redirect_to );
				}
				if ( isset( $_POST['rememberme'] ) ) {
					$redirect_to = add_query_arg( 'rememberme', 1, $redirect_to );
				}
				if ( isset( $_POST['user_login'] ) && ! empty( $_POST['user_login'] ) ) {
					$redirect_to = add_query_arg( 'user_login', urlencode( sanitize_text_field( $_POST['user_login'] ) ), $redirect_to );
				}

			} else {
				$redirect_to = add_query_arg( array( 'success' => true ), $redirect_to );
				$redirect_to = remove_query_arg( array( 'login_errors', '_lsnonce' ), $redirect_to );
			}

			nocache_headers();
			wp_safe_redirect( $redirect_to, 302 );
			exit;
		}

		/**
		 * Redirect to the originating page
		 */
		public function lost_password() {

			$this->login_errors( $this->errors );

		}

		/**
		 * Adds a nonce field to our front end forms so we can tell requests apart
		 *
		 * @param string $output Form output
		 * @param array  $args   Form output arguments
		 *
		 * @return string
		 */
		public function frontend_nonce( $output, $args ) {

			if ( isset( $args['frontend'] ) && $args['frontend'] ) {
				$output .= wp_nonce_field( 'frontend_login', '_lsnonce', true, false );
			}

			// add an error_redirect_to hidden field
			if ( isset( $_REQUEST['error_redirect_to'] ) ) {
				$output .= '<input type="hidden" name="error_redirect_to" value="' . esc_attr( $_REQUEST['error_redirect_to'] ) . '" />';
			}

			return $output;
		}

		public function form_button() {

			echo '<a href="#TB_inline?width=640&amp;height=557&amp;inlineId=loginform-shortcode" class="thickbox button" title="' . __( 'Insert a login form', 'icit_losh' ) . '"><img src="' . LOGIN_SHORTCODES_URL . '/images/loginform.png" alt="Insert login form" /> Add login form</a>';

		}

		public function form_shortcode( $attr, $content = null ) {

			if ( is_user_logged_in() ) {
				return '';
			}

			extract( shortcode_atts( array(
				'redirect'       => get_permalink(),
				'formid'         => 'login-form-' . get_the_ID(),
				'label_username' => __( 'Username', 'icit_losh' ),
				'label_password' => __( 'Password', 'icit_losh' ),
				'label_remember' => __( 'Please remember me on this computer', 'icit_losh' ),
				'label_log_in'   => __( 'Log In', 'icit_losh' ),
				'id_username'    => 'user_login-' . get_the_ID(),
				'id_password'    => 'user_pass-' . get_the_ID(),
				'id_remember'    => 'rememberme-' . get_the_ID(),
				'id_submit'      => 'wp-submit-' . get_the_ID(),
				'remember'       => false,
				'value_username' => null,
				'value_remember' => false
			), $attr ) );

			if ( $redirect == 'current' ) {
				$redirect = get_permalink( get_the_ID() );
			}

			// redirect to page
			if ( is_int( $redirect ) ) {
				$redirect = get_permalink( $redirect );
			}

			if ( is_string( $redirect ) && ! preg_match( "/^http(s)?:\/\//", $redirect ) ) {
				$redirect = get_permalink( get_page_by_path( $redirect )->ID );
			}

			// add username and remember values
			if ( isset( $_GET['log'] ) ) {
				$value_username = sanitize_text_field( $_GET['log'] );
			}
			if ( isset( $_GET['rememberme'] ) && $_GET['rememberme'] ) {
				$value_remember = true;
			}

			// setup output string
			$form = '';

			// show errors
			$form .= $this->get_errors();

			$form .= $this->wp_login_form( array(
				'echo'           => false,
				'formid'         => $formid,
				'redirect'       => $redirect,
				'label_username' => $label_username,
				'label_password' => $label_password,
				'label_remember' => $label_remember,
				'label_log_in'   => $label_log_in,
				'id_username'    => $id_username,
				'id_password'    => $id_password,
				'id_remember'    => $id_remember,
				'id_submit'      => $id_submit,
				'remember'       => $remember,
				'value_username' => $value_username,
				'value_remember' => $value_remember,
				'frontend'       => true
			) );

			return $form;
		}

		public function get_errors() {
			global $error;

			$output = '';

			// In case a plugin uses $error rather than the $wp_errors object
			if ( ! empty( $error ) ) {
				$this->errors->add( 'error', $error );
				unset( $error );
			}

			// error display
			if ( $this->errors->get_error_code() ) {
				$errors   = '';
				$messages = '';
				foreach ( $this->errors->get_error_codes() as $code ) {
					$severity = $this->errors->get_error_data( $code );
					foreach ( $this->errors->get_error_messages( $code ) as $error ) {
						if ( 'message' == $severity ) {
							$messages .= '	' . $error . "<br />\n";
						} else {
							$errors .= '	' . $error . "<br />\n";
						}
					}
				}
				if ( ! empty( $errors ) ) {
					$output .= '<div class="login-error">' . apply_filters( 'login_errors', $errors ) . "</div>\n";
				}
				if ( ! empty( $messages ) ) {
					$output .= '<div class="login-message">' . apply_filters( 'login_messages', $messages ) . "</div>\n";
				}
			}

			return $output;
		}

		/**
		 * ** Copy of function found in wp-includes/general-template.php **
		 *
		 * Provides a simple login form for use anywhere within WordPress. By default, it echoes
		 * the HTML immediately. Pass array('echo'=>false) to return the string instead.
		 *
		 * @since 3.0.0
		 *
		 * @param array $args Configuration options to modify the form output.
		 * @return string|null String when retrieving, null when displaying.
		 */
		public function wp_login_form( $args = array() ) {
			$defaults = array(
				'echo'           => true,
				'redirect'       => ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], // Default redirect is back to the current page
				'form_id'        => 'loginform',
				'label_username' => __( 'Username' ),
				'label_password' => __( 'Password' ),
				'label_remember' => __( 'Remember Me' ),
				'label_log_in'   => __( 'Log In' ),
				'id_username'    => 'user_login',
				'id_password'    => 'user_pass',
				'id_remember'    => 'rememberme',
				'id_submit'      => 'wp-submit',
				'remember'       => true,
				'value_username' => '',
				'value_remember' => false, // Set this to true to default the "Remember me" checkbox to checked
			);

			/**
			 * Filter the default login form output arguments.
			 *
			 * @since 3.0.0
			 *
			 * @see   wp_login_form()
			 *
			 * @param array $defaults An array of default login form arguments.
			 */
			$args = wp_parse_args( $args, apply_filters( 'login_form_defaults', $defaults ) );

			/**
			 * Filter content to display at the top of the login form.
			 *
			 * The filter evaluates just following the opening form tag element.
			 *
			 * @since 3.0.0
			 *
			 * @param string $content Content to display. Default empty.
			 * @param array  $args    Array of login form arguments.
			 */
			$login_form_top = apply_filters( 'login_form_top', '', $args );

			/**
			 * Filter content to display in the middle of the login form.
			 *
			 * The filter evaluates just following the location where the 'login-password'
			 * field is displayed.
			 *
			 * @since 3.0.0
			 *
			 * @param string $content Content to display. Default empty.
			 * @param array  $args    Array of login form arguments.
			 */
			$login_form_middle = apply_filters( 'login_form_middle', '', $args );

			/**
			 * Filter content to display at the bottom of the login form.
			 *
			 * The filter evaluates just preceding the closing form tag element.
			 *
			 * @since 3.0.0
			 *
			 * @param string $content Content to display. Default empty.
			 * @param array  $args    Array of login form arguments.
			 */
			$login_form_bottom = apply_filters( 'login_form_bottom', '', $args );

			// filter error codes for username and password fields
			$invalid_username_keys = apply_filters( 'login_invalid_username_keys', array( 'empty_username', 'invalid_username', 'invalid_email', 'invalidcombo' ) );
			$invalid_password_keys = apply_filters( 'login_invalid_password_keys', array( 'empty_password', 'incorrect_password', 'invalidcombo' ) );

			$form = '
		<form name="' . $args['form_id'] . '" id="' . $args['form_id'] . '" action="' . esc_url( site_url( 'wp-login.php', 'login_post' ) ) . '" method="post">
			' . $login_form_top . '
			<p class="login-username' . ( array_intersect( $invalid_username_keys, $this->errors->get_error_codes() ) ? ' field-error' : '' ) . '">
				<label for="' . esc_attr( $args['id_username'] ) . '">' . esc_html( $args['label_username'] ) . '</label>
				<input type="text" name="log" id="' . esc_attr( $args['id_username'] ) . '" class="input" value="' . esc_attr( $args['value_username'] ) . '" size="20" />
			</p>
			<p class="login-password' . ( array_intersect( $invalid_password_keys, $this->errors->get_error_codes() ) ? ' field-error' : '' ) . '">
				<label for="' . esc_attr( $args['id_password'] ) . '">' . esc_html( $args['label_password'] ) . '</label>
				<input type="password" name="pwd" id="' . esc_attr( $args['id_password'] ) . '" class="input" value="" size="20" />
			</p>
			' . $login_form_middle . '
			' . ( $args['remember'] ? '<p class="login-remember"><label><input name="rememberme" type="checkbox" id="' . esc_attr( $args['id_remember'] ) . '" value="forever"' . ( $args['value_remember'] ? ' checked="checked"' : '' ) . ' /> ' . esc_html( $args['label_remember'] ) . '</label></p>' : '' ) . '
			<p class="login-submit">
				<input type="submit" name="wp-submit" id="' . esc_attr( $args['id_submit'] ) . '" class="button button-primary" value="' . esc_attr( $args['label_log_in'] ) . '" />
				<input type="hidden" name="redirect_to" value="' . esc_url( $args['redirect'] ) . '" />
			</p>
			' . $login_form_bottom . '
		</form>';

			if ( $args['echo'] ) {
				echo $form;
			} else {
				return $form;
			}
		}


		public function lost_password_form( $args = array() ) {
			$defaults = array(
				'echo'             => true,
				'redirect'         => ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], // Default redirect is back to the current page
				'form_id'          => 'lostpasswordform',
				'label_username'   => __( 'Username or E-mail:' ),
				'label_submit'     => __( 'Get a new password' ),
				'id_username'      => 'user_login',
				'id_submit'        => 'wp-submit',
				'value_user_login' => ''
			);

			$args = wp_parse_args( $args, apply_filters( 'lostpassword_form_defaults', $defaults ) );

			$form = '';

			// prepend errors
			$form .= $this->get_errors();

			$form .= '
		<form name="' . $args['form_id'] . '" id="' . $args['form_id'] . '" action="' . esc_url( site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ) . '" method="post">
			<p class="lostpassword-username' . ( array_intersect( array( 'invalid_email', 'empty_username', 'invalidcombo' ), $this->errors->get_error_codes() ) ? ' field-error' : '' ) . '">
				<label for="' . $args['id_username'] . '" >' . $args['label_username'] . '</label>
				<input type="text" name="user_login" id="' . $args['id_username'] . '" class="input" value="' . esc_attr( $args['value_user_login'] ) . '" size="20" />
			</p>';
			ob_start();
			do_action( 'lostpassword_form' );
			$form .= ob_get_clean();
			$form .= '
			<p class="lostpassword-submit">
				<input type="submit" name="wp-submit" id="' . esc_attr( $args['id_submit'] ) . '" class="button button-primary" value="' . esc_attr( $args['label_submit'] ) . '" />
				<input type="hidden" name="redirect_to" value="' . esc_attr( add_query_arg( array( 'success' => true ), $args['redirect'] ) ) . '" />
				' . wp_nonce_field( 'frontend_login', '_lsnonce', true, false ) . '
			</p>
		</form>';

			if ( $echo ) {
				echo $form;
			}
			return $form;
		}


		public function form_insert() {

			$args = array();
			if ( isset( $_REQUEST['post'] ) ) {
				$args['exclude'] = intval( $_REQUEST['post'] );
			}

			$pages = get_pages( $args );

			?>
			<div style="display:none;">
				<div id="loginform-shortcode">
					<p>
						<label for="loginform-redirect"><?php _e( 'Redirect to', 'icit_losh' ); ?></label>
						<select name="loginform_redirect" id="loginform-redirect" class="widefat">
							<option value="current"><?php _e( 'Current page', 'icit_losh' ); ?></option>
							<option value="custom"><?php _e( 'Custom link', 'icit_losh' ); ?></option>
							<?php if ( count( $pages ) ) { ?>
								<optgroup label="<?php _e( 'Pages', 'icit_losh' ); ?>">
								<?php foreach ( $pages as $page ) { ?>
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
						<label for="loginform-username-label"><?php _e( 'Username label', 'icit_losh' ); ?></label>
						<input id="loginform-username-label" type="text" name="loginform_username_label" value="" class="widefat" />
					</p>
					<p>
						<label for="loginform-password-label"><?php _e( 'Password label', 'icit_losh' ); ?></label>
						<input id="loginform-password-label" type="text" name="loginform_password_label" value="" class="widefat" />
					</p>
					<p>
						<label for="loginform-remember-label"><?php _e( 'Remember me label', 'icit_losh' ); ?></label>
						<input id="loginform-remember-label" type="text" name="loginform_remember_label" value="" class="widefat" />
					</p>
					<p>
						<label for="loginform-button-label"><?php _e( 'Button text', 'icit_losh' ); ?></label>
						<input id="loginform-button-label" type="text" name="loginform_button_label" value="" class="widefat" />
					</p>
					<p>
						<input type="submit" value="<?php _e( 'Insert into post', 'icit_losh' ); ?>" class="button-primary" />
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

			foreach ( $buttons as $button ) {
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
			$plugins['loggedinout'] = LOGIN_SHORTCODES_URL . '/js/editor_plugin.js';
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
			$user  = new WP_User( get_current_user_id() );

			if ( $role ) {
				$roles = explode( ',', $role );
			}

			$allowed_roles = array_intersect( $user->roles, $roles );

			if ( is_user_logged_in() && ( empty( $roles ) || ! empty( $allowed_roles ) ) ) {
				return do_shortcode( $content );
			}

			return '';
		}

		public function logged_out_shortcode( $attr, $content = null ) {

			if ( ! is_user_logged_in() ) {
				return do_shortcode( $content );
			}

			return '';
		}

		public function logged_in_popup() {
			global $wp_roles;

			auth_redirect();

			?><!doctype html>
<html lang="en">
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<title><?php _e( 'Insert logged in only container.', 'icit_losh' ); ?></title>

		<?php wp_print_scripts( 'icit-login-popup' ); ?>
		<?php wp_print_styles( 'icit-login-popup-css' ); ?>

	</head>
	<body style="display: none" class="wp-core-ui">
		<form id="logged-in-popup" tabindex="-1">
			<p><?php _e( 'Choose which user roles this content should be visible to. If none are selected any logged in user will be able to see it.', 'icit_losh' ); ?></p>
			<ul class="roles-list">
			<?php foreach ( $wp_roles->roles as $role => $data ) { ?>
				<li><label><input checked="checked" type="checkbox" name="logged_in_role[]" value="<?php esc_attr_e( $role ); ?>" /> <?php echo $data['name']; ?></label></li>
			<?php } ?>
			</ul>
			<div class="submitbox">
				<span id="logged-in-update">
					<input type="submit" tabindex="100" value="<?php esc_attr_e( 'Insert shortcode', 'icit_losh' ); ?>" class="button button-primary button-large" name="loggedin-submit" />
				</span>

				<span id="logged-in-cancel" class="alignright">
					<a class="submitdelete deletion button" href="#"><?php _e( 'Cancel', 'icit_losh' ); ?></a>
				</span>
			</div>

		</form>
	</body>
</html><?php

			die();
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

			if ( empty( $shortcode_tags ) || ! is_array( $shortcode_tags ) ) {
				return $pee;
			}

			$tagregexp = join( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );

			$pattern =
				'/'
				. '<(?:p|span)(?:[^>]*)>'            // Opening paragraph plus attributes
				. '\\s*+'                            // Optional leading whitespace
				. '('                                // 1: The shortcode
				. '\\[\\/?'                      // Opening/Closing bracket
				. "($tagregexp)"                 // 2: Shortcode name
				. '\\b'                          // Word boundary
				// Unroll the loop: Inside the opening shortcode tag
				. '[^\\]\\/]*'                   // Not a closing bracket or forward slash
				. '(?:'
				. '\\/(?!\\])'               // A forward slash not followed by a closing bracket
				. '[^\\]\\/]*'               // Not a closing bracket or forward slash
				. ')*?'
				. '(?:'
				. '\\/\\]'                   // Self closing tag and closing bracket
				. '|'
				. '\\]'                      // Closing bracket
				//.         '(?:'                      // Unroll the loop: Optionally, anything between the opening and closing shortcode tags
				//.             '[^\\[]*+'             // Not an opening bracket
				//.             '(?:'
				//.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
				//.                 '[^\\[]*+'         // Not an opening bracket
				//.             ')*+'
				//.             '\\[\\/\\2\\]'         // Closing shortcode tag
				//.         ')?'
				. ')'
				. ')'
				. '\\s*+'                            // optional trailing whitespace
				. '<\\/(?:p|span)>'                  // closing paragraph
				. '/s';

			return preg_replace( $pattern, '$1', $pee );
		}

	}

}


?>
