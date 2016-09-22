<?php
/**
 * Core Widgets API
 *
 * This API is used for creating dynamic sidebar without hardcoding functionality into
 * themes
 *
 * Includes both internal WordPress routines and theme-use routines.
 *
 * This functionality was found in a plugin before the WordPress 2.2 release, which
 * included it in the core from that point on.
 *
 * @link https://codex.wordpress.org/Plugins/WordPress_Widgets WordPress Widgets
 * @link https://codex.wordpress.org/Plugins/WordPress_Widgets_Api Widgets API
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 2.2.0
 */

global $wp_registered_sidebars, $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates;

$wp_registered_sidebars = array();
$wp_registered_widgets = array();
$wp_registered_widget_controls = array();
$wp_registered_widget_updates = array();
$_wp_sidebars_widgets = array();

$GLOBALS['_wp_deprecated_widgets_callbacks'] = array(
	'wp_widget_pages',
	'wp_widget_pages_control',
	'wp_widget_calendar',
	'wp_widget_calendar_control',
	'wp_widget_archives',
	'wp_widget_archives_control',
	'wp_widget_links',
	'wp_widget_meta',
	'wp_widget_meta_control',
	'wp_widget_search',
	'wp_widget_recent_entries',
	'wp_widget_recent_entries_control',
	'wp_widget_tag_cloud',
	'wp_widget_tag_cloud_control',
	'wp_widget_categories',
	'wp_widget_categories_control',
	'wp_widget_text',
	'wp_widget_text_control',
	'wp_widget_rss',
	'wp_widget_rss_control',
	'wp_widget_recent_comments',
	'wp_widget_recent_comments_control'
);

function register_sidebars( $number = 1, $args = array() ) {
	global $wp_registered_sidebars;
	$number = (int) $number;

	if ( is_string($args) )
		parse_str($args, $args);

	for ( $i = 1; $i <= $number; $i++ ) {
		$_args = $args;

		if ( $number > 1 )
			$_args['name'] = isset($args['name']) ? sprintf($args['name'], $i) : sprintf('Sidebar %d', $i);
		else
			$_args['name'] = isset($args['name']) ? $args['name'] : 'Sidebar';

		if ( isset($args['id']) ) {
			$_args['id'] = $args['id'];
			$n = 2;
			while ( is_registered_sidebar( $_args['id'] ) ) {
				$_args['id'] = $args['id'] . '-' . $n++;
			}
		} else {
			$n = count( $wp_registered_sidebars );
			do {
				$_args['id'] = 'sidebar-' . ++$n;
			} while ( is_registered_sidebar( $_args['id'] ) );
		}
		register_sidebar($_args);
	}
}

function register_sidebar($args = array()) {
	global $wp_registered_sidebars;
	$i = count($wp_registered_sidebars) + 1;
	$id_is_empty = empty( $args['id'] );
	$defaults = array(
		'name' => sprintf('Sidebar %d', $i ),
		'id' => "sidebar-$i",
		'description' => '',
		'class' => '',
		'before_widget' => '<li id="%1$s" class="widget %2$s">',
		'after_widget' => "</li>\n",
		'before_title' => '<h2 class="widgettitle">',
		'after_title' => "</h2>\n",
	);
	$sidebar = wp_parse_args( $args, $defaults );

	if ( $id_is_empty ) {
		_doing_it_wrong( __FUNCTION__, sprintf( 'No %1$s was set in the arguments array for the "%2$s" sidebar. Defaulting to "%3$s". Manually set the %1$s to "%3$s" to silence this notice and keep existing sidebar content.', '<code>id</code>', $sidebar['name'], $sidebar['id'] ), '4.2.0' );
	}
	$wp_registered_sidebars[$sidebar['id']] = $sidebar;
	add_theme_support('widgets');
	do_action( 'register_sidebar', $sidebar );
	return $sidebar['id'];
}

function unregister_sidebar( $name ) {
	global $wp_registered_sidebars;
	unset( $wp_registered_sidebars[ $name ] );
}

function is_registered_sidebar( $sidebar_id ) {
	global $wp_registered_sidebars;
	return isset( $wp_registered_sidebars[ $sidebar_id ] );
}

function wp_register_sidebar_widget( $id, $name, $output_callback, $options = array() ) {
	global $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates, $_wp_deprecated_widgets_callbacks;

	$id = strtolower($id);

	if ( empty($output_callback) ) {
		unset($wp_registered_widgets[$id]);
		return;
	}

	$id_base = _get_widget_id_base($id);
	if ( in_array($output_callback, $_wp_deprecated_widgets_callbacks, true) && !is_callable($output_callback) ) {
		unset( $wp_registered_widget_controls[ $id ] );
		unset( $wp_registered_widget_updates[ $id_base ] );
		return;
	}

	$defaults = array('classname' => $output_callback);
	$options = wp_parse_args($options, $defaults);
	$widget = array(
		'name' => $name,
		'id' => $id,
		'callback' => $output_callback,
		'params' => array_slice(func_get_args(), 4)
	);
	$widget = array_merge($widget, $options);

	if ( is_callable($output_callback) && ( !isset($wp_registered_widgets[$id]) || did_action( 'widgets_init' ) ) ) {

		do_action( 'wp_register_sidebar_widget', $widget );
		$wp_registered_widgets[$id] = $widget;
	}
}

function wp_widget_description( $id ) {
	if ( !is_scalar($id) )
		return;

	global $wp_registered_widgets;

	if ( isset($wp_registered_widgets[$id]['description']) )
		return esc_html( $wp_registered_widgets[$id]['description'] );
}

function wp_sidebar_description( $id ) {
	if ( !is_scalar($id) )
		return;

	global $wp_registered_sidebars;

	if ( isset($wp_registered_sidebars[$id]['description']) )
		return esc_html( $wp_registered_sidebars[$id]['description'] );
}

function wp_unregister_sidebar_widget($id) {
	do_action( 'wp_unregister_sidebar_widget', $id );
	wp_register_sidebar_widget($id, '', '');
	wp_unregister_widget_control($id);
}

function wp_register_widget_control( $id, $name, $control_callback, $options = array() ) {
	global $wp_registered_widget_controls, $wp_registered_widget_updates, $wp_registered_widgets, $_wp_deprecated_widgets_callbacks;

	$id = strtolower($id);
	$id_base = _get_widget_id_base($id);

	if ( empty($control_callback) ) {
		unset($wp_registered_widget_controls[$id]);
		unset($wp_registered_widget_updates[$id_base]);
		return;
	}

	if ( in_array($control_callback, $_wp_deprecated_widgets_callbacks, true) && !is_callable($control_callback) ) {
		unset( $wp_registered_widgets[ $id ] );
		return;
	}

	if ( isset($wp_registered_widget_controls[$id]) && !did_action( 'widgets_init' ) )
		return;

	$defaults = array('width' => 250, 'height' => 200 );
	$options = wp_parse_args($options, $defaults);
	$options['width'] = (int) $options['width'];
	$options['height'] = (int) $options['height'];

	$widget = array(
		'name' => $name,
		'id' => $id,
		'callback' => $control_callback,
		'params' => array_slice(func_get_args(), 4)
	);
	$widget = array_merge($widget, $options);

	$wp_registered_widget_controls[$id] = $widget;

	if ( isset($wp_registered_widget_updates[$id_base]) )
		return;

	if ( isset($widget['params'][0]['number']) )
		$widget['params'][0]['number'] = -1;

	unset($widget['width'], $widget['height'], $widget['name'], $widget['id']);
	$wp_registered_widget_updates[$id_base] = $widget;
}

function _register_widget_update_callback( $id_base, $update_callback, $options = array() ) {
	global $wp_registered_widget_updates;

	if ( isset($wp_registered_widget_updates[$id_base]) ) {
		if ( empty($update_callback) )
			unset($wp_registered_widget_updates[$id_base]);
		return;
	}

	$widget = array(
		'callback' => $update_callback,
		'params' => array_slice(func_get_args(), 3)
	);

	$widget = array_merge($widget, $options);
	$wp_registered_widget_updates[$id_base] = $widget;
}

function _register_widget_form_callback($id, $name, $form_callback, $options = array()) {
	global $wp_registered_widget_controls;

	$id = strtolower($id);

	if ( empty($form_callback) ) {
		unset($wp_registered_widget_controls[$id]);
		return;
	}

	if ( isset($wp_registered_widget_controls[$id]) && !did_action( 'widgets_init' ) )
		return;

	$defaults = array('width' => 250, 'height' => 200 );
	$options = wp_parse_args($options, $defaults);
	$options['width'] = (int) $options['width'];
	$options['height'] = (int) $options['height'];

	$widget = array(
		'name' => $name,
		'id' => $id,
		'callback' => $form_callback,
		'params' => array_slice(func_get_args(), 4)
	);
	$widget = array_merge($widget, $options);

	$wp_registered_widget_controls[$id] = $widget;
}

function wp_unregister_widget_control($id) {
	wp_register_widget_control( $id, '', '' );
}

function dynamic_sidebar( $index = 1 ) {
	global $wp_registered_sidebars, $wp_registered_widgets;

	if ( is_int( $index ) ) {
		$index = "sidebar-$index";
	} else {
		$index = sanitize_title( $index );
		foreach ( (array) $wp_registered_sidebars as $key => $value ) {
			if ( sanitize_title( $value['name'] ) == $index ) {
				$index = $key;
				break;
			}
		}
	}

	$sidebars_widgets = wp_get_sidebars_widgets();
	if ( empty( $wp_registered_sidebars[ $index ] ) || empty( $sidebars_widgets[ $index ] ) || ! is_array( $sidebars_widgets[ $index ] ) ) {
		do_action( 'dynamic_sidebar_before', $index, false );
		do_action( 'dynamic_sidebar_after',  $index, false );
		return apply_filters( 'dynamic_sidebar_has_widgets', false, $index );
	}

	do_action( 'dynamic_sidebar_before', $index, true );
	$sidebar = $wp_registered_sidebars[$index];

	$did_one = false;
	foreach ( (array) $sidebars_widgets[$index] as $id ) {

		if ( !isset($wp_registered_widgets[$id]) ) continue;

		$params = array_merge(
			array( array_merge( $sidebar, array('widget_id' => $id, 'widget_name' => $wp_registered_widgets[$id]['name']) ) ),
			(array) $wp_registered_widgets[$id]['params']
		);

		// Substitute HTML id and class attributes into before_widget
		$classname_ = '';
		foreach ( (array) $wp_registered_widgets[$id]['classname'] as $cn ) {
			if ( is_string($cn) )
				$classname_ .= '_' . $cn;
			elseif ( is_object($cn) )
				$classname_ .= '_' . get_class($cn);
		}
		$classname_ = ltrim($classname_, '_');
		$params[0]['before_widget'] = sprintf($params[0]['before_widget'], $id, $classname_);

		$params = apply_filters( 'dynamic_sidebar_params', $params );

		$callback = $wp_registered_widgets[$id]['callback'];

		do_action( 'dynamic_sidebar', $wp_registered_widgets[ $id ] );

		if ( is_callable($callback) ) {
			call_user_func_array($callback, $params);
			$did_one = true;
		}
	}

	do_action( 'dynamic_sidebar_after', $index, true );

	return apply_filters( 'dynamic_sidebar_has_widgets', $did_one, $index );
}

function is_active_widget( $callback = false, $widget_id = false, $id_base = false, $skip_inactive = true ) {
	global $wp_registered_widgets;

	$sidebars_widgets = wp_get_sidebars_widgets();

	if ( is_array($sidebars_widgets) ) {
		foreach ( $sidebars_widgets as $sidebar => $widgets ) {
			if ( $skip_inactive && ( 'wp_inactive_widgets' === $sidebar || 'orphaned_widgets' === substr( $sidebar, 0, 16 ) ) ) {
				continue;
			}

			if ( is_array($widgets) ) {
				foreach ( $widgets as $widget ) {
					if ( ( $callback && isset($wp_registered_widgets[$widget]['callback']) && $wp_registered_widgets[$widget]['callback'] == $callback ) || ( $id_base && _get_widget_id_base($widget) == $id_base ) ) {
						if ( !$widget_id || $widget_id == $wp_registered_widgets[$widget]['id'] )
							return $sidebar;
					}
				}
			}
		}
	}
	return false;
}

function is_dynamic_sidebar() {
	global $wp_registered_widgets, $wp_registered_sidebars;
	$sidebars_widgets = get_option('sidebars_widgets');
	foreach ( (array) $wp_registered_sidebars as $index => $sidebar ) {
		if ( ! empty( $sidebars_widgets[ $index ] ) ) {
			foreach ( (array) $sidebars_widgets[$index] as $widget )
				if ( array_key_exists($widget, $wp_registered_widgets) )
					return true;
		}
	}
	return false;
}

function is_active_sidebar( $index ) {
	$index = ( is_int($index) ) ? "sidebar-$index" : sanitize_title($index);
	$sidebars_widgets = wp_get_sidebars_widgets();
	$is_active_sidebar = ! empty( $sidebars_widgets[$index] );
	return apply_filters( 'is_active_sidebar', $is_active_sidebar, $index );
}

function wp_get_sidebars_widgets( $deprecated = true ) {
	if ( $deprecated !== true )
		_deprecated_argument( __FUNCTION__, '2.8.1' );

	global $_wp_sidebars_widgets, $sidebars_widgets;

	if ( !is_admin() ) {
		if ( empty($_wp_sidebars_widgets) )
			$_wp_sidebars_widgets = get_option('sidebars_widgets', array());

		$sidebars_widgets = $_wp_sidebars_widgets;
	} else {
		$sidebars_widgets = get_option('sidebars_widgets', array());
	}

	if ( is_array( $sidebars_widgets ) && isset($sidebars_widgets['array_version']) )
		unset($sidebars_widgets['array_version']);

	return apply_filters( 'sidebars_widgets', $sidebars_widgets );
}

function wp_set_sidebars_widgets( $sidebars_widgets ) {
	if ( !isset( $sidebars_widgets['array_version'] ) )
		$sidebars_widgets['array_version'] = 3;
	update_option( 'sidebars_widgets', $sidebars_widgets );
}

function wp_get_widget_defaults() {
	global $wp_registered_sidebars;
	$defaults = array();
	foreach ( (array) $wp_registered_sidebars as $index => $sidebar )
		$defaults[$index] = array();
	return $defaults;
}

function wp_convert_widget_settings($base_name, $option_name, $settings) {
	$single = $changed = false;
	if ( empty($settings) ) {
		$single = true;
	} else {
		foreach ( array_keys($settings) as $number ) {
			if ( 'number' == $number )
				continue;
			if ( !is_numeric($number) ) {
				$single = true;
				break;
			}
		}
	}

	if ( $single ) {
		$settings = array( 2 => $settings );
		if ( is_admin() ) {
			$sidebars_widgets = get_option('sidebars_widgets');
		} else {
			if ( empty($GLOBALS['_wp_sidebars_widgets']) )
				$GLOBALS['_wp_sidebars_widgets'] = get_option('sidebars_widgets', array());
			$sidebars_widgets = &$GLOBALS['_wp_sidebars_widgets'];
		}

		foreach ( (array) $sidebars_widgets as $index => $sidebar ) {
			if ( is_array($sidebar) ) {
				foreach ( $sidebar as $i => $name ) {
					if ( $base_name == $name ) {
						$sidebars_widgets[$index][$i] = "$name-2";
						$changed = true;
						break 2;
					}
				}
			}
		}

		if ( is_admin() && $changed )
			update_option('sidebars_widgets', $sidebars_widgets);
	}

	$settings['_multiwidget'] = 1;
	if ( is_admin() )
		update_option( $option_name, $settings );

	return $settings;
}

function the_widget( $widget, $instance = array(), $args = array() ) {
	global $wp_widget_factory;

	$widget_obj = $wp_widget_factory->widgets[$widget];
	if ( ! ( $widget_obj instanceof WP_Widget ) ) {
		return;
	}
	$default_args = array(
		'before_widget' => '<div class="widget %s">',
		'after_widget'  => "</div>",
		'before_title'  => '<h2 class="widgettitle">',
		'after_title'   => '</h2>',
	);
	$args = wp_parse_args( $args, $default_args );
	$args['before_widget'] = sprintf( $args['before_widget'], $widget_obj->widget_options['classname'] );

	$instance = wp_parse_args($instance);

	do_action( 'the_widget', $widget, $instance, $args );

	$widget_obj->_set(-1);
	$widget_obj->widget($args, $instance);
}

function _get_widget_id_base( $id ) {
	return preg_replace( '/-[0-9]+$/', '', $id );
}

function _wp_sidebars_changed() {
	global $sidebars_widgets;

	if ( ! is_array( $sidebars_widgets ) )
		$sidebars_widgets = wp_get_sidebars_widgets();

	retrieve_widgets(true);
}

function retrieve_widgets( $theme_changed = false ) {
	global $wp_registered_sidebars, $sidebars_widgets, $wp_registered_widgets;

	$registered_sidebar_keys = array_keys( $wp_registered_sidebars );
	$orphaned = 0;

	$old_sidebars_widgets = get_theme_mod( 'sidebars_widgets' );
	if ( is_array( $old_sidebars_widgets ) ) {
		// time() that sidebars were stored is in $old_sidebars_widgets['time']
		$_sidebars_widgets = $old_sidebars_widgets['data'];

		if ( 'customize' !== $theme_changed ) {
			remove_theme_mod( 'sidebars_widgets' );
		}

		foreach ( $_sidebars_widgets as $sidebar => $widgets ) {
			if ( 'wp_inactive_widgets' === $sidebar || 'orphaned_widgets' === substr( $sidebar, 0, 16 ) ) {
				continue;
			}

			if ( !in_array( $sidebar, $registered_sidebar_keys ) ) {
				$_sidebars_widgets['orphaned_widgets_' . ++$orphaned] = $widgets;
				unset( $_sidebars_widgets[$sidebar] );
			}
		}
	} else {
		if ( empty( $sidebars_widgets ) )
			return;

		unset( $sidebars_widgets['array_version'] );

		$old = array_keys($sidebars_widgets);
		sort($old);
		sort($registered_sidebar_keys);

		if ( $old == $registered_sidebar_keys )
			return;

		$_sidebars_widgets = array(
			'wp_inactive_widgets' => !empty( $sidebars_widgets['wp_inactive_widgets'] ) ? $sidebars_widgets['wp_inactive_widgets'] : array()
		);

		unset( $sidebars_widgets['wp_inactive_widgets'] );

		foreach ( $wp_registered_sidebars as $id => $settings ) {
			if ( $theme_changed ) {
				$_sidebars_widgets[$id] = array_shift( $sidebars_widgets );
			} else {
				// no theme change, grab only sidebars that are currently registered
				if ( isset( $sidebars_widgets[$id] ) ) {
					$_sidebars_widgets[$id] = $sidebars_widgets[$id];
					unset( $sidebars_widgets[$id] );
				}
			}
		}

		foreach ( $sidebars_widgets as $val ) {
			if ( is_array($val) && ! empty( $val ) )
				$_sidebars_widgets['orphaned_widgets_' . ++$orphaned] = $val;
		}
	}

	// discard invalid, theme-specific widgets from sidebars
	$shown_widgets = array();

	foreach ( $_sidebars_widgets as $sidebar => $widgets ) {
		if ( !is_array($widgets) )
			continue;

		$_widgets = array();
		foreach ( $widgets as $widget ) {
			if ( isset($wp_registered_widgets[$widget]) )
				$_widgets[] = $widget;
		}

		$_sidebars_widgets[$sidebar] = $_widgets;
		$shown_widgets = array_merge($shown_widgets, $_widgets);
	}

	$sidebars_widgets = $_sidebars_widgets;
	unset($_sidebars_widgets, $_widgets);

	$lost_widgets = array();
	foreach ( $wp_registered_widgets as $key => $val ) {
		if ( in_array($key, $shown_widgets, true) )
			continue;

		$number = preg_replace('/.+?-([0-9]+)$/', '$1', $key);

		if ( 2 > (int) $number )
			continue;

		$lost_widgets[] = $key;
	}

	$sidebars_widgets['wp_inactive_widgets'] = array_merge($lost_widgets, (array) $sidebars_widgets['wp_inactive_widgets']);
	if ( 'customize' !== $theme_changed ) {
		wp_set_sidebars_widgets( $sidebars_widgets );
	}
	return $sidebars_widgets;
}

function wp_widgets_init() {
	register_widget('WP_Widget_Pages');
	register_widget('WP_Widget_Archives');
	register_widget('WP_Widget_Links');
	register_widget('WP_Widget_Meta');
	register_widget('WP_Widget_Search');
	register_widget('WP_Widget_Text');
	register_widget('WP_Widget_Categories');
	register_widget('WP_Widget_Recent_Posts');
	register_widget('WP_Nav_Menu_Widget');
	do_action( 'widgets_init' );
}
