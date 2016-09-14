<?php
/**
 * REST API functions.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.4.0
 */

define( 'REST_API_VERSION', '2.0' );

function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
	/** @var WP_REST_Server $wp_rest_server */
	global $wp_rest_server;

	if ( empty( $namespace ) ) {
		_doing_it_wrong( 'register_rest_route', 'Routes must be namespaced with plugin or theme name and version.', '4.4.0' );
		return false;
	} else if ( empty( $route ) ) {
		_doing_it_wrong( 'register_rest_route', 'Route must be specified.', '4.4.0' );
		return false;
	}

	if ( isset( $args['callback'] ) ) {
		$args = array( $args );
	}

	$defaults = array(
		'methods'         => 'GET',
		'callback'        => null,
		'args'            => array(),
	);
	foreach ( $args as $key => &$arg_group ) {
		if ( ! is_numeric( $arg_group ) ) {
			// Route option, skip here.
			continue;
		}

		$arg_group = array_merge( $defaults, $arg_group );
	}

	$full_route = '/' . trim( $namespace, '/' ) . '/' . trim( $route, '/' );
	$wp_rest_server->register_route( $namespace, $full_route, $args, $override );
	return true;
}

function rest_api_init() {
	rest_api_register_rewrites();
	global $wp;
	$wp->add_query_var( 'rest_route' );
}

function rest_api_register_rewrites() {
	add_rewrite_rule( '^' . rest_get_url_prefix() . '/?$','index.php?rest_route=/','top' );
	add_rewrite_rule( '^' . rest_get_url_prefix() . '/(.*)?','index.php?rest_route=/$matches[1]','top' );
}

function rest_api_default_filters() {
	add_action( 'deprecated_function_run', 'rest_handle_deprecated_function', 10, 3 );
	add_filter( 'deprecated_function_trigger_error', '__return_false' );
	add_action( 'deprecated_argument_run', 'rest_handle_deprecated_argument', 10, 3 );
	add_filter( 'deprecated_argument_trigger_error', '__return_false' );

	add_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	add_filter( 'rest_post_dispatch', 'rest_send_allow_header', 10, 3 );

	add_filter( 'rest_pre_dispatch', 'rest_handle_options_request', 10, 3 );
}

function rest_api_loaded() {
	if ( empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		return;
	}

	define( 'REST_REQUEST', true );

	$server = rest_get_server();

	$server->serve_request( $GLOBALS['wp']->query_vars['rest_route'] );

	die();
}

function rest_get_url_prefix() {
	return apply_filters( 'rest_url_prefix', 'wp-json' );
}

function get_rest_url( $blog_id = null, $path = '/', $scheme = 'rest' ) {
	if ( empty( $path ) ) {
		$path = '/';
	}

	if ( is_multisite() && get_blog_option( $blog_id, 'permalink_structure' ) || get_option( 'permalink_structure' ) ) {
		$url = get_home_url( $blog_id, rest_get_url_prefix(), $scheme );
		$url .= '/' . ltrim( $path, '/' );
	} else {
		$url = trailingslashit( get_home_url( $blog_id, '', $scheme ) );

		$path = '/' . ltrim( $path, '/' );

		$url = add_query_arg( 'rest_route', $path, $url );
	}

	if ( is_ssl() ) {
		// If the current host is the same as the REST URL host, force the REST URL scheme to HTTPS.
		if ( $_SERVER['SERVER_NAME'] === parse_url( get_home_url( $blog_id ), PHP_URL_HOST ) ) {
			$url = set_url_scheme( $url, 'https' );
		}
	}

	return apply_filters( 'rest_url', $url, $path, $blog_id, $scheme );
}

function rest_url( $path = '', $scheme = 'json' ) {
	return get_rest_url( null, $path, $scheme );
}

function rest_do_request( $request ) {
	$request = rest_ensure_request( $request );
	return rest_get_server()->dispatch( $request );
}

function rest_get_server() {
	/* @var WP_REST_Server $wp_rest_server */
	global $wp_rest_server;

	if ( empty( $wp_rest_server ) ) {

		$wp_rest_server_class = apply_filters( 'wp_rest_server_class', 'WP_REST_Server' );
		$wp_rest_server = new $wp_rest_server_class;

		do_action( 'rest_api_init', $wp_rest_server );
	}

	return $wp_rest_server;
}

function rest_ensure_request( $request ) {
	if ( $request instanceof WP_REST_Request ) {
		return $request;
	}

	return new WP_REST_Request( 'GET', '', $request );
}

function rest_ensure_response( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( $response instanceof WP_HTTP_Response ) {
		return $response;
	}

	return new WP_REST_Response( $response );
}

function rest_handle_deprecated_function( $function, $replacement, $version ) {
	if ( ! empty( $replacement ) ) {
		/* translators: 1: function name, 2: WordPress version number, 3: new function name */
		$string = sprintf( __( '%1$s (since %2$s; use %3$s instead)' ), $function, $version, $replacement );
	} else {
		/* translators: 1: function name, 2: WordPress version number */
		$string = sprintf( __( '%1$s (since %2$s; no alternative available)' ), $function, $version );
	}

	header( sprintf( 'X-WP-DeprecatedFunction: %s', $string ) );
}

function rest_handle_deprecated_argument( $function, $message, $version ) {
	if ( ! empty( $message ) ) {
		$string = sprintf( '%1$s (since %2$s; %3$s)', $function, $version, $message );
	} else {
		$string = sprintf( '%1$s (since %2$s; no alternative available)', $function, $version );
	}

	header( sprintf( 'X-WP-DeprecatedParam: %s', $string ) );
}

function rest_send_cors_headers( $value ) {
	$origin = get_http_origin();

	if ( $origin ) {
		header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
		header( 'Access-Control-Allow-Credentials: true' );
	}

	return $value;
}

function rest_handle_options_request( $response, $handler, $request ) {
	if ( ! empty( $response ) || $request->get_method() !== 'OPTIONS' ) {
		return $response;
	}

	$response = new WP_REST_Response();
	$data = array();

	$accept = array();

	foreach ( $handler->get_routes() as $route => $endpoints ) {
		$match = preg_match( '@^' . $route . '$@i', $request->get_route(), $args );

		if ( ! $match ) {
			continue;
		}

		$data = $handler->get_data_for_route( $route, $endpoints, 'help' );
		$response->set_matched_route( $route );
		break;
	}

	$response->set_data( $data );
	return $response;
}

function rest_send_allow_header( $response, $server, $request ) {
	$matched_route = $response->get_matched_route();

	if ( ! $matched_route ) {
		return $response;
	}

	$routes = $server->get_routes();

	$allowed_methods = array();

	// Get the allowed methods across the routes.
	foreach ( $routes[ $matched_route ] as $_handler ) {
		foreach ( $_handler['methods'] as $handler_method => $value ) {

			if ( ! empty( $_handler['permission_callback'] ) ) {

				$permission = call_user_func( $_handler['permission_callback'], $request );

				$allowed_methods[ $handler_method ] = true === $permission;
			} else {
				$allowed_methods[ $handler_method ] = true;
			}
		}
	}

	$allowed_methods = array_filter( $allowed_methods );

	if ( $allowed_methods ) {
		$response->header( 'Allow', implode( ', ', array_map( 'strtoupper', array_keys( $allowed_methods ) ) ) );
	}

	return $response;
}

function rest_output_rsd() {
	$api_root = get_rest_url();

	if ( empty( $api_root ) ) {
		return;
	}
	?>
	<api name="WP-API" blogID="1" preferred="false" apiLink="<?php echo esc_url( $api_root ); ?>" />
	<?php
}

function rest_output_link_wp_head() {
	$api_root = get_rest_url();

	if ( empty( $api_root ) ) {
		return;
	}

	echo "<link rel='https://api.w.org/' href='" . esc_url( $api_root ) . "' />\n";
}

function rest_output_link_header() {
	if ( headers_sent() ) {
		return;
	}

	$api_root = get_rest_url();

	if ( empty( $api_root ) ) {
		return;
	}

	header( 'Link: <' . esc_url_raw( $api_root ) . '>; rel="https://api.w.org/"', false );
}

function rest_cookie_check_errors( $result ) {
	if ( ! empty( $result ) ) {
		return $result;
	}

	global $wp_rest_auth_cookie;

	if ( true !== $wp_rest_auth_cookie && is_user_logged_in() ) {
		return $result;
	}

	$nonce = null;

	if ( isset( $_REQUEST['_wpnonce'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
	} elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
		$nonce = $_SERVER['HTTP_X_WP_NONCE'];
	}

	if ( null === $nonce ) {
		// No nonce at all, so act as if it's an unauthenticated request.
		wp_set_current_user( 0 );
		return true;
	}

	$result = wp_verify_nonce( $nonce, 'wp_rest' );

	if ( ! $result ) {
		return new WP_Error( 'rest_cookie_invalid_nonce', 'Cookie nonce is invalid', array( 'status' => 403 ) );
	}

	return true;
}

function rest_cookie_collect_status() {
	global $wp_rest_auth_cookie;

	$status_type = current_action();

	if ( 'auth_cookie_valid' !== $status_type ) {
		$wp_rest_auth_cookie = substr( $status_type, 12 );
		return;
	}

	$wp_rest_auth_cookie = true;
}

function rest_parse_date( $date, $force_utc = false ) {
	if ( $force_utc ) {
		$date = preg_replace( '/[+-]\d+:?\d+$/', '+00:00', $date );
	}

	$regex = '#^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}(?::\d{2})?)?$#';

	if ( ! preg_match( $regex, $date, $matches ) ) {
		return false;
	}

	return strtotime( $date );
}

function rest_get_date_with_gmt( $date, $force_utc = false ) {
	$date = rest_parse_date( $date, $force_utc );

	if ( empty( $date ) ) {
		return null;
	}

	$utc = date( 'Y-m-d H:i:s', $date );
	$local = get_date_from_gmt( $utc );

	return array( $local, $utc );
}
