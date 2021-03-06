<?php
/**
 * Dependencies API: WP_Scripts class
 *
 * @since 2.6.0
 *
 * @package WordPress
 * @subpackage Dependencies
 */

class WP_Scripts extends WP_Dependencies {

	public $base_url;

	public $content_url;

	public $default_version;

	public $in_footer = array();

	public $concat = '';

	public $concat_version = '';

	public $do_concat = false;

	public $print_html = '';

	public $print_code = '';

	public $ext_handles = '';

	public $ext_version = '';

	public $default_dirs;

	public function __construct() {
		$this->init();
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	public function init() {
		do_action_ref_array( 'wp_default_scripts', array(&$this) );
	}

	public function print_scripts( $handles = false, $group = false ) {
		return $this->do_items( $handles, $group );
	}

	public function print_scripts_l10n( $handle, $echo = true ) {
		_deprecated_function( __FUNCTION__, '3.3', 'print_extra_script()' );
		return $this->print_extra_script( $handle, $echo );
	}

	public function print_extra_script( $handle, $echo = true ) {
		if ( !$output = $this->get_data( $handle, 'data' ) )
			return;

		if ( !$echo )
			return $output;

		echo "<script type='text/javascript'>\n"; // CDATA and type='text/javascript' is not needed for HTML 5
		echo "/* <![CDATA[ */\n";
		echo "$output\n";
		echo "/* ]]> */\n";
		echo "</script>\n";

		return true;
	}

	public function do_item( $handle, $group = false ) {
		if ( !parent::do_item($handle) )
			return false;

		if ( 0 === $group && $this->groups[$handle] > 0 ) {
			$this->in_footer[] = $handle;
			return false;
		}

		if ( false === $group && in_array($handle, $this->in_footer, true) )
			$this->in_footer = array_diff( $this->in_footer, (array) $handle );

		$obj = $this->registered[$handle];

		if ( null === $obj->ver ) {
			$ver = '';
		} else {
			$ver = $obj->ver ? $obj->ver : $this->default_version;
		}

		if ( isset($this->args[$handle]) )
			$ver = $ver ? $ver . '&amp;' . $this->args[$handle] : $this->args[$handle];

		$src = $obj->src;
		$cond_before = $cond_after = '';
		$conditional = isset( $obj->extra['conditional'] ) ? $obj->extra['conditional'] : '';

		if ( $conditional ) {
			$cond_before = "<!--[if {$conditional}]>\n";
			$cond_after = "<![endif]-->\n";
		}

		$before_handle = $this->print_inline_script( $handle, 'before', false );
		$after_handle = $this->print_inline_script( $handle, 'after', false );

		if ( $before_handle ) {
			$before_handle = sprintf( "<script type='text/javascript'>\n%s\n</script>\n", $before_handle );
		}

		if ( $after_handle ) {
			$after_handle = sprintf( "<script type='text/javascript'>\n%s\n</script>\n", $after_handle );
		}

		if ( $this->do_concat ) {
			$srce = apply_filters( 'script_loader_src', $src, $handle );

			if ( $this->in_default_dir( $srce ) && ( $before_handle || $after_handle ) ) {
				$this->do_concat = false;

				// Have to print the so-far concatenated scripts right away to maintain the right order.
				_print_scripts();
				$this->reset();
			} elseif ( $this->in_default_dir( $srce ) && ! $conditional ) {
				$this->print_code .= $this->print_extra_script( $handle, false );
				$this->concat .= "$handle,";
				$this->concat_version .= "$handle$ver";
				return true;
			} else {
				$this->ext_handles .= "$handle,";
				$this->ext_version .= "$handle$ver";
			}
		}

		$has_conditional_data = $conditional && $this->get_data( $handle, 'data' );

		if ( $has_conditional_data ) {
			echo $cond_before;
		}

		$this->print_extra_script( $handle );

		if ( $has_conditional_data ) {
			echo $cond_after;
		}

		// A single item may alias a set of items, by having dependencies, but no source.
		if ( ! $obj->src ) {
			return true;
		}

		if ( ! preg_match( '|^(https?:)?//|', $src ) && ! ( $this->content_url && 0 === strpos( $src, $this->content_url ) ) ) {
			$src = $this->base_url . $src;
		}

		if ( ! empty( $ver ) )
			$src = add_query_arg( 'ver', $ver, $src );

		/** This filter is documented in wp-includes/class.wp-scripts.php */
		$src = esc_url( apply_filters( 'script_loader_src', $src, $handle ) );

		if ( ! $src )
			return true;

		$tag = "{$cond_before}{$before_handle}<script type='text/javascript' src='$src'></script>\n{$after_handle}{$cond_after}";

		$tag = apply_filters( 'script_loader_tag', $tag, $handle, $src );

		if ( $this->do_concat ) {
			$this->print_html .= $tag;
		} else {
			echo $tag;
		}

		return true;
	}

	public function add_inline_script( $handle, $data, $position = 'after' ) {
		if ( ! $data ) {
			return false;
		}

		if ( 'after' !== $position ) {
			$position = 'before';
		}

		$script   = (array) $this->get_data( $handle, $position );
		$script[] = $data;

		return $this->add_data( $handle, $position, $script );
	}

	public function print_inline_script( $handle, $position = 'after', $echo = true ) {
		$output = $this->get_data( $handle, $position );

		if ( empty( $output ) ) {
			return false;
		}

		$output = trim( implode( "\n", $output ), "\n" );

		if ( $echo ) {
			printf( "<script type='text/javascript'>\n%s\n</script>\n", $output );
		}

		return $output;
	}

	public function localize( $handle, $object_name, $l10n ) {
		if ( $handle === 'jquery' )
			$handle = 'jquery-core';

		if ( is_array($l10n) && isset($l10n['l10n_print_after']) ) { // back compat, preserve the code in 'l10n_print_after' if present
			$after = $l10n['l10n_print_after'];
			unset($l10n['l10n_print_after']);
		}

		foreach ( (array) $l10n as $key => $value ) {
			if ( !is_scalar($value) )
				continue;

			$l10n[$key] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8');
		}

		$script = "var $object_name = " . wp_json_encode( $l10n ) . ';';

		if ( !empty($after) )
			$script .= "\n$after;";

		$data = $this->get_data( $handle, 'data' );

		if ( !empty( $data ) )
			$script = "$data\n$script";

		return $this->add_data( $handle, 'data', $script );
	}

	public function set_group( $handle, $recursion, $group = false ) {
		if ( isset( $this->registered[$handle]->args ) && $this->registered[$handle]->args === 1 )
			$grp = 1;
		else
			$grp = (int) $this->get_data( $handle, 'group' );

		if ( false !== $group && $grp > $group )
			$grp = $group;

		return parent::set_group( $handle, $recursion, $grp );
	}

	public function all_deps( $handles, $recursion = false, $group = false ) {
		$r = parent::all_deps( $handles, $recursion, $group );
		if ( ! $recursion ) {
			$this->to_do = apply_filters( 'print_scripts_array', $this->to_do );
		}
		return $r;
	}

	public function do_head_items() {
		$this->do_items(false, 0);
		return $this->done;
	}

	public function do_footer_items() {
		$this->do_items(false, 1);
		return $this->done;
	}

	public function in_default_dir( $src ) {
		if ( ! $this->default_dirs ) {
			return true;
		}

		if ( 0 === strpos( $src, '/' . WPINC . '/js/l10n' ) ) {
			return false;
		}

		foreach ( (array) $this->default_dirs as $test ) {
			if ( 0 === strpos( $src, $test ) ) {
				return true;
			}
		}
		return false;
	}

	public function reset() {
		$this->do_concat = false;
		$this->print_code = '';
		$this->concat = '';
		$this->concat_version = '';
		$this->print_html = '';
		$this->ext_version = '';
		$this->ext_handles = '';
	}
}
