<?php

class WP_Widget {

	public $id_base;

	public $name;

	public $widget_options;

	public $control_options;

	public $number = false;

	public $id = false;

	public $updated = false;

	public function widget( $args, $instance ) {
		die('function WP_Widget::widget() must be over-ridden in a sub-class.');
	}

	public function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	public function form( $instance ) {
		echo '<p class="no-options-widget">There are no options for this widget.</p>';
		return 'noform';
	}

	public function __construct( $id_base, $name, $widget_options = array(), $control_options = array() ) {
		$this->id_base = empty($id_base) ? preg_replace( '/(wp_)?widget_/', '', strtolower(get_class($this)) ) : strtolower($id_base);
		$this->name = $name;
		$this->option_name = 'widget_' . $this->id_base;
		$this->widget_options = wp_parse_args( $widget_options, array( 'classname' => $this->option_name, 'customize_selective_refresh' => false ) );
		$this->control_options = wp_parse_args( $control_options, array( 'id_base' => $this->id_base ) );
	}

	public function get_field_name($field_name) {
		if ( false === $pos = strpos( $field_name, '[' ) ) {
			return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . ']';
		} else {
			return 'widget-' . $this->id_base . '[' . $this->number . '][' . substr_replace( $field_name, '][', $pos, strlen( '[' ) );
		}
	}

	public function get_field_id( $field_name ) {
		return 'widget-' . $this->id_base . '-' . $this->number . '-' . trim( str_replace( array( '[]', '[', ']' ), array( '', '-', '' ), $field_name ), '-' );
	}

	public function _register() {
		$settings = $this->get_settings();
		$empty = true;

		if ( $settings instanceof ArrayObject || $settings instanceof ArrayIterator ) {
			$settings = $settings->getArrayCopy();
		}

		if ( is_array( $settings ) ) {
			foreach ( array_keys( $settings ) as $number ) {
				if ( is_numeric( $number ) ) {
					$this->_set( $number );
					$this->_register_one( $number );
					$empty = false;
				}
			}
		}

		if ( $empty ) {
			$this->_set( 1 );
			$this->_register_one();
		}
	}

	public function _set($number) {
		$this->number = $number;
		$this->id = $this->id_base . '-' . $number;
	}

	public function _get_display_callback() {
		return array($this, 'display_callback');
	}

	public function _get_update_callback() {
		return array($this, 'update_callback');
	}

	public function _get_form_callback() {
		return array($this, 'form_callback');
	}

	public function is_preview() {
		global $wp_customize;
		return ( isset( $wp_customize ) && $wp_customize->is_preview() ) ;
	}

	public function display_callback( $args, $widget_args = 1 ) {
		if ( is_numeric( $widget_args ) ) {
			$widget_args = array( 'number' => $widget_args );
		}

		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		$this->_set( $widget_args['number'] );
		$instances = $this->get_settings();

		if ( array_key_exists( $this->number, $instances ) ) {
			$instance = $instances[ $this->number ];

			$instance = apply_filters( 'widget_display_callback', $instance, $this, $args );

			if ( false === $instance ) {
				return;
			}

			$was_cache_addition_suspended = wp_suspend_cache_addition();
			if ( $this->is_preview() && ! $was_cache_addition_suspended ) {
				wp_suspend_cache_addition( true );
			}

			$this->widget( $args, $instance );

			if ( $this->is_preview() ) {
				wp_suspend_cache_addition( $was_cache_addition_suspended );
			}
		}
	}

	public function update_callback( $deprecated = 1 ) {
		global $wp_registered_widgets;

		$all_instances = $this->get_settings();

		if ( $this->updated )
			return;

		if ( isset($_POST['delete_widget']) && $_POST['delete_widget'] ) {

			if ( isset($_POST['the-widget-id']) )
				$del_id = $_POST['the-widget-id'];
			else
				return;

			if ( isset($wp_registered_widgets[$del_id]['params'][0]['number']) ) {
				$number = $wp_registered_widgets[$del_id]['params'][0]['number'];

				if ( $this->id_base . '-' . $number == $del_id )
					unset($all_instances[$number]);
			}
		} else {
			if ( isset($_POST['widget-' . $this->id_base]) && is_array($_POST['widget-' . $this->id_base]) ) {
				$settings = $_POST['widget-' . $this->id_base];
			} elseif ( isset($_POST['id_base']) && $_POST['id_base'] == $this->id_base ) {
				$num = $_POST['multi_number'] ? (int) $_POST['multi_number'] : (int) $_POST['widget_number'];
				$settings = array( $num => array() );
			} else {
				return;
			}

			foreach ( $settings as $number => $new_instance ) {
				$new_instance = stripslashes_deep($new_instance);
				$this->_set($number);

				$old_instance = isset($all_instances[$number]) ? $all_instances[$number] : array();

				$was_cache_addition_suspended = wp_suspend_cache_addition();
				if ( $this->is_preview() && ! $was_cache_addition_suspended ) {
					wp_suspend_cache_addition( true );
				}

				$instance = $this->update( $new_instance, $old_instance );

				if ( $this->is_preview() ) {
					wp_suspend_cache_addition( $was_cache_addition_suspended );
				}

				$instance = apply_filters( 'widget_update_callback', $instance, $new_instance, $old_instance, $this );
				if ( false !== $instance ) {
					$all_instances[$number] = $instance;
				}

				break; // run only once
			}
		}

		$this->save_settings($all_instances);
		$this->updated = true;
	}

	public function form_callback( $widget_args = 1 ) {
		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );

		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		$all_instances = $this->get_settings();

		if ( -1 == $widget_args['number'] ) {
			// We echo out a form where 'number' can be set later
			$this->_set('__i__');
			$instance = array();
		} else {
			$this->_set($widget_args['number']);
			$instance = $all_instances[ $widget_args['number'] ];
		}

		$instance = apply_filters( 'widget_form_callback', $instance, $this );

		$return = null;
		if ( false !== $instance ) {
			$return = $this->form($instance);

			do_action_ref_array( 'in_widget_form', array( &$this, &$return, $instance ) );
		}
		return $return;
	}

	public function _register_one( $number = -1 ) {
		wp_register_sidebar_widget(	$this->id, $this->name,	$this->_get_display_callback(), $this->widget_options, array( 'number' => $number ) );
		_register_widget_update_callback( $this->id_base, $this->_get_update_callback(), $this->control_options, array( 'number' => -1 ) );
		_register_widget_form_callback(	$this->id, $this->name,	$this->_get_form_callback(), $this->control_options, array( 'number' => $number ) );
	}

	public function save_settings( $settings ) {
		$settings['_multiwidget'] = 1;
		update_option( $this->option_name, $settings );
	}

	public function get_settings() {

		$settings = get_option( $this->option_name );

		if ( false === $settings ) {
			if ( isset( $this->alt_option_name ) ) {
				$settings = get_option( $this->alt_option_name );
			} else {
				$this->save_settings( array() );
			}
		}

		if ( ! is_array( $settings ) && ! ( $settings instanceof ArrayObject || $settings instanceof ArrayIterator ) ) {
			$settings = array();
		}

		if ( ! empty( $settings ) && ! isset( $settings['_multiwidget'] ) ) {
			$settings = wp_convert_widget_settings( $this->id_base, $this->option_name, $settings );
		}

		unset( $settings['_multiwidget'], $settings['__i__'] );
		return $settings;
	}
}
