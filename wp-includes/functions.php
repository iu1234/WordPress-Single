<?php
/**
 * Main WordPress API
 *
 * @package WordPress
 */

require( ABSPATH . WPINC . '/option.php' );

function mysql2date( $format, $date, $translate = true ) {
	if ( empty( $date ) )
		return false;

	if ( 'G' == $format )
		return strtotime( $date . ' +0000' );

	$i = strtotime( $date );

	if ( 'U' == $format )
		return $i;

	if ( $translate )
		return date_i18n( $format, $i );
	else
		return date( $format, $i );
}

function current_time( $type, $gmt = 0 ) {
	switch ( $type ) {
		case 'mysql':
			return ( $gmt ) ? gmdate( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s', ( time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
		case 'timestamp':
			return ( $gmt ) ? time() : time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		default:
			return ( $gmt ) ? date( $type ) : date( $type, time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}
}

function date_i18n( $dateformatstring, $unixtimestamp = false, $gmt = false ) {
	global $wp_locale;
	$i = $unixtimestamp;

	if ( false === $i ) {
		if ( ! $gmt )
			$i = current_time( 'timestamp' );
		else
			$i = time();
		$gmt = true;
	}
	$req_format = $dateformatstring;
	$datefunc = $gmt? 'gmdate' : 'date';

	if ( ( !empty( $wp_locale->month ) ) && ( !empty( $wp_locale->weekday ) ) ) {
		$datemonth = $wp_locale->get_month( $datefunc( 'm', $i ) );
		$datemonth_abbrev = $wp_locale->get_month_abbrev( $datemonth );
		$dateweekday = $wp_locale->get_weekday( $datefunc( 'w', $i ) );
		$dateweekday_abbrev = $wp_locale->get_weekday_abbrev( $dateweekday );
		$datemeridiem = $wp_locale->get_meridiem( $datefunc( 'a', $i ) );
		$datemeridiem_capital = $wp_locale->get_meridiem( $datefunc( 'A', $i ) );
		$dateformatstring = ' '.$dateformatstring;
		$dateformatstring = preg_replace( "/([^\\\])D/", "\\1" . backslashit( $dateweekday_abbrev ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])F/", "\\1" . backslashit( $datemonth ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])l/", "\\1" . backslashit( $dateweekday ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])M/", "\\1" . backslashit( $datemonth_abbrev ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])a/", "\\1" . backslashit( $datemeridiem ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])A/", "\\1" . backslashit( $datemeridiem_capital ), $dateformatstring );

		$dateformatstring = substr( $dateformatstring, 1, strlen( $dateformatstring ) -1 );
	}
	$timezone_formats = array( 'P', 'I', 'O', 'T', 'Z', 'e' );
	$timezone_formats_re = implode( '|', $timezone_formats );
	if ( preg_match( "/$timezone_formats_re/", $dateformatstring ) ) {
		$timezone_string = get_option( 'timezone_string' );
		if ( $timezone_string ) {
			$timezone_object = timezone_open( $timezone_string );
			$date_object = date_create( null, $timezone_object );
			foreach ( $timezone_formats as $timezone_format ) {
				if ( false !== strpos( $dateformatstring, $timezone_format ) ) {
					$formatted = date_format( $date_object, $timezone_format );
					$dateformatstring = ' '.$dateformatstring;
					$dateformatstring = preg_replace( "/([^\\\])$timezone_format/", "\\1" . backslashit( $formatted ), $dateformatstring );
					$dateformatstring = substr( $dateformatstring, 1, strlen( $dateformatstring ) -1 );
				}
			}
		}
	}
	$j = @$datefunc( $dateformatstring, $i );

	$j = apply_filters( 'date_i18n', $j, $req_format, $i, $gmt );
	return $j;
}

function wp_maybe_decline_date( $date ) {
	global $wp_locale;
	if ( 'on' === _x( 'off', 'decline months names: on or off' ) ) {
		if ( @preg_match( '#^\d{1,2}\.? \w+#u', $date ) ) {
			$months = $wp_locale->month;

			foreach ( $months as $key => $month ) {
				$months[ $key ] = '#' . $month . '#';
			}

			$date = preg_replace( $months, $wp_locale->month_genitive, $date );
		}
	}
	$locale = get_locale();
	if ( 'ca' === $locale ) {
		$date = preg_replace( '# de ([ao])#i', " d'\\1", $date );
	}
	return $date;
}

function number_format_i18n( $number, $decimals = 0 ) {
	global $wp_locale;
	if ( isset( $wp_locale ) ) {
		$formatted = number_format( $number, absint( $decimals ), $wp_locale->number_format['decimal_point'], $wp_locale->number_format['thousands_sep'] );
	} else {
		$formatted = number_format( $number, absint( $decimals ) );
	}
	return apply_filters( 'number_format_i18n', $formatted );
}

function size_format( $bytes, $decimals = 0 ) {
	$quant = array( 'TB' => TB_IN_BYTES, 'GB' => GB_IN_BYTES, 'MB' => MB_IN_BYTES, 'kB' => KB_IN_BYTES, 'B'  => 1, );
	foreach ( $quant as $unit => $mag ) {
		if ( doubleval( $bytes ) >= $mag ) {
			return number_format_i18n( $bytes / $mag, $decimals ) . ' ' . $unit;
		}
	}
	return false;
}

function get_weekstartend( $mysqlstring, $start_of_week = '' ) {
	$my = substr( $mysqlstring, 0, 4 );
	$mm = substr( $mysqlstring, 8, 2 );
	$md = substr( $mysqlstring, 5, 2 );
	$day = mktime( 0, 0, 0, $md, $mm, $my );
	$weekday = date( 'w', $day );
	if ( !is_numeric($start_of_week) )
		$start_of_week = get_option( 'start_of_week' );
	if ( $weekday < $start_of_week )
		$weekday += 7;
	$start = $day - DAY_IN_SECONDS * ( $weekday - $start_of_week );
	$end = $start + WEEK_IN_SECONDS - 1;
	return compact( 'start', 'end' );
}

function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) )
		return @unserialize( $original );
	return $original;
}

function is_serialized( $data, $strict = true ) {
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
 	if ( 'N;' == $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	if ( $strict ) {
		$lastc = substr( $data, -1 );
		if ( ';' !== $lastc && '}' !== $lastc ) {
			return false;
		}
	} else {
		$semicolon = strpos( $data, ';' );
		$brace     = strpos( $data, '}' );
		if ( false === $semicolon && false === $brace )
			return false;
		if ( false !== $semicolon && $semicolon < 3 )
			return false;
		if ( false !== $brace && $brace < 4 )
			return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's' :
			if ( $strict ) {
				if ( '"' !== substr( $data, -2, 1 ) ) {
					return false;
				}
			} elseif ( false === strpos( $data, '"' ) ) {
				return false;
			}
		case 'a' :
		case 'O' :
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b' :
		case 'i' :
		case 'd' :
			$end = $strict ? '$' : '';
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
	}
	return false;
}

function is_serialized_string( $data ) {
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	if ( strlen( $data ) < 4 ) {
		return false;
	} elseif ( ':' !== $data[1] ) {
		return false;
	} elseif ( ';' !== substr( $data, -1 ) ) {
		return false;
	} elseif ( $data[0] !== 's' ) {
		return false;
	} elseif ( '"' !== substr( $data, -2, 1 ) ) {
		return false;
	} else {
		return true;
	}
}

function maybe_serialize( $data ) {
	if ( is_array( $data ) || is_object( $data ) )
		return serialize( $data );
	if ( is_serialized( $data, false ) )
		return serialize( $data );
	return $data;
}

function wp_extract_urls( $content ) {
	preg_match_all(
		"#([\"']?)("
			. "(?:([\w-]+:)?//?)"
			. "[^\s()<>]+"
			. "[.]"
			. "(?:"
				. "\([\w\d]+\)|"
				. "(?:"
					. "[^`!()\[\]{};:'\".,<>«»“”‘’\s]|"
					. "(?:[:]\d+)?/?"
				. ")+"
			. ")"
		. ")\\1#",
		$content,
		$post_links
	);

	$post_links = array_unique( array_map( 'html_entity_decode', $post_links[2] ) );

	return array_values( $post_links );
}

function do_enclose( $content, $post_ID ) {
	global $wpdb;
	include_once( ABSPATH . WPINC . '/class-IXR.php' );
	$post_links = array();
	$pung = get_enclosed( $post_ID );
	$post_links_temp = wp_extract_urls( $content );
	foreach ( $pung as $link_test ) {
		if ( ! in_array( $link_test, $post_links_temp ) ) {
			$mids = $wpdb->get_col( $wpdb->prepare("SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = 'enclosure' AND meta_value LIKE %s", $post_ID, $wpdb->esc_like( $link_test ) . '%') );
			foreach ( $mids as $mid )
				delete_metadata_by_mid( 'post', $mid );
		}
	}
	foreach ( (array) $post_links_temp as $link_test ) {
		if ( !in_array( $link_test, $pung ) ) {
			$test = @parse_url( $link_test );
			if ( false === $test )
				continue;
			if ( isset( $test['query'] ) )
				$post_links[] = $link_test;
			elseif ( isset($test['path']) && ( $test['path'] != '/' ) &&  ($test['path'] != '' ) )
				$post_links[] = $link_test;
		}
	}
	$post_links = apply_filters( 'enclosure_links', $post_links, $post_ID );
	foreach ( (array) $post_links as $url ) {
		if ( $url != '' && !$wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = 'enclosure' AND meta_value LIKE %s", $post_ID, $wpdb->esc_like( $url ) . '%' ) ) ) {
			if ( $headers = wp_get_http_headers( $url) ) {
				$len = isset( $headers['content-length'] ) ? (int) $headers['content-length'] : 0;
				$type = isset( $headers['content-type'] ) ? $headers['content-type'] : '';
				$allowed_types = array( 'video', 'audio' );
				$url_parts = @parse_url( $url );
				if ( false !== $url_parts ) {
					$extension = pathinfo( $url_parts['path'], PATHINFO_EXTENSION );
					if ( !empty( $extension ) ) {
						foreach ( wp_get_mime_types() as $exts => $mime ) {
							if ( preg_match( '!^(' . $exts . ')$!i', $extension ) ) {
								$type = $mime;
								break;
							}
						}
					}
				}
				if ( in_array( substr( $type, 0, strpos( $type, "/" ) ), $allowed_types ) ) {
					add_post_meta( $post_ID, 'enclosure', "$url\n$len\n$mime\n" );
				}
			}
		}
	}
}

function wp_get_http_headers( $url ) {
	$response = wp_safe_remote_head( $url );
	if ( is_wp_error( $response ) )
		return false;
	return wp_remote_retrieve_headers( $response );
}

function is_new_day() {
	global $currentday, $previousday;
	if ( $currentday != $previousday )
		return 1;
	else
		return 0;
}

function build_query( $data ) {
	return _http_build_query( $data, null, '&', '', false );
}

function _http_build_query( $data, $prefix = null, $sep = null, $key = '', $urlencode = true ) {
	$ret = array();
	foreach ( (array) $data as $k => $v ) {
		if ( $urlencode)
			$k = urlencode($k);
		if ( is_int($k) && $prefix != null )
			$k = $prefix.$k;
		if ( !empty($key) )
			$k = $key . '%5B' . $k . '%5D';
		if ( $v === null )
			continue;
		elseif ( $v === false )
			$v = '0';
		if ( is_array($v) || is_object($v) )
			array_push($ret,_http_build_query($v, '', $sep, $k, $urlencode));
		elseif ( $urlencode )
			array_push($ret, $k.'='.urlencode($v));
		else
			array_push($ret, $k.'='.$v);
	}
	if ( null === $sep )
		$sep = ini_get('arg_separator.output');
	return implode($sep, $ret);
}

function add_query_arg() {
	$args = func_get_args();
	if ( is_array( $args[0] ) ) {
		if ( count( $args ) < 2 || false === $args[1] )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = $args[1];
	} else {
		if ( count( $args ) < 3 || false === $args[2] )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = $args[2];
	}

	if ( $frag = strstr( $uri, '#' ) )
		$uri = substr( $uri, 0, -strlen( $frag ) );
	else
		$frag = '';

	if ( 0 === stripos( $uri, 'http://' ) ) {
		$protocol = 'http://';
		$uri = substr( $uri, 7 );
	} elseif ( 0 === stripos( $uri, 'https://' ) ) {
		$protocol = 'https://';
		$uri = substr( $uri, 8 );
	} else {
		$protocol = '';
	}

	if ( strpos( $uri, '?' ) !== false ) {
		list( $base, $query ) = explode( '?', $uri, 2 );
		$base .= '?';
	} elseif ( $protocol || strpos( $uri, '=' ) === false ) {
		$base = $uri . '?';
		$query = '';
	} else {
		$base = '';
		$query = $uri;
	}

	wp_parse_str( $query, $qs );
	$qs = urlencode_deep( $qs );
	if ( is_array( $args[0] ) ) {
		foreach ( $args[0] as $k => $v ) {
			$qs[ $k ] = $v;
		}
	} else {
		$qs[ $args[0] ] = $args[1];
	}

	foreach ( $qs as $k => $v ) {
		if ( $v === false )
			unset( $qs[$k] );
	}
	$ret = build_query( $qs );
	$ret = trim( $ret, '?' );
	$ret = preg_replace( '#=(&|$)#', '$1', $ret );
	$ret = $protocol . $base . $ret . $frag;
	$ret = rtrim( $ret, '?' );
	return $ret;
}

function remove_query_arg( $key, $query = false ) {
	if ( is_array( $key ) ) {
		foreach ( $key as $k )
			$query = add_query_arg( $k, false, $query );
		return $query;
	}
	return add_query_arg( $key, false, $query );
}

function wp_removable_query_args() {
	$removable_query_args = array(
		'activate',
		'activated',
		'approved',
		'deactivate',
		'deleted',
		'disabled',
		'enabled',
		'error',
		'locked',
		'message',
		'same',
		'saved',
		'settings-updated',
		'skipped',
		'spammed',
		'trashed',
		'unspammed',
		'untrashed',
		'update',
		'updated',
		'wp-post-new-reload',
	);

	return apply_filters( 'removable_query_args', $removable_query_args );
}

function add_magic_quotes( $array ) {
	foreach ( (array) $array as $k => $v ) {
		if ( is_array( $v ) ) {
			$array[$k] = add_magic_quotes( $v );
		} else {
			$array[$k] = addslashes( $v );
		}
	}
	return $array;
}

function wp_remote_fopen( $uri ) {
	$parsed_url = @parse_url( $uri );
	if ( !$parsed_url || !is_array( $parsed_url ) )
		return false;
	$options = array();
	$options['timeout'] = 10;
	$response = wp_safe_remote_get( $uri, $options );
	if ( is_wp_error( $response ) )
		return false;
	return wp_remote_retrieve_body( $response );
}

function wp( $query_vars = '' ) {
	global $wp, $wp_query, $wp_the_query;
	$wp->main( $query_vars );
	if ( !isset($wp_the_query) )
		$wp_the_query = $wp_query;
}

function get_status_header_desc( $code ) {
	global $wp_header_to_desc;
	$code = absint( $code );
	if ( !isset( $wp_header_to_desc ) ) {
		$wp_header_to_desc = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			226 => 'IM Used',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Reserved',
			307 => 'Temporary Redirect',
			308 => 'Permanent Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			418 => 'I\'m a teapot',
			421 => 'Misdirected Request',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			426 => 'Upgrade Required',
			428 => 'Precondition Required',
			429 => 'Too Many Requests',
			431 => 'Request Header Fields Too Large',
			451 => 'Unavailable For Legal Reasons',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			510 => 'Not Extended',
			511 => 'Network Authentication Required',
		);
	}
	if ( isset( $wp_header_to_desc[$code] ) )
		return $wp_header_to_desc[$code];
	else
		return '';
}

function status_header( $code, $description = '' ) {
	if ( ! $description ) { $description = get_status_header_desc( $code ); }
	if ( empty( $description ) ) { return; }
	$protocol = wp_get_server_protocol();
	$status_header = "$protocol $code $description";
	if ( function_exists( 'apply_filters' ) )
		$status_header = apply_filters( 'status_header', $status_header, $code, $description, $protocol );
	@header( $status_header, true, $code );
}

function wp_get_nocache_headers() {
	$headers = array( 'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache' );
	if ( function_exists('apply_filters') ) {
		$headers = (array) apply_filters( 'nocache_headers', $headers );
	}
	$headers['Last-Modified'] = false;
	return $headers;
}

function nocache_headers() {
	$headers = wp_get_nocache_headers();
	unset( $headers['Last-Modified'] );
	@header_remove( 'Last-Modified' );
	foreach ( $headers as $name => $field_value )
		@header("{$name}: {$field_value}");
}

function cache_javascript_headers() {
	$expiresOffset = 10 * 86400;
	header( "Content-Type: text/javascript; charset=" . get_bloginfo( 'charset' ) );
	header( "Vary: Accept-Encoding" );
	header( "Expires: " . gmdate( "D, d M Y H:i:s", time() + $expiresOffset ) . " GMT" );
}

function bool_from_yn( $yn ) {
	return ( strtolower( $yn ) == 'y' );
}

function do_robots() {
	header( 'Content-Type: text/plain; charset=utf-8' );
	do_action( 'do_robotstxt' );
	$output = "User-agent: *\n";
	$site_url = parse_url( site_url() );
	$path = ( !empty( $site_url['path'] ) ) ? $site_url['path'] : '';
	$output .= "Disallow: $path/wp-admin/\n";
	$output .= "Allow: $path/wp-admin/admin-ajax.php\n";
	echo apply_filters( 'robots_txt', $output, $public );
}

function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
	$actionurl = str_replace( '&amp;', '&', $actionurl );
	return esc_html( add_query_arg( $name, wp_create_nonce( $action ), $actionurl ) );
}

function wp_nonce_field( $action = -1, $name = "_wpnonce", $referer = true , $echo = true ) {
	$name = esc_attr( $name );
	$nonce_field = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';
	if ( $referer )
		$nonce_field .= wp_referer_field( false );
	if ( $echo )
		echo $nonce_field;
	return $nonce_field;
}

function wp_referer_field( $echo = true ) {
	$referer_field = '<input type="hidden" name="_wp_http_referer" value="'. esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) . '" />';
	if ( $echo )
		echo $referer_field;
	return $referer_field;
}

function wp_original_referer_field( $echo = true, $jump_back_to = 'current' ) {
	if ( ! $ref = wp_get_original_referer() ) {
		$ref = 'previous' == $jump_back_to ? wp_get_referer() : wp_unslash( $_SERVER['REQUEST_URI'] );
	}
	$orig_referer_field = '<input type="hidden" name="_wp_original_http_referer" value="' . esc_attr( $ref ) . '" />';
	if ( $echo )
		echo $orig_referer_field;
	return $orig_referer_field;
}

function wp_get_referer() {
	if ( ! function_exists( 'wp_validate_redirect' ) ) {
		return false;
	}
	$ref = wp_get_raw_referer();
	if ( $ref && $ref !== wp_unslash( $_SERVER['REQUEST_URI'] ) && $ref !== home_url() . wp_unslash( $_SERVER['REQUEST_URI'] ) ) {
		return wp_validate_redirect( $ref, false );
	}
	return false;
}

function wp_get_raw_referer() {
	if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
		return wp_unslash( $_REQUEST['_wp_http_referer'] );
	} else if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		return wp_unslash( $_SERVER['HTTP_REFERER'] );
	}
	return false;
}

function wp_get_original_referer() {
	if ( ! empty( $_REQUEST['_wp_original_http_referer'] ) && function_exists( 'wp_validate_redirect' ) )
		return wp_validate_redirect( wp_unslash( $_REQUEST['_wp_original_http_referer'] ), false );
	return false;
}

function wp_mkdir_p( $target ) {
	$wrapper = null;
	if ( wp_is_stream( $target ) ) {
		list( $wrapper, $target ) = explode( '://', $target, 2 );
	}
	$target = str_replace( '//', '/', $target );
	if ( $wrapper !== null ) {
		$target = $wrapper . '://' . $target;
	}
	$target = rtrim($target, '/');
	if ( empty($target) )
		$target = '/';

	if ( file_exists( $target ) )
		return @is_dir( $target );
	$target_parent = dirname( $target );
	while ( '.' != $target_parent && ! is_dir( $target_parent ) ) {
		$target_parent = dirname( $target_parent );
	}
	if ( $stat = @stat( $target_parent ) ) {
		$dir_perms = $stat['mode'] & 0007777;
	} else {
		$dir_perms = 0777;
	}
	if ( @mkdir( $target, $dir_perms, true ) ) {
		if ( $dir_perms != ( $dir_perms & ~umask() ) ) {
			$folder_parts = explode( '/', substr( $target, strlen( $target_parent ) + 1 ) );
			for ( $i = 1, $c = count( $folder_parts ); $i <= $c; $i++ ) {
				@chmod( $target_parent . '/' . implode( '/', array_slice( $folder_parts, 0, $i ) ), $dir_perms );
			}
		}
		return true;
	}
	return false;
}

function path_is_absolute( $path ) {
	if ( realpath($path) == $path )
		return true;
	if ( strlen($path) == 0 || $path[0] == '.' )
		return false;
	if ( preg_match('#^[a-zA-Z]:\\\\#', $path) )
		return true;
	return ( $path[0] == '/' || $path[0] == '\\' );
}

function path_join( $base, $path ) {
	if ( path_is_absolute($path) )
		return $path;
	return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function wp_normalize_path( $path ) {
	$path = str_replace( '\\', '/', $path );
	$path = preg_replace( '|(?<=.)/+|', '/', $path );
	if ( ':' === substr( $path, 1, 1 ) ) {
		$path = ucfirst( $path );
	}
	return $path;
}

function get_temp_dir() {
	static $temp = '';
	if ( defined('WP_TEMP_DIR') )
		return trailingslashit(WP_TEMP_DIR);
	if ( $temp )
		return trailingslashit( $temp );
	if ( function_exists('sys_get_temp_dir') ) {
		$temp = sys_get_temp_dir();
		if ( @is_dir( $temp ) && @is_writable( $temp ) )
			return trailingslashit( $temp );
	}
	$temp = ini_get('upload_tmp_dir');
	if ( @is_dir( $temp ) && @is_writable( $temp ) )
		return trailingslashit( $temp );
	$temp = WP_CONTENT_DIR . '/';
	if ( is_dir( $temp ) && @is_writable( $temp ) )
		return $temp;
	return '/tmp/';
}

function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
	static $cache = array(), $tested_paths = array();
	$key = sprintf( '%d-%s', get_current_blog_id(), (string) $time );
	if ( $refresh_cache || empty( $cache[ $key ] ) ) {
		$cache[ $key ] = _wp_upload_dir( $time );
	}
	$uploads = apply_filters( 'upload_dir', $cache[ $key ] );
	if ( $create_dir ) {
		$path = $uploads['path'];
		if ( array_key_exists( $path, $tested_paths ) ) {
			$uploads['error'] = $tested_paths[ $path ];
		} else {
			if ( ! wp_mkdir_p( $path ) ) {
				if ( 0 === strpos( $uploads['basedir'], ABSPATH ) ) {
					$error_path = str_replace( ABSPATH, '', $uploads['basedir'] ) . $uploads['subdir'];
				} else {
					$error_path = basename( $uploads['basedir'] ) . $uploads['subdir'];
				}
				$uploads['error'] = sprintf( 'Unable to create directory %s. Is its parent directory writable by the server?', esc_html( $error_path ) );
			}
			$tested_paths[ $path ] = $uploads['error'];
		}
	}
	return $uploads;
}

function _wp_upload_dir( $time = null ) {
	$siteurl = get_option( 'siteurl' );
	$upload_path = trim( get_option( 'upload_path' ) );
	if ( empty( $upload_path ) || 'wp-content/uploads' == $upload_path ) {
		$dir = WP_CONTENT_DIR . '/uploads';
	} elseif ( 0 !== strpos( $upload_path, ABSPATH ) ) {
		$dir = path_join( ABSPATH, $upload_path );
	} else {
		$dir = $upload_path;
	}
	if ( !$url = get_option( 'upload_url_path' ) ) {
		if ( empty($upload_path) || ( 'wp-content/uploads' == $upload_path ) || ( $upload_path == $dir ) )
			$url = WP_CONTENT_URL . '/uploads';
		else
			$url = trailingslashit( $siteurl ) . $upload_path;
	}
	if ( defined( 'UPLOADS' ) ) {
		$dir = ABSPATH . UPLOADS;
		$url = trailingslashit( $siteurl ) . UPLOADS;
	}

	$basedir = $dir;
	$baseurl = $url;

	$subdir = '';
	if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
		if ( !$time )
			$time = current_time( 'mysql' );
		$y = substr( $time, 0, 4 );
		$m = substr( $time, 5, 2 );
		$subdir = "/$y/$m";
	}
	$dir .= $subdir;
	$url .= $subdir;
	return array(
		'path'    => $dir,
		'url'     => $url,
		'subdir'  => $subdir,
		'basedir' => $basedir,
		'baseurl' => $baseurl,
		'error'   => false,
	);
}

function wp_unique_filename( $dir, $filename, $unique_filename_callback = null ) {
	$filename = sanitize_file_name($filename);
	$info = pathinfo($filename);
	$ext = !empty($info['extension']) ? '.' . $info['extension'] : '';
	$name = basename($filename, $ext);
	if ( $name === $ext )
		$name = '';
	if ( $unique_filename_callback && is_callable( $unique_filename_callback ) ) {
		$filename = call_user_func( $unique_filename_callback, $dir, $name, $ext );
	} else {
		$number = '';
		if ( $ext && strtolower($ext) != $ext ) {
			$ext2 = strtolower($ext);
			$filename2 = preg_replace( '|' . preg_quote($ext) . '$|', $ext2, $filename );
			while ( file_exists($dir . "/$filename") || file_exists($dir . "/$filename2") ) {
				$new_number = $number + 1;
				$filename = str_replace( array( "-$number$ext", "$number$ext" ), "-$new_number$ext", $filename );
				$filename2 = str_replace( array( "-$number$ext2", "$number$ext2" ), "-$new_number$ext2", $filename2 );
				$number = $new_number;
			}
			return apply_filters( 'wp_unique_filename', $filename2, $ext, $dir, $unique_filename_callback );
		}

		while ( file_exists( $dir . "/$filename" ) ) {
			if ( '' == "$number$ext" ) {
				$filename = "$filename-" . ++$number;
			} else {
				$filename = str_replace( array( "-$number$ext", "$number$ext" ), "-" . ++$number . $ext, $filename );
			}
		}
	}
	return apply_filters( 'wp_unique_filename', $filename, $ext, $dir, $unique_filename_callback );
}

function wp_upload_bits( $name, $bits, $time = null ) {
	if ( empty( $name ) )
		return array( 'error' => 'Empty filename' );
	$wp_filetype = wp_check_filetype( $name );
	if ( ! $wp_filetype['ext'] && ! current_user_can( 'unfiltered_upload' ) )
		return array( 'error' => 'Invalid file type' );
	$upload = wp_upload_dir( $time );
	if ( $upload['error'] !== false )
		return $upload;
	$upload_bits_error = apply_filters( 'wp_upload_bits', array( 'name' => $name, 'bits' => $bits, 'time' => $time ) );
	if ( !is_array( $upload_bits_error ) ) {
		$upload[ 'error' ] = $upload_bits_error;
		return $upload;
	}
	$filename = wp_unique_filename( $upload['path'], $name );
	$new_file = $upload['path'] . "/$filename";
	if ( ! wp_mkdir_p( dirname( $new_file ) ) ) {
		if ( 0 === strpos( $upload['basedir'], ABSPATH ) )
			$error_path = str_replace( ABSPATH, '', $upload['basedir'] ) . $upload['subdir'];
		else
			$error_path = basename( $upload['basedir'] ) . $upload['subdir'];
		$message = sprintf( 'Unable to create directory %s. Is its parent directory writable by the server?', $error_path );
		return array( 'error' => $message );
	}
	$ifp = @ fopen( $new_file, 'wb' );
	if ( ! $ifp )
		return array( 'error' => sprintf( 'Could not write file %s', $new_file ) );
	@fwrite( $ifp, $bits );
	fclose( $ifp );
	clearstatcache();
	$stat = @ stat( dirname( $new_file ) );
	$perms = $stat['mode'] & 0007777;
	$perms = $perms & 0000666;
	@ chmod( $new_file, $perms );
	clearstatcache();
	$url = $upload['url'] . "/$filename";
	return apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $wp_filetype['type'], 'error' => false ), 'sideload' );
}

function wp_ext2type( $ext ) {
	$ext = strtolower( $ext );
	$ext2type = apply_filters( 'ext2type', array(
		'image'       => array( 'jpg', 'jpeg', 'jpe',  'gif',  'png',  'bmp',   'tif',  'tiff', 'ico' ),
		'audio'       => array( 'aac', 'ac3',  'aif',  'aiff', 'm3a',  'm4a',   'm4b',  'mka',  'mp1',  'mp2',  'mp3', 'ogg', 'oga', 'ram', 'wav', 'wma' ),
		'video'       => array( '3g2',  '3gp', '3gpp', 'asf', 'avi',  'divx', 'dv',   'flv',  'm4v',   'mkv',  'mov',  'mp4',  'mpeg', 'mpg', 'mpv', 'ogm', 'ogv', 'qt',  'rm', 'vob', 'wmv' ),
		'document'    => array( 'doc', 'docx', 'docm', 'dotm', 'odt',  'pages', 'pdf',  'xps',  'oxps', 'rtf',  'wp', 'wpd', 'psd', 'xcf' ),
		'spreadsheet' => array( 'numbers',     'ods',  'xls',  'xlsx', 'xlsm',  'xlsb' ),
		'interactive' => array( 'swf', 'key',  'ppt',  'pptx', 'pptm', 'pps',   'ppsx', 'ppsm', 'sldx', 'sldm', 'odp' ),
		'text'        => array( 'asc', 'csv',  'tsv',  'txt' ),
		'archive'     => array( 'bz2', 'cab',  'dmg',  'gz',   'rar',  'sea',   'sit',  'sqx',  'tar',  'tgz',  'zip', '7z' ),
		'code'        => array( 'css', 'htm',  'html', 'php',  'js' ),
	) );

	foreach ( $ext2type as $type => $exts )
		if ( in_array( $ext, $exts ) )
			return $type;
}

function wp_check_filetype( $filename, $mimes = null ) {
	if ( empty($mimes) )
		$mimes = get_allowed_mime_types();
	$type = false;
	$ext = false;
	foreach ( $mimes as $ext_preg => $mime_match ) {
		$ext_preg = '!\.(' . $ext_preg . ')$!i';
		if ( preg_match( $ext_preg, $filename, $ext_matches ) ) {
			$type = $mime_match;
			$ext = $ext_matches[1];
			break;
		}
	}
	return compact( 'ext', 'type' );
}

function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	$proper_filename = false;
	$wp_filetype = wp_check_filetype( $filename, $mimes );
	$ext = $wp_filetype['ext'];
	$type = $wp_filetype['type'];
	if ( ! file_exists( $file ) ) {
		return compact( 'ext', 'type', 'proper_filename' );
	}
	if ( $type && 0 === strpos( $type, 'image/' ) && function_exists('getimagesize') ) {
		$imgstats = @getimagesize( $file );
		if ( !empty($imgstats['mime']) && $imgstats['mime'] != $type ) {
			$mime_to_ext = apply_filters( 'getimagesize_mimes_to_exts', array(
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/gif'  => 'gif',
				'image/bmp'  => 'bmp',
				'image/tiff' => 'tif',
			) );
			if ( ! empty( $mime_to_ext[ $imgstats['mime'] ] ) ) {
				$filename_parts = explode( '.', $filename );
				array_pop( $filename_parts );
				$filename_parts[] = $mime_to_ext[ $imgstats['mime'] ];
				$new_filename = implode( '.', $filename_parts );

				if ( $new_filename != $filename ) {
					$proper_filename = $new_filename;
				}
				$wp_filetype = wp_check_filetype( $new_filename, $mimes );
				$ext = $wp_filetype['ext'];
				$type = $wp_filetype['type'];
			}
		}
	}
	return apply_filters( 'wp_check_filetype_and_ext', compact( 'ext', 'type', 'proper_filename' ), $file, $filename, $mimes );
}

function wp_get_mime_types() {
	return apply_filters( 'mime_types', array(
	'jpg|jpeg|jpe' => 'image/jpeg',
	'gif' => 'image/gif',
	'png' => 'image/png',
	'bmp' => 'image/bmp',
	'tiff|tif' => 'image/tiff',
	'ico' => 'image/x-icon',
	'asf|asx' => 'video/x-ms-asf',
	'wmv' => 'video/x-ms-wmv',
	'wmx' => 'video/x-ms-wmx',
	'wm' => 'video/x-ms-wm',
	'avi' => 'video/avi',
	'divx' => 'video/divx',
	'flv' => 'video/x-flv',
	'mov|qt' => 'video/quicktime',
	'mpeg|mpg|mpe' => 'video/mpeg',
	'mp4|m4v' => 'video/mp4',
	'ogv' => 'video/ogg',
	'webm' => 'video/webm',
	'mkv' => 'video/x-matroska',
	'3gp|3gpp' => 'video/3gpp',
	'3g2|3gp2' => 'video/3gpp2',
	'txt|asc|c|cc|h|srt' => 'text/plain',
	'csv' => 'text/csv',
	'tsv' => 'text/tab-separated-values',
	'ics' => 'text/calendar',
	'rtx' => 'text/richtext',
	'css' => 'text/css',
	'htm|html' => 'text/html',
	'vtt' => 'text/vtt',
	'dfxp' => 'application/ttaf+xml',
	'mp3|m4a|m4b' => 'audio/mpeg',
	'ra|ram' => 'audio/x-realaudio',
	'wav' => 'audio/wav',
	'ogg|oga' => 'audio/ogg',
	'mid|midi' => 'audio/midi',
	'wma' => 'audio/x-ms-wma',
	'wax' => 'audio/x-ms-wax',
	'mka' => 'audio/x-matroska',
	'rtf' => 'application/rtf',
	'js' => 'application/javascript',
	'pdf' => 'application/pdf',
	'swf' => 'application/x-shockwave-flash',
	'class' => 'application/java',
	'tar' => 'application/x-tar',
	'zip' => 'application/zip',
	'gz|gzip' => 'application/x-gzip',
	'rar' => 'application/rar',
	'7z' => 'application/x-7z-compressed',
	'exe' => 'application/x-msdownload',
	'psd' => 'application/octet-stream',
	'xcf' => 'application/octet-stream',
	'doc' => 'application/msword',
	'pot|pps|ppt' => 'application/vnd.ms-powerpoint',
	'wri' => 'application/vnd.ms-write',
	'xla|xls|xlt|xlw' => 'application/vnd.ms-excel',
	'mdb' => 'application/vnd.ms-access',
	'mpp' => 'application/vnd.ms-project',
	'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'docm' => 'application/vnd.ms-word.document.macroEnabled.12',
	'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
	'dotm' => 'application/vnd.ms-word.template.macroEnabled.12',
	'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
	'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
	'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
	'xltm' => 'application/vnd.ms-excel.template.macroEnabled.12',
	'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12',
	'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	'pptm' => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
	'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
	'ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
	'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
	'potm' => 'application/vnd.ms-powerpoint.template.macroEnabled.12',
	'ppam' => 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
	'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
	'sldm' => 'application/vnd.ms-powerpoint.slide.macroEnabled.12',
	'onetoc|onetoc2|onetmp|onepkg' => 'application/onenote',
	'oxps' => 'application/oxps',
	'xps' => 'application/vnd.ms-xpsdocument',
	'odt' => 'application/vnd.oasis.opendocument.text',
	'odp' => 'application/vnd.oasis.opendocument.presentation',
	'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	'odg' => 'application/vnd.oasis.opendocument.graphics',
	'odc' => 'application/vnd.oasis.opendocument.chart',
	'odb' => 'application/vnd.oasis.opendocument.database',
	'odf' => 'application/vnd.oasis.opendocument.formula',
	'wp|wpd' => 'application/wordperfect',
	'key' => 'application/vnd.apple.keynote',
	'numbers' => 'application/vnd.apple.numbers',
	'pages' => 'application/vnd.apple.pages',
	) );
}

function get_allowed_mime_types( $user = null ) {
	$t = wp_get_mime_types();
	unset( $t['swf'], $t['exe'] );
	if ( function_exists( 'current_user_can' ) )
		$unfiltered = $user ? user_can( $user, 'unfiltered_html' ) : current_user_can( 'unfiltered_html' );
	if ( empty( $unfiltered ) )
		unset( $t['htm|html'] );
	return apply_filters( 'upload_mimes', $t, $user );
}

function wp_nonce_ays( $action ) {
	if ( 'log-out' == $action ) {
		$html = sprintf( 'You are attempting to log out of %s', get_bloginfo( 'name' ) ) . '</p><p>';
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
		$html .= sprintf( "Do you really want to <a href='%s'>log out</a>?", wp_logout_url( $redirect_to ) );
	} else {
		$html = 'Are you sure you want to do this?';
		if ( wp_get_referer() )
			$html .= "</p><p><a href='" . esc_url( remove_query_arg( 'updated', wp_get_referer() ) ) . "'>Please try again.</a>";
	}

	wp_die( $html, 'WordPress Failure Notice', 403 );
}

function wp_die( $message = '', $title = '', $args = array() ) {
	if ( is_int( $args ) ) {
		$args = array( 'response' => $args );
	} elseif ( is_int( $title ) ) {
		$args  = array( 'response' => $title );
		$title = '';
	}
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		$function = apply_filters( 'wp_die_ajax_handler', '_ajax_wp_die_handler' );
	} else {
		$function = apply_filters( 'wp_die_handler', '_default_wp_die_handler' );
	}
	call_user_func( $function, $message, $title, $args );
}

function _default_wp_die_handler( $message, $title = '', $args = array() ) {
	$defaults = array( 'response' => 500 );
	$r = wp_parse_args($args, $defaults);
	if ( function_exists( 'is_wp_error' ) && is_wp_error( $message ) ) {
		if ( empty( $title ) ) {
			$error_data = $message->get_error_data();
			if ( is_array( $error_data ) && isset( $error_data['title'] ) )
				$title = $error_data['title'];
		}
		$errors = $message->get_error_messages();
		switch ( count( $errors ) ) {
		case 0 :
			$message = '';
			break;
		case 1 :
			$message = "<p>{$errors[0]}</p>";
			break;
		default :
			$message = "<ul>\n\t\t<li>" . join( "</li>\n\t\t<li>", $errors ) . "</li>\n\t</ul>";
			break;
		}
	} elseif ( is_string( $message ) ) {
		$message = "<p>$message</p>";
	}

	if ( isset( $r['back_link'] ) && $r['back_link'] ) {
		$back_text = '&laquo; Back';
		$message .= "\n<p><a href='javascript:history.back()'>$back_text</a></p>";
	}

	if ( ! did_action( 'admin_head' ) ) :
		if ( !headers_sent() ) {
			status_header( $r['response'] );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		if ( empty($title) )
			$title = 'WordPress &rsaquo; Error';
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php if ( function_exists( 'language_attributes' ) ) language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width">
	<title><?php echo $title ?></title>
	<style type="text/css">
		html {background: #f1f1f1;}
		body {background: #fff;color: #444;font-family: sans-serif;margin: 2em auto;padding: 1em 2em;max-width: 700px;-webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.13);box-shadow: 0 1px 3px rgba(0,0,0,0.13);}
		h1 {
			border-bottom: 1px solid #dadada;
			clear: both;
			color: #666;
			font: 24px "Open Sans", sans-serif;
			margin: 30px 0 0 0;
			padding: 0;
			padding-bottom: 7px;
		}
		#error-page {margin-top: 50px;}
		#error-page p {font-size: 14px;line-height: 1.5;margin: 25px 0 20px;}
		#error-page code {font-family: Consolas, Monaco, monospace;}
		ul li {
			margin-bottom: 10px;
			font-size: 14px ;
		}
		a {color: #0073aa;}
		a:hover, a:active {color: #00a0d2;}
		a:focus {
			color: #124964;
		    -webkit-box-shadow:
		    	0 0 0 1px #5b9dd9,
				0 0 2px 1px rgba(30, 140, 190, .8);
		    box-shadow:
		    	0 0 0 1px #5b9dd9,
				0 0 2px 1px rgba(30, 140, 190, .8);
			outline: none;
		}
		.button {
			background: #f7f7f7;
			border: 1px solid #ccc;
			color: #555;
			display: inline-block;
			text-decoration: none;
			font-size: 13px;
			line-height: 26px;
			height: 28px;
			margin: 0;
			padding: 0 10px 1px;
			cursor: pointer;
			-webkit-border-radius: 3px;
			-webkit-appearance: none;
			border-radius: 3px;
			white-space: nowrap;
			-webkit-box-sizing: border-box;
			-moz-box-sizing:    border-box;
			box-sizing:         border-box;

			-webkit-box-shadow: 0 1px 0 #ccc;
			box-shadow: 0 1px 0 #ccc;
		 	vertical-align: top;
		}

		.button.button-large {
			height: 30px;
			line-height: 28px;
			padding: 0 12px 2px;
		}

		.button:hover,
		.button:focus {
			background: #fafafa;
			border-color: #999;
			color: #23282d;
		}

		.button:focus  {
			border-color: #5b9dd9;
			-webkit-box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
			box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
			outline: none;
		}

		.button:active {
			background: #eee;
			border-color: #999;
		 	-webkit-box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		 	box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		 	-webkit-transform: translateY(1px);
		 	-ms-transform: translateY(1px);
		 	transform: translateY(1px);
		}
	</style>
</head>
<body id="error-page">
<?php endif; // ! did_action( 'admin_head' ) ?>
	<?php echo $message; ?>
</body>
</html>
<?php
	die();
}

function _xmlrpc_wp_die_handler( $message, $title = '', $args = array() ) {
	global $wp_xmlrpc_server;
	$defaults = array( 'response' => 500 );
	$r = wp_parse_args($args, $defaults);
	if ( $wp_xmlrpc_server ) {
		$error = new IXR_Error( $r['response'] , $message);
		$wp_xmlrpc_server->output( $error->getXml() );
	}
	die();
}

function _ajax_wp_die_handler( $message = '' ) {
	if ( is_scalar( $message ) )
		die( (string) $message );
	die( '0' );
}

function _scalar_wp_die_handler( $message = '' ) {
	if ( is_scalar( $message ) )
		die( (string) $message );
	die();
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	if ( version_compare( PHP_VERSION, '5.5', '>=' ) ) {
		$args = array( $data, $options, $depth );
	} elseif ( version_compare( PHP_VERSION, '5.3', '>=' ) ) {
		$args = array( $data, $options );
	} else {
		$args = array( $data );
	}
	$data = _wp_json_prepare_data( $data );
	$json = @call_user_func_array( 'json_encode', $args );
	if ( false !== $json && ( version_compare( PHP_VERSION, '5.5', '>=' ) || false === strpos( $json, 'null' ) ) )  {
		return $json;
	}
	try {
		$args[0] = _wp_json_sanity_check( $data, $depth );
	} catch ( Exception $e ) {
		return false;
	}
	return call_user_func_array( 'json_encode', $args );
}

function _wp_json_sanity_check( $data, $depth ) {
	if ( $depth < 0 ) {
		throw new Exception( 'Reached depth limit' );
	}

	if ( is_array( $data ) ) {
		$output = array();
		foreach ( $data as $id => $el ) {
			if ( is_string( $id ) ) {
				$clean_id = _wp_json_convert_string( $id );
			} else {
				$clean_id = $id;
			}
			if ( is_array( $el ) || is_object( $el ) ) {
				$output[ $clean_id ] = _wp_json_sanity_check( $el, $depth - 1 );
			} elseif ( is_string( $el ) ) {
				$output[ $clean_id ] = _wp_json_convert_string( $el );
			} else {
				$output[ $clean_id ] = $el;
			}
		}
	} elseif ( is_object( $data ) ) {
		$output = new stdClass;
		foreach ( $data as $id => $el ) {
			if ( is_string( $id ) ) {
				$clean_id = _wp_json_convert_string( $id );
			} else {
				$clean_id = $id;
			}
			if ( is_array( $el ) || is_object( $el ) ) {
				$output->$clean_id = _wp_json_sanity_check( $el, $depth - 1 );
			} elseif ( is_string( $el ) ) {
				$output->$clean_id = _wp_json_convert_string( $el );
			} else {
				$output->$clean_id = $el;
			}
		}
	} elseif ( is_string( $data ) ) {
		return _wp_json_convert_string( $data );
	} else {
		return $data;
	}

	return $output;
}

function _wp_json_convert_string( $string ) {
	static $use_mb = null;
	if ( is_null( $use_mb ) ) {
		$use_mb = function_exists( 'mb_convert_encoding' );
	}
	if ( $use_mb ) {
		$encoding = mb_detect_encoding( $string, mb_detect_order(), true );
		if ( $encoding ) {
			return mb_convert_encoding( $string, 'UTF-8', $encoding );
		} else {
			return mb_convert_encoding( $string, 'UTF-8', 'UTF-8' );
		}
	} else {
		return wp_check_invalid_utf8( $string, true );
	}
}

function _wp_json_prepare_data( $data ) {
	if ( ! defined( 'WP_JSON_SERIALIZE_COMPATIBLE' ) || WP_JSON_SERIALIZE_COMPATIBLE === false ) {
		return $data;
	}
	switch ( gettype( $data ) ) {
		case 'boolean':
		case 'integer':
		case 'double':
		case 'string':
		case 'NULL':
			return $data;
		case 'array':
			return array_map( '_wp_json_prepare_data', $data );
		case 'object':
			if ( ! is_object( $data ) ) {
				return null;
			}

			if ( $data instanceof JsonSerializable ) {
				$data = $data->jsonSerialize();
			} else {
				$data = get_object_vars( $data );
			}
			return _wp_json_prepare_data( $data );
		default:
			return null;
	}
}

function wp_send_json( $response ) {
	@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
	echo wp_json_encode( $response );
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		wp_die();
	else
		die;
}

function wp_send_json_success( $data = null ) {
	$response = array( 'success' => true );
	if ( isset( $data ) )
		$response['data'] = $data;
	wp_send_json( $response );
}

function wp_send_json_error( $data = null ) {
	$response = array( 'success' => false );
	if ( isset( $data ) ) {
		if ( is_wp_error( $data ) ) {
			$result = array();
			foreach ( $data->errors as $code => $messages ) {
				foreach ( $messages as $message ) {
					$result[] = array( 'code' => $code, 'message' => $message );
				}
			}
			$response['data'] = $result;
		} else {
			$response['data'] = $data;
		}
	}
	wp_send_json( $response );
}

function _config_wp_home( $url = '' ) {
	if ( defined( 'WP_HOME' ) )
		return untrailingslashit( WP_HOME );
	return $url;
}

function _config_wp_siteurl( $url = '' ) {
	if ( defined( 'WP_SITEURL' ) )
		return untrailingslashit( WP_SITEURL );
	return $url;
}

function _mce_set_direction( $input ) {
	return $input;
}

function wp_parse_args( $args, $defaults = '' ) {
	if ( is_object( $args ) )
		$r = get_object_vars( $args );
	elseif ( is_array( $args ) )
		$r =& $args;
	else
		parse_str( $args, $r );

	if ( is_array( $defaults ) )
		return array_merge( $defaults, $r );
	return $r;
}

function wp_parse_id_list( $list ) {
	if ( !is_array($list) )
		$list = preg_split('/[\s,]+/', $list);

	return array_unique(array_map('absint', $list));
}

function wp_array_slice_assoc( $array, $keys ) {
	$slice = array();
	foreach ( $keys as $key )
		if ( isset( $array[ $key ] ) )
			$slice[ $key ] = $array[ $key ];

	return $slice;
}

function wp_is_numeric_array( $data ) {
	if ( ! is_array( $data ) ) {
		return false;
	}

	$keys = array_keys( $data );
	$string_keys = array_filter( $keys, 'is_string' );
	return count( $string_keys ) === 0;
}

function wp_filter_object_list( $list, $args = array(), $operator = 'and', $field = false ) {
	if ( ! is_array( $list ) )
		return array();
	$list = wp_list_filter( $list, $args, $operator );
	if ( $field )
		$list = wp_list_pluck( $list, $field );
	return $list;
}

function wp_list_filter( $list, $args = array(), $operator = 'AND' ) {
	if ( ! is_array( $list ) )
		return array();
	if ( empty( $args ) )
		return $list;
	$operator = strtoupper( $operator );
	$count = count( $args );
	$filtered = array();
	foreach ( $list as $key => $obj ) {
		$to_match = (array) $obj;
		$matched = 0;
		foreach ( $args as $m_key => $m_value ) {
			if ( array_key_exists( $m_key, $to_match ) && $m_value == $to_match[ $m_key ] )
				$matched++;
		}
		if ( ( 'AND' == $operator && $matched == $count ) || ( 'OR' == $operator && $matched > 0 ) || ( 'NOT' == $operator && 0 == $matched ) ) {
			$filtered[$key] = $obj;
		}
	}
	return $filtered;
}

function wp_list_pluck( $list, $field, $index_key = null ) {
	if ( ! $index_key ) {
		foreach ( $list as $key => $value ) {
			if ( is_object( $value ) ) {
				$list[ $key ] = $value->$field;
			} else {
				$list[ $key ] = $value[ $field ];
			}
		}
		return $list;
	}
	$newlist = array();
	foreach ( $list as $value ) {
		if ( is_object( $value ) ) {
			if ( isset( $value->$index_key ) ) {
				$newlist[ $value->$index_key ] = $value->$field;
			} else {
				$newlist[] = $value->$field;
			}
		} else {
			if ( isset( $value[ $index_key ] ) ) {
				$newlist[ $value[ $index_key ] ] = $value[ $field ];
			} else {
				$newlist[] = $value[ $field ];
			}
		}
	}

	return $newlist;
}

function wp_maybe_load_widgets() {
	require_once( ABSPATH . WPINC . '/widgets/class-wp-widget-search.php' );
	require_once( ABSPATH . WPINC . '/widgets/class-wp-widget-text.php' );
	require_once( ABSPATH . WPINC . '/widgets/class-wp-widget-categories.php' );
	require_once( ABSPATH . WPINC . '/widgets/class-wp-widget-recent-posts.php' );
	require_once( ABSPATH . WPINC . '/widgets/class-wp-nav-menu-widget.php' );
	add_action( '_admin_menu', 'wp_widgets_add_menu' );
}

function wp_widgets_add_menu() {
	global $submenu;
	if ( ! current_theme_supports( 'widgets' ) )
		return;
	$submenu['themes.php'][7] = array( 'Widgets', 'edit_theme_options', 'widgets.php' );
	ksort( $submenu['themes.php'], SORT_NUMERIC );
}

function wp_ob_end_flush_all() {
	$levels = ob_get_level();
	for ($i=0; $i<$levels; $i++)
		ob_end_flush();
}

function dead_db() {
	global $wpdb;
	if ( file_exists( WP_CONTENT_DIR . '/db-error.php' ) ) {
		require_once( WP_CONTENT_DIR . '/db-error.php' );
		die();
	}
	if ( wp_installing() || defined( 'WP_ADMIN' ) )
		wp_die($wpdb->error);
	status_header( 500 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Database Error</title>
</head>
<body>
	<h1>Error establishing a database connection</h1>
</body>
</html>
<?php
	die();
}

function absint( $maybeint ) {
	return abs( intval( $maybeint ) );
}

function _doing_it_wrong( $function, $message, $version ) {
	do_action( 'doing_it_wrong_run', $function, $message, $version );
	if ( apply_filters( 'doing_it_wrong_trigger_error', true ) ) {
			$version = is_null( $version ) ? '' : sprintf( '(This message was added in version %s.)', $version );
			$message .= sprintf( ' Please see <a href="%s">Debugging in WordPress</a> for more information.',
				'https://codex.wordpress.org/Debugging_in_WordPress'
			);
			trigger_error( sprintf( '%1$s was called <strong>incorrectly</strong>. %2$s %3$s', $function, $message, $version ) );
	}
}

function is_lighttpd_before_150() {
	$server_parts = explode( '/', isset( $_SERVER['SERVER_SOFTWARE'] )? $_SERVER['SERVER_SOFTWARE'] : '' );
	$server_parts[1] = isset( $server_parts[1] )? $server_parts[1] : '';
	return  'lighttpd' == $server_parts[0] && -1 == version_compare( $server_parts[1], '1.5.0' );
}

function apache_mod_loaded($mod, $default = false) {
	global $is_apache;
	if ( !$is_apache )
		return false;
	if ( function_exists( 'apache_get_modules' ) ) {
		$mods = apache_get_modules();
		if ( in_array($mod, $mods) )
			return true;
	} elseif ( function_exists( 'phpinfo' ) && false === strpos( ini_get( 'disable_functions' ), 'phpinfo' ) ) {
			ob_start();
			phpinfo(8);
			$phpinfo = ob_get_clean();
			if ( false !== strpos($phpinfo, $mod) )
				return true;
	}
	return $default;
}

function validate_file( $file, $allowed_files = '' ) {
	if ( false !== strpos( $file, '..' ) )
		return 1;

	if ( false !== strpos( $file, './' ) )
		return 1;

	if ( ! empty( $allowed_files ) && ! in_array( $file, $allowed_files ) )
		return 3;

	if (':' == substr( $file, 1, 1 ) )
		return 2;

	return 0;
}

function wp_guess_url() {
	if ( defined('WP_SITEURL') && '' != WP_SITEURL ) {
		$url = WP_SITEURL;
	} else {
		$abspath_fix = str_replace( '\\', '/', ABSPATH );
		$script_filename_dir = dirname( $_SERVER['SCRIPT_FILENAME'] );

		if ( strpos( $_SERVER['REQUEST_URI'], 'wp-admin' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) {
			$path = preg_replace( '#/(wp-admin/.*|wp-login.php)#i', '', $_SERVER['REQUEST_URI'] );

		} elseif ( $script_filename_dir . '/' == $abspath_fix ) {
			$path = preg_replace( '#/[^/]*$#i', '', $_SERVER['PHP_SELF'] );

		} else {
			if ( false !== strpos( $_SERVER['SCRIPT_FILENAME'], $abspath_fix ) ) {
				$directory = str_replace( ABSPATH, '', $script_filename_dir );
				$path = preg_replace( '#/' . preg_quote( $directory, '#' ) . '/[^/]*$#i', '' , $_SERVER['REQUEST_URI'] );
			} elseif ( false !== strpos( $abspath_fix, $script_filename_dir ) ) {
				$subdirectory = substr( $abspath_fix, strpos( $abspath_fix, $script_filename_dir ) + strlen( $script_filename_dir ) );
				$path = preg_replace( '#/[^/]*$#i', '' , $_SERVER['REQUEST_URI'] ) . $subdirectory;
			} else {
				$path = $_SERVER['REQUEST_URI'];
			}
		}
		$schema = is_ssl() ? 'https://' : 'http://';
		$url = $schema . $_SERVER['HTTP_HOST'] . $path;
	}
	return rtrim($url, '/');
}

function wp_suspend_cache_addition( $suspend = null ) {
	static $_suspend = false;
	if ( is_bool( $suspend ) )
		$_suspend = $suspend;
	return $_suspend;
}

function wp_suspend_cache_invalidation( $suspend = true ) {
	global $_wp_suspend_cache_invalidation;
	$current_suspend = $_wp_suspend_cache_invalidation;
	$_wp_suspend_cache_invalidation = $suspend;
	return $current_suspend;
}

function wp_timezone_override_offset() {
	if ( !$timezone_string = get_option( 'timezone_string' ) ) {
		return false;
	}
	$timezone_object = timezone_open( $timezone_string );
	$datetime_object = date_create();
	if ( false === $timezone_object || false === $datetime_object ) {
		return false;
	}
	return round( timezone_offset_get( $timezone_object, $datetime_object ) / HOUR_IN_SECONDS, 2 );
}

function _wp_timezone_choice_usort_callback( $a, $b ) {
	if ( 'Etc' === $a['continent'] && 'Etc' === $b['continent'] ) {
		if ( 'GMT+' === substr( $a['city'], 0, 4 ) && 'GMT+' === substr( $b['city'], 0, 4 ) ) {
			return -1 * ( strnatcasecmp( $a['city'], $b['city'] ) );
		}
		if ( 'UTC' === $a['city'] ) {
			if ( 'GMT+' === substr( $b['city'], 0, 4 ) ) {
				return 1;
			}
			return -1;
		}
		if ( 'UTC' === $b['city'] ) {
			if ( 'GMT+' === substr( $a['city'], 0, 4 ) ) {
				return -1;
			}
			return 1;
		}
		return strnatcasecmp( $a['city'], $b['city'] );
	}
	if ( $a['t_continent'] == $b['t_continent'] ) {
		if ( $a['t_city'] == $b['t_city'] ) {
			return strnatcasecmp( $a['t_subcity'], $b['t_subcity'] );
		}
		return strnatcasecmp( $a['t_city'], $b['t_city'] );
	} else {
		if ( 'Etc' === $a['continent'] ) {
			return 1;
		}
		if ( 'Etc' === $b['continent'] ) {
			return -1;
		}
		return strnatcasecmp( $a['t_continent'], $b['t_continent'] );
	}
}

function wp_timezone_choice( $selected_zone ) {
	static $mo_loaded = false;
	$continents = array( 'Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific');
	if ( !$mo_loaded ) {
		$locale = get_locale();
		$mofile = WP_LANG_DIR . '/continents-cities-' . $locale . '.mo';
		load_textdomain( 'continents-cities', $mofile );
		$mo_loaded = true;
	}
	$zonen = array();
	foreach ( timezone_identifiers_list() as $zone ) {
		$zone = explode( '/', $zone );
		if ( !in_array( $zone[0], $continents ) ) {
			continue;
		}
		$exists = array(
			0 => ( isset( $zone[0] ) && $zone[0] ),
			1 => ( isset( $zone[1] ) && $zone[1] ),
			2 => ( isset( $zone[2] ) && $zone[2] ),
		);
		$exists[3] = ( $exists[0] && 'Etc' !== $zone[0] );
		$exists[4] = ( $exists[1] && $exists[3] );
		$exists[5] = ( $exists[2] && $exists[3] );

		$zonen[] = array(
			'continent'   => ( $exists[0] ? $zone[0] : '' ),
			'city'        => ( $exists[1] ? $zone[1] : '' ),
			'subcity'     => ( $exists[2] ? $zone[2] : '' ),
			't_continent' => ( $exists[3] ? translate( str_replace( '_', ' ', $zone[0] ), 'continents-cities' ) : '' ),
			't_city'      => ( $exists[4] ? translate( str_replace( '_', ' ', $zone[1] ), 'continents-cities' ) : '' ),
			't_subcity'   => ( $exists[5] ? translate( str_replace( '_', ' ', $zone[2] ), 'continents-cities' ) : '' )
		);
	}
	usort( $zonen, '_wp_timezone_choice_usort_callback' );

	$structure = array();

	if ( empty( $selected_zone ) ) {
		$structure[] = '<option selected="selected" value="">' . __( 'Select a city' ) . '</option>';
	}

	foreach ( $zonen as $key => $zone ) {
		$value = array( $zone['continent'] );
		if ( empty( $zone['city'] ) ) {
			$display = $zone['t_continent'];
		} else {
			if ( !isset( $zonen[$key - 1] ) || $zonen[$key - 1]['continent'] !== $zone['continent'] ) {
				$label = $zone['t_continent'];
				$structure[] = '<optgroup label="'. esc_attr( $label ) .'">';
			}
			$value[] = $zone['city'];

			$display = $zone['t_city'];
			if ( !empty( $zone['subcity'] ) ) {
				$value[] = $zone['subcity'];
				$display .= ' - ' . $zone['t_subcity'];
			}
		}
		$value = join( '/', $value );
		$selected = '';
		if ( $value === $selected_zone ) {
			$selected = 'selected="selected" ';
		}
		$structure[] = '<option ' . $selected . 'value="' . esc_attr( $value ) . '">' . esc_html( $display ) . "</option>";
		if ( !empty( $zone['city'] ) && ( !isset($zonen[$key + 1]) || (isset( $zonen[$key + 1] ) && $zonen[$key + 1]['continent'] !== $zone['continent']) ) ) {
			$structure[] = '</optgroup>';
		}
	}
	$structure[] = '<optgroup label="UTC">';
	$selected = '';
	if ( 'UTC' === $selected_zone )
		$selected = 'selected="selected" ';
	$structure[] = '<option ' . $selected . 'value="UTC">UTC</option>';
	$structure[] = '</optgroup>';

	$structure[] = '<optgroup label="Manual Offsets">';
	$offset_range = array (-12, -11.5, -11, -10.5, -10, -9.5, -9, -8.5, -8, -7.5, -7, -6.5, -6, -5.5, -5, -4.5, -4, -3.5, -3, -2.5, -2, -1.5, -1, -0.5,
		0, 0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5, 5.5, 5.75, 6, 6.5, 7, 7.5, 8, 8.5, 8.75, 9, 9.5, 10, 10.5, 11, 11.5, 12, 12.75, 13, 13.75, 14);
	foreach ( $offset_range as $offset ) {
		if ( 0 <= $offset )
			$offset_name = '+' . $offset;
		else
			$offset_name = (string) $offset;

		$offset_value = $offset_name;
		$offset_name = str_replace(array('.25','.5','.75'), array(':15',':30',':45'), $offset_name);
		$offset_name = 'UTC' . $offset_name;
		$offset_value = 'UTC' . $offset_value;
		$selected = '';
		if ( $offset_value === $selected_zone )
			$selected = 'selected="selected" ';
		$structure[] = '<option ' . $selected . 'value="' . esc_attr( $offset_value ) . '">' . esc_html( $offset_name ) . "</option>";

	}
	$structure[] = '</optgroup>';

	return join( "\n", $structure );
}

function _cleanup_header_comment( $str ) {
	return trim(preg_replace("/\s*(?:\*\/|\?>).*/", '', $str));
}

function wp_scheduled_delete() {
	global $wpdb;

	$delete_timestamp = time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS );

	$posts_to_delete = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_trash_meta_time' AND meta_value < '%d'", $delete_timestamp), ARRAY_A);

	foreach ( (array) $posts_to_delete as $post ) {
		$post_id = (int) $post['post_id'];
		if ( !$post_id )
			continue;

		$del_post = get_post($post_id);

		if ( !$del_post || 'trash' != $del_post->post_status ) {
			delete_post_meta($post_id, '_wp_trash_meta_status');
			delete_post_meta($post_id, '_wp_trash_meta_time');
		} else {
			wp_delete_post($post_id);
		}
	}

	$comments_to_delete = $wpdb->get_results($wpdb->prepare("SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = '_wp_trash_meta_time' AND meta_value < '%d'", $delete_timestamp), ARRAY_A);

	foreach ( (array) $comments_to_delete as $comment ) {
		$comment_id = (int) $comment['comment_id'];
		if ( !$comment_id )
			continue;

		$del_comment = get_comment($comment_id);

		if ( !$del_comment || 'trash' != $del_comment->comment_approved ) {
			delete_comment_meta($comment_id, '_wp_trash_meta_time');
			delete_comment_meta($comment_id, '_wp_trash_meta_status');
		} else {
			wp_delete_comment( $del_comment );
		}
	}
}

function get_file_data( $file, $default_headers, $context = '' ) {
	$fp = fopen( $file, 'r' );

	$file_data = fread( $fp, 8192 );
	fclose( $fp );

	$file_data = str_replace( "\r", "\n", $file_data );

	if ( $context && $extra_headers = apply_filters( "extra_{$context}_headers", array() ) ) {
		$extra_headers = array_combine( $extra_headers, $extra_headers ); // keys equal values
		$all_headers = array_merge( $extra_headers, (array) $default_headers );
	} else {
		$all_headers = $default_headers;
	}

	foreach ( $all_headers as $field => $regex ) {
		if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] )
			$all_headers[ $field ] = _cleanup_header_comment( $match[1] );
		else
			$all_headers[ $field ] = '';
	}

	return $all_headers;
}

function send_nosniff_header() {
	@header( 'X-Content-Type-Options: nosniff' );
}

function _wp_mysql_week( $column ) {
	switch ( $start_of_week = (int) get_option( 'start_of_week' ) ) {
	case 1 :
		return "WEEK( $column, 1 )";
	case 2 :
	case 3 :
	case 4 :
	case 5 :
	case 6 :
		return "WEEK( DATE_SUB( $column, INTERVAL $start_of_week DAY ), 0 )";
	case 0 :
	default :
		return "WEEK( $column, 0 )";
	}
}

function wp_find_hierarchy_loop( $callback, $start, $start_parent, $callback_args = array() ) {
	$override = is_null( $start_parent ) ? array() : array( $start => $start_parent );
	if ( !$arbitrary_loop_member = wp_find_hierarchy_loop_tortoise_hare( $callback, $start, $override, $callback_args ) )
		return array();
	return wp_find_hierarchy_loop_tortoise_hare( $callback, $arbitrary_loop_member, $override, $callback_args, true );
}

function wp_find_hierarchy_loop_tortoise_hare( $callback, $start, $override = array(), $callback_args = array(), $_return_loop = false ) {
	$tortoise = $hare = $evanescent_hare = $start;
	$return = array();

	while (
		$tortoise
	&&
		( $evanescent_hare = isset( $override[$hare] ) ? $override[$hare] : call_user_func_array( $callback, array_merge( array( $hare ), $callback_args ) ) )
	&&
		( $hare = isset( $override[$evanescent_hare] ) ? $override[$evanescent_hare] : call_user_func_array( $callback, array_merge( array( $evanescent_hare ), $callback_args ) ) )
	) {
		if ( $_return_loop )
			$return[$tortoise] = $return[$evanescent_hare] = $return[$hare] = true;

		if ( $tortoise == $evanescent_hare || $tortoise == $hare )
			return $_return_loop ? $return : $tortoise;

		$tortoise = isset( $override[$tortoise] ) ? $override[$tortoise] : call_user_func_array( $callback, array_merge( array( $tortoise ), $callback_args ) );
	}

	return false;
}

function send_frame_options_header() {
	@header( 'X-Frame-Options: SAMEORIGIN' );
}

function wp_allowed_protocols() {
	static $protocols = array();
	if ( empty( $protocols ) ) {
		$protocols = array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'svn', 'tel', 'fax', 'xmpp', 'webcal' );
		$protocols = apply_filters( 'kses_allowed_protocols', $protocols );
	}
	return $protocols;
}

function wp_debug_backtrace_summary( $ignore_class = null, $skip_frames = 0, $pretty = true ) {
	if ( version_compare( PHP_VERSION, '5.2.5', '>=' ) )
		$trace = debug_backtrace( false );
	else
		$trace = debug_backtrace();

	$caller = array();
	$check_class = ! is_null( $ignore_class );
	$skip_frames++;

	foreach ( $trace as $call ) {
		if ( $skip_frames > 0 ) {
			$skip_frames--;
		} elseif ( isset( $call['class'] ) ) {
			if ( $check_class && $ignore_class == $call['class'] )
				continue;

			$caller[] = "{$call['class']}{$call['type']}{$call['function']}";
		} else {
			if ( in_array( $call['function'], array( 'do_action', 'apply_filters' ) ) ) {
				$caller[] = "{$call['function']}('{$call['args'][0]}')";
			} elseif ( in_array( $call['function'], array( 'include', 'include_once', 'require', 'require_once' ) ) ) {
				$caller[] = $call['function'] . "('" . str_replace( array( WP_CONTENT_DIR, ABSPATH ) , '', $call['args'][0] ) . "')";
			} else {
				$caller[] = $call['function'];
			}
		}
	}
	if ( $pretty )
		return join( ', ', array_reverse( $caller ) );
	else
		return $caller;
}

function _device_can_upload() {
	if ( ! wp_is_mobile() )
		return true;
	$ua = $_SERVER['HTTP_USER_AGENT'];
	if ( strpos($ua, 'iPhone') !== false
		|| strpos($ua, 'iPad') !== false
		|| strpos($ua, 'iPod') !== false ) {
			return preg_match( '#OS ([\d_]+) like Mac OS X#', $ua, $version ) && version_compare( $version[1], '6', '>=' );
	}
	return true;
}

function wp_is_stream( $path ) {
	$wrappers = stream_get_wrappers();
	$wrappers_re = '(' . join('|', $wrappers) . ')';
	return preg_match( "!^$wrappers_re://!", $path ) === 1;
}

function wp_checkdate( $month, $day, $year, $source_date ) {
	return apply_filters( 'wp_checkdate', checkdate( $month, $day, $year ), $source_date );
}

function wp_auth_check_load() {
	if ( ! is_admin() && ! is_user_logged_in() )
		return;
	if ( defined( 'IFRAME_REQUEST' ) )
		return;
	$screen = get_current_screen();
	$hidden = array( 'update', 'update-network', 'update-core', 'update-core-network', 'upgrade', 'upgrade-network', 'network' );
	$show = ! in_array( $screen->id, $hidden );

	if ( apply_filters( 'wp_auth_check_load', $show, $screen ) ) {
		wp_enqueue_style( 'wp-auth-check' );
		wp_enqueue_script( 'wp-auth-check' );

		add_action( 'admin_print_footer_scripts', 'wp_auth_check_html', 5 );
		add_action( 'wp_print_footer_scripts', 'wp_auth_check_html', 5 );
	}
}

function wp_auth_check_html() {
	$login_url = wp_login_url();
	$current_domain = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'];
	$same_domain = ( strpos( $login_url, $current_domain ) === 0 );
	$same_domain = apply_filters( 'wp_auth_check_same_domain', $same_domain );
	$wrap_class = $same_domain ? 'hidden' : 'hidden fallback';

	?>
	<div id="wp-auth-check-wrap" class="<?php echo $wrap_class; ?>">
	<div id="wp-auth-check-bg"></div>
	<div id="wp-auth-check">
	<button type="button" class="wp-auth-check-close button-link"><span class="screen-reader-text">Close dialog</span></button>
	<?php
	if ( $same_domain ) {
		?>
		<div id="wp-auth-check-form" class="loading" data-src="<?php echo esc_url( add_query_arg( array( 'interim-login' => 1 ), $login_url ) ); ?>"></div>
	<?php } ?>
	<div class="wp-auth-fallback">
		<p><b class="wp-auth-fallback-expired" tabindex="0">Session expired</b></p>
		<p><a href="<?php echo esc_url( $login_url ); ?>" target="_blank">Please log in again.</a>
		The login page will open in a new window. After logging in you can close it and return to this page.</p>
	</div>
	</div>
	</div>
	<?php
}

function wp_auth_check( $response ) {
	$response['wp-auth-check'] = is_user_logged_in() && empty( $GLOBALS['login_grace_period'] );
	return $response;
}

function get_tag_regex( $tag ) {
	if ( empty( $tag ) )
		return;
	return sprintf( '<%1$s[^<]*(?:>[\s\S]*<\/%1$s>|\s*\/>)', tag_escape( $tag ) );
}

function _canonical_charset( $charset ) {
	if ( 'UTF-8' === $charset || 'utf-8' === $charset || 'utf8' === $charset ||
		'UTF8' === $charset )
		return 'UTF-8';

	if ( 'ISO-8859-1' === $charset || 'iso-8859-1' === $charset ||
		'iso8859-1' === $charset || 'ISO8859-1' === $charset )
		return 'ISO-8859-1';

	return $charset;
}

function mbstring_binary_safe_encoding( $reset = false ) {
	static $encodings = array();
	static $overloaded = null;

	if ( is_null( $overloaded ) )
		$overloaded = function_exists( 'mb_internal_encoding' ) && ( ini_get( 'mbstring.func_overload' ) & 2 );

	if ( false === $overloaded )
		return;

	if ( ! $reset ) {
		$encoding = mb_internal_encoding();
		array_push( $encodings, $encoding );
		mb_internal_encoding( 'ISO-8859-1' );
	}

	if ( $reset && $encodings ) {
		$encoding = array_pop( $encodings );
		mb_internal_encoding( $encoding );
	}
}

function reset_mbstring_encoding() {
	mbstring_binary_safe_encoding( true );
}

function wp_validate_boolean( $var ) {
	if ( is_bool( $var ) ) {
		return $var;
	}
	if ( is_string( $var ) && 'false' === strtolower( $var ) ) {
		return false;
	}
	return (bool) $var;
}

function wp_delete_file( $file ) {
	$delete = apply_filters( 'wp_delete_file', $file );
	if ( ! empty( $delete ) ) {
		@unlink( $delete );
	}
}

function wp_post_preview_js() {
	global $post;
	if ( ! is_preview() || empty( $post ) ) {
		return;
	}
	$name = 'wp-preview-' . (int) $post->ID;
	?>
	<script>
	( function() {
		var query = document.location.search;

		if ( query && query.indexOf( 'preview=true' ) !== -1 ) {
			window.name = '<?php echo $name; ?>';
		}

		if ( window.addEventListener ) {
			window.addEventListener( 'unload', function() { window.name = ''; }, false );
		}
	}());
	</script>
	<?php
}

function mysql_to_rfc3339( $date_string ) {
	$formatted = mysql2date( 'c', $date_string, false );
	return preg_replace( '/(?:Z|[+-]\d{2}(?::\d{2})?)$/', '', $formatted );
}

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
