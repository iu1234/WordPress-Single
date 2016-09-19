<?php
/**
 * The plugin API is located in this file, which allows for creating actions
 * and filters and hooking functions, and methods. The functions or methods will
 * then be run when the action or filter is called.
 *
 * The API callback examples reference functions, but can be methods of classes.
 * To hook methods, you'll need to pass an array one of two ways.
 *
 * Any of the syntaxes explained in the PHP documentation for the
 * {@link http://us2.php.net/manual/en/language.pseudo-types.php#language.types.callback 'callback'}
 * type are valid.
 *
 * Also see the {@link https://codex.wordpress.org/Plugin_API Plugin API} for
 * more information and examples on how to use a lot of these functions.
 *
 * @package WordPress
 * @subpackage Plugin
 * @since 1.5.0
 */

// Initialize the filter globals.
global $wp_filter, $wp_actions, $merged_filters, $wp_current_filter;

if ( ! isset( $wp_filter ) )
	$wp_filter = array();

if ( ! isset( $wp_actions ) )
	$wp_actions = array();

if ( ! isset( $merged_filters ) )
	$merged_filters = array();

if ( ! isset( $wp_current_filter ) )
	$wp_current_filter = array();

function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
	global $wp_filter, $merged_filters;

	$idx = _wp_filter_build_unique_id($tag, $function_to_add, $priority);
	$wp_filter[$tag][$priority][$idx] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
	unset( $merged_filters[ $tag ] );
	return true;
}

function has_filter($tag, $function_to_check = false) {
	// Don't reset the internal array pointer
	$wp_filter = $GLOBALS['wp_filter'];

	$has = ! empty( $wp_filter[ $tag ] );

	// Make sure at least one priority has a filter callback
	if ( $has ) {
		$exists = false;
		foreach ( $wp_filter[ $tag ] as $callbacks ) {
			if ( ! empty( $callbacks ) ) {
				$exists = true;
				break;
			}
		}

		if ( ! $exists ) {
			$has = false;
		}
	}

	if ( false === $function_to_check || false === $has )
		return $has;

	if ( !$idx = _wp_filter_build_unique_id($tag, $function_to_check, false) )
		return false;

	foreach ( (array) array_keys($wp_filter[$tag]) as $priority ) {
		if ( isset($wp_filter[$tag][$priority][$idx]) )
			return $priority;
	}

	return false;
}

function apply_filters( $tag, $value ) {
	global $wp_filter, $merged_filters, $wp_current_filter;
	$args = array();
	if ( isset($wp_filter['all']) ) {
		$wp_current_filter[] = $tag;
		$args = func_get_args();
		_wp_call_all_hook($args);
	}
	if ( !isset($wp_filter[$tag]) ) {
		if ( isset($wp_filter['all']) )
			array_pop($wp_current_filter);
		return $value;
	}
	if ( !isset($wp_filter['all']) )
		$wp_current_filter[] = $tag;
	if ( !isset( $merged_filters[ $tag ] ) ) {
		ksort($wp_filter[$tag]);
		$merged_filters[ $tag ] = true;
	}
	reset( $wp_filter[ $tag ] );
	if ( empty($args) )
		$args = func_get_args();
	do {
		foreach ( (array) current($wp_filter[$tag]) as $the_ )
			if ( !is_null($the_['function']) ){
				$args[1] = $value;
				$value = call_user_func_array($the_['function'], array_slice($args, 1, (int) $the_['accepted_args']));
			}
	} while ( next($wp_filter[$tag]) !== false );
	array_pop( $wp_current_filter );
	return $value;
}

function apply_filters_ref_array($tag, $args) {
	global $wp_filter, $merged_filters, $wp_current_filter;

	// Do 'all' actions first
	if ( isset($wp_filter['all']) ) {
		$wp_current_filter[] = $tag;
		$all_args = func_get_args();
		_wp_call_all_hook($all_args);
	}

	if ( !isset($wp_filter[$tag]) ) {
		if ( isset($wp_filter['all']) )
			array_pop($wp_current_filter);
		return $args[0];
	}

	if ( !isset($wp_filter['all']) )
		$wp_current_filter[] = $tag;

	// Sort
	if ( !isset( $merged_filters[ $tag ] ) ) {
		ksort($wp_filter[$tag]);
		$merged_filters[ $tag ] = true;
	}

	reset( $wp_filter[ $tag ] );

	do {
		foreach ( (array) current($wp_filter[$tag]) as $the_ )
			if ( !is_null($the_['function']) )
				$args[0] = call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));

	} while ( next($wp_filter[$tag]) !== false );

	array_pop( $wp_current_filter );

	return $args[0];
}

function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
	$function_to_remove = _wp_filter_build_unique_id( $tag, $function_to_remove, $priority );

	$r = isset( $GLOBALS['wp_filter'][ $tag ][ $priority ][ $function_to_remove ] );

	if ( true === $r ) {
		unset( $GLOBALS['wp_filter'][ $tag ][ $priority ][ $function_to_remove ] );
		if ( empty( $GLOBALS['wp_filter'][ $tag ][ $priority ] ) ) {
			unset( $GLOBALS['wp_filter'][ $tag ][ $priority ] );
		}
		if ( empty( $GLOBALS['wp_filter'][ $tag ] ) ) {
			$GLOBALS['wp_filter'][ $tag ] = array();
		}
		unset( $GLOBALS['merged_filters'][ $tag ] );
	}

	return $r;
}

function remove_all_filters( $tag, $priority = false ) {
	global $wp_filter, $merged_filters;

	if ( isset( $wp_filter[ $tag ]) ) {
		if ( false === $priority ) {
			$wp_filter[ $tag ] = array();
		} elseif ( isset( $wp_filter[ $tag ][ $priority ] ) ) {
			$wp_filter[ $tag ][ $priority ] = array();
		}
	}

	unset( $merged_filters[ $tag ] );

	return true;
}

function current_filter() {
	global $wp_current_filter;
	return end( $wp_current_filter );
}

function current_action() {
	return current_filter();
}

function doing_filter( $filter = null ) {
	global $wp_current_filter;

	if ( null === $filter ) {
		return ! empty( $wp_current_filter );
	}

	return in_array( $filter, $wp_current_filter );
}

function doing_action( $action = null ) {
	return doing_filter( $action );
}

function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
	return add_filter($tag, $function_to_add, $priority, $accepted_args);
}

function do_action($tag, $arg = '') {
	global $wp_filter, $wp_actions, $merged_filters, $wp_current_filter;

	if ( ! isset($wp_actions[$tag]) )
		$wp_actions[$tag] = 1;
	else
		++$wp_actions[$tag];

	// Do 'all' actions first
	if ( isset($wp_filter['all']) ) {
		$wp_current_filter[] = $tag;
		$all_args = func_get_args();
		_wp_call_all_hook($all_args);
	}

	if ( !isset($wp_filter[$tag]) ) {
		if ( isset($wp_filter['all']) )
			array_pop($wp_current_filter);
		return;
	}

	if ( !isset($wp_filter['all']) )
		$wp_current_filter[] = $tag;

	$args = array();
	if ( is_array($arg) && 1 == count($arg) && isset($arg[0]) && is_object($arg[0]) ) // array(&$this)
		$args[] =& $arg[0];
	else
		$args[] = $arg;
	for ( $a = 2, $num = func_num_args(); $a < $num; $a++ )
		$args[] = func_get_arg($a);

	// Sort
	if ( !isset( $merged_filters[ $tag ] ) ) {
		ksort($wp_filter[$tag]);
		$merged_filters[ $tag ] = true;
	}

	reset( $wp_filter[ $tag ] );

	do {
		foreach ( (array) current($wp_filter[$tag]) as $the_ )
			if ( !is_null($the_['function']) )
				call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));

	} while ( next($wp_filter[$tag]) !== false );

	array_pop($wp_current_filter);
}

function did_action($tag) {
	global $wp_actions;

	if ( ! isset( $wp_actions[ $tag ] ) )
		return 0;

	return $wp_actions[$tag];
}

function do_action_ref_array($tag, $args) {
	global $wp_filter, $wp_actions, $merged_filters, $wp_current_filter;

	if ( ! isset($wp_actions[$tag]) )
		$wp_actions[$tag] = 1;
	else
		++$wp_actions[$tag];

	if ( isset($wp_filter['all']) ) {
		$wp_current_filter[] = $tag;
		$all_args = func_get_args();
		_wp_call_all_hook($all_args);
	}

	if ( !isset($wp_filter[$tag]) ) {
		if ( isset($wp_filter['all']) )
			array_pop($wp_current_filter);
		return;
	}

	if ( !isset($wp_filter['all']) )
		$wp_current_filter[] = $tag;

	if ( !isset( $merged_filters[ $tag ] ) ) {
		ksort($wp_filter[$tag]);
		$merged_filters[ $tag ] = true;
	}

	reset( $wp_filter[ $tag ] );

	do {
		foreach ( (array) current($wp_filter[$tag]) as $the_ )
			if ( !is_null($the_['function']) )
				call_user_func_array($the_['function'], array_slice($args, 0, (int) $the_['accepted_args']));

	} while ( next($wp_filter[$tag]) !== false );

	array_pop($wp_current_filter);
}

function has_action($tag, $function_to_check = false) {
	return has_filter($tag, $function_to_check);
}

function remove_action( $tag, $function_to_remove, $priority = 10 ) {
	return remove_filter( $tag, $function_to_remove, $priority );
}

function remove_all_actions($tag, $priority = false) {
	return remove_all_filters($tag, $priority);
}

function plugin_basename( $file ) {
	global $wp_plugin_paths;

	foreach ( $wp_plugin_paths as $dir => $realdir ) {
		if ( strpos( $file, $realdir ) === 0 ) {
			$file = $dir . substr( $file, strlen( $realdir ) );
		}
	}

	$file = wp_normalize_path( $file );
	$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
	$mu_plugin_dir = wp_normalize_path( WPMU_PLUGIN_DIR );

	$file = preg_replace('#^' . preg_quote($plugin_dir, '#') . '/|^' . preg_quote($mu_plugin_dir, '#') . '/#','',$file);
	$file = trim($file, '/');
	return $file;
}

function wp_register_plugin_realpath( $file ) {
	global $wp_plugin_paths;
	static $wp_plugin_path = null, $wpmu_plugin_path = null;
	if ( ! isset( $wp_plugin_path ) ) {
		$wp_plugin_path   = wp_normalize_path( WP_PLUGIN_DIR   );
	}
	$plugin_path = wp_normalize_path( dirname( $file ) );
	$plugin_realpath = wp_normalize_path( dirname( realpath( $file ) ) );
	if ( $plugin_path === $wp_plugin_path || $plugin_path === $wpmu_plugin_path ) {
		return false;
	}
	if ( $plugin_path !== $plugin_realpath ) {
		$wp_plugin_paths[ $plugin_path ] = $plugin_realpath;
	}
	return true;
}

function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}

function plugin_dir_url( $file ) {
	return trailingslashit( plugins_url( '', $file ) );
}

function register_activation_hook($file, $function) {
	$file = plugin_basename($file);
	add_action('activate_' . $file, $function);
}

function register_deactivation_hook($file, $function) {
	$file = plugin_basename($file);
	add_action('deactivate_' . $file, $function);
}

function register_uninstall_hook( $file, $callback ) {
	if ( is_array( $callback ) && is_object( $callback[0] ) ) {
		_doing_it_wrong( __FUNCTION__, 'Only a static class method or function can be used in an uninstall hook.', '3.1' );
		return;
	}

	$uninstallable_plugins = (array) get_option('uninstall_plugins');
	$uninstallable_plugins[plugin_basename($file)] = $callback;

	update_option('uninstall_plugins', $uninstallable_plugins);
}

function _wp_call_all_hook($args) {
	global $wp_filter;

	reset( $wp_filter['all'] );
	do {
		foreach ( (array) current($wp_filter['all']) as $the_ )
			if ( !is_null($the_['function']) )
				call_user_func_array($the_['function'], $args);

	} while ( next($wp_filter['all']) !== false );
}

function _wp_filter_build_unique_id($tag, $function, $priority) {
	global $wp_filter;
	static $filter_id_count = 0;

	if ( is_string($function) )
		return $function;

	if ( is_object($function) ) {
		// Closures are currently implemented as objects
		$function = array( $function, '' );
	} else {
		$function = (array) $function;
	}

	if (is_object($function[0]) ) {
		// Object Class Calling
		if ( function_exists('spl_object_hash') ) {
			return spl_object_hash($function[0]) . $function[1];
		} else {
			$obj_idx = get_class($function[0]).$function[1];
			if ( !isset($function[0]->wp_filter_id) ) {
				if ( false === $priority )
					return false;
				$obj_idx .= isset($wp_filter[$tag][$priority]) ? count((array)$wp_filter[$tag][$priority]) : $filter_id_count;
				$function[0]->wp_filter_id = $filter_id_count;
				++$filter_id_count;
			} else {
				$obj_idx .= $function[0]->wp_filter_id;
			}

			return $obj_idx;
		}
	} elseif ( is_string( $function[0] ) ) {
		// Static Calling
		return $function[0] . '::' . $function[1];
	}
}
