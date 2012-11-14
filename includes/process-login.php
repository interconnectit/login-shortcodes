<?php

/**
 * Taken from wp-login.php v3.4.1
 *
 * Process the login and get the error message but pass them back to our calling form shortcode
 */

class login_shortcodes_processing {

   function process() {

		$secure_cookie = '';
		$interim_login = isset($_REQUEST['interim-login']);
		$customize_login = isset( $_REQUEST['customize-login'] );
		if ( $customize_login )
			wp_enqueue_script( 'customize-base' );

		// If the user wants ssl but the session is not ssl, force a secure cookie.
		if ( !empty($_POST['log']) && !force_ssl_admin() ) {
			$user_name = sanitize_user($_POST['log']);
			if ( $user = get_user_by('login', $user_name) ) {
				if ( get_user_option('use_ssl', $user->ID) ) {
					$secure_cookie = true;
					force_ssl_admin(true);
				}
			}
		}

	   if ( isset( $_REQUEST['redirect_to'] ) ) {
		   $redirect_to = $_REQUEST['redirect_to'];
		   // Redirect to https if user wants ssl
		   if ( $secure_cookie && false !== strpos($redirect_to, 'wp-admin') )
			   $redirect_to = preg_replace('|^http://|', 'https://', $redirect_to);
	   } else {
		   $redirect_to = admin_url();
	   }

	   $reauth = empty($_REQUEST['reauth']) ? false : true;

	   // If the user was redirected to a secure login form from a non-secure admin page, and secure login is required but secure admin is not, then don't use a secure
	   // cookie and redirect back to the referring non-secure admin page. This allows logins to always be POSTed over SSL while allowing the user to choose visiting
	   // the admin via http or https.
	   if ( !$secure_cookie && is_ssl() && force_ssl_login() && !force_ssl_admin() && ( 0 !== strpos($redirect_to, 'https') ) && ( 0 === strpos($redirect_to, 'http') ) )
		   $secure_cookie = false;

	   $user = wp_signon( '', $secure_cookie );

	   $redirect_to = apply_filters('login_redirect', $redirect_to, isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '', $user);

	   if ( !is_wp_error($user) && !$reauth ) {
			if ( $interim_login ) {
				$message = '<p class="message">' . __('You have logged in successfully.') . '</p>';
				login_header( '', $message ); ?>

				<?php if ( ! $customize_login ) : ?>
				<script type="text/javascript">setTimeout( function(){window.close()}, 8000);</script>
				<p class="alignright">
				<input type="button" class="button-primary" value="<?php esc_attr_e('Close'); ?>" onclick="window.close()" /></p>
				<?php endif; ?>
				</div>
				<?php do_action( 'login_footer' ); ?>
				<?php if ( $customize_login ) : ?>
					<script type="text/javascript">setTimeout( function(){ new wp.customize.Messenger({ url: '<?php echo wp_customize_url(); ?>', channel: 'login' }).send('login') }, 1000 );</script>
				<?php endif; ?>
				</body></html>
	<?php		exit;
			}

			if ( ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( is_multisite() && !get_active_blog_for_user($user->ID) && !is_super_admin( $user->ID ) )
					$redirect_to = user_admin_url();
				elseif ( is_multisite() && !$user->has_cap('read') )
					$redirect_to = get_dashboard_url( $user->ID );
				elseif ( !$user->has_cap('edit_posts') )
					$redirect_to = admin_url('profile.php');
			}
			wp_safe_redirect($redirect_to);
			exit();
		}

		$errors = $user;
		// Clear errors if loggedout is set.
		if ( ! empty( $_GET[ 'loggedout' ] ) || $reauth )
			$errors = new WP_Error();

		// If cookies are disabled we can't log in even with a valid user+pass
		if ( isset($_POST['testcookie']) && empty($_COOKIE[TEST_COOKIE]) )
			$errors->add('test_cookie', __("<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href='http://www.google.com/cookies.html'>enable cookies</a> to use WordPress."));

		// Some parts of this script use the main login form to display a message
		if		( isset($_GET['loggedout']) && true == $_GET['loggedout'] )
			$errors->add('loggedout', __('You are now logged out.'), 'message');
		elseif	( isset($_GET['registration']) && 'disabled' == $_GET['registration'] )
			$errors->add('registerdisabled', __('User registration is currently not allowed.'));
		elseif	( isset($_GET['checkemail']) && 'confirm' == $_GET['checkemail'] )
			$errors->add('confirm', __('Check your e-mail for the confirmation link.'), 'message');
		elseif	( isset($_GET['checkemail']) && 'newpass' == $_GET['checkemail'] )
			$errors->add('newpass', __('Check your e-mail for your new password.'), 'message');
		elseif	( isset($_GET['checkemail']) && 'registered' == $_GET['checkemail'] )
			$errors->add('registered', __('Registration complete. Please check your e-mail.'), 'message');
		elseif	( $interim_login )
			$errors->add('expired', __('Your session has expired. Please log-in again.'), 'message');
		elseif ( strpos( $redirect_to, 'about.php?updated' ) )
			$errors->add('updated', __( '<strong>You have successfully updated WordPress!</strong> Please log back in to experience the awesomeness.' ), 'message' );

		// Clear any stale cookies.
		if ( $reauth )
			wp_clear_auth_cookie();

		if ( isset($_POST['log']) )
			$user_login = ( 'incorrect_password' == $errors->get_error_code() || 'empty_password' == $errors->get_error_code() ) ? esc_attr(stripslashes($_POST['log'])) : '';

		 // return errors to return to orginating page if login failed
		return array( 'errors' => $errors, 'redirect' => $redirect_to );
	}

}

?>
