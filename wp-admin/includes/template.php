<?php
/**
 * Template WordPress Administration API.
 *
 * A Big Mess. Also some neat functions that are nicely written.
 *
 * @package WordPress
 * @subpackage Administration
 */

require_once( ABSPATH . 'wp-admin/includes/class-walker-category-checklist.php' );

require_once( ABSPATH . 'wp-admin/includes/class-wp-internal-pointers.php' );

function wp_category_checklist( $post_id = 0, $descendants_and_self = 0, $selected_cats = false, $popular_cats = false, $walker = null, $checked_ontop = true ) {
	wp_terms_checklist( $post_id, array(
		'taxonomy' => 'category',
		'descendants_and_self' => $descendants_and_self,
		'selected_cats' => $selected_cats,
		'popular_cats' => $popular_cats,
		'walker' => $walker,
		'checked_ontop' => $checked_ontop
	) );
}

function wp_terms_checklist( $post_id = 0, $args = array() ) {
 	$defaults = array(
		'descendants_and_self' => 0,
		'selected_cats' => false,
		'popular_cats' => false,
		'walker' => null,
		'taxonomy' => 'category',
		'checked_ontop' => true,
		'echo' => true,
	);

	$params = apply_filters( 'wp_terms_checklist_args', $args, $post_id );

	$r = wp_parse_args( $params, $defaults );

	if ( empty( $r['walker'] ) || ! ( $r['walker'] instanceof Walker ) ) {
		$walker = new Walker_Category_Checklist;
	} else {
		$walker = $r['walker'];
	}

	$taxonomy = $r['taxonomy'];
	$descendants_and_self = (int) $r['descendants_and_self'];

	$args = array( 'taxonomy' => $taxonomy );

	$tax = get_taxonomy( $taxonomy );
	$args['disabled'] = ! current_user_can( $tax->cap->assign_terms );

	$args['list_only'] = ! empty( $r['list_only'] );

	if ( is_array( $r['selected_cats'] ) ) {
		$args['selected_cats'] = $r['selected_cats'];
	} elseif ( $post_id ) {
		$args['selected_cats'] = wp_get_object_terms( $post_id, $taxonomy, array_merge( $args, array( 'fields' => 'ids' ) ) );
	} else {
		$args['selected_cats'] = array();
	}
	if ( is_array( $r['popular_cats'] ) ) {
		$args['popular_cats'] = $r['popular_cats'];
	} else {
		$args['popular_cats'] = get_terms( $taxonomy, array(
			'fields' => 'ids',
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 10,
			'hierarchical' => false
		) );
	}
	if ( $descendants_and_self ) {
		$categories = (array) get_terms( $taxonomy, array(
			'child_of' => $descendants_and_self,
			'hierarchical' => 0,
			'hide_empty' => 0
		) );
		$self = get_term( $descendants_and_self, $taxonomy );
		array_unshift( $categories, $self );
	} else {
		$categories = (array) get_terms( $taxonomy, array( 'get' => 'all' ) );
	}

	$output = '';

	if ( $r['checked_ontop'] ) {
		// Post process $categories rather than adding an exclude to the get_terms() query to keep the query the same across all posts (for any query cache)
		$checked_categories = array();
		$keys = array_keys( $categories );

		foreach ( $keys as $k ) {
			if ( in_array( $categories[$k]->term_id, $args['selected_cats'] ) ) {
				$checked_categories[] = $categories[$k];
				unset( $categories[$k] );
			}
		}

		// Put checked cats on top
		$output .= call_user_func_array( array( $walker, 'walk' ), array( $checked_categories, 0, $args ) );
	}
	// Then the rest of them
	$output .= call_user_func_array( array( $walker, 'walk' ), array( $categories, 0, $args ) );

	if ( $r['echo'] ) {
		echo $output;
	}

	return $output;
}

function wp_popular_terms_checklist( $taxonomy, $default = 0, $number = 10, $echo = true ) {
	$post = get_post();

	if ( $post && $post->ID )
		$checked_terms = wp_get_object_terms($post->ID, $taxonomy, array('fields'=>'ids'));
	else
		$checked_terms = array();

	$terms = get_terms( $taxonomy, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => $number, 'hierarchical' => false ) );

	$tax = get_taxonomy($taxonomy);

	$popular_ids = array();
	foreach ( (array) $terms as $term ) {
		$popular_ids[] = $term->term_id;
		if ( !$echo ) // hack for AJAX use
			continue;
		$id = "popular-$taxonomy-$term->term_id";
		$checked = in_array( $term->term_id, $checked_terms ) ? 'checked="checked"' : '';
		?>

		<li id="<?php echo $id; ?>" class="popular-category">
			<label class="selectit">
				<input id="in-<?php echo $id; ?>" type="checkbox" <?php echo $checked; ?> value="<?php echo (int) $term->term_id; ?>" <?php disabled( ! current_user_can( $tax->cap->assign_terms ) ); ?> />
				<?php
				/** This filter is documented in wp-includes/category-template.php */
				echo esc_html( apply_filters( 'the_category', $term->name ) );
				?>
			</label>
		</li>

		<?php
	}
	return $popular_ids;
}

function wp_link_category_checklist( $link_id = 0 ) {
	$default = 1;

	$checked_categories = array();

	if ( $link_id ) {
		$checked_categories = wp_get_link_cats( $link_id );
		// No selected categories, strange
		if ( ! count( $checked_categories ) ) {
			$checked_categories[] = $default;
		}
	} else {
		$checked_categories[] = $default;
	}

	$categories = get_terms( 'link_category', array( 'orderby' => 'name', 'hide_empty' => 0 ) );

	if ( empty( $categories ) )
		return;

	foreach ( $categories as $category ) {
		$cat_id = $category->term_id;

		/** This filter is documented in wp-includes/category-template.php */
		$name = esc_html( apply_filters( 'the_category', $category->name ) );
		$checked = in_array( $cat_id, $checked_categories ) ? ' checked="checked"' : '';
		echo '<li id="link-category-', $cat_id, '"><label for="in-link-category-', $cat_id, '" class="selectit"><input value="', $cat_id, '" type="checkbox" name="link_category[]" id="in-link-category-', $cat_id, '"', $checked, '/> ', $name, "</label></li>";
	}
}

function get_inline_data($post) {
	$post_type_object = get_post_type_object($post->post_type);
	if ( ! current_user_can( 'edit_post', $post->ID ) )
		return;

	$title = esc_textarea( trim( $post->post_title ) );

	/** This filter is documented in wp-admin/edit-tag-form.php */
	echo '
<div class="hidden" id="inline_' . $post->ID . '">
	<div class="post_title">' . $title . '</div>' .
	/** This filter is documented in wp-admin/edit-tag-form.php */
	'<div class="post_name">' . apply_filters( 'editable_slug', $post->post_name, $post ) . '</div>
	<div class="post_author">' . $post->post_author . '</div>
	<div class="comment_status">' . esc_html( $post->comment_status ) . '</div>
	<div class="ping_status">' . esc_html( $post->ping_status ) . '</div>
	<div class="_status">' . esc_html( $post->post_status ) . '</div>
	<div class="jj">' . mysql2date( 'd', $post->post_date, false ) . '</div>
	<div class="mm">' . mysql2date( 'm', $post->post_date, false ) . '</div>
	<div class="aa">' . mysql2date( 'Y', $post->post_date, false ) . '</div>
	<div class="hh">' . mysql2date( 'H', $post->post_date, false ) . '</div>
	<div class="mn">' . mysql2date( 'i', $post->post_date, false ) . '</div>
	<div class="ss">' . mysql2date( 's', $post->post_date, false ) . '</div>
	<div class="post_password">' . esc_html( $post->post_password ) . '</div>';

	if ( $post_type_object->hierarchical )
		echo '<div class="post_parent">' . $post->post_parent . '</div>';

	if ( $post->post_type == 'page' )
		echo '<div class="page_template">' . esc_html( get_post_meta( $post->ID, '_wp_page_template', true ) ) . '</div>';

	if ( post_type_supports( $post->post_type, 'page-attributes' ) )
		echo '<div class="menu_order">' . $post->menu_order . '</div>';

	$taxonomy_names = get_object_taxonomies( $post->post_type );
	foreach ( $taxonomy_names as $taxonomy_name) {
		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( $taxonomy->hierarchical && $taxonomy->show_ui ) {

			$terms = get_object_term_cache( $post->ID, $taxonomy_name );
			if ( false === $terms ) {
				$terms = wp_get_object_terms( $post->ID, $taxonomy_name );
				wp_cache_add( $post->ID, $terms, $taxonomy_name . '_relationships' );
			}
			$term_ids = empty( $terms ) ? array() : wp_list_pluck( $terms, 'term_id' );

			echo '<div class="post_category" id="' . $taxonomy_name . '_' . $post->ID . '">' . implode( ',', $term_ids ) . '</div>';

		} elseif ( $taxonomy->show_ui ) {

			$terms_to_edit = get_terms_to_edit( $post->ID, $taxonomy_name );
			if ( ! is_string( $terms_to_edit ) ) {
				$terms_to_edit = '';
			}

			echo '<div class="tags_input" id="'.$taxonomy_name.'_'.$post->ID.'">'
				. esc_html( str_replace( ',', ', ', $terms_to_edit ) ) . '</div>';

		}
	}

	if ( !$post_type_object->hierarchical )
		echo '<div class="sticky">' . (is_sticky($post->ID) ? 'sticky' : '') . '</div>';

	if ( post_type_supports( $post->post_type, 'post-formats' ) )
		echo '<div class="post_format">' . esc_html( get_post_format( $post->ID ) ) . '</div>';

	echo '</div>';
}

function wp_comment_reply( $position = 1, $checkbox = false, $mode = 'single', $table_row = true ) {
	global $wp_list_table;

	$content = apply_filters( 'wp_comment_reply', '', array( 'position' => $position, 'checkbox' => $checkbox, 'mode' => $mode ) );

	if ( ! empty($content) ) {
		echo $content;
		return;
	}

	if ( ! $wp_list_table ) {
		if ( $mode == 'single' ) {
			$wp_list_table = _get_list_table('WP_Post_Comments_List_Table');
		} else {
			$wp_list_table = _get_list_table('WP_Comments_List_Table');
		}
	}

?>
<form method="get">
<?php if ( $table_row ) : ?>
<table style="display:none;"><tbody id="com-reply"><tr id="replyrow" class="inline-edit-row" style="display:none;"><td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="colspanchange">
<?php else : ?>
<div id="com-reply" style="display:none;"><div id="replyrow" style="display:none;">
<?php endif; ?>
	<fieldset class="comment-reply">
	<legend>
		<span class="hidden" id="editlegend">Edit Comment</span>
		<span class="hidden" id="replyhead">Reply to Comment</span>
		<span class="hidden" id="addhead">Add new Comment</span>
	</legend>

	<div id="replycontainer">
	<label for="replycontent" class="screen-reader-text">Comment</label>
	<?php
	$quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' );
	wp_editor( '', 'replycontent', array( 'media_buttons' => false, 'tinymce' => false, 'quicktags' => $quicktags_settings ) );
	?>
	</div>

	<div id="edithead" style="display:none;">
		<div class="inside">
		<label for="author-name">Name</label>
		<input type="text" name="newcomment_author" size="50" value="" id="author-name" />
		</div>

		<div class="inside">
		<label for="author-email">Email</label>
		<input type="text" name="newcomment_author_email" size="50" value="" id="author-email" />
		</div>

		<div class="inside">
		<label for="author-url">URL</label>
		<input type="text" id="author-url" name="newcomment_author_url" class="code" size="103" value="" />
		</div>
	</div>

	<p id="replysubmit" class="submit">
	<a href="#comments-form" class="save button-primary alignright">
	<span id="addbtn" style="display:none;">Add Comment</span>
	<span id="savebtn" style="display:none;">Update Comment</span>
	<span id="replybtn" style="display:none;">Submit Reply</span></a>
	<a href="#comments-form" class="cancel button-secondary alignleft">Cancel</a>
	<span class="waiting spinner"></span>
	<span class="error" style="display:none;"></span>
	</p>

	<input type="hidden" name="action" id="action" value="" />
	<input type="hidden" name="comment_ID" id="comment_ID" value="" />
	<input type="hidden" name="comment_post_ID" id="comment_post_ID" value="" />
	<input type="hidden" name="status" id="status" value="" />
	<input type="hidden" name="position" id="position" value="<?php echo $position; ?>" />
	<input type="hidden" name="checkbox" id="checkbox" value="<?php echo $checkbox ? 1 : 0; ?>" />
	<input type="hidden" name="mode" id="mode" value="<?php echo esc_attr($mode); ?>" />
	<?php
		wp_nonce_field( 'replyto-comment', '_ajax_nonce-replyto-comment', false );
		if ( current_user_can( 'unfiltered_html' ) )
			wp_nonce_field( 'unfiltered-html-comment', '_wp_unfiltered_html_comment', false );
	?>
	</fieldset>
<?php if ( $table_row ) : ?>
</td></tr></tbody></table>
<?php else : ?>
</div></div>
<?php endif; ?>
</form>
<?php
}

function wp_comment_trashnotice() {
?>
<div class="hidden" id="trash-undo-holder">
	<div class="trash-undo-inside"><?php printf('Comment by %s moved to the trash.', '<strong></strong>'); ?> <span class="undo untrash"><a href="#">Undo</a></span></div>
</div>
<div class="hidden" id="spam-undo-holder">
	<div class="spam-undo-inside"><?php printf('Comment by %s marked as spam.', '<strong></strong>'); ?> <span class="undo unspam"><a href="#">Undo</a></span></div>
</div>
<?php
}

function list_meta( $meta ) {
	if ( ! $meta ) {
		echo '
<table id="list-table" style="display: none;">
	<thead>
	<tr>
		<th class="left">' . _x( 'Name', 'meta name' ) . '</th>
		<th>Value</th>
	</tr>
	</thead>
	<tbody id="the-list" data-wp-lists="list:meta">
	<tr><td></td></tr>
	</tbody>
</table>'; //TBODY needed for list-manipulation JS
		return;
	}
	$count = 0;
?>
<table id="list-table">
	<thead>
	<tr>
		<th class="left"><?php _ex( 'Name', 'meta name' ) ?></th>
		<th><?php _e( 'Value' ) ?></th>
	</tr>
	</thead>
	<tbody id='the-list' data-wp-lists='list:meta'>
<?php
	foreach ( $meta as $entry )
		echo _list_meta_row( $entry, $count );
?>
	</tbody>
</table>
<?php
}

function _list_meta_row( $entry, &$count ) {
	static $update_nonce = '';

	if ( is_protected_meta( $entry['meta_key'], 'post' ) )
		return '';

	if ( ! $update_nonce )
		$update_nonce = wp_create_nonce( 'add-meta' );

	$r = '';
	++ $count;

	if ( is_serialized( $entry['meta_value'] ) ) {
		if ( is_serialized_string( $entry['meta_value'] ) ) {
			// This is a serialized string, so we should display it.
			$entry['meta_value'] = maybe_unserialize( $entry['meta_value'] );
		} else {
			// This is a serialized array/object so we should NOT display it.
			--$count;
			return '';
		}
	}

	$entry['meta_key'] = esc_attr($entry['meta_key']);
	$entry['meta_value'] = esc_textarea( $entry['meta_value'] ); // using a <textarea />
	$entry['meta_id'] = (int) $entry['meta_id'];

	$delete_nonce = wp_create_nonce( 'delete-meta_' . $entry['meta_id'] );

	$r .= "\n\t<tr id='meta-{$entry['meta_id']}'>";
	$r .= "\n\t\t<td class='left'><label class='screen-reader-text' for='meta-{$entry['meta_id']}-key'>" . __( 'Key' ) . "</label><input name='meta[{$entry['meta_id']}][key]' id='meta-{$entry['meta_id']}-key' type='text' size='20' value='{$entry['meta_key']}' />";

	$r .= "\n\t\t<div class='submit'>";
	$r .= get_submit_button( 'Delete', 'deletemeta small', "deletemeta[{$entry['meta_id']}]", false, array( 'data-wp-lists' => "delete:the-list:meta-{$entry['meta_id']}::_ajax_nonce=$delete_nonce" ) );
	$r .= "\n\t\t";
	$r .= get_submit_button( 'Update', 'updatemeta small', "meta-{$entry['meta_id']}-submit", false, array( 'data-wp-lists' => "add:the-list:meta-{$entry['meta_id']}::_ajax_nonce-add-meta=$update_nonce" ) );
	$r .= "</div>";
	$r .= wp_nonce_field( 'change-meta', '_ajax_nonce', false, false );
	$r .= "</td>";

	$r .= "\n\t\t<td><label class='screen-reader-text' for='meta-{$entry['meta_id']}-value'>Value</label><textarea name='meta[{$entry['meta_id']}][value]' id='meta-{$entry['meta_id']}-value' rows='2' cols='30'>{$entry['meta_value']}</textarea></td>\n\t</tr>";
	return $r;
}

function meta_form( $post = null ) {
	global $wpdb;
	$post = get_post( $post );

	$keys = apply_filters( 'postmeta_form_keys', null, $post );

	if ( null === $keys ) {
		$limit = apply_filters( 'postmeta_form_limit', 30 );
		$sql = "SELECT DISTINCT meta_key
			FROM $wpdb->postmeta
			WHERE meta_key NOT BETWEEN '_' AND '_z'
			HAVING meta_key NOT LIKE %s
			ORDER BY meta_key
			LIMIT %d";
		$keys = $wpdb->get_col( $wpdb->prepare( $sql, $wpdb->esc_like( '_' ) . '%', $limit ) );
	}

	if ( $keys ) {
		natcasesort( $keys );
		$meta_key_input_id = 'metakeyselect';
	} else {
		$meta_key_input_id = 'metakeyinput';
	}
?>
<p><strong>Add New Custom Field:</strong></p>
<table id="newmeta">
<thead>
<tr>
<th class="left"><label for="<?php echo $meta_key_input_id; ?>"><?php _ex( 'Name', 'meta name' ) ?></label></th>
<th><label for="metavalue">Value</label></th>
</tr>
</thead>

<tbody>
<tr>
<td id="newmetaleft" class="left">
<?php if ( $keys ) { ?>
<select id="metakeyselect" name="metakeyselect">
<option value="#NONE#"><?php _e( '&mdash; Select &mdash;' ); ?></option>
<?php

	foreach ( $keys as $key ) {
		if ( is_protected_meta( $key, 'post' ) || ! current_user_can( 'add_post_meta', $post->ID, $key ) )
			continue;
		echo "\n<option value='" . esc_attr($key) . "'>" . esc_html($key) . "</option>";
	}
?>
</select>
<input class="hide-if-js" type="text" id="metakeyinput" name="metakeyinput" value="" />
<a href="#postcustomstuff" class="hide-if-no-js" onclick="jQuery('#metakeyinput, #metakeyselect, #enternew, #cancelnew').toggle();return false;">
<span id="enternew"><?php _e('Enter new'); ?></span>
<span id="cancelnew" class="hidden"><?php _e('Cancel'); ?></span></a>
<?php } else { ?>
<input type="text" id="metakeyinput" name="metakeyinput" value="" />
<?php } ?>
</td>
<td><textarea id="metavalue" name="metavalue" rows="2" cols="25"></textarea></td>
</tr>

<tr><td colspan="2">
<div class="submit">
<?php submit_button( 'Add Custom Field', 'secondary', 'addmeta', false, array( 'id' => 'newmeta-submit', 'data-wp-lists' => 'add:the-list:newmeta' ) ); ?>
</div>
<?php wp_nonce_field( 'add-meta', '_ajax_nonce-add-meta', false ); ?>
</td></tr>
</tbody>
</table>
<?php

}

function touch_time( $edit = 1, $for_post = 1, $tab_index = 0, $multi = 0 ) {
	global $wp_locale;
	$post = get_post();

	if ( $for_post )
		$edit = ! ( in_array($post->post_status, array('draft', 'pending') ) && (!$post->post_date_gmt || '0000-00-00 00:00:00' == $post->post_date_gmt ) );

	$tab_index_attribute = '';
	if ( (int) $tab_index > 0 )
		$tab_index_attribute = " tabindex=\"$tab_index\"";

	// todo: Remove this?
	// echo '<label for="timestamp" style="display: block;"><input type="checkbox" class="checkbox" name="edit_date" value="1" id="timestamp"'.$tab_index_attribute.' /> '.__( 'Edit timestamp' ).'</label><br />';

	$time_adj = current_time('timestamp');
	$post_date = ($for_post) ? $post->post_date : get_comment()->comment_date;
	$jj = ($edit) ? mysql2date( 'd', $post_date, false ) : gmdate( 'd', $time_adj );
	$mm = ($edit) ? mysql2date( 'm', $post_date, false ) : gmdate( 'm', $time_adj );
	$aa = ($edit) ? mysql2date( 'Y', $post_date, false ) : gmdate( 'Y', $time_adj );
	$hh = ($edit) ? mysql2date( 'H', $post_date, false ) : gmdate( 'H', $time_adj );
	$mn = ($edit) ? mysql2date( 'i', $post_date, false ) : gmdate( 'i', $time_adj );
	$ss = ($edit) ? mysql2date( 's', $post_date, false ) : gmdate( 's', $time_adj );

	$cur_jj = gmdate( 'd', $time_adj );
	$cur_mm = gmdate( 'm', $time_adj );
	$cur_aa = gmdate( 'Y', $time_adj );
	$cur_hh = gmdate( 'H', $time_adj );
	$cur_mn = gmdate( 'i', $time_adj );

	$month = '<label><span class="screen-reader-text">Month</span><select ' . ( $multi ? '' : 'id="mm" ' ) . 'name="mm"' . $tab_index_attribute . ">\n";
	for ( $i = 1; $i < 13; $i = $i +1 ) {
		$monthnum = zeroise($i, 2);
		$monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
		$month .= "\t\t\t" . '<option value="' . $monthnum . '" data-text="' . $monthtext . '" ' . selected( $monthnum, $mm, false ) . '>';
		$month .= sprintf( '%1$s-%2$s', $monthnum, $monthtext ) . "</option>\n";
	}
	$month .= '</select></label>';

	$day = '<label><span class="screen-reader-text">Day</span><input type="text" ' . ( $multi ? '' : 'id="jj" ' ) . 'name="jj" value="' . $jj . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" /></label>';
	$year = '<label><span class="screen-reader-text">Year</span><input type="text" ' . ( $multi ? '' : 'id="aa" ' ) . 'name="aa" value="' . $aa . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" /></label>';
	$hour = '<label><span class="screen-reader-text">Hour</span><input type="text" ' . ( $multi ? '' : 'id="hh" ' ) . 'name="hh" value="' . $hh . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" /></label>';
	$minute = '<label><span class="screen-reader-text">Minute</span><input type="text" ' . ( $multi ? '' : 'id="mn" ' ) . 'name="mn" value="' . $mn . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" /></label>';

	echo '<div class="timestamp-wrap">';
	/* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
	printf( '%1$s %2$s, %3$s @ %4$s:%5$s', $month, $day, $year, $hour, $minute );

	echo '</div><input type="hidden" id="ss" name="ss" value="' . $ss . '" />';

	if ( $multi ) return;

	echo "\n\n";
	$map = array(
		'mm' => array( $mm, $cur_mm ),
		'jj' => array( $jj, $cur_jj ),
		'aa' => array( $aa, $cur_aa ),
		'hh' => array( $hh, $cur_hh ),
		'mn' => array( $mn, $cur_mn ),
	);
	foreach ( $map as $timeunit => $value ) {
		list( $unit, $curr ) = $value;

		echo '<input type="hidden" id="hidden_' . $timeunit . '" name="hidden_' . $timeunit . '" value="' . $unit . '" />' . "\n";
		$cur_timeunit = 'cur_' . $timeunit;
		echo '<input type="hidden" id="' . $cur_timeunit . '" name="' . $cur_timeunit . '" value="' . $curr . '" />' . "\n";
	}
?>

<p>
<a href="#edit_timestamp" class="save-timestamp hide-if-no-js button"><?php _e('OK'); ?></a>
<a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js button-cancel"><?php _e('Cancel'); ?></a>
</p>
<?php
}

function page_template_dropdown( $default = '' ) {
	$templates = get_page_templates( get_post() );
	ksort( $templates );
	foreach ( array_keys( $templates ) as $template ) {
		$selected = selected( $default, $templates[ $template ], false );
		echo "\n\t<option value='" . $templates[ $template ] . "' $selected>$template</option>";
	}
}

function parent_dropdown( $default = 0, $parent = 0, $level = 0, $post = null ) {
	global $wpdb;
	$post = get_post( $post );
	$items = $wpdb->get_results( $wpdb->prepare("SELECT ID, post_parent, post_title FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'page' ORDER BY menu_order", $parent) );

	if ( $items ) {
		foreach ( $items as $item ) {
			// A page cannot be its own parent.
			if ( $post && $post->ID && $item->ID == $post->ID )
				continue;

			$pad = str_repeat( '&nbsp;', $level * 3 );
			$selected = selected( $default, $item->ID, false );

			echo "\n\t<option class='level-$level' value='$item->ID' $selected>$pad " . esc_html($item->post_title) . "</option>";
			parent_dropdown( $default, $item->ID, $level +1 );
		}
	} else {
		return false;
	}
}

function wp_dropdown_roles( $selected = '' ) {
	$p = '';
	$r = '';

	$editable_roles = array_reverse( get_editable_roles() );

	foreach ( $editable_roles as $role => $details ) {
		$name = translate_user_role($details['name'] );
		if ( $selected == $role ) // preselect specified role
			$p = "\n\t<option selected='selected' value='" . esc_attr($role) . "'>$name</option>";
		else
			$r .= "\n\t<option value='" . esc_attr($role) . "'>$name</option>";
	}
	echo $p . $r;
}

function wp_import_upload_form( $action ) {

	$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
	$size = size_format( $bytes );
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) ) :
		?><div class="error"><p>Before you can upload your import file, you will need to fix the following error:</p>
		<p><strong><?php echo $upload_dir['error']; ?></strong></p></div><?php
	else :
?>
<form enctype="multipart/form-data" id="import-upload-form" method="post" class="wp-upload-form" action="<?php echo esc_url( wp_nonce_url( $action, 'import-upload' ) ); ?>">
<p>
<label for="upload">Choose a file from your computer:</label> (<?php printf( 'Maximum size: %s', $size ); ?>)
<input type="file" id="upload" name="import" size="25" />
<input type="hidden" name="action" value="save" />
<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
</p>
<?php submit_button( 'Upload file and import', 'primary' ); ?>
</form>
<?php
	endif;
}

function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {
	global $wp_meta_boxes;

	if ( empty( $screen ) ) {
		$screen = get_current_screen();
	} elseif ( is_string( $screen ) ) {
		$screen = convert_to_screen( $screen );
	} elseif ( is_array( $screen ) ) {
		foreach ( $screen as $single_screen ) {
			add_meta_box( $id, $title, $callback, $single_screen, $context, $priority, $callback_args );
		}
	}

	if ( ! isset( $screen->id ) ) {
		return;
	}

	$page = $screen->id;

	if ( !isset($wp_meta_boxes) )
		$wp_meta_boxes = array();
	if ( !isset($wp_meta_boxes[$page]) )
		$wp_meta_boxes[$page] = array();
	if ( !isset($wp_meta_boxes[$page][$context]) )
		$wp_meta_boxes[$page][$context] = array();

	foreach ( array_keys($wp_meta_boxes[$page]) as $a_context ) {
		foreach ( array('high', 'core', 'default', 'low') as $a_priority ) {
			if ( !isset($wp_meta_boxes[$page][$a_context][$a_priority][$id]) )
				continue;

			// If a core box was previously added or removed by a plugin, don't add.
			if ( 'core' == $priority ) {
				// If core box previously deleted, don't add
				if ( false === $wp_meta_boxes[$page][$a_context][$a_priority][$id] )
					return;
				if ( 'default' == $a_priority ) {
					$wp_meta_boxes[$page][$a_context]['core'][$id] = $wp_meta_boxes[$page][$a_context]['default'][$id];
					unset($wp_meta_boxes[$page][$a_context]['default'][$id]);
				}
				return;
			}
			if ( empty($priority) ) {
				$priority = $a_priority;
			} elseif ( 'sorted' == $priority ) {
				$title = $wp_meta_boxes[$page][$a_context][$a_priority][$id]['title'];
				$callback = $wp_meta_boxes[$page][$a_context][$a_priority][$id]['callback'];
				$callback_args = $wp_meta_boxes[$page][$a_context][$a_priority][$id]['args'];
			}
			if ( $priority != $a_priority || $context != $a_context )
				unset($wp_meta_boxes[$page][$a_context][$a_priority][$id]);
		}
	}

	if ( empty($priority) )
		$priority = 'low';

	if ( !isset($wp_meta_boxes[$page][$context][$priority]) )
		$wp_meta_boxes[$page][$context][$priority] = array();

	$wp_meta_boxes[$page][$context][$priority][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $callback_args);
}

function do_meta_boxes( $screen, $context, $object ) {
	global $wp_meta_boxes;
	static $already_sorted = false;

	if ( empty( $screen ) )
		$screen = get_current_screen();
	elseif ( is_string( $screen ) )
		$screen = convert_to_screen( $screen );

	$page = $screen->id;

	$hidden = get_hidden_meta_boxes( $screen );

	printf('<div id="%s-sortables" class="meta-box-sortables">', htmlspecialchars($context));

	// Grab the ones the user has manually sorted. Pull them out of their previous context/priority and into the one the user chose
	if ( ! $already_sorted && $sorted = get_user_option( "meta-box-order_$page" ) ) {
		foreach ( $sorted as $box_context => $ids ) {
			foreach ( explode( ',', $ids ) as $id ) {
				if ( $id && 'dashboard_browser_nag' !== $id ) {
					add_meta_box( $id, null, null, $screen, $box_context, 'sorted' );
				}
			}
		}
	}

	$already_sorted = true;

	$i = 0;

	if ( isset( $wp_meta_boxes[ $page ][ $context ] ) ) {
		foreach ( array( 'high', 'sorted', 'core', 'default', 'low' ) as $priority ) {
			if ( isset( $wp_meta_boxes[ $page ][ $context ][ $priority ]) ) {
				foreach ( (array) $wp_meta_boxes[ $page ][ $context ][ $priority ] as $box ) {
					if ( false == $box || ! $box['title'] )
						continue;
					$i++;
					$hidden_class = in_array($box['id'], $hidden) ? ' hide-if-js' : '';
					echo '<div id="' . $box['id'] . '" class="postbox ' . postbox_classes($box['id'], $page) . $hidden_class . '" ' . '>' . "\n";
					if ( 'dashboard_browser_nag' != $box['id'] ) {
						echo '<button type="button" class="handlediv button-link" aria-expanded="true">';
						echo '<span class="screen-reader-text">' . sprintf( __( 'Toggle panel: %s' ), $box['title'] ) . '</span>';
						echo '<span class="toggle-indicator" aria-hidden="true"></span>';
						echo '</button>';
					}
					echo "<h2 class='hndle'><span>{$box['title']}</span></h2>\n";
					echo '<div class="inside">' . "\n";
					call_user_func($box['callback'], $object, $box);
					echo "</div>\n";
					echo "</div>\n";
				}
			}
		}
	}

	echo "</div>";

	return $i;

}

function remove_meta_box( $id, $screen, $context ) {
	global $wp_meta_boxes;

	if ( empty( $screen ) ) {
		$screen = get_current_screen();
	} elseif ( is_string( $screen ) ) {
		$screen = convert_to_screen( $screen );
	} elseif ( is_array( $screen ) ) {
		foreach ( $screen as $single_screen ) {
			remove_meta_box( $id, $single_screen, $context );
		}
	}

	if ( ! isset( $screen->id ) ) {
		return;
	}

	$page = $screen->id;

	if ( !isset($wp_meta_boxes) )
		$wp_meta_boxes = array();
	if ( !isset($wp_meta_boxes[$page]) )
		$wp_meta_boxes[$page] = array();
	if ( !isset($wp_meta_boxes[$page][$context]) )
		$wp_meta_boxes[$page][$context] = array();

	foreach ( array('high', 'core', 'default', 'low') as $priority )
		$wp_meta_boxes[$page][$context][$priority][$id] = false;
}

function do_accordion_sections( $screen, $context, $object ) {
	global $wp_meta_boxes;

	wp_enqueue_script( 'accordion' );

	if ( empty( $screen ) )
		$screen = get_current_screen();
	elseif ( is_string( $screen ) )
		$screen = convert_to_screen( $screen );

	$page = $screen->id;

	$hidden = get_hidden_meta_boxes( $screen );
	?>
	<div id="side-sortables" class="accordion-container">
		<ul class="outer-border">
	<?php
	$i = 0;
	$first_open = false;

	if ( isset( $wp_meta_boxes[ $page ][ $context ] ) ) {
		foreach ( array( 'high', 'core', 'default', 'low' ) as $priority ) {
			if ( isset( $wp_meta_boxes[ $page ][ $context ][ $priority ] ) ) {
				foreach ( $wp_meta_boxes[ $page ][ $context ][ $priority ] as $box ) {
					if ( false == $box || ! $box['title'] )
						continue;
					$i++;
					$hidden_class = in_array( $box['id'], $hidden ) ? 'hide-if-js' : '';

					$open_class = '';
					if ( ! $first_open && empty( $hidden_class ) ) {
						$first_open = true;
						$open_class = 'open';
					}
					?>
					<li class="control-section accordion-section <?php echo $hidden_class; ?> <?php echo $open_class; ?> <?php echo esc_attr( $box['id'] ); ?>" id="<?php echo esc_attr( $box['id'] ); ?>">
						<h3 class="accordion-section-title hndle" tabindex="0">
							<?php echo esc_html( $box['title'] ); ?>
							<span class="screen-reader-text">按回车来打开此小节。</span>
						</h3>
						<div class="accordion-section-content <?php postbox_classes( $box['id'], $page ); ?>">
							<div class="inside">
								<?php call_user_func( $box['callback'], $object, $box ); ?>
							</div>
						</div>
					</li>
					<?php
				}
			}
		}
	}
	?>
		</ul><!-- .outer-border -->
	</div><!-- .accordion-container -->
	<?php
	return $i;
}

function add_settings_section($id, $title, $callback, $page) {
	global $wp_settings_sections;

	if ( 'misc' == $page ) {
		_deprecated_argument( __FUNCTION__, '3.0', sprintf( 'The "%s" options group has been removed. Use another settings group.', 'misc' ) );
		$page = 'general';
	}

	if ( 'privacy' == $page ) {
		_deprecated_argument( __FUNCTION__, '3.5', sprintf( 'The "%s" options group has been removed. Use another settings group.', 'privacy' ) );
		$page = 'reading';
	}

	$wp_settings_sections[$page][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback);
}

function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array()) {
	global $wp_settings_fields;

	if ( 'misc' == $page ) {
		_deprecated_argument( __FUNCTION__, '3.0', 'The miscellaneous options group has been removed. Use another settings group.' );
		$page = 'general';
	}

	if ( 'privacy' == $page ) {
		_deprecated_argument( __FUNCTION__, '3.5', 'The privacy options group has been removed. Use another settings group.' );
		$page = 'reading';
	}

	$wp_settings_fields[$page][$section][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $args);
}

function do_settings_sections( $page ) {
	global $wp_settings_sections, $wp_settings_fields;

	if ( ! isset( $wp_settings_sections[$page] ) )
		return;

	foreach ( (array) $wp_settings_sections[$page] as $section ) {
		if ( $section['title'] )
			echo "<h2>{$section['title']}</h2>\n";

		if ( $section['callback'] )
			call_user_func( $section['callback'], $section );

		if ( ! isset( $wp_settings_fields ) || !isset( $wp_settings_fields[$page] ) || !isset( $wp_settings_fields[$page][$section['id']] ) )
			continue;
		echo '<table class="form-table">';
		do_settings_fields( $page, $section['id'] );
		echo '</table>';
	}
}

function do_settings_fields($page, $section) {
	global $wp_settings_fields;

	if ( ! isset( $wp_settings_fields[$page][$section] ) )
		return;

	foreach ( (array) $wp_settings_fields[$page][$section] as $field ) {
		$class = '';

		if ( ! empty( $field['args']['class'] ) ) {
			$class = ' class="' . esc_attr( $field['args']['class'] ) . '"';
		}

		echo "<tr{$class}>";

		if ( ! empty( $field['args']['label_for'] ) ) {
			echo '<th scope="row"><label for="' . esc_attr( $field['args']['label_for'] ) . '">' . $field['title'] . '</label></th>';
		} else {
			echo '<th scope="row">' . $field['title'] . '</th>';
		}

		echo '<td>';
		call_user_func($field['callback'], $field['args']);
		echo '</td>';
		echo '</tr>';
	}
}

function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	global $wp_settings_errors;

	$wp_settings_errors[] = array(
		'setting' => $setting,
		'code'    => $code,
		'message' => $message,
		'type'    => $type
	);
}

function get_settings_errors( $setting = '', $sanitize = false ) {
	global $wp_settings_errors;

	if ( $sanitize )
		sanitize_option( $setting, get_option( $setting ) );

	if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] && get_transient( 'settings_errors' ) ) {
		$wp_settings_errors = array_merge( (array) $wp_settings_errors, get_transient( 'settings_errors' ) );
		delete_transient( 'settings_errors' );
	}

	if ( ! count( $wp_settings_errors ) )
		return array();

	if ( $setting ) {
		$setting_errors = array();
		foreach ( (array) $wp_settings_errors as $key => $details ) {
			if ( $setting == $details['setting'] )
				$setting_errors[] = $wp_settings_errors[$key];
		}
		return $setting_errors;
	}

	return $wp_settings_errors;
}

function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {

	if ( $hide_on_update && ! empty( $_GET['settings-updated'] ) )
		return;

	$settings_errors = get_settings_errors( $setting, $sanitize );

	if ( empty( $settings_errors ) )
		return;

	$output = '';
	foreach ( $settings_errors as $key => $details ) {
		$css_id = 'setting-error-' . $details['code'];
		$css_class = $details['type'] . ' settings-error notice is-dismissible';
		$output .= "<div id='$css_id' class='$css_class'> \n";
		$output .= "<p><strong>{$details['message']}</strong></p>";
		$output .= "</div> \n";
	}
	echo $output;
}

function find_posts_div($found_action = '') {
?>
	<div id="find-posts" class="find-box" style="display: none;">
		<div id="find-posts-head" class="find-box-head">
			Attach to existing content
			<div id="find-posts-close"></div>
		</div>
		<div class="find-box-inside">
			<div class="find-box-search">
				<?php if ( $found_action ) { ?>
					<input type="hidden" name="found_action" value="<?php echo esc_attr($found_action); ?>" />
				<?php } ?>
				<input type="hidden" name="affected" id="affected" value="" />
				<?php wp_nonce_field( 'find-posts', '_ajax_nonce', false ); ?>
				<label class="screen-reader-text" for="find-posts-input">Search</label>
				<input type="text" id="find-posts-input" name="ps" value="" />
				<span class="spinner"></span>
				<input type="button" id="find-posts-search" value="<?php esc_attr_e( 'Search' ); ?>" class="button" />
				<div class="clear"></div>
			</div>
			<div id="find-posts-response"></div>
		</div>
		<div class="find-box-buttons">
			<?php submit_button( 'Select', 'button-primary alignright', 'find-posts-submit', false ); ?>
			<div class="clear"></div>
		</div>
	</div>
<?php
}

function the_post_password() {
	$post = get_post();
	if ( isset( $post->post_password ) )
		echo esc_attr( $post->post_password );
}

function _draft_or_post_title( $post = 0 ) {
	$title = get_the_title( $post );
	if ( empty( $title ) )
		$title = '(no title)';
	return esc_html( $title );
}

function _admin_search_query() {
	echo isset($_REQUEST['s']) ? esc_attr( wp_unslash( $_REQUEST['s'] ) ) : '';
}

function iframe_header( $title = '', $deprecated = false ) {
	show_admin_bar( false );
	global $hook_suffix, $admin_body_class, $wp_locale;
	$admin_body_class = preg_replace('/[^a-z0-9_-]+/i', '-', $hook_suffix);

	$current_screen = get_current_screen();

	@header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
	_wp_admin_html_begin();
?>
<title><?php bloginfo('name') ?> &rsaquo; <?php echo $title ?> &#8212; <?php _e('WordPress'); ?></title>
<?php
wp_enqueue_style( 'colors' );
?>
<script type="text/javascript">
addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
function tb_close(){var win=window.dialogArguments||opener||parent||top;win.tb_remove();}
var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>',
	pagenow = '<?php echo $current_screen->id; ?>',
	typenow = '<?php echo $current_screen->post_type; ?>',
	adminpage = '<?php echo $admin_body_class; ?>',
	thousandsSeparator = '<?php echo addslashes( $wp_locale->number_format['thousands_sep'] ); ?>',
	decimalPoint = '<?php echo addslashes( $wp_locale->number_format['decimal_point'] ); ?>';
</script>
<?php
do_action( 'admin_enqueue_scripts', $hook_suffix );
do_action( "admin_print_styles-$hook_suffix" );
do_action( 'admin_print_styles' );
do_action( "admin_print_scripts-$hook_suffix" );
do_action( 'admin_print_scripts' );
do_action( "admin_head-$hook_suffix" );
do_action( 'admin_head' );

$admin_body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

?>
</head>
<?php
$admin_body_classes = apply_filters( 'admin_body_class', '' );
?>
<body<?php
if ( isset($GLOBALS['body_id']) ) echo ' id="' . $GLOBALS['body_id'] . '"'; ?> class="wp-admin wp-core-ui no-js iframe <?php echo $admin_body_classes . ' ' . $admin_body_class; ?>">
<script type="text/javascript">
(function(){
var c = document.body.className;
c = c.replace(/no-js/, 'js');
document.body.className = c;
})();
</script>
<?php
}

function iframe_footer() {
?>
	<div class="hidden">
<?php
	do_action( 'admin_footer', '' );
	do_action( 'admin_print_footer_scripts' );
?>
	</div>
<script type="text/javascript">if(typeof wpOnload=="function")wpOnload();</script>
</body>
</html>
<?php
}

function _post_states($post) {
	$post_states = array();
	if ( isset( $_REQUEST['post_status'] ) )
		$post_status = $_REQUEST['post_status'];
	else
		$post_status = '';

	if ( !empty($post->post_password) )
		$post_states['protected'] = 'Password protected';
	if ( 'private' == $post->post_status && 'private' != $post_status )
		$post_states['private'] = 'Private';
	if ( 'draft' == $post->post_status && 'draft' != $post_status )
		$post_states['draft'] = 'Draft';
	if ( 'pending' == $post->post_status && 'pending' != $post_status )
		$post_states['pending'] = 'Pending';
	if ( is_sticky($post->ID) )
		$post_states['sticky'] = 'Sticky';
	if ( 'future' === $post->post_status ) {
		$post_states['scheduled'] = 'Scheduled';
	}
	if ( 'page' === get_option( 'show_on_front' ) ) {
		if ( intval( get_option( 'page_on_front' ) ) === $post->ID ) {
			$post_states['page_on_front'] = 'Front Page';
		}
		if ( intval( get_option( 'page_for_posts' ) ) === $post->ID ) {
			$post_states['page_for_posts'] = 'Posts Page';
		}
	}

	$post_states = apply_filters( 'display_post_states', $post_states, $post );

	if ( ! empty($post_states) ) {
		$state_count = count($post_states);
		$i = 0;
		echo ' &mdash; ';
		foreach ( $post_states as $state ) {
			++$i;
			( $i == $state_count ) ? $sep = '' : $sep = ', ';
			echo "<span class='post-state'>$state$sep</span>";
		}
	}

}

function _media_states( $post ) {
	$media_states = array();
	$stylesheet = get_option('stylesheet');

	if ( current_theme_supports( 'custom-header') ) {
		$meta_header = get_post_meta($post->ID, '_wp_attachment_is_custom_header', true );
		if ( ! empty( $meta_header ) && $meta_header == $stylesheet )
			$media_states[] = 'Header Image';
	}

	if ( current_theme_supports( 'custom-background') ) {
		$meta_background = get_post_meta($post->ID, '_wp_attachment_is_custom_background', true );
		if ( ! empty( $meta_background ) && $meta_background == $stylesheet )
			$media_states[] = 'Background Image';
	}

	if ( $post->ID == get_option( 'site_icon' ) ) {
		$media_states[] = 'Site Icon';
	}

	if ( $post->ID == get_theme_mod( 'site_logo' ) ) {
		$media_states[] = 'Logo';
	}

	$media_states = apply_filters( 'display_media_states', $media_states );

	if ( ! empty( $media_states ) ) {
		$state_count = count( $media_states );
		$i = 0;
		echo ' &mdash; ';
		foreach ( $media_states as $state ) {
			++$i;
			( $i == $state_count ) ? $sep = '' : $sep = ', ';
			echo "<span class='post-state'>$state$sep</span>";
		}
	}
}

function compression_test() {
?>
	<script type="text/javascript">
	var compressionNonce = <?php echo wp_json_encode( wp_create_nonce( 'update_can_compress_scripts' ) ); ?>;
	var testCompression = {
		get : function(test) {
			var x;
			if ( window.XMLHttpRequest ) {
				x = new XMLHttpRequest();
			} else {
				try{x=new ActiveXObject('Msxml2.XMLHTTP');}catch(e){try{x=new ActiveXObject('Microsoft.XMLHTTP');}catch(e){};}
			}

			if (x) {
				x.onreadystatechange = function() {
					var r, h;
					if ( x.readyState == 4 ) {
						r = x.responseText.substr(0, 18);
						h = x.getResponseHeader('Content-Encoding');
						testCompression.check(r, h, test);
					}
				};

				x.open('GET', ajaxurl + '?action=wp-compression-test&test='+test+'&_ajax_nonce='+compressionNonce+'&'+(new Date()).getTime(), true);
				x.send('');
			}
		},

		check : function(r, h, test) {
			if ( ! r && ! test )
				this.get(1);

			if ( 1 == test ) {
				if ( h && ( h.match(/deflate/i) || h.match(/gzip/i) ) )
					this.get('no');
				else
					this.get(2);

				return;
			}

			if ( 2 == test ) {
				if ( '"wpCompressionTest' == r )
					this.get('yes');
				else
					this.get('no');
			}
		}
	};
	testCompression.check();
	</script>
<?php
}

function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
	echo get_submit_button( $text, $type, $name, $wrap, $other_attributes );
}

function get_submit_button( $text = '', $type = 'primary large', $name = 'submit', $wrap = true, $other_attributes = '' ) {
	if ( ! is_array( $type ) )
		$type = explode( ' ', $type );

	$button_shorthand = array( 'primary', 'small', 'large' );
	$classes = array( 'button' );
	foreach ( $type as $t ) {
		if ( 'secondary' === $t || 'button-secondary' === $t )
			continue;
		$classes[] = in_array( $t, $button_shorthand ) ? 'button-' . $t : $t;
	}
	$class = implode( ' ', array_unique( $classes ) );

	if ( 'delete' === $type )
		$class = 'button-secondary delete';

	$text = $text ? $text : __( 'Save Changes' );

	// Default the id attribute to $name unless an id was specifically provided in $other_attributes
	$id = $name;
	if ( is_array( $other_attributes ) && isset( $other_attributes['id'] ) ) {
		$id = $other_attributes['id'];
		unset( $other_attributes['id'] );
	}

	$attributes = '';
	if ( is_array( $other_attributes ) ) {
		foreach ( $other_attributes as $attribute => $value ) {
			$attributes .= $attribute . '="' . esc_attr( $value ) . '" '; // Trailing space is important
		}
	} elseif ( ! empty( $other_attributes ) ) { // Attributes provided as a string
		$attributes = $other_attributes;
	}

	// Don't output empty name and id attributes.
	$name_attr = $name ? ' name="' . esc_attr( $name ) . '"' : '';
	$id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';

	$button = '<input type="submit"' . $name_attr . $id_attr . ' class="' . esc_attr( $class );
	$button	.= '" value="' . esc_attr( $text ) . '" ' . $attributes . ' />';

	if ( $wrap ) {
		$button = '<p class="submit">' . $button . '</p>';
	}

	return $button;
}

function _wp_admin_html_begin() {
	global $is_IE;

	$admin_html_class = ( is_admin_bar_showing() ) ? 'wp-toolbar' : '';

	if ( $is_IE )
		@header('X-UA-Compatible: IE=edge');

?>
<!DOCTYPE html>
<!--[if IE 8]>
<html xmlns="http://www.w3.org/1999/xhtml" class="ie8 <?php echo $admin_html_class; ?>" <?php
	/**
	 * Fires inside the HTML tag in the admin header.
	 *
	 * @since 2.2.0
	 */
	do_action( 'admin_xml_ns' );
?> <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 8) ]><!-->
<html xmlns="http://www.w3.org/1999/xhtml" class="<?php echo $admin_html_class; ?>" <?php
	/** This action is documented in wp-admin/includes/template.php */
	do_action( 'admin_xml_ns' );
?> <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo get_option('blog_charset'); ?>" />
<?php
}

function convert_to_screen( $hook_name ) {
	if ( ! class_exists( 'WP_Screen', false ) ) {
		_doing_it_wrong( 'convert_to_screen(), add_meta_box()', __( "Likely direct inclusion of wp-admin/includes/template.php in order to use add_meta_box(). This is very wrong. Hook the add_meta_box() call into the add_meta_boxes action instead." ), '3.3' );
		return (object) array( 'id' => '_invalid', 'base' => '_are_belong_to_us' );
	}

	return WP_Screen::get( $hook_name );
}

function _local_storage_notice() {
	?>
	<div id="local-storage-notice" class="hidden notice">
	<p class="local-restore">
		The backup of this post in your browser is different from the version below.
		<a class="restore-backup" href="#">Restore the backup.</a>
	</p>
	<p class="undo-restore hidden">
		Post restored successfully.
		<a class="undo-restore-backup" href="#">Undo.</a>
	</p>
	</div>
	<?php
}

function wp_star_rating( $args = array() ) {
	$defaults = array(
		'rating' => 0,
		'type'   => 'rating',
		'number' => 0,
		'echo'   => true,
	);
	$r = wp_parse_args( $args, $defaults );
	$rating = str_replace( ',', '.', $r['rating'] );
	if ( 'percent' == $r['type'] ) {
		$rating = round( $rating / 10, 0 ) / 2;
	}
	$full_stars = floor( $rating );
	$half_stars = ceil( $rating - $full_stars );
	$empty_stars = 5 - $full_stars - $half_stars;

	if ( $r['number'] ) {
		$format = _n( '%1$s rating based on %2$s rating', '%1$s rating based on %2$s ratings', $r['number'] );
		$title = sprintf( $format, number_format_i18n( $rating, 1 ), number_format_i18n( $r['number'] ) );
	} else {
		$title = sprintf( '%s rating', number_format_i18n( $rating, 1 ) );
	}

	$output = '<div class="star-rating">';
	$output .= '<span class="screen-reader-text">' . $title . '</span>';
	$output .= str_repeat( '<div class="star star-full"></div>', $full_stars );
	$output .= str_repeat( '<div class="star star-half"></div>', $half_stars );
	$output .= str_repeat( '<div class="star star-empty"></div>', $empty_stars );
	$output .= '</div>';

	if ( $r['echo'] ) {
		echo $output;
	}

	return $output;
}

function _wp_posts_page_notice() {
	echo '<div class="notice notice-warning inline"><p>'You are currently editing the page that shows your latest posts.</p></div>';
}
