<?php
/**
 * Edit Posts Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

require_once( __DIR__ . '/admin.php' );

if ( ! $typenow )
	wp_die( 'Invalid post type' );

if ( ! in_array( $typenow, get_post_types( array( 'show_ui' => true ) ) ) ) {
	wp_die( 'You are not allowed to edit posts in this post type.' );
}

if ( 'attachment' === $typenow ) {
	if ( wp_redirect( admin_url( 'upload.php' ) ) ) {
		exit;
	}
}

global $post_type, $post_type_object;

$post_type = $typenow;
$post_type_object = get_post_type_object( $post_type );

if ( ! $post_type_object )
	wp_die( 'Invalid post type' );

if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
	wp_die(
		'<h1>Cheatin&#8217; uh?</h1>' .
		'<p>You are not allowed to edit posts in this post type.</p>',
		403
	);
}

$wp_list_table = _get_list_table('WP_Posts_List_Table');
$pagenum = $wp_list_table->get_pagenum();

foreach ( array( 'p', 'attachment_id', 'page_id' ) as $_redirect ) {
	if ( ! empty( $_REQUEST[ $_redirect ] ) ) {
		wp_redirect( admin_url( 'edit-comments.php?p=' . absint( $_REQUEST[ $_redirect ] ) ) );
		exit;
	}
}
unset( $_redirect );

if ( 'post' != $post_type ) {
	$parent_file = "edit.php?post_type=$post_type";
	$submenu_file = "edit.php?post_type=$post_type";
	$post_new_file = "post-new.php?post_type=$post_type";
} else {
	$parent_file = 'edit.php';
	$submenu_file = 'edit.php';
	$post_new_file = 'post-new.php';
}

$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer('bulk-posts');
	$sendback = remove_query_arg( array('trashed', 'untrashed', 'deleted', 'locked', 'ids'), wp_get_referer() );
	if ( ! $sendback )
		$sendback = admin_url( $parent_file );
	$sendback = add_query_arg( 'paged', $pagenum, $sendback );
	if ( strpos($sendback, 'post.php') !== false )
		$sendback = admin_url($post_new_file);

	if ( 'delete_all' == $doaction ) {
		$post_status = preg_replace('/[^a-z0-9_-]+/i', '', $_REQUEST['post_status']);
		if ( get_post_status_object( $post_status ) ) {
			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status = %s", $post_type, $post_status ) );
		}
		$doaction = 'delete';
	} elseif ( isset( $_REQUEST['media'] ) ) {
		$post_ids = $_REQUEST['media'];
	} elseif ( isset( $_REQUEST['ids'] ) ) {
		$post_ids = explode( ',', $_REQUEST['ids'] );
	} elseif ( !empty( $_REQUEST['post'] ) ) {
		$post_ids = array_map('intval', $_REQUEST['post']);
	}

	if ( !isset( $post_ids ) ) {
		wp_redirect( $sendback );
		exit;
	}

	switch ( $doaction ) {
		case 'trash':
			$trashed = $locked = 0;
			foreach ( (array) $post_ids as $post_id ) {
				if ( !current_user_can( 'delete_post', $post_id) )
					wp_die( 'You are not allowed to move this item to the Trash.' );

				if ( wp_check_post_lock( $post_id ) ) {
					$locked++;
					continue;
				}
				if ( !wp_trash_post($post_id) )
					wp_die( 'Error in moving to Trash.' );

				$trashed++;
			}
			$sendback = add_query_arg( array('trashed' => $trashed, 'ids' => join(',', $post_ids), 'locked' => $locked ), $sendback );
			break;
		case 'untrash':
			$untrashed = 0;
			foreach ( (array) $post_ids as $post_id ) {
				if ( !current_user_can( 'delete_post', $post_id) )
					wp_die( 'You are not allowed to restore this item from the Trash.' );
				if ( !wp_untrash_post($post_id) )
					wp_die( 'Error in restoring from Trash.' );
				$untrashed++;
			}
			$sendback = add_query_arg('untrashed', $untrashed, $sendback);
			break;
		case 'delete':
			$deleted = 0;
			foreach ( (array) $post_ids as $post_id ) {
				$post_del = get_post($post_id);
				if ( !current_user_can( 'delete_post', $post_id ) )
					wp_die( 'You are not allowed to delete this item.' );
				if ( $post_del->post_type == 'attachment' ) {
					if ( ! wp_delete_attachment($post_id) )
						wp_die( 'Error in deleting.' );
				} else {
					if ( !wp_delete_post($post_id) )
						wp_die( 'Error in deleting.' );
				}
				$deleted++;
			}
			$sendback = add_query_arg('deleted', $deleted, $sendback);
			break;
		case 'edit':
			if ( isset($_REQUEST['bulk_edit']) ) {
				$done = bulk_edit_posts($_REQUEST);
				if ( is_array($done) ) {
					$done['updated'] = count( $done['updated'] );
					$done['skipped'] = count( $done['skipped'] );
					$done['locked'] = count( $done['locked'] );
					$sendback = add_query_arg( $done, $sendback );
				}
			}
			break;
	}
	$sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view'), $sendback );

	wp_redirect($sendback);
	exit();
} elseif ( ! empty($_REQUEST['_wp_http_referer']) ) {
	 wp_redirect( remove_query_arg( array('_wp_http_referer', '_wpnonce'), wp_unslash($_SERVER['REQUEST_URI']) ) );
	 exit;
}

$wp_list_table->prepare_items();

wp_enqueue_script('inline-edit-post');
wp_enqueue_script('heartbeat');

$title = $post_type_object->labels->name;

if ( 'post' == $post_type ) {
	get_current_screen()->add_help_tab( array(
	'id'		=> 'overview',
	'title'		=> '概述',
	'content'	=>
		'<p>本页面提供文章相关的所有功能。您可以自定义页面的样式来使工作更顺手。</p>'
	) );
	get_current_screen()->add_help_tab( array(
	'id'		=> 'screen-content',
	'title'		=> '页面内容',
	'content'	=>
		'<p>您可以通过以下方法来自定义本页面内容的显示方式：</p>' .
		'<ul>' .
			'<li>您可在“显示选项”中依据您的需要隐藏或显示每页显示的文章数量。</li>' .
			'<li>您可以通过点击列表左上方的文字链接来过滤列表显示的项目——全部、已发布、草稿、回收站。默认视图中，显示所有文章。</li>' .
			'<li>您可以使用简单标题列表来查看文章，或是在显示选项面板种加入摘要。</li>' .			'<li>通过在文章列表上方的下拉菜单中选择，您可单独查看显示某一分类中的文章，或是某月发布的文章。点击列表中作者、分类，或标签也可令列表只显示那些内容。</li>' .
		'</ul>'
	) );
	get_current_screen()->add_help_tab( array(
	'id'		=> 'action-links',
	'title'		=> '可进行的操作',
	'content'	=>
		'<p>Hovering over a row in the posts list will display action links that allow you to manage your post. You can perform the following actions:</p>' .
		'<ul>' .
			'<li><strong>Edit</strong> takes you to the editing screen for that post. You can also reach that screen by clicking on the post title.</li>' .
			'<li><strong>Quick Edit</strong> provides inline access to the metadata of your post, allowing you to update post details without leaving this screen.</li>' .
			'<li><strong>Trash</strong> removes your post from this list and places it in the trash, from which you can permanently delete it.</li>' .
			'<li><strong>Preview</strong> will show you what your draft post will look like if you publish it. View will take you to your live site to view the post. Which link is available depends on your post&#8217;s status.</li>' .
		'</ul>'
	) );
	get_current_screen()->add_help_tab( array(
	'id'		=> 'bulk-actions',
	'title'		=> '批量操作',
	'content'	=>
		'<p>You can also edit or move multiple posts to the trash at once. Select the posts you want to act on using the checkboxes, then select the action you want to take from the Bulk Actions menu and click Apply.</p>' .
				'<p>When using Bulk Edit, you can change the metadata (categories, author, etc.) for all selected posts at once. To remove a post from the grouping, just click the x next to its name in the Bulk Edit area that appears.</p>'
	) );
} elseif ( 'page' == $post_type ) {
	get_current_screen()->add_help_tab( array(
	'id'		=> 'overview',
	'title'		=> '概述',
	'content'	=>
		'<p>Pages are similar to posts in that they have a title, body text, and associated metadata, but they are different in that they are not part of the chronological blog stream, kind of like permanent posts. Pages are not categorized or tagged, but can have a hierarchy. You can nest pages under other pages by making one the &#8220;Parent&#8221; of the other, creating a group of pages.</p>'
	) );
	get_current_screen()->add_help_tab( array(
	'id'		=> 'managing-pages',
	'title'		=> '页面的管理',
	'content'	=>
		'<p>管理页面的方法和管理文章的方法类似，本页面也可以用相同的方式自定义。</p>' .
		'<p>您可以进行同样的操作，比如使用过滤器筛选列表项、使用鼠标悬停的方式进行管理，或使用“批量操作”功能来同时编辑多个文章的属性。</p>'
	) );

}

get_current_screen()->set_screen_reader_content( array(
	'heading_views'      => $post_type_object->labels->filter_items_list,
	'heading_pagination' => $post_type_object->labels->items_list_navigation,
	'heading_list'       => $post_type_object->labels->items_list,
) );

add_screen_option( 'per_page', array( 'default' => 20, 'option' => 'edit_' . $post_type . '_per_page' ) );

$bulk_counts = array(
	'updated'   => isset( $_REQUEST['updated'] )   ? absint( $_REQUEST['updated'] )   : 0,
	'locked'    => isset( $_REQUEST['locked'] )    ? absint( $_REQUEST['locked'] )    : 0,
	'deleted'   => isset( $_REQUEST['deleted'] )   ? absint( $_REQUEST['deleted'] )   : 0,
	'trashed'   => isset( $_REQUEST['trashed'] )   ? absint( $_REQUEST['trashed'] )   : 0,
	'untrashed' => isset( $_REQUEST['untrashed'] ) ? absint( $_REQUEST['untrashed'] ) : 0,
);

$bulk_messages = array();
$bulk_messages['post'] = array(
	'updated'   => _n( '%s post updated.', '%s posts updated.', $bulk_counts['updated'] ),
	'locked'    => ( 1 == $bulk_counts['locked'] ) ? __( '1 post not updated, somebody is editing it.' ) :
	                   _n( '%s post not updated, somebody is editing it.', '%s posts not updated, somebody is editing them.', $bulk_counts['locked'] ),
	'deleted'   => _n( '%s post permanently deleted.', '%s posts permanently deleted.', $bulk_counts['deleted'] ),
	'trashed'   => _n( '%s post moved to the Trash.', '%s posts moved to the Trash.', $bulk_counts['trashed'] ),
	'untrashed' => _n( '%s post restored from the Trash.', '%s posts restored from the Trash.', $bulk_counts['untrashed'] ),
);
$bulk_messages['page'] = array(
	'updated'   => _n( '%s page updated.', '%s pages updated.', $bulk_counts['updated'] ),
	'locked'    => ( 1 == $bulk_counts['locked'] ) ? __( '1 page not updated, somebody is editing it.' ) :
	                   _n( '%s page not updated, somebody is editing it.', '%s pages not updated, somebody is editing them.', $bulk_counts['locked'] ),
	'deleted'   => _n( '%s page permanently deleted.', '%s pages permanently deleted.', $bulk_counts['deleted'] ),
	'trashed'   => _n( '%s page moved to the Trash.', '%s pages moved to the Trash.', $bulk_counts['trashed'] ),
	'untrashed' => _n( '%s page restored from the Trash.', '%s pages restored from the Trash.', $bulk_counts['untrashed'] ),
);

$bulk_messages = apply_filters( 'bulk_post_updated_messages', $bulk_messages, $bulk_counts );
$bulk_counts = array_filter( $bulk_counts );

require_once( ABSPATH . 'wp-admin/admin-header.php' );
?>
<div class="wrap">
<h1><?php
echo esc_html( $post_type_object->labels->name );
if ( current_user_can( $post_type_object->cap->create_posts ) )
	echo ' <a href="' . esc_url( admin_url( $post_new_file ) ) . '" class="page-title-action">' . esc_html( $post_type_object->labels->add_new ) . '</a>';

if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
	printf( ' <span class="subtitle">Search results for &#8220;%s&#8221;</span>', get_search_query() );
}
?></h1>

<?php
$messages = array();
foreach ( $bulk_counts as $message => $count ) {
	if ( isset( $bulk_messages[ $post_type ][ $message ] ) )
		$messages[] = sprintf( $bulk_messages[ $post_type ][ $message ], number_format_i18n( $count ) );
	elseif ( isset( $bulk_messages['post'][ $message ] ) )
		$messages[] = sprintf( $bulk_messages['post'][ $message ], number_format_i18n( $count ) );

	if ( $message == 'trashed' && isset( $_REQUEST['ids'] ) ) {
		$ids = preg_replace( '/[^0-9,]/', '', $_REQUEST['ids'] );
		$messages[] = '<a href="' . esc_url( wp_nonce_url( "edit.php?post_type=$post_type&doaction=undo&action=untrash&ids=$ids", "bulk-posts" ) ) . '">' . __('Undo') . '</a>';
	}
}

if ( $messages )
	echo '<div id="message" class="updated notice is-dismissible"><p>' . implode( ' ', $messages ) . '</p></div>';
unset( $messages );

$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'locked', 'skipped', 'updated', 'deleted', 'trashed', 'untrashed' ), $_SERVER['REQUEST_URI'] );
?>

<?php $wp_list_table->views(); ?>
<form id="posts-filter" method="get">
<?php $wp_list_table->search_box( $post_type_object->labels->search_items, 'post' ); ?>
<input type="hidden" name="post_status" class="post_status_page" value="<?php echo !empty($_REQUEST['post_status']) ? esc_attr($_REQUEST['post_status']) : 'all'; ?>" />
<input type="hidden" name="post_type" class="post_type_page" value="<?php echo $post_type; ?>" />
<?php if ( ! empty( $_REQUEST['show_sticky'] ) ) { ?>
<input type="hidden" name="show_sticky" value="1" />
<?php } ?>
<?php $wp_list_table->display(); ?>
</form>

<?php
if ( $wp_list_table->has_items() )
	$wp_list_table->inline_edit();
?>

<div id="ajax-response"></div>
<br class="clear" />
</div>

<?php
include( ABSPATH . 'wp-admin/admin-footer.php' );
