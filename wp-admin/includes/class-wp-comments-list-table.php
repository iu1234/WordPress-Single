<?php

class WP_Comments_List_Table extends WP_List_Table {

	public $checkbox = true;

	public $pending_count = array();

	public $extra_items;

	private $user_can;

	public function __construct( $args = array() ) {
		global $post_id;

		$post_id = isset( $_REQUEST['p'] ) ? absint( $_REQUEST['p'] ) : 0;

		if ( get_option( 'show_avatars' ) ) {
			add_filter( 'comment_author', array( $this, 'floated_admin_avatar' ), 10, 2 );
		}

		parent::__construct( array(
			'plural' => 'comments',
			'singular' => 'comment',
			'ajax' => true,
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
		) );
	}

	public function floated_admin_avatar( $name, $comment_ID ) {
		$comment = get_comment( $comment_ID );
		$avatar = get_avatar( $comment, 32, 'mystery' );
		return "$avatar $name";
	}

	public function ajax_user_can() {
		return current_user_can('edit_posts');
	}

	public function prepare_items() {
		global $post_id, $comment_status, $search, $comment_type;

		$comment_status = isset( $_REQUEST['comment_status'] ) ? $_REQUEST['comment_status'] : 'all';
		if ( !in_array( $comment_status, array( 'all', 'moderated', 'approved', 'spam', 'trash' ) ) )
			$comment_status = 'all';

		$comment_type = !empty( $_REQUEST['comment_type'] ) ? $_REQUEST['comment_type'] : '';

		$search = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : '';

		$post_type = ( isset( $_REQUEST['post_type'] ) ) ? sanitize_key( $_REQUEST['post_type'] ) : '';

		$user_id = ( isset( $_REQUEST['user_id'] ) ) ? $_REQUEST['user_id'] : '';

		$orderby = ( isset( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : '';
		$order = ( isset( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : '';

		$comments_per_page = $this->get_per_page( $comment_status );

		$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;

		if ( isset( $_REQUEST['number'] ) ) {
			$number = (int) $_REQUEST['number'];
		}
		else {
			$number = $comments_per_page + min( 8, $comments_per_page ); // Grab a few extra
		}

		$page = $this->get_pagenum();

		if ( isset( $_REQUEST['start'] ) ) {
			$start = $_REQUEST['start'];
		} else {
			$start = ( $page - 1 ) * $comments_per_page;
		}

		if ( $doing_ajax && isset( $_REQUEST['offset'] ) ) {
			$start += $_REQUEST['offset'];
		}

		$status_map = array(
			'moderated' => 'hold',
			'approved' => 'approve',
			'all' => '',
		);

		$args = array(
			'status' => isset( $status_map[$comment_status] ) ? $status_map[$comment_status] : $comment_status,
			'search' => $search,
			'user_id' => $user_id,
			'offset' => $start,
			'number' => $number,
			'post_id' => $post_id,
			'type' => $comment_type,
			'orderby' => $orderby,
			'order' => $order,
			'post_type' => $post_type,
		);

		$_comments = get_comments( $args );
		if ( is_array( $_comments ) ) {
			update_comment_cache( $_comments );

			$this->items = array_slice( $_comments, 0, $comments_per_page );
			$this->extra_items = array_slice( $_comments, $comments_per_page );

			$_comment_post_ids = array_unique( wp_list_pluck( $_comments, 'comment_post_ID' ) );

			$this->pending_count = get_pending_comments_num( $_comment_post_ids );
		}

		$total_comments = get_comments( array_merge( $args, array(
			'count' => true,
			'offset' => 0,
			'number' => 0
		) ) );

		$this->set_pagination_args( array(
			'total_items' => $total_comments,
			'per_page' => $comments_per_page,
		) );
	}

	public function get_per_page( $comment_status = 'all' ) {
		$comments_per_page = $this->get_items_per_page( 'edit_comments_per_page' );
		return apply_filters( 'comments_per_page', $comments_per_page, $comment_status );
	}

	public function no_items() {
		global $comment_status;

		if ( 'moderated' === $comment_status ) {
			echo 'No comments awaiting moderation.';
		} else {
			echo 'No comments found.';
		}
	}

	protected function get_views() {
		global $post_id, $comment_status, $comment_type;

		$status_links = array();
		$num_comments = ( $post_id ) ? wp_count_comments( $post_id ) : wp_count_comments();
		//, number_format_i18n($num_comments->moderated) ), "<span class='comment-count'>" . number_format_i18n($num_comments->moderated) . "</span>"),
		//, number_format_i18n($num_comments->spam) ), "<span class='spam-comment-count'>" . number_format_i18n($num_comments->spam) . "</span>")
		$stati = array(
			/* translators: %s: all comments count */
			'all' => _nx_noop(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				'comments'
			), // singular not used

			/* translators: %s: pending comments count */
			'moderated' => _nx_noop(
				'Pending <span class="count">(%s)</span>',
				'Pending <span class="count">(%s)</span>',
				'comments'
			),

			'approved' => _nx_noop(
				'Approved <span class="count">(%s)</span>',
				'Approved <span class="count">(%s)</span>',
				'comments'
			),

			'spam' => _nx_noop(
				'Spam <span class="count">(%s)</span>',
				'Spam <span class="count">(%s)</span>',
				'comments'
			),

			'trash' => _nx_noop(
				'Trash <span class="count">(%s)</span>',
				'Trash <span class="count">(%s)</span>',
				'comments'
			)
		);

		if ( !EMPTY_TRASH_DAYS )
			unset($stati['trash']);

		$link = admin_url( 'edit-comments.php' );
		if ( !empty($comment_type) && 'all' != $comment_type )
			$link = add_query_arg( 'comment_type', $comment_type, $link );

		foreach ( $stati as $status => $label ) {
			$class = ( $status === $comment_status ) ? ' class="current"' : '';

			if ( !isset( $num_comments->$status ) )
				$num_comments->$status = 10;
			$link = add_query_arg( 'comment_status', $status, $link );
			if ( $post_id )
				$link = add_query_arg( 'p', absint( $post_id ), $link );
			/*
			// I toyed with this, but decided against it. Leaving it in here in case anyone thinks it is a good idea. ~ Mark
			if ( !empty( $_REQUEST['s'] ) )
				$link = add_query_arg( 's', esc_attr( wp_unslash( $_REQUEST['s'] ) ), $link );
			*/
			$status_links[ $status ] = "<a href='$link'$class>" . sprintf(
				translate_nooped_plural( $label, $num_comments->$status ),
				sprintf( '<span class="%s-count">%s</span>',
					( 'moderated' === $status ) ? 'pending' : $status,
					number_format_i18n( $num_comments->$status )
				)
			) . '</a>';
		}

		return apply_filters( 'comment_status_links', $status_links );
	}

	protected function get_bulk_actions() {
		global $comment_status;

		$actions = array();
		if ( in_array( $comment_status, array( 'all', 'approved' ) ) )
			$actions['unapprove'] = 'Unapprove';
		if ( in_array( $comment_status, array( 'all', 'moderated' ) ) )
			$actions['approve'] = 'Approve';
		if ( in_array( $comment_status, array( 'all', 'moderated', 'approved', 'trash' ) ) )
			$actions['spam'] = 'Mark as Spam';

		if ( 'trash' === $comment_status ) {
			$actions['untrash'] = 'Restore';
		} elseif ( 'spam' === $comment_status ) {
			$actions['unspam'] = 'Not Spam';
		}

		if ( in_array( $comment_status, array( 'trash', 'spam' ) ) || !EMPTY_TRASH_DAYS )
			$actions['delete'] = 'Delete Permanently';
		else
			$actions['trash'] = 'Move to Trash';

		return $actions;
	}

	protected function extra_tablenav( $which ) {
		global $comment_status, $comment_type;
?>
		<div class="alignleft actions">
<?php
		if ( 'top' === $which ) {
?>
			<label class="screen-reader-text" for="filter-by-comment-type">Filter by comment type</label>
			<select id="filter-by-comment-type" name="comment_type">
				<option value="">All comment types</option>
<?php
				$comment_types = apply_filters( 'admin_comment_types_dropdown', array(
					'comment' => 'Comments',
					'pings' => 'Pings',
				) );

				foreach ( $comment_types as $type => $label )
					echo "\t" . '<option value="' . esc_attr( $type ) . '"' . selected( $comment_type, $type, false ) . ">$label</option>\n";
			?>
			</select>
<?php
			do_action( 'restrict_manage_comments' );
			submit_button( 'Filter', 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
		}

		if ( ( 'spam' === $comment_status || 'trash' === $comment_status ) && current_user_can( 'moderate_comments' ) ) {
			wp_nonce_field( 'bulk-destroy', '_destroy_nonce' );
			$title = ( 'spam' === $comment_status ) ? 'Empty Spam' : 'Empty Trash';
			submit_button( $title, 'apply', 'delete_all', false );
		}
		do_action( 'manage_comments_nav', $comment_status );
		echo '</div>';
	}

	public function current_action() {
		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) )
			return 'delete_all';

		return parent::current_action();
	}

	public function get_columns() {
		global $post_id;

		$columns = array();

		if ( $this->checkbox )
			$columns['cb'] = '<input type="checkbox" />';

		$columns['author'] = 'Author';
		$columns['comment'] = 'Comment';
		if ( ! $post_id ) {
			$columns['response'] = 'In Response To';
		}
		$columns['date'] = 'Submitted On';
		return $columns;
	}

	protected function get_sortable_columns() {
		return array(
			'author'   => 'comment_author',
			'response' => 'comment_post_ID',
			'date'     => 'comment_date'
		);
	}

	protected function get_default_primary_column_name() {
		return 'comment';
	}

	public function display() {
		wp_nonce_field( "fetch-list-" . get_class( $this ), '_ajax_fetch_list_nonce' );

		$this->display_tablenav( 'top' );

		$this->screen->render_screen_reader_content( 'heading_list' );

?>
<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
	<thead>
	<tr>
		<?php $this->print_column_headers(); ?>
	</tr>
	</thead>

	<tbody id="the-comment-list" data-wp-lists="list:comment">
		<?php $this->display_rows_or_placeholder(); ?>
	</tbody>

	<tbody id="the-extra-comment-list" data-wp-lists="list:comment" style="display: none;">
		<?php
			$this->items = $this->extra_items;
			$this->display_rows_or_placeholder();
		?>
	</tbody>

	<tfoot>
	<tr>
		<?php $this->print_column_headers( false ); ?>
	</tr>
	</tfoot>

</table>
<?php

		$this->display_tablenav( 'bottom' );
	}

	public function single_row( $item ) {
		global $post, $comment;

		$comment = $item;

		$the_comment_class = wp_get_comment_status( $comment );
		if ( ! $the_comment_class ) {
			$the_comment_class = '';
		}
		$the_comment_class = join( ' ', get_comment_class( $the_comment_class, $comment, $comment->comment_post_ID ) );

		if ( $comment->comment_post_ID > 0 ) {
			$post = get_post( $comment->comment_post_ID );
		}
		$this->user_can = current_user_can( 'edit_comment', $comment->comment_ID );

		echo "<tr id='comment-$comment->comment_ID' class='$the_comment_class'>";
		$this->single_row_columns( $comment );
		echo "</tr>\n";

		unset( $GLOBALS['post'], $GLOBALS['comment'] );
	}

 	protected function handle_row_actions( $comment, $column_name, $primary ) {
 		global $comment_status;

		if ( $primary !== $column_name ) {
			return '';
		}

 		if ( ! $this->user_can ) {
 			return;
		}

		$the_comment_status = wp_get_comment_status( $comment );

		$out = '';

		$del_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "delete-comment_$comment->comment_ID" ) );
		$approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-comment_$comment->comment_ID" ) );

		$url = "comment.php?c=$comment->comment_ID";

		$approve_url = esc_url( $url . "&action=approvecomment&$approve_nonce" );
		$unapprove_url = esc_url( $url . "&action=unapprovecomment&$approve_nonce" );
		$spam_url = esc_url( $url . "&action=spamcomment&$del_nonce" );
		$unspam_url = esc_url( $url . "&action=unspamcomment&$del_nonce" );
		$trash_url = esc_url( $url . "&action=trashcomment&$del_nonce" );
		$untrash_url = esc_url( $url . "&action=untrashcomment&$del_nonce" );
		$delete_url = esc_url( $url . "&action=deletecomment&$del_nonce" );

		// Preorder it: Approve | Reply | Quick Edit | Edit | Spam | Trash.
		$actions = array(
			'approve' => '', 'unapprove' => '',
			'reply' => '',
			'quickedit' => '',
			'edit' => '',
			'spam' => '', 'unspam' => '',
			'trash' => '', 'untrash' => '', 'delete' => ''
		);

		// Not looking at all comments.
		if ( $comment_status && 'all' != $comment_status ) {
			if ( 'approved' === $the_comment_status ) {
				$actions['unapprove'] = "<a href='$unapprove_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID:e7e7d3:action=dim-comment&amp;new=unapproved' class='vim-u vim-destructive' aria-label='" . esc_attr__( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a>';
			} elseif ( 'unapproved' === $the_comment_status ) {
				$actions['approve'] = "<a href='$approve_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID:e7e7d3:action=dim-comment&amp;new=approved' class='vim-a vim-destructive' aria-label='" . esc_attr__( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a>';
			}
		} else {
			$actions['approve'] = "<a href='$approve_url' data-wp-lists='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=approved' class='vim-a' aria-label='" . esc_attr__( 'Approve this comment' ) . "'>" . __( 'Approve' ) . '</a>';
			$actions['unapprove'] = "<a href='$unapprove_url' data-wp-lists='dim:the-comment-list:comment-$comment->comment_ID:unapproved:e7e7d3:e7e7d3:new=unapproved' class='vim-u' aria-label='" . esc_attr__( 'Unapprove this comment' ) . "'>" . __( 'Unapprove' ) . '</a>';
		}

		if ( 'spam' !== $the_comment_status ) {
			$actions['spam'] = "<a href='$spam_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::spam=1' class='vim-s vim-destructive' aria-label='" . esc_attr__( 'Mark this comment as spam' ) . "'>" . /* translators: mark as spam link */ _x( 'Spam', 'verb' ) . '</a>';
		} elseif ( 'spam' === $the_comment_status ) {
			$actions['unspam'] = "<a href='$unspam_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID:66cc66:unspam=1' class='vim-z vim-destructive' aria-label='" . esc_attr__( 'Restore this comment from the spam' ) . "'>" . _x( 'Not Spam', 'comment' ) . '</a>';
		}

		if ( 'trash' === $the_comment_status ) {
			$actions['untrash'] = "<a href='$untrash_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID:66cc66:untrash=1' class='vim-z vim-destructive' aria-label='" . esc_attr__( 'Restore this comment from the Trash' ) . "'>" . __( 'Restore' ) . '</a>';
		}

		if ( 'spam' === $the_comment_status || 'trash' === $the_comment_status || !EMPTY_TRASH_DAYS ) {
			$actions['delete'] = "<a href='$delete_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::delete=1' class='delete vim-d vim-destructive' aria-label='" . esc_attr__( 'Delete this comment permanently' ) . "'>" . __( 'Delete Permanently' ) . '</a>';
		} else {
			$actions['trash'] = "<a href='$trash_url' data-wp-lists='delete:the-comment-list:comment-$comment->comment_ID::trash=1' class='delete vim-d vim-destructive' aria-label='" . esc_attr__( 'Move this comment to the Trash' ) . "'>" . _x( 'Trash', 'verb' ) . '</a>';
		}

		if ( 'spam' !== $the_comment_status && 'trash' !== $the_comment_status ) {
			$actions['edit'] = "<a href='comment.php?action=editcomment&amp;c={$comment->comment_ID}' aria-label='" . esc_attr__( 'Edit this comment' ) . "'>". __( 'Edit' ) . '</a>';

			$format = '<a data-comment-id="%d" data-post-id="%d" data-action="%s" class="%s" aria-label="%s" href="#">%s</a>';

			$actions['quickedit'] = sprintf( $format, $comment->comment_ID, $comment->comment_post_ID, 'edit', 'vim-q comment-inline', esc_attr__( 'Quick edit this comment inline' ), __( 'Quick&nbsp;Edit' ) );

			$actions['reply'] = sprintf( $format, $comment->comment_ID, $comment->comment_post_ID, 'replyto', 'vim-r comment-inline', esc_attr__( 'Reply to this comment' ), __( 'Reply' ) );
		}

		$actions = apply_filters( 'comment_row_actions', array_filter( $actions ), $comment );

		$i = 0;
		$out .= '<div class="row-actions">';
		foreach ( $actions as $action => $link ) {
			++$i;
			( ( ( 'approve' === $action || 'unapprove' === $action ) && 2 === $i ) || 1 === $i ) ? $sep = '' : $sep = ' | ';

			if ( ( 'reply' === $action || 'quickedit' === $action ) && ! defined('DOING_AJAX') )
				$action .= ' hide-if-no-js';
			elseif ( ( $action === 'untrash' && $the_comment_status === 'trash' ) || ( $action === 'unspam' && $the_comment_status === 'spam' ) ) {
				if ( '1' == get_comment_meta( $comment->comment_ID, '_wp_trash_meta_status', true ) )
					$action .= ' approve';
				else
					$action .= ' unapprove';
			}

			$out .= "<span class='$action'>$sep$link</span>";
		}
		$out .= '</div>';

		$out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';

		return $out;
	}

	public function column_cb( $comment ) {
		if ( $this->user_can ) { ?>
		<label class="screen-reader-text" for="cb-select-<?php echo $comment->comment_ID; ?>">Select comment</label>
		<input id="cb-select-<?php echo $comment->comment_ID; ?>" type="checkbox" name="delete_comments[]" value="<?php echo $comment->comment_ID; ?>" />
		<?php
		}
	}

	public function column_comment( $comment ) {
		echo '<div class="comment-author">';
			$this->column_author( $comment );
		echo '</div>';

		if ( $comment->comment_parent ) {
			$parent = get_comment( $comment->comment_parent );
			if ( $parent ) {
				$parent_link = esc_url( get_comment_link( $parent ) );
				$name = get_comment_author( $parent );
				printf(
					'In reply to %s.',
					'<a href="' . $parent_link . '">' . $name . '</a>'
				);
			}
		}

		comment_text( $comment );
		if ( $this->user_can ) { ?>
		<div id="inline-<?php echo $comment->comment_ID; ?>" class="hidden">
		<textarea class="comment" rows="1" cols="1"><?php
			echo esc_textarea( apply_filters( 'comment_edit_pre', $comment->comment_content ) );
		?></textarea>
		<div class="author-email"><?php echo esc_attr( $comment->comment_author_email ); ?></div>
		<div class="author"><?php echo esc_attr( $comment->comment_author ); ?></div>
		<div class="author-url"><?php echo esc_attr( $comment->comment_author_url ); ?></div>
		<div class="comment_status"><?php echo $comment->comment_approved; ?></div>
		</div>
		<?php
		}
	}

	public function column_author( $comment ) {
		global $comment_status;

		$author_url = get_comment_author_url( $comment );

		$author_url_display = untrailingslashit( preg_replace( '|^http(s)?://(www\.)?|i', '', $author_url ) );
		if ( strlen( $author_url_display ) > 50 ) {
			$author_url_display = wp_html_excerpt( $author_url_display, 49, '&hellip;' );
		}

		echo "<strong>"; comment_author( $comment ); echo '</strong><br />';
		if ( ! empty( $author_url_display ) ) {
			printf( '<a href="%s">%s</a><br />', esc_url( $author_url ), esc_html( $author_url_display ) );
		}

		if ( $this->user_can ) {
			if ( ! empty( $comment->comment_author_email ) ) {
				/* This filter is documented in wp-includes/comment-template.php */
				$email = apply_filters( 'comment_email', $comment->comment_author_email, $comment );

				if ( ! empty( $email ) && '@' !== $email ) {
					printf( '<a href="%1$s">%2$s</a><br />', esc_url( 'mailto:' . $email ), esc_html( $email ) );
				}
			}

			$author_ip = get_comment_author_IP( $comment );
			if ( $author_ip ) {
				$author_ip_url = add_query_arg( array( 's' => $author_ip, 'mode' => 'detail' ), admin_url( 'edit-comments.php' ) );
				if ( 'spam' === $comment_status ) {
					$author_ip_url = add_query_arg( 'comment_status', 'spam', $author_ip_url );
				}
				printf( '<a href="%1$s">%2$s</a>', esc_url( $author_ip_url ), esc_html( $author_ip ) );
			}
		}
	}

	public function column_date( $comment ) {
		$submitted = sprintf( '%1$s at %2$s',
			get_comment_date( 'Y/m/d', $comment ),
			get_comment_date( 'g:i a', $comment )
		);

		echo '<div class="submitted-on">';
		if ( 'approved' === wp_get_comment_status( $comment ) && ! empty ( $comment->comment_post_ID ) ) {
			printf(
				'<a href="%s">%s</a>',
				esc_url( get_comment_link( $comment ) ),
				$submitted
			);
		} else {
			echo $submitted;
		}
		echo '</div>';
	}

	public function column_response( $comment ) {
		$post = get_post();

		if ( ! $post ) {
			return;
		}

		if ( isset( $this->pending_count[$post->ID] ) ) {
			$pending_comments = $this->pending_count[$post->ID];
		} else {
			$_pending_count_temp = get_pending_comments_num( array( $post->ID ) );
			$pending_comments = $this->pending_count[$post->ID] = $_pending_count_temp[$post->ID];
		}

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			$post_link = "<a href='" . get_edit_post_link( $post->ID ) . "' class='comments-edit-item-link'>";
			$post_link .= esc_html( get_the_title( $post->ID ) ) . '</a>';
		} else {
			$post_link = esc_html( get_the_title( $post->ID ) );
		}

		echo '<div class="response-links">';
		if ( 'attachment' === $post->post_type && ( $thumb = wp_get_attachment_image( $post->ID, array( 80, 60 ), true ) ) ) {
			echo $thumb;
		}
		echo $post_link;
		$post_type_object = get_post_type_object( $post->post_type );
		echo "<a href='" . get_permalink( $post->ID ) . "' class='comments-view-item-link'>" . $post_type_object->labels->view_item . '</a>';
		echo '<span class="post-com-count-wrapper post-com-count-', $post->ID, '">';
		$this->comments_bubble( $post->ID, $pending_comments );
		echo '</span> ';
		echo '</div>';
	}

	public function column_default( $comment, $column_name ) {
		do_action( 'manage_comments_custom_column', $column_name, $comment->comment_ID );
	}
}
