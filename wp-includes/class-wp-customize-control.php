<?php
/**
 * WordPress Customize Control classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

class WP_Customize_Control {

	protected static $instance_count = 0;

	public $instance_number;

	public $manager;

	public $id;

	public $settings;

	public $setting = 'default';

	public $capability;

	public $priority = 10;

	public $section = '';

	public $label = '';

	public $description = '';

	public $choices = array();

	public $input_attrs = array();

	public $json = array();

	public $type = 'text';

	public $active_callback = '';

	public function __construct( $manager, $id, $args = array() ) {
		$keys = array_keys( get_object_vars( $this ) );
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}

		$this->manager = $manager;
		$this->id = $id;
		if ( empty( $this->active_callback ) ) {
			$this->active_callback = array( $this, 'active_callback' );
		}
		self::$instance_count += 1;
		$this->instance_number = self::$instance_count;

		// Process settings.
		if ( ! isset( $this->settings ) ) {
			$this->settings = $id;
		}

		$settings = array();
		if ( is_array( $this->settings ) ) {
			foreach ( $this->settings as $key => $setting ) {
				$settings[ $key ] = $this->manager->get_setting( $setting );
			}
		} else if ( is_string( $this->settings ) ) {
			$this->setting = $this->manager->get_setting( $this->settings );
			$settings['default'] = $this->setting;
		}
		$this->settings = $settings;
	}

	public function enqueue() {}

	final public function active() {
		$control = $this;
		$active = call_user_func( $this->active_callback, $this );

		$active = apply_filters( 'customize_control_active', $active, $control );

		return $active;
	}

	public function active_callback() {
		return true;
	}

	final public function value( $setting_key = 'default' ) {
		if ( isset( $this->settings[ $setting_key ] ) ) {
			return $this->settings[ $setting_key ]->value();
		}
	}

	public function to_json() {
		$this->json['settings'] = array();
		foreach ( $this->settings as $key => $setting ) {
			$this->json['settings'][ $key ] = $setting->id;
		}

		$this->json['type'] = $this->type;
		$this->json['priority'] = $this->priority;
		$this->json['active'] = $this->active();
		$this->json['section'] = $this->section;
		$this->json['content'] = $this->get_content();
		$this->json['label'] = $this->label;
		$this->json['description'] = $this->description;
		$this->json['instanceNumber'] = $this->instance_number;
	}

	public function json() {
		$this->to_json();
		return $this->json;
	}

	final public function check_capabilities() {
		if ( ! empty( $this->capability ) && ! current_user_can( $this->capability ) ) {
			return false;
		}

		foreach ( $this->settings as $setting ) {
			if ( ! $setting || ! $setting->check_capabilities() ) {
				return false;
			}
		}

		$section = $this->manager->get_section( $this->section );
		if ( isset( $section ) && ! $section->check_capabilities() ) {
			return false;
		}

		return true;
	}

	final public function get_content() {
		ob_start();
		$this->maybe_render();
		return trim( ob_get_clean() );
	}

	final public function maybe_render() {
		if ( ! $this->check_capabilities() )
			return;

		do_action( 'customize_render_control', $this );

		do_action( 'customize_render_control_' . $this->id, $this );

		$this->render();
	}

	protected function render() {
		$id    = 'customize-control-' . str_replace( array( '[', ']' ), array( '-', '' ), $this->id );
		$class = 'customize-control customize-control-' . $this->type;

		?><li id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<?php $this->render_content(); ?>
		</li><?php
	}

	public function get_link( $setting_key = 'default' ) {
		if ( ! isset( $this->settings[ $setting_key ] ) )
			return '';

		return 'data-customize-setting-link="' . esc_attr( $this->settings[ $setting_key ]->id ) . '"';
	}

	public function link( $setting_key = 'default' ) {
		echo $this->get_link( $setting_key );
	}

	public function input_attrs() {
		foreach ( $this->input_attrs as $attr => $value ) {
			echo $attr . '="' . esc_attr( $value ) . '" ';
		}
	}

	protected function render_content() {
		switch( $this->type ) {
			case 'checkbox':
				?>
				<label>
					<input type="checkbox" value="<?php echo esc_attr( $this->value() ); ?>" <?php $this->link(); checked( $this->value() ); ?> />
					<?php echo esc_html( $this->label ); ?>
					<?php if ( ! empty( $this->description ) ) : ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php endif; ?>
				</label>
				<?php
				break;
			case 'radio':
				if ( empty( $this->choices ) )
					return;

				$name = '_customize-radio-' . $this->id;

				if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif;
				if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo $this->description ; ?></span>
				<?php endif;

				foreach ( $this->choices as $value => $label ) :
					?>
					<label>
						<input type="radio" value="<?php echo esc_attr( $value ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php $this->link(); checked( $this->value(), $value ); ?> />
						<?php echo esc_html( $label ); ?><br/>
					</label>
					<?php
				endforeach;
				break;
			case 'select':
				if ( empty( $this->choices ) )
					return;

				?>
				<label>
					<?php if ( ! empty( $this->label ) ) : ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php endif;
					if ( ! empty( $this->description ) ) : ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php endif; ?>

					<select <?php $this->link(); ?>>
						<?php
						foreach ( $this->choices as $value => $label )
							echo '<option value="' . esc_attr( $value ) . '"' . selected( $this->value(), $value, false ) . '>' . $label . '</option>';
						?>
					</select>
				</label>
				<?php
				break;
			case 'textarea':
				?>
				<label>
					<?php if ( ! empty( $this->label ) ) : ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php endif;
					if ( ! empty( $this->description ) ) : ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php endif; ?>
					<textarea rows="5" <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
				</label>
				<?php
				break;
			case 'dropdown-pages':
				?>
				<label>
				<?php if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif;
				if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo $this->description; ?></span>
				<?php endif; ?>

				<?php $dropdown = wp_dropdown_pages(
					array(
						'name'              => '_customize-dropdown-pages-' . $this->id,
						'echo'              => 0,
						'show_option_none'  => '&mdash; Select &mdash;',
						'option_none_value' => '0',
						'selected'          => $this->value(),
					)
				);

				$dropdown = str_replace( '<select', '<select ' . $this->get_link(), $dropdown );
				echo $dropdown;
				?>
				</label>
				<?php
				break;
			default:
				?>
				<label>
					<?php if ( ! empty( $this->label ) ) : ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php endif;
					if ( ! empty( $this->description ) ) : ?>
						<span class="description customize-control-description"><?php echo $this->description; ?></span>
					<?php endif; ?>
					<input type="<?php echo esc_attr( $this->type ); ?>" <?php $this->input_attrs(); ?> value="<?php echo esc_attr( $this->value() ); ?>" <?php $this->link(); ?> />
				</label>
				<?php
				break;
		}
	}

	final public function print_template() {
		?>
		<script type="text/html" id="tmpl-customize-control-<?php echo $this->type; ?>-content">
			<?php $this->content_template(); ?>
		</script>
		<?php
	}

	protected function content_template() {}

}

require_once( ABSPATH . WPINC . '/customize/class-wp-customize-color-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-media-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-upload-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-image-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-background-image-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-cropped-image-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-site-icon-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-header-image-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-theme-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-widget-area-customize-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-widget-form-customize-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-location-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-name-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-auto-add-control.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-control.php' );
