<?php

class WP_Embed {
	public $handlers = array();
	public $post_ID;
	public $usecache = true;
	public $linkifunknown = true;
	public $last_attr = array();
	public $last_url = '';

	public $return_false_on_fail = false;

	public function __construct() {
		add_filter( 'the_content', array( $this, 'run_shortcode' ), 8 );

		add_shortcode( 'embed', '__return_false' );

		add_filter( 'the_content', array( $this, 'autoembed' ), 8 );

		add_action( 'edit_form_advanced', array( $this, 'maybe_run_ajax_cache' ) );
		add_action( 'edit_page_form', array( $this, 'maybe_run_ajax_cache' ) );
	}

	public function run_shortcode( $content ) {
		global $shortcode_tags;

		// Back up current registered shortcodes and clear them all out
		$orig_shortcode_tags = $shortcode_tags;
		remove_all_shortcodes();

		add_shortcode( 'embed', array( $this, 'shortcode' ) );

		// Do the shortcode (only the [embed] one is registered)
		$content = do_shortcode( $content, true );

		// Put the original shortcodes back
		$shortcode_tags = $orig_shortcode_tags;

		return $content;
	}

	public function maybe_run_ajax_cache() {
		$post = get_post();

		if ( ! $post || empty( $_GET['message'] ) )
			return;

?>
<script type="text/javascript">
	jQuery(document).ready(function($){
		$.get("<?php echo admin_url( 'admin-ajax.php?action=oembed-cache&post=' . $post->ID, 'relative' ); ?>");
	});
</script>
<?php
	}

	public function register_handler( $id, $regex, $callback, $priority = 10 ) {
		$this->handlers[$priority][$id] = array(
			'regex'    => $regex,
			'callback' => $callback,
		);
	}

	public function unregister_handler( $id, $priority = 10 ) {
		unset( $this->handlers[ $priority ][ $id ] );
	}

	public function shortcode( $attr, $url = '' ) {
		$post = get_post();

		if ( empty( $url ) && ! empty( $attr['src'] ) ) {
			$url = $attr['src'];
		}

		$this->last_url = $url;

		if ( empty( $url ) ) {
			$this->last_attr = $attr;
			return '';
		}

		$rawattr = $attr;
		$attr = wp_parse_args( $attr, wp_embed_defaults( $url ) );

		$this->last_attr = $attr;

		$url = str_replace( '&amp;', '&', $url );

		// Look for known internal handlers
		ksort( $this->handlers );
		foreach ( $this->handlers as $priority => $handlers ) {
			foreach ( $handlers as $id => $handler ) {
				if ( preg_match( $handler['regex'], $url, $matches ) && is_callable( $handler['callback'] ) ) {
					if ( false !== $return = call_user_func( $handler['callback'], $matches, $attr, $url, $rawattr ) )

						return apply_filters( 'embed_handler_html', $return, $url, $attr );
				}
			}
		}

		$post_ID = ( ! empty( $post->ID ) ) ? $post->ID : null;
		if ( ! empty( $this->post_ID ) )
			$post_ID = $this->post_ID;

		if ( $post_ID ) {

			// Check for a cached result (stored in the post meta)
			$key_suffix = md5( $url . serialize( $attr ) );
			$cachekey = '_oembed_' . $key_suffix;
			$cachekey_time = '_oembed_time_' . $key_suffix;

			$ttl = apply_filters( 'oembed_ttl', DAY_IN_SECONDS, $url, $attr, $post_ID );

			$cache = get_post_meta( $post_ID, $cachekey, true );
			$cache_time = get_post_meta( $post_ID, $cachekey_time, true );

			if ( ! $cache_time ) {
				$cache_time = 0;
			}

			$cached_recently = ( time() - $cache_time ) < $ttl;

			if ( $this->usecache || $cached_recently ) {
				// Failures are cached. Serve one if we're using the cache.
				if ( '{{unknown}}' === $cache )
					return $this->maybe_make_link( $url );

				if ( ! empty( $cache ) ) {
					return apply_filters( 'embed_oembed_html', $cache, $url, $attr, $post_ID );
				}
			}

			$attr['discover'] = ( apply_filters( 'embed_oembed_discover', true ) );

			$html = wp_oembed_get( $url, $attr );

			if ( $html ) {
				update_post_meta( $post_ID, $cachekey, $html );
				update_post_meta( $post_ID, $cachekey_time, time() );
			} elseif ( ! $cache ) {
				update_post_meta( $post_ID, $cachekey, '{{unknown}}' );
			}

			if ( $html ) {
				return apply_filters( 'embed_oembed_html', $html, $url, $attr, $post_ID );
			}
		}

		return $this->maybe_make_link( $url );
	}

	public function delete_oembed_caches( $post_ID ) {
		$post_metas = get_post_custom_keys( $post_ID );
		if ( empty($post_metas) )
			return;

		foreach ( $post_metas as $post_meta_key ) {
			if ( '_oembed_' == substr( $post_meta_key, 0, 8 ) )
				delete_post_meta( $post_ID, $post_meta_key );
		}
	}

	public function cache_oembed( $post_ID ) {
		$post = get_post( $post_ID );

		$post_types = get_post_types( array( 'show_ui' => true ) );

		if ( empty( $post->ID ) || ! in_array( $post->post_type, apply_filters( 'embed_cache_oembed_types', $post_types ) ) ){
			return;
		}

		// Trigger a caching
		if ( ! empty( $post->post_content ) ) {
			$this->post_ID = $post->ID;
			$this->usecache = false;

			$content = $this->run_shortcode( $post->post_content );
			$this->autoembed( $content );

			$this->usecache = true;
		}
	}

	public function autoembed( $content ) {

		$content = wp_replace_in_html_tags( $content, array( "\n" => '<!-- wp-line-break -->' ) );

		$content = preg_replace_callback( '|^(\s*)(https?://[^\s"]+)(\s*)$|im', array( $this, 'autoembed_callback' ), $content );

		return str_replace( '<!-- wp-line-break -->', "\n", $content );
	}

	public function autoembed_callback( $match ) {
		$oldval = $this->linkifunknown;
		$this->linkifunknown = false;
		$return = $this->shortcode( array(), $match[2] );
		$this->linkifunknown = $oldval;

		return $match[1] . $return . $match[3];
	}

	public function maybe_make_link( $url ) {
		if ( $this->return_false_on_fail ) {
			return false;
		}

		$output = ( $this->linkifunknown ) ? '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>' : $url;

		return apply_filters( 'embed_maybe_make_link', $output, $url );
	}
}
$GLOBALS['wp_embed'] = new WP_Embed();
