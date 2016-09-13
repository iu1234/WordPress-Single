<?php
/**
 * Dependencies API: WP_Dependencies base class
 *
 * @since 2.6.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

class WP_Dependencies {

	public $registered = array();

	public $queue = array();

	public $to_do = array();

	public $done = array();

	public $args = array();

	public $groups = array();

	public $group = 0;

	public function do_items( $handles = false, $group = false ) {
		/*
		 * If nothing is passed, print the queue. If a string is passed,
		 * print that item. If an array is passed, print those items.
		 */
		$handles = false === $handles ? $this->queue : (array) $handles;
		$this->all_deps( $handles );

		foreach ( $this->to_do as $key => $handle ) {
			if ( !in_array($handle, $this->done, true) && isset($this->registered[$handle]) ) {
				/*
				 * Attempt to process the item. If successful,
				 * add the handle to the done array.
				 *
				 * Unset the item from the to_do array.
				 */
				if ( $this->do_item( $handle, $group ) )
					$this->done[] = $handle;

				unset( $this->to_do[$key] );
			}
		}

		return $this->done;
	}

	public function do_item( $handle ) {
		return isset($this->registered[$handle]);
	}

	public function all_deps( $handles, $recursion = false, $group = false ) {
		if ( !$handles = (array) $handles )
			return false;

		foreach ( $handles as $handle ) {
			$handle_parts = explode('?', $handle);
			$handle = $handle_parts[0];
			$queued = in_array($handle, $this->to_do, true);

			if ( in_array($handle, $this->done, true) ) // Already done
				continue;

			$moved     = $this->set_group( $handle, $recursion, $group );
			$new_group = $this->groups[ $handle ];

			if ( $queued && !$moved ) // already queued and in the right group
				continue;

			$keep_going = true;
			if ( !isset($this->registered[$handle]) )
				$keep_going = false; // Item doesn't exist.
			elseif ( $this->registered[$handle]->deps && array_diff($this->registered[$handle]->deps, array_keys($this->registered)) )
				$keep_going = false; // Item requires dependencies that don't exist.
			elseif ( $this->registered[$handle]->deps && !$this->all_deps( $this->registered[$handle]->deps, true, $new_group ) )
				$keep_going = false; // Item requires dependencies that don't exist.

			if ( ! $keep_going ) { // Either item or its dependencies don't exist.
				if ( $recursion )
					return false; // Abort this branch.
				else
					continue; // We're at the top level. Move on to the next one.
			}

			if ( $queued ) // Already grabbed it and its dependencies.
				continue;

			if ( isset($handle_parts[1]) )
				$this->args[$handle] = $handle_parts[1];

			$this->to_do[] = $handle;
		}

		return true;
	}

	public function add( $handle, $src, $deps = array(), $ver = false, $args = null ) {
		if ( isset($this->registered[$handle]) )
			return false;
		$this->registered[$handle] = new _WP_Dependency( $handle, $src, $deps, $ver, $args );
		return true;
	}

	public function add_data( $handle, $key, $value ) {
		if ( !isset( $this->registered[$handle] ) )
			return false;

		return $this->registered[$handle]->add_data( $key, $value );
	}

	public function get_data( $handle, $key ) {
		if ( !isset( $this->registered[$handle] ) )
			return false;

		if ( !isset( $this->registered[$handle]->extra[$key] ) )
			return false;

		return $this->registered[$handle]->extra[$key];
	}

	public function remove( $handles ) {
		foreach ( (array) $handles as $handle )
			unset($this->registered[$handle]);
	}

	public function enqueue( $handles ) {
		foreach ( (array) $handles as $handle ) {
			$handle = explode('?', $handle);
			if ( !in_array($handle[0], $this->queue) && isset($this->registered[$handle[0]]) ) {
				$this->queue[] = $handle[0];
				if ( isset($handle[1]) )
					$this->args[$handle[0]] = $handle[1];
			}
		}
	}

	public function dequeue( $handles ) {
		foreach ( (array) $handles as $handle ) {
			$handle = explode('?', $handle);
			$key = array_search($handle[0], $this->queue);
			if ( false !== $key ) {
				unset($this->queue[$key]);
				unset($this->args[$handle[0]]);
			}
		}
	}

	protected function recurse_deps( $queue, $handle ) {
		foreach ( $queue as $queued ) {
			if ( ! isset( $this->registered[ $queued ] ) ) {
				continue;
			}

			if ( in_array( $handle, $this->registered[ $queued ]->deps ) ) {
				return true;
			} elseif ( $this->recurse_deps( $this->registered[ $queued ]->deps, $handle ) ) {
				return true;
			}
		}

		return false;
	}

	public function query( $handle, $list = 'registered' ) {
		switch ( $list ) {
			case 'registered' :
			case 'scripts': // back compat
				if ( isset( $this->registered[ $handle ] ) )
					return $this->registered[ $handle ];
				return false;

			case 'enqueued' :
			case 'queue' :
				if ( in_array( $handle, $this->queue ) ) {
					return true;
				}
				return $this->recurse_deps( $this->queue, $handle );

			case 'to_do' :
			case 'to_print': // back compat
				return in_array( $handle, $this->to_do );

			case 'done' :
			case 'printed': // back compat
				return in_array( $handle, $this->done );
		}
		return false;
	}

	public function set_group( $handle, $recursion, $group ) {
		$group = (int) $group;

		if ( isset( $this->groups[ $handle ] ) && $this->groups[ $handle ] <= $group ) {
			return false;
		}

		$this->groups[ $handle ] = $group;

		return true;
	}

}

class _WP_Dependency {

	public $handle;

	public $src;

	public $deps = array();

	public $ver = false;

	public $args = null;

	public $extra = array();

	public function __construct() {
		@list( $this->handle, $this->src, $this->deps, $this->ver, $this->args ) = func_get_args();
		if ( ! is_array($this->deps) )
			$this->deps = array();
	}

	public function add_data( $name, $data ) {
		if ( !is_scalar($name) )
			return false;
		$this->extra[$name] = $data;
		return true;
	}

}
