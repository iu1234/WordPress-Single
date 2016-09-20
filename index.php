<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

require_once( __DIR__ . '/wp-load.php' );

$wp->main();

if ( !isset($wp_the_query) )
	$wp_the_query = $wp_query;

require_once( ABSPATH . WPINC . '/template-loader.php' );
