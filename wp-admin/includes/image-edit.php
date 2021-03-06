<?php

function wp_image_editor($post_id, $msg = false) {
	$nonce = wp_create_nonce("image_editor-$post_id");
	$meta = wp_get_attachment_metadata($post_id);
	$thumb = image_get_intermediate_size($post_id, 'thumbnail');
	$sub_sizes = isset($meta['sizes']) && is_array($meta['sizes']);
	$note = '';

	if ( isset( $meta['width'], $meta['height'] ) )
		$big = max( $meta['width'], $meta['height'] );
	else
		die( 'Image data does not exist. Please re-upload the image.' );

	$sizer = $big > 400 ? 400 / $big : 1;

	$backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
	$can_restore = false;
	if ( ! empty( $backup_sizes ) && isset( $backup_sizes['full-orig'], $meta['file'] ) )
		$can_restore = $backup_sizes['full-orig']['file'] != basename( $meta['file'] );

	if ( $msg ) {
		if ( isset($msg->error) )
			$note = "<div class='error'><p>$msg->error</p></div>";
		elseif ( isset($msg->msg) )
			$note = "<div class='updated'><p>$msg->msg</p></div>";
	}

	?>
	<div class="imgedit-wrap wp-clearfix">
	<div id="imgedit-panel-<?php echo $post_id; ?>">

	<div class="imgedit-settings">
	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h2>Scale Image</h2>
		<button type="button" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;" aria-expanded="false"><span class="screen-reader-text">Scale Image Help</span></button>
		<div class="imgedit-help">
		<p><?php _e('You can proportionally scale the original image. For best results, scaling should be done before you crop, flip, or rotate. Images can only be scaled down, not up.'); ?></p>
		</div>
		<?php if ( isset( $meta['width'], $meta['height'] ) ): ?>
		<p><?php printf( 'Original dimensions %s', $meta['width'] . ' &times; ' . $meta['height'] ); ?></p>
		<?php endif ?>
		<div class="imgedit-submit">

		<fieldset class="imgedit-scale">
		<legend><?php _e( 'New dimensions:' ); ?></legend>
		<div class="nowrap">
		<label><span class="screen-reader-text">scale width</span>
		<input type="text" id="imgedit-scale-width-<?php echo $post_id; ?>" onkeyup="imageEdit.scaleChanged(<?php echo $post_id; ?>, 1)" onblur="imageEdit.scaleChanged(<?php echo $post_id; ?>, 1)" value="<?php echo isset( $meta['width'] ) ? $meta['width'] : 0; ?>" />
		</label>
		<span class="imgedit-separator">&times;</span>
		<label><span class="screen-reader-text">scale height</span>
		<input type="text" id="imgedit-scale-height-<?php echo $post_id; ?>" onkeyup="imageEdit.scaleChanged(<?php echo $post_id; ?>, 0)" onblur="imageEdit.scaleChanged(<?php echo $post_id; ?>, 0)" value="<?php echo isset( $meta['height'] ) ? $meta['height'] : 0; ?>" />
		</label>
		<span class="imgedit-scale-warn" id="imgedit-scale-warn-<?php echo $post_id; ?>">!</span>
		<input id="imgedit-scale-button" type="button" onclick="imageEdit.action(<?php echo "$post_id, '$nonce'"; ?>, 'scale')" class="button button-primary" value="<?php esc_attr_e( 'Scale' ); ?>" />
 		</div>
		</fieldset>

		</div>
	</div>
	</div>

<?php if ( $can_restore ) { ?>

	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h2><button type="button" onclick="imageEdit.toggleHelp(this);" class="button-link">Restore Original Image <span class="dashicons dashicons-arrow-down imgedit-help-toggle"></span></button></h2>
		<div class="imgedit-help">
		<p>Discard any changes and restore the original image.<?php

		if ( !defined('IMAGE_EDIT_OVERWRITE') || !IMAGE_EDIT_OVERWRITE )
			echo ' Previously edited copies of the image will not be deleted.';

		?></p>
		<div class="imgedit-submit">
		<input type="button" onclick="imageEdit.action(<?php echo "$post_id, '$nonce'"; ?>, 'restore')" class="button button-primary" value="<?php esc_attr_e( 'Restore image' ); ?>" <?php echo $can_restore; ?> />
		</div>
		</div>
	</div>
	</div>

<?php } ?>

	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h2>Image Crop</h2>
		<button type="button" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;" aria-expanded="false"><span class="screen-reader-text"><?php esc_html_e( 'Image Crop Help' ); ?></span></button>

		<div class="imgedit-help">
		<p>To crop the image, click on it and drag to make your selection.</p>

		<p><strong>Crop Aspect Ratio</strong><br />
		The aspect ratio is the relationship between the width and height. You can preserve the aspect ratio by holding down the shift key while resizing your selection. Use the input box to specify the aspect ratio, e.g. 1:1 (square), 4:3, 16:9, etc.</p>

		<p><strong>Crop Selection</strong><br />
		Once you have made your selection, you can adjust it by entering the size in pixels. The minimum selection size is the thumbnail size as set in the Media settings.</p>
		</div>
	</div>

	<fieldset class="imgedit-crop-ratio">
		<legend>Aspect ratio:</legend>
		<div class="nowrap">
		<label><span class="screen-reader-text">crop ratio width</span>
		<input type="text" id="imgedit-crop-width-<?php echo $post_id; ?>" onkeyup="imageEdit.setRatioSelection(<?php echo $post_id; ?>, 0, this)" />
		</label>
		<span class="imgedit-separator">:</span>
		<label><span class="screen-reader-text">crop ratio height</span>
		<input type="text" id="imgedit-crop-height-<?php echo $post_id; ?>" onkeyup="imageEdit.setRatioSelection(<?php echo $post_id; ?>, 1, this)" />
		</label>
		</div>
	</fieldset>

	<fieldset id="imgedit-crop-sel-<?php echo $post_id; ?>" class="imgedit-crop-sel">
		<legend>Selection:</legend>
		<div class="nowrap">
		<label><span class="screen-reader-text">selection width</span>
		<input type="text" id="imgedit-sel-width-<?php echo $post_id; ?>" onkeyup="imageEdit.setNumSelection(<?php echo $post_id; ?>)" />
		</label>
		<span class="imgedit-separator">&times;</span>
		<label><span class="screen-reader-text">selection height</span>
		<input type="text" id="imgedit-sel-height-<?php echo $post_id; ?>" onkeyup="imageEdit.setNumSelection(<?php echo $post_id; ?>)" />
		</label>
		</div>
	</fieldset>

	</div>

	<?php if ( $thumb && $sub_sizes ) {
		$thumb_img = wp_constrain_dimensions( $thumb['width'], $thumb['height'], 160, 120 );
	?>

	<div class="imgedit-group imgedit-applyto">
	<div class="imgedit-group-top">
		<h2>Thumbnail Settings</h2>
		<button type="button" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;" aria-expanded="false"><span class="screen-reader-text"><?php esc_html_e( 'Thumbnail Settings Help' ); ?></span></button>
		<p class="imgedit-help"><?php _e('You can edit the image while preserving the thumbnail. For example, you may wish to have a square thumbnail that displays just a section of the image.'); ?></p>
	</div>

	<figure class="imgedit-thumbnail-preview">
		<img src="<?php echo $thumb['url']; ?>" width="<?php echo $thumb_img[0]; ?>" height="<?php echo $thumb_img[1]; ?>" class="imgedit-size-preview" alt="" draggable="false" />
		<figcaption class="imgedit-thumbnail-preview-caption"><?php _e( 'Current thumbnail' ); ?></figcaption>
	</figure>

	<div id="imgedit-save-target-<?php echo $post_id; ?>" class="imgedit-save-target">
	<fieldset>
		<legend><strong>Apply changes to:</strong></legend>

		<label class="imgedit-label">
		<input type="radio" name="imgedit-target-<?php echo $post_id; ?>" value="all" checked="checked" />
		All image sizes</label>

		<label class="imgedit-label">
		<input type="radio" name="imgedit-target-<?php echo $post_id; ?>" value="thumbnail" />
		Thumbnail</label>

		<label class="imgedit-label">
		<input type="radio" name="imgedit-target-<?php echo $post_id; ?>" value="nothumb" />
		All sizes except thumbnail</label>
	</fieldset>
	</div>
	</div>

	<?php } ?>

	</div>

	<div class="imgedit-panel-content wp-clearfix">
		<?php echo $note; ?>
		<div class="imgedit-menu wp-clearfix">
			<button type="button" onclick="imageEdit.crop(<?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-crop button disabled" disabled><span class="screen-reader-text"><?php esc_html_e( 'Crop' ); ?></span></button><?php

		// On some setups GD library does not provide imagerotate() - Ticket #11536
		if ( wp_image_editor_supports( array( 'mime_type' => get_post_mime_type( $post_id ), 'methods' => array( 'rotate' ) ) ) ) {
			$note_no_rotate = '';
	?>
			<button type="button" class="imgedit-rleft button" onclick="imageEdit.rotate( 90, <?php echo "$post_id, '$nonce'"; ?>, this)"><span class="screen-reader-text"><?php esc_html_e( 'Rotate counter-clockwise' ); ?></span></button>
			<button type="button" class="imgedit-rright button" onclick="imageEdit.rotate(-90, <?php echo "$post_id, '$nonce'"; ?>, this)"><span class="screen-reader-text"><?php esc_html_e( 'Rotate clockwise' ); ?></span></button>
	<?php } else {
			$note_no_rotate = '<p class="note-no-rotate"><em>Image rotation is not supported by your web host.</em></p>';
	?>
			<button type="button" class="imgedit-rleft button disabled" disabled></button>
			<button type="button" class="imgedit-rright button disabled" disabled></button>
	<?php } ?>

			<button type="button" onclick="imageEdit.flip(1, <?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-flipv button"><span class="screen-reader-text"><?php esc_html_e( 'Flip vertically' ); ?></span></button>
			<button type="button" onclick="imageEdit.flip(2, <?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-fliph button"><span class="screen-reader-text"><?php esc_html_e( 'Flip horizontally' ); ?></span></button>

			<button type="button" id="image-undo-<?php echo $post_id; ?>" onclick="imageEdit.undo(<?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-undo button disabled" disabled><span class="screen-reader-text"><?php esc_html_e( 'Undo' ); ?></span></button>
			<button type="button" id="image-redo-<?php echo $post_id; ?>" onclick="imageEdit.redo(<?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-redo button disabled" disabled><span class="screen-reader-text"><?php esc_html_e( 'Redo' ); ?></span></button>
			<?php echo $note_no_rotate; ?>
		</div>

		<input type="hidden" id="imgedit-sizer-<?php echo $post_id; ?>" value="<?php echo $sizer; ?>" />
		<input type="hidden" id="imgedit-history-<?php echo $post_id; ?>" value="" />
		<input type="hidden" id="imgedit-undone-<?php echo $post_id; ?>" value="0" />
		<input type="hidden" id="imgedit-selection-<?php echo $post_id; ?>" value="" />
		<input type="hidden" id="imgedit-x-<?php echo $post_id; ?>" value="<?php echo isset( $meta['width'] ) ? $meta['width'] : 0; ?>" />
		<input type="hidden" id="imgedit-y-<?php echo $post_id; ?>" value="<?php echo isset( $meta['height'] ) ? $meta['height'] : 0; ?>" />

		<div id="imgedit-crop-<?php echo $post_id; ?>" class="imgedit-crop-wrap">
		<img id="image-preview-<?php echo $post_id; ?>" onload="imageEdit.imgLoaded('<?php echo $post_id; ?>')" src="<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>?action=imgedit-preview&amp;_ajax_nonce=<?php echo $nonce; ?>&amp;postid=<?php echo $post_id; ?>&amp;rand=<?php echo rand(1, 99999); ?>" alt="" />
		</div>

		<div class="imgedit-submit">
			<input type="button" onclick="imageEdit.close(<?php echo $post_id; ?>, 1)" class="button imgedit-cancel-btn" value="<?php esc_attr_e( 'Cancel' ); ?>" />
			<input type="button" onclick="imageEdit.save(<?php echo "$post_id, '$nonce'"; ?>)" disabled="disabled" class="button button-primary imgedit-submit-btn" value="<?php esc_attr_e( 'Save' ); ?>" />
		</div>
	</div>

	</div>
	<div class="imgedit-wait" id="imgedit-wait-<?php echo $post_id; ?>"></div>
	<script type="text/javascript">jQuery( function() { imageEdit.init(<?php echo $post_id; ?>); });</script>
	<div class="hidden" id="imgedit-leaving-<?php echo $post_id; ?>"><?php _e("There are unsaved changes that will be lost. 'OK' to continue, 'Cancel' to return to the Image Editor."); ?></div>
	</div>
<?php
}

function wp_stream_image( $image, $mime_type, $post_id ) {
	if ( $image instanceof WP_Image_Editor ) {

		$image = apply_filters( 'image_editor_save_pre', $image, $post_id );

		if ( is_wp_error( $image->stream( $mime_type ) ) )
			return false;

		return true;
	} else {
		_deprecated_argument( __FUNCTION__, '3.5', '$image needs to be an WP_Image_Editor object' );

		$image = apply_filters( 'image_save_pre', $image, $post_id );

		switch ( $mime_type ) {
			case 'image/jpeg':
				header( 'Content-Type: image/jpeg' );
				return imagejpeg( $image, null, 90 );
			case 'image/png':
				header( 'Content-Type: image/png' );
				return imagepng( $image );
			case 'image/gif':
				header( 'Content-Type: image/gif' );
				return imagegif( $image );
			default:
				return false;
		}
	}
}

function wp_save_image_file( $filename, $image, $mime_type, $post_id ) {
	if ( $image instanceof WP_Image_Editor ) {

		/** This filter is documented in wp-admin/includes/image-edit.php */
		$image = apply_filters( 'image_editor_save_pre', $image, $post_id );

		$saved = apply_filters( 'wp_save_image_editor_file', null, $filename, $image, $mime_type, $post_id );

		if ( null !== $saved )
			return $saved;

		return $image->save( $filename, $mime_type );
	} else {
		_deprecated_argument( __FUNCTION__, '3.5', '$image needs to be an WP_Image_Editor object' );

		/** This filter is documented in wp-admin/includes/image-edit.php */
		$image = apply_filters( 'image_save_pre', $image, $post_id );

		$saved = apply_filters( 'wp_save_image_file', null, $filename, $image, $mime_type, $post_id );

		if ( null !== $saved )
			return $saved;

		switch ( $mime_type ) {
			case 'image/jpeg':

				/** This filter is documented in wp-includes/class-wp-image-editor.php */
				return imagejpeg( $image, $filename, apply_filters( 'jpeg_quality', 90, 'edit_image' ) );
			case 'image/png':
				return imagepng( $image, $filename );
			case 'image/gif':
				return imagegif( $image, $filename );
			default:
				return false;
		}
	}
}

function _image_get_preview_ratio($w, $h) {
	$max = max($w, $h);
	return $max > 400 ? (400 / $max) : 1;
}

function _rotate_image_resource($img, $angle) {
	_deprecated_function( __FUNCTION__, '3.5', 'Use WP_Image_Editor::rotate' );
	if ( function_exists('imagerotate') ) {
		$rotated = imagerotate($img, $angle, 0);
		if ( is_resource($rotated) ) {
			imagedestroy($img);
			$img = $rotated;
		}
	}
	return $img;
}

function _flip_image_resource($img, $horz, $vert) {
	_deprecated_function( __FUNCTION__, '3.5', 'Use WP_Image_Editor::flip' );
	$w = imagesx($img);
	$h = imagesy($img);
	$dst = wp_imagecreatetruecolor($w, $h);
	if ( is_resource($dst) ) {
		$sx = $vert ? ($w - 1) : 0;
		$sy = $horz ? ($h - 1) : 0;
		$sw = $vert ? -$w : $w;
		$sh = $horz ? -$h : $h;

		if ( imagecopyresampled($dst, $img, 0, 0, $sx, $sy, $w, $h, $sw, $sh) ) {
			imagedestroy($img);
			$img = $dst;
		}
	}
	return $img;
}

function _crop_image_resource($img, $x, $y, $w, $h) {
	$dst = wp_imagecreatetruecolor($w, $h);
	if ( is_resource($dst) ) {
		if ( imagecopy($dst, $img, 0, 0, $x, $y, $w, $h) ) {
			imagedestroy($img);
			$img = $dst;
		}
	}
	return $img;
}

function image_edit_apply_changes( $image, $changes ) {
	if ( is_resource( $image ) )
		_deprecated_argument( __FUNCTION__, '3.5', '$image needs to be an WP_Image_Editor object' );

	if ( !is_array($changes) )
		return $image;

	foreach ( $changes as $key => $obj ) {
		if ( isset($obj->r) ) {
			$obj->type = 'rotate';
			$obj->angle = $obj->r;
			unset($obj->r);
		} elseif ( isset($obj->f) ) {
			$obj->type = 'flip';
			$obj->axis = $obj->f;
			unset($obj->f);
		} elseif ( isset($obj->c) ) {
			$obj->type = 'crop';
			$obj->sel = $obj->c;
			unset($obj->c);
		}
		$changes[$key] = $obj;
	}

	// Combine operations.
	if ( count($changes) > 1 ) {
		$filtered = array($changes[0]);
		for ( $i = 0, $j = 1, $c = count( $changes ); $j < $c; $j++ ) {
			$combined = false;
			if ( $filtered[$i]->type == $changes[$j]->type ) {
				switch ( $filtered[$i]->type ) {
					case 'rotate':
						$filtered[$i]->angle += $changes[$j]->angle;
						$combined = true;
						break;
					case 'flip':
						$filtered[$i]->axis ^= $changes[$j]->axis;
						$combined = true;
						break;
				}
			}
			if ( !$combined )
				$filtered[++$i] = $changes[$j];
		}
		$changes = $filtered;
		unset($filtered);
	}

	if ( $image instanceof WP_Image_Editor ) {
		$image = apply_filters( 'wp_image_editor_before_change', $image, $changes );
	} elseif ( is_resource( $image ) ) {
		$image = apply_filters( 'image_edit_before_change', $image, $changes );
	}

	foreach ( $changes as $operation ) {
		switch ( $operation->type ) {
			case 'rotate':
				if ( $operation->angle != 0 ) {
					if ( $image instanceof WP_Image_Editor )
						$image->rotate( $operation->angle );
					else
						$image = _rotate_image_resource( $image, $operation->angle );
				}
				break;
			case 'flip':
				if ( $operation->axis != 0 )
					if ( $image instanceof WP_Image_Editor )
						$image->flip( ($operation->axis & 1) != 0, ($operation->axis & 2) != 0 );
					else
						$image = _flip_image_resource( $image, ( $operation->axis & 1 ) != 0, ( $operation->axis & 2 ) != 0 );
				break;
			case 'crop':
				$sel = $operation->sel;

				if ( $image instanceof WP_Image_Editor ) {
					$size = $image->get_size();
					$w = $size['width'];
					$h = $size['height'];

					$scale = 1 / _image_get_preview_ratio( $w, $h ); // discard preview scaling
					$image->crop( $sel->x * $scale, $sel->y * $scale, $sel->w * $scale, $sel->h * $scale );
				} else {
					$scale = 1 / _image_get_preview_ratio( imagesx( $image ), imagesy( $image ) ); // discard preview scaling
					$image = _crop_image_resource( $image, $sel->x * $scale, $sel->y * $scale, $sel->w * $scale, $sel->h * $scale );
				}
				break;
		}
	}

	return $image;
}


function stream_preview_image( $post_id ) {
	$post = get_post( $post_id );

	@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );

	$img = wp_get_image_editor( _load_image_to_edit_path( $post_id ) );

	if ( is_wp_error( $img ) ) {
		return false;
	}

	$changes = !empty($_REQUEST['history']) ? json_decode( wp_unslash($_REQUEST['history']) ) : null;
	if ( $changes )
		$img = image_edit_apply_changes( $img, $changes );

	// Scale the image.
	$size = $img->get_size();
	$w = $size['width'];
	$h = $size['height'];

	$ratio = _image_get_preview_ratio( $w, $h );
	$w2 = max ( 1, $w * $ratio );
	$h2 = max ( 1, $h * $ratio );

	if ( is_wp_error( $img->resize( $w2, $h2 ) ) )
		return false;

	return wp_stream_image( $img, $post->post_mime_type, $post_id );
}

function wp_restore_image($post_id) {
	$meta = wp_get_attachment_metadata($post_id);
	$file = get_attached_file($post_id);
	$backup_sizes = $old_backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
	$restored = false;
	$msg = new stdClass;

	if ( !is_array($backup_sizes) ) {
		$msg->error = 'Cannot load image metadata.';
		return $msg;
	}

	$parts = pathinfo($file);
	$suffix = time() . rand(100, 999);
	$default_sizes = get_intermediate_image_sizes();

	if ( isset($backup_sizes['full-orig']) && is_array($backup_sizes['full-orig']) ) {
		$data = $backup_sizes['full-orig'];

		if ( $parts['basename'] != $data['file'] ) {
			if ( defined('IMAGE_EDIT_OVERWRITE') && IMAGE_EDIT_OVERWRITE ) {

				// Delete only if it's an edited image.
				if ( preg_match('/-e[0-9]{13}\./', $parts['basename']) ) {
					wp_delete_file( $file );
				}
			} elseif ( isset( $meta['width'], $meta['height'] ) ) {
				$backup_sizes["full-$suffix"] = array('width' => $meta['width'], 'height' => $meta['height'], 'file' => $parts['basename']);
			}
		}

		$restored_file = path_join($parts['dirname'], $data['file']);
		$restored = update_attached_file($post_id, $restored_file);

		$meta['file'] = _wp_relative_upload_path( $restored_file );
		$meta['width'] = $data['width'];
		$meta['height'] = $data['height'];
	}

	foreach ( $default_sizes as $default_size ) {
		if ( isset($backup_sizes["$default_size-orig"]) ) {
			$data = $backup_sizes["$default_size-orig"];
			if ( isset($meta['sizes'][$default_size]) && $meta['sizes'][$default_size]['file'] != $data['file'] ) {
				if ( defined('IMAGE_EDIT_OVERWRITE') && IMAGE_EDIT_OVERWRITE ) {

					// Delete only if it's an edited image.
					if ( preg_match('/-e[0-9]{13}-/', $meta['sizes'][$default_size]['file']) ) {
						$delete_file = path_join( $parts['dirname'], $meta['sizes'][$default_size]['file'] );
						wp_delete_file( $delete_file );
					}
				} else {
					$backup_sizes["$default_size-{$suffix}"] = $meta['sizes'][$default_size];
				}
			}

			$meta['sizes'][$default_size] = $data;
		} else {
			unset($meta['sizes'][$default_size]);
		}
	}

	if ( ! wp_update_attachment_metadata( $post_id, $meta ) ||
		( $old_backup_sizes !== $backup_sizes && ! update_post_meta( $post_id, '_wp_attachment_backup_sizes', $backup_sizes ) ) ) {

		$msg->error = 'Cannot save image metadata.';
		return $msg;
	}

	if ( !$restored )
		$msg->error = 'Image metadata is inconsistent.';
	else
		$msg->msg = 'Image restored successfully.';

	return $msg;
}

function wp_save_image( $post_id ) {
	global $_wp_additional_image_sizes;

	$return = new stdClass;
	$success = $delete = $scaled = $nocrop = false;
	$post = get_post( $post_id );

	$img = wp_get_image_editor( _load_image_to_edit_path( $post_id, 'full' ) );
	if ( is_wp_error( $img ) ) {
		$return->error = esc_js( 'Unable to create new image.' );
		return $return;
	}

	$fwidth = !empty($_REQUEST['fwidth']) ? intval($_REQUEST['fwidth']) : 0;
	$fheight = !empty($_REQUEST['fheight']) ? intval($_REQUEST['fheight']) : 0;
	$target = !empty($_REQUEST['target']) ? preg_replace('/[^a-z0-9_-]+/i', '', $_REQUEST['target']) : '';
	$scale = !empty($_REQUEST['do']) && 'scale' == $_REQUEST['do'];

	if ( $scale && $fwidth > 0 && $fheight > 0 ) {
		$size = $img->get_size();
		$sX = $size['width'];
		$sY = $size['height'];

		// Check if it has roughly the same w / h ratio.
		$diff = round($sX / $sY, 2) - round($fwidth / $fheight, 2);
		if ( -0.1 < $diff && $diff < 0.1 ) {
			// Scale the full size image.
			if ( $img->resize( $fwidth, $fheight ) )
				$scaled = true;
		}

		if ( !$scaled ) {
			$return->error = esc_js( 'Error while saving the scaled image. Please reload the page and try again.' );
			return $return;
		}
	} elseif ( !empty($_REQUEST['history']) ) {
		$changes = json_decode( wp_unslash($_REQUEST['history']) );
		if ( $changes )
			$img = image_edit_apply_changes($img, $changes);
	} else {
		$return->error = esc_js( 'Nothing to save, the image has not changed.' );
		return $return;
	}

	$meta = wp_get_attachment_metadata($post_id);
	$backup_sizes = get_post_meta( $post->ID, '_wp_attachment_backup_sizes', true );

	if ( !is_array($meta) ) {
		$return->error = esc_js( 'Image data does not exist. Please re-upload the image.' );
		return $return;
	}

	if ( !is_array($backup_sizes) )
		$backup_sizes = array();

	$path = get_attached_file($post_id);
	$path_parts = pathinfo( $path );
	$filename = $path_parts['filename'];
	$suffix = time() . rand(100, 999);

	if ( defined('IMAGE_EDIT_OVERWRITE') && IMAGE_EDIT_OVERWRITE &&
		isset($backup_sizes['full-orig']) && $backup_sizes['full-orig']['file'] != $path_parts['basename'] ) {

		if ( 'thumbnail' == $target )
			$new_path = "{$path_parts['dirname']}/{$filename}-temp.{$path_parts['extension']}";
		else
			$new_path = $path;
	} else {
		while( true ) {
			$filename = preg_replace( '/-e([0-9]+)$/', '', $filename );
			$filename .= "-e{$suffix}";
			$new_filename = "{$filename}.{$path_parts['extension']}";
			$new_path = "{$path_parts['dirname']}/$new_filename";
			if ( file_exists($new_path) )
				$suffix++;
			else
				break;
		}
	}

	if ( !wp_save_image_file($new_path, $img, $post->post_mime_type, $post_id) ) {
		$return->error = esc_js( 'Unable to save the image.' );
		return $return;
	}

	if ( 'nothumb' == $target || 'all' == $target || 'full' == $target || $scaled ) {
		$tag = false;
		if ( isset($backup_sizes['full-orig']) ) {
			if ( ( !defined('IMAGE_EDIT_OVERWRITE') || !IMAGE_EDIT_OVERWRITE ) && $backup_sizes['full-orig']['file'] != $path_parts['basename'] )
				$tag = "full-$suffix";
		} else {
			$tag = 'full-orig';
		}

		if ( $tag )
			$backup_sizes[$tag] = array('width' => $meta['width'], 'height' => $meta['height'], 'file' => $path_parts['basename']);

		$success = ( $path === $new_path ) || update_attached_file( $post_id, $new_path );

		$meta['file'] = _wp_relative_upload_path( $new_path );

		$size = $img->get_size();
		$meta['width'] = $size['width'];
		$meta['height'] = $size['height'];

		if ( $success && ('nothumb' == $target || 'all' == $target) ) {
			$sizes = get_intermediate_image_sizes();
			if ( 'nothumb' == $target )
				$sizes = array_diff( $sizes, array('thumbnail') );
		}

		$return->fw = $meta['width'];
		$return->fh = $meta['height'];
	} elseif ( 'thumbnail' == $target ) {
		$sizes = array( 'thumbnail' );
		$success = $delete = $nocrop = true;
	}

	if ( isset( $sizes ) ) {
		$_sizes = array();

		foreach ( $sizes as $size ) {
			$tag = false;
			if ( isset( $meta['sizes'][$size] ) ) {
				if ( isset($backup_sizes["$size-orig"]) ) {
					if ( ( !defined('IMAGE_EDIT_OVERWRITE') || !IMAGE_EDIT_OVERWRITE ) && $backup_sizes["$size-orig"]['file'] != $meta['sizes'][$size]['file'] )
						$tag = "$size-$suffix";
				} else {
					$tag = "$size-orig";
				}

				if ( $tag )
					$backup_sizes[$tag] = $meta['sizes'][$size];
			}

			if ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
				$width  = intval( $_wp_additional_image_sizes[ $size ]['width'] );
				$height = intval( $_wp_additional_image_sizes[ $size ]['height'] );
				$crop   = ( $nocrop ) ? false : $_wp_additional_image_sizes[ $size ]['crop'];
			} else {
				$height = get_option( "{$size}_size_h" );
				$width  = get_option( "{$size}_size_w" );
				$crop   = ( $nocrop ) ? false : get_option( "{$size}_crop" );
			}

			$_sizes[ $size ] = array( 'width' => $width, 'height' => $height, 'crop' => $crop );
		}

		$meta['sizes'] = array_merge( $meta['sizes'], $img->multi_resize( $_sizes ) );
	}

	unset( $img );

	if ( $success ) {
		wp_update_attachment_metadata( $post_id, $meta );
		update_post_meta( $post_id, '_wp_attachment_backup_sizes', $backup_sizes);

		if ( $target == 'thumbnail' || $target == 'all' || $target == 'full' ) {
			// Check if it's an image edit from attachment edit screen
			if ( ! empty( $_REQUEST['context'] ) && 'edit-attachment' == $_REQUEST['context'] ) {
				$thumb_url = wp_get_attachment_image_src( $post_id, array( 900, 600 ), true );
				$return->thumbnail = $thumb_url[0];
			} else {
				$file_url = wp_get_attachment_url($post_id);
				if ( ! empty( $meta['sizes']['thumbnail'] ) && $thumb = $meta['sizes']['thumbnail'] ) {
					$return->thumbnail = path_join( dirname($file_url), $thumb['file'] );
				} else {
					$return->thumbnail = "$file_url?w=128&h=128";
				}
			}
		}
	} else {
		$delete = true;
	}

	if ( $delete ) {
		wp_delete_file( $new_path );
	}

	$return->msg = esc_js( 'Image saved' );
	return $return;
}
