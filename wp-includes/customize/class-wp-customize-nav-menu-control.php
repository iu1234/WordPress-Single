<?php
/**
 * Customize API: WP_Customize_Nav_Menu_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Nav_Menu_Control extends WP_Customize_Control {

	public $type = 'nav_menu';

	public $setting;

	public function render_content() {}

	public function content_template() {
		?>
		<button type="button" class="button-secondary add-new-menu-item" aria-label="Add or remove menu items" aria-expanded="false" aria-controls="available-menu-items">
			Add Items
		</button>
		<button type="button" class="button-link reorder-toggle" aria-label="Reorder menu items" aria-describedby="reorder-items-desc-{{ data.menu_id }}">
			<span class="reorder">Reorder</span>
			<span class="reorder-done">Done</span>
		</button>
		<p class="screen-reader-text" id="reorder-items-desc-{{ data.menu_id }}">When in reorder mode, additional controls to reorder menu items will be available in the items list above.</p>
		<span class="menu-delete-item">
			<button type="button" class="button-link menu-delete">Delete Menu</button>
		</span>
		<?php if ( current_theme_supports( 'menus' ) ) : ?>
		<ul class="menu-settings">
			<li class="customize-control">
				<span class="customize-control-title">Menu locations</span>
			</li>

			<?php foreach ( get_registered_nav_menus() as $location => $description ) : ?>
			<li class="customize-control customize-control-checkbox assigned-menu-location">
				<label>
					<input type="checkbox" data-menu-id="{{ data.menu_id }}" data-location-id="<?php echo esc_attr( $location ); ?>" class="menu-location" /> <?php echo $description; ?>
					<span class="theme-location-set"><?php
						printf( '(Current: %s)',
							'<span class="current-menu-location-name-' . esc_attr( $location ) . '"></span>'
						);
					?></span>
				</label>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php endif;
	}

	public function json() {
		$exported            = parent::json();
		$exported['menu_id'] = $this->setting->term_id;

		return $exported;
	}
}
