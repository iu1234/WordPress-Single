<?php
/**
 * Customize API: WP_Customize_Nav_Menus_Panel class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Nav_Menus_Panel extends WP_Customize_Panel {

	public $type = 'nav_menus';

	public function render_screen_options() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
		add_filter( 'manage_nav-menus_columns', 'wp_nav_menu_manage_columns' );

		$screen = WP_Screen::get( 'nav-menus.php' );
		$screen->render_screen_options( array( 'wrap' => false ) );
	}

	public function wp_nav_menu_manage_columns() {
		_deprecated_function( __METHOD__, '4.5.0', 'wp_nav_menu_manage_columns' );
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
		return wp_nav_menu_manage_columns();
	}

	protected function content_template() {
		?>
		<li class="panel-meta customize-info accordion-section <# if ( ! data.description ) { #> cannot-expand<# } #>">
			<button type="button" class="customize-panel-back" tabindex="-1">
				<span class="screen-reader-text">返回</span>
			</button>
			<div class="accordion-section-title">
				<span class="preview-notice">
					<?php printf( '您正在自定义 %s', '<strong class="panel-title">{{ data.title }}</strong>' ); ?>
				</span>
				<button type="button" class="customize-help-toggle dashicons dashicons-editor-help" aria-expanded="false">
					<span class="screen-reader-text">帮助</span>
				</button>
				<button type="button" class="customize-screen-options-toggle" aria-expanded="false">
					<span class="screen-reader-text">菜单选项</span>
				</button>
			</div>
			<# if ( data.description ) { #>
			<div class="description customize-panel-description">{{{ data.description }}}</div>
			<# } #>
			<div id="screen-options-wrap">
				<?php $this->render_screen_options(); ?>
			</div>
		</li>
	<?php
	}
}
