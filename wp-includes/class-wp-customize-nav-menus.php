<?php

final class WP_Customize_Nav_Menus {

	public $manager;

	public $previewed_menus;

	public function __construct( $manager ) {
		$this->previewed_menus = array();
		$this->manager         = $manager;

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		add_filter( 'customize_refresh_nonces', array( $this, 'filter_nonces' ) );
		add_action( 'wp_ajax_load-available-menu-items-customizer', array( $this, 'ajax_load_available_items' ) );
		add_action( 'wp_ajax_search-available-menu-items-customizer', array( $this, 'ajax_search_available_items' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Needs to run after core Navigation section is set up.
		add_action( 'customize_register', array( $this, 'customize_register' ), 11 );

		add_filter( 'customize_dynamic_setting_args', array( $this, 'filter_dynamic_setting_args' ), 10, 2 );
		add_filter( 'customize_dynamic_setting_class', array( $this, 'filter_dynamic_setting_class' ), 10, 3 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'print_templates' ) );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'available_items_template' ) );
		add_action( 'customize_preview_init', array( $this, 'customize_preview_init' ) );

		// Selective Refresh partials.
		add_filter( 'customize_dynamic_partial_args', array( $this, 'customize_dynamic_partial_args' ), 10, 2 );
	}

	public function filter_nonces( $nonces ) {
		$nonces['customize-menus'] = wp_create_nonce( 'customize-menus' );
		return $nonces;
	}

	public function ajax_load_available_items() {
		check_ajax_referer( 'customize-menus', 'customize-menus-nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( -1 );
		}

		if ( empty( $_POST['type'] ) || empty( $_POST['object'] ) ) {
			wp_send_json_error( 'nav_menus_missing_type_or_object_parameter' );
		}

		$type = sanitize_key( $_POST['type'] );
		$object = sanitize_key( $_POST['object'] );
		$page = empty( $_POST['page'] ) ? 0 : absint( $_POST['page'] );
		$items = $this->load_available_items_query( $type, $object, $page );

		if ( is_wp_error( $items ) ) {
			wp_send_json_error( $items->get_error_code() );
		} else {
			wp_send_json_success( array( 'items' => $items ) );
		}
	}

	public function load_available_items_query( $type = 'post_type', $object = 'page', $page = 0 ) {
		$items = array();

		if ( 'post_type' === $type ) {
			$post_type = get_post_type_object( $object );
			if ( ! $post_type ) {
				return new WP_Error( 'nav_menus_invalid_post_type' );
			}

			if ( 0 === $page && 'page' === $object ) {
				$items[] = array(
					'id'         => 'home',
					'title'      => 'Home',
					'type'       => 'custom',
					'type_label' => 'Custom Link',
					'object'     => '',
					'url'        => home_url(),
				);
			} elseif ( 'post' !== $object && 0 === $page && $post_type->has_archive ) {
				$items[] = array(
					'id'         => $object . '-archive',
					'title'      => $post_type->labels->archives,
					'type'       => 'post_type_archive',
					'type_label' => 'Post Type Archive',
					'object'     => $object,
					'url'        => get_post_type_archive_link( $object ),
				);
			}

			$posts = get_posts( array(
				'numberposts' => 10,
				'offset'      => 10 * $page,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'post_type'   => $object,
			) );
			foreach ( $posts as $post ) {
				$post_title = $post->post_title;
				if ( '' === $post_title ) {
					$post_title = sprintf( '#%d (no title)', $post->ID );
				}
				$items[] = array(
					'id'         => "post-{$post->ID}",
					'title'      => html_entity_decode( $post_title, ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'type'       => 'post_type',
					'type_label' => get_post_type_object( $post->post_type )->labels->singular_name,
					'object'     => $post->post_type,
					'object_id'  => intval( $post->ID ),
					'url'        => get_permalink( intval( $post->ID ) ),
				);
			}
		} elseif ( 'taxonomy' === $type ) {
			$terms = get_terms( $object, array(
				'child_of'     => 0,
				'exclude'      => '',
				'hide_empty'   => false,
				'hierarchical' => 1,
				'include'      => '',
				'number'       => 10,
				'offset'       => 10 * $page,
				'order'        => 'DESC',
				'orderby'      => 'count',
				'pad_counts'   => false,
			) );
			if ( is_wp_error( $terms ) ) {
				return $terms;
			}

			foreach ( $terms as $term ) {
				$items[] = array(
					'id'         => "term-{$term->term_id}",
					'title'      => html_entity_decode( $term->name, ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'type'       => 'taxonomy',
					'type_label' => get_taxonomy( $term->taxonomy )->labels->singular_name,
					'object'     => $term->taxonomy,
					'object_id'  => intval( $term->term_id ),
					'url'        => get_term_link( intval( $term->term_id ), $term->taxonomy ),
				);
			}
		}

		$items = apply_filters( 'customize_nav_menu_available_items', $items, $type, $object, $page );

		return $items;
	}

	public function ajax_search_available_items() {
		check_ajax_referer( 'customize-menus', 'customize-menus-nonce' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( -1 );
		}
		if ( empty( $_POST['search'] ) ) {
			wp_send_json_error( 'nav_menus_missing_search_parameter' );
		}
		$p = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 0;
		if ( $p < 1 ) {
			$p = 1;
		}

		$s = sanitize_text_field( wp_unslash( $_POST['search'] ) );
		$items = $this->search_available_items_query( array( 'pagenum' => $p, 's' => $s ) );

		if ( empty( $items ) ) {
			wp_send_json_error( array( 'message' => 'No results found.' ) );
		} else {
			wp_send_json_success( array( 'items' => $items ) );
		}
	}

	public function search_available_items_query( $args = array() ) {
		$items = array();
		$post_type_objects = get_post_types( array( 'show_in_nav_menus' => true ), 'objects' );
		$query = array(
			'post_type'              => array_keys( $post_type_objects ),
			'suppress_filters'       => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
		);

		$args['pagenum'] = isset( $args['pagenum'] ) ? absint( $args['pagenum'] ) : 1;
		$query['offset'] = $args['pagenum'] > 1 ? $query['posts_per_page'] * ( $args['pagenum'] - 1 ) : 0;

		if ( isset( $args['s'] ) ) {
			$query['s'] = $args['s'];
		}
		$get_posts = new WP_Query( $query );
		if ( $get_posts->post_count ) {
			foreach ( $get_posts->posts as $post ) {
				$post_title = $post->post_title;
				if ( '' === $post_title ) {
					$post_title = sprintf( '#%d (no title)', $post->ID );
				}
				$items[] = array(
					'id'         => 'post-' . $post->ID,
					'title'      => html_entity_decode( $post_title, ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'type'       => 'post_type',
					'type_label' => $post_type_objects[ $post->post_type ]->labels->singular_name,
					'object'     => $post->post_type,
					'object_id'  => intval( $post->ID ),
					'url'        => get_permalink( intval( $post->ID ) ),
				);
			}
		}
		$taxonomies = get_taxonomies( array( 'show_in_nav_menus' => true ), 'names' );
		$terms = get_terms( $taxonomies, array(
			'name__like' => $args['s'],
			'number'     => 20,
			'offset'     => 20 * ($args['pagenum'] - 1),
		) );
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$items[] = array(
					'id'         => 'term-' . $term->term_id,
					'title'      => html_entity_decode( $term->name, ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'type'       => 'taxonomy',
					'type_label' => get_taxonomy( $term->taxonomy )->labels->singular_name,
					'object'     => $term->taxonomy,
					'object_id'  => intval( $term->term_id ),
					'url'        => get_term_link( intval( $term->term_id ), $term->taxonomy ),
				);
			}
		}

		$items = apply_filters( 'customize_nav_menu_searched_items', $items, $args );

		return $items;
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'customize-nav-menus' );
		wp_enqueue_script( 'customize-nav-menus' );

		$temp_nav_menu_setting      = new WP_Customize_Nav_Menu_Setting( $this->manager, 'nav_menu[-1]' );
		$temp_nav_menu_item_setting = new WP_Customize_Nav_Menu_Item_Setting( $this->manager, 'nav_menu_item[-1]' );

		// Pass data to JS.
		$settings = array(
			'allMenus'             => wp_get_nav_menus(),
			'itemTypes'            => $this->available_item_types(),
			'l10n'                 => array(
				'untitled'          =>'(no label)',
				'unnamed'           => '(unnamed)',
				'custom_label'      => 'Custom Link',
				'menuLocation'      => '(Currently set to: %s)',
				'menuNameLabel'     => 'Menu Name',
				'itemAdded'         => 'Menu item added',
				'itemDeleted'       => 'Menu item deleted',
				'menuAdded'         => 'Menu created',
				'menuDeleted'       => 'Menu deleted',
				'movedUp'           => 'Menu item moved up',
				'movedDown'         => 'Menu item moved down',
				'movedLeft'         => 'Menu item moved out of submenu',
				'movedRight'        => 'Menu item is now a sub-item',
				'customizingMenus'  => sprintf( 'Customizing &#9656; %s', esc_html( $this->manager->get_panel( 'nav_menus' )->title ) ),
				'invalidTitleTpl'   => '%s (Invalid)',
				'pendingTitleTpl'   => '%s (Pending)',
				'itemsFound'        => 'Number of items found: %d',
				'itemsFoundMore'    => 'Additional items found: %d',
				'itemsLoadingMore'  => 'Loading more results... please wait.',
				'reorderModeOn'     => 'Reorder mode enabled',
				'reorderModeOff'    => 'Reorder mode closed',
				'reorderLabelOn'    => esc_attr__( 'Reorder menu items' ),
				'reorderLabelOff'   => esc_attr__( 'Close reorder mode' ),
			),
			'settingTransport'     => 'postMessage',
			'phpIntMax'            => PHP_INT_MAX,
			'defaultSettingValues' => array(
				'nav_menu'      => $temp_nav_menu_setting->default,
				'nav_menu_item' => $temp_nav_menu_item_setting->default,
			),
			'locationSlugMappedToName' => get_registered_nav_menus(),
		);

		$data = sprintf( 'var _wpCustomizeNavMenusSettings = %s;', wp_json_encode( $settings ) );
		wp_scripts()->add_data( 'customize-nav-menus', 'data', $data );

		// This is copied from nav-menus.php, and it has an unfortunate object name of `menus`.
		$nav_menus_l10n = array(
			'oneThemeLocationNoMenus' => null,
			'moveUp'       => 'Move up one',
			'moveDown'     => 'Move down one',
			'moveToTop'    => 'Move to the top',
			'moveUnder'    => 'Move under %s',
			'moveOutFrom'  => 'Move out from under %s',
			'under'        => 'Under %s',
			'outFrom'      => 'Out from under %s',
			'menuFocus'    => '%1$s. Menu item %2$d of %3$d.',
			'subMenuFocus' => '%1$s. Sub item number %2$d under %3$s.',
		);
		wp_localize_script( 'nav-menu', 'menus', $nav_menus_l10n );
	}

	public function filter_dynamic_setting_args( $setting_args, $setting_id ) {
		if ( preg_match( WP_Customize_Nav_Menu_Setting::ID_PATTERN, $setting_id ) ) {
			$setting_args = array(
				'type'      => WP_Customize_Nav_Menu_Setting::TYPE,
				'transport' => 'postMessage',
			);
		} elseif ( preg_match( WP_Customize_Nav_Menu_Item_Setting::ID_PATTERN, $setting_id ) ) {
			$setting_args = array(
				'type'      => WP_Customize_Nav_Menu_Item_Setting::TYPE,
				'transport' => 'postMessage',
			);
		}
		return $setting_args;
	}

	public function filter_dynamic_setting_class( $setting_class, $setting_id, $setting_args ) {
		unset( $setting_id );

		if ( ! empty( $setting_args['type'] ) && WP_Customize_Nav_Menu_Setting::TYPE === $setting_args['type'] ) {
			$setting_class = 'WP_Customize_Nav_Menu_Setting';
		} elseif ( ! empty( $setting_args['type'] ) && WP_Customize_Nav_Menu_Item_Setting::TYPE === $setting_args['type'] ) {
			$setting_class = 'WP_Customize_Nav_Menu_Item_Setting';
		}
		return $setting_class;
	}

	public function customize_register() {
		$this->manager->register_panel_type( 'WP_Customize_Nav_Menus_Panel' );
		$this->manager->register_control_type( 'WP_Customize_Nav_Menu_Control' );
		$this->manager->register_control_type( 'WP_Customize_Nav_Menu_Name_Control' );
		$this->manager->register_control_type( 'WP_Customize_Nav_Menu_Auto_Add_Control' );
		$this->manager->register_control_type( 'WP_Customize_Nav_Menu_Item_Control' );
		$description = '<p>' . __( 'This panel is used for managing navigation menus for content you have already published on your site. You can create menus and add items for existing content such as pages, posts, categories, tags, formats, or custom links.' ) . '</p>';
		if ( current_theme_supports( 'widgets' ) ) {
			$description .= '<p>' . sprintf( __( 'Menus can be displayed in locations defined by your theme or in <a href="%s">widget areas</a> by adding a &#8220;Custom Menu&#8221; widget.' ), "javascript:wp.customize.panel( 'widgets' ).focus();" ) . '</p>';
		} else {
			$description .= '<p>Menus can be displayed in locations defined by your theme.</p>';
		}
		$this->manager->add_panel( new WP_Customize_Nav_Menus_Panel( $this->manager, 'nav_menus', array(
			'title'       => 'Menus',
			'description' => $description,
			'priority'    => 100,
			// 'theme_supports' => 'menus|widgets', @todo allow multiple theme supports
		) ) );
		$menus = wp_get_nav_menus();

		// Menu locations.
		$locations     = get_registered_nav_menus();
		$num_locations = count( array_keys( $locations ) );
		if ( 1 == $num_locations ) {
			$description = '<p>Your theme supports one menu. Select which menu you would like to use.';
		} else {
			$description = '<p>' . sprintf( _n( 'Your theme supports %s menu. Select which menu appears in each location.', 'Your theme supports %s menus. Select which menu appears in each location.', $num_locations ), number_format_i18n( $num_locations ) );
		}
		$description  .= '</p><p>You can also place menus in widget areas with the Custom Menu widget.</p>';

		$this->manager->add_section( 'menu_locations', array(
			'title'       => 'Menu Locations',
			'panel'       => 'nav_menus',
			'priority'    => 5,
			'description' => $description,
		) );

		$choices = array( '0' => '&mdash; Select &mdash;' );
		foreach ( $menus as $menu ) {
			$choices[ $menu->term_id ] = wp_html_excerpt( $menu->name, 40, '&hellip;' );
		}

		foreach ( $locations as $location => $description ) {
			$setting_id = "nav_menu_locations[{$location}]";

			$setting = $this->manager->get_setting( $setting_id );
			if ( $setting ) {
				$setting->transport = 'postMessage';
				remove_filter( "customize_sanitize_{$setting_id}", 'absint' );
				add_filter( "customize_sanitize_{$setting_id}", array( $this, 'intval_base10' ) );
			} else {
				$this->manager->add_setting( $setting_id, array(
					'sanitize_callback' => array( $this, 'intval_base10' ),
					'theme_supports'    => 'menus',
					'type'              => 'theme_mod',
					'transport'         => 'postMessage',
					'default'           => 0,
				) );
			}

			$this->manager->add_control( new WP_Customize_Nav_Menu_Location_Control( $this->manager, $setting_id, array(
				'label'       => $description,
				'location_id' => $location,
				'section'     => 'menu_locations',
				'choices'     => $choices,
			) ) );
		}

		foreach ( $menus as $menu ) {
			$menu_id = $menu->term_id;
			$section_id = 'nav_menu[' . $menu_id . ']';
			$this->manager->add_section( new WP_Customize_Nav_Menu_Section( $this->manager, $section_id, array(
				'title'     => html_entity_decode( $menu->name, ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'priority'  => 10,
				'panel'     => 'nav_menus',
			) ) );

			$nav_menu_setting_id = 'nav_menu[' . $menu_id . ']';
			$this->manager->add_setting( new WP_Customize_Nav_Menu_Setting( $this->manager, $nav_menu_setting_id, array(
				'transport' => 'postMessage',
			) ) );
			$menu_items = (array) wp_get_nav_menu_items( $menu_id );

			foreach ( array_values( $menu_items ) as $i => $item ) {
				$menu_item_setting_id = 'nav_menu_item[' . $item->ID . ']';

				$value = (array) $item;
				$value['nav_menu_term_id'] = $menu_id;
				$this->manager->add_setting( new WP_Customize_Nav_Menu_Item_Setting( $this->manager, $menu_item_setting_id, array(
					'value'     => $value,
					'transport' => 'postMessage',
				) ) );
				$this->manager->add_control( new WP_Customize_Nav_Menu_Item_Control( $this->manager, $menu_item_setting_id, array(
					'label'    => $item->title,
					'section'  => $section_id,
					'priority' => 10 + $i,
				) ) );
			}
		}

		$this->manager->add_section( new WP_Customize_New_Menu_Section( $this->manager, 'add_menu', array(
			'title'    => 'Add a Menu',
			'panel'    => 'nav_menus',
			'priority' => 999,
		) ) );

		$this->manager->add_control( 'new_menu_name', array(
			'label'       => '',
			'section'     => 'add_menu',
			'type'        => 'text',
			'settings'    => array(),
			'input_attrs' => array(
				'class'       => 'menu-name-field',
				'placeholder' => 'New menu name',
			),
		) );

		$this->manager->add_control( new WP_Customize_New_Menu_Control( $this->manager, 'create_new_menu', array(
			'section'  => 'add_menu',
			'settings' => array(),
		) ) );
	}

	public function intval_base10( $value ) {
		return intval( $value, 10 );
	}

	public function available_item_types() {
		$item_types = array();

		$post_types = get_post_types( array( 'show_in_nav_menus' => true ), 'objects' );
		if ( $post_types ) {
			foreach ( $post_types as $slug => $post_type ) {
				$item_types[] = array(
					'title'  => $post_type->labels->name,
					'type'   => 'post_type',
					'object' => $post_type->name,
				);
			}
		}

		$taxonomies = get_taxonomies( array( 'show_in_nav_menus' => true ), 'objects' );
		if ( $taxonomies ) {
			foreach ( $taxonomies as $slug => $taxonomy ) {
				if ( 'post_format' === $taxonomy && ! current_theme_supports( 'post-formats' ) ) {
					continue;
				}
				$item_types[] = array(
					'title'  => $taxonomy->labels->name,
					'type'   => 'taxonomy',
					'object' => $taxonomy->name,
				);
			}
		}

		$item_types = apply_filters( 'customize_nav_menu_available_item_types', $item_types );

		return $item_types;
	}

	public function print_templates() {
		?>
		<script type="text/html" id="tmpl-available-menu-item">
			<li id="menu-item-tpl-{{ data.id }}" class="menu-item-tpl" data-menu-item-id="{{ data.id }}">
				<div class="menu-item-bar">
					<div class="menu-item-handle">
						<span class="item-type" aria-hidden="true">{{ data.type_label }}</span>
						<span class="item-title" aria-hidden="true">
							<span class="menu-item-title<# if ( ! data.title ) { #> no-title<# } #>">{{ data.title || wp.customize.Menus.data.l10n.untitled }}</span>
						</span>
						<button type="button" class="button-link item-add">
							<span class="screen-reader-text"><?php
								printf( 'Add to menu: %1$s (%2$s)', '{{ data.title || wp.customize.Menus.data.l10n.untitled }}', '{{ data.type_label }}' );
							?></span>
						</button>
					</div>
				</div>
			</li>
		</script>

		<script type="text/html" id="tmpl-menu-item-reorder-nav">
			<div class="menu-item-reorder-nav">
				<?php
				printf(
					'<button type="button" class="menus-move-up">%1$s</button><button type="button" class="menus-move-down">%2$s</button><button type="button" class="menus-move-left">%3$s</button><button type="button" class="menus-move-right">%4$s</button>',
					'Move up',
					'Move down',
					'Move one level up',
					'Move one level down'
				);
				?>
			</div>
		</script>
	<?php
	}

	public function available_items_template() {
		?>
		<div id="available-menu-items" class="accordion-container">
			<div class="customize-section-title">
				<button type="button" class="customize-section-back" tabindex="-1">
					<span class="screen-reader-text">Back</span>
				</button>
				<h3>
					<span class="customize-action">
						<?php
							printf( 'Customizing &#9656; %s', esc_html( $this->manager->get_panel( 'nav_menus' )->title ) );
						?>
					</span>
					Add Menu Items
				</h3>
			</div>
			<div id="available-menu-items-search" class="accordion-section cannot-expand">
				<div class="accordion-section-title">
					<label class="screen-reader-text" for="menu-items-search">Search Menu Items</label>
					<input type="text" id="menu-items-search" placeholder="Search menu items&hellip;" aria-describedby="menu-items-search-desc" />
					<p class="screen-reader-text" id="menu-items-search-desc">The search results will be updated as you type.</p>
					<span class="spinner"></span>
					<span class="clear-results"><span class="screen-reader-text">Clear Results</span></span>
				</div>
				<ul class="accordion-section-content" data-type="search"></ul>
			</div>
			<div id="new-custom-menu-item" class="accordion-section">
				<h4 class="accordion-section-title" role="presentation">
					Custom Links
					<button type="button" class="button-link" aria-expanded="false">
						<span class="screen-reader-text">Toggle section: Custom Links</span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
				</h4>
				<div class="accordion-section-content customlinkdiv">
					<input type="hidden" value="custom" id="custom-menu-item-type" name="menu-item[-1][menu-item-type]" />
					<p id="menu-item-url-wrap" class="wp-clearfix">
						<label class="howto" for="custom-menu-item-url">URL</label>
						<input id="custom-menu-item-url" name="menu-item[-1][menu-item-url]" type="text" class="code menu-item-textbox" value="http://">
					</p>
					<p id="menu-item-name-wrap" class="wp-clearfix">
						<label class="howto" for="custom-menu-item-name">Link Text</label>
						<input id="custom-menu-item-name" name="menu-item[-1][menu-item-title]" type="text" class="regular-text menu-item-textbox">
					</p>
					<p class="button-controls">
						<span class="add-to-menu">
							<input type="submit" class="button-secondary submit-add-to-menu right" value="Add to Menu" name="add-custom-menu-item" id="custom-menu-item-submit">
							<span class="spinner"></span>
						</span>
					</p>
				</div>
			</div>
			<?php
			foreach ( $this->available_item_types() as $available_item_type ) {
				$id = sprintf( 'available-menu-items-%s-%s', $available_item_type['type'], $available_item_type['object'] );
				?>
				<div id="<?php echo esc_attr( $id ); ?>" class="accordion-section">
					<h4 class="accordion-section-title" role="presentation">
						<?php echo esc_html( $available_item_type['title'] ); ?>
						<span class="spinner"></span>
						<span class="no-items">No items</span>
						<button type="button" class="button-link" aria-expanded="false">
							<span class="screen-reader-text"><?php
							printf( 'Toggle section: %s', esc_html( $available_item_type['title'] ) ); ?></span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
					</h4>
					<ul class="accordion-section-content" data-type="<?php echo esc_attr( $available_item_type['type'] ); ?>" data-object="<?php echo esc_attr( $available_item_type['object'] ); ?>"></ul>
				</div>
				<?php
			}
			?>
		</div>
	<?php
	}

	public $preview_nav_menu_instance_args = array();

	public function customize_dynamic_partial_args( $partial_args, $partial_id ) {

		if ( preg_match( '/^nav_menu_instance\[[0-9a-f]{32}\]$/', $partial_id ) ) {
			if ( false === $partial_args ) {
				$partial_args = array();
			}
			$partial_args = array_merge(
				$partial_args,
				array(
					'type'                => 'nav_menu_instance',
					'render_callback'     => array( $this, 'render_nav_menu_partial' ),
					'container_inclusive' => true,
					'settings'            => array(),
					'capability'          => 'edit_theme_options',
				)
			);
		}

		return $partial_args;
	}

	public function customize_preview_init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'customize_preview_enqueue_deps' ) );
		add_filter( 'wp_nav_menu_args', array( $this, 'filter_wp_nav_menu_args' ), 1000 );
		add_filter( 'wp_nav_menu', array( $this, 'filter_wp_nav_menu' ), 10, 2 );
		add_filter( 'wp_footer', array( $this, 'export_preview_data' ), 1 );
		add_filter( 'customize_render_partials_response', array( $this, 'export_partial_rendered_nav_menu_instances' ) );
	}

	public function filter_wp_nav_menu_args( $args ) {
		$can_partial_refresh = (
			! empty( $args['echo'] )
			&&
			( empty( $args['fallback_cb'] ) || is_string( $args['fallback_cb'] ) )
			&&
			( empty( $args['walker'] ) || is_string( $args['walker'] ) )
			&& (
				! empty( $args['theme_location'] )
				||
				( ! empty( $args['menu'] ) && ( is_numeric( $args['menu'] ) || is_object( $args['menu'] ) ) )
			)
			&&
			(
				! empty( $args['container'] )
				||
				( isset( $args['items_wrap'] ) && '<' === substr( $args['items_wrap'], 0, 1 ) )
			)
		);
		$args['can_partial_refresh'] = $can_partial_refresh;

		$exported_args = $args;

		if ( ! $can_partial_refresh ) {
			$exported_args['fallback_cb'] = '';
			$exported_args['walker'] = '';
		}

		if ( ! empty( $exported_args['menu'] ) && is_object( $exported_args['menu'] ) ) {
			$exported_args['menu'] = $exported_args['menu']->term_id;
		}

		ksort( $exported_args );
		$exported_args['args_hmac'] = $this->hash_nav_menu_args( $exported_args );

		$args['customize_preview_nav_menus_args'] = $exported_args;
		$this->preview_nav_menu_instance_args[ $exported_args['args_hmac'] ] = $exported_args;
		return $args;
	}

	public function filter_wp_nav_menu( $nav_menu_content, $args ) {
		if ( isset( $args->customize_preview_nav_menus_args['can_partial_refresh'] ) && $args->customize_preview_nav_menus_args['can_partial_refresh'] ) {
			$attributes = sprintf( ' data-customize-partial-id="%s"', esc_attr( 'nav_menu_instance[' . $args->customize_preview_nav_menus_args['args_hmac'] . ']' ) );
			$attributes .= ' data-customize-partial-type="nav_menu_instance"';
			$attributes .= sprintf( ' data-customize-partial-placement-context="%s"', esc_attr( wp_json_encode( $args->customize_preview_nav_menus_args ) ) );
			$nav_menu_content = preg_replace( '#^(<\w+)#', '$1 ' . $attributes, $nav_menu_content, 1 );
		}
		return $nav_menu_content;
	}

	public function hash_nav_menu_args( $args ) {
		return wp_hash( serialize( $args ) );
	}

	public function customize_preview_enqueue_deps() {
		wp_enqueue_script( 'customize-preview-nav-menus' );
		wp_enqueue_style( 'customize-preview' );
	}

	public function export_preview_data() {
		$exports = array(
			'navMenuInstanceArgs' => $this->preview_nav_menu_instance_args,
		);
		printf( '<script>var _wpCustomizePreviewNavMenusExports = %s;</script>', wp_json_encode( $exports ) );
	}

	public function export_partial_rendered_nav_menu_instances( $response ) {
		$response['nav_menu_instance_args'] = $this->preview_nav_menu_instance_args;
		return $response;
	}

	public function render_nav_menu_partial( $partial, $nav_menu_args ) {
		unset( $partial );

		if ( ! isset( $nav_menu_args['args_hmac'] ) ) {
			return false;
		}

		$nav_menu_args_hmac = $nav_menu_args['args_hmac'];
		unset( $nav_menu_args['args_hmac'] );

		ksort( $nav_menu_args );
		if ( ! hash_equals( $this->hash_nav_menu_args( $nav_menu_args ), $nav_menu_args_hmac ) ) {
			// Error: args_hmac_mismatch.
			return false;
		}

		ob_start();
		wp_nav_menu( $nav_menu_args );
		$content = ob_get_clean();

		return $content;
	}
}
