<?php

function create_initial_post_types() {
	register_post_type( 'post', array(
		'labels' => array(
			'name_admin_bar' => 'Post',
		),
		'public'  => true,
		'_builtin' => true,
		'_edit_link' => 'post.php?post=%d',
		'capability_type' => 'post',
		'map_meta_cap' => true,
		'menu_position' => 5,
		'hierarchical' => false,
		'rewrite' => false,
		'query_var' => false,
		'delete_with_user' => true,
		'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments', 'post-formats' ),
	) );
	register_post_type( 'page', array(
		'labels' => array(
			'name_admin_bar' => 'Page',
		),
		'public' => true,
		'publicly_queryable' => false,
		'_builtin' => true,
		'_edit_link' => 'post.php?post=%d',
		'capability_type' => 'page',
		'map_meta_cap' => true,
		'menu_position' => 20,
		'hierarchical' => true,
		'rewrite' => false,
		'query_var' => false,
		'delete_with_user' => true,
		'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'comments' ),
	) );
	register_post_type( 'attachment', array(
		'labels' => array(
			'name' => 'Media',
			'name_admin_bar' => 'Media',
			'add_new' =>'Add New',
 			'edit_item' => 'Edit Media',
 			'view_item' => 'View Attachment Page',
		),
		'public' => true,
		'show_ui' => true,
		'_builtin' => true,
		'_edit_link' => 'post.php?post=%d',
		'capability_type' => 'post',
		'capabilities' => array(
			'create_posts' => 'upload_files',
		),
		'map_meta_cap' => true,
		'hierarchical' => false,
		'rewrite' => false,
		'query_var' => false,
		'show_in_nav_menus' => false,
		'delete_with_user' => true,
		'supports' => array( 'title', 'author', 'comments' ),
	) );
	add_post_type_support( 'attachment:audio', 'thumbnail' );
	add_post_type_support( 'attachment:video', 'thumbnail' );
	register_post_type( 'nav_menu_item', array(
		'labels' => array(
			'name' => 'Navigation Menu Items',
			'singular_name' => 'Navigation Menu Item',
		),
		'public' => false,
		'_builtin' => true,
		'hierarchical' => false,
		'rewrite' => false,
		'delete_with_user' => false,
		'query_var' => false,
	) );
	register_post_status( 'publish', array(
		'label'       => 'Published',
		'public'      => true,
		'_builtin'    => true,
		'label_count' => _n_noop( 'Published <span class="count">(%s)</span>', 'Published <span class="count">(%s)</span>' ),
	) );
	register_post_status( 'future', array(
		'label'       => 'Scheduled',
		'protected'   => true,
		'_builtin'    => true,
		'label_count' => _n_noop('Scheduled <span class="count">(%s)</span>', 'Scheduled <span class="count">(%s)</span>' ),
	) );
	register_post_status( 'draft', array(
		'label'       => 'Draft',
		'protected'   => true,
		'_builtin'    => true,
		'label_count' => _n_noop( 'Draft <span class="count">(%s)</span>', 'Drafts <span class="count">(%s)</span>' ),
	) );
	register_post_status( 'pending', array(
		'label'       => 'Pending',
		'protected'   => true,
		'_builtin'    => true,
		'label_count' => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>' ),
	) );
	register_post_status( 'private', array(
		'label'       => 'Private',
		'private'     => true,
		'_builtin'    => true,
		'label_count' => _n_noop( 'Private <span class="count">(%s)</span>', 'Private <span class="count">(%s)</span>' ),
	) );
	register_post_status( 'trash', array(
		'label'       => 'Trash',
		'internal'    => true,
		'_builtin'    => true,
		'label_count' => _n_noop( 'Trash <span class="count">(%s)</span>', 'Trash <span class="count">(%s)</span>' ),
		'show_in_admin_status_list' => true,
	) );
	register_post_status( 'auto-draft', array(
		'label'    => 'auto-draft',
		'internal' => true,
		'_builtin' => true,
	) );
	register_post_status( 'inherit', array(
		'label'    => 'inherit',
		'internal' => true,
		'_builtin' => true,
		'exclude_from_search' => false,
	) );
}

function get_attached_file( $attachment_id, $unfiltered = false ) {
	$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( $file && 0 !== strpos( $file, '/' ) && ! preg_match( '|^.:\\\|', $file ) && ( ( $uploads = wp_upload_dir( null, false ) ) && false === $uploads['error'] ) ) {
		$file = $uploads['basedir'] . "/$file";
	}
	if ( $unfiltered ) {
		return $file;
	}
	return apply_filters( 'get_attached_file', $file, $attachment_id );
}

function update_attached_file( $attachment_id, $file ) {
	if ( !get_post( $attachment_id ) )
		return false;
	$file = apply_filters( 'update_attached_file', $file, $attachment_id );
	if ( $file = _wp_relative_upload_path( $file ) )
		return update_post_meta( $attachment_id, '_wp_attached_file', $file );
	else
		return delete_post_meta( $attachment_id, '_wp_attached_file' );
}

function _wp_relative_upload_path( $path ) {
	$new_path = $path;
	$uploads = wp_upload_dir( null, false );
	if ( 0 === strpos( $new_path, $uploads['basedir'] ) ) {
			$new_path = str_replace( $uploads['basedir'], '', $new_path );
			$new_path = ltrim( $new_path, '/' );
	}
	return apply_filters( '_wp_relative_upload_path', $new_path, $path );
}

function get_children( $args = '', $output = OBJECT ) {
	$kids = array();
	if ( empty( $args ) ) {
		if ( isset( $GLOBALS['post'] ) ) {
			$args = array('post_parent' => (int) $GLOBALS['post']->post_parent );
		} else {
			return $kids;
		}
	} elseif ( is_object( $args ) ) {
		$args = array('post_parent' => (int) $args->post_parent );
	} elseif ( is_numeric( $args ) ) {
		$args = array('post_parent' => (int) $args);
	}

	$defaults = array(
		'numberposts' => -1, 'post_type' => 'any',
		'post_status' => 'any', 'post_parent' => 0,
	);

	$r = wp_parse_args( $args, $defaults );

	$children = get_posts( $r );

	if ( ! $children )
		return $kids;

	if ( ! empty( $r['fields'] ) )
		return $children;

	update_post_cache($children);

	foreach ( $children as $key => $child )
		$kids[$child->ID] = $children[$key];

	if ( $output == OBJECT ) {
		return $kids;
	} elseif ( $output == ARRAY_A ) {
		$weeuns = array();
		foreach ( (array) $kids as $kid ) {
			$weeuns[$kid->ID] = get_object_vars($kids[$kid->ID]);
		}
		return $weeuns;
	} elseif ( $output == ARRAY_N ) {
		$babes = array();
		foreach ( (array) $kids as $kid ) {
			$babes[$kid->ID] = array_values(get_object_vars($kids[$kid->ID]));
		}
		return $babes;
	} else {
		return $kids;
	}
}

function get_extended( $post ) {
	if ( preg_match('/<!--more(.*?)?-->/', $post, $matches) ) {
		list($main, $extended) = explode($matches[0], $post, 2);
		$more_text = $matches[1];
	} else {
		$main = $post;
		$extended = '';
		$more_text = '';
	}
	$main = preg_replace('/^[\s]*(.*)[\s]*$/', '\\1', $main);
	$extended = preg_replace('/^[\s]*(.*)[\s]*$/', '\\1', $extended);
	$more_text = preg_replace('/^[\s]*(.*)[\s]*$/', '\\1', $more_text);
	return array( 'main' => $main, 'extended' => $extended, 'more_text' => $more_text );
}

function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $post ) && isset( $GLOBALS['post'] ) )
		$post = $GLOBALS['post'];
	if ( $post instanceof WP_Post ) {
		$_post = $post;
	} elseif ( is_object( $post ) ) {
		if ( empty( $post->filter ) ) {
			$_post = sanitize_post( $post, 'raw' );
			$_post = new WP_Post( $_post );
		} elseif ( 'raw' == $post->filter ) {
			$_post = new WP_Post( $post );
		} else {
			$_post = WP_Post::get_instance( $post->ID );
		}
	} else {
		$_post = WP_Post::get_instance( $post );
	}
	if ( ! $_post )
		return null;
	$_post = $_post->filter( $filter );
	if ( $output == ARRAY_A )
		return $_post->to_array();
	elseif ( $output == ARRAY_N )
		return array_values( $_post->to_array() );
	return $_post;
}

function get_post_ancestors( $post ) {
	$post = get_post( $post );
	if ( ! $post || empty( $post->post_parent ) || $post->post_parent == $post->ID )
		return array();
	$ancestors = array();
	$id = $ancestors[] = $post->post_parent;
	while ( $ancestor = get_post( $id ) ) {
		if ( empty( $ancestor->post_parent ) || ( $ancestor->post_parent == $post->ID ) || in_array( $ancestor->post_parent, $ancestors ) )
			break;
		$id = $ancestors[] = $ancestor->post_parent;
	}
	return $ancestors;
}

function get_post_field( $field, $post = null, $context = 'display' ) {
	$post = get_post( $post );

	if ( !$post )
		return '';

	if ( !isset($post->$field) )
		return '';
	return sanitize_post_field($field, $post->$field, $post->ID, $context);
}

function get_post_mime_type( $ID = '' ) {
	$post = get_post($ID);
	if ( is_object($post) )
		return $post->post_mime_type;
	return false;
}

function get_post_status( $ID = '' ) {
	$post = get_post($ID);
	if ( !is_object($post) )
		return false;
	if ( 'attachment' == $post->post_type ) {
		if ( 'private' == $post->post_status )
			return 'private';
		if ( ( 'inherit' == $post->post_status ) && ( 0 == $post->post_parent) )
			return 'publish';
		if ( $post->post_parent && ( $post->ID != $post->post_parent ) ) {
			$parent_post_status = get_post_status( $post->post_parent );
			if ( 'trash' == $parent_post_status ) {
				return get_post_meta( $post->post_parent, '_wp_trash_meta_status', true );
			} else {
				return $parent_post_status;
			}
		}

	}
	return apply_filters( 'get_post_status', $post->post_status, $post );
}

function get_post_statuses() {
	$status = array( 'draft'   => 'Draft', 'pending' => 'Pending Review', 'private' => 'Private', 'publish' => 'Published' );
	return $status;
}

function get_page_statuses() {
	$status = array( 'draft'   => 'Draft', 'private' => 'Private', 'publish' => 'Published' );
	return $status;
}

function register_post_status( $post_status, $args = array() ) {
	global $wp_post_statuses;
	if (!is_array($wp_post_statuses))
		$wp_post_statuses = array();
	$defaults = array(
		'label' => false,
		'label_count' => false,
		'exclude_from_search' => null,
		'_builtin' => false,
		'public' => null,
		'internal' => null,
		'protected' => null,
		'private' => null,
		'publicly_queryable' => null,
		'show_in_admin_status_list' => null,
		'show_in_admin_all_list' => null,
	);
	$args = wp_parse_args($args, $defaults);
	$args = (object) $args;

	$post_status = sanitize_key($post_status);
	$args->name = $post_status;
	if ( null === $args->public && null === $args->internal && null === $args->protected && null === $args->private )
		$args->internal = true;
	if ( null === $args->public  )
		$args->public = false;
	if ( null === $args->private  )
		$args->private = false;
	if ( null === $args->protected  )
		$args->protected = false;
	if ( null === $args->internal  )
		$args->internal = false;
	if ( null === $args->publicly_queryable )
		$args->publicly_queryable = $args->public;
	if ( null === $args->exclude_from_search )
		$args->exclude_from_search = $args->internal;
	if ( null === $args->show_in_admin_all_list )
		$args->show_in_admin_all_list = !$args->internal;
	if ( null === $args->show_in_admin_status_list )
		$args->show_in_admin_status_list = !$args->internal;
	if ( false === $args->label )
		$args->label = $post_status;
	if ( false === $args->label_count )
		$args->label_count = array( $args->label, $args->label );
	$wp_post_statuses[$post_status] = $args;
	return $args;
}

function get_post_status_object( $post_status ) {
	global $wp_post_statuses;
	if ( empty($wp_post_statuses[$post_status]) )
		return null;
	return $wp_post_statuses[$post_status];
}

function get_post_stati( $args = array(), $output = 'names', $operator = 'and' ) {
	global $wp_post_statuses;
	$field = ('names' == $output) ? 'name' : false;
	return wp_filter_object_list($wp_post_statuses, $args, $operator, $field);
}

function is_post_type_hierarchical( $post_type ) {
	if ( ! post_type_exists( $post_type ) )
		return false;
	$post_type = get_post_type_object( $post_type );
	return $post_type->hierarchical;
}

function post_type_exists( $post_type ) {
	return (bool) get_post_type_object( $post_type );
}

function get_post_type( $post = null ) {
	if ( $post = get_post( $post ) )
		return $post->post_type;
	return false;
}

function get_post_type_object( $post_type ) {
	global $wp_post_types;
	if ( ! is_scalar( $post_type ) || empty( $wp_post_types[ $post_type ] ) ) {
		return null;
	}
	return $wp_post_types[ $post_type ];
}

function get_post_types( $args = array(), $output = 'names', $operator = 'and' ) {
	global $wp_post_types;
	$field = ('names' == $output) ? 'name' : false;
	return wp_filter_object_list($wp_post_types, $args, $operator, $field);
}

function register_post_type( $post_type, $args = array() ) {
	global $wp_post_types, $wp_rewrite, $wp;
	if ( ! is_array( $wp_post_types ) ) {
		$wp_post_types = array();
	}
	$post_type = sanitize_key( $post_type );
	$args      = wp_parse_args( $args );
	$args = apply_filters( 'register_post_type_args', $args, $post_type );
	$has_edit_link = ! empty( $args['_edit_link'] );
	$defaults = array(
		'labels'               => array(),
		'description'          => '',
		'public'               => false,
		'hierarchical'         => false,
		'exclude_from_search'  => null,
		'publicly_queryable'   => null,
		'show_ui'              => null,
		'show_in_menu'         => null,
		'show_in_nav_menus'    => null,
		'show_in_admin_bar'    => null,
		'menu_position'        => null,
		'menu_icon'            => null,
		'capability_type'      => 'post',
		'capabilities'         => array(),
		'map_meta_cap'         => null,
		'supports'             => array(),
		'register_meta_box_cb' => null,
		'taxonomies'           => array(),
		'has_archive'          => false,
		'rewrite'              => true,
		'query_var'            => true,
		'can_export'           => true,
		'delete_with_user'     => null,
		'_builtin'             => false,
		'_edit_link'           => 'post.php?post=%d',
	);
	$args = array_merge( $defaults, $args );
	$args = (object) $args;

	$args->name = $post_type;

	if ( empty( $post_type ) || strlen( $post_type ) > 20 ) {
		_doing_it_wrong( __FUNCTION__, 'Post type names must be between 1 and 20 characters in length.', '4.2' );
		return new WP_Error( 'post_type_length_invalid', 'Post type names must be between 1 and 20 characters in length.' );
	}
	if ( null === $args->publicly_queryable )
		$args->publicly_queryable = $args->public;
	if ( null === $args->show_ui )
		$args->show_ui = $args->public;
	if ( null === $args->show_in_menu || ! $args->show_ui )
		$args->show_in_menu = $args->show_ui;
	if ( null === $args->show_in_admin_bar )
		$args->show_in_admin_bar = (bool) $args->show_in_menu;
	if ( null === $args->show_in_nav_menus )
		$args->show_in_nav_menus = $args->public;
	if ( null === $args->exclude_from_search )
		$args->exclude_from_search = !$args->public;
	if ( empty( $args->capabilities ) && null === $args->map_meta_cap && in_array( $args->capability_type, array( 'post', 'page' ) ) )
		$args->map_meta_cap = true;
	if ( null === $args->map_meta_cap )
		$args->map_meta_cap = false;
	if ( ! $args->show_ui && ! $has_edit_link ) {
		$args->_edit_link = '';
	}
	$args->cap = get_post_type_capabilities( $args );
	unset( $args->capabilities );

	if ( is_array( $args->capability_type ) )
		$args->capability_type = $args->capability_type[0];

	if ( ! empty( $args->supports ) ) {
		add_post_type_support( $post_type, $args->supports );
		unset( $args->supports );
	} elseif ( false !== $args->supports ) {
		add_post_type_support( $post_type, array( 'title', 'editor' ) );
	}

	if ( false !== $args->query_var ) {
		if ( true === $args->query_var )
			$args->query_var = $post_type;
		else
			$args->query_var = sanitize_title_with_dashes( $args->query_var );

		if ( $wp && is_post_type_viewable( $args ) ) {
			$wp->add_query_var( $args->query_var );
		}
	}

	if ( false !== $args->rewrite && ( is_admin() || '' != get_option( 'permalink_structure' ) ) ) {
		if ( ! is_array( $args->rewrite ) )
			$args->rewrite = array();
		if ( empty( $args->rewrite['slug'] ) )
			$args->rewrite['slug'] = $post_type;
		if ( ! isset( $args->rewrite['with_front'] ) )
			$args->rewrite['with_front'] = true;
		if ( ! isset( $args->rewrite['pages'] ) )
			$args->rewrite['pages'] = true;
		if ( ! isset( $args->rewrite['feeds'] ) || ! $args->has_archive )
			$args->rewrite['feeds'] = (bool) $args->has_archive;
		if ( ! isset( $args->rewrite['ep_mask'] ) ) {
			if ( isset( $args->permalink_epmask ) )
				$args->rewrite['ep_mask'] = $args->permalink_epmask;
			else
				$args->rewrite['ep_mask'] = EP_PERMALINK;
		}

		if ( $args->hierarchical )
			add_rewrite_tag( "%$post_type%", '(.+?)', $args->query_var ? "{$args->query_var}=" : "post_type=$post_type&pagename=" );
		else
			add_rewrite_tag( "%$post_type%", '([^/]+)', $args->query_var ? "{$args->query_var}=" : "post_type=$post_type&name=" );

		if ( $args->has_archive ) {
			$archive_slug = $args->has_archive === true ? $args->rewrite['slug'] : $args->has_archive;
			if ( $args->rewrite['with_front'] )
				$archive_slug = substr( $wp_rewrite->front, 1 ) . $archive_slug;
			else
				$archive_slug = $wp_rewrite->root . $archive_slug;

			add_rewrite_rule( "{$archive_slug}/?$", "index.php?post_type=$post_type", 'top' );
			if ( $args->rewrite['feeds'] && $wp_rewrite->feeds ) {
				$feeds = '(' . trim( implode( '|', $wp_rewrite->feeds ) ) . ')';
				add_rewrite_rule( "{$archive_slug}/feed/$feeds/?$", "index.php?post_type=$post_type" . '&feed=$matches[1]', 'top' );
				add_rewrite_rule( "{$archive_slug}/$feeds/?$", "index.php?post_type=$post_type" . '&feed=$matches[1]', 'top' );
			}
			if ( $args->rewrite['pages'] )
				add_rewrite_rule( "{$archive_slug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$", "index.php?post_type=$post_type" . '&paged=$matches[1]', 'top' );
		}

		$permastruct_args = $args->rewrite;
		$permastruct_args['feed'] = $permastruct_args['feeds'];
		add_permastruct( $post_type, "{$args->rewrite['slug']}/%$post_type%", $permastruct_args );
	}
	if ( $args->register_meta_box_cb )
		add_action( 'add_meta_boxes_' . $post_type, $args->register_meta_box_cb, 10, 1 );

	$args->labels = get_post_type_labels( $args );
	$args->label = $args->labels->name;

	$wp_post_types[ $post_type ] = $args;

	foreach ( $args->taxonomies as $taxonomy ) {
		register_taxonomy_for_object_type( $taxonomy, $post_type );
	}
	do_action( 'registered_post_type', $post_type, $args );
	return $args;
}

function unregister_post_type( $post_type ) {
	if ( ! post_type_exists( $post_type ) ) {
		return new WP_Error( 'invalid_post_type', 'Invalid post type' );
	}
	$post_type_args = get_post_type_object( $post_type );
	if ( $post_type_args->_builtin ) {
		return new WP_Error( 'invalid_post_type',  'Unregistering a built-in post type is not allowed' );
	}
	global $wp, $wp_rewrite, $_wp_post_type_features, $post_type_meta_caps, $wp_post_types;
	if ( false !== $post_type_args->query_var ) {
		$wp->remove_query_var( $post_type_args->query_var );
	}
	if ( false !== $post_type_args->rewrite ) {
		remove_rewrite_tag( "%$post_type%" );
		remove_permastruct( $post_type );
		foreach ( $wp_rewrite->extra_rules_top as $regex => $query ) {
			if ( false !== strpos( $query, "index.php?post_type=$post_type" ) ) {
				unset( $wp_rewrite->extra_rules_top[ $regex ] );
			}
		}
	}
	foreach ( $post_type_args->cap as $cap ) {
		unset( $post_type_meta_caps[ $cap ] );
	}
	unset( $_wp_post_type_features[ $post_type ] );
	if ( $post_type_args->register_meta_box_cb ) {
		remove_action( 'add_meta_boxes_' . $post_type, $post_type_args->register_meta_box_cb );
	}
	foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
		unregister_taxonomy_for_object_type( $taxonomy, $post_type );
	}
	unset( $wp_post_types[ $post_type ] );
	do_action( 'unregistered_post_type', $post_type );
	return true;
}

function get_post_type_capabilities( $args ) {
	if ( ! is_array( $args->capability_type ) )
		$args->capability_type = array( $args->capability_type, $args->capability_type . 's' );
	list( $singular_base, $plural_base ) = $args->capability_type;
	$default_capabilities = array(
		'edit_post'          => 'edit_'         . $singular_base,
		'read_post'          => 'read_'         . $singular_base,
		'delete_post'        => 'delete_'       . $singular_base,
		'edit_posts'         => 'edit_'         . $plural_base,
		'edit_others_posts'  => 'edit_others_'  . $plural_base,
		'publish_posts'      => 'publish_'      . $plural_base,
		'read_private_posts' => 'read_private_' . $plural_base,
	);
	if ( $args->map_meta_cap ) {
		$default_capabilities_for_mapping = array(
			'read'                   => 'read',
			'delete_posts'           => 'delete_'           . $plural_base,
			'delete_private_posts'   => 'delete_private_'   . $plural_base,
			'delete_published_posts' => 'delete_published_' . $plural_base,
			'delete_others_posts'    => 'delete_others_'    . $plural_base,
			'edit_private_posts'     => 'edit_private_'     . $plural_base,
			'edit_published_posts'   => 'edit_published_'   . $plural_base,
		);
		$default_capabilities = array_merge( $default_capabilities, $default_capabilities_for_mapping );
	}
	$capabilities = array_merge( $default_capabilities, $args->capabilities );
	if ( ! isset( $capabilities['create_posts'] ) )
		$capabilities['create_posts'] = $capabilities['edit_posts'];
	if ( $args->map_meta_cap )
		_post_type_meta_capabilities( $capabilities );
	return (object) $capabilities;
}

function _post_type_meta_capabilities( $capabilities = null ) {
	global $post_type_meta_caps;

	foreach ( $capabilities as $core => $custom ) {
		if ( in_array( $core, array( 'read_post', 'delete_post', 'edit_post' ) ) ) {
			$post_type_meta_caps[ $custom ] = $core;
		}
	}
}

function get_post_type_labels( $post_type_object ) {
	$nohier_vs_hier_defaults = array(
		'name' => array( 'Posts', 'Pages' ),
		'singular_name' => array( 'Post', 'Page' ),
		'add_new' => array( 'Add New', 'Add New' ),
		'add_new_item' => array( 'Add New Post', 'Add New Page' ),
		'edit_item' => array( 'Edit Post', 'Edit Page' ),
		'new_item' => array( 'New Post', 'New Page' ),
		'view_item' => array( 'View Post', 'View Page' ),
		'search_items' => array( 'Search Posts', 'Search Pages' ),
		'not_found' => array( 'No posts found.', 'No pages found.' ),
		'not_found_in_trash' => array( 'No posts found in Trash.', 'No pages found in Trash.' ),
		'parent_item_colon' => array( null, 'Parent Page:' ),
		'all_items' => array( 'All Posts', 'All Pages' ),
		'archives' => array( 'Post Archives', 'Page Archives' ),
		'insert_into_item' => array( 'Insert into post', 'Insert into page' ),
		'uploaded_to_this_item' => array( 'Uploaded to this post', 'Uploaded to this page' ),
		'featured_image' => array( 'Featured Image', 'Featured Image' ),
		'set_featured_image' => array( 'Set featured image', 'Set featured image' ),
		'remove_featured_image' => array( 'Remove featured image', 'Remove featured image' ),
		'use_featured_image' => array( 'Use as featured image', 'Use as featured image' ),
		'filter_items_list' => array( 'Filter posts list', 'Filter pages list' ),
		'items_list_navigation' => array( 'Posts list navigation', 'Pages list navigation' ),
		'items_list' => array( 'Posts list', 'Pages list' ),
	);
	$nohier_vs_hier_defaults['menu_name'] = $nohier_vs_hier_defaults['name'];
	$labels = _get_custom_object_labels( $post_type_object, $nohier_vs_hier_defaults );
	$post_type = $post_type_object->name;
	$default_labels = clone $labels;
	$labels = apply_filters( "post_type_labels_{$post_type}", $labels );
	$labels = (object) array_merge( (array) $default_labels, (array) $labels );
	return $labels;
}

function _get_custom_object_labels( $object, $nohier_vs_hier_defaults ) {
	$object->labels = (array) $object->labels;

	if ( isset( $object->label ) && empty( $object->labels['name'] ) )
		$object->labels['name'] = $object->label;

	if ( !isset( $object->labels['singular_name'] ) && isset( $object->labels['name'] ) )
		$object->labels['singular_name'] = $object->labels['name'];

	if ( ! isset( $object->labels['name_admin_bar'] ) )
		$object->labels['name_admin_bar'] = isset( $object->labels['singular_name'] ) ? $object->labels['singular_name'] : $object->name;

	if ( !isset( $object->labels['menu_name'] ) && isset( $object->labels['name'] ) )
		$object->labels['menu_name'] = $object->labels['name'];

	if ( !isset( $object->labels['all_items'] ) && isset( $object->labels['menu_name'] ) )
		$object->labels['all_items'] = $object->labels['menu_name'];

	if ( !isset( $object->labels['archives'] ) && isset( $object->labels['all_items'] ) ) {
		$object->labels['archives'] = $object->labels['all_items'];
	}

	$defaults = array();
	foreach ( $nohier_vs_hier_defaults as $key => $value ) {
		$defaults[$key] = $object->hierarchical ? $value[1] : $value[0];
	}
	$labels = array_merge( $defaults, $object->labels );
	$object->labels = (object) $object->labels;

	return (object) $labels;
}

function _add_post_type_submenus() {
	foreach ( get_post_types( array( 'show_ui' => true ) ) as $ptype ) {
		$ptype_obj = get_post_type_object( $ptype );
		if ( ! $ptype_obj->show_in_menu || $ptype_obj->show_in_menu === true )
			continue;
		add_submenu_page( $ptype_obj->show_in_menu, $ptype_obj->labels->name, $ptype_obj->labels->all_items, $ptype_obj->cap->edit_posts, "edit.php?post_type=$ptype" );
	}
}

function add_post_type_support( $post_type, $feature ) {
	global $_wp_post_type_features;

	$features = (array) $feature;
	foreach ($features as $feature) {
		if ( func_num_args() == 2 )
			$_wp_post_type_features[$post_type][$feature] = true;
		else
			$_wp_post_type_features[$post_type][$feature] = array_slice( func_get_args(), 2 );
	}
}

function remove_post_type_support( $post_type, $feature ) {
	global $_wp_post_type_features;
	unset( $_wp_post_type_features[ $post_type ][ $feature ] );
}

function get_all_post_type_supports( $post_type ) {
	global $_wp_post_type_features;
	if ( isset( $_wp_post_type_features[$post_type] ) )
		return $_wp_post_type_features[$post_type];
	return array();
}

function post_type_supports( $post_type, $feature ) {
	global $_wp_post_type_features;
	return ( isset( $_wp_post_type_features[$post_type][$feature] ) );
}

function get_post_types_by_support( $feature, $operator = 'and' ) {
	global $_wp_post_type_features;
	$features = array_fill_keys( (array) $feature, true );
	return array_keys( wp_filter_object_list( $_wp_post_type_features, $features, $operator ) );
}

function set_post_type( $post_id = 0, $post_type = 'post' ) {
	global $wpdb;
	$post_type = sanitize_post_field('post_type', $post_type, $post_id, 'db');
	$return = $wpdb->update( $wpdb->posts, array('post_type' => $post_type), array('ID' => $post_id) );
	clean_post_cache( $post_id );
	return $return;
}

function is_post_type_viewable( $post_type ) {
	if ( is_scalar( $post_type ) ) {
		$post_type = get_post_type_object( $post_type );
		if ( ! $post_type ) {
			return false;
		}
	}
	return $post_type->publicly_queryable || ( $post_type->_builtin && $post_type->public );
}

function get_posts( $args = null ) {
	$defaults = array(
		'numberposts' => 5,
		'category' => 0, 'orderby' => 'date',
		'order' => 'DESC', 'include' => array(),
		'exclude' => array(), 'meta_key' => '',
		'meta_value' =>'', 'post_type' => 'post',
		'suppress_filters' => true
	);

	$r = wp_parse_args( $args, $defaults );
	if ( empty( $r['post_status'] ) )
		$r['post_status'] = ( 'attachment' == $r['post_type'] ) ? 'inherit' : 'publish';
	if ( ! empty($r['numberposts']) && empty($r['posts_per_page']) )
		$r['posts_per_page'] = $r['numberposts'];
	if ( ! empty($r['category']) )
		$r['cat'] = $r['category'];
	if ( ! empty($r['include']) ) {
		$incposts = wp_parse_id_list( $r['include'] );
		$r['posts_per_page'] = count($incposts);
		$r['post__in'] = $incposts;
	} elseif ( ! empty($r['exclude']) )
		$r['post__not_in'] = wp_parse_id_list( $r['exclude'] );

	$r['ignore_sticky_posts'] = true;
	$r['no_found_rows'] = true;

	$get_posts = new WP_Query;
	return $get_posts->query($r);

}

function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
	if ( $the_post = wp_is_post_revision($post_id) )
		$post_id = $the_post;
	return add_metadata('post', $post_id, $meta_key, $meta_value, $unique);
}

function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
	if ( $the_post = wp_is_post_revision($post_id) )
		$post_id = $the_post;
	return delete_metadata('post', $post_id, $meta_key, $meta_value);
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	return get_metadata('post', $post_id, $key, $single);
}

function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
	if ( $the_post = wp_is_post_revision($post_id) )
		$post_id = $the_post;
	return update_metadata('post', $post_id, $meta_key, $meta_value, $prev_value);
}

function delete_post_meta_by_key( $post_meta_key ) {
	return delete_metadata( 'post', null, $post_meta_key, '', true );
}

function get_post_custom( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id )
		$post_id = get_the_ID();

	return get_post_meta( $post_id );
}

function get_post_custom_keys( $post_id = 0 ) {
	$custom = get_post_custom( $post_id );

	if ( !is_array($custom) )
		return;

	if ( $keys = array_keys($custom) )
		return $keys;
}

function get_post_custom_values( $key = '', $post_id = 0 ) {
	if ( !$key )
		return null;

	$custom = get_post_custom($post_id);

	return isset($custom[$key]) ? $custom[$key] : null;
}

function is_sticky( $post_id = 0 ) {
	$post_id = absint( $post_id );

	if ( ! $post_id )
		$post_id = get_the_ID();

	$stickies = get_option( 'sticky_posts' );

	if ( ! is_array( $stickies ) )
		return false;

	if ( in_array( $post_id, $stickies ) )
		return true;

	return false;
}

function sanitize_post( $post, $context = 'display' ) {
	if ( is_object($post) ) {
		if ( isset($post->filter) && $context == $post->filter )
			return $post;
		if ( !isset($post->ID) )
			$post->ID = 0;
		foreach ( array_keys(get_object_vars($post)) as $field )
			$post->$field = sanitize_post_field($field, $post->$field, $post->ID, $context);
		$post->filter = $context;
	} elseif ( is_array( $post ) ) {
		if ( isset($post['filter']) && $context == $post['filter'] )
			return $post;
		if ( !isset($post['ID']) )
			$post['ID'] = 0;
		foreach ( array_keys($post) as $field )
			$post[$field] = sanitize_post_field($field, $post[$field], $post['ID'], $context);
		$post['filter'] = $context;
	}
	return $post;
}

function sanitize_post_field( $field, $value, $post_id, $context = 'display' ) {
	$int_fields = array('ID', 'post_parent', 'menu_order');
	if ( in_array($field, $int_fields) )
		$value = (int) $value;
	$array_int_fields = array( 'ancestors' );
	if ( in_array($field, $array_int_fields) ) {
		$value = array_map( 'absint', $value);
		return $value;
	}

	if ( 'raw' == $context )
		return $value;

	$prefixed = false;
	if ( false !== strpos($field, 'post_') ) {
		$prefixed = true;
		$field_no_prefix = str_replace('post_', '', $field);
	}

	if ( 'edit' == $context ) {
		$format_to_edit = array('post_content', 'post_excerpt', 'post_title', 'post_password');

		if ( $prefixed ) {

			$value = apply_filters( "edit_{$field}", $value, $post_id );

			$value = apply_filters( "{$field_no_prefix}_edit_pre", $value, $post_id );
		} else {
			$value = apply_filters( "edit_post_{$field}", $value, $post_id );
		}

		if ( in_array($field, $format_to_edit) ) {
			if ( 'post_content' == $field )
				$value = format_to_edit($value, user_can_richedit());
			else
				$value = format_to_edit($value);
		} else {
			$value = esc_attr($value);
		}
	} elseif ( 'db' == $context ) {
		if ( $prefixed ) {

			$value = apply_filters( "pre_{$field}", $value );

			$value = apply_filters( "{$field_no_prefix}_save_pre", $value );
		} else {
			$value = apply_filters( "pre_post_{$field}", $value );

			$value = apply_filters( "{$field}_pre", $value );
		}
	} else {
		if ( $prefixed ) {
			$value = apply_filters( $field, $value, $post_id, $context );
		} else {
			$value = apply_filters( "post_{$field}", $value, $post_id, $context );
		}
	}

	if ( 'attribute' == $context )
		$value = esc_attr($value);
	elseif ( 'js' == $context )
		$value = esc_js($value);

	return $value;
}

function stick_post( $post_id ) {
	$stickies = get_option('sticky_posts');
	if ( !is_array($stickies) )
		$stickies = array($post_id);
	if ( ! in_array($post_id, $stickies) )
		$stickies[] = $post_id;
	update_option('sticky_posts', $stickies);
}

function unstick_post( $post_id ) {
	$stickies = get_option('sticky_posts');
	if ( !is_array($stickies) )
		return;
	if ( ! in_array($post_id, $stickies) )
		return;
	$offset = array_search($post_id, $stickies);
	if ( false === $offset )
		return;
	array_splice($stickies, $offset, 1);
	update_option('sticky_posts', $stickies);
}

function _count_posts_cache_key( $type = 'post', $perm = '' ) {
	$cache_key = 'posts-' . $type;
	if ( 'readable' == $perm && is_user_logged_in() ) {
		$post_type_object = get_post_type_object( $type );
		if ( $post_type_object && ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
			$cache_key .= '_' . $perm . '_' . get_current_user_id();
		}
	}
	return $cache_key;
}

function wp_count_posts( $type = 'post', $perm = '' ) {
	global $wpdb;
	if ( ! post_type_exists( $type ) )
		return new stdClass;
	$cache_key = _count_posts_cache_key( $type, $perm );
	$counts = wp_cache_get( $cache_key, 'counts' );
	if ( false !== $counts ) {
		return apply_filters( 'wp_count_posts', $counts, $type, $perm );
	}

	$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";
	if ( 'readable' == $perm && is_user_logged_in() ) {
		$post_type_object = get_post_type_object($type);
		if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
			$query .= $wpdb->prepare( " AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
				get_current_user_id()
			);
		}
	}
	$query .= ' GROUP BY post_status';

	$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );
	$counts = array_fill_keys( get_post_stati(), 0 );

	foreach ( $results as $row ) {
		$counts[ $row['post_status'] ] = $row['num_posts'];
	}

	$counts = (object) $counts;
	wp_cache_set( $cache_key, $counts, 'counts' );

	return apply_filters( 'wp_count_posts', $counts, $type, $perm );
}

function wp_count_attachments( $mime_type = '' ) {
	global $wpdb;

	$and = wp_post_mime_type_where( $mime_type );
	$count = $wpdb->get_results( "SELECT post_mime_type, COUNT( * ) AS num_posts FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status != 'trash' $and GROUP BY post_mime_type", ARRAY_A );

	$counts = array();
	foreach ( (array) $count as $row ) {
		$counts[ $row['post_mime_type'] ] = $row['num_posts'];
	}
	$counts['trash'] = $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status = 'trash' $and");
	return apply_filters( 'wp_count_attachments', (object) $counts, $mime_type );
}

function get_post_mime_types() {
	$post_mime_types = array(
		'image' => array('Images', 'Manage Images', _n_noop('Image <span class="count">(%s)</span>', 'Images <span class="count">(%s)</span>')),
		'audio' => array('Audio', 'Manage Audio', _n_noop('Audio <span class="count">(%s)</span>', 'Audio <span class="count">(%s)</span>')),
		'video' => array('Video', 'Manage Video', _n_noop('Video <span class="count">(%s)</span>', 'Video <span class="count">(%s)</span>')),
	);
	return apply_filters( 'post_mime_types', $post_mime_types );
}

function wp_match_mime_types( $wildcard_mime_types, $real_mime_types ) {
	$matches = array();
	if ( is_string( $wildcard_mime_types ) ) {
		$wildcard_mime_types = array_map( 'trim', explode( ',', $wildcard_mime_types ) );
	}
	if ( is_string( $real_mime_types ) ) {
		$real_mime_types = array_map( 'trim', explode( ',', $real_mime_types ) );
	}

	$patternses = array();
	$wild = '[-._a-z0-9]*';

	foreach ( (array) $wildcard_mime_types as $type ) {
		$mimes = array_map( 'trim', explode( ',', $type ) );
		foreach ( $mimes as $mime ) {
			$regex = str_replace( '__wildcard__', $wild, preg_quote( str_replace( '*', '__wildcard__', $mime ) ) );
			$patternses[][$type] = "^$regex$";
			if ( false === strpos( $mime, '/' ) ) {
				$patternses[][$type] = "^$regex/";
				$patternses[][$type] = $regex;
			}
		}
	}
	asort( $patternses );

	foreach ( $patternses as $patterns ) {
		foreach ( $patterns as $type => $pattern ) {
			foreach ( (array) $real_mime_types as $real ) {
				if ( preg_match( "#$pattern#", $real ) && ( empty( $matches[$type] ) || false === array_search( $real, $matches[$type] ) ) ) {
					$matches[$type][] = $real;
				}
			}
		}
	}
	return $matches;
}

function wp_post_mime_type_where( $post_mime_types, $table_alias = '' ) {
	$where = '';
	$wildcards = array('', '%', '%/%');
	if ( is_string($post_mime_types) )
		$post_mime_types = array_map('trim', explode(',', $post_mime_types));

	$wheres = array();

	foreach ( (array) $post_mime_types as $mime_type ) {
		$mime_type = preg_replace('/\s/', '', $mime_type);
		$slashpos = strpos($mime_type, '/');
		if ( false !== $slashpos ) {
			$mime_group = preg_replace('/[^-*.a-zA-Z0-9]/', '', substr($mime_type, 0, $slashpos));
			$mime_subgroup = preg_replace('/[^-*.+a-zA-Z0-9]/', '', substr($mime_type, $slashpos + 1));
			if ( empty($mime_subgroup) )
				$mime_subgroup = '*';
			else
				$mime_subgroup = str_replace('/', '', $mime_subgroup);
			$mime_pattern = "$mime_group/$mime_subgroup";
		} else {
			$mime_pattern = preg_replace('/[^-*.a-zA-Z0-9]/', '', $mime_type);
			if ( false === strpos($mime_pattern, '*') )
				$mime_pattern .= '/*';
		}

		$mime_pattern = preg_replace('/\*+/', '%', $mime_pattern);
		if ( in_array( $mime_type, $wildcards ) )
			return '';
		if ( false !== strpos($mime_pattern, '%') )
			$wheres[] = empty($table_alias) ? "post_mime_type LIKE '$mime_pattern'" : "$table_alias.post_mime_type LIKE '$mime_pattern'";
		else
			$wheres[] = empty($table_alias) ? "post_mime_type = '$mime_pattern'" : "$table_alias.post_mime_type = '$mime_pattern'";
	}
	if ( !empty($wheres) )
		$where = ' AND (' . join(' OR ', $wheres) . ') ';
	return $where;
}

function wp_delete_post( $postid = 0, $force_delete = false ) {
	global $wpdb;
	if ( !$post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", $postid)) )
		return $post;
	if ( !$force_delete && ( $post->post_type == 'post' || $post->post_type == 'page') && get_post_status( $postid ) != 'trash' && EMPTY_TRASH_DAYS )
		return wp_trash_post( $postid );
	if ( $post->post_type == 'attachment' )
		return wp_delete_attachment( $postid, $force_delete );
	$check = apply_filters( 'pre_delete_post', null, $post, $force_delete );
	if ( null !== $check ) {
		return $check;
	}
	do_action( 'before_delete_post', $postid );
	delete_post_meta($postid,'_wp_trash_meta_status');
	delete_post_meta($postid,'_wp_trash_meta_time');
	wp_delete_object_term_relationships($postid, get_object_taxonomies($post->post_type));
	$parent_data = array( 'post_parent' => $post->post_parent );
	$parent_where = array( 'post_parent' => $postid );
	if ( is_post_type_hierarchical( $post->post_type ) ) {
		$children_query = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_parent = %d AND post_type = %s", $postid, $post->post_type );
		$children = $wpdb->get_results( $children_query );
		if ( $children ) {
			$wpdb->update( $wpdb->posts, $parent_data, $parent_where + array( 'post_type' => $post->post_type ) );
		}
	}
	$revision_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'revision'", $postid ) );
	foreach ( $revision_ids as $revision_id )
		wp_delete_post_revision( $revision_id );
	$wpdb->update( $wpdb->posts, $parent_data, $parent_where + array( 'post_type' => 'attachment' ) );
	wp_defer_comment_counting( true );
	$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d", $postid ));
	foreach ( $comment_ids as $comment_id ) {
		wp_delete_comment( $comment_id, true );
	}
	wp_defer_comment_counting( false );
	$post_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d ", $postid ));
	foreach ( $post_meta_ids as $mid )
		delete_metadata_by_mid( 'post', $mid );
	do_action( 'delete_post', $postid );
	$result = $wpdb->delete( $wpdb->posts, array( 'ID' => $postid ) );
	if ( ! $result ) {
		return false;
	}
	do_action( 'deleted_post', $postid );
	clean_post_cache( $post );
	if ( is_post_type_hierarchical( $post->post_type ) && $children ) {
		foreach ( $children as $child )
			clean_post_cache( $child );
	}
	wp_clear_scheduled_hook('publish_future_post', array( $postid ) );
	do_action( 'after_delete_post', $postid );
	return $post;
}

function _reset_front_page_settings_for_post( $post_id ) {
	$post = get_post( $post_id );
	if ( 'page' == $post->post_type ) {
		if ( get_option( 'page_on_front' ) == $post->ID ) {
			update_option( 'show_on_front', 'posts' );
			update_option( 'page_on_front', 0 );
		}
		if ( get_option( 'page_for_posts' ) == $post->ID ) {
			delete_option( 'page_for_posts', 0 );
		}
	}
	unstick_post( $post->ID );
}

function wp_trash_post( $post_id = 0 ) {
	if ( !EMPTY_TRASH_DAYS )
		return wp_delete_post($post_id, true);
	if ( !$post = get_post($post_id, ARRAY_A) )
		return $post;
	if ( $post['post_status'] == 'trash' )
		return false;
	do_action( 'wp_trash_post', $post_id );

	add_post_meta($post_id,'_wp_trash_meta_status', $post['post_status']);
	add_post_meta($post_id,'_wp_trash_meta_time', time());

	$post['post_status'] = 'trash';
	wp_insert_post( wp_slash( $post ) );

	wp_trash_post_comments($post_id);

	do_action( 'trashed_post', $post_id );

	return $post;
}

function wp_untrash_post( $post_id = 0 ) {
	if ( !$post = get_post($post_id, ARRAY_A) )
		return $post;

	if ( $post['post_status'] != 'trash' )
		return false;

	do_action( 'untrash_post', $post_id );

	$post_status = get_post_meta($post_id, '_wp_trash_meta_status', true);

	$post['post_status'] = $post_status;

	delete_post_meta($post_id, '_wp_trash_meta_status');
	delete_post_meta($post_id, '_wp_trash_meta_time');

	wp_insert_post( wp_slash( $post ) );

	wp_untrash_post_comments($post_id);

	do_action( 'untrashed_post', $post_id );

	return $post;
}

function wp_trash_post_comments( $post = null ) {
	global $wpdb;

	$post = get_post($post);
	if ( empty($post) )
		return;

	$post_id = $post->ID;

	do_action( 'trash_post_comments', $post_id );

	$comments = $wpdb->get_results( $wpdb->prepare("SELECT comment_ID, comment_approved FROM $wpdb->comments WHERE comment_post_ID = %d", $post_id) );
	if ( empty($comments) )
		return;

	$statuses = array();
	foreach ( $comments as $comment )
		$statuses[$comment->comment_ID] = $comment->comment_approved;
	add_post_meta($post_id, '_wp_trash_meta_comments_status', $statuses);

	$result = $wpdb->update($wpdb->comments, array('comment_approved' => 'post-trashed'), array('comment_post_ID' => $post_id));

	clean_comment_cache( array_keys($statuses) );

	do_action( 'trashed_post_comments', $post_id, $statuses );

	return $result;
}

function wp_untrash_post_comments( $post = null ) {
	global $wpdb;

	$post = get_post($post);
	if ( empty($post) )
		return;

	$post_id = $post->ID;

	$statuses = get_post_meta($post_id, '_wp_trash_meta_comments_status', true);

	if ( empty($statuses) )
		return true;

	do_action( 'untrash_post_comments', $post_id );

	$group_by_status = array();
	foreach ( $statuses as $comment_id => $comment_status )
		$group_by_status[$comment_status][] = $comment_id;
	foreach ( $group_by_status as $status => $comments ) {
		if ( 'post-trashed' == $status ) {
			$status = '0';
		}
		$comments_in = implode( ', ', array_map( 'intval', $comments ) );
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->comments SET comment_approved = %s WHERE comment_ID IN ($comments_in)", $status ) );
	}
	clean_comment_cache( array_keys($statuses) );
	delete_post_meta($post_id, '_wp_trash_meta_comments_status');
	do_action( 'untrashed_post_comments', $post_id );
}

function wp_get_post_categories( $post_id = 0, $args = array() ) {
	$post_id = (int) $post_id;

	$defaults = array('fields' => 'ids');
	$args = wp_parse_args( $args, $defaults );

	$cats = wp_get_object_terms($post_id, 'category', $args);
	return $cats;
}

function wp_get_post_tags( $post_id = 0, $args = array() ) {
	return wp_get_post_terms( $post_id, 'post_tag', $args);
}

function wp_get_post_terms( $post_id = 0, $taxonomy = 'post_tag', $args = array() ) {
	$post_id = (int) $post_id;

	$defaults = array('fields' => 'all');
	$args = wp_parse_args( $args, $defaults );

	$tags = wp_get_object_terms($post_id, $taxonomy, $args);

	return $tags;
}

function wp_get_recent_posts( $args = array(), $output = ARRAY_A ) {
	if ( is_numeric( $args ) ) {
		_deprecated_argument( __FUNCTION__, '3.1', 'Passing an integer number of posts is deprecated. Pass an array of arguments instead.' );
		$args = array( 'numberposts' => absint( $args ) );
	}
	$defaults = array(
		'numberposts' => 10, 'offset' => 0,
		'category' => 0, 'orderby' => 'post_date',
		'order' => 'DESC', 'include' => '',
		'exclude' => '', 'meta_key' => '',
		'meta_value' =>'', 'post_type' => 'post', 'post_status' => 'draft, publish, future, pending, private',
		'suppress_filters' => true
	);

	$r = wp_parse_args( $args, $defaults );

	$results = get_posts( $r );
	if ( ARRAY_A == $output ){
		foreach ( $results as $key => $result ) {
			$results[$key] = get_object_vars( $result );
		}
		return $results ? $results : array();
	}

	return $results ? $results : false;

}

function wp_insert_post( $postarr, $wp_error = false ) {
	global $wpdb;
	$user_id = get_current_user_id();
	$defaults = array(
		'post_author' => $user_id,
		'post_content' => '',
		'post_content_filtered' => '',
		'post_title' => '',
		'post_excerpt' => '',
		'post_status' => 'draft',
		'post_type' => 'post',
		'comment_status' => '',
		'ping_status' => '',
		'post_password' => '',
		'to_ping' =>  '',
		'pinged' => '',
		'post_parent' => 0,
		'menu_order' => 0,
		'guid' => '',
		'import_id' => 0,
		'context' => '',
	);

	$postarr = wp_parse_args($postarr, $defaults);

	unset( $postarr[ 'filter' ] );

	$postarr = sanitize_post($postarr, 'db');
	$post_ID = 0;
	$update = false;
	$guid = $postarr['guid'];

	if ( ! empty( $postarr['ID'] ) ) {
		$update = true;
		$post_ID = $postarr['ID'];
		$post_before = get_post( $post_ID );
		if ( is_null( $post_before ) ) {
			if ( $wp_error ) {
				return new WP_Error( 'invalid_post', 'Invalid post ID.' );
			}
			return 0;
		}

		$guid = get_post_field( 'guid', $post_ID );
		$previous_status = get_post_field('post_status', $post_ID );
	} else {
		$previous_status = 'new';
	}

	$post_type = empty( $postarr['post_type'] ) ? 'post' : $postarr['post_type'];

	$post_title = $postarr['post_title'];
	$post_content = $postarr['post_content'];
	$post_excerpt = $postarr['post_excerpt'];
	if ( isset( $postarr['post_name'] ) ) {
		$post_name = $postarr['post_name'];
	} elseif ( $update ) {
		$post_name = $post_before->post_name;
	}

	$maybe_empty = 'attachment' !== $post_type
		&& ! $post_content && ! $post_title && ! $post_excerpt
		&& post_type_supports( $post_type, 'editor' )
		&& post_type_supports( $post_type, 'title' )
		&& post_type_supports( $post_type, 'excerpt' );

	if ( apply_filters( 'wp_insert_post_empty_content', $maybe_empty, $postarr ) ) {
		if ( $wp_error ) {
			return new WP_Error( 'empty_content', 'Content, title, and excerpt are empty.' );
		} else {
			return 0;
		}
	}

	$post_status = empty( $postarr['post_status'] ) ? 'draft' : $postarr['post_status'];
	if ( 'attachment' === $post_type && ! in_array( $post_status, array( 'inherit', 'private', 'trash' ) ) ) {
		$post_status = 'inherit';
	}

	if ( ! empty( $postarr['post_category'] ) ) {
		$post_category = array_filter( $postarr['post_category'] );
	}

	if ( empty( $post_category ) || 0 == count( $post_category ) || ! is_array( $post_category ) ) {
		// 'post' requires at least one category.
		if ( 'post' == $post_type && 'auto-draft' != $post_status ) {
			$post_category = array( get_option('default_category') );
		} else {
			$post_category = array();
		}
	}

	if ( 'pending' == $post_status && !current_user_can( 'publish_posts' ) ) {
		$post_name = '';
	}

	if ( empty($post_name) ) {
		if ( !in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
			$post_name = sanitize_title($post_title);
		} else {
			$post_name = '';
		}
	} else {
		$check_name = sanitize_title( $post_name, '', 'old-save' );
		if ( $update && strtolower( urlencode( $post_name ) ) == $check_name && get_post_field( 'post_name', $post_ID ) == $check_name ) {
			$post_name = $check_name;
		} else { // new post, or slug has changed.
			$post_name = sanitize_title($post_name);
		}
	}

	if ( empty( $postarr['post_date'] ) || '0000-00-00 00:00:00' == $postarr['post_date'] ) {
		if ( empty( $postarr['post_date_gmt'] ) || '0000-00-00 00:00:00' == $postarr['post_date_gmt'] ) {
			$post_date = current_time( 'mysql' );
		} else {
			$post_date = get_date_from_gmt( $postarr['post_date_gmt'] );
		}
	} else {
		$post_date = $postarr['post_date'];
	}

	$mm = substr( $post_date, 5, 2 );
	$jj = substr( $post_date, 8, 2 );
	$aa = substr( $post_date, 0, 4 );
	$valid_date = wp_checkdate( $mm, $jj, $aa, $post_date );
	if ( ! $valid_date ) {
		if ( $wp_error ) {
			return new WP_Error( 'invalid_date', 'Whoops, the provided date is invalid.' );
		} else {
			return 0;
		}
	}

	if ( empty( $postarr['post_date_gmt'] ) || '0000-00-00 00:00:00' == $postarr['post_date_gmt'] ) {
		if ( ! in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
			$post_date_gmt = get_gmt_from_date( $post_date );
		} else {
			$post_date_gmt = '0000-00-00 00:00:00';
		}
	} else {
		$post_date_gmt = $postarr['post_date_gmt'];
	}

	if ( $update || '0000-00-00 00:00:00' == $post_date ) {
		$post_modified     = current_time( 'mysql' );
		$post_modified_gmt = current_time( 'mysql', 1 );
	} else {
		$post_modified     = $post_date;
		$post_modified_gmt = $post_date_gmt;
	}

	if ( 'attachment' !== $post_type ) {
		if ( 'publish' == $post_status ) {
			$now = gmdate('Y-m-d H:i:59');
			if ( mysql2date('U', $post_date_gmt, false) > mysql2date('U', $now, false) ) {
				$post_status = 'future';
			}
		} elseif ( 'future' == $post_status ) {
			$now = gmdate('Y-m-d H:i:59');
			if ( mysql2date('U', $post_date_gmt, false) <= mysql2date('U', $now, false) ) {
				$post_status = 'publish';
			}
		}
	}

	if ( empty( $postarr['comment_status'] ) ) {
		if ( $update ) {
			$comment_status = 'closed';
		} else {
			$comment_status = get_default_comment_status( $post_type );
		}
	} else {
		$comment_status = $postarr['comment_status'];
	}

	$post_content_filtered = $postarr['post_content_filtered'];
	$post_author = isset( $postarr['post_author'] ) ? $postarr['post_author'] : $user_id;
	$ping_status = empty( $postarr['ping_status'] ) ? get_default_comment_status( $post_type, 'pingback' ) : $postarr['ping_status'];
	$to_ping = isset( $postarr['to_ping'] ) ? sanitize_trackback_urls( $postarr['to_ping'] ) : '';
	$pinged = isset( $postarr['pinged'] ) ? $postarr['pinged'] : '';
	$import_id = isset( $postarr['import_id'] ) ? $postarr['import_id'] : 0;

	if ( isset( $postarr['menu_order'] ) ) {
		$menu_order = (int) $postarr['menu_order'];
	} else {
		$menu_order = 0;
	}

	$post_password = isset( $postarr['post_password'] ) ? $postarr['post_password'] : '';
	if ( 'private' == $post_status ) {
		$post_password = '';
	}

	if ( isset( $postarr['post_parent'] ) ) {
		$post_parent = (int) $postarr['post_parent'];
	} else {
		$post_parent = 0;
	}

	$post_parent = apply_filters( 'wp_insert_post_parent', $post_parent, $post_ID, compact( array_keys( $postarr ) ), $postarr );

	if ( 'trash' === $previous_status && 'trash' !== $post_status ) {
		$desired_post_slug = get_post_meta( $post_ID, '_wp_desired_post_slug', true );
		if ( $desired_post_slug ) {
			delete_post_meta( $post_ID, '_wp_desired_post_slug' );
			$post_name = $desired_post_slug;
		}
	}

	if ( 'trash' !== $post_status && $post_name ) {
		wp_add_trashed_suffix_to_post_name_for_trashed_posts( $post_name, $post_ID );
	}

	if ( 'trash' === $post_status && 'trash' !== $previous_status && 'new' !== $previous_status ) {
		$post_name = wp_add_trashed_suffix_to_post_name_for_post( $post_ID );
	}

	$post_name = wp_unique_post_slug( $post_name, $post_ID, $post_status, $post_type, $post_parent );

	$post_mime_type = isset( $postarr['post_mime_type'] ) ? $postarr['post_mime_type'] : '';

	$data = compact( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_parent', 'menu_order', 'post_mime_type', 'guid' );

	$emoji_fields = array( 'post_title', 'post_content', 'post_excerpt' );

	foreach ( $emoji_fields as $emoji_field ) {
		if ( isset( $data[ $emoji_field ] ) ) {
			$charset = $wpdb->get_col_charset( $wpdb->posts, $emoji_field );
			if ( 'utf8' === $charset ) {
				$data[ $emoji_field ] = wp_encode_emoji( $data[ $emoji_field ] );
			}
		}
	}

	if ( 'attachment' === $post_type ) {
		$data = apply_filters( 'wp_insert_attachment_data', $data, $postarr );
	} else {
		$data = apply_filters( 'wp_insert_post_data', $data, $postarr );
	}
	$data = wp_unslash( $data );
	$where = array( 'ID' => $post_ID );

	if ( $update ) {
		do_action( 'pre_post_update', $post_ID, $data );
		if ( false === $wpdb->update( $wpdb->posts, $data, $where ) ) {
			if ( $wp_error ) {
				return new WP_Error('db_update_error', 'Could not update post in the database', $wpdb->last_error);
			} else {
				return 0;
			}
		}
	} else {
		if ( ! empty( $import_id ) ) {
			$import_id = (int) $import_id;
			if ( ! $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID = %d", $import_id) ) ) {
				$data['ID'] = $import_id;
			}
		}
		if ( false === $wpdb->insert( $wpdb->posts, $data ) ) {
			if ( $wp_error ) {
				return new WP_Error('db_insert_error', 'Could not insert post into the database', $wpdb->last_error);
			} else {
				return 0;
			}
		}
		$post_ID = (int) $wpdb->insert_id;

		$where = array( 'ID' => $post_ID );
	}

	if ( empty( $data['post_name'] ) && ! in_array( $data['post_status'], array( 'draft', 'pending', 'auto-draft' ) ) ) {
		$data['post_name'] = wp_unique_post_slug( sanitize_title( $data['post_title'], $post_ID ), $post_ID, $data['post_status'], $post_type, $post_parent );
		$wpdb->update( $wpdb->posts, array( 'post_name' => $data['post_name'] ), $where );
		clean_post_cache( $post_ID );
	}

	if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
		wp_set_post_categories( $post_ID, $post_category );
	}

	if ( isset( $postarr['tags_input'] ) && is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
		wp_set_post_tags( $post_ID, $postarr['tags_input'] );
	}

	if ( ! empty( $postarr['tax_input'] ) ) {
		foreach ( $postarr['tax_input'] as $taxonomy => $tags ) {
			$taxonomy_obj = get_taxonomy($taxonomy);
			if ( ! $taxonomy_obj ) {
				_doing_it_wrong( __FUNCTION__, sprintf( 'Invalid taxonomy: %s.', $taxonomy ), '4.4.0' );
				continue;
			}

			if ( is_array( $tags ) ) {
				$tags = array_filter($tags);
			}
			if ( current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
				wp_set_post_terms( $post_ID, $tags, $taxonomy );
			}
		}
	}

	if ( ! empty( $postarr['meta_input'] ) ) {
		foreach ( $postarr['meta_input'] as $field => $value ) {
			update_post_meta( $post_ID, $field, $value );
		}
	}

	$current_guid = get_post_field( 'guid', $post_ID );

	if ( ! $update && '' == $current_guid ) {
		$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post_ID ) ), $where );
	}

	if ( 'attachment' === $postarr['post_type'] ) {
		if ( ! empty( $postarr['file'] ) ) {
			update_attached_file( $post_ID, $postarr['file'] );
		}

		if ( ! empty( $postarr['context'] ) ) {
			add_post_meta( $post_ID, '_wp_attachment_context', $postarr['context'], true );
		}
	}

	clean_post_cache( $post_ID );

	$post = get_post( $post_ID );

	if ( ! empty( $postarr['page_template'] ) && 'page' == $data['post_type'] ) {
		$post->page_template = $postarr['page_template'];
		$page_templates = wp_get_theme()->get_page_templates( $post );
		if ( 'default' != $postarr['page_template'] && ! isset( $page_templates[ $postarr['page_template'] ] ) ) {
			if ( $wp_error ) {
				return new WP_Error('invalid_page_template', 'The page template is invalid.');
			}
			update_post_meta( $post_ID, '_wp_page_template', 'default' );
		} else {
			update_post_meta( $post_ID, '_wp_page_template', $postarr['page_template'] );
		}
	}

	if ( 'attachment' !== $postarr['post_type'] ) {
		wp_transition_post_status( $data['post_status'], $previous_status, $post );
	} else {
		if ( $update ) {
			do_action( 'edit_attachment', $post_ID );
			$post_after = get_post( $post_ID );
			do_action( 'attachment_updated', $post_ID, $post_after, $post_before );
		} else {
			do_action( 'add_attachment', $post_ID );
		}

		return $post_ID;
	}

	if ( $update ) {
		do_action( 'edit_post', $post_ID, $post );
		$post_after = get_post($post_ID);
		do_action( 'post_updated', $post_ID, $post_after, $post_before);
	}

	do_action( "save_post_{$post->post_type}", $post_ID, $post, $update );

	do_action( 'save_post', $post_ID, $post, $update );

	do_action( 'wp_insert_post', $post_ID, $post, $update );

	return $post_ID;
}

function wp_update_post( $postarr = array(), $wp_error = false ) {
	if ( is_object($postarr) ) {
		$postarr = get_object_vars($postarr);
		$postarr = wp_slash($postarr);
	}

	$post = get_post($postarr['ID'], ARRAY_A);

	if ( is_null( $post ) ) {
		if ( $wp_error )
			return new WP_Error( 'invalid_post', 'Invalid post ID.' );
		return 0;
	}

	$post = wp_slash($post);

	if ( isset($postarr['post_category']) && is_array($postarr['post_category'])
			 && 0 != count($postarr['post_category']) )
		$post_cats = $postarr['post_category'];
	else
		$post_cats = $post['post_category'];

	if ( isset( $post['post_status'] ) && in_array($post['post_status'], array('draft', 'pending', 'auto-draft')) && empty($postarr['edit_date']) &&
			 ('0000-00-00 00:00:00' == $post['post_date_gmt']) )
		$clear_date = true;
	else
		$clear_date = false;

	$postarr = array_merge($post, $postarr);
	$postarr['post_category'] = $post_cats;
	if ( $clear_date ) {
		$postarr['post_date'] = current_time('mysql');
		$postarr['post_date_gmt'] = '';
	}

	if ($postarr['post_type'] == 'attachment')
		return wp_insert_attachment($postarr);

	return wp_insert_post( $postarr, $wp_error );
}

function wp_publish_post( $post ) {
	global $wpdb;

	if ( ! $post = get_post( $post ) )
		return;

	if ( 'publish' == $post->post_status )
		return;

	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $post->ID ) );

	clean_post_cache( $post->ID );

	$old_status = $post->post_status;
	$post->post_status = 'publish';
	wp_transition_post_status( 'publish', $old_status, $post );

	do_action( 'edit_post', $post->ID, $post );
	do_action( "save_post_{$post->post_type}", $post->ID, $post, true );
	do_action( 'save_post', $post->ID, $post, true );
	do_action( 'wp_insert_post', $post->ID, $post, true );
}

function check_and_publish_future_post( $post_id ) {
	$post = get_post($post_id);

	if ( empty($post) )
		return;

	if ( 'future' != $post->post_status )
		return;

	$time = strtotime( $post->post_date_gmt . ' GMT' );

	if ( $time > time() ) {
		wp_clear_scheduled_hook( 'publish_future_post', array( $post_id ) );
		wp_schedule_single_event( $time, 'publish_future_post', array( $post_id ) );
		return;
	}

	wp_publish_post( $post_id );
}

function wp_unique_post_slug( $slug, $post_ID, $post_status, $post_type, $post_parent ) {
	if ( in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ) ) || ( 'inherit' == $post_status && 'revision' == $post_type ) )
		return $slug;

	global $wpdb, $wp_rewrite;

	$original_slug = $slug;

	$feeds = $wp_rewrite->feeds;
	if ( ! is_array( $feeds ) )
		$feeds = array();

	if ( 'attachment' == $post_type ) {
		$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND ID != %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_ID ) );

		if ( $post_name_check || in_array( $slug, $feeds ) || 'embed' === $slug || apply_filters( 'wp_unique_post_slug_is_bad_attachment_slug', false, $slug ) ) {
			$suffix = 2;
			do {
				$alt_post_name = _truncate_post_slug( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_ID ) );
				$suffix++;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	} elseif ( is_post_type_hierarchical( $post_type ) ) {
		if ( 'nav_menu_item' == $post_type )
			return $slug;

		$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_ID, $post_parent ) );

		if ( $post_name_check || in_array( $slug, $feeds ) || 'embed' === $slug || preg_match( "@^($wp_rewrite->pagination_base)?\d+$@", $slug )  || apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent ) ) {
			$suffix = 2;
			do {
				$alt_post_name = _truncate_post_slug( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_ID, $post_parent ) );
				$suffix++;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	} else {
		$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_ID ) );

		$post = get_post( $post_ID );
		$conflicts_with_date_archive = false;
		if ( 'post' === $post_type && ( ! $post || $post->post_name !== $slug ) && preg_match( '/^[0-9]+$/', $slug ) && $slug_num = intval( $slug ) ) {
			$permastructs   = array_values( array_filter( explode( '/', get_option( 'permalink_structure' ) ) ) );
			$postname_index = array_search( '%postname%', $permastructs );

			if ( 0 === $postname_index ||
				( $postname_index && '%year%' === $permastructs[ $postname_index - 1 ] && 13 > $slug_num ) ||
				( $postname_index && '%monthnum%' === $permastructs[ $postname_index - 1 ] && 32 > $slug_num )
			) {
				$conflicts_with_date_archive = true;
			}
		}

		if ( $post_name_check || in_array( $slug, $feeds ) || 'embed' === $slug || $conflicts_with_date_archive || apply_filters( 'wp_unique_post_slug_is_bad_flat_slug', false, $slug, $post_type ) ) {
			$suffix = 2;
			do {
				$alt_post_name = _truncate_post_slug( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $alt_post_name, $post_type, $post_ID ) );
				$suffix++;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
	}

	return apply_filters( 'wp_unique_post_slug', $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug );
}

function _truncate_post_slug( $slug, $length = 200 ) {
	if ( strlen( $slug ) > $length ) {
		$decoded_slug = urldecode( $slug );
		if ( $decoded_slug === $slug )
			$slug = substr( $slug, 0, $length );
		else
			$slug = utf8_uri_encode( $decoded_slug, $length );
	}

	return rtrim( $slug, '-' );
}

function wp_add_post_tags( $post_id = 0, $tags = '' ) {
	return wp_set_post_tags($post_id, $tags, true);
}

function wp_set_post_tags( $post_id = 0, $tags = '', $append = false ) {
	return wp_set_post_terms( $post_id, $tags, 'post_tag', $append);
}

function wp_set_post_terms( $post_id = 0, $tags = '', $taxonomy = 'post_tag', $append = false ) {
	$post_id = (int) $post_id;

	if ( !$post_id )
		return false;

	if ( empty($tags) )
		$tags = array();

	if ( ! is_array( $tags ) ) {
		$comma = _x( ',', 'tag delimiter' );
		if ( ',' !== $comma )
			$tags = str_replace( $comma, ',', $tags );
		$tags = explode( ',', trim( $tags, " \n\t\r\0\x0B," ) );
	}

	if ( is_taxonomy_hierarchical( $taxonomy ) ) {
		$tags = array_unique( array_map( 'intval', $tags ) );
	}

	return wp_set_object_terms( $post_id, $tags, $taxonomy, $append );
}

function wp_set_post_categories( $post_ID = 0, $post_categories = array(), $append = false ) {
	$post_ID = (int) $post_ID;
	$post_type = get_post_type( $post_ID );
	$post_status = get_post_status( $post_ID );
	$post_categories = (array) $post_categories;
	if ( empty( $post_categories ) ) {
		if ( 'post' == $post_type && 'auto-draft' != $post_status ) {
			$post_categories = array( get_option('default_category') );
			$append = false;
		} else {
			$post_categories = array();
		}
	} elseif ( 1 == count( $post_categories ) && '' == reset( $post_categories ) ) {
		return true;
	}

	return wp_set_post_terms( $post_ID, $post_categories, 'category', $append );
}

function wp_transition_post_status( $new_status, $old_status, $post ) {
	do_action( 'transition_post_status', $new_status, $old_status, $post );
	do_action( "{$old_status}_to_{$new_status}", $post );
	do_action( "{$new_status}_{$post->post_type}", $post->ID, $post );
}

function add_ping( $post_id, $uri ) {
	global $wpdb;
	$pung = $wpdb->get_var( $wpdb->prepare( "SELECT pinged FROM $wpdb->posts WHERE ID = %d", $post_id ));
	$pung = trim($pung);
	$pung = preg_split('/\s/', $pung);
	$pung[] = $uri;
	$new = implode("\n", $pung);
	$new = apply_filters( 'add_ping', $new );
	$new = wp_unslash($new);
	return $wpdb->update( $wpdb->posts, array( 'pinged' => $new ), array( 'ID' => $post_id ) );
}

function get_enclosed( $post_id ) {
	$custom_fields = get_post_custom( $post_id );
	$pung = array();
	if ( !is_array( $custom_fields ) )
		return $pung;
	foreach ( $custom_fields as $key => $val ) {
		if ( 'enclosure' != $key || !is_array( $val ) )
			continue;
		foreach ( $val as $enc ) {
			$enclosure = explode( "\n", $enc );
			$pung[] = trim( $enclosure[ 0 ] );
		}
	}
	return apply_filters( 'get_enclosed', $pung, $post_id );
}

function get_pung( $post_id ) {
	global $wpdb;
	$pung = $wpdb->get_var( $wpdb->prepare( "SELECT pinged FROM $wpdb->posts WHERE ID = %d", $post_id ));
	$pung = trim($pung);
	$pung = preg_split('/\s/', $pung);
	return apply_filters( 'get_pung', $pung );
}

function get_to_ping( $post_id ) {
	global $wpdb;
	$to_ping = $wpdb->get_var( $wpdb->prepare( "SELECT to_ping FROM $wpdb->posts WHERE ID = %d", $post_id ));
	$to_ping = sanitize_trackback_urls( $to_ping );
	$to_ping = preg_split('/\s/', $to_ping, -1, PREG_SPLIT_NO_EMPTY);
	return $to_ping;
}

function trackback_url_list( $tb_list, $post_id ) {
	if ( ! empty( $tb_list ) ) {
		$postdata = get_post( $post_id, ARRAY_A );
		$excerpt = strip_tags( $postdata['post_excerpt'] ? $postdata['post_excerpt'] : $postdata['post_content'] );
		if ( strlen( $excerpt ) > 255 ) {
			$excerpt = substr( $excerpt, 0, 252 ) . '&hellip;';
		}
		$trackback_urls = explode( ',', $tb_list );
		foreach ( (array) $trackback_urls as $tb_url ) {
			$tb_url = trim( $tb_url );
			trackback( $tb_url, wp_unslash( $postdata['post_title'] ), $excerpt, $post_id );
		}
	}
}

function get_all_page_ids() {
	global $wpdb;
	$page_ids = wp_cache_get('all_page_ids', 'posts');
	if ( ! is_array( $page_ids ) ) {
		$page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'page'");
		wp_cache_add('all_page_ids', $page_ids, 'posts');
	}
	return $page_ids;
}

function get_page( $page, $output = OBJECT, $filter = 'raw') {
	return get_post( $page, $output, $filter );
}

function get_page_by_path( $page_path, $output = OBJECT, $post_type = 'page' ) {
	global $wpdb;
	$page_path = rawurlencode(urldecode($page_path));
	$page_path = str_replace('%2F', '/', $page_path);
	$page_path = str_replace('%20', ' ', $page_path);
	$parts = explode( '/', trim( $page_path, '/' ) );
	$parts = esc_sql( $parts );
	$parts = array_map( 'sanitize_title_for_query', $parts );
	$in_string = "'" . implode( "','", $parts ) . "'";
	if ( is_array( $post_type ) ) {
		$post_types = $post_type;
	} else {
		$post_types = array( $post_type, 'attachment' );
	}
	$post_types = esc_sql( $post_types );
	$post_type_in_string = "'" . implode( "','", $post_types ) . "'";
	$sql = "
		SELECT ID, post_name, post_parent, post_type
		FROM $wpdb->posts
		WHERE post_name IN ($in_string)
		AND post_type IN ($post_type_in_string)
	";

	$pages = $wpdb->get_results( $sql, OBJECT_K );

	$revparts = array_reverse( $parts );

	$foundid = 0;
	foreach ( (array) $pages as $page ) {
		if ( $page->post_name == $revparts[0] ) {
			$count = 0;
			$p = $page;

			while ( $p->post_parent != 0 && isset( $pages[ $p->post_parent ] ) ) {
				$count++;
				$parent = $pages[ $p->post_parent ];
				if ( ! isset( $revparts[ $count ] ) || $parent->post_name != $revparts[ $count ] )
					break;
				$p = $parent;
			}

			if ( $p->post_parent == 0 && $count+1 == count( $revparts ) && $p->post_name == $revparts[ $count ] ) {
				$foundid = $page->ID;
				if ( $page->post_type == $post_type )
					break;
			}
		}
	}

	if ( $foundid ) {
		return get_post( $foundid, $output );
	}
}

function get_page_by_title( $page_title, $output = OBJECT, $post_type = 'page' ) {
	global $wpdb;

	if ( is_array( $post_type ) ) {
		$post_type = esc_sql( $post_type );
		$post_type_in_string = "'" . implode( "','", $post_type ) . "'";
		$sql = $wpdb->prepare( "
			SELECT ID
			FROM $wpdb->posts
			WHERE post_title = %s
			AND post_type IN ($post_type_in_string)
		", $page_title );
	} else {
		$sql = $wpdb->prepare( "
			SELECT ID
			FROM $wpdb->posts
			WHERE post_title = %s
			AND post_type = %s
		", $page_title, $post_type );
	}

	$page = $wpdb->get_var( $sql );

	if ( $page ) {
		return get_post( $page, $output );
	}
}

function get_page_children( $page_id, $pages ) {
	$children = array();
	foreach ( (array) $pages as $page ) {
		$children[ intval( $page->post_parent ) ][] = $page;
	}

	$page_list = array();
	if ( isset( $children[ $page_id ] ) ) {
		$to_look = array_reverse( $children[ $page_id ] );

		while ( $to_look ) {
			$p = array_pop( $to_look );
			$page_list[] = $p;
			if ( isset( $children[ $p->ID ] ) ) {
				foreach ( array_reverse( $children[ $p->ID ] ) as $child ) {
					$to_look[] = $child;
				}
			}
		}
	}
	return $page_list;
}

function get_page_hierarchy( &$pages, $page_id = 0 ) {
	if ( empty( $pages ) ) {
		return array();
	}
	$children = array();
	foreach ( (array) $pages as $p ) {
		$parent_id = intval( $p->post_parent );
		$children[ $parent_id ][] = $p;
	}
	$result = array();
	_page_traverse_name( $page_id, $children, $result );
	return $result;
}

function _page_traverse_name( $page_id, &$children, &$result ){
	if ( isset( $children[ $page_id ] ) ){
		foreach ( (array)$children[ $page_id ] as $child ) {
			$result[ $child->ID ] = $child->post_name;
			_page_traverse_name( $child->ID, $children, $result );
		}
	}
}

function get_page_uri( $page ) {
	if ( ! $page instanceof WP_Post ) {
		$page = get_post( $page );
	}

	if ( ! $page )
		return false;

	$uri = $page->post_name;

	foreach ( $page->ancestors as $parent ) {
		$parent = get_post( $parent );
		if ( $parent ) {
			$uri = $parent->post_name . '/' . $uri;
		}
	}

	return apply_filters( 'get_page_uri', $uri, $page );
}

function get_pages( $args = array() ) {
	global $wpdb;

	$defaults = array(
		'child_of' => 0, 'sort_order' => 'ASC',
		'sort_column' => 'post_title', 'hierarchical' => 1,
		'exclude' => array(), 'include' => array(),
		'meta_key' => '', 'meta_value' => '',
		'authors' => '', 'parent' => -1, 'exclude_tree' => array(),
		'number' => '', 'offset' => 0,
		'post_type' => 'page', 'post_status' => 'publish',
	);

	$r = wp_parse_args( $args, $defaults );

	$number = (int) $r['number'];
	$offset = (int) $r['offset'];
	$child_of = (int) $r['child_of'];
	$hierarchical = $r['hierarchical'];
	$exclude = $r['exclude'];
	$meta_key = $r['meta_key'];
	$meta_value = $r['meta_value'];
	$parent = $r['parent'];
	$post_status = $r['post_status'];

	$hierarchical_post_types = get_post_types( array( 'hierarchical' => true ) );
	if ( ! in_array( $r['post_type'], $hierarchical_post_types ) ) {
		return false;
	}
	if ( $parent > 0 && ! $child_of ) {
		$hierarchical = false;
	}

	if ( ! is_array( $post_status ) ) {
		$post_status = explode( ',', $post_status );
	}
	if ( array_diff( $post_status, get_post_stati() ) ) {
		return false;
	}

	$key = md5( serialize( wp_array_slice_assoc( $r, array_keys( $defaults ) ) ) );
	$last_changed = wp_cache_get( 'last_changed', 'posts' );
	if ( ! $last_changed ) {
		$last_changed = microtime();
		wp_cache_set( 'last_changed', $last_changed, 'posts' );
	}

	$cache_key = "get_pages:$key:$last_changed";
	if ( $cache = wp_cache_get( $cache_key, 'posts' ) ) {
		$pages = array_map( 'get_post', $cache );
		$pages = apply_filters( 'get_pages', $pages, $r );
		return $pages;
	}

	$inclusions = '';
	if ( ! empty( $r['include'] ) ) {
		$child_of = 0;
		$parent = -1;
		$exclude = '';
		$meta_key = '';
		$meta_value = '';
		$hierarchical = false;
		$incpages = wp_parse_id_list( $r['include'] );
		if ( ! empty( $incpages ) ) {
			$inclusions = ' AND ID IN (' . implode( ',', $incpages ) .  ')';
		}
	}

	$exclusions = '';
	if ( ! empty( $exclude ) ) {
		$expages = wp_parse_id_list( $exclude );
		if ( ! empty( $expages ) ) {
			$exclusions = ' AND ID NOT IN (' . implode( ',', $expages ) .  ')';
		}
	}

	$author_query = '';
	if ( ! empty( $r['authors'] ) ) {
		$post_authors = preg_split( '/[\s,]+/', $r['authors'] );

		if ( ! empty( $post_authors ) ) {
			foreach ( $post_authors as $post_author ) {
				if ( 0 == intval($post_author) ) {
					$post_author = get_user_by('login', $post_author);
					if ( empty( $post_author ) ) {
						continue;
					}
					if ( empty( $post_author->ID ) ) {
						continue;
					}
					$post_author = $post_author->ID;
				}

				if ( '' == $author_query ) {
					$author_query = $wpdb->prepare(' post_author = %d ', $post_author);
				} else {
					$author_query .= $wpdb->prepare(' OR post_author = %d ', $post_author);
				}
			}
			if ( '' != $author_query ) {
				$author_query = " AND ($author_query)";
			}
		}
	}

	$join = '';
	$where = "$exclusions $inclusions ";
	if ( '' !== $meta_key || '' !== $meta_value ) {
		$join = " LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )";
		$meta_key = wp_unslash($meta_key);
		$meta_value = wp_unslash($meta_value);
		if ( '' !== $meta_key ) {
			$where .= $wpdb->prepare(" AND $wpdb->postmeta.meta_key = %s", $meta_key);
		}
		if ( '' !== $meta_value ) {
			$where .= $wpdb->prepare(" AND $wpdb->postmeta.meta_value = %s", $meta_value);
		}

	}

	if ( is_array( $parent ) ) {
		$post_parent__in = implode( ',', array_map( 'absint', (array) $parent ) );
		if ( ! empty( $post_parent__in ) ) {
			$where .= " AND post_parent IN ($post_parent__in)";
		}
	} elseif ( $parent >= 0 ) {
		$where .= $wpdb->prepare(' AND post_parent = %d ', $parent);
	}

	if ( 1 == count( $post_status ) ) {
		$where_post_type = $wpdb->prepare( "post_type = %s AND post_status = %s", $r['post_type'], reset( $post_status ) );
	} else {
		$post_status = implode( "', '", $post_status );
		$where_post_type = $wpdb->prepare( "post_type = %s AND post_status IN ('$post_status')", $r['post_type'] );
	}

	$orderby_array = array();
	$allowed_keys = array( 'author', 'post_author', 'date', 'post_date', 'title', 'post_title', 'name', 'post_name', 'modified',
		'post_modified', 'modified_gmt', 'post_modified_gmt', 'menu_order', 'parent', 'post_parent',
		'ID', 'rand', 'comment_count' );

	foreach ( explode( ',', $r['sort_column'] ) as $orderby ) {
		$orderby = trim( $orderby );
		if ( ! in_array( $orderby, $allowed_keys ) ) {
			continue;
		}

		switch ( $orderby ) {
			case 'menu_order':
				break;
			case 'ID':
				$orderby = "$wpdb->posts.ID";
				break;
			case 'rand':
				$orderby = 'RAND()';
				break;
			case 'comment_count':
				$orderby = "$wpdb->posts.comment_count";
				break;
			default:
				if ( 0 === strpos( $orderby, 'post_' ) ) {
					$orderby = "$wpdb->posts." . $orderby;
				} else {
					$orderby = "$wpdb->posts.post_" . $orderby;
				}
		}

		$orderby_array[] = $orderby;

	}
	$sort_column = ! empty( $orderby_array ) ? implode( ',', $orderby_array ) : "$wpdb->posts.post_title";

	$sort_order = strtoupper( $r['sort_order'] );
	if ( '' !== $sort_order && ! in_array( $sort_order, array( 'ASC', 'DESC' ) ) ) {
		$sort_order = 'ASC';
	}

	$query = "SELECT * FROM $wpdb->posts $join WHERE ($where_post_type) $where ";
	$query .= $author_query;
	$query .= " ORDER BY " . $sort_column . " " . $sort_order ;

	if ( ! empty( $number ) ) {
		$query .= ' LIMIT ' . $offset . ',' . $number;
	}

	$pages = $wpdb->get_results($query);

	if ( empty($pages) ) {
		$pages = apply_filters( 'get_pages', array(), $r );
		return $pages;
	}
	$num_pages = count($pages);
	for ($i = 0; $i < $num_pages; $i++) {
		$pages[$i] = sanitize_post($pages[$i], 'raw');
	}
	update_post_cache( $pages );

	if ( $child_of || $hierarchical ) {
		$pages = get_page_children($child_of, $pages);
	}

	if ( ! empty( $r['exclude_tree'] ) ) {
		$exclude = wp_parse_id_list( $r['exclude_tree'] );
		foreach ( $exclude as $id ) {
			$children = get_page_children( $id, $pages );
			foreach ( $children as $child ) {
				$exclude[] = $child->ID;
			}
		}

		$num_pages = count( $pages );
		for ( $i = 0; $i < $num_pages; $i++ ) {
			if ( in_array( $pages[$i]->ID, $exclude ) ) {
				unset( $pages[$i] );
			}
		}
	}

	$page_structure = array();
	foreach ( $pages as $page ) {
		$page_structure[] = $page->ID;
	}

	wp_cache_set( $cache_key, $page_structure, 'posts' );
	$pages = array_map( 'get_post', $pages );

	return apply_filters( 'get_pages', $pages, $r );
}

function is_local_attachment($url) {
	if (strpos($url, home_url()) === false)
		return false;
	if (strpos($url, home_url('/?attachment_id=')) !== false)
		return true;
	if ( $id = url_to_postid($url) ) {
		$post = get_post($id);
		if ( 'attachment' == $post->post_type )
			return true;
	}
	return false;
}

function wp_insert_attachment( $args, $file = false, $parent = 0 ) {
	$defaults = array(
		'file'        => $file,
		'post_parent' => 0
	);

	$data = wp_parse_args( $args, $defaults );

	if ( ! empty( $parent ) ) {
		$data['post_parent'] = $parent;
	}

	$data['post_type'] = 'attachment';

	return wp_insert_post( $data );
}

function wp_delete_attachment( $post_id, $force_delete = false ) {
	global $wpdb;

	if ( !$post = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id) ) )
		return $post;

	if ( 'attachment' != $post->post_type )
		return false;

	if ( !$force_delete && EMPTY_TRASH_DAYS && MEDIA_TRASH && 'trash' != $post->post_status )
		return wp_trash_post( $post_id );

	delete_post_meta($post_id, '_wp_trash_meta_status');
	delete_post_meta($post_id, '_wp_trash_meta_time');

	$meta = wp_get_attachment_metadata( $post_id );
	$backup_sizes = get_post_meta( $post->ID, '_wp_attachment_backup_sizes', true );
	$file = get_attached_file( $post_id );


	do_action( 'delete_attachment', $post_id );

	wp_delete_object_term_relationships($post_id, array('category', 'post_tag'));
	wp_delete_object_term_relationships($post_id, get_object_taxonomies($post->post_type));

	// Delete all for any posts.
	delete_metadata( 'post', null, '_thumbnail_id', $post_id, true );

	wp_defer_comment_counting( true );

	$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d", $post_id ));
	foreach ( $comment_ids as $comment_id ) {
		wp_delete_comment( $comment_id, true );
	}

	wp_defer_comment_counting( false );

	$post_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d ", $post_id ));
	foreach ( $post_meta_ids as $mid )
		delete_metadata_by_mid( 'post', $mid );
	do_action( 'delete_post', $post_id );
	$result = $wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
	if ( ! $result ) {
		return false;
	}
	do_action( 'deleted_post', $post_id );
	$uploadpath = wp_upload_dir( null, false );
	if ( ! empty($meta['thumb']) ) {
		if (! $wpdb->get_row( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s AND post_id <> %d", '%' . $wpdb->esc_like( $meta['thumb'] ) . '%', $post_id)) ) {
			$thumbfile = str_replace(basename($file), $meta['thumb'], $file);
			$thumbfile = apply_filters( 'wp_delete_file', $thumbfile );
			@ unlink( path_join($uploadpath['basedir'], $thumbfile) );
		}
	}
	if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size => $sizeinfo ) {
			$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
			$intermediate_file = apply_filters( 'wp_delete_file', $intermediate_file );
			@ unlink( path_join( $uploadpath['basedir'], $intermediate_file ) );
		}
	}

	if ( is_array($backup_sizes) ) {
		foreach ( $backup_sizes as $size ) {
			$del_file = path_join( dirname($meta['file']), $size['file'] );
			$del_file = apply_filters( 'wp_delete_file', $del_file );
			@ unlink( path_join($uploadpath['basedir'], $del_file) );
		}
	}
	wp_delete_file( $file );
	clean_post_cache( $post );
	return $post;
}

function wp_get_attachment_metadata( $post_id = 0, $unfiltered = false ) {
	$post_id = (int) $post_id;
	if ( !$post = get_post( $post_id ) )
		return false;

	$data = get_post_meta( $post->ID, '_wp_attachment_metadata', true );

	if ( $unfiltered )
		return $data;

	return apply_filters( 'wp_get_attachment_metadata', $data, $post->ID );
}

function wp_update_attachment_metadata( $post_id, $data ) {
	$post_id = (int) $post_id;
	if ( !$post = get_post( $post_id ) )
		return false;

	if ( $data = apply_filters( 'wp_update_attachment_metadata', $data, $post->ID ) )
		return update_post_meta( $post->ID, '_wp_attachment_metadata', $data );
	else
		return delete_post_meta( $post->ID, '_wp_attachment_metadata' );
}

function wp_get_attachment_url( $post_id = 0 ) {
	$post_id = (int) $post_id;
	if ( !$post = get_post( $post_id ) )
		return false;

	if ( 'attachment' != $post->post_type )
		return false;

	$url = '';
	if ( $file = get_post_meta( $post->ID, '_wp_attached_file', true ) ) {
		if ( ( $uploads = wp_upload_dir( null, false ) ) && false === $uploads['error'] ) {
			if ( 0 === strpos( $file, $uploads['basedir'] ) ) {
				$url = str_replace($uploads['basedir'], $uploads['baseurl'], $file);
			} elseif ( false !== strpos($file, 'wp-content/uploads') ) {
				$url = trailingslashit( $uploads['baseurl'] . '/' . _wp_get_attachment_relative_path( $file ) ) . basename( $file );
			} else {
				$url = $uploads['baseurl'] . "/$file";
			}
		}
	}

	if ( empty($url) ) {
		$url = get_the_guid( $post->ID );
	}

	if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $GLOBALS['pagenow'] ) {
		$url = set_url_scheme( $url );
	}
	$url = apply_filters( 'wp_get_attachment_url', $url, $post->ID );

	if ( empty( $url ) )
		return false;

	return $url;
}

function wp_get_attachment_thumb_file( $post_id = 0 ) {
	$post_id = (int) $post_id;
	if ( !$post = get_post( $post_id ) )
		return false;
	if ( !is_array( $imagedata = wp_get_attachment_metadata( $post->ID ) ) )
		return false;

	$file = get_attached_file( $post->ID );

	if ( !empty($imagedata['thumb']) && ($thumbfile = str_replace(basename($file), $imagedata['thumb'], $file)) && file_exists($thumbfile) ) {
		return apply_filters( 'wp_get_attachment_thumb_file', $thumbfile, $post->ID );
	}
	return false;
}

function wp_get_attachment_thumb_url( $post_id = 0 ) {
	$post_id = (int) $post_id;
	if ( !$post = get_post( $post_id ) )
		return false;
	if ( !$url = wp_get_attachment_url( $post->ID ) )
		return false;

	$sized = image_downsize( $post_id, 'thumbnail' );
	if ( $sized )
		return $sized[0];

	if ( !$thumb = wp_get_attachment_thumb_file( $post->ID ) )
		return false;

	$url = str_replace(basename($url), basename($thumb), $url);

	return apply_filters( 'wp_get_attachment_thumb_url', $url, $post->ID );
}

function wp_attachment_is( $type, $post_id = 0 ) {
	if ( ! $post = get_post( $post_id ) ) {
		return false;
	}

	if ( ! $file = get_attached_file( $post->ID ) ) {
		return false;
	}

	if ( 0 === strpos( $post->post_mime_type, $type . '/' ) ) {
		return true;
	}

	$check = wp_check_filetype( $file );
	if ( empty( $check['ext'] ) ) {
		return false;
	}

	$ext = $check['ext'];

	if ( 'import' !== $post->post_mime_type ) {
		return $type === $ext;
	}

	switch ( $type ) {
	case 'image':
		$image_exts = array( 'jpg', 'jpeg', 'jpe', 'gif', 'png' );
		return in_array( $ext, $image_exts );

	case 'audio':
		return in_array( $ext, wp_get_audio_extensions() );

	case 'video':
		return in_array( $ext, wp_get_video_extensions() );

	default:
		return $type === $ext;
	}
}

function wp_attachment_is_image( $post = 0 ) {
	return wp_attachment_is( 'image', $post );
}

function wp_mime_type_icon( $mime = 0 ) {
	if ( !is_numeric($mime) )
		$icon = wp_cache_get("mime_type_icon_$mime");

	$post_id = 0;
	if ( empty($icon) ) {
		$post_mimes = array();
		if ( is_numeric($mime) ) {
			$mime = (int) $mime;
			if ( $post = get_post( $mime ) ) {
				$post_id = (int) $post->ID;
				$file = get_attached_file( $post_id );
				$ext = preg_replace('/^.+?\.([^.]+)$/', '$1', $file);
				if ( !empty($ext) ) {
					$post_mimes[] = $ext;
					if ( $ext_type = wp_ext2type( $ext ) )
						$post_mimes[] = $ext_type;
				}
				$mime = $post->post_mime_type;
			} else {
				$mime = 0;
			}
		} else {
			$post_mimes[] = $mime;
		}

		$icon_files = wp_cache_get('icon_files');

		if ( !is_array($icon_files) ) {
			$icon_dir = apply_filters( 'icon_dir', ABSPATH . WPINC . '/images/media' );

			$icon_dir_uri = apply_filters( 'icon_dir_uri', includes_url( 'images/media' ) );

			$dirs = apply_filters( 'icon_dirs', array( $icon_dir => $icon_dir_uri ) );
			$icon_files = array();
			while ( $dirs ) {
				$keys = array_keys( $dirs );
				$dir = array_shift( $keys );
				$uri = array_shift($dirs);
				if ( $dh = opendir($dir) ) {
					while ( false !== $file = readdir($dh) ) {
						$file = basename($file);
						if ( substr($file, 0, 1) == '.' )
							continue;
						if ( !in_array(strtolower(substr($file, -4)), array('.png', '.gif', '.jpg') ) ) {
							if ( is_dir("$dir/$file") )
								$dirs["$dir/$file"] = "$uri/$file";
							continue;
						}
						$icon_files["$dir/$file"] = "$uri/$file";
					}
					closedir($dh);
				}
			}
			wp_cache_add( 'icon_files', $icon_files, 'default', 600 );
		}

		$types = array();
		// Icon basename - extension = MIME wildcard.
		foreach ( $icon_files as $file => $uri )
			$types[ preg_replace('/^([^.]*).*$/', '$1', basename($file)) ] =& $icon_files[$file];

		if ( ! empty($mime) ) {
			$post_mimes[] = substr($mime, 0, strpos($mime, '/'));
			$post_mimes[] = substr($mime, strpos($mime, '/') + 1);
			$post_mimes[] = str_replace('/', '_', $mime);
		}

		$matches = wp_match_mime_types(array_keys($types), $post_mimes);
		$matches['default'] = array('default');

		foreach ( $matches as $match => $wilds ) {
			foreach ( $wilds as $wild ) {
				if ( ! isset( $types[ $wild ] ) ) {
					continue;
				}

				$icon = $types[ $wild ];
				if ( ! is_numeric( $mime ) ) {
					wp_cache_add( "mime_type_icon_$mime", $icon );
				}
				break 2;
			}
		}
	}

	return apply_filters( 'wp_mime_type_icon', $icon, $mime, $post_id );
}

function wp_check_for_changed_slugs( $post_id, $post, $post_before ) {
	// Don't bother if it hasn't changed.
	if ( $post->post_name == $post_before->post_name ) {
		return;
	}

	// We're only concerned with published, non-hierarchical objects.
	if ( ! ( 'publish' === $post->post_status || ( 'attachment' === get_post_type( $post ) && 'inherit' === $post->post_status ) ) || is_post_type_hierarchical( $post->post_type ) ) {
		return;
	}

	$old_slugs = (array) get_post_meta( $post_id, '_wp_old_slug' );

	// If we haven't added this old slug before, add it now.
	if ( ! empty( $post_before->post_name ) && ! in_array( $post_before->post_name, $old_slugs ) ) {
		add_post_meta( $post_id, '_wp_old_slug', $post_before->post_name );
	}

	// If the new slug was used previously, delete it from the list.
	if ( in_array( $post->post_name, $old_slugs ) ) {
		delete_post_meta( $post_id, '_wp_old_slug', $post->post_name );
	}
}

function get_private_posts_cap_sql( $post_type ) {
	return get_posts_by_author_sql( $post_type, false );
}

function get_posts_by_author_sql( $post_type, $full = true, $post_author = null, $public_only = false ) {
	global $wpdb;

	if ( is_array( $post_type ) ) {
		$post_types = $post_type;
	} else {
		$post_types = array( $post_type );
	}

	$post_type_clauses = array();
	foreach ( $post_types as $post_type ) {
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj ) {
			continue;
		}

		if ( ! $cap = apply_filters( 'pub_priv_sql_capability', '' ) ) {
			$cap = current_user_can( $post_type_obj->cap->read_private_posts );
		}

		// Only need to check the cap if $public_only is false.
		$post_status_sql = "post_status = 'publish'";
		if ( false === $public_only ) {
			if ( $cap ) {
				// Does the user have the capability to view private posts? Guess so.
				$post_status_sql .= " OR post_status = 'private'";
			} elseif ( is_user_logged_in() ) {
				// Users can view their own private posts.
				$id = get_current_user_id();
				if ( null === $post_author || ! $full ) {
					$post_status_sql .= " OR post_status = 'private' AND post_author = $id";
				} elseif ( $id == (int) $post_author ) {
					$post_status_sql .= " OR post_status = 'private'";
				} // else none
			} // else none
		}

		$post_type_clauses[] = "( post_type = '" . $post_type . "' AND ( $post_status_sql ) )";
	}

	if ( empty( $post_type_clauses ) ) {
		return $full ? 'WHERE 1 = 0' : '1 = 0';
	}

	$sql = '( '. implode( ' OR ', $post_type_clauses ) . ' )';

	if ( null !== $post_author ) {
		$sql .= $wpdb->prepare( ' AND post_author = %d', $post_author );
	}

	if ( $full ) {
		$sql = 'WHERE ' . $sql;
	}

	return $sql;
}

function get_lastpostdate( $timezone = 'server', $post_type = 'any' ) {
	return apply_filters( 'get_lastpostdate', _get_last_post_time( $timezone, 'date', $post_type ), $timezone );
}

function get_lastpostmodified( $timezone = 'server', $post_type = 'any' ) {

	$lastpostmodified = apply_filters( 'pre_get_lastpostmodified', false, $timezone, $post_type );
	if ( false !== $lastpostmodified ) {
		return $lastpostmodified;
	}

	$lastpostmodified = _get_last_post_time( $timezone, 'modified', $post_type );

	$lastpostdate = get_lastpostdate($timezone);
	if ( $lastpostdate > $lastpostmodified ) {
		$lastpostmodified = $lastpostdate;
	}

	return apply_filters( 'get_lastpostmodified', $lastpostmodified, $timezone );
}

function _get_last_post_time( $timezone, $field, $post_type = 'any' ) {
	global $wpdb;

	if ( ! in_array( $field, array( 'date', 'modified' ) ) ) {
		return false;
	}

	$timezone = strtolower( $timezone );

	$key = "lastpost{$field}:$timezone";
	if ( 'any' !== $post_type ) {
		$key .= ':' . sanitize_key( $post_type );
	}

	$date = wp_cache_get( $key, 'timeinfo' );

	if ( ! $date ) {
		if ( 'any' === $post_type ) {
			$post_types = get_post_types( array( 'public' => true ) );
			array_walk( $post_types, array( $wpdb, 'escape_by_ref' ) );
			$post_types = "'" . implode( "', '", $post_types ) . "'";
		} else {
			$post_types = "'" . sanitize_key( $post_type ) . "'";
		}

		switch ( $timezone ) {
			case 'gmt':
				$date = $wpdb->get_var("SELECT post_{$field}_gmt FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1");
				break;
			case 'blog':
				$date = $wpdb->get_var("SELECT post_{$field} FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1");
				break;
			case 'server':
				$add_seconds_server = date( 'Z' );
				$date = $wpdb->get_var("SELECT DATE_ADD(post_{$field}_gmt, INTERVAL '$add_seconds_server' SECOND) FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_{$field}_gmt DESC LIMIT 1");
				break;
		}

		if ( $date ) {
			wp_cache_set( $key, $date, 'timeinfo' );
		}
	}

	return $date;
}

function update_post_cache( &$posts ) {
	if ( ! $posts )
		return;

	foreach ( $posts as $post )
		wp_cache_add( $post->ID, $post, 'posts' );
}

function clean_post_cache( $post ) {
	global $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) )
		return;

	$post = get_post( $post );
	if ( empty( $post ) )
		return;

	wp_cache_delete( $post->ID, 'posts' );
	wp_cache_delete( $post->ID, 'post_meta' );

	clean_object_term_cache( $post->ID, $post->post_type );

	wp_cache_delete( 'wp_get_archives', 'general' );

	do_action( 'clean_post_cache', $post->ID, $post );

	if ( 'page' == $post->post_type ) {
		wp_cache_delete( 'all_page_ids', 'posts' );

		do_action( 'clean_page_cache', $post->ID );
	}

	wp_cache_set( 'last_changed', microtime(), 'posts' );
}

function update_post_caches( &$posts, $post_type = 'post', $update_term_cache = true, $update_meta_cache = true ) {
	// No point in doing all this work if we didn't match any posts.
	if ( !$posts )
		return;

	update_post_cache($posts);

	$post_ids = array();
	foreach ( $posts as $post )
		$post_ids[] = $post->ID;

	if ( ! $post_type )
		$post_type = 'any';

	if ( $update_term_cache ) {
		if ( is_array($post_type) ) {
			$ptypes = $post_type;
		} elseif ( 'any' == $post_type ) {
			$ptypes = array();
			// Just use the post_types in the supplied posts.
			foreach ( $posts as $post ) {
				$ptypes[] = $post->post_type;
			}
			$ptypes = array_unique($ptypes);
		} else {
			$ptypes = array($post_type);
		}

		if ( ! empty($ptypes) )
			update_object_term_cache($post_ids, $ptypes);
	}

	if ( $update_meta_cache )
		update_postmeta_cache($post_ids);
}

function update_postmeta_cache( $post_ids ) {
	return update_meta_cache('post', $post_ids);
}

function clean_attachment_cache( $id, $clean_terms = false ) {
	global $_wp_suspend_cache_invalidation;

	if ( !empty($_wp_suspend_cache_invalidation) )
		return;

	$id = (int) $id;

	wp_cache_delete($id, 'posts');
	wp_cache_delete($id, 'post_meta');

	if ( $clean_terms )
		clean_object_term_cache($id, 'attachment');

	do_action( 'clean_attachment_cache', $id );
}

function _transition_post_status( $new_status, $old_status, $post ) {
	global $wpdb;
	if ( $old_status != 'publish' && $new_status == 'publish' ) {
		if ( '' == get_the_guid($post->ID) )
			$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post->ID ) ), array( 'ID' => $post->ID ) );

		do_action('private_to_published', $post->ID);
	}
	if ( 'publish' == $new_status || 'publish' == $old_status) {
		foreach ( array( 'server', 'gmt', 'blog' ) as $timezone ) {
			wp_cache_delete( "lastpostmodified:$timezone", 'timeinfo' );
			wp_cache_delete( "lastpostdate:$timezone", 'timeinfo' );
			wp_cache_delete( "lastpostdate:$timezone:{$post->post_type}", 'timeinfo' );
		}
	}

	if ( $new_status !== $old_status ) {
		wp_cache_delete( _count_posts_cache_key( $post->post_type ), 'counts' );
		wp_cache_delete( _count_posts_cache_key( $post->post_type, 'readable' ), 'counts' );
	}
	wp_clear_scheduled_hook('publish_future_post', array( $post->ID ) );
}

function _publish_post_hook( $post_id ) {
	if ( defined('WP_IMPORTING') ) return;
	if ( get_option('default_pingback_flag') )
		add_post_meta( $post_id, '_pingme', '1' );
	add_post_meta( $post_id, '_encloseme', '1' );
	wp_schedule_single_event(time(), 'do_pings');
}

function wp_get_post_parent_id( $post_ID ) {
	$post = get_post( $post_ID );
	if ( !$post || is_wp_error( $post ) )
		return false;
	return (int) $post->post_parent;
}

function wp_check_post_hierarchy_for_loops( $post_parent, $post_ID ) {
	if ( !$post_parent )
		return 0;
	if ( empty( $post_ID ) )
		return $post_parent;
	if ( $post_parent == $post_ID )
		return 0;
	if ( !$loop = wp_find_hierarchy_loop( 'wp_get_post_parent_id', $post_ID, $post_parent ) )
		return $post_parent;
	if ( isset( $loop[$post_ID] ) )
		return 0;
	foreach ( array_keys( $loop ) as $loop_member )
		wp_update_post( array( 'ID' => $loop_member, 'post_parent' => 0 ) );

	return $post_parent;
}

function set_post_thumbnail( $post, $thumbnail_id ) {
	$post = get_post( $post );
	$thumbnail_id = absint( $thumbnail_id );
	if ( $post && $thumbnail_id && get_post( $thumbnail_id ) ) {
		if ( wp_get_attachment_image( $thumbnail_id, 'thumbnail' ) )
			return update_post_meta( $post->ID, '_thumbnail_id', $thumbnail_id );
		else
			return delete_post_meta( $post->ID, '_thumbnail_id' );
	}
	return false;
}

function delete_post_thumbnail( $post ) {
	$post = get_post( $post );
	if ( $post )
		return delete_post_meta( $post->ID, '_thumbnail_id' );
	return false;
}

function wp_delete_auto_drafts() {
	global $wpdb;
	$old_posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'auto-draft' AND DATE_SUB( NOW(), INTERVAL 7 DAY ) > post_date" );
	foreach ( (array) $old_posts as $delete ) {
		// Force delete.
		wp_delete_post( $delete, true );
	}
}

function wp_queue_posts_for_term_meta_lazyload( $posts ) {
	$post_type_taxonomies = $term_ids = array();
	foreach ( $posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}

		if ( ! isset( $post_type_taxonomies[ $post->post_type ] ) ) {
			$post_type_taxonomies[ $post->post_type ] = get_object_taxonomies( $post->post_type );
		}

		foreach ( $post_type_taxonomies[ $post->post_type ] as $taxonomy ) {
			// Term cache should already be primed by `update_post_term_cache()`.
			$terms = get_object_term_cache( $post->ID, $taxonomy );
			if ( false !== $terms ) {
				foreach ( $terms as $term ) {
					if ( ! isset( $term_ids[ $term->term_id ] ) ) {
						$term_ids[] = $term->term_id;
					}
				}
			}
		}
	}

	if ( $term_ids ) {
		$lazyloader = wp_metadata_lazyloader();
		$lazyloader->queue_objects( 'term', $term_ids );
	}
}

function _update_term_count_on_transition_post_status( $new_status, $old_status, $post ) {
	// Update counts for the post's terms.
	foreach ( (array) get_object_taxonomies( $post->post_type ) as $taxonomy ) {
		$tt_ids = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'tt_ids' ) );
		wp_update_term_count( $tt_ids, $taxonomy );
	}
}

function _prime_post_caches( $ids, $update_term_cache = true, $update_meta_cache = true ) {
	global $wpdb;

	$non_cached_ids = _get_non_cached_ids( $ids, 'posts' );
	if ( !empty( $non_cached_ids ) ) {
		$fresh_posts = $wpdb->get_results( sprintf( "SELECT $wpdb->posts.* FROM $wpdb->posts WHERE ID IN (%s)", join( ",", $non_cached_ids ) ) );

		update_post_caches( $fresh_posts, 'any', $update_term_cache, $update_meta_cache );
	}
}

function wp_add_trashed_suffix_to_post_name_for_trashed_posts( $post_name, $post_ID = 0 ) {
	$trashed_posts_with_desired_slug = get_posts( array(
		'name' => $post_name,
		'post_status' => 'trash',
		'post_type' => 'any',
		'nopaging' => true,
		'post__not_in' => array( $post_ID )
	) );

	if ( ! empty( $trashed_posts_with_desired_slug ) ) {
		foreach ( $trashed_posts_with_desired_slug as $_post ) {
			wp_add_trashed_suffix_to_post_name_for_post( $_post );
		}
	}
}

function wp_add_trashed_suffix_to_post_name_for_post( $post ) {
	global $wpdb;

	$post = get_post( $post );

	if ( '__trashed' === substr( $post->post_name, -9 ) ) {
		return $post->post_name;
	}
	add_post_meta( $post->ID, '_wp_desired_post_slug', $post->post_name );
	$post_name = _truncate_post_slug( $post->post_name, 191 ) . '__trashed';
	$wpdb->update( $wpdb->posts, array( 'post_name' => $post_name ), array( 'ID' => $post->ID ) );
	clean_post_cache( $post->ID );
	return $post_name;
}
