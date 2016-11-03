<?php
/**
 * Meta API: WP_Metadata_Lazyloader class
 *
 * @package WordPress
 * @subpackage Meta
 * @since 4.5.0
 */

class WP_Metadata_Lazyloader {

	protected $pending_objects;

	protected $settings = array();

	public function __construct() {
		$this->settings = array(
			'term' => array(
				'filter'   => 'get_term_metadata',
				'callback' => array( $this, 'lazyload_term_meta' ),
			),
			'comment' => array(
				'filter'   => 'get_comment_metadata',
				'callback' => array( $this, 'lazyload_comment_meta' ),
			),
		);
	}

	public function queue_objects( $object_type, $object_ids ) {
		if ( ! isset( $this->settings[ $object_type ] ) ) {
			return new WP_Error( 'invalid_object_type', __( 'Invalid object type' ) );
		}

		$type_settings = $this->settings[ $object_type ];

		if ( ! isset( $this->pending_objects[ $object_type ] ) ) {
			$this->pending_objects[ $object_type ] = array();
		}

		foreach ( $object_ids as $object_id ) {
			if ( ! isset( $this->pending_objects[ $object_type ][ $object_id ] ) ) {
				$this->pending_objects[ $object_type ][ $object_id ] = 1;
			}
		}

		add_filter( $type_settings['filter'], $type_settings['callback'] );

		do_action( 'metadata_lazyloader_queued_objects', $object_ids, $object_type, $this );
	}

	public function reset_queue( $object_type ) {
		if ( ! isset( $this->settings[ $object_type ] ) ) {
			return new WP_Error( 'invalid_object_type', 'Invalid object type' );
		}

		$type_settings = $this->settings[ $object_type ];

		$this->pending_objects[ $object_type ] = array();
		remove_filter( $type_settings['filter'], $type_settings['callback'] );
	}

	public function lazyload_term_meta( $check ) {
		if ( ! empty( $this->pending_objects['term'] ) ) {
			update_termmeta_cache( array_keys( $this->pending_objects['term'] ) );
			$this->reset_queue( 'term' );
		}

		return $check;
	}

	public function lazyload_comment_meta( $check ) {
		if ( ! empty( $this->pending_objects['comment'] ) ) {
			update_meta_cache( 'comment', array_keys( $this->pending_objects['comment'] ) );
			$this->reset_queue( 'comment' );
		}
		return $check;
	}
}
