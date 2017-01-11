<?php

class WP_Customize_Theme_Control extends WP_Customize_Control {

	public $type = 'theme';

	public $theme;

	public function to_json() {
		parent::to_json();
		$this->json['theme'] = $this->theme;
	}

	public function render_content() {}

	public function content_template() {
		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$active_url  = esc_url( remove_query_arg( 'theme', $current_url ) );
		$preview_url = esc_url( add_query_arg( 'theme', '__THEME__', $current_url ) );
		$preview_url = str_replace( '__THEME__', '{{ data.theme.id }}', $preview_url );
		?>
		<# if ( data.theme.isActiveTheme ) { #>
			<div class="theme active" tabindex="0" data-preview-url="<?php echo esc_attr( $active_url ); ?>" aria-describedby="{{ data.theme.id }}-action {{ data.theme.id }}-name">
		<# } else { #>
			<div class="theme" tabindex="0" data-preview-url="<?php echo esc_attr( $preview_url ); ?>" aria-describedby="{{ data.theme.id }}-action {{ data.theme.id }}-name">
		<# } #>

			<# if ( data.theme.screenshot[0] ) { #>
				<div class="theme-screenshot">
					<img data-src="{{ data.theme.screenshot[0] }}" alt="" />
				</div>
			<# } else { #>
				<div class="theme-screenshot blank"></div>
			<# } #>

			<# if ( data.theme.isActiveTheme ) { #>
				<span class="more-details" id="{{ data.theme.id }}-action">Customize</span>
			<# } else { #>
				<span class="more-details" id="{{ data.theme.id }}-action">Live Preview</span>
			<# } #>
			<div class="theme-author"><?php printf( 'By %s', '{{ data.theme.author }}' ); ?></div>
			<# if ( data.theme.isActiveTheme ) { #>
				<h3 class="theme-name" id="{{ data.theme.id }}-name">
					<?php
					printf( '<span>Active:</span> %s', '{{{ data.theme.name }}}' );
					?>
				</h3>
			<# } else { #>
				<h3 class="theme-name" id="{{ data.theme.id }}-name">{{{ data.theme.name }}}</h3>
				<div class="theme-actions">
					<button type="button" class="button theme-details">Theme Details</button>
				</div>
			<# } #>
		</div>
	<?php
	}
}
