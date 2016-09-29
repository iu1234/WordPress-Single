<?php

class WP_Site_Icon {

	public $min_size  = 512;

	public $page_crop = 512;

	public $site_icon_sizes = array(
		270,
		192,
		180,
		32,
	);

	public function __construct() {
		add_action( 'delete_attachment', array( $this, 'delete_attachment_data' ) );
		add_filter( 'get_post_metadata', array( $this, 'get_post_metadata' ), 10, 4 );
	}

	public function create_attachment_object( $cropped, $parent_attachment_id ) {
		$parent     = get_post( $parent_attachment_id );
		$parent_url = wp_get_attachment_url( $parent->ID );
		$url        = str_replace( basename( $parent_url ), basename( $cropped ), $parent_url );

		$size       = @getimagesize( $cropped );
		$image_type = ( $size ) ? $size['mime'] : 'image/jpeg';

		$object = array(
			'ID'             => $parent_attachment_id,
			'post_title'     => basename( $cropped ),
			'post_content'   => $url,
			'post_mime_type' => $image_type,
			'guid'           => $url,
			'context'        => 'site-icon'
		);

		return $object;
	}

	public function insert_attachment( $object, $file ) {
		$attachment_id = wp_insert_attachment( $object, $file );
		$metadata      = wp_generate_attachment_metadata( $attachment_id, $file );

		$metadata = apply_filters( 'site_icon_attachment_metadata', $metadata );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	public function additional_sizes( $sizes = array() ) {
		$only_crop_sizes = array();

		$this->site_icon_sizes = apply_filters( 'site_icon_image_sizes', $this->site_icon_sizes );

		natsort( $this->site_icon_sizes );
		$this->site_icon_sizes = array_reverse( $this->site_icon_sizes );

		foreach ( $sizes as $name => $size_array ) {
			if ( isset( $size_array['crop'] ) ) {
				$only_crop_sizes[ $name ] = $size_array;
			}
		}

		foreach ( $this->site_icon_sizes as $size ) {
			if ( $size < $this->min_size ) {
				$only_crop_sizes[ 'site_icon-' . $size ] = array(
					'width ' => $size,
					'height' => $size,
					'crop'   => true,
				);
			}
		}

		return $only_crop_sizes;
	}

	public function intermediate_image_sizes( $sizes = array() ) {
		$this->site_icon_sizes = apply_filters( 'site_icon_image_sizes', $this->site_icon_sizes );
		foreach ( $this->site_icon_sizes as $size ) {
			$sizes[] = 'site_icon-' . $size;
		}
		return $sizes;
	}

	public function delete_attachment_data( $post_id ) {
		$site_icon_id = get_option( 'site_icon' );

		if ( $site_icon_id && $post_id == $site_icon_id ) {
			delete_option( 'site_icon' );
		}
	}

	public function get_post_metadata( $value, $post_id, $meta_key, $single ) {
		if ( $single && '_wp_attachment_backup_sizes' === $meta_key ) {
			$site_icon_id = get_option( 'site_icon' );

			if ( $post_id == $site_icon_id ) {
				add_filter( 'intermediate_image_sizes', array( $this, 'intermediate_image_sizes' ) );
			}
		}

		return $value;
	}
}

$GLOBALS['wp_site_icon'] = new WP_Site_Icon;
