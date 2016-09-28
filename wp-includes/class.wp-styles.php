<?php
/**
 * Dependencies API: WP_Styles class
 *
 * @since 2.6.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

class WP_Styles extends WP_Dependencies {

	public $base_url;

	public $content_url;

	public $default_version;

	public $concat = '';

	public $concat_version = '';

	public $do_concat = false;

	public $print_html = '';

	public $print_code = '';

	public $default_dirs;

	public function __construct() {
		do_action_ref_array( 'wp_default_styles', array(&$this) );
	}

	public function do_item( $handle ) {
		if ( !parent::do_item($handle) )
			return false;

		$obj = $this->registered[$handle];
		if ( null === $obj->ver )
			$ver = '';
		else
			$ver = $obj->ver ? $obj->ver : $this->default_version;

		if ( isset($this->args[$handle]) )
			$ver = $ver ? $ver . '&amp;' . $this->args[$handle] : $this->args[$handle];

		if ( $this->do_concat ) {
			if ( $this->in_default_dir($obj->src) && !isset($obj->extra['conditional']) && !isset($obj->extra['alt']) ) {
				$this->concat .= "$handle,";
				$this->concat_version .= "$handle$ver";

				$this->print_code .= $this->print_inline_style( $handle, false );

				return true;
			}
		}

		if ( isset($obj->args) )
			$media = esc_attr( $obj->args );
		else
			$media = 'all';

		// A single item may alias a set of items, by having dependencies, but no source.
		if ( ! $obj->src ) {
			if ( $inline_style = $this->print_inline_style( $handle, false ) ) {
				$inline_style = sprintf( "<style id='%s-inline-css' type='text/css'>\n%s\n</style>\n", esc_attr( $handle ), $inline_style );
				if ( $this->do_concat ) {
					$this->print_html .= $inline_style;
				} else {
					echo $inline_style;
				}
			}
			return true;
		}

		$href = $this->_css_href( $obj->src, $ver, $handle );
		if ( ! $href ) {
			return true;
		}

		$rel = isset($obj->extra['alt']) && $obj->extra['alt'] ? 'alternate stylesheet' : 'stylesheet';
		$title = isset($obj->extra['title']) ? "title='" . esc_attr( $obj->extra['title'] ) . "'" : '';

		$tag = apply_filters( 'style_loader_tag', "<link rel='$rel' id='$handle-css' $title href='$href' type='text/css' media='$media' />\n", $handle, $href, $media);

		$conditional_pre = $conditional_post = '';
		if ( isset( $obj->extra['conditional'] ) && $obj->extra['conditional'] ) {
			$conditional_pre  = "<!--[if {$obj->extra['conditional']}]>\n";
			$conditional_post = "<![endif]-->\n";
		}

		if ( $this->do_concat ) {
			$this->print_html .= $conditional_pre;
			$this->print_html .= $tag;
			if ( $inline_style = $this->print_inline_style( $handle, false ) ) {
				$this->print_html .= sprintf( "<style id='%s-inline-css' type='text/css'>\n%s\n</style>\n", esc_attr( $handle ), $inline_style );
			}
			$this->print_html .= $conditional_post;
		} else {
			echo $conditional_pre;
			echo $tag;
			$this->print_inline_style( $handle );
			echo $conditional_post;
		}

		return true;
	}

	public function add_inline_style( $handle, $code ) {
		if ( ! $code ) {
			return false;
		}

		$after = $this->get_data( $handle, 'after' );
		if ( ! $after ) {
			$after = array();
		}

		$after[] = $code;

		return $this->add_data( $handle, 'after', $after );
	}

	public function print_inline_style( $handle, $echo = true ) {
		$output = $this->get_data( $handle, 'after' );

		if ( empty( $output ) ) {
			return false;
		}

		$output = implode( "\n", $output );

		if ( ! $echo ) {
			return $output;
		}

		printf( "<style id='%s-inline-css' type='text/css'>\n%s\n</style>\n", esc_attr( $handle ), $output );

		return true;
	}

	public function all_deps( $handles, $recursion = false, $group = false ) {
		$r = parent::all_deps( $handles, $recursion, $group );
		if ( ! $recursion ) {
			$this->to_do = apply_filters( 'print_styles_array', $this->to_do );
		}
		return $r;
	}

	public function _css_href( $src, $ver, $handle ) {
		if ( !is_bool($src) && !preg_match('|^(https?:)?//|', $src) && ! ( $this->content_url && 0 === strpos($src, $this->content_url) ) ) {
			$src = $this->base_url . $src;
		}

		if ( !empty($ver) )
			$src = add_query_arg('ver', $ver, $src);

		$src = apply_filters( 'style_loader_src', $src, $handle );
		return esc_url( $src );
	}

	public function in_default_dir( $src ) {
		if ( ! $this->default_dirs )
			return true;

		foreach ( (array) $this->default_dirs as $test ) {
			if ( 0 === strpos($src, $test) )
				return true;
		}
		return false;
	}

	public function do_footer_items() {
		$this->do_items(false, 1);
		return $this->done;
	}

	public function reset() {
		$this->do_concat = false;
		$this->concat = '';
		$this->concat_version = '';
		$this->print_html = '';
	}
}
