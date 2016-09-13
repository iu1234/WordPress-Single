<?php
/**
 * Template loading functions.
 *
 * @package WordPress
 * @subpackage Template
 */

function get_query_template( $type, $templates = array() ) {
	$type = preg_replace( '|[^a-z0-9-]+|', '', $type );

	if ( empty( $templates ) )
		$templates = array("{$type}.php");

	$template = locate_template( $templates );

	return apply_filters( "{$type}_template", $template );
}

function get_archive_template() {
	$post_types = array_filter( (array) get_query_var( 'post_type' ) );
	$templates = array();
	if ( count( $post_types ) == 1 ) {
		$post_type = reset( $post_types );
		$templates[] = "archive-{$post_type}.php";
	}
	$templates[] = 'archive.php';
	return get_query_template( 'archive', $templates );
}

function get_post_type_archive_template() {
	$post_type = get_query_var( 'post_type' );
	if ( is_array( $post_type ) )
		$post_type = reset( $post_type );
	$obj = get_post_type_object( $post_type );
	if ( ! $obj->has_archive )
		return '';
	return get_archive_template();
}

function get_author_template() {
	$author = get_queried_object();

	$templates = array();

	if ( $author instanceof WP_User ) {
		$templates[] = "author-{$author->user_nicename}.php";
		$templates[] = "author-{$author->ID}.php";
	}
	$templates[] = 'author.php';

	return get_query_template( 'author', $templates );
}

function get_category_template() {
	$category = get_queried_object();

	$templates = array();

	if ( ! empty( $category->slug ) ) {
		$templates[] = "category-{$category->slug}.php";
		$templates[] = "category-{$category->term_id}.php";
	}
	$templates[] = 'category.php';

	return get_query_template( 'category', $templates );
}

function get_tag_template() {
	$tag = get_queried_object();

	$templates = array();

	if ( ! empty( $tag->slug ) ) {
		$templates[] = "tag-{$tag->slug}.php";
		$templates[] = "tag-{$tag->term_id}.php";
	}
	$templates[] = 'tag.php';

	return get_query_template( 'tag', $templates );
}

function get_taxonomy_template() {
	$term = get_queried_object();

	$templates = array();

	if ( ! empty( $term->slug ) ) {
		$taxonomy = $term->taxonomy;
		$templates[] = "taxonomy-$taxonomy-{$term->slug}.php";
		$templates[] = "taxonomy-$taxonomy.php";
	}
	$templates[] = 'taxonomy.php';

	return get_query_template( 'taxonomy', $templates );
}

function get_home_template() {
	$templates = array( 'home.php', 'index.php' );
	return get_query_template( 'home', $templates );
}

function get_front_page_template() {
	$templates = array('front-page.php');
	return get_query_template( 'front_page', $templates );
}

function get_page_template() {
	$id = get_queried_object_id();
	$template = get_page_template_slug();
	$pagename = get_query_var('pagename');
	if ( ! $pagename && $id ) {
		$post = get_queried_object();
		if ( $post )
			$pagename = $post->post_name;
	}
	$templates = array();
	if ( $template && 0 === validate_file( $template ) )
		$templates[] = $template;
	if ( $pagename )
		$templates[] = "page-$pagename.php";
	if ( $id )
		$templates[] = "page-$id.php";
	$templates[] = 'page.php';
	return get_query_template( 'page', $templates );
}

function get_single_template() {
	$object = get_queried_object();
	$templates = array();
	if ( ! empty( $object->post_type ) ) {
		$templates[] = "single-{$object->post_type}-{$object->post_name}.php";
		$templates[] = "single-{$object->post_type}.php";
	}
	$templates[] = "single.php";
	return get_query_template( 'single', $templates );
}

function get_embed_template() {
	$object = get_queried_object();
	$templates = array();
	if ( ! empty( $object->post_type ) ) {
		$post_format = get_post_format( $object );
		if ( $post_format ) {
			$templates[] = "embed-{$object->post_type}-{$post_format}.php";
		}
		$templates[] = "embed-{$object->post_type}.php";
	}
	$templates[] = "embed.php";
	return get_query_template( 'embed', $templates );
}

function get_singular_template() {
	return get_query_template( 'singular' );
}

function get_attachment_template() {
	$attachment = get_queried_object();
	$templates = array();
	if ( $attachment ) {
		if ( false !== strpos( $attachment->post_mime_type, '/' ) ) {
			list( $type, $subtype ) = explode( '/', $attachment->post_mime_type );
		} else {
			list( $type, $subtype ) = array( $attachment->post_mime_type, '' );
		}

		if ( ! empty( $subtype ) ) {
			$templates[] = "{$type}-{$subtype}.php";
			$templates[] = "{$subtype}.php";
		}
		$templates[] = "{$type}.php";
	}
	$templates[] = 'attachment.php';

	return get_query_template( 'attachment', $templates );
}

function locate_template($template_names, $load = false, $require_once = true ) {
	$located = '';
	foreach ( (array) $template_names as $template_name ) {
		if ( !$template_name )
			continue;
		if ( file_exists(get_stylesheet_directory() . '/' . $template_name)) {
			$located = get_stylesheet_directory() . '/' . $template_name;
			break;
		} elseif ( file_exists(get_template_directory() . '/' . $template_name) ) {
			$located = get_template_directory() . '/' . $template_name;
			break;
		} elseif ( file_exists( ABSPATH . WPINC . '/theme-compat/' . $template_name ) ) {
			$located = ABSPATH . WPINC . '/theme-compat/' . $template_name;
			break;
		}
	}
	if ( $load && '' != $located )
		load_template( $located, $require_once );
	return $located;
}

function load_template( $_template_file, $require_once = true ) {
	global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID;

	if ( is_array( $wp_query->query_vars ) ) {
		extract( $wp_query->query_vars, EXTR_SKIP );
	}

	if ( isset( $s ) ) {
		$s = esc_attr( $s );
	}

	if ( $require_once ) {
		require_once( $_template_file );
	} else {
		require( $_template_file );
	}
}

