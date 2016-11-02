<?php

function has_post_thumbnail( $post = null ) {
	return (bool) get_post_thumbnail_id( $post );
}

function get_post_thumbnail_id( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) { return ''; }
	return get_post_meta( $post->ID, '_thumbnail_id', true );
}

function the_post_thumbnail( $size = 'post-thumbnail', $attr = '' ) {
	echo get_the_post_thumbnail( null, $size, $attr );
}

function update_post_thumbnail_cache( $wp_query = null ) {
	if ( ! $wp_query )
		$wp_query = $GLOBALS['wp_query'];

	if ( $wp_query->thumbnails_cached )
		return;

	$thumb_ids = array();
	foreach ( $wp_query->posts as $post ) {
		if ( $id = get_post_thumbnail_id( $post->ID ) )
			$thumb_ids[] = $id;
	}

	if ( ! empty ( $thumb_ids ) ) {
		_prime_post_caches( $thumb_ids, false, true );
	}

	$wp_query->thumbnails_cached = true;
}

function get_the_post_thumbnail( $post = null, $size = 'post-thumbnail', $attr = '' ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$post_thumbnail_id = get_post_thumbnail_id( $post );

	$size = apply_filters( 'post_thumbnail_size', $size );

	if ( $post_thumbnail_id ) {

		do_action( 'begin_fetch_post_thumbnail_html', $post->ID, $post_thumbnail_id, $size );
		if ( in_the_loop() )
			update_post_thumbnail_cache();
		$html = wp_get_attachment_image( $post_thumbnail_id, $size, false, $attr );

		do_action( 'end_fetch_post_thumbnail_html', $post->ID, $post_thumbnail_id, $size );

	} else {
		$html = '';
	}

	return apply_filters( 'post_thumbnail_html', $html, $post->ID, $post_thumbnail_id, $size, $attr );
}

function get_the_post_thumbnail_url( $post = null, $size = 'post-thumbnail' ) {
	$post_thumbnail_id = get_post_thumbnail_id( $post );
	if ( ! $post_thumbnail_id ) {
		return false;
	}
	return wp_get_attachment_image_url( $post_thumbnail_id, $size );
}

function the_post_thumbnail_url( $size = 'post-thumbnail' ) {
	$url = get_the_post_thumbnail_url( null, $size );
	if ( $url ) {
		echo esc_url( $url );
	}
}
