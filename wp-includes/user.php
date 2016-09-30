<?php

function wp_signon( $credentials = array(), $secure_cookie = '' ) {
	if ( empty($credentials) ) {
		if ( ! empty($_POST['log']) )
			$credentials['user_login'] = $_POST['log'];
		if ( ! empty($_POST['pwd']) )
			$credentials['user_password'] = $_POST['pwd'];
		if ( ! empty($_POST['rememberme']) )
			$credentials['remember'] = $_POST['rememberme'];
	}
	if ( !empty($credentials['remember']) )
		$credentials['remember'] = true;
	else
		$credentials['remember'] = false;
	do_action_ref_array( 'wp_authenticate', array( &$credentials['user_login'], &$credentials['user_password'] ) );
	if ( '' === $secure_cookie )
		$secure_cookie = is_ssl();
	$secure_cookie = apply_filters( 'secure_signon_cookie', $secure_cookie, $credentials );
	global $auth_secure_cookie;
	$auth_secure_cookie = $secure_cookie;
	add_filter('authenticate', 'wp_authenticate_cookie', 30, 3);
	$user = wp_authenticate($credentials['user_login'], $credentials['user_password']);
	if ( is_wp_error($user) ) {
		if ( $user->get_error_codes() == array('empty_username', 'empty_password') ) {
			$user = new WP_Error('', '');
		}
		return $user;
	}
	wp_set_auth_cookie($user->ID, $credentials['remember'], $secure_cookie);
	do_action( 'wp_login', $user->user_login, $user );
	return $user;
}

function wp_authenticate_username_password($user, $username, $password) {
	if ( $user instanceof WP_User ) {
		return $user;
	}
	if ( empty($username) || empty($password) ) {
		if ( is_wp_error( $user ) )
			return $user;
		$error = new WP_Error();
		if ( empty($username) )
			$error->add('empty_username', '<strong>ERROR</strong>: The username field is empty.');
		if ( empty($password) )
			$error->add('empty_password', '<strong>ERROR</strong>: The password field is empty.');
		return $error;
	}
	$user = get_user_by('login', $username);
	if ( !$user ) {
		return new WP_Error( 'invalid_username',
			'<strong>ERROR</strong>: Invalid username.' .
			' <a href="' . wp_lostpassword_url() . '">' .
			'Lost your password?' .
			'</a>'
		);
	}
	$user = apply_filters( 'wp_authenticate_user', $user, $password );
	if ( is_wp_error($user) )
		return $user;
	if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
		return new WP_Error( 'incorrect_password',
			sprintf(
				'<strong>ERROR</strong>: The password you entered for the username %s is incorrect.',
				'<strong>' . $username . '</strong>'
			) .
			' <a href="' . wp_lostpassword_url() . '">' .
			'Lost your password?' .
			'</a>'
		);
	}
	return $user;
}

function wp_authenticate_email_password( $user, $email, $password ) {
	if ( $user instanceof WP_User ) {
		return $user;
	}
	if ( empty( $email ) || empty( $password ) ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$error = new WP_Error();
		if ( empty( $email ) ) {
			$error->add( 'empty_username', '<strong>ERROR</strong>: The email field is empty.' );
		}
		if ( empty( $password ) ) {
			$error->add( 'empty_password', '<strong>ERROR</strong>: The password field is empty.' );
		}
		return $error;
	}
	if ( ! is_email( $email ) ) {
		return $user;
	}
	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		return new WP_Error( 'invalid_email',
			'<strong>ERROR</strong>: Invalid email address.' .
			' <a href="' . wp_lostpassword_url() . '">Lost your password?</a>'
		);
	}
	$user = apply_filters( 'wp_authenticate_user', $user, $password );

	if ( is_wp_error( $user ) ) {
		return $user;
	}
	if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
		return new WP_Error( 'incorrect_password',
			sprintf(
				'<strong>ERROR</strong>: The password you entered for the email address %s is incorrect.',
				'<strong>' . $email . '</strong>'
			) .
			' <a href="' . wp_lostpassword_url() . '">Lost your password?</a>'
		);
	}
	return $user;
}

function wp_authenticate_cookie($user, $username, $password) {
	if ( $user instanceof WP_User ) {
		return $user;
	}
	if ( empty($username) && empty($password) ) {
		$user_id = wp_validate_auth_cookie();
		if ( $user_id )
			return new WP_User($user_id);

		global $auth_secure_cookie;

		if ( $auth_secure_cookie )
			$auth_cookie = SECURE_AUTH_COOKIE;
		else
			$auth_cookie = AUTH_COOKIE;

		if ( !empty($_COOKIE[$auth_cookie]) )
			return new WP_Error('expired_session', 'Please log in again.');
	}
	return $user;
}

function wp_validate_logged_in_cookie( $user_id ) {
	if ( $user_id ) {
		return $user_id;
	}
	if ( is_blog_admin() || is_network_admin() || empty( $_COOKIE[LOGGED_IN_COOKIE] ) ) {
		return false;
	}
	return wp_validate_auth_cookie( $_COOKIE[LOGGED_IN_COOKIE], 'logged_in' );
}

function count_user_posts( $userid, $post_type = 'post', $public_only = false ) {
	global $wpdb;

	$where = get_posts_by_author_sql( $post_type, true, $userid, $public_only );

	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts $where" );

	return apply_filters( 'get_usernumposts', $count, $userid, $post_type, $public_only );
}

function count_many_users_posts( $users, $post_type = 'post', $public_only = false ) {
	global $wpdb;

	$count = array();
	if ( empty( $users ) || ! is_array( $users ) )
		return $count;

	$userlist = implode( ',', array_map( 'absint', $users ) );
	$where = get_posts_by_author_sql( $post_type, true, null, $public_only );

	$result = $wpdb->get_results( "SELECT post_author, COUNT(*) FROM $wpdb->posts $where AND post_author IN ($userlist) GROUP BY post_author", ARRAY_N );
	foreach ( $result as $row ) {
		$count[ $row[0] ] = $row[1];
	}

	foreach ( $users as $id ) {
		if ( ! isset( $count[ $id ] ) )
			$count[ $id ] = 0;
	}

	return $count;
}

function get_current_user_id() {
	if ( ! function_exists( 'wp_get_current_user' ) )
		return 0;
	$user = wp_get_current_user();
	return ( isset( $user->ID ) ? (int) $user->ID : 0 );
}

function get_user_option( $option, $user = 0, $deprecated = '' ) {
	global $wpdb;

	if ( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '3.0' );

	if ( empty( $user ) )
		$user = get_current_user_id();

	if ( ! $user = get_user_by( 'id', $user ) )
		return false;

	if ( $user->has_prop( $option ) )
		$result = $user->get( $option );
	else
		$result = false;

	return apply_filters( "get_user_option_{$option}", $result, $option, $user );
}

function update_user_option( $user_id, $option_name, $newvalue, $global = false ) {
	global $wpdb;
	return update_user_meta( $user_id, $option_name, $newvalue );
}

function delete_user_option( $user_id, $option_name, $global = false ) {
	global $wpdb;
	return delete_user_meta( $user_id, $option_name );
}

function get_users( $args = array() ) {
	$args = wp_parse_args( $args );
	$args['count_total'] = false;
	$user_search = new WP_User_Query($args);
	return (array) $user_search->get_results();
}

function get_blogs_of_user( $user_id, $all = false ) {
	global $wpdb;

	$user_id = (int) $user_id;

	// Logged out users can't have blogs
	if ( empty( $user_id ) )
		return array();

	$keys = get_user_meta( $user_id );
	if ( empty( $keys ) )
		return array();

	$blog_id = 0;
	$blogs = array( $blog_id => new stdClass );
	$blogs[ $blog_id ]->userblog_id = $blog_id;
	$blogs[ $blog_id ]->blogname = get_option('blogname');
	$blogs[ $blog_id ]->domain = '';
	$blogs[ $blog_id ]->path = '';
	$blogs[ $blog_id ]->site_id = 1;
	$blogs[ $blog_id ]->siteurl = get_option('siteurl');
	$blogs[ $blog_id ]->archived = 0;
	$blogs[ $blog_id ]->spam = 0;
	$blogs[ $blog_id ]->deleted = 0;
	return $blogs;

	$blogs = array();

	if ( isset( $keys[ $wpdb->base_prefix . 'capabilities' ] ) && defined( 'MULTISITE' ) ) {
		$blog = get_blog_details( 1 );
		if ( $blog && isset( $blog->domain ) && ( $all || ( ! $blog->archived && ! $blog->spam && ! $blog->deleted ) ) ) {
			$blogs[ 1 ] = (object) array(
				'userblog_id' => 1,
				'blogname'    => $blog->blogname,
				'domain'      => $blog->domain,
				'path'        => $blog->path,
				'site_id'     => $blog->site_id,
				'siteurl'     => $blog->siteurl,
				'archived'    => $blog->archived,
				'mature'      => $blog->mature,
				'spam'        => $blog->spam,
				'deleted'     => $blog->deleted,
			);
		}
		unset( $keys[ $wpdb->base_prefix . 'capabilities' ] );
	}

	$keys = array_keys( $keys );

	foreach ( $keys as $key ) {
		if ( 'capabilities' !== substr( $key, -12 ) )
			continue;
		if ( $wpdb->base_prefix && 0 !== strpos( $key, $wpdb->base_prefix ) )
			continue;
		$blog_id = str_replace( array( $wpdb->base_prefix, '_capabilities' ), '', $key );
		if ( ! is_numeric( $blog_id ) )
			continue;

		$blog_id = (int) $blog_id;
		$blog = get_blog_details( $blog_id );
		if ( $blog && isset( $blog->domain ) && ( $all || ( ! $blog->archived && ! $blog->spam && ! $blog->deleted ) ) ) {
			$blogs[ $blog_id ] = (object) array(
				'userblog_id' => $blog_id,
				'blogname'    => $blog->blogname,
				'domain'      => $blog->domain,
				'path'        => $blog->path,
				'site_id'     => $blog->site_id,
				'siteurl'     => $blog->siteurl,
				'archived'    => $blog->archived,
				'mature'      => $blog->mature,
				'spam'        => $blog->spam,
				'deleted'     => $blog->deleted,
			);
		}
	}

	return apply_filters( 'get_blogs_of_user', $blogs, $user_id, $all );
}

function is_user_member_of_blog( $user_id = 0, $blog_id = 0 ) {
	global $wpdb;

	$user_id = (int) $user_id;
	$blog_id = (int) $blog_id;

	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	if ( empty( $user_id ) ) {
		return false;
	} else {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return false;
		}
	}

	return true;
}

function add_user_meta($user_id, $meta_key, $meta_value, $unique = false) {
	return add_metadata('user', $user_id, $meta_key, $meta_value, $unique);
}

function delete_user_meta($user_id, $meta_key, $meta_value = '') {
	return delete_metadata('user', $user_id, $meta_key, $meta_value);
}

function get_user_meta($user_id, $key = '', $single = false) {
	return get_metadata('user', $user_id, $key, $single);
}

function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '') {
	return update_metadata('user', $user_id, $meta_key, $meta_value, $prev_value);
}

function count_users($strategy = 'time') {
	global $wpdb;

	$id = 0;
	$result = array();
	if ( 'time' == $strategy ) {
		$avail_roles = wp_roles()->get_names();

		$select_count = array();
		foreach ( $avail_roles as $this_role => $name ) {
			$select_count[] = $wpdb->prepare( "COUNT(NULLIF(`meta_value` LIKE %s, false))", '%' . $wpdb->esc_like( '"' . $this_role . '"' ) . '%');
		}
		$select_count[] = "COUNT(NULLIF(`meta_value` = 'a:0:{}', false))";
		$select_count = implode(', ', $select_count);
		$row = $wpdb->get_row( "SELECT $select_count, COUNT(*) FROM $wpdb->usermeta WHERE meta_key = 'capabilities'", ARRAY_N );
		$col = 0;
		$role_counts = array();
		foreach ( $avail_roles as $this_role => $name ) {
			$count = (int) $row[$col++];
			if ($count > 0) {
				$role_counts[$this_role] = $count;
			}
		}

		$role_counts['none'] = (int) $row[$col++];
		$total_users = (int) $row[$col];

		$result['total_users'] = $total_users;
		$result['avail_roles'] =& $role_counts;
	} else {
		$avail_roles = array(
			'none' => 0,
		);
		$users_of_blog = $wpdb->get_col( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = 'capabilities'" );
		foreach ( $users_of_blog as $caps_meta ) {
			$b_roles = maybe_unserialize($caps_meta);
			if ( ! is_array( $b_roles ) )
				continue;
			if ( empty( $b_roles ) ) {
				$avail_roles['none']++;
			}
			foreach ( $b_roles as $b_role => $val ) {
				if ( isset($avail_roles[$b_role]) ) {
					$avail_roles[$b_role]++;
				} else {
					$avail_roles[$b_role] = 1;
				}
			}
		}

		$result['total_users'] = count( $users_of_blog );
		$result['avail_roles'] =& $avail_roles;
	}

	return $result;
}

function setup_userdata($for_user_id = '') {
	global $user_login, $userdata, $user_level, $user_ID, $user_email, $user_url, $user_identity;

	if ( '' == $for_user_id )
		$for_user_id = get_current_user_id();
	$user = get_user_by( 'id', $for_user_id );

	if ( ! $user ) {
		$user_ID = 0;
		$user_level = 0;
		$userdata = null;
		$user_login = $user_email = $user_url = $user_identity = '';
		return;
	}

	$user_ID    = (int) $user->ID;
	$user_level = (int) $user->user_level;
	$userdata   = $user;
	$user_login = $user->user_login;
	$user_email = $user->user_email;
	$user_url   = $user->user_url;
	$user_identity = $user->display_name;
}

function wp_dropdown_users( $args = '' ) {
	$defaults = array(
		'show_option_all' => '', 'show_option_none' => '', 'hide_if_only_one_author' => '',
		'orderby' => 'display_name', 'order' => 'ASC',
		'include' => '', 'exclude' => '', 'multi' => 0,
		'show' => 'display_name', 'echo' => 1,
		'selected' => 0, 'name' => 'user', 'class' => '', 'id' => '',
		'blog_id' => $GLOBALS['blog_id'], 'who' => '', 'include_selected' => false,
		'option_none_value' => -1
	);

	$defaults['selected'] = is_author() ? get_query_var( 'author' ) : 0;

	$r = wp_parse_args( $args, $defaults );

	$query_args = wp_array_slice_assoc( $r, array( 'blog_id', 'include', 'exclude', 'orderby', 'order', 'who' ) );

	$fields = array( 'ID', 'user_login' );

	$show = ! empty( $r['show'] ) ? $r['show'] : 'display_name';
	if ( 'display_name_with_login' === $show ) {
		$fields[] = 'display_name';
	} else {
		$fields[] = $show;
	}

	$query_args['fields'] = $fields;

	$show_option_all = $r['show_option_all'];
	$show_option_none = $r['show_option_none'];
	$option_none_value = $r['option_none_value'];

	$query_args = apply_filters( 'wp_dropdown_users_args', $query_args, $r );

	$users = get_users( $query_args );

	$output = '';
	if ( ! empty( $users ) && ( empty( $r['hide_if_only_one_author'] ) || count( $users ) > 1 ) ) {
		$name = esc_attr( $r['name'] );
		if ( $r['multi'] && ! $r['id'] ) {
			$id = '';
		} else {
			$id = $r['id'] ? " id='" . esc_attr( $r['id'] ) . "'" : " id='$name'";
		}
		$output = "<select name='{$name}'{$id} class='" . $r['class'] . "'>\n";

		if ( $show_option_all ) {
			$output .= "\t<option value='0'>$show_option_all</option>\n";
		}

		if ( $show_option_none ) {
			$_selected = selected( $option_none_value, $r['selected'], false );
			$output .= "\t<option value='" . esc_attr( $option_none_value ) . "'$_selected>$show_option_none</option>\n";
		}

		if ( $r['include_selected'] && ( $r['selected'] > 0 ) ) {
			$found_selected = false;
			$r['selected'] = (int) $r['selected'];
			foreach ( (array) $users as $user ) {
				$user->ID = (int) $user->ID;
				if ( $user->ID === $r['selected'] ) {
					$found_selected = true;
				}
			}

			if ( ! $found_selected ) {
				$users[] = get_user_by( 'id', $r['selected'] );
			}
		}

		foreach ( (array) $users as $user ) {
			if ( 'display_name_with_login' === $show ) {
				/* translators: 1: display name, 2: user_login */
				$display = sprintf( _x( '%1$s (%2$s)', 'user dropdown' ), $user->display_name, $user->user_login );
			} elseif ( ! empty( $user->$show ) ) {
				$display = $user->$show;
			} else {
				$display = '(' . $user->user_login . ')';
			}

			$_selected = selected( $user->ID, $r['selected'], false );
			$output .= "\t<option value='$user->ID'$_selected>" . esc_html( $display ) . "</option>\n";
		}

		$output .= "</select>";
	}

	$html = apply_filters( 'wp_dropdown_users', $output );

	if ( $r['echo'] ) {
		echo $html;
	}
	return $html;
}

function sanitize_user_field($field, $value, $user_id, $context) {
	$int_fields = array('ID');
	if ( in_array($field, $int_fields) )
		$value = (int) $value;

	if ( 'raw' == $context )
		return $value;

	if ( !is_string($value) && !is_numeric($value) )
		return $value;

	$prefixed = false !== strpos( $field, 'user_' );

	if ( 'edit' == $context ) {
		if ( $prefixed ) {

			/** This filter is documented in wp-includes/post.php */
			$value = apply_filters( "edit_{$field}", $value, $user_id );
		} else {
			$value = apply_filters( "edit_user_{$field}", $value, $user_id );
		}

		if ( 'description' == $field )
			$value = esc_html( $value ); // textarea_escaped?
		else
			$value = esc_attr($value);
	} elseif ( 'db' == $context ) {
		if ( $prefixed ) {
			/** This filter is documented in wp-includes/post.php */
			$value = apply_filters( "pre_{$field}", $value );
		} else {
			$value = apply_filters( "pre_user_{$field}", $value );
		}
	} else {
		// Use display filters by default.
		if ( $prefixed ) {

			/** This filter is documented in wp-includes/post.php */
			$value = apply_filters( $field, $value, $user_id, $context );
		} else {
			$value = apply_filters( "user_{$field}", $value, $user_id, $context );
		}
	}

	if ( 'user_url' == $field )
		$value = esc_url($value);

	if ( 'attribute' == $context ) {
		$value = esc_attr( $value );
	} elseif ( 'js' == $context ) {
		$value = esc_js( $value );
	}
	return $value;
}

function username_exists( $username ) {
	if ( $user = get_user_by( 'login', $username ) ) {
		return $user->ID;
	}
	return false;
}

function email_exists( $email ) {
	if ( $user = get_user_by( 'email', $email) ) {
		return $user->ID;
	}
	return false;
}

function validate_username( $username ) {
	$sanitized = sanitize_user( $username, true );
	$valid = ( $sanitized == $username && ! empty( $sanitized ) );

	return apply_filters( 'validate_username', $valid, $username );
}

function wp_insert_user( $userdata ) {
	global $wpdb;
	if ( $userdata instanceof stdClass ) {
		$userdata = get_object_vars( $userdata );
	} elseif ( $userdata instanceof WP_User ) {
		$userdata = $userdata->to_array();
	}
	if ( ! empty( $userdata['ID'] ) ) {
		$ID = (int) $userdata['ID'];
		$update = true;
		$old_user_data = get_user_by( 'id', $ID );

		if ( ! $old_user_data ) {
			return new WP_Error( 'invalid_user_id', 'Invalid user ID.' );
		}
		$user_pass = ! empty( $userdata['user_pass'] ) ? $userdata['user_pass'] : $old_user_data->user_pass;
	} else {
		$update = false;
		$user_pass = wp_hash_password( $userdata['user_pass'] );
	}
	$sanitized_user_login = sanitize_user( $userdata['user_login'], true );
	$pre_user_login = apply_filters( 'pre_user_login', $sanitized_user_login );
	$user_login = trim( $pre_user_login );
	if ( empty( $user_login ) ) {
		return new WP_Error('empty_user_login', 'Cannot create a user with an empty login name.' );
	} elseif ( mb_strlen( $user_login ) > 60 ) {
		return new WP_Error( 'user_login_too_long', 'Username may not be longer than 60 characters.' );
	}

	if ( ! $update && username_exists( $user_login ) ) {
		return new WP_Error( 'existing_user_login', 'Sorry, that username already exists!' );
	}
	$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );
	if ( in_array( strtolower( $user_login ), array_map( 'strtolower', $illegal_logins ) ) ) {
		return new WP_Error( 'invalid_username', 'Sorry, that username is not allowed.' );
	}

	if ( ! empty( $userdata['user_nicename'] ) ) {
		$user_nicename = sanitize_user( $userdata['user_nicename'], true );
		if ( mb_strlen( $user_nicename ) > 50 ) {
			return new WP_Error( 'user_nicename_too_long', 'Nicename may not be longer than 50 characters.' );
		}
	} else {
		$user_nicename = mb_substr( $user_login, 0, 50 );
	}

	$user_nicename = sanitize_title( $user_nicename );

	$meta = array();
	$raw_user_url = empty( $userdata['user_url'] ) ? '' : $userdata['user_url'];
	$user_url = apply_filters( 'pre_user_url', $raw_user_url );
	$raw_user_email = empty( $userdata['user_email'] ) ? '' : $userdata['user_email'];
	$user_email = apply_filters( 'pre_user_email', $raw_user_email );

	if ( ( ! $update || ( ! empty( $old_user_data ) && 0 !== strcasecmp( $user_email, $old_user_data->user_email ) ) )
		&& ! defined( 'WP_IMPORTING' )
		&& email_exists( $user_email )
	) {
		return new WP_Error( 'existing_user_email', 'Sorry, that email address is already used!' );
	}
	$nickname = empty( $userdata['nickname'] ) ? $user_login : $userdata['nickname'];
	$meta['nickname'] = apply_filters( 'pre_user_nickname', $nickname );
	$first_name = empty( $userdata['first_name'] ) ? '' : $userdata['first_name'];
	$meta['first_name'] = apply_filters( 'pre_user_first_name', $first_name );
	$last_name = empty( $userdata['last_name'] ) ? '' : $userdata['last_name'];
	$meta['last_name'] = apply_filters( 'pre_user_last_name', $last_name );
	if ( empty( $userdata['display_name'] ) ) {
		if ( $update ) {
			$display_name = $user_login;
		} elseif ( $meta['first_name'] && $meta['last_name'] ) {
			$display_name = sprintf( _x( '%1$s %2$s', 'Display name based on first name and last name' ), $meta['first_name'], $meta['last_name'] );
		} elseif ( $meta['first_name'] ) {
			$display_name = $meta['first_name'];
		} elseif ( $meta['last_name'] ) {
			$display_name = $meta['last_name'];
		} else {
			$display_name = $user_login;
		}
	} else {
		$display_name = $userdata['display_name'];
	}
	$display_name = apply_filters( 'pre_user_display_name', $display_name );
	$description = empty( $userdata['description'] ) ? '' : $userdata['description'];
	$meta['description'] = apply_filters( 'pre_user_description', $description );
	$meta['rich_editing'] = empty( $userdata['rich_editing'] ) ? 'true' : $userdata['rich_editing'];
	$meta['comment_shortcuts'] = empty( $userdata['comment_shortcuts'] ) || 'false' === $userdata['comment_shortcuts'] ? 'false' : 'true';
	$admin_color = empty( $userdata['admin_color'] ) ? 'fresh' : $userdata['admin_color'];
	$meta['admin_color'] = preg_replace( '|[^a-z0-9 _.\-@]|i', '', $admin_color );
	$meta['use_ssl'] = empty( $userdata['use_ssl'] ) ? 0 : $userdata['use_ssl'];
	$user_registered = empty( $userdata['user_registered'] ) ? gmdate( 'Y-m-d H:i:s' ) : $userdata['user_registered'];
	$meta['show_admin_bar_front'] = empty( $userdata['show_admin_bar_front'] ) ? 'true' : $userdata['show_admin_bar_front'];
	$user_nicename_check = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1" , $user_nicename, $user_login));

	if ( $user_nicename_check ) {
		$suffix = 2;
		while ($user_nicename_check) {
			$base_length = 49 - mb_strlen( $suffix );
			$alt_user_nicename = mb_substr( $user_nicename, 0, $base_length ) . "-$suffix";
			$user_nicename_check = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->users WHERE user_nicename = %s AND user_login != %s LIMIT 1" , $alt_user_nicename, $user_login));
			$suffix++;
		}
		$user_nicename = $alt_user_nicename;
	}

	$compacted = compact( 'user_pass', 'user_email', 'user_url', 'user_nicename', 'display_name', 'user_registered' );
	$data = wp_unslash( $compacted );

	if ( $update ) {
		if ( $user_email !== $old_user_data->user_email ) {
			$data['user_activation_key'] = '';
		}
		$wpdb->update( $wpdb->users, $data, compact( 'ID' ) );
		$user_id = (int) $ID;
	} else {
		$wpdb->insert( $wpdb->users, $data + compact( 'user_login' ) );
		$user_id = (int) $wpdb->insert_id;
	}
	$user = new WP_User( $user_id );
	$meta = apply_filters( 'insert_user_meta', $meta, $user, $update );
	foreach ( $meta as $key => $value ) {
		update_user_meta( $user_id, $key, $value );
	}
	foreach ( wp_get_user_contact_methods( $user ) as $key => $value ) {
		if ( isset( $userdata[ $key ] ) ) {
			update_user_meta( $user_id, $key, $userdata[ $key ] );
		}
	}
	if ( isset( $userdata['role'] ) ) {
		$user->set_role( $userdata['role'] );
	} elseif ( ! $update ) {
		$user->set_role(get_option('default_role'));
	}
	wp_cache_delete( $user_id, 'users' );
	wp_cache_delete( $user_login, 'userlogins' );
	if ( $update ) {
		do_action( 'profile_update', $user_id, $old_user_data );
	} else {
		do_action( 'user_register', $user_id );
	}
	return $user_id;
}

function wp_update_user($userdata) {
	if ( $userdata instanceof stdClass ) {
		$userdata = get_object_vars( $userdata );
	} elseif ( $userdata instanceof WP_User ) {
		$userdata = $userdata->to_array();
	}

	$ID = isset( $userdata['ID'] ) ? (int) $userdata['ID'] : 0;
	if ( ! $ID ) {
		return new WP_Error( 'invalid_user_id', 'Invalid user ID.' );
	}

	$user_obj = get_user_by( 'id', $ID );
	if ( ! $user_obj ) {
		return new WP_Error( 'invalid_user_id', 'Invalid user ID.' );
	}

	$user = $user_obj->to_array();

	foreach ( _get_additional_user_keys( $user_obj ) as $key ) {
		$user[ $key ] = get_user_meta( $ID, $key, true );
	}

	$user = add_magic_quotes( $user );

	if ( ! empty( $userdata['user_pass'] ) && $userdata['user_pass'] !== $user_obj->user_pass ) {
		$plaintext_pass = $userdata['user_pass'];
		$userdata['user_pass'] = wp_hash_password( $userdata['user_pass'] );

		$send_password_change_email = apply_filters( 'send_password_change_email', true, $user, $userdata );
	}

	if ( isset( $userdata['user_email'] ) && $user['user_email'] !== $userdata['user_email'] ) {
		$send_email_change_email = apply_filters( 'send_email_change_email', true, $user, $userdata );
	}

	wp_cache_delete( $user['user_email'], 'useremail' );
	wp_cache_delete( $user['user_nicename'], 'userslugs' );

	$userdata = array_merge( $user, $userdata );
	$user_id = wp_insert_user( $userdata );

	if ( ! is_wp_error( $user_id ) ) {

		$blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		if ( ! empty( $send_password_change_email ) ) {

			$pass_change_text = __( 'Hi ###USERNAME###,

This notice confirms that your password was changed on ###SITENAME###.

If you did not change your password, please contact the Site Administrator at
###ADMIN_EMAIL###

This email has been sent to ###EMAIL###

Regards,
All at ###SITENAME###
###SITEURL###' );

			$pass_change_email = array(
				'to'      => $user['user_email'],
				'subject' => '[%s] Notice of Password Change',
				'message' => $pass_change_text,
				'headers' => '',
			);

			$pass_change_email = apply_filters( 'password_change_email', $pass_change_email, $user, $userdata );

			$pass_change_email['message'] = str_replace( '###USERNAME###', $user['user_login'], $pass_change_email['message'] );
			$pass_change_email['message'] = str_replace( '###ADMIN_EMAIL###', get_option( 'admin_email' ), $pass_change_email['message'] );
			$pass_change_email['message'] = str_replace( '###EMAIL###', $user['user_email'], $pass_change_email['message'] );
			$pass_change_email['message'] = str_replace( '###SITENAME###', $blog_name, $pass_change_email['message'] );
			$pass_change_email['message'] = str_replace( '###SITEURL###', home_url(), $pass_change_email['message'] );

			wp_mail( $pass_change_email['to'], sprintf( $pass_change_email['subject'], $blog_name ), $pass_change_email['message'], $pass_change_email['headers'] );
		}

		if ( ! empty( $send_email_change_email ) ) {
			/* translators: Do not translate USERNAME, ADMIN_EMAIL, EMAIL, SITENAME, SITEURL: those are placeholders. */
			$email_change_text = __( 'Hi ###USERNAME###,

This notice confirms that your email was changed on ###SITENAME###.

If you did not change your email, please contact the Site Administrator at
###ADMIN_EMAIL###

This email has been sent to ###EMAIL###

Regards,
All at ###SITENAME###
###SITEURL###' );

			$email_change_email = array(
				'to'      => $user['user_email'],
				'subject' => '[%s] Notice of Email Change',
				'message' => $email_change_text,
				'headers' => '',
			);

			$email_change_email = apply_filters( 'email_change_email', $email_change_email, $user, $userdata );

			$email_change_email['message'] = str_replace( '###USERNAME###', $user['user_login'], $email_change_email['message'] );
			$email_change_email['message'] = str_replace( '###ADMIN_EMAIL###', get_option( 'admin_email' ), $email_change_email['message'] );
			$email_change_email['message'] = str_replace( '###EMAIL###', $user['user_email'], $email_change_email['message'] );
			$email_change_email['message'] = str_replace( '###SITENAME###', $blog_name, $email_change_email['message'] );
			$email_change_email['message'] = str_replace( '###SITEURL###', home_url(), $email_change_email['message'] );

			wp_mail( $email_change_email['to'], sprintf( $email_change_email['subject'], $blog_name ), $email_change_email['message'], $email_change_email['headers'] );
		}
	}

	$current_user = wp_get_current_user();
	if ( $current_user->ID == $ID ) {
		if ( isset($plaintext_pass) ) {
			wp_clear_auth_cookie();

			// Here we calculate the expiration length of the current auth cookie and compare it to the default expiration.
			// If it's greater than this, then we know the user checked 'Remember Me' when they logged in.
			$logged_in_cookie    = wp_parse_auth_cookie( '', 'logged_in' );
			/** This filter is documented in wp-includes/pluggable.php */
			$default_cookie_life = apply_filters( 'auth_cookie_expiration', ( 2 * DAY_IN_SECONDS ), $ID, false );
			$remember            = ( ( $logged_in_cookie['expiration'] - time() ) > $default_cookie_life );

			wp_set_auth_cookie( $ID, $remember );
		}
	}

	return $user_id;
}

function wp_create_user($username, $password, $email = '') {
	$user_login = wp_slash( $username );
	$user_email = wp_slash( $email );
	$user_pass = $password;
	$userdata = compact('user_login', 'user_email', 'user_pass');
	return wp_insert_user($userdata);
}

function _get_additional_user_keys( $user ) {
	$keys = array( 'first_name', 'last_name', 'nickname', 'description', 'rich_editing', 'comment_shortcuts', 'admin_color', 'use_ssl', 'show_admin_bar_front' );
	return array_merge( $keys, array_keys( wp_get_user_contact_methods( $user ) ) );
}

function wp_get_user_contact_methods( $user = null ) {
	$methods = array();
	if ( get_site_option( 'initial_db_version' ) < 23588 ) {
		$methods = array(
			'aim'    => 'AIM',
			'yim'    => 'Yahoo IM',
			'jabber' => 'Jabber / Google Talk'
		);
	}

	return apply_filters( 'user_contactmethods', $methods, $user );
}

function _wp_get_user_contactmethods( $user = null ) {
	return wp_get_user_contact_methods( $user );
}

function wp_get_password_hint() {
	$hint = __( 'Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).' );

	return apply_filters( 'password_hint', $hint );
}

function get_password_reset_key( $user ) {
	global $wpdb, $wp_hasher;

	do_action( 'retreive_password', $user->user_login );

	do_action( 'retrieve_password', $user->user_login );

	$allow = apply_filters( 'allow_password_reset', true, $user->ID );

	if ( ! $allow ) {
		return new WP_Error( 'no_password_reset', 'Password reset is not allowed for this user' );
	} elseif ( is_wp_error( $allow ) ) {
		return $allow;
	}
	$key = '123456';
	do_action( 'retrieve_password_key', $user->user_login, $key );
	if ( empty( $wp_hasher ) ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true );
	}
	$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
	$key_saved = $wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user->user_login ) );
	if ( false === $key_saved ) {
		return new WP_Error( 'no_password_key_update', 'Could not save password reset key to database.' );
	}

	return $key;
}

function check_password_reset_key($key, $login) {
	global $wpdb, $wp_hasher;

	$key = preg_replace('/[^a-z0-9]/i', '', $key);

	if ( empty( $key ) || !is_string( $key ) )
		return new WP_Error('invalid_key', 'Invalid key');

	if ( empty($login) || !is_string($login) )
		return new WP_Error('invalid_key', 'Invalid key');

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID, user_activation_key FROM $wpdb->users WHERE user_login = %s", $login ) );
	if ( ! $row )
		return new WP_Error('invalid_key', 'Invalid key');

	if ( empty( $wp_hasher ) ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true );
	}

	$expiration_duration = apply_filters( 'password_reset_expiration', DAY_IN_SECONDS );

	if ( false !== strpos( $row->user_activation_key, ':' ) ) {
		list( $pass_request_time, $pass_key ) = explode( ':', $row->user_activation_key, 2 );
		$expiration_time = $pass_request_time + $expiration_duration;
	} else {
		$pass_key = $row->user_activation_key;
		$expiration_time = false;
	}

	if ( ! $pass_key ) {
		return new WP_Error( 'invalid_key', 'Invalid key' );
	}

	$hash_is_correct = $wp_hasher->CheckPassword( $key, $pass_key );

	if ( $hash_is_correct && $expiration_time && time() < $expiration_time ) {
		return get_user_by( 'id', $row->ID );
	} elseif ( $hash_is_correct && $expiration_time ) {
		return new WP_Error( 'expired_key', 'Invalid key' );
	}

	if ( hash_equals( $row->user_activation_key, $key ) || ( $hash_is_correct && ! $expiration_time ) ) {
		$return = new WP_Error( 'expired_key', 'Invalid key' );
		$user_id = $row->ID;

		return apply_filters( 'password_reset_key_expired', $return, $user_id );
	}

	return new WP_Error( 'invalid_key', 'Invalid key' );
}

function reset_password( $user, $new_pass ) {

	do_action( 'password_reset', $user, $new_pass );

	wp_set_password( $new_pass, $user->ID );
	update_user_option( $user->ID, 'default_password_nag', false, true );

	do_action( 'after_password_reset', $user, $new_pass );
}

function register_new_user( $user_login, $user_email ) {
	$errors = new WP_Error();
	$sanitized_user_login = sanitize_user( $user_login );
	$user_email = apply_filters( 'user_registration_email', $user_email );
	if ( $sanitized_user_login == '' ) {
		$errors->add( 'empty_username', '<strong>ERROR</strong>: Please enter a username.' );
	} elseif ( ! validate_username( $user_login ) ) {
		$errors->add( 'invalid_username', '<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' );
		$sanitized_user_login = '';
	} elseif ( username_exists( $sanitized_user_login ) ) {
		$errors->add( 'username_exists', '<strong>ERROR</strong>: This username is already registered. Please choose another one.' );

	} else {
		$illegal_user_logins = array_map( 'strtolower', (array) apply_filters( 'illegal_user_logins', array() ) );
		if ( in_array( strtolower( $sanitized_user_login ), $illegal_user_logins ) ) {
			$errors->add( 'invalid_username', '<strong>ERROR</strong>: Sorry, that username is not allowed.' );
		}
	}
	if ( $user_email == '' ) {
		$errors->add( 'empty_email', '<strong>ERROR</strong>: Please type your email address.' );
	} elseif ( ! is_email( $user_email ) ) {
		$errors->add( 'invalid_email', '<strong>ERROR</strong>: The email address isn&#8217;t correct.' );
		$user_email = '';
	} elseif ( email_exists( $user_email ) ) {
		$errors->add( 'email_exists', '<strong>ERROR</strong>: This email is already registered, please choose another one.' );
	}
	do_action( 'register_post', $sanitized_user_login, $user_email, $errors );
	$errors = apply_filters( 'registration_errors', $errors, $sanitized_user_login, $user_email );

	if ( $errors->get_error_code() )
		return $errors;
	$user_pass = '123456';
	$user_id = wp_create_user( $sanitized_user_login, $user_pass, $user_email );
	if ( ! $user_id || is_wp_error( $user_id ) ) {
		$errors->add( 'registerfail', sprintf( '<strong>ERROR</strong>: Couldn&#8217;t register you&hellip; please contact the <a href="mailto:%s">webmaster</a> !', get_option( 'admin_email' ) ) );
		return $errors;
	}
	update_user_option( $user_id, 'default_password_nag', true, true );
	return $user_id;
}

function wp_get_session_token() {
	$cookie = wp_parse_auth_cookie( '', 'logged_in' );
	return ! empty( $cookie['token'] ) ? $cookie['token'] : '';
}

function wp_get_all_sessions() {
	$manager = WP_Session_Tokens::get_instance( get_current_user_id() );
	return $manager->get_all();
}

function wp_destroy_current_session() {
	$token = wp_get_session_token();
	if ( $token ) {
		$manager = WP_Session_Tokens::get_instance( get_current_user_id() );
		$manager->destroy( $token );
	}
}

function wp_destroy_other_sessions() {
	$token = wp_get_session_token();
	if ( $token ) {
		$manager = WP_Session_Tokens::get_instance( get_current_user_id() );
		$manager->destroy_others( $token );
	}
}

function wp_destroy_all_sessions() {
	$manager = WP_Session_Tokens::get_instance( get_current_user_id() );
	$manager->destroy_all();
}

function wp_get_users_with_no_role() {
	global $wpdb;

	$regex  = implode( '|', wp_roles()->get_names() );
	$regex  = preg_replace( '/[^a-zA-Z_\|-]/', '', $regex );
	$users  = $wpdb->get_col( $wpdb->prepare( "
		SELECT user_id
		FROM $wpdb->usermeta
		WHERE meta_key = 'capabilities'
		AND meta_value NOT REGEXP %s
	", $regex ) );

	return $users;
}

function _wp_get_current_user() {
	global $current_user;
	if ( ! empty( $current_user ) ) {
		if ( $current_user instanceof WP_User ) { return $current_user; }
		if ( is_object( $current_user ) && isset( $current_user->ID ) ) {
			$cur_id = $current_user->ID;
			$current_user = null;
			wp_set_current_user( $cur_id );
			return $current_user;
		}
		$current_user = null;
		wp_set_current_user( 0 );
		return $current_user;
	}
	$user_id = apply_filters( 'determine_current_user', false );
	if ( ! $user_id ) {
		wp_set_current_user( 0 );
		return $current_user;
	}
	wp_set_current_user( $user_id );
	return $current_user;
}
