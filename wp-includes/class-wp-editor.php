<?php
/**
 * Facilitates adding of the WordPress editor as used on the Write and Edit screens.
 *
 * @package WordPress
 * @since 3.3.0
 *
 * Private, not included by default. See wp_editor() in wp-includes/general-template.php.
 */

final class _WP_Editors {

	public static $mce_locale;

	private static $mce_settings = array();

	private static $qt_settings = array();

	private static $plugins = array();

	private static $qt_buttons = array();

	private static $ext_plugins;

	private static $baseurl;

	private static $first_init;

	private static $this_tinymce = false;

	private static $this_quicktags = false;

	private static $has_tinymce = false;

	private static $has_quicktags = false;

	private static $has_medialib = false;

	private static $editor_buttons_css = true;

	private static $drag_drop_upload = false;

	private static $old_dfw_compat = false;

	private function __construct() {}

	public static function parse_settings( $editor_id, $settings ) {

		$settings = apply_filters( 'wp_editor_settings', $settings, $editor_id );

		$set = wp_parse_args( $settings, array(
			'wpautop'             => true,
			'media_buttons'       => true,
			'default_editor'      => '',
			'drag_drop_upload'    => false,
			'textarea_name'       => $editor_id,
			'textarea_rows'       => 20,
			'tabindex'            => '',
			'tabfocus_elements'   => ':prev,:next',
			'editor_css'          => '',
			'editor_class'        => '',
			'teeny'               => false,
			'dfw'                 => false,
			'_content_editor_dfw' => false,
			'tinymce'             => true,
			'quicktags'           => true
		) );

		self::$this_tinymce = ( $set['tinymce'] && user_can_richedit() );

		if ( self::$this_tinymce ) {
			if ( false !== strpos( $editor_id, '[' ) ) {
				self::$this_tinymce = false;
				_deprecated_argument( 'wp_editor()', '3.9', 'TinyMCE editor IDs cannot have brackets.' );
			}
		}

		self::$this_quicktags = (bool) $set['quicktags'];

		if ( self::$this_tinymce )
			self::$has_tinymce = true;

		if ( self::$this_quicktags )
			self::$has_quicktags = true;

		if ( $set['dfw'] ) {
			self::$old_dfw_compat = true;
		}

		if ( empty( $set['editor_height'] ) )
			return $set;

		if ( 'content' === $editor_id && empty( $set['tinymce']['wp_autoresize_on'] ) ) {
			$cookie = (int) get_user_setting( 'ed_size' );

			if ( $cookie )
				$set['editor_height'] = $cookie;
		}

		if ( $set['editor_height'] < 50 )
			$set['editor_height'] = 50;
		elseif ( $set['editor_height'] > 5000 )
			$set['editor_height'] = 5000;

		return $set;
	}

	public static function editor( $content, $editor_id, $settings = array() ) {
		$set = self::parse_settings( $editor_id, $settings );
		$editor_class = ' class="' . trim( esc_attr( $set['editor_class'] ) . ' wp-editor-area' ) . '"';
		$tabindex = $set['tabindex'] ? ' tabindex="' . (int) $set['tabindex'] . '"' : '';
		$default_editor = 'html';
		$buttons = $autocomplete = '';
		$editor_id_attr = esc_attr( $editor_id );

		if ( $set['drag_drop_upload'] ) {
			self::$drag_drop_upload = true;
		}

		if ( ! empty( $set['editor_height'] ) ) {
			$height = ' style="height: ' . (int) $set['editor_height'] . 'px"';
		} else {
			$height = ' rows="' . (int) $set['textarea_rows'] . '"';
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			$set['media_buttons'] = false;
		}

		if ( self::$this_tinymce ) {
			$autocomplete = ' autocomplete="off"';

			if ( self::$this_quicktags ) {
				$default_editor = $set['default_editor'] ? $set['default_editor'] : wp_default_editor();
				if ( 'html' !== $default_editor ) {
					$default_editor = 'tinymce';
				}

				$buttons .= '<button type="button" id="' . $editor_id_attr . '-tmce" class="wp-switch-editor switch-tmce"' .
					' data-wp-editor-id="' . $editor_id_attr . '">' . "Visual</button>\n";
				$buttons .= '<button type="button" id="' . $editor_id_attr . '-html" class="wp-switch-editor switch-html"' .
					' data-wp-editor-id="' . $editor_id_attr . '">' . "Text</button>\n";
			} else {
				$default_editor = 'tinymce';
			}
		}

		$switch_class = 'html' === $default_editor ? 'html-active' : 'tmce-active';
		$wrap_class = 'wp-core-ui wp-editor-wrap ' . $switch_class;

		if ( $set['_content_editor_dfw'] ) {
			$wrap_class .= ' has-dfw';
		}

		echo '<div id="wp-' . $editor_id_attr . '-wrap" class="' . $wrap_class . '">';

		if ( self::$editor_buttons_css ) {
			wp_print_styles( 'editor-buttons' );
			self::$editor_buttons_css = false;
		}

		if ( ! empty( $set['editor_css'] ) ) {
			echo $set['editor_css'] . "\n";
		}

		if ( ! empty( $buttons ) || $set['media_buttons'] ) {
			echo '<div id="wp-' . $editor_id_attr . '-editor-tools" class="wp-editor-tools hide-if-no-js">';

			if ( $set['media_buttons'] ) {
				self::$has_medialib = true;

				if ( ! function_exists( 'media_buttons' ) )
					include( ABSPATH . 'wp-admin/includes/media.php' );

				echo '<div id="wp-' . $editor_id_attr . '-media-buttons" class="wp-media-buttons">';

				do_action( 'media_buttons', $editor_id );
				echo "</div>\n";
			}

			echo '<div class="wp-editor-tabs">' . $buttons . "</div>\n";
			echo "</div>\n";
		}

		$quicktags_toolbar = '';

		if ( self::$this_quicktags ) {
			if ( 'content' === $editor_id && ! empty( $GLOBALS['current_screen'] ) && $GLOBALS['current_screen']->base === 'post' ) {
				$toolbar_id = 'ed_toolbar';
			} else {
				$toolbar_id = 'qt_' . $editor_id_attr . '_toolbar';
			}

			$quicktags_toolbar = '<div id="' . $toolbar_id . '" class="quicktags-toolbar"></div>';
		}

		$the_editor = apply_filters( 'the_editor', '<div id="wp-' . $editor_id_attr . '-editor-container" class="wp-editor-container">' .
			$quicktags_toolbar .
			'<textarea' . $editor_class . $height . $tabindex . $autocomplete . ' cols="40" name="' . esc_attr( $set['textarea_name'] ) . '" ' .
			'id="' . $editor_id_attr . '">%s</textarea></div>' );

		// Prepare the content for the Visual or Text editor, only when TinyMCE is used (back-compat).
		if ( self::$this_tinymce ) {
			add_filter( 'the_editor_content', 'format_for_editor', 10, 2 );
		}

		$content = apply_filters( 'the_editor_content', $content, $default_editor );

		// Remove the filter as the next editor on the same page may not need it.
		if ( self::$this_tinymce ) {
			remove_filter( 'the_editor_content', 'format_for_editor' );
		}

		if ( 'html' === $default_editor && has_filter( 'htmledit_pre' ) ) {
			// TODO: needs _deprecated_filter(), use _deprecated_function() as substitute for now
			_deprecated_function( 'add_filter( htmledit_pre )', '4.3.0', 'add_filter( format_for_editor )' );
			$content = apply_filters( 'htmledit_pre', $content );
		} elseif ( 'tinymce' === $default_editor && has_filter( 'richedit_pre' ) ) {
			_deprecated_function( 'add_filter( richedit_pre )', '4.3.0', 'add_filter( format_for_editor )' );
			$content = apply_filters( 'richedit_pre', $content );
		}

		if ( false !== stripos( $content, 'textarea' ) ) {
			$content = preg_replace( '%</textarea%i', '&lt;/textarea', $content );
		}

		printf( $the_editor, $content );
		echo "\n</div>\n\n";

		self::editor_settings( $editor_id, $set );
	}

	public static function editor_settings($editor_id, $set) {
		global $wp_version, $tinymce_version;

		if ( empty(self::$first_init) ) {
			if ( is_admin() ) {
				add_action( 'admin_print_footer_scripts', array( __CLASS__, 'editor_js' ), 50 );
				add_action( 'admin_print_footer_scripts', array( __CLASS__, 'enqueue_scripts' ), 1 );
			} else {
				add_action( 'wp_print_footer_scripts', array( __CLASS__, 'editor_js' ), 50 );
				add_action( 'wp_print_footer_scripts', array( __CLASS__, 'enqueue_scripts' ), 1 );
			}
		}

		if ( self::$this_quicktags ) {

			$qtInit = array(
				'id' => $editor_id,
				'buttons' => ''
			);

			if ( is_array($set['quicktags']) )
				$qtInit = array_merge($qtInit, $set['quicktags']);

			if ( empty($qtInit['buttons']) )
				$qtInit['buttons'] = 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close';

			if ( $set['_content_editor_dfw'] ) {
				$qtInit['buttons'] .= ',dfw';
			}

			$qtInit = apply_filters( 'quicktags_settings', $qtInit, $editor_id );

			self::$qt_settings[$editor_id] = $qtInit;

			self::$qt_buttons = array_merge( self::$qt_buttons, explode(',', $qtInit['buttons']) );
		}

		if ( self::$this_tinymce ) {

			if ( empty( self::$first_init ) ) {
				self::$baseurl = includes_url( 'js/tinymce' );

				$mce_locale = get_locale();
				self::$mce_locale = $mce_locale = empty( $mce_locale ) ? 'en' : strtolower( substr( $mce_locale, 0, 2 ) ); // ISO 639-1

				/** This filter is documented in wp-admin/includes/media.php */
				$no_captions = (bool) apply_filters( 'disable_captions', '' );
				$ext_plugins = '';

				if ( $set['teeny'] ) {

					self::$plugins = $plugins = apply_filters( 'teeny_mce_plugins', array( 'colorpicker', 'lists', 'fullscreen', 'image', 'wordpress', 'wpeditimage', 'wplink' ), $editor_id );
				} else {

					$mce_external_plugins = apply_filters( 'mce_external_plugins', array() );

					$plugins = array(
						'charmap',
						'colorpicker',
						'hr',
						'lists',
						'media',
						'paste',
						'tabfocus',
						'textcolor',
						'fullscreen',
						'wordpress',
						'wpautoresize',
						'wpeditimage',
						'wpemoji',
						'wpgallery',
						'wplink',
						'wpdialogs',
						'wptextpattern',
						'wpview',
						'wpembed',
					);

					if ( ! self::$has_medialib ) {
						$plugins[] = 'image';
					}

					$plugins = array_unique( apply_filters( 'tiny_mce_plugins', $plugins ) );

					if ( ( $key = array_search( 'spellchecker', $plugins ) ) !== false ) {
						unset( $plugins[$key] );
					}

					if ( ! empty( $mce_external_plugins ) ) {

						$mce_external_languages = apply_filters( 'mce_external_languages', array() );

						$loaded_langs = array();
						$strings = '';

						if ( ! empty( $mce_external_languages ) ) {
							foreach ( $mce_external_languages as $name => $path ) {
								if ( @is_file( $path ) && @is_readable( $path ) ) {
									include_once( $path );
									$ext_plugins .= $strings . "\n";
									$loaded_langs[] = $name;
								}
							}
						}

						foreach ( $mce_external_plugins as $name => $url ) {
							if ( in_array( $name, $plugins, true ) ) {
								unset( $mce_external_plugins[ $name ] );
								continue;
							}

							$url = set_url_scheme( $url );
							$mce_external_plugins[ $name ] = $url;
							$plugurl = dirname( $url );
							$strings = '';

							// Try to load langs/[locale].js and langs/[locale]_dlg.js
							if ( ! in_array( $name, $loaded_langs, true ) ) {
								$path = str_replace( content_url(), '', $plugurl );
								$path = WP_CONTENT_DIR . $path . '/langs/';

								if ( function_exists('realpath') )
									$path = trailingslashit( realpath($path) );

								if ( @is_file( $path . $mce_locale . '.js' ) )
									$strings .= @file_get_contents( $path . $mce_locale . '.js' ) . "\n";

								if ( @is_file( $path . $mce_locale . '_dlg.js' ) )
									$strings .= @file_get_contents( $path . $mce_locale . '_dlg.js' ) . "\n";

								if ( 'en' != $mce_locale && empty( $strings ) ) {
									if ( @is_file( $path . 'en.js' ) ) {
										$str1 = @file_get_contents( $path . 'en.js' );
										$strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str1, 1 ) . "\n";
									}

									if ( @is_file( $path . 'en_dlg.js' ) ) {
										$str2 = @file_get_contents( $path . 'en_dlg.js' );
										$strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str2, 1 ) . "\n";
									}
								}

								if ( ! empty( $strings ) )
									$ext_plugins .= "\n" . $strings . "\n";
							}

							$ext_plugins .= 'tinyMCEPreInit.load_ext("' . $plugurl . '", "' . $mce_locale . '");' . "\n";
							$ext_plugins .= 'tinymce.PluginManager.load("' . $name . '", "' . $url . '");' . "\n";
						}
					}
				}

				self::$plugins = $plugins;
				self::$ext_plugins = $ext_plugins;

				self::$first_init = array(
					'theme' => 'modern',
					'skin' => 'lightgray',
					'language' => self::$mce_locale,
					'formats' => '{' .
						'alignleft: [' .
							'{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"left"}},' .
							'{selector: "img,table,dl.wp-caption", classes: "alignleft"}' .
						'],' .
						'aligncenter: [' .
							'{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"center"}},' .
							'{selector: "img,table,dl.wp-caption", classes: "aligncenter"}' .
						'],' .
						'alignright: [' .
							'{selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: {textAlign:"right"}},' .
							'{selector: "img,table,dl.wp-caption", classes: "alignright"}' .
						'],' .
						'strikethrough: {inline: "del"}' .
					'}',
					'relative_urls' => false,
					'remove_script_host' => false,
					'convert_urls' => false,
					'browser_spellcheck' => true,
					'fix_list_elements' => true,
					'entities' => '38,amp,60,lt,62,gt',
					'entity_encoding' => 'raw',
					'keep_styles' => false,
					'cache_suffix' => 'wp-mce-' . $tinymce_version,

					// Limit the preview styles in the menu/toolbar
					'preview_styles' => 'font-family font-size font-weight font-style text-decoration text-transform',

					'end_container_on_empty_block' => true,
					'wpeditimage_disable_captions' => $no_captions,
					'wpeditimage_html5_captions' => current_theme_supports( 'html5', 'caption' ),
					'plugins' => implode( ',', $plugins ),
					'wp_lang_attr' => get_bloginfo( 'language' )
				);

				if ( ! empty( $mce_external_plugins ) ) {
					self::$first_init['external_plugins'] = wp_json_encode( $mce_external_plugins );
				}

				$suffix = SCRIPT_DEBUG ? '' : '.min';
				$version = 'ver=' . $wp_version;
				$dashicons = includes_url( "css/dashicons$suffix.css?$version" );

				// WordPress default stylesheet and dashicons
				$mce_css = array(
					$dashicons,
					self::$baseurl . '/skins/wordpress/wp-content.css?' . $version
				);

				$editor_styles = get_editor_stylesheets();
				if ( ! empty( $editor_styles ) ) {
					foreach ( $editor_styles as $style ) {
						$mce_css[] = $style;
					}
				}

				$mce_css = trim( apply_filters( 'mce_css', implode( ',', $mce_css ) ), ' ,' );

				if ( ! empty($mce_css) )
					self::$first_init['content_css'] = $mce_css;
			}

			if ( $set['teeny'] ) {

				$mce_buttons = apply_filters( 'teeny_mce_buttons', array('bold', 'italic', 'underline', 'blockquote', 'strikethrough', 'bullist', 'numlist', 'alignleft', 'aligncenter', 'alignright', 'undo', 'redo', 'link', 'unlink', 'fullscreen'), $editor_id );
				$mce_buttons_2 = $mce_buttons_3 = $mce_buttons_4 = array();
			} else {
				$mce_buttons = array( 'bold', 'italic', 'strikethrough', 'bullist', 'numlist', 'blockquote', 'hr', 'alignleft', 'aligncenter', 'alignright', 'link', 'unlink', 'wp_more', 'spellchecker' );

				if ( ! wp_is_mobile() ) {
					if ( $set['_content_editor_dfw'] ) {
						$mce_buttons[] = 'dfw';
					} else {
						$mce_buttons[] = 'fullscreen';
					}
				}

				$mce_buttons[] = 'wp_adv';

				$mce_buttons = apply_filters( 'mce_buttons', $mce_buttons, $editor_id );

				$mce_buttons_2 = array( 'formatselect', 'underline', 'alignjustify', 'forecolor', 'pastetext', 'removeformat', 'charmap', 'outdent', 'indent', 'undo', 'redo' );

				if ( ! wp_is_mobile() ) {
					$mce_buttons_2[] = 'wp_help';
				}

				$mce_buttons_2 = apply_filters( 'mce_buttons_2', $mce_buttons_2, $editor_id );

				$mce_buttons_3 = apply_filters( 'mce_buttons_3', array(), $editor_id );

				$mce_buttons_4 = apply_filters( 'mce_buttons_4', array(), $editor_id );
			}

			$body_class = $editor_id;

			if ( $post = get_post() ) {
				$body_class .= ' post-type-' . sanitize_html_class( $post->post_type ) . ' post-status-' . sanitize_html_class( $post->post_status );
				if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
					$post_format = get_post_format( $post );
					if ( $post_format && ! is_wp_error( $post_format ) )
						$body_class .= ' post-format-' . sanitize_html_class( $post_format );
					else
						$body_class .= ' post-format-standard';
				}
			}

			$body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

			if ( !empty($set['tinymce']['body_class']) ) {
				$body_class .= ' ' . $set['tinymce']['body_class'];
				unset($set['tinymce']['body_class']);
			}

			$mceInit = array (
				'selector' => "#$editor_id",
				'resize' => 'vertical',
				'menubar' => false,
				'wpautop' => (bool) $set['wpautop'],
				'indent' => ! $set['wpautop'],
				'toolbar1' => implode($mce_buttons, ','),
				'toolbar2' => implode($mce_buttons_2, ','),
				'toolbar3' => implode($mce_buttons_3, ','),
				'toolbar4' => implode($mce_buttons_4, ','),
				'tabfocus_elements' => $set['tabfocus_elements'],
				'body_class' => $body_class
			);

			// Merge with the first part of the init array
			$mceInit = array_merge( self::$first_init, $mceInit );

			if ( is_array( $set['tinymce'] ) )
				$mceInit = array_merge( $mceInit, $set['tinymce'] );

			if ( $set['teeny'] ) {

				$mceInit = apply_filters( 'teeny_mce_before_init', $mceInit, $editor_id );
			} else {

				$mceInit = apply_filters( 'tiny_mce_before_init', $mceInit, $editor_id );
			}

			if ( empty( $mceInit['toolbar3'] ) && ! empty( $mceInit['toolbar4'] ) ) {
				$mceInit['toolbar3'] = $mceInit['toolbar4'];
				$mceInit['toolbar4'] = '';
			}

			self::$mce_settings[$editor_id] = $mceInit;
		} // end if self::$this_tinymce
	}

	private static function _parse_init($init) {
		$options = '';

		foreach ( $init as $k => $v ) {
			if ( is_bool($v) ) {
				$val = $v ? 'true' : 'false';
				$options .= $k . ':' . $val . ',';
				continue;
			} elseif ( !empty($v) && is_string($v) && ( ('{' == $v{0} && '}' == $v{strlen($v) - 1}) || ('[' == $v{0} && ']' == $v{strlen($v) - 1}) || preg_match('/^\(?function ?\(/', $v) ) ) {
				$options .= $k . ':' . $v . ',';
				continue;
			}
			$options .= $k . ':"' . $v . '",';
		}

		return '{' . trim( $options, ' ,' ) . '}';
	}

	public static function enqueue_scripts() {
		if ( self::$has_tinymce )
			wp_enqueue_script('editor');

		if ( self::$has_quicktags ) {
			wp_enqueue_script( 'quicktags' );
			wp_enqueue_style( 'buttons' );
		}

		if ( in_array('wplink', self::$plugins, true) || in_array('link', self::$qt_buttons, true) ) {
			wp_enqueue_script('wplink');
			wp_enqueue_script( 'jquery-ui-autocomplete' );
		}

		if ( self::$old_dfw_compat ) {
			wp_enqueue_script('wp-fullscreen-stub');
		}

		if ( self::$has_medialib ) {
			add_thickbox();
			wp_enqueue_script('media-upload');
		}

		do_action( 'wp_enqueue_editor', array(
			'tinymce'   => self::$has_tinymce,
			'quicktags' => self::$has_quicktags,
		) );
	}

	public static function wp_mce_translation( $mce_locale = '', $json_only = false ) {

		$mce_translation = array(
			// Default TinyMCE strings
			'New document' => 'New document',
			'Formats' => 'Formats',

			'Headings' => 'Headings',
			'Heading 1' => 'Heading 1',
			'Heading 2' => 'Heading 2',
			'Heading 3' => 'Heading 3',
			'Heading 4' => 'Heading 4',
			'Heading 5' => 'Heading 5',
			'Heading 6' => 'Heading 6',

			'Blocks' => _x( 'Blocks', 'TinyMCE' ),
			'Paragraph' => __( 'Paragraph' ),
			'Blockquote' => __( 'Blockquote' ),
			'Div' => _x( 'Div', 'HTML tag' ),
			'Pre' => _x( 'Pre', 'HTML tag' ),
			'Preformatted' => _x( 'Preformatted', 'HTML tag' ),
			'Address' => _x( 'Address', 'HTML tag' ),

			'Inline' => _x( 'Inline', 'HTML elements' ),
			'Underline' => __( 'Underline' ),
			'Strikethrough' => __( 'Strikethrough' ),
			'Subscript' => __( 'Subscript' ),
			'Superscript' => __( 'Superscript' ),
			'Clear formatting' => __( 'Clear formatting' ),
			'Bold' => __( 'Bold' ),
			'Italic' => __( 'Italic' ),
			'Code' => _x( 'Code', 'editor button' ),
			'Source code' => __( 'Source code' ),
			'Font Family' => __( 'Font Family' ),
			'Font Sizes' => __( 'Font Sizes' ),

			'Align center' => __( 'Align center' ),
			'Align right' => __( 'Align right' ),
			'Align left' => __( 'Align left' ),
			'Justify' => __( 'Justify' ),
			'Increase indent' => __( 'Increase indent' ),
			'Decrease indent' => __( 'Decrease indent' ),

			'Cut' => __( 'Cut' ),
			'Copy' => __( 'Copy' ),
			'Paste' => __( 'Paste' ),
			'Select all' => __( 'Select all' ),
			'Undo' => __( 'Undo' ),
			'Redo' => __( 'Redo' ),

			'Ok' => __( 'OK' ),
			'Cancel' => __( 'Cancel' ),
			'Close' => __( 'Close' ),
			'Visual aids' => __( 'Visual aids' ),

			'Bullet list' => __( 'Bulleted list' ),
			'Numbered list' => __( 'Numbered list' ),
			'Square' => _x( 'Square', 'list style' ),
			'Default' => _x( 'Default', 'list style' ),
			'Circle' => _x( 'Circle', 'list style' ),
			'Disc' => _x('Disc', 'list style' ),
			'Lower Greek' => _x( 'Lower Greek', 'list style' ),
			'Lower Alpha' => _x( 'Lower Alpha', 'list style' ),
			'Upper Alpha' => _x( 'Upper Alpha', 'list style' ),
			'Upper Roman' => _x( 'Upper Roman', 'list style' ),
			'Lower Roman' => _x( 'Lower Roman', 'list style' ),

			// Anchor plugin
			'Name' => _x( 'Name', 'Name of link anchor (TinyMCE)' ),
			'Anchor' => _x( 'Anchor', 'Link anchor (TinyMCE)' ),
			'Anchors' => _x( 'Anchors', 'Link anchors (TinyMCE)' ),

			// Fullpage plugin
			'Document properties' => __( 'Document properties' ),
			'Robots' => __( 'Robots' ),
			'Title' => __( 'Title' ),
			'Keywords' => __( 'Keywords' ),
			'Encoding' => __( 'Encoding' ),
			'Description' => __( 'Description' ),
			'Author' => __( 'Author' ),

			// Media, image plugins
			'Insert/edit image' => __( 'Insert/edit image' ),
			'General' => __( 'General' ),
			'Advanced' => __( 'Advanced' ),
			'Source' => __( 'Source' ),
			'Border' => __( 'Border' ),
			'Constrain proportions' => __( 'Constrain proportions' ),
			'Vertical space' => __( 'Vertical space' ),
			'Image description' => __( 'Image description' ),
			'Style' => __( 'Style' ),
			'Dimensions' => __( 'Dimensions' ),
			'Insert image' => __( 'Insert image' ),
			'Insert date/time' => __( 'Insert date/time' ),
			'Insert/edit video' => __( 'Insert/edit video' ),
			'Poster' => __( 'Poster' ),
			'Alternative source' => __( 'Alternative source' ),
			'Paste your embed code below:' => __( 'Paste your embed code below:' ),
			'Insert video' => __( 'Insert video' ),
			'Embed' => __( 'Embed' ),

			// Each of these have a corresponding plugin
			'Special character' => __( 'Special character' ),
			'Right to left' => _x( 'Right to left', 'editor button' ),
			'Left to right' => _x( 'Left to right', 'editor button' ),
			'Emoticons' => __( 'Emoticons' ),
			'Nonbreaking space' => __( 'Nonbreaking space' ),
			'Page break' => __( 'Page break' ),
			'Paste as text' => __( 'Paste as text' ),
			'Preview' => __( 'Preview' ),
			'Print' => __( 'Print' ),
			'Save' => __( 'Save' ),
			'Fullscreen' => __( 'Fullscreen' ),
			'Horizontal line' => __( 'Horizontal line' ),
			'Horizontal space' => __( 'Horizontal space' ),
			'Restore last draft' => __( 'Restore last draft' ),
			'Insert/edit link' => __( 'Insert/edit link' ),
			'Remove link' => __( 'Remove link' ),

			'Color' => __( 'Color' ),
			'Custom color' => __( 'Custom color' ),
			'Custom...' => _x( 'Custom...', 'label for custom color' ), // no ellipsis
			'No color' => __( 'No color' ),

			// Spelling, search/replace plugins
			'Could not find the specified string.' => __( 'Could not find the specified string.' ),
			'Replace' => _x( 'Replace', 'find/replace' ),
			'Next' => _x( 'Next', 'find/replace' ),
			/* translators: previous */
			'Prev' => _x( 'Prev', 'find/replace' ),
			'Whole words' => _x( 'Whole words', 'find/replace' ),
			'Find and replace' => __( 'Find and replace' ),
			'Replace with' => _x('Replace with', 'find/replace' ),
			'Find' => _x( 'Find', 'find/replace' ),
			'Replace all' => _x( 'Replace all', 'find/replace' ),
			'Match case' => 'Match case',
			'Spellcheck' => 'Check Spelling',
			'Finish' => 'Finish',
			'Ignore all' => 'Ignore all',
			'Ignore' => 'Ignore',
			'Add to Dictionary' => 'Add to Dictionary',

			'Insert table' => 'Insert table',
			'Delete table' => 'Delete table',
			'Table properties' => 'Table properties',
			'Row properties' => 'Table row properties',
			'Cell properties' =>'Table cell properties',
			'Border color' => 'Border color',

			'Row' => 'Row',
			'Rows' => 'Rows',
			'Column' => 'Column',
			'Cols' => 'Cols',
			'Cell' => 'Cell',
			'Header cell' => 'Header cell',
			'Header' => 'Header',
			'Body' => 'Body',
			'Footer' => 'Footer',

			'Insert row before' => 'Insert row before',
			'Insert row after' => 'Insert row after',
			'Insert column before' => 'Insert column before',
			'Insert column after' => 'Insert column after',
			'Paste row before' => 'Paste table row before',
			'Paste row after' => 'Paste table row after',
			'Delete row' => 'Delete row',
			'Delete column' => 'Delete column',
			'Cut row' => 'Cut table row',
			'Copy row' => 'Copy table row',
			'Merge cells' => 'Merge table cells',
			'Split cell' => 'Split table cell',

			'Height' => 'Height',
			'Width' => 'Width',
			'Caption' => 'Caption',
			'Alignment' => 'Alignment',
			'H Align' => 'H Align',
			'Left' => 'Left',
			'Center' => 'Center',
			'Right' => 'Right',
			'None' => 'None',
			'V Align' => 'V Align',
			'Top' => 'Top',
			'Middle' => 'Middle',
			'Bottom' => 'Bottom',

			'Row group' => 'Row group',
			'Column group' => 'Column group',
			'Row type' => 'Row type',
			'Cell type' => 'Cell type',
			'Cell padding' => 'Cell padding',
			'Cell spacing' => 'Cell spacing',
			'Scope' => 'Scope',

			'Insert template' => 'Insert template',
			'Templates' => 'Templates',

			'Background color' => 'Background color',
			'Text color' => 'Text color',
			'Show blocks' => 'Show blocks',
			'Show invisible characters' => 'Show invisible characters',

			'Words: {0}' => sprintf( 'Words: %s', '{0}' ),
			'Paste is now in plain text mode. Contents will now be pasted as plain text until you toggle this option off.' => __( 'Paste is now in plain text mode. Contents will now be pasted as plain text until you toggle this option off.' ) . "\n\n" . __( 'If you&#8217;re looking to paste rich content from Microsoft Word, try turning this option off. The editor will clean up text pasted from Word automatically.' ),
			'Rich Text Area. Press ALT-F9 for menu. Press ALT-F10 for toolbar. Press ALT-0 for help' => __( 'Rich Text Area. Press Alt-Shift-H for help' ),
			'You have unsaved changes are you sure you want to navigate away?' => __( 'The changes you made will be lost if you navigate away from this page.' ),
			'Your browser doesn\'t support direct access to the clipboard. Please use the Ctrl+X/C/V keyboard shortcuts instead.' => __( 'Your browser does not support direct access to the clipboard. Please use keyboard shortcuts or your browser&#8217;s edit menu instead.' ),

			'Insert' => 'Insert',
			'File' => 'File',
			'Edit' => 'Edit',
			'Tools' => 'Tools',
			'View' => 'View',
			'Table' => 'Table',
			'Format' => 'Format',

			'Toolbar Toggle' => 'Toolbar Toggle',
			'Insert Read More tag' => 'Insert Read More tag',
			'Insert Page Break tag' => 'Insert Page Break tag',
			'Read more...' => 'Read more...',
			'Distraction-free writing mode' => 'Distraction-free writing mode',
			'No alignment' => 'No alignment',
			'Remove' => 'Remove',
			'Edit ' => 'Edit',
			'Paste URL or type to search' => 'Paste URL or type to search',
			'Apply'  => 'Apply',
			'Link options'  => 'Link options',

			'Keyboard Shortcuts' => 'Keyboard Shortcuts',
			'Default shortcuts,' => 'Default shortcuts,',
			'Additional shortcuts,' => 'Additional shortcuts,',
			'Focus shortcuts:' => 'Focus shortcuts:',
			'Inline toolbar (when an image, link or preview is selected)' => 'Inline toolbar (when an image, link or preview is selected)',
			'Editor menu (when enabled)' => 'Editor menu (when enabled)',
			'Editor toolbar' => 'Editor toolbar',
			'Elements path' => 'Elements path',
			'Ctrl + Alt + letter:' => 'Ctrl + Alt + letter:',
			'Shift + Alt + letter:' => 'Shift + Alt + letter:',
			'Cmd + letter:' => 'Cmd + letter:',
			'Ctrl + letter:' => 'Ctrl + letter:',
			'Letter' => 'Letter',
			'Action' => 'Action',
			'To move focus to other buttons use Tab or the arrow keys. To return focus to the editor press Escape or use one of the buttons.' =>
				__( 'To move focus to other buttons use Tab or the arrow keys. To return focus to the editor press Escape or use one of the buttons.' ),
			'When starting a new paragraph with one of these formatting shortcuts followed by a space, the formatting will be applied automatically. Press Backspace or Escape to undo.' =>
				__( 'When starting a new paragraph with one of these formatting shortcuts followed by a space, the formatting will be applied automatically. Press Backspace or Escape to undo.' ),
			'The following formatting shortcuts are replaced when pressing Enter. Press Escape or the Undo button to undo.' =>
				__( 'The following formatting shortcuts are replaced when pressing Enter. Press Escape or the Undo button to undo.' ),
			'The next group of formatting shortcuts are applied as you type or when you insert them around plain text in the same paragraph. Press Escape or the Undo button to undo.' =>
				__( 'The next group of formatting shortcuts are applied as you type or when you insert them around plain text in the same paragraph. Press Escape or the Undo button to undo.' ),
		);


		if ( ! $mce_locale ) {
			$mce_locale = self::$mce_locale;
		}

		$mce_translation = apply_filters( 'wp_mce_translation', $mce_translation, $mce_locale );

		foreach ( $mce_translation as $key => $value ) {
			if ( $key === $value ) {
				unset( $mce_translation[$key] );
				continue;
			}

			if ( false !== strpos( $value, '&' ) ) {
				$mce_translation[$key] = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
			}
		}

		if ( $json_only ) {
			return wp_json_encode( $mce_translation );
		}

		$baseurl = self::$baseurl ? self::$baseurl : includes_url( 'js/tinymce' );

		return "tinymce.addI18n( '$mce_locale', " . wp_json_encode( $mce_translation ) . ");\n" .
			"tinymce.ScriptLoader.markDone( '$baseurl/langs/$mce_locale.js' );\n";
	}

	public static function editor_js() {
		global $wp_version, $tinymce_version, $concatenate_scripts, $compress_scripts;

		$version = 'ver=' . $tinymce_version;
		$tmce_on = !empty(self::$mce_settings);

		if ( ! isset($concatenate_scripts) )
			script_concat_settings();

		$compressed = $compress_scripts && $concatenate_scripts && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
			&& false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');

		$mceInit = $qtInit = '';
		if ( $tmce_on ) {
			foreach ( self::$mce_settings as $editor_id => $init ) {
				$options = self::_parse_init( $init );
				$mceInit .= "'$editor_id':{$options},";
			}
			$mceInit = '{' . trim($mceInit, ',') . '}';
		} else {
			$mceInit = '{}';
		}

		if ( !empty(self::$qt_settings) ) {
			foreach ( self::$qt_settings as $editor_id => $init ) {
				$options = self::_parse_init( $init );
				$qtInit .= "'$editor_id':{$options},";
			}
			$qtInit = '{' . trim($qtInit, ',') . '}';
		} else {
			$qtInit = '{}';
		}

		$ref = array(
			'plugins' => implode( ',', self::$plugins ),
			'theme' => 'modern',
			'language' => self::$mce_locale
		);

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		do_action( 'before_wp_tiny_mce', self::$mce_settings );
		?>

		<script type="text/javascript">
		tinyMCEPreInit = {
			baseURL: "<?php echo self::$baseurl; ?>",
			suffix: "<?php echo $suffix; ?>",
			<?php

			if ( self::$drag_drop_upload ) {
				echo 'dragDropUpload: true,';
			}

			?>
			mceInit: <?php echo $mceInit; ?>,
			qtInit: <?php echo $qtInit; ?>,
			ref: <?php echo self::_parse_init( $ref ); ?>,
			load_ext: function(url,lang){var sl=tinymce.ScriptLoader;sl.markDone(url+'/langs/'+lang+'.js');sl.markDone(url+'/langs/'+lang+'_dlg.js');}
		};
		</script>
		<?php

		$baseurl = self::$baseurl;
		$mce_suffix = false !== strpos( $wp_version, '-src' ) ? '' : '.min';

		if ( $tmce_on ) {
			if ( $compressed ) {
				echo "<script type='text/javascript' src='{$baseurl}/wp-tinymce.php?c=1&amp;$version'></script>\n";
			} else {
				echo "<script type='text/javascript' src='{$baseurl}/tinymce{$mce_suffix}.js?$version'></script>\n";
				echo "<script type='text/javascript' src='{$baseurl}/plugins/compat3x/plugin{$suffix}.js?$version'></script>\n";
			}

			echo "<script type='text/javascript'>\n" . self::wp_mce_translation() . "</script>\n";

			if ( self::$ext_plugins ) {
				echo "<script type='text/javascript' src='{$baseurl}/langs/wp-langs-en.js?$version'></script>\n";
			}
		}

		do_action( 'wp_tiny_mce_init', self::$mce_settings );

		?>
		<script type="text/javascript">
		<?php

		if ( self::$ext_plugins )
			echo self::$ext_plugins . "\n";

		if ( ! is_admin() )
			echo 'var ajaxurl = "' . admin_url( 'admin-ajax.php', 'relative' ) . '";';

		?>

		( function() {
			var init, id, $wrap;

			if ( typeof tinymce !== 'undefined' ) {
				for ( id in tinyMCEPreInit.mceInit ) {
					init = tinyMCEPreInit.mceInit[id];
					$wrap = tinymce.$( '#wp-' + id + '-wrap' );

					if ( ( $wrap.hasClass( 'tmce-active' ) || ! tinyMCEPreInit.qtInit.hasOwnProperty( id ) ) && ! init.wp_skip_init ) {
						tinymce.init( init );

						if ( ! window.wpActiveEditor ) {
							window.wpActiveEditor = id;
						}
					}
				}
			}

			if ( typeof quicktags !== 'undefined' ) {
				for ( id in tinyMCEPreInit.qtInit ) {
					quicktags( tinyMCEPreInit.qtInit[id] );

					if ( ! window.wpActiveEditor ) {
						window.wpActiveEditor = id;
					}
				}
			}
		}());
		</script>
		<?php

		if ( in_array( 'wplink', self::$plugins, true ) || in_array( 'link', self::$qt_buttons, true ) )
			self::wp_link_dialog();

		do_action( 'after_wp_tiny_mce', self::$mce_settings );
	}

	public static function wp_link_query( $args = array() ) {
		$pts = get_post_types( array( 'public' => true ), 'objects' );
		$pt_names = array_keys( $pts );

		$query = array(
			'post_type' => $pt_names,
			'suppress_filters' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'post_status' => 'publish',
			'posts_per_page' => 20,
		);

		$args['pagenum'] = isset( $args['pagenum'] ) ? absint( $args['pagenum'] ) : 1;

		if ( isset( $args['s'] ) )
			$query['s'] = $args['s'];

		$query['offset'] = $args['pagenum'] > 1 ? $query['posts_per_page'] * ( $args['pagenum'] - 1 ) : 0;

		$query = apply_filters( 'wp_link_query_args', $query );

		// Do main query.
		$get_posts = new WP_Query;
		$posts = $get_posts->query( $query );
		// Check if any posts were found.
		if ( ! $get_posts->post_count )
			return false;

		$results = array();
		foreach ( $posts as $post ) {
			if ( 'post' == $post->post_type )
				$info = mysql2date( 'Y/m/d', $post->post_date );
			else
				$info = $pts[ $post->post_type ]->labels->singular_name;

			$results[] = array(
				'ID' => $post->ID,
				'title' => trim( esc_html( strip_tags( get_the_title( $post ) ) ) ),
				'permalink' => get_permalink( $post->ID ),
				'info' => $info,
			);
		}

		return apply_filters( 'wp_link_query', $results, $query );
	}

	public static function wp_link_dialog() {
		?>
		<div id="wp-link-backdrop" style="display: none"></div>
		<div id="wp-link-wrap" class="wp-core-ui" style="display: none" role="dialog" aria-labelledby="link-modal-title">
		<form id="wp-link" tabindex="-1">
		<?php wp_nonce_field( 'internal-linking', '_ajax_linking_nonce', false ); ?>
		<h1 id="link-modal-title">Insert/edit link</h1>
		<button type="button" id="wp-link-close"><span class="screen-reader-text">Close</span></button>
		<div id="link-selector">
			<div id="link-options">
				<p class="howto" id="wplink-enter-url">Enter the destination URL</p>
				<div>
					<label><span>URL</span>
					<input id="wp-link-url" type="text" aria-describedby="wplink-enter-url" /></label>
				</div>
				<div class="wp-link-text-field">
					<label><span>Link Text</span>
					<input id="wp-link-text" type="text" /></label>
				</div>
				<div class="link-target">
					<label><span></span>
					<input type="checkbox" id="wp-link-target" /> Open link in a new tab</label>
				</div>
			</div>
			<p class="howto" id="wplink-link-existing-content">Or link to existing content</p>
			<div id="search-panel">
				<div class="link-search-wrapper">
					<label>
						<span class="search-label">Search</span>
						<input type="search" id="wp-link-search" class="link-search-field" autocomplete="off" aria-describedby="wplink-link-existing-content" />
						<span class="spinner"></span>
					</label>
				</div>
				<div id="search-results" class="query-results" tabindex="0">
					<ul></ul>
					<div class="river-waiting">
						<span class="spinner"></span>
					</div>
				</div>
				<div id="most-recent-results" class="query-results" tabindex="0">
					<div class="query-notice" id="query-notice-message">
						<em class="query-notice-default">No search term specified. Showing recent items.</em>
						<em class="query-notice-hint screen-reader-text">Search or use up and down arrow keys to select an item.</em>
					</div>
					<ul></ul>
					<div class="river-waiting">
						<span class="spinner"></span>
					</div>
 				</div>
 			</div>
		</div>
		<div class="submitbox">
			<div id="wp-link-cancel">
				<button type="button" class="button">Cancel</button>
			</div>
			<div id="wp-link-update">
				<input type="submit" value="Add Link" class="button button-primary" id="wp-link-submit" name="wp-link-submit">
			</div>
		</div>
		</form>
		</div>
		<?php
	}
}
