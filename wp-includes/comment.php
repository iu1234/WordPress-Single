<?php
/**
 * Core Comment API
 *
 * @package WordPress
 * @subpackage Comment
 */

function check_comment($author, $email, $url, $comment, $user_ip, $user_agent, $comment_type) {
	global $wpdb;
	if ( 1 == get_option('comment_moderation') )
		return false;
	$comment = apply_filters( 'comment_text', $comment );
	if ( $max_links = get_option( 'comment_max_links' ) ) {
		$num_links = preg_match_all( '/<a [^>]*href/i', $comment, $out );
		$num_links = apply_filters( 'comment_max_links_url', $num_links, $url );
		if ( $num_links >= $max_links )
			return false;
	}

	$mod_keys = trim(get_option('moderation_keys'));
	if ( !empty($mod_keys) ) {
		$words = explode("\n", $mod_keys );

		foreach ( (array) $words as $word) {
			$word = trim($word);

			// Skip empty lines.
			if ( empty($word) )
				continue;

			/*
			 * Do some escaping magic so that '#' (number of) characters in the spam
			 * words don't break things:
			 */
			$word = preg_quote($word, '#');

			$pattern = "#$word#i";
			if ( preg_match($pattern, $author) ) return false;
			if ( preg_match($pattern, $email) ) return false;
			if ( preg_match($pattern, $url) ) return false;
			if ( preg_match($pattern, $comment) ) return false;
			if ( preg_match($pattern, $user_ip) ) return false;
			if ( preg_match($pattern, $user_agent) ) return false;
		}
	}

	if ( 1 == get_option('comment_whitelist')) {
		if ( 'trackback' != $comment_type && 'pingback' != $comment_type && $author != '' && $email != '' ) {
			// expected_slashed ($author, $email)
			$ok_to_comment = $wpdb->get_var("SELECT comment_approved FROM $wpdb->comments WHERE comment_author = '$author' AND comment_author_email = '$email' and comment_approved = '1' LIMIT 1");
			if ( ( 1 == $ok_to_comment ) &&
				( empty($mod_keys) || false === strpos( $email, $mod_keys) ) )
					return true;
			else
				return false;
		} else {
			return false;
		}
	}
	return true;
}

function get_approved_comments( $post_id, $args = array() ) {
	if ( ! $post_id ) {
		return array();
	}

	$defaults = array(
		'status'  => 1,
		'post_id' => $post_id,
		'order'   => 'ASC',
	);
	$r = wp_parse_args( $args, $defaults );

	$query = new WP_Comment_Query;
	return $query->query( $r );
}

function get_comment( &$comment = null, $output = OBJECT ) {
	if ( empty( $comment ) && isset( $GLOBALS['comment'] ) ) {
		$comment = $GLOBALS['comment'];
	}

	if ( $comment instanceof WP_Comment ) {
		$_comment = $comment;
	} elseif ( is_object( $comment ) ) {
		$_comment = new WP_Comment( $comment );
	} else {
		$_comment = WP_Comment::get_instance( $comment );
	}

	if ( ! $_comment ) {
		return null;
	}

	$_comment = apply_filters( 'get_comment', $_comment );

	if ( $output == OBJECT ) {
		return $_comment;
	} elseif ( $output == ARRAY_A ) {
		return $_comment->to_array();
	} elseif ( $output == ARRAY_N ) {
		return array_values( $_comment->to_array() );
	}
	return $_comment;
}

function get_comments( $args = '' ) {
	$query = new WP_Comment_Query;
	return $query->query( $args );
}

function get_comment_statuses() {
	$status = array(
		'hold'		=> 'Unapproved',
		'approve'	=> 'Approved',
		'spam'		=> 'Spam',
		'trash'		=> 'Trash',
	);
	return $status;
}

function get_default_comment_status( $post_type = 'post', $comment_type = 'comment' ) {
	switch ( $comment_type ) {
		case 'pingback' :
		case 'trackback' :
			$supports = 'trackbacks';
			$option = 'ping';
			break;
		default :
			$supports = 'comments';
			$option = 'comment';
	}

	// Set the status.
	if ( 'page' === $post_type ) {
		$status = 'closed';
	} elseif ( post_type_supports( $post_type, $supports ) ) {
		$status = get_option( "default_{$option}_status" );
	} else {
		$status = 'closed';
	}

	return apply_filters( 'get_default_comment_status' , $status, $post_type, $comment_type );
}

function get_lastcommentmodified($timezone = 'server') {
	global $wpdb;
	static $cache_lastcommentmodified = array();

	if ( isset($cache_lastcommentmodified[$timezone]) )
		return $cache_lastcommentmodified[$timezone];

	$add_seconds_server = date('Z');

	switch ( strtolower($timezone)) {
		case 'gmt':
			$lastcommentmodified = $wpdb->get_var("SELECT comment_date_gmt FROM $wpdb->comments WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 1");
			break;
		case 'blog':
			$lastcommentmodified = $wpdb->get_var("SELECT comment_date FROM $wpdb->comments WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 1");
			break;
		case 'server':
			$lastcommentmodified = $wpdb->get_var($wpdb->prepare("SELECT DATE_ADD(comment_date_gmt, INTERVAL %s SECOND) FROM $wpdb->comments WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 1", $add_seconds_server));
			break;
	}

	$cache_lastcommentmodified[$timezone] = $lastcommentmodified;

	return $lastcommentmodified;
}

function get_comment_count( $post_id = 0 ) {
	global $wpdb;

	$post_id = (int) $post_id;

	$where = '';
	if ( $post_id > 0 ) {
		$where = $wpdb->prepare("WHERE comment_post_ID = %d", $post_id);
	}

	$totals = (array) $wpdb->get_results("
		SELECT comment_approved, COUNT( * ) AS total
		FROM {$wpdb->comments}
		{$where}
		GROUP BY comment_approved
	", ARRAY_A);

	$comment_count = array(
		'approved'            => 0,
		'awaiting_moderation' => 0,
		'spam'                => 0,
		'trash'               => 0,
		'post-trashed'        => 0,
		'total_comments'      => 0,
		'all'                 => 0,
	);

	foreach ( $totals as $row ) {
		switch ( $row['comment_approved'] ) {
			case 'trash':
				$comment_count['trash'] = $row['total'];
				break;
			case 'post-trashed':
				$comment_count['post-trashed'] = $row['total'];
				break;
			case 'spam':
				$comment_count['spam'] = $row['total'];
				$comment_count['total_comments'] += $row['total'];
				break;
			case '1':
				$comment_count['approved'] = $row['total'];
				$comment_count['total_comments'] += $row['total'];
				$comment_count['all'] += $row['total'];
				break;
			case '0':
				$comment_count['awaiting_moderation'] = $row['total'];
				$comment_count['total_comments'] += $row['total'];
				$comment_count['all'] += $row['total'];
				break;
			default:
				break;
		}
	}

	return $comment_count;
}

function add_comment_meta($comment_id, $meta_key, $meta_value, $unique = false) {
	return add_metadata('comment', $comment_id, $meta_key, $meta_value, $unique);
}

function delete_comment_meta($comment_id, $meta_key, $meta_value = '') {
	return delete_metadata('comment', $comment_id, $meta_key, $meta_value);
}

function get_comment_meta($comment_id, $key = '', $single = false) {
	return get_metadata('comment', $comment_id, $key, $single);
}

function update_comment_meta($comment_id, $meta_key, $meta_value, $prev_value = '') {
	return update_metadata('comment', $comment_id, $meta_key, $meta_value, $prev_value);
}

function wp_queue_comments_for_comment_meta_lazyload( $comments ) {
	// Don't use `wp_list_pluck()` to avoid by-reference manipulation.
	$comment_ids = array();
	if ( is_array( $comments ) ) {
		foreach ( $comments as $comment ) {
			if ( $comment instanceof WP_Comment ) {
				$comment_ids[] = $comment->comment_ID;
			}
		}
	}

	if ( $comment_ids ) {
		$lazyloader = wp_metadata_lazyloader();
		$lazyloader->queue_objects( 'comment', $comment_ids );
	}
}

function wp_set_comment_cookies($comment, $user) {
	if ( $user->exists() )
		return;

	$comment_cookie_lifetime = apply_filters( 'comment_cookie_lifetime', 30000000 );
	$secure = ( 'https' === parse_url( home_url(), PHP_URL_SCHEME ) );
	setcookie( 'comment_author_' . COOKIEHASH, $comment->comment_author, time() + $comment_cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN, $secure );
	setcookie( 'comment_author_email_' . COOKIEHASH, $comment->comment_author_email, time() + $comment_cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN, $secure );
	setcookie( 'comment_author_url_' . COOKIEHASH, esc_url($comment->comment_author_url), time() + $comment_cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN, $secure );
}

function sanitize_comment_cookies() {
	if ( isset( $_COOKIE['comment_author_' . COOKIEHASH] ) ) {
		$comment_author = apply_filters( 'pre_comment_author_name', $_COOKIE['comment_author_' . COOKIEHASH] );
		$comment_author = wp_unslash($comment_author);
		$comment_author = esc_attr($comment_author);
		$_COOKIE['comment_author_' . COOKIEHASH] = $comment_author;
	}

	if ( isset( $_COOKIE['comment_author_email_' . COOKIEHASH] ) ) {
		$comment_author_email = apply_filters( 'pre_comment_author_email', $_COOKIE['comment_author_email_' . COOKIEHASH] );
		$comment_author_email = wp_unslash($comment_author_email);
		$comment_author_email = esc_attr($comment_author_email);
		$_COOKIE['comment_author_email_'.COOKIEHASH] = $comment_author_email;
	}

	if ( isset( $_COOKIE['comment_author_url_' . COOKIEHASH] ) ) {
		$comment_author_url = apply_filters( 'pre_comment_author_url', $_COOKIE['comment_author_url_' . COOKIEHASH] );
		$comment_author_url = wp_unslash($comment_author_url);
		$_COOKIE['comment_author_url_'.COOKIEHASH] = $comment_author_url;
	}
}

function wp_allow_comment( $commentdata ) {
	global $wpdb;

	// Simple duplicate check
	// expected_slashed ($comment_post_ID, $comment_author, $comment_author_email, $comment_content)
	$dupe = $wpdb->prepare(
		"SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_parent = %s AND comment_approved != 'trash' AND ( comment_author = %s ",
		wp_unslash( $commentdata['comment_post_ID'] ),
		wp_unslash( $commentdata['comment_parent'] ),
		wp_unslash( $commentdata['comment_author'] )
	);
	if ( $commentdata['comment_author_email'] ) {
		$dupe .= $wpdb->prepare(
			"OR comment_author_email = %s ",
			wp_unslash( $commentdata['comment_author_email'] )
		);
	}
	$dupe .= $wpdb->prepare(
		") AND comment_content = %s LIMIT 1",
		wp_unslash( $commentdata['comment_content'] )
	);

	$dupe_id = $wpdb->get_var( $dupe );

	$dupe_id = apply_filters( 'duplicate_comment_id', $dupe_id, $commentdata );

	if ( $dupe_id ) {
		do_action( 'comment_duplicate_trigger', $commentdata );
		if ( defined( 'DOING_AJAX' ) ) {
			die( 'Duplicate comment detected; it looks as though you&#8217;ve already said that!' );
		}
		wp_die( 'Duplicate comment detected; it looks as though you&#8217;ve already said that!', 409 );
	}

	do_action(
		'check_comment_flood',
		$commentdata['comment_author_IP'],
		$commentdata['comment_author_email'],
		$commentdata['comment_date_gmt']
	);

	if ( ! empty( $commentdata['user_id'] ) ) {
		$user = get_user_by( 'id', $commentdata['user_id'] );
		$post_author = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_author FROM $wpdb->posts WHERE ID = %d LIMIT 1",
			$commentdata['comment_post_ID']
		) );
	}

	if ( isset( $user ) && ( $commentdata['user_id'] == $post_author || $user->has_cap( 'moderate_comments' ) ) ) {
		// The author and the admins get respect.
		$approved = 1;
	} else {
		// Everyone else's comments will be checked.
		if ( check_comment(
			$commentdata['comment_author'],
			$commentdata['comment_author_email'],
			$commentdata['comment_author_url'],
			$commentdata['comment_content'],
			$commentdata['comment_author_IP'],
			$commentdata['comment_agent'],
			$commentdata['comment_type']
		) ) {
			$approved = 1;
		} else {
			$approved = 0;
		}

		if ( wp_blacklist_check(
			$commentdata['comment_author'],
			$commentdata['comment_author_email'],
			$commentdata['comment_author_url'],
			$commentdata['comment_content'],
			$commentdata['comment_author_IP'],
			$commentdata['comment_agent']
		) ) {
			$approved = EMPTY_TRASH_DAYS ? 'trash' : 'spam';
		}
	}

	$approved = apply_filters( 'pre_comment_approved', $approved, $commentdata );
	return $approved;
}

function check_comment_flood_db( $ip, $email, $date ) {
	global $wpdb;
	// don't throttle admins or moderators
	if ( current_user_can( 'manage_options' ) || current_user_can( 'moderate_comments' ) ) {
		return;
	}
	$hour_ago = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

	if ( is_user_logged_in() ) {
		$user = get_current_user_id();
		$check_column = '`user_id`';
	} else {
		$user = $ip;
		$check_column = '`comment_author_IP`';
	}

	$sql = $wpdb->prepare(
		"SELECT `comment_date_gmt` FROM `$wpdb->comments` WHERE `comment_date_gmt` >= %s AND ( $check_column = %s OR `comment_author_email` = %s ) ORDER BY `comment_date_gmt` DESC LIMIT 1",
		$hour_ago,
		$user,
		$email
	);
	$lasttime = $wpdb->get_var( $sql );
	if ( $lasttime ) {
		$time_lastcomment = mysql2date('U', $lasttime, false);
		$time_newcomment  = mysql2date('U', $date, false);
		$flood_die = apply_filters( 'comment_flood_filter', false, $time_lastcomment, $time_newcomment );
		if ( $flood_die ) {
			do_action( 'comment_flood_trigger', $time_lastcomment, $time_newcomment );

			if ( defined('DOING_AJAX') )
				die( 'You are posting comments too quickly. Slow down.' );

			wp_die( 'You are posting comments too quickly. Slow down.', 429 );
		}
	}
}

function separate_comments(&$comments) {
	$comments_by_type = array('comment' => array(), 'trackback' => array(), 'pingback' => array(), 'pings' => array());
	$count = count($comments);
	for ( $i = 0; $i < $count; $i++ ) {
		$type = $comments[$i]->comment_type;
		if ( empty($type) )
			$type = 'comment';
		$comments_by_type[$type][] = &$comments[$i];
		if ( 'trackback' == $type || 'pingback' == $type )
			$comments_by_type['pings'][] = &$comments[$i];
	}

	return $comments_by_type;
}

function get_comment_pages_count( $comments = null, $per_page = null, $threaded = null ) {
	global $wp_query;

	if ( null === $comments && null === $per_page && null === $threaded && !empty($wp_query->max_num_comment_pages) )
		return $wp_query->max_num_comment_pages;

	if ( ( ! $comments || ! is_array( $comments ) ) && ! empty( $wp_query->comments )  )
		$comments = $wp_query->comments;

	if ( empty($comments) )
		return 0;

	if ( ! get_option( 'page_comments' ) ) {
		return 1;
	}

	if ( !isset($per_page) )
		$per_page = (int) get_query_var('comments_per_page');
	if ( 0 === $per_page )
		$per_page = (int) get_option('comments_per_page');
	if ( 0 === $per_page )
		return 1;

	if ( !isset($threaded) )
		$threaded = get_option('thread_comments');

	if ( $threaded ) {
		$walker = new Walker_Comment;
		$count = ceil( $walker->get_number_of_root_elements( $comments ) / $per_page );
	} else {
		$count = ceil( count( $comments ) / $per_page );
	}

	return $count;
}

function get_page_of_comment( $comment_ID, $args = array() ) {
	global $wpdb;

	$page = null;

	if ( !$comment = get_comment( $comment_ID ) )
		return;

	$defaults = array( 'type' => 'all', 'page' => '', 'per_page' => '', 'max_depth' => '' );
	$args = wp_parse_args( $args, $defaults );
	$original_args = $args;

	// Order of precedence: 1. `$args['per_page']`, 2. 'comments_per_page' query_var, 3. 'comments_per_page' option.
	if ( get_option( 'page_comments' ) ) {
		if ( '' === $args['per_page'] ) {
			$args['per_page'] = get_query_var( 'comments_per_page' );
		}

		if ( '' === $args['per_page'] ) {
			$args['per_page'] = get_option( 'comments_per_page' );
		}
	}

	if ( empty($args['per_page']) ) {
		$args['per_page'] = 0;
		$args['page'] = 0;
	}

	if ( $args['per_page'] < 1 ) {
		$page = 1;
	}

	if ( null === $page ) {
		if ( '' === $args['max_depth'] ) {
			if ( get_option('thread_comments') )
				$args['max_depth'] = get_option('thread_comments_depth');
			else
				$args['max_depth'] = -1;
		}

		// Find this comment's top level parent if threading is enabled
		if ( $args['max_depth'] > 1 && 0 != $comment->comment_parent )
			return get_page_of_comment( $comment->comment_parent, $args );

		$comment_args = array(
			'type'       => $args['type'],
			'post_id'    => $comment->comment_post_ID,
			'fields'     => 'ids',
			'count'      => true,
			'status'     => 'approve',
			'parent'     => 0,
			'date_query' => array(
				array(
					'column' => "$wpdb->comments.comment_date_gmt",
					'before' => $comment->comment_date_gmt,
				)
			),
		);

		$comment_query = new WP_Comment_Query();
		$older_comment_count = $comment_query->query( $comment_args );

		// No older comments? Then it's page #1.
		if ( 0 == $older_comment_count ) {
			$page = 1;

		// Divide comments older than this one by comments per page to get this comment's page number
		} else {
			$page = ceil( ( $older_comment_count + 1 ) / $args['per_page'] );
		}
	}

	return apply_filters( 'get_page_of_comment', (int) $page, $args, $original_args );
}

function wp_get_comment_fields_max_lengths() {
	global $wpdb;

	$lengths = array(
		'comment_author'       => 245,
		'comment_author_email' => 100,
		'comment_author_url'   => 200,
		'comment_content'      => 65525,
	);

	if ( $wpdb->is_mysql ) {
		foreach ( $lengths as $column => $length ) {
			$col_length = $wpdb->get_col_length( $wpdb->comments, $column );
			$max_length = 0;

			// No point if we can't get the DB column lengths
			if ( is_wp_error( $col_length ) ) {
				break;
			}

			if ( ! is_array( $col_length ) && (int) $col_length > 0 ) {
				$max_length = (int) $col_length;
			} elseif ( is_array( $col_length ) && isset( $col_length['length'] ) && intval( $col_length['length'] ) > 0 ) {
				$max_length = (int) $col_length['length'];

				if ( ! empty( $col_length['type'] ) && 'byte' === $col_length['type'] ) {
					$max_length = $max_length - 10;
				}
			}

			if ( $max_length > 0 ) {
				$lengths[ $column ] = $max_length;
			}
		}
	}

	return apply_filters( 'wp_get_comment_fields_max_lengths', $lengths );
}

function wp_blacklist_check($author, $email, $url, $comment, $user_ip, $user_agent) {
	do_action( 'wp_blacklist_check', $author, $email, $url, $comment, $user_ip, $user_agent );

	$mod_keys = trim( get_option('blacklist_keys') );
	if ( '' == $mod_keys )
		return false; // If moderation keys are empty
	$words = explode("\n", $mod_keys );

	foreach ( (array) $words as $word ) {
		$word = trim($word);

		// Skip empty lines
		if ( empty($word) ) { continue; }

		// Do some escaping magic so that '#' chars in the
		// spam words don't break things:
		$word = preg_quote($word, '#');

		$pattern = "#$word#i";
		if (
			   preg_match($pattern, $author)
			|| preg_match($pattern, $email)
			|| preg_match($pattern, $url)
			|| preg_match($pattern, $comment)
			|| preg_match($pattern, $user_ip)
			|| preg_match($pattern, $user_agent)
		 )
			return true;
	}
	return false;
}

function wp_count_comments( $post_id = 0 ) {
	$post_id = (int) $post_id;

	$filtered = apply_filters( 'wp_count_comments', array(), $post_id );
	if ( ! empty( $filtered ) ) {
		return $filtered;
	}

	$count = wp_cache_get( "comments-{$post_id}", 'counts' );
	if ( false !== $count ) {
		return $count;
	}

	$stats = get_comment_count( $post_id );
	$stats['moderated'] = $stats['awaiting_moderation'];
	unset( $stats['awaiting_moderation'] );

	$stats_object = (object) $stats;
	wp_cache_set( "comments-{$post_id}", $stats_object, 'counts' );

	return $stats_object;
}

function wp_delete_comment($comment_id, $force_delete = false) {
	global $wpdb;
	if (!$comment = get_comment($comment_id))
		return false;

	if ( !$force_delete && EMPTY_TRASH_DAYS && !in_array( wp_get_comment_status( $comment ), array( 'trash', 'spam' ) ) )
		return wp_trash_comment($comment_id);

	do_action( 'delete_comment', $comment->comment_ID );

	$children = $wpdb->get_col( $wpdb->prepare("SELECT comment_ID FROM $wpdb->comments WHERE comment_parent = %d", $comment->comment_ID) );
	if ( !empty($children) ) {
		$wpdb->update($wpdb->comments, array('comment_parent' => $comment->comment_parent), array('comment_parent' => $comment->comment_ID));
		clean_comment_cache($children);
	}

	// Delete metadata
	$meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->commentmeta WHERE comment_id = %d", $comment->comment_ID ) );
	foreach ( $meta_ids as $mid )
		delete_metadata_by_mid( 'comment', $mid );

	if ( ! $wpdb->delete( $wpdb->comments, array( 'comment_ID' => $comment->comment_ID ) ) )
		return false;

	do_action( 'deleted_comment', $comment->comment_ID );

	$post_id = $comment->comment_post_ID;
	if ( $post_id && $comment->comment_approved == 1 )
		wp_update_comment_count($post_id);

	clean_comment_cache( $comment->comment_ID );

	/** This action is documented in wp-includes/comment.php */
	do_action( 'wp_set_comment_status', $comment->comment_ID, 'delete' );

	wp_transition_comment_status('delete', $comment->comment_approved, $comment);
	return true;
}

function wp_trash_comment($comment_id) {
	if ( !EMPTY_TRASH_DAYS )
		return wp_delete_comment($comment_id, true);

	if ( !$comment = get_comment($comment_id) )
		return false;

	do_action( 'trash_comment', $comment->comment_ID );

	if ( wp_set_comment_status( $comment, 'trash' ) ) {
		delete_comment_meta( $comment->comment_ID, '_wp_trash_meta_status' );
		delete_comment_meta( $comment->comment_ID, '_wp_trash_meta_time' );
		add_comment_meta( $comment->comment_ID, '_wp_trash_meta_status', $comment->comment_approved );
		add_comment_meta( $comment->comment_ID, '_wp_trash_meta_time', time() );

		do_action( 'trashed_comment', $comment->comment_ID );
		return true;
	}

	return false;
}

function wp_untrash_comment($comment_id) {
	$comment = get_comment( $comment_id );
	if ( ! $comment ) {
		return false;
	}

	do_action( 'untrash_comment', $comment->comment_ID );

	$status = (string) get_comment_meta( $comment->comment_ID, '_wp_trash_meta_status', true );
	if ( empty($status) )
		$status = '0';

	if ( wp_set_comment_status( $comment, $status ) ) {
		delete_comment_meta( $comment->comment_ID, '_wp_trash_meta_time' );
		delete_comment_meta( $comment->comment_ID, '_wp_trash_meta_status' );
		do_action( 'untrashed_comment', $comment->comment_ID );
		return true;
	}

	return false;
}

function wp_spam_comment( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( ! $comment ) {
		return false;
	}

	do_action( 'spam_comment', $comment->comment_ID );

	if ( wp_set_comment_status( $comment, 'spam' ) ) {
		delete_comment_meta( $comment->comment_ID, '_wp_trash_meta_status' );
		delete_comment_meta( $comment->comment_ID, '_wp_trash_meta_time' );
		add_comment_meta( $comment->comment_ID, '_wp_trash_meta_status', $comment->comment_approved );
		add_comment_meta( $comment->comment_ID, '_wp_trash_meta_time', time() );
		do_action( 'spammed_comment', $comment->comment_ID );
		return true;
	}

	return false;
}

function wp_unspam_comment( $comment_id ) {
	$comment = get_comment( $comment_id );
	if ( ! $comment ) {
		return false;
	}

	do_action( 'unspam_comment', $comment->comment_ID );

	$status = (string) get_comment_meta( $comment->comment_ID, '_wp_trash_meta_status', true );
	if ( empty($status) )
		$status = '0';

	if ( wp_set_comment_status( $comment, $status ) ) {
		delete_comment_meta( $comment->comment_ID, '_wp_trash_meta_status' );
		delete_comment_meta( $comment->comment_ID, '_wp_trash_meta_time' );
		do_action( 'unspammed_comment', $comment->comment_ID );
		return true;
	}

	return false;
}

function wp_get_comment_status($comment_id) {
	$comment = get_comment($comment_id);
	if ( !$comment )
		return false;

	$approved = $comment->comment_approved;

	if ( $approved == null )
		return false;
	elseif ( $approved == '1' )
		return 'approved';
	elseif ( $approved == '0' )
		return 'unapproved';
	elseif ( $approved == 'spam' )
		return 'spam';
	elseif ( $approved == 'trash' )
		return 'trash';
	else
		return false;
}

function wp_transition_comment_status($new_status, $old_status, $comment) {
	$comment_statuses = array(
		0         => 'unapproved',
		'hold'    => 'unapproved', // wp_set_comment_status() uses "hold"
		1         => 'approved',
		'approve' => 'approved', // wp_set_comment_status() uses "approve"
	);
	if ( isset($comment_statuses[$new_status]) ) $new_status = $comment_statuses[$new_status];
	if ( isset($comment_statuses[$old_status]) ) $old_status = $comment_statuses[$old_status];

	if ( $new_status != $old_status ) {
		do_action( 'transition_comment_status', $new_status, $old_status, $comment );
		do_action( "comment_{$old_status}_to_{$new_status}", $comment );
	}

	do_action( "comment_{$new_status}_{$comment->comment_type}", $comment->comment_ID, $comment );
}

function wp_get_current_commenter() {
	$comment_author = '';
	if ( isset($_COOKIE['comment_author_'.COOKIEHASH]) )
		$comment_author = $_COOKIE['comment_author_'.COOKIEHASH];

	$comment_author_email = '';
	if ( isset($_COOKIE['comment_author_email_'.COOKIEHASH]) )
		$comment_author_email = $_COOKIE['comment_author_email_'.COOKIEHASH];

	$comment_author_url = '';
	if ( isset($_COOKIE['comment_author_url_'.COOKIEHASH]) )
		$comment_author_url = $_COOKIE['comment_author_url_'.COOKIEHASH];

	return apply_filters( 'wp_get_current_commenter', compact('comment_author', 'comment_author_email', 'comment_author_url') );
}

function wp_insert_comment( $commentdata ) {
	global $wpdb;
	$data = wp_unslash( $commentdata );

	$comment_author       = ! isset( $data['comment_author'] )       ? '' : $data['comment_author'];
	$comment_author_email = ! isset( $data['comment_author_email'] ) ? '' : $data['comment_author_email'];
	$comment_author_url   = ! isset( $data['comment_author_url'] )   ? '' : $data['comment_author_url'];
	$comment_author_IP    = ! isset( $data['comment_author_IP'] )    ? '' : $data['comment_author_IP'];

	$comment_date     = ! isset( $data['comment_date'] )     ? current_time( 'mysql' )            : $data['comment_date'];
	$comment_date_gmt = ! isset( $data['comment_date_gmt'] ) ? get_gmt_from_date( $comment_date ) : $data['comment_date_gmt'];

	$comment_post_ID  = ! isset( $data['comment_post_ID'] )  ? 0  : $data['comment_post_ID'];
	$comment_content  = ! isset( $data['comment_content'] )  ? '' : $data['comment_content'];
	$comment_karma    = ! isset( $data['comment_karma'] )    ? 0  : $data['comment_karma'];
	$comment_approved = ! isset( $data['comment_approved'] ) ? 1  : $data['comment_approved'];
	$comment_agent    = ! isset( $data['comment_agent'] )    ? '' : $data['comment_agent'];
	$comment_type     = ! isset( $data['comment_type'] )     ? '' : $data['comment_type'];
	$comment_parent   = ! isset( $data['comment_parent'] )   ? 0  : $data['comment_parent'];

	$user_id  = ! isset( $data['user_id'] ) ? 0 : $data['user_id'];

	$compacted = compact( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_karma', 'comment_approved', 'comment_agent', 'comment_type', 'comment_parent', 'user_id' );
	if ( ! $wpdb->insert( $wpdb->comments, $compacted ) ) {
		return false;
	}

	$id = (int) $wpdb->insert_id;

	if ( $comment_approved == 1 ) {
		wp_update_comment_count( $comment_post_ID );
	}
	$comment = get_comment( $id );

	// If metadata is provided, store it.
	if ( isset( $commentdata['comment_meta'] ) && is_array( $commentdata['comment_meta'] ) ) {
		foreach ( $commentdata['comment_meta'] as $meta_key => $meta_value ) {
			add_comment_meta( $comment->comment_ID, $meta_key, $meta_value, true );
		}
	}

	do_action( 'wp_insert_comment', $id, $comment );

	wp_cache_set( 'last_changed', microtime(), 'comment' );

	return $id;
}

function wp_filter_comment($commentdata) {
	if ( isset( $commentdata['user_ID'] ) ) {
		$commentdata['user_id'] = apply_filters( 'pre_user_id', $commentdata['user_ID'] );
	} elseif ( isset( $commentdata['user_id'] ) ) {
		/** This filter is documented in wp-includes/comment.php */
		$commentdata['user_id'] = apply_filters( 'pre_user_id', $commentdata['user_id'] );
	}

	$commentdata['comment_agent'] = apply_filters( 'pre_comment_user_agent', ( isset( $commentdata['comment_agent'] ) ? $commentdata['comment_agent'] : '' ) );
	$commentdata['comment_author'] = apply_filters( 'pre_comment_author_name', $commentdata['comment_author'] );
	$commentdata['comment_content'] = apply_filters( 'pre_comment_content', $commentdata['comment_content'] );
	$commentdata['comment_author_IP'] = apply_filters( 'pre_comment_user_ip', $commentdata['comment_author_IP'] );
	$commentdata['comment_author_url'] = apply_filters( 'pre_comment_author_url', $commentdata['comment_author_url'] );
	$commentdata['comment_author_email'] = apply_filters( 'pre_comment_author_email', $commentdata['comment_author_email'] );
	$commentdata['filtered'] = true;
	return $commentdata;
}

function wp_throttle_comment_flood($block, $time_lastcomment, $time_newcomment) {
	if ( $block ) // a plugin has already blocked... we'll let that decision stand
		return $block;
	if ( ($time_newcomment - $time_lastcomment) < 15 )
		return true;
	return false;
}

function wp_new_comment( $commentdata ) {
	global $wpdb;

	if ( isset( $commentdata['user_ID'] ) ) {
		$commentdata['user_id'] = $commentdata['user_ID'] = (int) $commentdata['user_ID'];
	}

	$prefiltered_user_id = ( isset( $commentdata['user_id'] ) ) ? (int) $commentdata['user_id'] : 0;

	$commentdata = apply_filters( 'preprocess_comment', $commentdata );

	$commentdata['comment_post_ID'] = (int) $commentdata['comment_post_ID'];
	if ( isset( $commentdata['user_ID'] ) && $prefiltered_user_id !== (int) $commentdata['user_ID'] ) {
		$commentdata['user_id'] = $commentdata['user_ID'] = (int) $commentdata['user_ID'];
	} elseif ( isset( $commentdata['user_id'] ) ) {
		$commentdata['user_id'] = (int) $commentdata['user_id'];
	}

	$commentdata['comment_parent'] = isset($commentdata['comment_parent']) ? absint($commentdata['comment_parent']) : 0;
	$parent_status = ( 0 < $commentdata['comment_parent'] ) ? wp_get_comment_status($commentdata['comment_parent']) : '';
	$commentdata['comment_parent'] = ( 'approved' == $parent_status || 'unapproved' == $parent_status ) ? $commentdata['comment_parent'] : 0;

	if ( ! isset( $commentdata['comment_author_IP'] ) ) {
		$commentdata['comment_author_IP'] = $_SERVER['REMOTE_ADDR'];
	}
	$commentdata['comment_author_IP'] = preg_replace( '/[^0-9a-fA-F:., ]/', '', $commentdata['comment_author_IP'] );

	if ( ! isset( $commentdata['comment_agent'] ) ) {
		$commentdata['comment_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT']: '';
	}
	$commentdata['comment_agent'] = substr( $commentdata['comment_agent'], 0, 254 );

	if ( empty( $commentdata['comment_date'] ) ) {
		$commentdata['comment_date'] = current_time('mysql');
	}

	if ( empty( $commentdata['comment_date_gmt'] ) ) {
		$commentdata['comment_date_gmt'] = current_time( 'mysql', 1 );
	}

	$commentdata = wp_filter_comment($commentdata);

	$commentdata['comment_approved'] = wp_allow_comment($commentdata);

	$comment_ID = wp_insert_comment($commentdata);
	if ( ! $comment_ID ) {
		$fields = array( 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content' );

		foreach ( $fields as $field ) {
			if ( isset( $commentdata[ $field ] ) ) {
				$commentdata[ $field ] = $wpdb->strip_invalid_text_for_column( $wpdb->comments, $field, $commentdata[ $field ] );
			}
		}

		$commentdata = wp_filter_comment( $commentdata );

		$commentdata['comment_approved'] = wp_allow_comment( $commentdata );

		$comment_ID = wp_insert_comment( $commentdata );
		if ( ! $comment_ID ) {
			return false;
		}
	}

	do_action( 'comment_post', $comment_ID, $commentdata['comment_approved'], $commentdata );

	return $comment_ID;
}

function wp_set_comment_status($comment_id, $comment_status, $wp_error = false) {
	global $wpdb;
	switch ( $comment_status ) {
		case 'hold':
		case '0':
			$status = '0';
			break;
		case 'approve':
		case '1':
			$status = '1';
			add_action( 'wp_set_comment_status', 'wp_new_comment_notify_postauthor' );
			break;
		case 'spam':
			$status = 'spam';
			break;
		case 'trash':
			$status = 'trash';
			break;
		default:
			return false;
	}

	$comment_old = clone get_comment($comment_id);

	if ( !$wpdb->update( $wpdb->comments, array('comment_approved' => $status), array( 'comment_ID' => $comment_old->comment_ID ) ) ) {
		if ( $wp_error )
			return new WP_Error('db_update_error', __('Could not update comment status'), $wpdb->last_error);
		else
			return false;
	}

	clean_comment_cache( $comment_old->comment_ID );

	$comment = get_comment( $comment_old->comment_ID );

	do_action( 'wp_set_comment_status', $comment->comment_ID, $comment_status );

	wp_transition_comment_status($comment_status, $comment_old->comment_approved, $comment);

	wp_update_comment_count($comment->comment_post_ID);

	return true;
}

function wp_update_comment($commentarr) {
	global $wpdb;
	$comment = get_comment($commentarr['comment_ID'], ARRAY_A);
	if ( empty( $comment ) ) {
		return 0;
	}
	if ( ! empty( $commentarr['comment_post_ID'] ) && ! get_post( $commentarr['comment_post_ID'] ) ) {
		return 0;
	}
	$comment = wp_slash($comment);
	$old_status = $comment['comment_approved'];
	$commentarr = array_merge($comment, $commentarr);
	$commentarr = wp_filter_comment( $commentarr );
	$data = wp_unslash( $commentarr );
	$data['comment_content'] = apply_filters( 'comment_save_pre', $data['comment_content'] );
	$data['comment_date_gmt'] = get_gmt_from_date( $data['comment_date'] );
	if ( ! isset( $data['comment_approved'] ) ) {
		$data['comment_approved'] = 1;
	} elseif ( 'hold' == $data['comment_approved'] ) {
		$data['comment_approved'] = 0;
	} elseif ( 'approve' == $data['comment_approved'] ) {
		$data['comment_approved'] = 1;
	}
	$comment_ID = $data['comment_ID'];
	$comment_post_ID = $data['comment_post_ID'];
	$keys = array( 'comment_post_ID', 'comment_content', 'comment_author', 'comment_author_email', 'comment_approved', 'comment_karma', 'comment_author_url', 'comment_date', 'comment_date_gmt', 'comment_type', 'comment_parent', 'user_id', 'comment_agent', 'comment_author_IP' );
	$data = wp_array_slice_assoc( $data, $keys );
	$rval = $wpdb->update( $wpdb->comments, $data, compact( 'comment_ID' ) );
	clean_comment_cache( $comment_ID );
	wp_update_comment_count( $comment_post_ID );
	do_action( 'edit_comment', $comment_ID );
	$comment = get_comment($comment_ID);
	wp_transition_comment_status($comment->comment_approved, $old_status, $comment);
	return $rval;
}

function wp_defer_comment_counting($defer=null) {
	static $_defer = false;

	if ( is_bool($defer) ) {
		$_defer = $defer;
		// flush any deferred counts
		if ( !$defer )
			wp_update_comment_count( null, true );
	}

	return $_defer;
}

function wp_update_comment_count($post_id, $do_deferred=false) {
	static $_deferred = array();

	if ( empty( $post_id ) && ! $do_deferred ) {
		return false;
	}

	if ( $do_deferred ) {
		$_deferred = array_unique($_deferred);
		foreach ( $_deferred as $i => $_post_id ) {
			wp_update_comment_count_now($_post_id);
			unset( $_deferred[$i] ); /** @todo Move this outside of the foreach and reset $_deferred to an array instead */
		}
	}

	if ( wp_defer_comment_counting() ) {
		$_deferred[] = $post_id;
		return true;
	}
	elseif ( $post_id ) {
		return wp_update_comment_count_now($post_id);
	}

}

function wp_update_comment_count_now($post_id) {
	global $wpdb;
	$post_id = (int) $post_id;
	if ( !$post_id )
		return false;

	wp_cache_delete( 'comments-0', 'counts' );
	wp_cache_delete( "comments-{$post_id}", 'counts' );

	if ( !$post = get_post($post_id) )
		return false;

	$old = (int) $post->comment_count;

	$new = apply_filters( 'pre_wp_update_comment_count_now', null, $old, $post_id );

	if ( is_null( $new ) ) {
		$new = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1'", $post_id ) );
	} else {
		$new = (int) $new;
	}

	$wpdb->update( $wpdb->posts, array('comment_count' => $new), array('ID' => $post_id) );

	clean_post_cache( $post );

	do_action( 'wp_update_comment_count', $post_id, $new, $old );
	/** This action is documented in wp-includes/post.php */
	do_action( 'edit_post', $post_id, $post );

	return true;
}

function discover_pingback_server_uri( $url, $deprecated = '' ) {
	if ( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '2.7' );

	$pingback_str_dquote = 'rel="pingback"';
	$pingback_str_squote = 'rel=\'pingback\'';

	$parsed_url = parse_url($url);

	if ( ! isset( $parsed_url['host'] ) )
		return false;

	$uploads_dir = wp_get_upload_dir();
	if ( 0 === strpos($url, $uploads_dir['baseurl']) )
		return false;

	$response = wp_safe_remote_head( $url, array( 'timeout' => 2, 'httpversion' => '1.0' ) );

	if ( is_wp_error( $response ) )
		return false;

	if ( wp_remote_retrieve_header( $response, 'x-pingback' ) )
		return wp_remote_retrieve_header( $response, 'x-pingback' );

	// Not an (x)html, sgml, or xml page, no use going further.
	if ( preg_match('#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' )) )
		return false;

	// Now do a GET since we're going to look in the html headers (and we're sure it's not a binary file)
	$response = wp_safe_remote_get( $url, array( 'timeout' => 2, 'httpversion' => '1.0' ) );

	if ( is_wp_error( $response ) )
		return false;

	$contents = wp_remote_retrieve_body( $response );

	$pingback_link_offset_dquote = strpos($contents, $pingback_str_dquote);
	$pingback_link_offset_squote = strpos($contents, $pingback_str_squote);
	if ( $pingback_link_offset_dquote || $pingback_link_offset_squote ) {
		$quote = ($pingback_link_offset_dquote) ? '"' : '\'';
		$pingback_link_offset = ($quote=='"') ? $pingback_link_offset_dquote : $pingback_link_offset_squote;
		$pingback_href_pos = @strpos($contents, 'href=', $pingback_link_offset);
		$pingback_href_start = $pingback_href_pos+6;
		$pingback_href_end = @strpos($contents, $quote, $pingback_href_start);
		$pingback_server_url_len = $pingback_href_end - $pingback_href_start;
		$pingback_server_url = substr($contents, $pingback_href_start, $pingback_server_url_len);

		// We may find rel="pingback" but an incomplete pingback URL
		if ( $pingback_server_url_len > 0 ) { // We got it!
			return $pingback_server_url;
		}
	}

	return false;
}

function do_all_pings() {
	global $wpdb;

	while ($ping = $wpdb->get_row("SELECT ID, post_content, meta_id FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_pingme' LIMIT 1")) {
		delete_metadata_by_mid( 'post', $ping->meta_id );
		pingback( $ping->post_content, $ping->ID );
	}

	while ($enclosure = $wpdb->get_row("SELECT ID, post_content, meta_id FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_encloseme' LIMIT 1")) {
		delete_metadata_by_mid( 'post', $enclosure->meta_id );
		do_enclose( $enclosure->post_content, $enclosure->ID );
	}

	$trackbacks = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE to_ping <> '' AND post_status = 'publish'");
	if ( is_array($trackbacks) )
		foreach ( $trackbacks as $trackback )
			do_trackbacks($trackback);

	//Do Update Services/Generic Pings
	generic_ping();
}

function generic_ping( $post_id = 0 ) {
	$services = get_option('ping_sites');
	$services = explode("\n", $services);
	foreach ( (array) $services as $service ) {
		$service = trim($service);
		if ( '' != $service )
			weblog_ping($service);
	}
	return $post_id;
}

function privacy_ping_filter($sites) {
	if ( '0' != get_option('blog_public') )
		return $sites;
	else
		return '';
}

function weblog_ping($server = '', $path = '') {
	global $wp_version;
	include_once(ABSPATH . WPINC . '/class-IXR.php');
	include_once(ABSPATH . WPINC . '/class-wp-http-ixr-client.php');

	// using a timeout of 3 seconds should be enough to cover slow servers
	$client = new WP_HTTP_IXR_Client($server, ((!strlen(trim($path)) || ('/' == $path)) ? false : $path));
	$client->timeout = 3;
	$client->useragent .= ' -- WordPress/'.$wp_version;

	// when set to true, this outputs debug messages by itself
	$client->debug = false;
	$home = trailingslashit( home_url() );
	if ( !$client->query('weblogUpdates.extendedPing', get_option('blogname'), $home, get_bloginfo('rss2_url') ) ) // then try a normal ping
		$client->query('weblogUpdates.ping', get_option('blogname'), $home);
}

function clean_comment_cache($ids) {
	foreach ( (array) $ids as $id ) {
		wp_cache_delete( $id, 'comment' );
		do_action( 'clean_comment_cache', $id );
	}

	wp_cache_set( 'last_changed', microtime(), 'comment' );
}

function update_comment_cache( $comments, $update_meta_cache = true ) {
	foreach ( (array) $comments as $comment )
		wp_cache_add($comment->comment_ID, $comment, 'comment');

	if ( $update_meta_cache ) {
		// Avoid `wp_list_pluck()` in case `$comments` is passed by reference.
		$comment_ids = array();
		foreach ( $comments as $comment ) {
			$comment_ids[] = $comment->comment_ID;
		}
		update_meta_cache( 'comment', $comment_ids );
	}
}

function _prime_comment_caches( $comment_ids, $update_meta_cache = true ) {
	global $wpdb;

	$non_cached_ids = _get_non_cached_ids( $comment_ids, 'comment' );
	if ( !empty( $non_cached_ids ) ) {
		$fresh_comments = $wpdb->get_results( sprintf( "SELECT $wpdb->comments.* FROM $wpdb->comments WHERE comment_ID IN (%s)", join( ",", array_map( 'intval', $non_cached_ids ) ) ) );

		update_comment_cache( $fresh_comments, $update_meta_cache );
	}
}

function _close_comments_for_old_posts( $posts, $query ) {
	if ( empty( $posts ) || ! $query->is_singular() || ! get_option( 'close_comments_for_old_posts' ) )
		return $posts;

	$post_types = apply_filters( 'close_comments_for_post_types', array( 'post' ) );
	if ( ! in_array( $posts[0]->post_type, $post_types ) )
		return $posts;

	$days_old = (int) get_option( 'close_comments_days_old' );
	if ( ! $days_old )
		return $posts;

	if ( time() - strtotime( $posts[0]->post_date_gmt ) > ( $days_old * DAY_IN_SECONDS ) ) {
		$posts[0]->comment_status = 'closed';
		$posts[0]->ping_status = 'closed';
	}

	return $posts;
}

function _close_comments_for_old_post( $open, $post_id ) {
	if ( ! $open )
		return $open;

	if ( !get_option('close_comments_for_old_posts') )
		return $open;

	$days_old = (int) get_option('close_comments_days_old');
	if ( !$days_old )
		return $open;

	$post = get_post($post_id);

	/** This filter is documented in wp-includes/comment.php */
	$post_types = apply_filters( 'close_comments_for_post_types', array( 'post' ) );
	if ( ! in_array( $post->post_type, $post_types ) )
		return $open;

	// Undated drafts should not show up as comments closed.
	if ( '0000-00-00 00:00:00' === $post->post_date_gmt ) {
		return $open;
	}

	if ( time() - strtotime( $post->post_date_gmt ) > ( $days_old * DAY_IN_SECONDS ) )
		return false;

	return $open;
}

function wp_handle_comment_submission( $comment_data ) {

	$comment_post_ID = $comment_parent = 0;
	$comment_author = $comment_author_email = $comment_author_url = $comment_content = $_wp_unfiltered_html_comment = null;

	if ( isset( $comment_data['comment_post_ID'] ) ) {
		$comment_post_ID = (int) $comment_data['comment_post_ID'];
	}
	if ( isset( $comment_data['author'] ) && is_string( $comment_data['author'] ) ) {
		$comment_author = trim( strip_tags( $comment_data['author'] ) );
	}
	if ( isset( $comment_data['email'] ) && is_string( $comment_data['email'] ) ) {
		$comment_author_email = trim( $comment_data['email'] );
	}
	if ( isset( $comment_data['url'] ) && is_string( $comment_data['url'] ) ) {
		$comment_author_url = trim( $comment_data['url'] );
	}
	if ( isset( $comment_data['comment'] ) && is_string( $comment_data['comment'] ) ) {
		$comment_content = trim( $comment_data['comment'] );
	}
	if ( isset( $comment_data['comment_parent'] ) ) {
		$comment_parent = absint( $comment_data['comment_parent'] );
	}
	if ( isset( $comment_data['_wp_unfiltered_html_comment'] ) && is_string( $comment_data['_wp_unfiltered_html_comment'] ) ) {
		$_wp_unfiltered_html_comment = trim( $comment_data['_wp_unfiltered_html_comment'] );
	}

	$post = get_post( $comment_post_ID );

	if ( empty( $post->comment_status ) ) {
		do_action( 'comment_id_not_found', $comment_post_ID );

		return new WP_Error( 'comment_id_not_found' );

	}

	$status = get_post_status( $post );

	if ( ( 'private' == $status ) && ! current_user_can( 'read_post', $comment_post_ID ) ) {
		return new WP_Error( 'comment_id_not_found' );
	}

	$status_obj = get_post_status_object( $status );

	if ( ! comments_open( $comment_post_ID ) ) {
		do_action( 'comment_closed', $comment_post_ID );

		return new WP_Error( 'comment_closed', __( 'Sorry, comments are closed for this item.' ), 403 );

	} elseif ( 'trash' == $status ) {
		do_action( 'comment_on_trash', $comment_post_ID );

		return new WP_Error( 'comment_on_trash' );

	} elseif ( ! $status_obj->public && ! $status_obj->private ) {
		do_action( 'comment_on_draft', $comment_post_ID );

		return new WP_Error( 'comment_on_draft' );

	} elseif ( post_password_required( $comment_post_ID ) ) {
		do_action( 'comment_on_password_protected', $comment_post_ID );

		return new WP_Error( 'comment_on_password_protected' );

	} else {
		do_action( 'pre_comment_on_post', $comment_post_ID );

	}

	// If the user is logged in
	$user = wp_get_current_user();
	if ( $user->exists() ) {
		if ( empty( $user->display_name ) ) {
			$user->display_name=$user->user_login;
		}
		$comment_author       = $user->display_name;
		$comment_author_email = $user->user_email;
		$comment_author_url   = $user->user_url;
		$user_ID              = $user->ID;
		if ( current_user_can( 'unfiltered_html' ) ) {
			if ( ! isset( $comment_data['_wp_unfiltered_html_comment'] )
				|| ! wp_verify_nonce( $comment_data['_wp_unfiltered_html_comment'], 'unfiltered-html-comment_' . $comment_post_ID )
			) {
				kses_remove_filters(); // start with a clean slate
				kses_init_filters(); // set up the filters
			}
		}
	} else {
		if ( get_option( 'comment_registration' ) ) {
			return new WP_Error( 'not_logged_in', __( 'Sorry, you must be logged in to post a comment.' ), 403 );
		}
	}

	$comment_type = '';
	$max_lengths = wp_get_comment_fields_max_lengths();

	if ( get_option( 'require_name_email' ) && ! $user->exists() ) {
		if ( 6 > strlen( $comment_author_email ) || '' == $comment_author ) {
			return new WP_Error( 'require_name_email', __( '<strong>ERROR</strong>: please fill the required fields (name, email).' ), 200 );
		} elseif ( ! is_email( $comment_author_email ) ) {
			return new WP_Error( 'require_valid_email', __( '<strong>ERROR</strong>: please enter a valid email address.' ), 200 );
		}
	}

	if ( isset( $comment_author ) && $max_lengths['comment_author'] < mb_strlen( $comment_author, '8bit' ) ) {
		return new WP_Error( 'comment_author_column_length', __( '<strong>ERROR</strong>: your name is too long.' ), 200 );
	}

	if ( isset( $comment_author_email ) && $max_lengths['comment_author_email'] < strlen( $comment_author_email ) ) {
		return new WP_Error( 'comment_author_email_column_length', __( '<strong>ERROR</strong>: your email address is too long.' ), 200 );
	}

	if ( isset( $comment_author_url ) && $max_lengths['comment_author_url'] < strlen( $comment_author_url ) ) {
		return new WP_Error( 'comment_author_url_column_length', __( '<strong>ERROR</strong>: your url is too long.' ), 200 );
	}

	if ( '' == $comment_content ) {
		return new WP_Error( 'require_valid_comment', __( '<strong>ERROR</strong>: please type a comment.' ), 200 );
	} elseif ( $max_lengths['comment_content'] < mb_strlen( $comment_content, '8bit' ) ) {
		return new WP_Error( 'comment_content_column_length', __( '<strong>ERROR</strong>: your comment is too long.' ), 200 );
	}

	$commentdata = compact(
		'comment_post_ID',
		'comment_author',
		'comment_author_email',
		'comment_author_url',
		'comment_content',
		'comment_type',
		'comment_parent',
		'user_ID'
	);

	$comment_id = wp_new_comment( wp_slash( $commentdata ) );
	if ( ! $comment_id ) {
		return new WP_Error( 'comment_save_error', __( '<strong>ERROR</strong>: The comment could not be saved. Please try again later.' ), 500 );
	}

	return get_comment( $comment_id );

}
