<?php
/**
 * Base WordPress Filesystem
 *
 * @package WordPress
 * @subpackage Filesystem
 */

class WP_Filesystem_Base {

	public $verbose = false;

	public $cache = array();

	public $method = '';

	public $errors = null;

	public $options = array();

	public function abspath() {
		$folder = $this->find_folder(ABSPATH);
		// Perhaps the FTP folder is rooted at the WordPress install, Check for wp-includes folder in root, Could have some false positives, but rare.
		if ( ! $folder && $this->is_dir( '/' . WPINC ) )
			$folder = '/';
		return $folder;
	}

	public function wp_content_dir() {
		return $this->find_folder(WP_CONTENT_DIR);
	}

	public function wp_plugins_dir() {
		return $this->find_folder(WP_PLUGIN_DIR);
	}

	public function wp_themes_dir( $theme = false ) {
		$theme_root = get_theme_root( $theme );

		// Account for relative theme roots
		if ( '/themes' == $theme_root || ! is_dir( $theme_root ) )
			$theme_root = WP_CONTENT_DIR . $theme_root;

		return $this->find_folder( $theme_root );
	}

	public function wp_lang_dir() {
		return $this->find_folder(WP_LANG_DIR);
	}

	public function find_base_dir( $base = '.', $echo = false ) {
		_deprecated_function(__FUNCTION__, '2.7', 'WP_Filesystem::abspath() or WP_Filesystem::wp_*_dir()' );
		$this->verbose = $echo;
		return $this->abspath();
	}

	public function get_base_dir( $base = '.', $echo = false ) {
		_deprecated_function(__FUNCTION__, '2.7', 'WP_Filesystem::abspath() or WP_Filesystem::wp_*_dir()' );
		$this->verbose = $echo;
		return $this->abspath();
	}

	public function find_folder( $folder ) {
		if ( isset( $this->cache[ $folder ] ) )
			return $this->cache[ $folder ];

		if ( stripos($this->method, 'ftp') !== false ) {
			$constant_overrides = array(
				'FTP_BASE' => ABSPATH,
				'FTP_CONTENT_DIR' => WP_CONTENT_DIR,
				'FTP_PLUGIN_DIR' => WP_PLUGIN_DIR,
				'FTP_LANG_DIR' => WP_LANG_DIR
			);

			// Direct matches ( folder = CONSTANT/ )
			foreach ( $constant_overrides as $constant => $dir ) {
				if ( ! defined( $constant ) )
					continue;
				if ( $folder === $dir )
					return trailingslashit( constant( $constant ) );
			}

			// Prefix Matches ( folder = CONSTANT/subdir )
			foreach ( $constant_overrides as $constant => $dir ) {
				if ( ! defined( $constant ) )
					continue;
				if ( 0 === stripos( $folder, $dir ) ) { // $folder starts with $dir
					$potential_folder = preg_replace( '#^' . preg_quote( $dir, '#' ) . '/#i', trailingslashit( constant( $constant ) ), $folder );
					$potential_folder = trailingslashit( $potential_folder );

					if ( $this->is_dir( $potential_folder ) ) {
						$this->cache[ $folder ] = $potential_folder;
						return $potential_folder;
					}
				}
			}
		} elseif ( 'direct' == $this->method ) {
			$folder = str_replace('\\', '/', $folder); // Windows path sanitisation
			return trailingslashit($folder);
		}

		$folder = preg_replace('|^([a-z]{1}):|i', '', $folder); // Strip out windows drive letter if it's there.
		$folder = str_replace('\\', '/', $folder); // Windows path sanitisation

		if ( isset($this->cache[ $folder ] ) )
			return $this->cache[ $folder ];

		if ( $this->exists($folder) ) { // Folder exists at that absolute path.
			$folder = trailingslashit($folder);
			$this->cache[ $folder ] = $folder;
			return $folder;
		}
		if ( $return = $this->search_for_folder($folder) )
			$this->cache[ $folder ] = $return;
		return $return;
	}

	public function search_for_folder( $folder, $base = '.', $loop = false ) {
		if ( empty( $base ) || '.' == $base )
			$base = trailingslashit($this->cwd());

		$folder = untrailingslashit($folder);

		if ( $this->verbose ) {
			/* translators: 1: folder to locate, 2: folder to start searching from */
			printf( "\n" . __( 'Looking for %1$s in %2$s' ) . "<br/>\n", $folder, $base );
		}

		$folder_parts = explode('/', $folder);
		$folder_part_keys = array_keys( $folder_parts );
		$last_index = array_pop( $folder_part_keys );
		$last_path = $folder_parts[ $last_index ];

		$files = $this->dirlist( $base );

		foreach ( $folder_parts as $index => $key ) {
			if ( $index == $last_index )
				continue; // We want this to be caught by the next code block.

			if ( isset($files[ $key ]) ){

				// Let's try that folder:
				$newdir = trailingslashit(path_join($base, $key));
				if ( $this->verbose ) {
					/* translators: %s: directory name */
					printf( "\n" . __( 'Changing to %s' ) . "<br/>\n", $newdir );
				}

				// Only search for the remaining path tokens in the directory, not the full path again.
				$newfolder = implode( '/', array_slice( $folder_parts, $index + 1 ) );
				if ( $ret = $this->search_for_folder( $newfolder, $newdir, $loop) )
					return $ret;
			}
		}

		// Only check this as a last resort, to prevent locating the incorrect install.
		// All above procedures will fail quickly if this is the right branch to take.
		if (isset( $files[ $last_path ] ) ) {
			if ( $this->verbose ) {
				/* translators: %s: directory name */
				printf( "\n" . __( 'Found %s' ) . "<br/>\n",  $base . $last_path );
			}
			return trailingslashit($base . $last_path);
		}

		if ( $loop || '/' == $base )
			return false;

		return $this->search_for_folder( $folder, '/', true );

	}

	public function gethchmod( $file ){
		$perms = intval( $this->getchmod( $file ), 8 );
		if (($perms & 0xC000) == 0xC000) // Socket
			$info = 's';
		elseif (($perms & 0xA000) == 0xA000) // Symbolic Link
			$info = 'l';
		elseif (($perms & 0x8000) == 0x8000) // Regular
			$info = '-';
		elseif (($perms & 0x6000) == 0x6000) // Block special
			$info = 'b';
		elseif (($perms & 0x4000) == 0x4000) // Directory
			$info = 'd';
		elseif (($perms & 0x2000) == 0x2000) // Character special
			$info = 'c';
		elseif (($perms & 0x1000) == 0x1000) // FIFO pipe
			$info = 'p';
		else // Unknown
			$info = 'u';

		// Owner
		$info .= (($perms & 0x0100) ? 'r' : '-');
		$info .= (($perms & 0x0080) ? 'w' : '-');
		$info .= (($perms & 0x0040) ?
					(($perms & 0x0800) ? 's' : 'x' ) :
					(($perms & 0x0800) ? 'S' : '-'));

		// Group
		$info .= (($perms & 0x0020) ? 'r' : '-');
		$info .= (($perms & 0x0010) ? 'w' : '-');
		$info .= (($perms & 0x0008) ?
					(($perms & 0x0400) ? 's' : 'x' ) :
					(($perms & 0x0400) ? 'S' : '-'));

		// World
		$info .= (($perms & 0x0004) ? 'r' : '-');
		$info .= (($perms & 0x0002) ? 'w' : '-');
		$info .= (($perms & 0x0001) ?
					(($perms & 0x0200) ? 't' : 'x' ) :
					(($perms & 0x0200) ? 'T' : '-'));
		return $info;
	}

	public function getchmod( $file ) {
		return '777';
	}

	public function getnumchmodfromh( $mode ) {
		$realmode = '';
		$legal =  array('', 'w', 'r', 'x', '-');
		$attarray = preg_split('//', $mode);

		for ( $i = 0, $c = count( $attarray ); $i < $c; $i++ ) {
		   if ($key = array_search($attarray[$i], $legal)) {
			   $realmode .= $legal[$key];
		   }
		}

		$mode = str_pad($realmode, 10, '-', STR_PAD_LEFT);
		$trans = array('-'=>'0', 'r'=>'4', 'w'=>'2', 'x'=>'1');
		$mode = strtr($mode,$trans);

		$newmode = $mode[0];
		$newmode .= $mode[1] + $mode[2] + $mode[3];
		$newmode .= $mode[4] + $mode[5] + $mode[6];
		$newmode .= $mode[7] + $mode[8] + $mode[9];
		return $newmode;
	}

	public function is_binary( $text ) {
		return (bool) preg_match( '|[^\x20-\x7E]|', $text ); // chr(32)..chr(127)
	}

	public function chown( $file, $owner, $recursive = false ) {
		return false;
	}

	public function connect() {
		return true;
	}

	public function get_contents( $file ) {
		return false;
	}

	public function get_contents_array( $file ) {
		return false;
	}

	public function put_contents( $file, $contents, $mode = false ) {
		return false;
	}

	public function cwd() {
		return false;
	}

	public function chdir( $dir ) {
		return false;
	}

	public function chgrp( $file, $group, $recursive = false ) {
		return false;
	}

	public function chmod( $file, $mode = false, $recursive = false ) {
		return false;
	}

	public function owner( $file ) {
		return false;
	}

	public function group( $file ) {
		return false;
	}

	public function copy( $source, $destination, $overwrite = false, $mode = false ) {
		return false;
	}

	public function move( $source, $destination, $overwrite = false ) {
		return false;
	}

	public function delete( $file, $recursive = false, $type = false ) {
		return false;
	}

	public function exists( $file ) {
		return false;
	}

	public function is_file( $file ) {
		return false;
	}

	public function is_dir( $path ) {
		return false;
	}

	public function is_readable( $file ) {
		return false;
	}

	public function is_writable( $file ) {
		return false;
	}

	public function atime( $file ) {
		return false;
	}

	public function mtime( $file ) {
		return false;
	}

	public function size( $file ) {
		return false;
	}

	public function touch( $file, $time = 0, $atime = 0 ) {
		return false;
	}

	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ) {
		return false;
	}

	public function rmdir( $path, $recursive = false ) {
		return false;
	}

	public function dirlist( $path, $include_hidden = true, $recursive = false ) {
		return false;
	}

}
