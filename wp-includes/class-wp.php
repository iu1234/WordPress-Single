<?php

class WP {

	public $public_query_vars = array('m', 'p', 'posts', 'w', 'cat', 'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence', 'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order', 'orderby', 'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'name', 'category_name', 'tag', 'author_name', 'static', 'pagename', 'page_id', 'error', 'attachment', 'attachment_id', 'subpost', 'subpost_id', 'preview', 'robots', 'taxonomy', 'term', 'cpage', 'post_type', 'embed' );

	public $private_query_vars = array( 'offset', 'posts_per_page', 'posts_per_archive_page', 'showposts', 'nopaging', 'post_type', 'post_status', 'category__in', 'category__not_in', 'category__and', 'tag__in', 'tag__not_in', 'tag__and', 'tag_slug__in', 'tag_slug__and', 'tag_id', 'post_mime_type', 'perm', 'comments_per_page', 'post__in', 'post__not_in', 'post_parent', 'post_parent__in', 'post_parent__not_in', 'title' );

	public $extra_query_vars = array();

	public $query_vars;

	public $query_string;

	public $request;

	public $matched_rule;

	public $matched_query;

	public $did_permalink = false;

	public function add_query_var($qv) {
		if ( !in_array($qv, $this->public_query_vars) )
			$this->public_query_vars[] = $qv;
	}

	public function remove_query_var( $name ) {
		$this->public_query_vars = array_diff( $this->public_query_vars, array( $name ) );
	}

	public function set_query_var($key, $value) {
		$this->query_vars[$key] = $value;
	}

	public function parse_request($extra_query_vars = '') {
		global $wp_rewrite;
		if ( ! apply_filters( 'do_parse_request', true, $this, $extra_query_vars ) )
			return;
		$this->query_vars = array();
		$post_type_query_vars = array();

		if ( is_array( $extra_query_vars ) ) {
			$this->extra_query_vars = & $extra_query_vars;
		} elseif ( ! empty( $extra_query_vars ) ) {
			parse_str( $extra_query_vars, $this->extra_query_vars );
		}

		$rewrite = $wp_rewrite->wp_rewrite_rules();

		if ( ! empty($rewrite) ) {
			$error = '404';
			$this->did_permalink = true;

			$pathinfo = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '';
			list( $pathinfo ) = explode( '?', $pathinfo );
			$pathinfo = str_replace( "%", "%25", $pathinfo );

			list( $req_uri ) = explode( '?', $_SERVER['REQUEST_URI'] );
			$self = $_SERVER['PHP_SELF'];
			$home_path = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );
			$home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );

			$req_uri = str_replace($pathinfo, '', $req_uri);
			$req_uri = trim($req_uri, '/');
			$req_uri = preg_replace( $home_path_regex, '', $req_uri );
			$req_uri = trim($req_uri, '/');
			$pathinfo = trim($pathinfo, '/');
			$pathinfo = preg_replace( $home_path_regex, '', $pathinfo );
			$pathinfo = trim($pathinfo, '/');
			$self = trim($self, '/');
			$self = preg_replace( $home_path_regex, '', $self );
			$self = trim($self, '/');

			if ( ! empty($pathinfo) && !preg_match('|^.*' . $wp_rewrite->index . '$|', $pathinfo) ) {
				$request = $pathinfo;
			} else {
				if ( $req_uri == $wp_rewrite->index )
					$req_uri = '';
				$request = $req_uri;
			}
			$this->request = $request;
			$request_match = $request;
			if ( empty( $request_match ) ) {
				if ( isset( $rewrite['$'] ) ) {
					$this->matched_rule = '$';
					$query = $rewrite['$'];
					$matches = array('');
				}
			} else {
				foreach ( (array) $rewrite as $match => $query ) {
					if ( ! empty($req_uri) && strpos($match, $req_uri) === 0 && $req_uri != $request )
						$request_match = $req_uri . '/' . $request;
					if ( preg_match("#^$match#", $request_match, $matches) ||
						preg_match("#^$match#", urldecode($request_match), $matches) ) {

						if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
							$page = get_page_by_path( $matches[ $varmatch[1] ] );
							if ( ! $page ) {
						 		continue;
							}

							$post_status_obj = get_post_status_object( $page->post_status );
							if ( ! $post_status_obj->public && ! $post_status_obj->protected
								&& ! $post_status_obj->private && $post_status_obj->exclude_from_search ) {
								continue;
							}
						}

						$this->matched_rule = $match;
						break;
					}
				}
			}

			if ( isset( $this->matched_rule ) ) {
				$query = preg_replace("!^.+\?!", '', $query);

				$query = addslashes(WP_MatchesMapRegex::apply($query, $matches));

				$this->matched_query = $query;

				parse_str($query, $perma_query_vars);

				if ( '404' == $error )
					unset( $error, $_GET['error'] );
			}

			if ( empty($request) || $req_uri == $self || strpos($_SERVER['PHP_SELF'], 'wp-admin/') !== false ) {
				unset( $error, $_GET['error'] );

				if ( isset($perma_query_vars) && strpos($_SERVER['PHP_SELF'], 'wp-admin/') !== false )
					unset( $perma_query_vars );

				$this->did_permalink = false;
			}
		}

		$this->public_query_vars = apply_filters( 'query_vars', $this->public_query_vars );

		foreach ( get_post_types( array(), 'objects' ) as $post_type => $t ) {
			if ( is_post_type_viewable( $t ) && $t->query_var ) {
				$post_type_query_vars[$t->query_var] = $post_type;
			}
		}

		foreach ( $this->public_query_vars as $wpvar ) {
			if ( isset( $this->extra_query_vars[$wpvar] ) )
				$this->query_vars[$wpvar] = $this->extra_query_vars[$wpvar];
			elseif ( isset( $_POST[$wpvar] ) )
				$this->query_vars[$wpvar] = $_POST[$wpvar];
			elseif ( isset( $_GET[$wpvar] ) )
				$this->query_vars[$wpvar] = $_GET[$wpvar];
			elseif ( isset( $perma_query_vars[$wpvar] ) )
				$this->query_vars[$wpvar] = $perma_query_vars[$wpvar];

			if ( !empty( $this->query_vars[$wpvar] ) ) {
				if ( ! is_array( $this->query_vars[$wpvar] ) ) {
					$this->query_vars[$wpvar] = (string) $this->query_vars[$wpvar];
				} else {
					foreach ( $this->query_vars[$wpvar] as $vkey => $v ) {
						if ( !is_object( $v ) ) {
							$this->query_vars[$wpvar][$vkey] = (string) $v;
						}
					}
				}

				if ( isset($post_type_query_vars[$wpvar] ) ) {
					$this->query_vars['post_type'] = $post_type_query_vars[$wpvar];
					$this->query_vars['name'] = $this->query_vars[$wpvar];
				}
			}
		}

		foreach ( get_taxonomies( array() , 'objects' ) as $taxonomy => $t )
			if ( $t->query_var && isset( $this->query_vars[$t->query_var] ) )
				$this->query_vars[$t->query_var] = str_replace( ' ', '+', $this->query_vars[$t->query_var] );

		if ( ! is_admin() ) {
			foreach ( get_taxonomies( array( 'publicly_queryable' => false ), 'objects' ) as $taxonomy => $t ) {
				if ( isset( $this->query_vars['taxonomy'] ) && $taxonomy === $this->query_vars['taxonomy'] ) {
					unset( $this->query_vars['taxonomy'], $this->query_vars['term'] );
				}
			}
		}

		if ( isset( $this->query_vars['post_type']) ) {
			$queryable_post_types = get_post_types( array('publicly_queryable' => true) );
			if ( ! is_array( $this->query_vars['post_type'] ) ) {
				if ( ! in_array( $this->query_vars['post_type'], $queryable_post_types ) )
					unset( $this->query_vars['post_type'] );
			} else {
				$this->query_vars['post_type'] = array_intersect( $this->query_vars['post_type'], $queryable_post_types );
			}
		}

		$this->query_vars = wp_resolve_numeric_slug_conflicts( $this->query_vars );

		foreach ( (array) $this->private_query_vars as $var) {
			if ( isset($this->extra_query_vars[$var]) )
				$this->query_vars[$var] = $this->extra_query_vars[$var];
		}

		if ( isset($error) )
			$this->query_vars['error'] = $error;

		$this->query_vars = apply_filters( 'request', $this->query_vars );

		do_action_ref_array( 'parse_request', array( &$this ) );
	}

	public function send_headers() {
		$headers = array();
		$status = null;
		$exit_required = false;
		if ( is_user_logged_in() )
			$headers = array_merge($headers, wp_get_nocache_headers());
		if ( ! empty( $this->query_vars['error'] ) ) {
			$status = (int) $this->query_vars['error'];
			if ( 404 === $status ) {
				if ( ! is_user_logged_in() )
					$headers = array_merge($headers, wp_get_nocache_headers());
				$headers['Content-Type'] = get_option('html_type') . '; charset=' . get_option('blog_charset');
			} elseif ( in_array( $status, array( 403, 500, 502, 503 ) ) ) {
				$exit_required = true;
			}
		} elseif ( empty( $this->query_vars['feed'] ) ) {
			$headers['Content-Type'] = get_option('html_type') . '; charset=' . get_option('blog_charset');
		} else {
			$type = $this->query_vars['feed'];
			$headers['Content-Type'] = feed_content_type( $type ) . '; charset=' . get_option( 'blog_charset' );
			if ( !empty($this->query_vars['withcomments'])
				|| false !== strpos( $this->query_vars['feed'], 'comments-' )
				|| ( empty($this->query_vars['withoutcomments'])
					&& ( !empty($this->query_vars['p'])
						|| !empty($this->query_vars['name'])
						|| !empty($this->query_vars['page_id'])
						|| !empty($this->query_vars['pagename'])
						|| !empty($this->query_vars['attachment'])
						|| !empty($this->query_vars['attachment_id'])
					)
				)
			)
				$wp_last_modified = mysql2date('D, d M Y H:i:s', get_lastcommentmodified('GMT'), 0).' GMT';
			else
				$wp_last_modified = mysql2date('D, d M Y H:i:s', get_lastpostmodified('GMT'), 0).' GMT';
			$wp_etag = '"' . md5($wp_last_modified) . '"';
			$headers['Last-Modified'] = $wp_last_modified;
			$headers['ETag'] = $wp_etag;
			if (isset($_SERVER['HTTP_IF_NONE_MATCH']))
				$client_etag = wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] );
			else $client_etag = false;

			$client_last_modified = empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? '' : trim($_SERVER['HTTP_IF_MODIFIED_SINCE']);
			$client_modified_timestamp = $client_last_modified ? strtotime($client_last_modified) : 0;
			$wp_modified_timestamp = strtotime($wp_last_modified);
			if ( ($client_last_modified && $client_etag) ?
					 (($client_modified_timestamp >= $wp_modified_timestamp) && ($client_etag == $wp_etag)) :
					 (($client_modified_timestamp >= $wp_modified_timestamp) || ($client_etag == $wp_etag)) ) {
				$status = 304;
				$exit_required = true;
			}
		}
		$headers = apply_filters( 'wp_headers', $headers, $this );

		if ( ! empty( $status ) )
			status_header( $status );

		if ( isset( $headers['Last-Modified'] ) && false === $headers['Last-Modified'] ) {
			unset( $headers['Last-Modified'] );

		@header_remove( 'Last-Modified' );
		}

		foreach ( (array) $headers as $name => $field_value )
			@header("{$name}: {$field_value}");
		if ( $exit_required )
			exit();
		do_action_ref_array( 'send_headers', array( &$this ) );
	}

	public function build_query_string() {
		$this->query_string = '';
		foreach ( (array) array_keys($this->query_vars) as $wpvar) {
			if ( '' != $this->query_vars[$wpvar] ) {
				$this->query_string .= (strlen($this->query_string) < 1) ? '' : '&';
				if ( !is_scalar($this->query_vars[$wpvar]) )
					continue;
				$this->query_string .= $wpvar . '=' . rawurlencode($this->query_vars[$wpvar]);
			}
		}
		if ( has_filter( 'query_string' ) ) {
			$this->query_string = apply_filters( 'query_string', $this->query_string );
			parse_str($this->query_string, $this->query_vars);
		}
	}

	public function register_globals() {
		global $wp_query;
		foreach ( (array) $wp_query->query_vars as $key => $value ) {
			$GLOBALS[ $key ] = $value;
		}
		$GLOBALS['query_string'] = $this->query_string;
		$GLOBALS['posts'] = & $wp_query->posts;
		$GLOBALS['post'] = isset( $wp_query->post ) ? $wp_query->post : null;
		$GLOBALS['request'] = $wp_query->request;
		if ( $wp_query->is_single() || $wp_query->is_page() ) {
			$GLOBALS['more']   = 1;
			$GLOBALS['single'] = 1;
		}
		if ( $wp_query->is_author() && isset( $wp_query->post ) )
			$GLOBALS['authordata'] = get_user_by( 'id', $wp_query->post->post_author );
	}

	public function init() {
		_wp_get_current_user();
	}

	public function query_posts() {
		global $wp_the_query;
		$this->build_query_string();
		$wp_the_query->query($this->query_vars);
 	}

	public function handle_404() {
		global $wp_query;
		if ( false !== apply_filters( 'pre_handle_404', false, $wp_query ) ) {
			return;
		}
		if ( $wp_query->is_404() )
			return;
		if ( is_admin() || is_robots() || $wp_query->posts ) {
			$success = true;
			if ( is_singular() ) {
				$p = false;
				if ( $wp_query->post instanceof WP_Post ) {
					$p = clone $wp_query->post;
				}
				$next = '<!--nextpage-->';
				if ( $p && false !== strpos( $p->post_content, $next ) && ! empty( $this->query_vars['page'] ) ) {
					$page = trim( $this->query_vars['page'], '/' );
					$success = (int) $page <= ( substr_count( $p->post_content, $next ) + 1 );
				}
			}

			if ( $success ) {
				status_header( 200 );
				return;
			}
		}
		if ( ! is_paged() ) {
			$author = get_query_var( 'author' );
			if ( is_author() && is_numeric( $author ) && $author > 0 && is_user_member_of_blog( $author ) ) {
				status_header( 200 );
				return;
			}
			if ( ( is_tag() || is_category() || is_tax() || is_post_type_archive() ) && get_queried_object() ) {
				status_header( 200 );
				return;
			}
			if ( is_home() || is_search() || is_feed() ) {
				status_header( 200 );
				return;
			}
		}

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	public function main($query_args = '') {
		$this->init();
		$this->parse_request($query_args);
		$this->send_headers();
		$this->query_posts();
		$this->handle_404();
		$this->register_globals();
		do_action_ref_array( 'wp', array( &$this ) );
	}
}

class WP_MatchesMapRegex {

	private $_matches;

	public $output;

	private $_subject;

	public $_pattern = '(\$matches\[[1-9]+[0-9]*\])';

	public function __construct($subject, $matches) {
		$this->_subject = $subject;
		$this->_matches = $matches;
		$this->output = $this->_map();
	}

	public static function apply($subject, $matches) {
		$oSelf = new WP_MatchesMapRegex($subject, $matches);
		return $oSelf->output;
	}

	private function _map() {
		$callback = array($this, 'callback');
		return preg_replace_callback($this->_pattern, $callback, $this->_subject);
	}

	public function callback($matches) {
		$index = intval(substr($matches[0], 9, -1));
		return ( isset( $this->_matches[$index] ) ? urlencode($this->_matches[$index]) : '' );
	}
}
