<?php

class WP_Http_Curl {

	private $headers = '';

	private $body = '';

	private $max_body_length = false;

	private $stream_handle = false;

	private $bytes_written_total = 0;

	public function request($url, $args = array()) {
		$defaults = array(
			'method' => 'GET', 'timeout' => 5,
			'redirection' => 5, 'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(), 'body' => null, 'cookies' => array()
		);

		$r = wp_parse_args( $args, $defaults );

		if ( isset( $r['headers']['User-Agent'] ) ) {
			$r['user-agent'] = $r['headers']['User-Agent'];
			unset( $r['headers']['User-Agent'] );
		} elseif ( isset( $r['headers']['user-agent'] ) ) {
			$r['user-agent'] = $r['headers']['user-agent'];
			unset( $r['headers']['user-agent'] );
		}

		WP_Http::buildCookieHeader( $r );

		$handle = curl_init();

		$proxy = new WP_HTTP_Proxy();

		if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) ) {

			curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_HTTP );
			curl_setopt( $handle, CURLOPT_PROXY, $proxy->host() );
			curl_setopt( $handle, CURLOPT_PROXYPORT, $proxy->port() );

			if ( $proxy->use_authentication() ) {
				curl_setopt( $handle, CURLOPT_PROXYAUTH, CURLAUTH_ANY );
				curl_setopt( $handle, CURLOPT_PROXYUSERPWD, $proxy->authentication() );
			}
		}

		$is_local = isset($r['local']) && $r['local'];
		$ssl_verify = isset($r['sslverify']) && $r['sslverify'];
		if ( $is_local ) {
			$ssl_verify = apply_filters( 'https_local_ssl_verify', $ssl_verify );
		} elseif ( ! $is_local ) {
			$ssl_verify = apply_filters( 'https_ssl_verify', $ssl_verify );
		}

		$timeout = (int) ceil( $r['timeout'] );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );

		curl_setopt( $handle, CURLOPT_URL, $url);
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, ( $ssl_verify === true ) ? 2 : false );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, $ssl_verify );

		if ( $ssl_verify ) {
			curl_setopt( $handle, CURLOPT_CAINFO, $r['sslcertificates'] );
		}

		curl_setopt( $handle, CURLOPT_USERAGENT, $r['user-agent'] );

		curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, false );
		if ( defined( 'CURLOPT_PROTOCOLS' ) ) // PHP 5.2.10 / cURL 7.19.4
			curl_setopt( $handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );

		switch ( $r['method'] ) {
			case 'HEAD':
				curl_setopt( $handle, CURLOPT_NOBODY, true );
				break;
			case 'POST':
				curl_setopt( $handle, CURLOPT_POST, true );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
			case 'PUT':
				curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, 'PUT' );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
			default:
				curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, $r['method'] );
				if ( ! is_null( $r['body'] ) )
					curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
		}

		if ( true === $r['blocking'] ) {
			curl_setopt( $handle, CURLOPT_HEADERFUNCTION, array( $this, 'stream_headers' ) );
			curl_setopt( $handle, CURLOPT_WRITEFUNCTION, array( $this, 'stream_body' ) );
		}

		curl_setopt( $handle, CURLOPT_HEADER, false );

		if ( isset( $r['limit_response_size'] ) )
			$this->max_body_length = intval( $r['limit_response_size'] );
		else
			$this->max_body_length = false;

		if ( $r['stream'] ) {
			if ( ! WP_DEBUG )
				$this->stream_handle = @fopen( $r['filename'], 'w+' );
			else
				$this->stream_handle = fopen( $r['filename'], 'w+' );
			if ( ! $this->stream_handle )
				return new WP_Error( 'http_request_failed', sprintf( 'Could not open handle for fopen() to %s', $r['filename'] ) );
		} else {
			$this->stream_handle = false;
		}

		if ( !empty( $r['headers'] ) ) {
			$headers = array();
			foreach ( $r['headers'] as $name => $value ) {
				$headers[] = "{$name}: $value";
			}
			curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
		}

		if ( $r['httpversion'] == '1.0' )
			curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );
		else
			curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		do_action_ref_array( 'http_api_curl', array( &$handle, $r, $url ) );

		if ( ! $r['blocking'] ) {
			curl_exec( $handle );

			if ( $curl_error = curl_error( $handle ) ) {
				curl_close( $handle );
				return new WP_Error( 'http_request_failed', $curl_error );
			}
			if ( in_array( curl_getinfo( $handle, CURLINFO_HTTP_CODE ), array( 301, 302 ) ) ) {
				curl_close( $handle );
				return new WP_Error( 'http_request_failed', 'Too many redirects.' );
			}

			curl_close( $handle );
			return array( 'headers' => array(), 'body' => '', 'response' => array('code' => false, 'message' => false), 'cookies' => array() );
		}

		curl_exec( $handle );
		$theHeaders = WP_Http::processHeaders( $this->headers, $url );
		$theBody = $this->body;
		$bytes_written_total = $this->bytes_written_total;

		$this->headers = '';
		$this->body = '';
		$this->bytes_written_total = 0;

		$curl_error = curl_errno( $handle );

		if ( $curl_error || ( 0 == strlen( $theBody ) && empty( $theHeaders['headers'] ) ) ) {
			if ( CURLE_WRITE_ERROR /* 23 */ == $curl_error ) {
				if ( ! $this->max_body_length || $this->max_body_length != $bytes_written_total ) {
					if ( $r['stream'] ) {
						curl_close( $handle );
						fclose( $this->stream_handle );
						return new WP_Error( 'http_request_failed', 'Failed to write request to temporary file.' );
					} else {
						curl_close( $handle );
						return new WP_Error( 'http_request_failed', curl_error( $handle ) );
					}
				}
			} else {
				if ( $curl_error = curl_error( $handle ) ) {
					curl_close( $handle );
					return new WP_Error( 'http_request_failed', $curl_error );
				}
			}
			if ( in_array( curl_getinfo( $handle, CURLINFO_HTTP_CODE ), array( 301, 302 ) ) ) {
				curl_close( $handle );
				return new WP_Error( 'http_request_failed', 'Too many redirects.' );
			}
		}

		curl_close( $handle );

		if ( $r['stream'] )
			fclose( $this->stream_handle );

		$response = array(
			'headers' => $theHeaders['headers'],
			'body' => null,
			'response' => $theHeaders['response'],
			'cookies' => $theHeaders['cookies'],
			'filename' => $r['filename']
		);

		// Handle redirects.
		if ( false !== ( $redirect_response = WP_HTTP::handle_redirects( $url, $r, $response ) ) )
			return $redirect_response;

		if ( true === $r['decompress'] && true === WP_Http_Encoding::should_decode($theHeaders['headers']) )
			$theBody = WP_Http_Encoding::decompress( $theBody );

		$response['body'] = $theBody;

		return $response;
	}

	private function stream_headers( $handle, $headers ) {
		$this->headers .= $headers;
		return strlen( $headers );
	}

	private function stream_body( $handle, $data ) {
		$data_length = strlen( $data );

		if ( $this->max_body_length && ( $this->bytes_written_total + $data_length ) > $this->max_body_length ) {
			$data_length = ( $this->max_body_length - $this->bytes_written_total );
			$data = substr( $data, 0, $data_length );
		}

		if ( $this->stream_handle ) {
			$bytes_written = fwrite( $this->stream_handle, $data );
		} else {
			$this->body .= $data;
			$bytes_written = $data_length;
		}

		$this->bytes_written_total += $bytes_written;

		return $bytes_written;
	}

	public static function test( $args = array() ) {
		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_exec' ) )
			return false;

		$is_ssl = isset( $args['ssl'] ) && $args['ssl'];

		if ( $is_ssl ) {
			$curl_version = curl_version();
			if ( ! (CURL_VERSION_SSL & $curl_version['features']) )
				return false;
		}

		return apply_filters( 'use_curl_transport', true, $args );
	}
}
