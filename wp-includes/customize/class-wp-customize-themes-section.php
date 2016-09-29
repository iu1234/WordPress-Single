<?php
/**
 * Customize API: WP_Customize_Themes_Section class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Themes_Section extends WP_Customize_Section {

	public $type = 'themes';

	protected function render() {
		$classes = 'accordion-section control-section control-section-' . $this->type;
		?>
		<li id="accordion-section-<?php echo esc_attr( $this->id ); ?>" class="<?php echo esc_attr( $classes ); ?>">
			<h3 class="accordion-section-title">
				<?php
				if ( $this->manager->is_theme_active() ) {
					echo '<span class="customize-action">Active theme</span> ' . $this->title;
				} else {
					echo '<span class="customize-action">Previewing theme</span> ' . $this->title;
				}
				?>

				<?php if ( count( $this->controls ) > 0 ) : ?>
					<button type="button" class="button change-theme" tabindex="0">Change</button>
				<?php endif; ?>
			</h3>
			<div class="customize-themes-panel control-panel-content themes-php">
				<h3 class="accordion-section-title customize-section-title">
					<span class="customize-action">Customizing</span>
					Themes
					<span class="title-count theme-count"><?php echo count( $this->controls ) + 1; ?></span>
				</h3>
				<h3 class="accordion-section-title customize-section-title">
					<?php
					if ( $this->manager->is_theme_active() ) {
						echo '<span class="customize-action">Active theme</span> ' . $this->title;
					} else {
						echo '<span class="customize-action">Previewing theme</span> ' . $this->title;
					}
					?>
					<button type="button" class="button customize-theme">Customize</button>
				</h3>

				<div class="theme-overlay" tabindex="0" role="dialog" aria-label="Theme Details"></div>

				<div id="customize-container"></div>
				<?php if ( count( $this->controls ) > 4 ) : ?>
					<p><label for="themes-filter">
						<span class="screen-reader-text">Search installed themes&hellip;</span>
						<input type="text" id="themes-filter" placeholder="Search installed themes&hellip;" />
					</label></p>
				<?php endif; ?>
				<div class="theme-browser rendered">
					<ul class="themes accordion-section-content">
					</ul>
				</div>
			</div>
		</li>
<?php }
}
