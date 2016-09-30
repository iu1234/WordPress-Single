<?php
/**
 * General template tags that can go anywhere in a template.
 *
 * @package WordPress
 * @subpackage Template
 */

function get_header( $name = null ) {
	$templates = array();
	$name = (string) $name;
	if ( '' !== $name ) { $templates[] = "header-{$name}.php"; }
	$templates[] = 'header.php';
	locate_template( $templates, true );
}

function get_footer( $name = null ) {
	$templates = array();
	$name = (string) $name;
	if ( '' !== $name ) { $templates[] = "footer-{$name}.php"; }
	$templates[]    = 'footer.php';
	locate_template( $templates, true );
}

function get_sidebar( $name = null ) {
	$templates = array();
	$name = (string) $name;
	if ( '' !== $name ) { $templates[] = "sidebar-{$name}.php"; }
	$templates[] = 'sidebar.php';
	locate_template( $templates, true );
}

function get_template_part( $slug, $name = null ) {
	$templates = array();
	$name = (string) $name;
	if ( '' !== $name )	{ $templates[] = "{$slug}-{$name}.php"; }
	$templates[] = "{$slug}.php";
	locate_template($templates, true, false);
}

function get_search_form( $echo = true ) {
	$format = current_theme_supports( 'html5', 'search-form' ) ? 'html5' : 'xhtml';
	$format = apply_filters( 'search_form_format', $format );
	$search_form_template = locate_template( 'searchform.php' );
	if ( '' != $search_form_template ) {
		ob_start();
		require( $search_form_template );
		$form = ob_get_clean();
	} else {
		if ( 'html5' == $format ) {
			$form = '<form role="search" method="get" class="search-form" action="' . esc_url( home_url( '/' ) ) . '">
				<label>
					<span class="screen-reader-text">Search for:</span>
					<input type="search" class="search-field" placeholder="Search &hellip;" value="' . get_search_query() . '" name="s" />
				</label>
				<input type="submit" class="search-submit" value="搜索" />
			</form>';
		} else {
			$form = '<form role="search" method="get" id="searchform" class="searchform" action="' . esc_url( home_url( '/' ) ) . '">
				<div>
					<label class="screen-reader-text" for="s">Search for:</label>
					<input type="text" value="' . get_search_query() . '" name="s" id="s" />
					<input type="submit" id="searchsubmit" value="搜索" />
				</div>
			</form>';
		}
	}
	$result = apply_filters( 'get_search_form', $form );
	if ( null === $result )
		$result = $form;
	if ( $echo )
		echo $result;
	else
		return $result;
}

function wp_loginout($redirect = '', $echo = true) {
	if ( ! is_user_logged_in() )
		$link = '<a href="' . esc_url( wp_login_url($redirect) ) . '">登录</a>';
	else
		$link = '<a href="' . esc_url( wp_logout_url($redirect) ) . '">退出</a>';
	if ( $echo ) {
		echo apply_filters( 'loginout', $link );
	} else {
		return apply_filters( 'loginout', $link );
	}
}

function wp_logout_url($redirect = '') {
	$args = array( 'action' => 'logout' );
	if ( !empty($redirect) ) {
		$args['redirect_to'] = urlencode( $redirect );
	}
	$logout_url = add_query_arg($args, site_url('wp-login.php', 'login'));
	$logout_url = wp_nonce_url( $logout_url, 'log-out' );
	return apply_filters( 'logout_url', $logout_url, $redirect );
}

function wp_login_url($redirect = '', $force_reauth = false) {
	$login_url = site_url('wp-login.php', 'login');
	if ( !empty($redirect) )
		$login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
	if ( $force_reauth )
		$login_url = add_query_arg('reauth', '1', $login_url);
	return apply_filters( 'login_url', $login_url, $redirect, $force_reauth );
}

function wp_registration_url() {
	return apply_filters( 'register_url', site_url( 'wp-login.php?action=register', 'login' ) );
}

function wp_login_form( $args = array() ) {
	$defaults = array(
		'echo' => true,
		'redirect' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
		'form_id' => 'loginform',
		'label_username' => 'Username or Email',
		'label_password' => 'Password',
		'label_remember' => 'Remember Me',
		'label_log_in' => 'Log In',
		'id_username' => 'user_login',
		'id_password' => 'user_pass',
		'id_remember' => 'rememberme',
		'id_submit' => 'wp-submit',
		'remember' => true,
		'value_username' => '',
		'value_remember' => false,
	);
	$args = wp_parse_args( $args, apply_filters( 'login_form_defaults', $defaults ) );
	$login_form_top = apply_filters( 'login_form_top', '', $args );
	$login_form_middle = apply_filters( 'login_form_middle', '', $args );
	$login_form_bottom = apply_filters( 'login_form_bottom', '', $args );
	$form = '
		<form name="' . $args['form_id'] . '" id="' . $args['form_id'] . '" action="' . esc_url( site_url( 'wp-login.php', 'login_post' ) ) . '" method="post">
			' . $login_form_top . '
			<p class="login-username">
				<label for="' . esc_attr( $args['id_username'] ) . '">' . esc_html( $args['label_username'] ) . '</label>
				<input type="text" name="log" id="' . esc_attr( $args['id_username'] ) . '" class="input" value="' . esc_attr( $args['value_username'] ) . '" size="20" />
			</p>
			<p class="login-password">
				<label for="' . esc_attr( $args['id_password'] ) . '">' . esc_html( $args['label_password'] ) . '</label>
				<input type="password" name="pwd" id="' . esc_attr( $args['id_password'] ) . '" class="input" value="" size="20" />
			</p>
			' . $login_form_middle . '
			' . ( $args['remember'] ? '<p class="login-remember"><label><input name="rememberme" type="checkbox" id="' . esc_attr( $args['id_remember'] ) . '" value="forever"' . ( $args['value_remember'] ? ' checked="checked"' : '' ) . ' /> ' . esc_html( $args['label_remember'] ) . '</label></p>' : '' ) . '
			<p class="login-submit">
				<input type="submit" name="wp-submit" id="' . esc_attr( $args['id_submit'] ) . '" class="button-primary" value="' . esc_attr( $args['label_log_in'] ) . '" />
				<input type="hidden" name="redirect_to" value="' . esc_url( $args['redirect'] ) . '" />
			</p>
			' . $login_form_bottom . '
		</form>';
	if ( $args['echo'] )
		echo $form;
	else
		return $form;
}

function wp_lostpassword_url( $redirect = '' ) {
	$args = array( 'action' => 'lostpassword' );
	if ( !empty($redirect) ) {
		$args['redirect_to'] = $redirect;
	}
	$lostpassword_url = add_query_arg( $args, network_site_url('wp-login.php', 'login') );
	return apply_filters( 'lostpassword_url', $lostpassword_url, $redirect );
}

function wp_register( $before = '<li>', $after = '</li>', $echo = true ) {
	if ( ! is_user_logged_in() ) {
		if ( get_option('users_can_register') )
			$link = $before . '<a href="' . esc_url( wp_registration_url() ) . '">Register</a>' . $after;
		else
			$link = '';
	} elseif ( current_user_can( 'read' ) ) {
		$link = $before . '<a href="' . admin_url() . '">Site Admin</a>' . $after;
	} else {
		$link = '';
	}
	$link = apply_filters( 'register', $link );
	if ( $echo ) {
		echo $link;
	} else {
		return $link;
	}
}

function wp_meta() {
	do_action( 'wp_meta' );
}

function get_bloginfo( $show = '', $filter = 'raw' ) {
	switch( $show ) {
		case 'url' :
			$output = home_url();
			break;
		case 'wpurl' :
			$output = site_url();
			break;
		case 'description':
			$output = get_option('blogdescription');
			break;
		case 'stylesheet_url':
			$output = get_stylesheet_uri();
			break;
		case 'stylesheet_directory':
			$output = get_stylesheet_directory_uri();
			break;
		case 'template_directory':
		case 'template_url':
			$output = get_template_directory_uri();
			break;
		case 'admin_email':
			$output = get_option('admin_email');
			break;
		case 'charset':
			$output = get_option('blog_charset');
			if ('' == $output) $output = 'UTF-8';
			break;
		case 'html_type' :
			$output = get_option('html_type');
			break;
		case 'version':
			global $wp_version;
			$output = $wp_version;
			break;
		case 'name':
		default:
			$output = get_option('blogname');
			break;
	}
	$url = true;
	if (strpos($show, 'url') === false &&
		strpos($show, 'directory') === false &&
		strpos($show, 'home') === false)
		$url = false;
	if ( 'display' == $filter ) {
		if ( $url ) {
			$output = apply_filters( 'bloginfo_url', $output, $show );
		} else {
			$output = apply_filters( 'bloginfo', $output, $show );
		}
	}
	return $output;
}

function get_site_icon_url( $size = 512, $url = '' ) {
	$site_icon_id = get_option( 'site_icon' );
	if ( $site_icon_id ) {
		if ( $size >= 512 ) {
			$size_data = 'full';
		} else {
			$size_data = array( $size, $size );
		}
		$url = wp_get_attachment_image_url( $site_icon_id, $size_data );
	}
	return $url;
}

function has_site_icon() {
	return (bool) get_site_icon_url( 512, '' );
}

function has_custom_logo( $blog_id = 0 ) {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	return (bool) $custom_logo_id;
}

function get_custom_logo( $blog_id = 0 ) {
	$html = '';
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$html = sprintf( '<a href="%1$s" class="custom-logo-link" rel="home" itemprop="url">%2$s</a>',
			esc_url( home_url( '/' ) ),
			wp_get_attachment_image( $custom_logo_id, 'full', false, array(
				'class'    => 'custom-logo',
				'itemprop' => 'logo',
			) )
		);
	} elseif ( is_customize_preview() ) {
		$html = sprintf( '<a href="%1$s" class="custom-logo-link" style="display:none;"><img class="custom-logo"/></a>', esc_url( home_url( '/' ) ) );
	}
	return $html;
}

function wp_get_document_title() {
	$title = apply_filters( 'pre_get_document_title', '' );
	if ( ! empty( $title ) ) { return $title; }
	global $page, $paged;
	$title = array(	'title' => '' );

	if ( is_404() ) {
		$title['title'] = 'Page not found';
	} elseif ( is_search() ) {
		$title['title'] = sprintf( 'Search Results for &#8220;%s&#8221;', get_search_query() );
	} elseif ( is_front_page() ) {
		$title['title'] = get_bloginfo( 'name', 'display' );
	} elseif ( is_post_type_archive() ) {
		$title['title'] = post_type_archive_title( '', false );
	} elseif ( is_tax() ) {
		$title['title'] = single_term_title( '', false );
	} elseif ( is_home() || is_singular() ) {
		$title['title'] = single_post_title( '', false );
	} elseif ( is_category() || is_tag() ) {
		$title['title'] = single_term_title( '', false );
	} elseif ( is_author() && $author = get_queried_object() ) {
		$title['title'] = $author->display_name;
	} elseif ( is_year() ) {
		$title['title'] = get_the_date( 'Y' );
	} elseif ( is_month() ) {
		$title['title'] = get_the_date( 'F Y' );
	} elseif ( is_day() ) {
		$title['title'] = get_the_date();
	}
	if ( ( $paged >= 2 || $page >= 2 ) && ! is_404() ) {
		$title['page'] = sprintf( 'Page %s', max( $paged, $page ) );
	}
	if ( is_front_page() ) {
		$title['tagline'] = get_bloginfo( 'description', 'display' );
	} else {
		$title['site'] = get_bloginfo( 'name', 'display' );
	}
	$sep = apply_filters( 'document_title_separator', '-' );
	$title = apply_filters( 'document_title_parts', $title );
	$title = implode( " $sep ", array_filter( $title ) );
	$title = wptexturize( $title );
	$title = convert_chars( $title );
	$title = esc_html( $title );
	$title = capital_P_dangit( $title );
	return $title;
}

function _wp_render_title_tag() {
	if ( ! current_theme_supports( 'title-tag' ) ) { return; }
	echo '<title>' . wp_get_document_title() . '</title>' . "\n";
}

function wp_title( $sep = '&raquo;', $display = true, $seplocation = '' ) {
	global $wp_locale;
	$m        = get_query_var( 'm' );
	$year     = get_query_var( 'year' );
	$monthnum = get_query_var( 'monthnum' );
	$day      = get_query_var( 'day' );
	$search   = get_query_var( 's' );
	$title    = '';

	$t_sep = '%WP_TITLE_SEP%';
	if ( is_single() || ( is_home() && ! is_front_page() ) || ( is_page() && ! is_front_page() ) ) {
		$title = single_post_title( '', false );
	}
	if ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object->has_archive ) {
			$title = post_type_archive_title( '', false );
		}
	}
	if ( is_category() || is_tag() ) {
		$title = single_term_title( '', false );
	}
	if ( is_tax() ) {
		$term = get_queried_object();
		if ( $term ) {
			$tax   = get_taxonomy( $term->taxonomy );
			$title = single_term_title( $tax->labels->name . $t_sep, false );
		}
	}
	if ( is_author() && ! is_post_type_archive() ) {
		$author = get_queried_object();
		if ( $author ) {
			$title = $author->display_name;
		}
	}

	if ( is_post_type_archive() && $post_type_object->has_archive ) {
		$title = post_type_archive_title( '', false );
	}

	if ( is_archive() && ! empty( $m ) ) {
		$my_year  = substr( $m, 0, 4 );
		$my_month = $wp_locale->get_month( substr( $m, 4, 2 ) );
		$my_day   = intval( substr( $m, 6, 2 ) );
		$title    = $my_year . ( $my_month ? $t_sep . $my_month : '' ) . ( $my_day ? $t_sep . $my_day : '' );
	}

	if ( is_archive() && ! empty( $year ) ) {
		$title = $year;
		if ( ! empty( $monthnum ) ) {
			$title .= $t_sep . $wp_locale->get_month( $monthnum );
		}
		if ( ! empty( $day ) ) {
			$title .= $t_sep . zeroise( $day, 2 );
		}
	}
	if ( is_search() ) { $title = sprintf( 'Search Results %1$s %2$s', $t_sep, strip_tags( $search ) ); }
	if ( is_404() ) { $title = 'Page not found'; }
	$prefix = '';
	if ( ! empty( $title ) ) { $prefix = " $sep "; }
	$title_array = apply_filters( 'wp_title_parts', explode( $t_sep, $title ) );
	if ( 'right' == $seplocation ) {
		$title_array = array_reverse( $title_array );
		$title       = implode( " $sep ", $title_array ) . $prefix;
	} else {
		$title = $prefix . implode( " $sep ", $title_array );
	}
	$title = apply_filters( 'wp_title', $title, $sep, $seplocation );
	if ( $display ) {
		echo $title;
	} else {
		return $title;
	}
}

function single_post_title( $prefix = '', $display = true ) {
	$_post = get_queried_object();
	if ( !isset($_post->post_title) )
		return;
	$title = apply_filters( 'single_post_title', $_post->post_title, $_post );
	if ( $display )
		echo $prefix . $title;
	else
		return $prefix . $title;
}

function post_type_archive_title( $prefix = '', $display = true ) {
	if ( ! is_post_type_archive() )
		return;
	$post_type = get_query_var( 'post_type' );
	if ( is_array( $post_type ) )
		$post_type = reset( $post_type );
	$post_type_obj = get_post_type_object( $post_type );
	$title = apply_filters( 'post_type_archive_title', $post_type_obj->labels->name, $post_type );
	if ( $display )
		echo $prefix . $title;
	else
		return $prefix . $title;
}

function single_cat_title( $prefix = '', $display = true ) {
	return single_term_title( $prefix, $display );
}

function single_tag_title( $prefix = '', $display = true ) {
	return single_term_title( $prefix, $display );
}

function single_term_title( $prefix = '', $display = true ) {
	$term = get_queried_object();

	if ( !$term )
		return;

	if ( is_category() ) {
		$term_name = apply_filters( 'single_cat_title', $term->name );
	} elseif ( is_tag() ) {
		$term_name = apply_filters( 'single_tag_title', $term->name );
	} elseif ( is_tax() ) {
		$term_name = apply_filters( 'single_term_title', $term->name );
	} else {
		return;
	}

	if ( empty( $term_name ) )
		return;

	if ( $display )
		echo $prefix . $term_name;
	else
		return $prefix . $term_name;
}

function single_month_title($prefix = '', $display = true ) {
	global $wp_locale;

	$m = get_query_var('m');
	$year = get_query_var('year');
	$monthnum = get_query_var('monthnum');

	if ( !empty($monthnum) && !empty($year) ) {
		$my_year = $year;
		$my_month = $wp_locale->get_month($monthnum);
	} elseif ( !empty($m) ) {
		$my_year = substr($m, 0, 4);
		$my_month = $wp_locale->get_month(substr($m, 4, 2));
	}

	if ( empty($my_month) )
		return false;

	$result = $prefix . $my_month . $prefix . $my_year;

	if ( !$display )
		return $result;
	echo $result;
}

function the_archive_title( $before = '', $after = '' ) {
	$title = get_the_archive_title();

	if ( ! empty( $title ) ) {
		echo $before . $title . $after;
	}
}

function get_the_archive_title() {
	if ( is_category() ) {
		$title = sprintf( 'Category: %s', single_cat_title( '', false ) );
	} elseif ( is_tag() ) {
		$title = sprintf( 'Tag: %s', single_tag_title( '', false ) );
	} elseif ( is_author() ) {
		$title = sprintf( 'Author: %s', '<span class="vcard">' . get_the_author() . '</span>' );
	} elseif ( is_year() ) {
		$title = sprintf( 'Year: %s', get_the_date( 'Y' ) );
	} elseif ( is_month() ) {
		$title = sprintf( 'Month: %s', get_the_date( 'F Y' ) );
	} elseif ( is_day() ) {
		$title = sprintf( 'Day: %s', get_the_date( 'F j, Y' ) );
	} elseif ( is_tax( 'post_format' ) ) {
		if ( is_tax( 'post_format', 'post-format-aside' ) ) {
			$title = 'Asides';
		} elseif ( is_tax( 'post_format', 'post-format-gallery' ) ) {
			$title = 'Galleries';
		} elseif ( is_tax( 'post_format', 'post-format-image' ) ) {
			$title = 'Images';
		} elseif ( is_tax( 'post_format', 'post-format-video' ) ) {
			$title = 'Videos';
		} elseif ( is_tax( 'post_format', 'post-format-quote' ) ) {
			$title = 'Quotes';
		} elseif ( is_tax( 'post_format', 'post-format-link' ) ) {
			$title = 'Links';
		} elseif ( is_tax( 'post_format', 'post-format-status' ) ) {
			$title = 'Statuses';
		} elseif ( is_tax( 'post_format', 'post-format-audio' ) ) {
			$title = 'Audio';
		} elseif ( is_tax( 'post_format', 'post-format-chat' ) ) {
			$title = 'Chats';
		}
	} elseif ( is_post_type_archive() ) {
		$title = sprintf( 'Archives: %s', post_type_archive_title( '', false ) );
	} elseif ( is_tax() ) {
		$tax = get_taxonomy( get_queried_object()->taxonomy );
		$title = sprintf( '%1$s: %2$s', $tax->labels->singular_name, single_term_title( '', false ) );
	} else {
		$title = 'Archives';
	}
	return apply_filters( 'get_the_archive_title', $title );
}

function the_archive_description( $before = '', $after = '' ) {
	$description = get_the_archive_description();
	if ( $description ) {
		echo $before . $description . $after;
	}
}

function get_the_archive_description() {
	return apply_filters( 'get_the_archive_description', term_description() );
}

function get_archives_link($url, $text, $format = 'html', $before = '', $after = '') {
	$text = wptexturize($text);
	$url = esc_url($url);
	if ('link' == $format)
		$link_html = "\t<link rel='archives' title='" . esc_attr( $text ) . "' href='$url' />\n";
	elseif ('option' == $format)
		$link_html = "\t<option value='$url'>$before $text $after</option>\n";
	elseif ('html' == $format)
		$link_html = "\t<li>$before<a href='$url'>$text</a>$after</li>\n";
	else
		$link_html = "\t$before<a href='$url'>$text</a>$after\n";
	return apply_filters( 'get_archives_link', $link_html, $url, $text, $format, $before, $after );
}

function wp_get_archives( $args = '' ) {
	global $wpdb, $wp_locale;
	$defaults = array(
		'type' => 'monthly', 'limit' => '',
		'format' => 'html', 'before' => '',
		'after' => '', 'show_post_count' => false,
		'echo' => 1, 'order' => 'DESC',
		'post_type' => 'post'
	);

	$r = wp_parse_args( $args, $defaults );

	$post_type_object = get_post_type_object( $r['post_type'] );
	if ( ! is_post_type_viewable( $post_type_object ) ) {
		return;
	}
	$r['post_type'] = $post_type_object->name;

	if ( '' == $r['type'] ) {
		$r['type'] = 'monthly';
	}

	if ( ! empty( $r['limit'] ) ) {
		$r['limit'] = absint( $r['limit'] );
		$r['limit'] = ' LIMIT ' . $r['limit'];
	}

	$order = strtoupper( $r['order'] );
	if ( $order !== 'ASC' ) {
		$order = 'DESC';
	}
	$archive_week_separator = '&#8211;';

	$sql_where = $wpdb->prepare( "WHERE post_type = %s AND post_status = 'publish'", $r['post_type'] );

	$where = apply_filters( 'getarchives_where', $sql_where, $r );

	$join = apply_filters( 'getarchives_join', '', $r );

	$output = '';

	$last_changed = wp_cache_get( 'last_changed', 'posts' );
	if ( ! $last_changed ) {
		$last_changed = microtime();
		wp_cache_set( 'last_changed', $last_changed, 'posts' );
	}

	$limit = $r['limit'];

	if ( 'monthly' == $r['type'] ) {
		$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date $order $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {
			$after = $r['after'];
			foreach ( (array) $results as $result ) {
				$url = get_month_link( $result->year, $result->month );
				if ( 'post' !== $r['post_type'] ) {
					$url = add_query_arg( 'post_type', $r['post_type'], $url );
				}
				$text = sprintf( '%1$s %2$d', $wp_locale->get_month( $result->month ), $result->year );
				if ( $r['show_post_count'] ) {
					$r['after'] = '&nbsp;(' . $result->posts . ')' . $after;
				}
				$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
			}
		}
	} elseif ( 'yearly' == $r['type'] ) {
		$query = "SELECT YEAR(post_date) AS `year`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date) ORDER BY post_date $order $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {
			$after = $r['after'];
			foreach ( (array) $results as $result) {
				$url = get_year_link( $result->year );
				if ( 'post' !== $r['post_type'] ) {
					$url = add_query_arg( 'post_type', $r['post_type'], $url );
				}
				$text = sprintf( '%d', $result->year );
				if ( $r['show_post_count'] ) {
					$r['after'] = '&nbsp;(' . $result->posts . ')' . $after;
				}
				$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
			}
		}
	} elseif ( 'daily' == $r['type'] ) {
		$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, DAYOFMONTH(post_date) AS `dayofmonth`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date), MONTH(post_date), DAYOFMONTH(post_date) ORDER BY post_date $order $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {
			$after = $r['after'];
			foreach ( (array) $results as $result ) {
				$url  = get_day_link( $result->year, $result->month, $result->dayofmonth );
				if ( 'post' !== $r['post_type'] ) {
					$url = add_query_arg( 'post_type', $r['post_type'], $url );
				}
				$date = sprintf( '%1$d-%2$02d-%3$02d 00:00:00', $result->year, $result->month, $result->dayofmonth );
				$text = mysql2date( get_option( 'date_format' ), $date );
				if ( $r['show_post_count'] ) {
					$r['after'] = '&nbsp;(' . $result->posts . ')' . $after;
				}
				$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
			}
		}
	} elseif ( 'weekly' == $r['type'] ) {
		$week = _wp_mysql_week( '`post_date`' );
		$query = "SELECT DISTINCT $week AS `week`, YEAR( `post_date` ) AS `yr`, DATE_FORMAT( `post_date`, '%Y-%m-%d' ) AS `yyyymmdd`, count( `ID` ) AS `posts` FROM `$wpdb->posts` $join $where GROUP BY $week, YEAR( `post_date` ) ORDER BY `post_date` $order $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		$arc_w_last = '';
		if ( $results ) {
			$after = $r['after'];
			foreach ( (array) $results as $result ) {
				if ( $result->week != $arc_w_last ) {
					$arc_year       = $result->yr;
					$arc_w_last     = $result->week;
					$arc_week       = get_weekstartend( $result->yyyymmdd, get_option( 'start_of_week' ) );
					$arc_week_start = date_i18n( get_option( 'date_format' ), $arc_week['start'] );
					$arc_week_end   = date_i18n( get_option( 'date_format' ), $arc_week['end'] );
					$url            = sprintf( '%1$s/%2$s%3$sm%4$s%5$s%6$sw%7$s%8$d', home_url(), '', '?', '=', $arc_year, '&amp;', '=', $result->week );
					if ( 'post' !== $r['post_type'] ) {
						$url = add_query_arg( 'post_type', $r['post_type'], $url );
					}
					$text           = $arc_week_start . $archive_week_separator . $arc_week_end;
					if ( $r['show_post_count'] ) {
						$r['after'] = '&nbsp;(' . $result->posts . ')' . $after;
					}
					$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
				}
			}
		}
	} elseif ( ( 'postbypost' == $r['type'] ) || ('alpha' == $r['type'] ) ) {
		$orderby = ( 'alpha' == $r['type'] ) ? 'post_title ASC ' : 'post_date DESC, ID DESC ';
		$query = "SELECT * FROM $wpdb->posts $join $where ORDER BY $orderby $limit";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";
		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {
			foreach ( (array) $results as $result ) {
				if ( $result->post_date != '0000-00-00 00:00:00' ) {
					$url = get_permalink( $result );
					if ( $result->post_title ) {
						/** This filter is documented in wp-includes/post-template.php */
						$text = strip_tags( apply_filters( 'the_title', $result->post_title, $result->ID ) );
					} else {
						$text = $result->ID;
					}
					$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
				}
			}
		}
	}
	if ( $r['echo'] ) {
		echo $output;
	} else {
		return $output;
	}
}

function calendar_week_mod($num) {
	$base = 7;
	return ($num - $base*floor($num/$base));
}

function get_calendar( $initial = true, $echo = true ) {
	global $wpdb, $m, $monthnum, $year, $wp_locale, $posts;
	$key = md5( $m . $monthnum . $year );
	$cache = wp_cache_get( 'get_calendar', 'calendar' );
	if ( $cache && is_array( $cache ) && isset( $cache[ $key ] ) ) {
		$output = apply_filters( 'get_calendar', $cache[ $key ] );

		if ( $echo ) {
			echo $output;
			return;
		}

		return $output;
	}

	if ( ! is_array( $cache ) ) {
		$cache = array();
	}
	if ( ! $posts ) {
		$gotsome = $wpdb->get_var("SELECT 1 as test FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' LIMIT 1");
		if ( ! $gotsome ) {
			$cache[ $key ] = '';
			wp_cache_set( 'get_calendar', $cache, 'calendar' );
			return;
		}
	}

	if ( isset( $_GET['w'] ) ) {
		$w = (int) $_GET['w'];
	}
	$week_begins = (int) get_option( 'start_of_week' );
	$ts = current_time( 'timestamp' );

	// Let's figure out when we are
	if ( ! empty( $monthnum ) && ! empty( $year ) ) {
		$thismonth = zeroise( intval( $monthnum ), 2 );
		$thisyear = (int) $year;
	} elseif ( ! empty( $w ) ) {
		$thisyear = (int) substr( $m, 0, 4 );
		$d = ( ( $w - 1 ) * 7 ) + 6;
		$thismonth = $wpdb->get_var("SELECT DATE_FORMAT((DATE_ADD('{$thisyear}0101', INTERVAL $d DAY) ), '%m')");
	} elseif ( ! empty( $m ) ) {
		$thisyear = (int) substr( $m, 0, 4 );
		if ( strlen( $m ) < 6 ) {
			$thismonth = '01';
		} else {
			$thismonth = zeroise( (int) substr( $m, 4, 2 ), 2 );
		}
	} else {
		$thisyear = gmdate( 'Y', $ts );
		$thismonth = gmdate( 'm', $ts );
	}

	$unixmonth = mktime( 0, 0 , 0, $thismonth, 1, $thisyear );
	$last_day = date( 't', $unixmonth );

	$previous = $wpdb->get_row("SELECT MONTH(post_date) AS month, YEAR(post_date) AS year
		FROM $wpdb->posts
		WHERE post_date < '$thisyear-$thismonth-01'
		AND post_type = 'post' AND post_status = 'publish'
			ORDER BY post_date DESC
			LIMIT 1");
	$next = $wpdb->get_row("SELECT MONTH(post_date) AS month, YEAR(post_date) AS year
		FROM $wpdb->posts
		WHERE post_date > '$thisyear-$thismonth-{$last_day} 23:59:59'
		AND post_type = 'post' AND post_status = 'publish'
			ORDER BY post_date ASC
			LIMIT 1");

	$calendar_caption = _x('%1$s %2$s', 'calendar caption');
	$calendar_output = '<table id="wp-calendar">
	<caption>' . sprintf(
		$calendar_caption,
		$wp_locale->get_month( $thismonth ),
		date( 'Y', $unixmonth )
	) . '</caption>
	<thead>
	<tr>';

	$myweek = array();

	for ( $wdcount = 0; $wdcount <= 6; $wdcount++ ) {
		$myweek[] = $wp_locale->get_weekday( ( $wdcount + $week_begins ) % 7 );
	}
	foreach ( $myweek as $wd ) {
		$day_name = $initial ? $wp_locale->get_weekday_initial( $wd ) : $wp_locale->get_weekday_abbrev( $wd );
		$wd = esc_attr( $wd );
		$calendar_output .= "\n\t\t<th scope=\"col\" title=\"$wd\">$day_name</th>";
	}

	$calendar_output .= '
	</tr>
	</thead>

	<tfoot>
	<tr>';

	if ( $previous ) {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="prev"><a href="' . get_month_link( $previous->year, $previous->month ) . '">&laquo; ' .
			$wp_locale->get_month_abbrev( $wp_locale->get_month( $previous->month ) ) .
		'</a></td>';
	} else {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="prev" class="pad">&nbsp;</td>';
	}

	$calendar_output .= "\n\t\t".'<td class="pad">&nbsp;</td>';

	if ( $next ) {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="next"><a href="' . get_month_link( $next->year, $next->month ) . '">' .
			$wp_locale->get_month_abbrev( $wp_locale->get_month( $next->month ) ) .
		' &raquo;</a></td>';
	} else {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="next" class="pad">&nbsp;</td>';
	}

	$calendar_output .= '
	</tr>
	</tfoot>

	<tbody>
	<tr>';

	$daywithpost = array();

	// Get days with posts
	$dayswithposts = $wpdb->get_results("SELECT DISTINCT DAYOFMONTH(post_date)
		FROM $wpdb->posts WHERE post_date >= '{$thisyear}-{$thismonth}-01 00:00:00'
		AND post_type = 'post' AND post_status = 'publish'
		AND post_date <= '{$thisyear}-{$thismonth}-{$last_day} 23:59:59'", ARRAY_N);
	if ( $dayswithposts ) {
		foreach ( (array) $dayswithposts as $daywith ) {
			$daywithpost[] = $daywith[0];
		}
	}

	// See how much we should pad in the beginning
	$pad = calendar_week_mod( date( 'w', $unixmonth ) - $week_begins );
	if ( 0 != $pad ) {
		$calendar_output .= "\n\t\t".'<td colspan="'. esc_attr( $pad ) .'" class="pad">&nbsp;</td>';
	}

	$newrow = false;
	$daysinmonth = (int) date( 't', $unixmonth );

	for ( $day = 1; $day <= $daysinmonth; ++$day ) {
		if ( isset($newrow) && $newrow ) {
			$calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
		}
		$newrow = false;

		if ( $day == gmdate( 'j', $ts ) &&
			$thismonth == gmdate( 'm', $ts ) &&
			$thisyear == gmdate( 'Y', $ts ) ) {
			$calendar_output .= '<td id="today">';
		} else {
			$calendar_output .= '<td>';
		}

		if ( in_array( $day, $daywithpost ) ) {
			// any posts today?
			$date_format = date( 'F j, Y', strtotime( "{$thisyear}-{$thismonth}-{$day}" ) );
			$label = sprintf( 'Posts published on %s', $date_format );
			$calendar_output .= sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_day_link( $thisyear, $thismonth, $day ),
				esc_attr( $label ),
				$day
			);
		} else {
			$calendar_output .= $day;
		}
		$calendar_output .= '</td>';

		if ( 6 == calendar_week_mod( date( 'w', mktime(0, 0 , 0, $thismonth, $day, $thisyear ) ) - $week_begins ) ) {
			$newrow = true;
		}
	}

	$pad = 7 - calendar_week_mod( date( 'w', mktime( 0, 0 , 0, $thismonth, $day, $thisyear ) ) - $week_begins );
	if ( $pad != 0 && $pad != 7 ) {
		$calendar_output .= "\n\t\t".'<td class="pad" colspan="'. esc_attr( $pad ) .'">&nbsp;</td>';
	}
	$calendar_output .= "\n\t</tr>\n\t</tbody>\n\t</table>";

	$cache[ $key ] = $calendar_output;
	wp_cache_set( 'get_calendar', $cache, 'calendar' );

	if ( $echo ) {
		echo apply_filters( 'get_calendar', $calendar_output );
		return;
	}
	return apply_filters( 'get_calendar', $calendar_output );
}

function delete_get_calendar_cache() {
	wp_cache_delete( 'get_calendar', 'calendar' );
}

function allowed_tags() {
	global $allowedtags;
	$allowed = '';
	foreach ( (array) $allowedtags as $tag => $attributes ) {
		$allowed .= '<'.$tag;
		if ( 0 < count($attributes) ) {
			foreach ( $attributes as $attribute => $limits ) {
				$allowed .= ' '.$attribute.'=""';
			}
		}
		$allowed .= '> ';
	}
	return htmlentities( $allowed );
}

function the_date_xml() {
	echo mysql2date( 'Y-m-d', get_post()->post_date, false );
}

function the_date( $d = '', $before = '', $after = '', $echo = true ) {
	global $currentday, $previousday;
	if ( is_new_day() ) {
		$the_date = $before . get_the_date( $d ) . $after;
		$previousday = $currentday;
		$the_date = apply_filters( 'the_date', $the_date, $d, $before, $after );
		if ( $echo )
			echo $the_date;
		else
			return $the_date;
	}
}

function get_the_date( $d = '', $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	if ( '' == $d ) {
		$the_date = mysql2date( get_option( 'date_format' ), $post->post_date );
	} else {
		$the_date = mysql2date( $d, $post->post_date );
	}
	return apply_filters( 'get_the_date', $the_date, $d, $post );
}

function the_modified_date( $d = '', $before = '', $after = '', $echo = true ) {
	$the_modified_date = $before . get_the_modified_date($d) . $after;

	$the_modified_date = apply_filters( 'the_modified_date', $the_modified_date, $d, $before, $after );

	if ( $echo )
		echo $the_modified_date;
	else
		return $the_modified_date;

}

function get_the_modified_date($d = '') {
	if ( '' == $d )
		$the_time = get_post_modified_time(get_option('date_format'), null, null, true);
	else
		$the_time = get_post_modified_time($d, null, null, true);

	return apply_filters( 'get_the_modified_date', $the_time, $d );
}

function the_time( $d = '' ) {
	echo apply_filters( 'the_time', get_the_time( $d ), $d );
}

function get_the_time( $d = '', $post = null ) {
	$post = get_post($post);
	if ( ! $post ) {
		return false;
	}
	if ( '' == $d )
		$the_time = get_post_time(get_option('time_format'), false, $post, true);
	else
		$the_time = get_post_time($d, false, $post, true);
	return apply_filters( 'get_the_time', $the_time, $d, $post );
}

function get_post_time( $d = 'U', $gmt = false, $post = null, $translate = false ) {
	$post = get_post($post);

	if ( ! $post ) {
		return false;
	}

	if ( $gmt )
		$time = $post->post_date_gmt;
	else
		$time = $post->post_date;

	$time = mysql2date($d, $time, $translate);

	return apply_filters( 'get_post_time', $time, $d, $gmt );
}

function the_modified_time($d = '') {
	echo apply_filters( 'the_modified_time', get_the_modified_time($d), $d );
}

function get_the_modified_time($d = '') {
	if ( '' == $d )
		$the_time = get_post_modified_time(get_option('time_format'), null, null, true);
	else
		$the_time = get_post_modified_time($d, null, null, true);

	return apply_filters( 'get_the_modified_time', $the_time, $d );
}

function get_post_modified_time( $d = 'U', $gmt = false, $post = null, $translate = false ) {
	$post = get_post($post);
	if ( ! $post ) {
		return false;
	}

	if ( $gmt )
		$time = $post->post_modified_gmt;
	else
		$time = $post->post_modified;
	$time = mysql2date($d, $time, $translate);
	return apply_filters( 'get_post_modified_time', $time, $d, $gmt );
}

function the_weekday() {
	global $wp_locale;
	$the_weekday = $wp_locale->get_weekday( mysql2date( 'w', get_post()->post_date, false ) );
	echo apply_filters( 'the_weekday', $the_weekday );
}

function the_weekday_date($before='',$after='') {
	global $wp_locale, $currentday, $previousweekday;
	$the_weekday_date = '';
	if ( $currentday != $previousweekday ) {
		$the_weekday_date .= $before;
		$the_weekday_date .= $wp_locale->get_weekday( mysql2date( 'w', get_post()->post_date, false ) );
		$the_weekday_date .= $after;
		$previousweekday = $currentday;
	}

	$the_weekday_date = apply_filters( 'the_weekday_date', $the_weekday_date, $before, $after );
	echo $the_weekday_date;
}

function wp_head() {
	do_action( 'wp_head' );
}

function wp_footer() {
	do_action( 'wp_footer' );
}

function noindex() {
	if ( '0' == get_option('blog_public') )
		wp_no_robots();
}

function wp_no_robots() {
	echo "<meta name='robots' content='noindex,follow' />\n";
}

function wp_site_icon() {
	if ( ! has_site_icon() && ! is_customize_preview() ) { return; }
	$meta_tags = array(
		sprintf( '<link rel="icon" href="%s" sizes="32x32" />', esc_url( get_site_icon_url( 32 ) ) ),
		sprintf( '<link rel="icon" href="%s" sizes="192x192" />', esc_url( get_site_icon_url( 192 ) ) ),
		sprintf( '<link rel="apple-touch-icon-precomposed" href="%s" />', esc_url( get_site_icon_url( 180 ) ) ),
		sprintf( '<meta name="msapplication-TileImage" content="%s" />', esc_url( get_site_icon_url( 270 ) ) ),
	);
	$meta_tags = array_filter( $meta_tags );
	foreach ( $meta_tags as $meta_tag ) { echo "$meta_tag\n"; }
}

function user_can_richedit() {
	global $wp_rich_edit, $is_gecko, $is_opera, $is_safari, $is_chrome, $is_IE, $is_edge;
	if ( !isset($wp_rich_edit) ) {
		$wp_rich_edit = false;
		if ( get_user_option( 'rich_editing' ) == 'true' || ! is_user_logged_in() ) {
			if ( $is_safari ) {
				$wp_rich_edit = ! wp_is_mobile() || ( preg_match( '!AppleWebKit/(\d+)!', $_SERVER['HTTP_USER_AGENT'], $match ) && intval( $match[1] ) >= 534 );
			} elseif ( $is_gecko || $is_chrome || $is_IE || $is_edge || ( $is_opera && !wp_is_mobile() ) ) {
				$wp_rich_edit = true;
			}
		}
	}

	return apply_filters( 'user_can_richedit', $wp_rich_edit );
}

function wp_default_editor() {
	$r = user_can_richedit() ? 'tinymce' : 'html'; // defaults
	if ( wp_get_current_user() ) { // look for cookie
		$ed = get_user_setting('editor', 'tinymce');
		$r = ( in_array($ed, array('tinymce', 'html', 'test') ) ) ? $ed : $r;
	}

	return apply_filters( 'wp_default_editor', $r );
}

function wp_editor( $content, $editor_id, $settings = array() ) {
	if ( ! class_exists( '_WP_Editors', false ) )
		require( ABSPATH . WPINC . '/class-wp-editor.php' );

	_WP_Editors::editor($content, $editor_id, $settings);
}

function get_search_query( $escaped = true ) {
	$query = apply_filters( 'get_search_query', get_query_var( 's' ) );
	if ( $escaped )
		$query = esc_attr( $query );
	return $query;
}

function the_search_query() {
	echo esc_attr( apply_filters( 'the_search_query', get_search_query( false ) ) );
}

function paginate_links( $args = '' ) {
	global $wp_query, $wp_rewrite;
	$pagenum_link = html_entity_decode( get_pagenum_link() );
	$url_parts    = explode( '?', $pagenum_link );
	$total   = isset( $wp_query->max_num_pages ) ? $wp_query->max_num_pages : 1;
	$current = get_query_var( 'paged' ) ? intval( get_query_var( 'paged' ) ) : 1;
	$pagenum_link = trailingslashit( $url_parts[0] ) . '%_%';
	$format  = $wp_rewrite->using_index_permalinks() && ! strpos( $pagenum_link, 'index.php' ) ? 'index.php/' : '';
	$format .= $wp_rewrite->using_permalinks() ? user_trailingslashit( $wp_rewrite->pagination_base . '/%#%', 'paged' ) : '?paged=%#%';
	$defaults = array(
		'base' => $pagenum_link,
		'format' => $format,
		'total' => $total,
		'current' => $current,
		'show_all' => false,
		'prev_next' => true,
		'prev_text' => '&laquo; Previous',
		'next_text' => 'Next &raquo;',
		'end_size' => 1,
		'mid_size' => 2,
		'type' => 'plain',
		'add_args' => array(),
		'add_fragment' => '',
		'before_page_number' => '',
		'after_page_number' => ''
	);
	$args = wp_parse_args( $args, $defaults );
	if ( ! is_array( $args['add_args'] ) ) {
		$args['add_args'] = array();
	}
	if ( isset( $url_parts[1] ) ) {
		$format = explode( '?', str_replace( '%_%', $args['format'], $args['base'] ) );
		$format_query = isset( $format[1] ) ? $format[1] : '';
		wp_parse_str( $format_query, $format_args );
		wp_parse_str( $url_parts[1], $url_query_args );
		foreach ( $format_args as $format_arg => $format_arg_value ) {
			unset( $url_query_args[ $format_arg ] );
		}
		$args['add_args'] = array_merge( $args['add_args'], urlencode_deep( $url_query_args ) );
	}
	$total = (int) $args['total'];
	if ( $total < 2 ) {
		return;
	}
	$current  = (int) $args['current'];
	$end_size = (int) $args['end_size'];
	if ( $end_size < 1 ) {
		$end_size = 1;
	}
	$mid_size = (int) $args['mid_size'];
	if ( $mid_size < 0 ) {
		$mid_size = 2;
	}
	$add_args = $args['add_args'];
	$r = '';
	$page_links = array();
	$dots = false;

	if ( $args['prev_next'] && $current && 1 < $current ) :
		$link = str_replace( '%_%', 2 == $current ? '' : $args['format'], $args['base'] );
		$link = str_replace( '%#%', $current - 1, $link );
		if ( $add_args )
			$link = add_query_arg( $add_args, $link );
		$link .= $args['add_fragment'];

		$page_links[] = '<a class="prev page-numbers" href="' . esc_url( apply_filters( 'paginate_links', $link ) ) . '">' . $args['prev_text'] . '</a>';
	endif;
	for ( $n = 1; $n <= $total; $n++ ) :
		if ( $n == $current ) :
			$page_links[] = "<span class='page-numbers current'>" . $args['before_page_number'] . number_format_i18n( $n ) . $args['after_page_number'] . "</span>";
			$dots = true;
		else :
			if ( $args['show_all'] || ( $n <= $end_size || ( $current && $n >= $current - $mid_size && $n <= $current + $mid_size ) || $n > $total - $end_size ) ) :
				$link = str_replace( '%_%', 1 == $n ? '' : $args['format'], $args['base'] );
				$link = str_replace( '%#%', $n, $link );
				if ( $add_args )
					$link = add_query_arg( $add_args, $link );
				$link .= $args['add_fragment'];

				/** This filter is documented in wp-includes/general-template.php */
				$page_links[] = "<a class='page-numbers' href='" . esc_url( apply_filters( 'paginate_links', $link ) ) . "'>" . $args['before_page_number'] . number_format_i18n( $n ) . $args['after_page_number'] . "</a>";
				$dots = true;
			elseif ( $dots && ! $args['show_all'] ) :
				$page_links[] = '<span class="page-numbers dots">&hellip;</span>';
				$dots = false;
			endif;
		endif;
	endfor;
	if ( $args['prev_next'] && $current && ( $current < $total || -1 == $total ) ) :
		$link = str_replace( '%_%', $args['format'], $args['base'] );
		$link = str_replace( '%#%', $current + 1, $link );
		if ( $add_args )
			$link = add_query_arg( $add_args, $link );
		$link .= $args['add_fragment'];
		$page_links[] = '<a class="next page-numbers" href="' . esc_url( apply_filters( 'paginate_links', $link ) ) . '">' . $args['next_text'] . '</a>';
	endif;
	switch ( $args['type'] ) {
		case 'array' :
			return $page_links;

		case 'list' :
			$r .= "<ul class='page-numbers'>\n\t<li>";
			$r .= join("</li>\n\t<li>", $page_links);
			$r .= "</li>\n</ul>\n";
			break;

		default :
			$r = join("\n", $page_links);
			break;
	}
	return $r;
}

function wp_admin_css_color( $key, $name, $url, $colors = array(), $icons = array() ) {
	global $_wp_admin_css_colors;
	if ( !isset($_wp_admin_css_colors) )
		$_wp_admin_css_colors = array();
	$_wp_admin_css_colors[$key] = (object) array(
		'name' => $name,
		'url' => $url,
		'colors' => $colors,
		'icon_colors' => $icons,
	);
}

function register_admin_color_schemes() {
	wp_admin_css_color( 'fresh', 'Default',
		false,
		array( '#222', '#333', '#0073aa', '#00a0d2' ),
		array( 'base' => '#82878c', 'focus' => '#00a0d2', 'current' => '#fff' )
	);
	if ( false !== strpos( $GLOBALS['wp_version'], '-src' ) )
		return;

	wp_admin_css_color( 'light', 'Light',
		admin_url( "css/colors/light/colors.css" ),
		array( '#e5e5e5', '#999', '#d64e07', '#04a4cc' ),
		array( 'base' => '#999', 'focus' => '#ccc', 'current' => '#ccc' )
	);

	wp_admin_css_color( 'blue', 'Blue',
		admin_url( "css/colors/blue/colors.css" ),
		array( '#096484', '#4796b3', '#52accc', '#74B6CE' ),
		array( 'base' => '#e5f8ff', 'focus' => '#fff', 'current' => '#fff' )
	);

	wp_admin_css_color( 'midnight', 'Midnight',
		admin_url( "css/colors/midnight/colors.css" ),
		array( '#25282b', '#363b3f', '#69a8bb', '#e14d43' ),
		array( 'base' => '#f1f2f3', 'focus' => '#fff', 'current' => '#fff' )
	);

	wp_admin_css_color( 'sunrise', 'Sunrise',
		admin_url( "css/colors/sunrise/colors.css" ),
		array( '#b43c38', '#cf4944', '#dd823b', '#ccaf0b' ),
		array( 'base' => '#f3f1f1', 'focus' => '#fff', 'current' => '#fff' )
	);

	wp_admin_css_color( 'ectoplasm', 'Ectoplasm',
		admin_url( "css/colors/ectoplasm/colors.css" ),
		array( '#413256', '#523f6d', '#a3b745', '#d46f15' ),
		array( 'base' => '#ece6f6', 'focus' => '#fff', 'current' => '#fff' )
	);

	wp_admin_css_color( 'ocean', 'Ocean',
		admin_url( "css/colors/ocean/colors.css" ),
		array( '#627c83', '#738e96', '#9ebaa0', '#aa9d88' ),
		array( 'base' => '#f2fcff', 'focus' => '#fff', 'current' => '#fff' )
	);

	wp_admin_css_color( 'coffee', 'Coffee',
		admin_url( "css/colors/coffee/colors.css" ),
		array( '#46403c', '#59524c', '#c7a589', '#9ea476' ),
		array( 'base' => '#f3f2f1', 'focus' => '#fff', 'current' => '#fff' )
	);

}

function wp_admin_css_uri( $file = 'wp-admin' ) {
	if ( defined('WP_INSTALLING') ) {
		$_file = "./$file.css";
	} else {
		$_file = admin_url("$file.css");
	}
	$_file = add_query_arg( 'version', get_bloginfo( 'version' ),  $_file );
	return apply_filters( 'wp_admin_css_uri', $_file, $file );
}

function wp_admin_css( $file = 'wp-admin', $force_echo = false ) {
	$handle = 0 === strpos( $file, 'css/' ) ? substr( $file, 4 ) : $file;
	if ( wp_styles()->query( $handle ) ) {
		if ( $force_echo || did_action( 'wp_print_styles' ) )
			wp_print_styles( $handle );
		else
			wp_enqueue_style( $handle );
		return;
	}
	echo apply_filters( 'wp_admin_css', "<link rel='stylesheet' href='" . esc_url( wp_admin_css_uri( $file ) ) . "' type='text/css' />\n", $file );
}

function add_thickbox() {
	wp_enqueue_script( 'thickbox' );
	wp_enqueue_style( 'thickbox' );
}

function checked( $checked, $current = true, $echo = true ) {
	return __checked_selected_helper( $checked, $current, $echo, 'checked' );
}

function selected( $selected, $current = true, $echo = true ) {
	return __checked_selected_helper( $selected, $current, $echo, 'selected' );
}

function disabled( $disabled, $current = true, $echo = true ) {
	return __checked_selected_helper( $disabled, $current, $echo, 'disabled' );
}

function __checked_selected_helper( $helper, $current, $echo, $type ) {
	if ( (string) $helper === (string) $current )
		$result = " $type='$type'";
	else
		$result = '';
	if ( $echo )
		echo $result;
	return $result;
}

function wp_heartbeat_settings( $settings ) {
	if ( ! is_admin() )
		$settings['ajaxurl'] = admin_url( 'admin-ajax.php', 'relative' );
	if ( is_user_logged_in() )
		$settings['nonce'] = wp_create_nonce( 'heartbeat-nonce' );
	return $settings;
}
