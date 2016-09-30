<?php

require_once( __DIR__ . '/wp-load.php' );

$wp->main();

if ( !isset($wp_the_query) )
	$wp_the_query = $wp_query;

require_once( ABSPATH . WPINC . '/template-loader.php' );
