<?php
/**
 * WordPress User Page
 *
 * Handles authentication, registering, resetting passwords, forgot password,
 * and other user handling.
 *
 * @package WordPress
 */

require( __DIR__ . '/wp-load.php' );

function login_header( $title = 'Log In', $message = '', $wp_error = '' ) {
	global $error, $interim_login, $action;

	add_action( 'login_head', 'wp_no_robots' );

	if ( empty($wp_error) )
		$wp_error = new WP_Error();

	$shake_error_codes = array( 'empty_password', 'empty_email', 'invalid_email', 'invalidcombo', 'empty_username', 'invalid_username', 'incorrect_password' );
	$shake_error_codes = apply_filters( 'shake_error_codes', $shake_error_codes );

	if ( $shake_error_codes && $wp_error->get_error_code() && in_array( $wp_error->get_error_code(), $shake_error_codes ) )
		add_action( 'login_head', 'wp_shake_js', 12 );

	?>
	<!DOCTYPE html>
	<html xmlns="http://www.w3.org/1999/xhtml" lang="zh-CN">
	<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width" />
	<title><?php echo get_bloginfo( 'name', 'display' ) . ' &rsaquo; ' . $title; ?></title>
	<?php
	wp_enqueue_style( 'login' );
	if ( 'loggedout' == $wp_error->get_error_code() ) {
	?>
		<script>if("sessionStorage" in window){try{for(var key in sessionStorage){if(key.indexOf("wp-autosave-")!=-1){sessionStorage.removeItem(key)}}}catch(e){}};</script>
	<?php
	}

	do_action( 'login_enqueue_scripts' );
	do_action( 'login_head' );

	$classes = array( 'login-action-' . $action, 'wp-core-ui' );
	if ( wp_is_mobile() )
		$classes[] = 'mobile';
	if ( $interim_login ) {
		$classes[] = 'interim-login';
		?>
		<style type="text/css">html{background-color: transparent;}</style>
		<?php

		if ( 'success' ===  $interim_login )
			$classes[] = 'interim-login-success';
	}

	$classes = apply_filters( 'login_body_class', $classes, $action );

	?>
	</head>
	<body class="login <?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<div id="login">
		<h1><a href="#" tabindex="-1"><?php bloginfo( 'name' ); ?></a></h1>
	<?php

	$message = apply_filters( 'login_message', $message );
	if ( !empty( $message ) )
		echo $message . "\n";

	// In case a plugin uses $error rather than the $wp_errors object
	if ( !empty( $error ) ) {
		$wp_error->add('error', $error);
		unset($error);
	}

	if ( $wp_error->get_error_code() ) {
		$errors = '';
		$messages = '';
		foreach ( $wp_error->get_error_codes() as $code ) {
			$severity = $wp_error->get_error_data( $code );
			foreach ( $wp_error->get_error_messages( $code ) as $error_message ) {
				if ( 'message' == $severity )
					$messages .= '	' . $error_message . "<br />\n";
				else
					$errors .= '	' . $error_message . "<br />\n";
			}
		}
		if ( ! empty( $errors ) ) {
			echo '<div id="login_error">' . apply_filters( 'login_errors', $errors ) . "</div>\n";
		}
		if ( ! empty( $messages ) ) {
			echo '<p class="message">' . apply_filters( 'login_messages', $messages ) . "</p>\n";
		}
	}
}

function login_footer($input_id = '') {
	global $interim_login;

	// Don't allow interim logins to navigate away from the page.
	if ( ! $interim_login ): ?>
	<p id="backtoblog"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php printf( '&larr; Back to %s', get_bloginfo( 'title', 'display' ) ); ?></a></p>
	<?php endif; ?>

	</div>

	<?php if ( !empty($input_id) ) : ?>
	<script type="text/javascript">
	try{document.getElementById('<?php echo $input_id; ?>').focus();}catch(e){}
	if(typeof wpOnload=='function')wpOnload();
	</script>
	<?php endif; ?>

	<?php do_action( 'login_footer' ); ?>
	<div class="clear"></div>
	</body>
	</html>
	<?php
}

function wp_shake_js() {
	if ( wp_is_mobile() )
		return;
?>
<script type="text/javascript">
addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
function s(id,pos){g(id).left=pos+'px';}
function g(id){return document.getElementById(id).style;}
function shake(id,a,d){c=a.shift();s(id,c);if(a.length>0){setTimeout(function(){shake(id,a,d);},d);}else{try{g(id).position='static';wp_attempt_focus();}catch(e){}}}
addLoadEvent(function(){ var p=new Array(15,30,15,0,-15,-30,-15,0);p=p.concat(p.concat(p));var i=document.forms[0].id;g(i).position='relative';shake(i,p,20);});
</script>
<?php
}

function retrieve_password() {
	global $wpdb, $wp_hasher;

	$errors = new WP_Error();

	if ( empty( $_POST['user_login'] ) ) {
		$errors->add('empty_username', '<strong>ERROR</strong>: Enter a username or email address.');
	} elseif ( strpos( $_POST['user_login'], '@' ) ) {
		$user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );
		if ( empty( $user_data ) )
			$errors->add('invalid_email', '<strong>ERROR</strong>: There is no user registered with that email address.');
	} else {
		$login = trim($_POST['user_login']);
		$user_data = get_user_by('login', $login);
	}

	do_action( 'lostpassword_post', $errors );

	if ( $errors->get_error_code() )
		return $errors;

	if ( !$user_data ) {
		$errors->add('invalidcombo', '<strong>ERROR</strong>: Invalid username or email.');
		return $errors;
	}

	$user_login = $user_data->user_login;
	$user_email = $user_data->user_email;
	$key = get_password_reset_key( $user_data );

	if ( is_wp_error( $key ) ) {
		return $key;
	}

	$message = "Someone has requested a password reset for the following account:\r\n\r\n";
	$message .= network_home_url( '/' ) . "\r\n\r\n";
	$message .= sprintf('Username: %s', $user_login) . "\r\n\r\n";
	$message .= "If this was a mistake, just ignore this email and nothing will happen.\r\n\r\n";
	$message .= "To reset your password, visit the following address:\r\n\r\n";
	$message .= network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . "\r\n";

	$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

	$title = sprintf( '[%s] Password Reset', $blogname );

	$title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );

	$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

	if ( $message && !wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) )
		wp_die( 'The email could not be sent.' . "<br />\n" . 'Possible reason: your host may have disabled the mail() function.' );

	return true;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
$errors = new WP_Error();

if ( isset($_GET['key']) )
	$action = 'resetpass';

if ( !in_array( $action, array( 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'login' ), true ) && false === has_filter( 'login_form_' . $action ) )
	$action = 'login';

nocache_headers();

header('Content-Type: '.get_bloginfo('html_type').'; charset='.get_bloginfo('charset'));

if ( defined( 'RELOCATE' ) && RELOCATE ) {
	if ( isset( $_SERVER['PATH_INFO'] ) && ($_SERVER['PATH_INFO'] != $_SERVER['PHP_SELF']) )
		$_SERVER['PHP_SELF'] = str_replace( $_SERVER['PATH_INFO'], '', $_SERVER['PHP_SELF'] );

	$url = dirname( set_url_scheme( 'http://' .  $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] ) );
	if ( $url != get_option( 'siteurl' ) )
		update_option( 'siteurl', $url );
}

$secure = ( 'https' === parse_url( wp_login_url(), PHP_URL_SCHEME ) );
setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN, $secure );
if ( SITECOOKIEPATH != COOKIEPATH )
	setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN, $secure );

do_action( 'login_init' );

do_action( 'login_form_' . $action );

$http_post = ('POST' == $_SERVER['REQUEST_METHOD']);
$interim_login = isset($_REQUEST['interim-login']);

switch ($action) {

case 'postpass' :
	if ( ! array_key_exists( 'post_password', $_POST ) ) {
		wp_safe_redirect( wp_get_referer() );
		exit();
	}

	require_once ABSPATH . WPINC . '/class-phpass.php';
	$hasher = new PasswordHash( 8, true );

	$expire = apply_filters( 'post_password_expires', time() + 10 * DAY_IN_SECONDS );
	$referer = wp_get_referer();
	if ( $referer ) {
		$secure = ( 'https' === parse_url( $referer, PHP_URL_SCHEME ) );
	} else {
		$secure = false;
	}
	setcookie( 'wp-postpass_' . COOKIEHASH, $hasher->HashPassword( wp_unslash( $_POST['post_password'] ) ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );

	wp_safe_redirect( wp_get_referer() );
	exit();

case 'logout' :
	check_admin_referer('log-out');

	$user = wp_get_current_user();

	wp_logout();

	if ( ! empty( $_REQUEST['redirect_to'] ) ) {
		$redirect_to = $requested_redirect_to = $_REQUEST['redirect_to'];
	} else {
		$redirect_to = 'wp-login.php?loggedout=true';
		$requested_redirect_to = '';
	}

	$redirect_to = apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $user );
	wp_safe_redirect( $redirect_to );
	exit();

case 'lostpassword' :
case 'retrievepassword' :

	if ( $http_post ) {
		$errors = retrieve_password();
		if ( !is_wp_error($errors) ) {
			$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : 'wp-login.php?checkemail=confirm';
			wp_safe_redirect( $redirect_to );
			exit();
		}
	}

	if ( isset( $_GET['error'] ) ) {
		if ( 'invalidkey' == $_GET['error'] ) {
			$errors->add( 'invalidkey', __( 'Your password reset link appears to be invalid. Please request a new link below.' ) );
		} elseif ( 'expiredkey' == $_GET['error'] ) {
			$errors->add( 'expiredkey', __( 'Your password reset link has expired. Please request a new link below.' ) );
		}
	}

	$lostpassword_redirect = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';

	$redirect_to = apply_filters( 'lostpassword_redirect', $lostpassword_redirect );

	do_action( 'lost_password' );

	login_header('Lost Password', '<p class="message">Please enter your username or email address. You will receive a link to create a new password via email.</p>', $errors);

	$user_login = isset($_POST['user_login']) ? wp_unslash($_POST['user_login']) : '';

?>

<form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url( network_site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ); ?>" method="post">
	<p>
		<label for="user_login" >用户名或邮箱<br />
		<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr($user_login); ?>" size="20" /></label>
	</p>
	<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="获取新密码" /></p>
</form>

<p id="nav">
<a href="<?php echo esc_url( wp_login_url() ); ?>">登录</a>
<?php
if ( get_option( 'users_can_register' ) ) :
	$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), '注册' );
	echo ' | ' . apply_filters( 'register', $registration_url );
endif;
?>
</p>

<?php
login_footer('user_login');
break;

case 'resetpass' :
case 'rp' :
	list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$rp_cookie = 'wp-resetpass-' . COOKIEHASH;
	if ( isset( $_GET['key'] ) ) {
		$value = sprintf( '%s:%s', wp_unslash( $_GET['login'] ), wp_unslash( $_GET['key'] ) );
		setcookie( $rp_cookie, $value, 0, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
		wp_safe_redirect( remove_query_arg( array( 'key', 'login' ) ) );
		exit;
	}

	if ( isset( $_COOKIE[ $rp_cookie ] ) && 0 < strpos( $_COOKIE[ $rp_cookie ], ':' ) ) {
		list( $rp_login, $rp_key ) = explode( ':', wp_unslash( $_COOKIE[ $rp_cookie ] ), 2 );
		$user = check_password_reset_key( $rp_key, $rp_login );
		if ( isset( $_POST['pass1'] ) && ! hash_equals( $rp_key, $_POST['rp_key'] ) ) {
			$user = false;
		}
	} else {
		$user = false;
	}

	if ( ! $user || is_wp_error( $user ) ) {
		setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
		if ( $user && $user->get_error_code() === 'expired_key' )
			wp_redirect( site_url( 'wp-login.php?action=lostpassword&error=expiredkey' ) );
		else
			wp_redirect( site_url( 'wp-login.php?action=lostpassword&error=invalidkey' ) );
		exit;
	}

	$errors = new WP_Error();

	if ( isset($_POST['pass1']) && $_POST['pass1'] != $_POST['pass2'] )
		$errors->add( 'password_reset_mismatch', 'The passwords do not match.' );

	do_action( 'validate_password_reset', $errors, $user );

	if ( ( ! $errors->get_error_code() ) && isset( $_POST['pass1'] ) && !empty( $_POST['pass1'] ) ) {
		reset_password($user, $_POST['pass1']);
		setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
		login_header( __( 'Password Reset' ), '<p class="message reset-pass">' . __( 'Your password has been reset.' ) . ' <a href="' . esc_url( wp_login_url() ) . '">' . __( 'Log in' ) . '</a></p>' );
		login_footer();
		exit;
	}

	wp_enqueue_script('utils');
	wp_enqueue_script('user-profile');

	login_header('Reset Password', '<p class="message reset-pass">Enter your new password below.</p>', $errors );

?>
<form name="resetpassform" id="resetpassform" action="<?php echo esc_url( network_site_url( 'wp-login.php?action=resetpass', 'login_post' ) ); ?>" method="post" autocomplete="off">
	<input type="hidden" id="user_login" value="<?php echo esc_attr( $rp_login ); ?>" autocomplete="off" />

	<div class="user-pass1-wrap">
		<p>
			<label for="pass1">New password</label>
		</p>

		<div class="wp-pwd">
			<span class="password-input-wrapper">
				<input type="password" data-reveal="1" data-pw="<?php echo esc_attr( wp_generate_password( 16 ) ); ?>" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="off" aria-describedby="pass-strength-result" />
			</span>
			<div id="pass-strength-result" class="hide-if-no-js" aria-live="polite">Strength indicator</div>
		</div>
	</div>

	<p class="user-pass2-wrap">
		<label for="pass2">Confirm new password</label><br />
		<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" />
	</p>

	<p class="description indicator-hint"><?php echo wp_get_password_hint(); ?></p>
	<br class="clear" />

	<?php do_action( 'resetpass_form', $user ); ?>
	<input type="hidden" name="rp_key" value="<?php echo esc_attr( $rp_key ); ?>" />
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Reset Password" /></p>
</form>

<p id="nav">
<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a>
<?php
if ( get_option( 'users_can_register' ) ) :
	$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), 'Register' );

	/** This filter is documented in wp-includes/general-template.php */
	echo ' | ' . apply_filters( 'register', $registration_url );
endif;
?>
</p>

<?php
login_footer('user_pass');
break;

case 'register' :
	if ( !get_option('users_can_register') ) {
		wp_redirect( site_url('wp-login.php?registration=disabled') );
		exit();
	}

	$user_login = '';
	$user_email = '';
	if ( $http_post ) {
		$user_login = isset( $_POST['user_login'] ) ? $_POST['user_login'] : '';
		$user_email = isset( $_POST['user_email'] ) ? $_POST['user_email'] : '';
		$errors = register_new_user($user_login, $user_email);
		if ( !is_wp_error($errors) ) {
			$redirect_to = !empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : 'wp-login.php?checkemail=registered';
			wp_safe_redirect( $redirect_to );
			exit();
		}
	}

	$registration_redirect = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';

	$redirect_to = apply_filters( 'registration_redirect', $registration_redirect );
	login_header('Registration Form', '<p class="message register">Register For This Site</p>', $errors);
?>
<form name="registerform" id="registerform" action="<?php echo esc_url( site_url( 'wp-login.php?action=register', 'login_post' ) ); ?>" method="post" novalidate="novalidate">
	<p>
		<label for="user_login">用户名<br />
		<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr(wp_unslash($user_login)); ?>" size="20" /></label>
	</p>
	<p>
		<label for="user_email">邮箱<br />
		<input type="email" name="user_email" id="user_email" class="input" value="<?php echo esc_attr( wp_unslash( $user_email ) ); ?>" size="25" /></label>
	</p>
	<p id="reg_passmail">注册确认信将会被寄给您。</p>
	<br class="clear" />
	<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="注册" /></p>
</form>

<p id="nav">
<a href="<?php echo esc_url( wp_login_url() ); ?>">登录</a> |
<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">忘记密码？</a>
</p>

<?php
login_footer('user_login');
break;

case 'login' :
default :
	$secure_cookie = '';
	$customize_login = isset( $_REQUEST['customize-login'] );
	if ( $customize_login )
		wp_enqueue_script( 'customize-base' );

	if ( !empty($_POST['log']) && !force_ssl_admin() ) {
		$user_name = sanitize_user($_POST['log']);
		$user = get_user_by( 'login', $user_name );

		if ( ! $user && strpos( $user_name, '@' ) ) {
			$user = get_user_by( 'email', $user_name );
		}

		if ( $user ) {
			if ( get_user_option('use_ssl', $user->ID) ) {
				$secure_cookie = true;
				force_ssl_admin(true);
			}
		}
	}

	if ( isset( $_REQUEST['redirect_to'] ) ) {
		$redirect_to = $_REQUEST['redirect_to'];
		if ( $secure_cookie && false !== strpos($redirect_to, 'wp-admin') )
			$redirect_to = preg_replace('|^http://|', 'https://', $redirect_to);
	} else {
		$redirect_to = admin_url();
	}

	$reauth = empty($_REQUEST['reauth']) ? false : true;

	$user = wp_signon( '', $secure_cookie );

	if ( empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
		if ( headers_sent() ) {
			$user = new WP_Error( 'test_cookie', sprintf( '<strong>ERROR</strong>: Cookies are blocked due to unexpected output. For help, please see <a href="%1$s">this documentation</a> or try the <a href="%2$s">support forums</a>.',
				'https://codex.wordpress.org/Cookies', 'https://wordpress.org/support/' ) );
		} elseif ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[ TEST_COOKIE ] ) ) {
			$user = new WP_Error( 'test_cookie', sprintf( '<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href="%s">enable cookies</a> to use WordPress.',
				'https://codex.wordpress.org/Cookies' ) );
		}
	}

	$requested_redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';

	$redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user );

	if ( !is_wp_error($user) && !$reauth ) {
		if ( $interim_login ) {
			$message = '<p class="message">You have logged in successfully.</p>';
			$interim_login = 'success';
			login_header( '', $message ); ?>
			</div>
			<?php
			do_action( 'login_footer' ); ?>
			<?php if ( $customize_login ) : ?>
				<script type="text/javascript">setTimeout( function(){ new wp.customize.Messenger({ url: '<?php echo wp_customize_url(); ?>', channel: 'login' }).send('login') }, 1000 );</script>
			<?php endif; ?>
			</body></html>
<?php		exit;
		}

		if ( ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) ) {
			if ( !$user->has_cap('edit_posts') )
				$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();

			wp_redirect( $redirect_to );
			exit();
		}
		wp_safe_redirect($redirect_to);
		exit();
	}

	$errors = $user;

	if ( !empty($_GET['loggedout']) || $reauth )
		$errors = new WP_Error();

	if ( $interim_login ) {
		if ( ! $errors->get_error_code() )
			$errors->add( 'expired', 'Your session has expired. Please log in to continue where you left off.', 'message' );
	} else {
		if	( isset($_GET['loggedout']) && true == $_GET['loggedout'] )
			$errors->add('loggedout', 'You are now logged out.', 'message');
		elseif	( isset($_GET['registration']) && 'disabled' == $_GET['registration'] )
			$errors->add('registerdisabled', 'User registration is currently not allowed.');
		elseif	( isset($_GET['checkemail']) && 'confirm' == $_GET['checkemail'] )
			$errors->add('confirm', 'Check your email for the confirmation link.', 'message');
		elseif	( isset($_GET['checkemail']) && 'newpass' == $_GET['checkemail'] )
			$errors->add('newpass', 'Check your email for your new password.', 'message');
		elseif	( isset($_GET['checkemail']) && 'registered' == $_GET['checkemail'] )
			$errors->add('registered', 'Registration complete. Please check your email.', 'message');
		elseif ( strpos( $redirect_to, 'about.php?updated' ) )
			$errors->add('updated', '<strong>You have successfully updated WordPress!</strong> Please log back in to see what&#8217;s new.', 'message' );
	}

	$errors = apply_filters( 'wp_login_errors', $errors, $redirect_to );

	if ( $reauth )
		wp_clear_auth_cookie();

	login_header('Log In', '', $errors);

	if ( isset($_POST['log']) )
		$user_login = ( 'incorrect_password' == $errors->get_error_code() || 'empty_password' == $errors->get_error_code() ) ? esc_attr(wp_unslash($_POST['log'])) : '';
	$rememberme = ! empty( $_POST['rememberme'] );

	if ( ! empty( $errors->errors ) ) {
		$aria_describedby_error = ' aria-describedby="login_error"';
	} else {
		$aria_describedby_error = '';
	}
?>
<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
	<p>
		<label for="user_login">用户名或邮箱<br />
		<input type="text" name="log" id="user_login"<?php echo $aria_describedby_error; ?> class="input" value="<?php echo esc_attr( $user_login ); ?>" size="20" /></label>
	</p>
	<p>
		<label for="user_pass">密码<br />
		<input type="password" name="pwd" id="user_pass"<?php echo $aria_describedby_error; ?> class="input" value="" size="20" /></label>
	</p>
	<p class="forgetmenot"><label for="rememberme"><input name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked( $rememberme ); ?> /> 记住我的登录信息</label></p>
	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="登录" />
	<?php if ( $interim_login ) { ?>
		<input type="hidden" name="interim-login" value="1" />
	<?php } else { ?>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
	<?php } ?>
	<?php if ( $customize_login ) : ?>
		<input type="hidden" name="customize-login" value="1" />
	<?php endif; ?>
		<input type="hidden" name="testcookie" value="1" />
	</p>
</form>

<?php if ( ! $interim_login ) { ?>
<p id="nav">
<?php if ( ! isset( $_GET['checkemail'] ) || ! in_array( $_GET['checkemail'], array( 'confirm', 'newpass' ) ) ) :
	if ( get_option( 'users_can_register' ) ) :
		$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), '注册' );
		echo apply_filters( 'register', $registration_url ) . ' | ';
	endif;
	?>
	<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">忘记密码？</a>
<?php endif; ?>
</p>
<?php } ?>

<script type="text/javascript">
function wp_attempt_focus(){
setTimeout( function(){ try{
<?php if ( $user_login ) { ?>
d = document.getElementById('user_pass');
d.value = '';
<?php } else { ?>
d = document.getElementById('user_login');
<?php if ( 'invalid_username' == $errors->get_error_code() ) { ?>
if( d.value != '' )
d.value = '';
<?php
}
}?>
d.focus();
d.select();
} catch(e){}
}, 200);
}

<?php if ( !$error ) { ?>
wp_attempt_focus();
<?php } ?>
if(typeof wpOnload=='function')wpOnload();
<?php if ( $interim_login ) { ?>
(function(){
try {
	var i, links = document.getElementsByTagName('a');
	for ( i in links ) {
		if ( links[i].href )
			links[i].target = '_blank';
	}
} catch(e){}
}());
<?php } ?>
</script>

<?php
login_footer();
break;
}
