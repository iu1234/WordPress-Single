<?php
/**
 * WordPress API for media display.
 *
 * @package WordPress
 * @subpackage Media
 */

function image_constrain_size_for_editor( $width, $height, $size = 'medium', $context = null ) {
	global $content_width, $_wp_additional_image_sizes;

	if ( ! $context )
		$context = is_admin() ? 'edit' : 'display';

	if ( is_array($size) ) {
		$max_width = $size[0];
		$max_height = $size[1];
	}
	elseif ( $size == 'thumb' || $size == 'thumbnail' ) {
		$max_width = intval(get_option('thumbnail_size_w'));
		$max_height = intval(get_option('thumbnail_size_h'));
		if ( !$max_width && !$max_height ) {
			$max_width = 128;
			$max_height = 96;
		}
	}
	elseif ( $size == 'medium' ) {
		$max_width = intval(get_option('medium_size_w'));
		$max_height = intval(get_option('medium_size_h'));

	}
	elseif ( $size == 'medium_large' ) {
		$max_width = intval( get_option( 'medium_large_size_w' ) );
		$max_height = intval( get_option( 'medium_large_size_h' ) );

		if ( intval( $content_width ) > 0 ) {
			$max_width = min( intval( $content_width ), $max_width );
		}
	}
	elseif ( $size == 'large' ) {
		$max_width = intval(get_option('large_size_w'));
		$max_height = intval(get_option('large_size_h'));
		if ( intval($content_width) > 0 ) {
			$max_width = min( intval($content_width), $max_width );
		}
	} elseif ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) && in_array( $size, array_keys( $_wp_additional_image_sizes ) ) ) {
		$max_width = intval( $_wp_additional_image_sizes[$size]['width'] );
		$max_height = intval( $_wp_additional_image_sizes[$size]['height'] );
		if ( intval($content_width) > 0 && 'edit' == $context )
			$max_width = min( intval($content_width), $max_width );
	} else {
		$max_width = $width;
		$max_height = $height;
	}

	list( $max_width, $max_height ) = apply_filters( 'editor_max_image_size', array( $max_width, $max_height ), $size, $context );

	return wp_constrain_dimensions( $width, $height, $max_width, $max_height );
}

function image_hwstring( $width, $height ) {
	$out = '';
	if ($width)
		$out .= 'width="'.intval($width).'" ';
	if ($height)
		$out .= 'height="'.intval($height).'" ';
	return $out;
}

function image_downsize( $id, $size = 'medium' ) {

	if ( !wp_attachment_is_image($id) )
		return false;

	if ( $out = apply_filters( 'image_downsize', false, $id, $size ) ) {
		return $out;
	}

	$img_url = wp_get_attachment_url($id);
	$meta = wp_get_attachment_metadata($id);
	$width = $height = 0;
	$is_intermediate = false;
	$img_url_basename = wp_basename($img_url);

	// try for a new style intermediate size
	if ( $intermediate = image_get_intermediate_size($id, $size) ) {
		$img_url = str_replace($img_url_basename, $intermediate['file'], $img_url);
		$width = $intermediate['width'];
		$height = $intermediate['height'];
		$is_intermediate = true;
	}
	elseif ( $size == 'thumbnail' ) {
		// fall back to the old thumbnail
		if ( ($thumb_file = wp_get_attachment_thumb_file($id)) && $info = getimagesize($thumb_file) ) {
			$img_url = str_replace($img_url_basename, wp_basename($thumb_file), $img_url);
			$width = $info[0];
			$height = $info[1];
			$is_intermediate = true;
		}
	}
	if ( !$width && !$height && isset( $meta['width'], $meta['height'] ) ) {
		// any other type: use the real image
		$width = $meta['width'];
		$height = $meta['height'];
	}

	if ( $img_url) {
		// we have the actual image size, but might need to further constrain it if content_width is narrower
		list( $width, $height ) = image_constrain_size_for_editor( $width, $height, $size );

		return array( $img_url, $width, $height, $is_intermediate );
	}
	return false;

}

function add_image_size( $name, $width = 0, $height = 0, $crop = false ) {
	global $_wp_additional_image_sizes;

	$_wp_additional_image_sizes[ $name ] = array(
		'width'  => absint( $width ),
		'height' => absint( $height ),
		'crop'   => $crop,
	);
}

function has_image_size( $name ) {
	global $_wp_additional_image_sizes;

	return isset( $_wp_additional_image_sizes[ $name ] );
}

function remove_image_size( $name ) {
	global $_wp_additional_image_sizes;

	if ( isset( $_wp_additional_image_sizes[ $name ] ) ) {
		unset( $_wp_additional_image_sizes[ $name ] );
		return true;
	}

	return false;
}

function set_post_thumbnail_size( $width = 0, $height = 0, $crop = false ) {
	add_image_size( 'post-thumbnail', $width, $height, $crop );
}

function get_image_tag( $id, $alt, $title, $align, $size = 'medium' ) {

	list( $img_src, $width, $height ) = image_downsize($id, $size);
	$hwstring = image_hwstring($width, $height);

	$title = $title ? 'title="' . esc_attr( $title ) . '" ' : '';

	$class = 'align' . esc_attr($align) .' size-' . esc_attr($size) . ' wp-image-' . $id;

	$class = apply_filters( 'get_image_tag_class', $class, $id, $align, $size );

	$html = '<img src="' . esc_attr($img_src) . '" alt="' . esc_attr($alt) . '" ' . $title . $hwstring . 'class="' . $class . '" />';

	return apply_filters( 'get_image_tag', $html, $id, $alt, $title, $align, $size );
}

function wp_constrain_dimensions( $current_width, $current_height, $max_width = 0, $max_height = 0 ) {
	if ( !$max_width && !$max_height )
		return array( $current_width, $current_height );

	$width_ratio = $height_ratio = 1.0;
	$did_width = $did_height = false;

	if ( $max_width > 0 && $current_width > 0 && $current_width > $max_width ) {
		$width_ratio = $max_width / $current_width;
		$did_width = true;
	}

	if ( $max_height > 0 && $current_height > 0 && $current_height > $max_height ) {
		$height_ratio = $max_height / $current_height;
		$did_height = true;
	}

	// Calculate the larger/smaller ratios
	$smaller_ratio = min( $width_ratio, $height_ratio );
	$larger_ratio  = max( $width_ratio, $height_ratio );

	if ( (int) round( $current_width * $larger_ratio ) > $max_width || (int) round( $current_height * $larger_ratio ) > $max_height ) {
 		// The larger ratio is too big. It would result in an overflow.
		$ratio = $smaller_ratio;
	} else {
		// The larger ratio fits, and is likely to be a more "snug" fit.
		$ratio = $larger_ratio;
	}

	// Very small dimensions may result in 0, 1 should be the minimum.
	$w = max ( 1, (int) round( $current_width  * $ratio ) );
	$h = max ( 1, (int) round( $current_height * $ratio ) );

	if ( $did_width && $w == $max_width - 1 ) {
		$w = $max_width; // Round it up
	}

	if ( $did_height && $h == $max_height - 1 ) {
		$h = $max_height; // Round it up
	}

	return apply_filters( 'wp_constrain_dimensions', array( $w, $h ), $current_width, $current_height, $max_width, $max_height );
}

function image_resize_dimensions( $orig_w, $orig_h, $dest_w, $dest_h, $crop = false ) {

	if ($orig_w <= 0 || $orig_h <= 0)
		return false;
	// at least one of dest_w or dest_h must be specific
	if ($dest_w <= 0 && $dest_h <= 0)
		return false;

	$output = apply_filters( 'image_resize_dimensions', null, $orig_w, $orig_h, $dest_w, $dest_h, $crop );
	if ( null !== $output )
		return $output;

	if ( $crop ) {
		// crop the largest possible portion of the original image that we can size to $dest_w x $dest_h
		$aspect_ratio = $orig_w / $orig_h;
		$new_w = min($dest_w, $orig_w);
		$new_h = min($dest_h, $orig_h);

		if ( ! $new_w ) {
			$new_w = (int) round( $new_h * $aspect_ratio );
		}

		if ( ! $new_h ) {
			$new_h = (int) round( $new_w / $aspect_ratio );
		}

		$size_ratio = max($new_w / $orig_w, $new_h / $orig_h);

		$crop_w = round($new_w / $size_ratio);
		$crop_h = round($new_h / $size_ratio);

		if ( ! is_array( $crop ) || count( $crop ) !== 2 ) {
			$crop = array( 'center', 'center' );
		}

		list( $x, $y ) = $crop;

		if ( 'left' === $x ) {
			$s_x = 0;
		} elseif ( 'right' === $x ) {
			$s_x = $orig_w - $crop_w;
		} else {
			$s_x = floor( ( $orig_w - $crop_w ) / 2 );
		}

		if ( 'top' === $y ) {
			$s_y = 0;
		} elseif ( 'bottom' === $y ) {
			$s_y = $orig_h - $crop_h;
		} else {
			$s_y = floor( ( $orig_h - $crop_h ) / 2 );
		}
	} else {
		// don't crop, just resize using $dest_w x $dest_h as a maximum bounding box
		$crop_w = $orig_w;
		$crop_h = $orig_h;

		$s_x = 0;
		$s_y = 0;

		list( $new_w, $new_h ) = wp_constrain_dimensions( $orig_w, $orig_h, $dest_w, $dest_h );
	}

	// if the resulting image would be the same size or larger we don't want to resize it
	if ( $new_w >= $orig_w && $new_h >= $orig_h && $dest_w != $orig_w && $dest_h != $orig_h ) {
		return false;
	}

	return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );

}

function image_make_intermediate_size( $file, $width, $height, $crop = false ) {
	if ( $width || $height ) {
		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) || is_wp_error( $editor->resize( $width, $height, $crop ) ) )
			return false;

		$resized_file = $editor->save();

		if ( ! is_wp_error( $resized_file ) && $resized_file ) {
			unset( $resized_file['path'] );
			return $resized_file;
		}
	}
	return false;
}

function image_get_intermediate_size( $post_id, $size = 'thumbnail' ) {
	if ( !is_array( $imagedata = wp_get_attachment_metadata( $post_id ) ) )
		return false;

	// get the best one for a specified set of dimensions
	if ( is_array($size) && !empty($imagedata['sizes']) ) {
		$candidates = array();

		foreach ( $imagedata['sizes'] as $_size => $data ) {
			// If there's an exact match to an existing image size, short circuit.
			if ( $data['width'] == $size[0] && $data['height'] == $size[1] ) {
				list( $data['width'], $data['height'] ) = image_constrain_size_for_editor( $data['width'], $data['height'], $size );

				/** This filter is documented in wp-includes/media.php */
				return apply_filters( 'image_get_intermediate_size', $data, $post_id, $size );
			}
			// If it's not an exact match but it's at least the dimensions requested.
			if ( $data['width'] >= $size[0] && $data['height'] >= $size[1] ) {
				$candidates[ $data['width'] * $data['height'] ] = $_size;
			}
		}

		if ( ! empty( $candidates ) ) {
			// find for the smallest image not smaller than the desired size
			ksort( $candidates );
			foreach ( $candidates as $_size ) {
				$data = $imagedata['sizes'][$_size];
				$maybe_cropped = image_resize_dimensions($imagedata['width'], $imagedata['height'], $data['width'], $data['height'], false );
				// If the size doesn't match within one pixel, then it is of a different aspect ratio, so we skip it, unless it's the thumbnail size
				if ( 'thumbnail' != $_size &&
				  ( ! $maybe_cropped
				    || ( $maybe_cropped[4] != $data['width'] && $maybe_cropped[4] + 1 != $data['width'] )
				    || ( $maybe_cropped[5] != $data['height'] && $maybe_cropped[5] + 1 != $data['height'] )
				  ) ) {
				  continue;
				}
				// If we're still here, then we're going to use this size.
				list( $data['width'], $data['height'] ) = image_constrain_size_for_editor( $data['width'], $data['height'], $size );

				/** This filter is documented in wp-includes/media.php */
				return apply_filters( 'image_get_intermediate_size', $data, $post_id, $size );
			}
		}
	}

	if ( is_array($size) || empty($size) || empty($imagedata['sizes'][$size]) )
		return false;

	$data = $imagedata['sizes'][$size];
	// include the full filesystem path of the intermediate file
	if ( empty($data['path']) && !empty($data['file']) ) {
		$file_url = wp_get_attachment_url($post_id);
		$data['path'] = path_join( dirname($imagedata['file']), $data['file'] );
		$data['url'] = path_join( dirname($file_url), $data['file'] );
	}
	return apply_filters( 'image_get_intermediate_size', $data, $post_id, $size );
}

function get_intermediate_image_sizes() {
	global $_wp_additional_image_sizes;
	$image_sizes = array('thumbnail', 'medium', 'medium_large', 'large'); // Standard sizes
	if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) )
		$image_sizes = array_merge( $image_sizes, array_keys( $_wp_additional_image_sizes ) );

	/**
	 * Filter the list of intermediate image sizes.
	 *
	 * @since 2.5.0
	 *
	 * @param array $image_sizes An array of intermediate image sizes. Defaults
	 *                           are 'thumbnail', 'medium', 'medium_large', 'large'.
	 */
	return apply_filters( 'intermediate_image_sizes', $image_sizes );
}

function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail', $icon = false ) {
	// get a thumbnail or intermediate image if there is one
	$image = image_downsize( $attachment_id, $size );
	if ( ! $image ) {
		$src = false;

		if ( $icon && $src = wp_mime_type_icon( $attachment_id ) ) {
			/** This filter is documented in wp-includes/post.php */
			$icon_dir = apply_filters( 'icon_dir', ABSPATH . WPINC . '/images/media' );

			$src_file = $icon_dir . '/' . wp_basename( $src );
			@list( $width, $height ) = getimagesize( $src_file );
		}

		if ( $src && $width && $height ) {
			$image = array( $src, $width, $height );
		}
	}

	return apply_filters( 'wp_get_attachment_image_src', $image, $attachment_id, $size, $icon );
}

function wp_get_attachment_image($attachment_id, $size = 'thumbnail', $icon = false, $attr = '') {
	$html = '';
	$image = wp_get_attachment_image_src($attachment_id, $size, $icon);
	if ( $image ) {
		list($src, $width, $height) = $image;
		$hwstring = image_hwstring($width, $height);
		$size_class = $size;
		if ( is_array( $size_class ) ) {
			$size_class = join( 'x', $size_class );
		}
		$attachment = get_post($attachment_id);
		$default_attr = array(
			'src'	=> $src,
			'class'	=> "attachment-$size_class size-$size_class",
			'alt'	=> trim(strip_tags( get_post_meta($attachment_id, '_wp_attachment_image_alt', true) )), // Use Alt field first
		);
		if ( empty($default_attr['alt']) )
			$default_attr['alt'] = trim(strip_tags( $attachment->post_excerpt )); // If not, Use the Caption
		if ( empty($default_attr['alt']) )
			$default_attr['alt'] = trim(strip_tags( $attachment->post_title )); // Finally, use the title

		$attr = wp_parse_args( $attr, $default_attr );

		// Generate 'srcset' and 'sizes' if not already present.
		if ( empty( $attr['srcset'] ) ) {
			$image_meta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

			if ( is_array( $image_meta ) ) {
				$size_array = array( absint( $width ), absint( $height ) );
				$srcset = wp_calculate_image_srcset( $size_array, $src, $image_meta, $attachment_id );
				$sizes = wp_calculate_image_sizes( $size_array, $src, $image_meta, $attachment_id );

				if ( $srcset && ( $sizes || ! empty( $attr['sizes'] ) ) ) {
					$attr['srcset'] = $srcset;

					if ( empty( $attr['sizes'] ) ) {
						$attr['sizes'] = $sizes;
					}
				}
			}
		}

		$attr = apply_filters( 'wp_get_attachment_image_attributes', $attr, $attachment, $size );
		$attr = array_map( 'esc_attr', $attr );
		$html = rtrim("<img $hwstring");
		foreach ( $attr as $name => $value ) {
			$html .= " $name=" . '"' . $value . '"';
		}
		$html .= ' />';
	}

	return $html;
}

function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail', $icon = false ) {
	$image = wp_get_attachment_image_src( $attachment_id, $size, $icon );
	return isset( $image['0'] ) ? $image['0'] : false;
}

function _wp_get_attachment_relative_path( $file ) {
	$dirname = dirname( $file );

	if ( '.' === $dirname ) {
		return '';
	}

	if ( false !== strpos( $dirname, 'wp-content/uploads' ) ) {
		// Get the directory name relative to the upload directory (back compat for pre-2.7 uploads)
		$dirname = substr( $dirname, strpos( $dirname, 'wp-content/uploads' ) + 18 );
		$dirname = ltrim( $dirname, '/' );
	}

	return $dirname;
}

function _wp_get_image_size_from_meta( $size_name, $image_meta ) {
	if ( $size_name === 'full' ) {
		return array(
			absint( $image_meta['width'] ),
			absint( $image_meta['height'] ),
		);
	} elseif ( ! empty( $image_meta['sizes'][$size_name] ) ) {
		return array(
			absint( $image_meta['sizes'][$size_name]['width'] ),
			absint( $image_meta['sizes'][$size_name]['height'] ),
		);
	}

	return false;
}

function wp_get_attachment_image_srcset( $attachment_id, $size = 'medium', $image_meta = null ) {
	if ( ! $image = wp_get_attachment_image_src( $attachment_id, $size ) ) {
		return false;
	}

	if ( ! is_array( $image_meta ) ) {
		$image_meta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
	}

	$image_src = $image[0];
	$size_array = array(
		absint( $image[1] ),
		absint( $image[2] )
	);

	return wp_calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id );
}

function wp_calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id = 0 ) {
	$image_meta = apply_filters( 'wp_calculate_image_srcset_meta', $image_meta, $size_array, $image_src, $attachment_id );

	if ( empty( $image_meta['sizes'] ) || ! isset( $image_meta['file'] ) || strlen( $image_meta['file'] ) < 4 ) {
		return false;
	}

	$image_sizes = $image_meta['sizes'];

	// Get the width and height of the image.
	$image_width = (int) $size_array[0];
	$image_height = (int) $size_array[1];

	// Bail early if error/no width.
	if ( $image_width < 1 ) {
		return false;
	}

	$image_basename = wp_basename( $image_meta['file'] );

	if ( ! isset( $image_sizes['thumbnail']['mime-type'] ) || 'image/gif' !== $image_sizes['thumbnail']['mime-type'] ) {
		$image_sizes['full'] = array(
			'width'  => $image_meta['width'],
			'height' => $image_meta['height'],
			'file'   => $image_basename,
		);
	} elseif ( strpos( $image_src, $image_meta['file'] ) ) {
		return false;
	}

	// Retrieve the uploads sub-directory from the full size image.
	$dirname = _wp_get_attachment_relative_path( $image_meta['file'] );

	if ( $dirname ) {
		$dirname = trailingslashit( $dirname );
	}

	$upload_dir = wp_get_upload_dir();
	$image_baseurl = trailingslashit( $upload_dir['baseurl'] ) . $dirname;

	/*
	 * If currently on HTTPS, prefer HTTPS URLs when we know they're supported by the domain
	 * (which is to say, when they share the domain name of the current request).
	 */
	if ( is_ssl() && 'https' !== substr( $image_baseurl, 0, 5 ) && parse_url( $image_baseurl, PHP_URL_HOST ) === $_SERVER['HTTP_HOST'] ) {
		$image_baseurl = set_url_scheme( $image_baseurl, 'https' );
	}

	$image_edited = preg_match( '/-e[0-9]{13}/', wp_basename( $image_src ), $image_edit_hash );

	$max_srcset_image_width = apply_filters( 'max_srcset_image_width', 1600, $size_array );

	// Array to hold URL candidates.
	$sources = array();

	$src_matched = false;

	foreach ( $image_sizes as $image ) {
		$is_src = false;

		// Check if image meta isn't corrupted.
		if ( ! is_array( $image ) ) {
			continue;
		}

		// If the file name is part of the `src`, we've confirmed a match.
		if ( ! $src_matched && false !== strpos( $image_src, $dirname . $image['file'] ) ) {
			$src_matched = $is_src = true;
		}

		// Filter out images that are from previous edits.
		if ( $image_edited && ! strpos( $image['file'], $image_edit_hash[0] ) ) {
			continue;
		}

		/*
		 * Filter out images that are wider than '$max_srcset_image_width' unless
		 * that file is in the 'src' attribute.
		 */
		if ( $max_srcset_image_width && $image['width'] > $max_srcset_image_width && ! $is_src ) {
			continue;
		}

		/**
		 * To check for varying crops, we calculate the expected size of the smaller
		 * image if the larger were constrained by the width of the smaller and then
		 * see if it matches what we're expecting.
		 */
		if ( $image_width > $image['width'] ) {
			$constrained_size = wp_constrain_dimensions( $image_width, $image_height, $image['width'] );
			$expected_size = array( $image['width'], $image['height'] );
		} else {
			$constrained_size = wp_constrain_dimensions( $image['width'], $image['height'], $image_width );
			$expected_size = array( $image_width, $image_height );
		}

		// If the image dimensions are within 1px of the expected size, use it.
		if ( abs( $constrained_size[0] - $expected_size[0] ) <= 1 && abs( $constrained_size[1] - $expected_size[1] ) <= 1 ) {
			// Add the URL, descriptor, and value to the sources array to be returned.
			$source = array(
				'url'        => $image_baseurl . $image['file'],
				'descriptor' => 'w',
				'value'      => $image['width'],
			);

			// The 'src' image has to be the first in the 'srcset', because of a bug in iOS8. See #35030.
			if ( $is_src ) {
				$sources = array( $image['width'] => $source ) + $sources;
			} else {
				$sources[ $image['width'] ] = $source;
			}
		}
	}

	$sources = apply_filters( 'wp_calculate_image_srcset', $sources, $size_array, $image_src, $image_meta, $attachment_id );

	// Only return a 'srcset' value if there is more than one source.
	if ( ! $src_matched || count( $sources ) < 2 ) {
		return false;
	}

	$srcset = '';

	foreach ( $sources as $source ) {
		$srcset .= $source['url'] . ' ' . $source['value'] . $source['descriptor'] . ', ';
	}

	return rtrim( $srcset, ', ' );
}

function wp_get_attachment_image_sizes( $attachment_id, $size = 'medium', $image_meta = null ) {
	if ( ! $image = wp_get_attachment_image_src( $attachment_id, $size ) ) {
		return false;
	}

	if ( ! is_array( $image_meta ) ) {
		$image_meta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
	}

	$image_src = $image[0];
	$size_array = array(
		absint( $image[1] ),
		absint( $image[2] )
	);

	return wp_calculate_image_sizes( $size_array, $image_src, $image_meta, $attachment_id );
}

function wp_calculate_image_sizes( $size, $image_src = null, $image_meta = null, $attachment_id = 0 ) {
	$width = 0;

	if ( is_array( $size ) ) {
		$width = absint( $size[0] );
	} elseif ( is_string( $size ) ) {
		if ( ! $image_meta && $attachment_id ) {
			$image_meta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		}

		if ( is_array( $image_meta ) ) {
			$size_array = _wp_get_image_size_from_meta( $size, $image_meta );
			if ( $size_array ) {
				$width = absint( $size_array[0] );
			}
		}
	}

	if ( ! $width ) {
		return false;
	}

	// Setup the default 'sizes' attribute.
	$sizes = sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $width );

	return apply_filters( 'wp_calculate_image_sizes', $sizes, $size, $image_src, $image_meta, $attachment_id );
}

function wp_make_content_images_responsive( $content ) {
	if ( ! preg_match_all( '/<img [^>]+>/', $content, $matches ) ) {
		return $content;
	}

	$selected_images = $attachment_ids = array();

	foreach( $matches[0] as $image ) {
		if ( false === strpos( $image, ' srcset=' ) && preg_match( '/wp-image-([0-9]+)/i', $image, $class_id ) &&
			( $attachment_id = absint( $class_id[1] ) ) ) {

			/*
			 * If exactly the same image tag is used more than once, overwrite it.
			 * All identical tags will be replaced later with 'str_replace()'.
			 */
			$selected_images[ $image ] = $attachment_id;
			// Overwrite the ID when the same image is included more than once.
			$attachment_ids[ $attachment_id ] = true;
		}
	}

	if ( count( $attachment_ids ) > 1 ) {
		update_meta_cache( 'post', array_keys( $attachment_ids ) );
	}

	foreach ( $selected_images as $image => $attachment_id ) {
		$image_meta = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$content = str_replace( $image, wp_image_add_srcset_and_sizes( $image, $image_meta, $attachment_id ), $content );
	}

	return $content;
}

function wp_image_add_srcset_and_sizes( $image, $image_meta, $attachment_id ) {
	// Ensure the image meta exists.
	if ( empty( $image_meta['sizes'] ) ) {
		return $image;
	}

	$image_src = preg_match( '/src="([^"]+)"/', $image, $match_src ) ? $match_src[1] : '';
	list( $image_src ) = explode( '?', $image_src );

	// Return early if we couldn't get the image source.
	if ( ! $image_src ) {
		return $image;
	}

	// Bail early if an image has been inserted and later edited.
	if ( preg_match( '/-e[0-9]{13}/', $image_meta['file'], $img_edit_hash ) &&
		strpos( wp_basename( $image_src ), $img_edit_hash[0] ) === false ) {

		return $image;
	}

	$width  = preg_match( '/ width="([0-9]+)"/',  $image, $match_width  ) ? (int) $match_width[1]  : 0;
	$height = preg_match( '/ height="([0-9]+)"/', $image, $match_height ) ? (int) $match_height[1] : 0;

	if ( ! $width || ! $height ) {
		/*
		 * If attempts to parse the size value failed, attempt to use the image meta data to match
		 * the image file name from 'src' against the available sizes for an attachment.
		 */
		$image_filename = wp_basename( $image_src );

		if ( $image_filename === wp_basename( $image_meta['file'] ) ) {
			$width = (int) $image_meta['width'];
			$height = (int) $image_meta['height'];
		} else {
			foreach( $image_meta['sizes'] as $image_size_data ) {
				if ( $image_filename === $image_size_data['file'] ) {
					$width = (int) $image_size_data['width'];
					$height = (int) $image_size_data['height'];
					break;
				}
			}
		}
	}

	if ( ! $width || ! $height ) {
		return $image;
	}

	$size_array = array( $width, $height );
	$srcset = wp_calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id );

	if ( $srcset ) {
		// Check if there is already a 'sizes' attribute.
		$sizes = strpos( $image, ' sizes=' );

		if ( ! $sizes ) {
			$sizes = wp_calculate_image_sizes( $size_array, $image_src, $image_meta, $attachment_id );
		}
	}

	if ( $srcset && $sizes ) {
		// Format the 'srcset' and 'sizes' string and escape attributes.
		$attr = sprintf( ' srcset="%s"', esc_attr( $srcset ) );

		if ( is_string( $sizes ) ) {
			$attr .= sprintf( ' sizes="%s"', esc_attr( $sizes ) );
		}

		// Add 'srcset' and 'sizes' attributes to the image markup.
		$image = preg_replace( '/<img ([^>]+?)[\/ ]*>/', '<img $1' . $attr . ' />', $image );
	}

	return $image;
}

function _wp_post_thumbnail_class_filter( $attr ) {
	$attr['class'] .= ' wp-post-image';
	return $attr;
}

function _wp_post_thumbnail_class_filter_add( $attr ) {
	add_filter( 'wp_get_attachment_image_attributes', '_wp_post_thumbnail_class_filter' );
}

function _wp_post_thumbnail_class_filter_remove( $attr ) {
	remove_filter( 'wp_get_attachment_image_attributes', '_wp_post_thumbnail_class_filter' );
}

add_shortcode('wp_caption', 'img_caption_shortcode');
add_shortcode('caption', 'img_caption_shortcode');

function img_caption_shortcode( $attr, $content = null ) {
	// New-style shortcode with the caption inside the shortcode with the link and image tags.
	if ( ! isset( $attr['caption'] ) ) {
		if ( preg_match( '#((?:<a [^>]+>\s*)?<img [^>]+>(?:\s*</a>)?)(.*)#is', $content, $matches ) ) {
			$content = $matches[1];
			$attr['caption'] = trim( $matches[2] );
		}
	} elseif ( strpos( $attr['caption'], '<' ) !== false ) {
		$attr['caption'] = wp_kses( $attr['caption'], 'post' );
	}

	$output = apply_filters( 'img_caption_shortcode', '', $attr, $content );
	if ( $output != '' )
		return $output;

	$atts = shortcode_atts( array(
		'id'	  => '',
		'align'	  => 'alignnone',
		'width'	  => '',
		'caption' => '',
		'class'   => '',
	), $attr, 'caption' );

	$atts['width'] = (int) $atts['width'];
	if ( $atts['width'] < 1 || empty( $atts['caption'] ) )
		return $content;

	if ( ! empty( $atts['id'] ) )
		$atts['id'] = 'id="' . esc_attr( sanitize_html_class( $atts['id'] ) ) . '" ';

	$class = trim( 'wp-caption ' . $atts['align'] . ' ' . $atts['class'] );

	$html5 = current_theme_supports( 'html5', 'caption' );
	// HTML5 captions never added the extra 10px to the image width
	$width = $html5 ? $atts['width'] : ( 10 + $atts['width'] );

	$caption_width = apply_filters( 'img_caption_shortcode_width', $width, $atts, $content );

	$style = '';
	if ( $caption_width )
		$style = 'style="width: ' . (int) $caption_width . 'px" ';

	$html = '';
	if ( $html5 ) {
		$html = '<figure ' . $atts['id'] . $style . 'class="' . esc_attr( $class ) . '">'
		. do_shortcode( $content ) . '<figcaption class="wp-caption-text">' . $atts['caption'] . '</figcaption></figure>';
	} else {
		$html = '<div ' . $atts['id'] . $style . 'class="' . esc_attr( $class ) . '">'
		. do_shortcode( $content ) . '<p class="wp-caption-text">' . $atts['caption'] . '</p></div>';
	}

	return $html;
}

add_shortcode('gallery', 'gallery_shortcode');

function gallery_shortcode( $attr ) {
	$post = get_post();

	static $instance = 0;
	$instance++;

	if ( ! empty( $attr['ids'] ) ) {
		// 'ids' is explicitly ordered, unless you specify otherwise.
		if ( empty( $attr['orderby'] ) ) {
			$attr['orderby'] = 'post__in';
		}
		$attr['include'] = $attr['ids'];
	}

	$output = apply_filters( 'post_gallery', '', $attr, $instance );
	if ( $output != '' ) {
		return $output;
	}

	$html5 = current_theme_supports( 'html5', 'gallery' );
	$atts = shortcode_atts( array(
		'order'      => 'ASC',
		'orderby'    => 'menu_order ID',
		'id'         => $post ? $post->ID : 0,
		'itemtag'    => $html5 ? 'figure'     : 'dl',
		'icontag'    => $html5 ? 'div'        : 'dt',
		'captiontag' => $html5 ? 'figcaption' : 'dd',
		'columns'    => 3,
		'size'       => 'thumbnail',
		'include'    => '',
		'exclude'    => '',
		'link'       => ''
	), $attr, 'gallery' );

	$id = intval( $atts['id'] );

	if ( ! empty( $atts['include'] ) ) {
		$_attachments = get_posts( array( 'include' => $atts['include'], 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $atts['order'], 'orderby' => $atts['orderby'] ) );

		$attachments = array();
		foreach ( $_attachments as $key => $val ) {
			$attachments[$val->ID] = $_attachments[$key];
		}
	} elseif ( ! empty( $atts['exclude'] ) ) {
		$attachments = get_children( array( 'post_parent' => $id, 'exclude' => $atts['exclude'], 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $atts['order'], 'orderby' => $atts['orderby'] ) );
	} else {
		$attachments = get_children( array( 'post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $atts['order'], 'orderby' => $atts['orderby'] ) );
	}

	if ( empty( $attachments ) ) {
		return '';
	}

	if ( is_feed() ) {
		$output = "\n";
		foreach ( $attachments as $att_id => $attachment ) {
			$output .= wp_get_attachment_link( $att_id, $atts['size'], true ) . "\n";
		}
		return $output;
	}

	$itemtag = tag_escape( $atts['itemtag'] );
	$captiontag = tag_escape( $atts['captiontag'] );
	$icontag = tag_escape( $atts['icontag'] );
	$valid_tags = wp_kses_allowed_html( 'post' );
	if ( ! isset( $valid_tags[ $itemtag ] ) ) {
		$itemtag = 'dl';
	}
	if ( ! isset( $valid_tags[ $captiontag ] ) ) {
		$captiontag = 'dd';
	}
	if ( ! isset( $valid_tags[ $icontag ] ) ) {
		$icontag = 'dt';
	}

	$columns = intval( $atts['columns'] );
	$itemwidth = $columns > 0 ? floor(100/$columns) : 100;
	$float = 'left';

	$selector = "gallery-{$instance}";

	$gallery_style = '';

	if ( apply_filters( 'use_default_gallery_style', ! $html5 ) ) {
		$gallery_style = "
		<style type='text/css'>
			#{$selector} {
				margin: auto;
			}
			#{$selector} .gallery-item {
				float: {$float};
				margin-top: 10px;
				text-align: center;
				width: {$itemwidth}%;
			}
			#{$selector} img {
				border: 2px solid #cfcfcf;
			}
			#{$selector} .gallery-caption {
				margin-left: 0;
			}
			/* see gallery_shortcode() in wp-includes/media.php */
		</style>\n\t\t";
	}

	$size_class = sanitize_html_class( $atts['size'] );
	$gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}'>";

	$output = apply_filters( 'gallery_style', $gallery_style . $gallery_div );

	$i = 0;
	foreach ( $attachments as $id => $attachment ) {

		$attr = ( trim( $attachment->post_excerpt ) ) ? array( 'aria-describedby' => "$selector-$id" ) : '';
		if ( ! empty( $atts['link'] ) && 'file' === $atts['link'] ) {
			$image_output = wp_get_attachment_link( $id, $atts['size'], false, false, false, $attr );
		} elseif ( ! empty( $atts['link'] ) && 'none' === $atts['link'] ) {
			$image_output = wp_get_attachment_image( $id, $atts['size'], false, $attr );
		} else {
			$image_output = wp_get_attachment_link( $id, $atts['size'], true, false, false, $attr );
		}
		$image_meta  = wp_get_attachment_metadata( $id );

		$orientation = '';
		if ( isset( $image_meta['height'], $image_meta['width'] ) ) {
			$orientation = ( $image_meta['height'] > $image_meta['width'] ) ? 'portrait' : 'landscape';
		}
		$output .= "<{$itemtag} class='gallery-item'>";
		$output .= "
			<{$icontag} class='gallery-icon {$orientation}'>
				$image_output
			</{$icontag}>";
		if ( $captiontag && trim($attachment->post_excerpt) ) {
			$output .= "
				<{$captiontag} class='wp-caption-text gallery-caption' id='$selector-$id'>
				" . wptexturize($attachment->post_excerpt) . "
				</{$captiontag}>";
		}
		$output .= "</{$itemtag}>";
		if ( ! $html5 && $columns > 0 && ++$i % $columns == 0 ) {
			$output .= '<br style="clear: both" />';
		}
	}

	if ( ! $html5 && $columns > 0 && $i % $columns !== 0 ) {
		$output .= "
			<br style='clear: both' />";
	}

	$output .= "
		</div>\n";

	return $output;
}

function wp_underscore_playlist_templates() {
?>
<script type="text/html" id="tmpl-wp-playlist-current-item">
	<# if ( data.image ) { #>
	<img src="{{ data.thumb.src }}" alt="" />
	<# } #>
	<div class="wp-playlist-caption">
		<span class="wp-playlist-item-meta wp-playlist-item-title"><?php
			/* translators: playlist item title */
			printf( _x( '&#8220;%s&#8221;', 'playlist item title' ), '{{ data.title }}' );
		?></span>
		<# if ( data.meta.album ) { #><span class="wp-playlist-item-meta wp-playlist-item-album">{{ data.meta.album }}</span><# } #>
		<# if ( data.meta.artist ) { #><span class="wp-playlist-item-meta wp-playlist-item-artist">{{ data.meta.artist }}</span><# } #>
	</div>
</script>
<script type="text/html" id="tmpl-wp-playlist-item">
	<div class="wp-playlist-item">
		<a class="wp-playlist-caption" href="{{ data.src }}">
			{{ data.index ? ( data.index + '. ' ) : '' }}
			<# if ( data.caption ) { #>
				{{ data.caption }}
			<# } else { #>
				<span class="wp-playlist-item-title"><?php
					/* translators: playlist item title */
					printf( _x( '&#8220;%s&#8221;', 'playlist item title' ), '{{{ data.title }}}' );
				?></span>
				<# if ( data.artists && data.meta.artist ) { #>
				<span class="wp-playlist-item-artist"> &mdash; {{ data.meta.artist }}</span>
				<# } #>
			<# } #>
		</a>
		<# if ( data.meta.length_formatted ) { #>
		<div class="wp-playlist-item-length">{{ data.meta.length_formatted }}</div>
		<# } #>
	</div>
</script>
<?php
}

function wp_playlist_scripts( $type ) {
	wp_enqueue_style( 'wp-mediaelement' );
	wp_enqueue_script( 'wp-playlist' );
?>
<!--[if lt IE 9]><script>document.createElement('<?php echo esc_js( $type ) ?>');</script><![endif]-->
<?php
	add_action( 'wp_footer', 'wp_underscore_playlist_templates', 0 );
	add_action( 'admin_footer', 'wp_underscore_playlist_templates', 0 );
}

function wp_playlist_shortcode( $attr ) {
	global $content_width;
	$post = get_post();

	static $instance = 0;
	$instance++;

	if ( ! empty( $attr['ids'] ) ) {
		// 'ids' is explicitly ordered, unless you specify otherwise.
		if ( empty( $attr['orderby'] ) ) {
			$attr['orderby'] = 'post__in';
		}
		$attr['include'] = $attr['ids'];
	}

	$output = apply_filters( 'post_playlist', '', $attr, $instance );
	if ( $output != '' ) {
		return $output;
	}

	$atts = shortcode_atts( array(
		'type'		=> 'audio',
		'order'		=> 'ASC',
		'orderby'	=> 'menu_order ID',
		'id'		=> $post ? $post->ID : 0,
		'include'	=> '',
		'exclude'   => '',
		'style'		=> 'light',
		'tracklist' => true,
		'tracknumbers' => true,
		'images'	=> true,
		'artists'	=> true
	), $attr, 'playlist' );

	$id = intval( $atts['id'] );

	if ( $atts['type'] !== 'audio' ) {
		$atts['type'] = 'video';
	}

	$args = array(
		'post_status' => 'inherit',
		'post_type' => 'attachment',
		'post_mime_type' => $atts['type'],
		'order' => $atts['order'],
		'orderby' => $atts['orderby']
	);

	if ( ! empty( $atts['include'] ) ) {
		$args['include'] = $atts['include'];
		$_attachments = get_posts( $args );

		$attachments = array();
		foreach ( $_attachments as $key => $val ) {
			$attachments[$val->ID] = $_attachments[$key];
		}
	} elseif ( ! empty( $atts['exclude'] ) ) {
		$args['post_parent'] = $id;
		$args['exclude'] = $atts['exclude'];
		$attachments = get_children( $args );
	} else {
		$args['post_parent'] = $id;
		$attachments = get_children( $args );
	}

	if ( empty( $attachments ) ) {
		return '';
	}

	if ( is_feed() ) {
		$output = "\n";
		foreach ( $attachments as $att_id => $attachment ) {
			$output .= wp_get_attachment_link( $att_id ) . "\n";
		}
		return $output;
	}

	$outer = 22; // default padding and border of wrapper

	$default_width = 640;
	$default_height = 360;

	$theme_width = empty( $content_width ) ? $default_width : ( $content_width - $outer );
	$theme_height = empty( $content_width ) ? $default_height : round( ( $default_height * $theme_width ) / $default_width );

	$data = array(
		'type' => $atts['type'],
		// don't pass strings to JSON, will be truthy in JS
		'tracklist' => wp_validate_boolean( $atts['tracklist'] ),
		'tracknumbers' => wp_validate_boolean( $atts['tracknumbers'] ),
		'images' => wp_validate_boolean( $atts['images'] ),
		'artists' => wp_validate_boolean( $atts['artists'] ),
	);

	$tracks = array();
	foreach ( $attachments as $attachment ) {
		$url = wp_get_attachment_url( $attachment->ID );
		$ftype = wp_check_filetype( $url, wp_get_mime_types() );
		$track = array(
			'src' => $url,
			'type' => $ftype['type'],
			'title' => $attachment->post_title,
			'caption' => $attachment->post_excerpt,
			'description' => $attachment->post_content
		);

		$track['meta'] = array();
		$meta = wp_get_attachment_metadata( $attachment->ID );
		if ( ! empty( $meta ) ) {

			foreach ( wp_get_attachment_id3_keys( $attachment ) as $key => $label ) {
				if ( ! empty( $meta[ $key ] ) ) {
					$track['meta'][ $key ] = $meta[ $key ];
				}
			}

			if ( 'video' === $atts['type'] ) {
				if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
					$width = $meta['width'];
					$height = $meta['height'];
					$theme_height = round( ( $height * $theme_width ) / $width );
				} else {
					$width = $default_width;
					$height = $default_height;
				}

				$track['dimensions'] = array(
					'original' => compact( 'width', 'height' ),
					'resized' => array(
						'width' => $theme_width,
						'height' => $theme_height
					)
				);
			}
		}

		if ( $atts['images'] ) {
			$thumb_id = get_post_thumbnail_id( $attachment->ID );
			if ( ! empty( $thumb_id ) ) {
				list( $src, $width, $height ) = wp_get_attachment_image_src( $thumb_id, 'full' );
				$track['image'] = compact( 'src', 'width', 'height' );
				list( $src, $width, $height ) = wp_get_attachment_image_src( $thumb_id, 'thumbnail' );
				$track['thumb'] = compact( 'src', 'width', 'height' );
			} else {
				$src = wp_mime_type_icon( $attachment->ID );
				$width = 48;
				$height = 64;
				$track['image'] = compact( 'src', 'width', 'height' );
				$track['thumb'] = compact( 'src', 'width', 'height' );
			}
		}

		$tracks[] = $track;
	}
	$data['tracks'] = $tracks;

	$safe_type = esc_attr( $atts['type'] );
	$safe_style = esc_attr( $atts['style'] );

	ob_start();

	if ( 1 === $instance ) {
		do_action( 'wp_playlist_scripts', $atts['type'], $atts['style'] );
	} ?>
<div class="wp-playlist wp-<?php echo $safe_type ?>-playlist wp-playlist-<?php echo $safe_style ?>">
	<?php if ( 'audio' === $atts['type'] ): ?>
	<div class="wp-playlist-current-item"></div>
	<?php endif ?>
	<<?php echo $safe_type ?> controls="controls" preload="none" width="<?php
		echo (int) $theme_width;
	?>"<?php if ( 'video' === $safe_type ):
		echo ' height="', (int) $theme_height, '"';
	else:
		echo ' style="visibility: hidden"';
	endif; ?>></<?php echo $safe_type ?>>
	<div class="wp-playlist-next"></div>
	<div class="wp-playlist-prev"></div>
	<noscript>
	<ol><?php
	foreach ( $attachments as $att_id => $attachment ) {
		printf( '<li>%s</li>', wp_get_attachment_link( $att_id ) );
	}
	?></ol>
	</noscript>
	<script type="application/json" class="wp-playlist-script"><?php echo wp_json_encode( $data ) ?></script>
</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'playlist', 'wp_playlist_shortcode' );

function wp_mediaelement_fallback( $url ) {
	return apply_filters( 'wp_mediaelement_fallback', sprintf( '<a href="%1$s">%1$s</a>', esc_url( $url ) ), $url );
}

function wp_get_audio_extensions() {
	return apply_filters( 'wp_audio_extensions', array( 'mp3', 'ogg', 'wma', 'm4a', 'wav' ) );
}

function wp_get_attachment_id3_keys( $attachment, $context = 'display' ) {
	$fields = array(
		'artist' => 'Artist',
		'album' => 'Album',
	);

	if ( 'display' === $context ) {
		$fields['genre']            = 'Genre';
		$fields['year']             = 'Year';
		$fields['length_formatted'] = _x( 'Length', 'video or audio' );
	} elseif ( 'js' === $context ) {
		$fields['bitrate']          = 'Bitrate';
		$fields['bitrate_mode']     = 'Bitrate Mode';
	}

	return apply_filters( 'wp_get_attachment_id3_keys', $fields, $attachment, $context );
}

function wp_audio_shortcode( $attr, $content = '' ) {
	$post_id = get_post() ? get_the_ID() : 0;

	static $instance = 0;
	$instance++;

	$override = apply_filters( 'wp_audio_shortcode_override', '', $attr, $content, $instance );
	if ( '' !== $override ) {
		return $override;
	}

	$audio = null;

	$default_types = wp_get_audio_extensions();
	$defaults_atts = array(
		'src'      => '',
		'loop'     => '',
		'autoplay' => '',
		'preload'  => 'none',
		'class'    => 'wp-audio-shortcode',
		'style'    => 'width: 100%; visibility: hidden;'
	);
	foreach ( $default_types as $type ) {
		$defaults_atts[$type] = '';
	}

	$atts = shortcode_atts( $defaults_atts, $attr, 'audio' );

	$primary = false;
	if ( ! empty( $atts['src'] ) ) {
		$type = wp_check_filetype( $atts['src'], wp_get_mime_types() );
		if ( ! in_array( strtolower( $type['ext'] ), $default_types ) ) {
			return sprintf( '<a class="wp-embedded-audio" href="%s">%s</a>', esc_url( $atts['src'] ), esc_html( $atts['src'] ) );
		}
		$primary = true;
		array_unshift( $default_types, 'src' );
	} else {
		foreach ( $default_types as $ext ) {
			if ( ! empty( $atts[ $ext ] ) ) {
				$type = wp_check_filetype( $atts[ $ext ], wp_get_mime_types() );
				if ( strtolower( $type['ext'] ) === $ext ) {
					$primary = true;
				}
			}
		}
	}

	if ( ! $primary ) {
		$audios = get_attached_media( 'audio', $post_id );
		if ( empty( $audios ) ) {
			return;
		}

		$audio = reset( $audios );
		$atts['src'] = wp_get_attachment_url( $audio->ID );
		if ( empty( $atts['src'] ) ) {
			return;
		}

		array_unshift( $default_types, 'src' );
	}

	$library = apply_filters( 'wp_audio_shortcode_library', 'mediaelement' );
	if ( 'mediaelement' === $library && did_action( 'init' ) ) {
		wp_enqueue_style( 'wp-mediaelement' );
		wp_enqueue_script( 'wp-mediaelement' );
	}

	$atts['class'] = apply_filters( 'wp_audio_shortcode_class', $atts['class'] );

	$html_atts = array(
		'class'    => $atts['class'],
		'id'       => sprintf( 'audio-%d-%d', $post_id, $instance ),
		'loop'     => wp_validate_boolean( $atts['loop'] ),
		'autoplay' => wp_validate_boolean( $atts['autoplay'] ),
		'preload'  => $atts['preload'],
		'style'    => $atts['style'],
	);

	// These ones should just be omitted altogether if they are blank
	foreach ( array( 'loop', 'autoplay', 'preload' ) as $a ) {
		if ( empty( $html_atts[$a] ) ) {
			unset( $html_atts[$a] );
		}
	}

	$attr_strings = array();
	foreach ( $html_atts as $k => $v ) {
		$attr_strings[] = $k . '="' . esc_attr( $v ) . '"';
	}

	$html = '';
	if ( 'mediaelement' === $library && 1 === $instance ) {
		$html .= "<!--[if lt IE 9]><script>document.createElement('audio');</script><![endif]-->\n";
	}
	$html .= sprintf( '<audio %s controls="controls">', join( ' ', $attr_strings ) );

	$fileurl = '';
	$source = '<source type="%s" src="%s" />';
	foreach ( $default_types as $fallback ) {
		if ( ! empty( $atts[ $fallback ] ) ) {
			if ( empty( $fileurl ) ) {
				$fileurl = $atts[ $fallback ];
			}
			$type = wp_check_filetype( $atts[ $fallback ], wp_get_mime_types() );
			$url = add_query_arg( '_', $instance, $atts[ $fallback ] );
			$html .= sprintf( $source, $type['type'], esc_url( $url ) );
		}
	}

	if ( 'mediaelement' === $library ) {
		$html .= wp_mediaelement_fallback( $fileurl );
	}
	$html .= '</audio>';

	return apply_filters( 'wp_audio_shortcode', $html, $atts, $audio, $post_id, $library );
}
add_shortcode( 'audio', 'wp_audio_shortcode' );

function wp_get_video_extensions() {
	return apply_filters( 'wp_video_extensions', array( 'mp4', 'm4v', 'webm', 'ogv', 'wmv', 'flv' ) );
}

function wp_video_shortcode( $attr, $content = '' ) {
	global $content_width;
	$post_id = get_post() ? get_the_ID() : 0;

	static $instance = 0;
	$instance++;

	$override = apply_filters( 'wp_video_shortcode_override', '', $attr, $content, $instance );
	if ( '' !== $override ) {
		return $override;
	}

	$video = null;

	$default_types = wp_get_video_extensions();
	$defaults_atts = array(
		'src'      => '',
		'poster'   => '',
		'loop'     => '',
		'autoplay' => '',
		'preload'  => 'metadata',
		'width'    => 640,
		'height'   => 360,
		'class'    => 'wp-video-shortcode',
	);

	foreach ( $default_types as $type ) {
		$defaults_atts[$type] = '';
	}

	$atts = shortcode_atts( $defaults_atts, $attr, 'video' );

	if ( is_admin() ) {
		// shrink the video so it isn't huge in the admin
		if ( $atts['width'] > $defaults_atts['width'] ) {
			$atts['height'] = round( ( $atts['height'] * $defaults_atts['width'] ) / $atts['width'] );
			$atts['width'] = $defaults_atts['width'];
		}
	} else {
		// if the video is bigger than the theme
		if ( ! empty( $content_width ) && $atts['width'] > $content_width ) {
			$atts['height'] = round( ( $atts['height'] * $content_width ) / $atts['width'] );
			$atts['width'] = $content_width;
		}
	}

	$is_vimeo = $is_youtube = false;
	$yt_pattern = '#^https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/)#';
	$vimeo_pattern = '#^https?://(.+\.)?vimeo\.com/.*#';

	$primary = false;
	if ( ! empty( $atts['src'] ) ) {
		$is_vimeo = ( preg_match( $vimeo_pattern, $atts['src'] ) );
		$is_youtube = (  preg_match( $yt_pattern, $atts['src'] ) );
		if ( ! $is_youtube && ! $is_vimeo ) {
			$type = wp_check_filetype( $atts['src'], wp_get_mime_types() );
			if ( ! in_array( strtolower( $type['ext'] ), $default_types ) ) {
				return sprintf( '<a class="wp-embedded-video" href="%s">%s</a>', esc_url( $atts['src'] ), esc_html( $atts['src'] ) );
			}
		}

		if ( $is_vimeo ) {
			wp_enqueue_script( 'froogaloop' );
		}

		$primary = true;
		array_unshift( $default_types, 'src' );
	} else {
		foreach ( $default_types as $ext ) {
			if ( ! empty( $atts[ $ext ] ) ) {
				$type = wp_check_filetype( $atts[ $ext ], wp_get_mime_types() );
				if ( strtolower( $type['ext'] ) === $ext ) {
					$primary = true;
				}
			}
		}
	}

	if ( ! $primary ) {
		$videos = get_attached_media( 'video', $post_id );
		if ( empty( $videos ) ) {
			return;
		}

		$video = reset( $videos );
		$atts['src'] = wp_get_attachment_url( $video->ID );
		if ( empty( $atts['src'] ) ) {
			return;
		}

		array_unshift( $default_types, 'src' );
	}

	$library = apply_filters( 'wp_video_shortcode_library', 'mediaelement' );
	if ( 'mediaelement' === $library && did_action( 'init' ) ) {
		wp_enqueue_style( 'wp-mediaelement' );
		wp_enqueue_script( 'wp-mediaelement' );
	}

	$atts['class'] = apply_filters( 'wp_video_shortcode_class', $atts['class'] );

	$html_atts = array(
		'class'    => $atts['class'],
		'id'       => sprintf( 'video-%d-%d', $post_id, $instance ),
		'width'    => absint( $atts['width'] ),
		'height'   => absint( $atts['height'] ),
		'poster'   => esc_url( $atts['poster'] ),
		'loop'     => wp_validate_boolean( $atts['loop'] ),
		'autoplay' => wp_validate_boolean( $atts['autoplay'] ),
		'preload'  => $atts['preload'],
	);

	// These ones should just be omitted altogether if they are blank
	foreach ( array( 'poster', 'loop', 'autoplay', 'preload' ) as $a ) {
		if ( empty( $html_atts[$a] ) ) {
			unset( $html_atts[$a] );
		}
	}

	$attr_strings = array();
	foreach ( $html_atts as $k => $v ) {
		$attr_strings[] = $k . '="' . esc_attr( $v ) . '"';
	}

	$html = '';
	if ( 'mediaelement' === $library && 1 === $instance ) {
		$html .= "<!--[if lt IE 9]><script>document.createElement('video');</script><![endif]-->\n";
	}
	$html .= sprintf( '<video %s controls="controls">', join( ' ', $attr_strings ) );

	$fileurl = '';
	$source = '<source type="%s" src="%s" />';
	foreach ( $default_types as $fallback ) {
		if ( ! empty( $atts[ $fallback ] ) ) {
			if ( empty( $fileurl ) ) {
				$fileurl = $atts[ $fallback ];
			}
			if ( 'src' === $fallback && $is_youtube ) {
				$type = array( 'type' => 'video/youtube' );
			} elseif ( 'src' === $fallback && $is_vimeo ) {
				$type = array( 'type' => 'video/vimeo' );
			} else {
				$type = wp_check_filetype( $atts[ $fallback ], wp_get_mime_types() );
			}
			$url = add_query_arg( '_', $instance, $atts[ $fallback ] );
			$html .= sprintf( $source, $type['type'], esc_url( $url ) );
		}
	}

	if ( ! empty( $content ) ) {
		if ( false !== strpos( $content, "\n" ) ) {
			$content = str_replace( array( "\r\n", "\n", "\t" ), '', $content );
		}
		$html .= trim( $content );
	}

	if ( 'mediaelement' === $library ) {
		$html .= wp_mediaelement_fallback( $fileurl );
	}
	$html .= '</video>';

	$width_rule = '';
	if ( ! empty( $atts['width'] ) ) {
		$width_rule = sprintf( 'width: %dpx; ', $atts['width'] );
	}
	$output = sprintf( '<div style="%s" class="wp-video">%s</div>', $width_rule, $html );

	return apply_filters( 'wp_video_shortcode', $output, $atts, $video, $post_id, $library );
}
add_shortcode( 'video', 'wp_video_shortcode' );

function previous_image_link( $size = 'thumbnail', $text = false ) {
	adjacent_image_link(true, $size, $text);
}

function next_image_link( $size = 'thumbnail', $text = false ) {
	adjacent_image_link(false, $size, $text);
}

function adjacent_image_link( $prev = true, $size = 'thumbnail', $text = false ) {
	$post = get_post();
	$attachments = array_values( get_children( array( 'post_parent' => $post->post_parent, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'ASC', 'orderby' => 'menu_order ID' ) ) );

	foreach ( $attachments as $k => $attachment ) {
		if ( $attachment->ID == $post->ID ) {
			break;
		}
	}

	$output = '';
	$attachment_id = 0;

	if ( $attachments ) {
		$k = $prev ? $k - 1 : $k + 1;

		if ( isset( $attachments[ $k ] ) ) {
			$attachment_id = $attachments[ $k ]->ID;
			$output = wp_get_attachment_link( $attachment_id, $size, true, false, $text );
		}
	}

	$adjacent = $prev ? 'previous' : 'next';

	echo apply_filters( "{$adjacent}_image_link", $output, $attachment_id, $size, $text );
}

function get_attachment_taxonomies( $attachment ) {
	if ( is_int( $attachment ) ) {
		$attachment = get_post( $attachment );
	} elseif ( is_array( $attachment ) ) {
		$attachment = (object) $attachment;
	}
	if ( ! is_object($attachment) )
		return array();

	$file = get_attached_file( $attachment->ID );
	$filename = basename( $file );

	$objects = array('attachment');

	if ( false !== strpos($filename, '.') )
		$objects[] = 'attachment:' . substr($filename, strrpos($filename, '.') + 1);
	if ( !empty($attachment->post_mime_type) ) {
		$objects[] = 'attachment:' . $attachment->post_mime_type;
		if ( false !== strpos($attachment->post_mime_type, '/') )
			foreach ( explode('/', $attachment->post_mime_type) as $token )
				if ( !empty($token) )
					$objects[] = "attachment:$token";
	}

	$taxonomies = array();
	foreach ( $objects as $object )
		if ( $taxes = get_object_taxonomies($object) )
			$taxonomies = array_merge($taxonomies, $taxes);

	return array_unique($taxonomies);
}

function get_taxonomies_for_attachments( $output = 'names' ) {
	$taxonomies = array();
	foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
		foreach ( $taxonomy->object_type as $object_type ) {
			if ( 'attachment' == $object_type || 0 === strpos( $object_type, 'attachment:' ) ) {
				if ( 'names' == $output )
					$taxonomies[] = $taxonomy->name;
				else
					$taxonomies[ $taxonomy->name ] = $taxonomy;
				break;
			}
		}
	}

	return $taxonomies;
}

function wp_imagecreatetruecolor($width, $height) {
	$img = imagecreatetruecolor($width, $height);
	if ( is_resource($img) && function_exists('imagealphablending') && function_exists('imagesavealpha') ) {
		imagealphablending($img, false);
		imagesavealpha($img, true);
	}
	return $img;
}

function wp_expand_dimensions( $example_width, $example_height, $max_width, $max_height ) {
	$example_width  = (int) $example_width;
	$example_height = (int) $example_height;
	$max_width      = (int) $max_width;
	$max_height     = (int) $max_height;

	return wp_constrain_dimensions( $example_width * 1000000, $example_height * 1000000, $max_width, $max_height );
}

function wp_convert_hr_to_bytes( $size ) {
	$size  = strtolower( $size );
	$bytes = (int) $size;
	if ( strpos( $size, 'k' ) !== false )
		$bytes = intval( $size ) * KB_IN_BYTES;
	elseif ( strpos( $size, 'm' ) !== false )
		$bytes = intval($size) * MB_IN_BYTES;
	elseif ( strpos( $size, 'g' ) !== false )
		$bytes = intval( $size ) * GB_IN_BYTES;
	return $bytes;
}

function wp_max_upload_size() {
	$u_bytes = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
	$p_bytes = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );

	return apply_filters( 'upload_size_limit', min( $u_bytes, $p_bytes ), $u_bytes, $p_bytes );
}

function wp_get_image_editor( $path, $args = array() ) {
	$args['path'] = $path;

	if ( ! isset( $args['mime_type'] ) ) {
		$file_info = wp_check_filetype( $args['path'] );

		// If $file_info['type'] is false, then we let the editor attempt to
		// figure out the file type, rather than forcing a failure based on extension.
		if ( isset( $file_info ) && $file_info['type'] )
			$args['mime_type'] = $file_info['type'];
	}

	$implementation = _wp_image_editor_choose( $args );

	if ( $implementation ) {
		$editor = new $implementation( $path );
		$loaded = $editor->load();

		if ( is_wp_error( $loaded ) )
			return $loaded;

		return $editor;
	}

	return new WP_Error( 'image_no_editor', __('No editor could be selected.') );
}

function wp_image_editor_supports( $args = array() ) {
	return (bool) _wp_image_editor_choose( $args );
}

function _wp_image_editor_choose( $args = array() ) {
	require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
	require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
	require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

	$implementations = apply_filters( 'wp_image_editors', array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' ) );

	foreach ( $implementations as $implementation ) {
		if ( ! call_user_func( array( $implementation, 'test' ), $args ) )
			continue;

		if ( isset( $args['mime_type'] ) &&
			! call_user_func(
				array( $implementation, 'supports_mime_type' ),
				$args['mime_type'] ) ) {
			continue;
		}

		if ( isset( $args['methods'] ) &&
			 array_diff( $args['methods'], get_class_methods( $implementation ) ) ) {
			continue;
		}

		return $implementation;
	}

	return false;
}

function wp_plupload_default_settings() {
	$wp_scripts = wp_scripts();

	$data = $wp_scripts->get_data( 'wp-plupload', 'data' );
	if ( $data && false !== strpos( $data, '_wpPluploadSettings' ) )
		return;

	$max_upload_size = wp_max_upload_size();

	$defaults = array(
		'runtimes'            => 'html5,flash,silverlight,html4',
		'file_data_name'      => 'async-upload', // key passed to $_FILE.
		'url'                 => admin_url( 'async-upload.php', 'relative' ),
		'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
		'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
		'filters' => array(
			'max_file_size'   => $max_upload_size . 'b',
		),
	);

	// Currently only iOS Safari supports multiple files uploading but iOS 7.x has a bug that prevents uploading of videos
	// when enabled. See #29602.
	if ( wp_is_mobile() && strpos( $_SERVER['HTTP_USER_AGENT'], 'OS 7_' ) !== false &&
		strpos( $_SERVER['HTTP_USER_AGENT'], 'like Mac OS X' ) !== false ) {

		$defaults['multi_selection'] = false;
	}

	$defaults = apply_filters( 'plupload_default_settings', $defaults );

	$params = array(
		'action' => 'upload-attachment',
	);

	$params = apply_filters( 'plupload_default_params', $params );
	$params['_wpnonce'] = wp_create_nonce( 'media-form' );
	$defaults['multipart_params'] = $params;

	$settings = array(
		'defaults' => $defaults,
		'browser'  => array(
			'mobile'    => wp_is_mobile(),
			'supported' => _device_can_upload(),
		),
		'limitExceeded' => is_multisite() && ! is_upload_space_available()
	);

	$script = 'var _wpPluploadSettings = ' . wp_json_encode( $settings ) . ';';

	if ( $data )
		$script = "$data\n$script";

	$wp_scripts->add_data( 'wp-plupload', 'data', $script );
}

function wp_prepare_attachment_for_js( $attachment ) {
	if ( ! $attachment = get_post( $attachment ) )
		return;

	if ( 'attachment' != $attachment->post_type )
		return;

	$meta = wp_get_attachment_metadata( $attachment->ID );
	if ( false !== strpos( $attachment->post_mime_type, '/' ) )
		list( $type, $subtype ) = explode( '/', $attachment->post_mime_type );
	else
		list( $type, $subtype ) = array( $attachment->post_mime_type, '' );

	$attachment_url = wp_get_attachment_url( $attachment->ID );

	$response = array(
		'id'          => $attachment->ID,
		'title'       => $attachment->post_title,
		'filename'    => wp_basename( get_attached_file( $attachment->ID ) ),
		'url'         => $attachment_url,
		'link'        => get_attachment_link( $attachment->ID ),
		'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
		'author'      => $attachment->post_author,
		'description' => $attachment->post_content,
		'caption'     => $attachment->post_excerpt,
		'name'        => $attachment->post_name,
		'status'      => $attachment->post_status,
		'uploadedTo'  => $attachment->post_parent,
		'date'        => strtotime( $attachment->post_date_gmt ) * 1000,
		'modified'    => strtotime( $attachment->post_modified_gmt ) * 1000,
		'menuOrder'   => $attachment->menu_order,
		'mime'        => $attachment->post_mime_type,
		'type'        => $type,
		'subtype'     => $subtype,
		'icon'        => wp_mime_type_icon( $attachment->ID ),
		'dateFormatted' => mysql2date( 'F j, Y', $attachment->post_date ),
		'nonces'      => array(
			'update' => false,
			'delete' => false,
			'edit'   => false
		),
		'editLink'   => false,
		'meta'       => false,
	);

	$author = new WP_User( $attachment->post_author );
	$response['authorName'] = $author->display_name;

	if ( $attachment->post_parent ) {
		$post_parent = get_post( $attachment->post_parent );
	} else {
		$post_parent = false;
	}

	if ( $post_parent ) {
		$parent_type = get_post_type_object( $post_parent->post_type );
		if ( $parent_type && $parent_type->show_ui && current_user_can( 'edit_post', $attachment->post_parent ) ) {
			$response['uploadedToLink'] = get_edit_post_link( $attachment->post_parent, 'raw' );
		}
		$response['uploadedToTitle'] = $post_parent->post_title ? $post_parent->post_title : __( '(no title)' );
	}

	$attached_file = get_attached_file( $attachment->ID );

	if ( isset( $meta['filesize'] ) ) {
		$bytes = $meta['filesize'];
	} elseif ( file_exists( $attached_file ) ) {
		$bytes = filesize( $attached_file );
	} else {
		$bytes = '';
	}

	if ( $bytes ) {
		$response['filesizeInBytes'] = $bytes;
		$response['filesizeHumanReadable'] = size_format( $bytes );
	}

	if ( current_user_can( 'edit_post', $attachment->ID ) ) {
		$response['nonces']['update'] = wp_create_nonce( 'update-post_' . $attachment->ID );
		$response['nonces']['edit'] = wp_create_nonce( 'image_editor-' . $attachment->ID );
		$response['editLink'] = get_edit_post_link( $attachment->ID, 'raw' );
	}

	if ( current_user_can( 'delete_post', $attachment->ID ) )
		$response['nonces']['delete'] = wp_create_nonce( 'delete-post_' . $attachment->ID );

	if ( $meta && 'image' === $type ) {
		$sizes = array();

		$possible_sizes = apply_filters( 'image_size_names_choose', array(
			'thumbnail' => 'Thumbnail',
			'medium'    => 'Medium',
			'large'     => 'Large',
			'full'      => 'Full Size',
		) );
		unset( $possible_sizes['full'] );

		foreach ( $possible_sizes as $size => $label ) {
			if ( $downsize = apply_filters( 'image_downsize', false, $attachment->ID, $size ) ) {
				if ( ! $downsize[3] )
					continue;
				$sizes[ $size ] = array(
					'height'      => $downsize[2],
					'width'       => $downsize[1],
					'url'         => $downsize[0],
					'orientation' => $downsize[2] > $downsize[1] ? 'portrait' : 'landscape',
				);
			} elseif ( isset( $meta['sizes'][ $size ] ) ) {
				if ( ! isset( $base_url ) )
					$base_url = str_replace( wp_basename( $attachment_url ), '', $attachment_url );

				// Nothing from the filter, so consult image metadata if we have it.
				$size_meta = $meta['sizes'][ $size ];

				list( $width, $height ) = image_constrain_size_for_editor( $size_meta['width'], $size_meta['height'], $size, 'edit' );

				$sizes[ $size ] = array(
					'height'      => $height,
					'width'       => $width,
					'url'         => $base_url . $size_meta['file'],
					'orientation' => $height > $width ? 'portrait' : 'landscape',
				);
			}
		}

		$sizes['full'] = array( 'url' => $attachment_url );

		if ( isset( $meta['height'], $meta['width'] ) ) {
			$sizes['full']['height'] = $meta['height'];
			$sizes['full']['width'] = $meta['width'];
			$sizes['full']['orientation'] = $meta['height'] > $meta['width'] ? 'portrait' : 'landscape';
		}

		$response = array_merge( $response, array( 'sizes' => $sizes ), $sizes['full'] );
	} elseif ( $meta && 'video' === $type ) {
		if ( isset( $meta['width'] ) )
			$response['width'] = (int) $meta['width'];
		if ( isset( $meta['height'] ) )
			$response['height'] = (int) $meta['height'];
	}

	if ( $meta && ( 'audio' === $type || 'video' === $type ) ) {
		if ( isset( $meta['length_formatted'] ) )
			$response['fileLength'] = $meta['length_formatted'];

		$response['meta'] = array();
		foreach ( wp_get_attachment_id3_keys( $attachment, 'js' ) as $key => $label ) {
			$response['meta'][ $key ] = false;

			if ( ! empty( $meta[ $key ] ) ) {
				$response['meta'][ $key ] = $meta[ $key ];
			}
		}

		$id = get_post_thumbnail_id( $attachment->ID );
		if ( ! empty( $id ) ) {
			list( $src, $width, $height ) = wp_get_attachment_image_src( $id, 'full' );
			$response['image'] = compact( 'src', 'width', 'height' );
			list( $src, $width, $height ) = wp_get_attachment_image_src( $id, 'thumbnail' );
			$response['thumb'] = compact( 'src', 'width', 'height' );
		} else {
			$src = wp_mime_type_icon( $attachment->ID );
			$width = 48;
			$height = 64;
			$response['image'] = compact( 'src', 'width', 'height' );
			$response['thumb'] = compact( 'src', 'width', 'height' );
		}
	}

	if ( function_exists('get_compat_media_markup') )
		$response['compat'] = get_compat_media_markup( $attachment->ID, array( 'in_modal' => true ) );

	return apply_filters( 'wp_prepare_attachment_for_js', $response, $attachment, $meta );
}

function wp_enqueue_media( $args = array() ) {
	if ( did_action( 'wp_enqueue_media' ) )
		return;

	global $content_width, $wpdb, $wp_locale;

	$defaults = array(
		'post' => null,
	);
	$args = wp_parse_args( $args, $defaults );
	$tabs = array(
		// handler action suffix => tab label
		'type'     => '',
		'type_url' => '',
		'gallery'  => '',
		'library'  => '',
	);

	/** This filter is documented in wp-admin/includes/media.php */
	$tabs = apply_filters( 'media_upload_tabs', $tabs );
	unset( $tabs['type'], $tabs['type_url'], $tabs['gallery'], $tabs['library'] );

	$props = array(
		'link'  => get_option( 'image_default_link_type' ), // db default is 'file'
		'align' => get_option( 'image_default_align' ), // empty default
		'size'  => get_option( 'image_default_size' ),  // empty default
	);

	$exts = array_merge( wp_get_audio_extensions(), wp_get_video_extensions() );
	$mimes = get_allowed_mime_types();
	$ext_mimes = array();
	foreach ( $exts as $ext ) {
		foreach ( $mimes as $ext_preg => $mime_match ) {
			if ( preg_match( '#' . $ext . '#i', $ext_preg ) ) {
				$ext_mimes[ $ext ] = $mime_match;
				break;
			}
		}
	}

	$has_audio = $wpdb->get_var( "
		SELECT ID
		FROM $wpdb->posts
		WHERE post_type = 'attachment'
		AND post_mime_type LIKE 'audio%'
		LIMIT 1
	" );
	$has_video = $wpdb->get_var( "
		SELECT ID
		FROM $wpdb->posts
		WHERE post_type = 'attachment'
		AND post_mime_type LIKE 'video%'
		LIMIT 1
	" );
	$months = $wpdb->get_results( $wpdb->prepare( "
		SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
		FROM $wpdb->posts
		WHERE post_type = %s
		ORDER BY post_date DESC
	", 'attachment' ) );
	foreach ( $months as $month_year ) {
		$month_year->text = sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $month_year->month ), $month_year->year );
	}

	$settings = array(
		'tabs'      => $tabs,
		'tabUrl'    => add_query_arg( array( 'chromeless' => true ), admin_url('media-upload.php') ),
		'mimeTypes' => wp_list_pluck( get_post_mime_types(), 0 ),
		/** This filter is documented in wp-admin/includes/media.php */
		'captions'  => ! apply_filters( 'disable_captions', '' ),
		'nonce'     => array(
			'sendToEditor' => wp_create_nonce( 'media-send-to-editor' ),
		),
		'post'    => array(
			'id' => 0,
		),
		'defaultProps' => $props,
		'attachmentCounts' => array(
			'audio' => ( $has_audio ) ? 1 : 0,
			'video' => ( $has_video ) ? 1 : 0
		),
		'embedExts'    => $exts,
		'embedMimes'   => $ext_mimes,
		'contentWidth' => $content_width,
		'months'       => $months,
		'mediaTrash'   => MEDIA_TRASH ? 1 : 0
	);

	$post = null;
	if ( isset( $args['post'] ) ) {
		$post = get_post( $args['post'] );
		$settings['post'] = array(
			'id' => $post->ID,
			'nonce' => wp_create_nonce( 'update-post_' . $post->ID ),
		);

		$thumbnail_support = current_theme_supports( 'post-thumbnails', $post->post_type ) && post_type_supports( $post->post_type, 'thumbnail' );
		if ( ! $thumbnail_support && 'attachment' === $post->post_type && $post->post_mime_type ) {
			if ( wp_attachment_is( 'audio', $post ) ) {
				$thumbnail_support = post_type_supports( 'attachment:audio', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:audio' );
			} elseif ( wp_attachment_is( 'video', $post ) ) {
				$thumbnail_support = post_type_supports( 'attachment:video', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:video' );
			}
		}

		if ( $thumbnail_support ) {
			$featured_image_id = get_post_meta( $post->ID, '_thumbnail_id', true );
			$settings['post']['featuredImageId'] = $featured_image_id ? $featured_image_id : -1;
		}
	}

	if ( $post ) {
		$post_type_object = get_post_type_object( $post->post_type );
	} else {
		$post_type_object = get_post_type_object( 'post' );
	}

	$strings = array(
		'url'         => 'URL',
		'addMedia'    => 'Add Media',
		'search'      => 'Search',
		'select'      => 'Select',
		'cancel'      => 'Cancel',
		'update'      => 'Update',
		'replace'     => 'Replace',
		'remove'      => 'Remove',
		'back'        => 'Back',
		'selected'    => '%d selected',
		'dragInfo'    => 'Drag and drop to reorder media files.',
		'uploadFilesTitle'  => 'Upload Files',
		'uploadImagesTitle' => 'Upload Images',
		'mediaLibraryTitle'      => 'Media Library',
		'insertMediaTitle'       => 'Insert Media',
		'createNewGallery'       => 'Create a new gallery',
		'createNewPlaylist'      => 'Create a new playlist',
		'createNewVideoPlaylist' => 'Create a new video playlist',
		'returnToLibrary'        => '&#8592; Return to library',
		'allMediaItems'          => 'All media items',
		'allDates'               => 'All dates',
		'noItemsFound'           => 'No items found.',
		'insertIntoPost'         => $post_type_object->labels->insert_into_item,
		'unattached'             => 'Unattached',
		'trash'                  => 'Trash',
		'uploadedToThisPost'     => $post_type_object->labels->uploaded_to_this_item,
		'warnDelete'             => "You are about to permanently delete this item.\n  'Cancel' to stop, 'OK' to delete.",
		'warnBulkDelete'         => "You are about to permanently delete these items.\n  'Cancel' to stop, 'OK' to delete.",
		'warnBulkTrash'          => "You are about to trash these items.\n  'Cancel' to stop, 'OK' to delete.",
		'bulkSelect'             => 'Bulk Select',
		'cancelSelection'        => 'Cancel Selection',
		'trashSelected'          => 'Trash Selected',
		'untrashSelected'        => 'Untrash Selected',
		'deleteSelected'         => 'Delete Selected',
		'deletePermanently'      => 'Delete Permanently',
		'apply'                  => 'Apply',
		'filterByDate'           => 'Filter by date',
		'filterByType'           => 'Filter by type',
		'searchMediaLabel'       => 'Search Media',
		'noMedia'                => 'No media files found.',
		'attachmentDetails'  => 'Attachment Details',
		'insertFromUrlTitle' => 'Insert from URL',

		'setFeaturedImageTitle' => $post_type_object->labels->featured_image,
		'setFeaturedImage'      => $post_type_object->labels->set_featured_image,

		'createGalleryTitle' => 'Create Gallery',
		'editGalleryTitle'   => 'Edit Gallery',
		'cancelGalleryTitle' => '&#8592; Cancel Gallery',
		'insertGallery'      => 'Insert gallery',
		'updateGallery'      => 'Update gallery',
		'addToGallery'       => 'Add to gallery',
		'addToGalleryTitle'  => 'Add to Gallery',
		'reverseOrder'       => 'Reverse order',
		'imageDetailsTitle'     => 'Image Details',
		'imageReplaceTitle'     => 'Replace Image',
		'imageDetailsCancel'    => 'Cancel Edit',
		'editImage'             => 'Edit Image',
		'chooseImage' => 'Choose Image',
		'selectAndCrop' => 'Select and Crop',
		'skipCropping' => 'Skip Cropping',
		'cropImage' => 'Crop Image',
		'cropYourImage' => 'Crop your image',
		'cropping' => 'Cropping&hellip;',
		'suggestedDimensions' => 'Suggested image dimensions:',
		'cropError' => 'There has been an error cropping your image.',
		'audioDetailsTitle'     => 'Audio Details',
		'audioReplaceTitle'     => 'Replace Audio',
		'audioAddSourceTitle'   => 'Add Audio Source',
		'audioDetailsCancel'    => 'Cancel Edit',
		'videoDetailsTitle'     => 'Video Details',
		'videoReplaceTitle'     => 'Replace Video',
		'videoAddSourceTitle'   => 'Add Video Source',
		'videoDetailsCancel'    => 'Cancel Edit',
		'videoSelectPosterImageTitle' => 'Select Poster Image',
		'videoAddTrackTitle'	=> 'Add Subtitles',
 		'playlistDragInfo'    => 'Drag and drop to reorder tracks.',
 		'createPlaylistTitle' => 'Create Audio Playlist',
 		'editPlaylistTitle'   => 'Edit Audio Playlist',
 		'cancelPlaylistTitle' => '&#8592; Cancel Audio Playlist',
 		'insertPlaylist'      => 'Insert audio playlist',
 		'updatePlaylist'      => 'Update audio playlist',
 		'addToPlaylist'       => 'Add to audio playlist',
 		'addToPlaylistTitle'  => 'Add to Audio Playlist',
 		'videoPlaylistDragInfo'    => 'Drag and drop to reorder videos.',
 		'createVideoPlaylistTitle' => 'Create Video Playlist',
 		'editVideoPlaylistTitle'   => 'Edit Video Playlist',
 		'cancelVideoPlaylistTitle' => '&#8592; Cancel Video Playlist',
 		'insertVideoPlaylist'      => 'Insert video playlist',
 		'updateVideoPlaylist'      => 'Update video playlist',
 		'addToVideoPlaylist'       => 'Add to video playlist',
 		'addToVideoPlaylistTitle'  => 'Add to Video Playlist',
	);

	$settings = apply_filters( 'media_view_settings', $settings, $post );

	$strings = apply_filters( 'media_view_strings', $strings,  $post );

	$strings['settings'] = $settings;

	wp_enqueue_script( 'media-editor' );
	wp_localize_script( 'media-views', '_wpMediaViewsL10n', $strings );

	wp_enqueue_script( 'media-audiovideo' );
	wp_enqueue_style( 'media-views' );
	if ( is_admin() ) {
		wp_enqueue_script( 'mce-view' );
		wp_enqueue_script( 'image-edit' );
	}
	wp_enqueue_style( 'imgareaselect' );
	wp_plupload_default_settings();

	require_once ABSPATH . WPINC . '/media-template.php';
	add_action( 'admin_footer', 'wp_print_media_templates' );
	add_action( 'wp_footer', 'wp_print_media_templates' );
	add_action( 'customize_controls_print_footer_scripts', 'wp_print_media_templates' );

	do_action( 'wp_enqueue_media' );
}

function get_attached_media( $type, $post = 0 ) {
	if ( ! $post = get_post( $post ) )
		return array();

	$args = array(
		'post_parent' => $post->ID,
		'post_type' => 'attachment',
		'post_mime_type' => $type,
		'posts_per_page' => -1,
		'orderby' => 'menu_order',
		'order' => 'ASC',
	);

	$args = apply_filters( 'get_attached_media_args', $args, $type, $post );

	$children = get_children( $args );

	return (array) apply_filters( 'get_attached_media', $children, $type, $post );
}

function get_media_embedded_in_content( $content, $types = null ) {
	$html = array();

	$allowed_media_types = apply_filters( 'media_embedded_in_content_allowed_types', array( 'audio', 'video', 'object', 'embed', 'iframe' ) );

	if ( ! empty( $types ) ) {
		if ( ! is_array( $types ) ) {
			$types = array( $types );
		}

		$allowed_media_types = array_intersect( $allowed_media_types, $types );
	}

	$tags = implode( '|', $allowed_media_types );

	if ( preg_match_all( '#<(?P<tag>' . $tags . ')[^<]*?(?:>[\s\S]*?<\/(?P=tag)>|\s*\/>)#', $content, $matches ) ) {
		foreach ( $matches[0] as $match ) {
			$html[] = $match;
		}
	}

	return $html;
}

function get_post_galleries( $post, $html = true ) {
	if ( ! $post = get_post( $post ) )
		return array();

	if ( ! has_shortcode( $post->post_content, 'gallery' ) )
		return array();

	$galleries = array();
	if ( preg_match_all( '/' . get_shortcode_regex() . '/s', $post->post_content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $shortcode ) {
			if ( 'gallery' === $shortcode[2] ) {
				$srcs = array();

				$gallery = do_shortcode_tag( $shortcode );
				if ( $html ) {
					$galleries[] = $gallery;
				} else {
					preg_match_all( '#src=([\'"])(.+?)\1#is', $gallery, $src, PREG_SET_ORDER );
					if ( ! empty( $src ) ) {
						foreach ( $src as $s )
							$srcs[] = $s[2];
					}

					$data = shortcode_parse_atts( $shortcode[3] );
					$data['src'] = array_values( array_unique( $srcs ) );
					$galleries[] = $data;
				}
			}
		}
	}

	return apply_filters( 'get_post_galleries', $galleries, $post );
}

function get_post_gallery( $post = 0, $html = true ) {
	$galleries = get_post_galleries( $post, $html );
	$gallery = reset( $galleries );

	return apply_filters( 'get_post_gallery', $gallery, $post, $galleries );
}

function get_post_galleries_images( $post = 0 ) {
	$galleries = get_post_galleries( $post, false );
	return wp_list_pluck( $galleries, 'src' );
}

function get_post_gallery_images( $post = 0 ) {
	$gallery = get_post_gallery( $post, false );
	return empty( $gallery['src'] ) ? array() : $gallery['src'];
}

function wp_maybe_generate_attachment_metadata( $attachment ) {
	if ( empty( $attachment ) || ( empty( $attachment->ID ) || ! $attachment_id = (int) $attachment->ID ) ) {
		return;
	}

	$file = get_attached_file( $attachment_id );
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $meta ) && file_exists( $file ) ) {
		$_meta = get_post_meta( $attachment_id );
		$regeneration_lock = 'wp_generating_att_' . $attachment_id;
		if ( ! array_key_exists( '_wp_attachment_metadata', $_meta ) && ! get_transient( $regeneration_lock ) ) {
			set_transient( $regeneration_lock, $file );
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );
			delete_transient( $regeneration_lock );
		}
	}
}

function attachment_url_to_postid( $url ) {
	global $wpdb;

	$dir = wp_get_upload_dir();
	$path = $url;

	$site_url = parse_url( $dir['url'] );
	$image_path = parse_url( $path );

	//force the protocols to match if needed
	if ( isset( $image_path['scheme'] ) && ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
		$path = str_replace( $image_path['scheme'], $site_url['scheme'], $path );
	}

	if ( 0 === strpos( $path, $dir['baseurl'] . '/' ) ) {
		$path = substr( $path, strlen( $dir['baseurl'] . '/' ) );
	}

	$sql = $wpdb->prepare(
		"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
		$path
	);
	$post_id = $wpdb->get_var( $sql );

	return (int) apply_filters( 'attachment_url_to_postid', $post_id, $url );
}

function wpview_media_sandbox_styles() {
 	$version = 'ver=' . $GLOBALS['wp_version'];
 	$mediaelement = includes_url( "js/mediaelement/mediaelementplayer.min.css?$version" );
 	$wpmediaelement = includes_url( "js/mediaelement/wp-mediaelement.css?$version" );

	return array( $mediaelement, $wpmediaelement );
}
