<?php

class WP_Customize_Partial {

	public $component;

	public $id;

	protected $id_data = array();

	public $type = 'default';

	public $selector;

	public $settings;

	public $primary_setting;

	public $capability;

	public $render_callback;

	public $container_inclusive = false;

	public $fallback_refresh = true;

	public function __construct( WP_Customize_Selective_Refresh $component, $id, $args = array() ) {
		$keys = array_keys( get_object_vars( $this ) );
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}

		$this->component       = $component;
		$this->id              = $id;
		$this->id_data['keys'] = preg_split( '/\[/', str_replace( ']', '', $this->id ) );
		$this->id_data['base'] = array_shift( $this->id_data['keys'] );

		if ( empty( $this->render_callback ) ) {
			$this->render_callback = array( $this, 'render_callback' );
		}

		if ( ! isset( $this->settings ) ) {
			$this->settings = array( $id );
		} else if ( is_string( $this->settings ) ) {
			$this->settings = array( $this->settings );
		}

		if ( empty( $this->primary_setting ) ) {
			$this->primary_setting = current( $this->settings );
		}
	}

	final public function id_data() {
		return $this->id_data;
	}

	final public function render( $container_context = array() ) {
		$partial  = $this;
		$rendered = false;

		if ( ! empty( $this->render_callback ) ) {
			ob_start();
			$return_render = call_user_func( $this->render_callback, $this, $container_context );
			$ob_render = ob_get_clean();

			if ( null !== $return_render && '' !== $ob_render ) {
				_doing_it_wrong( __FUNCTION__, 'Partial render must echo the content or return the content string (or array), but not both.', '4.5.0' );
			}

			$rendered = null !== $return_render ? $return_render : $ob_render;
		}

		$rendered = apply_filters( 'customize_partial_render', $rendered, $partial, $container_context );

		$rendered = apply_filters( "customize_partial_render_{$partial->id}", $rendered, $partial, $container_context );

		return $rendered;
	}

	public function render_callback( WP_Customize_Partial $partial, $context = array() ) {
		unset( $partial, $context );
		return false;
	}

	public function json() {
		$exports = array(
			'settings'           => $this->settings,
			'primarySetting'     => $this->primary_setting,
			'selector'           => $this->selector,
			'type'               => $this->type,
			'fallbackRefresh'    => $this->fallback_refresh,
			'containerInclusive' => $this->container_inclusive,
		);
		return $exports;
	}

	final public function check_capabilities() {
		if ( ! empty( $this->capability ) && ! current_user_can( $this->capability ) ) {
			return false;
		}
		foreach ( $this->settings as $setting_id ) {
			$setting = $this->component->manager->get_setting( $setting_id );
			if ( ! $setting || ! $setting->check_capabilities() ) {
				return false;
			}
		}
		return true;
	}
}
