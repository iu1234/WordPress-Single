<?php
/**
 * Author Template functions for use in themes.
 *
 * These functions must be used within the WordPress Loop.
 *
 * @link https://codex.wordpress.org/Author_Templates
 *
 * @package WordPress
 * @subpackage Template
 */

function get_the_author($deprecated = '') {
	global $authordata;

	if ( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '2.1' );

	return apply_filters('the_author', is_object($authordata) ? $authordata->display_name : null);
}

function get_the_modified_author() {
	if ( $last_id = get_post_meta( get_post()->ID, '_edit_last', true) ) {
		$last_user = get_userdata($last_id);

		return apply_filters('the_modified_author', $last_user->display_name);
	}
}

function get_the_author_meta( $field = '', $user_id = false ) {
	$original_user_id = $user_id;

	if ( ! $user_id ) {
		global $authordata;
		$user_id = isset( $authordata->ID ) ? $authordata->ID : 0;
	} else {
		$authordata = get_userdata( $user_id );
	}

	if ( in_array( $field, array( 'login', 'pass', 'nicename', 'email', 'url', 'registered', 'activation_key', 'status' ) ) )
		$field = 'user_' . $field;

	$value = isset( $authordata->$field ) ? $authordata->$field : '';

	return apply_filters( 'get_the_author_' . $field, $value, $user_id, $original_user_id );
}

function get_the_author_link() {
	if ( get_the_author_meta('url') ) {
		return '<a href="' . esc_url( get_the_author_meta('url') ) . '" title="' . esc_attr( sprintf(__("Visit %s&#8217;s website"), get_the_author()) ) . '" rel="author external">' . get_the_author() . '</a>';
	} else {
		return get_the_author();
	}
}

function get_the_author_posts() {
	$post = get_post();
	if ( ! $post ) {
		return 0;
	}
	return count_user_posts( $post->post_author, $post->post_type );
}

function get_the_author_posts_link() {
	global $authordata;
	if ( ! is_object( $authordata ) ) {
		return;
	}

	$link = sprintf(
		'<a href="%1$s" title="%2$s" rel="author">%3$s</a>',
		esc_url( get_author_posts_url( $authordata->ID, $authordata->user_nicename ) ),
		esc_attr( sprintf( __( 'Posts by %s' ), get_the_author() ) ),
		get_the_author()
	);

	return apply_filters( 'the_author_posts_link', $link );
}

function the_author_posts_link( $deprecated = '' ) {
	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '2.1' );
	}
	echo get_the_author_posts_link();
}

function get_author_posts_url( $author_id, $author_nicename = '' ) {
	global $wp_rewrite;
	$auth_ID = (int) $author_id;
	$link = $wp_rewrite->get_author_permastruct();

	if ( empty($link) ) {
		$file = home_url( '/' );
		$link = $file . '?author=' . $auth_ID;
	} else {
		if ( '' == $author_nicename ) {
			$user = get_userdata($author_id);
			if ( !empty($user->user_nicename) )
				$author_nicename = $user->user_nicename;
		}
		$link = str_replace('%author%', $author_nicename, $link);
		$link = home_url( user_trailingslashit( $link ) );
	}

	$link = apply_filters( 'author_link', $link, $author_id, $author_nicename );

	return $link;
}

function wp_list_authors( $args = '' ) {
	global $wpdb;

	$defaults = array(
		'orderby' => 'name', 'order' => 'ASC', 'number' => '',
		'optioncount' => false, 'exclude_admin' => true,
		'show_fullname' => false, 'hide_empty' => true,
		'feed' => '', 'feed_image' => '', 'feed_type' => '', 'echo' => true,
		'style' => 'list', 'html' => true, 'exclude' => '', 'include' => ''
	);

	$args = wp_parse_args( $args, $defaults );

	$return = '';

	$query_args = wp_array_slice_assoc( $args, array( 'orderby', 'order', 'number', 'exclude', 'include' ) );
	$query_args['fields'] = 'ids';
	$authors = get_users( $query_args );

	$author_count = array();
	foreach ( (array) $wpdb->get_results( "SELECT DISTINCT post_author, COUNT(ID) AS count FROM $wpdb->posts WHERE " . get_private_posts_cap_sql( 'post' ) . " GROUP BY post_author" ) as $row ) {
		$author_count[$row->post_author] = $row->count;
	}
	foreach ( $authors as $author_id ) {
		$author = get_userdata( $author_id );
		if ( $args['exclude_admin'] && 'admin' == $author->display_name ) {
			continue;
		}
		$posts = isset( $author_count[$author->ID] ) ? $author_count[$author->ID] : 0;
		if ( ! $posts && $args['hide_empty'] ) {
			continue;
		}
		if ( $args['show_fullname'] && $author->first_name && $author->last_name ) {
			$name = "$author->first_name $author->last_name";
		} else {
			$name = $author->display_name;
		}
		if ( ! $args['html'] ) {
			$return .= $name . ', ';
			continue;
		}
		if ( 'list' == $args['style'] ) {
			$return .= '<li>';
		}
		$link = '<a href="' . get_author_posts_url( $author->ID, $author->user_nicename ) . '" title="' . esc_attr( sprintf("Posts by %s", $author->display_name) ) . '">' . $name . '</a>';
		if ( ! empty( $args['feed_image'] ) || ! empty( $args['feed'] ) ) {
			$link .= ' ';
			if ( empty( $args['feed_image'] ) ) {
				$link .= '(';
			}
			$link .= '<a href="' . get_author_feed_link( $author->ID, $args['feed_type'] ) . '"';
			$alt = '';
			if ( ! empty( $args['feed'] ) ) {
				$alt = ' alt="' . esc_attr( $args['feed'] ) . '"';
				$name = $args['feed'];
			}
			$link .= '>';
			if ( ! empty( $args['feed_image'] ) ) {
				$link .= '<img src="' . esc_url( $args['feed_image'] ) . '" style="border: none;"' . $alt . ' />';
			} else {
				$link .= $name;
			}
			$link .= '</a>';
			if ( empty( $args['feed_image'] ) ) {
				$link .= ')';
			}
		}

		if ( $args['optioncount'] ) {
			$link .= ' ('. $posts . ')';
		}

		$return .= $link;
		$return .= ( 'list' == $args['style'] ) ? '</li>' : ', ';
	}

	$return = rtrim( $return, ', ' );

	if ( ! $args['echo'] ) {
		return $return;
	}
	echo $return;
}
