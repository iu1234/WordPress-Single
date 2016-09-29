<?php

function get_option( $option, $default = false ) {
	global $wpdb, $wp_object_cache;
	$option = trim( $option );
	if ( empty( $option ) ) return false;
	$pre = apply_filters( 'pre_option_' . $option, false, $option );
	if ( false !== $pre ) return $pre;
	$notoptions = $wp_object_cache->get( 'notoptions', 'options' );
	if ( isset( $notoptions[ $option ] ) ) {
		return apply_filters( 'default_option_' . $option, $default, $option );
	}
	$alloptions = wp_load_alloptions();
	if ( isset( $alloptions[$option] ) ) {
		$value = $alloptions[$option];
	} else {
		$value = $wp_object_cache->get( $option, 'options' );

		if ( false === $value ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
			if ( is_object( $row ) ) {
				$value = $row->option_value;
				wp_cache_add( $option, $value, 'options' );
			} else {
				if ( ! is_array( $notoptions ) ) {
					 $notoptions = array();
				}
				$notoptions[$option] = true;
				wp_cache_set( 'notoptions', $notoptions, 'options' );
				return apply_filters( 'default_option_' . $option, $default, $option );
			}
		}
	}
	if ( 'home' == $option && '' == $value )
		return get_option( 'siteurl' );
	if ( in_array( $option, array('siteurl', 'home', 'category_base', 'tag_base') ) )
		$value = untrailingslashit( $value );
	return apply_filters( 'option_' . $option, maybe_unserialize( $value ), $option );
}

function wp_protect_special_option( $option ) {
	if ( 'alloptions' === $option || 'notoptions' === $option )
		wp_die( sprintf( '%s is a protected WP option and may not be modified', esc_html( $option ) ) );
}

function form_option( $option ) {
	echo esc_attr( get_option( $option ) );
}

function wp_load_alloptions() {
	global $wpdb, , $wp_object_cache;
	$alloptions = $wp_object_cache->get( 'alloptions', 'options' );
	if ( !$alloptions ) {
		$suppress = $wpdb->suppress_errors();
		if ( !$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE autoload = 'yes'" ) )
			$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options" );
		$wpdb->suppress_errors($suppress);
		$alloptions = array();
		foreach ( (array) $alloptions_db as $o ) {
			$alloptions[$o->option_name] = $o->option_value;
		}
		if ( ! wp_installing() || ! is_multisite() )
			wp_cache_add( 'alloptions', $alloptions, 'options' );
	}
	return $alloptions;
}

function wp_load_core_site_options( $site_id = null ) {
	global $wpdb;
	if ( empty($site_id) )
		$site_id = $wpdb->siteid;
	$core_options = array('site_name', 'siteurl', 'active_sitewide_plugins', '_site_transient_timeout_theme_roots', '_site_transient_theme_roots', 'site_admins', 'can_compress_scripts', 'global_terms_enabled', 'ms_files_rewriting' );
	$core_options_in = "'" . implode("', '", $core_options) . "'";
	$options = $wpdb->get_results( $wpdb->prepare("SELECT meta_key, meta_value FROM $wpdb->sitemeta WHERE meta_key IN ($core_options_in) AND site_id = %d", $site_id) );
	foreach ( $options as $option ) {
		$key = $option->meta_key;
		$cache_key = "{$site_id}:$key";
		$option->meta_value = maybe_unserialize( $option->meta_value );

		wp_cache_set( $cache_key, $option->meta_value, 'site-options' );
	}
}

function update_option( $option, $value, $autoload = null ) {
	global $wpdb, $wp_object_cache;
	$option = trim($option);
	if ( empty($option) )
		return false;
	wp_protect_special_option( $option );
	if ( is_object( $value ) )
		$value = clone $value;
	$value = sanitize_option( $option, $value );
	$old_value = get_option( $option );
	$value = apply_filters( 'pre_update_option_' . $option, $value, $old_value, $option );
	$value = apply_filters( 'pre_update_option', $value, $option, $old_value );
	if ( $value === $old_value )
		return false;
	if ( apply_filters( 'default_option_' . $option, false ) === $old_value ) {
		if ( null === $autoload ) {
			$autoload = 'yes';
		}
		return add_option( $option, $value, '', $autoload );
	}
	$serialized_value = maybe_serialize( $value );
	do_action( 'update_option', $option, $old_value, $value );
	$update_args = array(
		'option_value' => $serialized_value,
	);
	if ( null !== $autoload ) {
		$update_args['autoload'] = ( 'no' === $autoload || false === $autoload ) ? 'no' : 'yes';
	}
	$result = $wpdb->update( $wpdb->options, $update_args, array( 'option_name' => $option ) );
	if ( ! $result )
		return false;
	$notoptions = $wp_object_cache->get( 'notoptions', 'options' );
	if ( is_array( $notoptions ) && isset( $notoptions[$option] ) ) {
		unset( $notoptions[$option] );
		wp_cache_set( 'notoptions', $notoptions, 'options' );
	}
	$alloptions = wp_load_alloptions();
	if ( isset( $alloptions[$option] ) ) {
		$alloptions[ $option ] = $serialized_value;
		wp_cache_set( 'alloptions', $alloptions, 'options' );
	} else {
		wp_cache_set( $option, $serialized_value, 'options' );
	}
	do_action( "update_option_{$option}", $old_value, $value, $option );
	do_action( 'updated_option', $option, $old_value, $value );
	return true;
}

function add_option( $option, $value = '', $autoload = 'yes' ) {
	global $wpdb, $wp_object_cache;
	$option = trim($option);
	if ( empty($option) )
		return false;
	wp_protect_special_option( $option );
	if ( is_object($value) )
		$value = clone $value;

	$value = sanitize_option( $option, $value );
	$notoptions = $wp_object_cache->get( 'notoptions', 'options' );
	if ( !is_array( $notoptions ) || !isset( $notoptions[$option] ) )
		if ( apply_filters( 'default_option_' . $option, false ) !== get_option( $option ) )
			return false;

	$serialized_value = maybe_serialize( $value );
	$autoload = ( 'no' === $autoload || false === $autoload ) ? 'no' : 'yes';

	do_action( 'add_option', $option, $value );

	$result = $wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", $option, $serialized_value, $autoload ) );
	if ( ! $result )
		return false;

	if ( 'yes' == $autoload ) {
		$alloptions = wp_load_alloptions();
		$alloptions[ $option ] = $serialized_value;
		wp_cache_set( 'alloptions', $alloptions, 'options' );
	} else {
		wp_cache_set( $option, $serialized_value, 'options' );
	}
	$notoptions = $wp_object_cache->get( 'notoptions', 'options' );
	if ( is_array( $notoptions ) && isset( $notoptions[$option] ) ) {
		unset( $notoptions[$option] );
		wp_cache_set( 'notoptions', $notoptions, 'options' );
	}
	do_action( "add_option_{$option}", $option, $value );
	do_action( 'added_option', $option, $value );
	return true;
}

function delete_option( $option ) {
	global $wpdb;
	$option = trim( $option );
	if ( empty( $option ) )
		return false;
	wp_protect_special_option( $option );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) );
	if ( is_null( $row ) )
		return false;
	do_action( 'delete_option', $option );
	$result = $wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );
	if ( 'yes' == $row->autoload ) {
		$alloptions = wp_load_alloptions();
		if ( is_array( $alloptions ) && isset( $alloptions[$option] ) ) {
			unset( $alloptions[$option] );
			wp_cache_set( 'alloptions', $alloptions, 'options' );
		}
	} else {
		wp_cache_delete( $option, 'options' );
	}
	if ( $result ) {
		do_action( "delete_option_$option", $option );
		do_action( 'deleted_option', $option );
		return true;
	}
	return false;
}

function delete_transient( $transient ) {
	do_action( 'delete_transient_' . $transient, $transient );
	if ( wp_using_ext_object_cache() ) {
		$result = wp_cache_delete( $transient, 'transient' );
	} else {
		$option_timeout = '_transient_timeout_' . $transient;
		$option = '_transient_' . $transient;
		$result = delete_option( $option );
		if ( $result )
			delete_option( $option_timeout );
	}
	if ( $result ) {
		do_action( 'deleted_transient', $transient );
	}
	return $result;
}

function get_transient( $transient ) {
	$pre = apply_filters( 'pre_transient_' . $transient, false, $transient );
	if ( false !== $pre )
		return $pre;
	$transient_option = '_transient_' . $transient;
	$alloptions = wp_load_alloptions();
	if ( !isset( $alloptions[$transient_option] ) ) {
		$transient_timeout = '_transient_timeout_' . $transient;
		$timeout = get_option( $transient_timeout );
		if ( false !== $timeout && $timeout < time() ) {
			delete_option( $transient_option  );
			delete_option( $transient_timeout );
			$value = false;
		}
	}
	if ( ! isset( $value ) )
		$value = get_option( $transient_option );
	return apply_filters( 'transient_' . $transient, $value, $transient );
}

function set_transient( $transient, $value, $expiration = 0 ) {
	$expiration = (int) $expiration;
	$value = apply_filters( 'pre_set_transient_' . $transient, $value, $expiration, $transient );
	$expiration = apply_filters( 'expiration_of_transient_' . $transient, $expiration, $value, $transient );
	if ( wp_using_ext_object_cache() ) {
		$result = wp_cache_set( $transient, $value, 'transient', $expiration );
	} else {
		$transient_timeout = '_transient_timeout_' . $transient;
		$transient_option = '_transient_' . $transient;
		if ( false === get_option( $transient_option ) ) {
			$autoload = 'yes';
			if ( $expiration ) {
				$autoload = 'no';
				add_option( $transient_timeout, time() + $expiration, '', 'no' );
			}
			$result = add_option( $transient_option, $value, '', $autoload );
		} else {
			$update = true;
			if ( $expiration ) {
				if ( false === get_option( $transient_timeout ) ) {
					delete_option( $transient_option );
					add_option( $transient_timeout, time() + $expiration, '', 'no' );
					$result = add_option( $transient_option, $value, '', 'no' );
					$update = false;
				} else {
					update_option( $transient_timeout, time() + $expiration );
				}
			}
			if ( $update ) {
				$result = update_option( $transient_option, $value );
			}
		}
	}
	if ( $result ) {
		do_action( 'set_transient_' . $transient, $value, $expiration, $transient );
		do_action( 'setted_transient', $transient, $value, $expiration );
	}
	return $result;
}

function wp_user_settings() {
	if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! $user_id = get_current_user_id() ) {
		return;
	}
	if ( is_super_admin() && ! is_user_member_of_blog() ) {
		return;
	}
	$settings = (string) get_user_option( 'user-settings', $user_id );
	if ( isset( $_COOKIE['wp-settings-' . $user_id] ) ) {
		$cookie = preg_replace( '/[^A-Za-z0-9=&_]/', '', $_COOKIE['wp-settings-' . $user_id] );
		if ( $cookie == $settings )
			return;
		$last_saved = (int) get_user_option( 'user-settings-time', $user_id );
		$current = isset( $_COOKIE['wp-settings-time-' . $user_id]) ? preg_replace( '/[^0-9]/', '', $_COOKIE['wp-settings-time-' . $user_id] ) : 0;
		if ( $current > $last_saved ) {
			update_user_option( $user_id, 'user-settings', $cookie, false );
			update_user_option( $user_id, 'user-settings-time', time() - 5, false );
			return;
		}
	}
	$secure = ( 'https' === parse_url( admin_url(), PHP_URL_SCHEME ) );
	setcookie( 'wp-settings-' . $user_id, $settings, time() + YEAR_IN_SECONDS, SITECOOKIEPATH, null, $secure );
	setcookie( 'wp-settings-time-' . $user_id, time(), time() + YEAR_IN_SECONDS, SITECOOKIEPATH, null, $secure );
	$_COOKIE['wp-settings-' . $user_id] = $settings;
}

function get_user_setting( $name, $default = false ) {
	$all_user_settings = get_all_user_settings();
	return isset( $all_user_settings[$name] ) ? $all_user_settings[$name] : $default;
}

function set_user_setting( $name, $value ) {
	if ( headers_sent() ) {
		return false;
	}
	$all_user_settings = get_all_user_settings();
	$all_user_settings[$name] = $value;
	return wp_set_all_user_settings( $all_user_settings );
}

function delete_user_setting( $names ) {
	if ( headers_sent() ) {
		return false;
	}
	$all_user_settings = get_all_user_settings();
	$names = (array) $names;
	$deleted = false;
	foreach ( $names as $name ) {
		if ( isset( $all_user_settings[$name] ) ) {
			unset( $all_user_settings[$name] );
			$deleted = true;
		}
	}
	if ( $deleted ) {
		return wp_set_all_user_settings( $all_user_settings );
	}
	return false;
}

function get_all_user_settings() {
	global $_updated_user_settings;
	if ( ! $user_id = get_current_user_id() ) {
		return array();
	}
	if ( isset( $_updated_user_settings ) && is_array( $_updated_user_settings ) ) {
		return $_updated_user_settings;
	}
	$user_settings = array();
	if ( isset( $_COOKIE['wp-settings-' . $user_id] ) ) {
		$cookie = preg_replace( '/[^A-Za-z0-9=&_-]/', '', $_COOKIE['wp-settings-' . $user_id] );
		if ( strpos( $cookie, '=' ) ) {
			parse_str( $cookie, $user_settings );
		}
	} else {
		$option = get_user_option( 'user-settings', $user_id );

		if ( $option && is_string( $option ) ) {
			parse_str( $option, $user_settings );
		}
	}
	$_updated_user_settings = $user_settings;
	return $user_settings;
}

function wp_set_all_user_settings( $user_settings ) {
	global $_updated_user_settings;
	if ( ! $user_id = get_current_user_id() ) {
		return false;
	}
	if ( is_super_admin() && ! is_user_member_of_blog() ) {
		return;
	}
	$settings = '';
	foreach ( $user_settings as $name => $value ) {
		$_name = preg_replace( '/[^A-Za-z0-9_-]+/', '', $name );
		$_value = preg_replace( '/[^A-Za-z0-9_-]+/', '', $value );

		if ( ! empty( $_name ) ) {
			$settings .= $_name . '=' . $_value . '&';
		}
	}
	$settings = rtrim( $settings, '&' );
	parse_str( $settings, $_updated_user_settings );
	update_user_option( $user_id, 'user-settings', $settings, false );
	update_user_option( $user_id, 'user-settings-time', time(), false );
	return true;
}

function delete_all_user_settings() {
	if ( ! $user_id = get_current_user_id() ) {
		return;
	}
	update_user_option( $user_id, 'user-settings', '', false );
	setcookie( 'wp-settings-' . $user_id, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH );
}

function update_network_option( $network_id, $option, $value ) {
	global $wpdb, $current_site;
	if ( $network_id && ! is_numeric( $network_id ) ) {
		return false;
	}
	$network_id = (int) $network_id;
	wp_protect_special_option( $option );
	$old_value = get_network_option( $network_id, $option, false );
	$value = apply_filters( 'pre_update_site_option_' . $option, $value, $old_value, $option );

	if ( $value === $old_value ) {
		return false;
	}

	if ( false === $old_value ) {
		return add_network_option( $network_id, $option, $value );
	}

	$notoptions_key = "$network_id:notoptions";
	$notoptions = $wp_object_cache->get( $notoptions_key, 'site-options' );
	if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
		unset( $notoptions[ $option ] );
		wp_cache_set( $notoptions_key, $notoptions, 'site-options' );
	}

	$result = update_option( $option, $value );

	if ( $result ) {
		do_action( 'update_site_option_' . $option, $option, $value, $old_value );
		do_action( 'update_site_option', $option, $value, $old_value );
		return true;
	}

	return false;
}

function delete_site_transient( $transient ) {
	do_action( 'delete_site_transient_' . $transient, $transient );

	if ( wp_using_ext_object_cache() ) {
		$result = wp_cache_delete( $transient, 'site-transient' );
	} else {
		$option_timeout = '_site_transient_timeout_' . $transient;
		$option = '_site_transient_' . $transient;
		$result = delete_site_option( $option );
		if ( $result )
			delete_site_option( $option_timeout );
	}
	if ( $result ) {
		do_action( 'deleted_site_transient', $transient );
	}
	return $result;
}

function get_site_transient( $transient ) {
	$pre = apply_filters( 'pre_site_transient_' . $transient, false, $transient );
	if ( false !== $pre )
		return $pre;

	$no_timeout = array('update_core', 'update_plugins', 'update_themes');
	$transient_option = '_site_transient_' . $transient;
	if ( ! in_array( $transient, $no_timeout ) ) {
		$transient_timeout = '_site_transient_timeout_' . $transient;
		$timeout = get_site_option( $transient_timeout );
		if ( false !== $timeout && $timeout < time() ) {
			delete_site_option( $transient_option  );
			delete_site_option( $transient_timeout );
			$value = false;
		}
	}
	if ( ! isset( $value ) )
		$value = get_site_option( $transient_option );
	return apply_filters( 'site_transient_' . $transient, $value, $transient );
}

function set_site_transient( $transient, $value, $expiration = 0 ) {

	$value = apply_filters( 'pre_set_site_transient_' . $transient, $value, $transient );

	$expiration = (int) $expiration;

	$expiration = apply_filters( 'expiration_of_site_transient_' . $transient, $expiration, $value, $transient );

	if ( wp_using_ext_object_cache() ) {
		$result = wp_cache_set( $transient, $value, 'site-transient', $expiration );
	} else {
		$transient_timeout = '_site_transient_timeout_' . $transient;
		$option = '_site_transient_' . $transient;
		if ( false === get_site_option( $option ) ) {
			if ( $expiration )
				add_site_option( $transient_timeout, time() + $expiration );
			$result = add_site_option( $option, $value );
		} else {
			if ( $expiration )
				update_site_option( $transient_timeout, time() + $expiration );
			$result = update_site_option( $option, $value );
		}
	}
	if ( $result ) {
		do_action( 'set_site_transient_' . $transient, $value, $expiration, $transient );
		do_action( 'setted_site_transient', $transient, $value, $expiration );
	}
	return $result;
}
