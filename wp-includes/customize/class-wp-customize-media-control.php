<?php
/**
 * Customize API: WP_Customize_Media_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Media_Control extends WP_Customize_Control {

	public $type = 'media';

	public $mime_type = '';

	public $button_labels = array();

	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );

		if ( ! ( $this instanceof WP_Customize_Image_Control ) ) {
			$this->button_labels = wp_parse_args( $this->button_labels, array(
				'select'       => 'Select File',
				'change'       => 'Change File',
				'default'      => 'Default',
				'remove'       => 'Remove',
				'placeholder'  => 'No file selected',
				'frame_title'  => 'Select File',
				'frame_button' => 'Choose File',
			) );
		}
	}

	public function enqueue() {
		wp_enqueue_media();
	}

	public function to_json() {
		parent::to_json();
		$this->json['label'] = html_entity_decode( $this->label, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$this->json['mime_type'] = $this->mime_type;
		$this->json['button_labels'] = $this->button_labels;
		$this->json['canUpload'] = current_user_can( 'upload_files' );

		$value = $this->value();

		if ( is_object( $this->setting ) ) {
			if ( $this->setting->default ) {
				$type = in_array( substr( $this->setting->default, -3 ), array( 'jpg', 'png', 'gif', 'bmp' ) ) ? 'image' : 'document';
				$default_attachment = array(
					'id' => 1,
					'url' => $this->setting->default,
					'type' => $type,
					'icon' => wp_mime_type_icon( $type ),
					'title' => basename( $this->setting->default ),
				);

				if ( 'image' === $type ) {
					$default_attachment['sizes'] = array(
						'full' => array( 'url' => $this->setting->default ),
					);
				}

				$this->json['defaultAttachment'] = $default_attachment;
			}

			if ( $value && $this->setting->default && $value === $this->setting->default ) {
				$this->json['attachment'] = $this->json['defaultAttachment'];
			} elseif ( $value ) {
				$this->json['attachment'] = wp_prepare_attachment_for_js( $value );
			}
		}
	}

	public function render_content() {}

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
			<div class="current">
				<div class="container">
					<div class="attachment-media-view attachment-media-view-{{ data.attachment.type }} {{ data.attachment.orientation }}">
						<div class="thumbnail thumbnail-{{ data.attachment.type }}">
							<# if ( 'image' === data.attachment.type && data.attachment.sizes && data.attachment.sizes.medium ) { #>
								<img class="attachment-thumb" src="{{ data.attachment.sizes.medium.url }}" draggable="false" alt="" />
							<# } else if ( 'image' === data.attachment.type && data.attachment.sizes && data.attachment.sizes.full ) { #>
								<img class="attachment-thumb" src="{{ data.attachment.sizes.full.url }}" draggable="false" alt="" />
							<# } else if ( 'audio' === data.attachment.type ) { #>
								<# if ( data.attachment.image && data.attachment.image.src && data.attachment.image.src !== data.attachment.icon ) { #>
									<img src="{{ data.attachment.image.src }}" class="thumbnail" draggable="false" alt="" />
								<# } else { #>
									<img src="{{ data.attachment.icon }}" class="attachment-thumb type-icon" draggable="false" alt="" />
								<# } #>
								<p class="attachment-meta attachment-meta-title">&#8220;{{ data.attachment.title }}&#8221;</p>
								<# if ( data.attachment.album || data.attachment.meta.album ) { #>
								<p class="attachment-meta"><em>{{ data.attachment.album || data.attachment.meta.album }}</em></p>
								<# } #>
								<# if ( data.attachment.artist || data.attachment.meta.artist ) { #>
								<p class="attachment-meta">{{ data.attachment.artist || data.attachment.meta.artist }}</p>
								<# } #>
								<audio style="visibility: hidden" controls class="wp-audio-shortcode" width="100%" preload="none">
									<source type="{{ data.attachment.mime }}" src="{{ data.attachment.url }}"/>
								</audio>
							<# } else if ( 'video' === data.attachment.type ) { #>
								<div class="wp-media-wrapper wp-video">
									<video controls="controls" class="wp-video-shortcode" preload="metadata"
										<# if ( data.attachment.image && data.attachment.image.src !== data.attachment.icon ) { #>poster="{{ data.attachment.image.src }}"<# } #>>
										<source type="{{ data.attachment.mime }}" src="{{ data.attachment.url }}"/>
									</video>
								</div>
							<# } else { #>
								<img class="attachment-thumb type-icon icon" src="{{ data.attachment.icon }}" draggable="false" alt="" />
								<p class="attachment-title">{{ data.attachment.title }}</p>
							<# } #>
						</div>
					</div>
				</div>
			</div>
			<div class="actions">
				<# if ( data.canUpload ) { #>
				<button type="button" class="button remove-button">{{ data.button_labels.remove }}</button>
				<button type="button" class="button upload-button control-focus" id="{{ data.settings['default'] }}-button">{{ data.button_labels.change }}</button>
				<div style="clear:both"></div>
				<# } #>
			</div>
		<# } else { #>
			<div class="current">
				<div class="container">
					<div class="placeholder">
						<div class="inner">
							<span>
								{{ data.button_labels.placeholder }}
							</span>
						</div>
					</div>
				</div>
			</div>
			<div class="actions">
				<# if ( data.defaultAttachment ) { #>
					<button type="button" class="button default-button">{{ data.button_labels['default'] }}</button>
				<# } #>
				<# if ( data.canUpload ) { #>
				<button type="button" class="button upload-button" id="{{ data.settings['default'] }}-button">{{ data.button_labels.select }}</button>
				<# } #>
				<div style="clear:both"></div>
			</div>
		<# } #>
		<?php
	}
}
