<?php

class WP_Customize_New_Menu_Control extends WP_Customize_Control {

	public $type = 'new_menu';

	public function render_content() {
		?>
		<button type="button" class="button button-primary" id="create-new-menu-submit">Create Menu</button>
		<span class="spinner"></span>
		<?php
	}
}
