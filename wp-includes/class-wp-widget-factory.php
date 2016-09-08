<?php
/**
 * Widget API: WP_Widget_Factory class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.4.0
 */

/**
 * Singleton that registers and instantiates WP_Widget classes.
 *
 * @since 2.8.0
 * @since 4.4.0 Moved to its own file from wp-includes/widgets.php
 */
class WP_Widget_Factory {

	public $widgets = array();

	public function __construct() {
		add_action( 'widgets_init', array( $this, '_register_widgets' ), 100 );
	}

	public function WP_Widget_Factory() {
		_deprecated_constructor( 'WP_Widget_Factory', '4.2.0' );
		self::__construct();
	}

	public function register( $widget_class ) {
		$this->widgets[$widget_class] = new $widget_class();
	}

	public function unregister( $widget_class ) {
		unset( $this->widgets[ $widget_class ] );
	}

	public function _register_widgets() {
		global $wp_registered_widgets;
		$keys = array_keys($this->widgets);
		$registered = array_keys($wp_registered_widgets);
		$registered = array_map('_get_widget_id_base', $registered);

		foreach ( $keys as $key ) {
			// don't register new widget if old widget with the same id is already registered
			if ( in_array($this->widgets[$key]->id_base, $registered, true) ) {
				unset($this->widgets[$key]);
				continue;
			}

			$this->widgets[$key]->_register();
		}
	}
}
