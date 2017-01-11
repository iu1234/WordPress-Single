<?php

class WP_Customize_Site_Icon_Control extends WP_Customize_Cropped_Image_Control {

	public $type = 'site_icon';

	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );
		add_action( 'customize_controls_print_styles', 'wp_site_icon', 99 );
	}

	public function content_template() {
		?>
		<label for="{{ data.settings['default'] }}-button">
			<# if ( data.label ) { #>
				<span class="customize-control-title">{{ data.label }}</span>
			<# } #>
			<# if ( data.description ) { #>
				<span class="description customize-control-description">{{{ data.description }}}</span>
			<# } #>
		</label>

		<# if ( data.attachment && data.attachment.id ) { #>
		<# if ( data.attachment.sizes ) { #>
		<div class="current">
			<div class="container">
				<div class="site-icon-preview">
					<div class="favicon-preview">
						<img src="<?php echo esc_url( admin_url( 'images/browser.png' ) ); ?>" class="browser-preview" width="182" alt="Browser interface preview" />
						<div class="favicon">
							<img id="preview-favicon" src="{{ data.attachment.sizes.full.url }}" alt="Preview as a browser icon"/>
						</div>
						<span class="browser-title"><?php bloginfo( 'name' ); ?></span>
					</div>
					<img class="app-icon-preview" src="{{ data.attachment.sizes.full.url }}" alt="Preview as an app icon"/>
				</div>
			</div>
		</div>
		<# } #>
		<div class="actions">
			<# if ( data.canUpload ) { #>
				<button type="button" class="button remove-button"><?php echo $this->button_labels['remove']; ?></button>
				<button type="button" class="button upload-button" id="{{ data.settings['default'] }}-button"><?php echo $this->button_labels['change']; ?></button>
				<div style="clear:both"></div>
			<# } #>
		</div>
		<# } else { #>
		<div class="current">
			<div class="container">
				<div class="placeholder">
					<div class="inner">
						<span><?php echo $this->button_labels['placeholder']; ?></span>
					</div>
				</div>
			</div>
		</div>
		<div class="actions">
			<# if ( data.defaultAttachment ) { #>
				<button type="button" class="button default-button"><?php echo $this->button_labels['default']; ?></button>
			<# } #>
			<# if ( data.canUpload ) { #>
				<button type="button" class="button upload-button" id="{{ data.settings['default'] }}-button"><?php echo $this->button_labels['select']; ?></button>
			<# } #>
			<div style="clear:both"></div>
		</div>
		<# } #>
		<?php
	}
}
