<?php
/**
 * Comment template functions
 *
 * These functions are meant to live inside of the WordPress loop.
 *
 * @package WordPress
 * @subpackage Template
 */

function get_comment_author( $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );

	if ( empty( $comment->comment_author ) ) {
		if ( $comment->user_id && $user = get_userdata( $comment->user_id ) )
			$author = $user->display_name;
		else
			$author = 'Anonymous';
	} else {
		$author = $comment->comment_author;
	}

	return apply_filters( 'get_comment_author', $author, $comment->comment_ID, $comment );
}

function comment_author( $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );
	$author  = get_comment_author( $comment );

	echo apply_filters( 'comment_author', $author, $comment->comment_ID );
}

function get_comment_author_email( $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );

	return apply_filters( 'get_comment_author_email', $comment->comment_author_email, $comment->comment_ID, $comment );
}

function comment_author_email( $comment_ID = 0 ) {
	$comment      = get_comment( $comment_ID );
	$author_email = get_comment_author_email( $comment );

	echo apply_filters( 'author_email', $author_email, $comment->comment_ID );
}

function comment_author_email_link( $linktext = '', $before = '', $after = '' ) {
	if ( $link = get_comment_author_email_link( $linktext, $before, $after ) )
		echo $link;
}

function get_comment_author_email_link( $linktext = '', $before = '', $after = '' ) {
	$comment = get_comment();

	$email = apply_filters( 'comment_email', $comment->comment_author_email, $comment );
	if ((!empty($email)) && ($email != '@')) {
	$display = ($linktext != '') ? $linktext : $email;
		$return  = $before;
		$return .= sprintf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $email ), esc_html( $display ) );
	 	$return .= $after;
		return $return;
	} else {
		return '';
	}
}

function get_comment_author_link( $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );
	$url     = get_comment_author_url( $comment );
	$author  = get_comment_author( $comment );

	if ( empty( $url ) || 'http://' == $url )
		$return = $author;
	else
		$return = "<a href='$url' rel='external nofollow' class='url'>$author</a>";

	return apply_filters( 'get_comment_author_link', $return, $author, $comment->comment_ID );
}

function comment_author_link( $comment_ID = 0 ) {
	echo get_comment_author_link( $comment_ID );
}

function get_comment_author_IP( $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );

	return apply_filters( 'get_comment_author_IP', $comment->comment_author_IP, $comment->comment_ID, $comment );
}

function comment_author_IP( $comment_ID = 0 ) {
	echo esc_html( get_comment_author_IP( $comment_ID ) );
}

function get_comment_author_url( $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );
	$url = ('http://' == $comment->comment_author_url) ? '' : $comment->comment_author_url;
	$url = esc_url( $url, array('http', 'https') );

	return apply_filters( 'get_comment_author_url', $url, $comment->comment_ID, $comment );
}

function comment_author_url( $comment_ID = 0 ) {
	$comment    = get_comment( $comment_ID );
	$author_url = get_comment_author_url( $comment );

	echo apply_filters( 'comment_url', $author_url, $comment->comment_ID );
}

function get_comment_author_url_link( $linktext = '', $before = '', $after = '' ) {
	$url = get_comment_author_url();
	$display = ($linktext != '') ? $linktext : $url;
	$display = str_replace( 'http://www.', '', $display );
	$display = str_replace( 'http://', '', $display );

	if ( '/' == substr($display, -1) ) {
		$display = substr($display, 0, -1);
	}

	$return = "$before<a href='$url' rel='external'>$display</a>$after";

	return apply_filters( 'get_comment_author_url_link', $return );
}

function comment_author_url_link( $linktext = '', $before = '', $after = '' ) {
	echo get_comment_author_url_link( $linktext, $before, $after );
}

function comment_class( $class = '', $comment = null, $post_id = null, $echo = true ) {
	// Separates classes with a single space, collates classes for comment DIV
	$class = 'class="' . join( ' ', get_comment_class( $class, $comment, $post_id ) ) . '"';
	if ( $echo)
		echo $class;
	else
		return $class;
}

function get_comment_class( $class = '', $comment_id = null, $post_id = null ) {
	global $comment_alt, $comment_depth, $comment_thread_alt;

	$classes = array();

	$comment = get_comment( $comment_id );
	if ( ! $comment ) {
		return $classes;
	}

	// Get the comment type (comment, trackback),
	$classes[] = ( empty( $comment->comment_type ) ) ? 'comment' : $comment->comment_type;

	// Add classes for comment authors that are registered users.
	if ( $comment->user_id > 0 && $user = get_userdata( $comment->user_id ) ) {
		$classes[] = 'byuser';
		$classes[] = 'comment-author-' . sanitize_html_class( $user->user_nicename, $comment->user_id );
		// For comment authors who are the author of the post
		if ( $post = get_post($post_id) ) {
			if ( $comment->user_id === $post->post_author ) {
				$classes[] = 'bypostauthor';
			}
		}
	}

	if ( empty($comment_alt) )
		$comment_alt = 0;
	if ( empty($comment_depth) )
		$comment_depth = 1;
	if ( empty($comment_thread_alt) )
		$comment_thread_alt = 0;

	if ( $comment_alt % 2 ) {
		$classes[] = 'odd';
		$classes[] = 'alt';
	} else {
		$classes[] = 'even';
	}

	$comment_alt++;

	// Alt for top-level comments
	if ( 1 == $comment_depth ) {
		if ( $comment_thread_alt % 2 ) {
			$classes[] = 'thread-odd';
			$classes[] = 'thread-alt';
		} else {
			$classes[] = 'thread-even';
		}
		$comment_thread_alt++;
	}

	$classes[] = "depth-$comment_depth";

	if ( !empty($class) ) {
		if ( !is_array( $class ) )
			$class = preg_split('#\s+#', $class);
		$classes = array_merge($classes, $class);
	}

	$classes = array_map('esc_attr', $classes);

	return apply_filters( 'comment_class', $classes, $class, $comment->comment_ID, $comment, $post_id );
}

function get_comment_date( $d = '', $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );
	if ( '' == $d )
		$date = mysql2date(get_option('date_format'), $comment->comment_date);
	else
		$date = mysql2date($d, $comment->comment_date);

	return apply_filters( 'get_comment_date', $date, $d, $comment );
}

function comment_date( $d = '', $comment_ID = 0 ) {
	echo get_comment_date( $d, $comment_ID );
}

function get_comment_excerpt( $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );
	$comment_text = strip_tags( str_replace( array( "\n", "\r" ), ' ', $comment->comment_content ) );
	$words = explode( ' ', $comment_text );

	$comment_excerpt_length = apply_filters( 'comment_excerpt_length', 20 );

	$use_ellipsis = count( $words ) > $comment_excerpt_length;
	if ( $use_ellipsis ) {
		$words = array_slice( $words, 0, $comment_excerpt_length );
	}

	$excerpt = trim( join( ' ', $words ) );
	if ( $use_ellipsis ) {
		$excerpt .= '&hellip;';
	}

	return apply_filters( 'get_comment_excerpt', $excerpt, $comment->comment_ID, $comment );
}

function comment_excerpt( $comment_ID = 0 ) {
	$comment         = get_comment( $comment_ID );
	$comment_excerpt = get_comment_excerpt( $comment );

	echo apply_filters( 'comment_excerpt', $comment_excerpt, $comment->comment_ID );
}

function get_comment_ID() {
	$comment = get_comment();

	return apply_filters( 'get_comment_ID', $comment->comment_ID, $comment );
}

function get_comment_link( $comment = null, $args = array() ) {
	global $wp_rewrite, $in_comment_loop;

	$comment = get_comment($comment);

	if ( ! is_array( $args ) ) {
		$args = array( 'page' => $args );
	}

	$defaults = array(
		'type'      => 'all',
		'page'      => '',
		'per_page'  => '',
		'max_depth' => '',
		'cpage'     => null,
	);
	$args = wp_parse_args( $args, $defaults );

	$link = get_permalink( $comment->comment_post_ID );

	// The 'cpage' param takes precedence.
	if ( ! is_null( $args['cpage'] ) ) {
		$cpage = $args['cpage'];

	// No 'cpage' is provided, so we calculate one.
	} else {
		if ( '' === $args['per_page'] && get_option( 'page_comments' ) ) {
			$args['per_page'] = get_option('comments_per_page');
		}

		if ( empty( $args['per_page'] ) ) {
			$args['per_page'] = 0;
			$args['page'] = 0;
		}

		$cpage = $args['page'];

		if ( '' == $cpage ) {
			if ( ! empty( $in_comment_loop ) ) {
				$cpage = get_query_var( 'cpage' );
			} else {
				// Requires a database hit, so we only do it when we can't figure out from context.
				$cpage = get_page_of_comment( $comment->comment_ID, $args );
			}
		}

		/*
		 * If the default page displays the oldest comments, the permalinks for comments on the default page
		 * do not need a 'cpage' query var.
		 */
		if ( 'oldest' === get_option( 'default_comments_page' ) && 1 === $cpage ) {
			$cpage = '';
		}
	}

	if ( $cpage && get_option( 'page_comments' ) ) {
		if ( $wp_rewrite->using_permalinks() ) {
			if ( $cpage ) {
				$link = trailingslashit( $link ) . $wp_rewrite->comments_pagination_base . '-' . $cpage;
			}

			$link = user_trailingslashit( $link, 'comment' );
		} elseif ( $cpage ) {
			$link = add_query_arg( 'cpage', $cpage, $link );
		}

	}

	if ( $wp_rewrite->using_permalinks() ) {
		$link = user_trailingslashit( $link, 'comment' );
	}

	$link = $link . '#comment-' . $comment->comment_ID;

	return apply_filters( 'get_comment_link', $link, $comment, $args, $cpage );
}

function get_comments_link( $post_id = 0 ) {
	$hash = get_comments_number( $post_id ) ? '#comments' : '#respond';
	$comments_link = get_permalink( $post_id ) . $hash;

	return apply_filters( 'get_comments_link', $comments_link, $post_id );
}

function comments_link( $deprecated = '', $deprecated_2 = '' ) {
	if ( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '0.72' );
	if ( !empty( $deprecated_2 ) )
		_deprecated_argument( __FUNCTION__, '1.3' );
	echo esc_url( get_comments_link() );
}

function get_comments_number( $post_id = 0 ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		$count = 0;
	} else {
		$count = $post->comment_count;
		$post_id = $post->ID;
	}

	return apply_filters( 'get_comments_number', $count, $post_id );
}

function comments_number( $zero = false, $one = false, $more = false, $deprecated = '' ) {
	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '1.3' );
	}
	echo get_comments_number_text( $zero, $one, $more );
}

function get_comments_number_text( $zero = false, $one = false, $more = false ) {
	$number = get_comments_number();

	if ( $number > 1 ) {
		if ( false === $more ) {
			/* translators: %s: number of comments */
			$output = sprintf( _n( '%s Comment', '%s Comments', $number ), number_format_i18n( $number ) );
		} else {
			// % Comments
			$output = str_replace( '%', number_format_i18n( $number ), $more );
		}
	} elseif ( $number == 0 ) {
		$output = ( false === $zero ) ? __( 'No Comments' ) : $zero;
	} else { // must be one
		$output = ( false === $one ) ? __( '1 Comment' ) : $one;
	}

	return apply_filters( 'comments_number', $output, $number );
}

function get_comment_text( $comment_ID = 0, $args = array() ) {
	$comment = get_comment( $comment_ID );

	return apply_filters( 'get_comment_text', $comment->comment_content, $comment, $args );
}

function comment_text( $comment_ID = 0, $args = array() ) {
	$comment = get_comment( $comment_ID );

	$comment_text = get_comment_text( $comment, $args );

	echo apply_filters( 'comment_text', $comment_text, $comment, $args );
}

function get_comment_time( $d = '', $gmt = false, $translate = true ) {
	$comment = get_comment();

	$comment_date = $gmt ? $comment->comment_date_gmt : $comment->comment_date;
	if ( '' == $d )
		$date = mysql2date(get_option('time_format'), $comment_date, $translate);
	else
		$date = mysql2date($d, $comment_date, $translate);

	return apply_filters( 'get_comment_time', $date, $d, $gmt, $translate, $comment );
}

function comment_time( $d = '' ) {
	echo get_comment_time($d);
}

function get_comment_type( $comment_ID = 0 ) {
	$comment = get_comment( $comment_ID );
	if ( '' == $comment->comment_type )
		$comment->comment_type = 'comment';

	return apply_filters( 'get_comment_type', $comment->comment_type, $comment->comment_ID, $comment );
}

function comment_type( $commenttxt = false, $trackbacktxt = false, $pingbacktxt = false ) {
	if ( false === $commenttxt ) $commenttxt = _x( 'Comment', 'noun' );
	if ( false === $trackbacktxt ) $trackbacktxt = __( 'Trackback' );
	if ( false === $pingbacktxt ) $pingbacktxt = __( 'Pingback' );
	$type = get_comment_type();
	switch( $type ) {
		case 'trackback' :
			echo $trackbacktxt;
			break;
		case 'pingback' :
			echo $pingbacktxt;
			break;
		default :
			echo $commenttxt;
	}
}

/**
 * Retrieve The current post's trackback URL.
 *
 * There is a check to see if permalink's have been enabled and if so, will
 * retrieve the pretty path. If permalinks weren't enabled, the ID of the
 * current post is used and appended to the correct page to go to.
 *
 * @since 1.5.0
 *
 * @return string The trackback URL after being filtered.
 */
function get_trackback_url() {
	if ( '' != get_option('permalink_structure') )
		$tb_url = trailingslashit(get_permalink()) . user_trailingslashit('trackback', 'single_trackback');
	else
		$tb_url = get_option('siteurl') . '/wp-trackback.php?p=' . get_the_ID();

	/**
	 * Filter the returned trackback URL.
	 *
	 * @since 2.2.0
	 *
	 * @param string $tb_url The trackback URL.
	 */
	return apply_filters( 'trackback_url', $tb_url );
}

/**
 * Display the current post's trackback URL.
 *
 * @since 0.71
 *
 * @param bool $deprecated_echo Not used.
 * @return void|string Should only be used to echo the trackback URL, use get_trackback_url()
 *                     for the result instead.
 */
function trackback_url( $deprecated_echo = true ) {
	if ( true !== $deprecated_echo ) {
		_deprecated_argument( __FUNCTION__, '2.5',
			/* translators: %s: get_trackback_url() */
			sprintf( __( 'Use %s instead if you do not want the value echoed.' ),
				'<code>get_trackback_url()</code>'
			)
		);
	}

	if ( $deprecated_echo ) {
		echo get_trackback_url();
	} else {
		return get_trackback_url();
	}
}

/**
 * Generate and display the RDF for the trackback information of current post.
 *
 * Deprecated in 3.0.0, and restored in 3.0.1.
 *
 * @since 0.71
 *
 * @param int $deprecated Not used (Was $timezone = 0).
 */
function trackback_rdf( $deprecated = '' ) {
	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '2.5' );
	}

	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== stripos( $_SERVER['HTTP_USER_AGENT'], 'W3C_Validator' ) ) {
		return;
	}

	echo '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/">
		<rdf:Description rdf:about="';
	the_permalink();
	echo '"'."\n";
	echo '    dc:identifier="';
	the_permalink();
	echo '"'."\n";
	echo '    dc:title="'.str_replace('--', '&#x2d;&#x2d;', wptexturize(strip_tags(get_the_title()))).'"'."\n";
	echo '    trackback:ping="'.get_trackback_url().'"'." />\n";
	echo '</rdf:RDF>';
}

/**
 * Whether the current post is open for comments.
 *
 * @since 1.5.0
 *
 * @param int|WP_Post $post_id Post ID or WP_Post object. Default current post.
 * @return bool True if the comments are open.
 */
function comments_open( $post_id = null ) {

	$_post = get_post($post_id);

	$open = ( 'open' == $_post->comment_status );

	/**
	 * Filter whether the current post is open for comments.
	 *
	 * @since 2.5.0
	 *
	 * @param bool        $open    Whether the current post is open for comments.
	 * @param int|WP_Post $post_id The post ID or WP_Post object.
	 */
	return apply_filters( 'comments_open', $open, $post_id );
}

/**
 * Whether the current post is open for pings.
 *
 * @since 1.5.0
 *
 * @param int|WP_Post $post_id Post ID or WP_Post object. Default current post.
 * @return bool True if pings are accepted
 */
function pings_open( $post_id = null ) {

	$_post = get_post($post_id);

	$open = ( 'open' == $_post->ping_status );

	/**
	 * Filter whether the current post is open for pings.
	 *
	 * @since 2.5.0
	 *
	 * @param bool        $open    Whether the current post is open for pings.
	 * @param int|WP_Post $post_id The post ID or WP_Post object.
	 */
	return apply_filters( 'pings_open', $open, $post_id );
}

/**
 * Display form token for unfiltered comments.
 *
 * Will only display nonce token if the current user has permissions for
 * unfiltered html. Won't display the token for other users.
 *
 * The function was backported to 2.0.10 and was added to versions 2.1.3 and
 * above. Does not exist in versions prior to 2.0.10 in the 2.0 branch and in
 * the 2.1 branch, prior to 2.1.3. Technically added in 2.2.0.
 *
 * Backported to 2.0.10.
 *
 * @since 2.1.3
 */
function wp_comment_form_unfiltered_html_nonce() {
	$post = get_post();
	$post_id = $post ? $post->ID : 0;

	if ( current_user_can( 'unfiltered_html' ) ) {
		wp_nonce_field( 'unfiltered-html-comment_' . $post_id, '_wp_unfiltered_html_comment_disabled', false );
		echo "<script>(function(){if(window===window.parent){document.getElementById('_wp_unfiltered_html_comment_disabled').name='_wp_unfiltered_html_comment';}})();</script>\n";
	}
}

/**
 * Load the comment template specified in $file.
 *
 * Will not display the comments template if not on single post or page, or if
 * the post does not have comments.
 *
 * Uses the WordPress database object to query for the comments. The comments
 * are passed through the 'comments_array' filter hook with the list of comments
 * and the post ID respectively.
 *
 * The $file path is passed through a filter hook called, 'comments_template'
 * which includes  and $file combined. Tries the $filtered path
 * first and if it fails it will require the default comment template from the
 * default theme. If either does not exist, then the WordPress process will be
 * halted. It is advised for that reason, that the default theme is not deleted.
 *
 * Will not try to get the comments if the post has none.
 *
 * @since 1.5.0
 *
 * @global WP_Query   $wp_query
 * @global WP_Post    $post
 * @global wpdb       $wpdb
 * @global int        $id
 * @global WP_Comment $comment
 * @global string     $user_login
 * @global int        $user_ID
 * @global string     $user_identity
 * @global bool       $overridden_cpage
 * @global bool       $withcomments
 *
 * @param string $file              Optional. The file to load. Default '/comments.php'.
 * @param bool   $separate_comments Optional. Whether to separate the comments by comment type.
 *                                  Default false.
 */
function comments_template( $file = '/comments.php', $separate_comments = false ) {
	global $wp_query, $withcomments, $post, $wpdb, $id, $comment, $user_login, $user_ID, $user_identity, $overridden_cpage;

	if ( !(is_single() || is_page() || $withcomments) || empty($post) )
		return;

	if ( empty($file) )
		$file = '/comments.php';

	$req = get_option('require_name_email');

	/*
	 * Comment author information fetched from the comment cookies.
	 */
	$commenter = wp_get_current_commenter();

	/*
	 * The name of the current comment author escaped for use in attributes.
	 * Escaped by sanitize_comment_cookies().
	 */
	$comment_author = $commenter['comment_author'];

	/*
	 * The email address of the current comment author escaped for use in attributes.
	 * Escaped by sanitize_comment_cookies().
	 */
	$comment_author_email = $commenter['comment_author_email'];

	/*
	 * The url of the current comment author escaped for use in attributes.
	 */
	$comment_author_url = esc_url($commenter['comment_author_url']);

	$comment_args = array(
		'orderby' => 'comment_date_gmt',
		'order' => 'ASC',
		'status'  => 'approve',
		'post_id' => $post->ID,
		'no_found_rows' => false,
		'update_comment_meta_cache' => false, // We lazy-load comment meta for performance.
	);

	if ( get_option('thread_comments') ) {
		$comment_args['hierarchical'] = 'threaded';
	} else {
		$comment_args['hierarchical'] = false;
	}

	if ( $user_ID ) {
		$comment_args['include_unapproved'] = array( $user_ID );
	} elseif ( ! empty( $comment_author_email ) ) {
		$comment_args['include_unapproved'] = array( $comment_author_email );
	}

	$per_page = 0;
	if ( get_option( 'page_comments' ) ) {
		$per_page = (int) get_query_var( 'comments_per_page' );
		if ( 0 === $per_page ) {
			$per_page = (int) get_option( 'comments_per_page' );
		}

		$comment_args['number'] = $per_page;
		$page = (int) get_query_var( 'cpage' );

		if ( $page ) {
			$comment_args['offset'] = ( $page - 1 ) * $per_page;
		} elseif ( 'oldest' === get_option( 'default_comments_page' ) ) {
			$comment_args['offset'] = 0;
		} else {
			// If fetching the first page of 'newest', we need a top-level comment count.
			$top_level_query = new WP_Comment_Query();
			$top_level_args  = array(
				'count'   => true,
				'orderby' => false,
				'post_id' => $post->ID,
				'status'  => 'approve',
			);

			if ( $comment_args['hierarchical'] ) {
				$top_level_args['parent'] = 0;
			}

			if ( isset( $comment_args['include_unapproved'] ) ) {
				$top_level_args['include_unapproved'] = $comment_args['include_unapproved'];
			}

			$top_level_count = $top_level_query->query( $top_level_args );

			$comment_args['offset'] = ( ceil( $top_level_count / $per_page ) - 1 ) * $per_page;
		}
	}

	/**
	 * Filters the arguments used to query comments in comments_template().
	 *
	 * @since 4.5.0
	 *
	 * @see WP_Comment_Query::__construct()
	 *
	 * @param array $comment_args {
	 *     Array of WP_Comment_Query arguments.
	 *
	 *     @type string|array $orderby                   Field(s) to order by.
	 *     @type string       $order                     Order of results. Accepts 'ASC' or 'DESC'.
	 *     @type string       $status                    Comment status.
	 *     @type array        $include_unapproved        Array of IDs or email addresses whose unapproved comments
	 *                                                   will be included in results.
	 *     @type int          $post_id                   ID of the post.
	 *     @type bool         $no_found_rows             Whether to refrain from querying for found rows.
	 *     @type bool         $update_comment_meta_cache Whether to prime cache for comment meta.
	 *     @type bool|string  $hierarchical              Whether to query for comments hierarchically.
	 *     @type int          $offset                    Comment offset.
	 *     @type int          $number                    Number of comments to fetch.
	 * }
	 */
	$comment_args = apply_filters( 'comments_template_query_args', $comment_args );
	$comment_query = new WP_Comment_Query( $comment_args );
	$_comments = $comment_query->comments;

	// Trees must be flattened before they're passed to the walker.
	if ( $comment_args['hierarchical'] ) {
		$comments_flat = array();
		foreach ( $_comments as $_comment ) {
			$comments_flat[]  = $_comment;
			$comment_children = $_comment->get_children( array(
				'format' => 'flat',
				'status' => $comment_args['status'],
				'orderby' => $comment_args['orderby']
			) );

			foreach ( $comment_children as $comment_child ) {
				$comments_flat[] = $comment_child;
			}
		}
	} else {
		$comments_flat = $_comments;
	}

	/**
	 * Filter the comments array.
	 *
	 * @since 2.1.0
	 *
	 * @param array $comments Array of comments supplied to the comments template.
	 * @param int   $post_ID  Post ID.
	 */
	$wp_query->comments = apply_filters( 'comments_array', $comments_flat, $post->ID );

	$comments = &$wp_query->comments;
	$wp_query->comment_count = count($wp_query->comments);
	$wp_query->max_num_comment_pages = $comment_query->max_num_pages;

	if ( $separate_comments ) {
		$wp_query->comments_by_type = separate_comments($comments);
		$comments_by_type = &$wp_query->comments_by_type;
	} else {
		$wp_query->comments_by_type = array();
	}

	$overridden_cpage = false;
	if ( '' == get_query_var( 'cpage' ) && $wp_query->max_num_comment_pages > 1 ) {
		set_query_var( 'cpage', 'newest' == get_option('default_comments_page') ? get_comment_pages_count() : 1 );
		$overridden_cpage = true;
	}

	if ( !defined('COMMENTS_TEMPLATE') )
		define('COMMENTS_TEMPLATE', true);

	$theme_template = STYLESHEETPATH . $file;

	$include = apply_filters( 'comments_template', $theme_template );
	if ( file_exists( $include ) )
		require( $include );
	elseif ( file_exists( get_template_directory() . $file ) )
		require( get_template_directory() . $file );
}

function comments_popup_link( $zero = false, $one = false, $more = false, $css_class = '', $none = false ) {
	$id = get_the_ID();
	$title = get_the_title();
	$number = get_comments_number( $id );

	if ( false === $zero ) {
		$zero = sprintf( 'No Comments<span class="screen-reader-text"> on %s</span>', $title );
	}
	if ( false === $one ) {
		$one = sprintf( '1 Comment<span class="screen-reader-text"> on %s</span>', $title );
	}
	if ( false === $more ) {
		$more = _n( '%1$s Comment<span class="screen-reader-text"> on %2$s</span>', '%1$s Comments<span class="screen-reader-text"> on %2$s</span>', $number );
		$more = sprintf( $more, number_format_i18n( $number ), $title );
	}

	if ( false === $none ) {
		$none = sprintf( 'Comments Off<span class="screen-reader-text"> on %s</span>', $title );
	}

	if ( 0 == $number && !comments_open() && !pings_open() ) {
		echo '<span' . ((!empty($css_class)) ? ' class="' . esc_attr( $css_class ) . '"' : '') . '>' . $none . '</span>';
		return;
	}

	if ( post_password_required() ) {
		echo 'Enter your password to view comments.';
		return;
	}

	echo '<a href="';
	if ( 0 == $number ) {
		$respond_link = get_permalink() . '#respond';

		echo apply_filters( 'respond_link', $respond_link, $id );
	} else {
		comments_link();
	}
	echo '"';

	if ( !empty( $css_class ) ) {
		echo ' class="'.$css_class.'" ';
	}

	$attributes = '';

	echo apply_filters( 'comments_popup_link_attributes', $attributes );

	echo '>';
	comments_number( $zero, $one, $more );
	echo '</a>';
}

function get_comment_reply_link( $args = array(), $comment = null, $post = null ) {
	$defaults = array(
		'add_below'     => 'comment',
		'respond_id'    => 'respond',
		'reply_text'    => 'Reply',
		'reply_to_text' => 'Reply to %s',
		'login_text'    => 'Log in to Reply',
		'depth'         => 0,
		'before'        => '',
		'after'         => ''
	);

	$args = wp_parse_args( $args, $defaults );

	if ( 0 == $args['depth'] || $args['max_depth'] <= $args['depth'] ) {
		return;
	}

	$comment = get_comment( $comment );

	if ( empty( $post ) ) {
		$post = $comment->comment_post_ID;
	}

	$post = get_post( $post );

	if ( ! comments_open( $post->ID ) ) {
		return false;
	}

	$args = apply_filters( 'comment_reply_link_args', $args, $comment, $post );

	if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
		$link = sprintf( '<a rel="nofollow" class="comment-reply-login" href="%s">%s</a>',
			esc_url( wp_login_url( get_permalink() ) ),
			$args['login_text']
		);
	} else {
		$onclick = sprintf( 'return addComment.moveForm( "%1$s-%2$s", "%2$s", "%3$s", "%4$s" )',
			$args['add_below'], $comment->comment_ID, $args['respond_id'], $post->ID
		);

		$link = sprintf( "<a rel='nofollow' class='comment-reply-link' href='%s' onclick='%s' aria-label='%s'>%s</a>",
			esc_url( add_query_arg( 'replytocom', $comment->comment_ID, get_permalink( $post->ID ) ) ) . "#" . $args['respond_id'],
			$onclick,
			esc_attr( sprintf( $args['reply_to_text'], $comment->comment_author ) ),
			$args['reply_text']
		);
	}

	return apply_filters( 'comment_reply_link', $args['before'] . $link . $args['after'], $args, $comment, $post );
}

function get_post_reply_link($args = array(), $post = null) {
	$defaults = array(
		'add_below'  => 'post',
		'respond_id' => 'respond',
		'reply_text' => 'Leave a Comment',
		'login_text' => 'Log in to leave a Comment',
		'before'     => '',
		'after'      => '',
	);

	$args = wp_parse_args($args, $defaults);

	$post = get_post($post);

	if ( ! comments_open( $post->ID ) ) {
		return false;
	}

	if ( get_option('comment_registration') && ! is_user_logged_in() ) {
		$link = sprintf( '<a rel="nofollow" class="comment-reply-login" href="%s">%s</a>',
			wp_login_url( get_permalink() ),
			$args['login_text']
		);
	} else {
		$onclick = sprintf( 'return addComment.moveForm( "%1$s-%2$s", "0", "%3$s", "%2$s" )',
			$args['add_below'], $post->ID, $args['respond_id']
		);

		$link = sprintf( "<a rel='nofollow' class='comment-reply-link' href='%s' onclick='%s'>%s</a>",
			get_permalink( $post->ID ) . '#' . $args['respond_id'],
			$onclick,
			$args['reply_text']
		);
	}
	$formatted_link = $args['before'] . $link . $args['after'];

	return apply_filters( 'post_comments_link', $formatted_link, $post );
}

function get_cancel_comment_reply_link( $text = '' ) {
	if ( empty($text) )
		$text = 'Click here to cancel reply.';

	$style = isset($_GET['replytocom']) ? '' : ' style="display:none;"';
	$link = esc_html( remove_query_arg('replytocom') ) . '#respond';

	$formatted_link = '<a rel="nofollow" id="cancel-comment-reply-link" href="' . $link . '"' . $style . '>' . $text . '</a>';

	return apply_filters( 'cancel_comment_reply_link', $formatted_link, $link, $text );
}

function get_comment_id_fields( $id = 0 ) {
	if ( empty( $id ) )
		$id = get_the_ID();

	$replytoid = isset($_GET['replytocom']) ? (int) $_GET['replytocom'] : 0;
	$result  = "<input type='hidden' name='comment_post_ID' value='$id' id='comment_post_ID' />\n";
	$result .= "<input type='hidden' name='comment_parent' id='comment_parent' value='$replytoid' />\n";

	return apply_filters( 'comment_id_fields', $result, $id, $replytoid );
}

function comment_form_title( $noreplytext = false, $replytext = false, $linktoparent = true ) {
	global $comment;

	if ( false === $noreplytext ) $noreplytext = __( 'Leave a Reply' );
	if ( false === $replytext ) $replytext = __( 'Leave a Reply to %s' );

	$replytoid = isset($_GET['replytocom']) ? (int) $_GET['replytocom'] : 0;

	if ( 0 == $replytoid )
		echo $noreplytext;
	else {
		// Sets the global so that template tags can be used in the comment form.
		$comment = get_comment($replytoid);
		$author = ( $linktoparent ) ? '<a href="#comment-' . get_comment_ID() . '">' . get_comment_author( $comment ) . '</a>' : get_comment_author( $comment );
		printf( $replytext, $author );
	}
}

function wp_list_comments( $args = array(), $comments = null ) {
	global $wp_query, $comment_alt, $comment_depth, $comment_thread_alt, $overridden_cpage, $in_comment_loop;

	$in_comment_loop = true;

	$comment_alt = $comment_thread_alt = 0;
	$comment_depth = 1;

	$defaults = array(
		'walker'            => null,
		'max_depth'         => '',
		'style'             => 'ul',
		'callback'          => null,
		'end-callback'      => null,
		'type'              => 'all',
		'page'              => '',
		'per_page'          => '',
		'avatar_size'       => 32,
		'reverse_top_level' => null,
		'reverse_children'  => '',
		'format'            => current_theme_supports( 'html5', 'comment-list' ) ? 'html5' : 'xhtml',
		'short_ping'        => false,
		'echo'              => true,
	);

	$r = wp_parse_args( $args, $defaults );

	$r = apply_filters( 'wp_list_comments_args', $r );

	// Figure out what comments we'll be looping through ($_comments)
	if ( null !== $comments ) {
		$comments = (array) $comments;
		if ( empty($comments) )
			return;
		if ( 'all' != $r['type'] ) {
			$comments_by_type = separate_comments($comments);
			if ( empty($comments_by_type[$r['type']]) )
				return;
			$_comments = $comments_by_type[$r['type']];
		} else {
			$_comments = $comments;
		}
	} else {

		if ( $r['page'] || $r['per_page'] ) {
			$current_cpage = get_query_var( 'cpage' );
			if ( ! $current_cpage ) {
				$current_cpage = 'newest' === get_option( 'default_comments_page' ) ? 1 : $wp_query->max_num_comment_pages;
			}

			$current_per_page = get_query_var( 'comments_per_page' );
			if ( $r['page'] != $current_cpage || $r['per_page'] != $current_per_page ) {

				$comments = get_comments( array(
					'post_id' => get_the_ID(),
					'orderby' => 'comment_date_gmt',
					'order' => 'ASC',
					'status' => 'all',
				) );

				if ( 'all' != $r['type'] ) {
					$comments_by_type = separate_comments( $comments );
					if ( empty( $comments_by_type[ $r['type'] ] ) ) {
						return;
					}

					$_comments = $comments_by_type[ $r['type'] ];
				} else {
					$_comments = $comments;
				}
			}

		// Otherwise, fall back on the comments from `$wp_query->comments`.
		} else {
			if ( empty($wp_query->comments) )
				return;
			if ( 'all' != $r['type'] ) {
				if ( empty($wp_query->comments_by_type) )
					$wp_query->comments_by_type = separate_comments($wp_query->comments);
				if ( empty($wp_query->comments_by_type[$r['type']]) )
					return;
				$_comments = $wp_query->comments_by_type[$r['type']];
			} else {
				$_comments = $wp_query->comments;
			}

			if ( $wp_query->max_num_comment_pages ) {
				$default_comments_page = get_option( 'default_comments_page' );
				$cpage = get_query_var( 'cpage' );
				if ( 'newest' === $default_comments_page ) {
					$r['cpage'] = $cpage;

				/*
				 * When first page shows oldest comments, post permalink is the same as
				 * the comment permalink.
				 */
				} elseif ( $cpage == 1 ) {
					$r['cpage'] = '';
				} else {
					$r['cpage'] = $cpage;
				}

				$r['page'] = 0;
				$r['per_page'] = 0;
			}
		}
	}

	if ( '' === $r['per_page'] && get_option( 'page_comments' ) ) {
		$r['per_page'] = get_query_var('comments_per_page');
	}

	if ( empty($r['per_page']) ) {
		$r['per_page'] = 0;
		$r['page'] = 0;
	}

	if ( '' === $r['max_depth'] ) {
		if ( get_option('thread_comments') )
			$r['max_depth'] = get_option('thread_comments_depth');
		else
			$r['max_depth'] = -1;
	}

	if ( '' === $r['page'] ) {
		if ( empty($overridden_cpage) ) {
			$r['page'] = get_query_var('cpage');
		} else {
			$threaded = ( -1 != $r['max_depth'] );
			$r['page'] = ( 'newest' == get_option('default_comments_page') ) ? get_comment_pages_count($_comments, $r['per_page'], $threaded) : 1;
			set_query_var( 'cpage', $r['page'] );
		}
	}
	// Validation check
	$r['page'] = intval($r['page']);
	if ( 0 == $r['page'] && 0 != $r['per_page'] )
		$r['page'] = 1;

	if ( null === $r['reverse_top_level'] )
		$r['reverse_top_level'] = ( 'desc' == get_option('comment_order') );

	wp_queue_comments_for_comment_meta_lazyload( $_comments );

	if ( empty( $r['walker'] ) ) {
		$walker = new Walker_Comment;
	} else {
		$walker = $r['walker'];
	}

	$output = $walker->paged_walk( $_comments, $r['max_depth'], $r['page'], $r['per_page'], $r );

	$in_comment_loop = false;

	if ( $r['echo'] ) {
		echo $output;
	} else {
		return $output;
	}
}

function comment_form( $args = array(), $post_id = null ) {
	if ( null === $post_id )
		$post_id = get_the_ID();

	$commenter = wp_get_current_commenter();
	$user = wp_get_current_user();
	$user_identity = $user->exists() ? $user->display_name : '';

	$args = wp_parse_args( $args );
	if ( ! isset( $args['format'] ) )
		$args['format'] = current_theme_supports( 'html5', 'comment-form' ) ? 'html5' : 'xhtml';

	$req      = get_option( 'require_name_email' );
	$aria_req = ( $req ? " aria-required='true'" : '' );
	$html_req = ( $req ? " required='required'" : '' );
	$html5    = 'html5' === $args['format'];
	$fields   =  array(
		'author' => '<p class="comment-form-author">' . '<label for="author">Name' . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
		            '<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" maxlength="245"' . $aria_req . $html_req . ' /></p>',
		'email'  => '<p class="comment-form-email"><label for="email">Email' . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
		            '<input id="email" name="email" ' . ( $html5 ? 'type="email"' : 'type="text"' ) . ' value="' . esc_attr(  $commenter['comment_author_email'] ) . '" size="30" maxlength="100" aria-describedby="email-notes"' . $aria_req . $html_req  . ' /></p>',
		'url'    => '<p class="comment-form-url"><label for="url">Website' . '</label> ' .
		            '<input id="url" name="url" ' . ( $html5 ? 'type="url"' : 'type="text"' ) . ' value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="30" maxlength="200" /></p>',
	);

	$required_text = sprintf( ' ' . __('Required fields are marked %s'), '<span class="required">*</span>' );

	/**
	 * Filter the default comment form fields.
	 *
	 * @since 3.0.0
	 *
	 * @param array $fields The default comment fields.
	 */
	$fields = apply_filters( 'comment_form_default_fields', $fields );
	$defaults = array(
		'fields'               => $fields,
		'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . _x( 'Comment', 'noun' ) . '</label> <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" aria-required="true" required="required"></textarea></p>',
		/** This filter is documented in wp-includes/link-template.php */
		'must_log_in'          => '<p class="must-log-in">' . sprintf(
		                              /* translators: %s: login URL */
		                              __( 'You must be <a href="%s">logged in</a> to post a comment.' ),
		                              wp_login_url( apply_filters( 'the_permalink', get_permalink( $post_id ) ) )
		                          ) . '</p>',
		/** This filter is documented in wp-includes/link-template.php */
		'logged_in_as'         => '<p class="logged-in-as">' . sprintf(
		                              /* translators: 1: edit user link, 2: accessibility text, 3: user name, 4: logout URL */
		                              __( '<a href="%1$s" aria-label="%2$s">Logged in as %3$s</a>. <a href="%4$s">Log out?</a>' ),
		                              get_edit_user_link(),
		                              /* translators: %s: user name */
		                              esc_attr( sprintf( __( 'Logged in as %s. Edit your profile.' ), $user_identity ) ),
		                              $user_identity,
		                              wp_logout_url( apply_filters( 'the_permalink', get_permalink( $post_id ) ) )
		                          ) . '</p>',
		'comment_notes_before' => '<p class="comment-notes"><span id="email-notes">' . __( 'Your email address will not be published.' ) . '</span>'. ( $req ? $required_text : '' ) . '</p>',
		'comment_notes_after'  => '',
		'id_form'              => 'commentform',
		'id_submit'            => 'submit',
		'class_form'           => 'comment-form',
		'class_submit'         => 'submit',
		'name_submit'          => 'submit',
		'title_reply'          => 'Leave a Reply',
		'title_reply_to'       => 'Leave a Reply to %s',
		'title_reply_before'   => '<h3 id="reply-title" class="comment-reply-title">',
		'title_reply_after'    => '</h3>',
		'cancel_reply_before'  => ' <small>',
		'cancel_reply_after'   => '</small>',
		'cancel_reply_link'    => 'Cancel reply',
		'label_submit'         => 'Post Comment',
		'submit_button'        => '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" />',
		'submit_field'         => '<p class="form-submit">%1$s %2$s</p>',
		'format'               => 'xhtml',
	);

	$args = wp_parse_args( $args, apply_filters( 'comment_form_defaults', $defaults ) );

	// Ensure that the filtered args contain all required default values.
	$args = array_merge( $defaults, $args );

	if ( comments_open( $post_id ) ) : ?>
		<?php do_action( 'comment_form_before' ); ?>
		<div id="respond" class="comment-respond">
			<?php
			echo $args['title_reply_before'];

			comment_form_title( $args['title_reply'], $args['title_reply_to'] );

			echo $args['cancel_reply_before'];

			cancel_comment_reply_link( $args['cancel_reply_link'] );

			echo $args['cancel_reply_after'];

			echo $args['title_reply_after'];

			if ( get_option( 'comment_registration' ) && !is_user_logged_in() ) :
				echo $args['must_log_in'];
				do_action( 'comment_form_must_log_in_after' );
			else : ?>
				<form action="<?php echo site_url( '/wp-comments-post.php' ); ?>" method="post" id="<?php echo esc_attr( $args['id_form'] ); ?>" class="<?php echo esc_attr( $args['class_form'] ); ?>"<?php echo $html5 ? ' novalidate' : ''; ?>>
					<?php
					/**
					 * Fires at the top of the comment form, inside the form tag.
					 *
					 * @since 3.0.0
					 */
					do_action( 'comment_form_top' );

					if ( is_user_logged_in() ) :
						echo apply_filters( 'comment_form_logged_in', $args['logged_in_as'], $commenter, $user_identity );
						do_action( 'comment_form_logged_in_after', $commenter, $user_identity );
					else :
						echo $args['comment_notes_before'];
					endif;
					$comment_fields = array( 'comment' => $args['comment_field'] ) + (array) $args['fields'];

					/**
					 * Filter the comment form fields, including the textarea.
					 *
					 * @since 4.4.0
					 *
					 * @param array $comment_fields The comment fields.
					 */
					$comment_fields = apply_filters( 'comment_form_fields', $comment_fields );

					// Get an array of field names, excluding the textarea
					$comment_field_keys = array_diff( array_keys( $comment_fields ), array( 'comment' ) );

					// Get the first and the last field name, excluding the textarea
					$first_field = reset( $comment_field_keys );
					$last_field  = end( $comment_field_keys );

					foreach ( $comment_fields as $name => $field ) {

						if ( 'comment' === $name ) {

							echo apply_filters( 'comment_form_field_comment', $field );

							echo $args['comment_notes_after'];

						} elseif ( ! is_user_logged_in() ) {

							if ( $first_field === $name ) {

								do_action( 'comment_form_before_fields' );
							}

							echo apply_filters( "comment_form_field_{$name}", $field ) . "\n";

							if ( $last_field === $name ) {

								do_action( 'comment_form_after_fields' );
							}
						}
					}

					$submit_button = sprintf(
						$args['submit_button'],
						esc_attr( $args['name_submit'] ),
						esc_attr( $args['id_submit'] ),
						esc_attr( $args['class_submit'] ),
						esc_attr( $args['label_submit'] )
					);

					$submit_button = apply_filters( 'comment_form_submit_button', $submit_button, $args );

					$submit_field = sprintf(
						$args['submit_field'],
						$submit_button,
						get_comment_id_fields( $post_id )
					);

					echo apply_filters( 'comment_form_submit_field', $submit_field, $args );

					do_action( 'comment_form', $post_id );
					?>
				</form>
			<?php endif; ?>
		</div>
		<?php
		do_action( 'comment_form_after' );
	else :
		do_action( 'comment_form_comments_closed' );
	endif;
}
