<?php
/**
 * REST API: WP_REST_Server class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.4.0
 */

class WP_REST_Server {

	const READABLE = 'GET';

	const CREATABLE = 'POST';

	const EDITABLE = 'POST, PUT, PATCH';

	const DELETABLE = 'DELETE';

	const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';

	protected $namespaces = array();

	protected $endpoints = array();

	protected $route_options = array();

	public function __construct() {
		$this->endpoints = array(
			// Meta endpoints.
			'/' => array(
				'callback' => array( $this, 'get_index' ),
				'methods' => 'GET',
				'args' => array(
					'context' => array(
						'default' => 'view',
					),
				),
			),
		);
	}

	public function check_authentication() {
		return apply_filters( 'rest_authentication_errors', null );
	}

	protected function error_to_response( $error ) {
		$error_data = $error->get_error_data();

		if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
			$status = $error_data['status'];
		} else {
			$status = 500;
		}

		$errors = array();

		foreach ( (array) $error->errors as $code => $messages ) {
			foreach ( (array) $messages as $message ) {
				$errors[] = array( 'code' => $code, 'message' => $message, 'data' => $error->get_error_data( $code ) );
			}
		}

		$data = $errors[0];
		if ( count( $errors ) > 1 ) {
			// Remove the primary error.
			array_shift( $errors );
			$data['additional_errors'] = $errors;
		}

		$response = new WP_REST_Response( $data, $status );

		return $response;
	}

	protected function json_error( $code, $message, $status = null ) {
		if ( $status ) {
			$this->set_status( $status );
		}

		$error = compact( 'code', 'message' );

		return wp_json_encode( $error );
	}

	public function serve_request( $path = null ) {
		$content_type = isset( $_GET['_jsonp'] ) ? 'application/javascript' : 'application/json';
		$this->send_header( 'Content-Type', $content_type . '; charset=' . get_option( 'blog_charset' ) );

		$this->send_header( 'X-Content-Type-Options', 'nosniff' );
		$this->send_header( 'Access-Control-Expose-Headers', 'X-WP-Total, X-WP-TotalPages' );
		$this->send_header( 'Access-Control-Allow-Headers', 'Authorization' );

		$send_no_cache_headers = apply_filters( 'rest_send_nocache_headers', is_user_logged_in() );
		if ( $send_no_cache_headers ) {
			foreach ( wp_get_nocache_headers() as $header => $header_value ) {
				$this->send_header( $header, $header_value );
			}
		}

		$enabled = apply_filters( 'rest_enabled', true );

		$jsonp_enabled = apply_filters( 'rest_jsonp_enabled', true );

		$jsonp_callback = null;

		if ( ! $enabled ) {
			echo $this->json_error( 'rest_disabled', 'The REST API is disabled on this site.', 404 );
			return false;
		}
		if ( isset( $_GET['_jsonp'] ) ) {
			if ( ! $jsonp_enabled ) {
				echo $this->json_error( 'rest_callback_disabled', 'JSONP support is disabled on this site.', 400 );
				return false;
			}

			// Check for invalid characters (only alphanumeric allowed).
			if ( is_string( $_GET['_jsonp'] ) ) {
				$jsonp_callback = preg_replace( '/[^\w\.]/', '', wp_unslash( $_GET['_jsonp'] ), -1, $illegal_char_count );
				if ( 0 !== $illegal_char_count ) {
					$jsonp_callback = null;
				}
			}
			if ( null === $jsonp_callback ) {
				echo $this->json_error( 'rest_callback_invalid', 'The JSONP callback function is invalid.', 400 );
				return false;
			}
		}

		if ( empty( $path ) ) {
			if ( isset( $_SERVER['PATH_INFO'] ) ) {
				$path = $_SERVER['PATH_INFO'];
			} else {
				$path = '/';
			}
		}

		$request = new WP_REST_Request( $_SERVER['REQUEST_METHOD'], $path );

		$request->set_query_params( wp_unslash( $_GET ) );
		$request->set_body_params( wp_unslash( $_POST ) );
		$request->set_file_params( $_FILES );
		$request->set_headers( $this->get_headers( wp_unslash( $_SERVER ) ) );
		$request->set_body( $this->get_raw_data() );

		if ( isset( $_GET['_method'] ) ) {
			$request->set_method( $_GET['_method'] );
		} elseif ( isset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) {
			$request->set_method( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] );
		}

		$result = $this->check_authentication();

		if ( ! is_wp_error( $result ) ) {
			$result = $this->dispatch( $request );
		}

		// Normalize to either WP_Error or WP_REST_Response...
		$result = rest_ensure_response( $result );

		// ...then convert WP_Error across.
		if ( is_wp_error( $result ) ) {
			$result = $this->error_to_response( $result );
		}

		$result = apply_filters( 'rest_post_dispatch', rest_ensure_response( $result ), $this, $request );

		if ( isset( $_GET['_envelope'] ) ) {
			$result = $this->envelope_response( $result, isset( $_GET['_embed'] ) );
		}

		// Send extra data from response objects.
		$headers = $result->get_headers();
		$this->send_headers( $headers );

		$code = $result->get_status();
		$this->set_status( $code );

		$served = apply_filters( 'rest_pre_serve_request', false, $result, $request, $this );

		if ( ! $served ) {
			if ( 'HEAD' === $request->get_method() ) {
				return null;
			}

			// Embed links inside the request.
			$result = $this->response_to_data( $result, isset( $_GET['_embed'] ) );

			$result = wp_json_encode( $result );

			$json_error_message = $this->get_json_last_error();
			if ( $json_error_message ) {
				$json_error_obj = new WP_Error( 'rest_encode_error', $json_error_message, array( 'status' => 500 ) );
				$result = $this->error_to_response( $json_error_obj );
				$result = wp_json_encode( $result->data[0] );
			}

			if ( $jsonp_callback ) {
				echo '/**/' . $jsonp_callback . '(' . $result . ')';
			} else {
				echo $result;
			}
		}
		return null;
	}

	public function response_to_data( $response, $embed ) {
		$data  = $response->get_data();
		$links = $this->get_compact_response_links( $response );

		if ( ! empty( $links ) ) {
			// Convert links to part of the data.
			$data['_links'] = $links;
		}
		if ( $embed ) {
			// Determine if this is a numeric array.
			if ( wp_is_numeric_array( $data ) ) {
				$data = array_map( array( $this, 'embed_links' ), $data );
			} else {
				$data = $this->embed_links( $data );
			}
		}

		return $data;
	}

	public static function get_response_links( $response ) {
		$links = $response->get_links();
		if ( empty( $links ) ) {
			return array();
		}

		// Convert links to part of the data.
		$data = array();
		foreach ( $links as $rel => $items ) {
			$data[ $rel ] = array();

			foreach ( $items as $item ) {
				$attributes = $item['attributes'];
				$attributes['href'] = $item['href'];
				$data[ $rel ][] = $attributes;
			}
		}

		return $data;
	}

	public static function get_compact_response_links( $response ) {
		$links = self::get_response_links( $response );

		if ( empty( $links ) ) {
			return array();
		}

		$curies = $response->get_curies();
		$used_curies = array();

		foreach ( $links as $rel => $items ) {

			// Convert $rel URIs to their compact versions if they exist.
			foreach ( $curies as $curie ) {
				$href_prefix = substr( $curie['href'], 0, strpos( $curie['href'], '{rel}' ) );
				if ( strpos( $rel, $href_prefix ) !== 0 ) {
					continue;
				}

				// Relation now changes from '$uri' to '$curie:$relation'
				$rel_regex = str_replace( '\{rel\}', '(.+)', preg_quote( $curie['href'], '!' ) );
				preg_match( '!' . $rel_regex . '!', $rel, $matches );
				if ( $matches ) {
					$new_rel = $curie['name'] . ':' . $matches[1];
					$used_curies[ $curie['name'] ] = $curie;
					$links[ $new_rel ] = $items;
					unset( $links[ $rel ] );
					break;
				}
			}
		}

		// Push the curies onto the start of the links array.
		if ( $used_curies ) {
			$links['curies'] = array_values( $used_curies );
		}

		return $links;
	}

	protected function embed_links( $data ) {
		if ( empty( $data['_links'] ) ) {
			return $data;
		}

		$embedded = array();

		foreach ( $data['_links'] as $rel => $links ) {
			// Ignore links to self, for obvious reasons.
			if ( 'self' === $rel ) {
				continue;
			}

			$embeds = array();

			foreach ( $links as $item ) {
				// Determine if the link is embeddable.
				if ( empty( $item['embeddable'] ) ) {
					// Ensure we keep the same order.
					$embeds[] = array();
					continue;
				}

				// Run through our internal routing and serve.
				$request = WP_REST_Request::from_url( $item['href'] );
				if ( ! $request ) {
					$embeds[] = array();
					continue;
				}

				// Embedded resources get passed context=embed.
				if ( empty( $request['context'] ) ) {
					$request['context'] = 'embed';
				}

				$response = $this->dispatch( $request );

				/** This filter is documented in wp-includes/rest-api/class-wp-rest-server.php */
				$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $this, $request );

				$embeds[] = $this->response_to_data( $response, false );
			}

			// Determine if any real links were found.
			$has_links = count( array_filter( $embeds ) );
			if ( $has_links ) {
				$embedded[ $rel ] = $embeds;
			}
		}

		if ( ! empty( $embedded ) ) {
			$data['_embedded'] = $embedded;
		}

		return $data;
	}

	public function envelope_response( $response, $embed ) {
		$envelope = array(
			'body'    => $this->response_to_data( $response, $embed ),
			'status'  => $response->get_status(),
			'headers' => $response->get_headers(),
		);

		$envelope = apply_filters( 'rest_envelope_response', $envelope, $response );

		return rest_ensure_response( $envelope );
	}

	public function register_route( $namespace, $route, $route_args, $override = false ) {
		if ( ! isset( $this->namespaces[ $namespace ] ) ) {
			$this->namespaces[ $namespace ] = array();

			$this->register_route( $namespace, '/' . $namespace, array(
				array(
					'methods' => self::READABLE,
					'callback' => array( $this, 'get_namespace_index' ),
					'args' => array(
						'namespace' => array(
							'default' => $namespace,
						),
						'context' => array(
							'default' => 'view',
						),
					),
				),
			) );
		}

		// Associative to avoid double-registration.
		$this->namespaces[ $namespace ][ $route ] = true;
		$route_args['namespace'] = $namespace;

		if ( $override || empty( $this->endpoints[ $route ] ) ) {
			$this->endpoints[ $route ] = $route_args;
		} else {
			$this->endpoints[ $route ] = array_merge( $this->endpoints[ $route ], $route_args );
		}
	}

	public function get_routes() {
		$endpoints = apply_filters( 'rest_endpoints', $this->endpoints );

		$defaults = array(
			'methods'       => '',
			'accept_json'   => false,
			'accept_raw'    => false,
			'show_in_index' => true,
			'args'          => array(),
		);

		foreach ( $endpoints as $route => &$handlers ) {

			if ( isset( $handlers['callback'] ) ) {
				// Single endpoint, add one deeper.
				$handlers = array( $handlers );
			}

			if ( ! isset( $this->route_options[ $route ] ) ) {
				$this->route_options[ $route ] = array();
			}

			foreach ( $handlers as $key => &$handler ) {

				if ( ! is_numeric( $key ) ) {
					// Route option, move it to the options.
					$this->route_options[ $route ][ $key ] = $handler;
					unset( $handlers[ $key ] );
					continue;
				}

				$handler = wp_parse_args( $handler, $defaults );

				// Allow comma-separated HTTP methods.
				if ( is_string( $handler['methods'] ) ) {
					$methods = explode( ',', $handler['methods'] );
				} else if ( is_array( $handler['methods'] ) ) {
					$methods = $handler['methods'];
				} else {
					$methods = array();
				}

				$handler['methods'] = array();

				foreach ( $methods as $method ) {
					$method = strtoupper( trim( $method ) );
					$handler['methods'][ $method ] = true;
				}
			}
		}
		return $endpoints;
	}

	public function get_namespaces() {
		return array_keys( $this->namespaces );
	}

	public function get_route_options( $route ) {
		if ( ! isset( $this->route_options[ $route ] ) ) {
			return null;
		}

		return $this->route_options[ $route ];
	}

	public function dispatch( $request ) {
		$result = apply_filters( 'rest_pre_dispatch', null, $this, $request );

		if ( ! empty( $result ) ) {
			return $result;
		}

		$method = $request->get_method();
		$path   = $request->get_route();

		foreach ( $this->get_routes() as $route => $handlers ) {
			$match = preg_match( '@^' . $route . '$@i', $path, $args );

			if ( ! $match ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				$callback  = $handler['callback'];
				$response = null;

				// Fallback to GET method if no HEAD method is registered.
				$checked_method = $method;
				if ( 'HEAD' === $method && empty( $handler['methods']['HEAD'] ) ) {
					$checked_method = 'GET';
				}
				if ( empty( $handler['methods'][ $checked_method ] ) ) {
					continue;
				}

				if ( ! is_callable( $callback ) ) {
					$response = new WP_Error( 'rest_invalid_handler', __( 'The handler for the route is invalid' ), array( 'status' => 500 ) );
				}

				if ( ! is_wp_error( $response ) ) {
					// Remove the redundant preg_match argument.
					unset( $args[0] );

					$request->set_url_params( $args );
					$request->set_attributes( $handler );

					$request->sanitize_params();

					$defaults = array();

					foreach ( $handler['args'] as $arg => $options ) {
						if ( isset( $options['default'] ) ) {
							$defaults[ $arg ] = $options['default'];
						}
					}

					$request->set_default_params( $defaults );

					$check_required = $request->has_valid_params();
					if ( is_wp_error( $check_required ) ) {
						$response = $check_required;
					}
				}

				if ( ! is_wp_error( $response ) ) {
					// Check permission specified on the route.
					if ( ! empty( $handler['permission_callback'] ) ) {
						$permission = call_user_func( $handler['permission_callback'], $request );

						if ( is_wp_error( $permission ) ) {
							$response = $permission;
						} else if ( false === $permission || null === $permission ) {
							$response = new WP_Error( 'rest_forbidden', __( "You don't have permission to do this." ), array( 'status' => 403 ) );
						}
					}
				}

				if ( ! is_wp_error( $response ) ) {
					$dispatch_result = apply_filters( 'rest_dispatch_request', null, $request, $route, $handler );

					// Allow plugins to halt the request via this filter.
					if ( null !== $dispatch_result ) {
						$response = $dispatch_result;
					} else {
						$response = call_user_func( $callback, $request );
					}
				}

				if ( is_wp_error( $response ) ) {
					$response = $this->error_to_response( $response );
				} else {
					$response = rest_ensure_response( $response );
				}

				$response->set_matched_route( $route );
				$response->set_matched_handler( $handler );

				return $response;
			}
		}

		return $this->error_to_response( new WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method' ), array( 'status' => 404 ) ) );
	}

	protected function get_json_last_error() {
		if ( ! function_exists( 'json_last_error' ) ) {
			return false;
		}

		$last_error_code = json_last_error();

		if ( ( defined( 'JSON_ERROR_NONE' ) && JSON_ERROR_NONE === $last_error_code ) || empty( $last_error_code ) ) {
			return false;
		}

		return json_last_error_msg();
	}

	public function get_index( $request ) {
		// General site data.
		$available = array(
			'name'           => get_option( 'blogname' ),
			'description'    => get_option( 'blogdescription' ),
			'url'            => get_option( 'siteurl' ),
			'home'           => home_url(),
			'namespaces'     => array_keys( $this->namespaces ),
			'authentication' => array(),
			'routes'         => $this->get_data_for_routes( $this->get_routes(), $request['context'] ),
		);

		$response = new WP_REST_Response( $available );

		$response->add_link( 'help', 'http://v2.wp-api.org/' );

		return apply_filters( 'rest_index', $response );
	}

	public function get_namespace_index( $request ) {
		$namespace = $request['namespace'];

		if ( ! isset( $this->namespaces[ $namespace ] ) ) {
			return new WP_Error( 'rest_invalid_namespace', 'The specified namespace could not be found.', array( 'status' => 404 ) );
		}

		$routes = $this->namespaces[ $namespace ];
		$endpoints = array_intersect_key( $this->get_routes(), $routes );

		$data = array(
			'namespace' => $namespace,
			'routes' => $this->get_data_for_routes( $endpoints, $request['context'] ),
		);
		$response = rest_ensure_response( $data );

		$response->add_link( 'up', rest_url( '/' ) );

		return apply_filters( 'rest_namespace_index', $response, $request );
	}

	public function get_data_for_routes( $routes, $context = 'view' ) {
		$available = array();
		foreach ( $routes as $route => $callbacks ) {
			$data = $this->get_data_for_route( $route, $callbacks, $context );
			if ( empty( $data ) ) {
				continue;
			}

			$available[ $route ] = apply_filters( 'rest_endpoints_description', $data );
		}

		return apply_filters( 'rest_route_data', $available, $routes );
	}

	public function get_data_for_route( $route, $callbacks, $context = 'view' ) {
		$data = array(
			'namespace' => '',
			'methods' => array(),
			'endpoints' => array(),
		);

		if ( isset( $this->route_options[ $route ] ) ) {
			$options = $this->route_options[ $route ];

			if ( isset( $options['namespace'] ) ) {
				$data['namespace'] = $options['namespace'];
			}

			if ( isset( $options['schema'] ) && 'help' === $context ) {
				$data['schema'] = call_user_func( $options['schema'] );
			}
		}

		$route = preg_replace( '#\(\?P<(\w+?)>.*?\)#', '{$1}', $route );

		foreach ( $callbacks as $callback ) {
			// Skip to the next route if any callback is hidden.
			if ( empty( $callback['show_in_index'] ) ) {
				continue;
			}

			$data['methods'] = array_merge( $data['methods'], array_keys( $callback['methods'] ) );
			$endpoint_data = array(
				'methods' => array_keys( $callback['methods'] ),
			);

			if ( isset( $callback['args'] ) ) {
				$endpoint_data['args'] = array();
				foreach ( $callback['args'] as $key => $opts ) {
					$arg_data = array(
						'required' => ! empty( $opts['required'] ),
					);
					if ( isset( $opts['default'] ) ) {
						$arg_data['default'] = $opts['default'];
					}
					if ( isset( $opts['enum'] ) ) {
						$arg_data['enum'] = $opts['enum'];
					}
					if ( isset( $opts['description'] ) ) {
						$arg_data['description'] = $opts['description'];
					}
					$endpoint_data['args'][ $key ] = $arg_data;
				}
			}

			$data['endpoints'][] = $endpoint_data;
			if ( strpos( $route, '{' ) === false ) {
				$data['_links'] = array(
					'self' => rest_url( $route ),
				);
			}
		}

		if ( empty( $data['methods'] ) ) {
			// No methods supported, hide the route.
			return null;
		}

		return $data;
	}

	protected function set_status( $code ) {
		status_header( $code );
	}

	public function send_header( $key, $value ) {
		$value = preg_replace( '/\s+/', ' ', $value );
		header( sprintf( '%s: %s', $key, $value ) );
	}

	public function send_headers( $headers ) {
		foreach ( $headers as $key => $value ) {
			$this->send_header( $key, $value );
		}
	}

	public static function get_raw_data() {
		global $HTTP_RAW_POST_DATA;

		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		return $HTTP_RAW_POST_DATA;
	}

	public function get_headers( $server ) {
		$headers = array();

		$additional = array( 'CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true );

		foreach ( $server as $key => $value ) {
			if ( strpos( $key, 'HTTP_' ) === 0 ) {
				$headers[ substr( $key, 5 ) ] = $value;
			} elseif ( isset( $additional[ $key ] ) ) {
				$headers[ $key ] = $value;
			}
		}

		return $headers;
	}
}
