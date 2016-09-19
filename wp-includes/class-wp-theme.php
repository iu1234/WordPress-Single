<?php
/**
 * WP_Theme Class
 *
 * @package WordPress
 * @subpackage Theme
 * @since 3.4.0
 */
final class WP_Theme implements ArrayAccess {

	public $update = false;

	private static $file_headers = array(
		'Name'        => 'Theme Name',
		'ThemeURI'    => 'Theme URI',
		'Description' => 'Description',
		'Author'      => 'Author',
		'AuthorURI'   => 'Author URI',
		'Version'     => 'Version',
		'Template'    => 'Template',
		'Status'      => 'Status',
		'Tags'        => 'Tags',
		'TextDomain'  => 'Text Domain',
		'DomainPath'  => 'Domain Path',
	);

	private static $default_themes = array(
		'classic'        => 'WordPress Classic',
		'default'        => 'WordPress Default',
		'twentyfourteen' => 'Twenty Fourteen',
		'twentyfifteen'  => 'Twenty Fifteen',
		'twentysixteen'  => 'Twenty Sixteen',
	);

	private static $tag_map = array(
		'fixed-width'    => 'fixed-layout',
		'flexible-width' => 'fluid-layout',
	);

	private $theme_root;

	private $headers = array();

	private $headers_sanitized;

	private $name_translated;

	private $errors;

	private $stylesheet;

	private $template;

	private $parent;

	private $theme_root_uri;

	private $textdomain_loaded;

	private $cache_hash;

	private static $persistently_cache;

	private static $cache_expiration = 1800;

	public function __construct( $theme_dir, $theme_root, $_child = null ) {
		global $wp_theme_directories;

		// Initialize caching on first run.
		if ( ! isset( self::$persistently_cache ) ) {
			/** This action is documented in wp-includes/theme.php */
			self::$persistently_cache = apply_filters( 'wp_cache_themes_persistently', false, 'WP_Theme' );
			if ( self::$persistently_cache ) {
				wp_cache_add_global_groups( 'themes' );
				if ( is_int( self::$persistently_cache ) )
					self::$cache_expiration = self::$persistently_cache;
			} else {
				wp_cache_add_non_persistent_groups( 'themes' );
			}
		}

		$this->theme_root = $theme_root;
		$this->stylesheet = $theme_dir;

		// Correct a situation where the theme is 'some-directory/some-theme' but 'some-directory' was passed in as part of the theme root instead.
		if ( ! in_array( $theme_root, (array) $wp_theme_directories ) && in_array( dirname( $theme_root ), (array) $wp_theme_directories ) ) {
			$this->stylesheet = basename( $this->theme_root ) . '/' . $this->stylesheet;
			$this->theme_root = dirname( $theme_root );
		}

		$this->cache_hash = md5( $this->theme_root . '/' . $this->stylesheet );
		$theme_file = $this->stylesheet . '/style.css';

		$cache = $this->cache_get( 'theme' );

		if ( is_array( $cache ) ) {
			foreach ( array( 'errors', 'headers', 'template' ) as $key ) {
				if ( isset( $cache[ $key ] ) )
					$this->$key = $cache[ $key ];
			}
			if ( $this->errors )
				return;
			if ( isset( $cache['theme_root_template'] ) )
				$theme_root_template = $cache['theme_root_template'];
		} elseif ( ! file_exists( $this->theme_root . '/' . $theme_file ) ) {
			$this->headers['Name'] = $this->stylesheet;
			if ( ! file_exists( $this->theme_root . '/' . $this->stylesheet ) )
				$this->errors = new WP_Error( 'theme_not_found', sprintf( 'The theme directory "%s" does not exist.', esc_html( $this->stylesheet ) ) );
			else
				$this->errors = new WP_Error( 'theme_no_stylesheet', 'Stylesheet is missing.' );
			$this->template = $this->stylesheet;
			$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
			if ( ! file_exists( $this->theme_root ) )
				$this->errors->add( 'theme_root_missing', 'ERROR: The themes directory is either empty or doesn&#8217;t exist. Please check your installation.' );
			return;
		} elseif ( ! is_readable( $this->theme_root . '/' . $theme_file ) ) {
			$this->headers['Name'] = $this->stylesheet;
			$this->errors = new WP_Error( 'theme_stylesheet_not_readable', 'Stylesheet is not readable.' );
			$this->template = $this->stylesheet;
			$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
			return;
		} else {
			$this->headers = get_file_data( $this->theme_root . '/' . $theme_file, self::$file_headers, 'theme' );
			if ( $default_theme_slug = array_search( $this->headers['Name'], self::$default_themes ) ) {
				if ( basename( $this->stylesheet ) != $default_theme_slug )
					$this->headers['Name'] .= '/' . $this->stylesheet;
			}
		}

		if ( ! $this->template && ! ( $this->template = $this->headers['Template'] ) ) {
			$this->template = $this->stylesheet;
			if ( ! file_exists( $this->theme_root . '/' . $this->stylesheet . '/index.php' ) ) {
				$error_message = sprintf(
					__( 'Template is missing. Standalone themes need to have a %1$s template file. Child themes need to have a Template header in the %2$s stylesheet.' ),
					'<code>index.php</code>',
					'<code>style.css</code>'
				);
				$this->errors = new WP_Error( 'theme_no_index', $error_message );
				$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
				return;
			}
		}

		// If we got our data from cache, we can assume that 'template' is pointing to the right place.
		if ( ! is_array( $cache ) && $this->template != $this->stylesheet && ! file_exists( $this->theme_root . '/' . $this->template . '/index.php' ) ) {
			// If we're in a directory of themes inside /themes, look for the parent nearby.
			// wp-content/themes/directory-of-themes/*
			$parent_dir = dirname( $this->stylesheet );
			if ( '.' != $parent_dir && file_exists( $this->theme_root . '/' . $parent_dir . '/' . $this->template . '/index.php' ) ) {
				$this->template = $parent_dir . '/' . $this->template;
			} elseif ( ( $directories = search_theme_directories() ) && isset( $directories[ $this->template ] ) ) {
				$theme_root_template = $directories[ $this->template ]['theme_root'];
			} else {
				$this->errors = new WP_Error( 'theme_no_parent', sprintf( 'The parent theme is missing. Please install the "%s" parent theme.', esc_html( $this->template ) ) );
				$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
				$this->parent = new WP_Theme( $this->template, $this->theme_root, $this );
				return;
			}
		}

		if ( $this->template != $this->stylesheet ) {
			// If we are a parent, then there is a problem. Only two generations allowed! Cancel things out.
			if ( $_child instanceof WP_Theme && $_child->template == $this->stylesheet ) {
				$_child->parent = null;
				$_child->errors = new WP_Error( 'theme_parent_invalid', sprintf( 'The "%s" theme is not a valid parent theme.', esc_html( $_child->template ) ) );
				$_child->cache_add( 'theme', array( 'headers' => $_child->headers, 'errors' => $_child->errors, 'stylesheet' => $_child->stylesheet, 'template' => $_child->template ) );
				// The two themes actually reference each other with the Template header.
				if ( $_child->stylesheet == $this->template ) {
					$this->errors = new WP_Error( 'theme_parent_invalid', sprintf( 'The "%s" theme is not a valid parent theme.', esc_html( $this->template ) ) );
					$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
				}
				return;
			}
			$this->parent = new WP_Theme( $this->template, isset( $theme_root_template ) ? $theme_root_template : $this->theme_root, $this );
		}

		if ( ! is_array( $cache ) ) {
			$cache = array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template );
			if ( isset( $theme_root_template ) )
				$cache['theme_root_template'] = $theme_root_template;
			$this->cache_add( 'theme', $cache );
		}
	}

	public function __toString() {
		return (string) $this->display('Name');
	}

	public function __isset( $offset ) {
		static $properties = array(
			'name', 'title', 'version', 'parent_theme', 'template_dir', 'stylesheet_dir', 'template', 'stylesheet',
			'screenshot', 'description', 'author', 'tags', 'theme_root', 'theme_root_uri',
		);

		return in_array( $offset, $properties );
	}

	public function __get( $offset ) {
		switch ( $offset ) {
			case 'name' :
			case 'title' :
				return $this->get('Name');
			case 'version' :
				return $this->get('Version');
			case 'parent_theme' :
				return $this->parent() ? $this->parent()->get('Name') : '';
			case 'template_dir' :
				return $this->get_template_directory();
			case 'stylesheet_dir' :
				return $this->get_stylesheet_directory();
			case 'template' :
				return $this->get_template();
			case 'stylesheet' :
				return $this->get_stylesheet();
			case 'screenshot' :
				return $this->get_screenshot( 'relative' );
			// 'author' and 'description' did not previously return translated data.
			case 'description' :
				return $this->display('Description');
			case 'author' :
				return $this->display('Author');
			case 'tags' :
				return $this->get( 'Tags' );
			case 'theme_root' :
				return $this->get_theme_root();
			case 'theme_root_uri' :
				return $this->get_theme_root_uri();
			// For cases where the array was converted to an object.
			default :
				return $this->offsetGet( $offset );
		}
	}

	public function offsetSet( $offset, $value ) {}

	public function offsetUnset( $offset ) {}

	public function offsetExists( $offset ) {
		static $keys = array(
			'Name', 'Version', 'Status', 'Title', 'Author', 'Author Name', 'Author URI', 'Description',
			'Template', 'Stylesheet', 'Template Files', 'Stylesheet Files', 'Template Dir', 'Stylesheet Dir',
			'Screenshot', 'Tags', 'Theme Root', 'Theme Root URI', 'Parent Theme',
		);

		return in_array( $offset, $keys );
	}

	public function offsetGet( $offset ) {
		switch ( $offset ) {
			case 'Name' :
			case 'Title' :
				// See note above about using translated data. get() is not ideal.
				// It is only for backwards compatibility. Use display().
				return $this->get('Name');
			case 'Author' :
				return $this->display( 'Author');
			case 'Author Name' :
				return $this->display( 'Author', false);
			case 'Author URI' :
				return $this->display('AuthorURI');
			case 'Description' :
				return $this->display( 'Description');
			case 'Version' :
			case 'Status' :
				return $this->get( $offset );
			case 'Template' :
				return $this->get_template();
			case 'Stylesheet' :
				return $this->get_stylesheet();
			case 'Template Files' :
				return $this->get_files( 'php', 1, true );
			case 'Stylesheet Files' :
				return $this->get_files( 'css', 0, false );
			case 'Template Dir' :
				return $this->get_template_directory();
			case 'Stylesheet Dir' :
				return $this->get_stylesheet_directory();
			case 'Screenshot' :
				return $this->get_screenshot( 'relative' );
			case 'Tags' :
				return $this->get('Tags');
			case 'Theme Root' :
				return $this->get_theme_root();
			case 'Theme Root URI' :
				return $this->get_theme_root_uri();
			case 'Parent Theme' :
				return $this->parent() ? $this->parent()->get('Name') : '';
			default :
				return null;
		}
	}

	public function errors() {
		return is_wp_error( $this->errors ) ? $this->errors : false;
	}

	public function exists() {
		return ! ( $this->errors() && in_array( 'theme_not_found', $this->errors()->get_error_codes() ) );
	}

	public function parent() {
		return isset( $this->parent ) ? $this->parent : false;
	}

	private function cache_add( $key, $data ) {
		return wp_cache_add( $key . '-' . $this->cache_hash, $data, 'themes', self::$cache_expiration );
	}

	private function cache_get( $key ) {
		return wp_cache_get( $key . '-' . $this->cache_hash, 'themes' );
	}

	public function cache_delete() {
		foreach ( array( 'theme', 'screenshot', 'headers', 'page_templates' ) as $key )
			wp_cache_delete( $key . '-' . $this->cache_hash, 'themes' );
		$this->template = $this->textdomain_loaded = $this->theme_root_uri = $this->parent = $this->errors = $this->headers_sanitized = $this->name_translated = null;
		$this->headers = array();
		$this->__construct( $this->stylesheet, $this->theme_root );
	}

	public function get( $header ) {
		if ( ! isset( $this->headers[ $header ] ) )
			return false;

		if ( ! isset( $this->headers_sanitized ) ) {
			$this->headers_sanitized = $this->cache_get( 'headers' );
			if ( ! is_array( $this->headers_sanitized ) )
				$this->headers_sanitized = array();
		}

		if ( isset( $this->headers_sanitized[ $header ] ) )
			return $this->headers_sanitized[ $header ];

		// If themes are a persistent group, sanitize everything and cache it. One cache add is better than many cache sets.
		if ( self::$persistently_cache ) {
			foreach ( array_keys( $this->headers ) as $_header )
				$this->headers_sanitized[ $_header ] = $this->sanitize_header( $_header, $this->headers[ $_header ] );
			$this->cache_add( 'headers', $this->headers_sanitized );
		} else {
			$this->headers_sanitized[ $header ] = $this->sanitize_header( $header, $this->headers[ $header ] );
		}

		return $this->headers_sanitized[ $header ];
	}

	public function display( $header, $markup = true, $translate = true ) {
		$value = $this->get( $header );
		if ( false === $value ) {
			return false;
		}

		if ( $translate && ( empty( $value ) || ! $this->load_textdomain() ) )
			$translate = false;

		if ( $translate )
			$value = $this->translate_header( $header, $value );

		if ( $markup )
			$value = $this->markup_header( $header, $value, $translate );

		return $value;
	}

	private function sanitize_header( $header, $value ) {
		switch ( $header ) {
			case 'Status' :
				if ( ! $value ) {
					$value = 'publish';
					break;
				}
			case 'Name' :
				static $header_tags = array(
					'abbr'    => array( 'title' => true ),
					'acronym' => array( 'title' => true ),
					'code'    => true,
					'em'      => true,
					'strong'  => true,
				);
				$value = wp_kses( $value, $header_tags );
				break;
			case 'Author' :
			case 'Description' :
				static $header_tags_with_a = array(
					'a'       => array( 'href' => true, 'title' => true ),
					'abbr'    => array( 'title' => true ),
					'acronym' => array( 'title' => true ),
					'code'    => true,
					'em'      => true,
					'strong'  => true,
				);
				$value = wp_kses( $value, $header_tags_with_a );
				break;
			case 'ThemeURI' :
			case 'AuthorURI' :
				$value = esc_url_raw( $value );
				break;
			case 'Tags' :
				$value = array_filter( array_map( 'trim', explode( ',', strip_tags( $value ) ) ) );
				break;
			case 'Version' :
				$value = strip_tags( $value );
				break;
		}

		return $value;
	}

	private function markup_header( $header, $value, $translate ) {
		switch ( $header ) {
			case 'Name' :
				if ( empty( $value ) )
					$value = $this->get_stylesheet();
				break;
			case 'Description' :
				$value = wptexturize( $value );
				break;
			case 'Author' :
				if ( $this->get('AuthorURI') ) {
					$value = sprintf( '<a href="%1$s">%2$s</a>', $this->display( 'AuthorURI', true, $translate ), $value );
				} elseif ( ! $value ) {
					$value = 'Anonymous';
				}
				break;
			case 'Tags' :
				static $comma = null;
				if ( ! isset( $comma ) ) {
					$comma = ', ';
				}
				$value = implode( $comma, $value );
				break;
			case 'ThemeURI' :
			case 'AuthorURI' :
				$value = esc_url( $value );
				break;
		}

		return $value;
	}

	private function translate_header( $header, $value ) {
		switch ( $header ) {
			case 'Name' :
				// Cached for sorting reasons.
				if ( isset( $this->name_translated ) )
					return $this->name_translated;
				$this->name_translated = translate( $value, $this->get('TextDomain' ) );
				return $this->name_translated;
			case 'Tags' :
				if ( empty( $value ) || ! function_exists( 'get_theme_feature_list' ) )
					return $value;

				static $tags_list;
				if ( ! isset( $tags_list ) ) {
					$tags_list = array();
					$feature_list = get_theme_feature_list( false ); // No API
					foreach ( $feature_list as $tags )
						$tags_list += $tags;
				}

				foreach ( $value as &$tag ) {
					if ( isset( $tags_list[ $tag ] ) ) {
						$tag = $tags_list[ $tag ];
					} elseif ( isset( self::$tag_map[ $tag ] ) ) {
						$tag = $tags_list[ self::$tag_map[ $tag ] ];
					}
				}

				return $value;

			default :
				$value = translate( $value, $this->get('TextDomain') );
		}
		return $value;
	}

	public function get_stylesheet() {
		return $this->stylesheet;
	}

	public function get_template() {
		return $this->template;
	}

	public function get_stylesheet_directory() {
		if ( $this->errors() && in_array( 'theme_root_missing', $this->errors()->get_error_codes() ) )
			return '';

		return $this->theme_root . '/' . $this->stylesheet;
	}

	public function get_template_directory() {
		if ( $this->parent() )
			$theme_root = $this->parent()->theme_root;
		else
			$theme_root = $this->theme_root;

		return $theme_root . '/' . $this->template;
	}

	public function get_stylesheet_directory_uri() {
		return $this->get_theme_root_uri() . '/' . str_replace( '%2F', '/', rawurlencode( $this->stylesheet ) );
	}

	public function get_template_directory_uri() {
		if ( $this->parent() )
			$theme_root_uri = $this->parent()->get_theme_root_uri();
		else
			$theme_root_uri = $this->get_theme_root_uri();

		return $theme_root_uri . '/' . str_replace( '%2F', '/', rawurlencode( $this->template ) );
	}

	public function get_theme_root() {
		return $this->theme_root;
	}

	public function get_theme_root_uri() {
		if ( ! isset( $this->theme_root_uri ) )
			$this->theme_root_uri = get_theme_root_uri( $this->stylesheet, $this->theme_root );
		return $this->theme_root_uri;
	}

	public function get_screenshot( $uri = 'uri' ) {
		$screenshot = $this->cache_get( 'screenshot' );
		if ( $screenshot ) {
			if ( 'relative' == $uri )
				return $screenshot;
			return $this->get_stylesheet_directory_uri() . '/' . $screenshot;
		} elseif ( 0 === $screenshot ) {
			return false;
		}

		foreach ( array( 'png', 'gif', 'jpg', 'jpeg' ) as $ext ) {
			if ( file_exists( $this->get_stylesheet_directory() . "/screenshot.$ext" ) ) {
				$this->cache_add( 'screenshot', 'screenshot.' . $ext );
				if ( 'relative' == $uri )
					return 'screenshot.' . $ext;
				return $this->get_stylesheet_directory_uri() . '/' . 'screenshot.' . $ext;
			}
		}

		$this->cache_add( 'screenshot', 0 );
		return false;
	}

	public function get_files( $type = null, $depth = 0, $search_parent = false ) {
		$files = (array) self::scandir( $this->get_stylesheet_directory(), $type, $depth );

		if ( $search_parent && $this->parent() )
			$files += (array) self::scandir( $this->get_template_directory(), $type, $depth );

		return $files;
	}

	public function get_page_templates( $post = null ) {
		if ( $this->errors() && $this->errors()->get_error_codes() !== array( 'theme_parent_invalid' ) )
			return array();

		$page_templates = $this->cache_get( 'page_templates' );

		if ( ! is_array( $page_templates ) ) {
			$page_templates = array();

			$files = (array) $this->get_files( 'php', 1 );

			foreach ( $files as $file => $full_path ) {
				if ( ! preg_match( '|Template Name:(.*)$|mi', file_get_contents( $full_path ), $header ) )
					continue;
				$page_templates[ $file ] = _cleanup_header_comment( $header[1] );
			}

			$this->cache_add( 'page_templates', $page_templates );
		}

		if ( $this->load_textdomain() ) {
			foreach ( $page_templates as &$page_template ) {
				$page_template = $this->translate_header( 'Template Name', $page_template );
			}
		}

		if ( $this->parent() )
			$page_templates += $this->parent()->get_page_templates( $post );

		return (array) apply_filters( 'theme_page_templates', $page_templates, $this, $post );
	}

	private static function scandir( $path, $extensions = null, $depth = 0, $relative_path = '' ) {
		if ( ! is_dir( $path ) )
			return false;

		if ( $extensions ) {
			$extensions = (array) $extensions;
			$_extensions = implode( '|', $extensions );
		}

		$relative_path = trailingslashit( $relative_path );
		if ( '/' == $relative_path )
			$relative_path = '';

		$results = scandir( $path );
		$files = array();

		foreach ( $results as $result ) {
			if ( '.' == $result[0] )
				continue;
			if ( is_dir( $path . '/' . $result ) ) {
				if ( ! $depth || 'CVS' == $result )
					continue;
				$found = self::scandir( $path . '/' . $result, $extensions, $depth - 1 , $relative_path . $result );
				$files = array_merge_recursive( $files, $found );
			} elseif ( ! $extensions || preg_match( '~\.(' . $_extensions . ')$~', $result ) ) {
				$files[ $relative_path . $result ] = $path . '/' . $result;
			}
		}

		return $files;
	}

	public function is_allowed( $check = 'both', $blog_id = null ) {
		if ( ! is_multisite() )
			return true;

		if ( 'both' == $check || 'network' == $check ) {
			$allowed = self::get_allowed_on_network();
			if ( ! empty( $allowed[ $this->get_stylesheet() ] ) )
				return true;
		}

		if ( 'both' == $check || 'site' == $check ) {
			$allowed = self::get_allowed_on_site( $blog_id );
			if ( ! empty( $allowed[ $this->get_stylesheet() ] ) )
				return true;
		}

		return false;
	}

	public static function get_core_default_theme() {
		foreach ( array_reverse( self::$default_themes ) as $slug => $name ) {
			$theme = wp_get_theme( $slug );
			if ( $theme->exists() ) {
				return $theme;
			}
		}
		return false;
	}

	public static function get_allowed( $blog_id = null ) {
		$network = (array) apply_filters( 'network_allowed_themes', self::get_allowed_on_network(), $blog_id );
		return $network + self::get_allowed_on_site( $blog_id );
	}

	public static function get_allowed_on_network() {
		static $allowed_themes;
		if ( ! isset( $allowed_themes ) ) {
			$allowed_themes = (array) get_site_option( 'allowedthemes' );
		}

		$allowed_themes = apply_filters( 'allowed_themes', $allowed_themes );

		return $allowed_themes;
	}

	public static function get_allowed_on_site( $blog_id = null ) {
		static $allowed_themes = array();

		if ( ! $blog_id || ! is_multisite() )
			$blog_id = get_current_blog_id();

		if ( isset( $allowed_themes[ $blog_id ] ) ) {
			return (array) apply_filters( 'site_allowed_themes', $allowed_themes[ $blog_id ], $blog_id );
		}

		$current = $blog_id == get_current_blog_id();

		if ( $current ) {
			$allowed_themes[ $blog_id ] = get_option( 'allowedthemes' );
		} else {
			switch_to_blog( $blog_id );
			$allowed_themes[ $blog_id ] = get_option( 'allowedthemes' );
			restore_current_blog();
		}

		if ( false === $allowed_themes[ $blog_id ] ) {
			if ( $current ) {
				$allowed_themes[ $blog_id ] = get_option( 'allowed_themes' );
			} else {
				switch_to_blog( $blog_id );
				$allowed_themes[ $blog_id ] = get_option( 'allowed_themes' );
				restore_current_blog();
			}

			if ( ! is_array( $allowed_themes[ $blog_id ] ) || empty( $allowed_themes[ $blog_id ] ) ) {
				$allowed_themes[ $blog_id ] = array();
			} else {
				$converted = array();
				$themes = wp_get_themes();
				foreach ( $themes as $stylesheet => $theme_data ) {
					if ( isset( $allowed_themes[ $blog_id ][ $theme_data->get('Name') ] ) )
						$converted[ $stylesheet ] = true;
				}
				$allowed_themes[ $blog_id ] = $converted;
			}
			// Set the option so we never have to go through this pain again.
			if ( is_admin() && $allowed_themes[ $blog_id ] ) {
				if ( $current ) {
					update_option( 'allowedthemes', $allowed_themes[ $blog_id ] );
					delete_option( 'allowed_themes' );
				} else {
					switch_to_blog( $blog_id );
					update_option( 'allowedthemes', $allowed_themes[ $blog_id ] );
					delete_option( 'allowed_themes' );
					restore_current_blog();
				}
			}
		}

		return (array) apply_filters( 'site_allowed_themes', $allowed_themes[ $blog_id ], $blog_id );
	}

	public static function sort_by_name( &$themes ) {
		if ( 0 === strpos( get_locale(), 'en_' ) ) {
			uasort( $themes, array( 'WP_Theme', '_name_sort' ) );
		} else {
			uasort( $themes, array( 'WP_Theme', '_name_sort_i18n' ) );
		}
	}

	private static function _name_sort( $a, $b ) {
		return strnatcasecmp( $a->headers['Name'], $b->headers['Name'] );
	}

	private static function _name_sort_i18n( $a, $b ) {
		return strnatcasecmp( $a->display( 'Name', false, true ), $b->display( 'Name', false, true ) );
	}
}
