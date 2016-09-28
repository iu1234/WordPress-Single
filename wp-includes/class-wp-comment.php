<?php
/**
 * Comment API: WP_Comment class
 *
 * @package WordPress
 * @subpackage Comments
 * @since 4.4.0
 */

final class WP_Comment {

	public $comment_ID;

	public $comment_post_ID = 0;

	public $comment_author = '';

	public $comment_author_email = '';

	public $comment_author_url = '';

	public $comment_author_IP = '';

	public $comment_date = '0000-00-00 00:00:00';

	public $comment_date_gmt = '0000-00-00 00:00:00';

	public $comment_content;

	public $comment_karma = 0;

	public $comment_approved = '1';

	public $comment_agent = '';

	public $comment_type = '';

	public $comment_parent = 0;

	public $user_id = 0;

	protected $children;

	protected $populated_children = false;

	protected $post_fields = array( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count' );

	public static function get_instance( $id ) {
		global $wpdb;

		$comment_id = (int) $id;
		if ( ! $comment_id ) {
			return false;
		}

		$_comment = wp_cache_get( $comment_id, 'comment' );

		if ( ! $_comment ) {
			$_comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_ID = %d LIMIT 1", $comment_id ) );

			if ( ! $_comment ) {
				return false;
			}

			wp_cache_add( $_comment->comment_ID, $_comment, 'comment' );
		}

		return new WP_Comment( $_comment );
	}

	public function __construct( $comment ) {
		foreach ( get_object_vars( $comment ) as $key => $value ) {
			$this->$key = $value;
		}
	}

	public function to_array() {
		return get_object_vars( $this );
	}

	public function get_children( $args = array() ) {
		$defaults = array(
			'format' => 'tree',
			'status' => 'all',
			'hierarchical' => 'threaded',
			'orderby' => '',
		);

		$_args = wp_parse_args( $args, $defaults );
		$_args['parent'] = $this->comment_ID;

		if ( is_null( $this->children ) ) {
			if ( $this->populated_children ) {
				$this->children = array();
			} else {
				$this->children = get_comments( $_args );
			}
		}

		if ( 'flat' === $_args['format'] ) {
			$children = array();
			foreach ( $this->children as $child ) {
				$child_args = $_args;
				$child_args['format'] = 'flat';
				// get_children() resets this value automatically.
				unset( $child_args['parent'] );

				$children = array_merge( $children, array( $child ), $child->get_children( $child_args ) );
			}
		} else {
			$children = $this->children;
		}

		return $children;
	}

	public function add_child( WP_Comment $child ) {
		$this->children[ $child->comment_ID ] = $child;
	}

	public function get_child( $child_id ) {
		if ( isset( $this->children[ $child_id ] ) ) {
			return $this->children[ $child_id ];
		}

		return false;
	}

	public function populated_children( $set ) {
		$this->populated_children = (bool) $set;
	}

	public function __isset( $name ) {
		if ( in_array( $name, $this->post_fields ) && 0 !== (int) $this->comment_post_ID ) {
			$post = get_post( $this->comment_post_ID );
			return property_exists( $post, $name );
		}
	}

	public function __get( $name ) {
		if ( in_array( $name, $this->post_fields ) ) {
			$post = get_post( $this->comment_post_ID );
			return $post->$name;
		}
	}
}
