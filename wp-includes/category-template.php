<?php
/**
 * Taxonomy API: Core category-specific template tags
 *
 * @package WordPress
 * @subpackage Template
 * @since 1.2.0
 */

function get_category_link( $category ) {
	if ( ! is_object( $category ) )
		$category = (int) $category;

	$category = get_term_link( $category, 'category' );

	if ( is_wp_error( $category ) )
		return '';

	return $category;
}

function get_category_parents( $id, $link = false, $separator = '/', $nicename = false, $visited = array() ) {
	$chain = '';
	$parent = get_term( $id, 'category' );
	if ( is_wp_error( $parent ) )
		return $parent;

	if ( $nicename )
		$name = $parent->slug;
	else
		$name = $parent->name;

	if ( $parent->parent && ( $parent->parent != $parent->term_id ) && !in_array( $parent->parent, $visited ) ) {
		$visited[] = $parent->parent;
		$chain .= get_category_parents( $parent->parent, $link, $separator, $nicename, $visited );
	}

	if ( $link )
		$chain .= '<a href="' . esc_url( get_category_link( $parent->term_id ) ) . '">'.$name.'</a>' . $separator;
	else
		$chain .= $name.$separator;
	return $chain;
}

function get_the_category( $id = false ) {
	$categories = get_the_terms( $id, 'category' );
	if ( ! $categories || is_wp_error( $categories ) )
		$categories = array();

	$categories = array_values( $categories );

	foreach ( array_keys( $categories ) as $key ) {
		_make_cat_compat( $categories[$key] );
	}

	return apply_filters( 'get_the_categories', $categories, $id );
}

function _usort_terms_by_name( $a, $b ) {
	return strcmp( $a->name, $b->name );
}

function _usort_terms_by_ID( $a, $b ) {
	if ( $a->term_id > $b->term_id )
		return 1;
	elseif ( $a->term_id < $b->term_id )
		return -1;
	else
		return 0;
}

function get_the_category_by_ID( $cat_ID ) {
	$cat_ID = (int) $cat_ID;
	$category = get_term( $cat_ID, 'category' );

	if ( is_wp_error( $category ) )
		return $category;

	return ( $category ) ? $category->name : '';
}

function get_the_category_list( $separator = '', $parents='', $post_id = false ) {
	global $wp_rewrite;
	if ( ! is_object_in_taxonomy( get_post_type( $post_id ), 'category' ) ) {
		/** This filter is documented in wp-includes/category-template.php */
		return apply_filters( 'the_category', '', $separator, $parents );
	}

	$categories = apply_filters( 'the_category_list', get_the_category( $post_id ), $post_id );

	if ( empty( $categories ) ) {
		/** This filter is documented in wp-includes/category-template.php */
		return apply_filters( 'the_category', 'Uncategorized', $separator, $parents );
	}

	$rel = ( is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() ) ? 'rel="category tag"' : 'rel="category"';

	$thelist = '';
	if ( '' == $separator ) {
		$thelist .= '<ul class="post-categories">';
		foreach ( $categories as $category ) {
			$thelist .= "\n\t<li>";
			switch ( strtolower( $parents ) ) {
				case 'multiple':
					if ( $category->parent )
						$thelist .= get_category_parents( $category->parent, true, $separator );
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" ' . $rel . '>' . $category->name.'</a></li>';
					break;
				case 'single':
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '"  ' . $rel . '>';
					if ( $category->parent )
						$thelist .= get_category_parents( $category->parent, false, $separator );
					$thelist .= $category->name.'</a></li>';
					break;
				case '':
				default:
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" ' . $rel . '>' . $category->name.'</a></li>';
			}
		}
		$thelist .= '</ul>';
	} else {
		$i = 0;
		foreach ( $categories as $category ) {
			if ( 0 < $i )
				$thelist .= $separator;
			switch ( strtolower( $parents ) ) {
				case 'multiple':
					if ( $category->parent )
						$thelist .= get_category_parents( $category->parent, true, $separator );
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" ' . $rel . '>' . $category->name.'</a>';
					break;
				case 'single':
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" ' . $rel . '>';
					if ( $category->parent )
						$thelist .= get_category_parents( $category->parent, false, $separator );
					$thelist .= "$category->name</a>";
					break;
				case '':
				default:
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" ' . $rel . '>' . $category->name.'</a>';
			}
			++$i;
		}
	}

	return apply_filters( 'the_category', $thelist, $separator, $parents );
}

function in_category( $category, $post = null ) {
	if ( empty( $category ) )
		return false;
	return has_category( $category, $post );
}

function the_category( $separator = '', $parents='', $post_id = false ) {
	echo get_the_category_list( $separator, $parents, $post_id );
}

function category_description( $category = 0 ) {
	return term_description( $category, 'category' );
}

function wp_dropdown_categories( $args = '' ) {
	$defaults = array(
		'show_option_all' => '', 'show_option_none' => '',
		'orderby' => 'id', 'order' => 'ASC',
		'show_count' => 0,
		'hide_empty' => 1, 'child_of' => 0,
		'exclude' => '', 'echo' => 1,
		'selected' => 0, 'hierarchical' => 0,
		'name' => 'cat', 'id' => '',
		'class' => 'postform', 'depth' => 0,
		'tab_index' => 0, 'taxonomy' => 'category',
		'hide_if_empty' => false, 'option_none_value' => -1,
		'value_field' => 'term_id',
	);

	$defaults['selected'] = ( is_category() ) ? get_query_var( 'cat' ) : 0;

	// Back compat.
	if ( isset( $args['type'] ) && 'link' == $args['type'] ) {
		/* translators: 1: "type => link", 2: "taxonomy => link_category" alternative */
		_deprecated_argument( __FUNCTION__, '3.0',
			sprintf( __( '%1$s is deprecated. Use %2$s instead.' ),
				'<code>type => link</code>',
				'<code>taxonomy => link_category</code>'
			)
		);
		$args['taxonomy'] = 'link_category';
	}

	$r = wp_parse_args( $args, $defaults );
	$option_none_value = $r['option_none_value'];

	if ( ! isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	$tab_index = $r['tab_index'];

	$tab_index_attribute = '';
	if ( (int) $tab_index > 0 ) {
		$tab_index_attribute = " tabindex=\"$tab_index\"";
	}

	// Avoid clashes with the 'name' param of get_terms().
	$get_terms_args = $r;
	unset( $get_terms_args['name'] );
	$categories = get_terms( $r['taxonomy'], $get_terms_args );

	$name = esc_attr( $r['name'] );
	$class = esc_attr( $r['class'] );
	$id = $r['id'] ? esc_attr( $r['id'] ) : $name;

	if ( ! $r['hide_if_empty'] || ! empty( $categories ) ) {
		$output = "<select name='$name' id='$id' class='$class' $tab_index_attribute>\n";
	} else {
		$output = '';
	}
	if ( empty( $categories ) && ! $r['hide_if_empty'] && ! empty( $r['show_option_none'] ) ) {
		$show_option_none = apply_filters( 'list_cats', $r['show_option_none'] );
		$output .= "\t<option value='" . esc_attr( $option_none_value ) . "' selected='selected'>$show_option_none</option>\n";
	}

	if ( ! empty( $categories ) ) {

		if ( $r['show_option_all'] ) {

			/** This filter is documented in wp-includes/category-template.php */
			$show_option_all = apply_filters( 'list_cats', $r['show_option_all'] );
			$selected = ( '0' === strval($r['selected']) ) ? " selected='selected'" : '';
			$output .= "\t<option value='0'$selected>$show_option_all</option>\n";
		}

		if ( $r['show_option_none'] ) {

			/** This filter is documented in wp-includes/category-template.php */
			$show_option_none = apply_filters( 'list_cats', $r['show_option_none'] );
			$selected = selected( $option_none_value, $r['selected'], false );
			$output .= "\t<option value='" . esc_attr( $option_none_value ) . "'$selected>$show_option_none</option>\n";
		}

		if ( $r['hierarchical'] ) {
			$depth = $r['depth'];  // Walk the full depth.
		} else {
			$depth = -1; // Flat.
		}
		$output .= walk_category_dropdown_tree( $categories, $depth, $r );
	}

	if ( ! $r['hide_if_empty'] || ! empty( $categories ) ) {
		$output .= "</select>\n";
	}

	$output = apply_filters( 'wp_dropdown_cats', $output, $r );

	if ( $r['echo'] ) {
		echo $output;
	}
	return $output;
}

function wp_list_categories( $args = '' ) {
	$defaults = array(
		'child_of'            => 0,
		'current_category'    => 0,
		'depth'               => 0,
		'echo'                => 1,
		'exclude'             => '',
		'exclude_tree'        => '',
		'feed'                => '',
		'feed_image'          => '',
		'feed_type'           => '',
		'hide_empty'          => 1,
		'hide_title_if_empty' => false,
		'hierarchical'        => true,
		'order'               => 'ASC',
		'orderby'             => 'name',
		'separator'           => '<br />',
		'show_count'          => 0,
		'show_option_all'     => '',
		'show_option_none'    => 'No categories',
		'style'               => 'list',
		'taxonomy'            => 'category',
		'title_li'            => 'Categories',
		'use_desc_for_title'  => 1,
	);
	$r = wp_parse_args( $args, $defaults );
	if ( !isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] )
		$r['pad_counts'] = true;
	if ( true == $r['hierarchical'] ) {
		$exclude_tree = array();
		if ( $r['exclude_tree'] ) {
			$exclude_tree = array_merge( $exclude_tree, wp_parse_id_list( $r['exclude_tree'] ) );
		}
		if ( $r['exclude'] ) {
			$exclude_tree = array_merge( $exclude_tree, wp_parse_id_list( $r['exclude'] ) );
		}
		$r['exclude_tree'] = $exclude_tree;
		$r['exclude'] = '';
	}
	if ( ! isset( $r['class'] ) )
		$r['class'] = ( 'category' == $r['taxonomy'] ) ? 'categories' : $r['taxonomy'];
	if ( ! taxonomy_exists( $r['taxonomy'] ) ) {
		return false;
	}
	$show_option_all = $r['show_option_all'];
	$show_option_none = $r['show_option_none'];
	$categories = get_categories( $r );
	$output = '';
	if ( $r['title_li'] && 'list' == $r['style'] && ( ! empty( $categories ) || ! $r['hide_title_if_empty'] ) ) {
		$output = '<li class="' . esc_attr( $r['class'] ) . '">' . $r['title_li'] . '<ul>';
	}
	if ( empty( $categories ) ) {
		if ( ! empty( $show_option_none ) ) {
			if ( 'list' == $r['style'] ) {
				$output .= '<li class="cat-item-none">' . $show_option_none . '</li>';
			} else {
				$output .= $show_option_none;
			}
		}
	} else {
		if ( ! empty( $show_option_all ) ) {
			$posts_page = '';
			$taxonomy_object = get_taxonomy( $r['taxonomy'] );
			if ( ! in_array( 'post', $taxonomy_object->object_type ) && ! in_array( 'page', $taxonomy_object->object_type ) ) {
				foreach ( $taxonomy_object->object_type as $object_type ) {
					$_object_type = get_post_type_object( $object_type );
					if ( ! empty( $_object_type->has_archive ) ) {
						$posts_page = get_post_type_archive_link( $object_type );
						break;
					}
				}
			}
			if ( ! $posts_page ) {
				if ( 'page' == get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
					$posts_page = get_permalink( get_option( 'page_for_posts' ) );
				} else {
					$posts_page = home_url( '/' );
				}
			}
			$posts_page = esc_url( $posts_page );
			if ( 'list' == $r['style'] ) {
				$output .= "<li class='cat-item-all'><a href='$posts_page'>$show_option_all</a></li>";
			} else {
				$output .= "<a href='$posts_page'>$show_option_all</a>";
			}
		}
		if ( empty( $r['current_category'] ) && ( is_category() || is_tax() || is_tag() ) ) {
			$current_term_object = get_queried_object();
			if ( $current_term_object && $r['taxonomy'] === $current_term_object->taxonomy ) {
				$r['current_category'] = get_queried_object_id();
			}
		}
		if ( $r['hierarchical'] ) {
			$depth = $r['depth'];
		} else {
			$depth = -1;
		}
		$output .= walk_category_tree( $categories, $depth, $r );
	}
	if ( $r['title_li'] && 'list' == $r['style'] )
		$output .= '</ul></li>';
	if ( $r['echo'] ) {
		echo $html;
	} else {
		return $html;
	}
}

function wp_tag_cloud( $args = '' ) {
	$defaults = array(
		'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 45,
		'format' => 'flat', 'separator' => "\n", 'orderby' => 'name', 'order' => 'ASC',
		'exclude' => '', 'include' => '', 'link' => 'view', 'taxonomy' => 'post_tag', 'post_type' => '', 'echo' => true
	);
	$args = wp_parse_args( $args, $defaults );

	$tags = get_terms( $args['taxonomy'], array_merge( $args, array( 'orderby' => 'count', 'order' => 'DESC' ) ) ); // Always query top tags

	if ( empty( $tags ) || is_wp_error( $tags ) )
		return;

	foreach ( $tags as $key => $tag ) {
		if ( 'edit' == $args['link'] )
			$link = get_edit_term_link( $tag->term_id, $tag->taxonomy, $args['post_type'] );
		else
			$link = get_term_link( intval($tag->term_id), $tag->taxonomy );
		if ( is_wp_error( $link ) )
			return;

		$tags[ $key ]->link = $link;
		$tags[ $key ]->id = $tag->term_id;
	}

	$return = wp_generate_tag_cloud( $tags, $args ); // Here's where those top tags get sorted according to $args

	/**
	 * Filter the tag cloud output.
	 *
	 * @since 2.3.0
	 *
	 * @param string $return HTML output of the tag cloud.
	 * @param array  $args   An array of tag cloud arguments.
	 */
	$return = apply_filters( 'wp_tag_cloud', $return, $args );

	if ( 'array' == $args['format'] || empty($args['echo']) )
		return $return;

	echo $return;
}

/**
 * Default topic count scaling for tag links
 *
 * @param int $count number of posts with that tag
 * @return int scaled count
 */
function default_topic_count_scale( $count ) {
	return round(log10($count + 1) * 100);
}

function wp_generate_tag_cloud( $tags, $args = '' ) {
	$defaults = array(
		'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 0,
		'format' => 'flat', 'separator' => "\n", 'orderby' => 'name', 'order' => 'ASC',
		'topic_count_text' => null, 'topic_count_text_callback' => null,
		'topic_count_scale_callback' => 'default_topic_count_scale', 'filter' => 1,
	);

	$args = wp_parse_args( $args, $defaults );

	$return = ( 'array' === $args['format'] ) ? array() : '';

	if ( empty( $tags ) ) {
		return $return;
	}

	// Juggle topic count tooltips:
	if ( isset( $args['topic_count_text'] ) ) {
		// First look for nooped plural support via topic_count_text.
		$translate_nooped_plural = $args['topic_count_text'];
	} elseif ( ! empty( $args['topic_count_text_callback'] ) ) {
		// Look for the alternative callback style. Ignore the previous default.
		if ( $args['topic_count_text_callback'] === 'default_topic_count_text' ) {
			$translate_nooped_plural = _n_noop( '%s topic', '%s topics' );
		} else {
			$translate_nooped_plural = false;
		}
	} elseif ( isset( $args['single_text'] ) && isset( $args['multiple_text'] ) ) {
		// If no callback exists, look for the old-style single_text and multiple_text arguments.
		$translate_nooped_plural = _n_noop( $args['single_text'], $args['multiple_text'] );
	} else {
		// This is the default for when no callback, plural, or argument is passed in.
		$translate_nooped_plural = _n_noop( '%s topic', '%s topics' );
	}

	/**
	 * Filter how the items in a tag cloud are sorted.
	 *
	 * @since 2.8.0
	 *
	 * @param array $tags Ordered array of terms.
	 * @param array $args An array of tag cloud arguments.
	 */
	$tags_sorted = apply_filters( 'tag_cloud_sort', $tags, $args );
	if ( empty( $tags_sorted ) ) {
		return $return;
	}

	if ( $tags_sorted !== $tags ) {
		$tags = $tags_sorted;
		unset( $tags_sorted );
	} else {
		if ( 'RAND' === $args['order'] ) {
			shuffle( $tags );
		} else {
			// SQL cannot save you; this is a second (potentially different) sort on a subset of data.
			if ( 'name' === $args['orderby'] ) {
				uasort( $tags, '_wp_object_name_sort_cb' );
			} else {
				uasort( $tags, '_wp_object_count_sort_cb' );
			}

			if ( 'DESC' === $args['order'] ) {
				$tags = array_reverse( $tags, true );
			}
		}
	}

	if ( $args['number'] > 0 )
		$tags = array_slice( $tags, 0, $args['number'] );

	$counts = array();
	$real_counts = array(); // For the alt tag
	foreach ( (array) $tags as $key => $tag ) {
		$real_counts[ $key ] = $tag->count;
		$counts[ $key ] = call_user_func( $args['topic_count_scale_callback'], $tag->count );
	}

	$min_count = min( $counts );
	$spread = max( $counts ) - $min_count;
	if ( $spread <= 0 )
		$spread = 1;
	$font_spread = $args['largest'] - $args['smallest'];
	if ( $font_spread < 0 )
		$font_spread = 1;
	$font_step = $font_spread / $spread;

	// Assemble the data that will be used to generate the tag cloud markup.
	$tags_data = array();
	foreach ( $tags as $key => $tag ) {
		$tag_id = isset( $tag->id ) ? $tag->id : $key;

		$count = $counts[ $key ];
		$real_count = $real_counts[ $key ];

		if ( $translate_nooped_plural ) {
			$title = sprintf( translate_nooped_plural( $translate_nooped_plural, $real_count ), number_format_i18n( $real_count ) );
		} else {
			$title = call_user_func( $args['topic_count_text_callback'], $real_count, $tag, $args );
		}

		$tags_data[] = array(
			'id'         => $tag_id,
			'url'        => '#' != $tag->link ? $tag->link : '#',
			'name'	     => $tag->name,
			'title'      => $title,
			'slug'       => $tag->slug,
			'real_count' => $real_count,
			'class'	     => 'tag-link-' . $tag_id,
			'font_size'  => $args['smallest'] + ( $count - $min_count ) * $font_step,
		);
	}

	$tags_data = apply_filters( 'wp_generate_tag_cloud_data', $tags_data );

	$a = array();

	// generate the output links array
	foreach ( $tags_data as $key => $tag_data ) {
		$class = $tag_data['class'] . ' tag-link-position-' . ( $key + 1 );
		$a[] = "<a href='" . esc_url( $tag_data['url'] ) . "' class='" . esc_attr( $class ) . "' title='" . esc_attr( $tag_data['title'] ) . "' style='font-size: " . esc_attr( str_replace( ',', '.', $tag_data['font_size'] ) . $args['unit'] ) . ";'>" . esc_html( $tag_data['name'] ) . "</a>";
	}

	switch ( $args['format'] ) {
		case 'array' :
			$return =& $a;
			break;
		case 'list' :
			$return = "<ul class='wp-tag-cloud'>\n\t<li>";
			$return .= join( "</li>\n\t<li>", $a );
			$return .= "</li>\n</ul>\n";
			break;
		default :
			$return = join( $args['separator'], $a );
			break;
	}

	if ( $args['filter'] ) {
		return apply_filters( 'wp_generate_tag_cloud', $return, $tags, $args );
	}

	else
		return $return;
}

function _wp_object_name_sort_cb( $a, $b ) {
	return strnatcasecmp( $a->name, $b->name );
}

function _wp_object_count_sort_cb( $a, $b ) {
	return ( $a->count > $b->count );
}

function walk_category_tree() {
	$args = func_get_args();
	// the user's options are the third parameter
	if ( empty( $args[2]['walker'] ) || ! ( $args[2]['walker'] instanceof Walker ) ) {
		$walker = new Walker_Category;
	} else {
		$walker = $args[2]['walker'];
	}
	return call_user_func_array( array( $walker, 'walk' ), $args );
}

function walk_category_dropdown_tree() {
	$args = func_get_args();
	// the user's options are the third parameter
	if ( empty( $args[2]['walker'] ) || ! ( $args[2]['walker'] instanceof Walker ) ) {
		$walker = new Walker_CategoryDropdown;
	} else {
		$walker = $args[2]['walker'];
	}
	return call_user_func_array( array( $walker, 'walk' ), $args );
}

function get_tag_link( $tag ) {
	if ( ! is_object( $tag ) )
		$tag = (int) $tag;

	$tag = get_term_link( $tag, 'post_tag' );

	if ( is_wp_error( $tag ) )
		return '';

	return $tag;
}

function get_the_tags( $id = 0 ) {

	return apply_filters( 'get_the_tags', get_the_terms( $id, 'post_tag' ) );
}

function get_the_tag_list( $before = '', $sep = '', $after = '', $id = 0 ) {

	return apply_filters( 'the_tags', get_the_term_list( $id, 'post_tag', $before, $sep, $after ), $before, $sep, $after, $id );
}

function the_tags( $before = null, $sep = ', ', $after = '' ) {
	if ( null === $before )
		$before = __('Tags: ');
	echo get_the_tag_list($before, $sep, $after);
}

function tag_description( $tag = 0 ) {
	return term_description( $tag );
}

function term_description( $term = 0, $taxonomy = 'post_tag' ) {
	if ( ! $term && ( is_tax() || is_tag() || is_category() ) ) {
		$term = get_queried_object();
		if ( $term ) {
			$taxonomy = $term->taxonomy;
			$term = $term->term_id;
		}
	}
	$description = get_term_field( 'description', $term, $taxonomy );
	return is_wp_error( $description ) ? '' : $description;
}

function get_the_terms( $post, $taxonomy ) {
	if ( ! $post = get_post( $post ) )
		return false;

	$terms = get_object_term_cache( $post->ID, $taxonomy );
	if ( false === $terms ) {
		$terms = wp_get_object_terms( $post->ID, $taxonomy );
		if ( ! is_wp_error( $terms ) ) {
			$to_cache = array();
			foreach ( $terms as $key => $term ) {
				$to_cache[ $key ] = $term->data;
			}
			wp_cache_add( $post->ID, $to_cache, $taxonomy . '_relationships' );
		}
	}

	if ( ! is_wp_error( $terms ) ) {
		$terms = array_map( 'get_term', $terms );
	}

	$terms = apply_filters( 'get_the_terms', $terms, $post->ID, $taxonomy );

	if ( empty( $terms ) )
		return false;

	return $terms;
}

function get_the_term_list( $id, $taxonomy, $before = '', $sep = '', $after = '' ) {
	$terms = get_the_terms( $id, $taxonomy );

	if ( is_wp_error( $terms ) )
		return $terms;

	if ( empty( $terms ) )
		return false;

	$links = array();

	foreach ( $terms as $term ) {
		$link = get_term_link( $term, $taxonomy );
		if ( is_wp_error( $link ) ) {
			return $link;
		}
		$links[] = '<a href="' . esc_url( $link ) . '" rel="tag">' . $term->name . '</a>';
	}

	$term_links = apply_filters( "term_links-$taxonomy", $links );

	return $before . join( $sep, $term_links ) . $after;
}

function the_terms( $id, $taxonomy, $before = '', $sep = ', ', $after = '' ) {
	$term_list = get_the_term_list( $id, $taxonomy, $before, $sep, $after );

	if ( is_wp_error( $term_list ) )
		return false;

	echo apply_filters( 'the_terms', $term_list, $taxonomy, $before, $sep, $after );
}

function has_category( $category = '', $post = null ) {
	return has_term( $category, 'category', $post );
}

function has_tag( $tag = '', $post = null ) {
	return has_term( $tag, 'post_tag', $post );
}

function has_term( $term = '', $taxonomy = '', $post = null ) {
	$post = get_post($post);

	if ( !$post )
		return false;

	$r = is_object_in_term( $post->ID, $taxonomy, $term );
	if ( is_wp_error( $r ) )
		return false;

	return $r;
}
