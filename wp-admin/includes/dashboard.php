<?php

function wp_dashboard_setup() {
	global $wp_registered_widgets, $wp_registered_widget_controls, $wp_dashboard_control_callbacks;
	$wp_dashboard_control_callbacks = array();
	$screen = get_current_screen();
	$response = wp_check_browser_version();
	if ( $response && $response['upgrade'] ) {
		add_filter( 'postbox_classes_dashboard_dashboard_browser_nag', 'dashboard_browser_nag_class' );
		if ( $response['insecure'] )
			wp_add_dashboard_widget( 'dashboard_browser_nag', 'You are using an insecure browser!', 'wp_dashboard_browser_nag' );
		else
			wp_add_dashboard_widget( 'dashboard_browser_nag', 'Your browser is out of date!', 'wp_dashboard_browser_nag' );
	}
	if ( is_blog_admin() && current_user_can('edit_posts') )
		wp_add_dashboard_widget( 'dashboard_right_now', 'At a Glance', 'wp_dashboard_right_now' );
	if ( is_blog_admin() ) {
		wp_add_dashboard_widget( 'dashboard_activity', 'Activity', 'wp_dashboard_site_activity' );
	}
	if ( is_blog_admin() && current_user_can( get_post_type_object( 'post' )->cap->create_posts ) ) {
		$quick_draft_title = sprintf( '<span class="hide-if-no-js">%1$s</span> <span class="hide-if-js">%2$s</span>', 'Quick Draft', 'Drafts' );
		wp_add_dashboard_widget( 'dashboard_quick_press', $quick_draft_title, 'wp_dashboard_quick_press' );
	}

	//wp_add_dashboard_widget( 'dashboard_primary', __( 'WordPress News' ), 'wp_dashboard_primary' );

	do_action( 'wp_dashboard_setup' );
	$dashboard_widgets = apply_filters( 'wp_dashboard_widgets', array() );

	foreach ( $dashboard_widgets as $widget_id ) {
		$name = empty( $wp_registered_widgets[$widget_id]['all_link'] ) ? $wp_registered_widgets[$widget_id]['name'] : $wp_registered_widgets[$widget_id]['name'] . " <a href='{$wp_registered_widgets[$widget_id]['all_link']}' class='edit-box open-box'>" . __('View all') . '</a>';
		wp_add_dashboard_widget( $widget_id, $name, $wp_registered_widgets[$widget_id]['callback'], $wp_registered_widget_controls[$widget_id]['callback'] );
	}

	if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset($_POST['widget_id']) ) {
		check_admin_referer( 'edit-dashboard-widget_' . $_POST['widget_id'], 'dashboard-widget-nonce' );
		ob_start();
		wp_dashboard_trigger_widget_control( $_POST['widget_id'] );
		ob_end_clean();
		wp_redirect( remove_query_arg( 'edit' ) );
		exit;
	}

	do_action( 'do_meta_boxes', $screen->id, 'normal', '' );
	do_action( 'do_meta_boxes', $screen->id, 'side', '' );
}

function wp_add_dashboard_widget( $widget_id, $widget_name, $callback, $control_callback = null, $callback_args = null ) {
	$screen = get_current_screen();
	global $wp_dashboard_control_callbacks;

	if ( $control_callback && current_user_can( 'edit_dashboard' ) && is_callable( $control_callback ) ) {
		$wp_dashboard_control_callbacks[$widget_id] = $control_callback;
		if ( isset( $_GET['edit'] ) && $widget_id == $_GET['edit'] ) {
			list($url) = explode( '#', add_query_arg( 'edit', false ), 2 );
			$widget_name .= ' <span class="postbox-title-action"><a href="' . esc_url( $url ) . '">Cancel</a></span>';
			$callback = '_wp_dashboard_control_callback';
		} else {
			list($url) = explode( '#', add_query_arg( 'edit', $widget_id ), 2 );
			$widget_name .= ' <span class="postbox-title-action"><a href="' . esc_url( "$url#$widget_id" ) . '" class="edit-box open-box">Configure</a></span>';
		}
	}

	$side_widgets = array( 'dashboard_quick_press', 'dashboard_primary' );

	$location = 'normal';
	if ( in_array($widget_id, $side_widgets) )
		$location = 'side';

	$priority = 'core';
	if ( 'dashboard_browser_nag' === $widget_id )
		$priority = 'high';

	add_meta_box( $widget_id, $widget_name, $callback, $screen, $location, $priority, $callback_args );
}

function _wp_dashboard_control_callback( $dashboard, $meta_box ) {
	echo '<form method="post" class="dashboard-widget-control-form">';
	wp_dashboard_trigger_widget_control( $meta_box['id'] );
	wp_nonce_field( 'edit-dashboard-widget_' . $meta_box['id'], 'dashboard-widget-nonce' );
	echo '<input type="hidden" name="widget_id" value="' . esc_attr($meta_box['id']) . '" />';
	submit_button( 'Submit' );
	echo '</form>';
}

function wp_dashboard() {
	$screen = get_current_screen();
	$columns = absint( $screen->get_columns() );
	$columns_css = '';
	if ( $columns ) {
		$columns_css = " columns-$columns";
	}

?>
<div id="dashboard-widgets" class="metabox-holder<?php echo $columns_css; ?>">
	<div id="postbox-container-1" class="postbox-container">
	<?php do_meta_boxes( $screen->id, 'normal', '' ); ?>
	</div>
	<div id="postbox-container-2" class="postbox-container">
	<?php do_meta_boxes( $screen->id, 'side', '' ); ?>
	</div>
	<div id="postbox-container-3" class="postbox-container">
	<?php do_meta_boxes( $screen->id, 'column3', '' ); ?>
	</div>
	<div id="postbox-container-4" class="postbox-container">
	<?php do_meta_boxes( $screen->id, 'column4', '' ); ?>
	</div>
</div>

<?php
	wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
	wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
}

function wp_dashboard_right_now() {
?>
	<div class="main">
	<ul>
	<?php
	foreach ( array( 'post', 'page' ) as $post_type ) {
		$num_posts = wp_count_posts( $post_type );
		if ( $num_posts && $num_posts->publish ) {
			if ( 'post' == $post_type ) {
				$text = _n( '%s Post', '%s Posts', $num_posts->publish );
			} else {
				$text = _n( '%s Page', '%s Pages', $num_posts->publish );
			}
			$text = sprintf( $text, number_format_i18n( $num_posts->publish ) );
			$post_type_object = get_post_type_object( $post_type );
			if ( $post_type_object && current_user_can( $post_type_object->cap->edit_posts ) ) {
				printf( '<li class="%1$s-count"><a href="edit.php?post_type=%1$s">%2$s</a></li>', $post_type, $text );
			} else {
				printf( '<li class="%1$s-count"><span>%2$s</span></li>', $post_type, $text );
			}

		}
	}
	// Comments
	$num_comm = wp_count_comments();
	if ( $num_comm && $num_comm->approved ) {
		$text = sprintf( _n( '%s Comment', '%s Comments', $num_comm->approved ), number_format_i18n( $num_comm->approved ) );
		?>
		<li class="comment-count"><a href="edit-comments.php"><?php echo $text; ?></a></li>
		<?php
		$moderated_comments_count_i18n = number_format_i18n( $num_comm->moderated );
		/* translators: Number of comments in moderation */
		$text = sprintf( _nx( '%s in moderation', '%s in moderation', $num_comm->moderated, 'comments' ), $moderated_comments_count_i18n );
		/* translators: Number of comments in moderation */
		$aria_label = sprintf( _nx( '%s comment in moderation', '%s comments in moderation', $num_comm->moderated, 'comments' ), $moderated_comments_count_i18n );
		?>
		<li class="comment-mod-count<?php
			if ( ! $num_comm->moderated ) {
				echo ' hidden';
			}
		?>"><a href="edit-comments.php?comment_status=moderated" aria-label="<?php esc_attr_e( $aria_label ); ?>"><?php echo $text; ?></a></li>
		<?php
	}

	$elements = apply_filters( 'dashboard_glance_items', array() );

	if ( $elements ) {
		echo '<li>' . implode( "</li>\n<li>", $elements ) . "</li>\n";
	}

	?>
	</ul>
	<?php
	update_right_now_message();

	if ( ! is_network_admin() && ! is_user_admin() && current_user_can( 'manage_options' ) && '0' == get_option( 'blog_public' ) ) {

		$title = apply_filters( 'privacy_on_link_title', '' );

		$content = apply_filters( 'privacy_on_link_text' , 'Search Engines Discouraged' );
		$title_attr = '' === $title ? '' : " title='$title'";

		echo "<p><a href='options-reading.php'$title_attr>$content</a></p>";
	}
	?>
	</div>
	<?php

	ob_start();
	do_action( 'rightnow_end' );
	do_action( 'activity_box_end' );
	$actions = ob_get_clean();

	if ( !empty( $actions ) ) : ?>
	<div class="sub">
		<?php echo $actions; ?>
	</div>
	<?php endif;
}

function wp_dashboard_quick_press( $error_msg = false ) {
	global $post_ID;
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$last_post_id = (int) get_user_option( 'dashboard_quick_press_last_post_id' );
	if ( $last_post_id ) {
		$post = get_post( $last_post_id );
		if ( empty( $post ) || $post->post_status != 'auto-draft' ) {
			$post = get_default_post_to_edit( 'post', true );
			update_user_option( get_current_user_id(), 'dashboard_quick_press_last_post_id', (int) $post->ID );
		} else {
			$post->post_title = '';
		}
	} else {
		$post = get_default_post_to_edit( 'post' , true);
		$user_id = get_current_user_id();
		if ( ! ( is_super_admin( $user_id ) && ! in_array( 0, array_keys( get_blogs_of_user( $user_id ) ) ) ) )
			update_user_option( $user_id, 'dashboard_quick_press_last_post_id', (int) $post->ID ); // Save post_ID
	}

	$post_ID = (int) $post->ID;
?>

	<form name="post" action="<?php echo esc_url( admin_url( 'post.php' ) ); ?>" method="post" id="quick-press" class="initial-form hide-if-no-js">

		<?php if ( $error_msg ) : ?>
		<div class="error"><?php echo $error_msg; ?></div>
		<?php endif; ?>

		<div class="input-text-wrap" id="title-wrap">
			<label class="screen-reader-text prompt" for="title" id="title-prompt-text">
				<?php echo apply_filters( 'enter_title_here', 'Title', $post ); ?>
			</label>
			<input type="text" name="post_title" id="title" autocomplete="off" />
		</div>

		<div class="textarea-wrap" id="description-wrap">
			<label class="screen-reader-text prompt" for="content" id="content-prompt-text">What&#8217;s on your mind?</label>
			<textarea name="content" id="content" class="mceEditor" rows="3" cols="15" autocomplete="off"></textarea>
		</div>
		<p class="submit">
			<input type="hidden" name="action" id="quickpost-action" value="post-quickdraft-save" />
			<input type="hidden" name="post_ID" value="<?php echo $post_ID; ?>" />
			<input type="hidden" name="post_type" value="post" />
			<?php wp_nonce_field( 'add-post' ); ?>
			<?php submit_button( 'Save Draft', 'primary', 'save', false, array( 'id' => 'save-post' ) ); ?>
			<br class="clear" />
		</p>

	</form>
	<?php
	wp_dashboard_recent_drafts();
}

function wp_dashboard_recent_drafts( $drafts = false ) {
	if ( ! $drafts ) {
		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'draft',
			'author'         => get_current_user_id(),
			'posts_per_page' => 4,
			'orderby'        => 'modified',
			'order'          => 'DESC'
		);

		$query_args = apply_filters( 'dashboard_recent_drafts_query_args', $query_args );

		$drafts = get_posts( $query_args );
		if ( ! $drafts ) {
			return;
 		}
 	}

	echo '<div class="drafts">';
	if ( count( $drafts ) > 3 ) {
		echo '<p class="view-all"><a href="' . esc_url( admin_url( 'edit.php?post_status=draft' ) ) . '" aria-label="View all drafts">' . "View all</a></p>\n";
 	}
	echo '<h2 class="hide-if-no-js">Drafts' . "</h2>\n<ul>";

	$drafts = array_slice( $drafts, 0, 3 );
	foreach ( $drafts as $draft ) {
		$url = get_edit_post_link( $draft->ID );
		$title = _draft_or_post_title( $draft->ID );
		echo "<li>\n";
		/* translators: %s: post title */
		echo '<div class="draft-title"><a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( 'Edit &#8220;%s&#8221;', $title ) ) . '">' . esc_html( $title ) . '</a>';
		echo '<time datetime="' . get_the_time( 'c', $draft ) . '">' . get_the_time( 'F j, Y', $draft ) . '</time></div>';
		if ( $the_content = wp_trim_words( $draft->post_content, 10 ) ) {
			echo '<p>' . $the_content . '</p>';
 		}
		echo "</li>\n";
 	}
	echo "</ul>\n</div>";
}

function _wp_dashboard_recent_comments_row( &$comment, $show_date = true ) {
	$GLOBALS['comment'] = clone $comment;

	if ( $comment->comment_post_ID > 0 ) {

		$comment_post_title = _draft_or_post_title( $comment->comment_post_ID );
		$comment_post_url = get_the_permalink( $comment->comment_post_ID );
		$comment_post_link = "<a href='$comment_post_url'>$comment_post_title</a>";
	} else {
		$comment_post_link = '';
	}

	$actions_string = '';
	if ( current_user_can( 'edit_comment', $comment->comment_ID ) ) {
		$actions = array(
			'approve' => '', 'unapprove' => '',
			'reply' => '',
			'edit' => '',
			'spam' => '',
			'trash' => '', 'delete' => '',
			'view' => '',
		);

		$del_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "delete-comment_$comment->comment_ID" ) );
		$approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-comment_$comment->comment_ID" ) );

		$approve_url = esc_url( "comment.php?action=approvecomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$approve_nonce" );
		$unapprove_url = esc_url( "comment.php?action=unapprovecomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$approve_nonce" );
		$spam_url = esc_url( "comment.php?action=spamcomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$del_nonce" );
		$trash_url = esc_url( "comment.php?action=trashcomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$del_nonce" );
		$delete_url = esc_url( "comment.php?action=deletecomment&p=$comment->comment_post_ID&c=$comment->comment_ID&$del_nonce" );

		$actions['approve'] = "<a href='$approve_url' data-wp-lists='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=approved' class='vim-a' aria-label='" . esc_attr__( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a>';
		$actions['unapprove'] = "<a href='$unapprove_url' data-wp-lists='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=unapproved' class='vim-u' aria-label='" . esc_attr__( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a>';
		$actions['edit'] = "<a href='comment.php?action=editcomment&amp;c={$comment->comment_ID}' aria-label='" . esc_attr__( 'Edit this comment' ) . "'>". __( 'Edit' ) . '</a>';
		$actions['reply'] = '<a onclick="window.commentReply && commentReply.open(\'' . $comment->comment_ID . '\',\''.$comment->comment_post_ID.'\');return false;" class="vim-r hide-if-no-js" aria-label="' . esc_attr__( 'Reply to this comment' ) . '" href="#">' . __( 'Reply' ) . '</a>';
		$actions['spam'] = "<a href='$spam_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::spam=1' class='vim-s vim-destructive' aria-label='" . esc_attr__( 'Mark this comment as spam' ) . "'>" . /* translators: mark as spam link */ _x( 'Spam', 'verb' ) . '</a>';

		if ( ! EMPTY_TRASH_DAYS ) {
			$actions['delete'] = "<a href='$delete_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::trash=1' class='delete vim-d vim-destructive' aria-label='" . esc_attr__( 'Delete this comment permanently' ) . "'>" . __( 'Delete Permanently' ) . '</a>';
		} else {
			$actions['trash'] = "<a href='$trash_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::trash=1' class='delete vim-d vim-destructive' aria-label='" . esc_attr__( 'Move this comment to the Trash' ) . "'>" . _x( 'Trash', 'verb' ) . '</a>';
		}

		if ( '1' === $comment->comment_approved ) {
			$actions['view'] = '<a class="comment-link" href="' . esc_url( get_comment_link( $comment ) ) . '" aria-label="View this comment">View</a>';
		}

		$actions = apply_filters( 'comment_row_actions', array_filter($actions), $comment );

		$i = 0;
		foreach ( $actions as $action => $link ) {
			++$i;
			( ( ('approve' == $action || 'unapprove' == $action) && 2 === $i ) || 1 === $i ) ? $sep = '' : $sep = ' | ';

			// Reply and quickedit need a hide-if-no-js span
			if ( 'reply' == $action || 'quickedit' == $action )
				$action .= ' hide-if-no-js';

			$actions_string .= "<span class='$action'>$sep$link</span>";
		}
	}
?>

		<li id="comment-<?php echo $comment->comment_ID; ?>" <?php comment_class( array( 'comment-item', wp_get_comment_status( $comment ) ), $comment ); ?>>

			<?php echo get_avatar( $comment, 50, 'mystery' ); ?>

			<?php if ( !$comment->comment_type || 'comment' == $comment->comment_type ) : ?>

			<div class="dashboard-comment-wrap has-row-actions">
			<p class="comment-meta">
			<?php
				if ( $comment_post_link ) {
					printf(
						'From %1$s on %2$s %3$s',
						'<cite class="comment-author">' . get_comment_author_link( $comment ) . '</cite>',
						$comment_post_link,
						'<span class="approve">[Pending]</span>'
					);
				} else {
					printf(
						'From %1$s %2$s',
						'<cite class="comment-author">' . get_comment_author_link( $comment ) . '</cite>',
						'<span class="approve">[Pending]</span>'
					);
				}
			?>
			</p>

			<?php
			else :
				$type = ucwords( $comment->comment_type );
				$type = esc_html( $type );
			?>
			<div class="dashboard-comment-wrap has-row-actions">
			<p class="comment-meta">
			<?php
				if ( $comment_post_link ) {
					printf(
						'%1$s on %2$s %3$s',
						"<strong>$type</strong>",
						$comment_post_link,
						'<span class="approve">[Pending]</span>'
					);
				} else {
					printf(
						'%1$s %2$s',
						"<strong>$type</strong>",
						'<span class="approve">[Pending]</span>'
					);
				}
			?>
			</p>
			<p class="comment-author"><?php comment_author_link( $comment ); ?></p>

			<?php endif; ?>
			<blockquote><p><?php comment_excerpt( $comment ); ?></p></blockquote>
			<?php if ( $actions_string ) : ?>
			<p class="row-actions"><?php echo $actions_string; ?></p>
			<?php endif; ?>
			</div>
		</li>
<?php
	$GLOBALS['comment'] = null;
}

function wp_dashboard_site_activity() {

	echo '<div id="activity-widget">';

	$future_posts = wp_dashboard_recent_posts( array(
		'max'     => 5,
		'status'  => 'future',
		'order'   => 'ASC',
		'title'   => 'Publishing Soon',
		'id'      => 'future-posts',
	) );
	$recent_posts = wp_dashboard_recent_posts( array(
		'max'     => 5,
		'status'  => 'publish',
		'order'   => 'DESC',
		'title'   => 'Recently Published',
		'id'      => 'published-posts',
	) );

	$recent_comments = wp_dashboard_recent_comments();

	if ( !$future_posts && !$recent_posts && !$recent_comments ) {
		echo '<div class="no-activity">';
		echo '<p class="smiley"></p>';
		echo '<p>No activity yet!</p>';
		echo '</div>';
	}

	echo '</div>';
}

function wp_dashboard_recent_posts( $args ) {
	$query_args = array(
		'post_type'      => 'post',
		'post_status'    => $args['status'],
		'orderby'        => 'date',
		'order'          => $args['order'],
		'posts_per_page' => intval( $args['max'] ),
		'no_found_rows'  => true,
		'cache_results'  => false,
		'perm'           => ( 'future' === $args['status'] ) ? 'editable' : 'readable',
	);

	$query_args = apply_filters( 'dashboard_recent_posts_query_args', $query_args );
	$posts = new WP_Query( $query_args );

	if ( $posts->have_posts() ) {

		echo '<div id="' . $args['id'] . '" class="activity-block">';

		echo '<h3>' . $args['title'] . '</h3>';

		echo '<ul>';

		$today    = date( 'Y-m-d', current_time( 'timestamp' ) );
		$tomorrow = date( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) );

		while ( $posts->have_posts() ) {
			$posts->the_post();

			$time = get_the_time( 'U' );
			if ( date( 'Y-m-d', $time ) == $today ) {
				$relative = 'Today';
			} elseif ( date( 'Y-m-d', $time ) == $tomorrow ) {
				$relative = 'Tomorrow';
			} elseif ( date( 'Y', $time ) !== date( 'Y', current_time( 'timestamp' ) ) ) {
				$relative = date_i18n( 'M jS Y', $time );
			} else {
				$relative = date_i18n( 'M jS', $time );
			}
			$recent_post_link = current_user_can( 'edit_post', get_the_ID() ) ? get_edit_post_link() : get_permalink();
			$draft_or_post_title = _draft_or_post_title();
			printf(
				'<li><span>%1$s</span> <a href="%2$s" aria-label="%3$s">%4$s</a></li>',
				sprintf( '%1$s, %2$s', $relative, get_the_time() ),
				$recent_post_link,
				esc_attr( sprintf( 'Edit &#8220;%s&#8221;', $draft_or_post_title ) ),
				$draft_or_post_title
			);
		}

		echo '</ul>';
		echo '</div>';

	} else {
		return false;
	}

	wp_reset_postdata();

	return true;
}

function wp_dashboard_recent_comments( $total_items = 5 ) {
	$comments = array();

	$comments_query = array(
		'number' => $total_items * 5,
		'offset' => 0
	);
	if ( ! current_user_can( 'edit_posts' ) )
		$comments_query['status'] = 'approve';

	while ( count( $comments ) < $total_items && $possible = get_comments( $comments_query ) ) {
		if ( ! is_array( $possible ) ) {
			break;
		}
		foreach ( $possible as $comment ) {
			if ( ! current_user_can( 'read_post', $comment->comment_post_ID ) )
				continue;
			$comments[] = $comment;
			if ( count( $comments ) == $total_items )
				break 2;
		}
		$comments_query['offset'] += $comments_query['number'];
		$comments_query['number'] = $total_items * 10;
	}

	if ( $comments ) {
		echo '<div id="latest-comments" class="activity-block">';
		echo '<h3>Recent Comments</h3>';

		echo '<ul id="the-comment-list" data-wp-lists="list:comment">';
		foreach ( $comments as $comment )
			_wp_dashboard_recent_comments_row( $comment );
		echo '</ul>';

		if ( current_user_can( 'edit_posts' ) ) {
			echo '<h3 class="screen-reader-text">View more comments</h3>';
			_get_list_table( 'WP_Comments_List_Table' )->views();
		}

		wp_comment_reply( -1, false, 'dashboard', false );
		wp_comment_trashnotice();

		echo '</div>';
	} else {
		return false;
	}
	return true;
}

function wp_dashboard_trigger_widget_control( $widget_control_id = false ) {
	global $wp_dashboard_control_callbacks;

	if ( is_scalar($widget_control_id) && $widget_control_id && isset($wp_dashboard_control_callbacks[$widget_control_id]) && is_callable($wp_dashboard_control_callbacks[$widget_control_id]) ) {
		call_user_func( $wp_dashboard_control_callbacks[$widget_control_id], '', array( 'id' => $widget_control_id, 'callback' => $wp_dashboard_control_callbacks[$widget_control_id] ) );
	}
}

function wp_dashboard_plugins_output( $rss, $args = array() ) {
	$popular = fetch_feed( $args['url']['popular'] );

	if ( false === $plugin_slugs = get_transient( 'plugin_slugs' ) ) {
		$plugin_slugs = array_keys( get_plugins() );
		set_transient( 'plugin_slugs', $plugin_slugs, DAY_IN_SECONDS );
	}

	echo '<ul>';

	foreach ( array( $popular ) as $feed ) {
		if ( is_wp_error( $feed ) || ! $feed->get_item_quantity() )
			continue;

		$items = $feed->get_items(0, 5);

		// Pick a random, non-installed plugin
		while ( true ) {
			// Abort this foreach loop iteration if there's no plugins left of this type
			if ( 0 == count($items) )
				continue 2;

			$item_key = array_rand($items);
			$item = $items[$item_key];

			list($link, $frag) = explode( '#', $item->get_link() );

			$link = esc_url($link);
			if ( preg_match( '|/([^/]+?)/?$|', $link, $matches ) )
				$slug = $matches[1];
			else {
				unset( $items[$item_key] );
				continue;
			}

			// Is this random plugin's slug already installed? If so, try again.
			reset( $plugin_slugs );
			foreach ( $plugin_slugs as $plugin_slug ) {
				if ( $slug == substr( $plugin_slug, 0, strlen( $slug ) ) ) {
					unset( $items[$item_key] );
					continue 2;
				}
			}

			// If we get to this point, then the random plugin isn't installed and we can stop the while().
			break;
		}

		// Eliminate some common badly formed plugin descriptions
		while ( ( null !== $item_key = array_rand($items) ) && false !== strpos( $items[$item_key]->get_description(), 'Plugin Name:' ) )
			unset($items[$item_key]);

		if ( !isset($items[$item_key]) )
			continue;

		$raw_title = $item->get_title();

		$ilink = wp_nonce_url('plugin-install.php?tab=plugin-information&plugin=' . $slug, 'install-plugin_' . $slug) . '&amp;TB_iframe=true&amp;width=600&amp;height=800';
		echo '<li class="dashboard-news-plugin"><span>' . __( 'Popular Plugin' ) . ':</span> ' . esc_html( $raw_title ) .
			'&nbsp;<a href="' . $ilink . '" class="thickbox open-plugin-details-modal" aria-label="' .
			/* translators: %s: plugin name */
			esc_attr( sprintf( __( 'Install %s' ), $raw_title ) ) . '">(' . __( 'Install' ) . ')</a></li>';

		$feed->__destruct();
		unset( $feed );
	}

	echo '</ul>';
}

function wp_dashboard_quota() {
	if ( !is_multisite() || !current_user_can( 'upload_files' ) || get_site_option( 'upload_space_check_disabled' ) )
		return true;

	$quota = get_space_allowed();
	$used = get_space_used();

	if ( $used > $quota )
		$percentused = '100';
	else
		$percentused = ( $used / $quota ) * 100;
	$used_class = ( $percentused >= 70 ) ? ' warning' : '';
	$used = round( $used, 2 );
	$percentused = number_format( $percentused );

	?>
	<h3 class="mu-storage">Storage Space</h3>
	<div class="mu-storage">
	<ul>
		<li class="storage-count">
			<?php $text = sprintf(
				'%s MB Space Allowed',
				number_format_i18n( $quota )
			);
			printf(
				'<a href="%1$s">%2$s <span class="screen-reader-text">(%3$s)</span></a>',
				esc_url( admin_url( 'upload.php' ) ),
				$text,
				__( 'Manage Uploads' )
			); ?>
		</li><li class="storage-count <?php echo $used_class; ?>">
			<?php $text = sprintf(
				/* translators: 1: number of megabytes, 2: percentage */
				__( '%1$s MB (%2$s%%) Space Used' ),
				number_format_i18n( $used, 2 ),
				$percentused
			);
			printf(
				'<a href="%1$s" class="musublink">%2$s <span class="screen-reader-text">(%3$s)</span></a>',
				esc_url( admin_url( 'upload.php' ) ),
				$text,
				__( 'Manage Uploads' )
			); ?>
		</li>
	</ul>
	</div>
	<?php
}

function wp_dashboard_browser_nag() {
	$notice = '';
	$response = wp_check_browser_version();

	if ( $response ) {
		if ( $response['insecure'] ) {
			$msg = sprintf( "It looks like you're using an insecure version of %s. Using an outdated browser makes your computer unsafe. For the best WordPress experience, please update your browser.",
				sprintf( '<a href="%s">%s</a>', esc_url( $response['update_url'] ), esc_html( $response['name'] ) )
			);
		} else {
			$msg = sprintf( "It looks like you're using an old version of %s. For the best WordPress experience, please update your browser.",
				sprintf( '<a href="%s">%s</a>', esc_url( $response['update_url'] ), esc_html( $response['name'] ) )
			);
		}

		$browser_nag_class = '';
		if ( !empty( $response['img_src'] ) ) {
			$img_src = ( is_ssl() && ! empty( $response['img_src_ssl'] ) )? $response['img_src_ssl'] : $response['img_src'];

			$notice .= '<div class="alignright browser-icon"><a href="' . esc_attr($response['update_url']) . '"><img src="' . esc_attr( $img_src ) . '" alt="" /></a></div>';
			$browser_nag_class = ' has-browser-icon';
		}
		$notice .= "<p class='browser-update-nag{$browser_nag_class}'>{$msg}</p>";

		$browsehappy = 'http://browsehappy.com/';
		$locale = get_locale();
		if ( 'en_US' !== $locale )
			$browsehappy = add_query_arg( 'locale', $locale, $browsehappy );

		$notice .= '<p>' . sprintf( '<a href="%1$s" class="update-browser-link">Update %2$s</a> or learn how to <a href="%3$s" class="browse-happy-link">browse happy</a>', esc_attr( $response['update_url'] ), esc_html( $response['name'] ), esc_url( $browsehappy ) ) . '</p>';
		$notice .= '<p class="hide-if-no-js"><a href="" class="dismiss" aria-label="Dismiss the browser warning panel">Dismiss</a></p>';
		$notice .= '<div class="clear"></div>';
	}

	echo apply_filters( 'browse-happy-notice', $notice, $response );
}

function dashboard_browser_nag_class( $classes ) {
	$response = wp_check_browser_version();

	if ( $response && $response['insecure'] )
		$classes[] = 'browser-insecure';

	return $classes;
}

function wp_check_browser_version() {
	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) )
		return false;
	$key = md5( $_SERVER['HTTP_USER_AGENT'] );
	if ( false === ($response = get_site_transient('browser_' . $key) ) ) {
		global $wp_version;
		$options = array(
			'body'			=> array( 'useragent' => $_SERVER['HTTP_USER_AGENT'] ),
			'user-agent'	=> 'WordPress/' . $wp_version . '; ' . home_url()
		);
		$response = wp_remote_post( 'http://api.wordpress.org/core/browse-happy/1.1/', $options );
		if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) )
			return false;
		$response = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $response ) )
			return false;
		set_site_transient( 'browser_' . $key, $response, WEEK_IN_SECONDS );
	}
	return $response;
}

function wp_dashboard_empty() {}
