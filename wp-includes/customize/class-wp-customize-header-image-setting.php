<?php
/**
 * Customize API: WP_Customize_Header_Image_Setting class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

final class WP_Customize_Header_Image_Setting extends WP_Customize_Setting {
	public $id = 'header_image_data';

	public function update( $value ) {
		global $custom_image_header;

		if ( ! $value )
			$value = $this->manager->get_setting('header_image')->post_value();

		if ( is_array( $value ) && isset( $value['choice'] ) )
			$custom_image_header->set_header_image( $value['choice'] );
		else
			$custom_image_header->set_header_image( $value );
	}
}
