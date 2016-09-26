<?php
/**
 * Dependencies API: Styles functions
 *
 * @since 2.6.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

function wp_styles() {
	global $wp_styles;
	if ( ! ( $wp_styles instanceof WP_Styles ) ) {
		$wp_styles = new WP_Styles();
	}
	return $wp_styles;
}

function wp_print_styles( $handles = false ) {
	if ( '' === $handles ) { // for wp_head
		$handles = false;
	}
	if ( ! $handles ) {
		do_action( 'wp_print_styles' );
	}
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );
	global $wp_styles;
	if ( ! ( $wp_styles instanceof WP_Styles ) ) {
		if ( ! $handles ) {
			return array(); // No need to instantiate if nothing is there.
		}
	}

	return wp_styles()->do_items( $handles );
}

function wp_add_inline_style( $handle, $data ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );
	if ( false !== stripos( $data, '</style>' ) ) {
		_doing_it_wrong( __FUNCTION__, 'Do not pass style tags to wp_add_inline_style().', '3.7' );
		$data = trim( preg_replace( '#<style[^>]*>(.*)</style>#is', '$1', $data ) );
	}
	return wp_styles()->add_inline_style( $handle, $data );
}

function wp_register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );

	return wp_styles()->add( $handle, $src, $deps, $ver, $media );
}

function wp_deregister_style( $handle ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );
	wp_styles()->remove( $handle );
}

function wp_enqueue_style( $handle, $src = false, $deps = array(), $ver = false, $media = 'all' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );

	$wp_styles = wp_styles();

	if ( $src ) {
		$_handle = explode('?', $handle);
		$wp_styles->add( $_handle[0], $src, $deps, $ver, $media );
	}
	$wp_styles->enqueue( $handle );
}

function wp_dequeue_style( $handle ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );
	wp_styles()->dequeue( $handle );
}

function wp_style_is( $handle, $list = 'enqueued' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );
	return (bool) wp_styles()->query( $handle, $list );
}

function wp_style_add_data( $handle, $key, $value ) {
	return wp_styles()->add_data( $handle, $key, $value );
}
