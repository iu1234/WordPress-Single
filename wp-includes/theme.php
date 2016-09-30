<?php

function wp_get_themes( $args = array() ) {
	global $wp_theme_directories;
	$defaults = array( 'errors' => false, 'allowed' => null, 'blog_id' => 0 );
	$args = wp_parse_args( $args, $defaults );
	$theme_directories = search_theme_directories();
	if ( count( $wp_theme_directories ) > 1 ) {
		$current_theme = get_stylesheet();
		if ( isset( $theme_directories[ $current_theme ] ) ) {
			$root_of_current_theme = get_raw_theme_root( $current_theme );
			if ( ! in_array( $root_of_current_theme, $wp_theme_directories ) )
				$root_of_current_theme = WP_CONTENT_DIR . $root_of_current_theme;
			$theme_directories[ $current_theme ]['theme_root'] = $root_of_current_theme;
		}
	}
	if ( empty( $theme_directories ) )
		return array();
	$themes = array();
	static $_themes = array();
	foreach ( $theme_directories as $theme => $theme_root ) {
		if ( isset( $_themes[ $theme_root['theme_root'] . '/' . $theme ] ) )
			$themes[ $theme ] = $_themes[ $theme_root['theme_root'] . '/' . $theme ];
		else
			$themes[ $theme ] = $_themes[ $theme_root['theme_root'] . '/' . $theme ] = new WP_Theme( $theme, $theme_root['theme_root'] );
	}
	if ( null !== $args['errors'] ) {
		foreach ( $themes as $theme => $wp_theme ) {
			if ( $wp_theme->errors() != $args['errors'] )
				unset( $themes[ $theme ] );
		}
	}
	return $themes;
}

function wp_get_theme( $stylesheet = null, $theme_root = null ) {
	global $wp_theme_directories;
	if ( empty( $stylesheet ) )
		$stylesheet = get_stylesheet();
	if ( empty( $theme_root ) ) {
		$theme_root = get_raw_theme_root( $stylesheet );
		if ( false === $theme_root )
			$theme_root = WP_CONTENT_DIR . '/themes';
		elseif ( ! in_array( $theme_root, (array) $wp_theme_directories ) )
			$theme_root = WP_CONTENT_DIR . $theme_root;
	}
	return new WP_Theme( $stylesheet, $theme_root );
}

function wp_clean_themes_cache( $clear_update_cache = true ) {
	if ( $clear_update_cache )
		delete_site_transient( 'update_themes' );
	search_theme_directories( true );
	foreach ( wp_get_themes( array( 'errors' => null ) ) as $theme )
		$theme->cache_delete();
}

function get_stylesheet_directory() {
	$stylesheet = get_option( 'stylesheet' );
	$theme_root = get_theme_root( $stylesheet );
	$stylesheet_dir = "$theme_root/$stylesheet";
	return apply_filters( 'stylesheet_directory', $stylesheet_dir, $stylesheet, $theme_root );
}

function get_stylesheet_directory_uri() {
	$stylesheet = str_replace( '%2F', '/', rawurlencode( get_stylesheet() ) );
	$theme_root_uri = get_theme_root_uri( $stylesheet );
	$stylesheet_dir_uri = "$theme_root_uri/$stylesheet";
	return apply_filters( 'stylesheet_directory_uri', $stylesheet_dir_uri, $stylesheet, $theme_root_uri );
}

function get_stylesheet_uri() {
	$stylesheet_dir_uri = get_stylesheet_directory_uri();
	$stylesheet_uri = $stylesheet_dir_uri . '/style.css';
	return apply_filters( 'stylesheet_uri', $stylesheet_uri, $stylesheet_dir_uri );
}

function get_locale_stylesheet_uri() {
	global $wp_locale;
	$stylesheet_dir_uri = get_stylesheet_directory_uri();
	$dir = get_stylesheet_directory();
	$locale = get_locale();
	if ( file_exists("$dir/$locale.css") )
		$stylesheet_uri = "$stylesheet_dir_uri/$locale.css";
	elseif ( !empty($wp_locale->text_direction) && file_exists("$dir/{$wp_locale->text_direction}.css") )
		$stylesheet_uri = "$stylesheet_dir_uri/{$wp_locale->text_direction}.css";
	else
		$stylesheet_uri = '';
	return apply_filters( 'locale_stylesheet_uri', $stylesheet_uri, $stylesheet_dir_uri );
}

function get_template_directory() {
	$template = get_option( 'template' );
	$theme_root = get_theme_root( $template );
	$template_dir = "$theme_root/$template";
	return apply_filters( 'template_directory', $template_dir, $template, $theme_root );
}

function get_template_directory_uri() {
	$template = str_replace( '%2F', '/', rawurlencode( get_template() ) );
	$theme_root_uri = get_theme_root_uri( $template );
	$template_dir_uri = "$theme_root_uri/$template";
	return apply_filters( 'template_directory_uri', $template_dir_uri, $template, $theme_root_uri );
}

function get_theme_roots() {
	global $wp_theme_directories;
	if ( count($wp_theme_directories) <= 1 )
		return '/themes';
	$theme_roots = get_site_transient( 'theme_roots' );
	if ( false === $theme_roots ) {
		search_theme_directories( true );
		$theme_roots = get_site_transient( 'theme_roots' );
	}
	return $theme_roots;
}

function register_theme_directory( $directory ) {
	global $wp_theme_directories;
	if ( ! file_exists( $directory ) ) {
		$directory = WP_CONTENT_DIR . '/' . $directory;
		if ( ! file_exists( $directory ) ) {
			return false;
		}
	}
	if ( ! is_array( $wp_theme_directories ) ) {
		$wp_theme_directories = array();
	}
	$untrailed = untrailingslashit( $directory );
	if ( ! empty( $untrailed ) && ! in_array( $untrailed, $wp_theme_directories ) ) {
		$wp_theme_directories[] = $untrailed;
	}
	return true;
}

function search_theme_directories( $force = false ) {
	global $wp_theme_directories;
	static $found_themes = null;
	if ( empty( $wp_theme_directories ) )
		return false;
	if ( ! $force && isset( $found_themes ) )
		return $found_themes;
	$found_themes = array();
	$wp_theme_directories = (array) $wp_theme_directories;
	$relative_theme_roots = array();
	foreach ( $wp_theme_directories as $theme_root ) {
		if ( 0 === strpos( $theme_root, WP_CONTENT_DIR ) )
			$relative_theme_roots[ str_replace( WP_CONTENT_DIR, '', $theme_root ) ] = $theme_root;
		else
			$relative_theme_roots[ $theme_root ] = $theme_root;
	}
	if ( $cache_expiration = apply_filters( 'wp_cache_themes_persistently', false, 'search_theme_directories' ) ) {
		$cached_roots = get_site_transient( 'theme_roots' );
		if ( is_array( $cached_roots ) ) {
			foreach ( $cached_roots as $theme_dir => $theme_root ) {
				if ( ! isset( $relative_theme_roots[ $theme_root ] ) )
					continue;
				$found_themes[ $theme_dir ] = array(
					'theme_file' => $theme_dir . '/style.css',
					'theme_root' => $relative_theme_roots[ $theme_root ],
				);
			}
			return $found_themes;
		}
		if ( ! is_int( $cache_expiration ) )
			$cache_expiration = 1800;
	} else {
		$cache_expiration = 1800;
	}
	foreach ( $wp_theme_directories as $theme_root ) {
		$dirs = @ scandir( $theme_root );
		if ( ! $dirs ) {
			trigger_error( "$theme_root is not readable", E_USER_NOTICE );
			continue;
		}
		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $theme_root . '/' . $dir ) || $dir[0] == '.' || $dir == 'CVS' )
				continue;
			if ( file_exists( $theme_root . '/' . $dir . '/style.css' ) ) {
				$found_themes[ $dir ] = array(
					'theme_file' => $dir . '/style.css',
					'theme_root' => $theme_root,
				);
			} else {
				$found_theme = false;
				$sub_dirs = @ scandir( $theme_root . '/' . $dir );
				if ( ! $sub_dirs ) {
					trigger_error( "$theme_root/$dir is not readable", E_USER_NOTICE );
					continue;
				}
				foreach ( $sub_dirs as $sub_dir ) {
					if ( ! is_dir( $theme_root . '/' . $dir . '/' . $sub_dir ) || $dir[0] == '.' || $dir == 'CVS' )
						continue;
					if ( ! file_exists( $theme_root . '/' . $dir . '/' . $sub_dir . '/style.css' ) )
						continue;
					$found_themes[ $dir . '/' . $sub_dir ] = array(
						'theme_file' => $dir . '/' . $sub_dir . '/style.css',
						'theme_root' => $theme_root,
					);
					$found_theme = true;
				}
				if ( ! $found_theme )
					$found_themes[ $dir ] = array(
						'theme_file' => $dir . '/style.css',
						'theme_root' => $theme_root,
					);
			}
		}
	}

	asort( $found_themes );

	$theme_roots = array();
	$relative_theme_roots = array_flip( $relative_theme_roots );

	foreach ( $found_themes as $theme_dir => $theme_data ) {
		$theme_roots[ $theme_dir ] = $relative_theme_roots[ $theme_data['theme_root'] ]; // Convert absolute to relative.
	}

	if ( $theme_roots != get_site_transient( 'theme_roots' ) )
		set_site_transient( 'theme_roots', $theme_roots, $cache_expiration );

	return $found_themes;
}

function get_theme_root( $stylesheet_or_template = false ) {
	global $wp_theme_directories;
	if ( $stylesheet_or_template && $theme_root = get_raw_theme_root( $stylesheet_or_template ) ) {
		if ( ! in_array( $theme_root, (array) $wp_theme_directories ) )
			$theme_root = WP_CONTENT_DIR . $theme_root;
	} else {
		$theme_root = WP_CONTENT_DIR . '/themes';
	}
	return $theme_root;
}

function get_theme_root_uri( $stylesheet_or_template = false, $theme_root = false ) {
	global $wp_theme_directories;

	if ( $stylesheet_or_template && ! $theme_root )
		$theme_root = get_raw_theme_root( $stylesheet_or_template );

	if ( $stylesheet_or_template && $theme_root ) {
		if ( in_array( $theme_root, (array) $wp_theme_directories ) ) {
			if ( 0 === strpos( $theme_root, WP_CONTENT_DIR ) )
				$theme_root_uri = content_url( str_replace( WP_CONTENT_DIR, '', $theme_root ) );
			elseif ( 0 === strpos( $theme_root, ABSPATH ) )
				$theme_root_uri = site_url( str_replace( ABSPATH, '', $theme_root ) );
			elseif ( 0 === strpos( $theme_root, WP_PLUGIN_DIR ) || 0 === strpos( $theme_root, WPMU_PLUGIN_DIR ) )
				$theme_root_uri = plugins_url( basename( $theme_root ), $theme_root );
			else
				$theme_root_uri = $theme_root;
		} else {
			$theme_root_uri = content_url( $theme_root );
		}
	} else {
		$theme_root_uri = content_url( 'themes' );
	}
	return apply_filters( 'theme_root_uri', $theme_root_uri, get_option( 'siteurl' ), $stylesheet_or_template );
}

function get_raw_theme_root( $stylesheet_or_template, $skip_cache = false ) {
	global $wp_theme_directories;
	if ( count($wp_theme_directories) <= 1 )
		return '/themes';
	$theme_root = false;
	if ( ! $skip_cache ) {
		if ( get_option('stylesheet') == $stylesheet_or_template )
			$theme_root = get_option('stylesheet_root');
		elseif ( get_option('template') == $stylesheet_or_template )
			$theme_root = get_option('template_root');
	}
	if ( empty($theme_root) ) {
		$theme_roots = get_theme_roots();
		if ( !empty($theme_roots[$stylesheet_or_template]) )
			$theme_root = $theme_roots[$stylesheet_or_template];
	}
	return $theme_root;
}

function locale_stylesheet() {
	$stylesheet = get_locale_stylesheet_uri();
	if ( empty($stylesheet) )
		return;
	echo '<link rel="stylesheet" href="' . $stylesheet . '" type="text/css" media="screen" />';
}

function switch_theme( $stylesheet ) {
	global $wp_theme_directories, $wp_customize, $sidebars_widgets;
	$_sidebars_widgets = null;
	if ( 'wp_ajax_customize_save' === current_action() ) {
		$_sidebars_widgets = $wp_customize->post_value( $wp_customize->get_setting( 'old_sidebars_widgets_data' ) );
	} elseif ( is_array( $sidebars_widgets ) ) {
		$_sidebars_widgets = $sidebars_widgets;
	}
	if ( is_array( $_sidebars_widgets ) ) {
		set_theme_mod( 'sidebars_widgets', array( 'time' => time(), 'data' => $_sidebars_widgets ) );
	}
	$nav_menu_locations = get_theme_mod( 'nav_menu_locations' );
	if ( func_num_args() > 1 ) {
		$stylesheet = func_get_arg( 1 );
	}
	$old_theme = wp_get_theme();
	$new_theme = wp_get_theme( $stylesheet );
	$template  = $new_theme->get_template();
	update_option( 'template', $template );
	update_option( 'stylesheet', $stylesheet );
	if ( count( $wp_theme_directories ) > 1 ) {
		update_option( 'template_root', get_raw_theme_root( $template, true ) );
		update_option( 'stylesheet_root', get_raw_theme_root( $stylesheet, true ) );
	} else {
		delete_option( 'template_root' );
		delete_option( 'stylesheet_root' );
	}
	$new_name  = $new_theme->get('Name');
	update_option( 'current_theme', $new_name );
	if ( is_admin() && false === get_option( 'theme_mods_' . $stylesheet ) ) {
		$default_theme_mods = (array) get_option( 'mods_' . $new_name );
		if ( ! empty( $nav_menu_locations ) && empty( $default_theme_mods['nav_menu_locations'] ) ) {
			$default_theme_mods['nav_menu_locations'] = $nav_menu_locations;
		}
		add_option( "theme_mods_$stylesheet", $default_theme_mods );
	} else {
		if ( 'wp_ajax_customize_save' === current_action() ) {
			remove_theme_mod( 'sidebars_widgets' );
		}
		if ( ! empty( $nav_menu_locations ) ) {
			$nav_mods = get_theme_mod( 'nav_menu_locations' );
			if ( empty( $nav_mods ) ) {
				set_theme_mod( 'nav_menu_locations', $nav_menu_locations );
			}
		}
	}
	update_option( 'theme_switched', $old_theme->get_stylesheet() );
	do_action( 'switch_theme', $new_name, $new_theme, $old_theme );
}

function validate_current_theme() {
	if ( wp_installing() || ! apply_filters( 'validate_current_theme', true ) )
		return true;

	if ( ! file_exists( get_template_directory() . '/index.php' ) ) {
	} elseif ( ! file_exists( get_template_directory() . '/style.css' ) ) {
	} elseif ( is_child_theme() && ! file_exists( get_stylesheet_directory() . '/style.css' ) ) {
	} else {
		return true;
	}
	$default = wp_get_theme( WP_DEFAULT_THEME );
	if ( $default->exists() ) {
		switch_theme( WP_DEFAULT_THEME );
		return false;
	}
	$default = WP_Theme::get_core_default_theme();
	if ( false === $default || get_stylesheet() == $default->get_stylesheet() ) {
		return true;
	}
	switch_theme( $default->get_stylesheet() );
	return false;
}

function get_theme_mods() {
	$theme_slug = get_option( 'stylesheet' );
	$mods = get_option( "theme_mods_$theme_slug" );
	if ( false === $mods ) {
		$theme_name = get_option( 'current_theme' );
		if ( false === $theme_name )
			$theme_name = wp_get_theme()->get('Name');
		$mods = get_option( "mods_$theme_name" );
		if ( is_admin() && false !== $mods ) {
			update_option( "theme_mods_$theme_slug", $mods );
			delete_option( "mods_$theme_name" );
		}
	}
	return $mods;
}

function get_theme_mod( $name, $default = false ) {
	$mods = get_theme_mods();
	if ( isset( $mods[$name] ) ) {
		return apply_filters( "theme_mod_{$name}", $mods[$name] );
	}
	if ( is_string( $default ) )
		$default = sprintf( $default, get_template_directory_uri(), get_stylesheet_directory_uri() );
	return apply_filters( "theme_mod_{$name}", $default );
}

function set_theme_mod( $name, $value ) {
	$mods = get_theme_mods();
	$old_value = isset( $mods[ $name ] ) ? $mods[ $name ] : false;
	$mods[ $name ] = apply_filters( "pre_set_theme_mod_$name", $value, $old_value );
	$theme = get_option( 'stylesheet' );
	update_option( "theme_mods_$theme", $mods );
}

function remove_theme_mod( $name ) {
	$mods = get_theme_mods();
	if ( ! isset( $mods[ $name ] ) )
		return;
	unset( $mods[ $name ] );
	if ( empty( $mods ) ) {
		remove_theme_mods();
		return;
	}
	$theme = get_option( 'stylesheet' );
	update_option( "theme_mods_$theme", $mods );
}

function remove_theme_mods() {
	delete_option( 'theme_mods_' . get_option( 'stylesheet' ) );
	$theme_name = get_option( 'current_theme' );
	if ( false === $theme_name )
		$theme_name = wp_get_theme()->get('Name');
	delete_option( 'mods_' . $theme_name );
}

function get_header_textcolor() {
	return get_theme_mod('header_textcolor', get_theme_support( 'custom-header', 'default-text-color' ) );
}

function display_header_text() {
	if ( ! current_theme_supports( 'custom-header', 'header-text' ) )
		return false;
	$text_color = get_theme_mod( 'header_textcolor', get_theme_support( 'custom-header', 'default-text-color' ) );
	return 'blank' !== $text_color;
}

function has_header_image() {
	return (bool) get_header_image();
}

function get_header_image() {
	$url = get_theme_mod( 'header_image', get_theme_support( 'custom-header', 'default-image' ) );
	if ( 'remove-header' == $url )
		return false;
	if ( is_random_header_image() )
		$url = get_random_header_image();
	return esc_url_raw( set_url_scheme( $url ) );
}

function get_header_image_tag( $attr = array() ) {
	$header = get_custom_header();
	if ( empty( $header->url ) ) {
		return '';
	}
	$width = absint( $header->width );
	$height = absint( $header->height );
	$attr = wp_parse_args(
		$attr,
		array(
			'src' => $header->url,
			'width' => $width,
			'height' => $height,
			'alt' => get_bloginfo( 'name' ),
		)
	);
	if ( empty( $attr['srcset'] ) && ! empty( $header->attachment_id ) ) {
		$image_meta = get_post_meta( $header->attachment_id, '_wp_attachment_metadata', true );
		$size_array = array( $width, $height );
		if ( is_array( $image_meta ) ) {
			$srcset = wp_calculate_image_srcset( $size_array, $header->url, $image_meta, $header->attachment_id );
			$sizes = ! empty( $attr['sizes'] ) ? $attr['sizes'] : wp_calculate_image_sizes( $size_array, $header->url, $image_meta, $header->attachment_id );
			if ( $srcset && $sizes ) {
				$attr['srcset'] = $srcset;
				$attr['sizes'] = $sizes;
			}
		}
	}
	$attr = array_map( 'esc_attr', $attr );
	$html = '<img';

	foreach ( $attr as $name => $value ) {
		$html .= ' ' . $name . '="' . $value . '"';
	}

	$html .= ' />';

	return apply_filters( 'get_header_image_tag', $html, $header, $attr );
}

function the_header_image_tag( $attr = array() ) {
	echo get_header_image_tag( $attr );
}

function _get_random_header_data() {
	static $_wp_random_header = null;
	if ( empty( $_wp_random_header ) ) {
		global $_wp_default_headers;
		$header_image_mod = get_theme_mod( 'header_image', '' );
		$headers = array();

		if ( 'random-uploaded-image' == $header_image_mod )
			$headers = get_uploaded_header_images();
		elseif ( ! empty( $_wp_default_headers ) ) {
			if ( 'random-default-image' == $header_image_mod ) {
				$headers = $_wp_default_headers;
			} else {
				if ( current_theme_supports( 'custom-header', 'random-default' ) )
					$headers = $_wp_default_headers;
			}
		}

		if ( empty( $headers ) )
			return new stdClass;

		$_wp_random_header = (object) $headers[ array_rand( $headers ) ];

		$_wp_random_header->url =  sprintf( $_wp_random_header->url, get_template_directory_uri(), get_stylesheet_directory_uri() );
		$_wp_random_header->thumbnail_url =  sprintf( $_wp_random_header->thumbnail_url, get_template_directory_uri(), get_stylesheet_directory_uri() );
	}
	return $_wp_random_header;
}

function get_random_header_image() {
	$random_image = _get_random_header_data();
	if ( empty( $random_image->url ) )
		return '';
	return $random_image->url;
}

function is_random_header_image( $type = 'any' ) {
	$header_image_mod = get_theme_mod( 'header_image', get_theme_support( 'custom-header', 'default-image' ) );
	if ( 'any' == $type ) {
		if ( 'random-default-image' == $header_image_mod || 'random-uploaded-image' == $header_image_mod || ( '' != get_random_header_image() && empty( $header_image_mod ) ) )
			return true;
	} else {
		if ( "random-$type-image" == $header_image_mod )
			return true;
		elseif ( 'default' == $type && empty( $header_image_mod ) && '' != get_random_header_image() )
			return true;
	}
	return false;
}

function header_image() {
	$image = get_header_image();
	if ( $image ) { echo esc_url( $image ); }
}

function get_uploaded_header_images() {
	$header_images = array();
	$headers = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_wp_attachment_is_custom_header', 'meta_value' => get_option('stylesheet'), 'orderby' => 'none', 'nopaging' => true ) );

	if ( empty( $headers ) )
		return array();

	foreach ( (array) $headers as $header ) {
		$url = esc_url_raw( wp_get_attachment_url( $header->ID ) );
		$header_data = wp_get_attachment_metadata( $header->ID );
		$header_index = $header->ID;

		$header_images[$header_index] = array();
		$header_images[$header_index]['attachment_id'] = $header->ID;
		$header_images[$header_index]['url'] =  $url;
		$header_images[$header_index]['thumbnail_url'] = $url;
		$header_images[$header_index]['alt_text'] = get_post_meta( $header->ID, '_wp_attachment_image_alt', true );

		if ( isset( $header_data['width'] ) )
			$header_images[$header_index]['width'] = $header_data['width'];
		if ( isset( $header_data['height'] ) )
			$header_images[$header_index]['height'] = $header_data['height'];
	}

	return $header_images;
}

function get_custom_header() {
	global $_wp_default_headers;
	if ( is_random_header_image() ) {
		$data = _get_random_header_data();
	} else {
		$data = get_theme_mod( 'header_image_data' );
		if ( ! $data && current_theme_supports( 'custom-header', 'default-image' ) ) {
			$directory_args = array( get_template_directory_uri(), get_stylesheet_directory_uri() );
			$data = array();
			$data['url'] = $data['thumbnail_url'] = vsprintf( get_theme_support( 'custom-header', 'default-image' ), $directory_args );
			if ( ! empty( $_wp_default_headers ) ) {
				foreach ( (array) $_wp_default_headers as $default_header ) {
					$url = vsprintf( $default_header['url'], $directory_args );
					if ( $data['url'] == $url ) {
						$data = $default_header;
						$data['url'] = $url;
						$data['thumbnail_url'] = vsprintf( $data['thumbnail_url'], $directory_args );
						break;
					}
				}
			}
		}
	}

	$default = array(
		'url'           => '',
		'thumbnail_url' => '',
		'width'         => get_theme_support( 'custom-header', 'width' ),
		'height'        => get_theme_support( 'custom-header', 'height' ),
	);
	return (object) wp_parse_args( $data, $default );
}

function register_default_headers( $headers ) {
	global $_wp_default_headers;
	$_wp_default_headers = array_merge( (array) $_wp_default_headers, (array) $headers );
}

function unregister_default_headers( $header ) {
	global $_wp_default_headers;
	if ( is_array( $header ) ) {
		array_map( 'unregister_default_headers', $header );
	} elseif ( isset( $_wp_default_headers[ $header ] ) ) {
		unset( $_wp_default_headers[ $header ] );
		return true;
	} else {
		return false;
	}
}

function _custom_background_cb() {
	$background = set_url_scheme( get_theme_mod('background_image', get_theme_support( 'custom-background', 'default-image' ) );
	$color = get_theme_mod('background_color', get_theme_support( 'custom-background', 'default-color' ) );
	if ( $color === get_theme_support( 'custom-background', 'default-color' ) ) {
		$color = false;
	}
	if ( ! $background && ! $color )
		return;
	$style = $color ? "background-color: #$color;" : '';
	if ( $background ) {
		$image = " background-image: url('$background');";
		$repeat = get_theme_mod( 'background_repeat', get_theme_support( 'custom-background', 'default-repeat' ) );
		if ( ! in_array( $repeat, array( 'no-repeat', 'repeat-x', 'repeat-y', 'repeat' ) ) )
			$repeat = 'repeat';
		$repeat = " background-repeat: $repeat;";
		$position = get_theme_mod( 'background_position_x', get_theme_support( 'custom-background', 'default-position-x' ) );
		if ( ! in_array( $position, array( 'center', 'right', 'left' ) ) )
			$position = 'left';
		$position = " background-position: top $position;";
		$attachment = get_theme_mod( 'background_attachment', get_theme_support( 'custom-background', 'default-attachment' ) );
		if ( ! in_array( $attachment, array( 'fixed', 'scroll' ) ) )
			$attachment = 'scroll';
		$attachment = " background-attachment: $attachment;";
		$style .= $image . $repeat . $position . $attachment;
	}
?>
<style type="text/css" id="custom-background-css">
body.custom-background { <?php echo trim( $style ); ?> }
</style>
<?php
}

function add_editor_style( $stylesheet = 'editor-style.css' ) {
	add_theme_support( 'editor-style' );
	if ( ! is_admin() ) return;
	global $editor_styles;
	$editor_styles = (array) $editor_styles;
	$stylesheet    = (array) $stylesheet;
	$editor_styles = array_merge( $editor_styles, $stylesheet );
}

function remove_editor_styles() {
	if ( ! current_theme_supports( 'editor-style' ) ) return false;
	_remove_theme_support( 'editor-style' );
	if ( is_admin() ) $GLOBALS['editor_styles'] = array();
	return true;
}

function get_editor_stylesheets() {
	$stylesheets = array();
	if ( ! empty( $GLOBALS['editor_styles'] ) && is_array( $GLOBALS['editor_styles'] ) ) {
		$editor_styles = $GLOBALS['editor_styles'];
		$editor_styles = array_unique( array_filter( $editor_styles ) );
		$style_uri = get_stylesheet_directory_uri();
		$style_dir = get_stylesheet_directory();
		foreach ( $editor_styles as $key => $file ) {
			if ( preg_match( '~^(https?:)?//~', $file ) ) {
				$stylesheets[] = esc_url_raw( $file );
				unset( $editor_styles[ $key ] );
			}
		}
		if ( is_child_theme() ) {
			$template_uri = get_template_directory_uri();
			$template_dir = get_template_directory();

			foreach ( $editor_styles as $key => $file ) {
				if ( $file && file_exists( "$template_dir/$file" ) ) {
					$stylesheets[] = "$template_uri/$file";
				}
			}
		}
		foreach ( $editor_styles as $file ) {
			if ( $file && file_exists( "$style_dir/$file" ) ) {
				$stylesheets[] = "$style_uri/$file";
			}
		}
	}
	return apply_filters( 'editor_stylesheets', $stylesheets );
}

function add_theme_support( $feature ) {
	global $_wp_theme_features;
	if ( func_num_args() == 1 )
		$args = true;
	else
		$args = array_slice( func_get_args(), 1 );
	switch ( $feature ) {
		case 'post-formats' :
			if ( is_array( $args[0] ) ) {
				$post_formats = get_post_format_slugs();
				unset( $post_formats['standard'] );
				$args[0] = array_intersect( $args[0], array_keys( $post_formats ) );
			}
			break;
		case 'html5' :
			if ( empty( $args[0] ) ) {
				$args = array( 0 => array( 'comment-list', 'comment-form', 'search-form' ) );
			} elseif ( ! is_array( $args[0] ) ) {
				_doing_it_wrong( "add_theme_support( 'html5' )", 'You need to pass an array of types.', '3.6.1' );
				return false;
			}
			if ( isset( $_wp_theme_features['html5'] ) )
				$args[0] = array_merge( $_wp_theme_features['html5'][0], $args[0] );
			break;
		case 'custom-logo':
			if ( ! is_array( $args ) ) {
				$args = array( 0 => array() );
			}
			$defaults = array(
				'width'       => null,
				'height'      => null,
				'flex-width'  => false,
				'flex-height' => false,
				'header-text' => '',
			);
			$args[0] = wp_parse_args( array_intersect_key( $args[0], $defaults ), $defaults );
			if ( is_null( $args[0]['width'] ) && is_null( $args[0]['height'] ) ) {
				$args[0]['flex-width']  = true;
				$args[0]['flex-height'] = true;
			}
			break;
		case 'custom-header-uploads' :
			return add_theme_support( 'custom-header', array( 'uploads' => true ) );
		case 'custom-header' :
			if ( ! is_array( $args ) ) $args = array( 0 => array() );
			$defaults = array(
				'default-image' => '',
				'random-default' => false,
				'width' => 0,
				'height' => 0,
				'flex-height' => false,
				'flex-width' => false,
				'default-text-color' => '',
				'header-text' => true,
				'uploads' => true,
				'wp-head-callback' => '',
				'admin-head-callback' => '',
				'admin-preview-callback' => ''
			);
			$jit = isset( $args[0]['__jit'] );
			unset( $args[0]['__jit'] );
			if ( isset( $_wp_theme_features['custom-header'] ) ) $args[0] = wp_parse_args( $_wp_theme_features['custom-header'][0], $args[0] );
			if ( $jit )	$args[0] = wp_parse_args( $args[0], $defaults );
			if ( defined( 'NO_HEADER_TEXT' ) )
				$args[0]['header-text'] = ! NO_HEADER_TEXT;
			elseif ( isset( $args[0]['header-text'] ) )
				define( 'NO_HEADER_TEXT', empty( $args[0]['header-text'] ) );
			if ( defined( 'HEADER_IMAGE_WIDTH' ) )
				$args[0]['width'] = (int) HEADER_IMAGE_WIDTH;
			elseif ( isset( $args[0]['width'] ) )
				define( 'HEADER_IMAGE_WIDTH', (int) $args[0]['width'] );
			if ( defined( 'HEADER_IMAGE_HEIGHT' ) )
				$args[0]['height'] = (int) HEADER_IMAGE_HEIGHT;
			elseif ( isset( $args[0]['height'] ) )
				define( 'HEADER_IMAGE_HEIGHT', (int) $args[0]['height'] );
			if ( defined( 'HEADER_TEXTCOLOR' ) )
				$args[0]['default-text-color'] = HEADER_TEXTCOLOR;
			elseif ( isset( $args[0]['default-text-color'] ) )
				define( 'HEADER_TEXTCOLOR', $args[0]['default-text-color'] );
			if ( defined( 'HEADER_IMAGE' ) )
				$args[0]['default-image'] = HEADER_IMAGE;
			elseif ( isset( $args[0]['default-image'] ) )
				define( 'HEADER_IMAGE', $args[0]['default-image'] );
			if ( $jit && ! empty( $args[0]['default-image'] ) )
				$args[0]['random-default'] = false;
			if ( $jit ) {
				if ( empty( $args[0]['width'] ) && empty( $args[0]['flex-width'] ) )
					$args[0]['flex-width'] = true;
				if ( empty( $args[0]['height'] ) && empty( $args[0]['flex-height'] ) )
					$args[0]['flex-height'] = true;
			}
			break;
		case 'custom-background' :
			if ( ! is_array( $args ) )
				$args = array( 0 => array() );
			$defaults = array(
				'default-image'          => '',
				'default-repeat'         => 'repeat',
				'default-position-x'     => 'left',
				'default-attachment'     => 'scroll',
				'default-color'          => '',
				'wp-head-callback'       => '_custom_background_cb',
				'admin-head-callback'    => '',
				'admin-preview-callback' => '',
			);
			$jit = isset( $args[0]['__jit'] );
			unset( $args[0]['__jit'] );
			if ( isset( $_wp_theme_features['custom-background'] ) )
				$args[0] = wp_parse_args( $_wp_theme_features['custom-background'][0], $args[0] );
			if ( $jit )
				$args[0] = wp_parse_args( $args[0], $defaults );
			if ( defined( 'BACKGROUND_COLOR' ) )
				$args[0]['default-color'] = BACKGROUND_COLOR;
			elseif ( isset( $args[0]['default-color'] ) || $jit )
				define( 'BACKGROUND_COLOR', $args[0]['default-color'] );
			if ( defined( 'BACKGROUND_IMAGE' ) )
				$args[0]['default-image'] = BACKGROUND_IMAGE;
			elseif ( isset( $args[0]['default-image'] ) || $jit )
				define( 'BACKGROUND_IMAGE', $args[0]['default-image'] );
			break;
		case 'title-tag' :
			if ( did_action( 'wp_loaded' ) ) {
				_doing_it_wrong( "add_theme_support( 'title-tag' )", sprintf( 'Theme support for %1$s should be registered before the %2$s hook.',
					'<code>title-tag</code>', '<code>wp_loaded</code>' ), '4.1' );
				return false;
			}
	}
	$_wp_theme_features[ $feature ] = $args;
}

function _custom_header_background_just_in_time() {
	global $custom_image_header, $custom_background;
	if ( current_theme_supports( 'custom-header' ) ) {
		add_theme_support( 'custom-header', array( '__jit' => true ) );
		$args = get_theme_support( 'custom-header' );
		if ( $args[0]['wp-head-callback'] ) add_action( 'wp_head', $args[0]['wp-head-callback'] );
		if ( is_admin() ) {
			require_once( ABSPATH . 'wp-admin/custom-header.php' );
			$custom_image_header = new Custom_Image_Header( $args[0]['admin-head-callback'], $args[0]['admin-preview-callback'] );
		}
	}
	if ( current_theme_supports( 'custom-background' ) ) {
		add_theme_support( 'custom-background', array( '__jit' => true ) );
		$args = get_theme_support( 'custom-background' );
		add_action( 'wp_head', $args[0]['wp-head-callback'] );
		if ( is_admin() ) {
			require_once( ABSPATH . 'wp-admin/custom-background.php' );
			$custom_background = new Custom_Background( $args[0]['admin-head-callback'], $args[0]['admin-preview-callback'] );
		}
	}
}

function _custom_logo_header_styles() {
	if ( ! current_theme_supports( 'custom-header', 'header-text' ) && get_theme_support( 'custom-logo', 'header-text' ) && ! get_theme_mod( 'header_text', true ) ) {
		$classes = (array) get_theme_support( 'custom-logo', 'header-text' );
		$classes = array_map( 'sanitize_html_class', $classes );
		$classes = '.' . implode( ', .', $classes );
	?>
		<style id="custom-logo-css" type="text/css">
			<?php echo $classes; ?> {position: absolute;clip: rect(1px, 1px, 1px, 1px);}
		</style>
	<?php
	}
}

function get_theme_support( $feature ) {
	global $_wp_theme_features;
	if ( ! isset( $_wp_theme_features[ $feature ] ) ) return false;
	if ( func_num_args() <= 1 )
		return $_wp_theme_features[ $feature ];
	$args = array_slice( func_get_args(), 1 );
	switch ( $feature ) {
		case 'custom-logo' :
		case 'custom-header' :
		case 'custom-background' :
			if ( isset( $_wp_theme_features[ $feature ][0][ $args[0] ] ) ) return $_wp_theme_features[ $feature ][0][ $args[0] ];
			return false;
		default :
			return $_wp_theme_features[ $feature ];
	}
}

function remove_theme_support( $feature ) {
	if ( in_array( $feature, array( 'editor-style', 'widgets', 'menus' ) ) ) return false;
	return _remove_theme_support( $feature );
}

function _remove_theme_support( $feature ) {
	global $_wp_theme_features;
	switch ( $feature ) {
		case 'custom-header-uploads' :
			if ( ! isset( $_wp_theme_features['custom-header'] ) )
				return false;
			add_theme_support( 'custom-header', array( 'uploads' => false ) );
			return;
	}

	if ( ! isset( $_wp_theme_features[ $feature ] ) )
		return false;

	switch ( $feature ) {
		case 'custom-header' :
			if ( ! did_action( 'wp_loaded' ) )
				break;
			$support = get_theme_support( 'custom-header' );
			if ( $support[0]['wp-head-callback'] )
				remove_action( 'wp_head', $support[0]['wp-head-callback'] );
			remove_action( 'admin_menu', array( $GLOBALS['custom_image_header'], 'init' ) );
			unset( $GLOBALS['custom_image_header'] );
			break;

		case 'custom-background' :
			if ( ! did_action( 'wp_loaded' ) )
				break;
			$support = get_theme_support( 'custom-background' );
			remove_action( 'wp_head', $support[0]['wp-head-callback'] );
			remove_action( 'admin_menu', array( $GLOBALS['custom_background'], 'init' ) );
			unset( $GLOBALS['custom_background'] );
			break;
	}
	unset( $_wp_theme_features[ $feature ] );
	return true;
}

function current_theme_supports( $feature ) {
	global $_wp_theme_features;
	if ( 'custom-header-uploads' == $feature ) return current_theme_supports( 'custom-header', 'uploads' );
	if ( !isset( $_wp_theme_features[$feature] ) ) return false;
	if ( func_num_args() <= 1 )	return true;
	$args = array_slice( func_get_args(), 1 );
	switch ( $feature ) {
		case 'post-thumbnails':
			if ( true === $_wp_theme_features[$feature] )
				return true;
			$content_type = $args[0];
			return in_array( $content_type, $_wp_theme_features[$feature][0] );

		case 'html5':
		case 'post-formats':
			$type = $args[0];
			return in_array( $type, $_wp_theme_features[$feature][0] );

		case 'custom-logo':
		case 'custom-header':
		case 'custom-background':
			return ( isset( $_wp_theme_features[ $feature ][0][ $args[0] ] ) && $_wp_theme_features[ $feature ][0][ $args[0] ] );
	}
	return apply_filters( "current_theme_supports-{$feature}", true, $args, $_wp_theme_features[$feature] );
}

function require_if_theme_supports( $feature, $include ) {
	if ( current_theme_supports( $feature ) ) {
		require ( $include );
		return true;
	}
	return false;
}

function _delete_attachment_theme_mod( $id ) {
	$attachment_image = wp_get_attachment_url( $id );
	$header_image     = get_header_image();
	$background_image = get_theme_mod('background_image', get_theme_support( 'custom-background', 'default-image' );
	$custom_logo_id   = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id && $custom_logo_id == $id ) {
		remove_theme_mod( 'custom_logo' );
		remove_theme_mod( 'header_text' );
	}
	if ( $header_image && $header_image == $attachment_image ) {
		remove_theme_mod( 'header_image' );
		remove_theme_mod( 'header_image_data' );
	}
	if ( $background_image && $background_image == $attachment_image ) {
		remove_theme_mod( 'background_image' );
	}
}

function _wp_customize_include() {
	if ( ! ( ( isset( $_REQUEST['wp_customize'] ) && 'on' == $_REQUEST['wp_customize'] )
		|| ( is_admin() && 'customize.php' == basename( $_SERVER['PHP_SELF'] ) )
	) ) {
		return;
	}
	require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
	$GLOBALS['wp_customize'] = new WP_Customize_Manager();
}

function _wp_customize_loader_settings() {
	$admin_origin = parse_url( admin_url() );
	$home_origin  = parse_url( home_url() );
	$cross_domain = ( strtolower( $admin_origin[ 'host' ] ) != strtolower( $home_origin[ 'host' ] ) );

	$browser = array(
		'mobile' => wp_is_mobile(),
		'ios'    => wp_is_mobile() && preg_match( '/iPad|iPod|iPhone/', $_SERVER['HTTP_USER_AGENT'] ),
	);

	$settings = array(
		'url'           => esc_url( admin_url( 'customize.php' ) ),
		'isCrossDomain' => $cross_domain,
		'browser'       => $browser,
		'l10n'          => array(
			'saveAlert'       => 'The changes you made will be lost if you navigate away from this page.',
			'mainIframeTitle' => 'Customizer',
		),
	);

	$script = 'var _wpCustomizeLoaderSettings = ' . wp_json_encode( $settings ) . ';';

	$wp_scripts = wp_scripts();
	$data = $wp_scripts->get_data( 'customize-loader', 'data' );
	if ( $data )
		$script = "$data\n$script";

	$wp_scripts->add_data( 'customize-loader', 'data', $script );
}

function wp_customize_url( $stylesheet = null ) {
	$url = admin_url( 'customize.php' );
	if ( $stylesheet )
		$url .= '?theme=' . urlencode( $stylesheet );
	return esc_url( $url );
}

function wp_customize_support_script() {
	$admin_origin = parse_url( admin_url() );
	$home_origin  = parse_url( home_url() );
	$cross_domain = ( strtolower( $admin_origin[ 'host' ] ) != strtolower( $home_origin[ 'host' ] ) );

	?>
	<script type="text/javascript">
		(function() {
			var request, b = document.body, c = 'className', cs = 'customize-support', rcs = new RegExp('(^|\\s+)(no-)?'+cs+'(\\s+|$)');

<?php		if ( $cross_domain ): ?>
			request = (function(){ var xhr = new XMLHttpRequest(); return ('withCredentials' in xhr); })();
<?php		else: ?>
			request = true;
<?php		endif; ?>

			b[c] = b[c].replace( rcs, ' ' );
			b[c] += ( window.postMessage && request ? ' ' : ' no-' ) + cs;
		}());
	</script>
	<?php
}

function is_customize_preview() {
	global $wp_customize;
	return ( $wp_customize instanceof WP_Customize_Manager ) && $wp_customize->is_preview();
}
