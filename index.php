<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

if ( !isset($wp_did_header) ) {

	$wp_did_header = true;

	require_once( __DIR__ . '/wp-load.php' );

	wp();

	require_once( ABSPATH . WPINC . '/template-loader.php' );

}
