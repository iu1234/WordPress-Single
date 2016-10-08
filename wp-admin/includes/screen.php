<?php

function get_column_headers( $screen ) {
	if ( is_string( $screen ) )
		$screen = convert_to_screen( $screen );

	static $column_headers = array();

	if ( ! isset( $column_headers[ $screen->id ] ) ) {
		$column_headers[ $screen->id ] = apply_filters( "manage_{$screen->id}_columns", array() );
	}

	return $column_headers[ $screen->id ];
}

function get_hidden_columns( $screen ) {
	if ( is_string( $screen ) ) {
		$screen = convert_to_screen( $screen );
	}

	$hidden = get_user_option( 'manage' . $screen->id . 'columnshidden' );

	$use_defaults = ! is_array( $hidden );

	if ( $use_defaults ) {
		$hidden = array();

		$hidden = apply_filters( 'default_hidden_columns', $hidden, $screen );
	}

	return apply_filters( 'hidden_columns', $hidden, $screen, $use_defaults );
}

function meta_box_prefs( $screen ) {
	global $wp_meta_boxes;

	if ( is_string( $screen ) )
		$screen = convert_to_screen( $screen );

	if ( empty($wp_meta_boxes[$screen->id]) )
		return;

	$hidden = get_hidden_meta_boxes($screen);

	foreach ( array_keys( $wp_meta_boxes[ $screen->id ] ) as $context ) {
		foreach ( array( 'high', 'core', 'default', 'low' ) as $priority ) {
			if ( ! isset( $wp_meta_boxes[ $screen->id ][ $context ][ $priority ] ) ) {
				continue;
			}
			foreach ( $wp_meta_boxes[ $screen->id ][ $context ][ $priority ] as $box ) {
				if ( false == $box || ! $box['title'] )
					continue;
				// Submit box cannot be hidden
				if ( 'submitdiv' == $box['id'] || 'linksubmitdiv' == $box['id'] )
					continue;
				$box_id = $box['id'];
				echo '<label for="' . $box_id . '-hide">';
				echo '<input class="hide-postbox-tog" name="' . $box_id . '-hide" type="checkbox" id="' . $box_id . '-hide" value="' . $box_id . '"' . (! in_array($box_id, $hidden) ? ' checked="checked"' : '') . ' />';
				echo "{$box['title']}</label>\n";
			}
		}
	}
}

function get_hidden_meta_boxes( $screen ) {
	if ( is_string( $screen ) )
		$screen = convert_to_screen( $screen );

	$hidden = get_user_option( "metaboxhidden_{$screen->id}" );

	$use_defaults = ! is_array( $hidden );

	// Hide slug boxes by default
	if ( $use_defaults ) {
		$hidden = array();
		if ( 'post' == $screen->base ) {
			if ( 'post' == $screen->post_type || 'page' == $screen->post_type || 'attachment' == $screen->post_type )
				$hidden = array('slugdiv', 'trackbacksdiv', 'postcustom', 'postexcerpt', 'commentstatusdiv', 'commentsdiv', 'authordiv', 'revisionsdiv');
			else
				$hidden = array( 'slugdiv' );
		}

		$hidden = apply_filters( 'default_hidden_meta_boxes', $hidden, $screen );
	}

	return apply_filters( 'hidden_meta_boxes', $hidden, $screen, $use_defaults );
}

function add_screen_option( $option, $args = array() ) {
	$current_screen = get_current_screen();
	if ( ! $current_screen )
		return;
	$current_screen->add_option( $option, $args );
}

function get_current_screen() {
	global $current_screen;
	if ( ! isset( $current_screen ) )
		return null;
	return $current_screen;
}

function set_current_screen( $hook_name = '' ) {
	WP_Screen::get( $hook_name )->set_current_screen();
}
