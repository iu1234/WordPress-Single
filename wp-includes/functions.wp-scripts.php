<?php

function wp_scripts() {
	global $wp_scripts;
	if ( ! ( $wp_scripts instanceof WP_Scripts ) ) {
		$wp_scripts = new WP_Scripts();
	}
	return $wp_scripts;
}

function _wp_scripts_maybe_doing_it_wrong( $function ) {
	if ( did_action( 'init' ) ) {
		return;
	}

	_doing_it_wrong( $function, sprintf(
		'Scripts and styles should not be registered or enqueued until the %1$s, %2$s, or %3$s hooks.',
		'<code>wp_enqueue_scripts</code>',
		'<code>admin_enqueue_scripts</code>',
		'<code>login_enqueue_scripts</code>'
	), '3.3' );
}

function wp_print_scripts( $handles = false ) {
	do_action( 'wp_print_scripts' );
	if ( '' === $handles ) {
		$handles = false;
	}

	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );

	global $wp_scripts;
	if ( ! ( $wp_scripts instanceof WP_Scripts ) ) {
		if ( ! $handles ) {
			return array();
		}
	}

	return wp_scripts()->do_items( $handles );
}

function wp_add_inline_script( $handle, $data, $position = 'after' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );

	if ( false !== stripos( $data, '</script>' ) ) {
		_doing_it_wrong( __FUNCTION__, 'Do not pass script tags to wp_add_inline_script().', '4.5.0' );
		$data = trim( preg_replace( '#<script[^>]*>(.*)</script>#is', '$1', $data ) );
	}

	return wp_scripts()->add_inline_script( $handle, $data, $position );
}

function wp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
	$wp_scripts = wp_scripts();
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );

	$registered = $wp_scripts->add( $handle, $src, $deps, $ver );
	if ( $in_footer ) {
		$wp_scripts->add_data( $handle, 'group', 1 );
	}

	return $registered;
}

function wp_localize_script( $handle, $object_name, $l10n ) {
	global $wp_scripts;
	if ( ! ( $wp_scripts instanceof WP_Scripts ) ) {
		_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );
		return false;
	}

	return $wp_scripts->localize( $handle, $object_name, $l10n );
}

function wp_deregister_script( $handle ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );

	$current_filter = current_filter();
	if ( ( is_admin() && 'admin_enqueue_scripts' !== $current_filter ) ||
		( 'wp-login.php' === $GLOBALS['pagenow'] && 'login_enqueue_scripts' !== $current_filter )
	) {
		$no = array(
			'jquery', 'jquery-core', 'jquery-migrate', 'jquery-ui-core', 'jquery-ui-accordion',
			'jquery-ui-autocomplete', 'jquery-ui-button', 'jquery-ui-datepicker', 'jquery-ui-dialog',
			'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-menu', 'jquery-ui-mouse',
			'jquery-ui-position', 'jquery-ui-progressbar', 'jquery-ui-resizable', 'jquery-ui-selectable',
			'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-spinner', 'jquery-ui-tabs',
			'jquery-ui-tooltip', 'jquery-ui-widget', 'underscore', 'backbone',
		);

		if ( in_array( $handle, $no ) ) {
			$message = sprintf( 'Do not deregister the %1$s script in the administration area. To target the front-end theme, use the %2$s hook.',
				"<code>$handle</code>", '<code>wp_enqueue_scripts</code>' );
			_doing_it_wrong( __FUNCTION__, $message, '3.6' );
			return;
		}
	}

	wp_scripts()->remove( $handle );
}

function wp_enqueue_script( $handle, $src = false, $deps = array(), $ver = false, $in_footer = false ) {
	$wp_scripts = wp_scripts();

	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );


	if ( $src || $in_footer ) {
		$_handle = explode( '?', $handle );

		if ( $src ) {
			$wp_scripts->add( $_handle[0], $src, $deps, $ver );
		}

		if ( $in_footer ) {
			$wp_scripts->add_data( $_handle[0], 'group', 1 );
		}
	}

	$wp_scripts->enqueue( $handle );
}

function wp_dequeue_script( $handle ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );
	wp_scripts()->dequeue( $handle );
}

function wp_script_is( $handle, $list = 'enqueued' ) {
	_wp_scripts_maybe_doing_it_wrong( __FUNCTION__ );

	return (bool) wp_scripts()->query( $handle, $list );
}

function wp_script_add_data( $handle, $key, $value ){
	return wp_scripts()->add_data( $handle, $key, $value );
}
