<?php
/**
 * Customize API: WP_Customize_Header_Image_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Header_Image_Control extends WP_Customize_Image_Control {
	public $type = 'header';
	public $uploaded_headers;
	public $default_headers;

	public function __construct( $manager ) {
		parent::__construct( $manager, 'header_image', array(
			'label'    => 'Header Image',
			'settings' => array(
				'default' => 'header_image',
				'data'    => 'header_image_data',
			),
			'section'  => 'header_image',
			'removed'  => 'remove-header',
			'get_url'  => 'get_header_image',
		) );

	}

	public function enqueue() {
		wp_enqueue_media();
		wp_enqueue_script( 'customize-views' );

		$this->prepare_control();

		wp_localize_script( 'customize-views', '_wpCustomizeHeader', array(
			'data' => array(
				'width' => absint( get_theme_support( 'custom-header', 'width' ) ),
				'height' => absint( get_theme_support( 'custom-header', 'height' ) ),
				'flex-width' => absint( get_theme_support( 'custom-header', 'flex-width' ) ),
				'flex-height' => absint( get_theme_support( 'custom-header', 'flex-height' ) ),
				'currentImgSrc' => $this->get_current_image_src(),
			),
			'nonces' => array(
				'add' => wp_create_nonce( 'header-add' ),
				'remove' => wp_create_nonce( 'header-remove' ),
			),
			'uploads' => $this->uploaded_headers,
			'defaults' => $this->default_headers
		) );

		parent::enqueue();
	}

	public function prepare_control() {
		global $custom_image_header;
		if ( empty( $custom_image_header ) ) {
			return;
		}

		$custom_image_header->process_default_headers();
		$this->default_headers = $custom_image_header->get_default_header_images();
		$this->uploaded_headers = $custom_image_header->get_uploaded_header_images();
	}

	public function print_header_image_template() {
		?>
		<script type="text/template" id="tmpl-header-choice">
			<# if (data.random) { #>
			<button type="button" class="button display-options random">
				<span class="dashicons dashicons-randomize dice"></span>
				<# if ( data.type === 'uploaded' ) { #>
					Randomize uploaded headers
				<# } else if ( data.type === 'default' ) { #>
					Randomize suggested headers
				<# } #>
			</button>

			<# } else { #>

			<# if (data.type === 'uploaded') { #>
				<button type="button" class="dashicons dashicons-no close"><span class="screen-reader-text">Remove image</span></button>
			<# } #>

			<button type="button" class="choice thumbnail"
				data-customize-image-value="{{{data.header.url}}}"
				data-customize-header-image-data="{{JSON.stringify(data.header)}}">
				<span class="screen-reader-text">Set image</span>
				<img src="{{{data.header.thumbnail_url}}}" alt="{{{data.header.alt_text || data.header.description}}}">
			</button>

			<# } #>
		</script>

		<script type="text/template" id="tmpl-header-current">
			<# if (data.choice) { #>
				<# if (data.random) { #>

			<div class="placeholder">
				<div class="inner">
					<span><span class="dashicons dashicons-randomize dice"></span>
					<# if ( data.type === 'uploaded' ) { #>
						<?php _e( 'Randomizing uploaded headers' ); ?>
					<# } else if ( data.type === 'default' ) { #>
						<?php _e( 'Randomizing suggested headers' ); ?>
					<# } #>
					</span>
				</div>
			</div>

				<# } else { #>

			<img src="{{{data.header.thumbnail_url}}}" alt="{{{data.header.alt_text || data.header.description}}}" tabindex="0"/>

				<# } #>
			<# } else { #>

			<div class="placeholder">
				<div class="inner">
					<span>No image set</span>
				</div>
			</div>

			<# } #>
		</script>
		<?php
	}

	public function get_current_image_src() {
		$src = $this->value();
		if ( isset( $this->get_url ) ) {
			$src = call_user_func( $this->get_url, $src );
			return $src;
		}
	}

	public function render_content() {
		$this->print_header_image_template();
		$visibility = $this->get_current_image_src() ? '' : ' style="display:none" ';
		$width = absint( get_theme_support( 'custom-header', 'width' ) );
		$height = absint( get_theme_support( 'custom-header', 'height' ) );
		?>
		<div class="customize-control-content">
			<p class="customizer-section-intro">
				<?php
				if ( $width && $height ) {
					printf( 'While you can crop images to your liking after clicking <strong>Add new image</strong>, your theme recommends a header size of %s pixels.',
						sprintf( '<strong>%s &times; %s</strong>', $width, $height )
					);
				} elseif ( $width ) {
					printf( 'While you can crop images to your liking after clicking <strong>Add new image</strong>, your theme recommends a header width of %s pixels.',
						sprintf( '<strong>%s</strong>', $width )
					);
				} else {
					printf( 'While you can crop images to your liking after clicking <strong>Add new image</strong>, your theme recommends a header height of %s pixels.',
						sprintf( '<strong>%s</strong>', $height )
					);
				}
				?>
			</p>
			<div class="current">
				<label for="header_image-button">
					<span class="customize-control-title">Current header</span>
				</label>
				<div class="container">
				</div>
			</div>
			<div class="actions">
				<?php if ( current_user_can( 'upload_files' ) ): ?>
				<button type="button"<?php echo $visibility; ?> class="button remove" aria-label="Hide header image">Hide image</button>
				<button type="button" class="button new" id="header_image-button"  aria-label="Add new header image">Add new image</button>
				<div style="clear:both"></div>
				<?php endif; ?>
			</div>
			<div class="choices">
				<span class="customize-control-title header-previously-uploaded">Previously uploaded</span>
				<div class="uploaded">
					<div class="list">
					</div>
				</div>
				<span class="customize-control-title header-default">Suggested</span>
				<div class="default">
					<div class="list">
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
