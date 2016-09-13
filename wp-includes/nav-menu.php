<?php
/**
 * Navigation Menu functions
 *
 * @package WordPress
 * @subpackage Nav_Menus
 * @since 3.0.0
 */

function wp_get_nav_menu_object( $menu ) {
	$menu_obj = false;
	if ( is_object( $menu ) ) {
		$menu_obj = $menu;
	}

	if ( $menu && ! $menu_obj ) {
		$menu_obj = get_term( $menu, 'nav_menu' );

		if ( ! $menu_obj ) {
			$menu_obj = get_term_by( 'slug', $menu, 'nav_menu' );
		}

		if ( ! $menu_obj ) {
			$menu_obj = get_term_by( 'name', $menu, 'nav_menu' );
		}
	}

	if ( ! $menu_obj || is_wp_error( $menu_obj ) ) {
		$menu_obj = false;
	}

	return apply_filters( 'wp_get_nav_menu_object', $menu_obj, $menu );
}

function is_nav_menu( $menu ) {
	if ( ! $menu )
		return false;

	$menu_obj = wp_get_nav_menu_object( $menu );

	if (
		$menu_obj &&
		! is_wp_error( $menu_obj ) &&
		! empty( $menu_obj->taxonomy ) &&
		'nav_menu' == $menu_obj->taxonomy
	)
		return true;

	return false;
}

function register_nav_menus( $locations = array() ) {
	global $_wp_registered_nav_menus;

	add_theme_support( 'menus' );

	$_wp_registered_nav_menus = array_merge( (array) $_wp_registered_nav_menus, $locations );
}

function unregister_nav_menu( $location ) {
	global $_wp_registered_nav_menus;

	if ( is_array( $_wp_registered_nav_menus ) && isset( $_wp_registered_nav_menus[$location] ) ) {
		unset( $_wp_registered_nav_menus[$location] );
		if ( empty( $_wp_registered_nav_menus ) ) {
			_remove_theme_support( 'menus' );
		}
		return true;
	}
	return false;
}

function register_nav_menu( $location, $description ) {
	register_nav_menus( array( $location => $description ) );
}

function get_registered_nav_menus() {
	global $_wp_registered_nav_menus;
	if ( isset( $_wp_registered_nav_menus ) )
		return $_wp_registered_nav_menus;
	return array();
}

function get_nav_menu_locations() {
	$locations = get_theme_mod( 'nav_menu_locations' );
	return ( is_array( $locations ) ) ? $locations : array();
}

function has_nav_menu( $location ) {
	$has_nav_menu = false;

	$registered_nav_menus = get_registered_nav_menus();
	if ( isset( $registered_nav_menus[ $location ] ) ) {
		$locations = get_nav_menu_locations();
		$has_nav_menu = ! empty( $locations[ $location ] );
	}

	return apply_filters( 'has_nav_menu', $has_nav_menu, $location );
}

function is_nav_menu_item( $menu_item_id = 0 ) {
	return ( ! is_wp_error( $menu_item_id ) && ( 'nav_menu_item' == get_post_type( $menu_item_id ) ) );
}

function wp_create_nav_menu( $menu_name ) {
	return wp_update_nav_menu_object( 0, array( 'menu-name' => $menu_name ) );
}

function wp_delete_nav_menu( $menu ) {
	$menu = wp_get_nav_menu_object( $menu );
	if ( ! $menu )
		return false;

	$menu_objects = get_objects_in_term( $menu->term_id, 'nav_menu' );
	if ( ! empty( $menu_objects ) ) {
		foreach ( $menu_objects as $item ) {
			wp_delete_post( $item );
		}
	}

	$result = wp_delete_term( $menu->term_id, 'nav_menu' );

	// Remove this menu from any locations.
	$locations = get_nav_menu_locations();
	foreach ( $locations as $location => $menu_id ) {
		if ( $menu_id == $menu->term_id )
			$locations[ $location ] = 0;
	}
	set_theme_mod( 'nav_menu_locations', $locations );

	if ( $result && !is_wp_error($result) )

		/**
		 * Fires after a navigation menu has been successfully deleted.
		 *
		 * @since 3.0.0
		 *
		 * @param int $term_id ID of the deleted menu.
		 */
		do_action( 'wp_delete_nav_menu', $menu->term_id );

	return $result;
}

function wp_update_nav_menu_object( $menu_id = 0, $menu_data = array() ) {
	// expected_slashed ($menu_data)
	$menu_id = (int) $menu_id;

	$_menu = wp_get_nav_menu_object( $menu_id );

	$args = array(
		'description' => ( isset( $menu_data['description'] ) ? $menu_data['description']  : '' ),
		'name'        => ( isset( $menu_data['menu-name']   ) ? $menu_data['menu-name']    : '' ),
		'parent'      => ( isset( $menu_data['parent']      ) ? (int) $menu_data['parent'] : 0  ),
		'slug'        => null,
	);

	// double-check that we're not going to have one menu take the name of another
	$_possible_existing = get_term_by( 'name', $menu_data['menu-name'], 'nav_menu' );
	if (
		$_possible_existing &&
		! is_wp_error( $_possible_existing ) &&
		isset( $_possible_existing->term_id ) &&
		$_possible_existing->term_id != $menu_id
	) {
		return new WP_Error( 'menu_exists',
			/* translators: %s: menu name */
			sprintf( __( 'The menu name %s conflicts with another menu name. Please try another.' ),
				'<strong>' . esc_html( $menu_data['menu-name'] ) . '</strong>'
			)
		);
	}

	// menu doesn't already exist, so create a new menu
	if ( ! $_menu || is_wp_error( $_menu ) ) {
		$menu_exists = get_term_by( 'name', $menu_data['menu-name'], 'nav_menu' );

		if ( $menu_exists ) {
			return new WP_Error( 'menu_exists',
				/* translators: %s: menu name */
				sprintf( __( 'The menu name %s conflicts with another menu name. Please try another.' ),
					'<strong>' . esc_html( $menu_data['menu-name'] ) . '</strong>'
				)
			);
		}

		$_menu = wp_insert_term( $menu_data['menu-name'], 'nav_menu', $args );

		if ( is_wp_error( $_menu ) )
			return $_menu;

		/**
		 * Fires after a navigation menu is successfully created.
		 *
		 * @since 3.0.0
		 *
		 * @param int   $term_id   ID of the new menu.
		 * @param array $menu_data An array of menu data.
		 */
		do_action( 'wp_create_nav_menu', $_menu['term_id'], $menu_data );

		return (int) $_menu['term_id'];
	}

	if ( ! $_menu || ! isset( $_menu->term_id ) )
		return 0;

	$menu_id = (int) $_menu->term_id;

	$update_response = wp_update_term( $menu_id, 'nav_menu', $args );

	if ( is_wp_error( $update_response ) )
		return $update_response;

	$menu_id = (int) $update_response['term_id'];

	/**
	 * Fires after a navigation menu has been successfully updated.
	 *
	 * @since 3.0.0
	 *
	 * @param int   $menu_id   ID of the updated menu.
	 * @param array $menu_data An array of menu data.
	 */
	do_action( 'wp_update_nav_menu', $menu_id, $menu_data );
	return $menu_id;
}

function wp_update_nav_menu_item( $menu_id = 0, $menu_item_db_id = 0, $menu_item_data = array() ) {
	$menu_id = (int) $menu_id;
	$menu_item_db_id = (int) $menu_item_db_id;

	// make sure that we don't convert non-nav_menu_item objects into nav_menu_item objects
	if ( ! empty( $menu_item_db_id ) && ! is_nav_menu_item( $menu_item_db_id ) )
		return new WP_Error( 'update_nav_menu_item_failed', __( 'The given object ID is not that of a menu item.' ) );

	$menu = wp_get_nav_menu_object( $menu_id );

	if ( ! $menu && 0 !== $menu_id ) {
		return new WP_Error( 'invalid_menu_id', __( 'Invalid menu ID.' ) );
	}

	if ( is_wp_error( $menu ) ) {
		return $menu;
	}

	$defaults = array(
		'menu-item-db-id' => $menu_item_db_id,
		'menu-item-object-id' => 0,
		'menu-item-object' => '',
		'menu-item-parent-id' => 0,
		'menu-item-position' => 0,
		'menu-item-type' => 'custom',
		'menu-item-title' => '',
		'menu-item-url' => '',
		'menu-item-description' => '',
		'menu-item-attr-title' => '',
		'menu-item-target' => '',
		'menu-item-classes' => '',
		'menu-item-xfn' => '',
		'menu-item-status' => '',
	);

	$args = wp_parse_args( $menu_item_data, $defaults );

	if ( 0 == $menu_id ) {
		$args['menu-item-position'] = 1;
	} elseif ( 0 == (int) $args['menu-item-position'] ) {
		$menu_items = 0 == $menu_id ? array() : (array) wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );
		$last_item = array_pop( $menu_items );
		$args['menu-item-position'] = ( $last_item && isset( $last_item->menu_order ) ) ? 1 + $last_item->menu_order : count( $menu_items );
	}

	$original_parent = 0 < $menu_item_db_id ? get_post_field( 'post_parent', $menu_item_db_id ) : 0;

	if ( 'custom' != $args['menu-item-type'] ) {
		/* if non-custom menu item, then:
			* use original object's URL
			* blank default title to sync with original object's
		*/

		$args['menu-item-url'] = '';

		$original_title = '';
		if ( 'taxonomy' == $args['menu-item-type'] ) {
			$original_parent = get_term_field( 'parent', $args['menu-item-object-id'], $args['menu-item-object'], 'raw' );
			$original_title = get_term_field( 'name', $args['menu-item-object-id'], $args['menu-item-object'], 'raw' );
		} elseif ( 'post_type' == $args['menu-item-type'] ) {

			$original_object = get_post( $args['menu-item-object-id'] );
			$original_parent = (int) $original_object->post_parent;
			$original_title = $original_object->post_title;
		} elseif ( 'post_type_archive' == $args['menu-item-type'] ) {
			$original_object = get_post_type_object( $args['menu-item-object'] );
			if ( $original_object ) {
				$original_title = $original_object->labels->archives;
			}
		}

		if ( $args['menu-item-title'] == $original_title )
			$args['menu-item-title'] = '';

		// hack to get wp to create a post object when too many properties are empty
		if ( '' ==  $args['menu-item-title'] && '' == $args['menu-item-description'] )
			$args['menu-item-description'] = ' ';
	}

	// Populate the menu item object
	$post = array(
		'menu_order' => $args['menu-item-position'],
		'ping_status' => 0,
		'post_content' => $args['menu-item-description'],
		'post_excerpt' => $args['menu-item-attr-title'],
		'post_parent' => $original_parent,
		'post_title' => $args['menu-item-title'],
		'post_type' => 'nav_menu_item',
	);

	$update = 0 != $menu_item_db_id;

	// New menu item. Default is draft status
	if ( ! $update ) {
		$post['ID'] = 0;
		$post['post_status'] = 'publish' == $args['menu-item-status'] ? 'publish' : 'draft';
		$menu_item_db_id = wp_insert_post( $post );
		if ( ! $menu_item_db_id	|| is_wp_error( $menu_item_db_id ) )
			return $menu_item_db_id;

		/**
		 * Fires immediately after a new navigation menu item has been added.
		 *
		 * @since 4.4.0
		 *
		 * @see wp_update_nav_menu_item()
		 *
		 * @param int   $menu_id         ID of the updated menu.
		 * @param int   $menu_item_db_id ID of the new menu item.
		 * @param array $args            An array of arguments used to update/add the menu item.
		 */
		do_action( 'wp_add_nav_menu_item', $menu_id, $menu_item_db_id, $args );
	}

	// Associate the menu item with the menu term
	// Only set the menu term if it isn't set to avoid unnecessary wp_get_object_terms()
	 if ( $menu_id && ( ! $update || ! is_object_in_term( $menu_item_db_id, 'nav_menu', (int) $menu->term_id ) ) ) {
		wp_set_object_terms( $menu_item_db_id, array( $menu->term_id ), 'nav_menu' );
	}

	if ( 'custom' == $args['menu-item-type'] ) {
		$args['menu-item-object-id'] = $menu_item_db_id;
		$args['menu-item-object'] = 'custom';
	}

	$menu_item_db_id = (int) $menu_item_db_id;

	update_post_meta( $menu_item_db_id, '_menu_item_type', sanitize_key($args['menu-item-type']) );
	update_post_meta( $menu_item_db_id, '_menu_item_menu_item_parent', strval( (int) $args['menu-item-parent-id'] ) );
	update_post_meta( $menu_item_db_id, '_menu_item_object_id', strval( (int) $args['menu-item-object-id'] ) );
	update_post_meta( $menu_item_db_id, '_menu_item_object', sanitize_key($args['menu-item-object']) );
	update_post_meta( $menu_item_db_id, '_menu_item_target', sanitize_key($args['menu-item-target']) );

	$args['menu-item-classes'] = array_map( 'sanitize_html_class', explode( ' ', $args['menu-item-classes'] ) );
	$args['menu-item-xfn'] = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', $args['menu-item-xfn'] ) ) );
	update_post_meta( $menu_item_db_id, '_menu_item_classes', $args['menu-item-classes'] );
	update_post_meta( $menu_item_db_id, '_menu_item_xfn', $args['menu-item-xfn'] );
	update_post_meta( $menu_item_db_id, '_menu_item_url', esc_url_raw($args['menu-item-url']) );

	if ( 0 == $menu_id )
		update_post_meta( $menu_item_db_id, '_menu_item_orphaned', (string) time() );
	elseif ( get_post_meta( $menu_item_db_id, '_menu_item_orphaned' ) )
		delete_post_meta( $menu_item_db_id, '_menu_item_orphaned' );

	// Update existing menu item. Default is publish status
	if ( $update ) {
		$post['ID'] = $menu_item_db_id;
		$post['post_status'] = 'draft' == $args['menu-item-status'] ? 'draft' : 'publish';
		wp_update_post( $post );
	}

	/**
	 * Fires after a navigation menu item has been updated.
	 *
	 * @since 3.0.0
	 *
	 * @see wp_update_nav_menu_item()
	 *
	 * @param int   $menu_id         ID of the updated menu.
	 * @param int   $menu_item_db_id ID of the updated menu item.
	 * @param array $args            An array of arguments used to update a menu item.
	 */
	do_action( 'wp_update_nav_menu_item', $menu_id, $menu_item_db_id, $args );

	return $menu_item_db_id;
}

function wp_get_nav_menus( $args = array() ) {
	$defaults = array( 'hide_empty' => false, 'orderby' => 'name' );
	$args = wp_parse_args( $args, $defaults );

	return apply_filters( 'wp_get_nav_menus', get_terms( 'nav_menu',  $args), $args );
}

function _sort_nav_menu_items( $a, $b ) {
	global $_menu_item_sort_prop;

	if ( empty( $_menu_item_sort_prop ) )
		return 0;

	if ( ! isset( $a->$_menu_item_sort_prop ) || ! isset( $b->$_menu_item_sort_prop ) )
		return 0;

	$_a = (int) $a->$_menu_item_sort_prop;
	$_b = (int) $b->$_menu_item_sort_prop;

	if ( $a->$_menu_item_sort_prop == $b->$_menu_item_sort_prop )
		return 0;
	elseif ( $_a == $a->$_menu_item_sort_prop && $_b == $b->$_menu_item_sort_prop )
		return $_a < $_b ? -1 : 1;
	else
		return strcmp( $a->$_menu_item_sort_prop, $b->$_menu_item_sort_prop );
}

function _is_valid_nav_menu_item( $item ) {
	return empty( $item->_invalid );
}

function wp_get_nav_menu_items( $menu, $args = array() ) {
	$menu = wp_get_nav_menu_object( $menu );

	if ( ! $menu ) {
		return false;
	}

	static $fetched = array();

	$items = get_objects_in_term( $menu->term_id, 'nav_menu' );
	if ( is_wp_error( $items ) ) {
		return false;
	}

	$defaults = array( 'order' => 'ASC', 'orderby' => 'menu_order', 'post_type' => 'nav_menu_item',
		'post_status' => 'publish', 'output' => ARRAY_A, 'output_key' => 'menu_order', 'nopaging' => true );
	$args = wp_parse_args( $args, $defaults );
	$args['include'] = $items;

	if ( ! empty( $items ) ) {
		$items = get_posts( $args );
	} else {
		$items = array();
	}

	// Get all posts and terms at once to prime the caches
	if ( empty( $fetched[$menu->term_id] ) || wp_using_ext_object_cache() ) {
		$fetched[$menu->term_id] = true;
		$posts = array();
		$terms = array();
		foreach ( $items as $item ) {
			$object_id = get_post_meta( $item->ID, '_menu_item_object_id', true );
			$object    = get_post_meta( $item->ID, '_menu_item_object',    true );
			$type      = get_post_meta( $item->ID, '_menu_item_type',      true );

			if ( 'post_type' == $type )
				$posts[$object][] = $object_id;
			elseif ( 'taxonomy' == $type)
				$terms[$object][] = $object_id;
		}

		if ( ! empty( $posts ) ) {
			foreach ( array_keys($posts) as $post_type ) {
				get_posts( array('post__in' => $posts[$post_type], 'post_type' => $post_type, 'nopaging' => true, 'update_post_term_cache' => false) );
			}
		}
		unset($posts);

		if ( ! empty( $terms ) ) {
			foreach ( array_keys($terms) as $taxonomy ) {
				get_terms( $taxonomy, array(
					'include' => $terms[ $taxonomy ],
					'hierarchical' => false,
				) );
			}
		}
		unset($terms);
	}

	$items = array_map( 'wp_setup_nav_menu_item', $items );

	if ( ! is_admin() ) { // Remove invalid items only in front end
		$items = array_filter( $items, '_is_valid_nav_menu_item' );
	}

	if ( ARRAY_A == $args['output'] ) {
		$GLOBALS['_menu_item_sort_prop'] = $args['output_key'];
		usort($items, '_sort_nav_menu_items');
		$i = 1;
		foreach ( $items as $k => $item ) {
			$items[$k]->{$args['output_key']} = $i++;
		}
	}

	/**
	 * Filter the navigation menu items being returned.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $items An array of menu item post objects.
	 * @param object $menu  The menu object.
	 * @param array  $args  An array of arguments used to retrieve menu item objects.
	 */
	return apply_filters( 'wp_get_nav_menu_items', $items, $menu, $args );
}

function wp_setup_nav_menu_item( $menu_item ) {
	if ( isset( $menu_item->post_type ) ) {
		if ( 'nav_menu_item' == $menu_item->post_type ) {
			$menu_item->db_id = (int) $menu_item->ID;
			$menu_item->menu_item_parent = ! isset( $menu_item->menu_item_parent ) ? get_post_meta( $menu_item->ID, '_menu_item_menu_item_parent', true ) : $menu_item->menu_item_parent;
			$menu_item->object_id = ! isset( $menu_item->object_id ) ? get_post_meta( $menu_item->ID, '_menu_item_object_id', true ) : $menu_item->object_id;
			$menu_item->object = ! isset( $menu_item->object ) ? get_post_meta( $menu_item->ID, '_menu_item_object', true ) : $menu_item->object;
			$menu_item->type = ! isset( $menu_item->type ) ? get_post_meta( $menu_item->ID, '_menu_item_type', true ) : $menu_item->type;

			if ( 'post_type' == $menu_item->type ) {
				$object = get_post_type_object( $menu_item->object );
				if ( $object ) {
					$menu_item->type_label = $object->labels->singular_name;
				} else {
					$menu_item->type_label = $menu_item->object;
					$menu_item->_invalid = true;
				}

				$menu_item->url = get_permalink( $menu_item->object_id );

				$original_object = get_post( $menu_item->object_id );
				/** This filter is documented in wp-includes/post-template.php */
				$original_title = apply_filters( 'the_title', $original_object->post_title, $original_object->ID );

				if ( '' === $original_title ) {
					/* translators: %d: ID of a post */
					$original_title = sprintf( __( '#%d (no title)' ), $original_object->ID );
				}

				$menu_item->title = '' == $menu_item->post_title ? $original_title : $menu_item->post_title;

			} elseif ( 'post_type_archive' == $menu_item->type ) {
				$object =  get_post_type_object( $menu_item->object );
				if ( $object ) {
					$menu_item->title = '' == $menu_item->post_title ? $object->labels->archives : $menu_item->post_title;
					$post_type_description = $object->description;
				} else {
					$menu_item->_invalid = true;
					$post_type_description = '';
				}

				$menu_item->type_label = __( 'Post Type Archive' );
				$post_content = wp_trim_words( $menu_item->post_content, 200 );
				$post_type_description = '' == $post_content ? $post_type_description : $post_content; 
				$menu_item->url = get_post_type_archive_link( $menu_item->object );
			} elseif ( 'taxonomy' == $menu_item->type ) {
				$object = get_taxonomy( $menu_item->object );
				if ( $object ) {
					$menu_item->type_label = $object->labels->singular_name;
				} else {
					$menu_item->type_label = $menu_item->object;
					$menu_item->_invalid = true;
				}

				$term_url = get_term_link( (int) $menu_item->object_id, $menu_item->object );
				$menu_item->url = !is_wp_error( $term_url ) ? $term_url : '';

				$original_title = get_term_field( 'name', $menu_item->object_id, $menu_item->object, 'raw' );
				if ( is_wp_error( $original_title ) )
					$original_title = false;
				$menu_item->title = '' == $menu_item->post_title ? $original_title : $menu_item->post_title;

			} else {
				$menu_item->type_label = __('Custom Link');
				$menu_item->title = $menu_item->post_title;
				$menu_item->url = ! isset( $menu_item->url ) ? get_post_meta( $menu_item->ID, '_menu_item_url', true ) : $menu_item->url;
			}

			$menu_item->target = ! isset( $menu_item->target ) ? get_post_meta( $menu_item->ID, '_menu_item_target', true ) : $menu_item->target;

			/**
			 * Filter a navigation menu item's title attribute.
			 *
			 * @since 3.0.0
			 *
			 * @param string $item_title The menu item title attribute.
			 */
			$menu_item->attr_title = ! isset( $menu_item->attr_title ) ? apply_filters( 'nav_menu_attr_title', $menu_item->post_excerpt ) : $menu_item->attr_title;

			if ( ! isset( $menu_item->description ) ) {
				/**
				 * Filter a navigation menu item's description.
				 *
				 * @since 3.0.0
				 *
				 * @param string $description The menu item description.
				 */
				$menu_item->description = apply_filters( 'nav_menu_description', wp_trim_words( $menu_item->post_content, 200 ) );
			}

			$menu_item->classes = ! isset( $menu_item->classes ) ? (array) get_post_meta( $menu_item->ID, '_menu_item_classes', true ) : $menu_item->classes;
			$menu_item->xfn = ! isset( $menu_item->xfn ) ? get_post_meta( $menu_item->ID, '_menu_item_xfn', true ) : $menu_item->xfn;
		} else {
			$menu_item->db_id = 0;
			$menu_item->menu_item_parent = 0;
			$menu_item->object_id = (int) $menu_item->ID;
			$menu_item->type = 'post_type';

			$object = get_post_type_object( $menu_item->post_type );
			$menu_item->object = $object->name;
			$menu_item->type_label = $object->labels->singular_name;

			if ( '' === $menu_item->post_title ) {
				/* translators: %d: ID of a post */
				$menu_item->post_title = sprintf( __( '#%d (no title)' ), $menu_item->ID );
			}

			$menu_item->title = $menu_item->post_title;
			$menu_item->url = get_permalink( $menu_item->ID );
			$menu_item->target = '';

			/** This filter is documented in wp-includes/nav-menu.php */
			$menu_item->attr_title = apply_filters( 'nav_menu_attr_title', '' );

			/** This filter is documented in wp-includes/nav-menu.php */
			$menu_item->description = apply_filters( 'nav_menu_description', '' );
			$menu_item->classes = array();
			$menu_item->xfn = '';
		}
	} elseif ( isset( $menu_item->taxonomy ) ) {
		$menu_item->ID = $menu_item->term_id;
		$menu_item->db_id = 0;
		$menu_item->menu_item_parent = 0;
		$menu_item->object_id = (int) $menu_item->term_id;
		$menu_item->post_parent = (int) $menu_item->parent;
		$menu_item->type = 'taxonomy';

		$object = get_taxonomy( $menu_item->taxonomy );
		$menu_item->object = $object->name;
		$menu_item->type_label = $object->labels->singular_name;

		$menu_item->title = $menu_item->name;
		$menu_item->url = get_term_link( $menu_item, $menu_item->taxonomy );
		$menu_item->target = '';
		$menu_item->attr_title = '';
		$menu_item->description = get_term_field( 'description', $menu_item->term_id, $menu_item->taxonomy );
		$menu_item->classes = array();
		$menu_item->xfn = '';

	}

	return apply_filters( 'wp_setup_nav_menu_item', $menu_item );
}

function wp_get_associated_nav_menu_items( $object_id = 0, $object_type = 'post_type', $taxonomy = '' ) {
	$object_id = (int) $object_id;
	$menu_item_ids = array();

	$query = new WP_Query;
	$menu_items = $query->query(
		array(
			'meta_key' => '_menu_item_object_id',
			'meta_value' => $object_id,
			'post_status' => 'any',
			'post_type' => 'nav_menu_item',
			'posts_per_page' => -1,
		)
	);
	foreach ( (array) $menu_items as $menu_item ) {
		if ( isset( $menu_item->ID ) && is_nav_menu_item( $menu_item->ID ) ) {
			$menu_item_type = get_post_meta( $menu_item->ID, '_menu_item_type', true );
			if (
				'post_type' == $object_type &&
				'post_type' == $menu_item_type
			) {
				$menu_item_ids[] = (int) $menu_item->ID;
			} elseif (
				'taxonomy' == $object_type &&
				'taxonomy' == $menu_item_type &&
				get_post_meta( $menu_item->ID, '_menu_item_object', true ) == $taxonomy
			) {
				$menu_item_ids[] = (int) $menu_item->ID;
			}
		}
	}

	return array_unique( $menu_item_ids );
}

/**
 * Callback for handling a menu item when its original object is deleted.
 *
 * @since 3.0.0
 * @access private
 *
 * @param int $object_id The ID of the original object being trashed.
 *
 */
function _wp_delete_post_menu_item( $object_id = 0 ) {
	$object_id = (int) $object_id;

	$menu_item_ids = wp_get_associated_nav_menu_items( $object_id, 'post_type' );

	foreach ( (array) $menu_item_ids as $menu_item_id ) {
		wp_delete_post( $menu_item_id, true );
	}
}

function _wp_delete_tax_menu_item( $object_id = 0, $tt_id, $taxonomy ) {
	$object_id = (int) $object_id;

	$menu_item_ids = wp_get_associated_nav_menu_items( $object_id, 'taxonomy', $taxonomy );

	foreach ( (array) $menu_item_ids as $menu_item_id ) {
		wp_delete_post( $menu_item_id, true );
	}
}

function _wp_auto_add_pages_to_menu( $new_status, $old_status, $post ) {
	if ( 'publish' != $new_status || 'publish' == $old_status || 'page' != $post->post_type )
		return;
	if ( ! empty( $post->post_parent ) )
		return;
	$auto_add = get_option( 'nav_menu_options' );
	if ( empty( $auto_add ) || ! is_array( $auto_add ) || ! isset( $auto_add['auto_add'] ) )
		return;
	$auto_add = $auto_add['auto_add'];
	if ( empty( $auto_add ) || ! is_array( $auto_add ) )
		return;

	$args = array(
		'menu-item-object-id' => $post->ID,
		'menu-item-object' => $post->post_type,
		'menu-item-type' => 'post_type',
		'menu-item-status' => 'publish',
	);

	foreach ( $auto_add as $menu_id ) {
		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );
		if ( ! is_array( $items ) )
			continue;
		foreach ( $items as $item ) {
			if ( $post->ID == $item->object_id )
				continue 2;
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}
}
