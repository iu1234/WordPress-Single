<?php
/**
 * These functions can be replaced via plugins. If plugins do not redefine these
 * functions, then these will be used instead.
 *
 * @package WordPress
 */

if ( !function_exists('wp_set_current_user') ) :
function wp_set_current_user($id, $name = '') {
	global $current_user;
	if ( isset( $current_user )
		&& ( $current_user instanceof WP_User )
		&& ( $id == $current_user->ID )
		&& ( null !== $id )
	) {
		return $current_user;
	}

	$current_user = new WP_User( $id, $name );

	setup_userdata( $current_user->ID );

	do_action( 'set_current_user' );

	return $current_user;
}
endif;

if ( !function_exists('wp_get_current_user') ) :
function wp_get_current_user() {
	return _wp_get_current_user();
}
endif;

if ( !function_exists('get_user_by') ) :
function get_user_by( $field, $value ) {
	$userdata = WP_User::get_data_by( $field, $value );
	if ( !$userdata )
		return false;
	$user = new WP_User;
	$user->init( $userdata );
	return $user;
}
endif;

if ( !function_exists('cache_users') ) :
function cache_users( $user_ids ) {
	global $wpdb;

	$clean = _get_non_cached_ids( $user_ids, 'users' );

	if ( empty( $clean ) )
		return;

	$list = implode( ',', $clean );
	$users = $wpdb->get_results( "SELECT * FROM $wpdb->users WHERE ID IN ($list)" );
	$ids = array();
	foreach ( $users as $user ) {
		update_user_caches( $user );
		$ids[] = $user->ID;
	}
	update_meta_cache( 'user', $ids );
}
endif;

if ( !function_exists( 'wp_mail' ) ) :
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	$atts = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );

	if ( isset( $atts['to'] ) ) {
		$to = $atts['to'];
	}
	if ( isset( $atts['subject'] ) ) {
		$subject = $atts['subject'];
	}
	if ( isset( $atts['message'] ) ) {
		$message = $atts['message'];
	}
	if ( isset( $atts['headers'] ) ) {
		$headers = $atts['headers'];
	}
	if ( isset( $atts['attachments'] ) ) {
		$attachments = $atts['attachments'];
	}
	if ( ! is_array( $attachments ) ) {
		$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
	}
	global $phpmailer;

	if ( ! ( $phpmailer instanceof PHPMailer ) ) {
		require_once ABSPATH . WPINC . '/class-phpmailer.php';
		require_once ABSPATH . WPINC . '/class-smtp.php';
		$phpmailer = new PHPMailer( true );
	}

	if ( empty( $headers ) ) {
		$headers = array();
	} else {
		if ( !is_array( $headers ) ) {
			$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		} else {
			$tempheaders = $headers;
		}
		$headers = array();
		$cc = array();
		$bcc = array();

		if ( !empty( $tempheaders ) ) {
			foreach ( (array) $tempheaders as $header ) {
				if ( strpos($header, ':') === false ) {
					if ( false !== stripos( $header, 'boundary=' ) ) {
						$parts = preg_split('/boundary=/i', trim( $header ) );
						$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
					}
					continue;
				}
				// Explode them out
				list( $name, $content ) = explode( ':', trim( $header ), 2 );

				// Cleanup crew
				$name    = trim( $name    );
				$content = trim( $content );

				switch ( strtolower( $name ) ) {
					// Mainly for legacy -- process a From: header if it's there
					case 'from':
						$bracket_pos = strpos( $content, '<' );
						if ( $bracket_pos !== false ) {
							// Text before the bracketed email is the "From" name.
							if ( $bracket_pos > 0 ) {
								$from_name = substr( $content, 0, $bracket_pos - 1 );
								$from_name = str_replace( '"', '', $from_name );
								$from_name = trim( $from_name );
							}

							$from_email = substr( $content, $bracket_pos + 1 );
							$from_email = str_replace( '>', '', $from_email );
							$from_email = trim( $from_email );

						// Avoid setting an empty $from_email.
						} elseif ( '' !== trim( $content ) ) {
							$from_email = trim( $content );
						}
						break;
					case 'content-type':
						if ( strpos( $content, ';' ) !== false ) {
							list( $type, $charset_content ) = explode( ';', $content );
							$content_type = trim( $type );
							if ( false !== stripos( $charset_content, 'charset=' ) ) {
								$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset_content ) );
							} elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
								$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content ) );
								$charset = '';
							}

						// Avoid setting an empty $content_type.
						} elseif ( '' !== trim( $content ) ) {
							$content_type = trim( $content );
						}
						break;
					case 'cc':
						$cc = array_merge( (array) $cc, explode( ',', $content ) );
						break;
					case 'bcc':
						$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
						break;
					default:
						// Add it to our grand headers array
						$headers[trim( $name )] = trim( $content );
						break;
				}
			}
		}
	}

	$phpmailer->ClearAllRecipients();
	$phpmailer->ClearAttachments();
	$phpmailer->ClearCustomHeaders();
	$phpmailer->ClearReplyTos();

	if ( !isset( $from_name ) )
		$from_name = 'WordPress';

	if ( !isset( $from_email ) ) {
		$sitename = strtolower( $_SERVER['SERVER_NAME'] );
		if ( substr( $sitename, 0, 4 ) == 'www.' ) {
			$sitename = substr( $sitename, 4 );
		}

		$from_email = 'wordpress@' . $sitename;
	}

	$phpmailer->From = apply_filters( 'wp_mail_from', $from_email );

	$phpmailer->FromName = apply_filters( 'wp_mail_from_name', $from_name );

	if ( !is_array( $to ) )
		$to = explode( ',', $to );

	foreach ( (array) $to as $recipient ) {
		try {
			// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
			$recipient_name = '';
			if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
				if ( count( $matches ) == 3 ) {
					$recipient_name = $matches[1];
					$recipient = $matches[2];
				}
			}
			$phpmailer->AddAddress( $recipient, $recipient_name);
		} catch ( phpmailerException $e ) {
			continue;
		}
	}

	// Set mail's subject and body
	$phpmailer->Subject = $subject;
	$phpmailer->Body    = $message;

	// Add any CC and BCC recipients
	if ( !empty( $cc ) ) {
		foreach ( (array) $cc as $recipient ) {
			try {
				// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
				$recipient_name = '';
				if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
					if ( count( $matches ) == 3 ) {
						$recipient_name = $matches[1];
						$recipient = $matches[2];
					}
				}
				$phpmailer->AddCc( $recipient, $recipient_name );
			} catch ( phpmailerException $e ) {
				continue;
			}
		}
	}

	if ( !empty( $bcc ) ) {
		foreach ( (array) $bcc as $recipient) {
			try {
				// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
				$recipient_name = '';
				if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
					if ( count( $matches ) == 3 ) {
						$recipient_name = $matches[1];
						$recipient = $matches[2];
					}
				}
				$phpmailer->AddBcc( $recipient, $recipient_name );
			} catch ( phpmailerException $e ) {
				continue;
			}
		}
	}

	$phpmailer->IsMail();

	if ( !isset( $content_type ) )
		$content_type = 'text/plain';

	$content_type = apply_filters( 'wp_mail_content_type', $content_type );

	$phpmailer->ContentType = $content_type;

	// Set whether it's plaintext, depending on $content_type
	if ( 'text/html' == $content_type )
		$phpmailer->IsHTML( true );

	// If we don't have a charset from the input headers
	if ( !isset( $charset ) )
		$charset = get_bloginfo( 'charset' );

	$phpmailer->CharSet = apply_filters( 'wp_mail_charset', $charset );

	if ( !empty( $headers ) ) {
		foreach ( (array) $headers as $name => $content ) {
			$phpmailer->AddCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
		}

		if ( false !== stripos( $content_type, 'multipart' ) && ! empty($boundary) )
			$phpmailer->AddCustomHeader( sprintf( "Content-Type: %s;\n\t boundary=\"%s\"", $content_type, $boundary ) );
	}

	if ( !empty( $attachments ) ) {
		foreach ( $attachments as $attachment ) {
			try {
				$phpmailer->AddAttachment($attachment);
			} catch ( phpmailerException $e ) {
				continue;
			}
		}
	}

	do_action_ref_array( 'phpmailer_init', array( &$phpmailer ) );

	try {
		return $phpmailer->Send();
	} catch ( phpmailerException $e ) {
		$mail_error_data = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
 		do_action( 'wp_mail_failed', new WP_Error( $e->getCode(), $e->getMessage(), $mail_error_data ) );
		return false;
	}
}
endif;

if ( !function_exists('wp_authenticate') ) :
function wp_authenticate($username, $password) {
	$username = sanitize_user($username);
	$password = trim($password);
	$user = apply_filters( 'authenticate', null, $username, $password );
	if ( $user == null ) {
		$user = new WP_Error( 'authentication_failed', '<strong>ERROR</strong>: Invalid username, email address or incorrect password.' );
	}
	$ignore_codes = array('empty_username', 'empty_password');
	if (is_wp_error($user) && !in_array($user->get_error_code(), $ignore_codes) ) {
		do_action( 'wp_login_failed', $username );
	}
	return $user;
}
endif;

if ( !function_exists('wp_logout') ) :
function wp_logout() {
	wp_destroy_current_session();
	wp_clear_auth_cookie();
	do_action( 'wp_logout' );
}
endif;

if ( !function_exists('wp_validate_auth_cookie') ) :
function wp_validate_auth_cookie($cookie = '', $scheme = '') {
	if ( ! $cookie_elements = wp_parse_auth_cookie($cookie, $scheme) ) {
		do_action( 'auth_cookie_malformed', $cookie, $scheme );
		return false;
	}

	$scheme = $cookie_elements['scheme'];
	$username = $cookie_elements['username'];
	$hmac = $cookie_elements['hmac'];
	$token = $cookie_elements['token'];
	$expired = $expiration = $cookie_elements['expiration'];

	if ( defined('DOING_AJAX') || 'POST' == $_SERVER['REQUEST_METHOD'] ) {
		$expired += 3600;
	}

	if ( $expired < time() ) {
		do_action( 'auth_cookie_expired', $cookie_elements );
		return false;
	}

	$user = get_user_by('login', $username);
	if ( ! $user ) {
		do_action( 'auth_cookie_bad_username', $cookie_elements );
		return false;
	}

	$pass_frag = substr($user->user_pass, 8, 4);

	$key = wp_hash( $username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme );

	$algo = function_exists( 'hash' ) ? 'sha256' : 'sha1';
	$hash = hash_hmac( $algo, $username . '|' . $expiration . '|' . $token, $key );

	if ( ! hash_equals( $hash, $hmac ) ) {
		do_action( 'auth_cookie_bad_hash', $cookie_elements );
		return false;
	}

	$manager = WP_Session_Tokens::get_instance( $user->ID );
	if ( ! $manager->verify( $token ) ) {
		do_action( 'auth_cookie_bad_session_token', $cookie_elements );
		return false;
	}

	if ( $expiration < time() ) {
		$GLOBALS['login_grace_period'] = 1;
	}

	do_action( 'auth_cookie_valid', $cookie_elements, $user );

	return $user->ID;
}
endif;

if ( !function_exists('wp_generate_auth_cookie') ) :
function wp_generate_auth_cookie( $user_id, $expiration, $scheme = 'auth', $token = '' ) {
	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) { return ''; }
	if ( ! $token ) {
		$manager = WP_Session_Tokens::get_instance( $user_id );
		$token = $manager->create( $expiration );
	}
	$pass_frag = substr($user->user_pass, 8, 4);
	$key = wp_hash( $user->user_login . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme );
	$hash = hash_hmac( 'sha256', $user->user_login . '|' . $expiration . '|' . $token, $key );
	$cookie = $user->user_login . '|' . $expiration . '|' . $token . '|' . $hash;
	return apply_filters( 'auth_cookie', $cookie, $user_id, $expiration, $scheme, $token );
}
endif;

if ( !function_exists('wp_parse_auth_cookie') ) :
function wp_parse_auth_cookie($cookie = '', $scheme = '') {
	if ( empty($cookie) ) {
		switch ($scheme){
			case 'auth':
				$cookie_name = AUTH_COOKIE;
				break;
			case 'secure_auth':
				$cookie_name = SECURE_AUTH_COOKIE;
				break;
			case "logged_in":
				$cookie_name = LOGGED_IN_COOKIE;
				break;
			default:
				$cookie_name = AUTH_COOKIE;
				$scheme = 'auth';
	    }

		if ( empty($_COOKIE[$cookie_name]) )
			return false;
		$cookie = $_COOKIE[$cookie_name];
	}

	$cookie_elements = explode('|', $cookie);
	if ( count( $cookie_elements ) !== 4 ) {
		return false;
	}
	list( $username, $expiration, $token, $hmac ) = $cookie_elements;
	return compact( 'username', 'expiration', 'token', 'hmac', 'scheme' );
}
endif;

if ( !function_exists('wp_set_auth_cookie') ) :
function wp_set_auth_cookie( $user_id, $remember = false, $token = '' ) {
	if ( $remember ) {
		$expiration = time() + apply_filters( 'auth_cookie_expiration', 14 * 86400, $user_id, $remember );
		$expire = $expiration + ( 12 * 3600 );
	} else {
		$expiration = time() + apply_filters( 'auth_cookie_expiration', 2 * 86400, $user_id, $remember );
		$expire = 0;
	}

	$secure_logged_in_cookie = $secure && 'https' === parse_url( get_option( 'home' ), PHP_URL_SCHEME );
	$secure = apply_filters( 'secure_auth_cookie', $secure, $user_id );
	$secure_logged_in_cookie = apply_filters( 'secure_logged_in_cookie', $secure_logged_in_cookie, $user_id, $secure );
	$auth_cookie_name = AUTH_COOKIE;
	$scheme = 'auth';
	if ( '' === $token ) {
		$manager = WP_Session_Tokens::get_instance( $user_id );
		$token   = $manager->create( $expiration );
	}
	$auth_cookie = wp_generate_auth_cookie( $user_id, $expiration, $scheme, $token );
	$logged_in_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );
	do_action( 'set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, $scheme );
	do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in' );
	setcookie($auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
	setcookie($auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
	setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
	if ( COOKIEPATH != SITECOOKIEPATH )
		setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
}
endif;

if ( !function_exists('wp_clear_auth_cookie') ) :
function wp_clear_auth_cookie() {
	do_action( 'clear_auth_cookie' );

	setcookie( AUTH_COOKIE,        ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH,   COOKIE_DOMAIN );
	setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, ADMIN_COOKIE_PATH,   COOKIE_DOMAIN );
	setcookie( AUTH_COOKIE,        ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN );
	setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN );
	setcookie( LOGGED_IN_COOKIE,   ' ', time() - YEAR_IN_SECONDS, COOKIEPATH,          COOKIE_DOMAIN );
	setcookie( LOGGED_IN_COOKIE,   ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH,      COOKIE_DOMAIN );

	setcookie( AUTH_COOKIE,        ' ', time() - YEAR_IN_SECONDS, COOKIEPATH,     COOKIE_DOMAIN );
	setcookie( AUTH_COOKIE,        ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
	setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH,     COOKIE_DOMAIN );
	setcookie( SECURE_AUTH_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );

	setcookie( USER_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH,     COOKIE_DOMAIN );
	setcookie( PASS_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH,     COOKIE_DOMAIN );
	setcookie( USER_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
	setcookie( PASS_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );
}
endif;

if ( !function_exists('is_user_logged_in') ) :
function is_user_logged_in() {
	$user = wp_get_current_user();
	return $user->exists();
}
endif;

if ( !function_exists('auth_redirect') ) :
function auth_redirect() {

	$secure = ( is_ssl() || force_ssl_admin() );

	$secure = apply_filters( 'secure_auth_redirect', $secure );

	// If https is required and request is http, redirect
	if ( $secure && !is_ssl() && false !== strpos($_SERVER['REQUEST_URI'], 'wp-admin') ) {
		if ( 0 === strpos( $_SERVER['REQUEST_URI'], 'http' ) ) {
			wp_redirect( set_url_scheme( $_SERVER['REQUEST_URI'], 'https' ) );
			exit();
		} else {
			wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
			exit();
		}
	}

	$scheme = apply_filters( 'auth_redirect_scheme', '' );

	if ( $user_id = wp_validate_auth_cookie( '',  $scheme) ) {

		do_action( 'auth_redirect', $user_id );

		// If the user wants ssl but the session is not ssl, redirect.
		if ( !$secure && get_user_option('use_ssl', $user_id) && false !== strpos($_SERVER['REQUEST_URI'], 'wp-admin') ) {
			if ( 0 === strpos( $_SERVER['REQUEST_URI'], 'http' ) ) {
				wp_redirect( set_url_scheme( $_SERVER['REQUEST_URI'], 'https' ) );
				exit();
			} else {
				wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
				exit();
			}
		}

		return;  // The cookie is good so we're done
	}

	// The cookie is no good so force login
	nocache_headers();

	$redirect = ( strpos( $_SERVER['REQUEST_URI'], '/options.php' ) && wp_get_referer() ) ? wp_get_referer() : set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

	$login_url = wp_login_url($redirect, true);

	wp_redirect($login_url);
	exit();
}
endif;

if ( !function_exists('check_admin_referer') ) :

function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	if ( -1 == $action )
		_doing_it_wrong( __FUNCTION__, 'You should specify a nonce action to be verified by using the first parameter.', '3.2' );

	$adminurl = strtolower(admin_url());
	$referer = strtolower(wp_get_referer());
	$result = isset($_REQUEST[$query_arg]) ? wp_verify_nonce($_REQUEST[$query_arg], $action) : false;

	do_action( 'check_admin_referer', $action, $result );

	if ( ! $result && ! ( -1 == $action && strpos( $referer, $adminurl ) === 0 ) ) {
		wp_nonce_ays( $action );
		die();
	}

	return $result;
}
endif;

if ( !function_exists('check_ajax_referer') ) :
function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
	$nonce = '';

	if ( $query_arg && isset( $_REQUEST[ $query_arg ] ) )
		$nonce = $_REQUEST[ $query_arg ];
	elseif ( isset( $_REQUEST['_ajax_nonce'] ) )
		$nonce = $_REQUEST['_ajax_nonce'];
	elseif ( isset( $_REQUEST['_wpnonce'] ) )
		$nonce = $_REQUEST['_wpnonce'];

	$result = wp_verify_nonce( $nonce, $action );

	do_action( 'check_ajax_referer', $action, $result );

	if ( $die && false === $result ) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_die( -1 );
		} else {
			die( '-1' );
		}
	}

	return $result;
}
endif;

if ( !function_exists('wp_redirect') ) :
function wp_redirect($location, $status = 302) {
	global $is_IIS;

	$location = apply_filters( 'wp_redirect', $location, $status );
	$status = apply_filters( 'wp_redirect_status', $status, $location );
	if ( ! $location )
		return false;

	$location = wp_sanitize_redirect($location);
	if ( !$is_IIS && PHP_SAPI != 'cgi-fcgi' )
		status_header($status);

	header("Location: $location", true, $status);
	return true;
}
endif;

if ( !function_exists('wp_sanitize_redirect') ) :
function wp_sanitize_redirect($location) {
	$regex = '/
		(
			(?: [\xC2-\xDF][\x80-\xBF]        # double-byte sequences   110xxxxx 10xxxxxx
			|   \xE0[\xA0-\xBF][\x80-\xBF]    # triple-byte sequences   1110xxxx 10xxxxxx * 2
			|   [\xE1-\xEC][\x80-\xBF]{2}
			|   \xED[\x80-\x9F][\x80-\xBF]
			|   [\xEE-\xEF][\x80-\xBF]{2}
			|   \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
			|   [\xF1-\xF3][\x80-\xBF]{3}
			|   \xF4[\x80-\x8F][\x80-\xBF]{2}
		){1,40}                              # ...one or more times
		)/x';
	$location = preg_replace_callback( $regex, '_wp_sanitize_utf8_in_redirect', $location );
	$location = preg_replace('|[^a-z0-9-~+_.?#=&;,/:%!*\[\]()@]|i', '', $location);
	$location = wp_kses_no_null($location);

	// remove %0d and %0a from location
	$strip = array('%0d', '%0a', '%0D', '%0A');
	return _deep_replace( $strip, $location );
}

function _wp_sanitize_utf8_in_redirect( $matches ) {
	return urlencode( $matches[0] );
}
endif;

if ( !function_exists('wp_safe_redirect') ) :
function wp_safe_redirect($location, $status = 302) {
	$location = wp_sanitize_redirect($location);
	$location = wp_validate_redirect( $location, apply_filters( 'wp_safe_redirect_fallback', admin_url(), $status ) );
	wp_redirect($location, $status);
}
endif;

if ( !function_exists('wp_validate_redirect') ) :
function wp_validate_redirect($location, $default = '') {
	$location = trim( $location );
	// browsers will assume 'http' is your protocol, and will obey a redirect to a URL starting with '//'
	if ( substr($location, 0, 2) == '//' )
		$location = 'http:' . $location;

	// In php 5 parse_url may fail if the URL query part contains http://, bug #38143
	$test = ( $cut = strpos($location, '?') ) ? substr( $location, 0, $cut ) : $location;

	// @-operator is used to prevent possible warnings in PHP < 5.3.3.
	$lp = @parse_url($test);

	// Give up if malformed URL
	if ( false === $lp )
		return $default;

	// Allow only http and https schemes. No data:, etc.
	if ( isset($lp['scheme']) && !('http' == $lp['scheme'] || 'https' == $lp['scheme']) )
		return $default;

	// Reject if certain components are set but host is not. This catches urls like https:host.com for which parse_url does not set the host field.
	if ( ! isset( $lp['host'] ) && ( isset( $lp['scheme'] ) || isset( $lp['user'] ) || isset( $lp['pass'] ) || isset( $lp['port'] ) ) ) {
		return $default;
	}

	// Reject malformed components parse_url() can return on odd inputs.
	foreach ( array( 'user', 'pass', 'host' ) as $component ) {
		if ( isset( $lp[ $component ] ) && strpbrk( $lp[ $component ], ':/?#@' ) ) {
			return $default;
		}
	}

	$wpp = parse_url(home_url());

	$allowed_hosts = (array) apply_filters( 'allowed_redirect_hosts', array($wpp['host']), isset($lp['host']) ? $lp['host'] : '' );

	if ( isset($lp['host']) && ( !in_array($lp['host'], $allowed_hosts) && $lp['host'] != strtolower($wpp['host'])) )
		$location = $default;

	return $location;
}
endif;

if ( ! function_exists('wp_notify_postauthor') ) :
function wp_notify_postauthor( $comment_id, $deprecated = null ) {
	if ( null !== $deprecated ) {
		_deprecated_argument( __FUNCTION__, '3.8' );
	}

	$comment = get_comment( $comment_id );
	if ( empty( $comment ) || empty( $comment->comment_post_ID ) )
		return false;

	$post    = get_post( $comment->comment_post_ID );
	$author  = get_user_by( 'id', $post->post_author );

	$emails = array();
	if ( $author ) {
		$emails[] = $author->user_email;
	}

	$emails = apply_filters( 'comment_notification_recipients', $emails, $comment->comment_ID );
	$emails = array_filter( $emails );

	if ( ! count( $emails ) ) {
		return false;
	}

	$emails = array_flip( $emails );

	$notify_author = apply_filters( 'comment_notification_notify_author', false, $comment->comment_ID );

	if ( $author && ! $notify_author && $comment->user_id == $post->post_author ) {
		unset( $emails[ $author->user_email ] );
	}

	if ( $author && ! $notify_author && $post->post_author == get_current_user_id() ) {
		unset( $emails[ $author->user_email ] );
	}

	if ( $author && ! $notify_author && ! user_can( $post->post_author, 'read_post', $post->ID ) ) {
		unset( $emails[ $author->user_email ] );
	}

	if ( ! count( $emails ) ) {
		return false;
	} else {
		$emails = array_flip( $emails );
	}

	$comment_author_domain = @gethostbyaddr($comment->comment_author_IP);

	$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
	$comment_content = wp_specialchars_decode( $comment->comment_content );

	$notify_message  = sprintf( 'New comment on your post "%s"', $post->post_title ) . "\r\n";
	$notify_message .= sprintf( 'Author: %1$s (IP: %2$s, %3$s)', $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
	$notify_message .= sprintf( 'Email: %s', $comment->comment_author_email ) . "\r\n";
	$notify_message .= sprintf( 'URL: %s', $comment->comment_author_url ) . "\r\n";
	$notify_message .= sprintf( 'Comment: %s', "\r\n" . $comment_content ) . "\r\n\r\n";
	$notify_message .= "You can see all comments on this post here:\r\n";
	$subject = sprintf( '[%1$s] Comment: "%2$s"', $blogname, $post->post_title );
	$notify_message .= get_permalink($comment->comment_post_ID) . "#comments\r\n\r\n";
	$notify_message .= sprintf( __('Permalink: %s'), get_comment_link( $comment ) ) . "\r\n";

	if ( user_can( $post->post_author, 'edit_comment', $comment->comment_ID ) ) {
		if ( EMPTY_TRASH_DAYS ) {
			$notify_message .= sprintf( __( 'Trash it: %s' ), admin_url( "comment.php?action=trash&c={$comment->comment_ID}#wpbody-content" ) ) . "\r\n";
		} else {
			$notify_message .= sprintf( __( 'Delete it: %s' ), admin_url( "comment.php?action=delete&c={$comment->comment_ID}#wpbody-content" ) ) . "\r\n";
		}
		$notify_message .= sprintf( __( 'Spam it: %s' ), admin_url( "comment.php?action=spam&c={$comment->comment_ID}#wpbody-content" ) ) . "\r\n";
	}

	$wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));

	if ( '' == $comment->comment_author ) {
		$from = "From: \"$blogname\" <$wp_email>";
		if ( '' != $comment->comment_author_email )
			$reply_to = "Reply-To: $comment->comment_author_email";
	} else {
		$from = "From: \"$comment->comment_author\" <$wp_email>";
		if ( '' != $comment->comment_author_email )
			$reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
	}

	$message_headers = "$from\n"
		. "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";

	if ( isset($reply_to) )
		$message_headers .= $reply_to . "\n";

	$notify_message = apply_filters( 'comment_notification_text', $notify_message, $comment->comment_ID );

	$subject = apply_filters( 'comment_notification_subject', $subject, $comment->comment_ID );

	$message_headers = apply_filters( 'comment_notification_headers', $message_headers, $comment->comment_ID );

	foreach ( $emails as $email ) {
		@wp_mail( $email, wp_specialchars_decode( $subject ), $notify_message, $message_headers );
	}

	return true;
}
endif;

if ( !function_exists('wp_notify_moderator') ) :
function wp_notify_moderator($comment_id) {
	global $wpdb;
	$maybe_notify = get_option( 'moderation_notify' );
	$maybe_notify = apply_filters( 'notify_moderator', $maybe_notify, $comment_id );
	if ( ! $maybe_notify ) {
		return true;
	}
	$comment = get_comment($comment_id);
	$post = get_post($comment->comment_post_ID);
	$user = get_user_by( 'id', $post->post_author );
	// Send to the administration and to the post author if the author can modify the comment.
	$emails = array( get_option( 'admin_email' ) );
	if ( $user && user_can( $user->ID, 'edit_comment', $comment_id ) && ! empty( $user->user_email ) ) {
		if ( 0 !== strcasecmp( $user->user_email, get_option( 'admin_email' ) ) )
			$emails[] = $user->user_email;
	}

	$comment_author_domain = @gethostbyaddr($comment->comment_author_IP);
	$comments_waiting = $wpdb->get_var("SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = '0'");

	$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
	$comment_content = wp_specialchars_decode( $comment->comment_content );

	$notify_message  = sprintf( 'A new comment on the post "%s" is waiting for your approval', $post->post_title ) . "\r\n";
	$notify_message .= get_permalink($comment->comment_post_ID) . "\r\n\r\n";
	$notify_message .= sprintf( 'Author: %1$s (IP: %2$s, %3$s)', $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
	$notify_message .= sprintf( 'Email: %s', $comment->comment_author_email ) . "\r\n";
	$notify_message .= sprintf( 'URL: %s', $comment->comment_author_url ) . "\r\n";
	$notify_message .= sprintf( 'Comment: %s', "\r\n" . $comment_content ) . "\r\n\r\n";

	$notify_message .= sprintf( 'Approve it: %s', admin_url( "comment.php?action=approve&c={$comment_id}#wpbody-content" ) ) . "\r\n";

	if ( EMPTY_TRASH_DAYS )
		$notify_message .= sprintf( 'Trash it: %s', admin_url( "comment.php?action=trash&c={$comment_id}#wpbody-content" ) ) . "\r\n";
	else
		$notify_message .= sprintf( 'Delete it: %s', admin_url( "comment.php?action=delete&c={$comment_id}#wpbody-content" ) ) . "\r\n";

	$notify_message .= sprintf( 'Spam it: %s', admin_url( "comment.php?action=spam&c={$comment_id}#wpbody-content" ) ) . "\r\n";

	$notify_message .= sprintf( _n('Currently %s comment is waiting for approval. Please visit the moderation panel:',
 		'Currently %s comments are waiting for approval. Please visit the moderation panel:', $comments_waiting), number_format_i18n($comments_waiting) ) . "\r\n";
	$notify_message .= admin_url( "edit-comments.php?comment_status=moderated#wpbody-content" ) . "\r\n";

	$subject = sprintf( '[%1$s] Please moderate: "%2$s"', $blogname, $post->post_title );
	$message_headers = '';

	$emails = apply_filters( 'comment_moderation_recipients', $emails, $comment_id );

	$notify_message = apply_filters( 'comment_moderation_text', $notify_message, $comment_id );

	$subject = apply_filters( 'comment_moderation_subject', $subject, $comment_id );

	$message_headers = apply_filters( 'comment_moderation_headers', $message_headers, $comment_id );

	foreach ( $emails as $email ) {
		@wp_mail( $email, wp_specialchars_decode( $subject ), $notify_message, $message_headers );
	}

	return true;
}
endif;

if ( !function_exists('wp_password_change_notification') ) :
function wp_password_change_notification( $user ) {
	if ( 0 !== strcasecmp( $user->user_email, get_option( 'admin_email' ) ) ) {
		$message = sprintf('Password Lost and Changed for user: %s', $user->user_login) . "\r\n";
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		wp_mail(get_option('admin_email'), sprintf('[%s] Password Lost/Changed', $blogname), $message);
	}
}
endif;

if ( !function_exists('wp_new_user_notification') ) :
function wp_new_user_notification( $user_id, $notify = '' ) {
	global $wpdb, $wp_hasher;
	$user = get_user_by( 'id', $user_id );

	$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

	$message  = sprintf('New user registration on your site %s:', $blogname) . "\r\n\r\n";
	$message .= sprintf('Username: %s', $user->user_login) . "\r\n\r\n";
	$message .= sprintf('Email: %s', $user->user_email) . "\r\n";

	@wp_mail(get_option('admin_email'), sprintf('[%s] New User Registration', $blogname), $message);

	if ( 'admin' === $notify || empty( $notify ) ) {
		return;
	}
	$key = wp_generate_password( 20, false );
	do_action( 'retrieve_password_key', $user->user_login, $key );
	if ( empty( $wp_hasher ) ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true );
	}
	$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
	$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user->user_login ) );

	$message = sprintf('Username: %s', $user->user_login) . "\r\n\r\n";
	$message .= "To set your password, visit the following address:\r\n\r\n";
	$message .= '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login') . ">\r\n\r\n";
	$message .= wp_login_url() . "\r\n";
	wp_mail($user->user_email, sprintf('[%s] Your username and password info', $blogname), $message);
}
endif;

if ( !function_exists('wp_nonce_tick') ) :
/**
 * Get the time-dependent variable for nonce creation.
 *
 * A nonce has a lifespan of two ticks. Nonces in their second tick may be
 * updated, e.g. by autosave.
 *
 * @since 2.5.0
 *
 * @return float Float value rounded up to the next highest integer.
 */
function wp_nonce_tick() {
	/**
	 * Filter the lifespan of nonces in seconds.
	 *
	 * @param int $lifespan Lifespan of nonces in seconds. Default 86,400 seconds, or one day.
	 */
	$nonce_life = apply_filters( 'nonce_life', DAY_IN_SECONDS );

	return ceil(time() / ( $nonce_life / 2 ));
}
endif;

if ( !function_exists('wp_verify_nonce') ) :
/**
 * Verify that correct nonce was used with time limit.
 *
 * The user is given an amount of time to use the token, so therefore, since the
 * UID and $action remain the same, the independent variable is the time.
 *
 * @param string     $nonce  Nonce that was used in the form to verify
 * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
 * @return false|int False if the nonce is invalid, 1 if the nonce is valid and generated between
 *                   0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago.
 */
function wp_verify_nonce( $nonce, $action = -1 ) {
	$nonce = (string) $nonce;
	$user = wp_get_current_user();
	$uid = (int) $user->ID;
	if ( ! $uid ) {
		/**
		 * Filter whether the user who generated the nonce is logged out.
		 *
		 * @since 3.5.0
		 *
		 * @param int    $uid    ID of the nonce-owning user.
		 * @param string $action The nonce action.
		 */
		$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
	}

	if ( empty( $nonce ) ) {
		return false;
	}

	$token = wp_get_session_token();
	$i = wp_nonce_tick();

	// Nonce generated 0-12 hours ago
	$expected = substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10 );
	if ( hash_equals( $expected, $nonce ) ) {
		return 1;
	}

	// Nonce generated 12-24 hours ago
	$expected = substr( wp_hash( ( $i - 1 ) . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
	if ( hash_equals( $expected, $nonce ) ) {
		return 2;
	}

	/**
	 * Fires when nonce verification fails.
	 *
	 * @since 4.4.0
	 *
	 * @param string     $nonce  The invalid nonce.
	 * @param string|int $action The nonce action.
	 * @param WP_User    $user   The current user object.
	 * @param string     $token  The user's session token.
	 */
	do_action( 'wp_verify_nonce_failed', $nonce, $action, $user, $token );

	// Invalid nonce
	return false;
}
endif;

if ( !function_exists('wp_create_nonce') ) :
/**
 * Creates a cryptographic token tied to a specific action, user, user session,
 * and window of time.
 * @since 4.0.0 Session tokens were integrated with nonce creation
 *
 * @param string|int $action Scalar value to add context to the nonce.
 * @return string The token.
 */
function wp_create_nonce($action = -1) {
	$user = wp_get_current_user();
	$uid = (int) $user->ID;
	if ( ! $uid ) {
		/** This filter is documented in wp-includes/pluggable.php */
		$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
	}

	$token = wp_get_session_token();
	$i = wp_nonce_tick();

	return substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
}
endif;

if ( !function_exists('wp_salt') ) :
function wp_salt( $scheme = 'auth' ) {
	static $cached_salts = array();
	if ( isset( $cached_salts[ $scheme ] ) ) {
		return apply_filters( 'salt', $cached_salts[ $scheme ], $scheme );
	}

	static $duplicated_keys;
	if ( null === $duplicated_keys ) {
		$duplicated_keys = array( 'put your unique phrase here' => true );
		foreach ( array( 'AUTH', 'SECURE_AUTH', 'LOGGED_IN', 'NONCE', 'SECRET' ) as $first ) {
			foreach ( array( 'KEY', 'SALT' ) as $second ) {
				if ( ! defined( "{$first}_{$second}" ) ) {
					continue;
				}
				$value = constant( "{$first}_{$second}" );
				$duplicated_keys[ $value ] = isset( $duplicated_keys[ $value ] );
			}
		}
	}

	$values = array(
		'key' => '',
		'salt' => ''
	);
	if ( defined( 'SECRET_KEY' ) && SECRET_KEY && empty( $duplicated_keys[ SECRET_KEY ] ) ) {
		$values['key'] = SECRET_KEY;
	}
	if ( 'auth' == $scheme && defined( 'SECRET_SALT' ) && SECRET_SALT && empty( $duplicated_keys[ SECRET_SALT ] ) ) {
		$values['salt'] = SECRET_SALT;
	}

	if ( in_array( $scheme, array( 'auth', 'secure_auth', 'logged_in', 'nonce' ) ) ) {
		foreach ( array( 'key', 'salt' ) as $type ) {
			$const = strtoupper( "{$scheme}_{$type}" );
			if ( defined( $const ) && constant( $const ) && empty( $duplicated_keys[ constant( $const ) ] ) ) {
				$values[ $type ] = constant( $const );
			} elseif ( ! $values[ $type ] ) {
				$values[ $type ] = get_site_option( "{$scheme}_{$type}" );
				if ( ! $values[ $type ] ) {
					$values[ $type ] = wp_generate_password( 64, true, true );
					update_site_option( "{$scheme}_{$type}", $values[ $type ] );
				}
			}
		}
	} else {
		if ( ! $values['key'] ) {
			$values['key'] = get_site_option( 'secret_key' );
			if ( ! $values['key'] ) {
				$values['key'] = wp_generate_password( 64, true, true );
				update_site_option( 'secret_key', $values['key'] );
			}
		}
		$values['salt'] = hash_hmac( 'md5', $scheme, $values['key'] );
	}

	$cached_salts[ $scheme ] = $values['key'] . $values['salt'];

	/** This filter is documented in wp-includes/pluggable.php */
	return apply_filters( 'salt', $cached_salts[ $scheme ], $scheme );
}
endif;

if ( !function_exists('wp_hash') ) :
function wp_hash($data, $scheme = 'auth') {
	$salt = wp_salt($scheme);

	return hash_hmac('md5', $data, $salt);
}
endif;

if ( !function_exists('wp_hash_password') ) :
function wp_hash_password($password) {
	global $wp_hasher;
	if ( empty($wp_hasher) ) {
		require_once( ABSPATH . WPINC . '/class-phpass.php');
		$wp_hasher = new PasswordHash(8, true);
	}
	return $wp_hasher->HashPassword( trim( $password ) );
}
endif;

if ( !function_exists('wp_check_password') ) :
function wp_check_password($password, $hash, $user_id = '') {
	global $wp_hasher;

	if ( strlen($hash) <= 32 ) {
		$check = hash_equals( $hash, md5( $password ) );
		if ( $check && $user_id ) {
			// Rehash using new hash.
			wp_set_password($password, $user_id);
			$hash = wp_hash_password($password);
		}

		return apply_filters( 'check_password', $check, $password, $hash, $user_id );
	}

	if ( empty($wp_hasher) ) {
		require_once( ABSPATH . WPINC . '/class-phpass.php');
		// By default, use the portable hash from phpass
		$wp_hasher = new PasswordHash(8, true);
	}

	$check = $wp_hasher->CheckPassword($password, $hash);

	/** This filter is documented in wp-includes/pluggable.php */
	return apply_filters( 'check_password', $check, $password, $hash, $user_id );
}
endif;

if ( !function_exists('wp_generate_password') ) :
function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	if ( $special_chars )
		$chars .= '!@#$%^&*()';
	if ( $extra_special_chars )
		$chars .= '-_ []{}<>~`+=,.;:/?|';

	$password = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$password .= substr($chars, wp_rand(0, strlen($chars) - 1), 1);
	}

	return apply_filters( 'random_password', $password );
}
endif;

if ( !function_exists('wp_rand') ) :
function wp_rand( $min = 0, $max = 0 ) {
	global $rnd_value;

	$max_random_number = 3000000000 === 2147483647 ? (float) "4294967295" : 4294967295; // 4294967295 = 0xffffffff

	$min = (int) $min;
	$max = (int) $max;

	static $use_random_int_functionality = true;
	if ( $use_random_int_functionality ) {
		try {
			$_max = ( 0 != $max ) ? $max : $max_random_number;
			// wp_rand() can accept arguments in either order, PHP cannot.
			$_max = max( $min, $_max );
			$_min = min( $min, $_max );
			$val = random_int( $_min, $_max );
			if ( false !== $val ) {
				return absint( $val );
			} else {
				$use_random_int_functionality = false;
			}
		} catch ( Error $e ) {
			$use_random_int_functionality = false;
		} catch ( Exception $e ) {
			$use_random_int_functionality = false;
		}
	}

	// Reset $rnd_value after 14 uses
	// 32(md5) + 40(sha1) + 40(sha1) / 8 = 14 random numbers from $rnd_value
	if ( strlen($rnd_value) < 8 ) {
		if ( defined( 'WP_SETUP_CONFIG' ) )
			static $seed = '';
		else
			$seed = get_transient('random_seed');
		$rnd_value = md5( uniqid(microtime() . mt_rand(), true ) . $seed );
		$rnd_value .= sha1($rnd_value);
		$rnd_value .= sha1($rnd_value . $seed);
		$seed = md5($seed . $rnd_value);
		if ( ! defined( 'WP_SETUP_CONFIG' ) && ! defined( 'WP_INSTALLING' ) ) {
			set_transient( 'random_seed', $seed );
		}
	}

	// Take the first 8 digits for our value
	$value = substr($rnd_value, 0, 8);

	// Strip the first eight, leaving the remainder for the next call to wp_rand().
	$rnd_value = substr($rnd_value, 8);

	$value = abs(hexdec($value));

	// Reduce the value to be within the min - max range
	if ( $max != 0 )
		$value = $min + ( $max - $min + 1 ) * $value / ( $max_random_number + 1 );

	return abs(intval($value));
}
endif;

if ( !function_exists('wp_set_password') ) :
function wp_set_password( $password, $user_id ) {
	global $wpdb;

	$hash = wp_hash_password( $password );
	$wpdb->update($wpdb->users, array('user_pass' => $hash, 'user_activation_key' => ''), array('ID' => $user_id) );

	wp_cache_delete($user_id, 'users');
}
endif;

if ( !function_exists( 'get_avatar' ) ) :
function get_avatar( $id_or_email, $size = 96, $default = '', $alt = '', $args = null ) {
	$defaults = array(
		'size'          => 96,
		'height'        => null,
		'width'         => null,
		'default'       => get_option( 'avatar_default', 'mystery' ),
		'force_default' => false,
		'rating'        => get_option( 'avatar_rating' ),
		'scheme'        => null,
		'alt'           => '',
		'class'         => null,
		'force_display' => false,
		'extra_attr'    => '',
	);

	if ( empty( $args ) ) {
		$args = array();
	}

	$args['size']    = (int) $size;
	$args['default'] = $default;
	$args['alt']     = $alt;

	$args = wp_parse_args( $args, $defaults );

	if ( empty( $args['height'] ) ) {
		$args['height'] = $args['size'];
	}
	if ( empty( $args['width'] ) ) {
		$args['width'] = $args['size'];
	}

	if ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
		$id_or_email = get_comment( $id_or_email );
	}

	$avatar = apply_filters( 'pre_get_avatar', null, $id_or_email, $args );

	if ( ! is_null( $avatar ) ) {
		/** This filter is documented in wp-includes/pluggable.php */
		return apply_filters( 'get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args );
	}

	if ( ! $args['force_display'] && ! get_option( 'show_avatars' ) ) {
		return false;
	}

	$url2x = get_avatar_url( $id_or_email, array_merge( $args, array( 'size' => $args['size'] * 2 ) ) );

	$args = get_avatar_data( $id_or_email, $args );

	$url = $args['url'];

	if ( ! $url || is_wp_error( $url ) ) {
		return false;
	}

	$class = array( 'avatar', 'avatar-' . (int) $args['size'], 'photo' );

	if ( ! $args['found_avatar'] || $args['force_default'] ) {
		$class[] = 'avatar-default';
	}

	if ( $args['class'] ) {
		if ( is_array( $args['class'] ) ) {
			$class = array_merge( $class, $args['class'] );
		} else {
			$class[] = $args['class'];
		}
	}

	$avatar = sprintf(
		"<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>",
		esc_attr( $args['alt'] ),
		esc_url( $url ),
		esc_attr( "$url2x 2x" ),
		esc_attr( join( ' ', $class ) ),
		(int) $args['height'],
		(int) $args['width'],
		$args['extra_attr']
	);

	return apply_filters( 'get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args );
}
endif;

if ( !function_exists( 'wp_text_diff' ) ) :
function wp_text_diff( $left_string, $right_string, $args = null ) {
	$defaults = array( 'title' => '', 'title_left' => '', 'title_right' => '' );
	$args = wp_parse_args( $args, $defaults );

	if ( ! class_exists( 'WP_Text_Diff_Renderer_Table', false ) )
		require( ABSPATH . WPINC . '/wp-diff.php' );

	$left_string  = normalize_whitespace($left_string);
	$right_string = normalize_whitespace($right_string);

	$left_lines  = explode("\n", $left_string);
	$right_lines = explode("\n", $right_string);
	$text_diff = new Text_Diff($left_lines, $right_lines);
	$renderer  = new WP_Text_Diff_Renderer_Table( $args );
	$diff = $renderer->render($text_diff);

	if ( !$diff )
		return '';

	$r  = "<table class='diff'>\n";

	if ( ! empty( $args[ 'show_split_view' ] ) ) {
		$r .= "<col class='content diffsplit left' /><col class='content diffsplit middle' /><col class='content diffsplit right' />";
	} else {
		$r .= "<col class='content' />";
	}

	if ( $args['title'] || $args['title_left'] || $args['title_right'] )
		$r .= "<thead>";
	if ( $args['title'] )
		$r .= "<tr class='diff-title'><th colspan='4'>$args[title]</th></tr>\n";
	if ( $args['title_left'] || $args['title_right'] ) {
		$r .= "<tr class='diff-sub-title'>\n";
		$r .= "\t<td></td><th>$args[title_left]</th>\n";
		$r .= "\t<td></td><th>$args[title_right]</th>\n";
		$r .= "</tr>\n";
	}
	if ( $args['title'] || $args['title_left'] || $args['title_right'] )
		$r .= "</thead>\n";

	$r .= "<tbody>\n$diff\n</tbody>\n";
	$r .= "</table>";

	return $r;
}
endif;

