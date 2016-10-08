<?php

class WP_Posts_List_Table extends WP_List_Table {

	protected $hierarchical_display;

	protected $comment_pending_count;

	private $user_posts_count;

	private $sticky_posts_count = 0;

	private $is_trash;

	protected $current_level = 0;

	public function __construct( $args = array() ) {
		global $post_type_object, $wpdb;

		parent::__construct( array(
			'plural' => 'posts',
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
		) );

		$post_type        = $this->screen->post_type;
		$post_type_object = get_post_type_object( $post_type );

		$exclude_states   = get_post_stati( array(
			'show_in_admin_all_list' => false,
		) );
		$this->user_posts_count = intval( $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT( 1 )
			FROM $wpdb->posts
			WHERE post_type = %s
			AND post_status NOT IN ( '" . implode( "','", $exclude_states ) . "' )
			AND post_author = %d
		", $post_type, get_current_user_id() ) ) );

		if ( $this->user_posts_count && ! current_user_can( $post_type_object->cap->edit_others_posts ) && empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['all_posts'] ) && empty( $_REQUEST['author'] ) && empty( $_REQUEST['show_sticky'] ) ) {
			$_GET['author'] = get_current_user_id();
		}

		if ( 'post' === $post_type && $sticky_posts = get_option( 'sticky_posts' ) ) {
			$sticky_posts = implode( ', ', array_map( 'absint', (array) $sticky_posts ) );
			$this->sticky_posts_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( 1 ) FROM $wpdb->posts WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft') AND ID IN ($sticky_posts)", $post_type ) );
		}
	}

	public function set_hierarchical_display( $display ) {
		$this->hierarchical_display = $display;
	}

	public function ajax_user_can() {
		return current_user_can( get_post_type_object( $this->screen->post_type )->cap->edit_posts );
	}

	public function prepare_items() {
		global $avail_post_stati, $wp_query, $per_page, $mode;

		$avail_post_stati = wp_edit_posts_query();

		$this->set_hierarchical_display( is_post_type_hierarchical( $this->screen->post_type ) && 'menu_order title' === $wp_query->query['orderby'] );

		$post_type = $this->screen->post_type;
		$per_page = $this->get_items_per_page( 'edit_' . $post_type . '_per_page' );

 		$per_page = apply_filters( 'edit_posts_per_page', $per_page, $post_type );

		if ( $this->hierarchical_display ) {
			$total_items = $wp_query->post_count;
		} elseif ( $wp_query->found_posts || $this->get_pagenum() === 1 ) {
			$total_items = $wp_query->found_posts;
		} else {
			$post_counts = (array) wp_count_posts( $post_type, 'readable' );

			if ( isset( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'] , $avail_post_stati ) ) {
				$total_items = $post_counts[ $_REQUEST['post_status'] ];
			} elseif ( isset( $_REQUEST['show_sticky'] ) && $_REQUEST['show_sticky'] ) {
				$total_items = $this->sticky_posts_count;
			} elseif ( isset( $_GET['author'] ) && $_GET['author'] == get_current_user_id() ) {
				$total_items = $this->user_posts_count;
			} else {
				$total_items = array_sum( $post_counts );

				// Subtract post types that are not included in the admin all list.
				foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
					$total_items -= $post_counts[ $state ];
				}
			}
		}

		if ( ! empty( $_REQUEST['mode'] ) ) {
			$mode = $_REQUEST['mode'] === 'excerpt' ? 'excerpt' : 'list';
			set_user_setting( 'posts_list_mode', $mode );
		} else {
			$mode = get_user_setting( 'posts_list_mode', 'list' );
		}

		$this->is_trash = isset( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] === 'trash';

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page
		) );
	}

	public function has_items() {
		return have_posts();
	}

	public function no_items() {
		if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] )
			echo get_post_type_object( $this->screen->post_type )->labels->not_found_in_trash;
		else
			echo get_post_type_object( $this->screen->post_type )->labels->not_found;
	}

	protected function is_base_request() {
		$vars = $_GET;
		unset( $vars['paged'] );

		if ( empty( $vars ) ) {
			return true;
		} elseif ( 1 === count( $vars ) && ! empty( $vars['post_type'] ) ) {
			return $this->screen->post_type === $vars['post_type'];
		}

		return 1 === count( $vars ) && ! empty( $vars['mode'] );
	}

	protected function get_edit_link( $args, $label, $class = '' ) {
		$url = add_query_arg( $args, 'edit.php' );

		$class_html = '';
		if ( ! empty( $class ) ) {
			 $class_html = sprintf(
				' class="%s"',
				esc_attr( $class )
			);
		}

		return sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $url ),
			$class_html,
			$label
		);
	}

	protected function get_views() {
		global $locked_post_status, $avail_post_stati;

		$post_type = $this->screen->post_type;

		if ( !empty($locked_post_status) )
			return array();

		$status_links = array();
		$num_posts = wp_count_posts( $post_type, 'readable' );
		$total_posts = array_sum( (array) $num_posts );
		$class = '';

		$current_user_id = get_current_user_id();
		$all_args = array( 'post_type' => $post_type );
		$mine = '';

		foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
			$total_posts -= $num_posts->$state;
		}

		if ( $this->user_posts_count && $this->user_posts_count !== $total_posts ) {
			if ( isset( $_GET['author'] ) && ( $_GET['author'] == $current_user_id ) ) {
				$class = 'current';
			}

			$mine_args = array(
				'post_type' => $post_type,
				'author' => $current_user_id
			);

			$mine_inner_html = sprintf(
				_nx(
					'Mine <span class="count">(%s)</span>',
					'Mine <span class="count">(%s)</span>',
					$this->user_posts_count,
					'posts'
				),
				number_format_i18n( $this->user_posts_count )
			);

			$mine = $this->get_edit_link( $mine_args, $mine_inner_html, $class );

			$all_args['all_posts'] = 1;
			$class = '';
		}

		if ( empty( $class ) && ( $this->is_base_request() || isset( $_REQUEST['all_posts'] ) ) ) {
			$class = 'current';
		}

		$all_inner_html = sprintf(
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts'
			),
			number_format_i18n( $total_posts )
		);

		$status_links['all'] = $this->get_edit_link( $all_args, $all_inner_html, $class );
		if ( $mine ) {
			$status_links['mine'] = $mine;
		}

		foreach ( get_post_stati(array('show_in_admin_status_list' => true), 'objects') as $status ) {
			$class = '';

			$status_name = $status->name;

			if ( ! in_array( $status_name, $avail_post_stati ) || empty( $num_posts->$status_name ) ) {
				continue;
			}

			if ( isset($_REQUEST['post_status']) && $status_name === $_REQUEST['post_status'] ) {
				$class = 'current';
			}

			$status_args = array(
				'post_status' => $status_name,
				'post_type' => $post_type,
			);

			$status_label = sprintf(
				translate_nooped_plural( $status->label_count, $num_posts->$status_name ),
				number_format_i18n( $num_posts->$status_name )
			);

			$status_links[ $status_name ] = $this->get_edit_link( $status_args, $status_label, $class );
		}

		if ( ! empty( $this->sticky_posts_count ) ) {
			$class = ! empty( $_REQUEST['show_sticky'] ) ? 'current' : '';

			$sticky_args = array(
				'post_type'	=> $post_type,
				'show_sticky' => 1
			);

			$sticky_inner_html = sprintf(
				_nx(
					'Sticky <span class="count">(%s)</span>',
					'Sticky <span class="count">(%s)</span>',
					$this->sticky_posts_count,
					'posts'
				),
				number_format_i18n( $this->sticky_posts_count )
			);

			$sticky_link = array(
				'sticky' => $this->get_edit_link( $sticky_args, $sticky_inner_html, $class )
			);

			$split = 1 + array_search( ( isset( $status_links['publish'] ) ? 'publish' : 'all' ), array_keys( $status_links ) );
			$status_links = array_merge( array_slice( $status_links, 0, $split ), $sticky_link, array_slice( $status_links, $split ) );
		}

		return $status_links;
	}

	protected function get_bulk_actions() {
		$actions = array();
		$post_type_obj = get_post_type_object( $this->screen->post_type );

		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			if ( $this->is_trash ) {
				$actions['untrash'] = 'Restore';
			} else {
				$actions['edit'] = 'Edit';
			}
		}

		if ( current_user_can( $post_type_obj->cap->delete_posts ) ) {
			if ( $this->is_trash || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = 'Delete Permanently';
			} else {
				$actions['trash'] = 'Move to Trash';
			}
		}

		return $actions;
	}

	protected function extra_tablenav( $which ) {
		global $cat;
?>
		<div class="alignleft actions">
<?php
		if ( 'top' === $which && !is_singular() ) {

			$this->months_dropdown( $this->screen->post_type );

			if ( is_object_in_taxonomy( $this->screen->post_type, 'category' ) ) {
				$dropdown_options = array(
					'show_option_all' => get_taxonomy( 'category' )->labels->all_items,
					'hide_empty' => 0,
					'hierarchical' => 1,
					'show_count' => 0,
					'orderby' => 'name',
					'selected' => $cat
				);

				echo '<label class="screen-reader-text" for="cat">Filter by category</label>';
				wp_dropdown_categories( $dropdown_options );
			}

			do_action( 'restrict_manage_posts', $this->screen->post_type );

			submit_button( 'Filter', 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
		}

		if ( $this->is_trash && current_user_can( get_post_type_object( $this->screen->post_type )->cap->edit_others_posts ) ) {
			submit_button( 'Empty Trash', 'apply', 'delete_all', false );
		}
?>
		</div>
<?php

		do_action( 'manage_posts_extra_tablenav', $which );
	}

	public function current_action() {
		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) )
			return 'delete_all';

		return parent::current_action();
	}

	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', is_post_type_hierarchical( $this->screen->post_type ) ? 'pages' : 'posts' );
	}

	public function get_columns() {
		$post_type = $this->screen->post_type;

		$posts_columns = array();

		$posts_columns['cb'] = '<input type="checkbox" />';

		$posts_columns['title'] = 'Title';

		if ( post_type_supports( $post_type, 'author' ) ) {
			$posts_columns['author'] = 'Author';
		}

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$taxonomies = wp_filter_object_list( $taxonomies, array( 'show_admin_column' => true ), 'and', 'name' );

		$taxonomies = apply_filters( "manage_taxonomies_for_{$post_type}_columns", $taxonomies, $post_type );
		$taxonomies = array_filter( $taxonomies, 'taxonomy_exists' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( 'category' === $taxonomy )
				$column_key = 'categories';
			elseif ( 'post_tag' === $taxonomy )
				$column_key = 'tags';
			else
				$column_key = 'taxonomy-' . $taxonomy;

			$posts_columns[ $column_key ] = get_taxonomy( $taxonomy )->labels->name;
		}

		$post_status = !empty( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : 'all';
		if ( post_type_supports( $post_type, 'comments' ) && !in_array( $post_status, array( 'pending', 'draft', 'future' ) ) )
			$posts_columns['comments'] = '<span class="vers comment-grey-bubble" title="Comments"><span class="screen-reader-text">Comments</span></span>';

		$posts_columns['date'] = 'Date';

		if ( 'page' === $post_type ) {
			$posts_columns = apply_filters( 'manage_pages_columns', $posts_columns );
		} else {
			$posts_columns = apply_filters( 'manage_posts_columns', $posts_columns, $post_type );
		}
		return apply_filters( "manage_{$post_type}_posts_columns", $posts_columns );
	}

	protected function get_sortable_columns() {
		return array(
			'title'    => 'title',
			'parent'   => 'parent',
			'comments' => 'comment_count',
			'date'     => array( 'date', true )
		);
	}

	public function display_rows( $posts = array(), $level = 0 ) {
		global $wp_query, $per_page;

		if ( empty( $posts ) )
			$posts = $wp_query->posts;

		add_filter( 'the_title', 'esc_html' );

		if ( $this->hierarchical_display ) {
			$this->_display_rows_hierarchical( $posts, $this->get_pagenum(), $per_page );
		} else {
			$this->_display_rows( $posts, $level );
		}
	}

	private function _display_rows( $posts, $level = 0 ) {
		$post_ids = array();

		foreach ( $posts as $a_post )
			$post_ids[] = $a_post->ID;

		$this->comment_pending_count = get_pending_comments_num( $post_ids );

		foreach ( $posts as $post )
			$this->single_row( $post, $level );
	}

	private function _display_rows_hierarchical( $pages, $pagenum = 1, $per_page = 20 ) {
		global $wpdb;

		$level = 0;

		if ( ! $pages ) {
			$pages = get_pages( array( 'sort_column' => 'menu_order' ) );

			if ( ! $pages )
				return;
		}

		if ( empty( $_REQUEST['s'] ) ) {

			$top_level_pages = array();
			$children_pages = array();

			foreach ( $pages as $page ) {
				if ( $page->post_parent == $page->ID ) {
					$page->post_parent = 0;
					$wpdb->update( $wpdb->posts, array( 'post_parent' => 0 ), array( 'ID' => $page->ID ) );
					clean_post_cache( $page );
				}
				if ( 0 == $page->post_parent )
					$top_level_pages[] = $page;
				else
					$children_pages[ $page->post_parent ][] = $page;
			}

			$pages = &$top_level_pages;
		}

		$count = 0;
		$start = ( $pagenum - 1 ) * $per_page;
		$end = $start + $per_page;
		$to_display = array();

		foreach ( $pages as $page ) {
			if ( $count >= $end )
				break;

			if ( $count >= $start ) {
				$to_display[$page->ID] = $level;
			}

			$count++;

			if ( isset( $children_pages ) )
				$this->_page_rows( $children_pages, $count, $page->ID, $level + 1, $pagenum, $per_page, $to_display );
		}

		if ( isset( $children_pages ) && $count < $end ){
			foreach ( $children_pages as $orphans ){
				foreach ( $orphans as $op ) {
					if ( $count >= $end )
						break;

					if ( $count >= $start ) {
						$to_display[$op->ID] = 0;
					}

					$count++;
				}
			}
		}

		$ids = array_keys( $to_display );
		_prime_post_caches( $ids );

		if ( ! isset( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = reset( $ids );
		}

		foreach ( $to_display as $page_id => $level ) {
			echo "\t";
			$this->single_row( $page_id, $level );
		}
	}

	private function _page_rows( &$children_pages, &$count, $parent, $level, $pagenum, $per_page, &$to_display ) {
		if ( ! isset( $children_pages[$parent] ) )
			return;

		$start = ( $pagenum - 1 ) * $per_page;
		$end = $start + $per_page;

		foreach ( $children_pages[$parent] as $page ) {
			if ( $count >= $end )
				break;

			// If the page starts in a subtree, print the parents.
			if ( $count == $start && $page->post_parent > 0 ) {
				$my_parents = array();
				$my_parent = $page->post_parent;
				while ( $my_parent ) {
					// Get the ID from the list or the attribute if my_parent is an object
					$parent_id = $my_parent;
					if ( is_object( $my_parent ) ) {
						$parent_id = $my_parent->ID;
					}

					$my_parent = get_post( $parent_id );
					$my_parents[] = $my_parent;
					if ( !$my_parent->post_parent )
						break;
					$my_parent = $my_parent->post_parent;
				}
				$num_parents = count( $my_parents );
				while ( $my_parent = array_pop( $my_parents ) ) {
					$to_display[$my_parent->ID] = $level - $num_parents;
					$num_parents--;
				}
			}

			if ( $count >= $start ) {
				$to_display[$page->ID] = $level;
			}

			$count++;

			$this->_page_rows( $children_pages, $count, $page->ID, $level + 1, $pagenum, $per_page, $to_display );
		}

		unset( $children_pages[$parent] );
	}

	public function column_cb( $post ) {
		if ( current_user_can( 'edit_post', $post->ID ) ): ?>
			<label class="screen-reader-text" for="cb-select-<?php the_ID(); ?>"><?php
				printf( 'Select %s', _draft_or_post_title() );
			?></label>
			<input id="cb-select-<?php the_ID(); ?>" type="checkbox" name="post[]" value="<?php the_ID(); ?>" />
			<div class="locked-indicator"></div>
		<?php endif;
	}

	protected function _column_title( $post, $classes, $data, $primary ) {
		echo '<td class="' . $classes . ' page-title" ', $data, '>';
		echo $this->column_title( $post );
		echo $this->handle_row_actions( $post, 'title', $primary );
		echo '</td>';
	}

	public function column_title( $post ) {
		global $mode;

		if ( $this->hierarchical_display ) {
			if ( 0 === $this->current_level && (int) $post->post_parent > 0 ) {
				$find_main_page = (int) $post->post_parent;
				while ( $find_main_page > 0 ) {
					$parent = get_post( $find_main_page );
					if ( is_null( $parent ) ) {
						break;
					}
					$this->current_level++;
					$find_main_page = (int) $parent->post_parent;
					if ( ! isset( $parent_name ) ) {
						$parent_name = apply_filters( 'the_title', $parent->post_title, $parent->ID );
					}
				}
			}
		}

		$pad = str_repeat( '&#8212; ', $this->current_level );
		echo "<strong>";

		$format = get_post_format( $post->ID );
		if ( $format ) {
			$label = get_post_format_string( $format );

			$format_class = 'post-state-format post-format-icon post-format-' . $format;

			$format_args = array(
				'post_format' => $format,
				'post_type' => $post->post_type
			);

			echo $this->get_edit_link( $format_args, $label . ':', $format_class );
		}

		$can_edit_post = current_user_can( 'edit_post', $post->ID );
		$title = _draft_or_post_title();

		if ( $can_edit_post && $post->post_status != 'trash' ) {
			printf(
				'<a class="row-title" href="%s" aria-label="%s">%s%s</a>',
				get_edit_post_link( $post->ID ),
				esc_attr( sprintf( '&#8220;%s&#8221; (Edit)', $title ) ),
				$pad,
				$title
			);
		} else {
			echo $pad . $title;
		}
		_post_states( $post );

		if ( isset( $parent_name ) ) {
			$post_type_object = get_post_type_object( $post->post_type );
			echo ' | ' . $post_type_object->labels->parent_item_colon . ' ' . esc_html( $parent_name );
		}
		echo "</strong>\n";

		if ( $can_edit_post && $post->post_status != 'trash' ) {
			$lock_holder = wp_check_post_lock( $post->ID );

			if ( $lock_holder ) {
				$lock_holder = get_user_by( 'id', $lock_holder );
				$locked_avatar = get_avatar( $lock_holder->ID, 18 );
				$locked_text = esc_html( sprintf( '%s is currently editing', $lock_holder->display_name ) );
			} else {
				$locked_avatar = $locked_text = '';
			}

			echo '<div class="locked-info"><span class="locked-avatar">' . $locked_avatar . '</span> <span class="locked-text">' . $locked_text . "</span></div>\n";
		}

		if ( ! is_post_type_hierarchical( $this->screen->post_type ) && 'excerpt' === $mode && current_user_can( 'read_post', $post->ID ) ) {
			the_excerpt();
		}

		get_inline_data( $post );
	}

	public function column_date( $post ) {
		global $mode;

		if ( '0000-00-00 00:00:00' === $post->post_date ) {
			$t_time = $h_time = 'Unpublished';
			$time_diff = 0;
		} else {
			$t_time = get_the_time( 'Y/m/d g:i:s a' );
			$m_time = $post->post_date;
			$time = get_post_time( 'G', true, $post );

			$time_diff = time() - $time;

			if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
				$h_time = sprintf( '%s ago', human_time_diff( $time ) );
			} else {
				$h_time = mysql2date( 'Y/m/d', $m_time );
			}
		}

		if ( 'publish' === $post->post_status ) {
			_e( 'Published' );
		} elseif ( 'future' === $post->post_status ) {
			if ( $time_diff > 0 ) {
				echo '<strong class="error-message">Missed schedule</strong>';
			} else {
				echo 'Scheduled';
			}
		} else {
			echo 'Last Modified';
		}
		echo '<br />';
		if ( 'excerpt' === $mode ) {
			echo apply_filters( 'post_date_column_time', $t_time, $post, 'date', $mode );
		} else {
			echo '<abbr title="' . $t_time . '">' . apply_filters( 'post_date_column_time', $h_time, $post, 'date', $mode ) . '</abbr>';
		}
	}

	public function column_comments( $post ) {
		?>
		<div class="post-com-count-wrapper">
		<?php
			$pending_comments = isset( $this->comment_pending_count[$post->ID] ) ? $this->comment_pending_count[$post->ID] : 0;

			$this->comments_bubble( $post->ID, $pending_comments );
		?>
		</div>
		<?php
	}

	public function column_author( $post ) {
		$args = array(
			'post_type' => $post->post_type,
			'author' => get_the_author_meta( 'ID' )
		);
		echo $this->get_edit_link( $args, get_the_author() );
	}

	public function column_default( $post, $column_name ) {
		if ( 'categories' === $column_name ) {
			$taxonomy = 'category';
		} elseif ( 'tags' === $column_name ) {
			$taxonomy = 'post_tag';
		} elseif ( 0 === strpos( $column_name, 'taxonomy-' ) ) {
			$taxonomy = substr( $column_name, 9 );
		} else {
			$taxonomy = false;
		}
		if ( $taxonomy ) {
			$taxonomy_object = get_taxonomy( $taxonomy );
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( is_array( $terms ) ) {
				$out = array();
				foreach ( $terms as $t ) {
					$posts_in_term_qv = array();
					if ( 'post' != $post->post_type ) {
						$posts_in_term_qv['post_type'] = $post->post_type;
					}
					if ( $taxonomy_object->query_var ) {
						$posts_in_term_qv[ $taxonomy_object->query_var ] = $t->slug;
					} else {
						$posts_in_term_qv['taxonomy'] = $taxonomy;
						$posts_in_term_qv['term'] = $t->slug;
					}

					$label = esc_html( sanitize_term_field( 'name', $t->name, $t->term_id, $taxonomy, 'display' ) );
					$out[] = $this->get_edit_link( $posts_in_term_qv, $label );
				}
				echo join( ', ', $out );
			} else {
				echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . $taxonomy_object->labels->no_terms . '</span>';
			}
			return;
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			do_action( 'manage_pages_custom_column', $column_name, $post->ID );
		} else {
			do_action( 'manage_posts_custom_column', $column_name, $post->ID );
		}
		do_action( "manage_{$post->post_type}_posts_custom_column", $column_name, $post->ID );
	}

	public function single_row( $post, $level = 0 ) {
		$global_post = get_post();

		$post = get_post( $post );
		$this->current_level = $level;

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$classes = 'iedit author-' . ( get_current_user_id() == $post->post_author ? 'self' : 'other' );

		$lock_holder = wp_check_post_lock( $post->ID );
		if ( $lock_holder ) {
			$classes .= ' wp-locked';
		}

		if ( $post->post_parent ) {
		    $count = count( get_post_ancestors( $post->ID ) );
		    $classes .= ' level-'. $count;
		} else {
		    $classes .= ' level-0';
		}
	?>
		<tr id="post-<?php echo $post->ID; ?>" class="<?php echo implode( ' ', get_post_class( $classes, $post->ID ) ); ?>">
			<?php $this->single_row_columns( $post ); ?>
		</tr>
	<?php
		$GLOBALS['post'] = $global_post;
	}

	protected function get_default_primary_column_name() {
		return 'title';
	}

	protected function handle_row_actions( $post, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post = current_user_can( 'edit_post', $post->ID );
		$actions = array();
		$title = _draft_or_post_title();

		if ( $can_edit_post && 'trash' != $post->post_status ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_edit_post_link( $post->ID ),
				esc_attr( sprintf( 'Edit &#8220;%s&#8221;', $title ) ),
				'Edit'
			);
			$actions['inline hide-if-no-js'] = sprintf(
				'<a href="#" class="editinline" aria-label="%s">%s</a>',
				esc_attr( sprintf( 'Quick edit &#8220;%s&#8221; inline', $title ) ),
				'Quick&nbsp;Edit'
			);
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			if ( 'trash' === $post->post_status ) {
				$actions['untrash'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ),
					esc_attr( sprintf( 'Restore &#8220;%s&#8221; from the Trash', $title ) ),
					'Restore'
				);
			} elseif ( EMPTY_TRASH_DAYS ) {
				$actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID ),
					esc_attr( sprintf( 'Move &#8220;%s&#8221; to the Trash', $title ) ),
					'Trash'
				);
			}
			if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID, '', true ),
					esc_attr( sprintf( 'Delete &#8220;%s&#8221; permanently', $title ) ),
					'Delete Permanently'
				);
			}
		}

		if ( is_post_type_viewable( $post_type_object ) ) {
			if ( in_array( $post->post_status, array( 'pending', 'draft', 'future' ) ) ) {
				if ( $can_edit_post ) {
					$preview_link = get_preview_post_link( $post );
					$actions['view'] = sprintf(
						'<a href="%s" rel="permalink" aria-label="%s">%s</a>',
						esc_url( $preview_link ),
						esc_attr( sprintf( 'Preview &#8220;%s&#8221;', $title ) ),
						'Preview'
					);
				}
			} elseif ( 'trash' != $post->post_status ) {
				$actions['view'] = sprintf(
					'<a href="%s" rel="permalink" aria-label="%s">%s</a>',
					get_permalink( $post->ID ),
					esc_attr( sprintf( 'View &#8220;%s&#8221;', $title ) ),
					'View'
				);
			}
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$actions = apply_filters( 'page_row_actions', $actions, $post );
		} else {
			$actions = apply_filters( 'post_row_actions', $actions, $post );
		}

		return $this->row_actions( $actions );
	}

	public function inline_edit() {
		global $mode;

		$screen = $this->screen;

		$post = get_default_post_to_edit( $screen->post_type );
		$post_type_object = get_post_type_object( $screen->post_type );

		$taxonomy_names = get_object_taxonomies( $screen->post_type );
		$hierarchical_taxonomies = array();
		$flat_taxonomies = array();
		foreach ( $taxonomy_names as $taxonomy_name ) {

			$taxonomy = get_taxonomy( $taxonomy_name );

			$show_in_quick_edit = $taxonomy->show_in_quick_edit;

			if ( ! apply_filters( 'quick_edit_show_taxonomy', $show_in_quick_edit, $taxonomy_name, $screen->post_type ) ) {
				continue;
			}

			if ( $taxonomy->hierarchical )
				$hierarchical_taxonomies[] = $taxonomy;
			else
				$flat_taxonomies[] = $taxonomy;
		}

		$m = ( isset( $mode ) && 'excerpt' === $mode ) ? 'excerpt' : 'list';
		$can_publish = current_user_can( $post_type_object->cap->publish_posts );
		$core_columns = array( 'cb' => true, 'date' => true, 'title' => true, 'categories' => true, 'tags' => true, 'comments' => true, 'author' => true );

	?>

	<form method="get"><table style="display: none"><tbody id="inlineedit">
		<?php
		$hclass = count( $hierarchical_taxonomies ) ? 'post' : 'page';
		$bulk = 0;
		while ( $bulk < 2 ) { ?>

		<tr id="<?php echo $bulk ? 'bulk-edit' : 'inline-edit'; ?>" class="inline-edit-row inline-edit-row-<?php echo "$hclass inline-edit-" . $screen->post_type;
			echo $bulk ? " bulk-edit-row bulk-edit-row-$hclass bulk-edit-{$screen->post_type}" : " quick-edit-row quick-edit-row-$hclass inline-edit-{$screen->post_type}";
		?>" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

		<fieldset class="inline-edit-col-left">
			<legend class="inline-edit-legend"><?php echo $bulk ? 'Bulk Edit' : 'Quick Edit'; ?></legend>
			<div class="inline-edit-col">
	<?php

	if ( post_type_supports( $screen->post_type, 'title' ) ) :
		if ( $bulk ) : ?>
			<div id="bulk-title-div">
				<div id="bulk-titles"></div>
			</div>

	<?php else : ?>

			<label>
				<span class="title">Title</span>
				<span class="input-text-wrap"><input type="text" name="post_title" class="ptitle" value="" /></span>
			</label>

			<label>
				<span class="title">Slug</span>
				<span class="input-text-wrap"><input type="text" name="post_name" value="" /></span>
			</label>

	<?php endif;
	endif; ?>

	<?php if ( !$bulk ) : ?>
			<fieldset class="inline-edit-date">
			<legend><span class="title">Date</span></legend>
				<?php touch_time( 1, 1, 0, 1 ); ?>
			</fieldset>
			<br class="clear" />
	<?php endif;

		if ( post_type_supports( $screen->post_type, 'author' ) ) :
			$authors_dropdown = '';

			if ( is_super_admin() || current_user_can( $post_type_object->cap->edit_others_posts ) ) :
				$users_opt = array(
					'hide_if_only_one_author' => false,
					'who' => 'authors',
					'name' => 'post_author',
					'class'=> 'authors',
					'multi' => 1,
					'echo' => 0,
					'show' => 'display_name_with_login',
				);
				if ( $bulk )
					$users_opt['show_option_none'] = '&mdash; No Change &mdash;';

				if ( $authors = wp_dropdown_users( $users_opt ) ) :
					$authors_dropdown  = '<label class="inline-edit-author">';
					$authors_dropdown .= '<span class="title">Author</span>';
					$authors_dropdown .= $authors;
					$authors_dropdown .= '</label>';
				endif;
			endif;
	?>

	<?php if ( !$bulk ) echo $authors_dropdown;
	endif;

	if ( !$bulk && $can_publish ) :
	?>

			<div class="inline-edit-group wp-clearfix">
				<label class="alignleft">
					<span class="title">Password</span>
					<span class="input-text-wrap"><input type="text" name="post_password" class="inline-edit-password-input" value="" /></span>
				</label>

				<em class="alignleft inline-edit-or">&ndash;OR&ndash;</em>
				<label class="alignleft inline-edit-private">
					<input type="checkbox" name="keep_private" value="private" />
					<span class="checkbox-title">Private</span>
				</label>
			</div>

	<?php endif; ?>

		</div></fieldset>

	<?php if ( count( $hierarchical_taxonomies ) && !$bulk ) : ?>

		<fieldset class="inline-edit-col-center inline-edit-categories"><div class="inline-edit-col">

	<?php foreach ( $hierarchical_taxonomies as $taxonomy ) : ?>

			<span class="title inline-edit-categories-label"><?php echo esc_html( $taxonomy->labels->name ) ?></span>
			<input type="hidden" name="<?php echo ( $taxonomy->name === 'category' ) ? 'post_category[]' : 'tax_input[' . esc_attr( $taxonomy->name ) . '][]'; ?>" value="0" />
			<ul class="cat-checklist <?php echo esc_attr( $taxonomy->name )?>-checklist">
				<?php wp_terms_checklist( null, array( 'taxonomy' => $taxonomy->name ) ) ?>
			</ul>

	<?php endforeach; ?>

		</div></fieldset>

	<?php endif; ?>

		<fieldset class="inline-edit-col-right"><div class="inline-edit-col">

	<?php
		if ( post_type_supports( $screen->post_type, 'author' ) && $bulk )
			echo $authors_dropdown;

		if ( post_type_supports( $screen->post_type, 'page-attributes' ) ) :

			if ( $post_type_object->hierarchical ) :
		?>
			<label>
				<span class="title">Parent</span>
	<?php
		$dropdown_args = array(
			'post_type'         => $post_type_object->name,
			'selected'          => $post->post_parent,
			'name'              => 'post_parent',
			'show_option_none'  => 'Main Page (no parent)',
			'option_none_value' => 0,
			'sort_column'       => 'menu_order, post_title',
		);

		if ( $bulk )
			$dropdown_args['show_option_no_change'] =  '&mdash; No Change &mdash;';

		$dropdown_args = apply_filters( 'quick_edit_dropdown_pages_args', $dropdown_args );

		wp_dropdown_pages( $dropdown_args );
	?>
			</label>

	<?php
			endif;

			if ( !$bulk ) : ?>

			<label>
				<span class="title">Order</span>
				<span class="input-text-wrap"><input type="text" name="menu_order" class="inline-edit-menu-order-input" value="<?php echo $post->menu_order ?>" /></span>
			</label>

	<?php	endif;

			if ( 'page' === $screen->post_type ) :
	?>

			<label>
				<span class="title">Template</span>
				<select name="page_template">
	<?php	if ( $bulk ) : ?>
					<option value="-1">&mdash; No Change &mdash;</option>
	<?php	endif; ?>
    				<?php
					$default_title = apply_filters( 'default_page_template_title',  'Default Template', 'quick-edit' );
    				?>
					<option value="default"><?php echo esc_html( $default_title ); ?></option>
					<?php page_template_dropdown() ?>
				</select>
			</label>

	<?php
			endif;
		endif;
	?>

	<?php if ( count( $flat_taxonomies ) && !$bulk ) : ?>

	<?php foreach ( $flat_taxonomies as $taxonomy ) : ?>
		<?php if ( current_user_can( $taxonomy->cap->assign_terms ) ) : ?>
			<label class="inline-edit-tags">
				<span class="title"><?php echo esc_html( $taxonomy->labels->name ) ?></span>
				<textarea cols="22" rows="1" name="tax_input[<?php echo esc_attr( $taxonomy->name )?>]" class="tax_input_<?php echo esc_attr( $taxonomy->name )?>"></textarea>
			</label>
		<?php endif; ?>

	<?php endforeach; ?>

	<?php endif;  ?>

	<?php if ( post_type_supports( $screen->post_type, 'comments' ) || post_type_supports( $screen->post_type, 'trackbacks' ) ) :
		if ( $bulk ) : ?>

			<div class="inline-edit-group wp-clearfix">
		<?php if ( post_type_supports( $screen->post_type, 'comments' ) ) : ?>
			<label class="alignleft">
				<span class="title">Comments</span>
				<select name="comment_status">
					<option value="">&mdash; No Change &mdash;</option>
					<option value="open">Allow</option>
					<option value="closed">Do not allow</option>
				</select>
			</label>
		<?php endif; if ( post_type_supports( $screen->post_type, 'trackbacks' ) ) : ?>
			<label class="alignright">
				<span class="title">Pings</span>
				<select name="ping_status">
					<option value="">&mdash; No Change &mdash;</option>
					<option value="open">Allow</option>
					<option value="closed">Do not allow</option>
				</select>
			</label>
		<?php endif; ?>
			</div>

	<?php else : ?>

			<div class="inline-edit-group wp-clearfix">
			<?php if ( post_type_supports( $screen->post_type, 'comments' ) ) : ?>
				<label class="alignleft">
					<input type="checkbox" name="comment_status" value="open" />
					<span class="checkbox-title">Allow Comments</span>
				</label>
			<?php endif; if ( post_type_supports( $screen->post_type, 'trackbacks' ) ) : ?>
				<label class="alignleft">
					<input type="checkbox" name="ping_status" value="open" />
					<span class="checkbox-title">Allow Pings</span>
				</label>
			<?php endif; ?>
			</div>

	<?php endif;
	endif; ?>

			<div class="inline-edit-group wp-clearfix">
				<label class="inline-edit-status alignleft">
					<span class="title">Status</span>
					<select name="_status">
	<?php if ( $bulk ) : ?>
						<option value="-1">&mdash; No Change &mdash;</option>
	<?php endif; ?>
					<?php if ( $can_publish ) : ?>
						<option value="publish">Published</option>
						<option value="future">Scheduled</option>
	<?php if ( $bulk ) : ?>
						<option value="private">Private</option>
	<?php endif; ?>
					<?php endif; ?>
						<option value="pending">Pending Review</option>
						<option value="draft">Draft</option>
					</select>
				</label>

	<?php if ( 'post' === $screen->post_type && $can_publish && current_user_can( $post_type_object->cap->edit_others_posts ) ) : ?>

	<?php	if ( $bulk ) : ?>

				<label class="alignright">
					<span class="title">Sticky</span>
					<select name="sticky">
						<option value="-1">&mdash; No Change &mdash;</option>
						<option value="sticky">Sticky</option>
						<option value="unsticky">Not Sticky</option>
					</select>
				</label>

	<?php	else : ?>
				<label class="alignleft">
					<input type="checkbox" name="sticky" value="sticky" />
					<span class="checkbox-title">Make this post sticky</span>
				</label>

	<?php	endif; ?>

	<?php endif; ?>

			</div>

	<?php

	if ( $bulk && current_theme_supports( 'post-formats' ) && post_type_supports( $screen->post_type, 'post-formats' ) ) {
		$post_formats = get_theme_support( 'post-formats' );

		?>
		<label class="alignleft">
		<span class="title">Format</span>
		<select name="post_format">
			<option value="-1">&mdash; No Change &mdash;</option>
			<option value="0"><?php echo get_post_format_string( 'standard' ); ?></option>
			<?php
			if ( is_array( $post_formats[0] ) ) {
				foreach ( $post_formats[0] as $format ) {
					?>
					<option value="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( get_post_format_string( $format ) ); ?></option>
					<?php
				}
			}
			?>
		</select></label>
	<?php

	}

	?>

		</div></fieldset>

	<?php
		list( $columns ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			if ( isset( $core_columns[$column_name] ) )
				continue;

			if ( $bulk ) {
				do_action( 'bulk_edit_custom_box', $column_name, $screen->post_type );
			} else {
				do_action( 'quick_edit_custom_box', $column_name, $screen->post_type );
			}

		}
	?>
		<p class="submit inline-edit-save">
			<button type="button" class="button-secondary cancel alignleft">Cancel</button>
			<?php if ( ! $bulk ) {
				wp_nonce_field( 'inlineeditnonce', '_inline_edit', false );
				?>
				<button type="button" class="button-primary save alignright">Update</button>
				<span class="spinner"></span>
			<?php } else {
				submit_button( 'Update', 'button-primary alignright', 'bulk_edit', false );
			} ?>
			<input type="hidden" name="post_view" value="<?php echo esc_attr( $m ); ?>" />
			<input type="hidden" name="screen" value="<?php echo esc_attr( $screen->id ); ?>" />
			<?php if ( ! $bulk && ! post_type_supports( $screen->post_type, 'author' ) ) { ?>
				<input type="hidden" name="post_author" value="<?php echo esc_attr( $post->post_author ); ?>" />
			<?php } ?>
			<span class="error" style="display:none"></span>
			<br class="clear" />
		</p>
		</td></tr>
	<?php
		$bulk++;
		}
?>
		</tbody></table></form>
<?php
	}
}
