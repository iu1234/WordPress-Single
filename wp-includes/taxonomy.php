<?php

function create_initial_taxonomies() {
	global $wp_rewrite;
	if ( ! did_action( 'init' ) ) {
		$rewrite = array( 'category' => false, 'post_tag' => false, 'post_format' => false );
	} else {
		$post_format_base = apply_filters( 'post_format_rewrite_base', 'type' );
		$rewrite = array(
			'category' => array(
				'hierarchical' => true,
				'slug' => get_option('category_base') ? get_option('category_base') : 'category',
				'with_front' => ! get_option('category_base') || $wp_rewrite->using_index_permalinks(),
				'ep_mask' => EP_CATEGORIES,
			),
			'post_tag' => array(
				'hierarchical' => false,
				'slug' => get_option('tag_base') ? get_option('tag_base') : 'tag',
				'with_front' => ! get_option('tag_base') || $wp_rewrite->using_index_permalinks(),
				'ep_mask' => EP_TAGS,
			),
			'post_format' => $post_format_base ? array( 'slug' => $post_format_base ) : false,
		);
	}
	register_taxonomy( 'category', 'post', array(
		'hierarchical' => true,
		'query_var' => 'category_name',
		'rewrite' => $rewrite['category'],
		'public' => true,
		'show_ui' => true,
		'show_admin_column' => true,
		'_builtin' => true,
	) );
	register_taxonomy( 'post_tag', 'post', array(
	 	'hierarchical' => false,
		'query_var' => 'tag',
		'rewrite' => $rewrite['post_tag'],
		'public' => true,
		'show_ui' => true,
		'show_admin_column' => true,
		'_builtin' => true,
	) );
	register_taxonomy( 'nav_menu', 'nav_menu_item', array(
		'public' => false,
		'hierarchical' => false,
		'labels' => array(
			'name' => 'Navigation Menus',
			'singular_name' => 'Navigation Menu',
		),
		'query_var' => false,
		'rewrite' => false,
		'show_ui' => false,
		'_builtin' => true,
		'show_in_nav_menus' => false,
	) );
	register_taxonomy( 'link_category', 'link', array(
		'hierarchical' => false,
		'labels' => array(
			'name' => 'Link Categories',
			'singular_name' => 'Link Category',
			'search_items' => 'Search Link Categories',
			'popular_items' => null,
			'all_items' => 'All Link Categories',
			'edit_item' => 'Edit Link Category',
			'update_item' => 'Update Link Category',
			'add_new_item' => 'Add New Link Category',
			'new_item_name' => 'New Link Category Name',
			'separate_items_with_commas' => null,
			'add_or_remove_items' => null,
			'choose_from_most_used' => null,
		),
		'capabilities' => array(
			'manage_terms' => 'manage_links',
			'edit_terms'   => 'manage_links',
			'delete_terms' => 'manage_links',
			'assign_terms' => 'manage_links',
		),
		'query_var' => false,
		'rewrite' => false,
		'public' => false,
		'show_ui' => true,
		'_builtin' => true,
	) );
	register_taxonomy( 'post_format', 'post', array(
		'public' => true,
		'hierarchical' => false,
		'labels' => array(
			'name' => 'Format',
			'singular_name' => 'Format',
		),
		'query_var' => true,
		'rewrite' => $rewrite['post_format'],
		'show_ui' => false,
		'_builtin' => true,
		'show_in_nav_menus' => current_theme_supports( 'post-formats' ),
	) );
}

function get_taxonomies( $args = array(), $output = 'names', $operator = 'and' ) {
	global $wp_taxonomies;
	$field = ('names' == $output) ? 'name' : false;
	return wp_filter_object_list($wp_taxonomies, $args, $operator, $field);
}

function get_object_taxonomies( $object, $output = 'names' ) {
	global $wp_taxonomies;
	if ( is_object($object) ) {
		if ( $object->post_type == 'attachment' )
			return get_attachment_taxonomies($object);
		$object = $object->post_type;
	}

	$object = (array) $object;

	$taxonomies = array();
	foreach ( (array) $wp_taxonomies as $tax_name => $tax_obj ) {
		if ( array_intersect($object, (array) $tax_obj->object_type) ) {
			if ( 'names' == $output )
				$taxonomies[] = $tax_name;
			else
				$taxonomies[ $tax_name ] = $tax_obj;
		}
	}

	return $taxonomies;
}

function get_taxonomy( $taxonomy ) {
	global $wp_taxonomies;
	if ( ! taxonomy_exists( $taxonomy ) )
		return false;
	return $wp_taxonomies[$taxonomy];
}

function taxonomy_exists( $taxonomy ) {
	global $wp_taxonomies;
	return isset( $wp_taxonomies[$taxonomy] );
}

function is_taxonomy_hierarchical($taxonomy) {
	if ( ! taxonomy_exists($taxonomy) )
		return false;
	$taxonomy = get_taxonomy($taxonomy);
	return $taxonomy->hierarchical;
}

function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
	global $wp_taxonomies, $wp;
	if ( ! is_array( $wp_taxonomies ) )
		$wp_taxonomies = array();
	$args = wp_parse_args( $args );
	$args = apply_filters( 'register_taxonomy_args', $args, $taxonomy, (array) $object_type );
	$defaults = array(
		'labels'                => array(),
		'description'           => '',
		'public'                => true,
		'publicly_queryable'    => null,
		'hierarchical'          => false,
		'show_ui'               => null,
		'show_in_menu'          => null,
		'show_in_nav_menus'     => null,
		'show_tagcloud'         => null,
		'show_in_quick_edit'	=> null,
		'show_admin_column'     => false,
		'meta_box_cb'           => null,
		'capabilities'          => array(),
		'rewrite'               => true,
		'query_var'             => $taxonomy,
		'update_count_callback' => '',
		'_builtin'              => false,
	);
	$args = array_merge( $defaults, $args );
	if ( empty( $taxonomy ) || strlen( $taxonomy ) > 32 ) {
		_doing_it_wrong( __FUNCTION__, 'Taxonomy names must be between 1 and 32 characters in length.', '4.2' );
		return new WP_Error( 'taxonomy_length_invalid','Taxonomy names must be between 1 and 32 characters in length.' );
	}
	if ( null === $args['publicly_queryable'] ) {
		$args['publicly_queryable'] = $args['public'];
	}
	if ( false !== $args['query_var'] && ( is_admin() || false !== $args['publicly_queryable'] ) && ! empty( $wp ) ) {
		if ( true === $args['query_var'] )
			$args['query_var'] = $taxonomy;
		else
			$args['query_var'] = sanitize_title_with_dashes( $args['query_var'] );
		$wp->add_query_var( $args['query_var'] );
	} else {
		$args['query_var'] = false;
	}
	if ( false !== $args['rewrite'] && ( is_admin() || '' != get_option( 'permalink_structure' ) ) ) {
		$args['rewrite'] = wp_parse_args( $args['rewrite'], array(
			'with_front' => true,
			'hierarchical' => false,
			'ep_mask' => EP_NONE,
		) );
		if ( empty( $args['rewrite']['slug'] ) )
			$args['rewrite']['slug'] = sanitize_title_with_dashes( $taxonomy );

		if ( $args['hierarchical'] && $args['rewrite']['hierarchical'] )
			$tag = '(.+?)';
		else
			$tag = '([^/]+)';

		add_rewrite_tag( "%$taxonomy%", $tag, $args['query_var'] ? "{$args['query_var']}=" : "taxonomy=$taxonomy&term=" );
		add_permastruct( $taxonomy, "{$args['rewrite']['slug']}/%$taxonomy%", $args['rewrite'] );
	}
	if ( null === $args['show_ui'] )
		$args['show_ui'] = $args['public'];
	if ( null === $args['show_in_menu' ] || ! $args['show_ui'] )
		$args['show_in_menu' ] = $args['show_ui'];
	if ( null === $args['show_in_nav_menus'] )
		$args['show_in_nav_menus'] = $args['public'];
	if ( null === $args['show_tagcloud'] )
		$args['show_tagcloud'] = $args['show_ui'];
	if ( null === $args['show_in_quick_edit'] ) {
		$args['show_in_quick_edit'] = $args['show_ui'];
	}
	$default_caps = array(
		'manage_terms' => 'manage_categories',
		'edit_terms'   => 'manage_categories',
		'delete_terms' => 'manage_categories',
		'assign_terms' => 'edit_posts',
	);
	$args['cap'] = (object) array_merge( $default_caps, $args['capabilities'] );
	unset( $args['capabilities'] );
	$args['name'] = $taxonomy;
	$args['object_type'] = array_unique( (array) $object_type );
	$args['labels'] = get_taxonomy_labels( (object) $args );
	$args['label'] = $args['labels']->name;
	if ( null === $args['meta_box_cb'] ) {
		if ( $args['hierarchical'] )
			$args['meta_box_cb'] = 'post_categories_meta_box';
		else
			$args['meta_box_cb'] = 'post_tags_meta_box';
	}
	$wp_taxonomies[ $taxonomy ] = (object) $args;
 	add_filter( 'wp_ajax_add-' . $taxonomy, '_wp_ajax_add_hierarchical_term' );
	do_action( 'registered_taxonomy', $taxonomy, $object_type, $args );
}

function unregister_taxonomy( $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy' );
	}
	$taxonomy_args = get_taxonomy( $taxonomy );
	if ( $taxonomy_args->_builtin ) {
		return new WP_Error( 'invalid_taxonomy', 'Unregistering a built-in taxonomy is not allowed' );
	}
	global $wp, $wp_taxonomies;
	if ( false !== $taxonomy_args->query_var ) {
		$wp->remove_query_var( $taxonomy_args->query_var );
	}
	if ( false !== $taxonomy_args->rewrite ) {
		remove_rewrite_tag( "%$taxonomy%" );
		remove_permastruct( $taxonomy );
	}
	remove_filter( 'wp_ajax_add-' . $taxonomy, '_wp_ajax_add_hierarchical_term' );
	unset( $wp_taxonomies[ $taxonomy ] );
	do_action( 'unregistered_taxonomy', $taxonomy );
	return true;
}

function get_taxonomy_labels( $tax ) {
	$tax->labels = (array) $tax->labels;

	if ( isset( $tax->helps ) && empty( $tax->labels['separate_items_with_commas'] ) )
		$tax->labels['separate_items_with_commas'] = $tax->helps;

	if ( isset( $tax->no_tagcloud ) && empty( $tax->labels['not_found'] ) )
		$tax->labels['not_found'] = $tax->no_tagcloud;

	$nohier_vs_hier_defaults = array(
		'name' => array( 'Tags', 'Categories' ),
		'singular_name' => array( 'Tag', 'Category' ),
		'search_items' => array( 'Search Tags', 'Search Categories' ),
		'popular_items' => array( 'Popular Tags', null ),
		'all_items' => array( 'All Tags', 'All Categories' ),
		'parent_item' => array( null, 'Parent Category' ),
		'parent_item_colon' => array( null, 'Parent Category:' ),
		'edit_item' => array( 'Edit Tag', 'Edit Category' ),
		'view_item' => array( 'View Tag', 'View Category' ),
		'update_item' => array( 'Update Tag', 'Update Category' ),
		'add_new_item' => array( 'Add New Tag', 'Add New Category' ),
		'new_item_name' => array( 'New Tag Name', 'New Category Name' ),
		'separate_items_with_commas' => array( 'Separate tags with commas', null ),
		'add_or_remove_items' => array( 'Add or remove tags', null ),
		'choose_from_most_used' => array( 'Choose from the most used tags', null ),
		'not_found' => array( 'No tags found.', 'No categories found.' ),
		'no_terms' => array( 'No tags', 'No categories' ),
		'items_list_navigation' => array( 'Tags list navigation', 'Categories list navigation' ),
		'items_list' => array( 'Tags list', 'Categories list' ),
	);
	$nohier_vs_hier_defaults['menu_name'] = $nohier_vs_hier_defaults['name'];

	$labels = _get_custom_object_labels( $tax, $nohier_vs_hier_defaults );

	$taxonomy = $tax->name;

	$default_labels = clone $labels;

	$labels = apply_filters( "taxonomy_labels_{$taxonomy}", $labels );

	$labels = (object) array_merge( (array) $default_labels, (array) $labels );

	return $labels;
}

function register_taxonomy_for_object_type( $taxonomy, $object_type) {
	global $wp_taxonomies;

	if ( !isset($wp_taxonomies[$taxonomy]) )
		return false;

	if ( ! get_post_type_object($object_type) )
		return false;

	if ( ! in_array( $object_type, $wp_taxonomies[$taxonomy]->object_type ) )
		$wp_taxonomies[$taxonomy]->object_type[] = $object_type;

	$wp_taxonomies[ $taxonomy ]->object_type = array_filter( $wp_taxonomies[ $taxonomy ]->object_type );

	return true;
}

function unregister_taxonomy_for_object_type( $taxonomy, $object_type ) {
	global $wp_taxonomies;

	if ( ! isset( $wp_taxonomies[ $taxonomy ] ) )
		return false;

	if ( ! get_post_type_object( $object_type ) )
		return false;

	$key = array_search( $object_type, $wp_taxonomies[ $taxonomy ]->object_type, true );
	if ( false === $key )
		return false;

	unset( $wp_taxonomies[ $taxonomy ]->object_type[ $key ] );
	return true;
}

function get_objects_in_term( $term_ids, $taxonomies, $args = array() ) {
	global $wpdb;

	if ( ! is_array( $term_ids ) ) {
		$term_ids = array( $term_ids );
	}
	if ( ! is_array( $taxonomies ) ) {
		$taxonomies = array( $taxonomies );
	}
	foreach ( (array) $taxonomies as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy' );
		}
	}

	$defaults = array( 'order' => 'ASC' );
	$args = wp_parse_args( $args, $defaults );

	$order = ( 'desc' == strtolower( $args['order'] ) ) ? 'DESC' : 'ASC';

	$term_ids = array_map('intval', $term_ids );

	$taxonomies = "'" . implode( "', '", array_map( 'esc_sql', $taxonomies ) ) . "'";
	$term_ids = "'" . implode( "', '", $term_ids ) . "'";

	$object_ids = $wpdb->get_col("SELECT tr.object_id FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy IN ($taxonomies) AND tt.term_id IN ($term_ids) ORDER BY tr.object_id $order");

	if ( ! $object_ids ){
		return array();
	}
	return $object_ids;
}

function get_tax_sql( $tax_query, $primary_table, $primary_id_column ) {
	$tax_query_obj = new WP_Tax_Query( $tax_query );
	return $tax_query_obj->get_sql( $primary_table, $primary_id_column );
}

function get_term( $term, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $term ) ) {
		return new WP_Error( 'invalid_term', 'Empty Term' );
	}
	if ( $taxonomy && ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy' );
	}
	if ( $term instanceof WP_Term ) {
		$_term = $term;
	} elseif ( is_object( $term ) ) {
		if ( empty( $term->filter ) || 'raw' === $term->filter ) {
			$_term = sanitize_term( $term, $taxonomy, 'raw' );
			$_term = new WP_Term( $_term );
		} else {
			$_term = WP_Term::get_instance( $term->term_id );
		}
	} else {
		$_term = WP_Term::get_instance( $term, $taxonomy );
	}

	if ( is_wp_error( $_term ) ) {
		return $_term;
	} elseif ( ! $_term ) {
		return null;
	}
	$_term = apply_filters( 'get_term', $_term, $taxonomy );
	$_term = apply_filters( "get_$taxonomy", $_term, $taxonomy );
	if ( ! ( $_term instanceof WP_Term ) ) {
		return $_term;
	}
	$_term->filter( $filter );
	if ( $output == ARRAY_A ) {
		return $_term->to_array();
	} elseif ( $output == ARRAY_N ) {
		return array_values( $_term->to_array() );
	}
	return $_term;
}

function get_term_by( $field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
	global $wpdb;

	// 'term_taxonomy_id' lookups don't require taxonomy checks.
	if ( 'term_taxonomy_id' !== $field && ! taxonomy_exists( $taxonomy ) ) {
		return false;
	}

	$tax_clause = $wpdb->prepare( "AND tt.taxonomy = %s", $taxonomy );

	if ( 'slug' == $field ) {
		$_field = 't.slug';
		$value = sanitize_title($value);
		if ( empty($value) )
			return false;
	} elseif ( 'name' == $field ) {
		// Assume already escaped
		$value = wp_unslash($value);
		$_field = 't.name';
	} elseif ( 'term_taxonomy_id' == $field ) {
		$value = (int) $value;
		$_field = 'tt.term_taxonomy_id';

		// No `taxonomy` clause when searching by 'term_taxonomy_id'.
		$tax_clause = '';
	} else {
		$term = get_term( (int) $value, $taxonomy, $output, $filter );
		if ( is_wp_error( $term ) || is_null( $term ) ) {
			$term = false;
		}
		return $term;
	}

	$term = $wpdb->get_row( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE $_field = %s", $value ) . " $tax_clause LIMIT 1" );
	if ( ! $term )
		return false;

	// In the case of 'term_taxonomy_id', override the provided `$taxonomy` with whatever we find in the db.
	if ( 'term_taxonomy_id' === $field ) {
		$taxonomy = $term->taxonomy;
	}

	wp_cache_add( $term->term_id, $term, 'terms' );

	return get_term( $term, $taxonomy, $output, $filter );
}

function get_term_children( $term_id, $taxonomy ) {
	if ( ! taxonomy_exists($taxonomy) )
		return new WP_Error('invalid_taxonomy', 'Invalid taxonomy');

	$term_id = intval( $term_id );

	$terms = _get_term_hierarchy($taxonomy);

	if ( ! isset($terms[$term_id]) )
		return array();

	$children = $terms[$term_id];

	foreach ( (array) $terms[$term_id] as $child ) {
		if ( $term_id == $child ) {
			continue;
		}

		if ( isset($terms[$child]) )
			$children = array_merge($children, get_term_children($child, $taxonomy));
	}

	return $children;
}

function get_term_field( $field, $term, $taxonomy = '', $context = 'display' ) {
	$term = get_term( $term, $taxonomy );
	if ( is_wp_error($term) )
		return $term;

	if ( !is_object($term) )
		return '';

	if ( !isset($term->$field) )
		return '';

	return sanitize_term_field( $field, $term->$field, $term->term_id, $term->taxonomy, $context );
}

function get_term_to_edit( $id, $taxonomy ) {
	$term = get_term( $id, $taxonomy );

	if ( is_wp_error($term) )
		return $term;

	if ( !is_object($term) )
		return '';

	return sanitize_term($term, $taxonomy, 'edit');
}

function get_terms( $args = array(), $deprecated = '' ) {
	global $wpdb;
	$defaults = array(
		'taxonomy'               => null,
		'orderby'                => 'name',
		'order'                  => 'ASC',
		'hide_empty'             => true,
		'include'                => array(),
		'exclude'                => array(),
		'exclude_tree'           => array(),
		'number'                 => '',
		'offset'                 => '',
		'fields'                 => 'all',
		'name'                   => '',
		'slug'                   => '',
		'hierarchical'           => true,
		'search'                 => '',
		'name__like'             => '',
		'description__like'      => '',
		'pad_counts'             => false,
		'get'                    => '',
		'child_of'               => 0,
		'parent'                 => '',
		'childless'              => false,
		'cache_domain'           => 'core',
		'update_term_meta_cache' => true,
		'meta_query'             => ''
	);
	$key_intersect  = array_intersect_key( $defaults, (array) $args );
	$do_legacy_args = $deprecated || empty( $key_intersect );
	$taxonomies = null;
	if ( $do_legacy_args ) {
		$taxonomies = (array) $args;
		$args = $deprecated;
	} elseif ( isset( $args['taxonomy'] ) && null !== $args['taxonomy'] ) {
		$taxonomies = (array) $args['taxonomy'];
		unset( $args['taxonomy'] );
	}
	$empty_array = array();
	if ( $taxonomies ) {
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists($taxonomy) ) {
				return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy' );
			}
		}
	}
	$args = wp_parse_args( $args, apply_filters( 'get_terms_defaults', $defaults, $taxonomies ) );

	$args['number'] = absint( $args['number'] );
	$args['offset'] = absint( $args['offset'] );

	$has_hierarchical_tax = false;
	if ( $taxonomies ) {
		foreach ( $taxonomies as $_tax ) {
			if ( is_taxonomy_hierarchical( $_tax ) ) {
				$has_hierarchical_tax = true;
			}
		}
	}
	if ( ! $has_hierarchical_tax ) {
		$args['hierarchical'] = false;
		$args['pad_counts'] = false;
	}
	if ( 0 < intval( $args['parent'] ) ) {
		$args['child_of'] = false;
	}
	if ( 'all' == $args['get'] ) {
		$args['childless'] = false;
		$args['child_of'] = 0;
		$args['hide_empty'] = 0;
		$args['hierarchical'] = false;
		$args['pad_counts'] = false;
	}
	$args = apply_filters( 'get_terms_args', $args, $taxonomies );
	$child_of = $args['child_of'];
	$parent   = $args['parent'];

	if ( $child_of ) {
		$_parent = $child_of;
	} elseif ( $parent ) {
		$_parent = $parent;
	} else {
		$_parent = false;
	}
	if ( $_parent ) {
		$in_hierarchy = false;
		foreach ( $taxonomies as $_tax ) {
			$hierarchy = _get_term_hierarchy( $_tax );

			if ( isset( $hierarchy[ $_parent ] ) ) {
				$in_hierarchy = true;
			}
		}
		if ( ! $in_hierarchy ) {
			return $empty_array;
		}
	}
	$_orderby = strtolower( $args['orderby'] );
	if ( 'count' == $_orderby ) {
		$orderby = 'tt.count';
	} elseif ( 'name' == $_orderby ) {
		$orderby = 't.name';
	} elseif ( 'slug' == $_orderby ) {
		$orderby = 't.slug';
	} elseif ( 'include' == $_orderby && ! empty( $args['include'] ) ) {
		$include = implode( ',', array_map( 'absint', $args['include'] ) );
		$orderby = "FIELD( t.term_id, $include )";
	} elseif ( 'term_group' == $_orderby ) {
		$orderby = 't.term_group';
	} elseif ( 'description' == $_orderby ) {
		$orderby = 'tt.description';
	} elseif ( 'none' == $_orderby ) {
		$orderby = '';
	} elseif ( empty( $_orderby ) || 'id' == $_orderby || 'term_id' === $_orderby ) {
		$orderby = 't.term_id';
	} else {
		$orderby = 't.name';
	}
	$orderby = apply_filters( 'get_terms_orderby', $orderby, $args, $taxonomies );
	$order = strtoupper( $args['order'] );
	if ( ! empty( $orderby ) ) {
		$orderby = "ORDER BY $orderby";
	} else {
		$order = '';
	}
	if ( '' !== $order && ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
		$order = 'ASC';
	}
	$where_conditions = array();
	if ( $taxonomies ) {
		$where_conditions[] = "tt.taxonomy IN ('" . implode("', '", array_map( 'esc_sql', $taxonomies ) ) . "')";
	}
	$exclude = $args['exclude'];
	$exclude_tree = $args['exclude_tree'];
	$include = $args['include'];
	$inclusions = '';
	if ( ! empty( $include ) ) {
		$exclude = '';
		$exclude_tree = '';
		$inclusions = implode( ',', wp_parse_id_list( $include ) );
	}
	if ( ! empty( $inclusions ) ) {
		$where_conditions[] = 't.term_id IN ( ' . $inclusions . ' )';
	}
	$exclusions = array();
	if ( ! empty( $exclude_tree ) ) {
		$exclude_tree = wp_parse_id_list( $exclude_tree );
		$excluded_children = $exclude_tree;
		foreach ( $exclude_tree as $extrunk ) {
			$excluded_children = array_merge(
				$excluded_children,
				(array) get_terms( $taxonomies[0], array( 'child_of' => intval( $extrunk ), 'fields' => 'ids', 'hide_empty' => 0 ) )
			);
		}
		$exclusions = array_merge( $excluded_children, $exclusions );
	}
	if ( ! empty( $exclude ) ) {
		$exclusions = array_merge( wp_parse_id_list( $exclude ), $exclusions );
	}
	$childless = (bool) $args['childless'];
	if ( $childless ) {
		foreach ( $taxonomies as $_tax ) {
			$term_hierarchy = _get_term_hierarchy( $_tax );
			$exclusions = array_merge( array_keys( $term_hierarchy ), $exclusions );
		}
	}
	if ( ! empty( $exclusions ) ) {
		$exclusions = 't.term_id NOT IN (' . implode( ',', array_map( 'intval', $exclusions ) ) . ')';
	} else {
		$exclusions = '';
	}
	$exclusions = apply_filters( 'list_terms_exclusions', $exclusions, $args, $taxonomies );
	if ( ! empty( $exclusions ) ) {
		$where_conditions[] = preg_replace( '/^\s*AND\s*/', '', $exclusions );
	}
	if ( ! empty( $args['name'] ) ) {
		$names = (array) $args['name'];
		foreach ( $names as &$_name ) {
			$_name = stripslashes( sanitize_term_field( 'name', $_name, 0, reset( $taxonomies ), 'db' ) );
		}
		$where_conditions[] = "t.name IN ('" . implode( "', '", array_map( 'esc_sql', $names ) ) . "')";
	}
	if ( ! empty( $args['slug'] ) ) {
		if ( is_array( $args['slug'] ) ) {
			$slug = array_map( 'sanitize_title', $args['slug'] );
			$where_conditions[] = "t.slug IN ('" . implode( "', '", $slug ) . "')";
		} else {
			$slug = sanitize_title( $args['slug'] );
			$where_conditions[] = "t.slug = '$slug'";
		}
	}
	if ( ! empty( $args['name__like'] ) ) {
		$where_conditions[] = $wpdb->prepare( "t.name LIKE %s", '%' . $wpdb->esc_like( $args['name__like'] ) . '%' );
	}
	if ( ! empty( $args['description__like'] ) ) {
		$where_conditions[] = $wpdb->prepare( "tt.description LIKE %s", '%' . $wpdb->esc_like( $args['description__like'] ) . '%' );
	}
	if ( '' !== $parent ) {
		$parent = (int) $parent;
		$where_conditions[] = "tt.parent = '$parent'";
	}
	$hierarchical = $args['hierarchical'];
	if ( 'count' == $args['fields'] ) {
		$hierarchical = false;
	}
	if ( $args['hide_empty'] && !$hierarchical ) {
		$where_conditions[] = 'tt.count > 0';
	}
	$number = $args['number'];
	$offset = $args['offset'];
	if ( $number && ! $hierarchical && ! $child_of && '' === $parent ) {
		if ( $offset ) {
			$limits = 'LIMIT ' . $offset . ',' . $number;
		} else {
			$limits = 'LIMIT ' . $number;
		}
	} else {
		$limits = '';
	}

	if ( ! empty( $args['search'] ) ) {
		$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where_conditions[] = $wpdb->prepare( '((t.name LIKE %s) OR (t.slug LIKE %s))', $like, $like );
	}
	$join = '';
	$distinct = '';
	$mquery = new WP_Meta_Query();
	$mquery->parse_query_vars( $args );
	$mq_sql = $mquery->get_sql( 'term', 't', 'term_id' );
	$meta_clauses = $mquery->get_clauses();
	if ( ! empty( $meta_clauses ) ) {
		$join .= $mq_sql['join'];
		$where_conditions[] = preg_replace( '/^\s*AND\s*/', '', $mq_sql['where'] );
		$distinct .= "DISTINCT";
		$allowed_keys = array();
		$primary_meta_key   = null;
		$primary_meta_query = reset( $meta_clauses );
		if ( ! empty( $primary_meta_query['key'] ) ) {
			$primary_meta_key = $primary_meta_query['key'];
			$allowed_keys[] = $primary_meta_key;
		}
		$allowed_keys[] = 'meta_value';
		$allowed_keys[] = 'meta_value_num';
		$allowed_keys   = array_merge( $allowed_keys, array_keys( $meta_clauses ) );
		if ( ! empty( $args['orderby'] ) && in_array( $args['orderby'], $allowed_keys ) ) {
			switch( $args['orderby'] ) {
				case $primary_meta_key:
				case 'meta_value':
					if ( ! empty( $primary_meta_query['type'] ) ) {
						$orderby = "ORDER BY CAST({$primary_meta_query['alias']}.meta_value AS {$primary_meta_query['cast']})";
					} else {
						$orderby = "ORDER BY {$primary_meta_query['alias']}.meta_value";
					}
					break;

				case 'meta_value_num':
					$orderby = "ORDER BY {$primary_meta_query['alias']}.meta_value+0";
					break;

				default:
					if ( array_key_exists( $args['orderby'], $meta_clauses ) ) {
						$meta_clause = $meta_clauses[ $args['orderby'] ];
						$orderby = "ORDER BY CAST({$meta_clause['alias']}.meta_value AS {$meta_clause['cast']})";
					}
					break;
			}
		}
	}
	$selects = array();
	switch ( $args['fields'] ) {
		case 'all':
			$selects = array( 't.*', 'tt.*' );
			break;
		case 'ids':
		case 'id=>parent':
			$selects = array( 't.term_id', 'tt.parent', 'tt.count', 'tt.taxonomy' );
			break;
		case 'names':
			$selects = array( 't.term_id', 'tt.parent', 'tt.count', 't.name', 'tt.taxonomy' );
			break;
		case 'count':
			$orderby = '';
			$order = '';
			$selects = array( 'COUNT(*)' );
			break;
		case 'id=>name':
			$selects = array( 't.term_id', 't.name', 'tt.count', 'tt.taxonomy' );
			break;
		case 'id=>slug':
			$selects = array( 't.term_id', 't.slug', 'tt.count', 'tt.taxonomy' );
			break;
	}
	$_fields = $args['fields'];
	$fields = implode( ', ', apply_filters( 'get_terms_fields', $selects, $args, $taxonomies ) );
	$join .= " INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id";
	$where = implode( ' AND ', $where_conditions );
	$pieces = array( 'fields', 'join', 'where', 'distinct', 'orderby', 'order', 'limits' );
	$clauses = apply_filters( 'terms_clauses', compact( $pieces ), $taxonomies, $args );
	$fields = isset( $clauses[ 'fields' ] ) ? $clauses[ 'fields' ] : '';
	$join = isset( $clauses[ 'join' ] ) ? $clauses[ 'join' ] : '';
	$where = isset( $clauses[ 'where' ] ) ? $clauses[ 'where' ] : '';
	$distinct = isset( $clauses[ 'distinct' ] ) ? $clauses[ 'distinct' ] : '';
	$orderby = isset( $clauses[ 'orderby' ] ) ? $clauses[ 'orderby' ] : '';
	$order = isset( $clauses[ 'order' ] ) ? $clauses[ 'order' ] : '';
	$limits = isset( $clauses[ 'limits' ] ) ? $clauses[ 'limits' ] : '';
	if ( $where ) {
		$where = "WHERE $where";
	}
	$query = "SELECT $distinct $fields FROM $wpdb->terms AS t $join $where $orderby $order $limits";
	$key = md5( serialize( wp_array_slice_assoc( $args, array_keys( $defaults ) ) ) . serialize( $taxonomies ) . $query );
	$last_changed = wp_cache_get( 'last_changed', 'terms' );
	if ( ! $last_changed ) {
		$last_changed = microtime();
		wp_cache_set( 'last_changed', $last_changed, 'terms' );
	}
	$cache_key = "get_terms:$key:$last_changed";
	$cache = wp_cache_get( $cache_key, 'terms' );
	if ( false !== $cache ) {
		if ( 'all' === $_fields ) {
			$cache = array_map( 'get_term', $cache );
		}

		return apply_filters( 'get_terms', $cache, $taxonomies, $args );
	}
	if ( 'count' == $_fields ) {
		return $wpdb->get_var( $query );
	}
	$terms = $wpdb->get_results($query);
	if ( 'all' == $_fields ) {
		update_term_cache( $terms );
	}
	if ( $args['update_term_meta_cache'] ) {
		$term_ids = wp_list_pluck( $terms, 'term_id' );
		update_termmeta_cache( $term_ids );
	}
	if ( empty($terms) ) {
		wp_cache_add( $cache_key, array(), 'terms', DAY_IN_SECONDS );
		return apply_filters( 'get_terms', array(), $taxonomies, $args );
	}
	if ( $child_of ) {
		foreach ( $taxonomies as $_tax ) {
			$children = _get_term_hierarchy( $_tax );
			if ( ! empty( $children ) ) {
				$terms = _get_term_children( $child_of, $terms, $_tax );
			}
		}
	}
	if ( $args['pad_counts'] && 'all' == $_fields ) {
		foreach ( $taxonomies as $_tax ) {
			_pad_term_counts( $terms, $_tax );
		}
	}
	if ( $hierarchical && $args['hide_empty'] && is_array( $terms ) ) {
		foreach ( $terms as $k => $term ) {
			if ( ! $term->count ) {
				$children = get_term_children( $term->term_id, $term->taxonomy );
				if ( is_array( $children ) ) {
					foreach ( $children as $child_id ) {
						$child = get_term( $child_id, $term->taxonomy );
						if ( $child->count ) {
							continue 2;
						}
					}
				}
				unset($terms[$k]);
			}
		}
	}
	$_terms = array();
	if ( 'id=>parent' == $_fields ) {
		foreach ( $terms as $term ) {
			$_terms[ $term->term_id ] = $term->parent;
		}
	} elseif ( 'ids' == $_fields ) {
		foreach ( $terms as $term ) {
			$_terms[] = $term->term_id;
		}
	} elseif ( 'names' == $_fields ) {
		foreach ( $terms as $term ) {
			$_terms[] = $term->name;
		}
	} elseif ( 'id=>name' == $_fields ) {
		foreach ( $terms as $term ) {
			$_terms[ $term->term_id ] = $term->name;
		}
	} elseif ( 'id=>slug' == $_fields ) {
		foreach ( $terms as $term ) {
			$_terms[ $term->term_id ] = $term->slug;
		}
	}
	if ( ! empty( $_terms ) ) {
		$terms = $_terms;
	}
	if ( $hierarchical && $number && is_array( $terms ) ) {
		if ( $offset >= count( $terms ) ) {
			$terms = array();
		} else {
			$terms = array_slice( $terms, $offset, $number, true );
		}
	}
	wp_cache_add( $cache_key, $terms, 'terms', DAY_IN_SECONDS );
	if ( 'all' === $_fields ) {
		$terms = array_map( 'get_term', $terms );
	}
	return apply_filters( 'get_terms', $terms, $taxonomies, $args );
}

function add_term_meta( $term_id, $meta_key, $meta_value, $unique = false ) {
	if ( get_option( 'db_version' ) < 34370 ) {
		return false;
	}

	if ( wp_term_is_shared( $term_id ) ) {
		return new WP_Error( 'ambiguous_term_id', 'Term meta cannot be added to terms that are shared between taxonomies.', $term_id );
	}

	$added = add_metadata( 'term', $term_id, $meta_key, $meta_value, $unique );

	if ( $added ) {
		wp_cache_set( 'last_changed', microtime(), 'terms' );
	}

	return $added;
}

function delete_term_meta( $term_id, $meta_key, $meta_value = '' ) {
	// Bail if term meta table is not installed.
	if ( get_option( 'db_version' ) < 34370 ) {
		return false;
	}

	$deleted = delete_metadata( 'term', $term_id, $meta_key, $meta_value );

	if ( $deleted ) {
		wp_cache_set( 'last_changed', microtime(), 'terms' );
	}

	return $deleted;
}

function get_term_meta( $term_id, $key = '', $single = false ) {
	// Bail if term meta table is not installed.
	if ( get_option( 'db_version' ) < 34370 ) {
		return false;
	}

	return get_metadata( 'term', $term_id, $key, $single );
}

function update_term_meta( $term_id, $meta_key, $meta_value, $prev_value = '' ) {
	if ( get_option( 'db_version' ) < 34370 ) {
		return false;
	}

	if ( wp_term_is_shared( $term_id ) ) {
		return new WP_Error( 'ambiguous_term_id', 'Term meta cannot be added to terms that are shared between taxonomies.', $term_id );
	}

	$updated = update_metadata( 'term', $term_id, $meta_key, $meta_value, $prev_value );

	// Bust term query cache.
	if ( $updated ) {
		wp_cache_set( 'last_changed', microtime(), 'terms' );
	}

	return $updated;
}

function update_termmeta_cache( $term_ids ) {
	if ( get_option( 'db_version' ) < 34370 ) {
		return;
	}
	return update_meta_cache( 'term', $term_ids );
}

function term_exists( $term, $taxonomy = '', $parent = null ) {
	global $wpdb;

	$select = "SELECT term_id FROM $wpdb->terms as t WHERE ";
	$tax_select = "SELECT tt.term_id, tt.term_taxonomy_id FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy as tt ON tt.term_id = t.term_id WHERE ";

	if ( is_int($term) ) {
		if ( 0 == $term )
			return 0;
		$where = 't.term_id = %d';
		if ( !empty($taxonomy) )
			return $wpdb->get_row( $wpdb->prepare( $tax_select . $where . " AND tt.taxonomy = %s", $term, $taxonomy ), ARRAY_A );
		else
			return $wpdb->get_var( $wpdb->prepare( $select . $where, $term ) );
	}

	$term = trim( wp_unslash( $term ) );
	$slug = sanitize_title( $term );

	$where = 't.slug = %s';
	$else_where = 't.name = %s';
	$where_fields = array($slug);
	$else_where_fields = array($term);
	$orderby = 'ORDER BY t.term_id ASC';
	$limit = 'LIMIT 1';
	if ( !empty($taxonomy) ) {
		if ( is_numeric( $parent ) ) {
			$parent = (int) $parent;
			$where_fields[] = $parent;
			$else_where_fields[] = $parent;
			$where .= ' AND tt.parent = %d';
			$else_where .= ' AND tt.parent = %d';
		}

		$where_fields[] = $taxonomy;
		$else_where_fields[] = $taxonomy;

		if ( $result = $wpdb->get_row( $wpdb->prepare("SELECT tt.term_id, tt.term_taxonomy_id FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy as tt ON tt.term_id = t.term_id WHERE $where AND tt.taxonomy = %s $orderby $limit", $where_fields), ARRAY_A) )
			return $result;

		return $wpdb->get_row( $wpdb->prepare("SELECT tt.term_id, tt.term_taxonomy_id FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy as tt ON tt.term_id = t.term_id WHERE $else_where AND tt.taxonomy = %s $orderby $limit", $else_where_fields), ARRAY_A);
	}

	if ( $result = $wpdb->get_var( $wpdb->prepare("SELECT term_id FROM $wpdb->terms as t WHERE $where $orderby $limit", $where_fields) ) )
		return $result;

	return $wpdb->get_var( $wpdb->prepare("SELECT term_id FROM $wpdb->terms as t WHERE $else_where $orderby $limit", $else_where_fields) );
}

function term_is_ancestor_of( $term1, $term2, $taxonomy ) {
	if ( ! isset( $term1->term_id ) )
		$term1 = get_term( $term1, $taxonomy );
	if ( ! isset( $term2->parent ) )
		$term2 = get_term( $term2, $taxonomy );

	if ( empty( $term1->term_id ) || empty( $term2->parent ) )
		return false;
	if ( $term2->parent == $term1->term_id )
		return true;

	return term_is_ancestor_of( $term1, get_term( $term2->parent, $taxonomy ), $taxonomy );
}

function sanitize_term($term, $taxonomy, $context = 'display') {
	$fields = array( 'term_id', 'name', 'description', 'slug', 'count', 'parent', 'term_group', 'term_taxonomy_id', 'object_id' );

	$do_object = is_object( $term );

	$term_id = $do_object ? $term->term_id : (isset($term['term_id']) ? $term['term_id'] : 0);

	foreach ( (array) $fields as $field ) {
		if ( $do_object ) {
			if ( isset($term->$field) )
				$term->$field = sanitize_term_field($field, $term->$field, $term_id, $taxonomy, $context);
		} else {
			if ( isset($term[$field]) )
				$term[$field] = sanitize_term_field($field, $term[$field], $term_id, $taxonomy, $context);
		}
	}

	if ( $do_object )
		$term->filter = $context;
	else
		$term['filter'] = $context;

	return $term;
}

function sanitize_term_field($field, $value, $term_id, $taxonomy, $context) {
	$int_fields = array( 'parent', 'term_id', 'count', 'term_group', 'term_taxonomy_id', 'object_id' );
	if ( in_array( $field, $int_fields ) ) {
		$value = (int) $value;
		if ( $value < 0 )
			$value = 0;
	}

	if ( 'raw' == $context )
		return $value;

	if ( 'edit' == $context ) {
		$value = apply_filters( "edit_term_{$field}", $value, $term_id, $taxonomy );
		$value = apply_filters( "edit_{$taxonomy}_{$field}", $value, $term_id );

		if ( 'description' == $field )
			$value = esc_html($value); // textarea_escaped
		else
			$value = esc_attr($value);
	} elseif ( 'db' == $context ) {
		$value = apply_filters( "pre_term_{$field}", $value, $taxonomy );
		$value = apply_filters( "pre_{$taxonomy}_{$field}", $value );

		// Back compat filters
		if ( 'slug' == $field ) {
			$value = apply_filters( 'pre_category_nicename', $value );
		}

	} elseif ( 'rss' == $context ) {
		$value = apply_filters( "term_{$field}_rss", $value, $taxonomy );
		$value = apply_filters( "{$taxonomy}_{$field}_rss", $value );
	} else {
		$value = apply_filters( "term_{$field}", $value, $term_id, $taxonomy, $context );
		$value = apply_filters( "{$taxonomy}_{$field}", $value, $term_id, $context );
	}

	if ( 'attribute' == $context ) {
		$value = esc_attr($value);
	} elseif ( 'js' == $context ) {
		$value = esc_js($value);
	}
	return $value;
}

function wp_count_terms( $taxonomy, $args = array() ) {
	$defaults = array('hide_empty' => false);
	$args = wp_parse_args($args, $defaults);

	// backwards compatibility
	if ( isset($args['ignore_empty']) ) {
		$args['hide_empty'] = $args['ignore_empty'];
		unset($args['ignore_empty']);
	}

	$args['fields'] = 'count';

	return get_terms($taxonomy, $args);
}

function wp_delete_object_term_relationships( $object_id, $taxonomies ) {
	$object_id = (int) $object_id;

	if ( !is_array($taxonomies) )
		$taxonomies = array($taxonomies);

	foreach ( (array) $taxonomies as $taxonomy ) {
		$term_ids = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );
		$term_ids = array_map( 'intval', $term_ids );
		wp_remove_object_terms( $object_id, $term_ids, $taxonomy );
	}
}

function wp_delete_term( $term, $taxonomy, $args = array() ) {
	global $wpdb;

	$term = (int) $term;

	if ( ! $ids = term_exists($term, $taxonomy) )
		return false;
	if ( is_wp_error( $ids ) )
		return $ids;

	$tt_id = $ids['term_taxonomy_id'];

	$defaults = array();

	if ( 'category' == $taxonomy ) {
		$defaults['default'] = get_option( 'default_category' );
		if ( $defaults['default'] == $term )
			return 0; // Don't delete the default category
	}

	$args = wp_parse_args($args, $defaults);

	if ( isset( $args['default'] ) ) {
		$default = (int) $args['default'];
		if ( ! term_exists( $default, $taxonomy ) ) {
			unset( $default );
		}
	}

	if ( isset( $args['force_default'] ) ) {
		$force_default = $args['force_default'];
	}

	do_action( 'pre_delete_term', $term, $taxonomy );

	// Update children to point to new parent
	if ( is_taxonomy_hierarchical($taxonomy) ) {
		$term_obj = get_term($term, $taxonomy);
		if ( is_wp_error( $term_obj ) )
			return $term_obj;
		$parent = $term_obj->parent;

		$edit_ids = $wpdb->get_results( "SELECT term_id, term_taxonomy_id FROM $wpdb->term_taxonomy WHERE `parent` = " . (int)$term_obj->term_id );
		$edit_tt_ids = wp_list_pluck( $edit_ids, 'term_taxonomy_id' );

		/**
		 * Fires immediately before a term to delete's children are reassigned a parent.
		 *
		 * @since 2.9.0
		 *
		 * @param array $edit_tt_ids An array of term taxonomy IDs for the given term.
		 */
		do_action( 'edit_term_taxonomies', $edit_tt_ids );

		$wpdb->update( $wpdb->term_taxonomy, compact( 'parent' ), array( 'parent' => $term_obj->term_id) + compact( 'taxonomy' ) );

		// Clean the cache for all child terms.
		$edit_term_ids = wp_list_pluck( $edit_ids, 'term_id' );
		clean_term_cache( $edit_term_ids, $taxonomy );

		do_action( 'edited_term_taxonomies', $edit_tt_ids );
	}

	// Get the term before deleting it or its term relationships so we can pass to actions below.
	$deleted_term = get_term( $term, $taxonomy );

	$object_ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $tt_id ) );

	foreach ( $object_ids as $object_id ) {
		$terms = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids', 'orderby' => 'none' ) );
		if ( 1 == count($terms) && isset($default) ) {
			$terms = array($default);
		} else {
			$terms = array_diff($terms, array($term));
			if (isset($default) && isset($force_default) && $force_default)
				$terms = array_merge($terms, array($default));
		}
		$terms = array_map('intval', $terms);
		wp_set_object_terms( $object_id, $terms, $taxonomy );
	}

	// Clean the relationship caches for all object types using this term.
	$tax_object = get_taxonomy( $taxonomy );
	foreach ( $tax_object->object_type as $object_type )
		clean_object_term_cache( $object_ids, $object_type );

	$term_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->termmeta WHERE term_id = %d ", $term ) );
	foreach ( $term_meta_ids as $mid ) {
		delete_metadata_by_mid( 'term', $mid );
	}

	do_action( 'delete_term_taxonomy', $tt_id );
	$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $tt_id ) );

	do_action( 'deleted_term_taxonomy', $tt_id );

	// Delete the term if no taxonomies use it.
	if ( !$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_taxonomy WHERE term_id = %d", $term) ) )
		$wpdb->delete( $wpdb->terms, array( 'term_id' => $term ) );

	clean_term_cache($term, $taxonomy);

	do_action( 'delete_term', $term, $tt_id, $taxonomy, $deleted_term, $object_ids );

	do_action( "delete_$taxonomy", $term, $tt_id, $deleted_term, $object_ids );

	return true;
}

function wp_delete_category( $cat_ID ) {
	return wp_delete_term( $cat_ID, 'category' );
}

function wp_get_object_terms($object_ids, $taxonomies, $args = array()) {
	global $wpdb;

	if ( empty( $object_ids ) || empty( $taxonomies ) )
		return array();

	if ( !is_array($taxonomies) )
		$taxonomies = array($taxonomies);

	foreach ( $taxonomies as $taxonomy ) {
		if ( ! taxonomy_exists($taxonomy) )
			return new WP_Error('invalid_taxonomy', 'Invalid taxonomy');
	}

	if ( !is_array($object_ids) )
		$object_ids = array($object_ids);
	$object_ids = array_map('intval', $object_ids);

	$defaults = array(
		'orderby' => 'name',
		'order'   => 'ASC',
		'fields'  => 'all',
		'parent'  => '',
		'update_term_meta_cache' => true,
		'meta_query' => '',
	);
	$args = wp_parse_args( $args, $defaults );

	$terms = array();
	if ( count($taxonomies) > 1 ) {
		foreach ( $taxonomies as $index => $taxonomy ) {
			$t = get_taxonomy($taxonomy);
			if ( isset($t->args) && is_array($t->args) && $args != array_merge($args, $t->args) ) {
				unset($taxonomies[$index]);
				$terms = array_merge($terms, wp_get_object_terms($object_ids, $taxonomy, array_merge($args, $t->args)));
			}
		}
	} else {
		$t = get_taxonomy($taxonomies[0]);
		if ( isset($t->args) && is_array($t->args) )
			$args = array_merge($args, $t->args);
	}

	$orderby = $args['orderby'];
	$order = $args['order'];
	$fields = $args['fields'];

	if ( in_array( $orderby, array( 'term_id', 'name', 'slug', 'term_group' ) ) ) {
		$orderby = "t.$orderby";
	} elseif ( in_array( $orderby, array( 'count', 'parent', 'taxonomy', 'term_taxonomy_id' ) ) ) {
		$orderby = "tt.$orderby";
	} elseif ( 'term_order' === $orderby ) {
		$orderby = 'tr.term_order';
	} elseif ( 'none' === $orderby ) {
		$orderby = '';
		$order = '';
	} else {
		$orderby = 't.term_id';
	}

	// tt_ids queries can only be none or tr.term_taxonomy_id
	if ( ('tt_ids' == $fields) && !empty($orderby) )
		$orderby = 'tr.term_taxonomy_id';

	if ( !empty($orderby) )
		$orderby = "ORDER BY $orderby";

	$order = strtoupper( $order );
	if ( '' !== $order && ! in_array( $order, array( 'ASC', 'DESC' ) ) )
		$order = 'ASC';

	$taxonomy_array = $taxonomies;
	$object_id_array = $object_ids;
	$taxonomies = "'" . implode("', '", array_map( 'esc_sql', $taxonomies ) ) . "'";
	$object_ids = implode(', ', $object_ids);

	$select_this = '';
	if ( 'all' == $fields ) {
		$select_this = 't.*, tt.*';
	} elseif ( 'ids' == $fields ) {
		$select_this = 't.term_id';
	} elseif ( 'names' == $fields ) {
		$select_this = 't.name';
	} elseif ( 'slugs' == $fields ) {
		$select_this = 't.slug';
	} elseif ( 'all_with_object_id' == $fields ) {
		$select_this = 't.*, tt.*, tr.object_id';
	}

	$where = array(
		"tt.taxonomy IN ($taxonomies)",
		"tr.object_id IN ($object_ids)",
	);

	if ( '' !== $args['parent'] ) {
		$where[] = $wpdb->prepare( 'tt.parent = %d', $args['parent'] );
	}

	// Meta query support.
	$meta_query_join = '';
	if ( ! empty( $args['meta_query'] ) ) {
		$mquery = new WP_Meta_Query( $args['meta_query'] );
		$mq_sql = $mquery->get_sql( 'term', 't', 'term_id' );

		$meta_query_join .= $mq_sql['join'];

		// Strip leading AND.
		$where[] = preg_replace( '/^\s*AND/', '', $mq_sql['where'] );
	}

	$where = implode( ' AND ', $where );

	$query = "SELECT $select_this FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id $meta_query_join WHERE $where $orderby $order";

	$objects = false;
	if ( 'all' == $fields || 'all_with_object_id' == $fields ) {
		$_terms = $wpdb->get_results( $query );
		$object_id_index = array();
		foreach ( $_terms as $key => $term ) {
			$term = sanitize_term( $term, $taxonomy, 'raw' );
			$_terms[ $key ] = $term;

			if ( isset( $term->object_id ) ) {
				$object_id_index[ $key ] = $term->object_id;
			}
		}

		update_term_cache( $_terms );
		$_terms = array_map( 'get_term', $_terms );

		// Re-add the object_id data, which is lost when fetching terms from cache.
		if ( 'all_with_object_id' === $fields ) {
			foreach ( $_terms as $key => $_term ) {
				if ( isset( $object_id_index[ $key ] ) ) {
					$_term->object_id = $object_id_index[ $key ];
				}
			}
		}

		$terms = array_merge( $terms, $_terms );
		$objects = true;

	} elseif ( 'ids' == $fields || 'names' == $fields || 'slugs' == $fields ) {
		$_terms = $wpdb->get_col( $query );
		$_field = ( 'ids' == $fields ) ? 'term_id' : 'name';
		foreach ( $_terms as $key => $term ) {
			$_terms[$key] = sanitize_term_field( $_field, $term, $term, $taxonomy, 'raw' );
		}
		$terms = array_merge( $terms, $_terms );
	} elseif ( 'tt_ids' == $fields ) {
		$terms = $wpdb->get_col("SELECT tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id IN ($object_ids) AND tt.taxonomy IN ($taxonomies) $orderby $order");
		foreach ( $terms as $key => $tt_id ) {
			$terms[$key] = sanitize_term_field( 'term_taxonomy_id', $tt_id, 0, $taxonomy, 'raw' ); // 0 should be the term id, however is not needed when using raw context.
		}
	}

	// Update termmeta cache, if necessary.
	if ( $args['update_term_meta_cache'] && ( 'all' === $fields || 'all_with_object_ids' === $fields || 'term_id' === $fields ) ) {
		if ( 'term_id' === $fields ) {
			$term_ids = $fields;
		} else {
			$term_ids = wp_list_pluck( $terms, 'term_id' );
		}

		update_termmeta_cache( $term_ids );
	}

	if ( ! $terms ) {
		$terms = array();
	} elseif ( $objects && 'all_with_object_id' !== $fields ) {
		$_tt_ids = array();
		$_terms = array();
		foreach ( $terms as $term ) {
			if ( in_array( $term->term_taxonomy_id, $_tt_ids ) ) {
				continue;
			}

			$_tt_ids[] = $term->term_taxonomy_id;
			$_terms[] = $term;
		}
		$terms = $_terms;
	} elseif ( ! $objects ) {
		$terms = array_values( array_unique( $terms ) );
	}

	$terms = apply_filters( 'get_object_terms', $terms, $object_id_array, $taxonomy_array, $args );

	return apply_filters( 'wp_get_object_terms', $terms, $object_ids, $taxonomies, $args );
}

function wp_insert_term( $term, $taxonomy, $args = array() ) {
	global $wpdb;

	if ( ! taxonomy_exists($taxonomy) ) {
		return new WP_Error('invalid_taxonomy', 'Invalid taxonomy');
	}

	$term = apply_filters( 'pre_insert_term', $term, $taxonomy );
	if ( is_wp_error( $term ) ) {
		return $term;
	}
	if ( is_int($term) && 0 == $term ) {
		return new WP_Error('invalid_term_id', 'Invalid term ID');
	}
	if ( '' == trim($term) ) {
		return new WP_Error('empty_term_name', 'A name is required for this term');
	}
	$defaults = array( 'alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '');
	$args = wp_parse_args( $args, $defaults );

	if ( $args['parent'] > 0 && ! term_exists( (int) $args['parent'] ) ) {
		return new WP_Error( 'missing_parent', 'Parent term does not exist.' );
	}

	$args['name'] = $term;
	$args['taxonomy'] = $taxonomy;

	// Coerce null description to strings, to avoid database errors.
	$args['description'] = (string) $args['description'];

	$args = sanitize_term($args, $taxonomy, 'db');

	// expected_slashed ($name)
	$name = wp_unslash( $args['name'] );
	$description = wp_unslash( $args['description'] );
	$parent = (int) $args['parent'];

	$slug_provided = ! empty( $args['slug'] );
	if ( ! $slug_provided ) {
		$slug = sanitize_title( $name );
	} else {
		$slug = $args['slug'];
	}

	$term_group = 0;
	if ( $args['alias_of'] ) {
		$alias = get_term_by( 'slug', $args['alias_of'], $taxonomy );
		if ( ! empty( $alias->term_group ) ) {
			// The alias we want is already in a group, so let's use that one.
			$term_group = $alias->term_group;
		} elseif ( ! empty( $alias->term_id ) ) {
			$term_group = $wpdb->get_var("SELECT MAX(term_group) FROM $wpdb->terms") + 1;
			wp_update_term( $alias->term_id, $taxonomy, array(
				'term_group' => $term_group,
			) );
		}
	}

	$name_matches = get_terms( $taxonomy, array(
		'name' => $name,
		'hide_empty' => false,
	) );

	$name_match = null;
	if ( $name_matches ) {
		foreach ( $name_matches as $_match ) {
			if ( strtolower( $name ) === strtolower( $_match->name ) ) {
				$name_match = $_match;
				break;
			}
		}
	}

	if ( $name_match ) {
		$slug_match = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $slug_provided || $name_match->slug === $slug || $slug_match ) {
			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				$siblings = get_terms( $taxonomy, array( 'get' => 'all', 'parent' => $parent ) );

				$existing_term = null;
				if ( $name_match->slug === $slug && in_array( $name, wp_list_pluck( $siblings, 'name' ) ) ) {
					$existing_term = $name_match;
				} elseif ( $slug_match && in_array( $slug, wp_list_pluck( $siblings, 'slug' ) ) ) {
					$existing_term = $slug_match;
				}

				if ( $existing_term ) {
					return new WP_Error( 'term_exists', 'A term with the name provided already exists with this parent.', $existing_term->term_id );
				}
			} else {
				return new WP_Error( 'term_exists', 'A term with the name provided already exists in this taxonomy.', $name_match->term_id );
			}
		}
	}

	$slug = wp_unique_term_slug( $slug, (object) $args );

	if ( false === $wpdb->insert( $wpdb->terms, compact( 'name', 'slug', 'term_group' ) ) ) {
		return new WP_Error( 'db_insert_error', 'Could not insert term into the database', $wpdb->last_error );
	}

	$term_id = (int) $wpdb->insert_id;

	if ( empty($slug) ) {
		$slug = sanitize_title($slug, $term_id);

		do_action( 'edit_terms', $term_id, $taxonomy );
		$wpdb->update( $wpdb->terms, compact( 'slug' ), compact( 'term_id' ) );

		do_action( 'edited_terms', $term_id, $taxonomy );
	}

	$tt_id = $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.term_id = %d", $taxonomy, $term_id ) );

	if ( !empty($tt_id) ) {
		return array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
	}
	$wpdb->insert( $wpdb->term_taxonomy, compact( 'term_id', 'taxonomy', 'description', 'parent') + array( 'count' => 0 ) );
	$tt_id = (int) $wpdb->insert_id;

	$duplicate_term = $wpdb->get_row( $wpdb->prepare( "SELECT t.term_id, tt.term_taxonomy_id FROM $wpdb->terms t INNER JOIN $wpdb->term_taxonomy tt ON ( tt.term_id = t.term_id ) WHERE t.slug = %s AND tt.parent = %d AND tt.taxonomy = %s AND t.term_id < %d AND tt.term_taxonomy_id != %d", $slug, $parent, $taxonomy, $term_id, $tt_id ) );
	if ( $duplicate_term ) {
		$wpdb->delete( $wpdb->terms, array( 'term_id' => $term_id ) );
		$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $tt_id ) );

		$term_id = (int) $duplicate_term->term_id;
		$tt_id   = (int) $duplicate_term->term_taxonomy_id;

		clean_term_cache( $term_id, $taxonomy );
		return array( 'term_id' => $term_id, 'term_taxonomy_id' => $tt_id );
	}

	do_action( "create_term", $term_id, $tt_id, $taxonomy );

	do_action( "create_$taxonomy", $term_id, $tt_id );

	$term_id = apply_filters( 'term_id_filter', $term_id, $tt_id );

	clean_term_cache($term_id, $taxonomy);

	do_action( 'created_term', $term_id, $tt_id, $taxonomy );

	do_action( "created_$taxonomy", $term_id, $tt_id );

	return array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
}

function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
	global $wpdb;

	$object_id = (int) $object_id;

	if ( ! taxonomy_exists($taxonomy) )
		return new WP_Error('invalid_taxonomy', 'Invalid taxonomy');

	if ( !is_array($terms) )
		$terms = array($terms);

	if ( ! $append )
		$old_tt_ids =  wp_get_object_terms($object_id, $taxonomy, array('fields' => 'tt_ids', 'orderby' => 'none'));
	else
		$old_tt_ids = array();

	$tt_ids = array();
	$term_ids = array();
	$new_tt_ids = array();

	foreach ( (array) $terms as $term) {
		if ( !strlen(trim($term)) )
			continue;

		if ( !$term_info = term_exists($term, $taxonomy) ) {
			// Skip if a non-existent term ID is passed.
			if ( is_int($term) )
				continue;
			$term_info = wp_insert_term($term, $taxonomy);
		}
		if ( is_wp_error($term_info) )
			return $term_info;
		$term_ids[] = $term_info['term_id'];
		$tt_id = $term_info['term_taxonomy_id'];
		$tt_ids[] = $tt_id;

		if ( $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d", $object_id, $tt_id ) ) )
			continue;

		do_action( 'add_term_relationship', $object_id, $tt_id );
		$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $object_id, 'term_taxonomy_id' => $tt_id ) );
		do_action( 'added_term_relationship', $object_id, $tt_id );
		$new_tt_ids[] = $tt_id;
	}

	if ( $new_tt_ids )
		wp_update_term_count( $new_tt_ids, $taxonomy );

	if ( ! $append ) {
		$delete_tt_ids = array_diff( $old_tt_ids, $tt_ids );

		if ( $delete_tt_ids ) {
			$in_delete_tt_ids = "'" . implode( "', '", $delete_tt_ids ) . "'";
			$delete_term_ids = $wpdb->get_col( $wpdb->prepare( "SELECT tt.term_id FROM $wpdb->term_taxonomy AS tt WHERE tt.taxonomy = %s AND tt.term_taxonomy_id IN ($in_delete_tt_ids)", $taxonomy ) );
			$delete_term_ids = array_map( 'intval', $delete_term_ids );

			$remove = wp_remove_object_terms( $object_id, $delete_term_ids, $taxonomy );
			if ( is_wp_error( $remove ) ) {
				return $remove;
			}
		}
	}

	$t = get_taxonomy($taxonomy);
	if ( ! $append && isset($t->sort) && $t->sort ) {
		$values = array();
		$term_order = 0;
		$final_tt_ids = wp_get_object_terms($object_id, $taxonomy, array('fields' => 'tt_ids'));
		foreach ( $tt_ids as $tt_id )
			if ( in_array($tt_id, $final_tt_ids) )
				$values[] = $wpdb->prepare( "(%d, %d, %d)", $object_id, $tt_id, ++$term_order);
		if ( $values )
			if ( false === $wpdb->query( "INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order) VALUES " . join( ',', $values ) . " ON DUPLICATE KEY UPDATE term_order = VALUES(term_order)" ) )
				return new WP_Error( 'db_insert_error', 'Could not insert term relationship into the database', $wpdb->last_error );
	}

	wp_cache_delete( $object_id, $taxonomy . '_relationships' );
	do_action( 'set_object_terms', $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids );
	return $tt_ids;
}

function wp_add_object_terms( $object_id, $terms, $taxonomy ) {
	return wp_set_object_terms( $object_id, $terms, $taxonomy, true );
}

function wp_remove_object_terms( $object_id, $terms, $taxonomy ) {
	global $wpdb;

	$object_id = (int) $object_id;

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy' );
	}

	if ( ! is_array( $terms ) ) {
		$terms = array( $terms );
	}

	$tt_ids = array();

	foreach ( (array) $terms as $term ) {
		if ( ! strlen( trim( $term ) ) ) {
			continue;
		}

		if ( ! $term_info = term_exists( $term, $taxonomy ) ) {
			// Skip if a non-existent term ID is passed.
			if ( is_int( $term ) ) {
				continue;
			}
		}

		if ( is_wp_error( $term_info ) ) {
			return $term_info;
		}

		$tt_ids[] = $term_info['term_taxonomy_id'];
	}

	if ( $tt_ids ) {
		$in_tt_ids = "'" . implode( "', '", $tt_ids ) . "'";

		do_action( 'delete_term_relationships', $object_id, $tt_ids );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id IN ($in_tt_ids)", $object_id ) );

		wp_cache_delete( $object_id, $taxonomy . '_relationships' );

		do_action( 'deleted_term_relationships', $object_id, $tt_ids );

		wp_update_term_count( $tt_ids, $taxonomy );

		return (bool) $deleted;
	}

	return false;
}

function wp_unique_term_slug( $slug, $term ) {
	global $wpdb;

	$needs_suffix = true;
	$original_slug = $slug;

	// As of 4.1, duplicate slugs are allowed as long as they're in different taxonomies.
	if ( ! term_exists( $slug ) || get_option( 'db_version' ) >= 30133 && ! get_term_by( 'slug', $slug, $term->taxonomy ) ) {
		$needs_suffix = false;
	}

	$parent_suffix = '';
	if ( $needs_suffix && is_taxonomy_hierarchical( $term->taxonomy ) && ! empty( $term->parent ) ) {
		$the_parent = $term->parent;
		while ( ! empty($the_parent) ) {
			$parent_term = get_term($the_parent, $term->taxonomy);
			if ( is_wp_error($parent_term) || empty($parent_term) )
				break;
			$parent_suffix .= '-' . $parent_term->slug;
			if ( ! term_exists( $slug . $parent_suffix ) ) {
				break;
			}

			if ( empty($parent_term->parent) )
				break;
			$the_parent = $parent_term->parent;
		}
	}

	if ( apply_filters( 'wp_unique_term_slug_is_bad_slug', $needs_suffix, $slug, $term ) ) {
		if ( $parent_suffix ) {
			$slug .= $parent_suffix;
		} else {
			if ( ! empty( $term->term_id ) )
				$query = $wpdb->prepare( "SELECT slug FROM $wpdb->terms WHERE slug = %s AND term_id != %d", $slug, $term->term_id );
			else
				$query = $wpdb->prepare( "SELECT slug FROM $wpdb->terms WHERE slug = %s", $slug );

			if ( $wpdb->get_var( $query ) ) {
				$num = 2;
				do {
					$alt_slug = $slug . "-$num";
					$num++;
					$slug_check = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM $wpdb->terms WHERE slug = %s", $alt_slug ) );
				} while ( $slug_check );
				$slug = $alt_slug;
			}
		}
	}

	return apply_filters( 'wp_unique_term_slug', $slug, $term, $original_slug );
}

function wp_update_term( $term_id, $taxonomy, $args = array() ) {
	global $wpdb;

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy' );
	}

	$term_id = (int) $term_id;

	// First, get all of the original args
	$term = get_term( $term_id, $taxonomy );

	if ( is_wp_error( $term ) ) {
		return $term;
	}

	if ( ! $term ) {
		return new WP_Error( 'invalid_term', 'Empty Term' );
	}

	$term = (array) $term->data;

	// Escape data pulled from DB.
	$term = wp_slash( $term );

	// Merge old and new args with new args overwriting old ones.
	$args = array_merge($term, $args);

	$defaults = array( 'alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '');
	$args = wp_parse_args($args, $defaults);
	$args = sanitize_term($args, $taxonomy, 'db');
	$parsed_args = $args;

	// expected_slashed ($name)
	$name = wp_unslash( $args['name'] );
	$description = wp_unslash( $args['description'] );

	$parsed_args['name'] = $name;
	$parsed_args['description'] = $description;

	if ( '' == trim($name) )
		return new WP_Error('empty_term_name', 'A name is required for this term');

	if ( $parsed_args['parent'] > 0 && ! term_exists( (int) $parsed_args['parent'] ) ) {
		return new WP_Error( 'missing_parent', 'Parent term does not exist.' );
	}

	$empty_slug = false;
	if ( empty( $args['slug'] ) ) {
		$empty_slug = true;
		$slug = sanitize_title($name);
	} else {
		$slug = $args['slug'];
	}

	$parsed_args['slug'] = $slug;

	$term_group = isset( $parsed_args['term_group'] ) ? $parsed_args['term_group'] : 0;
	if ( $args['alias_of'] ) {
		$alias = get_term_by( 'slug', $args['alias_of'], $taxonomy );
		if ( ! empty( $alias->term_group ) ) {
			// The alias we want is already in a group, so let's use that one.
			$term_group = $alias->term_group;
		} elseif ( ! empty( $alias->term_id ) ) {
			/*
			 * The alias is not in a group, so we create a new one
			 * and add the alias to it.
			 */
			$term_group = $wpdb->get_var("SELECT MAX(term_group) FROM $wpdb->terms") + 1;

			wp_update_term( $alias->term_id, $taxonomy, array(
				'term_group' => $term_group,
			) );
		}

		$parsed_args['term_group'] = $term_group;
	}

	$parent = apply_filters( 'wp_update_term_parent', $args['parent'], $term_id, $taxonomy, $parsed_args, $args );

	$duplicate = get_term_by( 'slug', $slug, $taxonomy );
	if ( $duplicate && $duplicate->term_id != $term_id ) {

		if ( $empty_slug || ( $parent != $term['parent']) )
			$slug = wp_unique_term_slug($slug, (object) $args);
		else
			return new WP_Error('duplicate_term_slug', sprintf('The slug &#8220;%s&#8221; is already in use by another term', $slug));
	}

	$tt_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_taxonomy_id FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.term_id = %d", $taxonomy, $term_id) );

	$_term_id = _split_shared_term( $term_id, $tt_id );
	if ( ! is_wp_error( $_term_id ) ) {
		$term_id = $_term_id;
	}

	do_action( 'edit_terms', $term_id, $taxonomy );
	$wpdb->update($wpdb->terms, compact( 'name', 'slug', 'term_group' ), compact( 'term_id' ) );
	if ( empty($slug) ) {
		$slug = sanitize_title($name, $term_id);
		$wpdb->update( $wpdb->terms, compact( 'slug' ), compact( 'term_id' ) );
	}

	do_action( 'edited_terms', $term_id, $taxonomy );

	do_action( 'edit_term_taxonomy', $tt_id, $taxonomy );

	$wpdb->update( $wpdb->term_taxonomy, compact( 'term_id', 'taxonomy', 'description', 'parent' ), array( 'term_taxonomy_id' => $tt_id ) );

	do_action( 'edited_term_taxonomy', $tt_id, $taxonomy );

	$objects = $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $tt_id ) );
	$tax_object = get_taxonomy( $taxonomy );
	foreach ( $tax_object->object_type as $object_type ) {
		clean_object_term_cache( $objects, $object_type );
	}

	do_action( "edit_term", $term_id, $tt_id, $taxonomy );

	do_action( "edit_$taxonomy", $term_id, $tt_id );

	$term_id = apply_filters( 'term_id_filter', $term_id, $tt_id );

	clean_term_cache($term_id, $taxonomy);

	do_action( "edited_term", $term_id, $tt_id, $taxonomy );

	do_action( "edited_$taxonomy", $term_id, $tt_id );

	return array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
}

function wp_defer_term_counting($defer=null) {
	static $_defer = false;

	if ( is_bool($defer) ) {
		$_defer = $defer;
		if ( !$defer )
			wp_update_term_count( null, null, true );
	}

	return $_defer;
}

function wp_update_term_count( $terms, $taxonomy, $do_deferred = false ) {
	static $_deferred = array();

	if ( $do_deferred ) {
		foreach ( (array) array_keys($_deferred) as $tax ) {
			wp_update_term_count_now( $_deferred[$tax], $tax );
			unset( $_deferred[$tax] );
		}
	}

	if ( empty($terms) )
		return false;

	if ( !is_array($terms) )
		$terms = array($terms);

	if ( wp_defer_term_counting() ) {
		if ( !isset($_deferred[$taxonomy]) )
			$_deferred[$taxonomy] = array();
		$_deferred[$taxonomy] = array_unique( array_merge($_deferred[$taxonomy], $terms) );
		return true;
	}

	return wp_update_term_count_now( $terms, $taxonomy );
}

function wp_update_term_count_now( $terms, $taxonomy ) {
	$terms = array_map('intval', $terms);

	$taxonomy = get_taxonomy($taxonomy);
	if ( !empty($taxonomy->update_count_callback) ) {
		call_user_func($taxonomy->update_count_callback, $terms, $taxonomy);
	} else {
		$object_types = (array) $taxonomy->object_type;
		foreach ( $object_types as &$object_type ) {
			if ( 0 === strpos( $object_type, 'attachment:' ) )
				list( $object_type ) = explode( ':', $object_type );
		}

		if ( $object_types == array_filter( $object_types, 'post_type_exists' ) ) {
			// Only post types are attached to this taxonomy
			_update_post_term_count( $terms, $taxonomy );
		} else {
			// Default count updater
			_update_generic_term_count( $terms, $taxonomy );
		}
	}

	clean_term_cache($terms, '', false);

	return true;
}

function clean_object_term_cache($object_ids, $object_type) {
	global $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
		return;
	}

	if ( !is_array($object_ids) )
		$object_ids = array($object_ids);

	$taxonomies = get_object_taxonomies( $object_type );

	foreach ( $object_ids as $id ) {
		foreach ( $taxonomies as $taxonomy ) {
			wp_cache_delete($id, "{$taxonomy}_relationships");
		}
	}

	do_action( 'clean_object_term_cache', $object_ids, $object_type );
}

function clean_term_cache($ids, $taxonomy = '', $clean_taxonomy = true) {
	global $wpdb, $_wp_suspend_cache_invalidation;

	if ( ! empty( $_wp_suspend_cache_invalidation ) ) {
		return;
	}

	if ( !is_array($ids) )
		$ids = array($ids);

	$taxonomies = array();
	if ( empty($taxonomy) ) {
		$tt_ids = array_map('intval', $ids);
		$tt_ids = implode(', ', $tt_ids);
		$terms = $wpdb->get_results("SELECT term_id, taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id IN ($tt_ids)");
		$ids = array();
		foreach ( (array) $terms as $term ) {
			$taxonomies[] = $term->taxonomy;
			$ids[] = $term->term_id;
			wp_cache_delete( $term->term_id, 'terms' );
		}
		$taxonomies = array_unique($taxonomies);
	} else {
		$taxonomies = array($taxonomy);
		foreach ( $taxonomies as $taxonomy ) {
			foreach ( $ids as $id ) {
				wp_cache_delete( $id, 'terms' );
			}
		}
	}

	foreach ( $taxonomies as $taxonomy ) {
		if ( $clean_taxonomy ) {
			wp_cache_delete('all_ids', $taxonomy);
			wp_cache_delete('get', $taxonomy);
			delete_option("{$taxonomy}_children");
			_get_term_hierarchy($taxonomy);
		}

		do_action( 'clean_term_cache', $ids, $taxonomy, $clean_taxonomy );
	}

	wp_cache_set( 'last_changed', microtime(), 'terms' );
}

function get_object_term_cache( $id, $taxonomy ) {
	return wp_cache_get( $id, "{$taxonomy}_relationships" );
}

function update_object_term_cache($object_ids, $object_type) {
	if ( empty($object_ids) )
		return;

	if ( !is_array($object_ids) )
		$object_ids = explode(',', $object_ids);

	$object_ids = array_map('intval', $object_ids);

	$taxonomies = get_object_taxonomies($object_type);

	$ids = array();
	foreach ( (array) $object_ids as $id ) {
		foreach ( $taxonomies as $taxonomy ) {
			if ( false === wp_cache_get($id, "{$taxonomy}_relationships") ) {
				$ids[] = $id;
				break;
			}
		}
	}

	if ( empty( $ids ) )
		return false;

	$terms = wp_get_object_terms( $ids, $taxonomies, array(
		'fields' => 'all_with_object_id',
		'orderby' => 'name',
		'update_term_meta_cache' => false,
	) );

	$object_terms = array();
	foreach ( (array) $terms as $term )
		$object_terms[$term->object_id][$term->taxonomy][] = $term;

	foreach ( $ids as $id ) {
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! isset($object_terms[$id][$taxonomy]) ) {
				if ( !isset($object_terms[$id]) )
					$object_terms[$id] = array();
				$object_terms[$id][$taxonomy] = array();
			}
		}
	}

	foreach ( $object_terms as $id => $value ) {
		foreach ( $value as $taxonomy => $terms ) {
			wp_cache_add( $id, $terms, "{$taxonomy}_relationships" );
		}
	}
}

function update_term_cache( $terms, $taxonomy = '' ) {
	foreach ( (array) $terms as $term ) {
		// Create a copy in case the array was passed by reference.
		$_term = clone $term;

		// Object ID should not be cached.
		unset( $_term->object_id );

		wp_cache_add( $term->term_id, $_term, 'terms' );
	}
}

function _get_term_hierarchy( $taxonomy ) {
	if ( !is_taxonomy_hierarchical($taxonomy) )
		return array();
	$children = get_option("{$taxonomy}_children");

	if ( is_array($children) )
		return $children;
	$children = array();
	$terms = get_terms($taxonomy, array('get' => 'all', 'orderby' => 'id', 'fields' => 'id=>parent'));
	foreach ( $terms as $term_id => $parent ) {
		if ( $parent > 0 )
			$children[$parent][] = $term_id;
	}
	update_option("{$taxonomy}_children", $children);

	return $children;
}

function _get_term_children( $term_id, $terms, $taxonomy, &$ancestors = array() ) {
	$empty_array = array();
	if ( empty($terms) )
		return $empty_array;

	$term_list = array();
	$has_children = _get_term_hierarchy($taxonomy);

	if  ( ( 0 != $term_id ) && ! isset($has_children[$term_id]) )
		return $empty_array;

	// Include the term itself in the ancestors array, so we can properly detect when a loop has occurred.
	if ( empty( $ancestors ) ) {
		$ancestors[ $term_id ] = 1;
	}

	foreach ( (array) $terms as $term ) {
		$use_id = false;
		if ( !is_object($term) ) {
			$term = get_term($term, $taxonomy);
			if ( is_wp_error( $term ) )
				return $term;
			$use_id = true;
		}

		// Don't recurse if we've already identified the term as a child - this indicates a loop.
		if ( isset( $ancestors[ $term->term_id ] ) ) {
			continue;
		}

		if ( $term->parent == $term_id ) {
			if ( $use_id )
				$term_list[] = $term->term_id;
			else
				$term_list[] = $term;

			if ( !isset($has_children[$term->term_id]) )
				continue;

			$ancestors[ $term->term_id ] = 1;

			if ( $children = _get_term_children( $term->term_id, $terms, $taxonomy, $ancestors) )
				$term_list = array_merge($term_list, $children);
		}
	}

	return $term_list;
}

function _pad_term_counts( &$terms, $taxonomy ) {
	global $wpdb;

	// This function only works for hierarchical taxonomies like post categories.
	if ( !is_taxonomy_hierarchical( $taxonomy ) )
		return;

	$term_hier = _get_term_hierarchy($taxonomy);

	if ( empty($term_hier) )
		return;

	$term_items = array();
	$terms_by_id = array();
	$term_ids = array();

	foreach ( (array) $terms as $key => $term ) {
		$terms_by_id[$term->term_id] = & $terms[$key];
		$term_ids[$term->term_taxonomy_id] = $term->term_id;
	}

	// Get the object and term ids and stick them in a lookup table.
	$tax_obj = get_taxonomy($taxonomy);
	$object_types = esc_sql($tax_obj->object_type);
	$results = $wpdb->get_results("SELECT object_id, term_taxonomy_id FROM $wpdb->term_relationships INNER JOIN $wpdb->posts ON object_id = ID WHERE term_taxonomy_id IN (" . implode(',', array_keys($term_ids)) . ") AND post_type IN ('" . implode("', '", $object_types) . "') AND post_status = 'publish'");
	foreach ( $results as $row ) {
		$id = $term_ids[$row->term_taxonomy_id];
		$term_items[$id][$row->object_id] = isset($term_items[$id][$row->object_id]) ? ++$term_items[$id][$row->object_id] : 1;
	}

	// Touch every ancestor's lookup row for each post in each term.
	foreach ( $term_ids as $term_id ) {
		$child = $term_id;
		$ancestors = array();
		while ( !empty( $terms_by_id[$child] ) && $parent = $terms_by_id[$child]->parent ) {
			$ancestors[] = $child;
			if ( !empty( $term_items[$term_id] ) )
				foreach ( $term_items[$term_id] as $item_id => $touches ) {
					$term_items[$parent][$item_id] = isset($term_items[$parent][$item_id]) ? ++$term_items[$parent][$item_id]: 1;
				}
			$child = $parent;

			if ( in_array( $parent, $ancestors ) ) {
				break;
			}
		}
	}

	// Transfer the touched cells.
	foreach ( (array) $term_items as $id => $items )
		if ( isset($terms_by_id[$id]) )
			$terms_by_id[$id]->count = count($items);
}

function _update_post_term_count( $terms, $taxonomy ) {
	global $wpdb;

	$object_types = (array) $taxonomy->object_type;

	foreach ( $object_types as &$object_type )
		list( $object_type ) = explode( ':', $object_type );

	$object_types = array_unique( $object_types );

	if ( false !== ( $check_attachments = array_search( 'attachment', $object_types ) ) ) {
		unset( $object_types[ $check_attachments ] );
		$check_attachments = true;
	}

	if ( $object_types )
		$object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );

	foreach ( (array) $terms as $term ) {
		$count = 0;

		if ( $check_attachments )
			$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts p1 WHERE p1.ID = $wpdb->term_relationships.object_id AND ( post_status = 'publish' OR ( post_status = 'inherit' AND post_parent > 0 AND ( SELECT post_status FROM $wpdb->posts WHERE ID = p1.post_parent ) = 'publish' ) ) AND post_type = 'attachment' AND term_taxonomy_id = %d", $term ) );

		if ( $object_types )
			$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND post_type IN ('" . implode("', '", $object_types ) . "') AND term_taxonomy_id = %d", $term ) );

		do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
		$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );

		do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
	}
}

function _update_generic_term_count( $terms, $taxonomy ) {
	global $wpdb;

	foreach ( (array) $terms as $term ) {
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );

		do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
		$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );

		do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
	}
}

function _split_shared_term( $term_id, $term_taxonomy_id, $record = true ) {
	global $wpdb;

	if ( is_object( $term_id ) ) {
		$shared_term = $term_id;
		$term_id = intval( $shared_term->term_id );
	}

	if ( is_object( $term_taxonomy_id ) ) {
		$term_taxonomy = $term_taxonomy_id;
		$term_taxonomy_id = intval( $term_taxonomy->term_taxonomy_id );
	}

	$shared_tt_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_taxonomy tt WHERE tt.term_id = %d AND tt.term_taxonomy_id != %d", $term_id, $term_taxonomy_id ) );

	if ( ! $shared_tt_count ) {
		return $term_id;
	}

	$check_term_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d", $term_taxonomy_id ) );
	if ( $check_term_id != $term_id ) {
		return $check_term_id;
	}

	if ( empty( $shared_term ) ) {
		$shared_term = $wpdb->get_row( $wpdb->prepare( "SELECT t.* FROM $wpdb->terms t WHERE t.term_id = %d", $term_id ) );
	}

	$new_term_data = array(
		'name' => $shared_term->name,
		'slug' => $shared_term->slug,
		'term_group' => $shared_term->term_group,
	);

	if ( false === $wpdb->insert( $wpdb->terms, $new_term_data ) ) {
		return new WP_Error( 'db_insert_error', 'Could not split shared term.', $wpdb->last_error );
	}

	$new_term_id = (int) $wpdb->insert_id;

	$wpdb->update( $wpdb->term_taxonomy,
		array( 'term_id' => $new_term_id ),
		array( 'term_taxonomy_id' => $term_taxonomy_id )
	);

	if ( empty( $term_taxonomy ) ) {
		$term_taxonomy = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d", $term_taxonomy_id ) );
	}

	$children_tt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE parent = %d AND taxonomy = %s", $term_id, $term_taxonomy->taxonomy ) );
	if ( ! empty( $children_tt_ids ) ) {
		foreach ( $children_tt_ids as $child_tt_id ) {
			$wpdb->update( $wpdb->term_taxonomy,
				array( 'parent' => $new_term_id ),
				array( 'term_taxonomy_id' => $child_tt_id )
			);
			clean_term_cache( $term_id, $term_taxonomy->taxonomy );
		}
	} else {
		clean_term_cache( $new_term_id, $term_taxonomy->taxonomy );
	}

	$shared_term_taxonomies = $wpdb->get_row( $wpdb->prepare( "SELECT taxonomy FROM $wpdb->term_taxonomy WHERE term_id = %d", $term_id ) );
	if ( $shared_term_taxonomies ) {
		foreach ( $shared_term_taxonomies as $shared_term_taxonomy ) {
			clean_term_cache( $term_id, $shared_term_taxonomy );
		}
	}

	if ( $record ) {
		$split_term_data = get_option( '_split_terms', array() );
		if ( ! isset( $split_term_data[ $term_id ] ) ) {
			$split_term_data[ $term_id ] = array();
		}

		$split_term_data[ $term_id ][ $term_taxonomy->taxonomy ] = $new_term_id;
		update_option( '_split_terms', $split_term_data );
	}

	$shared_terms_exist = $wpdb->get_results(
		"SELECT tt.term_id, t.*, count(*) as term_tt_count FROM {$wpdb->term_taxonomy} tt
		 LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		 GROUP BY t.term_id
		 HAVING term_tt_count > 1
		 LIMIT 1"
	);
	if ( ! $shared_terms_exist ) {
		update_option( 'finished_splitting_shared_terms', true );
	}

	do_action( 'split_shared_term', $term_id, $new_term_id, $term_taxonomy_id, $term_taxonomy->taxonomy );

	return $new_term_id;
}

function _wp_batch_split_terms() {
	global $wpdb;

	$lock_name = 'term_split.lock';

	$lock_result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", $lock_name, time() ) );

	if ( ! $lock_result ) {
		$lock_result = get_option( $lock_name );

		if ( ! $lock_result || ( $lock_result > ( time() - HOUR_IN_SECONDS ) ) ) {
			wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'wp_split_shared_term_batch' );
			return;
		}
	}

	update_option( $lock_name, time() );
	$shared_terms = $wpdb->get_results(
		"SELECT tt.term_id, t.*, count(*) as term_tt_count FROM {$wpdb->term_taxonomy} tt
		 LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		 GROUP BY t.term_id
		 HAVING term_tt_count > 1
		 LIMIT 10"
	);

	if ( ! $shared_terms ) {
		update_option( 'finished_splitting_shared_terms', true );
		delete_option( $lock_name );
		return;
	}

	wp_schedule_single_event( time() + ( 2 * MINUTE_IN_SECONDS ), 'wp_split_shared_term_batch' );

	$_shared_terms = array();
	foreach ( $shared_terms as $shared_term ) {
		$term_id = intval( $shared_term->term_id );
		$_shared_terms[ $term_id ] = $shared_term;
	}
	$shared_terms = $_shared_terms;

	$shared_term_ids = implode( ',', array_keys( $shared_terms ) );
	$shared_tts = $wpdb->get_results( "SELECT * FROM {$wpdb->term_taxonomy} WHERE `term_id` IN ({$shared_term_ids})" );

	$split_term_data = get_option( '_split_terms', array() );
	$skipped_first_term = $taxonomies = array();
	foreach ( $shared_tts as $shared_tt ) {
		$term_id = intval( $shared_tt->term_id );

		if ( ! isset( $skipped_first_term[ $term_id ] ) ) {
			$skipped_first_term[ $term_id ] = 1;
			continue;
		}

		if ( ! isset( $split_term_data[ $term_id ] ) ) {
			$split_term_data[ $term_id ] = array();
		}

		if ( ! isset( $taxonomies[ $shared_tt->taxonomy ] ) ) {
			$taxonomies[ $shared_tt->taxonomy ] = 1;
		}

		// Split the term.
		$split_term_data[ $term_id ][ $shared_tt->taxonomy ] = _split_shared_term( $shared_terms[ $term_id ], $shared_tt, false );
	}

	foreach ( array_keys( $taxonomies ) as $tax ) {
		delete_option( "{$tax}_children" );
		_get_term_hierarchy( $tax );
	}

	update_option( '_split_terms', $split_term_data );

	delete_option( $lock_name );
}

function _wp_check_for_scheduled_split_terms() {
	if ( ! get_option( 'finished_splitting_shared_terms' ) && ! wp_next_scheduled( 'wp_split_shared_term_batch' ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wp_split_shared_term_batch' );
	}
}

function _wp_check_split_default_terms( $term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
	if ( 'category' != $taxonomy ) {
		return;
	}

	foreach ( array( 'default_category', 'default_link_category', 'default_email_category' ) as $option ) {
		if ( $term_id == get_option( $option, -1 ) ) {
			update_option( $option, $new_term_id );
		}
	}
}

function _wp_check_split_terms_in_menus( $term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
	global $wpdb;
	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT m1.post_id
		FROM {$wpdb->postmeta} AS m1
			INNER JOIN {$wpdb->postmeta} AS m2 ON ( m2.post_id = m1.post_id )
			INNER JOIN {$wpdb->postmeta} AS m3 ON ( m3.post_id = m1.post_id )
		WHERE ( m1.meta_key = '_menu_item_type' AND m1.meta_value = 'taxonomy' )
			AND ( m2.meta_key = '_menu_item_object' AND m2.meta_value = '%s' )
			AND ( m3.meta_key = '_menu_item_object_id' AND m3.meta_value = %d )",
		$taxonomy,
		$term_id
	) );

	if ( $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_menu_item_object_id', $new_term_id, $term_id );
		}
	}
}

function _wp_check_split_nav_menu_terms( $term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
	if ( 'nav_menu' !== $taxonomy ) {
		return;
	}
	$locations = get_nav_menu_locations();
	foreach ( $locations as $location => $menu_id ) {
		if ( $term_id == $menu_id ) {
			$locations[ $location ] = $new_term_id;
		}
	}
	set_theme_mod( 'nav_menu_locations', $locations );
}

function wp_get_split_terms( $old_term_id ) {
	$split_terms = get_option( '_split_terms', array() );

	$terms = array();
	if ( isset( $split_terms[ $old_term_id ] ) ) {
		$terms = $split_terms[ $old_term_id ];
	}

	return $terms;
}

function wp_get_split_term( $old_term_id, $taxonomy ) {
	$split_terms = wp_get_split_terms( $old_term_id );

	$term_id = false;
	if ( isset( $split_terms[ $taxonomy ] ) ) {
		$term_id = (int) $split_terms[ $taxonomy ];
	}

	return $term_id;
}

function wp_term_is_shared( $term_id ) {
	global $wpdb;

	if ( get_option( 'finished_splitting_shared_terms' ) ) {
		return false;
	}

	$tt_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_taxonomy WHERE term_id = %d", $term_id ) );

	return $tt_count > 1;
}

function get_term_link( $term, $taxonomy = '' ) {
	global $wp_rewrite;

	if ( !is_object($term) ) {
		if ( is_int( $term ) ) {
			$term = get_term( $term, $taxonomy );
		} else {
			$term = get_term_by( 'slug', $term, $taxonomy );
		}
	}

	if ( !is_object($term) )
		$term = new WP_Error('invalid_term', 'Empty Term');

	if ( is_wp_error( $term ) )
		return $term;

	$taxonomy = $term->taxonomy;

	$termlink = $wp_rewrite->get_extra_permastruct($taxonomy);

	$slug = $term->slug;
	$t = get_taxonomy($taxonomy);

	if ( empty($termlink) ) {
		if ( 'category' == $taxonomy )
			$termlink = '?cat=' . $term->term_id;
		elseif ( $t->query_var )
			$termlink = "?$t->query_var=$slug";
		else
			$termlink = "?taxonomy=$taxonomy&term=$slug";
		$termlink = home_url($termlink);
	} else {
		if ( $t->rewrite['hierarchical'] ) {
			$hierarchical_slugs = array();
			$ancestors = get_ancestors( $term->term_id, $taxonomy, 'taxonomy' );
			foreach ( (array)$ancestors as $ancestor ) {
				$ancestor_term = get_term($ancestor, $taxonomy);
				$hierarchical_slugs[] = $ancestor_term->slug;
			}
			$hierarchical_slugs = array_reverse($hierarchical_slugs);
			$hierarchical_slugs[] = $slug;
			$termlink = str_replace("%$taxonomy%", implode('/', $hierarchical_slugs), $termlink);
		} else {
			$termlink = str_replace("%$taxonomy%", $slug, $termlink);
		}
		$termlink = home_url( user_trailingslashit($termlink, 'category') );
	}
	if ( 'post_tag' == $taxonomy ) {

		$termlink = apply_filters( 'tag_link', $termlink, $term->term_id );
	} elseif ( 'category' == $taxonomy ) {
		$termlink = apply_filters( 'category_link', $termlink, $term->term_id );
	}

	return apply_filters( 'term_link', $termlink, $term, $taxonomy );
}

function the_taxonomies( $args = array() ) {
	$defaults = array(
		'post' => 0,
		'before' => '',
		'sep' => ' ',
		'after' => '',
	);

	$r = wp_parse_args( $args, $defaults );

	echo $r['before'] . join( $r['sep'], get_the_taxonomies( $r['post'], $r ) ) . $r['after'];
}

function get_the_taxonomies( $post = 0, $args = array() ) {
	$post = get_post( $post );

	$args = wp_parse_args( $args, array(
		'template' => '%s: %l.',
		'term_template' => '<a href="%1$s">%2$s</a>',
	) );

	$taxonomies = array();

	if ( ! $post ) {
		return $taxonomies;
	}

	foreach ( get_object_taxonomies( $post ) as $taxonomy ) {
		$t = (array) get_taxonomy( $taxonomy );
		if ( empty( $t['label'] ) ) {
			$t['label'] = $taxonomy;
		}
		if ( empty( $t['args'] ) ) {
			$t['args'] = array();
		}
		if ( empty( $t['template'] ) ) {
			$t['template'] = $args['template'];
		}
		if ( empty( $t['term_template'] ) ) {
			$t['term_template'] = $args['term_template'];
		}

		$terms = get_object_term_cache( $post->ID, $taxonomy );
		if ( false === $terms ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy, $t['args'] );
		}
		$links = array();

		foreach ( $terms as $term ) {
			$links[] = wp_sprintf( $t['term_template'], esc_attr( get_term_link( $term ) ), $term->name );
		}
		if ( $links ) {
			$taxonomies[$taxonomy] = wp_sprintf( $t['template'], $t['label'], $links, $terms );
		}
	}
	return $taxonomies;
}

function get_post_taxonomies( $post = 0 ) {
	$post = get_post( $post );

	return get_object_taxonomies($post);
}

function is_object_in_term( $object_id, $taxonomy, $terms = null ) {
	if ( !$object_id = (int) $object_id )
		return new WP_Error( 'invalid_object', 'Invalid object ID' );

	$object_terms = get_object_term_cache( $object_id, $taxonomy );
	if ( false === $object_terms ) {
		$object_terms = wp_get_object_terms( $object_id, $taxonomy, array( 'update_term_meta_cache' => false ) );
		wp_cache_set( $object_id, $object_terms, "{$taxonomy}_relationships" );
	}

	if ( is_wp_error( $object_terms ) )
		return $object_terms;
	if ( empty( $object_terms ) )
		return false;
	if ( empty( $terms ) )
		return ( !empty( $object_terms ) );

	$terms = (array) $terms;

	if ( $ints = array_filter( $terms, 'is_int' ) )
		$strs = array_diff( $terms, $ints );
	else
		$strs =& $terms;

	foreach ( $object_terms as $object_term ) {
		if ( $ints && in_array( $object_term->term_id, $ints ) ) {
			return true;
		}
		if ( $strs ) {
			$numeric_strs = array_map( 'intval', array_filter( $strs, 'is_numeric' ) );
			if ( in_array( $object_term->term_id, $numeric_strs, true ) ) {
				return true;
			}

			if ( in_array( $object_term->name, $strs ) ) return true;
			if ( in_array( $object_term->slug, $strs ) ) return true;
		}
	}

	return false;
}

function is_object_in_taxonomy( $object_type, $taxonomy ) {
	$taxonomies = get_object_taxonomies( $object_type );
	if ( empty( $taxonomies ) ) {
		return false;
	}
	return in_array( $taxonomy, $taxonomies );
}

function get_ancestors( $object_id = 0, $object_type = '', $resource_type = '' ) {
	$object_id = (int) $object_id;

	$ancestors = array();

	if ( empty( $object_id ) ) {
		return apply_filters( 'get_ancestors', $ancestors, $object_id, $object_type, $resource_type );
	}

	if ( ! $resource_type ) {
		if ( is_taxonomy_hierarchical( $object_type ) ) {
			$resource_type = 'taxonomy';
		} elseif ( post_type_exists( $object_type ) ) {
			$resource_type = 'post_type';
		}
	}

	if ( 'taxonomy' === $resource_type ) {
		$term = get_term($object_id, $object_type);
		while ( ! is_wp_error($term) && ! empty( $term->parent ) && ! in_array( $term->parent, $ancestors ) ) {
			$ancestors[] = (int) $term->parent;
			$term = get_term($term->parent, $object_type);
		}
	} elseif ( 'post_type' === $resource_type ) {
		$ancestors = get_post_ancestors($object_id);
	}

	return apply_filters( 'get_ancestors', $ancestors, $object_id, $object_type, $resource_type );
}

function wp_get_term_taxonomy_parent_id( $term_id, $taxonomy ) {
	$term = get_term( $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return false;
	}
	return (int) $term->parent;
}

function wp_check_term_hierarchy_for_loops( $parent, $term_id, $taxonomy ) {

	if ( !$parent )
		return 0;

	if ( $parent == $term_id )
		return 0;

	if ( !$loop = wp_find_hierarchy_loop( 'wp_get_term_taxonomy_parent_id', $term_id, $parent, array( $taxonomy ) ) )
		return $parent;

	if ( isset( $loop[$term_id] ) )
		return 0;

	foreach ( array_keys( $loop ) as $loop_member )
		wp_update_term( $loop_member, $taxonomy, array( 'parent' => 0 ) );

	return $parent;
}
