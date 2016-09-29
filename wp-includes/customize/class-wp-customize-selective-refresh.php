<?php
/**
 * Customize API: WP_Customize_Selective_Refresh class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.5.0
 */

final class WP_Customize_Selective_Refresh {

	const RENDER_QUERY_VAR = 'wp_customize_render_partials';

	public $manager;

	protected $partials = array();

	protected $triggered_errors = array();

	protected $current_partial_id;

	public function __construct( WP_Customize_Manager $manager ) {
		$this->manager = $manager;
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-partial.php' );

		add_action( 'customize_preview_init', array( $this, 'init_preview' ) );
	}

	public function partials() {
		return $this->partials;
	}

	public function add_partial( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Partial ) {
			$partial = $id;
		} else {
			$class = 'WP_Customize_Partial';
			$args = apply_filters( 'customize_dynamic_partial_args', $args, $id );
			$class = apply_filters( 'customize_dynamic_partial_class', $class, $id, $args );

			$partial = new $class( $this, $id, $args );
		}

		$this->partials[ $partial->id ] = $partial;
		return $partial;
	}

	public function get_partial( $id ) {
		if ( isset( $this->partials[ $id ] ) ) {
			return $this->partials[ $id ];
		} else {
			return null;
		}
	}

	public function remove_partial( $id ) {
		unset( $this->partials[ $id ] );
	}

	public function init_preview() {
		add_action( 'template_redirect', array( $this, 'handle_render_partials_request' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_preview_scripts' ) );
	}

	public function enqueue_preview_scripts() {
		wp_enqueue_script( 'customize-selective-refresh' );
		add_action( 'wp_footer', array( $this, 'export_preview_data' ), 1000 );
	}

	public function export_preview_data() {
		$partials = array();

		foreach ( $this->partials() as $partial ) {
			if ( $partial->check_capabilities() ) {
				$partials[ $partial->id ] = $partial->json();
			}
		}

		$exports = array(
			'partials'       => $partials,
			'renderQueryVar' => self::RENDER_QUERY_VAR,
			'l10n'           => array(
				'shiftClickToEdit' => __( 'Shift-click to edit this element.' ),
				/* translators: %s: document.write() */
				'badDocumentWrite' => sprintf( __( '%s is forbidden' ), 'document.write()' ),
			),
		);

		// Export data to JS.
		echo sprintf( '<script>var _customizePartialRefreshExports = %s;</script>', wp_json_encode( $exports ) );
	}

	public function add_dynamic_partials( $partial_ids ) {
		$new_partials = array();

		foreach ( $partial_ids as $partial_id ) {

			// Skip partials already created.
			$partial = $this->get_partial( $partial_id );
			if ( $partial ) {
				continue;
			}

			$partial_args = false;
			$partial_class = 'WP_Customize_Partial';

			$partial_args = apply_filters( 'customize_dynamic_partial_args', $partial_args, $partial_id );
			if ( false === $partial_args ) {
				continue;
			}

			$partial_class = apply_filters( 'customize_dynamic_partial_class', $partial_class, $partial_id, $partial_args );

			$partial = new $partial_class( $this, $partial_id, $partial_args );

			$this->add_partial( $partial );
			$new_partials[] = $partial;
		}
		return $new_partials;
	}

	public function is_render_partials_request() {
		return ! empty( $_POST[ self::RENDER_QUERY_VAR ] );
	}

	public function handle_error( $errno, $errstr, $errfile = null, $errline = null ) {
		$this->triggered_errors[] = array(
			'partial'      => $this->current_partial_id,
			'error_number' => $errno,
			'error_string' => $errstr,
			'error_file'   => $errfile,
			'error_line'   => $errline,
		);
		return true;
	}

	public function handle_render_partials_request() {
		if ( ! $this->is_render_partials_request() ) {
			return;
		}

		$this->manager->remove_preview_signature();

		if ( ! is_customize_preview() ) {
			status_header( 403 );
			wp_send_json_error( 'expected_customize_preview' );
		} else if ( ! isset( $_POST['partials'] ) ) {
			status_header( 400 );
			wp_send_json_error( 'missing_partials' );
		}

		$partials = json_decode( wp_unslash( $_POST['partials'] ), true );

		if ( ! is_array( $partials ) ) {
			wp_send_json_error( 'malformed_partials' );
		}

		$this->add_dynamic_partials( array_keys( $partials ) );

		do_action( 'customize_render_partials_before', $this, $partials );

		set_error_handler( array( $this, 'handle_error' ), error_reporting() );

		$contents = array();

		foreach ( $partials as $partial_id => $container_contexts ) {
			$this->current_partial_id = $partial_id;

			if ( ! is_array( $container_contexts ) ) {
				wp_send_json_error( 'malformed_container_contexts' );
			}

			$partial = $this->get_partial( $partial_id );

			if ( ! $partial || ! $partial->check_capabilities() ) {
				$contents[ $partial_id ] = null;
				continue;
			}

			$contents[ $partial_id ] = array();

			// @todo The array should include not only the contents, but also whether the container is included?
			if ( empty( $container_contexts ) ) {
				// Since there are no container contexts, render just once.
				$contents[ $partial_id ][] = $partial->render( null );
			} else {
				foreach ( $container_contexts as $container_context ) {
					$contents[ $partial_id ][] = $partial->render( $container_context );
				}
			}
		}
		$this->current_partial_id = null;

		restore_error_handler();

		do_action( 'customize_render_partials_after', $this, $partials );

		$response = array(
			'contents' => $contents,
		);

		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			$response['errors'] = $this->triggered_errors;
		}

		$response = apply_filters( 'customize_render_partials_response', $response, $this, $partials );

		wp_send_json_success( $response );
	}
}
