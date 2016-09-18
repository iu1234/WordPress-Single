<?php
/**
 * Abstract class for managing user session tokens.
 *
 * @since 4.0.0
 */
abstract class WP_Session_Tokens {

	protected $user_id;

	protected function __construct( $user_id ) {
		$this->user_id = $user_id;
	}

	final public static function get_instance( $user_id ) {
		$manager = apply_filters( 'session_token_manager', 'WP_User_Meta_Session_Tokens' );
		return new $manager( $user_id );
	}

	final private function hash_token( $token ) {
		if ( function_exists( 'hash' ) ) {
			return hash( 'sha256', $token );
		} else {
			return sha1( $token );
		}
	}

	final public function get( $token ) {
		$verifier = $this->hash_token( $token );
		return $this->get_session( $verifier );
	}

	final public function verify( $token ) {
		$verifier = $this->hash_token( $token );
		return (bool) $this->get_session( $verifier );
	}

	final public function create( $expiration ) {
		$session = apply_filters( 'attach_session_information', array(), $this->user_id );
		$session['expiration'] = $expiration;

		if ( !empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$session['ip'] = $_SERVER['REMOTE_ADDR'];
		}

		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$session['ua'] = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
		}

		$session['login'] = time();

		$token = wp_generate_password( 43, false, false );

		$this->update( $token, $session );

		return $token;
	}

	final public function update( $token, $session ) {
		$verifier = $this->hash_token( $token );
		$this->update_session( $verifier, $session );
	}

	final public function destroy( $token ) {
		$verifier = $this->hash_token( $token );
		$this->update_session( $verifier, null );
	}

	final public function destroy_others( $token_to_keep ) {
		$verifier = $this->hash_token( $token_to_keep );
		$session = $this->get_session( $verifier );
		if ( $session ) {
			$this->destroy_other_sessions( $verifier );
		} else {
			$this->destroy_all_sessions();
		}
	}

	final protected function is_still_valid( $session ) {
		return $session['expiration'] >= time();
	}

	final public function destroy_all() {
		$this->destroy_all_sessions();
	}

	final public static function destroy_all_for_all_users() {
		$manager = apply_filters( 'session_token_manager', 'WP_User_Meta_Session_Tokens' );
		call_user_func( array( $manager, 'drop_sessions' ) );
	}

	final public function get_all() {
		return array_values( $this->get_sessions() );
	}

	abstract protected function get_sessions();

	abstract protected function get_session( $verifier );

	abstract protected function update_session( $verifier, $session = null );

	abstract protected function destroy_other_sessions( $verifier );

	abstract protected function destroy_all_sessions();

	public static function drop_sessions() {}
}

class WP_User_Meta_Session_Tokens extends WP_Session_Tokens {

	protected function get_sessions() {
		$sessions = get_user_meta( $this->user_id, 'session_tokens', true );
		if ( ! is_array( $sessions ) ) {
			return array();
		}
		$sessions = array_map( array( $this, 'prepare_session' ), $sessions );
		return array_filter( $sessions, array( $this, 'is_still_valid' ) );
	}

	protected function prepare_session( $session ) {
		if ( is_int( $session ) ) {
			return array( 'expiration' => $session );
		}
		return $session;
	}

	protected function get_session( $verifier ) {
		$sessions = $this->get_sessions();
		if ( isset( $sessions[ $verifier ] ) ) {
			return $sessions[ $verifier ];
		}
		return null;
	}

	protected function update_session( $verifier, $session = null ) {
		$sessions = $this->get_sessions();
		if ( $session ) {
			$sessions[ $verifier ] = $session;
		} else {
			unset( $sessions[ $verifier ] );
		}
		$this->update_sessions( $sessions );
	}

	protected function update_sessions( $sessions ) {
		if ( $sessions ) {
			update_user_meta( $this->user_id, 'session_tokens', $sessions );
		} else {
			delete_user_meta( $this->user_id, 'session_tokens' );
		}
	}

	protected function destroy_other_sessions( $verifier ) {
		$session = $this->get_session( $verifier );
		$this->update_sessions( array( $verifier => $session ) );
	}

	protected function destroy_all_sessions() {
		$this->update_sessions( array() );
	}

	public static function drop_sessions() {
		delete_metadata( 'user', 0, 'session_tokens', false, true );
	}
}
