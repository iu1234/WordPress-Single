<?php

class WP_Terms_List_Table extends WP_List_Table {

	public $callback_args;

	private $level;

	public function __construct( $args = array() ) {
		global $post_type, $taxonomy, $action, $tax;

		parent::__construct( array(
			'plural' => 'tags',
			'singular' => 'tag',
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
		) );

		$action    = $this->screen->action;
		$post_type = $this->screen->post_type;
		$taxonomy  = $this->screen->taxonomy;

		if ( empty( $taxonomy ) )
			$taxonomy = 'post_tag';

		if ( ! taxonomy_exists( $taxonomy ) )
			wp_die( 'Invalid taxonomy' );

		$tax = get_taxonomy( $taxonomy );

		if ( empty( $post_type ) || !in_array( $post_type, get_post_types( array( 'show_ui' => true ) ) ) )
			$post_type = 'post';

	}

	public function ajax_user_can() {
		return current_user_can( get_taxonomy( $this->screen->taxonomy )->cap->manage_terms );
	}

	public function prepare_items() {
		$tags_per_page = $this->get_items_per_page( 'edit_' . $this->screen->taxonomy . '_per_page' );

		if ( 'post_tag' === $this->screen->taxonomy ) {

			$tags_per_page = apply_filters( 'edit_tags_per_page', $tags_per_page );

			$tags_per_page = apply_filters( 'tagsperpage', $tags_per_page );
		} elseif ( 'category' === $this->screen->taxonomy ) {
			$tags_per_page = apply_filters( 'edit_categories_per_page', $tags_per_page );
		}

		$search = !empty( $_REQUEST['s'] ) ? trim( wp_unslash( $_REQUEST['s'] ) ) : '';

		$args = array(
			'search' => $search,
			'page' => $this->get_pagenum(),
			'number' => $tags_per_page,
		);

		if ( !empty( $_REQUEST['orderby'] ) )
			$args['orderby'] = trim( wp_unslash( $_REQUEST['orderby'] ) );

		if ( !empty( $_REQUEST['order'] ) )
			$args['order'] = trim( wp_unslash( $_REQUEST['order'] ) );

		$this->callback_args = $args;

		$this->set_pagination_args( array(
			'total_items' => wp_count_terms( $this->screen->taxonomy, compact( 'search' ) ),
			'per_page' => $tags_per_page,
		) );
	}

	public function has_items() {
		return true;
	}

	public function no_items() {
		echo get_taxonomy( $this->screen->taxonomy )->labels->not_found;
	}

	protected function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = 'Delete';

		return $actions;
	}

	public function current_action() {
		if ( isset( $_REQUEST['action'] ) && isset( $_REQUEST['delete_tags'] ) && ( 'delete' === $_REQUEST['action'] || 'delete' === $_REQUEST['action2'] ) )
			return 'bulk-delete';
		return parent::current_action();
	}

	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'name'        => 'Name',
			'description' => 'Description',
			'slug'        => 'Slug',
		);

		if ( 'link_category' === $this->screen->taxonomy ) {
			$columns['links'] = 'Links';
		} else {
			$columns['posts'] = 'Count';
		}

		return $columns;
	}

	protected function get_sortable_columns() {
		return array(
			'name'        => 'name',
			'description' => 'description',
			'slug'        => 'slug',
			'posts'       => 'count',
			'links'       => 'count'
		);
	}

	public function display_rows_or_placeholder() {
		$taxonomy = $this->screen->taxonomy;

		$args = wp_parse_args( $this->callback_args, array(
			'page' => 1,
			'number' => 20,
			'search' => '',
			'hide_empty' => 0
		) );

		$page = $args['page'];

		$number = $args['number'];

		$args['offset'] = $offset = ( $page - 1 ) * $number;

		$count = 0;

		if ( is_taxonomy_hierarchical( $taxonomy ) && ! isset( $args['orderby'] ) ) {
			$args['number'] = $args['offset'] = 0;
		}
		$terms = get_terms( $taxonomy, $args );

		if ( empty( $terms ) || ! is_array( $terms ) ) {
			echo '<tr class="no-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">';
			$this->no_items();
			echo '</td></tr>';
			return;
		}

		if ( is_taxonomy_hierarchical( $taxonomy ) && ! isset( $args['orderby'] ) ) {
			if ( ! empty( $args['search'] ) ) {
				$children = array();
			} else {
				$children = _get_term_hierarchy( $taxonomy );
			}
			$this->_rows( $taxonomy, $terms, $children, $offset, $number, $count );
		} else {
			foreach ( $terms as $term ) {
				$this->single_row( $term );
			}
		}
	}

	private function _rows( $taxonomy, $terms, &$children, $start, $per_page, &$count, $parent = 0, $level = 0 ) {

		$end = $start + $per_page;

		foreach ( $terms as $key => $term ) {

			if ( $count >= $end )
				break;

			if ( $term->parent != $parent && empty( $_REQUEST['s'] ) )
				continue;

			if ( $count == $start && $term->parent > 0 && empty( $_REQUEST['s'] ) ) {
				$my_parents = $parent_ids = array();
				$p = $term->parent;
				while ( $p ) {
					$my_parent = get_term( $p, $taxonomy );
					$my_parents[] = $my_parent;
					$p = $my_parent->parent;
					if ( in_array( $p, $parent_ids ) ) // Prevent parent loops.
						break;
					$parent_ids[] = $p;
				}
				unset( $parent_ids );

				$num_parents = count( $my_parents );
				while ( $my_parent = array_pop( $my_parents ) ) {
					echo "\t";
					$this->single_row( $my_parent, $level - $num_parents );
					$num_parents--;
				}
			}

			if ( $count >= $start ) {
				echo "\t";
				$this->single_row( $term, $level );
			}

			++$count;

			unset( $terms[$key] );

			if ( isset( $children[$term->term_id] ) && empty( $_REQUEST['s'] ) )
				$this->_rows( $taxonomy, $terms, $children, $start, $per_page, $count, $term->term_id, $level + 1 );
		}
	}

	public function single_row( $tag, $level = 0 ) {
		global $taxonomy;
 		$tag = sanitize_term( $tag, $taxonomy );

		$this->level = $level;

		echo '<tr id="tag-' . $tag->term_id . '">';
		$this->single_row_columns( $tag );
		echo '</tr>';
	}

	public function column_cb( $tag ) {
		$default_term = get_option( 'default_' . $this->screen->taxonomy );

		if ( current_user_can( get_taxonomy( $this->screen->taxonomy )->cap->delete_terms ) && $tag->term_id != $default_term )
			return '<label class="screen-reader-text" for="cb-select-' . $tag->term_id . '">' . sprintf( 'Select %s', $tag->name ) . '</label>'
				. '<input type="checkbox" name="delete_tags[]" value="' . $tag->term_id . '" id="cb-select-' . $tag->term_id . '" />';

		return '&nbsp;';
	}

	public function column_name( $tag ) {
		$taxonomy = $this->screen->taxonomy;

		$pad = str_repeat( '&#8212; ', max( 0, $this->level ) );

		$name = apply_filters( 'term_name', $pad . ' ' . $tag->name, $tag );

		$qe_data = get_term( $tag->term_id, $taxonomy, OBJECT, 'edit' );

		$uri = ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ? wp_get_referer() : $_SERVER['REQUEST_URI'];

		$edit_link = add_query_arg(
			'wp_http_referer',
			urlencode( wp_unslash( $uri ) ),
			get_edit_term_link( $tag->term_id, $taxonomy, $this->screen->post_type )
		);

		$out = sprintf(
			'<strong><a class="row-title" href="%s" aria-label="%s">%s</a></strong><br />',
			esc_url( $edit_link ),
			esc_attr( sprintf( '&#8220;%s&#8221; (Edit)', $tag->name ) ),
			$name
		);

		$out .= '<div class="hidden" id="inline_' . $qe_data->term_id . '">';
		$out .= '<div class="name">' . $qe_data->name . '</div>';
		$out .= '<div class="slug">' . apply_filters( 'editable_slug', $qe_data->slug, $qe_data ) . '</div>';
		$out .= '<div class="parent">' . $qe_data->parent . '</div></div>';

		return $out;
	}

	protected function get_default_primary_column_name() {
		return 'name';
	}

	protected function handle_row_actions( $tag, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$taxonomy = $this->screen->taxonomy;
		$tax = get_taxonomy( $taxonomy );
		$default_term = get_option( 'default_' . $taxonomy );

		$uri = ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ? wp_get_referer() : $_SERVER['REQUEST_URI'];

		$edit_link = add_query_arg(
			'wp_http_referer',
			urlencode( wp_unslash( $uri ) ),
			get_edit_term_link( $tag->term_id, $taxonomy, $this->screen->post_type )
		);

		$actions = array();
		if ( current_user_can( $tax->cap->edit_terms ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( $edit_link ),
				esc_attr( sprintf( 'Edit &#8220;%s&#8221;', $tag->name ) ),
				'Edit'
			);
			$actions['inline hide-if-no-js'] = sprintf(
				'<a href="#" class="editinline aria-button-if-js" aria-label="%s">%s</a>',
				esc_attr( sprintf( 'Quick edit &#8220;%s&#8221; inline', $tag->name ) ),
				'Quick&nbsp;Edit'
			);
		}
		if ( current_user_can( $tax->cap->delete_terms ) && $tag->term_id != $default_term ) {
			$actions['delete'] = sprintf(
				'<a href="%s" class="delete-tag aria-button-if-js" aria-label="%s">%s</a>',
				wp_nonce_url( "edit-tags.php?action=delete&amp;taxonomy=$taxonomy&amp;tag_ID=$tag->term_id", 'delete-tag_' . $tag->term_id ),
				esc_attr( sprintf( 'Delete &#8220;%s&#8221;', $tag->name ) ),
				'Delete'
			);
		}
		if ( $tax->public ) {
			$actions['view'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_term_link( $tag ),
				esc_attr( sprintf( 'View &#8220;%s&#8221; archive', $tag->name ) ),
				'View'
			);
		}

		$actions = apply_filters( 'tag_row_actions', $actions, $tag );

		$actions = apply_filters( "{$taxonomy}_row_actions", $actions, $tag );

		return $this->row_actions( $actions );
	}

	public function column_description( $tag ) {
		return $tag->description;
	}

	public function column_slug( $tag ) {
		return apply_filters( 'editable_slug', $tag->slug, $tag );
	}

	public function column_posts( $tag ) {
		$count = number_format_i18n( $tag->count );

		$tax = get_taxonomy( $this->screen->taxonomy );

		$ptype_object = get_post_type_object( $this->screen->post_type );
		if ( ! $ptype_object->show_ui )
			return $count;

		if ( $tax->query_var ) {
			$args = array( $tax->query_var => $tag->slug );
		} else {
			$args = array( 'taxonomy' => $tax->name, 'term' => $tag->slug );
		}

		if ( 'post' != $this->screen->post_type )
			$args['post_type'] = $this->screen->post_type;

		if ( 'attachment' === $this->screen->post_type )
			return "<a href='" . esc_url ( add_query_arg( $args, 'upload.php' ) ) . "'>$count</a>";

		return "<a href='" . esc_url ( add_query_arg( $args, 'edit.php' ) ) . "'>$count</a>";
	}

	public function column_links( $tag ) {
		$count = number_format_i18n( $tag->count );
		if ( $count )
			$count = "<a href='link-manager.php?cat_id=$tag->term_id'>$count</a>";
		return $count;
	}

	public function column_default( $tag, $column_name ) {
		return apply_filters( "manage_{$this->screen->taxonomy}_custom_column", '', $column_name, $tag->term_id );
	}

	public function inline_edit() {
		$tax = get_taxonomy( $this->screen->taxonomy );
		if ( ! current_user_can( $tax->cap->edit_terms ) )
			return;
?>

	<form method="get"><table style="display: none"><tbody id="inlineedit">
		<tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

			<fieldset>
				<legend class="inline-edit-legend">Quick Edit</legend>
				<div class="inline-edit-col">
				<label>
					<span class="title">Name</span>
					<span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" /></span>
				</label>
	<?php if ( !global_terms_enabled() ) { ?>
				<label>
					<span class="title">Slug</span>
					<span class="input-text-wrap"><input type="text" name="slug" class="ptitle" value="" /></span>
				</label>
	<?php } ?>
			</div></fieldset>
	<?php

		$core_columns = array( 'cb' => true, 'description' => true, 'name' => true, 'slug' => true, 'posts' => true );

		list( $columns ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			if ( isset( $core_columns[$column_name] ) )
				continue;
			do_action( 'quick_edit_custom_box', $column_name, 'edit-tags', $this->screen->taxonomy );
		}

	?>

		<p class="inline-edit-save submit">
			<button type="button" class="cancel button-secondary alignleft">Cancel</button>
			<button type="button" class="save button-primary alignright"><?php echo $tax->labels->update_item; ?></button>
			<span class="spinner"></span>
			<span class="error" style="display:none;"></span>
			<?php wp_nonce_field( 'taxinlineeditnonce', '_inline_edit', false ); ?>
			<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $this->screen->taxonomy ); ?>" />
			<input type="hidden" name="post_type" value="<?php echo esc_attr( $this->screen->post_type ); ?>" />
			<br class="clear" />
		</p>
		</td></tr>
		</tbody></table></form>
	<?php
	}
}
