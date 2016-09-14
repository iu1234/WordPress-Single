<?php
/**
 * Bookmark Template Functions for usage in Themes
 *
 * @package WordPress
 * @subpackage Template
 */

function _walk_bookmarks( $bookmarks, $args = '' ) {
	$defaults = array(
		'show_updated' => 0, 'show_description' => 0,
		'show_images' => 1, 'show_name' => 0,
		'before' => '<li>', 'after' => '</li>', 'between' => "\n",
		'show_rating' => 0, 'link_before' => '', 'link_after' => ''
	);

	$r = wp_parse_args( $args, $defaults );

	$output = ''; // Blank string to start with.

	foreach ( (array) $bookmarks as $bookmark ) {
		if ( ! isset( $bookmark->recently_updated ) ) {
			$bookmark->recently_updated = false;
		}
		$output .= $r['before'];
		if ( $r['show_updated'] && $bookmark->recently_updated ) {
			$output .= '<em>';
		}
		$the_link = '#';
		if ( ! empty( $bookmark->link_url ) ) {
			$the_link = esc_url( $bookmark->link_url );
		}
		$desc = esc_attr( sanitize_bookmark_field( 'link_description', $bookmark->link_description, $bookmark->link_id, 'display' ) );
		$name = esc_attr( sanitize_bookmark_field( 'link_name', $bookmark->link_name, $bookmark->link_id, 'display' ) );
 		$title = $desc;

		if ( $r['show_updated'] ) {
			if ( '00' != substr( $bookmark->link_updated_f, 0, 2 ) ) {
				$title .= ' (';
				$title .= sprintf(
					'Last updated: %s',
					date(
						get_option( 'links_updated_date_format' ),
						$bookmark->link_updated_f + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )
					)
				);
				$title .= ')';
			}
		}
		$alt = ' alt="' . $name . ( $r['show_description'] ? ' ' . $title : '' ) . '"';

		if ( '' != $title ) {
			$title = ' title="' . $title . '"';
		}
		$rel = $bookmark->link_rel;
		if ( '' != $rel ) {
			$rel = ' rel="' . esc_attr($rel) . '"';
		}
		$target = $bookmark->link_target;
		if ( '' != $target ) {
			$target = ' target="' . $target . '"';
		}
		$output .= '<a href="' . $the_link . '"' . $rel . $title . $target . '>';

		$output .= $r['link_before'];

		if ( $bookmark->link_image != null && $r['show_images'] ) {
			if ( strpos( $bookmark->link_image, 'http' ) === 0 ) {
				$output .= "<img src=\"$bookmark->link_image\" $alt $title />";
			} else { // If it's a relative path
				$output .= "<img src=\"" . get_option('siteurl') . "$bookmark->link_image\" $alt $title />";
			}
			if ( $r['show_name'] ) {
				$output .= " $name";
			}
		} else {
			$output .= $name;
		}

		$output .= $r['link_after'];

		$output .= '</a>';

		if ( $r['show_updated'] && $bookmark->recently_updated ) {
			$output .= '</em>';
		}

		if ( $r['show_description'] && '' != $desc ) {
			$output .= $r['between'] . $desc;
		}

		if ( $r['show_rating'] ) {
			$output .= $r['between'] . sanitize_bookmark_field(
				'link_rating',
				$bookmark->link_rating,
				$bookmark->link_id,
				'display'
			);
		}
		$output .= $r['after'] . "\n";
	} // end while

	return $output;
}

function wp_list_bookmarks( $args = '' ) {
	$defaults = array(
		'orderby' => 'name', 'order' => 'ASC',
		'limit' => -1, 'category' => '', 'exclude_category' => '',
		'category_name' => '', 'hide_invisible' => 1,
		'show_updated' => 0, 'echo' => 1,
		'categorize' => 1, 'title_li' => __('Bookmarks'),
		'title_before' => '<h2>', 'title_after' => '</h2>',
		'category_orderby' => 'name', 'category_order' => 'ASC',
		'class' => 'linkcat', 'category_before' => '<li id="%id" class="%class">',
		'category_after' => '</li>'
	);

	$r = wp_parse_args( $args, $defaults );

	$output = '';

	if ( ! is_array( $r['class'] ) ) {
		$r['class'] = explode( ' ', $r['class'] );
	}
 	$r['class'] = array_map( 'sanitize_html_class', $r['class'] );
 	$r['class'] = trim( join( ' ', $r['class'] ) );

	if ( $r['categorize'] ) {
		$cats = get_terms( 'link_category', array(
			'name__like' => $r['category_name'],
			'include' => $r['category'],
			'exclude' => $r['exclude_category'],
			'orderby' => $r['category_orderby'],
			'order' => $r['category_order'],
			'hierarchical' => 0
		) );
		if ( empty( $cats ) ) {
			$r['categorize'] = false;
		}
	}

	if ( $r['categorize'] ) {
		// Split the bookmarks into ul's for each category
		foreach ( (array) $cats as $cat ) {
			$params = array_merge( $r, array( 'category' => $cat->term_id ) );
			$bookmarks = get_bookmarks( $params );
			if ( empty( $bookmarks ) ) {
				continue;
			}
			$output .= str_replace(
				array( '%id', '%class' ),
				array( "linkcat-$cat->term_id", $r['class'] ),
				$r['category_before']
			);
			/**
			 * Filter the bookmarks category name.
			 *
			 * @since 2.2.0
			 *
			 * @param string $cat_name The category name of bookmarks.
			 */
			$catname = apply_filters( 'link_category', $cat->name );

			$output .= $r['title_before'];
			$output .= $catname;
			$output .= $r['title_after'];
			$output .= "\n\t<ul class='xoxo blogroll'>\n";
			$output .= _walk_bookmarks( $bookmarks, $r );
			$output .= "\n\t</ul>\n";
			$output .= $r['category_after'] . "\n";
		}
	} else {
		//output one single list using title_li for the title
		$bookmarks = get_bookmarks( $r );

		if ( ! empty( $bookmarks ) ) {
			if ( ! empty( $r['title_li'] ) ) {
				$output .= str_replace(
					array( '%id', '%class' ),
					array( "linkcat-" . $r['category'], $r['class'] ),
					$r['category_before']
				);
				$output .= $r['title_before'];
				$output .= $r['title_li'];
				$output .= $r['title_after'];
				$output .= "\n\t<ul class='xoxo blogroll'>\n";
				$output .= _walk_bookmarks( $bookmarks, $r );
				$output .= "\n\t</ul>\n";
				$output .= $r['category_after'] . "\n";
			} else {
				$output .= _walk_bookmarks( $bookmarks, $r );
			}
		}
	}

	$html = apply_filters( 'wp_list_bookmarks', $output );

	if ( ! $r['echo'] ) {
		return $html;
	}
	echo $html;
}
