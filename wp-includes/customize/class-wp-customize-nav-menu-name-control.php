<?php
/**
 * Customize API: WP_Customize_Nav_Menu_Name_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Nav_Menu_Name_Control extends WP_Customize_Control {

	public $type = 'nav_menu_name';

	protected function render_content() {}

	protected function content_template() {
		?>
		<label>
			<# if ( data.label ) { #>
				<span class="customize-control-title screen-reader-text">{{ data.label }}</span>
			<# } #>
			<input type="text" class="menu-name-field live-update-section-title" />
		</label>
		<?php
	}
}
