<?php
/**
 * Customize API: WP_Customize_Nav_Menu_Auto_Add_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Nav_Menu_Auto_Add_Control extends WP_Customize_Control {

	public $type = 'nav_menu_auto_add';

	protected function render_content() {}

	protected function content_template() {
		?>
		<span class="customize-control-title">Menu options</span>
		<label>
			<input type="checkbox" class="auto_add" />
			Automatically add new top-level pages to this menu
		</label>
		<?php
	}
}
