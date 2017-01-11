<?php
/**
 * Customize API: WP_Customize_Color_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Color_Control extends WP_Customize_Control {

	public $type = 'color';

	public $statuses;

	public function __construct( $manager, $id, $args = array() ) {
		$this->statuses = array( '' => 'Default' );
		parent::__construct( $manager, $id, $args );
	}

	public function enqueue() {
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );
	}

	public function to_json() {
		parent::to_json();
		$this->json['statuses'] = $this->statuses;
		$this->json['defaultValue'] = $this->setting->default;
	}

	public function render_content() {}

	public function content_template() {
		?>
		<# var defaultValue = '';
		if ( data.defaultValue ) {
			if ( '#' !== data.defaultValue.substring( 0, 1 ) ) {
				defaultValue = '#' + data.defaultValue;
			} else {
				defaultValue = data.defaultValue;
			}
			defaultValue = ' data-default-color=' + defaultValue; // Quotes added automatically.
		} #>
		<label>
			<# if ( data.label ) { #>
				<span class="customize-control-title">{{{ data.label }}}</span>
			<# } #>
			<# if ( data.description ) { #>
				<span class="description customize-control-description">{{{ data.description }}}</span>
			<# } #>
			<div class="customize-control-content">
				<input class="color-picker-hex" type="text" maxlength="7" placeholder="Hex Value" {{ defaultValue }} />
			</div>
		</label>
		<?php
	}
}
