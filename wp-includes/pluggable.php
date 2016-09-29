<?php
/**
 * These functions can be replaced via plugins. If plugins do not redefine these
 * functions, then these will be used instead.
 *
 * @package WordPress
 */

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

function get_user_by( $field, $value ) {
	$userdata = WP_User::get_data_by( $field, $value );
	if ( !$userdata )
		return false;
	$user = new WP_User;
	$user->init( $userdata );
	return $user;
}

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
				list( $name, $content ) = explode( ':', trim( $header ), 2 );
				$name    = trim( $name    );
				$content = trim( $content );
				switch ( strtolower( $name ) ) {
					case 'from':
						$bracket_pos = strpos( $content, '<' );
						if ( $bracket_pos !== false ) {
							if ( $bracket_pos > 0 ) {
								$from_name = substr( $content, 0, $bracket_pos - 1 );
								$from_name = str_replace( '"', '', $from_name );
								$from_name = trim( $from_name );
							}
							$from_email = substr( $content, $bracket_pos + 1 );
							$from_email = str_replace( '>', '', $from_email );
							$from_email = trim( $from_email );
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
	$phpmailer->Subject = $subject;
	$phpmailer->Body    = $message;
	if ( !empty( $cc ) ) {
		foreach ( (array) $cc as $recipient ) {
			try {
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
	if ( 'text/html' == $content_type )
		$phpmailer->IsHTML( true );
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

function wp_logout() {
	wp_destroy_current_session();
	wp_clear_auth_cookie();
	do_action( 'wp_logout' );
}

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
	$hash = hash_hmac( 'sha256', $username . '|' . $expiration . '|' . $token, $key );
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

function wp_parse_auth_cookie($cookie = '', $scheme = '') {
	if ( empty($cookie) ) {
		switch ($scheme){
			case 'auth':
				$cookie_name = AUTH_COOKIE;
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

function is_user_logged_in() {
	$user = _wp_get_current_user();
	return $user->exists();
}

function auth_redirect() {
	$secure = apply_filters( 'secure_auth_redirect', $secure );
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
		if ( !$secure && get_user_option('use_ssl', $user_id) && false !== strpos($_SERVER['REQUEST_URI'], 'wp-admin') ) {
			if ( 0 === strpos( $_SERVER['REQUEST_URI'], 'http' ) ) {
				wp_redirect( set_url_scheme( $_SERVER['REQUEST_URI'], 'https' ) );
				exit();
			} else {
				wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
				exit();
			}
		}
		return;
	}
	nocache_headers();
	$redirect = ( strpos( $_SERVER['REQUEST_URI'], '/options.php' ) && wp_get_referer() ) ? wp_get_referer() : set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
	$login_url = wp_login_url($redirect, true);
	wp_redirect($login_url);
	exit();
}

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

function wp_redirect($location, $status = 302) {
	$location = apply_filters( 'wp_redirect', $location, $status );
	$status = apply_filters( 'wp_redirect_status', $status, $location );
	if ( ! $location )
		return false;

	$location = wp_sanitize_redirect($location);
	if ( PHP_SAPI != 'cgi-fcgi' )
		status_header($status);
	header("Location: $location", true, $status);
	return true;
}

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
	$strip = array('%0d', '%0a', '%0D', '%0A');
	return _deep_replace( $strip, $location );
}

function _wp_sanitize_utf8_in_redirect( $matches ) {
	return urlencode( $matches[0] );
}

function wp_safe_redirect($location, $status = 302) {
	$location = wp_sanitize_redirect($location);
	$location = wp_validate_redirect( $location, apply_filters( 'wp_safe_redirect_fallback', admin_url(), $status ) );
	wp_redirect($location, $status);
}

function wp_validate_redirect($location, $default = '') {
	$location = trim( $location );
	if ( substr($location, 0, 2) == '//' )
		$location = 'http:' . $location;
	$test = ( $cut = strpos($location, '?') ) ? substr( $location, 0, $cut ) : $location;
	$lp = @parse_url($test);
	if ( false === $lp )
		return $default;
	if ( isset($lp['scheme']) && !('http' == $lp['scheme'] || 'https' == $lp['scheme']) )
		return $default;
	if ( ! isset( $lp['host'] ) && ( isset( $lp['scheme'] ) || isset( $lp['user'] ) || isset( $lp['pass'] ) || isset( $lp['port'] ) ) ) {
		return $default;
	}
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

function wp_nonce_tick() {
	$nonce_life = apply_filters( 'nonce_life', DAY_IN_SECONDS );
	return ceil(time() / ( $nonce_life / 2 ));
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	$nonce = (string) $nonce;
	$user = _wp_get_current_user();
	$uid = (int) $user->ID;
	if ( ! $uid ) {
		$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
	}
	if ( empty( $nonce ) ) {
		return false;
	}
	$token = wp_get_session_token();
	$i = wp_nonce_tick();
	$expected = substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10 );
	if ( hash_equals( $expected, $nonce ) ) {
		return 1;
	}
	$expected = substr( wp_hash( ( $i - 1 ) . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
	if ( hash_equals( $expected, $nonce ) ) {
		return 2;
	}

	do_action( 'wp_verify_nonce_failed', $nonce, $action, $user, $token );
	return false;
}

function wp_create_nonce($action = -1) {
	$user = _wp_get_current_user();
	$uid = (int) $user->ID;
	if ( ! $uid ) {
		$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
	}
	$token = wp_get_session_token();
	$i = wp_nonce_tick();
	return substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
}

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
	$values = array( 'key' => '', 'salt' => '' );
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
					$values[ $type ] = '123456';
					update_site_option( "{$scheme}_{$type}", $values[ $type ] );
				}
			}
		}
	} else {
		if ( ! $values['key'] ) {
			$values['key'] = get_site_option( 'secret_key' );
			if ( ! $values['key'] ) {
				$values['key'] = '123456';
				update_site_option( 'secret_key', $values['key'] );
			}
		}
		$values['salt'] = hash_hmac( 'md5', $scheme, $values['key'] );
	}

	$cached_salts[ $scheme ] = $values['key'] . $values['salt'];
	return apply_filters( 'salt', $cached_salts[ $scheme ], $scheme );
}

function wp_hash($data, $scheme = 'auth') {
	$salt = wp_salt($scheme);
	return hash_hmac('md5', $data, $salt);
}

function wp_hash_password($password) {
	global $wp_hasher;
	if ( empty($wp_hasher) ) {
		require_once( ABSPATH . WPINC . '/class-phpass.php');
		$wp_hasher = new PasswordHash(8, true);
	}
	return $wp_hasher->HashPassword( trim( $password ) );
}

function wp_check_password($password, $hash, $user_id = '') {
	global $wp_hasher;
	if ( strlen($hash) <= 32 ) {
		$check = hash_equals( $hash, md5( $password ) );
		if ( $check && $user_id ) {
			wp_set_password($password, $user_id);
			$hash = wp_hash_password($password);
		}

		return apply_filters( 'check_password', $check, $password, $hash, $user_id );
	}
	if ( empty($wp_hasher) ) {
		require_once( ABSPATH . WPINC . '/class-phpass.php');
		$wp_hasher = new PasswordHash(8, true);
	}
	$check = $wp_hasher->CheckPassword($password, $hash);
	return apply_filters( 'check_password', $check, $password, $hash, $user_id );
}

function wp_rand( $min = 0, $max = 0 ) {
	global $rnd_value;
	$max_random_number = 3000000000 === 2147483647 ? (float) "4294967295" : 4294967295; // 4294967295 = 0xffffffff
	$min = (int) $min;
	$max = (int) $max;
	static $use_random_int_functionality = true;
	if ( $use_random_int_functionality ) {
		try {
			$_max = ( 0 != $max ) ? $max : $max_random_number;
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
	$value = substr($rnd_value, 0, 8);
	$rnd_value = substr($rnd_value, 8);
	$value = abs(hexdec($value));
	if ( $max != 0 )
		$value = $min + ( $max - $min + 1 ) * $value / ( $max_random_number + 1 );
	return abs(intval($value));
}

function wp_set_password( $password, $user_id ) {
	global $wpdb;
	$hash = wp_hash_password( $password );
	$wpdb->update($wpdb->users, array('user_pass' => $hash, 'user_activation_key' => ''), array('ID' => $user_id) );
	wp_cache_delete($user_id, 'users');
}

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
		return apply_filters( 'get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args );
	}

	if ( ! $args['force_display'] && ! get_option( 'show_avatars' ) ) {
		return false;
	}
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

