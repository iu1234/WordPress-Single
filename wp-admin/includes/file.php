<?php

$wp_file_descriptions = array(
	'functions.php'         => 'Theme Functions',
	'header.php'            => 'Theme Header',
	'footer.php'            => 'Theme Footer',
	'sidebar.php'           => 'Sidebar',
	'comments.php'          => 'Comments',
	'searchform.php'        => 'Search Form',
	'404.php'               => '404 Template',
	'link.php'              => 'Links Template',
	'index.php'             => 'Main Index Template',
	'archive.php'           => 'Archives',
	'author.php'            => 'Author Template',
	'taxonomy.php'          => 'Taxonomy Template',
	'category.php'          => 'Category Template',
	'tag.php'               => 'Tag Template',
	'home.php'              => 'Posts Page',
	'search.php'            => 'Search Results',
	'date.php'              => 'Date Template',
	'singular.php'          => 'Singular Template',
	'single.php'            => 'Single Post',
	'page.php'              => 'Single Page',
	'front-page.php'        => 'Static Front Page',
	'attachment.php'        => 'Attachment Template',
	'image.php'             => 'Image Attachment Template',
	'video.php'             => 'Video Attachment Template',
	'audio.php'             => 'Audio Attachment Template',
	'application.php'       => 'Application Attachment Template',
	'style.css'             => 'Stylesheet',
	'editor-style.css'      => 'Visual Editor Stylesheet',
	'.htaccess'             => '.htaccess (for rewrite rules )',
);

function get_file_description( $file ) {
	global $wp_file_descriptions, $allowed_files;
	$relative_pathinfo = pathinfo( $file );
	$file_path = $allowed_files[ $file ];
	if ( isset( $wp_file_descriptions[ basename( $file ) ] ) && '.' === $relative_pathinfo['dirname'] ) {
		return $wp_file_descriptions[ basename( $file ) ];
	} elseif ( file_exists( $file_path ) && is_file( $file_path ) ) {
		$template_data = implode( '', file( $file_path ) );
		if ( preg_match( '|Template Name:(.*)$|mi', $template_data, $name ) ) {
			return sprintf( '%s Page Template', _cleanup_header_comment( $name[1] ) );
		}
	}
	return trim( basename( $file ) );
}

function get_home_path() {
	$home    = set_url_scheme( get_option( 'home' ), 'http' );
	$siteurl = set_url_scheme( get_option( 'siteurl' ), 'http' );
	if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
		$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl );
		$pos = strripos( str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] ), trailingslashit( $wp_path_rel_to_home ) );
		$home_path = substr( $_SERVER['SCRIPT_FILENAME'], 0, $pos );
		$home_path = trailingslashit( $home_path );
	} else {
		$home_path = ABSPATH;
	}
	return str_replace( '\\', '/', $home_path );
}

function list_files( $folder = '', $levels = 100 ) {
	if ( empty($folder) )
		return false;

	if ( ! $levels )
		return false;

	$files = array();
	if ( $dir = @opendir( $folder ) ) {
		while (($file = readdir( $dir ) ) !== false ) {
			if ( in_array($file, array('.', '..') ) )
				continue;
			if ( is_dir( $folder . '/' . $file ) ) {
				$files2 = list_files( $folder . '/' . $file, $levels - 1);
				if ( $files2 )
					$files = array_merge($files, $files2 );
				else
					$files[] = $folder . '/' . $file . '/';
			} else {
				$files[] = $folder . '/' . $file;
			}
		}
	}
	@closedir( $dir );
	return $files;
}

function wp_tempnam( $filename = '', $dir = '' ) {
	if ( empty( $dir ) ) {
		$dir = get_temp_dir();
	}
	if ( empty( $filename ) || '.' == $filename || '/' == $filename ) {
		$filename = time();
	}
	$temp_filename = basename( $filename );
	$temp_filename = preg_replace( '|\.[^.]*$|', '', $temp_filename );
	if ( ! $temp_filename ) {
		return wp_tempnam( dirname( $filename ), $dir );
	}
	$temp_filename .= '-123456';
	$temp_filename .= '.tmp';
	$temp_filename = $dir . wp_unique_filename( $dir, $temp_filename );

	$fp = @fopen( $temp_filename, 'x' );
	if ( ! $fp && is_writable( $dir ) && file_exists( $temp_filename ) ) {
		return wp_tempnam( $filename, $dir );
	}
	if ( $fp ) {
		fclose( $fp );
	}

	return $temp_filename;
}

function validate_file_to_edit( $file, $allowed_files = '' ) {
	$code = validate_file( $file, $allowed_files );

	if (!$code )
		return $file;

	switch ( $code ) {
		case 1 :
			wp_die( 'Sorry, that file cannot be edited.' );

		// case 2 :
		// wp_die( __('Sorry, can&#8217;t call files with their real path.' ));

		case 3 :
			wp_die( 'Sorry, that file cannot be edited.' );
	}
}

function _wp_handle_upload( &$file, $overrides, $time, $action ) {
	if ( ! function_exists( 'wp_handle_upload_error' ) ) {
		function wp_handle_upload_error( &$file, $message ) {
			return array( 'error' => $message );
		}
	}
	$file = apply_filters( "{$action}_prefilter", $file );
	$upload_error_handler = 'wp_handle_upload_error';
	if ( isset( $overrides['upload_error_handler'] ) ) {
		$upload_error_handler = $overrides['upload_error_handler'];
	}
	if ( isset( $file['error'] ) && ! is_numeric( $file['error'] ) && $file['error'] ) {
		return $upload_error_handler( $file, $file['error'] );
	}
	$unique_filename_callback = null;
	if ( isset( $overrides['unique_filename_callback'] ) ) {
		$unique_filename_callback = $overrides['unique_filename_callback'];
	}
	if ( isset( $overrides['upload_error_strings'] ) ) {
		$upload_error_strings = $overrides['upload_error_strings'];
	} else {
		$upload_error_strings = array(
			false,
			'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
			'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
			'The uploaded file was only partially uploaded.',
			'No file was uploaded.',
			'',
			'Missing a temporary folder.',
			'Failed to write file to disk.',
			'File upload stopped by extension.'
		);
	}
	$test_form = isset( $overrides['test_form'] ) ? $overrides['test_form'] : true;
	$test_size = isset( $overrides['test_size'] ) ? $overrides['test_size'] : true;
	$test_type = isset( $overrides['test_type'] ) ? $overrides['test_type'] : true;
	$mimes = isset( $overrides['mimes'] ) ? $overrides['mimes'] : false;
	if ( $test_form && ( ! isset( $_POST['action'] ) || ( $_POST['action'] != $action ) ) ) {
		return call_user_func( $upload_error_handler, $file, 'Invalid form submission.' );
	}
	if ( isset( $file['error'] ) && $file['error'] > 0 ) {
		return call_user_func( $upload_error_handler, $file, $upload_error_strings[ $file['error'] ] );
	}
	$test_file_size = 'wp_handle_upload' === $action ? $file['size'] : filesize( $file['tmp_name'] );
	if ( $test_size && ! ( $test_file_size > 0 ) ) {
		$error_msg = 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.';
		return call_user_func( $upload_error_handler, $file, $error_msg );
	}
	$test_uploaded_file = 'wp_handle_upload' === $action ? @ is_uploaded_file( $file['tmp_name'] ) : @ is_file( $file['tmp_name'] );
	if ( ! $test_uploaded_file ) {
		return call_user_func( $upload_error_handler, $file, 'Specified file failed upload test.' );
	}

	if ( $test_type ) {
		$wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		$ext = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
		$type = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
		$proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];
		if ( $proper_filename ) {
			$file['name'] = $proper_filename;
		}
		if ( ( ! $type || !$ext ) && ! current_user_can( 'unfiltered_upload' ) ) {
			return call_user_func( $upload_error_handler, $file, 'Sorry, this file type is not permitted for security reasons.' );
		}
		if ( ! $type ) {
			$type = $file['type'];
		}
	} else {
		$type = '';
	}

	if ( ! ( ( $uploads = wp_upload_dir( $time ) ) && false === $uploads['error'] ) ) {
		return call_user_func( $upload_error_handler, $file, $uploads['error'] );
	}
	$filename = wp_unique_filename( $uploads['path'], $file['name'], $unique_filename_callback );
	$new_file = $uploads['path'] . "/$filename";
	if ( 'wp_handle_upload' === $action ) {
		$move_new_file = @ move_uploaded_file( $file['tmp_name'], $new_file );
	} else {
		// use copy and unlink because rename breaks streams.
		$move_new_file = @ copy( $file['tmp_name'], $new_file );
		unlink( $file['tmp_name'] );
	}

	if ( false === $move_new_file ) {
		if ( 0 === strpos( $uploads['basedir'], ABSPATH ) ) {
			$error_path = str_replace( ABSPATH, '', $uploads['basedir'] ) . $uploads['subdir'];
		} else {
			$error_path = basename( $uploads['basedir'] ) . $uploads['subdir'];
		}
		return $upload_error_handler( $file, sprintf( 'The uploaded file could not be moved to %s.', $error_path ) );
	}
	$stat = stat( dirname( $new_file ));
	$perms = $stat['mode'] & 0000666;
	@ chmod( $new_file, $perms );
	$url = $uploads['url'] . "/$filename";
	return apply_filters( 'wp_handle_upload', array(
		'file' => $new_file,
		'url'  => $url,
		'type' => $type
	), 'wp_handle_sideload' === $action ? 'sideload' : 'upload' );
}

function wp_handle_upload( &$file, $overrides = false, $time = null ) {
	$action = 'wp_handle_upload';
	if ( isset( $overrides['action'] ) ) {
		$action = $overrides['action'];
	}
	return _wp_handle_upload( $file, $overrides, $time, $action );
}

function wp_handle_sideload( &$file, $overrides = false, $time = null ) {
	$action = 'wp_handle_sideload';
	if ( isset( $overrides['action'] ) ) {
		$action = $overrides['action'];
	}
	return _wp_handle_upload( $file, $overrides, $time, $action );
}

function download_url( $url, $timeout = 300 ) {
	if ( ! $url )
		return new WP_Error('http_no_url', 'Invalid URL Provided.');
	$tmpfname = wp_tempnam($url);
	if ( ! $tmpfname )
		return new WP_Error('http_no_file', 'Could not create Temporary file.');
	$response = wp_safe_remote_get( $url, array( 'timeout' => $timeout, 'stream' => true, 'filename' => $tmpfname ) );
	if ( is_wp_error( $response ) ) {
		unlink( $tmpfname );
		return $response;
	}
	if ( 200 != wp_remote_retrieve_response_code( $response ) ){
		unlink( $tmpfname );
		return new WP_Error( 'http_404', trim( wp_remote_retrieve_response_message( $response ) ) );
	}
	$content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );
	if ( $content_md5 ) {
		$md5_check = verify_file_md5( $tmpfname, $content_md5 );
		if ( is_wp_error( $md5_check ) ) {
			unlink( $tmpfname );
			return $md5_check;
		}
	}
	return $tmpfname;
}

function verify_file_md5( $filename, $expected_md5 ) {
	if ( 32 == strlen( $expected_md5 ) )
		$expected_raw_md5 = pack( 'H*', $expected_md5 );
	elseif ( 24 == strlen( $expected_md5 ) )
		$expected_raw_md5 = base64_decode( $expected_md5 );
	else
		return false;
	$file_md5 = md5_file( $filename, true );
	if ( $file_md5 === $expected_raw_md5 )
		return true;
	return new WP_Error( 'md5_mismatch', sprintf( 'The checksum of the file (%1$s) does not match the expected checksum value (%2$s).', bin2hex( $file_md5 ), bin2hex( $expected_raw_md5 ) ) );
}

function unzip_file($file, $to) {
	global $wp_filesystem;
	if ( ! $wp_filesystem || !is_object($wp_filesystem) )
		return new WP_Error('fs_unavailable', 'Could not access filesystem.');

	@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );

	$needed_dirs = array();
	$to = trailingslashit($to);
	if ( ! $wp_filesystem->is_dir($to) ) { //Only do parents if no children exist
		$path = preg_split('![/\\\]!', untrailingslashit($to));
		for ( $i = count($path); $i >= 0; $i-- ) {
			if ( empty($path[$i]) )
				continue;

			$dir = implode('/', array_slice($path, 0, $i+1) );
			if ( preg_match('!^[a-z]:$!i', $dir) ) // Skip it if it looks like a Windows Drive letter.
				continue;

			if ( ! $wp_filesystem->is_dir($dir) )
				$needed_dirs[] = $dir;
			else
				break; // A folder exists, therefor, we dont need the check the levels below this
		}
	}

	if ( class_exists( 'ZipArchive', false ) && apply_filters( 'unzip_file_use_ziparchive', true ) ) {
		$result = _unzip_file_ziparchive($file, $to, $needed_dirs);
		if ( true === $result ) {
			return $result;
		} elseif ( is_wp_error($result) ) {
			if ( 'incompatible_archive' != $result->get_error_code() )
				return $result;
		}
	}
	return _unzip_file_pclzip($file, $to, $needed_dirs);
}

function _unzip_file_ziparchive($file, $to, $needed_dirs = array() ) {
	global $wp_filesystem;
	$z = new ZipArchive();
	$zopen = $z->open( $file, ZIPARCHIVE::CHECKCONS );
	if ( true !== $zopen )
		return new WP_Error( 'incompatible_archive', 'Incompatible Archive.', array( 'ziparchive_error' => $zopen ) );

	$uncompressed_size = 0;

	for ( $i = 0; $i < $z->numFiles; $i++ ) {
		if ( ! $info = $z->statIndex($i) )
			return new WP_Error( 'stat_failed_ziparchive', 'Could not retrieve file from archive.' );

		if ( '__MACOSX/' === substr($info['name'], 0, 9) )
			continue;

		$uncompressed_size += $info['size'];

		if ( '/' == substr($info['name'], -1) )
			$needed_dirs[] = $to . untrailingslashit($info['name']);
		else
			$needed_dirs[] = $to . untrailingslashit(dirname($info['name']));
	}

	$needed_dirs = array_unique($needed_dirs);
	foreach ( $needed_dirs as $dir ) {
		if ( untrailingslashit($to) == $dir )
			continue;
		if ( strpos($dir, $to) === false )
			continue;

		$parent_folder = dirname($dir);
		while ( !empty($parent_folder) && untrailingslashit($to) != $parent_folder && !in_array($parent_folder, $needed_dirs) ) {
			$needed_dirs[] = $parent_folder;
			$parent_folder = dirname($parent_folder);
		}
	}
	asort($needed_dirs);

	foreach ( $needed_dirs as $_dir ) {
		if ( ! $wp_filesystem->mkdir( $_dir, FS_CHMOD_DIR ) && ! $wp_filesystem->is_dir( $_dir ) ) {
			return new WP_Error( 'mkdir_failed_ziparchive', 'Could not create directory.', substr( $_dir, strlen( $to ) ) );
		}
	}
	unset($needed_dirs);

	for ( $i = 0; $i < $z->numFiles; $i++ ) {
		if ( ! $info = $z->statIndex($i) )
			return new WP_Error( 'stat_failed_ziparchive', 'Could not retrieve file from archive.' );

		if ( '/' == substr($info['name'], -1) )
			continue;

		if ( '__MACOSX/' === substr($info['name'], 0, 9) )
			continue;

		$contents = $z->getFromIndex($i);
		if ( false === $contents )
			return new WP_Error( 'extract_failed_ziparchive', 'Could not extract file from archive.', $info['name'] );

		if ( ! $wp_filesystem->put_contents( $to . $info['name'], $contents, FS_CHMOD_FILE) )
			return new WP_Error( 'copy_failed_ziparchive', 'Could not copy file.', $info['name'] );
	}

	$z->close();

	return true;
}

function _unzip_file_pclzip($file, $to, $needed_dirs = array()) {
	global $wp_filesystem;
	mbstring_binary_safe_encoding();
	require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
	$archive = new PclZip($file);
	$archive_files = $archive->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
	reset_mbstring_encoding();
	if ( !is_array($archive_files) )
		return new WP_Error('incompatible_archive', 'Incompatible Archive.', $archive->errorInfo(true));
	if ( 0 == count($archive_files) )
		return new WP_Error( 'empty_archive_pclzip', 'Empty archive.' );
	$uncompressed_size = 0;
	foreach ( $archive_files as $file ) {
		if ( '__MACOSX/' === substr($file['filename'], 0, 9) ) // Skip the OS X-created __MACOSX directory
			continue;

		$uncompressed_size += $file['size'];

		$needed_dirs[] = $to . untrailingslashit( $file['folder'] ? $file['filename'] : dirname($file['filename']) );
	}

	/*
	 * disk_free_space() could return false. Assume that any falsey value is an error.
	 * A disk that has zero free bytes has bigger problems.
	 * Require we have enough space to unzip the file and copy its contents, with a 10% buffer.
	 */
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		$available_space = @disk_free_space( WP_CONTENT_DIR );
		if ( $available_space && ( $uncompressed_size * 2.1 ) > $available_space )
			return new WP_Error( 'disk_full_unzip_file', 'Could not copy files. You may have run out of disk space.', compact( 'uncompressed_size', 'available_space' ) );
	}

	$needed_dirs = array_unique($needed_dirs);
	foreach ( $needed_dirs as $dir ) {
		// Check the parent folders of the folders all exist within the creation array.
		if ( untrailingslashit($to) == $dir ) // Skip over the working directory, We know this exists (or will exist)
			continue;
		if ( strpos($dir, $to) === false ) // If the directory is not within the working directory, Skip it
			continue;

		$parent_folder = dirname($dir);
		while ( !empty($parent_folder) && untrailingslashit($to) != $parent_folder && !in_array($parent_folder, $needed_dirs) ) {
			$needed_dirs[] = $parent_folder;
			$parent_folder = dirname($parent_folder);
		}
	}
	asort($needed_dirs);

	// Create those directories if need be:
	foreach ( $needed_dirs as $_dir ) {
		// Only check to see if the dir exists upon creation failure. Less I/O this way.
		if ( ! $wp_filesystem->mkdir( $_dir, FS_CHMOD_DIR ) && ! $wp_filesystem->is_dir( $_dir ) )
			return new WP_Error( 'mkdir_failed_pclzip', 'Could not create directory.', substr( $_dir, strlen( $to ) ) );
	}
	unset($needed_dirs);

	// Extract the files from the zip
	foreach ( $archive_files as $file ) {
		if ( $file['folder'] )
			continue;

		if ( '__MACOSX/' === substr($file['filename'], 0, 9) ) // Don't extract the OS X-created __MACOSX directory files
			continue;

		if ( ! $wp_filesystem->put_contents( $to . $file['filename'], $file['content'], FS_CHMOD_FILE) )
			return new WP_Error( 'copy_failed_pclzip', 'Could not copy file.', $file['filename'] );
	}
	return true;
}

function copy_dir($from, $to, $skip_list = array() ) {
	global $wp_filesystem;

	$dirlist = $wp_filesystem->dirlist($from);

	$from = trailingslashit($from);
	$to = trailingslashit($to);

	foreach ( (array) $dirlist as $filename => $fileinfo ) {
		if ( in_array( $filename, $skip_list ) )
			continue;

		if ( 'f' == $fileinfo['type'] ) {
			if ( ! $wp_filesystem->copy($from . $filename, $to . $filename, true, FS_CHMOD_FILE) ) {
				// If copy failed, chmod file to 0644 and try again.
				$wp_filesystem->chmod( $to . $filename, FS_CHMOD_FILE );
				if ( ! $wp_filesystem->copy($from . $filename, $to . $filename, true, FS_CHMOD_FILE) )
					return new WP_Error( 'copy_failed_copy_dir', 'Could not copy file.', $to . $filename );
			}
		} elseif ( 'd' == $fileinfo['type'] ) {
			if ( !$wp_filesystem->is_dir($to . $filename) ) {
				if ( !$wp_filesystem->mkdir($to . $filename, FS_CHMOD_DIR) )
					return new WP_Error( 'mkdir_failed_copy_dir', 'Could not create directory.', $to . $filename );
			}
			$sub_skip_list = array();
			foreach ( $skip_list as $skip_item ) {
				if ( 0 === strpos( $skip_item, $filename . '/' ) )
					$sub_skip_list[] = preg_replace( '!^' . preg_quote( $filename, '!' ) . '/!i', '', $skip_item );
			}

			$result = copy_dir($from . $filename, $to . $filename, $sub_skip_list);
			if ( is_wp_error($result) )
				return $result;
		}
	}
	return true;
}

function WP_Filesystem( $args = false, $context = false, $allow_relaxed_file_ownership = false ) {
	global $wp_filesystem;

	require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');

	$method = get_filesystem_method( $args, $context, $allow_relaxed_file_ownership );

	if ( ! $method )
		return false;

	if ( ! class_exists( "WP_Filesystem_$method" ) ) {
		$abstraction_file = apply_filters( 'filesystem_method_file', ABSPATH . 'wp-admin/includes/class-wp-filesystem-' . $method . '.php', $method );

		if ( ! file_exists($abstraction_file) )
			return;

		require_once($abstraction_file);
	}
	$method = "WP_Filesystem_$method";

	$wp_filesystem = new $method($args);

	//Define the timeouts for the connections. Only available after the construct is called to allow for per-transport overriding of the default.
	if ( ! defined('FS_CONNECT_TIMEOUT') )
		define('FS_CONNECT_TIMEOUT', 30);
	if ( ! defined('FS_TIMEOUT') )
		define('FS_TIMEOUT', 30);

	if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code() )
		return false;

	if ( !$wp_filesystem->connect() )
		return false; //There was an error connecting to the server.

	// Set the permission constants if not already set.
	if ( ! defined('FS_CHMOD_DIR') )
		define('FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
	if ( ! defined('FS_CHMOD_FILE') )
		define('FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );

	return true;
}

function get_filesystem_method( $args = array(), $context = false, $allow_relaxed_file_ownership = false ) {
	$method = defined('FS_METHOD') ? FS_METHOD : false; // Please ensure that this is either 'direct', 'ssh2', 'ftpext' or 'ftpsockets'

	if ( ! $context ) {
		$context = WP_CONTENT_DIR;
	}

	// If the directory doesn't exist (wp-content/languages) then use the parent directory as we'll create it.
	if ( WP_LANG_DIR == $context && ! is_dir( $context ) ) {
		$context = dirname( $context );
	}

	$context = trailingslashit( $context );

	if ( ! $method ) {

		$temp_file_name = $context . 'temp-write-test-' . time();
		$temp_handle = @fopen($temp_file_name, 'w');
		if ( $temp_handle ) {
			$wp_file_owner = $temp_file_owner = false;
			if ( function_exists('fileowner') ) {
				$wp_file_owner = @fileowner( __FILE__ );
				$temp_file_owner = @fileowner( $temp_file_name );
			}

			if ( $wp_file_owner !== false && $wp_file_owner === $temp_file_owner ) {
				$method = 'direct';
				$GLOBALS['_wp_filesystem_direct_method'] = 'file_owner';
			} elseif ( $allow_relaxed_file_ownership ) {
				$method = 'direct';
				$GLOBALS['_wp_filesystem_direct_method'] = 'relaxed_ownership';
			}

			@fclose($temp_handle);
			@unlink($temp_file_name);
		}
 	}

	if ( ! $method && isset($args['connection_type']) && 'ssh' == $args['connection_type'] && extension_loaded('ssh2') && function_exists('stream_get_contents') ) $method = 'ssh2';
	if ( ! $method && extension_loaded('ftp') ) $method = 'ftpext';
	if ( ! $method && ( extension_loaded('sockets') || function_exists('fsockopen') ) ) $method = 'ftpsockets'; //Sockets: Socket extension; PHP Mode: FSockopen / fwrite / fread

	return apply_filters( 'filesystem_method', $method, $args, $context, $allow_relaxed_file_ownership );
}

function request_filesystem_credentials( $form_post, $type = '', $error = false, $context = false, $extra_fields = null, $allow_relaxed_file_ownership = false ) {
	global $pagenow;

	$req_cred = apply_filters( 'request_filesystem_credentials', '', $form_post, $type, $error, $context, $extra_fields, $allow_relaxed_file_ownership );
	if ( '' !== $req_cred )
		return $req_cred;

	if ( empty($type) ) {
		$type = get_filesystem_method( array(), $context, $allow_relaxed_file_ownership );
	}

	if ( 'direct' == $type )
		return true;

	if ( is_null( $extra_fields ) )
		$extra_fields = array( 'version', 'locale' );

	$credentials = get_option('ftp_credentials', array( 'hostname' => '', 'username' => ''));

	// If defined, set it to that, Else, If POST'd, set it to that, If not, Set it to whatever it previously was(saved details in option)
	$credentials['hostname'] = defined('FTP_HOST') ? FTP_HOST : (!empty($_POST['hostname']) ? wp_unslash( $_POST['hostname'] ) : $credentials['hostname']);
	$credentials['username'] = defined('FTP_USER') ? FTP_USER : (!empty($_POST['username']) ? wp_unslash( $_POST['username'] ) : $credentials['username']);
	$credentials['password'] = defined('FTP_PASS') ? FTP_PASS : (!empty($_POST['password']) ? wp_unslash( $_POST['password'] ) : '');

	// Check to see if we are setting the public/private keys for ssh
	$credentials['public_key'] = defined('FTP_PUBKEY') ? FTP_PUBKEY : (!empty($_POST['public_key']) ? wp_unslash( $_POST['public_key'] ) : '');
	$credentials['private_key'] = defined('FTP_PRIKEY') ? FTP_PRIKEY : (!empty($_POST['private_key']) ? wp_unslash( $_POST['private_key'] ) : '');

	// Sanitize the hostname, Some people might pass in odd-data:
	$credentials['hostname'] = preg_replace('|\w+://|', '', $credentials['hostname']); //Strip any schemes off

	if ( strpos($credentials['hostname'], ':') ) {
		list( $credentials['hostname'], $credentials['port'] ) = explode(':', $credentials['hostname'], 2);
		if ( ! is_numeric($credentials['port']) )
			unset($credentials['port']);
	} else {
		unset($credentials['port']);
	}

	if ( ( defined( 'FTP_SSH' ) && FTP_SSH ) || ( defined( 'FS_METHOD' ) && 'ssh2' == FS_METHOD ) ) {
		$credentials['connection_type'] = 'ssh';
	} elseif ( ( defined( 'FTP_SSL' ) && FTP_SSL ) && 'ftpext' == $type ) { //Only the FTP Extension understands SSL
		$credentials['connection_type'] = 'ftps';
	} elseif ( ! empty( $_POST['connection_type'] ) ) {
		$credentials['connection_type'] = wp_unslash( $_POST['connection_type'] );
	} elseif ( ! isset( $credentials['connection_type'] ) ) { //All else fails (And it's not defaulted to something else saved), Default to FTP
		$credentials['connection_type'] = 'ftp';
	}
	if ( ! $error &&
			(
				( !empty($credentials['password']) && !empty($credentials['username']) && !empty($credentials['hostname']) ) ||
				( 'ssh' == $credentials['connection_type'] && !empty($credentials['public_key']) && !empty($credentials['private_key']) )
			) ) {
		$stored_credentials = $credentials;
		if ( !empty($stored_credentials['port']) ) //save port as part of hostname to simplify above code.
			$stored_credentials['hostname'] .= ':' . $stored_credentials['port'];

		unset($stored_credentials['password'], $stored_credentials['port'], $stored_credentials['private_key'], $stored_credentials['public_key']);
		if ( ! wp_installing() ) {
			update_option( 'ftp_credentials', $stored_credentials );
		}
		return $credentials;
	}
	$hostname = isset( $credentials['hostname'] ) ? $credentials['hostname'] : '';
	$username = isset( $credentials['username'] ) ? $credentials['username'] : '';
	$public_key = isset( $credentials['public_key'] ) ? $credentials['public_key'] : '';
	$private_key = isset( $credentials['private_key'] ) ? $credentials['private_key'] : '';
	$port = isset( $credentials['port'] ) ? $credentials['port'] : '';
	$connection_type = isset( $credentials['connection_type'] ) ? $credentials['connection_type'] : '';

	if ( $error ) {
		$error_string = '<strong>ERROR:</strong> There was an error connecting to the server, Please verify the settings are correct.';
		if ( is_wp_error($error) )
			$error_string = esc_html( $error->get_error_message() );
		echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
	}

	$types = array();
	if ( extension_loaded('ftp') || extension_loaded('sockets') || function_exists('fsockopen') )
		$types[ 'ftp' ] = 'FTP';
	if ( extension_loaded('ftp') )
		$types[ 'ftps' ] = 'FTPS (SSL)';
	if ( extension_loaded('ssh2') && function_exists('stream_get_contents') )
		$types[ 'ssh' ] = 'SSH2';

	$types = apply_filters( 'fs_ftp_connection_types', $types, $credentials, $type, $error, $context );

?>
<script type="text/javascript">
<!--
jQuery(function($){
	jQuery("#ssh").click(function () {
		jQuery("#ssh_keys").show();
	});
	jQuery("#ftp, #ftps").click(function () {
		jQuery("#ssh_keys").hide();
	});
	jQuery('#request-filesystem-credentials-form input[value=""]:first').focus();
});
-->
</script>
<form action="<?php echo esc_url( $form_post ) ?>" method="post">
<div id="request-filesystem-credentials-form" class="request-filesystem-credentials-form">
<?php
// Print a H1 heading in the FTP credentials modal dialog, default is a H2.
$heading_tag = 'h2';
if ( 'plugins.php' === $pagenow || 'plugin-install.php' === $pagenow ) {
	$heading_tag = 'h1';
}
echo "<$heading_tag id='request-filesystem-credentials-title'>" . 'Connection Information' . "</$heading_tag>";
?>
<p id="request-filesystem-credentials-desc"><?php
	$label_user = 'Username';
	$label_pass = 'Password';
	echo 'To perform the requested action, WordPress needs to access your web server.';
	echo ' ';
	if ( ( isset( $types['ftp'] ) || isset( $types['ftps'] ) ) ) {
		if ( isset( $types['ssh'] ) ) {
			echo 'Please enter your FTP or SSH credentials to proceed.';
			$label_user = 'FTP/SSH Username';
			$label_pass = 'FTP/SSH Password';
		} else {
			echo 'Please enter your FTP credentials to proceed.';
			$label_user = 'FTP Username';
			$label_pass = 'FTP Password';
		}
		echo ' ';
	}
	_e('If you do not remember your credentials, you should contact your web host.');
?></p>
<label for="hostname">
	<span class="field-title">Hostname</span>
	<input name="hostname" type="text" id="hostname" aria-describedby="request-filesystem-credentials-desc" class="code" placeholder="<?php esc_attr_e( 'example: www.wordpress.org' ) ?>" value="<?php echo esc_attr($hostname); if ( !empty($port) ) echo ":$port"; ?>"<?php disabled( defined('FTP_HOST') ); ?> />
</label>
<div class="ftp-username">
	<label for="username">
		<span class="field-title"><?php echo $label_user; ?></span>
		<input name="username" type="text" id="username" value="<?php echo esc_attr($username) ?>"<?php disabled( defined('FTP_USER') ); ?> />
	</label>
</div>
<div class="ftp-password">
	<label for="password">
		<span class="field-title"><?php echo $label_pass; ?></span>
		<input name="password" type="password" id="password" value="<?php if ( defined('FTP_PASS') ) echo '*****'; ?>"<?php disabled( defined('FTP_PASS') ); ?> />
		<em><?php if ( ! defined('FTP_PASS') ) echo 'This password will not be stored on the server.'; ?></em>
	</label>
</div>
<?php if ( isset($types['ssh']) ) : ?>
<fieldset>
<legend>Authentication Keys</legend>
<label for="public_key">
	<span class="field-title">Public Key:</span>
	<input name="public_key" type="text" id="public_key" aria-describedby="auth-keys-desc" value="<?php echo esc_attr($public_key) ?>"<?php disabled( defined('FTP_PUBKEY') ); ?> />
</label>
<label for="private_key">
	<span class="field-title">Private Key:</span>
	<input name="private_key" type="text" id="private_key" value="<?php echo esc_attr($private_key) ?>"<?php disabled( defined('FTP_PRIKEY') ); ?> />
</label>
</fieldset>
<span id="auth-keys-desc">Enter the location on the server where the public and private keys are located. If a passphrase is needed, enter that in the password field above.</span>
<?php endif; ?>
<fieldset>
<legend>Connection Type</legend>
<?php
	$disabled = disabled( (defined('FTP_SSL') && FTP_SSL) || (defined('FTP_SSH') && FTP_SSH), true, false );
	foreach ( $types as $name => $text ) : ?>
	<label for="<?php echo esc_attr($name) ?>">
		<input type="radio" name="connection_type" id="<?php echo esc_attr($name) ?>" value="<?php echo esc_attr($name) ?>"<?php checked($name, $connection_type); echo $disabled; ?> />
		<?php echo $text ?>
	</label>
	<?php endforeach; ?>
</fieldset>
<?php
foreach ( (array) $extra_fields as $field ) {
	if ( isset( $_POST[ $field ] ) )
		echo '<input type="hidden" name="' . esc_attr( $field ) . '" value="' . esc_attr( wp_unslash( $_POST[ $field ] ) ) . '" />';
}
?>
	<p class="request-filesystem-credentials-action-buttons">
		<button class="button cancel-button" data-js-action="close" type="button">Cancel</button>
		<?php submit_button( 'Proceed', 'button', 'upgrade', false ); ?>
	</p>
</div>
</form>
<?php
	return false;
}

function wp_print_request_filesystem_credentials_modal() {
	$filesystem_method = get_filesystem_method();
	ob_start();
	$filesystem_credentials_are_stored = request_filesystem_credentials( self_admin_url() );
	ob_end_clean();
	$request_filesystem_credentials = ( $filesystem_method != 'direct' && ! $filesystem_credentials_are_stored );
	if ( ! $request_filesystem_credentials ) {
		return;
	}
	?>
	<div id="request-filesystem-credentials-dialog" class="notification-dialog-wrap request-filesystem-credentials-dialog">
		<div class="notification-dialog-background"></div>
		<div class="notification-dialog" role="dialog" aria-labelledby="request-filesystem-credentials-title" tabindex="0">
			<div class="request-filesystem-credentials-dialog-content">
				<?php request_filesystem_credentials( site_url() ); ?>
			</div>
		</div>
	</div>
	<?php
}
