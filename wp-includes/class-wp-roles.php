<?php
/**
 * User API: WP_Roles class
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.4.0
 */

class WP_Roles {

	public $roles;

	public $role_objects = array();

	public $role_names = array();

	public $role_key;

	public $use_db = true;

	public function __construct() {
		$this->_init();
	}

	public function __call( $name, $arguments ) {
		if ( '_init' === $name ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		}
		return false;
	}

	protected function _init() {
		global $wpdb, $wp_user_roles;
		$this->role_key = $wpdb->get_blog_prefix() . 'user_roles';
		if ( ! empty( $wp_user_roles ) ) {
			$this->roles = $wp_user_roles;
			$this->use_db = false;
		} else {
			$this->roles = get_option( $this->role_key );
		}

		if ( empty( $this->roles ) )
			return;

		$this->role_objects = array();
		$this->role_names =  array();
		foreach ( array_keys( $this->roles ) as $role ) {
			$this->role_objects[$role] = new WP_Role( $role, $this->roles[$role]['capabilities'] );
			$this->role_names[$role] = $this->roles[$role]['name'];
		}
	}

	public function reinit() {
		if ( ! $this->use_db )
			return;

		global $wpdb;

		$this->role_key = $wpdb->get_blog_prefix() . 'user_roles';
		$this->roles = get_option( $this->role_key );
		if ( empty( $this->roles ) )
			return;

		$this->role_objects = array();
		$this->role_names =  array();
		foreach ( array_keys( $this->roles ) as $role ) {
			$this->role_objects[$role] = new WP_Role( $role, $this->roles[$role]['capabilities'] );
			$this->role_names[$role] = $this->roles[$role]['name'];
		}
	}

	public function add_role( $role, $display_name, $capabilities = array() ) {
		if ( empty( $role ) || isset( $this->roles[ $role ] ) ) {
			return;
		}

		$this->roles[$role] = array(
			'name' => $display_name,
			'capabilities' => $capabilities
			);
		if ( $this->use_db )
			update_option( $this->role_key, $this->roles );
		$this->role_objects[$role] = new WP_Role( $role, $capabilities );
		$this->role_names[$role] = $display_name;
		return $this->role_objects[$role];
	}

	public function remove_role( $role ) {
		if ( ! isset( $this->role_objects[$role] ) )
			return;

		unset( $this->role_objects[$role] );
		unset( $this->role_names[$role] );
		unset( $this->roles[$role] );

		if ( $this->use_db )
			update_option( $this->role_key, $this->roles );

		if ( get_option( 'default_role' ) == $role )
			update_option( 'default_role', 'subscriber' );
	}

	public function add_cap( $role, $cap, $grant = true ) {
		if ( ! isset( $this->roles[$role] ) )
			return;

		$this->roles[$role]['capabilities'][$cap] = $grant;
		if ( $this->use_db )
			update_option( $this->role_key, $this->roles );
	}

	public function remove_cap( $role, $cap ) {
		if ( ! isset( $this->roles[$role] ) )
			return;

		unset( $this->roles[$role]['capabilities'][$cap] );
		if ( $this->use_db )
			update_option( $this->role_key, $this->roles );
	}

	public function get_role( $role ) {
		if ( isset( $this->role_objects[$role] ) )
			return $this->role_objects[$role];
		else
			return null;
	}

	public function get_names() {
		return $this->role_names;
	}

	public function is_role( $role ) {
		return isset( $this->role_names[$role] );
	}
}
