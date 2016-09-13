<?php
/**
 * Defines constants and global variables that can be overridden.
 *
 * @package WordPress
 */

function wp_initial_constants() {

	if ( !defined('WP_MEMORY_LIMIT') ) {
		define('WP_MEMORY_LIMIT', '40M');
	}
	if ( ! defined( 'WP_MAX_MEMORY_LIMIT' ) ) {
		define( 'WP_MAX_MEMORY_LIMIT', '256M' );
	}

	if ( function_exists( 'memory_get_usage' ) ) {
		$current_limit = @ini_get( 'memory_limit' );
		$current_limit_int = intval( $current_limit );
		if ( false !== strpos( $current_limit, 'G' ) )
			$current_limit_int *= 1024;
		$wp_limit_int = intval( WP_MEMORY_LIMIT );
		if ( false !== strpos( WP_MEMORY_LIMIT, 'G' ) )
			$wp_limit_int *= 1024;
		if ( -1 != $current_limit && ( -1 == WP_MEMORY_LIMIT || $current_limit_int < $wp_limit_int ) )
			@ini_set( 'memory_limit', WP_MEMORY_LIMIT );
	}

	if ( !defined('WP_CONTENT_DIR') )
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

	if ( ! defined( 'SCRIPT_DEBUG' ) ) {
		if ( ! empty( $GLOBALS['wp_version'] ) ) {
			$develop_src = false !== strpos( $GLOBALS['wp_version'], '-src' );
		} else {
			$develop_src = false;
		}
		define( 'SCRIPT_DEBUG', $develop_src );
	}

	if ( !defined('MEDIA_TRASH') )
		define('MEDIA_TRASH', false);

	define( 'WP_FEATURE_BETTER_PASSWORDS', true );

	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS',   60 * MINUTE_IN_SECONDS );
	define( 'DAY_IN_SECONDS',    24 * HOUR_IN_SECONDS   );
	define( 'WEEK_IN_SECONDS',    7 * DAY_IN_SECONDS    );
	define( 'MONTH_IN_SECONDS',  30 * DAY_IN_SECONDS    );
	define( 'YEAR_IN_SECONDS',  365 * DAY_IN_SECONDS    );

	define( 'KB_IN_BYTES', 1024 );
	define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
	define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );
	define( 'TB_IN_BYTES', 1024 * GB_IN_BYTES );
}

function wp_plugin_directory_constants() {
	if ( !defined('WP_CONTENT_URL') )
		define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');

	if ( !defined('WP_PLUGIN_DIR') )
		define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

	if ( !defined('WP_PLUGIN_URL') )
		define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );
}

function wp_cookie_constants() {

	if ( !defined( 'COOKIEHASH' ) ) {
		$siteurl = get_site_option( 'siteurl' );
		if ( $siteurl )
			define( 'COOKIEHASH', md5( $siteurl ) );
		else
			define( 'COOKIEHASH', '' );
	}

	if ( !defined('USER_COOKIE') )
		define('USER_COOKIE', 'wordpressuser_' . COOKIEHASH);

	if ( !defined('PASS_COOKIE') )
		define('PASS_COOKIE', 'wordpresspass_' . COOKIEHASH);

	if ( !defined('AUTH_COOKIE') )
		define('AUTH_COOKIE', 'wordpress_' . COOKIEHASH);

	if ( !defined('SECURE_AUTH_COOKIE') )
		define('SECURE_AUTH_COOKIE', 'wordpress_sec_' . COOKIEHASH);

	if ( !defined('LOGGED_IN_COOKIE') )
		define('LOGGED_IN_COOKIE', 'wordpress_logged_in_' . COOKIEHASH);

	if ( !defined('TEST_COOKIE') )
		define('TEST_COOKIE', 'wordpress_test_cookie');

	if ( !defined('COOKIEPATH') )
		define('COOKIEPATH', preg_replace('|https?://[^/]+|i', '', get_option('home') . '/' ) );

	if ( !defined('SITECOOKIEPATH') )
		define('SITECOOKIEPATH', preg_replace('|https?://[^/]+|i', '', get_option('siteurl') . '/' ) );

	if ( !defined('ADMIN_COOKIE_PATH') )
		define( 'ADMIN_COOKIE_PATH', SITECOOKIEPATH . 'wp-admin' );

	if ( !defined('PLUGINS_COOKIE_PATH') )
		define( 'PLUGINS_COOKIE_PATH', preg_replace('|https?://[^/]+|i', '', WP_PLUGIN_URL)  );

	if ( !defined('COOKIE_DOMAIN') )
		define('COOKIE_DOMAIN', false);
}

function wp_ssl_constants() {

	if ( !defined( 'FORCE_SSL_ADMIN' ) ) {
		if ( 'https' === parse_url( get_option( 'siteurl' ), PHP_URL_SCHEME ) ) {
			define( 'FORCE_SSL_ADMIN', true );
		} else {
			define( 'FORCE_SSL_ADMIN', false );
		}
	}
	force_ssl_admin( FORCE_SSL_ADMIN );

	if ( defined( 'FORCE_SSL_LOGIN' ) && FORCE_SSL_LOGIN ) {
		force_ssl_admin( true );
	}
}

function wp_functionality_constants() {

	if ( !defined( 'AUTOSAVE_INTERVAL' ) )
		define( 'AUTOSAVE_INTERVAL', 1200 );

	if ( !defined( 'EMPTY_TRASH_DAYS' ) )
		define( 'EMPTY_TRASH_DAYS', 30 );

	if ( !defined( 'WP_CRON_LOCK_TIMEOUT' ) )
		define('WP_CRON_LOCK_TIMEOUT', 60);
}
