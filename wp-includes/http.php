<?php

function _wp_http_get_object() {
	static $http = null;

	if ( is_null( $http ) ) {
		$http = new WP_Http();
	}
	return $http;
}

function wp_safe_remote_request( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->request( $url, $args );
}

function wp_safe_remote_get( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->get( $url, $args );
}

function wp_safe_remote_post( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->post( $url, $args );
}

function wp_safe_remote_head( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->head( $url, $args );
}

function wp_remote_request($url, $args = array()) {
	$http = _wp_http_get_object();
	return $http->request( $url, $args );
}

function wp_remote_get($url, $args = array()) {
	$http = _wp_http_get_object();
	return $http->get( $url, $args );
}

function wp_remote_post($url, $args = array()) {
	$http = _wp_http_get_object();
	return $http->post( $url, $args );
}

function wp_remote_head($url, $args = array()) {
	$http = _wp_http_get_object();
	return $http->head( $url, $args );
}

function wp_remote_retrieve_headers( $response ) {
	if ( is_wp_error($response) || ! isset($response['headers']) || ! is_array($response['headers']))
		return array();

	return $response['headers'];
}

function wp_remote_retrieve_header( $response, $header ) {
	if ( is_wp_error($response) || ! isset($response['headers']) || ! is_array($response['headers']))
		return '';

	if ( array_key_exists($header, $response['headers']) )
		return $response['headers'][$header];

	return '';
}

function wp_remote_retrieve_response_code( $response ) {
	if ( is_wp_error($response) || ! isset($response['response']) || ! is_array($response['response']))
		return '';

	return $response['response']['code'];
}

function wp_remote_retrieve_response_message( $response ) {
	if ( is_wp_error($response) || ! isset($response['response']) || ! is_array($response['response']))
		return '';

	return $response['response']['message'];
}

function wp_remote_retrieve_body( $response ) {
	if ( is_wp_error($response) || ! isset($response['body']) )
		return '';

	return $response['body'];
}

function wp_remote_retrieve_cookies( $response ) {
	if ( is_wp_error( $response ) || empty( $response['cookies'] ) ) {
		return array();
	}

	return $response['cookies'];
}

function wp_remote_retrieve_cookie( $response, $name ) {
	$cookies = wp_remote_retrieve_cookies( $response );

	if ( empty( $cookies ) ) {
		return '';
	}

	foreach ( $cookies as $cookie ) {
		if ( $cookie->name === $name ) {
			return $cookie;
		}
	}

	return '';
}

function wp_remote_retrieve_cookie_value( $response, $name ) {
	$cookie = wp_remote_retrieve_cookie( $response, $name );

	if ( ! is_a( $cookie, 'WP_Http_Cookie' ) ) {
		return '';
	}

	return $cookie->value;
}

function wp_http_supports( $capabilities = array(), $url = null ) {
	$http = _wp_http_get_object();

	$capabilities = wp_parse_args( $capabilities );

	$count = count( $capabilities );

	if ( $count && count( array_filter( array_keys( $capabilities ), 'is_numeric' ) ) == $count ) {
		$capabilities = array_combine( array_values( $capabilities ), array_fill( 0, $count, true ) );
	}

	if ( $url && !isset( $capabilities['ssl'] ) ) {
		$scheme = parse_url( $url, PHP_URL_SCHEME );
		if ( 'https' == $scheme || 'ssl' == $scheme ) {
			$capabilities['ssl'] = true;
		}
	}

	return (bool) $http->_get_first_available_transport( $capabilities );
}

function get_http_origin() {
	$origin = '';
	if ( ! empty ( $_SERVER[ 'HTTP_ORIGIN' ] ) )
		$origin = $_SERVER[ 'HTTP_ORIGIN' ];

	return apply_filters( 'http_origin', $origin );
}

function get_allowed_http_origins() {
	$admin_origin = parse_url( admin_url() );
	$home_origin = parse_url( home_url() );

	// @todo preserve port?
	$allowed_origins = array_unique( array(
		'http://' . $admin_origin[ 'host' ],
		'https://' . $admin_origin[ 'host' ],
		'http://' . $home_origin[ 'host' ],
		'https://' . $home_origin[ 'host' ],
	) );

	return apply_filters( 'allowed_http_origins' , $allowed_origins );
}

function is_allowed_http_origin( $origin = null ) {
	$origin_arg = $origin;

	if ( null === $origin )
		$origin = get_http_origin();

	if ( $origin && ! in_array( $origin, get_allowed_http_origins() ) )
		$origin = '';

	return apply_filters( 'allowed_http_origin', $origin, $origin_arg );
}

function send_origin_headers() {
	$origin = get_http_origin();

	if ( is_allowed_http_origin( $origin ) ) {
		@header( 'Access-Control-Allow-Origin: ' .  $origin );
		@header( 'Access-Control-Allow-Credentials: true' );
		if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] )
			exit;
		return $origin;
	}

	if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
		status_header( 403 );
		exit;
	}

	return false;
}

function wp_http_validate_url( $url ) {
	$original_url = $url;
	$url = wp_kses_bad_protocol( $url, array( 'http', 'https' ) );
	if ( ! $url || strtolower( $url ) !== strtolower( $original_url ) )
		return false;

	$parsed_url = @parse_url( $url );
	if ( ! $parsed_url || empty( $parsed_url['host'] ) )
		return false;

	if ( isset( $parsed_url['user'] ) || isset( $parsed_url['pass'] ) )
		return false;

	if ( false !== strpbrk( $parsed_url['host'], ':#?[]' ) )
		return false;

	$parsed_home = @parse_url( get_option( 'home' ) );

	if ( isset( $parsed_home['host'] ) ) {
		$same_host = ( strtolower( $parsed_home['host'] ) === strtolower( $parsed_url['host'] ) || 'localhost' === strtolower( $parsed_url['host'] ) );
	} else {
		$same_host = false;
	}

	if ( ! $same_host ) {
		$host = trim( $parsed_url['host'], '.' );
		if ( preg_match( '#^(([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)\.){3}([1-9]?\d|1\d\d|25[0-5]|2[0-4]\d)$#', $host ) ) {
			$ip = $host;
		} else {
			$ip = gethostbyname( $host );
			if ( $ip === $host ) // Error condition for gethostbyname()
				$ip = false;
		}
		if ( $ip ) {
			$parts = array_map( 'intval', explode( '.', $ip ) );
			if ( 127 === $parts[0] || 10 === $parts[0] || 0 === $parts[0]
				|| ( 172 === $parts[0] && 16 <= $parts[1] && 31 >= $parts[1] )
				|| ( 192 === $parts[0] && 168 === $parts[1] )
			) {

				if ( ! apply_filters( 'http_request_host_is_external', false, $host, $url ) )
					return false;
			}
		}
	}

	if ( empty( $parsed_url['port'] ) )
		return $url;

	$port = $parsed_url['port'];
	if ( 80 === $port || 443 === $port || 8080 === $port )
		return $url;

	if ( $parsed_home && $same_host && isset( $parsed_home['port'] ) && $parsed_home['port'] === $port )
		return $url;

	return false;
}

function allowed_http_request_hosts( $is_external, $host ) {
	if ( ! $is_external && wp_validate_redirect( 'http://' . $host ) )
		$is_external = true;
	return $is_external;
}

function ms_allowed_http_request_hosts( $is_external, $host ) {
	global $wpdb;
	static $queried = array();
	if ( $is_external )
		return $is_external;
	if ( $host === get_current_site()->domain )
		return true;
	if ( isset( $queried[ $host ] ) )
		return $queried[ $host ];
	$queried[ $host ] = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM $wpdb->blogs WHERE domain = %s LIMIT 1", $host ) );
	return $queried[ $host ];
}

function wp_parse_url( $url ) {
	$parts = @parse_url( $url );
	if ( ! $parts ) {
		// < PHP 5.4.7 compat, trouble with relative paths including a scheme break in the path
		if ( '/' == $url[0] && false !== strpos( $url, '://' ) ) {
			// Since we know it's a relative path, prefix with a scheme/host placeholder and try again
			if ( ! $parts = @parse_url( 'placeholder://placeholder' . $url ) ) {
				return $parts;
			}
			// Remove the placeholder values
			unset( $parts['scheme'], $parts['host'] );
		} else {
			return $parts;
		}
	}

	// < PHP 5.4.7 compat, doesn't detect schemeless URL's host field
	if ( '//' == substr( $url, 0, 2 ) && ! isset( $parts['host'] ) ) {
		$path_parts = explode( '/', substr( $parts['path'], 2 ), 2 );
		$parts['host'] = $path_parts[0];
		if ( isset( $path_parts[1] ) ) {
			$parts['path'] = '/' . $path_parts[1];
		} else {
			unset( $parts['path'] );
		}
	}

	return $parts;
}
