<?php
/**
 * Customize API: WP_Customize_Image_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Image_Control extends WP_Customize_Upload_Control {
	public $type = 'image';
	public $mime_type = 'image';

	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );

		$this->button_labels = wp_parse_args( $this->button_labels, array(
			'select'       => 'Select Image',
			'change'       => 'Change Image',
			'remove'       => 'Remove',
			'default'      => 'Default',
			'placeholder'  => 'No image selected',
			'frame_title'  => 'Select Image',
			'frame_button' => 'Choose Image',
		) );
	}

	public function prepare_control() {}

	public function add_tab( $id, $label, $callback ) {}

	public function remove_tab( $id ) {}

	public function print_tab_image( $url, $thumbnail_url = null ) {}
}
