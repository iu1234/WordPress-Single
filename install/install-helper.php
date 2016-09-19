<?php
/**
 * Plugins may load this file to gain access to special helper functions for
 * plugin installation. This file is not included by WordPress and it is
 * recommended, to prevent fatal errors, that this file is included using
 * require_once().
 *
 * These functions are not optimized for speed, but they should only be used
 * once in a while, so speed shouldn't be a concern. If it is and you are
 * needing to use these functions a lot, you might experience time outs. If you
 * do, then it is advised to just write the SQL code yourself.
 *
 *     check_column( 'wp_links', 'link_description', 'mediumtext' );
 *     if ( check_column( $wpdb->comments, 'comment_author', 'tinytext' ) ) {
 *         echo "ok\n";
 *     }
 *
 *     $error_count = 0;
 *     $tablename = $wpdb->links;
 *     // Check the column.
 *     if ( ! check_column($wpdb->links, 'link_description', 'varchar( 255 )' ) ) {
 *         $ddl = "ALTER TABLE $wpdb->links MODIFY COLUMN link_description varchar(255) NOT NULL DEFAULT '' ";
 *         $q = $wpdb->query( $ddl );
 *     }
 *
 *     if ( check_column( $wpdb->links, 'link_description', 'varchar( 255 )' ) ) {
 *         $res .= $tablename . ' - ok <br />';
 *     } else {
 *         $res .= 'There was a problem with ' . $tablename . '<br />';
 *         ++$error_count;
 *     }
 *
 * @package WordPress
 * @subpackage Plugin
 */

require_once(dirname(__DIR__).'/wp-load.php');

if ( ! function_exists('maybe_create_table') ) :
function maybe_create_table($table_name, $create_ddl) {
	global $wpdb;
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $table_name) {
			return true;
		}
	}
	$wpdb->query($create_ddl);
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $table_name) {
			return true;
		}
	}
	return false;
}
endif;

if ( ! function_exists('maybe_add_column') ) :
function maybe_add_column($table_name, $column_name, $create_ddl) {
	global $wpdb;
	foreach ($wpdb->get_col("DESC $table_name",0) as $column ) {

		if ($column == $column_name) {
			return true;
		}
	}

	// Didn't find it, so try to create it.
	$wpdb->query($create_ddl);

	// We cannot directly tell that whether this succeeded!
	foreach ($wpdb->get_col("DESC $table_name",0) as $column ) {
		if ($column == $column_name) {
			return true;
		}
	}
	return false;
}
endif;

function maybe_drop_column($table_name, $column_name, $drop_ddl) {
	global $wpdb;
	foreach ($wpdb->get_col("DESC $table_name",0) as $column ) {
		if ($column == $column_name) {

			// Found it, so try to drop it.
			$wpdb->query($drop_ddl);

			// We cannot directly tell that whether this succeeded!
			foreach ($wpdb->get_col("DESC $table_name",0) as $column ) {
				if ($column == $column_name) {
					return false;
				}
			}
		}
	}
	// Else didn't find it.
	return true;
}

function check_column($table_name, $col_name, $col_type, $is_null = null, $key = null, $default = null, $extra = null) {
	global $wpdb;
	$diffs = 0;
	$results = $wpdb->get_results("DESC $table_name");
	foreach ($results as $row ) {
		if ($row->Field == $col_name) {
			if (($col_type != null) && ($row->Type != $col_type)) {
				++$diffs;
			}
			if (($is_null != null) && ($row->Null != $is_null)) {
				++$diffs;
			}
			if (($key != null) && ($row->Key  != $key)) {
				++$diffs;
			}
			if (($default != null) && ($row->Default != $default)) {
				++$diffs;
			}
			if (($extra != null) && ($row->Extra != $extra)) {
				++$diffs;
			}
			if ($diffs > 0) {
				return false;
			}
			return true;
		}
	}
	return false;
}

function is_blog_installed() {
	global $wpdb;
	$suppress = $wpdb->suppress_errors();
	$installed = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'siteurl'" );
	$wpdb->suppress_errors( $suppress );
	$installed = !empty( $installed );
	if ( $installed )
		return true;
	if ( defined( 'WP_REPAIRING' ) )
		return true;
	$suppress = $wpdb->suppress_errors();
	$wp_tables = $wpdb->tables();
	foreach ( $wp_tables as $table ) {
		if ( defined( 'CUSTOM_USER_TABLE' ) && CUSTOM_USER_TABLE == $table )
			continue;
		if ( defined( 'CUSTOM_USER_META_TABLE' ) && CUSTOM_USER_META_TABLE == $table )
			continue;
		if ( ! $wpdb->get_results( "DESCRIBE $table;" ) )
			continue;
		$wpdb->error = sprintf( 'One or more database tables are unavailable. The database may need to be <a href="%s">repaired</a>.', 'maint/repair.php?referrer=is_blog_installed' );
		dead_db();
	}
	$wpdb->suppress_errors( $suppress );
	return false;
}

function wp_install_defaults( $user_id ) {
	global $wpdb, $wp_rewrite, $table_prefix;

	$cat_name = 'Uncategorized';
	$cat_slug = sanitize_title('Uncategorized');

	if ( global_terms_enabled() ) {
		$cat_id = $wpdb->get_var( $wpdb->prepare( "SELECT cat_ID FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
		if ( $cat_id == null ) {
			$wpdb->insert( $wpdb->sitecategories, array('cat_ID' => 0, 'cat_name' => $cat_name, 'category_nicename' => $cat_slug, 'last_updated' => current_time('mysql', true)) );
			$cat_id = $wpdb->insert_id;
		}
		update_option('default_category', $cat_id);
	} else {
		$cat_id = 1;
	}

	$wpdb->insert( $wpdb->terms, array('term_id' => $cat_id, 'name' => $cat_name, 'slug' => $cat_slug, 'term_group' => 0) );
	$wpdb->insert( $wpdb->term_taxonomy, array('term_id' => $cat_id, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1));
	$cat_tt_id = $wpdb->insert_id;

	$now = current_time( 'mysql' );
	$now_gmt = current_time( 'mysql', 1 );
	$first_post_guid = get_option( 'home' ) . '/?p=1';

	$first_post = 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!';

	$wpdb->insert( $wpdb->posts, array(
		'post_author' => $user_id,
		'post_date' => $now,
		'post_date_gmt' => $now_gmt,
		'post_content' => $first_post,
		'post_excerpt' => '',
		'post_title' => 'Hello world!',
		'post_name' => sanitize_title( 'hello-world' ),
		'post_modified' => $now,
		'post_modified_gmt' => $now_gmt,
		'guid' => $first_post_guid,
		'comment_count' => 1,
		'to_ping' => '',
		'pinged' => '',
		'post_content_filtered' => ''
	));
	$wpdb->insert( $wpdb->term_relationships, array('term_taxonomy_id' => $cat_tt_id, 'object_id' => 1) );

	update_option( 'widget_search', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
	update_option( 'widget_recent-posts', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
	update_option( 'widget_recent-comments', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
	update_option( 'widget_archives', array ( 2 => array ( 'title' => '', 'count' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
	update_option( 'widget_categories', array ( 2 => array ( 'title' => '', 'count' => 0, 'hierarchical' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
	update_option( 'widget_meta', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
	update_option( 'sidebars_widgets', array ( 'wp_inactive_widgets' => array (), 'sidebar-1' => array ( 0 => 'search-2', 1 => 'recent-posts-2', 2 => 'recent-comments-2', 3 => 'archives-2', 4 => 'categories-2', 5 => 'meta-2', ), 'array_version' => 3 ) );
}

function wp_install_maybe_enable_pretty_permalinks() {
	global $wp_rewrite;
	if ( get_option( 'permalink_structure' ) ) {
		return true;
	}
	$permalink_structures = array(
		'/%year%/%monthnum%/%day%/%postname%/',
		'/index.php/%year%/%monthnum%/%day%/%postname%/'
	);
	foreach ( (array) $permalink_structures as $permalink_structure ) {
		$wp_rewrite->set_permalink_structure( $permalink_structure );
		$wp_rewrite->flush_rules( true );
		$test_url = get_permalink( 1 );
		if ( ! $test_url ) {
			$test_url = home_url( '/wordpress-check-for-rewrites/' );
		}

		$response          = wp_remote_get( $test_url, array( 'timeout' => 5 ) );
		$x_pingback_header = wp_remote_retrieve_header( $response, 'x-pingback' );
		$pretty_permalinks = $x_pingback_header && $x_pingback_header === get_bloginfo( 'pingback_url' );

		if ( $pretty_permalinks ) {
			return true;
		}
	}

	$wp_rewrite->set_permalink_structure( '' );
	$wp_rewrite->flush_rules( true );

	return false;
}

function wp_install( $blog_title, $user_name, $user_email, $user_password = '', $language = '' ) {
	make_db_current_silent();
	populate_options();
	populate_roles();
	update_option('blogname', $blog_title);
	update_option('admin_email', $user_email);
	update_option('blog_public', $public);
	$guessurl = wp_guess_url();
	update_option('siteurl', $guessurl);
	$user_id = username_exists($user_name);
	$user_password = trim($user_password);
	$email_password = false;
	if ( !$user_id && empty($user_password) ) {
		$user_password = wp_generate_password( 12, false );
		$message = '<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.';
		$user_id = wp_create_user($user_name, $user_password, $user_email);
		update_user_option($user_id, 'default_password_nag', true, true);
		$email_password = true;
	} elseif ( ! $user_id ) {
		$message = '<em>Your chosen password.</em>';
		$user_id = wp_create_user($user_name, $user_password, $user_email);
	} else {
		$message = 'User already exists. Password inherited.';
	}
	$user = new WP_User($user_id);
	$user->set_role('administrator');
	wp_install_defaults($user_id);
	wp_install_maybe_enable_pretty_permalinks();
	flush_rewrite_rules();
	do_action( 'wp_install', $user );
	return array('url' => $guessurl, 'user_id' => $user_id, 'password' => $user_password, 'password_message' => $message);
}
