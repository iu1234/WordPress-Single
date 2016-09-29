<?php
/**
 * Customize API: WP_Customize_Background_Image_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

class WP_Customize_Background_Image_Control extends WP_Customize_Image_Control {
	public $type = 'background';

	public function __construct( $manager ) {
		parent::__construct( $manager, 'background_image', array(
			'label'    => 'Background Image',
			'section'  => 'background_image',
		) );
	}

	public function enqueue() {
		parent::enqueue();

		wp_localize_script( 'customize-controls', '_wpCustomizeBackground', array(
			'nonces' => array(
				'add' => wp_create_nonce( 'background-add' ),
			),
		) );
	}
}
