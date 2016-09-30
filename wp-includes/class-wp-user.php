<?php

class WP_User {

	public $data;

	public $ID = 0;

	public $caps = array();

	public $cap_key;

	public $roles = array();

	public $allcaps = array();

	var $filter = null;

	private static $back_compat_keys;

	public function __construct( $id = 0, $name = '', $blog_id = '' ) {
		if ( ! isset( self::$back_compat_keys ) ) {
			$prefix = $GLOBALS['wpdb']->prefix;
			self::$back_compat_keys = array(
				'user_firstname' => 'first_name',
				'user_lastname' => 'last_name',
				'user_description' => 'description',
				'user_level' => $prefix . 'user_level',
				$prefix . 'usersettings' => $prefix . 'user-settings',
				$prefix . 'usersettingstime' => $prefix . 'user-settings-time',
			);
		}

		if ( $id instanceof WP_User ) {
			$this->init( $id->data, $blog_id );
			return;
		} elseif ( is_object( $id ) ) {
			$this->init( $id, $blog_id );
			return;
		}

		if ( ! empty( $id ) && ! is_numeric( $id ) ) {
			$name = $id;
			$id = 0;
		}

		if ( $id ) {
			$data = self::get_data_by( 'id', $id );
		} else {
			$data = self::get_data_by( 'login', $name );
		}

		if ( $data ) {
			$this->init( $data, $blog_id );
		} else {
			$this->data = new stdClass;
		}
	}

	public function init( $data, $blog_id = '' ) {
		$this->data = $data;
		$this->ID = (int) $data->ID;

		$this->for_blog( $blog_id );
	}

	public static function get_data_by( $field, $value ) {
		global $wpdb;

		// 'ID' is an alias of 'id'.
		if ( 'ID' === $field ) {
			$field = 'id';
		}

		if ( 'id' == $field ) {
			// Make sure the value is numeric to avoid casting objects, for example,
			// to int 1.
			if ( ! is_numeric( $value ) )
				return false;
			$value = intval( $value );
			if ( $value < 1 )
				return false;
		} else {
			$value = trim( $value );
		}

		if ( !$value )
			return false;

		switch ( $field ) {
			case 'id':
				$user_id = $value;
				$db_field = 'ID';
				break;
			case 'slug':
				$user_id = wp_cache_get($value, 'userslugs');
				$db_field = 'user_nicename';
				break;
			case 'email':
				$user_id = wp_cache_get($value, 'useremail');
				$db_field = 'user_email';
				break;
			case 'login':
				$value = sanitize_user( $value );
				$user_id = wp_cache_get($value, 'userlogins');
				$db_field = 'user_login';
				break;
			default:
				return false;
		}

		if ( false !== $user_id ) {
			if ( $user = wp_cache_get( $user_id, 'users' ) )
				return $user;
		}

		if ( !$user = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $wpdb->users WHERE $db_field = %s", $value
		) ) )
			return false;

		update_user_caches( $user );

		return $user;
	}

	public function __call( $name, $arguments ) {
		if ( '_init_caps' === $name ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		}
		return false;
	}

	public function __isset( $key ) {
		if ( isset( $this->data->$key ) )
			return true;

		if ( isset( self::$back_compat_keys[ $key ] ) )
			$key = self::$back_compat_keys[ $key ];

		return metadata_exists( 'user', $this->ID, $key );
	}

	public function __get( $key ) {

		if ( isset( $this->data->$key ) ) {
			$value = $this->data->$key;
		} else {
			if ( isset( self::$back_compat_keys[ $key ] ) )
				$key = self::$back_compat_keys[ $key ];
			$value = get_user_meta( $this->ID, $key, true );
		}

		if ( $this->filter ) {
			$value = sanitize_user_field( $key, $value, $this->ID, $this->filter );
		}

		return $value;
	}

	public function __set( $key, $value ) {
			$this->ID = $value;
			return;
		}

		$this->data->$key = $value;
	}

	public function __unset( $key ) {
		if ( isset( $this->data->$key ) ) {
			unset( $this->data->$key );
		}

		if ( isset( self::$back_compat_keys[ $key ] ) ) {
			unset( self::$back_compat_keys[ $key ] );
		}
	}

	public function exists() {
		return ! empty( $this->ID );
	}

	public function get( $key ) {
		return $this->__get( $key );
	}

	public function has_prop( $key ) {
		return $this->__isset( $key );
	}

	public function to_array() {
		return get_object_vars( $this->data );
	}

	protected function _init_caps( $cap_key = '' ) {
		global $wpdb;

		if ( empty($cap_key) )
			$this->cap_key = 'capabilities';
		else
			$this->cap_key = $cap_key;

		$this->caps = get_user_meta( $this->ID, $this->cap_key, true );

		if ( ! is_array( $this->caps ) )
			$this->caps = array();

		$this->get_role_caps();
	}

	public function get_role_caps() {
		$wp_roles = wp_roles();
		if ( is_array( $this->caps ) )
			$this->roles = array_filter( array_keys( $this->caps ), array( $wp_roles, 'is_role' ) );
		$this->allcaps = array();
		foreach ( (array) $this->roles as $role ) {
			$the_role = $wp_roles->get_role( $role );
			$this->allcaps = array_merge( (array) $this->allcaps, (array) $the_role->capabilities );
		}
		$this->allcaps = array_merge( (array) $this->allcaps, (array) $this->caps );
		return $this->allcaps;
	}

	public function add_role( $role ) {
		if ( empty( $role ) ) {
			return;
		}

		$this->caps[$role] = true;
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();

		do_action( 'add_user_role', $this->ID, $role );
	}

	public function remove_role( $role ) {
		if ( !in_array($role, $this->roles) )
			return;
		unset( $this->caps[$role] );
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();

		do_action( 'remove_user_role', $this->ID, $role );
	}

	public function set_role( $role ) {
		if ( 1 == count( $this->roles ) && $role == current( $this->roles ) )
			return;

		foreach ( (array) $this->roles as $oldrole )
			unset( $this->caps[$oldrole] );

		$old_roles = $this->roles;
		if ( !empty( $role ) ) {
			$this->caps[$role] = true;
			$this->roles = array( $role => true );
		} else {
			$this->roles = false;
		}
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();

		do_action( 'set_user_role', $this->ID, $role, $old_roles );
	}

	public function level_reduction( $max, $item ) {
		if ( preg_match( '/^level_(10|[0-9])$/i', $item, $matches ) ) {
			$level = intval( $matches[1] );
			return max( $max, $level );
		} else {
			return $max;
		}
	}

	public function update_user_level_from_caps() {
		global $wpdb;
		$this->user_level = array_reduce( array_keys( $this->allcaps ), array( $this, 'level_reduction' ), 0 );
		update_user_meta( $this->ID, 'user_level', $this->user_level );
	}

	public function add_cap( $cap, $grant = true ) {
		$this->caps[$cap] = $grant;
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();
	}

	public function remove_cap( $cap ) {
		if ( ! isset( $this->caps[ $cap ] ) ) {
			return;
		}
		unset( $this->caps[ $cap ] );
		update_user_meta( $this->ID, $this->cap_key, $this->caps );
		$this->get_role_caps();
		$this->update_user_level_from_caps();
	}

	public function remove_all_caps() {
		global $wpdb;
		$this->caps = array();
		delete_user_meta( $this->ID, $this->cap_key );
		delete_user_meta( $this->ID, 'user_level' );
		$this->get_role_caps();
	}

	public function has_cap( $cap ) {
		$args = array_slice( func_get_args(), 1 );
		$args = array_merge( array( $cap, $this->ID ), $args );
		$caps = call_user_func_array( 'map_meta_cap', $args );

		$capabilities = apply_filters( 'user_has_cap', $this->allcaps, $caps, $args, $this );

		$capabilities['exist'] = true;

		foreach ( (array) $caps as $cap ) {
			if ( empty( $capabilities[ $cap ] ) )
				return false;
		}

		return true;
	}

	public function translate_level_to_cap( $level ) {
		return 'level_' . $level;
	}

	public function for_blog( $blog_id = '' ) {
		global $wpdb;
		if ( ! empty( $blog_id ) )
			$cap_key = 'capabilities';
		else
			$cap_key = '';
		$this->_init_caps( $cap_key );
	}
}
