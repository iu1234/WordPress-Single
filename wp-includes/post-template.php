<?php

function get_the_ID() {
	$post = get_post();
	return ! empty( $post ) ? $post->ID : false;
}

function the_title_attribute( $args = '' ) {
	$defaults = array( 'before' => '', 'after' =>  '', 'echo' => true, 'post' => get_post() );
	$r = wp_parse_args( $args, $defaults );

	$title = get_the_title( $r['post'] );

	if ( strlen( $title ) == 0 ) {
		return;
	}

	$title = $r['before'] . $title . $r['after'];
	$title = esc_attr( strip_tags( $title ) );

	if ( $r['echo'] ) {
		echo $title;
	} else {
		return $title;
	}
}

function get_the_title( $post = 0 ) {
	$post = get_post( $post );

	$title = isset( $post->post_title ) ? $post->post_title : '';
	$id = isset( $post->ID ) ? $post->ID : 0;

	if ( ! is_admin() ) {
		if ( ! empty( $post->post_password ) ) {

			$protected_title_format = apply_filters( 'protected_title_format', 'Protected: %s', $post );
			$title = sprintf( $protected_title_format, $title );
		} elseif ( isset( $post->post_status ) && 'private' == $post->post_status ) {
			$private_title_format = apply_filters( 'private_title_format', 'Private: %s', $post );
			$title = sprintf( $private_title_format, $title );
		}
	}
	return apply_filters( 'the_title', $title, $id );
}

function the_guid( $post = 0 ) {
	$post = get_post( $post );

	$guid = isset( $post->guid ) ? get_the_guid( $post ) : '';
	$id   = isset( $post->ID ) ? $post->ID : 0;

	echo apply_filters( 'the_guid', $guid, $id );
}

function get_the_guid( $post = 0 ) {
	$post = get_post( $post );

	$guid = isset( $post->guid ) ? $post->guid : '';
	$id   = isset( $post->ID ) ? $post->ID : 0;

	return apply_filters( 'get_the_guid', $guid, $id );
}

function the_content( $more_link_text = null, $strip_teaser = false) {
	$content = get_the_content( $more_link_text, $strip_teaser );
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );
	echo $content;
}

function get_the_content( $more_link_text = null, $strip_teaser = false ) {
	global $page, $more, $preview, $pages, $multipage;
	$post = get_post();
	if ( null === $more_link_text ) $more_link_text = '(more&hellip;)';
	$output = '';
	$has_teaser = false;
	if ( post_password_required( $post ) ) return get_the_password_form( $post );
	if ( $page > count( $pages ) ) $page = count( $pages );
	$content = $pages[$page - 1];
	if ( preg_match( '/<!--more(.*?)?-->/', $content, $matches ) ) {
		$content = explode( $matches[0], $content, 2 );
		if ( ! empty( $matches[1] ) && ! empty( $more_link_text ) )
			$more_link_text = strip_tags( wp_kses_no_null( trim( $matches[1] ) ) );
		$has_teaser = true;
	} else {
		$content = array( $content );
	}

	if ( false !== strpos( $post->post_content, '<!--noteaser-->' ) && ( ! $multipage || $page == 1 ) )
		$strip_teaser = true;

	$teaser = $content[0];
	if ( $more && $strip_teaser && $has_teaser )
		$teaser = '';

	$output .= $teaser;
	if ( count( $content ) > 1 ) {
		if ( $more ) {
			$output .= '<span id="more-' . $post->ID . '"></span>' . $content[1];
		} else {
			if ( ! empty( $more_link_text ) )
				$output .= apply_filters( 'the_content_more_link', ' <a href="' . get_permalink() . "#more-{$post->ID}\" class=\"more-link\">$more_link_text</a>", $more_link_text );
			$output = force_balance_tags( $output );
		}
	}
	if ( $preview ) $output = preg_replace_callback( '/\%u([0-9A-F]{4})/', '_convert_urlencoded_to_entities', $output );
	return $output;
}

function _convert_urlencoded_to_entities( $match ) {
	return '&#' . base_convert( $match[1], 16, 10 ) . ';';
}

function get_the_excerpt( $post = null ) {
	$post = get_post( $post );
	if ( empty( $post ) ) {
		return '';
	}
	return apply_filters( 'get_the_excerpt', $post->post_excerpt, $post );
}

function has_excerpt( $id = 0 ) {
	$post = get_post( $id );
	return ( !empty( $post->post_excerpt ) );
}

function post_class( $class = '', $post_id = null ) {
	// Separates classes with a single space, collates classes for post DIV
	echo 'class="' . join( ' ', get_post_class( $class, $post_id ) ) . '"';
}

function get_post_class( $class = '', $post_id = null ) {
	$post = get_post( $post_id );

	$classes = array();

	if ( $class ) {
		if ( ! is_array( $class ) ) {
			$class = preg_split( '#\s+#', $class );
		}
		$classes = array_map( 'esc_attr', $class );
	} else {
		// Ensure that we always coerce class to being an array.
		$class = array();
	}

	if ( ! $post ) {
		return $classes;
	}

	$classes[] = 'post-' . $post->ID;
	if ( ! is_admin() )
		$classes[] = $post->post_type;
	$classes[] = 'type-' . $post->post_type;
	$classes[] = 'status-' . $post->post_status;

	// Post Format
	if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
		$post_format = get_post_format( $post->ID );

		if ( $post_format && !is_wp_error($post_format) )
			$classes[] = 'format-' . sanitize_html_class( $post_format );
		else
			$classes[] = 'format-standard';
	}

	$post_password_required = post_password_required( $post->ID );

	// Post requires password.
	if ( $post_password_required ) {
		$classes[] = 'post-password-required';
	} elseif ( ! empty( $post->post_password ) ) {
		$classes[] = 'post-password-protected';
	}

	// Post thumbnails.
	if ( current_theme_supports( 'post-thumbnails' ) && has_post_thumbnail( $post->ID ) && ! is_attachment( $post ) && ! $post_password_required ) {
		$classes[] = 'has-post-thumbnail';
	}

	// sticky for Sticky Posts
	if ( is_sticky( $post->ID ) ) {
		if ( is_home() && ! is_paged() ) {
			$classes[] = 'sticky';
		} elseif ( is_admin() ) {
			$classes[] = 'status-sticky';
		}
	}

	// hentry for hAtom compliance
	$classes[] = 'hentry';

	// All public taxonomies
	$taxonomies = get_taxonomies( array( 'public' => true ) );
	foreach ( (array) $taxonomies as $taxonomy ) {
		if ( is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
			foreach ( (array) get_the_terms( $post->ID, $taxonomy ) as $term ) {
				if ( empty( $term->slug ) ) {
					continue;
				}

				$term_class = sanitize_html_class( $term->slug, $term->term_id );
				if ( is_numeric( $term_class ) || ! trim( $term_class, '-' ) ) {
					$term_class = $term->term_id;
				}

				// 'post_tag' uses the 'tag' prefix for backward compatibility.
				if ( 'post_tag' == $taxonomy ) {
					$classes[] = 'tag-' . $term_class;
				} else {
					$classes[] = sanitize_html_class( $taxonomy . '-' . $term_class, $taxonomy . '-' . $term->term_id );
				}
			}
		}
	}

	$classes = array_map( 'esc_attr', $classes );

	$classes = apply_filters( 'post_class', $classes, $class, $post->ID );

	return array_unique( $classes );
}

function body_class( $class = '' ) {
	echo 'class="' . join( ' ', get_body_class( $class ) ) . '"';
}

function get_body_class( $class = '' ) {
	global $wp_query;

	$classes = array();
	if ( is_front_page() )
		$classes[] = 'home';
	if ( is_home() )
		$classes[] = 'blog';
	if ( is_archive() )
		$classes[] = 'archive';
	if ( is_date() )
		$classes[] = 'date';
	if ( is_search() ) {
		$classes[] = 'search';
		$classes[] = $wp_query->posts ? 'search-results' : 'search-no-results';
	}
	if ( is_paged() )
		$classes[] = 'paged';
	if ( is_attachment() )
		$classes[] = 'attachment';
	if ( is_404() )
		$classes[] = 'error404';

	if ( is_single() ) {
		$post_id = $wp_query->get_queried_object_id();
		$post = $wp_query->get_queried_object();

		$classes[] = 'single';
		if ( isset( $post->post_type ) ) {
			$classes[] = 'single-' . sanitize_html_class($post->post_type, $post_id);
			$classes[] = 'postid-' . $post_id;

			// Post Format
			if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
				$post_format = get_post_format( $post->ID );

				if ( $post_format && !is_wp_error($post_format) )
					$classes[] = 'single-format-' . sanitize_html_class( $post_format );
				else
					$classes[] = 'single-format-standard';
			}
		}

		if ( is_attachment() ) {
			$mime_type = get_post_mime_type($post_id);
			$mime_prefix = array( 'application/', 'image/', 'text/', 'audio/', 'video/', 'music/' );
			$classes[] = 'attachmentid-' . $post_id;
			$classes[] = 'attachment-' . str_replace( $mime_prefix, '', $mime_type );
		}
	} elseif ( is_archive() ) {
		if ( is_post_type_archive() ) {
			$classes[] = 'post-type-archive';
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) )
				$post_type = reset( $post_type );
			$classes[] = 'post-type-archive-' . sanitize_html_class( $post_type );
		} elseif ( is_author() ) {
			$author = $wp_query->get_queried_object();
			$classes[] = 'author';
			if ( isset( $author->user_nicename ) ) {
				$classes[] = 'author-' . sanitize_html_class( $author->user_nicename, $author->ID );
				$classes[] = 'author-' . $author->ID;
			}
		} elseif ( is_category() ) {
			$cat = $wp_query->get_queried_object();
			$classes[] = 'category';
			if ( isset( $cat->term_id ) ) {
				$cat_class = sanitize_html_class( $cat->slug, $cat->term_id );
				if ( is_numeric( $cat_class ) || ! trim( $cat_class, '-' ) ) {
					$cat_class = $cat->term_id;
				}

				$classes[] = 'category-' . $cat_class;
				$classes[] = 'category-' . $cat->term_id;
			}
		} elseif ( is_tag() ) {
			$tag = $wp_query->get_queried_object();
			$classes[] = 'tag';
			if ( isset( $tag->term_id ) ) {
				$tag_class = sanitize_html_class( $tag->slug, $tag->term_id );
				if ( is_numeric( $tag_class ) || ! trim( $tag_class, '-' ) ) {
					$tag_class = $tag->term_id;
				}

				$classes[] = 'tag-' . $tag_class;
				$classes[] = 'tag-' . $tag->term_id;
			}
		} elseif ( is_tax() ) {
			$term = $wp_query->get_queried_object();
			if ( isset( $term->term_id ) ) {
				$term_class = sanitize_html_class( $term->slug, $term->term_id );
				if ( is_numeric( $term_class ) || ! trim( $term_class, '-' ) ) {
					$term_class = $term->term_id;
				}

				$classes[] = 'tax-' . sanitize_html_class( $term->taxonomy );
				$classes[] = 'term-' . $term_class;
				$classes[] = 'term-' . $term->term_id;
			}
		}
	} elseif ( is_page() ) {
		$classes[] = 'page';

		$page_id = $wp_query->get_queried_object_id();

		$post = get_post($page_id);

		$classes[] = 'page-id-' . $page_id;

		if ( get_pages( array( 'parent' => $page_id, 'number' => 1 ) ) ) {
			$classes[] = 'page-parent';
		}

		if ( $post->post_parent ) {
			$classes[] = 'page-child';
			$classes[] = 'parent-pageid-' . $post->post_parent;
		}
		if ( is_page_template() ) {
			$classes[] = 'page-template';

			$template_slug  = get_page_template_slug( $page_id );
			$template_parts = explode( '/', $template_slug );

			foreach ( $template_parts as $part ) {
				$classes[] = 'page-template-' . sanitize_html_class( str_replace( array( '.', '/' ), '-', basename( $part, '.php' ) ) );
			}
			$classes[] = 'page-template-' . sanitize_html_class( str_replace( '.', '-', $template_slug ) );
		} else {
			$classes[] = 'page-template-default';
		}
	}

	if ( is_user_logged_in() )
		$classes[] = 'logged-in';

	if ( is_admin_bar_showing() ) {
		$classes[] = 'admin-bar';
		$classes[] = 'no-customize-support';
	}

	if ( get_theme_mod('background_color', get_theme_support( 'custom-background', 'default-color' ) ) !== get_theme_support( 'custom-background', 'default-color' ) || get_theme_mod('background_image', get_theme_support( 'custom-background', 'default-image' ) ) )
		$classes[] = 'custom-background';

	if ( has_custom_logo() ) {
		$classes[] = 'wp-custom-logo';
	}

	$page = $wp_query->get( 'page' );

	if ( ! $page || $page < 2 )
		$page = $wp_query->get( 'paged' );

	if ( $page && $page > 1 && ! is_404() ) {
		$classes[] = 'paged-' . $page;

		if ( is_single() )
			$classes[] = 'single-paged-' . $page;
		elseif ( is_page() )
			$classes[] = 'page-paged-' . $page;
		elseif ( is_category() )
			$classes[] = 'category-paged-' . $page;
		elseif ( is_tag() )
			$classes[] = 'tag-paged-' . $page;
		elseif ( is_date() )
			$classes[] = 'date-paged-' . $page;
		elseif ( is_author() )
			$classes[] = 'author-paged-' . $page;
		elseif ( is_search() )
			$classes[] = 'search-paged-' . $page;
		elseif ( is_post_type_archive() )
			$classes[] = 'post-type-paged-' . $page;
	}

	if ( ! empty( $class ) ) {
		if ( !is_array( $class ) )
			$class = preg_split( '#\s+#', $class );
		$classes = array_merge( $classes, $class );
	} else {
		// Ensure that we always coerce class to being an array.
		$class = array();
	}

	$classes = array_map( 'esc_attr', $classes );

	/**
	 * Filter the list of CSS body classes for the current post or page.
	 *
	 * @since 2.8.0
	 *
	 * @param array $classes An array of body classes.
	 * @param array $class   An array of additional classes added to the body.
	 */
	$classes = apply_filters( 'body_class', $classes, $class );

	return array_unique( $classes );
}

/**
 * Whether post requires password and correct password has been provided.
 *
 * @since 2.7.0
 *
 * @param int|WP_Post|null $post An optional post. Global $post used if not provided.
 * @return bool false if a password is not required or the correct password cookie is present, true otherwise.
 */
function post_password_required( $post = null ) {
	$post = get_post($post);

	if ( empty( $post->post_password ) )
		return false;

	if ( ! isset( $_COOKIE['wp-postpass_' . COOKIEHASH] ) )
		return true;

	require_once ABSPATH . WPINC . '/class-phpass.php';
	$hasher = new PasswordHash( 8, true );

	$hash = wp_unslash( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
	if ( 0 !== strpos( $hash, '$P$B' ) )
		return true;

	return ! $hasher->CheckPassword( $post->post_password, $hash );
}

function wp_link_pages( $args = '' ) {
	global $page, $numpages, $multipage, $more;

	$defaults = array(
		'before'           => '<p>Pages:',
		'after'            => '</p>',
		'link_before'      => '',
		'link_after'       => '',
		'next_or_number'   => 'number',
		'separator'        => ' ',
		'nextpagelink'     => 'Next page',
		'previouspagelink' => 'Previous page',
		'pagelink'         => '%',
		'echo'             => 1
	);
	$params = wp_parse_args( $args, $defaults );
	$r = apply_filters( 'wp_link_pages_args', $params );

	$output = '';
	if ( $multipage ) {
		if ( 'number' == $r['next_or_number'] ) {
			$output .= $r['before'];
			for ( $i = 1; $i <= $numpages; $i++ ) {
				$link = $r['link_before'] . str_replace( '%', $i, $r['pagelink'] ) . $r['link_after'];
				if ( $i != $page || ! $more && 1 == $page ) {
					$link = _wp_link_page( $i ) . $link . '</a>';
				}
				$link = apply_filters( 'wp_link_pages_link', $link, $i );
				$output .= ( 1 === $i ) ? ' ' : $r['separator'];
				$output .= $link;
			}
			$output .= $r['after'];
		} elseif ( $more ) {
			$output .= $r['before'];
			$prev = $page - 1;
			if ( $prev > 0 ) {
				$link = _wp_link_page( $prev ) . $r['link_before'] . $r['previouspagelink'] . $r['link_after'] . '</a>';
				$output .= apply_filters( 'wp_link_pages_link', $link, $prev );
			}
			$next = $page + 1;
			if ( $next <= $numpages ) {
				if ( $prev ) {
					$output .= $r['separator'];
				}
				$link = _wp_link_page( $next ) . $r['link_before'] . $r['nextpagelink'] . $r['link_after'] . '</a>';
				$output .= apply_filters( 'wp_link_pages_link', $link, $next );
			}
			$output .= $r['after'];
		}
	}

	$html = apply_filters( 'wp_link_pages', $output, $args );

	if ( $r['echo'] ) {
		echo $html;
	}
	return $html;
}

function _wp_link_page( $i ) {
	global $wp_rewrite;
	$post = get_post();
	$query_args = array();

	if ( 1 == $i ) {
		$url = get_permalink();
	} else {
		if ( '' == get_option('permalink_structure') || in_array($post->post_status, array('draft', 'pending')) )
			$url = add_query_arg( 'page', $i, get_permalink() );
		elseif ( 'page' == get_option('show_on_front') && get_option('page_on_front') == $post->ID )
			$url = trailingslashit(get_permalink()) . user_trailingslashit("$wp_rewrite->pagination_base/" . $i, 'single_paged');
		else
			$url = trailingslashit(get_permalink()) . user_trailingslashit($i, 'single_paged');
	}

	if ( is_preview() ) {

		if ( ( 'draft' !== $post->post_status ) && isset( $_GET['preview_id'], $_GET['preview_nonce'] ) ) {
			$query_args['preview_id'] = wp_unslash( $_GET['preview_id'] );
			$query_args['preview_nonce'] = wp_unslash( $_GET['preview_nonce'] );
		}

		$url = get_preview_post_link( $post, $query_args, $url );
	}

	return '<a href="' . esc_url( $url ) . '">';
}

function post_custom( $key = '' ) {
	$custom = get_post_custom();

	if ( !isset( $custom[$key] ) )
		return false;
	elseif ( 1 == count($custom[$key]) )
		return $custom[$key][0];
	else
		return $custom[$key];
}

function the_meta() {
	if ( $keys = get_post_custom_keys() ) {
		echo "<ul class='post-meta'>\n";
		foreach ( (array) $keys as $key ) {
			$keyt = trim($key);
			if ( is_protected_meta( $keyt, 'post' ) )
				continue;
			$values = array_map('trim', get_post_custom_values($key));
			$value = implode($values,', ');
			echo apply_filters( 'the_meta_key', "<li><span class='post-meta-key'>$key:</span> $value</li>\n", $key, $value );
		}
		echo "</ul>\n";
	}
}

function wp_dropdown_pages( $args = '' ) {
	$defaults = array(
		'depth' => 0, 'child_of' => 0,
		'selected' => 0, 'echo' => 1,
		'name' => 'page_id', 'id' => '',
		'class' => '',
		'show_option_none' => '', 'show_option_no_change' => '',
		'option_none_value' => '',
		'value_field' => 'ID',
	);

	$r = wp_parse_args( $args, $defaults );

	$pages = get_pages( $r );
	$output = '';
	if ( empty( $r['id'] ) ) {
		$r['id'] = $r['name'];
	}

	if ( ! empty( $pages ) ) {
		$class = '';
		if ( ! empty( $r['class'] ) ) {
			$class = " class='" . esc_attr( $r['class'] ) . "'";
		}

		$output = "<select name='" . esc_attr( $r['name'] ) . "'" . $class . " id='" . esc_attr( $r['id'] ) . "'>\n";
		if ( $r['show_option_no_change'] ) {
			$output .= "\t<option value=\"-1\">" . $r['show_option_no_change'] . "</option>\n";
		}
		if ( $r['show_option_none'] ) {
			$output .= "\t<option value=\"" . esc_attr( $r['option_none_value'] ) . '">' . $r['show_option_none'] . "</option>\n";
		}
		$output .= walk_page_dropdown_tree( $pages, $r['depth'], $r );
		$output .= "</select>\n";
	}

	$html = apply_filters( 'wp_dropdown_pages', $output, $r, $pages );

	if ( $r['echo'] ) {
		echo $html;
	}
	return $html;
}

function wp_list_pages( $args = '' ) {
	$defaults = array(
		'depth' => 0, 'show_date' => '',
		'date_format' => get_option( 'date_format' ),
		'child_of' => 0, 'exclude' => '',
		'title_li' => 'Pages', 'echo' => 1,
		'authors' => '', 'sort_column' => 'menu_order, post_title',
		'link_before' => '', 'link_after' => '', 'walker' => '',
	);

	$r = wp_parse_args( $args, $defaults );

	$output = '';
	$current_page = 0;

	$r['exclude'] = preg_replace( '/[^0-9,]/', '', $r['exclude'] );

	$exclude_array = ( $r['exclude'] ) ? explode( ',', $r['exclude'] ) : array();

	$r['exclude'] = implode( ',', apply_filters( 'wp_list_pages_excludes', $exclude_array ) );

	$r['hierarchical'] = 0;
	$pages = get_pages( $r );

	if ( ! empty( $pages ) ) {
		if ( $r['title_li'] ) {
			$output .= '<li class="pagenav">' . $r['title_li'] . '<ul>';
		}
		global $wp_query;
		if ( is_page() || is_attachment() || $wp_query->is_posts_page ) {
			$current_page = get_queried_object_id();
		} elseif ( is_singular() ) {
			$queried_object = get_queried_object();
			if ( is_post_type_hierarchical( $queried_object->post_type ) ) {
				$current_page = $queried_object->ID;
			}
		}

		$output .= walk_page_tree( $pages, $r['depth'], $current_page, $r );

		if ( $r['title_li'] ) {
			$output .= '</ul></li>';
		}
	}

	$html = apply_filters( 'wp_list_pages', $output, $r, $pages );

	if ( $r['echo'] ) {
		echo $html;
	} else {
		return $html;
	}
}

function wp_page_menu( $args = array() ) {
	$defaults = array(
		'sort_column' => 'menu_order, post_title',
		'menu_id'     => '',
		'menu_class'  => 'menu',
		'container'   => 'div',
		'echo'        => true,
		'link_before' => '',
		'link_after'  => '',
		'before'      => '<ul>',
		'after'       => '</ul>',
		'walker'      => '',
	);
	$args = wp_parse_args( $args, $defaults );

	$args = apply_filters( 'wp_page_menu_args', $args );

	$menu = '';

	$list_args = $args;

	if ( ! empty($args['show_home']) ) {
		if ( true === $args['show_home'] || '1' === $args['show_home'] || 1 === $args['show_home'] )
			$text = 'Home';
		else
			$text = $args['show_home'];
		$class = '';
		if ( is_front_page() && !is_paged() )
			$class = 'class="current_page_item"';
		$menu .= '<li ' . $class . '><a href="' . home_url( '/' ) . '">' . $args['link_before'] . $text . $args['link_after'] . '</a></li>';
		if (get_option('show_on_front') == 'page') {
			if ( !empty( $list_args['exclude'] ) ) {
				$list_args['exclude'] .= ',';
			} else {
				$list_args['exclude'] = '';
			}
			$list_args['exclude'] .= get_option('page_on_front');
		}
	}

	$list_args['echo'] = false;
	$list_args['title_li'] = '';
	$menu .= str_replace( array( "\r", "\n", "\t" ), '', wp_list_pages($list_args) );

	$container = sanitize_text_field( $args['container'] );
	if ( empty( $container ) ) {
		$container = 'div';
	}

	if ( $menu ) {
		if ( isset( $args['fallback_cb'] ) &&
			'wp_page_menu' === $args['fallback_cb'] &&
			'ul' !== $container ) {
			$args['before'] = '<ul>';
			$args['after'] = '</ul>';
		}

		$menu = $args['before'] . $menu . $args['after'];
	}

	$attrs = '';
	if ( ! empty( $args['menu_id'] ) ) {
		$attrs .= ' id="' . esc_attr( $args['menu_id'] ) . '"';
	}

	if ( ! empty( $args['menu_class'] ) ) {
		$attrs .= ' class="' . esc_attr( $args['menu_class'] ) . '"';
	}

	$menu = "<{$container}{$attrs}>" . $menu . "</{$container}>\n";

	$menu = apply_filters( 'wp_page_menu', $menu, $args );
	if ( $args['echo'] )
		echo $menu;
	else
		return $menu;
}

function walk_page_tree( $pages, $depth, $current_page, $r ) {
	if ( empty($r['walker']) )
		$walker = new Walker_Page;
	else
		$walker = $r['walker'];

	foreach ( (array) $pages as $page ) {
		if ( $page->post_parent )
			$r['pages_with_children'][ $page->post_parent ] = true;
	}

	$args = array($pages, $depth, $r, $current_page);
	return call_user_func_array(array($walker, 'walk'), $args);
}

function walk_page_dropdown_tree() {
	$args = func_get_args();
	if ( empty($args[2]['walker']) ) // the user's options are the third parameter
		$walker = new Walker_PageDropdown;
	else
		$walker = $args[2]['walker'];

	return call_user_func_array(array($walker, 'walk'), $args);
}

function the_attachment_link( $id = 0, $fullsize = false, $permalink = false ) {
	if ( $fullsize )
		echo wp_get_attachment_link($id, 'full', $permalink);
	else
		echo wp_get_attachment_link($id, 'thumbnail', $permalink);
}

function wp_get_attachment_link( $id = 0, $size = 'thumbnail', $permalink = false, $icon = false, $text = false, $attr = '' ) {
	$_post = get_post( $id );

	if ( empty( $_post ) || ( 'attachment' != $_post->post_type ) || ! $url = wp_get_attachment_url( $_post->ID ) )
		return 'Missing Attachment';

	if ( $permalink )
		$url = get_attachment_link( $_post->ID );

	if ( $text ) {
		$link_text = $text;
	} elseif ( $size && 'none' != $size ) {
		$link_text = wp_get_attachment_image( $_post->ID, $size, $icon, $attr );
	} else {
		$link_text = '';
	}

	if ( trim( $link_text ) == '' )
		$link_text = $_post->post_title;

	return apply_filters( 'wp_get_attachment_link', "<a href='" . esc_url( $url ) . "'>$link_text</a>", $id, $size, $permalink, $icon, $text );
}

function prepend_attachment($content) {
	$post = get_post();

	if ( empty($post->post_type) || $post->post_type != 'attachment' )
		return $content;

	if ( wp_attachment_is( 'video', $post ) ) {
		$meta = wp_get_attachment_metadata( get_the_ID() );
		$atts = array( 'src' => wp_get_attachment_url() );
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$atts['width'] = (int) $meta['width'];
			$atts['height'] = (int) $meta['height'];
		}
		if ( has_post_thumbnail() ) {
			$atts['poster'] = wp_get_attachment_url( get_post_thumbnail_id() );
		}
		$p = wp_video_shortcode( $atts );
	} elseif ( wp_attachment_is( 'audio', $post ) ) {
		$p = wp_audio_shortcode( array( 'src' => wp_get_attachment_url() ) );
	} else {
		$p = '<p class="attachment">';
		// show the medium sized image representation of the attachment if available, and link to the raw file
		$p .= wp_get_attachment_link(0, 'medium', false);
		$p .= '</p>';
	}

	$p = apply_filters( 'prepend_attachment', $p );

	return "$p\n$content";
}

function get_the_password_form( $post = 0 ) {
	$post = get_post( $post );
	$label = 'pwbox-' . ( empty($post->ID) ? rand() : $post->ID );
	$output = '<form action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" class="post-password-form" method="post">
	<p>This content is password protected. To view it please enter your password below:</p>
	<p><label for="' . $label . '">Password: <input name="post_password" id="' . $label . '" type="password" size="20" /></label> <input type="submit" name="Submit" value="' . esc_attr_x( 'Enter', 'post password form' ) . '" /></p></form>
	';

	return apply_filters( 'the_password_form', $output );
}

function is_page_template( $template = '' ) {
	if ( ! is_page() )
		return false;

	$page_template = get_page_template_slug( get_queried_object_id() );

	if ( empty( $template ) )
		return (bool) $page_template;

	if ( $template == $page_template )
		return true;

	if ( is_array( $template ) ) {
		if ( ( in_array( 'default', $template, true ) && ! $page_template )
			|| in_array( $page_template, $template, true )
		) {
			return true;
		}
	}

	return ( 'default' === $template && ! $page_template );
}

function get_page_template_slug( $post_id = null ) {
	$post = get_post( $post_id );
	if ( ! $post || 'page' != $post->post_type )
		return false;
	$template = get_post_meta( $post->ID, '_wp_page_template', true );
	if ( ! $template || 'default' == $template )
		return '';
	return $template;
}

function wp_post_revision_title( $revision, $link = true ) {
	if ( !$revision = get_post( $revision ) )
		return $revision;

	if ( !in_array( $revision->post_type, array( 'post', 'page', 'revision' ) ) )
		return false;

	$datef = 'F j, Y @ H:i:s';
	$autosavef = '%1$s [Autosave]';
	$currentf  = '%1$s [Current Revision]';
	$date = date_i18n( $datef, strtotime( $revision->post_modified ) );
	if ( $link && current_user_can( 'edit_post', $revision->ID ) && $link = get_edit_post_link( $revision->ID ) )
		$date = "<a href='$link'>$date</a>";

	if ( !wp_is_post_revision( $revision ) )
		$date = sprintf( $currentf, $date );
	elseif ( wp_is_post_autosave( $revision ) )
		$date = sprintf( $autosavef, $date );

	return $date;
}

function wp_post_revision_title_expanded( $revision, $link = true ) {
	if ( !$revision = get_post( $revision ) )
		return $revision;

	if ( !in_array( $revision->post_type, array( 'post', 'page', 'revision' ) ) )
		return false;

	$author = get_the_author_meta( 'display_name', $revision->post_author );
	$datef = 'F j, Y @ H:i:s';
	$gravatar = get_avatar( $revision->post_author, 24 );
	$date = date_i18n( $datef, strtotime( $revision->post_modified ) );
	if ( $link && current_user_can( 'edit_post', $revision->ID ) && $link = get_edit_post_link( $revision->ID ) )
		$date = "<a href='$link'>$date</a>";

	$revision_date_author = sprintf(
		'%1$s %2$s, %3$s ago (%4$s)',
		$gravatar,
		$author,
		human_time_diff( strtotime( $revision->post_modified ), current_time( 'timestamp' ) ),
		$date
	);

	$autosavef = '%1$s [Autosave]';
	$currentf  = '%1$s [Current Revision]';
	if ( !wp_is_post_revision( $revision ) )
		$revision_date_author = sprintf( $currentf, $revision_date_author );
	elseif ( wp_is_post_autosave( $revision ) )
		$revision_date_author = sprintf( $autosavef, $revision_date_author );
	return apply_filters( 'wp_post_revision_title_expanded', $revision_date_author, $revision, $link );
}

function wp_list_post_revisions( $post_id = 0, $type = 'all' ) {
	if ( ! $post = get_post( $post_id ) )
		return;

	if ( is_array( $type ) ) {
		$type = ! empty( $type['type'] ) ? $type['type']  : $type;
		_deprecated_argument( __FUNCTION__, '3.6' );
	}

	if ( ! $revisions = wp_get_post_revisions( $post->ID ) )
		return;

	$rows = '';
	foreach ( $revisions as $revision ) {
		if ( ! current_user_can( 'read_post', $revision->ID ) )
			continue;

		$is_autosave = wp_is_post_autosave( $revision );
		if ( ( 'revision' === $type && $is_autosave ) || ( 'autosave' === $type && ! $is_autosave ) )
			continue;

		$rows .= "\t<li>" . wp_post_revision_title_expanded( $revision ) . "</li>\n";
	}
	echo "<div class='hide-if-js'><p>JavaScript must be enabled to use this feature.</p></div>\n";
	echo "<ul class='post-revisions hide-if-no-js'>\n";
	echo $rows;
	echo "</ul>";
}
