<?php
/**
 * Loads the correct template based on the visitor's url
 * @package WordPress
 */

do_action( 'template_redirect' );

if ( 'HEAD' === $_SERVER['REQUEST_METHOD'] && apply_filters( 'exit_on_http_head', true ) )
	exit();

if ( is_robots() ) :
	do_action( 'do_robots' );
	return;
endif;

$template = false;
if ( is_404()            && $template = get_query_template('404')     ) :
elseif ( is_search()         && $template = get_query_template('search')  ) :
elseif ( is_front_page()     && $template = get_front_page_template()     ) :
elseif ( is_home()           && $template = get_home_template()           ) :
elseif ( is_post_type_archive() && $template = get_post_type_archive_template() ) :
elseif ( is_tax()            && $template = get_taxonomy_template()       ) :
	remove_filter('the_content', 'prepend_attachment');
elseif ( is_single()         && $template = get_single_template()         ) :
elseif ( is_page()           && $template = get_page_template()           ) :
elseif ( is_singular()       && $template = get_query_template( 'singular' )  ) :
elseif ( is_category()       && $template = get_category_template()       ) :
elseif ( is_tag()            && $template = get_tag_template()            ) :
elseif ( is_date()           && $template = get_query_template('date')    ) :
elseif ( is_archive()        && $template = get_archive_template()        ) :
elseif ( is_paged()          && $template = get_query_template('paged')   ) :
else :
	$template = get_query_template('index');
endif;

if ( $template ) {
	include( $template );
} elseif ( current_user_can( 'switch_themes' ) ) {
	$theme = wp_get_theme();
	if ( $theme->errors() ) {
		wp_die( $theme->errors() );
	}
}
return;
