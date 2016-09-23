<?php

global $wpdb, $wp_queries, $charset_collate;

$charset_collate = $wpdb->get_charset_collate();

function populate_options() {
	global $wpdb, $wp_db_version, $wp_current_db_version;
	$guessurl = wp_guess_url();
	do_action( 'populate_options' );
	if ( ini_get('safe_mode') ) {
		$uploads_use_yearmonth_folders = 0;
	} else {
		$uploads_use_yearmonth_folders = 1;
	}
	$stylesheet = $template = WP_DEFAULT_THEME;
	$theme = wp_get_theme( WP_DEFAULT_THEME );
	if ( ! $theme->exists() ) {
		$theme = WP_Theme::get_core_default_theme();
	}
	if ( $theme ) {
		$stylesheet = $theme->get_stylesheet();
		$template   = $theme->get_template();
	}
	$timezone_string = '';
	$gmt_offset = 0;
	$offset_or_tz = '0';
	if ( is_numeric( $offset_or_tz ) )
		$gmt_offset = $offset_or_tz;
	elseif ( $offset_or_tz && in_array( $offset_or_tz, timezone_identifiers_list() ) )
			$timezone_string = $offset_or_tz;

	$options = array(
	'siteurl' => $guessurl,
	'home' => $guessurl,
	'blogname' => 'My Site',
	'blogdescription' => 'Just another site',
	'users_can_register' => 0,
	'admin_email' => 'you@example.com',
	'start_of_week' => '1',
	'use_balanceTags' => 0,
	'require_name_email' => 1,
	'comments_notify' => 1,
	'posts_per_rss' => 10,
	'rss_use_excerpt' => 0,
	'mailserver_url' => 'mail.example.com',
	'mailserver_login' => 'login@example.com',
	'mailserver_pass' => 'password',
	'mailserver_port' => 110,
	'default_category' => 1,
	'default_comment_status' => 'open',
	'default_ping_status' => 'open',
	'default_pingback_flag' => 1,
	'posts_per_page' => 10,
	'date_format' => 'F j, Y',
	'time_format' => 'g:i a',
	'links_updated_date_format' => 'F j, Y g:i a',
	'comment_moderation' => 0,
	'moderation_notify' => 1,
	'permalink_structure' => '',
	'rewrite_rules' => '',
	'hack_file' => 0,
	'blog_charset' => 'UTF-8',
	'moderation_keys' => '',
	'active_plugins' => array(),
	'category_base' => '',
	'comment_max_links' => 2,
	'gmt_offset' => $gmt_offset,
	'default_email_category' => 1,
	'recently_edited' => '',
	'template' => $template,
	'stylesheet' => $stylesheet,
	'comment_whitelist' => 1,
	'blacklist_keys' => '',
	'comment_registration' => 0,
	'html_type' => 'text/html',
	'use_trackback' => 0,
	'default_role' => 'subscriber',
	'db_version' => $wp_db_version,
	'uploads_use_yearmonth_folders' => $uploads_use_yearmonth_folders,
	'upload_path' => '',
	'blog_public' => '1',
	'default_link_category' => 2,
	'show_on_front' => 'posts',
	'tag_base' => '',
	'show_avatars' => '1',
	'avatar_rating' => 'G',
	'upload_url_path' => '',
	'thumbnail_size_w' => 150,
	'thumbnail_size_h' => 150,
	'thumbnail_crop' => 1,
	'medium_size_w' => 300,
	'medium_size_h' => 300,
	'avatar_default' => 'mystery',
	'large_size_w' => 1024,
	'large_size_h' => 1024,
	'image_default_link_type' => 'none',
	'image_default_size' => '',
	'image_default_align' => '',
	'close_comments_for_old_posts' => 0,
	'close_comments_days_old' => 14,
	'thread_comments' => 1,
	'thread_comments_depth' => 5,
	'page_comments' => 0,
	'comments_per_page' => 50,
	'default_comments_page' => 'newest',
	'comment_order' => 'asc',
	'sticky_posts' => array(),
	'widget_categories' => array(),
	'widget_text' => array(),
	'widget_rss' => array(),
	'uninstall_plugins' => array(),
	'timezone_string' => $timezone_string,
	'page_for_posts' => 0,
	'page_on_front' => 0,
	'default_post_format' => 0,
	'link_manager_enabled' => 0,
	'finished_splitting_shared_terms' => 1,
	'site_icon' => 0,
	'medium_large_size_w' => 768,
	'medium_large_size_h' => 0,
	);
	$options['initial_db_version'] = ! empty( $wp_current_db_version ) && $wp_current_db_version < $wp_db_version
			? $wp_current_db_version : $wp_db_version;
	$fat_options = array( 'moderation_keys', 'recently_edited', 'blacklist_keys', 'uninstall_plugins' );
	$keys = "'" . implode( "', '", array_keys( $options ) ) . "'";
	$existing_options = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name in ( $keys )" );
	$insert = '';
	foreach ( $options as $option => $value ) {
		if ( in_array($option, $existing_options) )
			continue;
		if ( in_array($option, $fat_options) )
			$autoload = 'no';
		else
			$autoload = 'yes';

		if ( is_array($value) )
			$value = serialize($value);
		if ( !empty($insert) )
			$insert .= ', ';
		$insert .= $wpdb->prepare( "(%s, %s, %s)", $option, $value, $autoload );
	}
	if ( !empty($insert) )
		$wpdb->query("INSERT INTO $wpdb->options (option_name, option_value, autoload) VALUES " . $insert);
	if ( !__get_option('home') ) update_option('home', $guessurl);
	$unusedoptions = array(
		'blodotgsping_url', 'bodyterminator', 'emailtestonly', 'phoneemail_separator', 'smilies_directory',
		'subjectprefix', 'use_bbcode', 'use_blodotgsping', 'use_phoneemail', 'use_quicktags', 'use_weblogsping',
		'weblogs_cache_file', 'use_preview', 'use_htmltrans', 'smilies_directory', 'fileupload_allowedusers',
		'use_phoneemail', 'default_post_status', 'default_post_category', 'archive_mode', 'time_difference',
		'links_minadminlevel', 'links_use_adminlevels', 'links_rating_type', 'links_rating_char',
		'links_rating_ignore_zero', 'links_rating_single_image', 'links_rating_image0', 'links_rating_image1',
		'links_rating_image2', 'links_rating_image3', 'links_rating_image4', 'links_rating_image5',
		'links_rating_image6', 'links_rating_image7', 'links_rating_image8', 'links_rating_image9',
		'links_recently_updated_time', 'links_recently_updated_prepend', 'links_recently_updated_append',
		'weblogs_cacheminutes', 'comment_allowed_tags', 'search_engine_friendly_urls', 'default_geourl_lat',
		'default_geourl_lon', 'use_default_geourl', 'weblogs_xml_url', 'new_users_can_blog', '_wpnonce',
		'_wp_http_referer', 'Update', 'action', 'rich_editing', 'autosave_interval', 'deactivated_plugins',
		'can_compress_scripts', 'page_uris', 'update_core', 'update_plugins', 'update_themes', 'doing_cron',
		'random_seed', 'rss_excerpt_length', 'secret', 'use_linksupdate', 'default_comment_status_page',
		'wporg_popular_tags', 'what_to_show', 'rss_language', 'language', 'enable_xmlrpc', 'enable_app',
		'embed_autourls', 'default_post_edit_rows', 'gzipcompression', 'advanced_edit'
	);
	foreach ( $unusedoptions as $option )
		delete_option($option);

	$wpdb->query("DELETE FROM $wpdb->options WHERE option_name REGEXP '^rss_[0-9a-f]{32}(_ts)?$'");

	$time = time();
	$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
		WHERE a.option_name LIKE %s
		AND a.option_name NOT LIKE %s
		AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
		AND b.option_value < %d";
	$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_transient_timeout_' ) . '%', $time ) );

	if ( is_main_site() && is_main_network() ) {
		$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
			WHERE a.option_name LIKE %s
			AND a.option_name NOT LIKE %s
			AND b.option_name = CONCAT( '_site_transient_timeout_', SUBSTRING( a.option_name, 17 ) )
			AND b.option_value < %d";
		$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_site_transient_' ) . '%', $wpdb->esc_like( '_site_transient_timeout_' ) . '%', $time ) );
	}
}

function populate_roles() {
	'Administrator';
	'Editor';
	'Author';
	'Contributor';
	'Subscriber';

	add_role('administrator', 'Administrator');
	add_role('editor', 'Editor');
	add_role('author', 'Author');
	add_role('contributor', 'Contributor');
	add_role('subscriber', 'Subscriber');

	$role = get_role('administrator');
	$role->add_cap('switch_themes');
	$role->add_cap('edit_themes');
	$role->add_cap('activate_plugins');
	$role->add_cap('edit_plugins');
	$role->add_cap('edit_users');
	$role->add_cap('edit_files');
	$role->add_cap('manage_options');
	$role->add_cap('moderate_comments');
	$role->add_cap('manage_categories');
	$role->add_cap('manage_links');
	$role->add_cap('upload_files');
	$role->add_cap('import');
	$role->add_cap('unfiltered_html');
	$role->add_cap('edit_posts');
	$role->add_cap('edit_others_posts');
	$role->add_cap('edit_published_posts');
	$role->add_cap('publish_posts');
	$role->add_cap('edit_pages');
	$role->add_cap('read');
	$role->add_cap('level_10');
	$role->add_cap('level_9');
	$role->add_cap('level_8');
	$role->add_cap('level_7');
	$role->add_cap('level_6');
	$role->add_cap('level_5');
	$role->add_cap('level_4');
	$role->add_cap('level_3');
	$role->add_cap('level_2');
	$role->add_cap('level_1');
	$role->add_cap('level_0');

	$role = get_role('editor');
	$role->add_cap('moderate_comments');
	$role->add_cap('manage_categories');
	$role->add_cap('manage_links');
	$role->add_cap('upload_files');
	$role->add_cap('unfiltered_html');
	$role->add_cap('edit_posts');
	$role->add_cap('edit_others_posts');
	$role->add_cap('edit_published_posts');
	$role->add_cap('publish_posts');
	$role->add_cap('edit_pages');
	$role->add_cap('read');
	$role->add_cap('level_7');
	$role->add_cap('level_6');
	$role->add_cap('level_5');
	$role->add_cap('level_4');
	$role->add_cap('level_3');
	$role->add_cap('level_2');
	$role->add_cap('level_1');
	$role->add_cap('level_0');

	$role = get_role('author');
	$role->add_cap('upload_files');
	$role->add_cap('edit_posts');
	$role->add_cap('edit_published_posts');
	$role->add_cap('publish_posts');
	$role->add_cap('read');
	$role->add_cap('level_2');
	$role->add_cap('level_1');
	$role->add_cap('level_0');

	$role = get_role('contributor');
	$role->add_cap('edit_posts');
	$role->add_cap('read');
	$role->add_cap('level_1');
	$role->add_cap('level_0');

	$role = get_role('subscriber');
	$role->add_cap('read');
	$role->add_cap('level_0');

	$roles = array('administrator', 'editor');
	foreach ($roles as $role) {
		$role = get_role($role);
		if ( empty($role) )
			continue;

		$role->add_cap('edit_others_pages');
		$role->add_cap('edit_published_pages');
		$role->add_cap('publish_pages');
		$role->add_cap('delete_pages');
		$role->add_cap('delete_others_pages');
		$role->add_cap('delete_published_pages');
		$role->add_cap('delete_posts');
		$role->add_cap('delete_others_posts');
		$role->add_cap('delete_published_posts');
		$role->add_cap('delete_private_posts');
		$role->add_cap('edit_private_posts');
		$role->add_cap('read_private_posts');
		$role->add_cap('delete_private_pages');
		$role->add_cap('edit_private_pages');
		$role->add_cap('read_private_pages');
	}

	$role = get_role('administrator');
	if ( ! empty($role) ) {
		$role->add_cap('delete_users');
		$role->add_cap('create_users');
		$role->add_cap( 'unfiltered_upload' );
		$role->add_cap( 'edit_dashboard' );
		$role->add_cap( 'update_plugins' );
		$role->add_cap( 'delete_plugins' );
		$role->add_cap( 'install_plugins' );
		$role->add_cap( 'update_themes' );
		$role->add_cap( 'install_themes' );
		$role->add_cap( 'update_core' );
		$role->add_cap( 'list_users' );
		$role->add_cap( 'remove_users' );
		$role->add_cap( 'promote_users' );
		$role->add_cap( 'edit_theme_options' );
		$role->add_cap( 'delete_themes' );
		$role->add_cap( 'export' );
	}

	$role = get_role('author');
	if ( ! empty($role) ) {
		$role->add_cap('delete_posts');
		$role->add_cap('delete_published_posts');
	}

	$role = get_role('contributor');
	if ( ! empty($role) ) {
		$role->add_cap('delete_posts');
	}
}
