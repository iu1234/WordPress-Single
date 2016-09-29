<?php
/**
 * WordPress Plugin Administration API
 *
 * @package WordPress
 * @subpackage Administration
 */

function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {

	$default_headers = array(
		'Name' => 'Plugin Name',
		'PluginURI' => 'Plugin URI',
		'Version' => 'Version',
		'Description' => 'Description',
		'Author' => 'Author',
		'AuthorURI' => 'Author URI',
		'TextDomain' => 'Text Domain',
		'DomainPath' => 'Domain Path',
		'Network' => 'Network',
		'_sitewide' => 'Site Wide Only',
	);

	$plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );

	if ( ! $plugin_data['Network'] && $plugin_data['_sitewide'] ) {
		_deprecated_argument( __FUNCTION__, '3.0', sprintf( 'The %1$s plugin header is deprecated. Use %2$s instead.', '<code>Site Wide Only: true</code>', '<code>Network: true</code>' ) );
		$plugin_data['Network'] = $plugin_data['_sitewide'];
	}
	$plugin_data['Network'] = ( 'true' == strtolower( $plugin_data['Network'] ) );
	unset( $plugin_data['_sitewide'] );

	if ( $markup || $translate ) {
		$plugin_data = _get_plugin_data_markup_translate( $plugin_file, $plugin_data, $markup, $translate );
	} else {
		$plugin_data['Title']      = $plugin_data['Name'];
		$plugin_data['AuthorName'] = $plugin_data['Author'];
	}

	return $plugin_data;
}

function _get_plugin_data_markup_translate( $plugin_file, $plugin_data, $markup = true, $translate = true ) {

	// Sanitize the plugin filename to a WP_PLUGIN_DIR relative path
	$plugin_file = plugin_basename( $plugin_file );

	// Translate fields
	if ( $translate ) {
		if ( $textdomain = $plugin_data['TextDomain'] ) {
			if ( ! is_textdomain_loaded( $textdomain ) ) {
				if ( $plugin_data['DomainPath'] ) {
					load_plugin_textdomain( $textdomain, false, dirname( $plugin_file ) . $plugin_data['DomainPath'] );
				} else {
					load_plugin_textdomain( $textdomain, false, dirname( $plugin_file ) );
				}
			}
		} elseif ( 'hello.php' == basename( $plugin_file ) ) {
			$textdomain = 'default';
		}
		if ( $textdomain ) {
			foreach ( array( 'Name', 'PluginURI', 'Description', 'Author', 'AuthorURI', 'Version' ) as $field )
				$plugin_data[ $field ] = translate( $plugin_data[ $field ], $textdomain );
		}
	}

	// Sanitize fields
	$allowed_tags = $allowed_tags_in_links = array(
		'abbr'    => array( 'title' => true ),
		'acronym' => array( 'title' => true ),
		'code'    => true,
		'em'      => true,
		'strong'  => true,
	);
	$allowed_tags['a'] = array( 'href' => true, 'title' => true );

	$plugin_data['Name']        = wp_kses( $plugin_data['Name'],        $allowed_tags_in_links );
	$plugin_data['Author']      = wp_kses( $plugin_data['Author'],      $allowed_tags );

	$plugin_data['Description'] = wp_kses( $plugin_data['Description'], $allowed_tags );
	$plugin_data['Version']     = wp_kses( $plugin_data['Version'],     $allowed_tags );

	$plugin_data['PluginURI']   = esc_url( $plugin_data['PluginURI'] );
	$plugin_data['AuthorURI']   = esc_url( $plugin_data['AuthorURI'] );

	$plugin_data['Title']      = $plugin_data['Name'];
	$plugin_data['AuthorName'] = $plugin_data['Author'];

	// Apply markup
	if ( $markup ) {
		if ( $plugin_data['PluginURI'] && $plugin_data['Name'] )
			$plugin_data['Title'] = '<a href="' . $plugin_data['PluginURI'] . '">' . $plugin_data['Name'] . '</a>';

		if ( $plugin_data['AuthorURI'] && $plugin_data['Author'] )
			$plugin_data['Author'] = '<a href="' . $plugin_data['AuthorURI'] . '">' . $plugin_data['Author'] . '</a>';

		$plugin_data['Description'] = wptexturize( $plugin_data['Description'] );

		if ( $plugin_data['Author'] )
			$plugin_data['Description'] .= ' <cite>' . sprintf( 'By %s.', $plugin_data['Author'] ) . '</cite>';
	}

	return $plugin_data;
}

function get_plugin_files($plugin) {
	$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
	$dir = dirname($plugin_file);
	$plugin_files = array($plugin);
	if ( is_dir($dir) && $dir != WP_PLUGIN_DIR ) {
		$plugins_dir = @ opendir( $dir );
		if ( $plugins_dir ) {
			while (($file = readdir( $plugins_dir ) ) !== false ) {
				if ( substr($file, 0, 1) == '.' )
					continue;
				if ( is_dir( $dir . '/' . $file ) ) {
					$plugins_subdir = @ opendir( $dir . '/' . $file );
					if ( $plugins_subdir ) {
						while (($subfile = readdir( $plugins_subdir ) ) !== false ) {
							if ( substr($subfile, 0, 1) == '.' )
								continue;
							$plugin_files[] = plugin_basename("$dir/$file/$subfile");
						}
						@closedir( $plugins_subdir );
					}
				} else {
					if ( plugin_basename("$dir/$file") != $plugin )
						$plugin_files[] = plugin_basename("$dir/$file");
				}
			}
			@closedir( $plugins_dir );
		}
	}

	return $plugin_files;
}

function get_plugins($plugin_folder = '') {

	if ( ! $cache_plugins = wp_cache_get('plugins', 'plugins') )
		$cache_plugins = array();

	if ( isset($cache_plugins[ $plugin_folder ]) )
		return $cache_plugins[ $plugin_folder ];

	$wp_plugins = array ();
	$plugin_root = WP_PLUGIN_DIR;
	if ( !empty($plugin_folder) )
		$plugin_root .= $plugin_folder;

	// Files in wp-content/plugins directory
	$plugins_dir = @ opendir( $plugin_root);
	$plugin_files = array();
	if ( $plugins_dir ) {
		while (($file = readdir( $plugins_dir ) ) !== false ) {
			if ( substr($file, 0, 1) == '.' )
				continue;
			if ( is_dir( $plugin_root.'/'.$file ) ) {
				$plugins_subdir = @ opendir( $plugin_root.'/'.$file );
				if ( $plugins_subdir ) {
					while (($subfile = readdir( $plugins_subdir ) ) !== false ) {
						if ( substr($subfile, 0, 1) == '.' )
							continue;
						if ( substr($subfile, -4) == '.php' )
							$plugin_files[] = "$file/$subfile";
					}
					closedir( $plugins_subdir );
				}
			} else {
				if ( substr($file, -4) == '.php' )
					$plugin_files[] = $file;
			}
		}
		closedir( $plugins_dir );
	}

	if ( empty($plugin_files) )
		return $wp_plugins;

	foreach ( $plugin_files as $plugin_file ) {
		if ( !is_readable( "$plugin_root/$plugin_file" ) )
			continue;

		$plugin_data = get_plugin_data( "$plugin_root/$plugin_file", false, false ); //Do not apply markup/translate as it'll be cached.

		if ( empty ( $plugin_data['Name'] ) )
			continue;

		$wp_plugins[plugin_basename( $plugin_file )] = $plugin_data;
	}

	uasort( $wp_plugins, '_sort_uname_callback' );

	$cache_plugins[ $plugin_folder ] = $wp_plugins;
	wp_cache_set('plugins', $cache_plugins, 'plugins');

	return $wp_plugins;
}

function get_mu_plugins() {
	$wp_plugins = array();
	// Files in wp-content/mu-plugins directory
	$plugin_files = array();

	if ( ! is_dir( WPMU_PLUGIN_DIR ) )
		return $wp_plugins;
	if ( $plugins_dir = @ opendir( WPMU_PLUGIN_DIR ) ) {
		while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
			if ( substr( $file, -4 ) == '.php' )
				$plugin_files[] = $file;
		}
	} else {
		return $wp_plugins;
	}

	@closedir( $plugins_dir );

	if ( empty($plugin_files) )
		return $wp_plugins;

	foreach ( $plugin_files as $plugin_file ) {
		if ( !is_readable( WPMU_PLUGIN_DIR . "/$plugin_file" ) )
			continue;

		$plugin_data = get_plugin_data( WPMU_PLUGIN_DIR . "/$plugin_file", false, false ); //Do not apply markup/translate as it'll be cached.

		if ( empty ( $plugin_data['Name'] ) )
			$plugin_data['Name'] = $plugin_file;

		$wp_plugins[ $plugin_file ] = $plugin_data;
	}

	if ( isset( $wp_plugins['index.php'] ) && filesize( WPMU_PLUGIN_DIR . '/index.php') <= 30 ) // silence is golden
		unset( $wp_plugins['index.php'] );

	uasort( $wp_plugins, '_sort_uname_callback' );

	return $wp_plugins;
}

function _sort_uname_callback( $a, $b ) {
	return strnatcasecmp( $a['Name'], $b['Name'] );
}

function get_dropins() {
	$dropins = array();
	$plugin_files = array();
	$_dropins = _get_dropins();
	if ( $plugins_dir = @ opendir( WP_CONTENT_DIR ) ) {
		while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
			if ( isset( $_dropins[ $file ] ) )
				$plugin_files[] = $file;
		}
	} else {
		return $dropins;
	}
	@closedir( $plugins_dir );
	if ( empty($plugin_files) ) return $dropins;
	foreach ( $plugin_files as $plugin_file ) {
		if ( !is_readable( WP_CONTENT_DIR . "/$plugin_file" ) ) continue;
		$plugin_data = get_plugin_data( WP_CONTENT_DIR . "/$plugin_file", false, false );
		if ( empty( $plugin_data['Name'] ) ) $plugin_data['Name'] = $plugin_file;
		$dropins[ $plugin_file ] = $plugin_data;
	}
	uksort( $dropins, 'strnatcasecmp' );
	return $dropins;
}

function _get_dropins() {
	$dropins = array(
		'advanced-cache.php' => array( 'Advanced caching plugin.', 'WP_CACHE' ),
		'db.php'             => array( 'Custom database class.', true ),
		'db-error.php'       => array( 'Custom database error message.', true ),
		'install.php'        => array( 'Custom install script.', true ),
		'maintenance.php'    => array( 'Custom maintenance message.', true ),
		'object-cache.php'   => array( 'External object cache.', true ),
	);
	return $dropins;
}

function is_plugin_active( $plugin ) {
	return in_array( $plugin, (array) get_option( 'active_plugins', array() ) ) || is_plugin_active_for_network( $plugin );
}

function activate_plugin( $plugin, $redirect = '', $network_wide = false, $silent = false ) {
	$plugin = plugin_basename( trim( $plugin ) );

	if ( is_multisite() && ( $network_wide || is_network_only_plugin($plugin) ) ) {
		$network_wide = true;
		$current = get_site_option( 'active_sitewide_plugins', array() );
		$_GET['networkwide'] = 1;
	} else {
		$current = get_option( 'active_plugins', array() );
	}

	$valid = validate_plugin($plugin);
	if ( is_wp_error($valid) )
		return $valid;

	if ( ( $network_wide && ! isset( $current[ $plugin ] ) ) || ( ! $network_wide && ! in_array( $plugin, $current ) ) ) {
		if ( !empty($redirect) )
			wp_redirect(add_query_arg('_error_nonce', wp_create_nonce('plugin-activation-error_' . $plugin), $redirect)); // we'll override this later if the plugin can be included without fatal error
		ob_start();
		wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . $plugin );
		$_wp_plugin_file = $plugin;
		include_once( WP_PLUGIN_DIR . '/' . $plugin );
		$plugin = $_wp_plugin_file; // Avoid stomping of the $plugin variable in a plugin.

		if ( ! $silent ) {
			do_action( 'activate_plugin', $plugin, $network_wide );

			do_action( 'activate_' . $plugin, $network_wide );
		}

		if ( $network_wide ) {
			$current = get_site_option( 'active_sitewide_plugins', array() );
			$current[$plugin] = time();
			update_site_option( 'active_sitewide_plugins', $current );
		} else {
			$current = get_option( 'active_plugins', array() );
			$current[] = $plugin;
			sort($current);
			update_option('active_plugins', $current);
		}

		if ( ! $silent ) {
			do_action( 'activated_plugin', $plugin, $network_wide );
		}

		if ( ob_get_length() > 0 ) {
			$output = ob_get_clean();
			return new WP_Error('unexpected_output', 'The plugin generated unexpected output.', $output);
		}
		ob_end_clean();
	}

	return null;
}

function deactivate_plugins( $plugins, $silent = false, $network_wide = null ) {
	if ( is_multisite() )
		$network_current = get_site_option( 'active_sitewide_plugins', array() );
	$current = get_option( 'active_plugins', array() );
	$do_blog = $do_network = false;

	foreach ( (array) $plugins as $plugin ) {
		$plugin = plugin_basename( trim( $plugin ) );
		if ( ! is_plugin_active($plugin) )
			continue;

		$network_deactivating = false !== $network_wide && is_plugin_active_for_network( $plugin );

		if ( ! $silent ) {
			do_action( 'deactivate_plugin', $plugin, $network_deactivating );
		}

		if ( false !== $network_wide ) {
			if ( is_plugin_active_for_network( $plugin ) ) {
				$do_network = true;
				unset( $network_current[ $plugin ] );
			} elseif ( $network_wide ) {
				continue;
			}
		}

		if ( true !== $network_wide ) {
			$key = array_search( $plugin, $current );
			if ( false !== $key ) {
				$do_blog = true;
				unset( $current[ $key ] );
			}
		}

		if ( ! $silent ) {

			do_action( 'deactivate_' . $plugin, $network_deactivating );

			do_action( 'deactivated_plugin', $plugin, $network_deactivating );
		}
	}

	if ( $do_blog )
		update_option('active_plugins', $current);
	if ( $do_network )
		update_site_option( 'active_sitewide_plugins', $network_current );
}

function activate_plugins( $plugins, $redirect = '', $network_wide = false, $silent = false ) {
	if ( !is_array($plugins) )
		$plugins = array($plugins);

	$errors = array();
	foreach ( $plugins as $plugin ) {
		if ( !empty($redirect) )
			$redirect = add_query_arg('plugin', $plugin, $redirect);
		$result = activate_plugin($plugin, $redirect, $network_wide, $silent);
		if ( is_wp_error($result) )
			$errors[$plugin] = $result;
	}

	if ( !empty($errors) )
		return new WP_Error('plugins_invalid', 'One of the plugins is invalid.', $errors);

	return true;
}

function delete_plugins( $plugins, $deprecated = '' ) {
	global $wp_filesystem;

	if ( empty($plugins) )
		return false;

	$checked = array();
	foreach ( $plugins as $plugin )
		$checked[] = 'checked[]=' . $plugin;

	ob_start();
	$url = wp_nonce_url('plugins.php?action=delete-selected&verify-delete=1&' . implode('&', $checked), 'bulk-plugins');
	if ( false === ($credentials = request_filesystem_credentials($url)) ) {
		$data = ob_get_clean();

		if ( ! empty($data) ){
			include_once( ABSPATH . 'wp-admin/admin-header.php');
			echo $data;
			include( ABSPATH . 'wp-admin/admin-footer.php');
			exit;
		}
		return;
	}

	if ( ! WP_Filesystem($credentials) ) {
		request_filesystem_credentials($url, '', true); //Failed to connect, Error and request again
		$data = ob_get_clean();

		if ( ! empty($data) ){
			include_once( ABSPATH . 'wp-admin/admin-header.php');
			echo $data;
			include( ABSPATH . 'wp-admin/admin-footer.php');
			exit;
		}
		return;
	}

	if ( ! is_object($wp_filesystem) )
		return new WP_Error('fs_unavailable', 'Could not access filesystem.');

	if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code() )
		return new WP_Error('fs_error', 'Filesystem error.', $wp_filesystem->errors);

	$plugins_dir = $wp_filesystem->wp_plugins_dir();
	if ( empty( $plugins_dir ) ) {
		return new WP_Error( 'fs_no_plugins_dir', 'Unable to locate WordPress Plugin directory.' );
	}

	$plugins_dir = trailingslashit( $plugins_dir );

	$plugin_translations = wp_get_installed_translations( 'plugins' );

	$errors = array();

	foreach ( $plugins as $plugin_file ) {
		// Run Uninstall hook.
		if ( is_uninstallable_plugin( $plugin_file ) ) {
			uninstall_plugin($plugin_file);
		}

		do_action( 'delete_plugin', $plugin_file );

		$this_plugin_dir = trailingslashit( dirname( $plugins_dir . $plugin_file ) );

		// If plugin is in its own directory, recursively delete the directory.
		if ( strpos( $plugin_file, '/' ) && $this_plugin_dir != $plugins_dir ) { //base check on if plugin includes directory separator AND that it's not the root plugin folder
			$deleted = $wp_filesystem->delete( $this_plugin_dir, true );
		} else {
			$deleted = $wp_filesystem->delete( $plugins_dir . $plugin_file );
		}

		do_action( 'deleted_plugin', $plugin_file, $deleted );

		if ( ! $deleted ) {
			$errors[] = $plugin_file;
			continue;
		}

		// Remove language files, silently.
		$plugin_slug = dirname( $plugin_file );
		if ( '.' !== $plugin_slug && ! empty( $plugin_translations[ $plugin_slug ] ) ) {
			$translations = $plugin_translations[ $plugin_slug ];

			foreach ( $translations as $translation => $data ) {
				$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.po' );
				$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.mo' );
			}
		}
	}

	// Remove deleted plugins from the plugin updates list.
	if ( $current = get_site_transient('update_plugins') ) {
		// Don't remove the plugins that weren't deleted.
		$deleted = array_diff( $plugins, $errors );

		foreach ( $deleted as $plugin_file ) {
			unset( $current->response[ $plugin_file ] );
		}

		set_site_transient( 'update_plugins', $current );
	}

	if ( ! empty($errors) )
		return new WP_Error('could_not_remove_plugin', sprintf(__('Could not fully remove the plugin(s) %s.'), implode(', ', $errors)) );

	return true;
}

function validate_active_plugins() {
	$plugins = get_option( 'active_plugins', array() );
	// Validate vartype: array.
	if ( ! is_array( $plugins ) ) {
		update_option( 'active_plugins', array() );
		$plugins = array();
	}

	if ( is_multisite() && current_user_can( 'manage_network_plugins' ) ) {
		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		$plugins = array_merge( $plugins, array_keys( $network_plugins ) );
	}

	if ( empty( $plugins ) )
		return array();

	$invalid = array();

	// Invalid plugins get deactivated.
	foreach ( $plugins as $plugin ) {
		$result = validate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			$invalid[$plugin] = $result;
			deactivate_plugins( $plugin, true );
		}
	}
	return $invalid;
}

function validate_plugin($plugin) {
	if ( validate_file($plugin) )
		return new WP_Error('plugin_invalid', __('Invalid plugin path.'));
	if ( ! file_exists(WP_PLUGIN_DIR . '/' . $plugin) )
		return new WP_Error('plugin_not_found', __('Plugin file does not exist.'));

	$installed_plugins = get_plugins();
	if ( ! isset($installed_plugins[$plugin]) )
		return new WP_Error('no_plugin_header', __('The plugin does not have a valid header.'));
	return 0;
}

function is_uninstallable_plugin($plugin) {
	$file = plugin_basename($plugin);

	$uninstallable_plugins = (array) get_option('uninstall_plugins');
	if ( isset( $uninstallable_plugins[$file] ) || file_exists( WP_PLUGIN_DIR . '/' . dirname($file) . '/uninstall.php' ) )
		return true;

	return false;
}

function uninstall_plugin($plugin) {
	$file = plugin_basename($plugin);

	$uninstallable_plugins = (array) get_option('uninstall_plugins');

	do_action( 'pre_uninstall_plugin', $plugin, $uninstallable_plugins );

	if ( file_exists( WP_PLUGIN_DIR . '/' . dirname($file) . '/uninstall.php' ) ) {
		if ( isset( $uninstallable_plugins[$file] ) ) {
			unset($uninstallable_plugins[$file]);
			update_option('uninstall_plugins', $uninstallable_plugins);
		}
		unset($uninstallable_plugins);

		define('WP_UNINSTALL_PLUGIN', $file);
		wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . dirname( $file ) );
		include( WP_PLUGIN_DIR . '/' . dirname($file) . '/uninstall.php' );

		return true;
	}

	if ( isset( $uninstallable_plugins[$file] ) ) {
		$callable = $uninstallable_plugins[$file];
		unset($uninstallable_plugins[$file]);
		update_option('uninstall_plugins', $uninstallable_plugins);
		unset($uninstallable_plugins);

		wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . $file );
		include( WP_PLUGIN_DIR . '/' . $file );

		add_action( 'uninstall_' . $file, $callable );

		do_action( 'uninstall_' . $file );
	}
}

function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null ) {
	global $menu, $admin_page_hooks, $_registered_pages, $_parent_pages;

	$menu_slug = plugin_basename( $menu_slug );

	$admin_page_hooks[$menu_slug] = sanitize_title( $menu_title );

	$hookname = get_plugin_page_hookname( $menu_slug, '' );

	if ( !empty( $function ) && !empty( $hookname ) && current_user_can( $capability ) )
		add_action( $hookname, $function );

	if ( empty($icon_url) ) {
		$icon_url = 'dashicons-admin-generic';
		$icon_class = 'menu-icon-generic ';
	} else {
		$icon_url = set_url_scheme( $icon_url );
		$icon_class = '';
	}

	$new_menu = array( $menu_title, $capability, $menu_slug, $page_title, 'menu-top ' . $icon_class . $hookname, $hookname, $icon_url );

	if ( null === $position ) {
		$menu[] = $new_menu;
	} elseif ( isset( $menu[ "$position" ] ) ) {
	 	$position = $position + substr( base_convert( md5( $menu_slug . $menu_title ), 16, 10 ) , -5 ) * 0.00001;
		$menu[ "$position" ] = $new_menu;
	} else {
		$menu[ $position ] = $new_menu;
	}

	$_registered_pages[$hookname] = true;

	// No parent as top level
	$_parent_pages[$menu_slug] = false;

	return $hookname;
}

function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	global $submenu, $menu, $_wp_real_parent_file, $_wp_submenu_nopriv,
		$_registered_pages, $_parent_pages;

	$menu_slug = plugin_basename( $menu_slug );
	$parent_slug = plugin_basename( $parent_slug);

	if ( isset( $_wp_real_parent_file[$parent_slug] ) )
		$parent_slug = $_wp_real_parent_file[$parent_slug];

	if ( !current_user_can( $capability ) ) {
		$_wp_submenu_nopriv[$parent_slug][$menu_slug] = true;
		return false;
	}

	if (!isset( $submenu[$parent_slug] ) && $menu_slug != $parent_slug ) {
		foreach ( (array)$menu as $parent_menu ) {
			if ( $parent_menu[2] == $parent_slug && current_user_can( $parent_menu[1] ) )
				$submenu[$parent_slug][] = array_slice( $parent_menu, 0, 4 );
		}
	}

	$submenu[$parent_slug][] = array ( $menu_title, $capability, $menu_slug, $page_title );

	$hookname = get_plugin_page_hookname( $menu_slug, $parent_slug);
	if (!empty ( $function ) && !empty ( $hookname ))
		add_action( $hookname, $function );

	$_registered_pages[$hookname] = true;

	if ( 'tools.php' == $parent_slug )
		$_registered_pages[get_plugin_page_hookname( $menu_slug, 'edit.php')] = true;

	// No parent as top level.
	$_parent_pages[$menu_slug] = $parent_slug;

	return $hookname;
}

function add_management_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'tools.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'options-general.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_theme_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'themes.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_plugins_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'plugins.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_users_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	if ( current_user_can('edit_users') )
		$parent = 'users.php';
	else
		$parent = 'profile.php';
	return add_submenu_page( $parent, $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_dashboard_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'index.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_posts_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'edit.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_media_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'upload.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_links_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'link-manager.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_pages_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'edit.php?post_type=page', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function add_comments_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
	return add_submenu_page( 'edit-comments.php', $page_title, $menu_title, $capability, $menu_slug, $function );
}

function remove_menu_page( $menu_slug ) {
	global $menu;

	foreach ( $menu as $i => $item ) {
		if ( $menu_slug == $item[2] ) {
			unset( $menu[$i] );
			return $item;
		}
	}

	return false;
}

function remove_submenu_page( $menu_slug, $submenu_slug ) {
	global $submenu;

	if ( !isset( $submenu[$menu_slug] ) )
		return false;

	foreach ( $submenu[$menu_slug] as $i => $item ) {
		if ( $submenu_slug == $item[2] ) {
			unset( $submenu[$menu_slug][$i] );
			return $item;
		}
	}

	return false;
}

function menu_page_url($menu_slug, $echo = true) {
	global $_parent_pages;

	if ( isset( $_parent_pages[$menu_slug] ) ) {
		$parent_slug = $_parent_pages[$menu_slug];
		if ( $parent_slug && ! isset( $_parent_pages[$parent_slug] ) ) {
			$url = admin_url( add_query_arg( 'page', $menu_slug, $parent_slug ) );
		} else {
			$url = admin_url( 'admin.php?page=' . $menu_slug );
		}
	} else {
		$url = '';
	}

	$url = esc_url($url);

	if ( $echo )
		echo $url;

	return $url;
}

function get_admin_page_parent( $parent = '' ) {
	global $parent_file, $menu, $submenu, $pagenow, $typenow,
		$plugin_page, $_wp_real_parent_file, $_wp_menu_nopriv, $_wp_submenu_nopriv;

	if ( !empty ( $parent ) && 'admin.php' != $parent ) {
		if ( isset( $_wp_real_parent_file[$parent] ) )
			$parent = $_wp_real_parent_file[$parent];
		return $parent;
	}

	if ( $pagenow == 'admin.php' && isset( $plugin_page ) ) {
		foreach ( (array)$menu as $parent_menu ) {
			if ( $parent_menu[2] == $plugin_page ) {
				$parent_file = $plugin_page;
				if ( isset( $_wp_real_parent_file[$parent_file] ) )
					$parent_file = $_wp_real_parent_file[$parent_file];
				return $parent_file;
			}
		}
		if ( isset( $_wp_menu_nopriv[$plugin_page] ) ) {
			$parent_file = $plugin_page;
			if ( isset( $_wp_real_parent_file[$parent_file] ) )
					$parent_file = $_wp_real_parent_file[$parent_file];
			return $parent_file;
		}
	}

	if ( isset( $plugin_page ) && isset( $_wp_submenu_nopriv[$pagenow][$plugin_page] ) ) {
		$parent_file = $pagenow;
		if ( isset( $_wp_real_parent_file[$parent_file] ) )
			$parent_file = $_wp_real_parent_file[$parent_file];
		return $parent_file;
	}

	foreach (array_keys( (array)$submenu ) as $parent) {
		foreach ( $submenu[$parent] as $submenu_array ) {
			if ( isset( $_wp_real_parent_file[$parent] ) )
				$parent = $_wp_real_parent_file[$parent];
			if ( !empty($typenow) && ($submenu_array[2] == "$pagenow?post_type=$typenow") ) {
				$parent_file = $parent;
				return $parent;
			} elseif ( $submenu_array[2] == $pagenow && empty($typenow) && ( empty($parent_file) || false === strpos($parent_file, '?') ) ) {
				$parent_file = $parent;
				return $parent;
			} elseif ( isset( $plugin_page ) && ($plugin_page == $submenu_array[2] ) ) {
				$parent_file = $parent;
				return $parent;
			}
		}
	}

	if ( empty($parent_file) )
		$parent_file = '';
	return '';
}

function get_admin_page_title() {
	global $title, $menu, $submenu, $pagenow, $plugin_page, $typenow;

	if ( ! empty ( $title ) )
		return $title;

	$hook = get_plugin_page_hook( $plugin_page, $pagenow );

	$parent = $parent1 = get_admin_page_parent();

	if ( empty ( $parent) ) {
		foreach ( (array)$menu as $menu_array ) {
			if ( isset( $menu_array[3] ) ) {
				if ( $menu_array[2] == $pagenow ) {
					$title = $menu_array[3];
					return $menu_array[3];
				} elseif ( isset( $plugin_page ) && ($plugin_page == $menu_array[2] ) && ($hook == $menu_array[3] ) ) {
					$title = $menu_array[3];
					return $menu_array[3];
				}
			} else {
				$title = $menu_array[0];
				return $title;
			}
		}
	} else {
		foreach ( array_keys( $submenu ) as $parent ) {
			foreach ( $submenu[$parent] as $submenu_array ) {
				if ( isset( $plugin_page ) &&
					( $plugin_page == $submenu_array[2] ) &&
					(
						( $parent == $pagenow ) ||
						( $parent == $plugin_page ) ||
						( $plugin_page == $hook ) ||
						( $pagenow == 'admin.php' && $parent1 != $submenu_array[2] ) ||
						( !empty($typenow) && $parent == $pagenow . '?post_type=' . $typenow)
					)
					) {
						$title = $submenu_array[3];
						return $submenu_array[3];
					}

				if ( $submenu_array[2] != $pagenow || isset( $_GET['page'] ) ) // not the current page
					continue;

				if ( isset( $submenu_array[3] ) ) {
					$title = $submenu_array[3];
					return $submenu_array[3];
				} else {
					$title = $submenu_array[0];
					return $title;
				}
			}
		}
		if ( empty ( $title ) ) {
			foreach ( $menu as $menu_array ) {
				if ( isset( $plugin_page ) &&
					( $plugin_page == $menu_array[2] ) &&
					( $pagenow == 'admin.php' ) &&
					( $parent1 == $menu_array[2] ) )
					{
						$title = $menu_array[3];
						return $menu_array[3];
					}
			}
		}
	}

	return $title;
}

function get_plugin_page_hook( $plugin_page, $parent_page ) {
	$hook = get_plugin_page_hookname( $plugin_page, $parent_page );
	if ( has_action($hook) )
		return $hook;
	else
		return null;
}

function get_plugin_page_hookname( $plugin_page, $parent_page ) {
	global $admin_page_hooks;

	$parent = get_admin_page_parent( $parent_page );

	$page_type = 'admin';
	if ( empty ( $parent_page ) || 'admin.php' == $parent_page || isset( $admin_page_hooks[$plugin_page] ) ) {
		if ( isset( $admin_page_hooks[$plugin_page] ) ) {
			$page_type = 'toplevel';
		} elseif ( isset( $admin_page_hooks[$parent] )) {
			$page_type = $admin_page_hooks[$parent];
		}
	} elseif ( isset( $admin_page_hooks[$parent] ) ) {
		$page_type = $admin_page_hooks[$parent];
	}

	$plugin_name = preg_replace( '!\.php!', '', $plugin_page );

	return $page_type . '_page_' . $plugin_name;
}

function user_can_access_admin_page() {
	global $pagenow, $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv,
		$plugin_page, $_registered_pages;

	$parent = get_admin_page_parent();

	if ( !isset( $plugin_page ) && isset( $_wp_submenu_nopriv[$parent][$pagenow] ) )
		return false;

	if ( isset( $plugin_page ) ) {
		if ( isset( $_wp_submenu_nopriv[$parent][$plugin_page] ) )
			return false;

		$hookname = get_plugin_page_hookname($plugin_page, $parent);

		if ( !isset($_registered_pages[$hookname]) )
			return false;
	}

	if ( empty( $parent) ) {
		if ( isset( $_wp_menu_nopriv[$pagenow] ) )
			return false;
		if ( isset( $_wp_submenu_nopriv[$pagenow][$pagenow] ) )
			return false;
		if ( isset( $plugin_page ) && isset( $_wp_submenu_nopriv[$pagenow][$plugin_page] ) )
			return false;
		if ( isset( $plugin_page ) && isset( $_wp_menu_nopriv[$plugin_page] ) )
			return false;
		foreach (array_keys( $_wp_submenu_nopriv ) as $key ) {
			if ( isset( $_wp_submenu_nopriv[$key][$pagenow] ) )
				return false;
			if ( isset( $plugin_page ) && isset( $_wp_submenu_nopriv[$key][$plugin_page] ) )
			return false;
		}
		return true;
	}

	if ( isset( $plugin_page ) && ( $plugin_page == $parent ) && isset( $_wp_menu_nopriv[$plugin_page] ) )
		return false;

	if ( isset( $submenu[$parent] ) ) {
		foreach ( $submenu[$parent] as $submenu_array ) {
			if ( isset( $plugin_page ) && ( $submenu_array[2] == $plugin_page ) ) {
				if ( current_user_can( $submenu_array[1] ))
					return true;
				else
					return false;
			} elseif ( $submenu_array[2] == $pagenow ) {
				if ( current_user_can( $submenu_array[1] ))
					return true;
				else
					return false;
			}
		}
	}

	foreach ( $menu as $menu_array ) {
		if ( $menu_array[2] == $parent) {
			if ( current_user_can( $menu_array[1] ))
				return true;
			else
				return false;
		}
	}

	return true;
}

function register_setting( $option_group, $option_name, $sanitize_callback = '' ) {
	global $new_whitelist_options;

	if ( 'misc' == $option_group ) {
		_deprecated_argument( __FUNCTION__, '3.0', sprintf( 'The "%s" options group has been removed. Use another settings group.', 'misc' ) );
		$option_group = 'general';
	}

	if ( 'privacy' == $option_group ) {
		_deprecated_argument( __FUNCTION__, '3.5', sprintf( 'The "%s" options group has been removed. Use another settings group.', 'privacy' ) );
		$option_group = 'reading';
	}

	$new_whitelist_options[ $option_group ][] = $option_name;
	if ( $sanitize_callback != '' )
		add_filter( "sanitize_option_{$option_name}", $sanitize_callback );
}

function unregister_setting( $option_group, $option_name, $sanitize_callback = '' ) {
	global $new_whitelist_options;

	if ( 'misc' == $option_group ) {
		_deprecated_argument( __FUNCTION__, '3.0', sprintf( __( 'The "%s" options group has been removed. Use another settings group.' ), 'misc' ) );
		$option_group = 'general';
	}

	if ( 'privacy' == $option_group ) {
		_deprecated_argument( __FUNCTION__, '3.5', sprintf( __( 'The "%s" options group has been removed. Use another settings group.' ), 'privacy' ) );
		$option_group = 'reading';
	}

	$pos = array_search( $option_name, (array) $new_whitelist_options[ $option_group ] );
	if ( $pos !== false )
		unset( $new_whitelist_options[ $option_group ][ $pos ] );
	if ( $sanitize_callback != '' )
		remove_filter( "sanitize_option_{$option_name}", $sanitize_callback );
}

function option_update_filter( $options ) {
	global $new_whitelist_options;

	if ( is_array( $new_whitelist_options ) )
		$options = add_option_whitelist( $new_whitelist_options, $options );

	return $options;
}

function add_option_whitelist( $new_options, $options = '' ) {
	if ( $options == '' )
		global $whitelist_options;
	else
		$whitelist_options = $options;

	foreach ( $new_options as $page => $keys ) {
		foreach ( $keys as $key ) {
			if ( !isset($whitelist_options[ $page ]) || !is_array($whitelist_options[ $page ]) ) {
				$whitelist_options[ $page ] = array();
				$whitelist_options[ $page ][] = $key;
			} else {
				$pos = array_search( $key, $whitelist_options[ $page ] );
				if ( $pos === false )
					$whitelist_options[ $page ][] = $key;
			}
		}
	}

	return $whitelist_options;
}

function remove_option_whitelist( $del_options, $options = '' ) {
	if ( $options == '' )
		global $whitelist_options;
	else
		$whitelist_options = $options;

	foreach ( $del_options as $page => $keys ) {
		foreach ( $keys as $key ) {
			if ( isset($whitelist_options[ $page ]) && is_array($whitelist_options[ $page ]) ) {
				$pos = array_search( $key, $whitelist_options[ $page ] );
				if ( $pos !== false )
					unset( $whitelist_options[ $page ][ $pos ] );
			}
		}
	}

	return $whitelist_options;
}

function settings_fields($option_group) {
	echo "<input type='hidden' name='option_page' value='" . esc_attr($option_group) . "' />";
	echo '<input type="hidden" name="action" value="update" />';
	wp_nonce_field("$option_group-options");
}

function wp_clean_plugins_cache( $clear_update_cache = true ) {
	if ( $clear_update_cache )
		delete_site_transient( 'update_plugins' );
	wp_cache_delete( 'plugins', 'plugins' );
}

function plugin_sandbox_scrape( $plugin ) {
	wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . $plugin );
	include( WP_PLUGIN_DIR . '/' . $plugin );
}
