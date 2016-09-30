<?php

class WP_Widget_Factory {

	public $widgets = array();

	public function __construct() {
		add_action( 'widgets_init', array( $this, '_register_widgets' ), 100 );
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
			if ( in_array($this->widgets[$key]->id_base, $registered, true) ) {
				unset($this->widgets[$key]);
				continue;
			}
			$this->widgets[$key]->_register();
		}
	}
}
