<?php
/**
 * Taxonomy API: Core category-specific functionality
 *
 * @package WordPress
 * @subpackage Taxonomy
 */

function get_categories( $args = '' ) {
	$defaults = array( 'taxonomy' => 'category' );
	$args = wp_parse_args( $args, $defaults );
	$taxonomy = $args['taxonomy'];
	$taxonomy = apply_filters( 'get_categories_taxonomy', $taxonomy, $args );
	$categories = get_terms( $taxonomy, $args );
	if ( is_wp_error( $categories ) ) {
		$categories = array();
	} else {
		$categories = (array) $categories;
		foreach ( array_keys( $categories ) as $k ) {
			_make_cat_compat( $categories[ $k ] );
		}
	}
	return $categories;
}

function get_category( $category, $output = OBJECT, $filter = 'raw' ) {
	$category = get_term( $category, 'category', $output, $filter );
	if ( is_wp_error( $category ) ) return $category;
	_make_cat_compat( $category );
	return $category;
}

function get_category_by_path( $category_path, $full_match = true, $output = OBJECT ) {
	$category_path = rawurlencode( urldecode( $category_path ) );
	$category_path = str_replace( '%2F', '/', $category_path );
	$category_path = str_replace( '%20', ' ', $category_path );
	$category_paths = '/' . trim( $category_path, '/' );
	$leaf_path  = sanitize_title( basename( $category_paths ) );
	$category_paths = explode( '/', $category_paths );
	$full_path = '';
	foreach ( (array) $category_paths as $pathdir ) {
		$full_path .= ( $pathdir != '' ? '/' : '' ) . sanitize_title( $pathdir );
	}
	$categories = get_terms( 'category', array('get' => 'all', 'slug' => $leaf_path) );
	if ( empty( $categories ) ) { return; }
	foreach ( $categories as $category ) {
		$path = '/' . $leaf_path;
		$curcategory = $category;
		while ( ( $curcategory->parent != 0 ) && ( $curcategory->parent != $curcategory->term_id ) ) {
			$curcategory = get_term( $curcategory->parent, 'category' );
			if ( is_wp_error( $curcategory ) ) {
				return $curcategory;
			}
			$path = '/' . $curcategory->slug . $path;
		}

		if ( $path == $full_path ) {
			$category = get_term( $category->term_id, 'category', $output );
			_make_cat_compat( $category );
			return $category;
		}
	}

	if ( ! $full_match ) {
		$category = get_term( reset( $categories )->term_id, 'category', $output );
		_make_cat_compat( $category );
		return $category;
	}
}

function get_category_by_slug( $slug  ) {
	$category = get_term_by( 'slug', $slug, 'category' );
	if ( $category ) _make_cat_compat( $category );
	return $category;
}

function get_cat_ID( $cat_name ) {
	$cat = get_term_by( 'name', $cat_name, 'category' );
	if ( $cat ) return $cat->term_id;
	return 0;
}

function get_cat_name( $cat_id ) {
	$cat_id = (int) $cat_id;
	$category = get_term( $cat_id, 'category' );
	if ( ! $category || is_wp_error( $category ) )
		return '';
	return $category->name;
}

function get_tags( $args = '' ) {
	$tags = get_terms( 'post_tag', $args );
	if ( empty( $tags ) ) {
		$return = array();
		return $return;
	}
	$tags = apply_filters( 'get_tags', $tags, $args );
	return $tags;
}

function _make_cat_compat( &$category ) {
	if ( is_object( $category ) && ! is_wp_error( $category ) ) {
		$category->cat_ID = $category->term_id;
		$category->category_count = $category->count;
		$category->category_description = $category->description;
		$category->cat_name = $category->name;
		$category->category_nicename = $category->slug;
		$category->category_parent = $category->parent;
	} elseif ( is_array( $category ) && isset( $category['term_id'] ) ) {
		$category['cat_ID'] = &$category['term_id'];
		$category['category_count'] = &$category['count'];
		$category['category_description'] = &$category['description'];
		$category['cat_name'] = &$category['name'];
		$category['category_nicename'] = &$category['slug'];
		$category['category_parent'] = &$category['parent'];
	}
}
