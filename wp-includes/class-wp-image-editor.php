<?php
/**
 * Base WordPress Image Editor
 *
 * @package WordPress
 * @subpackage Image_Editor
 */

abstract class WP_Image_Editor {
	protected $file = null;
	protected $size = null;
	protected $mime_type = null;
	protected $default_mime_type = 'image/jpeg';
	protected $quality = false;
	protected $default_quality = 82;

	public function __construct( $file ) {
		$this->file = $file;
	}

	public static function test( $args = array() ) {
		return false;
	}

	public static function supports_mime_type( $mime_type ) {
		return false;
	}

	abstract public function load();

	abstract public function save( $destfilename = null, $mime_type = null );

	abstract public function resize( $max_w, $max_h, $crop = false );

	abstract public function multi_resize( $sizes );

	abstract public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false );

	abstract public function rotate( $angle );

	abstract public function flip( $horz, $vert );

	abstract public function stream( $mime_type = null );

	public function get_size() {
		return $this->size;
	}

	protected function update_size( $width = null, $height = null ) {
		$this->size = array(
			'width' => (int) $width,
			'height' => (int) $height
		);
		return true;
	}

	public function get_quality() {
		if ( ! $this->quality ) {
			$this->set_quality();
		}

		return $this->quality;
	}

	public function set_quality( $quality = null ) {
		if ( null === $quality ) {
			$quality = apply_filters( 'wp_editor_set_quality', $this->default_quality, $this->mime_type );

			if ( 'image/jpeg' == $this->mime_type ) {
				$quality = apply_filters( 'jpeg_quality', $quality, 'image_resize' );
			}

			if ( $quality < 0 || $quality > 100 ) {
				$quality = $this->default_quality;
			}
		}

		// Allow 0, but squash to 1 due to identical images in GD, and for backwards compatibility.
		if ( 0 === $quality ) {
			$quality = 1;
		}

		if ( ( $quality >= 1 ) && ( $quality <= 100 ) ) {
			$this->quality = $quality;
			return true;
		} else {
			return new WP_Error( 'invalid_image_quality', 'Attempted to set image quality outside of the range [1,100].' );
		}
	}

	protected function get_output_format( $filename = null, $mime_type = null ) {
		$new_ext = null;

		// By default, assume specified type takes priority
		if ( $mime_type ) {
			$new_ext = $this->get_extension( $mime_type );
		}

		if ( $filename ) {
			$file_ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$file_mime = $this->get_mime_type( $file_ext );
		}
		else {
			// If no file specified, grab editor's current extension and mime-type.
			$file_ext = strtolower( pathinfo( $this->file, PATHINFO_EXTENSION ) );
			$file_mime = $this->mime_type;
		}

		// Check to see if specified mime-type is the same as type implied by
		// file extension.  If so, prefer extension from file.
		if ( ! $mime_type || ( $file_mime == $mime_type ) ) {
			$mime_type = $file_mime;
			$new_ext = $file_ext;
		}

		if ( ! $this->supports_mime_type( $mime_type ) ) {
			$mime_type = apply_filters( 'image_editor_default_mime_type', $this->default_mime_type );
			$new_ext = $this->get_extension( $mime_type );
		}

		if ( $filename ) {
			$ext = '';
			$info = pathinfo( $filename );
			$dir  = $info['dirname'];

			if ( isset( $info['extension'] ) )
				$ext = $info['extension'];

			$filename = trailingslashit( $dir ) . wp_basename( $filename, ".$ext" ) . ".{$new_ext}";
		}

		return array( $filename, $new_ext, $mime_type );
	}

	public function generate_filename( $suffix = null, $dest_path = null, $extension = null ) {
		// $suffix will be appended to the destination filename, just before the extension
		if ( ! $suffix )
			$suffix = $this->get_suffix();

		$info = pathinfo( $this->file );
		$dir  = $info['dirname'];
		$ext  = $info['extension'];

		$name = wp_basename( $this->file, ".$ext" );
		$new_ext = strtolower( $extension ? $extension : $ext );

		if ( ! is_null( $dest_path ) && $_dest_path = realpath( $dest_path ) )
			$dir = $_dest_path;

		return trailingslashit( $dir ) . "{$name}-{$suffix}.{$new_ext}";
	}

	public function get_suffix() {
		if ( ! $this->get_size() )
			return false;

		return "{$this->size['width']}x{$this->size['height']}";
	}

	protected function make_image( $filename, $function, $arguments ) {
		if ( $stream = wp_is_stream( $filename ) ) {
			ob_start();
		} else {
			// The directory containing the original file may no longer exist when using a replication plugin.
			wp_mkdir_p( dirname( $filename ) );
		}

		$result = call_user_func_array( $function, $arguments );

		if ( $result && $stream ) {
			$contents = ob_get_contents();

			$fp = fopen( $filename, 'w' );

			if ( ! $fp )
				return false;

			fwrite( $fp, $contents );
			fclose( $fp );
		}

		if ( $stream ) {
			ob_end_clean();
		}

		return $result;
	}

	protected static function get_mime_type( $extension = null ) {
		if ( ! $extension )
			return false;

		$mime_types = wp_get_mime_types();
		$extensions = array_keys( $mime_types );

		foreach ( $extensions as $_extension ) {
			if ( preg_match( "/{$extension}/i", $_extension ) ) {
				return $mime_types[$_extension];
			}
		}

		return false;
	}

	protected static function get_extension( $mime_type = null ) {
		$extensions = explode( '|', array_search( $mime_type, wp_get_mime_types() ) );

		if ( empty( $extensions[0] ) )
			return false;

		return $extensions[0];
	}
}

