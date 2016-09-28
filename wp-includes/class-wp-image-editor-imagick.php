<?php
/**
 * WordPress Imagick Image Editor
 *
 * @package WordPress
 * @subpackage Image_Editor
 */

class WP_Image_Editor_Imagick extends WP_Image_Editor {

	protected $image;

	public function __destruct() {
		if ( $this->image instanceof Imagick ) {
			$this->image->clear();
			$this->image->destroy();
		}
	}

	public static function test( $args = array() ) {

		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick', false ) || ! class_exists( 'ImagickPixel', false ) )
			return false;

		if ( version_compare( phpversion( 'imagick' ), '2.2.0', '<' ) )
			return false;

		$required_methods = array(
			'clear',
			'destroy',
			'valid',
			'getimage',
			'writeimage',
			'getimageblob',
			'getimagegeometry',
			'getimageformat',
			'setimageformat',
			'setimagecompression',
			'setimagecompressionquality',
			'setimagepage',
			'setoption',
			'scaleimage',
			'cropimage',
			'rotateimage',
			'flipimage',
			'flopimage',
		);

		// Now, test for deep requirements within Imagick.
		if ( ! defined( 'imagick::COMPRESSION_JPEG' ) )
			return false;

		$class_methods = array_map( 'strtolower', get_class_methods( 'Imagick' ) );
		if ( array_diff( $required_methods, $class_methods ) ) {
			return false;
		}

		// HHVM Imagick does not support loading from URL, so fail to allow fallback to GD.
		if ( defined( 'HHVM_VERSION' ) && isset( $args['path'] ) && preg_match( '|^https?://|', $args['path'] ) ) {
			return false;
		}

		return true;
	}

	public static function supports_mime_type( $mime_type ) {
		$imagick_extension = strtoupper( self::get_extension( $mime_type ) );

		if ( ! $imagick_extension )
			return false;

		if ( ! method_exists( 'Imagick', 'setIteratorIndex' ) && $mime_type != 'image/jpeg' )
				return false;

		try {
			return ( (bool) @Imagick::queryFormats( $imagick_extension ) );
		}
		catch ( Exception $e ) {
			return false;
		}
	}

	public function load() {
		if ( $this->image instanceof Imagick )
			return true;

		if ( ! is_file( $this->file ) && ! preg_match( '|^https?://|', $this->file ) )
			return new WP_Error( 'error_loading_image', 'File doesn&#8217;t exist?', $this->file );

		@ini_set( 'memory_limit', apply_filters( 'image_memory_limit', WP_MAX_MEMORY_LIMIT ) );

		try {
			$this->image = new Imagick( $this->file );

			if ( ! $this->image->valid() )
				return new WP_Error( 'invalid_image', 'File is not an image.', $this->file);

			if ( is_callable( array( $this->image, 'setIteratorIndex' ) ) )
				$this->image->setIteratorIndex(0);

			$this->mime_type = $this->get_mime_type( $this->image->getImageFormat() );
		}
		catch ( Exception $e ) {
			return new WP_Error( 'invalid_image', $e->getMessage(), $this->file );
		}

		$updated_size = $this->update_size();
		if ( is_wp_error( $updated_size ) ) {
			return $updated_size;
		}

		return $this->set_quality();
	}

	public function set_quality( $quality = null ) {
		$quality_result = parent::set_quality( $quality );
		if ( is_wp_error( $quality_result ) ) {
			return $quality_result;
		} else {
			$quality = $this->get_quality();
		}

		try {
			if ( 'image/jpeg' == $this->mime_type ) {
				$this->image->setImageCompressionQuality( $quality );
				$this->image->setImageCompression( imagick::COMPRESSION_JPEG );
			}
			else {
				$this->image->setImageCompressionQuality( $quality );
			}
		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_quality_error', $e->getMessage() );
		}

		return true;
	}

	protected function update_size( $width = null, $height = null ) {
		$size = null;
		if ( !$width || !$height ) {
			try {
				$size = $this->image->getImageGeometry();
			}
			catch ( Exception $e ) {
				return new WP_Error( 'invalid_image', 'Could not read image size.', $this->file );
			}
		}

		if ( ! $width )
			$width = $size['width'];

		if ( ! $height )
			$height = $size['height'];

		return parent::update_size( $width, $height );
	}

	public function resize( $max_w, $max_h, $crop = false ) {
		if ( ( $this->size['width'] == $max_w ) && ( $this->size['height'] == $max_h ) )
			return true;

		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims )
			return new WP_Error( 'error_getting_dimensions', 'Could not calculate resized image dimensions' );
		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		if ( $crop ) {
			return $this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h );
		}

		$thumb_result = $this->thumbnail_image( $dst_w, $dst_h );
		if ( is_wp_error( $thumb_result ) ) {
			return $thumb_result;
		}

		return $this->update_size( $dst_w, $dst_h );
	}

	protected function thumbnail_image( $dst_w, $dst_h, $filter_name = 'FILTER_TRIANGLE', $strip_meta = true ) {
		$allowed_filters = array(
			'FILTER_POINT',
			'FILTER_BOX',
			'FILTER_TRIANGLE',
			'FILTER_HERMITE',
			'FILTER_HANNING',
			'FILTER_HAMMING',
			'FILTER_BLACKMAN',
			'FILTER_GAUSSIAN',
			'FILTER_QUADRATIC',
			'FILTER_CUBIC',
			'FILTER_CATROM',
			'FILTER_MITCHELL',
			'FILTER_LANCZOS',
			'FILTER_BESSEL',
			'FILTER_SINC',
		);

		if ( in_array( $filter_name, $allowed_filters ) && defined( 'Imagick::' . $filter_name ) ) {
			$filter = constant( 'Imagick::' . $filter_name );
		} else {
			$filter = defined( 'Imagick::FILTER_TRIANGLE' ) ? Imagick::FILTER_TRIANGLE : false;
		}

		if ( apply_filters( 'image_strip_meta', $strip_meta ) ) {
			$this->strip_meta();
		}

		try {
			if ( is_callable( array( $this->image, 'sampleImage' ) ) ) {
				$resize_ratio = ( $dst_w / $this->size['width'] ) * ( $dst_h / $this->size['height'] );
				$sample_factor = 5;

				if ( $resize_ratio < .111 && ( $dst_w * $sample_factor > 128 && $dst_h * $sample_factor > 128 ) ) {
					$this->image->sampleImage( $dst_w * $sample_factor, $dst_h * $sample_factor );
				}
			}

			if ( is_callable( array( $this->image, 'resizeImage' ) ) && $filter ) {
				$this->image->setOption( 'filter:support', '2.0' );
				$this->image->resizeImage( $dst_w, $dst_h, $filter, 1 );
			} else {
				$this->image->scaleImage( $dst_w, $dst_h );
			}

			// Set appropriate quality settings after resizing.
			if ( 'image/jpeg' == $this->mime_type ) {
				if ( is_callable( array( $this->image, 'unsharpMaskImage' ) ) ) {
					$this->image->unsharpMaskImage( 0.25, 0.25, 8, 0.065 );
				}

				$this->image->setOption( 'jpeg:fancy-upsampling', 'off' );
			}

			if ( 'image/png' === $this->mime_type ) {
				$this->image->setOption( 'png:compression-filter', '5' );
				$this->image->setOption( 'png:compression-level', '9' );
				$this->image->setOption( 'png:compression-strategy', '1' );
				$this->image->setOption( 'png:exclude-chunk', 'all' );
			}

			if ( is_callable( array( $this->image, 'getImageAlphaChannel' ) )
				&& is_callable( array( $this->image, 'setImageAlphaChannel' ) )
				&& defined( 'Imagick::ALPHACHANNEL_UNDEFINED' )
				&& defined( 'Imagick::ALPHACHANNEL_OPAQUE' )
			) {
				if ( $this->image->getImageAlphaChannel() === Imagick::ALPHACHANNEL_UNDEFINED ) {
					$this->image->setImageAlphaChannel( Imagick::ALPHACHANNEL_OPAQUE );
				}
			}

			// Limit the bit depth of resized images to 8 bits per channel.
			if ( is_callable( array( $this->image, 'getImageDepth' ) ) && is_callable( array( $this->image, 'setImageDepth' ) ) ) {
				if ( 8 < $this->image->getImageDepth() ) {
					$this->image->setImageDepth( 8 );
				}
			}

			if ( is_callable( array( $this->image, 'setInterlaceScheme' ) ) && defined( 'Imagick::INTERLACE_NO' ) ) {
				$this->image->setInterlaceScheme( Imagick::INTERLACE_NO );
			}

		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_resize_error', $e->getMessage() );
		}
	}

	public function multi_resize( $sizes ) {
		$metadata = array();
		$orig_size = $this->size;
		$orig_image = $this->image->getImage();

		foreach ( $sizes as $size => $size_data ) {
			if ( ! $this->image )
				$this->image = $orig_image->getImage();

			if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
				continue;
			}

			if ( ! isset( $size_data['width'] ) ) {
				$size_data['width'] = null;
			}
			if ( ! isset( $size_data['height'] ) ) {
				$size_data['height'] = null;
			}

			if ( ! isset( $size_data['crop'] ) ) {
				$size_data['crop'] = false;
			}

			$resize_result = $this->resize( $size_data['width'], $size_data['height'], $size_data['crop'] );
			$duplicate = ( ( $orig_size['width'] == $size_data['width'] ) && ( $orig_size['height'] == $size_data['height'] ) );

			if ( ! is_wp_error( $resize_result ) && ! $duplicate ) {
				$resized = $this->_save( $this->image );

				$this->image->clear();
				$this->image->destroy();
				$this->image = null;

				if ( ! is_wp_error( $resized ) && $resized ) {
					unset( $resized['path'] );
					$metadata[$size] = $resized;
				}
			}

			$this->size = $orig_size;
		}

		$this->image = $orig_image;

		return $metadata;
	}

	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		if ( $src_abs ) {
			$src_w -= $src_x;
			$src_h -= $src_y;
		}

		try {
			$this->image->cropImage( $src_w, $src_h, $src_x, $src_y );
			$this->image->setImagePage( $src_w, $src_h, 0, 0);

			if ( $dst_w || $dst_h ) {
				// If destination width/height isn't specified, use same as
				// width/height from source.
				if ( ! $dst_w )
					$dst_w = $src_w;
				if ( ! $dst_h )
					$dst_h = $src_h;

				$thumb_result = $this->thumbnail_image( $dst_w, $dst_h );
				if ( is_wp_error( $thumb_result ) ) {
					return $thumb_result;
				}

				return $this->update_size();
			}
		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_crop_error', $e->getMessage() );
		}
		return $this->update_size();
	}

	public function rotate( $angle ) {

		try {
			$this->image->rotateImage( new ImagickPixel('none'), 360-$angle );

			// Since this changes the dimensions of the image, update the size.
			$result = $this->update_size();
			if ( is_wp_error( $result ) )
				return $result;

			$this->image->setImagePage( $this->size['width'], $this->size['height'], 0, 0 );
		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_rotate_error', $e->getMessage() );
		}
		return true;
	}

	public function flip( $horz, $vert ) {
		try {
			if ( $horz )
				$this->image->flipImage();

			if ( $vert )
				$this->image->flopImage();
		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_flip_error', $e->getMessage() );
		}
		return true;
	}

	public function save( $destfilename = null, $mime_type = null ) {
		$saved = $this->_save( $this->image, $destfilename, $mime_type );

		if ( ! is_wp_error( $saved ) ) {
			$this->file = $saved['path'];
			$this->mime_type = $saved['mime-type'];

			try {
				$this->image->setImageFormat( strtoupper( $this->get_extension( $this->mime_type ) ) );
			}
			catch ( Exception $e ) {
				return new WP_Error( 'image_save_error', $e->getMessage(), $this->file );
			}
		}

		return $saved;
	}

	protected function _save( $image, $filename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename )
			$filename = $this->generate_filename( null, null, $extension );

		try {
			// Store initial Format
			$orig_format = $this->image->getImageFormat();

			$this->image->setImageFormat( strtoupper( $this->get_extension( $mime_type ) ) );
			$this->make_image( $filename, array( $image, 'writeImage' ), array( $filename ) );

			// Reset original Format
			$this->image->setImageFormat( $orig_format );
		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
		}

		// Set correct file permissions
		$stat = stat( dirname( $filename ) );
		$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
		@ chmod( $filename, $perms );

		/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
		return array(
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}

	public function stream( $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );

		try {
			$this->image->setImageFormat( strtoupper( $extension ) );
			header( "Content-Type: $mime_type" );
			print $this->image->getImageBlob();
			$this->image->setImageFormat( $this->get_extension( $this->mime_type ) );
		}
		catch ( Exception $e ) {
			return new WP_Error( 'image_stream_error', $e->getMessage() );
		}
		return true;
	}

	protected function strip_meta() {
		if ( ! is_callable( array( $this->image, 'getImageProfiles' ) ) ) {
			return new WP_Error( 'image_strip_meta_error', sprintf( '%s is required to strip image meta.', '<code>Imagick::getImageProfiles()</code>' ) );
		}

		if ( ! is_callable( array( $this->image, 'removeImageProfile' ) ) ) {
			return new WP_Error( 'image_strip_meta_error', sprintf( '%s is required to strip image meta.', '<code>Imagick::removeImageProfile()</code>' ) );
		}

		$protected_profiles = array(
			'icc',
			'icm',
			'iptc',
			'exif',
			'xmp',
		);

		try {
			foreach ( $this->image->getImageProfiles( '*', true ) as $key => $value ) {
				if ( ! in_array( $key, $protected_profiles ) ) {
					$this->image->removeImageProfile( $key );
				}
			}

		} catch ( Exception $e ) {
			return new WP_Error( 'image_strip_meta_error', $e->getMessage() );
		}

		return true;
	}

}
