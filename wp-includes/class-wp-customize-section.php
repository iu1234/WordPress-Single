<?php
/**
 * WordPress Customize Section classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

class WP_Customize_Section {

	protected static $instance_count = 0;

	public $instance_number;

	public $manager;

	public $id;

	public $priority = 160;

	public $panel = '';

	public $capability = 'edit_theme_options';

	public $theme_supports = '';

	public $title = '';

	public $description = '';

	public $controls;

	public $type = 'default';

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

		$this->controls = array(); // Users cannot customize the $controls array.
	}

	final public function active() {
		$section = $this;
		$active = call_user_func( $this->active_callback, $this );

		$active = apply_filters( 'customize_section_active', $active, $section );

		return $active;
	}

	public function active_callback() {
		return true;
	}

	public function json() {
		$array = wp_array_slice_assoc( (array) $this, array( 'id', 'description', 'priority', 'panel', 'type' ) );
		$array['title'] = html_entity_decode( $this->title, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$array['content'] = $this->get_content();
		$array['active'] = $this->active();
		$array['instanceNumber'] = $this->instance_number;

		if ( $this->panel ) {
			/* translators: &#9656; is the unicode right-pointing triangle, and %s is the section title in the Customizer */
			$array['customizeAction'] = sprintf( __( 'Customizing &#9656; %s' ), esc_html( $this->manager->get_panel( $this->panel )->title ) );
		} else {
			$array['customizeAction'] = __( 'Customizing' );
		}

		return $array;
	}

	final public function check_capabilities() {
		if ( $this->capability && ! call_user_func_array( 'current_user_can', (array) $this->capability ) ) {
			return false;
		}

		if ( $this->theme_supports && ! call_user_func_array( 'current_theme_supports', (array) $this->theme_supports ) ) {
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
		if ( ! $this->check_capabilities() ) {
			return;
		}

		do_action( 'customize_render_section', $this );

		do_action( "customize_render_section_{$this->id}" );

		$this->render();
	}

	protected function render() {}

	public function print_template() {
        ?>
		<script type="text/html" id="tmpl-customize-section-<?php echo $this->type; ?>">
			<?php $this->render_template(); ?>
		</script>
        <?php
	}

	protected function render_template() {
		?>
		<li id="accordion-section-{{ data.id }}" class="accordion-section control-section control-section-{{ data.type }}">
			<h3 class="accordion-section-title" tabindex="0">
				{{ data.title }}
				<span class="screen-reader-text">Press return or enter to open this section</span>
			</h3>
			<ul class="accordion-section-content">
				<li class="customize-section-description-container">
					<div class="customize-section-title">
						<button class="customize-section-back" tabindex="-1">
							<span class="screen-reader-text">Back</span>
						</button>
						<h3>
							<span class="customize-action">
								{{{ data.customizeAction }}}
							</span>
							{{ data.title }}
						</h3>
					</div>
					<# if ( data.description ) { #>
						<div class="description customize-section-description">
							{{{ data.description }}}
						</div>
					<# } #>
				</li>
			</ul>
		</li>
		<?php
	}
}

require_once( ABSPATH . WPINC . '/customize/class-wp-customize-themes-section.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-sidebar-section.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-section.php' );
require_once( ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-section.php' );
